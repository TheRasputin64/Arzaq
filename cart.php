<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) {header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));exit;}
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$stmt = $conn->prepare("SELECT c.cart_id FROM cart c WHERE c.user_id = :user_id");
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$cart = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$cart) {
    $stmt = $conn->prepare("INSERT INTO cart (user_id) VALUES (:user_id)");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $cartId = $conn->lastInsertId();
} else {
    $cartId = $cart['cart_id'];
}
$stmt = $conn->prepare("
    SELECT ci.cart_item_id, ci.quantity, p.product_id, p.name, p.price, p.image_path, p.points, 
           p.is_local, p.is_new, p.has_cashback, p.cashback_percentage, m.name as market_name
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.product_id
    JOIN markets m ON p.market_id = m.market_id
    JOIN cart c ON ci.cart_id = c.cart_id
    WHERE c.user_id = :user_id
");
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalItems = 0;
$subtotal = 0;
foreach ($cartItems as $item) {
    $totalItems += $item['quantity'];
    $subtotal += $item['price'] * $item['quantity'];
}
$stmt = $conn->prepare("SELECT SUM(ci.quantity) as count FROM cart_items ci JOIN cart c ON ci.cart_id = c.cart_id WHERE c.user_id = :user_id");
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$cartCount = $result['count'] ? $result['count'] : 0;
$stmt = $conn->prepare("SELECT p.*, m.name as market_name FROM products p JOIN markets m ON p.market_id = m.market_id WHERE p.is_active = 1 ORDER BY RAND() LIMIT 4");
$stmt->execute();
$recommendedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $conn->prepare("SELECT points, user_tier FROM users WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سلة التسوق - أرزاق</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="ui/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>.cart-page{padding:20px 0;}.steps-container{display:flex;align-items:center;justify-content:space-between;margin-bottom:30px;flex-wrap:wrap;}.step{display:flex;flex-direction:column;align-items:center;position:relative;flex:1;min-width:100px;}.step-number{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:14px;margin-bottom:8px;}.step-active .step-number{background-color:#25995C;color:white;}.step-inactive .step-number{background-color:#e0e0e0;color:#666;}.step-text{font-size:14px;color:#666;text-align:center;}.step-active .step-text{color:#25995C;font-weight:bold;}.steps-line{height:2px;background-color:#e0e0e0;flex:1;margin:0 5px;max-width:80px;}.cart-features{display:flex;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;}.cart-feature{display:flex;align-items:center;background-color:#f9f9f9;padding:10px;border-radius:8px;flex:1;min-width:200px;}.cart-feature i{font-size:20px;color:#25995C;margin-left:10px;}.cart-feature-text h4{margin:0;font-size:14px;color:#333;}.cart-feature-text p{margin:3px 0 0;font-size:12px;color:#666;}.cart-container{background-color:white;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.05);margin-bottom:20px;}.cart-header{display:flex;justify-content:space-between;align-items:center;padding:15px;border-bottom:1px solid #eee;}.cart-header h2{margin:0;font-size:18px;color:#333;}.cart-header span{font-size:14px;color:#666;}.cart-items{padding:0 15px;}.cart-item{display:flex;padding:15px 0;border-bottom:1px solid #f3f3f3;position:relative;}.cart-item:last-child{border-bottom:none;}.cart-item-image{width:80px;height:80px;margin-left:15px;}.cart-item-image img{width:100%;height:100%;object-fit:cover;border-radius:4px;}.cart-item-details{flex:1;}.cart-item-title{font-weight:bold;margin-bottom:5px;}.cart-item-title a{color:#333;text-decoration:none;}.cart-item-meta{font-size:13px;color:#666;margin-bottom:5px;}.cart-item-price{font-weight:bold;color:#25995C;}.cart-item-badges{display:flex;flex-wrap:wrap;gap:5px;margin-top:5px;}.item-badge{padding:2px 6px;border-radius:3px;font-size:11px;}.cart-item-actions{display:flex;flex-direction:column;align-items:flex-end;justify-content:space-between;min-width:120px;}.cart-item-quantity{display:flex;align-items:center;margin-bottom:10px;}.quantity-btn{width:25px;height:25px;background-color:#f3f3f3;display:flex;align-items:center;justify-content:center;cursor:pointer;border-radius:4px;}.quantity-input{width:30px;text-align:center;margin:0 5px;border:1px solid #ddd;border-radius:4px;padding:3px 0;}.cart-item-total{font-weight:bold;margin-bottom:10px;}.cart-item-remove{color:#dc3545;cursor:pointer;}.cart-summary{background-color:#f9f9f9;padding:15px;border-top:1px solid #eee;}.cart-summary-row{display:flex;justify-content:space-between;margin-bottom:10px;}.cart-summary-total{font-weight:bold;font-size:18px;margin-top:10px;padding-top:10px;border-top:1px solid #ddd;}.checkout-btn{width:100%;padding:12px;background-color:#25995C;color:white;border:none;border-radius:4px;font-weight:bold;cursor:pointer;margin-top:10px;}.checkout-btn:hover{background-color:#1c7a48;}.empty-cart{text-align:center;padding:40px 0;}.empty-cart i{font-size:50px;color:#ddd;margin-bottom:15px;}.empty-cart p{margin-bottom:20px;color:#666;}.continue-shopping{display:inline-block;margin:20px 0;color:#25995C;text-decoration:none;}.continue-shopping i{margin-left:5px;}.recommended-products{margin-top:30px;}.recommended-title{margin-bottom:15px;font-size:18px;color:#333;}.products-grid{display:grid;grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));gap:15px;}.product-card{background-color:white;border-radius:8px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,0.05);position:relative;}.product-card a{text-decoration:none;}.product-image{height:150px;}.product-image img{width:100%;height:100%;object-fit:cover;}.cashback-tag,.points-tag{position:absolute;top:8px;padding:3px 6px;border-radius:3px;font-size:11px;font-weight:bold;z-index:1;}.cashback-tag{right:8px;background-color:#25995C;color:white;}.points-tag{left:8px;background-color:#FFD700;color:#333;}.product-info{padding:12px;}.product-info h3{margin:0 0 8px;font-size:14px;color:#333;font-weight:bold;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}.product-meta{display:flex;justify-content:space-between;align-items:flex-start;margin-top:8px;}.price-market{flex:1;}.price{font-weight:bold;color:#25995C;margin:0;}.state-badges{display:flex;flex-wrap:wrap;gap:4px;}.state-tag{padding:2px 6px;border-radius:3px;font-size:9px;}.new{background-color:#25995C;color:white;}.used{background-color:#6c757d;color:white;}.local{background-color:#0275d8;color:white;}.imported{background-color:#5bc0de;color:white;}.product-actions{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-top:1px solid #f3f3f3;}.market-name{font-size:12px;color:#666;max-width:50%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}.add-to-cart{background-color:#25995C;color:white;border:none;border-radius:4px;padding:5px 10px;font-size:12px;cursor:pointer;display:flex;align-items:center;}.add-to-cart i{margin-left:5px;}@media(max-width:768px){.cart-features{flex-direction:column;}.cart-feature{width:100%;}.cart-item{flex-wrap:wrap;}.cart-item-image{width:60px;height:60px;}.cart-item-details{flex:1;min-width:0;}.cart-item-actions{width:100%;flex-direction:row;align-items:center;margin-top:10px;}.cart-item-quantity{margin-bottom:0;}.products-grid{grid-template-columns:repeat(auto-fill, minmax(150px, 1fr));}}</style>
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

    <section class="cart-page">
        <div class="container">
            <div class="steps-container">
                <div class="step step-active">
                    <div class="step-number">1</div>
                    <div class="step-text">سلة التسوق</div>
                </div>
                <div class="steps-line"></div>
                <div class="step step-inactive">
                    <div class="step-number">2</div>
                    <div class="step-text">معلومات الشحن</div>
                </div>
                <div class="steps-line"></div>
                <div class="step step-inactive">
                    <div class="step-number">3</div>
                    <div class="step-text">الدفع</div>
                </div>
                <div class="steps-line"></div>
                <div class="step step-inactive">
                    <div class="step-number">4</div>
                    <div class="step-text">تأكيد الطلب</div>
                </div>
            </div>

            <div class="cart-features">
                <div class="cart-feature">
                    <i class="fas fa-truck"></i>
                    <div class="cart-feature-text">
                        <h4>توصيل سريع</h4>
                        <p>توصيل في جميع أنحاء المملكة</p>
                    </div>
                </div>
                <div class="cart-feature">
                    <i class="fas fa-shield-alt"></i>
                    <div class="cart-feature-text">
                        <h4>دفع آمن</h4>
                        <p>طرق دفع متعددة وآمنة</p>
                    </div>
                </div>
                <div class="cart-feature">
                    <i class="fas fa-sync-alt"></i>
                    <div class="cart-feature-text">
                        <h4>سياسة إرجاع مرنة</h4>
                        <p>استرجاع خلال 14 يوم</p>
                    </div>
                </div>
            </div>

            <div class="cart-container">
                <div class="cart-header">
                    <h2>سلة التسوق</h2>
                    <span><?= $totalItems ?> منتج</span>
                </div>
                
                <?php if (count($cartItems) > 0): ?>
                    <div class="cart-items">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="cart-item" id="cart-item-<?= $item['cart_item_id'] ?>">
                                <div class="cart-item-image">
                                    <a href="product.php?id=<?= $item['product_id'] ?>">
                                        <img src="<?= $item['image_path'] ?>" alt="<?= $item['name'] ?>" onerror="this.src='ui/placeholder.png'">
                                    </a>
                                </div>
                                <div class="cart-item-details">
                                    <div class="cart-item-title">
                                        <a href="product.php?id=<?= $item['product_id'] ?>"><?= $item['name'] ?></a>
                                    </div>
                                    <div class="cart-item-meta">
                                        <?= $item['market_name'] ?>
                                    </div>
                                    <div class="cart-item-price">
                                        <?= $item['price'] ?> ريال
                                    </div>
                                    <div class="cart-item-badges">
                                        <?php if($item['is_local']): ?>
                                            <span class="item-badge local">محلي</span>
                                        <?php else: ?>
                                            <span class="item-badge imported">مستورد</span>
                                        <?php endif; ?>
                                        
                                        <?php if($item['is_new']): ?>
                                            <span class="item-badge new">جديد</span>
                                        <?php else: ?>
                                            <span class="item-badge used">مستعمل</span>
                                        <?php endif; ?>
                                        
                                        <?php if($item['has_cashback']): ?>
                                            <span class="item-badge cashback"><?= $item['cashback_percentage'] ?>% استرداد</span>
                                        <?php endif; ?>
                                        
                                        <?php if($item['points'] > 0): ?>
                                            <span class="item-badge points"><i class="fas fa-star"></i> <?= $item['points'] ?> نقطة</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="cart-item-actions">
                                    <div class="cart-item-quantity">
                                        <div class="quantity-btn decrease" data-id="<?= $item['cart_item_id'] ?>">-</div>
                                        <input type="number" class="quantity-input" value="<?= $item['quantity'] ?>" min="1" max="99" data-id="<?= $item['cart_item_id'] ?>" readonly>
                                        <div class="quantity-btn increase" data-id="<?= $item['cart_item_id'] ?>">+</div>
                                    </div>
                                    <div class="cart-item-total">
                                        <?= number_format($item['price'] * $item['quantity'], 2) ?> ريال
                                    </div>
                                    <div class="cart-item-remove" data-id="<?= $item['cart_item_id'] ?>">
                                        <i class="fas fa-trash-alt"></i>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="cart-summary">
                        <div class="cart-summary-row">
                            <span>إجمالي المنتجات:</span>
                            <span><?= $totalItems ?> منتج</span>
                        </div>
                        <div class="cart-summary-row">
                            <span>المجموع الفرعي:</span>
                            <span><?= number_format($subtotal, 2) ?> ريال</span>
                        </div>
                        <div class="cart-summary-row">
                            <span>الشحن:</span>
                            <span>حسب الوجهة</span>
                        </div>
                        <div class="cart-summary-row cart-summary-total">
                            <span>الإجمالي:</span>
                            <span><?= number_format($subtotal, 2) ?> ريال</span>
                        </div>
                        <a href="checkout.php"><button class="checkout-btn">إتمام الطلب</button></a>
                    </div>
                <?php else: ?>
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <p>سلة التسوق فارغة</p>
                        <a href="index.php" class="btn-primary">تصفح المنتجات</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (count($cartItems) > 0): ?>
                <a href="index.php" class="continue-shopping">
                    <i class="fas fa-arrow-right"></i> مواصلة التسوق
                </a>
            <?php endif; ?>

            <?php if (count($recommendedProducts) > 0): ?>
            <div class="recommended-products">
                <div class="section-header">
                    <h2>منتجات قد تعجبك</h2>
                    <a href="products.php" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
                </div>
                <div class="products-grid">
                    <?php foreach ($recommendedProducts as $index => $product): 
                        $stateLabels = [];
                        if($product['is_new'] == 1) {
                            $stateLabels[] = '<span class="state-tag new">جديد</span>';
                        } else {
                            $stateLabels[] = '<span class="state-tag used">مستعمل</span>';
                        }
                        
                        if($product['is_local'] == 1) {
                            $stateLabels[] = '<span class="state-tag local">محلي</span>';
                        } else {
                            $stateLabels[] = '<span class="state-tag imported">مستورد</span>';
                        }
                    ?>
                    <div class="product-card">
                        <?php if($product['has_cashback']): ?>
                        <span class="cashback-tag"><?= $product['cashback_percentage'] ?>% استرداد</span>
                        <?php endif; ?>
                        <?php if($product['points'] > 0): ?>
                        <span class="points-tag"><i class="fas fa-star"></i> <?= $product['points'] ?></span>
                        <?php endif; ?>
                        <a href="product.php?id=<?= $product['product_id'] ?>">
                            <div class="product-image">
                                <img src="<?= $product['image_path'] ?>" alt="<?= $product['name'] ?>" loading="<?= $index < 2 ? 'eager' : 'lazy' ?>" onerror="this.src='ui/placeholder.png'">
                            </div>
                            <div class="product-info">
                                <h3><?= $product['name'] ?></h3>
                                <div class="product-meta">
                                    <div class="price-market">
                                        <p class="price"><?= $product['price'] ?> ريال</p>
                                    </div>
                                    <div class="state-badges">
                                        <?= implode('', $stateLabels) ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                        <div class="product-actions">
                            <span class="market-name">
                                <?= $product['market_name'] ?>
                            </span>
                            <button class="add-to-cart" data-id="<?= $product['product_id'] ?>">
                                <i class="fas fa-cart-plus"></i> طلب  
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
    
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <img src="ui/footer.png" alt="أرزاق">
                </div>
                <div class="footer-links"></div>
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
    <script>document.addEventListener('DOMContentLoaded',function(){const addToCartButtons=document.querySelectorAll('.add-to-cart');addToCartButtons.forEach(button=>{button.addEventListener('click',function(){const productId=this.getAttribute('data-id');fetch('add_to_cart.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'product_id='+productId+'&quantity=1'}).then(response=>response.json()).then(data=>{if(data.success){this.innerHTML='<i class="fas fa-check"></i> تم الطلب';setTimeout(()=>{this.innerHTML='<i class="fas fa-cart-plus"></i> طلب';},2000);const cartCountElement=document.querySelector('.cart-count');if(cartCountElement){const currentCount=parseInt(cartCountElement.textContent)||0;cartCountElement.textContent=currentCount+1;}else{const cartIcon=document.querySelector('.cart-icon');const countSpan=document.createElement('span');countSpan.className='cart-count';countSpan.textContent='1';cartIcon.appendChild(countSpan);}}else{console.error('Error adding to cart');}}).catch(error=>{console.error('Error:',error);});});});const increaseButtons=document.querySelectorAll('.quantity-btn.increase');const decreaseButtons=document.querySelectorAll('.quantity-btn.decrease');const removeButtons=document.querySelectorAll('.cart-item-remove');increaseButtons.forEach(btn=>{btn.addEventListener('click',function(){const itemId=this.getAttribute('data-id');updateCartItemQuantity(itemId,1);});});decreaseButtons.forEach(btn=>{btn.addEventListener('click',function(){const itemId=this.getAttribute('data-id');const quantityInput=document.querySelector(`.quantity-input[data-id="${itemId}"]`);const currentQuantity=parseInt(quantityInput.value);if(currentQuantity>1){updateCartItemQuantity(itemId,-1);}});});removeButtons.forEach(btn=>{btn.addEventListener('click',function(){const itemId=this.getAttribute('data-id');removeCartItem(itemId);});});function updateCartItemQuantity(cartItemId,change){fetch('update_cart.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`cart_item_id=${cartItemId}&change=${change}`}).then(response=>response.json()).then(data=>{if(data.success){const quantityInput=document.querySelector(`.quantity-input[data-id="${cartItemId}"]`);quantityInput.value=data.quantity;document.querySelector(`#cart-item-${cartItemId} .cart-item-total`).textContent=`${data.item_total} ريال`;updateCartSummary(data.total_items,data.subtotal);updateCartCount(data.total_items);}else{alert('حدث خطأ: '+data.message);}}).catch(error=>{console.error('Error:',error);});}function removeCartItem(cartItemId){if(confirm('هل أنت متأكد من رغبتك في إزالة هذا المنتج من السلة؟')){fetch('remove_from_cart.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`cart_item_id=${cartItemId}`}).then(response=>response.json()).then(data=>{if(data.success){document.querySelector(`#cart-item-${cartItemId}`).remove();updateCartSummary(data.total_items,data.subtotal);updateCartCount(data.total_items);if(data.total_items===0){window.location.reload();}}else{alert('حدث خطأ: '+data.message);}}).catch(error=>{console.error('Error:',error);});}}function updateCartSummary(totalItems,subtotal){document.querySelectorAll('.cart-header span, .cart-summary-row:first-child span:last-child').forEach(el=>{el.textContent=`${totalItems} منتج`;});document.querySelector('.cart-summary-row:nth-child(2) span:last-child').textContent=`${subtotal.toFixed(2)} ريال`;document.querySelector('.cart-summary-total span:last-child').textContent=`${subtotal.toFixed(2)} ريال`;}function updateCartCount(count){const cartCountElement=document.querySelector('.cart-count');if(cartCountElement){if(count>0){cartCountElement.textContent=count;}else{cartCountElement.remove();}}}});</script></body></html>