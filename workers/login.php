<?php
session_start();
require_once 'db.php';

// Redirect if already logged in
if (isset($_SESSION['rep_id'])) {
    $rep_type = $_SESSION['rep_type'];
    header('Location: ' . $rep_type . '_dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    
    if (empty($phone) || empty($password)) {
        $error = "جميع الحقول مطلوبة";
    } else {
        try {
            $stmt = $conn->prepare("SELECT rep_id, name, password, rep_type FROM representatives WHERE phone = ?");
            $stmt->execute([$phone]);
            $rep = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($rep && password_verify($password, $rep['password'])) {
                $_SESSION['rep_id'] = $rep['rep_id'];
                $_SESSION['rep_name'] = $rep['name'];
                $_SESSION['rep_type'] = $rep['rep_type'];
                
                // Redirect based on representative type
                switch ($rep['rep_type']) {
                    case 'sales':
                        header('Location: sales_dashboard.php');
                        break;
                    case 'purchasing':
                        header('Location: purchasing_dashboard.php');
                        break;
                    case 'delivery':
                        header('Location: delivery_dashboard.php');
                        break;
                    case 'service_provider':
                        header('Location: service_dashboard.php');
                        break;
                    case 'employee':
                        header('Location: employee_dashboard.php');
                        break;
                    default:
                        header('Location: rep_dashboard.php');
                        break;
                }
                exit();
            } else {
                $error = "رقم الهاتف أو كلمة المرور غير صحيحة";
            }
        } catch (PDOException $e) {
            $error = "حدث خطأ أثناء تسجيل الدخول. الرجاء المحاولة مرة أخرى";
        }
    }
}

if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>منصة أرزاق بلس - تسجيل الدخول</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {margin: 0; padding: 0; box-sizing: border-box;}
        body {font-family: 'Cairo', sans-serif; direction: rtl; background-color: #edf2f7; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;}
        .login-container {width: 100%; max-width: 800px; display: flex; background-color: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(37, 153, 92, 0.15); overflow: hidden; margin: 20px auto;}
        .login-image {width: 40%; background-color: #f1f5fd; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 30px; color: #25995C; border-left: 1px solid #eaeaea;}
        .login-image h2 {margin-bottom: 15px; font-size: 1.8rem; color: #25995C;}
        .login-image p {text-align: center; line-height: 1.6;}
        .login-content {width: 60%;}
        .login-header {background-color: #25995C; color: white; padding: 25px; text-align: center;}
        .login-header h1 {font-size: 2rem; margin-bottom: 8px; text-shadow: 0 2px 4px rgba(0,0,0,0.1);}
        .login-header p {font-size: 1.1rem; opacity: 0.9;}
        .login-form {padding: 30px;}
        .form-group {margin-bottom: 25px; position: relative;}
        .form-group label {display: block; margin-bottom: 10px; font-weight: bold; color: #333; font-size: 1.1rem;}
        .input-icon {position: relative;}
        .input-icon i {position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #25995C; font-size: 1.1rem;}
        .form-control {width: 100%; padding: 14px 20px; padding-left: 45px; border: 2px solid #e5e5e5; border-radius: 10px; font-family: 'Cairo', sans-serif; font-size: 1rem; transition: all 0.3s;}
        .form-control:focus {outline: none; border-color: #25995C; box-shadow: 0 0 0 3px rgba(37, 153, 92, 0.15);}
        .error-input {border-color: #e74c3c !important;}
        .btn {width: 100%; padding: 14px 0; background-color: #25995C; color: white; border: none; border-radius: 10px; font-family: 'Cairo', sans-serif; font-weight: bold; font-size: 1.1rem; cursor: pointer; transition: all 0.3s; box-shadow: 0 6px 12px rgba(37, 153, 92, 0.2); display: flex; align-items: center; justify-content: center; gap: 10px;}
        .btn:hover {background-color: #1c7a48; transform: translateY(-2px);}
        .btn:active {transform: translateY(0);}
        .error-msg {color: #e74c3c; background-color: #fdecea; border-radius: 8px; padding: 12px 15px; margin-bottom: 20px; text-align: center; font-size: 1rem; border-right: 4px solid #e74c3c;}
        .success-msg {color: #2ecc71; background-color: #e8f8f2; border-radius: 8px; padding: 12px 15px; margin-bottom: 20px; text-align: center; font-size: 1rem; border-right: 4px solid #2ecc71;}
        .footer {text-align: center; margin-top: 25px; font-size: 0.9rem; color: #777;}
        .footer a {color: #25995C; text-decoration: none; font-weight: bold;}
        .footer a:hover {text-decoration: underline;}
        .logo-wrapper {text-align: center; margin-bottom: 30px;}
        .logo-wrapper i {color: #25995C; font-size: 3rem; margin-bottom: 10px;}
        .logo-wrapper h3 {color: #25995C; font-size: 1.5rem;}
        .forgot-password {text-align: left; margin-top: -15px; margin-bottom: 20px;}
        .forgot-password a {color: #25995C; text-decoration: none; font-size: 0.9rem;}
        .forgot-password a:hover {text-decoration: underline;}
        
        @media (max-width: 768px) {
            .login-container {flex-direction: column; max-width: 600px;}
            .login-image {width: 100%; border-left: none; border-bottom: 1px solid #eaeaea;}
            .login-content {width: 100%;}
        }
        
        @media (max-width: 576px) {
            .login-container {width: 95%;}
            .login-form {padding: 20px 15px;}
            .login-header {padding: 20px;}
            .login-header h1 {font-size: 1.6rem;}
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-image">
            <div class="logo-wrapper">
                <i class="fas fa-store"></i>
                <h3>أرزاق بلس</h3>
            </div>
            <h2>مرحباً بعودتك</h2>
            <p>قم بتسجيل الدخول للوصول إلى حسابك وإدارة أعمالك بكل سهولة</p>
            <div style="margin-top: 30px;">
                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                    <i class="fas fa-check-circle" style="color: #25995C; margin-left: 10px;"></i>
                    <span>تتبع المبيعات والعمولات</span>
                </div>
                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                    <i class="fas fa-check-circle" style="color: #25995C; margin-left: 10px;"></i>
                    <span>إدارة الإحالات</span>
                </div>
                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                    <i class="fas fa-check-circle" style="color: #25995C; margin-left: 10px;"></i>
                    <span>متابعة الطلبات</span>
                </div>
            </div>
        </div>
        <div class="login-content">
            <div class="login-header">
                <h1>تسجيل الدخول</h1>
                <p>أدخل بيانات حسابك للوصول إلى لوحة التحكم</p>
            </div>
            <div class="login-form">
                <?php if(!empty($error)): ?>
                    <div class="error-msg">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if(!empty($success)): ?>
                    <div class="success-msg">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <div class="form-group">
                        <label for="phone">رقم الهاتف</label>
                        <div class="input-icon">
                            <input type="tel" id="phone" name="phone" class="form-control" placeholder="أدخل رقم الهاتف" required>
                            <i class="fas fa-phone"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="password">كلمة المرور</label>
                        <div class="input-icon">
                            <input type="password" id="password" name="password" class="form-control" placeholder="أدخل كلمة المرور" required>
                            <i class="fas fa-lock"></i>
                        </div>
                    </div>
                    
                    <div class="forgot-password">
                        <a href="reset_password.php">نسيت كلمة المرور؟</a>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-sign-in-alt"></i>
                        تسجيل الدخول
                    </button>
                </form>
                
                <div class="footer">
                    <p>ليس لديك حساب؟ <a href="select.php">تسجيل حساب جديد</a></p>
                    <p style="margin-top: 10px">منصة أرزاق بلس &copy; <?php echo date('Y'); ?> - جميع الحقوق محفوظة</p>
                </div>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const phoneField = document.getElementById('phone');
        if (phoneField) {
            phoneField.addEventListener('input', function() {
                const phonePattern = /^\d{10,15}$/;
                if (!phonePattern.test(this.value.replace(/\s/g, ''))) {
                    this.classList.add('error-input');
                } else {
                    this.classList.remove('error-input');
                }
            });
        }
        
        const passwordField = document.getElementById('password');
        if (passwordField) {
            passwordField.addEventListener('input', function() {
                if (this.value.length < 6) {
                    this.classList.add('error-input');
                } else {
                    this.classList.remove('error-input');
                }
            });
        }
    });
    </script>
</body>
</html>