<?php
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'librarian' && $_SESSION['user']['role'] !== 'admin')) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];

// Include database connection
include("connection.php");

$searchTerm = '';
$members = [];

// ====================================
// SEARCH FUNCTIONALITY
// ====================================
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    $searchPattern = '%' . $searchTerm . '%';
    
    $stmt = $conn->prepare("
        SELECT u.*, 
               COUNT(DISTINCT i.issue_id) as total_issues,
               COUNT(DISTINCT CASE WHEN i.return_date IS NULL THEN i.issue_id END) as active_issues,
               SUM(CASE WHEN i.fine_paid = 0 THEN i.fine_amount ELSE 0 END) as unpaid_fines
        FROM users u
        LEFT JOIN issues i ON u.id = i.user_id
        WHERE u.role = 'member' 
        AND (u.fullname LIKE ? OR u.email LIKE ? OR u.contact LIKE ?)
        GROUP BY u.id
        ORDER BY u.fullname ASC
    ");
    $stmt->bind_param("sss", $searchPattern, $searchPattern, $searchPattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
} else {
    // Fetch all members
    $query = "
        SELECT u.*, 
               COUNT(DISTINCT i.issue_id) as total_issues,
               COUNT(DISTINCT CASE WHEN i.return_date IS NULL THEN i.issue_id END) as active_issues,
               SUM(CASE WHEN i.fine_paid = 0 THEN i.fine_amount ELSE 0 END) as unpaid_fines
        FROM users u
        LEFT JOIN issues i ON u.id = i.user_id
        WHERE u.role = 'member' AND u.status = 'approved'
        GROUP BY u.id
        ORDER BY u.fullname ASC
    ";
    
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
    }
}

// ====================================
// VIEW MEMBER DETAILS
// ====================================
$selectedMember = null;
$memberIssues = [];

if (isset($_GET['view']) && !empty($_GET['view'])) {
    $memberId = (int)$_GET['view'];
    
    // Fetch member details
    $stmt = $conn->prepare("
        SELECT u.*,
               COUNT(DISTINCT i.issue_id) as total_issues,
               COUNT(DISTINCT CASE WHEN i.return_date IS NULL THEN i.issue_id END) as active_issues,
               COUNT(DISTINCT CASE WHEN i.return_date IS NOT NULL THEN i.issue_id END) as returned_issues,
               SUM(CASE WHEN i.fine_paid = 0 THEN i.fine_amount ELSE 0 END) as unpaid_fines
        FROM users u
        LEFT JOIN issues i ON u.id = i.user_id
        WHERE u.id = ? AND u.role = 'member'
        GROUP BY u.id
    ");
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    $selectedMember = $stmt->get_result()->fetch_assoc();
    
    if ($selectedMember) {
        // Fetch member's issue history
        $stmt = $conn->prepare("
            SELECT i.*, b.title, b.author, b.isbn,
                   DATEDIFF(COALESCE(i.return_date, CURDATE()), i.due_date) as days_difference
            FROM issues i
            JOIN books b ON i.book_id = b.book_id
            WHERE i.user_id = ?
            ORDER BY i.issue_date DESC
        ");
        $stmt->bind_param("i", $memberId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $memberIssues[] = $row;
        }
    }
}

// Get statistics
$totalMembers = count($members);
$activeMembers = 0;
$membersWithOverdue = 0;

foreach ($members as $member) {
    if ($member['active_issues'] > 0) {
        $activeMembers++;
    }
    
    // Check for overdue books
    $stmt = $conn->prepare("
        SELECT COUNT(*) as overdue_count
        FROM issues
        WHERE user_id = ? AND return_date IS NULL AND due_date < CURDATE()
    ");
    $stmt->bind_param("i", $member['id']);
    $stmt->execute();
    $overdueCheck = $stmt->get_result()->fetch_assoc();
    
    if ($overdueCheck['overdue_count'] > 0) {
        $membersWithOverdue++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Members</title>
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

        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            align-items: center;
        }

        .search-input {
            flex: 1;
            max-width: 500px;
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
            text-decoration: none;
            display: inline-block;
        }

        .search-btn:hover {
            background: var(--green-hover);
            color: #fff;
        }

        .members-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .member-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .member-card:hover {
            transform: translateY(-4px);
            border-color: var(--green);
            box-shadow: 0 4px 20px rgba(10, 224, 100, 0.15);
        }

        .member-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .member-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--green), #06c456);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            color: #000;
            flex-shrink: 0;
        }

        .member-info {
            flex: 1;
        }

        .member-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 3px;
        }

        .member-email {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .member-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }

        .member-stat {
            text-align: center;
            padding: 8px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 6px;
        }

        .member-stat-label {
            font-size: 11px;
            color: var(--text-secondary);
            margin-bottom: 3px;
        }

        .member-stat-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .member-actions {
            display: flex;
            gap: 8px;
        }

        .btn-view {
            flex: 1;
            padding: 8px 16px;
            background: var(--green);
            color: #000;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-view:hover {
            background: var(--green-hover);
            color: #fff;
        }

        /* Member Details Modal */
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
            overflow-y: auto;
            padding: 20px;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }

        .modal-header h2 {
            color: var(--text-primary);
            font-size: 24px;
        }

        .btn-close {
            background: var(--border-color);
            color: var(--text-primary);
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-close:hover {
            background: #3a3f4e;
        }

        .member-details-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 25px;
            margin-bottom: 25px;
        }

        .detail-section {
            background: rgba(255, 255, 255, 0.02);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .detail-section h3 {
            font-size: 16px;
            color: var(--text-primary);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: var(--text-secondary);
            font-size: 13px;
        }

        .detail-value {
            color: var(--text-primary);
            font-size: 13px;
            font-weight: 500;
        }

        .issues-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .issues-table thead {
            background: var(--border-color);
        }

        .issues-table th {
            padding: 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            border-bottom: 2px solid #3a3f4e;
        }

        .issues-table td {
            padding: 12px;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
        }

        .issues-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .no-members {
            text-align: center;
            padding: 60px;
            color: var(--text-muted);
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .no-members h3 {
            font-size: 24px;
            margin-bottom: 10px;
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
            <?php if ($user['role'] === 'librarian'): ?>
                <li><a href="../dashboard/librarian_dashboard.php">Dashboard</a></li>
                <li><a href="../librarian/book_list.php">Manage Books</a></li>
                <li><a href="../php/issue_books.php">Issue Books</a></li>
                <li><a href="../php/return_books.php">Return Books</a></li>
                <li><a href="../php/view_members.php" class="active">View Members</a></li>
                <li><a href="../librarian/profile_librarian.php">Profile</a></li>
            <?php else: ?>
                <li><a href="../dashboard/admin_dashboard.php">Dashboard</a></li>
                <li><a href="../admin/manage_librarian.php">Manage Librarians</a></li>
                <li><a href="../admin/manage_member.php">Manage Members</a></li>
                <li><a href="../admin/view_reports.php">View Reports</a></li>
                <li><a href="../php/view_members.php" class="active">View Members</a></li>
                <li><a href="../librarian/book_list.php">Manage Books</a></li>
                <li><a href="../php/issue_books.php">Issue Books</a></li>
                <li><a href="../php/return_books.php">Return Books</a></li>
                <li><a href="../admin/profile.php">Profile</a></li>
            <?php endif; ?>
            <li class="logout"><a href="../php/logout.php">Logout</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="content">
        <h1>View Members 👥</h1>
        <p>Browse and manage library members</p>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-box">
                <h3>Total Members</h3>
                <div class="number"><?= $totalMembers; ?></div>
            </div>
            <div class="stat-box">
                <h3>Active Members</h3>
                <div class="number"><?= $activeMembers; ?></div>
            </div>
            <div class="stat-box">
                <h3>Members with Overdue</h3>
                <div class="number"><?= $membersWithOverdue; ?></div>
            </div>
        </div>

        <!-- Search Box -->
        <form method="GET" class="search-box">
            <input 
                type="text" 
                name="search" 
                class="search-input" 
                placeholder="Search by name, email, or contact number..."
                value="<?= htmlspecialchars($searchTerm); ?>"
            >
            <button type="submit" class="search-btn">🔍 Search</button>
            <?php if ($searchTerm): ?>
                <a href="view_members.php" class="search-btn" style="background: var(--border-color);">Clear</a>
            <?php endif; ?>
        </form>

        <!-- Members Grid -->
        <?php if (count($members) > 0): ?>
            <div class="members-grid">
                <?php foreach ($members as $member): ?>
                    <div class="member-card" onclick="window.location.href='?view=<?= $member['id']; ?>'">
                        <div class="member-header">
                            <div class="member-avatar">
                                <?= strtoupper(substr($member['fullname'], 0, 1)); ?>
                            </div>
                            <div class="member-info">
                                <div class="member-name"><?= htmlspecialchars($member['fullname']); ?></div>
                                <div class="member-email"><?= htmlspecialchars($member['email']); ?></div>
                            </div>
                        </div>

                        <div class="member-stats">
                            <div class="member-stat">
                                <div class="member-stat-label">Total Issues</div>
                                <div class="member-stat-value"><?= $member['total_issues'] ?? 0; ?></div>
                            </div>
                            <div class="member-stat">
                                <div class="member-stat-label">Active</div>
                                <div class="member-stat-value"><?= $member['active_issues'] ?? 0; ?></div>
                            </div>
                        </div>

                        <div class="member-actions">
                            <a href="?view=<?= $member['id']; ?>" class="btn-view">👁️ View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-members">
                <h3>No Members Found</h3>
                <p><?= $searchTerm ? 'Try a different search term' : 'No approved members in the system'; ?></p>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Member Details Modal -->
<?php if ($selectedMember): ?>
<div class="modal active" id="memberModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>📋 Member Details</h2>
            <button class="btn-close" onclick="window.location.href='view_members.php'">✕ Close</button>
        </div>

        <div class="member-details-grid">
            <!-- Personal Information -->
            <div class="detail-section">
                <h3>Personal Information</h3>
                <div class="detail-item">
                    <span class="detail-label">Member ID:</span>
                    <span class="detail-value">#<?= $selectedMember['id']; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Full Name:</span>
                    <span class="detail-value"><?= htmlspecialchars($selectedMember['fullname']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value"><?= htmlspecialchars($selectedMember['email']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Contact:</span>
                    <span class="detail-value"><?= htmlspecialchars($selectedMember['contact'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Address:</span>
                    <span class="detail-value"><?= htmlspecialchars($selectedMember['address'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Joined:</span>
                    <span class="detail-value"><?= date('M d, Y', strtotime($selectedMember['created_at'])); ?></span>
                </div>
            </div>

            <!-- Statistics -->
            <div class="detail-section">
                <h3>Account Statistics</h3>
                <div class="detail-item">
                    <span class="detail-label">Total Issues:</span>
                    <span class="detail-value"><?= $selectedMember['total_issues'] ?? 0; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Active Issues:</span>
                    <span class="detail-value"><?= $selectedMember['active_issues'] ?? 0; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Returned Books:</span>
                    <span class="detail-value"><?= $selectedMember['returned_issues'] ?? 0; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Unpaid Fines:</span>
                    <span class="detail-value" style="color: <?= ($selectedMember['unpaid_fines'] > 0) ? '#ef4444' : '#22c55e'; ?>">
                        NPR <?= number_format($selectedMember['unpaid_fines'] ?? 0, 2); ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value" style="color: #22c55e;">
                        <?= ucfirst($selectedMember['status']); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Issue History -->
        <div class="detail-section">
            <h3>Issue History</h3>
            <?php if (count($memberIssues) > 0): ?>
                <table class="issues-table">
                    <thead>
                        <tr>
                            <th>Book</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                            <th>Fine</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($memberIssues as $issue): ?>
                            <?php
                                if ($issue['return_date']) {
                                    $status = 'Returned';
                                    $statusClass = 'returned';
                                } elseif ($issue['due_date'] < date('Y-m-d')) {
                                    $status = 'Overdue';
                                    $statusClass = 'overdue';
                                } else {
                                    $status = 'Active';
                                    $statusClass = 'issued';
                                }
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($issue['title']); ?></strong><br>
                                    <small style="color: var(--text-muted);"><?= htmlspecialchars($issue['author']); ?></small>
                                </td>
                                <td><?= date('M d, Y', strtotime($issue['issue_date'])); ?></td>
                                <td><?= date('M d, Y', strtotime($issue['due_date'])); ?></td>
                                <td>
                                    <?= $issue['return_date'] ? date('M d, Y', strtotime($issue['return_date'])) : '-'; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $statusClass; ?>">
                                        <?= $status; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($issue['fine_amount'] > 0): ?>
                                        NPR <?= number_format($issue['fine_amount'], 2); ?>
                                        <?php if ($issue['fine_paid']): ?>
                                            <br><span style="color: #22c55e; font-size: 11px;">✓ Paid</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: var(--text-muted); padding: 20px;">
                    No issue history available
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
<script src="../js/mobile_menu.js"></script>
</body>
</html>