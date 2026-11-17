<?php
//======================================================================
// MEMBER: cancel one of their holds
//======================================================================
// If a copy was already set aside for this hold, it goes to the next
// person waiting, or back on the shelf.

include_once (__DIR__ . '/session.php');
require_role(ROLE_MEMBER);
require_post('user/loans.php');

$hold_id = (int)(isset($_POST['hold_id']) ? $_POST['hold_id'] : 0);

$db_connection->begin_transaction();
try {
  $q = $db_connection->prepare("SELECT book_id, status FROM Holds
                                WHERE hold_id = ? AND user_id = ? AND status IN ('waiting', 'ready') FOR UPDATE");
  $q->bind_param('ii', $hold_id, $user_id);
  $q->execute();
  $hold = $q->get_result()->fetch_assoc();
  $q->close();

  if ($hold) {
    $u = $db_connection->prepare("UPDATE Holds SET status = 'cancelled' WHERE hold_id = ?");
    $u->bind_param('i', $hold_id);
    $u->execute();
    $u->close();
    if ($hold['status'] === 'ready') {
      promote_next_hold($db_connection, (int)$hold['book_id']);
    }
    flash('success', 'Your hold was cancelled.');
  } else {
    flash('warning', 'That hold was not found.');
  }
  $db_connection->commit();
} catch (mysqli_sql_exception $ex) {
  $db_connection->rollback();
  flash('danger', 'The hold could not be cancelled. Please try again.');
}
redirect('user/loans.php');
