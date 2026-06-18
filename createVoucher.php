<?php
include ('../../config/config.inc.php');
include ('../../init.php');
include '../../config/settings.inc.php';

$servername = _DB_SERVER_;
$username = _DB_USER_;
$password = _DB_PASSWD_;
$database = _DB_NAME_;
$prefix = _DB_PREFIX_;

try {
$pdo = new PDO("mysql:host=$servername;dbname=$database", $username, $password,
       array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
}
catch(Exception $e) {
    echo 'Exception -> ';
    var_dump($e->getMessage());
}

header('Content-Type: application/json');

if(!isset($_POST['order_id'])){ die(json_encode(array('success'=>false,'error'=>'something is wrong'))); }

//get vars
$order_id = (int)$_POST['order_id'];
//get configuration (use Configuration::get so the correct shop context value is read)
$api_url = Configuration::get('BOXNOW_API_URL');
$api_id = Configuration::get('BOXNOW_OAUTH_CLIENT_ID');
$api_secret = Configuration::get('BOXNOW_OAUTH_CLIENT_SECRET');
$api_warehouse = Configuration::get('BOXNOW_WAREHOUSE_NUMBER');
$api_partner = Configuration::get('BOXNOW_PARTNER_ID');
$api_email = Configuration::get('BOXNOW_CONTACT_EMAIL');
$api_name = Configuration::get('BOXNOW_CONTACT_NAME');
$api_contact = Configuration::get('BOXNOW_CONTACT_NUMBER');

// strip any accidental scheme/trailing slash from the base url
$api_url = preg_replace('#^https?://#', '', trim($api_url));
$api_url = rtrim($api_url, '/');


//get api session
$auth = curl_init();

        curl_setopt_array($auth, array(
            CURLOPT_URL => 'https://' . $api_url . '/api/v1/auth-sessions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            CURLOPT_POSTFIELDS => json_encode(array(
                'grant_type' => 'client_credentials',
                'client_id' => $api_id,
                'client_secret' => $api_secret,
            )),
        ));

        $auth_response = curl_exec($auth);

        $auth_http_status = curl_getinfo($auth, CURLINFO_HTTP_CODE);
        curl_close($auth);

        if ($auth_http_status != 200) {
            die(json_encode(array('success'=>false,'error'=>'Authentication with BoxNow failed')));
        }

        $auth_json = json_decode($auth_response, true);

//get orderinfo
$objOrder = new Order($order_id);
$customer = new Customer($objOrder->id_customer);
$address = new Address($objOrder->id_address_delivery);
$state = new State($address->id_state);
$country = new Country($address->id_country);
$products = $objOrder->getProducts(); 

// Native PrestaShop Cash on Delivery is "cashondelivery" on 1.6 and "ps_cashondelivery" on 1.7
$cod_modules = array('cashondelivery', 'ps_cashondelivery');
if (in_array($objOrder->module, $cod_modules)) {
	$paymentMode = 'cod';
	$payment_type = 2;
	// amount to collect on delivery = products price + delivery fee (the full order total)
	$amountcollect = number_format(floor($objOrder->total_paid * 100) / 100, 2, '.', '');
} else {
	$paymentMode = 'prepaid';
	$payment_type = 1;
	$amountcollect = 0.00;
}


//get order boxnow info
$boxnoworder = $pdo->prepare("SELECT * FROM ".$prefix."boxnow_entries WHERE id_order='$order_id' LIMIT 1");
$boxnoworder->execute();
$boxnoworder = $boxnoworder->fetch();

//create a single voucher for the whole order in boxnow
$orderid = $order_id.uniqid();
$apicontact = "+30".$api_contact;
$addressphone = "+30".$address->phone;
$invoiceValue = number_format(floor($objOrder->total_paid*100)/100,2, '.', '');
$warehouse = $_POST['warehouses'];

// default compartment / box size (1, 2 or 3); fall back to 2 when not provided
$compartmentSize = isset($_POST['compartment_size']) && (int)$_POST['compartment_size'] > 0
	? (int)$_POST['compartment_size']
	: 2;

$post_data = [
	'orderNumber' => "$orderid",
	'invoiceValue' => "$invoiceValue",
	'paymentMode' => $paymentMode,
	'amountToBeCollected' => "$amountcollect",
	'allowReturn' => false,
	'origin' => [
		'contactNumber' => "$apicontact",
		'contactEmail' => "$api_email",
		'contactName' => "$api_name",
		'locationId' => $_POST['warehouses'],
	],
	'destination' => [
		'contactNumber' => "$addressphone",
		'contactEmail' => "$customer->email",
		'contactName' => "$address->firstname $address->lastname",
		'name' => $boxnoworder['locker_name'],
		'addressLine1' => "$address->address1",
		'country' => "$country->iso_code",
		'postalCode' => "$address->postcode",
		'note' => "$address->other",
		'locationId' => $boxnoworder['locker_id'],
	],
];

// single combined item for the whole order
$combined_value = 0;
$combined_weight = 0;
$item_names = array();
foreach ($products as $k => $v) {
	$combined_value += ($v['unit_price_tax_incl'] * $v['product_quantity']);
	$combined_weight += ($v['product_weight'] * $v['product_quantity']);
	$item_names[] = $v['product_name'];
}
$combined_value = number_format(floor($combined_value*100)/100,2, '.', '');
$post_data['items'][] = [
	'id' => (string)$order_id,
	'name' => implode(', ', $item_names),
	'value' => "$combined_value",
	'weight' => $combined_weight,
	'compartmentSize' => $compartmentSize,
];

$data_json = json_encode($post_data);
$authorization = "Authorization: Bearer " . $auth_json['access_token'];
$delivery_request = curl_init();

curl_setopt_array($delivery_request, array(
	CURLOPT_URL => 'https://' . $api_url . '/api/v1/delivery-requests',
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_ENCODING => '',
	CURLOPT_MAXREDIRS => 10,
	CURLOPT_TIMEOUT => 0,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	CURLOPT_CUSTOMREQUEST => 'POST',
	CURLOPT_HTTPHEADER => array(
		$authorization,
		'Content-Type: application/json'
	),
	CURLOPT_POSTFIELDS => $data_json
));

$delivery_response = curl_exec($delivery_request);

$delivery_http_status = curl_getinfo($delivery_request, CURLINFO_HTTP_CODE);
curl_close($delivery_request);
$delivery = json_decode($delivery_response,true);

if(!empty($delivery['parcels'][0]['id'])){
	$voucher_id = $delivery['parcels'][0]['id'];
	// always a single voucher for the whole order
	$trackid = $voucher_id.',';
	$totalvoucher = 1;
	$sql = "UPDATE ".$prefix."boxnow_entries SET vouchers_numbers=".$pdo->quote($trackid).",warehouse_id=".$pdo->quote($warehouse).",vouchers='$totalvoucher',payment_type='$payment_type' WHERE id_order=".$order_id;
	$pdo->exec($sql);
	die(json_encode(array('success'=>true,'vouchers'=>array($voucher_id))));
} else {
	$errors_arr = array(
		"P400"=>"Invalid request data Make sure you are sending the request according to the documentation.",
		"P401"=>"Invalid request origin location reference Make sure you are referencing a valid location ID from Origins endpoint or valid address.",
		"P402"=>"Invalid request destination location reference Make sure you are referencing a valid location ID from Destinations endpoint or valid address",
		"P403"=>"You are not allowed to use AnyAPM SameAPM delivery Contact support if you believe this is a mistake",
		"P404"=>"Invalid import CSV See error contents for additional info.",
		"P405"=>"Invalid phone number Make sure you are sending the phone number in full international format, e.g. +30 xx x xxx xxxx",
		"P406"=>"Invalid compartment/parcel size Make sure you are sending one of required sizes 1, 2 or 3 ( Medium or Large) Size is required when sending from AnyAPM directly",
		"P407"=>"Invalid country code Make sure you are sending country code in ISO 3166 1 alpha 2 format, e.g. GR.",
		"P410"=>"Order number conflict You are trying to create a delivery request for order ID that has already been created. Choose another order ID",
		"P411"=>"You are not eligible to use Cash on delivery payment type Use another payment type or contact our support.",
		"P420"=>"Parcel not ready for cancel You can cancel only new, undelivered, or parcels that are not returned or lost. Make sure parcel is in transit and try again.",
		"P430"=>"Parcel not ready for AnyAPM confirmation Parcel is probably already confirmed or being delivered. Contact support if you believe this is a mistake."
	);
	$code = isset($delivery['code']) ? $delivery['code'] : '';
	$msg = isset($errors_arr[$code]) ? $errors_arr[$code] : 'Unknown error';
	die(json_encode(array('success'=>false,'error'=>'Error: '.$code.' - '.$msg)));
}
?>