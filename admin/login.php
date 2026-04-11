<?php
session_start();
require_once __DIR__ . '/../config/db.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  if (!$username || !$password) {
    $err = 'Vui lòng nhập username và mật khẩu.';
  } else {
    // Expecting a table named `Admins` with columns: Ma_admin, Username, Password_hash
    if (isset($pdo)) {
      $stmt = $pdo->prepare('SELECT Ma_admin AS id, Password_hash AS password_hash FROM Admins WHERE Username = ? LIMIT 1');
      $stmt->execute([$username]);
      $admin = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id'] = $admin['id'];
        header('Location: index.php');
        exit;
      }
      $err = 'Sai tên đăng nhập hoặc mật khẩu';
    } elseif (isset($conn)) {
      $stmt = $conn->prepare('SELECT Ma_admin AS id, Password_hash AS password_hash FROM Admins WHERE Username = ? LIMIT 1');
      $stmt->bind_param('s', $username);
      $stmt->execute();
      $res = $stmt->get_result();
      $admin = $res->fetch_assoc();
      if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id'] = $admin['id'];
        header('Location: index.php');
        exit;
      }
      $err = 'Sai tên đăng nhập hoặc mật khẩu';
    } else {
      $err = 'Không tìm thấy kết nối DB trong config/db.php';
    }
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <h2>Đăng nhập quản trị</h2>
  <?php if ($err): ?>
    <div class="error"><?php echo htmlspecialchars($err);?></div>
  <?php endif; ?>
  <form method="post" action="">
    <label>Username<br><input type="text" name="username" required></label><br>
    <label>Password<br><input type="password" name="password" required></label><br>
    <button type="submit">Đăng nhập</button>
  </form>
</body>
</html>
