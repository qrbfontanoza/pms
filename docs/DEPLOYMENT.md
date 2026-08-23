# PMS Car Rental — Deployment

This document reflects what was directly verified from the source code, SQL files, and the local project environment. Where a requirement is inferred from code syntax or file contents (e.g. a minimum PHP version implied by a language feature in use), the specific evidence is cited. No deployment automation, config files, or code changes were created as part of this document.

---

## Required Software

Verified from source code and file structure:

- **Web server** with PHP support and the ability to serve `.php` files directly (no router/front-controller is used — see [ARCHITECTURE.md](ARCHITECTURE.md)). `.htaccess` files exist in `assets/licenses/` and `uploads/licenses/`, which requires an Apache-compatible server (or another server that honors `.htaccess`-equivalent rules) for those access restrictions to take effect.
- **PHP** with the following extensions in use, confirmed by function calls in the code:
  - `pdo_mysql` — used by [db.php](../db.php) (`new PDO('mysql:...')`)
  - `mysqli` — used by [db_connect.php](../db_connect.php)
  - `exif` — used by `exif_imagetype()` in [register.php](../register.php), [admin_add_vehicle.php](../admin_add_vehicle.php), [admin_edit_vehicle.php](../admin_edit_vehicle.php) for real image-content validation
  - Core PHP (`session`, `json`, `filter`, `hash`/`password_hash` via the bundled password hashing extension) — no separate extension install needed for these on a standard PHP build
- **MySQL or MySQL-compatible server** (MariaDB was not confirmed either way — the dump's comments explicitly say "Server version: 8.4.3," which is a MySQL, not MariaDB, version string).
- **No Composer, no Node.js/npm, no build tool** — confirmed absent from the project tree (no `composer.json`, `package.json`, or `node_modules`). All front-end libraries are CDN-loaded; nothing needs to be installed or compiled for the front end.

---

## PHP Version

- The base SQL dump's header comment states `-- PHP Version: 8.3.30`, indicating the environment the dump was exported from — this is informational, not necessarily a hard requirement of the application code itself.
- **A real minimum version constraint was found in the application code**: [admin-dashboard.php](../admin-dashboard.php) uses a `match` expression —
  ```php
  $badgeClass = match ($row['status']) { ... };
  ```
  `match` was introduced in **PHP 8.0**. Running this codebase on PHP 7.x would cause a fatal parse error on this file.
- No other PHP 8.1+/8.2+/8.3+-exclusive syntax (enums, readonly properties, first-class callable syntax, named constructor promotion) was found elsewhere in the files inspected — the rest of the codebase uses syntax compatible with PHP 7.x.
- **Verified minimum: PHP 8.0.** The specific patch version is not constrained by anything found in the code.

---

## MySQL Version

- The dump header states `-- Server version: 8.4.3`.
- [db_migrations/20251106_add_columns.sql](../db_migrations/20251106_add_columns.sql) contains an explicit comment: *"Some statements use `IF NOT EXISTS` which requires MySQL 8+. If you run an older MySQL version, run the individual statements guarded by information_schema checks instead."* This applies to all four migration scripts, which consistently use `ADD COLUMN IF NOT EXISTS`.
- `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` specifically requires **MySQL 8.0.29 or later** (this exact clause was not supported in earlier MySQL 8.0.x releases). This was not independently re-verified against MySQL's official changelog as part of this inspection, but is stated directly in the migration file's own comment.
- The schema uses `InnoDB` engine, `utf8mb4`/`utf8mb4_general_ci`, `ENUM` types, and `FOR UPDATE` row locking (used in [admin_confirm_booking.php](../admin_confirm_booking.php) and [return_early.php](../return_early.php)) — all standard InnoDB/MySQL features with no unusual version requirement beyond the `IF NOT EXISTS` clause above.
- **Verified minimum: MySQL 8.0** (specifically 8.0.29+ if running the migration scripts as written).

---

## Laragon Setup

Verified from the current local environment and hardcoded connection values:

- The project root observed during this inspection is `C:\laragon\www\pms`, consistent with Laragon's convention of serving sites from `www\<project-name>` at `http://<project-name>.test` or `http://localhost/pms`, depending on Laragon's virtual-host configuration.
- Both [db.php](../db.php) and [db_connect.php](../db_connect.php) hardcode `host=localhost`, `user=root`, `pass=''`, `dbname=pms_connection` — these are Laragon's (and XAMPP's) default MySQL credentials, confirming local development was done against Laragon (or an equivalent local stack sharing the same defaults) with no per-environment configuration layer.
- To run this project under Laragon as currently written: the `pms_connection` database must exist locally (imported from [sql_file/pms_connection.sql](../sql_file/pms_connection.sql)) with the schema-fixing migrations applied on top (see [DATABASE.md](DATABASE.md)), and Laragon's MySQL service must be running with an unauthenticated `root` user (Laragon's default) — no other local configuration is read by the code.
- The Postman collection's `base_url` variable defaults to `http://localhost/pms` ([PMS.postman_collection.json](../PMS.postman_collection.json)), consistent with a Laragon/XAMPP-style local URL rather than a virtual-host `.test` domain.

---

## Configuration

- **No configuration file, `.env`, or config class exists anywhere in the project.** All configuration (DB host/user/pass/dbname) is hardcoded directly inside [db.php](../db.php) and [db_connect.php](../db_connect.php) as PHP variable assignments.
- Because these two files are plain `.php` source (not `.env` or JSON), any change of environment (local → Hostinger, or any other host) requires directly editing both files' hardcoded values — there is no single source of truth for connection settings, and the two files must be kept manually in sync (both already point at the same `pms_connection` database name, but as two independent hardcoded blocks).
- No other runtime configuration (timezones, error-reporting levels, upload size limits beyond the applicaton-level checks in `register.php`/`reserve.php`, session lifetime) is centrally configured — each file that sets something like `ini_set('display_errors', 0)` or `error_reporting(E_ALL)` does so independently (confirmed inconsistently applied — e.g. `reserve.php` and `admin_delete_user.php` explicitly turn error display *on* for debugging, while `login.php`/`register.php` turn it *off*).

---

## Uploads

Verified upload targets and their handling, cross-referenced with [BUGS.md](BUGS.md) and [FEATURES.md](FEATURES.md):

- **`assets/licenses/`** — written by [register.php](../register.php) for the driver's-license image submitted at account registration. Created at runtime via `mkdir($uploadDir, 0755, true)` if it doesn't already exist. Files are named with a random 8-byte hex string plus the detected extension (`.jpg`/`.png`), not the original filename.
- **`uploads/licenses/`** — written by [reserve.php](../reserve.php) for the (separate) driver's-license image optionally submitted at booking time, sent as a base64 data URI rather than a multipart upload. Also created at runtime via `mkdir(..., 0755, true)` if missing. Filenames are derived from the sanitized booking reference.
- **`assets/`** (root, not a subfolder) — target for vehicle thumbnail images uploaded via [admin_add_vehicle.php](../admin_add_vehicle.php) and [admin_edit_vehicle.php](../admin_edit_vehicle.php). Filenames are random 8-byte hex strings plus the detected extension; this directory is not created at runtime (assumed to already exist, which it does, since it also holds the seed vehicle images and static assets like the logo and hero video).
- All upload paths validate real image content via `exif_imagetype()` against an allow-list, not just file extension or client-supplied MIME type (confirmed in all three upload-handling files above).
- Both `assets/licenses/` and `uploads/licenses/` contain an `.htaccess` file that denies all direct HTTP access to their contents (`Require all denied` / `Deny from all`), confirmed by reading both files directly — license images cannot be fetched by URL even though they're stored inside the public web root. **The `assets/` root directory itself (where vehicle thumbnails live) has no such `.htaccess` restriction** — those images are intentionally publicly accessible, consistent with their use as `<img src="assets/...">` on public pages.

---

## Permissions

Verified requirements based on what the code does with the filesystem (no server/OS-level permission configuration exists in the repository itself — this section describes what the running PHP process needs, not a provided setup script):

- The web server process (the user PHP-FPM/Apache's `mod_php` runs as) needs **write access** to:
  - `assets/licenses/` (created if missing, then written to by `move_uploaded_file()`)
  - `uploads/licenses/` (created if missing, then written to by `file_put_contents()`)
  - `assets/` (written to directly by `move_uploaded_file()` for vehicle thumbnails — no subdirectory creation needed since it's expected to pre-exist)
- No `.htaccess`, `chmod`, or permission-setting code exists anywhere in the PHP source — directory creation calls use `mkdir(..., 0755, true)`, which sets the mode at creation time only; this does not guarantee the web server's OS user actually has write access to the *parent* directory needed to create them in the first place, which depends on hosting-environment configuration outside this repository.
- No file permission requirements were found for any other directory (`includes/`, `css/`, `js/`, `db_migrations/`, `sql_file/`) or the PHP files at the repository root beyond standard read access for the web server to serve them.

---

## Environment Variables

- **No environment variables are read anywhere in the codebase.** A search of all PHP files for `getenv()`, `$_ENV`, and `.env`-style loading found none. All values that would typically be environment-specific (DB credentials, base URL, mail sender domain) are either hardcoded directly in PHP files or derived at runtime from `$_SERVER` (e.g. `forgot_password.php` builds its email's `From:` header using `$_SERVER['HTTP_HOST']`, which is request-dependent, not a configured environment variable).
- This means there is currently no mechanism to run this application with different configuration per environment (local vs. staging vs. production) without directly editing source files.

---

## Hostinger Deployment Notes

Evidence of a Hostinger deployment target, verified directly from the migration files:

- [db_migrations/01_fix_schema_mismatches_hostinger.sql](../db_migrations/01_fix_schema_mismatches_hostinger.sql) is explicitly written for Hostinger: its header comment reads *"Run AFTER importing pms_connection.sql, while the correct Hostinger database (e.g. `u822732810_pmsdatabase`) is selected in phpMyAdmin."* This confirms a Hostinger shared-hosting MySQL database with a Hostinger-style prefixed database name was used or planned at some point.
- The same file also contains: *"Reset admin password to a known value (same as the local XAMPP setup): Admin@12345"* followed by an `UPDATE admins SET password = '$2y$10$Ok3e8dwPrh6OrWnsCw0gcuEsUi3y9PrfTG8tYeOFpX8LHXQ44OAdm' WHERE email = 'admin@pms.local';` — **this file discloses the admin account's plaintext password directly in a SQL comment** (see Sensitive Files below).
- Because `db.php`/`db_connect.php` hardcode `localhost`/`root`/empty-password, deploying to Hostinger (or any host) as currently written requires manually editing both files' `$host`, `$user`, `$pass`, and `$db`/`$dbname` values to match the Hostinger-provided MySQL credentials before upload — no separate Hostinger-specific config file exists to do this automatically.
- No `.htaccess` rewrite rules, no deployment script, and no CI/CD configuration were found anywhere in the repository — deployment to Hostinger, based on what's present, would be a manual file upload (e.g. via FTP/File Manager) plus a manual phpMyAdmin import of the schema and manual execution of the migration scripts.
- Hostinger's shared PHP hosting typically lets the account holder select a PHP version per domain; given the PHP 8.0 minimum established above, that selection would need to be set accordingly — this is a hosting-panel setting, not something enforced by any file in this repository.

---

## Sensitive Files

Files verified to contain credentials, secrets, or otherwise sensitive data that would need deliberate handling before any public distribution (e.g. pushing this repository to a public Git host) or production deployment:

- **[db.php](../db.php) and [db_connect.php](../db_connect.php)** — contain DB host/user/password in plaintext PHP. Currently local defaults (`root`/empty password), but any production edit to these files would put live production DB credentials directly into version-controlled source.
- **[db_migrations/01_fix_schema_mismatches_hostinger.sql](../db_migrations/01_fix_schema_mismatches_hostinger.sql)** — contains the admin account's plaintext password (`Admin@12345`) in a SQL comment, directly next to the bcrypt hash it resets the account to. Anyone with read access to this file has the admin login credential.
- **[sql_file/pms_connection.sql](../sql_file/pms_connection.sql)** — a full data dump, not just schema. It includes real bcrypt password hashes for the seeded `admins` row and both seeded `users` rows, plus real-looking contact numbers (`0999988777`) and a real-looking student email address (`qgksantos@tip.edu.ph`) in the seed `users` data. Bcrypt hashes are not trivially reversible, but this is still account and personal data embedded directly in a SQL file.
- **`assets/licenses/` and `uploads/licenses/`** — intended to hold uploaded driver's-license images (personal identity documents). Both are protected by `.htaccess` `Deny from all`, confirmed by direct inspection, which prevents direct URL access — but the files remain plaintext on disk with no encryption, and their protection depends entirely on the web server correctly honoring `.htaccess` (not guaranteed on all hosting configurations, e.g. Nginx without an equivalent rule, or Apache with `AllowOverride None`).
- **[PMS.postman_collection.json](../PMS.postman_collection.json)** — does not itself contain secrets (its `base_url` variable defaults to `http://localhost/pms`, and its sample request bodies use placeholder test data like `testuser@example.com`), but would need its `base_url` updated per environment if used against a deployed instance.

---

## Deployment Checklist

A checklist of what was verified as required or currently unaddressed, based on this inspection. This is a list of facts to confirm/handle, not a set of changes already made:

- [ ] Target server has PHP ≥ 8.0 (required by the `match` expression in `admin-dashboard.php`), with `pdo_mysql`, `mysqli`, and `exif` extensions enabled.
- [ ] Target server has MySQL ≥ 8.0 (≥ 8.0.29 if running the migration scripts' `ADD COLUMN IF NOT EXISTS` statements as written).
- [ ] `pms_connection` database created and [sql_file/pms_connection.sql](../sql_file/pms_connection.sql) imported.
- [ ] All applicable migration scripts from [db_migrations/](../db_migrations/) run on top of the imported dump (the `_hostinger` variant of migration 01 if deploying to Hostinger), per [DATABASE.md](DATABASE.md)'s Current Schema section — without this, `login.php`/`register.php` will fail against the raw dump's `password_hash` column name mismatch.
- [ ] `db.php` and `db_connect.php` both manually updated with the target environment's real DB host/user/password/database name (no automated mechanism exists for this).
- [ ] Web server process has write permission to `assets/`, `assets/licenses/`, and `uploads/licenses/` (the latter two are created automatically at runtime if the parent directory is writable, but will fail silently/error if not).
- [ ] Web server honors `.htaccess` (`AllowOverride` enabled for Apache, or an equivalent rule configured for other servers) so that `assets/licenses/.htaccess` and `uploads/licenses/.htaccess` actually block direct access to uploaded license images.
- [ ] Admin account password changed from the disclosed value if the Hostinger migration script's `UPDATE admins SET password = ...` (which sets it to the publicly-visible `Admin@12345`) was ever run against a real deployment.
- [ ] A decision made about `db.php`/`db_connect.php` and `db_migrations/*_hostinger.sql` regarding version control exposure, given they contain (or, for the DB files, would need to contain) live credentials and a disclosed admin password respectively.
- [ ] Outbound mail capability confirmed on the target server if `forgot_password.php`'s use of PHP's native `mail()` is expected to work — no SMTP configuration exists in the codebase, so deliverability depends entirely on the host's local mail transport being functional.
- [ ] PHP version selected in the hosting control panel (relevant specifically for Hostinger's per-domain PHP version setting) matches the ≥ 8.0 requirement above.

---

*This document reflects direct inspection of the source code, SQL files, and local environment. No deployment configuration, environment files, or code changes were created as part of producing this document.*
