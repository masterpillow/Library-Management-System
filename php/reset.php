<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include_once(realpath(dirname(__FILE__) . '/path.php'));
include_once(ROOT_SRC_PATH . '/config.php');

$message = '';
$token = $_GET['token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $new_password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($new_password !== $confirm) {
        $message = "Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $message = "Password must be at least 6 characters.";
    } else {
        $stmt = $db_connection->prepare("
            SELECT pr.reset_id, pr.user_id, pr.token_hash
            FROM Password_Resets pr
            WHERE pr.expires_at > NOW() AND pr.used_at IS NULL
        ");
        $stmt->execute();
        $resets = $stmt->get_result();

        $valid = null;
        while ($row = $resets->fetch_assoc()) {
            if (password_verify($token, $row['token_hash'])) {
                $valid = $row;
                break;
            }
        }

        if ($valid) {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $upd = $db_connection->prepare("UPDATE Credentials SET password_hash = ? WHERE user_id = ?");
            $upd->bind_param('si', $hash, $valid['user_id']);
            $upd->execute();

            $mark = $db_connection->prepare("UPDATE Password_Resets SET used_at = NOW() WHERE reset_id = ?");
            $mark->bind_param('i', $valid['reset_id']);
            $mark->execute();

            $message = "Password updated! <a href='../index.php'>Log in</a>";
        } else {
            $message = "Invalid or expired reset link.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Reset Password</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">
  <h1>Reset Password</h1>
  <?php if ($message) echo "<div class='alert alert-info'>$message</div>"; ?>

  <?php if (!$message || strpos($message, 'updated') === false): ?>
  <form method="post">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <div class="mb-3">
      <label>New Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Confirm Password</label>
      <input type="password" name="confirm" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary">Update Password</button>
  </form>
  <?php endif; ?>
</body>
</html>