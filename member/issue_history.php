<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'member') {
    header("Location: ../php/login.php");
    exit();
}

$user = $_SESSION['user'];
include("../php/connection.php");

$userId = $user['id'];
$issueHistory = [];

// Fetch all issue history
$stmt = $conn->prepare("
    SELECT i.*, b.title, b.author, b.isbn, b.category,
           DATEDIFF(COALESCE(i.return_date, CURDATE()), i.due_date) as days_difference
    FROM issues i
    JOIN books b ON i.book_id = b.book_id
    WHERE i.user_id = ?
    ORDER BY i.issue_date DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $issueHistory[] = $row;
}

// Calculate statistics
$totalIssued = count($issueHistory);
$activeIssues = 0;
$returnedOnTime = 0;
$returnedLate = 0;
$totalFinesPaid = 0;
$totalFinesUnpaid = 0;

foreach ($issueHistory as $issue) {
    if ($issue['return_date'] === null) {
        $activeIssues++;
    } else {
        // Book was returned
        if ($issue['days_difference'] <= 0) {
            $returnedOnTime++;
        } else {
            $returnedLate++;
        }
    }
    
    if ($issue['fine_amount'] > 0) {
        if ($issue['fine_paid']) {
            $totalFinesPaid += $issue['fine_amount'];
        } else {
            $totalFinesUnpaid += $issue['fine_amount'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue History</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <style>
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }

        .stat-box h3 {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }

        .stat-box .number {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .history-table-wrapper {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table thead {
            background: var(--border-color);
        }

        .history-table th {
            padding: 15px;
            text-align: left;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            border-bottom: 2px solid #3a3f4e;
        }

        .history-table td {
            padding: 15px;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }

        .history-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .history-table tbody tr:last-child td {
            border-bottom: none;
        }

        .book-title-cell {
            font-weight: 600;
        }

        .book-author-cell {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 3px;
        }

        .no-history {
            text-align: center;
            padding: 60px;
            color: var(--text-muted);
        }

        .no-history h3 {
            font-size: 24px;
            margin-bottom: 15px;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 1px solid var(--border-color);
        }

        .filter-tab {
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

        .filter-tab.active {
            color: var(--green);
            border-bottom-color: var(--green);
        }

        .filter-tab:hover {
            color: var(--green);
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
            <li><a href="issue_history.php" class="active">Issue History</a></li>
            <li><a href="profile_member.php">Profile</a></li>
            <li class="logout"><a href="../php/logout.php">Logout</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="content">
        <h1>📋 Issue History</h1>
        <p>Your complete borrowing history</p>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-box">
                <h3>Total Borrowed</h3>
                <div class="number"><?= $totalIssued; ?></div>
            </div>
            <div class="stat-box">
                <h3>Active Issues</h3>
                <div class="number" style="color: #3b82f6;"><?= $activeIssues; ?></div>
            </div>
            <div class="stat-box">
                <h3>Returned On Time</h3>
                <div class="number" style="color: #22c55e;"><?= $returnedOnTime; ?></div>
            </div>
            <div class="stat-box">
                <h3>Returned Late</h3>
                <div class="number" style="color: #eab308;"><?= $returnedLate; ?></div>
            </div>
            <div class="stat-box">
                <h3>Total Fines Paid</h3>
                <div class="number" style="font-size: 24px;">NPR <?= number_format($totalFinesPaid, 2); ?></div>
            </div>
            <div class="stat-box">
                <h3>Unpaid Fines</h3>
                <div class="number" style="color: #ef4444; font-size: 24px;">NPR <?= number_format($totalFinesUnpaid, 2); ?></div>
            </div>
        </div>

        <!-- History Table -->
        <?php if (count($issueHistory) > 0): ?>
            <div class="history-table-wrapper">
                <table class="history-table" id="historyTable">
                    <thead>
                        <tr>
                            <th>Book</th>
                            <th>Category</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                            <th>Fine</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($issueHistory as $issue): ?>
                            <?php
                                if ($issue['return_date']) {
                                    if ($issue['days_difference'] <= 0) {
                                        $status = 'Returned On Time';
                                        $statusClass = 'returned';
                                    } else {
                                        $status = 'Returned Late';
                                        $statusClass = 'pending';
                                    }
                                } else {
                                    if ($issue['due_date'] < date('Y-m-d')) {
                                        $status = 'Overdue';
                                        $statusClass = 'overdue';
                                    } else {
                                        $status = 'Active';
                                        $statusClass = 'issued';
                                    }
                                }
                            ?>
                            <tr data-status="<?= $issue['return_date'] ? 'returned' : 'active'; ?>">
                                <td>
                                    <div class="book-title-cell"><?= htmlspecialchars($issue['title']); ?></div>
                                    <div class="book-author-cell">by <?= htmlspecialchars($issue['author']); ?></div>
                                </td>
                                <td><?= htmlspecialchars($issue['category'] ?: '-'); ?></td>
                                <td><?= date('M d, Y', strtotime($issue['issue_date'])); ?></td>
                                <td><?= date('M d, Y', strtotime($issue['due_date'])); ?></td>
                                <td>
                                    <?= $issue['return_date'] ? date('M d, Y', strtotime($issue['return_date'])) : '<span style="color: var(--text-muted);">Not returned</span>'; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $statusClass; ?>">
                                        <?= $status; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($issue['fine_amount'] > 0): ?>
                                        <div>
                                            <span style="color: #ef4444;">NPR <?= number_format($issue['fine_amount'], 2); ?></span>
                                            <?php if ($issue['fine_paid']): ?>
                                                <br><span style="color: #22c55e; font-size: 11px;">✓ Paid</span>
                                            <?php else: ?>
                                                <br><span style="color: #eab308; font-size: 11px;">⚠️ Unpaid</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="history-table-wrapper">
                <div class="no-history">
                    <h3>📚 No History Available</h3>
                    <p>You haven't borrowed any books yet.</p>
                    <a href="browse_books.php" class="btn" style="margin-top: 20px; display: inline-block;">Browse Books</a>
                </div>
            </div>
        <?php endif; ?>

    </main>
</div>

<script>
function filterHistory(status) {
    const rows = document.querySelectorAll('#historyTable tbody tr');
    const tabs = document.querySelectorAll('.filter-tab');
    
    // Update active tab
    tabs.forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');
    
    // Filter rows
    rows.forEach(row => {
        if (status === 'all') {
            row.style.display = '';
        } else {
            row.style.display = row.dataset.status === status ? '' : 'none';
        }
    });
}
</script>
<script src="../js/mobile_menu.js"></script>
</body>
</html>