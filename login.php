<?php
session_start();
require_once 'db.php';
require 'market/PHPMailer/src/Exception.php';
require 'market/PHPMailer/src/PHPMailer.php';
require 'market/PHPMailer/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
function generateOTP() {return rand(1000, 9999);}
function sendOTP($email, $otp) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'bodysameh2006@gmail.com';
        $mail->Password = 'wriq tazo iphn djrk';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom('bodysameh2006@gmail.com', 'أرزاق بلس');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = "رمز التحقق من أرزاق بلس";
        $mail->Body = '
        <!DOCTYPE html>
        <html dir="rtl" lang="ar">
        <head>
            <meta charset="UTF-8">
            <style>
                .container {max-width: 600px; margin: 0 auto; padding: 20px;}
                .header {background-color: #25995C; color: white; text-align: center; padding: 20px;}
                .content {background-color: #f9f9f9; padding: 30px; text-align: center;}
                .logo {font-size: 24px; font-weight: bold; margin-bottom: 10px;}
                .verification-code {font-size: 32px; font-weight: bold; color: #25995C; letter-spacing: 5px; margin: 20px 0; padding: 10px; background-color: #ffffff; border-radius: 10px; display: inline-block;}
                .footer {margin-top: 30px; font-size: 12px; color: #777777; text-align: center;}
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="logo">أرزاق بلس</div>
                    <p>منصة التجارة الإلكترونية الموثوقة</p>
                </div>
                <div class="content">
                    <h2>رمز التحقق الخاص بك</h2>
                    <p>أهلاً بك في أرزاق بلس، الرجاء استخدام الرمز التالي لإكمال عملية التحقق:</p>
                    <div class="verification-code">' . $otp . '</div>
                    <p>هذا الرمز صالح لمدة 10 دقائق فقط.</p>
                    <p>إذا لم تقم بطلب هذا الرمز، يرجى تجاهل هذا البريد الإلكتروني.</p>
                </div>
                <div class="footer">
                    <p>© ' . date('Y') . ' أرزاق بلس - جميع الحقوق محفوظة</p>
                </div>
            </div>
        </body>
        </html>
        ';
        $mail->AltBody = "رمز التحقق الخاص بك هو: $otp - أرزاق بلس";
        $mail->send();
        return true;
    } catch (Exception $e) {return false;}
}
function validateEmail($email) {return filter_var($email, FILTER_VALIDATE_EMAIL);}
function validatePhone($phone) {
    $phone = trim($phone);
    if (substr($phone, 0, 3) === '+20' && strlen($phone) === 13) {
        return true;
    } elseif (substr($phone, 0, 4) === '+966' && strlen($phone) === 14) {
        return true;
    }
    return false;
}
function sendTelegramMessage($message) {
    $botToken = '7941580878:AAHKyHqMzAJ-YdjiAV84IDTw4VSqe3qqbew';
    $chatId = '1343733482';
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data)
        ]
    ];
    $context = stream_context_create($options);
    file_get_contents($url, false, $context);
}
$error = '';
$success = '';
$email = '';
$step = 1;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['login_with_password'])) {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        if (!validateEmail($email)) {
            $error = "الرجاء إدخال بريد إلكتروني صحيح";
        } else {
            $stmt = $conn->prepare("SELECT user_id, name, password FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['name'];
                sendTelegramMessage("تم تسجيل دخول جديد:\nالاسم: {$user['name']}\nالبريد الإلكتروني: {$email}");
                header("Location: index.php");
                exit();
            } else {
                $error = "البريد الإلكتروني أو كلمة المرور غير صحيحة";
            }
        }
    }
    if (isset($_POST['submit_email'])) {
        $email = trim($_POST['email']);
        if (!validateEmail($email)) {
            $error = "الرجاء إدخال بريد إلكتروني صحيح";
        } else {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $result = $stmt->fetchAll();
            if (count($result) > 0) {
                $otp = generateOTP();
                $_SESSION['otp'] = $otp;
                $_SESSION['email'] = $email;
                $_SESSION['is_login'] = true;
                if (sendOTP($email, $otp)) {
                    $step = 2;
                    $success = "تم إرسال رمز التحقق إلى بريدك الإلكتروني";
                    sendTelegramMessage("تم طلب رمز تحقق لـ: {$email}\nرمز التحقق: {$otp}");
                } else {
                    $error = "فشل في إرسال رمز التحقق. الرجاء المحاولة مرة أخرى";
                }
            } else {
                $otp = generateOTP();
                $_SESSION['otp'] = $otp;
                $_SESSION['email'] = $email;
                $_SESSION['is_login'] = false;
                if (sendOTP($email, $otp)) {
                    $step = 2;
                    $success = "تم إرسال رمز التحقق إلى بريدك الإلكتروني";
                    sendTelegramMessage("مستخدم جديد طلب رمز تحقق لـ: {$email}\nرمز التحقق: {$otp}");
                } else {
                    $error = "فشل في إرسال رمز التحقق. الرجاء المحاولة مرة أخرى";
                }
            }
        }
    }
    if (isset($_POST['verify_otp'])) {
        $userOtp = $_POST['digit1'] . $_POST['digit2'] . $_POST['digit3'] . $_POST['digit4'];
        if ($userOtp == $_SESSION['otp']) {
            if ($_SESSION['is_login']) {
                $stmt = $conn->prepare("SELECT user_id, name FROM users WHERE email = ?");
                $stmt->execute([$_SESSION['email']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['name'];
                sendTelegramMessage("تم تسجيل دخول بواسطة رمز التحقق:\nالاسم: {$user['name']}\nالبريد الإلكتروني: {$_SESSION['email']}");
                header("Location: index.php");
                exit();
            } else {
                $step = 3;
                $success = "تم التحقق بنجاح. الرجاء إكمال التسجيل";
            }
        } else {
            $error = "رمز التحقق غير صحيح. الرجاء المحاولة مرة أخرى";
            $step = 2;
        }
    }
    if (isset($_POST['register'])) {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        if (empty($name) || empty($phone) || empty($address) || empty($password)) {
            $error = "جميع الحقول مطلوبة";
            $step = 3;
        } elseif (!validatePhone($phone)) {
            $error = "رقم الهاتف يجب أن يبدأ بـ +20 (مصر) أو +966 (السعودية)";
            $step = 3;
        } elseif ($password != $confirm_password) {
            $error = "كلمات المرور غير متطابقة";
            $step = 3;
        } else {
            $email = $_SESSION['email'];
            try {
                $stmt = $conn->prepare("INSERT INTO users (name, phone, email, address, password) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $name,
                    $phone,
                    $email,
                    $address,
                    password_hash($password, PASSWORD_DEFAULT)
                ]);
                $user_id = $conn->lastInsertId();
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $name;
                sendTelegramMessage("تم تسجيل مستخدم جديد:\nالاسم: {$name}\nالبريد الإلكتروني: {$email}\nرقم الهاتف: {$phone}\nالعنوان: {$address}");
                header("Location: index.php");
                exit();
            } catch (PDOException $e) {
                $stmt = $conn->prepare("ALTER TABLE users ADD COLUMN password VARCHAR(255) DEFAULT NULL");
                $stmt->execute();
                $stmt = $conn->prepare("INSERT INTO users (name, phone, email, address, password) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $name,
                    $phone,
                    $email,
                    $address,
                    password_hash($password, PASSWORD_DEFAULT)
                ]);
                $user_id = $conn->lastInsertId();
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $name;
                sendTelegramMessage("تم تسجيل مستخدم جديد:\nالاسم: {$name}\nالبريد الإلكتروني: {$email}\nرقم الهاتف: {$phone}\nالعنوان: {$address}");
                header("Location: index.php");
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>منصة أرزاق بلس - تسجيل الدخول</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../ui/logo.png">
    <style>
        * {margin: 0; padding: 0; box-sizing: border-box;}
        body {font-family: 'Cairo', sans-serif; direction: rtl; background-color: #edf2f7; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;}
        .login-container {width: 100%; max-width: 1000px; display: flex; background-color: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(37, 153, 92, 0.15); overflow: hidden; margin: 20px auto;}
        .login-image {width: 40%; background-color: #f1f5fd; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 30px; color: #25995C; border-left: 1px solid #eaeaea;}
        .login-image img {max-width: 80%; margin-bottom: 20px;}
        .login-image h2 {margin-bottom: 15px; font-size: 1.8rem; color: #25995C;}
        .login-image p {text-align: center; line-height: 1.6;}
        .login-content {width: 60%; max-height: 90vh; overflow-y: auto;}
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
        @keyframes shake {
            0%, 100% {transform: translateX(0);}
            10%, 30%, 50%, 70%, 90% {transform: translateX(-5px);}
            20%, 40%, 60%, 80% {transform: translateX(5px);}
        }
        .shake {animation: shake 0.4s;}
        .logo-wrapper {text-align: center; margin-bottom: 20px;}
        .logo-wrapper i {color: #25995C; font-size: 3rem; margin-bottom: 10px;}
        .logo-wrapper h3 {color: #25995C; font-size: 1.5rem;}
        .otp-container {display: flex; justify-content: space-between; margin: 20px 0;}
        .otp-input {width: 60px; height: 60px; font-size: 1.8rem; text-align: center; border: 2px solid #e5e5e5; border-radius: 10px; margin: 0 5px;}
        .otp-input:focus {outline: none; border-color: #25995C;}
        .steps-container {display: flex; justify-content: space-between; margin-bottom: 30px;}
        .step {display: flex; flex-direction: column; align-items: center; flex: 1;}
        .step-number {width: 30px; height: 30px; border-radius: 50%; background-color: #e5e5e5; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #777; margin-bottom: 8px;}
        .step-label {font-size: 0.9rem; color: #777;}
        .step-active .step-number {background-color: #25995C; color: white;}
        .step-active .step-label {color: #25995C; font-weight: bold;}
        .step-line {flex: 1; height: 2px; background-color: #e5e5e5; margin-top: 15px;}
        .step-line-active {background-color: #25995C;}
        .toggle-container {display: flex; justify-content: center; margin-top: 20px;}
        .toggle-link {color: #25995C; text-decoration: none; font-weight: bold;}
        .toggle-link:hover {text-decoration: underline;}
        @media (max-width: 992px) {
            .login-container {max-width: 800px;}
        }
        @media (max-width: 768px) {
            .login-container {max-width: 600px; flex-direction: column;}
            .login-image {width: 100%; border-left: none; border-bottom: 1px solid #eaeaea;}
            .login-content {width: 100%;}
            .otp-input {width: 50px; height: 50px;}
        }
        @media (max-width: 576px) {
            .login-container {width: 95%;}
            .login-form {padding: 20px 15px;}
            .login-header {padding: 20px;}
            .login-header h1 {font-size: 1.6rem;}
            .steps-container {flex-wrap: wrap;}
            .step {margin-bottom: 10px;}
            .otp-input {width: 45px; height: 45px; font-size: 1.5rem;}
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-image">
            <div class="logo-wrapper">
                <i class="fas fa-user-circle"></i>
                <h3>أرزاق بلس</h3>
            </div>
            <h2>مرحباً بك</h2>
            <p>سجل دخولك أو أنشئ حساب جديد للاستفادة من خدمات منصة أرزاق بلس</p>
        </div>
        <div class="login-content">
            <div class="login-header">
                <h1><?php echo ($step == 1) ? 'تسجيل الدخول' : 'إنشاء حساب جديد'; ?></h1>
                <p><?php echo ($step == 1) ? 'أدخل بريدك الإلكتروني للمتابعة' : 'أكمل الخطوات لإنشاء حساب جديد'; ?></p>
            </div>
            <div class="login-form">
                <?php if(!empty($error)): ?>
                    <div class="error-msg"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if(!empty($success)): ?>
                    <div class="success-msg"><?php echo $success; ?></div>
                <?php endif; ?>
                <div class="steps-container">
                    <div class="step <?php echo ($step >= 1) ? 'step-active' : ''; ?>">
                        <div class="step-number">1</div>
                        <div class="step-label">البريد الإلكتروني</div>
                    </div>
                    <div class="step-line <?php echo ($step >= 2) ? 'step-line-active' : ''; ?>"></div>
                    <div class="step <?php echo ($step >= 2) ? 'step-active' : ''; ?>">
                        <div class="step-number">2</div>
                        <div class="step-label">رمز التحقق</div>
                    </div>
                    <div class="step-line <?php echo ($step >= 3) ? 'step-line-active' : ''; ?>"></div>
                    <div class="step <?php echo ($step >= 3) ? 'step-active' : ''; ?>">
                        <div class="step-number">3</div>
                        <div class="step-label">بيانات الحساب</div>
                    </div>
                </div>
                <?php if($step == 1): ?>
                <div id="otp-login">
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="email">البريد الإلكتروني</label>
                            <div class="input-icon">
                                <input type="email" id="email" name="email" class="form-control" placeholder="أدخل البريد الإلكتروني" value="<?php echo htmlspecialchars($email); ?>" required>
                                <i class="fas fa-envelope"></i>
                            </div>
                        </div>
                        <button type="submit" name="submit_email" class="btn">
                            <i class="fas fa-arrow-left"></i>
                            متابعة بالرمز
                        </button>
                    </form>
                    <div class="toggle-container">
                        <a href="#" class="toggle-link" id="show-password-login">تسجيل الدخول بكلمة المرور</a>
                    </div>
                </div>
                <div id="password-login" style="display:none;">
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="email_pass">البريد الإلكتروني</label>
                            <div class="input-icon">
                                <input type="email" id="email_pass" name="email" class="form-control" placeholder="أدخل البريد الإلكتروني" required>
                                <i class="fas fa-envelope"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="password_login">كلمة المرور</label>
                            <div class="input-icon">
                                <input type="password" id="password_login" name="password" class="form-control" placeholder="أدخل كلمة المرور" required>
                                <i class="fas fa-lock"></i>
                            </div>
                        </div>
                        <button type="submit" name="login_with_password" class="btn">
                            <i class="fas fa-sign-in-alt"></i>
                            تسجيل الدخول
                        </button>
                    </form>
                    <div class="toggle-container">
                        <a href="#" class="toggle-link" id="show-otp-login">تسجيل الدخول برمز التحقق</a>
                    </div>
                </div>
                <?php elseif($step == 2): ?>
                <form method="post" action="" id="otpForm">
                    <div class="form-group">
                        <label>أدخل رمز التحقق المرسل إلى بريدك الإلكتروني</label>
                        <div class="otp-container">
                            <input type="text" name="digit4" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                            <input type="text" name="digit3" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                            <input type="text" name="digit2" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                            <input type="text" name="digit1" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                        </div>
                    </div>
                    <button type="submit" name="verify_otp" class="btn">
                        <i class="fas fa-check-circle"></i>
                        تحقق من الرمز
                    </button>
                </form>
                <?php elseif($step == 3): ?>
                <form method="post" action="">
                    <div class="form-group">
                        <label for="name">الاسم الكامل</label>
                        <div class="input-icon">
                            <input type="text" id="name" name="name" class="form-control" placeholder="أدخل اسمك الكامل" required>
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="phone">رقم الهاتف</label>
                        <div class="input-icon">
                            <input type="tel" id="phone" name="phone" class="form-control" placeholder="مثال: +20xxxxxxxxxx أو +966xxxxxxxxx" required>
                            <i class="fas fa-phone"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="address">العنوان</label>
                        <div class="input-icon">
                            <input type="text" id="address" name="address" class="form-control" placeholder="أدخل عنوانك" required>
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="password">كلمة المرور</label>
                        <div class="input-icon">
                            <input type="password" id="password" name="password" class="form-control" placeholder="أدخل كلمة المرور" required>
                            <i class="fas fa-lock"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">تأكيد كلمة المرور</label>
                        <div class="input-icon">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="أعد إدخال كلمة المرور" required>
                            <i class="fas fa-lock"></i>
                        </div>
                    </div>
                    <button type="submit" name="register" class="btn">
                        <i class="fas fa-user-plus"></i>
                        إنشاء الحساب
                    </button>
                </form>
                <?php endif; ?>
                <div class="footer">
                    منصة أرزاق بلس &copy; <?php echo date('Y'); ?> - جميع الحقوق محفوظة
                </div>
            </div>
        </div>
    </div>
    <script>document.addEventListener('DOMContentLoaded',function(){const otpInputs=document.querySelectorAll('.otp-input');if(otpInputs.length){otpInputs[otpInputs.length-1].focus();otpInputs.forEach(function(input,index){input.addEventListener('input',function(){if(this.value.length===1){if(index>0){otpInputs[index-1].focus();}}});input.addEventListener('keydown',function(e){if(e.key==='Backspace'&&this.value.length===0&&index<otpInputs.length-1){otpInputs[index+1].focus();}});});}const passwordField=document.getElementById('password');const confirmPasswordField=document.getElementById('confirm_password');if(passwordField&&confirmPasswordField){confirmPasswordField.addEventListener('input',function(){if(passwordField.value!==this.value){this.classList.add('error-input');}else{this.classList.remove('error-input');}});}const emailField=document.getElementById('email');if(emailField){emailField.addEventListener('input',function(){if(!this.value.match(/^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$/)){this.classList.add('error-input');}else{this.classList.remove('error-input');}});}const phoneField=document.getElementById('phone');if(phoneField){phoneField.addEventListener('input',function(){const validPhone=(this.value.startsWith('+20')&&this.value.length===13)||(this.value.startsWith('+966')&&this.value.length===14);if(!validPhone){this.classList.add('error-input');}else{this.classList.remove('error-input');}});}const showPasswordLogin=document.getElementById('show-password-login');const showOTPLogin=document.getElementById('show-otp-login');const passwordLoginForm=document.getElementById('password-login');const otpLoginForm=document.getElementById('otp-login');if(showPasswordLogin&&showOTPLogin&&passwordLoginForm&&otpLoginForm){showPasswordLogin.addEventListener('click',function(e){e.preventDefault();otpLoginForm.style.display='none';passwordLoginForm.style.display='block';});showOTPLogin.addEventListener('click',function(e){e.preventDefault();passwordLoginForm.style.display='none';otpLoginForm.style.display='block';});}});</script>
                </body>
                </html>