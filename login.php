<?php
session_start();
require_once "config/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
  // Check whether email or password fields are empty
    if ($email === "" || $password === "") {
        $error = "Please enter your email and password.";
    } else {

        // Prepare SQL query to find the user by email
        $stmt = $conn->prepare(
            "SELECT user_id, username, email, password_hash
             FROM users
             WHERE email = ?"
        );

        // Verify the entered password with the stored password hash
        if (!$stmt) {
            $error = "Unable to process login. Please try again.";
        } else {

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 1) {

                $user = $result->fetch_assoc();
                
                // Verify the entered password with the stored password hash
                if (password_verify($password, $user["password_hash"])) {

                    session_regenerate_id(true);

                   // Store user information in session variables
                    $_SESSION["user_id"] = $user["user_id"];
                    $_SESSION["username"] = $user["username"];
                    $_SESSION["email"] = $user["email"];

                    $roleStmt = $conn->prepare(
                        "SELECT role FROM user_roles WHERE user_id = ? LIMIT 1"
                    );

                    if (!$roleStmt) {
                        $error = "Unable to identify your account type.";
                    } else {

                        $roleStmt->bind_param("i", $user["user_id"]);
                        $roleStmt->execute();

                        $roleResult = $roleStmt->get_result();

                        if ($roleResult->num_rows === 1) {

                            $roleData = $roleResult->fetch_assoc();
                            $role = strtolower($roleData["role"]);

                            $_SESSION["role"] = $role;

                            switch ($role) {

                                case "patient":
                                    header("Location: dashboard.php");
                                    exit;

                                case "donor":
                                    header("Location: donor/donor_dashboard.php");
                                    exit;

                                case "hospital":
                                    header("Location: hospital/hospital_dashboard.php");
                                    exit;

                                default:
                                    session_unset();
                                    session_destroy();
                                    $error = "Invalid account type.";
                            }

                        } else {
                            $error = "No account role found. Please contact the administrator.";
                        }

                        $roleStmt->close();
                    }

                } else {
                    $error = "Invalid email or password.";
                }

            } else {
                $error = "Invalid email or password.";
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Medi Scan</title>

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

        .register-link {
            text-align: center;
            margin-top: 22px;
            color: #718096;
        }

        .register-link a {
            color: #123B63;
            font-weight: 700;
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
            <h1>Welcome Back</h1>
            <p>Login to your Emergency Medical ID account</p>
        </div>

        <?php if ($error !== ""): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">

            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>"
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
                Login
            </button>

            <div style="text-align: center; margin-top: 15px;">
                <a href="forgot_password.php">
                    Forgot Password?
                </a>
            </div>

        </form>

        <div class="register-link">
            Don't have an account?
            <a href="register.php">Create an account</a>
        </div>

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
