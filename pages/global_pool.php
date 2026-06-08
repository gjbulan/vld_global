
<?php
$member_id = $_SESSION['member_id'];

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM members
    WHERE sponsor_id=? AND package_id IN (2,3)
");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$qualified_directs = $stmt->get_result()->fetch_assoc()['total'];

$is_qualified = $qualified_directs >= 5;

$total_sales_result = $conn->query("
    SELECT SUM(p.price) AS total_sales
    FROM members m
    JOIN packages p ON m.package_id = p.id
");

$total_sales = $total_sales_result->fetch_assoc()['total_sales'] ?? 0;
$pool_amount = $total_sales * 0.02;
?>

<h4>Global Pool</h4>

<div class="card mt-3">
    <div class="card-body">
        <table class="table table-bordered">
            <tr>
                <th>Total Package Sales</th>
                <td>₱<?php echo number_format($total_sales, 2); ?></td>
            </tr>
            <tr>
                <th>Global Pool Percentage</th>
                <td>2%</td>
            </tr>
            <tr>
                <th>Current Pool Amount</th>
                <td>₱<?php echo number_format($pool_amount, 2); ?></td>
            </tr>
            <tr>
                <th>Your Foundation/Premium Directs</th>
                <td><?php echo $qualified_directs; ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td>
                    <?php if ($is_qualified): ?>
                        <span class="badge bg-success">Qualified</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Not Qualified</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
</div>