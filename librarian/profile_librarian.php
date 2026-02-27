<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'librarian') {
    header("Location: ../php/login.php");
    exit();
}

$user = $_SESSION['user'];

// Include database connection
include("../php/connection.php");

$errors = [];
$success = '';

// Fetch full librarian data from database
$librarianId = $user['id'];
$stmt = $conn->prepare("SELECT * FROM librarians WHERE id = ?");
$stmt->bind_param("i", $librarianId);
$stmt->execute();
$result = $stmt->get_result();
$librarianData = $result->fetch_assoc();

// ====================================
// UPDATE PROFILE
// ====================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    // Validation
    if (empty($fullname)) {
        $errors[] = "Full name is required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Check if email exists for other librarians
    $stmt = $conn->prepare("SELECT id FROM librarians WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $librarianId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Email already exists.";
    }

    // Validate phone number
    if (!empty($phone) && !preg_match('/^9[6-8][0-9]{8}$/', $phone)) {
        $errors[] = "Contact must start with 9, second digit 6-8, and be 10 digits.";
    }

    // Update if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE librarians SET fullname = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("sssi", $fullname, $email, $phone, $librarianId);
        
        if ($stmt->execute()) {
            $_SESSION['user']['fullname'] = $fullname;
            $_SESSION['user']['email'] = $email;
            $success = "Profile updated successfully!";
            
            // Refresh librarian data
            $stmt = $conn->prepare("SELECT * FROM librarians WHERE id = ?");
            $stmt->bind_param("i", $librarianId);
            $stmt->execute();
            $librarianData = $stmt->get_result()->fetch_assoc();
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
    if (!password_verify($currentPassword, $librarianData['password'])) {
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
        $stmt = $conn->prepare("UPDATE librarians SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $librarianId);
        
        if ($stmt->execute()) {
            $success = "Password changed successfully!";
        } else {
            $errors[] = "Failed to change password.";
        }
    }
}

// Get librarian statistics
$checkBooks = $conn->query("SHOW TABLES LIKE 'books'");
$totalBooks = 0;
$availableBooks = 0;

if ($checkBooks && $checkBooks->num_rows > 0) {
    $totalBooks = $conn->query("SELECT COUNT(*) as count FROM books")->fetch_assoc()['count'] ?? 0;
    $availableBooks = $conn->query("SELECT SUM(available_copies) as count FROM books")->fetch_assoc()['count'] ?? 0;
}

$checkIssues = $conn->query("SHOW TABLES LIKE 'issues'");
$booksIssuedByMe = 0;
$booksReturnedToMe = 0;
$activeIssues = 0;

if ($checkIssues && $checkIssues->num_rows > 0) {
    // Books issued by this librarian
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM issues WHERE issued_by = ?");
    $stmt->bind_param("i", $librarianId);
    $stmt->execute();
    $booksIssuedByMe = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
    
    // Books returned to this librarian
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM issues WHERE returned_to = ? AND return_date IS NOT NULL");
    $stmt->bind_param("i", $librarianId);
    $stmt->execute();
    $booksReturnedToMe = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
    
    // Current active issues
    $activeIssues = $conn->query("SELECT COUNT(*) as count FROM issues WHERE return_date IS NULL")->fetch_assoc()['count'] ?? 0;
}

// Recent activity by this librarian
$recentActivity = [];
if ($checkIssues && $checkIssues->num_rows > 0) {
    $stmt = $conn->prepare("
        SELECT i.issue_id, i.issue_date, i.return_date,
               b.title as book_title,
               u.fullname as member_name,
               CASE 
                   WHEN i.return_date IS NOT NULL THEN 'returned'
                   WHEN i.issued_by = ? THEN 'issued'
                   ELSE 'other'
               END as action_type
        FROM issues i
        JOIN books b ON i.book_id = b.book_id
        JOIN users u ON i.user_id = u.id
        WHERE i.issued_by = ? OR i.returned_to = ?
        ORDER BY COALESCE(i.return_date, i.issue_date) DESC
        LIMIT 10
    ");
    $stmt->bind_param("iii", $librarianId, $librarianId, $librarianId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $recentActivity[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Librarian Profile</title>
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
            <p>Librarian</p>
        </div>

        <ul class="menu">
            <li><a href="../dashboard/librarian_dashboard.php">Dashboard</a></li>
            <li><a href="manage_books.php">Manage Books</a></li>
            <li><a href="../php/issue_books.php">Issue Books</a></li>
            <li><a href="../php/return_books.php">Return Books</a></li>
            <li><a href="../php/view_members.php">View Members</a></li>
            <li><a href="profile_librarian.php" class="active">Profile</a></li>
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
            
            <!-- Left Column - Profile Info -->
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?= strtoupper(substr($librarianData['fullname'], 0, 1)); ?>
                    </div>
                    <div class="profile-name"><?= htmlspecialchars($librarianData['fullname']); ?></div>
                    <div class="profile-role">Librarian</div>
                </div>

                <h3 class="section-title">Work Statistics</h3>
                <div class="profile-stats">
                    <div class="stat-item">
                        <span class="stat-label">Books Issued</span>
                        <span class="stat-number"><?= $booksIssuedByMe; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Books Returned</span>
                        <span class="stat-number"><?= $booksReturnedToMe; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Total Books</span>
                        <span class="stat-number"><?= $totalBooks; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Available Books</span>
                        <span class="stat-number"><?= $availableBooks; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Active Issues</span>
                        <span class="stat-number"><?= $activeIssues; ?></span>
                    </div>
                </div>

                <h3 class="section-title" style="margin-top: 30px;">Account Details</h3>
                <div class="info-item">
                    <span class="info-label">Librarian ID:</span>
                    <span class="info-value">#<?= $librarianData['id']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?= htmlspecialchars($librarianData['email']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Phone:</span>
                    <span class="info-value"><?= htmlspecialchars($librarianData['phone'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Account Type:</span>
                    <span class="info-value">Librarian</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status:</span>
                    <span class="info-value" style="color: #22c55e;">Active</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Joined:</span>
                    <span class="info-value"><?= date('M d, Y', strtotime($librarianData['created_at'])); ?></span>
                </div>
            </div>

            <!-- Right Column - Edit Forms -->
            <div class="profile-card">
                <!-- Tabs -->
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('personal')">Personal Information</button>
                    <button class="tab" onclick="switchTab('security')">Security</button>
                    <button class="tab" onclick="switchTab('activity')">Recent Activity</button>
                </div>

                <!-- Personal Information Tab -->
                <div id="personal-tab" class="tab-content active">
                    <form method="POST">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="fullname" value="<?= htmlspecialchars($librarianData['fullname']); ?>" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Email Address *</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($librarianData['email']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="phone" pattern="^9[6-8][0-9]{8}$" placeholder="9*********" maxlength="10" value="<?= htmlspecialchars($librarianData['phone'] ?? ''); ?>">
                            </div>
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

                <!-- Recent Activity Tab -->
                <div id="activity-tab" class="tab-content">
                    <h3 class="section-title">Your Recent Activity</h3>
                    
                    <?php if (count($recentActivity) > 0): ?>
                        <?php foreach ($recentActivity as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-info">
                                    <div class="activity-title">
                                        <?= htmlspecialchars($activity['book_title']); ?>
                                        <span class="activity-badge <?= $activity['return_date'] ? 'returned' : 'issued'; ?>">
                                            <?= $activity['return_date'] ? 'Returned' : 'Issued'; ?>
                                        </span>
                                    </div>
                                    <div class="activity-detail">
                                        Member: <?= htmlspecialchars($activity['member_name']); ?>
                                    </div>
                                </div>
                                <div class="activity-date">
                                    <?= date('M d, Y', strtotime($activity['return_date'] ?? $activity['issue_date'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: var(--text-muted); padding: 40px;">
                            No recent activity
                        </p>
                    <?php endif; ?>
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
<script src="../js/mobile_menu.js"></script>
</body>
</html>