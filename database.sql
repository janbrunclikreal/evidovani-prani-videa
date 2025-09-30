-- Evidence přání videií v3.1.1
-- Databázová struktura pro MySQL 5.7.34

CREATE DATABASE IF NOT EXISTS evidence_prani DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE evidence_prani;

-- Tabulka uživatelů
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabulka záznamů přání
CREATE TABLE IF NOT EXISTS records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    datum DATE NOT NULL,
    jmeno VARCHAR(100) NOT NULL,
    ucet VARCHAR(50),
    castka DECIMAL(10,2),
    stav ENUM('zaplaceno', 'zaslano', 'odmitnuto', 'rozpracovane') DEFAULT 'rozpracovane',
    prani TEXT,
    nick VARCHAR(50),
    link VARCHAR(255),
    faktura VARCHAR(100),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_datum (datum),
    INDEX idx_stav (stav),
    INDEX idx_nick (nick)
);

-- Tabulka audit logů pro sledování všech akcí uživatelů
CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(50) NULL,
    action_type VARCHAR(50) NOT NULL,
    affected_table VARCHAR(50) NULL,
    affected_id INT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    severity ENUM('info', 'warning', 'error', 'critical') DEFAULT 'info',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_username (username),
    INDEX idx_action_type (action_type),
    INDEX idx_affected_table (affected_table),
    INDEX idx_affected_id (affected_id),
    INDEX idx_severity (severity),
    INDEX idx_created_at (created_at),
    INDEX idx_ip_address (ip_address)
);

-- Vložení výchozího administrátora
INSERT INTO users (username, password, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- Heslo: password

-- Vložení ukázkového uživatele
INSERT INTO users (username, password, role) VALUES 
('user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user');
-- Heslo: password