<?php
$page_title = 'โปรไฟล์ของฉัน - STOCKPRO';
require_once 'db.php';

// เพิ่มคอลัมน์โปรไฟล์ถ้ายังไม่มี
try {
    $need = [
        'bio'          => "ALTER TABLE users ADD COLUMN bio TEXT NULL AFTER fullname",
        'phone'        => "ALTER TABLE users ADD COLUMN phone VARCHAR(30) NULL AFTER bio",
        'avatar_color' => "ALTER TABLE users ADD COLUMN avatar_color VARCHAR(20) NULL DEFAULT '#06b6d4' AFTER phone",
        'avatar_path'  => "ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL AFTER avatar_color",
    ];
    foreach ($need as $col => $sql) {
        $exists = $pdo->query("SHOW COLUMNS FROM users LIKE " . $pdo->quote($col))->fetch();
        if (!$exists) {
            $pdo->exec($sql);
        }
    }
} catch (Exception $e) {
    // ข้ามถ้าไม่มีสิทธิ์ ALTER
}

require_once 'header.php';

$msg = '';
$error = '';
$user_id = (int)$_SESSION['user_id'];

/** ชนิดไฟล์ภาพที่อนุญาต */
$ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];
$ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$MAX_SIZE = 2 * 1024 * 1024; // 2 MB
$UPLOAD_DIR = __DIR__ . '/assets/avatars';

if (!is_dir($UPLOAD_DIR)) {
    @mkdir($UPLOAD_DIR, 0755, true);
}

/**
 * ลบไฟล์อวาตาร์เก่า (เฉพาะในโฟลเดอร์ avatars)
 */
function deleteAvatarFile(?string $path): void {
    if (!$path) return;
    $base = realpath(__DIR__ . '/assets/avatars');
    $full = realpath(__DIR__ . '/' . ltrim(str_replace(['\\', '..'], ['/', ''], $path), '/'));
    if ($base && $full && str_starts_with($full, $base) && is_file($full)) {
        @unlink($full);
    }
}

// บันทึกโปรไฟล์ / อัปโหลด / รหัสผ่าน
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'profile';

    // —— ลบรูปโปรไฟล์ ——
    if ($action === 'remove_avatar') {
        try {
            $stmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $old = $stmt->fetchColumn();
            deleteAvatarFile($old ?: null);
            $pdo->prepare("UPDATE users SET avatar_path = NULL WHERE id = ?")->execute([$user_id]);
            $msg = 'ลบรูปโปรไฟล์แล้ว';
        } catch (Exception $e) {
            $error = 'ไม่สามารถลบรูปได้: ' . $e->getMessage();
        }
    }

    // —— บันทึกข้อมูล + อัปโหลดรูป (ถ้ามี) ——
    if ($action === 'profile') {
        $fullname     = trim($_POST['fullname'] ?? '');
        $bio          = trim($_POST['bio'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $avatar_color = trim($_POST['avatar_color'] ?? '#06b6d4');
        $new_avatar_path = null;

        if ($fullname === '') {
            $error = 'กรุณากรอกชื่อ-นามสกุล';
        } elseif (mb_strlen($bio) > 500) {
            $error = 'ไบโอต้องไม่เกิน 500 ตัวอักษร';
        } else {
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $avatar_color)) {
                $avatar_color = '#06b6d4';
            }

            // ตรวจไฟล์อัปโหลด (ถ้าเลือกไฟล์)
            if (!empty($_FILES['avatar']['name']) && (int)$_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
                $f = $_FILES['avatar'];

                if ($f['error'] !== UPLOAD_ERR_OK) {
                    $error = 'อัปโหลดไม่สำเร็จ (รหัสข้อผิดพลาด: ' . $f['error'] . ')';
                } elseif ($f['size'] > $MAX_SIZE) {
                    $error = 'ขนาดไฟล์ต้องไม่เกิน 2 MB';
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime  = $finfo->file($f['tmp_name']) ?: '';
                    $ext   = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));

                    if (!isset($ALLOWED_MIME[$mime]) || !in_array($ext, $ALLOWED_EXT, true)) {
                        $error = 'รองรับเฉพาะไฟล์ภาพ: JPG, JPEG, PNG, GIF, WEBP';
                    } else {
                        // ยืนยันว่าเป็นภาพจริง
                        $imgInfo = @getimagesize($f['tmp_name']);
                        if ($imgInfo === false) {
                            $error = 'ไฟล์ที่อัปโหลดไม่ใช่รูปภาพที่ถูกต้อง';
                        } else {
                            $safeExt = $ALLOWED_MIME[$mime];
                            $filename = 'u' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $safeExt;
                            $dest = $UPLOAD_DIR . '/' . $filename;

                            if (!move_uploaded_file($f['tmp_name'], $dest)) {
                                $error = 'ไม่สามารถบันทึกไฟล์ได้ กรุณาตรวจสอบสิทธิ์โฟลเดอร์';
                            } else {
                                @chmod($dest, 0644);
                                $new_avatar_path = 'assets/avatars/' . $filename;
                            }
                        }
                    }
                }
            }

            if ($error === '') {
                try {
                    // ลบไฟล์เก่าถ้ามีรูปใหม่
                    if ($new_avatar_path !== null) {
                        $stmtOld = $pdo->prepare("SELECT avatar_path FROM users WHERE id = ?");
                        $stmtOld->execute([$user_id]);
                        deleteAvatarFile($stmtOld->fetchColumn() ?: null);

                        $stmt = $pdo->prepare("UPDATE users SET fullname = ?, bio = ?, phone = ?, avatar_color = ?, avatar_path = ? WHERE id = ?");
                        $stmt->execute([
                            $fullname,
                            $bio !== '' ? $bio : null,
                            $phone !== '' ? $phone : null,
                            $avatar_color,
                            $new_avatar_path,
                            $user_id
                        ]);
                        $msg = 'บันทึกโปรไฟล์และรูปภาพเรียบร้อยแล้ว';
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET fullname = ?, bio = ?, phone = ?, avatar_color = ? WHERE id = ?");
                        $stmt->execute([
                            $fullname,
                            $bio !== '' ? $bio : null,
                            $phone !== '' ? $phone : null,
                            $avatar_color,
                            $user_id
                        ]);
                        $msg = 'บันทึกโปรไฟล์เรียบร้อยแล้ว';
                    }
                    $_SESSION['fullname'] = $fullname;
                } catch (Exception $e) {
                    if ($new_avatar_path) {
                        deleteAvatarFile($new_avatar_path);
                    }
                    $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
                }
            }
        }
    }

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $newpass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($current === '' || $newpass === '' || $confirm === '') {
            $error = 'กรุณากรอกรหัสผ่านให้ครบทุกช่อง';
        } elseif (strlen($newpass) < 6) {
            $error = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
        } elseif ($newpass !== $confirm) {
            $error = 'รหัสผ่านใหม่และยืนยันไม่ตรงกัน';
        } else {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $row = $stmt->fetch();
            $ok = $row && (
                password_verify($current, $row['password']) ||
                $current === $row['password']
            );
            if (!$ok) {
                $error = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
            } else {
                $hash = password_hash($newpass, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $user_id]);
                $msg = 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว';
            }
        }
    }
}

// โหลดข้อมูลผู้ใช้
$stmt = $pdo->prepare("SELECT id, username, fullname, role, bio, phone, avatar_color, avatar_path, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo '<div class="container py-5"><div class="alert alert-danger">ไม่พบข้อมูลผู้ใช้</div></div>';
    require_once 'footer.php';
    exit;
}

$avatar_color = $user['avatar_color'] ?: '#06b6d4';
$initial = mb_strtoupper(mb_substr($user['fullname'] ?: $user['username'], 0, 1));
$role_label = ($user['role'] === 'admin') ? 'Admin / ผู้ดูแลระบบ' : 'Warehouse Staff';
$has_avatar = !empty($user['avatar_path']) && is_file(__DIR__ . '/' . $user['avatar_path']);
?>

<div class="container-fluid px-lg-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-person-gear text-primary me-2"></i>โปรไฟล์ของฉัน</h3>
            <p class="text-muted small mb-0">ตั้งค่าข้อมูลส่วนตัว รูปโปรไฟล์ ไบโอ และรหัสผ่าน</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>กลับแดชบอร์ด
        </a>
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

    <div class="row g-4">
        <!-- สรุปโปรไฟล์ -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body text-center p-4">
                    <?php if ($has_avatar): ?>
                        <img src="<?= htmlspecialchars($user['avatar_path']) ?>?v=<?= filemtime(__DIR__ . '/' . $user['avatar_path']) ?>"
                             alt="Avatar"
                             class="mx-auto mb-3 rounded-circle object-fit-cover"
                             style="width:96px;height:96px;object-fit:cover;box-shadow:0 0 24px <?= htmlspecialchars($avatar_color) ?>55;border:3px solid <?= htmlspecialchars($avatar_color) ?>;">
                    <?php else: ?>
                        <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle fw-bold text-white"
                             style="width:96px;height:96px;font-size:2.4rem;background:<?= htmlspecialchars($avatar_color) ?>;box-shadow:0 0 24px <?= htmlspecialchars($avatar_color) ?>55;">
                            <?= htmlspecialchars($initial) ?>
                        </div>
                    <?php endif; ?>
                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($user['fullname']) ?></h4>
                    <p class="text-muted small mb-2">@<?= htmlspecialchars($user['username']) ?></p>
                    <span class="badge <?= $user['role'] === 'admin' ? 'bg-danger' : 'bg-info text-dark' ?> mb-3">
                        <?= htmlspecialchars($role_label) ?>
                    </span>
                    <?php if (!empty($user['bio'])): ?>
                        <p class="small text-secondary mb-3 px-2" style="line-height:1.55;">
                            <?= nl2br(htmlspecialchars($user['bio'])) ?>
                        </p>
                    <?php else: ?>
                        <p class="small text-muted mb-3">ยังไม่ได้ตั้งไบโอ</p>
                    <?php endif; ?>
                    <?php if (!empty($user['phone'])): ?>
                        <p class="small mb-1"><i class="bi bi-telephone me-1 text-primary"></i><?= htmlspecialchars($user['phone']) ?></p>
                    <?php endif; ?>
                    <p class="small text-muted mb-0">
                        <i class="bi bi-calendar3 me-1"></i>
                        สมาชิกตั้งแต่ <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- ฟอร์มแก้ไข -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header border-0 bg-transparent pt-3 px-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-pencil-square text-primary me-2"></i>แก้ไขโปรไฟล์</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="profile">
                        <div class="row g-3">
                            <!-- อัปโหลดรูป -->
                            <div class="col-12">
                                <label class="form-label small fw-semibold">รูปโปรไฟล์</label>
                                <div class="d-flex flex-wrap align-items-center gap-3 p-3 rounded-3" style="background:rgba(30,41,59,0.5);border:1px solid rgba(6,182,212,0.2);">
                                    <div>
                                        <?php if ($has_avatar): ?>
                                            <img id="avatarPreview" src="<?= htmlspecialchars($user['avatar_path']) ?>?v=<?= filemtime(__DIR__ . '/' . $user['avatar_path']) ?>"
                                                 alt="Preview" class="rounded-circle" style="width:64px;height:64px;object-fit:cover;">
                                        <?php else: ?>
                                            <div id="avatarPreview" class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                                 style="width:64px;height:64px;background:<?= htmlspecialchars($avatar_color) ?>;font-size:1.4rem;">
                                                <?= htmlspecialchars($initial) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1" style="min-width:200px;">
                                        <input type="file" name="avatar" id="avatarInput" class="form-control form-control-sm"
                                               accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp">
                                        <div class="form-text mt-1">
                                            รองรับ <strong>JPG, JPEG, PNG, GIF, WEBP</strong> · ขนาดไม่เกิน <strong>2 MB</strong>
                                        </div>
                                    </div>
                                    <?php if ($has_avatar): ?>
                                        <button type="submit" name="action" value="remove_avatar" class="btn btn-outline-danger btn-sm"
                                                onclick="return confirm('ลบรูปโปรไฟล์นี้หรือไม่?')">
                                            <i class="bi bi-trash me-1"></i>ลบรูป
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">ชื่อผู้ใช้ (Username)</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                                <div class="form-text">ไม่สามารถเปลี่ยน username ได้</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname']) ?>" required maxlength="100">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">เบอร์โทรศัพท์</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="08x-xxx-xxxx" maxlength="30">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">สีอวาตาร์ (เมื่อไม่มีรูป)</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="color" name="avatar_color" class="form-control form-control-color" value="<?= htmlspecialchars($avatar_color) ?>" title="เลือกสี">
                                    <span class="small text-muted">ใช้เมื่อยังไม่ได้อัปโหลดรูป</span>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">ไบโอ / แนะนำตัว</label>
                                <textarea name="bio" class="form-control" rows="4" maxlength="500" placeholder="เขียนสั้น ๆ เกี่ยวกับตัวเอง หน้าที่ หรือทีมงาน..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                                <div class="form-text">สูงสุด 500 ตัวอักษร</div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary fw-semibold">
                                    <i class="bi bi-save me-1"></i>บันทึกโปรไฟล์
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header border-0 bg-transparent pt-3 px-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-key text-warning me-2"></i>เปลี่ยนรหัสผ่าน</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    <form method="POST">
                        <input type="hidden" name="action" value="password">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">รหัสผ่านปัจจุบัน</label>
                                <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">รหัสผ่านใหม่</label>
                                <input type="password" name="new_password" class="form-control" required minlength="6" autocomplete="new-password">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">ยืนยันรหัสผ่านใหม่</label>
                                <input type="password" name="confirm_password" class="form-control" required minlength="6" autocomplete="new-password">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-outline-warning fw-semibold">
                                    <i class="bi bi-shield-lock me-1"></i>เปลี่ยนรหัสผ่าน
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('avatarInput')?.addEventListener('change', function (e) {
    const file = e.target.files && e.target.files[0];
    if (!file) return;
    const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowed.includes(file.type)) {
        alert('รองรับเฉพาะ JPG, JPEG, PNG, GIF, WEBP');
        e.target.value = '';
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        alert('ขนาดไฟล์ต้องไม่เกิน 2 MB');
        e.target.value = '';
        return;
    }
    const url = URL.createObjectURL(file);
    const preview = document.getElementById('avatarPreview');
    if (preview.tagName === 'IMG') {
        preview.src = url;
    } else {
        const img = document.createElement('img');
        img.id = 'avatarPreview';
        img.src = url;
        img.alt = 'Preview';
        img.className = 'rounded-circle';
        img.style.cssText = 'width:64px;height:64px;object-fit:cover;';
        preview.replaceWith(img);
    }
});
</script>

<?php require_once 'footer.php'; ?>
