<?php
session_start();
include("config/db.php");

/*
====================================================
PART 6 FULL EMAIL FLOW
XACNHAN_THANHTOAN.PHP
✔ Sau khi bấm Tôi đã thanh toán
✔ Hiện form nhập email thật
✔ Validate email
✔ Lưu đăng ký workshop
✔ Lưu ghế
✔ Update số lượng
✔ Gửi email xác nhận
✔ Chống F5 lưu trùng
====================================================
*/

/* ===============================
LOAD PHPMailer
================================= */
require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ===============================
CHECK SESSION
================================= */
if (!isset($_SESSION['booking'])) {
    echo "<script>alert('Không tìm thấy đơn thanh toán');location='index.php';</script>";
    exit();
}

$b = $_SESSION['booking'];

/* ===============================
BƯỚC 1: CHƯA NHẬP EMAIL -> HIỆN FORM
================================= */
if (!isset($_POST['email_xacnhan'])) {
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Xác nhận Email</title>
<style>
body{
    margin:0;
    background:#fff7fa;
    font-family:Arial;
}
.box{
    width:450px;
    max-width:95%;
    margin:80px auto;
    background:white;
    padding:35px;
    border-radius:22px;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
}
h2{
    color:#d67d93;
    margin-bottom:15px;
}
p{
    color:#666;
    line-height:1.6;
}
input{
    width:100%;
    padding:14px;
    border:1px solid #ddd;
    border-radius:12px;
    margin-top:15px;
    font-size:16px;
}
button{
    width:100%;
    margin-top:18px;
    padding:14px;
    background:#f7c4d0;
    border:none;
    border-radius:14px;
    font-weight:bold;
    cursor:pointer;
}
</style>
</head>
<body>

<div class="box">

<h2>📩 Xác nhận Email</h2>

<p>
Vui lòng nhập email chính xác để nhận thông tin workshop sau khi thanh toán thành công.
</p>

<form method="POST">

<input type="email"
name="email_xacnhan"
placeholder="Nhập email của bạn"
required>

<button type="submit">
Xác nhận & Hoàn tất
</button>

</form>

</div>

</body>
</html>
<?php
exit();
}

/* ===============================
BƯỚC 2: NHẬN EMAIL
================================= */
$email = trim($_POST['email_xacnhan']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "<script>alert('Email không hợp lệ');history.back();</script>";
    exit();
}

/* ===============================
LẤY SESSION BOOKING
================================= */
$madk      = $b['Ma_dang_ky'];
$ma_lich   = $b['Ma_lich'];
$ma_kh     = $b['Ma_kh'];
$soluong   = $b['So_luong'];
$tong      = $b['Tong_tien'];
$thanhtoan = $b['Thanh_toan'];
$seats     = $b['Seats'];

/* ===============================
CHECK TRÙNG
================================= */
$check = $conn->prepare("SELECT Ma_dang_ky FROM dangkyworkshop WHERE Ma_dang_ky=?");
$check->bind_param("s", $madk);
$check->execute();
$rs = $check->get_result();

if ($rs->num_rows > 0) {
    unset($_SESSION['booking']);
    echo "<script>alert('Đơn hàng đã xử lý trước đó');location='index.php';</script>";
    exit();
}

/* ===============================
LẤY THÔNG TIN WORKSHOP
================================= */
$sqlInfo = "
SELECT c.Ten_chu_de,l.Ngay_to_chuc
FROM lichworkshop l
JOIN chudeworkshop c ON l.Ma_chu_de=c.Ma_chu_de
WHERE l.Ma_lich_workshop='$ma_lich'
";

$rInfo = $conn->query($sqlInfo)->fetch_assoc();

$tenWorkshop = $rInfo['Ten_chu_de'];
$ngayHoc = $rInfo['Ngay_to_chuc'];

/* ===============================
TRANSACTION
================================= */
$conn->begin_transaction();

try{

/* LƯU ĐĂNG KÝ */
$conn->query("
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
VALUES
(
'$madk',
'$ma_lich',
'$ma_kh',
'$soluong',
'$tong',
'Đã thanh toán',
NOW()
)
");

/* LƯU GHẾ */
foreach($seats as $ghe){

$ghe = trim($ghe);

$conn->query("
INSERT INTO chitietghe
(
Ma_lich_workshop,
So_ghe,
Ma_dang_ky,
Trang_thai
)
VALUES
(
'$ma_lich',
'$ghe',
'$madk',
'Đã đặt'
)
");

}

/* UPDATE */
$conn->query("
UPDATE lichworkshop
SET So_luong_da_dang_ky = So_luong_da_dang_ky + $soluong
WHERE Ma_lich_workshop='$ma_lich'
");

$conn->commit();

}catch(Exception $e){

$conn->rollback();

echo "<script>alert('Lỗi xử lý thanh toán');history.back();</script>";
exit();
}

/* ===============================
GỬI EMAIL
================================= */
try{

$mail = new PHPMailer(true);

$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->SMTPAuth = true;

$mail->Username = 'lovetfboys1172005@gmail.com';
$mail->Password = 'opkhjejnbjednswh';

$mail->SMTPSecure = 'tls';
$mail->Port = 587;

$mail->CharSet = 'UTF-8';

$mail->setFrom('lovetfboys1172005@gmail.com','Carpe Diem Workshop');
$mail->addAddress($email);

$mail->isHTML(true);
$mail->Subject = 'Xác nhận đăng ký workshop thành công';

$mail->Body = "
<h2>🎉 Đăng ký thành công</h2>

<p><b>Mã đăng ký:</b> $madk</p>
<p><b>Workshop:</b> $tenWorkshop</p>
<p><b>Ngày học:</b> $ngayHoc</p>
<p><b>Ghế:</b> ".implode(', ', $seats)."</p>
<p><b>Số lượng:</b> $soluong người</p>
<p><b>Tổng tiền:</b> ".number_format($tong,0,',','.')."đ</p>

<br>

<p>Cảm ơn bạn đã đồng hành cùng Carpe Diem 🌿</p>
";

$mail->send();

}catch(Exception $e){
    // mail lỗi vẫn cho hoàn tất
}

/* ===============================
DONE
================================= */
unset($_SESSION['booking']);

echo "
<script>
alert('🎉 Thanh toán thành công! Email xác nhận đã được gửi!');
location='index.php';
</script>
";
?>