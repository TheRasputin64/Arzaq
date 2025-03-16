<?php
session_start();
require_once 'db.php';
$loggedIn = isset($_SESSION['user_id']);
$username = $loggedIn ? $_SESSION['username'] : '';
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
    <title>أرزاق - اختيار نوع الحساب</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="icon" type="image/png" href="../ui/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>.selection-container{display:flex;flex-direction:column;align-items:center;padding:30px 0;background-color:#f8f8f8;}.selection-header{text-align:center;margin-bottom:30px;}.selection-header h1{color:#25995C;font-size:1.8rem;margin-bottom:10px;}.selection-header p{color:#666;font-size:1.1rem;}.selection-grid{display:grid;grid-template-columns:repeat(2, 1fr);gap:20px;width:100%;max-width:800px;margin:0 auto;}.account-card{background-color:white;border-radius:8px;box-shadow:0 3px 10px rgba(0,0,0,0.08);padding:25px;text-align:center;transition:all 0.3s;border:2px solid transparent;text-decoration:none;color:#333;}.account-card:hover{transform:translateY(-5px);border-color:#25995C;box-shadow:0 5px 15px rgba(37,153,92,0.2);}.account-icon{font-size:3rem;color:#25995C;margin-bottom:15px;}.account-title{font-size:1.2rem;font-weight:bold;margin-bottom:10px;color:#333;}.account-desc{color:#666;font-size:0.9rem;line-height:1.5;}@media(max-width:768px){.selection-grid{grid-template-columns:1fr;max-width:450px;}}</style>
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
                <a href="index.php"><img src="../ui/logo.png" alt="أرزاق"></a>
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
                <li><a href="categories.php"><i class="fas fa-th-large"></i> الأقسام</a></li>
                <li><a href="offers.php"><i class="fas fa-tag"></i> العروض</a></li>
                <li><a href="wholesale.php"><i class="fas fa-box"></i> الجملة</a></li>
                <li><a href="markets.php"><i class="fas fa-store"></i> المتاجر</a></li>
                <li><a href="services.php"><i class="fas fa-concierge-bell"></i> الخدمات</a></li>
            </ul>
        </div>
    </div>
    
    <section class="selection-container">
        <div class="container">
            <div class="selection-header">
                <h1>تسجيل حساب جديد</h1>
                <p>اختر نوع الحساب المناسب لك</p>
            </div>
            
            <div class="selection-grid">
                <a href="register.php?type=sales" class="account-card">
                    <div class="account-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <h3 class="account-title">مندوب مبيعات</h3>
                    <p class="account-desc">سجل كمندوب مبيعات وساعد التجار في تسويق منتجاتهم وزيادة مبيعاتهم</p>
                </a>
                
                <a href="register.php?type=purchasing" class="account-card">
                    <div class="account-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3 class="account-title">مندوب مشتريات</h3>
                    <p class="account-desc">سجل كمندوب مشتريات وساعد التجار في الحصول على أفضل المنتجات بأفضل الأسعار</p>
                </a>
                
                <a href="register.php?type=delivery" class="account-card">
                    <div class="account-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <h3 class="account-title">مندوب توصيل</h3>
                    <p class="account-desc">سجل كمندوب توصيل وساهم في إيصال المنتجات للعملاء بكفاءة وسرعة</p>
                </a>
                
                <a href="register.php?type=service_provider" class="account-card">
                    <div class="account-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <h3 class="account-title">مزود خدمة</h3>
                    <p class="account-desc">سجل كمزود خدمة وقدم خدماتك المتنوعة للتجار والعملاء على المنصة</p>
                </a>
            </div>
            
            <div class="footer" style="text-align:center; margin-top:30px; padding-top:15px; border-top:1px solid #eee;">
                <p>لديك حساب بالفعل؟ <a href="login.php" style="color:#25995C; font-weight:bold;">تسجيل الدخول</a></p>
            </div>
        </div>
    </section>
    
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <img src="../ui/footer.png" alt="أرزاق">
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
    <script>document.addEventListener('DOMContentLoaded',function(){const addToCartButtons=document.querySelectorAll('.add-to-cart');addToCartButtons.forEach(button=>{button.addEventListener('click',function(){const productId=this.getAttribute('data-id');<?php if($loggedIn): ?>fetch('add_to_cart.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'product_id='+productId+'&quantity=1'}).then(response=>response.json()).then(data=>{if(data.success){alert('تمت إضافة المنتج إلى السلة بنجاح!');const cartCountElement=document.querySelector('.cart-count');if(cartCountElement){const currentCount=parseInt(cartCountElement.textContent)||0;cartCountElement.textContent=currentCount+1;}else{const cartIcon=document.querySelector('.cart-icon');const countSpan=document.createElement('span');countSpan.className='cart-count';countSpan.textContent='1';cartIcon.appendChild(countSpan);}}else{alert('حدث خطأ أثناء إضافة المنتج إلى السلة.');}}).catch(error=>{console.error('Error:',error);alert('حدث خطأ أثناء إضافة المنتج إلى السلة.');});<?php else: ?>window.location.href='login.php?redirect=index.php';<?php endif; ?>});});});</script>
</body>
</html>