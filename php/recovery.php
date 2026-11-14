<?php
include_once(realpath(dirname(__FILE__) . '/path.php'));
include_once(ROOT_SRC_PATH . '/config.php');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email) {
        $stmt = $db_connection->prepare("SELECT u.user_id FROM Users u JOIN Contacts c ON u.contact_id = c.contact_id WHERE c.email = ? AND u.is_active = 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $hash = password_hash($token, PASSWORD_DEFAULT);
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $ins = $db_connection->prepare("INSERT INTO Password_Resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
            $ins->bind_param('iss', $user['user_id'], $hash, $expires);
            $ins->execute();
            $message = "Reset link (demo): <a href='reset.php?token=$token'>Click here to reset</a>";
        } else {
            $message = "If that email exists, a reset link has been generated.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Forgot Password</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">
  <h1>Forgot Password</h1>
  <?php if ($message) echo "<div class='alert alert-info'>$message</div>"; ?>
  <form method="post" class="mt-3">
    <div class="mb-3">
      <label>Email</label>
      <input type="email" name="email" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary">Send Reset Link</button>
  </form>
  <p class="mt-3"><a href="../index.php">Back to login</a></p>
</body>
</html>