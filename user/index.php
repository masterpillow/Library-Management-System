<?php
//======================================================================
// MEMBER DASHBOARD
//======================================================================
include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));
require_role(ROLE_MEMBER);

$page_name = 'user';
$page_title = 'Dashboard';

expire_old_holds($db_connection);

$s = $db_connection->prepare(
  "SELECT ms.type_name, ms.max_loans,
          (SELECT COUNT(*) FROM Loans l WHERE l.user_id = u.user_id AND l.return_date IS NULL) AS open_loans,
          (SELECT COUNT(*) FROM Loans l WHERE l.user_id = u.user_id AND l.return_date IS NULL AND DATEDIFF(NOW(), l.due_date) > 0) AS overdue,
          (SELECT COUNT(*) FROM Loans l WHERE l.user_id = u.user_id AND l.return_date IS NULL
             AND l.due_date >= NOW() AND l.due_date < DATE_ADD(NOW(), INTERVAL 3 DAY)) AS due_soon,
          (SELECT COUNT(*) FROM Holds h WHERE h.user_id = u.user_id AND h.status = 'ready') AS holds_ready,
          (SELECT COUNT(*) FROM Holds h WHERE h.user_id = u.user_id AND h.status = 'waiting') AS holds_waiting,
          (SELECT COALESCE(SUM(l.fine), 0) FROM Loans l WHERE l.user_id = u.user_id) AS fines
   FROM Users u JOIN Memberships ms ON ms.membership_id = u.membership_id
   WHERE u.user_id = ?");
$s->bind_param('i', $user_id);
$s->execute();
$me = $s->get_result()->fetch_assoc();
$s->close();

/* A few staff picks to suggest */
$picks = $db_connection->query(
  "SELECT b.title, sp.review_blurb,
          GROUP_CONCAT(CONCAT(a.first_name, ' ', a.last_name) SEPARATOR ', ') AS authors
   FROM Staff_Picks sp
   JOIN Books b ON b.book_id = sp.book_id AND b.is_active = 1
   LEFT JOIN Book_Authors ba ON ba.book_id = b.book_id
   LEFT JOIN Authors a ON a.author_id = ba.author_id
   GROUP BY sp.pick_id
   ORDER BY sp.pick_date DESC
   LIMIT 3")->fetch_all(MYSQLI_ASSOC);
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
      <p class="text-secondary mb-0"><?php echo e($me['type_name']); ?> member</p>
    </div>

    <?php if ($me['holds_ready'] > 0): ?>
      <div class="alert alert-success">
        <i class="bi bi-bell me-1"></i>
        <?php echo (int)$me['holds_ready'] === 1 ? 'A book you put on hold is' : (int)$me['holds_ready'] . ' books you put on hold are'; ?>
        ready for you. <a href="loans.php" class="alert-link">Check it out on My Loans</a> within <?php echo HOLD_PICKUP_DAYS; ?> days.
      </div>
    <?php endif; ?>
    <?php if ($me['overdue'] > 0): ?>
      <div class="alert alert-danger">
        <i class="bi bi-exclamation-octagon me-1"></i>
        You have <?php echo (int)$me['overdue']; ?> overdue <?php echo $me['overdue'] == 1 ? 'book' : 'books'; ?>.
        Late books cost $<?php echo number_format(FINE_PER_DAY, 2); ?> per day. <a href="loans.php" class="alert-link">See My Loans</a>.
      </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3">
        <div class="card stat-card h-100 shadow-sm border-0"><div class="card-body">
          <div class="text-secondary small">Books out</div>
          <div class="stat-value"><?php echo (int)$me['open_loans']; ?> <small class="fs-6 text-secondary">of <?php echo (int)$me['max_loans']; ?></small></div>
        </div></div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card stat-card h-100 shadow-sm border-0"><div class="card-body">
          <div class="text-secondary small">Due in the next 3 days</div>
          <div class="stat-value"><?php echo (int)$me['due_soon']; ?></div>
        </div></div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card stat-card h-100 shadow-sm border-0"><div class="card-body">
          <div class="text-secondary small">Holds</div>
          <div class="stat-value"><?php echo (int)$me['holds_ready'] + (int)$me['holds_waiting']; ?></div>
          <?php if ($me['holds_ready'] > 0): ?><small class="text-success"><?php echo (int)$me['holds_ready']; ?> ready</small><?php endif; ?>
        </div></div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card stat-card h-100 shadow-sm border-0"><div class="card-body">
          <div class="text-secondary small">Fines owed</div>
          <div class="stat-value<?php echo $me['fines'] > 0 ? ' text-danger' : ''; ?>">$<?php echo number_format($me['fines'], 2); ?></div>
        </div></div>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <a class="card h-100 shadow-sm border-0 text-decoration-none" href="search.php"><div class="card-body">
          <h2 class="h5"><i class="bi bi-search me-2 text-success"></i>Catalog</h2>
          <p class="text-secondary mb-0">Search by title, author, or genre, then check out or place a hold.</p>
        </div></a>
      </div>
      <div class="col-md-4">
        <a class="card h-100 shadow-sm border-0 text-decoration-none" href="loans.php"><div class="card-body">
          <h2 class="h5"><i class="bi bi-journal-bookmark me-2 text-success"></i>My Loans</h2>
          <p class="text-secondary mb-0">Due dates, holds, fines, and books you can rate.</p>
        </div></a>
      </div>
      <div class="col-md-4">
        <a class="card h-100 shadow-sm border-0 text-decoration-none" href="account.php"><div class="card-body">
          <h2 class="h5"><i class="bi bi-person-circle me-2 text-success"></i>My Account</h2>
          <p class="text-secondary mb-0">Update your contact details, username, or password.</p>
        </div></a>
      </div>
    </div>

    <?php if ($picks): ?>
      <section>
        <h2 class="h4">Staff picks</h2>
        <div class="row g-3">
          <?php foreach ($picks as $p): ?>
            <div class="col-md-4">
              <div class="card h-100 shadow-sm border-0"><div class="card-body">
                <h3 class="h6 mb-1"><?php echo e($p['title']); ?></h3>
                <div class="small text-secondary mb-2"><?php echo e($p['authors'] !== null ? $p['authors'] : 'Unknown author'); ?></div>
                <?php if ($p['review_blurb']): ?><p class="small fst-italic mb-0">"<?php echo e($p['review_blurb']); ?>"</p><?php endif; ?>
              </div></div>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="mt-2"><a href="search.php?mode=staff">See all staff picks</a></p>
      </section>
    <?php endif; ?>
  </main>
  <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
</body>
</html>
