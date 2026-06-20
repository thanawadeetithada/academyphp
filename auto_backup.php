<?php
require 'db.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Ifsnop\Mysqldump\Mysqldump;

date_default_timezone_set('Asia/Bangkok');

try {
    $default_email = 'namvankana@gmail.com'; 
    $target_emails = [$default_email]; 

    $dbHost = 'localhost';
    $dbUser = 'root';
    $dbPass = '';
    $dbName = 'academydb';

    $dateString = date('Y-m-d_H-i-s');
    $backupFileName = "backup_{$dbName}_{$dateString}.sql";
    $backupFilePath = __DIR__ . '/' . $backupFileName;
    
    // สร้างไฟล์ Backup
    try {
        $dump = new Mysqldump("mysql:host={$dbHost};dbname={$dbName}", $dbUser, $dbPass);
        $dump->start($backupFilePath);
    } catch (\Exception $e) {
        die("Backup Error: " . $e->getMessage());
    }

    if (file_exists($backupFilePath) && filesize($backupFilePath) > 0) {
        
        $mail = new PHPMailer(true);
        $mail->CharSet = "UTF-8";
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = '@gmail.com';
        $mail->Password = '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('@gmail.com', 'System Auto Backup');

        // วนลูปส่งอีเมลตามรายชื่อที่ตั้งไว้
        foreach ($target_emails as $address) {
            $mail->addAddress($address);
        }

        $mail->isHTML(true);
        $mail->Subject = 'สำรองเว็บไซต์ ประจำวันที่ ' . date('d/m/Y');
        
        $email_list_text = implode(', ', $target_emails);
        $mail->Body = "
            <h3>ไฟล์สำรองข้อมูลสำเร็จ (ระบบอัตโนมัติ)</h3>
            <p>วันที่ทำรายการ:</b> " . date('d/m/Y H:i:s') . "</p>
            <p><i>โปรดเก็บไฟล์นี้ไว้ในที่ปลอดภัยเพื่อความปลอดภัยของข้อมูล</i></p>
        ";

        $mail->addAttachment($backupFilePath);
        $mail->send();

        unlink($backupFilePath); // ลบไฟล์ชั่วคราว

        echo "สำเร็จ: ระบบส่งไฟล์ Backup เข้าอีเมลเรียบร้อยแล้ว!";
    } else {
        echo "ผิดพลาด: ไฟล์ Backup ไม่มีข้อมูล";
    }

} catch (Exception $e) {
    if (isset($backupFilePath) && file_exists($backupFilePath)) unlink($backupFilePath);
    echo "Mail Error: " . $mail->ErrorInfo;
}
?>