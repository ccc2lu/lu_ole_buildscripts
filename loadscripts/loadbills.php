<?php

//PARSES A TEXT FILE (hardcoded as bill.data)
//THAT WAS EXTRACTED FROM Sirsi/Unicorn USING THE selbill
//API CALL AS SHOWN HERE: http://pastebin.com/yj0ZBWEk
//OR HERE:
//https://github.com/jcamins/koha-migration-toolbox/blob/master/migration/Symphony/By_API/unicorn_export_scripts.txt
//THIS SCRIPT INSERTS THE DATA
//INTO THE OLE DATABASE
//**IMPORTANT NOTE: THIS SCRIPT INSERTS ROWS DIRECTLY
//INTO THE OLE DB -- THIS SCRIPT GENERATES
//THE UNIQUE KEYS FOR EACH ROW INSTEAD OF
//USING THE '_S' TABLE PROVIDED BY OLE
//YOU HAVE TO SYNC UP THE _S TABLES AFTER YOU
//RUN THIS SCRIPT OR OLE WILL THINK
//THE STARTING POINT FOR THE INCREMENTAL
//KEY IS LESS THAN IT IS (MEANING - IT COULD TRY TO
//USE KEYS YOU'VE ALREADY INSERTED)
//THIS SCRIPT INSERTS INTO MULTIPLE TABLES
//RECONCILE THE _T AND _S TABLES FOR EACH ONE
//TODO: USE THE _S TABLES FOR THIS SCRIPT INSTEAD!
//EXAMPLE:
//OLE_DLVR_PTRN_BILL_T ---> OLE_DLVR_PTRN_BILL_S


//SET UP LOGGING
require('KLogger.php');
$log = new KLogger ( "logbills.txt" , KLogger::DEBUG );

//used to generate obj_id(s)
//http://php.net/manual/en/function.uniqid.php
function v4() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

      // 32 bits for "time_low"
      mt_rand(0, 0xffff), mt_rand(0, 0xffff),

      // 16 bits for "time_mid"
      mt_rand(0, 0xffff),

      // 16 bits for "time_hi_and_version",
      // four most significant bits holds version number 4
      mt_rand(0, 0x0fff) | 0x4000,

      // 16 bits, 8 bits for "clk_seq_hi_res",
      // 8 bits for "clk_seq_low",
      // two most significant bits holds zero and one for variant DCE1.1
      mt_rand(0, 0x3fff) | 0x8000,

      // 48 bits for "node"
      mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
  }


//CONTAINS THE DB USERID AND PASSWORD VARIABLES
//INITIALIZATION
require('proddbinfo.php');

$olecon=mysqli_connect("localhost","$userid","$password","OLE");
// CHECK DATABASE CONNECTION
if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  die;
}

//TODO: REMOVE
$olecontwo=mysqli_connect("localhost","$userid","$password","OLE");
// CHECK DATABASE CONNECTION
if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  die;
}
$stmt = mysqli_stmt_init($olecon);

//TODO: REMOVE
$itemcon=mysqli_connect("localhost","$userid","$password","OLE");
// CHECK DATABASE CONNECTION
if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  die;
}
	
//TODO: REMOVE
$quickconnection=mysqli_connect("localhost","$userid","$password","OLE");
// CHECK DATABASE CONNECTION
if (mysqli_connect_errno()) {
	 echo "Failed to connect to staging database: " . mysqli_connect_error();
	die;
}
	

//LOAD IN FILE
$response = file_get_contents("data/bill.data");

	

//SET UP EXCEPTION HANDLING
//http://stackoverflow.com/questions/5373780/how-to-catch-this-error-notice-undefined-offset-0
function exceptions_error_handler($severity, $message, $filename, $lineno) {
  if (error_reporting() == 0) {
    echo "here";
    return;
  }
  if (error_reporting() & $severity) {
    echo $message;
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
  }
}

set_error_handler('exceptions_error_handler');




$counter = 0;
//break up each record by spliting via the 'DOCUMENT BOUNDARY' STRING
//COUNT HOW MANY DOCUMENT BOUNDARIES EXIST IN THE FILE
$thecount = mb_substr_count($response,"*** DOCUMENT BOUNDARY ***");
$responsearray = explode("*** DOCUMENT BOUNDARY ***",$response);
$uniqueid = 1;
foreach ($responsearray as $ra) {
	    $stringtowritetofile = "";
		$counter++;
		$lines = explode("\n",$ra);
		//EACH KEY/VALUE FOR THE CHARGE -- WRITE OUT JUST THE VALUE ('1' IN THE ARRAY -- '0' HOLDS THE LABEL
		$valuearray = array("userid"=>'',"date"=>'',"amount"=>'',"reason"=>'',"barcode"=>'',"copyno"=>'',"callno"=>'');
	
	    $uniqueid++;
		foreach ($lines as $line) {
			$thevaluesarray = explode("|",$line);
			//EXAMPLE TEXT FROM THE BILLS FILE:
			//.BILL_AMOUNT.   |a$39.95
			//.BILL_REASON.   |aLONGOVERDU
			$v="";
			if(is_array($thevaluesarray) && count($thevaluesarray) > 1) {
				try {				    
					$label = $thevaluesarray[0];
					$v = $thevaluesarray[1];

					//if ($label == ".USER_ALT_ID.   ") echo "";
					if ($label == ".USER_ID.   ") {
						   $v = substr($v, 1);
						   //GET THE OLE PATRON ID USING PATRON BARCODE
						   //FROM THIS FILE
						   $query = "select OLE_PTRN_ID FROM OLE_PTRN_T WHERE BARCODE = '" . $v . "'";
					       $resulttwo = mysqli_query($olecontwo,$query);
					       $rowtwo = mysqli_fetch_array($resulttwo);
					       $patron = $rowtwo['OLE_PTRN_ID'];

						   $valuearray["userid"] = $patron;
					}
					if ($label == ".BILL_DB.   ") $valuearray["date"] = $v;
					if ($label == ".BILL_AMOUNT.   ") $valuearray["amount"] = $v;
					if ($label == ".BILL_REASON.   ") $valuearray["reason"] = $v;
					if ($label == ".ITEM_ID.   ") {
					  $valuearray["barcode"] = substr($v, 1);
					  //echo "Barcode is " . $valuearray["barcode"] . "\n";
					}
					if ($label == ".ITEM_COPYNUM.   ") $valuearray["copyno"] = $v;
					if ($label == ".CALL_ITEMNUM.   ") $valuearray["callno"] = $v;
				}
				catch(Exception $e) {
					$log->LogError("PROBLEM PARSING FILE TO ARRAY");
				}
			}
			else {
				//DO NOTHING			   
			}

			
		}

		$userid = $valuearray['userid'];
		
		$billamount = $valuearray["amount"];
		$billamount = substr($billamount, 2);
		$billamount = $billamount +0;
		echo "Bill amount: $billamount\n";

		$billdate = $valuearray["date"];
	    $date = new DateTime($billdate);
        $datetime = $date->format('Y-m-d H:i:s');
        $datestring = $date->format('Y-m-d');
        echo "$datetime .............  $datestring \n\n";


		//initialize fee type to 1;
		//in the else (below) -- if there is no barcode -- change the fee type to 3 
		//which does not require a barcode
		$feetype = 1; 
		if ( isset($valuearray["barcode"]) && strlen($valuearray["barcode"]) > 0 ) {
		  $barcode = $valuearray["barcode"];
		  //GET THE ITEM ID -- NEEDED FOR THE BILL
		  $itemquery = "select item_id from OLE_DS_ITEM_T where barcode = '$barcode'";
		  echo "Searching for item: $itemquery\n";
		  $itemresults = mysqli_query($itemcon,$itemquery);
		  
		  $item= mysqli_fetch_array($itemresults);
		  try {
		    $item_id = 'wio-' . $item['item_id'];
		    //$log->LogError($item_id);
		  }
		  catch(Exception $e) {
		      echo "failed to find item by barcode $barcode\n";
		      //if there is no barcode -- fee type must be 3 (service fee)
		      //and item_id has to be null
		      $item_id = null;
		      $feetype = 3;
		  }
		  //echo "Item ID found is $item_id\n";


		} else {
		  //changed barcode failover value from 0 to ''
		  //to match the operations of the ole interface
		  $barcode = '';
		  //if there is no barcode -- fee type must be 3 (service fee)
		  //and item_id has to be null
		  $item_id = null;
		  $feetype = 3;
		}

		
		$obj_id = v4();
	    mysqli_stmt_prepare($stmt, "INSERT INTO OLE_DLVR_PTRN_BILL_T (PTRN_BILL_ID,OLE_PTRN_ID,PROXY_PTRN_ID,TOT_AMT_DUE,UNPAID_BAL,PAY_METHOD_ID,PAY_AMT,PAY_DT,PAY_OPTR_ID,PAY_MACHINE_ID,CRTE_DT_TIME,OPTR_CRTE_ID,OPTR_MACHINE_ID,PAY_NOTE,NOTE,OBJ_ID,VER_NBR,BILL_REVIEWED) 
		                        VALUES (?,?,null,?,?,null,null,null,null,null,?,'test','Test',null,'',?,1,'N')");
	    mysqli_stmt_bind_param($stmt, "ssddss", $uniqueid,$userid,$billamount,$billamount,$datestring,$obj_id);
	    mysqli_stmt_execute($stmt);
	    
	    $obj_id = v4();
	    mysqli_stmt_prepare($stmt, "INSERT INTO OLE_DLVR_PTRN_BILL_FEE_TYP_T (ID,PTRN_BILL_ID,FEE_TYP_ID,ITM_BARCODE,FEE_TYP_AMT,PAY_STATUS_ID,ITM_UUID,BALANCE_AMT,PTRN_BILL_DATE) 
		                        VALUES (?,?,?,?,?,'1',?,?,?)");
	    mysqli_stmt_bind_param($stmt, "ssssdsds", $uniqueid,$uniqueid,$feetype,$barcode,$billamount,$item_id,$billamount,$datetime);
	    mysqli_stmt_execute($stmt);

	}

	
	echo "processed $counter bills";
	echo "\n\n";
	echo "counted.....$thecount";
	
	
?>