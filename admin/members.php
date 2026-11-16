<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include_once(realpath(dirname(__FILE__) . '/../php/path.php'));
include_once(ROOT_SRC_PATH . '/session.php');
include_once(ROOT_SRC_PATH . '/config.php');
include_once(ROOT_SRC_PATH . '/check_admin.php');

$message = '';
$error = '';

// Handle deactivate / reactivate via secure POST request
if (isset($_POST['toggle_active'])) {
    $user_id = (int)$_POST['user_id'];
    $new_status = (int)$_POST['new_status'];
    
    // Prevent self-deactivation of the currently logged-in administrator
    if ($user_id === (int)$_SESSION['user_id']) {
        $error = "You cannot deactivate your own active administrator account.";
    } else {
        $stmt = $db_connection->prepare("UPDATE Users SET is_active = ? WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param('ii', $new_status, $user_id);
            if ($stmt->execute()) {
                $message = "Member account status successfully updated.";
            } else {
                $error = "Failed to update member status.";
            }
        }
    }
}

// Handle new user creation and registration submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_member'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role_id = (int)$_POST['role_id'];

    if (empty($first_name) || empty($last_name) || empty($email) || empty($username) || empty($password)) {
        $error = "All fields are required to register a new member.";
    } else {
        $db_connection->begin_transaction();
        try {
            // 1. Insert contact information
            $stmt1 = $db_connection->prepare("INSERT INTO Contacts (first_name, last_name, email) VALUES (?, ?, ?)");
            $stmt1->bind_param('sss', $first_name, $last_name, $email);
            $stmt1->execute();
            $contact_id = $db_connection->insert_id;

            // 2. Insert user record linking contact, role, and active status
            $stmt2 = $db_connection->prepare("INSERT INTO Users (contact_id, role_id, is_active) VALUES (?, ?, 1)");
            $stmt2->bind_param('ii', $contact_id, $role_id);
            $stmt2->execute();
            $user_id = $db_connection->insert_id;

            // 3. Insert secure credential record with password hashing
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt3 = $db_connection->prepare("INSERT INTO Credentials (user_id, username, password_hash) VALUES (?, ?, ?)");
            $stmt3->bind_param('iss', $user_id, $username, $password_hash);
            $stmt3->execute();

            $db_connection->commit();
            $message = "New member account successfully registered!";
        } catch (Exception $e) {
            $db_connection->rollback();
            $error = "Error registering member: " . $e->getMessage();
        }
    }
}

// Fetch available roles for the registration dropdown
$roles = $db_connection->query("SELECT role_id, role_type FROM Roles");

// Fetch all registered members for the directory table
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
  <h1 class="mb-4">Manage Members & Accounts</h1>
  <p class="text-muted">View existing library patrons, manage access status, and register new accounts securely.</p>

  <?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Register New Member Card -->
  <div class="card mb-4 shadow-sm">
    <div class="card-header bg-primary text-white">Register New Member</div>
    <div class="card-body">
      <form method="post" class="row g-3">
        <input type="hidden" name="create_member" value="1">
        <div class="col-md-6">
          <label class="form-label">First Name</label>
          <input type="text" name="first_name" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Last Name</label>
          <input type="text" name="last_name" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Role Assignment</label>
          <select name="role_id" class="form-select" required>
            <?php while ($role = $roles->fetch_assoc()): ?>
              <option value="<?= $role['role_id'] ?>"><?= htmlspecialchars($role['role_type']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-success">Create Member Account</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Existing Members Directory Table -->
  <h2 class="mb-3">Existing Members Directory</h2>
  <table class="table table-striped align-middle">
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
        <td>
          <?php if ($row['is_active']): ?>
            <span class="badge bg-success">Active</span>
          <?php else: ?>
            <span class="badge bg-danger">Inactive</span>
          <?php endif; ?>
        </td>
        <td>
          <form method="post" style="display:inline">
            <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
            <input type="hidden" name="new_status" value="<?= $row['is_active'] ? 0 : 1 ?>">
            <button type="submit" name="toggle_active" class="btn btn-sm <?= $row['is_active'] ? 'btn-warning' : 'btn-success' ?>" onclick="return confirm('Are you sure you want to change this user status?');">
              <?= $row['is_active'] ? 'Deactivate' : 'Reactivate' ?>
            </button>
          </form>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
  
  <a href="index.php" class="btn btn-primary mt-3">Back to Admin Home</a>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>