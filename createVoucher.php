<?php
include ('../../config/config.inc.php');
include ('../../init.php');
include ('boxnow.php');

$databaseConfig = include '../../app/config/parameters.php';
$servername = $databaseConfig['parameters']['database_host'];
$username   = $databaseConfig['parameters']['database_user'];
$password   = $databaseConfig['parameters']['database_password'];
$database   = $databaseConfig['parameters']['database_name'];
$prefix     = $databaseConfig['parameters']['database_prefix'];

try {
    $pdo = new PDO(
        "mysql:host=$servername;dbname=$database",
        $username,
        $password,
        [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"]
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (Exception $e) {
    die('DB error: ' . $e->getMessage());
}

$order_id         = isset($_POST['order_id'])         ? (int)$_POST['order_id']        : 0;
$warehouse        = isset($_POST['warehouses'])       ? trim($_POST['warehouses'])       : '';
$compartment_size = isset($_POST['compartment_size']) ? (int)$_POST['compartment_size'] : 1;

if (!$order_id) { die('Invalid order'); }
if (!in_array($compartment_size, [1, 2, 3])) { $compartment_size = 1; }

// Atomic lock — prevents race conditions from double-submit
$locked = $pdo->exec(
    "UPDATE {$prefix}boxnow_entries SET submitted='1'"
    . " WHERE id_order='{$order_id}' AND submitted='0' AND vouchers='0'"
);
if (!$locked) { exit; }

// API config
$stmt = $pdo->query("SELECT name, value FROM {$prefix}configuration WHERE name LIKE 'BOXNOW_%'");
$apisets = [];
foreach ($stmt->fetchAll() as $row) { $apisets[$row['name']] = $row['value']; }

$api_url     = $apisets['BOXNOW_API_URL']             ?? '';
$api_id      = $apisets['BOXNOW_OAUTH_CLIENT_ID']     ?? '';
$api_secret  = $apisets['BOXNOW_OAUTH_CLIENT_SECRET'] ?? '';
$api_email   = $apisets['BOXNOW_CONTACT_EMAIL']       ?? '';
$api_name    = $apisets['BOXNOW_CONTACT_NAME']        ?? '';
$api_contact = $apisets['BOXNOW_CONTACT_NUMBER']      ?? '';

// Authenticate
$auth = curl_init('https://' . $api_url . '/api/v1/auth-sessions');
curl_setopt($auth, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($auth, CURLOPT_RETURNTRANSFER, true);
curl_setopt($auth, CURLOPT_POST, 1);
curl_setopt($auth, CURLOPT_POSTFIELDS, json_encode([
    'grant_type'    => 'client_credentials',
    'client_id'     => $api_id,
    'client_secret' => $api_secret,
]));
curl_setopt($auth, CURLOPT_FOLLOWLOCATION, 1);
$auth_response    = curl_exec($auth);
$auth_http_status = curl_getinfo($auth, CURLINFO_HTTP_CODE);
$auth_json        = json_decode($auth_response, true);
curl_close($auth);

if ($auth_http_status != 200) {
    $pdo->exec("UPDATE {$prefix}boxnow_entries SET submitted='0' WHERE id_order='{$order_id}'");
    die('Auth failed');
}

// Order data
$objOrder     = new Order($order_id);
$customer     = new Customer($objOrder->id_customer);
$address      = new Address($objOrder->id_address_delivery);
$products     = $objOrder->getProducts();
$boxnoworder  = $pdo->query("SELECT * FROM {$prefix}boxnow_entries WHERE id_order='{$order_id}' LIMIT 1")->fetch();

$paymentMode   = ($objOrder->module == 'ps_cashondelivery') ? 'cod' : 'prepaid';
$payment_type  = ($paymentMode == 'cod') ? 2 : 1;
$invoiceValue  = number_format(floor($objOrder->total_paid * 100) / 100, 2, '.', '');
$amountcollect = ($paymentMode == 'cod') ? $invoiceValue : '0.00';
$addressphone  = Boxnow::formatPhone($address->phone);

$total_weight = 0;
foreach ($products as $p) {
    $total_weight += $p['product_weight'] * $p['product_quantity'];
}

$postData = json_encode([
    'orderNumber'         => $order_id . '_' . uniqid(),
    'invoiceValue'        => $invoiceValue,
    'paymentMode'         => $paymentMode,
    'amountToBeCollected' => $amountcollect,
    'allowReturn'         => true,
    'origin' => [
        'contactNumber' => $api_contact,
        'contactEmail'  => $api_email,
        'contactName'   => $api_name,
        'locationId'    => $warehouse,
    ],
    'destination' => [
        'contactNumber' => $addressphone,
        'contactEmail'  => $customer->email,
        'contactName'   => $address->firstname . ' ' . $address->lastname,
        'name'          => $boxnoworder['locker_name'],
        'addressLine1'  => $address->address1,
        'locationId'    => $boxnoworder['locker_id'],
    ],
    'items' => [[
        'id'              => (string)$order_id,
        'name'            => 'Order #' . $order_id,
        'value'           => $invoiceValue,
        'weight'          => (float)$total_weight,
        'compartmentSize' => $compartment_size,
    ]],
]);

$req = curl_init('https://' . $api_url . '/api/v1/delivery-requests');
curl_setopt($req, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $auth_json['access_token'], 'Content-Type: application/json']);
curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
curl_setopt($req, CURLOPT_POST, 1);
curl_setopt($req, CURLOPT_POSTFIELDS, $postData);
curl_setopt($req, CURLOPT_FOLLOWLOCATION, 1);
$resp = curl_exec($req);
curl_close($req);

$delivery  = json_decode($resp, true);
$parcel_id = isset($delivery['parcels'][0]['id']) ? $delivery['parcels'][0]['id'] : null;

if (!$parcel_id) {
    $pdo->exec("UPDATE {$prefix}boxnow_entries SET submitted='0' WHERE id_order='{$order_id}'");
    $errors_arr = [
        'P400' => 'Заявка с грешни данни.',
        'P401' => 'Грешна начална точка. Проверете locationId на склада.',
        'P402' => 'Невалидна крайна дестинация. Проверете locationId на автомата.',
        'P403' => 'Нямате право на AnyAPM - SameAPM доставки.',
        'P405' => 'Невалиден телефонен номер. Използвайте формат +359xxxxxxxxx.',
        'P406' => 'Невалиден размер. Използвайте 1 (Малък), 2 (Среден) или 3 (Голям).',
        'P410' => 'Номерът на поръчката вече е използван.',
        'P411' => 'Нямате право на Наложен платеж.',
    ];
    $code = isset($delivery['code']) ? $delivery['code'] : '';
    echo 'Грешка: ' . (isset($errors_arr[$code]) ? $errors_arr[$code] : $code);
    exit;
}

$stmt = $pdo->prepare("UPDATE {$prefix}boxnow_entries SET vouchers_numbers=?, warehouse_id=?, vouchers='1', payment_type=? WHERE id_order=?");
$stmt->execute([$parcel_id . ',', $warehouse, $payment_type, $order_id]);
