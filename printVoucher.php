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

if(isset($_GET['voucher'])){} else { die('something is wrong');}


//get vars
// parcel/voucher ids are alphanumeric strings, never cast to int
$voucher = preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['voucher']);
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
            return;
        }

        $auth_json = json_decode($auth_response, true);
		$authorization = "Authorization: Bearer " . $auth_json['access_token'];
//print voucher
	header('Content-Type: application/pdf');
	header('Content-Disposition: inline; filename="label.pdf"');	
$printv = curl_init();

        curl_setopt_array($printv, array(
            CURLOPT_URL => 'https://' . $api_url . '/api/v1/parcels/'.$voucher.'/label.pdf',
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
	curl_setopt($ch, CURLOPT_URL, 'https://' . $api_url . '/api/v1/parcels/'.$voucher.'/label.pdf');
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	
	echo curl_exec($ch);;
	exit();
		
?>