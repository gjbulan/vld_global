<?php
$member_id = $_SESSION['member_id'];
ensureChairmanBonusLedgerTable($conn);

$stmt = $conn->prepare("
    SELECT
        cb.*,
        fm.username AS from_username,
        fm.full_name AS from_full_name,
        fm.member_code AS from_member_code,
        source.description AS source_description,
        source.created_at AS source_created_at
    FROM chairman_bonus_ledger cb
    JOIN members fm ON cb.from_member_id = fm.id
    LEFT JOIN bonus_ledger source ON cb.source_bonus_ledger_id = source.id
    WHERE cb.member_id=?
    ORDER BY cb.id DESC
");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$rows = $stmt->get_result();

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM chairman_bonus_ledger
    WHERE member_id=?
");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$total_chairman_bonus = $summary ? (float)$summary['total'] : 0;
$is_chairman_qualified = isChairmanQualified($conn, $member_id);
?>

<div class="premium-card">
    <div class="card-title-row">
        <h5>Chairman Bonus</h5>
        <span><?php echo $is_chairman_qualified ? 'Qualified' : 'Not Qualified'; ?></span>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="stat-card teal">
                <span>Total Chairman Bonus</span>
                <h3>&#8369;<?php echo number_format($total_chairman_bonus, 2); ?></h3>
            </div>
        </div>

        <div class="col-md-6">
            <div class="stat-card dark">
                <span>Qualification Status</span>
                <h3><?php echo $is_chairman_qualified ? 'Qualified' : 'Not Qualified'; ?></h3>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead>
                <tr>
                    <th>From Direct Member</th>
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
                            <td>
                                <?php echo htmlspecialchars($row['from_username']); ?><br>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($row['from_full_name']); ?> |
                                    <?php echo htmlspecialchars($row['from_member_code']); ?>
                                </small>
                            </td>
                            <td>&#8369;<?php echo number_format((float)$row['source_generation_bonus_amount'], 2); ?></td>
                            <td><?php echo number_format((float)$row['percentage'] * 100, 2); ?>%</td>
                            <td>&#8369;<?php echo number_format((float)$row['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['source_description'] ?? 'Source ledger unavailable'); ?></td>
                            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            No Chairman Bonus earnings found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
