

<?php
$result = $conn->query("
    SELECT p.*, m.username, m.full_name
    FROM payouts p
    JOIN members m ON p.member_id = m.id
    ORDER BY p.id DESC
");
?>

<h4>Payouts</h4>

<div class="card mt-3">
    <div class="card-body">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Member</th>
                    <th>Name</th>
                    <th>Amount</th>
                    <th>Fee</th>
                    <th>Net Amount</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td>₱<?php echo number_format($row['amount'], 2); ?></td>
                        <td>₱<?php echo number_format($row['fee'], 2); ?></td>
                        <td>₱<?php echo number_format($row['net_amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>