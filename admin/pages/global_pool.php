

<?php
$total_package_sales = $conn->query("
    SELECT SUM(p.price) AS total
    FROM members m
    JOIN packages p ON m.package_id = p.id
")->fetch_assoc()['total'] ?? 0;

$pool_percentage = 2;
$pool_amount = $total_package_sales * ($pool_percentage / 100);

$qualified = $conn->query("
    SELECT m.id, m.username, m.full_name, COUNT(d.id) AS qualified_directs
    FROM members m
    LEFT JOIN members d ON d.sponsor_id = m.id AND d.package_id IN (2,3)
    GROUP BY m.id
    HAVING qualified_directs >= 5
    ORDER BY qualified_directs DESC
");

$qualified_count = $qualified->num_rows;
$share = $qualified_count > 0 ? $pool_amount / $qualified_count : 0;
?>

<h4>Global Pool</h4>

<div class="card mt-3">
    <div class="card-body">
        <table class="table table-bordered">
            <tr>
                <th>Total Package Sales</th>
                <td>₱<?php echo number_format($total_package_sales, 2); ?></td>
            </tr>
            <tr>
                <th>Pool Percentage</th>
                <td><?php echo $pool_percentage; ?>%</td>
            </tr>
            <tr>
                <th>Total Pool Amount</th>
                <td>₱<?php echo number_format($pool_amount, 2); ?></td>
            </tr>
            <tr>
                <th>Qualified Members</th>
                <td><?php echo $qualified_count; ?></td>
            </tr>
            <tr>
                <th>Estimated Share Per Qualified Member</th>
                <td>₱<?php echo number_format($share, 2); ?></td>
            </tr>
        </table>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">Qualified Members</div>
    <div class="card-body">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Foundation/Premium Directs</th>
                    <th>Estimated Share</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $qualified->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo $row['qualified_directs']; ?></td>
                        <td>₱<?php echo number_format($share, 2); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>