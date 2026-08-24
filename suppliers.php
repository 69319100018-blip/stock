<?php
require_once 'auth_check.php';
require_once 'db.php';
checkAdmin();

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($name)) {
        $error = 'กรุณาระบุชื่อบริษัท/ร้านค้าคู่ค้า';
    } else {
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO suppliers (name, phone, email, address) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $email, $address]);
            $msg = 'เพิ่มข้อมูลซัพพลายเออร์เรียบร้อยแล้ว';
        } elseif ($action === 'edit') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, phone = ?, email = ?, address = ? WHERE id = ?");
                $stmt->execute([$name, $phone, $email, $address, $id]);
                $msg = 'อัปเดตข้อมูลคู่ค้าเรียบร้อยแล้ว';
            }
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
        $stmt->execute([$id]);
        $msg = 'ลบข้อมูลซัพพลายเออร์เรียบร้อยแล้ว';
    }
}

$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY id DESC")->fetchAll();

$page_title = 'จัดการซัพพลายเออร์ - STOCKPRO';
require_once 'header.php';
?>

<div class="container-fluid px-lg-4 py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 page-header">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-truck text-primary me-2"></i>จัดการข้อมูลคู่ค้าและซัพพลายเออร์</h3>
            <p class="page-subtitle mb-0">บันทึกช่องทางการติดต่อ ข้อมูลคู่ค้าสำหรับสั่งซื้อสินค้าเข้าคลัง</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="prepareAddSupplier()">
            <i class="bi bi-plus-lg me-1"></i>เพิ่มคู่ค้าใหม่
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

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">ชื่อบริษัท/ร้านค้า</th>
                            <th>เบอร์โทรศัพท์</th>
                            <th>อีเมล</th>
                            <th>ที่อยู่</th>
                            <th class="text-center pe-4" style="width: 140px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($suppliers)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">ยังไม่มีข้อมูลคู่ค้า/ซัพพลายเออร์</td></tr>
                        <?php else: ?>
                            <?php foreach ($suppliers as $s): ?>
                                <tr>
                                    <td class="ps-4 fw-bold">
                                        <i class="bi bi-building text-muted me-2"></i><?= htmlspecialchars($s['name']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($s['phone'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($s['email'] ?: '-') ?></td>
                                    <td class="small text-muted text-truncate" style="max-width: 250px;">
                                        <?= htmlspecialchars($s['address'] ?: '-') ?>
                                    </td>
                                    <td class="text-center pe-4">
                                        <button class="btn btn-outline-primary btn-sm me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#supplierModal" 
                                                data-id="<?= $s['id'] ?>"
                                                data-name="<?= htmlspecialchars($s['name']) ?>"
                                                data-phone="<?= htmlspecialchars($s['phone'] ?? '') ?>"
                                                data-email="<?= htmlspecialchars($s['email'] ?? '') ?>"
                                                data-address="<?= htmlspecialchars($s['address'] ?? '') ?>"
                                                onclick="prepareEditSupplier(this)" 
                                                title="แก้ไข">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <a href="suppliers.php?action=delete&id=<?= $s['id'] ?>" 
                                           class="btn btn-outline-danger btn-sm" 
                                           onclick="return confirm('ยืนยันลบข้อมูลคู่ค้า <?= htmlspecialchars($s['name']) ?> หรือไม่?')" 
                                           title="ลบ">
                                            <i class="bi bi-trash"></i>
                                        </a>
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

<div class="modal fade" id="supplierModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="supplierModalTitle">เพิ่มคู่ค้าใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="suppliers.php" method="POST">
                <input type="hidden" name="action" id="suppAction" value="add">
                <input type="hidden" name="id" id="suppId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">ชื่อบริษัท / ร้านค้า <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="suppName" class="form-control" required placeholder="เช่น บจก. สยาม โลจิสติกส์">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">เบอร์โทรศัพท์</label>
                            <input type="text" name="phone" id="suppPhone" class="form-control" placeholder="02-xxx-xxxx / 08x-xxxxxxx">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">อีเมล</label>
                            <input type="email" name="email" id="suppEmail" class="form-control" placeholder="contact@company.com">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">ที่อยู่ / สถานประกอบการ</label>
                        <textarea name="address" id="suppAddress" class="form-control" rows="3" placeholder="ระบุที่อยู่ เลขที่ ถนน อำเภอ จังหวัด รหัสไปรษณีย์"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-save me-1"></i>บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function prepareAddSupplier() {
    document.getElementById('supplierModalTitle').innerText = 'เพิ่มคู่ค้าใหม่';
    document.getElementById('suppAction').value = 'add';
    document.getElementById('suppId').value = '';
    document.getElementById('suppName').value = '';
    document.getElementById('suppPhone').value = '';
    document.getElementById('suppEmail').value = '';
    document.getElementById('suppAddress').value = '';
}

function prepareEditSupplier(button) {
    document.getElementById('supplierModalTitle').innerText = 'แก้ไขข้อมูลคู่ค้า';
    document.getElementById('suppAction').value = 'edit';
    document.getElementById('suppId').value = button.getAttribute('data-id');
    document.getElementById('suppName').value = button.getAttribute('data-name');
    document.getElementById('suppPhone').value = button.getAttribute('data-phone');
    document.getElementById('suppEmail').value = button.getAttribute('data-email');
    document.getElementById('suppAddress').value = button.getAttribute('data-address');
}
</script>

<?php require_once 'footer.php'; ?>