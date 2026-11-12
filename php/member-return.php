<?php
require_once 'session.php';
require_once 'path.php';
require_once 'config.php';

$loan_id = (int) ($_GET['loan_id'] ?? 0);
$user_id = (int) $_SESSION['user_id'];

// Verify the loan belongs to the user and is currently active (not yet returned)
$loan_query = $db_connection->query("SELECT copy_id FROM Loans WHERE loan_id = $loan_id AND user_id = $user_id AND return_date IS NULL");
if ($loan_query && $loan_query->num_rows > 0) {
    $loan = $loan_query->fetch_assoc();
    $copy_id = (int) $loan['copy_id'];

    // 1. Mark the loan as returned today
    $db_connection->query("UPDATE Loans SET return_date = CURDATE() WHERE loan_id = $loan_id");

    // 2. Set the book copy status back to 'available' so others can check it out
    $db_connection->query("UPDATE Copies SET status = 'available' WHERE copy_id = $copy_id");
}

// Redirect back to account page with success message
header("Location: /Library-Management-System/php/my_loans.php?msg=return_success");
exit;