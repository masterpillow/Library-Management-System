<?php
//======================================================================
// TEMPLATE: copy this to start a new page
//======================================================================
// 1. Pick who can open it with require_role(...)
// 2. Set $page_name (highlights the navbar link) and $page_title
// 3. Handle POST first: csrf_check(), do the work, flash(), redirect()
include_once (realpath(dirname(__FILE__) . '/php/session.php'));
require_role(ROLE_ADMIN, ROLE_MEMBER, ROLE_GUEST);

$page_name = '';
$page_title = 'New Page';
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
    <div class="page-header"><h1>New Page</h1></div>
    <!-- Page content goes here. Every form that changes data needs < ?php echo csrf_field(); ? > -->
  </main>
  <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
</body>
</html>
