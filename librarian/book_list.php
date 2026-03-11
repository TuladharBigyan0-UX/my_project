<?php
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'librarian' && $_SESSION['user']['role'] !== 'admin')) {
    header("Location: ../php/login.php");
    exit();
}

$user = $_SESSION['user'];
include("../php/connection.php");

$errors = [];
$success = '';

// ====================================
// DELETE BOOK
// ====================================
if (isset($_GET['delete'])) {
    $bookId = (int)$_GET['delete'];

    // Check if book has active (unreturned) issues
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM issues WHERE book_id = ? AND return_date IS NULL");
    $stmt->bind_param("i", $bookId);
    $stmt->execute();
    $activeIssues = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

    if ($activeIssues > 0) {
        $errors[] = "Cannot delete this book — it has $activeIssues active issue(s). Please ensure all copies are returned first.";
    } else {
        $stmt = $conn->prepare("DELETE FROM books WHERE book_id = ?");
        $stmt->bind_param("i", $bookId);
        if ($stmt->execute()) {
            $success = "Book deleted successfully.";
        } else {
            $errors[] = "Failed to delete book.";
        }
    }
}

// ====================================
// SEARCH / FILTER
// ====================================
$searchTerm = trim($_GET['search'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');

$query = "SELECT * FROM books WHERE 1=1";
$params = [];
$types = '';

if (!empty($searchTerm)) {
    $query .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
    $pattern = '%' . $searchTerm . '%';
    $params[] = $pattern;
    $params[] = $pattern;
    $params[] = $pattern;
    $types .= 'sss';
}

if (!empty($categoryFilter)) {
    $query .= " AND category = ?";
    $params[] = $categoryFilter;
    $types .= 's';
}

$query .= " ORDER BY title ASC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $booksResult = $stmt->get_result();
} else {
    $booksResult = $conn->query($query);
}

$books = [];
while ($row = $booksResult->fetch_assoc()) {
    $books[] = $row;
}

// Fetch categories for filter
$catResult = $conn->query("SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category != '' ORDER BY category");
$categories = [];
while ($row = $catResult->fetch_assoc()) {
    $categories[] = $row['category'];
}

// Stats
$totalBooks   = $conn->query("SELECT COUNT(*) as c FROM books")->fetch_assoc()['c'] ?? 0;
$totalCopies  = $conn->query("SELECT SUM(total_copies) as c FROM books")->fetch_assoc()['c'] ?? 0;
$availCopies  = $conn->query("SELECT SUM(available_copies) as c FROM books")->fetch_assoc()['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <style>
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-box {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 18px;
            text-align: center;
        }

        .stat-box h3 {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .stat-box .number {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .filter-bar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            align-items: center;
        }

        .filter-bar input,
        .filter-bar select {
            padding: 10px 14px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            min-width: 200px;
        }

        .filter-bar input:focus,
        .filter-bar select:focus {
            outline: none;
            border-color: var(--green);
        }

        .filter-bar select option {
            background: #1e2433;
        }

        .filter-btn {
            padding: 10px 20px;
            background: var(--green);
            color: #000;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .filter-btn:hover {
            background: var(--green-hover);
            color: #fff;
        }

        .filter-btn.secondary {
            background: var(--border-color);
            color: var(--text-primary);
        }

        .filter-btn.secondary:hover {
            background: #3a3f4e;
        }

        .book-table-wrapper {
            overflow-x: auto;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
        }

        .book-mgmt-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        .book-mgmt-table thead {
            background: var(--border-color);
        }

        .book-mgmt-table th {
            padding: 14px 16px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            border-bottom: 2px solid #3a3f4e;
            white-space: nowrap;
        }

        .book-mgmt-table td {
            padding: 14px 16px;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            vertical-align: middle;
        }

        .book-mgmt-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .book-mgmt-table tbody tr:last-child td {
            border-bottom: none;
        }

        .book-title-cell {
            font-weight: 600;
            color: var(--text-primary);
        }

        .book-author-cell {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 3px;
        }

        .avail-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .avail-badge.green {
            background: rgba(34, 197, 94, 0.15);
            color: #22c55e;
        }

        .avail-badge.red {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }

        .avail-badge.yellow {
            background: rgba(234, 179, 8, 0.15);
            color: #eab308;
        }

        .action-btn {
            padding: 7px 14px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
            margin-right: 5px;
        }

        .action-btn.edit {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
        }

        .action-btn.edit:hover {
            background: #3b82f6;
            color: #fff;
        }

        .action-btn.delete {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }

        .action-btn.delete:hover {
            background: #ef4444;
            color: #fff;
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

        .no-books {
            text-align: center;
            padding: 60px;
            color: var(--text-muted);
        }

        /* Delete Confirmation Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.75);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px;
            max-width: 420px;
            width: 90%;
            text-align: center;
        }

        .modal-box h3 {
            font-size: 20px;
            color: var(--text-primary);
            margin-bottom: 12px;
        }

        .modal-box p {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .modal-box .book-name {
            color: #ef4444;
            font-weight: 600;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .modal-btn {
            padding: 11px 28px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
        }

        .modal-btn.cancel {
            background: var(--border-color);
            color: var(--text-primary);
        }

        .modal-btn.cancel:hover {
            background: #3a3f4e;
        }

        .modal-btn.confirm {
            background: #ef4444;
            color: #fff;
        }

        .modal-btn.confirm:hover {
            background: #dc2626;
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
                <li><a href="book_list.php" class="active">Manage Books</a></li>
                <li><a href="../php/issue_books.php">Issue Books</a></li>
                <li><a href="../php/return_books.php">Return Books</a></li>
                <li><a href="../php/view_members.php">View Members</a></li>
                <li><a href="profile_librarian.php">Profile</a></li>
            <?php else: ?>
                <li><a href="../dashboard/admin_dashboard.php">Dashboard</a></li>
                <li><a href="../admin/manage_librarian.php">Manage Librarians</a></li>
                <li><a href="../admin/manage_member.php">Manage Members</a></li>
                <li><a href="../admin/view_reports.php">View Reports</a></li>
                <li><a href="../php/view_members.php">View Members</a></li>
                <li><a href="book_list.php" class="active">Manage Books</a></li>
                <li><a href="../php/issue_books.php">Issue Books</a></li>
                <li><a href="../php/return_books.php">Return Books</a></li>
                <li><a href="../admin/profile.php">Profile</a></li>
            <?php endif; ?>
            <li class="logout"><a href="../php/logout.php">Logout</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="content">
        <div class="top-bar">
            <div>
                <h1>Manage Books 📚</h1>
                <p style="color:var(--text-secondary);font-size:14px;margin-top:5px;">View, edit, and delete books in the library</p>
            </div>
            <a href="manage_books.php" class="btn">➕ Add New Book</a>
        </div>

        <!-- Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $e): ?>
                    <p><?= htmlspecialchars($e); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-box">
                <h3>Total Titles</h3>
                <div class="number"><?= $totalBooks; ?></div>
            </div>
            <div class="stat-box">
                <h3>Total Copies</h3>
                <div class="number"><?= $totalCopies; ?></div>
            </div>
            <div class="stat-box">
                <h3>Available</h3>
                <div class="number" style="color:#22c55e;"><?= $availCopies; ?></div>
            </div>
            <div class="stat-box">
                <h3>Issued</h3>
                <div class="number" style="color:#eab308;"><?= $totalCopies - $availCopies; ?></div>
            </div>
        </div>

        <!-- Search & Filter -->
        <form method="GET" class="filter-bar">
            <input type="text" name="search" placeholder="Search by title, author, ISBN..." value="<?= htmlspecialchars($searchTerm); ?>">
            <select name="category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat); ?>" <?= $categoryFilter === $cat ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="filter-btn">🔍 Search</button>
            <?php if ($searchTerm || $categoryFilter): ?>
                <a href="book_list.php" class="filter-btn secondary">✕ Clear</a>
            <?php endif; ?>
        </form>

        <!-- Books Table -->
        <div class="book-table-wrapper">
            <?php if (count($books) > 0): ?>
                <table class="book-mgmt-table">
                    <thead>
                        <tr>
                            <th>SN</th>
                            <th>Title / Author</th>
                            <th>ISBN</th>
                            <th>Category</th>
                            <th>Publisher</th>
                            <th>Year</th>
                            <th>Copies</th>
                            <th>Available</th>
                            <th>Location</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sn = 1; foreach ($books as $book): ?>
                            <tr>
                                <td><?= $sn++; ?></td>
                                <td>
                                    <div class="book-title-cell"><?= htmlspecialchars($book['title']); ?></div>
                                    <div class="book-author-cell">by <?= htmlspecialchars($book['author']); ?></div>
                                </td>
                                <td><?= htmlspecialchars($book['isbn'] ?: '—'); ?></td>
                                <td><?= htmlspecialchars($book['category'] ?: '—'); ?></td>
                                <td><?= htmlspecialchars($book['publisher'] ?: '—'); ?></td>
                                <td><?= $book['publication_year'] ?: '—'; ?></td>
                                <td><?= $book['total_copies']; ?></td>
                                <td>
                                    <?php
                                        $avail = $book['available_copies'];
                                        $total = $book['total_copies'];
                                        if ($avail == 0) {
                                            $cls = 'red';
                                        } elseif ($avail < $total) {
                                            $cls = 'yellow';
                                        } else {
                                            $cls = 'green';
                                        }
                                    ?>
                                    <span class="avail-badge <?= $cls; ?>"><?= $avail; ?> / <?= $total; ?></span>
                                </td>
                                <td><?= htmlspecialchars($book['shelf_location'] ?: '—'); ?></td>
                                <td>
                                    <a href="manage_books.php?id=<?= $book['book_id']; ?>" class="action-btn edit">✏️ Edit</a>
                                    <button
                                        class="action-btn delete"
                                        onclick="confirmDelete(<?= $book['book_id']; ?>, '<?= addslashes(htmlspecialchars($book['title'])); ?>', <?= $book['available_copies']; ?>, <?= $book['total_copies']; ?>)"
                                    >🗑️ Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-books">
                    <p style="font-size:40px;margin-bottom:15px;">📚</p>
                    <p style="font-size:18px;font-weight:600;color:var(--text-primary);margin-bottom:8px;">No Books Found</p>
                    <p><?= ($searchTerm || $categoryFilter) ? 'Try a different search or clear filters.' : 'No books in the library yet.'; ?></p>
                    <a href="manage_books.php" class="btn" style="margin-top:20px;">➕ Add First Book</a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <p style="font-size:48px;margin-bottom:10px;">🗑️</p>
        <h3>Delete Book?</h3>
        <p>You are about to permanently delete<br>
           <span class="book-name" id="modalBookName"></span><br>
           <span id="modalWarning" style="color:#eab308;"></span>
           This action <strong>cannot be undone</strong>.
        </p>
        <div class="modal-actions">
            <button class="modal-btn cancel" onclick="closeModal()">Cancel</button>
            <a id="confirmDeleteBtn" href="#" class="modal-btn confirm">Yes, Delete</a>
        </div>
    </div>
</div>

<script>
function confirmDelete(bookId, title, available, total) {
    document.getElementById('modalBookName').textContent = '"' + title + '"';
    document.getElementById('confirmDeleteBtn').href = 'book_list.php?delete=' + bookId;

    const issued = total - available;
    const warning = document.getElementById('modalWarning');
    if (issued > 0) {
        warning.textContent = '⚠️ ' + issued + ' copy/copies are currently issued. ';
    } else {
        warning.textContent = '';
    }

    document.getElementById('deleteModal').classList.add('active');
}

function closeModal() {
    document.getElementById('deleteModal').classList.remove('active');
}

// Close on overlay click
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Close on ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>
<script src="../js/mobile_menu.js"></script>
</body>
</html>