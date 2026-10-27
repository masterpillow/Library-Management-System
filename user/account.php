<?php
//======================================================================
// MY ACCOUNT PAGE (members)
//======================================================================
// Lets a logged-in member update their contact details, username,
// and password. Every form posts back to this page and then redirects
// (post/redirect/get), so refreshing never resubmits a form.

error_reporting(E_ALL);
ini_set('display_errors', 0); // set to 1 to display errors, 0 to hide them

  /* Quick Paths */
  /* note the 2 after __FILE__, because it's 2 directories deep */
  include_once (realpath(dirname(__FILE__, 2).'/php/session.php')); // sets $user_id and opens $db_connection
  include_once (realpath(dirname(__FILE__, 2).'/php/path.php'));

  /* Check Role */
  include_once (ROOT_SRC_PATH .'/check_user.php');

  /* Page Name */
  $page_name = "account";

//----------------------------------------------------------------------
// Helpers
//----------------------------------------------------------------------

/* One token per session, checked on every form, so another site can't submit these forms for the member */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* Save a message for the next page load, then reload this page */
function finish($type, $text) {
  $_SESSION['account_flash'] = ['type' => $type, 'text' => $text];
  header("location: " . BASE_URL . "/user/account.php");
  exit();
}

$MIN_PASSWORD_LENGTH = 8;

//----------------------------------------------------------------------
// Handle form submissions
//----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    finish('danger', 'Your session expired. Please try again.');
  }

  $action = isset($_POST['action']) ? $_POST['action'] : '';

  /* ---------- Contact details ---------- */
  if ($action === 'profile') {
    $fields = ['first_name', 'last_name', 'email', 'phone', 'street_1', 'street_2', 'city', 'state_code', 'post_code'];
    $in = [];
    foreach ($fields as $f) {
      $in[$f] = isset($_POST[$f]) ? trim($_POST[$f]) : '';
    }

    if ($in['first_name'] === '' || $in['last_name'] === '' || $in['email'] === '' || $in['phone'] === '') {
      finish('danger', 'First name, last name, email, and phone are required.');
    }
    if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
      finish('danger', 'Please enter a valid email address.');
    }

    try {
      $update = $db_connection->prepare(
        "UPDATE Contacts c
         JOIN Users u ON u.contact_id = c.contact_id
         SET c.first_name = ?, c.last_name = ?, c.email = ?, c.phone = ?,
             c.street_1 = ?, c.street_2 = ?, c.city = ?, c.state_code = ?, c.post_code = ?
         WHERE u.user_id = ?");
      $update->bind_param("sssssssssi",
        $in['first_name'], $in['last_name'], $in['email'], $in['phone'],
        $in['street_1'], $in['street_2'], $in['city'], $in['state_code'], $in['post_code'],
        $user_id);
      $update->execute();
      $update->close();
    } catch (mysqli_sql_exception $e) {
      if ($e->getCode() == 1062) { // duplicate key: Contacts.email is unique
        finish('danger', 'That email is already used by another account.');
      }
      finish('danger', 'Your details could not be saved. Please try again.');
    }
    finish('success', 'Your details were updated.');
  }

  /* ---------- Username ---------- */
  if ($action === 'username') {
    $new_username = isset($_POST['username']) ? trim($_POST['username']) : '';

    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $new_username)) {
      finish('danger', 'Usernames are 3 to 50 characters: letters, numbers, dots, dashes, or underscores.');
    }
    if ($new_username === $_SESSION['login_user']) {
      finish('success', 'That is already your username.');
    }

    try {
      $update = $db_connection->prepare("UPDATE Credentials SET username = ? WHERE user_id = ?");
      $update->bind_param("si", $new_username, $user_id);
      $update->execute();
      $update->close();
    } catch (mysqli_sql_exception $e) {
      if ($e->getCode() == 1062) { // duplicate key: username is the primary key
        finish('danger', 'That username is taken.');
      }
      finish('danger', 'Your username could not be changed. Please try again.');
    }
    // session.php looks the member up by this name on every page
    $_SESSION['login_user'] = $new_username;
    finish('success', 'Your username is now ' . $new_username . '.');
  }

  /* ---------- Password ---------- */
  if ($action === 'password') {
    $current = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    $select = $db_connection->prepare("SELECT password_hash FROM Credentials WHERE user_id = ?");
    $select->bind_param("i", $user_id);
    $select->execute();
    $select->bind_result($stored_hash);
    $found = $select->fetch();
    $select->close();

    if (!$found || !password_verify($current, $stored_hash)) {
      finish('danger', 'Your current password is incorrect.');
    }
    if (strlen($new) < $MIN_PASSWORD_LENGTH) {
      finish('danger', 'Your new password must be at least ' . $MIN_PASSWORD_LENGTH . ' characters.');
    }
    if ($new !== $confirm) {
      finish('danger', 'The new passwords do not match.');
    }
    if (password_verify($new, $stored_hash)) {
      finish('danger', 'Your new password must be different from your current one.');
    }

    $new_hash = password_hash($new, PASSWORD_DEFAULT);
    $update = $db_connection->prepare("UPDATE Credentials SET password_hash = ? WHERE user_id = ?");
    $update->bind_param("si", $new_hash, $user_id);
    $update->execute();
    $update->close();

    session_regenerate_id(true); // new session id after a credential change
    finish('success', 'Your password was changed.');
  }

  finish('danger', 'Unknown request.');
}

//----------------------------------------------------------------------
// Load the member's current details for the forms
//----------------------------------------------------------------------
$select = $db_connection->prepare(
  "SELECT c.first_name, c.last_name, c.email, c.phone, c.street_1, c.street_2,
          c.city, c.state_code, c.post_code, cr.username, m.type_name, m.max_loans, m.loan_days
   FROM Users u
   JOIN Contacts c ON u.contact_id = c.contact_id
   JOIN Credentials cr ON cr.user_id = u.user_id
   JOIN Memberships m ON m.membership_id = u.membership_id
   WHERE u.user_id = ?");
$select->bind_param("i", $user_id);
$select->execute();
$me = $select->get_result()->fetch_assoc();
$select->close();

$flash = isset($_SESSION['account_flash']) ? $_SESSION['account_flash'] : null;
unset($_SESSION['account_flash']);

/* Escape for HTML output */
function e($value) {
  return htmlspecialchars((string)$value, ENT_QUOTES);
}
$token = e($_SESSION['csrf_token']);

?>
<!doctype html>
<html lang="en">
  <head>
  <?php include_once (ROOT_PATH . '/include/head.php'); ?>
  </head>
  <body class="<?php echo $page_name; ?>">

  <?php include_once (ROOT_PATH . '/include/header.php'); ?>
    <main role="main" class="container mb-5 pb-5">
      <div class="row justify-content-center">
        <div class="col-lg-8">

          <h1 class="mb-4">My Account</h1>

          <?php if ($flash): ?>
            <div class="alert alert-<?php echo e($flash['type']); ?>" role="alert"><?php echo e($flash['text']); ?></div>
          <?php endif; ?>

          <!-- Membership (read only: admins set this) -->
          <section class="card mb-4">
            <div class="card-body">
              <h2 class="h5 card-title">Membership</h2>
              <p class="mb-0">
                <strong><?php echo e($me['type_name']); ?></strong>:
                up to <?php echo (int)$me['max_loans']; ?> books at a time, for <?php echo (int)$me['loan_days']; ?> days each.
              </p>
            </div>
          </section>

          <!-- Contact details -->
          <section class="card mb-4">
            <div class="card-body">
              <h2 class="h5 card-title mb-3">Contact details</h2>
              <form method="post" action="account.php">
                <input type="hidden" name="csrf_token" value="<?php echo $token; ?>">
                <input type="hidden" name="action" value="profile">
                <div class="row g-3">
                  <div class="col-sm-6">
                    <label for="first_name" class="form-label">First name</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo e($me['first_name']); ?>" required>
                  </div>
                  <div class="col-sm-6">
                    <label for="last_name" class="form-label">Last name</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo e($me['last_name']); ?>" required>
                  </div>
                  <div class="col-sm-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo e($me['email']); ?>" required>
                  </div>
                  <div class="col-sm-6">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo e($me['phone']); ?>" required>
                  </div>
                  <div class="col-12">
                    <label for="street_1" class="form-label">Street address</label>
                    <input type="text" class="form-control" id="street_1" name="street_1" value="<?php echo e($me['street_1']); ?>">
                  </div>
                  <div class="col-12">
                    <label for="street_2" class="form-label">Apartment, suite, etc.</label>
                    <input type="text" class="form-control" id="street_2" name="street_2" value="<?php echo e($me['street_2']); ?>">
                  </div>
                  <div class="col-sm-6">
                    <label for="city" class="form-label">City</label>
                    <input type="text" class="form-control" id="city" name="city" value="<?php echo e($me['city']); ?>">
                  </div>
                  <div class="col-sm-3">
                    <label for="state_code" class="form-label">State</label>
                    <input type="text" class="form-control" id="state_code" name="state_code" value="<?php echo e($me['state_code']); ?>">
                  </div>
                  <div class="col-sm-3">
                    <label for="post_code" class="form-label">Zip code</label>
                    <input type="text" class="form-control" id="post_code" name="post_code" value="<?php echo e($me['post_code']); ?>">
                  </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Save details</button>
              </form>
            </div>
          </section>

          <!-- Username -->
          <section class="card mb-4">
            <div class="card-body">
              <h2 class="h5 card-title mb-3">Username</h2>
              <form method="post" action="account.php">
                <input type="hidden" name="csrf_token" value="<?php echo $token; ?>">
                <input type="hidden" name="action" value="username">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" value="<?php echo e($me['username']); ?>" required>
                <div class="form-text">You'll use this to log in.</div>
                <button type="submit" class="btn btn-primary mt-3">Change username</button>
              </form>
            </div>
          </section>

          <!-- Password -->
          <section class="card mb-4">
            <div class="card-body">
              <h2 class="h5 card-title mb-3">Password</h2>
              <form method="post" action="account.php">
                <input type="hidden" name="csrf_token" value="<?php echo $token; ?>">
                <input type="hidden" name="action" value="password">
                <div class="mb-3">
                  <label for="current_password" class="form-label">Current password</label>
                  <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password" required>
                </div>
                <div class="mb-3">
                  <label for="new_password" class="form-label">New password</label>
                  <input type="password" class="form-control" id="new_password" name="new_password" autocomplete="new-password" minlength="<?php echo $MIN_PASSWORD_LENGTH; ?>" required>
                  <div class="form-text">At least <?php echo $MIN_PASSWORD_LENGTH; ?> characters.</div>
                </div>
                <div class="mb-3">
                  <label for="confirm_password" class="form-label">Confirm new password</label>
                  <input type="password" class="form-control" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="<?php echo $MIN_PASSWORD_LENGTH; ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">Change password</button>
              </form>
            </div>
          </section>

        </div>
      </div>
    </main>
    <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
  </body>
</html>
