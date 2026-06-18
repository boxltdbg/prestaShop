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

if(!isset($_POST['order_id']) || !isset($_POST['voucher_number'])){ die(json_encode(array('success'=>false,'error'=>'something is wrong'))); }


//get vars
$order_id = (int)$_POST['order_id'];
// parcel/voucher ids are alphanumeric strings, never cast to int
$voucher = preg_replace('/[^A-Za-z0-9_\-]/', '', $_POST['voucher_number']);
//get configuration (use Configuration::get so the correct shop context value is read)
$api_url = Configuration::get('BOXNOW_API_URL');
$api_id = Configuration::get('BOXNOW_OAUTH_CLIENT_ID');
$api_secret = Configuration::get('BOXNOW_OAUTH_CLIENT_SECRET');
$api_warehouse = Configuration::get('BOXNOW_WAREHOUSE_NUMBER');
$api_partner = Configuration::get('BOXNOW_PARTNER_ID');

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
		$authorization = "Authorization: Bearer " . $auth_json['access_token'];	

	$headers = [
		$authorization,
		'Content-Type: application/json'
	];

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://' . $api_url . '/api/v1/parcels/'.rawurlencode($voucher).':cancel');
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

	$raw_response = curl_exec($ch);
	$cancel_http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	$response = json_decode($raw_response, true);

	// boxnow returns an empty body (204) on a successful cancel
	if($cancel_http_status >= 200 && $cancel_http_status < 300){
		$boxnow = $pdo->prepare("SELECT vouchers_numbers,vouchers FROM ".$prefix."boxnow_entries WHERE id_order=".$order_id);
		$boxnow->execute();
		$boxnow = $boxnow->fetch();
		$vouchers = $boxnow['vouchers_numbers'];
		$totalvouchers = $boxnow['vouchers'] - 1;
		if($totalvouchers < 0){ $totalvouchers = 0; }
		//update vouchers
		$newvouchers = str_replace($voucher.',','',$vouchers);
		$sql = "UPDATE ".$prefix."boxnow_entries SET vouchers_numbers=".$pdo->quote($newvouchers).", vouchers='$totalvouchers' WHERE id_order=".$order_id;
		$pdo->exec($sql);
		die(json_encode(array('success'=>true,'vouchers_left'=>(int)$totalvouchers)));
	}

	$err = isset($response['code']) ? $response['code'] : ('HTTP '.$cancel_http_status);
	die(json_encode(array('success'=>false,'error'=>'Cancel failed: '.$err)));
?>