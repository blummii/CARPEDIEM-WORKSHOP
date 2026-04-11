<?php
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/csrf.php';
include 'includes/header.php';

$message = '';

if (!empty($_SESSION['accounts_msg'])) {
    $message = (string)$_SESSION['accounts_msg'];
    unset($_SESSION['accounts_msg']);
}

// Detect columns
$roleCol = null;
$roleLabel = 'Quyền';
$cols = [];
$colsRes = $conn->query('SHOW COLUMNS FROM Admins');
if ($colsRes) {
    while ($c = $colsRes->fetch_assoc()) {
        $cols[] = $c['Field'];
    }
}
$candidates = ['Role', 'Quyen', 'quyen', 'Phan_quyen', 'phan_quyen', 'Loai_admin', 'Permission', 'Permissions', 'Quyen_admin'];
foreach ($candidates as $cand) {
    if (in_array($cand, $cols, true)) {
        $roleCol = $cand;
        $roleLabel = $cand;
        break;
    }
}

// Tên cột mật khẩu trong MySQL có thể khác hoa/thường — so khớp không phân biệt
$pwdCol = null;
$pwdAliases = ['password_hash', 'password', 'mat_khau_hash', 'mat_khau', 'matkhau', 'mk_hash'];
foreach ($cols as $c) {
    if (in_array(strtolower($c), $pwdAliases, true)) {
        $pwdCol = $c;
        break;
    }
}

$maInfo = $conn->query("SHOW COLUMNS FROM Admins WHERE Field = 'Ma_admin'");
$maRow = $maInfo ? $maInfo->fetch_assoc() : null;
$maAdminAutoInc = $maRow && stripos($maRow['Extra'] ?? '', 'auto_increment') !== false;

// Gỡ admin dư / không dùng (không xóa chính mình; phải còn >1 admin)
$purgeUsernames = ['26a4041211@hvnh.edu.vn'];
$cntAll = $conn->query('SELECT COUNT(*) AS c FROM Admins');
$acTotal = (int)(($cntAll ? $cntAll->fetch_assoc() : [])['c'] ?? 0);
if ($acTotal > 1) {
    $selfId = trim((string)($_SESSION['admin_id'] ?? ''));
    foreach ($purgeUsernames as $pun) {
        $st = $conn->prepare('SELECT Ma_admin FROM Admins WHERE Username = ? LIMIT 1');
        if (!$st) {
            break;
        }
        $st->bind_param('s', $pun);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        if (!$row) {
            continue;
        }
        $mid = trim((string)($row['Ma_admin'] ?? ''));
        // Chỉ giữ lại nếu đúng là bản ghi của chính mình (có mã admin khớp session).
        // Trước đây bỏ qua cả khi Ma_admin rỗng → dòng lỗi không bao giờ bị xóa.
        if ($mid !== '' && $mid === $selfId) {
            continue;
        }
        $del = $conn->prepare('DELETE FROM Admins WHERE Username = ?');
        if ($del) {
            $del->bind_param('s', $pun);
            $del->execute();
        }
    }
    // Xóa dòng admin hỏng: Ma_admin NULL hoặc rỗng (ép CHAR + TRIM)
    $conn->query("DELETE FROM Admins WHERE TRIM(IFNULL(CAST(Ma_admin AS CHAR), '')) = ''");
}

function gen_admin_code(mysqli $conn, string $prefix = 'AD'): string
{
    $res = $conn->query('SELECT MAX(Ma_admin) AS m FROM Admins');
    $row = $res ? $res->fetch_assoc() : null;
    $m = $row['m'] ?? null;
    if ($m === null || $m === '') {
        return $prefix . '001';
    }
    $ms = (string)$m;
    if (preg_match('/^([A-Za-z]+)(\d+)$/', $ms, $mm)) {
        $p = $mm[1];
        $n = (int)$mm[2];
        $w = max(3, strlen($mm[2]));
        return $p . str_pad((string)($n + 1), $w, '0', STR_PAD_LEFT);
    }
    if (ctype_digit($ms)) {
        return (string)((int)$ms + 1);
    }
    return $prefix . '001';
}

// actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = $_POST['action'];
    if (!check_csrf($_POST['_csrf'] ?? '')) {
        die('CSRF token không hợp lệ');
    }

    if ($act === 'start_edit') {
        $mid = trim((string)($_POST['ma_admin'] ?? ''));
        if ($mid !== '') {
            $_SESSION['accounts_edit_ma'] = $mid;
            header('Location: accounts.php?edit=sess');
            exit;
        }
        header('Location: accounts.php');
        exit;
    }

    $username = trim($_POST['username'] ?? '');

    if ($act === 'add_admin') {
        $password = $_POST['password'] ?? '';
        $roleVal = trim((string)($_POST['role'] ?? ''));
        if ($roleCol && $roleVal === '') {
            $roleVal = 'admin';
        }

        if (!$pwdCol) {
            $message = 'Bảng Admins không có cột mật khẩu (Password_hash / …).';
        } elseif (!$username || !$password) {
            $message = 'Vui lòng nhập username và mật khẩu.';
        } else {
            $dup = $conn->prepare('SELECT 1 FROM Admins WHERE Username = ? LIMIT 1');
            $dup->bind_param('s', $username);
            $dup->execute();
            if ($dup->get_result()->fetch_row()) {
                $message = 'Username đã tồn tại.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ok = false;

                if ($maAdminAutoInc) {
                    if ($roleCol) {
                        $stmt = $conn->prepare("INSERT INTO Admins (Username, {$pwdCol}, {$roleCol}) VALUES (?, ?, ?)");
                        $stmt->bind_param('sss', $username, $hash, $roleVal);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO Admins (Username, {$pwdCol}) VALUES (?, ?)");
                        $stmt->bind_param('ss', $username, $hash);
                    }
                    $ok = $stmt->execute();
                } else {
                    $newMa = gen_admin_code($conn);
                    if ($roleCol) {
                        $stmt = $conn->prepare("INSERT INTO Admins (Ma_admin, Username, {$pwdCol}, {$roleCol}) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param('ssss', $newMa, $username, $hash, $roleVal);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO Admins (Ma_admin, Username, {$pwdCol}) VALUES (?, ?, ?)");
                        $stmt->bind_param('sss', $newMa, $username, $hash);
                    }
                    $ok = $stmt->execute();
                }

                if ($ok) {
                    header('Location: accounts.php');
                    exit;
                }
                $message = 'Không thể thêm tài khoản: ' . $conn->error;
            }
        }
    }

    if ($act === 'edit_admin') {
        $ma_admin = trim((string)($_POST['ma_admin'] ?? ''));
        $newPassword = (string)($_POST['password'] ?? '');
        $roleVal = $_POST['role'] ?? null;

        if (!$ma_admin || !$username) {
            $message = 'Dữ liệu không hợp lệ.';
        } elseif (!$pwdCol && $newPassword !== '') {
            $message = 'Bảng Admins không có cột mật khẩu — không thể đổi mật khẩu.';
        } else {
            $maType = strtolower($maRow['Type'] ?? '');
            $isIntMa = (bool)preg_match('/^(tinyint|smallint|mediumint|int|bigint)/', $maType);
            $stmt = null;

            if ($pwdCol) {
                if ($roleCol) {
                    if ($newPassword !== '') {
                        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE Admins SET Username = ?, {$pwdCol} = ?, {$roleCol} = ? WHERE Ma_admin = ?");
                        if ($stmt) {
                            if ($isIntMa) {
                                $mid = (int)$ma_admin;
                                $stmt->bind_param('sssi', $username, $hash, $roleVal, $mid);
                            } else {
                                $stmt->bind_param('ssss', $username, $hash, $roleVal, $ma_admin);
                            }
                        }
                    } else {
                        $stmt = $conn->prepare("UPDATE Admins SET Username = ?, {$roleCol} = ? WHERE Ma_admin = ?");
                        if ($stmt) {
                            if ($isIntMa) {
                                $mid = (int)$ma_admin;
                                $stmt->bind_param('ssi', $username, $roleVal, $mid);
                            } else {
                                $stmt->bind_param('sss', $username, $roleVal, $ma_admin);
                            }
                        }
                    }
                } elseif ($newPassword !== '') {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE Admins SET Username = ?, {$pwdCol} = ? WHERE Ma_admin = ?");
                    if ($stmt) {
                        if ($isIntMa) {
                            $mid = (int)$ma_admin;
                            $stmt->bind_param('ssi', $username, $hash, $mid);
                        } else {
                            $stmt->bind_param('sss', $username, $hash, $ma_admin);
                        }
                    }
                } else {
                    $stmt = $conn->prepare('UPDATE Admins SET Username = ? WHERE Ma_admin = ?');
                    if ($stmt) {
                        if ($isIntMa) {
                            $mid = (int)$ma_admin;
                            $stmt->bind_param('si', $username, $mid);
                        } else {
                            $stmt->bind_param('ss', $username, $ma_admin);
                        }
                    }
                }
            } elseif ($roleCol) {
                $stmt = $conn->prepare("UPDATE Admins SET Username = ?, {$roleCol} = ? WHERE Ma_admin = ?");
                if ($stmt) {
                    if ($isIntMa) {
                        $mid = (int)$ma_admin;
                        $stmt->bind_param('ssi', $username, $roleVal, $mid);
                    } else {
                        $stmt->bind_param('sss', $username, $roleVal, $ma_admin);
                    }
                }
            } else {
                $stmt = $conn->prepare('UPDATE Admins SET Username = ? WHERE Ma_admin = ?');
                if ($stmt) {
                    if ($isIntMa) {
                        $mid = (int)$ma_admin;
                        $stmt->bind_param('si', $username, $mid);
                    } else {
                        $stmt->bind_param('ss', $username, $ma_admin);
                    }
                }
            }

            if ($stmt && $stmt->execute()) {
                unset($_SESSION['accounts_edit_ma']);
                header('Location: accounts.php');
                exit;
            }
            $message = 'Không thể cập nhật: ' . ($stmt ? $stmt->error : $conn->error);
        }
    }

    if ($act === 'delete_admin') {
        $ma_admin = trim((string)($_POST['ma_admin'] ?? ''));
        if ($ma_admin === '') {
            header('Location: accounts.php');
            exit;
        }
        if ((string)$ma_admin === (string)($_SESSION['admin_id'] ?? '')) {
            $_SESSION['accounts_msg'] = 'Không thể xóa tài khoản bạn đang đăng nhập.';
            header('Location: accounts.php');
            exit;
        }
        $cntRes = $conn->query('SELECT COUNT(*) AS c FROM Admins');
        $cnt = (int)($cntRes->fetch_assoc()['c'] ?? 0);
        if ($cnt <= 1) {
            $_SESSION['accounts_msg'] = 'Phải giữ ít nhất một tài khoản admin.';
            header('Location: accounts.php');
            exit;
        }
        $stmt = $conn->prepare('DELETE FROM Admins WHERE Ma_admin = ?');
        if (!$stmt) {
            $_SESSION['accounts_msg'] = 'Lỗi chuẩn bị câu lệnh: ' . $conn->error;
            header('Location: accounts.php');
            exit;
        }
        $maType = strtolower($maRow['Type'] ?? '');
        if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)/', $maType)) {
            $mid = (int)$ma_admin;
            $stmt->bind_param('i', $mid);
        } else {
            $stmt->bind_param('s', $ma_admin);
        }
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                header('Location: accounts.php');
                exit;
            }
            $_SESSION['accounts_msg'] = 'Không xóa được (không có dòng nào bị xóa — kiểm tra mã admin). Chi tiết: ' . ($stmt->error ?: 'unknown');
        } else {
            $_SESSION['accounts_msg'] = 'Lỗi khi xóa: ' . ($stmt->error ?: $conn->error);
        }
        header('Location: accounts.php');
        exit;
    }
}

$editId = null;
if (($_GET['edit'] ?? '') === 'sess' && !empty($_SESSION['accounts_edit_ma'])) {
    $editId = (string)$_SESSION['accounts_edit_ma'];
} else {
    $e = $_GET['edit'] ?? null;
    if ($e !== null && $e !== '' && (string)$e !== 'sess') {
        $editId = (string)$e;
    }
}

if (!$editId) {
    unset($_SESSION['accounts_edit_ma']);
}

$admins = [];
if ($editId) {
    $colsSel = 'Ma_admin, Username';
    if ($roleCol) {
        $colsSel .= ", {$roleCol} AS role";
    }
    $st = $conn->prepare("SELECT {$colsSel} FROM Admins WHERE Ma_admin = ? LIMIT 1");
    $maType = strtolower($maRow['Type'] ?? '');
    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)/', $maType)) {
        $eid = (int)$editId;
        $st->bind_param('i', $eid);
    } else {
        $st->bind_param('s', $editId);
    }
    $st->execute();
    $editAdmin = $st->get_result()->fetch_assoc();
} else {
    $colsSel = 'Ma_admin, Username';
    if ($roleCol) {
        $colsSel .= ", {$roleCol} AS role";
    }
    $res = $conn->query("SELECT {$colsSel} FROM Admins ORDER BY Ma_admin DESC LIMIT 200");
    while ($r = $res->fetch_assoc()) {
        if (trim((string)($r['Ma_admin'] ?? '')) === '') {
            continue;
        }
        $admins[] = $r;
    }
}

$currentAdminId = trim((string)($_SESSION['admin_id'] ?? ''));
$adminCount = count($admins);

?>
<div class="page-header">
  <div>
    <div class="title">QUẢN LÝ TÀI KHOẢN</div>
    <div class="subtitle">Thêm, sửa và xóa tài khoản admin</div>
  </div>
  <?php if (!$editId): ?>
  <div class="header-actions">
    <button type="button" class="add-btn" onclick="document.getElementById('addAccountSection').scrollIntoView({behavior:'smooth', block:'start'})">+ THÊM TÀI KHOẢN</button>
  </div>
  <?php endif; ?>
</div>

<?php if ($message): ?>
  <div style="margin:0 0 14px; padding:10px 14px; border-radius:10px; background:#fff6f6; color:#6b3f3f;"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if (!$pwdCol && !$editId): ?>
  <div style="margin-bottom:14px; padding:10px 14px; border-radius:10px; background:#fff3e0; color:#7a4b2b;">
    Cảnh báo: không tìm thấy cột mật khẩu (Password_hash) trong bảng Admins — không thể thêm/sửa mật khẩu qua trang này.
  </div>
<?php endif; ?>

<?php if ($editId): ?>
  <div class="panel" style="max-width:480px;">
    <h3 style="margin-top:0">Sửa tài khoản</h3>
    <?php if (!empty($editAdmin)): ?>
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="edit_admin">
        <input type="hidden" name="ma_admin" value="<?php echo htmlspecialchars($editAdmin['Ma_admin']); ?>">

        <div class="form-row column">
          <label>Username<br><input class="form-input" name="username" required value="<?php echo htmlspecialchars($editAdmin['Username']); ?>"></label>
        </div>

        <?php if ($roleCol): ?>
        <div class="form-row column">
          <label><?php echo htmlspecialchars($roleLabel); ?><br><input class="form-input" name="role" value="<?php echo htmlspecialchars($editAdmin['role'] ?? ''); ?>"></label>
        </div>
        <?php endif; ?>

        <?php if ($pwdCol): ?>
        <div class="form-row column">
          <label>Mật khẩu mới (để trống nếu không đổi)<br><input class="form-input" name="password" type="password" autocomplete="new-password"></label>
        </div>
        <?php endif; ?>

        <div class="modal-actions" style="border-top:none; padding-top:8px; margin-top:8px;">
          <button class="add-btn" type="submit">Lưu</button>
          <a class="icon-btn" style="padding:10px 14px; border:1px solid #f0dede; border-radius:10px; text-decoration:none;" href="accounts.php">Hủy</a>
        </div>
      </form>
    <?php else: ?>
      <div>Không tìm thấy tài khoản.</div>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="dash-row" style="align-items:flex-start;">
    <div class="panel" style="flex:1; min-width:280px;">
      <h3 style="margin-top:0">Danh sách tài khoản</h3>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <?php if ($roleCol): ?><th><?php echo htmlspecialchars($roleLabel); ?></th><?php endif; ?>
              <th>Hành động</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($admins as $a): ?>
              <?php
                $rowMa = trim((string)($a['Ma_admin'] ?? ''));
                $isSelf = ($rowMa === $currentAdminId);
                // Xóa không phụ thuộc cột mật khẩu — chỉ cần không phải chính mình và còn >1 admin
                $canDelete = !$isSelf && $adminCount > 1;
              ?>
              <tr>
                <td><?php echo htmlspecialchars($a['Ma_admin']); ?></td>
                <td><?php echo htmlspecialchars($a['Username']); ?><?php if ($isSelf): ?> <span class="badge" style="font-size:11px;">Bạn</span><?php endif; ?></td>
                <?php if ($roleCol): ?>
                  <td><?php echo htmlspecialchars($a['role'] ?? ''); ?></td>
                <?php endif; ?>
                <td>
                  <div class="action-icons">
                    <form method="post" style="display:inline">
                      <?php echo csrf_input(); ?>
                      <input type="hidden" name="action" value="start_edit">
                      <input type="hidden" name="ma_admin" value="<?php echo htmlspecialchars($a['Ma_admin']); ?>">
                      <button class="icon-btn" type="submit" title="Sửa">✏️</button>
                    </form>
                    <?php if ($canDelete): ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('Xác nhận xóa tài khoản này?');">
                      <?php echo csrf_input(); ?>
                      <input type="hidden" name="action" value="delete_admin">
                      <input type="hidden" name="ma_admin" value="<?php echo htmlspecialchars($a['Ma_admin']); ?>">
                      <button class="icon-btn" type="submit" title="Xóa">🗑️</button>
                    </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel" style="flex:1; min-width:280px;" id="addAccountSection">
      <h3 style="margin-top:0">Thêm tài khoản admin</h3>
      <?php if ($pwdCol): ?>
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="add_admin">

        <div class="form-row column">
          <label>Username<br><input class="form-input" name="username" required autocomplete="username"></label>
        </div>

        <div class="form-row column">
          <label>Mật khẩu<br><input class="form-input" name="password" type="password" required autocomplete="new-password"></label>
        </div>

        <?php if ($roleCol): ?>
        <div class="form-row column">
          <label><?php echo htmlspecialchars($roleLabel); ?> <span style="color:#a88;font-weight:400">(mặc định: admin)</span><br>
            <input class="form-input" name="role" value="admin" placeholder="admin"></label>
        </div>
        <?php endif; ?>

        <div class="modal-actions" style="border-top:none; padding-top:8px; margin-top:8px;">
          <button class="add-btn" type="submit">Thêm tài khoản</button>
        </div>
      </form>
      <?php else: ?>
        <p style="color:#a88;">Không thể thêm: thiếu cột mật khẩu trong CSDL.</p>
      <?php endif; ?>

      <p style="margin-top:14px; color:#a88; font-size:13px; line-height:1.5;">
        Chỉ tài khoản có quyền admin đầy đủ mới vào được trang này. Không thể xóa chính mình hoặc xóa hết admin (phải còn ít nhất một tài khoản).
      </p>
    </div>
  </div>
<?php endif; ?>

<?php include 'includes/footer.php';
