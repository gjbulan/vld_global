<?php
$member_id = $_SESSION['member_id'];

if (!function_exists('parseGenerationBonusDescription')) {
    function parseGenerationBonusDescription($description) {
        $parsed = [
            'level' => 'Unknown',
            'source_member' => 'Unknown',
            'source_package' => 'Unknown'
        ];

        if (preg_match('/Generation level\s+([0-9]+)\s+bonus from\s+(.+?)\s+\((.+?)\)$/i', $description, $matches)) {
            $parsed['level'] = $matches[1];
            $parsed['source_member'] = $matches[2];
            $parsed['source_package'] = $matches[3];
            return $parsed;
        }

        if (preg_match('/Generation level\s+([0-9]+)\s+bonus from\s+(.+)$/i', $description, $matches)) {
            $parsed['level'] = $matches[1];
            $parsed['source_member'] = $matches[2];
        }

        return $parsed;
    }
}

$stmt = $conn->prepare("
    SELECT id, amount, description, created_at
    FROM bonus_ledger
    WHERE member_id=? AND type='generation_bonus'
    ORDER BY id DESC
");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="premium-card">
    <div class="card-title-row">
        <h5>Generation Bonus</h5>
        <span>Level 2 to Level 8</span>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead>
                <tr>
                    <th>Level</th>
                    <th>Source Member</th>
                    <th>Source Package</th>
                    <th>Amount</th>
                    <th>Description</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php $parsed = parseGenerationBonusDescription($row['description'] ?? ''); ?>
                        <tr>
                            <td><?php echo htmlspecialchars($parsed['level']); ?></td>
                            <td><?php echo htmlspecialchars($parsed['source_member']); ?></td>
                            <td><?php echo htmlspecialchars($parsed['source_package']); ?></td>
                            <td>&#8369;<?php echo number_format((float)$row['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            No generation bonus earnings found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
