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

if(isset($_POST['locker_id'])){} else { die('something is wrong');}

//get vars
$locker_id = trim($_POST['locker_id']);
$order_id = (int)$_POST['order_id'];

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
$api_email = $apisets['BOXNOW_CONTACT_EMAIL'];
$api_name = $apisets['BOXNOW_CONTACT_NAME'];
$api_contact = $apisets['BOXNOW_CONTACT_NUMBER'];

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
		

	//change locker
	$data = array('locationId'=>"$locker_id");
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://' . $api_url . '/api/v1/destinations');
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				$authorization,
                'Content-Type: application/json'
            ));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	
	$response = curl_exec($ch);
	curl_close($ch);
	$response = json_decode($response, true);
	//print_r($response['data']);
	foreach ($response['data'] as $destination) {
		if($destination['id'] == $locker_id){
			$locker_name = $destination['name'];
			$locker_address = $destination['title'];
			$locker_postcode = $destination['postalCode'];
	
		}		
	}
	if(!empty($locker_name)){
		$update = $pdo->prepare("UPDATE ".$prefix."boxnow_entries SET locker_id = ?, locker_name = ?, locker_address = ?, locker_post_code = ? WHERE id_order = ?");
		$update->execute([$locker_id, $locker_name, $locker_address, $locker_postcode, $order_id]);
		$objOrder = new Order($order_id);
		$customer = new Customer($objOrder->id_customer);
		$shopmail = $pdo->query("SELECT value FROM ".$prefix."configuration WHERE name='PS_SHOP_EMAIL' LIMIT 1")->fetchColumn();
		$subject = mb_encode_mimeheader('Промяна в автомата за доставка на BOX NOW', 'UTF-8', 'B');
		$headers = implode("\r\n", [
			'MIME-Version: 1.0',
			'Content-type: text/html; charset=UTF-8',
			'To: ' . $customer->email,
			'From: ' . $shopmail,
		]);
		$msg  = 'Здравейте,<br>Поради проблем с кутията за доставка, която сте избрали за поръчката <b>#' . $order_id . '</b>, променихме автомата, както следва:';
		$msg .= '<p>Нов BOX NOW автомат: <b>' . $locker_name . '</b><br>';
		$msg .= 'Адрес: <b>' . $locker_address . '</b></p>';
		$msg .= 'За допълнителни разяснения, моля свържете се с нас.<br><br>Благодарим Ви и съжаляваме за причиненото неудобство!';
		mail($customer->email, $subject, $msg, $headers);
	} else {echo 'error';}
?>