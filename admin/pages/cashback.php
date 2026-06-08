<?php
$search = trim($_GET['search'] ?? '');
$reward_type = trim($_GET['reward_type'] ?? '');
$status = trim($_GET['status'] ?? '');

$conditions = [];
$params = [];
$param_types = "";

if ($search !== "") {
    $search_like = "%" . $search . "%";
    $conditions[] = "(m.username LIKE ? OR m.full_name LIKE ? OR m.member_code LIKE ? OR p.name LIKE ?)";
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $param_types .= "ssss";
}

if ($reward_type !== "") {
    $conditions[] = "cl.reward_type=?";
    $params[] = $reward_type;
    $param_types .= "s";
}

if ($status !== "") {
    $conditions[] = "cl.status=?";
    $params[] = $status;
    $param_types .= "s";
}

$where_sql = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

$sql = "
    SELECT
        cl.*,
        m.username,
        m.full_name,
        m.member_code,
        p.name AS package_name
    FROM cashback_ledger cl
    JOIN members m ON cl.member_id = m.id
    LEFT JOIN packages p ON cl.package_id = p.id
    $where_sql
    ORDER BY cl.id DESC
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
$cashback_rows = $stmt->get_result();

$credit_rows = $conn->query("
    SELECT dac.*, m.username, m.full_name, m.member_code, p.name AS package_name
    FROM dominance_advancement_credits dac
    JOIN members m ON dac.member_id = m.id
    LEFT JOIN packages p ON m.package_id = p.id
    ORDER BY dac.id DESC
");
?>

<h4>Cashback &amp; Dominance Advancement</h4>

<div class="card mt-3">
    <div class="card-body">
        <form method="GET" class="row g-3 mb-4">
            <input type="hidden" name="page" value="cashback">

            <div class="col-lg-4">
                <label class="form-label">Search</label>
                <input
                    type="text"
                    name="search"
                    class="form-control"
                    value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Member, code, or package"
                >
            </div>

            <div class="col-lg-3">
                <label class="form-label">Reward Type</label>
                <select name="reward_type" class="form-control">
                    <option value="">All Types</option>
                    <option value="cashback" <?php echo $reward_type === 'cashback' ? 'selected' : ''; ?>>cashback</option>
                    <option value="dominance_advancement_credit" <?php echo $reward_type === 'dominance_advancement_credit' ? 'selected' : ''; ?>>dominance_advancement_credit</option>
                </select>
            </div>

            <div class="col-lg-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>active</option>
                    <option value="used" <?php echo $status === 'used' ? 'selected' : ''; ?>>used</option>
                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>cancelled</option>
                </select>
            </div>

            <div class="col-lg-2 d-flex align-items-end gap-2">
                <button class="btn btn-primary w-100">Filter</button>
                <a href="index.php?page=cashback" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-sm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Member</th>
                        <th>Package</th>
                        <th>Qualification</th>
                        <th>Qualified Directs</th>
                        <th>Reward Type</th>
                        <th>Amount</th>
                        <th>Bonus Ledger ID</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $cashback_rows->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo (int)$row['id']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($row['username']); ?><br>
                                <small><?php echo htmlspecialchars($row['member_code']); ?> - <?php echo htmlspecialchars($row['full_name']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($row['package_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['qualification_type']); ?></td>
                            <td><?php echo (int)$row['qualified_direct_count']; ?></td>
                            <td><?php echo htmlspecialchars($row['reward_type']); ?></td>
                            <td>&#8369;<?php echo number_format((float)$row['reward_amount'], 2); ?></td>
                            <td><?php echo $row['bonus_ledger_id'] ? (int)$row['bonus_ledger_id'] : 'None'; ?></td>
                            <td><?php echo htmlspecialchars($row['status']); ?></td>
                            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">Dominance Advancement Credit Usage</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-sm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Member</th>
                        <th>Current Package</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Used At</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $credit_rows->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo (int)$row['id']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($row['username']); ?><br>
                                <small><?php echo htmlspecialchars($row['member_code']); ?> - <?php echo htmlspecialchars($row['full_name']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($row['package_name'] ?? ''); ?></td>
                            <td>&#8369;<?php echo number_format((float)$row['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['status']); ?></td>
                            <td><?php echo $row['used_at'] ? htmlspecialchars($row['used_at']) : 'Not used'; ?></td>
                            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
