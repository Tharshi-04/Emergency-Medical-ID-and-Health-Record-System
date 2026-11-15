<?php
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {
        $error = "Please enter your username and password.";
    } else {

        $admin_username = "admin123@gmail.com";
        $admin_password = "Admin@123";

        if ($username === $admin_username && $password === $admin_password) {

            session_regenerate_id(true);

            $_SESSION["admin_logged_in"] = true;
            $_SESSION["admin_username"] = $admin_username;

            header("Location: admin/admin_dashboard.php");
            exit;

        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Login | Emergency Medical ID</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .login-section {
            min-height: 80vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 45px 16px;
        }

        .login-card {
            width: 100%;
            max-width: 450px;
            background: white;
            padding: 38px;
            border-radius: 16px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 8px 30px rgba(18,59,99,0.10);
        }

        .login-heading {
            text-align: center;
            margin-bottom: 28px;
        }

        .login-heading h1 {
            color: #123B63;
            font-size: 28px;
            margin-bottom: 8px;
        }

        .login-heading p {
            color: #718096;
        }

        .login-card .submit-btn {
            width: 100%;
            margin-top: 8px;
            padding: 13px;
            background: #16A6A1;
            color: white;
            font-weight: 700;
            border: none;
            border-radius: 8px;
        }

        .login-card .submit-btn:hover {
            background: #108580;
        }

        @media (max-width: 500px) {
            .login-card {
                padding: 25px 18px;
            }
        }
    </style>
</head>

<body>

<header class="site-header">
    <div class="container">
        <a href="index.php" class="logo">
            Medi <span>Scan</span>
        </a>
        <a href="index.php">Home</a>
    </div>
</header>

<main class="login-section">

    <div class="login-card">

        <div class="login-heading">
            <h1>Admin Login</h1>
            <p>Login to your Admin Dashboard</p>
        </div>

        <?php if ($error !== ""): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="admin_login.php">

            <div class="form-group">
                <label for="username">Admin Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter admin username"
                    value="<?php echo htmlspecialchars($_POST["username"] ?? ""); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                >
            </div>

            <button type="submit" class="submit-btn">
                Admin Login
            </button>

        </form>

    </div>

</main>

<footer class="main-footer">
    <div class="footer-container">
        <div class="footer-bottom">
            &copy; <?php echo date("Y"); ?>
            Medi Scan. All rights reserved.
        </div>
    </div>
</footer>

</body>
</html>