

<?php
include 'config.php';

$username = 'root';
$new_password = '123456';
$hash = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE members SET password=?, status='active' WHERE username=?");
$stmt->bind_param("ss", $hash, $username);

if ($stmt->execute()) {
    echo "Root password reset successfully.<br>";
    echo "Username: root<br>";
    echo "Password: 123456<br>";
    echo "<br>DELETE this file after using it.";
} else {
    echo "Failed: " . $conn->error;
}
?>