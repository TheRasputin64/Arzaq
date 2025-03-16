<?php
session_start();
include 'db.php';

if (!isset($_SESSION['rep_id']) || $_SESSION['rep_type'] != 'purchasing') {
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

$stmt = $conn->prepare("
    SELECT o.order_id, o.total_amount, o.order_date, o.status, u.name as user_name
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    JOIN referrals r ON u.user_id = r.user_id
    WHERE r.rep_id = :rep_id
    ORDER BY o.order_date DESC LIMIT 5
");
$stmt->bindParam(':rep_id', $rep_id, PDO::PARAM_INT);
$stmt->execute();
$recent_purchases = $stmt->fetchAll();

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.user_id) as active_users
    FROM users u
    JOIN referrals r ON u.user_id = r.user_id
    JOIN orders o ON u.user_id = o.user_id
    WHERE r.rep_id = :rep_id
");
$stmt->bindParam(':rep_id', $rep_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetch();
$active_users = $result['active_users'] ?? 0;

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
    SELECT u.name, COUNT(o.order_id) as order_count, SUM(o.total_amount) as total_spent
    FROM users u
    JOIN referrals r ON u.user_id = r.user_id
    JOIN orders o ON u.user_id = o.user_id
    WHERE r.rep_id = :rep_id
    GROUP BY u.user_id
    ORDER BY total_spent DESC
    LIMIT 10
");
$stmt->bindParam(':rep_id', $rep_id, PDO::PARAM_INT);
$stmt->execute();
$top_users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم مندوب المشتريات - أرزاق</title>
    <link rel="stylesheet" href="../admin/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>أرزاق</h2>
                <p>لوحة تحكم المندوب</p>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item active"><i class="fas fa-tachometer-alt"></i>الرئيسية</a>
                <a href="customers.php" class="menu-item"><i class="fas fa-users"></i>العملاء</a>
                <a href="markets.php" class="menu-item"><i class="fas fa-store"></i>المتاجر</a>
                <a href="commissions.php" class="menu-item"><i class="fas fa-money-bill-wave"></i>العمولات</a>
                <a href="profile.php" class="menu-item"><i class="fas fa-user-cog"></i>الملف الشخصي</a>
                <a href="logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i>تسجيل الخروج</a>
            </div>
        </div>
        <div class="main-content">
            <div class="menu-toggle" id="menu-toggle"><i class="fas fa-bars"></i></div>
            <div class="top-bar">
                <div class="admin-info">
                    <div class="user-avatar"><?php echo substr($rep_name, 0, 1); ?></div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($rep_name); ?></div>
                        <div class="user-role">مندوب مشتريات</div>
                    </div>
                </div>
                <div class="top-actions">
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i>تسجيل الخروج</a>
                </div>
            </div>
            
            <div class="content-header">
                <h1>لوحة تحكم مندوب المشتريات</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="dashboard.php">الرئيسية</a></div>
                    <div class="breadcrumb-item">لوحة التحكم</div>
                </div>
            </div>
            
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon markets"><i class="fas fa-users"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo number_format($visitor_count); ?></div>
                        <div class="stat-label">عدد زوار الرابط</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon users"><i class="fas fa-user-check"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo number_format($registered_users); ?></div>
                        <div class="stat-label">العملاء المسجلين</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon products"><i class="fas fa-store"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo number_format($registered_markets); ?></div>
                        <div class="stat-label">المتاجر المسجلة</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orders"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo number_format($active_users); ?></div>
                        <div class="stat-label">العملاء النشطين</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon categories"><i class="fas fa-wallet"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo number_format($balance, 2); ?></div>
                        <div class="stat-label">الرصيد المتاح (ريال)</div>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-panel">
                <div class="panel-header">
                    <div class="panel-title">رابطك التسويقي</div>
                </div>
                <div class="panel-body">
                    <div style="display:flex; gap:10px;">
                        <input type="text" value="<?php echo htmlspecialchars($referral_link); ?>" readonly onclick="this.select();" style="flex:1; padding:10px; border:1px solid var(--border-color); border-radius:5px;">
                        <button onclick="copyToClipboard(this.previousElementSibling)" style="background:var(--primary-color); color:white; border:none; border-radius:5px; padding:10px 15px; cursor:pointer;">نسخ الرابط</button>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-panels">
                <div class="dashboard-panel recent-orders">
                    <div class="panel-header">
                        <div class="panel-title">آخر المشتريات</div>
                    </div>
                    <div class="panel-body">
                        <table>
                            <thead>
                                <tr>
                                    <th>رقم الطلب</th>
                                    <th>العميل</th>
                                    <th>المبلغ</th>
                                    <th>التاريخ</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_purchases)): ?>
                                    <?php foreach ($recent_purchases as $purchase): ?>
                                    <tr>
                                        <td>#<?php echo htmlspecialchars($purchase['order_id']); ?></td>
                                        <td><?php echo htmlspecialchars($purchase['user_name']); ?></td>
                                        <td><?php echo number_format($purchase['total_amount'], 2); ?> ريال</td>
                                        <td><?php echo date('Y/m/d', strtotime($purchase['order_date'])); ?></td>
                                        <td>
                                            <span class="status <?php echo $purchase['status']; ?>">
                                                <?php 
                                                    switch($purchase['status']) {
                                                        case 'pending': echo 'قيد الانتظار'; break;
                                                        case 'processing': echo 'قيد المعالجة'; break;
                                                        case 'completed': echo 'مكتمل'; break;
                                                        case 'cancelled': echo 'ملغي'; break;
                                                        default: echo htmlspecialchars($purchase['status']);
                                                    }
                                                ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center;">لا توجد مشتريات حتى الآن</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="panel-footer">
                        <a href="orders.php" class="view-all">عرض جميع الطلبات <i class="fas fa-arrow-left"></i></a>
                    </div>
                </div>
                
                <div class="dashboard-panel">
                    <div class="panel-header">
                        <div class="panel-title">ملخص العمولات</div>
                    </div>
                    <div class="panel-body">
                        <ul class="top-products">
                            <li>
                                <span class="product-name">إجمالي العمولة المستحقة</span>
                                <span class="product-count"><?php echo number_format($total_commission, 2); ?> ريال</span>
                            </li>
                            <li>
                                <span class="product-name">المبلغ المسحوب</span>
                                <span class="product-count"><?php echo number_format($paid_commission, 2); ?> ريال</span>
                            </li>
                            <li>
                                <span class="product-name">الرصيد المتاح</span>
                                <span class="product-count"><?php echo number_format($balance, 2); ?> ريال</span>
                            </li>
                        </ul>
                        <?php if ($balance > 0): ?>
                        <div style="margin-top:15px; text-align:center;">
                            <form method="post" action="withdraw_request.php">
                                <button type="submit" style="background:var(--primary-color); color:white; border:none; border-radius:5px; padding:10px 15px; cursor:pointer;">طلب سحب الرصيد</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-panel">
                <div class="panel-header">
                    <div class="panel-title">تحليل أداء المستخدمين</div>
                </div>
                <div class="panel-body">
                   <?php if (!empty($top_users)): ?>
                   <table>
                       <thead>
                           <tr>
                               <th>اسم العميل</th>
                               <th>عدد الطلبات</th>
                               <th>إجمالي المشتريات</th>
                           </tr>
                       </thead>
                       <tbody>
                           <?php foreach ($top_users as $user): ?>
                               <tr>
                                   <td><?php echo htmlspecialchars($user['name']); ?></td>
                                   <td><?php echo number_format($user['order_count']); ?></td>
                                   <td><?php echo number_format($user['total_spent'], 2); ?> ريال</td>
                               </tr>
                           <?php endforeach; ?>
                       </tbody>
                   </table>
                   <?php else: ?>
                   <p style="text-align:center;">لا توجد بيانات متاحة</p>
                   <?php endif; ?>
               </div>
               <div class="panel-footer">
                   <a href="users.php" class="view-all">عرض جميع العملاء <i class="fas fa-arrow-left"></i></a>
               </div>
           </div>
           
           <div class="dashboard-panel">
               <div class="panel-header">
                   <div class="panel-title">إحصائيات شهرية</div>
               </div>
               <div class="panel-body">
                   <?php if (!empty($monthly_stats)): ?>
                   <table>
                       <thead>
                           <tr>
                               <th>الشهر</th>
                               <th>عدد الطلبات</th>
                               <th>إجمالي العمولات</th>
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
                   <p style="text-align:center;">لا توجد إحصائيات شهرية متاحة</p>
                   <?php endif; ?>
               </div>
               <div class="panel-footer">
                   <a href="stats.php" class="view-all">عرض التقارير الكاملة <i class="fas fa-arrow-left"></i></a>
               </div>
           </div>
       </div>
   </div>
   <script>document.getElementById('menu-toggle').addEventListener('click', function() { document.getElementById('sidebar').classList.toggle('active'); });</script>
</body>
</html>