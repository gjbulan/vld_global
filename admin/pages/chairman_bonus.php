<?php
$chairman_filter = trim($_GET['chairman'] ?? '');
$from_member_filter = trim($_GET['from_member'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

$conditions = [];
$params = [];
$param_types = "";

if ($chairman_filter !== "") {
    $chairman_like = "%" . $chairman_filter . "%";
    $conditions[] = "(cm.username LIKE ? OR cm.full_name LIKE ? OR cm.member_code LIKE ?)";
    $params[] = $chairman_like;
    $params[] = $chairman_like;
    $params[] = $chairman_like;
    $param_types .= "sss";
}

if ($from_member_filter !== "") {
    $from_like = "%" . $from_member_filter . "%";
    $conditions[] = "(fm.username LIKE ? OR fm.full_name LIKE ? OR fm.member_code LIKE ?)";
    $params[] = $from_like;
    $params[] = $from_like;
    $params[] = $from_like;
    $param_types .= "sss";
}

if ($date_from !== "") {
    $conditions[] = "DATE(cb.created_at) >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if ($date_to !== "") {
    $conditions[] = "DATE(cb.created_at) <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

$where_sql = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

if (!function_exists('bindAdminChairmanParams')) {
    function bindAdminChairmanParams($stmt, $param_types, &$params) {
        if (!$params) {
            return;
        }

        $bind_values = [];
        $bind_values[] = $param_types;

        foreach ($params as $key => $value) {
            $bind_values[] = &$params[$key];
        }

        call_user_func_array([$stmt, 'bind_param'], $bind_values);
    }
}

$summary_sql = "
    SELECT
        COUNT(*) AS total_rows,
        COALESCE(SUM(cb.source_generation_bonus_amount), 0) AS total_source_generation,
        COALESCE(SUM(cb.amount), 0) AS total_chairman
    FROM chairman_bonus_ledger cb
    JOIN members cm ON cb.member_id = cm.id
    JOIN members fm ON cb.from_member_id = fm.id
    $where_sql
";
$summary_stmt = $conn->prepare($summary_sql);
bindAdminChairmanParams($summary_stmt, $param_types, $params);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();

$rows_sql = "
    SELECT
        cb.*,
        cm.username AS chairman_username,
        cm.full_name AS chairman_full_name,
        cm.member_code AS chairman_member_code,
        fm.username AS from_username,
        fm.full_name AS from_full_name,
        fm.member_code AS from_member_code,
        source.description AS source_description,
        source.created_at AS source_created_at
    FROM chairman_bonus_ledger cb
    JOIN members cm ON cb.member_id = cm.id
    JOIN members fm ON cb.from_member_id = fm.id
    LEFT JOIN bonus_ledger source ON cb.source_bonus_ledger_id = source.id
    $where_sql
    ORDER BY cb.id DESC
    LIMIT 500
";
$rows_stmt = $conn->prepare($rows_sql);
bindAdminChairmanParams($rows_stmt, $param_types, $params);
$rows_stmt->execute();
$rows = $rows_stmt->get_result();
?>

<h4>Chairman Bonus Report</h4>

<div class="row g-4 mt-3">
    <div class="col-lg-4">
        <div class="admin-mini-card">
            <span>Total Rows</span>
            <h4><?php echo (int)$summary['total_rows']; ?></h4>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-mini-card">
            <span>Source Generation Bonus</span>
            <h4>&#8369;<?php echo number_format((float)$summary['total_source_generation'], 2); ?></h4>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-mini-card">
            <span>Total Chairman Bonus</span>
            <h4>&#8369;<?php echo number_format((float)$summary['total_chairman'], 2); ?></h4>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <form method="GET" class="row g-3 mb-4">
            <input type="hidden" name="page" value="chairman_bonus">

            <div class="col-lg-3 col-md-6">
                <label class="form-label">Chairman Member</label>
                <input
                    type="text"
                    name="chairman"
                    class="form-control"
                    value="<?php echo htmlspecialchars($chairman_filter); ?>"
                    placeholder="Username, name, or code"
                >
            </div>

            <div class="col-lg-3 col-md-6">
                <label class="form-label">From Direct Member</label>
                <input
                    type="text"
                    name="from_member"
                    class="form-control"
                    value="<?php echo htmlspecialchars($from_member_filter); ?>"
                    placeholder="Username, name, or code"
                >
            </div>

            <div class="col-lg-2 col-md-6">
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>

            <div class="col-lg-2 col-md-6">
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>

            <div class="col-lg-2 col-md-12 d-flex align-items-end gap-2">
                <button class="btn btn-primary w-100">Filter</button>
                <a href="index.php?page=chairman_bonus" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-sm align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Chairman Member</th>
                        <th>From Direct Member</th>
                        <th>Source Ledger ID</th>
                        <th>Source Generation Bonus</th>
                        <th>Percentage</th>
                        <th>Chairman Amount</th>
                        <th>Source Description</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows->num_rows > 0): ?>
                        <?php while ($row = $rows->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($row['chairman_username']); ?><br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($row['chairman_full_name']); ?> |
                                        <?php echo htmlspecialchars($row['chairman_member_code']); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($row['from_username']); ?><br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($row['from_full_name']); ?> |
                                        <?php echo htmlspecialchars($row['from_member_code']); ?>
                                    </small>
                                </td>
                                <td><?php echo (int)$row['source_bonus_ledger_id']; ?></td>
                                <td>&#8369;<?php echo number_format((float)$row['source_generation_bonus_amount'], 2); ?></td>
                                <td><?php echo number_format((float)$row['percentage'] * 100, 2); ?>%</td>
                                <td>&#8369;<?php echo number_format((float)$row['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['source_description'] ?? 'Source ledger unavailable'); ?></td>
                                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                No Chairman Bonus records found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
