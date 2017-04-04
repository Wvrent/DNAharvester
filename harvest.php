#!/usr/bin/php
<?php
// Load configuration file
$_GLOBALS = parse_ini_file('harvest.conf', true);

include_once "/home/maint/harvest_include.php";

set_error_handler('errHandle');

if (isset($argv[1])) {
	$mode   = $argv[1];
}
else {
	$mode = "help";
}

switch ($mode) {
case "bibscan":
	bibscan();
	break;
case "itemscan":
	itemscan();
	break;
case "update":
	update();
	break;
}

$conn = pg_close($pgconn);


?>
