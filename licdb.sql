ALTER TABLE licenses ADD COLUMN max_devices INT NOT NULL DEFAULT 1 AFTER plan;

CREATE TABLE IF NOT EXISTS license_devices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  license_id INT NOT NULL,
  hwid VARCHAR(64) NOT NULL,
  first_used DATETIME DEFAULT CURRENT_TIMESTAMP,
  last_used DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_lic_hwid (license_id, hwid),
  KEY idx_lic (license_id)
);