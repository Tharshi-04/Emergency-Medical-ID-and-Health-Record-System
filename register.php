
<?php
session_start();

require_once "config/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $full_name = trim($_POST["full_name"]);
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $dob = $_POST["dob"];
    $gender = $_POST["gender"];
    $blood_group = $_POST["blood_group"];
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    if (
        empty($full_name) ||
        empty($username) ||
        empty($email) ||
        empty($dob) ||
        empty($gender) ||
        empty($blood_group) ||
        empty($password) ||
        empty($confirm_password)
    ) {
        $error = "Please fill in all fields.";
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    }
    elseif (strlen($password) < 8) {
        $error = "Password must contain at least 8 characters.";
    }
    elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    }
    else {

        $check = $conn->prepare(
            "SELECT user_id FROM users WHERE username = ? OR email = ?"
        );

        $check->bind_param("ss", $username, $email);
        $check->execute();

        $result = $check->get_result();

        if ($result->num_rows > 0) {

            $error = "Username or email already exists.";

        } else {

            try {

                $conn->begin_transaction();

                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $conn->prepare(
                    "INSERT INTO users (username, email, password_hash)
                     VALUES (?, ?, ?)"
                );

                $stmt->bind_param(
                    "sss",
                    $username,
                    $email,
                    $password_hash
                );

                $stmt->execute();

                $user_id = $conn->insert_id;

                $qr_token = bin2hex(random_bytes(16));

                $profile = $conn->prepare(
                    "INSERT INTO patient_profiles
                    (user_id, full_name, dob, gender, blood_group, qr_token)
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                $profile->bind_param(
                    "isssss",
                    $user_id,
                    $full_name,
                    $dob,
                    $gender,
                    $blood_group,
                    $qr_token
                );

                $profile->execute();

                $conn->commit();

                header("Location: login.php?registered=success");
                exit();

            } catch (Exception $e) {

                $conn->rollback();

                $error = "Registration failed. Please try again.";

            }
        }

        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Register - Emergency Medical ID</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .register-wrapper {
            max-width: 650px;
            margin: 40px auto;
        }

        .register-heading {
            text-align: center;
            margin-bottom: 25px;
        }

        .register-heading h1 {
            color: #1E40AF;
        }

        .register-heading p {
            color: #64748B;
        }

        .register-form-card {
            background: #FFFFFF;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.10);
            border: 1px solid #BFDBFE;
        }

        .register-form-card h2 {
            text-align: center;
            margin-bottom: 25px;
        }

        .register-form-card .register-btn {
            width: 100%;
            margin-top: 10px;
        }

        @media (max-width: 600px) {
            .register-form-card {
                padding: 20px;
            }
        }

        
.password-wrapper {
    position: relative;
    width: 100%;
}

.password-wrapper input {
    width: 100%;
    padding-right: 45px;
    box-sizing: border-box;
}

.toggle-password {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 18px;
    padding: 5px;
}

    </style>

</head>

<body>

    <!-- Navigation Bar -->
    <nav class="navbar">

        <h2>Emergency Medical ID</h2>

        <div>
            <a href="index.php">Home</a>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        </div>

    </nav>


    <div class="container">

        <div class="register-wrapper">

            <!-- Page Heading -->
            <div class="register-heading">

                <h1>Create Your Account</h1>

                <p>
                    Register to manage your emergency medical information.
                </p>

            </div>


            <!-- Registration Form -->
            <div class="register-form-card">

                <h2>Registration Form</h2>


                <?php if (!empty($error)) { ?>

                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($error); ?>
                    </div>

                <?php } ?>


                <form method="POST" action="register.php">

                    <div class="form-group">

                        <label>Full Name</label>

                        <input
                            type="text"
                            name="full_name"
                            placeholder="Enter your full name"
                            value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>Username</label>

                        <input
                            type="text"
                            name="username"
                            placeholder="Enter username"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>Email Address</label>

                        <input
                            type="email"
                            name="email"
                            placeholder="Enter your email"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>Date of Birth</label>

                        <input
                            type="date"
                            name="dob"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>Gender</label>

                        <select name="gender" required>

                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>Blood Group</label>

                        <select name="blood_group" required>

                            <option value="">Select Blood Group</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                            <option value="Unknown">Unknown</option>

                        </select>

                    </div>


                    
<div class="form-group">

    <label>Password</label>

    <div class="password-wrapper">
        <input
            type="password"
            name="password"
            id="password"
            placeholder="Create a password"
            minlength="8"
            required
        >

        <button type="button"
                class="toggle-password"
                onclick="togglePassword('password', this)"
                aria-label="Show password">
            👁
        </button>
    </div>

</div>


<div class="form-group">

    <label>Confirm Password</label>

    <div class="password-wrapper">
        <input
            type="password"
            name="confirm_password"
            id="confirm_password"
            placeholder="Confirm your password"
            minlength="8"
            required
        >

        <button type="button"
                class="toggle-password"
                onclick="togglePassword('confirm_password', this)"
                aria-label="Show confirm password">
            👁
        </button>
    </div>

</div>



                    <button type="submit" class="register-btn">
                        Create Account
                    </button>

                </form>


                <div class="form-link">

                    <p>
                        Already have an account?
                        <a href="login.php">Login Here</a>
                    </p>

                </div>

            </div>

        </div>

    </div>


    <!-- Footer -->
    <footer class="footer">

        <p>
            &copy; 2026 Emergency Medical ID & Health Record System.
            All Rights Reserved.
        </p>

    </footer>

    
<script>
document.addEventListener("DOMContentLoaded", function () {

    const form = document.querySelector("form");
    const password = document.querySelector('input[name="password"]');

    if (!form || !password) return;

    form.addEventListener("submit", function (event) {

        const value = password.value;

        const hasUppercase = /[A-Z]/.test(value);
        const hasLowercase = /[a-z]/.test(value);
        const hasNumber = /[0-9]/.test(value);
        const hasSpecial = /[^A-Za-z0-9]/.test(value);
        const hasLength = value.length >= 8;

        let errors = [];

        if (!hasLength) {
            errors.push("At least 8 characters required.");
        }

        if (!hasUppercase) {
            errors.push("At least one Uppercase letter (A-Z) required.");
        }

        if (!hasLowercase) {
            errors.push("At least one Lowercase letter (a-z) required.");
        }

        if (!hasNumber) {
            errors.push("At least one Number (0-9) required.");
        }

        if (!hasSpecial) {
            errors.push("At least one Special character (@, #, $, !) required.");
        }

        if (errors.length > 0) {
            event.preventDefault();

            alert(
                "Please enter a Strong Password:\n\n" +
                errors.join("\n")
            );

            password.focus();
            return;
        }

        const confirmPassword =
            document.querySelector('input[name="confirm_password"]');

        if (confirmPassword && value !== confirmPassword.value) {
            event.preventDefault();

            alert("Password and Confirm Password do not match!");

            confirmPassword.focus();
        }

    });

});
</script>

<script>
    
function togglePassword(inputId, button) {

    const passwordInput = document.getElementById(inputId);

    if (passwordInput.type === "password") {

        passwordInput.type = "text";
        button.textContent = "🙈";
        button.setAttribute("aria-label", "Hide password");

    } else {

        passwordInput.type = "password";
        button.textContent = "👁";
        button.setAttribute("aria-label", "Show password");

    }
}

</script>

    

</body>

</html>
