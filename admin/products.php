<?php
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/csrf.php';
include 'includes/header.php';

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

/**
 * Xóa sản phẩm: chi tiết đơn, tồn kho, mọi bảng FK tới sanpham, rồi SanPham.
 */
function delete_san_pham_cascade(mysqli $conn, string $ma_sp): array
{
    $ma_sp = trim($ma_sp);
    if ($ma_sp === '') {
        return [false, 'Mã sản phẩm không hợp lệ.'];
    }

    $conn->begin_transaction();
    try {
        foreach (['ChiTietDonHang', 'chitietdonhang'] as $tCt) {
            $chk = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tCt) . "'");
            if ($chk && $chk->num_rows > 0) {
                $st = $conn->prepare("DELETE FROM `{$tCt}` WHERE Ma_san_pham = ?");
                if ($st) {
                    $st->bind_param('s', $ma_sp);
                    $st->execute();
                }
                break;
            }
        }

        foreach (['TonKho', 'tonkho'] as $tTk) {
            $chk = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tTk) . "'");
            if ($chk && $chk->num_rows > 0) {
                $st = $conn->prepare("DELETE FROM `{$tTk}` WHERE Ma_san_pham = ?");
                if ($st) {
                    $st->bind_param('s', $ma_sp);
                    $st->execute();
                }
                break;
            }
        }

        for ($round = 0; $round < 30; $round++) {
            $sql = "SELECT DISTINCT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND REFERENCED_TABLE_SCHEMA = DATABASE()
                    AND REFERENCED_COLUMN_NAME = 'Ma_san_pham'
                    AND LOWER(REFERENCED_TABLE_NAME) = 'sanpham'";
            $res = $conn->query($sql);
            if (!$res) {
                break;
            }
            $deleted = 0;
            while ($row = $res->fetch_assoc()) {
                $tn = $row['TABLE_NAME'];
                $cn = $row['COLUMN_NAME'];
                if (strtolower($tn) === 'sanpham') {
                    continue;
                }
                $st = $conn->prepare("DELETE FROM `{$tn}` WHERE `{$cn}` = ?");
                if ($st) {
                    $st->bind_param('s', $ma_sp);
                    $st->execute();
                    $deleted += $st->affected_rows;
                }
            }
            if ($deleted === 0) {
                break;
            }
        }

        $ok = false;
        foreach (['SanPham', 'sanpham'] as $tSp) {
            $chk = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tSp) . "'");
            if ($chk && $chk->num_rows > 0) {
                $d = $conn->prepare("DELETE FROM `{$tSp}` WHERE Ma_san_pham = ?");
                if (!$d) {
                    throw new RuntimeException($conn->error);
                }
                $d->bind_param('s', $ma_sp);
                $d->execute();
                $ok = $d->affected_rows > 0;
                break;
            }
        }

        if (!$ok) {
            $conn->rollback();
            return [false, 'Không xóa được sản phẩm (không tìm thấy hoặc còn ràng buộc).'];
        }
        $conn->commit();
        return [true, ''];
    } catch (Throwable $e) {
        $conn->rollback();
        return [false, $e->getMessage()];
    }
}

// Fetch categories for select
$categories = [];
if (isset($pdo)) {
    $stmt = $pdo->query('SELECT Ma_danh_muc, Ten_danh_muc FROM DanhMucSanPham ORDER BY Ten_danh_muc');
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif (isset($conn)) {
    $res = $conn->query('SELECT Ma_danh_muc, Ten_danh_muc FROM DanhMucSanPham ORDER BY Ten_danh_muc');
    while ($r = $res->fetch_assoc()) $categories[] = $r;
}

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if (!check_csrf($_POST['_csrf'] ?? '')) die('CSRF token không hợp lệ');
  $act = $_POST['action'];
  if ($act === 'add') {
    $ma_danh_muc = $_POST['ma_danh_muc'] ?? null;
    $ten = $_POST['ten'] ?? '';
    $gia = $_POST['gia'] ?? 0;
    $mo_ta = $_POST['mo_ta'] ?? '';
    $hinh = $_POST['hinh_anh'] ?? '';
    $initial_stock = (int)($_POST['initial_stock'] ?? 0);
    $muc_toi_thieu = (int)($_POST['muc_toi_thieu'] ?? 0);

      // handle upload if provided
      $uploadedImage = '';
      if (!empty($_FILES['image']['name'])) {
        $up = $_FILES['image'];
        $ext = strtolower(pathinfo($up['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif'];
        if (in_array($ext, $allowed) && $up['error'] === UPLOAD_ERR_OK) {
          $dir = __DIR__ . '/../assets/images/products';
          if (!is_dir($dir)) mkdir($dir, 0755, true);
          $fileName = uniqid('p_') . '.' . $ext;
          if (move_uploaded_file($up['tmp_name'], $dir . '/' . $fileName)) {
            $uploadedImage = 'assets/images/products/' . $fileName;
          }
        }
      }

      if (isset($pdo)) {
        $ma_sp = genCode($pdo, 'SP', 'SanPham', 'Ma_san_pham');
        $stmt = $pdo->prepare('INSERT INTO SanPham (Ma_san_pham, Ma_danh_muc, Ten_san_pham, Gia, Mo_ta, Hinh_anh) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$ma_sp, $ma_danh_muc, $ten, $gia, $mo_ta, $uploadedImage]);
        // insert ton kho
        $ma_tk = genCode($pdo, 'TK', 'TonKho', 'Ma_ton_kho');
        $stmt2 = $pdo->prepare('INSERT INTO TonKho (Ma_ton_kho, Ma_san_pham, So_luong, Muc_ton_toi_thieu) VALUES (?, ?, ?, ?)');
        $stmt2->execute([$ma_tk, $ma_sp, $initial_stock, $muc_toi_thieu]);
      } elseif (isset($conn)) {
        $ma_sp = genCode($conn, 'SP', 'SanPham', 'Ma_san_pham');
        $stmt = $conn->prepare('INSERT INTO SanPham (Ma_san_pham, Ma_danh_muc, Ten_san_pham, Gia, Mo_ta, Hinh_anh) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssssss', $ma_sp, $ma_danh_muc, $ten, $gia, $mo_ta, $uploadedImage);
        $stmt->execute();
        $ma_tk = genCode($conn, 'TK', 'TonKho', 'Ma_ton_kho');
        $stmt2 = $conn->prepare('INSERT INTO TonKho (Ma_ton_kho, Ma_san_pham, So_luong, Muc_ton_toi_thieu) VALUES (?, ?, ?, ?)');
        $stmt2->bind_param('ssii', $ma_tk, $ma_sp, $initial_stock, $muc_toi_thieu);
        $stmt2->execute();
      }
      header('Location: products.php');
      exit;
    } elseif ($act === 'edit') {
      $ma_sp = $_POST['ma_sp'] ?? '';
      $ma_danh_muc = $_POST['ma_danh_muc'] ?? null;
      $ten = $_POST['ten'] ?? '';
      $gia = $_POST['gia'] ?? 0;
      $mo_ta = $_POST['mo_ta'] ?? '';
      $uploadedImage = '';
      if (!empty($_FILES['image']['name'])) {
        $up = $_FILES['image'];
        $ext = strtolower(pathinfo($up['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif'];
        if (in_array($ext, $allowed) && $up['error'] === UPLOAD_ERR_OK) {
          $dir = __DIR__ . '/../assets/images/products';
          if (!is_dir($dir)) mkdir($dir, 0755, true);
          $fileName = uniqid('p_') . '.' . $ext;
          if (move_uploaded_file($up['tmp_name'], $dir . '/' . $fileName)) {
            $uploadedImage = 'assets/images/products/' . $fileName;
          }
        }
      }
      if (isset($pdo)) {
        if ($uploadedImage) {
          $u = $pdo->prepare('UPDATE SanPham SET Ma_danh_muc=?, Ten_san_pham=?, Gia=?, Mo_ta=?, Hinh_anh=? WHERE Ma_san_pham=?');
          $u->execute([$ma_danh_muc, $ten, $gia, $mo_ta, $uploadedImage, $ma_sp]);
        } else {
          $u = $pdo->prepare('UPDATE SanPham SET Ma_danh_muc=?, Ten_san_pham=?, Gia=?, Mo_ta=? WHERE Ma_san_pham=?');
          $u->execute([$ma_danh_muc, $ten, $gia, $mo_ta, $ma_sp]);
        }
      } else {
        if ($uploadedImage) {
          $u = $conn->prepare('UPDATE SanPham SET Ma_danh_muc=?, Ten_san_pham=?, Gia=?, Mo_ta=?, Hinh_anh=? WHERE Ma_san_pham=?');
          $u->bind_param('ssisss', $ma_danh_muc, $ten, $gia, $mo_ta, $uploadedImage, $ma_sp);
          $u->execute();
        } else {
          $u = $conn->prepare('UPDATE SanPham SET Ma_danh_muc=?, Ten_san_pham=?, Gia=?, Mo_ta=? WHERE Ma_san_pham=?');
          $u->bind_param('ssiss', $ma_danh_muc, $ten, $gia, $mo_ta, $ma_sp);
          $u->execute();
        }
      }
      header('Location: products.php');
      exit;
    } elseif ($act === 'delete') {
      $ma_sp = trim((string)($_POST['ma_sp'] ?? ''));
      if (isset($pdo)) {
        try {
          $pdo->beginTransaction();
          try {
            $pdo->prepare('DELETE FROM ChiTietDonHang WHERE Ma_san_pham = ?')->execute([$ma_sp]);
          } catch (PDOException $e) {
          }
          try {
            $pdo->prepare('DELETE FROM TonKho WHERE Ma_san_pham = ?')->execute([$ma_sp]);
          } catch (PDOException $e) {
          }
          $pdo->prepare('DELETE FROM SanPham WHERE Ma_san_pham = ?')->execute([$ma_sp]);
          $pdo->commit();
        } catch (PDOException $e) {
          if ($pdo->inTransaction()) {
            $pdo->rollBack();
          }
        }
      } else {
        delete_san_pham_cascade($conn, $ma_sp);
      }
      header('Location: products.php');
      exit;
    }
}

// Fetch products; tìm kiếm ?q= (ô Search trên cùng hoặc form dưới)
$q = trim($_GET['q'] ?? '');
$products = [];
$sqlProd = 'SELECT s.Ma_san_pham, s.Ma_danh_muc, s.Ten_san_pham, s.Gia, s.Mo_ta, s.Hinh_anh, s.Trang_thai, c.Ten_danh_muc, t.So_luong
  FROM SanPham s
  LEFT JOIN DanhMucSanPham c ON s.Ma_danh_muc = c.Ma_danh_muc
  LEFT JOIN TonKho t ON s.Ma_san_pham = t.Ma_san_pham';
if ($q !== '') {
  $like = '%' . $q . '%';
  if (isset($pdo)) {
    $stmt = $pdo->prepare($sqlProd . ' WHERE s.Ten_san_pham LIKE ? OR IFNULL(s.Mo_ta, \'\') LIKE ? OR s.Ma_san_pham LIKE ? OR IFNULL(c.Ten_danh_muc, \'\') LIKE ? ORDER BY s.Ma_san_pham DESC');
    $stmt->execute([$like, $like, $like, $like]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } elseif (isset($conn)) {
    $st = $conn->prepare($sqlProd . ' WHERE s.Ten_san_pham LIKE ? OR IFNULL(s.Mo_ta, \'\') LIKE ? OR s.Ma_san_pham LIKE ? OR IFNULL(c.Ten_danh_muc, \'\') LIKE ? ORDER BY s.Ma_san_pham DESC');
    $st->bind_param('ssss', $like, $like, $like, $like);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
      $products[] = $r;
    }
  }
} else {
  if (isset($pdo)) {
    $stmt = $pdo->query($sqlProd . ' ORDER BY s.Ma_san_pham DESC');
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } elseif (isset($conn)) {
    $res = $conn->query($sqlProd . ' ORDER BY s.Ma_san_pham DESC');
    while ($r = $res->fetch_assoc()) {
      $products[] = $r;
    }
  }
}

?>

<div class="page-header">
  <div>
    <div class="title">QUẢN LÝ SẢN PHẨM</div>
    <div class="subtitle">Danh sách và quản lý sản phẩm</div>
  </div>
  <div class="header-actions">
    <form method="get" action="" class="admin-inline-search" style="display:flex;align-items:center;gap:8px;margin:0;">
      <input type="search" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Tìm sản phẩm…" class="search-input" aria-label="Tìm sản phẩm">
      <button type="submit" class="add-btn" style="padding:8px 14px;">Tìm</button>
      <?php if ($q !== ''): ?>
        <a href="products.php" class="icon-btn" style="text-decoration:none;padding:8px 12px;white-space:nowrap;">Xóa lọc</a>
      <?php endif; ?>
    </form>
    <button type="button" class="add-btn" onclick="openAddProduct()">+ THÊM SẢN PHẨM MỚI</button>
  </div>
</div>

<section id="addSectionProd" style="margin-bottom:18px; display:none">
  <form method="post" action="" enctype="multipart/form-data" style="display:flex; gap:12px; align-items:flex-end;">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="action" value="add">
    <div style="flex:1">
      <label style="display:block">Danh mục<br>
        <select name="ma_danh_muc" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #f1dede">
          <option value="">-- Chọn --</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?php echo htmlspecialchars($cat['Ma_danh_muc'] ?? $cat['Ma_danh_muc']);?>"><?php echo htmlspecialchars($cat['Ten_danh_muc'] ?? $cat['Ten_danh_muc']);?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div style="flex:2">
      <label style="display:block">Tên sản phẩm<br><input name="ten" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #f1dede"></label>
    </div>
    <div style="width:140px">
      <label style="display:block">Giá<br><input name="gia" type="number" step="0.01" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #f1dede"></label>
    </div>
    <div>
      <button class="add-btn" type="submit">Lưu</button>
      <button type="button" class="icon-btn" onclick="closeAddProduct()">Hủy</button>
    </div>
  </form>
</section>

<section>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>Hình ảnh</th><th>Tên Sản Phẩm</th><th>Danh Mục</th><th>SKU/Giá</th><th>Tồn Kho</th><th>Trạng Thái</th><th>Hành Động</th></tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): ?>
          <tr>
            <td><?php if (!empty($p['Hinh_anh'])): ?><img src="../<?php echo htmlspecialchars($p['Hinh_anh']);?>" class="thumbnail" alt=""><?php else: ?><img src="assets/images/default.png" class="thumbnail"><?php endif; ?></td>
            <td><?php echo htmlspecialchars($p['Ten_san_pham']);?></td>
            <td><?php echo htmlspecialchars($p['Ten_danh_muc']);?></td>
            <td><?php echo 'SKU1 / ' . number_format($p['Gia'],0,',','.'); ?></td>
            <td><?php echo htmlspecialchars($p['So_luong'] ?? '0'); ?></td>
            <td><span class="badge"><?php echo htmlspecialchars($p['Trang_thai'] ?? 'Hiển thị'); ?></span></td>
            <td>
              <div class="action-icons">
                <form method="post" style="display:inline" enctype="multipart/form-data">
                  <?php echo csrf_input(); ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="ma_sp" value="<?php echo htmlspecialchars($p['Ma_san_pham']);?>">
                  <button class="icon-btn" type="submit" onclick="return confirm('Xác nhận xóa sản phẩm?')">🗑️</button>
                </form>
                <button type="button" class="icon-btn editBtn"
                  data-ma="<?php echo htmlspecialchars($p['Ma_san_pham']);?>"
                  data-ma_dm="<?php echo htmlspecialchars($p['Ma_danh_muc']);?>"
                  data-ten="<?php echo htmlspecialchars($p['Ten_san_pham']);?>"
                  data-gia="<?php echo htmlspecialchars($p['Gia']);?>"
                  data-mota="<?php echo htmlspecialchars($p['Mo_ta']);?>"
                  data-hinh="<?php echo htmlspecialchars($p['Hinh_anh'] ?? '');?>"
                >✏️</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<div id="editModal" style="display:none">
  <div class="modal-overlay" role="presentation" onclick="if(event.target===this) document.getElementById('editModal').style.display='none'">
    <div class="modal-panel panel" style="max-width:520px" onclick="event.stopPropagation()">
      <h3>Sửa sản phẩm</h3>
      <form method="post" enctype="multipart/form-data">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="ma_sp" id="edit_ma_sp">

        <div class="form-row">
          <label style="flex:1">Danh mục<br>
            <select class="form-select" name="ma_danh_muc" id="edit_ma_danh_muc" required>
              <option value="">-- Chọn --</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat['Ma_danh_muc'] ?? $cat['Ma_danh_muc']);?>"><?php echo htmlspecialchars($cat['Ten_danh_muc'] ?? $cat['Ten_danh_muc']);?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label style="flex:2">Tên sản phẩm<br><input class="form-input" name="ten" id="edit_ten" required></label>
        </div>

        <div class="form-row">
          <label style="width:160px">Giá<br><input class="form-input" name="gia" id="edit_gia" type="number" step="0.01" required></label>
          <label style="flex:1">Mô tả<br><textarea class="form-textarea" name="mo_ta" id="edit_mo_ta"></textarea></label>
        </div>

        <div class="form-row">
          <label>Ảnh mới (nếu cần)<br><input name="image" type="file" accept="image/*"></label>
          <div class="modal-preview" id="currentImageEdit" style="align-self:center">No image</div>
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
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('.editBtn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var ma = this.dataset.ma || '';
      var ma_dm = this.dataset.ma_dm || '';
      var ten = this.dataset.ten || '';
      var gia = this.dataset.gia || '';
      var mota = this.dataset.mota || '';
      var hinh = this.dataset.hinh || '';
      document.getElementById('edit_ma_sp').value = ma;
      document.getElementById('edit_ten').value = ten;
      document.getElementById('edit_gia').value = gia;
      document.getElementById('edit_mo_ta').value = mota;
      var preview = document.getElementById('currentImageEdit');
      if (hinh) {
        preview.innerHTML = '<img src="../'+hinh+'" style="max-width:120px;max-height:120px;object-fit:cover">';
      } else {
        preview.innerHTML = '<span style="color:#999">No image</span>';
      }
      var sel = document.getElementById('edit_ma_danh_muc');
      if (sel) sel.value = ma_dm;
      document.getElementById('editModal').style.display = 'block';
    });
  });
});

function openAddProduct(){ document.getElementById('addSectionProd').style.display='block'; window.scrollTo({top:0, behavior:'smooth'}); }
function closeAddProduct(){ document.getElementById('addSectionProd').style.display='none'; }
</script>

<?php include 'includes/footer.php';
