<?php
$page_key = $_GET['page'] ?? 'dashboard';

switch ($page_key) {
    case 'dashboard': $page = 'pages/dashboard.php'; break;
    case 'members': $page = 'pages/members.php'; break;
    case 'packages': $page = 'pages/packages.php'; break;
    case 'products': $page = 'pages/products.php'; break;
    case 'product_purchases': $page = 'pages/product_purchases.php'; break;
    case 'bonuses': $page = 'pages/bonuses.php'; break;
    case 'chairman_bonus': $page = 'pages/chairman_bonus.php'; break;
	case 'quarterly_draw': $page = 'pages/quarterly_draw.php'; break;
    case 'cashback': $page = 'pages/cashback.php'; break;
    case 'royalty': $page = 'pages/royalty.php'; break;
    case 'payouts': $page = 'pages/payouts.php'; break;
    case 'leadership_ranks': $page = 'pages/leadership_ranks.php'; break;
    case 'reports': $page = 'pages/reports.php'; break;
    case 'settings': $page = 'pages/settings.php'; break;
    case 'package_codes': $page = 'pages/package_codes.php'; break;
    case 'product_codes': $page = 'pages/product_codes.php'; break;
    default: $page = 'pages/dashboard.php';
}

if ($page_key === 'leadership_ranks' && ($_GET['export'] ?? '') === 'csv') {
    include_once __DIR__ . '/../functions.php';
    include __DIR__ . '/includes/auth_check.php';
    include __DIR__ . '/' . $page;
    exit;
}

include 'main.php';
?>
