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

if(!isset($_POST['locker_id']) || $_POST['locker_id'] === ''){ die(json_encode(array('success'=>false,'error'=>'something is wrong'))); }

//get vars
$locker_id = preg_replace('/[^A-Za-z0-9_\-]/', '', $_POST['locker_id']);
$order_id = (int)$_POST['order_id'];
// when the locker is chosen on the map widget, its details come directly in the request
$post_locker_name = isset($_POST['locker_name']) ? trim($_POST['locker_name']) : '';
$post_locker_address = isset($_POST['locker_address']) ? trim($_POST['locker_address']) : '';
$post_locker_postcode = isset($_POST['locker_post_code']) ? trim($_POST['locker_post_code']) : '';
$objOrder = new Order($order_id);
$customer = new Customer($objOrder->id_customer);

//get configuration (use Configuration::get so the correct shop context value is read)
$api_url = Configuration::get('BOXNOW_API_URL');
$api_id = Configuration::get('BOXNOW_OAUTH_CLIENT_ID');
$api_secret = Configuration::get('BOXNOW_OAUTH_CLIENT_SECRET');
$api_warehouse = Configuration::get('BOXNOW_WAREHOUSE_NUMBER');
$api_partner = Configuration::get('BOXNOW_PARTNER_ID');
$api_email = Configuration::get('BOXNOW_CONTACT_EMAIL');
$api_name = Configuration::get('BOXNOW_CONTACT_NAME');
$api_contact = Configuration::get('BOXNOW_CONTACT_NUMBER');
$shopmail = Configuration::get('PS_SHOP_EMAIL');

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

	$locker_name = '';
	$locker_address = '';
	$locker_postcode = '';

	if($post_locker_name !== ''){
		// locker chosen on the map widget - details supplied directly
		$locker_name = $post_locker_name;
		$locker_address = $post_locker_address;
		$locker_postcode = $post_locker_postcode;
	} else {
		// manual locker id - look it up against the destinations list
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
		if(!empty($response['data'])){
			foreach ($response['data'] as $destination) {
				if($destination['id'] == $locker_id){
					$locker_name = $destination['name'];
					$locker_address = $destination['title'];
					$locker_postcode = $destination['postalCode'];
				}
			}
		}
	}

	if($locker_name !== ''){
		$sql = "UPDATE ".$prefix."boxnow_entries SET locker_id=".$pdo->quote($locker_id).",locker_name=".$pdo->quote($locker_name).",locker_address=".$pdo->quote($locker_address).",locker_post_code=".$pdo->quote($locker_postcode)." WHERE id_order=".$order_id;
		$pdo->exec($sql);
		$headers[] = 'MIME-Version: 1.0';
		$headers[] = 'Content-type: text/html; charset=iso-8859-7';

		// Additional headers
		$headers[] = 'To: '.$customer->email;
		$headers[] = 'From: '.$shopmail;		
		$subject = 'Αλλαγή θυρίδας παραλαβής';
		$msg='Γεια σας,<br>Λόγω προβλήματος που παρουσιάστηκε στην θυρίδα παραλαβής που είχατε επιλέξει για την παραγγελία <b>#'.$order_id.'</b> αλλάξαμε την θυρίδα όπως παρακάτω:' ;
		$msg .= '<p>Η νέα θυρίδα παραλαβής είναι:<br>';
		$msg .= 'Όνομα Θυρίδας: <b>'.$locker_name.'</b>';
		$msg .= '<br>Διεύθυνση Θυρίδας: <b>'.$locker_address.'</b></p>';
		$msg .= 'Για οιανδήποτε διευκρίνηση θέλετε παρακαλώ, επικοινωνήστε.<br><br>Ευχαριστούμε και συγνώμη για την ταλαιπωρία!';
		mail($customer->email, $subject, $msg, implode("\r\n", $headers));
		die(json_encode(array(
			'success' => true,
			'locker_id' => $locker_id,
			'locker_name' => $locker_name,
			'locker_address' => $locker_address,
			'locker_post_code' => $locker_postcode
		)));
	} else {
		die(json_encode(array('success'=>false,'error'=>'Locker not found. Check the locker ID.')));
	}
?>