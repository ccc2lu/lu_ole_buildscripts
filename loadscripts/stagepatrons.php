<?php

//PARSES A TEXT FILE (hardcoded as users.data)
//THAT WAS EXTRACTED FROM Sirsi/Unicorn USING THE seluser
//API CALL AS SHOWN HERE: http://pastebin.com/yj0ZBWEk
//OR HERE:
//https://github.com/jcamins/koha-migration-toolbox/blob/master/migration/Symphony/By_API/unicorn_export_scripts.txt
//THIS SCRIPT INSERTS THE DATA
//INTO A SQLITE DATABASE AS A STAGING
//ENVIRONMENT (WHICH WILL THEN BE WRITTEN
//TO THE OLE DB)

//SET UP LOGGING
require('KLogger.php');
$log = new KLogger ( "stagepatrons.txt" , KLogger::DEBUG );


//SET UP EXCEPTION HANDLING
//http://stackoverflow.com/questions/5373780/how-to-catch-this-error-notice-undefined-offset-0
function exceptions_error_handler($severity, $message, $filename, $lineno) {
  global $log;
  global $sql;
  if (error_reporting() == 0) {
    return;
  }
  if (error_reporting() & $severity) {
    echo $message  . "---" . $lineno;
    $log->LogError($message);
 	$log->LogError($sql);
    //throw new ErrorException($message, 0, $severity, $filename, $lineno);
  }
}

set_error_handler('exceptions_error_handler');


//CONNECT TO THE SQLITE DB:
$db = new SQLite3('olemigration.sqlite');
echo 'Connected to the database.';


//REMOVE ALL EXISTING DATA FROM THE PATRONS TABLE (sqlite)
$query = "delete from patrons";
if ($db->exec($query)) echo "\n ALL OLD DATA DELETED FROM PATRONS TABLE \n\n";

//LOAD IN FILE THAT CONTAINS ALL ACTIVE PATRONS 
$response = file_get_contents("data/users.data");



//INIT COUNTER
$counter = 0;
//DETERMINE THE NUMBER OF PATRONS IN THIS ACCOUNT
$thecount = mb_substr_count($response,"*** DOCUMENT BOUNDARY ***");

//PARSE THE USERS FILE INTO AN ARRAY, LOOP THROUGH THE ARRAY
$responsearray = explode("*** DOCUMENT BOUNDARY ***",$response);
//FOR EACH PATRON
foreach ($responsearray as $ra) {
    //KEEP TRACK OF THE NUMBER OF PATRONS IN THE FILE
	$counter++;
	//PATRON RECORD HAS MANY VALUES - GET THEM INTO AN ARRAY
	$lines = explode("\n",$ra);
	$valuearray = array();
	$current = '';
	foreach ($lines as $line) {
			//DIVIDE EACH PATRON ATTRIBUTE INTO KEY/VALUE ARRAY
			$thevaluesarray = explode("|",$line);
			//EXAMPLE OF ROWS IN THIS TEXT FILE:
			//.USER_ID.   |aADMIN
			//.USER_ALT_ID.   |aADMIN
			
			//COUNTERS USED BELOW
			//NOT ALL PATRONS HAVE THE SAME FIELDS
			//EXAMPLE - SOME PATRONS HAVE
			//MORE THAN ONE STREET ADDRESS
			$streetCounter = 1;
			$phoneCounter = 1;
			$lineCounter = 1;
			$citystateCounter = 1;
			$emailCounter = 1;
			$zipCounter = 1;
			$firstname = "";
			$lastname = "";

			
			if (sizeof($thevaluesarray) < 2) {
				//NOTES CAN
				//BE CARRIED OVER SEVERAL LINES
				if ($current == "NOTE") {
					//NOTE CONTINUED
					$value = trim($thevaluesarray[0]);
					$value = SQLite3::escapeString($value);
					$valuearray["note1"] = $valuearray["note1"] . " " . $value;
					echo $valuearray["note1"];
				}
		    }

		    if (sizeof($thevaluesarray) < 2) {
		    	//COMMENTS CAN
		    	//BE CARRIED OVER SEVERAL LINES
				if ($current == "COMMENT") {
					//NOTE CONTINUED
					$value = trim($thevaluesarray[0]);
					$value = SQLite3::escapeString($value);
					$valuearray["comment1"] = $valuearray["comment1"] . " " . $value;
					echo $valuearray["comment1"];
				}
		    }
			
			//MAKE SURE THE LINE HELD A KEY/VALUE SPLIT BY A '|'
			if(is_array($thevaluesarray) && count($thevaluesarray) > 1) {
				try {
				    //GET KEY/VALUE
					$label = $thevaluesarray[0];
					$v = $thevaluesarray[1];
					//GET RID OF THE 'a' THAT PREFIXES EACH VALUE
					$v = substr($v, 1);
					//REPLACE DOUBLE QUOTES IN COMMENTS WITH SINGLE QUOTES
					$v = str_replace('"',"'",$v);

							//if ($label == ".USER_ALT_ID.   ") echo "";
							//INGORE ALT ID
							//if ($label == ".USER_ALT_ID.   ") echo "";
							
							//PLACE EACH VALUE IN A KEY/VALUE ARRAY
							if ($label == ".USER_ID.   ") $valuearray["patronlin"] = $v;
							if ($label == ".USER_NAME.   ") { 
								$valuearray["name"] = $v;
								$namearray = explode(",",$v);
								if (sizeof($namearray) > 0) $lastname = $namearray[0];
								if (sizeof($namearray) > 1)  $firstname = $namearray[1];
								$valuearray["firstname"] = $firstname;
								$valuearray["lastname"] =  $lastname;

							}
							if ($label == ".WORKPHONE. ") $valuearray["workphone"] = $v;
							if ($label == ".HOMEPHONE. ") $valuearray["phone"] = $v;
							if ($label == ".USER_PROFILE.   ") $valuearray["profile"] = $v;
							if ($label == ".USER_LOCATION.   ") $valuearray["location"] = $v;
							if ($label == ".USER_STATUS.   ") $valuearray["status"] = $v;
							if ($label == ".USER_PRIV_EXPIRES.   ") $valuearray["expires"] = $v;
							if ($label == ".USER_CATEGORY1.   ") $valuearray["cat1"] = $v;
							if ($label == ".USER_CATEGORY2.   ") $valuearray["cat2"] = $v;
							if ($label == ".NOTE. ") $valuearray["note1"] = $v;
							
							if ($label == ".STREET. ") { 
								$valuearray["street" . $streetCounter] = $v;
								$streetCounter++;
							}
							
							if ($label == ".CITY/STATE. ") { 
								$valuearray["citystate" . $citystateCounter] = $v;
								$citystateCounter++;
							}
							
							if ($label == ".ZIP. ") { 
								$valuearray["zip" . $zipCounter] = $v;
								$zipCounter++;
							}
							
							if ($label == ".EMAIL. ") { 
								$valuearray["email" . $emailCounter] = $v;
								$emailCounter++;
							}
							
							if ($label == ".LINE. ") { 
								$valuearray["line" . $emailCounter] = $v;
								$lineCounter++;
							}
							$current = trim($label);
							$current = ltrim($current,".");
							$current = rtrim($current,".");
							$current = ltrim($current,"a");

				}
				catch(Exception $e) {
					echo "ERROR OCURRED:";
					$log->LogError($e);
					var_dump($e);
				}
			}
			else {
			   //THIS LINE WASN'T NOT A KEY/VALUE WITH | -- IGNORE
			}	
		}

		//INSERT INTO THE SQLITE patrons TABLE
		//THE COLS. IN THE SQLITE DATABASE
		//WERE NAMED AFTER THE 'KEYS' FROM
		//THE FILE
		$sql = "INSERT INTO patrons (id";
		$c = 0;
		//GET ALL OF THE 'KEY'S
		foreach ($valuearray as $key => $value) {
			//CONSTRUCTION SQL STATEMENT:
			//if ($c != 0) $sql = $sql . ",";
			$sql = $sql . ",";
			$sql = $sql . $key;
			$c++;
		}
		
		$sql = $sql . ") VALUES (null";
		$c = 0;
		//GET ALL OF THE VALUES
		foreach ($valuearray as $key => $value) {
			//CONSTRUCTION SQL STATEMENT:
			//if ($c != 0) $sql = $sql . ",";
			$sql = $sql . ",";
			$sql = $sql . '"'.$value.'"';
			$c++;
		}
		
		$sql = $sql . ")";
			
	    if (array_key_exists("patronlin", $valuearray)) {
	    		    $db->exec($sql);
	    }
	    else {
	    	$log->LogError("missing patronlin " + $sql);
	    }
	}
	

echo "processed $counter patrons";
echo "\n\n";
echo "document headings in the file.....$thecount";

//SQLITE TABLE DEFINITION
/*
 * CREATE TABLE `patrons` (
  `patronid` varchar(255) DEFAULT NULL,
  `patronlin` varchar(255) DEFAULT NULL,
  `firstname` varchar(255) DEFAULT NULL,
  `lastname` varchar(255) DEFAULT NULL,
  `insertdone` tinyint(1) DEFAULT NULL,
  `email1` varchar(255) DEFAULT NULL,
  `email2` varchar(255) DEFAULT NULL,
  `street1` varchar(255) DEFAULT NULL,
  `street2` varchar(255) DEFAULT NULL,
  `line1` varchar(255) DEFAULT NULL,
  `line2` varchar(255) DEFAULT NULL,
  `citystate1` varchar(255) DEFAULT NULL,
  `citystate2` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `zip1` varchar(255) DEFAULT NULL,
  `zip2` varchar(255) DEFAULT NULL,
  `homephone` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `workphone` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `profile` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `expires` varchar(255) DEFAULT NULL,
  `cat1` varchar(255) DEFAULT NULL,
  `cat2` varchar(255) DEFAULT NULL,
  `note1` varchar(255) DEFAULT NULL,
  `note2` varchar(255) DEFAULT NULL,
  `comment1` int(11) DEFAULT NULL,
  `comment` int(11) DEFAULT NULL
, "id" INTEGER);
 */	
	
?>