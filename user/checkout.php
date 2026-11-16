<?php
include_once(realpath(dirname(__FILE__, 2) . '/php/session.php'));
include_once(realpath(dirname(__FILE__, 2) . '/php/path.php'));
include_once(ROOT_PATH . '/php/config.php');

$copy_id = (int) ($_GET['copy_id'] ?? 0);
$user_id = (int) ($_SESSION['user_id'] ?? 0);

if ($user_id <= 0) {
    header("Location: login.php");
    exit;
}

$member = $db_connection->query("SELECT member_id FROM Members WHERE user_id = $user_id")->fetch_assoc();
if (!$member) { die("No library card on this account."); }
$member_id = (int) $member['member_id'];

// Match the standardized database status string 'available' instead of 'in'
$copy = $db_connection->query("SELECT copy_id, status FROM Copies WHERE copy_id = $copy_id")->fetch_assoc();
if (!$copy || $copy['status'] !== 'available') { die("That copy is not available."); }

$due = date('Y-m-d', strtotime('+14 days'));

$db_connection->begin_transaction();
try {
    $db_connection->query("INSERT INTO Loans (copy_id, member_id, checkout_date, due_date) VALUES ($copy_id, $member_id, CURDATE(), '$due')");
    
    // Match the standardized database status string 'checked_out' instead of 'out'
    $db_connection->query("UPDATE Copies SET status = 'checked_out' WHERE copy_id = $copy_id");
    
    $db_connection->commit();
    header("Location: my_loans.php");
    exit;
} catch (Exception $e) {
    $db_connection->rollback();
    die("Checkout failed. Please try again.");
}