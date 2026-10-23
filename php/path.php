<?php
//======================================================================
// PATHS
//======================================================================
// Works out the project folder automatically, so the app runs no matter
// what the folder in htdocs is called (for example
// "Library-Management-System-main" from a GitHub zip download).

$doc_root = realpath($_SERVER["DOCUMENT_ROOT"]);
$app_root = realpath(dirname(__DIR__));

$directory = "/Library-Management-System"; // fallback if it can't be worked out
if ($doc_root !== false && $app_root !== false && stripos($app_root, $doc_root) === 0) {
  $directory = str_replace('\\', '/', substr($app_root, strlen($doc_root)));
}

/* Define base url path */
if (!defined("BASE_URL")) {
  DEFINE("BASE_URL", $directory);
  DEFINE("ROOT_PATH", dirname(__DIR__));
  DEFINE("ROOT_SRC_PATH", __DIR__);
  DEFINE("SRC_PATH", $directory . "/php");
}
?>