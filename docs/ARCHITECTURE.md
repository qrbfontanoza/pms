# PMS Car Rental — Architecture

This document describes only what was directly verified by reading the source code. No architectural changes are proposed here.

---

## Folder Structure

```
(root)        — all PHP pages + JSON endpoints (35 files, flat, no subfolders)
includes/     — header.php, footer.php (used only by admin pages)
assets/       — vehicle images, logo, hero video, and assets/licenses/ (license upload target used by register.php)
uploads/      — uploads/licenses/ (a second license upload target, used by reserve.php)
css/          — styles.css (single stylesheet, 904 lines)
js/           — app.js, admin.js, booking-validation.js, voucher-manager.js, confirm.js, theme.js
                (printer.js was listed here until 2026-08-21, when UI Implementation Plan
                 Phase 11 deleted it as confirmed dead — unreferenced by any PHP entry point)
sql_file/     — pms_connection.sql (phpMyAdmin dump: schema + seed data)
db_migrations/— 4 standalone SQL patch scripts, run manually (no migration tool/framework)
docs/         — this file plus DATABASE.md, API.md, FEATURES.md, PROJECT_AUDIT.md, BUGS.md,
                DEPLOYMENT.md, DESIGN_SYSTEM.md
references/   — inspiration/, screenshots/, ui/ (design reference material)
skills/       — Claude Code skill definitions (not part of the running application)
```

The PHP files at the repository root have no subfolders separating concerns (no `controllers/`, `models/`, `views/`). Every file is both an entry point and the implementation of that endpoint.

---

## Execution Flow

There is no bootstrap file, front controller, or router. Each `.php` file at the repository root is executed directly by the web server when its URL is requested. A typical file's execution order, verified across every page read (e.g. [vehicles.php](../vehicles.php), [reserve.php](../reserve.php), [admin_vehicles.php](../admin_vehicles.php)):

1. `session_start()`
2. `require`/`include` one or both DB connection files ([db.php](../db.php) and/or [db_connect.php](../db_connect.php))
3. Inline auth guard (`if (!isset($_SESSION[...])) { ... exit; }`) where applicable
4. Inline input reading (`$_POST`, `$_GET`, or `json_decode(file_get_contents('php://input'))`)
5. Inline SQL query/queries against the connection opened in step 2
6. Either `echo json_encode(...)` (for JSON endpoints) or direct HTML output mixed with PHP (for page endpoints)

There is no shared function library for repeating this pattern — each file re-implements steps 1–4 independently.

---

## Request Lifecycle

Verified for the two dominant request types:

**Page request (browser navigation), e.g. GET `vehicles.php`:**
`session_start()` → include `db.php`/`db_connect.php` → inline SQL to fetch data (e.g. active vehicles, per-vehicle booked counts) → PHP builds an HTML string via `echo` mixed with heredoc/concatenation → the same script also emits the full HTML document (head, nav, body, footer, modals) → response is a complete HTML page.

**AJAX/JSON request (jQuery `fetch`/`$.ajax` from the browser), e.g. POST `reserve.php`:**
`session_start()` → `header('Content-Type: application/json')` → auth/session check → `json_decode(file_get_contents('php://input'))` to read the JSON body → field validation → one or more SQL queries (often inside a `beginTransaction()`/`commit()`/`rollBack()` block) → `echo json_encode([...])` with a matching `http_response_code()`.

No middleware layer, no centralized error handler, and no consistent response envelope exist — each endpoint decides its own JSON shape (`{success, ...}` in some files, `{error, ...}` in others, sometimes both patterns in the same file, as seen in [reserve.php](../reserve.php)).

---

## Shared Includes

Only two shared include files exist, and both are used exclusively by the **admin** side:

- [includes/header.php](../includes/header.php) — checks `$_SESSION['admin_id']`, redirects to `login.php` if absent, then emits `<head>` (Bootstrap, Font Awesome, DataTables CSS, `css/styles.css`) and opens `<body>`/the main-content wrapper `<div>`.
- [includes/footer.php](../includes/footer.php) — closes the wrapper `<div>`s and emits jQuery, Bootstrap JS, and DataTables JS `<script>` tags.

Confirmed usage: [admin_users.php](../admin_users.php), [admin_vehicles.php](../admin_vehicles.php), and [view-all-data.php](../view-all-data.php) `include` both files around their page-specific content. [admin-dashboard.php](../admin-dashboard.php), [admin-login.php](../admin-login.php), and [admin_vouchers.php](../admin_vouchers.php) do **not** use these includes — they build their own full `<html>` document inline instead.

**Customer-facing pages have no shared include at all.** The navbar, footer, and login/signup modal markup are duplicated verbatim (confirmed identical block-for-block) inside [index.php](../index.php), [vehicles.php](../vehicles.php), [transactions.php](../transactions.php), [faq.php](../faq.php), and [about.php](../about.php).

---

## PHP Execution Path

Verified DB access pattern per file: two independent connection files exist and are not interchangeable at the API level.

- [db.php](../db.php) — opens a `PDO` connection (`mysql:host=localhost;dbname=pms_connection`), `ERRMODE_EXCEPTION`, `FETCH_ASSOC` default, emulated prepares disabled. On failure, echoes an error message and calls `exit`.
- [db_connect.php](../db_connect.php) — opens a `mysqli` connection to the same host/database. On failure, calls `die()` with the connect error.

Files verified to use `db.php`/PDO: `login.php`, `register.php`, `me.php`, `reserve.php`, `reserve_preview.php`, `api_vehicles.php`, `api_my_bookings.php`, `apply_voucher.php` (also uses `db_connect.php`), `cancel_booking.php`, `return_early.php`, `receipt.php`, `save_message.php`, `get_vouchers.php` (via `db_connect.php` only), `forgot_password.php`, `reset_password.php`, `change_password.php`, `transactions.php`, `vehicles.php` (also uses `db_connect.php`).

Files verified to use `db_connect.php`/mysqli: `admin-login.php`, `admin-dashboard.php`, `admin_add_vehicle.php`, `admin_edit_vehicle.php`, `admin_delete_vehicle.php`, `admin_delete_user.php`, `admin_update_user.php`, `admin_users.php`, `admin_vehicles.php`, `admin_vouchers.php`, `admin_confirm_booking.php`, `delete_booking.php`, `update_booking_time.php`, `view-all-data.php`.

Files verified to open **both connections in the same request**: [vehicles.php](../vehicles.php), [apply_voucher.php](../apply_voucher.php), [get_vouchers.php](../get_vouchers.php) — each `include`s `db_connect.php` and (for `vehicles.php`) also `require`s `db.php`, resulting in two separate MySQL connections opened per request in those files.

Error handling style follows the connection type: PDO files wrap DB calls in `try { } catch (PDOException $e)` and return JSON error bodies; mysqli files check `$stmt->execute()` return values or wrap logic in `try/catch (Exception $e)` blocks with manually thrown exceptions.

---

## Routing

There is no router or URL-rewriting layer (no `.htaccess` rewrite rules were found governing routing). Each page is reached by its literal filename as a URL path (e.g. `/vehicles.php`, `/admin-dashboard.php`). Navigation between pages is done via plain `<a href="...">` links and `header('Location: ...')` redirects — for example, `admin-login.php` redirects to `admin-dashboard.php` on success, and every `admin_*` action script (`admin_add_vehicle.php`, `admin_edit_vehicle.php`, `admin_delete_vehicle.php`) redirects back to `admin_vehicles.php` or `admin-dashboard.php` with a query-string flag (`?success=1`, `?error=...`, `?updated=1`, `?deleted=1`) that the destination page reads via `$_GET` to show an alert.

Query parameters are also used directly for record lookup, e.g. `receipt.php?id=...` or `receipt.php?ref=...`, and `admin_delete_vehicle.php?id=...`.

---

## Session Lifecycle

Two independent, non-intersecting session namespaces were verified, both using PHP's native `$_SESSION` (file-based sessions; no custom session handler or session store was found):

- **Customer session:** key `$_SESSION['user']`, an associative array of `id`, `name`, `email` (and `role` when read back by `me.php`, defaulting to `'user'` if absent). Set by [login.php](../login.php) on successful `password_verify()`. Read by every customer-facing endpoint that requires login (`reserve.php`, `cancel_booking.php`, `return_early.php`, `api_my_bookings.php`, `save_message.php`, `change_password.php`, `receipt.php`, `transactions.php`, `about.php`). Cleared by [logout.php](../logout.php) via `session_unset()` + `session_destroy()`.
- **Admin session:** key `$_SESSION['admin_id']` (plus `$_SESSION['admin_name']`). Set by [admin-login.php](../admin-login.php) on successful lookup against the `admins` table. Read independently by every `admin_*.php` file and by `includes/header.php`. There is no shared logout endpoint distinct from the customer one — the admin sidebar's logout link points to `logout.php`, the same script used for customer logout, which calls `session_unset()`/`session_destroy()` (clearing both session namespaces since they live in the same PHP session).

Every protected endpoint independently calls `session_start()` and checks its respective session key inline; no shared authentication guard function or middleware exists. Session checks are duplicated verbatim across dozens of files.

---

## Database Interaction

- Single database: `pms_connection`, accessed via either PDO ([db.php](../db.php)) or mysqli ([db_connect.php](../db_connect.php)), both hardcoded to `localhost` / `root` / empty password.
- All observed queries use parameterized statements — PDO `prepare()`/`execute([...])` with `?` placeholders, or mysqli `prepare()`/`bind_param()`. No raw string-concatenated SQL containing user input was found in the files read.
- Transactions are used selectively: PDO's `beginTransaction()`/`commit()`/`rollBack()` in [reserve.php](../reserve.php), [cancel_booking.php](../cancel_booking.php), [return_early.php](../return_early.php); mysqli's `begin_transaction()`/`commit()`/`rollback()` in [admin_confirm_booking.php](../admin_confirm_booking.php) and [apply_voucher.php](../apply_voucher.php)'s voucher-completion branch.
- Row locking (`SELECT ... FOR UPDATE`) is used in [admin_confirm_booking.php](../admin_confirm_booking.php) (locks the booking and vehicle rows before decrementing inventory) and [return_early.php](../return_early.php) (locks the booking row before updating status).
- Availability is computed on read, not stored: both [vehicles.php](../vehicles.php) and [api_vehicles.php](../api_vehicles.php) compute `available_units = units_total - COUNT(bookings WHERE status IN ('pending','confirmed','completed'))` per vehicle at request time rather than maintaining a running counter.
- Some files defensively query `information_schema.COLUMNS` at runtime to check whether optional columns exist before using them ([return_early.php](../return_early.php)), and [register.php](../register.php) will `ALTER TABLE` the `users` table on the fly if `license_path` is missing, then retry the insert.

---

## File Uploads

Two independent upload paths were verified, targeting two different directories:

1. **License upload during registration** — [register.php](../register.php): accepts a `multipart/form-data` file field `license`, validates size (≤3MB) and real image type via `exif_imagetype()` (JPEG/PNG only), generates a random filename with `bin2hex(random_bytes(8))`, and writes it to `assets/licenses/` (created with `mkdir(..., 0755, true)` if missing). The relative path is stored in `users.license_path`.
2. **License upload during booking** — [reserve.php](../reserve.php): accepts a base64 data-URI string (`license_base64` + `license_name`) inside the JSON booking payload (not a multipart upload), validates the data-URI prefix via regex (`data:image/(jpeg|png|jpg);base64,`), decodes it, builds a filename from the sanitized booking reference, and writes it to `uploads/licenses/`. The relative path is stored in `bookings.license_file`.

**Vehicle image upload (admin)** — [admin_add_vehicle.php](../admin_add_vehicle.php) and [admin_edit_vehicle.php](../admin_edit_vehicle.php): accepts a `multipart/form-data` file field (`thumbnail` on add, `image` on edit), validates real image content via `exif_imagetype()` against an allow-list (JPEG/PNG/GIF/WEBP), generates a random filename via `bin2hex(random_bytes(8))` plus the detected extension, and writes it to `assets/` directly (not a subfolder). On a failed DB insert/update after a successful upload, `admin_add_vehicle.php` calls `@unlink($targetFilePath)` to remove the orphaned file.

Both `assets/licenses/.htaccess` and `uploads/licenses/.htaccess` exist as empty/placeholder files in those directories (contents not evaluated as part of this audit).

---

## Admin Architecture

- Entry: [admin-login.php](../admin-login.php) → sets `$_SESSION['admin_id']`/`$_SESSION['admin_name']` → redirects to [admin-dashboard.php](../admin-dashboard.php).
- Every admin page/action script independently re-checks `$_SESSION['admin_id']` at the top (via `includes/header.php` for pages that use it, or an inline check for pages/scripts that don't).
- Page-rendering admin files ([admin-dashboard.php](../admin-dashboard.php), [admin_vehicles.php](../admin_vehicles.php), [admin_users.php](../admin_users.php), [admin_vouchers.php](../admin_vouchers.php), [view-all-data.php](../view-all-data.php)) run their SQL queries inline at the top of the file, then interpolate results directly into HTML via string concatenation or embedded `<?php ?>` blocks, then attach jQuery/DataTables/Bootstrap-modal JS at the bottom of the same file.
- Action scripts that don't render HTML ([admin_add_vehicle.php](../admin_add_vehicle.php), [admin_edit_vehicle.php](../admin_edit_vehicle.php), [admin_delete_vehicle.php](../admin_delete_vehicle.php), [admin_delete_user.php](../admin_delete_user.php), [admin_update_user.php](../admin_update_user.php), [admin_confirm_booking.php](../admin_confirm_booking.php), [delete_booking.php](../delete_booking.php), [update_booking_time.php](../update_booking_time.php)) either `header('Location: ...')` redirect back to a page script (for form-POST-driven CRUD) or `echo json_encode(...)` (for AJAX-driven actions from DataTables rows). Both patterns exist side by side depending on which admin page triggers them.
- [admin_confirm_booking.php](../admin_confirm_booking.php) is the only script observed to combine row locking, a DB transaction, and multi-table writes (booking status, vehicle inventory, transaction record) in one atomic operation.
- Client-side JS embedded per-page (not centralized) handles: sidebar collapse, DataTables initialization, modal population from `data-*` attributes on row buttons, and AJAX calls back to the corresponding action scripts.

---

## Customer Architecture

- Entry: any customer-facing page (e.g. [index.php](../index.php)) can be reached without a session; login state is checked per-page, not enforced globally.
- Each customer page independently queries the DB for its own content at render time (e.g. `vehicles.php` queries `vehicles` and per-vehicle `bookings` counts; `transactions.php` queries `bookings` joined to `vehicles` and `transactions` for the logged-in user).
- Session/login state on the client side is determined by calling `me.php` (or, in one code path, `check_login.php`) via `fetch`/`$.getJSON` after page load, then toggling the navbar auth area and gating booking actions in JavaScript — the initial page HTML does not conditionally hide/show these elements server-side except in a few places (e.g. `vehicles.php` sets `window.userLoggedIn` from PHP session state directly; `transactions.php` and `about.php` branch server-side on `$_SESSION['user']` before rendering).
- Booking is a two-step client-server exchange: `reserve_preview.php` (no side effects, pure calculation) followed by `reserve.php` (validates and persists). Both independently recompute days/subtotal/discount/total from the same inputs rather than one calling the other.
- [js/app.js](../js/app.js) is loaded on every customer page and contains the shared client-side logic (navbar auth rendering, login/signup form handlers, category filtering, booking flow handlers). Several customer pages (`vehicles.php` in particular) additionally define their own inline `<script>` blocks that duplicate parts of this logic rather than relying solely on `app.js`.

---

## External Integrations

Verified from `<script>`/`<link>` tags and PHP function calls; no server-to-server API integrations were found.

- **CDN-hosted front-end libraries only:** Bootstrap 5.3.2, Font Awesome 6.4.2, Animate.css 4.1.1, jQuery (3.6.0 or 3.7.1 depending on page), jQuery UI 1.13.2 + Timepicker Addon 1.6.3, DataTables 1.11.5 (admin pages only). No local vendoring or lockfile.
- **Email:** [forgot_password.php](../forgot_password.php) calls PHP's native `mail()` directly with a `From:` header built from `$_SERVER['HTTP_HOST']`. No SMTP client library, API-based email service, or mail configuration file was found anywhere in the codebase.
- **No payment gateway integration** — `amount_paid` is a plain numeric field entered by the customer and compared server-side against the computed total in `reserve.php`; no external payment processor call was found.
- **No SMS, maps, or other third-party API integration** was found in any file read.
- **Postman collection** ([PMS.postman_collection.json](../PMS.postman_collection.json)) documents the JSON endpoints as an API surface intended for reuse by a future mobile client, but no mobile application source was found in this repository.

---

*This document reflects direct source inspection only. No code changes were made and no architectural changes are proposed.*
