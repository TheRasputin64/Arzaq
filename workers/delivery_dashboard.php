<?php
session_start();
include 'db.php';

if (!isset($_SESSION['rep_id']) || $_SESSION['rep_type'] != 'delivery') {
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
$referral_link = "https://arzaqplus.com/register?ref=" . $referral_code;

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
    SELECT o.order_id, o.total_amount, o.order_date, o.status, u.name as user_name, u.phone as user_phone, u.address 
    FROM orders o
    JOIN commissions c ON o.order_id = c.order_id
    JOIN users u ON o.user_id = u.user_id
    WHERE c.rep_id = :rep_id AND o.status IN ('processing', 'shipped')
    ORDER BY o.order_date DESC
");
$stmt->bindParam(':rep_id', $rep_id, PDO::PARAM_INT);
$stmt->execute();
$active_deliveries = $stmt->fetchAll();

$stmt = $conn->prepare("
    SELECT o.order_id, o.total_amount, o.order_date, o.status, u.name as user_name
    FROM orders o
    JOIN commissions c ON o.order_id = c.order_id
    JOIN users u ON o.user_id = u.user_id
    WHERE c.rep_id = :rep_id AND o.status = 'completed'
    ORDER BY o.order_date DESC LIMIT 10
");
$stmt->bindParam(':rep_id', $rep_id, PDO::PARAM_INT);
$stmt->execute();
$completed_deliveries = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم مندوب التوصيل - أرزاق بلس</title>
</head>
<body>
    <h1>لوحة تحكم مندوب التوصيل</h1>
    <p>مرحباً <?php echo htmlspecialchars($rep_name); ?></p>

    <h2>رابطك التسويقي</h2>
    <input type="text" value="<?php echo htmlspecialchars($referral_link); ?>" readonly onclick="this.select();" style="width:80%;">
    <button onclick="copyToClipboard(this.previousElementSibling)">نسخ الرابط</button>

    <h2>الطلبات النشطة للتوصيل</h2>
    <?php if (!empty($active_deliveries)): ?>
    <table border="1" style="width:100%;">
        <tr>
            <th>رقم الطلب</th>
            <th>اسم العميل</th>
            <th>رقم الهاتف</th>
            <th>العنوان</th>
            <th>المبلغ</th>
            <th>التاريخ</th>
            <th>الحالة</th>
            <th>الإجراءات</th>
        </tr>
        <?php foreach ($active_deliveries as $delivery): ?>
        <tr>
            <td><?php echo htmlspecialchars($delivery['order_id']); ?></td>
            <td><?php echo htmlspecialchars($delivery['user_name']); ?></td>
            <td><?php echo htmlspecialchars($delivery['user_phone']); ?></td>
            <td><?php echo htmlspecialchars($delivery['address']); ?></td>
            <td><?php echo number_format($delivery['total_amount'], 2); ?> ريال</td>
            <td><?php echo date('Y/m/d', strtotime($delivery['order_date'])); ?></td>
            <td><?php 
                switch($delivery['status']) {
                    case 'processing': echo 'قيد المعالجة'; break;
                    case 'shipped': echo 'قيد التوصيل'; break;
                    default: echo htmlspecialchars($delivery['status']);
                }
            ?></td>
            <td>
                <form method="post" action="update_delivery.php">
                    <input type="hidden" name="order_id" value="<?php echo $delivery['order_id']; ?>">
                    <?php if ($delivery['status'] == 'processing'): ?>
                    <button type="submit" name="action" value="ship">بدء التوصيل</button>
                    <?php elseif ($delivery['status'] == 'shipped'): ?>
                    <button type="submit" name="action" value="complete">تم التوصيل</button>
                    <?php endif; ?>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p>لا توجد طلبات نشطة للتوصيل حالياً</p>
    <?php endif; ?>

    <h2>الطلبات المكتملة</h2>
    <?php if (!empty($completed_deliveries)): ?>
    <table border="1" style="width:100%;">
        <tr>
            <th>رقم الطلب</th>
            <th>اسم العميل</th>
            <th>المبلغ</th>
            <th>تاريخ الطلب</th>
            <th>تاريخ التوصيل</th>
        </tr>
        <?php foreach ($completed_deliveries as $delivery): ?>
        <tr>
            <td><?php echo htmlspecialchars($delivery['order_id']); ?></td>
            <td><?php echo htmlspecialchars($delivery['user_name']); ?></td>
            <td><?php echo number_format($delivery['total_amount'], 2); ?> ريال</td>
            <td><?php echo date('Y/m/d', strtotime($delivery['order_date'])); ?></td>
            <td>
                <?php 
                $stmt = $conn->prepare("SELECT payment_date FROM orders WHERE order_id = :order_id AND status = 'completed'");
                $stmt->bindParam(':order_id', $delivery['order_id'], PDO::PARAM_INT);
                $stmt->execute();
                $completion_date = $stmt->fetch()['payment_date'];
                echo $completion_date ? date('Y/m/d', strtotime($completion_date)) : 'غير متوفر';
                ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p>لا توجد طلبات مكتملة</p>
    <?php endif; ?>

    <h2>إحصائيات المندوب</h2>
    <table border="1" style="width:100%;">
        <tr>
            <th>البند</th>
            <th>العدد/المبلغ</th>
        </tr>
        <tr>
            <td>عدد زوار الرابط</td>
            <td><?php echo number_format($visitor_count); ?></td>
        </tr>
        <tr>
            <td>عدد العملاء المسجلين</td>
            <td><?php echo number_format($registered_users); ?></td>
        </tr>
        <tr>
            <td>عدد المتاجر المسجلة</td>
            <td><?php echo number_format($registered_markets); ?></td>
        </tr>
        <tr>
            <td>إجمالي العمولة المستحقة</td>
            <td><?php echo number_format($total_commission, 2); ?> ريال</td>
        </tr>
        <tr>
            <td>المبلغ المسحوب</td>
            <td><?php echo number_format($paid_commission, 2); ?> ريال</td>
        </tr>
        <tr>
            <td>الرصيد المتاح</td>
            <td><?php echo number_format($balance, 2); ?> ريال</td>
        </tr>
    </table>

    <?php if ($balance > 0): ?>
    <form method="post" action="withdraw_request.php">
        <button type="submit">طلب سحب الرصيد</button>
    </form>
    <?php endif; ?>

    <p><a href="dashboard.php">الرجوع للوحة التحكم الرئيسية</a> | <a href="logout.php">تسجيل الخروج</a></p>

    <script>
    function copyToClipboard(element) {
        element.select();
        document.execCommand("copy");
        alert("تم نسخ الرابط!");
    }
    </script>
</body>
</html>