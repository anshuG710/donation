-- ============================================================
--  DONATE+ Nepal — database schema
--  Derived from the queries used by the PHP pages.
--  Load:  mysql -h 127.0.0.1 -u root donation_db < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS donation_db
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE donation_db;


-- ------------------------------------------------------------
--  USERS
--  login.php / register.php
-- ------------------------------------------------------------


CREATE TABLE IF NOT EXISTS users (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    name              VARCHAR(120)  NOT NULL,
    email             VARCHAR(190)  NOT NULL UNIQUE,
    phone             VARCHAR(30)       NULL,
    password          VARCHAR(255)  NOT NULL,           -- password_hash() output
    security_question VARCHAR(255)      NULL,           -- chosen at registration
    security_answer   VARCHAR(255)      NULL,           -- password_hash() of the answer
    role              ENUM('donor','recipient','admin') NOT NULL DEFAULT 'donor',
    is_active         TINYINT(1)    NOT NULL DEFAULT 1,  -- 0 = disabled account
    address           VARCHAR(255)      NULL,
    created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
--  CATEGORIES
--  add-donation.php and find-donations.php read this for their
--  dropdowns; with no rows here nothing can be donated at all.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS categories (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
--  DONATIONS
--  add-donation.php inserts; my-donations / find-donations /
--  donation-details / donor-dashboard read.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS donations (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    donor_id       INT          NOT NULL,
    category_id    INT              NULL,
    title          VARCHAR(150) NOT NULL,
    description    TEXT             NULL,
    quantity       INT          NOT NULL DEFAULT 1,
    unit           VARCHAR(30)      NULL,   -- pieces, kg, grams, liters, packets, boxes, sets
    item_condition VARCHAR(30)      NULL,   -- New, Like New, Good, Used
    location       VARCHAR(150)     NULL,
    image          VARCHAR(255)     NULL,   -- filename inside uploads/donations/
    status         ENUM('available','requested','completed','cancelled')
                                NOT NULL DEFAULT 'available',
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_donations_donor
        FOREIGN KEY (donor_id)    REFERENCES users(id)      ON DELETE CASCADE,
    CONSTRAINT fk_donations_category
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,

    INDEX idx_donations_donor  (donor_id),
    INDEX idx_donations_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
--  DONATION REQUESTS
--  request-donation.php inserts; donor-requests.php approves or
--  rejects; recipient-requests.php and both dashboards read.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS donation_requests (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    donation_id     INT  NOT NULL,
    recipient_id    INT  NOT NULL,
    quantity        INT  NOT NULL DEFAULT 1,
    beneficiaries   INT      NULL,
    purpose         TEXT     NULL,
    message         TEXT     NULL,
    collection_date DATE     NULL,
    status          ENUM('pending','approved','rejected','completed')
                         NOT NULL DEFAULT 'pending',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_requests_donation
        FOREIGN KEY (donation_id)  REFERENCES donations(id) ON DELETE CASCADE,
    CONSTRAINT fk_requests_recipient
        FOREIGN KEY (recipient_id) REFERENCES users(id)     ON DELETE CASCADE,

    INDEX idx_requests_recipient (recipient_id),
    INDEX idx_requests_donation  (donation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
--  SEED CATEGORIES
-- ------------------------------------------------------------

INSERT IGNORE INTO categories (name) VALUES
    ('Clothing'),
    ('Food & Groceries'),
    ('Books & Stationery'),
    ('Furniture'),
    ('Electronics'),
    ('Medical Supplies'),
    ('Toys & Games'),
    ('Household Items'),
    ('Other');


-- ------------------------------------------------------------
--  UPGRADES / MIGRATIONS
--  Safe to re-run. CREATE TABLE IF NOT EXISTS above skips tables
--  that already exist, so column changes for an existing database
--  go here as idempotent ALTERs. Re-running this whole file brings
--  any database (fresh or existing) fully up to date.
-- ------------------------------------------------------------

--  is_active: lets an admin disable an account without deleting it.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role;


--  security question + answer: used by forgot-password.php so a user can
--  reset their own password by answering the question they picked at signup.
--  The answer is stored as a password_hash(), never in plain text.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS security_question VARCHAR(255) NULL AFTER password;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS security_answer VARCHAR(255) NULL AFTER security_question;


--  activity_log: records admin actions (who did what, when) for accountability.
--  No FK on admin_id so history survives even if that user row changes.
CREATE TABLE IF NOT EXISTS activity_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    admin_id   INT              NULL,
    action     VARCHAR(80)  NOT NULL,   -- e.g. user.role, donation.delete
    detail     VARCHAR(255)     NULL,   -- human-readable description
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_activity_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
