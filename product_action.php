<?php
require_once 'auth_check.php';
require_once 'db.php';
checkAuth();

$action =$_POST['action'] ?? $_GET['action'] ?? '';$upload_dir = 'uploads/products/';

function uploadProductImage($file,$upload_dir) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    $file_type = mime_content_type($file['tmp_name']);
    $file_size =$file['size'];

    if (!in_array($file_type,$allowed_types)) {
        throw new Exception("รองรับเฉพาะไฟล์ JPG, PNG และ WEBP เท่านั้น");
    }

    if ($file_size > 2 * 1024 * 1024) {
        throw new Exception("ขนาดไฟล์ต้องไม่เกิน 2 MB");
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'prod_' . uniqid() . '.' . strtolower($ext);

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0775, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $upload_dir .$filename)) {
        throw new Exception("ไม่สามารถบันทึกรูปภาพได้");
    }

    return $filename;
}

try {
    if ($action === 'add' &&$_SERVER['REQUEST_METHOD'] === 'POST') {
        $sku           = trim($_POST['sku'] ?? '');
        $barcode       = trim($_POST['barcode'] ?? '') ?: null;
        $name          = trim($_POST['name'] ?? '');$category_id   = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $location_zone = trim($_POST['location_zone'] ?? 'Zone A');
        $cost_price    = (float)($_POST['cost_price'] ?? 0);
        $sell_price    = (float)($_POST['sell_price'] ?? 0);
        $quantity      = (int)($_POST['quantity'] ?? 0);
        $min_threshold = (int)($_POST['min_threshold'] ?? 5);
        $description   = trim($_POST['description'] ?? '');

        if (empty($sku) || empty($name)) {
            throw new Exception("กรุณากรอก SKU และชื่อสินค้า");
        }

        $check_sku =$pdo->prepare("SELECT id FROM products WHERE sku = ?");
        $check_sku->execute([$sku]);
        if ($check_sku->fetch()) {
            throw new Exception("รหัส SKU นี้มีอยู่ในระบบแล้ว");
        }

        $image = uploadProductImage($_FILES['product_image'] ?? null, $upload_dir);

        $stmt =$pdo->prepare("INSERT INTO products (sku, barcode, name, description, category_id, location_zone, cost_price, sell_price, quantity, min_threshold, image) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$sku, $barcode,$name, $description,$category_id, $location_zone,$cost_price, $sell_price,$quantity, $min_threshold,$image]);

        if ($quantity > 0) {
            $new_product_id =$pdo->lastInsertId();
            $stmt_log =$pdo->prepare("INSERT INTO stock_movements (product_id, type, quantity, note, user_id) VALUES (?, 'IN', ?, 'สต็อกเริ่มต้น', ?)");
            $stmt_log->execute([$new_product_id, $quantity,$_SESSION['user_id']]);
        }

        $_SESSION['msg'] = 'เพิ่มสินค้าสำเร็จเรียบร้อย';
    } 
    elseif ($action === 'edit' &&$_SERVER['REQUEST_METHOD'] === 'POST') {
        $id            = (int)$_POST['id'];
        $sku           = trim($_POST['sku'] ?? '');
        $barcode       = trim($_POST['barcode'] ?? '') ?: null;
        $name          = trim($_POST['name'] ?? '');$category_id   = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $location_zone = trim($_POST['location_zone'] ?? 'Zone A');
        $cost_price    = (float)($_POST['cost_price'] ?? 0);
        $sell_price    = (float)($_POST['sell_price'] ?? 0);
        $min_threshold = (int)($_POST['min_threshold'] ?? 5);
        $description   = trim($_POST['description'] ?? '');

        if (empty($id) || empty($sku) || empty($name)) {
            throw new Exception("ข้อมูลไม่ครบถ้วน");
        }

        $check_sku =$pdo->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
        $check_sku->execute([$sku,$id]);
        if ($check_sku->fetch()) {
            throw new Exception("รหัส SKU นี้ถูกใช้งานแล้วโดยสินค้าอื่น");
        }

        $stmt_old =$pdo->prepare("SELECT image FROM products WHERE id = ?");
        $stmt_old->execute([$id]);
        $old_image =$stmt_old->fetchColumn();

        $image = uploadProductImage($_FILES['product_image'] ?? null, $upload_dir);

        if ($image) {
            if (!empty($old_image) && file_exists($upload_dir .$old_image)) {
                unlink($upload_dir .$old_image);
            }
            $stmt =$pdo->prepare("UPDATE products SET sku=?, barcode=?, name=?, description=?, category_id=?, location_zone=?, cost_price=?, sell_price=?, min_threshold=?, image=? WHERE id=?");
            $stmt->execute([$sku, $barcode,$name, $description,$category_id, $location_zone,$cost_price, $sell_price,$min_threshold, $image,$id]);
        } else {
            $stmt =$pdo->prepare("UPDATE products SET sku=?, barcode=?, name=?, description=?, category_id=?, location_zone=?, cost_price=?, sell_price=?, min_threshold=? WHERE id=?");
            $stmt->execute([$sku,$barcode, $name,$description, $category_id,$location_zone, $cost_price,$sell_price, $min_threshold,$id]);
        }

        $_SESSION['msg'] = 'อัปเดตข้อมูลสินค้าเรียบร้อย';
    } 
    elseif ($action === 'delete') {$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            throw new Exception("รหัสสินค้าไม่ถูกต้อง");
        }

        $stmt =$pdo->prepare("SELECT image FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $image =$stmt->fetchColumn();

        $stmt_del =$pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt_del->execute([$id]);

        if (!empty($image) && file_exists($upload_dir .$image)) {
            unlink($upload_dir .$image);
        }

        $_SESSION['msg'] = 'ลบรายการสินค้าเรียบร้อยแล้ว';
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
}

header('Location: products.php');
exit;