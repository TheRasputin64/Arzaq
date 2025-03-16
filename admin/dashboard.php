<?php
session_start();
include 'db.php';
if (!isset($_SESSION['admin_id'])) {header('Location: login.php');exit();
}
$adminInfo = $conn->query("SELECT username FROM admin WHERE admin_id = " . $_SESSION['admin_id'])->fetch_assoc();
$marketCount = $conn->query("SELECT COUNT(*) as count FROM markets")->fetch_assoc()['count'];
$productCount = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$categoryCount = $conn->query("SELECT COUNT(*) as count FROM categories")->fetch_assoc()['count'];
$orderCount = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
$userCount = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$recentOrders = $conn->query("SELECT o.order_id, u.name as user_name, o.total_amount, o.order_date, o.status FROM orders o JOIN users u ON o.user_id = u.user_id ORDER BY o.order_date DESC LIMIT 5");
$topProducts = $conn->query("SELECT p.name, COUNT(oi.order_item_id) as order_count FROM products p JOIN order_items oi ON p.product_id = oi.product_id GROUP BY p.product_id ORDER BY order_count DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم أرزاق - الرئيسية</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../ui/logo.png">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>أرزاق</h2>
                <p>لوحة تحكم الإدارة</p>
            </div>
            <nav class="sidebar-menu">
                <a href="dashboard.php" class="menu-item active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>الرئيسية</span>
                </a>
                <a href="markets.php" class="menu-item">
                    <i class="fas fa-store"></i>
                    <span>الأسواق</span>
                </a>
                <a href="categories.php" class="menu-item">
                    <i class="fas fa-th-large"></i>
                    <span>الفئات</span>
                </a>
                <a href="products.php" class="menu-item">
                    <i class="fas fa-box"></i>
                    <span>المنتجات</span>
                </a>
                <a href="orders.php" class="menu-item">
                    <i class="fas fa-shopping-cart"></i>
                    <span>الطلبات</span>
                </a>
                <a href="users.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>المستخدمين</span>
                </a>
                <a href="reports.php" class="menu-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>التقارير</span>
                </a>
                <a href="settings.php" class="menu-item">
                    <i class="fas fa-cog"></i>
                    <span>الإعدادات</span>
                </a>
                <a href="logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>تسجيل الخروج</span>
                </a>
            </nav>
        </aside>
        <main class="main-content">
            <div class="top-bar">
                <div class="admin-info">
                    <div class="user-avatar">
                        <?php echo substr($adminInfo['name'] ?? 'A', 0, 1); ?>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?php echo $adminInfo['name'] ?? 'المدير'; ?></span>
                        <span class="user-role">مدير النظام</span>
                    </div>
                </div>
                <div class="top-actions">
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        تسجيل الخروج
                    </a>
                </div>
            </div>
            <div class="content-header">
                <h1>لوحة التحكم</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="dashboard.php">الرئيسية</a>
                    </div>
                    <div class="breadcrumb-item">لوحة التحكم</div>
                </div>
            </div>
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon markets">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo $marketCount; ?></div>
                        <div class="stat-label">الأسواق</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon products">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo $productCount; ?></div>
                        <div class="stat-label">المنتجات</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon categories">
                        <i class="fas fa-th-large"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo $categoryCount; ?></div>
                        <div class="stat-label">الفئات</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orders">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo $orderCount; ?></div>
                        <div class="stat-label">الطلبات</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo $userCount; ?></div>
                        <div class="stat-label">المستخدمين</div>
                    </div>
                </div>
            </div>
            <div class="dashboard-panels">
                <div class="dashboard-panel">
                    <div class="panel-header">
                        <h3 class="panel-title">أحدث الطلبات</h3>
                    </div>
                    <div class="panel-body recent-orders">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>العميل</th>
                                    <th>المبلغ</th>
                                    <th>التاريخ</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($order = $recentOrders->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $order['order_id']; ?></td>
                                    <td><?php echo $order['user_name']; ?></td>
                                    <td><?php echo number_format($order['total_amount'], 2) . ' ريال'; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($order['order_date'])); ?></td>
                                    <td>
                                        <?php 
                                        $statusClass = '';
                                        switch($order['status']) {
                                            case 'pending':
                                                $statusClass = 'pending';
                                                $statusText = 'قيد الانتظار';
                                                break;
                                            case 'processing':
                                                $statusClass = 'processing';
                                                $statusText = 'قيد المعالجة';
                                                break;
                                            case 'completed':
                                                $statusClass = 'completed';
                                                $statusText = 'مكتمل';
                                                break;
                                            case 'cancelled':
                                                $statusClass = 'cancelled';
                                                $statusText = 'ملغي';
                                                break;
                                            default:
                                                $statusClass = 'pending';
                                                $statusText = $order['status'];
                                        }
                                        ?>
                                        <span class="status <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php if($recentOrders->num_rows === 0): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">لا توجد طلبات حديثة</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="panel-footer">
                        <a href="orders.php" class="view-all">عرض كل الطلبات <i class="fas fa-arrow-left"></i></a>
                    </div>
                </div>
                <div class="dashboard-panel">
                    <div class="panel-header">
                        <h3 class="panel-title">أكثر المنتجات مبيعًا</h3>
                    </div>
                    <div class="panel-body">
                        <ul class="top-products">
                            <?php while($product = $topProducts->fetch_assoc()): ?>
                            <li>
                                <span class="product-name"><?php echo $product['name']; ?></span>
                                <span class="product-count"><?php echo $product['order_count']; ?> طلب</span>
                            </li>
                            <?php endwhile; ?>
                            <?php if($topProducts->num_rows === 0): ?>
                            <li style="text-align: center;">لا توجد منتجات للعرض</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="panel-footer">
                        <a href="products.php" class="view-all">عرض كل المنتجات <i class="fas fa-arrow-left"></i></a>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <div class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </div>
    <script>
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const menuToggle = document.getElementById('menuToggle');
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !menuToggle.contains(event.target) &&
                sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        });
    </script>
</body>
</html>