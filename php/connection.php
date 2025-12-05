<?php
$server = "localhost";
$username = "LIBRARY_MS";
$password = "12345";
$db_name = "library_db";

// Create connection using OOP style
$conn = new mysqli($server, $username, $password, $db_name);

// Check connection
if ($conn->connect_error) {
    // Log the error to the server error log
    error_log("Database connection failed: " . $conn->connect_error);
    
    // Show a generic message to the user
    die("Connection to the database failed. Please try again later.");
}

// Connection successful (optional message, remove in production)
// echo "Connected successfully";
?>
