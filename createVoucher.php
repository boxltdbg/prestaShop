<?php
/**
 * 2007-2024 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2024 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
require_once '../../config/config.inc.php';
require_once '../../init.php';
require_once 'boxnow.php';
$databaseConfig = require_once '../../app/config/parameters.php';

$servername = $databaseConfig['parameters']['database_host'];
$username = $databaseConfig['parameters']['database_user'];
$password = $databaseConfig['parameters']['database_password'];
$database = $databaseConfig['parameters']['database_name'];
$prefix = $databaseConfig['parameters']['database_prefix'];

try {
    $pdo = new PDO(
        "mysql:host=$servername;dbname=$database",
        $username,
        $password,
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'")
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (Exception $e) {
    echo 'Exception -> ';
    var_dump($e->getMessage());
}

if (!isset($_POST['order_id'], $_POST['Compartment_Size'], $_POST['warehouses'])) {
    die('Missing required parameters.');
}


// get vars
$order_id = $_POST['order_id'];
$Comp_S = (int) $_POST['Compartment_Size'];
// get configuration
$apisettings = $pdo->prepare("SELECT * FROM " . $prefix . "configuration WHERE name like 'BOXNOW_%'");
$apisettings->execute();
$apisettings = $apisettings->fetchAll();
$apisets = array();
foreach ($apisettings as $apis) {
    $apisets[$apis['name']] = $apis['value'];
}

$api_url = $apisets['BOXNOW_API_URL'];
$api_id = $apisets['BOXNOW_OAUTH_CLIENT_ID'];
$api_secret = $apisets['BOXNOW_OAUTH_CLIENT_SECRET'];
$api_warehouse = $apisets['BOXNOW_WAREHOUSE_NUMBER'];
$api_partner = $apisets['BOXNOW_PARTNER_ID'];
$api_email = $apisets['BOXNOW_CONTACT_EMAIL'];
$api_name = $apisets['BOXNOW_CONTACT_NAME'];
$api_contact = $apisets['BOXNOW_CONTACT_NUMBER'];

// get api session

$post_dt = '{
	"grant_type": "client_credentials",
	"client_id": "' . $api_id . '",
	"client_secret": "' . $api_secret . '"
  }';
$auth = curl_init('https://' . $api_url . '/api/v1/auth-sessions'); // Initialise cURL
curl_setopt($auth, CURLOPT_USERAGENT, 'BoxNow-PrestaShop-Module/2.0');
curl_setopt($auth, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); // Inject the token into the header
curl_setopt($auth, CURLOPT_RETURNTRANSFER, true);
curl_setopt($auth, CURLOPT_POST, 1); // Specify the request method as POST
curl_setopt($auth, CURLOPT_POSTFIELDS, $post_dt); // Set the posted fields
curl_setopt($auth, CURLOPT_FOLLOWLOCATION, 1); // This will follow any redirects
$auth_response = curl_exec($auth);
$auth_http_status = curl_getinfo($auth, CURLINFO_HTTP_CODE);
$auth_json = json_decode($auth_response, true);
curl_close($auth);

if ($auth_http_status != 200) {
    $authMsg = is_array($auth_json) && isset($auth_json['message']) ? $auth_json['message'] : 'Unknown error';
    die('Authentication failed (HTTP ' . (int) $auth_http_status . '): ' . $authMsg);
}


// get orderinfo
$objOrder = new Order($order_id);
$customer = new Customer($objOrder->id_customer);
$address = new Address($objOrder->id_address_delivery);
$state = new State($address->id_state);
$country = new Country($address->id_country);
$products = $objOrder->getProducts();

if ($objOrder->module == 'ps_cashondelivery') {
    $paymentMode = 'cod';
    $payment_type = 2;
    $amountcollect = number_format(floor($objOrder->total_paid * 100) / 100, 2, '.', '');
} else {
    $paymentMode = 'prepaid';
    $payment_type = 1;
    $amountcollect = 0.00;
}

// get order boxnow info
$boxnoworder = $pdo->prepare("SELECT * FROM " . $prefix . "boxnow_entries WHERE id_order='$order_id' LIMIT 1");
$boxnoworder->execute();
$boxnoworder = $boxnoworder->fetch();

// get
$orderid = $order_id . uniqid();
$apicontact = $api_contact;
$addressphone = Boxnow::formatPhone($address->phone, $api_url);
$invoiceValue = number_format(floor($objOrder->total_paid * 100) / 100, 2, '.', '');
$warehouse = $_POST['warehouses'];

$totalItemValue = 0;
$totalItemWeight = 0;
foreach ($products as $v) {
    $totalItemValue += $v['unit_price_tax_incl'] * $v['product_quantity'];
    $totalItemWeight += $v['product_weight'] * $v['product_quantity'];
}
$totalItemValue = number_format(floor($totalItemValue * 100) / 100, 2, '.', '');

$items_prod = '[{
        "id": "' . $order_id . '",
        "name": "Order #' . $order_id . '",
        "value": "' . $totalItemValue . '",
        "weight": ' . $totalItemWeight . ',
        "compartmentSize": ' . (int) $Comp_S . '
    }]';

$postData = '{
        "orderNumber": "' . $orderid . '",
        "invoiceValue": "' . $invoiceValue . '",
        "paymentMode": "' . $paymentMode . '",
        "amountToBeCollected" : "' . $amountcollect . '",
        "allowReturn": true,
        "origin": {
            "contactNumber": "' . $apicontact . '",
            "contactEmail": "' . $api_email . '",
            "contactName": "' . $api_name . '",
            "locationId": "' . $_POST['warehouses'] . '"
        },
        "destination": {
            "contactNumber": "' . $addressphone . '",
            "contactEmail": "' . $customer->email . '",
            "contactName": "' . $address->firstname . ' ' . $address->lastname . '",
            "name": "' . $boxnoworder['locker_name'] . '",
            "addressLine1": "' . $address->address1 . '",
            "locationId": "' . $boxnoworder['locker_id'] . '"
        }, "items": ' . $items_prod . '}';

$authorization = "Authorization: Bearer " . $auth_json['access_token'];
$delivery_request = curl_init('https://' . $api_url . '/api/v1/delivery-requests');
curl_setopt($delivery_request, CURLOPT_USERAGENT, 'BoxNow-PrestaShop-Module/2.0');
curl_setopt($delivery_request, CURLOPT_HTTPHEADER, array($authorization, 'Content-Type: application/json'));
curl_setopt($delivery_request, CURLOPT_RETURNTRANSFER, true);
curl_setopt($delivery_request, CURLOPT_POST, 1);
curl_setopt($delivery_request, CURLOPT_POSTFIELDS, $postData);
curl_setopt($delivery_request, CURLOPT_FOLLOWLOCATION, 1);
$delivery_response = curl_exec($delivery_request);
$delivery_http_status = curl_getinfo($delivery_request, CURLINFO_HTTP_CODE);
$delivery = json_decode($delivery_response, true);
curl_close($delivery_request);

if (!empty($delivery['parcels'][0]['id'])) {
    // get old vouchers
    $oldvoucher = $pdo->prepare("SELECT parcel_ids FROM " . $prefix . "boxnow_entries WHERE id_order='$order_id' LIMIT 1");
    $oldvoucher->execute();
    $oldvoucher = $oldvoucher->fetch();
    $trackid = $oldvoucher['parcel_ids'] . $delivery['parcels'][0]['id'] . ',';
    $totalvoucher = 1; // Only one voucher per order
    $sql = "UPDATE " . $prefix . "boxnow_entries SET parcel_ids='$trackid',warehouse_id='$warehouse',vouchers='$totalvoucher',payment_type='$payment_type' WHERE id_order=" . $order_id;
    $insert = $pdo->exec($sql);
} else {
    $errors_arr = array(
        "P400" => "Invalid request data Make sure you are sending the request according to the documentation.",
        "P401" => "Invalid request origin location reference Make sure you are referencing a valid location ID from Origins endpoint or valid address.",
        "P402" => "Invalid request destination location reference Make sure you are referencing a valid location ID from Destinations endpoint or valid address",
        "P403" => "You are not allowed to use AnyAPM SameAPM delivery Contact support if you believe this is a mistake",
        "P404" => "Invalid import CSV See error contents for additional info.",
        "P405" => "Invalid phone number Make sure you are sending the phone number in full international format, e.g. +30 xx x xxx xxxx",
        "P406" => "Invalid compartment/parcel size Make sure you are sending one of required sizes 1, 2 or 3 ( Medium or Large) Size is required when sen ding from AnyAPM directly",
        "P407" => "Invalid country code Make sure you are sending country code in ISO 3166 1 alpha 2 format, e.g. GR.",
        "P410" => "Order number conflict You are trying to create a delivery request for order ID that has already been created. Choose another order ID",
        "P411" => "You are not eligible to use Cash on delivery payment type Use another payment type or contact our support.",
        "P420" => "Parcel not ready for cancel You can cancel only new, undelivered, or parcels that are not returned or lost. Make sure parcel is in transit and try again.",
        "P430" => "Parcel not ready for AnyAPM confirmation Parcel is probably already confirmed or being delivered. Contact support if you believe this is a mistake.",
    );

    // $delivery may be null (empty/non-JSON body) or an error object without a "code".
    // Build a message that always tells us what actually happened, null-safely.
    $code = is_array($delivery) && isset($delivery['code']) ? $delivery['code'] : '';
    $known = ($code !== '' && isset($errors_arr[$code])) ? $errors_arr[$code] : '';
    $apiMessage = is_array($delivery) && isset($delivery['message']) ? $delivery['message'] : '';

    $parts = array();
    $parts[] = 'HTTP ' . (int) $delivery_http_status;
    if ($code !== '') {
        $parts[] = 'code ' . $code;
    }
    if ($known !== '') {
        $parts[] = $known;
    }
    if ($apiMessage !== '') {
        $parts[] = $apiMessage;
    }
    // If BoxNow returned nothing useful, fall back to the raw response for diagnosis.
    if ($code === '' && $known === '' && $apiMessage === '') {
        $parts[] = 'raw response: ' . ($delivery_response === false || $delivery_response === '' ? '(empty)' : $delivery_response);
    }

    PrestaShopLogger::addLog('BOXNOW createVoucher failed: ' . implode(' | ', $parts), 3);
    echo 'Error: ' . implode(' - ', $parts);
}
