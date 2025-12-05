<?php
// Start session
session_start();
require_once "connection.php";

// Check if form submitted
if (empty($_POST['email']) || empty($_POST['password'])) {
    die("Please fill both email and password fields!");
}

$email = trim($_POST['email']);
$password = $_POST['password'];

// Prepare SQL
$sql = "SELECT user_id, fullname, email, password, role FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows === 1) {

    $user = $result->fetch_assoc();

    // Verify password
    if (password_verify($password, $user['password'])) {

        // Secure session
        session_regenerate_id(true);

        $_SESSION['user_id']  = $user['user_id'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['email']    = $user['email'];
        $_SESSION['role']     = strtolower($user['role']);

        // Redirect based on role
        switch ($_SESSION['role']) {
            case 'admin':
                header("Location: ../dashboard/admin_dashboard.php");
                break;

            case 'librarian':
                header("Location: ../dashboard/librarian_dashboard.php");
                break;

            case 'member':
                header("Location: ../dashboard/member_dashboard.php");
                break;

            default:
                header("Location: ../dashboard/dashboard.php");
        }
        exit();

    } else {
        // Password wrong
        echo "<h2 style='color:red; text-align:center;'>Invalid email or password!</h2>";
        exit();
    }

} else {
    // Email not found
    echo "<h2 style='color:red; text-align:center;'>Invalid email or password!</h2>";
    exit();
}
?>
