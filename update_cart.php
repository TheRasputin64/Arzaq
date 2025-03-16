<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success' => false, 'message' => 'يرجى تسجيل الدخول أولاً']); exit; }
if (!isset($_POST['cart_item_id']) || !isset($_POST['change'])) { echo json_encode(['success' => false, 'message' => 'معلومات غير صحيحة']); exit; }
$userId = $_SESSION['user_id'];
$cartItemId = $_POST['cart_item_id'];
$change = intval($_POST['change']);
try {
    $conn->beginTransaction();
    $stmt = $conn->prepare("SELECT ci.cart_item_id, ci.quantity, ci.product_id, p.price, c.cart_id FROM cart_items ci JOIN cart c ON ci.cart_id = c.cart_id JOIN products p ON ci.product_id = p.product_id WHERE ci.cart_item_id = :cart_item_id AND c.user_id = :user_id");
    $stmt->bindParam(':cart_item_id', $cartItemId);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $cartItem = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cartItem) { throw new Exception('المنتج غير موجود في السلة'); }
    $newQuantity = max(1, $cartItem['quantity'] + $change);
    $stmt = $conn->prepare("UPDATE cart_items SET quantity = :quantity WHERE cart_item_id = :cart_item_id");
    $stmt->bindParam(':quantity', $newQuantity);
    $stmt->bindParam(':cart_item_id', $cartItemId);
    $stmt->execute();
    $stmt = $conn->prepare("SELECT SUM(ci.quantity) as total_items, SUM(ci.quantity * p.price) as subtotal FROM cart_items ci JOIN products p ON ci.product_id = p.product_id WHERE ci.cart_id = :cart_id");
    $stmt->bindParam(':cart_id', $cartItem['cart_id']);
    $stmt->execute();
    $cartSummary = $stmt->fetch(PDO::FETCH_ASSOC);
    $conn->commit();
    echo json_encode(['success' => true, 'quantity' => $newQuantity, 'item_total' => number_format($cartItem['price'] * $newQuantity, 2), 'total_items' => $cartSummary['total_items'], 'subtotal' => number_format($cartSummary['subtotal'], 2)]);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()]);
}
?>