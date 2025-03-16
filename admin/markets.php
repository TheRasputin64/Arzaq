<?php
session_start();
include 'db.php';
if (!isset($_SESSION['admin_id'])) {header('Location: login.php');exit();}
$adminInfo = $conn->query("SELECT username FROM admin WHERE admin_id = " . $_SESSION['admin_id'])->fetch_assoc();
$marketsQuery = "SELECT m.*, COUNT(DISTINCT p.product_id) as product_count, COUNT(DISTINCT o.order_id) as order_count 
                FROM markets m 
                LEFT JOIN products p ON m.market_id = p.market_id 
                LEFT JOIN order_items oi ON p.product_id = oi.product_id 
                LEFT JOIN orders o ON oi.order_id = o.order_id 
                GROUP BY m.market_id 
                ORDER BY m.name";
$markets = $conn->query($marketsQuery);
$marketDetails = null;
if(isset($_GET['market_id'])) {
    $marketId = intval($_GET['market_id']);
    $marketDetailsQuery = "SELECT * FROM markets WHERE market_id = $marketId";
    $marketDetails = $conn->query($marketDetailsQuery)->fetch_assoc();
    $marketProductsQuery = "SELECT p.*, c.category_name FROM products p JOIN categories c ON p.category_id = c.category_id WHERE p.market_id = $marketId ORDER BY p.name";
    $marketProducts = $conn->query($marketProductsQuery);
    $marketOrdersQuery = "SELECT o.*, u.name as user_name FROM orders o JOIN users u ON o.user_id = u.user_id JOIN order_items oi ON o.order_id = oi.order_id JOIN products p ON oi.product_id = p.product_id WHERE p.market_id = $marketId GROUP BY o.order_id ORDER BY o.order_date DESC";
    $marketOrders = $conn->query($marketOrdersQuery);
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم أرزاق - الأسواق</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../ui/logo.png">
    <link rel="stylesheet" href="admin.css">
    <style>
.market-details{display:none;margin-top:20px;}
.market-details.active{display:block;}
.market-tabs{display:flex;margin-bottom:15px;border-bottom:1px solid var(--border-color);}
.market-tab{padding:10px 20px;cursor:pointer;border-bottom:3px solid transparent;margin-left:10px;}
.market-tab.active{border-bottom-color:var(--primary-color);color:var(--primary-color);font-weight:bold;}
.tab-content{display:none;}
.tab-content.active{display:block;}
.status-badge{padding:4px 10px;border-radius:20px;font-size:0.8rem;font-weight:600;}
.status-active{background-color:rgba(46,204,113,0.1);color:var(--success-color);}
.status-inactive{background-color:rgba(231,76,60,0.1);color:var(--danger-color);}
.market-info-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:15px;margin-bottom:20px;}
.market-info-item{background:var(--light-gray);padding:15px;border-radius:8px;}
.market-info-item strong{display:block;margin-bottom:5px;color:#666;}
.document-preview{max-width:100px;max-height:100px;border-radius:5px;border:1px solid var(--border-color);}
.search-add-container{display:flex;justify-content:space-between;margin-bottom:20px;}
.search-container{position:relative;width:300px;}
.search-container input{width:100%;padding:10px 35px 10px 15px;border-radius:5px;border:1px solid var(--border-color);}
.search-container i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#999;}
.add-market-btn{background:var(--primary-color);color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;display:flex;align-items:center;}
.add-market-btn i{margin-left:8px;}
.action-buttons{display:flex;gap:5px;justify-content:center;}
.action-btn{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;text-decoration:none;transition:all 0.3s;}
.view-btn{background-color:var(--info-color);}
.edit-btn{background-color:var(--warning-color);}
.delete-btn{background-color:var(--danger-color);}
.action-btn:hover{opacity:0.8;transform:scale(1.1);}
.market-row{cursor:pointer;transition:background-color 0.3s;}
.market-row:hover{background-color:var(--light-gray);}
.market-row.active{background-color:var(--primary-light);}
.table-responsive{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
th,td{padding:12px 15px;text-align:right;border-bottom:1px solid var(--border-color);}
th{background-color:var(--light-gray);font-weight:600;color:var(--text-color);}
tbody tr:last-child td{border-bottom:none;}
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
                <a href="markets.php" class="menu-item active">
                    <i class="fas fa-store"></i>
                    <span>الأسواق</span>
                </a>
                <a href="categories.php" class="menu-item">
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
                <h1>إدارة الأسواق</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="dashboard.php">الرئيسية</a>
                    </div>
                    <div class="breadcrumb-item">الأسواق</div>
                </div>
            </div>
            <div class="search-add-container">
                <div class="search-container">
                    <input type="text" id="marketSearch" placeholder="بحث عن سوق...">
                    <i class="fas fa-search"></i>
                </div>
                <a href="../market/market.php" class="add-market-btn">
                    <i class="fas fa-plus"></i>
                    إضافة سوق جديد
                </a>
            </div>
            <div class="dashboard-panel">
                <div class="panel-header">
                    <h3 class="panel-title">قائمة الأسواق</h3>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table id="marketsTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>اسم السوق</th>
                                    <th>البريد الإلكتروني</th>
                                    <th>رقم الهاتف</th>
                                    <th>الحالة</th>
                                    <th>عدد المنتجات</th>
                                    <th>عدد الطلبات</th>
                                    <th>النقاط</th>
                                    <th>وثائق</th>
                                    <th>إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($markets->num_rows > 0): ?>
                                <?php while($market = $markets->fetch_assoc()): ?>
                                <tr data-market-id="<?php echo $market['market_id']; ?>" class="market-row <?php echo isset($_GET['market_id']) && $_GET['market_id'] == $market['market_id'] ? 'active' : ''; ?>">
                                    <td><?php echo $market['market_id']; ?></td>
                                    <td><?php echo $market['name']; ?></td>
                                    <td><?php echo $market['email']; ?></td>
                                    <td><?php echo $market['phone']; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $market['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $market['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $market['product_count']; ?></td>
                                    <td><?php echo $market['order_count']; ?></td>
                                    <td><?php echo $market['points']; ?></td>
                                    <td>
                                        <?php if($market['document_path']): ?>
                                            <a href="../<?php echo $market['document_path']; ?>" target="_blank">
                                                <img src="../<?php echo $market['document_path']; ?>" class="document-preview" alt="وثيقة">
                                            </a>
                                        <?php else: ?>
                                            <span>-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="markets.php?market_id=<?php echo $market['market_id']; ?>" class="action-btn view-btn" title="عرض التفاصيل">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_market.php?id=<?php echo $market['market_id']; ?>" class="action-btn edit-btn" title="تعديل">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="#" class="action-btn delete-btn" data-id="<?php echo $market['market_id']; ?>" title="حذف">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="10" style="text-align: center;">لا توجد أسواق لعرضها</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php if($marketDetails): ?>
            <div class="market-details active" id="marketDetails-<?php echo $marketDetails['market_id']; ?>">
                <div class="dashboard-panel">
                    <div class="panel-header">
                        <h3 class="panel-title">تفاصيل السوق: <?php echo $marketDetails['name']; ?></h3>
                    </div>
                    <div class="panel-body">
                        <div class="market-info-grid">
                            <div class="market-info-item">
                                <strong>اسم السوق</strong>
                                <span><?php echo $marketDetails['name']; ?></span>
                            </div>
                            <div class="market-info-item">
                                <strong>البريد الإلكتروني</strong>
                                <span><?php echo $marketDetails['email']; ?></span>
                            </div>
                            <div class="market-info-item">
                                <strong>رقم الهاتف</strong>
                                <span><?php echo $marketDetails['phone']; ?></span>
                            </div>
                            <div class="market-info-item">
                                <strong>العنوان</strong>
                                <span><?php echo $marketDetails['address']; ?></span>
                            </div>
                            <div class="market-info-item">
                                <strong>الحالة</strong>
                                <span class="status-badge <?php echo $marketDetails['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $marketDetails['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                </span>
                            </div>
                            <div class="market-info-item">
                                <strong>النقاط</strong>
                                <span><?php echo $marketDetails['points']; ?></span>
                            </div>
                        </div>
                        <div class="market-tabs">
                            <div class="market-tab active" data-tab="products">المنتجات</div>
                            <div class="market-tab" data-tab="orders">الطلبات</div>
                            <div class="market-tab" data-tab="documents">الوثائق</div>
                        </div>
                        <div class="tab-content active" id="products-tab">
                            <table>
                                <thead>
                                <tr>
                                        <th>#</th>
                                        <th>اسم المنتج</th>
                                        <th>الفئة</th>
                                        <th>السعر</th>
                                        <th>الصورة</th>
                                        <th>إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($marketProducts && $marketProducts->num_rows > 0): ?>
                                    <?php while($product = $marketProducts->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $product['product_id']; ?></td>
                                        <td><?php echo $product['name']; ?></td>
                                        <td><?php echo $product['category_name']; ?></td>
                                        <td><?php echo number_format($product['price'], 2) . ' ريال'; ?></td>
                                        <td>
                                            <?php if($product['image_path']): ?>
                                                <img src="../<?php echo $product['image_path']; ?>" class="document-preview" alt="صورة المنتج">
                                            <?php else: ?>
                                                <span>-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="edit_product.php?id=<?php echo $product['product_id']; ?>" class="action-btn edit-btn" title="تعديل">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="#" class="action-btn delete-btn" data-id="<?php echo $product['product_id']; ?>" title="حذف">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center;">لا توجد منتجات لهذا السوق</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="tab-content" id="orders-tab">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>العميل</th>
                                        <th>المبلغ</th>
                                        <th>التاريخ</th>
                                        <th>الحالة</th>
                                        <th>العنوان</th>
                                        <th>إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($marketOrders && $marketOrders->num_rows > 0): ?>
                                    <?php while($order = $marketOrders->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $order['order_id']; ?></td>
                                        <td><?php echo $order['user_name']; ?></td>
                                        <td><?php echo number_format($order['total_amount'], 2) . ' ريال'; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($order['order_date'])); ?></td>
                                        <td>
                                            <?php 
                                            $statusClass = '';
                                            switch($order['status']) {
                                                case 'pending':
                                                    $statusClass = 'pending';
                                                    $statusText = 'قيد الانتظار';
                                                    break;
                                                case 'processing':
                                                    $statusClass = 'processing';
                                                    $statusText = 'قيد المعالجة';
                                                    break;
                                                case 'completed':
                                                    $statusClass = 'completed';
                                                    $statusText = 'مكتمل';
                                                    break;
                                                case 'cancelled':
                                                    $statusClass = 'cancelled';
                                                    $statusText = 'ملغي';
                                                    break;
                                                default:
                                                    $statusClass = 'pending';
                                                    $statusText = $order['status'];
                                            }
                                            ?>
                                            <span class="status <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                        </td>
                                        <td><?php echo mb_substr($order['shipping_address'], 0, 30) . (mb_strlen($order['shipping_address']) > 30 ? '...' : ''); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="view_order.php?id=<?php echo $order['order_id']; ?>" class="action-btn view-btn" title="عرض التفاصيل">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_order.php?id=<?php echo $order['order_id']; ?>" class="action-btn edit-btn" title="تعديل">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center;">لا توجد طلبات لهذا السوق</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="tab-content" id="documents-tab">
                            <div class="market-info-grid">
                                <div class="market-info-item">
                                    <strong>وثيقة السوق</strong>
                                    <?php if($marketDetails['document_path']): ?>
                                        <div>
                                            <a href="../<?php echo $marketDetails['document_path']; ?>" target="_blank">
                                                <img src="../<?php echo $marketDetails['document_path']; ?>" style="max-width: 200px; max-height: 200px;" alt="وثيقة السوق">
                                            </a>
                                        </div>
                                        <div style="margin-top: 10px;">
                                            <a href="../<?php echo $marketDetails['document_path']; ?>" download class="view-all">
                                                <i class="fas fa-download"></i> تحميل الوثيقة
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <span>لا توجد وثائق مرفقة</span>
                                    <?php endif; ?>
                                </div>
                                <div class="market-info-item">
                                    <strong>إرفاق وثيقة جديدة</strong>
                                    <form action="upload_document.php" method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="market_id" value="<?php echo $marketDetails['market_id']; ?>">
                                        <input type="file" name="document" required style="margin-bottom: 10px;">
                                        <button type="submit" class="add-market-btn" style="padding: 8px 15px; font-size: 0.9rem;">
                                            <i class="fas fa-upload"></i> رفع الوثيقة
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
    <div class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </div>
    <script>document.getElementById('menuToggle').addEventListener('click',function(){document.querySelector('.sidebar').classList.toggle('active');});document.addEventListener('click',function(event){const sidebar=document.querySelector('.sidebar');const menuToggle=document.getElementById('menuToggle');if(window.innerWidth<=768&&!sidebar.contains(event.target)&&!menuToggle.contains(event.target)&&sidebar.classList.contains('active')){sidebar.classList.remove('active');}});document.querySelectorAll('.market-tab').forEach(tab=>{tab.addEventListener('click',function(){const tabName=this.getAttribute('data-tab');document.querySelectorAll('.market-tab').forEach(t=>t.classList.remove('active'));document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));this.classList.add('active');document.getElementById(tabName+'-tab').classList.add('active');});});document.getElementById('marketSearch').addEventListener('keyup',function(){const searchValue=this.value.toLowerCase();const rows=document.querySelectorAll('#marketsTable tbody tr');rows.forEach(row=>{const marketName=row.cells[1].textContent.toLowerCase();const marketEmail=row.cells[2].textContent.toLowerCase();const marketPhone=row.cells[3].textContent.toLowerCase();if(marketName.includes(searchValue)||marketEmail.includes(searchValue)||marketPhone.includes(searchValue)){row.style.display='';}else{row.style.display='none';}});});document.querySelectorAll('.delete-btn').forEach(btn=>{btn.addEventListener('click',function(e){e.preventDefault();if(confirm('هل أنت متأكد من حذف هذا العنصر؟')){const id=this.getAttribute('data-id');window.location.href=`delete_market.php?id=${id}`;}});});</script>
</body>
</html>