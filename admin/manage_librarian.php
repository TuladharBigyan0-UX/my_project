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
// DELETE LIBRARIAN
// =======================
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM librarians WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: manage_librarian.php");
    exit();
}

// =======================
// FETCH LIBRARIANS
// =======================
$result = $conn->query("SELECT * FROM librarians ORDER BY id DESC");
$librarians = [];
if ($result) { while ($row = $result->fetch_assoc()) $librarians[] = $row; }
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Librarians</title>
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

        .action-btn.edit  { background: rgba(59,130,246,0.15); color: #3b82f6; }
        .action-btn.edit:hover  { background: #3b82f6; color: #fff; }
        .action-btn.delete { background: rgba(239,68,68,0.15); color: #ef4444; }
        .action-btn.delete:hover { background: #ef4444; color: #fff; }

        .action-cell { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }

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

        .librarian-table {
            width: 100%;
            border-collapse: collapse;
            background: transparent;
            color: var(--text-primary);
        }

        .librarian-table th {
            background: var(--border-color);
            color: var(--text-secondary);
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid #3a3f4e;
            white-space: nowrap;
        }

        .librarian-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            color: var(--text-primary);
            vertical-align: middle;
        }

        .librarian-table tbody tr:last-child td { border-bottom: none; }
        .librarian-table tbody tr:hover { background: rgba(255,255,255,0.03); }

        /* ── Mobile cards (hidden on desktop) ── */
        .mobile-cards { display: none; }

        @media (max-width: 768px) {
            .table-card   { display: none; }
            .mobile-cards { display: flex; flex-direction: column; gap: 12px; }

            .librarian-card {
                background: var(--card-bg);
                border: 1px solid var(--border-color);
                border-radius: 12px;
                padding: 16px;
            }

            /* Header: avatar + name + id */
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

            /* Footer: actions */
            .mc-footer {
                display: flex;
                justify-content: flex-end;
                gap: 8px;
                margin-top: 12px;
                padding-top: 12px;
                border-top: 1px solid var(--border-color);
                flex-wrap: wrap;
            }

            .action-btn { padding: 9px 18px; font-size: 13px; }
        }

        @media (max-width: 420px) {
            .mc-footer { justify-content: stretch; }
            .mc-footer .action-btn { flex: 1; text-align: center; }
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
            <li><a href="manage_librarian.php" class="active">Manage Librarians</a></li>
            <li><a href="manage_member.php">Manage Members</a></li>
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
            <h1>Manage Librarians</h1>
            <a href="add_edit_librarian.php" class="btn">➕ Add Librarian</a>
        </div>

        <!-- ===== DESKTOP TABLE ===== -->
        <div class="table-card">
            <div class="table-wrapper">
                <table class="librarian-table">
                    <thead>
                        <tr>
                            <th>SN</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($librarians) > 0): ?>
                            <?php foreach ($librarians as $i => $row): ?>
                            <tr>
                                <td><?= $i + 1; ?></td>
                                <td><?= htmlspecialchars($row['fullname']); ?></td>
                                <td><?= htmlspecialchars($row['email']); ?></td>
                                <td><?= htmlspecialchars($row['phone']); ?></td>
                                <td>
                                    <div class="action-cell">
                                        <a href="add_edit_librarian.php?id=<?= $row['id']; ?>" class="action-btn edit">✏️ Edit</a>
                                        <a href="?delete=<?= $row['id']; ?>"
                                           class="action-btn delete"
                                           onclick="return confirm('Delete this librarian?')">🗑️ Delete</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5"><div class="empty-state">No librarians found.</div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== MOBILE CARDS ===== -->
        <div class="mobile-cards">
            <?php if (count($librarians) > 0): ?>
                <?php foreach ($librarians as $i => $row): ?>
                <div class="librarian-card">

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
                        <span class="mc-value"><?= htmlspecialchars($row['phone'] ?: '—'); ?></span>
                    </div>

                    <div class="mc-footer">
                        <a href="add_edit_librarian.php?id=<?= $row['id']; ?>" class="action-btn edit">✏️ Edit</a>
                        <a href="?delete=<?= $row['id']; ?>"
                           class="action-btn delete"
                           onclick="return confirm('Delete this librarian?')">🗑️ Delete</a>
                    </div>

                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="librarian-card">
                    <div class="empty-state">No librarians found.</div>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<script src="../js/mobile_menu.js"></script>
</body>
</html>