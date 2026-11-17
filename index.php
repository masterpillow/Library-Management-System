<?php
//======================================================================
// LOGIN PAGE
//======================================================================
include_once (realpath(dirname(__FILE__) . '/php/helpers.php'));

// Already logged in? Go straight to your dashboard
if (!empty($_SESSION['user_role']) && !empty($_SESSION['login_user'])) {
  redirect(home_for_role($_SESSION['user_role']));
}

$page_name = 'home';
$page_title = 'Log In';
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
        <h1 class="h3 mb-1">Log in</h1>
        <p class="text-secondary">Search the catalog, check out books, and keep track of your loans.</p>
        <form action="php/authenticate.php" method="post">
          <?php echo csrf_field(); ?>
          <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username" autocomplete="username" required autofocus>
          </div>
          <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
          </div>
          <div class="d-grid"><button type="submit" class="btn btn-library">Log in</button></div>
        </form>
        <hr>
        <p class="text-center mb-0 small">
          <a href="create_account.php">Create an account</a> &middot; <a href="forgot_pass.php">Forgot password?</a>
        </p>
      </div></div>
    </div>
  </main>
  <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
</body>
</html>
