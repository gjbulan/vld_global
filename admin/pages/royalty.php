<?php
$search = trim($_GET['search'] ?? '');
$release_status = trim($_GET['release_status'] ?? '');

$conditions = [];
$params = [];
$param_types = "";

if ($search !== "") {
    $search_like = "%" . $search . "%";
    $conditions[] = "(m.username LIKE ? OR m.full_name LIKE ? OR m.member_code LIKE ?)";
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $param_types .= "sss";
}

if ($release_status === "released") {
    $conditions[] = "dr.status='active' AND dr.available_at <= NOW()";
} elseif ($release_status === "pending") {
    $conditions[] = "dr.status='active' AND dr.available_at > NOW()";
} elseif ($release_status === "cancelled") {
    $conditions[] = "dr.status='cancelled'";
}

$where_sql = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

$summary_sql = "
    SELECT
        COALESCE(SUM(dr.amount), 0) AS total_issued,
        COALESCE(SUM(CASE WHEN dr.status='active' AND dr.available_at <= NOW() THEN dr.amount ELSE 0 END), 0) AS total_released,
        COALESCE(SUM(CASE WHEN dr.status='active' AND dr.available_at > NOW() THEN dr.amount ELSE 0 END), 0) AS total_pending,
        COUNT(*) AS total_rows
    FROM dominance_royalty_ledger dr
    JOIN members m ON dr.member_id = m.id
    $where_sql
";

$summary_stmt = $conn->prepare($summary_sql);

if ($params) {
    $bind_values = [];
    $bind_values[] = $param_types;

    foreach ($params as $key => $value) {
        $bind_values[] = &$params[$key];
    }

    call_user_func_array([$summary_stmt, 'bind_param'], $bind_values);
}

$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();

$rows_sql = "
    SELECT
        dr.*,
        m.username,
        m.full_name,
        m.member_code,
        p.name AS package_name
    FROM dominance_royalty_ledger dr
    JOIN members m ON dr.member_id = m.id
    LEFT JOIN packages p ON m.package_id = p.id
    $where_sql
    ORDER BY dr.available_at ASC, dr.member_id ASC, dr.month_no ASC
";

$rows_stmt = $conn->prepare($rows_sql);

if ($params) {
    $bind_values = [];
    $bind_values[] = $param_types;

    foreach ($params as $key => $value) {
        $bind_values[] = &$params[$key];
    }

    call_user_func_array([$rows_stmt, 'bind_param'], $bind_values);
}

$rows_stmt->execute();
$rows = $rows_stmt->get_result();
?>

<h4>Dominance Royalty Report</h4>

<div class="row g-4 mt-3">
    <div class="col-lg-4">
        <div class="admin-mini-card">
            <span>Total Royalty Issued</span>
            <h4>&#8369;<?php echo number_format((float)$summary['total_issued'], 2); ?></h4>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-mini-card">
            <span>Total Royalty Released</span>
            <h4>&#8369;<?php echo number_format((float)$summary['total_released'], 2); ?></h4>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-mini-card">
            <span>Total Royalty Pending</span>
            <h4>&#8369;<?php echo number_format((float)$summary['total_pending'], 2); ?></h4>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <form method="GET" class="row g-3 mb-4">
            <input type="hidden" name="page" value="royalty">

            <div class="col-lg-6">
                <label class="form-label">Member</label>
                <input
                    type="text"
                    name="search"
                    class="form-control"
                    value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Username, name, or member code"
                >
            </div>

            <div class="col-lg-4">
                <label class="form-label">Release Status</label>
                <select name="release_status" class="form-control">
                    <option value="">All</option>
                    <option value="released" <?php echo $release_status === 'released' ? 'selected' : ''; ?>>Released</option>
                    <option value="pending" <?php echo $release_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="cancelled" <?php echo $release_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>

            <div class="col-lg-2 d-flex align-items-end gap-2">
                <button class="btn btn-primary w-100">Filter</button>
                <a href="index.php?page=royalty" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-sm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Member</th>
                        <th>Package</th>
                        <th>Month</th>
                        <th>Amount</th>
                        <th>Available At</th>
                        <th>Release State</th>
                        <th>Ledger ID</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $rows->fetch_assoc()): ?>
                        <?php
                        $is_released = $row['status'] === 'active' && strtotime($row['available_at']) <= time();
                        $release_label = $row['status'] === 'cancelled' ? 'Cancelled' : ($is_released ? 'Released' : 'Pending');
                        ?>
                        <tr>
                            <td><?php echo (int)$row['id']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($row['username']); ?><br>
                                <small><?php echo htmlspecialchars($row['member_code']); ?> - <?php echo htmlspecialchars($row['full_name']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($row['package_name'] ?? ''); ?></td>
                            <td><?php echo (int)$row['month_no']; ?>/6</td>
                            <td>&#8369;<?php echo number_format((float)$row['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['available_at']); ?></td>
                            <td><?php echo htmlspecialchars($release_label); ?></td>
                            <td><?php echo $row['bonus_ledger_id'] ? (int)$row['bonus_ledger_id'] : 'None'; ?></td>
                            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
