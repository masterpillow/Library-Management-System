<?php
//======================================================================
// GUEST DASHBOARD: guests can browse the catalog but not borrow
//======================================================================
include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));
require_role(ROLE_GUEST);

$page_name = 'guest';
$page_title = 'Guest';

$counts = $db_connection->query(
  "SELECT (SELECT COUNT(*) FROM Books WHERE is_active = 1) AS books,
          (SELECT COUNT(*) FROM Copies WHERE status = 'available') AS on_shelf")->fetch_assoc();
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
    <div class="page-header">
      <h1>Welcome, <?php echo e($_SESSION['user_first']); ?>!</h1>
      <p class="text-secondary mb-0">You have a guest account. You can browse the catalog, but borrowing books needs a membership.</p>
    </div>
    <div class="row g-3">
      <div class="col-md-6">
        <a class="card h-100 shadow-sm border-0 text-decoration-none" href="<?php echo e(url('user/search.php')); ?>"><div class="card-body">
          <h2 class="h5"><i class="bi bi-search me-2 text-success"></i>Browse the catalog</h2>
          <p class="text-secondary mb-0"><?php echo (int)$counts['books']; ?> books, <?php echo (int)$counts['on_shelf']; ?> copies on the shelf right now.</p>
        </div></a>
      </div>
      <div class="col-md-6">
        <div class="card h-100 shadow-sm border-0"><div class="card-body">
          <h2 class="h5"><i class="bi bi-person-badge me-2 text-success"></i>Want to borrow books?</h2>
          <p class="text-secondary mb-0">Ask a librarian to upgrade your account to a membership.</p>
        </div></div>
      </div>
    </div>
  </main>
  <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
</body>
</html>
