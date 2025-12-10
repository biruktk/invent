-- Normalized schema for Invent (SQLite)
PRAGMA foreign_keys = ON;

BEGIN TRANSACTION;

-- Roles table to normalize permissions
CREATE TABLE IF NOT EXISTS roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT
);

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role_id INTEGER REFERENCES roles(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT,
    CHECK(username <> ''),
    CHECK(email <> '')
);

-- Categories for items
CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT
);

-- Items table
CREATE TABLE IF NOT EXISTS items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sku TEXT UNIQUE,
    name TEXT NOT NULL,
    description TEXT,
    category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
    quantity INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT
);

-- Audits: changes to items (normalized history)
CREATE TABLE IF NOT EXISTS audits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER REFERENCES items(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    action TEXT NOT NULL,
    delta TEXT,
    created_at TEXT NOT NULL
);

-- Optional attachments or files metadata
CREATE TABLE IF NOT EXISTS attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER REFERENCES items(id) ON DELETE CASCADE,
    filename TEXT NOT NULL,
    mime TEXT,
    created_at TEXT NOT NULL
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_items_category ON items(category_id);
CREATE INDEX IF NOT EXISTS idx_audits_item ON audits(item_id);
CREATE INDEX IF NOT EXISTS idx_audits_user ON audits(user_id);

-- Seed roles
INSERT OR IGNORE INTO roles (id, name, description) VALUES (1, 'admin', 'Administrator with full access');
INSERT OR IGNORE INTO roles (id, name, description) VALUES (2, 'user', 'Standard user');

COMMIT;
