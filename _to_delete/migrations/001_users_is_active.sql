-- ============================================================
--  Migration 001 — add is_active flag to users
--  Lets admins disable an account without deleting it.
--  Run once:  mysql -h 127.0.0.1 -u root donation_db < migrations/001_users_is_active.sql
--  (or paste into phpMyAdmin > donation_db > SQL)
-- ============================================================

USE donation_db;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role;
