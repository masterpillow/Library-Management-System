<?php
//======================================================================
// LOGOUT: end the session and go back to the login page
//======================================================================
include_once (__DIR__ . '/helpers.php'); // starts the session

$_SESSION = [];
session_destroy();
session_start();
flash('success', 'You have been logged out.');
redirect('index.php');
