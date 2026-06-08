<?php
$total_members = $conn->query("SELECT COUNT(*) AS total FROM members")->fetch_assoc()['total'];
$total_purchases = $conn->query("SELECT COUNT(*) AS total FROM product_purchases")->fetch_assoc()['total'];
$total_bonuses = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM bonus_ledger WHERE amount > 0")->fetch_assoc()['total'];
$total_payouts = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM payouts")->fetch_assoc()['total'];
$total_cashback_paid = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM bonus_ledger WHERE type='cashback_bonus'")->fetch_assoc()['total'];
$total_advancement_credits = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM dominance_advancement_credits WHERE status<>'cancelled'")->fetch_assoc()['total'];
$total_used_advancement_credits = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM dominance_advancement_credits WHERE status='used'")->fetch_assoc()['total'];
$total_royalty_issued = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM dominance_royalty_ledger WHERE status='active'")->fetch_assoc()['total'];
$total_royalty_released = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM dominance_royalty_ledger WHERE status='active' AND available_at <= NOW()")->fetch_assoc()['total'];
$total_royalty_pending = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM dominance_royalty_ledger WHERE status='active' AND available_at > NOW()")->fetch_assoc()['total'];

$total_package_sales = $conn->query("
    SELECT COALESCE(SUM(p.price), 0) AS total
    FROM members m
    JOIN packages p ON m.package_id = p.id
")->fetch_assoc()['total'];

$unused_package_codes = $conn->query("SELECT COUNT(*) AS total FROM package_codes WHERE status='unused'")->fetch_assoc()['total'];
$unused_product_codes = $conn->query("SELECT COUNT(*) AS total FROM product_codes WHERE status='unused'")->fetch_assoc()['total'];

$recent_members = $conn->query("
    SELECT m.*, p.name AS package_name
    FROM members m
    LEFT JOIN packages p ON m.package_id = p.id
    ORDER BY m.id DESC
    LIMIT 5
");
?>

<div class="admin-page-header">
    <div>
        <h2>Dashboard</h2>
        <p>Monitor members, sales, bonuses, payouts, cashback, and advancement credits.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-3 col-md-6">
        <div class="admin-stat-card blue">
            <span>Total Members</span>
            <h3><?php echo (int)$total_members; ?></h3>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="admin-stat-card green">
            <span>Total Package Sales</span>
            <h3>&#8369;<?php echo number_format((float)$total_package_sales, 2); ?></h3>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="admin-stat-card gold">
            <span>Total Bonuses</span>
            <h3>&#8369;<?php echo number_format((float)$total_bonuses, 2); ?></h3>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="admin-stat-card red">
            <span>Total Payouts</span>
            <h3>&#8369;<?php echo number_format((float)$total_payouts, 2); ?></h3>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-lg-4">
        <div class="admin-mini-card">
            <span>Total Cashback Paid</span>
            <h4>&#8369;<?php echo number_format((float)$total_cashback_paid, 2); ?></h4>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-mini-card">
            <span>Dominance Credits Issued</span>
            <h4>&#8369;<?php echo number_format((float)$total_advancement_credits, 2); ?></h4>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-mini-card">
            <span>Used Advancement Credits</span>
            <h4>&#8369;<?php echo number_format((float)$total_used_advancement_credits, 2); ?></h4>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-lg-4">
        <div class="admin-mini-card">
            <span>Total Royalty Issued</span>
            <h4>&#8369;<?php echo number_format((float)$total_royalty_issued, 2); ?></h4>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-mini-card">
            <span>Total Royalty Released</span>
            <h4>&#8369;<?php echo number_format((float)$total_royalty_released, 2); ?></h4>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-mini-card">
            <span>Total Royalty Pending</span>
            <h4>&#8369;<?php echo number_format((float)$total_royalty_pending, 2); ?></h4>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-lg-4">
        <div class="admin-mini-card">
            <span>Product Purchases</span>
            <h4><?php echo (int)$total_purchases; ?></h4>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-mini-card">
            <span>Unused Package Codes</span>
            <h4><?php echo (int)$unused_package_codes; ?></h4>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-mini-card">
            <span>Unused Product Codes</span>
            <h4><?php echo (int)$unused_product_codes; ?></h4>
        </div>
    </div>
</div>

<div class="admin-card mt-4">
    <div class="admin-card-header">
        <h5>Recent Members</h5>
        <a href="index.php?page=members" class="btn btn-sm btn-outline-primary">View All</a>
    </div>

    <div class="table-responsive">
        <table class="table admin-table align-middle">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Package</th>
                    <th>Status</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $recent_members->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['member_code']); ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['package_name'] ?? ''); ?></td>
                        <td><span class="badge bg-success"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
