<?php
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/workshop_schema.php';
include 'includes/header.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Cần mysqli $conn');
}
workshop_ensure_schema($conn);

$message = '';
$y = (int)($_GET['y'] ?? date('Y'));
$m = (int)($_GET['m'] ?? date('n'));
if ($m < 1 || $m > 12) {
    $m = (int)date('n');
}
if ($y < 2000 || $y > 2100) {
    $y = (int)date('Y');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!check_csrf($_POST['_csrf'] ?? '')) {
        die('CSRF token không hợp lệ');
    }
    $act = $_POST['action'];

    if ($act === 'add_lich') {
        $ma_cd = trim((string)($_POST['ma_chu_de'] ?? ''));
        $ngay = trim((string)($_POST['ngay_to_chuc'] ?? ''));
        $sl = max(1, (int)($_POST['so_luong_toi_da'] ?? 1));
        if ($ma_cd === '' || $ngay === '') {
            $message = 'Chọn chủ đề và ngày tổ chức.';
        } else {
            $ok = false;
            $lastErr = '';
            for ($t = 0; $t < 12; $t++) {
                $ma_lich = workshop_gen_ma_lich($conn);
                $st = $conn->prepare('INSERT INTO lichworkshop (Ma_lich_workshop, Ma_chu_de, Ngay_to_chuc, So_luong_toi_da, So_luong_da_dang_ky) VALUES (?, ?, ?, ?, 0)');
                if (!$st) {
                    $lastErr = $conn->error;
                    break;
                }
                $st->bind_param('sssi', $ma_lich, $ma_cd, $ngay, $sl);
                $dup = false;
                try {
                    if ($st->execute()) {
                        $ok = true;
                        break;
                    }
                    $dup = ((int) $st->errno === 1062);
                    $lastErr = $st->error;
                } catch (mysqli_sql_exception $e) {
                    $dup = ((int) $e->getCode() === 1062) || str_contains($e->getMessage(), 'Duplicate entry');
                    $lastErr = $e->getMessage();
                    if (!$dup) {
                        throw $e;
                    }
                }
                if (!$dup) {
                    break;
                }
            }
            if ($ok) {
                header('Location: workshop_schedule.php?y=' . $y . '&m=' . $m . '&ok=1');
                exit;
            }
            if ($lastErr !== '') {
                $message = 'Lỗi: ' . $lastErr;
            }
        }
    }

    if ($act === 'edit_lich') {
        $ma_lich = trim((string)($_POST['ma_lich_workshop'] ?? ''));
        $ngay = trim((string)($_POST['ngay_to_chuc'] ?? ''));
        $sl = max(1, (int)($_POST['so_luong_toi_da'] ?? 1));
        if ($ma_lich !== '' && $ngay !== '') {
            $st = $conn->prepare('UPDATE lichworkshop SET Ngay_to_chuc=?, So_luong_toi_da=? WHERE Ma_lich_workshop=?');
            $st->bind_param('sis', $ngay, $sl, $ma_lich);
            $st->execute();
        }
        header('Location: workshop_schedule.php?y=' . $y . '&m=' . $m);
        exit;
    }
}

if (isset($_GET['ok'])) {
    $message = 'Đã thêm lịch.';
}

$lichMa = trim((string)($_GET['lich'] ?? ''));
$registrations = [];
$lichDetail = null;

if ($lichMa !== '') {
    $st = $conn->prepare('SELECT l.*, c.Ten_chu_de, c.Gia FROM lichworkshop l JOIN chudeworkshop c ON l.Ma_chu_de = c.Ma_chu_de WHERE l.Ma_lich_workshop = ? LIMIT 1');
    $st->bind_param('s', $lichMa);
    $st->execute();
    $lichDetail = $st->get_result()->fetch_assoc();
    if ($lichDetail) {
        $khTable = 'khachhang';
        $tchk = $conn->query("SHOW TABLES LIKE 'KhachHang'");
        if ($tchk && $tchk->num_rows > 0) {
            $khTable = 'KhachHang';
        }
        $ngayBuoi = workshop_date_ymd($lichDetail['Ngay_to_chuc'] ?? null);
        $sqlReg = "SELECT dk.Ma_dang_ky, dk.So_nguoi_tham_gia, dk.Tong_tien, dk.Trang_thai_thanh_toan, dk.Thoi_gian_tao,
          lw.Ngay_to_chuc AS Ngay_buoi_hoc,
          kh.Ten_khach_hang, kh.Email, kh.So_dien_thoai, kh.Ma_khach_hang
          FROM dangkyworkshop dk
          INNER JOIN lichworkshop lw ON dk.Ma_lich_workshop = lw.Ma_lich_workshop
          LEFT JOIN `{$khTable}` kh ON dk.Ma_khach_hang = kh.Ma_khach_hang
          WHERE dk.Ma_lich_workshop = ?";
        if ($ngayBuoi !== null) {
            $sqlReg .= ' AND DATE(lw.Ngay_to_chuc) = ?';
        }
        $sqlReg .= ' ORDER BY dk.Thoi_gian_tao ASC, dk.Ma_dang_ky ASC';
        $st2 = $conn->prepare($sqlReg);
        if ($st2) {
            if ($ngayBuoi !== null) {
                $st2->bind_param('ss', $lichMa, $ngayBuoi);
            } else {
                $st2->bind_param('s', $lichMa);
            }
            $st2->execute();
            $r2 = $st2->get_result();
            while ($row = $r2->fetch_assoc()) {
                $registrations[] = $row;
            }
        }
    }
}

$start = sprintf('%04d-%02d-01', $y, $m);
if ($m === 12) {
    $startNextMonth = sprintf('%04d-01-01', $y + 1);
} else {
    $startNextMonth = sprintf('%04d-%02d-01', $y, $m + 1);
}

$rows = [];
$sql = "SELECT l.Ma_lich_workshop, l.Ngay_to_chuc, l.So_luong_toi_da, l.So_luong_da_dang_ky, c.Ten_chu_de, c.Ma_chu_de
        FROM lichworkshop l
        JOIN chudeworkshop c ON l.Ma_chu_de = c.Ma_chu_de
        WHERE l.Ngay_to_chuc >= ? AND l.Ngay_to_chuc < ?
        ORDER BY l.Ngay_to_chuc ASC, l.Ma_lich_workshop ASC";
$st = $conn->prepare($sql);
$st->bind_param('ss', $start, $startNextMonth);
$st->execute();
$rs = $st->get_result();
while ($row = $rs->fetch_assoc()) {
    $rows[] = $row;
}

$topicsForSelect = [];
$r2 = $conn->query("SELECT Ma_chu_de, Ten_chu_de FROM chudeworkshop WHERE Trang_thai = 'Active' ORDER BY Ten_chu_de");
if ($r2) {
    while ($t = $r2->fetch_assoc()) {
        $topicsForSelect[] = $t;
    }
}

$prevM = $m - 1;
$prevY = $y;
if ($prevM < 1) {
    $prevM = 12;
    $prevY--;
}
$nextM = $m + 1;
$nextY = $y;
if ($nextM > 12) {
    $nextM = 1;
    $nextY++;
}
?>
<div class="page-header">
  <div>
    <div class="title">QUẢN LÝ LỊCH WORKSHOP</div>
  </div>
  <div class="header-actions">
    <a href="workshop.php" class="icon-btn" style="text-decoration:none;padding:10px 14px;">← Workshop</a>
    <button type="button" class="add-btn" onclick="document.getElementById('addLichModal').style.display='block'">+ THÊM LỊCH</button>
  </div>
</div>

<?php if ($message): ?>
  <div style="margin-bottom:12px;padding:10px 14px;border-radius:10px;background:#fff6f6;color:#6b3f3f;"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<p style="margin-bottom:14px;">
  <a href="workshop_schedule.php?y=<?php echo (int)$prevY; ?>&m=<?php echo (int)$prevM; ?>" class="icon-btn" style="text-decoration:none;padding:8px 12px;">← Tháng trước</a>
  <strong style="margin:0 12px;">Tháng <?php echo (int)$m; ?> / <?php echo (int)$y; ?></strong>
  <a href="workshop_schedule.php?y=<?php echo (int)$nextY; ?>&m=<?php echo (int)$nextM; ?>" class="icon-btn" style="text-decoration:none;padding:8px 12px;">Tháng sau →</a>
</p>

<section>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Ngày tổ chức</th>
          <th>Chủ đề</th>
          <th>Mã lịch</th>
          <th>Đã đăng ký / Tối đa</th>
          <th>Trạng thái chỗ</th>
          <th>Hành động</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row):
            $max = (int)($row['So_luong_toi_da'] ?? 0);
            $used = (int)($row['So_luong_da_dang_ky'] ?? 0);
            $con = $max - $used;
            ?>
          <tr>
            <td><?php echo htmlspecialchars(workshop_fmt_ngay_vn($row['Ngay_to_chuc'] ?? null)); ?></td>
            <td><?php echo htmlspecialchars($row['Ten_chu_de'] ?? ''); ?></td>
            <td><code><?php echo htmlspecialchars($row['Ma_lich_workshop'] ?? ''); ?></code></td>
            <td><?php echo (int)$used; ?> / <?php echo (int)$max; ?></td>
            <td><?php if ($con > 0): ?><span class="badge" style="background:#e8f5e9;color:#2e7d32;">Còn <?php echo (int)$con; ?> chỗ</span><?php else: ?><span class="badge" style="background:#ffebee;color:#c62828;">Hết chỗ</span><?php endif; ?></td>
            <td>
              <a class="add-btn" style="padding:6px 12px;font-size:12px;text-decoration:none;display:inline-block;" href="workshop_schedule.php?y=<?php echo (int)$y; ?>&m=<?php echo (int)$m; ?>&lich=<?php echo urlencode($row['Ma_lich_workshop']); ?>&ngay=<?php echo urlencode((string) workshop_date_ymd($row['Ngay_to_chuc'] ?? null)); ?>">Danh sách KH</a>
              <button type="button" class="icon-btn btn-edit-lich" style="margin-left:6px;"
                data-ma="<?php echo htmlspecialchars($row['Ma_lich_workshop'], ENT_QUOTES); ?>"
                data-ngay="<?php echo htmlspecialchars((string) workshop_date_ymd($row['Ngay_to_chuc'] ?? null), ENT_QUOTES); ?>"
                data-sl="<?php echo (int)$max; ?>"
              >✏️</button>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
          <tr><td colspan="6" style="color:#a88;">Không có lịch trong tháng này. Thêm lịch mới hoặc chọn tháng khác.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if ($lichDetail): ?>
<div class="panel" style="margin-top:20px;">
  <h3 style="margin-top:0;">Đăng ký — <?php echo htmlspecialchars($lichDetail['Ten_chu_de'] ?? ''); ?> · ngày <?php echo htmlspecialchars(workshop_fmt_ngay_vn($lichDetail['Ngay_to_chuc'] ?? null)); ?></h3>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>Ngày buổi học</th><th>Thời gian ĐK</th><th>Khách hàng</th><th>SĐT</th><th>Email</th><th>Số ghế</th><th>Tổng tiền</th><th>TT thanh toán</th></tr>
      </thead>
      <tbody>
        <?php foreach ($registrations as $r): ?>
          <tr>
            <td><?php echo htmlspecialchars(workshop_fmt_ngay_vn($r['Ngay_buoi_hoc'] ?? null)); ?></td>
            <td><?php echo htmlspecialchars($r['Thoi_gian_tao'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['Ten_khach_hang'] ?? '—'); ?></td>
            <td><?php echo htmlspecialchars($r['So_dien_thoai'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['Email'] ?? ''); ?></td>
            <td><?php echo (int)($r['So_nguoi_tham_gia'] ?? 0); ?></td>
            <td><?php echo number_format((float)($r['Tong_tien'] ?? 0), 0, ',', '.'); ?>đ</td>
            <td><?php echo htmlspecialchars($r['Trang_thai_thanh_toan'] ?? ''); ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($registrations === []): ?>
          <tr><td colspan="8" style="color:#a88;">Chưa có khách đăng ký buổi này.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <p style="margin-top:12px;"><a href="workshop_schedule.php?y=<?php echo (int)$y; ?>&m=<?php echo (int)$m; ?>" class="icon-btn" style="text-decoration:none;padding:8px 12px;">Đóng danh sách</a></p>
</div>
<?php endif; ?>

<div id="addLichModal" style="display:none">
  <div class="modal-overlay" role="presentation" onclick="if(event.target===this) document.getElementById('addLichModal').style.display='none'">
    <div class="modal-panel panel" style="max-width:480px" onclick="event.stopPropagation()">
      <h3>Thêm lịch workshop</h3>
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="add_lich">
        <div class="form-row column"><label>Chủ đề (đang mở)<br>
          <select class="form-select" name="ma_chu_de" required>
            <option value="">— Chọn —</option>
            <?php foreach ($topicsForSelect as $t): ?>
              <option value="<?php echo htmlspecialchars($t['Ma_chu_de']); ?>"><?php echo htmlspecialchars($t['Ten_chu_de']); ?></option>
            <?php endforeach; ?>
          </select>
        </label></div>
        <div class="form-row column"><label>Ngày tổ chức<br><input class="form-input" type="date" name="ngay_to_chuc" required></label></div>
        <div class="form-row column"><label>Số lượng tối đa (ghế)<br><input class="form-input" type="number" name="so_luong_toi_da" value="30" min="1" id="add_sl_max"></label></div>
        <div class="modal-actions">
          <button class="add-btn" type="submit">Lưu</button>
          <button type="button" class="icon-btn" onclick="document.getElementById('addLichModal').style.display='none'">Hủy</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div id="editLichModal" style="display:none">
  <div class="modal-overlay" role="presentation" onclick="if(event.target===this) document.getElementById('editLichModal').style.display='none'">
    <div class="modal-panel panel" style="max-width:480px" onclick="event.stopPropagation()">
      <h3>Sửa lịch</h3>
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="edit_lich">
        <input type="hidden" name="ma_lich_workshop" id="el_ma">
        <div class="form-row column"><label>Ngày tổ chức<br><input class="form-input" type="date" name="ngay_to_chuc" id="el_ngay" required></label></div>
        <div class="form-row column"><label>Số lượng tối đa<br><input class="form-input" type="number" name="so_luong_toi_da" id="el_sl" min="1" required></label></div>
        <div class="modal-actions">
          <button class="add-btn" type="submit">Lưu</button>
          <button type="button" class="icon-btn" onclick="document.getElementById('editLichModal').style.display='none'">Hủy</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.btn-edit-lich').forEach(function (btn) {
  btn.addEventListener('click', function () {
    document.getElementById('el_ma').value = btn.getAttribute('data-ma') || '';
    document.getElementById('el_ngay').value = btn.getAttribute('data-ngay') || '';
    document.getElementById('el_sl').value = btn.getAttribute('data-sl') || '1';
    document.getElementById('editLichModal').style.display = 'block';
  });
});
</script>

<?php include 'includes/footer.php';
