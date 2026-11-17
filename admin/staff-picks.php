<?php
//======================================================================
// ADMIN: staff picks
//======================================================================
// A hand-picked list of recommended books with a short blurb. Checkout
// counts are shown to help choose what's popular.

include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));
require_role(ROLE_ADMIN);

$page_name = 'staff-picks';
$page_title = 'Staff Picks';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check('admin/staff-picks.php');
  $action = isset($_POST['action']) ? $_POST['action'] : '';
  $book_id = (int)(isset($_POST['book_id']) ? $_POST['book_id'] : 0);

  if ($action === 'save') {
    $blurb = trim(isset($_POST['review_blurb']) ? $_POST['review_blurb'] : '');
    if (strlen($blurb) > 500) {
      flash('danger', 'Keep the blurb under 500 characters.');
      redirect('admin/staff-picks.php');
    }
    $blurb = $blurb === '' ? null : $blurb;
    // One pick per book: saving again just updates the blurb
    $s = $db_connection->prepare("INSERT INTO Staff_Picks (user_id, book_id, pick_date, review_blurb) VALUES (?, ?, NOW(), ?)
                                  ON DUPLICATE KEY UPDATE review_blurb = VALUES(review_blurb)");
    $s->bind_param('iis', $user_id, $book_id, $blurb);
    $s->execute();
    $s->close();
    flash('success', 'Staff pick saved.');
  } elseif ($action === 'remove') {
    $s = $db_connection->prepare("DELETE FROM Staff_Picks WHERE book_id = ?");
    $s->bind_param('i', $book_id);
    $s->execute();
    $s->close();
    flash('success', 'Removed from staff picks.');
  }
  redirect('admin/staff-picks.php');
}

$books = $db_connection->query(
  "SELECT b.book_id, b.title, g.name AS genre, sp.pick_id, sp.review_blurb, sp.pick_date,
          GROUP_CONCAT(DISTINCT CONCAT(a.first_name, ' ', a.last_name) SEPARATOR ', ') AS authors,
          (SELECT COUNT(*) FROM Loans l JOIN Copies c ON c.copy_id = l.copy_id WHERE c.book_id = b.book_id) AS checkouts,
          (SELECT ROUND(AVG(r.rating), 1) FROM Ratings r WHERE r.book_id = b.book_id) AS avg_rating
   FROM Books b
   LEFT JOIN Genres g ON g.genre_id = b.genre_id
   LEFT JOIN Book_Authors ba ON ba.book_id = b.book_id
   LEFT JOIN Authors a ON a.author_id = ba.author_id
   LEFT JOIN Staff_Picks sp ON sp.book_id = b.book_id
   WHERE b.is_active = 1
   GROUP BY b.book_id
   ORDER BY sp.pick_id IS NULL, checkouts DESC, b.title")->fetch_all(MYSQLI_ASSOC);
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
      <h1>Staff Picks</h1>
      <p class="text-secondary mb-0">Feature books on the member dashboard and catalog. Checkout counts and ratings help show what's popular.</p>
    </div>

    <div class="table-responsive bg-white rounded shadow-sm">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Title</th><th>Checkouts</th><th>Rating</th><th>Blurb</th><th class="text-end">Action</th></tr></thead>
        <tbody>
        <?php foreach ($books as $b): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?php echo e($b['title']); ?>
                <?php if ($b['pick_id']): ?><span class="badge text-bg-warning ms-1"><i class="bi bi-star-fill"></i> Pick</span><?php endif; ?>
              </div>
              <small class="text-secondary"><?php echo e($b['authors'] !== null ? $b['authors'] : 'Unknown author'); ?><?php echo $b['genre'] ? ' &middot; ' . e($b['genre']) : ''; ?></small>
            </td>
            <td><?php echo (int)$b['checkouts']; ?></td>
            <td><?php echo $b['avg_rating'] !== null ? '<i class="bi bi-star-fill text-warning"></i> ' . e($b['avg_rating']) : '<span class="text-secondary">None</span>'; ?></td>
            <td class="small fst-italic text-secondary"><?php echo $b['review_blurb'] !== null ? e($b['review_blurb']) : ''; ?></td>
            <td class="text-end text-nowrap">
              <button class="btn btn-sm <?php echo $b['pick_id'] ? 'btn-outline-primary' : 'btn-outline-success'; ?>" type="button"
                      data-bs-toggle="modal" data-bs-target="#pick<?php echo (int)$b['book_id']; ?>">
                <?php echo $b['pick_id'] ? 'Edit blurb' : 'Make a pick'; ?>
              </button>
              <?php if ($b['pick_id']): ?>
                <form method="post" class="d-inline" data-confirm="Remove this book from staff picks?">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="book_id" value="<?php echo (int)$b['book_id']; ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php foreach ($books as $b): ?>
      <div class="modal fade" id="pick<?php echo (int)$b['book_id']; ?>" tabindex="-1" aria-labelledby="pick<?php echo (int)$b['book_id']; ?>Label" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
          <form method="post">
            <div class="modal-header">
              <h2 class="modal-title h5" id="pick<?php echo (int)$b['book_id']; ?>Label"><?php echo e($b['title']); ?></h2>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="save">
              <input type="hidden" name="book_id" value="<?php echo (int)$b['book_id']; ?>">
              <label class="form-label" for="blurb<?php echo (int)$b['book_id']; ?>">Why should members read it?</label>
              <textarea class="form-control" id="blurb<?php echo (int)$b['book_id']; ?>" name="review_blurb" rows="3" maxlength="500"><?php echo $b['review_blurb'] !== null ? e($b['review_blurb']) : ''; ?></textarea>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-library">Save pick</button>
            </div>
          </form>
        </div></div>
      </div>
    <?php endforeach; ?>
  </main>
  <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
</body>
</html>
