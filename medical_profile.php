
<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];
$message = "";

// Get patient profile
$stmt = $conn->prepare(
    "SELECT profile_id, full_name, dob, gender, blood_group,
            height_cm, weight_kg
     FROM patient_profiles
     WHERE user_id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile) {
    die("Medical profile not found. Please contact the administrator.");
}

$patient_id = (int)$profile["profile_id"];

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    // UPDATE PERSONAL DETAILS
    if ($action === "update_profile") {

        $full_name = trim($_POST["full_name"] ?? "");
        $dob = !empty($_POST["dob"]) ? $_POST["dob"] : null;
        $gender = $_POST["gender"] ?? "";
        $blood_group = $_POST["blood_group"] ?? "";

        $height = ($_POST["height_cm"] ?? "") !== ""
            ? (float)$_POST["height_cm"] : null;

        $weight = ($_POST["weight_kg"] ?? "") !== ""
            ? (float)$_POST["weight_kg"] : null;

        if ($full_name === "") {
            $message = "Full name is required.";
        } elseif (
            ($height !== null && ($height <= 0 || $height > 300)) ||
            ($weight !== null && ($weight <= 0 || $weight > 700))
        ) {
            $message = "Please enter valid height and weight.";
        } else {

            $stmt = $conn->prepare(
                "UPDATE patient_profiles
                 SET full_name = ?, dob = ?, gender = ?,
                     blood_group = ?, height_cm = ?, weight_kg = ?
                 WHERE profile_id = ? AND user_id = ?"
            );

            $stmt->bind_param(
                "ssssddii",
                $full_name,
                $dob,
                $gender,
                $blood_group,
                $height,
                $weight,
                $patient_id,
                $user_id
            );

            $message = $stmt->execute()
                ? "Personal details updated successfully."
                : "Unable to update personal details.";

            $stmt->close();
        }
    }

    // ADD MEDICAL CONDITION
    elseif ($action === "add_condition") {

        $condition_name = trim($_POST["condition_name"] ?? "");
        $diagnosed_year = ($_POST["diagnosed_year"] ?? "") !== ""
            ? (int)$_POST["diagnosed_year"] : null;
        $notes = trim($_POST["notes"] ?? "");

        if ($condition_name === "") {
            $message = "Please enter the condition name.";
        } elseif (
            $diagnosed_year !== null &&
            ($diagnosed_year < 1900 || $diagnosed_year > (int)date("Y"))
        ) {
            $message = "Please enter a valid diagnosed year.";
        } else {

            $stmt = $conn->prepare(
                "INSERT INTO medical_conditions
                 (patient_id, condition_name, diagnosed_year, notes)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "isis",
                $patient_id,
                $condition_name,
                $diagnosed_year,
                $notes
            );

            $message = $stmt->execute()
                ? "Medical condition added successfully."
                : "Unable to add medical condition.";

            $stmt->close();
        }
    }

    // UPDATE MEDICAL CONDITION
    elseif ($action === "update_condition") {

        $condition_id = (int)($_POST["condition_id"] ?? 0);
        $condition_name = trim($_POST["condition_name"] ?? "");
        $diagnosed_year = ($_POST["diagnosed_year"] ?? "") !== ""
            ? (int)$_POST["diagnosed_year"] : null;
        $notes = trim($_POST["notes"] ?? "");

        if ($condition_id <= 0 || $condition_name === "") {
            $message = "Please enter a valid condition.";
        } elseif (
            $diagnosed_year !== null &&
            ($diagnosed_year < 1900 || $diagnosed_year > (int)date("Y"))
        ) {
            $message = "Please enter a valid diagnosed year.";
        } else {

            $stmt = $conn->prepare(
                "UPDATE medical_conditions
                 SET condition_name = ?, diagnosed_year = ?, notes = ?
                 WHERE condition_id = ? AND patient_id = ?"
            );

            $stmt->bind_param(
                "sisii",
                $condition_name,
                $diagnosed_year,
                $notes,
                $condition_id,
                $patient_id
            );

            $message = $stmt->execute() && $stmt->affected_rows >= 0
                ? "Medical condition updated successfully."
                : "Unable to update medical condition.";

            $stmt->close();
        }
    }

    // DELETE MEDICAL CONDITION
    elseif ($action === "delete_condition") {

        $condition_id = (int)($_POST["condition_id"] ?? 0);

        $stmt = $conn->prepare(
            "DELETE FROM medical_conditions
             WHERE condition_id = ? AND patient_id = ?"
        );

        $stmt->bind_param("ii", $condition_id, $patient_id);
        $stmt->execute();

        $message = $stmt->affected_rows > 0
            ? "Medical condition deleted successfully."
            : "Medical condition not found.";

        $stmt->close();
    }

    // ADD ALLERGY
    elseif ($action === "add_allergy") {

        $allergen_name = trim($_POST["allergen_name"] ?? "");
        $severity = $_POST["severity"] ?? "";
        $reaction_notes = trim($_POST["reaction_notes"] ?? "");

        if ($allergen_name === "") {
            $message = "Please enter the allergen name.";
        } else {

            $stmt = $conn->prepare(
                "INSERT INTO allergies
                 (patient_id, allergen_name, severity, reaction_notes)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "isss",
                $patient_id,
                $allergen_name,
                $severity,
                $reaction_notes
            );

            $message = $stmt->execute()
                ? "Allergy added successfully."
                : "Unable to add allergy.";

            $stmt->close();
        }
    }

    // UPDATE ALLERGY
    elseif ($action === "update_allergy") {

        $allergy_id = (int)($_POST["allergy_id"] ?? 0);
        $allergen_name = trim($_POST["allergen_name"] ?? "");
        $severity = $_POST["severity"] ?? "";
        $reaction_notes = trim($_POST["reaction_notes"] ?? "");

        if ($allergy_id <= 0 || $allergen_name === "") {
            $message = "Please enter a valid allergen name.";
        } else {

            $stmt = $conn->prepare(
                "UPDATE allergies
                 SET allergen_name = ?, severity = ?, reaction_notes = ?
                 WHERE allergy_id = ? AND patient_id = ?"
            );

            $stmt->bind_param(
                "sssii",
                $allergen_name,
                $severity,
                $reaction_notes,
                $allergy_id,
                $patient_id
            );

            $message = $stmt->execute()
                ? "Allergy updated successfully."
                : "Unable to update allergy.";

            $stmt->close();
        }
    }

    // DELETE ALLERGY
    elseif ($action === "delete_allergy") {

        $allergy_id = (int)($_POST["allergy_id"] ?? 0);

        $stmt = $conn->prepare(
            "DELETE FROM allergies
             WHERE allergy_id = ? AND patient_id = ?"
        );

        $stmt->bind_param("ii", $allergy_id, $patient_id);
        $stmt->execute();

        $message = $stmt->affected_rows > 0
            ? "Allergy deleted successfully."
            : "Allergy not found.";

        $stmt->close();
    }

    $_SESSION["profile_message"] = $message;

    header("Location: medical_profile.php");
    exit();
}

// Show message after form submission
if (isset($_SESSION["profile_message"])) {
    $message = $_SESSION["profile_message"];
    unset($_SESSION["profile_message"]);
}

// Refresh personal profile
$stmt = $conn->prepare(
    "SELECT profile_id, full_name, dob, gender, blood_group,
            height_cm, weight_kg
     FROM patient_profiles
     WHERE user_id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// READ MEDICAL CONDITIONS
$stmt = $conn->prepare(
    "SELECT condition_id, condition_name, diagnosed_year, notes
     FROM medical_conditions
     WHERE patient_id = ?
     ORDER BY condition_id DESC"
);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$conditions = $stmt->get_result();

// READ ALLERGIES
$stmt = $conn->prepare(
    "SELECT allergy_id, allergen_name, severity, reaction_notes
     FROM allergies
     WHERE patient_id = ?
     ORDER BY allergy_id DESC"
);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$allergies = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Medical Profile - Emergency Medical ID</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .profile-section {
            background: #FFFFFF;
            padding: 25px;
            margin: 25px 0;
            border: 1px solid #BFDBFE;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(59,130,246,0.08);
        }

        .profile-section h2 {
            color: #1E40AF;
            margin-bottom: 20px;
        }

        .profile-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
        }

        .form-field {
            margin-bottom: 12px;
        }

        .form-field label {
            display: block;
            margin-bottom: 7px;
            color: #334155;
            font-weight: bold;
        }

        .form-field input,
        .form-field select,
        .form-field textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #BFDBFE;
            border-radius: 6px;
            box-sizing: border-box;
        }

        .record-item {
            border: 1px solid #BFDBFE;
            border-radius: 8px;
            padding: 18px;
            margin: 15px 0;
            background: #EFF6FF;
        }

        .record-item h3 {
            color: #0F766E;
        }

        .record-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .delete-btn {
            background: #DC2626;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
        }

        .delete-btn:hover {
            background: #B91C1C;
        }

        .message {
            background: #DBEAFE;
            color: #1E40AF;
            padding: 12px;
            border-radius: 7px;
            margin: 15px 0;
        }

        .empty-message {
            color: #64748B;
            padding: 10px 0;
        }
    </style>
</head>

<body>

<nav class="navbar">
    <h2>Emergency Medical ID</h2>

    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="medical_profile.php">Medical Profile</a>
        <a href="contacts.php">Contacts</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <h1>My Medical Profile</h1>

    <?php if ($message !== ""): ?>
        <div class="message">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>


    <!-- SECTION 1: PERSONAL DETAILS -->

    <section class="profile-section">

        <h2>1. Personal Details</h2>

        <form method="POST" action="medical_profile.php">

            <input type="hidden" name="action" value="update_profile">

            <div class="profile-form">

                <div class="form-field">
                    <label>Full Name</label>
                    <input type="text" name="full_name"
                        value="<?php echo htmlspecialchars($profile["full_name"]); ?>"
                        required>
                </div>

                <div class="form-field">
                    <label>Date of Birth</label>
                    <input type="date" name="dob"
                        value="<?php echo htmlspecialchars($profile["dob"] ?? ""); ?>">
                </div>

                <div class="form-field">
                    <label>Gender</label>
                    <select name="gender">
                        <option value="">Select Gender</option>
                        <option value="Male" <?php if ($profile["gender"] === "Male") echo "selected"; ?>>Male</option>
                        <option value="Female" <?php if ($profile["gender"] === "Female") echo "selected"; ?>>Female</option>
                        <option value="Other" <?php if ($profile["gender"] === "Other") echo "selected"; ?>>Other</option>
                    </select>
                </div>

                <div class="form-field">
                    <label>Blood Group</label>
                    <select name="blood_group">
                        <option value="">Select Blood Group</option>

                        <?php
                        $groups = ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"];
                        foreach ($groups as $group):
                        ?>
                            <option value="<?php echo $group; ?>"
                                <?php if ($profile["blood_group"] === $group) echo "selected"; ?>>
                                <?php echo $group; ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-field">
                    <label>Height (cm)</label>
                    <input type="number" name="height_cm"
                        step="0.01" min="1" max="300"
                        value="<?php echo htmlspecialchars($profile["height_cm"] ?? ""); ?>">
                </div>

                <div class="form-field">
                    <label>Weight (kg)</label>
                    <input type="number" name="weight_kg"
                        step="0.01" min="1" max="700"
                        value="<?php echo htmlspecialchars($profile["weight_kg"] ?? ""); ?>">
                </div>

            </div>

            <button type="submit" class="btn">
                Update Personal Details
            </button>

        </form>

    </section>


    <!-- SECTION 2: MEDICAL HISTORY / CONDITIONS -->

    <section class="profile-section">

        <h2>2. Medical History / Chronic Conditions</h2>

        <h3>Add Medical Condition</h3>

        <form method="POST" action="medical_profile.php">

            <input type="hidden" name="action" value="add_condition">

            <div class="profile-form">

                <div class="form-field">
                    <label>Condition Name</label>
                    <input type="text" name="condition_name"
                        placeholder="Example: Asthma" required>
                </div>

                <div class="form-field">
                    <label>Diagnosed Year</label>
                    <input type="number" name="diagnosed_year"
                        min="1900" max="<?php echo date("Y"); ?>"
                        placeholder="Example: 2022">
                </div>

                <div class="form-field">
                    <label>Medical Notes</label>
                    <textarea name="notes" rows="3"
                        placeholder="Additional medical information"></textarea>
                </div>

            </div>

            <button type="submit" class="btn">
                Add Medical Condition
            </button>

        </form>

        <hr>

        <h3>Saved Medical Conditions</h3>

        <?php if ($conditions->num_rows > 0): ?>

            <?php while ($condition = $conditions->fetch_assoc()): ?>

                <div class="record-item">

                    <form method="POST" action="medical_profile.php">

                        <input type="hidden" name="action"
                            value="update_condition">

                        <input type="hidden" name="condition_id"
                            value="<?php echo (int)$condition["condition_id"]; ?>">

                        <div class="profile-form">

                            <div class="form-field">
                                <label>Condition Name</label>
                                <input type="text" name="condition_name"
                                    value="<?php echo htmlspecialchars($condition["condition_name"]); ?>"
                                    required>
                            </div>

                            <div class="form-field">
                                <label>Diagnosed Year</label>
                                <input type="number" name="diagnosed_year"
                                    min="1900" max="<?php echo date("Y"); ?>"
                                    value="<?php echo htmlspecialchars($condition["diagnosed_year"] ?? ""); ?>">
                            </div>

                            <div class="form-field">
                                <label>Medical Notes</label>
                                <textarea name="notes" rows="3"><?php echo htmlspecialchars($condition["notes"] ?? ""); ?></textarea>
                            </div>

                        </div>

                        <button type="submit" class="btn">
                            Update Condition
                        </button>

                    </form>

                    <form method="POST" action="medical_profile.php"
                        onsubmit="return confirm('Delete this medical condition?');">

                        <input type="hidden" name="action"
                            value="delete_condition">

                        <input type="hidden" name="condition_id"
                            value="<?php echo (int)$condition["condition_id"]; ?>">

                        <div class="record-actions">
                            <button type="submit" class="delete-btn">
                                Delete Condition
                            </button>
                        </div>

                    </form>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <p class="empty-message">No medical conditions added yet.</p>

        <?php endif; ?>

    </section>


    <!-- SECTION 3: ALLERGIES -->

    <section class="profile-section">

        <h2>3. Allergies</h2>

        <h3>Add Allergy</h3>

        <form method="POST" action="medical_profile.php">

            <input type="hidden" name="action" value="add_allergy">

            <div class="profile-form">

                <div class="form-field">
                    <label>Allergen Name</label>
                    <input type="text" name="allergen_name"
                        placeholder="Example: Penicillin, Peanuts" required>
                </div>

                <div class="form-field">
                    <label>Severity</label>
                    <select name="severity">
                        <option value="">Select Severity</option>
                        <option value="Mild">Mild</option>
                        <option value="Moderate">Moderate</option>
                        <option value="Severe">Severe</option>
                    </select>
                </div>

                <div class="form-field">
                    <label>Reaction Notes</label>
                    <textarea name="reaction_notes" rows="3"
                        placeholder="Example: Skin rash, swelling, breathing difficulty"></textarea>
                </div>

            </div>

            <button type="submit" class="btn">
                Add Allergy
            </button>

        </form>

        <hr>

        <h3>Saved Allergies</h3>

        <?php if ($allergies->num_rows > 0): ?>

            <?php while ($allergy = $allergies->fetch_assoc()): ?>

                <div class="record-item">

                    <form method="POST" action="medical_profile.php">

                        <input type="hidden" name="action"
                            value="update_allergy">

                        <input type="hidden" name="allergy_id"
                            value="<?php echo (int)$allergy["allergy_id"]; ?>">

                        <div class="profile-form">

                            <div class="form-field">
                                <label>Allergen Name</label>
                                <input type="text" name="allergen_name"
                                    value="<?php echo htmlspecialchars($allergy["allergen_name"]); ?>"
                                    required>
                            </div>

                            <div class="form-field">
                                <label>Severity</label>
                                <select name="severity">
                                    <option value="">Select Severity</option>

                                    <?php
                                    $severities = ["Mild", "Moderate", "Severe"];
                                    foreach ($severities as $severity):
                                    ?>
                                        <option value="<?php echo $severity; ?>"
                                            <?php if ($allergy["severity"] === $severity) echo "selected"; ?>>
                                            <?php echo $severity; ?>
                                        </option>
                                    <?php endforeach; ?>

                                </select>
                            </div>

                            <div class="form-field">
                                <label>Reaction Notes</label>
                                <textarea name="reaction_notes" rows="3"><?php echo htmlspecialchars($allergy["reaction_notes"] ?? ""); ?></textarea>
                            </div>

                        </div>

                        <button type="submit" class="btn">
                            Update Allergy
                        </button>

                    </form>

                    <form method="POST" action="medical_profile.php"
                        onsubmit="return confirm('Delete this allergy?');">

                        <input type="hidden" name="action"
                            value="delete_allergy">

                        <input type="hidden" name="allergy_id"
                            value="<?php echo (int)$allergy["allergy_id"]; ?>">

                        <div class="record-actions">
                            <button type="submit" class="delete-btn">
                                Delete Allergy
                            </button>
                        </div>

                    </form>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <p class="empty-message">No allergies added yet.</p>

        <?php endif; ?>

    </section>

</div>

<footer class="footer">
    <p>
        &copy; 2026 Emergency Medical ID & Health Record System.
        All Rights Reserved.
    </p>
</footer>

</body>
</html>

<?php
$stmt->close();
?>
