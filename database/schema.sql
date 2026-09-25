CREATE DATABASE IF NOT EXISTS traffic_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE traffic_system;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS challans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  vehicle_no VARCHAR(20) NOT NULL,
  violation VARCHAR(100) NOT NULL,
  fine_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status ENUM('Paid','Unpaid') NOT NULL DEFAULT 'Unpaid',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_challans_user_id (user_id),
  INDEX idx_challans_vehicle_no (vehicle_no),
  CONSTRAINT fk_challans_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  challan_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_payments_challan_id (challan_id),
  CONSTRAINT fk_payments_challan FOREIGN KEY (challan_id) REFERENCES challans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
