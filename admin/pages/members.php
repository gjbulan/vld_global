

<?php
$search = isset($_GET['search']) ? trim($_GET['search']) : "";

if ($search !== "") {
    $search_like = "%" . $search . "%";

    $stmt = $conn->prepare("
        SELECT
            m.*,
            p.name AS package_name,
            s.username AS sponsor_username,
            cb.status AS cashback_status,
            cb.reward_amount AS cashback_amount,
            dac.status AS advancement_credit_status,
            dac.amount AS advancement_credit_amount
        FROM members m
        LEFT JOIN packages p ON m.package_id = p.id
        LEFT JOIN members s ON m.sponsor_id = s.id
        LEFT JOIN cashback_ledger cb ON cb.member_id = m.id AND cb.reward_type='cashback' AND cb.status<>'cancelled'
        LEFT JOIN dominance_advancement_credits dac ON dac.member_id = m.id AND dac.status<>'cancelled'
        WHERE m.username LIKE ?
           OR m.member_code LIKE ?
           OR m.full_name LIKE ?
           OR m.email LIKE ?
           OR m.contact_no LIKE ?
           OR s.username LIKE ?
           OR p.name LIKE ?
           OR cb.status LIKE ?
           OR dac.status LIKE ?
        ORDER BY m.id DESC
    ");
    $stmt->bind_param(
        "sssssssss",
        $search_like,
        $search_like,
        $search_like,
        $search_like,
        $search_like,
        $search_like,
        $search_like,
        $search_like,
        $search_like
    );
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("
        SELECT
            m.*,
            p.name AS package_name,
            s.username AS sponsor_username,
            cb.status AS cashback_status,
            cb.reward_amount AS cashback_amount,
            dac.status AS advancement_credit_status,
            dac.amount AS advancement_credit_amount
        FROM members m
        LEFT JOIN packages p ON m.package_id = p.id
        LEFT JOIN members s ON m.sponsor_id = s.id
        LEFT JOIN cashback_ledger cb ON cb.member_id = m.id AND cb.reward_type='cashback' AND cb.status<>'cancelled'
        LEFT JOIN dominance_advancement_credits dac ON dac.member_id = m.id AND dac.status<>'cancelled'
        ORDER BY m.id DESC
    ");
}
?>

<h4>Manage Members</h4>

<div class="card mt-3">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <input type="hidden" name="page" value="members">
            <div class="col-md-10">
                <input
                    type="text"
                    name="search"
                    class="form-control"
                    value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Search members, code, email, contact, sponsor, package, cashback, or credit"
                >
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">Search</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-sm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Member Code</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Contact Number</th>
                        <th>Sponsor</th>
                        <th>Package</th>
                        <th>Cashback Status</th>
                        <th>Advancement Credit</th>
                        <th>Status</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo (int)$row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['member_code']); ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['contact_no'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['sponsor_username'] ?? 'None'); ?></td>
                            <td><?php echo htmlspecialchars($row['package_name']); ?></td>
                            <td>
                                <?php if ($row['cashback_status']): ?>
                                    <?php echo htmlspecialchars($row['cashback_status']); ?>
                                    <br><small>&#8369;<?php echo number_format((float)$row['cashback_amount'], 2); ?></small>
                                <?php else: ?>
                                    None
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['advancement_credit_status']): ?>
                                    <?php echo htmlspecialchars($row['advancement_credit_status']); ?>
                                    <br><small>&#8369;<?php echo number_format((float)$row['advancement_credit_amount'], 2); ?></small>
                                <?php else: ?>
                                    None
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['status']); ?></td>
                            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
