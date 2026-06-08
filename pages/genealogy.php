

<?php
function buildTree($conn, $member_id, $level = 0) {
    $stmt = $conn->prepare("SELECT id, username FROM members WHERE sponsor_id=?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        echo str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $level);
        echo "↳ " . htmlspecialchars($row['username']) . "<br>";
        buildTree($conn, $row['id'], $level + 1);
    }
}

echo "<h4>Genealogy Tree</h4><div class='card mt-3'><div class='card-body'>";
echo "<strong>You</strong><br>";
buildTree($conn, $_SESSION['member_id']);
echo "</div></div>";
?>