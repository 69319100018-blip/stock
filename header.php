<?php
require_once 'auth_check.php';
checkAuth();

$current_page = basename($_SERVER['PHP_SELF']);
$is_admin = ($_SESSION['role'] === 'admin');

// ดึงจำนวนสินค้าที่ต่ำกว่าเกณฑ์สำหรับแจ้งเตือน
$low_stock_badge = 0;
if (isset($pdo)) {
    $low_stock_badge = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE quantity <= min_threshold")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'STOCKPRO - Enterprise WMS' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-xl navbar-dark sticky-top">
    <div class="container-fluid px-lg-4">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="dashboard.php">
            <i class="bi bi-box-seam fs-3"></i>
            <span class="fs-4">STOCK<span class="text-gradient">PRO</span></span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-xl-0">
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>" href="dashboard.php">
                        <i class="bi bi-speedometer2 me-1"></i> แดชบอร์ด
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link position-relative <?= in_array($current_page, ['products.php', 'product_form.php']) ? 'active' : '' ?>" href="products.php">
                        <i class="bi bi-boxes me-1"></i> สินค้าคงคลัง
                        <?php if ($low_stock_badge > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= $low_stock_badge ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page === 'stock_form.php') ? 'active' : '' ?>" href="stock_form.php">
                        <i class="bi bi-arrow-left-right me-1"></i> รับเข้า-เบิกจ่าย
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page === 'stock_logs.php') ? 'active' : '' ?>" href="stock_logs.php">
                        <i class="bi bi-clock-history me-1"></i> ประวัติการเคลื่อนไหว
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page === 'shifts.php') ? 'active' : '' ?>" href="shifts.php">
                        <i class="bi bi-person-badge me-1"></i> เข้าเวร-ออกเวร
                    </a>
                </li>

                <!-- WMS Operations Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_page, ['purchase_orders.php', 'requisitions.php', 'barcodes.php']) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="true">
                        <i class="bi bi-gear-wide-connected me-1"></i> ปฏิบัติการ WMS
                    </a>
                    <ul class="dropdown-menu shadow">
                        <li>
                            <a class="dropdown-item py-2" href="purchase_orders.php">
                                <i class="bi bi-receipt me-2 text-info"></i> ใบสั่งซื้อสินค้า (PO)
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="requisitions.php">
                                <i class="bi bi-box-arrow-up-right me-2 text-warning"></i> ขออนุมัติเบิกสินค้า
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="barcodes.php">
                                <i class="bi bi-upc-scan me-2 text-info"></i> พิมพ์บาร์โค้ด & สแกนเนอร์
                            </a>
                        </li>
                    </ul>
                </li>

                <?php if ($is_admin): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_page, ['users.php', 'user_edit.php', 'categories.php', 'suppliers.php']) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="true">
                        <i class="bi bi-shield-lock me-1"></i> ข้อมูลระบบ
                    </a>
                    <ul class="dropdown-menu shadow">
                        <li>
                            <a class="dropdown-item py-2" href="users.php">
                                <i class="bi bi-people me-2"></i> จัดการผู้ใช้งานและสิทธิ์
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="categories.php">
                                <i class="bi bi-tags me-2"></i> จัดการหมวดหมู่สินค้า
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="suppliers.php">
                                <i class="bi bi-truck me-2"></i> จัดการซัพพลายเออร์
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>

            <?php
            // อวาตาร์จากโปรไฟล์ (รูป / สี + ตัวอักษรแรก)
            $_nav_avatar = '#06b6d4';
            $_nav_initial = mb_strtoupper(mb_substr($_SESSION['fullname'] ?? 'U', 0, 1));
            $_nav_photo = null;
            if (isset($pdo) && !empty($_SESSION['user_id'])) {
                try {
                    $av = $pdo->prepare("SELECT avatar_color, avatar_path, fullname FROM users WHERE id = ?");
                    $av->execute([$_SESSION['user_id']]);
                    $avRow = $av->fetch();
                    if ($avRow) {
                        if (!empty($avRow['avatar_color'])) {
                            $_nav_avatar = $avRow['avatar_color'];
                        }
                        if (!empty($avRow['fullname'])) {
                            $_nav_initial = mb_strtoupper(mb_substr($avRow['fullname'], 0, 1));
                        }
                        if (!empty($avRow['avatar_path']) && is_file(__DIR__ . '/' . $avRow['avatar_path'])) {
                            $_nav_photo = $avRow['avatar_path'];
                        }
                    }
                } catch (Exception $e) { /* ข้ามถ้ายังไม่มีคอลัมน์ */ }
            }
            ?>
            <ul class="navbar-nav align-items-center">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php if ($_nav_photo): ?>
                            <img src="<?= htmlspecialchars($_nav_photo) ?>" alt=""
                                 class="rounded-circle"
                                 style="width:34px;height:34px;object-fit:cover;box-shadow:0 0 10px <?= htmlspecialchars($_nav_avatar) ?>66;border:2px solid <?= htmlspecialchars($_nav_avatar) ?>;">
                        <?php else: ?>
                            <div class="text-white rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                 style="width:34px;height:34px;font-size:0.9rem;background:<?= htmlspecialchars($_nav_avatar) ?>;box-shadow:0 0 10px <?= htmlspecialchars($_nav_avatar) ?>66;">
                                <?= htmlspecialchars($_nav_initial) ?>
                            </div>
                        <?php endif; ?>
                        <span class="small fw-semibold"><?= htmlspecialchars($_SESSION['fullname']) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow mt-2">
                        <li class="px-3 py-2 border-bottom border-secondary">
                            <span class="text-muted small d-block">ระดับสิทธิ์การใช้งาน</span>
                            <span class="badge <?= $is_admin ? 'bg-danger' : 'bg-info text-dark' ?>">
                                <?= $is_admin ? 'Admin / Manager' : 'Warehouse Staff' ?>
                            </span>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 <?= ($current_page === 'profile.php') ? 'active' : '' ?>" href="profile.php">
                                <i class="bi bi-person-gear me-2 text-primary"></i> โปรไฟล์ของฉัน
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item py-2 text-danger" href="logout.php" onclick="return confirm('ยืนยันออกจากระบบหรือไม่?')">
                                <i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="flex-grow-1">