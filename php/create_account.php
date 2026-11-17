<?php
//======================================================================
// CREATE ACCOUNT: handles the sign-up form
//======================================================================
// New accounts are members (role 2) with the default membership.

include_once (__DIR__ . '/config.php');
include_once (__DIR__ . '/helpers.php');

require_post('create_account.php');

$f = [];
foreach (['first_name', 'last_name', 'email', 'phone', 'username', 'street', 'city', 'state', 'zip'] as $k) {
  $f[$k] = trim(isset($_POST[$k]) ? $_POST[$k] : '');
}
$password = isset($_POST['password']) ? $_POST['password'] : '';

/* Send them back to the form with their values and a message */
function fail($text, $f) {
  $_SESSION['signup_old'] = $f;
  flash('danger', $text);
  redirect('create_account.php');
}

if ($f['first_name'] === '' || $f['last_name'] === '' || $f['email'] === '' || $f['phone'] === '' || $f['username'] === '') {
  fail('First name, last name, email, phone, and username are required.', $f);
}
if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
  fail('Please enter a valid email address.', $f);
}
if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $f['username'])) {
  fail('Usernames are 3 to 50 characters: letters, numbers, dots, dashes, or underscores.', $f);
}
if (strlen($password) < MIN_PASSWORD_LENGTH) {
  fail('Your password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.', $f);
}

$db_connection->begin_transaction();
try {
  $c = $db_connection->prepare("INSERT INTO Contacts (first_name, last_name, email, phone, street_1, city, state_code, post_code)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
  $c->bind_param('ssssssss', $f['first_name'], $f['last_name'], $f['email'], $f['phone'], $f['street'], $f['city'], $f['state'], $f['zip']);
  $c->execute();
  $contact_id = $c->insert_id;
  $c->close();

  $role = ROLE_MEMBER;
  $u = $db_connection->prepare("INSERT INTO Users (role_id, contact_id) VALUES (?, ?)");
  $u->bind_param('ii', $role, $contact_id);
  $u->execute();
  $new_id = $u->insert_id;
  $u->close();

  $hash = password_hash($password, PASSWORD_DEFAULT);
  $cr = $db_connection->prepare("INSERT INTO Credentials (username, user_id, password_hash) VALUES (?, ?, ?)");
  $cr->bind_param('sis', $f['username'], $new_id, $hash);
  $cr->execute();
  $cr->close();

  $db_connection->commit();
} catch (mysqli_sql_exception $ex) {
  $db_connection->rollback();
  if ($ex->getCode() == 1062) {
    fail(stripos($ex->getMessage(), 'email') !== false
      ? 'An account with that email already exists. Try logging in or resetting your password.'
      : 'That username is taken. Please choose another.', $f);
  }
  fail('Your account could not be created. Please try again.', $f);
}

flash('success', 'Welcome, ' . $f['first_name'] . '! Your account is ready. Log in to get started.');
redirect('index.php');
