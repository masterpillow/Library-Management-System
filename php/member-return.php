<?php
//======================================================================
// MEMBER: return a book (same rules as the admin Returns page)
//======================================================================
include_once (__DIR__ . '/session.php');
require_role(ROLE_MEMBER);
require_post('user/loans.php');

$loan_id = (int)(isset($_POST['loan_id']) ? $_POST['loan_id'] : 0);

// Only the member's own open loan
$own = $db_connection->prepare("SELECT loan_id FROM Loans WHERE loan_id = ? AND user_id = ? AND return_date IS NULL");
$own->bind_param('ii', $loan_id, $user_id);
$own->execute();
$is_mine = (bool)$own->get_result()->fetch_assoc();
$own->close();

if ($is_mine) {
  $result = return_loan($db_connection, $loan_id);
  flash('success', $result !== null ? $result : 'That loan was already returned.');
} else {
  flash('warning', 'That loan was not found.');
}
redirect('user/loans.php');
