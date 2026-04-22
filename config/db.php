<?php
// Thông số kết nối
$servername = "localhost";
$username = "root";
$password = ""; // Mặc định của XAMPP thường để trống
$dbname = "qlworkshop (1)";

// Tạo kết nối
$conn = new mysqli($servername, $username, $password, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Thiết lập bộ mã hiển thị tiếng Việt chuẩn
$conn->set_charset("utf8mb4");
?>