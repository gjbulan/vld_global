

<?php
$member_id = $_SESSION['member_id'];

$stmt = $conn->prepare("
    SELECT m.*, p.name AS package_name
    FROM members m
    LEFT JOIN packages p ON m.package_id = p.id
    WHERE m.sponsor_id=?
    ORDER BY m.id DESC
");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<h4>My Direct Members</h4>

<div class="card mt-3">
    <div class="card-body">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Member Code</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Package</th>
                    <th>Status</th>
                    <th>Date Joined</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['member_code']); ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['package_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['status']); ?></td>
                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>