<?php
include ("connection.php");

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get and sanitize form inputs
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $contact = trim($_POST['contact']);
    $address = trim($_POST['address']);
    $role = 'member'; // default role for self-signup

    // Basic validation
    if ($password !== $confirm_password) {
        die("Passwords do not match.");
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        error_log("Failed to check email existence: " . $stmt->error);
        die("An error occurred. Please try again later.");
    }
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        die("Email already registered. Please use a different email.");
    }
    $stmt->close();

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, role, contact, address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $fullname, $email, $hashed_password, $role, $contact, $address);

    if ($stmt->execute()) {
        echo "Account created successfully! <a href='login.html'>Login here</a>.";
    } else {
        error_log("Failed to create user: " . $stmt->error);
        die("Failed to create account. Please try again later.");
    }

    $stmt->close();
}

$conn->close();
?>