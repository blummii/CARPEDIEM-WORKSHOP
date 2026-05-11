<?php
session_start();
include("config/db.php");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php#cua-hang');
    exit();
}

if (!isset($_SESSION['user']['Ma_khach_hang'])) {
    echo "<script>alert('Vui lòng đăng nhập để mua sản phẩm.');location='dangnhap.php';</script>";
    exit();
}

function gen_order_code(mysqli $conn): string
{
    $res = $conn->query("SELECT MAX(Ma_don_hang) AS m FROM DonHang WHERE Ma_don_hang LIKE 'DH%'");
    $m = null;
    if ($res) {
        $row = $res->fetch_assoc();
        $m = $row['m'] ?? null;
    }
    if (!$m) {
        return 'DH001';
    }
    $num = (int)substr((string)$m, 2);
    return 'DH' . str_pad((string)($num + 1), 3, '0', STR_PAD_LEFT);
}

$maSp = trim((string)($_POST['ma_sp'] ?? ''));
$qty = max(1, (int)($_POST['qty'] ?? 1));
$maKh = (string)($_SESSION['user']['Ma_khach_hang'] ?? '');

if ($maSp === '' || $maKh === '') {
    echo "<script>alert('Thiếu thông tin mua hàng.');history.back();</script>";
    exit();
}

$sql = "
    SELECT s.Ma_san_pham, s.Ten_san_pham, s.Gia, COALESCE(t.So_luong, 0) AS So_luong
    FROM SanPham s
    LEFT JOIN TonKho t ON t.Ma_san_pham = s.Ma_san_pham
    WHERE s.Ma_san_pham = ?
      AND (
        s.Trang_thai IS NULL
        OR TRIM(IFNULL(s.Trang_thai,'')) = ''
        OR LOWER(TRIM(IFNULL(s.Trang_thai,''))) IN ('hiển thị', 'hien thi', 'active')
      )
    LIMIT 1
";
$st = $conn->prepare($sql);
$st->bind_param('s', $maSp);
$st->execute();
$product = $st->get_result()->fetch_assoc();

if (!$product) {
    echo "<script>alert('Sản phẩm không tồn tại hoặc đang ẩn.');location='index.php#cua-hang';</script>";
    exit();
}

$stock = (int)($product['So_luong'] ?? 0);
if ($stock < $qty) {
    echo "<script>alert('Sản phẩm không đủ tồn kho để mua.');location='index.php#cua-hang';</script>";
    exit();
}

$gia = (float)($product['Gia'] ?? 0);
$thanhTien = $gia * $qty;
$maDon = gen_order_code($conn);

$conn->begin_transaction();
try {
    $trangThai = 'pending';
    $insOrder = $conn->prepare("
        INSERT INTO DonHang (Ma_don_hang, Ma_khach_hang, Ma_nhan_vien, Tong_tien, Trang_thai, Thoi_gian_tao)
        VALUES (?, ?, NULL, ?, ?, NOW())
    ");
    $insOrder->bind_param('ssds', $maDon, $maKh, $thanhTien, $trangThai);
    $insOrder->execute();

    $insDetail = $conn->prepare("
        INSERT INTO ChiTietDonHang (Ma_don_hang, Ma_san_pham, So_luong, Don_gia, Thanh_tien)
        VALUES (?, ?, ?, ?, ?)
    ");
    $insDetail->bind_param('ssidd', $maDon, $maSp, $qty, $gia, $thanhTien);
    $insDetail->execute();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    echo "<script>alert('Không thể tạo đơn hàng: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "');history.back();</script>";
    exit();
}

$_SESSION['checkout_product'] = [
    'ma_don_hang' => $maDon,
    'ma_kh' => $maKh,
    'ten_kh' => (string)($_SESSION['user']['Ten_khach_hang'] ?? ''),
    'email' => (string)($_SESSION['user']['Email'] ?? ''),
];

header('Location: thanhtoan_sanpham.php?don=' . urlencode($maDon));
exit();
?>
