<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location:login.php");
    exit();
}

$user = $_SESSION['user'];

// Include database connection
include("../php/connection.php");

// Fetch statistics with error handling
$totalBooks = 0;
$availableBooks = 0;
$totalStudents = 0;
$booksIssuedToday = 0;
$overdueBooks = 0;
$activeIssues = 0;
$pendingFines = 0.00;

// Check if books table exists and fetch data
$checkBooks = $conn->query("SHOW TABLES LIKE 'books'");
if ($checkBooks && $checkBooks->num_rows > 0) {
    $result = $conn->query("SELECT COUNT(*) as count FROM books");
    if ($result) $totalBooks = $result->fetch_assoc()['count'] ?? 0;
    
    $result = $conn->query("SELECT SUM(available_copies) as count FROM books");
    if ($result) $availableBooks = $result->fetch_assoc()['count'] ?? 0;
}

// Fetch member count
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='member'");
if ($result) $totalMembers = $result->fetch_assoc()['count'] ?? 0;

// Check if issues table exists
$recentActivities = [];
$checkIssues = $conn->query("SHOW TABLES LIKE 'issues'");
if ($checkIssues && $checkIssues->num_rows > 0) {
    // Books issued today
    $result = $conn->query("SELECT COUNT(*) as count FROM issues WHERE DATE(issue_date) = CURDATE()");
    if ($result) $booksIssuedToday = $result->fetch_assoc()['count'] ?? 0;
    
    // Active issues
    $result = $conn->query("SELECT COUNT(*) as count FROM issues WHERE return_date IS NULL");
    if ($result) $activeIssues = $result->fetch_assoc()['count'] ?? 0;
    
    // Overdue books
    $result = $conn->query("SELECT COUNT(*) as count FROM issues WHERE return_date IS NULL AND due_date < CURDATE()");
    if ($result) $overdueBooks = $result->fetch_assoc()['count'] ?? 0;
    
    // Pending fines
    $result = $conn->query("SELECT SUM(fine_amount) as total FROM issues WHERE fine_amount > 0 AND fine_paid = 0");
    if ($result) {
        $fineData = $result->fetch_assoc();
        $pendingFines = $fineData['total'] ?? 0.00;
    }
    
    // Fetch recent activities (last 10 issues)
    $query = "SELECT i.issue_id, b.title as book_title, u.fullname as member_name, 
              i.issue_date, i.due_date, i.return_date, i.status
              FROM issues i
              JOIN books b ON i.book_id = b.book_id
              JOIN users u ON i.user_id = u.id
              ORDER BY i.issue_date DESC
              LIMIT 10";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recentActivities[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../css/dashboard.css">
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
            <li><a href="admin_dashboard.php" class="active">Dashboard</a></li>
            <li><a href="../php/manage_librarian.php">Manage Librarians</a></li>
            <li><a href="../php/manage_member.php">Manage Members</a></li>
            <li><a href="../php/return_books.php">Return Books</a></li>
            <li><a href="../php/view_reports.php">View Reports</a></li>
            <li><a href="../php/profile.php">Profile</a></li>
            <li class="logout"><a href="../php/logout.php">Logout</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="content">
        <h1>Dashboard Overview</h1>
        <p>Key metrics and insights</p>

        <!-- Metric Cards -->
        <div class="metric-cards">
            <!-- Total Books -->
            <div class="metric-card blue">
                <div class="metric-info">
                    <h3>Total Books</h3>
                    <div class="metric-value"><?= $totalBooks; ?></div>
                    <div class="metric-subtitle"><?= $availableBooks; ?> available</div>
                </div>
                <div class="metric-icon">📚</div>
            </div>

            <!-- Total Members -->
            <div class="metric-card green">
                <div class="metric-info">
                    <h3>Total Members</h3>
                    <div class="metric-value"><?= $totalMembers; ?></div>
                </div>
                <div class="metric-icon">👥</div>
            </div>

            <!-- Books Issued Today -->
            <div class="metric-card purple">
                <div class="metric-info">
                    <h3>Books Issued Today</h3>
                    <div class="metric-value"><?= $booksIssuedToday; ?></div>
                </div>
                <div class="metric-icon">📖</div>
            </div>

            <!-- Overdue Books -->
            <div class="metric-card red">
                <div class="metric-info">
                    <h3>Overdue Books</h3>
                    <div class="metric-value"><?= $overdueBooks; ?></div>
                </div>
                <div class="metric-icon">⏰</div>
            </div>

            <!-- Active Issues -->
            <div class="metric-card orange">
                <div class="metric-info">
                    <h3>Active Issues</h3>
                    <div class="metric-value"><?= $activeIssues; ?></div>
                </div>
                <div class="metric-icon">📊</div>
            </div>

            <!-- Pending Fines -->
            <div class="metric-card yellow">
                <div class="metric-info">
                    <h3>Pending Fines</h3>
                    <div class="metric-value">NPR <?= number_format($pendingFines, 2); ?></div>
                </div>
                <div class="metric-icon">💰</div>
            </div>
        </div>
            <!-- Recent Activity Section -->
        <div class="recent-activity">
            <h2>Recent Activity</h2>
            
            <?php if (count($recentActivities) > 0): ?>
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>Book</th>
                            <th>Members</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentActivities as $activity): ?>
                            <?php
                                // Determine status
                                $status = $activity['status'];
                                if ($activity['return_date']) {
                                    $status = 'returned';
                                    $statusClass = 'returned';
                                } elseif ($activity['due_date'] < date('Y-m-d')) {
                                    $status = 'overdue';
                                    $statusClass = 'overdue';
                                } else {
                                    $status = 'issued';
                                    $statusClass = 'issued';
                                }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($activity['book_title']); ?></td>
                                <td><?= htmlspecialchars($activity['student_name']); ?></td>
                                <td><?= date('M d, Y', strtotime($activity['issue_date'])); ?></td>
                                <td><?= date('M d, Y', strtotime($activity['due_date'])); ?></td>
                                <td>
                                    <span class="status-badge <?= $statusClass; ?>">
                                        <?= ucfirst($status); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-activity">No recent activities</div>
            <?php endif; ?>
        </div>

    </main>
</div>

</body>
</html>