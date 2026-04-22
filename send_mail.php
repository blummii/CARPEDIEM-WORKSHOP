<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

function guiMail($to, $tenkh, $workshop, $ghe, $tongtien)
{
    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        $mail->Username = 'yourgmail@gmail.com';
        $mail->Password = 'app_password_16_so';

        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->CharSet = 'UTF-8';

        $mail->setFrom('yourgmail@gmail.com', 'Carpe Diem');
        $mail->addAddress($to, $tenkh);

        $mail->isHTML(true);
        $mail->Subject = 'Xác nhận đăng ký workshop';

        $mail->Body = "
        <h2>🎉 Đăng ký thành công</h2>

        <p>Xin chào <b>$tenkh</b></p>

        <p>Bạn đã đăng ký workshop thành công.</p>

        <table border='1' cellpadding='8'>
            <tr>
                <td>Workshop</td>
                <td>$workshop</td>
            </tr>

            <tr>
                <td>Ghế</td>
                <td>$ghe</td>
            </tr>

            <tr>
                <td>Số tiền</td>
                <td>$tongtien đ</td>
            </tr>
        </table>

        <br>
        <p>Hẹn gặp bạn tại workshop ❤️</p>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        return false;
    }
}
?>