<?php
session_start();
require_once 'db.php';

$error = '';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password, fullname, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && ($password === $user['password'] || password_verify($password, $user['password']))) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role']     = $user['role'];

            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ — STOCKPRO WMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;800&family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --cyan: #22d3ee;
            --cyan-dim: rgba(34, 211, 238, 0.15);
            --violet: #a78bfa;
            --violet-dim: rgba(167, 139, 250, 0.12);
            --bg-deep: #020617;
            --glass: rgba(15, 23, 42, 0.72);
            --border-glow: rgba(34, 211, 238, 0.35);
        }

        * { box-sizing: border-box; }
        html, body {
            height: 100%;
            margin: 0;
            font-family: 'Noto Sans Thai', 'Segoe UI', system-ui, sans-serif;
            background: var(--bg-deep);
        }

        .login-page {
            min-height: 100vh;
            width: 100%;
            position: relative;
            display: flex;
            align-items: center;
            overflow: hidden;
        }

        /* วิดีโอพื้นหลัง */
        .login-bg-video {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
            z-index: 0;
            pointer-events: none;
            filter: saturate(1.05) contrast(1.05);
        }

        /* ชั้นมืด + vignette โทน cyber */
        .login-overlay {
            position: absolute;
            inset: 0;
            z-index: 1;
            background:
                radial-gradient(ellipse 80% 70% at 20% 50%, rgba(6, 182, 212, 0.12) 0%, transparent 55%),
                radial-gradient(ellipse 60% 50% at 85% 40%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
                linear-gradient(105deg, rgba(2, 6, 23, 0.78) 0%, rgba(2, 6, 23, 0.45) 42%, rgba(2, 6, 23, 0.72) 100%);
            pointer-events: none;
        }

        /* เส้น grid บาง ๆ */
        .login-grid {
            position: absolute;
            inset: 0;
            z-index: 1;
            background-image:
                linear-gradient(rgba(34, 211, 238, 0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(34, 211, 238, 0.04) 1px, transparent 1px);
            background-size: 56px 56px;
            pointer-events: none;
            mask-image: radial-gradient(ellipse 90% 80% at 50% 50%, black 20%, transparent 75%);
            -webkit-mask-image: radial-gradient(ellipse 90% 80% at 50% 50%, black 20%, transparent 75%);
        }

        /* scan line เบา ๆ */
        .scanline {
            position: absolute;
            left: 0;
            right: 0;
            height: 2px;
            z-index: 2;
            background: linear-gradient(90deg, transparent, rgba(34, 211, 238, 0.45), transparent);
            box-shadow: 0 0 18px rgba(34, 211, 238, 0.35);
            animation: scan 7s linear infinite;
            pointer-events: none;
            opacity: 0.55;
        }
        @keyframes scan {
            0%   { top: -2%; }
            100% { top: 102%; }
        }

        .login-inner {
            position: relative;
            z-index: 3;
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 2.5rem;
        }

        /* —— Brand ซ้าย —— */
        .login-brand {
            flex: 1 1 340px;
            color: #fff;
            max-width: 540px;
        }

        .brand-logo {
            display: inline-flex;
            align-items: center;
            gap: 0.85rem;
            margin-bottom: 1.4rem;
        }
        .brand-mark {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: linear-gradient(145deg, rgba(34, 211, 238, 0.2), rgba(167, 139, 250, 0.15));
            border: 1px solid rgba(34, 211, 238, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 28px rgba(34, 211, 238, 0.25), inset 0 0 12px rgba(34, 211, 238, 0.08);
            position: relative;
        }
        .brand-mark i {
            font-size: 1.55rem;
            color: var(--cyan);
            filter: drop-shadow(0 0 6px rgba(34, 211, 238, 0.6));
        }
        .brand-mark::after {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 16px;
            border: 1px solid rgba(34, 211, 238, 0.15);
            animation: pulse-ring 2.8s ease-out infinite;
        }
        @keyframes pulse-ring {
            0%   { opacity: 0.7; transform: scale(1); }
            100% { opacity: 0; transform: scale(1.18); }
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--cyan);
            background: var(--cyan-dim);
            border: 1px solid rgba(34, 211, 238, 0.3);
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            margin-bottom: 1rem;
        }
        .status-pill .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #34d399;
            box-shadow: 0 0 8px #34d399;
            animation: blink 1.6s ease-in-out infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.35; }
        }

        .login-brand h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(2.4rem, 5.5vw, 3.6rem);
            font-weight: 800;
            margin: 0 0 0.35rem;
            line-height: 1.1;
            letter-spacing: 0.02em;
            text-shadow: 0 2px 30px rgba(0,0,0,0.5);
        }
        .login-brand h1 span {
            background: linear-gradient(135deg, #22d3ee 0%, #67e8f9 40%, #a78bfa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .login-brand .tagline {
            font-size: 0.95rem;
            color: rgba(148, 163, 184, 0.95);
            margin: 0 0 1.35rem;
            letter-spacing: 0.04em;
        }
        .login-brand .desc {
            font-size: 1.05rem;
            color: rgba(226, 232, 240, 0.9);
            margin: 0 0 1.5rem;
            line-height: 1.65;
            text-shadow: 0 1px 10px rgba(0,0,0,0.35);
        }

        .feature-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.55rem;
        }
        .feature-chips span {
            font-size: 0.8rem;
            font-weight: 500;
            color: rgba(226, 232, 240, 0.9);
            background: rgba(15, 23, 42, 0.55);
            border: 1px solid rgba(34, 211, 238, 0.22);
            padding: 0.4rem 0.75rem;
            border-radius: 8px;
            backdrop-filter: blur(8px);
        }
        .feature-chips span i {
            color: var(--cyan);
            margin-right: 0.3rem;
            font-size: 0.85rem;
        }

        /* —— การ์ดฟอร์ม ขวา (glass cyber) —— */
        .login-card {
            flex: 0 1 400px;
            background: var(--glass);
            backdrop-filter: blur(22px) saturate(1.2);
            -webkit-backdrop-filter: blur(22px) saturate(1.2);
            border-radius: 20px;
            padding: 2rem 1.85rem 1.75rem;
            border: 1px solid var(--border-glow);
            box-shadow:
                0 0 0 1px rgba(167, 139, 250, 0.08),
                0 25px 60px rgba(0, 0, 0, 0.45),
                0 0 40px rgba(34, 211, 238, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 0.06);
            position: relative;
            overflow: hidden;
        }
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--cyan), var(--violet), transparent);
            opacity: 0.85;
        }

        .card-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.4rem;
        }
        .login-card h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #f1f5f9;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .login-card h2 i {
            color: var(--cyan);
            font-size: 1.15rem;
        }
        .secure-badge {
            font-size: 0.72rem;
            font-weight: 600;
            color: #34d399;
            background: rgba(52, 211, 153, 0.12);
            border: 1px solid rgba(52, 211, 153, 0.3);
            padding: 0.25rem 0.55rem;
            border-radius: 6px;
            letter-spacing: 0.03em;
        }

        .login-card label {
            font-size: 0.82rem;
            font-weight: 600;
            color: #94a3b8;
            margin-bottom: 0.4rem;
            letter-spacing: 0.02em;
        }

        .input-wrap {
            position: relative;
            margin-bottom: 1.1rem;
        }
        .input-wrap i.field-icon {
            position: absolute;
            left: 0.95rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 1rem;
            pointer-events: none;
            transition: color 0.2s;
        }
        .login-card .form-control {
            border-radius: 12px;
            border: 1.5px solid rgba(148, 163, 184, 0.2);
            padding: 0.78rem 1rem 0.78rem 2.65rem;
            font-size: 0.95rem;
            background: rgba(15, 23, 42, 0.75);
            color: #f1f5f9;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }
        .login-card .form-control:focus {
            border-color: var(--cyan);
            box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.18);
            background: rgba(15, 23, 42, 0.9);
            color: #fff;
            outline: none;
        }
        .login-card .form-control:focus + i.field-icon,
        .input-wrap:focus-within i.field-icon {
            color: var(--cyan);
        }
        .login-card .form-control::placeholder {
            color: #64748b;
        }

        .btn-login {
            width: 100%;
            border: none;
            border-radius: 12px;
            padding: 0.85rem 1.25rem;
            font-weight: 700;
            font-size: 1rem;
            color: #0f172a;
            background: linear-gradient(135deg, #22d3ee 0%, #67e8f9 45%, #a78bfa 100%);
            background-size: 160% 100%;
            box-shadow: 0 4px 20px rgba(34, 211, 238, 0.35);
            transition: background-position 0.35s, transform 0.15s, box-shadow 0.2s;
            letter-spacing: 0.03em;
            margin-top: 0.35rem;
        }
        .btn-login:hover {
            background-position: 100% 0;
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(34, 211, 238, 0.45);
            color: #0f172a;
        }
        .btn-login:active {
            transform: translateY(0);
        }

        .card-footer-note {
            font-size: 0.78rem;
            color: #64748b;
            margin-top: 1.15rem;
            margin-bottom: 0;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
        }
        .card-footer-note i {
            color: var(--cyan);
            opacity: 0.7;
        }

        .login-card .alert {
            border-radius: 10px;
            font-size: 0.88rem;
            padding: 0.7rem 0.9rem;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #fca5a5;
            margin-bottom: 1.1rem;
        }

        /* มุมตกแต่ง circuit */
        .corner {
            position: absolute;
            width: 18px;
            height: 18px;
            border-color: rgba(34, 211, 238, 0.45);
            border-style: solid;
            pointer-events: none;
        }
        .corner-tl { top: 10px; left: 10px; border-width: 2px 0 0 2px; border-radius: 4px 0 0 0; }
        .corner-tr { top: 10px; right: 10px; border-width: 2px 2px 0 0; border-radius: 0 4px 0 0; }
        .corner-bl { bottom: 10px; left: 10px; border-width: 0 0 2px 2px; border-radius: 0 0 0 4px; }
        .corner-br { bottom: 10px; right: 10px; border-width: 0 2px 2px 0; border-radius: 0 0 4px 0; }

        .page-footer {
            position: absolute;
            bottom: 1rem;
            left: 0;
            right: 0;
            z-index: 3;
            text-align: center;
            font-size: 0.75rem;
            color: rgba(148, 163, 184, 0.55);
            letter-spacing: 0.04em;
        }

        @media (max-width: 768px) {
            .login-inner {
                flex-direction: column;
                justify-content: center;
                padding: 1.5rem 1rem 3.5rem;
            }
            .login-brand {
                text-align: center;
                max-width: 100%;
            }
            .brand-logo {
                justify-content: center;
            }
            .feature-chips {
                justify-content: center;
            }
            .login-card {
                width: 100%;
                max-width: 400px;
            }
            .scanline { display: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            .login-bg-video { display: none; }
            .scanline, .brand-mark::after { display: none; }
            .login-page {
                background-image: url('assets/login-bg.jpg');
                background-size: cover;
                background-position: center top;
            }
        }
    </style>
</head>
<body>
<div class="login-page">
    <video class="login-bg-video" autoplay muted loop playsinline poster="assets/login-bg.jpg">
        <source src="assets/login-bg.mp4" type="video/mp4">
    </video>
    <div class="login-overlay"></div>
    <div class="login-grid"></div>
    <div class="scanline"></div>

    <div class="login-inner">
        <div class="login-brand">
            <div class="brand-logo">
                <div class="brand-mark">
                    <i class="bi bi-box-seam"></i>
                </div>
            </div>
            <div class="status-pill">
                <span class="dot"></span>
                System Online
            </div>
            <h1>STOCK<span>PRO</span></h1>
            <p class="tagline">ENTERPRISE WMS · IT &amp; ELECTRONICS</p>
            <p class="desc">
                ศูนย์กลางจัดการคลังสินค้าไอทีและอิเล็กทรอนิกส์<br>
                รับเข้า · เบิกจ่าย · ติดตามสต็อกแบบเรียลไทม์
            </p>
            <div class="feature-chips">
                <span><i class="bi bi-cpu"></i>IT Inventory</span>
                <span><i class="bi bi-qr-code"></i>Barcode</span>
                <span><i class="bi bi-arrow-left-right"></i>Stock Flow</span>
                <span><i class="bi bi-shield-lock"></i>Secure Access</span>
            </div>
        </div>

        <div class="login-card">
            <span class="corner corner-tl"></span>
            <span class="corner corner-tr"></span>
            <span class="corner corner-bl"></span>
            <span class="corner corner-br"></span>

            <div class="card-header-row">
                <h2><i class="bi bi-terminal"></i> เข้าสู่ระบบ</h2>
                <span class="secure-badge"><i class="bi bi-lock-fill"></i> SECURE</span>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="on">
                <label class="form-label">ชื่อผู้ใช้</label>
                <div class="input-wrap">
                    <input type="text" name="username" class="form-control" placeholder="Username" required autofocus>
                    <i class="bi bi-person field-icon"></i>
                </div>

                <label class="form-label">รหัสผ่าน</label>
                <div class="input-wrap">
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    <i class="bi bi-key field-icon"></i>
                </div>

                <button type="submit" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right me-1"></i> AUTHENTICATE
                </button>
            </form>

            <p class="card-footer-note">
                <i class="bi bi-shield-check"></i>
                Encrypted session · Role-based access
            </p>
        </div>
    </div>

    <div class="page-footer">
        © <?= date('Y') ?> STOCKPRO — Enterprise IT &amp; Electronics Warehouse Management
    </div>
</div>
</body>
</html>
