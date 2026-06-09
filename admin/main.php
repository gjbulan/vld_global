
<?php
include_once __DIR__ . '/../functions.php';
include 'includes/auth_check.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="admin-content">
    <?php include $page; ?>
</div>

<?php include 'includes/footer.php'; ?>
