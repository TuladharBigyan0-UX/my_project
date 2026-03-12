<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("../php/connection.php");

// Admin protection
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../php/login.php");
    exit();
}

$user = $_SESSION['user'];

// =======================
// APPROVE / REJECT MEMBER
// =======================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE users SET status='approved' WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }

    header("Location: manage_member.php" . (isset($_GET['filter']) ? '?filter=' . $_GET['filter'] : ''));
    exit();
}

// =======================
// FETCH MEMBERS
// =======================
$filter = $_GET['filter'] ?? '';

if ($filter === 'pending') {
    $result = $conn->query("SELECT * FROM users WHERE role='member' AND status='pending' ORDER BY id DESC");
} else {
    $result = $conn->query("SELECT * FROM users WHERE role='member' ORDER BY id DESC");
}

$pendingCount = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='member' AND status='pending'")->fetch_assoc()['c'] ?? 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Members</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <style>
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        /* Filter buttons */
        .filter-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .filter-btn {
            padding: 9px 18px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            color: var(--text-primary);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .filter-btn:hover, .filter-btn.active {
            background: var(--green);
            color: #000;
            border-color: var(--green);
        }

        .badge {
            background: #ef4444;
            color: #fff;
            border-radius: 10px;
            font-size: 11px;
            padding: 1px 7px;
            font-weight: 700;
        }

        /* Status pill */
        .status-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pill.approved { background: rgba(34,197,94,0.15); color: #22c55e; }
        .status-pill.pending  { background: rgba(234,179,8,0.15);  color: #eab308; }

        /* Action buttons */
        .action-btn {
            padding: 7px 13px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .action-btn.approve { background: rgba(34,197,94,0.15); color: #22c55e; }
        .action-btn.approve:hover { background: #22c55e; color: #000; }
        .action-btn.reject  { background: rgba(239,68,68,0.15);  color: #ef4444; }
        .action-btn.reject:hover  { background: #ef4444; color: #fff; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        /* ── Desktop table ── */
        .table-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
        }

        .member-table {
            width: 100%;
            border-collapse: collapse;
            background: transparent;
            color: var(--text-primary);
        }

        .member-table th {
            background: var(--border-color);
            color: var(--text-secondary);
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid #3a3f4e;
            white-space: nowrap;
        }

        .member-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            color: var(--text-primary);
            vertical-align: middle;
        }

        .member-table tbody tr:last-child td { border-bottom: none; }
        .member-table tbody tr:hover { background: rgba(255,255,255,0.03); }
        .member-table tbody tr.pending-row { background: rgba(234,179,8,0.04); }

        .action-cell { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }

        /* ── Mobile cards (hidden on desktop) ── */
        .mobile-cards { display: none; }

        @media (max-width: 768px) {
            /* Hide table, show cards */
            .table-card   { display: none; }
            .mobile-cards { display: flex; flex-direction: column; gap: 12px; }

            .member-card {
                background: var(--card-bg);
                border: 1px solid var(--border-color);
                border-radius: 12px;
                padding: 16px;
            }

            .member-card.pending-card {
                border-color: rgba(234,179,8,0.45);
                background: rgba(234,179,8,0.04);
            }

            /* Header row: avatar + name + sn */
            .mc-header {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 12px;
                padding-bottom: 12px;
                border-bottom: 1px solid var(--border-color);
            }

            .mc-avatar {
                width: 42px;
                height: 42px;
                border-radius: 50%;
                background: linear-gradient(135deg, var(--green), #06c456);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
                font-weight: 700;
                color: #000;
                flex-shrink: 0;
            }

            .mc-name {
                flex: 1;
                font-size: 15px;
                font-weight: 600;
                color: var(--text-primary);
            }

            .mc-sn {
                font-size: 11px;
                color: var(--text-muted);
                background: rgba(255,255,255,0.05);
                padding: 3px 8px;
                border-radius: 10px;
                flex-shrink: 0;
            }

            /* Detail rows */
            .mc-row {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                padding: 5px 0;
                gap: 8px;
                font-size: 13px;
            }

            .mc-label {
                color: var(--text-secondary);
                font-size: 12px;
                flex-shrink: 0;
                padding-top: 1px;
            }

            .mc-value {
                color: var(--text-primary);
                text-align: right;
                word-break: break-all;
            }

            /* Footer: status + actions */
            .mc-footer {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-top: 12px;
                padding-top: 12px;
                border-top: 1px solid var(--border-color);
                gap: 8px;
                flex-wrap: wrap;
            }

            .mc-actions { display: flex; gap: 8px; flex-wrap: wrap; }

            .action-btn { padding: 8px 16px; font-size: 13px; }
        }

        @media (max-width: 420px) {
            .mc-footer { flex-direction: column; align-items: flex-start; }
            .mc-actions { width: 100%; }
            .mc-actions .action-btn { flex: 1; text-align: center; }
        }
    </style>
</head>
<body>

<div class="dashboard">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="profile-box">
            <h3><?= htmlspecialchars($user['fullname']); ?></h3>
            <p>Admin</p>
        </div>
        <ul class="menu">
            <li><a href="../dashboard/admin_dashboard.php">Dashboard</a></li>
            <li><a href="manage_librarian.php">Manage Librarians</a></li>
            <li><a href="manage_member.php" class="active">Manage Members</a></li>
            <li><a href="view_reports.php">View Reports</a></li>
            <li><a href="../php/view_members.php">View Members</a></li>
            <li><a href="../librarian/book_list.php">Manage Books</a></li>
            <li><a href="../php/issue_books.php">Issue Books</a></li>
            <li><a href="../php/return_books.php">Return Books</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li class="logout"><a href="../php/logout.php">Logout</a></li>
        </ul>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="content">
        <div class="top-bar">
            <h1>Manage Members</h1>
        </div>

        <div class="filter-buttons">
            <a href="manage_member.php" class="filter-btn <?= $filter === '' ? 'active' : ''; ?>">
                All Members
            </a>
            <a href="manage_member.php?filter=pending" class="filter-btn <?= $filter === 'pending' ? 'active' : ''; ?>">
                Pending <?php if ($pendingCount > 0): ?><span class="badge"><?= $pendingCount; ?></span><?php endif; ?>
            </a>
        </div>

        <?php
        $members = [];
        if ($result) { while ($row = $result->fetch_assoc()) $members[] = $row; }
        ?>

        <!-- ===== DESKTOP TABLE ===== -->
        <div class="table-card">
            <div class="table-wrapper">
                <table class="member-table">
                    <thead>
                        <tr>
                            <th>SN</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($members) > 0): ?>
                            <?php foreach ($members as $i => $row): ?>
                            <tr class="<?= $row['status'] === 'pending' ? 'pending-row' : ''; ?>">
                                <td><?= $i + 1; ?></td>
                                <td><?= htmlspecialchars($row['fullname']); ?></td>
                                <td><?= htmlspecialchars($row['email']); ?></td>
                                <td><?= htmlspecialchars($row['contact']); ?></td>
                                <td>
                                    <span class="status-pill <?= $row['status'] === 'approved' ? 'approved' : 'pending'; ?>">
                                        <?= $row['status'] === 'approved' ? 'Approved' : 'Pending'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['status'] !== 'approved'): ?>
                                        <div class="action-cell">
                                            <a href="?action=approve&id=<?= $row['id']; ?><?= $filter ? '&filter='.$filter : ''; ?>"
                                               class="action-btn approve"
                                               onclick="return confirm('Approve this member?')">✅ Approve</a>
                                            <a href="?action=reject&id=<?= $row['id']; ?><?= $filter ? '&filter='.$filter : ''; ?>"
                                               class="action-btn reject"
                                               onclick="return confirm('Reject and delete this member?')">❌ Reject</a>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:#22c55e;font-size:18px;">✅</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6"><div class="empty-state"><?= $filter === 'pending' ? 'No pending members.' : 'No members found.'; ?></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== MOBILE CARDS ===== -->
        <div class="mobile-cards">
            <?php if (count($members) > 0): ?>
                <?php foreach ($members as $i => $row): ?>
                <div class="member-card <?= $row['status'] === 'pending' ? 'pending-card' : ''; ?>">

                    <div class="mc-header">
                        <div class="mc-avatar"><?= strtoupper(substr($row['fullname'], 0, 1)); ?></div>
                        <div class="mc-name"><?= htmlspecialchars($row['fullname']); ?></div>
                        <div class="mc-sn">#<?= $i + 1; ?></div>
                    </div>

                    <div class="mc-row">
                        <span class="mc-label">Email</span>
                        <span class="mc-value"><?= htmlspecialchars($row['email']); ?></span>
                    </div>
                    <div class="mc-row">
                        <span class="mc-label">Phone</span>
                        <span class="mc-value"><?= htmlspecialchars($row['contact'] ?: '—'); ?></span>
                    </div>

                    <div class="mc-footer">
                        <span class="status-pill <?= $row['status'] === 'approved' ? 'approved' : 'pending'; ?>">
                            <?= $row['status'] === 'approved' ? '✅ Approved' : '⏳ Pending'; ?>
                        </span>

                        <?php if ($row['status'] !== 'approved'): ?>
                        <div class="mc-actions">
                            <a href="?action=approve&id=<?= $row['id']; ?><?= $filter ? '&filter='.$filter : ''; ?>"
                               class="action-btn approve"
                               onclick="return confirm('Approve this member?')">✅ Approve</a>
                            <a href="?action=reject&id=<?= $row['id']; ?><?= $filter ? '&filter='.$filter : ''; ?>"
                               class="action-btn reject"
                               onclick="return confirm('Reject this member?')">❌ Reject</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="member-card">
                    <div class="empty-state"><?= $filter === 'pending' ? 'No pending members.' : 'No members found.'; ?></div>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<script src="../js/mobile_menu.js"></script>
</body>
</html>