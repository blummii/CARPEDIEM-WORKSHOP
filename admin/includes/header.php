<?php
// Admin layout header (sidebar + topbar)
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$isWorkshopSection = (strncmp($currentPage, 'workshop', 8) === 0);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - CARPEDIEM</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="../assets/style.css?v=1.9">
</head>
<body>
  <div class="admin-wrap">
    <aside class="admin-sidebar">
      <div class="brand">
        <a href="index.php" class="brand-link">
          <span class="brand-logo-wrap">
            <img src="../assets/images/logo-carpe-diem.png" alt="" class="brand-logo" width="52" height="52">
          </span>
          <span class="logo">CARPEDIEM</span>
        </a>
      </div>
      <ul class="side-menu">
        <li><a href="index.php" <?php echo ($currentPage === 'index.php') ? 'class="active"' : ''; ?>>Dashboard</a></li>
        <li><a href="categories.php" <?php echo ($currentPage === 'categories.php') ? 'class="active"' : ''; ?>>Quản lý danh mục</a></li>
        <li><a href="products.php" <?php echo ($currentPage === 'products.php') ? 'class="active"' : ''; ?>>Quản lý sản phẩm</a></li>
        <li><a href="customers.php" <?php echo ($currentPage === 'customers.php') ? 'class="active"' : ''; ?>>Quản lý khách hàng</a></li>
        <li><a href="accounts.php" <?php echo ($currentPage === 'accounts.php') ? 'class="active"' : ''; ?>>Quản lý tài khoản</a></li>
        <li><a href="orders.php" <?php echo ($currentPage === 'orders.php') ? 'class="active"' : ''; ?>>Quản lý đơn hàng</a></li>
        <li><a href="inventory.php" <?php echo ($currentPage === 'inventory.php') ? 'class="active"' : ''; ?>>Quản lý tồn kho</a></li>
        <li><a href="workshop.php" <?php echo $isWorkshopSection ? 'class="active"' : ''; ?>>Quản lý Workshop</a></li>
      </ul>
    </aside>
    <div class="admin-main">
      <header class="admin-topbar">
        <?php if ($currentPage !== 'index.php'): ?>
        <div class="search">
          <input id="adminSearch" placeholder="Search...">
        </div>
        <?php endif; ?>
        <div class="top-actions">
          <span class="welcome">Chào, Admin</span>
          <a class="logout" href="../logout.php">Đăng xuất</a>
        </div>
      </header>
      <div class="admin-content">
        <?php if ($currentPage !== 'index.php'): ?>
        <script>
          (function(){
            var inp = document.getElementById('adminSearch');
            if (!inp) return;
            try {
              var params = new URLSearchParams(window.location.search);
              var qq = params.get('q');
              if (qq && !inp.value) inp.value = qq;
            } catch (e) {}
            inp.addEventListener('keydown', function(e){
              if (e.key === 'Enter') {
                e.preventDefault();
                var v = inp.value.trim();
                if (!v) {
                  window.location.href = window.location.pathname;
                  return;
                }
                var target = window.location.pathname + '?q=' + encodeURIComponent(v);
                window.location.href = target;
              }
            });
          })();
        </script>
        <?php endif; ?>
