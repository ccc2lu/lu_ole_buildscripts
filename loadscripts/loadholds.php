<?php

//PULLS HOLDS DATA (aka REQUESTS) OUT OF
//THE 'holds' TABLE IN THE SQLITE STAGING DB
//PREVIOUSLY POPULATED WITH stageholds.php
//AND INSERTS IT INTO THE OLEDB
//**NOTE: THIS SCRIPT INSERTS ROWS DIRECTLY
//INTO THE OLE DB -- THIS SCRIPT GENERATES
//THE UNIQUE KEYS FOR EACH ROW INSTEAD OF
//USING THE '_S' TABLES PROVIDED BY OLE
//YOU HAVE TO SYNC UP THE _S TABLE AFTER YOU
//RUN THIS SCRIPT OR OLE WILL THINK
//THE STARTING POINT FOR THE INCREMENTAL
//KEY IS LESS THAN IT IS (MEANING - IT COULD TRY TO
//USE KEYS YOU'VE ALREADY INSERTED)
//TODO: USE THE _S TABLES FOR THIS SCRIPT
//EXAMPLE:
//OLE_DLVR_RQST_T ---> OLE_DLVR_RQST_S (keeps
//track of keys for the '_T' table 
//**NOTE: PREREQ FOR RUNNING THIS SCRIPT-- PATRONS AND ITEMS HAVE TO
//ALREADY BE LOADED SO THE HOLDS/REQUESTS CAN LINK TO THEM

//SET UP LOGGING 
require('KLogger.php');
$log = new KLogger ( "logholds.txt" , KLogger::DEBUG );


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

//connection to main ole database:
$olecon=mysqli_connect("localhost","$userid","$password","OLE");
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

//TODO: REMOVE
$itemcon=mysqli_connect("localhost","$userid","$password","OLE");
//connection to main ole database -- USED TO GET ITEM IDS
// CHECK DATABASE CONNECTION
if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  die;
}

//TODO: REMOVE
$olecontwo=mysqli_connect("localhost","$userid","$password","OLE");
//connection to main ole database -- USED TO GET ITEM IDS
// CHECK DATABASE CONNECTION
if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  die;
}


//CONNECT TO SQLITE DB
$db = new SQLite3('olemigration.sqlite');
echo 'Connected to the database.';

//GET ALL OF THE HOLDS FROM THE SQLITE DB
$query = "select * from holds";
$result = $db->query($query);



$uniqueid = 245;

$counter = 0;
while ($row = $result->fetchArray(SQLITE3_ASSOC)) { 
      //var_dump($row);
      $counter++;
      $uniqueid++;
      $barcode = $row['ITEM_ID'];
      $patronbarcode = $row['USER_ID'];
      $holdDate = $row["HOLD_DATE"];
      $holdExpireDate = $row["HOLD_EXPIRES_DATE"];
      if ($holdExpireDate == "NEVER") $holdExpireDate = "20250101";
      //NEED BOTH PATRON ID AND BARCODE FOR THIS TABLE 
      //LOOK UP PATRON ID FROM OLE USING THE BARCODE
      $query = "select OLE_PTRN_ID FROM OLE_PTRN_T WHERE BARCODE = '" . $patronbarcode . "'";
      $resulttwo = mysqli_query($olecontwo,$query);
      $rowtwo = mysqli_fetch_array($resulttwo);
      $patronid = $rowtwo['OLE_PTRN_ID'];
      //END PATRON ID LOOKUP
      
      //IN OLE, HOLDS REQUIRE THE ITEM ID -- NOT THE ITEM BARCODE -- SO THIS
      //HAS TO BE LOOKED UP
      //THE HOLDS DUMP ONLY HAS THE BARCODE
      //getting  $item_id 
      $barcode = $row['ITEM_ID'];
      $itemquery = "select item_id from OLE_DS_ITEM_T where barcode = '$barcode'";
      $itemresults = mysqli_query($itemcon,$itemquery);
      $item= mysqli_fetch_array($itemresults);
      try {
         $item_id = $item['item_id'];  //ITEM ID IS SET HERE
      }
      catch(Exception $e) {
          echo "failed to find item id";
          $item_id = 0;
      }
      ///END LOOKUP ITEM ID


     //CALL INSERT STATEMENT:
     //OLE_DLVR_RQST_T
     $stmt = mysqli_stmt_init($olecon);
     $obj_id = v4();
     mysqli_stmt_prepare($stmt, "INSERT INTO OLE_DLVR_RQST_T (OLE_RQST_ID,OBJ_ID,VER_NBR,ITM_ID,OLE_PTRN_ID,OLE_PTRN_BARCD,OLE_RQST_TYP_ID,RQST_EXPIR_DT_TIME,CRTE_DT_TIME,ITEM_UUID)
                                                              VALUES(?,?,1,?,?,?,5,?,?,?)");
     mysqli_stmt_bind_param($stmt, "ssssssss",$uniqueid,$obj_id,$barcode,$patronid,$patronbarcode,$holdExpireDate,$holdDate,$item_id);
     mysqli_stmt_execute($stmt);

}

  echo "processed $counter holds";
  echo "\n\n";
