<?php
include ('../../config/config.inc.php');
include ('../../init.php');

$databaseConfig = include '../../app/config/parameters.php';

$servername = $databaseConfig['parameters']['database_host'];
$username = $databaseConfig['parameters']['database_user'];
$password = $databaseConfig['parameters']['database_password'];
$database = $databaseConfig['parameters']['database_name'];
$prefix = $databaseConfig['parameters']['database_prefix'];

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

if(isset($_POST['order_id']) && isset($_POST['voucher_number'])){} else { die('something is wrong');}


//get vars
$order_id = (int)$_POST['order_id'];
$voucher = $_POST['voucher_number'];
//get configuration
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

//get api session
$auth = curl_init();

        curl_setopt_array($auth, array(
            CURLOPT_URL => 'https://' . $api_url . '/api/v1/auth-sessions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 15,
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
            return;
        }

        $auth_json = json_decode($auth_response, true);	
		$authorization = "Authorization: Bearer " . $auth_json['access_token'];	

	$headers = [
		$authorization,
		'Content-Type: application/json'
	];

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://' . $api_url . '/api/v1/parcels/'.$voucher.':cancel');
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	
	$response = curl_exec($ch);
	$cancel_http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	$response = json_decode($response, true);
	if($cancel_http_status == 200 || $cancel_http_status == 204){
		$boxnow = $pdo->prepare("SELECT vouchers_numbers, vouchers FROM ".$prefix."boxnow_entries WHERE id_order = ?");
		$boxnow->execute([$order_id]);
		$boxnow = $boxnow->fetch();
		$vouchers      = $boxnow['vouchers_numbers'];
		$totalvouchers = $boxnow['vouchers'] - 1;
		//update vouchers
		$newvouchers = str_replace($voucher.',', '', $vouchers);
		// Reset submitted to 0 when all vouchers are cancelled so creation is allowed again
		$submitted = ($totalvouchers <= 0) ? '0' : '1';
		$update = $pdo->prepare("UPDATE ".$prefix."boxnow_entries SET vouchers_numbers = ?, vouchers = ?, submitted = ? WHERE id_order = ?");
		$update->execute([$newvouchers, $totalvouchers, $submitted, $order_id]);
	}

?>