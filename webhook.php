<?php
include("config/db.php");

/* nhận json */
$data = json_decode(file_get_contents("php://input"),true);

$money = $data['amount'];
$content = $data['content'];

/* tìm mã DK trong nội dung */
preg_match('/DK[0-9]+/', $content, $match);

$madk = $match[0] ?? '';

if($madk != ''){

$conn->query("
UPDATE dangkyworkshop
SET Trang_thai_thanh_toan='Đã thanh toán'
WHERE Ma_dang_ky='$madk'
");

}

echo "OK";
?>