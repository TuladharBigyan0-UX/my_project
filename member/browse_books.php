<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'member') {
    header("Location: ../php/login.php");
    exit();
}

$user = $_SESSION['user'];
include("../php/connection.php");

$userId   = $user['id'];
$errors   = [];
$success  = '';

// ====================================
// HANDLE BORROW REQUEST SUBMISSION
// ====================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_borrow'])) {
    $bookId = (int)$_POST['book_id'];
    $notes  = trim($_POST['notes'] ?? '');

    // Ensure borrow_requests table exists
    $conn->query("CREATE TABLE IF NOT EXISTS borrow_requests (
        request_id   INT AUTO_INCREMENT PRIMARY KEY,
        user_id      INT NOT NULL,
        book_id      INT NOT NULL,
        request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status       ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
        notes        TEXT NULL,
        reviewed_by  INT NULL,
        reviewed_at  TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id)  ON DELETE CASCADE,
        FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
        INDEX idx_user   (user_id),
        INDEX idx_book   (book_id),
        INDEX idx_status (status)
    )");

    // Check book exists & available
    $stmt = $conn->prepare("SELECT title, available_copies FROM books WHERE book_id = ?");
    $stmt->bind_param("i", $bookId);
    $stmt->execute();
    $book = $stmt->get_result()->fetch_assoc();

    if (!$book) {
        $errors[] = "Book not found.";
    } elseif ($book['available_copies'] <= 0) {
        $errors[] = "Sorry, this book has no available copies right now.";
    } else {
        // Block if user already has this book actively issued (not yet returned)
        $stmt = $conn->prepare("SELECT issue_id FROM issues
                                WHERE user_id = ? AND book_id = ? AND return_date IS NULL");
        $stmt->bind_param("ii", $userId, $bookId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "You already have \"{$book['title']}\" borrowed. Please return it before requesting again.";
        }
        // Block duplicate pending/approved request for same book
        elseif (($chkDup = (function() use ($conn, $userId, $bookId) {
            $s = $conn->prepare("SELECT request_id FROM borrow_requests
                                  WHERE user_id = ? AND book_id = ? AND status IN ('pending','approved')");
            $s->bind_param("ii", $userId, $bookId);
            $s->execute();
            return $s->get_result()->num_rows;
        })()) > 0) {
            $errors[] = "You already have an open request for \"{$book['title']}\".";
        } else {
            // Check active issue limit (3)
            $stmt = $conn->prepare("SELECT COUNT(*) as c FROM issues
                                    WHERE user_id = ? AND return_date IS NULL");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $activeCount = $stmt->get_result()->fetch_assoc()['c'] ?? 0;

            if ($activeCount >= 3) {
                $errors[] = "You have reached the maximum borrow limit (3 books). Please return a book first.";
            } else {
                $stmt = $conn->prepare("INSERT INTO borrow_requests (user_id, book_id, notes)
                                        VALUES (?, ?, ?)");
                $stmt->bind_param("iis", $userId, $bookId, $notes);
                if ($stmt->execute()) {
                    $success = "Borrow request for \"{$book['title']}\" submitted! A librarian will process it shortly.";
                } else {
                    $errors[] = "Failed to submit request. Please try again.";
                }
            }
        }
    }
}

// ====================================
// HANDLE CANCEL REQUEST (pending only)
// ====================================
if (isset($_GET['cancel_request'])) {
    $reqId = (int)$_GET['cancel_request'];
    $stmt  = $conn->prepare("UPDATE borrow_requests
                             SET status = 'cancelled'
                             WHERE request_id = ? AND user_id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $reqId, $userId);
    $stmt->execute();
    $success = "Borrow request cancelled.";
}

// ====================================
// HANDLE DELETE REQUEST (approved only)
// Member deletes an approved request after collecting the book
// ====================================
if (isset($_GET['delete_request'])) {
    $reqId = (int)$_GET['delete_request'];
    $stmt  = $conn->prepare("DELETE FROM borrow_requests
                             WHERE request_id = ? AND user_id = ? AND status = 'approved'");
    $stmt->bind_param("ii", $reqId, $userId);
    $stmt->execute();
    $success = "Request removed.";
}

// ====================================
// FETCH MEMBER'S PENDING REQUESTS
// ====================================
$myRequests = [];
$checkReq = $conn->query("SHOW TABLES LIKE 'borrow_requests'");
if ($checkReq && $checkReq->num_rows > 0) {
    $stmt = $conn->prepare("SELECT br.request_id, br.book_id, br.status, br.request_date,
                                   b.title, b.author
                            FROM borrow_requests br
                            JOIN books b ON br.book_id = b.book_id
                            WHERE br.user_id = ? AND br.status IN ('pending','approved','rejected')
                            ORDER BY br.request_date DESC
                            LIMIT 10");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $myRequests[] = $row;
}

// ====================================
// SEARCH & FILTER
// ====================================
$searchTerm     = $_GET['search']   ?? '';
$categoryFilter = $_GET['category'] ?? '';
$books          = [];
$categories     = [];

$catRes = $conn->query("SELECT DISTINCT category FROM books
                         WHERE category IS NOT NULL AND category != ''
                         ORDER BY category");
while ($row = $catRes->fetch_assoc()) $categories[] = $row['category'];

$query  = "SELECT * FROM books WHERE 1=1";
$params = [];
$types  = '';
if (!empty($searchTerm)) {
    $query   .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
    $p        = '%' . $searchTerm . '%';
    $params   = array_merge($params, [$p, $p, $p]);
    $types   .= 'sss';
}
if (!empty($categoryFilter)) {
    $query  .= " AND category = ?";
    $params[] = $categoryFilter;
    $types   .= 's';
}
$query .= " ORDER BY title ASC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}
while ($row = $result->fetch_assoc()) $books[] = $row;

// Booking counts per book (pending + approved requests from all users)
$bookingCounts = [];
$chkReq = $conn->query("SHOW TABLES LIKE 'borrow_requests'");
if ($chkReq && $chkReq->num_rows > 0) {
    $bcRes = $conn->query("SELECT book_id, COUNT(*) as cnt
                           FROM borrow_requests
                           WHERE status IN ('pending','approved')
                           GROUP BY book_id");
    while ($bcRow = $bcRes->fetch_assoc()) {
        $bookingCounts[$bcRow['book_id']] = (int)$bcRow['cnt'];
    }
}

// Pending request book_ids for quick lookup
$pendingBookIds = array_column(
    array_filter($myRequests, fn($r) => $r['status'] === 'pending'),
    'request_id', 'book_id'
);

// Approved request book_ids for quick lookup
$approvedBookIds = array_column(
    array_filter($myRequests, fn($r) => $r['status'] === 'approved'),
    'request_id', 'book_id'
);

// Active issues count + book_ids the user currently has borrowed
$stmt = $conn->prepare("SELECT book_id FROM issues WHERE user_id = ? AND return_date IS NULL");
$stmt->bind_param("i", $userId);
$stmt->execute();
$activeIssueRes  = $stmt->get_result();
$activeIssueBookIds = [];
while ($r = $activeIssueRes->fetch_assoc()) $activeIssueBookIds[$r['book_id']] = true;
$activeIssues  = count($activeIssueBookIds);
$canBorrowMore = $activeIssues < 3;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Books</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <style>
        /* ── Search / filter ── */
        .search-filter-section {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px 25px;
            margin-bottom: 25px;
        }
        .search-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        .search-input, .category-select {
            flex: 1;
            min-width: 200px;
            padding: 11px 14px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
        }
        .search-input:focus, .category-select:focus {
            outline: none;
            border-color: var(--green);
        }
        .category-select option { background: #1e2433; }
        .search-btn {
            padding: 11px 24px;
            background: var(--green);
            color: #000;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            text-decoration: none;
            display: inline-block;
            white-space: nowrap;
        }
        .search-btn:hover { background: var(--green-hover); color: #fff; }
        .search-btn.secondary { background: var(--border-color); color: var(--text-primary); }
        .search-btn.secondary:hover { background: #3a3f4e; }

        /* ── Banners ── */
        .info-banner, .warning-banner {
            padding: 13px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .info-banner    { background: rgba(59,130,246,.15); color: #3b82f6; border: 1px solid rgba(59,130,246,.3); }
        .warning-banner { background: rgba(239,68,68,.15);  color: #ef4444; border: 1px solid rgba(239,68,68,.3); }
        .alert-success  { background: rgba(34,197,94,.15);  color: #22c55e; border: 1px solid rgba(34,197,94,.3); border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; font-size: 14px; }
        .alert-error    { background: rgba(239,68,68,.15);  color: #ef4444; border: 1px solid rgba(239,68,68,.3);  border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; font-size: 14px; }

        /* ── My requests strip ── */
        .my-requests-section {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px 25px;
            margin-bottom: 25px;
        }
        .my-requests-section h3 {
            font-size: 16px;
            color: var(--text-primary);
            margin-bottom: 14px;
        }
        .req-list { display: flex; flex-direction: column; gap: 10px; }
        .req-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            background: rgba(255,255,255,.03);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            gap: 12px;
            flex-wrap: wrap;
        }
        .req-info { flex: 1; }
        .req-title { font-size: 14px; font-weight: 600; color: var(--text-primary); }
        .req-date  { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
        .req-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .5px;
            text-transform: uppercase;
        }
        .req-badge.pending  { background: rgba(234,179,8,.15);  color: #eab308; }
        .req-badge.approved { background: rgba(34,197,94,.15);  color: #22c55e; }
        .req-badge.rejected { background: rgba(239,68,68,.15);  color: #ef4444; }
        .btn-cancel-req {
            padding: 5px 12px;
            background: rgba(239,68,68,.15);
            color: #ef4444;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all .2s;
        }
        .btn-cancel-req:hover { background: #ef4444; color: #fff; }

        /* ── Books grid ── */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .book-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            transition: all .25s;
            display: flex;
            flex-direction: column;
        }
        .book-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(10,224,100,.12);
            border-color: var(--green);
        }
        .book-card.unavailable { opacity: .6; border-color: rgba(239,68,68,.3); }
        .book-header { display: flex; gap: 14px; margin-bottom: 14px; }
        .book-cover {
            width: 72px; height: 100px;
            background: linear-gradient(135deg, rgba(10,224,100,.2), rgba(6,196,86,.1));
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 30px; flex-shrink: 0;
        }
        .book-meta-info { flex: 1; }
        .book-title  { font-size: 15px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px; line-height: 1.4; }
        .book-author { font-size: 13px; color: var(--text-secondary); margin-bottom: 10px; }
        .meta-tags   { display: flex; flex-wrap: wrap; gap: 6px; }
        .meta-tag {
            padding: 3px 9px;
            background: rgba(10,224,100,.1);
            border: 1px solid rgba(10,224,100,.25);
            border-radius: 12px;
            font-size: 11px;
            color: var(--green);
        }
        .book-details {
            flex: 1;
            margin: 12px 0;
            padding: 12px 0;
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 13px;
        }
        .detail-label { color: var(--text-secondary); }
        .detail-value { color: var(--text-primary); font-weight: 500; }
        .book-footer {
            margin-top: 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: center;
        }
        .availability-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .availability-badge.available   { background: rgba(34,197,94,.15); color: #22c55e; }
        .availability-badge.unavailable { background: rgba(239,68,68,.15);  color: #ef4444; }

        /* Borrow button */
        .btn-borrow {
            width: 100%;
            padding: 10px;
            background: var(--green);
            color: #000;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
        }
        .btn-borrow:hover { background: var(--green-hover); color: #fff; }
        .btn-borrow:disabled {
            background: rgba(255,255,255,.08);
            color: var(--text-muted);
            cursor: not-allowed;
        }
        .btn-pending {
            width: 100%;
            padding: 10px;
            background: rgba(234,179,8,.15);
            color: #eab308;
            border: 1px solid rgba(234,179,8,.4);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: default;
            text-align: center;
        }
        .btn-approved {
            width: 100%;
            padding: 10px;
            background: rgba(34,197,94,.18);
            color: #22c55e;
            border: 1px solid rgba(34,197,94,.45);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: default;
            text-align: center;
        }
        .btn-borrowed {
            width: 100%;
            padding: 10px;
            background: rgba(59,130,246,.15);
            color: #3b82f6;
            border: 1px solid rgba(59,130,246,.35);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: default;
            text-align: center;
        }
        .booking-badge {
            display: inline-block;
            padding: 4px 10px;
            background: rgba(168,85,247,.15);
            border: 1px solid rgba(168,85,247,.3);
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            color: #a855f7;
        }
        .btn-delete-req {
            display: block;
            padding: 8px 14px;
            background: rgba(239,68,68,.15);
            color: #ef4444;
            border: 1px solid rgba(239,68,68,.35);
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all .2s;
            text-align: center;
        }
        .btn-delete-req:hover { background: #ef4444; color: #fff; }
        /* approved badge in request strip */
        .req-badge.approved { background: rgba(34,197,94,.15); color: #22c55e; }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,.7);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 30px;
            max-width: 440px;
            width: 100%;
        }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; }
        .modal-cancel  { padding: 10px 20px; background: var(--border-color); color: var(--text-primary); border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .modal-submit  { padding: 10px 22px; background: var(--green); color: #000; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all .2s; }
        .modal-submit:hover { background: var(--green-hover); color: #fff; }

        .no-books {
            text-align: center; padding: 60px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-muted);
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
            <li><a href="browse_books.php" class="active">Browse Books</a></li>
            <li><a href="issue_history.php">Issue History</a></li>
            <li><a href="profile_member.php">Profile</a></li>
            <li class="logout"><a href="../php/logout.php">Logout</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="content">
        <h1>📚 Browse Books</h1>
        <p>Explore our collection and submit borrow requests</p>

        <?php if (!empty($errors)): ?>
            <div class="alert-error">
                <?php foreach ($errors as $e): ?><p><?= htmlspecialchars($e); ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert-success"><?= htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Borrow limit banner -->
        <?php if (!$canBorrowMore): ?>
            <div class="warning-banner">
                ⚠️ You have reached your borrowing limit (<?= $activeIssues; ?>/3 books). Return a book before requesting more.
            </div>
        <?php else: ?>
            <div class="info-banner">
                ℹ️ You can request up to <?= 3 - $activeIssues; ?> more book(s). Submit a request — a librarian will process it.
            </div>
        <?php endif; ?>

        <!-- My pending / recent requests -->
        <?php if (!empty($myRequests)): ?>
        <div class="my-requests-section">
            <h3>📋 My Recent Borrow Requests</h3>
            <div class="req-list">
                <?php foreach ($myRequests as $req): ?>
                <div class="req-item">
                    <div class="req-info">
                        <div class="req-title"><?= htmlspecialchars($req['title']); ?></div>
                        <div class="req-date">by <?= htmlspecialchars($req['author']); ?> &nbsp;·&nbsp;
                            Requested <?= date('M d, Y', strtotime($req['request_date'])); ?>
                        </div>
                    </div>
                    <span class="req-badge <?= $req['status']; ?>"><?= ucfirst($req['status']); ?></span>
                    <?php if ($req['status'] === 'pending'): ?>
                        <a href="?cancel_request=<?= $req['request_id']; ?>"
                           class="btn-cancel-req"
                           onclick="return confirm('Cancel this request?')">✕ Cancel</a>
                    <?php elseif ($req['status'] === 'approved'): ?>
                        <a href="?delete_request=<?= $req['request_id']; ?>"
                           class="btn-delete-req"
                           onclick="return confirm('Remove this approved request?')">🗑️ Delete</a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Search & Filter -->
        <div class="search-filter-section">
            <form method="GET" class="search-row">
                <input type="text" name="search" class="search-input"
                       placeholder="Search by title, author, ISBN…"
                       value="<?= htmlspecialchars($searchTerm); ?>">
                <select name="category" class="category-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat); ?>"
                            <?= $categoryFilter === $cat ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="search-btn">🔍 Search</button>
                <?php if ($searchTerm || $categoryFilter): ?>
                    <a href="browse_books.php" class="search-btn secondary">✕ Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Books Grid -->
        <?php if (count($books) > 0): ?>
        <div class="books-grid">
            <?php foreach ($books as $book): ?>
                <?php
                    $isAvailable   = $book['available_copies'] > 0;
                    $hasPending    = isset($pendingBookIds[$book['book_id']]);
                    $pendingReqId  = $pendingBookIds[$book['book_id']] ?? null;
                ?>
                <div class="book-card <?= !$isAvailable ? 'unavailable' : ''; ?>">
                    <div class="book-header">
                        <div class="book-cover">📖</div>
                        <div class="book-meta-info">
                            <div class="book-title"><?= htmlspecialchars($book['title']); ?></div>
                            <div class="book-author">by <?= htmlspecialchars($book['author']); ?></div>
                            <div class="meta-tags">
                                <?php if (!empty($book['category'])): ?>
                                    <span class="meta-tag"><?= htmlspecialchars($book['category']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($book['publication_year'])): ?>
                                    <span class="meta-tag"><?= $book['publication_year']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="book-details">
                        <?php if (!empty($book['publisher'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">Publisher</span>
                            <span class="detail-value"><?= htmlspecialchars($book['publisher']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($book['isbn'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">ISBN</span>
                            <span class="detail-value"><?= htmlspecialchars($book['isbn']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($book['shelf_location'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">Location</span>
                            <span class="detail-value"><?= htmlspecialchars($book['shelf_location']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="detail-row">
                            <span class="detail-label">Total Copies</span>
                            <span class="detail-value"><?= $book['total_copies']; ?></span>
                        </div>
                    </div>

                    <div class="book-footer">
                        <?php
                            $bookings = $bookingCounts[$book['book_id']] ?? 0;
                        ?>
                        <?php if ($isAvailable): ?>
                            <span class="availability-badge available">✓ <?= $book['available_copies']; ?> Available</span>
                        <?php else: ?>
                            <span class="availability-badge unavailable">✕ Not Available</span>
                        <?php endif; ?>
                        <?php if ($bookings > 0): ?>
                            <span class="booking-badge">🔖 <?= $bookings; ?> <?= $bookings === 1 ? 'booking' : 'bookings'; ?></span>
                        <?php endif; ?>

                        <?php
                            $hasApproved    = isset($approvedBookIds[$book['book_id']]);
                            $approvedReqId  = $approvedBookIds[$book['book_id']] ?? null;
                            $alreadyBorrowed = isset($activeIssueBookIds[$book['book_id']]);
                        ?>
                        <?php if ($alreadyBorrowed): ?>
                            <!-- User already has this book issued -->
                            <div class="btn-borrowed">📖 Already Borrowed</div>

                        <?php elseif ($hasApproved): ?>
                            <!-- Approved — member can go collect -->
                            <div class="btn-approved">✅ Request Approved!</div>
                            <p style="font-size:12px;color:var(--text-secondary);text-align:center;margin:0;">
                                Visit the library to collect this book.
                            </p>
                            <a href="?delete_request=<?= $approvedReqId; ?>"
                               class="btn-delete-req" style="width:100%;text-align:center;padding:8px 0;"
                               onclick="return confirm('Remove this approved request?')">🗑️ Delete Request</a>

                        <?php elseif ($hasPending): ?>
                            <!-- Pending — waiting for review -->
                            <div class="btn-pending">⏳ Request Pending</div>
                            <a href="?cancel_request=<?= $pendingReqId; ?>"
                               class="btn-cancel-req" style="width:100%;text-align:center;padding:8px 0;"
                               onclick="return confirm('Cancel this borrow request?')">✕ Cancel Request</a>

                        <?php elseif (!$isAvailable): ?>
                            <button class="btn-borrow" disabled>Not Available</button>

                        <?php elseif (!$canBorrowMore): ?>
                            <button class="btn-borrow" disabled title="Borrow limit reached">Limit Reached (3/3)</button>

                        <?php else: ?>
                            <button class="btn-borrow"
                                    onclick="openBorrowModal(<?= $book['book_id']; ?>, '<?= addslashes(htmlspecialchars($book['title'])); ?>', '<?= addslashes(htmlspecialchars($book['author'])); ?>')">
                                📩 Request to Borrow
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="no-books">
                <p style="font-size:36px;margin-bottom:12px;">📚</p>
                <p style="font-size:18px;font-weight:600;color:var(--text-primary);margin-bottom:6px;">No Books Found</p>
                <p><?= ($searchTerm || $categoryFilter) ? 'Try a different search or clear filters.' : 'No books available in the library.'; ?></p>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- ===== Borrow Request Modal ===== -->
<div class="modal-overlay" id="borrowModal">
    <div class="modal-box">
        <h3>📩 Request to Borrow</h3>
        <div class="modal-book" id="modalBookInfo"></div>
        <form method="POST">
            <input type="hidden" name="book_id" id="modalBookId">
            <div class="modal-actions">
                <button type="button" class="modal-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" name="request_borrow" class="modal-submit">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
function openBorrowModal(bookId, title, author) {
    document.getElementById('modalBookId').value   = bookId;
    document.getElementById('modalBookInfo').textContent = '"' + title + '" by ' + author;
    document.getElementById('borrowModal').classList.add('active');
}
function closeModal() {
    document.getElementById('borrowModal').classList.remove('active');
}
document.getElementById('borrowModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>
<script src="../js/mobile_menu.js"></script>
</body>
</html>