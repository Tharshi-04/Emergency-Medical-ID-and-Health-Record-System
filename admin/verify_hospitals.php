<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check admin permission
$user_id = $_SESSION['user_id'];

$adminQuery = $conn->prepare(
    "SELECT is_admin FROM users WHERE user_id = ?"
);
$adminQuery->bind_param("i", $user_id);
$adminQuery->execute();
$admin = $adminQuery->get_result()->fetch_assoc();

if (!$admin || (int)$admin['is_admin'] !== 1) {
    die("Access denied. Administrator permission required.");
}

$success = "";
$error = "";

// Approve or reject hospital
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $hospital_id = filter_input(
        INPUT_POST,
        'hospital_id',
        FILTER_VALIDATE_INT
    );

    $action = $_POST['action'] ?? '';

    if (!$hospital_id || !in_array($action, ['verified', 'rejected'], true)) {
        $error = "Invalid request.";
    } else {

        $update = $conn->prepare(
            "UPDATE hospital_profiles
             SET verification_status = ?
             WHERE hospital_id = ?
               AND verification_status = 'pending'"
        );

        $update->bind_param("si", $action, $hospital_id);

        if ($update->execute() && $update->affected_rows === 1) {
            $success = $action === 'verified'
                ? "Hospital verified successfully."
                : "Hospital rejected successfully.";
        } else {
            $error = "Hospital not found or its status has already changed.";
        }
    }
}

// Get hospital records
$result = $conn->query(
    "SELECT
        h.*,
        u.email
     FROM hospital_profiles h
     INNER JOIN users u ON h.user_id = u.user_id
     ORDER BY
        CASE
            WHEN h.verification_status = 'pending' THEN 1
            ELSE 2
        END,
        h.created_at DESC"
);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Hospital Verification</title>

    <link rel="stylesheet" href="../css/style.css">

    <style>
        .admin-container {
            max-width: 1150px;
            margin: 40px auto;
            padding: 20px;
        }

        .page-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 4px 18px rgba(0,0,0,0.06);
        }

        h1 {
            color: #123B63;
            margin-bottom: 20px;
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

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #E2E8F0;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #F4F8FC;
            color: #123B63;
        }

        .status {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            text-transform: capitalize;
        }

        .pending {
            background: #FFF2CC;
            color: #805B00;
        }

        .verified {
            background: #DFF5E8;
            color: #167344;
        }

        .rejected {
            background: #FDECEC;
            color: #B42332;
        }

        .action-form {
            display: flex;
            gap: 8px;
        }

        .action-button {
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
        }

        .approve {
            background: #168653;
        }

        .reject {
            background: #DC3545;
        }

        .empty-message {
            background: #F4F8FC;
            padding: 20px;
            border-radius: 8px;
            color: #718096;
        }
    </style>
</head>

<body>

<header class="main-header">
    <div class="header-container">
        <h2>Emergency Medical ID</h2>

        <nav class="nav-menu">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="verify_hospitals.php">Hospitals</a>
            <a href="manage_requests.php">Requests</a>
            <a href="medical_access.php">Medical Access</a>
            <a href="../logout.php">Logout</a>
        </nav>
    </div>
</header>

<main class="admin-container">

    <div class="page-card">

        <h1>Hospital Verification</h1>

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

        <?php if ($result->num_rows > 0): ?>

            <div class="table-wrapper">

                <table>
                    <thead>
                        <tr>
                            <th>Hospital Name</th>
                            <th>Email</th>
                            <th>Registration No.</th>
                            <th>Contact</th>
                            <th>District</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php while ($hospital = $result->fetch_assoc()): ?>

                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($hospital['hospital_name']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($hospital['official_email']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($hospital['registration_number'] ?? 'N/A'); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($hospital['contact_number']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($hospital['district']); ?>
                                </td>

                                <td>
                                    <span class="status <?php echo htmlspecialchars($hospital['verification_status']); ?>">
                                        <?php echo htmlspecialchars($hospital['verification_status']); ?>
                                    </span>
                                </td>

                                <td>

                                    <?php if ($hospital['verification_status'] === 'pending'): ?>

                                        <form method="POST" class="action-form">

                                            <input
                                                type="hidden"
                                                name="hospital_id"
                                                value="<?php echo (int)$hospital['hospital_id']; ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="action"
                                                value="verified"
                                                class="action-button approve"
                                                onclick="return confirm('Verify this hospital?')"
                                            >
                                                Verify
                                            </button>

                                            <button
                                                type="submit"
                                                name="action"
                                                value="rejected"
                                                class="action-button reject"
                                                onclick="return confirm('Reject this hospital?')"
                                            >
                                                Reject
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        No action available

                                    <?php endif; ?>

                                </td>
                            </tr>

                        <?php endwhile; ?>

                    </tbody>
                </table>

            </div>

        <?php else: ?>

            <div class="empty-message">
                No hospital profiles have been registered yet.
            </div>

        <?php endif; ?>

    </div>

</main>

<footer class="main-footer">
    <p>© <?php echo date("Y"); ?> Emergency Medical ID System</p>
</footer>

</body>
</html>