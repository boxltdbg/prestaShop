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

if(isset($_GET['voucher'])){} else { die('something is wrong');}


//get vars
$voucher = $_GET['voucher'];
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
//print voucher
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

	$pdf_data = curl_exec($ch);
	curl_close($ch);
	echo $pdf_data;
	exit();
		
?>