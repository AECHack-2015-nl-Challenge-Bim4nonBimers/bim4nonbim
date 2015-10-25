<?php

   $POST=$GLOBALS['HTTP_RAW_POST_DATA'];
 if(empty($POST)) {
	 var_dump($GLOBALS);
  echo "No POST";
  echo '<html>
<head><title>test</title></head>
<body>
<form action="" method="POST">
<input type="text" name="json" id="json"></p>
<input value="Submit" type="submit" onclick="submitform()">
</form></body></html>';
  exit;
 }

 $json=json_decode($POST);
 //var_dump(json_decode($_POST['json']));
 $bimid=$json->id;
 $bimObjKey=$json->Key;
 $bimObjValue=$json->Value;
 //$$json->objects;
 $bimObjectOID=$json->objects->oid;
 $bimObjectGUID=$json->objects->guid;
//echo "$json";

// echo "[id] $bimid [key] $bimObjKey [value] $bimObjValue [oid] $bimObjectOID [guid] $bimObjectGUID";

 $s='"'.$bimObjectGUID.'","'.$bimObjKey.'","'.$bimObjValue.'"';
 $f=fopen("updates.csv","a");
 fwrite($f,"$s\n");
 fclose($f);
 //exit();

 require("credentials.inc");
 include("bimserverJsonConnector.class.php");

 $bimserver = new bimserverJsonConnector($bimserver["URL"], $bimserver["Name"], $bimserver["Key"]);

 $projects=$bimserver->getAllProjects(false,false);

$ROID=$projects[0]->lastRevisionId;
$name=$projects[0]->name;
$POID=$projects[0]->oid;

$transaction = $bimserver->LowLevelStartTransaction($POID)->response->result;
$change=$bimserver->addStringAttribute($transaction,$bimid,$bimObjKey,$bimObjValue);

$endTrans=$bimserver->LowLevelCommitTransaction($transaction);

$bimserver->logout();

echo "POID=$POID, ROID=$ROID, NewRoid=".var_export($endTrans,TRUE)."\n";

return $endTrans;