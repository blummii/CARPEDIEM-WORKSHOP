<?php
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/csrf.php';
include 'includes/header.php';

// --- helpers ---
function money_vn($v) {
  $n = (float)($v ?? 0);
  // Không hiển thị phần thập phân để giống UI khác trong admin
  return number_format($n, 0, ',', '.');
}

function map_order_status($st) {
  return match ($st) {
    'pending' => 'Đang xử lý',
    'confirmed' => 'Đã xác nhận',
    'shipped' => 'Đang giao',
    'completed' => 'Hoàn thành',
    'cancelled' => 'Hủy',
    default => $st ?? '',
  };
}

function genCode(mysqli $db, string $prefix, string $table, string $col): string {
  $res = $db->query("SELECT MAX($col) AS m FROM $table");
  $row = $res ? $res->fetch_assoc() : null;
  $m = $row['m'] ?? null;
  if (!$m) return $prefix . '001';
  $num = (int)substr((string)$m, strlen($prefix));
  return $prefix . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
}

function resolveEmployeeId(mysqli $db, $adminSessionId): ?string {
  $adminId = trim((string)$adminSessionId);

  // 1) Nếu session đã lưu đúng mã nhân viên thì dùng luôn.
  if ($adminId !== '') {
    $st = $db->prepare('SELECT Ma_nhan_vien FROM NhanVien WHERE Ma_nhan_vien = ? LIMIT 1');
    $st->bind_param('s', $adminId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!empty($row['Ma_nhan_vien'])) return (string)$row['Ma_nhan_vien'];
  }

  // 2) Fallback: lấy 1 nhân viên bất kỳ để đảm bảo không vướng FK.
  $res = $db->query('SELECT Ma_nhan_vien FROM NhanVien ORDER BY Ma_nhan_vien LIMIT 1');
  $row = $res ? $res->fetch_assoc() : null;
  $val = $row['Ma_nhan_vien'] ?? null;
  if ($val === null || $val === '') return null;
  return (string)$val;
}

// list orders


// view details or update status
$edit = $_GET['edit'] ?? null;
$view = $_GET['view'] ?? ($_POST['ma_don'] ?? null);
$message = '';

$allowedStatuses = ['pending', 'confirmed', 'shipped', 'completed', 'cancelled'];

// Handle status update from detail view
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
  if (!check_csrf($_POST['_csrf'] ?? '')) die('CSRF token không hợp lệ');

  $ma = $_POST['ma_don'] ?? '';
  $status = $_POST['status'] ?? '';

  if (!$ma) {
    $message = 'Thiếu mã đơn hàng.';
  } elseif (!in_array($status, $allowedStatuses, true)) {
    $message = 'Trạng thái không hợp lệ.';
  } else {
    // Get current order status
    $st = $conn->prepare('SELECT Trang_thai FROM DonHang WHERE Ma_don_hang = ?');
    $st->bind_param('s', $ma);
    $st->execute();
    $cur = $st->get_result()->fetch_assoc();

    if (!$cur) {
      $message = 'Không tìm thấy đơn hàng.';
    } else {
      $currentStatus = $cur['Trang_thai'];

      $needItems = [];
      $st2 = $conn->prepare('
        SELECT cdh.Ma_san_pham, cdh.So_luong, COALESCE(tk.So_luong, 0) AS So_luong_ton
        FROM ChiTietDonHang cdh
        LEFT JOIN TonKho tk ON tk.Ma_san_pham = cdh.Ma_san_pham
        WHERE cdh.Ma_don_hang = ?
      ');
      $st2->bind_param('s', $ma);
      $st2->execute();
      $res2 = $st2->get_result();
      while ($r = $res2->fetch_assoc()) {
        $needItems[] = $r;
      }

      $shouldDeduct = in_array($status, ['confirmed', 'shipped'], true) && $currentStatus === 'pending';
      $shouldRestock = $status === 'cancelled' && in_array($currentStatus, ['confirmed', 'shipped', 'completed'], true);

      if ($shouldDeduct) {
        $lack = [];
        foreach ($needItems as $it) {
          $soLuongCan = (int)($it['So_luong'] ?? 0);
          $soLuongTon = (int)($it['So_luong_ton'] ?? 0);
          if ($soLuongTon < $soLuongCan) {
            $lack[] = $it['Ma_san_pham'] . ' (cần ' . $soLuongCan . ', còn ' . $soLuongTon . ')';
          }
        }

        if ($lack) {
          $message = 'Không đủ tồn kho để cập nhật: ' . implode('; ', $lack);
        } else {
          $conn->begin_transaction();
          try {
            // Update order status
            $u = $conn->prepare('UPDATE DonHang SET Trang_thai = ? WHERE Ma_don_hang = ?');
            $u->bind_param('ss', $status, $ma);
            $u->execute();

            // Deduct stock
            $upd = $conn->prepare('UPDATE TonKho SET So_luong = So_luong - ? WHERE Ma_san_pham = ?');
            foreach ($needItems as $it) {
              $qty = (int)($it['So_luong'] ?? 0);
              $ma_sp = $it['Ma_san_pham'];
              $upd->bind_param('is', $qty, $ma_sp);
              $upd->execute();
            }

            $conn->commit();
            $message = 'Đã cập nhật trạng thái và trừ tồn kho.';
          } catch (Throwable $e) {
            $conn->rollback();
            $message = 'Lỗi cập nhật: ' . $e->getMessage();
          }
        }
      } elseif ($shouldRestock) {
        $conn->begin_transaction();
        try {
          $u = $conn->prepare('UPDATE DonHang SET Trang_thai = ? WHERE Ma_don_hang = ?');
          $u->bind_param('ss', $status, $ma);
          $u->execute();

          // Restock stock
          $upd = $conn->prepare('UPDATE TonKho SET So_luong = So_luong + ? WHERE Ma_san_pham = ?');
          foreach ($needItems as $it) {
            $qty = (int)($it['So_luong'] ?? 0);
            $ma_sp = $it['Ma_san_pham'];
            $upd->bind_param('is', $qty, $ma_sp);
            $upd->execute();
          }

          $conn->commit();
          $message = 'Đã cập nhật trạng thái và hoàn lại tồn kho.';
        } catch (Throwable $e) {
          $conn->rollback();
          $message = 'Lỗi cập nhật: ' . $e->getMessage();
        }
      } else {
        // Plain status update (no stock movement)
        $u = $conn->prepare('UPDATE DonHang SET Trang_thai = ? WHERE Ma_don_hang = ?');
        $u->bind_param('ss', $status, $ma);
        $u->execute();
        $message = 'Cập nhật trạng thái.';
      }
    }
  }
}

// Handle order CRUD (add / edit / delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $act = $_POST['action'];
  if ($act === 'add_order') {
    if (!check_csrf($_POST['_csrf'] ?? '')) die('CSRF token không hợp lệ');

    $ma_khach_hang = $_POST['ma_khach_hang'] ?? '';
    $items_ma_sp = $_POST['items_ma_sp'] ?? [];
    $items_so_luong = $_POST['items_so_luong'] ?? [];

    if (!$ma_khach_hang) {
      $message = 'Thiếu khách hàng.';
    } else {
      $pairs = [];
      for ($i = 0; $i < count($items_ma_sp); $i++) {
        $sp = (string)($items_ma_sp[$i] ?? '');
        $qty = (int)($items_so_luong[$i] ?? 0);
        if ($sp && $qty > 0) {
          $pairs[] = [$sp, $qty];
        }
      }

      if (!$pairs) {
        $message = 'Đơn hàng phải có ít nhất 1 sản phẩm (số lượng > 0).';
      } else {
        $conn->begin_transaction();
        try {
          $ma_don_hang = genCode($conn, 'DH', 'DonHang', 'Ma_don_hang');

          // Fetch product prices in bulk
          $in = implode(',', array_fill(0, count($pairs), '?'));
          $params = [];
          foreach ($pairs as $p) $params[] = $p[0];
          $types = str_repeat('s', count($params));

          $stPrices = $conn->prepare("SELECT Ma_san_pham, Gia FROM SanPham WHERE Ma_san_pham IN ($in)");
          $stPrices->bind_param($types, ...$params);
          $stPrices->execute();
          $resPrices = $stPrices->get_result();
          $priceMap = [];
          while ($r = $resPrices->fetch_assoc()) {
            $priceMap[(string)$r['Ma_san_pham']] = (float)$r['Gia'];
          }

          $tong_tien = 0;
          foreach ($pairs as $p) {
            [$sp, $qty] = $p;
            $gia = $priceMap[$sp] ?? null;
            if ($gia === null) {
              throw new Exception('Không tìm thấy giá sản phẩm: ' . $sp);
            }
            $tong_tien += $gia * $qty;
          }

          $trang_thai = 'pending';
          $ma_nhan_vien = resolveEmployeeId($conn, $_SESSION['admin_id'] ?? '');
          if ($ma_nhan_vien !== null) {
            $u = $conn->prepare('INSERT INTO DonHang (Ma_don_hang, Ma_khach_hang, Ma_nhan_vien, Tong_tien, Trang_thai, Thoi_gian_tao) VALUES (?, ?, ?, ?, ?, NOW())');
            // types: Ma_don_hang(s), Ma_khach_hang(s), Ma_nhan_vien(s), Tong_tien(d), Trang_thai(s)
            $u->bind_param('sssds', $ma_don_hang, $ma_khach_hang, $ma_nhan_vien, $tong_tien, $trang_thai);
          } else {
            // Nếu không map được nhân viên, thử ghi NULL (chỉ chạy được nếu schema cho phép).
            $u = $conn->prepare('INSERT INTO DonHang (Ma_don_hang, Ma_khach_hang, Ma_nhan_vien, Tong_tien, Trang_thai, Thoi_gian_tao) VALUES (?, ?, NULL, ?, ?, NOW())');
            $u->bind_param('ssds', $ma_don_hang, $ma_khach_hang, $tong_tien, $trang_thai);
          }
          $u->execute();

          $ins = $conn->prepare('
            INSERT INTO ChiTietDonHang (Ma_don_hang, Ma_san_pham, So_luong, Don_gia, Thanh_tien)
            VALUES (?, ?, ?, ?, ?)
          ');

          foreach ($pairs as $p) {
            [$sp, $qty] = $p;
            $gia = (float)$priceMap[$sp];
            $thanhTien = $gia * $qty;
            $ins->bind_param('ssidd', $ma_don_hang, $sp, $qty, $gia, $thanhTien);
            $ins->execute();
          }

          $conn->commit();
          header('Location: orders.php');
          exit;
        } catch (Throwable $e) {
          $conn->rollback();
          $message = 'Lỗi tạo đơn: ' . $e->getMessage() . '. Hãy kiểm tra dữ liệu bảng NhanVien hoặc ràng buộc Ma_nhan_vien trong bảng DonHang.';
        }
      }
    }
  } elseif ($act === 'edit_order') {
    if (!check_csrf($_POST['_csrf'] ?? '')) die('CSRF token không hợp lệ');

    $ma_don = $_POST['ma_don'] ?? '';
    $ma_khach_hang = $_POST['ma_khach_hang'] ?? '';
    $items_ma_sp = $_POST['items_ma_sp'] ?? [];
    $items_so_luong = $_POST['items_so_luong'] ?? [];

    if (!$ma_don) {
      $message = 'Thiếu mã đơn.';
    } elseif (!$ma_khach_hang) {
      $message = 'Thiếu khách hàng.';
    } else {
      $pairs = [];
      for ($i = 0; $i < count($items_ma_sp); $i++) {
        $sp = (string)($items_ma_sp[$i] ?? '');
        $qty = (int)($items_so_luong[$i] ?? 0);
        // Cho phép qty = 0 để xóa dòng (sẽ bỏ qua khi insert)
        if ($sp && $qty >= 0) {
          $pairs[] = [$sp, $qty];
        }
      }
      $pairs = array_values(array_filter($pairs, fn($x) => $x[1] > 0));

      if (!$pairs) {
        $message = 'Đơn hàng phải có ít nhất 1 sản phẩm (số lượng > 0).';
      } else {
        $conn->begin_transaction();
        try {
          $st = $conn->prepare('SELECT Trang_thai FROM DonHang WHERE Ma_don_hang = ? FOR UPDATE');
          $st->bind_param('s', $ma_don);
          $st->execute();
          $orderCur = $st->get_result()->fetch_assoc();
          if (!$orderCur) throw new Exception('Không tìm thấy đơn hàng.');
          $currentStatus = $orderCur['Trang_thai'];

          // Load old items for stock restore when needed
          $oldItems = [];
          if (in_array($currentStatus, ['confirmed', 'shipped', 'completed'], true)) {
            $stOld = $conn->prepare('SELECT Ma_san_pham, So_luong FROM ChiTietDonHang WHERE Ma_don_hang = ?');
            $stOld->bind_param('s', $ma_don);
            $stOld->execute();
            $resOld = $stOld->get_result();
            while ($r = $resOld->fetch_assoc()) {
              $oldItems[] = [(string)$r['Ma_san_pham'], (int)$r['So_luong']];
            }
            foreach ($oldItems as $it) {
              [$spOld, $qtyOld] = $it;
              $updRest = $conn->prepare('UPDATE TonKho SET So_luong = So_luong + ? WHERE Ma_san_pham = ?');
              $updRest->bind_param('is', $qtyOld, $spOld);
              $updRest->execute();
            }
          }

          // Fetch new prices
          $in = implode(',', array_fill(0, count($pairs), '?'));
          $params = [];
          foreach ($pairs as $p) $params[] = $p[0];
          $types = str_repeat('s', count($params));
          $stPrices = $conn->prepare("SELECT Ma_san_pham, Gia FROM SanPham WHERE Ma_san_pham IN ($in)");
          $stPrices->bind_param($types, ...$params);
          $stPrices->execute();
          $resPrices = $stPrices->get_result();
          $priceMap = [];
          while ($r = $resPrices->fetch_assoc()) {
            $priceMap[(string)$r['Ma_san_pham']] = (float)$r['Gia'];
          }

          $tong_tien = 0;
          foreach ($pairs as $p) {
            [$sp, $qty] = $p;
            $gia = $priceMap[$sp] ?? null;
            if ($gia === null) throw new Exception('Không tìm thấy giá sản phẩm: ' . $sp);
            $tong_tien += $gia * $qty;
          }

          // Replace details
          $del = $conn->prepare('DELETE FROM ChiTietDonHang WHERE Ma_don_hang = ?');
          $del->bind_param('s', $ma_don);
          $del->execute();

          $ins = $conn->prepare('
            INSERT INTO ChiTietDonHang (Ma_don_hang, Ma_san_pham, So_luong, Don_gia, Thanh_tien)
            VALUES (?, ?, ?, ?, ?)
          ');
          foreach ($pairs as $p) {
            [$sp, $qty] = $p;
            $gia = (float)$priceMap[$sp];
            $thanhTien = $gia * $qty;
            $ins->bind_param('ssidd', $ma_don, $sp, $qty, $gia, $thanhTien);
            $ins->execute();
          }

          // Update order header
          $ma_nhan_vien = resolveEmployeeId($conn, $_SESSION['admin_id'] ?? '');
          if ($ma_nhan_vien !== null) {
            $upHead = $conn->prepare('UPDATE DonHang SET Ma_khach_hang = ?, Ma_nhan_vien = ?, Tong_tien = ? WHERE Ma_don_hang = ?');
            $upHead->bind_param('ssds', $ma_khach_hang, $ma_nhan_vien, $tong_tien, $ma_don);
          } else {
            $upHead = $conn->prepare('UPDATE DonHang SET Ma_khach_hang = ?, Ma_nhan_vien = NULL, Tong_tien = ? WHERE Ma_don_hang = ?');
            $upHead->bind_param('sds', $ma_khach_hang, $tong_tien, $ma_don);
          }
          $upHead->execute();

          // Deduct new stock when status already deducted
          if (in_array($currentStatus, ['confirmed', 'shipped', 'completed'], true)) {
            foreach ($pairs as $p) {
              [$sp, $qty] = $p;
              $upd = $conn->prepare('UPDATE TonKho SET So_luong = So_luong - ? WHERE Ma_san_pham = ? AND So_luong >= ?');
              $upd->bind_param('isi', $qty, $sp, $qty);
              $upd->execute();
              if ($upd->affected_rows < 1) {
                throw new Exception('Không đủ tồn kho để cập nhật sản phẩm: ' . $sp);
              }
            }
          }

          $conn->commit();
          header('Location: orders.php');
          exit;
        } catch (Throwable $e) {
          $conn->rollback();
          $message = 'Lỗi cập nhật đơn: ' . $e->getMessage() . '. Hãy kiểm tra dữ liệu bảng NhanVien hoặc ràng buộc Ma_nhan_vien trong bảng DonHang.';
        }
      }
    }
  } elseif ($act === 'delete_order') {
    if (!check_csrf($_POST['_csrf'] ?? '')) die('CSRF token không hợp lệ');

    $ma_don = $_POST['ma_don'] ?? '';
    if (!$ma_don) {
      $message = 'Thiếu mã đơn.';
    } else {
      $conn->begin_transaction();
      try {
        $st = $conn->prepare('SELECT Trang_thai FROM DonHang WHERE Ma_don_hang = ? FOR UPDATE');
        $st->bind_param('s', $ma_don);
        $st->execute();
        $orderCur = $st->get_result()->fetch_assoc();
        if (!$orderCur) throw new Exception('Không tìm thấy đơn hàng.');

        $currentStatus = $orderCur['Trang_thai'];

        $oldItems = [];
        $stOld = $conn->prepare('SELECT Ma_san_pham, So_luong FROM ChiTietDonHang WHERE Ma_don_hang = ?');
        $stOld->bind_param('s', $ma_don);
        $stOld->execute();
        $resOld = $stOld->get_result();
        while ($r = $resOld->fetch_assoc()) $oldItems[] = [(string)$r['Ma_san_pham'], (int)$r['So_luong']];

        if (in_array($currentStatus, ['confirmed', 'shipped', 'completed'], true)) {
          foreach ($oldItems as $it) {
            [$sp, $qty] = $it;
            $upd = $conn->prepare('UPDATE TonKho SET So_luong = So_luong + ? WHERE Ma_san_pham = ?');
            $upd->bind_param('is', $qty, $sp);
            $upd->execute();
          }
        }

        $d1 = $conn->prepare('DELETE FROM ChiTietDonHang WHERE Ma_don_hang = ?');
        $d1->bind_param('s', $ma_don);
        $d1->execute();

        $d2 = $conn->prepare('DELETE FROM DonHang WHERE Ma_don_hang = ?');
        $d2->bind_param('s', $ma_don);
        $d2->execute();

        $conn->commit();
        header('Location: orders.php');
        exit;
      } catch (Throwable $e) {
        $conn->rollback();
        $message = 'Lỗi xóa đơn: ' . $e->getMessage();
      }
    }
  }
}

// Search q (from topbar)
$q = trim($_GET['q'] ?? '');

if (!$view && !$edit) {
  if ($q !== '') {
    $orders = [];
    $like = '%' . $q . '%';
    $st = $conn->prepare('
      SELECT dh.Ma_don_hang, dh.Tong_tien, dh.Trang_thai, dh.Thoi_gian_tao,
             kh.Ten_khach_hang, kh.So_dien_thoai
      FROM DonHang dh
      LEFT JOIN KhachHang kh ON dh.Ma_khach_hang = kh.Ma_khach_hang
      WHERE dh.Ma_don_hang LIKE ? OR kh.Ten_khach_hang LIKE ? OR kh.So_dien_thoai LIKE ?
      ORDER BY dh.Thoi_gian_tao DESC
      LIMIT 200
    ');
    $st->bind_param('sss', $like, $like, $like);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) $orders[] = $r;
  } else {
    $orders = [];
    $res = $conn->query('
      SELECT dh.Ma_don_hang, dh.Tong_tien, dh.Trang_thai, dh.Thoi_gian_tao,
             kh.Ten_khach_hang, kh.So_dien_thoai
      FROM DonHang dh
      LEFT JOIN KhachHang kh ON dh.Ma_khach_hang = kh.Ma_khach_hang
      ORDER BY dh.Thoi_gian_tao DESC
      LIMIT 200
    ');
    while ($r = $res->fetch_assoc()) $orders[] = $r;
  }
}

?>
<div class="page-header">
  <div>
    <div class="title">QUẢN LÝ ĐƠN HÀNG</div>
    <div class="subtitle">Danh sách đơn đặt hàng và theo dõi trạng thái</div>
  </div>
  <div class="header-actions">
    <button type="button" class="add-btn" onclick="var el=document.getElementById('addSectionOrder'); if(!el) return false; el.style.display='block'; window.scrollTo({top:0, behavior:'smooth'}); return false;">+ TẠO ĐƠN HÀNG MỚI</button>
  </div>
</div>
<?php if ($message) echo '<div style="margin-bottom:12px;color:#6b3f3f">'.htmlspecialchars($message).'</div>'; ?>

<?php if ($view):
    // fetch order and items
    $order = null;
    $items = [];

    $st = $conn->prepare('
      SELECT dh.*, kh.Ten_khach_hang, kh.So_dien_thoai
      FROM DonHang dh
      LEFT JOIN KhachHang kh ON dh.Ma_khach_hang = kh.Ma_khach_hang
      WHERE dh.Ma_don_hang = ?
    ');
    $st->bind_param('s', $view);
    $st->execute();
    $order = $st->get_result()->fetch_assoc();

    $it = $conn->prepare('
      SELECT cdh.Ma_san_pham, sp.Ten_san_pham, sp.Hinh_anh,
             cdh.So_luong, cdh.Don_gia, cdh.Thanh_tien,
             COALESCE(tk.So_luong, 0) AS So_luong_ton
      FROM ChiTietDonHang cdh
      JOIN SanPham sp ON sp.Ma_san_pham = cdh.Ma_san_pham
      LEFT JOIN TonKho tk ON tk.Ma_san_pham = cdh.Ma_san_pham
      WHERE cdh.Ma_don_hang = ?
      ORDER BY sp.Ma_san_pham
    ');
    $it->bind_param('s', $view);
    $it->execute();
    $res = $it->get_result();
    while ($r = $res->fetch_assoc()) $items[] = $r;
?>
  <div class="card" style="padding:14px; border-radius:12px;">
    <h3 style="margin-top:0">CHI TIẾT HÓA ĐƠN <?php echo htmlspecialchars($view);?></h3>
    <?php if ($order): ?>
      <p style="margin:0 0 10px 0">
        Khách: <strong><?php echo htmlspecialchars($order['Ten_khach_hang'] ?? $order['Ma_khach_hang']);?></strong>
        <?php if (!empty($order['So_dien_thoai'])): ?>
          <span style="color:#a88"> (<?php echo htmlspecialchars($order['So_dien_thoai']);?>)</span>
        <?php endif; ?>
        | Tổng tiền: <strong><?php echo money_vn($order['Tong_tien'] ?? 0);?></strong>
        | Trạng thái: <span class="badge"><?php echo htmlspecialchars(map_order_status($order['Trang_thai']));?></span>
      </p>

      <table class="data-table">
        <thead>
          <tr>
            <th>Hình ảnh</th>
            <th>Mã (SKU)</th>
            <th>Tên sản phẩm</th>
            <th>Số lượng</th>
            <th>Đơn giá</th>
            <th>Thành tiền</th>
            <th>Tồn kho</th>
          </tr>
        </thead>
        <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td>
            <?php if (!empty($it['Hinh_anh'])): ?>
              <img src="../<?php echo htmlspecialchars($it['Hinh_anh']);?>" class="thumbnail" alt="">
            <?php else: ?>
              <img src="assets/images/default.png" class="thumbnail" alt="">
            <?php endif; ?>
          </td>
          <td><?php echo htmlspecialchars($it['Ma_san_pham']);?></td>
          <td><?php echo htmlspecialchars($it['Ten_san_pham']);?></td>
          <td><?php echo (int)$it['So_luong'];?></td>
          <td><?php echo money_vn($it['Don_gia']);?></td>
          <td><?php echo money_vn($it['Thanh_tien']);?></td>
          <?php
            $ton = (int)($it['So_luong_ton'] ?? 0);
            $can = (int)($it['So_luong'] ?? 0);
            $stockLabel = ($ton >= $can) ? 'Còn hàng' : 'Hết hàng';
          ?>
          <td><span class="badge"><?php echo $stockLabel; ?></span></td>
        </tr>
      <?php endforeach; ?>
        </tbody>
      </table>

      <h4 style="margin-top:12px">Cập nhật trạng thái đơn hàng</h4>
      <form method="post" style="display:flex;gap:8px;align-items:center; flex-wrap:wrap;">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="ma_don" value="<?php echo htmlspecialchars($view);?>">
        <select name="status" style="padding:8px;border-radius:8px;border:1px solid #f1dede">
          <option value="pending" <?php echo ($order['Trang_thai'] === 'pending') ? 'selected' : ''; ?>>Đang xử lý</option>
          <option value="confirmed" <?php echo ($order['Trang_thai'] === 'confirmed') ? 'selected' : ''; ?>>Đã xác nhận</option>
          <option value="shipped" <?php echo ($order['Trang_thai'] === 'shipped') ? 'selected' : ''; ?>>Đang giao</option>
          <option value="completed" <?php echo ($order['Trang_thai'] === 'completed') ? 'selected' : ''; ?>>Hoàn thành</option>
          <option value="cancelled" <?php echo ($order['Trang_thai'] === 'cancelled') ? 'selected' : ''; ?>>Hủy</option>
        </select>
        <button class="add-btn" type="submit">Lưu</button>
      </form>
    <?php else: ?>
      <div>Không tìm thấy đơn hàng.</div>
    <?php endif; ?>

    <p style="margin-top:12px"><a href="orders.php">Quay lại</a></p>
  </div>

<?php elseif ($edit): ?>
  <!-- Edit order page -->
  <?php
    $order = null;
    $items = [];
    $st = $conn->prepare('
      SELECT dh.*, kh.Ten_khach_hang, kh.So_dien_thoai
      FROM DonHang dh
      LEFT JOIN KhachHang kh ON dh.Ma_khach_hang = kh.Ma_khach_hang
      WHERE dh.Ma_don_hang = ?
    ');
    $st->bind_param('s', $edit);
    $st->execute();
    $order = $st->get_result()->fetch_assoc();

    $it = $conn->prepare('
      SELECT cdh.Ma_san_pham, sp.Ten_san_pham, sp.Gia, cdh.So_luong
      FROM ChiTietDonHang cdh
      JOIN SanPham sp ON sp.Ma_san_pham = cdh.Ma_san_pham
      WHERE cdh.Ma_don_hang = ?
      ORDER BY sp.Ma_san_pham
    ');
    $it->bind_param('s', $edit);
    $it->execute();
    $res = $it->get_result();
    while ($r = $res->fetch_assoc()) $items[] = $r;

    // preload dropdowns
    $customers = [];
    $cst = $conn->query('SELECT Ma_khach_hang, Ten_khach_hang FROM KhachHang ORDER BY Ten_khach_hang');
    while ($r = $cst->fetch_assoc()) $customers[] = $r;

    $categories = [];
    $catst = $conn->query('SELECT Ma_danh_muc, Ten_danh_muc FROM DanhMucSanPham ORDER BY Ten_danh_muc');
    while ($r = $catst->fetch_assoc()) $categories[] = $r;

    $products = [];
    $ps = $conn->query('SELECT Ma_san_pham, Ten_san_pham, Gia, Ma_danh_muc FROM SanPham ORDER BY Ten_san_pham');
    while ($r = $ps->fetch_assoc()) $products[] = $r;
  ?>

  <div class="card" style="padding:14px; border-radius:12px;">
    <h3 style="margin-top:0">SỬA ĐƠN HÀNG <?php echo htmlspecialchars($edit); ?></h3>

    <?php if ($order): ?>
      <p style="margin:0 0 10px 0">
        Trạng thái: <span class="badge"><?php echo htmlspecialchars(map_order_status($order['Trang_thai'] ?? '')); ?></span>
        | Tổng tiền: <strong><?php echo money_vn($order['Tong_tien'] ?? 0); ?></strong>
      </p>

      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="edit_order">
        <input type="hidden" name="ma_don" value="<?php echo htmlspecialchars($edit); ?>">

        <label style="display:block; margin-bottom:8px;">
          Khách hàng<br>
          <select name="ma_khach_hang" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #f1dede">
            <?php foreach ($customers as $c): ?>
              <option value="<?php echo htmlspecialchars($c['Ma_khach_hang']); ?>" <?php echo ((string)$c['Ma_khach_hang'] === (string)($order['Ma_khach_hang'] ?? '')) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($c['Ten_khach_hang']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; margin:10px 0;">
          <div style="flex:1; min-width:240px;">
            <label style="display:block;">
              Danh mục<br>
              <select id="edit_cat" style="width:100%; padding:10px; border-radius:8px; border:1px solid #f1dede">
                <option value="">-- Tất cả --</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo htmlspecialchars($cat['Ma_danh_muc']); ?>">
                    <?php echo htmlspecialchars($cat['Ten_danh_muc']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div style="flex:2; min-width:320px;">
            <label style="display:block;">
              Sản phẩm<br>
              <select id="edit_sp" style="width:100%; padding:10px; border-radius:8px; border:1px solid #f1dede">
                <option value="">-- Chọn --</option>
                <?php foreach ($products as $p): ?>
                  <option value="<?php echo htmlspecialchars($p['Ma_san_pham']); ?>" data-cat="<?php echo htmlspecialchars($p['Ma_danh_muc']); ?>">
                    <?php echo htmlspecialchars($p['Ten_san_pham']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div style="width:160px;">
            <label style="display:block;">Số lượng<br><input id="edit_qty" type="number" min="1" value="1" style="width:100%; padding:10px; border-radius:8px; border:1px solid #f1dede"></label>
          </div>
          <div>
            <button type="button" class="add-btn" onclick="addEditItem()">+ Thêm</button>
          </div>
        </div>

        <div class="table-responsive" style="margin:10px 0;">
          <table class="data-table">
            <thead>
              <tr>
                <th>SKU</th>
                <th>Sản phẩm</th>
                <th>Số lượng</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="edit_items_body">
              <?php foreach ($items as $it): ?>
                <tr>
                  <td><?php echo htmlspecialchars($it['Ma_san_pham']); ?></td>
                  <td><?php echo htmlspecialchars($it['Ten_san_pham']); ?></td>
                  <td>
                    <input type="hidden" name="items_ma_sp[]" value="<?php echo htmlspecialchars($it['Ma_san_pham']); ?>">
                    <input name="items_so_luong[]" type="number" min="0" value="<?php echo (int)$it['So_luong']; ?>" style="width:120px; padding:8px; border-radius:8px; border:1px solid #f1dede">
                  </td>
                  <td>
                    <button type="button" class="icon-btn" onclick="removeRow(this)">🗑️</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end;">
          <button class="add-btn" type="submit">Lưu</button>
          <a class="icon-btn" style="padding:10px 14px; border:1px solid #f0dede; border-radius:10px; text-decoration:none;" href="orders.php">Hủy</a>
        </div>

        <p style="margin-top:10px; color:#a88; font-size:13px;">
          Nếu cần sửa danh mục sản phẩm, bạn vào `categories.php`.
        </p>
      </form>

      <script>
        (function(){
          var catSel = document.getElementById('edit_cat');
          var spSel = document.getElementById('edit_sp');
          if (!catSel || !spSel) return;

          catSel.addEventListener('change', function(){
            var v = catSel.value;
            Array.from(spSel.options).forEach(function(opt){
              if (!opt.value) return;
              var cat = opt.getAttribute('data-cat');
              opt.style.display = (!v || cat === v) ? '' : 'none';
            });
          });
        })();

        function addEditItem(){
          var spSel = document.getElementById('edit_sp');
          var qtyInp = document.getElementById('edit_qty');
          if (!spSel || !qtyInp) return;
          var spId = spSel.value;
          if (!spId) return alert('Chọn sản phẩm trước');

          var opt = spSel.options[spSel.selectedIndex];
          var ten = (opt && opt.textContent) ? opt.textContent : spId;
          var qty = parseInt(qtyInp.value || '1', 10);
          if (isNaN(qty) || qty < 0) qty = 0;

          var body = document.getElementById('edit_items_body');
          var tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${spId}</td>
            <td>${ten.replace(/</g,'&lt;')}</td>
            <td>
              <input type="hidden" name="items_ma_sp[]" value="${spId}">
              <input name="items_so_luong[]" type="number" min="0" value="${qty}" style="width:120px; padding:8px; border-radius:8px; border:1px solid #f1dede">
            </td>
            <td><button type="button" class="icon-btn" onclick="removeRow(this)">🗑️</button></td>
          `;
          body.appendChild(tr);
        }

        function removeRow(btn){
          var tr = btn.closest('tr');
          if (tr) tr.parentNode.removeChild(tr);
        }
      </script>
    <?php else: ?>
      <div>Không tìm thấy đơn hàng.</div>
    <?php endif; ?>
  </div>

<?php else: ?>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Mã đơn</th>
          <th>Khách</th>
          <th>Tổng</th>
          <th>Trạng thái</th>
          <th>Thời gian</th>
          <th>Hành động</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td><?php echo htmlspecialchars($o['Ma_don_hang']);?></td>
          <td><?php echo htmlspecialchars($o['Ten_khach_hang'] ?? $o['Ma_khach_hang'] ?? ''); ?></td>
          <td><?php echo money_vn($o['Tong_tien'] ?? 0);?></td>
          <td><span class="badge"><?php echo htmlspecialchars(map_order_status($o['Trang_thai'] ?? ''));?></span></td>
          <td><?php echo htmlspecialchars($o['Thoi_gian_tao']);?></td>
          <td>
            <a class="icon-btn" href="orders.php?view=<?php echo urlencode($o['Ma_don_hang']);?>" title="Xem chi tiết hóa đơn">🧾</a>
            <a class="icon-btn" href="orders.php?edit=<?php echo urlencode($o['Ma_don_hang']);?>" title="Sửa đơn" style="margin-left:6px;">✏️</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Xác nhận xóa đơn?')">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="action" value="delete_order">
              <input type="hidden" name="ma_don" value="<?php echo htmlspecialchars($o['Ma_don_hang']); ?>">
              <button class="icon-btn" type="submit" title="Xóa đơn" style="margin-left:6px; padding:0;">🗑️</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Add order section -->
  <section id="addSectionOrder" style="margin:18px 0; display:none;">
    <?php
      $customers = [];
      $cst = $conn->query('SELECT Ma_khach_hang, Ten_khach_hang FROM KhachHang ORDER BY Ten_khach_hang');
      while ($r = $cst->fetch_assoc()) $customers[] = $r;

      $categories = [];
      $catst = $conn->query('SELECT Ma_danh_muc, Ten_danh_muc FROM DanhMucSanPham ORDER BY Ten_danh_muc');
      while ($r = $catst->fetch_assoc()) $categories[] = $r;

      $products = [];
      $ps = $conn->query('SELECT Ma_san_pham, Ten_san_pham, Gia, Ma_danh_muc FROM SanPham ORDER BY Ten_san_pham');
      while ($r = $ps->fetch_assoc()) $products[] = $r;
    ?>

    <div class="card" style="padding:14px; border-radius:12px;">
      <h3 style="margin-top:0">TẠO ĐƠN HÀNG</h3>
      <form method="post" id="addOrderForm">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="add_order">

        <label style="display:block; margin-bottom:8px;">
          Khách hàng<br>
          <select name="ma_khach_hang" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #f1dede">
            <?php foreach ($customers as $c): ?>
              <option value="<?php echo htmlspecialchars($c['Ma_khach_hang']); ?>">
                <?php echo htmlspecialchars($c['Ten_khach_hang']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; margin:10px 0;">
          <div style="flex:1; min-width:240px;">
            <label style="display:block;">
              Danh mục<br>
              <select id="add_cat" style="width:100%; padding:10px; border-radius:8px; border:1px solid #f1dede">
                <option value="">-- Tất cả --</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo htmlspecialchars($cat['Ma_danh_muc']); ?>">
                    <?php echo htmlspecialchars($cat['Ten_danh_muc']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div style="flex:2; min-width:320px;">
            <label style="display:block;">
              Sản phẩm<br>
              <select id="add_sp" style="width:100%; padding:10px; border-radius:8px; border:1px solid #f1dede">
                <option value="">-- Chọn --</option>
                <?php foreach ($products as $p): ?>
                  <option value="<?php echo htmlspecialchars($p['Ma_san_pham']); ?>" data-cat="<?php echo htmlspecialchars($p['Ma_danh_muc']); ?>">
                    <?php echo htmlspecialchars($p['Ten_san_pham']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div style="width:160px;">
            <label style="display:block;">Số lượng<br><input id="add_qty" type="number" min="1" value="1" style="width:100%; padding:10px; border-radius:8px; border:1px solid #f1dede"></label>
          </div>
          <div>
            <button type="button" class="add-btn" onclick="addNewItem()">+ Thêm</button>
          </div>
          <div>
            <a class="icon-btn" style="padding:10px 14px; border:1px solid #f0dede; border-radius:10px; text-decoration:none;" href="categories.php">Quản lý danh mục</a>
          </div>
        </div>

        <div class="table-responsive" style="margin:10px 0;">
          <table class="data-table">
            <thead>
              <tr>
                <th>SKU</th>
                <th>Sản phẩm</th>
                <th>Số lượng</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="add_items_body"></tbody>
          </table>
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end;">
          <button class="add-btn" type="submit">Lưu đơn</button>
          <button type="button" class="icon-btn" onclick="closeAddOrder()" style="padding:10px 14px; border:1px solid #f0dede; border-radius:10px;">Hủy</button>
        </div>
      </form>
    </div>

    <script>
      (function(){
        var catSel = document.getElementById('add_cat');
        var spSel = document.getElementById('add_sp');
        if (!catSel || !spSel) return;

        catSel.addEventListener('change', function(){
          var v = catSel.value;
          Array.from(spSel.options).forEach(function(opt){
            if (!opt.value) return;
            var cat = opt.getAttribute('data-cat');
            opt.style.display = (!v || cat === v) ? '' : 'none';
          });
        });
      })();

      function addNewItem(){
        var spSel = document.getElementById('add_sp');
        var qtyInp = document.getElementById('add_qty');
        var body = document.getElementById('add_items_body');
        if (!spSel || !qtyInp || !body) return;

        var spId = spSel.value;
        if (!spId) return alert('Chọn sản phẩm trước');
        var opt = spSel.options[spSel.selectedIndex];
        var ten = (opt && opt.textContent) ? opt.textContent : spId;
        var qty = parseInt(qtyInp.value || '1', 10);
        if (isNaN(qty) || qty < 0) qty = 0;

        var tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${spId}</td>
          <td>${ten.replace(/</g,'&lt;')}</td>
          <td>
            <input type="hidden" name="items_ma_sp[]" value="${spId}">
            <input name="items_so_luong[]" type="number" min="0" value="${qty}" style="width:120px; padding:8px; border-radius:8px; border:1px solid #f1dede">
          </td>
          <td><button type="button" class="icon-btn" onclick="removeRow(this)">🗑️</button></td>
        `;
        body.appendChild(tr);
      }

      function removeRow(btn){
        var tr = btn.closest('tr');
        if (tr) tr.parentNode.removeChild(tr);
      }
    </script>

  </section>

  <script>
    function openAddOrder(){ document.getElementById('addSectionOrder').style.display='block'; window.scrollTo({top:0, behavior:'smooth'}); }
    function closeAddOrder(){ document.getElementById('addSectionOrder').style.display='none'; }
  </script>
<?php endif; ?>

<?php include 'includes/footer.php';
