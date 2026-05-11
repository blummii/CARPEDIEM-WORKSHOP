<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include("config/db.php");
require_once __DIR__ . '/includes/workshop_date.php';

$isLogin = isset($_SESSION['user']);
$user = $isLogin ? $_SESSION['user'] : null;

/**
 * URL ảnh công khai: đường dẫn trong DB (vd. assets/images/products/...) hoặc mặc định trong admin/assets/images.
 */
function cd_public_img(?string $path, string $fallback = 'admin/assets/images/default-product.jpg'): string
{
    $path = $path !== null ? trim($path) : '';
    if ($path !== '' && preg_match('#^https?://#i', $path)) {
        return htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
    }
    if ($path !== '') {
        return htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
    }

    return htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8');
}

/** Tên file (không ext) không dùng làm ảnh sản phẩm ngẫu nhiên (banner, placeholder…). */
function cd_shop_pool_exclude_basename(string $baseLower): bool
{
    foreach (['banner', 'hero', 'default-product'] as $p) {
        if ($baseLower === $p || strncmp($baseLower, $p . '.', strlen($p) + 1) === 0) {
            return true;
        }
        if (strncmp($baseLower, $p, strlen($p)) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * Gom mọi ảnh trong admin/assets/images (và thư mục con, vd. products/) để gán “ngẫu nhiên” theo mã SP.
 */
function cd_build_shop_image_pool(string $rootAbs, string $webPrefix): array
{
    $pool = [];
    if (!is_dir($rootAbs)) {
        return $pool;
    }
    $rootNorm = rtrim(str_replace('\\', '/', $rootAbs), '/');
    $webPrefix = rtrim(str_replace('\\', '/', $webPrefix), '/');
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootAbs, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }
        $ext = strtolower($fileInfo->getExtension());
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            continue;
        }
        $base = strtolower(pathinfo($fileInfo->getFilename(), PATHINFO_FILENAME));
        if (cd_shop_pool_exclude_basename($base)) {
            continue;
        }
        $fullNorm = str_replace('\\', '/', $fileInfo->getPathname());
        if (strncmp($fullNorm, $rootNorm, strlen($rootNorm)) !== 0) {
            continue;
        }
        $suffix = ltrim(substr($fullNorm, strlen($rootNorm)), '/');
        $pool[] = $suffix === '' ? $webPrefix : $webPrefix . '/' . $suffix;
    }
    sort($pool, SORT_STRING);

    return $pool;
}

/** Một ảnh trong pool cho từng mã SP (ổn định giữa các lần tải trang). */
function cd_shop_image_for_product(string $maSanPham, array $pool): string
{
    if ($pool === []) {
        return 'admin/assets/images/default-product.jpg';
    }
    $idx = abs(crc32($maSanPham)) % count($pool);

    return $pool[$idx];
}

$heroCandidates = [
    __DIR__ . '/admin/assets/images/banner.jpg',
    __DIR__ . '/admin/assets/images/banner.JPG',
    __DIR__ . '/admin/assets/images/hero.jpg',
    __DIR__ . '/assets/images/banner.JPG',
    __DIR__ . '/assets/images/banner.jpg',
];
$heroUrl = 'admin/assets/images/banner.jpg';
foreach ($heroCandidates as $f) {
    if (is_file($f)) {
        $heroUrl = str_replace('\\', '/', substr($f, strlen(__DIR__) + 1));
        break;
    }
}

$shopCategories = [];
$shopProducts = [];
if (isset($conn) && $conn instanceof mysqli) {
    $rsCat = @$conn->query('SELECT Ma_danh_muc, Ten_danh_muc FROM DanhMucSanPham ORDER BY Ten_danh_muc');
    if ($rsCat) {
        while ($row = $rsCat->fetch_assoc()) {
            $shopCategories[] = $row;
        }
    }
    $rsShop = @$conn->query("
        SELECT s.Ma_san_pham, s.Ma_danh_muc, s.Ten_san_pham, s.Gia, s.Mo_ta, s.Hinh_anh,
               c.Ten_danh_muc AS ten_loai, COALESCE(t.So_luong, 0) AS so_luong
        FROM SanPham s
        LEFT JOIN DanhMucSanPham c ON s.Ma_danh_muc = c.Ma_danh_muc
        LEFT JOIN TonKho t ON s.Ma_san_pham = t.Ma_san_pham
        WHERE (
            s.Trang_thai IS NULL
            OR TRIM(IFNULL(s.Trang_thai,'')) = ''
            OR LOWER(TRIM(IFNULL(s.Trang_thai,''))) IN ('hiển thị', 'hien thi', 'active')
        )
        ORDER BY c.Ten_danh_muc, s.Ten_san_pham
    ");
    if ($rsShop) {
        while ($row = $rsShop->fetch_assoc()) {
            $shopProducts[] = $row;
        }
    }
}

$shopImagePool = cd_build_shop_image_pool(
    __DIR__ . '/admin/assets/images',
    'admin/assets/images'
);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Carpe Diem Workshop</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="assets/style.css">

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:'Montserrat', Arial, Helvetica, sans-serif;
    background:#f5f5f5;
    color:#222;
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
    font-size:28px;
    font-weight:700;
    color:#F4B0C1;
    letter-spacing:.12em;
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

/* ================= SUB NAV ================= */

.subnav{
    width:100%;
    background:#EFEBE9;
    padding:16px 6%;
    display:flex;
    justify-content:center;
    align-items:center;
    gap:8px 36px;
    flex-wrap:wrap;
    border-bottom:1px solid rgba(0,0,0,.06);
    position:sticky;
    top:70px;
    z-index:998;
}

.subnav a{
    text-decoration:none;
    color:#222;
    font-weight:600;
    font-size:12px;
    letter-spacing:.1em;
    text-transform:uppercase;
    padding:12px 22px;
    border-radius:10px;
    transition:background .2s, color .2s;
}

.subnav a:hover{
    background:rgba(0,0,0,.05);
}

.subnav a.active{
    background:rgba(0,0,0,.1);
    color:#111;
    font-weight:700;
}

#workshop-section,
#cua-hang,
#ve-chung-toi{
    scroll-margin-top:160px;
}

/* ================= HERO ================= */

.hero{
    height:520px;
    background:url('<?= htmlspecialchars($heroUrl, ENT_QUOTES, 'UTF-8') ?>') center/cover no-repeat;
    position:relative;
    display:flex;
    align-items:center;
}

.hero::before{
    content:'';
    position:absolute;
    inset:0;
    background:rgba(0,0,0,.42);
}

.hero-content{
    position:relative;
    z-index:2;
    width:90%;
    max-width:900px;
    margin:auto;
    color:white;
    text-align:center;
}

.hero-content h1{
    font-size:clamp(32px, 5vw, 56px);
    margin-bottom:18px;
    font-weight:700;
    line-height:1.15;
}

.hero-content p{
    max-width:640px;
    margin-left:auto;
    margin-right:auto;
    font-size:clamp(16px, 2vw, 20px);
    line-height:1.75;
    opacity:.95;
}

.hero-btn{
    margin-top:30px;
    display:inline-block;
    background:#F4B0C1;
    color:#fff;
    text-decoration:none;
    padding:16px 34px;
    border-radius:40px;
    font-weight:700;
    box-shadow:0 4px 14px rgba(0,0,0,.12);
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

/* ================= SHOP (CỬA HÀNG) ================= */

.shop-band{
    background:#f5f5f5;
    padding:40px 0 64px;
}

.shop-band .section-title{
    color:#222;
}

.shop-band .container{
    max-width:1320px;
    width:min(1320px, 94%);
    margin:0 auto;
    display:block;
}

.shop-filters-label{
    font-size:12px;
    font-weight:700;
    letter-spacing:.12em;
    text-transform:uppercase;
    color:#888;
    margin-bottom:10px;
}

.cat-pills{
    display:flex;
    flex-wrap:wrap;
    gap:12px;
    margin-bottom:28px;
    align-items:center;
}

.cat-pill{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:96px;
    height:40px;
    padding:0 20px;
    border-radius:999px;
    border:1px solid #e0d8d4;
    background:#fff;
    cursor:pointer;
    font-weight:600;
    font-size:13px;
    font-family:inherit;
    color:#333;
    transition:background .2s, color .2s, border-color .2s;
    white-space:nowrap;
    line-height:1;
    writing-mode:horizontal-tb;
    text-orientation:mixed;
    flex:0 0 auto;
}

.cat-pill:hover{
    border-color:#c98b9b;
    color:#8B0000;
}

.cat-pill.active{
    background:#333;
    border-color:#333;
    color:#fff;
}

.product-grid{
    display:flex;
    gap:24px;
    align-items:stretch;
    overflow-x:auto;
    overflow-y:hidden;
    scroll-behavior:smooth;
    padding-bottom:8px;
}

.product-grid::-webkit-scrollbar{
    height:8px;
}

.product-grid::-webkit-scrollbar-thumb{
    background:#d3d3d3;
    border-radius:20px;
}

.product-card{
    background:#fff;
    border-radius:14px;
    overflow:hidden;
    box-shadow:0 4px 20px rgba(0,0,0,.06);
    border:1px solid rgba(0,0,0,.04);
    display:flex;
    flex-direction:column;
    transition:transform .2s, box-shadow .2s;
    min-width:0;
    flex:0 0 calc((100% - 72px) / 4);
    max-width:calc((100% - 72px) / 4);
}

.product-card:hover{
    transform:translateY(-3px);
    box-shadow:0 10px 28px rgba(0,0,0,.09);
}

.product-card img{
    width:100%;
    aspect-ratio:1;
    height:auto;
    object-fit:cover;
    background:#f0ebe8;
}

.product-card-body{
    padding:18px 18px 20px;
    flex:1;
    display:flex;
    flex-direction:column;
}

.product-card-title{
    font-size:16px;
    font-weight:700;
    color:#111;
    margin-bottom:6px;
    line-height:1.35;
    word-break:normal;
    overflow-wrap:anywhere;
}

.product-card-sub{
    font-size:13px;
    color:#777;
    line-height:1.45;
    flex:1;
    display:-webkit-box;
    -webkit-line-clamp:2;
    -webkit-box-orient:vertical;
    overflow:hidden;
    min-height:38px;
}

.product-card-price{
    font-size:18px;
    font-weight:700;
    color:#111;
    margin:14px 0 16px;
}

.product-card-actions{
    margin-top:auto;
}

.btn-shop-full{
    width:100%;
    border:none;
    padding:14px 16px;
    border-radius:12px;
    font-weight:700;
    font-size:14px;
    font-family:inherit;
    cursor:pointer;
    background:#F4B0C1;
    color:#222;
    transition:opacity .2s, filter .2s;
    white-space:nowrap;
    writing-mode:horizontal-tb;
    text-orientation:mixed;
}

.btn-shop-full[disabled]{
    cursor:not-allowed;
    background:#e5e5e5;
    color:#666;
    filter:none;
}

.btn-shop-full:hover{
    opacity:.95;
    filter:brightness(1.02);
}

@media(max-width:1200px){
.product-grid{
    gap:18px;
}
.product-card{
    flex-basis:calc((100% - 36px) / 3);
    max-width:calc((100% - 36px) / 3);
}
}

@media(max-width:900px){
.product-card{
    flex-basis:calc((100% - 18px) / 2);
    max-width:calc((100% - 18px) / 2);
}
}

@media(max-width:520px){
.cat-pills{
    overflow-x:auto;
    flex-wrap:nowrap;
    padding-bottom:6px;
}
.product-card{
    flex-basis:88%;
    max-width:88%;
}
}

/* ================= ABOUT ================= */

.about-band{
    background:#fff;
    padding:56px 0 72px;
}

.about-inner{
    max-width:720px;
    margin:0 auto;
    text-align:center;
    color:#555;
    line-height:1.85;
    font-size:17px;
}

.about-inner h2{
    font-size:36px;
    color:#222;
    margin-bottom:20px;
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

<a href="giohang.php">
Giỏ hàng
</a>

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

<a href="giohang.php">
Giỏ hàng
</a>

<?php endif; ?>

</div>
</div>

<!-- ================= SUB NAV ================= -->

<nav class="subnav" aria-label="Điều hướng chính">
<a href="#workshop-section" data-nav="workshop">Đặt lịch workshop</a>
<a href="#cua-hang" data-nav="shop">Cửa hàng mua sắm</a>
<a href="#ve-chung-toi" data-nav="about">Về chúng tôi</a>
</nav>

<!-- ================= HERO ================= -->

<section class="hero">

<div class="hero-content">

<h1>
Cửa Hàng Nến &amp; Tinh Dầu Cao Cấp
</h1>

<p>
Khám phá bộ sưu tập nến thơm và tinh dầu trị liệu tinh tế, được làm thủ công tại Carpe Diem.
</p>

<a href="#cua-hang" class="hero-btn">
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

$img = cd_public_img($row['Hinh_anh'] ?? null, 'admin/assets/images/default-product.jpg');

?>

<div class="card">

<img src="<?= $img ?>" alt="<?= htmlspecialchars($row['Ten_chu_de'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

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

<!-- ================= CỬA HÀNG MUA SẮM ================= -->

<section class="shop-band" id="cua-hang">
<div class="container" style="padding-top:0;padding-bottom:0;">

<h2 class="section-title" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;">Cửa hàng mua sắm</h2>

<?php if (count($shopCategories) > 0): ?>
<div class="shop-filters-label">Danh mục</div>
<div class="cat-pills" role="tablist" aria-label="Lọc danh mục">
<button type="button" class="cat-pill active" data-dm="">Tất cả</button>
<?php foreach ($shopCategories as $cat): ?>
<button type="button" class="cat-pill" data-dm="<?= htmlspecialchars($cat['Ma_danh_muc'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
<?= htmlspecialchars($cat['Ten_danh_muc'] ?? '', ENT_QUOTES, 'UTF-8') ?>
</button>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (count($shopProducts) === 0): ?>
<p style="color:#666;font-size:17px;">Hiện chưa có sản phẩm trong cửa hàng. Vui lòng quay lại sau.</p>
<?php else: ?>
<div class="product-grid" id="product-grid">
<?php foreach ($shopProducts as $p): ?>
<?php
    $dm = (string)($p['Ma_danh_muc'] ?? '');
    $maSp = (string)($p['Ma_san_pham'] ?? '');
    $imgSp = htmlspecialchars(cd_shop_image_for_product($maSp, $shopImagePool), ENT_QUOTES, 'UTF-8');
    $soLuongSp = (int)($p['so_luong'] ?? 0);
    $sub = trim((string)($p['ten_loai'] ?? ''));
    if ($sub === '') {
        $rawMo = trim((string)($p['Mo_ta'] ?? ''));
        $sub = function_exists('mb_substr') ? mb_substr($rawMo, 0, 72) : substr($rawMo, 0, 72);
    }
    $cta = $soLuongSp > 0 ? 'Xem chi tiết' : 'Hết hàng';
?>
<article class="product-card" data-dm="<?= htmlspecialchars($dm, ENT_QUOTES, 'UTF-8') ?>">
<img src="<?= $imgSp ?>" alt="<?= htmlspecialchars($p['Ten_san_pham'] ?? '', ENT_QUOTES, 'UTF-8') ?>" width="400" height="400" loading="lazy">
<div class="product-card-body">
<div class="product-card-title"><?= htmlspecialchars($p['Ten_san_pham'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
<div class="product-card-sub"><?= htmlspecialchars($sub, ENT_QUOTES, 'UTF-8') ?></div>
<div class="product-card-price"><?= number_format((float)($p['Gia'] ?? 0), 0, ',', '.') ?>₫</div>
<div class="product-card-actions">
<?php if ($soLuongSp > 0): ?>
<a href="sanpham_chitiet.php?ma_sp=<?= urlencode($maSp) ?>" class="btn-shop-full" style="display:block;text-align:center;text-decoration:none;"><?= htmlspecialchars($cta, ENT_QUOTES, 'UTF-8') ?></a>
<?php else: ?>
<button type="button" class="btn-shop-full" disabled><?= htmlspecialchars($cta, ENT_QUOTES, 'UTF-8') ?></button>
<?php endif; ?>
</div>
</div>
</article>
<?php endforeach; ?>
</div>
<?php endif; ?>

</div>
</section>

<!-- ================= VỀ CHÚNG TÔI ================= -->

<section class="about-band" id="ve-chung-toi">
<div class="container" style="padding-top:0;padding-bottom:0;">
<div class="about-inner">
<h2>Về chúng tôi</h2>
<p>
Carpe Diem là không gian của nến thơm, tinh dầu và những buổi workshop nhỏ để bạn thư giãn và sáng tạo.
Chúng tôi chọn nguyên liệu tử tế, hướng dẫn tận tình và mong mỗi sản phẩm đều mang lại cảm giác ấm áp cho ngôi nhà của bạn.
</p>
<p style="margin-top:16px;">
Cùng trải nghiệm cửa hàng trực tuyến và đặt lịch workshop để gặp gỡ trực tiếp đội ngũ Carpe Diem.
</p>
</div>
</div>
</section>

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
<script>
(function(){
  function setActiveNav(){
    var hash = (location.hash || '').replace('#','');
    var links = document.querySelectorAll('.subnav a');
    var map = { 'workshop-section':'workshop', 'cua-hang':'shop', 've-chung-toi':'about' };
    var nav = map[hash] || '';
    links.forEach(function(a){
      a.classList.toggle('active', nav && a.getAttribute('data-nav') === nav);
    });
    if(!nav && links.length){
      links.forEach(function(a){ a.classList.remove('active'); });
    }
  }
  document.querySelectorAll('.subnav a').forEach(function(a){
    a.addEventListener('click', function(){ setTimeout(setActiveNav, 0); });
  });
  window.addEventListener('hashchange', setActiveNav);
  setActiveNav();

  document.querySelectorAll('.cat-pill').forEach(function(btn){
    btn.addEventListener('click', function(){
      var self = this;
      var dm = self.getAttribute('data-dm') || '';
      document.querySelectorAll('.cat-pill').forEach(function(b){
        b.classList.toggle('active', b === self);
      });
      document.querySelectorAll('.product-card').forEach(function(card){
        var cdm = card.getAttribute('data-dm') || '';
        card.style.display = (!dm || cdm === dm) ? '' : 'none';
      });
    });
  });

})();
</script>

</body>
</html>