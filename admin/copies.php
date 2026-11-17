<?php
//======================================================================
// ADMIN: manage physical copies
//======================================================================
// "On loan" and "set aside for a hold" are controlled by checkouts,
// returns and holds, so only copies not in use can be changed here.

include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));
require_role(ROLE_ADMIN);

$page_name = 'copies';
$page_title = 'Manage Copies';

/* Statuses an admin may set by hand */
$editable = ['available', 'damaged', 'lost', 'retired'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check('admin/copies.php');
  $action = isset($_POST['action']) ? $_POST['action'] : '';
  $back = 'admin/copies.php' . (isset($_POST['search']) && $_POST['search'] !== '' ? '?search=' . urlencode($_POST['search']) : '');

  if ($action === 'add') {
    $book_id = (int)$_POST['book_id'];
    $count = max(1, min(20, (int)(isset($_POST['count']) ? $_POST['count'] : 1)));
    $b = $db_connection->prepare("SELECT title FROM Books WHERE book_id = ? AND is_active = 1");
    $b->bind_param('i', $book_id);
    $b->execute();
    $book = $b->get_result()->fetch_assoc();
    $b->close();
    if (!$book) {
      flash('danger', 'Pick a book from the list.');
      redirect($back);
    }

    // Barcodes continue the LMS-0001 sequence
    $next = (int)$db_connection->query("SELECT COALESCE(MAX(CAST(SUBSTRING(barcode, 5) AS UNSIGNED)), 0) + 1 AS n
                                        FROM Copies WHERE barcode REGEXP '^LMS-[0-9]+$'")->fetch_assoc()['n'];
    $ins = $db_connection->prepare("INSERT INTO Copies (book_id, barcode, status) VALUES (?, ?, 'available')");
    $made = [];
    for ($i = 0; $i < $count; $i++) {
      $barcode = sprintf('LMS-%04d', $next + $i);
      $ins->bind_param('is', $book_id, $barcode);
      $ins->execute();
      $made[] = $barcode;
    }
    $ins->close();
    // A new copy can go straight to someone waiting on a hold
    $db_connection->begin_transaction();
    promote_waiting_holds($db_connection, $book_id);
    $db_connection->commit();
    flash('success', 'Added ' . count($made) . ' ' . (count($made) === 1 ? 'copy' : 'copies') . ' of "' . $book['title'] . '": ' . implode(', ', $made) . '.');
    redirect($back);
  }

  if ($action === 'status') {
    $copy_id = (int)$_POST['copy_id'];
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    if (!in_array($status, $editable, true)) {
      flash('danger', 'That status can\'t be set by hand.');
      redirect($back);
    }
    $s = $db_connection->prepare("UPDATE Copies SET status = ? WHERE copy_id = ? AND status NOT IN ('on_loan', 'on_hold')");
    $s->bind_param('si', $status, $copy_id);
    $s->execute();
    $changed = $s->affected_rows;
    $s->close();
    if ($changed) {
      if ($status === 'available') {
        $b = $db_connection->prepare("SELECT book_id FROM Copies WHERE copy_id = ?");
        $b->bind_param('i', $copy_id);
        $b->execute();
        $book_id = (int)$b->get_result()->fetch_assoc()['book_id'];
        $b->close();
        $db_connection->begin_transaction();
        promote_waiting_holds($db_connection, $book_id);
        $db_connection->commit();
      }
      flash('success', 'Copy marked ' . strtolower(copy_status_label($status)) . '.');
    } else {
      flash('warning', 'That copy is checked out or set aside for a hold, so its status can\'t be changed here.');
    }
    redirect($back);
  }
  redirect($back);
}

/* Copies sitting on the shelf go to members waiting on holds, oldest hold first */
function promote_waiting_holds(mysqli $db, $book_id) {
  while (true) {
    $h = $db->prepare("SELECT hold_id FROM Holds WHERE book_id = ? AND status = 'waiting' ORDER BY hold_date, hold_id LIMIT 1");
    $h->bind_param('i', $book_id);
    $h->execute();
    $hold = $h->get_result()->fetch_assoc();
    $h->close();
    if (!$hold) {
      return;
    }
    $c = $db->prepare("SELECT copy_id FROM Copies WHERE book_id = ? AND status = 'available' LIMIT 1");
    $c->bind_param('i', $book_id);
    $c->execute();
    $copy = $c->get_result()->fetch_assoc();
    $c->close();
    if (!$copy) {
      return;
    }
    $u = $db->prepare("UPDATE Copies SET status = 'on_hold' WHERE copy_id = ?");
    $u->bind_param('i', $copy['copy_id']);
    $u->execute();
    $u->close();
    $r = $db->prepare("UPDATE Holds SET status = 'ready', ready_at = NOW() WHERE hold_id = ?");
    $r->bind_param('i', $hold['hold_id']);
    $r->execute();
    $r->close();
  }
}

$search = trim(isset($_GET['search']) ? $_GET['search'] : '');
$sql = "SELECT c.copy_id, c.barcode, c.status, b.title, b.is_active,
               co.first_name, co.last_name, l.due_date
        FROM Copies c
        JOIN Books b ON b.book_id = c.book_id
        LEFT JOIN Loans l ON l.copy_id = c.copy_id AND l.return_date IS NULL
        LEFT JOIN Users u ON u.user_id = l.user_id
        LEFT JOIN Contacts co ON co.contact_id = u.contact_id";
if ($search !== '') {
  $sql .= " WHERE b.title LIKE ? OR c.barcode LIKE ?";
}
$sql .= " ORDER BY b.title, c.barcode";
$s = $db_connection->prepare($sql);
if ($search !== '') {
  $like = '%' . $search . '%';
  $s->bind_param('ss', $like, $like);
}
$s->execute();
$copies = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$s->close();

$books = $db_connection->query("SELECT book_id, title FROM Books WHERE is_active = 1 ORDER BY title")->fetch_all(MYSQLI_ASSOC);
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
      <h1>Copies</h1>
      <p class="text-secondary mb-0">Each copy is one physical book with its own barcode. Barcodes are assigned automatically.</p>
    </div>

    <div class="card shadow-sm border-0 mb-4"><div class="card-body">
      <h2 class="h5 mb-3">Add copies</h2>
      <form method="post" class="row g-3 align-items-end">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="search" value="<?php echo e($search); ?>">
        <div class="col-md-7">
          <label class="form-label" for="book_id">Book</label>
          <select class="form-select" id="book_id" name="book_id" required>
            <option value="">Choose a book</option>
            <?php foreach ($books as $b): ?>
              <option value="<?php echo (int)$b['book_id']; ?>"><?php echo e($b['title']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label" for="count">How many</label>
          <input type="number" class="form-control" id="count" name="count" value="1" min="1" max="20">
        </div>
        <div class="col-md-3 d-grid"><button type="submit" class="btn btn-library"><i class="bi bi-plus-lg me-1"></i>Add</button></div>
      </form>
    </div></div>

    <form method="get" class="row g-2 mb-3">
      <div class="col-sm-9 col-lg-10">
        <input type="search" class="form-control" name="search" value="<?php echo e($search); ?>" placeholder="Search by title or barcode" aria-label="Search copies">
      </div>
      <div class="col-sm-3 col-lg-2 d-grid"><button class="btn btn-outline-secondary" type="submit">Search</button></div>
    </form>

    <div class="table-responsive bg-white rounded shadow-sm">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Barcode</th><th>Title</th><th>Status</th><th class="text-end">Change status</th></tr></thead>
        <tbody>
        <?php if (!$copies): ?>
          <tr><td colspan="4" class="text-center text-secondary py-4">No copies found.</td></tr>
        <?php endif; ?>
        <?php foreach ($copies as $c): ?>
          <tr>
            <td><code><?php echo e($c['barcode']); ?></code></td>
            <td>
              <?php echo e($c['title']); ?>
              <?php if (!$c['is_active']): ?><span class="badge text-bg-secondary ms-1">Book inactive</span><?php endif; ?>
            </td>
            <td>
              <?php echo copy_status_badge($c['status']); ?>
              <?php if ($c['status'] === 'on_loan' && $c['first_name'] !== null): ?>
                <small class="text-secondary d-block"><?php echo e($c['first_name'] . ' ' . $c['last_name']); ?>, due <?php echo e(date('M j', strtotime($c['due_date']))); ?></small>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <?php if (in_array($c['status'], ['on_loan', 'on_hold'], true)): ?>
                <span class="text-secondary small"><?php echo $c['status'] === 'on_loan' ? 'Use Returns to check it in' : 'Waiting for pickup'; ?></span>
              <?php else: ?>
                <form method="post" class="d-inline-flex gap-2">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="status">
                  <input type="hidden" name="copy_id" value="<?php echo (int)$c['copy_id']; ?>">
                  <input type="hidden" name="search" value="<?php echo e($search); ?>">
                  <label class="visually-hidden" for="st<?php echo (int)$c['copy_id']; ?>">Status for <?php echo e($c['barcode']); ?></label>
                  <select class="form-select form-select-sm w-auto" id="st<?php echo (int)$c['copy_id']; ?>" name="status">
                    <?php foreach ($editable as $st): ?>
                      <option value="<?php echo $st; ?>" <?php echo $st === $c['status'] ? 'selected' : ''; ?>><?php echo e(copy_status_label($st)); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
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
