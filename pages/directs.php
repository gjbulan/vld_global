<?php
$member_id = $_SESSION['member_id'];
$search = trim($_GET['search'] ?? '');
$current_page = max(1, (int)($_GET['p'] ?? 1));
$per_page = 10;
$offset = ($current_page - 1) * $per_page;

$conditions = ["m.sponsor_id=?"];
$params = [$member_id];
$param_types = "i";

if ($search !== "") {
    $search_like = "%" . $search . "%";
    $conditions[] = "(
        m.member_code LIKE ?
        OR m.username LIKE ?
        OR m.full_name LIKE ?
        OR m.contact_no LIKE ?
        OR p.name LIKE ?
        OR m.status LIKE ?
    )";
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $param_types .= "ssssss";
}

$where_sql = "WHERE " . implode(" AND ", $conditions);

if (!function_exists('bindDirectReferralParams')) {
    function bindDirectReferralParams($stmt, $param_types, &$params) {
        $bind_values = [];
        $bind_values[] = $param_types;

        foreach ($params as $key => $value) {
            $bind_values[] = &$params[$key];
        }

        call_user_func_array([$stmt, 'bind_param'], $bind_values);
    }
}

$count_sql = "
    SELECT COUNT(*) AS total
    FROM members m
    LEFT JOIN packages p ON m.package_id = p.id
    $where_sql
";
$count_stmt = $conn->prepare($count_sql);
bindDirectReferralParams($count_stmt, $param_types, $params);
$count_stmt->execute();
$total_rows = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, (int)ceil($total_rows / $per_page));

if ($current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $per_page;
}

$list_params = $params;
$list_param_types = $param_types . "ii";
$list_params[] = $per_page;
$list_params[] = $offset;

$list_sql = "
    SELECT m.member_code, m.username, m.full_name, m.contact_no, m.status, m.created_at, p.name AS package_name
    FROM members m
    LEFT JOIN packages p ON m.package_id = p.id
    $where_sql
    ORDER BY m.created_at DESC, m.id DESC
    LIMIT ? OFFSET ?
";
$list_stmt = $conn->prepare($list_sql);
bindDirectReferralParams($list_stmt, $list_param_types, $list_params);
$list_stmt->execute();
$directs = $list_stmt->get_result();

$pagination_base = "index.php?page=directs&search=" . urlencode($search);
?>

<div class="premium-card">
    <div class="card-title-row">
        <h5>Direct Referrals</h5>
        <span><?php echo $total_rows; ?> Total</span>
    </div>

    <form method="GET" class="row g-3 mb-4">
        <input type="hidden" name="page" value="directs">

        <div class="col-lg-10 col-md-8">
            <input
                type="text"
                name="search"
                class="form-control premium-input"
                value="<?php echo htmlspecialchars($search); ?>"
                placeholder="Search code, username, name, contact no, package, or status"
            >
        </div>

        <div class="col-lg-2 col-md-4">
            <button class="btn copy-btn w-100" type="submit">Search</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead>
                <tr>
                    <th>Member Code</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Contact No</th>
                    <th>Package</th>
                    <th>Status</th>
                    <th>Date Joined</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($directs->num_rows > 0): ?>
                    <?php while ($row = $directs->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['member_code']); ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['contact_no'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['package_name'] ?? 'No Package'); ?></td>
                            <td>
                                <span class="badge <?php echo $row['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No direct referrals found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
        <nav aria-label="Direct referrals pagination">
            <ul class="pagination flex-wrap mb-0">
                <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $pagination_base; ?>&p=<?php echo max(1, $current_page - 1); ?>">Previous</a>
                </li>

                <?php for ($page_no = 1; $page_no <= $total_pages; $page_no++): ?>
                    <li class="page-item <?php echo $page_no === $current_page ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo $pagination_base; ?>&p=<?php echo $page_no; ?>">
                            <?php echo $page_no; ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $pagination_base; ?>&p=<?php echo min($total_pages, $current_page + 1); ?>">Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
