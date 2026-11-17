<?php
//======================================================================
// MEMBER: place a hold on a book
//======================================================================
// Holds are only for books with every copy checked out. One active hold
// per book per member. First come, first served by hold_date.

include_once (__DIR__ . '/session.php');
require_role(ROLE_MEMBER);
require_post('user/search.php');

$book_id = (int)(isset($_POST['book_id']) ? $_POST['book_id'] : 0);

$q = $db_connection->prepare(
  "SELECT b.title,
          (SELECT COUNT(*) FROM Copies c WHERE c.book_id = b.book_id AND c.status = 'available') AS available,
          (SELECT COUNT(*) FROM Copies c WHERE c.book_id = b.book_id AND c.status IN ('available', 'on_loan', 'on_hold')) AS in_circulation,
          (SELECT COUNT(*) FROM Holds h WHERE h.book_id = b.book_id AND h.user_id = ? AND h.status IN ('waiting', 'ready')) AS my_holds,
          (SELECT COUNT(*) FROM Loans l JOIN Copies c ON c.copy_id = l.copy_id
            WHERE c.book_id = b.book_id AND l.user_id = ? AND l.return_date IS NULL) AS my_loans
   FROM Books b WHERE b.book_id = ? AND b.is_active = 1");
$q->bind_param('iii', $user_id, $user_id, $book_id);
$q->execute();
$book = $q->get_result()->fetch_assoc();
$q->close();

if (!$book) {
  flash('warning', 'That book is not in the catalog.');
} elseif ($book['my_loans'] > 0) {
  flash('warning', 'You already have "' . $book['title'] . '" checked out.');
} elseif ($book['my_holds'] > 0) {
  flash('info', 'You are already on the hold list for "' . $book['title'] . '".');
} elseif ($book['available'] > 0) {
  flash('info', 'A copy of "' . $book['title'] . '" is on the shelf, so you can check it out now instead.');
} elseif ($book['in_circulation'] == 0) {
  flash('warning', 'The library has no copies of "' . $book['title'] . '" to hold.');
} else {
  $ins = $db_connection->prepare("INSERT INTO Holds (book_id, user_id, status, hold_date) VALUES (?, ?, 'waiting', NOW())");
  $ins->bind_param('ii', $book_id, $user_id);
  $ins->execute();
  $ins->close();
  flash('success', 'You\'re on the hold list for "' . $book['title'] . '". When a copy comes back it will be set aside for you for ' . HOLD_PICKUP_DAYS . ' days.');
  redirect('user/loans.php');
}
redirect('user/search.php');
