<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$manager = new MongoDB\Driver\Manager("mongodb://localhost:27017");

function debugLog($text) {
	file_put_contents('debug.txt', $text.PHP_EOL , FILE_APPEND);
}

function countAll(){
	$command = new MongoDB\Driver\Command(["listDatabases" => ""]);
	$result = $manager->executeCommand("admin", $command);
	$res = current($result->toArray());
	
	foreach($res->databases as $databaseData){
		//$dbName = $databaseData->name;
		$dbName = "SHA1";
	
		$command = new MongoDB\Driver\Command(["listCollections" => NULL]);
		$result = $manager->executeCommand($dbName, $command);
		$res = current($result->toArray());
		echo var_dump($res) . "<br>";
	
		//foreach($res as $collectionData){
			
			
		//}
		//echo var_dump($res);
		// echo var_dump($databaseName);
	}
}

function countItems() {
	$command = new MongoDB\Driver\Command(["count" => "Alpago"]);
	$result = $manager->executeCommand("Leaks", $command);
	$res = current($result->toArray());
	$count = $res->n;
	echo $count;

}







function insertMissingIntoDatabase($type, $data){
	global $manager;
	$bulk = new MongoDB\Driver\BulkWrite;
	if($type == "MD5") {
		if(md5($data->password) == $data->_id or substr( $data->password, 0, 5 ) === '$HEX['){
			$bulk->update(
				['_id' => $data->_id], 
				['$set' => ['password' => $data->password, "source" => $data->source]], 
				['multi' => false, 'upsert' => true]
			);
			$manager->executeBulkWrite('MD5.' . substr( $data->_id, 0, 1 ), $bulk);
		}
	}
	else if($type == "BCRYPT") {
		if(password_verify($data->password, $data->_id) == true){
			$bulk->update(
				['_id' => $data->_id], 
				['$set' => ['password' => $data->password, "source" => $data->source]], 
				['multi' => false, 'upsert' => true]
			);
			$manager->executeBulkWrite('HASHES.BCRYPT', $bulk);
		}
	}
}


function queryOutsource($hashType, $itemsArray) {
	global $manager;
	$retrieved = array();
	foreach($itemsArray as $item) {
		if($hashType == "MD5"){
			if(strlen($item) == 32) {
				
				$url = "http://nitrxgen.net/md5db/" . $item;
				$received = file_get_contents($url);
				//print($received . "\n");
				if(strlen($received)>0 and strlen($received)<200) {
					if(md5($received) == $item or substr( $received, 0, 5 ) === '$HEX['){
						$result = new \stdClass();
						$result->_id = $item;
						$result->password = $received;
						$result->source = "http://nitrxgen.net";
						array_push($retrieved, $result);
						insertMissingIntoDatabase($hashType, $result);
						continue;	
					}
					else{
						debugLog("Failed md5 check nitrxgen.net, hash: " . $item);
					}
				}
				else if(strlen($received)>200){
					debugLog("Long response from nitrxgen.net, length: " . strlen($received) . " url: " . $url);
				}

				



				$url = "https://md5decrypt.net/Api/api.php?hash=" . $item . "&hash_type=md5&email=deanna_abshire@proxymail.eu&code=1152464b80a61728";
				$opts = [
					"http" => [
						"method" => "GET",
						"header" => "Accept-language: en\r\n" .
							"Cookie: foo=bar\r\n" .
							"User-Agent: Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.102011-10-16 20:23:10\r\n"
				
					]
				];
				$context = stream_context_create($opts);
				if(false === ($received = file_get_contents($url, false, $context))){
					//print($received . "\n");
					if(strlen($received)>0 and strlen($received)<200) {
						if(md5($received) == $item or substr( $received, 0, 5 ) === '$HEX['){
							$result = new \stdClass();
							$result->_id = $item;
							$result->password = $received;
							$result->source = "https://md5decrypt.net";
							array_push($retrieved, $result);
							insertMissingIntoDatabase($hashType, $result);
							continue;
						}
						else{
							debugLog("Failed md5 check md5decrypt.net, hash: " . $item);
						}
					}
					else if(strlen($received)>200){
						debugLog("Long response from md5decrypt.net, length: " . strlen($received) . " url: " . $url);
					}
				}
				else{
					debugLog("Failed query to md5decrypt.net, hash: " . $item);
				}
				


			}
		}
	}
	return $retrieved;	
}



function pushSearchResults($cursor) {
	$retrieved = array();
	foreach ( $cursor as $id => $value ){
		$result = new \stdClass();
		
		$result->_id = $value->_id;
		$result->password = $value->password;

		if(property_exists($value, 'salt')) {
			$result->salt = $value->salt;	
		}
		
		if(property_exists($value, 'source')){
			$result->source = $value->source;
		}
		else{
			$result->source = "https://dehash.lt";
		}

		array_push($retrieved, $result);
	}
	return $retrieved;
}


function crack($hash, $passwords){
	foreach($passwords as $password){
		if(password_verify($password, $hash) == true){
			return $password;
		}
	}
	return null;
}



function search($hashType, $data) {
	global $manager;
	$hashType = strtoupper($hashType);
	$results = array();



	// Password reusal attack
	if(substr($hashType, 0, 6) === 'EMAIL:'){
		foreach(preg_split("/((\r?\n)|(\r\n?))/", $data) as $line) {
			$submitedEmail = strtolower(explode(":", $line, 2)[0]);
			$submitedHash = explode(":", $line, 2)[1];

			$emailsPasswords = search("EMAIL", $submitedEmail);
			$passwords = array();
			foreach($emailsPasswords as $emailPassword){
				$password = $emailPassword->password;
				array_push($passwords, $password);
			}

			// Bcrypt cracking attack
			if($hashType == "EMAIL:BCRYPT"){
				$verifiedPassword = crack($submitedHash, $passwords);
				if($verifiedPassword){
					$result = new \stdClass();
					$result->_id = $submitedHash;
					$result->password = $verifiedPassword;
					$result->source = "https://dehash.lt/password_reuse_attack.php";
					array_push($results, $result);
					insertMissingIntoDatabase("BCRYPT", $result);

				}
			}
			else{
				print("Not currently supported.");
				break;
			}
		}
	}

	// Email lookup
	else if($hashType == "EMAIL"){
		$_ids = array();
		foreach(preg_split("/((\r?\n)|(\r\n?))/", $data) as $line) {
			$line = strtolower($line);
			array_push($_ids, $line);
		} 
		$query = new MongoDB\Driver\Query( array('_id' => array( '$in' => $_ids)) );

		$cursor = $manager->executeQuery('leakworks.LeakWorks', $query);
		foreach( $cursor as $id => $value ) {
			foreach($value->passwords as &$valuePass) {
				$result = new \stdClass();
				$result->_id = $value->_id;
				$result->password = $valuePass;
				if(property_exists($value, 'source')){
					$result->source = $value->source;
				}
				else{
					$result->source = "https://dehash.lt";
				}
				array_push($results, $result);
			}
		}
	}
	else if($hashType == "BCRYPT") {
		$_ids = array();
		foreach(preg_split("/((\r?\n)|(\r\n?))/", $data) as $line){
			array_push($_ids, $line);
		}
		$query = new MongoDB\Driver\Query( array('_id' => array( '$in' => $_ids)) );
		$cursor = $manager->executeQuery('HASHES.' . $hashType, $query);
		
		$retrieved = pushSearchResults($cursor);
		$results = array_merge($results, $retrieved);
	}


	// Splited into collections hashes lookup
	else {
		if($hashType == 'MD5' or $hashType == "SHA1" or $hashType == "SHA256" or $hashType == "SHA384"){
			foreach(str_split('0123456789abcdef') as $char){
				$_ids = array();
				foreach(preg_split("/((\r?\n)|(\r\n?))/", $data) as $line){
					$line = strtolower($line);
					if(substr( $line, 0, 1 ) === $char) {
						array_push($_ids, $line);
					}
				}
				

				$localSearchEnabled = TRUE;
				if($localSearchEnabled) {
					$query = new MongoDB\Driver\Query( array('_id' => array( '$in' => $_ids)) );
					$cursor = $manager->executeQuery($hashType . '.' . $char, $query);
					$retrieved = pushSearchResults($cursor);
					$results = array_merge($results, $retrieved);


					// Remove Founds For Outsource Search
					foreach($retrieved as $retrievedItem){
						$_ids = array_diff($_ids, [$retrievedItem->_id]);
					}
				}
				
				// Outsource search
				if(count($_ids)>0){
					$outsourceRetrieved = queryOutsource($hashType, $_ids);
					$results = array_merge($results, $outsourceRetrieved);
				}

			}
		}


		$_ids = array();
		foreach(preg_split("/((\r?\n)|(\r\n?))/", $data) as $line){
			$line = strtolower($line);
			array_push($_ids, $line);
		}
		$query = new MongoDB\Driver\Query( array('_id' => array( '$in' => $_ids)) );

		if($hashType == 'MD5' or $hashType == "VBULLETIN"){
			// VBULLETIN
			$cursor = $manager->executeQuery('HASHES.VBULLETIN', $query);
			$retrieved = pushSearchResults($cursor);
			$results = array_merge($results, $retrieved);
		}
	}
	return $results;
}


function identifyHashes($data) {
	$hashType = "";
	foreach(preg_split("/((\r?\n)|(\r\n?))/", $data) as $line){
		if(strlen($line) > 0){
			if(ctype_xdigit($line)) {
				if(strlen($line) == 32){
					if($hashType == "") {
						$hashType = "MD5";
					}
					else if($hashType != "MD5"){
						return "ERROR 1";
					}
				}
				else if(strlen($line) == 40){
					if($hashType == "") {
						$hashType = "SHA1";
					}
					else if($hashType != "SHA1"){
						return "ERROR 2";
					}
				}
				else if(strlen($line) == 64){
					if($hashType == "") {
						$hashType = "SHA256";
					}
					else if($hashType != "SHA256"){
						return "ERROR 3";
					}
				}
				else if(strlen($line) == 96){
					if($hashType == "") {
						$hashType = "SHA384";
					}
					else if($hashType != "SHA384"){
						return "ERROR 12";
					}
				}

			}
			else if(substr( $line, 0, 4 ) === '$2a$') {
				if($hashType == "") {
					$hashType = "BCRYPT";
				}
				else if($hashType != "BCRYPT"){
					return "ERROR 4";
				}
			}
			else if(strpos($line, ":") !== false){
				$splited = explode(":", $line);
				if(strlen($splited[0]) == 32){
					if($hashType == "") {
						$hashType = "VBULLETIN";
					}
					else if($hashType != "VBULLETIN"){
						return "ERROR 5";
					}
				}
				else if(strlen($splited[0]) == 40){
					if($hashType == "") {
						$hashType = "SHA1SALT";
					}
					else if($hashType != "SHA1SALT"){
						return "ERROR 6";
					}
				}
				else if(strpos($line, "@") !== false){
					$splited = explode(":", $line, 2);
					if(strpos($splited[0], "@") !== false){
						$secondPartType = identifyHashes($splited[1]);
						if(substr($secondPartType, 0, 5) !== 'ERROR'){
							$thisLineType = "EMAIL:" . $secondPartType;
							if($hashType == "") {
								$hashType = $thisLineType;
							}
							else if($hashType != $thisLineType){
								return "ERROR 7";
							}
						}
						else {
							return "ERROR 8";
						}
					}
				}
			}
			else if(strpos($line, "@") !== false){
				if(strpos($line, ".") !== false){
					if($hashType == "") {
						$hashType = "EMAIL";
					}
					else if($hashType != "EMAIL"){
						return "ERROR 9";
					}
				}
				else{
					return "ERROR 10";
				}
			}
		}
	}


	if($hashType == "") {
		return "ERROR 11";
	}
	return $hashType;
}




$postPayload = file_get_contents('php://input');
if(strlen($postPayload) > 10485760) {
	print("Error: Payload too big. Max 10MB");
	exit;
}


// User Data
if(substr( $_SERVER['REMOTE_ADDR'], 0, 12 ) !== "192.168.198.") {
	$userData = new MongoDB\Driver\BulkWrite;
	$userData->insert(
		array(
			'Time' => date('Y-m-d H:i:s'),
			'IP'    => $_SERVER['REMOTE_ADDR'],
			'UserAgent' => $_SERVER['HTTP_USER_AGENT'],
			'Method' => $_SERVER['REQUEST_METHOD'],
			'Url' => $_SERVER['REQUEST_URI'],
			'PostPayload' => file_get_contents('php://input')
		)
	);
	$manager->executeBulkWrite('Users.Queries', $userData);
}




// API
$queryText = "";
if(strlen($postPayload) == 0){
	if(isset($_GET['search'])){
		$queryText = $_GET['search'];
	}
}
else if(strlen($postPayload) <= 10485760){
	$queryText = $postPayload;
}

if(strlen($queryText) > 0){
	$hashType = identifyHashes($queryText);
	//print($hashType);
	if(substr($hashType, 0, 5) !== 'ERROR'){
		$data = search($hashType, $queryText);

		if(isset($_GET['json']) != TRUE){
			

			$resultCount = count($data);
			$printingId = 0;
			foreach($data as $item){
				print($item->_id . ":" . $item->password);
				if($resultCount > $printingId){
					print("\n");
				}
			}
		}
		else {
			$json = json_decode('{}');


			$oldSource = "";
			$sameSourceLines = array();
			foreach($data as $item){
				if($oldSource != $item->source){
					if($oldSource != ""){
						$json->$oldSource = json_decode('{}');
						$json->$oldSource->results = $sameSourceLines;
					}
					$sameSourceLines = array();
					$oldSource = $item->source;
				}
				

				$line = $item->_id;
				if(property_exists($item, 'salt')){
					$line = $line . ":" . $item->salt;
				}
				$line = $line . ":" . $item->password;

				array_push($sameSourceLines, $line);

			}
			if($oldSource != ""){
				$json->$oldSource = json_decode('{}');
				$json->$oldSource->results = $sameSourceLines;
			}

			print(json_encode($json, JSON_PRETTY_PRINT));
		}

		


	}
	else{
		print("Error");
		http_response_code(404);
	}
}


?>
