<?php
$debug = false;
date_default_timezone_set('America/Los_Angeles');

if ($debug) {
    ini_set("display_errors", 1);
    error_reporting(E_ALL);
}

require("include/content_db_functions.php");

$db = new Content_DB_Functions(null);

echo json_encode($db->getRewards());

?>