<?php
$host = "localhost";      // XAMPP default
$user = "root";           // default MySQL user
$password = "";           // leave blank for XAMPP default
$dbname = "wpu_mrs";      // database name

// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
