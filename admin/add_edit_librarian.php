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

$id = 0;
$fullname = '';
$email = '';
$phone = '';
$errors = [];

// ======================
// EDIT MODE
// ======================
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT fullname, email, phone FROM librarians WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row) {
        $fullname = $row['fullname'];
        $email    = $row['email'];
        $phone    = $row['phone'];
    }
}

// =======================
// PASSWORD CHECK FUNCTION
// =======================
function validatePassword($password) {
    return preg_match(
        '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/',
        $password
    );
}

// ======================
// SAVE FORM
// ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullname = trim($_POST['fullname']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $password = $_POST['password'] ?? '';
    $id       = (int)($_POST['id'] ?? 0);

    // -----------------------
    // EMAIL FORMAT CHECK
    // -----------------------
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // -----------------------
    // EMAIL UNIQUE CHECK
    // -----------------------
    $stmt = $conn->prepare("SELECT id FROM librarians WHERE email=? AND id!=?");
    $stmt->bind_param("si", $email, $id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Email already exists.";
    }

    // -----------------------
    // PHONE VALIDATION
    // -----------------------
    if (!empty($phone) && !preg_match('/^9[6-8][0-9]{8}$/', $phone)) {
        $errors[] = "Contact must start with 9, second digit 6–8, and be 10 digits.";
    }

    // -----------------------
    // PASSWORD VALIDATION
    // -----------------------
    if ($id === 0 && empty($password)) {
        $errors[] = "Password is required.";
    }

    if (!empty($password) && !validatePassword($password)) {
        $errors[] = "Password must be at least 8 characters and include uppercase, lowercase, number, and special character.";
    }

    // -----------------------
    // SAVE IF NO ERRORS
    // -----------------------
    if (empty($errors)) {

        if ($id === 0) {
            // ADD
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO librarians (fullname, email, phone, password) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("ssss", $fullname, $email, $phone, $hashedPassword);
        } else {
            // UPDATE
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare(
                    "UPDATE librarians SET fullname=?, email=?, phone=?, password=? WHERE id=?"
                );
                $stmt->bind_param("ssssi", $fullname, $email, $phone, $hashedPassword, $id);
            } else {
                $stmt = $conn->prepare(
                    "UPDATE librarians SET fullname=?, email=?, phone=? WHERE id=?"
                );
                $stmt->bind_param("sssi", $fullname, $email, $phone, $id);
            }
        }

        $stmt->execute();
        header("Location: manage_librarian.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $id ? 'Edit' : 'Add'; ?> Librarian</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <style>
        .form-card {
            max-width: 560px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px;
            margin-top: 20px;
        }

        .form-card h2 {
            font-size: 20px;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .form-card .subtitle {
            color: var(--text-secondary);
            font-size: 13px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 7px;
        }

        .form-group label .req {
            color: #ef4444;
        }

        .form-group input {
            width: 100%;
            padding: 11px 14px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            transition: border-color 0.2s, background 0.2s;
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--green);
            background: rgba(255,255,255,0.08);
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: var(--text-muted);
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            flex-wrap: wrap;
        }

        .btn-save {
            padding: 11px 28px;
            background: var(--green);
            color: #000;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-save:hover {
            background: var(--green-hover);
            color: #fff;
        }

        .btn-cancel {
            padding: 11px 24px;
            background: var(--border-color);
            color: var(--text-primary);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-cancel:hover {
            background: #3a3f4e;
        }

        /* Error box */
        .alert-error {
            background: rgba(239,68,68,0.15);
            color: #ef4444;
            border: 1px solid rgba(239,68,68,0.3);
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error p {
            margin: 0 0 4px;
        }

        .alert-error p:last-child {
            margin-bottom: 0;
        }

        @media (max-width: 480px) {
            .form-card {
                padding: 20px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn-save,
            .btn-cancel {
                width: 100%;
                text-align: center;
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
        <h1><?= $id ? 'Edit' : 'Add'; ?> Librarian</h1>

        <!-- ERROR DISPLAY -->
        <?php if (!empty($errors)): ?>
            <div class="alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <h2><?= $id ? '✏️ Edit Librarian' : '➕ Add New Librarian'; ?></h2>
            <p class="subtitle"><?= $id ? 'Update librarian account details' : 'Create a new librarian account'; ?></p>

            <form method="POST">
                <input type="hidden" name="id" value="<?= $id; ?>">

                <div class="form-group">
                    <label>Full Name <span class="req">*</span></label>
                    <input type="text" name="fullname" required
                           value="<?= htmlspecialchars($fullname); ?>"
                           placeholder="Enter full name">
                </div>

                <div class="form-group">
                    <label>Email Address <span class="req">*</span></label>
                    <input type="email" name="email" required
                           value="<?= htmlspecialchars($email); ?>"
                           placeholder="Enter email address">
                </div>

                <div class="form-group">
                    <label>Phone Number <span class="req">*</span></label>
                    <input type="text" name="phone"
                           pattern="^9[6-8][0-9]{8}$"
                           placeholder="9*********"
                           maxlength="10"
                           required
                           value="<?= htmlspecialchars($phone); ?>">
                    <small>Must start with 9, second digit 6–8, 10 digits total.</small>
                </div>

                <div class="form-group">
                    <label>Password <?= $id ? '<span style="color:var(--text-muted);font-weight:400;">(leave blank to keep current)</span>' : '<span class="req">*</span>'; ?></label>
                    <input type="password" name="password"
                           <?= $id === 0 ? 'required' : ''; ?>
                           placeholder="<?= $id ? 'Leave blank to keep current password' : 'Enter password'; ?>">
                    <small>Min. 8 characters with uppercase, lowercase, number &amp; special character.</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-save">
                        <?= $id ? '💾 Update Librarian' : '➕ Add Librarian'; ?>
                    </button>
                    <a href="manage_librarian.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </main>

</div>

<script src="../js/mobile_menu.js"></script>
</body>
</html>