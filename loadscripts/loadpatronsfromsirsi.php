<?php

//PULLS PATRON DATA OUT OF
//THE 'patrons' TABLE IN THE SQLITE STAGING DB
//PREVIOUSLY POPULATED WITH stagepatrons.php
//(USING DATA FROM SIRSI)
//AND INSERTS THEM INTO THE OLEDB
//**IMPORTANT NOTE: THIS SCRIPT INSERTS ROWS DIRECTLY
//INTO THE OLE DB -- THIS SCRIPT GENERATES
//THE UNIQUE KEYS FOR EACH ROW INSTEAD OF
//USING THE '_S' TABLE PROVIDED BY OLE
//YOU HAVE TO SYNC UP THE _S TABLES AFTER YOU
//RUN THIS SCRIPT OR OLE WILL THINK
//THE STARTING POINT FOR THE INCREMENTAL
//KEY IS LESS THAN IT IS (MEANING - IT COULD TRY TO
//USE KEYS YOU'VE ALREADY INSERTED)
//THIS SCRIPT INSERTS INTO SEVERAL TABLES
//RECONCILE THE _T AND _S TABLES FOR EACH ONE
//TODO: USE THE _S TABLES FOR THIS SCRIPT INSTEAD!
//EXAMPLE:
//KRIM_ENTITY_T ---> KRIM_ENTITY_ID_S


//SET UP LOGGING
require('KLogger.php');
$log = new KLogger ( "insertpatronsintodbfromsirsi.txt" , KLogger::DEBUG );

//CONTAINS THE DB USERID AND PASSWORD VARIABLES
//INITIALIZATION
require('proddbinfo.php');

//CONNECT TO OLD DB
$olecon=mysqli_connect("localhost","$userid","$password","OLE");
// CHECK DATABASE CONNECTION
if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  die;
}

//TODO REMOVE
$olecontwo=mysqli_connect("localhost","$userid","$password","OLE");
// CHECK DATABASE CONNECTION
if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  die;
}


//get existing borrower types from OLE
//WILL USE AS A LOOKUP ARRAY LATER WHEN
//INSERTING INTO THE PATRON TABLE
$query = "select * from OLE_DLVR_BORR_TYP_T";
$result = mysqli_query($olecon,$query);
while ($row = mysqli_fetch_array($result)) {
		$patronTypes[$row['DLVR_BORR_TYP_CD']] = $row['DLVR_BORR_TYP_ID'];
}

//get existing statistical categories from OLE
//WILL USE AS A LOOKUP ARRAY LATER WHEN
//INSERTING INTO THE PATRON TABLE
$query = "select * from ole_dlvr_stat_cat_t";
$result = mysqli_query($olecon,$query);
while ($row = mysqli_fetch_array($result)) {
		$statcats[$row['OLE_DLVR_STAT_CAT_CD']] = $row['OLE_DLVR_STAT_CAT_ID'];
}

//get existing note types
//WILL USE AS A LOOKUP ARRAY LATER WHEN
//INSERTING INTO THE PATRON NOTES TABLE
$query = "select * from ole_ptrn_nte_typ_t";
$result = mysqli_query($olecon,$query);
while ($row = mysqli_fetch_array($result)) {
		$notearray[$row['OLE_PTRN_NTE_TYPE_CD']] = $row['OLE_PTRN_NTE_TYP_ID'];
}


//this array will be used to translate old sirsi patron profiles
//to ole borrower types
//EXAMPLE OF ARRAY BELOW
$borrowerTypeArray = array("FACULTYTEST"=>"FACULTY",
    "LIBRARYUSETEST"=>"LIBRARYUSE",
    "RETIREE"=>"FACULTY",
    "GRADSTUDNT"=>"GRAD",
    "GLOBAL"=>"OTHER");


//SET UP EXCEPTION HANDLING
//http://stackoverflow.com/questions/5373780/how-to-catch-this-error-notice-undefined-offset-0
function exceptions_error_handler($severity, $message, $filename, $lineno) {
  global $log;
  global $sql;
  if (error_reporting() == 0) {
    echo "here";
    return;
  }
  if (error_reporting() & $severity) {
    echo $message . "---LINE NUMBER: " . $lineno . "\n\n";
    $log->LogError($message . "---LINE NUMBER: " . $lineno);
 	$log->LogError($sql);
    //throw new ErrorException($message, 0, $severity, $filename, $lineno);
  }
}

set_error_handler('exceptions_error_handler');


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


//CONNECT TO THE SQLITE DB:
//http://us1.php.net/manual/en/book.sqlite3.php
$db = new SQLite3('olemigration.sqlite');
echo 'Connected to the database.';

//GET ALL OF THE PATRONS WE EXPORTED FROM SIRSI:
$query = "select * from patrons";
$result = $db->query($query);


$idcounter = 400000;
$counter = 0;
while ($row = $result->fetchArray(SQLITE3_ASSOC)) { 	
       $exists = false;
       $lin = $row['patronlin'];
       $log->LogError("count is $counter and lin is $lin");
       //DID WE ALREADY LOAD THIS PATRON THROUGH THE IDM?
       //loadpatrons.php loaded a list
       //of our patrons from the idm
       //we only want to load into OLE
       //any patrons *not* already loaded in
       $querytwo = "select * from OLE_PTRN_T where BARCODE = '" . $lin . "'";
	   $idresult = mysqli_query($olecontwo,$querytwo);
	   if (mysqli_num_rows($idresult) > 0) $exists = true;

	   //if the patron does not exist -- insert
       if (!$exists) {
       		   $log->LogError("could not find: $lin -- adding patron from sirsi");
       	       $idcounter++;
			   $patronid = $idcounter;
			   $patronbarcode = $lin;
			  
			   $lastname = $row['lastname'];
			   $firstname = $row['firstname'];
			   $borrowertype = $row['profile'];
			   $email = $row['email1'];
			   $phone = $row['phone'];
			   $streetaddress = $row['street1'];
			   $middle = "";
			   $citystatestring = $row['citystate1'];
			   $citystatearray = explode(",", $citystatestring);
			   if (sizeof($citystatearray) > 1) {
			   		$city = $citystatearray[0];
			   		$state = $citystatearray[1];
			   		$state = trim($state);
			   }
			   else {
			   		$city = null;
			   		$state = null;
			   }
			   $zip = $row["zip1"];
			   //TRANSLATE FROM SIRSI BORROWER TYPE TO OLE BORROWER TYPE
			   $oleborrowertype = $borrowerTypeArray["$borrowertype"];
			   //THEN GET THE CODE FOR THE OLE BORROWER TYPE (E.G. 1, 2, 3)
			   $oleborrowertypecode = $patronTypes["$oleborrowertype"];

			   if ($oleborrowertypecode == null) {
			   	echo "sirsi type is $borrowertype which is oleborrowertype $oleborrowertype which is code $oleborrowertypecode";
			   	echo "patronid is $patronid and lin is $lin";
			   	$oleborrowertypecode = "OTHER";
			   }

			   $stmt = mysqli_stmt_init($olecon);

			   $obj_id = v4(); //GETS A NEW OBJ_ID
			   //#1) $query = "INSERT INTO KRIM_ENTITY_T...
			   mysqli_stmt_prepare($stmt, "INSERT INTO KRIM_ENTITY_T (ENTITY_ID,ACTV_IND,VER_NBR,OBJ_ID) VALUES (?,'Y',1,?)");
			   mysqli_stmt_bind_param($stmt, "ss", $patronid,$obj_id);
			   mysqli_stmt_execute($stmt);
			   $log->LogError("Error: %s.\n", mysqli_stmt_error($stmt));

			  
			   $obj_id = v4();
			   //#2) $query = "INSERT INTO KRIM_ENTITY_ENT_TYP_T...
			   mysqli_stmt_prepare($stmt, "INSERT INTO KRIM_ENTITY_ENT_TYP_T (ENTITY_ID,ENT_TYP_CD,ACTV_IND,VER_NBR,OBJ_ID) VALUES (?,'PERSON','N',1,?)");
			   mysqli_stmt_bind_param($stmt, "ss", $patronid,$obj_id);
			   mysqli_stmt_execute($stmt);
			   $log->LogError("Error: %s.\n", mysqli_stmt_error($stmt));

			  
			   $obj_id = v4();
			   //#3) $query = "INSERT INTO KRIM_ENTITY_NM_T...
			   mysqli_stmt_prepare($stmt, "INSERT INTO KRIM_ENTITY_NM_T (ENTITY_NM_ID,ENTITY_ID,NM_TYP_CD,FIRST_NM,MIDDLE_NM,LAST_NM,PREFIX_NM,TITLE_NM,SUFFIX_NM,NOTE_MSG,NM_CHNG_DT,DFLT_IND,ACTV_IND,VER_NBR,OBJ_ID) VALUES (?,?,null,?,?,?,'',null,'',null,null,'Y','Y',1, ?)");
			   mysqli_stmt_bind_param($stmt, "ssssss", $patronid,$patronid,$firstname,$middle,$lastname,$obj_id);
			   mysqli_stmt_execute($stmt);
			   $log->LogError("Error: %s.\n", mysqli_stmt_error($stmt));


			   $obj_id = v4();
			   //INSERT A TEST EMAIL FOR EVERY PATRON FOR OUR INITIAL TESTING
			   //#4) $query = "INSERT INTO KRIM_ENTITY_EMAIL_T...
			   //*temp -- put in a general email as the active email for testing
			   mysqli_stmt_prepare($stmt, "INSERT INTO KRIM_ENTITY_EMAIL_T (ENTITY_EMAIL_ID,ENTITY_ID,ENT_TYP_CD,EMAIL_TYP_CD,EMAIL_ADDR,DFLT_IND,ACTV_IND,VER_NBR,OBJ_ID) VALUES (?,?,'PERSON','OTH','ourtestemail@lehigh.edu','Y','Y',1,?)");
			   mysqli_stmt_bind_param($stmt, "sss", $patronid,$patronid,$obj_id);
			   mysqli_stmt_execute($stmt);
			   $log->LogError("Error: %s.\n", mysqli_stmt_error($stmt));

			   //*insert real email as inactive - will active later, after testing
			   if ($email != null && $email != "") {
			      $obj_id = v4();
				  mysqli_stmt_prepare($stmt, "INSERT INTO KRIM_ENTITY_EMAIL_T (ENTITY_EMAIL_ID,ENTITY_ID,ENT_TYP_CD,EMAIL_TYP_CD,EMAIL_ADDR,DFLT_IND,ACTV_IND,VER_NBR,OBJ_ID) VALUES (?,?,'PERSON','CMP',?,'N','N',1,?)");
				  $emailid = $patronid . '01';
				  mysqli_stmt_bind_param($stmt, "ssss", $emailid,$patronid,$email,$obj_id);
				  mysqli_stmt_execute($stmt);
				  //$log->LogError("Error: %s.\n", mysqli_stmt_error($stmt));
			  }	
		


			  $obj_id = v4();
			  //#5) $query = "INSERT INTO KRIM_ENTITY_ADDR_T...
			  mysqli_stmt_prepare($stmt, "INSERT INTO KRIM_ENTITY_ADDR_T (ENTITY_ADDR_ID,ENTITY_ID,ADDR_TYP_CD,ENT_TYP_CD,CITY,STATE_PVC_CD,postal_cd,POSTAL_CNTRY_CD,ATTN_LINE,ADDR_LINE_1,ADDR_LINE_2,ADDR_LINE_3,ADDR_FMT,MOD_DT,VALID_DT,VALID_IND,NOTE_MSG,DFLT_IND,ACTV_IND,VER_NBR,OBJ_ID) VALUES (?,?,'HM','PERSON',?,?,?,'US',null,?,'','',null,null,null,'N',null,'Y','Y',1,?)");
			  mysqli_stmt_bind_param($stmt, "sssssss", $patronid,$patronid,$city,$state,$zip,$streetaddress,$obj_id);
			  mysqli_stmt_execute($stmt);
			  $log->LogError("Error: %s.\n", mysqli_stmt_error($stmt));

			  $obj_id = v4();
			  //#6) $query = "INSERT INTO OLE_PTRN_T...
			  mysqli_stmt_prepare($stmt, "INSERT INTO OLE_PTRN_T (OLE_PTRN_ID,BARCODE,BORR_TYP,ACTV_IND,GENERAL_BLOCK,PAGING_PRIVILEGE,COURTESY_NOTICE,DELIVERY_PRIVILEGE,EXPIRATION_DATE,ACTIVATION_DATE,GENERAL_BLOCK_NT,OLE_SRC,OLE_STAT_CAT,PHOTOGRAPH,OBJ_ID,VER_NBR) VALUES (?,?,?,'Y','N','N','Y','N',null,'2014-02-04','','10','1',null,?,1)");
			  mysqli_stmt_bind_param($stmt, "ssss", $patronid,$lin,$oleborrowertypecode,$obj_id);
			  mysqli_stmt_execute($stmt);
			  $log->LogError("Error: %s.\n", mysqli_stmt_error($stmt));

			  $obj_id = v4();
			  //#7) $query = "INSERT INTO OLE_DLVR_ADD_T...
			  mysqli_stmt_prepare($stmt, "INSERT INTO OLE_DLVR_ADD_T (DLVR_ADD_ID,OLE_PTRN_ID,ENTITY_ADDR_ID,OLE_ADDR_SRC,DLVR_PTRN_ADD_VER,ADD_VALID_FROM,ADD_VALID_TO,OBJ_ID,VER_NBR) VALUES (?,?,?,'1','Y',null,null,?,1)");
			  mysqli_stmt_bind_param($stmt, "ssss", $patronid,$patronid,$patronid,$obj_id);
			  mysqli_stmt_execute($stmt);	
			  $log->LogError("Error: %s.\n", mysqli_stmt_error($stmt));

			  if ($phone != null && $phone != "") {
				  $obj_id = v4();
				  $type = 'OTH'; //FOR NOW
				  //#8) $query = INSERT INTO KRIM_ENTITY_PHONE_T...
				  mysqli_stmt_prepare($stmt, "INSERT INTO KRIM_ENTITY_PHONE_T (ENTITY_PHONE_ID,OBJ_ID,VER_NBR,ENTITY_ID,ENT_TYP_CD,PHONE_TYP_CD,PHONE_NBR,DFLT_IND,ACTV_IND) VALUES(?,?,1,?,'PERSON',?,?,'Y','Y')");
				  mysqli_stmt_bind_param($stmt, "sssss", $patronid,$obj_id,$patronid,$type,$phone);
				  mysqli_stmt_execute($stmt);	
				  $log->LogError("Error: %s.\n", mysqli_stmt_error($stmt));
               }				 


		      $counter++;
		      echo $counter;
		      echo "\n\n";
		}
		//END -- INSERT MISSING PATRON
		
	    //FOR EVERY PATRON -- EVEN THOSE ALREADY
	    //INSERTED VIA THE IDM FILE
	    //UPDATE STAT CAT AND COMMENTS	
	    $lin = $row['patronlin'];	
	    //get patron id
	    $query = "select OLE_PTRN_ID FROM OLE_PTRN_T WHERE BARCODE = '" . $lin . "'";
	    $resulttwo = mysqli_query($olecontwo,$query);
	    $rowtwo = mysqli_fetch_array($resulttwo);
	    $patron_id = $rowtwo['OLE_PTRN_ID'];
	    //


	    $borrowertype = $row['profile'];
	    $cat = $statcats["$borrowertype"];
	    //echo "---$cat---";
	    //update stat cat with sirsi profile
	    $stmt = mysqli_stmt_init($olecon);
	    mysqli_stmt_prepare($stmt, "UPDATE OLE_PTRN_T SET OLE_STAT_CAT = ? WHERE OLE_PTRN_ID = ?");
	    mysqli_stmt_bind_param($stmt, "ss", $cat,$patronid);
	    mysqli_stmt_execute($stmt);
	    //$log->LogError("Error: %s.\n", mysqli_stmt_error($stmt));

	    //insert comments as notes
	    if ($row['comment1'] != null && $row['comment1'] != "" && $row['comment1'] != "**REQUIRED FIELD**") {
	   		$obj_id = v4();
	   		$notetype = "s-commen";
	   		$notetypecode = $notearray["$notetype"];
	   		$notetext = $row['comment1'];
		   	    mysqli_stmt_prepare($stmt, "INSERT INTO OLE_PTRN_NTE_T (OLE_PTRN_NTE_ID,OBJ_ID,VER_NBR,
		   	  	OLE_PTRN_ID,OLE_PTRN_NTE_TYP_ID,OLE_PTRN_NTE_TXT,ACTV_IND) 
		   	    VALUES (?,?,1,?,?,?,'Y')");
		   	    mysqli_stmt_bind_param($stmt, "sssss", $obj_id,$obj_id,$patron_id,$notetypecode,$notetext);
		   	    mysqli_stmt_execute($stmt);	
	  	    //$log->LogError("Error: %s.\n", mysqli_stmt_error($stmt));
	    }

	    //insert notes as notes
	    if ($row['note1'] != null && $row['note1'] != "") {
	   		$obj_id = v4();
	   		$notetype = "sirsi";
	   		$notetypecode = $notearray["$notetype"];
	   		$notetext = $row['note1'];
		   	    mysqli_stmt_prepare($stmt, "INSERT INTO OLE_PTRN_NTE_T (OLE_PTRN_NTE_ID,OBJ_ID,VER_NBR,
		   	  	OLE_PTRN_ID,OLE_PTRN_NTE_TYP_ID,OLE_PTRN_NTE_TXT,ACTV_IND) 
		   	    VALUES (?,?,1,?,?,?,'Y')");
		   	    mysqli_stmt_bind_param($stmt, "sssss", $obj_id,$obj_id,$patron_id,$notetypecode,$notetext);
		   	    mysqli_stmt_execute($stmt);	
	  	    //$log->LogError("Error: %s.\n", mysqli_stmt_error($stmt));
	    }

}


?>