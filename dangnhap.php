<?php
session_start();
include("config/db.php");
$message = "";

$recaptcha_secret = "6LeqIhMsAAAAAO7sELQwRxNpBMovbQvhWlUJcyZo";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sdt_email = trim($_POST['sdt_email']);
    $pass = $_POST['pass'];
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    // 1. Check reCAPTCHA
    $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret}&response={$recaptcha_response}");
    $captcha_success = json_decode($verify);

    if (!$captcha_success->success) {
        $message = "Vui lòng xác thực reCAPTCHA!";
    } else {
        // 2. Ưu tiên đăng nhập Admin trước (để admin cũng dùng được trang này)
        $stmt_admin = $conn->prepare("SELECT Ma_admin AS id, Password_hash AS password_hash FROM Admins WHERE Username = ? LIMIT 1");
        $stmt_admin->bind_param("s", $sdt_email);
        $stmt_admin->execute();
        $admin = $stmt_admin->get_result()->fetch_assoc();

        if ($admin) {
            if (password_verify($pass, $admin['password_hash'])) {
                $_SESSION['admin_id'] = $admin['id'];
                unset($_SESSION['user']);
                header("Location: admin/index.php");
                exit();
            }
            // Nếu nhập đúng username admin nhưng sai mật khẩu thì báo lỗi luôn.
            $message = "Sai tên đăng nhập hoặc mật khẩu!";
        } else {
            // 3. Tìm user bằng SĐT hoặc Email
            $stmt = $conn->prepare("SELECT * FROM khachhang WHERE So_dien_thoai=? OR Email=?");
            $stmt->bind_param("ss", $sdt_email, $sdt_email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if ($user) {
                $lockout_time = 15 * 60; // 15 phút
                $current_time = time();
                $last_attempt = strtotime($user['last_attempt'] ?? '0');

                // 3. Kiểm tra Lockout
                if ($user['login_attempts'] >= 5 && ($current_time - $last_attempt) < $lockout_time) {
                    $message = "Sai quá 5 lần. Vui lòng thử lại sau 15 phút.";
                } else {
                    // Reset nếu đã qua thời gian khóa
                    if (($current_time - $last_attempt) >= $lockout_time) {
                        $conn->query("UPDATE khachhang SET login_attempts=0 WHERE Ma_khach_hang='{$user['Ma_khach_hang']}'");
                        $user['login_attempts'] = 0;
                    }

                    // 4. Kiểm tra mật khẩu
                    if (password_verify($pass, $user['Mat_khau'])) {
                        // Thành công
                        $conn->query("UPDATE khachhang SET login_attempts=0, last_attempt=NULL WHERE Ma_khach_hang='{$user['Ma_khach_hang']}'");
                        $_SESSION['user'] = $user;
                        header("Location: index.php");
                        exit();
                    } else {
                        // Thất bại
                        $conn->query("UPDATE khachhang SET login_attempts = login_attempts + 1, last_attempt = NOW() WHERE Ma_khach_hang='{$user['Ma_khach_hang']}'");
                        $message = "Thông tin đăng nhập không chính xác! (Lần thử: ".($user['login_attempts']+1).")";
                    }
                }
            } else {
                $message = "Tài khoản không tồn tại!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <meta charset="UTF-8">
    <title>Đăng nhập - Carpe Diem</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h2>Đăng nhập</h2>
            <?php if ($message): ?><p style="color:red;"><?= $message ?></p><?php endif; ?>
            <form class="auth-form" method="POST">
                <input type="text" name="sdt_email" placeholder="SĐT hoặc Email" required>
                <input type="password" name="pass" placeholder="Mật khẩu" required>
                <center><div class="g-recaptcha" data-sitekey="6LeqIhMsAAAAAP3becH6MiYEdC7EmyqQ7ZC8PajJ"></div></center>
                <button type="submit" class="auth-btn">ĐĂNG NHẬP</button>
            </form>
            <div class="auth-footer">Chưa có tài khoản? <a href="dangky.php">Đăng ký ngay</a></div>
        </div>
    </div>
</body>
</html>