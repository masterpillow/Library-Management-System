<?php
//======================================================================
// MEMBER: rate a book they have borrowed (1 to 5, one rating per book)
//======================================================================
include_once (__DIR__ . '/session.php');
require_role(ROLE_MEMBER);
require_post('user/loans.php');

$book_id = (int)(isset($_POST['book_id']) ? $_POST['book_id'] : 0);
$rating = (int)(isset($_POST['rating']) ? $_POST['rating'] : 0);

// Only books this member has actually borrowed
$q = $db_connection->prepare("SELECT b.title FROM Loans l
                              JOIN Copies c ON c.copy_id = l.copy_id
                              JOIN Books b ON b.book_id = c.book_id
                              WHERE l.user_id = ? AND c.book_id = ? LIMIT 1");
$q->bind_param('ii', $user_id, $book_id);
$q->execute();
$book = $q->get_result()->fetch_assoc();
$q->close();

if (!$book) {
  flash('warning', 'You can only rate books you have borrowed.');
} elseif ($rating < 1 || $rating > 5) {
  flash('warning', 'Pick a rating from 1 to 5.');
} else {
  $s = $db_connection->prepare("INSERT INTO Ratings (user_id, book_id, rating) VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE rating = VALUES(rating)");
  $s->bind_param('iii', $user_id, $book_id, $rating);
  $s->execute();
  $s->close();
  flash('success', 'You rated "' . $book['title'] . '" ' . $rating . ' out of 5.');
}
redirect('user/loans.php');
