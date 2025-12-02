<?php
// ------------------------
// DATABASE CONNECTION
// ------------------------
$servername = "localhost";
$username = "root"; 
$password = "";      
$database = "library_db"; 

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ------------------------
// FETCH FORM DATA
// ------------------------
$fullname  = $_POST['fullname'];
$email     = $_POST['email'];
$pass      = $_POST['password'];
$cpass     = $_POST['confirm_password'];
$role      = $_POST['role'];          // User or Librarian
$contact   = $_POST['contact'];
$address   = $_POST['address'];

// ------------------------
// VALIDATION
// ------------------------

// Check password match
if ($pass !== $cpass) {
    die("Password and Confirm Password do not match.");
}

// Check if email already exists
$checkEmail = $conn->prepare("SELECT email FROM users WHERE email = ?");
$checkEmail->bind_param("s", $email);
$checkEmail->execute();
$result = $checkEmail->get_result();

if ($result->num_rows > 0) {
    die("Email already registered. Try another.");
}

// Hash password
$hashedPassword = password_hash($pass, PASSWORD_DEFAULT);

// ------------------------
// INSERT INTO DATABASE
// ------------------------
$stmt = $conn->prepare("
    INSERT INTO users (fullname, email, password, role, contact, address)
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmt->bind_param("ssssss", 
    $fullname, 
    $email, 
    $hashedPassword, 
    $role, 
    $contact, 
    $address
);

if ($stmt->execute()) {
    echo "Signup successful! <a href='../html/login.html'>Login Here</a>";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>