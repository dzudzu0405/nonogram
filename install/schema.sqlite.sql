-- Nonogram Studio schema for local testing with SQLite (same tables as schema.mysql.sql)

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL DEFAULT '',
  password_hash TEXT NULL,
  role TEXT NOT NULL DEFAULT 'user',
  status TEXT NOT NULL DEFAULT 'active',
  created_at TEXT NOT NULL,
  last_login_at TEXT NULL
);

CREATE TABLE IF NOT EXISTS entitlements (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  product TEXT NOT NULL,
  source TEXT NOT NULL DEFAULT 'admin',
  txn_id TEXT NULL,
  active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL,
  revoked_at TEXT NULL
);
CREATE INDEX IF NOT EXISTS idx_ent_user ON entitlements(user_id, active);

CREATE TABLE IF NOT EXISTS purchases (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  txn_id TEXT NOT NULL,
  event TEXT NOT NULL,
  product TEXT NOT NULL,
  email TEXT NOT NULL,
  user_id INTEGER NULL,
  amount REAL NULL,
  raw TEXT NULL,
  created_at TEXT NOT NULL,
  UNIQUE (txn_id, event)
);

CREATE TABLE IF NOT EXISTS tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token_hash TEXT NOT NULL UNIQUE,
  purpose TEXT NOT NULL,
  expires_at TEXT NOT NULL,
  used_at TEXT NULL,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS login_attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ip TEXT NOT NULL,
  email TEXT NOT NULL,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_la_email ON login_attempts(email, created_at);
CREATE INDEX IF NOT EXISTS idx_la_ip ON login_attempts(ip, created_at);

CREATE TABLE IF NOT EXISTS projects (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  settings TEXT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_proj_user ON projects(user_id, updated_at);

CREATE TABLE IF NOT EXISTS puzzles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL,
  position INTEGER NOT NULL DEFAULT 0,
  name TEXT NOT NULL,
  w INTEGER NOT NULL,
  h INTEGER NOT NULL,
  colors INTEGER NOT NULL DEFAULT 1,
  grid TEXT NOT NULL,
  palette TEXT NOT NULL,
  row_clues TEXT NOT NULL,
  col_clues TEXT NOT NULL,
  difficulty TEXT NOT NULL,
  score REAL NOT NULL DEFAULT 0,
  is_unique INTEGER NOT NULL DEFAULT 0,
  hash TEXT NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_pz_project ON puzzles(project_id, position);
CREATE INDEX IF NOT EXISTS idx_pz_hash ON puzzles(project_id, hash);
