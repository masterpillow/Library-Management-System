<?php
/**
 * Submit Book Rating Handler
 */
require_once 'path.php';
require_once ROOT_PATH . '/php/session.php';
require_once ROOT_PATH . '/php/config.php';

// Ensure user is logged in as a member (Role 2)
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 2) {
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $book_id = filter_input(INPUT_POST, 'book_id', FILTER_VALIDATE_INT);
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    $user_id = $_SESSION['user_id'];

    // Validate inputs against the 1-5 constraint
    if ($book_id && $rating >= 1 && $rating <= 5) {
        $sql = "INSERT INTO Ratings (user_id, book_id, rating) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE rating = VALUES(rating)";
                
        $stmt = $db_connection->prepare($sql);
        $stmt->bind_param("iii", $user_id, $book_id, $rating);
        $stmt->execute();
        $stmt->close();
    }
}

// Redirect back to search page
header("Location: " . BASE_URL . "/user/search.php");
exit();
?>