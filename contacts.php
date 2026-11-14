
<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Check patient role
$role_sql = "SELECT role FROM user_roles WHERE user_id = ? AND role = 'patient'";
$role_stmt = $conn->prepare($role_sql);
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();

if ($role_stmt->get_result()->num_rows === 0) {
    header("Location: login.php");
    exit();
}

// Get patient profile
$profile_sql = "SELECT profile_id FROM patient_profiles WHERE user_id = ?";
$profile_stmt = $conn->prepare($profile_sql);
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile = $profile_stmt->get_result()->fetch_assoc();

if (!$profile) {
    header("Location: medical_profile.php");
    exit();
}

$profile_id = (int)$profile['profile_id'];

$message = "";
$message_type = "";

// Add or update contact
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_contact'])) {

    $contact_id = (int)($_POST['contact_id'] ?? 0);
    $contact_name = trim($_POST['contact_name'] ?? "");
    $relationship = trim($_POST['relationship'] ?? "");
    $phone = trim($_POST['phone_number'] ?? "");
    $is_primary = isset($_POST['is_primary']) ? 1 : 0;

    if ($contact_name === "" || $phone === "") {
        $message = "Please enter the contact name and phone number.";
        $message_type = "error";
    } else {
        try {
            $conn->begin_transaction();

            if ($is_primary === 1) {
                $reset_sql = "UPDATE emergency_contacts
                              SET is_primary = 0
                              WHERE patient_id = ?";
                $reset_stmt = $conn->prepare($reset_sql);
                $reset_stmt->bind_param("i", $profile_id);
                $reset_stmt->execute();
            }

            if ($contact_id > 0) {

                $sql = "UPDATE emergency_contacts
                        SET contact_name = ?,
                            relationship = ?,
                            phone_number = ?,
                            is_primary = ?
                        WHERE contact_id = ? AND patient_id = ?";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "sssiii",
                    $contact_name,
                    $relationship,
                    $phone,
                    $is_primary,
                    $contact_id,
                    $profile_id
                );

                $stmt->execute();
                $message = "Emergency contact updated successfully.";

            } else {

                $sql = "INSERT INTO emergency_contacts
                        (patient_id, contact_name, relationship, phone_number, is_primary)
                        VALUES (?, ?, ?, ?, ?)";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "isssi",
                    $profile_id,
                    $contact_name,
                    $relationship,
                    $phone,
                    $is_primary
                );

                $stmt->execute();
                $message = "Emergency contact added successfully.";
            }

            $conn->commit();
            $message_type = "success";

        } catch (Throwable $e) {
            $conn->rollback();
            $message = "Unable to save the emergency contact. Please try again.";
            $message_type = "error";
        }
    }
}

// Delete contact
if (isset($_GET['delete'])) {

    $contact_id = (int)$_GET['delete'];

    $sql = "DELETE FROM emergency_contacts
            WHERE contact_id = ? AND patient_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $contact_id, $profile_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $message = "Emergency contact deleted successfully.";
        $message_type = "success";
    } else {
        $message = "Unable to delete the contact.";
        $message_type = "error";
    }
}

// Get contact for editing
$edit_contact = null;

if (isset($_GET['edit'])) {

    $contact_id = (int)$_GET['edit'];

    $sql = "SELECT * FROM emergency_contacts
            WHERE contact_id = ? AND patient_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $contact_id, $profile_id);
    $stmt->execute();

    $edit_contact = $stmt->get_result()->fetch_assoc();
}

// Get all contacts
$sql = "SELECT * FROM emergency_contacts
        WHERE patient_id = ?
        ORDER BY is_primary DESC, contact_id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$contacts = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Emergency Contacts</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .contacts-container {
            max-width: 950px;
            margin: 40px auto;
            padding: 20px;
        }

        .contact-form,
        .contact-card {
            background: #fff;
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.07);
        }

        .contact-form input:not([type="checkbox"]) {
            width: 100%;
            padding: 12px;
            margin: 8px 0 18px;
            border: 1px solid #ddd;
            border-radius: 7px;
            box-sizing: border-box;
        }

        .contact-form label {
            font-weight: 600;
        }

        /* Primary checkbox spacing */
        .contact-form .primary-checkbox {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            margin-top: 5px;
        }

        .contact-form input[type="checkbox"] {
            width: auto;
            margin: 0;
            padding: 0;
            vertical-align: middle;
        }

        .contact-form button,
        .action-btn {
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px 3px;
        }

        .save-btn {
            background: #123B63;
            color: white;
        }

        .edit-btn {
            background: #E9A23B;
            color: white;
        }

        .delete-btn {
            background: #DC3545;
            color: white;
        }

        .cancel-btn {
            background: #718096;
            color: white;
        }

        .primary-label {
            color: #108580;
            font-weight: bold;
        }

        .message {
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .success {
            background: #d4edda;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }

        .contact-card p {
            margin: 10px 0;
        }
    </style>
</head>

<body>

<header class="main-header">
    <div class="header-container">
        <h1>Medi Scan</h1>

        <nav class="nav-menu">
            <a href="dashboard.php">Dashboard</a>
            <a href="medical_profile.php">Medical Profile</a>
            <a href="contacts.php">Emergency Contacts</a>
            <a href="logout.php">Logout</a>
        </nav>
    </div>
</header>

<main class="contacts-container">

    <h1>Emergency Contacts</h1>
    <p>Manage the people who should be contacted during an emergency.</p>

    <?php if ($message !== ""): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <section class="contact-form">

        <h2>
            <?php echo $edit_contact ? "Edit Emergency Contact" : "Add Emergency Contact"; ?>
        </h2>

        <form method="POST" action="contacts.php">

            <?php if ($edit_contact): ?>
                <input type="hidden" name="contact_id"
                       value="<?php echo (int)$edit_contact['contact_id']; ?>">
            <?php endif; ?>

            <label>Contact Name</label>
            <input type="text"
                   name="contact_name"
                   placeholder="Enter contact name"
                   value="<?php echo htmlspecialchars($edit_contact['contact_name'] ?? ''); ?>"
                   required>

            <label>Relationship</label>
            <input type="text"
                   name="relationship"
                   placeholder="Example: Father, Mother, Friend"
                   value="<?php echo htmlspecialchars($edit_contact['relationship'] ?? ''); ?>">

            <label>Phone Number</label>
            <input type="tel"
                   name="phone_number"
                   placeholder="Enter phone number"
                   value="<?php echo htmlspecialchars($edit_contact['phone_number'] ?? ''); ?>"
                   required>

            <br>

            <label class="primary-checkbox">
                <input type="checkbox"
                       name="is_primary"
                       value="1"
                    <?php echo (!empty($edit_contact['is_primary'])) ? 'checked' : ''; ?>>
                <span>Set as Primary Contact</span>
            </label>

            <br><br>

            <button type="submit" name="save_contact" class="save-btn">
                <?php echo $edit_contact ? "Update Contact" : "Add Contact"; ?>
            </button>

            <?php if ($edit_contact): ?>
                <a href="contacts.php" class="action-btn cancel-btn">Cancel</a>
            <?php endif; ?>

        </form>
    </section>

    <section>

        <h2>Saved Emergency Contacts</h2>

        <?php if ($contacts->num_rows > 0): ?>

            <?php while ($contact = $contacts->fetch_assoc()): ?>

                <div class="contact-card">

                    <h3>
                        <?php echo htmlspecialchars($contact['contact_name']); ?>

                        <?php if ($contact['is_primary']): ?>
                            <span class="primary-label"> - Primary Contact</span>
                        <?php endif; ?>
                    </h3>

                    <p>
                        <strong>Relationship:</strong>
                        <?php echo htmlspecialchars($contact['relationship'] ?? 'Not specified'); ?>
                    </p>

                    <p>
                        <strong>Phone Number:</strong>
                        <?php echo htmlspecialchars($contact['phone_number']); ?>
                    </p>

                    <a class="action-btn edit-btn"
                       href="contacts.php?edit=<?php echo (int)$contact['contact_id']; ?>">
                        Edit
                    </a>

                    <a class="action-btn delete-btn"
                       href="contacts.php?delete=<?php echo (int)$contact['contact_id']; ?>"
                       onclick="return confirm('Are you sure you want to delete this contact?');">
                        Delete
                    </a>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="contact-card">
                <p>No emergency contacts added yet.</p>
            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
