<?php
$page_title = 'ขออนุมัติเบิกสินค้า (Requisition) - STOCKPRO';
require_once 'db.php';
require_once 'header.php';

$msg = '';
$error = '';

// ส่งคำขอเบิกสินค้าใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_req') {
    $department = trim($_POST['department'] ?? '');
    $purpose    = trim($_POST['purpose'] ?? '');
    $items      = $_POST['items'] ?? [];

    if (empty($department) || empty($items)) {
        $error = 'กรุณาระบุแผนกและเลือกสินค้าที่ต้องการเบิก';
    } else {
        try {
            $pdo->beginTransaction();
            $req_number = 'REQ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            $note = $department . ($purpose !== '' ? ' | ' . $purpose : '');
            $stmt_req = $pdo->prepare("INSERT INTO requisitions (req_number, user_id, note, status) VALUES (?, ?, ?, 'pending')");
            $stmt_req->execute([$req_number, $_SESSION['user_id'], $note]);
            $req_id = $pdo->lastInsertId();

            $stmt_item = $pdo->prepare("INSERT INTO requisition_items (req_id, product_id, quantity) VALUES (?, ?, ?)");
            foreach ($items as $it) {
                $stmt_item->execute([$req_id, (int)$it['product_id'], (int)$it['quantity']]);
            }

            $pdo->commit();
            $msg = "ส่งคำขอเบิก {$req_number} สำเร็จ รอการอนุมัติจากผู้จัดการ";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
}

// อนุมัติการเบิก (เฉพาะ Admin / Manager)
if (isset($_GET['action']) && in_array($_GET['action'], ['approve', 'reject']) && isset($_GET['id'])) {
    checkAdmin();
    $req_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $action = $_GET['action'];

    if ($req_id) {
        try {
            $pdo->beginTransaction();
            $stmt_chk = $pdo->prepare("SELECT * FROM requisitions WHERE id = ? FOR UPDATE");
            $stmt_chk->execute([$req_id]);
            $req = $stmt_chk->fetch();

            if (!$req || $req['status'] !== 'pending') {
                throw new Exception("คำขอเบิกนี้ได้รับการดำเนินการไปแล้ว");
            }

            if ($action === 'approve') {
                $items_stmt = $pdo->prepare("SELECT ri.*, p.quantity AS current_stock, p.name FROM requisition_items ri JOIN products p ON ri.product_id = p.id WHERE ri.req_id = ?");
                $items_stmt->execute([$req_id]);
                $req_items = $items_stmt->fetchAll();

                // ตรวจสอบสต็อกว่าพอตัดหรือไม่
                foreach ($req_items as $ri) {
                    if ($ri['current_stock'] < $ri['quantity']) {
                        throw new Exception("สต็อกสินค้า {$ri['name']} ไม่เพียงพอ (คงเหลือ {$ri['current_stock']}, ขอเบิก {$ri['quantity']})");
                    }
                }

                $stmt_cut = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                $stmt_log = $pdo->prepare("INSERT INTO stock_movements (product_id, type, quantity, note, user_id) VALUES (?, 'OUT', ?, ?, ?)");

                foreach ($req_items as $ri) {
                    $stmt_cut->execute([$ri['quantity'], $ri['product_id']]);
                    $stmt_log->execute([$ri['product_id'], $ri['quantity'], "เบิกตามใบขอเบิก {$req['req_number']} ({$req['note']})", $_SESSION['user_id']]);
                }

                $stmt_app = $pdo->prepare("UPDATE requisitions SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
                $stmt_app->execute([$_SESSION['user_id'], $req_id]);
                $msg = "อนุมัติคำขอเบิก {$req['req_number']} และตัดสต็อกสินค้าเรียบร้อยแล้ว";
            } elseif ($action === 'reject') {
                $stmt_rej = $pdo->prepare("UPDATE requisitions SET status = 'rejected', approved_by = ?, approved_at = NOW() WHERE id = ?");
                $stmt_rej->execute([$_SESSION['user_id'], $req_id]);
                $msg = "ปฏิเสธคำขอเบิก {$req['req_number']} เรียบร้อย";
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
}

$products = $pdo->query("SELECT id, sku, name, quantity FROM products WHERE quantity > 0 ORDER BY name ASC")->fetchAll();
$requisitions = $pdo->query("
    SELECT r.*, u.fullname AS requester_name, approver.fullname AS approver_name 
    FROM requisitions r 
    JOIN users u ON r.user_id = u.id 
    LEFT JOIN users approver ON r.approved_by = approver.id 
    ORDER BY r.id DESC
")->fetchAll();
?>

<div class="container-fluid px-lg-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-box-arrow-up-right text-primary me-2"></i>ระบบขออนุมัติเบิกสินค้า (Requisitions)</h3>
            <p class="text-muted small mb-0">ส่งคำขอเบิกอุปกรณ์และควบคุมขั้นตอนการอนุมัติก่อนตัดยอดสต็อก</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createReqModal">
            <i class="bi bi-plus-lg me-1"></i>ส่งคำขอเบิกใหม่
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
                            <th class="ps-3">เลขที่คำขอ</th>
                            <th>ผู้ขอเบิก / แผนก</th>
                            <th>วัตถุประสงค์</th>
                            <th>วันที่ขอ</th>
                            <th class="text-center">สถานะ</th>
                            <th class="pe-3 text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requisitions)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">ยังไม่มีรายการขอเบิกสินค้า</td></tr>
                        <?php else: ?>
                            <?php foreach ($requisitions as $r): ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($r['req_number']) ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($r['requester_name']) ?></div>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($r['note'] ?: '-') ?></span>
                                    </td>
                                    <td class="small text-muted text-truncate" style="max-width: 250px;"><?= htmlspecialchars($r['note'] ?: '-') ?></td>
                                    <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                                    <td class="text-center">
                                        <?php if ($r['status'] === 'approved'): ?>
                                            <span class="badge bg-success">อนุมัติแล้ว</span>
                                        <?php elseif ($r['status'] === 'rejected'): ?>
                                            <span class="badge bg-danger">ปฏิเสธ</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">รอการอนุมัติ</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center pe-3">
                                        <?php if ($r['status'] === 'pending' && $_SESSION['role'] === 'admin'): ?>
                                            <a href="requisitions.php?action=approve&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-success me-1" onclick="return confirm('ยืนยันอนุมัติและตัดสต็อกสินค้าหรือไม่?')">
                                                <i class="bi bi-check-lg"></i> อนุมัติ
                                            </a>
                                            <a href="requisitions.php?action=reject&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('ยืนยันปฏิเสธคำขอนี้หรือไม่?')">
                                                <i class="bi bi-x-lg"></i> ปฏิเสธ
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small"><?= $r['approver_name'] ? "โดย " . htmlspecialchars($r['approver_name']) : '-' ?></span>
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

<!-- Modal ขอเบิกสินค้า -->
<div class="modal fade" id="createReqModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-up-right text-primary me-2"></i>สร้างคำขอเบิกสินค้า</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="requisitions.php" method="POST">
                <input type="hidden" name="action" value="create_req">
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">แผนก / ฝ่ายที่ขอเบิก <span class="text-danger">*</span></label>
                            <input type="text" name="department" class="form-control" placeholder="เช่น แผนกจัดส่ง, แผนกผลิต, ฝ่ายการตลาด" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">วัตถุประสงค์การใช้งาน</label>
                            <input type="text" name="purpose" class="form-control" placeholder="เช่น เบิกไปใช้ในงานอีเวนต์, บำรุงรักษาเครื่องจักร" required>
                        </div>
                    </div>

                    <div class="card bg-light border-0 rounded-3 p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="fw-bold small mb-0">เลือกสินค้าและจำนวนที่ต้องการเบิก</label>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-8">
                                <select name="items[0][product_id]" class="form-select form-select-sm" required>
                                    <option value="" disabled selected>-- เลือกสินค้าที่มีในคลัง --</option>
                                    <?php foreach ($products as $pr): ?>
                                        <option value="<?= $pr['id'] ?>">
                                            [<?= htmlspecialchars($pr['sku']) ?>] <?= htmlspecialchars($pr['name']) ?> (คงเหลือ: <?= $pr['quantity'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="number" name="items[0][quantity]" class="form-control form-control-sm" placeholder="จำนวนที่ต้องการเบิก" min="1" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-send me-1"></i>ส่งคำขอเบิก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>