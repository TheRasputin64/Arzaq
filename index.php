<?php
session_start();
require_once 'db.php';
$loggedIn = isset($_SESSION['user_id']);
$username = $loggedIn ? $_SESSION['username'] : '';
$stmt = $conn->prepare("SELECT * FROM categories WHERE is_main = 1 LIMIT 8");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
$cartCount = 0;
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
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>أرزاق - موقع التسوق الإلكتروني</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="ui/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                <li><a href="index.php" class="active"><i class="fas fa-home"></i> الرئيسية</a></li>
                <li><a href="categories.php"><i class="fas fa-th-large"></i> الأقسام</a></li>
                <li><a href="products.php"><i class="fas fa-tag"></i> المنتجات</a></li>
                <li><a href="wholesale.php"><i class="fas fa-box"></i> الجملة</a></li>
                <li><a href="markets.php"><i class="fas fa-store"></i> المتاجر</a></li>
                <li><a href="workers/select.php"><i class="fas fa-concierge-bell"></i> الخدمات</a></li>
            </ul>
        </div>
    </div>
    
    <section class="hero">
        <div class="container">
            <h1>تسوق بسهولة وأمان مع أرزاق</h1>
            <p>أكبر سوق إلكتروني في المملكة العربية السعودية</p>
            <div class="hero-buttons">
                <a href="market.php" class="btn-primary">بيع معنا</a>
                <a href="offers.php" class="btn-secondary">تصفح العروض</a>
            </div>
        </div>
    </section>
    
    <section id="categories-section" class="categories">
        <div class="container">
            <div class="section-header">
                <h2>تسوق حسب الأقسام</h2>
                <a href="categories.php" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
            </div>
            <div class="categories-grid">
                <?php 
                $categoryCount = count($categories);
                $halfCount = ceil($categoryCount / 2);
                foreach($categories as $index => $category): 
                ?>
                <a href="category.php?id=<?= $category['category_id'] ?>" class="category-card">
                    <div class="category-image">
                        <img src="<?= $category['image_path'] ?>" alt="<?= $category['category_name'] ?>" loading="<?= $index < $halfCount ? 'eager' : 'lazy' ?>" onerror="this.src='ui/placeholder.png'">
                    </div>
                    <h3><?= $category['category_name'] ?></h3>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    
    <section class="featured-products">
        <div class="container">
            <div class="section-header">
                <h2>منتجات مميزة</h2>
                <a href="products.php" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
            </div>
            <div class="products-grid">
                <?php
                $stmt = $conn->prepare("SELECT p.*, m.name as market_name FROM products p JOIN markets m ON p.market_id = m.market_id WHERE p.is_active = 1 ORDER BY RAND() LIMIT 10");
                $stmt->execute();
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach($products as $index => $product): 
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
                            <img src="<?= $product['image_path'] ?>" alt="<?= $product['name'] ?>" loading="<?= $index < 5 ? 'eager' : 'lazy' ?>" onerror="this.src='ui/placeholder.png'">
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
    </section>
    
    <section id="partners-section" class="partners">
        <div class="container">
            <h2>شركاؤنا</h2>
            <div class="partners-slider">
                <div class="partners-track">
                    <?php
                    for($i = 1; $i <= 26; $i++):
                    ?>
                    <div class="partner">
                        <img src="partners/<?= $i ?>.png" alt="شريك <?= $i ?>" loading="lazy" onerror="this.src='ui/placeholder.png'">
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </section>
    
    <section id="about-section" class="about">
        <div class="container">
            <h2>عن أرزاق</h2>
            <p>أرزاق هي منصة تسوق إلكتروني سعودية تهدف إلى ربط التجار بالمستهلكين بطريقة سهلة وآمنة. نسعى دائماً لتقديم أفضل تجربة تسوق للمستخدمين وفرص نمو للتجار.</p>
        </div>
    </section>
    
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <img src="ui/footer.png" alt="أرزاق">
                </div>                <div class="footer-links"></div>
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
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');
            <?php if($loggedIn): ?>
            fetch('add_to_cart.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'product_id=' + productId + '&quantity=1'}).then(response => response.json())
            .then(data => {
                if(data.success) {
                    this.innerHTML = '<i class="fas fa-check"></i> تم الطلب';
                    setTimeout(() => {
                        this.innerHTML = '<i class="fas fa-cart-plus"></i> طلب';
                    }, 2000);
                    
                    const cartCountElement = document.querySelector('.cart-count');
                    if(cartCountElement) {
                        const currentCount = parseInt(cartCountElement.textContent) || 0;
                        cartCountElement.textContent = currentCount + 1;
                    } else {
                        const cartIcon = document.querySelector('.cart-icon');
                        const countSpan = document.createElement('span');
                        countSpan.className = 'cart-count';
                        countSpan.textContent = '1';
                        cartIcon.appendChild(countSpan);
                    }
                } else {
                    console.error('Error adding to cart');
                }
            }).catch(error => {
                console.error('Error:', error);
            });
            <?php else: ?>
            window.location.href = 'login.php?redirect=index.php';
            <?php endif; ?>
        });
    });
});
    </script>
    </body>
    </html>