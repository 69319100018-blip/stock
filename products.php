<?php
$page_title = 'รายการสินค้าคงคลัง - STOCKPRO';
require_once 'db.php';
require_once 'header.php';

$search      = trim($_GET['search'] ?? '');
$category_id = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
$status      = $_GET['status'] ?? '';
$page        = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$per_page    = 15; // แสดงผล 15 รายการต่อหน้า
$offset      = ($page - 1) * $per_page;

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(p.sku LIKE ? OR p.name LIKE ? OR p.barcode LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_id) {
    $where[] = "p.category_id = ?";
    $params[] = $category_id;
}

if ($status === 'low') {
    $where[] = "p.quantity <= p.min_threshold AND p.quantity > 0";
} elseif ($status === 'out') {
    $where[] = "p.quantity = 0";
} elseif ($status === 'normal') {
    $where[] = "p.quantity > p.min_threshold";
}

$where_clause = implode(" AND ", $where);

// นับจำนวนทั้งหมดสำหรับแบ่งหน้า
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE {$where_clause}");
$count_stmt->execute($params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $per_page);

// ดึงข้อมูลสินค้า
$sql = "SELECT p.*, c.name AS category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE {$where_clause} 
        ORDER BY p.id DESC 
        LIMIT {$per_page} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
$msg = $_SESSION['msg'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['msg'], $_SESSION['error']);
?>

<div class="container-fluid px-lg-4 py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 page-header">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-boxes text-primary me-2"></i>จัดการสินค้าคงคลัง</h3>
            <p class="page-subtitle mb-0">รายการสินค้า พิกัดจัดเก็บ และตรวจสอบระดับความปลอดภัยสต็อก · รวม <?= number_format($total_rows) ?> รายการ</p>
        </div>
        <a href="product_form.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>เพิ่มสินค้าใหม่</a>
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

    <!-- Search & Filter Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" class="row g-2">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="พิมพ์ค้นหาด้วย SKU, Barcode หรือชื่อสินค้า..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="category_id" class="form-select">
                        <option value="">-- ทุกหมวดหมู่สินค้า --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">-- สถานะสต็อกทั้งหมด --</option>
                        <option value="normal" <?= $status === 'normal' ? 'selected' : '' ?>>สต็อกปกติ</option>
                        <option value="low" <?= $status === 'low' ? 'selected' : '' ?>>ใกล้หมด (Safety Alert)</option>
                        <option value="out" <?= $status === 'out' ? 'selected' : '' ?>>สินค้าหมดสต็อก</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter me-1"></i>กรองข้อมูล</button>
                    <a href="products.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 70px;">รูปภาพ</th>
                            <th>รหัส SKU / Barcode</th>
                            <th>ชื่อสินค้า / หมวดหมู่</th>
                            <th>พิกัดจัดเก็บ</th>
                            <th class="text-end">ราคาทุน</th>
                            <th class="text-end">ราคาขาย</th>
                            <th class="text-center">คงเหลือ / ขั้นต่ำ</th>
                            <th class="text-center pe-3" style="width: 140px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">ไม่พบข้อมูลสินค้าตามเงื่อนไขที่เลือก</td></tr>
                        <?php else: ?>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td class="ps-3">
                                        <?php if (!empty($p['image']) && file_exists('uploads/products/' . $p['image'])): ?>
                                            <img src="uploads/products/<?= htmlspecialchars($p['image']) ?>" alt="Product" class="rounded-3 object-fit-cover border" width="48" height="48">
                                        <?php else: ?>
                                            <div class="bg-light text-secondary rounded-3 d-flex align-items-center justify-content-center border" style="width: 48px; height: 48px;">
                                                <i class="bi bi-box fs-5"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($p['sku']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($p['barcode'] ?: '-') ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($p['name']) ?></div>
                                        <span class="badge bg-light text-secondary border"><?= htmlspecialchars($p['category_name'] ?? 'ไม่ระบุหมวดหมู่') ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                            <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($p['location_zone'] ?? 'Zone A') ?>
                                        </span>
                                    </td>
                                    <td class="text-end">฿<?= number_format($p['cost_price'], 2) ?></td>
                                    <td class="text-end fw-bold text-primary">฿<?= number_format($p['sell_price'], 2) ?></td>
                                    <td class="text-center">
                                        <?php if ($p['quantity'] <= 0): ?>
                                            <span class="badge bg-danger">หมดสต็อก</span>
                                        <?php elseif ($p['quantity'] <= $p['min_threshold']): ?>
                                            <span class="badge bg-warning text-dark"><?= $p['quantity'] ?> ชิ้น (ใกล้หมด)</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><?= $p['quantity'] ?> ชิ้น</span>
                                        <?php endif; ?>
                                        <div class="small text-muted">/ ขั้นต่ำ <?= $p['min_threshold'] ?></div>
                                    </td>
                                    <td class="text-center pe-3">
                                        <a href="stock_form.php?search_sku=<?= urlencode($p['sku']) ?>" class="btn btn-outline-success btn-sm me-1" title="รับเข้า/เบิกจ่าย">
                                            <i class="bi bi-arrow-left-right"></i>
                                        </a>
                                        <a href="product_form.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm me-1" title="แก้ไข">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <a href="product_action.php?action=delete&id=<?= $p['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('ยืนยันลบสินค้านี้ออกจากระบบหรือไม่?')" title="ลบ">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar (5 รายการต่อหน้า) -->
            <?php if ($total_pages > 1): ?>
                <div class="p-3 border-top d-flex justify-content-between align-items-center">
                    <span class="text-muted small">แสดงผลหน้า <?= $page ?> จากทั้งหมด <?= $total_pages ?> หน้า (รวม <?= number_format($total_rows) ?> รายการ)</span>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">ก่อนหน้า</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">ถัดไป</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>