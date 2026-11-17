<?php
//======================================================================
// Navigation bar for every page. Links depend on the logged-in role.
// Set $page_name before including to highlight the current page.
//======================================================================
include_once (realpath(dirname(__FILE__, 2) . '/php/helpers.php'));

$__role = isset($_SESSION['user_role']) ? (int)$_SESSION['user_role'] : 0;
$__logged_in = $__role > 0 && isset($_SESSION['user_first']);
$__current = isset($page_name) ? $page_name : '';

/* [page_name, label, path, icon] per role */
$__links = [];
if ($__logged_in && $__role === ROLE_ADMIN) {
  $__links = [
    ['admin',       'Dashboard',   'admin/index.php',       'speedometer2'],
    ['books',       'Books',       'admin/books.php',       'book'],
    ['copies',      'Copies',      'admin/copies.php',      'upc-scan'],
    ['returns',     'Returns',     'admin/returns.php',     'arrow-return-left'],
    ['members',     'Members',     'admin/members.php',     'people'],
    ['staff-picks', 'Staff Picks', 'admin/staff-picks.php', 'star'],
  ];
} elseif ($__logged_in && $__role === ROLE_MEMBER) {
  $__links = [
    ['user',    'Dashboard',  'user/index.php',   'house'],
    ['search',  'Catalog',    'user/search.php',  'search'],
    ['loans',   'My Loans',   'user/loans.php',   'journal-bookmark'],
    ['account', 'My Account', 'user/account.php', 'person-circle'],
  ];
} elseif ($__logged_in && $__role === ROLE_GUEST) {
  $__links = [
    ['guest',  'Dashboard', 'guest/index.php', 'house'],
    ['search', 'Browse Catalog', 'user/search.php', 'search'],
  ];
} else {
  $__links = [
    ['home',   'Log In',         'index.php',          'box-arrow-in-right'],
    ['signup', 'Create Account', 'create_account.php', 'person-plus'],
  ];
}
?>
<header class="mb-4">
<nav class="navbar navbar-expand-lg navbar-dark bg-library shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="<?php echo e(url($__logged_in ? home_for_role($__role) : 'index.php')); ?>">
      <i class="bi bi-book-half me-1"></i> Library Management System
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php foreach ($__links as $l): ?>
          <li class="nav-item">
            <a class="nav-link<?php echo $l[0] === $__current ? ' active' : ''; ?>"
               <?php echo $l[0] === $__current ? 'aria-current="page"' : ''; ?>
               href="<?php echo e(url($l[2])); ?>">
              <?php echo e($l[1]); ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($__logged_in): ?>
        <span class="navbar-text me-3">
          <i class="bi bi-person me-1"></i><?php echo e($_SESSION['user_first']); ?>
          <span class="badge text-bg-light ms-1"><?php echo e([ROLE_ADMIN => 'Admin', ROLE_MEMBER => 'Member', ROLE_GUEST => 'Guest'][$__role] ?? ''); ?></span>
        </span>
        <a class="btn btn-outline-light btn-sm" href="<?php echo e(url('php/logout.php')); ?>">
          <i class="bi bi-box-arrow-right me-1"></i>Log Out
        </a>
      <?php endif; ?>
    </div>
  </div>
</nav>
</header>
