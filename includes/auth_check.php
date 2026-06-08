
<?php
/*if (!isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit;
}*/
?>


<?php
if (!isset($_SESSION['member_id']) || empty($_SESSION['member_id'])) {
    header("Location: /vld_global/login.php");
    exit;
}
?>