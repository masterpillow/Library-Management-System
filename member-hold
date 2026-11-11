<?php
include_once(realpath(dirname(__FILE__, 2) . '/php/session.php'));
include_once(realpath(dirname(__FILE__, 2) . '/php/path.php'));
include_once(ROOT_PATH . '/php/config.php');

$book_id = (int) ($_GET['book_id'] ?? 0);
$user_id = (int) $_SESSION['user_id'];

$member = $db_connection->query("SELECT member_id FROM Members WHERE user_id = $user_id")->fetch_assoc();
if (!$member) { die("No library card on this account."); }
$member_id = (int) $member['member_id'];

$in = $db_connection->query("SELECT copy_id FROM Copies WHERE book_id = $book_id AND status = 'in' LIMIT 1")->fetch_assoc();
if ($in) { die("A copy is in. Check it out instead of placing a hold."); }

$db_connection->query("INSERT INTO Holds (book_id, member_id, requested_at, status) VALUES ($book_id, $member_id, NOW(), 'waiting')");
header("Location: my_loans.php");
exit;
