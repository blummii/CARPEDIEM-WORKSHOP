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

// Handle add / delete using DanhMucSanPham
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if (!check_csrf($_POST['_csrf'] ?? '')) {
    die('CSRF token không hợp lệ');
  }
  $message = '';
  if ($_POST['action'] === 'add') {
        $ten = $_POST['ten'] ?? '';
        $mo_ta = $_POST['mo_ta'] ?? '';
        if (isset($pdo)) {
            $ma = genCode($pdo, 'DM', 'DanhMucSanPham', 'Ma_danh_muc');
            $stmt = $pdo->prepare('INSERT INTO DanhMucSanPham (Ma_danh_muc, Ten_danh_muc, Mo_ta) VALUES (?, ?, ?)');
            $stmt->execute([$ma, $ten, $mo_ta]);
        } elseif (isset($conn)) {
            $ma = genCode($conn, 'DM', 'DanhMucSanPham', 'Ma_danh_muc');
            $stmt = $conn->prepare('INSERT INTO DanhMucSanPham (Ma_danh_muc, Ten_danh_muc, Mo_ta) VALUES (?, ?, ?)');
            $stmt->bind_param('sss', $ma, $ten, $mo_ta);
            $stmt->execute();
        }
        header('Location: categories.php');
        exit;
    }
      if ($_POST['action'] === 'edit') {
        $ma = $_POST['ma'] ?? '';
        $ten = $_POST['ten'] ?? '';
        $mo_ta = $_POST['mo_ta'] ?? '';
        // Debug: log edit attempt
        @file_put_contents(__DIR__ . '/debug.log', "[".date('Y-m-d H:i:s')."] EDIT ATTEMPT: " . json_encode(['ma'=>$ma,'ten'=>$ten,'mo_ta'=>$mo_ta]) . "\n", FILE_APPEND);
        try {
          if (isset($pdo)) {
            $u = $pdo->prepare('UPDATE DanhMucSanPham SET Ten_danh_muc = ?, Mo_ta = ? WHERE Ma_danh_muc = ?');
            $u->execute([$ten, $mo_ta, $ma]);
          } else {
            $u = $conn->prepare('UPDATE DanhMucSanPham SET Ten_danh_muc = ?, Mo_ta = ? WHERE Ma_danh_muc = ?');
            $u->bind_param('sss', $ten, $mo_ta, $ma);
            $ok = $u->execute();
            if ($ok === false) {
              @file_put_contents(__DIR__ . '/debug.log', "[".date('Y-m-d H:i:s')."] MySQLi error: " . $conn->error . "\n", FILE_APPEND);
              throw new Exception($conn->error);
            }
          }
          // success -> redirect to show updated list
          header('Location: categories.php?edited=1');
          exit;
        } catch (Exception $e) {
          $message = 'Lỗi cập nhật: ' . $e->getMessage();
          @file_put_contents(__DIR__ . '/debug.log', "[".date('Y-m-d H:i:s')."] Exception: " . $e->getMessage() . "\n", FILE_APPEND);
        }
      }
    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
      $id = $_POST['id'];
        if (isset($pdo)) {
            $stmt = $pdo->prepare('DELETE FROM DanhMucSanPham WHERE Ma_danh_muc = ?');
            $stmt->execute([$id]);
        } elseif (isset($conn)) {
            $stmt = $conn->prepare('DELETE FROM DanhMucSanPham WHERE Ma_danh_muc = ?');
            $stmt->bind_param('s', $id);
            $stmt->execute();
        }
        header('Location: categories.php');
        exit;
    }
}

// Fetch categories + số sản phẩm; tìm kiếm ?q= (ô Search trên cùng hoặc form dưới)
$q = trim($_GET['q'] ?? '');
$sqlBase = 'SELECT d.Ma_danh_muc AS id, d.Ten_danh_muc AS name, d.Mo_ta AS description,
  (SELECT COUNT(*) FROM SanPham s WHERE s.Ma_danh_muc = d.Ma_danh_muc) AS product_count
  FROM DanhMucSanPham d';
$categories = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    if (isset($pdo)) {
        $stmt = $pdo->prepare($sqlBase . ' WHERE d.Ten_danh_muc LIKE ? OR IFNULL(d.Mo_ta, \'\') LIKE ? OR d.Ma_danh_muc LIKE ? ORDER BY d.Ma_danh_muc DESC');
        $stmt->execute([$like, $like, $like]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (isset($conn)) {
        $st = $conn->prepare($sqlBase . ' WHERE d.Ten_danh_muc LIKE ? OR IFNULL(d.Mo_ta, \'\') LIKE ? OR d.Ma_danh_muc LIKE ? ORDER BY d.Ma_danh_muc DESC');
        $st->bind_param('sss', $like, $like, $like);
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            $categories[] = $row;
        }
    }
} else {
    if (isset($pdo)) {
        $stmt = $pdo->query($sqlBase . ' ORDER BY d.Ma_danh_muc DESC');
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (isset($conn)) {
        $res = $conn->query($sqlBase . ' ORDER BY d.Ma_danh_muc DESC');
        while ($row = $res->fetch_assoc()) {
            $categories[] = $row;
        }
    }
}
?>

<div class="page-header">
  <div>
    <div class="title">QUẢN LÝ DANH MỤC</div>
    <div class="subtitle">Quản lý danh mục sản phẩm</div>
  </div>
  <div class="header-actions">
    <form method="get" action="" class="admin-inline-search" style="display:flex;align-items:center;gap:8px;margin:0;">
      <input type="search" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Tìm danh mục…" class="search-input" aria-label="Tìm danh mục">
      <button type="submit" class="add-btn" style="padding:8px 14px;">Tìm</button>
      <?php if ($q !== ''): ?>
        <a href="categories.php" class="icon-btn" style="text-decoration:none;padding:8px 12px;white-space:nowrap;">Xóa lọc</a>
      <?php endif; ?>
    </form>
    <button type="button" class="add-btn" onclick="openAdd()">+ THÊM DANH MỤC MỚI</button>
  </div>
</div>

  <?php if (!empty($message)): ?>
    <div style="margin:12px 0; color:#6b3f3f; background:#fff6f6; padding:8px; border-radius:8px"><?php echo htmlspecialchars($message); ?></div>
  <?php elseif (!empty($_GET['edited'])): ?>
    <div style="margin:12px 0; color:#6b3f3f; background:#fff6f6; padding:8px; border-radius:8px">Đã cập nhật danh mục.</div>
  <?php endif; ?>

<section id="addSection" style="margin-bottom:18px; display:none">
  <form method="post" action="" style="display:flex; gap:12px; align-items:flex-end;">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="action" value="add">
    <div style="flex:1">
      <label style="display:block">Tên<br><input name="ten" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #f1dede"></label>
    </div>
    <div style="flex:2">
      <label style="display:block">Mô tả<br><input name="mo_ta" style="width:100%; padding:10px; border-radius:8px; border:1px solid #f1dede"></label>
    </div>
    <div>
      <button class="add-btn" type="submit">Lưu</button>
      <button type="button" class="icon-btn" onclick="closeAdd()">Hủy</button>
    </div>
  </form>
</section>

<section>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>Tên Danh Mục</th><th>Mô tả</th><th>Số Lượng Sản Phẩm</th><th>Trạng Thái</th><th>Hành Động</th></tr>
      </thead>
      <tbody>
        <?php foreach ($categories as $c): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <?php
                $nm = (string)($c['name'] ?? '');
                $initial = $nm !== ''
                  ? (function_exists('mb_substr') ? mb_substr($nm, 0, 1, 'UTF-8') : substr($nm, 0, 1))
                  : '?';
                ?>
                <div class="thumbnail" style="display:flex;align-items:center;justify-content:center;margin:0;background:#f5ebe9;color:#6b3f3f;font-weight:600;font-size:1rem" title=""><?php echo htmlspecialchars($initial); ?></div>
                <div><?php echo htmlspecialchars($c['name']);?></div>
              </div>
            </td>
            <td><?php echo htmlspecialchars($c['description'] ?? '');?></td>
            <td><?php echo (int)($c['product_count'] ?? 0); ?></td>
            <td><span class="badge">Hiển thị</span></td>
            <td>
              <div class="action-icons">
                <button type="button" class="icon-btn cat-edit"
                  data-id="<?php echo htmlspecialchars($c['id'], ENT_QUOTES);?>"
                  data-name="<?php echo htmlspecialchars($c['name'], ENT_QUOTES);?>"
                  data-desc="<?php echo htmlspecialchars($c['description'], ENT_QUOTES);?>"
                >✏️</button>
                <form method="post" style="display:inline" onsubmit="return confirm('Xác nhận xóa?');">
                  <?php echo csrf_input(); ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo htmlspecialchars($c['id']);?>">
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

<div id="editModal" style="display:none">
  <div class="modal-overlay" role="presentation" onclick="if(event.target===this) document.getElementById('editModal').style.display='none'">
    <div class="modal-panel panel" style="max-width:420px" onclick="event.stopPropagation()">
      <h3>Sửa danh mục</h3>
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="ma" id="edit_ma">
        <div class="form-row column">
          <label>Tên<br><input class="form-input" name="ten" id="edit_ten" required></label>
        </div>
        <div class="form-row column">
          <label>Mô tả<br><textarea class="form-textarea" name="mo_ta" id="edit_mo_ta"></textarea></label>
        </div>
        <div class="modal-actions">
          <button class="add-btn" type="submit">Lưu</button>
          <button class="icon-btn" type="button" onclick="closeEditModal()">Hủy</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.cat-edit').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openEdit(this.getAttribute('data-id') || '', this.getAttribute('data-name') || '', this.getAttribute('data-desc') || '');
    });
  });
});
function openEdit(ma, ten, mota) {
  document.getElementById('edit_ma').value = ma;
  document.getElementById('edit_ten').value = ten;
  document.getElementById('edit_mo_ta').value = mota;
  // debug banner to confirm function ran
  var dbg = document.getElementById('debugBanner');
  if (!dbg) {
    dbg = document.createElement('div');
    dbg.id = 'debugBanner';
    dbg.style.position = 'fixed'; dbg.style.right = '18px'; dbg.style.top = '18px'; dbg.style.background = '#ffdede'; dbg.style.color='#6b3f3f'; dbg.style.padding='8px 12px'; dbg.style.borderRadius='8px'; dbg.style.zIndex = 9999;
    document.body.appendChild(dbg);
  }
  dbg.textContent = 'Opening edit: ' + ma;
  setTimeout(function(){ if (dbg) dbg.style.display='none'; }, 1500);
  document.getElementById('editModal').style.display = 'block';
}
function closeEditModal(){ document.getElementById('editModal').style.display='none'; }
function openAdd(){ document.getElementById('addSection').style.display='block'; window.scrollTo({top:0, behavior:'smooth'}); }
function closeAdd(){ document.getElementById('addSection').style.display='none'; }
</script>

<?php include 'includes/footer.php';
