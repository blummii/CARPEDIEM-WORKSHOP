<?php
session_start();
include("config/db.php");

/*
====================================================
XACNHAN_THANHTOAN.PHP
✔ Fix parse error
✔ Lưu đăng ký workshop
✔ Lưu ghế
✔ Cập nhật số lượng
✔ Gửi email xác nhận
✔ Chống lưu trùng khi F5
====================================================
*/

/* =========================
LOAD PHPMailer
========================= */
require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* =========================
CHECK SESSION BOOKING
========================= */
if (!isset($_SESSION['booking'])) {
    echo "<script>
            alert('Không tìm thấy thông tin thanh toán!');
            location='index.php';
          </script>";
    exit();
}

$b = $_SESSION['booking'];

/* =========================
LẤY DỮ LIỆU SESSION
========================= */
$madk      = $b['Ma_dang_ky'] ?? '';
$ma_lich   = $b['Ma_lich'] ?? '';
$ma_kh     = $b['Ma_kh'] ?? '';
$soluong   = (int)($b['So_luong'] ?? 0);
$tong      = (float)($b['Tong_tien'] ?? 0);
$seats     = $b['Seats'] ?? [];
$email     = $b['Email'] ?? '';
$thanhtoan = (float)($b['Thanh_toan'] ?? 0);

/* =========================
VALIDATE
========================= */
if ($madk == '' || $ma_lich == '' || $ma_kh == '' || $soluong <= 0) {
    echo "<script>
            alert('Dữ liệu đặt chỗ không hợp lệ!');
            location='index.php';
          </script>";
    exit();
}

/* =========================
CHỐNG F5 LƯU TRÙNG
========================= */
$check = $conn->prepare("SELECT Ma_dang_ky FROM dangkyworkshop WHERE Ma_dang_ky=?");
$check->bind_param("s", $madk);
$check->execute();
$rsCheck = $check->get_result();

if ($rsCheck->num_rows > 0) {
    unset($_SESSION['booking']);

    echo "<script>
            alert('Đơn hàng đã được xác nhận trước đó!');
            location='index.php';
          </script>";
    exit();
}

/* =========================
LẤY THÔNG TIN WORKSHOP
========================= */
$stmtInfo = $conn->prepare("
SELECT c.Ten_chu_de, l.Ngay_to_chuc
FROM lichworkshop l
JOIN chudeworkshop c ON l.Ma_chu_de = c.Ma_chu_de
WHERE l.Ma_lich_workshop = ?
");
$stmtInfo->bind_param("s", $ma_lich);
$stmtInfo->execute();
$info = $stmtInfo->get_result()->fetch_assoc();

$tenWorkshop = $info['Ten_chu_de'] ?? 'Workshop';
$ngayHoc = $info['Ngay_to_chuc'] ?? '';

/* =========================
TRANSACTION
========================= */
$conn->begin_transaction();

try {

    /* =========================
    INSERT ĐĂNG KÝ
    ========================= */
    $stmt = $conn->prepare("
        INSERT INTO dangkyworkshop
        (
            Ma_dang_ky,
            Ma_lich_workshop,
            Ma_khach_hang,
            So_nguoi_tham_gia,
            Tong_tien,
            Trang_thai_thanh_toan,
            Thoi_gian_tao
        )
        VALUES (?, ?, ?, ?, ?, 'Đã thanh toán', NOW())
    ");

    $stmt->bind_param(
        "sssii",
        $madk,
        $ma_lich,
        $ma_kh,
        $soluong,
        $tong
    );

    $stmt->execute();

    /* =========================
    INSERT GHẾ
    ========================= */
    foreach ($seats as $ghe) {

        $ghe = trim($ghe);

        $stmtSeat = $conn->prepare("
            INSERT INTO chitietghe
            (
                Ma_lich_workshop,
                So_ghe,
                Ma_dang_ky,
                Trang_thai
            )
            VALUES (?, ?, ?, 'Đã đặt')
        ");

        $stmtSeat->bind_param("sis", $ma_lich, $ghe, $madk);
        $stmtSeat->execute();
    }

    /* =========================
    UPDATE SỐ LƯỢNG
    ========================= */
    $stmtUpdate = $conn->prepare("
        UPDATE lichworkshop
        SET So_luong_da_dang_ky = So_luong_da_dang_ky + ?
        WHERE Ma_lich_workshop = ?
    ");

    $stmtUpdate->bind_param("is", $soluong, $ma_lich);
    $stmtUpdate->execute();

    $conn->commit();

} catch (Exception $e) {

    $conn->rollback();

    echo "<script>
            alert('Có lỗi xảy ra. Vui lòng thử lại!');
            history.back();
          </script>";
    exit();
}

/* =========================
GỬI EMAIL
========================= */
if ($email != '') {

    try {

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;

        /* THAY THÔNG TIN GMAIL CỦA BẠN */
        $mail->Username   = 'yourgmail@gmail.com';
        $mail->Password   = 'your_app_password';

        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->CharSet = 'UTF-8';

        $mail->setFrom('yourgmail@gmail.com', 'Carpe Diem Workshop');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Xác nhận đăng ký workshop thành công';

        $mail->Body = "
        <h2>🎉 Đăng ký thành công</h2>

        <p><b>Mã đăng ký:</b> $madk</p>
        <p><b>Workshop:</b> $tenWorkshop</p>
        <p><b>Ngày học:</b> $ngayHoc</p>
        <p><b>Số ghế:</b> " . implode(", ", $seats) . "</p>
        <p><b>Số lượng:</b> $soluong người</p>
        <p><b>Tổng tiền:</b> " . number_format($tong, 0, ',', '.') . "đ</p>
        <p><b>Đã thanh toán:</b> " . number_format($thanhtoan, 0, ',', '.') . "đ</p>

        <br>
        <p>Cảm ơn bạn đã đăng ký tại Carpe Diem 🌿</p>
        ";

        $mail->send();

    } catch (Exception $e) {
        // Không dừng hệ thống nếu mail lỗi
    }
}

/* =========================
XÓA SESSION BOOKING
========================= */
unset($_SESSION['booking']);

/* =========================
THÀNH CÔNG
========================= */
echo "
<script>
alert('🎉 Thanh toán thành công! Đăng ký workshop thành công!');
location='index.php';
</script>
";
?>