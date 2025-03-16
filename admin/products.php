<?php
session_start();
include 'db.php';
if (!isset($_SESSION['admin_id'])) {header('Location: login.php');exit();}
$adminInfo = $conn->query("SELECT username FROM admin WHERE admin_id = " . $_SESSION['admin_id'])->fetch_assoc();
$products = $conn->query("SELECT p.product_id, p.name, p.price, p.image_path, p.is_local, p.is_new, p.points, p.has_cashback, p.cashback_percentage, m.name as market_name, c.category_name 
                          FROM products p 
                          JOIN markets m ON p.market_id = m.market_id 
                          JOIN categories c ON p.category_id = c.category_id 
                          ORDER BY p.product_id DESC");
$markets = $conn->query("SELECT market_id, name FROM markets ORDER BY name");
$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
if (isset($_GET['delete'])) {
    $productId = intval($_GET['delete']);
    $conn->query("DELETE FROM products WHERE product_id = $productId");
    header('Location: products.php?deleted=true');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم أرزاق - المنتجات</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../ui/logo.png">
    <link rel="stylesheet" href="admin.css">
    <style>.switch-container{display:flex;align-items:center;justify-content:space-between;margin-bottom:15px;background-color:#f9f9f9;padding:12px 15px;border-radius:8px;transition:all 0.3s;}.switch-container:hover{background-color:#f2f2f2;}.switch-label{font-weight:600;color:var(--text-color);}.switch{position:relative;display:inline-block;width:80px;height:34px;}.switch input{opacity:0;width:0;height:0;}.slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background-color:#f1f1f1;transition:.3s;border-radius:34px;box-shadow:inset 0 0 5px rgba(0,0,0,0.1);}.slider:before{position:absolute;content:"";height:26px;width:26px;left:10px;bottom:4px;background-color:white;transition:.3s;border-radius:50%;box-shadow:0 2px 5px rgba(0,0,0,0.2);}input:checked + .slider{background:linear-gradient(to right, var(--primary-color), #27ae60);}input:focus + .slider{box-shadow:0 0 3px var(--primary-color);}input:checked + .slider:before{transform:translateX(36px);}.switch-labels{display:flex;justify-content:space-between;font-size:0.85rem;margin-top:5px;}.switch-values{width:85px;display:flex;justify-content:space-between;}.form-row{display:flex;flex-wrap:wrap;gap:20px;margin-bottom:10px;}.form-col{flex:1 1 280px;}.points-info{font-size:0.85rem;color:#666;margin-top:5px;}.badge{display:inline-block;padding:3px 8px;border-radius:4px;font-size:0.8rem;margin-right:5px;}.badge-success{background-color:rgba(46, 204, 113, 0.2);color:var(--success-color);}.badge-secondary{background-color:rgba(52, 152, 219, 0.2);color:var(--info-color);}.badge-warning{background-color:rgba(241, 196, 15, 0.2);color:#d35400;}.actions-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;background-color:white;padding:15px;border-radius:10px;box-shadow:0 2px 5px rgba(0,0,0,0.05);}.filter-options{display:flex;gap:15px;}.filter-select{padding:8px 15px;border-radius:5px;border:1px solid var(--border-color);background-color:white;color:var(--text-color);font-size:0.9rem;font-family:'Cairo', sans-serif;min-width:150px;}.action-btn{padding:8px 15px;border-radius:5px;border:none;font-family:'Cairo', sans-serif;font-weight:600;font-size:0.9rem;cursor:pointer;display:flex;align-items:center;transition:all 0.3s;}.action-btn i{margin-left:8px;}.action-btn.primary{background-color:var(--primary-color);color:white;}.action-btn.primary:hover{background-color:#1d7d4a;}.action-btn.edit{background-color:var(--info-color);color:white;margin-left:5px;padding:5px 10px;}.action-btn.edit:hover{background-color:#2980b9;}.action-btn.delete{background-color:var(--danger-color);color:white;padding:5px 10px;}.action-btn.delete:hover{background-color:#c0392b;}.data-table-container{background-color:white;border-radius:10px;box-shadow:0 2px 5px rgba(0,0,0,0.05);overflow:hidden;}.data-table{width:100%;border-collapse:collapse;}.data-table th{background-color:var(--light-gray);color:var(--text-color);padding:12px 15px;text-align:right;font-weight:600;border-bottom:1px solid var(--border-color);}.data-table td{padding:12px 15px;border-bottom:1px solid var(--border-color);vertical-align:middle;}.data-table tr:last-child td{border-bottom:none;}.data-table tr:hover{background-color:rgba(0,0,0,0.01);}.product-image{width:60px;height:60px;border-radius:5px;overflow:hidden;display:flex;align-items:center;justify-content:center;border:1px solid var(--border-color);}.product-image img{max-width:100%;max-height:100%;object-fit:cover;}.action-buttons{display:flex;justify-content:flex-start;}.no-data{text-align:center;color:#666;padding:20px 0;}.modal{display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;overflow:none;background-color:rgba(0,0,0,0.5);}.modal-content{background-color:white;margin:2% auto;padding:20px;border-radius:12px;box-shadow:0 5px 25px rgba(0,0,0,0.25);max-width:800px;width:95%;position:relative;max-height:85vh;overflow-y:auto;scrollbar-width:thin;}.modal-content::-webkit-scrollbar{width:6px;}.modal-content::-webkit-scrollbar-thumb{background-color:rgba(0,0,0,0.2);border-radius:3px;}.close-modal{position:absolute;top:15px;left:15px;font-size:1.5rem;cursor:pointer;color:#666;transition:all 0.3s;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:50%;}.close-modal:hover{color:var(--danger-color);background-color:rgba(231,76,60,0.1);}.modal h2{margin-bottom:25px;color:var(--text-color);text-align:right;font-size:1.6rem;padding-bottom:10px;border-bottom:2px solid #f1f1f1;}.form-group{margin-bottom:15px;transition:all 0.3s;}.form-group:hover label{color:var(--primary-color);}.form-group label{display:block;margin-bottom:8px;font-weight:600;color:var(--text-color);}.form-group input[type="text"],.form-group input[type="number"],.form-group select,.form-group textarea{width:100%;padding:12px 15px;border-radius:8px;border:1px solid var(--border-color);font-family:'Cairo', sans-serif;font-size:0.95rem;transition:all 0.3s;}.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--primary-color);box-shadow:0 0 0 2px rgba(46,204,113,0.2);}.form-group textarea{resize:vertical;min-height:100px;}.form-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:25px;}.btn{padding:12px 24px;border-radius:8px;border:none;font-family:'Cairo', sans-serif;font-weight:600;font-size:0.95rem;cursor:pointer;transition:all 0.3s;}.btn.primary{background:linear-gradient(to right, var(--primary-color), #27ae60);color:white;}.btn.primary:hover{background:linear-gradient(to right, #1d7d4a, #218c53);box-shadow:0 4px 8px rgba(46,204,113,0.2);}.btn.danger{background-color:var(--danger-color);color:white;text-decoration:none;display:inline-block;text-align:center;}.btn.danger:hover{background-color:#c0392b;}.btn:not(.primary):not(.danger){background-color:#ddd;color:#333;}.btn:not(.primary):not(.danger):hover{background-color:#ccc;}.alert{padding:15px;border-radius:5px;margin-bottom:20px;display:flex;align-items:center;position:relative;}.alert i{margin-left:10px;font-size:1.2rem;}.alert.alert-success{background-color:rgba(46,204,113,0.1);color:var(--success-color);border:1px solid rgba(46,204,113,0.2);}.alert.alert-danger{background-color:rgba(231,76,60,0.1);color:var(--danger-color);border:1px solid rgba(231,76,60,0.2);}.close-alert{position:absolute;top:12px;left:15px;background:none;border:none;font-size:1.2rem;cursor:pointer;color:inherit;opacity:0.7;}.close-alert:hover{opacity:1;}@media (max-width:992px){.actions-bar{flex-direction:column;align-items:stretch;gap:15px;}.filter-options{flex-wrap:wrap;}.filter-select{flex-grow:1;}.action-btn.primary{align-self:flex-end;}}@media (max-width:768px){.data-table-container{overflow-x:auto;}.data-table{min-width:800px;}.modal-content{margin:5% auto;max-height:90vh;}.form-row{flex-direction:column;gap:5px;}.form-col{flex:1 1 100%;}}@media (max-width:576px){.form-actions{flex-direction:column;}.btn{width:100%;}}</style>
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
                <a href="categories.php" class="menu-item">
                    <i class="fas fa-th-large"></i>
                    <span>الفئات</span>
                </a>
                <a href="products.php" class="menu-item active">
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
                <h1>إدارة المنتجات</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="dashboard.php">الرئيسية</a>
                    </div>
                    <div class="breadcrumb-item">المنتجات</div>
                </div>
            </div>
            <?php if(isset($_GET['deleted'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                تم حذف المنتج بنجاح
                <button class="close-alert">&times;</button>
            </div>
            <?php endif; ?>
            <?php if(isset($_GET['added'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                تم إضافة المنتج بنجاح
                <button class="close-alert">&times;</button>
            </div>
            <?php endif; ?>
            <?php if(isset($_GET['updated'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                تم تحديث المنتج بنجاح
                <button class="close-alert">&times;</button>
            </div>
            <?php endif; ?>
            <div class="actions-bar">
                <div class="filter-options">
                    <select id="marketFilter" class="filter-select">
                        <option value="">جميع الأسواق</option>
                        <?php 
                        $markets->data_seek(0);
                        while($market = $markets->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $market['name']; ?>"><?php echo $market['name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                    <select id="categoryFilter" class="filter-select">
                        <option value="">جميع الفئات</option>
                        <?php 
                        $categories->data_seek(0);
                        while($category = $categories->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $category['category_name']; ?>"><?php echo $category['category_name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button id="addProductBtn" class="action-btn primary">
                    <i class="fas fa-plus"></i>
                    إضافة منتج جديد
                </button>
            </div>
            <div class="data-table-container">
                <table id="productsTable" class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الصورة</th>
                            <th>اسم المنتج</th>
                            <th>السعر</th>
                            <th>الفئة</th>
                            <th>السوق</th>
                            <th>الحالة</th>
                            <th>النقاط</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($product = $products->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $product['product_id']; ?></td>
                            <td>
                                <div class="product-image">
                                    <img src="<?php echo str_replace('pro/', '../pro/', $product['image_path']) ?: '../images/placeholder.png'; ?>" alt="<?php echo $product['name']; ?>">
                                </div>
                            </td>
                            <td><?php echo $product['name']; ?></td>
                            <td><?php echo number_format($product['price'], 2) . ' ريال'; ?></td>
                            <td><?php echo $product['category_name']; ?></td>
                            <td><?php echo $product['market_name']; ?></td>
                            <td>
                                <?php if($product['is_local'] == 1): ?>
                                <span class="badge badge-success">محلي</span>
                                <?php else: ?>
                                <span class="badge badge-secondary">مستورد</span>
                                <?php endif; ?>
                                
                                <?php if($product['is_new'] == 1): ?>
                                <span class="badge badge-success">جديد</span>
                                <?php else: ?>
                                <span class="badge badge-warning">مستعمل</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $product['points']; ?> نقطة
                                <?php if($product['has_cashback'] == 1): ?>
                                <br><small><?php echo $product['cashback_percentage']; ?>% استرداد نقدي</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn edit" data-id="<?php echo $product['product_id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn delete" data-id="<?php echo $product['product_id']; ?>" data-name="<?php echo $product['name']; ?>">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if($products->num_rows === 0): ?>
                        <tr>
                            <td colspan="9" class="no-data">لا توجد منتجات للعرض</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <div class="modal" id="productModal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2 id="modalTitle">إضافة منتج جديد</h2>
            <form id="productForm" action="product_process.php" method="post" enctype="multipart/form-data">
                <input type="hidden" id="productId" name="productId" value="">
                <input type="hidden" name="imagePath" id="imagePath" value="">
                <div class="form-group">
                    <label for="productName">اسم المنتج</label>
                    <input type="text" id="productName" name="productName" required>
                </div>
                <div class="form-group">
                    <label for="productPrice">السعر (ريال)</label>
                    <input type="number" id="productPrice" name="productPrice" step="0.01" min="0" required>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="productCategory">الفئة</label>
                            <select id="productCategory" name="productCategory" required>
                                <option value="">اختر الفئة</option>
                                <?php 
                                $categoriesForSelect = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
                                while($cat = $categoriesForSelect->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $cat['category_id']; ?>"><?php echo $cat['category_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="productMarket">السوق</label>
                            <select id="productMarket" name="productMarket" required>
                                <option value="">اختر السوق</option>
                                <?php 
                                $marketsForSelect = $conn->query("SELECT market_id, name FROM markets ORDER BY name");
                                while($mkt = $marketsForSelect->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $mkt['market_id']; ?>"><?php echo $mkt['name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="switch-container">
                        <span class="switch-label">حالة المنتج:</span>
                        <div>
                            <label class="switch">
                                <input type="checkbox" id="isLocal" name="isLocal" value="1" checked>
                                <span class="slider"></span>
                            </label>
                            <div class="switch-labels">
                                <div class="switch-values">
                                <span>محلي</span>
                                <span>مستورد</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="switch-container">
                        <span class="switch-label">حالة الاستخدام:</span>
                        <div>
                            <label class="switch">
                                <input type="checkbox" id="isNew" name="isNew" value="1" checked>
                                <span class="slider"></span>
                            </label>
                            <div class="switch-labels">
                                <div class="switch-values">
                                <span>جديد</span>
                                    <span>مستعمل</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="productPoints">النقاط</label>
                    <input type="number" id="productPoints" name="productPoints" value="0" min="0">
                    <div class="points-info">يتم احتساب 5 نقاط لكل 50 ريال</div>
                </div>
                <div class="form-group">
                    <div class="switch-container">
                        <span class="switch-label">استرداد نقدي:</span>
                        <div>
                            <label class="switch">
                                <input type="checkbox" id="hasCashback" name="hasCashback" value="1">
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="cashbackPercentage">نسبة الاسترداد النقدي (%)</label>
                    <input type="number" id="cashbackPercentage" name="cashbackPercentage" value="10" step="0.01" min="0" disabled>
                    <div class="points-info">10% من قيمة النقاط</div>
                </div>
                <div class="form-group">
                    <label for="productDescription">وصف المنتج</label>
                    <textarea id="productDescription" name="productDescription" rows="4"></textarea>
                </div>
                <div class="form-group">
                    <label for="productImage">صورة المنتج</label>
                    <input type="file" id="productImage" name="productImage" accept="image/*">
                    <div id="currentImageContainer" style="display: none;">
                        <p>الصورة الحالية:</p>
                        <img id="currentImage" src="" alt="Current Image" style="max-width: 100px; max-height: 100px;">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn primary">حفظ</button>
                    <button type="button" class="btn" id="cancelBtn">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2>حذف المنتج</h2>
            <p>هل أنت متأكد من حذف المنتج: <span id="deleteProductName"></span>؟</p>
            <div class="form-actions">
                <a href="#" id="confirmDelete" class="btn danger">نعم، حذف</a>
                <button type="button" class="btn" id="cancelDeleteBtn">إلغاء</button>
            </div>
        </div>
    </div>
    <div class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </div>
    <script>document.getElementById('menuToggle').addEventListener('click', function() {document.querySelector('.sidebar').classList.toggle('active');}); document.addEventListener('click', function(event) {const sidebar = document.querySelector('.sidebar'); const menuToggle = document.getElementById('menuToggle'); if (window.innerWidth <= 768 && !sidebar.contains(event.target) && !menuToggle.contains(event.target) && sidebar.classList.contains('active')) {sidebar.classList.remove('active');}}); const productModal = document.getElementById('productModal'); const deleteModal = document.getElementById('deleteModal'); const addProductBtn = document.getElementById('addProductBtn'); const closeModal = document.querySelectorAll('.close-modal'); const cancelBtn = document.getElementById('cancelBtn'); const cancelDeleteBtn = document.getElementById('cancelDeleteBtn'); const productForm = document.getElementById('productForm'); const deleteProductName = document.getElementById('deleteProductName'); const confirmDelete = document.getElementById('confirmDelete'); const editButtons = document.querySelectorAll('.action-btn.edit'); const deleteButtons = document.querySelectorAll('.action-btn.delete'); const closeAlerts = document.querySelectorAll('.close-alert'); const marketFilter = document.getElementById('marketFilter'); const categoryFilter = document.getElementById('categoryFilter'); const productsTable = document.getElementById('productsTable'); const productPrice = document.getElementById('productPrice'); const productPoints = document.getElementById('productPoints'); const hasCashbackCheckbox = document.getElementById('hasCashback'); const cashbackPercentage = document.getElementById('cashbackPercentage'); addProductBtn.addEventListener('click', function() {document.getElementById('modalTitle').textContent = 'إضافة منتج جديد'; productForm.reset(); document.getElementById('productId').value = ''; document.getElementById('imagePath').value = ''; document.getElementById('currentImageContainer').style.display = 'none'; document.getElementById('isLocal').checked = true; document.getElementById('isNew').checked = true; document.getElementById('hasCashback').checked = false; document.getElementById('cashbackPercentage').disabled = true; productModal.style.display = 'block';}); closeModal.forEach(function(btn) {btn.addEventListener('click', function() {productModal.style.display = 'none'; deleteModal.style.display = 'none';})}); cancelBtn.addEventListener('click', function() {productModal.style.display = 'none';}); cancelDeleteBtn.addEventListener('click', function() {deleteModal.style.display = 'none';}); productPrice.addEventListener('input', function() {const price = parseFloat(this.value) || 0; const points = Math.floor(price / 50) * 5; productPoints.value = points;}); hasCashbackCheckbox.addEventListener('change', function() {cashbackPercentage.disabled = !this.checked;}); editButtons.forEach(function(btn) {btn.addEventListener('click', function() {const productId = this.getAttribute('data-id'); document.getElementById('modalTitle').textContent = 'تعديل المنتج'; fetch('get_product.php?id=' + productId).then(response => response.json()).then(data => {document.getElementById('productId').value = data.product_id; document.getElementById('productName').value = data.name; document.getElementById('productPrice').value = data.price; document.getElementById('productCategory').value = data.category_id; document.getElementById('productMarket').value = data.market_id; document.getElementById('productDescription').value = data.description || ''; document.getElementById('imagePath').value = data.image_path || ''; document.getElementById('isLocal').checked = data.is_local == 1; document.getElementById('isNew').checked = data.is_new == 1; document.getElementById('productPoints').value = data.points || 0; document.getElementById('hasCashback').checked = data.has_cashback == 1; document.getElementById('cashbackPercentage').value = data.cashback_percentage || 10; document.getElementById('cashbackPercentage').disabled = !data.has_cashback; if (data.image_path) {document.getElementById('currentImage').src = data.image_path.replace('pro/', '../pro/'); document.getElementById('currentImageContainer').style.display = 'block';} else {document.getElementById('currentImageContainer').style.display = 'none';} productModal.style.display = 'block';}).catch(error => console.error('Error:', error));})}); deleteButtons.forEach(function(btn) {btn.addEventListener('click', function() {const productId = this.getAttribute('data-id'); const productName = this.getAttribute('data-name'); deleteProductName.textContent = productName; confirmDelete.href = 'products.php?delete=' + productId; deleteModal.style.display = 'block';})}); closeAlerts.forEach(function(btn) {btn.addEventListener('click', function() {this.parentElement.style.display = 'none';})}); marketFilter.addEventListener('change', filterTable); categoryFilter.addEventListener('change', filterTable); function filterTable() {const market = marketFilter.value.toLowerCase(); const category = categoryFilter.value.toLowerCase(); const rows = productsTable.getElementsByTagName('tbody')[0].getElementsByTagName('tr'); for (let i = 0; i < rows.length; i++) {const marketCell = rows[i].getElementsByTagName('td')[5]; const categoryCell = rows[i].getElementsByTagName('td')[4]; if (!marketCell || !categoryCell) continue; const marketValue = marketCell.textContent.toLowerCase(); const categoryValue = categoryCell.textContent.toLowerCase(); const showRow = (market === '' || marketValue.includes(market)) && (category === '' || categoryValue.includes(category)); rows[i].style.display = showRow ? '' : 'none';}} window.onclick = function(event) {if (event.target == productModal) {productModal.style.display = 'none';} if (event.target == deleteModal) {deleteModal.style.display = 'none';}}</script>
</body>
</html>