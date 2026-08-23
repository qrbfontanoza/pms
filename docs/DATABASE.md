# PMS Car Rental — Database

This document is based on a direct inspection of every SQL file ([sql_file/pms_connection.sql](../sql_file/pms_connection.sql) and all four scripts in [db_migrations/](../db_migrations/)) and every SQL query embedded in the 31 PHP files that touch the database. No SQL was modified as part of this inspection.

---

## Tables

Six tables are defined in the base dump ([sql_file/pms_connection.sql](../sql_file/pms_connection.sql)):

| Table | Purpose (verified from schema + queries) |
|---|---|
| `admins` | Admin login accounts, checked by `admin-login.php` |
| `users` | Customer accounts, checked by `login.php`/`register.php` |
| `vehicles` | Rental inventory, read by `vehicles.php`, `api_vehicles.php`, managed by `admin_vehicles.php` |
| `bookings` | Rental reservations, the central table joined by nearly every other feature |
| `vouchers` | Discount codes applied at booking time |
| `transactions` | Payment/return records tied to a booking |
| `messages` | Contact-form submissions from `about.php` / `save_message.php` |

No other tables are referenced by any query in the PHP files inspected.

---

## Relationships

Verified from the `CREATE TABLE`/`ALTER TABLE ... ADD CONSTRAINT` statements in the dump:

- `bookings.user_id` → `users.id` (many bookings per user)
- `bookings.vehicle_id` → `vehicles.id` (many bookings per vehicle)
- `bookings.voucher_id` → `vouchers.id` (optional, nullable — a booking may have zero or one voucher)
- `transactions.booking_id` → `bookings.id` (a booking can have one or more transaction rows — one from confirmation, potentially another from early return)
- `vouchers.used_by` → `users.id` (optional, nullable — the last user who used the voucher; not a full usage log, see Missing Constraints)
- `admins` and `messages` have no foreign keys — they are standalone tables.

There is no join table for many-to-many relationships anywhere in the schema; every relationship observed is one-to-many via a nullable or required foreign key column.

---

## Primary Keys

All six tables use a single auto-incrementing integer primary key named `id`, confirmed by the `ADD PRIMARY KEY (id)` statements and matching `MODIFY id int NOT NULL AUTO_INCREMENT` statements in the dump:

- `admins.id`
- `bookings.id`
- `messages.id`
- `transactions.id`
- `users.id`
- `vehicles.id`
- `vouchers.id`

No composite primary keys exist anywhere in the schema.

---

## Foreign Keys

Verified from the `Constraints for dumped tables` section of [sql_file/pms_connection.sql](../sql_file/pms_connection.sql):

| Constraint | Column | References | On Delete |
|---|---|---|---|
| `bookings_user_fk` | `bookings.user_id` | `users.id` | CASCADE |
| `bookings_vehicle_fk` | `bookings.vehicle_id` | `vehicles.id` | CASCADE |
| `bookings_voucher_fk` | `bookings.voucher_id` | `vouchers.id` | SET NULL |
| `transactions_booking_fk` | `transactions.booking_id` | `bookings.id` | CASCADE |
| `vouchers_ibfk_1` | `vouchers.used_by` | `users.id` | SET NULL |

Practical effect of the CASCADE deletes, as verified against the code that performs deletes: [admin_delete_user.php](../admin_delete_user.php) deletes a row from `users`, which — per `bookings_user_fk ON DELETE CASCADE` — would also delete every booking that user ever made, which in turn — per `transactions_booking_fk ON DELETE CASCADE` — would delete every transaction tied to those bookings. Neither `admin_delete_user.php` nor the UI warns about this cascade beyond a generic "This action cannot be undone" confirm dialog. Similarly, `admin_delete_vehicle.php` deletes a row from `vehicles`, which cascades to delete every booking (and, transitively, every transaction) ever made for that vehicle.

`delete_booking.php` deletes directly from `bookings`, which cascades to `transactions` for that booking only.

---

## Indexes

Verified from the `Indexes for dumped tables` section of the dump:

| Table | Indexes |
|---|---|
| `admins` | PRIMARY (`id`) |
| `bookings` | PRIMARY (`id`); KEY `vehicle_id`; KEY `status`; KEY `rental_date`; KEY `return_date`; KEY `bookings_user_fk` (`user_id`); KEY `bookings_voucher_fk` (`voucher_id`) |
| `messages` | PRIMARY (`id`) |
| `transactions` | PRIMARY (`id`); UNIQUE `transaction_ref`; KEY `booking_id` |
| `users` | PRIMARY (`id`); UNIQUE `email` |
| `vehicles` | PRIMARY (`id`) only |
| `vouchers` | PRIMARY (`id`); UNIQUE `code`; KEY `used_by` |

Notable from direct comparison against the queries that filter on these columns: `bookings` is indexed on `status`, `rental_date`, and `return_date` individually, and the availability-check queries in `vehicles.php`, `api_vehicles.php`, and `reserve.php` filter on `vehicle_id`, `status`, `rental_date`, and `return_date` together — there is no composite index covering that combination. `vehicles` has no index beyond its primary key, despite `vehicles.php` and `api_vehicles.php` filtering on `is_active` and `category` on every page load.

---

## Current Schema

The schema as defined in [sql_file/pms_connection.sql](../sql_file/pms_connection.sql), **before** any migration script is applied:

- `admins`: `id`, `name`, `email`, `password`
- `users`: `id`, `name`, `email`, `password_hash`, `role`, `created_at`
- `vehicles`: `id`, `title`, `category`, `seats`, `fuel`, `transmission`, `price_per_day`, `units_total`, `is_active`, `thumbnail`, `created_at`, `slug`
- `bookings`: `id`, `user_id`, `vehicle_id`, `booking_ref`, `rental_date`, `return_date`, `pickup_time`, `dropoff_time`, `days`, `rate`, `discount`, `total_amount`, `contact_number`, `status` (enum: `completed`,`pending`,`confirmed`,`cancelled`, default `pending`), `created_at`, `voucher_id`, `age` (default 18), `license_file`, `paid`
- `vouchers`: `id`, `code`, `discount_amount`, `discount_pct`, `is_active`, `single_use`, `usage_count`, `usage_limit`, `used_at`, `used_by`, `created_at`
- `transactions`: `id`, `booking_id`, `transaction_ref`, `amount`, `paid_at`
- `messages`: `id`, `name`, `email`, `message`, `created_at`

This dump's `users` table uses `password_hash`, but no PHP file queries or inserts that column name — every auth file (`login.php`, `register.php`, `reset_password.php`, `change_password.php`) reads/writes `users.password` instead. The schema actually required by the application code is the dump **plus** the migrations below applied on top:

- [db_migrations/01_fix_schema_mismatches.sql](../db_migrations/01_fix_schema_mismatches.sql) (or its `_hostinger` variant): renames `users.password_hash` → `users.password`; adds `transactions.payment_status VARCHAR(50)`.
- [db_migrations/02_add_password_reset_columns.sql](../db_migrations/02_add_password_reset_columns.sql): adds `users.reset_code VARCHAR(6)` and `users.reset_code_expires DATETIME`.
- [db_migrations/20251106_add_columns.sql](../db_migrations/20251106_add_columns.sql): adds/ensures `vehicles.units_total`, `vehicles.thumbnail`, `vehicles.fuel`, `vehicles.price_per_day`, `vehicles.is_active`; adds `bookings.voucher_id`, `bookings.age`, `bookings.license_file`; adds `vouchers.is_active`; adds `transactions.payment_status`, `transactions.created_at`; adds `users.password`.

Columns referenced by PHP but **not present in the base dump's `CREATE TABLE` statement**, confirmed only addable via the migrations above or via `register.php`'s own runtime `ALTER TABLE`:
- `users.license_path` — written by `register.php`; not in the dump, not in any migration script. `register.php` contains its own inline fallback: on an "unknown column" PDO error it runs `ALTER TABLE users ADD COLUMN IF NOT EXISTS license_path VARCHAR(255) DEFAULT NULL` itself, then retries the insert.
- `bookings.actual_return_date`, `bookings.early_return` — referenced conditionally by `return_early.php`, which first queries `information_schema.COLUMNS` to check whether they exist before using them, and skips setting them if they don't.

Because of this, **the effective live schema depends entirely on which of these migration scripts (and which runtime auto-patches) have actually executed against a given database** — this cannot be determined from the SQL files alone, only from the live database itself, which was not accessible during this inspection.

---

## Missing Constraints

Verified by cross-referencing every `ALTER TABLE ... ADD CONSTRAINT` / `ADD KEY` in the dump against every column actually used in application logic. No SQL was changed; this section only reports what is absent:

- **No unique constraint on `bookings.booking_ref`** or `bookings.license_file`, despite `booking_ref` being generated as `'B' . time() . rand(1000,9999)` in `reserve.php` and used as a public lookup key in `receipt.php` (`WHERE booking_ref = ?`). A collision (however unlikely) would not be rejected by the database.
- **No unique constraint on `transactions` per booking** beyond the unique `transaction_ref` — `transactions.booking_id` has an index but not a uniqueness constraint, and the schema/code allow multiple transaction rows per booking (confirmed: `admin_confirm_booking.php` inserts one on confirmation, `return_early.php` inserts a second one with `payment_status='returned'` on the same `booking_id`).
- **No CHECK constraint on `bookings.status`** beyond the `ENUM` type itself — the enum values (`completed`,`pending`,`confirmed`,`cancelled`) are enforced by MySQL, but no constraint enforces valid state *transitions* (e.g. nothing in the schema prevents a direct `pending` → `completed` update bypassing `confirmed`).
- **No foreign key from `bookings` to `transactions`** or vice versa beyond the one already listed — there is no constraint preventing a `transactions` row from referencing a `booking_id` that doesn't correspond to that transaction's actual payment amount; `amount` is not validated against `bookings.total_amount` at the database level.
- **No NOT NULL / default on `transactions.amount`** in the base dump (`amount` is `decimal(10,2) DEFAULT NULL`) — `return_early.php`'s transaction insert only supplies `booking_id`, `transaction_ref`, `payment_status`, leaving `amount` NULL for return-type transaction rows.
- **`vouchers.used_by`/`used_at` are single-value columns, not a usage log** — there is no constraint or separate table preventing overwrite; each new use of a multi-use voucher (`usage_limit > 1`) overwrites `used_by`/`used_at` with the most recent user, discarding who used it previously. This is a schema-shape limitation, not an enforced constraint gap, but is noted here because no constraint exists to signal it.
- **No index on `vehicles.is_active` or `vehicles.category`**, despite both being filtered on in `vehicles.php` and `api_vehicles.php` on every request (see Indexes above).
- **No composite index on `bookings(vehicle_id, status, rental_date, return_date)`**, despite this exact combination being queried for availability checks in `vehicles.php`, `api_vehicles.php`, and `reserve.php`.
- **`users.role` has no CHECK/ENUM constraint** — it's a plain `varchar(50) DEFAULT 'user'`; nothing at the database level restricts it to `user`/`admin`/other specific values (and in practice, admin accounts live in the separate `admins` table, not via `users.role`).
- **`vehicles.slug` is nullable with no uniqueness constraint** and is not populated or read by any query found in the PHP files inspected — it appears unused by the current application code.

---

## Data Flow

Traced by following the actual query sequence across files for the two core flows:

**Booking creation → confirmation → payment record:**
1. `reserve_preview.php` — `SELECT price_per_day FROM vehicles WHERE id = ? AND is_active = 1`, optional `SELECT * FROM vouchers WHERE code = ? AND is_active = 1`. No writes.
2. `reserve.php` — re-runs the same vehicle/voucher lookups, then `SELECT COUNT(*) FROM bookings WHERE vehicle_id = ? AND status IN ('pending','confirmed','completed') AND NOT (return_date < ? OR rental_date > ?)` to check overlap availability, then `INSERT INTO bookings (...)` with `status='pending'` inside a transaction. If a voucher was used, a separate `UPDATE vouchers SET used_by=?, used_at=NOW(), usage_count=? WHERE id=?` runs immediately after the booking commit (not inside the same transaction). No row is written to `transactions` and `vehicles.units_total` is not decremented at this stage.
3. `admin_confirm_booking.php` — `SELECT ... FROM bookings WHERE id=? FOR UPDATE`, `SELECT units_total FROM vehicles WHERE id=? FOR UPDATE`, then `UPDATE vehicles SET units_total = units_total - 1 ...`, `UPDATE bookings SET status='confirmed'`, and `INSERT INTO transactions (booking_id, transaction_ref, amount)` — all inside one mysqli transaction with row locks.
4. Terminal states from here: `cancel_booking.php` (`UPDATE bookings SET status='cancelled'`, and if the booking had been `confirmed`, `UPDATE vehicles SET units_total = units_total + 1`) or `return_early.php` (`UPDATE bookings SET status='completed'`, `UPDATE vehicles SET units_total = units_total + 1`, `INSERT INTO transactions (booking_id, transaction_ref, payment_status)`).

**Vehicle availability display:**
`vehicles.php` and `api_vehicles.php` both run `SELECT * FROM vehicles WHERE is_active = 1 ...` followed by, per row, `SELECT COUNT(*) FROM bookings WHERE vehicle_id = ? AND status IN ('pending','confirmed','completed')` — availability is `units_total - bookedCount`, computed fresh on every page load rather than read from a stored value. Note this per-vehicle count query (used for display) does **not** filter by date range, while the equivalent check inside `reserve.php` at booking time **does** filter by `rental_date`/`return_date` overlap — meaning the number shown on the vehicles listing page is a stricter (lower) estimate of availability than what `reserve.php` will actually allow at booking time for a specific date range.

**Contact messages:** `about.php`'s form → `save_message.php` → `INSERT INTO messages (name, email, message)`. Read-only display in `admin-dashboard.php` via `SELECT * FROM messages ORDER BY created_at DESC`. No status/read tracking column exists on `messages`.

---

## Important Queries

The queries most central to core functionality, quoted as found in the source (not modified):

**Availability check at booking time** ([reserve.php](../reserve.php)):
```sql
SELECT COUNT(*) FROM bookings
WHERE vehicle_id = ? AND status IN ('pending','confirmed','completed')
AND NOT (return_date < ? OR rental_date > ?)
```

**Availability count for display** ([vehicles.php](../vehicles.php), [api_vehicles.php](../api_vehicles.php)):
```sql
SELECT COUNT(*) FROM bookings
WHERE vehicle_id = ? AND status IN ('pending','confirmed','completed')
```

**Booking confirmation with row locking** ([admin_confirm_booking.php](../admin_confirm_booking.php)):
```sql
SELECT id, vehicle_id, total_amount, contact_number, status FROM bookings WHERE id = ? FOR UPDATE
SELECT units_total FROM vehicles WHERE id = ? FOR UPDATE
UPDATE vehicles SET units_total = GREATEST(0, units_total - 1),
  is_active = IF(GREATEST(0, units_total - 1) = 0, 0, is_active) WHERE id = ?
UPDATE bookings SET status = 'confirmed' WHERE id = ? LIMIT 1
INSERT INTO transactions (booking_id, transaction_ref, amount) VALUES (?, ?, ?)
```

**Voucher lookup and discount calculation** ([reserve.php](../reserve.php), [reserve_preview.php](../reserve_preview.php)):
```sql
SELECT * FROM vouchers WHERE code = ? AND is_active = 1
```
Discount applied in PHP, not SQL: `discount_pct` is applied as a percentage of subtotal if set, otherwise `discount_amount` is applied as a flat value.

**Voucher usage increment** ([reserve.php](../reserve.php)):
```sql
UPDATE vouchers SET used_by = ?, used_at = NOW(), usage_count = ? WHERE id = ?
-- followed conditionally by:
UPDATE vouchers SET is_active = 0 WHERE id = ?   -- when usage_count reaches usage_limit
```

**User's active vs. completed bookings** ([transactions.php](../transactions.php)):
```sql
SELECT b.id, b.status, b.booking_ref, b.rental_date, b.return_date, b.pickup_time, b.dropoff_time,
       b.days, b.rate, b.discount, b.total_amount, b.contact_number,
       v.title AS vehicle_title, t.transaction_ref
FROM bookings b
LEFT JOIN vehicles v ON b.vehicle_id = v.id
LEFT JOIN transactions t ON t.booking_id = b.id
WHERE b.user_id = ? AND b.status != 'completed'
ORDER BY b.rental_date DESC
```
(Split into "active" vs. "completed" buckets in PHP by comparing `return_date` to today's date — note this query already excludes `status = 'completed'` bookings, so the "Completed Rentals" section on this page only ever shows bookings whose `return_date` has passed while `status` is still `pending`/`confirmed`/`cancelled`, not bookings actually marked `completed`.)

**Return-early completion** ([return_early.php](../return_early.php)):
```sql
SELECT b.*, v.id as vehicle_id, v.units_total, v.title as vehicle_name, ...
FROM bookings b JOIN vehicles v ON b.vehicle_id = v.id
WHERE b.id = ? AND b.user_id = ? FOR UPDATE
-- then conditionally, based on an information_schema.COLUMNS probe:
UPDATE bookings SET status = 'completed', actual_return_date = CURRENT_TIMESTAMP, early_return = ?
  WHERE id = ? AND status = 'confirmed' LIMIT 1
UPDATE vehicles SET units_total = units_total + 1,
  is_active = CASE WHEN units_total + 1 > 0 THEN 1 ELSE is_active END WHERE id = ? LIMIT 1
INSERT INTO transactions (booking_id, transaction_ref, payment_status) VALUES (?, ?, ?)
```

**Admin dashboard metrics** ([admin-dashboard.php](../admin-dashboard.php)):
```sql
SELECT COUNT(*) AS total FROM vehicles
SELECT COUNT(*) AS total FROM users
SELECT COUNT(*) AS total FROM bookings WHERE status='confirmed'
```

---

*This document reflects direct inspection of every SQL file and every embedded query in the codebase. No SQL was modified.*
