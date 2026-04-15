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

function customer_table_name(mysqli $conn): string
{
    foreach (['KhachHang', 'khachhang'] as $tb) {
        $chk = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tb) . "'");
        if ($chk && $chk->num_rows > 0) {
            return $tb;
        }
    }
    return 'KhachHang';
}

/**
 * Xóa khách hàng: tìm mọi bảng có cột ma_khach_hang (mọi kiểu tên bảng), xóa con trước, bảng khách sau.
 * Tạm tắt FOREIGN_KEY_CHECKS trong session để không vướng thứ tự / FK khai báo sai trong CSDL.
 *
 * @return array{0:bool,1:string}
 */
function delete_khach_hang_cascade(mysqli $conn, string $ma): array
{
    $ma = trim($ma);
    if ($ma === '') {
        return [false, 'Mã khách hàng không hợp lệ.'];
    }

    $conn->begin_transaction();
    $fkWas = 1;
    try {
        $chkRes = $conn->query('SELECT @@SESSION.foreign_key_checks AS v');
        if ($chkRes) {
            $rowFk = $chkRes->fetch_assoc();
            $fkWas = (int)($rowFk['v'] ?? 1);
        }
        if (!$conn->query('SET SESSION foreign_key_checks = 0')) {
            throw new RuntimeException($conn->error);
        }

        $sql = "SELECT c.TABLE_NAME, c.COLUMN_NAME
                FROM information_schema.COLUMNS c
                INNER JOIN information_schema.TABLES t
                  ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
                WHERE c.TABLE_SCHEMA = DATABASE()
                AND LOWER(c.COLUMN_NAME) = 'ma_khach_hang'
                AND t.TABLE_TYPE = 'BASE TABLE'";
        $res = $conn->query($sql);
        if (!$res) {
            throw new RuntimeException($conn->error);
        }

        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }

        if ($rows === []) {
            foreach (['KhachHang', 'khachhang'] as $tKh) {
                $chk = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tKh) . "'");
                if ($chk && $chk->num_rows > 0) {
                    $rows[] = ['TABLE_NAME' => $tKh, 'COLUMN_NAME' => 'Ma_khach_hang'];
                    break;
                }
            }
        }

        foreach ($rows as $row) {
            if (strtolower((string)$row['TABLE_NAME']) === 'khachhang') {
                continue;
            }
            $tn = $row['TABLE_NAME'];
            $cn = $row['COLUMN_NAME'];
            $st = $conn->prepare("DELETE FROM `{$tn}` WHERE `{$cn}` = ?");
            if ($st) {
                $st->bind_param('s', $ma);
                $st->execute();
            }
        }

        $deletedParent = false;
        foreach ($rows as $row) {
            if (strtolower((string)$row['TABLE_NAME']) !== 'khachhang') {
                continue;
            }
            $tn = $row['TABLE_NAME'];
            $cn = $row['COLUMN_NAME'];
            $d = $conn->prepare("DELETE FROM `{$tn}` WHERE `{$cn}` = ?");
            if (!$d) {
                throw new RuntimeException($conn->error);
            }
            $d->bind_param('s', $ma);
            $d->execute();
            if ($d->affected_rows > 0) {
                $deletedParent = true;
            }
        }

        if (!$deletedParent) {
            foreach (['KhachHang', 'khachhang'] as $tKh) {
                $chk = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tKh) . "'");
                if (!$chk || $chk->num_rows === 0) {
                    continue;
                }
                $d = $conn->prepare("DELETE FROM `{$tKh}` WHERE Ma_khach_hang = ?");
                if ($d) {
                    $d->bind_param('s', $ma);
                    $d->execute();
                    if ($d->affected_rows > 0) {
                        $deletedParent = true;
                    }
                }
                break;
            }
        }

        $conn->query('SET SESSION foreign_key_checks = ' . ($fkWas ? '1' : '0'));

        if (!$deletedParent) {
            $conn->rollback();
            return [false, 'Không tìm thấy khách hàng (mã không khớp) hoặc không có cột Ma_khach_hang trong CSDL.'];
        }

        $conn->commit();
        return [true, ''];
    } catch (Throwable $e) {
        $conn->rollback();
        $conn->query('SET SESSION foreign_key_checks = ' . ($fkWas ? '1' : '0'));
        return [false, $e->getMessage()];
    }
}

// Handle add / edit / delete
$message = '';
$khTable = customer_table_name($conn);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if (!check_csrf($_POST['_csrf'] ?? '')) die('CSRF token không hợp lệ');
  $action = $_POST['action'];
    if ($action === 'add') {
        $ten = $_POST['ten'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $phoneNorm = preg_replace('/\D+/', '', (string)$phone);
        if ($phoneNorm === '') {
            $message = 'Số điện thoại không hợp lệ.';
        } else {
            // Chặn trùng SĐT: nếu đã có trong CSDL thì không cho thêm.
            $exists = false;
            try {
                if (isset($pdo)) {
                    $chk = $pdo->prepare('SELECT 1 FROM KhachHang WHERE REPLACE(REPLACE(REPLACE(So_dien_thoai, " ", ""), ".", ""), "-", "") = ? LIMIT 1');
                    $chk->execute([$phoneNorm]);
                    $exists = (bool)$chk->fetchColumn();
                } else {
                    $sqlChk = "SELECT 1
                      FROM `{$khTable}`
                      WHERE REPLACE(REPLACE(REPLACE(So_dien_thoai, ' ', ''), '.', ''), '-', '') = ?
                      LIMIT 1";
                    $chk = $conn->prepare($sqlChk);
                    if ($chk) {
                        $chk->bind_param('s', $phoneNorm);
                        $chk->execute();
                        $res = $chk->get_result();
                        $exists = ($res && $res->num_rows > 0);
                    }
                }
            } catch (Throwable $e) {
                // Nếu check lỗi thì coi như không tồn tại, để insert bắt lỗi (nhưng vẫn báo rõ khi insert fail).
            }

            if ($exists) {
                $message = 'Không thể thêm: Số điện thoại đã tồn tại trong hệ thống.';
            } else {
        // Giữ form thêm khách đơn giản: không nhập mật khẩu.
        // Nếu DB bắt buộc Mat_khau thì tự sinh mật khẩu mặc định nội bộ.
        $passwordSeed = 'KH@' . preg_replace('/\D+/', '', $phone) . '#123';
        $passwordHash = password_hash($passwordSeed, PASSWORD_DEFAULT);
        try {
            if (isset($pdo)) {
                $ma = genCode($pdo, 'KH', 'KhachHang', 'Ma_khach_hang');
                try {
                    $stmt = $pdo->prepare('INSERT INTO KhachHang (Ma_khach_hang, Ten_khach_hang, So_dien_thoai, Email, Mat_khau, Thoi_gian_tao) VALUES (?, ?, ?, ?, ?, NOW())');
                    $stmt->execute([$ma, $ten, $phone, $email, $passwordHash]);
                } catch (Throwable $e) {
                    $stmt = $pdo->prepare('INSERT INTO KhachHang (Ma_khach_hang, Ten_khach_hang, So_dien_thoai, Email, Mat_khau) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$ma, $ten, $phone, $email, $passwordHash]);
                }
            } else {
                $ma = genCode($conn, 'KH', $khTable, 'Ma_khach_hang');
                $stmt = $conn->prepare("INSERT INTO `{$khTable}` (Ma_khach_hang, Ten_khach_hang, So_dien_thoai, Email, Mat_khau, Thoi_gian_tao) VALUES (?, ?, ?, ?, ?, NOW())");
                if ($stmt) {
                    $stmt->bind_param('sssss', $ma, $ten, $phone, $email, $passwordHash);
                    $stmt->execute();
                } else {
                    $stmt2 = $conn->prepare("INSERT INTO `{$khTable}` (Ma_khach_hang, Ten_khach_hang, So_dien_thoai, Email, Mat_khau) VALUES (?, ?, ?, ?, ?)");
                    if (!$stmt2) {
                        throw new RuntimeException($conn->error);
                    }
                    $stmt2->bind_param('sssss', $ma, $ten, $phone, $email, $passwordHash);
                    $stmt2->execute();
                }
            }
            $message = 'Đã thêm khách hàng.';
        } catch (Throwable $e) {
            $message = 'Thêm khách hàng lỗi: ' . $e->getMessage();
        }
            }
        }
    } elseif ($action === 'edit') {
        $ma = $_POST['ma'] ?? '';
        $ten = $_POST['ten'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        if (isset($pdo)) {
            $u = $pdo->prepare('UPDATE KhachHang SET Ten_khach_hang = ?, So_dien_thoai = ?, Email = ? WHERE Ma_khach_hang = ?');
            $u->execute([$ten, $phone, $email, $ma]);
        } else {
            $u = $conn->prepare("UPDATE `{$khTable}` SET Ten_khach_hang = ?, So_dien_thoai = ?, Email = ? WHERE Ma_khach_hang = ?");
            $u->bind_param('ssss', $ten, $phone, $email, $ma);
            $u->execute();
        }
        $message = 'Đã cập nhật.';
    } elseif ($action === 'delete') {
        $ma = trim((string)($_POST['ma'] ?? ''));
        if (isset($pdo)) {
            try {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('DELETE FROM dangkyworkshop WHERE Ma_khach_hang = ?')->execute([$ma]);
                } catch (PDOException $e) {
                }
                try {
                    $st = $pdo->prepare('SELECT Ma_don_hang FROM DonHang WHERE Ma_khach_hang = ?');
                    $st->execute([$ma]);
                    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                        $madh = $row['Ma_don_hang'];
                        try {
                            $pdo->prepare('DELETE FROM ChiTietDonHang WHERE Ma_don_hang = ?')->execute([$madh]);
                        } catch (PDOException $e) {
                        }
                        $pdo->prepare('DELETE FROM DonHang WHERE Ma_don_hang = ?')->execute([$madh]);
                    }
                } catch (PDOException $e) {
                }
                try {
                    $pdo->prepare('DELETE FROM KhachHang WHERE Ma_khach_hang = ?')->execute([$ma]);
                } catch (PDOException $e) {
                    $pdo->prepare('DELETE FROM khachhang WHERE Ma_khach_hang = ?')->execute([$ma]);
                }
                $pdo->commit();
                $message = 'Đã xóa.';
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $message = 'Không xóa được: ' . $e->getMessage();
            }
        } else {
            [$ok, $err] = delete_khach_hang_cascade($conn, $ma);
            $message = $ok ? 'Đã xóa.' : ('Không xóa được: ' . $err);
        }
    }
}

// Search
$q = trim($_GET['q'] ?? '');
$customers = [];
if ($q !== '') {
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT * FROM KhachHang WHERE Ten_khach_hang LIKE ? OR So_dien_thoai LIKE ? ORDER BY Thoi_gian_tao DESC");
        $like = "%$q%";
        $stmt->execute([$like, $like]);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $like = "%$q%";
        $st = $conn->prepare("SELECT * FROM `{$khTable}` WHERE Ten_khach_hang LIKE ? OR So_dien_thoai LIKE ? ORDER BY Thoi_gian_tao DESC");
        $st->bind_param('ss', $like, $like);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) $customers[] = $r;
    }
} else {
    if (isset($pdo)) {
        $stmt = $pdo->query('SELECT * FROM KhachHang ORDER BY Thoi_gian_tao DESC LIMIT 100');
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $res = $conn->query("SELECT * FROM `{$khTable}` ORDER BY Thoi_gian_tao DESC LIMIT 100");
        while ($r = $res->fetch_assoc()) $customers[] = $r;
    }
}

?>

<div class="page-header">
  <div>
    <div class="title">QUẢN LÝ KHÁCH HÀNG</div>
    <div class="subtitle">Danh sách khách hàng và thông tin liên hệ</div>
  </div>
  <div class="header-actions">
    <button type="button" class="add-btn" onclick="openAddCustomer()">+ THÊM KHÁCH HÀNG MỚI</button>
  </div>
</div>

<?php if ($message): ?><div style="margin-bottom:12px; color:#6b3f3f;"> <?php echo htmlspecialchars($message);?> </div><?php endif; ?>

<section id="addSectionCust" style="margin-bottom:18px; display:none">
  <form method="post" style="display:flex; gap:12px; align-items:flex-end;">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="action" value="add">
    <div style="flex:2">
      <label style="display:block">Tên<br><input name="ten" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #f1dede"></label>
    </div>
    <div style="width:200px">
      <label style="display:block">Điện thoại<br><input name="phone" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #f1dede"></label>
    </div>
    <div style="width:260px">
      <label style="display:block">Email<br><input name="email" style="width:100%; padding:10px; border-radius:8px; border:1px solid #f1dede"></label>
    </div>
    <div>
      <button class="add-btn" type="submit">Thêm</button>
      <button type="button" class="icon-btn" onclick="closeAddCustomer()">Hủy</button>
    </div>
  </form>
</section>

<section>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>ID KH</th><th>Tên Khách Hàng</th><th>Email</th><th>Số Điện Thoại</th><th>Thời gian</th><th>Hành động</th></tr>
      </thead>
      <tbody>
        <?php foreach ($customers as $c): ?>
          <tr>
            <td><?php echo htmlspecialchars($c['Ma_khach_hang']);?></td>
            <td><?php echo htmlspecialchars($c['Ten_khach_hang']);?></td>
            <td><?php echo htmlspecialchars($c['Email']);?></td>
            <td><?php echo htmlspecialchars($c['So_dien_thoai']);?></td>
            <td><?php echo htmlspecialchars($c['Thoi_gian_tao']);?></td>
            <td>
              <div class="action-icons">
                <form method="post" style="display:inline">
                  <?php echo csrf_input(); ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="ma" value="<?php echo htmlspecialchars($c['Ma_khach_hang']);?>">
                  <button class="icon-btn" type="submit" onclick="return confirm('Xác nhận xóa?')">🗑️</button>
                </form>
                <button type="button" class="icon-btn" onclick="openEdit('<?php echo htmlspecialchars($c['Ma_khach_hang']);?>','<?php echo htmlspecialchars(addslashes($c['Ten_khach_hang']));?>','<?php echo htmlspecialchars($c['So_dien_thoai']);?>','<?php echo htmlspecialchars($c['Email']);?>')">✏️</button>
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
      <h3>Sửa khách hàng</h3>
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="ma" id="edit_ma">
        <div class="form-row column">
          <label>Tên<br><input class="form-input" name="ten" id="edit_ten" required></label>
        </div>
        <div class="form-row column">
          <label>Điện thoại<br><input class="form-input" name="phone" id="edit_phone" required></label>
        </div>
        <div class="form-row column">
          <label>Email<br><input class="form-input" name="email" id="edit_email"></label>
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
function openEdit(ma, ten, phone, email){
  document.getElementById('edit_ma').value = ma;
  document.getElementById('edit_ten').value = ten;
  document.getElementById('edit_phone').value = phone;
  document.getElementById('edit_email').value = email;
  document.getElementById('editModal').style.display = 'block';
}
function openAddCustomer(){ document.getElementById('addSectionCust').style.display='block'; window.scrollTo({top:0, behavior:'smooth'}); }
function closeAddCustomer(){ document.getElementById('addSectionCust').style.display='none'; }
</script>

<?php include 'includes/footer.php';
