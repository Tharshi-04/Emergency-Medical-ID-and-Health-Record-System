
<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'donor') {
    header("Location: ../login.php");
    exit();
}

require_once "../config/db.php";

$user_id = $_SESSION['user_id'];
$message = "";
$message_type = "";

// Get existing donor profile
$stmt = $conn->prepare(
    "SELECT donor_id, full_name, dob, gender, phone_number,
            blood_group, district
     FROM donor_profiles
     WHERE user_id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$donor = $stmt->get_result()->fetch_assoc();
$stmt->close();

$donor_id = $donor['donor_id'] ?? null;

// Get existing donation preferences
$registry = null;

if ($donor_id) {
    $stmt = $conn->prepare(
        "SELECT is_blood_donor, is_organ_donor, district,
                last_donated_date, share_contact_on_accept, is_active
         FROM donor_registry
         WHERE donor_id = ?"
    );
    $stmt->bind_param("i", $donor_id);
    $stmt->execute();
    $registry = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Save donor profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {

    $full_name = trim($_POST['full_name'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $blood_group = trim($_POST['blood_group'] ?? '');
    $district = trim($_POST['district'] ?? '');

    $is_blood_donor = isset($_POST['is_blood_donor']) ? 1 : 0;
    $is_organ_donor = isset($_POST['is_organ_donor']) ? 1 : 0;
    $share_contact = isset($_POST['share_contact']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $last_donated_date = trim($_POST['last_donated_date'] ?? '');

    $valid_blood_groups = [
        '', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'
    ];

    $valid_genders = ['', 'Male', 'Female', 'Other'];

    if ($full_name === '' || strlen($full_name) > 150) {
        $message = "Please enter a valid full name.";
        $message_type = "error";
    } elseif (!in_array($blood_group, $valid_blood_groups, true)) {
        $message = "Please select a valid blood group.";
        $message_type = "error";
    } elseif (!in_array($gender, $valid_genders, true)) {
        $message = "Please select a valid gender.";
        $message_type = "error";
    } elseif (
        ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) ||
        ($last_donated_date !== '' &&
         !preg_match('/^\d{4}-\d{2}-\d{2}$/', $last_donated_date))
    ) {
        $message = "Please enter valid dates.";
        $message_type = "error";
    } elseif ($district !== '' && strlen($district) > 100) {
        $message = "District name is too long.";
        $message_type = "error";
    } else {

        $dob_value = $dob !== '' ? $dob : null;
        $last_date_value = $last_donated_date !== ''
            ? $last_donated_date
            : null;

        $conn->begin_transaction();

        try {

            if ($donor) {

                $stmt = $conn->prepare(
                    "UPDATE donor_profiles
                     SET full_name = ?, dob = ?, gender = ?,
                         phone_number = ?, blood_group = ?, district = ?
                     WHERE user_id = ?"
                );

                $stmt->bind_param(
                    "ssssssi",
                    $full_name,
                    $dob_value,
                    $gender,
                    $phone_number,
                    $blood_group,
                    $district,
                    $user_id
                );

                $stmt->execute();
                $stmt->close();

                $donor_id = (int) $donor['donor_id'];

            } else {

                $stmt = $conn->prepare(
                    "INSERT INTO donor_profiles
                     (user_id, full_name, dob, gender, phone_number,
                      blood_group, district)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "issssss",
                    $user_id,
                    $full_name,
                    $dob_value,
                    $gender,
                    $phone_number,
                    $blood_group,
                    $district
                );

                $stmt->execute();
                $donor_id = $conn->insert_id;
                $stmt->close();
            }

            // Save donation preferences
            $stmt = $conn->prepare(
                "INSERT INTO donor_registry
                 (donor_id, is_blood_donor, is_organ_donor, district,
                  last_donated_date, share_contact_on_accept, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    is_blood_donor = VALUES(is_blood_donor),
                    is_organ_donor = VALUES(is_organ_donor),
                    district = VALUES(district),
                    last_donated_date = VALUES(last_donated_date),
                    share_contact_on_accept = VALUES(share_contact_on_accept),
                    is_active = VALUES(is_active)"
            );

            $stmt->bind_param(
                "iiissii",
                $donor_id,
                $is_blood_donor,
                $is_organ_donor,
                $district,
                $last_date_value,
                $share_contact,
                $is_active
            );

            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $message = "Donor profile saved successfully.";
            $message_type = "success";

            // Refresh profile information
            $stmt = $conn->prepare(
                "SELECT donor_id, full_name, dob, gender, phone_number,
                        blood_group, district
                 FROM donor_profiles
                 WHERE user_id = ?"
            );
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $donor = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $donor_id = $donor['donor_id'];

            $stmt = $conn->prepare(
                "SELECT is_blood_donor, is_organ_donor, district,
                        last_donated_date, share_contact_on_accept, is_active
                 FROM donor_registry
                 WHERE donor_id = ?"
            );
            $stmt->bind_param("i", $donor_id);
            $stmt->execute();
            $registry = $stmt->get_result()->fetch_assoc();
            $stmt->close();

        } catch (Exception $e) {

            $conn->rollback();

            $message = "Unable to save the profile. Please try again.";
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Profile</title>
    <link rel="stylesheet" href="../css/style.css">

    <style>
        .profile-container {
            max-width: 850px;
            margin: 40px auto;
            padding: 20px;
        }

        .profile-card {
            background: white;
            padding: 28px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            margin-bottom: 25px;
        }

        .profile-card h2 {
            color: #123B63;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 17px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            color: #263746;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d5dce5;
            border-radius: 7px;
            box-sizing: border-box;
        }

        .preference-option {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
        }

        .preference-option input {
            width: auto;
        }

        .message {
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
        }

        .save-btn {
            background: #123B63;
            color: white;
            border: none;
            padding: 12px 22px;
            border-radius: 7px;
            cursor: pointer;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #123B63;
            text-decoration: none;
            font-weight: bold;
        }

        @media (max-width: 600px) {
            .profile-container {
                padding: 12px;
            }
        }
    </style>
</head>

<body>

<header class="main-header">
    <div class="header-container">
        <h2>Emergency Medical ID</h2>

        <nav class="nav-menu">
            <a href="donor_dashboard.php">Dashboard</a>
            <a href="donor_profile.php">My Profile</a>
            <a href="donor_requests.php">Donation Requests</a>
            <a href="../logout.php">Logout</a>
        </nav>
    </div>
</header>

<main class="profile-container">

    <a href="donor_dashboard.php" class="back-link">
        ← Back to Dashboard
    </a>

    <h1>Donor Profile</h1>
    <p>Complete your personal details and donation preferences.</p>

    <?php if ($message !== ""): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="donor_profile.php">

        <section class="profile-card">

            <h2>Personal Information</h2>

            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="full_name" maxlength="150"
                       value="<?php echo htmlspecialchars($donor['full_name'] ?? ''); ?>"
                       required>
            </div>

            <div class="form-group">
                <label>Date of Birth</label>
                <input type="date" name="dob"
                       value="<?php echo htmlspecialchars($donor['dob'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Gender</label>
                <select name="gender">
                    <option value="">Select Gender</option>
                    <?php foreach (['Male', 'Female', 'Other'] as $gender_option): ?>
                        <option value="<?php echo $gender_option; ?>"
                            <?php echo (($donor['gender'] ?? '') === $gender_option) ? 'selected' : ''; ?>>
                            <?php echo $gender_option; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone_number" maxlength="30"
                       value="<?php echo htmlspecialchars($donor['phone_number'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Blood Group</label>
                <select name="blood_group">
                    <option value="">Select Blood Group</option>
                    <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $group): ?>
                        <option value="<?php echo $group; ?>"
                            <?php echo (($donor['blood_group'] ?? '') === $group) ? 'selected' : ''; ?>>
                            <?php echo $group; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>District</label>
                <input type="text" name="district" maxlength="100"
                       placeholder="Enter your district"
                       value="<?php echo htmlspecialchars($donor['district'] ?? ''); ?>">
            </div>

        </section>

        <section class="profile-card">

            <h2>Donation Preferences</h2>

            <label class="preference-option">
                <input type="checkbox" name="is_blood_donor" value="1"
                    <?php echo !empty($registry['is_blood_donor']) ? 'checked' : ''; ?>>
                I am willing to donate blood.
            </label>

            <label class="preference-option">
                <input type="checkbox" name="is_organ_donor" value="1"
                    <?php echo !empty($registry['is_organ_donor']) ? 'checked' : ''; ?>>
                I am willing to register as an organ donor.
            </label>

            <div class="form-group">
                <label>Last Donated Date (if applicable)</label>
                <input type="date" name="last_donated_date"
                       value="<?php echo htmlspecialchars($registry['last_donated_date'] ?? ''); ?>">
            </div>

            <label class="preference-option">
                <input type="checkbox" name="share_contact" value="1"
                    <?php echo !empty($registry['share_contact_on_accept']) ? 'checked' : ''; ?>>
                Allow my contact details to be shared when I accept a request.
            </label>

            <label class="preference-option">
                <input type="checkbox" name="is_active" value="1"
                    <?php echo (!$registry || !empty($registry['is_active'])) ? 'checked' : ''; ?>>
                Keep my donor profile active.
            </label>

        </section>

        <button type="submit" name="save_profile" class="save-btn">
            Save Donor Profile
        </button>

    </form>

</main>

<footer class="main-footer">
    <p>© 2026 Emergency Medical ID System</p>
</footer>

</body>
</html>

<?php
$conn->close();
?>
