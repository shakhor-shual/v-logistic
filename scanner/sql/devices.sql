CREATE TABLE devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_code VARCHAR(32) NOT NULL UNIQUE COMMENT 'рег. код (REG-xxxx)',
    device_token VARCHAR(64) NOT NULL UNIQUE COMMENT 'сессионный токен',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_active DATETIME DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1 COMMENT '0 = заблокировано'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
