<?php
 require("credentials.inc");
 include("bimserverJsonConnector.class.php");

 $bimserver = new bimserverJsonConnector($bimserver["URL"], $bimserver["Name"], $bimserver["Key"]);
 //var_dump($bimserver);

 var_dump($bimserver->getAllProjects(false,false));

 $bimserver->logout();
?>
