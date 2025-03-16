<?php
session_start();
require_once 'db.php';
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {header("Location: index.php");exit;}
$productId = $_GET['id'];
$loggedIn = isset($_SESSION['user_id']);
$username = $loggedIn ? $_SESSION['username'] : '';
$stmt = $conn->prepare("SELECT p.*, m.name as market_name, c.category_name, ROUND(AVG(r.rating_value), 1) as avg_rating, COUNT(r.rating_id) as rating_count FROM products p JOIN markets m ON p.market_id = m.market_id JOIN categories c ON p.category_id = c.category_id LEFT JOIN ratings r ON p.product_id = r.product_id WHERE p.product_id = :id AND p.is_active = 1 GROUP BY p.product_id");
$stmt->bindParam(':id', $productId);
$stmt->execute();
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) {header("Location: index.php");exit;}
$stmt = $conn->prepare("SELECT p.*, m.name as market_name FROM products p JOIN markets m ON p.market_id = m.market_id WHERE p.category_id = :category_id AND p.product_id != :product_id AND p.is_active = 1 ORDER BY RAND() LIMIT 4");
$stmt->bindParam(':category_id', $product['category_id']);
$stmt->bindParam(':product_id', $productId);
$stmt->execute();
$similarProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $conn->prepare("SELECT r.*, u.username FROM ratings r JOIN users u ON r.user_id = u.user_id WHERE r.product_id = :product_id ORDER BY r.rating_date DESC LIMIT 5");
$stmt->bindParam(':product_id', $productId);
$stmt->execute();
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
$userRated = false;
if ($loggedIn) {
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM ratings WHERE product_id = :product_id AND user_id = :user_id");
    $stmt->bindParam(':product_id', $productId);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $userRated = $result['count'] > 0;
    $stmt = $conn->prepare("SELECT SUM(ci.quantity) as count FROM cart_items ci JOIN cart c ON ci.cart_id = c.cart_id WHERE c.user_id = :user_id");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $cartCount = $result['count'] ? $result['count'] : 0;
    
    $stmt = $conn->prepare("SELECT points, user_tier FROM users WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $cartCount = 0;
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $product['name'] ?> - أرزاق</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="ui/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>.product-container{display:flex;flex-wrap:wrap;gap:20px;padding:20px 0;}.product-gallery{flex:0 0 40%;}.product-gallery img{width:100%;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.1);}.product-details{flex:1;min-width:300px;}.product-title{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;}.product-title h1{font-size:1.6rem;color:#333;max-width:70%;}.product-rating{display:flex;align-items:center;gap:8px;}.product-rating .stars{color:#FFC107;}.product-rating .count{color:#666;font-size:0.85rem;}.product-price{font-size:1.4rem;color:#25995C;font-weight:bold;margin-bottom:12px;}.product-market{display:flex;align-items:center;gap:8px;margin-bottom:12px;padding:8px;background-color:#f8f8f8;border-radius:4px;}.product-market span{color:#1c7a48;font-weight:bold;}.product-badges{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;}.product-badges .badge{padding:4px 8px;border-radius:4px;font-size:0.8rem;}.badge.local{background-color:#d1ecf1;color:#0c5460;}.badge.imported{background-color:#f8d7da;color:#721c24;}.badge.new{background-color:#e3f5e9;color:#198754;}.badge.used{background-color:#fff3cd;color:#856404;}.badge.cashback{background-color:#f39c12;color:white;}.badge.points{background-color:#ffc107;color:#333;}.product-description{margin-bottom:15px;line-height:1.5;color:#555;}.product-actions{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;}.product-actions .quantity{display:flex;align-items:center;}.product-actions .quantity input{width:55px;padding:6px;text-align:center;border:1px solid #ddd;border-radius:4px;}.product-actions button{flex:1;min-width:140px;}.product-tabs{margin:30px 0;}.tab-links{display:flex;border-bottom:1px solid #ddd;margin-bottom:15px;}.tab-link{padding:8px 15px;cursor:pointer;border-bottom:3px solid transparent;margin-right:8px;transition:all 0.3s;}.tab-link.active{border-bottom-color:#25995C;color:#25995C;font-weight:bold;}.tab-content{display:none;}.tab-content.active{display:block;}.review{border-bottom:1px solid #eee;padding:12px 0;}.review-header{display:flex;justify-content:space-between;margin-bottom:8px;}.review-user{font-weight:bold;}.review-date{color:#999;font-size:0.85rem;}.review-rating{color:#FFC107;margin-bottom:6px;}.review-text{line-height:1.4;}.review-form{background-color:#f8f8f8;padding:15px;border-radius:8px;margin-top:15px;}.review-form h3{margin-bottom:12px;color:#333;font-size:1.1rem;}.form-group{margin-bottom:12px;}.form-group label{display:block;margin-bottom:4px;font-weight:bold;}.form-group textarea{width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;font-family:'Cairo',sans-serif;height:80px;resize:vertical;}.star-rating{display:flex;flex-direction:row-reverse;justify-content:flex-end;}.star-rating input{display:none;}.star-rating label{cursor:pointer;color:#ccc;font-size:1.3rem;padding:0 3px;}.star-rating input:checked ~ label{color:#FFC107;}.star-rating label:hover,.star-rating label:hover ~ label{color:#FFC107;}.products-grid{margin-top:15px;}.tab-section{margin-bottom:20px;}.report-btn{color:#dc3545;text-decoration:underline;cursor:pointer;font-size:0.85rem;}.report-form{display:none;margin-top:12px;}.similar-products{margin-bottom:40px;}.similar-products h2{margin-bottom:15px;font-size:1.4rem;}footer{margin-top:40px;}@media(max-width:768px){.product-container{gap:15px;padding:15px 0;}.product-gallery,.product-details{flex:0 0 100%;}.product-title{flex-direction:column;gap:8px;}.product-title h1{font-size:1.4rem;max-width:100%;}.product-price{font-size:1.3rem;}.product-actions{flex-direction:column;}.tab-links{overflow-x:auto;white-space:nowrap;padding-bottom:3px;}.tab-link{padding:6px 12px;font-size:0.9rem;}.review-form{padding:12px;}.form-group textarea{height:70px;}}</style>
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
                <li><a href="products.php" class="active"><i class="fas fa-tag"></i> المنتجات</a></li>
                <li><a href="wholesale.php"><i class="fas fa-box"></i> الجملة</a></li>
                <li><a href="markets.php"><i class="fas fa-store"></i> المتاجر</a></li>
                <li><a href="workers/select.php"><i class="fas fa-concierge-bell"></i> الخدمات</a></li>
            </ul>
        </div>
    </div>
    
    <div class="container">
        <div class="product-container">
            <div class="product-gallery">
                <img src="<?= $product['image_path'] ?>" alt="<?= $product['name'] ?>">
            </div>
            <div class="product-details">
                <div class="product-title">
                    <h1><?= $product['name'] ?></h1>
                    <div class="product-rating">
                        <div class="stars">
                            <?php 
                            $rating = $product['avg_rating'] ? $product['avg_rating'] : 0;
                            for($i = 1; $i <= 5; $i++) {
                                if($i <= $rating) {
                                    echo '<i class="fas fa-star"></i>';
                                } elseif($i - 0.5 <= $rating) {
                                    echo '<i class="fas fa-star-half-alt"></i>';
                                } else {
                                    echo '<i class="far fa-star"></i>';
                                }
                            }
                            ?>
                        </div>
                        <span class="count">(<?= $product['rating_count'] ?> تقييم)</span>
                    </div>
                </div>
                
                <div class="product-price">
                    <?= $product['price'] ?> ريال
                </div>
                
                <div class="product-market">
                    <i class="fas fa-store"></i> 
                    <span><?= $product['market_name'] ?></span>
                </div>
                
                <div class="product-badges">
                    <?php if($product['is_local']): ?>
                    <span class="badge local">محلي</span>
                    <?php else: ?>
                    <span class="badge imported">مستورد</span>
                    <?php endif; ?>
                    
                    <?php if($product['is_new']): ?>
                    <span class="badge new">جديد</span>
                    <?php else: ?>
                    <span class="badge used">مستعمل</span>
                    <?php endif; ?>
                    <?php if($product['has_cashback']): ?>
                    <span class="badge cashback"><?= $product['cashback_percentage'] ?>% استرداد</span>
                    <?php endif; ?>
                    
                    <?php if($product['points'] > 0): ?>
                    <span class="badge points"><i class="fas fa-star"></i> <?= $product['points'] ?> نقطة</span>
                    <?php endif; ?>
                </div>
                
                <div class="product-description">
                    <?= $product['description'] ?>
                </div>
                
                <div class="product-actions">
                    <div class="quantity">
                        <label for="quantity">الكمية:</label>
                        <input type="number" id="quantity" value="1" min="1" max="10">
                    </div>
                    <button class="btn-primary add-to-cart" data-id="<?= $product['product_id'] ?>">
                        <i class="fas fa-cart-plus"></i> أضف إلى السلة
                    </button>
                </div>
                
                <div class="product-tabs">
                    <div class="tab-links">
                        <div class="tab-link active" data-tab="description">الوصف</div>
                        <div class="tab-link" data-tab="reviews">التقييمات (<?= $product['rating_count'] ?>)</div>
                        <div class="tab-link" data-tab="shipping">الشحن والتوصيل</div>
                    </div>
                    
                    <div class="tab-content active" id="description">
                        <p><?= $product['description'] ?></p>
                    </div>
                    
                    <div class="tab-content" id="reviews">
                        <?php if(count($reviews) > 0): ?>
                            <?php foreach($reviews as $review): ?>
                            <div class="review">
                                <div class="review-header">
                                    <span class="review-user"><?= $review['username'] ?></span>
                                    <span class="review-date"><?= date('d/m/Y', strtotime($review['rating_date'])) ?></span>
                                </div>
                                <div class="review-rating">
                                    <?php 
                                    for($i = 1; $i <= 5; $i++) {
                                        if($i <= $review['rating_value']) {
                                            echo '<i class="fas fa-star"></i>';
                                        } else {
                                            echo '<i class="far fa-star"></i>';
                                        }
                                    }
                                    ?>
                                </div>
                                <div class="review-text"><?= $review['comment'] ?></div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>لا توجد تقييمات لهذا المنتج بعد.</p>
                        <?php endif; ?>
                        
                        <?php if($loggedIn && !$userRated): ?>
                        <div class="review-form">
                            <h3>أضف تقييمك</h3>
                            <form id="review-form" method="POST" action="add_review.php">
                                <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                                
                                <div class="form-group">
                                    <label>التقييم:</label>
                                    <div class="star-rating">
                                        <input type="radio" id="star5" name="rating" value="5" /><label for="star5">★</label>
                                        <input type="radio" id="star4" name="rating" value="4" /><label for="star4">★</label>
                                        <input type="radio" id="star3" name="rating" value="3" /><label for="star3">★</label>
                                        <input type="radio" id="star2" name="rating" value="2" /><label for="star2">★</label>
                                        <input type="radio" id="star1" name="rating" value="1" /><label for="star1">★</label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="comment">التعليق:</label>
                                    <textarea id="comment" name="comment" required></textarea>
                                </div>
                                
                                <button type="submit" class="btn-primary">إرسال التقييم</button>
                            </form>
                        </div>
                        <?php elseif(!$loggedIn): ?>
                        <p><a href="login.php?redirect=product.php?id=<?= $product['product_id'] ?>">سجل الدخول</a> لإضافة تقييم</p>
                        <?php endif; ?>
                        
                        <?php if($loggedIn): ?>
                        <div class="report-section">
                            <p class="report-btn" id="report-toggle">الإبلاغ عن هذا المنتج</p>
                            <div class="report-form" id="report-form">
                                <form method="POST" action="report_product.php">
                                    <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                                    <div class="form-group">
                                        <label for="report_text">سبب الإبلاغ:</label>
                                        <textarea id="report_text" name="report_text" required></textarea>
                                    </div>
                                    <button type="submit" class="btn-primary">إرسال البلاغ</button>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="tab-content" id="shipping">
                        <div class="tab-section">
                            <h3>معلومات الشحن</h3>
                            <p>يتم شحن المنتج خلال 2-5 أيام عمل.</p>
                            <p>رسوم الشحن: حسب المنطقة</p>
                        </div>
                        
                        <div class="tab-section">
                            <h3>سياسة الإرجاع</h3>
                            <p>يمكن إرجاع المنتج خلال 7 أيام من استلامه إذا كان به عيب مصنعي.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="similar-products">
            <h2>منتجات مشابهة</h2>
            <div class="products-grid">
                <?php foreach($similarProducts as $similar): 
                    $stateLabels = [];
                    if($similar['is_new'] == 1) {
                        $stateLabels[] = '<span class="state-tag new">جديد</span>';
                    } else {
                        $stateLabels[] = '<span class="state-tag used">مستعمل</span>';
                    }
                    
                    if($similar['is_local'] == 1) {
                        $stateLabels[] = '<span class="state-tag local">محلي</span>';
                    } else {
                        $stateLabels[] = '<span class="state-tag imported">مستورد</span>';
                    }
                ?>
                <div class="product-card">
                    <?php if($similar['has_cashback']): ?>
                    <span class="cashback-tag"><?= $similar['cashback_percentage'] ?>% استرداد</span>
                    <?php endif; ?>
                    <?php if($similar['points'] > 0): ?>
                    <span class="points-tag"><i class="fas fa-star"></i> <?= $similar['points'] ?></span>
                    <?php endif; ?>
                    <a href="product.php?id=<?= $similar['product_id'] ?>">
                        <div class="product-image">
                            <img src="<?= $similar['image_path'] ?>" alt="<?= $similar['name'] ?>">
                        </div>
                        <div class="product-info">
                            <h3><?= $similar['name'] ?></h3>
                            <div class="product-meta">
                                <div class="price-market">
                                    <p class="price"><?= $similar['price'] ?> ريال</p>
                                </div>
                                <div class="state-badges">
                                    <?= implode('', $stateLabels) ?>
                                </div>
                            </div>
                        </div>
                    </a>
                    <div class="product-actions">
                        <span class="market-name">
                            <?= $similar['market_name'] ?>
                        </span>
                        <button class="add-to-cart" data-id="<?= $similar['product_id'] ?>">
                            <i class="fas fa-cart-plus"></i> طلب
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
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
    <script>document.addEventListener('DOMContentLoaded',function(){const addToCartButtons=document.querySelectorAll('.add-to-cart');addToCartButtons.forEach(button=>{button.addEventListener('click',function(){const productId=this.getAttribute('data-id');let quantity=1;if(productId==<?= $product['product_id'] ?>){const quantityInput=document.getElementById('quantity');quantity=quantityInput?parseInt(quantityInput.value):1;}<?php if($loggedIn): ?>fetch('add_to_cart.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'product_id='+productId+'&quantity='+quantity}).then(response=>response.json()).then(data=>{if(data.success){this.innerHTML='<i class="fas fa-check"></i> تم الإضافة';setTimeout(()=>{this.innerHTML='<i class="fas fa-cart-plus"></i> '+(productId==<?= $product['product_id'] ?>?'أضف إلى السلة':'طلب');},2000);const cartCountElement=document.querySelector('.cart-count');if(cartCountElement){const currentCount=parseInt(cartCountElement.textContent)||0;cartCountElement.textContent=currentCount+quantity;}else{const cartIcon=document.querySelector('.cart-icon');const countSpan=document.createElement('span');countSpan.className='cart-count';countSpan.textContent=quantity;cartIcon.appendChild(countSpan);}}else{console.error('Error adding to cart');}}).catch(error=>{console.error('Error:',error);});<?php else: ?>window.location.href='login.php?redirect=product.php?id=<?= $product['product_id'] ?>';<?php endif; ?>});});const tabLinks=document.querySelectorAll('.tab-link');const tabContents=document.querySelectorAll('.tab-content');tabLinks.forEach(link=>{link.addEventListener('click',function(){const tabId=this.getAttribute('data-tab');tabLinks.forEach(l=>l.classList.remove('active'));tabContents.forEach(c=>c.classList.remove('active'));this.classList.add('active');document.getElementById(tabId).classList.add('active');});});const reportToggle=document.getElementById('report-toggle');const reportForm=document.getElementById('report-form');if(reportToggle&&reportForm){reportToggle.addEventListener('click',function(){reportForm.style.display=reportForm.style.display==='block'?'none':'block';});}});</script>
</body>
</html>