
<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true) {
    header("Location: ../admin_login.php");
    exit();
}

$success = "";
$error = "";

// Forward accepted donor response to hospital
if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST['forward_to_hospital'])) {

    $response_id = filter_input(INPUT_POST, 'response_id', FILTER_VALIDATE_INT);
    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);

    if (!$response_id || !$request_id) {
        $error = "Invalid response or request.";
    } else {

        $check = $conn->prepare(
            "SELECT response_id
             FROM donor_request_responses
             WHERE response_id = ?
               AND request_id = ?
               AND response_status = 'accepted'
               AND forwarded_to_hospital = 0"
        );

        $check->bind_param("ii", $response_id, $request_id);
        $check->execute();
        $acceptedResponse = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$acceptedResponse) {
            $error = "Accepted response not found or already forwarded.";
        } else {

            $conn->begin_transaction();

            try {

                $forward = $conn->prepare(
                    "UPDATE donor_request_responses
                     SET forwarded_to_hospital = 1
                     WHERE response_id = ?
                       AND request_id = ?
                       AND response_status = 'accepted'
                       AND forwarded_to_hospital = 0"
                );

                $forward->bind_param("ii", $response_id, $request_id);
                $forward->execute();

                if ($forward->affected_rows !== 1) {
                    throw new Exception("Unable to forward donor response.");
                }

                $forward->close();

                $updateRequest = $conn->prepare(
                    "UPDATE hospital_requests
                     SET request_status = 'approved'
                     WHERE request_id = ?"
                );

                $updateRequest->bind_param("i", $request_id);
                $updateRequest->execute();
                $updateRequest->close();

                $conn->commit();

                $success = "Accepted donor response successfully sent to the hospital.";

            } catch (Exception $e) {

                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

// Send request to selected donor
if ($_SERVER["REQUEST_METHOD"] === 'POST'
    && isset($_POST['send_request'])) {

    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $donor_id = filter_input(INPUT_POST, 'donor_id', FILTER_VALIDATE_INT);

    if (!$request_id || !$donor_id) {
        $error = "Please select a valid donor and request.";
    } else {

        $stmt = $conn->prepare(
            "SELECT request_id, request_type, blood_group
             FROM hospital_requests
             WHERE request_id = ?"
        );

        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $hospitalRequest = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $donorSql = "
            SELECT dp.donor_id
            FROM donor_profiles dp
            INNER JOIN donor_registry dr
                ON dp.donor_id = dr.donor_id
            WHERE dp.donor_id = ?
              AND dr.is_active = 1
        ";

        if ($hospitalRequest) {

            if ($hospitalRequest['request_type'] === 'blood') {

                $donorSql .= "
                    AND dr.is_blood_donor = 1
                    AND dp.blood_group = ?
                ";

            } elseif ($hospitalRequest['request_type'] === 'organ') {

                $donorSql .= "
                    AND dr.is_organ_donor = 1
                ";

            } else {
                $donorSql = "";
            }
        }

        if (!$hospitalRequest || $donorSql === "") {

            $error = "Invalid hospital request.";

        } else {

            $stmt = $conn->prepare($donorSql);

            if ($hospitalRequest['request_type'] === 'blood') {
                $blood_group = $hospitalRequest['blood_group'];
                $stmt->bind_param("is", $donor_id, $blood_group);
            } else {
                $stmt->bind_param("i", $donor_id);
            }

            $stmt->execute();
            $matchedDonor = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$matchedDonor) {

                $error = "Selected donor is not eligible for this request.";

            } else {

                $check = $conn->prepare(
                    "SELECT response_id
                     FROM donor_request_responses
                     WHERE request_id = ? AND donor_id = ?"
                );

                $check->bind_param("ii", $request_id, $donor_id);
                $check->execute();

                $alreadySent = $check->get_result()->num_rows > 0;
                $check->close();

                if ($alreadySent) {

                    $error = "This request has already been sent to this donor.";

                } else {

                    $insert = $conn->prepare(
                        "INSERT INTO donor_request_responses
                         (request_id, donor_id, response_status)
                         VALUES (?, ?, 'pending')"
                    );

                    $insert->bind_param("ii", $request_id, $donor_id);

                    if ($insert->execute()) {
                        $success = "Request successfully sent to the donor.";
                    } else {
                        $error = "Unable to send request.";
                    }

                    $insert->close();
                }
            }
        }
    }
}

// Get hospital requests
$result = $conn->query(
    "SELECT r.*, h.hospital_name, h.district AS hospital_district
     FROM hospital_requests r
     INNER JOIN hospital_profiles h
        ON r.hospital_id = h.hospital_id
     ORDER BY r.created_at DESC"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Hospital Requests</title>

<link rel="stylesheet" href="../css/style.css">

<style>
/* Original page styles */
.container {
    max-width: 1200px;
    margin: 35px auto;
    padding: 20px;
}

.card {
    background: white;
    padding: 22px;
    margin-bottom: 22px;
    border-radius: 10px;
    box-shadow: 0 3px 12px rgba(0,0,0,.07);
}

h1, h2 {
    color: #123B63;
}

.request-details {
    line-height: 1.8;
    margin-bottom: 15px;
}

select, button {
    padding: 10px;
    border-radius: 6px;
}

select {
    min-width: 250px;
    border: 1px solid #ccc;
}

button {
    border: 0;
    background: #123B63;
    color: white;
    cursor: pointer;
}

.forward-button {
    background: #168653;
}

.message {
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 6px;
}

.success {
    background: #d4edda;
    color: #155724;
}

.error {
    background: #f8d7da;
    color: #721c24;
}

.accepted-section {
    background: #f0f8ff;
    border-left: 4px solid #168653;
    padding: 16px;
    margin-top: 20px;
    border-radius: 8px;
}

.accepted-item {
    background: white;
    padding: 14px;
    margin-top: 12px;
    border-radius: 7px;
    border: 1px solid #dce5ed;
}

.forwarded {
    color: #168653;
    font-weight: bold;
}

.pending-response {
    color: #805B00;
    font-weight: bold;
}

small {
    color: #555;
}

/* Sidebar design only */
.admin-layout {
    display: flex;
    min-height: 100vh;
}

.sidebar {
    width: 250px;
    min-width: 250px;
    background: #0B2945;
    color: white;
    padding: 25px 15px;
    box-sizing: border-box;
    min-height: 100vh;
}

.sidebar-logo {
    font-size: 27px;
    font-weight: bold;
    color: white;
    margin: 0 0 35px 10px;
}

.sidebar-logo span {
    color: #16A6A1;
}

.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.sidebar-nav a {
    display: block;
    padding: 13px 15px;
    color: #dce7f1;
    text-decoration: none;
    border-radius: 7px;
    font-size: 15px;
    transition: 0.3s;
}

.sidebar-nav a:hover,
.sidebar-nav a.active {
    background: #16476D;
    color: white;
}

.admin-main {
    flex: 1;
    min-width: 0;
}

/* Keep original header content but hide old navigation */
.main-header .nav-menu {
    display: none;
}

.main-header {
    display: none;
}

@media (max-width: 768px) {
    .admin-layout {
        flex-direction: column;
    }

    .sidebar {
        width: 100%;
        min-width: 100%;
        min-height: auto;
        padding: 15px;
    }

    .sidebar-logo {
        margin-bottom: 15px;
    }

    .sidebar-nav {
        flex-direction: row;
        flex-wrap: wrap;
    }

    .sidebar-nav a {
        padding: 10px;
    }

    .container {
        margin: 15px auto;
        padding: 12px;
    }
}
</style>
</head>

<body>

<div class="admin-layout">

    <!-- Sidebar -->
    <aside class="sidebar">

        <h2 class="sidebar-logo">Medi <span>Scan</span></h2>

        <nav class="sidebar-nav">

            <a href="admin_dashboard.php">Dashboard</a>

            <a href="verify_hospitals.php">Hospitals</a>

            <a href="manage_requests.php" class="active">Requests</a>

            <a href="patient_overview.php">Patient Monitoring</a>

            <a href="../admin_logout.php">Logout</a>

        </nav>

    </aside>

    <!-- Main Content -->
    <div class="admin-main">

        <main class="container">

        <h1>Hospital Donation Requests</h1>

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

        <?php if ($result && $result->num_rows > 0): ?>

            <?php while ($request = $result->fetch_assoc()): ?>

                <?php
                $request_id = (int)$request['request_id'];
                $donors = [];

                // Get eligible donors
                $sql = "
                    SELECT dp.donor_id, dp.full_name, dp.blood_group,
                           dp.district, dp.phone_number
                    FROM donor_profiles dp
                    INNER JOIN donor_registry dr
                        ON dp.donor_id = dr.donor_id
                    WHERE dr.is_active = 1
                ";

                if ($request['request_type'] === 'blood') {

                    $sql .= "
                        AND dr.is_blood_donor = 1
                        AND dp.blood_group = ?
                    ";

                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $request['blood_group']);

                } elseif ($request['request_type'] === 'organ') {

                    $sql .= " AND dr.is_organ_donor = 1";
                    $stmt = $conn->prepare($sql);

                } else {
                    $stmt = null;
                }

                if ($stmt) {

                    $stmt->execute();
                    $donorResult = $stmt->get_result();

                    while ($d = $donorResult->fetch_assoc()) {
                        $donors[] = $d;
                    }

                    $stmt->close();
                }

                // Remove donors who already received this request
                if (!empty($donors)) {

                    $check = $conn->prepare(
                        "SELECT donor_id
                         FROM donor_request_responses
                         WHERE request_id = ?"
                    );

                    $check->bind_param("i", $request_id);
                    $check->execute();

                    $sentResult = $check->get_result();

                    $sentIds = [];

                    while ($sent = $sentResult->fetch_assoc()) {
                        $sentIds[] = (int)$sent['donor_id'];
                    }

                    $check->close();

                    $donors = array_values(array_filter(
                        $donors,
                        function ($d) use ($sentIds) {
                            return !in_array(
                                (int)$d['donor_id'],
                                $sentIds,
                                true
                            );
                        }
                    ));
                }

                // Get accepted responses for this request
                $acceptedStmt = $conn->prepare(
                    "SELECT response_id, response_status,
                            responded_at, forwarded_to_hospital
                     FROM donor_request_responses
                     WHERE request_id = ?
                       AND response_status = 'accepted'
                     ORDER BY responded_at DESC"
                );

                $acceptedStmt->bind_param("i", $request_id);
                $acceptedStmt->execute();

                $acceptedResponses = $acceptedStmt->get_result();
                ?>

                <section class="card">

                    <h2>
                        Request #<?php echo $request_id; ?>
                        - <?php echo htmlspecialchars(ucfirst($request['request_type'])); ?>
                    </h2>

                    <div class="request-details">

                        <strong>Hospital:</strong>
                        <?php echo htmlspecialchars($request['hospital_name']); ?><br>

                        <strong>Hospital District:</strong>
                        <?php echo htmlspecialchars($request['hospital_district']); ?><br>

                        <strong>Requirement:</strong>

                        <?php if ($request['request_type'] === 'blood'): ?>

                            Blood Group:
                            <?php echo htmlspecialchars($request['blood_group']); ?>,

                            Units:
                            <?php echo htmlspecialchars($request['required_units']); ?>

                        <?php else: ?>

                            Organ:
                            <?php echo htmlspecialchars($request['organ_type'] ?? 'N/A'); ?>

                        <?php endif; ?>

                        <br>

                        <strong>Required Date:</strong>
                        <?php echo htmlspecialchars($request['required_date']); ?><br>

                        <strong>Urgency:</strong>
                        <?php echo htmlspecialchars($request['urgency']); ?><br>

                        <strong>Description:</strong>
                        <?php echo htmlspecialchars($request['description']); ?><br>

                        <strong>Hospital Request Status:</strong>
                        <?php echo htmlspecialchars($request['request_status']); ?>

                    </div>

                    <h3>Select a Donor</h3>

                    <?php if (!empty($donors)): ?>

                        <form method="POST">

                            <input type="hidden" name="request_id"
                                   value="<?php echo $request_id; ?>">

                            <select name="donor_id" required>

                                <option value="">Select Donor</option>

                                <?php foreach ($donors as $donor): ?>

                                    <option value="<?php echo (int)$donor['donor_id']; ?>">

                                        <?php
                                        echo htmlspecialchars(
                                            $donor['full_name'] . " - " .
                                            $donor['district'] . " - " .
                                            ($donor['blood_group'] ?? 'N/A')
                                        );
                                        ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <button type="submit" name="send_request">
                                Send Request to Donor
                            </button>

                        </form>

                    <?php else: ?>

                        <p>No eligible new donors available for this request.</p>

                    <?php endif; ?>

                    <!-- Accepted Donor Responses -->
                    <div class="accepted-section">

                        <h3>Accepted Donor Responses</h3>

                        <?php if ($acceptedResponses->num_rows > 0): ?>

                            <?php while ($accepted = $acceptedResponses->fetch_assoc()): ?>

                                <div class="accepted-item">

                                    <p>
                                        <strong>Response ID:</strong>
                                        <?php echo (int)$accepted['response_id']; ?>
                                    </p>

                                    <p>
                                        <strong>Donor Response:</strong>
                                        <span class="pending-response">Accepted</span>
                                    </p>

                                    <p>
                                        <strong>Responded At:</strong>
                                        <?php echo htmlspecialchars(
                                            $accepted['responded_at'] ?? 'Not available'
                                        ); ?>
                                    </p>

                                    <?php if ((int)$accepted['forwarded_to_hospital'] === 1): ?>

                                        <p class="forwarded">
                                            Successfully sent to hospital.
                                        </p>

                                    <?php else: ?>

                                        <form method="POST"
                                              onsubmit="return confirm('Send this accepted donor response to the hospital?');">

                                            <input type="hidden"
                                                   name="request_id"
                                                   value="<?php echo $request_id; ?>">

                                            <input type="hidden"
                                                   name="response_id"
                                                   value="<?php echo (int)$accepted['response_id']; ?>">

                                            <button type="submit"
                                                    name="forward_to_hospital"
                                                    class="forward-button">

                                                Send to Hospital

                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </div>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <p>No donors have accepted this request yet.</p>

                        <?php endif; ?>

                    </div>

                </section>

                <?php $acceptedStmt->close(); ?>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="card">
                No hospital requests available.
            </div>

        <?php endif; ?>

        </main>

        

    </div>

</div>

</body>
</html>

<?php
$conn->close();
?>
