<?php
session_start();
include 'db.php';

if (!isset($_SESSION['rep_id']) || $_SESSION['rep_type'] != 'sales') {
    header('Location: login.php');
    exit();
}

$rep_id = $_SESSION['rep_id'];
$rep_name = $_SESSION['rep_name'];

$stmt = $conn->prepare("SELECT * FROM representatives WHERE rep_id = :rep_id");
$stmt->bindParam(':rep_id', $rep_id, PDO::PARAM_INT);
$stmt->execute();
$rep_info = $stmt->fetch();
$referral_code = $rep_info['referral_code'];
$referral_link = "https://arzaq.app/register?ref=" . $referral_code;

$stmt = $conn->prepare("SELECT COUNT(*) as visit_count FROM referral_visits WHERE referral_code = :referral_code");
$stmt->bindParam(':referral_code', $referral_code, PDO::PARAM_STR);
$stmt->execute();
$visitor_count = $stmt->fetch()['visit_count'];

$stmt = $conn->prepare("SELECT COUNT(*) as user_count FROM referrals WHERE rep_id = :rep_id AND user_id IS NOT NULL");
$stmt->bindParam(':rep_id', $rep_id, PDO::PARAM_INT);
$stmt->execute();
$registered_users = $stmt->fetch()['user_count'];

$stmt = $conn->prepare("SELECT COUNT(*) as market_count FROM referrals WHERE rep_id = :rep_id AND market_id IS NOT NULL");
$stmt->bindParam(':rep_id', $rep_id, PDO::PARAM_INT);
$stmt->execute();
$registered_markets = $stmt->fetch()['market_count'];

$stmt = $conn->prepare("SELECT SUM(amount) as total_commission FROM commissions WHERE rep_id = :rep_id");
$stmt->bindParam(':rep_id', $rep_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetch();
$total_commission = $result['total_commission'] ?? 0;

$stmt = $conn->prepare("SELECT SUM(amount) as paid_commission FROM commissions WHERE rep_id = :rep_id AND status = 'paid'");
$stmt->bindParam(':rep_id', $rep_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetch();
$paid_commission = $result['paid_commission'] ?? 0;

$balance = $total_commission - $paid_commission;

$stmt = $conn->prepare("SELECT COUNT(*) as total_orders FROM orders o JOIN commissions c ON o.order_id = c.order_id WHERE c.rep_id = :rep_id");
$stmt->bindParam(':rep_id', $rep_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetch();
$total_orders = $result['total_orders'] ?? 0;

$stmt = $conn->prepare("
    SELECT o.order_id, o.total_amount, o.order_date, o.status, m.name as market_name
    FROM orders o
    JOIN commissions c ON o.order_id = c.order_id
    JOIN products p ON p.product_id = (SELECT product_id FROM order_items WHERE order_id = o.order_id LIMIT 1)
    JOIN markets m ON m.market_id = p.market_id
    WHERE c.rep_id = :rep_id
    ORDER BY o.order_date DESC LIMIT 5
");
$stmt->bindParam(':rep_id', $rep_id, PDO::PARAM_INT);
$stmt->execute();
$recent_sales = $stmt->fetchAll();

$stmt = $conn->prepare("
    SELECT DATE_FORMAT(o.order_date, '%Y-%m') as month, 
           SUM(c.amount) as monthly_commission,
           COUNT(DISTINCT o.order_id) as orders_count
    FROM commissions c
    JOIN orders o ON c.order_id = o.order_id
    WHERE c.rep_id = :rep_id
    GROUP BY DATE_FORMAT(o.order_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
$stmt->bindParam(':rep_id', $rep_id, PDO::PARAM_INT);
$stmt->execute();
$monthly_stats = $stmt->fetchAll();

$stmt = $conn->prepare("
    SELECT c.commission_id, c.amount, c.commission_date, c.status, 
           o.order_id, o.total_amount
    FROM commissions c
    LEFT JOIN orders o ON c.order_id = o.order_id
    WHERE c.rep_id = :rep_id
    ORDER BY c.commission_date DESC
    LIMIT 10
");
$stmt->bindParam(':rep_id', $rep_id, PDO::PARAM_INT);
$stmt->execute();
$commissions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم مندوب المبيعات - أرزاق</title>
    <link rel="stylesheet" href="../admin/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>.referral-link{width:100%;max-width:500px;padding:10px;border-radius:5px;border:1px solid var(--border-color);background:var(--light-gray);cursor:pointer;margin-bottom:15px;}.sales-table{width:100%;border-collapse:collapse;margin-bottom:20px;}.sales-table th,.sales-table td{padding:12px 15px;text-align:right;border-bottom:1px solid var(--border-color);}.sales-table th{background-color:var(--light-gray);font-weight:600;}.sales-table tr:last-child td{border-bottom:none;}.btn{padding:8px 16px;border-radius:5px;background-color:var(--primary-color);color:white;border:none;cursor:pointer;font-weight:600;transition:all 0.3s;}.btn:hover{background-color:#1d7d4a;}.btn-danger{background-color:var(--danger-color);}.btn-danger:hover{background-color:#c0392b;}.balance-card{background:linear-gradient(135deg,var(--primary-color),#1d7d4a);color:white;padding:20px;border-radius:10px;margin-bottom:20px;}.balance-title{font-size:1.1rem;margin-bottom:10px;}.balance-amount{font-size:2rem;font-weight:bold;margin-bottom:15px;}.status-badge{padding:4px 10px;border-radius:20px;font-size:0.8rem;font-weight:600;display:inline-block;}.status-pending{background-color:rgba(243,156,18,0.1);color:var(--warning-color);}.status-processing{background-color:rgba(52,152,219,0.1);color:var(--info-color);}.status-completed{background-color:rgba(46,204,113,0.1);color:var(--success-color);}.status-cancelled{background-color:rgba(231,76,60,0.1);color:var(--danger-color);}</style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>أرزاق</h2>
                <p>لوحة تحكم المندوب</p>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> اللوحة الرئيسية</a>
                <a href="sales_dashboard.php" class="menu-item active"><i class="fas fa-chart-line"></i> لوحة المبيعات</a>
                <a href="referrals.php" class="menu-item"><i class="fas fa-users"></i> إحالاتي</a>
                <a href="reports.php" class="menu-item"><i class="fas fa-file-alt"></i> التقارير</a>
                <a href="profile.php" class="menu-item"><i class="fas fa-user"></i> الملف الشخصي</a>
                <a href="logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
            </div>
        </div>

        <div class="main-content">
            <div class="menu-toggle"><i class="fas fa-bars"></i></div>
            
            <div class="top-bar">
                <div class="admin-info">
                    <div class="user-avatar"><?php echo substr($rep_name, 0, 1); ?></div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($rep_name); ?></div>
                        <div class="user-role">مندوب مبيعات</div>
                    </div>
                </div>
                <div class="top-actions">
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
                </div>
            </div>

            <div class="content-header">
                <h1>لوحة المبيعات</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="dashboard.php">الرئيسية</a></div>
                    <div class="breadcrumb-item">لوحة المبيعات</div>
                </div>
            </div>

            <div class="dashboard-panel">
                <div class="panel-header">
                    <div class="panel-title">رابطك التسويقي</div>
                </div>
                <div class="panel-body">
                    <input type="text" class="referral-link" value="<?php echo htmlspecialchars($referral_link); ?>" readonly onclick="this.select();">
                    <button class="btn" onclick="copyToClipboard('.referral-link')"><i class="fas fa-copy"></i> نسخ الرابط</button>
                </div>
            </div>

            <div class="balance-card">
                <div class="balance-title">الرصيد المتاح للسحب</div>
                <div class="balance-amount"><?php echo number_format($balance, 2); ?> ريال</div>
                <?php if ($balance > 0): ?>
                <form method="post" action="withdraw_request.php">
                    <button type="submit" class="btn">طلب سحب الرصيد</button>
                </form>
                <?php endif; ?>
            </div>

            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon markets"><i class="fas fa-link"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo number_format($visitor_count); ?></div>
                        <div class="stat-label">زوار الرابط</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon users"><i class="fas fa-users"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo number_format($registered_users); ?></div>
                        <div class="stat-label">العملاء المسجلين</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon markets"><i class="fas fa-store"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo number_format($registered_markets); ?></div>
                        <div class="stat-label">المتاجر المسجلة</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orders"><i class="fas fa-shopping-cart"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo number_format($total_orders); ?></div>
                        <div class="stat-label">إجمالي الطلبات</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon products"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo number_format($total_commission, 2); ?></div>
                        <div class="stat-label">إجمالي العمولات</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-panels">
                <div class="dashboard-panel">
                    <div class="panel-header">
                        <div class="panel-title">آخر المبيعات</div>
                        <a href="all_sales.php" class="view-all">عرض الكل <i class="fas fa-chevron-left"></i></a>
                    </div>
                    <div class="panel-body recent-orders">
                        <table class="sales-table">
                            <thead>
                                <tr>
                                    <th>رقم الطلب</th>
                                    <th>المتجر</th>
                                    <th>المبلغ</th>
                                    <th>التاريخ</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_sales)): ?>
                                    <?php foreach ($recent_sales as $sale): ?>
                                    <tr>
                                        <td>#<?php echo htmlspecialchars($sale['order_id']); ?></td>
                                        <td><?php echo htmlspecialchars($sale['market_name']); ?></td>
                                        <td><?php echo number_format($sale['total_amount'], 2); ?> ريال</td>
                                        <td><?php echo date('Y/m/d', strtotime($sale['order_date'])); ?></td>
                                        <td>
                                            <?php 
                                                $statusClass = '';
                                                $statusText = '';
                                                switch($sale['status']) {
                                                    case 'pending': 
                                                        $statusClass = 'status-pending'; 
                                                        $statusText = 'قيد الانتظار'; 
                                                        break;
                                                    case 'processing': 
                                                        $statusClass = 'status-processing'; 
                                                        $statusText = 'قيد المعالجة'; 
                                                        break;
                                                    case 'completed': 
                                                        $statusClass = 'status-completed'; 
                                                        $statusText = 'مكتمل'; 
                                                        break;
                                                    case 'cancelled': 
                                                        $statusClass = 'status-cancelled'; 
                                                        $statusText = 'ملغي'; 
                                                        break;
                                                    default: 
                                                        $statusClass = ''; 
                                                        $statusText = htmlspecialchars($sale['status']);
                                                }
                                            ?>
                                            <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center;">لا توجد مبيعات حتى الآن</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="dashboard-panel">
                    <div class="panel-header">
                        <div class="panel-title">إحصائيات شهرية</div>
                    </div>
                    <div class="panel-body">
                        <?php if (!empty($monthly_stats)): ?>
                        <table class="sales-table">
                            <thead>
                                <tr>
                                    <th>الشهر</th>
                                    <th>الطلبات</th>
                                    <th>العمولات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthly_stats as $stat): ?>
                                    <?php 
                                    $date = date_create($stat['month'] . '-01');
                                    $formatted_month = strftime('%B %Y', $date->getTimestamp());
                                    ?>
                                    <tr>
                                        <td><?php echo $formatted_month; ?></td>
                                        <td><?php echo number_format($stat['orders_count']); ?></td>
                                        <td><?php echo number_format($stat['monthly_commission'], 2); ?> ريال</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p>لا توجد إحصائيات شهرية متاحة</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-panel">
                <div class="panel-header">
                    <div class="panel-title">بيانات العمولات</div>
                    <a href="all_commissions.php" class="view-all">عرض الكل <i class="fas fa-chevron-left"></i></a>
                </div>
                <div class="panel-body">
                    <?php if (!empty($commissions)): ?>
                    <table class="sales-table">
                        <thead>
                            <tr>
                                <th>رقم العمولة</th>
                                <th>رقم الطلب</th>
                                <th>المبلغ</th>
                                <th>التاريخ</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commissions as $commission): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($commission['commission_id']); ?></td>
                                    <td><?php echo $commission['order_id'] ? '#' . htmlspecialchars($commission['order_id']) : 'غير متصل بطلب'; ?></td>
                                    <td><?php echo number_format($commission['amount'], 2); ?> ريال</td>
                                    <td><?php echo date('Y/m/d', strtotime($commission['commission_date'])); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $commission['status'] == 'paid' ? 'status-completed' : 'status-pending'; ?>">
                                            <?php echo $commission['status'] == 'paid' ? 'مدفوع' : 'قيد الانتظار'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p>لا توجد بيانات عمولات متاحة</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>function copyToClipboard(element){const el=document.querySelector(element);el.select();document.execCommand("copy");alert("تم نسخ الرابط!");} document.addEventListener("DOMContentLoaded",function(){const menuToggle=document.querySelector(".menu-toggle");const sidebar=document.querySelector(".sidebar");menuToggle.addEventListener("click",function(){sidebar.classList.toggle("active");});});</script>
</body>
</html>