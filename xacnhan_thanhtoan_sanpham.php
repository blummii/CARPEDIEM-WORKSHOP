<?php
session_start();
include("config/db.php");

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['checkout_product']['ma_don_hang'])) {
    echo "<script>alert('Không tìm thấy phiên thanh toán sản phẩm.');location='index.php#cua-hang';</script>";
    exit();
}

$email = trim((string)($_POST['email_xacnhan'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "<script>alert('Email không hợp lệ.');history.back();</script>";
    exit();
}

$maDon = (string)$_SESSION['checkout_product']['ma_don_hang'];
$maKh = (string)($_SESSION['checkout_product']['ma_kh'] ?? '');
$tenKh = (string)($_SESSION['checkout_product']['ten_kh'] ?? '');

$conn->begin_transaction();
try {
    $stOrder = $conn->prepare("
        SELECT Ma_don_hang, Ma_khach_hang, Trang_thai, Tong_tien
        FROM DonHang
        WHERE Ma_don_hang = ?
        FOR UPDATE
    ");
    $stOrder->bind_param('s', $maDon);
    $stOrder->execute();
    $order = $stOrder->get_result()->fetch_assoc();

    if (!$order) {
        throw new RuntimeException('Không tìm thấy đơn hàng.');
    }
    if ($maKh !== '' && (string)$order['Ma_khach_hang'] !== $maKh) {
        throw new RuntimeException('Đơn hàng không thuộc tài khoản hiện tại.');
    }

    $status = (string)($order['Trang_thai'] ?? '');
    if ($status !== 'pending') {
        $conn->rollback();
        unset($_SESSION['checkout_product']);
        echo "<script>alert('Đơn hàng đã được xử lý trước đó.');location='index.php#cua-hang';</script>";
        exit();
    }

    $stItems = $conn->prepare("
        SELECT c.Ma_san_pham, c.So_luong, c.Don_gia, c.Thanh_tien, s.Ten_san_pham, COALESCE(t.So_luong, 0) AS So_luong_ton
        FROM ChiTietDonHang c
        JOIN SanPham s ON s.Ma_san_pham = c.Ma_san_pham
        LEFT JOIN TonKho t ON t.Ma_san_pham = c.Ma_san_pham
        WHERE c.Ma_don_hang = ?
        FOR UPDATE
    ");
    $stItems->bind_param('s', $maDon);
    $stItems->execute();
    $items = [];
    $resItems = $stItems->get_result();
    while ($r = $resItems->fetch_assoc()) {
        $items[] = $r;
    }
    if ($items === []) {
        throw new RuntimeException('Đơn hàng không có chi tiết sản phẩm.');
    }

    foreach ($items as $it) {
        $qty = (int)($it['So_luong'] ?? 0);
        $stock = (int)($it['So_luong_ton'] ?? 0);
        if ($qty <= 0) {
            throw new RuntimeException('Số lượng sản phẩm không hợp lệ.');
        }
        if ($stock < $qty) {
            throw new RuntimeException('Sản phẩm "' . (string)$it['Ten_san_pham'] . '" không đủ tồn kho.');
        }
    }

    $updStock = $conn->prepare('UPDATE TonKho SET So_luong = So_luong - ? WHERE Ma_san_pham = ? AND So_luong >= ?');
    foreach ($items as $it) {
        $sp = (string)$it['Ma_san_pham'];
        $qty = (int)$it['So_luong'];
        $updStock->bind_param('isi', $qty, $sp, $qty);
        $updStock->execute();
        if ($updStock->affected_rows <= 0) {
            throw new RuntimeException('Không thể trừ tồn kho cho sản phẩm ' . $sp . '.');
        }
    }

    $newStatus = 'confirmed';
    $updOrder = $conn->prepare('UPDATE DonHang SET Trang_thai = ? WHERE Ma_don_hang = ?');
    $updOrder->bind_param('ss', $newStatus, $maDon);
    $updOrder->execute();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    echo "<script>alert('Lỗi xác nhận thanh toán: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "');history.back();</script>";
    exit();
}

$mailBodyRows = '';
foreach ($items as $it) {
    $mailBodyRows .= '<tr>'
        . '<td style="border:1px solid #ddd;padding:8px;">' . htmlspecialchars((string)$it['Ten_san_pham'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td style="border:1px solid #ddd;padding:8px;text-align:center;">' . (int)$it['So_luong'] . '</td>'
        . '<td style="border:1px solid #ddd;padding:8px;text-align:right;">' . number_format((float)$it['Thanh_tien'], 0, ',', '.') . 'đ</td>'
        . '</tr>';
}

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'lovetfboys1172005@gmail.com';
    $mail->Password = 'opkhjejnbjednswh';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom('lovetfboys1172005@gmail.com', 'Carpe Diem Workshop');
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'Xác nhận thanh toán đơn hàng sản phẩm thành công';
    $mail->Body = '
      <h2>🎉 Thanh toán thành công</h2>
      <p><b>Mã đơn hàng:</b> ' . htmlspecialchars($maDon, ENT_QUOTES, 'UTF-8') . '</p>
      <p><b>Khách hàng:</b> ' . htmlspecialchars($tenKh, ENT_QUOTES, 'UTF-8') . '</p>
      <table style="border-collapse:collapse;width:100%;max-width:700px;">
        <thead>
          <tr>
            <th style="border:1px solid #ddd;padding:8px;text-align:left;">Sản phẩm</th>
            <th style="border:1px solid #ddd;padding:8px;text-align:center;">Số lượng</th>
            <th style="border:1px solid #ddd;padding:8px;text-align:right;">Thành tiền</th>
          </tr>
        </thead>
        <tbody>' . $mailBodyRows . '</tbody>
      </table>
      <p style="margin-top:12px;"><b>Tổng tiền:</b> ' . number_format((float)$order['Tong_tien'], 0, ',', '.') . 'đ</p>
      <p>Cảm ơn bạn đã mua sắm tại Carpe Diem.</p>
    ';
    $mail->send();
} catch (Exception $e) {
    // Không rollback thanh toán nếu gửi mail lỗi.
}

unset($_SESSION['checkout_product']);
echo "<script>alert('Thanh toán thành công! Đơn hàng đã được xác nhận và trừ tồn kho.');location='index.php#cua-hang';</script>";
?>
