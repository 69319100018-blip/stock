<?php
$page_title = 'บันทึกรับเข้า-เบิกจ่าย - STOCKPRO';
require_once 'db.php';
require_once 'header.php';

$search_sku = trim($_GET['search_sku'] ?? '');
$stmt = $pdo->query("SELECT id, sku, barcode, name, quantity, location_zone FROM products ORDER BY name ASC");
$products = $stmt->fetchAll();

$msg = $_SESSION['msg'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['msg'], $_SESSION['error']);
?>

<div class="container-fluid px-lg-4 py-4">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-xl-6">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-4 gap-2 page-header">
                <div>
                    <h3 class="fw-bold mb-1"><i class="bi bi-arrow-left-right text-primary me-2"></i>บันทึกการเคลื่อนไหวสต็อก</h3>
                    <p class="page-subtitle mb-0">ผู้ทำรายการ: <strong><?= htmlspecialchars($_SESSION['fullname']) ?></strong></p>
                </div>
                <a href="barcodes.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-upc-scan me-1"></i>สแกนบาร์โค้ด</a>
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
                <div class="card-body p-4">
                    <form action="stock_action.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">เลือกสินค้า <span class="text-danger">*</span></label>
                            <select name="product_id" id="productSelect" class="form-select" required>
                                <option value="" selected disabled>-- กรุณาเลือกรายการสินค้า --</option>
                                <?php foreach ($products as $item): 
                                    $selected = ($search_sku && ($item['sku'] === $search_sku || $item['barcode'] === $search_sku)) ? 'selected' : '';
                                ?>
                                    <option value="<?= $item['id'] ?>" data-stock="<?= $item['quantity'] ?>" data-zone="<?= htmlspecialchars($item['location_zone'] ?? '') ?>" <?= $selected ?>>
                                        [<?= htmlspecialchars($item['sku']) ?>] <?= htmlspecialchars($item['name']) ?> (คงเหลือ: <?= $item['quantity'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text mt-1 text-muted" id="stockBadge">กรุณาเลือกสินค้าเพื่อดูสต็อกคงเหลือและพิกัดจัดเก็บ</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">ประเภทรายการ <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                <div class="col-6 col-md-3">
                                    <input type="radio" class="btn-check" name="type" id="typeIN" value="IN" checked>
                                    <label class="btn btn-outline-success w-100 py-2" for="typeIN">
                                        <i class="bi bi-box-arrow-in-down d-block fs-5"></i> รับเข้า (IN)
                                    </label>
                                </div>
                                <div class="col-6 col-md-3">
                                    <input type="radio" class="btn-check" name="type" id="typeOUT" value="OUT">
                                    <label class="btn btn-outline-warning w-100 py-2" for="typeOUT">
                                        <i class="bi bi-box-arrow-up d-block fs-5"></i> เบิกออก (OUT)
                                    </label>
                                </div>
                                <div class="col-6 col-md-3">
                                    <input type="radio" class="btn-check" name="type" id="typeADJUST" value="ADJUST">
                                    <label class="btn btn-outline-info w-100 py-2" for="typeADJUST">
                                        <i class="bi bi-sliders d-block fs-5"></i> ปรับยอด
                                    </label>
                                </div>
                                <div class="col-6 col-md-3">
                                    <input type="radio" class="btn-check" name="type" id="typeDAMAGED" value="DAMAGED">
                                    <label class="btn btn-outline-danger w-100 py-2" for="typeDAMAGED">
                                        <i class="bi bi-x-octagon d-block fs-5"></i> ชำรุด
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" id="qtyLabel">จำนวนที่ต้องการทำรายการ <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" class="form-control" min="1" placeholder="ระบุจำนวนเต็มบวก" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">หมายเหตุ / เลขที่เอกสารอ้างอิง</label>
                            <textarea name="note" class="form-control" rows="2" placeholder="เช่น PO-2026-001, เบิกใช้แผนกไอที, นับสต็อกประจำไตรมาส"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold">
                            <i class="bi bi-save me-1"></i> ยืนยันการทำรายการ
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const productSelect = document.getElementById('productSelect');
const stockBadge = document.getElementById('stockBadge');
const qtyLabel = document.getElementById('qtyLabel');

function updateProductInfo() {
    const selected = productSelect.options[productSelect.selectedIndex];
    if (selected && selected.value) {
        const stock = selected.getAttribute('data-stock');
        const zone = selected.getAttribute('data-zone') || 'ไม่ระบุพิกัด';
        stockBadge.innerHTML = `<span class="badge bg-secondary me-2">คงเหลือ: ${stock} ชิ้น</span> <span class="badge bg-primary-subtle text-primary border"><i class="bi bi-geo-alt me-1"></i>พิกัด: ${zone}</span>`;
    }
}

productSelect.addEventListener('change', updateProductInfo);
window.addEventListener('DOMContentLoaded', updateProductInfo);

document.querySelectorAll('input[name="type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        if (this.value === 'ADJUST') {
            qtyLabel.innerHTML = 'จำนวนยอดจริงหลังปรับนับ <span class="text-danger">*</span>';
        } else {
            qtyLabel.innerHTML = 'จำนวนที่ต้องการทำรายการ <span class="text-danger">*</span>';
        }
    });
});
</script>

<?php require_once 'footer.php'; ?>