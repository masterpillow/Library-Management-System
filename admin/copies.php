<?php
include_once(__DIR__ . '/../php/check_admin.php');
$page_name = "admin";

$copies = $db_connection->query("
  SELECT c.copy_id, c.barcode, c.status, b.title
  FROM Copies c
  JOIN Books b ON c.book_id = b.book_id
  ORDER BY b.title, c.barcode
");
?>
<!doctype html>
<html lang="en">
<head><?php include_once(ROOT_PATH . '/include/head.php'); ?></head>
<body class="admin">
<?php include_once(ROOT_PATH . '/include/header.php'); ?>
<main class="container">
  <h1>Manage Copies</h1>
  <table class="table">
    <tr><th>Barcode</th><th>Book</th><th>Status</th></tr>
    <?php while ($row = $copies->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($row['barcode']) ?></td>
        <td><?= htmlspecialchars($row['title']) ?></td>
        <td><?= htmlspecialchars($row['status']) ?></td>
      </tr>
    <?php endwhile; ?>
  </table>
</main>
</body>
</html>