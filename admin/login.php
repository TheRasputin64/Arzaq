<?php
session_start();
include 'db.php';
if (isset($_SESSION['admin_id'])) {header('Location: dashboard.php');exit();}
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $stmt = $conn->prepare("SELECT admin_id, username, password FROM admin WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['admin_id'] = $row['admin_id'];
            $_SESSION['admin_username'] = $row['username'];
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'كلمة المرور غير صحيحة';
        }
    } else {
        $error = 'اسم المستخدم غير موجود';
    }
    $stmt->close();
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header("Location: login.php?error=" . urlencode($error));
    exit;
}
if (isset($_GET['error']) && !empty($_GET['error'])) {
    $error = $_GET['error'];
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم أرزاق - تسجيل الدخول</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../ui/logo.png">
    <style>
        * {margin: 0; padding: 0; box-sizing: border-box;}
        body {font-family: 'Cairo', sans-serif; direction: rtl; background-color: #edf2f7; height: 100vh; display: flex; align-items: center; justify-content: center;}
        .login-container {width: 100%; max-width: 900px; display: flex; background-color: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(37, 153, 92, 0.15); overflow: hidden;}
        .login-image {width: 45%; background-color: #f1f5fd; display: flex; align-items: center; justify-content: center; padding: 20px; color: #25995C; border-left: 1px solid #eaeaea;}
        .login-content {width: 55%;}
        .login-header {background-color: #25995C; color: white; padding: 20px; text-align: center;}
        .login-header h1 {font-size: 1.8rem; margin-bottom: 5px; text-shadow: 0 2px 4px rgba(0,0,0,0.1);}
        .login-header p {font-size: 1rem; opacity: 0.9;}
        .login-form {padding: 25px;}
        .form-group {margin-bottom: 20px; position: relative;}
        .form-group label {display: block; margin-bottom: 8px; font-weight: bold; color: #333; font-size: 1rem;}
        .input-icon {position: relative;}
        .input-icon i {position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #25995C; font-size: 1.1rem;}
        .form-control {width: 100%; padding: 12px 18px; padding-left: 45px; border: 2px solid #e5e5e5; border-radius: 10px; font-family: 'Cairo', sans-serif; font-size: 1rem; transition: all 0.3s;}
        .form-control:focus {outline: none; border-color: #25995C; box-shadow: 0 0 0 3px rgba(37, 153, 92, 0.15);}
        .error-input {border-color: #e74c3c !important;}
        .login-btn {width: 100%; padding: 12px 0; background-color: #25995C; color: white; border: none; border-radius: 10px; font-family: 'Cairo', sans-serif; font-weight: bold; font-size: 1.1rem; cursor: pointer; transition: all 0.3s; box-shadow: 0 6px 12px rgba(37, 153, 92, 0.2); display: flex; align-items: center; justify-content: center; gap: 10px;}
        .login-btn:hover {background-color: #1c7a48; transform: translateY(-2px);}
        .login-btn:active {transform: translateY(0);}
        .error-msg {color: #e74c3c; background-color: #fdecea; border-radius: 8px; padding: 10px; margin-bottom: 15px; text-align: center; font-size: 0.9rem; border-right: 3px solid #e74c3c;}
        .footer {text-align: center; margin-top: 20px; font-size: 0.8rem; color: #777;}
        @keyframes shake {
            0%, 100% {transform: translateX(0);}
            10%, 30%, 50%, 70%, 90% {transform: translateX(-5px);}
            20%, 40%, 60%, 80% {transform: translateX(5px);}
        }
        .shake {animation: shake 0.4s;}
        .logo-wrapper {text-align: center;}
        .logo-wrapper i {color: #25995C;}
        
        @media (max-width: 768px) {
            .login-container {max-width: 450px;}
            .login-image {display: none;}
            .login-content {width: 100%;}
        }
        
        @media (max-width: 576px) {
            .login-container {width: 95%;}
            .login-form {padding: 20px 15px;}
            .login-header {padding: 15px;}
            .login-header h1 {font-size: 1.5rem;}
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-content">
            <div class="login-header">
                <h1>لوحة تحكم أرزاق</h1>
                <p>تسجيل دخول المسؤول</p>
            </div>
            <div class="login-form">
                <?php if (!empty($error)): ?>
                    <div class="error-msg shake">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                <form action="login.php" method="post" id="loginForm">
                    <div class="form-group">
                        <label for="username">اسم المستخدم</label>
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" class="form-control <?php echo (!empty($error) && $error == 'اسم المستخدم غير موجود') ? 'error-input' : ''; ?>" id="username" name="username" required autocomplete="off">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="password">كلمة المرور</label>
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" class="form-control <?php echo (!empty($error) && $error == 'كلمة المرور غير صحيحة') ? 'error-input' : ''; ?>" id="password" name="password" required>
                        </div>
                    </div>
                    <button type="submit" class="login-btn">تسجيل الدخول <i class="fas fa-sign-in-alt"></i></button>
                </form>
                <div class="footer">
                    &copy; <?php echo date('Y'); ?> أرزاق - منصة التسوق الأمثل
                </div>
            </div>
        </div>
        <div class="login-image">
            <div class="logo-wrapper">
                <i class="fas fa-shopping-basket" style="font-size: 6rem;"></i>
                <div style="margin-top: 15px; font-weight: bold; font-size: 1.5rem; color: #333;">أرزاق</div>
                <div style="margin-top: 10px; color: #666; font-size: 0.9rem;">منصة التسوق الأمثل</div>
            </div>
        </div>
    </div>
    <script>document.addEventListener('DOMContentLoaded', function() {const errorMsg = document.querySelector('.error-msg'); if (errorMsg) {errorMsg.classList.add('shake'); setTimeout(() => errorMsg.classList.remove('shake'), 500);} const error = "<?php echo $error; ?>"; if (error === 'كلمة المرور غير صحيحة') {document.getElementById('password').classList.add('error-input');} else if (error === 'اسم المستخدم غير موجود') {document.getElementById('username').classList.add('error-input');} const inputs = document.querySelectorAll('.form-control'); inputs.forEach(input => {input.addEventListener('focus', function() {this.classList.remove('error-input');});});});</script>
</body>
</html>