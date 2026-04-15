<?php
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/csrf.php';
include 'includes/header.php';

// Temporary debug helper: append ?debug=1 to the URL to see session/DB status
if (!empty($_GET['debug']) && $_GET['debug'] == '1') {
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
  echo '<div style="background:#fff6f6;padding:12px;border-radius:8px;margin:12px 0;color:#6b3f3f">';
  echo '<strong>DEBUG</strong><br>';
  echo 'Session admin_id: ' . (isset($_SESSION['admin_id']) ? htmlspecialchars($_SESSION['admin_id']) : '<em>not set</em>') . '<br>';
  echo 'PDO present: ' . (isset($pdo) ? 'yes' : 'no') . '<br>';
  echo 'mysqli present: ' . (isset($conn) ? 'yes' : 'no') . '<br>';
  echo '</div>';
}

function genCode($db, $prefix, $table, $col) {
    if (isset($db) && $db instanceof PDO) {
        $stmt = $db->query("SELECT MAX($col) AS m FROM $table");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $m = $row['m'] ?? null;
    } elseif (isset($db)) {
        $res = $db->query("SELECT MAX($col) AS m FROM $table");
        $row = $res->fetch_assoc();
        $m = $row['m'] ?? null;
    } else {
        return $prefix . '001';
    }
    if (!$m) return $prefix . '001';
    $num = (int)substr($m, strlen($prefix));
    return $prefix . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
}

// Actions: add / edit / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!check_csrf($_POST['_csrf'] ?? '')) die('CSRF token không hợp lệ');
    $act = $_POST['action'];
    if ($act === 'edit') {
        $ma_tk = $_POST['ma_tk'] ?? '';
        $so_luong = (int)($_POST['so_luong'] ?? 0);
        $muc_toi_thieu = (int)($_POST['muc_toi_thieu'] ?? 0);
        if (isset($pdo)) {
            $u = $pdo->prepare('UPDATE TonKho SET So_luong=?, Muc_ton_toi_thieu=? WHERE Ma_ton_kho=?');
            $u->execute([$so_luong, $muc_toi_thieu, $ma_tk]);
        } else {
            $u = $conn->prepare('UPDATE TonKho SET So_luong=?, Muc_ton_toi_thieu=? WHERE Ma_ton_kho=?');
            $u->bind_param('iis', $so_luong, $muc_toi_thieu, $ma_tk);
            $u->execute();
        }
        header('Location: inventory.php'); exit;
    }
    if ($act === 'delete') {
        $ma_tk = $_POST['ma_tk'] ?? '';
        if (isset($pdo)) {
            $d = $pdo->prepare('DELETE FROM TonKho WHERE Ma_ton_kho = ?'); $d->execute([$ma_tk]);
        } else {
            $d = $conn->prepare('DELETE FROM TonKho WHERE Ma_ton_kho = ?'); $d->bind_param('s', $ma_tk); $d->execute();
        }
        header('Location: inventory.php'); exit;
    }
}

// Search / list
$q = trim($_GET['q'] ?? '');
$items = [];
$sqlBase = 'SELECT t.Ma_ton_kho, t.Ma_san_pham, t.So_luong, t.Muc_ton_toi_thieu, s.Ten_san_pham, s.Gia, s.Hinh_anh, c.Ten_danh_muc FROM TonKho t LEFT JOIN SanPham s ON t.Ma_san_pham=s.Ma_san_pham LEFT JOIN DanhMucSanPham c ON s.Ma_danh_muc=c.Ma_danh_muc';
if ($q !== '') {
    $like = "%$q%";
    if (isset($pdo)) {
        $st = $pdo->prepare($sqlBase . ' WHERE s.Ten_san_pham LIKE ? OR s.Ma_san_pham LIKE ? ORDER BY t.Ma_ton_kho DESC');
        $st->execute([$like, $like]); $items = $st->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $st = $conn->prepare($sqlBase . ' WHERE s.Ten_san_pham LIKE ? OR s.Ma_san_pham LIKE ? ORDER BY t.Ma_ton_kho DESC');
        $st->bind_param('ss', $like, $like); $st->execute(); $res = $st->get_result(); while ($r=$res->fetch_assoc()) $items[]=$r;
    }
} else {
    if (isset($pdo)) { $st = $pdo->query($sqlBase . ' ORDER BY t.Ma_ton_kho DESC'); $items = $st->fetchAll(PDO::FETCH_ASSOC); }
    else { $res = $conn->query($sqlBase . ' ORDER BY t.Ma_ton_kho DESC'); while ($r=$res->fetch_assoc()) $items[]=$r; }
}

?>

<div class="page-header">
  <div>
    <div class="title">QUẢN LÝ TỒN KHO</div>
    <div class="subtitle">Theo dõi số lượng và cảnh báo tồn kho</div>
  </div>
</div>

<section>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>Hình ảnh</th><th>Mã Sản Phẩm (SKU)</th><th>Tên Sản Phẩm</th><th>Danh Mục</th><th>Số Lượng Tồn Kho</th><th>Giá Bán</th><th>Trạng Thái</th><th>Hành Động</th></tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it): ?>
          <?php $status = ((int)($it['So_luong'] ?? 0) <= (int)($it['Muc_ton_toi_thieu'] ?? 0)) ? 'Hết hàng' : 'Còn hàng'; ?>
          <tr>
            <td><?php if (!empty($it['Hinh_anh'])): ?><img src="../<?php echo htmlspecialchars($it['Hinh_anh']);?>" class="thumbnail"><?php else: ?><img src="assets/images/default.png" class="thumbnail"><?php endif; ?></td>
            <td><?php echo htmlspecialchars($it['Ma_san_pham']);?></td>
            <td><?php echo htmlspecialchars($it['Ten_san_pham']);?></td>
            <td><?php echo htmlspecialchars($it['Ten_danh_muc']);?></td>
            <td><?php echo (int)$it['So_luong'];?></td>
            <td><?php echo number_format($it['Gia'] ?? 0,0,',','.');?></td>
            <td><span class="badge"><?php echo $status;?></span></td>
            <td>
              <div class="action-icons">
                <button class="icon-btn" onclick="openEdit('<?php echo htmlspecialchars($it['Ma_ton_kho'] ?? ''); ?>', '<?php echo htmlspecialchars($it['So_luong']);?>', '<?php echo htmlspecialchars($it['Muc_ton_toi_thieu']);?>')">✏️</button>
                <form method="post" style="display:inline" onsubmit="return confirm('Xác nhận xóa?')">
                  <?php echo csrf_input(); ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="ma_tk" value="<?php echo htmlspecialchars($it['Ma_ton_kho'] ?? ''); ?>">
                  <button class="icon-btn" type="submit">🗑️</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- Edit modal -->
<div id="editModal" style="display:none">
  <div class="modal-overlay" role="presentation" onclick="if(event.target===this) document.getElementById('editModal').style.display='none'">
    <div class="modal-panel panel" style="max-width:420px" onclick="event.stopPropagation()">
      <h3>Cập nhật tồn kho</h3>
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="ma_tk" id="edit_ma_tk">
        <div class="form-row column">
          <label>Số lượng<br><input class="form-input" name="so_luong" id="edit_so_luong" type="number" min="0" value="0" required></label>
        </div>
        <div class="form-row column">
          <label>Mức tồn tối thiểu<br><input class="form-input" name="muc_toi_thieu" id="edit_muc_toi_thieu" type="number" min="0" value="0" required></label>
        </div>
        <div class="modal-actions">
          <button class="add-btn" type="submit">Lưu</button>
          <button class="icon-btn" type="button" onclick="document.getElementById('editModal').style.display='none'">Hủy</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEdit(ma, so, muc) {
  if (!ma) return alert('Không có dữ liệu để chỉnh sửa');
  document.getElementById('edit_ma_tk').value = ma;
  document.getElementById('edit_so_luong').value = so;
  document.getElementById('edit_muc_toi_thieu').value = muc;
  document.getElementById('editModal').style.display = 'block';
}
</script>

<?php include 'includes/footer.php';
