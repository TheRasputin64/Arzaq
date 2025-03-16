<?php
session_start();
include 'db.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$adminInfo = $conn->query("SELECT username FROM admin WHERE admin_id = " . $_SESSION['admin_id'])->fetch_assoc();

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $_GET['search'] : '';
$searchCondition = '';
if (!empty($search)) {
    $searchCondition = " WHERE name LIKE '%$search%' OR phone LIKE '%$search%' OR email LIKE '%$search%'";
}

$countQuery = "SELECT COUNT(*) as total FROM users" . $searchCondition;
$totalUsers = $conn->query($countQuery)->fetch_assoc()['total'];
$totalPages = ceil($totalUsers / $limit);

$usersQuery = "SELECT * FROM users" . $searchCondition . " ORDER BY user_id DESC LIMIT $offset, $limit";
$usersResult = $conn->query($usersQuery);
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم أرزاق - المستخدمين</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../ui/logo.png">
    <link rel="stylesheet" href="admin.css">
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
                <a href="products.php" class="menu-item">
                    <i class="fas fa-box"></i>
                    <span>المنتجات</span>
                </a>
                <a href="orders.php" class="menu-item">
                    <i class="fas fa-shopping-cart"></i>
                    <span>الطلبات</span>
                </a>
                <a href="users.php" class="menu-item active">
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
                <h1>إدارة المستخدمين</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="dashboard.php">الرئيسية</a>
                    </div>
                    <div class="breadcrumb-item">المستخدمين</div>
                </div>
            </div>

            <div class="page-actions">
                <div class="search-box">
                    <form action="" method="GET">
                        <div class="search-input">
                            <input type="text" name="search" placeholder="بحث عن مستخدم..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <button class="add-new-btn" onclick="location.href='user-form.php'">
                    <i class="fas fa-plus"></i>
                    إضافة مستخدم جديد
                </button>
            </div>

            <div class="data-container">
                <div class="dashboard-panel">
                    <div class="panel-header">
                        <h3 class="panel-title">قائمة المستخدمين</h3>
                        <div class="panel-actions">
                            <span class="record-count"><?php echo $totalUsers; ?> مستخدم</span>
                        </div>
                    </div>
                    <div class="panel-body">
                        <?php if ($usersResult->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>اسم المستخدم</th>
                                        <th>رقم الهاتف</th>
                                        <th>البريد الإلكتروني</th>
                                        <th>العنوان</th>
                                        <th>الحالة</th>
                                        <th>العمليات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($user = $usersResult->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $user['user_id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td>
                                            <a href="tel:<?php echo $user['phone']; ?>" class="phone-link"><?php echo htmlspecialchars($user['phone']); ?></a>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($user['address'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($user['state'] ?? '-'); ?></td>
                                        <td class="actions">
                                            <a href="javascript:void(0)" onclick="viewUserDetails(<?php echo $user['user_id']; ?>)" class="action-btn view">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="user-form.php?id=<?php echo $user['user_id']; ?>" class="action-btn edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $user['user_id']; ?>)" class="action-btn delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="page-link">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php 
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $startPage + 4);
                            if ($endPage - $startPage < 4) {
                                $startPage = max(1, $endPage - 4);
                            }
                            for ($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo ($page + 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="page-link">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php else: ?>
                        <div class="empty-data">
                            <i class="fas fa-users"></i>
                            <p>لا يوجد مستخدمين<?php echo !empty($search) ? ' مطابقين لبحثك' : ''; ?></p>
                            <?php if (!empty($search)): ?>
                            <a href="users.php" class="reset-search">إعادة ضبط البحث</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <div class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </div>
    
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>تأكيد الحذف</h3>
                <span class="close" onclick="closeModal('deleteModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p>هل أنت متأكد من حذف هذا المستخدم؟</p>
                <p class="warning">لا يمكن التراجع عن هذه العملية!</p>
            </div>
            <div class="modal-footer">
                <form id="deleteForm" action="delete-user.php" method="POST">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <button type="button" class="btn cancel" onclick="closeModal('deleteModal')">إلغاء</button>
                    <button type="submit" class="btn delete">حذف</button>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="userDetailsModal">
        <div class="modal-content user-details-modal">
            <div class="modal-header">
                <h3>تفاصيل المستخدم</h3>
                <span class="close" onclick="closeModal('userDetailsModal')">&times;</span>
            </div>
            <div class="modal-body" id="userDetailsContent">
                <div class="loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    جاري التحميل...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn cancel" onclick="closeModal('userDetailsModal')">إغلاق</button>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('menuToggle').addEventListener('click', function() { document.querySelector('.sidebar').classList.toggle('active'); });
        
        document.addEventListener('click', function(event) { const sidebar = document.querySelector('.sidebar'); const menuToggle = document.getElementById('menuToggle'); if (window.innerWidth <= 768 && !sidebar.contains(event.target) && !menuToggle.contains(event.target) && sidebar.classList.contains('active')) { sidebar.classList.remove('active'); } });
        
        function confirmDelete(userId) { document.getElementById('deleteUserId').value = userId; document.getElementById('deleteModal').style.display = 'flex'; }
        
        function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }
        
        window.onclick = function(event) { if (event.target.classList.contains('modal')) { event.target.style.display = 'none'; } }
        
        function viewUserDetails(userId) {
            const modal = document.getElementById('userDetailsModal');
            const content = document.getElementById('userDetailsContent');
            modal.style.display = 'flex';
            content.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> جاري التحميل...</div>';
            
            fetch('get-user-details.php?id=' + userId)
                .then(response => response.json())
                .then(data => {
                    let ordersHtml = '';
                    if (data.orders && data.orders.length > 0) {
                        ordersHtml = `
                            <div class="detail-section">
                                <h4>الطلبات <span class="count-badge">${data.orders.length}</span></h4>
                                <div class="table-responsive">
                                    <table class="details-table">
                                        <thead>
                                            <tr>
                                                <th>رقم الطلب</th>
                                                <th>التاريخ</th>
                                                <th>المبلغ</th>
                                                <th>الحالة</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${data.orders.map(order => `
                                                <tr>
                                                    <td>#${order.order_id}</td>
                                                    <td>${order.order_date}</td>
                                                    <td>${order.total_amount} ريال</td>
                                                    <td><span class="status-badge ${order.status}">${order.status}</span></td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        `;
                    } else {
                        ordersHtml = `
                            <div class="detail-section">
                                <h4>الطلبات</h4>
                                <div class="empty-detail">
                                    <i class="fas fa-shopping-cart"></i>
                                    <p>لا توجد طلبات لهذا المستخدم</p>
                                </div>
                            </div>
                        `;
                    }

                    content.innerHTML = `
                        <div class="user-profile">
                            <div class="user-avatar-large">${data.user.name ? data.user.name.charAt(0).toUpperCase() : 'U'}</div>
                            <div class="user-info">
                                <h3>${data.user.name}</h3>
                                <p><i class="fas fa-phone"></i> ${data.user.phone}</p>
                                <p><i class="fas fa-envelope"></i> ${data.user.email || 'لا يوجد بريد إلكتروني'}</p>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4>معلومات الاتصال</h4>
                            <div class="contact-details">
                                <div class="contact-item">
                                    <strong>المنطقة:</strong>
                                    <span>${data.user.state || 'غير محدد'}</span>
                                </div>
                                <div class="contact-item">
                                    <strong>العنوان:</strong>
                                    <span>${data.user.address || 'غير محدد'}</span>
                                </div>
                            </div>
                        </div>
                        
                        ${ordersHtml}
                    `;
                })
                .catch(error => {
                    content.innerHTML = `
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>حدث خطأ أثناء تحميل البيانات</p>
                        </div>
                    `;
                    console.error('Error fetching user details:', error);
                });
        }
    </script>

    <style>
.page-actions{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
.search-box{flex:1;max-width:400px;}
.search-input{position:relative;}
.search-input input{width:100%;padding:10px 15px;border:1px solid var(--border-color);border-radius:5px;font-family:'Cairo',sans-serif;}
.search-input button{position:absolute;left:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#666;cursor:pointer;}
.add-new-btn{background-color:var(--primary-color);color:white;border:none;padding:8px 15px;border-radius:5px;display:flex;align-items:center;cursor:pointer;font-family:'Cairo',sans-serif;font-weight:600;}
.add-new-btn i{margin-left:5px;}
.add-new-btn:hover{background-color:#1d7d4a;}
.table-responsive{overflow-x:auto;}
.data-table{width:100%;border-collapse:collapse;}
.data-table th{text-align:right;padding:12px 15px;background-color:var(--light-gray);font-weight:600;color:var(--text-color);border-bottom:1px solid var(--border-color);}
.data-table td{padding:12px 15px;border-bottom:1px solid var(--border-color);vertical-align:middle;}
.data-table tr:hover{background-color:rgba(37,153,92,0.03);}
.phone-link{color:var(--info-color);text-decoration:none;}
.phone-link:hover{text-decoration:underline;}
.actions{display:flex;gap:5px;}
.action-btn{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;text-decoration:none;font-size:0.8rem;}
.action-btn.view{background-color:var(--info-color);}
.action-btn.edit{background-color:var(--warning-color);}
.action-btn.delete{background-color:var(--danger-color);}
.panel-actions{display:flex;align-items:center;}
.record-count{font-size:0.9rem;color:#666;background-color:var(--light-gray);padding:3px 10px;border-radius:20px;}
.pagination{display:flex;justify-content:center;align-items:center;margin-top:20px;gap:5px;}
.page-link{display:flex;align-items:center;justify-content:center;width:35px;height:35px;border-radius:5px;background-color:white;border:1px solid var(--border-color);color:var(--text-color);text-decoration:none;font-weight:600;}
.page-link.active{background-color:var(--primary-color);color:white;border-color:var(--primary-color);}
.page-link:hover:not(.active){background-color:var(--light-gray);}
.empty-data{text-align:center;padding:30px;color:#666;}
.empty-data i{font-size:3rem;color:#ddd;margin-bottom:15px;}
.reset-search{display:inline-block;margin-top:10px;color:var(--primary-color);text-decoration:none;}
.modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background-color:rgba(0,0,0,0.5);z-index:1000;justify-content:center;align-items:center;}
.modal-content{background-color:white;border-radius:10px;width:400px;max-width:90%;box-shadow:0 4px 15px rgba(0,0,0,0.1);}
.user-details-modal{width:600px;max-width:95%;}
.modal-header{display:flex;justify-content:space-between;align-items:center;padding:15px 20px;border-bottom:1px solid var(--border-color);}
.modal-header h3{margin:0;font-size:1.2rem;}
.close{font-size:1.5rem;cursor:pointer;}
.modal-body{padding:20px;}
.warning{color:var(--danger-color);font-weight:600;margin-top:10px;}
.modal-footer{padding:15px 20px;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;}
.btn{padding:8px 15px;border-radius:5px;border:none;cursor:pointer;font-family:'Cairo',sans-serif;font-weight:600;margin-right:10px;}
.btn.cancel{background-color:#f1f1f1;color:#333;}
.btn.delete{background-color:var(--danger-color);color:white;}
.loading{text-align:center;padding:20px;color:#666;}
.loading i{margin-left:10px;color:var(--primary-color);}
.user-profile{display:flex;align-items:center;margin-bottom:20px;}
.user-avatar-large{width:60px;height:60px;background-color:var(--primary-color);color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:bold;margin-left:15px;}
.user-info{flex:1;}
.user-info h3{margin:0 0 10px 0;color:var(--text-color);font-size:1.4rem;}
.user-info p{margin:5px 0;color:#666;display:flex;align-items:center;}
.user-info p i{margin-left:8px;color:var(--primary-color);width:16px;}
.detail-section{margin-bottom:25px;border-radius:8px;border:1px solid var(--border-color);overflow:hidden;}
.detail-section h4{margin:0;padding:12px 15px;background-color:var(--light-gray);font-size:1.1rem;display:flex;align-items:center;justify-content:space-between;}
.count-badge{font-size:0.8rem;background-color:var(--primary-color);color:white;padding:2px 8px;border-radius:20px;}
.contact-details{padding:15px;}
.contact-item{display:flex;margin-bottom:10px;}
.contact-item strong{min-width:100px;color:#666;}
.details-table{width:100%;border-collapse:collapse;}
.details-table th{text-align:right;padding:10px;background-color:#f9f9f9;font-weight:600;color:var(--text-color);border-bottom:1px solid var(--border-color);font-size:0.9rem;}
.details-table td{padding:10px;border-bottom:1px solid var(--border-color);font-size:0.9rem;}
.status-badge{padding:4px 8px;border-radius:4px;font-size:0.8rem;font-weight:600;}
.status-badge.pending{background-color:#f8d7da;color:#721c24;}
.status-badge.processing{background-color:#fff3cd;color:#856404;}
.status-badge.shipped{background-color:#d4edda;color:#155724;}
.status-badge.delivered{background-color:#cce5ff;color:#004085;}
.status-badge.completed{background-color:#d1e7dd;color:#0f5132;}
.status-badge.cancelled{background-color:#e2e3e5;color:#383d41;}
.empty-detail{text-align:center;padding:20px;color:#666;}
.empty-detail i{font-size:2rem;color:#ddd;margin-bottom:10px;}
.error-message{text-align:center;padding:20px;color:var(--danger-color);}
.error-message i{font-size:2rem;margin-bottom:10px;}
@media (max-width:768px){.page-actions{flex-direction:column;align-items:stretch;}.search-box{max-width:100%;margin-bottom:15px;}.add-new-btn{width:100%;justify-content:center;}.user-details-modal{width:95%;}.user-profile{flex-direction:column;text-align:center;}.user-avatar-large{margin:0 auto 15px;}.contact-item{flex-direction:column;}.contact-item strong{min-width:auto;margin-bottom:5px;}}
    </style>
</body>
</html>