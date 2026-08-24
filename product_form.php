<?php
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$is_edit = !empty($id);
$page_title = ($is_edit ? 'แก้ไขสินค้า' : 'เพิ่มสินค้าใหม่') . ' - STOCKPRO';

require_once 'header.php';
require_once 'db.php';

$product = [
    'sku' => '',
    'barcode' => '',
    'name' => '',
    'description' => '',
    'category_id' => '',
    'location_zone' => 'Zone A',
    'cost_price' => '0.00',
    'sell_price' => '0.00',
    'quantity' => 0,
    'min_threshold' => 5,
    'image' => ''
];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        $_SESSION['error'] = 'ไม่พบข้อมูลสินค้า';
        header('Location: products.php');
        exit;
    }
    $product = $existing;
}

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0">
                    <i class="bi bi-<?= $is_edit ? 'pencil-square' : 'plus-circle' ?> text-primary me-2"></i>
                    <?= $is_edit ? 'แก้ไขข้อมูลสินค้า' : 'เพิ่มสินค้าใหม่เข้าคลัง' ?>
                </h4>
                <a href="products.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>กลับหน้ารายการ</a>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <form action="product_action.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="<?= $is_edit ? 'edit' : 'add' ?>">
                        <?php if ($is_edit): ?>
                            <input type="hidden" name="id" value="<?= $product['id'] ?>">
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">รหัสสินค้า (SKU) <span class="text-danger">*</span></label>
                                <input type="text" name="sku" class="form-control" value="<?= htmlspecialchars($product['sku']) ?>" required placeholder="เช่น SKU-001">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">รหัสบาร์โค้ด (Barcode / QR)</label>
                                <input type="text" name="barcode" class="form-control" value="<?= htmlspecialchars($product['barcode'] ?? '') ?>" placeholder="เช่น 8851234567890">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ชื่อสินค้า <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($product['name']) ?>" required placeholder="ระบุชื่อสินค้า">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">หมวดหมู่สินค้า</label>
                                <select name="category_id" class="form-select">
                                    <option value="">-- ไม่ระบุหมวดหมู่ --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= $product['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">พิกัดจัดเก็บ (Location & Zone)</label>
                                <input type="text" name="location_zone" class="form-control" value="<?= htmlspecialchars($product['location_zone'] ?? 'Zone A') ?>" placeholder="เช่น Zone A - Shelf 02 - Bin 15">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">จุดสั่งซื้อขั้นต่ำ (Safety Min Threshold)</label>
                                <input type="number" name="min_threshold" class="form-control" value="<?= $product['min_threshold'] ?>" min="0" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ราคาทุน (บาท)</label>
                                <input type="number" step="0.01" name="cost_price" class="form-control" value="<?= $product['cost_price'] ?>" min="0" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ราคาขาย (บาท)</label>
                                <input type="number" step="0.01" name="sell_price" class="form-control" value="<?= $product['sell_price'] ?>" min="0" required>
                            </div>

                            <?php if (!$is_edit): ?>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">จำนวนเริ่มต้นรับเข้าคลัง</label>
                                <input type="number" name="quantity" class="form-control" value="0" min="0" required>
                            </div>
                            <?php endif; ?>

                            <div class="col-12">
                                <label class="form-label fw-semibold">รายละเอียดสินค้า</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="ระบุรายละเอียดเพิ่มเติมของสินค้า..."><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">รูปภาพสินค้า</label>
                                <input type="file" name="product_image" class="form-control" accept="image/png, image/jpeg, image/webp">
                                <div class="form-text">รองรับไฟล์ JPG, PNG, WEBP ขนาดไม่เกิน 2MB</div>
                                <?php if ($is_edit && !empty($product['image']) && file_exists('uploads/products/' . $product['image'])): ?>
                                    <div class="mt-2 d-flex align-items-center gap-3">
                                        <img src="uploads/products/<?= htmlspecialchars($product['image']) ?>" alt="Current Image" class="rounded border" width="80" height="80">
                                        <span class="text-muted small">รูปภาพปัจจุบันในระบบ</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <hr class="my-4">

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="bi bi-save me-1"></i> บันทึกข้อมูลสินค้า
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>