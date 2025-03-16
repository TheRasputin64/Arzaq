<?php
session_start();
include 'db.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = isset($_POST['productId']) ? intval($_POST['productId']) : 0;
    $name = $conn->real_escape_string($_POST['productName']);
    $price = floatval($_POST['productPrice']);
    $categoryId = intval($_POST['productCategory']);
    $marketId = intval($_POST['productMarket']);
    $description = $conn->real_escape_string($_POST['productDescription'] ?? '');
    $oldImagePath = isset($_POST['imagePath']) ? $_POST['imagePath'] : '';
    $isLocal = isset($_POST['isLocal']) ? 1 : 0;
    $isNew = isset($_POST['isNew']) ? 1 : 0;
    $points = isset($_POST['productPoints']) ? intval($_POST['productPoints']) : 0;
    $hasCashback = isset($_POST['hasCashback']) ? 1 : 0;
    $cashbackPercentage = $hasCashback ? floatval($_POST['cashbackPercentage']) : null;
    $isUpdate = $productId > 0;
    
    if (isset($_FILES['productImage']) && $_FILES['productImage']['size'] > 0) {
        $targetDir = "../pro/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $fileExtension = pathinfo($_FILES['productImage']['name'], PATHINFO_EXTENSION);
        $newFileName = uniqid() . '.' . $fileExtension;
        $targetFile = $targetDir . $newFileName;
        if (move_uploaded_file($_FILES['productImage']['tmp_name'], $targetFile)) {
            $imagePath = "pro/" . $newFileName;
        }
    } else {
        $imagePath = $oldImagePath;
    }
    
    if ($isUpdate) {
        $query = "UPDATE products SET
                  name = '$name',
                  price = $price,
                  category_id = $categoryId,
                  market_id = $marketId,
                  description = '$description',
                  is_local = $isLocal,
                  is_new = $isNew,
                  points = $points,
                  has_cashback = $hasCashback";
        
        if ($hasCashback && $cashbackPercentage !== null) {
            $query .= ", cashback_percentage = $cashbackPercentage";
        } else {
            $query .= ", cashback_percentage = NULL";
        }
        
        if ($imagePath) {
            $query .= ", image_path = '$imagePath'";
        }
        
        $query .= " WHERE product_id = $productId";
        
        if ($conn->query($query)) {
            header('Location: products.php?updated=true');
        } else {
            header('Location: products.php?error=update');
        }
    } else {
        $query = "INSERT INTO products (name, price, category_id, market_id, description, image_path, is_local, is_new, points, has_cashback, cashback_percentage)
                  VALUES ('$name', $price, $categoryId, $marketId, '$description', " .
                  ($imagePath ? "'$imagePath'" : "NULL") . ", $isLocal, $isNew, $points, $hasCashback, " .
                  ($hasCashback && $cashbackPercentage !== null ? "$cashbackPercentage" : "NULL") . ")";
        
        if ($conn->query($query)) {
            header('Location: products.php?added=true');
        } else {
            header('Location: products.php?error=add');
        }
    }
} else {
    header('Location: products.php');
}
exit();
?>