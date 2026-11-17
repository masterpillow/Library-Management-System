<?php
//======================================================================
// RESET PASSWORD: set a new password from an emailed link
//======================================================================
include_once (__DIR__ . '/config.php');
include_once (__DIR__ . '/helpers.php');

$page_name = 'forgot';
$page_title = 'Reset Password';

$token = isset($_POST['token']) ? $_POST['token'] : (isset($_GET['token']) ? $_GET['token'] : '');
$token = is_string($token) ? $token : '';

/* Find an unused, unexpired reset for this token */
function find_reset(mysqli $db, $token) {
  if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    return null;
  }
  $hash = hash('sha256', $token);
  $s = $db->prepare("SELECT reset_id, user_id FROM Password_Resets
                     WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1");
  $s->bind_param('s', $hash);
  $s->execute();
  $row = $s->get_result()->fetch_assoc();
  $s->close();
  return $row;
}

$reset = find_reset($db_connection, $token);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
  csrf_check('php/reset.php?token=' . urlencode($token));
  $new = isset($_POST['password']) ? $_POST['password'] : '';
  $confirm = isset($_POST['confirm']) ? $_POST['confirm'] : '';

  if (strlen($new) < MIN_PASSWORD_LENGTH) {
    flash('danger', 'Your new password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.');
  } elseif ($new !== $confirm) {
    flash('danger', 'The passwords do not match.');
  } else {
    $db_connection->begin_transaction();
    $hash = password_hash($new, PASSWORD_DEFAULT);
    $u = $db_connection->prepare("UPDATE Credentials SET password_hash = ? WHERE user_id = ?");
    $u->bind_param('si', $hash, $reset['user_id']);
    $u->execute();
    $u->close();
    // This link and any other open links for the account stop working
    $m = $db_connection->prepare("UPDATE Password_Resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL");
    $m->bind_param('i', $reset['user_id']);
    $m->execute();
    $m->close();
    $db_connection->commit();

    flash('success', 'Your password was changed. Log in with your new password.');
    redirect('index.php');
  }
  redirect('php/reset.php?token=' . urlencode($token));
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
      <div class="card shadow-sm border-0"><div class="card-body p-4">
        <h1 class="h3 mb-3">Choose a new password</h1>
        <?php if (!$reset): ?>
          <p>This reset link is invalid, already used, or expired.</p>
          <a href="<?php echo e(url('forgot_pass.php')); ?>" class="btn btn-library">Request a new link</a>
        <?php else: ?>
          <form method="post" action="<?php echo e(url('php/reset.php')); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="token" value="<?php echo e($token); ?>">
            <div class="mb-3">
              <label for="password" class="form-label">New password</label>
              <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" minlength="<?php echo MIN_PASSWORD_LENGTH; ?>" required>
              <div class="form-text">At least <?php echo MIN_PASSWORD_LENGTH; ?> characters.</div>
            </div>
            <div class="mb-3">
              <label for="confirm" class="form-label">Confirm new password</label>
              <input type="password" class="form-control" id="confirm" name="confirm" autocomplete="new-password" minlength="<?php echo MIN_PASSWORD_LENGTH; ?>" required>
            </div>
            <div class="d-grid"><button type="submit" class="btn btn-library">Change password</button></div>
          </form>
        <?php endif; ?>
      </div></div>
    </div>
  </main>
  <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
</body>
</html>
