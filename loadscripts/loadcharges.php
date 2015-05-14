<?php

//PULLS CHARGES DATA OUT OF
//THE 'charges' TABLE IN THE SQLITE STAGING DB
//PREVIOUSLY POPULATED WITH stagecharges.php
//AND INSERTS THEM INTO THE OLEDB
//**NOTE: THIS SCRIPT INSERTS ROWS DIRECTLY
//INTO THE OLE DB -- THIS SCRIPT GENERATES
//THE UNIQUE KEYS FOR EACH ROW INSTEAD OF
//USING THE '_S' TABLE PROVIDED BY OLE
//YOU HAVE TO SYNC UP THE _S TABLE AFTER YOU
//RUN THIS SCRIPT OR OLE WILL THINK
//THE STARTING POINT FOR THE INCREMENTAL
//KEY IS LESS THAN IT IS (MEANING - IT COULD TRY TO
//USE KEYS YOU'VE ALREADY INSERTED)
//TODO: USE THE _S TABLES FOR THIS SCRIPT!
//EXAMPLE:
//ole_dlvr_loan_t ---> ole_dlvr_loan_s (keeps
//track of keys for the '_T' table
//**NOTE: PREREQ FOR RUNNING THIS SCRIPT -- PATRONS AND ITEMS HAVE TO
//ALREADY BE LOADED SO THE CHARGES CAN LINK TO THEM


//SET UP LOGGING
require('KLogger.php');
$log = new KLogger ( "logcharges.txt" , KLogger::DEBUG );


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

//SET UP EXCEPTION HANDLING
//http://stackoverflow.com/questions/5373780/how-to-catch-this-error-notice-undefined-offset-0
function exceptions_error_handler($severity, $message, $filename, $lineno) {
  global $log;
  global $sql;
  if (error_reporting() == 0) {
    return;
  }
  if (error_reporting() & $severity) {
    echo $message . "-----" . $lineno;
    $log->LogError($message . "--------" . $lineno);
 	$log->LogError($sql);
    //throw new ErrorException($message, 0, $severity, $filename, $lineno);
  }
}

set_error_handler('exceptions_error_handler');

//CONTAINS THE DB USERID AND PASSWORD VARIABLES
//INITIALIZATION
require('proddbinfo.php');


$olecon=mysqli_connect("localhost","$userid","$password","OLE");
// CHECK DATABASE CONNECTION
if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  die;
}



//TODO: REMOVE?
$itemcon=mysqli_connect("localhost","$userid","$password","OLE");
// CHECK DATABASE CONNECTION
if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  die;
}

//TODO: REMOVE?
$olecontwo=mysqli_connect("localhost","$userid","$password","OLE");
// CHECK DATABASE CONNECTION
if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  die;
}

//CONNECT TO THE SQLITE DB:
$db = new SQLite3('olemigration.sqlite');
echo 'Connected to the database.';

//GET ALL OF THE CHARGES FROM THE SQLITE DB
$query = "select * from charges";
$result = $db->query($query);


$uniqueid = 245;
$counter = 0;

while ($row = $result->fetchArray(SQLITE3_ASSOC)) { 
	    $counter++;
	    $lin = $row['USER_ID'];
	    
	    //GET PATRON ID FROM OLE DB USING PATRON BARCODE (lin)
	    //THIS TABLE REQUIRES PATRON ID (NOT BARCODE)
        $query = "select OLE_PTRN_ID FROM OLE_PTRN_T WHERE BARCODE = '" . $lin . "'";
        $resulttwo = mysqli_query($olecontwo,$query);
        $rowtwo = mysqli_fetch_array($resulttwo);
        $patronid = $rowtwo['OLE_PTRN_ID'];
        //END PATRON ID LOOKUP
        
        $barcode = $row['ITEM_ID'];
	    $item_copynum = $row["ITEM_COPYNUM"];
	    $duedate = $row["CHRG_DATEDUE"];
	    if ($duedate == "NEVER") $duedate = "20250101";
	    $chargedate = $row["CHRG_DC"];
	    
	    //LOOKUP THE ITEM ID FROM OLE -- THE CHARGES DUMP ONLY HAS THE BARCODE
	    $itemquery = "select item_id from OLE_DS_ITEM_T where barcode = '$barcode'";
	    $itemresults = mysqli_query($itemcon,$itemquery);
	    $item= mysqli_fetch_array($itemresults);

	    try {
	       $item_id = $item['item_id'];
	    }
	    catch(Exception $e) {
	   	   echo "failed to find item id";
	    }
	    //END LOOKUP ITEM ID
	    //ITEM ID REQUIRES THIS PREFIX
	    $item_id = "wio-" . $item_id;

	    //charge renewal count
	    //will be migrated post go live
	    $r_count = 0;

	 
	    $stmt = mysqli_stmt_init($olecon);
	    $obj_id = v4();

	    //TODO -- WHY IS CIR_POLICY_ID A STRING?  SHOULDN'T IT BE A CODE?
	    //TODO -- ?CREATE AN OPERATOR FOR THIS INITIAL MIGRATION -- NOW -- HARDCODED OLE-QUICKSTART
	    //WE'LL NEED TO IMPORT OUR POLICIES INTO OLE...AND LINK OUR OLD
	    //TRANSACTIONS TO THE NEW OLE CIRC POLICIES
	    //TODO -- ALSO MACHINE ID AND OVERIDE OPERTOR ID BOTH HARDCODED TO MACH12233 AND 1130
	   
	    //CALL INSERT STATEMENT:
	    //ole_dlvr_loan_t
	    mysqli_stmt_prepare($stmt, "INSERT INTO ole_dlvr_loan_t 
		(LOAN_TRAN_ID ,CIR_POLICY_ID,ITM_ID,OLE_PTRN_ID,PROXY_PTRN_ID,CIRC_LOC_ID,OPTR_CRTE_ID,MACH_ID,OVRR_OPTR_ID,NUM_RENEWALS,CRTSY_NTCE,NUM_OVERDUE_NOTICES_SENT,N_OVERDUE_NOTICE,OLE_RQST_ID,REPMNT_FEE_PTRN_BILL_ID,CRTE_DT_TIME,CURR_DUE_DT_TIME,PAST_DUE_DT_TIME,OVERDUE_NOTICE_DATE,OBJ_ID,VER_NBR,ITEM_UUID) 
	    	                                 VALUES (?,'Check out Circulation Policy Set 1',?,?,null,'1','ole-quickstart','MACH12233','1130',?,'N',null,null,null,null,?,?,null,null,?,1,?)");
	    mysqli_stmt_bind_param($stmt, "ssssssss", $uniqueid,$barcode,$patronid,$r_count,$chargedate,$duedate,$obj_id,$item_id);
	    mysqli_stmt_execute($stmt);

	    //UPDATE THE ITEM TO REFLECT 'LOANED STATUS'
		mysqli_stmt_prepare($stmt, "UPDATE OLE_DS_ITEM_T SET ITEM_STATUS_ID = 2 WHERE ITEM_ID = ?");
		mysqli_stmt_bind_param($stmt, "s", $item_id);
		mysqli_stmt_execute($stmt);


		echo $counter;
		echo "\n\n";
		$uniqueid++;

}
echo "\n\n process $counter records";

?>