<?php
session_start();
include 'db.php';
if (!isset($_SESSION['market_id'])) {header("Location: market.php");exit();}
header("Location: market_dashboard.php");
exit();
?>