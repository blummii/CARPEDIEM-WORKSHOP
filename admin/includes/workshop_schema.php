<?php
require_once dirname(__DIR__, 2) . '/includes/workshop_date.php';

/**
 * Bổ sung cột cho quy trình quản lý workshop (chạy an toàn nhiều lần — bỏ qua nếu đã có).
 */
function workshop_chudeworkshop_column_names(mysqli $conn): array
{
    $names = [];
    $res = $conn->query(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chudeworkshop'"
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $names[strtolower((string) $row['COLUMN_NAME'])] = true;
        }
    }

    return $names;
}

function workshop_ensure_schema(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $existing = workshop_chudeworkshop_column_names($conn);
    $alters = [
        'dia_diem' => 'ALTER TABLE chudeworkshop ADD COLUMN Dia_diem VARCHAR(255) NULL DEFAULT NULL',
        'thoi_gian_mo_ta' => 'ALTER TABLE chudeworkshop ADD COLUMN Thoi_gian_mo_ta VARCHAR(150) NULL DEFAULT NULL',
        'so_luong_mac_dinh' => 'ALTER TABLE chudeworkshop ADD COLUMN So_luong_mac_dinh INT UNSIGNED NOT NULL DEFAULT 30',
    ];
    foreach ($alters as $key => $sql) {
        if (!isset($existing[$key])) {
            $conn->query($sql);
            $existing[$key] = true;
        }
    }
}

function workshop_gen_ma_chu_de(mysqli $conn): string
{
    $res = $conn->query('SELECT MAX(Ma_chu_de) AS m FROM chudeworkshop');
    $row = $res ? $res->fetch_assoc() : null;
    $m = $row['m'] ?? null;
    if (!$m) {
        return 'CD001';
    }
    if (preg_match('/^([A-Za-z]+)(\d+)$/', (string)$m, $mm)) {
        $p = $mm[1];
        $n = (int)$mm[2];
        $w = max(3, strlen($mm[2]));

        return $p . str_pad((string)($n + 1), $w, '0', STR_PAD_LEFT);
    }

    return 'CD' . str_pad((string)(random_int(1, 999)), 3, '0', STR_PAD_LEFT);
}

/**
 * Sinh Ma_lich_workshop không trùng: không dùng MAX(chuỗi) vì sai khi có nhiều tiền tố (L / LW) hoặc độ dài số khác nhau.
 */
function workshop_gen_ma_lich(mysqli $conn): string
{
    $res = $conn->query('SELECT Ma_lich_workshop FROM lichworkshop');
    $maxNum = 0;
    $prefix = 'LW';
    $width = 3;
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $id = (string) ($row['Ma_lich_workshop'] ?? '');
            if (preg_match('/^([A-Za-z]+)(\d+)$/', $id, $mm)) {
                $n = (int) $mm[2];
                if ($n > $maxNum) {
                    $maxNum = $n;
                    $prefix = $mm[1];
                    $width = strlen($mm[2]);
                }
            }
        }
    }
    if ($maxNum === 0) {
        return $prefix . str_pad('1', max(3, $width), '0', STR_PAD_LEFT);
    }
    $next = $maxNum + 1;
    $w = max(3, $width, strlen((string) $next));

    return $prefix . str_pad((string) $next, $w, '0', STR_PAD_LEFT);
}

/** Các trạng thái chủ đề hiển thị trên site khách */
function workshop_topic_statuses(): array
{
    return [
        'Active' => 'Đang mở đăng ký',
        'Ngừng tổ chức' => 'Ngừng tổ chức',
    ];
}
