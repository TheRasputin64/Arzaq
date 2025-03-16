<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success' => false, 'message' => 'يرجى تسجيل الدخول أولاً']); exit; }
if (!isset($_POST['cart_item_id'])) { echo json_encode(['success' => false, 'message' => 'معلومات غير صحيحة']); exit; }
$userId = $_SESSION['user_id'];
$cartItemId = $_POST['cart_item_id'];
try {
    $conn->beginTransaction();
    $stmt = $conn->prepare("SELECT c.cart_id FROM cart_items ci JOIN cart c ON ci.cart_id = c.cart_id WHERE ci.cart_item_id = :cart_item_id AND c.user_id = :user_id");
    $stmt->bindParam(':cart_item_id', $cartItemId);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$result) { throw new Exception('المنتج غير موجود في السلة'); }
    $cartId = $result['cart_id'];
    $stmt = $conn->prepare("DELETE FROM cart_items WHERE cart_item_id = :cart_item_id");
    $stmt->bindParam(':cart_item_id', $cartItemId);
    $stmt->execute();
    $stmt = $conn->prepare("SELECT SUM(ci.quantity) as total_items, SUM(ci.quantity * p.price) as subtotal FROM cart_items ci JOIN products p ON ci.product_id = p.product_id WHERE ci.cart_id = :cart_id");
    $stmt->bindParam(':cart_id', $cartId);
    $stmt->execute();
    $cartSummary = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalItems = $cartSummary['total_items'] ? intval($cartSummary['total_items']) : 0;
    $subtotal = $cartSummary['subtotal'] ? number_format($cartSummary['subtotal'], 2) : '0.00';
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'تمت إزالة المنتج من السلة', 'total_items' => $totalItems, 'subtotal' => $subtotal]);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()]);
}
?>
