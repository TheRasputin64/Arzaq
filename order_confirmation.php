<?php
session_start();
require_once 'db.php';

if (!isset($_GET['order_id'])) {
    header('Location: orders.php');
    exit;
}

$orderId = $_GET['order_id'];
$success = isset($_GET['success']) && $_GET['success'] === 'true';
$transactionId = isset($_GET['id']) ? $_GET['id'] : null;

$loggedIn = isset($_SESSION['user_id']);
$username = $loggedIn ? $_SESSION['username'] : '';

if ($loggedIn) {
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT SUM(ci.quantity) as count FROM cart_items ci JOIN cart c ON ci.cart_id = c.cart_id WHERE c.user_id = :user_id");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $cartCount = $result['count'] ? $result['count'] : 0;
    
    $stmt = $conn->prepare("SELECT points, user_tier FROM users WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
}

$stmt = $conn->prepare("SELECT * FROM categories WHERE is_main = 1 LIMIT 8");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($transactionId && isset($_GET['success'])) {
    try {
        $conn->beginTransaction();
        $paymentStatus = $success ? 'تم الدفع' : 'لم يتم الدفع';
        $orderStatus = $success ? 'processed' : 'cancelled';
        $paymentDate = date('Y-m-d H:i:s');
        
        $stmt = $conn->prepare("UPDATE orders SET status = :status, payment_status = :payment_status, 
                               payment_date = :payment_date, payment_id = :payment_id 
                               WHERE order_id = :order_id");
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
        unset($_SESSION['current_order_id']);
        unset($_SESSION['paymob_order_id']);
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Order Update Error: " . $e->getMessage());
    }
}

function getOrderDetails($conn, $orderId) {
    $stmt = $conn->prepare("SELECT o.*, u.name, u.phone, u.email, u.user_tier, tpv.point_value  
                           FROM orders o 
                           JOIN users u ON o.user_id = u.user_id 
                           JOIN tier_points_value tpv ON u.user_tier = tpv.tier_name
                           WHERE o.order_id = :order_id");
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getOrderItems($conn, $orderId) {
    $stmt = $conn->prepare("SELECT oi.*, p.name, p.image_path, m.name as market_name 
                           FROM order_items oi 
                           JOIN products p ON oi.product_id = p.product_id 
                           JOIN markets m ON p.market_id = m.market_id 
                           WHERE oi.order_id = :order_id");
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
    $order = getOrderDetails($conn, $orderId);
    if (!$order) {
        throw new Exception("Order not found");
    }
    $orderItems = getOrderItems($conn, $orderId);
    $pointsEarned = floor($order['total_amount'] * (1 / $order['point_value']));
} catch (Exception $e) {
    error_log("Order Confirmation Error: " . $e->getMessage());
    $error = "حدث خطأ أثناء معالجة الطلب. يرجى المحاولة مرة أخرى.";
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تأكيد الطلب - أرزاق</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="ui/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    .order-confirmation{max-width:900px;margin:20px auto;padding:20px;background-color:#fff;border-radius:10px;box-shadow:0 0 15px rgba(0,0,0,0.05);}.payment-success,.payment-failed,.payment-pending{padding:25px;margin-bottom:20px;border-radius:8px;text-align:center;position:relative;overflow:hidden;}.payment-success{background:linear-gradient(135deg,#e3f5e9 0%,#d1f5e4 100%);border-right:5px solid #25995C;}.payment-success h1{color:#25995C;font-size:1.8rem;margin-bottom:10px;}.payment-success p{color:#186c41;}.payment-success::before{content:'\f058';font-family:'Font Awesome 6 Free';font-weight:900;position:absolute;font-size:120px;color:rgba(37,153,92,0.07);bottom:-30px;left:-10px;transform:rotate(-15deg);}.payment-failed{background:linear-gradient(135deg,#ffecec 0%,#ffe0e0 100%);border-right:5px solid #dc3545;}.payment-failed h1{color:#dc3545;font-size:1.8rem;margin-bottom:10px;}.payment-failed p{color:#9c1c2b;}.payment-failed::before{content:'\f057';font-family:'Font Awesome 6 Free';font-weight:900;position:absolute;font-size:120px;color:rgba(220,53,69,0.07);bottom:-30px;left:-10px;transform:rotate(-15deg);}.payment-pending{background:linear-gradient(135deg,#fff8e6 0%,#fff4d9 100%);border-right:5px solid #fd7e14;}.payment-pending h1{color:#fd7e14;font-size:1.8rem;margin-bottom:10px;}.payment-pending p{color:#b35600;}.payment-pending::before{content:'\f252';font-family:'Font Awesome 6 Free';font-weight:900;position:absolute;font-size:120px;color:rgba(253,126,20,0.07);bottom:-30px;left:-10px;transform:rotate(-15deg);}.order-details{margin:20px 0;padding:20px;border:1px solid #eee;border-radius:8px;background-color:#f9f9f9;}.order-details h2{color:#25995C;margin-bottom:15px;position:relative;padding-bottom:8px;}.order-details h2:after{content:'';position:absolute;width:50px;height:3px;background-color:#25995C;bottom:0;right:0;}.order-summary{margin-bottom:20px;display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:10px;}.order-summary p{margin:8px 0;padding:8px;background-color:#fff;border-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,0.05);display:flex;justify-content:space-between;}.order-summary p strong{color:#444;}.shipping-info{margin-bottom:20px;}.shipping-info h3{color:#25995C;margin-bottom:10px;}.shipping-info p{margin:6px 0;}.order-items{margin-top:30px;}.order-items h3{color:#25995C;margin-bottom:15px;}.order-items table{width:100%;border-collapse:collapse;}.order-items th{background-color:#f0f7f4;color:#25995C;text-align:right;padding:10px;}.order-items td{padding:10px;border-bottom:1px solid #eee;vertical-align:middle;}.order-items img{max-width:50px;border-radius:4px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}.order-items tfoot td{font-weight:bold;background-color:#f9f9f9;}.order-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:30px;}.earned-points{position:relative;border-radius:8px;padding:15px;text-align:center;background:linear-gradient(135deg,#e7f8ff 0%,#d0f1ff 100%);border-right:5px solid #0099e5;margin:20px 0;overflow:hidden;}.earned-points p{color:#0077b6;font-size:1.2rem;z-index:2;position:relative;}.earned-points strong{font-size:1.4rem;color:#0099e5;animation:pulse 2s infinite;}.earned-points::before{content:'\f005';font-family:'Font Awesome 6 Free';font-weight:900;position:absolute;font-size:120px;color:rgba(0,153,229,0.07);bottom:-30px;left:-10px;transform:rotate(-15deg);}.earned-points::after{content:'';position:absolute;width:120px;height:120px;background:radial-gradient(circle,rgba(0,153,229,0.1) 0%,rgba(255,255,255,0) 70%);top:10px;right:10px;border-radius:50%;animation:glow 3s infinite alternate;}.points-animation{display:inline-block;}.order-status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:0.85rem;font-weight:bold;}.order-status-processed{background-color:#d1e7dd;color:#0f5132;}.order-status-cancelled{background-color:#f8d7da;color:#842029;}.order-status-pending{background-color:#fff3cd;color:#664d03;}.payment-status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:0.85rem;font-weight:bold;}.payment-paid{background-color:#d1e7dd;color:#0f5132;}.payment-unpaid{background-color:#f8d7da;color:#842029;}.payment-pending{background-color:#fff3cd;color:#664d03;}@keyframes pulse{0%{transform:scale(1);}50%{transform:scale(1.1);}100%{transform:scale(1);}}@keyframes glow{0%{opacity:0.3;}100%{opacity:0.8;}}@keyframes fadeIn{0%{opacity:0;transform:translateY(-20px);}100%{opacity:1;transform:translateY(0);}}@keyframes spin{to{transform:rotate(360deg);}}@keyframes float{0%{transform:translateY(0px);}50%{transform:translateY(-10px);}100%{transform:translateY(0px);}}@media(max-width:768px){.order-summary{grid-template-columns:1fr;}.order-items table{font-size:0.9rem;}.order-actions{justify-content:center;}.payment-success h1,.payment-failed h1,.payment-pending h1{font-size:1.5rem;}.earned-points p{font-size:1.1rem;}.earned-points strong{font-size:1.3rem;}}@media(max-width:576px){.order-confirmation{padding:15px 10px;}.payment-success,.payment-failed,.payment-pending{padding:15px;}.payment-success h1,.payment-failed h1,.payment-pending h1{font-size:1.3rem;}.order-details h2{font-size:1.2rem;}.order-items th,.order-items td{padding:8px 5px;font-size:0.85rem;}.order-items img{max-width:40px;}.order-actions .btn{font-size:0.85rem;padding:8px 12px;}.earned-points{padding:12px;}.earned-points p{font-size:1rem;}.earned-points strong{font-size:1.2rem;}}@keyframes pointsUp{0%{opacity:0;transform:translateY(20px);}25%{opacity:1;}75%{transform:translateY(-20px);}100%{opacity:0;transform:translateY(-40px);}}
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="container">
            <div class="top-links">
                <a href="#about-section">عن أرزاق</a>
                <a href="#partners-section">شركاؤنا</a>
                <a href="contact.php">اتصل بنا</a>
            </div>
            <div class="lang-currency">
                <div class="currency">
                    <i class="fas fa-coins"></i> ريال سعودي
                </div>
                <div class="lang-switch">
                    <a href="?lang=ar" class="active">العربية</a>
                    <span>|</span>
                    <a href="?lang=en">English</a>
                </div>
            </div>
        </div>
    </div>

    <nav class="navbar">
        <div class="container">
            <div class="logo">
                <a href="index.php"><img src="ui/logo.png" alt="أرزاق"></a>
            </div>
            
            <div class="search-bar">
                <form action="search.php" method="GET">
                    <input type="text" name="q" placeholder="ما الذي تبحث عنه؟">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
            
            <div class="user-actions">
                <?php if(!$loggedIn): ?>
                <a href="login.php" class="login-btn"><i class="fas fa-sign-in-alt"></i> تسجيل الدخول</a>
                <?php else: ?>
                <div class="user-panel">
                    <div class="user-details">
                        <span class="user-name"><?= $username ?></span>
                        <span class="user-points"><i class="fas fa-star"></i> <?= $userInfo['points'] ?? 0 ?> نقطة</span>
                    </div>
                    <div class="user-quick-links">
                        <a href="orders.php" class="quick-link"><i class="fas fa-shopping-bag"></i> طلباتي</a>
                        <a href="profile.php" class="quick-link"><i class="fas fa-user-cog"></i> الملف</a>
                        <a href="logout.php" class="quick-link logout"><i class="fas fa-sign-out-alt"></i></a>
                    </div>
                </div>
                <?php endif; ?>
                <a href="cart.php" class="cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if($cartCount > 0): ?>
                    <span class="cart-count"><?= $cartCount ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </nav>
    
    <div class="main-menu">
        <div class="container">
            <ul class="menu-links">
                <li><a href="index.php"><i class="fas fa-home"></i> الرئيسية</a></li>
                <li><a href="categories.php"><i class="fas fa-th-large"></i> الأقسام</a></li>
                <li><a href="products.php"><i class="fas fa-tag"></i> المنتجات</a></li>
                <li><a href="wholesale.php"><i class="fas fa-box"></i> الجملة</a></li>
                <li><a href="markets.php"><i class="fas fa-store"></i> المتاجر</a></li>
                <li><a href="workers/select.php"><i class="fas fa-concierge-bell"></i> الخدمات</a></li>
            </ul>
        </div>
    </div>

    <main>
        <div class="container">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php else: ?>
                <div class="order-confirmation">
                    <?php if ($order['payment_status'] === 'تم الدفع'): ?>
                        <div class="payment-success">
                            <h1>تم الدفع بنجاح!</h1>
                            <p>شكراً لك. تم استلام طلبك وسيتم معالجته قريباً.</p>
                        </div>
                    <?php elseif ($order['payment_status'] === 'لم يتم الدفع'): ?>
                        <div class="payment-failed">
                            <h1>فشل الدفع</h1>
                            <p>لم يتم استلام الدفع. يرجى المحاولة مرة أخرى.</p>
                            <a href="checkout.php?retry=<?php echo $order['order_id']; ?>" class="btn btn-primary">إعادة محاولة الدفع</a>
                        </div>
                    <?php else: ?>
                        <div class="payment-pending">
                            <h1>الطلب معلق</h1>
                            <p>لم يتم استلام الدفع بعد. يرجى التحقق من حالة الدفع.</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="order-details">
                        <h2>تفاصيل الطلب</h2>
                        <div class="order-summary">
                            <p><strong>رقم الطلب:</strong> <span><?php echo $order['order_id']; ?></span></p>
                            <p><strong>تاريخ الطلب:</strong> <span><?php echo date('Y-m-d H:i', strtotime($order['order_date'])); ?></span></p>
                            <p>
                                <strong>حالة الطلب:</strong> 
                                <span class="order-status-badge <?php echo $order['status'] === 'processed' ? 'order-status-processed' : ($order['status'] === 'cancelled' ? 'order-status-cancelled' : 'order-status-pending'); ?>">
                                    <?php echo $order['status'] === 'processed' ? 'تمت المعالجة' : ($order['status'] === 'cancelled' ? 'ملغي' : 'معلق'); ?>
                                </span>
                            </p>
                            <p>
                                <strong>حالة الدفع:</strong> 
                                <span class="payment-status-badge <?php echo $order['payment_status'] === 'تم الدفع' ? 'payment-paid' : ($order['payment_status'] === 'لم يتم الدفع' ? 'payment-unpaid' : 'payment-pending'); ?>">
                                    <?php echo $order['payment_status']; ?>
                                </span>
                            </p>
                            <p><strong>طريقة الدفع:</strong> <span><?php echo $order['payment_method'] === 'points' ? 'نقاط' : 'بطاقة الائتمان'; ?></span></p>
                            <?php if ($order['payment_date']): ?>
                                <p><strong>تاريخ الدفع:</strong> <span><?php echo date('Y-m-d H:i', strtotime($order['payment_date'])); ?></span></p>
                            <?php endif; ?>
                            <?php if ($order['payment_id']): ?>
                                <p><strong>رقم المعاملة:</strong> <span><?php echo $order['payment_id']; ?></span></p>
                            <?php endif; ?>
                            <p><strong>إجمالي المبلغ:</strong> <span><?php echo number_format($order['total_amount'], 2); ?> ريال</span></p>
                        </div>
                        
                        <div class="shipping-info">
                            <h3>معلومات الشحن</h3>
                            <p><strong>الاسم:</strong> <?php echo $order['name']; ?></p>
                            <p><strong>الهاتف:</strong> <?php echo $order['phone']; ?></p>
                            <?php if ($order['email']): ?>
                                <p><strong>البريد الإلكتروني:</strong> <?php echo $order['email']; ?></p>
                            <?php endif; ?>
                            <p><strong>عنوان الشحن:</strong> <?php echo $order['shipping_address']; ?></p>
                        </div>
                    </div>
                    
                    <div class="order-items">
                        <h3>المنتجات</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>المنتج</th>
                                    <th>المتجر</th>
                                    <th>السعر</th>
                                    <th>الكمية</th>
                                    <th>المجموع</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td>
                                            <?php if ($item['image_path']): ?>
                                                <img src="<?php echo $item['image_path']; ?>" alt="<?php echo $item['name']; ?>">
                                            <?php endif; ?>
                                            <?php echo $item['name']; ?>
                                        </td>
                                        <td><?php echo $item['market_name']; ?></td>
                                        <td><?php echo number_format($item['price'], 2); ?> ريال</td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td><?php echo number_format($item['price'] * $item['quantity'], 2); ?> ريال</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4"><strong>المجموع:</strong></td>
                                    <td><?php echo number_format($order['total_amount'], 2); ?> ريال</td>
                                </tr>
                                <?php if ($order['payment_method'] === 'points'): ?>
                                    <tr>
                                        <td colspan="4"><strong>النقاط المستخدمة:</strong></td>
                                        <td><?php echo floor($order['total_amount']); ?> نقطة</td>
                                    </tr>
                                <?php endif; ?>
                            </tfoot>
                        </table>
                    </div>
                    
                    <?php if ($order['payment_status'] === 'تم الدفع'): ?>
                        <div class="earned-points">
                            <p>لقد ربحت <strong class="points-animation"><?php echo $pointsEarned; ?></strong> نقطة من هذا الطلب!</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="order-actions">
                        <a href="orders.php" class="btn-primary">عرض جميع الطلبات</a>
                        <a href="index.php" class="btn-secondary">العودة للرئيسية</a>
                        <?php if ($order['payment_status'] !== 'تم الدفع' && $order['payment_method'] === 'credit'): ?>
                            <a href="checkout.php?retry=<?php echo $order['order_id']; ?>" class="btn-primary">إعادة محاولة الدفع</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <img src="ui/footer.png" alt="أرزاق">
                </div>
                <div class="footer-links">
                    <h3>روابط سريعة</h3>
                    <ul>
                        <li><a href="wholesale.php">اشتر بالجملة</a></li>
                        <li><a href="market.php">بيع في أرزاق</a></li>
                        <li><a href="privacy.php">سياسة الخصوصية</a></li>
                        <li><a href="terms.php">شروط الخدمة</a></li>
                    </ul>
                </div>
                <div class="footer-links">
                    <h3>تواصل معنا</h3>
                    <ul>
                        <li><a href="mailto:Arzaqplus10@gmail.com">Arzaqplus10@gmail.com</a></li>
                        <li><a href="mailto:Arzaqplus@outlook.com">Arzaqplus@outlook.com</a></li>
                        <li><a href="https://t.me/Arzaqplus">Telegram: Arzaqplus</a></li>
                    </ul>
                </div>
                <div class="footer-links">
                    <h3>تابعنا</h3>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>جميع الحقوق محفوظة © 2025 أرزاق</p>
            </div>
        </div>
    </footer>
    <a href="https://wa.me/1234567890" target="_blank" class="whatsapp-button"><i class="fab fa-whatsapp"></i></a>
    
    <script>document.addEventListener('DOMContentLoaded', function() { <?php if (isset($_GET['success'])): ?> <?php if ($success): ?> const successDiv = document.querySelector('.payment-success'); if (successDiv) { successDiv.style.animation = 'fadeIn 1s ease-in-out'; } const pointsAnimation = document.querySelector('.points-animation'); if (pointsAnimation) { pointsAnimation.style.animation = 'pulse 2s infinite'; } <?php else: ?> const failureDiv = document.querySelector('.payment-failed'); if (failureDiv) { failureDiv.style.animation = 'fadeIn 1s ease-in-out'; } <?php endif; ?> <?php endif; ?> });</script>
</body>
</html>