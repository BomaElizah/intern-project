-- =====================================================================
-- WPU Maintenance Request System — Database Schema (MySQL / XAMPP)
-- Western Pacific University
--
-- Matches SRS Section 7 (Database Requirements). Written for MySQL 5.7+/8.0
-- as used under XAMPP (SRS Section 3.5 constraint). Column names, types,
-- and constraints follow the SRS Data Dictionary (7.3.1 - 7.3.12) with
-- SERIAL translated to INT AUTO_INCREMENT and CHECK translated to ENUM
-- (MySQL enforces ENUM the same way CHECK-list constraints were intended).
--
-- Roles: the source document used two different role lists in different
-- sections (FR-03 vs. Section 3.1/6.x/7.3.1). This schema — and the rest
-- of the system — standardizes on the six roles actually used by the
-- dashboards in Section 6: Student, Staff, Academic, Technician,
-- Supervisor, Administrator ("Technician" = the "Maintenance Officer" of
-- 7.3.1, matching the FR-07..FR-11 wording and the 6.5 dashboard name).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS wpu_maintenance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wpu_maintenance;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 7.3.1 roles
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    role_id     INT AUTO_INCREMENT PRIMARY KEY,
    role_name   VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7.3.2 users
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    user_id           INT AUTO_INCREMENT PRIMARY KEY,
    full_name         VARCHAR(100) NOT NULL,
    university_email  VARCHAR(150) NOT NULL UNIQUE,
    id_number         VARCHAR(30) NOT NULL UNIQUE,
    password_hash     VARCHAR(255) NOT NULL,
    role_id           INT NOT NULL,
    phone_number      VARCHAR(20) NULL,
    is_active         BOOLEAN NOT NULL DEFAULT TRUE,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Password reset support for FR-02 (no email delivery in scope — Section 3.2 /
-- Section 9 — so the token is a screen-displayed, single-use, time-limited code)
CREATE TABLE IF NOT EXISTS password_resets (
    reset_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    token       VARCHAR(64) NOT NULL UNIQUE,
    expires_at  TIMESTAMP NOT NULL,
    used        BOOLEAN NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7.3.3 buildings
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS buildings (
    building_id    INT AUTO_INCREMENT PRIMARY KEY,
    building_name  VARCHAR(100) NOT NULL,
    building_code  VARCHAR(20) NULL UNIQUE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7.3.4 rooms
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rooms (
    room_id      INT AUTO_INCREMENT PRIMARY KEY,
    building_id  INT NOT NULL,
    room_number  VARCHAR(20) NOT NULL,
    room_type    VARCHAR(50) NULL,
    CONSTRAINT fk_rooms_building FOREIGN KEY (building_id) REFERENCES buildings(building_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7.3.5 categories
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    category_id    INT AUTO_INCREMENT PRIMARY KEY,
    category_name  VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7.3.6 maintenance_requests
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS maintenance_requests (
    request_id     INT AUTO_INCREMENT PRIMARY KEY,
    requester_id   INT NOT NULL,
    title          VARCHAR(150) NOT NULL,
    description    TEXT NOT NULL,
    building_id    INT NOT NULL,
    room_id        INT NULL,
    category_id    INT NOT NULL,
    priority       ENUM('Low','Medium','High','Urgent') NOT NULL,
    status         ENUM('Submitted','Assigned','Pending','Completed','Rejected') NOT NULL DEFAULT 'Submitted',
    submitted_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at   TIMESTAMP NULL,
    CONSTRAINT fk_req_requester FOREIGN KEY (requester_id) REFERENCES users(user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_req_building  FOREIGN KEY (building_id)  REFERENCES buildings(building_id) ON DELETE RESTRICT,
    CONSTRAINT fk_req_room      FOREIGN KEY (room_id)      REFERENCES rooms(room_id) ON DELETE RESTRICT,
    CONSTRAINT fk_req_category  FOREIGN KEY (category_id)  REFERENCES categories(category_id) ON DELETE RESTRICT,
    INDEX idx_req_status (status),
    INDEX idx_req_priority (priority),
    INDEX idx_req_submitted (submitted_at),
    INDEX idx_req_building (building_id),
    INDEX idx_req_category (category_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7.3.7 assignments
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS assignments (
    assignment_id  INT AUTO_INCREMENT PRIMARY KEY,
    request_id     INT NOT NULL,
    technician_id  INT NOT NULL,
    assigned_by    INT NOT NULL,
    assigned_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    due_date       DATE NULL,
    is_current     BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT fk_assign_request    FOREIGN KEY (request_id)    REFERENCES maintenance_requests(request_id) ON DELETE CASCADE,
    CONSTRAINT fk_assign_technician FOREIGN KEY (technician_id) REFERENCES users(user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_assign_by         FOREIGN KEY (assigned_by)   REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_assign_tech_current (technician_id, is_current)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7.3.8 request_status_history
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS request_status_history (
    history_id   INT AUTO_INCREMENT PRIMARY KEY,
    request_id   INT NOT NULL,
    old_status   ENUM('Submitted','Assigned','Pending','Completed','Rejected') NULL,
    new_status   ENUM('Submitted','Assigned','Pending','Completed','Rejected') NOT NULL,
    changed_by   INT NOT NULL,
    changed_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    remarks      TEXT NULL,
    CONSTRAINT fk_hist_request FOREIGN KEY (request_id) REFERENCES maintenance_requests(request_id) ON DELETE CASCADE,
    CONSTRAINT fk_hist_user    FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7.3.9 attachments
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS attachments (
    attachment_id     INT AUTO_INCREMENT PRIMARY KEY,
    request_id        INT NOT NULL,
    uploaded_by       INT NOT NULL,
    file_path         VARCHAR(255) NOT NULL,
    attachment_stage  ENUM('Request','Before-Work','After-Work') NOT NULL,
    uploaded_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_att_request FOREIGN KEY (request_id)  REFERENCES maintenance_requests(request_id) ON DELETE CASCADE,
    CONSTRAINT fk_att_user    FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7.3.10 work_comments
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS work_comments (
    comment_id     INT AUTO_INCREMENT PRIMARY KEY,
    request_id     INT NOT NULL,
    technician_id  INT NOT NULL,
    comment        TEXT NOT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comment_request    FOREIGN KEY (request_id)    REFERENCES maintenance_requests(request_id) ON DELETE CASCADE,
    CONSTRAINT fk_comment_technician FOREIGN KEY (technician_id) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7.3.11 notifications  (Dashboard alerts only — email is out of scope, Section 3.2/9)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    notification_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id            INT NOT NULL,
    request_id         INT NULL,
    message            VARCHAR(255) NOT NULL,
    notification_type  ENUM('Dashboard') NOT NULL DEFAULT 'Dashboard',
    is_read            BOOLEAN NOT NULL DEFAULT FALSE,
    sent_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user    FOREIGN KEY (user_id)    REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_request FOREIGN KEY (request_id) REFERENCES maintenance_requests(request_id) ON DELETE CASCADE,
    INDEX idx_notif_user_read (user_id, is_read)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7.3.12 audit_logs
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    log_id       INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NULL,
    action       VARCHAR(100) NOT NULL,
    entity_type  VARCHAR(50) NULL,
    entity_id    INT NULL,
    ip_address   VARCHAR(45) NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- app_settings — simple key/value store backing the Admin "System Settings"
-- screen (Section 6.7). Not itemized in the source SRS; added here so the
-- dashboard section has a working, minimal implementation.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS app_settings (
    setting_key    VARCHAR(50) PRIMARY KEY,
    setting_value  VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 7.6 Reporting Support — views
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW vw_request_summary AS
SELECT
    r.request_id,
    r.title,
    r.status,
    r.priority,
    r.submitted_at,
    r.completed_at,
    b.building_name,
    rm.room_number,
    c.category_name,
    u.full_name AS requester_name,
    tech.full_name AS technician_name
FROM maintenance_requests r
JOIN buildings b ON b.building_id = r.building_id
LEFT JOIN rooms rm ON rm.room_id = r.room_id
JOIN categories c ON c.category_id = r.category_id
JOIN users u ON u.user_id = r.requester_id
LEFT JOIN assignments a ON a.request_id = r.request_id AND a.is_current = TRUE
LEFT JOIN users tech ON tech.user_id = a.technician_id;

CREATE OR REPLACE VIEW vw_dashboard_metrics AS
SELECT
    DATE_FORMAT(r.submitted_at, '%Y-%m') AS month,
    b.building_name,
    c.category_name,
    a.technician_id,
    r.status,
    COUNT(*) AS total_requests,
    AVG(TIMESTAMPDIFF(HOUR, r.submitted_at, r.completed_at)) AS avg_resolution_hours
FROM maintenance_requests r
JOIN buildings b ON b.building_id = r.building_id
JOIN categories c ON c.category_id = r.category_id
LEFT JOIN assignments a ON a.request_id = r.request_id AND a.is_current = TRUE
GROUP BY month, b.building_name, c.category_name, a.technician_id, r.status;
