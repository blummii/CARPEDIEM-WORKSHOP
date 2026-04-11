<?php
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';
include 'includes/header.php';

function dash_money(float $v): string
{
    return number_format($v, 0, ',', '.') . ' VND';
}

function dash_map_status(?string $st): string
{
    return match ($st) {
        'pending' => 'Đang xử lý',
        'confirmed' => 'Đã xác nhận',
        'shipped' => 'Đang giao',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Hủy',
        default => $st ?? '',
    };
}

function dash_format_dt($dt): string
{
    if ($dt === null || $dt === '') {
        return '—';
    }
    $t = strtotime((string)$dt);
    return $t ? date('d/m/Y H:i', $t) : htmlspecialchars((string)$dt);
}

/** Nhãn tháng cho biểu đồ: 2026-04 → T4/2026 */
function dash_ym_label(string $ym): string
{
    if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) {
        return $ym;
    }
    return 'T' . (int)$m[2] . '/' . $m[1];
}

$totalRevenue = 0.0;
$newOrders = 0;
$newCustomers = 0;
$lowStockCount = 0;
$recentOrders = [];
$lowStockItems = [];
$topProducts = [];
$revenueByMonth = [];

if (isset($conn) && $conn instanceof mysqli) {
    $r = $conn->query("SELECT COALESCE(SUM(Tong_tien), 0) AS s FROM DonHang WHERE Trang_thai = 'completed'");
    if ($r && ($row = $r->fetch_assoc())) {
        $totalRevenue = (float)$row['s'];
    }

    // Đơn mới / chờ xử lý: khớp 'pending' (chuẩn app) + giá trị cũ/Excel (Processing, tiếng Việt, NULL/rỗng)
    $sqlNewOrders = "SELECT COUNT(*) AS c FROM DonHang WHERE (
        Trang_thai IS NULL
        OR TRIM(COALESCE(Trang_thai, '')) = ''
        OR LOWER(TRIM(COALESCE(Trang_thai, ''))) IN ('pending', 'processing')
        OR TRIM(COALESCE(Trang_thai, '')) IN ('Đang xử lý', 'Processing', 'Pending')
    )";
    $r = $conn->query($sqlNewOrders);
    if ($r && ($row = $r->fetch_assoc())) {
        $newOrders = (int)$row['c'];
    }

    $r = $conn->query('SELECT COUNT(*) AS c FROM KhachHang WHERE Thoi_gian_tao >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
    if ($r && ($row = $r->fetch_assoc())) {
        $newCustomers = (int)$row['c'];
    }

    $r = $conn->query('SELECT COUNT(*) AS c FROM TonKho WHERE So_luong <= Muc_ton_toi_thieu');
    if ($r && ($row = $r->fetch_assoc())) {
        $lowStockCount = (int)$row['c'];
    }

    $sql = 'SELECT dh.Ma_don_hang, kh.Ten_khach_hang, dh.Thoi_gian_tao, dh.Tong_tien, dh.Trang_thai
            FROM DonHang dh
            LEFT JOIN KhachHang kh ON kh.Ma_khach_hang = dh.Ma_khach_hang
            ORDER BY dh.Thoi_gian_tao DESC
            LIMIT 8';
    $r = $conn->query($sql);
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $recentOrders[] = $row;
        }
    }

    $sql = 'SELECT sp.Ten_san_pham, tk.Ma_san_pham, tk.So_luong, tk.Muc_ton_toi_thieu
            FROM TonKho tk
            LEFT JOIN SanPham sp ON sp.Ma_san_pham = tk.Ma_san_pham
            WHERE tk.So_luong <= tk.Muc_ton_toi_thieu
            ORDER BY tk.So_luong ASC
            LIMIT 8';
    $r = $conn->query($sql);
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $lowStockItems[] = $row;
        }
    }

    $sql = 'SELECT sp.Ten_san_pham, SUM(ct.So_luong) AS qty
            FROM ChiTietDonHang ct
            INNER JOIN SanPham sp ON sp.Ma_san_pham = ct.Ma_san_pham
            INNER JOIN DonHang dh ON dh.Ma_don_hang = ct.Ma_don_hang
            WHERE dh.Trang_thai = \'completed\'
            GROUP BY ct.Ma_san_pham, sp.Ten_san_pham
            ORDER BY qty DESC
            LIMIT 6';
    $r = $conn->query($sql);
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $topProducts[] = $row;
        }
    }

    $sql = "SELECT DATE_FORMAT(Thoi_gian_tao, '%Y-%m') AS ym, COALESCE(SUM(Tong_tien), 0) AS rev
            FROM DonHang
            WHERE Trang_thai = 'completed'
            AND Thoi_gian_tao >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL 11 MONTH)
            GROUP BY ym
            ORDER BY ym ASC";
    $byYm = [];
    $r = $conn->query($sql);
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $byYm[(string)($row['ym'] ?? '')] = (float)($row['rev'] ?? 0);
        }
    }
    $baseMonth = new DateTime('first day of this month');
    for ($i = 11; $i >= 0; $i--) {
        $d = (clone $baseMonth)->modify("-{$i} months");
        $key = $d->format('Y-m');
        $revenueByMonth[] = ['ym' => $key, 'rev' => $byYm[$key] ?? 0.0];
    }
}

$chartLabels = [];
$chartValues = [];
foreach ($revenueByMonth as $m) {
    $chartLabels[] = dash_ym_label($m['ym']);
    $chartValues[] = round((float)($m['rev'] ?? 0), 0);
}
$chartLabelsJson = json_encode($chartLabels, JSON_UNESCAPED_UNICODE);
$chartValuesJson = json_encode($chartValues);
$chartHasData = array_sum($chartValues) > 0;
?>
<div class="big-title"><span class="label">Carpe Diem: Tiệm Nến Thơm</span></div>
<div class="dashboard">
	<div class="dash-row dash-cards">
		<div class="card">
			<div class="card-title">TỔNG DOANH THU</div>
			<div class="card-value"><?php echo htmlspecialchars(dash_money($totalRevenue)); ?></div>
			<div class="card-note" style="font-size:12px;color:#a88;margin-top:6px;">Chỉ các đơn trạng thái hoàn thành</div>
		</div>
		<div class="card">
			<div class="card-title">ĐƠN HÀNG MỚI</div>
			<div class="card-value"><?php echo (int)$newOrders; ?></div>
			<div class="card-note" style="font-size:12px;color:#a88;margin-top:6px;">Gồm đơn đang xử lý và đơn chưa có trạng thái</div>
		</div>
		<div class="card">
			<div class="card-title">KHÁCH HÀNG MỚI</div>
			<div class="card-value"><?php echo (int)$newCustomers; ?></div>
			<div class="card-note" style="font-size:12px;color:#a88;margin-top:6px;">Khách đăng ký trong 30 ngày gần đây</div>
		</div>
		<div class="card">
			<div class="card-title">TỒN KHO THẤP</div>
			<div class="card-value"><?php echo (int)$lowStockCount; ?> SP</div>
			<div class="card-note" style="font-size:12px;color:#a88;margin-top:6px;">Tồn không vượt quá mức tối thiểu</div>
		</div>
	</div>

	<div class="dash-row">
		<div class="panel panel-chart">
			<h3>Doanh thu theo tháng (12 tháng gần nhất)</h3>
			<?php if (!$chartHasData): ?>
				<p class="chart-placeholder" style="margin:0;color:#a88;">Chưa có đơn hoàn thành trong 12 tháng gần đây.</p>
			<?php else: ?>
				<div class="dash-chart-canvas-wrap">
					<canvas id="revenueMonthChart" aria-label="Biểu đồ doanh thu theo tháng" role="img"></canvas>
				</div>
				<p style="margin:10px 0 0;font-size:12px;color:#a88;">Đơn vị: VND · Chỉ tính đơn đã hoàn thành.</p>
			<?php endif; ?>
		</div>
		<div class="panel panel-stats">
			<h3>Sản phẩm bán chạy (theo số lượng đã bán)</h3>
			<?php if ($topProducts === []): ?>
				<p class="chart-placeholder" style="margin:0;color:#a88;">Chưa có chi tiết đơn hàng.</p>
			<?php else: ?>
				<ul style="list-style:none;margin:0;padding:0;">
					<?php foreach ($topProducts as $i => $p): ?>
						<li style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f5e8e8;">
							<span><?php echo (int)($i + 1); ?>. <?php echo htmlspecialchars($p['Ten_san_pham'] ?? ''); ?></span>
							<span class="badge"><?php echo (int)($p['qty'] ?? 0); ?> sp</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>

	<div class="dash-row">
		<div class="panel panel-list">
			<h3>Đơn hàng gần đây</h3>
			<table class="mini-table"><thead><tr><th>Mã ĐH</th><th>Khách hàng</th><th>Thời gian</th><th>Tổng tiền</th><th>Trạng thái</th></tr></thead>
				<tbody>
					<?php if ($recentOrders === []): ?>
						<tr><td colspan="5" style="color:#a88;">Chưa có đơn hàng.</td></tr>
					<?php else: ?>
						<?php foreach ($recentOrders as $o): ?>
							<tr>
								<td><?php echo htmlspecialchars($o['Ma_don_hang'] ?? ''); ?></td>
								<td><?php echo htmlspecialchars($o['Ten_khach_hang'] ?? '—'); ?></td>
								<td><?php echo htmlspecialchars(dash_format_dt($o['Thoi_gian_tao'] ?? null)); ?></td>
								<td><?php echo htmlspecialchars(dash_money((float)($o['Tong_tien'] ?? 0))); ?></td>
								<td><span class="badge"><?php echo htmlspecialchars(dash_map_status($o['Trang_thai'] ?? null)); ?></span></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<div class="panel panel-inventory">
			<h3>Cảnh báo tồn kho</h3>
			<table class="mini-table"><thead><tr><th>Sản phẩm</th><th>Mã SP</th><th>Số lượng</th><th>Tối thiểu</th></tr></thead>
				<tbody>
					<?php if ($lowStockItems === []): ?>
						<tr><td colspan="4" style="color:#a88;">Không có sản phẩm dưới ngưỡng tồn.</td></tr>
					<?php else: ?>
						<?php foreach ($lowStockItems as $it): ?>
							<tr>
								<td><?php echo htmlspecialchars($it['Ten_san_pham'] ?? '—'); ?></td>
								<td><?php echo htmlspecialchars($it['Ma_san_pham'] ?? ''); ?></td>
								<td><?php echo (int)($it['So_luong'] ?? 0); ?></td>
								<td><?php echo (int)($it['Muc_ton_toi_thieu'] ?? 0); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php if ($chartHasData): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
(function () {
  var labels = <?php echo $chartLabelsJson; ?>;
  var values = <?php echo $chartValuesJson; ?>;
  var el = document.getElementById('revenueMonthChart');
  if (!el || typeof Chart === 'undefined') return;
  var fmt = new Intl.NumberFormat('vi-VN');
  new Chart(el, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: 'Doanh thu',
        data: values,
        backgroundColor: 'rgba(212, 165, 165, 0.42)',
        borderColor: 'rgba(212, 165, 165, 1)',
        borderWidth: 1,
        borderRadius: 6,
        borderSkipped: false
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function (ctx) {
              return fmt.format(ctx.parsed.y) + ' VND';
            }
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { maxRotation: 45, minRotation: 0, font: { size: 11 } }
        },
        y: {
          beginAtZero: true,
          ticks: {
            font: { size: 11 },
            callback: function (v) {
              var n = Number(v);
              if (!isFinite(n)) return v;
              if (n >= 1e9) return (n / 1e9).toFixed(1).replace(/\.0$/, '') + 'B';
              if (n >= 1e6) return (n / 1e6).toFixed(1).replace(/\.0$/, '') + 'tr';
              if (n >= 1e3) return fmt.format(Math.round(n / 1e3)) + 'k';
              return fmt.format(n);
            }
          }
        }
      }
    }
  });
})();
</script>
<?php endif; ?>

<?php include 'includes/footer.php';
