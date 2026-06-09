<?php
$member_id = (int)$_SESSION['member_id'];

processTravelRankQualification($conn, $member_id);

$direct_sales_volume = getDirectSalesVolume($conn, $member_id);
$current_rank = getCurrentTravelRank($conn, $member_id);
$next_rank = getNextTravelRank($conn, $member_id);
$progress_percent = (float)$next_rank['progress_percent'];
$progress_width = number_format($progress_percent, 2, '.', '');
$remaining_volume = (float)$next_rank['remaining_volume'];

$history_stmt = $conn->prepare("
    SELECT rank_level, required_volume, achieved_volume, qualified_at
    FROM member_rank_history
    WHERE member_id=?
    ORDER BY rank_level DESC
");
$history_stmt->bind_param("i", $member_id);
$history_stmt->execute();
$rank_history = $history_stmt->get_result();
?>

<div class="premium-hero">
    <div>
        <h2>Travel Ranking</h2>
        <p>Track travel incentive recognition from your lifetime Level 1 direct package sales.</p>
    </div>
    <div class="hero-badge">
        <?php echo htmlspecialchars($current_rank['rank_name']); ?>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-md-4">
        <div class="stat-card ocean">
            <span>Current Rank</span>
            <h3><?php echo htmlspecialchars($current_rank['rank_name']); ?></h3>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card gold">
            <span>Direct Sales Volume</span>
            <h3>&#8369;<?php echo number_format($direct_sales_volume, 2); ?></h3>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card teal">
            <span>Next Rank</span>
            <h3><?php echo htmlspecialchars($next_rank['rank_name']); ?></h3>
        </div>
    </div>
</div>

<div class="premium-card mt-4">
    <div class="card-title-row">
        <h5>Rank Progress</h5>
        <span>Travel Incentive</span>
    </div>

    <div class="row g-4 align-items-center">
        <div class="col-lg-4">
            <table class="table premium-table mb-0">
                <tr>
                    <th>Current Rank</th>
                    <td><?php echo htmlspecialchars($current_rank['rank_name']); ?></td>
                </tr>
                <tr>
                    <th>Next Rank</th>
                    <td><?php echo htmlspecialchars($next_rank['rank_name']); ?></td>
                </tr>
                <tr>
                    <th>Remaining Volume</th>
                    <td>&#8369;<?php echo number_format($remaining_volume, 2); ?></td>
                </tr>
            </table>
        </div>

        <div class="col-lg-8">
            <div class="d-flex justify-content-between mb-2">
                <strong>&#8369;<?php echo number_format($direct_sales_volume, 2); ?></strong>
                <?php if ((int)$next_rank['rank_level'] > 0): ?>
                    <span class="text-muted">
                        Target: &#8369;<?php echo number_format((float)$next_rank['required_volume'], 2); ?>
                    </span>
                <?php else: ?>
                    <span class="text-muted">All rank levels achieved</span>
                <?php endif; ?>
            </div>

            <div class="progress" style="height: 24px;">
                <div
                    class="progress-bar bg-success"
                    role="progressbar"
                    style="width: <?php echo $progress_width; ?>%;"
                    aria-valuenow="<?php echo $progress_width; ?>"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
                    <?php echo number_format($progress_percent, 2); ?>%
                </div>
            </div>

            <small class="text-muted d-block mt-3">
                Travel ranks are recognition incentives only and do not create wallet or bonus ledger payouts.
            </small>
        </div>
    </div>
</div>

<div class="premium-card mt-4">
    <div class="card-title-row">
        <h5>Rank History</h5>
        <span>Lifetime Achievements</span>
    </div>

    <div class="table-responsive">
        <table class="table premium-table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Required Volume</th>
                    <th>Achieved Volume</th>
                    <th>Qualified Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rank_history->num_rows > 0): ?>
                    <?php while ($row = $rank_history->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(formatTravelRankName((int)$row['rank_level'])); ?></td>
                            <td>&#8369;<?php echo number_format((float)$row['required_volume'], 2); ?></td>
                            <td>&#8369;<?php echo number_format((float)$row['achieved_volume'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['qualified_at']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted">No travel rank achievements yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
