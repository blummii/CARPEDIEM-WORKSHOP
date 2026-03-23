<?php
session_start();
include("config/db.php");

// 1. Kiểm tra đăng nhập (Bảo mật Session)
if (!isset($_SESSION['user'])) {
    die("<script>alert('Vui lòng đăng nhập để thực hiện đặt chỗ!'); window.location.href='dangnhap.php';</script>");
}

// 2. Lấy dữ liệu từ Session và Form
$user = $_SESSION['user'];
$ma_khach_hang = $user['Ma_khach_hang']; // Lấy mã KH từ session đã login

$ma_lich = $_POST['id_lich'] ?? '';
$selected_seats = $_POST['selected_seats'] ?? ''; 

// Kiểm tra nếu chưa chọn ghế
if (empty($selected_seats)) {
    die("<script>alert('Vui lòng quay lại chọn ít nhất một ghế!'); window.history.back();</script>");
}

// Chuyển chuỗi ghế "1,2,5" thành mảng và đếm số lượng
$seat_array = explode(",", $selected_seats);
$so_luong = count($seat_array);

try {
    // 3. Lấy giá tiền từ Database (Sử dụng Prepared Statement)
    $stmt_price = $conn->prepare("SELECT c.Gia FROM lichworkshop l 
                                  JOIN chudeworkshop c ON l.Ma_chu_de = c.Ma_chu_de 
                                  WHERE l.Ma_lich_workshop = ?");
    $stmt_price->bind_param("s", $ma_lich);
    $stmt_price->execute();
    $res_price = $stmt_price->get_result();
    $row_price = $res_price->fetch_assoc();
    
    $gia_don_vi = $row_price['Gia'] ?? 0;
    $tong_tien = $gia_don_vi * $so_luong;

    // 4. Tạo mã đăng ký mới
    $ma_dang_ky = "DK" . rand(1000, 9999);

    // 5. Lưu vào bảng dangkyworkshop (Khớp chính xác các cột bạn đã gửi)
    // Các cột: Ma_dang_ky, Ma_lich_workshop, Ma_khach_hang, So_nguoi_tham_gia, Tong_tien, Trang_thai_thanh_toan, Thoi_gian_tao
    $stmt_dk = $conn->prepare("INSERT INTO dangkyworkshop (Ma_dang_ky, Ma_lich_workshop, Ma_khach_hang, So_nguoi_tham_gia, Tong_tien, Trang_thai_thanh_toan, Thoi_gian_tao) 
                               VALUES (?, ?, ?, ?, ?, 'Chưa thanh toán', NOW())");
    
    $stmt_dk->bind_param("sssid", $ma_dang_ky, $ma_lich, $ma_khach_hang, $so_luong, $tong_tien);

    if ($stmt_dk->execute()) {
        
        // 6. Cập nhật trạng thái từng ghế vào bảng chitietghe
        foreach ($seat_array as $so_ghe) {
            $so_ghe = trim($so_ghe);
            // Sử dụng ON DUPLICATE KEY để cập nhật nếu ghế đã tồn tại trong lịch đó
            $stmt_seat = $conn->prepare("INSERT INTO chitietghe (Ma_lich_workshop, So_ghe, Ma_dang_ky, Trang_thai) 
                                         VALUES (?, ?, ?, 'Đã đặt') 
                                         ON DUPLICATE KEY UPDATE Trang_thai='Đã đặt', Ma_dang_ky=?");
            $stmt_seat->bind_param("siss", $ma_lich, $so_ghe, $ma_dang_ky, $ma_dang_ky);
            $stmt_seat->execute();
        }

        // 7. Cập nhật số lượng đã đăng ký trong bảng lichworkshop
        $stmt_update_lich = $conn->prepare("UPDATE lichworkshop SET So_luong_da_dang_ky = So_luong_da_dang_ky + ? WHERE Ma_lich_workshop = ?");
        $stmt_update_lich->bind_param("is", $so_luong, $ma_lich);
        $stmt_update_lich->execute();

        // Thành công: Thông báo và chuyển hướng
        echo "<script>
                alert('Chúc mừng " . htmlspecialchars($user['Ten_khach_hang']) . "! Bạn đã đặt thành công " . $so_luong . " ghế.');
                window.location.href='index.php';
              </script>";
    } else {
        throw new Exception($conn->error);
    }

} catch (Exception $e) {
    // Xử lý lỗi nếu có
    die("Lỗi xử lý đăng ký: " . $e->getMessage());
}
?>