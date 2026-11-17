<?php
//======================================================================
// ADMIN: manage books (add, edit, deactivate / reactivate)
//======================================================================
// Books are never deleted, so loan history stays intact. A deactivated
// book disappears from the catalog but keeps its records.

include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));
require_role(ROLE_ADMIN);

$page_name = 'books';
$page_title = 'Manage Books';

/* "Harper Lee, George Orwell" -> list of author_ids, creating new authors as needed */
function author_ids_from_text(mysqli $db, $text) {
  $ids = [];
  foreach (explode(',', $text) as $name) {
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if ($name === '') {
      continue;
    }
    // Last word is the last name: "F. Scott Fitzgerald" -> "F. Scott" + "Fitzgerald"
    $pos = strrpos($name, ' ');
    $first = $pos === false ? $name : substr($name, 0, $pos);
    $last = $pos === false ? '' : substr($name, $pos + 1);

    $f = $db->prepare("SELECT author_id FROM Authors WHERE first_name = ? AND last_name = ? LIMIT 1");
    $f->bind_param('ss', $first, $last);
    $f->execute();
    $row = $f->get_result()->fetch_assoc();
    $f->close();
    if ($row) {
      $ids[] = (int)$row['author_id'];
    } else {
      $i = $db->prepare("INSERT INTO Authors (first_name, last_name) VALUES (?, ?)");
      $i->bind_param('ss', $first, $last);
      $i->execute();
      $ids[] = $i->insert_id;
      $i->close();
    }
  }
  return array_values(array_unique($ids));
}

function set_book_authors(mysqli $db, $book_id, array $author_ids) {
  $d = $db->prepare("DELETE FROM Book_Authors WHERE book_id = ?");
  $d->bind_param('i', $book_id);
  $d->execute();
  $d->close();
  $l = $db->prepare("INSERT INTO Book_Authors (book_id, author_id) VALUES (?, ?)");
  foreach ($author_ids as $aid) {
    $l->bind_param('ii', $book_id, $aid);
    $l->execute();
  }
  $l->close();
}

/* Read and validate the add/edit form. Returns [fields, error] */
function book_form_input() {
  $title = trim(isset($_POST['title']) ? $_POST['title'] : '');
  $authors = trim(isset($_POST['authors']) ? $_POST['authors'] : '');
  $genre_id = (int)(isset($_POST['genre_id']) ? $_POST['genre_id'] : 0);
  $year_text = trim(isset($_POST['release_year']) ? $_POST['release_year'] : '');
  $isbn = preg_replace('/[^0-9Xx]/', '', isset($_POST['isbn']) ? $_POST['isbn'] : '');

  if ($title === '') {
    return [null, 'Title is required.'];
  }
  if ($year_text !== '' && (!ctype_digit($year_text) || (int)$year_text < 1000 || (int)$year_text > (int)date('Y') + 1)) {
    return [null, 'Release year must be a 4-digit year.'];
  }
  if ($isbn !== '' && strlen($isbn) !== 10 && strlen($isbn) !== 13) {
    return [null, 'ISBN must be 10 or 13 digits.'];
  }
  return [[
    'title' => $title,
    'authors' => $authors,
    'genre_id' => $genre_id > 0 ? $genre_id : null,
    'release_year' => $year_text === '' ? null : (int)$year_text,
    'isbn' => $isbn === '' ? null : strtoupper($isbn),
  ], null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check('admin/books.php');
  $action = isset($_POST['action']) ? $_POST['action'] : '';

  if ($action === 'add' || $action === 'edit') {
    list($in, $err) = book_form_input();
    if ($err) {
      flash('danger', $err);
      redirect('admin/books.php');
    }
    $db_connection->begin_transaction();
    try {
      if ($action === 'add') {
        $s = $db_connection->prepare("INSERT INTO Books (title, isbn, genre_id, release_year, is_active) VALUES (?, ?, ?, ?, 1)");
        $s->bind_param('ssii', $in['title'], $in['isbn'], $in['genre_id'], $in['release_year']);
        $s->execute();
        $book_id = $s->insert_id;
        $s->close();
      } else {
        $book_id = (int)$_POST['book_id'];
        $s = $db_connection->prepare("UPDATE Books SET title = ?, isbn = ?, genre_id = ?, release_year = ? WHERE book_id = ?");
        $s->bind_param('ssiii', $in['title'], $in['isbn'], $in['genre_id'], $in['release_year'], $book_id);
        $s->execute();
        $s->close();
      }
      set_book_authors($db_connection, $book_id, author_ids_from_text($db_connection, $in['authors']));
      $db_connection->commit();
      flash('success', ($action === 'add' ? 'Added "' : 'Saved "') . $in['title'] . '".'
            . ($action === 'add' ? ' Add copies on the Copies page so members can borrow it.' : ''));
    } catch (mysqli_sql_exception $ex) {
      $db_connection->rollback();
      flash('danger', $ex->getCode() == 1062 ? 'Another book already has that ISBN.' : 'The book could not be saved.');
    }
    redirect('admin/books.php');
  }

  if ($action === 'toggle') {
    $book_id = (int)$_POST['book_id'];
    $active = (int)$_POST['active'] === 1 ? 1 : 0;
    $s = $db_connection->prepare("UPDATE Books SET is_active = ? WHERE book_id = ?");
    $s->bind_param('ii', $active, $book_id);
    $s->execute();
    $s->close();
    flash('success', $active ? 'The book is back in the catalog.' : 'The book was deactivated and no longer shows in the catalog. Its history is kept.');
    redirect('admin/books.php');
  }
  redirect('admin/books.php');
}

$search = trim(isset($_GET['search']) ? $_GET['search'] : '');
$sql = "SELECT b.book_id, b.title, b.isbn, b.release_year, b.genre_id, b.is_active, g.name AS genre,
               GROUP_CONCAT(DISTINCT CONCAT(a.first_name, ' ', a.last_name) ORDER BY a.last_name SEPARATOR ', ') AS authors,
               (SELECT COUNT(*) FROM Copies c WHERE c.book_id = b.book_id AND c.status IN ('available', 'on_loan', 'on_hold')) AS copies,
               (SELECT COUNT(*) FROM Copies c WHERE c.book_id = b.book_id AND c.status = 'available') AS on_shelf
        FROM Books b
        LEFT JOIN Genres g ON g.genre_id = b.genre_id
        LEFT JOIN Book_Authors ba ON ba.book_id = b.book_id
        LEFT JOIN Authors a ON a.author_id = ba.author_id";
if ($search !== '') {
  $sql .= " WHERE b.title LIKE ? OR b.isbn LIKE ?
              OR EXISTS (SELECT 1 FROM Book_Authors ba2 JOIN Authors a2 ON a2.author_id = ba2.author_id
                         WHERE ba2.book_id = b.book_id AND CONCAT(a2.first_name, ' ', a2.last_name) LIKE ?)";
}
$sql .= " GROUP BY b.book_id ORDER BY b.is_active DESC, b.title";
$s = $db_connection->prepare($sql);
if ($search !== '') {
  $like = '%' . $search . '%';
  $s->bind_param('sss', $like, $like, $like);
}
$s->execute();
$books = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$s->close();

$genres = $db_connection->query("SELECT genre_id, name FROM Genres ORDER BY name")->fetch_all(MYSQLI_ASSOC);

/* Shared add/edit form fields */
function book_fields($genres, $b = null, $prefix = 'new') {
  $v = function ($k) use ($b) { return $b ? e($b[$k]) : ''; };
  ob_start(); ?>
  <div class="col-md-6">
    <label class="form-label" for="<?php echo $prefix; ?>-title">Title</label>
    <input type="text" class="form-control" id="<?php echo $prefix; ?>-title" name="title" value="<?php echo $v('title'); ?>" required maxlength="255">
  </div>
  <div class="col-md-6">
    <label class="form-label" for="<?php echo $prefix; ?>-authors">Authors</label>
    <input type="text" class="form-control" id="<?php echo $prefix; ?>-authors" name="authors" value="<?php echo $v('authors'); ?>" placeholder="First Last, First Last">
    <div class="form-text">Separate multiple authors with commas.</div>
  </div>
  <div class="col-md-4">
    <label class="form-label" for="<?php echo $prefix; ?>-genre">Genre</label>
    <select class="form-select" id="<?php echo $prefix; ?>-genre" name="genre_id">
      <option value="">None</option>
      <?php foreach ($genres as $g): ?>
        <option value="<?php echo (int)$g['genre_id']; ?>" <?php echo ($b && (int)$b['genre_id'] === (int)$g['genre_id']) ? 'selected' : ''; ?>><?php echo e($g['name']); ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label" for="<?php echo $prefix; ?>-year">Release year</label>
    <input type="number" class="form-control" id="<?php echo $prefix; ?>-year" name="release_year" value="<?php echo $v('release_year'); ?>" min="1000" max="<?php echo date('Y') + 1; ?>">
  </div>
  <div class="col-md-4">
    <label class="form-label" for="<?php echo $prefix; ?>-isbn">ISBN</label>
    <input type="text" class="form-control" id="<?php echo $prefix; ?>-isbn" name="isbn" value="<?php echo $v('isbn'); ?>" placeholder="Optional">
  </div>
  <?php return ob_get_clean();
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
        <h1>Books</h1>
        <p class="text-secondary mb-0">Add titles to the catalog, edit their details, or take them out of circulation.</p>
      </div>
      <button class="btn btn-library" type="button" data-bs-toggle="collapse" data-bs-target="#addBook" aria-expanded="false" aria-controls="addBook">
        <i class="bi bi-plus-lg me-1"></i>Add a book
      </button>
    </div>

    <div class="collapse mb-4" id="addBook">
      <div class="card shadow-sm border-0"><div class="card-body">
        <h2 class="h5 mb-3">Add a book</h2>
        <form method="post" class="row g-3">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="add">
          <?php echo book_fields($genres); ?>
          <div class="col-12"><button type="submit" class="btn btn-library">Add book</button></div>
        </form>
      </div></div>
    </div>

    <form method="get" class="row g-2 mb-3">
      <div class="col-sm-9 col-lg-10">
        <input type="search" class="form-control" name="search" value="<?php echo e($search); ?>" placeholder="Search by title, author, or ISBN" aria-label="Search books">
      </div>
      <div class="col-sm-3 col-lg-2 d-grid"><button class="btn btn-outline-secondary" type="submit">Search</button></div>
    </form>

    <div class="table-responsive bg-white rounded shadow-sm">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr><th>Title</th><th>Authors</th><th>Genre</th><th>Year</th><th>Copies</th><th>Status</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
        <?php if (!$books): ?>
          <tr><td colspan="7" class="text-center text-secondary py-4">No books found.</td></tr>
        <?php endif; ?>
        <?php foreach ($books as $b): ?>
          <tr class="<?php echo $b['is_active'] ? '' : 'table-secondary'; ?>">
            <td>
              <div class="fw-semibold"><?php echo e($b['title']); ?></div>
              <?php if ($b['isbn']): ?><small class="text-secondary">ISBN <?php echo e($b['isbn']); ?></small><?php endif; ?>
            </td>
            <td><?php echo $b['authors'] !== null ? e($b['authors']) : '<span class="text-secondary">None</span>'; ?></td>
            <td><?php echo $b['genre'] !== null ? e($b['genre']) : '<span class="text-secondary">None</span>'; ?></td>
            <td><?php echo $b['release_year'] ? (int)$b['release_year'] : ''; ?></td>
            <td><a href="copies.php?search=<?php echo urlencode($b['title']); ?>"><?php echo (int)$b['on_shelf']; ?> of <?php echo (int)$b['copies']; ?> on shelf</a></td>
            <td><?php echo $b['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>'; ?></td>
            <td class="text-end text-nowrap">
              <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#edit<?php echo (int)$b['book_id']; ?>">Edit</button>
              <form method="post" class="d-inline"
                    data-confirm="<?php echo $b['is_active'] ? 'Remove this book from the catalog? Its loan history is kept.' : 'Put this book back in the catalog?'; ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="book_id" value="<?php echo (int)$b['book_id']; ?>">
                <input type="hidden" name="active" value="<?php echo $b['is_active'] ? 0 : 1; ?>">
                <button type="submit" class="btn btn-sm <?php echo $b['is_active'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>">
                  <?php echo $b['is_active'] ? 'Deactivate' : 'Reactivate'; ?>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php foreach ($books as $b): ?>
      <div class="modal fade" id="edit<?php echo (int)$b['book_id']; ?>" tabindex="-1" aria-labelledby="edit<?php echo (int)$b['book_id']; ?>Label" aria-hidden="true">
        <div class="modal-dialog modal-lg"><div class="modal-content">
          <form method="post">
            <div class="modal-header">
              <h2 class="modal-title h5" id="edit<?php echo (int)$b['book_id']; ?>Label">Edit "<?php echo e($b['title']); ?>"</h2>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body row g-3">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="edit">
              <input type="hidden" name="book_id" value="<?php echo (int)$b['book_id']; ?>">
              <?php echo book_fields($genres, $b, 'b' . (int)$b['book_id']); ?>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-library">Save changes</button>
            </div>
          </form>
        </div></div>
      </div>
    <?php endforeach; ?>
  </main>
  <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
</body>
</html>
