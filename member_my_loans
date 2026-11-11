<?php
include_once(realpath(dirname(__FILE__, 2) . '/php/session.php'));
include_once(realpath(dirname(__FILE__, 2) . '/php/path.php'));
include_once(ROOT_PATH . '/php/config.php');
$page_name = "member";

$user_id = (int) $_SESSION['user_id'];
$member = $db_connection->query("SELECT member_id FROM Members WHERE user_id = $user_id")->fetch_assoc();
$member_id = (int) ($member['member_id'] ?? 0);

$loans = $db_connection->query("
  SELECT l.loan_id, b.title, l.due_date, l.returned, l.fine
  FROM Loans l
  JOIN Copies c ON l.copy_id = c.copy_id
  JOIN Books b ON c.book_id = b.book_id
  WHERE l.member_id = $member_id
  ORDER BY l.due_date
");
?>
<!doctype html>
<html lang="en">
<head><?php include_once(ROOT_PATH . '/include/head.php'); ?></head>
<body class="<?php echo $page_name; ?>">
<?php include_once(ROOT_PATH . '/include/header.php'); ?>
<main class="container">
  <h1>My loans</h1>
  <table class="table">
    <tr><th>Title</th><th>Due</th><th>Returned</th><th>Fine</th></tr>
    <?php while ($row = $loans->fetch_assoc()):
      $late = (!$row['returned'] && $row['due_date'] < date('Y-m-d'));
    ?>
      <tr>
        <td><?php echo htmlspecialchars($row['title']); ?></td>
        <td><?php echo htmlspecialchars($row['due_date']); ?><?php if ($late) echo " (overdue)"; ?></td>
        <td><?php echo $row['returned'] ? htmlspecialchars($row['returned']) : "out"; ?></td>
        <td>$<?php echo number_format((float) $row['fine'], 2); ?></td>
      </tr>
    <?php endwhile; ?>
  </table>
</main>
<?php include_once(ROOT_PATH . '/include/footer.php'); ?>
</body>
</html>
