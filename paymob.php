<?php
session_start();
require_once 'db.php';

function callPaymobAPI($url, $data) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
            "content-type: application/json"
        ),
    ));
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    if ($err) {
        return ["error" => "cURL Error #:" . $err];
    } else {
        return json_decode($response, true);
    }
}

function getPaymobConfig($conn) {
    $stmt = $conn->prepare("SELECT paymob_api_key, paymob_integration_id, paymob_iframe_id, paymob_hmac FROM admin LIMIT 1");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserPointsValue($conn, $userId) {
    $stmt = $conn->prepare("SELECT u.points, tpv.point_value, tpv.discount_percentage 
                           FROM users u 
                           JOIN tier_points_value tpv ON u.user_tier = tpv.tier_name 
                           WHERE u.user_id = :user_id");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function processPointsPayment($conn, $userId, $total, $name, $phone, $email, $address, $city, $postal_code, $cartItems) {
    $pointsInfo = getUserPointsValue($conn, $userId);
    $pointsNeeded = ceil($total / $pointsInfo['point_value']);
    
    if($pointsInfo['points'] < $pointsNeeded) {
        throw new Exception("رصيد النقاط غير كافٍ لإتمام عملية الشراء");
    }
    
    try {
        $conn->beginTransaction();
        
        $full_address = "$address, $city" . ($postal_code ? ", $postal_code" : "");
        
        $stmt = $conn->prepare("INSERT INTO orders (user_id, order_date, total_amount, status, payment_method, payment_status, shipping_address) 
                               VALUES (:user_id, NOW(), :total_amount, 'processed', 'points', 'تم الدفع', :shipping_address)");
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':total_amount', $total);
        $stmt->bindParam(':shipping_address', $full_address);
        $stmt->execute();
        $orderId = $conn->lastInsertId();
        
        foreach($cartItems as $item) {
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) 
                                   VALUES (:order_id, :product_id, :quantity, :price)");
            $stmt->bindParam(':order_id', $orderId);
            $stmt->bindParam(':product_id', $item['product_id']);
            $stmt->bindParam(':quantity', $item['quantity']);
            $stmt->bindParam(':price', $item['price']);
            $stmt->execute();
        }
        
        $stmt = $conn->prepare("DELETE ci FROM cart_items ci JOIN cart c ON ci.cart_id=c.cart_id WHERE c.user_id=:user_id");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        $newPoints = $pointsInfo['points'] - $pointsNeeded;
        $stmt = $conn->prepare("UPDATE users SET points=:points, name=:name, phone=:phone, email=:email, 
                               address=:address, state=:city, postal_code=:postal_code WHERE user_id=:user_id");
        $stmt->bindParam(':points', $newPoints);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':city', $city);
        $stmt->bindParam(':postal_code', $postal_code);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        $conn->commit();
        
        return [
            'success' => true,
            'order_id' => $orderId,
            'payment_status' => 'تم الدفع',
            'points_used' => $pointsNeeded
        ];
    } catch(Exception $e) {
        if($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}

function processPaymobPayment($conn, $orderId, $userId, $total, $name, $phone, $email, $address, $city, $postal_code, $cartItems) {
    $paymentConfig = getPaymobConfig($conn);
    
    $auth_data = callPaymobAPI("https://ksa.paymob.com/api/auth/tokens", [
        "api_key" => $paymentConfig['paymob_api_key']
    ]);

    if (isset($auth_data['error'])) {
        throw new Exception("خطأ في المصادقة مع خدمة الدفع: " . $auth_data['error']);
    } elseif (!isset($auth_data['token'])) {
        throw new Exception("خطأ في المصادقة مع خدمة الدفع: بيانات الاستجابة غير كاملة");
    }
    
    $auth_token = $auth_data['token'];
    $amount_cents = (int)(round($total * 100));
    
    $itemsData = [];
    foreach($cartItems as $item) {
        $itemsData[] = [
            "name" => $item['name'],
            "amount_cents" => (int)(round($item['price'] * 100)),
            "description" => $item['name'] . " - " . $item['market_name'],
            "quantity" => $item['quantity']
        ];
    }
    
    $order_data = callPaymobAPI("https://ksa.paymob.com/api/ecommerce/orders", [
        "auth_token" => $auth_token,
        "delivery_needed" => "false",
        "amount_cents" => $amount_cents,
        "currency" => "SAR",
        "merchant_order_id" => $orderId,
        "items" => $itemsData
    ]);

    if (isset($order_data['error'])) {
        throw new Exception("خطأ في إنشاء الطلب: " . $order_data['error']);
    } elseif (!isset($order_data['id'])) {
        throw new Exception("خطأ في إنشاء الطلب: بيانات الاستجابة غير كاملة");
    }
    
    $paymob_order_id = $order_data['id'];
    
    $stmt = $conn->prepare("UPDATE orders SET payment_reference=:payment_reference WHERE order_id=:order_id");
    $stmt->bindParam(':payment_reference', $paymob_order_id);
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    
    $names = explode(' ', $name, 2);
    $first_name = $names[0];
    $last_name = isset($names[1]) ? $names[1] : $first_name;
    
    $billing_data = [
        "apartment" => "NA",
        "email" => $email ? $email : "customer@example.com",
        "floor" => "NA",
        "first_name" => $first_name,
        "street" => $address,
        "building" => "NA",
        "phone_number" => $phone,
        "shipping_method" => "NA",
        "postal_code" => $postal_code ? $postal_code : "NA",
        "city" => $city,
        "country" => "SA",
        "last_name" => $last_name,
        "state" => $city
    ];

    $payment_key_data = callPaymobAPI("https://ksa.paymob.com/api/acceptance/payment_keys", [
        "auth_token" => $auth_token,
        "amount_cents" => $amount_cents,
        "expiration" => 3600,
        "order_id" => $paymob_order_id,
        "billing_data" => $billing_data,
        "currency" => "SAR",
        "integration_id" => $paymentConfig['paymob_integration_id'],
        "lock_order_when_paid" => "false"
    ]);

    if (isset($payment_key_data['error'])) {
        throw new Exception("خطأ في إنشاء مفتاح الدفع: " . $payment_key_data['error']);
    } elseif (!isset($payment_key_data['token'])) {
        throw new Exception("خطأ في إنشاء مفتاح الدفع: بيانات الاستجابة غير كاملة");
    }
    
    $_SESSION['paymob_order_id'] = $paymob_order_id;
    $_SESSION['current_order_id'] = $orderId;
    
    return [
        'payment_token' => $payment_key_data['token'],
        'iframe_id' => $paymentConfig['paymob_iframe_id']
    ];
}

function handlePaymentCallback($conn, $userId) {
    if (isset($_GET['success']) && isset($_GET['id']) && isset($_SESSION['current_order_id'])) {
        $success = $_GET['success'] === 'true';
        $transactionId = $_GET['id'];
        $orderId = $_SESSION['current_order_id'];
        
        $stmt = $conn->prepare("SELECT payment_status FROM orders WHERE order_id = :order_id");
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['payment_status'] !== 'تم الدفع' && $success) {
            updateOrderStatus($conn, $transactionId, $success, $orderId);
            return [
                'success' => true,
                'order_id' => $orderId,
                'payment_status' => 'تم الدفع'
            ];
        } elseif ($result && !$success) {
            return [
                'success' => true,
                'order_id' => $orderId,
                'payment_status' => 'لم يتم الدفع'
            ];
        }
    }
    
    return ['success' => false];
}

function updateOrderStatus($conn, $transactionId, $success, $orderId = null) {
    try {
        $conn->beginTransaction();
        
        if (!$orderId) {
            $stmt = $conn->prepare("SELECT merchant_order_id FROM paymob_transactions WHERE transaction_id = :transaction_id");
            $stmt->bindParam(':transaction_id', $transactionId);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $orderId = $result['merchant_order_id'];
            } else {
                throw new Exception("لم يتم العثور على معرف الطلب");
            }
        }
        
        $new_status = $success ? 'تم الدفع' : 'لم يتم الدفع';
        $order_status = $success ? 'processed' : 'cancelled';
        $payment_date = date('Y-m-d H:i:s');
        
        $stmt = $conn->prepare("UPDATE orders SET status = :status, payment_status = :payment_status, 
                               payment_date = :payment_date, payment_id = :payment_id 
                               WHERE order_id = :order_id");
        $stmt->bindParam(':status', $order_status);
        $stmt->bindParam(':payment_status', $new_status);
        $stmt->bindParam(':payment_date', $payment_date);
        $stmt->bindParam(':payment_id', $transactionId);
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        
        if ($success) {
            $stmt = $conn->prepare("SELECT o.user_id, o.total_amount FROM orders o WHERE o.order_id = :order_id");
            $stmt->bindParam(':order_id', $orderId);
            $stmt->execute();
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($order) {
                $userId = $order['user_id'];
                $pointsToAdd = floor($order['total_amount']);
                
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
        error_log("PayMob Callback Error: " . $e->getMessage());
        return false;
    }
}