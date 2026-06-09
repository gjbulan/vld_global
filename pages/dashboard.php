<?php
$member_id = $_SESSION['member_id'];
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

$package_id = $member ? (int)$member['package_id'] : 0;
$is_dominance_member = $package_id === $package_ids['dominance'];
$is_upgrade_member = $package_id === $package_ids['vision'] || $package_id === $package_ids['legacy'];

$balance = getBalance($conn, $member_id);
$cashback_status = getCashbackStatus($conn, $member_id);
$qualified_direct_count = $cashback_status['qualified_direct_count'];
$reward_amount = $cashback_status['reward_amount'];
$cashback = $cashback_status['cashback'];
$credit = $cashback_status['credit'];
$has_unused_credit = $cashback_status['has_unused_credit'];
$royalty_summary = getDominanceRoyaltySummary($conn, $member_id);
$next_release_date = $royalty_summary['next_release_date'] ? date("Y-m-d", strtotime($royalty_summary['next_release_date'])) : "No pending release";

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM members WHERE sponsor_id=?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$directs = (int)$stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM members WHERE sponsor_id=? AND status='active'");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$active_directs = (int)$stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM members WHERE sponsor_id=? AND status='active' AND package_id=?");
$stmt->bind_param("ii", $member_id, $package_ids['dominance']);
$stmt->execute();
$active_direct_dominance = (int)$stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM product_purchases WHERE member_id=?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$purchases = (int)$stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM bonus_ledger
    WHERE member_id=? AND type='cashback_bonus'
");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$withdrawable_cashback = (float)$stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM bonus_ledger
    WHERE member_id=? AND type='generation_bonus'
");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$generation_bonus_total = (float)$stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM bonus_ledger
    WHERE member_id=? AND type='chairman_bonus'
");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$chairman_bonus_total = (float)$stmt->get_result()->fetch_assoc()['total'];

$is_chairman_qualified = isChairmanQualified($conn, $member_id);
$chairman_status_label = $is_chairman_qualified ? "Qualified" : "Not qualified";
$show_chairman_bonus_total = $chairman_bonus_total > 0 || $is_chairman_qualified;

$stmt = $conn->prepare("
    SELECT amount, type, description, created_at
    FROM bonus_ledger
    WHERE member_id=?
    ORDER BY id DESC
    LIMIT 8
");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$recent_bonuses = $stmt->get_result();

$credit_amount = $credit ? (float)$credit['amount'] : 0;
$credit_status_label = "No credit";

if ($credit) {
    $credit_status_label = $credit['status'] === "unused" ? "Unused credit available" : "Credit used";
}

$cashback_status_label = $cashback ? "Cashback earned" : $cashback_status['qualification_label'];
$cashback_direct_label = $is_dominance_member ? "Active Direct Dominance" : "Cashback Qualified Directs";
$cashback_direct_value = $is_dominance_member ? $active_direct_dominance : $qualified_direct_count;
$http_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_path = $_SERVER['PHP_SELF'] ?? '/vld_global/index.php';
$ref_link = "http://" . $http_host . dirname($script_path) . "/register.php?ref=" . $member['username'];
?>

<div class="premium-hero">
    <div>
        <h2>Dashboard</h2>
        <p>Track your rewards, community growth, and global journey.</p>
    </div>
    <div class="hero-badge">
        <?php echo htmlspecialchars($member['package_name'] ?? 'No Package'); ?>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-md-3">
        <div class="stat-card ocean">
            <span>Available Wallet Balance</span>
            <h3>&#8369;<?php echo number_format($balance, 2); ?></h3>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card gold">
            <span><?php echo htmlspecialchars($cashback_direct_label); ?></span>
            <h3><?php echo $cashback_direct_value; ?>/5</h3>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card teal">
            <span><?php echo $is_dominance_member ? 'Available Royalty' : 'Product Encodes'; ?></span>
            <h3>
                <?php if ($is_dominance_member): ?>
                    &#8369;<?php echo number_format($royalty_summary['released_amount'], 2); ?>
                <?php else: ?>
                    <?php echo $purchases; ?>
                <?php endif; ?>
            </h3>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card dark">
            <span>Member Code</span>
            <h3><?php echo htmlspecialchars($member['member_code']); ?></h3>
        </div>
    </div>
</div>

<div class="row g-4 mt-2">
    <div class="col-lg-4">
        <div class="premium-card h-100">
            <div class="card-title-row">
                <h5>Generation Bonus Total</h5>
                <span>Level 2 to Level 8</span>
            </div>

            <h3 class="mb-0">&#8369;<?php echo number_format($generation_bonus_total, 2); ?></h3>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="premium-card h-100">
            <div class="card-title-row">
                <h5>Chairman Qualification</h5>
                <span><?php echo htmlspecialchars($chairman_status_label); ?></span>
            </div>

            <table class="table premium-table mb-0">
                <tr>
                    <th>Required Package</th>
                    <td>Dominance</td>
                </tr>
                <tr>
                    <th>Active Direct Dominance</th>
                    <td><?php echo $active_direct_dominance; ?>/5</td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td><?php echo htmlspecialchars($chairman_status_label); ?></td>
                </tr>
            </table>
        </div>
    </div>

    <?php if ($show_chairman_bonus_total): ?>
        <div class="col-lg-4">
            <div class="premium-card h-100">
                <div class="card-title-row">
                    <h5>Chairman Bonus Total</h5>
                    <span>2% Override</span>
                </div>

                <h3 class="mb-2">&#8369;<?php echo number_format($chairman_bonus_total, 2); ?></h3>
                <small class="text-muted">
                    Earned from generation bonuses received by your direct referrals.
                </small>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="row g-4 mt-2">
    <div class="col-lg-4">
        <div class="premium-card h-100">
            <div class="card-title-row">
                <h5>Cashback Status</h5>
                <span>Withdrawable Cashback</span>
            </div>

            <h3 class="mb-2">&#8369;<?php echo number_format($withdrawable_cashback, 2); ?></h3>
            <p class="text-muted mb-2"><?php echo htmlspecialchars($cashback_status_label); ?></p>
            <small class="text-muted">
                Reward target: &#8369;<?php echo number_format($reward_amount, 2); ?>
            </small>
        </div>
    </div>

    <?php if ($is_dominance_member): ?>
        <div class="col-lg-4">
            <div class="premium-card h-100">
                <div class="card-title-row">
                    <h5>Dominance Royalty Bonus</h5>
                    <span><?php echo htmlspecialchars($royalty_summary['royalty_status']); ?></span>
                </div>

                <table class="table premium-table mb-0">
                    <tr>
                        <th>Total Royalty Scheduled</th>
                        <td>&#8369;<?php echo number_format($royalty_summary['total_royalty'], 2); ?></td>
                    </tr>
                    <tr>
                        <th>Available Royalty</th>
                        <td>&#8369;<?php echo number_format($royalty_summary['released_amount'], 2); ?></td>
                    </tr>
                    <tr>
                        <th>Pending Royalty</th>
                        <td>&#8369;<?php echo number_format($royalty_summary['pending_amount'], 2); ?></td>
                    </tr>
                    <tr>
                        <th>Next Release Date</th>
                        <td><?php echo htmlspecialchars($next_release_date); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="premium-card h-100">
                <div class="card-title-row">
                    <h5>Dominance Qualification</h5>
                    <span>Royalty Status</span>
                </div>

                <table class="table premium-table mb-0">
                    <tr>
                        <th>Active Direct Dominance Count</th>
                        <td><?php echo $active_direct_dominance; ?>/5</td>
                    </tr>
                    <tr>
                        <th>Royalty Status</th>
                        <td><?php echo htmlspecialchars($royalty_summary['royalty_status']); ?></td>
                    </tr>
                    <tr>
                        <th>Months Released</th>
                        <td><?php echo $royalty_summary['months_released']; ?>/6</td>
                    </tr>
                    <tr>
                        <th>Months Remaining</th>
                        <td><?php echo $royalty_summary['months_remaining']; ?>/6</td>
                    </tr>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="col-lg-4">
            <div class="premium-card h-100">
                <div class="card-title-row">
                    <h5>Dominance Credit</h5>
                    <span>Non-withdrawable Upgrade Credit</span>
                </div>

                <h3 class="mb-2">&#8369;<?php echo number_format($credit_amount, 2); ?></h3>
                <p class="text-muted mb-3"><?php echo htmlspecialchars($credit_status_label); ?></p>

                <?php if ($is_upgrade_member && $has_unused_credit): ?>
                    <a href="index.php?page=dominance_upgrade" class="btn copy-btn w-100">
                        Upgrade to Dominance
                    </a>
                <?php else: ?>
                    <small class="text-muted">
                        Credit is issued only for qualified Vision or Legacy members with 5 active Direct Dominance referrals.
                    </small>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="premium-card h-100">
                <div class="card-title-row">
                    <h5>Team Activity</h5>
                    <span>Earnings Cards</span>
                </div>

                <table class="table premium-table mb-0">
                    <tr>
                        <th>Total Directs</th>
                        <td><?php echo $directs; ?></td>
                    </tr>
                    <tr>
                        <th>Active Directs</th>
                        <td><?php echo $active_directs; ?></td>
                    </tr>
                    <tr>
                        <th>Active Direct Dominance</th>
                        <td><?php echo $active_direct_dominance; ?></td>
                    </tr>
                    <tr>
                        <th>Product Encodes</th>
                        <td><?php echo $purchases; ?></td>
                    </tr>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="row g-4 mt-2">
    <div class="col-lg-8">
        <div class="premium-card">
            <div class="card-title-row">
                <h5>Your Referral Link</h5>
                <span>Share & Grow</span>
            </div>

            <div class="input-group">
                <input type="text" class="form-control premium-input" value="<?php echo htmlspecialchars($ref_link); ?>" id="refLink" readonly>
                <button class="btn copy-btn" onclick="copyReferral()">Copy</button>
            </div>

            <small class="text-muted d-block mt-3">
                Use this link to invite new members under your network.
            </small>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="premium-card profile-summary">
            <img src="assets/logo.png" alt="VLD Global">
            <h5><?php echo htmlspecialchars($member['full_name']); ?></h5>
            <p>@<?php echo htmlspecialchars($member['username']); ?></p>
            <span class="status-pill"><?php echo htmlspecialchars($member['status']); ?></span>
        </div>
    </div>
</div>

<div class="premium-card mt-4">
    <div class="card-title-row">
        <h5>Recent Bonus History</h5>
        <span>Wallet Earnings</span>
    </div>

    <div class="table-responsive">
        <table class="table premium-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Description</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($bonus = $recent_bonuses->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($bonus['type']); ?></td>
                        <td>&#8369;<?php echo number_format((float)$bonus['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($bonus['description']); ?></td>
                        <td><?php echo htmlspecialchars($bonus['created_at']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="premium-card mt-4">
    <div class="card-title-row">
        <h5>Account Information</h5>
        <span>Member Details</span>
    </div>

    <div class="table-responsive">
        <table class="table premium-table">
            <tr>
                <th>Member Code</th>
                <td><?php echo htmlspecialchars($member['member_code']); ?></td>
            </tr>
            <tr>
                <th>Username</th>
                <td><?php echo htmlspecialchars($member['username']); ?></td>
            </tr>
            <tr>
                <th>Full Name</th>
                <td><?php echo htmlspecialchars($member['full_name']); ?></td>
            </tr>
            <tr>
                <th>Email</th>
                <td><?php echo htmlspecialchars($member['email']); ?></td>
            </tr>
            <tr>
                <th>Package</th>
                <td><?php echo htmlspecialchars($member['package_name']); ?></td>
            </tr>
            <tr>
                <th>Cashback Status</th>
                <td><?php echo htmlspecialchars($cashback_status_label); ?></td>
            </tr>
            <?php if ($is_dominance_member): ?>
                <tr>
                    <th>Royalty Status</th>
                    <td><?php echo htmlspecialchars($royalty_summary['royalty_status']); ?></td>
                </tr>
            <?php else: ?>
                <tr>
                    <th>Dominance Credit</th>
                    <td><?php echo htmlspecialchars($credit_status_label); ?></td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<script>
function copyReferral() {
    var copyText = document.getElementById("refLink");
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value);
    alert("Referral link copied!");
}
</script>
