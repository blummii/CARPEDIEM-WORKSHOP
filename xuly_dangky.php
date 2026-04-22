<?php
session_start();
include("config/db.php");

/*
==================================================
PART A - XULY_DANGKY.PHP
✔ Nhận dữ liệu từ form index
✔ Kiểm tra đăng nhập
✔ Kiểm tra ghế
✔ Tính tiền
✔ Lưu SESSION booking đầy đủ
✔ Có email để gửi mail sau này
✔ Chuyển sang trang thanh toán
==================================================
*/

if (!isset($_SESSION['user'])) {
    echo "
    <script>
    alert('Vui lòng đăng nhập để đặt workshop!');
    location='dangnhap.php';
    </script>
    ";
    exit();
}

$user = $_SESSION['user'];

$ma_kh = $user['Ma_khach_hang'];

/* ===============================
   NHẬN FORM
================================= */

$ma_lich = $_POST['id_lich'] ?? '';
$seats = $_POST['selected_seats'] ?? '';
$ten = trim($_POST['ten'] ?? '');
$sdt = trim($_POST['sdt'] ?? '');
$email = trim($_POST['email'] ?? '');
$hinhthuc = $_POST['hinh_thuc_tt'] ?? 50;

/* ===============================
   VALIDATE
================================= */

if ($ma_lich == '') {
    die("<script>alert('Thiếu mã lịch workshop');history.back();</script>");
}

if ($seats == '') {
    die("<script>alert('Vui lòng chọn ghế');history.back();</script>");
}

if ($ten == '' || $sdt == '' || $email == '') {
    die("<script>alert('Vui lòng nhập đầy đủ thông tin');history.back();</script>");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("<script>alert('Email không hợp lệ');history.back();</script>");
}

/* ===============================
   XỬ LÝ GHẾ
================================= */

$arr = explode(",", $seats);
$arr = array_filter($arr);

$soluong = count($arr);

if ($soluong <= 0) {
    die("<script>alert('Chưa chọn ghế');history.back();</script>");
}

/* ===============================
   LẤY GIÁ WORKSHOP
================================= */

$sql = "
SELECT c.Gia, c.Ten_chu_de
FROM lichworkshop l
JOIN chudeworkshop c ON l.Ma_chu_de = c.Ma_chu_de
WHERE l.Ma_lich_workshop = '$ma_lich'
LIMIT 1
";

$rs = $conn->query($sql);

if (!$rs || $rs->num_rows <= 0) {
    die("<script>alert('Workshop không tồn tại');history.back();</script>");
}

$row = $rs->fetch_assoc();

$gia = (float)$row['Gia'];
$ten_workshop = $row['Ten_chu_de'];

$tong = $gia * $soluong;

/* ===============================
   THANH TOÁN 50 / 100
================================= */

$hinhthuc = (int)$hinhthuc;

if ($hinhthuc == 50) {
    $thanhtoan = $tong * 0.5;
} else {
    $hinhthuc = 100;
    $thanhtoan = $tong;
}

/* ===============================
   TẠO MÃ ĐĂNG KÝ
================================= */

$madk = "DK" . rand(1000,9999);

/* ===============================
   LƯU SESSION BOOKING
================================= */

$_SESSION['booking'] = [

    'Ma_dang_ky' => $madk,
    'Ma_lich' => $ma_lich,
    'Ma_kh' => $ma_kh,

    'Ten_khach' => $ten,
    'So_dien_thoai' => $sdt,
    'email' => $email,

    'Ten_workshop' => $ten_workshop,

    'Seats' => $arr,
    'So_luong' => $soluong,

    'Tong_tien' => $tong,
    'Thanh_toan' => $thanhtoan,

    'Hinh_thuc' => $hinhthuc
];

/* ===============================
   CHUYỂN TRANG THANH TOÁN
================================= */

header("Location: thanhtoan.php");
exit();
?>