

<?php
$member_id = $_SESSION['member_id'];

function countDirects($conn, $member_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM members WHERE sponsor_id=?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)$row['total'];
}

function getRankLevel($conn, $member_id) {
    $directs = countDirects($conn, $member_id);

    if ($directs >= 10) {
        return "L1";
    }

    return "No Rank";
}

$rank = getRankLevel($conn, $member_id);
$directs = countDirects($conn, $member_id);
?>

<h4>Leadership Ranking</h4>

<div class="card mt-3">
    <div class="card-body">
        <table class="table table-bordered">
            <tr>
                <th>Current Rank</th>
                <td><?php echo htmlspecialchars($rank); ?></td>
            </tr>
            <tr>
                <th>Total Directs</th>
                <td><?php echo $directs; ?></td>
            </tr>
        </table>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">Rank Requirements</div>
    <div class="card-body">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Requirement</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>L1</td>
                    <td>10 Directs</td>
                </tr>
                <tr>
                    <td>L2</td>
                    <td>5 L1</td>
                </tr>
                <tr>
                    <td>L3</td>
                    <td>3 L2</td>
                </tr>
                <tr>
                    <td>L4</td>
                    <td>2 L3</td>
                </tr>
                <tr>
                    <td>L5</td>
                    <td>2 L4</td>
                </tr>
                <tr>
                    <td>L6</td>
                    <td>2 L5</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>