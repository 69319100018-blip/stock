<?php
$page_title = 'ใบสั่งซื้อสินค้า (Purchase Order) - STOCKPRO';
require_once 'db.php';

// เพิ่มคอลัมน์วันสั่งซื้อ / กำหนดส่ง ถ้ายังไม่มี (สำหรับฐานข้อมูลเดิม)
try {
    $cols = $pdo->query("SHOW COLUMNS FROM purchase_orders LIKE 'order_date'")->fetch();
    if (!$cols) {
        $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN order_date DATE NULL AFTER total_amount");
    }
    $cols2 = $pdo->query("SHOW COLUMNS FROM purchase_orders LIKE 'expected_delivery'")->fetch();
    if (!$cols2) {
        $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN expected_delivery DATE NULL AFTER order_date");
    }
} catch (Exception $e) {
    // ข้ามถ้าไม่มีสิทธิ์ ALTER
}

require_once 'header.php';

$msg = '';
$error = '';

// บันทึกสร้าง PO ใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_po') {
    $supplier_id       = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT);
    $order_date        = trim($_POST['order_date'] ?? '');
    $expected_delivery = trim($_POST['expected_delivery'] ?? '');
    $items             = $_POST['items'] ?? [];

    if (!$supplier_id || empty($items)) {
        $error = 'กรุณาเลือกซัพพลายเออร์และระบุรายการสินค้าอย่างน้อย 1 รายการ';
    } elseif ($order_date === '') {
        $error = 'กรุณาระบุวันสั่งซื้อ';
    } else {
        try {
            $pdo->beginTransaction();
            $po_number = 'PO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            $total_amount = 0;
            foreach ($items as $item) {
                $qty  = (int)($item['quantity'] ?? 0);
                $cost = (float)($item['cost_price'] ?? 0);
                $total_amount += ($qty * $cost);
            }

            $delivery = ($expected_delivery !== '') ? $expected_delivery : null;

            $stmt_po = $pdo->prepare("
                INSERT INTO purchase_orders
                    (po_number, supplier_id, total_amount, order_date, expected_delivery, status, created_by)
                VALUES (?, ?, ?, ?, ?, 'pending', ?)
            ");
            $stmt_po->execute([
                $po_number,
                $supplier_id,
                $total_amount,
                $order_date,
                $delivery,
                $_SESSION['user_id']
            ]);
            $po_id = $pdo->lastInsertId();

            $stmt_item = $pdo->prepare("INSERT INTO po_items (po_id, product_id, quantity_ordered, unit_cost) VALUES (?, ?, ?, ?)");
            foreach ($items as $item) {
                $prod_id = (int)$item['product_id'];
                $qty     = (int)$item['quantity'];
                $cost    = (float)$item['cost_price'];
                if ($prod_id > 0 && $qty > 0) {
                    $stmt_item->execute([$po_id, $prod_id, $qty, $cost]);
                }
            }

            $pdo->commit();
            $msg = "สร้างใบสั่งซื้อ {$po_number} สำเร็จเรียบร้อยแล้ว";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
}

// รับสินค้าเข้าคลังตาม PO (Receive Stock)
if (isset($_GET['action']) && $_GET['action'] === 'receive' && isset($_GET['id'])) {
    $po_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($po_id) {
        try {
            $pdo->beginTransaction();
            $stmt_chk = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE");
            $stmt_chk->execute([$po_id]);
            $po = $stmt_chk->fetch();

            if (!$po || $po['status'] === 'received') {
                throw new Exception("ใบสั่งซื้อนี้ไม่สามารถทำรายการรับเข้าได้");
            }

            $items_stmt = $pdo->prepare("SELECT * FROM po_items WHERE po_id = ?");
            $items_stmt->execute([$po_id]);
            $po_items = $items_stmt->fetchAll();

            $stmt_upd_prod = $pdo->prepare("UPDATE products SET quantity = quantity + ? WHERE id = ?");
            $stmt_log = $pdo->prepare("INSERT INTO stock_movements (product_id, type, quantity, note, user_id) VALUES (?, 'IN', ?, ?, ?)");

            foreach ($po_items as $pi) {
                $stmt_upd_prod->execute([$pi['quantity_ordered'], $pi['product_id']]);
                $stmt_log->execute([$pi['product_id'], $pi['quantity_ordered'], "รับเข้าจาก {$po['po_number']}", $_SESSION['user_id']]);
            }

            $stmt_po_status = $pdo->prepare("UPDATE purchase_orders SET status = 'received', received_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt_po_status->execute([$po_id]);

            $pdo->commit();
            $msg = "รับสินค้าเข้าคลังตามใบสั่งซื้อ {$po['po_number']} สำเร็จเรียบร้อย";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
}

$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();
$products = $pdo->query("SELECT id, sku, name, cost_price FROM products ORDER BY name ASC")->fetchAll();

$pos = $pdo->query("
    SELECT po.*, s.name AS supplier_name, u.fullname AS creator_name 
    FROM purchase_orders po 
    JOIN suppliers s ON po.supplier_id = s.id 
    JOIN users u ON po.created_by = u.id 
    ORDER BY po.id DESC
")->fetchAll();
?>

<div class="container-fluid px-lg-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-receipt text-primary me-2"></i>ใบสั่งซื้อสินค้า (Purchase Orders)</h3>
            <p class="text-muted small mb-0">สร้างและติดตามใบสั่งซื้อคู่ค้า พร้อมระบบรับเข้าสต็อกอัตโนมัติ</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPOModal">
            <i class="bi bi-plus-lg me-1"></i>ออกใบสั่งซื้อใหม่
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
                            <th class="ps-3">เลขที่ PO</th>
                            <th>ซัพพลายเออร์</th>
                            <th>วันสั่งซื้อ</th>
                            <th>กำหนดส่ง</th>
                            <th class="text-end">ยอดรวมทั้งสิ้น</th>
                            <th class="text-center">สถานะ</th>
                            <th class="pe-3 text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pos)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">ยังไม่มีประวัติการออกใบสั่งซื้อ</td></tr>
                        <?php else: ?>
                            <?php foreach ($pos as $p): ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($p['po_number']) ?></td>
                                    <td><?= htmlspecialchars($p['supplier_name']) ?></td>
                                    <td class="small">
                                        <?php if (!empty($p['order_date'])): ?>
                                            <?= date('d/m/Y', strtotime($p['order_date'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted"><?= date('d/m/Y', strtotime($p['created_at'])) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?php if (!empty($p['expected_delivery'])): ?>
                                            <?php
                                            $due = strtotime($p['expected_delivery']);
                                            $overdue = ($p['status'] !== 'received' && $due < strtotime('today'));
                                            ?>
                                            <span class="<?= $overdue ? 'text-danger fw-semibold' : '' ?>">
                                                <?= date('d/m/Y', $due) ?>
                                                <?php if ($overdue): ?><i class="bi bi-exclamation-circle ms-1" title="เกินกำหนดส่ง"></i><?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold">฿<?= number_format($p['total_amount'], 2) ?></td>
                                    <td class="text-center">
                                        <?php if ($p['status'] === 'received'): ?>
                                            <span class="badge bg-success">รับเข้าคลังแล้ว</span>
                                        <?php elseif ($p['status'] === 'approved'): ?>
                                            <span class="badge bg-info text-dark">อนุมัติแล้ว</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">รอดำเนินการ</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center pe-3">
                                        <?php if ($p['status'] !== 'received'): ?>
                                            <a href="purchase_orders.php?action=receive&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-success" onclick="return confirm('ยืนยันตรวจรับสินค้าเข้าคลังตาม PO นี้หรือไม่?')">
                                                <i class="bi bi-box-arrow-in-down me-1"></i>รับเข้าคลัง
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small"><i class="bi bi-check2-all text-success me-1"></i>สมบูรณ์</span>
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

<!-- Modal ออกใบสั่งซื้อ PO -->
<div class="modal fade" id="createPOModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-receipt text-primary me-2"></i>ออกใบสั่งซื้อสินค้าใหม่ (PO)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="purchase_orders.php" method="POST">
                <input type="hidden" name="action" value="create_po">
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold">เลือกซัพพลายเออร์ / คู่ค้า <span class="text-danger">*</span></label>
                            <select name="supplier_id" class="form-select" required>
                                <option value="" selected disabled>-- เลือกคู่ค้า --</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">วันสั่งซื้อ <span class="text-danger">*</span></label>
                            <input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">กำหนดส่ง</label>
                            <input type="date" name="expected_delivery" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                        </div>
                    </div>

                    <div class="rounded-3 p-3 mb-3" style="background:#1e293b;border:1px solid rgba(6,182,212,0.2);">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="fw-bold small mb-0">รายการสินค้าที่สั่งซื้อ</label>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addPOItemRow()"><i class="bi bi-plus-lg me-1"></i>เพิ่มรายการ</button>
                        </div>
                        <div id="poItemsContainer">
                            <div class="row g-2 mb-2 po-item-row align-items-center">
                                <div class="col-md-6">
                                    <select name="items[0][product_id]" class="form-select form-select-sm po-prod-select" required onchange="setCost(this, 0)">
                                        <option value="" disabled selected>-- เลือกสินค้า --</option>
                                        <?php foreach ($products as $pr): ?>
                                            <option value="<?= $pr['id'] ?>" data-cost="<?= $pr['cost_price'] ?>">
                                                [<?= htmlspecialchars($pr['sku']) ?>] <?= htmlspecialchars($pr['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" name="items[0][quantity]" class="form-control form-control-sm" placeholder="จำนวน" min="1" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" step="0.01" name="items[0][cost_price]" id="cost_0" class="form-control form-control-sm" placeholder="ราคาทุน/หน่วย" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-save me-1"></i>ยืนยันออกใบสั่งซื้อ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let itemIndex = 1;
function setCost(select, idx) {
    const cost = select.options[select.selectedIndex].getAttribute('data-cost');
    document.getElementById('cost_' + idx).value = cost || 0;
}

function addPOItemRow() {
    const container = document.getElementById('poItemsContainer');
    const firstSelect = document.querySelector('.po-prod-select').innerHTML;
    const div = document.createElement('div');
    div.className = 'row g-2 mb-2 po-item-row align-items-center';
    div.innerHTML = `
        <div class="col-md-6">
            <select name="items[${itemIndex}][product_id]" class="form-select form-select-sm" required onchange="setCost(this, ${itemIndex})">
                ${firstSelect}
            </select>
        </div>
        <div class="col-md-3">
            <input type="number" name="items[${itemIndex}][quantity]" class="form-control form-control-sm" placeholder="จำนวน" min="1" required>
        </div>
        <div class="col-md-3 d-flex gap-1">
            <input type="number" step="0.01" name="items[${itemIndex}][cost_price]" id="cost_${itemIndex}" class="form-control form-control-sm" placeholder="ราคาทุน" required>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.po-item-row').remove()"><i class="bi bi-trash"></i></button>
        </div>
    `;
    container.appendChild(div);
    itemIndex++;
}
</script>

<?php require_once 'footer.php'; ?>