<?php

/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
|
| MySQL connection configuration for DONATE+ Nepal.
| Update these values according to your database setup.
|
*/

// Database credentials
$db_host     = getenv('DB_HOST') ?: 'localhost';
$db_user     = getenv('DB_USER') ?: 'root';
$db_pass     = getenv('DB_PASS') ?: '';
$db_name     = getenv('DB_NAME') ?: 'donation_db';
$db_port     = getenv('DB_PORT') ?: 3306;

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . htmlspecialchars($conn->connect_error));
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// Enable error reporting for development
if (getenv('APP_ENV') === 'development') {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

?>