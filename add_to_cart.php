<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success' => false, 'message' => 'يرجى تسجيل الدخول أولاً']); exit; }
if (!isset($_POST['product_id'])) { echo json_encode(['success' => false, 'message' => 'معلومات المنتج غير صحيحة']); exit; }
$userId = $_SESSION['user_id'];
$productId = $_POST['product_id'];
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
try {
    $conn->beginTransaction();
    $stmt = $conn->prepare("SELECT cart_id FROM cart WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $cart = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cart) {
        $stmt = $conn->prepare("INSERT INTO cart (user_id) VALUES (:user_id)");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $cartId = $conn->lastInsertId();
    } else { $cartId = $cart['cart_id']; }
    $stmt = $conn->prepare("SELECT cart_item_id, quantity FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id");
    $stmt->bindParam(':cart_id', $cartId);
    $stmt->bindParam(':product_id', $productId);
    $stmt->execute();
    $cartItem = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cartItem) {
        $newQuantity = $cartItem['quantity'] + $quantity;
        $stmt = $conn->prepare("UPDATE cart_items SET quantity = :quantity WHERE cart_item_id = :cart_item_id");
        $stmt->bindParam(':quantity', $newQuantity);
        $stmt->bindParam(':cart_item_id', $cartItem['cart_item_id']);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (:cart_id, :product_id, :quantity)");
        $stmt->bindParam(':cart_id', $cartId);
        $stmt->bindParam(':product_id', $productId);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->execute();
    }
    $stmt = $conn->prepare("SELECT SUM(quantity) as total_items FROM cart_items WHERE cart_id = :cart_id");
    $stmt->bindParam(':cart_id', $cartId);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'تمت إضافة المنتج إلى السلة', 'total_items' => $result['total_items']]);
} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()]);
}
?>