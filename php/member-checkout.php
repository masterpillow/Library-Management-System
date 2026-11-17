<?php
//======================================================================
// MEMBER: check out a book
//======================================================================
// Rules (Business Rules slide):
//  - members only, no unpaid fines, under their membership's loan limit
//  - one copy per book at a time
//  - if a copy is set aside for this member's hold, they get that copy
//    and the hold is fulfilled; otherwise they get any copy on the shelf
//  - due date = today + the membership's loan_days

include_once (__DIR__ . '/session.php');
require_role(ROLE_MEMBER);

$back = (isset($_POST['back']) && $_POST['back'] === 'search') ? 'user/search.php' : 'user/loans.php';
require_post($back);

$book_id = (int)(isset($_POST['book_id']) ? $_POST['book_id'] : 0);

$db_connection->begin_transaction();
try {
  // Member's limits and current standing
  $m = $db_connection->prepare(
    "SELECT ms.max_loans, ms.loan_days,
            (SELECT COUNT(*) FROM Loans l WHERE l.user_id = u.user_id AND l.return_date IS NULL) AS open_loans,
            (SELECT COALESCE(SUM(l.fine), 0) FROM Loans l WHERE l.user_id = u.user_id) AS fines
     FROM Users u JOIN Memberships ms ON ms.membership_id = u.membership_id
     WHERE u.user_id = ?");
  $m->bind_param('i', $user_id);
  $m->execute();
  $me = $m->get_result()->fetch_assoc();
  $m->close();

  if ($me['fines'] > 0) {
    throw new RuntimeException('You owe $' . number_format($me['fines'], 2) . ' in fines. Pay it on My Loans before checking out more books.');
  }
  if ($me['open_loans'] >= $me['max_loans']) {
    throw new RuntimeException('You already have ' . (int)$me['open_loans'] . ' books out, the most your membership allows. Return one first.');
  }

  // The book must be active, and the member can't already have a copy of it
  $b = $db_connection->prepare(
    "SELECT b.title,
            (SELECT COUNT(*) FROM Loans l JOIN Copies c ON c.copy_id = l.copy_id
              WHERE c.book_id = b.book_id AND l.user_id = ? AND l.return_date IS NULL) AS already
     FROM Books b WHERE b.book_id = ? AND b.is_active = 1");
  $b->bind_param('ii', $user_id, $book_id);
  $b->execute();
  $book = $b->get_result()->fetch_assoc();
  $b->close();
  if (!$book) {
    throw new RuntimeException('That book is not in the catalog.');
  }
  if ($book['already'] > 0) {
    throw new RuntimeException('You already have a copy of "' . $book['title'] . '".');
  }

  // A copy set aside for this member's hold comes first
  $h = $db_connection->prepare("SELECT hold_id FROM Holds WHERE book_id = ? AND user_id = ? AND status = 'ready' LIMIT 1 FOR UPDATE");
  $h->bind_param('ii', $book_id, $user_id);
  $h->execute();
  $hold = $h->get_result()->fetch_assoc();
  $h->close();

  $want = $hold ? 'on_hold' : 'available';
  $c = $db_connection->prepare("SELECT copy_id FROM Copies WHERE book_id = ? AND status = ? ORDER BY copy_id LIMIT 1 FOR UPDATE");
  $c->bind_param('is', $book_id, $want);
  $c->execute();
  $copy = $c->get_result()->fetch_assoc();
  $c->close();
  if (!$copy) {
    throw new RuntimeException('Every copy of "' . $book['title'] . '" is checked out. You can place a hold instead.');
  }

  $ins = $db_connection->prepare("INSERT INTO Loans (copy_id, user_id, checkout_date, due_date)
                                  VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY))");
  $ins->bind_param('iii', $copy['copy_id'], $user_id, $me['loan_days']);
  $ins->execute();
  $ins->close();

  $up = $db_connection->prepare("UPDATE Copies SET status = 'on_loan' WHERE copy_id = ?");
  $up->bind_param('i', $copy['copy_id']);
  $up->execute();
  $up->close();

  if ($hold) {
    $f = $db_connection->prepare("UPDATE Holds SET status = 'fulfilled' WHERE hold_id = ?");
    $f->bind_param('i', $hold['hold_id']);
    $f->execute();
    $f->close();
  }

  $db_connection->commit();
  flash('success', 'You checked out "' . $book['title'] . '". It\'s due in ' . (int)$me['loan_days'] . ' days.');
  redirect('user/loans.php');
} catch (RuntimeException $ex) {
  $db_connection->rollback();
  flash('warning', $ex->getMessage());
  redirect($back);
} catch (mysqli_sql_exception $ex) {
  $db_connection->rollback();
  flash('danger', 'Checkout failed. Please try again.');
  redirect($back);
}
