<?php

//ALL OF OUR PATRONS IN OLE ARE NOT 'OPERATORS' - SET UP TO LOGIN
//ONLY LIBRARY STAFF HAVE BEEN SET UP AS OPERATORS
//THIS FILE ADDS TO THE NECESSARY TABLES TO TRANSFORM
//THE PATRON RECORD TO AN OPERATOR FOR OUR LIB. STAFF
//AND ASSIGNS THEM THE PROPER ROLES


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
  if (error_reporting() == 0) {
    return;
  }
  if (error_reporting() & $severity) {
    echo $message;
    echo "\n\n";
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
  }
}

 set_error_handler('exceptions_error_handler');


//USING THIS CONNECTION TO THE SQLITE DB
//TO GET THE LIST OF ROLES NEEDED FOR THIS
//LIBRARY STAFF MEMBER
 $db = new SQLite3('olemigration.sqlite');
 echo 'Connected to the database.';

//CONTAINS THE DB USERID AND PASSWORD VARIABLES
//INITIALIZATION
require('proddbinfo.php');



$olecontwo=mysqli_connect("localhost","$userid","$password","OLE");
// CHECK DATABASE CONNECTION
if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  die;
}

//TODO: REMOVE
$olecon=mysqli_connect("localhost","$userid","$password","OLE");
// CHECK DATABASE CONNECTION
if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  die;
}

$stmt = mysqli_stmt_init($olecon);




$uniqueid = 20000;
//THE oleoperators.csv file contains a
//LIST OF LIBRARY STAFF WHO NEEDED TO BE 
//SET UP AS OPERATORS 
//EXAMPLE ROWS:
//8675309,test511~
//5551212,tset609~
//represents:
//barcode,userid (they will use to log in)
$response = file_get_contents('oleoperators.csv');
$operators = explode('~', $response);

foreach ($operators as $operator) {
     $uniqueid++;
     //SPLIT INTO OPERATOR/PRINCIPAL NAME
     ///var_dump($operator);
     $operator = trim($operator);
     $values = explode(",", $operator);
     //echo "SIZE OF" . sizeof($values);
     if ((sizeof($values)) > 1) {
     	 //barcode
         $lin = trim($values[0]);
         //userid
         $principalid = trim($values[1]);
     }
     else {
         echo "missing id";
         var_dump($values);
         //die;
     }

     //get patron id
     $query = "select OLE_PTRN_ID FROM OLE_PTRN_T WHERE BARCODE = '" . $lin . "'";
     $resulttwo = mysqli_query($olecontwo,$query);
     $rowtwo = mysqli_fetch_array($resulttwo);
     $entityid = $rowtwo['OLE_PTRN_ID'];
     

     //#1)
     mysqli_stmt_prepare($stmt, "INSERT into krim_prncpl_t (PRNCPL_ID,OBJ_ID,VER_NBR,PRNCPL_NM,ENTITY_ID,ACTV_IND) VALUES(?,?,'1',?,?,'Y')");
	 mysqli_stmt_bind_param($stmt, "ssss",$entityid ,$entityid,$principalid,$entityid);
	 mysqli_stmt_execute($stmt);
     printf("Error: %s.\n", $stmt->error);

	 //#2
     mysqli_stmt_prepare($stmt, "INSERT into krim_entity_afltn_t (ENTITY_AFLTN_ID,OBJ_ID,VER_NBR,ENTITY_ID,AFLTN_TYP_CD,CAMPUS_CD,DFLT_IND,ACTV_IND)
                                 values(?,?,'1',?,'STAFF','AP','Y','Y')");
	 mysqli_stmt_bind_param($stmt, "sss", $entityid ,$entityid,$entityid);
	 mysqli_stmt_execute($stmt);
     printf("Error: %s.\n", $stmt->error);

  	 //#3
  	 mysqli_stmt_prepare($stmt, "UPDATE krim_entity_ent_typ_t SET ACTV_IND = 'Y' where ENTITY_ID = ?");
  	 mysqli_stmt_bind_param($stmt, "s", $entityid);
  	 mysqli_stmt_execute($stmt);
     printf("Error: %s.\n", $stmt->error);

     //GET ROLES FOR EACH OPERATOR
     //echo "===============part2";
     //THE SQLITE DB / 'roles' TABLE
     //CONTAINED INFORMATION ABOUT
     //WHICH ROLES SHOULD BE ASSIGNED
     //TO WHICH LIB. STAFF
     $rolesquery = "select * from roles where userid = '$principalid'";
     $result = $db->query($rolesquery);
	 //FOR EACH ROLE:
     while ($row = $result->fetchArray(SQLITE3_ASSOC)) { 
         $obj_id = v4();
         $role_mbr_id = "OLE" . $uniqueid++;
         $role_id = $row["role"];
         $roleq = "insert into krim_role_mbr_t set ROLE_MBR_ID = ?, VER_NBR = 1, OBJ_ID = ?,ROLE_ID=?,MBR_ID=?,MBR_TYP_CD='P', ACTV_FRM_DT='2014-05-11'";
         mysqli_stmt_prepare($stmt, $roleq);
         mysqli_stmt_bind_param($stmt, "ssss", $role_mbr_id,$obj_id,$role_id,$entityid);
         mysqli_stmt_execute($stmt);
         printf("Error: %s.\n", $stmt->error);
    }
}


//SQLITE TABLE DEFINITION FOR 'ROLES' TABLE
/*
 * CREATE TABLE "roles" ("id" INTEGER PRIMARY KEY  NOT NULL , "userid" VARCHAR NOT NULL , "role" VARCHAR NOT NULL );
 */



?>