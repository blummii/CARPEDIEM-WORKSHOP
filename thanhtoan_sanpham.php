<?php
session_start();
include("config/db.php");

if (!isset($_SESSION['checkout_product']['ma_don_hang'])) {
    header('Location: index.php#cua-hang');
    exit();
}

$maDonSession = (string)($_SESSION['checkout_product']['ma_don_hang'] ?? '');
$maDonGet = trim((string)($_GET['don'] ?? ''));
$maDon = $maDonGet !== '' ? $maDonGet : $maDonSession;

if ($maDon === '' || $maDon !== $maDonSession) {
    header('Location: index.php#cua-hang');
    exit();
}

$stOrder = $conn->prepare("
    SELECT dh.Ma_don_hang, dh.Tong_tien, dh.Trang_thai, dh.Thoi_gian_tao,
           kh.Ten_khach_hang, kh.Email
    FROM DonHang dh
    LEFT JOIN KhachHang kh ON kh.Ma_khach_hang = dh.Ma_khach_hang
    WHERE dh.Ma_don_hang = ?
    LIMIT 1
");
$stOrder->bind_param('s', $maDon);
$stOrder->execute();
$order = $stOrder->get_result()->fetch_assoc();

if (!$order) {
    unset($_SESSION['checkout_product']);
    echo "<script>alert('Không tìm thấy đơn hàng.');location='index.php#cua-hang';</script>";
    exit();
}

$stItems = $conn->prepare("
    SELECT c.Ma_san_pham, c.So_luong, c.Don_gia, c.Thanh_tien, s.Ten_san_pham
    FROM ChiTietDonHang c
    JOIN SanPham s ON s.Ma_san_pham = c.Ma_san_pham
    WHERE c.Ma_don_hang = ?
    ORDER BY c.Ma_san_pham
");
$stItems->bind_param('s', $maDon);
$stItems->execute();
$items = [];
$res = $stItems->get_result();
while ($r = $res->fetch_assoc()) {
    $items[] = $r;
}

$bank = "VCB";
$stk = "1041501109";
$tenTK = "CARPE DIEM";
$qr = "https://img.vietqr.io/image/"
    . $bank . "-" . $stk . "-compact2.png"
    . "?amount=" . (int)$order['Tong_tien']
    . "&addInfo=" . urlencode($maDon)
    . "&accountName=" . urlencode($tenTK);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Thanh toán đơn hàng</title>
<style>
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#fff5f7}
.wrap{max-width:700px;margin:40px auto;background:#fff;border-radius:20px;padding:35px;box-shadow:0 15px 40px rgba(0,0,0,.08)}
h1{margin-top:0;color:#d4a5a5;text-align:center}
.box{background:#fafafa;padding:18px;border-radius:14px;margin-bottom:20px;line-height:1.8}
.qr{text-align:center}
.qr img{width:300px;max-width:100%;border:1px solid #eee;border-radius:16px;padding:10px;background:#fff}
.money{font-size:32px;color:#842029;font-weight:bold;margin-top:15px}
.note{margin-top:10px;color:#666}
.btn{display:block;width:100%;padding:15px;border:none;border-radius:12px;background:#f8d7da;font-size:16px;font-weight:bold;cursor:pointer;margin-top:25px}
.btn:hover{opacity:.9}
.small{color:#888;font-size:14px}
input{width:100%;padding:12px;margin:15px 0;border:1px solid #ddd;border-radius:10px}
.item-list{margin:8px 0 0 0;padding-left:18px}
</style>
</head>
<body>
<div class="wrap">
<h1>💳 Thanh toán đơn hàng</h1>

<div class="box">
  <b>Mã đơn hàng:</b> <?= htmlspecialchars($maDon) ?><br>
  <b>Khách hàng:</b> <?= htmlspecialchars((string)($order['Ten_khach_hang'] ?? $_SESSION['checkout_product']['ten_kh'] ?? '')) ?><br>
  <b>Email:</b> <?= htmlspecialchars((string)($order['Email'] ?? $_SESSION['checkout_product']['email'] ?? '')) ?><br>
  <b>Sản phẩm:</b>
  <ul class="item-list">
    <?php foreach ($items as $it): ?>
      <li><?= htmlspecialchars((string)$it['Ten_san_pham']) ?> x <?= (int)$it['So_luong'] ?> (<?= number_format((float)$it['Thanh_tien'], 0, ',', '.') ?>đ)</li>
    <?php endforeach; ?>
  </ul>
  <b>Tổng đơn:</b> <?= number_format((float)$order['Tong_tien'], 0, ',', '.') ?>đ<br>
  <b>Thanh toán:</b> 100%
</div>

<?php if ((string)$order['Trang_thai'] !== 'pending'): ?>
  <div class="box">Đơn hàng này đã được xử lý thanh toán trước đó.</div>
  <a href="index.php#cua-hang" style="display:inline-block;margin-top:10px;">Quay lại cửa hàng</a>
<?php else: ?>
<div class="qr">
  <img src="<?= htmlspecialchars($qr, ENT_QUOTES, 'UTF-8') ?>" alt="QR thanh toán sản phẩm">
  <div class="money"><?= number_format((float)$order['Tong_tien'], 0, ',', '.') ?>đ</div>
  <div class="note">Ngân hàng <b>VCB</b> - STK <b>1041501109</b><br>Nội dung chuyển khoản: <b><?= htmlspecialchars($maDon) ?></b></div>
  <p class="small">Sau khi chuyển khoản xong, bấm nút bên dưới để hoàn tất đơn hàng.</p>
</div>

<form action="xacnhan_thanhtoan_sanpham.php" method="POST">
  <input type="email" name="email_xacnhan" required placeholder="Nhập email nhận xác nhận" value="<?= htmlspecialchars((string)($_SESSION['checkout_product']['email'] ?? '')) ?>">
  <button class="btn" type="submit">Tôi đã thanh toán</button>
</form>
<?php endif; ?>
</div>
</body>
</html>
