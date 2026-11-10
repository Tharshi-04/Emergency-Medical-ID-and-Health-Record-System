<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check hospital role
$roleQuery = $conn->prepare(
    "SELECT role FROM user_roles WHERE user_id = ? AND role = 'hospital'"
);
$roleQuery->bind_param("i", $user_id);
$roleQuery->execute();

if ($roleQuery->get_result()->num_rows === 0) {
    die("Access denied. Hospital account required.");
}

// Get existing hospital profile
$query = $conn->prepare(
    "SELECT * FROM hospital_profiles WHERE user_id = ?"
);
$query->bind_param("i", $user_id);
$query->execute();
$hospital = $query->get_result()->fetch_assoc();

$success = "";
$error = "";

// Save profile
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $hospital_name = trim($_POST['hospital_name'] ?? '');
    $registration_number = trim($_POST['registration_number'] ?? '');
    $official_email = trim($_POST['official_email'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $district = trim($_POST['district'] ?? '');

    if (
        $hospital_name === '' ||
        $official_email === '' ||
        $contact_number === '' ||
        $address === '' ||
        $district === ''
    ) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($official_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid official email address.";
    } else {

        if ($hospital) {
            // Update existing profile without changing verification status
            $update = $conn->prepare(
                "UPDATE hospital_profiles
                 SET hospital_name = ?,
                     registration_number = ?,
                     official_email = ?,
                     contact_number = ?,
                     address = ?,
                     district = ?
                 WHERE user_id = ?"
            );

            $update->bind_param(
                "ssssssi",
                $hospital_name,
                $registration_number,
                $official_email,
                $contact_number,
                $address,
                $district,
                $user_id
            );

            if ($update->execute()) {
                $success = "Hospital profile updated successfully.";
            } else {
                $error = "Unable to update hospital profile.";
            }

        } else {
            // Create new profile; verification status defaults to pending
            $insert = $conn->prepare(
                "INSERT INTO hospital_profiles
                 (user_id, hospital_name, registration_number,
                  official_email, contact_number, address, district)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $insert->bind_param(
                "issssss",
                $user_id,
                $hospital_name,
                $registration_number,
                $official_email,
                $contact_number,
                $address,
                $district
            );

            if ($insert->execute()) {
                $success = "Hospital profile submitted successfully. Waiting for admin verification.";
            } else {
                $error = "Unable to save hospital profile.";
            }
        }

        // Reload saved profile
        $query = $conn->prepare(
            "SELECT * FROM hospital_profiles WHERE user_id = ?"
        );
        $query->bind_param("i", $user_id);
        $query->execute();
        $hospital = $query->get_result()->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Profile</title>
    <link rel="stylesheet" href="../css/style.css">

    <style>
        .profile-container {
            max-width: 750px;
            margin: 40px auto;
            padding: 20px;
        }

        .profile-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.07);
            border: 1px solid #E2E8F0;
        }

        .profile-card h1 {
            color: #123B63;
            margin-bottom: 10px;
        }

        .profile-card .description {
            color: #718096;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 7px;
            color: #263746;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #CBD5E0;
            border-radius: 7px;
            font-size: 15px;
            box-sizing: border-box;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .save-button {
            background: #123B63;
            color: white;
            border: none;
            padding: 12px 22px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 15px;
        }

        .save-button:hover {
            background: #0B2945;
        }

        .message {
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 18px;
        }

        .success {
            background: #E8F7EF;
            color: #167344;
        }

        .error {
            background: #FDECEC;
            color: #B42332;
        }

        .verification {
            background: #FFF7E6;
            color: #795313;
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>

<header class="main-header">
    <div class="header-container">
        <h2>Emergency Medical ID</h2>

        <nav class="nav-menu">
            <a href="hospital_dashboard.php">Dashboard</a>
            <a href="hospital_profile.php">Hospital Profile</a>
            <a href="create_request.php">Create Request</a>
            <a href="hospital_requests.php">My Requests</a>
            <a href="../logout.php">Logout</a>
        </nav>
    </div>
</header>

<main class="profile-container">

    <div class="profile-card">

        <h1>Hospital Profile</h1>
        <p class="description">
            Enter your hospital's official information.
        </p>

        <?php if ($success): ?>
            <div class="message success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($hospital): ?>
            <div class="verification">
                Verification Status:
                <strong>
                    <?php echo htmlspecialchars(ucfirst($hospital['verification_status'])); ?>
                </strong>
            </div>
        <?php endif; ?>

        <form method="POST" action="">

            <div class="form-group">
                <label>Hospital Name *</label>
                <input
                    type="text"
                    name="hospital_name"
                    value="<?php echo htmlspecialchars($hospital['hospital_name'] ?? ''); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Registration Number</label>
                <input
                    type="text"
                    name="registration_number"
                    value="<?php echo htmlspecialchars($hospital['registration_number'] ?? ''); ?>"
                >
            </div>

            <div class="form-group">
                <label>Official Email *</label>
                <input
                    type="email"
                    name="official_email"
                    value="<?php echo htmlspecialchars($hospital['official_email'] ?? ''); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Contact Number *</label>
                <input
                    type="text"
                    name="contact_number"
                    value="<?php echo htmlspecialchars($hospital['contact_number'] ?? ''); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Hospital Address *</label>
                <textarea name="address" required><?php
                    echo htmlspecialchars($hospital['address'] ?? '');
                ?></textarea>
            </div>

            <div class="form-group">
                <label>District *</label>
                <select name="district" required>
                    <option value="">Select District</option>
                    <?php
                    $districts = [
                        "Ampara", "Anuradhapura", "Badulla", "Batticaloa",
                        "Colombo", "Galle", "Gampaha", "Hambantota",
                        "Jaffna", "Kalutara", "Kandy", "Kegalle",
                        "Kilinochchi", "Kurunegala", "Mannar",
                        "Matale", "Matara", "Monaragala", "Mullaitivu",
                        "Nuwara Eliya", "Polonnaruwa", "Puttalam",
                        "Ratnapura", "Trincomalee", "Vavuniya"
                    ];

                    foreach ($districts as $district):
                        $selected = (
                            ($hospital['district'] ?? '') === $district
                        ) ? 'selected' : '';
                    ?>
                        <option value="<?php echo htmlspecialchars($district); ?>"
                            <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($district); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="save-button">
                Save Hospital Profile
            </button>

        </form>

    </div>

</main>

<footer class="main-footer">
    <p>© <?php echo date("Y"); ?> Emergency Medical ID System</p>
</footer>

</body>
</html>