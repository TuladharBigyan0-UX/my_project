<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];

// Include database connection
include("connection.php");

// Initialize variables
$totalBooks = 0;
$totalCopies = 0;
$availableCopies = 0;
$issuedCopies = 0;
$totalStudents = 0;
$approvedStudents = 0;
$pendingStudents = 0;
$totalLibrarians = 0;
$totalIssues = 0;
$activeIssues = 0;
$returnedIssues = 0;
$overdueIssues = 0;
$totalFines = 0;
$paidFines = 0;
$unpaidFines = 0;
$topBooks = [];
$categoryStats = [];
$monthlyIssues = [];

// ====================================
// BOOKS STATISTICS
// ====================================
$checkBooks = $conn->query("SHOW TABLES LIKE 'books'");
if ($checkBooks && $checkBooks->num_rows > 0) {
    // Total books
    $result = $conn->query("SELECT COUNT(*) as count FROM books");
    if ($result) $totalBooks = $result->fetch_assoc()['count'] ?? 0;
    
    // Total copies
    $result = $conn->query("SELECT SUM(total_copies) as total FROM books");
    if ($result) $totalCopies = $result->fetch_assoc()['total'] ?? 0;
    
    // Available copies
    $result = $conn->query("SELECT SUM(available_copies) as total FROM books");
    if ($result) $availableCopies = $result->fetch_assoc()['total'] ?? 0;
    
    // Issued copies
    $issuedCopies = $totalCopies - $availableCopies;
    
    // Top 5 most issued books
    $result = $conn->query("
        SELECT b.title, b.author, COUNT(i.issue_id) as issue_count
        FROM books b
        LEFT JOIN issues i ON b.book_id = i.book_id
        GROUP BY b.book_id
        ORDER BY issue_count DESC
        LIMIT 5
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $topBooks[] = $row;
        }
    }
    
    // Books by category
    $result = $conn->query("
        SELECT category, COUNT(*) as count
        FROM books
        GROUP BY category
        ORDER BY count DESC
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categoryStats[] = $row;
        }
    }
}

// ====================================
// STUDENTS STATISTICS
// ====================================
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='member'");
if ($result) $totalStudents = $result->fetch_assoc()['count'] ?? 0;

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='member' AND status='approved'");
if ($result) $approvedStudents = $result->fetch_assoc()['count'] ?? 0;

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='member' AND status='pending'");
if ($result) $pendingStudents = $result->fetch_assoc()['count'] ?? 0;

// ====================================
// LIBRARIANS STATISTICS
// ====================================
$result = $conn->query("SELECT COUNT(*) as count FROM librarians");
if ($result) $totalLibrarians = $result->fetch_assoc()['count'] ?? 0;

// ====================================
// ISSUES STATISTICS
// ====================================
$checkIssues = $conn->query("SHOW TABLES LIKE 'issues'");
if ($checkIssues && $checkIssues->num_rows > 0) {
    // Total issues
    $result = $conn->query("SELECT COUNT(*) as count FROM issues");
    if ($result) $totalIssues = $result->fetch_assoc()['count'] ?? 0;
    
    // Active issues
    $result = $conn->query("SELECT COUNT(*) as count FROM issues WHERE return_date IS NULL");
    if ($result) $activeIssues = $result->fetch_assoc()['count'] ?? 0;
    
    // Returned issues
    $result = $conn->query("SELECT COUNT(*) as count FROM issues WHERE return_date IS NOT NULL");
    if ($result) $returnedIssues = $result->fetch_assoc()['count'] ?? 0;
    
    // Overdue issues
    $result = $conn->query("SELECT COUNT(*) as count FROM issues WHERE return_date IS NULL AND due_date < CURDATE()");
    if ($result) $overdueIssues = $result->fetch_assoc()['count'] ?? 0;
    
    // Monthly issues (last 6 months)
    $result = $conn->query("
        SELECT DATE_FORMAT(issue_date, '%Y-%m') as month, COUNT(*) as count
        FROM issues
        WHERE issue_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $monthlyIssues[] = $row;
        }
    }
    
    // Fines statistics
    $result = $conn->query("SELECT SUM(fine_amount) as total FROM issues WHERE fine_amount > 0");
    if ($result) $totalFines = $result->fetch_assoc()['total'] ?? 0;
    
    $result = $conn->query("SELECT SUM(fine_amount) as total FROM issues WHERE fine_amount > 0 AND fine_paid = 1");
    if ($result) $paidFines = $result->fetch_assoc()['total'] ?? 0;
    
    $unpaidFines = $totalFines - $paidFines;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Reports - Admin Dashboard</title>
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
            <li><a href="view_reports.php" class="active">View Reports</a></li>
            <li><a href="return_books.php">Return Books</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li class="logout"><a href="logout.php">Logout</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="content">
        <h1>Library Reports</h1>
        <p>Comprehensive analytics and statistics</p>
        <!-- Reports Grid -->
        <div class="reports-grid">
            
            <!-- Books Statistics -->
            <div class="report-section">
                <h3>📚 Books Statistics</h3>
                <div class="stat-row">
                    <span class="stat-label">Total Books</span>
                    <span class="stat-value blue"><?= $totalBooks; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Total Copies</span>
                    <span class="stat-value"><?= $totalCopies; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Available Copies</span>
                    <span class="stat-value green"><?= $availableCopies; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Issued Copies</span>
                    <span class="stat-value yellow"><?= $issuedCopies; ?></span>
                </div>
            </div>

            <!-- Members Statistics -->
            <div class="report-section">
                <h3>👥 Members Statistics</h3>
                <div class="stat-row">
                    <span class="stat-label">Total Members</span>
                    <span class="stat-value blue"><?= $totalStudents; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Approved Members</span>
                    <span class="stat-value green"><?= $approvedStudents; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Pending Approval</span>
                    <span class="stat-value yellow"><?= $pendingStudents; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Total Librarians</span>
                    <span class="stat-value"><?= $totalLibrarians; ?></span>
                </div>
            </div>

            <!-- Issues Statistics -->
            <div class="report-section">
                <h3>📖 Issues Statistics</h3>
                <div class="stat-row">
                    <span class="stat-label">Total Issues</span>
                    <span class="stat-value blue"><?= $totalIssues; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Active Issues</span>
                    <span class="stat-value yellow"><?= $activeIssues; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Returned</span>
                    <span class="stat-value green"><?= $returnedIssues; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Overdue</span>
                    <span class="stat-value red"><?= $overdueIssues; ?></span>
                </div>
            </div>

            <!-- Fines Statistics -->
            <div class="report-section">
                <h3>💰 Fines Statistics</h3>
                <div class="stat-row">
                    <span class="stat-label">Total Fines</span>
                    <span class="stat-value blue">NPR <?= number_format($totalFines, 2); ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Paid Fines</span>
                    <span class="stat-value green">NPR <?= number_format($paidFines, 2); ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Unpaid Fines</span>
                    <span class="stat-value red">NPR <?= number_format($unpaidFines, 2); ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Collection Rate</span>
                    <span class="stat-value">
                        <?= $totalFines > 0 ? number_format(($paidFines / $totalFines) * 100, 1) : 0; ?>%
                    </span>
                </div>
            </div>

            <!-- Top 5 Most Issued Books -->
            <div class="report-section full-width">
                <h3>📊 Top 5 Most Issued Books</h3>
                <?php if (count($topBooks) > 0): ?>
                    <div class="chart-container">
                        <?php 
                        $maxIssues = max(array_column($topBooks, 'issue_count'));
                        foreach ($topBooks as $book): 
                            $percentage = $maxIssues > 0 ? ($book['issue_count'] / $maxIssues) * 100 : 0;
                        ?>
                            <div class="chart-bar">
                                <div class="chart-label" title="<?= htmlspecialchars($book['title']); ?>">
                                    <?= htmlspecialchars($book['title']); ?>
                                </div>
                                <div class="chart-bar-bg">
                                    <div class="chart-bar-fill" style="width: <?= $percentage; ?>%"></div>
                                </div>
                                <div class="chart-value"><?= $book['issue_count']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data">No book issues recorded yet</div>
                <?php endif; ?>
            </div>

            <!-- Books by Category -->
            <div class="report-section full-width">
                <h3>📚 Books by Category</h3>
                <?php if (count($categoryStats) > 0): ?>
                    <div class="chart-container">
                        <?php 
                        $maxBooks = max(array_column($categoryStats, 'count'));
                        foreach ($categoryStats as $category): 
                            $percentage = $maxBooks > 0 ? ($category['count'] / $maxBooks) * 100 : 0;
                        ?>
                            <div class="chart-bar">
                                <div class="chart-label">
                                    <?= htmlspecialchars($category['category'] ?: 'Uncategorized'); ?>
                                </div>
                                <div class="chart-bar-bg">
                                    <div class="chart-bar-fill" style="width: <?= $percentage; ?>%"></div>
                                </div>
                                <div class="chart-value"><?= $category['count']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data">No books available</div>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>

</body>
</html>