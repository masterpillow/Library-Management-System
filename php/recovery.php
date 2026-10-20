<?php
//======================================================================
// PASSWORD RECOVERY (placeholder)
//======================================================================
// TODO: generate a random token, store its hash in Password_Resets with
// an expires_at time, and email the member a reset link. The table is
// already created by start.php. Until then, send them back with a note.

include_once (realpath(dirname(__FILE__).'/path.php'));

session_start();
$_SESSION['message'] = "Password reset isn't available yet. Please contact a librarian.";
header("location: " . BASE_URL . "/index.php");
exit();
