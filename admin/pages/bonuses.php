<?php
$member_filter = trim($_GET['member'] ?? '');
$type_filter = trim($_GET['type'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

$type_options = $conn->query("
    SELECT DISTINCT type
    FROM bonus_ledger
    WHERE type IS NOT NULL AND type<>''
    ORDER BY type ASC
");

$conditions = [];
$params = [];
$param_types = "";

if ($member_filter !== "") {
    $member_like = "%" . $member_filter . "%";
    $conditions[] = "(m.username LIKE ? OR m.full_name LIKE ? OR m.member_code LIKE ?)";
    $params[] = $member_like;
    $params[] = $member_like;
    $params[] = $member_like;
    $param_types .= "sss";
}

if ($type_filter !== "") {
    $conditions[] = "b.type=?";
    $params[] = $type_filter;
    $param_types .= "s";
}

if ($date_from !== "") {
    $conditions[] = "DATE(b.created_at) >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if ($date_to !== "") {
    $conditions[] = "DATE(b.created_at) <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

$where_sql = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

$sql = "
    SELECT b.*, m.username, m.full_name, m.member_code
    FROM bonus_ledger b
    JOIN members m ON b.member_id = m.id
    $where_sql
    ORDER BY b.id DESC
    LIMIT 500
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
?>

<h4>Bonus Ledger</h4>

<div class="card mt-3">
    <div class="card-body">
        <form method="GET" class="row g-3 mb-4">
            <input type="hidden" name="page" value="bonuses">

            <div class="col-lg-3 col-md-6">
                <label class="form-label">Member</label>
                <input
                    type="text"
                    name="member"
                    class="form-control"
                    value="<?php echo htmlspecialchars($member_filter); ?>"
                    placeholder="Username, name, or code"
                >
            </div>

            <div class="col-lg-3 col-md-6">
                <label class="form-label">Bonus Type</label>
                <select name="type" class="form-control">
                    <option value="">All Types</option>
                    <?php while ($type_row = $type_options->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($type_row['type']); ?>" <?php echo $type_filter === $type_row['type'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type_row['type']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
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
                <a href="index.php?page=bonuses" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-sm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Member Code</th>
                        <th>Member</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Description</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo (int)$row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['member_code']); ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['type']); ?></td>
                            <td>&#8369;<?php echo number_format((float)$row['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
