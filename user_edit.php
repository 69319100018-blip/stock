<?php
$page_title = 'แก้ไขผู้ใช้งาน - STOCKPRO';
require_once 'header.php';
require_once 'db.php';
checkAdmin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: users.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, username, fullname, role FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = 'ไม่พบข้อมูลผู้ใช้งาน';
    header('Location: users.php');
    exit;
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0"><i class="bi bi-person-gear text-primary me-2"></i>แก้ไขข้อมูลผู้ใช้งาน</h4>
                <a href="users.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>กลับหน้ารายการ</a>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <form action="user_action.php" method="POST">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?= $user['id'] ?>">

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">ชื่อผู้ใช้ (Username)</label>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                            <div class="form-text">Username ไม่สามารถเปลี่ยนได้</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                            <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">เปลี่ยนรหัสผ่านใหม่</label>
                            <input type="password" name="password" class="form-control" minlength="6" placeholder="ปล่อยว่างไว้หากไม่ต้องการเปลี่ยน">
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-semibold">ระดับสิทธิ์ (Role) <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required <?= ($user['id'] == $_SESSION['user_id']) ? 'disabled' : '' ?>>
                                <option value="staff" <?= $user['role'] === 'staff' ? 'selected' : '' ?>>Warehouse Staff (พนักงานคลังสินค้า)</option>
                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin / Manager (ผู้ดูแลระบบ)</option>
                            </select>
                            <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                <input type="hidden" name="role" value="<?= $user['role'] ?>">
                                <div class="form-text text-danger">ไม่สามารถลดระดับสิทธิ์ของบัญชีตัวเองได้</div>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold">
                            <i class="bi bi-save me-1"></i> บันทึกการแก้ไข
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>