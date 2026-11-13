<?php
include_once(__DIR__ . '/config.php');

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
  <?php include_once(ROOT_PATH . '/include/head.php'); ?>
</head>
<body class="member">
  <?php include_once(ROOT_PATH . '/include/header.php'); ?>
  <main class="container">
    <h1>Forgot Password</h1>
    <?php if ($message) echo "<p>$message</p>"; ?>
    <form method="post">
      <label>Email</label>
      <input type="email" name="email" required>
      <button type="submit">Send Reset Link</button>
    </form>
  </main>
</body>
</html>