<?php
session_start();
require_once 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=orders.php');
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get user info
$stmt = $conn->prepare("SELECT points, user_tier FROM users WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$userInfo = $stmt->fetch(PDO::FETCH_ASSOC);

// Get cart count
$stmt = $conn->prepare("SELECT SUM(ci.quantity) as count FROM cart_items ci JOIN cart c ON ci.cart_id = c.cart_id WHERE c.user_id = :user_id");
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$cartCount = $result['count'] ? $result['count'] : 0;

// Get categories for menu
$stmt = $conn->prepare("SELECT * FROM categories WHERE is_main = 1 LIMIT 8");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user orders with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$ordersPerPage = 10;
$offset = ($page - 1) * $ordersPerPage;

$stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = :user_id ORDER BY order_date DESC LIMIT :limit OFFSET :offset");
$stmt->bindParam(':user_id', $userId);
$stmt->bindParam(':limit', $ordersPerPage, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total orders count for pagination
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$totalOrders = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalOrders / $ordersPerPage);

// Process order details request via AJAX
if (isset($_GET['action']) && $_GET['action'] == 'get_details' && isset($_GET['order_id'])) {
    $orderId = (int)$_GET['order_id'];
    
    // Verify order belongs to user
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = :order_id AND user_id = :user_id");
    $stmt->bindParam(':order_id', $orderId);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Get order items
        $stmt = $conn->prepare("
            SELECT oi.*, p.name, p.image_path, m.name as market_name 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.product_id 
            JOIN markets m ON p.market_id = m.market_id 
            WHERE oi.order_id = :order_id
        ");
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'items' => $items]);
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
}

// Status translation
$statusTranslation = [
    'pending' => 'قيد الانتظار',
    'processing' => 'قيد المعالجة',
    'shipped' => 'تم الشحن',
    'delivered' => 'تم التوصيل',
    'cancelled' => 'ملغي',
    'completed' => 'مكتمل',
    'paid' => 'مدفوع',
    'unpaid' => 'غير مدفوع'
];

// Status colors
$statusColors = [
    'pending' => '#f39c12',
    'processing' => '#3498db',
    'shipped' => '#9b59b6',
    'delivered' => '#27ae60',
    'cancelled' => '#e74c3c',
    'completed' => '#2ecc71',
    'paid' => '#2ecc71',
    'unpaid' => '#e74c3c'
];
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلباتي - أرزاق</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="ui/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>.orders-container{padding:30px 0}.orders-title{margin-bottom:20px;color:#25995C}.order-table{width:100%;border-collapse:collapse;background-color:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.1);border-radius:8px;overflow:hidden}.order-table th{background-color:#25995C;color:#fff;padding:12px;text-align:right}.order-table td{padding:12px;border-bottom:1px solid #eee}.order-table tr:last-child td{border-bottom:none}.order-table tr:hover{background-color:#f8fffc}.order-status{padding:4px 8px;border-radius:4px;font-size:0.85rem;font-weight:bold;display:inline-block}.order-details-btn{background-color:#25995C;color:#fff;border:none;padding:6px 12px;border-radius:4px;cursor:pointer;font-family:'Cairo',sans-serif;font-size:0.85rem;transition:all 0.3s}.order-details-btn:hover{background-color:#1c7a48}.pagination{display:flex;justify-content:center;margin-top:20px;gap:5px}.pagination a,.pagination span{padding:8px 12px;border:1px solid #ddd;background-color:#fff;color:#25995C;border-radius:4px;transition:all 0.3s}.pagination a:hover{background-color:#25995C;color:#fff}.pagination .active{background-color:#25995C;color:#fff}.no-orders{text-align:center;padding:20px;background-color:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}.order-details-modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background-color:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center}.modal-content{background-color:#fff;border-radius:8px;width:90%;max-width:800px;max-height:90vh;overflow-y:auto;padding:20px;position:relative;animation:fadeIn 0.3s}.close-modal{position:absolute;left:15px;top:15px;font-size:1.5rem;cursor:pointer;color:#666;transition:all 0.3s}.close-modal:hover{color:#25995C}.modal-title{color:#25995C;margin-bottom:20px;padding-bottom:10px;border-bottom:1px solid #eee}.items-table{width:100%;border-collapse:collapse;margin-top:10px}.items-table th{background-color:#f8f8f8;padding:10px;text-align:right;border-bottom:1px solid #eee}.items-table td{padding:10px;border-bottom:1px solid #eee}.order-summary{margin-top:20px;padding:15px;background-color:#f8fffc;border-radius:4px}.item-image{width:50px;height:50px;object-fit:cover;border-radius:4px}.order-item{display:flex;align-items:center;gap:10px}.item-details{flex:1}.shipping-details{margin-top:20px;padding:15px;background-color:#f8f8f8;border-radius:4px}.order-date{color:#666;font-size:0.85rem}@keyframes fadeIn{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}@media(max-width:768px){.order-table{font-size:0.9rem}.order-table th:nth-child(2),.order-table td:nth-child(2){display:none}.order-status{font-size:0.75rem;padding:3px 6px}}@media(max-width:576px){.order-table{font-size:0.85rem}.order-table th:nth-child(3),.order-table td:nth-child(3){display:none}.order-table th:nth-child(4),.order-table td:nth-child(4){display:none}.order-details-btn{padding:4px 8px;font-size:0.75rem}}</style>
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
    
    <section class="orders-container">
        <div class="container">
            <h1 class="orders-title">طلباتي</h1>
            
            <?php if(count($orders) > 0): ?>
                <div class="table-responsive">
                    <table class="order-table">
                        <thead>
                            <tr>
                                <th>رقم الطلب</th>
                                <th>تاريخ الطلب</th>
                                <th>حالة الطلب</th>
                                <th>حالة الدفع</th>
                                <th>المبلغ</th>
                                <th>التفاصيل</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($orders as $order): ?>
                                <tr>
                                    <td>#<?= $order['order_id'] ?></td>
                                    <td><?= date('Y/m/d', strtotime($order['order_date'])) ?></td>
                                    <td>
                                        <span class="order-status" style="background-color: <?= $statusColors[$order['status']] ?? '#666' ?>; color: white;">
                                            <?= $statusTranslation[$order['status']] ?? $order['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="order-status" style="background-color: <?= $statusColors[$order['payment_status']] ?? '#666' ?>; color: white;">
                                            <?= $statusTranslation[$order['payment_status']] ?? $order['payment_status'] ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($order['total_amount'], 2) ?> ريال</td>
                                    <td>
                                        <button class="order-details-btn" data-order-id="<?= $order['order_id'] ?>">
                                            عرض التفاصيل
                                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if($totalPages > 1): ?>
    <div class="pagination">
        <?php if($page > 1): ?>
            <a href="?page=<?= $page-1 ?>">&raquo;</a>
        <?php endif; ?>
        
        <?php for($i = 1; $i <= $totalPages; $i++): ?>
            <?php if($i == $page): ?>
                <span class="active"><?= $i ?></span>
            <?php else: ?>
                <a href="?page=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if($page < $totalPages): ?>
            <a href="?page=<?= $page+1 ?>">&laquo;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php else: ?>
    <div class="no-orders">
        <p>لا توجد طلبات حتى الآن</p>
        <a href="products.php" class="btn-primary">تصفح المنتجات</a>
    </div>
<?php endif; ?>
        </div>
    </section>
    
    <!-- Order Details Modal -->
    <div id="orderDetailsModal" class="order-details-modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2 class="modal-title">تفاصيل الطلب #<span id="orderIdDisplay"></span></h2>
            <div id="orderItemsContainer"></div>
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
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('orderDetailsModal');
            const closeModal = document.querySelector('.close-modal');
            const orderDetailsButtons = document.querySelectorAll('.order-details-btn');
            
            // Open modal when clicking on "Show Details" button
            orderDetailsButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const orderId = this.getAttribute('data-order-id');
                    document.getElementById('orderIdDisplay').textContent = orderId;
                    
                    // Fetch order details via AJAX
                    fetch(`orders.php?action=get_details&order_id=${orderId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const itemsContainer = document.getElementById('orderItemsContainer');
                                let html = '<div class="table-responsive"><table class="items-table"><thead><tr><th>المنتج</th><th>المتجر</th><th>الكمية</th><th>السعر</th><th>الإجمالي</th></tr></thead><tbody>';
                                
                                let totalAmount = 0;
                                data.items.forEach(item => {
                                    const itemTotal = item.quantity * item.price;
                                    totalAmount += itemTotal;
                                    
                                    html += `
                                        <tr>
                                            <td>
                                                <div class="order-item">
                                                    <img src="${item.image_path}" alt="${item.name}" class="item-image" onerror="this.src='ui/placeholder.png'">
                                                    <div class="item-details">
                                                        <div>${item.name}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>${item.market_name}</td>
                                            <td>${item.quantity}</td>
                                            <td>${parseFloat(item.price).toFixed(2)} ريال</td>
                                            <td>${(item.quantity * item.price).toFixed(2)} ريال</td>
                                        </tr>
                                    `;
                                });
                                
                                html += '</tbody></table></div>';
                                
                                // Add order summary
                                html += `
                                    <div class="order-summary">
                                        <h3>ملخص الطلب</h3>
                                        <p>إجمالي المبلغ: ${totalAmount.toFixed(2)} ريال</p>
                                    </div>
                                `;
                                
                                itemsContainer.innerHTML = html;
                            } else {
                                document.getElementById('orderItemsContainer').innerHTML = '<p>حدث خطأ في عرض تفاصيل الطلب</p>';
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            document.getElementById('orderItemsContainer').innerHTML = '<p>حدث خطأ في عرض تفاصيل الطلب</p>';
                        });
                    
                    modal.style.display = 'flex';
                });
            });
            
            // Close modal when clicking on the X
            closeModal.addEventListener('click', function() {
                modal.style.display = 'none';
            });
            
            // Close modal when clicking outside the modal content
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>