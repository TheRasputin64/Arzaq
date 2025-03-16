<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid user ID']);
    exit();
}

$userId = (int)$_GET['id'];

// Get user information
$userQuery = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param('i', $userId);
$stmt->execute();
$userResult = $stmt->get_result();

if ($userResult->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'User not found']);
    exit();
}

$user = $userResult->fetch_assoc();

// Get user orders
$ordersQuery = "SELECT o.*, 
                (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) AS items_count 
                FROM orders o 
                WHERE o.user_id = ? 
                ORDER BY o.order_date DESC";
$stmt = $conn->prepare($ordersQuery);
$stmt->bind_param('i', $userId);
$stmt->execute();
$ordersResult = $stmt->get_result();

$orders = [];
while ($order = $ordersResult->fetch_assoc()) {
    $order['order_date'] = date('d/m/Y', strtotime($order['order_date']));
    $orders[] = $order;
}

// Prepare response
$response = [
    'user' => $user,
    'orders' => $orders
];

header('Content-Type: application/json');
echo json_encode($response);