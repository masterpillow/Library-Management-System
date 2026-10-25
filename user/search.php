<?php
// Step 1: Session & Path Initialization
require_once '../php/path.php';
require_once ROOT_PATH . '/php/session.php';

// Step 2: Role Access Control (Member / Role 2 Only)
if ($_SESSION['user_role'] != 2) {
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

$search_results = [];
$search_term = '';

// Step 3: Handle Search Query Submission
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['query'])) {
    $search_term = trim($_GET['query']);
    
    // MariaDB Prepared Statement joining Books, Authors, Genres, and Copies
    $sql = "SELECT 
                b.book_id, 
                b.title, 
                b.isbn, 
                CONCAT(a.first_name, ' ', a.last_name) AS author_name, 
                g.name AS genre_name,
                COUNT(c.copy_id) AS total_copies,
                SUM(CASE WHEN c.status = 'available' THEN 1 ELSE 0 END) AS available_copies
            FROM Books b
            JOIN Book_Authors ba ON b.book_id = ba.book_id
            JOIN Authors a ON ba.author_id = a.author_id
            JOIN Genres g ON b.genre_id = g.genre_id
            LEFT JOIN Copies c ON b.book_id = c.book_id
            WHERE b.is_active = 1 
              AND (b.title LIKE ? OR a.first_name LIKE ? OR a.last_name LIKE ? OR g.name LIKE ?)
            GROUP BY b.book_id, b.title, b.isbn, author_name, g.name";

    $stmt = $db_connection->prepare($sql);
    $like_term = '%' . $search_term . '%';
    
    // Bind search parameters safely to prevent SQL injection
    $stmt->bind_param("ssss", $like_term, $like_term, $like_term, $like_term);
    $stmt->execute();
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $search_results[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Catalog - Library System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">

    <!-- Header Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">Library System</a>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">Welcome, <?= htmlspecialchars($_SESSION['user_first']) ?></span>
                <a href="../php/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Search Library Catalog</h2>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">← Back to Dashboard</a>
                </div>
                
                <!-- Search Form -->
                <form action="search.php" method="GET" class="card p-3 shadow-sm mb-4">
                    <div class="input-group">
                        <input type="text" name="query" class="form-control form-control-lg" 
                               placeholder="Search by title, author, or genre..." 
                               value="<?= htmlspecialchars($search_term) ?>" required>
                        <button type="submit" class="btn btn-primary btn-lg">Search Catalog</button>
                    </div>
                </form>

                <!-- Results Output -->
                <?php if (!empty($search_term)): ?>
                    <h5 class="mb-3">Search Results for "<?= htmlspecialchars($search_term) ?>"</h5>
                    
                    <?php if (count($search_results) > 0): ?>
                        <div class="card shadow-sm">
                            <div class="card-body p-0">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Title</th>
                                            <th>Author</th>
                                            <th>Genre</th>
                                            <th>ISBN</th>
                                            <th>Availability</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($search_results as $book): ?>
                                            <tr>
                                                <td class="fw-bold"><?= htmlspecialchars($book['title']) ?></td>
                                                <td><?= htmlspecialchars($book['author_name']) ?></td>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars($book['genre_name'] ?? 'General') ?></span></td>
                                                <td><code><?= htmlspecialchars($book['isbn'] ?? 'N/A') ?></code></td>
                                                <td>
                                                    <?php if ($book['available_copies'] > 0): ?>
                                                        <span class="badge bg-success"><?= $book['available_copies'] ?> Available</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">0 Available (On Loan)</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <?php if ($book['available_copies'] > 0): ?>
                                                        <a href="checkout.php?book_id=<?= $book['book_id'] ?>" class="btn btn-sm btn-primary">Check Out</a>
                                                    <?php else: ?>
                                                        <a href="hold.php?book_id=<?= $book['book_id'] ?>" class="btn btn-sm btn-outline-secondary">Place Hold</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning shadow-sm">No catalog items matched your search query. Try searching for "Orwell", "Classic", or "Gatsby".</div>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </div>
    </div>

</body>
</html>