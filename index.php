<?php
session_start();
include("config/db.php");
require_once __DIR__ . '/includes/workshop_date.php';

/*
==================================================
INDEX.PHP BẢN ĐẸP - GIỮ ẢNH + GIAO DIỆN XỊN
✔ Hero banner ảnh lớn
✔ Card workshop có ảnh
✔ Popup chọn ghế
✔ Hiển thị workshop đã đăng ký
✔ Giữ style đẹp như web thật
==================================================
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

/* ================= TOPBAR ================= */
.topbar{
    background:white;
    padding:16px 6%;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 4px 18px rgba(0,0,0,.06);
    position:sticky;
    top:0;
    z-index:100;
}

.logo{
    font-size:24px;
    font-weight:bold;
    color:#e08ea0;
}

.topbar a{
    text-decoration:none;
    margin-left:12px;
    color:#333;
    font-weight:600;
}

/* ================= HERO ================= */
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
    font-size:54px;
    margin-bottom:15px;
}

.hero p{
    font-size:20px;
    width:600px;
    max-width:100%;
    line-height:1.6;
}

.hero-btn{
    margin-top:25px;
    display:inline-block;
    padding:14px 28px;
    background:#f8c7d2;
    color:#333;
    border-radius:30px;
    text-decoration:none;
    font-weight:bold;
}

/* ================= MAIN ================= */
.container{
    width:90%;
    max-width:1300px;
    margin:auto;
    padding:50px 0;
}

.title{
    font-size:34px;
    margin-bottom:30px;
    color:#d67d93;
}

/* ================= BOOKED ================= */
.booked{
    background:white;
    padding:30px;
    border-radius:22px;
    box-shadow:0 8px 28px rgba(0,0,0,.06);
    margin-bottom:45px;
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

/* ================= CARD ================= */
.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(320px,1fr));
    gap:28px;
}

.card{
    background:white;
    border-radius:22px;
    overflow:hidden;
    box-shadow:0 10px 25px rgba(0,0,0,.07);
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
    margin-bottom:12px;
    font-size:24px;
}

.desc{
    color:#666;
    line-height:1.6;
    min-height:70px;
}

.price{
    color:#e07f96;
    font-size:22px;
    font-weight:bold;
    margin:14px 0;
}

.slot{
    background:#fff4f7;
    padding:14px;
    border-radius:14px;
    margin-top:12px;
}

.btn{
    margin-top:14px;
    background:#f7c4d0;
    border:none;
    padding:12px 18px;
    border-radius:14px;
    cursor:pointer;
    font-weight:bold;
    width:100%;
}

/* ================= POPUP ================= */
.popup{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.5);
    display:none;
    justify-content:center;
    align-items:center;
    z-index:999;
}

.popup-content{
    background:white;
    width:760px;
    max-width:96%;
    border-radius:22px;
    padding:28px;
}

.close{
    float:right;
    font-size:26px;
    cursor:pointer;
}

.seat-grid{
    display:grid;
    grid-template-columns:repeat(5,1fr);
    gap:12px;
    margin:20px 0;
}

.seat{
    padding:12px;
    text-align:center;
    border-radius:12px;
    font-weight:bold;
    cursor:pointer;
}

.available{background:#ececec;}
.selected{background:#f8c7d2;}
.occupied{background:#777;color:white;cursor:not-allowed;}

input[type=text],
input[type=email]{
    width:100%;
    padding:12px;
    border:1px solid #ddd;
    border-radius:12px;
    margin-top:10px;
}

.footer{
    margin-top:60px;
    padding:25px;
    text-align:center;
    background:white;
    color:#777;
}
</style>
</head>
<body>

<!-- TOPBAR -->
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
<p>Khám phá nghệ thuật, tinh dầu, sáng tạo và chữa lành cùng Carpe Diem.</p>
<a href="#workshop" class="hero-btn">Khám phá ngay</a>
</div>
</section>

<div class="container">

<?php if($isLogin): ?>

<div class="booked">
<h2 class="title">Workshop bạn đã đăng ký</h2>

<table>
<tr>
<th>Tên workshop</th>
<th>Ngày</th>
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

$get = $conn->query("SELECT So_ghe FROM chitietghe WHERE Ma_dang_ky='$madk' ORDER BY So_ghe ASC");
while($x = $get->fetch_assoc()){
$ghe[] = $x['So_ghe'];
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
<td colspan="4">Chưa có workshop nào</td>
</tr>

<?php endif; ?>
</table>
</div>

<?php endif; ?>

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

<!-- POPUP -->
<div class="popup" id="popup">

<div class="popup-content">

<span class="close" onclick="closePopup()">×</span>

<h2>Đặt workshop</h2>

<form action="xuly_dangky.php" method="POST">

<input type="hidden" name="id_lich" id="id_lich">
<input type="hidden" name="selected_seats" id="selected_seats">

<div class="seat-grid" id="seat-grid"></div>

<p>Ghế chọn: <b id="seatText">Chưa chọn</b></p>
<p>Tạm tính: <b id="tongTien">0đ</b></p>

<label><input type="radio" name="hinh_thuc_tt" value="50" checked> Thanh toán 50%</label>
<label><input type="radio" name="hinh_thuc_tt" value="100"> Thanh toán 100%</label>

<input type="text" name="ten" placeholder="Họ tên"
value="<?= $isLogin ? $user['Ten_khach_hang'] : '' ?>" required>

<input type="text" name="sdt" placeholder="SĐT"
value="<?= $isLogin ? $user['So_dien_thoai'] : '' ?>" required>

<input type="email" name="email" placeholder="Email"
value="<?= $isLogin ? $user['Email'] : '' ?>" required>

<button class="btn">Tiếp tục thanh toán</button>

</form>

</div>
</div>

<div class="footer">
© 2026 Carpe Diem Workshop
</div>

<script src="assets/script.js"></script>

</body>
</html>