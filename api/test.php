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

$bimserver->logout();

echo "[POID] $POID\n";
echo "[ROID] $ROID\n";
echo "[Name] $name\n";

var_dump($OBJECTS->response->result[0]);

