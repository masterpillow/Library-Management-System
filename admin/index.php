<?php
//======================================================================
// ADMIN DASHBOARD
//======================================================================
include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));
require_role(ROLE_ADMIN);

$page_name = 'admin';
$page_title = 'Admin Dashboard';

expire_old_holds($db_connection);

$stats = $db_connection->query(
  "SELECT
     (SELECT COUNT(*) FROM Books WHERE is_active = 1) AS books,
     (SELECT COUNT(*) FROM Copies WHERE status IN ('available', 'on_loan', 'on_hold')) AS copies,
     (SELECT COUNT(*) FROM Copies WHERE status = 'available') AS on_shelf,
     (SELECT COUNT(*) FROM Loans WHERE return_date IS NULL) AS loans_out,
     (SELECT COUNT(*) FROM Loans WHERE return_date IS NULL AND DATEDIFF(NOW(), due_date) > 0) AS overdue,
     (SELECT COUNT(*) FROM Holds WHERE status = 'waiting') AS holds_waiting,
     (SELECT COUNT(*) FROM Holds WHERE status = 'ready') AS holds_ready,
     (SELECT COUNT(*) FROM Users WHERE role_id = " . ROLE_MEMBER . " AND is_active = 1) AS members,
     (SELECT COALESCE(SUM(fine), 0) FROM Loans) AS fines")->fetch_assoc();

$overdue = $db_connection->query(
  "SELECT b.title, c.barcode, co.first_name, co.last_name, l.due_date, " . DAYS_LATE_SQL . " AS days_late
   FROM Loans l
   JOIN Copies c ON c.copy_id = l.copy_id
   JOIN Books b ON b.book_id = c.book_id
   JOIN Users u ON u.user_id = l.user_id
   JOIN Contacts co ON co.contact_id = u.contact_id
   WHERE l.return_date IS NULL AND DATEDIFF(NOW(), l.due_date) > 0
   ORDER BY l.due_date
   LIMIT 10")->fetch_all(MYSQLI_ASSOC);

$cards = [
  ['Active books',        $stats['books'],                                  'book',              'books.php'],
  ['Copies on the shelf', $stats['on_shelf'] . ' / ' . $stats['copies'],   'upc-scan',          'copies.php'],
  ['Books checked out',   $stats['loans_out'],                              'journal-arrow-up',  'returns.php'],
  ['Overdue',             $stats['overdue'],                                'exclamation-octagon','returns.php'],
  ['Holds waiting',       $stats['holds_waiting'],                          'hourglass-split',   null],
  ['Holds ready for pickup', $stats['holds_ready'],                         'bell',              null],
  ['Active members',      $stats['members'],                                'people',            'members.php'],
  ['Unpaid fines',        '$' . number_format($stats['fines'], 2),          'cash-coin',         null],
];
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
      <h1>Welcome, <?php echo e($_SESSION['user_first']); ?></h1>
      <p class="text-secondary mb-0">Here's where the library stands today.</p>
    </div>

    <div class="row g-3 mb-4">
      <?php foreach ($cards as $c): ?>
        <div class="col-6 col-lg-3">
          <?php if ($c[3]): ?><a class="text-decoration-none" href="<?php echo e($c[3]); ?>"><?php endif; ?>
          <div class="card stat-card h-100 shadow-sm border-0"><div class="card-body">
            <div class="text-secondary small"><i class="bi bi-<?php echo $c[2]; ?> me-1"></i><?php echo e($c[0]); ?></div>
            <div class="stat-value text-body"><?php echo e($c[1]); ?></div>
          </div></div>
          <?php if ($c[3]): ?></a><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <section class="mb-4">
      <div class="d-flex justify-content-between align-items-center">
        <h2 class="h4 mb-2">Overdue books</h2>
        <a href="returns.php" class="btn btn-sm btn-library">Process returns</a>
      </div>
      <?php if (!$overdue): ?>
        <p class="text-secondary">Nothing is overdue.</p>
      <?php else: ?>
        <div class="table-responsive bg-white rounded shadow-sm">
          <table class="table mb-0">
            <thead class="table-light"><tr><th>Title</th><th>Copy</th><th>Member</th><th>Due</th><th>Fine so far</th></tr></thead>
            <tbody>
            <?php foreach ($overdue as $o): ?>
              <tr>
                <td class="fw-semibold"><?php echo e($o['title']); ?></td>
                <td><code><?php echo e($o['barcode']); ?></code></td>
                <td><?php echo e($o['first_name'] . ' ' . $o['last_name']); ?></td>
                <td><?php echo e(date('M j, Y', strtotime($o['due_date']))); ?>
                  <span class="badge text-bg-danger ms-1"><?php echo (int)$o['days_late']; ?> days late</span></td>
                <td>$<?php echo number_format($o['days_late'] * FINE_PER_DAY, 2); ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </main>
  <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
</body>
</html>
