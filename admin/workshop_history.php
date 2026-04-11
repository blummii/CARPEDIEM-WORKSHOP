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

$today = date('Y-m-d');
$rows = [];
$sql = "SELECT l.Ma_lich_workshop, l.Ngay_to_chuc, l.So_luong_toi_da, l.So_luong_da_dang_ky,
        c.Ten_chu_de, c.Ma_chu_de
        FROM lichworkshop l
        JOIN chudeworkshop c ON l.Ma_chu_de = c.Ma_chu_de
        WHERE l.Ngay_to_chuc < ?
        ORDER BY l.Ngay_to_chuc DESC, l.Ma_lich_workshop DESC
        LIMIT 200";
$st = $conn->prepare($sql);
$st->bind_param('s', $today);
$st->execute();
$rs = $st->get_result();
while ($row = $rs->fetch_assoc()) {
    $rows[] = $row;
}
?>
<div class="page-header">
  <div>
    <div class="title">LỊCH SỬ WORKSHOP</div>
  </div>
  <div class="header-actions">
    <a href="workshop.php" class="icon-btn" style="text-decoration:none;padding:10px 14px;">← Workshop</a>
  </div>
</div>

<section>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Ngày tổ chức</th>
          <th>Chủ đề</th>
          <th>Đăng ký / Tối đa</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?php echo htmlspecialchars(workshop_fmt_ngay_vn($row['Ngay_to_chuc'] ?? null)); ?></td>
            <td><?php echo htmlspecialchars($row['Ten_chu_de'] ?? ''); ?></td>
            <td><?php echo (int)($row['So_luong_da_dang_ky'] ?? 0); ?> / <?php echo (int)($row['So_luong_toi_da'] ?? 0); ?></td>
            <td><?php
                $ym = workshop_ym_from_mysql_date($row['Ngay_to_chuc'] ?? null);
                $href = 'workshop_schedule.php?lich=' . urlencode($row['Ma_lich_workshop'] ?? '');
                if ($ym !== null) {
                    $href .= '&y=' . (int) $ym['y'] . '&m=' . (int) $ym['m'] . '&ngay=' . urlencode(workshop_date_ymd($row['Ngay_to_chuc'] ?? null) ?? '');
                }
                ?><a class="add-btn" style="padding:6px 12px;font-size:12px;text-decoration:none;" href="<?php echo htmlspecialchars($href); ?>">Xem đăng ký</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
          <tr><td colspan="4" style="color:#a88;">Chưa có buổi nào trong quá khứ.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php include 'includes/footer.php';
