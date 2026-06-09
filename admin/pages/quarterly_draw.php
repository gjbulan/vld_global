

<?php
$from = isset($_GET['from']) ? trim($_GET['from']) : date('Y-m-01');
$to = isset($_GET['to']) ? trim($_GET['to']) : date('Y-m-d');
$export = isset($_GET['export']) && $_GET['export'] === 'csv';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-d');
}

$from_datetime = $from . " 00:00:00";
$to_datetime = $to . " 23:59:59";

$sql = "
    SELECT 
        m.id,
        m.member_code,
        m.username,
        m.full_name,
        m.email,
        m.contact_no,
        p.name AS package_name,
        COUNT(d.id) AS dominance_directs,
        GROUP_CONCAT(
            CONCAT(d.username, ' - ', d.full_name, ' (', d.member_code, ')')
            ORDER BY d.created_at ASC
            SEPARATOR ' | '
        ) AS dominance_direct_list
    FROM members m
    LEFT JOIN packages p ON m.package_id = p.id
    INNER JOIN members d 
        ON d.sponsor_id = m.id
        AND d.status = 'active'
        AND d.created_at BETWEEN ? AND ?
    INNER JOIN packages dp 
        ON d.package_id = dp.id
        AND LOWER(dp.name) = 'dominance'
    WHERE m.status = 'active'
    GROUP BY m.id
    HAVING dominance_directs >= 5
    ORDER BY dominance_directs DESC, m.full_name ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $from_datetime, $to_datetime);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

if ($export) {
    $filename = "quarterly_draw_" . $from . "_to_" . $to . ".csv";

    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=" . $filename);

    $output = fopen("php://output", "w");

    fputcsv($output, [
        "Member Code",
        "Username",
        "Full Name",
        "Email",
        "Contact No",
        "Package",
        "Qualified Dominance Directs",
        "Dominance Direct List",
        "Date From",
        "Date To"
    ]);

    foreach ($rows as $row) {
        fputcsv($output, [
            $row['member_code'],
            $row['username'],
            $row['full_name'],
            $row['email'],
            $row['contact_no'],
            $row['package_name'],
            $row['dominance_directs'],
            $row['dominance_direct_list'],
            $from,
            $to
        ]);
    }

    fclose($output);
    exit;
}
?>

<div class="admin-page-header">
    <div>
        <h2>Quarterly Draw Report</h2>
        <p>Members qualified by having at least 5 direct Dominance sponsors within the selected date range.</p>
    </div>
</div>

<div class="admin-card mb-4">
    <div class="admin-card-header">
        <h5>Filter Qualification Date</h5>
    </div>

    <form method="GET" class="row g-3">
        <input type="hidden" name="page" value="quarterly_draw">

        <div class="col-md-4">
            <label class="form-label">From Date</label>
            <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($from); ?>" required>
        </div>

        <div class="col-md-4">
            <label class="form-label">To Date</label>
            <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($to); ?>" required>
        </div>

        <div class="col-md-4 d-flex align-items-end gap-2">
            <button type="submit" class="btn admin-btn-primary w-50">Filter</button>

            <a 
                href="index.php?page=quarterly_draw&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>&export=csv" 
                class="btn btn-success w-50"
            >
                Export CSV
            </a>
        </div>
    </form>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="admin-mini-card">
            <span>Qualified Members</span>
            <h4><?php echo count($rows); ?></h4>
        </div>
    </div>

    <div class="col-md-4">
        <div class="admin-mini-card">
            <span>Required Direct Dominance</span>
            <h4>5</h4>
        </div>
    </div>

    <div class="col-md-4">
        <div class="admin-mini-card">
            <span>Date Range</span>
            <h4 style="font-size:18px;">
                <?php echo htmlspecialchars($from); ?> to <?php echo htmlspecialchars($to); ?>
            </h4>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h5>Qualified Members for Quarterly Draw</h5>
    </div>

    <div class="table-responsive">
        <table class="table admin-table align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Member Code</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Contact No</th>
                    <th>Package</th>
                    <th>Direct Dominance Count</th>
                    <th>Direct Dominance Members</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($rows) > 0): ?>
                    <?php $i = 1; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['member_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['contact_no']); ?></td>
                            <td><?php echo htmlspecialchars($row['package_name']); ?></td>
                            <td>
                                <span class="badge bg-success">
                                    <?php echo (int)$row['dominance_directs']; ?>
                                </span>
                            </td>
                            <td style="min-width:320px;">
                                <?php echo htmlspecialchars($row['dominance_direct_list']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            No qualified members found for this date range.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>