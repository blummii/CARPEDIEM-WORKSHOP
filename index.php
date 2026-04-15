<?php 
session_start(); 
include("config/db.php");
require_once __DIR__ . '/includes/workshop_date.php';

// Xử lý thông tin người dùng từ Session
$isLoggedIn = isset($_SESSION['user']);
$user = $isLoggedIn ? $_SESSION['user'] : null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carpe Diem - Tiệm Nến Thơm</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* Bổ sung một chút style cho phần người dùng */
        .user-bar {
            background: #fff;
            padding: 10px 5%;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 15px;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .user-bar a { color: #d4a5a5; text-decoration: none; font-weight: bold; }
        .booked-section { margin-top: 30px; background: #fff; padding: 20px; border-radius: 20px; }
        .booked-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .booked-table th, .booked-table td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        .booked-table th { background: #f8d7da; color: #842029; }
    </style>
</head>
<body>

<div class="user-bar">
    <?php if ($isLoggedIn): ?>
        <span>Chào, <strong><?php echo htmlspecialchars($user['Ten_khach_hang']); ?></strong></span>
        <a href="logout.php">Đăng xuất</a>
    <?php else: ?>
        <a href="dangnhap.php">Đăng nhập</a>
        <a href="dangky.php" style="background: #f8d7da; padding: 5px 15px; border-radius: 20px;">Đăng ký</a>
    <?php endif; ?>
</div>

<div class="hero">
    <img src="assets/images/IMG_0820.jpg" alt="Hero Image">
    <div class="hero-text">Carpe Diem: Tiệm Nến Thơm</div>
</div>

<div class="container">
    <?php if ($isLoggedIn): ?>
        <div class="booked-section">
            <h3 style="color: #d4a5a5;">📅 Workshop bạn đã tham gia</h3>
            <?php
            $ma_kh = $user['Ma_khach_hang'];
            // Truy vấn lấy các đơn hàng khớp với mã khách hàng
            $sql_booked = "SELECT dk.*, l.Ngay_to_chuc, c.Ten_chu_de 
                           FROM dangkyworkshop dk
                           JOIN lichworkshop l ON dk.Ma_lich_workshop = l.Ma_lich_workshop
                           JOIN chudeworkshop c ON l.Ma_chu_de = c.Ma_chu_de
                           WHERE dk.Ma_khach_hang = '$ma_kh'
                           ORDER BY l.Ngay_to_chuc DESC, dk.Thoi_gian_tao DESC";
            $res_booked = $conn->query($sql_booked);

            if ($res_booked && $res_booked->num_rows > 0): ?>
                <table class="booked-table">
                    <thead>
                        <tr>
                            <th>Chủ đề</th>
                            <th>Ngày học</th>
                            <th>Số chỗ</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $res_booked->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo $row['Ten_chu_de']; ?></strong></td>
                                <td><?php echo htmlspecialchars(workshop_fmt_ngay_vn($row['Ngay_to_chuc'] ?? null)); ?></td>
                                <td><?php echo $row['So_nguoi_tham_gia']; ?> ghế</td>
                                <td><?php echo number_format($row['Tong_tien'], 0, ',', '.'); ?>đ</td>
                                <td><span style="color: green;"><?php echo $row['Trang_thai_thanh_toan']; ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="font-style: italic; color: #999;">Bạn chưa đăng ký buổi workshop nào.</p>
            <?php endif; ?>
        </div>
        <hr style="margin: 40px 0; border: 1px solid #f8d7da; opacity: 0.3;">
    <?php endif; ?>

    <h3 style="text-align: center; color: #d4a5a5; font-size: 24px;">Khám phá các buổi Workshop</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin-top: 20px;">
        <?php
        $sql = "SELECT * FROM chudeworkshop WHERE Trang_thai = 'Active'";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            while($cd = $result->fetch_assoc()){
                ?>
                <div class="item" style="opacity: 1; transform: none;"> <img src="assets/images/workshop_default.jpg" alt="Workshop">
                    <h2><?php echo $cd['Ten_chu_de']; ?></h2>
                    <p><?php echo $cd['Mo_ta']; ?></p>
                    <p><strong>Giá: <?php echo number_format($cd['Gia'], 0, ',', '.'); ?>đ / người</strong></p>

                    <div style="margin-top: 15px;">
                        <?php
                        $ma_cd = $cd['Ma_chu_de'];
                        $lich = $conn->query("SELECT * FROM lichworkshop WHERE Ma_chu_de='$ma_cd' ORDER BY Ngay_to_chuc ASC");
                        while($l = $lich->fetch_assoc()){
                            $conlai = $l['So_luong_toi_da'] - $l['So_luong_da_dang_ky'];
                        ?>
                            <div style="padding: 10px; border: 1px solid #f8d7da; border-radius: 10px; margin-bottom: 10px;">
                                <small>📅 Ngày: <?php echo htmlspecialchars(workshop_fmt_ngay_vn($l['Ngay_to_chuc'] ?? null)); ?></small><br>
                                <?php if($conlai > 0): ?>
                                    <button class="btn"
                                        data-lich="<?php echo htmlspecialchars($l['Ma_lich_workshop'], ENT_QUOTES); ?>"
                                        data-gia="<?php echo htmlspecialchars((string)($cd['Gia'] ?? 0), ENT_QUOTES); ?>"
                                        onclick="openPopup('<?php echo $l['Ma_lich_workshop']; ?>', <?php echo (int)$l['So_luong_toi_da']; ?>, <?php echo (int)$l['So_luong_da_dang_ky']; ?>, <?php echo (float)($cd['Gia'] ?? 0); ?>)">
                                        Chọn ghế (Còn <?php echo $conlai; ?> chỗ)
                                    </button>
                                <?php else: ?>
                                    <span style="color:#ccc; font-size: 13px;">Hết chỗ</span>
                                <?php endif; ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
        <?php 
            } 
        }
        ?>
    </div>
</div>

<div id="popup" class="popup">
    <div class="popup-content">
        <span class="close" onclick="closePopup()">&times;</span>
        <h3 style="color: #b88b8b; text-align: center;">Chọn vị trí ngồi của bạn</h3>
        
        <div class="screen">BÀN HƯỚNG DẪN (SCREEN)</div>
        
        <div class="seat-grid" id="seat-grid"></div>

        <div class="seat-legend">
            <div class="legend-item"><span class="seat-demo available"></span> Trống</div>
            <div class="legend-item"><span class="seat-demo selected"></span> Đang chọn</div>
            <div class="legend-item"><span class="seat-demo occupied"></span> Đã đặt</div>
        </div>

        <form action="xuly_dangky.php" method="POST">
            <input type="hidden" name="id_lich" id="id_lich">
            <input type="hidden" name="selected_seats" id="selected_seats"> 
            
            <div style="background: #fff5f7; padding: 10px; border-radius: 10px; margin-bottom: 15px; text-align: left;">
                <p style="margin: 5px 0;">Ghế chọn: <span id="display-seats" style="font-weight:bold; color:#d4a5a5;">Chưa chọn</span></p>
                <p style="margin: 5px 0;">Tổng tiền: <span id="display-total" style="font-weight:bold; color: #842029;">0đ</span></p>
            </div>
            
            <input type="text" name="ten" placeholder="Họ tên" required value="<?php echo $isLoggedIn ? $user['Ten_khach_hang'] : ''; ?>">
            <input type="text" name="sdt" placeholder="Số điện thoại" required value="<?php echo $isLoggedIn ? $user['So_dien_thoai'] : ''; ?>">
            <input type="email" name="email" placeholder="Email" value="<?php echo $isLoggedIn ? $user['Email'] : ''; ?>">
            
            <button type="submit" class="btn" style="width: 100%; margin-top: 10px;">XÁC NHẬN ĐẶT CHỖ</button>
        </form>
    </div>
</div>

<script src="assets/script.js?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/assets/script.js')); ?>"></script>
<script>
    function closePopup() {
        document.getElementById("popup").style.display = "none";
    }
</script>
</body>
</html>