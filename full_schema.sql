CREATE TABLE cust (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    phone TEXT UNIQUE NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    pstatus TEXT DEFAULT 'inactive',
    password_hash TEXT NOT NULL,
    txn TEXT,
    pkg TEXT,
    company_id INTEGER,
    role TEXT NOT NULL DEFAULT 'employee',
    created_at TEXT NOT NULL DEFAULT (datetime('now')), permissions TEXT DEFAULT '[]',
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);


CREATE TABLE companies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        owner_id INTEGER,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
    );
CREATE TABLE items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    brand_name TEXT,
    item_number TEXT,
    qty INTEGER NOT NULL CHECK(qty >= 0),
    price_cents INTEGER NOT NULL CHECK(price_cents >= 0),
    tax_pct REAL NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    user_id INTEGER NOT NULL
, image_path TEXT);
CREATE TABLE purchases (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  item_id INTEGER NOT NULL REFERENCES items(id) ON DELETE CASCADE,
  item_name TEXT NOT NULL,
  brand_name TEXT,
  item_number TEXT,
  qty INTEGER NOT NULL,
  price_cents INTEGER NOT NULL,
  tax_pct REAL NOT NULL DEFAULT 0,
  -- payment type: 'cash', 'credit', 'bank', 'prepaid'
  payment_type TEXT NOT NULL CHECK (payment_type IN ('cash','credit','bank','prepaid')),
  -- for bank payments
  bank_id INTEGER REFERENCES banks(id)  DEFAULT NULL,
  -- for credit purchases
  due_date TEXT,
  -- for prepaid purchases
  prepaid_min_cents INTEGER CHECK (prepaid_min_cents >= 0),
  date TEXT NOT NULL DEFAULT (date('now')),
  total_cents INTEGER NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
    user_id INTEGER NOT NULL
    ,ids TEXT
, status TEXT DEFAULT 'paid');
CREATE TABLE sales (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  item_id INTEGER REFERENCES items(id) ON DELETE SET NULL,
  item_name TEXT NOT NULL,
  qty INTEGER NOT NULL,
  price_cents INTEGER NOT NULL,
  tax_pct REAL NOT NULL DEFAULT 0,
  payment_method TEXT NOT NULL CHECK(payment_method IN ('Paid','Pre-paid','Credit')),
  paid_via TEXT, -- 'bank' or 'cash' (updated when payment is made)
  bank_id INTEGER REFERENCES banks(id) ON DELETE SET NULL,
  prepayment_cents INTEGER DEFAULT 0, -- only for Pre-paid
  due_date TEXT, -- for Pre-paid and Credit
  date TEXT NOT NULL DEFAULT (date('now')),
  total_cents INTEGER NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
    user_id INTEGER NOT NULL ,
    ids TEXT ,
    username TEXT,
    phone TEXT,
    status TEXT DEFAULT 'pending'
, item_number TEXT);
CREATE TABLE misc (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  reason TEXT,
  amount_cents INTEGER NOT NULL,
  bank_id INTEGER NOT NULL REFERENCES banks(id) ON DELETE RESTRICT,
  date TEXT NOT NULL DEFAULT (date('now')),
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
    user_id INTEGER NOT NULL
);
CREATE TABLE banks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    balance_cents INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    user_id INTEGER NOT NULL
);
CREATE INDEX idx_banks_user ON banks(user_id);
CREATE INDEX idx_items_user ON items(user_id);
CREATE INDEX idx_purchases_user ON purchases(user_id);
CREATE INDEX idx_sales_user ON sales(user_id);
CREATE INDEX idx_misc_user ON misc(user_id);
/* No STAT tables available */
