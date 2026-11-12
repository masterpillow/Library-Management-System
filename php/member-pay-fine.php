<?php
require_once 'session.php';
require_once 'path.php';
require_once 'config.php';

$loan_id = (int) ($_GET['loan_id'] ?? 0);
$user_id = (int) $_SESSION['user_id'];

// Clear the fine for this specific loan, ensuring it belongs to the logged-in user
$db_connection->query("UPDATE Loans SET fine = 0.00 WHERE loan_id = $loan_id AND user_id = $user_id");

// Redirect back to account page with a success message
header("Location: /Library-Management-System/php/my_loans.php?msg=fine_paid");
exit;