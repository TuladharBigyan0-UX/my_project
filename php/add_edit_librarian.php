<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("connection.php");

$id = '';
$fullname = '';
$email = '';
$phone = '';

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
        $email = $row['email'];
        $phone = $row['phone'];
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
    $stmt = $conn->prepare(
        "SELECT id FROM librarians WHERE email=? AND id!=?"
    );
    $stmt->bind_param("si", $email, $id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Email already exists.";
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
                "INSERT INTO librarians (fullname, email, phone, password)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("ssss", $fullname, $email, $phone, $hashedPassword);
        } else {
            // UPDATE
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare(
                    "UPDATE librarians
                     SET fullname=?, email=?, phone=?, password=?
                     WHERE id=?"
                );
                $stmt->bind_param("ssssi", $fullname, $email, $phone, $hashedPassword, $id);
            } else {
                $stmt = $conn->prepare(
                    "UPDATE librarians
                     SET fullname=?, email=?, phone=?
                     WHERE id=?"
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
    <style>
        .form-box {
            max-width: 500px;
            background: #ffffff;
            color: #000;
            padding: 30px;
            border-radius: 12px;
            margin-top: 30px;
        }

        .form-box input {
            width: 100%;
            padding: 12px;
            margin-top: 6px;
            margin-bottom: 16px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }

        .form-box button {
            padding: 12px 18px;
            background: #0ae064;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }

        .form-box a {
            margin-left: 10px;
            text-decoration: none;
            color: crimson;
        }
    </style>
</head>
<body>

<div class="dashboard">

    <main class="content">
        <h1><?= $id ? 'Edit' : 'Add'; ?> Librarian</h1>

             <!-- ERROR DISPLAY -->
        <?php if (!empty($errors)): ?>
            <div style="background:#ffdddd;color:#900;padding:10px;border-radius:6px;margin-bottom:15px;">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>


        <div class="form-box">
            <form method="POST">

                <input type="hidden" name="id" value="<?= $id; ?>">

                <label>Full Name</label>
                <input type="text" name="fullname" required value="<?= htmlspecialchars($fullname); ?>">

                <label>Email</label>
                <input type="email" name="email" required value="<?= htmlspecialchars($email); ?>">

                <label>Phone</label>
                     <input type="text" name="phone" pattern="^9[6-8][0-9]{8}$" placeholder="9*********" maxlength="10" required>
                <small id="contactError" style="color:red;"></small>

                <label>Password <?= $id ? '(leave blank to keep current)' : '*' ?></label>
                <input type="password" name="password">

                <button type="submit">Save</button>
                <a href="manage_librarian.php">Cancel</a>

            </form>
        </div>
    </main>

</div>

</body>
</html>
