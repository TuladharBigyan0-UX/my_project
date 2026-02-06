<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'member') {
    header("Location: ../php/login.php");
    exit();
}

$user = $_SESSION['user'];
include("../php/connection.php");

$errors = [];
$success = '';

// Fetch full user data
$userId = $user['id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();

// UPDATE PROFILE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);
    $address = trim($_POST['address']);

    if (empty($fullname)) $errors[] = "Full name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
    
    // Check email uniqueness
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Email already exists.";
    }

    if (!empty($contact) && !preg_match('/^9[6-8][0-9]{8}$/', $contact)) {
        $errors[] = "Contact must start with 9, second digit 6-8, and be 10 digits.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, contact = ?, address = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $fullname, $email, $contact, $address, $userId);
        
        if ($stmt->execute()) {
            $_SESSION['user']['fullname'] = $fullname;
            $_SESSION['user']['email'] = $email;
            $success = "Profile updated successfully!";
            
            // Refresh data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $userData = $stmt->get_result()->fetch_assoc();
        } else {
            $errors[] = "Failed to update profile.";
        }
    }
}

// CHANGE PASSWORD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if (!password_verify($currentPassword, $userData['password'])) {
        $errors[] = "Current password is incorrect.";
    }

    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/', $newPassword)) {
        $errors[] = "Password must be at least 8 characters with uppercase, lowercase, number & special character.";
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = "New passwords do not match.";
    }

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

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM issues WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$totalIssued = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM issues WHERE user_id = ? AND return_date IS NULL");
$stmt->bind_param("i", $userId);
$stmt->execute();
$activeIssues = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

$stmt = $conn->prepare("SELECT SUM(fine_amount) as total FROM issues WHERE user_id = ? AND fine_amount > 0 AND fine_paid = 0");
$stmt->bind_param("i", $userId);
$stmt->execute();
$unpaidFines = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/responsive.css">
     <style>
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 25px;
            margin-top: 30px;
        }

        .profile-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px;
        }

        .profile-header {
            text-align: center;
            padding-bottom: 25px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 25px;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--green), #06c456);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #000;
            margin: 0 auto 15px;
            font-weight: 700;
        }

        .profile-name {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .profile-role {
            color: var(--green);
            font-size: 14px;
            font-weight: 500;
        }

        .profile-stats {
            display: grid;
            gap: 15px;
            margin-top: 20px;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 8px;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .stat-number {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 18px;
        }

        .section-title {
            font-size: 20px;
            color: var(--text-primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--green);
            background: rgba(255, 255, 255, 0.08);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn-primary {
            background: var(--green);
            color: #000;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--green-hover);
            color: #fff;
            transform: translateY(-2px);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .info-item {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            width: 140px;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .info-value {
            flex: 1;
            color: var(--text-primary);
            font-size: 14px;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 1px solid var(--border-color);
        }

        .tab {
            padding: 12px 20px;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .tab.active {
            color: var(--green);
            border-bottom-color: var(--green);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .activity-item {
            padding: 12px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .activity-info {
            flex: 1;
        }

        .activity-title {
            font-size: 14px;
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 3px;
        }

        .activity-detail {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .activity-date {
            font-size: 12px;
            color: var(--text-muted);
        }

        .activity-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 10px;
        }

        .activity-badge.issued {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
        }

        .activity-badge.returned {
            background: rgba(34, 197, 94, 0.15);
            color: #22c55e;
        }

        @media (max-width: 968px) {
            .profile-container {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="dashboard">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="profile-box">
            <h3><?= htmlspecialchars($user['fullname']); ?></h3>
            <p>Member</p>
        </div>

        <ul class="menu">
            <li><a href="../dashboard/member_dashboard.php">Dashboard</a></li>
            <li><a href="my_books.php">My Books</a></li>
            <li><a href="browse_books.php">Browse Books</a></li>
            <li><a href="issue_history.php">Issue History</a></li>
            <li><a href="profile_member.php" class="active">Profile</a></li>
            <li class="logout"><a href="../php/logout.php">Logout</a></li>
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
            
            <!-- Left Column -->
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?= strtoupper(substr($userData['fullname'], 0, 1)); ?>
                    </div>
                    <div class="profile-name"><?= htmlspecialchars($userData['fullname']); ?></div>
                    <div class="profile-role">Library Member</div>
                </div>

                <h3 class="section-title">Account Statistics</h3>
                <div class="profile-stats">
                    <div class="stat-item">
                        <span class="stat-label">Total Books Borrowed</span>
                        <span class="stat-number"><?= $totalIssued; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Active Issues</span>
                        <span class="stat-number"><?= $activeIssues; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Unpaid Fines</span>
                        <span class="stat-number" style="color: <?= $unpaidFines > 0 ? '#ef4444' : '#22c55e'; ?>">
                            NPR <?= number_format($unpaidFines, 2); ?>
                        </span>
                    </div>
                </div>

                <h3 class="section-title" style="margin-top: 30px;">Account Details</h3>
                <div class="info-item">
                    <span class="info-label">Member ID:</span>
                    <span class="info-value">#<?= $userData['id']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Account Type:</span>
                    <span class="info-value">Member</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status:</span>
                    <span class="info-value" style="color: <?= $userData['status'] === 'approved' ? '#22c55e' : '#eab308'; ?>">
                        <?= ucfirst($userData['status']); ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Member Since:</span>
                    <span class="info-value"><?= date('M d, Y', strtotime($userData['created_at'])); ?></span>
                </div>
            </div>

            <!-- Right Column -->
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
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });

        document.getElementById(tabName + '-tab').classList.add('active');
        event.target.classList.add('active');
    }
</script>
<script src="../js/mobile_menu.js"></script>
</body>
</html>