<?php
$sidebar_package_ids = getVldPackageIds();
$sidebar_member = isset($_SESSION['member_id']) ? getMemberPackage($conn, (int)$_SESSION['member_id']) : null;
$sidebar_package_id = $sidebar_member ? (int)$sidebar_member['package_id'] : 0;
$show_dominance_upgrade = $sidebar_package_id === $sidebar_package_ids['vision'] || $sidebar_package_id === $sidebar_package_ids['legacy'];
?>
<div class="app-shell">

<aside class="premium-sidebar" id="memberSidebar">

    <div class="sidebar-brand">
        <img src="assets/logo.png" alt="VLD Global">
        <h5>VLD Global</h5>
        <small>Vision • Legacy • Dominance</small>
    </div>

    <nav class="sidebar-nav">
        <a href="index.php?page=dashboard">Dashboard</a>
        <a href="index.php?page=genealogy">Genealogy</a>
        <a href="index.php?page=directs">Direct Referrals</a>
        <a href="index.php?page=community_bonus">Community Bonus</a>
        <a href="index.php?page=generation_bonus">Generation Bonus</a>
        <a href="index.php?page=chairman_bonus">Chairman Bonus</a>
        <a href="index.php?page=encode_product">Encode Product</a>
        <a href="index.php?page=leadership_ranking">Leadership Ranking</a>
        <?php if ($show_dominance_upgrade): ?>
            <a href="index.php?page=dominance_upgrade">Dominance Upgrade</a>
        <?php endif; ?>
        <a href="index.php?page=payout">Payout</a>
        <a href="index.php?page=payout_history">Payout History</a>
        <a href="index.php?page=profile">Profile</a>
    </nav>

</aside>

<div class="member-sidebar-overlay" id="memberSidebarOverlay"></div>

<main class="premium-main">
