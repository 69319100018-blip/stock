<?php
require_once 'auth_check.php';
require_once 'db.php';
checkAuth();

$action  = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    if ($action === 'clock_in' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $shift_type = trim($_POST['shift_type'] ?? 'กะปกติ (08:00 - 17:00)');
        $note       = trim($_POST['note'] ?? '');

        // ตรวจสอบว่าผู้ใช้งานเข้าเวรค้างไว้หรือไม่
        $check = $pdo->prepare("SELECT id FROM duty_shifts WHERE user_id = ? AND status = 'active'");
        $check->execute([$user_id]);
        if ($check->fetch()) {
            throw new Exception("คุณได้ลงชื่อเข้าเวรไว้อยู่แล้ว กรุณาลงชื่อออกเวรก่อนบันทึกใหม่");
        }

        $stmt = $pdo->prepare("INSERT INTO duty_shifts (user_id, shift_type, note, status) VALUES (?, ?, ?, 'active')");
        $stmt->execute([$user_id, $shift_type, $note]);

        $_SESSION['msg'] = 'บันทึกเข้าเวรสำเร็จ ขอให้ปฏิบัติงานด้วยความปลอดภัยครับ';
    } 
    elseif ($action === 'clock_out' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $shift_id = filter_input(INPUT_POST, 'shift_id', FILTER_VALIDATE_INT);
        $note     = trim($_POST['note'] ?? '');

        if (!$shift_id) {
            throw new Exception("ไม่พบรหัสการเข้าเวร");
        }

        $stmt_check = $pdo->prepare("SELECT note FROM duty_shifts WHERE id = ? AND user_id = ? AND status = 'active'");
        $stmt_check->execute([$shift_id, $user_id]);
        $existing_note = $stmt_check->fetchColumn();

        if ($existing_note === false) {
            throw new Exception("ไม่พบรายการเข้าเวรที่กำลังทำงานอยู่");
        }

        $final_note = !empty($note) ? ($existing_note ? $existing_note . " | สรุปออกเวร: " . $note : "สรุปออกเวร: " . $note) : $existing_note;

        $stmt_out = $pdo->prepare("UPDATE duty_shifts SET clock_out = NOW(), status = 'completed', note = ? WHERE id = ? AND user_id = ?");
        $stmt_out->execute([$final_note, $shift_id, $user_id]);

        $_SESSION['msg'] = 'ลงชื่อออกเวรเรียบร้อยแล้ว ขอบคุณสำหรับการทำงานครับ!';
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
}

header('Location: shifts.php');
exit;