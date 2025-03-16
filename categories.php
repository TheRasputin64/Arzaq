<?php
session_start();
require_once 'db.php';

$loggedIn = isset($_SESSION['user_id']);
$username = $loggedIn ? $_SESSION['username'] : '';

// Get all main categories
$stmt = $conn->prepare("SELECT * FROM categories WHERE is_main = 1");
$stmt->execute();
$mainCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get cart count
$cartCount = 0;
if ($loggedIn) {
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT SUM(ci.quantity) as count FROM cart_items ci JOIN cart c ON ci.cart_id = c.cart_id WHERE c.user_id = :user_id");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $cartCount = $result['count'] ? $result['count'] : 0;
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الأقسام - أرزاق</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="ui/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>.page-header{background:linear-gradient(135deg,#25995C,#1c7a48);padding:40px 0;text-align:center;color:white;margin-bottom:30px;border-radius:8px;position:relative;overflow:hidden;}.page-header::before{content:'';position:absolute;top:0;left:0;right:0;bottom:0;background:linear-gradient(45deg,rgba(255,255,255,0.1) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.1) 50%,rgba(255,255,255,0.1) 75%,transparent 75%);background-size:20px 20px;opacity:0.2;}.page-header h1{margin:0;font-size:2.5rem;text-shadow:0 2px 4px rgba(0,0,0,0.2);position:relative;}.categories-container{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:20px;margin-bottom:40px;}.category-box{background-color:white;border-radius:10px;overflow:hidden;transition:all 0.3s ease;box-shadow:0 3px 10px rgba(0,0,0,0.08);height:100%;display:flex;flex-direction:column;}.category-box:hover{transform:translateY(-5px);box-shadow:0 10px 20px rgba(0,0,0,0.12);}.category-image{height:180px;overflow:hidden;position:relative;}.category-image img{width:100%;height:100%;object-fit:cover;transition:transform 0.5s ease;}.category-box:hover .category-image img{transform:scale(1.05);}.category-content{padding:15px;display:flex;flex-direction:column;flex-grow:1;}.category-title{font-size:1.2rem;font-weight:bold;margin-bottom:8px;color:#333;}.category-details{display:flex;align-items:center;justify-content:space-between;margin-top:auto;}.subcategories-count{background-color:#f0f9f4;color:#25995C;padding:4px 10px;border-radius:20px;font-size:0.85rem;}.view-btn{color:#25995C;font-weight:bold;display:flex;align-items:center;text-decoration:none;gap:5px;}.view-btn i{transition:transform 0.2s ease;}.category-box:hover .view-btn i{transform:translateX(-5px);}.section-intro{text-align:center;max-width:800px;margin:0 auto 40px;}.section-intro p{color:#666;line-height:1.6;}.search-categories{margin-bottom:30px;background-color:white;padding:20px;border-radius:10px;box-shadow:0 3px 10px rgba(0,0,0,0.08);}.search-categories form{display:flex;gap:10px;}.search-categories input{flex:1;padding:12px 15px;border:1px solid #ddd;border-radius:8px;font-family:'Cairo',sans-serif;}.search-categories button{background-color:#25995C;color:white;border:none;padding:0 20px;border-radius:8px;cursor:pointer;transition:background-color 0.3s;}.search-categories button:hover{background-color:#1c7a48;}@media (max-width:768px){.categories-container{grid-template-columns:repeat(auto-fill,minmax(200px,1fr));}.page-header{padding:30px 0;}.page-header h1{font-size:2rem;}}@media (max-width:576px){.categories-container{grid-template-columns:1fr;}.category-image{height:160px;}.search-categories form{flex-direction:column;}.search-categories button{width:100%;padding:10px;}}</style>
</head>
<body>
    <div class="top-bar">
        <div class="container">
            <div class="top-links">
                <a href="index.php#about-section">عن أرزاق</a>
                <a href="index.php#partners-section">شركاؤنا</a>
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
                <div class="user-dropdown">
                    <a href="#" class="user-btn"><i class="fas fa-user"></i> <?= $username ?></a>
                    <div class="dropdown-content">
                        <a href="orders.php"><i class="fas fa-shopping-bag"></i> طلباتي</a>
                        <a href="cart.php"><i class="fas fa-shopping-cart"></i> السلة</a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
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
                <li><a href="products.php" class="active"><i class="fas fa-th-large"></i> الأقسام</a></li>
                <li><a href="offers.php"><i class="fas fa-tag"></i> العروض</a></li>
                <li><a href="wholesale.php"><i class="fas fa-box"></i> الجملة</a></li>
                <li><a href="markets.php"><i class="fas fa-store"></i> المتاجر</a></li>
                <li><a href="services.php"><i class="fas fa-concierge-bell"></i> الخدمات</a></li>
            </ul>
        </div>
    </div>
    
    <div class="container">
        <div class="breadcrumb">
            <a href="index.php">الرئيسية</a>
            <i class="fas fa-angle-left"></i>
            <span>الأقسام</span>
        </div>
        
        <div class="page-header">
            <h1>تصفح جميع الأقسام</h1>
        </div>
        
        <div class="section-intro">
            <p>اكتشف مجموعة واسعة من الأقسام المتنوعة لتلبية جميع احتياجاتك التسوقية في مكان واحد. نقدم لك أفضل المنتجات من أشهر العلامات التجارية وبأسعار تنافسية.</p>
        </div>
        
        <div class="search-categories">
            <form action="search.php" method="GET">
                <input type="text" name="q" placeholder="ابحث عن قسم محدد...">
                <button type="submit"><i class="fas fa-search"></i> بحث</button>
            </form>
        </div>
        
        <div class="categories-container">
            <?php foreach($mainCategories as $category): 
                // Get subcategories count
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM categories WHERE parent_category_id = :category_id");
                $stmt->bindParam(':category_id', $category['category_id']);
                $stmt->execute();
                $subCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                // Get products count
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = :category_id AND is_active = 1");
                $stmt->bindParam(':category_id', $category['category_id']);
                $stmt->execute();
                $productsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            ?>
            <a href="category.php?id=<?= $category['category_id'] ?>" class="category-box">
                <div class="category-image">
                    <img src="<?= $category['image_path'] ?>" alt="<?= $category['category_name'] ?>">
                </div>
                <div class="category-content">
                    <h3 class="category-title"><?= $category['category_name'] ?></h3>
                    <p><?= $productsCount ?> منتج متوفر</p>
                    <div class="category-details">
                        <?php if($subCount > 0): ?>
                        <span class="subcategories-count"><?= $subCount ?> قسم فرعي</span>
                        <?php else: ?>
                        <span></span>
                        <?php endif; ?>
                        <span class="view-btn">تصفح الآن <i class="fas fa-arrow-left"></i></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
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
    <script>document.addEventListener('DOMContentLoaded',function(){const categoryBoxes=document.querySelectorAll('.category-box');categoryBoxes.forEach(box=>{box.addEventListener('mouseenter',function(){this.querySelector('.view-btn i').style.transform='translateX(-5px)';});box.addEventListener('mouseleave',function(){this.querySelector('.view-btn i').style.transform='translateX(0)';});});});</script>
</body>
</html>