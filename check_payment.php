<?php
include("config/db.php");

$madk = $_GET['madk'];

$sql = "SELECT Trang_thai_thanh_toan 
FROM dangkyworkshop
WHERE Ma_dang_ky='$madk'";

$rs = $conn->query($sql);

$row = $rs->fetch_assoc();

if($row['Trang_thai_thanh_toan']=="Đã thanh toán"){
    echo "paid";
}else{
    echo "wait";
}
?>