<?php
$page_title = 'ระบบเข้าเวร-ออกเวรพนักงาน (Shift Attendance) - StockPro';
require_once 'header.php';
require_once 'db.php';

$user_id = $_SESSION['user_id'];

// 1. ตรวจสอบสถานะการเข้าเวรปัจจุบันของผู้ใช้ที่ล็อกอินอยู่
$stmt_curr = $pdo->prepare("SELECT * FROM duty_shifts WHERE user_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
$stmt_curr->execute([$user_id]);
$current_shift = $stmt_curr->fetch();
$is_on_duty = !empty($current_shift);

// 2. คำนวณภาพรวมสถิติการเข้าเวรประจำวันและเดือนนี้
$today_active_count = (int)$pdo->query("SELECT COUNT(*) FROM duty_shifts WHERE status = 'active'")->fetchColumn();
$today_shifts_total = (int)$pdo->query("SELECT COUNT(*) FROM duty_shifts WHERE DATE(clock_in) = CURDATE()")->fetchColumn();

$stmt_hours = $pdo->prepare("
    SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, clock_in, clock_out)), 0) 
    FROM duty_shifts 
    WHERE status = 'completed' AND MONTH(clock_in) = MONTH(CURDATE()) AND YEAR(clock_in) = YEAR(CURDATE())
");
$stmt_hours->execute();
$total_minutes_month = (int)$stmt_hours->fetchColumn();
$total_hours_month = round($total_minutes_month / 60, 1);

// 3. ดึงรายชื่อพนักงานที่กำลังอยู่ในเวรขณะนี้ (Live On-Duty Roster)
$active_staff = $pdo->query("
    SELECT ds.*, u.fullname, u.username, u.role
    FROM duty_shifts ds
    JOIN users u ON ds.user_id = u.id
    WHERE ds.status = 'active'
    ORDER BY ds.clock_in ASC
")->fetchAll();

// 4. รับค่าตัวกรองประวัติการเข้าเวรย้อนหลัง
$filter_user = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
$filter_shift = $_GET['shift_type'] ?? '';
$filter_date  = $_GET['date'] ?? '';

$where = ["1=1"];
$params = [];

if ($filter_user) {
    $where[] = "ds.user_id = ?";
    $params[] = $filter_user;
}
if (!empty($filter_shift)) {
    $where[] = "ds.shift_type = ?";
    $params[] = $filter_shift;
}
if (!empty($filter_date)) {
    $where[] = "DATE(ds.clock_in) = ?";
    $params[] = $filter_date;
}

$where_sql = implode(" AND ", $where);

$sql_logs = "
    SELECT ds.*, u.fullname, u.username, u.role 
    FROM duty_shifts ds
    JOIN users u ON ds.user_id = u.id
    WHERE {$where_sql}
    ORDER BY ds.id DESC 
    LIMIT 30
";
$stmt_logs = $pdo->prepare($sql_logs);
$stmt_logs->execute($params);
$shift_logs = $stmt_logs->fetchAll();

$all_users = $pdo->query("SELECT id, fullname, username FROM users ORDER BY fullname ASC")->fetchAll();

$msg = $_SESSION['msg'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['msg'], $_SESSION['error']);
?>

<div class="container-fluid px-4 py-4">

    <!-- Header Section -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary rounded-pill px-3 py-1 fs-7"><i class="bi bi-person-check me-1"></i> Attendance System</span>
                <span class="text-muted small"><i class="bi bi-clock me-1"></i>วันที่ปัจจุบัน: <?= date('d/m/Y') ?></span>
            </div>
            <h3 class="fw-bold mb-0 text-dark"><i class="bi bi-calendar2-check text-primary me-2"></i>บันทึกเวลาเข้าเวร-ออกเวรพนักงาน</h3>
            <p class="text-muted small mb-0">ระบบลงเวลาการปฏิบัติงานของเจ้าหน้าที่และผู้จัดการคลังสินค้า</p>
        </div>
        <div>
            <button onclick="exportShiftCSV()" class="btn btn-outline-success fw-semibold shadow-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> ส่งออกรายงานประวัติ (CSV)
            </button>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5 align-middle"></i><?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5 align-middle"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">พนักงานในเวรขณะนี้</span>
                        <h3 class="fw-bold mb-0 mt-1 text-primary"><?= number_format($today_active_count) ?> <span class="fs-6 text-muted fw-normal">คน</span></h3>
                    </div>
                    <div class="bg-primary-light text-primary rounded-circle p-3">
                        <i class="bi bi-people-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">บันทึกเข้าเวรวันนี้</span>
                        <h3 class="fw-bold mb-0 mt-1"><?= number_format($today_shifts_total) ?> <span class="fs-6 text-muted fw-normal">ครั้ง</span></h3>
                    </div>
                    <div class="bg-info-light text-info rounded-circle p-3">
                        <i class="bi bi-clock-history fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">เวลารวมในคลัง (เดือนนี้)</span>
                        <h3 class="fw-bold mb-0 mt-1 text-success"><?= number_format($total_hours_month, 1) ?> <span class="fs-6 text-muted fw-normal">ชั่วโมง</span></h3>
                    </div>
                    <div class="bg-success-light text-success rounded-circle p-3">
                        <i class="bi bi-stopwatch fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">สถานะของคุณ</span>
                        <h4 class="fw-bold mb-0 mt-1">
                            <?php if ($is_on_duty): ?>
                                <span class="badge bg-success"><i class="bi bi-dot animate-pulse-success"></i> กำลังอยู่ในเวร</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">นอกเวลาปฏิบัติงาน</span>
                            <?php endif; ?>
                        </h4>
                    </div>
                    <div class="<?= $is_on_duty ? 'bg-success-light text-success' : 'bg-secondary-light text-secondary' ?> rounded-circle p-3">
                        <i class="bi bi-person-badge fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN ACTION SECTION & LIVE ROSTER -->
    <div class="row g-4 mb-4">
        
        <!-- Shift Action Terminal Card -->
        <div class="col-lg-5 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-cpu text-primary me-2"></i>Terminal ลงเวลาปฏิบัติงาน</h5>
                    <p class="text-muted small mb-0">สวัสดีคุณ <strong><?= htmlspecialchars($_SESSION['fullname']) ?></strong></p>
                </div>
                <div class="card-body p-4">
                    <?php if ($is_on_duty): ?>
                        <!-- Clock-Out Interface -->
                        <?php
                        $start_time = strtotime($current_shift['clock_in']);
                        $elapsed_sec = time() - $start_time;
                        $hours_cnt = floor($elapsed_sec / 3600);
                        $mins_cnt  = floor(($elapsed_sec % 3600) / 60);
                        ?>
                        <div class="text-center p-3 rounded-4 bg-success-light border border-success-subtle mb-4">
                            <span class="badge bg-success px-3 py-1 mb-2">กำลังอยู่ในเวรปฏิบัติงาน</span>
                            <h6 class="fw-bold text-dark mb-1">กะการทำงาน: <?= htmlspecialchars($current_shift['shift_type']) ?></h6>
                            <div class="small text-muted mb-2"><i class="bi bi-play-circle me-1"></i>ลงชื่อเข้าเวรเมื่อ: <strong><?= date('d/m/Y H:i', $start_time) ?> น.</strong></div>
                            <div class="fs-7 text-success fw-bold"><i class="bi bi-hourglass-split me-1"></i>ปฏิบัติงานมาแล้วประมาณ <?= $hours_cnt ?> ชม. <?= $mins_cnt ?> นาที</div>
                        </div>

                        <form action="shift_action.php" method="POST">
                            <input type="hidden" name="action" value="clock_out">
                            <input type="hidden" name="shift_id" value="<?= $current_shift['id'] ?>">

                            <div class="mb-3">
                                <label class="form-label fw-bold">บันทึกสรุปงานก่อนออกเวร (ถ้ามี)</label>
                                <textarea name="note" class="form-control" rows="3" placeholder="ระบุสิ่งที่ดำเนินการเสร็จสิ้น หรือสิ่งที่ต้องฝากต่อกะถัดไป..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-danger w-100 py-3 rounded-4 fw-bold shadow-sm" onclick="return confirm('ยืนยันลงชื่อออกเวรหรือไม่?')">
                                <i class="bi bi-box-arrow-right me-2 fs-5 align-middle"></i> ลงชื่อออกเวร (Clock Out)
                            </button>
                        </form>
                    <?php else: ?>
                        <!-- Clock-In Interface -->
                        <form action="shift_action.php" method="POST">
                            <input type="hidden" name="action" value="clock_in">

                            <div class="mb-3">
                                <label class="form-label fw-bold">1. เลือกรอบกะการทำงาน <span class="text-danger">*</span></label>
                                <select name="shift_type" class="form-select form-select-lg" required>
                                    <option value="กะปกติ (08:00 - 17:00)" selected>กะปกติ (08:00 - 17:00 น.)</option>
                                    <option value="กะเช้า (06:00 - 14:00)">กะเช้า (06:00 - 14:00 น.)</option>
                                    <option value="กะบ่าย (14:00 - 22:00)">กะบ่าย (14:00 - 22:00 น.)</option>
                                    <option value="กะดึก (22:00 - 06:00)">กะดึก (22:00 - 06:00 น.)</option>
                                    <option value="กะพิเศษ/ล่วงเวลา (OT)">กะพิเศษ / ล่วงเวลา (OT)</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">2. หมายเหตุการเข้าเวร</label>
                                <input type="text" name="note" class="form-control" placeholder="เช่น รับเวรต่อจากทีม A, ปฏิบัติงานคลังสินค้าหลัก">
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-3 rounded-4 fs-6 fw-bold shadow-glow">
                                <i class="bi bi-box-arrow-in-right me-2 fs-5 align-middle"></i> ลงชื่อเข้าเวร (Clock In)
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Live On-Duty Staff Roster Table -->
        <div class="col-lg-7 col-xl-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-person-workspace text-success me-2"></i>พนักงานที่กำลังปฏิบัติงานในขณะนี้ (Live Roster)</h5>
                        <p class="text-muted small mb-0">รายชื่อเจ้าหน้าที่ที่กำลังลงเวลาอยู่ในเวรคลังสินค้า</p>
                    </div>
                    <span class="badge bg-success-light text-success fw-bold px-3 py-2"><i class="bi bi-circle-fill me-1 fs-8"></i> <?= count($active_staff) ?> คนในระบบ</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">ชื่อ-นามสกุล / สิทธิ์</th>
                                    <th>รอบกะ</th>
                                    <th>เวลาเริ่มเข้าเวร</th>
                                    <th>ระยะเวลา</th>
                                    <th class="pe-4">บันทึกเข้าเวร</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($active_staff)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="bi bi-moon-stars fs-1 d-block mb-2 text-muted opacity-50"></i>
                                            ยังไม่มีพนักงานลงชื่อเข้าเวรในขณะนี้
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($active_staff as $st): ?>
                                        <?php
                                        $duration_min = round((time() - strtotime($st['clock_in'])) / 60);
                                        $d_hours = floor($duration_min / 60);
                                        $d_mins = $duration_min % 60;
                                        ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                                        <?= mb_substr($st['fullname'], 0, 1) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-dark mb-0"><?= htmlspecialchars($st['fullname']) ?></div>
                                                        <span class="badge <?= $st['role'] === 'admin' ? 'bg-danger' : 'bg-info text-dark' ?> fs-8"><?= $st['role'] === 'admin' ? 'Manager' : 'Staff' ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($st['shift_type']) ?></span></td>
                                            <td class="font-monospace small">
                                                <i class="bi bi-clock text-primary me-1"></i><?= date('H:i', strtotime($st['clock_in'])) ?> น.
                                                <div class="fs-8 text-muted"><?= date('d/m/Y', strtotime($st['clock_in'])) ?></div>
                                            </td>
                                            <td class="fw-bold text-success small">
                                                <?= $d_hours ?> ชม. <?= $d_mins ?> นาที
                                            </td>
                                            <td class="pe-4 small text-muted text-truncate" style="max-width: 180px;">
                                                <?= htmlspecialchars($st['note'] ?: '-') ?>
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

    </div>

    <!-- SHIFT AUDIT LOG & FILTERS -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select name="user_id" class="form-select">
                        <option value="">-- เลือกพนักงานทั้งหมด --</option>
                        <?php foreach ($all_users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $filter_user == $u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['fullname']) ?> (@<?= htmlspecialchars($u['username']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="shift_type" class="form-select">
                        <option value="">-- รอบกะทั้งหมด --</option>
                        <option value="กะปกติ (08:00 - 17:00)" <?= $filter_shift === 'กะปกติ (08:00 - 17:00)' ? 'selected' : '' ?>>กะปกติ</option>
                        <option value="กะเช้า (06:00 - 14:00)" <?= $filter_shift === 'กะเช้า (06:00 - 14:00)' ? 'selected' : '' ?>>กะเช้า</option>
                        <option value="กะบ่าย (14:00 - 22:00)" <?= $filter_shift === 'กะบ่าย (14:00 - 22:00)' ? 'selected' : '' ?>>กะบ่าย</option>
                        <option value="กะดึก (22:00 - 06:00)" <?= $filter_shift === 'กะดึก (22:00 - 06:00)' ? 'selected' : '' ?>>กะดึก</option>
                        <option value="กะพิเศษ/ล่วงเวลา (OT)" <?= $filter_shift === 'กะพิเศษ/ล่วงเวลา (OT)' ? 'selected' : '' ?>>กะพิเศษ / OT</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary fw-semibold w-100"><i class="bi bi-filter me-1"></i> ค้นหาประวัติ</button>
                    <?php if ($filter_user || $filter_shift || $filter_date): ?>
                        <a href="shifts.php" class="btn btn-outline-secondary" title="ล้างตัวกรอง"><i class="bi bi-x-circle"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Shift History Table -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-journal-text text-primary me-2"></i>รายงานประวัติการปฏิบัติงาน (Shift Log History)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="shiftLogTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">พนักงาน</th>
                            <th>รอบกะ</th>
                            <th>เวลาเข้าเวร</th>
                            <th>เวลาออกเวร</th>
                            <th class="text-center">เวลารวม (ชั่วโมง)</th>
                            <th class="pe-4">บันทึกสรุป</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($shift_logs)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted opacity-50"></i>
                                    ไม่พบประวัติการเข้าเวรตรงตามเงื่อนไขที่ค้นหา
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($shift_logs as $log): ?>
                                <?php
                                $c_in = strtotime($log['clock_in']);
                                $c_out = $log['clock_out'] ? strtotime($log['clock_out']) : null;
                                $diff_mins = $c_out ? round(($c_out - $c_in) / 60) : 0;
                                $hrs = floor($diff_mins / 60);
                                $mns = $diff_mins % 60;
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($log['fullname']) ?></div>
                                        <span class="small text-muted font-monospace">@<?= htmlspecialchars($log['username']) ?></span>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($log['shift_type']) ?></span></td>
                                    <td class="small text-muted">
                                        <?= date('d/m/Y', $c_in) ?>
                                        <div class="fw-semibold text-dark"><?= date('H:i:s', $c_in) ?> น.</div>
                                    </td>
                                    <td class="small text-muted">
                                        <?php if ($c_out): ?>
                                            <?= date('d/m/Y', $c_out) ?>
                                            <div class="fw-semibold text-dark"><?= date('H:i:s', $c_out) ?> น.</div>
                                        <?php else: ?>
                                            <span class="badge bg-success">กำลังอยู่ในเวร</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center fw-bold">
                                        <?php if ($c_out): ?>
                                            <span class="text-primary"><?= $hrs ?> ชม. <?= $mns ?> นาที</span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 small text-muted"><?= htmlspecialchars($log['note'] ?: '-') ?></td>
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
function exportShiftCSV() {
    const table = document.getElementById("shiftLogTable");
    let rows = [];
    
    for (let i = 0; i < table.rows.length; i++) {
        let row = [], cols = table.rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length; j++) {
            row.push('"' + cols[j].innerText.replace(/"/g, '""').replace(/\n/g, ' ').trim() + '"');
        }
        rows.push(row.join(","));
    }
    
    const csvContent = "\uFEFF" + rows.join("\n");
    const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    
    const link = document.createElement("a");
    link.setAttribute("href", url);
    link.setAttribute("download", "StockPro_Shift_Attendance_Report_" + new Date().toISOString().slice(0,10) + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php require_once 'footer.php'; ?>