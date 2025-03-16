<?php
session_start();
require_once 'db.php';

$type = isset($_GET['type']) ? $_GET['type'] : '';
$valid_types = ['sales', 'purchasing', 'delivery', 'service_provider', 'employee'];

if (!in_array($type, $valid_types)) {
    header('Location: select.php');
    exit();
}

$type_names = [
    'sales' => 'مندوب مبيعات',
    'purchasing' => 'مندوب مشتريات',
    'delivery' => 'مندوب توصيل',
    'service_provider' => 'مزود خدمة',
    'employee' => 'موظف'
];

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $name = $_POST['name'];
        $phone = $_POST['phone'];
        $email = $_POST['email'] ?? null;
        $city = $_POST['city'];
        $areas = $_POST['areas'];
        $id_number = $_POST['id_number'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        // Generate unique referral code
        $referral_code = substr(md5($phone . time()), 0, 8);
        
        // Handle ID image upload
        $id_image_path = '';
        if (isset($_FILES['id_image']) && $_FILES['id_image']['error'] == 0) {
            $target_dir = "uploads/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_extension = pathinfo($_FILES['id_image']['name'], PATHINFO_EXTENSION);
            $new_filename = 'ID_' . time() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['id_image']['tmp_name'], $target_file)) {
                $id_image_path = $target_file;
            }
        }
        
        // Check if phone already exists
        $stmt = $conn->prepare("SELECT rep_id FROM representatives WHERE phone = ?");
        $stmt->execute([$phone]);
        $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_user) {
            $error_message = 'رقم الهاتف مسجل بالفعل. الرجاء استخدام رقم آخر أو تسجيل الدخول.';
        } else {
            // Begin transaction
            $conn->beginTransaction();
            
            // Insert representative data
            $stmt = $conn->prepare("INSERT INTO representatives (name, phone, email, city, id_number, id_image_path, password, rep_type, areas, referral_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $email, $city, $id_number, $id_image_path, $password, $type, $areas, $referral_code]);
            
            $rep_id = $conn->lastInsertId();
            
            // Additional fields for service providers
            if ($type == 'service_provider' && isset($_POST['service_type'])) {
                $service_type = $_POST['service_type'];
                $commercial_reg = $_POST['commercial_reg'] ?? null;
                $tax_number = $_POST['tax_number'] ?? null;
                
                // Handle document upload for service providers
                $document_path = '';
                if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
                    $target_dir = "uploads/";
                    $file_extension = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
                    $new_filename = 'DOC_' . time() . '.' . $file_extension;
                    $target_file = $target_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['document']['tmp_name'], $target_file)) {
                        $document_path = $target_file;
                    }
                }
                
                $stmt = $conn->prepare("INSERT INTO service_providers (rep_id, service_type, commercial_reg, tax_number, document_path) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$rep_id, $service_type, $commercial_reg, $tax_number, $document_path]);
            }
            
            // Check if referral code exists in URL
            if (isset($_GET['ref']) && !empty($_GET['ref'])) {
                $referrer_code = $_GET['ref'];
                
                // Get referring rep_id
                $stmt = $conn->prepare("SELECT rep_id FROM representatives WHERE referral_code = ?");
                $stmt->execute([$referrer_code]);
                $referrer = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($referrer) {
                    $referrer_id = $referrer['rep_id'];
                    
                    // Insert into referrals table
                    $stmt = $conn->prepare("INSERT INTO referrals (rep_id, user_id, market_id) VALUES (?, NULL, NULL)");
                    $stmt->execute([$referrer_id]);
                    
                    // Update referral_visits table
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $stmt = $conn->prepare("UPDATE referral_visits SET converted = 1 WHERE referral_code = ? AND ip_address = ? ORDER BY visit_id DESC LIMIT 1");
                    $stmt->execute([$referrer_code, $ip]);
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success_message'] = 'تم التسجيل بنجاح. يمكنك الآن تسجيل الدخول.';
            header('Location: login.php');
            exit();
        }
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error_message = 'حدث خطأ أثناء التسجيل. يرجى المحاولة مرة أخرى. ' . $e->getMessage();
    }
}

// Track referral visits
if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    $referral_code = $_GET['ref'];
    $ip = $_SERVER['REMOTE_ADDR'];
    
    try {
        $stmt = $conn->prepare("INSERT INTO referral_visits (referral_code, ip_address) VALUES (?, ?)");
        $stmt->execute([$referral_code, $ip]);
    } catch (PDOException $e) {
        // Silently fail, this isn't critical
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل <?php echo $type_names[$type]; ?> - أرزاق بلس</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>* {margin: 0; padding: 0; box-sizing: border-box;} body {font-family: 'Cairo', sans-serif; direction: rtl; background-color: #edf2f7; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;} .register-container {width: 100%; max-width: 1000px; display: flex; background-color: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(37, 153, 92, 0.15); overflow: hidden; margin: 20px auto;} .register-image {width: 40%; background-color: #f1f5fd; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 30px; color: #25995C; border-left: 1px solid #eaeaea;} .register-image img {max-width: 80%; margin-bottom: 20px;} .register-image h2 {margin-bottom: 15px; font-size: 1.8rem; color: #25995C;} .register-image p {text-align: center; line-height: 1.6;} .register-content {width: 60%; max-height: 90vh; overflow-y: auto;} .register-header {background-color: #25995C; color: white; padding: 25px; text-align: center;} .register-header h1 {font-size: 2rem; margin-bottom: 8px; text-shadow: 0 2px 4px rgba(0,0,0,0.1);} .register-header p {font-size: 1.1rem; opacity: 0.9;} .register-form {padding: 30px;} .form-row {display: flex; gap: 15px; margin-bottom: 20px;} .form-group {margin-bottom: 20px; position: relative; flex: 1;} .form-group label {display: block; margin-bottom: 10px; font-weight: bold; color: #333; font-size: 1.1rem;} .input-icon {position: relative;} .input-icon i {position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #25995C; font-size: 1.1rem;} .form-control {width: 100%; padding: 14px 20px; padding-left: 45px; border: 2px solid #e5e5e5; border-radius: 10px; font-family: 'Cairo', sans-serif; font-size: 1rem; transition: all 0.3s;} .form-file {padding: 12px; cursor: pointer;} .form-control:focus {outline: none; border-color: #25995C; box-shadow: 0 0 0 3px rgba(37, 153, 92, 0.15);} .error-input {border-color: #e74c3c !important;} .btn {width: 100%; padding: 14px 0; background-color: #25995C; color: white; border: none; border-radius: 10px; font-family: 'Cairo', sans-serif; font-weight: bold; font-size: 1.1rem; cursor: pointer; transition: all 0.3s; box-shadow: 0 6px 12px rgba(37, 153, 92, 0.2); display: flex; align-items: center; justify-content: center; gap: 10px;} .btn:hover {background-color: #1c7a48; transform: translateY(-2px);} .btn:active {transform: translateY(0);} .error-msg {color: #e74c3c; background-color: #fdecea; border-radius: 8px; padding: 12px 15px; margin-bottom: 20px; text-align: center; font-size: 1rem; border-right: 4px solid #e74c3c;} .success-msg {color: #2ecc71; background-color: #e8f8f2; border-radius: 8px; padding: 12px 15px; margin-bottom: 20px; text-align: center; font-size: 1rem; border-right: 4px solid #2ecc71;} .footer {text-align: center; margin-top: 25px; font-size: 0.9rem; color: #777;} .checkbox-container {display: flex; align-items: center; margin-bottom: 20px;} .checkbox-container input[type="checkbox"] {width: 20px; height: 20px; margin-left: 10px;} .steps-container {display: flex; justify-content: space-between; margin-bottom: 30px;} .step {display: flex; flex-direction: column; align-items: center; flex: 1;} .step-number {width: 30px; height: 30px; border-radius: 50%; background-color: #e5e5e5; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #777; margin-bottom: 8px;} .step-label {font-size: 0.9rem; color: #777;} .step-active .step-number {background-color: #25995C; color: white;} .step-active .step-label {color: #25995C; font-weight: bold;} .step-line {flex: 1; height: 2px; background-color: #e5e5e5; margin-top: 15px;} .step-line-active {background-color: #25995C;} @media (max-width: 992px) {.register-container {max-width: 800px;}} @media (max-width: 768px) {.register-container {max-width: 600px; flex-direction: column;} .register-image {width: 100%; border-left: none; border-bottom: 1px solid #eaeaea;} .register-content {width: 100%;} .form-row {flex-direction: column; gap: 0;}} @media (max-width: 576px) {.register-container {width: 95%;} .register-form {padding: 20px 15px;} .register-header {padding: 20px;} .register-header h1 {font-size: 1.6rem;} .steps-container {flex-wrap: wrap;} .step {margin-bottom: 10px;}}</style>
</head>
<body>
    <div class="register-container">
        <div class="register-image">
            <i class="fas fa-handshake" style="font-size: 5rem; margin-bottom: 20px;"></i>
            <h2>انضم إلى فريق أرزاق بلس</h2>
            <p>كن جزءًا من شبكتنا المتنامية واستفد من الميزات الحصرية والفرص المتاحة</p>
            <div style="margin-top: 30px;">
                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                    <i class="fas fa-check-circle" style="color: #25995C; margin-left: 10px;"></i>
                    <span>عمولات تنافسية</span>
                </div>
                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                    <i class="fas fa-check-circle" style="color: #25995C; margin-left: 10px;"></i>
                    <span>نظام إحالة مربح</span>
                </div>
                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                    <i class="fas fa-check-circle" style="color: #25995C; margin-left: 10px;"></i>
                    <span>دعم فني على مدار الساعة</span>
                </div>
                <div style="display: flex; align-items: center;">
                    <i class="fas fa-check-circle" style="color: #25995C; margin-left: 10px;"></i>
                    <span>تسويق رقمي متكامل</span>
                </div>
            </div>
        </div>
        <div class="register-content">
            <div class="register-header">
                <h1>تسجيل <?php echo $type_names[$type]; ?> جديد</h1>
                <p>أكمل البيانات التالية للانضمام إلى منصة أرزاق بلس</p>
            </div>
            <div class="register-form">
                <?php if (!empty($error_message)): ?>
                <div class="error-msg">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-msg">
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
                <?php endif; ?>
                
                <div class="steps-container">
                    <div class="step step-active">
                        <div class="step-number">1</div>
                        <div class="step-label">المعلومات الشخصية</div>
                    </div>
                    <div class="step-line step-line-active"></div>
                    <div class="step">
                        <div class="step-number">2</div>
                        <div class="step-label">معلومات العمل</div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <div class="step-label">المراجعة والتأكيد</div>
                    </div>
                </div>
                
                <form method="post" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">الاسم الكامل</label>
                            <div class="input-icon">
                                <input type="text" id="name" name="name" class="form-control" required>
                                <i class="fas fa-user"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="phone">رقم الجوال</label>
                            <div class="input-icon">
                                <input type="text" id="phone" name="phone" class="form-control" placeholder="05xxxxxxxx" required>
                                <i class="fas fa-phone"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">البريد الإلكتروني (اختياري)</label>
                            <div class="input-icon">
                                <input type="email" id="email" name="email" class="form-control">
                                <i class="fas fa-envelope"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="id_number">رقم الهوية/الإقامة</label>
                            <div class="input-icon">
                                <input type="text" id="id_number" name="id_number" class="form-control" required>
                                <i class="fas fa-id-card"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="id_image">صورة الهوية/الإقامة</label>
                        <div class="input-icon">
                            <input type="file" id="id_image" name="id_image" class="form-control form-file" accept="image/*" required>
                            <i class="fas fa-upload"></i>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">المدينة</label>
                            <div class="input-icon">
                                <input type="text" id="city" name="city" class="form-control" required>
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="areas">مناطق التغطية</label>
                            <div class="input-icon">
                                <textarea id="areas" name="areas" class="form-control" placeholder="افصل بين المناطق بفاصلة" required></textarea>
                                <i class="fas fa-map"></i>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($type == 'service_provider'): ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="service_type">نوع الخدمة</label>
                            <div class="input-icon">
                                <input type="text" id="service_type" name="service_type" class="form-control" required>
                                <i class="fas fa-briefcase"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="commercial_reg">رقم السجل التجاري (اختياري)</label>
                            <div class="input-icon">
                                <input type="text" id="commercial_reg" name="commercial_reg" class="form-control">
                                <i class="fas fa-file-contract"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="tax_number">الرقم الضريبي (اختياري)</label>
                            <div class="input-icon">
                                <input type="text" id="tax_number" name="tax_number" class="form-control">
                                <i class="fas fa-receipt"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="document">المستندات الداعمة (اختياري)</label>
                        <div class="input-icon">
                            <input type="file" id="document" name="document" class="form-control form-file" accept=".pdf,.jpg,.jpeg,.png">
                            <i class="fas fa-file-upload"></i>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">كلمة المرور</label>
                            <div class="input-icon">
                                <input type="password" id="password" name="password" class="form-control" required>
                                <i class="fas fa-lock"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">تأكيد كلمة المرور</label>
                            <div class="input-icon">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                <i class="fas fa-lock"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="checkbox-container">
                        <input type="checkbox" id="agree" name="agree" required>
                        <label for="agree">أقر بأن جميع البيانات المقدمة صحيحة وأوافق على <a href="terms.php" target="_blank">الشروط والأحكام</a></label>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-user-plus"></i> تسجيل
                    </button>
                </form>
                
                <div class="footer">
                    <p>لديك حساب بالفعل؟ <a href="login.php">تسجيل الدخول</a></p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.getElementById('confirm_password').addEventListener('input', function() {
        const password = document.getElementById('password').value;
        const confirm = this.value;
        
        if (password !== confirm) {
            this.classList.add('error-input');
        } else {
            this.classList.remove('error-input');
        }
    });
    
    document.querySelector('form').addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const confirm = document.getElementById('confirm_password').value;
        
        if (password !== confirm) {
            e.preventDefault();
            document.getElementById('confirm_password').classList.add('error-input');
            alert('كلمات المرور غير متطابقة');
        }
    });
    </script>
</body>
</html>