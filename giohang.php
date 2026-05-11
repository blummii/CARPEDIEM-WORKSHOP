<?php
session_start();
include("config/db.php");

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'update' && isset($_POST['qty']) && is_array($_POST['qty'])) {
        foreach ($_POST['qty'] as $ma => $qty) {
            $ma = trim((string)$ma);
            $q = max(0, (int)$qty);
            if ($q === 0) {
                unset($_SESSION['cart'][$ma]);
            } else {
                $_SESSION['cart'][$ma] = $q;
            }
        }
        header('Location: giohang.php');
        exit();
    }
    if ($action === 'remove') {
        $ma = trim((string)($_POST['ma_sp'] ?? ''));
        unset($_SESSION['cart'][$ma]);
        header('Location: giohang.php');
        exit();
    }
}

$cart = $_SESSION['cart'];
$items = [];
$tong = 0.0;

if ($cart !== []) {
    $ids = array_keys($cart);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('s', count($ids));
    $st = $conn->prepare("
        SELECT s.Ma_san_pham, s.Ten_san_pham, s.Gia, COALESCE(t.So_luong, 0) AS So_luong
        FROM SanPham s
        LEFT JOIN TonKho t ON t.Ma_san_pham = s.Ma_san_pham
        WHERE s.Ma_san_pham IN ($in)
    ");
    $st->bind_param($types, ...$ids);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
        $ma = (string)$r['Ma_san_pham'];
        $qty = max(1, (int)($cart[$ma] ?? 1));
        $stock = (int)($r['So_luong'] ?? 0);
        if ($qty > $stock && $stock > 0) {
            $qty = $stock;
            $_SESSION['cart'][$ma] = $qty;
        }
        $line = (float)$r['Gia'] * $qty;
        $tong += $line;
        $items[] = [
            'ma' => $ma,
            'ten' => (string)$r['Ten_san_pham'],
            'gia' => (float)$r['Gia'],
            'qty' => $qty,
            'stock' => $stock,
            'line' => $line
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Giỏ hàng</title>
<style>
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f7f7f7}
.wrap{max-width:1080px;margin:30px auto;padding:0 16px}
.head{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
.head a{text-decoration:none;color:#333}
.card{background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.08);padding:18px}
table{width:100%;border-collapse:collapse}
th,td{padding:12px;border-bottom:1px solid #eee;text-align:left}
input[type=number]{width:90px;padding:8px;border:1px solid #ddd;border-radius:8px}
.btn{border:none;border-radius:10px;padding:10px 14px;font-weight:700;cursor:pointer}
.btn-primary{background:#f4b0c1}
.btn-outline{background:#fff;border:1px solid #ccc}
.summary{display:flex;justify-content:space-between;align-items:center;margin-top:16px}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <h1>Giỏ hàng</h1>
    <a href="index.php#cua-hang">← Tiếp tục mua sắm</a>
  </div>
  <div class="card">
    <?php if ($items === []): ?>
      <p>Giỏ hàng đang trống.</p>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="action" value="update">
        <table>
          <thead>
            <tr><th>Sản phẩm</th><th>Đơn giá</th><th>Số lượng</th><th>Tồn kho</th><th>Thành tiền</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($items as $it): ?>
            <tr>
              <td><?= htmlspecialchars($it['ten']) ?></td>
              <td><?= number_format($it['gia'], 0, ',', '.') ?>đ</td>
              <td><input type="number" name="qty[<?= htmlspecialchars($it['ma'], ENT_QUOTES, 'UTF-8') ?>]" min="1" max="<?= max(1, $it['stock']) ?>" value="<?= (int)$it['qty'] ?>"></td>
              <td><?= (int)$it['stock'] ?></td>
              <td><?= number_format($it['line'], 0, ',', '.') ?>đ</td>
              <td>
                <button class="btn btn-outline" type="button" onclick="removeItem('<?= htmlspecialchars($it['ma'], ENT_QUOTES, 'UTF-8') ?>')">Xóa</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="summary">
          <div><b>Tổng cộng:</b> <?= number_format($tong, 0, ',', '.') ?>đ</div>
          <div>
            <button class="btn btn-outline" type="submit">Cập nhật giỏ hàng</button>
            <a class="btn btn-primary" style="text-decoration:none;color:#222;display:inline-block;" href="taodon_sanpham.php">Thanh toán</a>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>
<form id="removeForm" method="post" style="display:none;">
  <input type="hidden" name="action" value="remove">
  <input type="hidden" name="ma_sp" id="removeMaSp" value="">
</form>
<script>
function removeItem(maSp) {
  document.getElementById('removeMaSp').value = maSp;
  document.getElementById('removeForm').submit();
}
</script>
</body>
</html>
