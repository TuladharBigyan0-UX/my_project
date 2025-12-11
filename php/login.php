<?php
session_start();
include('connection.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];

    // Fetch user by email
    $sql = "SELECT * FROM users WHERE email = '$email'";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);

        // Verify password (assuming it's hashed)
        if (password_verify($password, $user['password'])) {

            // Store user info in session
            $_SESSION['user'] = [
                'id' => $user['id'],
                'role' => $user['role'],
                'fullname' => $user['fullname']
            ];

            // Get role
            $role = $user['role'];

            // Redirect based on role
            if ($role === 'admin') {
                header("Location: ../dashboard/admin_dashboard.php");
            } elseif ($role === 'librarian') {
                header("Location: ../dashboard/librarian_dashboard.php");
            } elseif ($role === 'member') {
                header("Location: ../dashboard/member_dashboard.php");
            } else {
                // Fallback
                header("Location: login.php");
            }
            exit();

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
