<?php
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'librarian' && $_SESSION['user']['role'] !== 'admin')) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
include("connection.php");

$errors  = [];
$success = '';

// ====================================
// APPROVE / REJECT BORROW REQUEST
// ====================================
if (isset($_GET['approve_req']) || isset($_GET['reject_req'])) {
    $reqId  = (int)($_GET['approve_req'] ?? $_GET['reject_req']);
    $action = isset($_GET['approve_req']) ? 'approved' : 'rejected';
    $redir  = isset($_GET['view']) ? '?view=' . (int)$_GET['view'] : '';

    // Fetch request details
    $stmt = $conn->prepare("SELECT br.*, b.available_copies, b.title
                             FROM borrow_requests br
                             JOIN books b ON br.book_id = b.book_id
                             WHERE br.request_id = ? AND br.status = 'pending'");
    $stmt->bind_param("i", $reqId);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();

    if (!$req) {
        $success = "Request not found or already processed.";
    } elseif ($action === 'approved') {
        // Guard: book still available?
        if ($req['available_copies'] <= 0) {
            $success = "❌ Cannot approve — no copies of \"{$req['title']}\" are available.";
        } else {
            // 1. Mark request approved
            $stmt = $conn->prepare("UPDATE borrow_requests
                                    SET status = 'approved', reviewed_by = ?, reviewed_at = NOW()
                                    WHERE request_id = ?");
            $stmt->bind_param("ii", $user['id'], $reqId);
            $stmt->execute();

            // 2. Create an issue record (14-day default loan)
            $issueDate  = date('Y-m-d');
            $dueDate    = date('Y-m-d', strtotime('+14 days'));
            $status     = 'issued';
            $issuedById = $user['id'];

            $stmt = $conn->prepare("INSERT INTO issues
                                    (book_id, user_id, issue_date, due_date, status, issued_by)
                                    VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssi",
                $req['book_id'], $req['user_id'],
                $issueDate, $dueDate, $status, $issuedById);
            $stmt->execute();

            // 3. Decrement available_copies
            $stmt = $conn->prepare("UPDATE books
                                    SET available_copies = available_copies - 1
                                    WHERE book_id = ? AND available_copies > 0");
            $stmt->bind_param("i", $req['book_id']);
            $stmt->execute();

            $success = "✅ Request approved — \"{$req['title']}\" issued. Due: " . date('M d, Y', strtotime($dueDate));
        }
    } else {
        // Rejected — just update status
        $stmt = $conn->prepare("UPDATE borrow_requests
                                SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW()
                                WHERE request_id = ?");
        $stmt->bind_param("ii", $user['id'], $reqId);
        $stmt->execute();
        $success = "Request rejected.";
    }

    header("Location: view_members.php$redir" . ($redir ? '&' : '?') . "msg=" . urlencode($success));
    exit();
}

// Pick up flash message from redirect
if (!empty($_GET['msg'])) {
    $success = $_GET['msg'];
}

$searchTerm = '';
$members    = [];

// ====================================
// SEARCH
// ====================================
if (!empty($_GET['search'])) {
    $searchTerm    = trim($_GET['search']);
    $searchPattern = '%' . $searchTerm . '%';

    $stmt = $conn->prepare("
        SELECT u.*,
               COUNT(DISTINCT i.issue_id)                                              AS total_issues,
               COUNT(DISTINCT CASE WHEN i.return_date IS NULL THEN i.issue_id END)     AS active_issues,
               SUM(CASE WHEN i.fine_paid = 0 THEN i.fine_amount ELSE 0 END)            AS unpaid_fines
        FROM users u
        LEFT JOIN issues i ON u.id = i.user_id
        WHERE u.role = 'member'
          AND (u.fullname LIKE ? OR u.email LIKE ? OR u.contact LIKE ?)
        GROUP BY u.id
        ORDER BY u.fullname ASC
    ");
    $stmt->bind_param("sss", $searchPattern, $searchPattern, $searchPattern);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $members[] = $row;
} else {
    $res = $conn->query("
        SELECT u.*,
               COUNT(DISTINCT i.issue_id)                                              AS total_issues,
               COUNT(DISTINCT CASE WHEN i.return_date IS NULL THEN i.issue_id END)     AS active_issues,
               SUM(CASE WHEN i.fine_paid = 0 THEN i.fine_amount ELSE 0 END)            AS unpaid_fines
        FROM users u
        LEFT JOIN issues i ON u.id = i.user_id
        WHERE u.role = 'member' AND u.status = 'approved'
        GROUP BY u.id
        ORDER BY u.fullname ASC
    ");
    while ($row = $res->fetch_assoc()) $members[] = $row;
}

// ====================================
// VIEW MEMBER DETAILS
// ====================================
$selectedMember  = null;
$memberIssues    = [];
$borrowRequests  = [];

if (!empty($_GET['view'])) {
    $memberId = (int)$_GET['view'];

    $stmt = $conn->prepare("
        SELECT u.*,
               COUNT(DISTINCT i.issue_id)                                              AS total_issues,
               COUNT(DISTINCT CASE WHEN i.return_date IS NULL THEN i.issue_id END)     AS active_issues,
               COUNT(DISTINCT CASE WHEN i.return_date IS NOT NULL THEN i.issue_id END) AS returned_issues,
               SUM(CASE WHEN i.fine_paid = 0 THEN i.fine_amount ELSE 0 END)            AS unpaid_fines
        FROM users u
        LEFT JOIN issues i ON u.id = i.user_id
        WHERE u.id = ? AND u.role = 'member'
        GROUP BY u.id
    ");
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    $selectedMember = $stmt->get_result()->fetch_assoc();

    if ($selectedMember) {
        // Issue history
        $stmt = $conn->prepare("
            SELECT i.*, b.title, b.author, b.isbn,
                   DATEDIFF(COALESCE(i.return_date, CURDATE()), i.due_date) AS days_difference
            FROM issues i
            JOIN books b ON i.book_id = b.book_id
            WHERE i.user_id = ?
            ORDER BY i.issue_date DESC
        ");
        $stmt->bind_param("i", $memberId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $memberIssues[] = $row;

        // Borrow requests (if table exists)
        $checkReq = $conn->query("SHOW TABLES LIKE 'borrow_requests'");
        if ($checkReq && $checkReq->num_rows > 0) {
            $stmt = $conn->prepare("
                SELECT br.*, b.title, b.author, b.available_copies,
                       l.fullname AS reviewed_by_name
                FROM borrow_requests br
                JOIN books b ON br.book_id = b.book_id
                LEFT JOIN users lu  ON br.reviewed_by = lu.id AND lu.role IN ('admin','librarian')
                LEFT JOIN librarians l ON br.reviewed_by = l.id
                WHERE br.user_id = ?
                ORDER BY br.request_date DESC
            ");
            $stmt->bind_param("i", $memberId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) $borrowRequests[] = $row;
        }
    }
}

// Statistics
$totalMembers        = count($members);
$activeMembers       = 0;
$membersWithOverdue  = 0;
foreach ($members as $m) {
    if ($m['active_issues'] > 0) $activeMembers++;
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM issues
                             WHERE user_id = ? AND return_date IS NULL AND due_date < CURDATE()");
    $stmt->bind_param("i", $m['id']);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()['c'] > 0) $membersWithOverdue++;
}

// Pending requests count for badge
$pendingReqCount = 0;
$checkReq2 = $conn->query("SHOW TABLES LIKE 'borrow_requests'");
if ($checkReq2 && $checkReq2->num_rows > 0) {
    $pendingReqCount = $conn->query("SELECT COUNT(*) AS c FROM borrow_requests WHERE status='pending'")->fetch_assoc()['c'] ?? 0;
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
        /* ── Stats ── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px,1fr));
            gap: 20px; margin-bottom: 30px;
        }
        .stat-box {
            background: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: 10px; padding: 20px; text-align: center;
        }
        .stat-box h3 { font-size:14px; color:var(--text-secondary); margin-bottom:10px; }
        .stat-box .number { font-size:32px; font-weight:700; color:var(--text-primary); }

        /* ── Search ── */
        .search-box { display:flex; gap:10px; margin-bottom:25px; flex-wrap:wrap; }
        .search-input {
            flex:1; max-width:500px; padding:12px 16px;
            background:var(--card-bg); border:1px solid var(--border-color);
            border-radius:8px; color:var(--text-primary); font-size:14px;
        }
        .search-input:focus { outline:none; border-color:var(--green); }
        .search-btn {
            padding:12px 24px; background:var(--green); color:#000;
            border:none; border-radius:8px; font-weight:600; cursor:pointer;
            transition:all .2s; text-decoration:none; display:inline-block;
        }
        .search-btn:hover { background:var(--green-hover); color:#fff; }
        .search-btn.secondary { background:var(--border-color); color:var(--text-primary); }

        /* ── Pending requests banner ── */
        .pending-banner {
            display:flex; align-items:center; gap:12px;
            background:rgba(234,179,8,.12); border:1px solid rgba(234,179,8,.35);
            border-radius:10px; padding:14px 18px; margin-bottom:22px; font-size:14px;
            color:#eab308;
        }
        .pending-banner .badge-num {
            background:#eab308; color:#000;
            border-radius:20px; padding:2px 10px; font-weight:700; font-size:13px;
        }

        /* ── Members grid ── */
        .members-grid {
            display:grid;
            grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
            gap:20px; margin-top:20px;
        }
        .member-card {
            background:var(--card-bg); border:1px solid var(--border-color);
            border-radius:12px; padding:20px; cursor:pointer;
            transition:all .25s;
        }
        .member-card:hover { transform:translateY(-4px); border-color:var(--green); box-shadow:0 4px 20px rgba(10,224,100,.15); }
        .mc-header { display:flex; align-items:center; gap:14px; margin-bottom:14px; padding-bottom:14px; border-bottom:1px solid var(--border-color); }
        .mc-avatar {
            width:46px; height:46px; border-radius:50%;
            background:linear-gradient(135deg,var(--green),#06c456);
            display:flex; align-items:center; justify-content:center;
            font-size:20px; font-weight:700; color:#000; flex-shrink:0;
        }
        .mc-name  { font-size:15px; font-weight:600; color:var(--text-primary); margin-bottom:3px; }
        .mc-email { font-size:13px; color:var(--text-secondary); }
        .mc-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-bottom:14px; }
        .mc-stat  { text-align:center; padding:8px; background:rgba(255,255,255,.02); border-radius:6px; }
        .mc-stat-label { font-size:10px; color:var(--text-secondary); margin-bottom:2px; }
        .mc-stat-value { font-size:17px; font-weight:700; color:var(--text-primary); }
        .btn-view {
            display:block; width:100%; padding:9px; text-align:center;
            background:var(--green); color:#000; border:none; border-radius:7px;
            font-size:13px; font-weight:600; cursor:pointer; text-decoration:none;
            transition:all .2s;
        }
        .btn-view:hover { background:var(--green-hover); color:#fff; }
        .no-members { text-align:center; padding:60px; color:var(--text-muted); background:var(--card-bg); border:1px solid var(--border-color); border-radius:12px; }

        /* ── Alerts ── */
        .alert-success { background:rgba(34,197,94,.15); color:#22c55e; border:1px solid rgba(34,197,94,.3); border-radius:8px; padding:12px 16px; margin-bottom:20px; font-size:14px; }

        /* ── Modal ── */
        .modal {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,.8); z-index:1000;
            align-items:flex-start; justify-content:center;
            overflow-y:auto; padding:20px;
        }
        .modal.active { display:flex; }
        .modal-content {
            background:var(--card-bg); border:1px solid var(--border-color);
            border-radius:14px; padding:30px;
            max-width:920px; width:100%; margin:auto;
        }
        .modal-header {
            display:flex; justify-content:space-between; align-items:center;
            margin-bottom:25px; padding-bottom:15px; border-bottom:2px solid var(--border-color);
        }
        .modal-header h2 { color:var(--text-primary); font-size:22px; }
        .btn-close { background:var(--border-color); color:var(--text-primary); border:none; border-radius:7px; padding:9px 18px; cursor:pointer; font-weight:600; font-size:14px; }
        .btn-close:hover { background:#3a3f4e; }

        /* Member info grid inside modal */
        .detail-grid { display:grid; grid-template-columns:1fr 1.4fr; gap:20px; margin-bottom:24px; }
        .detail-section { background:rgba(255,255,255,.02); padding:18px; border-radius:10px; border:1px solid var(--border-color); }
        .detail-section h3 { font-size:15px; color:var(--text-primary); margin-bottom:14px; padding-bottom:10px; border-bottom:1px solid var(--border-color); }
        .detail-item { display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid rgba(255,255,255,.04); font-size:13px; }
        .detail-item:last-child { border-bottom:none; }
        .detail-label { color:var(--text-secondary); }
        .detail-value { color:var(--text-primary); font-weight:500; text-align:right; max-width:60%; }

        /* Tabs inside modal */
        .modal-tabs { display:flex; gap:8px; margin-bottom:20px; border-bottom:1px solid var(--border-color); }
        .modal-tab {
            padding:11px 20px; background:transparent; border:none;
            color:var(--text-secondary); font-size:14px; font-weight:500;
            cursor:pointer; border-bottom:2px solid transparent; transition:all .2s;
            position:relative;
        }
        .modal-tab.active { color:var(--green); border-bottom-color:var(--green); }
        .modal-tab .tab-badge {
            position:absolute; top:6px; right:6px;
            background:#ef4444; color:#fff;
            border-radius:10px; font-size:10px; padding:1px 6px; font-weight:700;
        }
        .modal-tab-content { display:none; }
        .modal-tab-content.active { display:block; }

        /* Tables inside modal */
        .data-table { width:100%; border-collapse:collapse; }
        .data-table thead { background:var(--border-color); }
        .data-table th { padding:12px 14px; text-align:left; font-size:13px; font-weight:600; color:var(--text-secondary); border-bottom:2px solid #3a3f4e; white-space:nowrap; }
        .data-table td { padding:12px 14px; color:var(--text-primary); border-bottom:1px solid var(--border-color); font-size:13px; vertical-align:middle; }
        .data-table tbody tr:hover { background:rgba(255,255,255,.02); }
        .data-table tbody tr:last-child td { border-bottom:none; }
        .table-wrap { overflow-x:auto; border-radius:8px; border:1px solid var(--border-color); }

        /* Action buttons inside table */
        .tbl-btn {
            padding:5px 12px; border:none; border-radius:6px;
            font-size:12px; font-weight:600; cursor:pointer;
            text-decoration:none; display:inline-block; transition:all .2s;
            margin-right:4px;
        }
        .tbl-btn.approve { background:rgba(34,197,94,.15); color:#22c55e; }
        .tbl-btn.approve:hover { background:#22c55e; color:#000; }
        .tbl-btn.reject  { background:rgba(239,68,68,.15);  color:#ef4444; }
        .tbl-btn.reject:hover  { background:#ef4444; color:#fff; }

        /* Request status badges */
        .req-status { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; }
        .req-status.pending  { background:rgba(234,179,8,.15);  color:#eab308; }
        .req-status.approved { background:rgba(34,197,94,.15);  color:#22c55e; }
        .req-status.rejected { background:rgba(239,68,68,.15);  color:#ef4444; }
        .req-status.cancelled{ background:rgba(107,114,128,.15); color:#9ca3af; }

        .no-data { text-align:center; padding:40px; color:var(--text-muted); font-size:14px; }

        @media (max-width:768px) {
            .detail-grid { grid-template-columns:1fr; }
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
                <li><a href="view_members.php" class="active">View Members</a></li>
                <li><a href="../librarian/profile_librarian.php">Profile</a></li>
            <?php else: ?>
                <li><a href="../dashboard/admin_dashboard.php">Dashboard</a></li>
                <li><a href="../admin/manage_librarian.php">Manage Librarians</a></li>
                <li><a href="../admin/manage_member.php">Manage Members</a></li>
                <li><a href="../admin/view_reports.php">View Reports</a></li>
                <li><a href="view_members.php" class="active">View Members</a></li>
                <li><a href="../librarian/book_list.php">Manage Books</a></li>
                <li><a href="issue_books.php">Issue Books</a></li>
                <li><a href="return_books.php">Return Books</a></li>
                <li><a href="../admin/profile.php">Profile</a></li>
            <?php endif; ?>
            <li class="logout"><a href="../php/logout.php">Logout</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="content">
        <h1>View Members 👥</h1>
        <p>Browse members, review borrow requests, and check issue history</p>

        <?php if ($success): ?>
            <div class="alert-success"><?= htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Pending requests banner -->
        <?php if ($pendingReqCount > 0): ?>
        <div class="pending-banner">
            📬 There <?= $pendingReqCount === 1 ? 'is' : 'are'; ?>
            <span class="badge-num"><?= $pendingReqCount; ?></span>
            pending borrow <?= $pendingReqCount === 1 ? 'request' : 'requests'; ?> waiting for review.
            Click a member card to open their details.
        </div>
        <?php endif; ?>

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
                <div class="number" style="color:<?= $membersWithOverdue > 0 ? '#ef4444' : 'inherit'; ?>"><?= $membersWithOverdue; ?></div>
            </div>
            <div class="stat-box">
                <h3>Pending Requests</h3>
                <div class="number" style="color:<?= $pendingReqCount > 0 ? '#eab308' : 'inherit'; ?>"><?= $pendingReqCount; ?></div>
            </div>
        </div>

        <!-- Search -->
        <form method="GET" class="search-box">
            <input type="text" name="search" class="search-input"
                   placeholder="Search by name, email, or contact…"
                   value="<?= htmlspecialchars($searchTerm); ?>">
            <button type="submit" class="search-btn">🔍 Search</button>
            <?php if ($searchTerm): ?>
                <a href="view_members.php" class="search-btn secondary">✕ Clear</a>
            <?php endif; ?>
        </form>

        <!-- Members Grid -->
        <?php if (count($members) > 0): ?>
        <div class="members-grid">
            <?php foreach ($members as $m): ?>
                <?php
                    // Pending requests for this member
                    $mPendingReq = 0;
                    $chk = $conn->query("SHOW TABLES LIKE 'borrow_requests'");
                    if ($chk && $chk->num_rows > 0) {
                        $s2 = $conn->prepare("SELECT COUNT(*) AS c FROM borrow_requests WHERE user_id = ? AND status='pending'");
                        $s2->bind_param("i", $m['id']);
                        $s2->execute();
                        $mPendingReq = $s2->get_result()->fetch_assoc()['c'] ?? 0;
                    }
                ?>
                <div class="member-card" onclick="window.location.href='?view=<?= $m['id']; ?>'">
                    <div class="mc-header">
                        <div class="mc-avatar"><?= strtoupper(substr($m['fullname'], 0, 1)); ?></div>
                        <div>
                            <div class="mc-name"><?= htmlspecialchars($m['fullname']); ?></div>
                            <div class="mc-email"><?= htmlspecialchars($m['email']); ?></div>
                        </div>
                    </div>
                    <div class="mc-stats">
                        <div class="mc-stat">
                            <div class="mc-stat-label">Total Borrows</div>
                            <div class="mc-stat-value"><?= $m['total_issues'] ?? 0; ?></div>
                        </div>
                        <div class="mc-stat">
                            <div class="mc-stat-label">Active</div>
                            <div class="mc-stat-value"><?= $m['active_issues'] ?? 0; ?></div>
                        </div>
                        <div class="mc-stat">
                            <div class="mc-stat-label">Requests</div>
                            <div class="mc-stat-value" style="color:<?= $mPendingReq > 0 ? '#eab308' : 'inherit'; ?>">
                                <?= $mPendingReq; ?>
                            </div>
                        </div>
                    </div>
                    <a href="?view=<?= $m['id']; ?>" class="btn-view">👁️ View Details</a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="no-members">
                <p style="font-size:32px;margin-bottom:10px;">👥</p>
                <p style="font-size:18px;font-weight:600;color:var(--text-primary);margin-bottom:6px;">No Members Found</p>
                <p><?= $searchTerm ? 'Try a different search term.' : 'No approved members in the system.'; ?></p>
            </div>
        <?php endif; ?>

    </main>
</div>

<!-- ===== Member Detail Modal ===== -->
<?php if ($selectedMember): ?>
<?php
    // Count pending requests for this member
    $pendingForMember = count(array_filter($borrowRequests, fn($r) => $r['status'] === 'pending'));
?>
<div class="modal active" id="memberModal">
    <div class="modal-content">

        <div class="modal-header">
            <h2>📋 <?= htmlspecialchars($selectedMember['fullname']); ?></h2>
            <a href="view_members.php<?= $searchTerm ? '?search='.urlencode($searchTerm) : ''; ?>" class="btn-close">✕ Close</a>
        </div>

        <!-- Personal info + stats -->
        <div class="detail-grid">
            <div class="detail-section">
                <h3>👤 Personal Information</h3>
                <div class="detail-item"><span class="detail-label">Member ID</span><span class="detail-value">#<?= $selectedMember['id']; ?></span></div>
                <div class="detail-item"><span class="detail-label">Full Name</span><span class="detail-value"><?= htmlspecialchars($selectedMember['fullname']); ?></span></div>
                <div class="detail-item"><span class="detail-label">Email</span><span class="detail-value"><?= htmlspecialchars($selectedMember['email']); ?></span></div>
                <div class="detail-item"><span class="detail-label">Contact</span><span class="detail-value"><?= htmlspecialchars($selectedMember['contact'] ?? 'N/A'); ?></span></div>
                <div class="detail-item"><span class="detail-label">Address</span><span class="detail-value"><?= htmlspecialchars($selectedMember['address'] ?? 'N/A'); ?></span></div>
                <div class="detail-item"><span class="detail-label">Status</span>
                    <span class="detail-value" style="color:#22c55e;"><?= ucfirst($selectedMember['status']); ?></span>
                </div>
                <div class="detail-item"><span class="detail-label">Joined</span><span class="detail-value"><?= date('M d, Y', strtotime($selectedMember['created_at'])); ?></span></div>
            </div>

            <div class="detail-section">
                <h3>📊 Borrow Summary</h3>
                <div class="detail-item"><span class="detail-label">Total Borrowed (All Time)</span><span class="detail-value"><?= $selectedMember['total_issues'] ?? 0; ?></span></div>
                <div class="detail-item"><span class="detail-label">Currently Borrowed</span>
                    <span class="detail-value" style="color:#3b82f6;"><?= $selectedMember['active_issues'] ?? 0; ?> / 3</span>
                </div>
                <div class="detail-item"><span class="detail-label">Returned Books</span>
                    <span class="detail-value" style="color:#22c55e;"><?= $selectedMember['returned_issues'] ?? 0; ?></span>
                </div>
                <div class="detail-item"><span class="detail-label">Pending Requests</span>
                    <span class="detail-value" style="color:<?= $pendingForMember > 0 ? '#eab308' : 'inherit'; ?>">
                        <?= $pendingForMember; ?>
                    </span>
                </div>
                <div class="detail-item"><span class="detail-label">Unpaid Fines</span>
                    <span class="detail-value" style="color:<?= ($selectedMember['unpaid_fines'] > 0) ? '#ef4444' : '#22c55e'; ?>">
                        NPR <?= number_format($selectedMember['unpaid_fines'] ?? 0, 2); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Tabs: Borrow Requests | Issue History -->
        <div class="modal-tabs">
            <button class="modal-tab active" onclick="switchModalTab('requests', this)">
                📬 Borrow Requests
                <?php if ($pendingForMember > 0): ?>
                    <span class="tab-badge"><?= $pendingForMember; ?></span>
                <?php endif; ?>
            </button>
            <button class="modal-tab" onclick="switchModalTab('history', this)">
                📋 Issue History
            </button>
        </div>

        <!-- TAB: Borrow Requests -->
        <div id="tab-requests" class="modal-tab-content active">
            <?php if (!empty($borrowRequests)): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Book Title</th>
                            <th>Author</th>
                            <th>Copies Available</th>
                            <th>Requested On</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th>Reviewed By</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($borrowRequests as $i => $req): ?>
                        <tr>
                            <td><?= $i + 1; ?></td>
                            <td><strong><?= htmlspecialchars($req['title']); ?></strong></td>
                            <td><?= htmlspecialchars($req['author']); ?></td>
                            <td>
                                <?php if ($req['available_copies'] > 0): ?>
                                    <span style="color:#22c55e;">✓ <?= $req['available_copies']; ?></span>
                                <?php else: ?>
                                    <span style="color:#ef4444;">✕ None</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, Y H:i', strtotime($req['request_date'])); ?></td>
                            <td><span class="req-status <?= $req['status']; ?>"><?= ucfirst($req['status']); ?></span></td>
                            <td style="max-width:150px;word-break:break-word;font-size:12px;color:var(--text-secondary);">
                                <?= htmlspecialchars($req['notes'] ?: '—'); ?>
                            </td>
                            <td style="font-size:12px;">
                                <?php if ($req['reviewed_by_name']): ?>
                                    <?= htmlspecialchars($req['reviewed_by_name']); ?>
                                    <?php if ($req['reviewed_at']): ?>
                                        <br><span style="color:var(--text-muted);"><?= date('M d', strtotime($req['reviewed_at'])); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($req['status'] === 'pending'): ?>
                                    <a href="?view=<?= $selectedMember['id']; ?>&approve_req=<?= $req['request_id']; ?>"
                                       class="tbl-btn approve"
                                       onclick="return confirm('Approve this borrow request?')">✅ Approve</a>
                                    <a href="?view=<?= $selectedMember['id']; ?>&reject_req=<?= $req['request_id']; ?>"
                                       class="tbl-btn reject"
                                       onclick="return confirm('Reject this borrow request?')">✕ Reject</a>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="no-data">📬 No borrow requests from this member yet.</div>
            <?php endif; ?>
        </div>

        <!-- TAB: Issue History -->
        <div id="tab-history" class="modal-tab-content">
            <?php if (!empty($memberIssues)): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Book</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                            <th>Fine</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($memberIssues as $i => $issue): ?>
                            <?php
                                if ($issue['return_date']) {
                                    $status = 'Returned'; $sc = 'returned';
                                } elseif ($issue['due_date'] < date('Y-m-d')) {
                                    $status = 'Overdue';  $sc = 'overdue';
                                } else {
                                    $status = 'Active';   $sc = 'issued';
                                }
                            ?>
                            <tr>
                                <td><?= $i + 1; ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($issue['title']); ?></strong><br>
                                    <span style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($issue['author']); ?></span>
                                </td>
                                <td><?= date('M d, Y', strtotime($issue['issue_date'])); ?></td>
                                <td><?= date('M d, Y', strtotime($issue['due_date'])); ?></td>
                                <td>
                                    <?= $issue['return_date']
                                        ? date('M d, Y', strtotime($issue['return_date']))
                                        : '<span style="color:var(--text-muted);">—</span>'; ?>
                                </td>
                                <td><span class="status-badge <?= $sc; ?>"><?= $status; ?></span></td>
                                <td>
                                    <?php if ($issue['fine_amount'] > 0): ?>
                                        <span style="color:#ef4444;">NPR <?= number_format($issue['fine_amount'], 2); ?></span>
                                        <?php if ($issue['fine_paid']): ?>
                                            <br><span style="color:#22c55e;font-size:11px;">✓ Paid</span>
                                        <?php else: ?>
                                            <br><span style="color:#eab308;font-size:11px;">⚠️ Unpaid</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="no-data">📋 No issue history for this member.</div>
            <?php endif; ?>
        </div>

    </div>
</div>
<?php endif; ?>

<script>
function switchModalTab(name, btn) {
    document.querySelectorAll('.modal-tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}
</script>
<script src="../js/mobile_menu.js"></script>
</body>
</html>