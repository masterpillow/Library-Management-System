<?php
/**
 * Database Configuration File
 *
 * @author    Joshua Connor <connorj4@southernct.edu>
 * @copyright 2026 
 * @date      2026-02-24
 * @version   1.0
 */

// Author: Joshua Connor - Added new validation logic (2026-02-24)
// The database login itself is in php/db_credentials.php.

include_once (ROOT_PATH.'/php/path.php');

/* Important - Comment Error Reporting Section out before going live!!!  */
error_reporting(E_ALL);
ini_set('display_errors', 1); // set to 1 to display errors, 0 to hide them
// End of Error Reporting
/* --------------------------------------------------------------------- */

include_once (__DIR__ . '/db_credentials.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $db_connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
} catch (mysqli_sql_exception $e) {
  // Show how to fix it instead of a stack of PHP errors
  http_response_code(500);
  echo '<p style="font-family:sans-serif;max-width:40em;margin:3em auto">'
     . htmlspecialchars(db_setup_message($e->getCode(), $e->getMessage())) . '</p>';
  exit();
}

?>