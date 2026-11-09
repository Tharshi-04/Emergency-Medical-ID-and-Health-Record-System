
<?php
require_once "config/db.php";

$token = trim($_GET['token'] ?? '');

$profile = null;
$conditions = [];
$allergies = [];
$contacts = [];
$error_message = "";

if ($token === "" || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $error_message = "Invalid or unavailable QR code.";
} else {

    // Find patient using QR token
    $stmt = $conn->prepare(
        "SELECT profile_id, full_name, dob, gender, blood_group,
                height_cm, weight_kg
         FROM patient_profiles
         WHERE qr_token = ?
         LIMIT 1"
    );

    $stmt->bind_param("s", $token);
    $stmt->execute();

    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();
    $stmt->close();

    if (!$profile) {
        $error_message = "This QR code is invalid or no longer available.";
    } else {

        $patient_id = (int) $profile['profile_id'];

        // Get medical conditions
        $stmt = $conn->prepare(
            "SELECT condition_name, diagnosed_year, notes
             FROM medical_conditions
             WHERE patient_id = ?
             ORDER BY condition_id DESC"
        );

        $stmt->bind_param("i", $patient_id);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $conditions[] = $row;
        }

        $stmt->close();

        // Get allergies
        $stmt = $conn->prepare(
            "SELECT allergen_name, severity, reaction_notes
             FROM allergies
             WHERE patient_id = ?
             ORDER BY allergy_id DESC"
        );

        $stmt->bind_param("i", $patient_id);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $allergies[] = $row;
        }

        $stmt->close();

        // Get emergency contacts
        $stmt = $conn->prepare(
            "SELECT contact_name, relationship, phone_number, is_primary
             FROM emergency_contacts
             WHERE patient_id = ?
             ORDER BY is_primary DESC, contact_id DESC"
        );

        $stmt->bind_param("i", $patient_id);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $contacts[] = $row;
        }

        $stmt->close();
    }
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
        .emergency-container {
            max-width: 900px;
            margin: 35px auto;
            padding: 20px;
        }

        .emergency-heading {
            background: #123B63;
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 25px;
        }

        .emergency-heading h1 {
            margin-bottom: 8px;
        }

        .emergency-alert {
            background: #fff3cd;
            color: #795600;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .medical-card {
            background: white;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            margin-bottom: 20px;
        }

        .medical-card h2 {
            color: #123B63;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .detail-item {
            background: #f4f8fc;
            padding: 13px;
            border-radius: 8px;
        }

        .detail-item strong {
            display: block;
            color: #718096;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .detail-item span {
            color: #263746;
            font-weight: 600;
        }

        .record-item {
            padding: 14px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .record-item:last-child {
            border-bottom: none;
        }

        .record-item h3 {
            color: #263746;
            margin-bottom: 6px;
        }

        .record-item p {
            color: #555;
            margin: 5px 0;
            overflow-wrap: anywhere;
        }

        .primary-contact {
            color: #087443;
            font-weight: bold;
            font-size: 13px;
        }

        .empty-record {
            color: #718096;
        }

        .invalid-card {
            background: white;
            padding: 35px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }

        .invalid-card h2 {
            color: #c62828;
            margin-bottom: 12px;
        }

        .privacy-note {
            background: #e8f1fa;
            color: #123B63;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        @media (max-width: 600px) {
            .details-grid {
                grid-template-columns: 1fr;
            }

            .emergency-container {
                padding: 12px;
            }
        }
    </style>
</head>

<body>

<main class="emergency-container">

    <?php if ($error_message !== ""): ?>

        <div class="invalid-card">
            <h2>QR Code Unavailable</h2>
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>

    <?php else: ?>

        <div class="emergency-heading">
            <h1>Emergency Medical Information</h1>
            <p>Please use this information if the patient needs emergency assistance.</p>
        </div>

        <div class="emergency-alert">
            <strong>Emergency notice:</strong>
            If the patient needs urgent medical attention, contact local
            emergency services or take the patient to the nearest hospital.
        </div>

        <!-- Patient Details -->
        <section class="medical-card">

            <h2>Patient Information</h2>

            <div class="details-grid">

                <div class="detail-item">
                    <strong>Full Name</strong>
                    <span>
                        <?php echo htmlspecialchars($profile['full_name']); ?>
                    </span>
                </div>

                <div class="detail-item">
                    <strong>Blood Group</strong>
                    <span>
                        <?php echo htmlspecialchars($profile['blood_group'] ?: 'Not provided'); ?>
                    </span>
                </div>

                <div class="detail-item">
                    <strong>Date of Birth</strong>
                    <span>
                        <?php echo htmlspecialchars($profile['dob'] ?: 'Not provided'); ?>
                    </span>
                </div>

                <div class="detail-item">
                    <strong>Gender</strong>
                    <span>
                        <?php echo htmlspecialchars($profile['gender'] ?: 'Not provided'); ?>
                    </span>
                </div>

                <div class="detail-item">
                    <strong>Height</strong>
                    <span>
                        <?php
                        echo $profile['height_cm'] !== null
                            ? htmlspecialchars($profile['height_cm']) . ' cm'
                            : 'Not provided';
                        ?>
                    </span>
                </div>

                <div class="detail-item">
                    <strong>Weight</strong>
                    <span>
                        <?php
                        echo $profile['weight_kg'] !== null
                            ? htmlspecialchars($profile['weight_kg']) . ' kg'
                            : 'Not provided';
                        ?>
                    </span>
                </div>

            </div>

        </section>

        <!-- Medical Conditions -->
        <section class="medical-card">

            <h2>Medical Conditions</h2>

            <?php if (count($conditions) > 0): ?>

                <?php foreach ($conditions as $condition): ?>

                    <div class="record-item">

                        <h3>
                            <?php echo htmlspecialchars($condition['condition_name']); ?>
                        </h3>

                        <?php if (!empty($condition['diagnosed_year'])): ?>
                            <p>
                                Diagnosed Year:
                                <?php echo htmlspecialchars($condition['diagnosed_year']); ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($condition['notes'])): ?>
                            <p>
                                Notes:
                                <?php echo nl2br(htmlspecialchars($condition['notes'])); ?>
                            </p>
                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p class="empty-record">No medical conditions provided.</p>

            <?php endif; ?>

        </section>

        <!-- Allergies -->
        <section class="medical-card">

            <h2>Allergies</h2>

            <?php if (count($allergies) > 0): ?>

                <?php foreach ($allergies as $allergy): ?>

                    <div class="record-item">

                        <h3>
                            <?php echo htmlspecialchars($allergy['allergen_name']); ?>
                        </h3>

                        <p>
                            Severity:
                            <?php echo htmlspecialchars($allergy['severity'] ?: 'Not specified'); ?>
                        </p>

                        <?php if (!empty($allergy['reaction_notes'])): ?>
                            <p>
                                Reaction:
                                <?php echo nl2br(htmlspecialchars($allergy['reaction_notes'])); ?>
                            </p>
                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p class="empty-record">No allergies provided.</p>

            <?php endif; ?>

        </section>

        <!-- Emergency Contacts -->
        <section class="medical-card">

            <h2>Emergency Contacts</h2>

            <?php if (count($contacts) > 0): ?>

                <?php foreach ($contacts as $contact): ?>

                    <div class="record-item">

                        <h3>
                            <?php echo htmlspecialchars($contact['contact_name']); ?>
                        </h3>

                        <p>
                            Relationship:
                            <?php echo htmlspecialchars($contact['relationship'] ?: 'Not specified'); ?>
                        </p>

                        <p>
                            Phone Number:
                            <a href="tel:<?php echo htmlspecialchars($contact['phone_number']); ?>">
                                <?php echo htmlspecialchars($contact['phone_number']); ?>
                            </a>
                        </p>

                        <?php if ($contact['is_primary']): ?>
                            <span class="primary-contact">
                                Primary Emergency Contact
                            </span>
                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p class="empty-record">No emergency contacts provided.</p>

            <?php endif; ?>

        </section>

        <div class="privacy-note">
            This information is provided for emergency assistance.
            Medical information may be incomplete or outdated. Confirm
            important details with the patient or their healthcare provider.
        </div>

    <?php endif; ?>

</main>

</body>
</html>
