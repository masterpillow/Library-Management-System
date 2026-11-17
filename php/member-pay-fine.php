<?php
//======================================================================
// MEMBER: pay all outstanding fines (simulated payment for the demo)
//======================================================================
include_once (__DIR__ . '/session.php');
require_role(ROLE_MEMBER);
require_post('user/loans.php');

$q = $db_connection->prepare("SELECT COALESCE(SUM(fine), 0) AS total FROM Loans WHERE user_id = ? AND fine > 0");
$q->bind_param('i', $user_id);
$q->execute();
$total = (float)$q->get_result()->fetch_assoc()['total'];
$q->close();

if ($total > 0) {
  $u = $db_connection->prepare("UPDATE Loans SET fine = 0.00 WHERE user_id = ? AND fine > 0");
  $u->bind_param('i', $user_id);
  $u->execute();
  $u->close();
  flash('success', 'Payment of $' . number_format($total, 2) . ' received. Thank you!');
} else {
  flash('info', 'You have no fines to pay.');
}
redirect('user/loans.php');
