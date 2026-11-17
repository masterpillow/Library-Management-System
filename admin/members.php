<?php
//======================================================================
// ADMIN: manage member accounts
//======================================================================
// Create accounts, edit details, change role and membership type, reset a
// password, and deactivate or reactivate. Accounts are never deleted, so
// loan history stays intact. Admins can't lock themselves out.

include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));
require_role(ROLE_ADMIN);

$page_name = 'members';
$page_title = 'Manage Members';

$roles = $db_connection->query("SELECT role_id, role_type FROM Roles ORDER BY role_id")->fetch_all(MYSQLI_ASSOC);
$memberships = $db_connection->query("SELECT membership_id, type_name, max_loans, loan_days FROM Memberships ORDER BY membership_id")->fetch_all(MYSQLI_ASSOC);

/* Read and validate the create/edit form. Returns [fields, error] */
function member_form_input($creating) {
  $f = [];
  foreach (['first_name', 'last_name', 'email', 'phone', 'username'] as $k) {
    $f[$k] = trim(isset($_POST[$k]) ? $_POST[$k] : '');
  }
  $f['password'] = isset($_POST['password']) ? $_POST['password'] : '';
  $f['role_id'] = (int)(isset($_POST['role_id']) ? $_POST['role_id'] : ROLE_MEMBER);
  $f['membership_id'] = (int)(isset($_POST['membership_id']) ? $_POST['membership_id'] : 1);

  if ($f['first_name'] === '' || $f['last_name'] === '' || $f['email'] === '' || $f['phone'] === '' || $f['username'] === '') {
    return [null, 'First name, last name, email, phone, and username are required.'];
  }
  if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
    return [null, 'Please enter a valid email address.'];
  }
  if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $f['username'])) {
    return [null, 'Usernames are 3 to 50 characters: letters, numbers, dots, dashes, or underscores.'];
  }
  if (($creating || $f['password'] !== '') && strlen($f['password']) < MIN_PASSWORD_LENGTH) {
    return [null, 'Passwords must be at least ' . MIN_PASSWORD_LENGTH . ' characters.'];
  }
  if (!in_array($f['role_id'], [ROLE_ADMIN, ROLE_MEMBER, ROLE_GUEST], true)) {
    return [null, 'Pick a role.'];
  }
  return [$f, null];
}

function duplicate_message(mysqli_sql_exception $ex) {
  if ($ex->getCode() == 1062) {
    return stripos($ex->getMessage(), 'email') !== false ? 'Another account already uses that email.' : 'That username is taken.';
  }
  return 'The account could not be saved. Please try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check('admin/members.php');
  $action = isset($_POST['action']) ? $_POST['action'] : '';

  if ($action === 'create') {
    list($f, $err) = member_form_input(true);
    if ($err) {
      flash('danger', $err);
      redirect('admin/members.php');
    }
    $db_connection->begin_transaction();
    try {
      $c = $db_connection->prepare("INSERT INTO Contacts (first_name, last_name, email, phone) VALUES (?, ?, ?, ?)");
      $c->bind_param('ssss', $f['first_name'], $f['last_name'], $f['email'], $f['phone']);
      $c->execute();
      $contact_id = $c->insert_id;
      $c->close();

      $u = $db_connection->prepare("INSERT INTO Users (role_id, contact_id, membership_id, is_active) VALUES (?, ?, ?, 1)");
      $u->bind_param('iii', $f['role_id'], $contact_id, $f['membership_id']);
      $u->execute();
      $new_id = $u->insert_id;
      $u->close();

      $hash = password_hash($f['password'], PASSWORD_DEFAULT);
      $cr = $db_connection->prepare("INSERT INTO Credentials (username, user_id, password_hash) VALUES (?, ?, ?)");
      $cr->bind_param('sis', $f['username'], $new_id, $hash);
      $cr->execute();
      $cr->close();

      $db_connection->commit();
      flash('success', 'Created an account for ' . $f['first_name'] . ' ' . $f['last_name'] . ' (username ' . $f['username'] . ').');
    } catch (mysqli_sql_exception $ex) {
      $db_connection->rollback();
      flash('danger', duplicate_message($ex));
    }
    redirect('admin/members.php');
  }

  if ($action === 'update') {
    $edit_id = (int)$_POST['user_id'];
    list($f, $err) = member_form_input(false);
    if ($err) {
      flash('danger', $err);
      redirect('admin/members.php?edit_id=' . $edit_id);
    }
    if ($edit_id === (int)$user_id && $f['role_id'] !== ROLE_ADMIN) {
      flash('danger', "You can't remove your own administrator role.");
      redirect('admin/members.php?edit_id=' . $edit_id);
    }
    $db_connection->begin_transaction();
    try {
      $c = $db_connection->prepare("UPDATE Contacts c JOIN Users u ON u.contact_id = c.contact_id
                                    SET c.first_name = ?, c.last_name = ?, c.email = ?, c.phone = ?
                                    WHERE u.user_id = ?");
      $c->bind_param('ssssi', $f['first_name'], $f['last_name'], $f['email'], $f['phone'], $edit_id);
      $c->execute();
      $c->close();

      $u = $db_connection->prepare("UPDATE Users SET role_id = ?, membership_id = ? WHERE user_id = ?");
      $u->bind_param('iii', $f['role_id'], $f['membership_id'], $edit_id);
      $u->execute();
      $u->close();

      $cr = $db_connection->prepare("UPDATE Credentials SET username = ? WHERE user_id = ?");
      $cr->bind_param('si', $f['username'], $edit_id);
      $cr->execute();
      $cr->close();

      if ($f['password'] !== '') {
        $hash = password_hash($f['password'], PASSWORD_DEFAULT);
        $p = $db_connection->prepare("UPDATE Credentials SET password_hash = ? WHERE user_id = ?");
        $p->bind_param('si', $hash, $edit_id);
        $p->execute();
        $p->close();
      }
      $db_connection->commit();

      // Editing your own username: keep the session pointing at the new name
      if ($edit_id === (int)$user_id) {
        $_SESSION['login_user'] = $f['username'];
      }
      flash('success', 'Saved changes to ' . $f['first_name'] . ' ' . $f['last_name'] . '.' . ($f['password'] !== '' ? ' Their password was reset.' : ''));
      redirect('admin/members.php');
    } catch (mysqli_sql_exception $ex) {
      $db_connection->rollback();
      flash('danger', duplicate_message($ex));
      redirect('admin/members.php?edit_id=' . $edit_id);
    }
  }

  if ($action === 'toggle') {
    $target = (int)$_POST['user_id'];
    $active = (int)$_POST['active'] === 1 ? 1 : 0;
    if ($target === (int)$user_id) {
      flash('danger', "You can't deactivate your own account.");
    } else {
      $s = $db_connection->prepare("UPDATE Users SET is_active = ? WHERE user_id = ?");
      $s->bind_param('ii', $active, $target);
      $s->execute();
      $s->close();
      flash('success', $active ? 'The account was reactivated.' : 'The account was deactivated. They can no longer log in, and their history is kept.');
    }
    redirect('admin/members.php');
  }
  redirect('admin/members.php');
}

/* Member being edited, if any */
$edit = null;
if (isset($_GET['edit_id'])) {
  $s = $db_connection->prepare(
    "SELECT u.user_id, u.role_id, u.membership_id, c.first_name, c.last_name, c.email, c.phone, cr.username
     FROM Users u JOIN Contacts c ON c.contact_id = u.contact_id JOIN Credentials cr ON cr.user_id = u.user_id
     WHERE u.user_id = ?");
  $edit_id = (int)$_GET['edit_id'];
  $s->bind_param('i', $edit_id);
  $s->execute();
  $edit = $s->get_result()->fetch_assoc();
  $s->close();
}

$search = trim(isset($_GET['search']) ? $_GET['search'] : '');
$sql = "SELECT u.user_id, u.is_active, u.role_id, c.first_name, c.last_name, c.email, cr.username,
               r.role_type, m.type_name,
               (SELECT COUNT(*) FROM Loans l WHERE l.user_id = u.user_id AND l.return_date IS NULL) AS loans_out,
               (SELECT COALESCE(SUM(l.fine), 0) FROM Loans l WHERE l.user_id = u.user_id) AS fines
        FROM Users u
        JOIN Contacts c ON c.contact_id = u.contact_id
        JOIN Credentials cr ON cr.user_id = u.user_id
        JOIN Roles r ON r.role_id = u.role_id
        JOIN Memberships m ON m.membership_id = u.membership_id";
if ($search !== '') {
  $sql .= " WHERE CONCAT(c.first_name, ' ', c.last_name) LIKE ? OR c.email LIKE ? OR cr.username LIKE ?";
}
$sql .= " ORDER BY u.is_active DESC, c.last_name, c.first_name";
$s = $db_connection->prepare($sql);
if ($search !== '') {
  $like = '%' . $search . '%';
  $s->bind_param('sss', $like, $like, $like);
}
$s->execute();
$people = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$s->close();

/* Shared create/edit form fields */
function member_fields($roles, $memberships, $m = null) {
  $v = function ($k) use ($m) { return $m ? e($m[$k]) : ''; };
  $p = $m ? 'e' : 'n';
  ob_start(); ?>
  <div class="col-md-6"><label class="form-label" for="<?php echo $p; ?>first">First name</label>
    <input type="text" class="form-control" id="<?php echo $p; ?>first" name="first_name" value="<?php echo $v('first_name'); ?>" required></div>
  <div class="col-md-6"><label class="form-label" for="<?php echo $p; ?>last">Last name</label>
    <input type="text" class="form-control" id="<?php echo $p; ?>last" name="last_name" value="<?php echo $v('last_name'); ?>" required></div>
  <div class="col-md-6"><label class="form-label" for="<?php echo $p; ?>email">Email</label>
    <input type="email" class="form-control" id="<?php echo $p; ?>email" name="email" value="<?php echo $v('email'); ?>" required></div>
  <div class="col-md-6"><label class="form-label" for="<?php echo $p; ?>phone">Phone</label>
    <input type="tel" class="form-control" id="<?php echo $p; ?>phone" name="phone" value="<?php echo $v('phone'); ?>" required></div>
  <div class="col-md-6"><label class="form-label" for="<?php echo $p; ?>user">Username</label>
    <input type="text" class="form-control" id="<?php echo $p; ?>user" name="username" value="<?php echo $v('username'); ?>" required></div>
  <div class="col-md-6"><label class="form-label" for="<?php echo $p; ?>pass"><?php echo $m ? 'New password' : 'Password'; ?></label>
    <input type="password" class="form-control" id="<?php echo $p; ?>pass" name="password" autocomplete="new-password"
           minlength="<?php echo MIN_PASSWORD_LENGTH; ?>" <?php echo $m ? '' : 'required'; ?>>
    <div class="form-text"><?php echo $m ? 'Leave blank to keep their current password.' : 'At least ' . MIN_PASSWORD_LENGTH . ' characters.'; ?></div></div>
  <div class="col-md-6"><label class="form-label" for="<?php echo $p; ?>role">Role</label>
    <select class="form-select" id="<?php echo $p; ?>role" name="role_id">
      <?php foreach ($roles as $r): ?>
        <option value="<?php echo (int)$r['role_id']; ?>" <?php echo ($m ? (int)$m['role_id'] : ROLE_MEMBER) === (int)$r['role_id'] ? 'selected' : ''; ?>><?php echo e(ucfirst($r['role_type'] === 'user' ? 'member' : $r['role_type'])); ?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="col-md-6"><label class="form-label" for="<?php echo $p; ?>ms">Membership</label>
    <select class="form-select" id="<?php echo $p; ?>ms" name="membership_id">
      <?php foreach ($memberships as $ms): ?>
        <option value="<?php echo (int)$ms['membership_id']; ?>" <?php echo ($m ? (int)$m['membership_id'] : 1) === (int)$ms['membership_id'] ? 'selected' : ''; ?>>
          <?php echo e($ms['type_name']); ?> (<?php echo (int)$ms['max_loans']; ?> books, <?php echo (int)$ms['loan_days']; ?> days)
        </option>
      <?php endforeach; ?>
    </select></div>
  <?php return ob_get_clean();
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
    <?php show_flashes(); ?>

    <div class="page-header d-flex flex-wrap justify-content-between align-items-end gap-2">
      <div>
        <h1>Members</h1>
        <p class="text-secondary mb-0">Create accounts, update details and memberships, and deactivate accounts that shouldn't log in.</p>
      </div>
      <?php if (!$edit): ?>
        <button class="btn btn-library" type="button" data-bs-toggle="collapse" data-bs-target="#addMember" aria-expanded="false" aria-controls="addMember">
          <i class="bi bi-person-plus me-1"></i>New account
        </button>
      <?php endif; ?>
    </div>

    <?php if ($edit): ?>
      <div class="card shadow-sm border-0 mb-4"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="h5 mb-0">Edit <?php echo e($edit['first_name'] . ' ' . $edit['last_name']); ?></h2>
          <a href="members.php" class="btn btn-sm btn-outline-secondary">Cancel</a>
        </div>
        <form method="post" class="row g-3">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="user_id" value="<?php echo (int)$edit['user_id']; ?>">
          <?php echo member_fields($roles, $memberships, $edit); ?>
          <div class="col-12"><button type="submit" class="btn btn-library">Save changes</button></div>
        </form>
      </div></div>
    <?php else: ?>
      <div class="collapse mb-4" id="addMember">
        <div class="card shadow-sm border-0"><div class="card-body">
          <h2 class="h5 mb-3">New account</h2>
          <form method="post" class="row g-3">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create">
            <?php echo member_fields($roles, $memberships); ?>
            <div class="col-12"><button type="submit" class="btn btn-library">Create account</button></div>
          </form>
        </div></div>
      </div>
    <?php endif; ?>

    <form method="get" class="row g-2 mb-3">
      <div class="col-sm-9 col-lg-10">
        <input type="search" class="form-control" name="search" value="<?php echo e($search); ?>" placeholder="Search by name, email, or username" aria-label="Search members">
      </div>
      <div class="col-sm-3 col-lg-2 d-grid"><button class="btn btn-outline-secondary" type="submit">Search</button></div>
    </form>

    <div class="table-responsive bg-white rounded shadow-sm">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Name</th><th>Username</th><th>Role</th><th>Membership</th><th>Books out</th><th>Fines</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php if (!$people): ?>
          <tr><td colspan="8" class="text-center text-secondary py-4">No accounts found.</td></tr>
        <?php endif; ?>
        <?php foreach ($people as $p): ?>
          <tr class="<?php echo $p['is_active'] ? '' : 'table-secondary'; ?>">
            <td><div class="fw-semibold"><?php echo e($p['first_name'] . ' ' . $p['last_name']); ?></div><small class="text-secondary"><?php echo e($p['email']); ?></small></td>
            <td><?php echo e($p['username']); ?></td>
            <td><?php echo e(ucfirst($p['role_type'] === 'user' ? 'member' : $p['role_type'])); ?></td>
            <td><?php echo e($p['type_name']); ?></td>
            <td><?php echo (int)$p['loans_out']; ?></td>
            <td><?php echo $p['fines'] > 0 ? '<span class="text-danger">$' . number_format($p['fines'], 2) . '</span>' : '<span class="text-secondary">None</span>'; ?></td>
            <td><?php echo $p['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Deactivated</span>'; ?></td>
            <td class="text-end text-nowrap">
              <a href="members.php?edit_id=<?php echo (int)$p['user_id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
              <?php if ((int)$p['user_id'] !== (int)$user_id): ?>
                <form method="post" class="d-inline"
                      data-confirm="<?php echo $p['is_active'] ? 'Deactivate this account? They will not be able to log in.' : 'Reactivate this account?'; ?>">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="user_id" value="<?php echo (int)$p['user_id']; ?>">
                  <input type="hidden" name="active" value="<?php echo $p['is_active'] ? 0 : 1; ?>">
                  <button type="submit" class="btn btn-sm <?php echo $p['is_active'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>"><?php echo $p['is_active'] ? 'Deactivate' : 'Reactivate'; ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
  <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
</body>
</html>
