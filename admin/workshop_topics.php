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
$statuses = workshop_topic_statuses();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!check_csrf($_POST['_csrf'] ?? '')) {
        die('CSRF token không hợp lệ');
    }
    $act = $_POST['action'];

    if ($act === 'add_topic') {
        $ten = trim((string)($_POST['ten_chu_de'] ?? ''));
        $mo_ta = trim((string)($_POST['mo_ta'] ?? ''));
        $gia = (float)str_replace(',', '.', (string)($_POST['gia'] ?? '0'));
        $dia_diem = trim((string)($_POST['dia_diem'] ?? ''));
        $thoi_gian_mt = trim((string)($_POST['thoi_gian_mo_ta'] ?? ''));
        $so_mac_dinh = max(1, (int)($_POST['so_luong_mac_dinh'] ?? 30));
        $trang = $_POST['trang_thai'] ?? 'Active';
        if (!isset($statuses[$trang])) {
            $trang = 'Active';
        }
        if ($ten === '') {
            $message = 'Vui lòng nhập tên workshop.';
        } else {
            $okIns = false;
            $lastErr = '';
            $lastNo = 0;
            $ma = '';

            for ($t = 0; $t < 10; $t++) {
                $ma = workshop_gen_ma_chu_de($conn);
                $st = $conn->prepare('INSERT INTO chudeworkshop (Ma_chu_de, Ten_chu_de, Mo_ta, Gia, Trang_thai, Dia_diem, Thoi_gian_mo_ta, So_luong_mac_dinh) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                if (!$st) {
                    $lastErr = $conn->error;
                    $lastNo = (int)$conn->errno;
                    break;
                }
                $st->bind_param('sssdsssi', $ma, $ten, $mo_ta, $gia, $trang, $dia_diem, $thoi_gian_mt, $so_mac_dinh);
                $okIns = $st->execute();
                if ($okIns) {
                    break;
                }
                $lastErr = $st->error ?: $conn->error;
                $lastNo = (int)($st->errno ?: $conn->errno);

                // Trùng khóa chính → thử sinh mã khác
                if ($lastNo === 1062) {
                    continue;
                }

                // Thiếu cột do schema cũ → fallback insert tối giản
                if (stripos($lastErr, 'Unknown column') !== false) {
                    $st2 = $conn->prepare('INSERT INTO chudeworkshop (Ma_chu_de, Ten_chu_de, Mo_ta, Gia, Trang_thai) VALUES (?, ?, ?, ?, ?)');
                    if (!$st2) {
                        $lastErr = $conn->error;
                        $lastNo = (int)$conn->errno;
                        break;
                    }
                    $st2->bind_param(str_repeat('s', 3) . 'ds', $ma, $ten, $mo_ta, $gia, $trang);
                    $okIns = $st2->execute();
                    if ($okIns) {
                        break;
                    }
                    $lastErr = $st2->error ?: $conn->error;
                    $lastNo = (int)($st2->errno ?: $conn->errno);
                }

                break;
            }

            if ($okIns) {
                header('Location: workshop_topics.php?ok=1');
                exit;
            }
            $detail = $lastErr !== '' ? (' (errno ' . (int)$lastNo . ') ' . $lastErr) : '';
            $message = 'Lỗi lưu chủ đề' . ($ma !== '' ? (' [' . $ma . ']') : '') . ': ' . htmlspecialchars($detail !== '' ? $detail : 'Không rõ lỗi.');
        }
    }

    if ($act === 'edit_topic') {
        $ma = trim((string)($_POST['ma_chu_de'] ?? ''));
        $ten = trim((string)($_POST['ten_chu_de'] ?? ''));
        $mo_ta = trim((string)($_POST['mo_ta'] ?? ''));
        $gia = (float)str_replace(',', '.', (string)($_POST['gia'] ?? '0'));
        $dia_diem = trim((string)($_POST['dia_diem'] ?? ''));
        $thoi_gian_mt = trim((string)($_POST['thoi_gian_mo_ta'] ?? ''));
        $so_mac_dinh = max(1, (int)($_POST['so_luong_mac_dinh'] ?? 30));
        $trang = $_POST['trang_thai'] ?? 'Active';
        if (!isset($statuses[$trang])) {
            $trang = 'Active';
        }
        if ($ma === '' || $ten === '') {
            $message = 'Thiếu dữ liệu.';
        } else {
            $st = $conn->prepare('UPDATE chudeworkshop SET Ten_chu_de=?, Mo_ta=?, Gia=?, Trang_thai=?, Dia_diem=?, Thoi_gian_mo_ta=?, So_luong_mac_dinh=? WHERE Ma_chu_de=?');
            $okUp = false;
            if ($st) {
                $st->bind_param('ssdsssis', $ten, $mo_ta, $gia, $trang, $dia_diem, $thoi_gian_mt, $so_mac_dinh, $ma);
                $okUp = $st->execute();
                if (!$okUp && stripos($st->error . $conn->error, 'Unknown column') !== false) {
                    $st2 = $conn->prepare('UPDATE chudeworkshop SET Ten_chu_de=?, Mo_ta=?, Gia=?, Trang_thai=? WHERE Ma_chu_de=?');
                    if ($st2) {
                        $st2->bind_param('ssdss', $ten, $mo_ta, $gia, $trang, $ma);
                        $okUp = $st2->execute();
                    }
                }
            }
            if ($okUp) {
                header('Location: workshop_topics.php?ok=1');
                exit;
            }
            $stErr = $st ? ($st->error ?? '') : '';
            $message = 'Lỗi cập nhật: ' . htmlspecialchars((string)($conn->error ?: $stErr));
        }
    }

    if ($act === 'update_status') {
        $ma = trim((string)($_POST['ma_chu_de'] ?? ''));
        $trang = $_POST['trang_thai'] ?? '';
        if ($ma !== '' && isset($statuses[$trang])) {
            $st = $conn->prepare('UPDATE chudeworkshop SET Trang_thai = ? WHERE Ma_chu_de = ?');
            $st->bind_param('ss', $trang, $ma);
            $st->execute();
        }
        header('Location: workshop_topics.php');
        exit;
    }
}

if (isset($_GET['ok'])) {
    $message = 'Đã lưu thành công.';
}

$topics = [];
$res = $conn->query('SELECT * FROM chudeworkshop ORDER BY Ma_chu_de DESC');
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $topics[] = $r;
    }
}
?>
<div class="page-header">
  <div>
    <div class="title">QUẢN LÝ CHỦ ĐỀ WORKSHOP</div>
  </div>
  <div class="header-actions">
    <a href="workshop.php" class="icon-btn" style="text-decoration:none;padding:10px 14px;">← Workshop</a>
    <button type="button" class="add-btn" onclick="document.getElementById('addModal').style.display='block'">+ THÊM CHỦ ĐỀ</button>
  </div>
</div>

<?php if ($message): ?>
  <div style="margin-bottom:12px;padding:10px 14px;border-radius:10px;background:#fff6f6;color:#6b3f3f;"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<section>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Mã</th>
          <th>Tên workshop</th>
          <th>Thời gian (mô tả)</th>
          <th>Địa điểm</th>
          <th>SL mặc định / buổi</th>
          <th>Giá / người</th>
          <th>Trạng thái</th>
          <th>Hành động</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($topics as $t): ?>
          <tr>
            <td><?php echo htmlspecialchars($t['Ma_chu_de'] ?? ''); ?></td>
            <td><strong><?php echo htmlspecialchars($t['Ten_chu_de'] ?? ''); ?></strong>
              <div style="font-size:12px;color:#a88;max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?php echo htmlspecialchars($t['Mo_ta'] ?? ''); ?>"><?php echo htmlspecialchars($t['Mo_ta'] ?? ''); ?></div>
            </td>
            <td><?php echo htmlspecialchars($t['Thoi_gian_mo_ta'] ?? '—'); ?></td>
            <td><?php echo htmlspecialchars($t['Dia_diem'] ?? '—'); ?></td>
            <td><?php echo (int)($t['So_luong_mac_dinh'] ?? 30); ?></td>
            <td><?php echo number_format((float)($t['Gia'] ?? 0), 0, ',', '.'); ?>đ</td>
            <td><span class="badge"><?php echo htmlspecialchars($statuses[$t['Trang_thai'] ?? ''] ?? ($t['Trang_thai'] ?? '')); ?></span></td>
            <td>
              <div class="action-icons" style="flex-wrap:wrap;gap:6px;">
                <button type="button" class="icon-btn btn-edit-topic" title="Sửa"
                  data-ma="<?php echo htmlspecialchars($t['Ma_chu_de'], ENT_QUOTES); ?>"
                  data-ten="<?php echo htmlspecialchars($t['Ten_chu_de'], ENT_QUOTES); ?>"
                  data-mota="<?php echo htmlspecialchars($t['Mo_ta'] ?? '', ENT_QUOTES); ?>"
                  data-gia="<?php echo htmlspecialchars((string)($t['Gia'] ?? 0), ENT_QUOTES); ?>"
                  data-dia="<?php echo htmlspecialchars($t['Dia_diem'] ?? '', ENT_QUOTES); ?>"
                  data-tg="<?php echo htmlspecialchars($t['Thoi_gian_mo_ta'] ?? '', ENT_QUOTES); ?>"
                  data-sl="<?php echo (int)($t['So_luong_mac_dinh'] ?? 30); ?>"
                  data-tt="<?php echo htmlspecialchars($t['Trang_thai'] ?? 'Active', ENT_QUOTES); ?>"
                >✏️</button>
                <form method="post" style="display:inline" onsubmit="return confirm('Đổi trạng thái chủ đề này?');">
                  <?php echo csrf_input(); ?>
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="ma_chu_de" value="<?php echo htmlspecialchars($t['Ma_chu_de']); ?>">
                  <select name="trang_thai" class="form-select" style="max-width:140px;padding:6px;font-size:12px;" onchange="this.form.submit()">
                    <?php foreach ($statuses as $k => $lab): ?>
                      <option value="<?php echo htmlspecialchars($k); ?>" <?php echo (($t['Trang_thai'] ?? '') === $k) ? 'selected' : ''; ?>><?php echo htmlspecialchars($lab); ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($topics === []): ?>
          <tr><td colspan="8" style="color:#a88;">Chưa có chủ đề. Thêm mới hoặc import CSDL.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- Thêm -->
<div id="addModal" style="display:none">
  <div class="modal-overlay" role="presentation" onclick="if(event.target===this) document.getElementById('addModal').style.display='none'">
    <div class="modal-panel panel" style="max-width:520px" onclick="event.stopPropagation()">
      <h3>Thêm chủ đề workshop</h3>
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="add_topic">
        <div class="form-row column"><label>Tên workshop *<br><input class="form-input" name="ten_chu_de" required></label></div>
        <div class="form-row column"><label>Thời gian (mô tả, VD 14:00–17:00 Thứ 7)<br><input class="form-input" name="thoi_gian_mo_ta" placeholder=""></label></div>
        <div class="form-row column"><label>Địa điểm<br><input class="form-input" name="dia_diem" placeholder=""></label></div>
        <div class="form-row column"><label>Số lượng người tham gia (mặc định / buổi)<br><input class="form-input" type="number" name="so_luong_mac_dinh" value="30" min="1"></label></div>
        <div class="form-row column"><label>Mô tả<br><textarea class="form-textarea" name="mo_ta" rows="3"></textarea></label></div>
        <div class="form-row column"><label>Chi phí tham gia (VNĐ / người)<br><input class="form-input" type="number" step="0.01" name="gia" value="0" min="0"></label></div>
        <div class="form-row column"><label>Trạng thái<br>
          <select class="form-select" name="trang_thai"><?php foreach ($statuses as $k => $lab): ?><option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($lab); ?></option><?php endforeach; ?></select>
        </label></div>
        <div class="modal-actions">
          <button class="add-btn" type="submit">Lưu</button>
          <button type="button" class="icon-btn" onclick="document.getElementById('addModal').style.display='none'">Hủy</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Sửa -->
<div id="editModal" style="display:none">
  <div class="modal-overlay" role="presentation" onclick="if(event.target===this) document.getElementById('editModal').style.display='none'">
    <div class="modal-panel panel" style="max-width:520px" onclick="event.stopPropagation()">
      <h3>Sửa chủ đề workshop</h3>
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="edit_topic">
        <input type="hidden" name="ma_chu_de" id="edit_ma">
        <div class="form-row column"><label>Tên workshop *<br><input class="form-input" name="ten_chu_de" id="edit_ten" required></label></div>
        <div class="form-row column"><label>Thời gian (mô tả)<br><input class="form-input" name="thoi_gian_mo_ta" id="edit_tg"></label></div>
        <div class="form-row column"><label>Địa điểm<br><input class="form-input" name="dia_diem" id="edit_dia"></label></div>
        <div class="form-row column"><label>Số lượng mặc định / buổi<br><input class="form-input" type="number" name="so_luong_mac_dinh" id="edit_sl" min="1"></label></div>
        <div class="form-row column"><label>Mô tả<br><textarea class="form-textarea" name="mo_ta" id="edit_mota" rows="3"></textarea></label></div>
        <div class="form-row column"><label>Chi phí (VNĐ / người)<br><input class="form-input" type="number" step="0.01" name="gia" id="edit_gia" min="0"></label></div>
        <div class="form-row column"><label>Trạng thái<br><select class="form-select" name="trang_thai" id="edit_tt"><?php foreach ($statuses as $k => $lab): ?><option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($lab); ?></option><?php endforeach; ?></select></label></div>
        <div class="modal-actions">
          <button class="add-btn" type="submit">Lưu</button>
          <button type="button" class="icon-btn" onclick="document.getElementById('editModal').style.display='none'">Hủy</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.btn-edit-topic').forEach(function (btn) {
  btn.addEventListener('click', function () {
    document.getElementById('edit_ma').value = btn.getAttribute('data-ma') || '';
    document.getElementById('edit_ten').value = btn.getAttribute('data-ten') || '';
    document.getElementById('edit_mota').value = btn.getAttribute('data-mota') || '';
    document.getElementById('edit_gia').value = btn.getAttribute('data-gia') || '0';
    document.getElementById('edit_dia').value = btn.getAttribute('data-dia') || '';
    document.getElementById('edit_tg').value = btn.getAttribute('data-tg') || '';
    document.getElementById('edit_sl').value = btn.getAttribute('data-sl') || '30';
    var tt = btn.getAttribute('data-tt') || 'Active';
    var sel = document.getElementById('edit_tt');
    if (sel) sel.value = tt;
    document.getElementById('editModal').style.display = 'block';
  });
});
</script>

<?php include 'includes/footer.php';
