
<?php
session_start();
require_once "config/db.php";

$error = "";
$success = "";

$username = "";
$email = "";
$role = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";
    $role = $_POST["role"] ?? "";

    if ($username === "" || $email === "" || $password === "" || $confirm_password === "" || $role === "") {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must contain at least 8 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!in_array($role, ["patient", "donor", "hospital"], true)) {
        $error = "Please select a valid account type.";
    } else {

        $check = $conn->prepare(
            "SELECT user_id FROM users WHERE email = ? OR username = ?"
        );

        if (!$check) {
            $error = "Unable to check account details.";
        } else {
            $check->bind_param("ss", $email, $username);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {
                $error = "Email or username already exists.";
            } else {

                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $conn->begin_transaction();

                try {
                    $insertUser = $conn->prepare(
                        "INSERT INTO users (username, email, password_hash, is_admin)
                         VALUES (?, ?, ?, 0)"
                    );

                    if (!$insertUser) {
                        throw new Exception("Unable to prepare user registration.");
                    }

                    $insertUser->bind_param(
                        "sss",
                        $username,
                        $email,
                        $password_hash
                    );

                    if (!$insertUser->execute()) {
                        throw new Exception("Unable to create user account.");
                    }

                    $user_id = $conn->insert_id;

                    $insertRole = $conn->prepare(
                        "INSERT INTO user_roles (user_id, role)
                         VALUES (?, ?)"
                    );

                    if (!$insertRole) {
                        throw new Exception("Unable to prepare user role.");
                    }

                    $insertRole->bind_param("is", $user_id, $role);

                    if (!$insertRole->execute()) {
                        throw new Exception("Unable to save user role.");
                    }

                    $conn->commit();

                    $success = "Registration successful! You can now login.";
                    $username = "";
                    $email = "";
                    $role = "";

                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Registration failed. Please try again.";
                }
            }

            $check->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Account | Emergency Medical ID</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .register-section {
            min-height: 80vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 45px 16px;
        }

        .register-card {
            width: 100%;
            max-width: 520px;
            background: white;
            padding: 35px;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(18, 59, 99, 0.10);
            border: 1px solid #E2E8F0;
        }

        .register-heading {
            text-align: center;
            margin-bottom: 28px;
        }

        .register-heading h1 {
            color: #123B63;
            font-size: 27px;
            margin-bottom: 8px;
        }

        .register-heading p {
            color: #718096;
        }

        .register-card .submit-btn {
            width: 100%;
            margin-top: 8px;
            padding: 13px;
            background: #16A6A1;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 700;
        }

        .register-card .submit-btn:hover {
            background: #108580;
        }

        .login-link {
            text-align: center;
            margin-top: 22px;
            color: #718096;
        }

        .login-link a {
            color: #123B63;
            font-weight: 700;
        }

        .password-note {
            color: #718096;
            font-size: 12px;
            margin-top: 5px;
        }

        @media (max-width: 500px) {
            .register-card {
                padding: 24px 18px;
            }
        }
    </style>
</head>

<body>

<header class="site-header">
    <div class="container">
        <a href="index.php" class="logo">
            Emergency <span>Medical ID</span>
        </a>
        <a href="index.php">Home</a>
    </div>
</header>

<main class="register-section">

    <div class="register-card">

        <div class="register-heading">
            <h1>Create Your Account</h1>
            <p>Join Emergency Medical ID</p>
        </div>

        <?php if ($error !== ""): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
                <p style="margin-top:10px;">
                    <a href="login.php">Go to Login</a>
                </p>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">

            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?php echo htmlspecialchars($username); ?>"
                    placeholder="Enter your username"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?php echo htmlspecialchars($email); ?>"
                    placeholder="Enter your email"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Create a password"
                    minlength="8"
                    required
                >
                <p class="password-note">Use at least 8 characters.</p>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Re-enter your password"
                    minlength="8"
                    required
                >
            </div>

            <div class="form-group">
                <label for="role">Account Type</label>

                <select id="role" name="role" required>
                    <option value="">Select account type</option>

                    <option value="patient" <?php echo $role === "patient" ? "selected" : ""; ?>>
                        Patient
                    </option>

                    <option value="donor" <?php echo $role === "donor" ? "selected" : ""; ?>>
                        Donor
                    </option>

                    <option value="hospital" <?php echo $role === "hospital" ? "selected" : ""; ?>>
                        Hospital
                    </option>
                </select>
            </div>

            <button type="submit" class="submit-btn">
                Create Account
            </button>

        </form>

        <div class="login-link">
            Already have an account?
            <a href="login.php">Login here</a>
        </div>

    </div>

</main>

<footer class="main-footer">
    <div class="footer-container">
        <div class="footer-bottom">
            &copy; <?php echo date("Y"); ?>
            Emergency Medical ID. All rights reserved.
        </div>
    </div>
</footer>

</body>
</html>
 