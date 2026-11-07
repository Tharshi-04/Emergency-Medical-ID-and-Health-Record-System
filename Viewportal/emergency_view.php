
<?php
require_once "config/db.php";

// Get QR token from URL
$token = $_GET["token"] ?? "";

if (empty($token)) {
    die("Invalid QR code. No access token found.");
}

// Find patient using QR token
$sql = "SELECT id, full_name, dob, gender, blood_group,
               height_cm, weight_kg
        FROM patient_profiles
        WHERE qr_token = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token);
$stmt->execute();

$result = $stmt->get_result();
$patient = $result->fetch_assoc();

if (!$patient) {
    die("Invalid or expired QR code.");
}

$patient_id = $patient["id"];

// Get medical conditions
$condition_sql = "SELECT condition_name, diagnosed_year, notes
                  FROM medical_conditions
                  WHERE patient_id = ?";

$condition_stmt = $conn->prepare($condition_sql);
$condition_stmt->bind_param("i", $patient_id);
$condition_stmt->execute();

$conditions = $condition_stmt->get_result();

// Get allergies
$allergy_sql = "SELECT allergen_name, severity, reaction_notes
                FROM allergies
                WHERE patient_id = ?";

$allergy_stmt = $conn->prepare($allergy_sql);
$allergy_stmt->bind_param("i", $patient_id);
$allergy_stmt->execute();

$allergies = $allergy_stmt->get_result();

// Get emergency contacts
$contact_sql = "SELECT contact_name, relationship,
                       phone_number, is_primary
                FROM emergency_contacts
                WHERE patient_id = ?
                ORDER BY is_primary DESC";

$contact_stmt = $conn->prepare($contact_sql);
$contact_stmt->bind_param("i", $patient_id);
$contact_stmt->execute();

$contacts = $contact_stmt->get_result();

// Safe output function
function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Emergency Medical Information</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        body {
            background: #EFF6FF;
            color: #1E293B;
        }

        .emergency-wrapper {
            max-width: 850px;
            margin: 35px auto;
            padding: 20px;
        }

        .emergency-header {
            background: #60A5FA;
            color: white;
            text-align: center;
            padding: 25px;
            border-radius: 14px;
            margin-bottom: 25px;
        }

        .emergency-header h1 {
            margin: 0 0 10px;
        }

        .emergency-header p {
            margin: 0;
        }

        .emergency-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.10);
        }

        .emergency-card h2 {
            color: #0F766E;
            border-bottom: 2px solid #DBEAFE;
            padding-bottom: 10px;
            margin-top: 0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .info-item {
            background: #EFF6FF;
            padding: 14px;
            border-radius: 8px;
        }

        .info-item strong {
            display: block;
            color: #64748B;
            font-size: 13px;
            margin-bottom: 6px;
        }

        .info-item span {
            font-weight: bold;
        }

        .record-item {
            background: #F8FAFC;
            border-left: 4px solid #0D9488;
            padding: 15px;
            margin-bottom: 12px;
            border-radius: 6px;
        }

        .record-item p {
            margin: 6px 0;
        }

        .primary-label {
            display: inline-block;
            background: #CCFBF1;
            color: #0F766E;
            padding: 4px 9px;
            border-radius: 15px;
            font-size: 12px;
            margin-left: 5px;
        }

        .empty-message {
            color: #64748B;
            font-style: italic;
        }

        .emergency-note {
            background: #FEF3C7;
            color: #92400E;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
        }

        .back-button {
            display: inline-block;
            background: #0D9488;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            margin-top: 10px;
        }

        @media (max-width: 600px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .emergency-wrapper {
                padding: 12px;
            }

            .emergency-card {
                padding: 18px;
            }
        }

        @media print {
            .back-button {
                display: none;
            }

            body {
                background: white;
            }

            .emergency-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>

<body>

<div class="emergency-wrapper">

    <div class="emergency-header">
        <h1>Emergency Medical Information</h1>
        <p>Important health details for emergency situations</p>
    </div>

    <!-- Patient Information -->
    <div class="emergency-card">

        <h2>Patient Information</h2>

        <div class="info-grid">

            <div class="info-item">
                <strong>Full Name</strong>
                <span><?php echo e($patient["full_name"]); ?></span>
            </div>

            <div class="info-item">
                <strong>Blood Group</strong>
                <span><?php echo e($patient["blood_group"] ?: "Not provided"); ?></span>
            </div>

            <div class="info-item">
                <strong>Date of Birth</strong>
                <span><?php echo e($patient["dob"] ?: "Not provided"); ?></span>
            </div>

            <div class="info-item">
                <strong>Gender</strong>
                <span><?php echo e($patient["gender"] ?: "Not provided"); ?></span>
            </div>

            <div class="info-item">
                <strong>Height</strong>
                <span>
                    <?php echo $patient["height_cm"] !== null
                        ? e($patient["height_cm"]) . " cm"
                        : "Not provided"; ?>
                </span>
            </div>

            <div class="info-item">
                <strong>Weight</strong>
                <span>
                    <?php echo $patient["weight_kg"] !== null
                        ? e($patient["weight_kg"]) . " kg"
                        : "Not provided"; ?>
                </span>
            </div>

        </div>
    </div>

    <!-- Medical Conditions -->
    <div class="emergency-card">

        <h2>Medical Conditions</h2>

        <?php if ($conditions->num_rows > 0): ?>

            <?php while ($condition = $conditions->fetch_assoc()): ?>

                <div class="record-item">

                    <p>
                        <strong>Condition:</strong>
                        <?php echo e($condition["condition_name"]); ?>
                    </p>

                    <p>
                        <strong>Diagnosed Year:</strong>
                        <?php echo e($condition["diagnosed_year"] ?: "Not provided"); ?>
                    </p>

                    <?php if (!empty($condition["notes"])): ?>
                        <p>
                            <strong>Notes:</strong>
                            <?php echo e($condition["notes"]); ?>
                        </p>
                    <?php endif; ?>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <p class="empty-message">No medical conditions recorded.</p>

        <?php endif; ?>

    </div>

    <!-- Allergies -->
    <div class="emergency-card">

        <h2>Allergies</h2>

        <?php if ($allergies->num_rows > 0): ?>

            <?php while ($allergy = $allergies->fetch_assoc()): ?>

                <div class="record-item">

                    <p>
                        <strong>Allergen:</strong>
                        <?php echo e($allergy["allergen_name"]); ?>
                    </p>

                    <p>
                        <strong>Severity:</strong>
                        <?php echo e($allergy["severity"] ?: "Not provided"); ?>
                    </p>

                    <?php if (!empty($allergy["reaction_notes"])): ?>
                        <p>
                            <strong>Reaction:</strong>
                            <?php echo e($allergy["reaction_notes"]); ?>
                        </p>
                    <?php endif; ?>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <p class="empty-message">No allergies recorded.</p>

        <?php endif; ?>

    </div>

    <!-- Emergency Contacts -->
    <div class="emergency-card">

        <h2>Emergency Contacts</h2>

        <?php if ($contacts->num_rows > 0): ?>

            <?php while ($contact = $contacts->fetch_assoc()): ?>

                <div class="record-item">

                    <p>
                        <strong>Name:</strong>
                        <?php echo e($contact["contact_name"]); ?>

                        <?php if ($contact["is_primary"]): ?>
                            <span class="primary-label">Primary Contact</span>
                        <?php endif; ?>
                    </p>

                    <p>
                        <strong>Relationship:</strong>
                        <?php echo e($contact["relationship"]); ?>
                    </p>

                    <p>
                        <strong>Phone:</strong>
                        <a href="tel:<?php echo e($contact["phone_number"]); ?>">
                            <?php echo e($contact["phone_number"]); ?>
                        </a>
                    </p>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <p class="empty-message">No emergency contacts recorded.</p>

        <?php endif; ?>

    </div>

    <div class="emergency-note">
        <strong>Important:</strong>
        This information is provided to assist in emergencies.
        Always confirm medical details with the patient or a healthcare professional.
        In an emergency, contact local emergency services.
    </div>

    <div style="text-align:center;">
        <a href="javascript:window.print()" class="back-button">
            Print Medical Information
        </a>
    </div>

</div>

</body>
</html>
