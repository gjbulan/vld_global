<?php
seedDefaultProducts($conn);

$result = $conn->query("
    SELECT
        pp.*,
        m.username,
        m.full_name,
        m.member_code,
        p.name AS product_name,
        p.personal_bonus,
        p.community_bonus,
        pc.code AS product_code
    FROM product_purchases pp
    JOIN members m ON pp.member_id = m.id
    LEFT JOIN products p ON pp.product_id = p.id
    LEFT JOIN product_codes pc ON pp.product_code_id = pc.id
    ORDER BY pp.id DESC
    LIMIT 500
");
?>

<h4>Product Purchases</h4>

<div class="card mt-3">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Member Code</th>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Product Code</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Personal Bonus</th>
                        <th>Community Bonus Base</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                        $quantity = (int)$row['quantity'];
                        $personal_total = (float)($row['personal_bonus'] ?? 0) * $quantity;
                        $community_total = (float)($row['community_bonus'] ?? 0) * $quantity;
                        ?>
                        <tr>
                            <td><?php echo (int)$row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['member_code']); ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['product_code'] ?? 'Legacy Purchase'); ?></td>
                            <td><?php echo htmlspecialchars($row['product_name'] ?? 'Inactive/Removed Product'); ?></td>
                            <td><?php echo $quantity; ?></td>
                            <td>&#8369;<?php echo number_format($personal_total, 2); ?></td>
                            <td>&#8369;<?php echo number_format($community_total, 2); ?></td>
                            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
