<?php
/**
 * Create the database and tables for the Library Management System
 *
 * Based on the CSC 235 login starter by Joshua Connor <connorj4@southernct.edu>.
 * The login tables (Roles, Contacts, Users, Credentials) come from the starter;
 * the library tables are added on top of Users.user_id.
 *
 * Run once from the browser: http://localhost/Library-Management-System/php/start.php
 * WARNING: this drops and recreates every table in library_db.
 */

error_reporting(-1);
ini_set('display_errors', 'On');

/* Database login lives in php/db_credentials.php */
include_once (__DIR__ . '/db_credentials.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $db_connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD);
} catch (mysqli_sql_exception $e) {
  die(htmlspecialchars(db_setup_message($e->getCode(), $e->getMessage())));
}

function status($text) {
  echo htmlspecialchars($text) . "<br>\n";
}

/* Create the database if needed and switch to it */
$db_connection->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
$db_connection->select_db(DB_NAME);
status("Database " . DB_NAME . " is ready.");

/* Drop all tables for a clean install */
$db_connection->query('SET foreign_key_checks = 0');
$result = $db_connection->query("SHOW TABLES");
while ($row = $result->fetch_array(MYSQLI_NUM)) {
  $db_connection->query('DROP TABLE IF EXISTS `' . $row[0] . '`');
}
$db_connection->query('SET foreign_key_checks = 1');
status("Old tables removed.");

//-----------------------------------------------------
// Create Tables (order matters: a table must exist before another references it)
//-----------------------------------------------------
$tables = [

/* ---------- Login tables (from the starter) ---------- */

"Roles" => "CREATE TABLE Roles(
    role_id int NOT NULL AUTO_INCREMENT,
    role_type varchar(255) NOT NULL,
    PRIMARY KEY(role_id))",

"Contacts" => "CREATE TABLE Contacts(
    contact_id int NOT NULL AUTO_INCREMENT,
    last_name varchar(255) NOT NULL,
    first_name varchar(255) NOT NULL,
    email varchar(255) NOT NULL,
    phone varchar(255) NOT NULL,
    street_1 varchar(255),
    street_2 varchar(255),
    city varchar(255),
    state_code varchar(255),
    post_code varchar(10),
    updated timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(contact_id),
    UNIQUE(email))",

/* Membership type sets how many books a member can have out and for how long */
"Memberships" => "CREATE TABLE Memberships(
    membership_id int NOT NULL AUTO_INCREMENT,
    type_name varchar(50) NOT NULL,
    max_loans int NOT NULL,
    loan_days int NOT NULL,
    PRIMARY KEY(membership_id))",

/* Users: added membership_id and is_active (deactivate instead of delete) */
"Users" => "CREATE TABLE Users(
    user_id int NOT NULL AUTO_INCREMENT,
    role_id int NOT NULL,
    contact_id int NOT NULL,
    membership_id int NOT NULL DEFAULT 1,
    is_active boolean NOT NULL DEFAULT 1,
    creation_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(user_id),
    FOREIGN KEY(role_id) REFERENCES Roles(role_id),
    FOREIGN KEY(contact_id) REFERENCES Contacts(contact_id),
    FOREIGN KEY(membership_id) REFERENCES Memberships(membership_id))",

/* Credentials: password_hash holds a password_hash() value (bcrypt) */
"Credentials" => "CREATE TABLE Credentials(
    username varchar(255) NOT NULL,
    user_id int NOT NULL,
    password_hash varchar(255) NOT NULL,
    PRIMARY KEY(username),
    UNIQUE(user_id),
    FOREIGN KEY(user_id) REFERENCES Users(user_id))",

/* For the forgot-password flow: store a hash of the token, never the token */
"Password_Resets" => "CREATE TABLE Password_Resets(
    reset_id int NOT NULL AUTO_INCREMENT,
    user_id int NOT NULL,
    token_hash varchar(255) NOT NULL,
    expires_at datetime NOT NULL,
    used_at datetime NULL,
    PRIMARY KEY(reset_id),
    FOREIGN KEY(user_id) REFERENCES Users(user_id))",

/* ---------- Catalog ---------- */

"Authors" => "CREATE TABLE Authors(
    author_id int NOT NULL AUTO_INCREMENT,
    first_name varchar(50) NOT NULL,
    last_name varchar(50) NOT NULL,
    PRIMARY KEY(author_id))",

"Publishers" => "CREATE TABLE Publishers(
    publisher_id int NOT NULL AUTO_INCREMENT,
    name varchar(100) NOT NULL,
    city varchar(50) NULL,
    PRIMARY KEY(publisher_id))",

"Genres" => "CREATE TABLE Genres(
    genre_id int NOT NULL AUTO_INCREMENT,
    name varchar(100) NOT NULL,
    PRIMARY KEY(genre_id),
    UNIQUE(name))",

/* A book is a title in the catalog */
"Books" => "CREATE TABLE Books(
    book_id int NOT NULL AUTO_INCREMENT,
    title varchar(255) NOT NULL,
    isbn char(13) NULL,
    publisher_id int NULL,
    genre_id int NULL,
    release_year int NULL,
    is_active boolean NOT NULL DEFAULT 1,
    PRIMARY KEY(book_id),
    UNIQUE(isbn),
    FOREIGN KEY(publisher_id) REFERENCES Publishers(publisher_id),
    FOREIGN KEY(genre_id) REFERENCES Genres(genre_id))",

"Book_Authors" => "CREATE TABLE Book_Authors(
    book_id int NOT NULL,
    author_id int NOT NULL,
    PRIMARY KEY(book_id, author_id),
    FOREIGN KEY(book_id) REFERENCES Books(book_id),
    FOREIGN KEY(author_id) REFERENCES Authors(author_id))",

/* A copy is one physical book on the shelf */
"Copies" => "CREATE TABLE Copies(
    copy_id int NOT NULL AUTO_INCREMENT,
    book_id int NOT NULL,
    barcode varchar(30) NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'available',
    PRIMARY KEY(copy_id),
    UNIQUE(barcode),
    CHECK (status IN ('available', 'on_loan', 'on_hold', 'retired')),
    FOREIGN KEY(book_id) REFERENCES Books(book_id))",

/* ---------- Circulation ---------- */

/* A loan is always for one copy. open_copy_id is only filled while the loan is
   open, and it's UNIQUE, so the database itself refuses a second open loan on
   the same copy. */
"Loans" => "CREATE TABLE Loans(
    loan_id int NOT NULL AUTO_INCREMENT,
    copy_id int NOT NULL,
    user_id int NOT NULL,
    checkout_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    due_date datetime NOT NULL,
    return_date datetime NULL,
    fine decimal(5,2) NOT NULL DEFAULT 0.00,
    open_copy_id int AS (IF(return_date IS NULL, copy_id, NULL)) PERSISTENT,
    PRIMARY KEY(loan_id),
    UNIQUE(open_copy_id),
    FOREIGN KEY(copy_id) REFERENCES Copies(copy_id),
    FOREIGN KEY(user_id) REFERENCES Users(user_id))",

/* Holds are first come, first served by hold_date. ready_at is set when a
   returned copy is set aside for this member. */
"Holds" => "CREATE TABLE Holds(
    hold_id int NOT NULL AUTO_INCREMENT,
    book_id int NOT NULL,
    user_id int NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'waiting',
    hold_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ready_at datetime NULL,
    PRIMARY KEY(hold_id),
    CHECK (status IN ('waiting', 'ready', 'fulfilled', 'cancelled', 'expired')),
    FOREIGN KEY(book_id) REFERENCES Books(book_id),
    FOREIGN KEY(user_id) REFERENCES Users(user_id))",

/* One rating per member per book, 1 to 5 */
"Ratings" => "CREATE TABLE Ratings(
    rating_id int NOT NULL AUTO_INCREMENT,
    user_id int NOT NULL,
    book_id int NOT NULL,
    rating int NOT NULL,
    PRIMARY KEY(rating_id),
    UNIQUE(user_id, book_id),
    CHECK (rating BETWEEN 1 AND 5),
    FOREIGN KEY(user_id) REFERENCES Users(user_id),
    FOREIGN KEY(book_id) REFERENCES Books(book_id))",

/* Staff picks: user_id is the admin who picked the book */
"Staff_Picks" => "CREATE TABLE Staff_Picks(
    pick_id int NOT NULL AUTO_INCREMENT,
    user_id int NOT NULL,
    book_id int NOT NULL,
    pick_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    review_blurb text NULL,
    PRIMARY KEY(pick_id),
    FOREIGN KEY(user_id) REFERENCES Users(user_id),
    FOREIGN KEY(book_id) REFERENCES Books(book_id))",
];

foreach ($tables as $name => $sql) {
  $db_connection->query($sql);
  status("Created table $name.");
}

//-----------------------------------------------------
// Populate Tables (sample data for development and the demo)
//-----------------------------------------------------

/* Roles: 1 = administrator, 2 = user (member), 3 = guest */
$db_connection->query("INSERT INTO Roles (role_id, role_type) VALUES
    (1, 'administrator'), (2, 'user'), (3, 'guest')");

/* Memberships (placeholder limits, change as the team decides) */
$db_connection->query("INSERT INTO Memberships (membership_id, type_name, max_loans, loan_days) VALUES
    (1, 'Standard', 5, 21),
    (2, 'Extended', 10, 28)");

/* Starter test accounts, same usernames and passwords as the original starter */
$people = [
  // [role_id, first, last, email, phone, street_1, street_2, city, state, zip, username, password]
  [1, 'John', 'Doe', 'johndoe@example.com', '123-456-7890', '123 Main St', '', 'Anytown', 'CA', '12345', 'johndoe', 'SCSU2024'],
  [2, 'Jane', 'Smith', 'janesmith@example.com', '987-654-3210', '456 Elm St', 'Apt 2', 'Othertown', 'NY', '54321', 'janesmith', 'SCSU2025'],
  [3, 'Charlie', 'Brown', 'charliebrown@example.com', '555-555-5555', '789 Oak St', '', 'Sometown', 'TX', '67890', 'charliebrown', 'SCSU2026'],
];
$insert_contact = $db_connection->prepare("INSERT INTO Contacts
    (first_name, last_name, email, phone, street_1, street_2, city, state_code, post_code) VALUES (?,?,?,?,?,?,?,?,?)");
$insert_user = $db_connection->prepare("INSERT INTO Users (role_id, contact_id) VALUES (?,?)");
$insert_cred = $db_connection->prepare("INSERT INTO Credentials (username, user_id, password_hash) VALUES (?,?,?)");
foreach ($people as $p) {
  [$role_id, $first, $last, $email, $phone, $street_1, $street_2, $city, $state, $zip, $username, $password] = $p;
  $insert_contact->bind_param("sssssssss", $first, $last, $email, $phone, $street_1, $street_2, $city, $state, $zip);
  $insert_contact->execute();
  $contact_id = $insert_contact->insert_id;
  $insert_user->bind_param("ii", $role_id, $contact_id);
  $insert_user->execute();
  $user_id = $insert_user->insert_id;
  $hash = password_hash($password, PASSWORD_DEFAULT);
  $insert_cred->bind_param("sis", $username, $user_id, $hash);
  $insert_cred->execute();
}
$insert_contact->close();
$insert_user->close();
$insert_cred->close();

/* Catalog */
$db_connection->query("INSERT INTO Genres (genre_id, name) VALUES
    (1, 'Fiction'), (2, 'Classic'), (3, 'Fantasy'), (4, 'Dystopian')");

$db_connection->query("INSERT INTO Authors (author_id, first_name, last_name) VALUES
    (1, 'Harper', 'Lee'),
    (2, 'George', 'Orwell'),
    (3, 'Jane', 'Austen'),
    (4, 'F. Scott', 'Fitzgerald'),
    (5, 'Toni', 'Morrison'),
    (6, 'J.R.R.', 'Tolkien')");

$db_connection->query("INSERT INTO Books (book_id, title, genre_id, release_year) VALUES
    (1, 'To Kill a Mockingbird', 1, 1960),
    (2, 'Nineteen Eighty-Four', 4, 1949),
    (3, 'Pride and Prejudice', 2, 1813),
    (4, 'The Great Gatsby', 2, 1925),
    (5, 'Beloved', 1, 1987),
    (6, 'The Hobbit', 3, 1937)");

$db_connection->query("INSERT INTO Book_Authors (book_id, author_id) VALUES
    (1, 1), (2, 2), (3, 3), (4, 4), (5, 5), (6, 6)");

/* Two copies of most books, one copy of The Hobbit (so holds can be tested) */
$db_connection->query("INSERT INTO Copies (book_id, barcode, status) VALUES
    (1, 'LMS-0001', 'available'), (1, 'LMS-0002', 'available'),
    (2, 'LMS-0003', 'available'), (2, 'LMS-0004', 'available'),
    (3, 'LMS-0005', 'available'), (3, 'LMS-0006', 'available'),
    (4, 'LMS-0007', 'available'), (4, 'LMS-0008', 'available'),
    (5, 'LMS-0009', 'available'), (5, 'LMS-0010', 'available'),
    (6, 'LMS-0011', 'on_loan')");

/* Jane (user 2) has The Hobbit checked out, due in 21 days */
$db_connection->query("INSERT INTO Loans (copy_id, user_id, checkout_date, due_date) VALUES
    (11, 2, NOW(), DATE_ADD(NOW(), INTERVAL 21 DAY))");

$db_connection->query("INSERT INTO Ratings (user_id, book_id, rating) VALUES (2, 3, 5)");

/* John (admin, user 1) picked a book */
$db_connection->query("INSERT INTO Staff_Picks (user_id, book_id, review_blurb) VALUES
    (1, 1, 'A classic everyone should read at least once.')");

status("Sample data added.");
status("Done. Test logins: johndoe / SCSU2024 (admin), janesmith / SCSU2025 (user), charliebrown / SCSU2026 (guest).");
echo '<a href="../index.php">Go to the login page</a>';

/* ALWAYS CLOSE THE DB CONNECTION */
$db_connection->close();

?>
