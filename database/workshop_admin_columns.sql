-- Chạy trong phpMyAdmin (một lần) nếu trang Quản lý Workshop báo thiếu cột.
-- Hoặc mở bất kỳ trang admin workshop nào — code sẽ tự ALTER (nếu user MySQL có quyền).

ALTER TABLE chudeworkshop ADD COLUMN Dia_diem VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE chudeworkshop ADD COLUMN Thoi_gian_mo_ta VARCHAR(150) NULL DEFAULT NULL;
ALTER TABLE chudeworkshop ADD COLUMN So_luong_mac_dinh INT UNSIGNED NOT NULL DEFAULT 30;
