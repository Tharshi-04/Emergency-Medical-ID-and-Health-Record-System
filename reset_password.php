<?php
session_start();
require_once "config/db.php";

$message = "";
$success = false;

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$hashedToken = hash('sha256', $token);

// Check whether the reset token is valid and not expired.
$stmt = $conn->prepare(
    "SELECT reset_id, user_id
     FROM password_resets
     WHERE reset_token = ? AND expires_at > NOW()
     LIMIT 1"
);

$stmt->bind_param("s", $hashedToken);
$stmt->execute();

$resetRequest = $stmt->get_result()->fetch_assoc();

if (!$resetRequest) {
    $message = "This password reset link is invalid or has expired.";
} elseif ($_SERVER["REQUEST_METHOD"] === "POST") {

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $message = "Password must contain at least 8 characters.";
    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";
    } else {

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $update = $conn->prepare(
            "UPDATE users SET password_hash = ? WHERE user_id = ?"
        );

        $update->bind_param(
            "si",
            $passwordHash,
            $resetRequest['user_id']
        );

        if ($update->execute()) {

            // Delete the used reset token.
            $delete = $conn->prepare(
                "DELETE FROM password_resets WHERE user_id = ?"
            );

            $delete->bind_param("i", $resetRequest['user_id']);
            $delete->execute();

            $success = true;
            $message = "Your password has been reset successfully.";
        } else {
            $message = "Unable to reset your password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .reset-container {
            max-width: 450px;
            margin: 70px auto;
            padding: 20px;
        }

        .reset-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.08);
        }

        .reset-card h1 {
            color: #123B63;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #CBD5E0;
            border-radius: 7px;
            box-sizing: border-box;
        }

        .reset-card button {
            width: 100%;
            padding: 12px;
            background: #123B63;
            color: white;
            border: none;
            border-radius: 7px;
            cursor: pointer;
        }

        .message {
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 18px;
            background: #FDECEC;
            color: #B42332;
        }

        .success {
            background: #E8F7EF;
            color: #167344;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #123B63;
        }
    </style>
</head>

<body>

<div class="reset-container">

    <div class="reset-card">

        <h1>Reset Password</h1>

        <div class="message <?php echo $success ? 'success' : ''; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>

        <?php if ($resetRequest && !$success): ?>

            <form method="POST">

                <input
                    type="hidden"
                    name="token"
                    value="<?php echo htmlspecialchars($token); ?>"
                >

                <div class="form-group">
                    <label>New Password</label>
                    <input
                        type="password"
                        name="password"
                        minlength="8"
                        required
                        placeholder="Enter new password"
                    >
                </div>

                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input
                        type="password"
                        name="confirm_password"
                        minlength="8"
                        required
                        placeholder="Confirm new password"
                    >
                </div>

                <button type="submit">
                    Reset Password
                </button>

            </form>

        <?php endif; ?>

        <a href="login.php" class="back-link">
            Back to Login
        </a>

    </div>

</div>

</body>
</html>