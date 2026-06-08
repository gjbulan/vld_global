<?php
include 'functions.php';

$page_key = $_GET['page'] ?? 'dashboard';

if ($page_key === 'members') {
    header("Location: index.php?page=directs");
    exit;
}

switch ($page_key) {
    case 'dashboard': $page = 'pages/dashboard.php'; break;
    case 'genealogy': $page = 'pages/genealogy.php'; break;
    case 'directs': $page = 'pages/directs.php'; break;
    case 'generation_bonus': $page = 'pages/generation_bonus.php'; break;
    case 'community_bonus': $page = 'pages/community_bonus.php'; break;
    case 'encode_product': $page = 'pages/encode_product.php'; break;
    case 'leadership_ranking': $page = 'pages/leadership_ranking.php'; break;
    case 'global_pool': $page = 'pages/global_pool.php'; break;
    case 'dominance_upgrade':
        if (isset($_SESSION['member_id'])) {
            $package_ids = getVldPackageIds();
            $member = getMemberPackage($conn, (int)$_SESSION['member_id']);
            $package_id = $member ? (int)$member['package_id'] : 0;

            if ($package_id !== $package_ids['vision'] && $package_id !== $package_ids['legacy']) {
                header("Location: index.php?page=dashboard");
                exit;
            }
        }

        $page = 'pages/dominance_upgrade.php';
        break;
    case 'payout': $page = 'pages/payout.php'; break;
    case 'payout_history': $page = 'pages/payout_history.php'; break;
    case 'profile': $page = 'pages/profile.php'; break;
    default: $page = 'pages/dashboard.php';
}

include 'main.php';
?>
