<?php

//PARSES A TEXT FILE (hardcoded as charges.data)
//THAT WAS EXTRACTED FROM Sirsi/Unicorn USING THE selcharge
//API CALL AS SHOWN HERE: http://pastebin.com/yj0ZBWEk
//OR HERE:
//https://github.com/jcamins/koha-migration-toolbox/blob/master/migration/Symphony/By_API/unicorn_export_scripts.txt
//THIS SCRIPT INSERTS THE DATA
//INTO A SQLITE DATABASE AS A STAGING
//ENVIRONMENT (WHICH WILL THEN BE WRITTEN 
//TO THE OLE DB)



//SET UP LOGGING
require('KLogger.php');
$log = new KLogger ( "stagecharges.txt" , KLogger::DEBUG );

//ADDED FOR USE ON THE SERVER WHERE
//ALL DATA FILES & SQLITE DBs WERE KEPT TOGETHER
//IN A DIRECTORY
$migrationdbdir = getenv("migrationdbdir");
$exportdir = getenv("sirsiexportdir");


$db = new SQLite3($migrationdbdir.'/olemigration.sqlite');
echo 'Connected to the database.';

//REMOVE ALL EXISTING DATA FROM THE SQLITE charges TABLE
$query = "delete from charges";
if ($db->exec($query)) echo "\n ALL OLD DATA DELETED FROM CHARGES TABLE \n\n";


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


//READ FILE
$file = "$exportdir/charge.data";
$response = file_get_contents($file);


//break up each record by spliting via the DOCUMENT BOUNDARY
$thecount = mb_substr_count($response,"*** DOCUMENT BOUNDARY ***");
$responsearray = explode("*** DOCUMENT BOUNDARY ***",$response);
$counter++;
foreach ($responsearray as $onereocrd) {
	$counter++;
	if ( $counter % 1000 == 0 ) {
	  echo "Staged $counter charges\n";
	}
	//grab each line
	$lines = explode("\n", $onereocrd);
	$valuearray = array();
	$current = '';
	foreach ($lines as $line) {
		$keyvalue = explode("|", $line);
		//EXAMPLE LINE FROM THIS TEXT FILE
		//.CALL_ITEMNUM.   |a510 F858nE
		//.ITEM_ID.   |a39151000155099
		if (sizeof($keyvalue) < 2) {
			//NOTES CAN
			//BE CARRIED OVER SEVERAL LINES
			if ($current == "NOTE") {
				//NOTE CONTINUED
				$value = trim($keyvalue[0]);
				$value = SQLite3::escapeString($value);
				$valuearray[$key] = $valuearray[$key] . $value;
			}
		}
		else {
			$key = trim($keyvalue[0]);
			$value = trim($keyvalue[1]);
			$key = ltrim($key,".");
			$key = rtrim($key,".");
			$value = ltrim($value,"a");
			$value = SQLite3::escapeString($value);
			if ($key === $current) $valuearray[$key] = $valuearray[$key] . $value;
			else $valuearray[$key]=$value;
			$current = $key;
		}
	}
	
	//THE COLS. IN THE SQLITE DATABASE
	//WERE NAMED AFTER THE 'KEYS' FROM
	//THE FILE
	$sql = "insert into charges (";
	foreach ($valuearray as $key => $value) {
		$sql = $sql . "" . $key . ",";
	}
	$sql = rtrim($sql,",");
	$sql = $sql . ") values (";
	foreach ($valuearray as $key => $value) {
		$sql = $sql . "'" . $value . "',";
	}
	$sql = rtrim($sql,",");
	$sql = $sql . ");";
	$db->exec($sql);
}


echo "no of records was $thecount";
echo "\n\n";
echo "the count is " . $counter;


//SQLITE TABLE DEFINITION
/*
 * CREATE TABLE `charges` (

  `USER_ID` varchar(255) DEFAULT NULL,
  `CALL_ITEMNUM` varchar(255) DEFAULT NULL,
  `ITEM_ID` varchar(255) DEFAULT NULL,
  `ITEM_COPYNUM` varchar(255) DEFAULT NULL,
  `CHRG_DC` varchar(255) DEFAULT NULL,
  `CHRG_LIBRARY` varchar(255) DEFAULT NULL,
  `CHRG_DATEDUE` varchar(255) DEFAULT NULL

);
 */


?>