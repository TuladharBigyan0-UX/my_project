<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];

// Include database connection
include("connection.php");

$errors = [];
$success = '';

// Fetch full user data from database
$userId = $user['id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();

// ====================================
// UPDATE PROFILE
// ====================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);
    $address = trim($_POST['address']);

    // Validation
    if (empty($fullname)) {
        $errors[] = "Full name is required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Check if email exists for other users
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Email already exists.";
    }

    // Validate contact number
    if (!empty($contact) && !preg_match('/^9[6-8][0-9]{8}$/', $contact)) {
        $errors[] = "Contact must start with 9, second digit 6-8, and be 10 digits.";
    }

    // Update if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, contact = ?, address = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $fullname, $email, $contact, $address, $userId);
        
        if ($stmt->execute()) {
            $_SESSION['user']['fullname'] = $fullname;
            $_SESSION['user']['email'] = $email;
            $success = "Profile updated successfully!";
            
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $userData = $stmt->get_result()->fetch_assoc();
        } else {
            $errors[] = "Failed to update profile.";
        }
    }
}

// ====================================
// CHANGE PASSWORD
// ====================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    // Verify current password
    if (!password_verify($currentPassword, $userData['password'])) {
        $errors[] = "Current password is incorrect.";
    }

    // Validate new password
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/', $newPassword)) {
        $errors[] = "Password must be at least 8 characters with uppercase, lowercase, number & special character.";
    }

    // Check if passwords match
    if ($newPassword !== $confirmPassword) {
        $errors[] = "New passwords do not match.";
    }

    // Update password if no errors
    if (empty($errors)) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $userId);
        
        if ($stmt->execute()) {
            $success = "Password changed successfully!";
        } else {
            $errors[] = "Failed to change password.";
        }
    }
}

// Get account statistics
$memberCount = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='member'")->fetch_assoc()['count'] ?? 0;
$librarianCount = $conn->query("SELECT COUNT(*) as count FROM librarians")->fetch_assoc()['count'] ?? 0;

$checkBooks = $conn->query("SHOW TABLES LIKE 'books'");
$bookCount = 0;
if ($checkBooks && $checkBooks->num_rows > 0) {
    $bookCount = $conn->query("SELECT COUNT(*) as count FROM books")->fetch_assoc()['count'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
       
    </style>
</head>
<body>

<div class="dashboard">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="profile-box">
            <h3><?= htmlspecialchars($user['fullname']); ?></h3>
            <p>Admin</p>
        </div>

        <ul class="menu">
            <li><a href="../dashboard/admin_dashboard.php">Dashboard</a></li>
            <li><a href="manage_librarian.php">Manage Librarians</a></li>
            <li><a href="manage_member.php">Manage Members</a></li>
            <li><a href="view_reports.php">View Reports</a></li>
            <li><a href="return_books.php">Return Books</a></li>
            <li><a href="profile.php" class="active">Profile</a></li>
            <li class="logout"><a href="logout.php">Logout</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="content">
        <h1>My Profile</h1>
        <p>Manage your account settings and information</p>

        <!-- Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="profile-container">
            
            <!-- Left Column - Profile Info -->
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?= strtoupper(substr($userData['fullname'], 0, 1)); ?>
                    </div>
                    <div class="profile-name"><?= htmlspecialchars($userData['fullname']); ?></div>
                    <div class="profile-role">Administrator</div>
                </div>

                <h3 class="section-title">Account Statistics</h3>
                <div class="profile-stats">
                    <div class="stat-item">
                        <span class="stat-label">Total Books</span>
                        <span class="stat-number"><?= $bookCount; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Total Members</span>
                        <span class="stat-number"><?= $memberCount; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Total Librarians</span>
                        <span class="stat-number"><?= $librarianCount; ?></span>
                    </div>
                </div>

                <h3 class="section-title" style="margin-top: 30px;">Account Details</h3>
                <div class="info-item">
                    <span class="info-label">User ID:</span>
                    <span class="info-value">#<?= $userData['id']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Account Type:</span>
                    <span class="info-value">Administrator</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status:</span>
                    <span class="info-value" style="color: #22c55e;">Active</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Member Since:</span>
                    <span class="info-value"><?= date('M d, Y', strtotime($userData['created_at'])); ?></span>
                </div>
            </div>

            <!-- Right Column - Edit Forms -->
            <div class="profile-card">
                <!-- Tabs -->
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('personal')">Personal Information</button>
                    <button class="tab" onclick="switchTab('security')">Security</button>
                </div>

                <!-- Personal Information Tab -->
                <div id="personal-tab" class="tab-content active">
                    <form method="POST">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="fullname" value="<?= htmlspecialchars($userData['fullname']); ?>" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Email Address *</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($userData['email']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Contact Number</label>
                                <input type="text" name="contact" pattern="^9[6-8][0-9]{8}$" placeholder="9*********" maxlength="10" value="<?= htmlspecialchars($userData['contact'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address"><?= htmlspecialchars($userData['address'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" name="update_profile" class="btn-primary">💾 Update Profile</button>
                    </form>
                </div>

                <!-- Security Tab -->
                <div id="security-tab" class="tab-content">
                    <form method="POST">
                        <div class="form-group">
                            <label>Current Password *</label>
                            <input type="password" name="current_password" required>
                        </div>

                        <div class="form-group">
                            <label>New Password *</label>
                            <input type="password" name="new_password" required>
                            <small style="color: var(--text-muted); font-size: 12px; display: block; margin-top: 5px;">
                                Must be at least 8 characters with uppercase, lowercase, number & special character
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Confirm New Password *</label>
                            <input type="password" name="confirm_password" required>
                        </div>

                        <button type="submit" name="change_password" class="btn-primary">🔒 Change Password</button>
                    </form>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
    function switchTab(tabName) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });

        // Show selected tab
        document.getElementById(tabName + '-tab').classList.add('active');
        event.target.classList.add('active');
    }
</script>

</body>
</html>