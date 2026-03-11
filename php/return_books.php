<?php
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'admin' && $_SESSION['user']['role'] !== 'librarian')) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];

// Include database connection
include("connection.php");

$errors = [];
$success = '';
$activeIssues = [];

// Fine calculation rate (per day)
$finePerDay = 5.00;

// Check if issues table exists
$checkIssues = $conn->query("SHOW TABLES LIKE 'issues'");
$issuesTableExists = $checkIssues && $checkIssues->num_rows > 0;

// ====================================
// PROCESS BOOK RETURN
// ====================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_book'])) {
    $issueId = (int)$_POST['issue_id'];
    $returnDate = date('Y-m-d');
    
    // Get issue details
    $stmt = $conn->prepare("
        SELECT i.*, b.book_id, b.title, b.available_copies 
        FROM issues i 
        JOIN books b ON i.book_id = b.book_id 
        WHERE i.issue_id = ? AND i.return_date IS NULL
    ");
    $stmt->bind_param("i", $issueId);
    $stmt->execute();
    $issue = $stmt->get_result()->fetch_assoc();
    
    if ($issue) {
        // Calculate fine if overdue
        $dueDate = new DateTime($issue['due_date']);
        $returnDateTime = new DateTime($returnDate);
        $daysDiff = $returnDateTime->diff($dueDate)->days;
        
        $fineAmount = 0;
        $status = 'returned';
        
        if ($returnDateTime > $dueDate) {
            // Overdue - calculate fine
            $fineAmount = $daysDiff * $finePerDay;
            $status = 'returned';
        }
        
        // Update issue record
        $stmt = $conn->prepare("
            UPDATE issues 
            SET return_date = ?, 
                fine_amount = ?, 
                status = ?,
                returned_to = ?
            WHERE issue_id = ?
        ");
        $stmt->bind_param("sdsii", $returnDate, $fineAmount, $status, $user['id'], $issueId);
        
        if ($stmt->execute()) {
            // Increment available copies
            $newAvailable = $issue['available_copies'] + 1;
            $stmt = $conn->prepare("UPDATE books SET available_copies = ? WHERE book_id = ?");
            $stmt->bind_param("ii", $newAvailable, $issue['book_id']);
            $stmt->execute();
            
            if ($fineAmount > 0) {
                $success = "Book returned successfully! Fine: NPR" . number_format($fineAmount, 2) . " (" . $daysDiff . " days overdue)";
            } else {
                $success = "Book returned successfully! No fine.";
            }
        } else {
            $errors[] = "Failed to process return.";
        }
    } else {
        $errors[] = "Invalid issue ID or book already returned.";
    }
}

// ====================================
// PAY FINE
// ====================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_fine'])) {
    $issueId = (int)$_POST['issue_id'];
    
    $stmt = $conn->prepare("UPDATE issues SET fine_paid = 1 WHERE issue_id = ?");
    $stmt->bind_param("i", $issueId);
    
    if ($stmt->execute()) {
        $success = "Fine payment recorded successfully!";
    } else {
        $errors[] = "Failed to record payment.";
    }
}

// ====================================
// FETCH ACTIVE ISSUES
// ====================================
if ($issuesTableExists) {
    $query = "
        SELECT i.issue_id, i.issue_date, i.due_date, i.fine_amount, i.fine_paid, i.status,
               b.title as book_title, b.author,
               u.fullname as member_name, u.email as member_email,
               DATEDIFF(CURDATE(), i.due_date) as days_overdue
        FROM issues i
        JOIN books b ON i.book_id = b.book_id
        JOIN users u ON i.user_id = u.id
        WHERE i.return_date IS NULL
        ORDER BY i.due_date ASC
    ";
    
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $activeIssues[] = $row;
        }
    }
}

// ====================================
// SEARCH FUNCTIONALITY
// ====================================
$searchResults = [];
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = '%' . $_GET['search'] . '%';
    
    $stmt = $conn->prepare("
        SELECT i.issue_id, i.issue_date, i.due_date, i.fine_amount, i.fine_paid,
               b.title as book_title, b.author,
               u.fullname as member_name, u.email as member_email,
               DATEDIFF(CURDATE(), i.due_date) as days_overdue
        FROM issues i
        JOIN books b ON i.book_id = b.book_id
        JOIN users u ON i.user_id = u.id
        WHERE i.return_date IS NULL 
        AND (u.fullname LIKE ? OR u.email LIKE ? OR b.title LIKE ?)
        ORDER BY i.due_date ASC
    ");
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $searchResults[] = $row;
    }
    
    if (!empty($searchResults)) {
        $activeIssues = $searchResults;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Books</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <style>
        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            align-items: center;
        }

        .search-input {
            flex: 1;
            max-width: 400px;
            padding: 12px 16px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--green);
        }

        .search-btn {
            padding: 12px 24px;
            background: var(--green);
            color: #000;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            background: var(--green-hover);
            color: #fff;
        }

        .return-table-wrapper {
            overflow-x: auto;
        }

        .return-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
        }

        .return-table thead {
            background: var(--border-color);
        }

        .return-table th {
            padding: 15px;
            text-align: left;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 14px;
            border-bottom: 2px solid #3a3f4e;
        }

        .return-table td {
            padding: 15px;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }

        .return-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .return-table tbody tr:last-child td {
            border-bottom: none;
        }

        .overdue {
            color: #ef4444;
            font-weight: 600;
        }

        .on-time {
            color: #22c55e;
        }

        .action-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 5px;
        }

        .btn-return {
            background: #22c55e;
            color: #000;
        }

        .btn-return:hover {
            background: #16a34a;
            color: #fff;
        }

        .btn-pay {
            background: #3b82f6;
            color: #fff;
        }

        .btn-pay:hover {
            background: #2563eb;
        }

        .btn-paid {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
            cursor: default;
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

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }

        .stat-card h3 {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }

        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .stat-card.overdue-card .number {
            color: #ef4444;
        }

        .no-issues {
            text-align: center;
            padding: 60px;
            color: var(--text-muted);
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .no-issues h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: var(--text-primary);
            font-size: 24px;
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-cancel {
            padding: 10px 20px;
            background: var(--border-color);
            color: var(--text-primary);
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-confirm {
            padding: 10px 20px;
            background: var(--green);
            color: #000;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="dashboard">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="profile-box">
            <h3><?= htmlspecialchars($user['fullname']); ?></h3>
            <p><?= ucfirst($user['role']); ?></p>
        </div>

        <ul class="menu">
    <?php if ($user['role'] === 'admin'): ?>
    <li><a href="../dashboard/admin_dashboard.php">Dashboard</a></li>
    <li><a href="../admin/manage_librarian.php">Manage Librarians</a></li>
    <li><a href="../admin/manage_member.php">Manage Members</a></li>
    <li><a href="../admin/view_reports.php">View Reports</a></li>
    <li><a href="view_members.php">View Members</a></li>
    <li><a href="../librarian/book_list.php">Manage Books</a></li>
    <li><a href="issue_books.php">Issue Books</a></li>
    <li><a href="return_books.php" class="active">Return Books</a></li>
    <li><a href="../admin/profile.php">Profile</a></li>

    <?php else: ?>
    <li><a href="../dashboard/librarian_dashboard.php">Dashboard</a></li>
    <li><a href="../librarian/book_list.php">Manage Books</a></li>
    <li><a href="../php/issue_books.php">Issue Books</a></li>
    <li><a href="../php/return_books.php" class="active">Return Books</a></li>
    <li><a href="../php/view_members.php">View Members</a></li>
    <li><a href="../librarian/profile_librarian.php">Profile</a></li>
    <?php endif; ?>

    <li class="logout"><a href="../php/logout.php">Logout</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="content">
        <h1>Return Books</h1>
        <p>Process book returns and manage fines</p>

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

        <!-- Statistics Cards -->
        <?php
        $totalActive = count($activeIssues);
        $overdueCount = 0;
        $totalFinesOwed = 0;
        
        foreach ($activeIssues as $issue) {
            if ($issue['days_overdue'] > 0) {
                $overdueCount++;
                $calculatedFine = $issue['days_overdue'] * $finePerDay;
                if (!$issue['fine_paid']) {
                    $totalFinesOwed += $calculatedFine;
                }
            }
        }
        ?>

        <div class="stats-cards">
            <div class="stat-card">
                <h3>Active Issues</h3>
                <div class="number"><?= $totalActive; ?></div>
            </div>
            <div class="stat-card overdue-card">
                <h3>Overdue Books</h3>
                <div class="number"><?= $overdueCount; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Fines Owed</h3>
                <div class="number">NPR <?= number_format($totalFinesOwed, 2); ?></div>
            </div>
        </div>

        <!-- Search Box -->
        <form method="GET" class="search-box">
            <input 
                type="text" 
                name="search" 
                class="search-input" 
                placeholder="Search by member name, email, or book title..."
                value="<?= htmlspecialchars($_GET['search'] ?? ''); ?>"
            >
            <button type="submit" class="search-btn">🔍 Search</button>
            <?php if (isset($_GET['search'])): ?>
                <a href="return_books.php" class="search-btn" style="background: var(--border-color); text-decoration: none;">Clear</a>
            <?php endif; ?>
        </form>

        <!-- Active Issues Table -->
        <?php if ($issuesTableExists && count($activeIssues) > 0): ?>
            <div class="return-table-wrapper">
                <table class="return-table">
                    <thead>
                        <tr>
                            <th>Issue ID</th>
                            <th>Book</th>
                            <th>Member</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Fine</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeIssues as $issue): ?>
                            <?php
                                $daysOverdue = max(0, $issue['days_overdue']);
                                $calculatedFine = $daysOverdue * $finePerDay;
                                $isOverdue = $daysOverdue > 0;
                            ?>
                            <tr>
                                <td>#<?= $issue['issue_id']; ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($issue['book_title']); ?></strong><br>
                                    <small style="color: var(--text-muted);"><?= htmlspecialchars($issue['author']); ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($issue['member_name']); ?><br>
                                    <small style="color: var(--text-muted);"><?= htmlspecialchars($issue['member_email']); ?></small>
                                </td>
                                <td><?= date('M d, Y', strtotime($issue['issue_date'])); ?></td>
                                <td><?= date('M d, Y', strtotime($issue['due_date'])); ?></td>
                                <td>
                                    <?php if ($isOverdue): ?>
                                        <span class="overdue">⚠️ <?= $daysOverdue; ?> days overdue</span>
                                    <?php else: ?>
                                        <span class="on-time">✓ On time</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($calculatedFine > 0): ?>
                                        <span class="overdue">NPR<?= number_format($calculatedFine, 2); ?></span>
                                        <?php if ($issue['fine_paid']): ?>
                                            <br><span style="color: #22c55e; font-size: 12px;">✓ Paid</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">No fine</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="issue_id" value="<?= $issue['issue_id']; ?>">
                                        <button type="submit" name="return_book" class="action-btn btn-return" 
                                                onclick="return confirm('Process return for this book?')">
                                            ✓ Return
                                        </button>
                                    </form>
                                    
                                    <?php if ($calculatedFine > 0): ?>
                                        <?php if (!$issue['fine_paid']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="issue_id" value="<?= $issue['issue_id']; ?>">
                                                <button type="submit" name="pay_fine" class="action-btn btn-pay"
                                                        onclick="return confirm('Mark fine as paid?')">
                                                    💰 Pay Fine
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="action-btn btn-paid" disabled>✓ Paid</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (!$issuesTableExists): ?>
            <div class="no-issues">
                <h3>⚠️ Issues Table Not Found</h3>
                <p>Please create the issues table to track book returns.</p>
            </div>
        <?php else: ?>
            <div class="no-issues">
                <h3>🎉 All Clear!</h3>
                <p>No active book issues to return.</p>
            </div>
        <?php endif; ?>
    </main>
</div>
<script src="../js/mobile_menu.js"></script>
</body>
</html>