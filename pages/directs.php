

<?php
$member_id = $_SESSION['member_id'];

$stmt = $conn->prepare("
    SELECT m.*, p.name AS package_name, p.price
    FROM members m
    LEFT JOIN packages p ON m.package_id = p.id
    WHERE m.sponsor_id=?
    ORDER BY m.created_at DESC
");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$directs = $stmt->get_result();
?>

<h4>Direct Referrals</h4>

<div class="card mt-3">
    <div class="card-header">
        Direct Referral List
    </div>

    <div class="card-body">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Username</th>
                    <th>Name</th>
                    <th>Package</th>
                    <th>Package Value</th>
                    <th>Date Joined</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $directs->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['member_code']); ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['package_name']); ?></td>
                        <td>₱<?php echo number_format($row['price'], 2); ?></td>
                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>