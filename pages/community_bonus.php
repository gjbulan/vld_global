

<?php
$member_id = $_SESSION['member_id'];

$stmt = $conn->prepare("
    SELECT c.*, m.username AS from_user
    FROM community_bonus_ledger c
    LEFT JOIN members m ON c.from_member_id = m.id
    WHERE c.member_id=?
    ORDER BY c.id DESC
");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<h4>Community Bonus</h4>

<div class="card mt-3">
    <div class="card-body">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>From</th>
                    <th>Level</th>
                    <th>Amount</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['from_user']); ?></td>
                    <td><?php echo $row['level']; ?></td>
                    <td>₱<?php echo number_format($row['amount'],2); ?></td>
                    <td><?php echo $row['created_at']; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>