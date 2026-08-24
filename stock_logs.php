<?php
$page_title = 'ประวัติความเคลื่อนไหวสต็อก - STOCKPRO';
require_once 'header.php';
require_once 'db.php';

$type       = $_GET['type'] ?? '';
$product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';
$search     = trim($_GET['search'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($type)) {
    $where[] = "sm.type = ?";
    $params[] = $type;
}
if (!empty($product_id)) {
    $where[] = "sm.product_id = ?";
    $params[] = $product_id;
}
if (!empty($start_date)) {
    $where[] = "DATE(sm.created_at) >= ?";
    $params[] = $start_date;
}
if (!empty($end_date)) {
    $where[] = "DATE(sm.created_at) <= ?";
    $params[] = $end_date;
}
if (!empty($search)) {
    $where[] = "(p.sku LIKE ? OR p.name LIKE ? OR sm.note LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(" AND ", $where);

$sql = "SELECT sm.*, p.name AS product_name, p.sku, u.fullname AS staff_name 
        FROM stock_movements sm
        JOIN products p ON sm.product_id = p.id
        JOIN users u ON sm.user_id = u.id
        WHERE {$where_clause}
        ORDER BY sm.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$summary = ['IN' => 0, 'OUT' => 0, 'ADJUST' => 0, 'DAMAGED' => 0];
foreach ($logs as $log) {
    $summary[$log['type']] += $log['quantity'];
}

$products_list = $pdo->query("SELECT id, sku, name FROM products ORDER BY name ASC")->fetchAll();
?>

<div class="container-fluid px-lg-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-clock-history me-2"></i>รายงานประวัติการเคลื่อนไหวสต็อก</h3>
            <p class="text-muted small mb-0">ตรวจสอบรายการรับเข้า เบิกออก ปรับยอด และสินค้าชำรุดทั้งหมด</p>
        </div>
        <button onclick="exportToCSV()" class="btn btn-success"><i class="bi bi-file-earmark-excel me-1"></i>ส่งออกรายงาน CSV</button>
    </div>

    <!-- Summary Badges -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm border-start border-success border-4 rounded-4 p-3">
                <div class="text-muted small fw-semibold">รับเข้าทั้งหมด (IN)</div>
                <h4 class="fw-bold text-success mb-0">+<?= number_format($summary['IN']) ?> ชิ้น</h4>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm border-start border-warning border-4 rounded-4 p-3">
                <div class="text-muted small fw-semibold">เบิกจ่ายทั้งหมด (OUT)</div>
                <h4 class="fw-bold text-warning text-dark mb-0">-<?= number_format($summary['OUT']) ?> ชิ้น</h4>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm border-start border-info border-4 rounded-4 p-3">
                <div class="text-muted small fw-semibold">ปรับยอดสต็อก (ADJUST)</div>
                <h4 class="fw-bold text-info text-dark mb-0"><?= number_format($summary['ADJUST']) ?> รายการ</h4>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm border-start border-danger border-4 rounded-4 p-3">
                <div class="text-muted small fw-semibold">สินค้าชำรุด (DAMAGED)</div>
                <h4 class="fw-bold text-danger mb-0">-<?= number_format($summary['DAMAGED']) ?> ชิ้น</h4>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">ค้นหาข้อความ</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="SKU, ชื่อสินค้า, หมายเหตุ..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">ประเภทรายการ</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">-- ทั้งหมด --</option>
                        <option value="IN" <?= $type === 'IN' ? 'selected' : '' ?>>รับเข้า</option>
                        <option value="OUT" <?= $type === 'OUT' ? 'selected' : '' ?>>เบิกจ่าย</option>
                        <option value="ADJUST" <?= $type === 'ADJUST' ? 'selected' : '' ?>>ปรับยอด</option>
                        <option value="DAMAGED" <?= $type === 'DAMAGED' ? 'selected' : '' ?>>ชำรุด</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">สินค้าเฉพาะรายการ</label>
                    <select name="product_id" class="form-select form-select-sm">
                        <option value="">-- สินค้าทั้งหมด --</option>
                        <?php foreach ($products_list as $prod): ?>
                            <option value="<?= $prod['id'] ?>" <?= $product_id == $prod['id'] ? 'selected' : '' ?>>
                                [<?= htmlspecialchars($prod['sku']) ?>] <?= htmlspecialchars($prod['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">ตั้งแต่วันที่</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">ถึงวันที่</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-filter"></i></button>
                    <a href="stock_logs.php" class="btn btn-outline-secondary btn-sm" title="ล้างตัวกรอง"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="logTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 170px;">วัน-เวลา</th>
                            <th style="width: 110px;">ประเภท</th>
                            <th>รหัส SKU</th>
                            <th>ชื่อสินค้า</th>
                            <th class="text-center" style="width: 120px;">จำนวน</th>
                            <th>หมายเหตุ / เลขอ้างอิง</th>
                            <th class="pe-3">ผู้บันทึก</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $row): 
                                $badges = ['IN'=>'bg-success', 'OUT'=>'bg-warning text-dark', 'ADJUST'=>'bg-info text-dark', 'DAMAGED'=>'bg-danger'];
                                $type_label = ['IN'=>'รับเข้า', 'OUT'=>'เบิกจ่าย', 'ADJUST'=>'ปรับยอด', 'DAMAGED'=>'ชำรุด'];
                                $prefix = in_array($row['type'], ['OUT', 'DAMAGED']) ? '-' : ($row['type'] === 'IN' ? '+' : '');
                            ?>
                                <tr>
                                    <td class="ps-3 small text-muted"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                                    <td><span class="badge <?= $badges[$row['type']] ?? 'bg-secondary' ?>"><?= $type_label[$row['type']] ?? $row['type'] ?></span></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['sku']) ?></span></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($row['product_name']) ?></td>
                                    <td class="text-center fw-bold <?= in_array($row['type'], ['OUT', 'DAMAGED']) ? 'text-danger' : 'text-success' ?>">
                                        <?= $prefix . number_format($row['quantity']) ?>
                                    </td>
                                    <td class="text-muted small"><?= htmlspecialchars($row['note'] ?: '-') ?></td>
                                    <td class="small pe-3"><?= htmlspecialchars($row['staff_name']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function exportToCSV() {
    const table = document.getElementById("logTable");
    let rows = [];
    for (let i = 0; i < table.rows.length; i++) {
        let row = [], cols = table.rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length; j++) {
            row.push('"' + cols[j].innerText.replace(/"/g, '""').trim() + '"');
        }
        rows.push(row.join(","));
    }
    const csvContent = "\uFEFF" + rows.join("\n");
    const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.setAttribute("href", url);
    link.setAttribute("download", "stock_movement_report_" + new Date().toISOString().slice(0,10) + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php require_once 'footer.php'; ?>