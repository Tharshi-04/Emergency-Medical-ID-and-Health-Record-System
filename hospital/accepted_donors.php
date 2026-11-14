
<?php
session_start();

require_once "../config/db.php";

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Get hospital profile
$hospital_stmt = $conn->prepare(
    "SELECT hospital_id, hospital_name
     FROM hospital_profiles
     WHERE user_id = ?
     AND verification_status = 'verified'
     LIMIT 1"
);

$hospital_stmt->bind_param("i", $user_id);
$hospital_stmt->execute();

$hospital_result = $hospital_stmt->get_result();
$hospital = $hospital_result->fetch_assoc();
$hospital_stmt->close();

if (!$hospital) {
    die("Your hospital account is not verified or hospital profile is missing.");
}

$hospital_id = (int) $hospital['hospital_id'];

// Get accepted donors forwarded to this hospital
$donors_stmt = $conn->prepare(
    "SELECT
        dp.full_name,
        dp.phone_number,
        dp.district,
        u.email,
        dp.blood_group,
        hr.request_type,
        hr.request_id,
        drr.responded_at

     FROM donor_request_responses drr

     INNER JOIN donor_profiles dp
        ON drr.donor_id = dp.donor_id

     INNER JOIN users u
        ON dp.user_id = u.user_id

     INNER JOIN donor_registry dr
        ON dp.donor_id = dr.donor_id

     INNER JOIN hospital_requests hr
        ON drr.request_id = hr.request_id

     WHERE hr.hospital_id = ?
       AND hr.request_status = 'approved'
       AND drr.response_status = 'accepted'
       AND drr.forwarded_to_hospital = 1
       AND dr.share_contact_on_accept = 1

     ORDER BY drr.responded_at DESC"
);

$donors_stmt->bind_param("i", $hospital_id);
$donors_stmt->execute();

$donors_result = $donors_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Accepted Donors</title>

    <link rel="stylesheet" href="../css/style.css">

    <style>
        .page-container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 20px;
        }

        .donor-card {
            background: white;
            padding: 22px;
            margin-bottom: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }

        .donor-card h2 {
            color: #123B63;
            margin-bottom: 15px;
        }

        .donor-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .detail-item {
            background: #f4f8fc;
            padding: 13px;
            border-radius: 8px;
            overflow-wrap: anywhere;
        }

        .detail-item strong {
            display: block;
            color: #718096;
            margin-bottom: 7px;
        }

        .no-donors {
            background: white;
            padding: 30px;
            text-align: center;
            border-radius: 12px;
        }

        .notice {
            background: #e6f7ee;
            color: #167044;
            padding: 14px;
            border-radius: 8px;
            margin: 20px 0;
        }
    </style>
</head>

<body>

<div class="page-container">

    <h1>Accepted Donors</h1>

    <h3>
        <?php echo htmlspecialchars($hospital['hospital_name']); ?>
    </h3>

    <div class="notice">
        Contact details are displayed only for donors who have accepted
        your hospital's donation requests, whose contact-sharing
        permission is recorded, and whose responses have been forwarded
        by the administrator.
    </div>

    <?php if ($donors_result->num_rows > 0): ?>

        <?php while ($donor = $donors_result->fetch_assoc()): ?>

            <div class="donor-card">

                <h2>
                    <?php echo htmlspecialchars($donor['full_name']); ?>
                </h2>

                <div class="donor-details">

                    <div class="detail-item">
                        <strong>Phone Number</strong>

                        <?php
                        echo htmlspecialchars(
                            $donor['phone_number'] ?? 'Not available'
                        );
                        ?>
                    </div>

                    <div class="detail-item">
                        <strong>Email Address</strong>

                        <?php
                        echo htmlspecialchars(
                            $donor['email'] ?? 'Not available'
                        );
                        ?>
                    </div>

                    <div class="detail-item">
                        <strong>Blood Group</strong>

                        <?php
                        echo htmlspecialchars(
                            $donor['blood_group'] ?? 'Not specified'
                        );
                        ?>
                    </div>

                    <div class="detail-item">
                        <strong>District</strong>

                        <?php
                        echo htmlspecialchars(
                            $donor['district'] ?? 'Not available'
                        );
                        ?>
                    </div>

                    <div class="detail-item">
                        <strong>Request Type</strong>

                        <?php
                        echo htmlspecialchars(
                            ucfirst($donor['request_type'])
                        );
                        ?>
                    </div>

                    <div class="detail-item">
                        <strong>Request ID</strong>

                        <?php
                        echo htmlspecialchars($donor['request_id']);
                        ?>
                    </div>

                    <div class="detail-item">
                        <strong>Response Date</strong>

                        <?php
                        echo htmlspecialchars(
                            $donor['responded_at'] ?? 'Not available'
                        );
                        ?>
                    </div>

                </div>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="no-donors">
            <h2>No Accepted Donors Yet</h2>

            <p>
                Donor details will appear here when eligible donors
                accept your donation requests, consent to contact
                sharing, and the administrator forwards their responses.
            </p>
        </div>

    <?php endif; ?>

    <p>
        <a href="hospital_dashboard.php">
            ← Back to Hospital Dashboard
        </a>
    </p>

</div>

</body>
</html>

<?php
$donors_stmt->close();
$conn->close();
?>
