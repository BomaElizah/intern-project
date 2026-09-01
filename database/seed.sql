-- =====================================================================
-- WPU Maintenance Request System — Seed Data
-- Run this after schema.sql. Sets up the six roles, maintenance
-- categories, a starter set of buildings/rooms, and one Administrator
-- account so the system can be logged into immediately.
--
-- Default administrator login (CHANGE THIS PASSWORD after first login):
--   University email : admin@wpu.ac.pg
--   Password         : Admin@12345
-- =====================================================================

USE wpu_maintenance;

-- Roles (Section 6 dashboard names / FR-07..FR-11 "technician" wording)
INSERT INTO roles (role_name) VALUES
('Student'), ('Staff'), ('Academic'), ('Technician'), ('Supervisor'), ('Administrator');

-- Categories (FR-04)
INSERT INTO categories (category_name) VALUES
('Electrical'), ('Plumbing'), ('Air Conditioning'), ('Furniture'), ('IT Equipment'), ('Building Repair');

-- Starter buildings (dormitories, lecture buildings, admin, library, areas)
INSERT INTO buildings (building_name, building_code) VALUES
('Dormitory A', 'DORM-A'),
('Dormitory B', 'DORM-B'),
('Main Lecture Block', 'MLB'),
('Science Lecture Block', 'SLB'),
('Administration Building', 'ADMIN'),
('University Library', 'LIB'),
('Area 1', 'AREA-1'),
('Area 2', 'AREA-2');

-- Starter rooms
INSERT INTO rooms (building_id, room_number, room_type) VALUES
(1, 'A101', 'Dormitory'), (1, 'A102', 'Dormitory'), (1, 'A201', 'Dormitory'),
(2, 'B101', 'Dormitory'), (2, 'B102', 'Dormitory'),
(3, 'MLB-01', 'Lecture Hall'), (3, 'MLB-02', 'Lecture Hall'),
(4, 'SLB-01', 'Laboratory'), (4, 'SLB-02', 'Lecture Hall'),
(5, 'ADM-01', 'Office'), (5, 'ADM-02', 'Office'),
(6, 'LIB-01', 'Reading Room'),
(7, 'AREA1-BLK1', 'Outdoor'),
(8, 'AREA2-BLK1', 'Outdoor');

-- Default system settings (Section 6.7)
INSERT INTO app_settings (setting_key, setting_value) VALUES
('site_name', 'WPU Maintenance Request System'),
('support_email', 'maintenance@wpu.ac.pg'),
('max_upload_mb', '5'),
('request_backup_frequency', 'Daily');

-- Default administrator account (password: Admin@12345, hashed with PHP password_hash/BCRYPT)
INSERT INTO users (full_name, university_email, id_number, password_hash, role_id, is_active)
VALUES ('System Administrator', 'admin@wpu.ac.pg', 'ADM-0001',
        '$2y$12$djjSVC3XSzomS05m.cXia.j4hqPgdARIfdpi1ueS2s/MtbwZ37Zwi', 6, TRUE);
-- This hash was generated with PHP's own password_hash('Admin@12345', PASSWORD_BCRYPT),
-- so it verifies correctly against this system's login code out of the box.
