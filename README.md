# Library Management System

A library web app where members search the catalog, check out and hold books, and track their loans, and admins manage books, copies, members, and staff picks.

Team: Devon Kiddoo, Jermane Harrell, Brandon Apruzzese, Maximilian Musial

Built on the CSC 235 login starter by Joshua Connor ([connorj4/csc235_login](https://github.com/connorj4/csc235_login)), which is for school projects only.

## Stack

XAMPP (Apache, MariaDB, PHP) with Bootstrap 5.3 from a CDN.

## Setup

1. Install [XAMPP](https://www.apachefriends.org/index.html) and start **Apache** and **MySQL** from the XAMPP control panel.
2. Clone this repo into XAMPP's `htdocs` folder. Keep the folder name `Library-Management-System`, or change `$directory` in `php/path.php` to match.
   ```
   cd C:\xampp\htdocs        (Mac: /Applications/XAMPP/htdocs)
   git clone https://github.com/masterpillow/Library-Management-System.git
   ```
3. If your MySQL root user has a password, put it in both `php/config.php` and `php/start.php`.
4. Create the database by opening http://localhost/Library-Management-System/php/start.php.
   This **drops and recreates every table** in `library_db`, so rerun it whenever the schema changes.
5. Log in at http://localhost/Library-Management-System/

### Test accounts

| Username | Password | Role |
|---|---|---|
| johndoe | SCSU2024 | administrator |
| janesmith | SCSU2025 | user (member) |
| charliebrown | SCSU2026 | guest |

## Database

`php/start.php` creates everything. Login tables come from the starter; library tables hang off `Users.user_id`.

| Area | Tables |
|---|---|
| Login (starter) | `Roles`, `Contacts`, `Users`, `Credentials` |
| Accounts (added) | `Memberships`, `Password_Resets` |
| Catalog | `Authors`, `Publishers`, `Genres`, `Books`, `Book_Authors`, `Copies` |
| Circulation | `Loans`, `Holds`, `Ratings`, `Staff_Picks` |

Rules the database enforces:

- A loan is for one copy, and a copy can't have two open loans at once (`Loans.open_copy_id` is unique while `return_date` is empty).
- One rating per member per book, from 1 to 5.
- Copy status is `available`, `on_loan`, `on_hold`, or `retired`. Hold status is `waiting`, `ready`, `fulfilled`, `cancelled`, or `expired`.
- Members and books are deactivated with `is_active`, never deleted, so loan history stays intact.

Sample data: 6 books with copies, an active loan for Jane, one rating, and one staff pick.

## Changes from the starter

- Passwords use `password_hash` / `password_verify` (bcrypt). The starter used `crypt()` with a fixed salt, which falls back to DES and only checks the first 8 characters.
- Role checks (`check_admin.php`, `check_user.php`, `check_guest.php`) now `exit()` after redirecting. Before, the rest of the protected page still ran and was sent to the browser.
- `check_guest.php` checked for role 2 instead of 3, so guests were always logged out.
- Failed logins show the error message on the login page instead of losing it on logout.
- The user dashboard query uses a prepared statement instead of putting the username into the SQL.
- Fixed broken paths: `db/error_rprt.php` → `php/error_rprt.php`, and the forgot-password form now posts to `php/recovery.php`.
- Inactive accounts can't log in.
- Database renamed from `login_db` to `library_db`; `password_salted` renamed to `password_hash`.

## Not built yet

- **Forgot password:** `php/recovery.php` is a placeholder. The `Password_Resets` table is ready for it.
- **Member pages:** search, check out, place a hold, loan history, ratings.
- **Admin pages:** books and authors, copies, members, staff picks, mark copy returned.
