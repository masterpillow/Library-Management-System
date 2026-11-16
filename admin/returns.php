<?php
include_once(realpath(dirname(__FILE__) . '/../php/path.php'));
include_once(ROOT_SRC_PATH . '/session.php');
include_once(ROOT_SRC_PATH . '/config.php');
include_once(ROOT_SRC_PATH . '/check_admin.php');

// Handle processing a book return
if (isset($_POST['process_return'])) {
    $loan_id = (int)$_POST['loan_id'];
    $copy_id = (int)$_POST['copy_id'];
    
    $db_connection->begin_transaction();
    
    try {
        $loan_stmt = $db_connection->prepare("UPDATE Loans SET return_date = NOW() WHERE loan_id = ?");
        $loan_stmt->bind_param('i', $loan_id);
        $loan_stmt->execute();
        
        $copy_stmt = $db_connection->prepare("UPDATE Copies SET status = 'available' WHERE copy_id = ?");
        $copy_stmt->bind_param('i', $copy_id);
        $copy_stmt->execute();
        
        $db_connection->commit();
        header("Location: returns.php");
        exit;
    } catch (Exception $e) {
        $db_connection->rollback();
        $error_message = "Failed to process return. Please try again.";
    }
}

// Handle search filtering for active loans with correct table joins
$search = trim($_GET['search'] ?? '');
if (!empty($search)) {
    $search_param = "%$search%";
    $stmt = $db_connection->prepare("
        SELECT l.loan_id, l.checkout_date, l.due_date, c.copy_id, c.barcode, b.title, co.first_name, co.last_name, co.email
        FROM Loans l
        JOIN Copies c ON l.copy_id = c.copy_id
        JOIN Books b ON c.book_id = b.book_id
        JOIN Users u ON l.user_id = u.user_id
        JOIN Contacts co ON u.contact_id = co.contact_id
        WHERE l.return_date IS NULL 
          AND (b.title LIKE ? OR c.barcode LIKE ? OR co.first_name LIKE ? OR co.last_name LIKE ?)
        ORDER BY l.due_date ASC
    ");
    $stmt->bind_param('ssss', $search_param, $search_param, $search_param, $search_param);
    $stmt->execute();
    $active_loans = $stmt->get_result();
} else {
    $active_loans = $db_connection->query("
        SELECT l.loan_id, l.checkout_date, l.due_date, c.copy_id, c.barcode, b.title, co.first_name, co.last_name, co.email
        FROM Loans l
        JOIN Copies c ON l.copy_id = c.copy_id
        JOIN Books b ON c.book_id = b.book_id
        JOIN Users u ON l.user_id = u.user_id
        JOIN Contacts co ON u.contact_id = co.contact_id
        WHERE l.return_date IS NULL
        ORDER BY l.due_date ASC
    ");
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Manage Returns & Active Loans</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
  <h1 class="mb-4">Manage Returns & Active Loans</h1>
  <p class="text-muted">View active checkouts and process book returns to update physical inventory.</p>

  <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
  <?php endif; ?>

  <form method="get" class="row g-3 mb-4">
    <div class="col-md-6">
      <input type="text" name="search" class="form-control" placeholder="Search by book title, barcode, or patron name..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-secondary w-100">Filter</button>
    </div>
    <?php if (!empty($search)): ?>
      <div class="col-md-2">
        <a href="returns.php" class="btn btn-outline-dark w-100">Reset</a>
      </div>
    <?php endif; ?>
  </form>

  <table class="table table-striped align-middle">
    <thead>
      <tr>
        <th>Barcode</th>
        <th>Book Title</th>
        <th>Patron Name</th>
        <th>Checkout Date</th>
        <th>Due Date</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
    <?php if ($active_loans->num_rows > 0): ?>
      <?php while ($row = $active_loans->fetch_assoc()): ?>
        <?php $is_overdue = strtotime($row['due_date']) < time(); ?>
        <tr>
          <td><code><?= htmlspecialchars($row['barcode']) ?></code></td>
          <td><?= htmlspecialchars($row['title']) ?></td>
          <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?> <br><small class="text-muted"><?= htmlspecialchars($row['email']) ?></small></td>
          <td><?= htmlspecialchars($row['checkout_date']) ?></td>
          <td>
            <span class="badge bg-<?= $is_overdue ? 'danger' : 'info' ?>">
              <?= htmlspecialchars($row['due_date']) ?>
            </span>
          </td>
          <td>
            <form method="post" style="display:inline">
              <input type="hidden" name="loan_id" value="<?= $row['loan_id'] ?>">
              <input type="hidden" name="copy_id" value="<?= $row['copy_id'] ?>">
              <button type="submit" name="process_return" class="btn btn-sm btn-success">Process Return</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr>
        <td colspan="6" class="text-center text-muted py-4">No active loans found. All items are currently checked in.</td>
      </tr>
    <?php endif; ?>
    </tbody>
  </table>
  
  <a href="index.php" class="btn btn-primary mt-3">Back to Admin Home</a>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>