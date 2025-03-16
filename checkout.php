<?php
session_start();
require_once 'db.php';
require_once 'paymob.php';

// Redirect to login if user is not authenticated
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect='.urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get cart count for header display
$stmt = $conn->prepare("SELECT SUM(ci.quantity) as count FROM cart_items ci JOIN cart c ON ci.cart_id=c.cart_id WHERE c.user_id=:user_id");
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$cartCount = $result['count'] ? $result['count'] : 0;

// Get cart items
$stmt = $conn->prepare("SELECT ci.cart_item_id, ci.quantity, p.product_id, p.name, p.price, p.image_path, p.points, m.name as market_name 
                        FROM cart_items ci 
                        JOIN products p ON ci.product_id=p.product_id 
                        JOIN markets m ON p.market_id=m.market_id 
                        JOIN cart c ON ci.cart_id=c.cart_id 
                        WHERE c.user_id=:user_id");
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Redirect to cart if empty
if(count($cartItems) == 0) {
    header('Location: cart.php');
    exit;
}

// Calculate totals
$totalItems = 0;
$subtotal = 0;
$totalPoints = 0;
foreach($cartItems as $item) {
    $totalItems += $item['quantity'];
    $subtotal += $item['price'] * $item['quantity'];
    $totalPoints += $item['points'] * $item['quantity'];
}

$total = $subtotal;
$payment_token = "";
$iframe_id = "";
$error = "";
$success = "";

// Get user points information
$stmt = $conn->prepare("SELECT u.points, u.user_tier, tpv.point_value, tpv.discount_percentage 
                       FROM users u 
                       JOIN tier_points_value tpv ON u.user_tier = tpv.tier_name 
                       WHERE u.user_id = :user_id");
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$userPointsInfo = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate required points for payment
$pointsNeeded = ceil($total / $userPointsInfo['point_value']);
$canUsePoints = $userPointsInfo['points'] >= $pointsNeeded;

// Handle payment callback from PayMob
$callbackResult = handlePaymentCallback($conn, $userId);
if(isset($callbackResult['success']) && $callbackResult['success']) {
    header('Location: order_confirmation.php?order_id='.$callbackResult['order_id'].'&payment_status='.$callbackResult['payment_status']);
    exit;
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $postal_code = trim($_POST['postal_code'] ?? '');
    $payment_method = trim($_POST['payment_method']);
    
    if(empty($name) || empty($phone) || empty($address) || empty($city)) {
        $error = "جميع الحقول المطلوبة يجب ملؤها";
    } else {
        try {
            if($payment_method === 'points') {
                // Process points payment
                if(!$canUsePoints) {
                    $error = "رصيد النقاط غير كافٍ لإتمام عملية الشراء";
                } else {
                    $pointsResult = processPointsPayment($conn, $userId, $total, $name, $phone, $email, $address, $city, $postal_code, $cartItems);
                    header('Location: order_confirmation.php?order_id='.$pointsResult['order_id'].'&payment_status='.$pointsResult['payment_status'].'&points_used='.$pointsResult['points_used']);
                    exit;
                }
            } elseif($payment_method === 'online') {
                // Process online payment - first create the order
                $conn->beginTransaction();
                $full_address = "$address, $city" . ($postal_code ? ", $postal_code" : "");
                
                $stmt = $conn->prepare("INSERT INTO orders (user_id, order_date, total_amount, status, payment_method, payment_status, shipping_address) 
                                       VALUES (:user_id, NOW(), :total_amount, 'awaiting_payment', 'online', 'awaiting_payment', :shipping_address)");
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
                
                // Update user information
                $stmt = $conn->prepare("UPDATE users SET name=:name, phone=:phone, email=:email, address=:address, state=:city, postal_code=:postal_code 
                                       WHERE user_id=:user_id");
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':city', $city);
                $stmt->bindParam(':postal_code', $postal_code);
                $stmt->bindParam(':user_id', $userId);
                $stmt->execute();
                
                // Process the payment with PayMob
                $paymentResult = processPaymobPayment($conn, $orderId, $userId, $total, $name, $phone, $email, $address, $city, $postal_code, $cartItems);
                $payment_token = $paymentResult['payment_token'];
                $iframe_id = $paymentResult['iframe_id'];
                $success = "تم إنشاء طلب الدفع بنجاح!";
                $_SESSION['current_order_id'] = $orderId; // Store order ID for callback handling
                
                // Note: The cart will be cleared ONLY when payment is confirmed in the callback handling
                $conn->commit();
            }
        } catch(Exception $e) {
            if($conn->inTransaction()) {
                $conn->rollBack();
            }
            $error = "حدث خطأ أثناء معالجة طلبك: " . $e->getMessage();
        }
    }
}

// Get user information for form pre-filling
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id=:user_id");
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إتمام الطلب - أرزاق</title>
  <link rel="stylesheet" href="style.css">
  <link rel="icon" type="image/png" href="ui/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <style>.checkout-page{padding:20px 0;}.steps-container{display:flex;align-items:center;justify-content:space-between;margin-bottom:30px;flex-wrap:wrap;}.step{display:flex;flex-direction:column;align-items:center;position:relative;flex:1;min-width:100px;}.step-number{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:14px;margin-bottom:8px;}.step-active .step-number{background-color:#25995C;color:white;}.step-inactive .step-number{background-color:#e0e0e0;color:#666;}.step-text{font-size:14px;color:#666;text-align:center;}.step-active .step-text{color:#25995C;font-weight:bold;}.steps-line{height:2px;background-color:#e0e0e0;flex:1;margin:0 5px;max-width:80px;}.steps-line.step-active{background-color:#25995C;}.checkout-container{display:flex;gap:20px;margin-top:20px;flex-wrap:wrap;}.checkout-form{flex:2;min-width:300px;}.checkout-summary{flex:1;min-width:250px;}.form-section{background-color:white;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.05);padding:20px;margin-bottom:20px;}.form-section h3{margin-top:0;margin-bottom:15px;padding-bottom:10px;border-bottom:1px solid #eee;}.form-group{margin-bottom:15px;}.form-group label{display:block;margin-bottom:5px;font-weight:bold;font-size:14px;}.form-control{width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;font-family:'Cairo',sans-serif;}.payment-options{display:flex;gap:15px;flex-wrap:wrap;}.payment-option{display:flex;align-items:center;padding:15px;border:1px solid #ddd;border-radius:4px;cursor:pointer;flex:1;min-width:150px;}.payment-option.active{border-color:#25995C;background-color:#f8f8f8;}.payment-option.disabled{opacity:0.5;cursor:not-allowed;}.payment-option input{margin-left:10px;}.payment-option i{font-size:18px;margin-left:10px;color:#25995C;}.checkout-btn{width:100%;padding:12px;background-color:#25995C;color:white;border:none;border-radius:4px;font-weight:bold;cursor:pointer;font-size:16px;}.checkout-btn:hover{background-color:#1c7a48;}.summary-section{background-color:white;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.05);padding:20px;margin-bottom:20px;}.summary-section h3{margin-top:0;margin-bottom:15px;padding-bottom:10px;border-bottom:1px solid #eee;}.cart-items-mini{max-height:300px;overflow-y:auto;}.cart-item-mini{display:flex;padding:10px 0;border-bottom:1px solid #f3f3f3;}.cart-item-mini:last-child{border-bottom:none;}.cart-item-mini-image{width:50px;height:50px;margin-left:10px;}.cart-item-mini-image img{width:100%;height:100%;object-fit:cover;border-radius:4px;}.cart-item-mini-details{flex:1;}.cart-item-mini-title{font-weight:bold;font-size:14px;margin-bottom:3px;}.cart-item-mini-quantity{font-size:12px;color:#666;}.cart-item-mini-price{font-weight:bold;font-size:14px;color:#25995C;}.summary-item{display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px;}.summary-total{font-weight:bold;font-size:16px;margin-top:10px;padding-top:10px;border-top:1px solid #ddd;}.alert{padding:12px 15px;margin-bottom:20px;border-radius:4px;}.alert-danger{background-color:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}.alert-success{background-color:#d4edda;color:#155724;border:1px solid #c3e6cb;}.iframe-container{background-color:white;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.05);padding:20px;margin-top:20px;}.iframe-container h2{margin-top:0;margin-bottom:15px;padding-bottom:10px;border-bottom:1px solid #eee;}.user-points{font-weight:bold;color:#25995C;}.points-info{margin-top:10px;font-size:14px;color:#666;}.tier-info{padding: 10px; background-color: #f8f8f8; border-radius: 5px; margin-top: 10px;}.tier-name{font-weight: bold; color: #25995C;}@media(max-width:768px){.checkout-container{flex-direction:column;}.checkout-summary{order:-1;}.payment-options{flex-direction:column;}.payment-option{width:100%;}}</style>
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
                <div class="user-dropdown">
                    <a href="#" class="user-btn"><i class="fas fa-user"></i> <?= $username ?></a>
                    <div class="dropdown-content">
                        <a href="orders.php"><i class="fas fa-shopping-bag"></i> طلباتي</a>
                        <a href="cart.php"><i class="fas fa-shopping-cart"></i> السلة</a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
                    </div>
                </div>
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
                <li><a href="products.php"><i class="fas fa-th-large"></i> الأقسام</a></li>
                <li><a href="offers.php"><i class="fas fa-tag"></i> العروض</a></li>
                <li><a href="wholesale.php"><i class="fas fa-box"></i> الجملة</a></li>
                <li><a href="markets.php"><i class="fas fa-store"></i> المتاجر</a></li>
                <li><a href="services.php"><i class="fas fa-concierge-bell"></i> الخدمات</a></li>
            </ul>
        </div>
    </div>
  <section class="checkout-page">
    <div class="container">
      <div class="steps-container">
        <div class="step step-active">
          <div class="step-number">1</div>
          <div class="step-text">سلة التسوق</div>
        </div>
        <div class="steps-line step-active"></div>
        <div class="step step-active">
          <div class="step-number">2</div>
          <div class="step-text">معلومات الشحن</div>
        </div>
        <div class="steps-line step-active"></div>
        <div class="step step-active">
          <div class="step-number">3</div>
          <div class="step-text">الدفع</div>
        </div>
        <div class="steps-line"></div>
        <div class="step">
          <div class="step-number">4</div>
          <div class="step-text">تأكيد الطلب</div>
        </div>
      </div>
      <?php if($error):?>
        <div class="alert alert-danger"><?=$error?></div>
      <?php endif;?>
      <?php if($success):?>
        <div class="alert alert-success"><?=$success?></div>
      <?php endif;?>
      <div class="checkout-container">
        <div class="checkout-form">
          <form method="POST" action="">
            <div class="form-section">
              <h3>معلومات الشحن</h3>
              <div class="form-group">
                <label for="name">الاسم الكامل *</label>
                <input type="text" id="name" name="name" class="form-control" required value="<?=$user['name']??''?>">
              </div>
              <div class="form-group">
                <label for="phone">رقم الهاتف *</label>
                <input type="tel" id="phone" name="phone" class="form-control" required value="<?=$user['phone']??''?>">
              </div>
              <div class="form-group">
                <label for="email">البريد الإلكتروني *</label>
                <input type="email" id="email" name="email" class="form-control" required value="<?=$user['email']??''?>">
              </div>
              <div class="form-group">
                <label for="address">العنوان التفصيلي *</label>
                <textarea id="address" name="address" class="form-control" rows="3" required><?=$user['address']??''?></textarea>
              </div>
              <div class="form-group">
                <label for="city">المدينة *</label>
                <input type="text" id="city" name="city" class="form-control" required value="<?=$user['state']??''?>">
              </div>
              <div class="form-group">
                <label for="postal_code">الرمز البريدي *</label>
                <input type="text" id="postal_code" name="postal_code" class="form-control" required value="<?=$user['postal_code']??''?>">
              </div>
              <input type="hidden" name="country" value="SA">
            </div>
            
            <div class="form-section">
              <h3>طريقة الدفع</h3>
              <div class="tier-info">
                <div>مستوى العضوية: <span class="tier-name"><?= $userPointsInfo['user_tier'] ?></span></div>
                <div>رصيد النقاط: <span class="user-points"><?= $userPointsInfo['points'] ?> نقطة</span></div>
                <div>قيمة النقطة: <span><?= number_format($userPointsInfo['point_value'], 2) ?> ريال</span></div>
                <div>نسبة الخصم: <span><?= $userPointsInfo['discount_percentage'] ?>%</span></div>
              </div>
              
              <div class="payment-options">
                <label class="payment-option <?= $canUsePoints ? 'active' : 'disabled' ?>">
                  <input type="radio" name="payment_method" value="points" <?= $canUsePoints ? 'checked' : 'disabled' ?>>
                  <i class="fas fa-coins"></i>
                  <span>الدفع بالنقاط</span>
                </label>
                <label class="payment-option <?= $canUsePoints ? '' : 'active' ?>">
                  <input type="radio" name="payment_method" value="online" <?= $canUsePoints ? '' : 'checked' ?>>
                  <i class="fas fa-credit-card"></i>
                  <span>الدفع الإلكتروني</span>
                </label>
              </div>
              
              <?php if($canUsePoints): ?>
              <div class="points-info">
                <p>سيتم استخدام <strong><?= $pointsNeeded ?></strong> نقطة من رصيدك لإتمام هذا الطلب.</p>
                <p>الرصيد المتبقي بعد الدفع: <strong><?= $userPointsInfo['points'] - $pointsNeeded ?></strong> نقطة</p>
              </div>
              <?php else: ?>
              <div class="points-info">
                <p>رصيد النقاط غير كافٍ للدفع (تحتاج <?= $pointsNeeded ?> نقطة)</p>
                <p>ستحصل على <strong><?= $totalPoints ?></strong> نقطة عند اكتمال هذا الطلب</p>
              </div>
              <?php endif; ?>
            </div>
            
            <button type="submit" class="checkout-btn">إتمام الطلب</button>
          </form>
        </div>
        <div class="checkout-summary">
          <div class="summary-section">
            <h3>ملخص الطلب</h3>
            <div class="cart-items-mini">
              <?php foreach($cartItems as $item):?>
                <div class="cart-item-mini">
                  <div class="cart-item-mini-image">
                    <img src="<?=$item['image_path']?>" alt="<?=$item['name']?>">
                  </div>
                  <div class="cart-item-mini-details">
                    <div class="cart-item-mini-title"><?=$item['name']?></div>
                    <div class="cart-item-mini-quantity">الكمية: <?=$item['quantity']?></div>
                  </div>
                  <div class="cart-item-mini-price">
                    <?=number_format($item['price']*$item['quantity'],2)?> ريال
                  </div>
                </div>
              <?php endforeach;?>
            </div>
          </div>
          <div class="summary-section">
            <h3>التفاصيل</h3>
            <div class="summary-item">
              <span>إجمالي المنتجات:</span>
              <span><?=$totalItems?> منتج</span>
            </div>
            <div class="summary-item">
              <span>المجموع الفرعي:</span>
              <span><?=number_format($subtotal,2)?> ريال</span>
            </div>
            <div class="summary-item">
              <span>النقاط المكتسبة:</span>
              <span><?=$totalPoints?> نقطة</span>
            </div>
            <div class="summary-item">
              <span>الشحن:</span>
              <span>حسب الوجهة</span>
            </div>
            <div class="summary-item summary-total">
              <span>الإجمالي:</span>
              <span><?=number_format($total,2)?> ريال</span>
            </div>
            <?php if($canUsePoints): ?>
            <div class="summary-item">
              <span>أو بالنقاط:</span>
              <span><?=$pointsNeeded?> نقطة</span>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <?php if ($payment_token): ?>
        <div class="iframe-container">
            <h2>الدفع الإلكتروني</h2>
            <iframe src="https://ksa.paymob.com/api/acceptance/iframes/<?php echo $iframe_id; ?>?payment_token=<?php echo $payment_token; ?>" width="100%" height="500px"></iframe>
        </div>
      <?php endif; ?>
    </div>
  </section>
  <footer>
    <div class="container">
      <div class="footer-content">
        <div class="footer-logo"><img src="ui/footer.png" alt="أرزاق"></div>
        <div class="footer-links"></div>
        <div class="footer-links">
          <h3>روابط سريعة</h3>
          <ul>
            <li><a href="#">اشتر بالجملة</a></li>
            <li><a href="#">بيع في أرزاق</a></li>
            <li><a href="#">سياسة الخصوصية</a></li>
            <li><a href="#">شروط الخدمة</a></li>
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
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const userDropdown = document.querySelector('.user-dropdown');
    if(userDropdown) {
      userDropdown.addEventListener('click', function(e) {
        if(e.target.closest('.user-btn')) {
          e.preventDefault();
          this.classList.toggle('active');
        }
      });
      
      document.addEventListener('click', function(e) {
        if(!e.target.closest('.user-dropdown')) {
          userDropdown.classList.remove('active');
        }
      });
    }
    
    const paymentOptions = document.querySelectorAll('.payment-option:not(.disabled)');
    paymentOptions.forEach(option => {
      option.addEventListener('click', function() {
        paymentOptions.forEach(opt => opt.classList.remove('active'));
        this.classList.add('active');
        this.querySelector('input').checked = true;
      });
    });
  });
  </script>
</body>
</html>