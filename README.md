# Library Management System

A library web app where members search the catalog, check out and hold books, and track their loans, and admins manage books, copies, members, and staff picks.

Team: Devon Kiddoo, Brandon Apruzzese, Maximilian Musial

Built on the CSC 235 login starter by Joshua Connor ([connorj4/csc235_login](https://github.com/connorj4/csc235_login)), which is for school projects only.

## Stack

XAMPP (Apache, MariaDB, PHP) with Bootstrap 5.3 from a CDN.

## Setup

1. Install [XAMPP](https://www.apachefriends.org/index.html).
2. Clone this repo into XAMPP's `htdocs` folder. Any folder name works, including `Library-Management-System-main` from a GitHub zip download; just use that name in the addresses below.

   **Mac:**
   ```
   cd /Applications/XAMPP/xamppfiles/htdocs
   git clone https://github.com/masterpillow/Library-Management-System.git
   ```

   **Windows:**
   ```
   cd C:\xampp\htdocs
   git clone https://github.com/masterpillow/Library-Management-System.git
   ```
3. Start **Apache** and **MySQL**. On a Mac, open `/Applications/XAMPP/manager-osx.app` and use the **Manage Servers** tab; on Windows, use the XAMPP Control Panel. See [Troubleshooting](#troubleshooting) if Apache won't start.
4. If your MySQL root user has a password, put it in `DB_PASSWORD` in `php/db_credentials.php`. That's the only place the database login lives.
5. Create the database by opening http://localhost/Library-Management-System/php/start.php.
   This **drops and recreates every table** in `library_db`, so rerun it whenever the schema changes.
6. Log in at http://localhost/Library-Management-System/

### Test accounts

| Username | Password | Role | What's set up for the demo |
|---|---|---|---|
| johndoe | SCSU2024 | Admin | |
| janesmith | SCSU2025 | Member | Has *The Hobbit* out, owes a $1.50 late fine, has rated two books |
| mikejones | SCSU2027 | Member | *The Great Gatsby* is 5 days overdue; waiting on a hold for *The Hobbit* |
| charliebrown | SCSU2026 | Guest | Can browse the catalog but not borrow |

Rerun `php/start.php` any time to reset everything to this state.

## What it does

**Members**
- Search and browse the catalog (all books, by author, staff picks), with copies on the shelf and average ratings
- Check out a book, or place a hold when every copy is out
- My Loans: due dates, overdue warnings, holds and place in line, fines, borrowing history, rate books 1 to 5
- Return books, cancel holds, pay fines
- My Account: update contact details, username, and password

**Admins**
- Dashboard with library totals and overdue books
- Books: add, edit (including several authors), deactivate and reactivate
- Copies: add copies with automatic barcodes, mark copies damaged, lost, or retired
- Returns: check in books, which charges late fines and hands the copy to the next hold
- Members: create accounts, edit details, role and membership, reset passwords, deactivate and reactivate
- Staff picks: feature books with a blurb, using checkout counts and ratings to see what's popular

**Guests** can browse the catalog. **Anyone** can create a member account or reset a forgotten password.

## Library rules

These live in `php/helpers.php` and match the Business Rules slide.

- A book is a title; a copy is one physical book. A loan is always for one copy.
- Loan length and the number of books a member can have out come from their membership (Standard: 5 books, 21 days; Extended: 10 books, 28 days).
- Members with unpaid fines can't check out more books.
- Late returns are charged **$0.25 per day**, added when the book comes back.
- Holds are only for books with every copy out. First come, first served: a returned copy is set aside for the oldest hold for **3 days**, then goes to the next person.
- Books and accounts are deactivated, never deleted, so history stays intact.

## Database

`php/start.php` creates everything. Login tables come from the starter; library tables hang off `Users.user_id`.

| Area | Tables |
|---|---|
| Login (starter) | `Roles`, `Contacts`, `Users`, `Credentials` |
| Accounts | `Memberships`, `Password_Resets` |
| Catalog | `Authors`, `Publishers`, `Genres`, `Books`, `Book_Authors`, `Copies` |
| Circulation | `Loans`, `Holds`, `Ratings`, `Staff_Picks` |

Rules the database enforces on its own:

- A copy can't have two open loans (`Loans.open_copy_id` is unique while `return_date` is empty).
- One rating per member per book, 1 to 5. One staff pick per book.
- Copy status is `available`, `on_loan`, `on_hold`, `damaged`, `lost`, or `retired`. Hold status is `waiting`, `ready`, `fulfilled`, `cancelled`, or `expired`.

## Project layout

| Folder | What's in it |
|---|---|
| `admin/` | Admin pages |
| `user/` | Member pages (catalog, My Loans, My Account) |
| `guest/` | Guest dashboard |
| `php/` | Setup, login, member actions, and shared code |
| `include/` | Page head, navbar, and footer used by every page |
| `css/`, `js/` | Styles and the confirm-before-submit script |

To add a page, copy `template.php`. It shows the pattern every page follows: `require_role(...)`, set `$page_name` and `$page_title`, handle POST with `csrf_check()`, then `flash()` a message and `redirect()`.

## Security

- Passwords are hashed with bcrypt (`password_hash`). Password reset links store only a SHA-256 hash of the token and expire after an hour.
- Every page checks the role before doing anything (`require_role`), and admins can't deactivate or demote themselves.
- Every form that changes data carries a CSRF token, and actions only run on POST.
- All SQL uses prepared statements, and everything shown on a page is escaped.

## Troubleshooting

**Apache won't start (Mac).** macOS has its own Apache that takes port 80. Check with:
```
sudo lsof -i :80
```
If it lists `httpd` running as `_www`, stop the built-in one, then start Apache in XAMPP again:
```
sudo apachectl stop
```
To keep it from coming back after a restart:
```
sudo launchctl disable system/org.apache.httpd
```

**Apache won't start (Windows).** Something else is on port 80, often Skype or IIS. In the XAMPP Control Panel, click **Netstat** to see what's using it, close that program, and start Apache again.

**Another option on either system:** change Apache's port to 8080 (Mac: Manage Servers → Apache → Configure; Windows: Config → `httpd.conf`, change `Listen 80` to `Listen 8080`), then use http://localhost:8080/Library-Management-System/ instead.

**"Can't reach MySQL."** MySQL isn't running. Start it in XAMPP, then reload the page.

**"MySQL refused the login for 'root'."** Your MySQL root user has a password. Put it in `DB_PASSWORD` in `php/db_credentials.php`.

**"The library_db database doesn't exist yet."** You opened the login page before creating the database. Open `php/start.php` first.
