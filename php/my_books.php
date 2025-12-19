<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'member') {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
include("connection.php");

$userId = $user['id'];
$currentBooks = [];

// Fetch currently borrowed books
$stmt = $conn->prepare("
    SELECT i.*, b.title, b.author, b.isbn, b.category,
           DATEDIFF(i.due_date, CURDATE()) as days_until_due,
           DATEDIFF(CURDATE(), i.due_date) as days_overdue
    FROM issues i
    JOIN books b ON i.book_id = b.book_id
    WHERE i.user_id = ? AND i.return_date IS NULL
    ORDER BY i.due_date ASC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $currentBooks[] = $row;
}

// Calculate statistics
$totalActive = count($currentBooks);
$overdueCount = 0;
$dueSoonCount = 0;
$totalFines = 0;

foreach ($currentBooks as $book) {
    if ($book['days_until_due'] < 0) {
        $overdueCount++;
    } elseif ($book['days_until_due'] <= 3) {
        $dueSoonCount++;
    }
    if ($book['fine_amount'] > 0 && !$book['fine_paid']) {
        $totalFines += $book['fine_amount'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Books</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .book-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 25px;
            transition: all 0.3s ease;
        }

        .book-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(10, 224, 100, 0.15);
            border-color: var(--green);
        }

        .book-card.overdue {
            border-color: rgba(239, 68, 68, 0.5);
            background: rgba(239, 68, 68, 0.05);
        }

        .book-card.due-soon {
            border-color: rgba(234, 179, 8, 0.5);
            background: rgba(234, 179, 8, 0.05);
        }

        .book-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .book-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--green), #06c456);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .book-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .book-author {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .book-details {
            margin: 15px 0;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }

        .detail-label {
            color: var(--text-secondary);
        }

        .detail-value {
            color: var(--text-primary);
            font-weight: 500;
        }

        .book-status {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }

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

        .no-books {
            text-align: center;
            padding: 60px;
            color: var(--text-muted);
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .no-books h3 {
            font-size: 24px;
            margin-bottom: 15px;
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
            <li><a href="my_books.php" class="active">My Books</a></li>
            <li><a href="browse_books.php">Browse Books</a></li>
            <li><a href="issue_history.php">Issue History</a></li>
            <li><a href="profile_member.php">Profile</a></li>
            <li class="logout"><a href="logout.php">Logout</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="content">
        <h1>📚 My Books</h1>
        <p>Books currently borrowed by you</p>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-box">
                <h3>Active Borrowings</h3>
                <div class="number"><?= $totalActive; ?></div>
            </div>
            <div class="stat-box">
                <h3>Overdue Books</h3>
                <div class="number" style="color: <?= $overdueCount > 0 ? '#ef4444' : 'inherit'; ?>">
                    <?= $overdueCount; ?>
                </div>
            </div>
            <div class="stat-box">
                <h3>Due Soon</h3>
                <div class="number" style="color: <?= $dueSoonCount > 0 ? '#eab308' : 'inherit'; ?>">
                    <?= $dueSoonCount; ?>
                </div>
            </div>
            <div class="stat-box">
                <h3>Unpaid Fines</h3>
                <div class="number" style="color: <?= $totalFines > 0 ? '#ef4444' : '#22c55e'; ?>">
                    NPR <?= number_format($totalFines, 2); ?>
                </div>
            </div>
        </div>

        <!-- Books Grid -->
        <?php if (count($currentBooks) > 0): ?>
            <div class="books-grid">
                <?php foreach ($currentBooks as $book): ?>
                    <?php
                        $isOverdue = $book['days_until_due'] < 0;
                        $isDueSoon = $book['days_until_due'] >= 0 && $book['days_until_due'] <= 3;
                        
                        if ($isOverdue) {
                            $statusText = 'Overdue by ' . abs($book['days_overdue']) . ' days';
                            $statusClass = 'overdue';
                            $cardClass = 'overdue';
                        } elseif ($isDueSoon) {
                            $statusText = 'Due in ' . $book['days_until_due'] . ' days';
                            $statusClass = 'pending';
                            $cardClass = 'due-soon';
                        } else {
                            $statusText = 'Due in ' . $book['days_until_due'] . ' days';
                            $statusClass = 'issued';
                            $cardClass = '';
                        }
                    ?>
                    <div class="book-card <?= $cardClass; ?>">
                        <div class="book-header">
                            <div style="flex: 1;">
                                <div class="book-title"><?= htmlspecialchars($book['title']); ?></div>
                                <div class="book-author">by <?= htmlspecialchars($book['author']); ?></div>
                            </div>
                            <div class="book-icon">📖</div>
                        </div>

                        <div class="book-details">
                            <div class="detail-row">
                                <span class="detail-label">Issue Date:</span>
                                <span class="detail-value"><?= date('M d, Y', strtotime($book['issue_date'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Due Date:</span>
                                <span class="detail-value"><?= date('M d, Y', strtotime($book['due_date'])); ?></span>
                            </div>
                            <?php if (!empty($book['category'])): ?>
                            <div class="detail-row">
                                <span class="detail-label">Category:</span>
                                <span class="detail-value"><?= htmlspecialchars($book['category']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($book['isbn'])): ?>
                            <div class="detail-row">
                                <span class="detail-label">ISBN:</span>
                                <span class="detail-value"><?= htmlspecialchars($book['isbn']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="book-status">
                            <span class="status-badge <?= $statusClass; ?>">
                                <?= $statusText; ?>
                            </span>
                            <?php if ($book['fine_amount'] > 0): ?>
                                <div style="margin-top: 10px; font-size: 14px;">
                                    <span style="color: #ef4444;">💰 Fine: NPR <?= number_format($book['fine_amount'], 2); ?></span>
                                    <?php if ($book['fine_paid']): ?>
                                        <span style="color: #22c55e; margin-left: 10px;">✓ Paid</span>
                                    <?php else: ?>
                                        <span style="color: #eab308; margin-left: 10px;">⚠️ Unpaid</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-books">
                <h3>📚 No Books Borrowed</h3>
                <p>You don't have any books borrowed currently.</p>
                <a href="browse_books.php" class="btn" style="margin-top: 20px; display: inline-block;">Browse Available Books</a>
            </div>
        <?php endif; ?>

        <?php if ($overdueCount > 0): ?>
        <div style="background: rgba(239, 68, 68, 0.15); color: #ef4444; padding: 15px; border-radius: 8px; margin-top: 30px; border: 1px solid rgba(239, 68, 68, 0.3);">
            ⚠️ <strong>Important:</strong> You have <?= $overdueCount; ?> overdue book(s). Please return them to the library as soon as possible to avoid additional fines.
        </div>
        <?php endif; ?>

    </main>
</div>

</body>
</html>