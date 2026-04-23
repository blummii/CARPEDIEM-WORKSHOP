<?php
session_start();

/*
==================================================
PART B - THANHTOAN.PHP
✔ Lấy dữ liệu từ SESSION booking
✔ Hiển thị thông tin đơn đăng ký
✔ Tạo mã QR VietQR thật
✔ VCB - 1041501109
✔ Nội dung CK = mã đăng ký
✔ Nút Tôi đã thanh toán
==================================================
*/

if (!isset($_SESSION['booking'])) {
    header("Location:index.php");
    exit();
}

$b = $_SESSION['booking'];

$madk        = $b['Ma_dang_ky'];
$tenKH       = $b['Ten_khach'];
$email       = $b['email'];
$tenWorkshop = $b['Ten_workshop'];
$seats       = $b['Seats'];
$soluong     = $b['So_luong'];
$tongTien    = $b['Tong_tien'];
$canThanhToan= $b['Thanh_toan'];
$hinhThuc    = $b['Hinh_thuc'];

/* =========================
   QR BANK
========================= */

$bank = "VCB";
$stk  = "1041501109";
$tenTK = "CARPE DIEM";

/* Nội dung chuyển khoản */
$noidung = $madk;

/* QR VietQR */
$qr = "https://img.vietqr.io/image/"
    . $bank . "-" . $stk . "-compact2.png"
    . "?amount=" . (int)$canThanhToan
    . "&addInfo=" . urlencode($noidung)
    . "&accountName=" . urlencode($tenTK);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Thanh toán Workshop</title>

<style>
body{
    margin:0;
    font-family:Arial, Helvetica, sans-serif;
    background:#fff5f7;
}

.wrap{
    max-width:700px;
    margin:40px auto;
    background:white;
    border-radius:20px;
    padding:35px;
    box-shadow:0 15px 40px rgba(0,0,0,0.08);
}

h1{
    margin-top:0;
    color:#d4a5a5;
    text-align:center;
}

.box{
    background:#fafafa;
    padding:18px;
    border-radius:14px;
    margin-bottom:20px;
    line-height:1.8;
}

.qr{
    text-align:center;
}

.qr img{
    width:300px;
    max-width:100%;
    border:1px solid #eee;
    border-radius:16px;
    padding:10px;
    background:white;
}

.money{
    font-size:32px;
    color:#842029;
    font-weight:bold;
    margin-top:15px;
}

.note{
    margin-top:10px;
    color:#666;
}

.btn{
    display:block;
    width:100%;
    padding:15px;
    border:none;
    border-radius:12px;
    background:#f8d7da;
    font-size:16px;
    font-weight:bold;
    cursor:pointer;
    margin-top:25px;
}

.btn:hover{
    opacity:.9;
}

.small{
    color:#888;
    font-size:14px;
}
</style>
</head>
<body>

<div class="wrap">

<h1>💳 Thanh toán Workshop</h1>

<div class="box">
    <b>Mã đăng ký:</b> <?= $madk ?><br>
    <b>Khách hàng:</b> <?= htmlspecialchars($tenKH) ?><br>
    <b>Email:</b> <?= htmlspecialchars($email) ?><br>
    <b>Workshop:</b> <?= htmlspecialchars($tenWorkshop) ?><br>
    <b>Ghế:</b> <?= implode(", ", $seats) ?><br>
    <b>Số lượng:</b> <?= $soluong ?><br>
    <b>Tổng đơn:</b> <?= number_format($tongTien,0,",",".") ?>đ<br>
    <b>Thanh toán:</b> <?= $hinhThuc ?>%
</div>

<div class="qr">
    <img src="<?= $qr ?>" alt="QR thanh toán">

    <div class="money">
        <?= number_format($canThanhToan,0,",",".") ?>đ
    </div>

    <div class="note">
        Ngân hàng <b>VCB</b> - STK <b>1041501109</b><br>
        Nội dung chuyển khoản: <b><?= $madk ?></b>
    </div>

    <p class="small">
        Sau khi chuyển khoản xong, bấm nút bên dưới để hoàn tất đăng ký.
    </p>
</div>

<form action="xacnhan_thanhtoan.php" method="POST">

<input type="email" name="email_xacnhan"
placeholder="Nhập email nhận xác nhận"
required
style="width:100%;padding:12px;margin:15px 0;">

<button class="btn">
Tôi đã thanh toán
</button>

</form>

</div>

</body>
</html>