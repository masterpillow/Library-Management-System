<?php
//======================================================================
// CATALOG: search and browse books
//======================================================================
// Members can check out, place holds, and see their own status for each
// book. Guests can browse but not borrow.

include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));
require_role(ROLE_MEMBER, ROLE_GUEST);

$page_name = 'search';
$page_title = 'Catalog';
$is_member = ((int)$user_role === ROLE_MEMBER);

expire_old_holds($db_connection);

$query = trim(isset($_GET['query']) ? $_GET['query'] : '');
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'all';
if (!in_array($mode, ['all', 'authors', 'staff'], true)) {
  $mode = 'all';
}

//----------------------------------------------------------------------
// One row per book. Authors are combined, copy counts come from
// subqueries so a book with two authors isn't counted twice.
//----------------------------------------------------------------------
$sql = "
  SELECT b.book_id, b.title, b.release_year,
         g.name AS genre,
         GROUP_CONCAT(DISTINCT CONCAT(a.first_name, ' ', a.last_name)
                      ORDER BY a.last_name SEPARATOR ', ') AS authors,
         sp.review_blurb,
         (SELECT COUNT(*) FROM Copies c WHERE c.book_id = b.book_id AND c.status = 'available') AS available,
         (SELECT COUNT(*) FROM Copies c WHERE c.book_id = b.book_id AND c.status IN ('available', 'on_loan', 'on_hold')) AS in_circulation,
         (SELECT ROUND(AVG(r.rating), 1) FROM Ratings r WHERE r.book_id = b.book_id) AS avg_rating,
         (SELECT COUNT(*) FROM Ratings r WHERE r.book_id = b.book_id) AS rating_count,
         (SELECT COUNT(*) FROM Loans l JOIN Copies c ON c.copy_id = l.copy_id
           WHERE c.book_id = b.book_id AND l.user_id = ? AND l.return_date IS NULL) AS my_loan,
         (SELECT h.status FROM Holds h
           WHERE h.book_id = b.book_id AND h.user_id = ? AND h.status IN ('waiting', 'ready') LIMIT 1) AS my_hold
  FROM Books b
  LEFT JOIN Genres g ON g.genre_id = b.genre_id
  LEFT JOIN Book_Authors ba ON ba.book_id = b.book_id
  LEFT JOIN Authors a ON a.author_id = ba.author_id
  LEFT JOIN Staff_Picks sp ON sp.book_id = b.book_id
  WHERE b.is_active = 1";
$types = 'ii';
$params = [$user_id, $user_id];

if ($query !== '') {
  // Match title, genre, or any author's full name
  $sql .= " AND (b.title LIKE ? OR g.name LIKE ?
                 OR EXISTS (SELECT 1 FROM Book_Authors ba2 JOIN Authors a2 ON a2.author_id = ba2.author_id
                            WHERE ba2.book_id = b.book_id
                              AND CONCAT(a2.first_name, ' ', a2.last_name) LIKE ?))";
  $like = '%' . $query . '%';
  $types .= 'sss';
  array_push($params, $like, $like, $like);
}
if ($mode === 'staff') {
  $sql .= " AND sp.pick_id IS NOT NULL";
}
$sql .= " GROUP BY b.book_id";
$sql .= ($mode === 'authors') ? " ORDER BY MIN(a.last_name) IS NULL, MIN(a.last_name), b.title"
                              : " ORDER BY b.title";

$stmt = $db_connection->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$headings = ['all' => 'All Books', 'authors' => 'Books by Author', 'staff' => 'Staff Picks'];

/* Keep the current mode and query when switching tabs or searching */
function tab_url($m, $q) {
  $params = ['mode' => $m];
  if ($q !== '') {
    $params['query'] = $q;
  }
  return 'search.php?' . http_build_query($params);
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

    <div class="page-header d-flex flex-wrap justify-content-between align-items-end gap-2">
      <div>
        <h1>Catalog</h1>
        <p class="text-secondary mb-0">
          <?php echo $is_member
            ? 'Search by title, author, or genre. Check out a book that has copies on the shelf, or place a hold if they are all out.'
            : 'Browse the collection. Ask a librarian to upgrade your account to borrow books.'; ?>
        </p>
      </div>
    </div>

    <form method="get" action="search.php" class="row g-2 mb-3" role="search">
      <input type="hidden" name="mode" value="<?php echo e($mode); ?>">
      <div class="col-sm-9 col-lg-10">
        <label for="query" class="visually-hidden">Search the catalog</label>
        <input type="search" class="form-control" id="query" name="query" value="<?php echo e($query); ?>"
               placeholder="Search by title, author, or genre">
      </div>
      <div class="col-sm-3 col-lg-2 d-grid">
        <button type="submit" class="btn btn-library"><i class="bi bi-search me-1"></i>Search</button>
      </div>
    </form>

    <ul class="nav nav-pills mb-3">
      <?php foreach ($headings as $m => $label): ?>
        <li class="nav-item">
          <a class="nav-link<?php echo $m === $mode ? ' active' : ''; ?>" href="<?php echo e(tab_url($m, $query)); ?>"><?php echo e($label); ?></a>
        </li>
      <?php endforeach; ?>
    </ul>

    <p class="text-secondary">
      <?php echo count($books); ?> <?php echo count($books) === 1 ? 'book' : 'books'; ?>
      <?php if ($query !== ''): ?>
        matching "<?php echo e($query); ?>" &middot; <a href="<?php echo e(tab_url($mode, '')); ?>">Clear search</a>
      <?php endif; ?>
    </p>

    <?php if (!$books): ?>
      <div class="alert alert-light border">No books found. Try a different title, author, or genre.</div>
    <?php else: ?>
      <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th scope="col">Title</th>
              <th scope="col">Author</th>
              <th scope="col">Genre</th>
              <th scope="col">Rating</th>
              <th scope="col">Availability</th>
              <?php if ($is_member): ?><th scope="col" class="text-end">Action</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($books as $b): ?>
            <tr>
              <td>
                <div class="fw-semibold"><?php echo e($b['title']); ?></div>
                <?php if ($b['release_year']): ?><small class="text-secondary"><?php echo (int)$b['release_year']; ?></small><?php endif; ?>
                <?php if ($b['review_blurb'] !== null): ?>
                  <div class="small mt-1"><span class="badge text-bg-warning"><i class="bi bi-star-fill"></i> Staff pick</span>
                    <?php if ($mode === 'staff' && $b['review_blurb'] !== ''): ?>
                      <span class="fst-italic text-secondary"><?php echo e($b['review_blurb']); ?></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td><?php echo $b['authors'] !== null ? e($b['authors']) : '<span class="text-secondary">Unknown</span>'; ?></td>
              <td><?php echo $b['genre'] !== null ? e($b['genre']) : '<span class="text-secondary">None</span>'; ?></td>
              <td class="text-nowrap">
                <?php if ($b['rating_count'] > 0): ?>
                  <i class="bi bi-star-fill text-warning"></i> <?php echo e($b['avg_rating']); ?>
                  <small class="text-secondary">(<?php echo (int)$b['rating_count']; ?>)</small>
                <?php else: ?>
                  <span class="text-secondary">No ratings</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($b['available'] > 0): ?>
                  <span class="badge text-bg-success"><?php echo (int)$b['available']; ?> of <?php echo (int)$b['in_circulation']; ?> on the shelf</span>
                <?php elseif ($b['in_circulation'] > 0): ?>
                  <span class="badge text-bg-secondary">All <?php echo (int)$b['in_circulation']; ?> checked out</span>
                <?php else: ?>
                  <span class="badge text-bg-light border">Not available</span>
                <?php endif; ?>
              </td>
              <?php if ($is_member): ?>
              <td class="text-end text-nowrap">
                <?php if ($b['my_loan'] > 0): ?>
                  <span class="badge text-bg-primary">You have this</span>
                <?php elseif ($b['my_hold'] === 'ready'): ?>
                  <form method="post" action="<?php echo e(url('php/member-checkout.php')); ?>" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="book_id" value="<?php echo (int)$b['book_id']; ?>">
                    <input type="hidden" name="back" value="search">
                    <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-bag-check me-1"></i>Pick up hold</button>
                  </form>
                <?php elseif ($b['my_hold'] === 'waiting'): ?>
                  <span class="badge text-bg-info">On the hold list</span>
                <?php elseif ($b['available'] > 0): ?>
                  <form method="post" action="<?php echo e(url('php/member-checkout.php')); ?>" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="book_id" value="<?php echo (int)$b['book_id']; ?>">
                    <input type="hidden" name="back" value="search">
                    <button type="submit" class="btn btn-sm btn-library"><i class="bi bi-bag-plus me-1"></i>Check out</button>
                  </form>
                <?php elseif ($b['in_circulation'] > 0): ?>
                  <form method="post" action="<?php echo e(url('php/member-hold.php')); ?>" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="book_id" value="<?php echo (int)$b['book_id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-hourglass-split me-1"></i>Place hold</button>
                  </form>
                <?php else: ?>
                  <span class="text-secondary small">Unavailable</span>
                <?php endif; ?>
              </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </main>
  <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
</body>
</html>
