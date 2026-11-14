
<?php
session_start();

if (!isset($_SESSION["admin_logged_in"])) {
    header("Location: ../admin_login.php");
    exit();
}

require_once "../config/db.php";

$search = trim($_GET["search"] ?? "");
$bloodGroup = trim($_GET["blood_group"] ?? "");
$selectedPatient = isset($_GET["patient_id"]) ? (int)$_GET["patient_id"] : 0;

$bloodGroups = ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"];

$sql = "
    SELECT
        pp.profile_id,
        pp.full_name,
        pp.dob,
        pp.gender,
        pp.blood_group,
        pp.height_cm,
        pp.weight_kg,
        pp.qr_token,
        (
            SELECT COUNT(*)
            FROM medical_conditions mc
            WHERE mc.patient_id = pp.profile_id
        ) AS condition_count,
        (
            SELECT COUNT(*)
            FROM allergies a
            WHERE a.patient_id = pp.profile_id
        ) AS allergy_count
    FROM patient_profiles pp
    WHERE pp.full_name LIKE ?
";

$params = ["%" . $search . "%"];
$types = "s";

if ($bloodGroup !== "" && in_array($bloodGroup, $bloodGroups, true)) {
    $sql .= " AND pp.blood_group = ?";
    $params[] = $bloodGroup;
    $types .= "s";
}

$sql .= " ORDER BY pp.profile_id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$patients = $stmt->get_result();

$patient = null;
$conditions = [];
$allergies = [];
$contacts = [];

if ($selectedPatient > 0) {
    $stmt = $conn->prepare("
        SELECT profile_id, full_name, dob, gender,
               blood_group, height_cm, weight_kg, qr_token
        FROM patient_profiles
        WHERE profile_id = ?
    ");
    $stmt->bind_param("i", $selectedPatient);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();

    if ($patient) {
        $stmt = $conn->prepare("
            SELECT condition_name, diagnosed_year, notes
            FROM medical_conditions
            WHERE patient_id = ?
        ");
        $stmt->bind_param("i", $selectedPatient);
        $stmt->execute();
        $conditions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt = $conn->prepare("
            SELECT allergen_name, severity, reaction_notes
            FROM allergies
            WHERE patient_id = ?
        ");
        $stmt->bind_param("i", $selectedPatient);
        $stmt->execute();
        $allergies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt = $conn->prepare("
            SELECT contact_name, relationship, phone_number, is_primary
            FROM emergency_contacts
            WHERE patient_id = ?
            ORDER BY is_primary DESC
        ");
        $stmt->bind_param("i", $selectedPatient);
        $stmt->execute();
        $contacts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Monitoring | Admin</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f3f6fa;
    color: #263445;
}

.sidebar {
    width: 240px;
    height: 100vh;
    background: #183653;
    position: fixed;
    left: 0;
    top: 0;
    padding: 28px 18px;
}

.sidebar h2 {
    color: white;
    margin-bottom: 35px;
    font-size: 21px;
}

.sidebar a {
    display: block;
    color: #e5edf5;
    text-decoration: none;
    padding: 13px 14px;
    margin-bottom: 9px;
    border-radius: 7px;
}

.sidebar a:hover,
.sidebar a.active {
    background: #2d5276;
}

.sidebar-logo {
    color: white;
    font-size: 27px;
    font-weight: bold;
}

.sidebar-logo span {
    color: #16A6A1;
}

.main {
    margin-left: 240px;
    padding: 30px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    gap: 15px;
    flex-wrap: wrap;
}

.topbar h1 {
    font-size: 27px;
    color: #183653;
}

.back-link {
    text-decoration: none;
    color: white;
    background: #183653;
    padding: 11px 17px;
    border-radius: 6px;
}

.panel {
    background: white;
    padding: 23px;
    border-radius: 10px;
    margin-bottom: 25px;
    box-shadow: 0 2px 9px rgba(0,0,0,0.05);
}

.panel h2 {
    color: #183653;
    margin-bottom: 18px;
    font-size: 20px;
}

.filters {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.filters input,
.filters select {
    padding: 11px;
    border: 1px solid #d5dce5;
    border-radius: 6px;
    min-width: 190px;
}

.btn {
    border: none;
    padding: 11px 17px;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 14px;
}

.search-btn {
    background: #183653;
    color: white;
}

.view-btn {
    background: #2878b9;
    color: white;
}

.clear-btn {
    background: #e8edf2;
    color: #263445;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

.table-wrap {
    overflow-x: auto;
}

th, td {
    padding: 14px 12px;
    text-align: left;
    border-bottom: 1px solid #e8edf2;
    font-size: 14px;
}

th {
    background: #f4f7fa;
    color: #183653;
}

.status {
    padding: 6px 10px;
    border-radius: 15px;
    font-size: 12px;
    background: #e1f5e9;
    color: #197044;
}

.missing {
    background: #fff0d8;
    color: #99600b;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 15px;
}

.detail-item {
    background: #f5f8fb;
    padding: 15px;
    border-radius: 7px;
}

.detail-item span {
    display: block;
    color: #6b7887;
    font-size: 13px;
    margin-bottom: 7px;
}

.detail-item strong {
    color: #183653;
}

.section-title {
    margin: 25px 0 13px;
    color: #183653;
    font-size: 17px;
}

.empty {
    padding: 20px;
    text-align: center;
    color: #788493;
}

@media(max-width: 800px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }

    .main {
        margin-left: 0;
        padding: 18px;
    }

    .details-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<aside class="sidebar">
    <h2 class="sidebar-logo">Medi <span>Scan</span></h2>

    <a href="admin_dashboard.php">Dashboard</a>
    <a href="verify_hospitals.php">Verify Hospitals</a>
    <a href="manage_requests.php">Manage Requests</a>
    <a href="patient_overview.php" class="active">Patient Monitoring</a>
    <a href="../admin_logout.php">Logout</a>
</aside>

<main class="main">

    <div class="topbar">
        <h1>Patient Monitoring</h1>
        
    </div>

    <section class="panel">
        <h2>Find Patients</h2>

        <form method="GET" class="filters">
            <input
                type="text"
                name="search"
                placeholder="Search patient name..."
                value="<?= htmlspecialchars($search) ?>"
            >

            <select name="blood_group">
                <option value="">All Blood Groups</option>
                <?php foreach ($bloodGroups as $group): ?>
                    <option value="<?= htmlspecialchars($group) ?>"
                        <?= $bloodGroup === $group ? "selected" : "" ?>>
                        <?= htmlspecialchars($group) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn search-btn">Search</button>
            <a href="patient_overview.php" class="btn clear-btn">Clear</a>
        </form>
    </section>

    <section class="panel">
        <h2>Registered Patients</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Patient Name</th>
                        <th>Gender</th>
                        <th>Blood Group</th>
                        <th>Medical Conditions</th>
                        <th>Allergies</th>
                        <th>QR Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($patients->num_rows > 0): ?>
                    <?php while ($row = $patients->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row["full_name"]) ?></td>
                            <td><?= htmlspecialchars($row["gender"] ?? "Not added") ?></td>
                            <td><?= htmlspecialchars($row["blood_group"] ?? "Not added") ?></td>
                            <td><?= (int)$row["condition_count"] ?></td>
                            <td><?= (int)$row["allergy_count"] ?></td>
                            <td>
                                <?php if (!empty($row["qr_token"])): ?>
                                    <span class="status">Generated</span>
                                <?php else: ?>
                                    <span class="status missing">Not generated</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="btn view-btn"
                                   href="patient_overview.php?patient_id=<?= (int)$row["profile_id"] ?>">
                                   View Profile
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty">No patients found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($selectedPatient > 0): ?>

        <?php if ($patient): ?>

            <section class="panel">
                <h2>Patient Profile</h2>

                <div class="details-grid">
                    <div class="detail-item">
                        <span>Full Name</span>
                        <strong><?= htmlspecialchars($patient["full_name"]) ?></strong>
                    </div>

                    <div class="detail-item">
                        <span>Date of Birth</span>
                        <strong><?= htmlspecialchars($patient["dob"] ?? "Not added") ?></strong>
                    </div>

                    <div class="detail-item">
                        <span>Gender</span>
                        <strong><?= htmlspecialchars($patient["gender"] ?? "Not added") ?></strong>
                    </div>

                    <div class="detail-item">
                        <span>Blood Group</span>
                        <strong><?= htmlspecialchars($patient["blood_group"] ?? "Not added") ?></strong>
                    </div>

                    <div class="detail-item">
                        <span>Height</span>
                        <strong><?= htmlspecialchars($patient["height_cm"] ?? "Not added") ?> cm</strong>
                    </div>

                    <div class="detail-item">
                        <span>Weight</span>
                        <strong><?= htmlspecialchars($patient["weight_kg"] ?? "Not added") ?> kg</strong>
                    </div>

                    <div class="detail-item">
                        <span>QR Code Status</span>
                        <strong><?= !empty($patient["qr_token"]) ? "Generated" : "Not generated" ?></strong>
                    </div>
                </div>

                <h3 class="section-title">Medical Conditions</h3>

                <?php if (count($conditions) > 0): ?>
                    <div class="table-wrap">
                        <table>
                            <tr>
                                <th>Condition</th>
                                <th>Diagnosed Year</th>
                                <th>Notes</th>
                            </tr>

                            <?php foreach ($conditions as $condition): ?>
                                <tr>
                                    <td><?= htmlspecialchars($condition["condition_name"]) ?></td>
                                    <td><?= htmlspecialchars($condition["diagnosed_year"] ?? "Not added") ?></td>
                                    <td><?= htmlspecialchars($condition["notes"] ?? "No notes") ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="empty">No medical conditions added.</p>
                <?php endif; ?>

                <h3 class="section-title">Allergies</h3>

                <?php if (count($allergies) > 0): ?>
                    <div class="table-wrap">
                        <table>
                            <tr>
                                <th>Allergen</th>
                                <th>Severity</th>
                                <th>Reaction Notes</th>
                            </tr>

                            <?php foreach ($allergies as $allergy): ?>
                                <tr>
                                    <td><?= htmlspecialchars($allergy["allergen_name"]) ?></td>
                                    <td><?= htmlspecialchars($allergy["severity"] ?? "Not added") ?></td>
                                    <td><?= htmlspecialchars($allergy["reaction_notes"] ?? "No notes") ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="empty">No allergies added.</p>
                <?php endif; ?>

                <h3 class="section-title">Emergency Contacts</h3>

                <?php if (count($contacts) > 0): ?>
                    <div class="table-wrap">
                        <table>
                            <tr>
                                <th>Contact Name</th>
                                <th>Relationship</th>
                                <th>Phone Number</th>
                                <th>Primary Contact</th>
                            </tr>

                            <?php foreach ($contacts as $contact): ?>
                                <tr>
                                    <td><?= htmlspecialchars($contact["contact_name"]) ?></td>
                                    <td><?= htmlspecialchars($contact["relationship"]) ?></td>
                                    <td><?= htmlspecialchars($contact["phone_number"]) ?></td>
                                    <td><?= $contact["is_primary"] ? "Yes" : "No" ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="empty">No emergency contacts added.</p>
                <?php endif; ?>

            </section>

        <?php else: ?>
            <section class="panel">
                <p class="empty">Patient profile not found.</p>
            </section>
        <?php endif; ?>

    <?php endif; ?>

</main>
</body>
</html>
