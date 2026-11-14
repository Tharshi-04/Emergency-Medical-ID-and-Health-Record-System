
<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "patient") {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];
$error = "";
$success = "";

$profile = [
    "full_name" => "",
    "phone_number" => "",
    "dob" => "",
    "gender" => "",
    "blood_group" => "",
    "height_cm" => "",
    "weight_kg" => ""
];

$conditions = [];
$diagnosed_years = [];
$condition_notes = [];
$medications = [];
$surgeries = [];
$allergies = [];
$profile_id = null;


// Load patient profile
function loadPatientProfile(
    $conn,
    $user_id,
    &$profile,
    &$conditions,
    &$diagnosed_years,
    &$condition_notes,
    &$medications,
    &$surgeries,
    &$allergies,
    &$profile_id
) {
    $stmt = $conn->prepare(
        "SELECT profile_id, full_name, phone_number, dob, gender, blood_group,
                height_cm, weight_kg
         FROM patient_profiles
         WHERE user_id = ?"
    );

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($data = $result->fetch_assoc()) {
        $profile_id = (int)$data["profile_id"];

        foreach ($profile as $key => $value) {
            $profile[$key] = $data[$key] ?? "";
        }
    }

    $stmt->close();

    if ($profile_id !== null) {

        // Load medical conditions and related details
        $stmt = $conn->prepare(
            "SELECT condition_name, diagnosed_year, notes, medications, surgeries
             FROM medical_conditions
             WHERE patient_id = ?
             ORDER BY condition_id ASC"
        );

        $stmt->bind_param("i", $profile_id);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $conditions[] = $row["condition_name"];
            $diagnosed_years[] = $row["diagnosed_year"] ?? "";
            $condition_notes[] = $row["notes"] ?? "";
            $medications[] = $row["medications"] ?? "";
            $surgeries[] = $row["surgeries"] ?? "";
        }

        $stmt->close();


        // Load allergies
        $stmt = $conn->prepare(
            "SELECT allergen_name
             FROM allergies
             WHERE patient_id = ?"
        );

        $stmt->bind_param("i", $profile_id);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $allergies[] = $row["allergen_name"];
        }

        $stmt->close();
    }
}


// Initial profile loading
loadPatientProfile(
    $conn,
    $user_id,
    $profile,
    $conditions,
    $diagnosed_years,
    $condition_notes,
    $medications,
    $surgeries,
    $allergies,
    $profile_id
);


// DELETE PROFILE
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_profile"])) {

    if ($profile_id === null) {
        $error = "No medical profile found to delete.";
    } else {

        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare(
                "DELETE FROM patient_profiles WHERE user_id = ?"
            );

            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                throw new Exception("Profile not found.");
            }

            $stmt->close();

            $conn->commit();

            $profile_id = null;

            $profile = [
                "full_name" => "",
                "phone_number" => "",
                "dob" => "",
                "gender" => "",
                "blood_group" => "",
                "height_cm" => "",
                "weight_kg" => ""
            ];

            $conditions = [];
            $diagnosed_years = [];
            $condition_notes = [];
            $medications = [];
            $surgeries = [];
            $allergies = [];

            $success = "Medical profile deleted successfully!";

        } catch (Throwable $e) {
            $conn->rollback();
            $error = "Unable to delete your profile. Please try again.";
        }
    }
}


// SAVE OR UPDATE PROFILE
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_profile"])) {

    $full_name = trim($_POST["full_name"] ?? "");
    $phone_number = trim($_POST["phone_number"] ?? "");
    $dob = $_POST["dob"] ?? "";
    $gender = $_POST["gender"] ?? "";
    $blood_group = $_POST["blood_group"] ?? "";
    $height = $_POST["height_cm"] ?? "";
    $weight = $_POST["weight_kg"] ?? "";

    // Read medical details while preserving line positions
    $condition_lines = explode("\n", str_replace("\r", "", $_POST["conditions"] ?? ""));
    $year_lines = explode("\n", str_replace("\r", "", $_POST["diagnosed_years"] ?? ""));
    $note_lines = explode("\n", str_replace("\r", "", $_POST["condition_notes"] ?? ""));
    $medication_lines = explode("\n", str_replace("\r", "", $_POST["medications"] ?? ""));
    $surgery_lines = explode("\n", str_replace("\r", "", $_POST["surgeries"] ?? ""));

    $conditions = [];
    $diagnosed_years = [];
    $condition_notes = [];
    $medications = [];
    $surgeries = [];

    foreach ($condition_lines as $i => $condition) {

        $condition = trim($condition);

        if ($condition === "") {
            continue;
        }

        $conditions[] = $condition;
        $diagnosed_years[] = trim($year_lines[$i] ?? "");
        $condition_notes[] = trim($note_lines[$i] ?? "");
        $medications[] = trim($medication_lines[$i] ?? "");
        $surgeries[] = trim($surgery_lines[$i] ?? "");
    }

    // Read allergies
    $allergies = array_values(array_filter(
        array_map("trim", explode("\n", str_replace("\r", "", $_POST["allergies"] ?? "")))
    ));

    $profile = [
        "full_name" => $full_name,
        "phone_number" => $phone_number,
        "dob" => $dob,
        "gender" => $gender,
        "blood_group" => $blood_group,
        "height_cm" => $height,
        "weight_kg" => $weight
    ];


    // Validation
    if ($full_name === "") {

        $error = "Please enter your full name.";

    } elseif (
        $phone_number !== "" &&
        !preg_match('/^[0-9+()\-\s]{7,30}$/', $phone_number)
    ) {

        $error = "Please enter a valid phone number.";

    } elseif ($dob !== "" && strtotime($dob) === false) {

        $error = "Please enter a valid date of birth.";

    } elseif (!in_array($gender, ["", "Male", "Female", "Other"], true)) {

        $error = "Please select a valid gender.";

    } elseif (!in_array($blood_group, ["", "A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"], true)) {

        $error = "Please select a valid blood group.";

    } elseif (
        ($height !== "" && (!is_numeric($height) || $height <= 0 || $height > 300)) ||
        ($weight !== "" && (!is_numeric($weight) || $weight <= 0 || $weight > 700))
    ) {

        $error = "Please enter valid height and weight values.";

    } else {

        // Validate diagnosed years
        foreach ($diagnosed_years as $year) {
            if (
                $year !== "" &&
                (!preg_match('/^\d{4}$/', $year) ||
                 (int)$year < 1000 ||
                 (int)$year > (int)date("Y"))
            ) {
                $error = "Please enter a valid diagnosed year.";
                break;
            }
        }

        if ($error === "") {

            $dob_value = $dob !== "" ? $dob : null;
            $gender_value = $gender !== "" ? $gender : null;
            $blood_value = $blood_group !== "" ? $blood_group : null;
            $height_value = $height !== "" ? (float)$height : null;
            $weight_value = $weight !== "" ? (float)$weight : null;

            try {

                $conn->begin_transaction();

                if ($profile_id === null) {

                    // Save new profile
                    $stmt = $conn->prepare(
                        "INSERT INTO patient_profiles
                        (user_id, full_name, phone_number, dob, gender, blood_group,
                         height_cm, weight_kg)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    );

                    $stmt->bind_param(
                        "isssssdd",
                        $user_id,
                        $full_name,
                        $phone_number,
                        $dob_value,
                        $gender_value,
                        $blood_value,
                        $height_value,
                        $weight_value
                    );

                    $stmt->execute();

                    $profile_id = $conn->insert_id;
                    $stmt->close();

                } else {

                    // Update existing profile
                    $stmt = $conn->prepare(
                        "UPDATE patient_profiles
                         SET full_name = ?, phone_number = ?, dob = ?, gender = ?,
                             blood_group = ?, height_cm = ?, weight_kg = ?
                         WHERE user_id = ?"
                    );

                    $stmt->bind_param(
                        "sssssddi",
                        $full_name,
                        $phone_number,
                        $dob_value,
                        $gender_value,
                        $blood_value,
                        $height_value,
                        $weight_value,
                        $user_id
                    );

                    $stmt->execute();
                    $stmt->close();
                }


                // Replace medical conditions and related details
                $stmt = $conn->prepare(
                    "DELETE FROM medical_conditions WHERE patient_id = ?"
                );

                $stmt->bind_param("i", $profile_id);
                $stmt->execute();
                $stmt->close();


                // Insert medical conditions with all related details
                $stmt = $conn->prepare(
                    "INSERT INTO medical_conditions
                    (patient_id, condition_name, diagnosed_year, notes, medications, surgeries)
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                foreach ($conditions as $i => $condition) {

                    $year = $diagnosed_years[$i] !== ""
                        ? $diagnosed_years[$i]
                        : null;

                    $note = $condition_notes[$i];
                    $medication = $medications[$i];
                    $surgery = $surgeries[$i];

                    $stmt->bind_param(
                        "isssss",
                        $profile_id,
                        $condition,
                        $year,
                        $note,
                        $medication,
                        $surgery
                    );

                    $stmt->execute();
                }

                $stmt->close();


                // Replace allergies
                $stmt = $conn->prepare(
                    "DELETE FROM allergies WHERE patient_id = ?"
                );

                $stmt->bind_param("i", $profile_id);
                $stmt->execute();
                $stmt->close();


                foreach ($allergies as $allergy) {

                    $severity = "Not specified";

                    $stmt = $conn->prepare(
                        "INSERT INTO allergies
                        (patient_id, allergen_name, severity)
                        VALUES (?, ?, ?)"
                    );

                    $stmt->bind_param(
                        "iss",
                        $profile_id,
                        $allergy,
                        $severity
                    );

                    $stmt->execute();
                    $stmt->close();
                }


                $conn->commit();

                // Reload saved profile details
                $conditions = [];
                $diagnosed_years = [];
                $condition_notes = [];
                $medications = [];
                $surgeries = [];
                $allergies = [];

                loadPatientProfile(
                    $conn,
                    $user_id,
                    $profile,
                    $conditions,
                    $diagnosed_years,
                    $condition_notes,
                    $medications,
                    $surgeries,
                    $allergies,
                    $profile_id
                );

                $success = "Medical profile saved successfully!";

            } catch (Throwable $e) {

                $conn->rollback();
                $error = "Unable to save your profile. Please try again.";
            }
        }
    }
}


// Prepare textarea values
$conditions_text = implode("\n", $conditions);
$diagnosed_years_text = implode("\n", $diagnosed_years);
$condition_notes_text = implode("\n", $condition_notes);
$medications_text = implode("\n", $medications);
$surgeries_text = implode("\n", $surgeries);
$allergies_text = implode("\n", $allergies);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Medical Profile | Medi Scan</title>

    <link rel="stylesheet" href="css/style.css">

    <style>

        .profile-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .profile-card {
            background: white;
            padding: 30px;
            border-radius: 14px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 4px 18px rgba(18,59,99,0.07);
            margin-bottom: 25px;
        }

        .profile-card h2 {
            color: #123B63;
            margin-bottom: 22px;
            font-size: 20px;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .profile-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .save-btn,
        .update-btn,
        .delete-btn {
            padding: 13px 25px;
            border-radius: 8px;
            border: none;
            font-weight: 700;
            cursor: pointer;
        }

        .save-btn {
            background: #16A6A1;
            color: white;
        }

        .update-btn {
            background: #123B63;
            color: white;
        }

        .delete-btn {
            background: #DC3545;
            color: white;
        }

        .save-btn:hover {
            background: #108580;
        }

        .update-btn:hover {
            background: #0B2945;
        }

        .delete-btn:hover {
            background: #B52A37;
        }

        .back-link {
            display: inline-block;
            padding: 13px 20px;
            border: 1px solid #123B63;
            color: #123B63;
            border-radius: 8px;
            font-weight: 600;
        }

        .help-text {
            color: #718096;
            font-size: 12px;
            margin-top: 6px;
        }

        .delete-section {
            margin-top: 30px;
            padding: 22px;
            background: #FFF5F5;
            border: 1px solid #F5C6CB;
            border-radius: 12px;
        }

        .delete-section h2 {
            color: #DC3545;
            margin-bottom: 10px;
        }

        .delete-section p {
            color: #718096;
            margin-bottom: 15px;
        }

        @media (max-width: 650px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }

            .profile-card {
                padding: 22px 17px;
            }
        }

    </style>
</head>

<body>

<header class="main-header">
    <div class="header-container">

        <a href="dashboard.php" class="logo">
            Medi <span>Scan</span>
        </a>

        <nav class="nav-menu">
            <a href="dashboard.php">Dashboard</a>
            <a href="medical_profile.php" class="active">Medical Profile</a>
            <a href="contacts.php">Emergency Contacts</a>
            <a href="logout.php">Logout</a>
        </nav>

    </div>
</header>


<main class="container">

    <div class="profile-container">

        <h1 class="page-title">My Medical Profile</h1>

        <p class="page-description">
            Add and manage your important medical information.
        </p>

        <?php if ($error !== ""): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>


        <form method="POST" action="medical_profile.php">

            <!-- PERSONAL INFORMATION -->
            <div class="profile-card">

                <h2>Personal Information</h2>

                <div class="profile-grid">

                    <div class="form-group">
                        <label for="full_name">Full Name *</label>

                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            value="<?php echo htmlspecialchars($profile["full_name"]); ?>"
                            placeholder="Enter your full name"
                            required
                        >
                    </div>


                    <div class="form-group">
                        <label for="phone_number">Phone Number</label>

                        <input
                            type="tel"
                            id="phone_number"
                            name="phone_number"
                            value="<?php echo htmlspecialchars($profile["phone_number"]); ?>"
                            placeholder="Enter your phone number"
                        >
                    </div>


                    <div class="form-group">
                        <label for="dob">Date of Birth</label>

                        <input
                            type="date"
                            id="dob"
                            name="dob"
                            value="<?php echo htmlspecialchars($profile["dob"] ?? ""); ?>"
                        >
                    </div>


                    <div class="form-group">
                        <label for="gender">Gender</label>

                        <select id="gender" name="gender">
                            <option value="">Select gender</option>

                            <?php foreach (["Male", "Female", "Other"] as $option): ?>
                                <option
                                    value="<?php echo $option; ?>"
                                    <?php echo $profile["gender"] === $option ? "selected" : ""; ?>
                                >
                                    <?php echo $option; ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>


                    <div class="form-group">
                        <label for="blood_group">Blood Group</label>

                        <select id="blood_group" name="blood_group">
                            <option value="">Select blood group</option>

                            <?php foreach (["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"] as $option): ?>
                                <option
                                    value="<?php echo $option; ?>"
                                    <?php echo $profile["blood_group"] === $option ? "selected" : ""; ?>
                                >
                                    <?php echo $option; ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>


                    <div class="form-group">
                        <label for="height_cm">Height (cm)</label>

                        <input
                            type="number"
                            id="height_cm"
                            name="height_cm"
                            min="1"
                            max="300"
                            step="0.01"
                            value="<?php echo htmlspecialchars((string)$profile["height_cm"]); ?>"
                            placeholder="Example: 165"
                        >
                    </div>


                    <div class="form-group">
                        <label for="weight_kg">Weight (kg)</label>

                        <input
                            type="number"
                            id="weight_kg"
                            name="weight_kg"
                            min="1"
                            max="700"
                            step="0.01"
                            value="<?php echo htmlspecialchars((string)$profile["weight_kg"]); ?>"
                            placeholder="Example: 60"
                        >
                    </div>

                </div>

            </div>


            <!-- MEDICAL CONDITIONS -->
            <div class="profile-card">

                <h2>Medical Conditions</h2>

                <div class="form-group">

                    <label for="conditions">Medical Conditions</label>

                    <textarea
                        id="conditions"
                        name="conditions"
                        placeholder="Example: Diabetes&#10;Asthma"
                    ><?php echo htmlspecialchars($conditions_text); ?></textarea>

                    <p class="help-text">
                        Enter one medical condition per line.
                    </p>

                </div>


                <div class="form-group">

                    <label for="diagnosed_years">Diagnosed Year</label>

                    <textarea
                        id="diagnosed_years"
                        name="diagnosed_years"
                        placeholder="Example: 2020&#10;2022"
                    ><?php echo htmlspecialchars($diagnosed_years_text); ?></textarea>

                    <p class="help-text">
                        Enter the diagnosed year in the same order as the conditions.
                        Leave blank if unknown.
                    </p>

                </div>


                <div class="form-group">

                    <label for="condition_notes">Notes</label>

                    <textarea
                        id="condition_notes"
                        name="condition_notes"
                        placeholder="Enter additional information about each condition"
                    ><?php echo htmlspecialchars($condition_notes_text); ?></textarea>

                    <p class="help-text">
                        Enter notes in the same order as the conditions.
                    </p>

                </div>


                <div class="form-group">

                    <label for="medications">Medications</label>

                    <textarea
                        id="medications"
                        name="medications"
                        placeholder="Example: Metformin&#10;Inhaler"
                    ><?php echo htmlspecialchars($medications_text); ?></textarea>

                    <p class="help-text">
                        Enter medications for each condition in the same order.
                        Leave blank if none.
                    </p>

                </div>


                <div class="form-group">

                    <label for="surgeries">Surgeries</label>

                    <textarea
                        id="surgeries"
                        name="surgeries"
                        placeholder="Enter surgery details for each condition"
                    ><?php echo htmlspecialchars($surgeries_text); ?></textarea>

                    <p class="help-text">
                        Enter surgery details in the same order as the conditions.
                        Leave blank if none.
                    </p>

                </div>

            </div>


            <!-- ALLERGIES -->
            <div class="profile-card">

                <h2>Allergies</h2>

                <div class="form-group">

                    <label for="allergies">Allergies</label>

                    <textarea
                        id="allergies"
                        name="allergies"
                        placeholder="Enter each allergy on a separate line"
                    ><?php echo htmlspecialchars($allergies_text); ?></textarea>

                    <p class="help-text">
                        Enter one allergy per line. Leave empty if none.
                        Severity is currently stored as Not specified.
                    </p>

                </div>

            </div>


            <!-- SAVE AND UPDATE BUTTONS -->
            <div class="profile-actions">

                <button
                    type="submit"
                    name="save_profile"
                    value="save"
                    class="save-btn">
                    Save Medical Profile
                </button>

                <?php if ($profile_id !== null): ?>

                    <button
                        type="submit"
                        name="save_profile"
                        value="update"
                        class="update-btn">
                        Update Medical Profile
                    </button>

                <?php endif; ?>

                <a href="dashboard.php" class="back-link">
                    Back to Dashboard
                </a>

            </div>

        </form>


        <!-- DELETE BUTTON -->
        <?php if ($profile_id !== null): ?>

            <div class="delete-section">

                <h2>Delete Medical Profile</h2>

                <p>
                    Deleting your profile will also delete its associated
                    medical conditions, allergies, emergency contacts
                    and access-request records.
                </p>

                <form
                    method="POST"
                    action="medical_profile.php"
                    onsubmit="return confirm('Are you sure you want to permanently delete your medical profile and its related information? This action cannot be undone.');"
                >

                    <button
                        type="submit"
                        name="delete_profile"
                        value="1"
                        class="delete-btn">
                        Delete Medical Profile
                    </button>

                </form>

            </div>

        <?php endif; ?>

    </div>

</main>




</body>
</html>
