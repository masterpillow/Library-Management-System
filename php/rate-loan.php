<?php
require_once 'session.php';
require_once 'path.php';
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loan_id = (int) ($_POST['loan_id'] ?? 0);
    $rating = (int) ($_POST['rating'] ?? 0);
    $user_id = (int) ($_SESSION['user_id'] ?? 0);

    // Validate rating range (1 to 5) and ensure IDs are valid
    if ($rating >= 1 && $rating <= 5 && $loan_id > 0 && $user_id > 0) {
        // Secure update: verify the loan belongs to this specific user before updating
        $stmt = $db_connection->prepare("UPDATE Loans SET rating = ? WHERE loan_id = ? AND user_id = ?");
        $stmt->bind_param("iii", $rating, $loan_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Redirect back to the loans account page with a success flag
header("Location: my_loans.php?msg=rating_success");
exit;