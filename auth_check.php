<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function checkAdmin() {
    checkAuth();
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo "<div style='font-family: sans-serif; text-align: center; margin-top: 50px;'>
                <h2 style='color: red;'>403 Forbidden</h2>
                <p>คุณไม่มีสิทธิ์เข้าถึงส่วนนี้ (เฉพาะ Admin เท่านั้น)</p>
                <a href='dashboard.php'>กลับสู่หน้าแดชบอร์ด</a>
              </div>";
        exit;
    }
}