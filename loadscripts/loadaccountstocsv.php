<?php

//PULLS ACCOUNT (AKA FUND) DATA OUT OF 
//THE 'funds' TABLE IN THE SQLITE STAGING DB
//PREVIOUSLY POPULATED WITH stageaccounts.php
//AND WRITES IT TO CSV FILES.
//AT THE TIME OF MIGRATION
//OLE's PROCESS WOULD INGEST THE CSV FILES
//AND INSERT THE DATA INTO THE PROPER
//OLE TABLES


//SET UP LOGGING 
require('KLogger.php');
$log = new KLogger ( "loadaccounts.txt" , KLogger::DEBUG );


//SET UP EXCEPTION HANDLING
//http://stackoverflow.com/questions/5373780/how-to-catch-this-error-notice-undefined-offset-0
function exceptions_error_handler($severity, $message, $filename, $lineno) {
  global $log;
  if (error_reporting() == 0) {
    return;
  }
  if (error_reporting() & $severity) {
 	$log->LogError($severity . "--" . $message . " on line: " . $lineno);
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



function endswith($string, $test) {
    $strlen = strlen($string);
    $testlen = strlen($test);
    if ($testlen > $strlen) return false;
    return substr_compare($string, $test, -$testlen) === 0;
}



//SETUP NEW ACCOUNT NAMES:
//DURING OUR MIGRATION THERE WERE 
//SEVERAL FUNDS NAMES THAT HAD TO BE
//CHANGED
//THE ARRAY BELOW WAS USED FOR THE
//TRANSLATION.
//(THIS IS A EDITED VERSION OF THE ARRAY)
$newnames = array("AV-STRM"=>"Streaming rights for videos");



//HARDCODING SOME VALUES:
$chartCode = "LU";
$accountRestrictionStatusCode = "U";
$createDate = "2014-01-01";
$ACCT_SF_CD = "N";  
$inactiveIndicator = "N";
$version = 1;
$SUB_FUND_GRP_CD = "GENFND";
$ACCT_FSC_OFC_UID = "olequickstart"; //for now
$ORG_CD = 'LIB';
$ACCT_ICR_TYP_CD = "10";
$accountTypeCode = "NA";
$higherEdFunctionCode = "NA";
$BDGT_REC_LVL_CD = "A";

//OUTPUT FILES:
$csvdir='/usr/local/oledev/buildscripts/ole-inst';
$thisdir = '/usr/local/oledev/buildscripts/loadscripts';
$acctsfname=$csvdir.'/chart-of-accounts/CA_ACCOUNT_T.csv';
$translationfname = $thisdir . '/fundtranslation.csv';
$commentsfname = $csvdir.'/chart-of-accounts/CA_ACCT_GDLNPRPS_T.csv';



//READ IN THE TRANSLATION FILE -- TRANSLATING FUND CODE
//ADDING NEW FUND CODES AND NOT MIGRATING SOME OF THE CODES:
$translationarray = array();
$fundsused = array();
$file = fopen($translationfname, 'r')  or die ("Couldn't open output file $translationfname");
while (($line = fgetcsv($file)) !== FALSE) {
  $translationarray[$line[0]]=$line[1];
}
fclose($file);



$acctsfile=fopen($acctsfname, 'r');
$colheaders=fgets($acctsfile);
$colheaders=rtrim($colheaders);
fclose($acctsfile);


$commentsfile=fopen($commentsfname, 'r');
$colheaderscomments=fgets($commentsfile);
$colheaderscomments=rtrim($colheaderscomments);
fclose($commentsfile);


//FOLDER FOR SQLITE DB AND TEXT FILES:
$migrationdbdir = getenv("migrationdbdir");

//CONNECT TO THE SQLITE DB:
$db = new SQLite3($migrationdbdir.'/olemigration.sqlite');
echo 'Connected to the database.';

//GET ALL ACCOUNTS (AKA FUNDS) FROM
//THE SQLITE DB (LOADED WITH stageaccounts.php)
$query = "select * from funds";
$result = $db->query($query);

//NOT SURE IF THE OLE INGEST
//WILL USE THIS UNIQUE ID - FROM THE CSV FILE
//OR GENERATE ITS OWN?
$uniqueid = 245;
$counter = 0;


// Blow away whatever was already in the accounts .csv file,
// but put back the column headers
$acctsfile=fopen($acctsfname, 'w') or die ("Couldn't open output file $acctsfname");
fwrite($acctsfile, "$colheaders\n");
$cols = split(",", $colheaders);

$commentsfile=fopen($commentsfname, 'w') or die ("Couldn't open output file $commentsfile");
fwrite($commentsfile, "$colheaderscomments\n");
$commentcols = split(",", $colheaderscomments);
while ($row = $result->fetchArray(SQLITE3_ASSOC)) { 

		$obj_id = v4();	   
		$fundId = $row['FUND_ID'];
		//MAX LENGTH ON ACCOUNT NAME IS 40 CHARS:
		$fundName = substr($row['FUND_NAME'], 0, 39);
		$accountNumber = $row['FUND_ACCOUNT'];

		//clean up comments
		$comments = $row['COMMENT'];
		$notes = $row['NOTE'];
		$comments = str_replace('$<cycle>',' ',$comments);
		$comments = str_replace('$<user_id>',' ',$comments);
		$comments = str_replace('FUND_XINFO_END.',' ',$comments);
		$notes = str_replace('FUND_XINFO_END.',' ',$notes);
		if ($notes == null || $notes=="") $notes = "N/A";
		//MAX LENGTH ON COMMENTS FIELDS IS 400
		$comments = substr($comments, 0, 399);
		$notes = substr($notes, 0, 399);

		//if fund name ends with the word book OR periodical-- remove it -- cleaning up / consolidating our old accounts
		$fundName = trim($fundName);
		if (endswith($fundName,"books")) $fundName = preg_replace('/books$/', '', $fundName);
		if (endswith($fundName,"Books")) $fundName = preg_replace('/Books$/', '', $fundName);
		if (endswith($fundName,"Boo")) $fundName = preg_replace('/Boo$/', '', $fundName);
		if (endswith($fundName,"periodicals")) $fundName = preg_replace('/periodicals$/', '', $fundName);
		//the field only fits 7 chars.  none of our fund codes are longer -- but trimming as a precaution
		if (strlen($fundId) > 7) $fundId = substr($fundId,0,7);
		//translate -- old fund code to new fund code:
		
		//SAMPLE ROWS FROM THE fundtranslation.csv file
		//PTHE,DELETE
		//BEBK,EBK
		//OLD FUND NAME,NEW FUND NAME
		//OR
		//OLD FUND NAME, 'DELETE' (NOT MIGRATING)
		$translatedfundid = $translationarray[$fundId];
		if (strlen($translatedfundid) > 7) $translatedfundid = substr($translatedfundid,0,7);
                
		// We only want one account for each unique fund ID
		// If there are multiple accounts with the same fund ID, 
		// then we will need multiple object codes for those other accounts
		if ( !isset($existing_funds[$translatedfundid] ) ) {
			//INSERT IF THE 'NEW' FUND CODE EXISTS AND IS NOT EQUAL TO 'DELETE'
			if ($translatedfundid != "" && $translatedfundid != 'DELETE') {
          		  array_push($fundsused, $translatedfundid);
				  $existing_funds[$translatedfundid] = 		  
				  $colvals["FIN_COA_CD"] = $chartCode;
				  $colvals["ACCOUNT_NBR"] = $translatedfundid;
				  $colvals["OBJ_ID"] = $obj_id;
				  $colvals["VER_NBR"] = $version;
				  $colvals["ACCOUNT_NM"] = $fundName;
				  $colvals["ACCT_SF_CD"] = $ACCT_SF_CD;
				  $colvals["ACCT_CLOSED_IND"] = $inactiveIndicator;
				  $colvals["OLE_UNIV_ACCT_NBR"] = $accountNumber;
				  $colvals["ACCT_CREATE_DT"] = $createDate;
				  $colvals["ACCT_RSTRC_STAT_CD"] = $accountRestrictionStatusCode;
				  $colvals["ORG_CD"] = $ORG_CD;
				  $colvals["ACCT_FSC_OFC_UID"] = $ACCT_FSC_OFC_UID;
				  //I'M NOT ENTIRELY SURE THESE VALUES ARE NEEDED
				  $colvals["SUB_FUND_GRP_CD"] = $SUB_FUND_GRP_CD;
				  $colvals["ACCT_TYP_CD"] = $accountTypeCode;
				  $colvals["FIN_HGH_ED_FUNC_CD"] = $higherEdFunctionCode;
				  $colvals["CONT_FIN_COA_CD"] = $chartCode;
				  $colvals["CONT_ACCOUNT_NBR"] = $translatedfundid;
				  $colvals["ENDOW_FIN_COA_CD"] = $chartCode;
				  $colvals["ENDOW_ACCOUNT_NBR"] = $translatedfundid;
				  $colvals["INCOME_FIN_COA_CD"] = $chartCode;
		          $colvals["INCOME_ACCOUNT_NBR"] = $translatedfundid;
		          $colvals["ACCT_ICR_TYP_CD"] = $ACCT_ICR_TYP_CD;
		          $colvals["ICR_FIN_COA_CD"] = $chartCode;
		          $colvals["ICR_ACCOUNT_NBR"] = $translatedfundid;
		          $colvals["BDGT_REC_LVL_CD"] = $BDGT_REC_LVL_CD;
				  $line = "";
				  foreach ( $cols as $col ) {
				    if ( strlen($line) > 0 ) {
				      $line .= ",";
				    }
				    if ( isset($colvals["$col"]) ) {
				      $line .= "\"" . $colvals["$col"] . "\"";
				    } else {
				      $line .= "NULL";
				    }
				  }
				  fwrite($acctsfile, "$line\n");


				  //NOTES AND COMMENTS
				  $valuearray = array();
		  		  $valuearray["ACCT_EXP_GDLN_TXT"] = $notes;
		          $valuearray["ACCT_INC_GDLN_TXT"] = "";
		          $valuearray["ACCT_PURPOSE_TXT"] = $comments;
		          $valuearray["FIN_COA_CD"] = $chartCode;
				  $valuearray["ACCOUNT_NBR"] = $translatedfundid;
				  $valuearray["OBJ_ID"] = $obj_id;
				  $valuearray["VER_NBR"] = $version;
		          $line = "";
				  foreach ( $commentcols as $col ) {
				  	//echo $col;
				    if ( strlen($line) > 0 ) {
				      $line .= ",";
				    }
				    if ( isset($valuearray["$col"]) ) {
				      $line .= "\"" . $valuearray["$col"] . "\"";
				    } else {
				      $line .= "NULL";
				    }
				  }
				  fwrite($commentsfile, "$line\n");
			 }
		} 
		else {
		  echo "Skipping duplicate account with fund ID $fundId and name $fundName\n";
		}

}

//LASTLY -- INSERT ANY NEW FUND CODES THAT DID NOT EXIST IN SIRSI:
//TODO: THIS SHOULD BE CONSOLIDATED (INSTEAD OF REPEATING CODE ABOVE)
foreach ($translationarray as $orig => $translatedfundid) {
	//trim to 7
	$obj_id = v4();
	if (strlen($translatedfundid) > 7) $translatedfundid = substr($translatedfundid,0,7);
	if (($translatedfundid != "DELETE") && (!in_array($translatedfundid, $fundsused))) {
		  //TODO: rewrite -- to remove duplicate code
		  array_push($fundsused, $translatedfundid);
		  $existing_funds[$fundId] = 		  
		  $colvals["FIN_COA_CD"] = $chartCode;
		  $colvals["ACCOUNT_NBR"] = $translatedfundid;
		  $colvals["OBJ_ID"] = $obj_id;
		  $colvals["VER_NBR"] = $version;
		  $colvals["ACCOUNT_NM"] = $newnames[$translatedfundid];
		  if ($newnames[$translatedfundid] == null) echo "name was null: " . $translatedfundid;
		  $colvals["ACCT_SF_CD"] = $ACCT_SF_CD;
		  $colvals["ACCT_CLOSED_IND"] = $inactiveIndicator;
		  $colvals["OLE_UNIV_ACCT_NBR"] = '';
		  $colvals["ACCT_CREATE_DT"] = $createDate;
		  $colvals["ACCT_RSTRC_STAT_CD"] = $accountRestrictionStatusCode;
		  $colvals["ORG_CD"] = $ORG_CD;
		  $colvals["ACCT_FSC_OFC_UID"] = $ACCT_FSC_OFC_UID;
		  //I'M NOT ENTIRELY SURE THESE VALUES ARE NEEDED
		  $colvals["SUB_FUND_GRP_CD"] = $SUB_FUND_GRP_CD;
		  $colvals["ACCT_TYP_CD"] = $accountTypeCode;
		  $colvals["FIN_HGH_ED_FUNC_CD"] = $higherEdFunctionCode;
		  $colvals["CONT_FIN_COA_CD"] = $chartCode;
		  $colvals["CONT_ACCOUNT_NBR"] = $translatedfundid;
		  $colvals["ENDOW_ACCOUNT_NBR"] = $translatedfundid;
		  $colvals["ENDOW_FIN_COA_CD"] = $chartCode;
		  $colvals["INCOME_FIN_COA_CD"] = $chartCode;
		  $colvals["INCOME_ACCOUNT_NBR"] = $translatedfundid;
		  $colvals["ACCT_ICR_TYP_CD"] = $ACCT_ICR_TYP_CD;
		  $colvals["ICR_FIN_COA_CD"] = $chartCode;
		  $colvals["ICR_ACCOUNT_NBR"] = $translatedfundid;
		  $colvals["BDGT_REC_LVL_CD"] = $BDGT_REC_LVL_CD;
		  $line = "";
		  foreach ( $cols as $col ) {
		    if ( strlen($line) > 0 ) {
		      $line .= ",";
		    }
		    if ( isset($colvals["$col"]) ) {
		      $line .= "\"" . $colvals["$col"] . "\"";
		    } else {
		      $line .= "NULL";
		    }
		  }
		  fwrite($acctsfile, "$line\n");
	}
}


fclose($acctsfile);
fclose($commentsfile);


?>