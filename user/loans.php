<?php
//======================================================================
// MY LOANS: current loans, holds, fines, and borrowing history
//======================================================================
include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));
require_role(ROLE_MEMBER);

$page_name = 'loans';
$page_title = 'My Loans';

expire_old_holds($db_connection);

/* Membership limits and fine balance */
$s = $db_connection->prepare(
  "SELECT ms.type_name, ms.max_loans, ms.loan_days,
          (SELECT COUNT(*) FROM Loans l WHERE l.user_id = u.user_id AND l.return_date IS NULL) AS open_loans,
          (SELECT COALESCE(SUM(l.fine), 0) FROM Loans l WHERE l.user_id = u.user_id) AS fines
   FROM Users u JOIN Memberships ms ON ms.membership_id = u.membership_id
   WHERE u.user_id = ?");
$s->bind_param('i', $user_id);
$s->execute();
$me = $s->get_result()->fetch_assoc();
$s->close();

/* Current loans, with the fine they'd owe if returned today */
$s = $db_connection->prepare(
  "SELECT l.loan_id, b.title, c.barcode, l.checkout_date, l.due_date,
          " . DAYS_LATE_SQL . " AS days_late
   FROM Loans l
   JOIN Copies c ON c.copy_id = l.copy_id
   JOIN Books b ON b.book_id = c.book_id
   WHERE l.user_id = ? AND l.return_date IS NULL
   ORDER BY l.due_date");
$s->bind_param('i', $user_id);
$s->execute();
$current = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$s->close();

/* Active holds, with place in line for waiting ones */
$s = $db_connection->prepare(
  "SELECT h.hold_id, h.book_id, b.title, h.status, h.hold_date,
          DATE_ADD(h.ready_at, INTERVAL " . HOLD_PICKUP_DAYS . " DAY) AS pickup_by,
          (SELECT COUNT(*) FROM Holds h2
            WHERE h2.book_id = h.book_id AND h2.status = 'waiting'
              AND (h2.hold_date < h.hold_date OR (h2.hold_date = h.hold_date AND h2.hold_id < h.hold_id))) + 1 AS place
   FROM Holds h JOIN Books b ON b.book_id = h.book_id
   WHERE h.user_id = ? AND h.status IN ('waiting', 'ready')
   ORDER BY h.status = 'ready' DESC, h.hold_date");
$s->bind_param('i', $user_id);
$s->execute();
$holds = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$s->close();

/* Returned loans, newest first, with the member's rating for each book */
$s = $db_connection->prepare(
  "SELECT l.loan_id, c.book_id, b.title, l.checkout_date, l.due_date, l.return_date, l.fine,
          (DATEDIFF(l.return_date, l.due_date) > 0) AS was_late, r.rating
   FROM Loans l
   JOIN Copies c ON c.copy_id = l.copy_id
   JOIN Books b ON b.book_id = c.book_id
   LEFT JOIN Ratings r ON r.book_id = c.book_id AND r.user_id = l.user_id
   WHERE l.user_id = ? AND l.return_date IS NOT NULL
   ORDER BY l.return_date DESC");
$s->bind_param('i', $user_id);
$s->execute();
$history = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$s->close();

function fmt_date($value) {
  return $value ? date('M j, Y', strtotime($value)) : '';
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

    <div class="page-header">
      <h1>My Loans</h1>
      <p class="text-secondary mb-0">
        <?php echo e($me['type_name']); ?> membership:
        <?php echo (int)$me['open_loans']; ?> of <?php echo (int)$me['max_loans']; ?> books out,
        <?php echo (int)$me['loan_days']; ?> days per loan,
        $<?php echo number_format(FINE_PER_DAY, 2); ?> per day late.
      </p>
    </div>

    <?php if ($me['fines'] > 0): ?>
      <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
          <i class="bi bi-exclamation-triangle me-1"></i>
          You owe <strong>$<?php echo number_format($me['fines'], 2); ?></strong> in late fines.
          You can't check out more books until it's paid.
        </div>
        <form method="post" action="<?php echo e(url('php/member-pay-fine.php')); ?>"
              data-confirm="Pay $<?php echo number_format($me['fines'], 2); ?> now?">
          <?php echo csrf_field(); ?>
          <button type="submit" class="btn btn-warning btn-sm">Pay $<?php echo number_format($me['fines'], 2); ?></button>
        </form>
      </div>
    <?php endif; ?>

    <!-- Holds -->
    <section class="mb-5">
      <h2 class="h4">Holds</h2>
      <?php if (!$holds): ?>
        <p class="text-secondary">No holds. When every copy of a book is out, place a hold from the <a href="search.php">catalog</a>.</p>
      <?php else: ?>
        <div class="table-responsive bg-white rounded shadow-sm">
          <table class="table mb-0">
            <thead class="table-light">
              <tr><th>Title</th><th>Placed</th><th>Status</th><th class="text-end">Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($holds as $h): ?>
              <tr>
                <td class="fw-semibold"><?php echo e($h['title']); ?></td>
                <td><?php echo e(fmt_date($h['hold_date'])); ?></td>
                <td>
                  <?php if ($h['status'] === 'ready'): ?>
                    <span class="badge text-bg-success">Ready for you</span>
                    <small class="text-secondary d-block">Pick up by <?php echo e(fmt_date($h['pickup_by'])); ?></small>
                  <?php else: ?>
                    <span class="badge text-bg-info">Waiting</span>
                    <small class="text-secondary d-block">#<?php echo (int)$h['place']; ?> in line</small>
                  <?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
                  <?php if ($h['status'] === 'ready'): ?>
                    <form method="post" action="<?php echo e(url('php/member-checkout.php')); ?>" class="d-inline">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="book_id" value="<?php echo (int)$h['book_id']; ?>">
                      <button type="submit" class="btn btn-sm btn-success">Check out</button>
                    </form>
                  <?php endif; ?>
                  <form method="post" action="<?php echo e(url('php/member-cancel-hold.php')); ?>" class="d-inline"
                        data-confirm="Cancel your hold on this book?">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="hold_id" value="<?php echo (int)$h['hold_id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <!-- Current loans -->
    <section class="mb-5">
      <h2 class="h4">Checked out</h2>
      <?php if (!$current): ?>
        <p class="text-secondary">Nothing checked out right now. Find something in the <a href="search.php">catalog</a>.</p>
      <?php else: ?>
        <div class="table-responsive bg-white rounded shadow-sm">
          <table class="table mb-0">
            <thead class="table-light">
              <tr><th>Title</th><th>Checked out</th><th>Due</th><th class="text-end">Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($current as $l): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?php echo e($l['title']); ?></div>
                  <small class="text-secondary">Copy <?php echo e($l['barcode']); ?></small>
                </td>
                <td><?php echo e(fmt_date($l['checkout_date'])); ?></td>
                <td>
                  <?php echo e(fmt_date($l['due_date'])); ?>
                  <?php if ($l['days_late'] > 0): ?>
                    <span class="badge text-bg-danger ms-1"><?php echo (int)$l['days_late']; ?> <?php echo $l['days_late'] == 1 ? 'day' : 'days'; ?> overdue</span>
                    <small class="text-danger d-block">$<?php echo number_format($l['days_late'] * FINE_PER_DAY, 2); ?> fine if returned today</small>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <form method="post" action="<?php echo e(url('php/member-return.php')); ?>" class="d-inline"
                        data-confirm="Return this book?">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="loan_id" value="<?php echo (int)$l['loan_id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-success">Return</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <!-- History -->
    <section class="mb-4">
      <h2 class="h4">History</h2>
      <?php if (!$history): ?>
        <p class="text-secondary">Books you return will show up here, and you can rate them.</p>
      <?php else: ?>
        <div class="table-responsive bg-white rounded shadow-sm">
          <table class="table mb-0">
            <thead class="table-light">
              <tr><th>Title</th><th>Borrowed</th><th>Returned</th><th>Fine</th><th>Your rating</th></tr>
            </thead>
            <tbody>
            <?php foreach ($history as $h): ?>
              <tr>
                <td class="fw-semibold"><?php echo e($h['title']); ?></td>
                <td><?php echo e(fmt_date($h['checkout_date'])); ?></td>
                <td>
                  <?php echo e(fmt_date($h['return_date'])); ?>
                  <?php if ($h['was_late']): ?><span class="badge text-bg-warning ms-1">Late</span><?php endif; ?>
                </td>
                <td><?php echo $h['fine'] > 0 ? '<span class="text-danger">$' . number_format($h['fine'], 2) . ' owed</span>' : '<span class="text-secondary">None</span>'; ?></td>
                <td>
                  <form method="post" action="<?php echo e(url('php/rate-loan.php')); ?>" class="d-flex gap-1 align-items-center">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="book_id" value="<?php echo (int)$h['book_id']; ?>">
                    <label class="visually-hidden" for="rate<?php echo (int)$h['loan_id']; ?>">Rating for <?php echo e($h['title']); ?></label>
                    <select name="rating" id="rate<?php echo (int)$h['loan_id']; ?>" class="form-select form-select-sm w-auto" required>
                      <option value="" <?php echo $h['rating'] ? '' : 'selected'; ?> disabled>Rate</option>
                      <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?php echo $i; ?>" <?php echo (int)$h['rating'] === $i ? 'selected' : ''; ?>><?php echo str_repeat('★', $i); ?></option>
                      <?php endfor; ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><?php echo $h['rating'] ? 'Update' : 'Save'; ?></button>
                  </form>
                </td>
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
