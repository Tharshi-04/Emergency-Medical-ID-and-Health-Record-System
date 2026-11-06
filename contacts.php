
<?php
session_start();

require_once "config/db.php";

// Check whether user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];

$message = "";
$error = "";

// Phone number validation
function validPhone($phone) {
    return preg_match('/^[0-9+\-\s()]{7,30}$/', $phone);
}

// HTML output security
function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

// Get patient profile ID
$patient_sql = "SELECT profile_id
                FROM patient_profiles
                WHERE user_id = ?";

$patient_stmt = $conn->prepare($patient_sql);
$patient_stmt->bind_param("i", $user_id);
$patient_stmt->execute();

$patient_result = $patient_stmt->get_result();
$patient = $patient_result->fetch_assoc();

if (!$patient) {
    die("Patient profile not found. Please complete your medical profile.");
}

$patient_id = (int)$patient["profile_id"];

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    // ADD CONTACT
    if ($action === "add_contact") {

        $contact_name = trim($_POST["contact_name"] ?? "");
        $relationship = trim($_POST["relationship"] ?? "");
        $phone_number = trim($_POST["phone_number"] ?? "");
        $is_primary = isset($_POST["is_primary"]) ? 1 : 0;

        if ($contact_name === "" || $relationship === "" || $phone_number === "") {

            $error = "Please fill in all fields.";

        } elseif (!validPhone($phone_number)) {

            $error = "Please enter a valid phone number.";

        } else {

            try {

                $conn->begin_transaction();

                // Count existing contacts
                $count_sql = "SELECT COUNT(*) AS total
                              FROM emergency_contacts
                              WHERE patient_id = ?";

                $count_stmt = $conn->prepare($count_sql);
                $count_stmt->bind_param("i", $patient_id);
                $count_stmt->execute();

                $count_data = $count_stmt->get_result()->fetch_assoc();

                // First contact becomes primary
                if ((int)$count_data["total"] === 0) {
                    $is_primary = 1;
                }

                // Clear previous primary contact
                if ($is_primary === 1) {

                    $reset_sql = "UPDATE emergency_contacts
                                  SET is_primary = 0
                                  WHERE patient_id = ?";

                    $reset_stmt = $conn->prepare($reset_sql);
                    $reset_stmt->bind_param("i", $patient_id);
                    $reset_stmt->execute();
                }

                // Insert contact
                $insert_sql = "INSERT INTO emergency_contacts
                               (patient_id, contact_name, relationship,
                                phone_number, is_primary)
                               VALUES (?, ?, ?, ?, ?)";

                $insert_stmt = $conn->prepare($insert_sql);

                $insert_stmt->bind_param(
                    "isssi",
                    $patient_id,
                    $contact_name,
                    $relationship,
                    $phone_number,
                    $is_primary
                );

                $insert_stmt->execute();

                $conn->commit();

                $message = "Emergency contact added successfully!";

            } catch (Exception $e) {

                $conn->rollback();
                $error = "Unable to add contact. Please try again.";
            }
        }
    }

    // EDIT CONTACT
    elseif ($action === "edit_contact") {

        $contact_id = (int)($_POST["contact_id"] ?? 0);
        $contact_name = trim($_POST["contact_name"] ?? "");
        $relationship = trim($_POST["relationship"] ?? "");
        $phone_number = trim($_POST["phone_number"] ?? "");

        if ($contact_id <= 0 || $contact_name === "" ||
            $relationship === "" || $phone_number === "") {

            $error = "Please fill in all edit fields.";

        } elseif (!validPhone($phone_number)) {

            $error = "Please enter a valid phone number.";

        } else {

            $update_sql = "UPDATE emergency_contacts
                           SET contact_name = ?,
                               relationship = ?,
                               phone_number = ?
                           WHERE contact_id = ?
                           AND patient_id = ?";

            $update_stmt = $conn->prepare($update_sql);

            $update_stmt->bind_param(
                "sssii",
                $contact_name,
                $relationship,
                $phone_number,
                $contact_id,
                $patient_id
            );

            if ($update_stmt->execute()) {
                $message = "Emergency contact updated successfully!";
            } else {
                $error = "Unable to update contact.";
            }
        }
    }

    // SET PRIMARY CONTACT
    elseif ($action === "set_primary") {

        $contact_id = (int)($_POST["contact_id"] ?? 0);

        try {

            $conn->begin_transaction();

            // Check contact belongs to this patient
            $check_sql = "SELECT contact_id
                          FROM emergency_contacts
                          WHERE contact_id = ?
                          AND patient_id = ?";

            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $contact_id, $patient_id);
            $check_stmt->execute();

            if ($check_stmt->get_result()->num_rows === 0) {
                throw new Exception("Contact not found.");
            }

            // Remove old primary status
            $reset_sql = "UPDATE emergency_contacts
                          SET is_primary = 0
                          WHERE patient_id = ?";

            $reset_stmt = $conn->prepare($reset_sql);
            $reset_stmt->bind_param("i", $patient_id);
            $reset_stmt->execute();

            // Set selected contact as primary
            $primary_sql = "UPDATE emergency_contacts
                            SET is_primary = 1
                            WHERE contact_id = ?
                            AND patient_id = ?";

            $primary_stmt = $conn->prepare($primary_sql);
            $primary_stmt->bind_param("ii", $contact_id, $patient_id);
            $primary_stmt->execute();

            $conn->commit();

            $message = "Primary contact updated successfully!";

        } catch (Exception $e) {

            $conn->rollback();
            $error = "Unable to update primary contact.";
        }
    }

    // DELETE CONTACT
    elseif ($action === "delete_contact") {

        $contact_id = (int)($_POST["contact_id"] ?? 0);

        try {

            $conn->begin_transaction();

            // Check contact belongs to this patient
            $check_sql = "SELECT is_primary
                          FROM emergency_contacts
                          WHERE contact_id = ?
                          AND patient_id = ?";

            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $contact_id, $patient_id);
            $check_stmt->execute();

            $contact_data = $check_stmt->get_result()->fetch_assoc();

            if (!$contact_data) {
                throw new Exception("Contact not found.");
            }

            $was_primary = (int)$contact_data["is_primary"];

            // Delete contact
            $delete_sql = "DELETE FROM emergency_contacts
                           WHERE contact_id = ?
                           AND patient_id = ?";

            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("ii", $contact_id, $patient_id);
            $delete_stmt->execute();

            // If primary was deleted, make another contact primary
            if ($was_primary === 1) {

                $next_sql = "SELECT contact_id
                             FROM emergency_contacts
                             WHERE patient_id = ?
                             ORDER BY contact_id DESC
                             LIMIT 1";

                $next_stmt = $conn->prepare($next_sql);
                $next_stmt->bind_param("i", $patient_id);
                $next_stmt->execute();

                $next_contact = $next_stmt->get_result()->fetch_assoc();

                if ($next_contact) {

                    $new_primary_sql = "UPDATE emergency_contacts
                                        SET is_primary = 1
                                        WHERE contact_id = ?
                                        AND patient_id = ?";

                    $new_primary_stmt = $conn->prepare($new_primary_sql);

                    $new_primary_stmt->bind_param(
                        "ii",
                        $next_contact["contact_id"],
                        $patient_id
                    );

                    $new_primary_stmt->execute();
                }
            }

            $conn->commit();

            $message = "Emergency contact deleted successfully!";

        } catch (Exception $e) {

            $conn->rollback();
            $error = "Unable to delete contact.";
        }
    }
}

// Get saved emergency contacts
$contacts_sql = "SELECT contact_id, contact_name, relationship,
                        phone_number, is_primary
                 FROM emergency_contacts
                 WHERE patient_id = ?
                 ORDER BY is_primary DESC, contact_id DESC";

$contacts_stmt = $conn->prepare($contacts_sql);
$contacts_stmt->bind_param("i", $patient_id);
$contacts_stmt->execute();

$contacts = $contacts_stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Emergency Contacts</title>

    <link rel="stylesheet" href="css/style.css">

    <style>

        .contacts-wrapper {
            max-width: 850px;
            margin: 40px auto;
            padding: 20px;
        }

        .contacts-heading {
            text-align: center;
            margin-bottom: 25px;
        }

        .contacts-heading h1 {
            color: #1E293B;
        }

        .contacts-heading p {
            color: #64748B;
        }

        .contacts-card {
            background: #FFFFFF;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.10);
            margin-bottom: 25px;
        }

        .contacts-card h2 {
            color: #0F766E;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            color: #1E293B;
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

        .primary-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            color: #1E293B;
        }

        .primary-checkbox input {
            width: 17px;
            height: 17px;
            accent-color: #0D9488;
        }

        .save-button {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 8px;
            background: #0D9488;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        .save-button:hover {
            background: #0F766E;
        }

        .contact-item {
            background: #EFF6FF;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .contact-item h3 {
            color: #1E293B;
            margin-top: 0;
        }

        .contact-item p {
            color: #475569;
            margin: 8px 0;
        }

        .primary-label {
            display: inline-block;
            background: #CCFBF1;
            color: #0F766E;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            margin-left: 5px;
        }

        .contact-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .contact-actions button,
        .call-button {
            padding: 9px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            color: white;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }

        .primary-button {
            background: #0D9488;
        }

        .edit-button {
            background: #3B82F6;
        }

        .delete-button {
            background: #DC2626;
        }

        .call-button {
            background: #16A34A;
        }

        .success-message {
            background: #DCFCE7;
            color: #166534;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 18px;
            text-align: center;
        }

        .error-message {
            background: #FEE2E2;
            color: #B91C1C;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 18px;
            text-align: center;
        }

        .empty-message {
            color: #64748B;
            text-align: center;
            padding: 20px;
        }

        .back-link {
            display: inline-block;
            color: #0D9488;
            text-decoration: none;
            font-weight: bold;
            margin-top: 15px;
        }

        .edit-form {
            background: #FFFFFF;
            padding: 18px;
            border-radius: 8px;
            margin-top: 15px;
            border: 1px solid #BFDBFE;
        }

        .edit-form h4 {
            color: #0F766E;
            margin-top: 0;
        }

        @media (max-width: 600px) {
            .contacts-wrapper {
                padding: 12px;
            }

            .contacts-card {
                padding: 20px;
            }
        }

    </style>

</head>

<body>

<div class="contacts-wrapper">

    <div class="contacts-heading">

        <h1>Emergency Contacts</h1>

        <p>
            Add trusted people who can be contacted during emergencies.
        </p>

    </div>

    <?php if ($message !== ""): ?>

        <div class="success-message">
            <?php echo e($message); ?>
        </div>

    <?php endif; ?>

    <?php if ($error !== ""): ?>

        <div class="error-message">
            <?php echo e($error); ?>
        </div>

    <?php endif; ?>


    <!-- ADD CONTACT FORM -->

    <div class="contacts-card">

        <h2>Add Emergency Contact</h2>

        <form method="POST" action="">

            <input type="hidden" name="action" value="add_contact">

            <div class="form-group">

                <label for="contact_name">Contact Name</label>

                <input
                    type="text"
                    id="contact_name"
                    name="contact_name"
                    placeholder="Enter contact person's name"
                    required
                >

            </div>

            <div class="form-group">

                <label for="relationship">Relationship</label>

                <select id="relationship" name="relationship" required>

                    <option value="">Select Relationship</option>
                    <option value="Father">Father</option>
                    <option value="Mother">Mother</option>
                    <option value="Brother">Brother</option>
                    <option value="Sister">Sister</option>
                    <option value="Spouse">Spouse</option>
                    <option value="Friend">Friend</option>
                    <option value="Other">Other</option>

                </select>

            </div>

            <div class="form-group">

                <label for="phone_number">Phone Number</label>

                <input
                    type="tel"
                    id="phone_number"
                    name="phone_number"
                    placeholder="Enter phone number"
                    maxlength="30"
                    required
                >

            </div>

            <div class="primary-checkbox">

                <input
                    type="checkbox"
                    id="is_primary"
                    name="is_primary"
                    value="1"
                >

                <label for="is_primary">
                    Set as Primary Emergency Contact
                </label>

            </div>

            <button type="submit" class="save-button">
                Add Contact
            </button>

        </form>

    </div>


    <!-- SAVED CONTACTS -->

    <div class="contacts-card">

        <h2>Saved Emergency Contacts</h2>

        <?php if ($contacts->num_rows > 0): ?>

            <?php while ($contact = $contacts->fetch_assoc()): ?>

                <div class="contact-item">

                    <h3>

                        <?php echo e($contact["contact_name"]); ?>

                        <?php if ($contact["is_primary"]): ?>

                            <span class="primary-label">
                                Primary Contact
                            </span>

                        <?php endif; ?>

                    </h3>

                    <p>
                        <strong>Relationship:</strong>
                        <?php echo e($contact["relationship"]); ?>
                    </p>

                    <p>
                        <strong>Phone Number:</strong>
                        <?php echo e($contact["phone_number"]); ?>
                    </p>


                    <!-- CLICK TO CALL -->

                    <div class="contact-actions">

                        <a
                            class="call-button"
                            href="tel:<?php echo e(preg_replace('/[^0-9+]/', '', $contact["phone_number"])); ?>"
                        >
                            Call Contact
                        </a>


                        <!-- SET PRIMARY -->

                        <?php if (!$contact["is_primary"]): ?>

                            <form method="POST" action="">

                                <input
                                    type="hidden"
                                    name="action"
                                    value="set_primary"
                                >

                                <input
                                    type="hidden"
                                    name="contact_id"
                                    value="<?php echo (int)$contact["contact_id"]; ?>"
                                >

                                <button type="submit" class="primary-button">
                                    Set as Primary
                                </button>

                            </form>

                        <?php endif; ?>


                        <!-- EDIT BUTTON -->

                        <button
                            type="button"
                            class="edit-button"
                            onclick="toggleEdit(<?php echo (int)$contact["contact_id"]; ?>)"
                        >
                            Edit
                        </button>


                        <!-- DELETE BUTTON -->

                        <form
                            method="POST"
                            action=""
                            onsubmit="return confirm('Are you sure you want to delete this contact?');"
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="delete_contact"
                            >

                            <input
                                type="hidden"
                                name="contact_id"
                                value="<?php echo (int)$contact["contact_id"]; ?>"
                            >

                            <button type="submit" class="delete-button">
                                Delete
                            </button>

                        </form>

                    </div>


                    <!-- EDIT CONTACT FORM -->

                    <div
                        class="edit-form"
                        id="edit-<?php echo (int)$contact["contact_id"]; ?>"
                        style="display:none;"
                    >

                        <h4>Edit Emergency Contact</h4>

                        <form method="POST" action="">

                            <input
                                type="hidden"
                                name="action"
                                value="edit_contact"
                            >

                            <input
                                type="hidden"
                                name="contact_id"
                                value="<?php echo (int)$contact["contact_id"]; ?>"
                            >

                            <div class="form-group">

                                <label>Contact Name</label>

                                <input
                                    type="text"
                                    name="contact_name"
                                    value="<?php echo e($contact["contact_name"]); ?>"
                                    required
                                >

                            </div>

                            <div class="form-group">

                                <label>Relationship</label>

                                <select name="relationship" required>

                                    <?php
                                    $relationships = [
                                        "Father",
                                        "Mother",
                                        "Brother",
                                        "Sister",
                                        "Spouse",
                                        "Friend",
                                        "Other"
                                    ];

                                    foreach ($relationships as $relation):
                                    ?>

                                        <option
                                            value="<?php echo e($relation); ?>"
                                            <?php echo ($contact["relationship"] === $relation) ? "selected" : ""; ?>
                                        >
                                            <?php echo e($relation); ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div class="form-group">

                                <label>Phone Number</label>

                                <input
                                    type="tel"
                                    name="phone_number"
                                    value="<?php echo e($contact["phone_number"]); ?>"
                                    maxlength="30"
                                    required
                                >

                            </div>

                            <button type="submit" class="save-button">
                                Save Changes
                            </button>

                        </form>

                    </div>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <p class="empty-message">
                No emergency contacts added yet.
            </p>

        <?php endif; ?>

        <a href="dashboard.php" class="back-link">
            ← Back to Dashboard
        </a>

    </div>

</div>


<script>

// Show or hide edit form
function toggleEdit(contactId) {

    const editForm = document.getElementById("edit-" + contactId);

    if (editForm.style.display === "none") {
        editForm.style.display = "block";
    } else {
        editForm.style.display = "none";
    }
}

</script>

</body>
</html>
