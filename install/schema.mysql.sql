-- Nonogram Studio schema (MySQL 5.7+ / MariaDB 10.3+)
-- install.php runs this automatically. You can also import it in phpMyAdmin.

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'user',
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL,
  last_login_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entitlements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  product VARCHAR(30) NOT NULL,
  source VARCHAR(20) NOT NULL DEFAULT 'admin',
  txn_id VARCHAR(100) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  KEY idx_ent_user (user_id, active),
  CONSTRAINT fk_ent_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchases (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  txn_id VARCHAR(100) NOT NULL,
  event VARCHAR(30) NOT NULL,
  product VARCHAR(30) NOT NULL,
  email VARCHAR(190) NOT NULL,
  user_id INT UNSIGNED NULL,
  amount DECIMAL(10,2) NULL,
  raw MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_txn_event (txn_id, event)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  purpose VARCHAR(20) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_tok_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  email VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_la_email (email, created_at),
  KEY idx_la_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  settings TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_proj_user (user_id, updated_at),
  CONSTRAINT fk_proj_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS puzzles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  position INT NOT NULL DEFAULT 0,
  name VARCHAR(160) NOT NULL,
  w SMALLINT NOT NULL,
  h SMALLINT NOT NULL,
  colors TINYINT NOT NULL DEFAULT 1,
  grid TEXT NOT NULL,
  palette VARCHAR(255) NOT NULL,
  row_clues MEDIUMTEXT NOT NULL,
  col_clues MEDIUMTEXT NOT NULL,
  difficulty VARCHAR(20) NOT NULL,
  score DECIMAL(8,2) NOT NULL DEFAULT 0,
  is_unique TINYINT(1) NOT NULL DEFAULT 0,
  hash CHAR(16) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_pz_project (project_id, position),
  KEY idx_pz_hash (project_id, hash),
  CONSTRAINT fk_pz_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
