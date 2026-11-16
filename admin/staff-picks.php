<?php
include_once(realpath(dirname(__FILE__) . '/../php/path.php'));
include_once(ROOT_SRC_PATH . '/session.php');
include_once(ROOT_SRC_PATH . '/config.php');
include_once(ROOT_SRC_PATH . '/check_admin.php');

// Handle adding or removing a staff pick
if (isset($_POST['save_pick'])) {
    $book_id = (int)$_POST['book_id'];
    $user_id = $_SESSION['user_id'] ?? 1; // Fallback to current logged-in admin
    $review_blurb = trim($_POST['review_blurb']);
    
    // Explicitly matching table column order: user_id then book_id
    $stmt = $db_connection->prepare("INSERT INTO Staff_Picks (user_id, book_id, pick_date, review_blurb) VALUES (?, ?, NOW(), ?) ON DUPLICATE KEY UPDATE review_blurb = ?");
    $stmt->bind_param('iiss', $user_id, $book_id, $review_blurb, $review_blurb);
    $stmt->execute();
    header("Location: staff-picks.php");
    exit;
}

if (isset($_POST['remove_pick'])) {
    $pick_id = (int)$_POST['pick_id'];
    $stmt = $db_connection->prepare("DELETE FROM Staff_Picks WHERE pick_id = ?");
    $stmt->bind_param('i', $pick_id);
    $stmt->execute();
    header("Location: staff-picks.php");
    exit;
}

// Fetch all active books and check if they are currently featured
$books = $db_connection->query("
  SELECT b.book_id, b.title, sp.pick_id, sp.review_blurb, g.name AS genre,
         GROUP_CONCAT(CONCAT(a.first_name, ' ', a.last_name) SEPARATOR ', ') AS authors
  FROM Books b
  LEFT JOIN Genres g ON b.genre_id = g.genre_id
  LEFT JOIN Book_Authors ba ON b.book_id = ba.book_id
  LEFT JOIN Authors a ON ba.author_id = a.author_id
  LEFT JOIN Staff_Picks sp ON b.book_id = sp.book_id
  WHERE b.is_active = 1
  GROUP BY b.book_id
  ORDER BY sp.pick_date DESC, b.title ASC
");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Manage Staff Picks</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
  <h1 class="mb-4">Manage Staff Picks</h1>
  <p class="text-muted">Curate featured library titles and add review blurbs for patrons.</p>

  <table class="table table-striped align-middle">
    <thead>
      <tr>
        <th>Title</th>
        <th>Authors</th>
        <th>Review Blurb</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
    <?php while ($row = $books->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($row['title']) ?></td>
        <td><?= htmlspecialchars($row['authors'] ?? 'Unknown') ?></td>
        <td><?= htmlspecialchars($row['review_blurb'] ?? 'No blurb added.') ?></td>
        <td>
          <?php if (!empty($row['pick_id'])): ?>
            <span class="badge bg-success">Featured Pick</span>
          <?php else: ?>
            <span class="badge bg-secondary">Standard</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if (!empty($row['pick_id'])): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="pick_id" value="<?= $row['pick_id'] ?>">
              <button type="submit" name="remove_pick" class="btn btn-sm btn-warning">Remove Pick</button>
            </form>
          <?php else: ?>
            <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#pickModal<?= $row['book_id'] ?>">Add Pick</button>
          <?php endif; ?>
        </td>
      </tr>

      <!-- Modal to add review blurb -->
      <div class="modal fade" id="pickModal<?= $row['book_id'] ?>" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <form method="post">
              <div class="modal-header">
                <h5 class="modal-title">Feature Book: <?= htmlspecialchars($row['title']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
                <div class="mb-3">
                  <label class="form-label">Review Blurb / Recommendation</label>
                  <textarea name="review_blurb" class="form-control" rows="3" placeholder="Why should patrons read this?"></textarea>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="save_pick" class="btn btn-primary">Save Staff Pick</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
    </tbody>
  </table>
  
  <a href="index.php" class="btn btn-primary mt-3">Back to Admin Home</a>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>