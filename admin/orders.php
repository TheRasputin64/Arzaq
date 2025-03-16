<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$adminInfo = $conn->query("SELECT username FROM admin WHERE admin_id = " . $_SESSION['admin_id'])->fetch_assoc();

// Process status update if submitted
if (isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = $conn->real_escape_string($_POST['status']);
    
    $update_query = "UPDATE orders SET status = '$new_status' WHERE order_id = $order_id";
    $conn->query($update_query);
    
    // Redirect to prevent form resubmission
    header('Location: orders.php');
    exit();
}

// Handle order deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $order_id = (int)$_GET['delete'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Delete order items first
        $conn->query("DELETE FROM order_items WHERE order_id = $order_id");
        
        // Then delete the order
        $conn->query("DELETE FROM orders WHERE order_id = $order_id");
        
        // Commit transaction
        $conn->commit();
        
        // Redirect to prevent resubmission
        header('Location: orders.php');
        exit();
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
    }
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Search functionality
$search = '';
$searchCondition = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $searchCondition = " WHERE o.order_id LIKE '%$search%' OR u.name LIKE '%$search%' OR u.phone LIKE '%$search%' OR o.status LIKE '%$search%'";
}

// Filtering by status
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status = $conn->real_escape_string($_GET['status']);
    if ($searchCondition) {
        $searchCondition .= " AND o.status = '$status'";
    } else {
        $searchCondition = " WHERE o.status = '$status'";
    }
}

// Count total records for pagination
$countQuery = "SELECT COUNT(*) as total FROM orders o JOIN users u ON o.user_id = u.user_id" . $searchCondition;
$totalRecords = $conn->query($countQuery)->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $records_per_page);

// Get orders with pagination
$ordersQuery = "SELECT o.order_id, u.name as user_name, u.phone, o.order_date, o.total_amount, o.status, o.shipping_address 
                FROM orders o 
                JOIN users u ON o.user_id = u.user_id" 
                . $searchCondition . 
                " ORDER BY o.order_date DESC LIMIT $offset, $records_per_page";

$orders = $conn->query($ordersQuery);
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم أرزاق - الطلبات</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../ui/logo.png">
    <link rel="stylesheet" href="admin.css">
    <style>
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            align-items: center;
        }
        .filter-group label {
            margin-left: 8px;
            font-weight: 600;
        }
        .search-box {
            display: flex;
            margin-bottom: 20px;
        }
        .search-box input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px 0 0 4px;
            font-family: 'Cairo', sans-serif;
        }
        .search-box button {
            padding: 8px 15px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
        }
        .status-filter {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-family: 'Cairo', sans-serif;
        }
        .order-item {
            background-color: var(--light-gray);
            padding: 10px;
            margin-top: 10px;
            border-radius: 4px;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        .close {
            font-size: 1.5rem;
            cursor: pointer;
        }
        .order-details-container {
            max-height: 400px;
            overflow-y: auto;
        }
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 5px 10px;
            margin: 0 3px;
            border: 1px solid var(--border-color);
            border-radius: 3px;
            text-decoration: none;
            color: var(--text-color);
        }
        .pagination a:hover {
            background-color: var(--light-gray);
        }
        .pagination .current {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        .view-btn, .edit-btn, .delete-btn {
            padding: 5px 10px;
            border-radius: 4px;
            color: white;
            text-decoration: none;
            font-size: 0.8rem;
            cursor: pointer;
        }
        .view-btn {
            background-color: var(--info-color);
        }
        .edit-btn {
            background-color: var(--warning-color);
        }
        .delete-btn {
            background-color: var(--danger-color);
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
                <a href="categories.php" class="menu-item">
                    <i class="fas fa-th-large"></i>
                    <span>الفئات</span>
                </a>
                <a href="products.php" class="menu-item">
                    <i class="fas fa-box"></i>
                    <span>المنتجات</span>
                </a>
                <a href="orders.php" class="menu-item active">
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
                <h1>إدارة الطلبات</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="dashboard.php">الرئيسية</a>
                    </div>
                    <div class="breadcrumb-item">الطلبات</div>
                </div>
            </div>
            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">قائمة الطلبات</h3>
                </div>
                <div class="panel-body">
                    <form action="orders.php" method="GET" class="search-box">
                        <input type="text" name="search" placeholder="البحث عن طلب..." value="<?php echo $search; ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                    <div class="filters">
                        <div class="filter-group">
                            <label for="status-filter">تصفية حسب الحالة:</label>
                            <select id="status-filter" class="status-filter" onchange="window.location.href=this.value">
                                <option value="orders.php">جميع الحالات</option>
                                <option value="orders.php?status=pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>قيد الانتظار</option>
                                <option value="orders.php?status=processing" <?php echo (isset($_GET['status']) && $_GET['status'] == 'processing') ? 'selected' : ''; ?>>قيد المعالجة</option>
                                <option value="orders.php?status=completed" <?php echo (isset($_GET['status']) && $_GET['status'] == 'completed') ? 'selected' : ''; ?>>مكتمل</option>
                                <option value="orders.php?status=cancelled" <?php echo (isset($_GET['status']) && $_GET['status'] == 'cancelled') ? 'selected' : ''; ?>>ملغي</option>
                            </select>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>رقم الطلب</th>
                                <th>العميل</th>
                                <th>رقم الهاتف</th>
                                <th>التاريخ</th>
                                <th>المبلغ</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($orders->num_rows > 0): while($order = $orders->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $order['order_id']; ?></td>
                                <td><?php echo $order['user_name']; ?></td>
                                <td><?php echo $order['phone']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?></td>
                                <td><?php echo number_format($order['total_amount'], 2) . ' ريال'; ?></td>
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
                                <td>
                                    <div class="action-buttons">
                                    <span class="view-btn" onclick="viewOrderDetails(<?php echo $order['order_id']; ?>)">
    <i class="fas fa-eye"></i>
    عرض
</span>
<span class="edit-btn" onclick="editOrderStatus(<?php echo $order['order_id']; ?>, '<?php echo $order['status']; ?>')">
    <i class="fas fa-pencil-alt"></i>
    تعديل
</span>
<a href="orders.php?delete=<?php echo $order['order_id']; ?>" class="delete-btn" onclick="return confirm('هل أنت متأكد من حذف هذا الطلب؟')">
    <i class="fas fa-trash"></i>
    حذف
</a>
</div>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="7" class="text-center">لا توجد طلبات للعرض</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <!-- Pagination -->
                    <div class="pagination">
                        <?php if($totalPages > 1): ?>
                            <?php if($page > 1): ?>
                                <a href="orders.php?page=<?php echo ($page - 1); echo !empty($search) ? '&search='.$search : ''; echo isset($_GET['status']) ? '&status='.$_GET['status'] : ''; ?>">&laquo;</a>
                            <?php endif; ?>
                            
                            <?php for($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if($i == $page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="orders.php?page=<?php echo $i; echo !empty($search) ? '&search='.$search : ''; echo isset($_GET['status']) ? '&status='.$_GET['status'] : ''; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if($page < $totalPages): ?>
                                <a href="orders.php?page=<?php echo ($page + 1); echo !empty($search) ? '&search='.$search : ''; echo isset($_GET['status']) ? '&status='.$_GET['status'] : ''; ?>">&raquo;</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <!-- Order Details Modal -->
    <div id="orderDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>تفاصيل الطلب <span id="orderIdDisplay"></span></h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="order-details-container" id="orderDetailsContent">
                <!-- Order details will be loaded here via AJAX -->
            </div>
        </div>
    </div>
    
    <!-- Order Status Edit Modal -->
    <div id="editStatusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>تحديث حالة الطلب <span id="editOrderIdDisplay"></span></h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" action="orders.php">
                    <input type="hidden" id="edit_order_id" name="order_id" value="">
                    <div style="margin-bottom: 15px;">
                        <label for="status" style="display: block; margin-bottom: 5px; font-weight: 600;">الحالة:</label>
                        <select id="status" name="status" style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; font-family: 'Cairo', sans-serif;">
                            <option value="pending">قيد الانتظار</option>
                            <option value="processing">قيد المعالجة</option>
                            <option value="completed">مكتمل</option>
                            <option value="cancelled">ملغي</option>
                        </select>
                    </div>
                    <div style="text-align: left;">
                        <button type="submit" name="update_status" style="background-color: var(--primary-color); color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">تحديث الحالة</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </div>
    <script>
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        
        // Close menu when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const menuToggle = document.getElementById('menuToggle');
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !menuToggle.contains(event.target) &&
                sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        });
        
        // Modal control functions
        function viewOrderDetails(orderId) {
            document.getElementById('orderIdDisplay').textContent = '#' + orderId;
            document.getElementById('orderDetailsContent').innerHTML = '<div style="text-align: center; padding: 20px;">جاري تحميل البيانات...</div>';
            document.getElementById('orderDetailsModal').style.display = 'block';
            
            // AJAX call to fetch order details
            fetch('get_order_details.php?order_id=' + orderId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('orderDetailsContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('orderDetailsContent').innerHTML = '<div style="text-align: center; padding: 20px; color: red;">حدث خطأ أثناء تحميل البيانات</div>';
                });
        }
        
        function closeModal() {
            document.getElementById('orderDetailsModal').style.display = 'none';
        }
        
        function editOrderStatus(orderId, currentStatus) {
            document.getElementById('editOrderIdDisplay').textContent = '#' + orderId;
            document.getElementById('edit_order_id').value = orderId;
            document.getElementById('status').value = currentStatus;
            document.getElementById('editStatusModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editStatusModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('orderDetailsModal')) {
                closeModal();
            }
            if (event.target == document.getElementById('editStatusModal')) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>