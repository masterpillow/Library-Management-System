<?php
//======================================================================
// DATABASE LOGIN (the only place to change it)
//======================================================================
// Used by both php/config.php and php/start.php.
// XAMPP's default MySQL login is root with no password. If your root
// user has a password, put it in DB_PASSWORD below.

if (!defined('DB_HOST')) {
  DEFINE('DB_HOST', "localhost");
  DEFINE('DB_USER', "root");
  DEFINE('DB_PASSWORD', "");
  DEFINE('DB_NAME', "library_db");
}

/**
 * Turn a MySQL connection error into a message that says how to fix it.
 */
function db_setup_message($error_code, $error_text) {
  switch ($error_code) {
    case 2002:
      return "Can't reach MySQL. Start MySQL in XAMPP (Mac: XAMPP app > Manage Servers; "
           . "Windows: XAMPP Control Panel), then reload this page.";
    case 1045:
      return "MySQL refused the login for '" . DB_USER . "'. If your MySQL root user has a "
           . "password, put it in DB_PASSWORD in php/db_credentials.php.";
    case 1049:
      return "The " . DB_NAME . " database doesn't exist yet. Open php/start.php first to create it.";
    default:
      return "Database connection failed (" . $error_code . "): " . $error_text;
  }
}
?>
