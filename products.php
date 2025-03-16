<?php
session_start();
require_once 'db.php';
$loggedIn = isset($_SESSION['user_id']);
$username = $loggedIn ? $_SESSION['username'] : '';

// Get all active categories
$stmt = $conn->prepare("SELECT * FROM categories WHERE is_main = 1");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Get filter parameters
$categoryId = isset($_GET['category']) ? intval($_GET['category']) : null;
$minPrice = isset($_GET['min_price']) ? floatval($_GET['min_price']) : null;
$maxPrice = isset($_GET['max_price']) ? floatval($_GET['max_price']) : null;
$isLocal = isset($_GET['local']) ? intval($_GET['local']) : null;
$isNew = isset($_GET['new']) ? intval($_GET['new']) : null;
$hasCashback = isset($_GET['cashback']) ? intval($_GET['cashback']) : null;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query
$query = "SELECT p.*, m.name as market_name FROM products p JOIN markets m ON p.market_id = m.market_id WHERE p.is_active = 1";
$params = [];

if ($categoryId) {
    $query .= " AND p.category_id = :category_id";
    $params[':category_id'] = $categoryId;
}

if ($minPrice !== null) {
    $query .= " AND p.price >= :min_price";
    $params[':min_price'] = $minPrice;
}

if ($maxPrice !== null) {
    $query .= " AND p.price <= :max_price";
    $params[':max_price'] = $maxPrice;
}

if ($isLocal !== null) {
    $query .= " AND p.is_local = :is_local";
    $params[':is_local'] = $isLocal;
}

if ($isNew !== null) {
    $query .= " AND p.is_new = :is_new";
    $params[':is_new'] = $isNew;
}

if ($hasCashback !== null) {
    $query .= " AND p.has_cashback = :has_cashback";
    $params[':has_cashback'] = $hasCashback;
}

// Apply sorting
switch ($sort) {
    case 'price_asc':
        $query .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY p.price DESC";
        break;
    case 'points_desc':
        $query .= " ORDER BY p.points DESC";
        break;
    case 'cashback_desc':
        $query .= " ORDER BY p.cashback_percentage DESC";
        break;
    case 'newest':
    default:
        $query .= " ORDER BY p.product_id DESC";
        break;
}

// Prepare and execute the query
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total product count
$totalProductCount = count($products);
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تصفح المنتجات - أرزاق</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="ui/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>.products-page{min-height:80vh;padding:20px 0}.page-layout{display:flex;gap:20px;flex-direction:row-reverse}.filter-sidebar{width:250px;flex-shrink:0;position:sticky;top:20px;align-self:flex-start}.products-main{flex-grow:1}.filter-section{background-color:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.08);border-radius:8px;padding:16px;margin-bottom:20px}.filter-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;border-bottom:1px solid #f0f0f0;padding-bottom:10px}.filter-header h3{color:#25995C;font-size:1.1rem;margin:0}.filter-toggle{background:none;border:none;font-size:1.2rem;color:#666;cursor:pointer;display:none}.filter-group{margin-bottom:18px}.filter-group h4{color:#333;margin-bottom:8px;font-size:0.95rem;font-weight:600}.filter-options{display:flex;flex-direction:column;gap:10px}.filter-option{display:flex;align-items:center}.filter-option input{margin-left:8px;accent-color:#25995C;width:16px;height:16px}.price-inputs{display:flex;gap:10px;margin-top:5px}.price-inputs input{width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;text-align:center}.filter-actions{display:flex;justify-content:space-between;gap:10px;margin-top:20px}.filter-actions button{padding:8px 15px;border-radius:4px;border:none;cursor:pointer;font-family:'Cairo',sans-serif;font-weight:bold;transition:all 0.2s ease}.apply-filters{background-color:#25995C;color:white;flex-grow:1}.apply-filters:hover{background-color:#1e8049}.reset-filters{background-color:#f1f1f1;color:#333}.reset-filters:hover{background-color:#e5e5e5}.products-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px}.products-header h2{color:#25995C;font-size:1.4rem;margin:0}.sort-filter{display:flex;align-items:center;gap:15px}.sort-by select{padding:8px 12px;border:1px solid #ddd;border-radius:4px;background-color:#fff;font-family:'Cairo',sans-serif;min-width:200px}.view-options{display:flex;gap:5px}.view-option{width:36px;height:36px;border:1px solid #ddd;border-radius:4px;display:flex;align-items:center;justify-content:center;cursor:pointer;background-color:#fff;color:#666;transition:all 0.2s ease}.view-option.active{background-color:#25995C;color:#fff;border-color:#25995C}.products-grid{display:grid;grid-template-columns:repeat(auto-fill, minmax(230px, 1fr));gap:20px}.products-grid.list-view{display:flex;flex-direction:column;gap:15px}.product-card{transition:transform 0.2s ease, box-shadow 0.2s ease}.product-card:hover{transform:translateY(-5px);box-shadow:0 5px 15px rgba(0,0,0,0.1)}.product-card.list-mode{display:grid;grid-template-columns:180px 1fr auto;height:auto;gap:15px}.product-card.list-mode .product-image{height:160px}.product-card.list-mode .product-info{padding:15px 0}.product-card.list-mode .product-info h3{height:auto;margin-bottom:10px;font-size:1.1rem}.product-card.list-mode .product-meta{flex-direction:column;align-items:flex-start;gap:10px}.product-card.list-mode .price-market{flex-direction:row;align-items:center;gap:15px}.product-card.list-mode .product-actions{flex-direction:column;width:auto}.product-card.list-mode .add-to-cart,.product-card.list-mode .market-name{width:120px}.no-products{text-align:center;padding:50px 0;color:#666}.no-products i{font-size:48px;color:#25995C;margin-bottom:15px}.pagination{display:flex;justify-content:center;margin-top:30px;gap:5px}.pagination-btn{width:38px;height:38px;border:1px solid #ddd;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#25995C;background-color:#fff;cursor:pointer;transition:all 0.2s ease}.pagination-btn.active{background-color:#25995C;color:#fff;border-color:#25995C}.pagination-btn:hover:not(.active){background-color:#f0f0f0}.mobile-filter-toggle{display:none;padding:8px 15px;background-color:#25995C;color:#fff;border:none;border-radius:4px;margin-bottom:15px;cursor:pointer;align-items:center;gap:5px;font-weight:bold;font-family:'Cairo',sans-serif}.filter-backdrop{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background-color:rgba(0,0,0,0.5);z-index:998}.product-grid-container{position:relative;min-height:300px}.loading-overlay{position:absolute;top:0;left:0;right:0;bottom:0;background-color:rgba(255,255,255,0.7);display:flex;align-items:center;justify-content:center;z-index:10;display:none}.spinner{width:50px;height:50px;border:5px solid #f3f3f3;border-top:5px solid #25995C;border-radius:50%;animation:spin 1s linear infinite}@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}@media(max-width:992px){.page-layout{gap:15px}.filter-sidebar{width:220px}}@media(max-width:768px){.page-layout{flex-direction:column}.filter-sidebar{width:100%;position:static}.mobile-filter-toggle{display:flex}.filter-section{display:none;margin-bottom:20px}.filter-section.mobile-visible{display:block;position:fixed;top:0;right:0;bottom:0;width:85%;max-width:300px;z-index:999;overflow-y:auto;transform:translateX(0);transition:transform 0.3s ease-in-out}.filter-toggle{display:block}.filter-group{padding-bottom:10px;border-bottom:1px solid #f0f0f0}.product-card.list-mode{grid-template-columns:120px 1fr;grid-template-rows:1fr auto}.product-card.list-mode .product-actions{grid-column:span 2;flex-direction:row}}@media(max-width:576px){.products-header{flex-direction:column;align-items:flex-start}.sort-filter{width:100%;justify-content:space-between}.products-grid{grid-template-columns:repeat(auto-fill, minmax(160px, 1fr))}}@media(max-width:480px){.product-card.list-mode{grid-template-columns:100px 1fr}.product-card.list-mode .product-image{height:120px}.product-card.list-mode .product-actions{flex-direction:column}.pagination-btn{width:32px;height:32px}}</style>
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

    <section class="products-page">
        <div class="container">
            <button class="mobile-filter-toggle" id="mobileFilterToggle">
                <i class="fas fa-filter"></i> تصفية المنتجات
            </button>
            
            <div class="filter-backdrop" id="filterBackdrop"></div>
            
            <div class="page-layout">
                <div class="filter-sidebar">
                    <div class="filter-section" id="filterSection">
                        <div class="filter-header">
                            <h3>تصفية المنتجات</h3>
                            <button class="filter-toggle" id="closeFilter">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <form id="filterForm" action="products.php" method="GET">
                            <div class="filter-group">
                                <h4>الأقسام</h4>
                                <div class="filter-options">
                                    <?php foreach($categories as $category): ?>
                                    <div class="filter-option">
                                        <input type="radio" name="category" id="cat<?= $category['category_id'] ?>" value="<?= $category['category_id'] ?>" <?= $categoryId == $category['category_id'] ? 'checked' : '' ?>>
                                        <label for="cat<?= $category['category_id'] ?>"><?= $category['category_name'] ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="filter-group">
                                <h4>السعر</h4>
                                <div class="price-inputs">
                                    <input type="number" name="min_price" placeholder="من" value="<?= $minPrice ?>">
                                    <input type="number" name="max_price" placeholder="إلى" value="<?= $maxPrice ?>">
                                </div>
                            </div>
                            
                            <div class="filter-group">
                                <h4>حالة المنتج</h4>
                                <div class="filter-options">
                                    <div class="filter-option">
                                        <input type="radio" name="new" id="newYes" value="1" <?= $isNew === 1 ? 'checked' : '' ?>>
                                        <label for="newYes">جديد</label>
                                    </div>
                                    <div class="filter-option">
                                        <input type="radio" name="new" id="newNo" value="0" <?= $isNew === 0 ? 'checked' : '' ?>>
                                        <label for="newNo">مستعمل</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="filter-group">
                                <h4>المصدر</h4>
                                <div class="filter-options">
                                    <div class="filter-option">
                                        <input type="radio" name="local" id="localYes" value="1" <?= $isLocal === 1 ? 'checked' : '' ?>>
                                        <label for="localYes">محلي</label>
                                    </div>
                                    <div class="filter-option">
                                        <input type="radio" name="local" id="localNo" value="0" <?= $isLocal === 0 ? 'checked' : '' ?>>
                                        <label for="localNo">مستورد</label>
                                    </div>
                                </div>
                            </div>
                            
                            <input type="hidden" name="sort" value="<?= $sort ?>">
                            
                            <div class="filter-actions">
                                <button type="submit" class="apply-filters">تطبيق</button>
                                <button type="button" id="resetFilters" class="reset-filters">إعادة</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="products-main">
                    <div class="products-header">
                        <h2>المنتجات (<?= $totalProductCount ?>)</h2>
                        
                        <div class="sort-filter">
                            <div class="sort-by">
                                <select id="sortSelect" name="sort">
                                    <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>الأحدث</option>
                                    <option value="price_asc" <?= $sort == 'price_asc' ? 'selected' : '' ?>>السعر: من الأقل للأعلى</option>
                                    <option value="price_desc" <?= $sort == 'price_desc' ? 'selected' : '' ?>>السعر: من الأعلى للأقل</option>
                                    <option value="points_desc" <?= $sort == 'points_desc' ? 'selected' : '' ?>>النقاط: من الأعلى للأقل</option>
                                    <option value="cashback_desc" <?= $sort == 'cashback_desc' ? 'selected' : '' ?>>الاسترداد: من الأعلى للأقل</option>
                                </select>
                            </div>
                            
                            <div class="view-options">
                                <button class="view-option active" data-view="grid">
                                    <i class="fas fa-th-large"></i>
                                </button>
                                <button class="view-option" data-view="list">
                                    <i class="fas fa-list"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="product-grid-container">
                        <div class="loading-overlay" id="loadingOverlay">
                            <div class="spinner"></div>
                        </div>
                        
                        <div class="products-grid" id="productsGrid">
                            <?php if (count($products) > 0): ?>
                                <?php foreach($products as $product): 
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
                                            <i class="fas fa-cart-plus"></i> أضف إلى السلة
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-products">
                                    <i class="fas fa-search"></i>
                                    <h3>لا توجد منتجات مطابقة لمعايير البحث</h3>
                                    <p>حاول تغيير معايير التصفية للعثور على منتجات</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (count($products) > 0): ?>
                    <div class="pagination">
                        <a href="#" class="pagination-btn"><i class="fas fa-chevron-right"></i></a>
                        <a href="#" class="pagination-btn active">1</a>
                        <a href="#" class="pagination-btn">2</a>
                        <a href="#" class="pagination-btn">3</a>
                        <a href="#" class="pagination-btn"><i class="fas fa-chevron-left"></i></a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    
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
    
    <script>document.addEventListener('DOMContentLoaded',function(){const addToCartButtons=document.querySelectorAll('.add-to-cart');const viewOptions=document.querySelectorAll('.view-option');const productsGrid=document.getElementById('productsGrid');const mobileFilterToggle=document.getElementById('mobileFilterToggle');const filterSection=document.getElementById('filterSection');const filterBackdrop=document.getElementById('filterBackdrop');const closeFilter=document.getElementById('closeFilter');const sortSelect=document.getElementById('sortSelect');const resetFiltersBtn=document.getElementById('resetFilters');const loadingOverlay=document.getElementById('loadingOverlay');addToCartButtons.forEach(button=>{button.addEventListener('click',function(){const productId=this.getAttribute('data-id');<?php if($loggedIn): ?>loadingOverlay.style.display='flex';fetch('add_to_cart.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'product_id='+productId+'&quantity=1'}).then(response=>response.json()).then(data=>{loadingOverlay.style.display='none';if(data.success){this.classList.add('added');this.innerHTML='<i class="fas fa-check"></i> تمت الإضافة';setTimeout(()=>{this.classList.remove('added');this.innerHTML='<i class="fas fa-cart-plus"></i> أضف إلى السلة';},2000);const cartCountElement=document.querySelector('.cart-count');if(cartCountElement){const currentCount=parseInt(cartCountElement.textContent)||0;cartCountElement.textContent=currentCount+1;}else{const cartIcon=document.querySelector('.cart-icon');const countSpan=document.createElement('span');countSpan.className='cart-count';countSpan.textContent='1';cartIcon.appendChild(countSpan);}}else{alert('حدث خطأ أثناء إضافة المنتج إلى السلة.');}}).catch(error=>{loadingOverlay.style.display='none';console.error('Error:',error);alert('حدث خطأ أثناء إضافة المنتج إلى السلة.');});<?php else: ?>window.location.href='login.php?redirect=products.php';<?php endif; ?>});});viewOptions.forEach(option=>{option.addEventListener('click',function(){const view=this.getAttribute('data-view');viewOptions.forEach(opt=>opt.classList.remove('active'));this.classList.add('active');if(view==='grid'){productsGrid.classList.remove('list-view');document.querySelectorAll('.product-card').forEach(card=>{card.classList.remove('list-mode');});}else{productsGrid.classList.add('list-view');document.querySelectorAll('.product-card').forEach(card=>{card.classList.add('list-mode');});}});});mobileFilterToggle.addEventListener('click',function(){filterSection.classList.add('mobile-visible');filterBackdrop.style.display='block';document.body.style.overflow='hidden';});closeFilter.addEventListener('click',function(){filterSection.classList.remove('mobile-visible');filterBackdrop.style.display='none';document.body.style.overflow='auto';});filterBackdrop.addEventListener('click',function(){filterSection.classList.remove('mobile-visible');filterBackdrop.style.display='none';document.body.style.overflow='auto';});sortSelect.addEventListener('change',function(){const currentUrl=new URL(window.location.href);currentUrl.searchParams.set('sort',this.value);loadingOverlay.style.display='flex';window.location.href=currentUrl.toString();});resetFiltersBtn.addEventListener('click',function(){window.location.href='products.php';});});</script>
</body>
</html>