<?php
//======================================================================
// CREATE ACCOUNT PAGE: anyone can sign up and becomes a library member
//======================================================================
include_once (realpath(dirname(__FILE__) . '/php/helpers.php'));

$page_name = 'signup';
$page_title = 'Create Account';

// Values from a form that had an error, so nothing has to be retyped
$old = isset($_SESSION['signup_old']) ? $_SESSION['signup_old'] : [];
unset($_SESSION['signup_old']);
$v = function ($k) use ($old) { return isset($old[$k]) ? e($old[$k]) : ''; };
?>
<!doctype html>
<html lang="en">
<head>
  <?php include_once (ROOT_PATH . '/include/head.php'); ?>
</head>
<body>
  <?php include_once (ROOT_PATH . '/include/header.php'); ?>
  <main class="container">
    <div class="mx-auto" style="max-width: 640px;">
      <?php show_flashes(); ?>
      <div class="card shadow-sm border-0"><div class="card-body p-4">
        <h1 class="h3 mb-1">Create an account</h1>
        <p class="text-secondary">New accounts are library members with a Standard membership.</p>
        <form action="php/create_account.php" method="post" class="row g-3">
          <?php echo csrf_field(); ?>
          <div class="col-sm-6"><label class="form-label" for="first_name">First name</label>
            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo $v('first_name'); ?>" required></div>
          <div class="col-sm-6"><label class="form-label" for="last_name">Last name</label>
            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo $v('last_name'); ?>" required></div>
          <div class="col-sm-6"><label class="form-label" for="email">Email</label>
            <input type="email" class="form-control" id="email" name="email" value="<?php echo $v('email'); ?>" required></div>
          <div class="col-sm-6"><label class="form-label" for="phone">Phone</label>
            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo $v('phone'); ?>" required></div>
          <div class="col-sm-6"><label class="form-label" for="username">Username</label>
            <input type="text" class="form-control" id="username" name="username" value="<?php echo $v('username'); ?>" autocomplete="username" required>
            <div class="form-text">3 to 50 letters, numbers, dots, dashes, or underscores.</div></div>
          <div class="col-sm-6"><label class="form-label" for="password">Password</label>
            <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" minlength="<?php echo MIN_PASSWORD_LENGTH; ?>" required>
            <div class="form-text">At least <?php echo MIN_PASSWORD_LENGTH; ?> characters.</div></div>
          <div class="col-12"><label class="form-label" for="street">Street address <span class="text-secondary">(optional)</span></label>
            <input type="text" class="form-control" id="street" name="street" value="<?php echo $v('street'); ?>"></div>
          <div class="col-sm-6"><label class="form-label" for="city">City</label>
            <input type="text" class="form-control" id="city" name="city" value="<?php echo $v('city'); ?>"></div>
          <div class="col-sm-3"><label class="form-label" for="state">State</label>
            <input type="text" class="form-control" id="state" name="state" value="<?php echo $v('state'); ?>"></div>
          <div class="col-sm-3"><label class="form-label" for="zip">Zip</label>
            <input type="text" class="form-control" id="zip" name="zip" value="<?php echo $v('zip'); ?>"></div>
          <div class="col-12 d-grid"><button type="submit" class="btn btn-library">Create account</button></div>
        </form>
        <hr>
        <p class="text-center mb-0 small">Already have an account? <a href="index.php">Log in</a></p>
      </div></div>
    </div>
  </main>
  <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
</body>
</html>
