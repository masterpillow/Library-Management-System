<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include_once(realpath(dirname(__FILE__) . '/../php/path.php'));
include_once(ROOT_SRC_PATH . '/config.php');

// Handle deactivate / reactivate
if (isset($_POST['toggle_active'])) {
    $user_id = (int)$_POST['user_id'];
    $new_status = (int)$_POST['new_status'];
    $stmt = $db_connection->prepare("UPDATE Users SET is_active = ? WHERE user_id = ?");
    $stmt->bind_param('ii', $new_status, $user_id);
    $stmt->execute();
    header("Location: members.php");
    exit;
}

$members = $db_connection->query("
  SELECT u.user_id, u.is_active, c.first_name, c.last_name, c.email, cr.username, r.role_type
  FROM Users u
  JOIN Contacts c ON u.contact_id = c.contact_id
  JOIN Credentials cr ON u.user_id = cr.user_id
  JOIN Roles r ON u.role_id = r.role_id
  ORDER BY u.user_id
");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Manage Members</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
  <h1>Manage Members</h1>
  <table class="table table-striped">
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Username</th>
        <th>Role</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
    <?php while ($row = $members->fetch_assoc()): ?>
      <tr>
        <td><?= $row['user_id'] ?></td>
        <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
        <td><?= htmlspecialchars($row['email']) ?></td>
        <td><?= htmlspecialchars($row['username']) ?></td>
        <td><?= htmlspecialchars($row['role_type']) ?></td>
        <td><?= $row['is_active'] ? 'Active' : 'Inactive' ?></td>
        <td>
          <form method="post" style="display:inline">
            <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
            <input type="hidden" name="new_status" value="<?= $row['is_active'] ? 0 : 1 ?>">
            <button type="submit" name="toggle_active" class="btn btn-sm <?= $row['is_active'] ? 'btn-warning' : 'btn-success' ?>">
              <?= $row['is_active'] ? 'Deactivate' : 'Reactivate' ?>
            </button>
          </form>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
  <a href="../index.php">Back to Home</a>
</body>
</html>