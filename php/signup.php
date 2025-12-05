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

    // Password match validation
    if ($password !== $confirm_password) {
        header("Location: signup_error.php?error=password_mismatch");
        exit();
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);

    if (!$stmt->execute()) {
        error_log("Failed to check email existence: " . $stmt->error);
        $stmt->close();
        $conn->close();
        header("Location: signup_error.php?error=server_error");
        exit();
    }

    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        $conn->close();
        header("Location: signup_error.php?error=email_exists");
        exit();
    }
    $stmt->close();

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user
    $stmt = $conn->prepare("
        INSERT INTO users (fullname, email, password, role, contact, address) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssss", $fullname, $email, $hashed_password, $role, $contact, $address);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header("Location: ../html/signup_success.html");
        exit();
    } else {
        error_log("Failed to create user: " . $stmt->error);
        $stmt->close();
        $conn->close();
        header("Location: ../html/signup_error.html");
        exit();
    }
}

$conn->close();
?>
