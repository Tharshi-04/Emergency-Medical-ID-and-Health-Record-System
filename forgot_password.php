<?php
session_start();
require_once "config/db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } else {

        $stmt = $conn->prepare(
            "SELECT user_id FROM users WHERE email = ?"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();

        // Always show the same message to protect account privacy.
        $message = "If this email is registered, a password reset link will be sent.";

        if ($user) {

            // Delete previous reset requests for this user.
            $delete = $conn->prepare(
                "DELETE FROM password_resets WHERE user_id = ?"
            );
            $delete->bind_param("i", $user['user_id']);
            $delete->execute();

            // Generate a secure reset token.
            $token = bin2hex(random_bytes(32));
            $hashedToken = hash('sha256', $token);

            $expires = date('Y-m-d H:i:s', time() + 1800);

            $insert = $conn->prepare(
                "INSERT INTO password_resets
                (user_id, reset_token, expires_at)
                VALUES (?, ?, ?)"
            );

            $insert->bind_param(
                "iss",
                $user['user_id'],
                $hashedToken,
                $expires
            );

            if ($insert->execute()) {

                $resetLink =
                    "http://localhost/Emergency%20Medical%20ID/reset_password.php?token="
                    . urlencode($token);

                $subject = "Emergency Medical ID - Password Reset";

                $body = "Hello,\n\n";
                $body .= "Click the link below to reset your password:\n\n";
                $body .= $resetLink . "\n\n";
                $body .= "This link expires in 30 minutes.\n\n";
                $body .= "If you did not request this, ignore this email.";

                $headers = "From: noreply@emergencymedicalid.com";

                // Email requires SMTP/mail server configuration.
                mail($email, $subject, $body, $headers);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .forgot-container {
            max-width: 450px;
            margin: 70px auto;
            padding: 20px;
        }

        .forgot-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.08);
        }

        .forgot-card h1 {
            color: #123B63;
            margin-bottom: 12px;
        }

        .forgot-card p {
            color: #718096;
            margin-bottom: 22px;
        }

        .forgot-card input {
            width: 100%;
            padding: 12px;
            border: 1px solid #CBD5E0;
            border-radius: 7px;
            margin-bottom: 18px;
            box-sizing: border-box;
        }

        .forgot-card button {
            width: 100%;
            padding: 12px;
            background: #123B63;
            color: white;
            border: none;
            border-radius: 7px;
            cursor: pointer;
        }

        .message {
            background: #E8F7EF;
            color: #167344;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 18px;
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

<div class="forgot-container">

    <div class="forgot-card">

        <h1>Forgot Password?</h1>

        <p>
            Enter your registered email address to request a password reset link.
        </p>

        <?php if ($message): ?>
            <div class="message">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <label for="email">Email Address</label>

            <input
                type="email"
                id="email"
                name="email"
                placeholder="Enter your registered email"
                required
            >

            <button type="submit">
                Send Reset Link
            </button>

        </form>

        <a href="login.php" class="back-link">
            Back to Login
        </a>

    </div>

</div>

</body>
</html>