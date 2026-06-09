<?php
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

$members_by_package = $conn->query("
    SELECT p.name, COUNT(m.id) AS total
    FROM packages p
    LEFT JOIN members m ON m.package_id = p.id
    GROUP BY p.id
");

$bonus_conditions = [];
$bonus_params = [];
$bonus_param_types = "";

if ($date_from !== "") {
    $bonus_conditions[] = "DATE(created_at) >= ?";
    $bonus_params[] = $date_from;
    $bonus_param_types .= "s";
}

if ($date_to !== "") {
    $bonus_conditions[] = "DATE(created_at) <= ?";
    $bonus_params[] = $date_to;
    $bonus_param_types .= "s";
}

$bonus_where = $bonus_conditions ? "WHERE " . implode(" AND ", $bonus_conditions) : "";
$bonus_sql = "
    SELECT type, COALESCE(SUM(amount), 0) AS total, COUNT(*) AS total_rows
    FROM bonus_ledger
    $bonus_where
    GROUP BY type
    ORDER BY type ASC
";
$bonus_stmt = $conn->prepare($bonus_sql);

if ($bonus_params) {
    $bind_values = [];
    $bind_values[] = $bonus_param_types;

    foreach ($bonus_params as $key => $value) {
        $bind_values[] = &$bonus_params[$key];
    }

    call_user_func_array([$bonus_stmt, 'bind_param'], $bind_values);
}

$bonus_stmt->execute();
$bonus_by_type = $bonus_stmt->get_result();

$bonus_totals_sql = "
    SELECT
        COALESCE(SUM(CASE WHEN type='generation_bonus' THEN amount ELSE 0 END), 0) AS generation_bonus_total,
        COALESCE(SUM(CASE WHEN type='chairman_bonus' THEN amount ELSE 0 END), 0) AS chairman_bonus_total
    FROM bonus_ledger
    $bonus_where
";
$bonus_totals_stmt = $conn->prepare($bonus_totals_sql);

if ($bonus_params) {
    $bind_values = [];
    $bind_values[] = $bonus_param_types;

    foreach ($bonus_params as $key => $value) {
        $bind_values[] = &$bonus_params[$key];
    }

    call_user_func_array([$bonus_totals_stmt, 'bind_param'], $bind_values);
}

$bonus_totals_stmt->execute();
$bonus_totals = $bonus_totals_stmt->get_result()->fetch_assoc();

$purchases_by_product = $conn->query("
    SELECT p.name, COALESCE(SUM(pp.quantity), 0) AS total_quantity
    FROM products p
    LEFT JOIN product_purchases pp ON pp.product_id = p.id
    GROUP BY p.id
");

$cashback_summary = $conn->query("
    SELECT reward_type, status, COUNT(*) AS total_rows, COALESCE(SUM(reward_amount), 0) AS total_amount
    FROM cashback_ledger
    GROUP BY reward_type, status
    ORDER BY reward_type ASC, status ASC
");

$advancement_summary = $conn->query("
    SELECT status, COUNT(*) AS total_rows, COALESCE(SUM(amount), 0) AS total_amount
    FROM dominance_advancement_credits
    GROUP BY status
    ORDER BY status ASC
");
?>

<h4>Reports</h4>

<div class="card mt-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="reports">

            <div class="col-md-4">
                <label class="form-label">Bonus Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Bonus Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>

            <div class="col-md-4 d-flex align-items-end gap-2">
                <button class="btn btn-primary w-100">Filter</button>
                <a href="index.php?page=reports" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row mt-3 g-4">
    <div class="col-lg-6">
        <div class="admin-mini-card">
            <span>Generation Bonus Total</span>
            <h4>&#8369;<?php echo number_format((float)$bonus_totals['generation_bonus_total'], 2); ?></h4>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="admin-mini-card">
            <span>Chairman Bonus Total</span>
            <h4>&#8369;<?php echo number_format((float)$bonus_totals['chairman_bonus_total'], 2); ?></h4>
        </div>
    </div>
</div>

<div class="row mt-3 g-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Members by Package</div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Package</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $members_by_package->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo (int)$row['total']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Bonuses by Type</div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Rows</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $bonus_by_type->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['type']); ?></td>
                                <td><?php echo (int)$row['total_rows']; ?></td>
                                <td>&#8369;<?php echo number_format((float)$row['total'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Purchases by Product</div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $purchases_by_product->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo (int)$row['total_quantity']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row mt-1 g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Cashback Ledger Summary</div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Reward Type</th>
                            <th>Status</th>
                            <th>Rows</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $cashback_summary->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['reward_type']); ?></td>
                                <td><?php echo htmlspecialchars($row['status']); ?></td>
                                <td><?php echo (int)$row['total_rows']; ?></td>
                                <td>&#8369;<?php echo number_format((float)$row['total_amount'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Dominance Advancement Credits</div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Rows</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $advancement_summary->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['status']); ?></td>
                                <td><?php echo (int)$row['total_rows']; ?></td>
                                <td>&#8369;<?php echo number_format((float)$row['total_amount'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
