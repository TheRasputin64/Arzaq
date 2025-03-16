<?php
session_start();
require_once 'db.php';
$loggedIn = isset($_SESSION['user_id']);
$username = $loggedIn ? $_SESSION['username'] : '';
$categoryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($categoryId <= 0) {
    header('Location: categories.php');
    exit;
}
$stmt = $conn->prepare("SELECT * FROM categories WHERE category_id = :category_id");
$stmt->bindParam(':category_id', $categoryId);
$stmt->execute();
$category = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$category) {
    header('Location: categories.php');
    exit;
}
$stmt = $conn->prepare("SELECT * FROM categories WHERE parent_category_id = :parent_id");
$stmt->bindParam(':parent_id', $categoryId);
$stmt->execute();
$subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $conn->prepare("SELECT p.*, m.name as market_name FROM products p 
                     JOIN markets m ON p.market_id = m.market_id 
                     WHERE p.category_id = :category_id AND p.is_active = 1
                     ORDER BY p.has_cashback DESC, p.product_id DESC");
$stmt->bindParam(':category_id', $categoryId);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$cartCount = 0;
if ($loggedIn) {
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT SUM(ci.quantity) as count FROM cart_items ci 
                        JOIN cart c ON ci.cart_id = c.cart_id 
                        WHERE c.user_id = :user_id");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $cartCount = $result['count'] ? $result['count'] : 0;
    
    $stmt = $conn->prepare("SELECT points, user_tier FROM users WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
}
$productsPerPage = 12;
$totalProducts = count($products);
$totalPages = ceil($totalProducts / $productsPerPage);
$currentPage = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $totalPages)) : 1;
$startIndex = ($currentPage - 1) * $productsPerPage;
$paginatedProducts = array_slice($products, $startIndex, $productsPerPage);
$breadcrumb = [];
$currentCat = $category;
$breadcrumb[] = $currentCat;
while ($currentCat['parent_category_id']) {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE category_id = :parent_id");
    $stmt->bindParam(':parent_id', $currentCat['parent_category_id']);
    $stmt->execute();
    $parentCat = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($parentCat) {
        $breadcrumb[] = $parentCat;
        $currentCat = $parentCat;
    } else {
        break;
    }
}
$breadcrumb = array_reverse($breadcrumb);
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $category['category_name'] ?> - أرزاق</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="ui/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>.category-header{position:relative;overflow:hidden;height:180px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;justify-content:center;}.category-header-bg{position:absolute;top:0;left:0;width:100%;height:100%;z-index:1;background-size:cover;background-position:center;filter:blur(2px);opacity:0.7;}.category-overlay{position:absolute;top:0;left:0;width:100%;height:100%;background:linear-gradient(to bottom,rgba(37,153,92,0.5),rgba(37,153,92,0.8));z-index:2;}.category-info{position:relative;z-index:3;text-align:center;color:white;padding:20px;}.breadcrumb{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background-color:white;padding:12px;border-radius:8px;margin-bottom:15px;box-shadow:0 2px 5px rgba(0,0,0,0.05);}.breadcrumb-item{display:flex;align-items:center;}.breadcrumb-item a{color:#666;transition:color 0.3s;}.breadcrumb-item a:hover{color:#25995C;}.breadcrumb-separator{color:#ccc;}.category-title{margin-bottom:10px;font-size:1.8rem;text-shadow:1px 1px 3px rgba(0,0,0,0.3);}.subcategories{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px;}.subcategory-tag{background-color:white;color:#25995C;border:1px solid #25995C;border-radius:20px;padding:5px 15px;font-size:0.9rem;transition:all 0.3s;}.subcategory-tag:hover{background-color:#25995C;color:white;}.content-container{display:flex;gap:20px;}.sidebar{width:260px;flex-shrink:0;}.main-content{flex:1;}.filters{background-color:white;border-radius:8px;padding:15px;margin-bottom:20px;box-shadow:0 2px 5px rgba(0,0,0,0.05);}.filter-group{margin-bottom:15px;}.filter-label{font-weight:bold;color:#333;font-size:0.95rem;margin-bottom:8px;}.filter-options{display:flex;flex-wrap:wrap;gap:8px;}.filter-option{cursor:pointer;background-color:#f0f0f0;border-radius:4px;padding:6px 12px;font-size:0.85rem;transition:all 0.2s;}.filter-option:hover,.filter-option.active{background-color:#25995C;color:white;}.pagination{display:flex;justify-content:center;gap:5px;margin:25px 0;}.pagination-link{display:flex;align-items:center;justify-content:center;width:35px;height:35px;border-radius:50%;background-color:white;color:#333;box-shadow:0 2px 5px rgba(0,0,0,0.1);transition:all 0.3s;}.pagination-link:hover,.pagination-link.active{background-color:#25995C;color:white;}.empty-state{text-align:center;padding:40px 20px;background-color:white;border-radius:8px;margin:20px 0;}.empty-state i{font-size:3rem;color:#ccc;margin-bottom:15px;}.empty-state h3{color:#666;margin-bottom:10px;}.empty-state p{color:#999;max-width:400px;margin:0 auto;}.products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:15px;}.sort-dropdown{margin-bottom:15px;text-align:left;}.sort-btn{display:inline-flex;align-items:center;gap:5px;background-color:white;border-radius:4px;padding:8px 15px;color:#666;font-size:0.9rem;box-shadow:0 1px 3px rgba(0,0,0,0.1);cursor:pointer;transition:all 0.3s;}.sort-btn:hover{color:#25995C;}@media(max-width:992px){.content-container{flex-direction:column;}.sidebar{width:100%;}}@media(max-width:768px){.products-grid{grid-template-columns:repeat(auto-fill,minmax(180px,1fr));}}@media(max-width:576px){.products-grid{grid-template-columns:repeat(auto-fill,minmax(140px,1fr));}}</style>
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
                <li><a href="categories.php" class="active"><i class="fas fa-th-large"></i> الأقسام</a></li>
                <li><a href="products.php"><i class="fas fa-tag"></i> المنتجات</a></li>
                <li><a href="wholesale.php"><i class="fas fa-box"></i> الجملة</a></li>
                <li><a href="markets.php"><i class="fas fa-store"></i> المتاجر</a></li>
                <li><a href="workers/select.php"><i class="fas fa-concierge-bell"></i> الخدمات</a></li>
            </ul>
        </div>
    </div>
    
    <div class="container" style="padding-top: 20px; padding-bottom: 30px;">
        <div class="breadcrumb">
            <div class="breadcrumb-item">
                <a href="index.php"><i class="fas fa-home"></i></a>
            </div>
            <div class="breadcrumb-separator"><i class="fas fa-angle-left"></i></div>
            <div class="breadcrumb-item">
                <a href="categories.php">الأقسام</a>
            </div>
            
            <?php foreach($breadcrumb as $index => $item): ?>
                <div class="breadcrumb-separator"><i class="fas fa-angle-left"></i></div>
                <div class="breadcrumb-item">
                    <?php if($index === count($breadcrumb) - 1): ?>
                        <span><?= $item['category_name'] ?></span>
                    <?php else: ?>
                        <a href="category.php?id=<?= $item['category_id'] ?>"><?= $item['category_name'] ?></a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="category-header">
            <div class="category-header-bg" style="background-image: url('<?= $category['image_path'] ?>');"></div>
            <div class="category-overlay"></div>
            <div class="category-info">
                <h1 class="category-title"><?= $category['category_name'] ?></h1>
                <?php if(count($subcategories) > 0): ?>
                <div class="subcategories">
                    <?php foreach($subcategories as $subcat): ?>
                    <a href="category.php?id=<?= $subcat['category_id'] ?>" class="subcategory-tag">
                        <?= $subcat['category_name'] ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="content-container">
            <div class="sidebar">
                <div class="filters">
                    <div class="filter-group">
                        <div class="filter-label">الحالة</div>
                        <div class="filter-options">
                            <div class="filter-option active">الكل</div>
                            <div class="filter-option">جديد</div>
                            <div class="filter-option">مستعمل</div>
                        </div>
                        </div>
                    
                    <div class="filter-group">
                        <div class="filter-label">السعر</div>
                        <div class="filter-options">
                            <div class="filter-option active">الكل</div>
                            <div class="filter-option">أقل من 100 ريال</div>
                            <div class="filter-option">أكثر من 100 ريال</div>
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <div class="filter-label">العروض</div>
                        <div class="filter-options">
                            <div class="filter-option active">الكل</div>
                            <div class="filter-option">استرداد نقدي</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="main-content">
                <?php if(count($paginatedProducts) > 0): ?>
                <div class="sort-dropdown">
                    <div class="sort-btn">
                        <i class="fas fa-sort"></i> ترتيب حسب
                    </div>
                </div>
                
                <div class="products-container">
                    <div class="products-grid">
                        <?php foreach($paginatedProducts as $product): 
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
                                    <img src="<?= $product['image_path'] ?>" alt="<?= $product['name'] ?>">
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
                
                <?php if($totalPages > 1): ?>
                <div class="pagination">
                    <?php if($currentPage > 1): ?>
                    <a href="?id=<?= $categoryId ?>&page=<?= $currentPage - 1 ?>" class="pagination-link">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $startPage + 4);
                    
                    if($endPage - $startPage < 4) {
                        $startPage = max(1, $endPage - 4);
                    }
                    
                    for($i = $startPage; $i <= $endPage; $i++):
                    ?>
                    <a href="?id=<?= $categoryId ?>&page=<?= $i ?>" class="pagination-link <?= $i === $currentPage ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if($currentPage < $totalPages): ?>
                    <a href="?id=<?= $categoryId ?>&page=<?= $currentPage + 1 ?>" class="pagination-link">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>لا توجد منتجات</h3>
                    <p>لم يتم العثور على منتجات في هذا القسم حالياً، يرجى التحقق لاحقاً أو استعراض أقسام أخرى.</p>
                </div>
                <?php endif; ?>
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
    
    <script>document.addEventListener('DOMContentLoaded',function(){const addToCartButtons=document.querySelectorAll('.add-to-cart');addToCartButtons.forEach(button=>{button.addEventListener('click',function(){const productId=this.getAttribute('data-id');<?php if($loggedIn): ?>fetch('add_to_cart.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'product_id='+productId+'&quantity=1'}).then(response=>response.json()).then(data=>{if(data.success){this.innerHTML='<i class="fas fa-check"></i> تم الطلب';setTimeout(()=>{this.innerHTML='<i class="fas fa-cart-plus"></i> طلب';},2000);const cartCountElement=document.querySelector('.cart-count');if(cartCountElement){const currentCount=parseInt(cartCountElement.textContent)||0;cartCountElement.textContent=currentCount+1;}else{const cartIcon=document.querySelector('.cart-icon');const countSpan=document.createElement('span');countSpan.className='cart-count';countSpan.textContent='1';cartIcon.appendChild(countSpan);}}else{console.error('Error adding to cart');}}).catch(error=>{console.error('Error:',error);});<?php else: ?>window.location.href='login.php?redirect=category.php?id=<?= $categoryId ?>';<?php endif; ?>});});const filterOptions=document.querySelectorAll('.filter-option');filterOptions.forEach(option=>{option.addEventListener('click',function(){const parent=this.parentNode;parent.querySelectorAll('.filter-option').forEach(opt=>opt.classList.remove('active'));this.classList.add('active');});});});</script>
</body>
</html>