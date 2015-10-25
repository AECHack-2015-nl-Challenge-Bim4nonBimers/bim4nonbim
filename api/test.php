<?php
 require("credentials.inc");
 include("bimserverJsonConnector.class.php");

 $bimserver = new bimserverJsonConnector($bimserver["URL"], $bimserver["Name"], $bimserver["Key"]);
 //var_dump($bimserver);

 $projects=$bimserver->getAllProjects(false,false);

$ROID=$projects[0]->lastRevisionId;
$name=$projects[0]->name;
$POID=$projects[0]->oid;

 $OBJECTS=$bimserver->getProjectDataObjects(array("roid"=>"$ROID"));

 //var_dump($bimserver->metaGetAllAsJson());
 $elements=$bimserver->getDataObjectByGuid($ROID,"1a51wBsI18khScCv3nhQJq")->response->result;

 $elementDoor=$bimserver->getDataObjectByGuid($ROID,'3ToDNw32H4sO$J5u6hyGUv')->response->result;

$transaction = $bimserver->LowLevelStartTransaction($POID)->response->result;
//"IfcText"
//$change=$bimserver->setStringAttribute($transaction,65814,"Description","Cool I set this Myself");

$endTrans=$bimserver->LowLevelAbortTransaction($transaction);
    //$bimserver->LowLevelCommitTransaction($transaction);

//$modelcheckers=$bimserver->getDefaultModelCompare()->response->result->oid;
//$compare=$bimserver->revisionCompare("65539","196611","ALL",$modelcheckers);
$bimserver->logout();

echo "[POID] $POID\n";
echo "[ROID] $ROID\n";
echo "[Name] $name\n";
echo "[RObC] " . ( count($OBJECTS->response->result) ) . "\n";
//var_dump($OBJECTS->response->result[0]);

//echo "[ELEMENTS]\n";
//var_dump($elements);
//echo "\n---------\n";
//var_dump($elementDoor);

var_dump($transaction);
var_dump($change);
var_dump($endTrans);

echo "[ELEMENTS]\n";
var_dump($elementDoor);


$in=Array("id"=>"1", "Key" =>"description", "Value"=>"This Text");

var_dump(json_encode($in));