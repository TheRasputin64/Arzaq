<?php
session_start();
require_once 'db.php';

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function generateOTP() {
    return rand(1000, 9999);
}

function sendOTP($email, $otp) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'bodysameh2006@gmail.com';
        $mail->Password   = 'wriq tazo iphn djrk';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        
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
    } catch (Exception $e) {
        return false;
    }
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

$error = '';
$success = '';
$email = '';
$step = 1;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['submit_email'])) {
        $email = trim($_POST['email']);
        if (!validateEmail($email)) {
            $error = "الرجاء إدخال بريد إلكتروني صحيح";
        } else {
            $stmt = $conn->prepare("SELECT market_id FROM markets WHERE email = ?");
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
                $stmt = $conn->prepare("SELECT market_id, name FROM markets WHERE email = ?");
                $stmt->execute([$_SESSION['email']]);
                $market = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $_SESSION['market_id'] = $market['market_id'];
                $_SESSION['market_name'] = $market['name'];
                
                header("Location: market_dashboard.php");
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
        $whatsapp = isset($_POST['whatsapp']) ? 1 : 0;
        $address = trim($_POST['address']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        
        if (empty($name) || empty($phone) || empty($address) || empty($password)) {
            $error = "جميع الحقول مطلوبة";
            $step = 3;
        } elseif ($password != $confirm_password) {
            $error = "كلمات المرور غير متطابقة";
            $step = 3;
        } elseif (strlen($password) < 6) {
            $error = "يجب أن تكون كلمة المرور 6 أحرف على الأقل";
            $step = 3;
        } else {
            $step = 4;
            $_SESSION['market_data'] = [
                'name' => $name,
                'phone' => $phone,
                'whatsapp' => $whatsapp,
                'address' => $address,
                'password' => password_hash($password, PASSWORD_DEFAULT)
            ];
        }
    }
    
    if (isset($_POST['accept_agreement'])) {
        $email = $_SESSION['email'];
        $marketData = $_SESSION['market_data'];
        
        try {
            $stmt = $conn->prepare("INSERT INTO markets (name, phone, whatsapp, email, address, password) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $marketData['name'],
                $marketData['phone'],
                $marketData['whatsapp'],
                $email,
                $marketData['address'],
                $marketData['password']
            ]);
            
            $market_id = $conn->lastInsertId();
            $_SESSION['market_id'] = $market_id;
            $_SESSION['market_name'] = $marketData['name'];
            
            header("Location: market_dashboard.php");
            exit();
        } catch (PDOException $e) {
            $error = "حدث خطأ أثناء التسجيل. الرجاء المحاولة مرة أخرى";
            $step = 3;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>منصة أرزاق بلس - تسجيل المتاجر</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        
        /* OTP Fields */
        .otp-container {display: flex; justify-content: space-between; margin: 20px 0;}
        .otp-input {width: 60px; height: 60px; font-size: 1.8rem; text-align: center; border: 2px solid #e5e5e5; border-radius: 10px; margin: 0 5px;}
        .otp-input:focus {outline: none; border-color: #25995C;}
        
        /* Checkbox */
        .checkbox-container {display: flex; align-items: center; margin-bottom: 20px;}
        .checkbox-container input[type="checkbox"] {width: 20px; height: 20px; margin-left: 10px;}
        
        /* Agreement */
        .agreement-container {background-color: #f9f9f9; padding: 20px; border-radius: 10px; max-height: 400px; overflow-y: auto; margin-bottom: 20px; border: 1px solid #e5e5e5;}
        .agreement-container h3 {margin-bottom: 15px; color: #25995C;}
        .agreement-container p {margin-bottom: 10px; line-height: 1.6;}
        .agreement-container ul {margin-right: 20px; margin-bottom: 15px;}
        .agreement-container li {margin-bottom: 5px; line-height: 1.6;}
        
        /* Steps */
        .steps-container {display: flex; justify-content: space-between; margin-bottom: 30px;}
        .step {display: flex; flex-direction: column; align-items: center; flex: 1;}
        .step-number {width: 30px; height: 30px; border-radius: 50%; background-color: #e5e5e5; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #777; margin-bottom: 8px;}
        .step-label {font-size: 0.9rem; color: #777;}
        .step-active .step-number {background-color: #25995C; color: white;}
        .step-active .step-label {color: #25995C; font-weight: bold;}
        .step-line {flex: 1; height: 2px; background-color: #e5e5e5; margin-top: 15px;}
        .step-line-active {background-color: #25995C;}
        
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
                <i class="fas fa-store"></i>
                <h3>أرزاق بلس</h3>
            </div>
            <h2>انضم إلينا اليوم</h2>
            <p>سجل متجرك في منصة أرزاق بلس واصل إلى آلاف العملاء وزد مبيعاتك</p>
        </div>
        <div class="login-content">
            <div class="login-header">
                <h1>تسجيل متجر جديد</h1>
                <p>سجل متجرك الآن وابدأ رحلتك معنا</p>
            </div>
            <div class="login-form">
                <?php if(!empty($error)): ?>
                    <div class="error-msg"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if(!empty($success)): ?>
                    <div class="success-msg"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Steps indicator -->
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
                        <div class="step-label">بيانات المتجر</div>
                    </div>
                    <div class="step-line <?php echo ($step >= 4) ? 'step-line-active' : ''; ?>"></div>
                    <div class="step <?php echo ($step >= 4) ? 'step-active' : ''; ?>">
                        <div class="step-number">4</div>
                        <div class="step-label">الاتفاقية</div>
                    </div>
                </div>
                
                <?php if($step == 1): ?>
                <!-- Step 1: Email Form -->
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
                        متابعة
                    </button>
                </form>
                
                <?php elseif($step == 2): ?>
                <!-- Step 2: OTP Verification -->
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
                <!-- Step 3: Registration Form -->
                <form method="post" action="">
                    <div class="form-group">
                        <label for="name">اسم المتجر</label>
                        <div class="input-icon">
                            <input type="text" id="name" name="name" class="form-control" placeholder="أدخل اسم المتجر" required>
                            <i class="fas fa-store"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="phone">رقم الهاتف</label>
                        <div class="input-icon">
                            <input type="tel" id="phone" name="phone" class="form-control" placeholder="أدخل رقم الهاتف" required>
                            <i class="fas fa-phone"></i>
                        </div>
                    </div>
                    <div class="checkbox-container">
                        <input type="checkbox" id="whatsapp" name="whatsapp">
                        <label for="whatsapp">هذا الرقم متاح على واتساب</label>
                    </div>
                    <div class="form-group">
                        <label for="address">عنوان المتجر</label>
                        <div class="input-icon">
                            <input type="text" id="address" name="address" class="form-control" placeholder="أدخل عنوان المتجر" required>
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
                        متابعة
                    </button>
                </form>
                
                <?php elseif($step == 4): ?>
                <!-- Step 4: Agreement -->
                <form method="post" action="">
                    <div class="agreement-container">
                        <h3>عقد اتفاق والتزام بين منصة [أرزاق بلس] و [<?php echo htmlspecialchars($_SESSION['market_data']['name']); ?>]</h3>
                        <p>عند تسجيلك كبائع (تاجر) في منصة [أرزاق بلس]، فإنك تقر وتوافق على جميع الشروط والأحكام التالية، والتي تهدف إلى حماية حقوق المنصة وضمان بيئة تجارية آمنة وسليمة لجميع الأطراف:</p>
                        
                        <h4>1. البيانات والتسجيل</h4>
                        <ul>
                            <li>يلتزم التاجر بتقديم بيانات صحيحة ودقيقة عند التسجيل، بما في ذلك (السجل التجاري - موقع المحل - الرقم الضريبي - بيانات التواصل).</li>
                            <li>يقر التاجر بأن جميع المعلومات المقدمة قانونية وصحيحة ومحدثة.</li>
                        </ul>
                        
                        <h4>2. المسؤولية القانونية</h4>
                        <ul>
                            <li>يتحمل التاجر المسؤولية الكاملة عن المنتجات المعروضة، من حيث الجودة والمواصفات والصلاحية، وأي مخالفات قانونية أو تنظيمية تخص المنتجات أو نشاطه التجاري.</li>
                            <li>يلتزم التاجر بالامتثال لجميع الأنظمة والقوانين المعمول بها في الدولة التي يعمل فيها.</li>
                        </ul>
                        
                        <h4>3. الالتزام بحقوق العملاء</h4>
                        <ul>
                            <li>يلتزم التاجر بالتعامل بشفافية مع العملاء، وتوضيح كافة التفاصيل المتعلقة بالمنتجات (الأسعار – المواصفات – سياسة الاستبدال والاسترجاع – الضمانات).</li>
                            <li>يمنع بيع أي منتجات مخالفة أو مقلدة أو تنتهك حقوق الملكية الفكرية.</li>
                        </ul>
                        
                        <h4>4. المسؤولية عن الضرر</h4>
                        <ul>
                            <li>يقر التاجر بأنه مسؤول بشكل كامل عن أي أضرار أو خسائر (مباشرة أو غير مباشرة) قد تلحق بالمنصة أو بعملائها نتيجة أي مخالفة أو إهمال من قبل التاجر.</li>
                            <li>يحق للمنصة اتخاذ الإجراءات القانونية، بما في ذلك إيقاف المتجر أو حذف الحساب أو المطالبة بالتعويضات المالية.</li>
                        </ul>
                        
                        <h4>5. إخلاء مسؤولية المنصة</h4>
                        <ul>
                            <li>المنصة لا تتحمل أي مسؤولية قانونية عن المنتجات أو التعاملات بين التاجر والعملاء، ودور المنصة يقتصر على توفير البيئة الإلكترونية لعرض المنتجات.</li>
                            <li>يوافق التاجر على أن المنصة ليست مسؤولة عن أي نزاعات تنشأ بين التاجر والعميل.</li>
                        </ul>
                        
                        <h4>6. سياسة إنهاء الخدمة</h4>
                        <ul>
                            <li>تحتفظ المنصة بحق إيقاف أو تعليق حساب التاجر أو حذفه بشكل كامل في حال مخالفة الشروط أو ارتكاب مخالفات قانونية أو تقديم منتجات أو خدمات تضر بسمعة المنصة.</li>
                        </ul>
                        
                        <h4>7. التعديلات على الاتفاقية</h4>
                        <ul>
                            <li>يحق للمنصة تحديث أو تعديل هذه الاتفاقية في أي وقت، ويلتزم التاجر بمراجعة الشروط بشكل دوري.</li>
                        </ul>
                        
                        <h4>8. استلام وسحب المستحقات المالية</h4>
                        <ul>
                            <li>يقر التاجر أن جميع المستحقات المالية الخاصة به يتم إضافتها إلى محفظته الإلكترونية داخل منصة [أرزاق بلس]، ويمكنه سحبها فقط عبر وسائل الدفع المتاحة والمعتمدة داخل المنصة.</li>
                            <li>يلتزم التاجر بتحديث بيانات الدفع الخاصة به، ويتحمل وحده مسؤولية أي تأخير أو مشكلة في السحب نتيجة إدخال بيانات خاطئة أو غير مكتملة.</li>
                            <li>تحتفظ المنصة بحق تأخير أو تعليق أي دفعات في حال وجود شكوك تتعلق بالنشاط التجاري أو نزاعات قائمة حتى يتم حلها.</li>
                            <li>يوافق التاجر أن أي رسوم تحويل أو رسوم خدمات بنكية أو إلكترونية سيتم خصمها من الرصيد المحول وفق سياسة المنصة.</li>
                        </ul>
                        
                        <h4>9. الموافقة</h4>
                        <ul>
                            <li>بتسجيلك في المنصة، فإنك تقر وتوافق على جميع البنود المذكورة أعلاه وتتحمل كامل المسؤولية في حال الإخلال بها.</li>
                        </ul>
                        
                        <p>أنا، الموقع أدناه، أقر وأوافق على جميع الشروط والأحكام المذكورة أعلاه وألتزم بالامتثال لها.</p>
                    </div>
                    
                    <div class="checkbox-container">
                        <input type="checkbox" id="agree" name="agree" required>
                        <label for="agree">أوافق على جميع الشروط والأحكام المذكورة أعلاه</label>
                    </div>
                    
                    <button type="submit" name="accept_agreement" class="btn">
                        <i class="fas fa-handshake"></i>
                        إكمال التسجيل
                    </button>
                </form>
                <?php endif; ?>
                
                <div class="footer">
                    منصة أرزاق بلس &copy; <?php echo date('Y'); ?> - جميع الحقوق محفوظة
                </div>
            </div>
        </div>
    </div>
    <script>document.addEventListener('DOMContentLoaded',function(){const otpInputs=document.querySelectorAll('.otp-input');if(otpInputs.length){otpInputs[otpInputs.length-1].focus();otpInputs.forEach(function(input,index){input.addEventListener('input',function(){if(this.value.length===1){if(index>0){otpInputs[index-1].focus();}}});input.addEventListener('keydown',function(e){if(e.key==='Backspace'&&this.value.length===0&&index<otpInputs.length-1){otpInputs[index+1].focus();}});});}});document.addEventListener('DOMContentLoaded',function(){const passwordField=document.getElementById('password');const confirmPasswordField=document.getElementById('confirm_password');if(passwordField&&confirmPasswordField){confirmPasswordField.addEventListener('input',function(){if(passwordField.value!==this.value){this.classList.add('error-input');}else{this.classList.remove('error-input');}});}const emailField=document.getElementById('email');if(emailField){emailField.addEventListener('input',function(){const emailPattern=/^[^\s@]+@[^\s@]+\.[^\s@]+$/;if(!emailPattern.test(this.value)){this.classList.add('error-input');}else{this.classList.remove('error-input');}});}const phoneField=document.getElementById('phone');if(phoneField){phoneField.addEventListener('input',function(){const phonePattern=/^\d{10,15}$/;if(!phonePattern.test(this.value.replace(/\s/g,''))){this.classList.add('error-input');}else{this.classList.remove('error-input');}});}const agreeCheckbox=document.getElementById('agree');const submitButton=document.querySelector('button[name="accept_agreement"]');if(agreeCheckbox&&submitButton){submitButton.disabled=!agreeCheckbox.checked;agreeCheckbox.addEventListener('change',function(){submitButton.disabled=!this.checked;});}});</script>
</body>
</html>