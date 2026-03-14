<?php
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'librarian' && $_SESSION['user']['role'] !== 'admin')) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];

// Include database connection
include("connection.php");

$errors = [];
$success = '';

// Default loan period (14 days)
$defaultLoanDays = 14;

// ====================================
// PROCESS BOOK ISSUE
// ====================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_book'])) {
    $bookId = (int)$_POST['book_id'];
    $memberId = (int)$_POST['member_id'];
    $loanDays = (int)$_POST['loan_days'];
    
    // Validate
    if ($bookId <= 0) {
        $errors[] = "Please select a book.";
    }
    
    if ($memberId <= 0) {
        $errors[] = "Please select a member.";
    }
    
    if ($loanDays < 1 || $loanDays > 90) {
        $errors[] = "Loan period must be between 1 and 90 days.";
    }
    
    if (empty($errors)) {
        // Check book availability
        $stmt = $conn->prepare("SELECT title, available_copies FROM books WHERE book_id = ?");
        $stmt->bind_param("i", $bookId);
        $stmt->execute();
        $book = $stmt->get_result()->fetch_assoc();
        
        if (!$book) {
            $errors[] = "Book not found.";
        } elseif ($book['available_copies'] <= 0) {
            $errors[] = "Book is not available. All copies are currently issued.";
        } else {
            // Check if member has overdue books
            $stmt = $conn->prepare("
                SELECT COUNT(*) as overdue_count 
                FROM issues 
                WHERE user_id = ? AND return_date IS NULL AND due_date < CURDATE()
            ");
            $stmt->bind_param("i", $memberId);
            $stmt->execute();
            $overdueCheck = $stmt->get_result()->fetch_assoc();
            
            if ($overdueCheck['overdue_count'] > 0) {
                $errors[] = "Member has overdue books. Please return them before issuing new books.";
            } else {
                // Check if member has unpaid fines
                $stmt = $conn->prepare("
                    SELECT SUM(fine_amount) as unpaid_fines 
                    FROM issues 
                    WHERE user_id = ? AND fine_amount > 0 AND fine_paid = 0
                ");
                $stmt->bind_param("i", $memberId);
                $stmt->execute();
                $fineCheck = $stmt->get_result()->fetch_assoc();
                
                if ($fineCheck['unpaid_fines'] > 0) {
                    $errors[] = "Member has unpaid fines (NPR " . number_format($fineCheck['unpaid_fines'], 2) . "). Please clear fines first.";
                } else {
                    // Check issue limit (max 3 active books per member)
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) as active_issues 
                        FROM issues 
                        WHERE user_id = ? AND return_date IS NULL
                    ");
                    $stmt->bind_param("i", $memberId);
                    $stmt->execute();
                    $limitCheck = $stmt->get_result()->fetch_assoc();
                    
                    if ($limitCheck['active_issues'] >= 3) {
                        $errors[] = "Member has reached maximum issue limit (3 books). Please return a book first.";
                    } else {
                        // Issue the book
                        $issueDate = date('Y-m-d');
                        $dueDate = date('Y-m-d', strtotime("+$loanDays days"));
                        $status = 'issued';
                        
                        $stmt = $conn->prepare("
                            INSERT INTO issues (book_id, user_id, issue_date, due_date, status, issued_by)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->bind_param("iisssi", $bookId, $memberId, $issueDate, $dueDate, $status, $user['id']);
                        
                        if ($stmt->execute()) {
                            // Decrease available copies
                            $newAvailable = $book['available_copies'] - 1;
                            $stmt = $conn->prepare("UPDATE books SET available_copies = ? WHERE book_id = ?");
                            $stmt->bind_param("ii", $newAvailable, $bookId);
                            $stmt->execute();
                            
                            $success = "Book issued successfully! Due date: " . date('M d, Y', strtotime($dueDate));
                        } else {
                            $errors[] = "Failed to issue book. Please try again.";
                        }
                    }
                }
            }
        }
    }
}

// ====================================
// FETCH AVAILABLE BOOKS
// ====================================
$booksResult = $conn->query("
    SELECT book_id, title, author, category, available_copies 
    FROM books 
    WHERE available_copies > 0 
    ORDER BY title ASC
");

// ====================================
// FETCH ACTIVE MEMBERS
// ====================================
$membersResult = $conn->query("
    SELECT id, fullname, email, contact 
    FROM users 
    WHERE role='member' AND status='approved' 
    ORDER BY fullname ASC
");

// ====================================
// FETCH RECENT ISSUES
// ====================================
$recentIssuesQuery = "
    SELECT i.issue_id, i.issue_date, i.due_date,
           b.title as book_title, b.author,
           u.fullname as member_name, u.email as member_email
    FROM issues i
    JOIN books b ON i.book_id = b.book_id
    JOIN users u ON i.user_id = u.id
    WHERE i.return_date IS NULL
    ORDER BY i.issue_date DESC
    LIMIT 10
";
$recentIssues = $conn->query($recentIssuesQuery);

// Get statistics
$totalIssued = $conn->query("SELECT COUNT(*) as count FROM issues WHERE return_date IS NULL")->fetch_assoc()['count'] ?? 0;
$issuedToday = $conn->query("SELECT COUNT(*) as count FROM issues WHERE DATE(issue_date) = CURDATE()")->fetch_assoc()['count'] ?? 0;
$availableBooks = $conn->query("SELECT COUNT(*) as count FROM books WHERE available_copies > 0")->fetch_assoc()['count'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="w<?php
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'librarian' && $_SESSION['user']['role'] !== 'admin')) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
include("connection.php");

$errors  = [];
$success = '';

$defaultLoanDays = 14;

// ====================================
// PROCESS BOOK ISSUE
// ====================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_book'])) {
    $bookId   = (int)$_POST['book_id'];
    $memberId = (int)$_POST['member_id'];
    $loanDays = (int)$_POST['loan_days'];

    if ($bookId   <= 0) $errors[] = "Please select a book.";
    if ($memberId <= 0) $errors[] = "Please select a member.";
    if ($loanDays < 1 || $loanDays > 90) $errors[] = "Loan period must be between 1 and 90 days.";

    if (empty($errors)) {
        // Check book availability
        $stmt = $conn->prepare("SELECT title, available_copies FROM books WHERE book_id = ?");
        $stmt->bind_param("i", $bookId);
        $stmt->execute();
        $book = $stmt->get_result()->fetch_assoc();

        if (!$book) {
            $errors[] = "Book not found.";
        } elseif ($book['available_copies'] <= 0) {
            $errors[] = "Book is not available. All copies are currently issued.";
        } else {
            // Check overdue books
            $stmt = $conn->prepare("SELECT COUNT(*) as c FROM issues
                                    WHERE user_id = ? AND return_date IS NULL AND due_date < CURDATE()");
            $stmt->bind_param("i", $memberId);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()['c'] > 0) {
                $errors[] = "Member has overdue books. Please return them before issuing new books.";
            } else {
                // Check unpaid fines
                $stmt = $conn->prepare("SELECT SUM(fine_amount) as total FROM issues
                                        WHERE user_id = ? AND fine_amount > 0 AND fine_paid = 0");
                $stmt->bind_param("i", $memberId);
                $stmt->execute();
                $unpaid = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

                if ($unpaid > 0) {
                    $errors[] = "Member has unpaid fines (NPR " . number_format($unpaid, 2) . "). Please clear fines first.";
                } else {
                    // Check issue limit (max 3)
                    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM issues
                                            WHERE user_id = ? AND return_date IS NULL");
                    $stmt->bind_param("i", $memberId);
                    $stmt->execute();
                    if ($stmt->get_result()->fetch_assoc()['c'] >= 3) {
                        $errors[] = "Member has reached maximum issue limit (3 books). Please return a book first.";
                    } else {
                        // ── Issue the book ──
                        $issueDate = date('Y-m-d');
                        $dueDate   = date('Y-m-d', strtotime("+$loanDays days"));
                        $status    = 'issued';

                        $stmt = $conn->prepare("INSERT INTO issues
                                                (book_id, user_id, issue_date, due_date, status, issued_by)
                                                VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("iisssi", $bookId, $memberId, $issueDate, $dueDate, $status, $user['id']);

                        if ($stmt->execute()) {
                            // Decrease available copies
                            $newAvailable = $book['available_copies'] - 1;
                            $stmt = $conn->prepare("UPDATE books SET available_copies = ? WHERE book_id = ?");
                            $stmt->bind_param("ii", $newAvailable, $bookId);
                            $stmt->execute();

                            // ── KEY FIX: close any pending/approved borrow request
                            //    for this member + book so it no longer appears as open ──
                            $chkReq = $conn->query("SHOW TABLES LIKE 'borrow_requests'");
                            if ($chkReq && $chkReq->num_rows > 0) {
                                $stmt = $conn->prepare("UPDATE borrow_requests
                                                        SET status = 'fulfilled'
                                                        WHERE user_id = ? AND book_id = ?
                                                          AND status IN ('pending','approved')");
                                $stmt->bind_param("ii", $memberId, $bookId);
                                $stmt->execute();
                            }

                            $success = "Book issued successfully! Due date: " . date('M d, Y', strtotime($dueDate));
                        } else {
                            $errors[] = "Failed to issue book. Please try again.";
                        }
                    }
                }
            }
        }
    }
}

// ====================================
// FETCH AVAILABLE BOOKS
// ====================================
$booksResult = $conn->query("
    SELECT book_id, title, author, category, available_copies
    FROM books
    WHERE available_copies > 0
    ORDER BY title ASC
");

// ====================================
// FETCH ACTIVE MEMBERS
// ====================================
$membersResult = $conn->query("
    SELECT id, fullname, email, contact
    FROM users
    WHERE role='member' AND status='approved'
    ORDER BY fullname ASC
");

// ====================================
// FETCH RECENT ISSUES
// ====================================
$recentIssues = $conn->query("
    SELECT i.issue_id, i.issue_date, i.due_date,
           b.title as book_title, b.author,
           u.fullname as member_name, u.email as member_email
    FROM issues i
    JOIN books b ON i.book_id = b.book_id
    JOIN users u ON i.user_id = u.id
    WHERE i.return_date IS NULL
    ORDER BY i.issue_date DESC
    LIMIT 10
");

// Stats
$totalIssued    = $conn->query("SELECT COUNT(*) as c FROM issues WHERE return_date IS NULL")->fetch_assoc()['c'] ?? 0;
$issuedToday    = $conn->query("SELECT COUNT(*) as c FROM issues WHERE DATE(issue_date) = CURDATE()")->fetch_assoc()['c'] ?? 0;
$availableBooks = $conn->query("SELECT COUNT(*) as c FROM books WHERE available_copies > 0")->fetch_assoc()['c'] ?? 0;

// Pending borrow requests count
$pendingRequests = 0;
$chkReq = $conn->query("SHOW TABLES LIKE 'borrow_requests'");
if ($chkReq && $chkReq->num_rows > 0) {
    $pendingRequests = $conn->query("SELECT COUNT(*) as c FROM borrow_requests WHERE status='approved'")->fetch_assoc()['c'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Books</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <style>
        .issue-container {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 25px;
            margin-top: 30px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }

        .stat-box h3 { font-size: 13px; color: var(--text-secondary); margin-bottom: 10px; }
        .stat-box .number { font-size: 28px; font-weight: 700; color: var(--text-primary); }

        .issue-form-card, .recent-issues-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px;
        }

        .card-header { margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid var(--border-color); }
        .card-header h2 { font-size: 20px; color: var(--text-primary); margin-bottom: 5px; }
        .card-header p  { color: var(--text-secondary); font-size: 13px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; color: var(--text-secondary); font-weight: 500; margin-bottom: 8px; }
        .form-group label .required { color: #ef4444; }
        .form-group input, .form-group select {
            width: 100%; padding: 12px 14px;
            background: rgba(255,255,255,.05);
            border: 1px solid var(--border-color);
            border-radius: 8px; color: var(--text-primary); font-size: 14px;
            transition: all .3s;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none; border-color: var(--green);
            background: rgba(255,255,255,.08);
        }
        .form-group select option { background: #1e2433; color: var(--text-primary); }
        .form-group small { display: block; margin-top: 5px; color: var(--text-muted); font-size: 12px; }

        .btn-issue {
            width: 100%; padding: 14px;
            background: var(--green); color: #000;
            border: none; border-radius: 8px;
            font-weight: 600; font-size: 15px; cursor: pointer;
            transition: all .3s; margin-top: 10px;
        }
        .btn-issue:hover { background: var(--green-hover); color: #fff; transform: translateY(-2px); }

        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: rgba(34,197,94,.15);  color: #22c55e; border: 1px solid rgba(34,197,94,.3); }
        .alert-error   { background: rgba(239,68,68,.15);  color: #ef4444; border: 1px solid rgba(239,68,68,.3); }

        /* Approved requests banner */
        .approved-banner {
            background: rgba(34,197,94,.1);
            border: 1px solid rgba(34,197,94,.35);
            border-radius: 10px;
            padding: 13px 18px;
            margin-bottom: 22px;
            font-size: 14px;
            color: #22c55e;
            display: flex; align-items: center; gap: 10px;
        }
        .approved-banner strong { color: #fff; }

        .issue-item {
            padding: 15px; background: rgba(255,255,255,.02);
            border: 1px solid var(--border-color); border-radius: 8px;
            margin-bottom: 12px; transition: all .3s;
        }
        .issue-item:hover { background: rgba(255,255,255,.04); border-color: #3a3f4e; }
        .issue-item-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px; }
        .issue-item-title  { font-weight: 600; color: var(--text-primary); font-size: 14px; }
        .issue-item-date   { font-size: 12px; color: var(--text-muted); }
        .issue-item-details { font-size: 13px; color: var(--text-secondary); line-height: 1.6; }

        .due-badge {
            display: inline-block; padding: 3px 10px; border-radius: 12px;
            font-size: 11px; font-weight: 600; margin-top: 5px;
        }
        .due-badge.upcoming { background: rgba(59,130,246,.15); color: #3b82f6; }
        .due-badge.soon     { background: rgba(234,179,8,.15);  color: #eab308; }

        .no-issues { text-align: center; padding: 40px; color: var(--text-muted); }

        .loan-presets { display: flex; gap: 8px; margin-top: 8px; }
        .preset-btn {
            padding: 6px 12px;
            background: rgba(10,224,100,.1);
            border: 1px solid rgba(10,224,100,.3);
            border-radius: 6px; font-size: 12px;
            color: var(--green); cursor: pointer; transition: all .3s;
        }
        .preset-btn:hover { background: var(--green); color: #000; }

        .member-info {
            padding: 12px;
            background: rgba(59,130,246,.1);
            border: 1px solid rgba(59,130,246,.3);
            border-radius: 8px; margin-top: 10px;
            font-size: 13px; color: #3b82f6;
        }

        @media (max-width: 968px) { .issue-container { grid-template-columns: 1fr; } }
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
                <li><a href="issue_books.php" class="active">Issue Books</a></li>
                <li><a href="return_books.php">Return Books</a></li>
                <li><a href="view_members.php">View Members</a></li>
                <li><a href="../librarian/profile_librarian.php">Profile</a></li>
            <?php else: ?>
                <li><a href="../dashboard/admin_dashboard.php">Dashboard</a></li>
                <li><a href="../admin/manage_librarian.php">Manage Librarians</a></li>
                <li><a href="../admin/manage_member.php">Manage Members</a></li>
                <li><a href="../admin/view_reports.php">View Reports</a></li>
                <li><a href="view_members.php">View Members</a></li>
                <li><a href="../librarian/book_list.php">Manage Books</a></li>
                <li><a href="issue_books.php" class="active">Issue Books</a></li>
                <li><a href="return_books.php">Return Books</a></li>
                <li><a href="../admin/profile.php">Profile</a></li>
            <?php endif; ?>
            <li class="logout"><a href="../php/logout.php">Logout</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="content">
        <h1>Issue Books 📖</h1>
        <p>Issue books to library members</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $e): ?><p><?= htmlspecialchars($e); ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Approved requests reminder -->
        <?php if ($pendingRequests > 0): ?>
        <div class="approved-banner">
            📋 <span><strong><?= $pendingRequests; ?></strong> borrow
            <?= $pendingRequests === 1 ? 'request has' : 'requests have'; ?> been approved —
            members are waiting to collect their books. Issue the book below to complete the process.</span>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-box">
                <h3>Active Issues</h3>
                <div class="number"><?= $totalIssued; ?></div>
            </div>
            <div class="stat-box">
                <h3>Issued Today</h3>
                <div class="number"><?= $issuedToday; ?></div>
            </div>
            <div class="stat-box">
                <h3>Available Books</h3>
                <div class="number"><?= $availableBooks; ?></div>
            </div>
            <div class="stat-box">
                <h3>Approved Requests</h3>
                <div class="number" style="color:<?= $pendingRequests > 0 ? '#22c55e' : 'inherit'; ?>">
                    <?= $pendingRequests; ?>
                </div>
            </div>
        </div>

        <!-- Issue Form + Recent Issues -->
        <div class="issue-container">

            <!-- Issue Form -->
            <div class="issue-form-card">
                <div class="card-header">
                    <h2>Issue New Book</h2>
                    <p>Fill in the details to issue a book to a member</p>
                </div>

                <form method="POST" id="issueForm">
                    <div class="form-group">
                        <label>Select Book <span class="required">*</span></label>
                        <select name="book_id" id="bookSelect" required onchange="updateBookInfo()">
                            <option value="">-- Choose a book --</option>
                            <?php while ($book = $booksResult->fetch_assoc()): ?>
                                <option value="<?= $book['book_id']; ?>"
                                        data-copies="<?= $book['available_copies']; ?>"
                                        data-author="<?= htmlspecialchars($book['author']); ?>">
                                    <?= htmlspecialchars($book['title']); ?> — <?= htmlspecialchars($book['author']); ?>
                                    (<?= $book['available_copies']; ?> available)
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div id="bookInfo"></div>
                    </div>

                    <div class="form-group">
                        <label>Select Member <span class="required">*</span></label>
                        <select name="member_id" id="memberSelect" required onchange="updateMemberInfo()">
                            <option value="">-- Choose a member --</option>
                            <?php while ($member = $membersResult->fetch_assoc()): ?>
                                <option value="<?= $member['id']; ?>"
                                        data-email="<?= htmlspecialchars($member['email']); ?>"
                                        data-contact="<?= htmlspecialchars($member['contact']); ?>">
                                    <?= htmlspecialchars($member['fullname']); ?> — <?= htmlspecialchars($member['email']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div id="memberInfo"></div>
                    </div>

                    <div class="form-group">
                        <label>Loan Period (Days) <span class="required">*</span></label>
                        <input type="number" name="loan_days" id="loanDays"
                               value="<?= $defaultLoanDays; ?>" min="1" max="90" required
                               onchange="updateDueDate()">
                        <small>Due date: <span id="dueDate"><?= date('M d, Y', strtotime("+{$defaultLoanDays} days")); ?></span></small>
                        <div class="loan-presets">
                            <button type="button" class="preset-btn" onclick="setLoanDays(7)">7 Days</button>
                            <button type="button" class="preset-btn" onclick="setLoanDays(14)">14 Days</button>
                            <button type="button" class="preset-btn" onclick="setLoanDays(30)">30 Days</button>
                        </div>
                    </div>

                    <button type="submit" name="issue_book" class="btn-issue">📖 Issue Book</button>
                </form>
            </div>

            <!-- Recent Issues -->
            <div class="recent-issues-card">
                <div class="card-header">
                    <h2>Recent Issues</h2>
                    <p>Currently active book issues</p>
                </div>

                <?php if ($recentIssues && $recentIssues->num_rows > 0): ?>
                    <?php while ($issue = $recentIssues->fetch_assoc()): ?>
                        <?php
                            $due     = new DateTime($issue['due_date']);
                            $today   = new DateTime();
                            $diff    = (int)$today->diff($due)->days;
                            $isOver  = $due < $today;
                            $badgeClass = (!$isOver && $diff <= 3) ? 'soon' : 'upcoming';
                            $badgeText  = (!$isOver && $diff <= 3)
                                ? "Due in $diff days"
                                : "Due " . date('M d', strtotime($issue['due_date']));
                        ?>
                        <div class="issue-item">
                            <div class="issue-item-header">
                                <div class="issue-item-title"><?= htmlspecialchars($issue['book_title']); ?></div>
                                <div class="issue-item-date"><?= date('M d', strtotime($issue['issue_date'])); ?></div>
                            </div>
                            <div class="issue-item-details">
                                <div>👤 <?= htmlspecialchars($issue['member_name']); ?></div>
                                <div>✍️ <?= htmlspecialchars($issue['author']); ?></div>
                                <span class="due-badge <?= $badgeClass; ?>"><?= $badgeText; ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-issues"><p>No active issues</p></div>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>

<script>
function updateBookInfo() {
    const sel = document.getElementById('bookSelect');
    const opt = sel.options[sel.selectedIndex];
    const div = document.getElementById('bookInfo');
    if (opt.value) {
        div.innerHTML = `<div class="member-info">📚 ${opt.getAttribute('data-copies')} copies available &nbsp;·&nbsp; ✍️ ${opt.getAttribute('data-author')}</div>`;
    } else { div.innerHTML = ''; }
}
function updateMemberInfo() {
    const sel = document.getElementById('memberSelect');
    const opt = sel.options[sel.selectedIndex];
    const div = document.getElementById('memberInfo');
    if (opt.value) {
        div.innerHTML = `<div class="member-info">📧 ${opt.getAttribute('data-email')} &nbsp;·&nbsp; 📱 ${opt.getAttribute('data-contact')}</div>`;
    } else { div.innerHTML = ''; }
}
function updateDueDate() {
    const days = parseInt(document.getElementById('loanDays').value);
    const d = new Date();
    d.setDate(d.getDate() + days);
    document.getElementById('dueDate').textContent = d.toLocaleDateString('en-US', {year:'numeric',month:'short',day:'numeric'});
}
function setLoanDays(days) {
    document.getElementById('loanDays').value = days;
    updateDueDate();
}
</script>
<script src="../js/mobile_menu.js"></script>
</body>
</html>