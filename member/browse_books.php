<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'member') {
    header("Location: ../php/login.php");
    exit();
}

$user = $_SESSION['user'];
include("../php/connection.php");

$searchTerm = '';
$categoryFilter = '';
$books = [];
$categories = [];

// Fetch all categories
$result = $conn->query("SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category != '' ORDER BY category");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row['category'];
}

// Search and filter logic
if (isset($_GET['search']) || isset($_GET['category'])) {
    $searchTerm = $_GET['search'] ?? '';
    $categoryFilter = $_GET['category'] ?? '';
    
    $query = "SELECT * FROM books WHERE 1=1";
    $params = [];
    $types = '';
    
    if (!empty($searchTerm)) {
        $query .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
        $searchPattern = '%' . $searchTerm . '%';
        $params[] = $searchPattern;
        $params[] = $searchPattern;
        $params[] = $searchPattern;
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
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query);
    }
} else {
    // Fetch all books
    $result = $conn->query("SELECT * FROM books ORDER BY title ASC");
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
}

// Get member's active issues count
$userId = $user['id'];
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM issues WHERE user_id = ? AND return_date IS NULL");
$stmt->bind_param("i", $userId);
$stmt->execute();
$activeIssues = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
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
        .search-filter-section {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .search-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .search-input, .category-select {
            flex: 1;
            min-width: 250px;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
        }

        .search-input:focus, .category-select:focus {
            outline: none;
            border-color: var(--green);
        }

        .category-select option {
            background: #1e2433;
        }

        .search-btn {
            padding: 12px 30px;
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
            transition: all 0.3s ease;
        }

        .book-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(10, 224, 100, 0.15);
            border-color: var(--green);
        }

        .book-card.unavailable {
            opacity: 0.6;
            border-color: rgba(239, 68, 68, 0.3);
        }

        .book-header {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }

        .book-cover {
            width: 80px;
            height: 110px;
            background: linear-gradient(135deg, rgba(10, 224, 100, 0.2), rgba(6, 196, 86, 0.2));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            flex-shrink: 0;
        }

        .book-info {
            flex: 1;
        }

        .book-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
            line-height: 1.4;
        }

        .book-author {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }

        .book-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .meta-tag {
            padding: 4px 10px;
            background: rgba(10, 224, 100, 0.1);
            border: 1px solid rgba(10, 224, 100, 0.3);
            border-radius: 12px;
            font-size: 11px;
            color: var(--green);
        }

        .book-details {
            margin: 15px 0;
            padding: 15px 0;
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 13px;
        }

        .detail-label {
            color: var(--text-secondary);
        }

        .detail-value {
            color: var(--text-primary);
            font-weight: 500;
        }

        .availability-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .availability-badge.available {
            background: rgba(34, 197, 94, 0.15);
            color: #22c55e;
        }

        .availability-badge.unavailable {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }

        .info-banner {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid rgba(59, 130, 246, 0.3);
            font-size: 14px;
        }

        .warning-banner {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid rgba(239, 68, 68, 0.3);
            font-size: 14px;
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
        <p>Explore our collection of available books</p>

        <!-- Info Banner -->
        <?php if (!$canBorrowMore): ?>
            <div class="warning-banner">
                ⚠️ You have reached your borrowing limit (<?= $activeIssues; ?>/3 books). Please return a book before borrowing more.
            </div>
        <?php else: ?>
            <div class="info-banner">
                ℹ️ You can borrow up to <?= 3 - $activeIssues; ?> more book(s). Visit the library to borrow books.
            </div>
        <?php endif; ?>

        <!-- Search and Filter -->
        <div class="search-filter-section">
            <form method="GET" class="search-row">
                <input 
                    type="text" 
                    name="search" 
                    class="search-input" 
                    placeholder="Search by title, author, or ISBN..."
                    value="<?= htmlspecialchars($searchTerm); ?>"
                >
                
                <select name="category" class="category-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat); ?>" <?= $categoryFilter === $cat ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="search-btn">🔍 Search</button>
                
                <?php if ($searchTerm || $categoryFilter): ?>
                    <a href="browse_books.php" class="search-btn" style="background: var(--border-color);">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Books Grid -->
        <?php if (count($books) > 0): ?>
            <div class="books-grid">
                <?php foreach ($books as $book): ?>
                    <div class="book-card <?= $book['available_copies'] == 0 ? 'unavailable' : ''; ?>">
                        <div class="book-header">
                            <div class="book-cover">📖</div>
                            <div class="book-info">
                                <div class="book-title"><?= htmlspecialchars($book['title']); ?></div>
                                <div class="book-author">by <?= htmlspecialchars($book['author']); ?></div>
                                <div class="book-meta">
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
                                <span class="detail-label">Publisher:</span>
                                <span class="detail-value"><?= htmlspecialchars($book['publisher']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($book['isbn'])): ?>
                            <div class="detail-row">
                                <span class="detail-label">ISBN:</span>
                                <span class="detail-value"><?= htmlspecialchars($book['isbn']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($book['shelf_location'])): ?>
                            <div class="detail-row">
                                <span class="detail-label">Location:</span>
                                <span class="detail-value"><?= htmlspecialchars($book['shelf_location']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="detail-row">
                                <span class="detail-label">Total Copies:</span>
                                <span class="detail-value"><?= $book['total_copies']; ?></span>
                            </div>
                        </div>

                        <div style="text-align: center;">
                            <?php if ($book['available_copies'] > 0): ?>
                                <span class="availability-badge available">
                                    ✓ <?= $book['available_copies']; ?> Available
                                </span>
                            <?php else: ?>
                                <span class="availability-badge unavailable">
                                    ✕ Not Available
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-books">
                <h3>📚 No Books Found</h3>
                <p><?= ($searchTerm || $categoryFilter) ? 'Try a different search or filter' : 'No books available in the library'; ?></p>
            </div>
        <?php endif; ?>

    </main>
</div>
<script src="../js/mobile_menu.js"></script>
</body>
</html>