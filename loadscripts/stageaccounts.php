<?php

//PARSES A TEXT FILE (hardcoded as funds.data)
//THAT WAS EXTRACTED FROM Sirsi/Unicorn USING THE selfund 
//API CALL AS SHOWN HERE: http://pastebin.com/yj0ZBWEk
//OR HERE: 
//https://github.com/jcamins/koha-migration-toolbox/blob/master/migration/Symphony/By_API/unicorn_export_scripts.txt
//THIS SCRIPT INSERTS THE DATA 
//INTO A SQLITE DATABASE AS A STAGING 
//ENVIRONMENT (WHICH WILL THEN BE WRITTEN TO A
//CSV FILE THAT OLE WILL INGEST USING
//loadaccountstocsv.php)


//SET UP LOGGING
require('KLogger.php');
$log = new KLogger ( "stageaccounts" , KLogger::DEBUG );

//ADDED FOR USE ON THE SERVER WHERE
//ALL DATA FILES & SQLITE DBs WERE KEPT TOGETHER 
//IN A DIRECTORY
$exportdir = getenv("sirsiexportdir");
if ($exportdir == "") die("stageaccounts.php SCRIPT FAILED: set sirsiexportdir environmental variable \n\n");
$migrationdbdir = getenv("migrationdbdir");
$db = new SQLite3($migrationdbdir.'/olemigration.sqlite');
echo 'Connected to the database.';


//REMOVE ALL EXISTING DATA FROM THE funds TABLE IN THE
//SQLITE DATABASE
$query = "delete from funds";
if ($db->exec($query)) echo "\n ALL OLD DATA DELETED FROM funds TABLE \n\n";


//SET UP EXCEPTION HANDLING
//http://stackoverflow.com/questions/5373780/how-to-catch-this-error-notice-undefined-offset-0
function exceptions_error_handler($severity, $message, $filename, $lineno) {
  global $sql;
  global $log;
  if (error_reporting() == 0) {
    return;
  }
  if (error_reporting() & $severity) {
      $log->LogError($severity . "--" . $message . " on line: " . $lineno);
  }
}

set_error_handler('exceptions_error_handler');

//PULL IN FILE/DATA
$file = "$exportdir/fund.data";
$response = file_get_contents($file);


//break up each record by spliting via the 'DOCUMENT BOUNDARY' STRING
//COUNT HOW MANY DOCUMENT BOUNDARIES EXIST IN THE FILE
$thecount = mb_substr_count($response,"*** DOCUMENT BOUNDARY ***");
$responsearray = explode("*** DOCUMENT BOUNDARY ***",$response);
$counter=0;
foreach ($responsearray as $onereocrd) {
	$counter++;
	//grab each line
	$lines = explode("\n", $onereocrd);
	$valuearray = array();
	$current = '';
	foreach ($lines as $line) {
		$keyvalue = explode("|", $line);
		//EXAMPLE LINES FROM THIS TEXT FILE:
		//.FUND_NAME.   |amarvin's money
		//'FUND_NAME' WOULD BE THE KEY
		//amarvin's money WOULD BE THE VALUE
		if (sizeof($keyvalue) < 2) {
			//COMMENTS & NOTES CAN
			//BE CARRIED OVER SEVERAL LINES
			if ($current == "COMMENT") {
				//COMMENT CONTINUED
				$value = trim($keyvalue[0]);
				$value = SQLite3::escapeString($value);
				$valuearray[$key] = $valuearray[$key] . $value;
			}
			elseif ($current == "NOTE") {
				//NOTE CONTINUED
				$value = trim($keyvalue[0]);
				$value = SQLite3::escapeString($value);
				$valuearray[$key] = $valuearray[$key] . $value;
			}
		}
		else {
			$key = trim($keyvalue[0]);
			$value = trim($keyvalue[1]);
			//TRIM OFF THE PERIODS FROM THE KEY AND 
			//'a' FROM THE VALUE
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
	$sql = "insert into funds (";
	foreach ($valuearray as $key => $value) {
		$sql = $sql . "" . $key . ",";
	}
	$sql = rtrim($sql,",");
	$sql = $sql . ") values (";
	foreach ($valuearray as $key => $value) {
		$value = str_replace('"',"'",$value);
		$sql = $sql . '"' . $value . '",';
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
 * CREATE TABLE `funds` (

  `FUND_ID` varchar(500) NOT NULL DEFAULT '',
  `FUND_ACCOUNT` varchar(500) DEFAULT NULL,
  `FUND_NAME` varchar(500) DEFAULT NULL,
  `FUND_LIBR` varchar(500) DEFAULT NULL,
  `DATE_CREATED` varchar(500) DEFAULT NULL,
  `CREATED_BY` varchar(500) DEFAULT NULL,
  `DATE_LAST_MODIFIED` varchar(500) DEFAULT NULL,
  `LAST_MODIFIED_BY` varchar(500) DEFAULT NULL,
  `FUND_LEVEL1` varchar(500) DEFAULT NULL,
  `FUND_LEVEL2` varchar(500) DEFAULT NULL,
  `FUND_LEVEL3` varchar(500) DEFAULT NULL,
  `FUND_LEVEL4` varchar(500) DEFAULT NULL,
  `NOTE` varchar(500) DEFAULT NULL,
  `COMMENT` varchar(25) DEFAULT NULL
);
*/
 

?>