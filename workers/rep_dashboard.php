<?php
session_start();
include 'db.php';
if (!isset($_SESSION['rep_id'])) {
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
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم المندوب - أرزاق بلس</title>
</head>
<body>
    <h1>لوحة تحكم المندوب</h1>
    <p>مرحباً <?php echo htmlspecialchars($rep_name); ?></p>
    <h2>رابطك التسويقي</h2>
    <input type="text" value="<?php echo htmlspecialchars($referral_link); ?>" readonly onclick="this.select();" style="width:80%;">
    <button onclick="copyToClipboard(this.previousElementSibling)">نسخ الرابط</button>
    <h2>إحصائيات عامة</h2>
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
    <h2>بيانات العمولات</h2>
    <?php
    $stmt = $conn->prepare("
        SELECT c.commission_id, c.amount, c.commission_date, c.status, o.order_id, o.total_amount
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
    <?php if (!empty($commissions)): ?>
    <table border="1" style="width:100%;">
        <tr>
            <th>رقم العمولة</th>
            <th>رقم الطلب</th>
            <th>المبلغ</th>
            <th>التاريخ</th>
            <th>الحالة</th>
        </tr>
        <?php foreach ($commissions as $commission): ?>
            <tr>
                <td><?php echo htmlspecialchars($commission['commission_id']); ?></td>
                <td><?php echo htmlspecialchars($commission['order_id'] ?? 'غير متصل بطلب'); ?></td>
                <td><?php echo number_format($commission['amount'], 2); ?> ريال</td>
                <td><?php echo date('Y/m/d', strtotime($commission['commission_date'])); ?></td>
                <td><?php echo $commission['status'] == 'paid' ? 'مدفوع' : 'قيد الانتظار'; ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p>لا توجد بيانات عمولات متاحة</p>
    <?php endif; ?>
    <p><a href="logout.php">تسجيل الخروج</a></p>
    <script>
    function copyToClipboard(element) {
        element.select();
        document.execCommand("copy");
        alert("تم نسخ الرابط!");
    }
    </script>
</body>
</html>