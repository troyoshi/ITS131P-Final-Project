-- ============================================================
--  CDRC Relief Tracker — Full Database Schema
--  System:  Disaster Relief Distribution Tracking System
--  Org:     Citizens' Disaster Response Center (CDRC)
--  Course:  ITS131P — Group 9
--  DBMS:    MySQL 8.x
--
--  Normalised to 3NF.
--  Run this script in phpMyAdmin or MySQL CLI:
--    SOURCE /path/to/cdrc_database.sql;
-- ============================================================

CREATE DATABASE IF NOT EXISTS cdrc_relief_tracker
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE cdrc_relief_tracker;

-- ============================================================
--  TABLE 1: roles
--  Lookup table for user roles (Admin, Staff, Volunteer).
--  Separated to satisfy 3NF — role name lives in one place.
-- ============================================================
CREATE TABLE IF NOT EXISTS roles (
    role_id     TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_name   VARCHAR(30)  NOT NULL UNIQUE,
    description VARCHAR(120) NOT NULL DEFAULT '',
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO roles (role_name, description) VALUES
  ('Administrator', 'Full system access — users, reports, all records, settings'),
  ('Staff',         'Manage beneficiaries, inventory, distributions, generate reports'),
  ('Volunteer',     'Assist in registration, update distribution records, view centers');


-- ============================================================
--  TABLE 2: users
--  Staff and volunteer accounts.  Passwords hashed with bcrypt.
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    user_id      INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    role_id      TINYINT UNSIGNED NOT NULL,
    first_name   VARCHAR(60)     NOT NULL,
    last_name    VARCHAR(60)     NOT NULL,
    middle_init  CHAR(2)         DEFAULT NULL,
    username     VARCHAR(50)     NOT NULL UNIQUE,
    email        VARCHAR(120)    NOT NULL UNIQUE,
    password     VARCHAR(255)    NOT NULL,            -- bcrypt hash
    contact_no   VARCHAR(20)     DEFAULT NULL,
    department   VARCHAR(80)     DEFAULT NULL,
    is_active    TINYINT(1)      NOT NULL DEFAULT 1,
    last_login   DATETIME        DEFAULT NULL,
    created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(role_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Passwords below are bcrypt hashes of 'Password123!'
INSERT INTO users (role_id, first_name, last_name, middle_init, username, email, password, contact_no, department) VALUES
  (1, 'Juan',    'Admin',      'B', 'juan.admin',    'juan.admin@cdrc.org.ph',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uFutB/ZW2', '+63 917 123 4567', 'Operations'),
  (2, 'Ana',     'Reyes',      'C', 'ana.reyes',     'ana.reyes@cdrc.org.ph',     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uFutB/ZW2', '+63 918 234 5678', 'Field Operations'),
  (2, 'Ben',     'Lim',        'A', 'ben.lim',       'ben.lim@cdrc.org.ph',       '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uFutB/ZW2', '+63 919 345 6789', 'Logistics'),
  (3, 'Francis', 'Go',         'R', 'francis.go',    'francis.go@cdrc.org.ph',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uFutB/ZW2', '+63 920 456 7890', 'Volunteers'),
  (3, 'Carolyne','Abad',       'M', 'carolyne.abad', 'carolyne.abad@cdrc.org.ph', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uFutB/ZW2', '+63 921 567 8901', 'Volunteers');


-- ============================================================
--  TABLE 3: evacuation_centers
--  Physical relief/evacuation sites managed by CDRC.
-- ============================================================
CREATE TABLE IF NOT EXISTS evacuation_centers (
    center_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    center_name     VARCHAR(100) NOT NULL,
    barangay        VARCHAR(80)  NOT NULL,
    city            VARCHAR(80)  NOT NULL DEFAULT 'Quezon City',
    province        VARCHAR(80)  NOT NULL DEFAULT 'Metro Manila',
    max_capacity    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    current_occupancy SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status          ENUM('Active','Full','Closed','Standby') NOT NULL DEFAULT 'Active',
    contact_person  VARCHAR(120) DEFAULT NULL,
    contact_no      VARCHAR(20)  DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_occupancy CHECK (current_occupancy <= max_capacity)
) ENGINE=InnoDB;

INSERT INTO evacuation_centers (center_name, barangay, city, province, max_capacity, current_occupancy, status, contact_person, contact_no) VALUES
  ('Brgy. Masaya Evacuation Center',    'Masaya',     'Quezon City', 'Metro Manila', 200, 154, 'Active',  'Brgy. Captain Santos',   '+63 2 8111 0001'),
  ('Brgy. Maliwanag Relief Hub',        'Maliwanag',  'Quezon City', 'Metro Manila', 150, 120, 'Active',  'Brgy. Captain Cruz',     '+63 2 8111 0002'),
  ('Brgy. Pag-asa Distribution Point',  'Pag-asa',    'Quezon City', 'Metro Manila', 180, 180, 'Full',    'Brgy. Captain Reyes',    '+63 2 8111 0003'),
  ('Brgy. Maligaya Relief Center',      'Maligaya',   'Quezon City', 'Metro Manila', 120, 98,  'Active',  'Brgy. Captain Lim',      '+63 2 8111 0004'),
  ('Brgy. Bagong Pag-asa Hub',          'Bagong Pag-asa', 'Quezon City', 'Metro Manila', 100, 0, 'Standby', 'Brgy. Captain Aquino', '+63 2 8111 0005');


-- ============================================================
--  TABLE 4: special_needs_types
--  Lookup table — avoids repeating strings in beneficiaries.
-- ============================================================
CREATE TABLE IF NOT EXISTS special_needs_types (
    need_id     TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    need_label  VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO special_needs_types (need_label) VALUES
  ('None'),
  ('Elderly (60+)'),
  ('Infant / Young Child'),
  ('Medical Condition'),
  ('Person with Disability'),
  ('Pregnant / Lactating');


-- ============================================================
--  TABLE 5: beneficiaries
--  Disaster-affected households registered by CDRC.
-- ============================================================
CREATE TABLE IF NOT EXISTS beneficiaries (
    beneficiary_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    center_id        INT UNSIGNED     NOT NULL,
    need_id          TINYINT UNSIGNED NOT NULL DEFAULT 1,
    registered_by    INT UNSIGNED     NOT NULL,           -- FK → users
    beneficiary_code VARCHAR(12)      NOT NULL UNIQUE,    -- e.g. BEN-0001
    first_name       VARCHAR(60)      NOT NULL,
    last_name        VARCHAR(60)      NOT NULL,
    middle_name      VARCHAR(60)      DEFAULT NULL,
    household_size   TINYINT UNSIGNED NOT NULL DEFAULT 1,
    address          VARCHAR(200)     NOT NULL,
    barangay         VARCHAR(80)      NOT NULL,
    city             VARCHAR(80)      NOT NULL DEFAULT 'Quezon City',
    contact_no       VARCHAR(20)      DEFAULT NULL,
    status           ENUM('Pending','Served','Priority') NOT NULL DEFAULT 'Pending',
    notes            TEXT             DEFAULT NULL,
    registered_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bene_center   FOREIGN KEY (center_id)     REFERENCES evacuation_centers(center_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_bene_need     FOREIGN KEY (need_id)       REFERENCES special_needs_types(need_id)  ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_bene_reg_by   FOREIGN KEY (registered_by) REFERENCES users(user_id)                ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO beneficiaries (center_id, need_id, registered_by, beneficiary_code, first_name, last_name, household_size, address, barangay, city, contact_no, status, notes) VALUES
  (1, 3, 2, 'BEN-0001', 'Maria',    'Santos',    4, '12 Sampaguita St.',       'Masaya',    'Quezon City', '+63 912 100 0001', 'Served',   'Has a 6-month-old infant'),
  (2, 1, 2, 'BEN-0002', 'Jose',     'Cruz',      6, '45 Rosal St.',            'Maliwanag', 'Quezon City', '+63 912 100 0002', 'Served',   NULL),
  (1, 2, 3, 'BEN-0003', 'Ana',      'Reyes',     3, '8 Dahlia Ave.',           'Masaya',    'Quezon City', '+63 912 100 0003', 'Pending',  'Lola is 78 years old'),
  (3, 4, 2, 'BEN-0004', 'Pedro',    'Lim',       5, '33 Camia St.',            'Pag-asa',   'Quezon City', '+63 912 100 0004', 'Priority', 'Father has hypertension, needs medicine'),
  (4, 1, 3, 'BEN-0005', 'Rosa',     'Aquino',    7, '19 Ilang-Ilang Rd.',      'Maligaya',  'Quezon City', '+63 912 100 0005', 'Served',   NULL),
  (1, 1, 2, 'BEN-0006', 'Juan',     'Garcia',    2, '5 Pikake Blvd.',          'Masaya',    'Quezon City', '+63 912 100 0006', 'Pending',  NULL),
  (3, 2, 4, 'BEN-0007', 'Clara',    'Villanueva',4, '77 Magnolia Lane',        'Pag-asa',   'Quezon City', '+63 912 100 0007', 'Served',   NULL),
  (2, 3, 3, 'BEN-0008', 'Roberto',  'Mendoza',   8, '2 Orchid St.',            'Maliwanag', 'Quezon City', '+63 912 100 0008', 'Priority', 'Twin infants, needs extra milk'),
  (4, 4, 2, 'BEN-0009', 'Nena',     'Dela Cruz', 3, '60 Acacia Ave.',          'Maligaya',  'Quezon City', '+63 912 100 0009', 'Pending',  'Dialysis patient'),
  (1, 1, 3, 'BEN-0010', 'Mario',    'Castillo',  5, '14 Sampaguita Extension', 'Masaya',    'Quezon City', '+63 912 100 0010', 'Served',   NULL);


-- ============================================================
--  TABLE 6: item_categories
--  Groups relief items (Food, Water, Medicine, etc.)
-- ============================================================
CREATE TABLE IF NOT EXISTS item_categories (
    category_id   TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(60) NOT NULL UNIQUE,
    description   VARCHAR(120) DEFAULT NULL
) ENGINE=InnoDB;

INSERT INTO item_categories (category_name, description) VALUES
  ('Food',              'Rice, canned goods, noodles, biscuits'),
  ('Water',             'Drinking water in liters or bottles'),
  ('Hygiene',           'Soap, toothbrush, sanitary napkins, towels'),
  ('Medicine',          'First aid supplies, OTC medications, vitamins'),
  ('Clothing',          'Shirts, shorts, blankets, raincoats'),
  ('Non-food Items',    'Cooking utensils, sleeping mats, lighting');


-- ============================================================
--  TABLE 7: relief_items
--  Master list of all relief goods tracked by the system.
-- ============================================================
CREATE TABLE IF NOT EXISTS relief_items (
    item_id       INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    category_id   TINYINT UNSIGNED NOT NULL,
    item_name     VARCHAR(100)    NOT NULL,
    unit          VARCHAR(30)     NOT NULL,   -- e.g. kg, pack, liter, piece
    current_stock INT UNSIGNED    NOT NULL DEFAULT 0,
    reorder_level INT UNSIGNED    NOT NULL DEFAULT 50,   -- alert threshold
    description   VARCHAR(200)   DEFAULT NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_item_category FOREIGN KEY (category_id) REFERENCES item_categories(category_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO relief_items (category_id, item_name, unit, current_stock, reorder_level, description) VALUES
  (1, 'Rice',             'kg',    342,  100, '25kg sacks distributed as per family size'),
  (1, 'Canned Sardines',  'can',   890,  200, '155g cans, 2 per family member'),
  (1, 'Instant Noodles',  'pack',  1200, 300, 'Assorted flavors'),
  (2, 'Drinking Water',   'liter', 60,   500, '5-liter purified water jugs'),
  (3, 'Hygiene Kit',      'kit',   180,   80, 'Includes soap, toothbrush, sanitary items'),
  (4, 'First Aid Kit',    'kit',   40,    30, 'Basic first aid supplies'),
  (4, 'Paracetamol',      'tablet',500,  200, '500mg, 10 tablets per pack'),
  (5, 'Blanket',          'piece', 220,   60, 'All-weather fleece blanket'),
  (6, 'Sleeping Mat',     'piece', 315,   80, 'Foldable foam mat'),
  (1, 'Biscuits / Bread', 'pack',  640,  150, 'Ready-to-eat snack packs');


-- ============================================================
--  TABLE 8: inventory_transactions
--  Every stock movement (IN = donation/restock, OUT = distributed).
-- ============================================================
CREATE TABLE IF NOT EXISTS inventory_transactions (
    transaction_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id          INT UNSIGNED NOT NULL,
    user_id          INT UNSIGNED NOT NULL,
    transaction_type ENUM('IN','OUT') NOT NULL,
    quantity         INT UNSIGNED NOT NULL,
    reference_note   VARCHAR(200) DEFAULT NULL,   -- donor name, PO#, etc.
    transaction_date DATE         NOT NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_invtx_item FOREIGN KEY (item_id)  REFERENCES relief_items(item_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_invtx_user FOREIGN KEY (user_id)  REFERENCES users(user_id)        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO inventory_transactions (item_id, user_id, transaction_type, quantity, reference_note, transaction_date) VALUES
  (1, 1, 'IN',  500, 'Donation — DSWD Batch 2025-06-01',            '2025-06-01'),
  (4, 1, 'IN',  300, 'Donation — Manila Water Foundation',           '2025-06-01'),
  (5, 2, 'OUT', 44,  'Distribution — Brgy. Masaya Jun 15',           '2025-06-15'),
  (1, 2, 'OUT', 89,  'Distribution — Brgy. Masaya Jun 15',           '2025-06-15'),
  (2, 3, 'OUT', 178, 'Distribution — Brgy. Masaya Jun 15',           '2025-06-15'),
  (6, 1, 'IN',  50,  'Purchase — PO#2025-0012 Red Cross Surplus',    '2025-06-10'),
  (4, 2, 'OUT', 240, 'Distribution — Brgy. Maliwanag Jun 14',        '2025-06-14'),
  (7, 3, 'IN',  600, 'Donation — Generika Foundation',               '2025-06-08'),
  (3, 1, 'IN',  800, 'Donation — SM Cares Drive',                    '2025-06-05'),
  (8, 2, 'OUT', 80,  'Distribution — Brgy. Pag-asa Jun 13',          '2025-06-13');


-- ============================================================
--  TABLE 9: distribution_records
--  One row per beneficiary-per-distribution event.
-- ============================================================
CREATE TABLE IF NOT EXISTS distribution_records (
    distribution_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    beneficiary_id   INT UNSIGNED NOT NULL,
    center_id        INT UNSIGNED NOT NULL,
    distributed_by   INT UNSIGNED NOT NULL,   -- FK → users
    distribution_date DATE        NOT NULL,
    remarks          VARCHAR(255) DEFAULT NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dist_bene    FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(beneficiary_id)    ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_dist_center  FOREIGN KEY (center_id)      REFERENCES evacuation_centers(center_id)   ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_dist_user    FOREIGN KEY (distributed_by) REFERENCES users(user_id)                  ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO distribution_records (beneficiary_id, center_id, distributed_by, distribution_date, remarks) VALUES
  (1, 1, 2, '2025-06-15', 'Full relief pack distributed, extra milk formula included'),
  (2, 2, 3, '2025-06-15', 'Standard pack — family of 6'),
  (4, 3, 2, '2025-06-14', 'Priority distribution, medicine kit included'),
  (5, 4, 3, '2025-06-13', 'Standard pack — large family'),
  (7, 3, 4, '2025-06-12', 'Standard distribution at Pag-asa center'),
  (8, 2, 3, '2025-06-14', 'Extra canned goods for infant household'),
  (10,1, 2, '2025-06-15', 'Standard distribution at Masaya center'),
  (3, 1, 4, '2025-06-11', 'Partial — water not available, to follow up'),
  (6, 1, 2, '2025-06-10', 'Small household — 2 persons'),
  (9, 4, 3, '2025-06-13', 'Prioritised — dialysis patient, medicine included');


-- ============================================================
--  TABLE 10: distribution_items
--  Line items within each distribution (what was given).
--  Junction table between distribution_records and relief_items.
-- ============================================================
CREATE TABLE IF NOT EXISTS distribution_items (
    dist_item_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    distribution_id  INT UNSIGNED NOT NULL,
    item_id          INT UNSIGNED NOT NULL,
    quantity_given   INT UNSIGNED NOT NULL,
    CONSTRAINT fk_di_dist FOREIGN KEY (distribution_id) REFERENCES distribution_records(distribution_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_di_item FOREIGN KEY (item_id)         REFERENCES relief_items(item_id)                ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT uq_di UNIQUE (distribution_id, item_id)
) ENGINE=InnoDB;

INSERT INTO distribution_items (distribution_id, item_id, quantity_given) VALUES
  (1, 1, 4),   -- Distribution 1: 4 kg rice
  (1, 2, 8),   -- Distribution 1: 8 cans sardines
  (1, 4, 20),  -- Distribution 1: 20L water
  (1, 5, 1),   -- Distribution 1: 1 hygiene kit
  (2, 1, 6),(2, 2,12),(2, 3, 6),(2, 9, 3),
  (3, 1, 5),(3, 2,10),(3, 6, 1),(3, 7,10),
  (4, 1, 7),(4, 2,14),(4, 4,35),(4, 5, 1),(4, 8, 4),
  (5, 1, 4),(5, 2, 8),(5, 4,20),
  (6, 1, 8),(6, 2,16),(6, 3, 8),(6, 4,40),(6, 5, 1),
  (7, 1, 5),(7, 2,10),(7, 4,25),
  (8, 1, 3),(8, 2, 6),(8, 3, 3),
  (9, 1, 2),(9, 2, 4),
  (10,1, 3),(10,2, 6),(10,6, 1),(10,7,10);


-- ============================================================
--  USEFUL VIEWS
-- ============================================================

-- View: beneficiary summary (joins center + special needs)
CREATE OR REPLACE VIEW v_beneficiaries AS
SELECT
    b.beneficiary_id,
    b.beneficiary_code,
    CONCAT(b.first_name,' ',b.last_name) AS full_name,
    b.household_size,
    b.address, b.barangay, b.city,
    b.contact_no, b.status, b.notes, b.registered_at,
    e.center_name,
    sn.need_label AS special_need,
    CONCAT(u.first_name,' ',u.last_name) AS registered_by
FROM beneficiaries b
JOIN evacuation_centers     e  ON b.center_id    = e.center_id
JOIN special_needs_types    sn ON b.need_id      = sn.need_id
JOIN users                  u  ON b.registered_by = u.user_id;

-- View: current inventory
CREATE OR REPLACE VIEW v_inventory AS
SELECT
    i.item_id, i.item_name, i.unit,
    c.category_name,
    i.current_stock, i.reorder_level,
    CASE WHEN i.current_stock <= i.reorder_level THEN 'Low Stock' ELSE 'OK' END AS stock_status
FROM relief_items i
JOIN item_categories c ON i.category_id = c.category_id;

-- View: distribution summary
CREATE OR REPLACE VIEW v_distributions AS
SELECT
    dr.distribution_id,
    dr.distribution_date,
    b.beneficiary_code,
    CONCAT(b.first_name,' ',b.last_name) AS beneficiary_name,
    b.household_size,
    e.center_name,
    CONCAT(u.first_name,' ',u.last_name) AS staff_name,
    dr.remarks
FROM distribution_records dr
JOIN beneficiaries        b  ON dr.beneficiary_id = b.beneficiary_id
JOIN evacuation_centers   e  ON dr.center_id      = e.center_id
JOIN users                u  ON dr.distributed_by = u.user_id;

-- ============================================================
--  END OF SCHEMA
-- ============================================================
