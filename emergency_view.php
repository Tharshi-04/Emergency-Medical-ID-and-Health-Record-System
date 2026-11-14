
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

        // Get medical conditions, medications and surgeries
        $stmt = $conn->prepare(
            "SELECT condition_name, diagnosed_year, notes,
                    medications, surgeries
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
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #F5F8FC;
            font-family: Arial, sans-serif;
            color: #263746;
            line-height: 1.6;
        }

        .emergency-container {
            max-width: 950px;
            margin: 40px auto;
            padding: 20px;
        }

        /* HEADER */
        .emergency-heading {
            background: linear-gradient(120deg, #0B2945, #123B63, #1E5A8A);
            color: white;
            padding: 35px 25px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 25px;
            box-shadow: 0 5px 18px rgba(18, 59, 99, 0.12);
        }

        .emergency-heading h1 {
            font-size: 30px;
            margin: 0 0 10px;
        }

        .emergency-heading p {
            color: #DCE8F2;
            font-size: 16px;
            margin: 0;
        }

        /* EMERGENCY ALERT */
        .emergency-alert {
            background: #FFF3CD;
            color: #795600;
            padding: 16px 20px;
            border-left: 5px solid #E5A000;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 15px;
        }

        /* MEDICAL CARDS */
        .medical-card {
            background: white;
            padding: 25px;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(18, 59, 99, 0.06);
            border: 1px solid #E6EDF5;
            margin-bottom: 22px;
        }

        .medical-card h2 {
            color: #123B63;
            font-size: 23px;
            border-bottom: 2px solid #E2E8F0;
            padding-bottom: 12px;
            margin: 0 0 20px;
        }

        /* PATIENT DETAILS */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .detail-item {
            background: #F4F8FC;
            padding: 15px;
            border-radius: 9px;
            border-left: 3px solid #16A6A1;
        }

        .detail-item strong {
            display: block;
            color: #718096;
            font-size: 14px;
            margin-bottom: 6px;
        }

        .detail-item span {
            color: #263746;
            font-size: 16px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        /* RECORD ITEMS */
        .record-item {
            padding: 18px 0;
            border-bottom: 1px solid #E2E8F0;
        }

        .record-item:first-of-type {
            padding-top: 0;
        }

        .record-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .record-item h3 {
            color: #123B63;
            font-size: 19px;
            margin: 0 0 10px;
        }

        .record-item p {
            color: #4A5568;
            font-size: 15px;
            margin: 7px 0;
            overflow-wrap: anywhere;
        }

        .record-item p strong {
            color: #263746;
        }

        /* PRIMARY CONTACT */
        .primary-contact {
            display: inline-block;
            background: #E6F7F0;
            color: #087443;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 13px;
            margin-top: 5px;
        }

        .contact-phone {
            color: #087E8B;
            font-weight: 600;
            text-decoration: none;
        }

        .contact-phone:hover {
            text-decoration: underline;
        }

        /* EMPTY RECORD */
        .empty-record {
            color: #718096;
            background: #F4F8FC;
            padding: 15px;
            border-radius: 8px;
            font-size: 15px;
        }

        /* INVALID QR */
        .invalid-card {
            background: white;
            padding: 40px 25px;
            border-radius: 14px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }

        .invalid-card h2 {
            color: #C62828;
            font-size: 25px;
            margin-bottom: 12px;
        }

        .invalid-card p {
            color: #555;
            font-size: 16px;
        }

        /* PRIVACY NOTE */
        .privacy-note {
            background: #E8F1FA;
            color: #123B63;
            padding: 18px;
            border-left: 4px solid #16A6A1;
            border-radius: 9px;
            margin-top: 25px;
            font-size: 14px;
        }

        /* RESPONSIVE DESIGN */
        @media (max-width: 600px) {

            .emergency-container {
                margin: 20px auto;
                padding: 12px;
            }

            .emergency-heading {
                padding: 25px 15px;
            }

            .emergency-heading h1 {
                font-size: 24px;
            }

            .emergency-heading p {
                font-size: 14px;
            }

            .medical-card {
                padding: 18px;
            }

            .medical-card h2 {
                font-size: 20px;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .record-item h3 {
                font-size: 17px;
            }

            .record-item p {
                font-size: 14px;
            }
        }
    </style>
</head>

<body>

<main class="emergency-container">

    <?php if ($error_message !== ""): ?>

        <!-- INVALID QR CODE -->
        <div class="invalid-card">
            <h2>QR Code Unavailable</h2>
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>

    <?php else: ?>

        <!-- PAGE HEADER -->
        <div class="emergency-heading">
            <h1>Emergency Medical Information</h1>
            <p>
                Important patient information for emergency assistance.
            </p>
        </div>

        <!-- EMERGENCY ALERT -->
        <div class="emergency-alert">
            <strong>Emergency Notice:</strong>
            If the patient needs urgent medical attention, contact local
            emergency services or take the patient to the nearest hospital.
        </div>

        <!-- PATIENT INFORMATION -->
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

        <!-- MEDICAL CONDITIONS, MEDICATIONS AND SURGERIES -->
        <section class="medical-card">

            <h2>Medical History</h2>

            <?php if (count($conditions) > 0): ?>

                <?php foreach ($conditions as $condition): ?>

                    <div class="record-item">

                        <h3>
                            <?php echo htmlspecialchars($condition['condition_name']); ?>
                        </h3>

                        <?php if (!empty($condition['diagnosed_year'])): ?>
                            <p>
                                <strong>Diagnosed Year:</strong>
                                <?php echo htmlspecialchars($condition['diagnosed_year']); ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($condition['notes'])): ?>
                            <p>
                                <strong>Notes:</strong>
                                <?php echo nl2br(htmlspecialchars($condition['notes'])); ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($condition['medications'])): ?>
                            <p>
                                <strong>Medications:</strong><br>
                                <?php echo nl2br(htmlspecialchars($condition['medications'])); ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($condition['surgeries'])): ?>
                            <p>
                                <strong>Surgeries:</strong><br>
                                <?php echo nl2br(htmlspecialchars($condition['surgeries'])); ?>
                            </p>
                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p class="empty-record">
                    No medical history provided.
                </p>

            <?php endif; ?>

        </section>

        <!-- ALLERGIES -->
        <section class="medical-card">

            <h2>Allergies</h2>

            <?php if (count($allergies) > 0): ?>

                <?php foreach ($allergies as $allergy): ?>

                    <div class="record-item">

                        <h3>
                            <?php echo htmlspecialchars($allergy['allergen_name']); ?>
                        </h3>

                        <p>
                            <strong>Severity:</strong>
                            <?php echo htmlspecialchars($allergy['severity'] ?: 'Not specified'); ?>
                        </p>

                        <?php if (!empty($allergy['reaction_notes'])): ?>
                            <p>
                                <strong>Reaction:</strong><br>
                                <?php echo nl2br(htmlspecialchars($allergy['reaction_notes'])); ?>
                            </p>
                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p class="empty-record">
                    No allergies provided.
                </p>

            <?php endif; ?>

        </section>

        <!-- EMERGENCY CONTACTS -->
        <section class="medical-card">

            <h2>Emergency Contacts</h2>

            <?php if (count($contacts) > 0): ?>

                <?php foreach ($contacts as $contact): ?>

                    <div class="record-item">

                        <h3>
                            <?php echo htmlspecialchars($contact['contact_name']); ?>
                        </h3>

                        <p>
                            <strong>Relationship:</strong>
                            <?php echo htmlspecialchars($contact['relationship'] ?: 'Not specified'); ?>
                        </p>

                        <p>
                            <strong>Phone Number:</strong>
                            <a class="contact-phone"
                               href="tel:<?php echo htmlspecialchars($contact['phone_number']); ?>">
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

                <p class="empty-record">
                    No emergency contacts provided.
                </p>

            <?php endif; ?>

        </section>

        <!-- PRIVACY NOTE -->
        <div class="privacy-note">
            <strong>Important Information:</strong>
            This information is provided for emergency assistance.
            Medical information may be incomplete or outdated. Confirm
            important details with the patient or their healthcare provider.
        </div>

    <?php endif; ?>

</main>

</body>
</html>
