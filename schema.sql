-- Create database
CREATE DATABASE wpu_mrs;
USE wpu_mrs;

-- Roles
CREATE TABLE roles (
  role_id INT AUTO_INCREMENT PRIMARY KEY,
  role_name VARCHAR(30) UNIQUE NOT NULL
);

-- Users
CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  university_email VARCHAR(150) UNIQUE NOT NULL,
  id_number VARCHAR(30) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role_id INT NOT NULL,
  phone_number VARCHAR(20),
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (role_id) REFERENCES roles(role_id)
);

-- Buildings
CREATE TABLE buildings (
  building_id INT AUTO_INCREMENT PRIMARY KEY,
  building_name VARCHAR(100) NOT NULL,
  building_code VARCHAR(20) UNIQUE
);

-- Rooms
CREATE TABLE rooms (
  room_id INT AUTO_INCREMENT PRIMARY KEY,
  building_id INT NOT NULL,
  room_number VARCHAR(20) NOT NULL,
  room_type VARCHAR(50),
  FOREIGN KEY (building_id) REFERENCES buildings(building_id)
);

-- Categories
CREATE TABLE categories (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(50) UNIQUE NOT NULL
);

-- Maintenance Requests
CREATE TABLE maintenance_requests (
  request_id INT AUTO_INCREMENT PRIMARY KEY,
  requester_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  building_id INT NOT NULL,
  room_id INT,
  category_id INT NOT NULL,
  priority ENUM('Low','Medium','High','Urgent') NOT NULL,
  status ENUM('Submitted','Assigned','Pending','Completed','Rejected') DEFAULT 'Submitted',
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL,
  FOREIGN KEY (requester_id) REFERENCES users(user_id),
  FOREIGN KEY (building_id) REFERENCES buildings(building_id),
  FOREIGN KEY (room_id) REFERENCES rooms(room_id),
  FOREIGN KEY (category_id) REFERENCES categories(category_id)
);

-- Assignments
CREATE TABLE assignments (
  assignment_id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  technician_id INT NOT NULL,
  assigned_by INT NOT NULL,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  due_date DATE,
  is_current BOOLEAN DEFAULT TRUE,
  FOREIGN KEY (request_id) REFERENCES maintenance_requests(request_id),
  FOREIGN KEY (technician_id) REFERENCES users(user_id),
  FOREIGN KEY (assigned_by) REFERENCES users(user_id)
);

-- Request Status History
CREATE TABLE request_status_history (
  history_id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  old_status VARCHAR(15),
  new_status VARCHAR(15) NOT NULL,
  changed_by INT NOT NULL,
  changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  remarks TEXT,
  FOREIGN KEY (request_id) REFERENCES maintenance_requests(request_id),
  FOREIGN KEY (changed_by) REFERENCES users(user_id)
);

-- Attachments
CREATE TABLE attachments (
  attachment_id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  uploaded_by INT NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  attachment_stage ENUM('Request','Before-Work','After-Work') NOT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (request_id) REFERENCES maintenance_requests(request_id),
  FOREIGN KEY (uploaded_by) REFERENCES users(user_id)
);

-- Work Comments
CREATE TABLE work_comments (
  comment_id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  technician_id INT NOT NULL,
  comment TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (request_id) REFERENCES maintenance_requests(request_id),
  FOREIGN KEY (technician_id) REFERENCES users(user_id)
);

-- Notifications
CREATE TABLE notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  request_id INT,
  message VARCHAR(255) NOT NULL,
  notification_type ENUM('Email','Dashboard') NOT NULL,
  is_read BOOLEAN DEFAULT FALSE,
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  FOREIGN KEY (request_id) REFERENCES maintenance_requests(request_id)
);

-- Audit Logs
CREATE TABLE audit_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(50),
  entity_id INT,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Password Reset Tokens
CREATE TABLE password_resets (
  reset_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);
