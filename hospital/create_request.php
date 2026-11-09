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

// Get hospital profile
$hospitalQuery = $conn->prepare(
    "SELECT hospital_id, verification_status
     FROM hospital_profiles
     WHERE user_id = ?"
);
$hospitalQuery->bind_param("i", $user_id);
$hospitalQuery->execute();
$hospital = $hospitalQuery->get_result()->fetch_assoc();

$success = "";
$error = "";

if (!$hospital) {
    $error = "Please complete your hospital profile first.";
} elseif ($hospital['verification_status'] !== 'verified') {
    $error = "Your hospital must be verified by an administrator before creating requests.";
}

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    $hospital &&
    $hospital['verification_status'] === 'verified'
) {
    $request_type = $_POST['request_type'] ?? '';
    $patient_reference = trim($_POST['patient_reference'] ?? '');
    $blood_group = trim($_POST['blood_group'] ?? '');
    $required_units = $_POST['required_units'] ?? '';
    $organ_type = trim($_POST['organ_type'] ?? '');
    $required_date = $_POST['required_date'] ?? '';
    $urgency = $_POST['urgency'] ?? '';
    $description = trim($_POST['description'] ?? '');

    $allowedTypes = ['blood', 'organ'];
    $allowedUrgencies = ['normal', 'urgent', 'emergency'];

    if (!in_array($request_type, $allowedTypes, true)) {
        $error = "Please select a valid request type.";
    } elseif (!in_array($urgency, $allowedUrgencies, true)) {
        $error = "Please select a valid urgency level.";
    } elseif ($required_date === '') {
        $error = "Please select the required date.";
    } elseif ($required_date < date('Y-m-d')) {
        $error = "Required date cannot be in the past.";
    } elseif ($description === '') {
        $error = "Please enter a description.";
    } elseif (
        $request_type === 'blood' &&
        !in_array($blood_group, [
            'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'
        ], true)
    ) {
        $error = "Please select a valid blood group.";
    } elseif (
        $request_type === 'blood' &&
        (!filter_var($required_units, FILTER_VALIDATE_INT) ||
         (int)$required_units < 1)
    ) {
        $error = "Please enter a valid number of blood units.";
    } elseif (
        $request_type === 'organ' &&
        $organ_type === ''
    ) {
        $error = "Please select the required organ.";
    } else {

        if ($request_type === 'blood') {
            $organ_type = null;
        } else {
            $blood_group = null;
            $required_units = null;
        }

        if ($required_units !== null) {
            $required_units = (int)$required_units;
        }

        $insert = $conn->prepare(
            "INSERT INTO hospital_requests
            (
                hospital_id,
                request_type,
                patient_reference,
                blood_group,
                required_units,
                organ_type,
                required_date,
                urgency,
                description
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $insert->bind_param(
            "isssissss",
            $hospital['hospital_id'],
            $request_type,
            $patient_reference,
            $blood_group,
            $required_units,
            $organ_type,
            $required_date,
            $urgency,
            $description
        );

        if ($insert->execute()) {
            $success = "Request submitted successfully. It is waiting for administrator review.";
        } else {
            $error = "Unable to submit request. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Donation Request</title>
    <link rel="stylesheet" href="../css/style.css">

    <style>
        .request-container {
            max-width: 750px;
            margin: 40px auto;
            padding: 20px;
        }

        .request-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.07);
            border: 1px solid #E2E8F0;
        }

        .request-card h1 {
            color: #123B63;
            margin-bottom: 10px;
        }

        .description-text {
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #CBD5E0;
            border-radius: 7px;
            font-size: 15px;
            box-sizing: border-box;
        }

        .form-group textarea {
            min-height: 110px;
            resize: vertical;
        }

        .submit-button {
            background: #123B63;
            color: white;
            border: none;
            padding: 12px 22px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 15px;
        }

        .submit-button:hover {
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

        .notice {
            background: #FFF7E6;
            color: #795313;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .hidden {
            display: none;
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

<main class="request-container">

    <div class="request-card">

        <h1>Create Donation Request</h1>

        <p class="description-text">
            Submit a blood or organ donation request.
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

        <?php if (
            $hospital &&
            $hospital['verification_status'] === 'verified'
        ): ?>

            <form method="POST" action="">

                <div class="form-group">
                    <label>Request Type *</label>
                    <select name="request_type" id="request_type" required>
                        <option value="">Select Request Type</option>
                        <option value="blood">Blood Donation</option>
                        <option value="organ">Organ Donation</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Patient Reference</label>
                    <input
                        type="text"
                        name="patient_reference"
                        placeholder="Enter a hospital reference number"
                    >
                </div>

                <div id="blood_fields" class="hidden">

                    <div class="form-group">
                        <label>Blood Group *</label>
                        <select name="blood_group" id="blood_group">
                            <option value="">Select Blood Group</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Required Units *</label>
                        <input
                            type="number"
                            name="required_units"
                            id="required_units"
                            min="1"
                            placeholder="Enter number of units"
                        >
                    </div>

                </div>

                <div id="organ_fields" class="hidden">

                    <div class="form-group">
                        <label>Required Organ *</label>
                        <select name="organ_type" id="organ_type">
                            <option value="">Select Organ</option>
                            <option value="Kidney">Kidney</option>
                            <option value="Liver">Liver</option>
                            <option value="Heart">Heart</option>
                            <option value="Lung">Lung</option>
                            <option value="Pancreas">Pancreas</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                </div>

                <div class="form-group">
                    <label>Required Date *</label>
                    <input
                        type="date"
                        name="required_date"
                        min="<?php echo date('Y-m-d'); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Urgency *</label>
                    <select name="urgency" required>
                        <option value="">Select Urgency</option>
                        <option value="normal">Normal</option>
                        <option value="urgent">Urgent</option>
                        <option value="emergency">Emergency</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Description *</label>
                    <textarea
                        name="description"
                        placeholder="Provide necessary request details..."
                        required
                    ></textarea>
                </div>

                <button type="submit" class="submit-button">
                    Submit Request
                </button>

            </form>

        <?php else: ?>

            <div class="notice">
                Complete your hospital profile and wait for admin verification
                before creating donation requests.
            </div>

            <a href="hospital_profile.php" class="submit-button">
                Go to Hospital Profile
            </a>

        <?php endif; ?>

    </div>

</main>

<footer class="main-footer">
    <p>© <?php echo date("Y"); ?> Emergency Medical ID System</p>
</footer>

<script>
const requestType = document.getElementById("request_type");

if (requestType) {
    const bloodFields = document.getElementById("blood_fields");
    const organFields = document.getElementById("organ_fields");

    const bloodGroup = document.getElementById("blood_group");
    const requiredUnits = document.getElementById("required_units");
    const organType = document.getElementById("organ_type");

    function updateRequestFields() {
        const selectedType = requestType.value;

        bloodFields.classList.toggle("hidden", selectedType !== "blood");
        organFields.classList.toggle("hidden", selectedType !== "organ");

        bloodGroup.required = selectedType === "blood";
        requiredUnits.required = selectedType === "blood";
        organType.required = selectedType === "organ";
    }

    requestType.addEventListener("change", updateRequestFields);
    updateRequestFields();
}
</script>

</body>
</html>