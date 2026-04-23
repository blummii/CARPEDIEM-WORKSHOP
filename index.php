<?php
session_start();
include("config/db.php");
require_once __DIR__ . '/includes/workshop_date.php';

/*
====================================================
INDEX.PHP CHUẨN ĐỒ ÁN 9-10 ĐIỂM
✔ Giao diện đẹp
✔ Hiển thị workshop
✔ Popup chọn ghế
✔ Ghế trống / đã đặt
✔ Chỉ thanh toán 100%
✔ Tự tính tiền
✔ Form thông tin khách hàng
✔ Hiển thị workshop đã đăng ký
====================================================
*/

$isLogin = isset($_SESSION['user']);
$user = $isLogin ? $_SESSION['user'] : null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
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
    background:#fff8fa;
    color:#333;
}

/* HEADER */
.topbar{
    background:#fff;
    padding:16px 6%;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 4px 18px rgba(0,0,0,.06);
    position:sticky;
    top:0;
    z-index:999;
}

.logo{
    font-size:24px;
    font-weight:bold;
    color:#e48aa1;
}

.topbar a{
    text-decoration:none;
    color:#333;
    margin-left:15px;
    font-weight:600;
}

/* HERO */
.hero{
    height:500px;
    background:url('assets/images/banner.jpg') center/cover no-repeat;
    position:relative;
    display:flex;
    align-items:center;
}

.hero::before{
    content:'';
    position:absolute;
    inset:0;
    background:rgba(0,0,0,.35);
}

.hero-content{
    position:relative;
    z-index:2;
    color:white;
    width:90%;
    max-width:1200px;
    margin:auto;
}

.hero h1{
    font-size:56px;
    margin-bottom:15px;
}

.hero p{
    font-size:20px;
    line-height:1.7;
    max-width:650px;
}

.hero-btn{
    display:inline-block;
    margin-top:25px;
    padding:14px 28px;
    border-radius:30px;
    background:#f8c8d3;
    text-decoration:none;
    color:#333;
    font-weight:bold;
}

/* MAIN */
.container{
    width:90%;
    max-width:1300px;
    margin:auto;
    padding:55px 0;
}

.title{
    font-size:34px;
    color:#d47d94;
    margin-bottom:28px;
}

/* TABLE */
.booked{
    background:#fff;
    padding:28px;
    border-radius:22px;
    box-shadow:0 8px 26px rgba(0,0,0,.05);
    margin-bottom:50px;
}

table{
    width:100%;
    border-collapse:collapse;
}

th,td{
    padding:14px;
    border-bottom:1px solid #eee;
}

th{
    background:#fff2f5;
    text-align:left;
}

/* CARD */
.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(320px,1fr));
    gap:28px;
}

.card{
    background:#fff;
    border-radius:22px;
    overflow:hidden;
    box-shadow:0 10px 26px rgba(0,0,0,.07);
    transition:.25s;
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
    padding:22px;
}

.card h3{
    font-size:24px;
    margin-bottom:12px;
}

.desc{
    color:#666;
    line-height:1.6;
    min-height:75px;
}

.price{
    color:#e48198;
    font-size:22px;
    font-weight:bold;
    margin:14px 0;
}

.slot{
    background:#fff3f6;
    padding:14px;
    border-radius:14px;
}

.btn{
    width:100%;
    border:none;
    margin-top:14px;
    padding:13px;
    border-radius:14px;
    background:#f7c5d1;
    font-weight:bold;
    cursor:pointer;
}

/* POPUP */
.popup{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.45);
    display:none;
    justify-content:center;
    align-items:center;
    z-index:9999;
}

.popup-content{
    background:#fff;
    width:760px;
    max-width:96%;
    border-radius:22px;
    padding:28px;
}

.close{
    float:right;
    font-size:28px;
    cursor:pointer;
}

.seat-grid{
    display:grid;
    grid-template-columns:repeat(5,1fr);
    gap:12px;
    margin:22px 0;
}

.seat{
    padding:12px;
    text-align:center;
    border-radius:12px;
    font-weight:bold;
    cursor:pointer;
}

.available{background:#ececec;}
.selected{background:#f8c8d3;}
.occupied{background:#777;color:#fff;cursor:not-allowed;}

input[type=text],
input[type=email]{
    width:100%;
    padding:12px;
    border:1px solid #ddd;
    border-radius:12px;
    margin-top:10px;
}

.payment-box{
    background:#fff4f7;
    padding:14px;
    border-radius:14px;
    margin:14px 0;
    color:#d66b88;
    font-weight:bold;
    text-align:center;
}

.footer{
    margin-top:60px;
    background:#fff;
    padding:25px;
    text-align:center;
    color:#777;
}
</style>
</head>
<body>

<!-- HEADER -->
<div class="topbar">

<div class="logo">CARPE DIEM</div>

<div>
<?php if($isLogin): ?>
Xin chào <b><?= $user['Ten_khach_hang'] ?></b>
<a href="logout.php">Đăng xuất</a>
<?php else: ?>
<a href="dangnhap.php">Đăng nhập</a>
<a href="dangky.php">Đăng ký</a>
<?php endif; ?>
</div>

</div>

<!-- HERO -->
<section class="hero">
<div class="hero-content">
<h1>Workshop truyền cảm hứng</h1>
<p>Khám phá nghệ thuật, tinh dầu, chữa lành và sáng tạo cùng Carpe Diem.</p>
<a href="#workshop" class="hero-btn">Khám phá ngay</a>
</div>
</section>

<div class="container">

<?php if($isLogin): ?>

<!-- WORKSHOP ĐÃ ĐĂNG KÝ -->
<div class="booked">

<h2 class="title">Workshop bạn đã đăng ký</h2>

<table>
<tr>
<th>Tên workshop</th>
<th>Ngày học</th>
<th>Ghế</th>
<th>Thanh toán</th>
</tr>

<?php
$ma_kh = $user['Ma_khach_hang'];

$sql = "
SELECT dk.*, c.Ten_chu_de, l.Ngay_to_chuc
FROM dangkyworkshop dk
JOIN lichworkshop l ON dk.Ma_lich_workshop=l.Ma_lich_workshop
JOIN chudeworkshop c ON c.Ma_chu_de=l.Ma_chu_de
WHERE dk.Ma_khach_hang='$ma_kh'
ORDER BY dk.Thoi_gian_tao DESC
";

$rs = $conn->query($sql);

if($rs && $rs->num_rows > 0):

while($r = $rs->fetch_assoc()):

$madk = $r['Ma_dang_ky'];
$ghe = [];

$getSeat = $conn->query("SELECT So_ghe FROM chitietghe WHERE Ma_dang_ky='$madk' ORDER BY So_ghe ASC");

while($s = $getSeat->fetch_assoc()){
$ghe[] = $s['So_ghe'];
}
?>

<tr>
<td><?= $r['Ten_chu_de'] ?></td>
<td><?= workshop_fmt_ngay_vn($r['Ngay_to_chuc']) ?></td>
<td><?= implode(", ", $ghe) ?></td>
<td><?= $r['Trang_thai_thanh_toan'] ?></td>
</tr>

<?php endwhile; else: ?>

<tr>
<td colspan="4">Chưa đăng ký workshop nào</td>
</tr>

<?php endif; ?>

</table>
</div>

<?php endif; ?>

<!-- DANH SÁCH WORKSHOP -->
<h2 class="title" id="workshop">Khám phá Workshop</h2>

<div class="grid">

<?php
$sql = "
SELECT c.*, l.*
FROM chudeworkshop c
JOIN lichworkshop l ON c.Ma_chu_de=l.Ma_chu_de
ORDER BY l.Ngay_to_chuc ASC
";

$rs = $conn->query($sql);

while($row = $rs->fetch_assoc()):

$conlai = $row['So_luong_toi_da'] - $row['So_luong_da_dang_ky'];
$img = !empty($row['Hinh_anh']) ? $row['Hinh_anh'] : 'assets/images/default.jpg';
?>

<div class="card">

<img src="<?= $img ?>">

<div class="card-body">

<h3><?= $row['Ten_chu_de'] ?></h3>

<p class="desc"><?= $row['Mo_ta'] ?></p>

<div class="price">
<?= number_format($row['Gia'],0,",",".") ?>đ
</div>

<div class="slot">
📅 <?= workshop_fmt_ngay_vn($row['Ngay_to_chuc']) ?><br>
🪑 Còn <?= $conlai ?> chỗ
</div>

<?php if($conlai > 0): ?>

<button class="btn"
onclick="openPopup(
'<?= $row['Ma_lich_workshop'] ?>',
<?= $row['So_luong_toi_da'] ?>,
<?= $row['Gia'] ?>
)">
Chọn ghế ngay
</button>

<?php else: ?>

<button class="btn" disabled>Đã hết chỗ</button>

<?php endif; ?>

</div>
</div>

<?php endwhile; ?>

</div>
</div>

<!-- POPUP ĐẶT CHỖ -->
<div class="popup" id="popup">

<div class="popup-content">

<span class="close" onclick="closePopup()">×</span>

<h2>Đăng ký workshop</h2>

<form action="xuly_dangky.php" method="POST">

<input type="hidden" name="id_lich" id="id_lich">
<input type="hidden" name="selected_seats" id="selected_seats">

<div class="seat-grid" id="seat-grid"></div>

<p>Ghế chọn: <b id="seatText">Chưa chọn</b></p>
<p>Tổng tiền: <b id="tongTien">0đ</b></p>

<!-- CHỈ 100% -->
<input type="hidden" name="hinh_thuc_tt" value="100">

<div class="payment-box">
💳 Thanh toán 100% giá trị đơn hàng
</div>

<input type="text" name="ten" placeholder="Họ tên"
value="<?= $isLogin ? $user['Ten_khach_hang'] : '' ?>" required>

<input type="text" name="sdt" placeholder="Số điện thoại"
value="<?= $isLogin ? $user['So_dien_thoai'] : '' ?>" required>

<input type="email" name="email" placeholder="Email"
value="<?= $isLogin ? $user['Email'] : '' ?>" required>

<button class="btn">Xác nhận đặt chỗ</button>

</form>

</div>
</div>

<div class="footer">
© 2026 Carpe Diem Workshop
</div>

<script src="assets/script.js"></script>

</body>
</html>