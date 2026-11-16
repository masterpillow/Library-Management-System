<?php
require_once 'session.php';
require_once 'path.php';
require_once 'config.php';

$copy_id = (int) ($_GET['copy_id'] ?? 0);
$user_id = (int) ($_SESSION['user_id'] ?? 1); 

// Verify copy is available using the standardized database status string
$copy = $db_connection->query("SELECT copy_id, status FROM Copies WHERE copy_id = $copy_id")->fetch_assoc();
if (!$copy || $copy['status'] !== 'available') { die("That copy is not available."); }

// Verify user exists
$user_check = $db_connection->query("SELECT user_id FROM Users WHERE user_id = $user_id")->fetch_assoc();
if (!$user_check) { die("User account not found."); }

$due = date('Y-m-d', strtotime('+14 days'));

// Use database transactions to ensure data integrity during checkout
$db_connection->begin_transaction();

try {
    // Insert loan record
    $db_connection->query("INSERT INTO Loans (copy_id, user_id, checkout_date, due_date) VALUES ($copy_id, $user_id, CURDATE(), '$due')");
    
    // Update copy status to match the standardized 'checked_out' value used across admin tools
    $db_connection->query("UPDATE Copies SET status = 'checked_out' WHERE copy_id = $copy_id");
    
    $db_connection->commit();
    
    // Redirect back to your loans page
    header("Location: /Library-Management-System/php/my_loans.php?msg=checkout_success");
    exit;
} catch (Exception $e) {
    $db_connection->rollback();
    die("Checkout failed. Please try again.");
}