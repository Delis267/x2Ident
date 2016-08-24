<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

//Get user IP address
$ip = "";
if (!isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
	$ip = $_SERVER['REMOTE_ADDR'];
}
else {
	$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
}
$proxy_ip = getallheaders()["xident-real-ip"];
if(strlen($proxy_ip)>1) {
	$ip = $proxy_ip;
}

//Get JS id
$js_id = $_GET['js-id'];
if(strlen($js_id)<1) {
	die("JS-id not valid.");
}

include('api.secret.php');

$mysqli = new mysqli("localhost", "xident", "jugendhackt", "xident");

//Check DB connection
if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
}

//Check js_id
$js_id_valide = false;
$user = "";
$db_ip = "";
$sess_id = "";
$query = "SELECT user, ip, sess_id FROM session_user WHERE js_id='$js_id'";
    //echo $query;
	if ($result = $mysqli->query($query)) {
	
	    /* fetch object array */
	    while ($obj = $result->fetch_object()) {
			$js_id_valide = true;
			$user = $obj->user;
			$sess_id = $obj->sess_id;
			$db_ip = $obj->ip;
		}
	}

if(!js_id_valide) {
	die("JS-id not valid.");
}

if(strcmp($ip,$db_ip)) {
	//evtl. Warnung
}

//Daten abrufen
$ch = curl_init();
$url = str_replace("@@user@@",$user,$api_url);

//URL übergeben
curl_setopt($ch, CURLOPT_URL, $url);

//Parameter für Netzwerk-Anfrage konfigurieren
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );

//Anfrage durchführen und Antwort in $result speichern
$result = curl_exec ($ch);
$data = json_decode($result,true);


$id = 0;
foreach ($data as $key => $val) {
	$id = $key;
	$title = $val['label'];
	$url = $val['url'];
	$website = '<a href="'.$url.'" target="_blank">'.$url.'</a>';
	$username = $val['login'];
	$lastlogin = 0;
	$expires = 0;
	$otk = "-";
	$pw_global = "0";
	
	//Get OTKs from db
	$query = "SELECT onetime, expires, pw_active, pw_global FROM onetimekeys WHERE pwid='$id' AND sess_id='$sess_id'";
    //echo $query;
	if ($result = $mysqli->query($query)) {
	
	    /* fetch object array */
	    while ($obj = $result->fetch_object()) {
			$expires = $obj->expires;
			if($obj->pw_active == 1) {
	        	$otk = $obj->onetime;
				$pw_global = $obj->pw_global;
			}
	    }
	
	    /* free result set */
	    $result->close();
	}

	//Get last login time
	$query = "SELECT last_login FROM history WHERE pwid='".$id."'";
	if ($result = $mysqli->query($query)) {
	
	    /* fetch object array */
	    while ($obj = $result->fetch_object()) {
			$lastlogin = $obj->last_login;
	    }
	
	    /* free result set */
	    $result->close();
	}

	//Calc last login
	$timestamp = time();
	$diff = $timestamp-$lastlogin;
	$lastlogin_text = "vor ".$diff." Sekunde(n)";
	if($diff>=60) {
		$diff = round($diff/60);
		$lastlogin_text = "vor ".$diff." Minute(n)";

		if($diff>=60) {
			$diff = round($diff/60);
			$lastlogin_text = "vor ".$diff." Stunde(n)";

			if($diff>=24) {
				$diff = round($diff/24);
				$lastlogin_text = "vor ".$diff." Tag(en)";

				if($diff>=30) {
					$diff = round($diff/30);
					$lastlogin_text = "vor ".$diff." month ago";
				}
			}
		}
	}

	//Calc last login
	$timestamp = time();
	$diff2 = $expires-$timestamp;
	$expires_text = $diff2." Sekunden";
    
	
	//echo "expires: ".$expires."; timestamp: ".$timestamp."|";
	if($expires<$timestamp-1) {
		//maybe delete real passwort due to security?
		$eintrag = "UPDATE onetimekeys SET pw_active='0' WHERE pwid = '$id' AND sess_id='$sess_id'";
		$mysqli->query($eintrag);
		$otk = "-";
		$expires_text = "-";
	}

	$output = "$id;$title;$url;$otk;$expires;$pw_global;$lastlogin|";
	echo $output;

}

echo "OK";
//var_dump($data);
//echo " </tbody></table></body></html>";

//Sonderzeichen (auch Satzzeichen) verursachen beim Login Probleme
function rand_char($length) {
	$characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  	$random = '';
  	$max = strlen($characters) - 1;
 	for ($i = 0; $i < $length; $i++) {
		$random .= $characters[mt_rand(0, $max)];
	}
	return $random;
}
?>
