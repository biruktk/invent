PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;
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
INSERT INTO users VALUES(1,'tygaby','jipyle@mailinator.com','paid','$2y$12$zOU8vZA.ufXnasHFP9X0Yut1Yq2d61cJBqLqzZzQ6AGn0Qp3jFL5u','6','6',1,'admin','2025-11-28 15:21:36',NULL);
CREATE TABLE companies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        owner_id INTEGER,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
    );
INSERT INTO companies VALUES(1,'JJ',NULL,'2025-11-28 15:21:24');
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
INSERT INTO items VALUES(1,'Ali Boone','Willow Mason','2',167,89100,77.0,'2025-11-28 15:21:58',1,NULL);
INSERT INTO items VALUES(2,'Heather Manning','Brooke Fitzpatrick','756',560,14900,44.0,'2025-11-28 15:22:14',1,NULL);
INSERT INTO items VALUES(3,'Meghan Edwards','Samantha Hubbard','187',573,15400,40.0,'2025-11-28 15:23:05',1,NULL);
INSERT INTO items VALUES(4,'Noelle Workman','Bruce Wiggins','689',124,55400,40.0,'2025-11-28 15:23:17',1,NULL);
INSERT INTO items VALUES(5,'Armand Barnes','Gabriel Cooke','715',60,35100,36.0,'2025-11-28 15:23:50',1,NULL);
INSERT INTO items VALUES(6,'Ivory Hampton','Quintessa Valencia','284',689,68800,63.0,'2025-11-30 17:07:59',15,'uploads/items/item_692c79efa0a31_1764522479.png');
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
);
INSERT INTO purchases VALUES(1,1,'Ali Boone','Willow Mason','2',171,89100,77.0,'cash',NULL,'',0,'2025-11-28',26967897,'2025-11-28 15:21:58',1,NULL);
INSERT INTO purchases VALUES(2,2,'Heather Manning','Brooke Fitzpatrick','756',562,14900,44.0,'cash',NULL,'',0,'2025-11-28',12058272,'2025-11-28 15:22:14',1,NULL);
INSERT INTO purchases VALUES(3,3,'Meghan Edwards','Samantha Hubbard','187',573,15400,40.0,'bank',1,'',0,'2025-11-28',12353880,'2025-11-28 15:23:05',1,NULL);
INSERT INTO purchases VALUES(4,4,'Noelle Workman','Bruce Wiggins','689',124,55400,40.0,'prepaid',NULL,'2025-11-20',11100,'2025-11-28',9617440,'2025-11-28 15:23:17',1,NULL);
INSERT INTO purchases VALUES(5,5,'Armand Barnes','Gabriel Cooke','715',60,35100,36.0,'credit',NULL,'2025-11-26',0,'2025-11-28',2864160,'2025-11-28 15:23:50',1,NULL);
INSERT INTO purchases VALUES(6,6,'Ivory Hampton','Quintessa Valencia','284',690,68800,63.0,'cash',NULL,'',0,'2025-11-30',77379360,'2025-11-30 17:07:59',15,NULL);
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
);
INSERT INTO sales VALUES(1,1,'Ali Boone',1,89100,77.0,'Paid','bank',1,0,'','2025-11-28',157707,'2025-11-28 15:25:14',1,'2','33D','333','paid');
INSERT INTO sales VALUES(3,1,'Ali Boone',1,89100,77.0,'Paid','cash',1,0,'','2025-11-28',157707,'2025-11-28 15:26:12',1,'0','JJ','88','paid');
INSERT INTO sales VALUES(4,1,'Ali Boone',1,89100,77.0,'Paid','cash',1,0,'','2025-11-28',157707,'2025-11-28 15:27:48',1,'0','JJI','99999','paid');
INSERT INTO sales VALUES(5,2,'Heather Manning',1,14900,44.0,'Credit','bank',1,0,'2025-11-21','2025-11-28',21456,'2025-11-28 15:34:00',1,'0','8','999','paid');
INSERT INTO sales VALUES(6,1,'Ali Boone',1,89100,7.0,'Credit','bank',1,0,'2025-11-21','2025-11-28',95337,'2025-11-28 15:34:45',1,'9','JJJOOO','09999','paid');
INSERT INTO sales VALUES(7,6,'Ivory Hampton',1,68800,63.0,'Paid','cash',NULL,0,'','2025-11-30',112144,'2025-11-30 17:08:15',15,'','ss','66666','paid');
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
INSERT INTO banks VALUES(1,'Fay Gould',197920620,'2025-11-28 15:22:07',1);
INSERT INTO banks VALUES(2,'Holly Goodwin',980000,'2025-11-30 17:07:49',15);
DELETE FROM sqlite_sequence;
INSERT INTO sqlite_sequence VALUES('companies',1);
INSERT INTO sqlite_sequence VALUES('users',1);
INSERT INTO sqlite_sequence VALUES('items',6);
INSERT INTO sqlite_sequence VALUES('purchases',6);
INSERT INTO sqlite_sequence VALUES('banks',2);
INSERT INTO sqlite_sequence VALUES('sales',7);
CREATE INDEX idx_banks_user ON banks(user_id);
CREATE INDEX idx_items_user ON items(user_id);
CREATE INDEX idx_purchases_user ON purchases(user_id);
CREATE INDEX idx_sales_user ON sales(user_id);
CREATE INDEX idx_misc_user ON misc(user_id);
COMMIT;
