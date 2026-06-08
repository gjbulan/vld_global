

<?php
$member_id = $_SESSION['member_id'];

$stmt = $conn->prepare("
    SELECT * FROM bonus_ledger 
    WHERE member_id=? AND type='generation_bonus'
    ORDER BY id DESC
");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<h4>Generation Bonus</h4>

<div class="card mt-3">
    <div class="card-body">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Amount</th>
                    <th>Description</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td>₱<?php echo number_format($row['amount'],2); ?></td>
                    <td><?php echo $row['description']; ?></td>
                    <td><?php echo $row['created_at']; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>