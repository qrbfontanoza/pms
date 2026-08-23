-- Run AFTER importing pms_connection.sql, while the correct Hostinger database
-- (e.g. u822732810_pmsdatabase) is selected in phpMyAdmin.
-- Fixes two mismatches between that dump and what the PHP code actually queries.

-- login.php / register.php select/insert a `password` column, but the dump has `password_hash`.
ALTER TABLE users CHANGE COLUMN password_hash password VARCHAR(255) NOT NULL;

-- return_early.php inserts a `payment_status` value; the dump's transactions table lacks it.
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS payment_status VARCHAR(50) DEFAULT NULL;

-- Reset admin password to a known value (same as the local XAMPP setup): Admin@12345
-- Change this password after logging in.
UPDATE admins SET password = '$2y$10$Ok3e8dwPrh6OrWnsCw0gcuEsUi3y9PrfTG8tYeOFpX8LHXQ44OAdm' WHERE email = 'admin@pms.local';
