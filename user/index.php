<?php
/**
 * User Index Page
 *
 * @author    Joshua Connor <connorj4@southernct.edu>
 * @copyright 2026 
 * @version   1.1
 */
error_reporting(E_ALL);
ini_set('display_errors', 0); // set to 1 to display errors, 0 to hide them

  /* Quick Paths */
  include_once (realpath(dirname(__FILE__, 2).'/php/session.php'));
  include_once (realpath(dirname(__FILE__, 2).'/php/path.php'));
  
  /* Check Role */
  include_once (ROOT_SRC_PATH . '/check_user.php'); // Ensures user is Role 2 (Member)

  $user_check = $_SESSION['login_user'];

  /* Page Name */
  $page_name = "user"; // Fixed: Set to "user" so navigation highlights correctly

?>
<!doctype html>
<html lang="en">
  <head>
  <?php include_once (ROOT_PATH . '/include/head.php'); // Include head.php ?>
  </head>
  <body class="<?php echo $page_name; ?>">

  <?php include_once (ROOT_PATH . '/include/header.php'); // Include header.php ?>
    <main role="main" class="container py-4">

      <div class="text-center mb-5">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user_first']); ?>!</h1>
        <p class="lead">This is your member portal for the Library Management System.</p>
      </div>

      <!-- Quick Action Cards (Added Search & Loans Navigation) -->
      <div class="row g-4 mb-5">
        <div class="col-md-6">
          <div class="card h-100 shadow-sm border-0 text-start">
            <div class="card-body p-4">
              <h5 class="card-title text-primary fw-bold">Search Catalog</h5>
              <p class="card-text text-secondary">Search books by title, author, or genre to check out available copies or place holds.</p>
              <a href="search.php" class="btn btn-primary">Go to Search Catalog</a>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card h-100 shadow-sm border-0 text-start">
            <div class="card-body p-4">
              <h5 class="card-title text-success fw-bold">My Active Loans</h5>
              <p class="card-text text-secondary">View items currently checked out to your account and track upcoming due dates.</p>
              <a href="loans.php" class="btn btn-outline-success">View My Loans</a>
            </div>
          </div>
        </div>
      </div>

      <hr class="mb-5">

      <!-- User Information Section (Preserved from starter code) -->
      <section class="mb-4">
        <h2 class="h4 mb-3">User Profile Information</h2>
        <div class="table-responsive">
          <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
              <tr>
                <th scope="col">User ID</th>
                <th scope="col">First</th>
                <th scope="col">Last</th>
                <th scope="col">Email</th>
                <th scope="col">Username</th>
                <th scope="col">Role</th>
              </tr>
            </thead>
            <tbody>
              <?php
                include_once (ROOT_PATH . '/php/config.php');

                $select_info = $db_connection->prepare(
                  "SELECT 
                      u.user_id,
                      c.first_name,
                      c.last_name,
                      c.email,
                      cr.username,
                      r.role_type
                  FROM Users u
                  INNER JOIN Contacts c ON u.contact_id = c.contact_id
                  INNER JOIN Credentials cr ON u.user_id = cr.user_id
                  INNER JOIN Roles r ON u.role_id = r.role_id
                  WHERE cr.username = ?");
                $select_info->bind_param("s", $user_check);
                $select_info->execute();
                $result = $select_info->get_result();

                if ($result && $result->num_rows > 0) {
                  while($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<th scope='row'>" . htmlspecialchars($row["user_id"]) . "</th>";
                    echo "<td>" . htmlspecialchars($row["first_name"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["last_name"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["email"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["username"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["role_type"]) . "</td>";
                    echo "</tr>";
                  }
                } else {
                  echo "<tr><td colspan='6'>No user details found</td></tr>";
                }
                $select_info->close();
              ?>
            </tbody>
          </table>
        </div>
      </section>
       
    </main>
    <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
  </body>
</html>
