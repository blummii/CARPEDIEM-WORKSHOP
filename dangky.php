<?php
include("config/db.php"); // Sử dụng file connect của bạn
$message = "";

// Cấu hình reCAPTCHA (Dùng key bạn đã cung cấp)
$recaptcha_secret = "6LeqIhMsAAAAAO7sELQwRxNpBMovbQvhWlUJcyZo";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hoten = trim($_POST['ten']);
    $sdt = trim($_POST['sdt']);
    $email = trim($_POST['email']);
    $matkhau_raw = $_POST['pass'];
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    // 1. Kiểm tra reCAPTCHA
    if (empty($recaptcha_response)) {
        $message = "Vui lòng xác thực reCAPTCHA!";
    } else {
        $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret}&response={$recaptcha_response}");
        $captcha_success = json_decode($verify);
        if (!$captcha_success->success) {
            $message = "Xác thực reCAPTCHA thất bại!";
        }
    }

    if (!$message) {
        // 2. Kiểm tra định dạng dữ liệu
        if (empty($hoten) || empty($sdt) || empty($email) || empty($matkhau_raw)) {
            $message = "Vui lòng nhập đầy đủ thông tin!";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Email không đúng định dạng!";
        } elseif (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/', $matkhau_raw)) {
            $message = "Mật khẩu phải từ 8 ký tự, có chữ hoa, thường, số và ký tự đặc biệt!";
        } else {
            // 3. Kiểm tra SĐT hoặc Email trùng
            $check = $conn->prepare("SELECT Ma_khach_hang FROM khachhang WHERE So_dien_thoai=? OR Email=?");
            $check->bind_param("ss", $sdt, $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $message = "Số điện thoại hoặc Email đã tồn tại!";
            } else {
                // 4. Mã hóa mật khẩu và lưu
                $matkhau_hash = password_hash($matkhau_raw, PASSWORD_DEFAULT);
                $ma_kh = "KH" . rand(1000, 9999);
                
                $stmt = $conn->prepare("INSERT INTO khachhang (Ma_khach_hang, Ten_khach_hang, So_dien_thoai, Email, Mat_khau) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $ma_kh, $hoten, $sdt, $email, $matkhau_hash);
                
                if ($stmt->execute()) {
                    echo "<script>alert('Đăng ký thành công!'); window.location.href='dangnhap.php';</script>";
                    exit();
                } else {
                    $message = "Lỗi hệ thống, vui lòng thử lại!";
                }
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
    <title>Đăng ký - Carpe Diem</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h2>Đăng ký Workshop</h2>
            <?php if ($message): ?><p style="color:red;"><?= $message ?></p><?php endif; ?>
            <form class="auth-form" method="POST">
                <input type="text" name="ten" placeholder="Họ tên" required>
                <input type="text" name="sdt" placeholder="Số điện thoại" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="pass" placeholder="Mật khẩu" required>
                <center><div class="g-recaptcha" data-sitekey="6LeqIhMsAAAAAP3becH6MiYEdC7EmyqQ7ZC8PajJ"></div></center>
                <button type="submit" class="auth-btn">ĐĂNG KÝ</button>
            </form>
            <div class="auth-footer">Đã có tài khoản? <a href="dangnhap.php">Đăng nhập</a></div>
        </div>
    </div>
</body>
</html>