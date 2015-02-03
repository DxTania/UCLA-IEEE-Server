<?php
$debug = false;
date_default_timezone_set('America/Los_Angeles');

if ($debug) {
  ini_set("display_errors", 1);
  error_reporting(E_ALL);
}

require("include/content_db_functions.php");

$db = new Content_DB_Functions(null);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  if (isset($_GET['limit'])) {
      echo json_encode($db->getAnnouncements($_GET['limit']));
  } else {
      echo json_encode($db->getAnnouncements(10));
  }

} else if ($_SERVER['REQUEST_METHOD'] === 'POST'
  && isset($_POST['content'])) {
  $returnContent = $db->postAnnouncement($_POST['content']);
  if ($returnContent['content'] == $_POST['content']) {
    echo json_encode(array('success' => 1));
  } else {
    echo json_encode(array('error' => 1));
  }
}

?>