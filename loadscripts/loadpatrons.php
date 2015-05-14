<?php

//PULLS PATRON DATA OUT OF AN XML FILE
//PROVIDED BY OUR IDM TEAM
//AND INSERTS THEM INTO OLE TABLES
//**NOTE: THIS SCRIPT INSERTS ROWS DIRECTLY
//INTO THE OLE DB -- THIS SCRIPT GENERATES
//THE UNIQUE KEYS FOR EACH ROW INSTEAD OF
//USING THE '_S' TABLE PROVIDED BY OLE
//YOU HAVE TO SYNC UP THE _S TABLE(s) AFTER YOU
//RUN THIS SCRIPT OR OLE WILL THINK
//THE STARTING POINT FOR THE INCREMENTAL
//KEY IS LESS THAN IT IS (MEANING - IT COULD TRY TO
//USE KEYS YOU'VE ALREADY INSERTED)
//TODO: USE THE _S TABLES FOR THIS SCRIPT!
//EXAMPLE:
//KRIM_ENTITY_T ---> KRIM_ENTITY_ID_S


//SET UP LOGGING
require('KLogger.php');
$log = new KLogger ( "insertpatronsintodb.txt" , KLogger::DEBUG );

//lookup table for patron type
$patronTypes = array (
		"FACULTY"=> "1",
		"STAFF"=> "3",
		"RETIREE"=>"2",
		"GRADSTUDNT"=>"6",
		"UNDERGRAD"=>"7",
		"P-GRADSTUD"=>"6",
		"SPOUSE"=>"4",
		"P-UGRAD"=>"7",
		"RESEARCH"=>"5",
		"VISITOR"=>"9",
		"ADJUNCT"=>"1"
);


//SET UP EXCEPTION HANDLING
//http://stackoverflow.com/questions/5373780/how-to-catch-this-error-notice-undefined-offset-0
function exceptions_error_handler($severity, $message, $filename, $lineno) {
  global $log;
  global $sql;
  if (error_reporting() == 0) {
    return;
  }
  if (error_reporting() & $severity) {
    echo $message;
    $log->LogError($message . "--------" . $lineno);
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



//CONTAINS THE DB USERID AND PASSWORD VARIABLES
//INITIALIZATION
require('proddbinfo.php');


$olecon=mysqli_connect("localhost","$userid","$password","OLE");
// CHECK DATABASE CONNECTION
if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  die;
}


//MAPPING OUR IDM NOTE NAMES TO OLE NOTE TYPES
$notearray = array("collegeC"=>"College Code","confiden"=>"Confidential","deptName"=>"Department Name",
           "eduPerso"=>"In Common Role","login"=>"Login Id","majorCod"=>"Major Code",
           "majorDes"=>"Major Description","nickname"=>"nickname","role"=>"role","studentL"=>"student leave","studentS"=>"student status","udcid"=>"udcid","sirsi"=>"sirsi note");


//GETTING ALL OF THE NOTE TYPE IDS FROM OLE
$query = "select * from ole_ptrn_nte_typ_t";
$result = mysqli_query($olecon,$query);
while ($row = mysqli_fetch_array($result)) {
		$notearray[$row['OLE_PTRN_NTE_TYPE_CD']] = $row['OLE_PTRN_NTE_TYP_ID'];
}

//GETTING ALL OF THE BORROWER TYPE IDS FROM OLE
$query = "select * from OLE_DLVR_BORR_TYP_T";
$result = mysqli_query($olecon,$query);
while ($row = mysqli_fetch_array($result)) {
		$patrontypearray[$row['DLVR_BORR_TYP_CD']] = $row['DLVR_BORR_TYP_ID'];
}


//var_dump($patrontypearray);


$counter = 0;

//FILE OF PATRONS FROM THE IDM TEAM
$patrons=simplexml_load_file("data/patrons.xml");

$idcounter = 310000;
foreach ($patrons as $patron) {

 	   $counter++;
 	   $idcounter++;
 	   $barcode = $patron->barcode;
 	   //does barcode exist already?
 	   $patronquery = "select * from OLE_PTRN_T where BARCODE = '$barcode'";
	   $rslt = mysqli_query($olecon,$patronquery);
	   $size = ($rslt->num_rows);

	   //MAKE SURE THE PATRON DOES NOT ALREADY EXIST
 	   if ($size < 1) {
		 	   $patronid = $idcounter;
		 	   echo $patronid . "\n\n";

		 	   $firstname = $patron->name->first;
		 	   $lastname = $patron->name->surname;
		 	   $prefix = $patron->name->title;

		 	   $borrowertype = $patron->borrowerType;
		 	   $borrowertypecode = $patrontypearray["$borrowertype"];
		 	   //if ($borrowertype == "RETIREE") $borrowertypecode = "101";
		 	   $middle = "";

		 	   //EMAIL ADDRESS
		 	   $emailxml = $patron->emailAddresses[0]->emailAddress;
		 	   $emailaddress = $emailxml->emailAddress;


		 	   $telephoneNumberXml = $patron->telephoneNumbers[0]->telephoneNumber;
		 	   $number = $telephoneNumberXml->telephoneNumber;
		 	   $type = $telephoneNumberXml->telephoneNumberType;
		 	   if ($type == "HO") $type = "HM";
		 	   else $type = "OTH";


			  
			 
			   $stmt = mysqli_stmt_init($olecon);

			   $obj_id = v4();
			   //#1) $query = "INSERT INTO KRIM_ENTITY_T...
			   mysqli_stmt_prepare($stmt, "INSERT INTO KRIM_ENTITY_T (ENTITY_ID,ACTV_IND,VER_NBR,OBJ_ID) VALUES (?,'Y',1,?)");
			   mysqli_stmt_bind_param($stmt, "ss", $patronid,$obj_id);
			   mysqli_stmt_execute($stmt);
			   //echo "$patronid \n";
			   $log->LogError("Error1: %s.\n", mysqli_stmt_error($stmt));

			  
			  $obj_id = v4();
			  //#2) $query = "INSERT INTO KRIM_ENTITY_ENT_TYP_T ...
			  mysqli_stmt_prepare($stmt, "INSERT INTO KRIM_ENTITY_ENT_TYP_T (ENTITY_ID,ENT_TYP_CD,ACTV_IND,VER_NBR,OBJ_ID) VALUES (?,'PERSON','N',1,?)");
			  mysqli_stmt_bind_param($stmt, "ss", $patronid,$obj_id);
			  mysqli_stmt_execute($stmt);
			  $log->LogError("Error2: %s.\n", mysqli_stmt_error($stmt));

			  
			  $obj_id = v4();
			  //#3) $query = "INSERT INTO KRIM_ENTITY_NM_T...
			  mysqli_stmt_prepare($stmt, "INSERT INTO KRIM_ENTITY_NM_T (ENTITY_NM_ID,ENTITY_ID,NM_TYP_CD,FIRST_NM,MIDDLE_NM,LAST_NM,PREFIX_NM,TITLE_NM,SUFFIX_NM,NOTE_MSG,NM_CHNG_DT,DFLT_IND,ACTV_IND,VER_NBR,OBJ_ID) VALUES (?,?,null,?,?,?,?,null,'',null,null,'Y','Y',1, ?)");
			  mysqli_stmt_bind_param($stmt, "sssssss", $patronid,$patronid,$firstname,$middle,$lastname,$prefix,$obj_id);
			  mysqli_stmt_execute($stmt);
			  $log->LogError("Error3: %s.\n", mysqli_stmt_error($stmt));


			  $obj_id = v4();
			  //#4) $query = "INSERT INTO KRIM_ENTITY_EMAIL_T ...
			  //*temp -- put in our test email as the active email for testing
			  mysqli_stmt_prepare($stmt, "INSERT INTO KRIM_ENTITY_EMAIL_T (ENTITY_EMAIL_ID,ENTITY_ID,ENT_TYP_CD,EMAIL_TYP_CD,EMAIL_ADDR,DFLT_IND,ACTV_IND,VER_NBR,OBJ_ID) VALUES (?,?,'PERSON','OTH','ourtestemail@lehigh.edu','Y','Y',1,?)");
			  mysqli_stmt_bind_param($stmt, "sss", $patronid,$patronid,$obj_id);
			  mysqli_stmt_execute($stmt);
			  $log->LogError("Error4: %s.\n", mysqli_stmt_error($stmt));

			  //*real email as inactive for later
			  $obj_id = v4();
			  mysqli_stmt_prepare($stmt, "INSERT INTO KRIM_ENTITY_EMAIL_T (ENTITY_EMAIL_ID,ENTITY_ID,ENT_TYP_CD,EMAIL_TYP_CD,EMAIL_ADDR,DFLT_IND,ACTV_IND,VER_NBR,OBJ_ID) VALUES (?,?,'PERSON','CMP',?,'N','N',1,?)");
			  $emailid = $patronid . '01';
			  mysqli_stmt_bind_param($stmt, "ssss", $emailid,$patronid,$emailaddress,$obj_id);
			  mysqli_stmt_execute($stmt);
			  $log->LogError("Error4a: %s.\n", mysqli_stmt_error($stmt));


			  $obj_id = v4();
			  //#6) $query = "INSERT INTO OLE_PTRN_T (OLE_PTRN_ID,BARCODE,BORR_TYP,ACTV_IND,GENERAL_BLOCK,PAGING_PRIVILEGE,COURTESY_NOTICE,DELIVERY_PRIVILEGE,EXPIRATION_DATE,ACTIVATION_DATE,GENERAL_BLOCK_NT,OLE_SRC,OLE_STAT_CAT,PHOTOGRAPH,OBJ_ID,VER_NBR) VALUES ('99993','8675312','7','Y','N','Y','Y','Y','2017-02-04','2014-02-04','','1','1',null,'24bc3da4-400e-4afb-a57b-f984819f4722',1)";
			  mysqli_stmt_prepare($stmt, "INSERT INTO OLE_PTRN_T (OLE_PTRN_ID,BARCODE,BORR_TYP,ACTV_IND,GENERAL_BLOCK,PAGING_PRIVILEGE,COURTESY_NOTICE,DELIVERY_PRIVILEGE,EXPIRATION_DATE,ACTIVATION_DATE,GENERAL_BLOCK_NT,OLE_SRC,OLE_STAT_CAT,PHOTOGRAPH,OBJ_ID,VER_NBR) VALUES (?,?,?,'Y','N','N','Y','N',null,'2014-07-01','','11',null,null,?,1)");
			  mysqli_stmt_bind_param($stmt, "ssss", $patronid,$barcode,$borrowertypecode,$obj_id);
			  mysqli_stmt_execute($stmt);
			  $log->LogError("Error6: %s.\n", mysqli_stmt_error($stmt));


			  $addressxml = $patron->postalAddresses;
			  //loop for each address:
			  $addresscount = 0;
			  foreach ($addressxml->children() as $addressxml) {
			  	  $addresscount++;
			  	  $addressid = $patronid . $addresscount;
			  	  $streetaddress = $addressxml->addressLine[0];
			  	  $streetaddresstwo = $addressxml->addressLine[1];
			  	  $city = $addressxml->city;
		 	   	  $state = $addressxml->stateProvince;
		 	      $zip = $addressxml->postalCode;
		 	      $postalAddressType = $addressxml->postalAddressType;
		 	  	  if ($postalAddressType == "HO") $postalAddressType = "HM";
		 	      else $postalAddressType = "OTH";

		 	      //KRIM_ENTITY_T and OLE_DLVR_ADD_T have to be in-sync
		 	      //caused 'address not verified error'
		 	      //fix update krim_entity_addr_t set ENTITY_ADDR_ID = '300010' where ENTITY_ID = '300010'
				  $obj_id = v4();
				  //#5) $query = "INSERT INTO KRIM_ENTITY_ADDR_T...
				  mysqli_stmt_prepare($stmt, "INSERT INTO KRIM_ENTITY_ADDR_T (ENTITY_ADDR_ID,ENTITY_ID,ADDR_TYP_CD,ENT_TYP_CD,CITY,STATE_PVC_CD,postal_cd,POSTAL_CNTRY_CD,ATTN_LINE,ADDR_LINE_1,ADDR_LINE_2,ADDR_LINE_3,ADDR_FMT,MOD_DT,VALID_DT,VALID_IND,NOTE_MSG,DFLT_IND,ACTV_IND,VER_NBR,OBJ_ID) VALUES (?,?,?,'PERSON',?,?,?,'US',null,?,?,'',null,null,null,'N',null,'Y','Y',1,?)");
				  mysqli_stmt_bind_param($stmt, "sssssssss", $addressid,$patronid,$postalAddressType,$city,$state,$zip,$streetaddress,$streetaddresstwo,$obj_id);
				  mysqli_stmt_execute($stmt);
				  $log->LogError("Error5: %s.\n", mysqli_stmt_error($stmt));


				  $obj_id = v4();
				  //#7) $query = "INSERT INTO OLE_DLVR_ADD_T ...
				  mysqli_stmt_prepare($stmt, "INSERT INTO OLE_DLVR_ADD_T (DLVR_ADD_ID,OLE_PTRN_ID,ENTITY_ADDR_ID,OLE_ADDR_SRC,DLVR_PTRN_ADD_VER,ADD_VALID_FROM,ADD_VALID_TO,OBJ_ID,VER_NBR) VALUES (?,?,?,'1','Y',null,null,?,1)");
				  mysqli_stmt_bind_param($stmt, "ssss", $addressid,$patronid,$patronid,$obj_id);
				  mysqli_stmt_execute($stmt);	
				  $log->LogError("Error7: %s.\n", mysqli_stmt_error($stmt));
			   }

			  


			  if ($number != null && $number != "") {
				  $obj_id = v4();
				  //#8) $query = INSERT INTO KRIM_ENTITY_PHONE_T...
				  mysqli_stmt_prepare($stmt, "INSERT INTO KRIM_ENTITY_PHONE_T (ENTITY_PHONE_ID,OBJ_ID,VER_NBR,ENTITY_ID,ENT_TYP_CD,PHONE_TYP_CD,PHONE_NBR,DFLT_IND,ACTV_IND) VALUES(?,?,1,?,'PERSON',?,?,'Y','Y')");
				  mysqli_stmt_bind_param($stmt, "sssss", $patronid,$obj_id,$patronid,$type,$number);
				  mysqli_stmt_execute($stmt);	
				  $log->LogError("Error8: %s.\n", mysqli_stmt_error($stmt));
			  }


			  //NOTES
		 	  $x = 0;
		 	  $thenotes = $patron->notes->children();
		 	  //var_dump($thenotes);
		 	  foreach ($thenotes as $n) {
		 	   	  //echo $n->noteType;
		 	      $type = $n->noteType;
		 	      $notetext = $n->note;
		 	      if ($notetext != "") {
		 	   	  //lookup id for note type
			 	   	  if (strlen($type) > 8) $type = substr($type,0,8);
			 	   	  $notetype = $notearray["$type"];
			 	      //echo "$x . --- $type----" . $notetype;
			 	   	  $x++;
			 	   	  $obj_id = v4();
			 	   	  mysqli_stmt_prepare($stmt, "INSERT INTO OLE_PTRN_NTE_T (OLE_PTRN_NTE_ID,OBJ_ID,VER_NBR,
			 	   	  	OLE_PTRN_ID,OLE_PTRN_NTE_TYP_ID,OLE_PTRN_NTE_TXT,ACTV_IND) 
			 	   	  VALUES (?,?,1,?,?,?,'Y')");
			 	   	  mysqli_stmt_bind_param($stmt, "sssss", $obj_id,$obj_id,$patronid,$notetype,$notetext);
			 	   	  mysqli_stmt_execute($stmt);	
				  	  $log->LogError("Error: %s.\n", mysqli_stmt_error($stmt));
				  }
		 	  }
			  echo "\n";
			  echo "COUNTER: " . $counter;
			  echo "\n";
		}
}

?>