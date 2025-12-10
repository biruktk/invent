BEGIN TRANSACTION;
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS companies (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  owner_user_id INTEGER,
  address TEXT,
  plan TEXT,
  plan_start TEXT,
  plan_end TEXT,
  billing_status TEXT DEFAULT 'unpaid',
  paid_until TEXT,
  is_active INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS roles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  description TEXT
);

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id INTEGER,
  username TEXT,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role_id INTEGER,
  status TEXT NOT NULL DEFAULT 'active',
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE SET NULL,
  FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS products (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id INTEGER,
  sku TEXT,
  name TEXT NOT NULL,
  description TEXT,
  cost_price_cents INTEGER NOT NULL DEFAULT 0,
  sale_price_cents INTEGER NOT NULL DEFAULT 0,
  reorder_level INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS locations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS stock_levels (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  product_id INTEGER NOT NULL,
  location_id INTEGER NOT NULL,
  quantity INTEGER NOT NULL DEFAULT 0,
  updated_at TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE(product_id, location_id),
  FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY(location_id) REFERENCES locations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS stock_movements (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id INTEGER,
  product_id INTEGER NOT NULL,
  from_location_id INTEGER,
  to_location_id INTEGER,
  quantity INTEGER NOT NULL,
  type TEXT NOT NULL,
  reference TEXT,
  performed_by INTEGER,
  note TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE CASCADE,
  FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS customers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  phone TEXT,
  email TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS sales (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id INTEGER NOT NULL,
  customer_id INTEGER,
  invoice_number TEXT,
  payment_method TEXT NOT NULL CHECK(payment_method IN ('cash','bank','prepaid','credit')),
  paid_via TEXT,
  bank_id INTEGER,
  total_cents INTEGER NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  due_date TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE CASCADE,
  FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  FOREIGN KEY(bank_id) REFERENCES banks(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS sale_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  sale_id INTEGER NOT NULL,
  product_id INTEGER,
  name TEXT NOT NULL,
  qty INTEGER NOT NULL,
  unit_price_cents INTEGER NOT NULL,
  tax_pct REAL NOT NULL DEFAULT 0,
  line_total_cents INTEGER NOT NULL,
  FOREIGN KEY(sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS purchases (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id INTEGER NOT NULL,
  supplier TEXT,
  payment_type TEXT NOT NULL CHECK(payment_type IN ('cash','bank','credit','prepaid')),
  bank_id INTEGER,
  total_cents INTEGER NOT NULL,
  status TEXT NOT NULL DEFAULT 'paid',
  due_date TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE CASCADE,
  FOREIGN KEY(bank_id) REFERENCES banks(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS purchase_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  purchase_id INTEGER NOT NULL,
  product_id INTEGER,
  name TEXT NOT NULL,
  qty INTEGER NOT NULL,
  unit_price_cents INTEGER NOT NULL,
  tax_pct REAL NOT NULL DEFAULT 0,
  line_total_cents INTEGER NOT NULL,
  FOREIGN KEY(purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
  FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS banks (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  balance_cents INTEGER NOT NULL DEFAULT 0,
  currency TEXT NOT NULL DEFAULT 'USD',
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS payments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id INTEGER NOT NULL,
  related_type TEXT NOT NULL,
  related_id INTEGER NOT NULL,
  amount_cents INTEGER NOT NULL,
  method TEXT NOT NULL,
  bank_id INTEGER,
  transaction_ref TEXT,
  recorded_by INTEGER,
  recorded_at TEXT NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE CASCADE,
  FOREIGN KEY(bank_id) REFERENCES banks(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS misc (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  reason TEXT,
  amount_cents INTEGER NOT NULL,
  bank_id INTEGER,
  date TEXT NOT NULL DEFAULT (date('now')),
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  recorded_by INTEGER,
  FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS audit_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id INTEGER,
  user_id INTEGER,
  action TEXT NOT NULL,
  object_type TEXT,
  object_id INTEGER,
  data TEXT,
  ip_address TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

COMMIT;

-- end schema
