<?php
$member_id = $_SESSION['member_id'];
$success = "";
$error = "";
$package_ids = getVldPackageIds();

$stmt = $conn->prepare("
    SELECT m.*, p.name AS package_name, p.price
    FROM members m
    LEFT JOIN packages p ON m.package_id = p.id
    WHERE m.id=?
");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();

if (
    !$member ||
    ((int)$member['package_id'] !== $package_ids['vision'] && (int)$member['package_id'] !== $package_ids['legacy'])
) {
    echo '<script>window.location.replace("index.php?page=dashboard");</script>';
    return;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $result = useDominanceAdvancementCreditForUpgrade($conn, $member_id);

    if ($result['success']) {
        echo '<script>window.location.replace("index.php?page=dashboard");</script>';
        return;
    }

    $error = $result['message'];
}

$cashback_status = getCashbackStatus($conn, $member_id);
$credit = $cashback_status['credit'];
$has_unused_credit = $cashback_status['has_unused_credit'];
?>

<div class="premium-hero">
    <div>
        <h2>Dominance Upgrade</h2>
        <p>Use an earned Dominance Advancement Credit to upgrade your package.</p>
    </div>
    <div class="hero-badge">
        <?php echo htmlspecialchars($member['package_name'] ?? 'No Package'); ?>
    </div>
</div>

<div class="row g-4 mt-2">
    <div class="col-lg-7">
        <div class="premium-card">
            <div class="card-title-row">
                <h5>Upgrade Credit</h5>
                <span>Non-withdrawable</span>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <table class="table premium-table">
                <tr>
                    <th>Current Package</th>
                    <td><?php echo htmlspecialchars($member['package_name'] ?? 'No Package'); ?></td>
                </tr>
                <tr>
                    <th>Credit Status</th>
                    <td><?php echo $credit ? htmlspecialchars($credit['status']) : 'No credit'; ?></td>
                </tr>
                <tr>
                    <th>Credit Amount</th>
                    <td>&#8369;<?php echo number_format($credit ? (float)$credit['amount'] : 0, 2); ?></td>
                </tr>
                <tr>
                    <th>Used At</th>
                    <td><?php echo $credit && $credit['used_at'] ? htmlspecialchars($credit['used_at']) : 'Not used'; ?></td>
                </tr>
            </table>

            <?php if ($has_unused_credit): ?>
                <form method="POST">
                    <button type="submit" class="btn copy-btn w-100">
                        Use Credit and Upgrade to Dominance
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-info mb-0">
                    An unused Dominance Advancement Credit is required before this upgrade can be processed.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="premium-card">
            <div class="card-title-row">
                <h5>Program Rule</h5>
                <span>Upgrade Only</span>
            </div>

            <p class="text-muted">
                Dominance Advancement Credit is not withdrawable, not transferable, and not convertible to cash.
                It can only be consumed once to upgrade a qualified Vision or Legacy member to Dominance.
            </p>

            <table class="table premium-table mb-0">
                <tr>
                    <th>Wallet Impact</th>
                    <td>No bonus ledger income</td>
                </tr>
                <tr>
                    <th>Upgrade Package</th>
                    <td>Dominance</td>
                </tr>
                <tr>
                    <th>Qualification</th>
                    <td>5 active Direct Dominance referrals</td>
                </tr>
            </table>
        </div>
    </div>
</div>
