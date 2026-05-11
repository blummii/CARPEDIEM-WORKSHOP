<?php
session_start();
include("config/db.php");

function cd_shop_pool_exclude_basename(string $baseLower): bool
{
    foreach (['banner', 'hero', 'default-product'] as $p) {
        if ($baseLower === $p || strncmp($baseLower, $p, strlen($p)) === 0) {
            return true;
        }
    }
    return false;
}

function cd_build_shop_image_pool(string $rootAbs, string $webPrefix): array
{
    $pool = [];
    if (!is_dir($rootAbs)) return $pool;
    $rootNorm = rtrim(str_replace('\\', '/', $rootAbs), '/');
    $webPrefix = rtrim(str_replace('\\', '/', $webPrefix), '/');
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootAbs, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $fi) {
        if (!$fi->isFile()) continue;
        $ext = strtolower($fi->getExtension());
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) continue;
        $base = strtolower(pathinfo($fi->getFilename(), PATHINFO_FILENAME));
        if (cd_shop_pool_exclude_basename($base)) continue;
        $full = str_replace('\\', '/', $fi->getPathname());
        if (strncmp($full, $rootNorm, strlen($rootNorm)) !== 0) continue;
        $suffix = ltrim(substr($full, strlen($rootNorm)), '/');
        $pool[] = $webPrefix . '/' . $suffix;
    }
    sort($pool, SORT_STRING);
    return $pool;
}

function cd_shop_image_for_product(string $maSp, array $pool): string
{
    if ($pool === []) return 'admin/assets/images/default-product.jpg';
    $idx = abs(crc32($maSp)) % count($pool);
    return $pool[$idx];
}

$maSp = trim((string)($_GET['ma_sp'] ?? $_POST['ma_sp'] ?? ''));
if ($maSp === '') {
    echo "<script>alert('Thiếu mã sản phẩm.');location='index.php#cua-hang';</script>";
    exit();
}

$st = $conn->prepare("
    SELECT s.Ma_san_pham, s.Ten_san_pham, s.Gia, s.Mo_ta, s.Ma_danh_muc,
           c.Ten_danh_muc, COALESCE(t.So_luong, 0) AS So_luong
    FROM SanPham s
    LEFT JOIN DanhMucSanPham c ON c.Ma_danh_muc = s.Ma_danh_muc
    LEFT JOIN TonKho t ON t.Ma_san_pham = s.Ma_san_pham
    WHERE s.Ma_san_pham = ?
      AND (
        s.Trang_thai IS NULL
        OR TRIM(IFNULL(s.Trang_thai,'')) = ''
        OR LOWER(TRIM(IFNULL(s.Trang_thai,''))) IN ('hiển thị', 'hien thi', 'active')
      )
    LIMIT 1
");
$st->bind_param('s', $maSp);
$st->execute();
$sp = $st->get_result()->fetch_assoc();

if (!$sp) {
    echo "<script>alert('Sản phẩm không tồn tại hoặc đang ẩn.');location='index.php#cua-hang';</script>";
    exit();
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $qty = max(1, (int)($_POST['qty'] ?? 1));
    $stock = (int)($sp['So_luong'] ?? 0);
    if ($stock <= 0) {
        echo "<script>alert('Sản phẩm đã hết hàng.');history.back();</script>";
        exit();
    }
    if ($qty > $stock) $qty = $stock;
    $current = (int)($_SESSION['cart'][$maSp] ?? 0);
    $newQty = min($stock, $current + $qty);
    $_SESSION['cart'][$maSp] = $newQty;

    if ($_POST['action'] === 'buy_now') {
        header('Location: giohang.php');
        exit();
    }
    echo "<script>alert('Đã thêm sản phẩm vào giỏ hàng.');location='giohang.php';</script>";
    exit();
}

$pool = cd_build_shop_image_pool(__DIR__ . '/admin/assets/images', 'admin/assets/images');
$img = cd_shop_image_for_product((string)$sp['Ma_san_pham'], $pool);
$stock = (int)($sp['So_luong'] ?? 0);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chi tiết sản phẩm</title>
<style>
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f7f7f7;color:#222}
.wrap{max-width:1100px;margin:30px auto;padding:0 16px}
.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.top a{text-decoration:none;color:#333;font-weight:600}
.card{background:#fff;border-radius:16px;box-shadow:0 8px 24px rgba(0,0,0,.08);display:grid;grid-template-columns:1fr 1fr;gap:22px;padding:22px}
.card img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:14px;background:#eee}
h1{margin:0 0 10px;font-size:30px}
.sub{color:#666;margin-bottom:10px}
.price{font-size:34px;font-weight:bold;color:#8a2a3f;margin:8px 0 16px}
.desc{line-height:1.7;color:#555;white-space:pre-wrap}
.stock{margin:14px 0;color:#444}
.qty-row{display:flex;gap:12px;align-items:center;margin-top:8px}
input[type=number]{width:120px;padding:10px;border:1px solid #ddd;border-radius:10px}
.actions{display:flex;gap:10px;margin-top:16px}
.btn{border:none;border-radius:12px;padding:12px 18px;font-weight:700;cursor:pointer}
.btn-primary{background:#f4b0c1;color:#222}
.btn-outline{background:#fff;border:1px solid #ccc;color:#333}
.btn[disabled]{background:#ddd;color:#777;cursor:not-allowed}
@media(max-width:900px){.card{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <a href="index.php#cua-hang">← Quay lại cửa hàng</a>
    <a href="giohang.php">Giỏ hàng (<?= array_sum($_SESSION['cart']) ?>)</a>
  </div>

  <div class="card">
    <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string)$sp['Ten_san_pham'], ENT_QUOTES, 'UTF-8') ?>">
    <div>
      <h1><?= htmlspecialchars((string)$sp['Ten_san_pham']) ?></h1>
      <div class="sub">Danh mục: <?= htmlspecialchars((string)($sp['Ten_danh_muc'] ?? 'Chưa phân loại')) ?></div>
      <div class="price"><?= number_format((float)$sp['Gia'], 0, ',', '.') ?>đ</div>
      <div class="desc"><?= htmlspecialchars((string)($sp['Mo_ta'] ?? '')) ?></div>
      <div class="stock">Tồn kho: <b><?= $stock ?></b></div>

      <form method="post">
        <input type="hidden" name="ma_sp" value="<?= htmlspecialchars($maSp, ENT_QUOTES, 'UTF-8') ?>">
        <div class="qty-row">
          <label for="qty">Số lượng:</label>
          <input id="qty" type="number" name="qty" min="1" max="<?= max(1, $stock) ?>" value="1" <?= $stock > 0 ? '' : 'disabled' ?>>
        </div>
        <div class="actions">
          <button class="btn btn-primary" type="submit" name="action" value="add_to_cart" <?= $stock > 0 ? '' : 'disabled' ?>>Thêm vào giỏ hàng</button>
          <button class="btn btn-outline" type="submit" name="action" value="buy_now" <?= $stock > 0 ? '' : 'disabled' ?>>Mua ngay</button>
        </div>
      </form>
    </div>
  </div>
</div>
</body>
</html>
