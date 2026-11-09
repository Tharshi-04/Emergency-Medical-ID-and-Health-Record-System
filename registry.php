
<?php
session_start();

require_once "config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];

$message = "";
$error = "";

// Get patient profile using profile_id
$stmt = $conn->prepare(
    "SELECT profile_id, full_name
     FROM patient_profiles
     WHERE user_id = ?"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    die("Patient profile not found.");
}

$patient_id = $patient["profile_id"];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $is_blood_donor = isset($_POST["is_blood_donor"]) ? 1 : 0;
    $is_organ_donor = isset($_POST["is_organ_donor"]) ? 1 : 0;

    $district = trim($_POST["district"] ?? "");
    $last_donated_date = !empty($_POST["last_donated_date"])
        ? $_POST["last_donated_date"]
        : null;

    if ($district === "") {
        $error = "Please select your district.";
    } elseif (
        $last_donated_date !== null &&
        $last_donated_date > date("Y-m-d")
    ) {
        $error = "Last donated date cannot be in the future.";
    } else {

        // Check whether registry record already exists
        $stmt = $conn->prepare(
            "SELECT registry_id
             FROM organ_blood_registry
             WHERE patient_id = ?"
        );

        $stmt->bind_param("i", $patient_id);
        $stmt->execute();

        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {

            // Update existing record
            $stmt = $conn->prepare(
                "UPDATE organ_blood_registry
                 SET is_blood_donor = ?,
                     is_organ_donor = ?,
                     district = ?,
                     last_donated_date = ?
                 WHERE patient_id = ?"
            );

            $stmt->bind_param(
                "iissi",
                $is_blood_donor,
                $is_organ_donor,
                $district,
                $last_donated_date,
                $patient_id
            );

        } else {

            // Insert new record
            $stmt = $conn->prepare(
                "INSERT INTO organ_blood_registry
                 (patient_id, is_blood_donor, is_organ_donor,
                  district, last_donated_date)
                 VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "iiiss",
                $patient_id,
                $is_blood_donor,
                $is_organ_donor,
                $district,
                $last_donated_date
            );
        }

        if ($stmt->execute()) {
            $message = "Registry details saved successfully!";
        } else {
            $error = "Unable to save registry details.";
        }

        $stmt->close();
    }
}

// Get saved registry details
$stmt = $conn->prepare(
    "SELECT registry_id, is_blood_donor, is_organ_donor,
            district, last_donated_date
     FROM organ_blood_registry
     WHERE patient_id = ?"
);

$stmt->bind_param("i", $patient_id);
$stmt->execute();

$registry = $stmt->get_result()->fetch_assoc();
$stmt->close();

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$districts = [
    "Ampara",
    "Anuradhapura",
    "Badulla",
    "Batticaloa",
    "Colombo",
    "Galle",
    "Gampaha",
    "Hambantota",
    "Jaffna",
    "Kalutara",
    "Kandy",
    "Kegalle",
    "Kilinochchi",
    "Kurunegala",
    "Mannar",
    "Matale",
    "Matara",
    "Monaragala",
    "Mullaitivu",
    "Nuwara Eliya",
    "Polonnaruwa",
    "Puttalam",
    "Ratnapura",
    "Trincomalee",
    "Vavuniya"
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Blood and Organ Donor Registry</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        body {
            background: #EFF6FF;
            color: #1E293B;
            font-family: Arial, sans-serif;
        }

        .registry-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }

        .registry-card {
            background: #FFFFFF;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(59,130,246,0.10);
        }

        h1 {
            text-align: center;
            color: #1D4ED8;
            margin-bottom: 10px;
        }

        h2 {
            color: #0F766E;
            margin-bottom: 20px;
        }

        .subtitle {
            text-align: center;
            color: #64748B;
            margin-bottom: 25px;
        }

        .donor-option {
            background: #EFF6FF;
            border: 1px solid #BFDBFE;
            padding: 18px;
            border-radius: 9px;
            margin-bottom: 15px;
        }

        .donor-option label {
            font-weight: bold;
            cursor: pointer;
            margin-left: 8px;
        }

        .form-group {
            margin-top: 20px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #BFDBFE;
            border-radius: 7px;
            box-sizing: border-box;
            font-size: 15px;
        }

        .save-btn {
            background: #0D9488;
            color: white;
            border: none;
            padding: 13px 25px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 15px;
            margin-top: 22px;
        }

        .save-btn:hover {
            background: #0F766E;
        }

        .message {
            background: #DCFCE7;
            color: #166534;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 18px;
        }

        .error {
            background: #FEE2E2;
            color: #991B1B;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 18px;
        }

        .saved-details {
            background: #DBEAFE;
            padding: 18px;
            border-radius: 9px;
            margin-top: 25px;
        }

        .saved-details p {
            margin: 10px 0;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #0F766E;
            text-decoration: none;
            font-weight: bold;
        }

        .note {
            background: #FEF3C7;
            color: #92400E;
            padding: 13px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
            line-height: 1.5;
        }

        @media(max-width: 600px) {
            .registry-container {
                padding: 12px;
            }

            .registry-card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<div class="registry-container">

    <a href="dashboard.php" class="back-link">
        ← Back to Dashboard
    </a>

    <div class="registry-card">

        <h1>Blood & Organ Donor Registry</h1>

        <p class="subtitle">
            Register your interest in helping others through donation.
        </p>

        <?php if ($message): ?>
            <div class="message">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error">
                <?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <h2>Donor Information</h2>

            <div class="donor-option">
                <input
                    type="checkbox"
                    id="blood"
                    name="is_blood_donor"
                    value="1"
                    <?php
                    if (!empty($registry["is_blood_donor"])) {
                        echo "checked";
                    }
                    ?>
                >

                <label for="blood">
                    I am interested in becoming a Blood Donor.
                </label>
            </div>

            <div class="donor-option">
                <input
                    type="checkbox"
                    id="organ"
                    name="is_organ_donor"
                    value="1"
                    <?php
                    if (!empty($registry["is_organ_donor"])) {
                        echo "checked";
                    }
                    ?>
                >

                <label for="organ">
                    I am interested in becoming an Organ Donor.
                </label>
            </div>

            <div class="form-group">

                <label>District</label>

                <select name="district" required>

                    <option value="">Select Your District</option>

                    <?php foreach ($districts as $district): ?>

                        <option
                            value="<?php echo e($district); ?>"
                            <?php
                            if (
                                isset($registry["district"]) &&
                                $registry["district"] === $district
                            ) {
                                echo "selected";
                            }
                            ?>
                        >
                            <?php echo e($district); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="form-group">

                <label>Last Donated Date (Optional)</label>

                <input
                    type="date"
                    name="last_donated_date"
                    max="<?php echo date("Y-m-d"); ?>"
                    value="<?php echo e($registry["last_donated_date"] ?? ""); ?>"
                >

            </div>

            <button type="submit" class="save-btn">
                Save Registry Details
            </button>

        </form>

        <?php if ($registry): ?>

            <div class="saved-details">

                <h2>Saved Registry Details</h2>

                <p>
                    <strong>Name:</strong>
                    <?php echo e($patient["full_name"]); ?>
                </p>

                <p>
                    <strong>Blood Donor:</strong>
                    <?php echo $registry["is_blood_donor"] ? "Yes" : "No"; ?>
                </p>

                <p>
                    <strong>Organ Donor:</strong>
                    <?php echo $registry["is_organ_donor"] ? "Yes" : "No"; ?>
                </p>

                <p>
                    <strong>District:</strong>
                    <?php echo e($registry["district"]); ?>
                </p>

                <p>
                    <strong>Last Donated Date:</strong>
                    <?php echo e($registry["last_donated_date"] ?: "Not provided"); ?>
                </p>

            </div>

        <?php endif; ?>

        <div class="note">
            <strong>Important:</strong> This form records your interest
            in blood or organ donation. It is not an official donor
            registration or legal consent. Please contact the relevant
            authorized health service to complete official registration.
        </div>

    </div>

</div>

</body>
</html>
