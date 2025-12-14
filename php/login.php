<?php
session_start();
include('connection.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {

            // Check approval for members
            if ($user['role'] === 'member' && $user['status'] !== 'approved') {
                echo "<script>alert('Your account is not approved yet by admin.');</script>";
            } else {
                // Login success
                $_SESSION['user'] = [
                    'id' => $user['id'], // or user_id if your PK is different
                    'role' => $user['role'],
                    'fullname' => $user['fullname']
                ];

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: ../dashboard/admin_dashboard.php");
                } elseif ($user['role'] === 'librarian') {
                    header("Location: ../dashboard/librarian_dashboard.php");
                } elseif ($user['role'] === 'member') {
                    header("Location: ../dashboard/member_dashboard.php");
                } else {
                    header("Location: login.php");
                }
                exit();
            }

        } else {
            echo "<script>alert('Invalid password');</script>";
        }

    } else {
        echo "<script>alert('No user found with this email');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Login</title>
    <link rel="stylesheet" href="../css/login_style.css">
</head>

<body>

    <a href="../html/index.html" class="back-btn">← Back to Home</a>

    <div class="login-container">
        <div class="login-card">

            <div class="logo">
                <img src="../images/logo.png" alt="logo" height="80">
            </div>

            <h2>Library Management System</h2>
            <p class="subtitle">Sign in to your account</p>

            <form id="loginForm" action="login.php" method="POST">
                <!-- Email -->
                <label>Email Address</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>

                <!-- Password -->
                <label>Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>

                <!-- Button -->
                <button type="submit" class="btn">Sign In</button>
            </form>

            <div class="signup-link">
                Don't have an account? <a href="../html/signup.html">Sign Up</a>
            </div>

        </div>
    </div>

    <script src="script.js"></script>
</body>

</html>
