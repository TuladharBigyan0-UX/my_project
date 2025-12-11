<?php
include("connection.php");

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $contact = trim($_POST['contact'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $role = 'member';

    // Validation
    if (!preg_match('/^9[6-8][0-9]{8}$/', $contact)) $errors['contact'] = "Contact invalid.";
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W]).{8,}$/', $password)) $errors['password'] = "Password invalid.";
    if ($password !== $confirm_password) $errors['confirm_password'] = "Passwords do not match.";

    
  // Check if email already exists
$sql = "SELECT email FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);

$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<script>
            alert('Email already exists.');
            window.location.href = 'login.php';
          </script>";
    exit;
}

$stmt->close();


    // Insert user if no errors
    if (!$errors) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmtInsert = $conn->prepare("INSERT INTO users (fullname,email,password,role,contact,address) VALUES (?,?,?,?,?,?)");
        if ($stmtInsert) {
            $stmtInsert->bind_param("ssssss", $fullname, $email, $hashed_password, $role, $contact, $address);
            if ($stmtInsert->execute()) {
                $stmtInsert->close();
                header("Location: ../html/signup_success.html");
                exit();
            } else {
                $stmtInsert->close();
                header("Location: ../html/signup_error.html?error=db_error");
                exit();
            }
        } else {
            header("Location: ../html/signup_error.html?error=db_error");
            exit();
        }
    } else {
        // Validation errors → redirect
        header("Location: ../html/signup_error.html?error=validation");
        exit();
    }
}
$conn->close();
?>