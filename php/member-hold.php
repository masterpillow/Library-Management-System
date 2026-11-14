<?php
require_once 'session.php';
require_once 'path.php';
require_once 'config.php';

$book_id = (int) ($_GET['book_id'] ?? 0);
$user_id = (int) ($_SESSION['user_id'] ?? 1);

// Verify book exists in Books table
$book_check = $db_connection->query("SELECT book_id FROM Books WHERE book_id = $book_id")->fetch_assoc();
if (!$book_check) { die("Book not found."); }

// Verify user exists in Users table
$user_check = $db_connection->query("SELECT user_id FROM Users WHERE user_id = $user_id")->fetch_assoc();
if (!$user_check) { die("User account not found."); }

// PREVENT DUPLICATE HOLDS: Check if an active hold already exists for this user and book
$duplicate_check = $db_connection->prepare("SELECT hold_id FROM Holds WHERE user_id = ? AND book_id = ? AND status IN ('waiting', 'ready')");
$duplicate_check->bind_param("ii", $user_id, $book_id);
$duplicate_check->execute();
$duplicate_check->store_result();

if ($duplicate_check->num_rows > 0) {
    $duplicate_check->close();
    header("Location: /Library-Management-System/php/my_loans.php?msg=hold_duplicate");
    exit;
}
$duplicate_check->close();

// Insert the hold request with the official 'waiting' status
$stmt = $db_connection->prepare("INSERT INTO Holds (book_id, user_id, status, hold_date) VALUES (?, ?, 'waiting', CURDATE())");
$stmt->bind_param("ii", $book_id, $user_id);
$stmt->execute();
$stmt->close();

// Redirect back to your loans/account page
header("Location: /Library-Management-System/php/my_loans.php?msg=hold_success");
exit;