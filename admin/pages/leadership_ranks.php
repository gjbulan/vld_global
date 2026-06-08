
<?php
function adminCountDirects($conn, $member_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM members WHERE sponsor_id=?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['total'];
}

function adminMemberRank($conn, $member_id) {
    $directs = adminCountDirects($conn, $member_id);

    if ($directs >= 10) {
        return "L1";
    }

    return "No Rank";
}

$result = $conn->query("
    SELECT m.*, p.name AS package_name
    FROM members m
    LEFT JOIN packages p ON m.package_id = p.id
    ORDER BY m.id DESC
");
?>

<h4>Leadership Ranks</h4>

<div class="card mt-3">
    <div class="card-body">
        <table class="table table-bordered table-striped table-sm">
            <thead>
                <tr>
                    <th>Member Code</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Package</th>
                    <th>Directs</th>
                    <th>Rank</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                    $directs = adminCountDirects($conn, $row['id']);
                    $rank = adminMemberRank($conn, $row['id']);
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['member_code']); ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['package_name']); ?></td>
                        <td><?php echo $directs; ?></td>
                        <td><?php echo htmlspecialchars($rank); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>