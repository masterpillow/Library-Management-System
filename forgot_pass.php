<?php
//======================================================================
// FORGOT PASSWORD: request a reset link
//======================================================================
// A real library would email the link. For the demo, the link is shown
// on screen. Only a hash of the token is stored, and it expires in an hour.

include_once (realpath(dirname(__FILE__) . '/php/config.php'));
include_once (realpath(dirname(__FILE__) . '/php/helpers.php'));

$page_name = 'forgot';
$page_title = 'Forgot Password';
$demo_link = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check('forgot_pass.php');
  $email = trim(isset($_POST['email']) ? $_POST['email'] : '');

  $s = $db_connection->prepare("SELECT u.user_id FROM Users u JOIN Contacts c ON c.contact_id = u.contact_id
                                WHERE c.email = ? AND u.is_active = 1");
  $s->bind_param('s', $email);
  $s->execute();
  $user = $s->get_result()->fetch_assoc();
  $s->close();

  if ($user) {
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $i = $db_connection->prepare("INSERT INTO Password_Resets (user_id, token_hash, expires_at)
                                  VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
    $i->bind_param('is', $user['user_id'], $hash);
    $i->execute();
    $i->close();
    $demo_link = url('php/reset.php') . '?token=' . $token;
  }
  // Same message either way, so this page can't be used to find out who has an account
  flash('info', 'If that email belongs to an account, a reset link has been sent. It expires in 1 hour.');
}
?>
<!doctype html>
<html lang="en">
<head>
  <?php include_once (ROOT_PATH . '/include/head.php'); ?>
</head>
<body>
  <?php include_once (ROOT_PATH . '/include/header.php'); ?>
  <main class="container">
    <div class="auth-card mx-auto">
      <?php show_flashes(); ?>
      <?php if ($demo_link): ?>
        <div class="alert alert-secondary small">
          <strong>Demo only:</strong> email isn't set up, so here is the link that would be sent:<br>
          <a href="<?php echo e($demo_link); ?>">Reset your password</a>
        </div>
      <?php endif; ?>
      <div class="card shadow-sm border-0"><div class="card-body p-4">
        <h1 class="h3 mb-1">Forgot your password?</h1>
        <p class="text-secondary">Enter the email on your account and we'll send you a link to set a new one.</p>
        <form method="post" action="forgot_pass.php">
          <?php echo csrf_field(); ?>
          <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email" autocomplete="email" required>
          </div>
          <div class="d-grid"><button type="submit" class="btn btn-library">Send reset link</button></div>
        </form>
        <hr>
        <p class="text-center mb-0 small"><a href="index.php">Back to log in</a></p>
      </div></div>
    </div>
  </main>
  <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
</body>
</html>
