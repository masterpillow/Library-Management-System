<?php
require_once 'session.php';
require_once 'path.php';
require_once 'config.php';

$copy_id = (int) ($_GET['copy_id'] ?? 0);
$user_id = (int) ($_SESSION['user_id'] ?? 1); 

// Verify copy is available
$copy = $db_connection->query("SELECT copy_id, status FROM Copies WHERE copy_id = $copy_id")->fetch_assoc();
if (!$copy || $copy['status'] !== 'available') { die("That copy is not available."); }

// Verify user exists
$user_check = $db_connection->query("SELECT user_id FROM Users WHERE user_id = $user_id")->fetch_assoc();
if (!$user_check) { die("User account not found."); }

$due = date('Y-m-d', strtotime('+14 days'));

// Insert loan and update copy status
$db_connection->query("INSERT INTO Loans (copy_id, user_id, checkout_date, due_date) VALUES ($copy_id, $user_id, CURDATE(), '$due')");
$db_connection->query("UPDATE Copies SET status = 'on_loan' WHERE copy_id = $copy_id");

// Redirect back to your loans page
header("Location: /Library-Management-System/php/my_loans.php?msg=checkout_success");
exit;