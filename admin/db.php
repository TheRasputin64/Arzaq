<?php
$server = "localhost";
$username = "u268954309_arzaqplus";
$password = "ArzaqPlus2025@";
$database = "u268954309_arzaq";

$conn = new mysqli($server, $username, $password, $database);

if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

function sanitize($conn, $input) {
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = sanitize($conn, $value);
        }
        return $input;
    }
    return $conn->real_escape_string(trim($input));
}

function formatPrice($price) {
    return number_format($price, 2) . ' ر.س';
}

function getStatusClass($status) {
    switch($status) {
        case 'pending': return 'status-pending';
        case 'processing': return 'status-processing';
        case 'shipped': return 'status-shipped';
        case 'delivered': return 'status-delivered';
        case 'cancelled': return 'status-cancelled';
        default: return '';
    }
}

function getStatusText($status) {
    switch($status) {
        case 'pending': return 'قيد الانتظار';
        case 'processing': return 'قيد التجهيز';
        case 'shipped': return 'تم الشحن';
        case 'delivered': return 'تم التوصيل';
        case 'cancelled': return 'ملغي';
        default: return $status;
    }
}