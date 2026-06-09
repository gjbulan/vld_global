<?php
$member_id = (int)$_SESSION['member_id'];
seedDefaultProducts($conn);
$monthly_purchase_count = getMonthlyProductPurchaseCount($conn, $member_id);
$is_community_bonus_qualified = $monthly_purchase_count >= 2;

$stmt = $conn->prepare("
    SELECT
        c.*,
        m.username AS from_user,
        m.full_name AS from_full_name,
        p.name AS product_name,
        pc.code AS product_code,
        b.description AS bonus_description
    FROM community_bonus_ledger c
    LEFT JOIN members m ON c.from_member_id = m.id
    LEFT JOIN products p ON c.product_id = p.id
    LEFT JOIN product_codes pc ON c.source_product_code_id = pc.id
    LEFT JOIN bonus_ledger b ON c.bonus_ledger_id = b.id
    WHERE c.member_id=?
    ORDER BY c.id DESC
");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="premium-card">
    <div class="card-title-row">
        <h5>Community Purchase Bonus</h5>
        <span><?php echo $is_community_bonus_qualified ? 'Qualified' : 'Not Qualified'; ?></span>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="stat-card teal">
                <span>Community Qualified</span>
                <h3><?php echo $is_community_bonus_qualified ? 'Yes' : 'No'; ?></h3>
            </div>
        </div>

        <div class="col-md-6">
            <div class="stat-card dark">
                <span>Products Bought This Month</span>
                <h3><?php echo $monthly_purchase_count; ?>/2</h3>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead>
                <tr>
                    <th>From</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Level</th>
                    <th>Amount</th>
                    <th>Description</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($row['from_user'] ?? 'Unknown'); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($row['from_full_name'] ?? ''); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($row['product_name'] ?? 'Legacy Product'); ?></td>
                            <td><?php echo (int)($row['quantity'] ?? 0); ?></td>
                            <td><?php echo (int)$row['level']; ?></td>
                            <td>&#8369;<?php echo number_format((float)$row['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['bonus_description'] ?? 'Community purchase bonus'); ?></td>
                            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No community purchase bonus earnings found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
