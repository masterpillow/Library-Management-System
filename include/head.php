<?php
//======================================================================
// <head> contents for every page. Set $page_title before including.
//======================================================================
include_once (realpath(dirname(__FILE__, 2) . '/php/path.php'));
$__title = isset($page_title) ? $page_title . ' | Library Management System' : 'Library Management System';
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Library Management System">
<title><?php echo htmlspecialchars($__title); ?></title>

<?php /* Google Fonts - Roboto Flex */ ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Flex:opsz,wght@8..144,100..1000&display=swap" rel="stylesheet">

<?php /* Bootstrap and Bootstrap Icons from a CDN */ ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

<?php /* App styles */ ?>
<link href="<?php echo BASE_URL; ?>/css/style.css" rel="stylesheet" type="text/css">
