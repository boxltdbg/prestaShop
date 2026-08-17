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

if (isset($_GET['voucher'])) {
} else {
    die('something is wrong');
}

// get vars
$voucher = $_GET['voucher'];
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

// get api session
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
    CURLOPT_POSTFIELDS => '{
				  "grant_type": "client_credentials",
				  "client_id": "' . $api_id . '",
				  "client_secret": "' . $api_secret . '"
				}',
));

$auth_response = curl_exec($auth);

$auth_http_status = curl_getinfo($auth, CURLINFO_HTTP_CODE);
curl_close($auth);

if ($auth_http_status != 200) {
    die('Authentication failed: ' . $auth_json['message'] ?? 'Unknown error');
}


$auth_json = json_decode($auth_response, true);
$authorization = "Authorization: Bearer " . $auth_json['access_token'];


// print voucher
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="label.pdf"');
$printv = curl_init();

curl_setopt_array($printv, array(
    CURLOPT_URL => 'https://' . $api_url . '/api/v1/parcels/' . $voucher . '/label.pdf',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => array(
        $authorization,
        'accept: application/pdf'
    ),
    CURLOPT_POSTFIELDS => '{
				  "grant_type": "client_credentials",
				  "client_id": "' . $api_id . '",
				  "client_secret": "' . $api_secret . '"
				}',
));


header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="label.pdf"');

$headers = [
    'accept: application/pdf',
    'Authorization: Bearer ' . $auth_json['access_token']
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://' . $api_url . '/api/v1/parcels/' . $voucher . '/label.pdf');
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
echo curl_exec($ch);;
exit();
