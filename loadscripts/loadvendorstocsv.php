<?php

//PULLS VENDOR  DATA OUT OF
//THE 'vendors' TABLE IN THE SQLITE STAGING DB
//PREVIOUSLY POPULATED WITH stagevendors.php
//AND WRITES IT CSV FILES.
//AT THE TIME OF MIGRATION
//OLE's PROCESS WOULD INGEST THE CSV FILES
//AND INSERT THE DATA INTO THE PROPER
//OLE TABLES

//SET UP LOGGING
require('KLogger.php');
$log = new KLogger ( "log_loadvendors.txt" , KLogger::DEBUG );




//SET UP EXCEPTION HANDLING
//http://stackoverflow.com/questions/5373780/how-to-catch-this-error-notice-undefined-offset-0
function exceptions_error_handler($severity, $message, $filename, $lineno) {
  global $sql;
  global $log;
  if (error_reporting() == 0) {
    return;
  }
  if (error_reporting() & $severity) {
 	$log->LogError($message . "-- LINE NUMBER:" . $lineno . "---\n\n");
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



//OUTPUT FILES:
$csvdir='/usr/local/oledev/buildscripts/ole-inst';
$vendor_hdr_name=$csvdir.'/purchasing/PUR_VNDR_HDR_T.csv';
$vendor_dtl_name=$csvdir.'/purchasing/PUR_VNDR_DTL_T.csv';
$vendor_alias_name=$csvdir.'/purchasing/PUR_VNDR_ALIAS_T.csv';
//new
$vendor_phone_number_name=$csvdir.'/purchasing/PUR_VNDR_PHN_NBR_T.csv';
$vendor_address_name=$csvdir.'/purchasing/PUR_VNDR_ADDR_T.csv';
$vendor_contact_name=$csvdir.'/purchasing/PUR_VNDR_CNTCT_T.csv';

$vendor_hdr=fopen($vendor_hdr_name, 'r');
$vendor_hdr_headers=fgets($vendor_hdr);
$vendor_hdr_headers=rtrim($vendor_hdr_headers);
fclose($vendor_hdr);

$vendor_dtl=fopen($vendor_dtl_name, 'r');
$vendor_dtl_headers=fgets($vendor_dtl);
$vendor_dtl_headers=rtrim($vendor_dtl_headers);
fclose($vendor_dtl);

$vendor_alias=fopen($vendor_alias_name, 'r');
$vendor_alias_headers=fgets($vendor_alias);
$vendor_alias_headers=rtrim($vendor_alias_headers);
fclose($vendor_alias);

//new
$vendor_phone=fopen($vendor_phone_number_name, 'r');
$vendor_phone_headers=fgets($vendor_phone);
$vendor_phone_headers=rtrim($vendor_phone_headers);
fclose($vendor_phone);

$vendor_address=fopen($vendor_address_name, 'r');
$vendor_address_headers=fgets($vendor_address);
$vendor_address_headers=rtrim($vendor_address_headers);
fclose($vendor_address);

$vendor_contact=fopen($vendor_contact_name, 'r');
$vendor_contact_headers=fgets($vendor_contact);
$vendor_contact_headers=rtrim($vendor_contact_headers);
fclose($vendor_contact);


//CONNECT TO THE SQLITE DB:
$migrationdbdir = getenv("migrationdbdir");
$db = new SQLite3($migrationdbdir.'/olemigration.sqlite');
echo 'Connected to the database.';

$query = "select * from vendors";
$result = $db->query($query);

//NEEDED A UNIQUE ID IF INSERTING
//NOT SURE IF OLE PICKS UP 
//THESE IDS FROM THE CSV FILE OR
//GENERATES ITS OWN?
$uniqueid = 2700;
$counter = 0;

$vendor_hdr=fopen($vendor_hdr_name, 'w') or die("Couldn't open output file $vendor_hdr_name");
fwrite($vendor_hdr, "$vendor_hdr_headers\n");
$vendor_hdr_cols = split(",", $vendor_hdr_headers);

$vendor_dtl=fopen($vendor_dtl_name, 'w') or die("Couldn't open output file $vendor_dtl_name");
fwrite($vendor_dtl, "$vendor_dtl_headers\n");
$vendor_dtl_cols = split(",", $vendor_dtl_headers);

$vendor_alias=fopen($vendor_alias_name, 'w') or die("Couldn't open output file $vendor_alias_name");
fwrite($vendor_alias, "$vendor_alias_headers\n");
$vendor_alias_cols = split(",", $vendor_alias_headers);

//new
$vendor_phone=fopen($vendor_phone_number_name, 'w') or die("Couldn't open output file $vendor_phone_number_name");
fwrite($vendor_phone, "$vendor_phone_headers\n");
$vendor_phone_cols = split(",", $vendor_phone_headers);

$vendor_address=fopen($vendor_address_name, 'w') or die("Couldn't open output file $vendor_address_name");
fwrite($vendor_address, "$vendor_address_headers\n");
$vendor_address_cols = split(",", $vendor_address_headers);

$vendor_contact=fopen($vendor_contact_name, 'w') or die("Couldn't open output file $vendor_contact_name");
fwrite($vendor_contact, "$vendor_address_headers\n");
$vendor_contact_cols = split(",", $vendor_contact_headers);


while ($row = $result->fetchArray(SQLITE3_ASSOC)) { 

  $vendorid = $row['VEND_ID'];
  $vendorName = substr($row['VEND_NAME'], 0, 44);
 
  $obj_id = v4();
  
  $colvals = array();
           
  $colvals["VNDR_HDR_GNRTD_ID"] = $uniqueid;
  $colvals["OBJ_ID"] = $obj_id;
  $colvals["VER_NBR"] = 1;
  $colvals["VNDR_TYP_CD"] = "PO";
  $colvals["VNDR_DEBRD_IND"] = "N";
  $colvals["VNDR_FRGN_IND"] = "N";
  $colvals["VNDR_OWNR_CD"] = "CP";
  $line = "";
  foreach ( $vendor_hdr_cols as $col ) {
    if ( strlen($line) > 0 ) {
      $line .= ",";
    }
    if ( isset($colvals["$col"]) ) {
      $line .= "\"" . $colvals["$col"] . "\"";
    } else {
      $line .= "null";
    }
  }
  fwrite($vendor_hdr, "$line\n");

  $obj_id = v4();

  unset($colvals);
  $colvals = array();
  $colvals["VNDR_HDR_GNRTD_ID"] = $uniqueid;
  $colvals["VNDR_DTL_ASND_ID"] = 0;
  $colvals["OBJ_ID"] = $obj_id;
  $colvals["VER_NBR"] = 1;
  $colvals["VNDR_PARENT_IND"] = "Y";
  $colvals["VNDR_NM"] = $vendorName;
  $colvals["DOBJ_MAINT_CD_ACTV_IND"] = "Y";
  $colvals["VNDR_1ST_LST_NM_IND"] = "N";
  $colvals["COLLECT_TAX_IND"] = "N";
  $colvals["OLE_CURR_TYP_ID"] = 1;
  $colvals["VNDR_PMT_MTHD_ID"] = 1;
  $colvals["VNDR_LINK_ID"] = "UNKNOWN";
  $line = "";
  foreach ( $vendor_dtl_cols as $col ) {
    if ( strlen($line) > 0 ) {
      $line .= ",";
    }
    if ( isset($colvals["$col"]) ) {
      $line .= "\"" . $colvals["$col"] . "\"";
    } else {
      $line .= "null";
    }
  }
  fwrite($vendor_dtl, "$line\n");
  
  $obj_id = v4();
  unset($colvals);
  $colvals = array();
  $colvals["VNDR_ALIAS_NM"] = $vendorid;
  $colvals["VNDR_HDR_GNRTD_ID"] = $uniqueid;
  $colvals["VNDR_DTL_ASND_ID"] = 0;
  $colvals["OBJ_ID"] = $obj_id;
  $colvals["VER_NBR"] = 1;
  $colvals["DOBJ_MAINT_CD_ACTV_IND"] = "Y";
  $colvals["OLE_ALIAS_TYP_ID"] = 1;
  $colvals["OLE_PUR_VNDR_ALIAS_ID"] = 8000;
  $line = "";
  foreach ( $vendor_alias_cols as $col ) {
    if ( strlen($line) > 0 ) {
      $line .= ",";
    }
    if ( isset($colvals["$col"]) ) {
      $line .= "\"" . $colvals["$col"] . "\"";
    } else {
      $line .= "null";
    }
  }
  fwrite($vendor_alias, "$line\n");
  //NEW -- VENDOR ADDRESS
  $obj_id = v4();
  unset($colvals);
  $vendAddressAttn = $row['VEND_ADDR1_ATTN'];
  $vendAddressLineOne = $row['VEND_ADDR1_STREET'];
  $vendAddressLineTwo = $row['VEND_ADDR1_LINE'];
  $vendCityState = $row['VEND_ADDR1_CITYSTATE'];
  $zip = $row['VEND_ADDR1_ZIP'];
  $email = $row['VEND_ADDR1_EMAIL'];
  //$fax = $row['VEND_ADDR1_FAX']; REMOVED FOR NOW
  $note = $row['NOTE'];
  //replace any double quotes in the comment with single
  if ($note != null) $note = str_replace('"',"'",$note);
  //NOTE FIELD IS ONLY 400 CHARS.  WILL THIS WORK FOR US?
  if ($note != null && strlen($note) > 298) {
      $note = substr($note,0,390) .'...';
  }

  $citystatearray = explode(",",$vendCityState);
  if (sizeof($citystatearray) > 1) { 
    $city = $citystatearray[0];
    $state = $citystatearray[1];
  }
  else {
    $city = $vendCityState;
  }
  $colvals["VNDR_CTY_NM"] = $city;

  if ($state != null && strlen(trim($state)) == 2) {
    $colvals['VNDR_ST_CD'] = trim($state);
  }

  $colvals["VNDR_ADDR_TYP_CD"] = "PO"; //HARCODED ADDRESS TYPE AS PO -- OPEN QUESTION ON Q&A DOC
  $colvals["VNDR_ADDR_GNRTD_ID"] = $uniqueid;
  $colvals["VNDR_DTL_ASND_ID"] = "0";
  $colvals["VNDR_HDR_GNRTD_ID"] = $uniqueid;
  $colvals["DOBJ_MAINT_CD_ACTV_IND"] = "Y";
  $colvals["VNDR_DFLT_ADDR_IND"] = "Y";
  $colvals["OBJ_ID"] = $obj_id;
  $colvals["VER_NBR"] = 1;
  $colvals["VNDR_LN1_ADDR"] = substr($vendAddressLineOne, 0, 44);
  $colvals["VNDR_LN2_ADDR"] = substr($vendAddressLineTwo, 0, 44);
  $colvals["VNDR_CTY_NM"] = $city;
  $colvals["VNDR_ATTN_NM"] = substr($vendAddressAttn, 0, 44);
  $colvals["VNDR_CNTRY_CD"] = 'US';
  $colvals["VNDR_ZIP_CD"] = $zip;

  //TODO: HARDCODING COUNTRY -- CAN WE LEAVE THIS AS IS
  //OR DO WE NEED DETAILED LOGIC?
  //HOW MANY NON-US VENDORS.  LISTED IN Q&A DOCUMENT
  $colvals["NDR_CNTRY_CD"] = 'US';
  $colvals["VNDR_ADDR_EMAIL_ADDR"] = $email;
  $colvals["VNDR_ACCOUNT_NBR"] = $uniqueid;
  $colvals["VNDR_FIN_COA_CD"] = "LU";  //-- not sure why this is in the address table.  we have only one coa -- ok to hardcode?
  $colvals["OLE_VNDR_ADDR_NT"] = $note; //400 char limit


 $line = "";
 foreach ($vendor_address_cols as $col ) {
    if ( strlen($line) > 0 ) {
      $line .= ",";
    }
    if ( isset($colvals["$col"]) ) {
      $line .= "\"" . $colvals["$col"] . "\"";
    } else {
      $line .= "null";
    }
 }
 fwrite($vendor_address, "$line\n");
  
  $uniqueid++;
  $counter++;

}



?>