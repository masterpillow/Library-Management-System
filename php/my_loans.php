<?php
require_once 'session.php';
require_once 'path.php';
require_once 'config.php';
$page_name = "member";

$user_id = (int) $_SESSION['user_id'];
$msg = $_GET['msg'] ?? '';

// Fetch loans and calculate total unpaid fines
$loans = $db_connection->query("
  SELECT l.loan_id, b.title, l.due_date, l.return_date AS returned, l.fine
  FROM Loans l
  JOIN Copies c ON l.copy_id = c.copy_id
  JOIN Books b ON c.book_id = b.book_id
  WHERE l.user_id = $user_id
  ORDER BY l.due_date DESC
");

// Calculate total fine balance
$fine_sum_query = $db_connection->query("SELECT SUM(fine) AS total_fine FROM Loans WHERE user_id = $user_id");
$fine_data = $fine_sum_query ? $fine_sum_query->fetch_assoc() : ['total_fine' => 0];
$total_fine = $fine_data['total_fine'] ?? 0;

// Fetch active holds
$holds = $db_connection->query("
  SELECT h.hold_id, b.title, h.hold_date, h.status
  FROM Holds h
  JOIN Books b ON h.book_id = b.book_id
  WHERE h.user_id = $user_id AND h.status IN ('waiting', 'ready')
  ORDER BY h.hold_date
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Library Account - Library Management System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f9f9f9; color: #333; }
        .container { max-width: 900px; margin: auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #2c3e50; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 30px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f4f4f4; }
        a { color: #3498db; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .btn-danger { color: #e74c3c; font-weight: bold; }
        .btn-danger:hover { color: #c0392b; }
        .btn-success { color: #27ae60; font-weight: bold; }
        .btn-success:hover { color: #1e8449; }
        .btn-pay { color: #e67e22; font-weight: bold; }
        .btn-pay:hover { color: #d35400; }
        .alert { background: #d4edda; color: #155724; padding: 12px 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .alert-warning { background: #f8d7da; color: #721c24; padding: 12px 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>My Library Account</h1>
        <p><a href="search.php">&larr; Back to Search</a></p>

        <?php if ($msg === 'checkout_success'): ?>
            <div class="alert">Book successfully checked out!</div>
        <?php elseif ($msg === 'hold_success'): ?>
            <div class="alert">Hold placed successfully!</div>
        <?php elseif ($msg === 'hold_cancelled'): ?>
            <div class="alert">Hold successfully cancelled.</div>
        <?php elseif ($msg === 'return_success'): ?>
            <div class="alert">Book successfully returned!</div>
        <?php elseif ($msg === 'fine_paid'): ?>
            <div class="alert">Fine successfully paid. Thank you!</div>
        <?php endif; ?>

        <?php if ($total_fine > 0): ?>
            <div class="alert-warning">
                <strong>Notice:</strong> You have an outstanding fine balance of <strong>$<?php echo number_format($total_fine, 2); ?></strong>. Please settle your fines to maintain borrowing privileges.
            </div>
        <?php endif; ?>
        
        <!-- LOANS SECTION -->
        <h2>Checked-Out Books & Loan History</h2>
        <?php if ($loans && $loans->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Book Title</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Fine</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $loans->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars($row['due_date']); ?></td>
                            <td>
                                <?php if (!empty($row['returned'])): ?>
                                    <span style="color: green;">Returned (<?php echo htmlspecialchars($row['returned']); ?>)</span>
                                <?php else: ?>
                                    <span style="color: #e67e22; font-weight: bold;">Active Loan</span>
                                <?php endif; ?>
                            </td>
                            <td>$<?php echo number_format($row['fine'], 2); ?></td>
                            <td>
                                <?php if (empty($row['returned'])): ?>
                                    <a href="member-return.php?loan_id=<?php echo $row['loan_id']; ?>" class="btn-success" onclick="return confirm('Are you sure you want to return this book?');">Return</a>
                                <?php endif; ?>
                                
                                <?php if ($row['fine'] > 0): ?>
                                    <a href="member-pay-fine.php?loan_id=<?php echo $row['loan_id']; ?>" class="btn-pay" onclick="return confirm('Process payment of $<?php echo number_format($row['fine'], 2); ?>?');">Pay Fine</a>
                                <?php endif; ?>

                                <?php if (!empty($row['returned']) && $row['fine'] == 0): ?>
                                    <span style="color: #95a5a6;">Completed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>You currently have no loan history.</p>
        <?php endif; ?>

        <!-- HOLDS SECTION -->
        <h2>Active Holds</h2>
        <?php if ($holds && $holds->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Book Title</th>
                        <th>Hold Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($hold = $holds->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($hold['title']); ?></td>
                            <td><?php echo htmlspecialchars($hold['hold_date']); ?></td>
                            <td>
                                <span style="text-transform: capitalize; font-weight: bold; color: #2980b9;">
                                    <?php echo htmlspecialchars($hold['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="member-cancel-hold.php?hold_id=<?php echo $hold['hold_id']; ?>" class="btn-danger" onclick="return confirm('Are you sure you want to cancel this hold?');">Cancel Hold</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>You currently have no active holds.</p>
        <?php endif; ?>
    </div>
</body>
</html>