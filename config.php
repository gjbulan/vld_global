<?php
// MUST BE FIRST LINE — NO SPACES ABOVE

session_start();

$host = "localhost";
$user = "root";
$pass = "";
$db   = "vld_global";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
