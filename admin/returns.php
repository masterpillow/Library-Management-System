<?php
//======================================================================
// ADMIN: check in returned books
//======================================================================
// Returning closes the loan, charges the late fine, and sets the copy
// aside for the next member on the hold list (or puts it back on the shelf).

include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));
require_role(ROLE_ADMIN);

$page_name = 'returns';
$page_title = 'Returns';

$search = trim(isset($_GET['search']) ? $_GET['search'] : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $search = trim(isset($_POST['search']) ? $_POST['search'] : '');
  $back = 'admin/returns.php' . ($search !== '' ? '?search=' . urlencode($search) : '');
  csrf_check($back);

  $loan_id = (int)(isset($_POST['loan_id']) ? $_POST['loan_id'] : 0);
  try {
    $result = return_loan($db_connection, $loan_id);
    flash($result !== null ? 'success' : 'warning', $result !== null ? $result : 'That loan was already returned.');
  } catch (mysqli_sql_exception $ex) {
    flash('danger', 'The return could not be processed. Please try again.');
  }
  redirect($back);
}

$sql = "SELECT l.loan_id, l.checkout_date, l.due_date, " . DAYS_LATE_SQL . " AS days_late,
               c.barcode, b.title, co.first_name, co.last_name, co.email,
               (SELECT COUNT(*) FROM Holds h WHERE h.book_id = b.book_id AND h.status = 'waiting') AS waiting
        FROM Loans l
        JOIN Copies c ON c.copy_id = l.copy_id
        JOIN Books b ON b.book_id = c.book_id
        JOIN Users u ON u.user_id = l.user_id
        JOIN Contacts co ON co.contact_id = u.contact_id
        WHERE l.return_date IS NULL";
if ($search !== '') {
  $sql .= " AND (b.title LIKE ? OR c.barcode LIKE ? OR CONCAT(co.first_name, ' ', co.last_name) LIKE ?)";
}
$sql .= " ORDER BY l.due_date";
$s = $db_connection->prepare($sql);
if ($search !== '') {
  $like = '%' . $search . '%';
  $s->bind_param('sss', $like, $like, $like);
}
$s->execute();
$loans = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$s->close();
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
      <h1>Returns</h1>
      <p class="text-secondary mb-0">
        Everything currently checked out, oldest due date first. Late returns are charged
        $<?php echo number_format(FINE_PER_DAY, 2); ?> per day.
      </p>
    </div>

    <form method="get" class="row g-2 mb-3">
      <div class="col-sm-9 col-lg-10">
        <input type="search" class="form-control" name="search" value="<?php echo e($search); ?>"
               placeholder="Scan a barcode, or search by title or member name" aria-label="Search loans" autofocus>
      </div>
      <div class="col-sm-3 col-lg-2 d-grid"><button class="btn btn-outline-secondary" type="submit">Search</button></div>
    </form>

    <div class="table-responsive bg-white rounded shadow-sm">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Copy</th><th>Title</th><th>Member</th><th>Due</th><th class="text-end">Action</th></tr></thead>
        <tbody>
        <?php if (!$loans): ?>
          <tr><td colspan="5" class="text-center text-secondary py-4"><?php echo $search !== '' ? 'No checked-out books match that search.' : 'Nothing is checked out right now.'; ?></td></tr>
        <?php endif; ?>
        <?php foreach ($loans as $l): ?>
          <tr>
            <td><code><?php echo e($l['barcode']); ?></code></td>
            <td>
              <div class="fw-semibold"><?php echo e($l['title']); ?></div>
              <?php if ($l['waiting'] > 0): ?><small class="text-info"><?php echo (int)$l['waiting']; ?> waiting on a hold</small><?php endif; ?>
            </td>
            <td><?php echo e($l['first_name'] . ' ' . $l['last_name']); ?><small class="text-secondary d-block"><?php echo e($l['email']); ?></small></td>
            <td>
              <?php echo e(date('M j, Y', strtotime($l['due_date']))); ?>
              <?php if ($l['days_late'] > 0): ?>
                <span class="badge text-bg-danger ms-1"><?php echo (int)$l['days_late']; ?> days late</span>
                <small class="text-danger d-block">$<?php echo number_format($l['days_late'] * FINE_PER_DAY, 2); ?> fine</small>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <form method="post" class="d-inline" data-confirm="Check in <?php echo e($l['barcode']); ?>?">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="loan_id" value="<?php echo (int)$l['loan_id']; ?>">
                <input type="hidden" name="search" value="<?php echo e($search); ?>">
                <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-box-arrow-in-down me-1"></i>Check in</button>
              </form>
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
