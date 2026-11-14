<?php
include_once(realpath(dirname(__FILE__) . '/../php/path.php'));
include_once(ROOT_SRC_PATH . '/session.php');
include_once(ROOT_SRC_PATH . '/config.php');
include_once(ROOT_SRC_PATH . '/check_admin.php');

$copies = $db_connection->query("
  SELECT c.copy_id, c.barcode, c.status, b.title
  FROM Copies c
  JOIN Books b ON c.book_id = b.book_id
  ORDER BY b.title, c.barcode
");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Manage Copies</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
  <h1>Manage Copies</h1>
  <table class="table table-striped">
    <thead><tr><th>Barcode</th><th>Book</th><th>Status</th></tr></thead>
    <tbody>
    <?php while ($row = $copies->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($row['barcode']) ?></td>
        <td><?= htmlspecialchars($row['title']) ?></td>
        <td><?= htmlspecialchars($row['status']) ?></td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
  <a href="index.php">Back to Admin</a>
</body>
</html>