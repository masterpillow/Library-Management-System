<?php
//======================================================================
// ERROR AND MESSAGE REPORTS
//======================================================================

  /* Error Report */
  if (isset($error)) {
    // uses bootstrap alert style for error messages
    echo '<div class="alert alert-danger" role="alert">' . $error . '</div>';
  }
  if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']); // show it once
  }
  /* Message Report */
  if (isset($message)) {
    // uses bootstrap alert style for error messages
    echo '<div class="alert alert-warning" role="warning">' . $message . '</div>';
  }
  if (isset($_SESSION['message'])) {
    // uses bootstrap alert style for error messages
    echo '<div class="alert alert-warning" role="warning">' . htmlspecialchars($_SESSION['message']) . '</div>';
    unset($_SESSION['message']); // show it once
  }
?>