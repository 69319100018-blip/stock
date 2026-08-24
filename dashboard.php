<?php
$page_title = 'ศูนย์ควบคุมคลังสินค้า (WMS Executive Dashboard) - StockPro';
require_once 'db.php';
require_once 'header.php';

// ---------------------------------------------------------
// 1. คำนวณภาพรวมสถิติและดัชนีชี้วัดคลังสินค้า (WMS Core KPIs)
// ---------------------------------------------------------

// สรุปจำนวน SKU, จำนวนชิ้นรวม, มูลค่าต้นทุนรวม และมูลค่าราคาขายรวม
$kpi = $pdo->query("
    SELECT 
        COUNT(*) as total_skus,
        COALESCE(SUM(quantity), 0) as total_units,
        COALESCE(SUM(quantity * cost_price), 0) as total_cost_val,
        COALESCE(SUM(quantity * sell_price), 0) as total_sell_val
    FROM products
")->fetch();

$total_skus = (int)$kpi['total_skus'];
$total_units = (int)$kpi['total_units'];
$total_cost_val = (float)$kpi['total_cost_val'];
$total_sell_val = (float)$kpi['total_sell_val'];
$estimated_profit_margin = $total_sell_val - $total_cost_val;

// สรุปสถานะความเสี่ยงสต็อก (Out of Stock & Low Stock)
$out_of_stock_count = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE quantity = 0")->fetchColumn();
$low_stock_count = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE quantity > 0 AND quantity <= min_threshold")->fetchColumn();

// สรุปกิจกรรมคลังประจำวัน (Today's Operations)
$today_in = (int)$pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE type = 'IN' AND DATE(created_at) = CURDATE()")->fetchColumn();
$today_out = (int)$pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE type = 'OUT' AND DATE(created_at) = CURDATE()")->fetchColumn();
$today_tx = (int)$pdo->query("SELECT COUNT(*) FROM stock_movements WHERE DATE(created_at) = CURDATE()")->fetchColumn();

// ---------------------------------------------------------
// 2. ดึงข้อมูลสินค้าที่ต้องดำเนินการด่วน (Urgent Safety Stock Alerts)
// ---------------------------------------------------------
$critical_products = $pdo->query("
    SELECT p.*, c.name AS category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.quantity <= p.min_threshold 
    ORDER BY p.quantity ASC, p.min_threshold DESC 
    LIMIT 6
")->fetchAll();

// ---------------------------------------------------------
// 3. ดึงประวัติความเคลื่อนไหวล่าสุด (Real-time Audit Logs)
// ---------------------------------------------------------
$recent_movements = $pdo->query("
    SELECT sm.*, p.name AS product_name, p.sku, u.fullname AS staff_name 
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    JOIN users u ON sm.user_id = u.id
    ORDER BY sm.created_at DESC LIMIT 6
")->fetchAll();

// ---------------------------------------------------------
// 4. สรุปมูลค่าสต็อกตามหมวดหมู่ (Category Inventory Distribution)
// ---------------------------------------------------------
$cat_analytics = $pdo->query("
    SELECT 
        COALESCE(c.name, 'ไม่ระบุหมวดหมู่') as category_name, 
        COUNT(p.id) as sku_count, 
        COALESCE(SUM(p.quantity), 0) as total_qty,
        COALESCE(SUM(p.quantity * p.cost_price), 0) as total_val
    FROM categories c
    RIGHT JOIN products p ON c.id = p.category_id
    GROUP BY c.id, c.name
    ORDER BY total_val DESC
    LIMIT 5
")->fetchAll();

// ---------------------------------------------------------
// 5. เตรียมข้อมูลกราฟแนวโน้มการหมุนเวียนสต็อก 7 วันย้อนหลัง (7-Day Trend Chart)
// ---------------------------------------------------------
$dates_map = [];
$chart_in = [];
$chart_out = [];

for ($i = 6; $i >= 0; $i--) {
    $date_str = date('Y-m-d', strtotime("-$i days"));
    $display_str = date('d/m', strtotime("-$i days"));
    $dates_map[$date_str] = $display_str;
    $chart_in[$date_str] = 0;
    $chart_out[$date_str] = 0;
}

$chart_raw = $pdo->query("
    SELECT DATE(created_at) as move_date, type, SUM(quantity) as qty
    FROM stock_movements
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      AND type IN ('IN', 'OUT')
    GROUP BY DATE(created_at), type
")->fetchAll();

foreach ($chart_raw as $row) {
    $mdate = $row['move_date'];
    if (isset($dates_map[$mdate])) {
        if ($row['type'] === 'IN') {
            $chart_in[$mdate] = (int)$row['qty'];
        } elseif ($row['type'] === 'OUT') {
            $chart_out[$mdate] = (int)$row['qty'];
        }
    }
}

$chart_labels = json_encode(array_values($dates_map), JSON_UNESCAPED_UNICODE);
$chart_in_data = json_encode(array_values($chart_in));
$chart_out_data = json_encode(array_values($chart_out));

// หมวดหมู่สำหรับกราฟวงกลม
$cat_names = json_encode(array_column($cat_analytics, 'category_name'), JSON_UNESCAPED_UNICODE);
$cat_values = json_encode(array_column($cat_analytics, 'total_val'));
?>

<!-- Load Chart.js for Warehouse Analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="container-fluid px-4 py-4">
    
    <!-- HEADER BAR: TITLE & QUICK ACTIONS -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary rounded-pill px-3 py-1 fs-7"><i class="bi bi-shield-check me-1"></i> Live WMS Control</span>
                <span class="text-muted small"><i class="bi bi-clock me-1"></i>อัปเดตข้อมูลล่าสุด: <?= date('d/m/Y H:i') ?> น.</span>
            </div>
            <h3 class="fw-bold mb-0">ศูนย์ควบคุมภาพรวมคลังสินค้า</h3>
            <p class="text-muted small mb-0">ยินดีต้อนรับคุณ <strong class="text-gradient"><?= htmlspecialchars($_SESSION['fullname']) ?></strong> · <?= $_SESSION['role'] === 'admin' ? 'ผู้จัดการคลังสินค้า' : 'เจ้าหน้าที่คลังสินค้า' ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="stock_form.php" class="btn btn-primary shadow-sm fw-semibold">
                <i class="bi bi-arrow-left-right me-1"></i> บันทึก เบิก-รับ สินค้า
            </a>
            <a href="product_form.php" class="btn btn-outline-primary shadow-sm fw-semibold">
                <i class="bi bi-box-seam me-1"></i> เพิ่มรายการสินค้า
            </a>
            <a href="stock_logs.php" class="btn btn-outline-secondary shadow-sm fw-semibold">
                <i class="bi bi-file-earmark-excel me-1"></i> ออกรายงาน
            </a>
        </div>
    </div>

    <!-- 1. KEY PERFORMANCE INDICATORS (KPI CARDS) -->
    <div class="row g-3 mb-4">
        <!-- KPI 1: Inventory Valuation -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 position-relative overflow-hidden">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-bold text-uppercase">มูลค่าคลังสินค้ารวม</span>
                        <div class="bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="bi bi-currency-dollar fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1">฿<?= number_format($total_cost_val, 2) ?></h3>
                    <div class="d-flex align-items-center justify-content-between small">
                        <span class="text-muted">ราคาประเมินขาย: ฿<?= number_format($total_sell_val, 2) ?></span>
                        <span class="badge bg-success-light text-success fw-bold">+฿<?= number_format($estimated_profit_margin, 0) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 2: Total Units & SKUs -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 position-relative overflow-hidden">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-bold text-uppercase">จำนวนสินค้าในสต็อก</span>
                        <div class="bg-info text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="bi bi-boxes fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1"><?= number_format($total_units) ?> <span class="fs-6 fw-normal text-muted">ชิ้น</span></h3>
                    <div class="d-flex align-items-center justify-content-between small text-muted">
                        <span>จำนวนรายการ SKU:</span>
                        <span class="fw-bold"><?= number_format($total_skus) ?> รายการ</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 3: Stock Health Alerts -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 position-relative overflow-hidden">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-bold text-uppercase">สถานะความเสี่ยงสต็อก</span>
                        <div class="bg-danger text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="bi bi-exclamation-triangle fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-danger"><?= number_format($out_of_stock_count + $low_stock_count) ?> <span class="fs-6 fw-normal text-muted">รายการ</span></h3>
                    <div class="d-flex align-items-center gap-2 small">
                        <span class="badge bg-danger text-white"><?= $out_of_stock_count ?> หมดสต็อก</span>
                        <span class="badge bg-warning text-dark"><?= $low_stock_count ?> ใกล้หมด</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 4: Today's Movement Activity -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 position-relative overflow-hidden">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-bold text-uppercase">การเคลื่อนไหววันนี้</span>
                        <div class="bg-success text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="bi bi-activity fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1"><?= number_format($today_tx) ?> <span class="fs-6 fw-normal text-muted">รายการ</span></h3>
                    <div class="d-flex align-items-center justify-content-between small">
                        <span class="text-success fw-bold"><i class="bi bi-arrow-down-right me-1"></i>รับเข้า +<?= number_format($today_in) ?></span>
                        <span class="text-warning fw-bold"><i class="bi bi-arrow-up-right me-1"></i>เบิกจ่าย -<?= number_format($today_out) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. ANALYTICS CHARTS SECTION -->
    <div class="row g-4 mb-4">
        <!-- Stock Turnover Trend (7 Days) -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0"><i class="bi bi-graph-up-arrow text-primary me-2"></i>แนวโน้มการรับเข้า-เบิกจ่ายสินค้า (7 วันย้อนหลัง)</h5>
                        <p class="text-muted small mb-0">เปรียบเทียบปริมาณสินค้าเข้าและออกคลังเพื่อวิเคราะห์การหมุนเวียน</p>
                    </div>
                </div>
                <div class="card-body px-4 pb-4">
                    <div style="height: 280px; position: relative;">
                        <canvas id="movementChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Value Distribution -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-0"><i class="bi bi-pie-chart text-info me-2"></i>สัดส่วนมูลค่าตามหมวดหมู่</h5>
                    <p class="text-muted small mb-0">การกระจายตัวของต้นทุนสินค้าในคลัง</p>
                </div>
                <div class="card-body px-4 pb-4 d-flex align-items-center justify-content-center">
                    <div style="height: 240px; width: 100%; position: relative;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. ACTIONABLE TABLES SECTION -->
    <div class="row g-4 mb-4">
        
        <!-- Urgent Safety Stock Table -->
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <span class="p-2 bg-danger-light text-danger rounded-3"><i class="bi bi-exclamation-octagon fs-5"></i></span>
                        <div>
                            <h5 class="fw-bold mb-0">สินค้าวิกฤต / ต้องสั่งซื้อด่วน</h5>
                            <p class="text-muted small mb-0">รายการสินค้าที่มีสต็อกต่ำกว่าเกณฑ์ความปลอดภัย (Safety Stock)</p>
                        </div>
                    </div>
                    <a href="products.php" class="btn btn-sm btn-outline-secondary">ดูทั้งหมด</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">สินค้า</th>
                                    <th>หมวดหมู่</th>
                                    <th class="text-center">คงเหลือ</th>
                                    <th class="text-center">ขั้นต่ำ</th>
                                    <th class="text-end pe-4">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($critical_products)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            <i class="bi bi-check-circle-fill text-success me-1"></i> สต็อกสินค้าทุกรายการอยู่ในระดับปลอดภัย
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($critical_products as $item): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold"><?= htmlspecialchars($item['name']) ?></div>
                                                <span class="badge bg-light text-secondary border font-monospace"><?= htmlspecialchars($item['sku']) ?></span>
                                            </td>
                                            <td class="small text-muted"><?= htmlspecialchars($item['category_name'] ?? 'ทั่วไป') ?></td>
                                            <td class="text-center fw-bold">
                                                <?php if ($item['quantity'] == 0): ?>
                                                    <span class="badge bg-danger animate-pulse-danger">หมดสต็อก (0)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><?= number_format($item['quantity']) ?> ชิ้น</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center small text-muted"><?= number_format($item['min_threshold']) ?></td>
                                            <td class="text-end pe-4">
                                                <a href="stock_form.php?product_id=<?= $item['id'] ?>" class="btn btn-sm btn-primary py-1 px-2 fw-semibold">
                                                    <i class="bi bi-plus-lg me-1"></i> เติมสต็อก
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

        <!-- Real-time Movement Activity Feed -->
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <span class="p-2 bg-primary-light text-primary rounded-3"><i class="bi bi-clock-history fs-5"></i></span>
                        <div>
                            <h5 class="fw-bold mb-0">บันทึกความเคลื่อนไหวล่าสุด</h5>
                            <p class="text-muted small mb-0">รายการรับเข้า เบิกจ่าย และปรับยอดสต็อกจากเจ้าหน้าที่</p>
                        </div>
                    </div>
                    <a href="stock_logs.php" class="btn btn-sm btn-outline-secondary">ดูทั้งหมด</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">ประเภท</th>
                                    <th>สินค้า</th>
                                    <th class="text-center">จำนวน</th>
                                    <th>ผู้ทำรายการ</th>
                                    <th class="text-end pe-4">เวลา</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_movements)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">ยังไม่มีบันทึกกิจกรรมในระบบ</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_movements as $m): ?>
                                        <?php 
                                        $badge_map = [
                                            'IN' => ['bg-success', 'รับเข้า', '+'],
                                            'OUT' => ['bg-warning text-dark', 'เบิกออก', '-'],
                                            'ADJUST' => ['bg-info text-dark', 'ปรับยอด', ''],
                                            'DAMAGED' => ['bg-danger', 'ชำรุด', '-']
                                        ];
                                        $type_info = $badge_map[$m['type']] ?? ['bg-secondary', $m['type'], ''];
                                        ?>
                                        <tr>
                                            <td class="ps-4">
                                                <span class="badge <?= $type_info[0] ?>"><?= $type_info[1] ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-truncate" style="max-width: 180px;"><?= htmlspecialchars($m['product_name']) ?></div>
                                                <span class="small text-muted font-monospace"><?= htmlspecialchars($m['sku']) ?></span>
                                            </td>
                                            <td class="text-center fw-bold <?= in_array($m['type'], ['OUT', 'DAMAGED']) ? 'text-danger' : 'text-success' ?>">
                                                <?= $type_info[2] . number_format($m['quantity']) ?>
                                            </td>
                                            <td class="small text-muted"><?= htmlspecialchars($m['staff_name']) ?></td>
                                            <td class="text-end pe-4 small text-muted"><?= date('H:i', strtotime($m['created_at'])) ?> น.</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- 4. CATEGORY SUMMARY ROW -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-transparent border-0 pt-3 px-4 pb-2">
            <h6 class="fw-bold mb-0"><i class="bi bi-folder-symlink me-2 text-primary"></i>สรุปสถานะคลังสินค้าแยกตามหมวดหมู่</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">ชื่อหมวดหมู่</th>
                            <th class="text-center">จำนวน SKU</th>
                            <th class="text-center">ปริมาณรวมในคลัง</th>
                            <th class="text-end pe-4">มูลค่าต้นทุนรวม</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cat_analytics)): ?>
                            <tr><td colspan="4" class="text-center py-3 text-muted">ไม่มีข้อมูลหมวดหมู่</td></tr>
                        <?php else: ?>
                            <?php foreach ($cat_analytics as $cat): ?>
                                <tr>
                                    <td class="ps-4 fw-semibold">
                                        <i class="bi bi-tag-fill text-primary me-2"></i><?= htmlspecialchars($cat['category_name']) ?>
                                    </td>
                                    <td class="text-center"><span class="badge bg-secondary"><?= number_format($cat['sku_count']) ?> รายการ</span></td>
                                    <td class="text-center fw-bold"><?= number_format($cat['total_qty']) ?> ชิ้น</td>
                                    <td class="text-end pe-4 fw-bold text-primary">฿<?= number_format($cat['total_val'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- CHART.JS INITIALIZATION SCRIPT -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Stock Movement Trend Chart
    const ctxMovement = document.getElementById('movementChart').getContext('2d');
    new Chart(ctxMovement, {
        type: 'line',
        data: {
            labels: <?= $chart_labels ?>,
            datasets: [
                {
                    label: 'รับเข้า (IN)',
                    data: <?= $chart_in_data ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'เบิกจ่าย (OUT)',
                    data: <?= $chart_out_data ?>,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: { family: 'Prompt', size: 12 },
                        usePointStyle: true,
                        color: '#94a3b8'
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(15, 23, 42, 0.95)',
                    titleColor: '#e2e8f0',
                    bodyColor: '#94a3b8',
                    borderColor: 'rgba(6, 182, 212, 0.3)',
                    borderWidth: 1,
                    bodyFont: { family: 'Prompt' },
                    titleFont: { family: 'Prompt', weight: 'bold' }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { family: 'Prompt', size: 11 }, color: '#64748b' }
                },
                y: {
                    grid: { color: 'rgba(6, 182, 212, 0.08)' },
                    ticks: { font: { family: 'Prompt', size: 11 }, color: '#64748b' },
                    beginAtZero: true
                }
            }
        }
    });

    // 2. Category Distribution Chart
    const ctxCategory = document.getElementById('categoryChart').getContext('2d');
    new Chart(ctxCategory, {
        type: 'doughnut',
        data: {
            labels: <?= $cat_names ?>,
            datasets: [{
                data: <?= $cat_values ?>,
                backgroundColor: [
                    '#06b6d4',
                    '#8b5cf6',
                    '#10b981',
                    '#f59e0b',
                    '#f43f5e',
                    '#3b82f6'
                ],
                borderWidth: 2,
                borderColor: '#0f172a'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { family: 'Prompt', size: 11 },
                        boxWidth: 12,
                        color: '#94a3b8'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.95)',
                    titleColor: '#e2e8f0',
                    bodyColor: '#94a3b8',
                    borderColor: 'rgba(6, 182, 212, 0.3)',
                    borderWidth: 1
                }
            },
            cutout: '68%'
        }
    });
});
</script>

<?php require_once 'footer.php'; ?>