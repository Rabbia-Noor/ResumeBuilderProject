<?php
$servername = "localhost"; // XAMPP's default server
$username = "root"; // Default MySQL user
$password = ""; // Default password (empty)
$database = "portfolio_db"; // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
//echo "Connected successfully";
?>
