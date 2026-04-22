<?php
include("config/db.php");

$ma_lich = $_GET['ma_lich'] ?? '';

$sql = "SELECT So_ghe FROM chitietghe 
        WHERE Ma_lich_workshop='$ma_lich'
        AND Trang_thai='Đã đặt'";

$rs = $conn->query($sql);

$data = [];

while($row = $rs->fetch_assoc()){
    $data[] = $row['So_ghe'];
}

header('Content-Type: application/json');
echo json_encode($data);
?>