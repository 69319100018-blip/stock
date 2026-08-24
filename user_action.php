<?php
require_once 'auth_check.php';
require_once 'db.php';
checkAdmin();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'staff';

        if (empty($username) || empty($fullname) || empty($password)) {
            throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วน");
        }

        if (strlen($password) < 6) {
            throw new Exception("รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร");
        }

        if (!in_array($role, ['admin', 'staff'])) {
            throw new Exception("สิทธิ์การใช้งานไม่ถูกต้อง");
        }

        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            throw new Exception("ชื่อผู้ใช้นี้ (Username) มีอยู่ในระบบแล้ว");
        }

        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $password_hash, $fullname, $role]);

        $_SESSION['msg'] = 'เพิ่มผู้ใช้งานใหม่สำเร็จ';
    }
    elseif ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id       = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $fullname = trim($_POST['fullname'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'staff';

        if (!$id || empty($fullname)) {
            throw new Exception("ข้อมูลไม่ครบถ้วน");
        }

        if (!in_array($role, ['admin', 'staff'])) {
            throw new Exception("สิทธิ์การใช้งานไม่ถูกต้อง");
        }

        if (!empty($password)) {
            if (strlen($password) < 6) {
                throw new Exception("รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร");
            }
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET fullname = ?, password = ?, role = ? WHERE id = ?");
            $stmt->execute([$fullname, $password_hash, $role, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET fullname = ?, role = ? WHERE id = ?");
            $stmt->execute([$fullname, $role, $id]);
        }

        if ($id == $_SESSION['user_id']) {
            $_SESSION['fullname'] = $fullname;
        }

        $_SESSION['msg'] = 'อัปเดตข้อมูลผู้ใช้งานเรียบร้อยแล้ว';
    }
    elseif ($action === 'delete') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        
        if (!$id) {
            throw new Exception("รหัสผู้ใช้ไม่ถูกต้อง");
        }

        if ($id == $_SESSION['user_id']) {
            throw new Exception("คุณไม่สามารถลบบัญชีของตัวเองที่กำลังล็อกอินอยู่ได้");
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        $_SESSION['msg'] = 'ลบผู้ใช้งานสำเร็จ';
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
}

header('Location: users.php');
exit;