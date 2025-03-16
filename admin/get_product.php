<?php
session_start();
include 'db.php';
if (!isset($_SESSION['admin_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}
if (isset($_GET['id'])) {
    $productId = intval($_GET['id']);
    $result = $conn->query("SELECT p.*, m.name as market_name, c.category_name 
                           FROM products p 
                           JOIN markets m ON p.market_id = m.market_id 
                           JOIN categories c ON p.category_id = c.category_id 
                           WHERE p.product_id = $productId");
    if ($result && $result->num_rows > 0) {
        $product = $result->fetch_assoc();
        if ($product['image_path']) {
            $product['image_path'] = str_replace('pro/', '../pro/', $product['image_path']);
        }
        echo json_encode($product);
    } else {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Product not found']);
    }
} else {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'No product ID provided']);
}
exit();
?>