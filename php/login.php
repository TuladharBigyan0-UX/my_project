<?php
// Start session
session_start();

// Database connection — update credentials if needed
$servername = "localhost";
$dbUsername = "root";
$dbPassword = "";
$dbName = "library_db";  // make sure this matches your DB name

$conn = new mysqli($servername, $dbUsername, $dbPassword, $dbName);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Check that form fields are set
if (!isset($_POST['email'], $_POST['password'])) {
    // Invalid request
    die("Please fill both email and password fields!");
}

$email = trim($_POST['email']);
$password = $_POST['password'];

// Prepare and execute query safely
$sql = "SELECT user_id, fullname, email, password, role FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // Verify password
    if (password_verify($password, $user['password'])) {
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        // Store minimal necessary info in session
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['fullname']  = $user['fullname'];
        $_SESSION['email']     = $user['email'];
        $_SESSION['role']      = $user['role'];

        // Redirect based on role (optional)
        if ($user['role'] === 'Librarian') {
            header("Location: librarian_dashboard.php");
            exit();
        } else {
            header("Location: user_dashboard.php");
            exit();
        }
    }
}

// If we reach here — invalid login
// You may redirect back to login page or show message
echo "<h2 style='color:red;'>Invalid email or password!</h2>";
exit();
?>
