<?php
session_start();
include 'db.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
$adminInfo = $conn->query("SELECT username FROM admin WHERE admin_id = " . $_SESSION['admin_id'])->fetch_assoc();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_category'])) {
        $categoryName = $conn->real_escape_string($_POST['category_name']);
        $parentCategoryId = !empty($_POST['parent_category_id']) ? (int)$_POST['parent_category_id'] : null;
        $isMain = isset($_POST['is_main']) ? 1 : 0;
        $imagePath = null;
        if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] == 0) {
            $uploadDir = '../cat/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = basename($_FILES['category_image']['name']);
            $uploadFile = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['category_image']['tmp_name'], $uploadFile)) {
                $imagePath = 'cat/' . $fileName;
            }
        }
        if ($isMain) {
            $parentCategoryId = null;
        }
        $stmt = $conn->prepare("INSERT INTO categories (category_name, parent_category_id, image_path, is_main) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sisi", $categoryName, $parentCategoryId, $imagePath, $isMain);
        if ($stmt->execute()) {
            $successMessage = "تم إضافة الفئة بنجاح";
        } else {
            $errorMessage = "حدث خطأ أثناء إضافة الفئة: " . $conn->error;
        }
        $stmt->close();
    }
    elseif (isset($_POST['update_category'])) {
        $categoryId = (int)$_POST['category_id'];
        $categoryName = $conn->real_escape_string($_POST['category_name']);
        $parentCategoryId = !empty($_POST['parent_category_id']) ? (int)$_POST['parent_category_id'] : null;
        $isMain = isset($_POST['is_main']) ? 1 : 0;
        if ($isMain) {
            $parentCategoryId = null;
        }
        $imagePath = $_POST['current_image'];
        if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] == 0) {
            $uploadDir = '../cat/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = basename($_FILES['category_image']['name']);
            $uploadFile = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['category_image']['tmp_name'], $uploadFile)) {
                $imagePath = 'cat/' . $fileName;
            }
        }
        $stmt = $conn->prepare("UPDATE categories SET category_name = ?, parent_category_id = ?, image_path = ?, is_main = ? WHERE category_id = ?");
        $stmt->bind_param("sisii", $categoryName, $parentCategoryId, $imagePath, $isMain, $categoryId);
        if ($stmt->execute()) {
            $successMessage = "تم تحديث الفئة بنجاح";
        } else {
            $errorMessage = "حدث خطأ أثناء تحديث الفئة: " . $conn->error;
        }
        $stmt->close();
    }
    elseif (isset($_POST['delete_category'])) {
        $categoryId = (int)$_POST['category_id'];
        $checkSub = $conn->query("SELECT COUNT(*) as count FROM categories WHERE parent_category_id = $categoryId");
        $hasSubcategories = $checkSub->fetch_assoc()['count'] > 0;
        $checkProducts = $conn->query("SELECT COUNT(*) as count FROM products WHERE category_id = $categoryId");
        $hasProducts = $checkProducts->fetch_assoc()['count'] > 0;
        if ($hasSubcategories) {
            $errorMessage = "لا يمكن حذف الفئة لأنها تحتوي على فئات فرعية";
        } 
        elseif ($hasProducts) {
            $errorMessage = "لا يمكن حذف الفئة لأنها تحتوي على منتجات";
        } 
        else {
            $result = $conn->query("SELECT image_path FROM categories WHERE category_id = $categoryId");
            $category = $result->fetch_assoc();
            if ($conn->query("DELETE FROM categories WHERE category_id = $categoryId")) {
                if (!empty($category['image_path'])) {
                    $fullPath = '../' . $category['image_path'];
                    if (file_exists($fullPath)) {
                        unlink($fullPath);
                    }
                }
                $successMessage = "تم حذف الفئة بنجاح";
            } else {
                $errorMessage = "حدث خطأ أثناء حذف الفئة: " . $conn->error;
            }
        }
    }
}
$categories = $conn->query("SELECT c.*, 
                          (SELECT COUNT(*) FROM products WHERE category_id = c.category_id) AS product_count,
                          (SELECT COUNT(*) FROM categories WHERE parent_category_id = c.category_id) AS subcategory_count,
                          p.category_name AS parent_name
                          FROM categories c
                          LEFT JOIN categories p ON c.parent_category_id = p.category_id
                          ORDER BY c.is_main DESC, c.category_name ASC");
$mainCategories = $conn->query("SELECT category_id, category_name FROM categories WHERE is_main = 1 OR parent_category_id IS NULL ORDER BY category_name ASC");
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم أرزاق - الفئات</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../ui/logo.png">
    <link rel="stylesheet" href="admin.css">
    <style>
        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .category-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.08);
        }
        .category-image {
            height: 160px;
            background-size: cover;
            background-position: center;
            background-color: var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #aaa;
        }
        .category-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .category-details {
            padding: 15px;
        }
        .category-name {
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
        }
        .category-name .badge {
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 12px;
            background-color: var(--primary-light);
            color: var(--primary-color);
        }
        .category-meta {
            display: flex;
            justify-content: space-between;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        .category-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }
        .category-actions button {
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background-color 0.3s;
        }
        .edit-btn {
            background-color: var(--info-color);
            color: white;
        }
        .delete-btn {
            background-color: var(--danger-color);
            color: white;
        }
        .edit-btn:hover, .delete-btn:hover {
            opacity: 0.9;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 50px auto;
            padding: 20px;
            border-radius: 10px;
            max-width: 600px;
            width: 90%;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        .modal-title {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .close-btn {
            font-size: 1.5rem;
            cursor: pointer;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-family: 'Cairo', sans-serif;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .checkbox-group label {
            margin-right: 10px;
            margin-bottom: 0;
        }
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Cairo', sans-serif;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        .btn-secondary {
            background-color: var(--light-gray);
            color: var(--text-color);
        }
        .btn-primary:hover, .btn-secondary:hover {
            opacity: 0.9;
        }
        .alert {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background-color: rgba(46,204,113,0.1);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }
        .alert-danger {
            background-color: rgba(231,76,60,0.1);
            color: var(--danger-color);
            border: 1px solid var(--danger-color);
        }
        .subcategory-indicator {
            margin-right: 5px;
            font-size: 0.8rem;
            color: #666;
        }
        .filter-controls {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .search-form {
            display: flex;
            flex: 1;
            max-width: 300px;
        }
        .search-form input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 5px 0 0 5px;
            font-family: 'Cairo', sans-serif;
        }
        .search-form button {
            padding: 8px 12px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 0 5px 5px 0;
            cursor: pointer;
        }
        .overview-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .view-mode-controls {
            display: flex;
            gap: 10px;
        }
        .view-mode-btn {
            padding: 8px 12px;
            background-color: var(--light-gray);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .view-mode-btn.active {
            background-color: var(--primary-color);
            color: white;
        }
        .hidden {
            display: none;
        }
        #categoriesTable {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        #categoriesTable th, #categoriesTable td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid var(--border-color);
        }
        #categoriesTable th {
            background-color: var(--light-gray);
            font-weight: 600;
        }
        #categoriesTable tr:hover {
            background-color: rgba(0,0,0,0.02);
        }
        .table-actions {
            display: flex;
            gap: 5px;
        }
        .table-image {
            width: 60px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
        }
        @media (max-width: 768px) {
                .overview-cards {
                    grid-template-columns: repeat(2, 1fr);
                }
                .filter-controls {
                    flex-direction: column;
                    gap: 10px;
                }
                .search-form {
                    max-width: 100%;
                }
                .view-mode-controls {
                    justify-content: flex-end;
                }
                #categoriesTable {
                    min-width: 700px;
                }
                .table-container {
                    overflow-x: auto;
                }
            }
            @media (max-width: 576px) {
                .overview-cards {
                    grid-template-columns: 1fr;
                }
                .category-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>أرزاق</h2>
                <p>لوحة تحكم الإدارة</p>
            </div>
            <nav class="sidebar-menu">
                <a href="dashboard.php" class="menu-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>الرئيسية</span>
                </a>
                <a href="markets.php" class="menu-item">
                    <i class="fas fa-store"></i>
                    <span>الأسواق</span>
                </a>
                <a href="categories.php" class="menu-item active">
                    <i class="fas fa-th-large"></i>
                    <span>الفئات</span>
                </a>
                <a href="products.php" class="menu-item">
                    <i class="fas fa-box"></i>
                    <span>المنتجات</span>
                </a>
                <a href="orders.php" class="menu-item">
                    <i class="fas fa-shopping-cart"></i>
                    <span>الطلبات</span>
                </a>
                <a href="users.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>المستخدمين</span>
                </a>
                <a href="reports.php" class="menu-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>التقارير</span>
                </a>
                <a href="settings.php" class="menu-item">
                    <i class="fas fa-cog"></i>
                    <span>الإعدادات</span>
                </a>
                <a href="logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>تسجيل الخروج</span>
                </a>
            </nav>
        </aside>
        <main class="main-content">
            <div class="top-bar">
                <div class="admin-info">
                    <div class="user-avatar">
                        <?php echo substr($adminInfo['username'] ?? 'A', 0, 1); ?>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?php echo $adminInfo['username'] ?? 'المدير'; ?></span>
                        <span class="user-role">مدير النظام</span>
                    </div>
                </div>
                <div class="top-actions">
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        تسجيل الخروج
                    </a>
                </div>
            </div>
            <div class="content-header">
                <h1>إدارة الفئات</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="dashboard.php">الرئيسية</a>
                    </div>
                    <div class="breadcrumb-item">الفئات</div>
                </div>
            </div>
            <?php if (isset($successMessage)): ?>
                <div class="alert alert-success"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            <?php if (isset($errorMessage)): ?>
                <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
            <?php endif; ?>
            <div class="overview-cards">
                <div class="stat-card">
                    <div class="stat-icon categories">
                        <i class="fas fa-th-large"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-value">
                            <?php 
                                $totalCats = $conn->query("SELECT COUNT(*) as count FROM categories")->fetch_assoc()['count'];
                                echo $totalCats; 
                            ?>
                        </div>
                        <div class="stat-label">إجمالي الفئات</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon markets" style="background-color: #3498db;">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-value">
                            <?php 
                                $mainCats = $conn->query("SELECT COUNT(*) as count FROM categories WHERE is_main = 1 OR parent_category_id IS NULL")->fetch_assoc()['count'];
                                echo $mainCats; 
                            ?>
                        </div>
                        <div class="stat-label">الفئات الرئيسية</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon products" style="background-color: #9b59b6;">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-value">
                            <?php 
                                $subCats = $conn->query("SELECT COUNT(*) as count FROM categories WHERE parent_category_id IS NOT NULL")->fetch_assoc()['count'];
                                echo $subCats; 
                            ?>
                        </div>
                        <div class="stat-label">الفئات الفرعية</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orders" style="background-color: #f39c12;">
                        <i class="fas fa-shopping-basket"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-value">
                            <?php 
                                $prodCount = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
                                echo $prodCount; 
                            ?>
                        </div>
                        <div class="stat-label">إجمالي المنتجات</div>
                    </div>
                </div>
            </div>
            <div class="filter-controls">
                <button class="btn btn-primary" id="addCategoryBtn">
                    <i class="fas fa-plus"></i> إضافة فئة جديدة
                </button>
                <div class="view-mode-controls">
                    <button class="view-mode-btn active" data-view="grid">
                        <i class="fas fa-th"></i>
                    </button>
                    <button class="view-mode-btn" data-view="table">
                        <i class="fas fa-list"></i>
                    </button>
                </div>
            </div>
            <div id="gridView" class="category-grid">
                <?php if ($categories->num_rows > 0): ?>
                    <?php while ($category = $categories->fetch_assoc()): ?>
                        <div class="category-card">
                            <div class="category-image">
                                <?php if (!empty($category['image_path'])): ?>
                                    <img src="../<?php echo $category['image_path']; ?>" alt="<?php echo $category['category_name']; ?>">
                                <?php else: ?>
                                    <i class="fas fa-image fa-3x"></i>
                                <?php endif; ?>
                            </div>
                            <div class="category-details">
                                <div class="category-name">
                                    <?php echo $category['category_name']; ?>
                                    <?php if ($category['is_main'] || $category['parent_category_id'] === null): ?>
                                        <span class="badge">رئيسية</span>
                                    <?php else: ?>
                                        <span class="subcategory-indicator">
                                            <i class="fas fa-level-up-alt fa-rotate-90"></i> <?php echo $category['parent_name']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="category-meta">
                                    <span>
                                        <i class="fas fa-box"></i> <?php echo $category['product_count']; ?> منتج
                                    </span>
                                    <?php if ($category['is_main'] || $category['parent_category_id'] === null): ?>
                                        <span>
                                            <i class="fas fa-folder"></i> <?php echo $category['subcategory_count']; ?> فئة فرعية
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="category-actions">
                                    <button class="edit-btn" onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                        <i class="fas fa-edit"></i> تعديل
                                    </button>
                                    <button class="delete-btn" onclick="confirmDelete(<?php echo $category['category_id']; ?>, '<?php echo $category['category_name']; ?>')">
                                        <i class="fas fa-trash"></i> حذف
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 20px;">
                        <i class="fas fa-folder-open fa-3x" style="color: #ddd; margin-bottom: 10px;"></i>
                        <p>لا توجد فئات لعرضها</p>
                    </div>
                <?php endif; ?>
            </div>
            <div id="tableView" class="hidden">
                <div class="table-container">
                    <table id="categoriesTable">
                        <thead>
                            <tr>
                                <th width="70">الصورة</th>
                                <th>الاسم</th>
                                <th>النوع</th>
                                <th>الفئة الأم</th>
                                <th>عدد المنتجات</th>
                                <th>الفئات الفرعية</th>
                                <th width="120">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($categories->num_rows > 0): 
                                $categories->data_seek(0);
                                while ($category = $categories->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($category['image_path'])): ?>
                                            <img src="../<?php echo $category['image_path']; ?>" alt="<?php echo $category['category_name']; ?>" class="table-image">
                                        <?php else: ?>
                                            <i class="fas fa-image" style="color: #ddd;"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $category['category_name']; ?></td>
                                    <td>
                                        <?php if ($category['is_main'] || $category['parent_category_id'] === null): ?>
                                            <span class="status completed">رئيسية</span>
                                        <?php else: ?>
                                            <span class="status processing">فرعية</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $category['parent_name'] ?: '-'; ?></td>
                                    <td><?php echo $category['product_count']; ?></td>
                                    <td><?php echo $category['subcategory_count']; ?></td>
                                    <td class="table-actions">
                                        <button class="btn edit-btn" onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn delete-btn" onclick="confirmDelete(<?php echo $category['category_id']; ?>, '<?php echo $category['category_name']; ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php 
                                endwhile; 
                            else: 
                            ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 20px;">لا توجد فئات لعرضها</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    <div id="addCategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">إضافة فئة جديدة</h3>
                <span class="close-btn" id="closeAddModal">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="category_name">اسم الفئة</label>
                    <input type="text" id="category_name" name="category_name" class="form-control" required>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="is_main" name="is_main" checked>
                    <label for="is_main">فئة رئيسية</label>
                </div>
                <div class="form-group" id="parentCategoryGroup" style="display: none;">
                    <label for="parent_category_id">الفئة الأم</label>
                    <select name="parent_category_id" id="parent_category_id" class="form-control">
                    <option value="">اختر الفئة الأم</option>
                        <?php 
                        $mainCategories->data_seek(0);
                        while ($mainCat = $mainCategories->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $mainCat['category_id']; ?>">
                                <?php echo $mainCat['category_name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="category_image">صورة الفئة</label>
                    <input type="file" name="category_image" id="category_image" class="form-control">
                </div>
                <div class="form-actions">
                    <button type="submit" name="add_category" class="btn btn-primary">إضافة الفئة</button>
                    <button type="button" class="btn btn-secondary" id="cancelAddBtn">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
    <div id="editCategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">تعديل الفئة</h3>
                <span class="close-btn" id="closeEditModal">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" id="edit_category_id" name="category_id">
                <input type="hidden" id="current_image" name="current_image">
                <div class="form-group">
                    <label for="edit_category_name">اسم الفئة</label>
                    <input type="text" id="edit_category_name" name="category_name" class="form-control" required>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="edit_is_main" name="is_main">
                    <label for="edit_is_main">فئة رئيسية</label>
                </div>
                <div class="form-group" id="editParentCategoryGroup">
                    <label for="edit_parent_category_id">الفئة الأم</label>
                    <select name="parent_category_id" id="edit_parent_category_id" class="form-control">
                        <option value="">اختر الفئة الأم</option>
                        <?php 
                        $mainCategories->data_seek(0);
                        while ($mainCat = $mainCategories->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $mainCat['category_id']; ?>">
                                <?php echo $mainCat['category_name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_category_image">صورة الفئة</label>
                    <input type="file" name="category_image" id="edit_category_image" class="form-control">
                    <div id="current_image_preview" style="margin-top: 10px;"></div>
                </div>
                <div class="form-actions">
                    <button type="submit" name="update_category" class="btn btn-primary">حفظ التغييرات</button>
                    <button type="button" class="btn btn-secondary" id="cancelEditBtn">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
    <div id="deleteCategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">حذف الفئة</h3>
                <span class="close-btn" id="closeDeleteModal">&times;</span>
            </div>
            <div class="modal-body">
                <p>هل أنت متأكد من رغبتك في حذف الفئة <span id="delete_category_name"></span>؟</p>
                <p class="text-danger">تنبيه: لا يمكن التراجع عن هذا الإجراء.</p>
            </div>
            <form method="POST">
                <input type="hidden" id="delete_category_id" name="category_id">
                <div class="form-actions">
                    <button type="submit" name="delete_category" class="btn btn-danger">نعم، حذف</button>
                    <button type="button" class="btn btn-secondary" id="cancelDeleteBtn">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
    <script>document.querySelectorAll('.view-mode-btn').forEach(btn => {btn.addEventListener('click', function() {const viewMode = this.getAttribute('data-view');document.querySelectorAll('.view-mode-btn').forEach(b => b.classList.remove('active'));this.classList.add('active');if (viewMode === 'grid') {document.getElementById('gridView').classList.remove('hidden');document.getElementById('tableView').classList.add('hidden');} else {document.getElementById('gridView').classList.add('hidden');document.getElementById('tableView').classList.remove('hidden');}});});const addCategoryBtn = document.getElementById('addCategoryBtn');const addCategoryModal = document.getElementById('addCategoryModal');const closeAddModal = document.getElementById('closeAddModal');const cancelAddBtn = document.getElementById('cancelAddBtn');const isMainCheckbox = document.getElementById('is_main');const parentCategoryGroup = document.getElementById('parentCategoryGroup');addCategoryBtn.addEventListener('click', function() {addCategoryModal.style.display = 'block';});closeAddModal.addEventListener('click', function() {addCategoryModal.style.display = 'none';});cancelAddBtn.addEventListener('click', function() {addCategoryModal.style.display = 'none';});isMainCheckbox.addEventListener('change', function() {if (this.checked) {parentCategoryGroup.style.display = 'none';} else {parentCategoryGroup.style.display = 'block';}});const editCategoryModal = document.getElementById('editCategoryModal');const closeEditModal = document.getElementById('closeEditModal');const cancelEditBtn = document.getElementById('cancelEditBtn');const editIsMainCheckbox = document.getElementById('edit_is_main');const editParentCategoryGroup = document.getElementById('editParentCategoryGroup');function editCategory(category) {document.getElementById('edit_category_id').value = category.category_id;document.getElementById('edit_category_name').value = category.category_name;document.getElementById('edit_is_main').checked = category.is_main == 1;document.getElementById('edit_parent_category_id').value = category.parent_category_id || '';document.getElementById('current_image').value = category.image_path || '';const imagePreview = document.getElementById('current_image_preview');if (category.image_path) {imagePreview.innerHTML = `<img src="../${category.image_path}" style="max-width: 100px; max-height: 100px;">`;} else {imagePreview.innerHTML = '';}if (category.is_main == 1) {editParentCategoryGroup.style.display = 'none';} else {editParentCategoryGroup.style.display = 'block';}editCategoryModal.style.display = 'block';}closeEditModal.addEventListener('click', function() {editCategoryModal.style.display = 'none';});cancelEditBtn.addEventListener('click', function() {editCategoryModal.style.display = 'none';});editIsMainCheckbox.addEventListener('change', function() {if (this.checked) {editParentCategoryGroup.style.display = 'none';} else {editParentCategoryGroup.style.display = 'block';}});const deleteCategoryModal = document.getElementById('deleteCategoryModal');const closeDeleteModal = document.getElementById('closeDeleteModal');const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');function confirmDelete(categoryId, categoryName) {document.getElementById('delete_category_id').value = categoryId;document.getElementById('delete_category_name').textContent = categoryName;deleteCategoryModal.style.display = 'block';}closeDeleteModal.addEventListener('click', function() {deleteCategoryModal.style.display = 'none';});cancelDeleteBtn.addEventListener('click', function() {deleteCategoryModal.style.display = 'none';});window.addEventListener('click', function(event) {if (event.target === addCategoryModal) {addCategoryModal.style.display = 'none';}if (event.target === editCategoryModal) {editCategoryModal.style.display = 'none';}if (event.target === deleteCategoryModal) {deleteCategoryModal.style.display = 'none';}});</script>
</body>
</html>