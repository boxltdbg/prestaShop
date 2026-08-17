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
$pdo = new PDO("mysql:host=$servername;dbname=$database", $username, $password, // initiate connection with mysql db 
       array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
}
catch(Exception $e) {
    echo 'Exception -> ';
    var_dump($e->getMessage());
}

if(isset($_POST['order_id']) && isset($_POST['voucher_number'])){} else { die('something is wrong');}


// get vars
$order_id = $_POST['order_id'];
$voucher = $_POST['voucher_number'];
// get configuration
$apisettings = $pdo->prepare("SELECT * FROM ".$prefix."configuration WHERE name like 'BOXNOW_%'");
$apisettings->execute();
$apisettings = $apisettings->fetchAll();
$apisets = array();
foreach($apisettings as $apis){
	$apisets[$apis['name']] = $apis['value'];	
}
$api_url = $apisets['BOXNOW_API_URL'];
$api_id = $apisets['BOXNOW_OAUTH_CLIENT_ID'];
$api_secret = $apisets['BOXNOW_OAUTH_CLIENT_SECRET'];
$api_warehouse = $apisets['BOXNOW_WAREHOUSE_NUMBER'];
$api_partner = $apisets['BOXNOW_PARTNER_ID'];

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
    die('Authentication failed: ' . $auth_json['message'] ?? 'Unknown error');
}


$authorization = "Authorization: Bearer " . $auth_json['access_token'];
	$cancelation = curl_init('https://' . $api_url . '/api/v1/parcels/'.$voucher.':cancel');
	curl_setopt($cancelation, CURLOPT_USERAGENT, 'BoxNow-PrestaShop-Module/2.0');
	curl_setopt($cancelation, CURLOPT_HTTPHEADER, array($authorization, 'Content-Type: application/json')); // Inject the token into the header
	curl_setopt($cancelation, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($cancelation, CURLOPT_POST, 1); // Specify the request method as POST
	curl_setopt($cancelation, CURLOPT_POSTFIELDS, $postData); // Set the posted fields
	curl_setopt($cancelation, CURLOPT_FOLLOWLOCATION, 1); // This will follow any redirects
	$cancelation_response = curl_exec($cancelation);
	$cancelation_http_status = curl_getinfo($cancelation, CURLINFO_HTTP_CODE);
	$cancelation_json = json_decode($cancelation_response, true);
	curl_close($cancelation);

	if($cancelation_response == ''){
		$boxnow = $pdo->prepare("SELECT parcel_ids,vouchers FROM ".$prefix."boxnow_entries WHERE id_order=".$order_id);
		$boxnow->execute();
		$boxnow = $boxnow->fetch();
		$vouchers = $boxnow['parcel_ids'];
		$totalvouchers = $boxnow['vouchers'] - 1;
		// update vouchers
		$newvouchers = str_replace($voucher.',','',$vouchers);
		$sql = "UPDATE ".$prefix."boxnow_entries SET parcel_ids='$newvouchers', vouchers='$totalvouchers' WHERE id_order=".$order_id;
		$insert = $pdo->exec($sql);	
	}
