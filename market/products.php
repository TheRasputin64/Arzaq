<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['market_id'])) {
    header("Location: index.php");
    exit();
}

$market_id = $_SESSION['market_id'];
$market_name = $_SESSION['market_name'];

// Check if the table has the is_active column, if not, add it
try {
    $check_column = $conn->query("SHOW COLUMNS FROM products LIKE 'is_active'");
    if ($check_column->rowCount() == 0) {
        $conn->exec("ALTER TABLE products ADD COLUMN is_active TINYINT(1) DEFAULT 1");
    }
} catch (PDOException $e) {
    $error_message = "خطأ في قاعدة البيانات: " . $e->getMessage();
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $product_id = $_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ? AND market_id = ?");
        $result = $stmt->execute([$product_id, $market_id]);
        
        if ($result) {
            $success_message = "تم حذف المنتج بنجاح";
        } else {
            $error_message = "فشل في حذف المنتج";
        }
    } catch (PDOException $e) {
        $error_message = "حدث خطأ أثناء محاولة حذف المنتج";
    }
}

if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    $product_id = $_GET['toggle_status'];
    try {
        $stmt = $conn->prepare("SELECT is_active FROM products WHERE product_id = ? AND market_id = ?");
        $stmt->execute([$product_id, $market_id]);
        $current_status = $stmt->fetchColumn();
        
        if ($current_status === false) {
            // If is_active is not set, default to 1
            $current_status = 1;
        }
        
        $new_status = ($current_status == 1) ? 0 : 1;
        
        $stmt = $conn->prepare("UPDATE products SET is_active = ? WHERE product_id = ? AND market_id = ?");
        $result = $stmt->execute([$new_status, $product_id, $market_id]);
        
        if ($result) {
            $status_message = "تم تغيير حالة المنتج بنجاح";
            header("Location: products.php");
            exit();
        }
    } catch (PDOException $e) {
        $error_message = "حدث خطأ أثناء تحديث حالة المنتج: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $name = $_POST['name'];
    $category_id = $_POST['category_id'];
    $price = $_POST['price'];
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    $state_id = isset($_POST['state_id']) ? $_POST['state_id'] : null;
    $shipping_id = isset($_POST['shipping_id']) ? $_POST['shipping_id'] : null;
    
    $image_path = '';
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024;
        
        if (in_array($_FILES['image']['type'], $allowed_types) && $_FILES['image']['size'] <= $max_size) {
            $upload_dir = 'products/';
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('product_') . '.' . $file_extension;
            $target_file = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_path = $target_file;
            } else {
                $error_message = "فشل في رفع الصورة";
            }
        } else {
            $error_message = "نوع الملف غير مسموح به أو حجم الملف كبير جداً";
        }
    }
    
    if (!isset($error_message)) {
        try {
            // Check if is_active column exists in the query
            $columns = "market_id, category_id, name, description, price, image_path";
            $values = "?, ?, ?, ?, ?, ?";
            $params = [$market_id, $category_id, $name, $description, $price, $image_path];
            
            // Add state_id if provided
            if ($state_id !== null) {
                $columns .= ", state_id";
                $values .= ", ?";
                $params[] = $state_id;
            }
            
            // Add shipping_id if provided
            if ($shipping_id !== null) {
                $columns .= ", shipping_id";
                $values .= ", ?";
                $params[] = $shipping_id;
            }
            
            // Check if is_active exists in the table
            $check_column = $conn->query("SHOW COLUMNS FROM products LIKE 'is_active'");
            if ($check_column->rowCount() > 0) {
                $columns .= ", is_active";
                $values .= ", ?";
                $params[] = 1; // Default to active
            }
            
            $stmt = $conn->prepare("INSERT INTO products ($columns) VALUES ($values)");
            $result = $stmt->execute($params);
            
            if ($result) {
                $success_message = "تم إضافة المنتج بنجاح";
            } else {
                $error_message = "فشل في إضافة المنتج";
            }
        } catch (PDOException $e) {
            $error_message = "حدث خطأ أثناء محاولة إضافة المنتج: " . $e->getMessage();
        }
    }
}

// Get all products with categories
try {
    $stmt = $conn->prepare("
        SELECT p.*, c.category_name 
        FROM products p 
        JOIN categories c ON p.category_id = c.category_id 
        WHERE p.market_id = ?
    ");
    $stmt->execute([$market_id]);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "خطأ في استرجاع المنتجات: " . $e->getMessage();
    $products = [];
}

// Get all categories
try {
    $stmt = $conn->prepare("SELECT category_id, category_name FROM categories ORDER BY category_name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "خطأ في استرجاع الفئات: " . $e->getMessage();
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المنتجات - أرزاق بلس</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Cairo',sans-serif;background-color:#f7f9fc;color:#333;direction:rtl;}
        .dashboard-container{display:flex;min-height:100vh;position:relative;}
        .sidebar{width:250px;background-color:#25995C;color:white;position:fixed;height:100vh;overflow-y:auto;transition:all 0.3s;}
        .sidebar-header{padding:20px 15px;border-bottom:1px solid rgba(255,255,255,0.1);}
        .sidebar-header h3{font-size:1.5rem;}
        .sidebar-header p{font-size:0.9rem;opacity:0.8;margin-top:5px;}
        .sidebar-menu{padding:20px 0;}
        .sidebar-menu-item{padding:12px 20px;display:flex;align-items:center;transition:all 0.3s;cursor:pointer;}
        .sidebar-menu-item:hover{background-color:rgba(255,255,255,0.1);}
        .sidebar-menu-item.active{background-color:rgba(255,255,255,0.2);border-right:4px solid white;}
        .sidebar-menu-item i{margin-left:10px;width:20px;text-align:center;}
        .sidebar-footer{position:absolute;bottom:0;width:100%;padding:15px;text-align:center;border-top:1px solid rgba(255,255,255,0.1);}
        .sidebar-footer a{color:white;text-decoration:none;opacity:0.8;font-size:0.9rem;}
        .sidebar-footer a:hover{opacity:1;}
        .main-content{flex:1;padding:20px;width:calc(100% - 250px);margin-right:250px;transition:all 0.3s;}
        .header{background-color:white;padding:15px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.05);margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;}
        .header h2{color:#25995C;}
        .header-actions a{background-color:#25995C;color:white;padding:8px 15px;border-radius:5px;text-decoration:none;font-size:0.9rem;transition:all 0.3s;display:inline-block;}
        .header-actions a:hover{background-color:#1e7a48;}
        .section{background-color:white;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:15px;margin-bottom:20px;}
        .table{width:100%;border-collapse:collapse;}
        .table th{background-color:#f9f9f9;padding:10px;text-align:right;border-bottom:2px solid #eee;}
        .table td{padding:10px;border-bottom:1px solid #eee;vertical-align:middle;}
        .table tr:last-child td{border-bottom:none;}
        .product-image{width:60px;height:60px;object-fit:cover;border-radius:5px;}
        .action-btn{color:#25995C;text-decoration:none;margin-left:10px;font-size:1rem;}
        .delete-btn{color:#dc3545;}
        .toggle-btn{cursor:pointer;position:relative;display:inline-block;width:40px;height:20px;}
        .toggle-btn input{opacity:0;width:0;height:0;}
        .toggle-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background-color:#ccc;transition:.4s;border-radius:34px;}
        .toggle-slider:before{position:absolute;content:"";height:16px;width:16px;left:2px;bottom:2px;background-color:white;transition:.4s;border-radius:50%;}
        input:checked + .toggle-slider{background-color:#25995C;}
        input:checked + .toggle-slider:before{transform:translateX(20px);}
        .alert{padding:10px 15px;border-radius:5px;margin-bottom:15px;}
        .alert-success{background-color:#d4edda;border:1px solid #c3e6cb;color:#155724;}
        .alert-danger{background-color:#f8d7da;border:1px solid #f5c6cb;color:#721c24;}
        .no-products{text-align:center;padding:20px;color:#777;}
        .hamburger{display:none;position:fixed;top:15px;right:15px;z-index:999;background-color:#25995C;color:white;width:40px;height:40px;border-radius:50%;align-items:center;justify-content:center;cursor:pointer;border:none;}
        .sidebar-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background-color:rgba(0,0,0,0.5);z-index:998;}
        .add-product-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background-color:rgba(0,0,0,0.5);z-index:1000;justify-content:center;align-items:center;}
        .add-product-form{background-color:white;width:90%;max-width:600px;border-radius:10px;box-shadow:0 2px 20px rgba(0,0,0,0.2);padding:20px;position:relative;}
        .form-close{position:absolute;top:15px;left:15px;font-size:1.5rem;color:#777;cursor:pointer;}
        .form-title{color:#25995C;margin-bottom:20px;text-align:center;}
        .form-group{margin-bottom:15px;}
        .form-label{display:block;margin-bottom:5px;font-weight:bold;}
        .form-control{width:100%;padding:10px;border:1px solid #ddd;border-radius:5px;}
        .form-submit{background-color:#25995C;color:white;border:none;padding:10px 15px;border-radius:5px;cursor:pointer;width:100%;font-weight:bold;font-size:1rem;transition:all 0.3s;}
        .form-submit:hover{background-color:#1e7a48;}
        @media (max-width:992px){
            .sidebar{width:240px;transform:translateX(240px);z-index:999;}
            .sidebar.active{transform:translateX(0);}
            .main-content{width:100%;margin-right:0;}
            .hamburger{display:flex;}
            .sidebar-overlay.active{display:block;}
        }
        @media (max-width:768px){
            .header{flex-direction:column;align-items:flex-start;}
            .header-actions{margin-top:10px;width:100%;}
            .header-actions a{display:block;text-align:center;margin-top:10px;}
            .table{display:block;overflow-x:auto;white-space:nowrap;}
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <button class="hamburger" id="toggleSidebar"><i class="fas fa-bars"></i></button>
        
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3>أرزاق بلس</h3>
                <p>لوحة تحكم المتجر</p>
            </div>
            <div class="sidebar-menu">
                <div class="sidebar-menu-item" onclick="window.location.href='market_dashboard.php'">
                    <i class="fas fa-home"></i>
                    <span>الرئيسية</span>
                </div>
                <div class="sidebar-menu-item active">
                    <i class="fas fa-box"></i>
                    <span>المنتجات</span>
                </div>
                <div class="sidebar-menu-item" onclick="window.location.href='orders.php'">
                    <i class="fas fa-shopping-cart"></i>
                    <span>الطلبات</span>
                </div>
                <div class="sidebar-menu-item" onclick="window.location.href='statistics.php'">
                    <i class="fas fa-chart-bar"></i>
                    <span>الإحصائيات</span>
                </div>
                <div class="sidebar-menu-item" onclick="window.location.href='finances.php'">
                    <i class="fas fa-wallet"></i>
                    <span>المالية</span>
                </div>
                <div class="sidebar-menu-item" onclick="window.location.href='settings.php'">
                    <i class="fas fa-cog"></i>
                    <span>الإعدادات</span>
                </div>
            </div>
            <div class="sidebar-footer">
                <a href="dashboard.php?logout=1">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>تسجيل الخروج</span>
                </a>
            </div>
        </div>
        
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <div class="main-content">
            <div class="header">
                <h2>إدارة المنتجات</h2>
                <div class="header-actions">
                    <a href="javascript:void(0);" id="showAddProductForm"><i class="fas fa-plus"></i> إضافة منتج جديد</a>
                </div>
            </div>
            
            <?php if(isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if(isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <div class="section">
                <?php if(empty($products)): ?>
                <div class="no-products">
                    <i class="fas fa-box-open" style="font-size: 3rem; color: #ddd; margin-bottom: 15px;"></i>
                    <p>لا توجد منتجات متاحة</p>
                </div>
                <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">الصورة</th>
                                <th>اسم المنتج</th>
                                <th>الفئة</th>
                                <th>السعر</th>
                                <th style="width: 80px;">الحالة</th>
                                <th style="width: 120px;">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($products as $product): ?>
                            <tr>
                                <td>
                                    <?php if(!empty($product['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                                    <?php else: ?>
                                    <img src="img/no-image.jpg" alt="No Image" class="product-image">
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                <td><?php echo number_format($product['price'], 2); ?> SAR</td>
                                <td>
                                    <label class="toggle-btn">
                                        <input type="checkbox" <?php echo (isset($product['is_active']) && $product['is_active'] == 1) ? 'checked' : ''; ?> 
                                               onchange="window.location.href='products.php?toggle_status=<?php echo $product['product_id']; ?>'">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </td>
                                <td>
                                    <a href="edit-product.php?id=<?php echo $product['product_id']; ?>" class="action-btn" title="تعديل"><i class="fas fa-edit"></i></a>
                                    <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $product['product_id']; ?>)" class="action-btn delete-btn" title="حذف"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="add-product-overlay" id="addProductOverlay">
        <div class="add-product-form">
            <div class="form-close" id="closeAddProductForm"><i class="fas fa-times"></i></div>
            <h3 class="form-title">إضافة منتج جديد</h3>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">اسم المنتج</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">الفئة</label>
                    <select name="category_id" class="form-control" required>
                        <?php foreach($categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>"><?php echo htmlspecialchars($category['category_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">السعر (SAR)</label>
                    <input type="number" step="0.01" name="price" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">الوصف</label>
                    <textarea name="description" class="form-control" rows="4"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">صورة المنتج</label>
                    <input type="file" name="image" class="form-control">
                </div>
                <input type="hidden" name="add_product" value="1">
                <button type="submit" class="form-submit">إضافة المنتج</button>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const toggleSidebar = document.getElementById('toggleSidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const addProductOverlay = document.getElementById('addProductOverlay');
            const showAddProductForm = document.getElementById('showAddProductForm');
            const closeAddProductForm = document.getElementById('closeAddProductForm');
            
            toggleSidebar.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
            });
            
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            });
            
            showAddProductForm.addEventListener('click', function() {
                addProductOverlay.style.display = 'flex';
            });
            
            closeAddProductForm.addEventListener('click', function() {
                addProductOverlay.style.display = 'none';
            });
            
            window.addEventListener('click', function(event) {
                if (event.target === addProductOverlay) {
                    addProductOverlay.style.display = 'none';
                }
            });
            
            function adjustLayout() {
                if (window.innerWidth <= 992) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                }
            }
            
            window.addEventListener('resize', adjustLayout);
            adjustLayout();
        });
        
        function confirmDelete(productId) {
            if (confirm('هل أنت متأكد من رغبتك في حذف هذا المنتج؟ لا يمكن التراجع عن هذا الإجراء.')) {
                window.location.href = 'products.php?delete=' + productId;
            }
        }
    </script>
</body>
</html>