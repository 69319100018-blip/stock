<?php
require_once 'auth_check.php';
require_once 'db.php';
checkAdmin();

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if (!empty($name)) {
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$name]);
            $msg = 'เพิ่มหมวดหมู่สินค้าเรียบร้อยแล้ว';
        } else {
            $error = 'กรุณากรอกชื่อหมวดหมู่';
        }
    } elseif ($action === 'edit') {
        $id   = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $name = trim($_POST['name'] ?? '');
        if ($id && !empty($name)) {
            $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            $msg = 'อัปเดตชื่อหมวดหมู่เรียบร้อยแล้ว';
        } else {
            $error = 'ข้อมูลไม่ถูกต้อง';
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            $error = 'ไม่สามารถลบได้ เนื่องจากมีรายการสินค้าที่อยู่ในหมวดหมู่นี้';
        } else {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $msg = 'ลบหมวดหมู่สินค้าเรียบร้อยแล้ว';
        }
    }
}

$categories = $pdo->query("
    SELECT c.*, COUNT(p.id) AS product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id 
    GROUP BY c.id 
    ORDER BY c.id DESC
")->fetchAll();

$page_title = 'จัดการหมวดหมู่สินค้า - STOCKPRO';
require_once 'header.php';
?>

<div class="container-fluid px-lg-4 py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 page-header">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-tags text-primary me-2"></i>จัดการหมวดหมู่สินค้า</h3>
            <p class="page-subtitle mb-0">กำหนดกลุ่มสินค้าเพื่อความสะดวกในการจัดหมวดหมู่และค้นหา</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCatModal">
            <i class="bi bi-plus-lg me-1"></i>เพิ่มหมวดหมู่ใหม่
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
                            <th class="ps-4" style="width: 100px;">รหัส</th>
                            <th>ชื่อหมวดหมู่</th>
                            <th class="text-center" style="width: 180px;">จำนวนสินค้าในหมวด</th>
                            <th class="text-center pe-4" style="width: 140px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">ยังไม่มีข้อมูลหมวดหมู่สินค้า</td></tr>
                        <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td class="ps-4 text-muted">#<?= $cat['id'] ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($cat['name']) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?= number_format($cat['product_count']) ?> รายการ</span>
                                    </td>
                                    <td class="text-center pe-4">
                                        <button class="btn btn-outline-primary btn-sm me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editCatModal" 
                                                data-id="<?= $cat['id'] ?>" 
                                                data-name="<?= htmlspecialchars($cat['name']) ?>" 
                                                title="แก้ไข">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <a href="categories.php?action=delete&id=<?= $cat['id'] ?>" 
                                           class="btn btn-outline-danger btn-sm" 
                                           onclick="return confirm('ยืนยันลบหมวดหมู่ <?= htmlspecialchars($cat['name']) ?> หรือไม่?')" 
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

<!-- Modal เพิ่ม/แก้ไข หมวดหมู่ -->
<div class="modal fade" id="addCatModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">เพิ่มหมวดหมู่ใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="categories.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">ชื่อหมวดหมู่ <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="เช่น อุปกรณ์ไอที, เครื่องเขียน" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-save me-1"></i>บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editCatModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">แก้ไขชื่อหมวดหมู่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="categories.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editCatId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">ชื่อหมวดหมู่ <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editCatName" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-save me-1"></i>บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const editCatModal = document.getElementById('editCatModal');
if (editCatModal) {
    editCatModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        document.getElementById('editCatId').value = button.getAttribute('data-id');
        document.getElementById('editCatName').value = button.getAttribute('data-name');
    });
}
</script>

<?php require_once 'footer.php'; ?>