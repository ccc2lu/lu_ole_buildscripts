<?php


//PARSES A TEXT FILE (hardcoded as vendor.data)
//THAT WAS EXTRACTED FROM Sirsi/Unicorn USING THE selvendor
//API CALL AS SHOWN HERE: http://pastebin.com/yj0ZBWEk
//OR HERE:
//https://github.com/jcamins/koha-migration-toolbox/blob/master/migration/Symphony/By_API/unicorn_export_scripts.txt
//THIS SCRIPT INSERTS THE DATA
//INTO A SQLITE DATABASE AS A STAGING
//ENVIRONMENT (WHICH WILL THEN BE WRITTEN TO A
//CSV FILE THAT OLE WILL INGEST USING
//loadvendorstocsv.php)

//SET UP LOGGING
require('KLogger.php');
$log = new KLogger ( "stagevendors.txt" , KLogger::DEBUG );



//SET UP EXCEPTION HANDLING
//http://stackoverflow.com/questions/5373780/how-to-catch-this-error-notice-undefined-offset-0
function exceptions_error_handler($severity, $message, $filename, $lineno) {
  global $sql;
  global $log;
  if (error_reporting() == 0) {
    return;
  }
  if (error_reporting() & $severity) {
 	$log->LogError($message);
 	$log->LogError($sql);
    //throw new ErrorException($message, 0, $severity, $filename, $lineno);
  }
}

set_error_handler('exceptions_error_handler');

//ADDED FOR USE ON THE SERVER WHERE
//ALL DATA FILES & SQLITE DBs WERE KEPT TOGETHER
//IN A DIRECTORY
$migrationdbdir = getenv("migrationdbdir");

//CONNECT TO SQLITE DB
$db = new SQLite3($migrationdbdir.'/olemigration.sqlite');
echo 'Connected to the database.';

//REMOVE ALL EXISTING DATA FROM THE SQLITE VENDORS TABLE
$query = "delete from vendors";
if ($db->exec($query)) echo "\n ALL OLD DATA DELETED FROM VENDORS TABLE \n\n";


//READ THE FILE
$exportdir = getenv("sirsiexportdir");
$file = "$exportdir/vendor.data";
$response = file_get_contents($file);

//break up each record by spliting via the 'DOCUMENT BOUNDARY' STRING
//COUNT HOW MANY DOCUMENT BOUNDARIES EXIST IN THE FILE
$thecount = mb_substr_count($response,"*** DOCUMENT BOUNDARY ***");
$responsearray = explode("*** DOCUMENT BOUNDARY ***",$response);
$counter = 0;

foreach ($responsearray as $onerecord) {
    $lines = explode("\n", $onerecord);
	$valuearray = array();
	$current = '';
	$newkeyvalue = array();
	$processingaddress = false;
	$addressnumber = "";
	$phonecount = 1;
	foreach ($lines as $line) {
		//SAMPLE LINES FROM THIS FILE:
		//.VEND_ID.   |aESS
		//.VEND_LIBRARY.   |aLEHIGH
		$keyvalue = explode("|", $line);

        //PULL OUT REPEAING ADDRESS
		if (strpos($line,'VEND_ADDR') && strpos($line,'BEGIN')) {
			if (strpos($line,'1')) $addressnumber = 1;
			if (strpos($line,'2')) $addressnumber = 2;
			if (strpos($line,'3')) $addressnumber = 3;
			$processingaddress = true;

		}

		if (strpos($line,'VEND_ADDR') && strpos($line,'_END')) {
			$processingaddress = false;
		}

        if (sizeof($keyvalue) > 1) {
				//clean up the values from the file
				$key = trim($keyvalue[0]);
				$value = trim($keyvalue[1]);
				$key = ltrim($key,".");
				$key = rtrim($key,".");
				$value = ltrim($value,"a");
				$value = SQLite3::escapeString($value);

				if (strpos($key,'STATE')) $key = "CITYSTATE";
				if (stripos($key, 'HONE')) {
					$key = $key . $phonecount;
					$phonecount++;
				}

				if ($processingaddress) {
					$key = "VEND_ADDR" . $addressnumber . "_" . $key;	
					$newkeyvalue[$key] = $value;		
				}
				else {

					$newkeyvalue[$key] = $value;	
				}

		}


	}

	//THE COLS. IN THE SQLITE DATABASE
	//WERE NAMED AFTER THE 'KEYS' FROM
	//THE FILE (E.G. VEND_ADDR)
	$sql = "insert into vendors (";
	foreach ($newkeyvalue as $key => $value) {
		$sql = $sql . "" . $key . ",";
	}
	$sql = rtrim($sql,",");
	$sql = $sql . ") values (";
	foreach ($newkeyvalue as $key => $value) {
		$sql = $sql . "'" . $value . "',";
	}
	$sql = rtrim($sql,",");
	$sql = $sql . ");";
    //$log->LogInfo($sql);

	$db->exec($sql);
	$counter++;
}

$db->close();

//SQLITE TABLE DEFINITION
/*
CREATE TABLE `vendors` (
  `VEND_ID` varchar(500) DEFAULT NULL,
  `VEND_LIBRARY` varchar(500) DEFAULT NULL,
  `VEND_NAME` varchar(500) DEFAULT NULL,
  `VEND_CUSTOMER` varchar(500) DEFAULT NULL,
  `VEND_CURRENCY` varchar(500) DEFAULT NULL,
  `VEND_GROUP1` varchar(500) DEFAULT NULL,
  `VEND_GROUP2` varchar(500) DEFAULT NULL,
  `VEND_GROUP3` varchar(500) DEFAULT NULL,
  `VEND_ORDER_ACTIVE` varchar(500) DEFAULT NULL,
  `VEND_PAYING_ACTIVE` varchar(500) DEFAULT NULL,
  `VEND_ADDR1_ATTN` varchar(500) DEFAULT NULL,
  `VEND_ADDR1_LINE` varchar(500) DEFAULT NULL,
  `VEND_ADDR1_STREET` varchar(500) DEFAULT NULL,
  `VEND_ADDR1_CITYSTATE` varchar(500) DEFAULT NULL,
  `VEND_ADDR1_ZIP` varchar(500) DEFAULT NULL,
  `VEND_ADDR1_FAX` varchar(500) DEFAULT NULL,
  `VEND_ADDR1_EMAIL` varchar(500) DEFAULT NULL,
  `VEND_ADDR2_ATTN` varchar(500) DEFAULT NULL,
  `VEND_ADDR2_STREET` varchar(500) DEFAULT NULL,
  `VEND_ADDR2_LINE` varchar(500) DEFAULT NULL,
  `VEND_ADDR2_CITYSTATE` varchar(500) DEFAULT NULL,
  `VEND_ADDR2_ZIP` varchar(500) DEFAULT NULL,
  `VEND_ADDR2_FAX` varchar(500) DEFAULT NULL,
  `VEND_ADDR2_EMAIL` varchar(500) DEFAULT NULL,
  `VEND_ADDR3_ATTN` varchar(500) DEFAULT NULL,
  `VEND_ADDR3_LINE` varchar(500) DEFAULT NULL,
  `VEND_ADDR3_STREET` varchar(500) DEFAULT NULL,
  `VEND_ADDR3_CITYSTATE` varchar(500) DEFAULT NULL,
  `VEND_ADDR3_ZIP` varchar(500) DEFAULT NULL,
  `VEND_ADDR3_EMAIL` varchar(500) DEFAULT NULL,
  `VEND_ADDR3_FAX` varchar(500) DEFAULT NULL,
  `VEND_ADDR3_PHONE1` varchar(500) DEFAULT NULL,
  `VEND_ADDR3_PHONE2` varchar(500) DEFAULT NULL,
  `VEND_ADDR1_PHONE1` varchar(500) DEFAULT NULL,
  `VEND_ADDR1_PHONE2` varchar(500) DEFAULT NULL,
  `VEND_ADDR2_PHONE1` varchar(500) DEFAULT NULL,
  `VEND_ADDR2_PHONE2` varchar(500) DEFAULT NULL,
  `NOTE` varchar(500) DEFAULT NULL,
  `COMMENT` varchar(500) DEFAULT NULL,
  `VEND_ACCOUNTADDR` varchar(500) DEFAULT NULL,
  `VEND_SERVICEADDR` varchar(500) DEFAULT NULL,
  `VEND_ORDERADDR` varchar(500) DEFAULT NULL,
  `VEND_ADDR3_COUNTRY` varchar(25) DEFAULT NULL,
  `VEND_ADDR2_COUNTRY` varchar(25) DEFAULT NULL,
  `VEND_ADDR1_COUNTRY` varchar(25) DEFAULT NULL,
  `VEND_ADDR1_PHONE3` varchar(500) DEFAULT NULL,
  `VEND_ADDR3_PHONE3` varchar(500) DEFAULT NULL,
  `VEND_ADDR2_PHONE3` varchar(500) DEFAULT NULL,
  `TERMS` varchar(50) DEFAULT NULL,
  `VEND_ADDR3_LINE4` varchar(500) DEFAULT NULL,
  `VEND_ADDR2_LINE4` varchar(500) DEFAULT NULL,
  `VEND_ADDR1_LINE4` varchar(500) DEFAULT NULL,
  `VEND_ADDR2_PHONE4` varchar(400) DEFAULT NULL,
  `VEND_ADDR2_LINE1` varchar(500) DEFAULT NULL,
  `VEND_ADDR1_PHONE4` varchar(500) DEFAULT NULL,
  `SAN` varchar(500) DEFAULT NULL,
  `VEND_ADDR1_LINE1` varchar(500) DEFAULT NULL,
  `VEND_ADDR2_PHONE5` varchar(500) DEFAULT NULL,
  `VEND_ADDR1_LINE2` varchar(500) DEFAULT NULL,
  `VEND_ADDR3_PHONE6` varchar(500) DEFAULT NULL
);
 */





















?>