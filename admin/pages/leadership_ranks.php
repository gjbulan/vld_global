<?php
processAllTravelRankQualifications($conn);

$rank_levels = getTravelRankLevels();
$search = trim($_GET['search'] ?? '');
$rank_level = isset($_GET['rank_level']) ? (int)$_GET['rank_level'] : 0;
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$export_csv = ($_GET['export'] ?? '') === 'csv';

if (!array_key_exists($rank_level, $rank_levels)) {
    $rank_level = 0;
}

if ($date_from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $date_from = '';
}

if ($date_to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $date_to = '';
}

$where = [];
$params = [];
$param_types = '';

if ($search !== '') {
    $search_like = '%' . $search . '%';
    $where[] = "(
        m.member_code LIKE ?
        OR m.username LIKE ?
        OR m.full_name LIKE ?
        OR m.contact_no LIKE ?
        OR p.name LIKE ?
    )";

    for ($i = 0; $i < 5; $i++) {
        $params[] = $search_like;
        $param_types .= 's';
    }
}

if ($rank_level > 0) {
    $where[] = "cr.rank_level = ?";
    $params[] = $rank_level;
    $param_types .= 'i';
}

if ($date_from !== '') {
    $where[] = "DATE(cr.qualified_at) >= ?";
    $params[] = $date_from;
    $param_types .= 's';
}

if ($date_to !== '') {
    $where[] = "DATE(cr.qualified_at) <= ?";
    $params[] = $date_to;
    $param_types .= 's';
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
    SELECT
        m.id,
        m.member_code,
        m.username,
        m.full_name,
        m.contact_no,
        p.name AS package_name,
        COALESCE(dv.direct_sales_volume, 0) AS direct_sales_volume,
        cr.rank_level,
        cr.required_volume,
        cr.achieved_volume,
        cr.qualified_at
    FROM members m
    LEFT JOIN packages p ON m.package_id = p.id
    LEFT JOIN (
        SELECT d.sponsor_id AS member_id, COALESCE(SUM(dp.price), 0) AS direct_sales_volume
        FROM members d
        JOIN packages dp ON d.package_id = dp.id
        WHERE d.sponsor_id IS NOT NULL
        GROUP BY d.sponsor_id
    ) dv ON dv.member_id = m.id
    LEFT JOIN (
        SELECT h.member_id, h.rank_level, h.required_volume, h.achieved_volume, h.qualified_at
        FROM member_rank_history h
        INNER JOIN (
            SELECT member_id, MAX(rank_level) AS max_rank_level
            FROM member_rank_history
            GROUP BY member_id
        ) latest ON latest.member_id = h.member_id AND latest.max_rank_level = h.rank_level
    ) cr ON cr.member_id = m.id
    $where_sql
    ORDER BY COALESCE(cr.rank_level, 0) DESC, COALESCE(dv.direct_sales_volume, 0) DESC, m.id DESC
";

$stmt = $conn->prepare($sql);

if ($params) {
    $bind_values = [];
    $bind_values[] = $param_types;

    foreach ($params as $key => $value) {
        $bind_values[] = &$params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $bind_values);
}

$stmt->execute();
$result = $stmt->get_result();
$rows = [];

while ($row = $result->fetch_assoc()) {
    $current_rank_level = isset($row['rank_level']) ? (int)$row['rank_level'] : 0;
    $next_rank_level = $current_rank_level >= 6 ? 0 : $current_rank_level + 1;
    $row['current_rank_name'] = formatTravelRankName($current_rank_level);
    $row['next_rank_name'] = $next_rank_level > 0 ? formatTravelRankName($next_rank_level) : 'Max Rank';
    $row['qualified_date'] = $row['qualified_at'] ?? '';
    $rows[] = $row;
}

if ($export_csv) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="travel_ranking_' . date('Ymd_His') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Member Code',
        'Username',
        'Full Name',
        'Contact No',
        'Current Package',
        'Direct Sales Volume',
        'Current Rank',
        'Next Rank',
        'Qualified Date'
    ]);

    foreach ($rows as $row) {
        fputcsv($output, [
            $row['member_code'],
            $row['username'],
            $row['full_name'],
            $row['contact_no'],
            $row['package_name'],
            number_format((float)$row['direct_sales_volume'], 2, '.', ''),
            $row['current_rank_name'],
            $row['next_rank_name'],
            $row['qualified_date']
        ]);
    }

    fclose($output);
    exit;
}

$export_params = $_GET;
$export_params['page'] = 'leadership_ranks';
$export_params['export'] = 'csv';
$export_url = 'index.php?' . http_build_query($export_params);
?>

<div class="admin-page-header">
    <div>
        <h2>Travel Ranking</h2>
        <p>Lifetime Level 1 direct package sales volume for travel incentives and recognition.</p>
    </div>
    <a href="<?php echo htmlspecialchars($export_url); ?>" class="btn btn-success">Export CSV</a>
</div>

<div class="card mt-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="leadership_ranks">

            <div class="col-lg-3 col-md-6">
                <label class="form-label">Search Member</label>
                <input
                    type="text"
                    name="search"
                    class="form-control"
                    value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Code, username, name, contact, package"
                >
            </div>

            <div class="col-lg-3 col-md-6">
                <label class="form-label">Rank Level</label>
                <select name="rank_level" class="form-select">
                    <option value="0">All ranks</option>
                    <?php foreach ($rank_levels as $level => $required_volume): ?>
                        <option value="<?php echo (int)$level; ?>" <?php echo $rank_level === (int)$level ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(formatTravelRankName((int)$level)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-2 col-md-6">
                <label class="form-label">Qualified From</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>

            <div class="col-lg-2 col-md-6">
                <label class="form-label">Qualified To</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>

            <div class="col-lg-2 col-md-12 d-flex align-items-end gap-2">
                <button class="btn btn-primary w-100">Filter</button>
                <a href="index.php?page=leadership_ranks" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-sm align-middle">
                <thead>
                    <tr>
                        <th>Member Code</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Contact No</th>
                        <th>Current Package</th>
                        <th>Direct Sales Volume</th>
                        <th>Current Rank</th>
                        <th>Next Rank</th>
                        <th>Qualified Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows): ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['member_code']); ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['contact_no'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['package_name'] ?? 'No Package'); ?></td>
                                <td>&#8369;<?php echo number_format((float)$row['direct_sales_volume'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['current_rank_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['next_rank_name']); ?></td>
                                <td><?php echo $row['qualified_date'] ? htmlspecialchars($row['qualified_date']) : '<span class="text-muted">Not qualified</span>'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">No members match the selected filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
