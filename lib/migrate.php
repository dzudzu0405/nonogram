<?php
// Database upgrades. Updating on cPanel means extracting a new zip over the old files, so new tables have to
// appear by themselves: bootstrap calls migrate() and it runs once per schema version (cheap file check after).
// Every step is idempotent (CREATE TABLE IF NOT EXISTS), so running it twice is harmless.

const SCHEMA_VERSION = 3;

function schema_version_file(): string
{
    return APP_ROOT . '/storage/schema.version';
}

/** Runs pending upgrades. Silent no-op before the app is installed (no users table yet). */
function migrate(bool $force = false): void
{
    $file = schema_version_file();
    $have = is_file($file) ? (int) trim((string) file_get_contents($file)) : 0;
    if (!$force && $have >= SCHEMA_VERSION) return;
    try {
        db()->query('SELECT 1 FROM users LIMIT 1');
    } catch (Throwable $e) {
        return; // not installed yet: install.php creates everything
    }
    foreach (schema_v2() as $sql) db()->exec($sql);
    schema_v3();
    @file_put_contents($file, SCHEMA_VERSION . "\n");
}

/** v2: WarriorPlus access links and the product activity log. Password reset tokens use the existing tokens table. */
function schema_v2(): array
{
    if (db_driver() === 'sqlite') {
        return [
            'CREATE TABLE IF NOT EXISTS access_links (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              code TEXT NOT NULL UNIQUE,
              label TEXT NOT NULL,
              products TEXT NOT NULL,
              note TEXT NULL,
              max_uses INTEGER NOT NULL DEFAULT 0,
              uses INTEGER NOT NULL DEFAULT 0,
              expires_at TEXT NULL,
              is_active INTEGER NOT NULL DEFAULT 1,
              created_by INTEGER NULL,
              created_at TEXT NOT NULL,
              updated_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS product_events (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              user_id INTEGER NOT NULL,
              product TEXT NOT NULL,
              action TEXT NOT NULL,
              source TEXT NOT NULL,
              link_id INTEGER NULL,
              actor_id INTEGER NULL,
              note TEXT NULL,
              ip TEXT NULL,
              created_at TEXT NOT NULL
            )',
            'CREATE INDEX IF NOT EXISTS idx_pe_user ON product_events(user_id, created_at)',
            'CREATE INDEX IF NOT EXISTS idx_pe_link ON product_events(link_id)',
            'CREATE INDEX IF NOT EXISTS idx_pe_created ON product_events(created_at)',
        ];
    }
    $tail = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'CREATE TABLE IF NOT EXISTS access_links (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          code VARCHAR(64) NOT NULL UNIQUE,
          label VARCHAR(120) NOT NULL,
          products VARCHAR(255) NOT NULL,
          note VARCHAR(255) NULL,
          max_uses INT NOT NULL DEFAULT 0,
          uses INT NOT NULL DEFAULT 0,
          expires_at DATETIME NULL,
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          created_by INT UNSIGNED NULL,
          created_at DATETIME NOT NULL,
          updated_at DATETIME NOT NULL
        )' . $tail,
        // No foreign key on user_id: the log outlives deleted accounts.
        'CREATE TABLE IF NOT EXISTS product_events (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          user_id INT UNSIGNED NOT NULL,
          product VARCHAR(30) NOT NULL,
          action VARCHAR(10) NOT NULL,
          source VARCHAR(20) NOT NULL,
          link_id INT UNSIGNED NULL,
          actor_id INT UNSIGNED NULL,
          note VARCHAR(255) NULL,
          ip VARCHAR(45) NULL,
          created_at DATETIME NOT NULL,
          KEY idx_pe_user (user_id, created_at),
          KEY idx_pe_link (link_id),
          KEY idx_pe_created (created_at)
        )' . $tail,
    ];
}

function has_column(string $table, string $col): bool
{
    if (db_driver() === 'sqlite') {
        foreach (q("PRAGMA table_info($table)")->fetchAll() as $c) if ($c['name'] === $col) return true;
        return false;
    }
    return (bool) q("SHOW COLUMNS FROM $table LIKE " . db()->quote($col))->fetch();
}

/** v3: Google sign-in (users.google_id) and the export history (My Exports). */
function schema_v3(): void
{
    if (!has_column('users', 'google_id')) {
        db()->exec('ALTER TABLE users ADD COLUMN google_id VARCHAR(64) NULL');
        db()->exec('CREATE INDEX idx_users_google ON users(google_id)');
    }
    if (db_driver() === 'sqlite') {
        db()->exec('CREATE TABLE IF NOT EXISTS exports (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
          project_id INTEGER NULL,
          kind TEXT NOT NULL,
          name TEXT NOT NULL,
          page_size TEXT NULL,
          puzzle_count INTEGER NOT NULL DEFAULT 0,
          page_count INTEGER NOT NULL DEFAULT 0,
          created_at TEXT NOT NULL
        )');
        db()->exec('CREATE INDEX IF NOT EXISTS idx_exports_user ON exports(user_id, created_at)');
        return;
    }
    // project_id has no foreign key: the history keeps rows for deleted projects ("deleted project").
    db()->exec('CREATE TABLE IF NOT EXISTS exports (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id INT UNSIGNED NOT NULL,
      project_id INT UNSIGNED NULL,
      kind VARCHAR(20) NOT NULL,
      name VARCHAR(160) NOT NULL,
      page_size VARCHAR(10) NULL,
      puzzle_count INT NOT NULL DEFAULT 0,
      page_count INT NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL,
      KEY idx_exports_user (user_id, created_at),
      CONSTRAINT fk_exports_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}
