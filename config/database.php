<?php
mysqli_report(MYSQLI_REPORT_OFF);

$host = "localhost";
$user = "root";
$password = "";
$database = "simple_erp";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed. Import install.sql in phpMyAdmin and check config/database.php. Error: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
