<?php
/**
 * Ngày từ MySQL DATE/DATETIME — dùng phần chuỗi Y-m-d, không strtotime (tránh lệch múi giờ).
 */
function workshop_date_ymd(?string $mysqlDate): ?string
{
    if ($mysqlDate === null || $mysqlDate === '') {
        return null;
    }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', trim($mysqlDate), $m)) {
        return $m[1];
    }

    return null;
}

function workshop_fmt_ngay_vn(?string $mysqlDate): string
{
    $d = workshop_date_ymd($mysqlDate);
    if ($d === null) {
        return '—';
    }
    [$y, $mo, $day] = explode('-', $d);

    return $day . '/' . $mo . '/' . $y;
}

/** y, m (1–12) từ giá trị cột ngày MySQL */
function workshop_ym_from_mysql_date(?string $mysqlDate): ?array
{
    $d = workshop_date_ymd($mysqlDate);
    if ($d === null) {
        return null;
    }
    [$y, $m] = explode('-', $d);

    return ['y' => (int) $y, 'm' => (int) $m];
}
