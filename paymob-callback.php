<?php
session_start();
require_once 'db.php';

function logTransaction($conn, $data) {
    try {
        $stmt = $conn->prepare("INSERT INTO payment_logs (order_id, payment_status, transaction_id, log_date, request_data) VALUES (:order_id, :payment_status, :transaction_id, NOW(), :request_data)");
        $orderId = $data['order']['merchant_order_id'] ?? null;
        $transactionId = $data['id'] ?? null;
        $success = isset($data['success']) && $data['success'] === true ? 'تم الدفع' : 'لم يتم الدفع';
        $requestData = json_encode($data);
        $stmt->bindParam(':order_id', $orderId);
        $stmt->bindParam(':payment_status', $success);
        $stmt->bindParam(':transaction_id', $transactionId);
        $stmt->bindParam(':request_data', $requestData);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Payment Log Error: " . $e->getMessage());
    }
}

function updateOrderStatus($conn, $orderId, $transactionId, $success) {
    try {
        $conn->beginTransaction();
        $paymentStatus = $success ? 'تم الدفع' : 'لم يتم الدفع';
        $orderStatus = $success ? 'processed' : 'cancelled';
        $paymentDate = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE orders SET status = :status, payment_status = :payment_status, payment_date = :payment_date, payment_id = :payment_id WHERE order_id = :order_id");
        $stmt->bindParam(':status', $orderStatus);
        $stmt->bindParam(':payment_status', $paymentStatus);
        $stmt->bindParam(':payment_date', $paymentDate);
        $stmt->bindParam(':payment_id', $transactionId);
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        
        if ($success) {
            $stmt = $conn->prepare("SELECT o.user_id, o.total_amount, u.user_tier, tpv.point_value 
                                  FROM orders o 
                                  JOIN users u ON o.user_id = u.user_id
                                  JOIN tier_points_value tpv ON u.user_tier = tpv.tier_name
                                  WHERE o.order_id = :order_id");
            $stmt->bindParam(':order_id', $orderId);
            $stmt->execute();
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($order) {
                $userId = $order['user_id'];
                $pointsToAdd = floor($order['total_amount'] * (1 / $order['point_value']));
                
                $stmt = $conn->prepare("UPDATE users SET points = points + :points WHERE user_id = :user_id");
                $stmt->bindParam(':points', $pointsToAdd);
                $stmt->bindParam(':user_id', $userId);
                $stmt->execute();
                
                $stmt = $conn->prepare("DELETE ci FROM cart_items ci JOIN cart c ON ci.cart_id = c.cart_id WHERE c.user_id = :user_id");
                $stmt->bindParam(':user_id', $userId);
                $stmt->execute();
            }
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Order Update Error: " . $e->getMessage());
        return false;
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postData = file_get_contents('php://input');
        $data = json_decode($postData, true);
        
        if (!empty($data)) {
            logTransaction($conn, $data);
            
            $transactionId = $data['id'] ?? null;
            $success = isset($data['success']) && $data['success'] === true;
            $orderId = $data['order']['merchant_order_id'] ?? null;
            
            if ($transactionId && $orderId) {
                updateOrderStatus($conn, $orderId, $transactionId, $success);
                echo json_encode(['success' => true]);
                exit;
            }
        }
        
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    } 
    elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $transactionId = isset($_GET['id']) ? $_GET['id'] : null;
        $success = isset($_GET['success']) && $_GET['success'] === 'true';
        $orderId = isset($_SESSION['current_order_id']) ? $_SESSION['current_order_id'] : null;
        
        if ($transactionId && $orderId) {
            updateOrderStatus($conn, $orderId, $transactionId, $success);
            
            header('Location: order_confirmation.php?order_id=' . $orderId . '&success=' . ($success ? 'true' : 'false') . '&id=' . $transactionId);
            exit;
        } else {
            header('Location: orders.php');
            exit;
        }
    }
} catch (Exception $e) {
    error_log("Paymob Callback Error: " . $e->getMessage());
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        header('Location: orders.php?error=' . urlencode('حدث خطأ أثناء معالجة الدفع'));
    }
    exit;
}
?>