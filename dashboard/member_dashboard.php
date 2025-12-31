<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'member') {
    header("Location: ../php/login.php");
    exit();
}

$user = $_SESSION['user'];

// Include database connection
include("../php/connection.php");

// Initialize variables
$activeIssues = 0;
$totalIssued = 0;
$overdueBooks = 0;
$totalFines = 0;
$unpaidFines = 0;
$recentIssues = [];
$availableBooks = 0;

// Get member statistics
$userId = $user['id'];

// Active issues
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM issues WHERE user_id = ? AND return_date IS NULL");
$stmt->bind_param("i", $userId);
$stmt->execute();
$activeIssues = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Total issued (all time)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM issues WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$totalIssued = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Overdue books
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM issues WHERE user_id = ? AND return_date IS NULL AND due_date < CURDATE()");
$stmt->bind_param("i", $userId);
$stmt->execute();
$overdueBooks = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Total fines
$stmt = $conn->prepare("SELECT SUM(fine_amount) as total FROM issues WHERE user_id = ? AND fine_amount > 0");
$stmt->bind_param("i", $userId);
$stmt->execute();
$totalFines = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Unpaid fines
$stmt = $conn->prepare("SELECT SUM(fine_amount) as total FROM issues WHERE user_id = ? AND fine_amount > 0 AND fine_paid = 0");
$stmt->bind_param("i", $userId);
$stmt->execute();
$unpaidFines = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Recent issues
$stmt = $conn->prepare("
    SELECT i.*, b.title, b.author, b.isbn,
           DATEDIFF(i.due_date, CURDATE()) as days_until_due,
           DATEDIFF(CURDATE(), i.due_date) as days_overdue
    FROM issues i
    JOIN books b ON i.book_id = b.book_id
    WHERE i.user_id = ? AND i.return_date IS NULL
    ORDER BY i.issue_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recentIssues[] = $row;
}

// Available books count
$checkBooks = $conn->query("SHOW TABLES LIKE 'books'");
if ($checkBooks && $checkBooks->num_rows > 0) {
    $result = $conn->query("SELECT SUM(available_copies) as count FROM books WHERE available_copies > 0");
    if ($result) $availableBooks = $result->fetch_assoc()['count'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard</title>
    <link rel="stylesheet" href="../css/dashboard.css">
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
            <li><a href="member_dashboard.php" class="active">Dashboard</a></li>
            <li><a href="../member/my_books.php">My Books</a></li>
            <li><a href="../member/browse_books.php">Browse Books</a></li>
            <li><a href="../member/issue_history.php">Issue History</a></li>
            <li><a href="../member/profile_member.php">Profile</a></li>
            <li class="logout"><a href="../php/logout.php">Logout</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="content">
        <h1>Welcome, <?= htmlspecialchars($user['fullname']); ?>! 👋</h1>
        <p>Here's your library activity overview</p>

        <!-- Metric Cards -->
        <div class="metric-cards">
            <!-- Active Issues -->
            <div class="metric-card blue">
                <div class="metric-info">
                    <h3>Active Issues</h3>
                    <div class="metric-value"><?= $activeIssues; ?></div>
                    <div class="metric-subtitle">Currently borrowed</div>
                </div>
                <div class="metric-icon">📖</div>
            </div>

            <!-- Total Issued -->
            <div class="metric-card green">
                <div class="metric-info">
                    <h3>Total Borrowed</h3>
                    <div class="metric-value"><?= $totalIssued; ?></div>
                    <div class="metric-subtitle">All time</div>
                </div>
                <div class="metric-icon">📚</div>
            </div>

            <!-- Overdue Books -->
            <div class="metric-card red">
                <div class="metric-info">
                    <h3>Overdue Books</h3>
                    <div class="metric-value"><?= $overdueBooks; ?></div>
                    <div class="metric-subtitle">Needs attention</div>
                </div>
                <div class="metric-icon">⏰</div>
            </div>

            <!-- Unpaid Fines -->
            <div class="metric-card yellow">
                <div class="metric-info">
                    <h3>Unpaid Fines</h3>
                    <div class="metric-value">NPR <?= number_format($unpaidFines, 2); ?></div>
                    <div class="metric-subtitle">Total: NPR <?= number_format($totalFines, 2); ?></div>
                </div>
                <div class="metric-icon">💰</div>
            </div>

            <!-- Available Books -->
            <div class="metric-card purple">
                <div class="metric-info">
                    <h3>Available Books</h3>
                    <div class="metric-value"><?= $availableBooks; ?></div>
                    <div class="metric-subtitle">Ready to borrow</div>
                </div>
                <div class="metric-icon">📕</div>
            </div>

            <!-- Issue Limit -->
            <div class="metric-card orange">
                <div class="metric-info">
                    <h3>Issue Limit</h3>
                    <div class="metric-value"><?= $activeIssues; ?>/3</div>
                    <div class="metric-subtitle">Books limit</div>
                </div>
                <div class="metric-icon">📊</div>
            </div>
        </div>

        <!-- Currently Borrowed Books -->
        <div class="recent-activity">
            <h2>📖 Currently Borrowed Books</h2>
            
            <?php if (count($recentIssues) > 0): ?>
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>Book</th>
                            <th>Author</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentIssues as $issue): ?>
                            <?php
                                $daysUntilDue = $issue['days_until_due'];
                                $isOverdue = $daysUntilDue < 0;
                                
                                if ($isOverdue) {
                                    $statusText = 'Overdue (' . abs($issue['days_overdue']) . ' days)';
                                    $statusClass = 'overdue';
                                } elseif ($daysUntilDue <= 3) {
                                    $statusText = 'Due soon (' . $daysUntilDue . ' days)';
                                    $statusClass = 'pending';
                                } else {
                                    $statusText = 'Active';
                                    $statusClass = 'issued';
                                }
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($issue['title']); ?></strong></td>
                                <td><?= htmlspecialchars($issue['author']); ?></td>
                                <td><?= date('M d, Y', strtotime($issue['issue_date'])); ?></td>
                                <td><?= date('M d, Y', strtotime($issue['due_date'])); ?></td>
                                <td>
                                    <span class="status-badge <?= $statusClass; ?>">
                                        <?= $statusText; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-activity">
                    <p>📚 You don't have any books borrowed currently.</p>
                    <a href="../php/browse_books.php" class="btn" style="margin-top: 15px; display: inline-block;">Browse Books</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($overdueBooks > 0): ?>
        <div style="background: rgba(239, 68, 68, 0.15); color: #ef4444; padding: 15px; border-radius: 8px; margin-top: 20px; border: 1px solid rgba(239, 68, 68, 0.3);">
            ⚠️ <strong>Important:</strong> You have <?= $overdueBooks; ?> overdue book(s). Please return them as soon as possible to avoid additional fines.
        </div>
        <?php endif; ?>

        <?php if ($unpaidFines > 0): ?>
        <div style="background: rgba(234, 179, 8, 0.15); color: #eab308; padding: 15px; border-radius: 8px; margin-top: 20px; border: 1px solid rgba(234, 179, 8, 0.3);">
            💰 You have unpaid fines totaling NPR <?= number_format($unpaidFines, 2); ?>. Please clear your fines to continue borrowing books.
        </div>
        <?php endif; ?>

    </main>
</div>
<script src="../js/mobile_menu.js"></script>
</body>
</html>