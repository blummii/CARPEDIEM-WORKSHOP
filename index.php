<?php
session_start();
include("config/db.php");
require_once __DIR__ . '/includes/workshop_date.php';

$isLogin = isset($_SESSION['user']);
$user = $isLogin ? $_SESSION['user'] : null;
?>

<!DOCTYPE html>
<html lang="vi">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Carpe Diem Workshop</title>

<link rel="stylesheet" href="assets/style.css">

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:Arial, Helvetica, sans-serif;
    background:#f5f7fb;
    color:#333;
}

/* ================= HEADER ================= */

.topbar{
    width:100%;
    background:white;
    padding:18px 6%;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 2px 12px rgba(0,0,0,.05);
    position:sticky;
    top:0;
    z-index:999;
}

.logo{
    font-size:30px;
    font-weight:bold;
    color:#ff6b9d;
}

.menu{
    display:flex;
    align-items:center;
    gap:18px;
}

.menu a{
    text-decoration:none;
    color:#333;
    font-weight:600;
}

/* ================= HERO ================= */

.hero{
    height:520px;
    background:url('assets/images/banner.jpg') center/cover no-repeat;
    position:relative;
    display:flex;
    align-items:center;
}

.hero::before{
    content:'';
    position:absolute;
    inset:0;
    background:rgba(0,0,0,.45);
}

.hero-content{
    position:relative;
    z-index:2;
    width:90%;
    max-width:1300px;
    margin:auto;
    color:white;
}

.hero-content h1{
    font-size:60px;
    margin-bottom:18px;
}

.hero-content p{
    width:650px;
    max-width:100%;
    font-size:20px;
    line-height:1.7;
}

.hero-btn{
    margin-top:30px;
    display:inline-block;
    background:#ffbfd0;
    color:#333;
    text-decoration:none;
    padding:16px 30px;
    border-radius:40px;
    font-weight:bold;
}

/* ================= CONTAINER ================= */

.container{
    width:90%;
    max-width:1450px;
    margin:auto;
    padding:60px 0;
}

/* ================= SECTION ================= */

.section{
    margin-bottom:70px;
}

.section-top{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:28px;
}

.section-title{
    font-size:42px;
    font-weight:bold;
}

.view-more{
    background:#d9f4f4;
    color:#2d9198;
    padding:12px 24px;
    border-radius:18px;
    text-decoration:none;
    font-weight:bold;
}

/* ================= WORKSHOP ROW ================= */

.workshop-row{
    display:flex;
    gap:28px;
    overflow-x:auto;
    padding-bottom:12px;
    scroll-behavior:smooth;
}

.workshop-row::-webkit-scrollbar{
    height:8px;
}

.workshop-row::-webkit-scrollbar-thumb{
    background:#ddd;
    border-radius:20px;
}

/* ================= CARD ================= */

.card{
    min-width:360px;
    max-width:360px;
    background:white;
    border-radius:24px;
    overflow:hidden;
    box-shadow:0 5px 22px rgba(0,0,0,.06);
    transition:.25s;
    flex-shrink:0;
}

.card:hover{
    transform:translateY(-6px);
}

.card img{
    width:100%;
    height:230px;
    object-fit:cover;
}

.card-body{
    padding:24px;
}

.card-title{
    font-size:24px;
    font-weight:bold;
    color:#b47d84;
    margin-bottom:12px;
}

.desc{
    color:#666;
    line-height:1.7;
    height:58px;
    overflow:hidden;
}

.price{
    color:#ff5f92;
    font-size:24px;
    font-weight:bold;
    margin:24px 0;
}

.info-box{
    background:#fff2f6;
    padding:16px;
    border-radius:16px;
    line-height:2;
    font-size:17px;
}

.btn{
    width:100%;
    border:none;
    padding:15px;
    margin-top:20px;
    border-radius:16px;
    background:#f6b7c9;
    color:#7c2c41;
    font-weight:bold;
    font-size:17px;
    cursor:pointer;
    transition:.2s;
}

.btn:hover{
    opacity:.9;
}

/* ================= BOOKED ================= */

.booked-box{
    background:white;
    padding:30px;
    border-radius:24px;
    margin-bottom:60px;
    box-shadow:0 5px 22px rgba(0,0,0,.05);
}

table{
    width:100%;
    border-collapse:collapse;
}

th, td{
    padding:14px;
    border-bottom:1px solid #eee;
    text-align:left;
}

th{
    background:#fff2f6;
}

/* ================= POPUP ================= */

.popup{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.6);
    display:none;
    justify-content:center;
    align-items:center;
    z-index:9999;
}

.popup-content{
    width:760px;
    max-width:95%;
    background:white;
    border-radius:24px;
    padding:30px;
    position:relative;
}

.close{
    position:absolute;
    right:22px;
    top:12px;
    font-size:30px;
    cursor:pointer;
}

.popup-title{
    font-size:34px;
    margin-bottom:25px;
}

/* ================= SEAT ================= */

.seat-grid{
    display:grid;
    grid-template-columns:repeat(5,1fr);
    gap:12px;
    margin-bottom:25px;
}

.seat{
    padding:14px;
    text-align:center;
    border-radius:12px;
    font-weight:bold;
    cursor:pointer;
}

.available{
    background:#ececec;
}

.selected{
    background:#ffbfd0;
}

.occupied{
    background:#999;
    color:white;
    cursor:not-allowed;
}

.summary{
    margin:18px 0;
    line-height:2;
    font-size:17px;
}

.pay-box{
    background:#fff2f6;
    padding:16px;
    border-radius:16px;
    margin-bottom:16px;
}

input{
    width:100%;
    padding:14px;
    border:1px solid #ddd;
    border-radius:14px;
    margin-top:12px;
}

/* ================= FOOTER ================= */

.footer{
    margin-top:70px;
    background:white;
    text-align:center;
    padding:26px;
    color:#777;
}

/* ================= MOBILE ================= */

@media(max-width:768px){

.hero{
    height:380px;
}

.hero-content h1{
    font-size:40px;
}

.section-title{
    font-size:30px;
}

.card{
    min-width:300px;
    max-width:300px;
}

}

</style>
</head>

<body>

<!-- ================= HEADER ================= -->

<div class="topbar">

<div class="logo">
CARPE DIEM
</div>

<div class="menu">

<?php if($isLogin): ?>

<span>
Xin chào <b><?= $user['Ten_khach_hang'] ?></b>
</span>

<a href="logout.php">
Đăng xuất
</a>

<?php else: ?>

<a href="dangnhap.php">
Đăng nhập
</a>

<a href="dangky.php">
Đăng ký
</a>

<?php endif; ?>

</div>
</div>

<!-- ================= HERO ================= -->

<section class="hero">

<div class="hero-content">

<h1>
Workshop truyền cảm hứng
</h1>

<p>
Khám phá nghệ thuật, sáng tạo và chữa lành
cùng những workshop độc đáo tại Carpe Diem.
</p>

<a href="#workshop-section" class="hero-btn">
Khám phá ngay
</a>

</div>
</section>

<!-- ================= CONTENT ================= -->

<div class="container">

<!-- ================= ĐÃ ĐĂNG KÝ ================= -->

<?php if($isLogin): ?>

<div class="booked-box">

<h2 class="section-title" style="margin-bottom:20px;">
Workshop đã đăng ký
</h2>

<table>

<tr>
<th>Workshop</th>
<th>Ngày học</th>
<th>Ghế</th>
<th>Thanh toán</th>
</tr>

<?php

$ma_kh = $user['Ma_khach_hang'];

$sqlBooked = "
SELECT dk.*, c.Ten_chu_de, l.Ngay_to_chuc
FROM dangkyworkshop dk
JOIN lichworkshop l
ON dk.Ma_lich_workshop = l.Ma_lich_workshop
JOIN chudeworkshop c
ON l.Ma_chu_de = c.Ma_chu_de
WHERE dk.Ma_khach_hang = '$ma_kh'
ORDER BY dk.Thoi_gian_tao DESC
";

$rsBooked = $conn->query($sqlBooked);

if($rsBooked && $rsBooked->num_rows > 0):

while($b = $rsBooked->fetch_assoc()):

$madk = $b['Ma_dang_ky'];

$ghe = [];

$getSeat = $conn->query("
SELECT So_ghe
FROM chitietghe
WHERE Ma_dang_ky='$madk'
");

while($s = $getSeat->fetch_assoc()){
    $ghe[] = $s['So_ghe'];
}

?>

<tr>

<td><?= $b['Ten_chu_de'] ?></td>

<td><?= workshop_fmt_ngay_vn($b['Ngay_to_chuc']) ?></td>

<td><?= implode(", ", $ghe) ?></td>

<td><?= $b['Trang_thai_thanh_toan'] ?></td>

</tr>

<?php endwhile; else: ?>

<tr>
<td colspan="4">
Bạn chưa đăng ký workshop nào
</td>
</tr>

<?php endif; ?>

</table>

</div>

<?php endif; ?>

<!-- ================= WORKSHOP ================= -->

<div class="section" id="workshop-section">

<div class="section-top">

<h2 class="section-title">
Workshop đang diễn ra
</h2>


</div>

<div class="workshop-row">

<?php

$sql = "
SELECT c.*, l.*
FROM chudeworkshop c
JOIN lichworkshop l
ON c.Ma_chu_de = l.Ma_chu_de
ORDER BY l.Ngay_to_chuc ASC
";

$rs = $conn->query($sql);

$shownWorkshop = [];

while($row = $rs->fetch_assoc()):

if(in_array($row['Ma_chu_de'], $shownWorkshop)){
    continue;
}

$shownWorkshop[] = $row['Ma_chu_de'];

$conlai = $row['So_luong_toi_da'] - $row['So_luong_da_dang_ky'];

$img = !empty($row['Hinh_anh'])
? $row['Hinh_anh']
: 'assets/images/default.jpg';

?>

<div class="card">

<img src="<?= $img ?>">

<div class="card-body">

<div class="card-title">
<?= $row['Ten_chu_de'] ?>
</div>

<div class="desc">
<?= $row['Mo_ta'] ?>
</div>

<div class="price">
<?= number_format($row['Gia'],0,",",".") ?>đ
</div>

<div class="info-box">

📅 <?= workshop_fmt_ngay_vn($row['Ngay_to_chuc']) ?>

<br>

🪑 Còn <?= $conlai ?> chỗ

</div>

<?php if($conlai > 0): ?>

<button class="btn"

onclick="openPopup(
'<?= $row['Ma_lich_workshop'] ?>',
<?= $row['So_luong_toi_da'] ?>,
<?= $row['Gia'] ?>
)">

Đặt workshop

</button>

<?php else: ?>

<button class="btn" disabled>
Đã hết chỗ
</button>

<?php endif; ?>

</div>
</div>

<?php endwhile; ?>

</div>
</div>

</div>

<!-- ================= POPUP ================= -->

<div class="popup" id="popup">

<div class="popup-content">

<span class="close" onclick="closePopup()">×</span>

<h2 class="popup-title">
Đăng ký Workshop
</h2>

<form action="xuly_dangky.php" method="POST">

<input type="hidden" name="id_lich" id="id_lich">

<input type="hidden"
name="selected_seats"
id="selected_seats">

<div class="seat-grid" id="seat-grid"></div>

<div class="summary">

<p>
Ghế đã chọn:
<b id="seatText">Chưa chọn</b>
</p>

<p>
Tổng tiền:
<b id="tongTien">0đ</b>
</p>

</div>

<div class="pay-box">

<b>Thanh toán:</b>
100% giá trị đơn hàng

<input type="hidden"
name="hinh_thuc_tt"
value="100">

</div>

<input type="text"
name="ten"
placeholder="Họ tên"
value="<?= $isLogin ? $user['Ten_khach_hang'] : '' ?>"
required>

<input type="text"
name="sdt"
placeholder="Số điện thoại"
value="<?= $isLogin ? $user['So_dien_thoai'] : '' ?>"
required>

<input type="email"
name="email"
placeholder="Email"
value="<?= $isLogin ? $user['Email'] : '' ?>"
required>

<button class="btn">
Xác nhận đặt chỗ
</button>

</form>

</div>
</div>

<!-- ================= FOOTER ================= -->

<div class="footer">
© 2026 Carpe Diem Workshop
</div>

<script src="assets/script.js"></script>

</body>
</html>