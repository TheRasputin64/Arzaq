<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['market_id'])) {header("Location: index.php");exit();}

$market_id = $_SESSION['market_id'];
$market_name = $_SESSION['market_name'];

$stmt = $conn->prepare("SELECT * FROM markets WHERE market_id = ?");
$stmt->execute([$market_id]);
$market = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT COUNT(*) AS total_products FROM products WHERE market_id = ?");
$stmt->execute([$market_id]);
$total_products = $stmt->fetch(PDO::FETCH_ASSOC)['total_products'];

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT o.order_id) AS total_orders 
    FROM orders o 
    JOIN order_items oi ON o.order_id = oi.order_id 
    JOIN products p ON oi.product_id = p.product_id 
    WHERE p.market_id = ?
");
$stmt->execute([$market_id]);
$total_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total_orders'];

$stmt = $conn->prepare("
    SELECT SUM(oi.price * oi.quantity) AS total_sales 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    WHERE p.market_id = ?
");
$stmt->execute([$market_id]);
$total_sales = $stmt->fetch(PDO::FETCH_ASSOC)['total_sales'] ?: 0;

$stmt = $conn->prepare("
    SELECT o.order_id, o.order_date, o.total_amount, o.status, u.name AS user_name
    FROM orders o 
    JOIN users u ON o.user_id = u.user_id
    JOIN order_items oi ON o.order_id = oi.order_id 
    JOIN products p ON oi.product_id = p.product_id 
    WHERE p.market_id = ?
    GROUP BY o.order_id
    ORDER BY o.order_date DESC 
    LIMIT 5
");
$stmt->execute([$market_id]);
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم المتجر - أرزاق بلس</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Cairo',sans-serif;background-color:#f7f9fc;color:#333;direction:rtl;}
        .dashboard-container{display:flex;min-height:100vh;position:relative;}
        .sidebar{width:250px;background-color:#25995C;color:white;position:fixed;height:100vh;overflow-y:auto;transition:all 0.3s;}
        .sidebar-header{padding:20px 15px;border-bottom:1px solid rgba(255,255,255,0.1);}
        .sidebar-header h3{font-size:1.5rem;}
        .sidebar-header p{font-size:0.9rem;opacity:0.8;margin-top:5px;}
        .sidebar-menu{padding:20px 0;}
        .sidebar-menu-item{padding:12px 20px;display:flex;align-items:center;transition:all 0.3s;cursor:pointer;}
        .sidebar-menu-item:hover{background-color:rgba(255,255,255,0.1);}
        .sidebar-menu-item.active{background-color:rgba(255,255,255,0.2);border-right:4px solid white;}
        .sidebar-menu-item i{margin-left:10px;width:20px;text-align:center;}
        .sidebar-footer{position:absolute;bottom:0;width:100%;padding:15px;text-align:center;border-top:1px solid rgba(255,255,255,0.1);}
        .sidebar-footer a{color:white;text-decoration:none;opacity:0.8;font-size:0.9rem;}
        .sidebar-footer a:hover{opacity:1;}
        .main-content{flex:1;padding:20px;width:calc(100% - 250px);margin-right:250px;transition:all 0.3s;}
        .header{background-color:white;padding:15px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.05);margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;}
        .header h2{color:#25995C;}
        .header-actions a{background-color:#25995C;color:white;padding:8px 15px;border-radius:5px;text-decoration:none;font-size:0.9rem;transition:all 0.3s;}
        .header-actions a:hover{background-color:#1e7a48;}
        .stats-container{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:20px;}
        .stat-card{background-color:white;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:15px;text-align:center;}
        .stat-card i{font-size:2rem;color:#25995C;margin-bottom:10px;}
        .stat-card h3{font-size:1.8rem;color:#25995C;margin-bottom:5px;}
        .stat-card p{color:#777;font-size:0.9rem;}
        .section{background-color:white;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:15px;margin-bottom:20px;}
        .section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;padding-bottom:10px;border-bottom:1px solid #eee;}
        .section-header h3{color:#25995C;}
        .section-header a{color:#25995C;text-decoration:none;font-size:0.9rem;}
        .section-header a:hover{text-decoration:underline;}
        .table{width:100%;border-collapse:collapse;}
        .table th{background-color:#f9f9f9;padding:10px;text-align:right;border-bottom:2px solid #eee;}
        .table td{padding:10px;border-bottom:1px solid #eee;}
        .table tr:last-child td{border-bottom:none;}
        .status-badge{padding:5px 10px;border-radius:5px;font-size:0.8rem;display:inline-block;}
        .status-pending{background-color:#ffecb3;color:#e6a700;}
        .status-processing{background-color:#b3e5fc;color:#0277bd;}
        .status-shipped{background-color:#c8e6c9;color:#2e7d32;}
        .status-delivered{background-color:#dcedc8;color:#558b2f;}
        .status-cancelled{background-color:#ffcdd2;color:#c62828;}
        .quick-actions{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;}
        .quick-action-btn{display:flex;flex-direction:column;align-items:center;justify-content:center;background-color:#f8f9fa;border:1px solid #eee;border-radius:10px;padding:15px;text-decoration:none;color:#333;transition:all 0.3s;}
        .quick-action-btn:hover{background-color:#f1f3f5;transform:translateY(-3px);box-shadow:0 5px 15px rgba(0,0,0,0.1);}
        .quick-action-btn i{font-size:1.8rem;color:#25995C;margin-bottom:10px;}
        .store-info{display:grid;grid-template-columns:1fr 1fr;gap:15px;}
        .store-info p{margin-bottom:10px;}
        .hamburger{display:none;position:fixed;top:15px;right:15px;z-index:999;background-color:#25995C;color:white;width:40px;height:40px;border-radius:50%;align-items:center;justify-content:center;cursor:pointer;border:none;}
        .sidebar-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background-color:rgba(0,0,0,0.5);z-index:998;}
        @media (max-width:992px){
            .stats-container{grid-template-columns:repeat(2,1fr);}
            .quick-actions{grid-template-columns:repeat(2,1fr);}
            .sidebar{width:240px;transform:translateX(240px);z-index:999;}
            .sidebar.active{transform:translateX(0);}
            .main-content{width:100%;margin-right:0;}
            .hamburger{display:flex;}
            .sidebar-overlay.active{display:block;}
        }
        @media (max-width:768px){
            .header{flex-direction:column;align-items:flex-start;}
            .header-actions{margin-top:10px;width:100%;}
            .header-actions a{display:block;text-align:center;margin-top:10px;}
            .store-info{grid-template-columns:1fr;}
        }
        @media (max-width:576px){
            .stats-container{grid-template-columns:1fr;}
            .quick-actions{grid-template-columns:1fr;}
            .table{display:block;overflow-x:auto;white-space:nowrap;}
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <button class="hamburger" id="toggleSidebar">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3>أرزاق بلس</h3>
                <p>لوحة تحكم المتجر</p>
            </div>
            <div class="sidebar-menu">
                <div class="sidebar-menu-item active">
                    <i class="fas fa-home"></i>
                    <span>الرئيسية</span>
                </div>
                <div class="sidebar-menu-item" onclick="window.location.href='products.php'">
                    <i class="fas fa-box"></i>
                    <span>المنتجات</span>
                </div>
                <div class="sidebar-menu-item" onclick="window.location.href='orders.php'">
                    <i class="fas fa-shopping-cart"></i>
                    <span>الطلبات</span>
                </div>
                <div class="sidebar-menu-item" onclick="window.location.href='statistics.php'">
                    <i class="fas fa-chart-bar"></i>
                    <span>الإحصائيات</span>
                </div>
                <div class="sidebar-menu-item" onclick="window.location.href='finances.php'">
                    <i class="fas fa-wallet"></i>
                    <span>المالية</span>
                </div>
                <div class="sidebar-menu-item" onclick="window.location.href='settings.php'">
                    <i class="fas fa-cog"></i>
                    <span>الإعدادات</span>
                </div>
            </div>
            <div class="sidebar-footer">
                <a href="?logout=1">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>تسجيل الخروج</span>
                </a>
            </div>
        </div>
        
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <div class="main-content">
            <div class="header">
                <div>
                    <h2>مرحباً، <?php echo htmlspecialchars($market_name); ?></h2>
                    <p><?php echo date("Y-m-d") ?></p>
                </div>
                <div class="header-actions">
                    <a href="add-product.php"><i class="fas fa-plus"></i> إضافة منتج جديد</a>
                </div>
            </div>
            
            <div class="stats-container">
                <div class="stat-card">
                    <i class="fas fa-box"></i>
                    <h3><?php echo $total_products; ?></h3>
                    <p>إجمالي المنتجات</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-shopping-cart"></i>
                    <h3><?php echo $total_orders; ?></h3>
                    <p>إجمالي الطلبات</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-money-bill-wave"></i>
                    <h3><?php echo number_format($total_sales, 2); ?> SAR</h3>
                    <p>إجمالي المبيعات</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-star"></i>
                    <h3><?php echo number_format($market['points']); ?></h3>
                    <p>نقاط المتجر</p>
                </div>
            </div>
            
            <div class="section">
                <div class="section-header">
                    <h3>الإجراءات السريعة</h3>
                </div>
                <div class="quick-actions">
                    <a href="add-product.php" class="quick-action-btn">
                        <i class="fas fa-plus"></i>
                        <span>إضافة منتج</span>
                    </a>
                    <a href="products.php" class="quick-action-btn">
                        <i class="fas fa-list"></i>
                        <span>عرض المنتجات</span>
                    </a>
                    <a href="orders.php?filter=new" class="quick-action-btn">
                        <i class="fas fa-shopping-bag"></i>
                        <span>الطلبات الجديدة</span>
                    </a>
                    <a href="statistics.php" class="quick-action-btn">
                        <i class="fas fa-chart-line"></i>
                        <span>إحصائيات المبيعات</span>
                    </a>
                </div>
            </div>
            
            <div class="section">
                <div class="section-header">
                    <h3>آخر الطلبات</h3>
                    <a href="orders.php">عرض الكل</a>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>رقم الطلب</th>
                            <th>العميل</th>
                            <th>التاريخ</th>
                            <th>القيمة</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_orders)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">لا توجد طلبات حتى الآن</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['order_id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['user_name']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($order['order_date'])); ?></td>
                                    <td><?php echo number_format($order['total_amount'], 2); ?> SAR</td>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        switch ($order['status']) {
                                            case 'pending':
                                                $statusClass = 'status-pending';
                                                $statusText = 'قيد الانتظار';
                                                break;
                                            case 'processing':
                                                $statusClass = 'status-processing';
                                                $statusText = 'قيد المعالجة';
                                                break;
                                            case 'shipped':
                                                $statusClass = 'status-shipped';
                                                $statusText = 'تم الشحن';
                                                break;
                                            case 'delivered':
                                                $statusClass = 'status-delivered';
                                                $statusText = 'تم التسليم';
                                                break;
                                            case 'cancelled':
                                                $statusClass = 'status-cancelled';
                                                $statusText = 'ملغي';
                                                break;
                                            default:
                                                $statusClass = '';
                                                $statusText = $order['status'];
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                    </td>
                                    <td>
                                        <a href="view-order.php?id=<?php echo $order['order_id']; ?>" style="color: #25995C; margin-left: 10px;"><i class="fas fa-eye"></i></a>
                                        <a href="edit-order.php?id=<?php echo $order['order_id']; ?>" style="color: #25995C;"><i class="fas fa-edit"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="section">
                <div class="section-header">
                    <h3>معلومات المتجر</h3>
                    <a href="edit-store.php">تعديل</a>
                </div>
                <div class="store-info">
                    <div>
                        <p><strong>الاسم:</strong> <?php echo htmlspecialchars($market['name']); ?></p>
                        <p><strong>البريد الإلكتروني:</strong> <?php echo htmlspecialchars($market['email']); ?></p>
                        <p><strong>رقم الهاتف:</strong> <?php echo htmlspecialchars($market['phone']); ?></p>
                    </div>
                    <div>
                        <p><strong>العنوان:</strong> <?php echo htmlspecialchars($market['address']); ?></p>
                        <p><strong>متاح على واتساب:</strong> <?php echo $market['whatsapp'] ? 'نعم' : 'لا'; ?></p>
                        <p><strong>حالة المتجر:</strong> <?php echo $market['is_active'] ? 'نشط' : 'غير نشط'; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const toggleSidebar = document.getElementById('toggleSidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            
            toggleSidebar.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
            });
            
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            });
            
            const menuItems = document.querySelectorAll('.sidebar-menu-item');
            menuItems.forEach(item => {
                item.addEventListener('click', function() {
                    menuItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                });
            });
            
            function adjustLayout() {
                if (window.innerWidth <= 992) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                }
            }
            
            window.addEventListener('resize', adjustLayout);
            adjustLayout();
        });
    </script>
</body>
</html>