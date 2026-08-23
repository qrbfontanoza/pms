-- Run AFTER importing pms_connection.sql (the real phpMyAdmin dump).
-- Fixes two mismatches between that dump and what the PHP code actually queries.

USE pms_connection;

-- login.php / register.php select/insert a `password` column, but the dump has `password_hash`.
ALTER TABLE users CHANGE COLUMN password_hash password VARCHAR(255) NOT NULL;

-- return_early.php inserts a `payment_status` value; the dump's transactions table lacks it.
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS payment_status VARCHAR(50) DEFAULT NULL;
