<?php
//======================================================================
// SHARED HELPERS
//======================================================================
// Included by php/session.php, so every logged-in page has these.
// Public pages (login, signup, password reset) include it directly.

include_once (__DIR__ . '/path.php');

/* Library rules (also listed on the Business Rules slide) */
const FINE_PER_DAY = 0.25;       // late fee per day, charged when a copy comes back
const HOLD_PICKUP_DAYS = 3;      // how long a returned copy is set aside for a hold
const MIN_PASSWORD_LENGTH = 8;

/* Roles, matching the Roles table */
const ROLE_ADMIN = 1;
const ROLE_MEMBER = 2;
const ROLE_GUEST = 3;

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/* Escape a value for HTML output */
if (!function_exists('e')) {
  function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES);
  }
}

/* Absolute URL inside the app, e.g. url('user/search.php') */
function url($path = '') {
  return BASE_URL . '/' . ltrim($path, '/');
}

/* Redirect and stop */
function redirect($path) {
  header("Location: " . url($path));
  exit();
}

/* Only let these roles through; everyone else is sent to their own home page */
function require_role(...$roles) {
  $role = isset($_SESSION['user_role']) ? (int)$_SESSION['user_role'] : 0;
  if (!in_array($role, $roles, true)) {
    flash('danger', "You don't have access to that page.");
    redirect(home_for_role($role));
  }
}

function home_for_role($role) {
  switch ((int)$role) {
    case ROLE_ADMIN:  return 'admin/index.php';
    case ROLE_MEMBER: return 'user/index.php';
    case ROLE_GUEST:  return 'guest/index.php';
    default:          return 'index.php';
  }
}

//----------------------------------------------------------------------
// Flash messages: set before a redirect, shown once on the next page
//----------------------------------------------------------------------
function flash($type, $text) {
  $_SESSION['flash'][] = ['type' => $type, 'text' => $text];
}

function show_flashes() {
  if (empty($_SESSION['flash'])) {
    return;
  }
  foreach ($_SESSION['flash'] as $f) {
    echo '<div class="alert alert-' . e($f['type']) . ' alert-dismissible fade show" role="alert">'
       . e($f['text'])
       . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
  }
  unset($_SESSION['flash']);
}

//----------------------------------------------------------------------
// CSRF protection: every form that changes data carries this token
//----------------------------------------------------------------------
function csrf_token() {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function csrf_field() {
  return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/* Call at the top of every POST handler. Stops the request if the token is missing or wrong. */
function csrf_check($back = null) {
  $sent = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
  if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
    flash('danger', 'Your session expired. Please try again.');
    redirect($back !== null ? $back : home_for_role(isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 0));
  }
}

/* Only accept POST for actions that change data */
function require_post($back) {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($back);
  }
  csrf_check($back);
}

//----------------------------------------------------------------------
// Circulation rules shared by member and admin pages
//----------------------------------------------------------------------

/* Calendar days past the due date, worked out by MySQL so it uses the same clock that stamped the due date */
const DAYS_LATE_SQL = "GREATEST(0, DATEDIFF(NOW(), l.due_date))";

/* Close an open loan: set the return date and fine, then either hand the copy
   to the next waiting hold for that book or put it back on the shelf.
   Returns a short message describing what happened, or null if the loan wasn't open. */
function return_loan(mysqli $db, $loan_id) {
  $db->begin_transaction();
  try {
    $q = $db->prepare("SELECT l.loan_id, l.copy_id, c.book_id, " . DAYS_LATE_SQL . " AS days_late
                       FROM Loans l JOIN Copies c ON c.copy_id = l.copy_id
                       WHERE l.loan_id = ? AND l.return_date IS NULL FOR UPDATE");
    $q->bind_param('i', $loan_id);
    $q->execute();
    $loan = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$loan) {
      $db->rollback();
      return null;
    }

    $fine = round((int)$loan['days_late'] * FINE_PER_DAY, 2);
    $u = $db->prepare("UPDATE Loans SET return_date = NOW(), fine = ? WHERE loan_id = ?");
    $u->bind_param('di', $fine, $loan['loan_id']);
    $u->execute();
    $u->close();

    // First come, first served: the oldest waiting hold gets this copy
    $h = $db->prepare("SELECT hold_id FROM Holds WHERE book_id = ? AND status = 'waiting'
                       ORDER BY hold_date, hold_id LIMIT 1 FOR UPDATE");
    $h->bind_param('i', $loan['book_id']);
    $h->execute();
    $hold = $h->get_result()->fetch_assoc();
    $h->close();

    if ($hold) {
      $r = $db->prepare("UPDATE Holds SET status = 'ready', ready_at = NOW() WHERE hold_id = ?");
      $r->bind_param('i', $hold['hold_id']);
      $r->execute();
      $r->close();
      $status = 'on_hold';
    } else {
      $status = 'available';
    }
    $c = $db->prepare("UPDATE Copies SET status = ? WHERE copy_id = ?");
    $c->bind_param('si', $status, $loan['copy_id']);
    $c->execute();
    $c->close();

    $db->commit();
  } catch (Exception $ex) {
    $db->rollback();
    throw $ex;
  }

  $msg = 'Returned.';
  if ($fine > 0) {
    $msg .= ' It was late, so a $' . number_format($fine, 2) . ' fine was added.';
  }
  if ($hold) {
    $msg .= ' The copy is now set aside for the next member on the hold list.';
  }
  return $msg;
}

/* Holds that were set aside but never picked up go back on the shelf
   (or to the next person waiting). Called when circulation pages load. */
function expire_old_holds(mysqli $db) {
  $days = HOLD_PICKUP_DAYS;
  $stale = $db->query("SELECT hold_id, book_id FROM Holds
                       WHERE status = 'ready' AND ready_at < DATE_SUB(NOW(), INTERVAL $days DAY)");
  while ($row = $stale->fetch_assoc()) {
    $db->begin_transaction();
    try {
      $x = $db->prepare("UPDATE Holds SET status = 'expired' WHERE hold_id = ? AND status = 'ready'");
      $x->bind_param('i', $row['hold_id']);
      $x->execute();
      $changed = $x->affected_rows;
      $x->close();
      if ($changed) {
        promote_next_hold($db, (int)$row['book_id']);
      }
      $db->commit();
    } catch (Exception $ex) {
      $db->rollback();
    }
  }
}

/* A set-aside copy was freed (hold expired or cancelled): give it to the next
   waiting hold, or put it back on the shelf. Run inside a transaction. */
function promote_next_hold(mysqli $db, $book_id) {
  $c = $db->prepare("SELECT copy_id FROM Copies WHERE book_id = ? AND status = 'on_hold' LIMIT 1");
  $c->bind_param('i', $book_id);
  $c->execute();
  $copy = $c->get_result()->fetch_assoc();
  $c->close();
  if (!$copy) {
    return;
  }
  // Only free a set-aside copy if there are more set-aside copies than ready holds
  $ready = $db->prepare("SELECT COUNT(*) AS n FROM Holds WHERE book_id = ? AND status = 'ready'");
  $ready->bind_param('i', $book_id);
  $ready->execute();
  $ready_count = (int)$ready->get_result()->fetch_assoc()['n'];
  $ready->close();
  $held = $db->prepare("SELECT COUNT(*) AS n FROM Copies WHERE book_id = ? AND status = 'on_hold'");
  $held->bind_param('i', $book_id);
  $held->execute();
  $held_count = (int)$held->get_result()->fetch_assoc()['n'];
  $held->close();
  if ($held_count <= $ready_count) {
    return;
  }

  $h = $db->prepare("SELECT hold_id FROM Holds WHERE book_id = ? AND status = 'waiting'
                     ORDER BY hold_date, hold_id LIMIT 1");
  $h->bind_param('i', $book_id);
  $h->execute();
  $next = $h->get_result()->fetch_assoc();
  $h->close();
  if ($next) {
    $r = $db->prepare("UPDATE Holds SET status = 'ready', ready_at = NOW() WHERE hold_id = ?");
    $r->bind_param('i', $next['hold_id']);
    $r->execute();
    $r->close();
  } else {
    $a = $db->prepare("UPDATE Copies SET status = 'available' WHERE copy_id = ?");
    $a->bind_param('i', $copy['copy_id']);
    $a->execute();
    $a->close();
  }
}

/* Human-readable copy status */
function copy_status_label($status) {
  $labels = [
    'available' => 'Available',
    'on_loan'   => 'On loan',
    'on_hold'   => 'Set aside for a hold',
    'damaged'   => 'Damaged',
    'lost'      => 'Lost',
    'retired'   => 'Retired',
  ];
  return isset($labels[$status]) ? $labels[$status] : $status;
}

function copy_status_badge($status) {
  $colors = [
    'available' => 'success',
    'on_loan'   => 'primary',
    'on_hold'   => 'info',
    'damaged'   => 'warning',
    'lost'      => 'danger',
    'retired'   => 'secondary',
  ];
  $color = isset($colors[$status]) ? $colors[$status] : 'secondary';
  return '<span class="badge text-bg-' . $color . '">' . e(copy_status_label($status)) . '</span>';
}
