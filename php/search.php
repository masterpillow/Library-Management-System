<?php
require_once 'path.php';
require_once 'config.php';

$searchQuery = "";
$books = [];

if (isset($_GET['query']) && trim($_GET['query']) !== '') {
    $searchQuery = trim($_GET['query']);

    try {
        $sql = "SELECT DISTINCT b.*, a.first_name, a.last_name, g.name AS genre_name 
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

    } catch (mysqli_sql_exception $e) {
        $error = "Database error: " . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Library Book Search</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
            color: #333;
            background-color: #f9f9fb;
        }
        h1, h2 {
            color: #111;
        }
        form {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }
        input[type="text"] {
            flex: 1;
            padding: 10px 14px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        button[type="submit"] {
            padding: 10px 20px;
            font-size: 16px;
            background-color: #0066cc;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        button[type="submit"]:hover {
            background-color: #0052a3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 6px;
            overflow: hidden;
        }
        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background-color: #f1f3f5;
            font-weight: 600;
        }
        tr:last-child td {
            border-bottom: none;
        }
        .error {
            color: #d93838;
            background: #fdf2f2;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #f8d7da;
        }
        .btn-action {
            display: inline-block;
            padding: 6px 12px;
            font-size: 14px;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 6px;
            font-weight: 500;
        }
        .btn-checkout {
            background-color: #2b8a3e;
            color: white;
        }
        .btn-checkout:hover {
            background-color: #2b8a3e;
            opacity: 0.9;
        }
        .btn-hold {
            background-color: #e67700;
            color: white;
        }
        .btn-hold:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <h1>Library Book Search</h1>

    <form method="GET" action="search.php">
        <input type="text" name="query" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search by title, author, or genre..." required>
        <button type="submit">Search</button>
    </form>

    <?php if (isset($error)): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (!empty($searchQuery)): ?>
        <h2>Results for "<?php echo htmlspecialchars($searchQuery); ?>"</h2>
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
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($bookId); ?></td>
                            <td><?php echo htmlspecialchars($book['title'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars(trim(($book['first_name'] ?? '') . ' ' . ($book['last_name'] ?? ''))); ?></td>
                            <td><?php echo htmlspecialchars($book['genre_name'] ?? $book['genre'] ?? ''); ?></td>
                            <td>
                                <a href="checkout.php?book_id=<?php echo urlencode($bookId); ?>" class="btn-action btn-checkout">Checkout</a>
                                <a href="hold.php?book_id=<?php echo urlencode($bookId); ?>" class="btn-action btn-hold">Hold</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No books found matching your search.</p>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>