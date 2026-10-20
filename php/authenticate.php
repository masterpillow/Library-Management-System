<?php

/**
 * Authentication Script
 *
 * @author    Joshua Connor <connorj4@southernct.edu>
 * @copyright 2026
 * @date      2026-02-24
 * @version   1.1  Library Management System: password_verify, exit after redirects
 */

// Include necessary configuration and path files
include_once (realpath(dirname(__FILE__).'/path.php'));
include_once (realpath(dirname(__FILE__).'/config.php'));

session_start();

//-----------------------------------------------------
// Authenticate
//-----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  // Direct access without POST data
  header("location: " . SRC_PATH . "/logout.php");
  exit();
}

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if ($username === '' || $password === '') {
  $_SESSION['message'] = "Username or Password is empty!";
  header("location: " . BASE_URL . "/index.php");
  exit();
}

// Look the user up by username only, then check the password against the stored hash.
// Prepared statements already protect against SQL injection, so no escaping is needed.
$select_user = $db_connection->prepare(
    "SELECT u.user_id, u.role_id, cr.username, cr.password_hash
     FROM Users u
     JOIN Credentials cr ON u.user_id = cr.user_id
     WHERE cr.username = ? AND u.is_active = 1
     LIMIT 1");
$select_user->bind_param("s", $username);
$select_user->execute();
$select_user->bind_result($user_id, $user_role, $db_username, $password_hash);
$found = $select_user->fetch();
$select_user->close();

if ($found && password_verify($password, $password_hash)) {
  // Successful login: new session id so an old one can't be reused
  session_regenerate_id(true);
  $_SESSION['user_id'] = $user_id;
  $_SESSION['login_user'] = $db_username;
  $_SESSION['user_role'] = $user_role;

  // Redirect based on user role
  if ($user_role == 1) {
    header("location: " . BASE_URL . "/admin");
  } elseif ($user_role == 2) {
    header("location: " . BASE_URL . "/user");
  } elseif ($user_role == 3) {
    header("location: " . BASE_URL . "/guest");
  } else {
    $_SESSION['error'] = "Login Failed";
    header("location: " . BASE_URL . "/index.php");
  }
  exit();
}

// Invalid login: same message whether the username or the password was wrong
$_SESSION['message'] = "Username or Password did not match!";
header("location: " . BASE_URL . "/index.php");
exit();
