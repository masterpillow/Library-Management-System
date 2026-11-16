<?php
include_once(realpath(dirname(__FILE__) . '/../php/path.php'));
include_once(ROOT_SRC_PATH . '/session.php');
include_once(ROOT_SRC_PATH . '/config.php');
include_once(ROOT_SRC_PATH . '/check_admin.php');

// Handle adding a new book and author
if (isset($_POST['add_book'])) {
    $title = trim($_POST['title']);
    $release_year = (int)$_POST['release_year'];
    $genre_id = (int)$_POST['genre_id'];
    $author_name = trim($_POST['author_name']);
    
    if (!empty($title)) {
        $stmt = $db_connection->prepare("INSERT INTO Books (title, release_year, genre_id, is_active) VALUES (?, ?, ?, 1)");
        $stmt->bind_param('sii', $title, $release_year, $genre_id);
        $stmt->execute();
        $book_id = $stmt->insert_id;
        
        if (!empty($author_name)) {
            $name_parts = explode(' ', $author_name, 2);
            $first_name = $name_parts[0];
            $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
            
            $author_stmt = $db_connection->prepare("SELECT author_id FROM Authors WHERE first_name = ? AND last_name = ?");
            $author_stmt->bind_param('ss', $first_name, $last_name);
            $author_stmt->execute();
            $result = $author_stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $author_id = $row['author_id'];
            } else {
                $ins_author = $db_connection->prepare("INSERT INTO Authors (first_name, last_name) VALUES (?, ?)");
                $ins_author->bind_param('ss', $first_name, $last_name);
                $ins_author->execute();
                $author_id = $ins_author->insert_id;
            }
            
            $link_stmt = $db_connection->prepare("INSERT INTO Book_Authors (book_id, author_id) VALUES (?, ?)");
            $link_stmt->bind_param('ii', $book_id, $author_id);
            $link_stmt->execute();
        }
        
        header("Location: books.php");
        exit;
    }
}

// Handle editing an existing book and author
if (isset($_POST['edit_book'])) {
    $book_id = (int)$_POST['book_id'];
    $title = trim($_POST['title']);
    $release_year = (int)$_POST['release_year'];
    $genre_id = (int)$_POST['genre_id'];
    $author_name = trim($_POST['author_name']);
    
    // Update basic book details
    $stmt = $db_connection->prepare("UPDATE Books SET title = ?, release_year = ?, genre_id = ? WHERE book_id = ?");
    $stmt->bind_param('siii', $title, $release_year, $genre_id, $book_id);
    $stmt->execute();
    
    // Update or link author if provided
    if (!empty($author_name)) {
        $name_parts = explode(' ', $author_name, 2);
        $first_name = $name_parts[0];
        $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
        
        $author_stmt = $db_connection->prepare("SELECT author_id FROM Authors WHERE first_name = ? AND last_name = ?");
        $author_stmt->bind_param('ss', $first_name, $last_name);
        $author_stmt->execute();
        $result = $author_stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $author_id = $row['author_id'];
        } else {
            $ins_author = $db_connection->prepare("INSERT INTO Authors (first_name, last_name) VALUES (?, ?)");
            $ins_author->bind_param('ss', $first_name, $last_name);
            $ins_author->execute();
            $author_id = $ins_author->insert_id;
        }
        
        // Remove old links and insert new author link
        $del_link = $db_connection->prepare("DELETE FROM Book_Authors WHERE book_id = ?");
        $del_link->bind_param('i', $book_id);
        $del_link->execute();
        
        $link_stmt = $db_connection->prepare("INSERT INTO Book_Authors (book_id, author_id) VALUES (?, ?)");
        $link_stmt->bind_param('ii', $book_id, $author_id);
        $link_stmt->execute();
    }
    
    header("Location: books.php");
    exit;
}

// Handle deactivating / reactivating a book
if (isset($_POST['toggle_book_active'])) {
    $book_id = (int)$_POST['book_id'];
    $new_status = (int)$_POST['new_status'];
    $stmt = $db_connection->prepare("UPDATE Books SET is_active = ? WHERE book_id = ?");
    $stmt->bind_param('ii', $new_status, $book_id);
    $stmt->execute();
    header("Location: books.php");
    exit;
}

// Fetch all books
$books = $db_connection->query("
  SELECT b.book_id, b.title, b.release_year, b.genre_id, b.is_active, g.name AS genre,
         GROUP_CONCAT(CONCAT(a.first_name, ' ', a.last_name) SEPARATOR ', ') AS authors
  FROM Books b
  LEFT JOIN Genres g ON b.genre_id = g.genre_id
  LEFT JOIN Book_Authors ba ON b.book_id = ba.book_id
  LEFT JOIN Authors a ON ba.author_id = a.author_id
  GROUP BY b.book_id
  ORDER BY b.title
");

$genres = $db_connection->query("SELECT genre_id, name FROM Genres ORDER BY name");
$genres_array = [];
while ($g = $genres->fetch_assoc()) {
    $genres_array[] = $g;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Manage Books</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
  <h1 class="mb-4">Manage Books</h1>
  
  <!-- Add Book Form -->
  <div class="card mb-4 p-3 bg-light">
    <h3>Add New Book</h3>
    <form method="post" class="row g-3">
      <div class="col-md-3">
        <label class="form-label">Title</label>
        <input type="text" name="title" class="form-control" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Author Name</label>
        <input type="text" name="author_name" class="form-control" placeholder="First Last">
      </div>
      <div class="col-md-2">
        <label class="form-label">Genre</label>
        <select name="genre_id" class="form-select">
          <option value="">Select Genre</option>
          <?php foreach ($genres_array as $g): ?>
            <option value="<?= $g['genre_id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Release Year</label>
        <input type="number" name="release_year" class="form-control">
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button type="submit" name="add_book" class="btn btn-success w-100">Add Book</button>
      </div>
    </form>
  </div>

  <!-- Books Table -->
  <table class="table table-striped align-middle">
    <thead>
      <tr>
        <th>Title</th>
        <th>Authors</th>
        <th>Genre</th>
        <th>Year</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php while ($row = $books->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($row['title']) ?></td>
        <td><?= htmlspecialchars($row['authors'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['genre'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['release_year']) ?></td>
        <td><?= $row['is_active'] ? 'Active' : 'Inactive' ?></td>
        <td>
          <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['book_id'] ?>">Edit</button>
          
          <form method="post" style="display:inline">
            <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
            <input type="hidden" name="new_status" value="<?= $row['is_active'] ? 0 : 1 ?>">
            <button type="submit" name="toggle_book_active" class="btn btn-sm <?= $row['is_active'] ? 'btn-warning' : 'btn-success' ?>">
              <?= $row['is_active'] ? 'Deactivate' : 'Reactivate' ?>
            </button>
          </form>
        </td>
      </tr>

      <!-- Edit Modal -->
      <div class="modal fade" id="editModal<?= $row['book_id'] ?>" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <form method="post">
              <div class="modal-header">
                <h5 class="modal-title">Edit Book</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
                <div class="mb-3">
                  <label class="form-label">Title</label>
                  <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($row['title']) ?>" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Author Name</label>
                  <input type="text" name="author_name" class="form-control" value="<?= htmlspecialchars($row['authors'] ?? '') ?>">
                </div>
                <div class="mb-3">
                  <label class="form-label">Genre</label>
                  <select name="genre_id" class="form-select">
                    <option value="">Select Genre</option>
                    <?php foreach ($genres_array as $g): ?>
                      <option value="<?= $g['genre_id'] ?>" <?= $g['genre_id'] == $row['genre_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($g['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Release Year</label>
                  <input type="number" name="release_year" class="form-control" value="<?= htmlspecialchars($row['release_year']) ?>">
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="edit_book" class="btn btn-primary">Save Changes</button>
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