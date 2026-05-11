<?php
session_start();
include("config/db.php");

if (!isset($_SESSION['user']['Ma_khach_hang'])) {
    echo "<script>alert('Vui lòng đăng nhập để thanh toán đơn hàng.');location='dangnhap.php';</script>";
    exit();
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart']) || $_SESSION['cart'] === []) {
    echo "<script>alert('Giỏ hàng đang trống.');location='giohang.php';</script>";
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
    if (!$m) return 'DH001';
    $num = (int)substr((string)$m, 2);
    return 'DH' . str_pad((string)($num + 1), 3, '0', STR_PAD_LEFT);
}

$cart = $_SESSION['cart'];
$ids = array_keys($cart);
$in = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('s', count($ids));

$st = $conn->prepare("
    SELECT s.Ma_san_pham, s.Ten_san_pham, s.Gia, COALESCE(t.So_luong, 0) AS So_luong
    FROM SanPham s
    LEFT JOIN TonKho t ON t.Ma_san_pham = s.Ma_san_pham
    WHERE s.Ma_san_pham IN ($in)
      AND (
        s.Trang_thai IS NULL
        OR TRIM(IFNULL(s.Trang_thai,'')) = ''
        OR LOWER(TRIM(IFNULL(s.Trang_thai,''))) IN ('hiển thị', 'hien thi', 'active')
      )
");
$st->bind_param($types, ...$ids);
$st->execute();
$res = $st->get_result();

$products = [];
while ($r = $res->fetch_assoc()) {
    $products[(string)$r['Ma_san_pham']] = $r;
}

if ($products === []) {
    echo "<script>alert('Không có sản phẩm hợp lệ để tạo đơn.');location='giohang.php';</script>";
    exit();
}

$pairs = [];
$tong = 0.0;
foreach ($cart as $ma => $qtyRaw) {
    $ma = (string)$ma;
    $qty = max(1, (int)$qtyRaw);
    if (!isset($products[$ma])) continue;
    $stock = (int)($products[$ma]['So_luong'] ?? 0);
    if ($stock < $qty) {
        echo "<script>alert('Sản phẩm " . htmlspecialchars((string)$products[$ma]['Ten_san_pham'], ENT_QUOTES, 'UTF-8') . " không đủ tồn kho.');location='giohang.php';</script>";
        exit();
    }
    $gia = (float)$products[$ma]['Gia'];
    $line = $gia * $qty;
    $tong += $line;
    $pairs[] = [$ma, $qty, $gia, $line];
}

if ($pairs === []) {
    echo "<script>alert('Giỏ hàng không có sản phẩm hợp lệ.');location='giohang.php';</script>";
    exit();
}

$maDon = gen_order_code($conn);
$maKh = (string)$_SESSION['user']['Ma_khach_hang'];

$conn->begin_transaction();
try {
    $status = 'pending';
    $insOrder = $conn->prepare("
        INSERT INTO DonHang (Ma_don_hang, Ma_khach_hang, Ma_nhan_vien, Tong_tien, Trang_thai, Thoi_gian_tao)
        VALUES (?, ?, NULL, ?, ?, NOW())
    ");
    $insOrder->bind_param('ssds', $maDon, $maKh, $tong, $status);
    $insOrder->execute();

    $insItem = $conn->prepare("
        INSERT INTO ChiTietDonHang (Ma_don_hang, Ma_san_pham, So_luong, Don_gia, Thanh_tien)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($pairs as $p) {
        [$maSp, $qty, $gia, $line] = $p;
        $insItem->bind_param('ssidd', $maDon, $maSp, $qty, $gia, $line);
        $insItem->execute();
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    echo "<script>alert('Không thể tạo đơn hàng: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "');location='giohang.php';</script>";
    exit();
}

$_SESSION['checkout_product'] = [
    'ma_don_hang' => $maDon,
    'ma_kh' => $maKh,
    'ten_kh' => (string)($_SESSION['user']['Ten_khach_hang'] ?? ''),
    'email' => (string)($_SESSION['user']['Email'] ?? ''),
];
$_SESSION['cart'] = [];

header('Location: thanhtoan_sanpham.php?don=' . urlencode($maDon));
exit();
?>
