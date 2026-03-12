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

// Count pending
$pendingCount = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='member' AND status='pending'")->fetch_assoc()['c'] ?? 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Members</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <style>
        /* ── Top bar ── */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        /* ── Filter buttons ── */
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

        .filter-btn:hover,
        .filter-btn.active {
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

        /* ── Table card ── */
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

        .member-table tbody tr:last-child td {
            border-bottom: none;
        }

        .member-table tbody tr:hover {
            background: rgba(255,255,255,0.03);
        }

        .member-table tbody tr.pending-row {
            background: rgba(234,179,8,0.05);
        }

        /* ── Status badge ── */
        .status-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pill.approved {
            background: rgba(34,197,94,0.15);
            color: #22c55e;
        }

        .status-pill.pending {
            background: rgba(234,179,8,0.15);
            color: #eab308;
        }

        /* ── Action buttons ── */
        .action-cell {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

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

        .action-btn.approve {
            background: rgba(34,197,94,0.15);
            color: #22c55e;
        }

        .action-btn.approve:hover {
            background: #22c55e;
            color: #000;
        }

        .action-btn.reject {
            background: rgba(239,68,68,0.15);
            color: #ef4444;
        }

        .action-btn.reject:hover {
            background: #ef4444;
            color: #fff;
        }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        /* ── Card layout for very small screens ── */
        @media (max-width: 640px) {
            .member-table thead {
                display: none;
            }

            .member-table,
            .member-table tbody,
            .member-table tr,
            .member-table td {
                display: block;
                width: 100%;
            }

            .member-table tr {
                border-bottom: 2px solid var(--border-color);
                padding: 10px 0;
            }

            .member-table tr:last-child {
                border-bottom: none;
            }

            .member-table td {
                border-bottom: none;
                padding: 5px 16px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 8px;
                font-size: 13px;
            }

            .member-table td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--text-secondary);
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                flex-shrink: 0;
                min-width: 70px;
            }

            .action-cell {
                justify-content: flex-end;
            }
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

        <!-- Filter Buttons -->
        <div class="filter-buttons">
            <a href="manage_member.php" class="filter-btn <?= $filter === '' ? 'active' : ''; ?>">
                All Members
            </a>
            <a href="manage_member.php?filter=pending" class="filter-btn <?= $filter === 'pending' ? 'active' : ''; ?>">
                Pending
                <?php if ($pendingCount > 0): ?>
                    <span class="badge"><?= $pendingCount; ?></span>
                <?php endif; ?>
            </a>
        </div>

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
                        <?php if ($result->num_rows > 0): ?>
                            <?php $sn = 1; ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="<?= $row['status'] === 'pending' ? 'pending-row' : ''; ?>">
                                <td data-label="SN"><?= $sn++; ?></td>
                                <td data-label="Name"><?= htmlspecialchars($row['fullname']); ?></td>
                                <td data-label="Email"><?= htmlspecialchars($row['email']); ?></td>
                                <td data-label="Phone"><?= htmlspecialchars($row['contact']); ?></td>
                                <td data-label="Status">
                                    <span class="status-pill <?= $row['status'] === 'approved' ? 'approved' : 'pending'; ?>">
                                        <?= $row['status'] === 'approved' ? 'Approved' : 'Pending'; ?>
                                    </span>
                                </td>
                                <td data-label="Action">
                                    <?php if ($row['status'] !== 'approved'): ?>
                                        <div class="action-cell">
                                            <a href="?action=approve&id=<?= $row['id']; ?><?= $filter ? '&filter=' . $filter : ''; ?>"
                                               class="action-btn approve"
                                               onclick="return confirm('Approve this member?')">
                                               ✅ Approve
                                            </a>
                                            <a href="?action=reject&id=<?= $row['id']; ?><?= $filter ? '&filter=' . $filter : ''; ?>"
                                               class="action-btn reject"
                                               onclick="return confirm('Reject and delete this member?')">
                                               ❌ Reject
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:#22c55e;font-size:18px;">✅</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <?= $filter === 'pending' ? 'No pending members.' : 'No members found.'; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

</div>

<script src="../js/mobile_menu.js"></script>
</body>
</html>