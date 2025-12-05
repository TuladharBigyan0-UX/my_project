<?php
session_start();
include('connection.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email = '$email'";
    $stmt = mysqli_query($conn, $sql);

    if ($stmt && mysqli_num_rows($stmt) > 0) {
        $res = mysqli_fetch_assoc($stmt);

        if (password_verify($password, $res['password'])) { // use password_verify() if hashed
            $_SESSION['user'] = [
            'id' => $res['id'],
            'role' => $res['role'],
            'fullname' => $res['fullname']
            ];

            // Redirect based on role
            switch ($_SESSION['user']['role']) {
                case 'admin':
                    header("Location: ../dashboard/admin_dashboard.php");
                    exit();
                case 'librarian':
                    header("Location: ../dashboard/librarian_dashboard.php");
                    exit();
                case 'member':
                    header("Location: ../dashboard/member_dashboard.php");
                    exit();
                default:
                    header("Location: ../dashboard/dashboard.php");
                    exit();
            }

        } else {
            echo "<script>alert('Invalid Password');</script>";
        }

    } else {
        echo "<script>alert('There is no user with the given email');</script>";
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

    <a href="index.html" class="back-btn">← Back to Home</a>

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
                Don't have an account? <a href="signup.html">Sign Up</a>
            </div>

        </div>
    </div>

    <script src="script.js"></script>
</body>

</html>