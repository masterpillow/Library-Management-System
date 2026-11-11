<?php
require_once 'session.php';
require_once 'path.php';
require_once 'config.php';

$hold_id = (int) ($_GET['hold_id'] ?? 0);
$user_id = (int) $_SESSION['user_id'];

// Update the hold status to 'cancelled' (matches database constraints) 
// only if it belongs to this user and is still active ('waiting' or 'ready')
$db_connection->query("
    UPDATE Holds 
    SET status = 'cancelled' 
    WHERE hold_id = $hold_id 
      AND user_id = $user_id 
      AND status IN ('waiting', 'ready')
");

header("Location: /Library-Management-System/php/my_loans.php?msg=hold_cancelled");
exit;