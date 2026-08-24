<?php
$page_title = 'จัดการผู้ใช้งานและสิทธิ์ - STOCKPRO';
require_once 'db.php';
require_once 'auth_check.php';
checkAdmin();
require_once 'header.php';

$search = trim($_GET['search'] ?? '');

$sql = "SELECT id, username, fullname, role, created_at FROM users";
$params = [];

if (!empty($search)) {
    $sql .= " WHERE username LIKE ? OR fullname LIKE ?";
    $params = ["%$search%", "%$search%"];
}
$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$msg = $_SESSION['msg'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['msg'], $_SESSION['error']);
?>

<div class="container-fluid px-lg-4 py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 page-header">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-people text-primary me-2"></i>จัดการผู้ใช้งานและสิทธิ์</h3>
            <p class="page-subtitle mb-0">เพิ่ม ลบ แก้ไขสิทธิ์การเข้าถึงระบบของพนักงาน (Admin Only)</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-person-plus me-1"></i>เพิ่มผู้ใช้งานใหม่
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" class="row g-2">
                <div class="col-md-10">
                    <input type="text" name="search" class="form-control" placeholder="ค้นหาด้วยชื่อผู้ใช้ (Username) หรือ ชื่อ-นามสกุล..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-dark w-100"><i class="bi bi-search me-1"></i>ค้นหา</button>
                    <?php if (!empty($search)): ?>
                        <a href="users.php" class="btn btn-outline-secondary">ล้าง</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">ชื่อผู้ใช้ (Username)</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th class="text-center">ระดับสิทธิ์ (Role)</th>
                            <th>วันที่สร้าง</th>
                            <th class="text-center pe-4" style="width: 140px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">ไม่พบข้อมูลผู้ใช้งาน</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td class="ps-4 fw-bold">
                                        <i class="bi bi-person-badge text-muted me-2"></i><?= htmlspecialchars($u['username']) ?>
                                        <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                            <span class="badge bg-secondary ms-1">คุณ</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($u['fullname']) ?></td>
                                    <td class="text-center">
                                        <?php if ($u['role'] === 'admin'): ?>
                                            <span class="badge bg-danger"><i class="bi bi-shield-lock me-1"></i>Admin</span>
                                        <?php else: ?>
                                            <span class="badge bg-info text-dark"><i class="bi bi-person me-1"></i>Staff</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($u['created_at'])) ?></td>
                                    <td class="text-center pe-4">
                                        <a href="user_edit.php?id=<?= $u['id'] ?>" class="btn btn-outline-primary btn-sm me-1" title="แก้ไข">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                            <a href="user_action.php?action=delete&id=<?= $u['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('ยืนยันลบผู้ใช้งาน <?= htmlspecialchars($u['username']) ?> หรือไม่?')" title="ลบ">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-outline-secondary btn-sm" disabled title="ไม่สามารถลบบัญชีตัวเองได้"><i class="bi bi-slash-circle"></i></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal เพิ่มผู้ใช้งาน -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus text-primary me-2"></i>เพิ่มผู้ใช้งานใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="user_action.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">ชื่อผู้ใช้ (Username) <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" placeholder="เช่น somchai_k" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                        <input type="text" name="fullname" class="form-control" placeholder="เช่น สมชาย เข็มกลัด" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">รหัสผ่าน (Password) <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" minlength="6" placeholder="กำหนดรหัสผ่านอย่างน้อย 6 ตัวอักษร" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">ระดับสิทธิ์ (Role) <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="staff" selected>Warehouse Staff (พนักงานคลังสินค้า)</option>
                            <option value="admin">Admin / Manager (ผู้ดูแลระบบ)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-save me-1"></i> บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>