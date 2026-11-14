<?php
// ======================================================================
// USER SEARCH CATALOG (Unified Browsing Modes & Search Query Handler)
// ======================================================================
require_once '../php/path.php';
require_once '../php/session.php';
require_once '../php/config.php';

// Role Access Control (Member / Role 2 Only)
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 2) {
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

$searchQuery = trim($_GET['query'] ?? '');
$browse_mode = $_GET['mode'] ?? 'all';
$books = [];
$error = '';

try {
    if ($browse_mode === 'staff') {
        $sql = "SELECT DISTINCT b.*, a.first_name, a.last_name, g.name AS genre_name,
                       (SELECT copy_id FROM Copies WHERE book_id = b.book_id AND status = 'available' LIMIT 1) AS available_copy_id
                FROM Books b 
                LEFT JOIN Book_Authors ba ON b.book_id = ba.book_id 
                LEFT JOIN authors a ON ba.author_id = a.author_id 
                LEFT JOIN genres g ON b.genre_id = g.genre_id 
                WHERE b.is_staff_pick = 1
                ORDER BY b.title ASC";
        $stmt = $db_connection->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $books = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } elseif ($browse_mode === 'authors') {
        $sql = "SELECT DISTINCT b.*, a.first_name, a.last_name, g.name AS genre_name,
                       (SELECT copy_id FROM Copies WHERE book_id = b.book_id AND status = 'available' LIMIT 1) AS available_copy_id
                FROM Books b 
                LEFT JOIN Book_Authors ba ON b.book_id = ba.book_id 
                LEFT JOIN authors a ON ba.author_id = a.author_id 
                LEFT JOIN genres g ON b.genre_id = g.genre_id 
                ORDER BY a.last_name ASC, b.title ASC";
        $stmt = $db_connection->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $books = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } elseif (!empty($searchQuery)) {
        $sql = "SELECT DISTINCT b.*, a.first_name, a.last_name, g.name AS genre_name,
                       (SELECT copy_id FROM Copies WHERE book_id = b.book_id AND status = 'available' LIMIT 1) AS available_copy_id
                FROM Books b 
                LEFT JOIN Book_Authors ba ON b.book_id = ba.book_id 
                LEFT JOIN authors a ON ba.author_id = a.author_id 
                LEFT JOIN genres g ON b.genre_id = g.genre_id 
                WHERE b.title LIKE ? OR g.name LIKE ? OR a.first_name LIKE ? OR a.last_name LIKE ?";
        $stmt = $db_connection->prepare($sql);
        $wildcardQuery = '%' . $searchQuery . '%';
        $stmt->bind_param("ssss", $wildcardQuery, $wildcardQuery, $wildcardQuery, $wildcardQuery);
        $stmt->execute();
        $result = $stmt->get_result();
        $books = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $sql = "SELECT DISTINCT b.*, a.first_name, a.last_name, g.name AS genre_name,
                       (SELECT copy_id FROM Copies WHERE book_id = b.book_id AND status = 'available' LIMIT 1) AS available_copy_id
                FROM Books b 
                LEFT JOIN Book_Authors ba ON b.book_id = ba.book_id 
                LEFT JOIN authors a ON ba.author_id = a.author_id 
                LEFT JOIN genres g ON b.genre_id = g.genre_id 
                ORDER BY b.title ASC";
        $stmt = $db_connection->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $books = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) {
    $error = "Database error: " . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Library Book Search</title>
    <style>
        body { font-family: sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; background: #f9f9fb; color: #333; }
        .nav-back { margin-bottom: 20px; }
        .nav-back a { color: #0066cc; text-decoration: none; font-weight: 500; }
        .nav-back a:hover { text-decoration: underline; }
        .browse-tabs { margin-bottom: 20px; display: flex; gap: 10px; }
        .btn-tab { padding: 8px 16px; background: #e9ecef; color: #333; border-radius: 4px; text-decoration: none; font-weight: 500; }
        .btn-tab.active { background: #0066cc; color: white; }
        form { display: flex; gap: 10px; margin-bottom: 30px; }
        input[type="text"] { flex: 1; padding: 10px; font-size: 16px; border: 1px solid #ccc; border-radius: 6px; }
        button[type="submit"] { padding: 10px 20px; font-size: 16px; background: #0066cc; color: white; border: none; border-radius: 6px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 6px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f1f3f5; }
        .btn-action { display: inline-block; padding: 6px 12px; font-size: 14px; text-decoration: none; border-radius: 4px; margin-right: 6px; font-weight: 500; color: white; }
        .btn-checkout { background: #2b8a3e; }
        .btn-hold { background: #e67700; }
        .error { color: #d93838; background: #fdf2f2; padding: 10px; border-radius: 6px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="nav-back">
        <a href="index.php">&larr; Back to Member Dashboard</a>
    </div>

    <h1>Library Book Search</h1>

    <!-- Browsing Mode Tabs -->
    <div class="browse-tabs">
        <a href="search.php?mode=all" class="btn-tab <?php echo ($browse_mode === 'all') ? 'active' : ''; ?>">All Books</a>
        <a href="search.php?mode=authors" class="btn-tab <?php echo ($browse_mode === 'authors') ? 'active' : ''; ?>">Browse by Author</a>
        <a href="search.php?mode=staff" class="btn-tab <?php echo ($browse_mode === 'staff') ? 'active' : ''; ?>">Staff Recommendations</a>
    </div>

    <form method="GET" action="search.php">
        <input type="hidden" name="mode" value="<?php echo htmlspecialchars($browse_mode); ?>">
        <input type="text" name="query" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search by title, author, or genre...">
        <button type="submit">Search</button>
    </form>

    <?php if (!empty($error)): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <h2>
        <?php 
            if ($browse_mode === 'staff') echo "Staff Recommendations";
            elseif ($browse_mode === 'authors') echo "Catalog Sorted by Author";
            elseif (!empty($searchQuery)) echo "Search Results for \"" . htmlspecialchars($searchQuery) . "\"";
            else echo "All Library Books";
        ?>
    </h2>

    <?php if (count($books) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Genre</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($books as $book): 
                    $bookId = $book['book_id'] ?? $book['id'] ?? '';
                    $copyId = $book['available_copy_id'] ?? '';
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($bookId); ?></td>
                        <td><?php echo htmlspecialchars($book['title'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars(trim(($book['first_name'] ?? '') . ' ' . ($book['last_name'] ?? ''))); ?></td>
                        <td><?php echo htmlspecialchars($book['genre_name'] ?? ''); ?></td>
                        <td>
                            <?php if (!empty($copyId)): ?>
                                <a href="../php/member-checkout.php?copy_id=<?php echo urlencode($copyId); ?>" class="btn-action btn-checkout">Checkout</a>
                            <?php else: ?>
                                <span class="btn-action" style="background: #adb5bd; cursor: not-allowed;">Unavailable</span>
                            <?php endif; ?>
                            
                            <a href="../php/member-hold.php?book_id=<?php echo urlencode($bookId); ?>" class="btn-action btn-hold">Hold</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No books found matching your selection.</p>
    <?php endif; ?>
</body>
</html>