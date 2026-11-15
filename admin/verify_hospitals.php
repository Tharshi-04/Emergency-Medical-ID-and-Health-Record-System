
<?php
session_start();

if (!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true) {
    header("Location: ../admin_login.php");
    exit();
}

require_once "../config/db.php";

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

        $update->close();
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

    <title>Hospital Verification | Medi Scan</title>

    <link rel="stylesheet" href="../css/style.css">

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #F4F8FC;
            color: #263746;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */

        .sidebar {
            width: 250px;
            min-height: 100vh;
            background: #0B2945;
            color: white;
            padding: 30px 20px;
            flex-shrink: 0;
        }

        .logo {
            font-size: 27px;
            font-weight: 800;
            margin-bottom: 45px;
            white-space: nowrap;
        }

        .logo span {
            color: #16A6A1;
        }

        .sidebar nav a {
            display: block;
            color: #D5E0EA;
            text-decoration: none;
            padding: 14px 15px;
            margin-bottom: 10px;
            border-radius: 7px;
            font-size: 15px;
            font-weight: 500;
            transition: 0.3s;
        }

        .sidebar nav a:hover,
        .sidebar nav a.active {
            background: #1E5A8A;
            color: white;
        }

        /* MAIN CONTENT */

        .main-content {
            flex: 1;
            padding: 35px;
            min-width: 0;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: #123B63;
            font-size: 30px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #718096;
            font-size: 15px;
        }

        /* MESSAGE */

        .message {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .success {
            background: #E6F7EF;
            color: #167347;
            border: 1px solid #B7E8CD;
        }

        .error {
            background: #FDECEC;
            color: #B42332;
            border: 1px solid #F5C2C7;
        }

        /* TABLE SECTION */

        .section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 4px 15px rgba(18,59,99,0.05);
        }

        .section-header {
            margin-bottom: 22px;
        }

        .section-header h2 {
            color: #123B63;
            font-size: 20px;
            margin-bottom: 6px;
        }

        .section-description {
            color: #718096;
            font-size: 14px;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px 13px;
            border-bottom: 1px solid #E2E8F0;
            text-align: left;
            vertical-align: middle;
            font-size: 14px;
        }

        th {
            background: #F4F8FC;
            color: #123B63;
            font-weight: 700;
            white-space: nowrap;
        }

        td {
            color: #526477;
        }

        tbody tr:hover {
            background: #F8FBFE;
        }

        /* STATUS */

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .status.pending {
            background: #FFF2CC;
            color: #805B00;
        }

        .status.verified {
            background: #DFF5E8;
            color: #167344;
        }

        .status.rejected {
            background: #FDECEC;
            color: #B42332;
        }

        /* ACTION BUTTONS */

        .action-form {
            display: flex;
            gap: 8px;
        }

        .action-button {
            border: none;
            padding: 9px 13px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: 0.3s;
        }

        .approve {
            background: #168653;
        }

        .approve:hover {
            background: #116B41;
        }

        .reject {
            background: #DC3545;
        }

        .reject:hover {
            background: #B92332;
        }

        .no-action {
            color: #718096;
            font-size: 13px;
        }

        /* EMPTY MESSAGE */

        .empty-message {
            background: #F4F8FC;
            padding: 25px;
            border-radius: 8px;
            color: #718096;
            text-align: center;
            font-size: 14px;
        }

        /* RESPONSIVE DESIGN */

        @media (max-width: 768px) {

            .dashboard {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                min-height: auto;
                padding: 20px;
            }

            .logo {
                margin-bottom: 20px;
            }

            .sidebar nav {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }

            .sidebar nav a {
                margin: 0;
                padding: 10px 12px;
            }

            .main-content {
                padding: 20px;
            }

        }

        @media (max-width: 550px) {

            .main-content {
                padding: 15px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .section {
                padding: 18px;
            }

        }

    </style>

</head>

<body>

<div class="dashboard">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="logo">
            Medi <span>Scan</span>
        </div>

        <nav>

            <a href="admin_dashboard.php">
                Dashboard
            </a>

            <a href="verify_hospitals.php" class="active">
                Hospitals
            </a>

            <a href="manage_requests.php">
                Requests
            </a>

            <a href="patient_overview.php">
                Patient Monitoring
            </a>

            <a href="../admin_logout.php">
                Logout
            </a>

        </nav>

    </aside>


    <!-- MAIN CONTENT -->

    <main class="main-content">

        <!-- PAGE HEADER -->

        <div class="page-header">

            <h1>Hospital Verification</h1>

            <p>
                View hospital details and manage verification requests.
            </p>

        </div>


        <!-- SUCCESS MESSAGE -->

        <?php if ($success): ?>

            <div class="message success">
                <?php echo htmlspecialchars($success); ?>
            </div>

        <?php endif; ?>


        <!-- ERROR MESSAGE -->

        <?php if ($error): ?>

            <div class="message error">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>


        <!-- HOSPITAL TABLE -->

        <section class="section">

            <div class="section-header">

                <h2>Registered Hospitals</h2>

                <p class="section-description">
                    Review hospital information and approve or reject pending verification requests.
                </p>

            </div>


            <?php if ($result && $result->num_rows > 0): ?>

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

                                            <span class="no-action">
                                                No action available
                                            </span>

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

        </section>

    </main>

</div>

</body>

</html>
