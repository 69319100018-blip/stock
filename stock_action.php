<?php
require_once 'auth_check.php';
require_once 'db.php';
checkAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);$type       = $_POST['type'] ?? '';$qty        = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $note       = trim($_POST['note'] ?? '');
    $user_id    =$_SESSION['user_id'];

    $allowed_types = ['IN', 'OUT', 'ADJUST', 'DAMAGED'];

    if (!$product_id || !in_array($type, $allowed_types) || $qty === false || $qty < 0) {$_SESSION['error'] = 'ข้อมูลที่ส่งมาไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง';
        header('Location: stock_form.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt =$pdo->prepare("SELECT quantity FROM products WHERE id = ? FOR UPDATE");
        $stmt->execute([$product_id]);
        $current_qty =$stmt->fetchColumn();

        if ($current_qty === false) {
            throw new Exception("ไม่พบข้อมูลสินค้ารายการนี้ในฐานข้อมูล");
        }

        if ($type === 'IN') {$new_qty = $current_qty +$qty;
        } elseif (in_array($type, ['OUT', 'DAMAGED'])) {
            if ($current_qty <$qty) {
                throw new Exception("สต็อกคงเหลือไม่เพียงพอ (มีอยู่: {$current_qty}, ต้องการเบิก: {$qty})");
            }
            $new_qty = $current_qty -$qty;
        } elseif ($type === 'ADJUST') {
            $new_qty =$qty;
        }

        $stmt_log =$pdo->prepare("INSERT INTO stock_movements (product_id, type, quantity, note, user_id) VALUES (?, ?, ?, ?, ?)");
        $stmt_log->execute([$product_id,$type, $qty, $note, $user_id]);

        $stmt_update =$pdo->prepare("UPDATE products SET quantity = ? WHERE id = ?");
        $stmt_update->execute([$new_qty,$product_id]);

        $pdo->commit();$_SESSION['msg'] = 'บันทึกรายการสำเร็จ ยอดสต็อกได้รับการอัปเดตแล้ว';
    } catch (Exception $e) {$pdo->rollBack();
        $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }

    header('Location: stock_form.php');
    exit;
} else {
    header('Location: stock_form.php');
    exit;
}