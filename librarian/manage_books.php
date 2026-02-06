<?php
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'librarian' && $_SESSION['user']['role'] !== 'admin')) {
    header("Location: ../php/login.php");
    exit();
}

$user = $_SESSION['user'];

// Include database connection
include("../php/connection.php");

$errors = [];
$bookId = 0;
$book = [
    'title' => '',
    'author' => '',
    'isbn' => '',
    'category' => '',
    'publisher' => '',
    'publication_year' => '',
    'total_copies' => 1,
    'available_copies' => 1,
    'shelf_location' => ''
];

// ====================================
// EDIT MODE - FETCH BOOK DATA
// ====================================
if (isset($_GET['id'])) {
    $bookId = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM books WHERE book_id = ?");
    $stmt->bind_param("i", $bookId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $book = $result->fetch_assoc();
    } else {
        header("Location: manage_books.php");
        exit();
    }
}

// ====================================
// SAVE BOOK (ADD OR EDIT)
// ====================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $isbn = trim($_POST['isbn']);
    $category = trim($_POST['category']);
    $publisher = trim($_POST['publisher']);
    $publicationYear = (int)$_POST['publication_year'];
    $totalCopies = (int)$_POST['total_copies'];
    $shelfLocation = trim($_POST['shelf_location']);
    $bookId = (int)($_POST['book_id'] ?? 0);

    // Validation
    if (empty($title)) {
        $errors[] = "Book title is required.";
    }

    if (empty($author)) {
        $errors[] = "Author name is required.";
    }

    if ($totalCopies < 1) {
        $errors[] = "Total copies must be at least 1.";
    }

    // Check ISBN uniqueness
    if (!empty($isbn)) {
        $stmt = $conn->prepare("SELECT book_id FROM books WHERE isbn = ? AND book_id != ?");
        $stmt->bind_param("si", $isbn, $bookId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "ISBN already exists in the library.";
        }
    }

    // Save if no errors
    if (empty($errors)) {
        if ($bookId === 0) {
            // ADD NEW BOOK
            $availableCopies = $totalCopies;
            
            $stmt = $conn->prepare("
                INSERT INTO books (title, author, isbn, category, publisher, publication_year, total_copies, available_copies, shelf_location)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "sssssiiis",
                $title, $author, $isbn, $category, $publisher, 
                $publicationYear, $totalCopies, $availableCopies, $shelfLocation
            );
            
            if ($stmt->execute()) {
                header("Location: manage_books.php");
                exit();
            } else {
                $errors[] = "Failed to add book.";
            }
        } else {
            // EDIT EXISTING BOOK
            // Calculate available copies adjustment
            $stmt = $conn->prepare("SELECT total_copies, available_copies FROM books WHERE book_id = ?");
            $stmt->bind_param("i", $bookId);
            $stmt->execute();
            $oldData = $stmt->get_result()->fetch_assoc();
            
            $difference = $totalCopies - $oldData['total_copies'];
            $newAvailable = $oldData['available_copies'] + $difference;
            
            // Ensure available copies don't go negative
            if ($newAvailable < 0) {
                $errors[] = "Cannot reduce total copies below currently issued copies.";
            } else {
                $stmt = $conn->prepare("
                    UPDATE books 
                    SET title = ?, author = ?, isbn = ?, category = ?, publisher = ?, 
                        publication_year = ?, total_copies = ?, available_copies = ?, shelf_location = ?
                    WHERE book_id = ?
                ");
                $stmt->bind_param(
                    "sssssiisi",
                    $title, $author, $isbn, $category, $publisher, 
                    $publicationYear, $totalCopies, $newAvailable, $shelfLocation, $bookId
                );
                
                if ($stmt->execute()) {
                    header("Location: manage_books.php");
                    exit();
                } else {
                    $errors[] = "Failed to update book.";
                }
            }
        }
    }
    
    // If errors, keep form data
    if (!empty($errors)) {
        $book = [
            'title' => $title,
            'author' => $author,
            'isbn' => $isbn,
            'category' => $category,
            'publisher' => $publisher,
            'publication_year' => $publicationYear,
            'total_copies' => $totalCopies,
            'shelf_location' => $shelfLocation
        ];
    }
}

// Fetch existing categories
$categoriesResult = $conn->query("SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category != '' ORDER BY category");
$existingCategories = [];
while ($row = $categoriesResult->fetch_assoc()) {
    $existingCategories[] = $row['category'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $bookId ? 'Edit' : 'Add'; ?> Book</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .form-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px;
        }

        .form-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }

        .form-header h2 {
            font-size: 24px;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .form-header p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .form-group label .required {
            color: #ef4444;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 14px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--green);
            background: rgba(255, 255, 255, 0.08);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group small {
            color: var(--text-muted);
            font-size: 12px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .btn-submit {
            padding: 12px 30px;
            background: var(--green);
            color: #000;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            background: var(--green-hover);
            color: #fff;
            transform: translateY(-2px);
        }

        .btn-cancel {
            padding: 12px 30px;
            background: var(--border-color);
            color: var(--text-primary);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-cancel:hover {
            background: #3a3f4e;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .category-suggestions {
            margin-top: 5px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .category-chip {
            padding: 4px 12px;
            background: rgba(10, 224, 100, 0.1);
            border: 1px solid rgba(10, 224, 100, 0.3);
            border-radius: 20px;
            font-size: 12px;
            color: var(--green);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .category-chip:hover {
            background: var(--green);
            color: #000;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }
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
                <li><a href="manage_books.php" class="active">Manage Books</a></li>
                <li><a href="../php/issue_books.php">Issue Books</a></li>
                <li><a href="../php/return_books.php">Return Books</a></li>
                <li><a href="view_members.php">View Members</a></li>
            <?php endif; ?>
            <li><a href="profile_librarian.php">Profile</a></li>
            <li class="logout"><a href="../php/logout.php">Logout</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="content">
        <div class="form-container">
            
            <!-- Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="form-card">
                <div class="form-header">
                    <h2><?= $bookId ? '📝 Edit Book' : '➕ Add New Book'; ?></h2>
                    <p><?= $bookId ? 'Update book information' : 'Add a new book to the library collection'; ?></p>
                </div>

                <form method="POST">
                    <input type="hidden" name="book_id" value="<?= $bookId; ?>">

                    <div class="form-grid">
                        <!-- Title -->
                        <div class="form-group full-width">
                            <label>Book Title <span class="required">*</span></label>
                            <input type="text" name="title" value="<?= htmlspecialchars($book['title']); ?>" required>
                        </div>

                        <!-- Author -->
                        <div class="form-group">
                            <label>Author <span class="required">*</span></label>
                            <input type="text" name="author" value="<?= htmlspecialchars($book['author']); ?>" required>
                        </div>

                        <!-- ISBN -->
                        <div class="form-group">
                            <label>ISBN</label>
                            <input type="text" name="isbn" value="<?= htmlspecialchars($book['isbn']); ?>" placeholder="978-3-16-148410-0">
                            <small>International Standard Book Number</small>
                        </div>

                        <!-- Category -->
                        <div class="form-group">
                            <label>Category</label>
                            <input type="text" name="category" id="categoryInput" value="<?= htmlspecialchars($book['category']); ?>" list="categoryList">
                            <datalist id="categoryList">
                                <?php foreach ($existingCategories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat); ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <?php if (!empty($existingCategories)): ?>
                                <div class="category-suggestions">
                                    <?php foreach (array_slice($existingCategories, 0, 8) as $cat): ?>
                                        <span class="category-chip" onclick="document.getElementById('categoryInput').value='<?= htmlspecialchars($cat); ?>'"><?= htmlspecialchars($cat); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Publisher -->
                        <div class="form-group">
                            <label>Publisher</label>
                            <input type="text" name="publisher" value="<?= htmlspecialchars($book['publisher']); ?>">
                        </div>

                        <!-- Publication Year -->
                        <div class="form-group">
                            <label>Publication Year</label>
                            <input type="number" name="publication_year" value="<?= $book['publication_year']; ?>" min="1000" max="<?= date('Y'); ?>">
                        </div>

                        <!-- Total Copies -->
                        <div class="form-group">
                            <label>Total Copies <span class="required">*</span></label>
                            <input type="number" name="total_copies" value="<?= $book['total_copies']; ?>" min="1" required>
                            <small>Number of copies in library</small>
                        </div>

                        <!-- Shelf Location -->
                        <div class="form-group">
                            <label>Shelf Location</label>
                            <input type="text" name="shelf_location" value="<?= htmlspecialchars($book['shelf_location']); ?>" placeholder="e.g., A-101">
                            <small>Physical location in library</small>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-submit">
                            <?= $bookId ? '💾 Update Book' : '➕ Add Book'; ?>
                        </button>
                        <a href="manage_books.php" class="btn-cancel">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<script src="../js/mobile_menu.js"></script>
</body>
</html>