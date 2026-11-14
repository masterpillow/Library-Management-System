<?php
include_once(realpath(dirname(__FILE__) . '/../php/path.php'));
include_once(ROOT_SRC_PATH . '/session.php');
include_once(ROOT_SRC_PATH . '/config.php');
include_once(ROOT_SRC_PATH . '/check_admin.php');

$books = $db_connection->query("
  SELECT b.book_id, b.title, b.release_year, g.name AS genre,
         GROUP_CONCAT(CONCAT(a.first_name, ' ', a.last_name) SEPARATOR ', ') AS authors
  FROM Books b
  LEFT JOIN Genres g ON b.genre_id = g.genre_id
  LEFT JOIN Book_Authors ba ON b.book_id = ba.book_id
  LEFT JOIN Authors a ON ba.author_id = a.author_id
  WHERE b.is_active = 1
  GROUP BY b.book_id
  ORDER BY b.title
");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Manage Books</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
  <h1>Manage Books</h1>
  <table class="table table-striped">
    <thead><tr><th>Title</th><th>Authors</th><th>Genre</th><th>Year</th></tr></thead>
    <tbody>
    <?php while ($row = $books->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($row['title']) ?></td>
        <td><?= htmlspecialchars($row['authors'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['genre'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['release_year']) ?></td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
  <a href="index.php">Back to Admin</a>
</body>
</html>