# PMS Car Rental — Project Audit

This audit reflects a direct, file-by-file inspection of the codebase as it exists today. Facts are marked as verified where confirmed by reading source files; anything not directly observable is listed under **Unknown Areas** rather than assumed.

---

## Project Overview

PMS Car Rental is a university-project PHP web application for browsing vehicles, booking rentals, and managing bookings/inventory from an admin panel. It is a **traditional server-rendered PHP app** (no framework, no MVC, no build tooling) with a small JSON API surface intended for a future mobile client. The system is being incrementally maintained and extended as part of a Systems Integration and Architecture course, per [CLAUDE.md](../CLAUDE.md).

---

## Technology Stack

Verified from actual `<script>`/`<link>` tags and PHP source:

- **Backend:** PHP, procedural style (no framework). Mixes two DB access layers — `PDO` ([db.php](../db.php)) and `mysqli` ([db_connect.php](../db_connect.php)) — inconsistently across files.
- **Database:** MySQL/MariaDB, database name `pms_connection`.
- **Frontend:** Bootstrap 5.3.2 (CDN), Font Awesome 6.4.2 (CDN), Animate.css 4.1.1 (CDN), jQuery 3.7.1 (**corrected, UI Implementation Plan Phase 11, 2026-08-21** — this line previously read "version differs by page: 3.6.0 on admin pages, 3.7.1 on customer pages"; a fresh grep of every `<script src="…jquery…">` tag across all 20 PHP entry points found `3.7.1` everywhere, admin and customer alike, with zero references to `3.6.0` anywhere in the codebase. The split was fixed by an earlier, undocumented change; only this line was stale), jQuery UI 1.13.2 + Timepicker Addon 1.6.3, DataTables 1.11.5 (admin pages only).
- **No build tooling:** no `package.json`, `composer.json`, bundler config, or `node_modules` anywhere in the tree. All dependencies are CDN-hosted; no lockfile exists.
- **No environment/config framework:** no `.env`, no config class. Credentials are hardcoded in PHP.

---

## Architecture

- No router, no front controller, no templating engine, no autoloader. Every PHP file at the repository root is a directly-hit entry point that mixes HTML output, inline SQL, and business logic.
- Two independent surfaces with **no shared bootstrap**: customer-facing pages (full HTML documents with embedded jQuery/AJAX, sharing `includes/client_navbar.php` / `includes/client_footer.php` / `includes/auth_modals.php`) and admin pages (gated by a separate session key, sharing `includes/admin_sidebar.php` / `includes/admin_topbar.php`).
- **Correction (System Enhancements initiative, 2026-08-21):** `js/app.js` (customer) and `js/admin.js` (admin) remain fully separate from each other — neither includes or calls into the other — but they are no longer each other's *only* JS. Two new shared modules now load on both surfaces: `js/confirm.js` (the `window.PMSConfirm` confirmation dialog, moved out of what was originally an admin-only `js/admin.js` implementation) and `js/theme.js` (the dark-mode toggle). Both are self-contained, page-shell-only modules — they know nothing about bookings, vehicles, or any other business logic — so the customer/admin business-logic isolation itself is unchanged; only the page-chrome layer now has two small shared pieces.
- A small JSON API layer ([api_vehicles.php](../api_vehicles.php), [api_my_bookings.php](../api_my_bookings.php)) is explicitly built for a future mobile client — confirmed by an in-code comment and by [PMS.postman_collection.json](../PMS.postman_collection.json), whose description states it is "used by both the web front end and (eventually) the mobile app."
- No shared "require login" helper — every endpoint repeats its own inline `if (!isset($_SESSION[...]))` guard.
- No CSRF protection or rate limiting observed anywhere.

---

## Folder Structure

```
(root)        — all PHP pages + JSON endpoints, flat, no subfolders. Includes privacy.php
                (new, System Enhancements initiative, Step 6, 2026-08-21) — a seventh
                customer-facing page, bringing that count to 7 (previously 6: index, vehicles,
                transactions, about, faq, receipt).
includes/     — admin_sidebar.php, admin_topbar.php (admin-panel chrome; not used by customer pages)
              — client_navbar.php, client_footer.php, auth_modals.php (customer-facing chrome; not used by admin pages)
assets/       — vehicle images, logo, video, and assets/licenses/ (one license-upload target)
uploads/      — uploads/licenses/ (a SECOND, separate license-upload target — see Known Limitations)
css/          — one file, styles.css
js/           — app.js, admin.js, booking-validation.js, voucher-manager.js,
                confirm.js and theme.js (the last two added by the System Enhancements
                initiative 2026-08-21 — shared page-chrome modules loaded on both
                customer and admin pages).
                Correction (UI Implementation Plan, Phase 11, 2026-08-21): printer.js
                was listed here but has been DELETED — confirmed dead, unreferenced by
                any of the 20 PHP entry points. Seven JS files remain, not eight.
sql_file/     — pms_connection.sql (phpMyAdmin full dump: schema + seed data)
db_migrations/— 4 standalone patch scripts (not a migration framework/tool)
docs/         — this audit plus ARCHITECTURE.md, DATABASE.md, API.md, FEATURES.md, BUGS.md,
                DEPLOYMENT.md, DESIGN_SYSTEM.md (all currently empty except this file)
references/   — inspiration/, screenshots/, ui/ — design reference material, not app code
skills/       — Claude Code skill definitions (php-pro.md, sql-pro.md, etc.) — tooling only,
                not part of the running application
CLAUDE.md, README.md, CHANGELOG.md, TODO.md — root docs; README/CHANGELOG/TODO are empty
```

This directory is **not a git repository** — there is no commit history available to cross-reference file provenance.

---

## Authentication

Two entirely separate, non-overlapping auth systems:

**Customer auth** — session key `$_SESSION['user']`, PDO via [db.php](../db.php):
- [register.php](../register.php) — validates email/password (min 6 chars), hashes with `password_hash()`, inserts into `users`. Contains a runtime schema patch: if the `license_path` column is missing, it `ALTER TABLE`s it on the fly and retries the insert.
- [login.php](../login.php) — `password_verify()` against `users.password`, stores `id/name/email` in session.
- [logout.php](../logout.php) — `session_unset()` + `session_destroy()`.
- [me.php](../me.php) and [check_login.php](../check_login.php) — two different JSON session-check endpoints, used inconsistently by different pages/scripts.
- [forgot_password.php](../forgot_password.php) / [reset_password.php](../reset_password.php) — 6-digit code, 15-minute expiry, sent via PHP's native `mail()` (no SMTP configured).
- [change_password.php](../change_password.php) — requires current-password verification.

**Admin auth** — session key `$_SESSION['admin_id']`, mysqli via [db_connect.php](../db_connect.php):
- [admin-login.php](../admin-login.php) — separate `admins` table and session key.
- Every `admin_*.php` page independently re-checks `$_SESSION['admin_id']` at the top; there is no shared guard.
- Every admin page redirects unauthenticated visitors to `admin-login.php` directly; the earlier `includes/header.php`/`includes/footer.php` shell (which redirected to `login.php`, the customer login page — a naming/redirect mismatch) was removed during the Admin Dashboard phase (Steps 1 and 10) after being fully superseded by `includes/admin_sidebar.php` + `includes/admin_topbar.php`.

---

## Customer Flow

1. **Home** ([index.php](../index.php)) → **Vehicles** ([vehicles.php](../vehicles.php)): lists active vehicles; availability is computed live by counting overlapping bookings with status in `pending/confirmed/completed`.
2. Clicking "Reserve" requires login (checked client-side against `me.php`).
3. Booking modal calls [reserve_preview.php](../reserve_preview.php) — computes days/subtotal/discount/total, no side effects.
4. On confirm, [reserve.php](../reserve.php) re-validates everything server-side (dates, age ≥ 18, voucher, unit availability) and inserts the booking as `status='pending'` inside a DB transaction. It explicitly does **not** decrement `vehicles.units_total` or create a `transactions` row at this point — inventory and payment-record creation are deferred until an admin confirms the booking.
5. Optional driver's license upload: sent as base64 in the JSON payload, written to `uploads/licenses/` — a different directory than the one `register.php` uses (`assets/licenses/`).
6. [transactions.php](../transactions.php) — user's active/completed bookings, with:
   - **Cancel** ([cancel_booking.php](../cancel_booking.php)) — tiered refund: 100% if ≥24h before rental date, 50% if <24h.
   - **Return Early** ([return_early.php](../return_early.php)) — marks booking `completed`, increments `units_total` back, logs a `transactions` row with `payment_status='returned'`.
7. [receipt.php](../receipt.php) — renders a single booking by `id` or `booking_ref`.
8. [about.php](../about.php) — contact form wired to [save_message.php](../save_message.php) (login-gated, writes to `messages`).
9. [faq.php](../faq.php) — static content, no DB usage.

---

## Admin Flow

1. [admin-login.php](../admin-login.php) → [admin-dashboard.php](../admin-dashboard.php): shows total cars/users/active-rental metrics, recent transactions (with inline "Confirm" for pending bookings), and recent contact messages.
2. [admin_vehicles.php](../admin_vehicles.php) — vehicle CRUD via [admin_add_vehicle.php](../admin_add_vehicle.php) / [admin_edit_vehicle.php](../admin_edit_vehicle.php) / [admin_delete_vehicle.php](../admin_delete_vehicle.php); image uploads validated with `exif_imagetype()` (real content check, not just extension).
3. [admin_users.php](../admin_users.php) — user edit/delete via [admin_update_user.php](../admin_update_user.php) / [admin_delete_user.php](../admin_delete_user.php) (self-delete is blocked).
4. [admin_vouchers.php](../admin_vouchers.php) — single-file CRUD switching on `$_POST['action']` (`create`/`update`/`delete`).
5. [view-all-data.php](../view-all-data.php) — all transactions, with edit-time ([update_booking_time.php](../update_booking_time.php)), delete ([delete_booking.php](../delete_booking.php)), and confirm actions.
6. [admin_confirm_booking.php](../admin_confirm_booking.php) — the one place that actually decrements `vehicles.units_total` and inserts the `transactions` row; does this atomically under `FOR UPDATE` row locks inside a transaction. This is the most defensively-written file in the codebase.

---

## Database

Schema and seed data verified from [sql_file/pms_connection.sql](../sql_file/pms_connection.sql) (a phpMyAdmin dump). Six tables:

- `admins` — id, name, email, password (hashed)
- `users` — id, name, email, password_hash*, role, created_at (+ `reset_code`/`reset_code_expires` from migration 02, + `license_path`/`password` patched at runtime or via migration)
- `vehicles` — id, title, category, seats, fuel, transmission, price_per_day, units_total, is_active, thumbnail, created_at, slug
- `bookings` — id, user_id, vehicle_id, booking_ref, rental_date, return_date, pickup_time, dropoff_time, days, rate, discount, total_amount, contact_number, status (enum: pending/confirmed/completed/cancelled), created_at, voucher_id, age, license_file, paid
- `vouchers` — id, code, discount_amount, discount_pct, is_active, single_use, usage_count, usage_limit, used_at, used_by, created_at
- `transactions` — id, booking_id, transaction_ref, amount, paid_at (+ `payment_status` from migration)
- `messages` — id, name, email, message, created_at

FK constraints: `bookings.user_id/vehicle_id/voucher_id`, `transactions.booking_id`, `vouchers.used_by`, with cascade/set-null rules as defined in the dump.

**\*Important verified mismatch:** the raw dump defines `users.password_hash`, but every PHP auth file queries/inserts `users.password`. [db_migrations/01_fix_schema_mismatches.sql](../db_migrations/01_fix_schema_mismatches.sql) (and its `_hostinger` variant) exist specifically to rename that column and add `transactions.payment_status` after importing the raw dump. [db_migrations/20251106_add_columns.sql](../db_migrations/20251106_add_columns.sql) is a broader catch-up migration adding columns the code expects (`units_total`, `thumbnail`, `voucher_id`, `age`, `license_file`, etc.).

This means **the live schema depends on which migration scripts were actually run against a given database**, and cannot be treated as fixed from the dump alone. Several PHP files defensively probe `information_schema` or catch "unknown column" errors at runtime ([return_early.php](../return_early.php), [register.php](../register.php)) rather than relying on a guaranteed schema, which indicates real drift between environments (local XAMPP vs. Hostinger — see the `u822732810_pmsdatabase` reference in the `_hostinger` migration).

There is no migration framework/tool — `db_migrations/` is four standalone, manually-run SQL scripts, not a versioned/tracked system.

---

## Current Features

Verified as implemented and wired end-to-end:

- Customer registration/login/logout, password reset via emailed code, change password
- Vehicle browsing with category filter and live availability
- Booking preview → confirm flow with age validation (18+), optional voucher, optional license upload
- Booking cancellation with tiered refund calculation
- Early return of an active rental
- Transaction/booking history view (active vs. completed)
- Printable/viewable receipt per booking
- Contact form (login-gated) that stores messages for admin review
- Admin: dashboard metrics, vehicle CRUD (with image upload), user management (edit/delete), voucher CRUD, all-transactions view with time editing/deleting/confirming
- Admin booking confirmation that atomically decrements inventory and creates the payment/transaction record
- A JSON API surface (`api_vehicles.php`, `api_my_bookings.php`) intended for a future mobile client

---

## Known Limitations

Directly observed in the code, not inferred:

- **No CSRF protection or rate limiting** on any form or endpoint.
- **Hardcoded DB credentials** in two separate files ([db.php](../db.php), [db_connect.php](../db_connect.php)), both defaulting to local XAMPP/Laragon values (`root` / empty password) — not safe as-is for a shared production config.
- **Dual DB access layers** (PDO vs. mysqli) used inconsistently; some files (`vehicles.php`, `apply_voucher.php`, `get_vouchers.php`) open both connections in a single request.
- **Two separate license-upload directories** (`assets/licenses/` from `register.php`, `uploads/licenses/` from `reserve.php`) with no apparent reconciliation.
- **Schema/code drift**, as described under Database — the raw SQL dump does not match what the application queries without applying the migration scripts.
- **No shared layout/include for customer pages** — navbar, footer, and login/signup modal markup is copy-pasted identically across `index.php`, `vehicles.php`, `transactions.php`, `faq.php`, and `about.php`. Changing shared chrome requires editing all five files. **Stale for `about.php`:** since the Shared Components phase, `about.php` includes `includes/client_navbar.php`, `includes/client_footer.php`, and `includes/auth_modals.php` instead of duplicating this markup — confirmed by direct inspection during the About Us Modernization phase.
- **`js/app.js` (1208 lines) contains substantial dead/duplicated code**: multiple competing implementations of the booking-confirm flow, a large commented-out block from an earlier localStorage-based auth system, and references to DOM elements (`#bookingMultiModal`, `#bookingStep1`, `#carType`, `#rentalDate`) that do not appear in any PHP page read during this audit, which use `#bookingForm`/`#rental_date` snake_case instead — app.js appears only partially in sync with current markup.
- **Inconsistent session-check endpoints** — `me.php` and `check_login.php` both report login state but are used by different pages/scripts without a clear convention.
- ~~**Admin auth redirect mismatch** — `includes/header.php` redirects unauthenticated admin visitors to `login.php` (customer login) instead of `admin-login.php`.~~ Resolved during the Admin Dashboard phase: `includes/header.php` was removed (Step 10) after Step 1 stopped including it; every admin page now redirects unauthenticated visitors to `admin-login.php` directly.
- **No email service configured** — `forgot_password.php` calls PHP's native `mail()` with no SMTP setup; will silently no-op on most environments.
- **No CSRF token, no request throttling, and no centralized input-validation layer** — every endpoint re-implements its own ad hoc checks.
- **File naming is inconsistent**: hyphenated (`admin-dashboard.php`, `admin-login.php`) vs. underscored (`admin_add_vehicle.php`, etc.).
- ~~All `docs/*.md` files except this one, plus root `README.md`/`CHANGELOG.md`/`TODO.md`, are currently empty — there was no prior documentation to reconcile this audit against.~~ **Stale as of 2026-08-22.** True on 2026-08-06 when this audit was written; long since superseded. `docs/` now holds 27 populated Markdown documents (analyses, implementation plans, `BUGS.md`, `FEATURES.md`, `DESIGN_SYSTEM.md`, `COMPONENT_LIBRARY.md`, `API.md`, `ARCHITECTURE.md`, `DATABASE.md`, `DEPLOYMENT.md`) and root `CHANGELOG.md` is ~4,100 lines. `README.md` **is** still empty — that one part of the claim remains accurate and is the only genuinely undocumented entry point.

---

## Unknown Areas

Could not be verified from the source code alone during this audit:

- **The actual live/deployed database schema** — only the dump and migration scripts were inspected; no live DB connection was available to confirm which migrations have actually been applied to any running environment (local or Hostinger).
- **Deployment target and process** — `db_migrations/01_fix_schema_mismatches_hostinger.sql` references a Hostinger database (`u822732810_pmsdatabase`), implying a hosting target, but no deployment scripts, CI config, or `DEPLOYMENT.md` content exist to confirm the actual process.
- **Whether the mobile app referenced in the Postman collection description and `api_vehicles.php` comment exists anywhere** — no mobile project files were found in this repository.
- **Intended use of `references/inspiration`, `references/screenshots`, `references/ui`** — contents were listed but not reviewed in depth; unclear whether these are actively used as design references or leftover material.
- **Whether `check_login.php` vs. `me.php` represents an intentional split (e.g. legacy vs. current) or simple duplication** — no comments or docs clarify this.
- **Whether the commented-out/dead code blocks in `app.js` are safe to remove** — without version history (no git repo) it's unclear whether they're inactive legacy code or mid-refactor scaffolding.
- **Git/version history** — this directory is not a git repository, so authorship, change timeline, and prior intent behind any of the above cannot be traced.

---

## Questions for Developers

1. Which `db_migrations/*.sql` scripts have actually been run against the environment(s) currently in use (local, Hostinger, or both)? Is there a canonical "current schema" source of truth?
2. Is there a plan to consolidate the PDO (`db.php`) and mysqli (`db_connect.php`) connection layers, or is the split intentional (e.g. legacy vs. new code)?
3. Should `assets/licenses/` and `uploads/licenses/` be unified into a single upload target?
4. Is `check_login.php` deprecated in favor of `me.php`, or are both still intentionally in use?
5. Is the mobile app referenced in the Postman collection and `api_vehicles.php` an active, planned, or shelved effort?
6. Is `js/app.js`'s dead code (commented localStorage auth, unmatched `#bookingMultiModal`/`#bookingStep*` handlers) safe to remove, or does it reflect an in-progress refactor that should be completed instead?
7. What is the intended deployment target and process (confirm Hostinger, or another host)? Should `DEPLOYMENT.md` be filled in as part of this audit's follow-up?
8. ~~Is the `admin-login.php` vs. `login.php` redirect mismatch in `includes/header.php` intentional, or a bug?~~ Resolved — `includes/header.php` was deleted during the Admin Dashboard phase (Step 10); the mismatch no longer exists.
9. Is email delivery (password reset) expected to work via `mail()` in production, or should a transactional email service be planned for?
10. ~~Should the customer-facing shared chrome (navbar/footer/modals) be extracted into a shared include, matching the pattern already used for admin pages in `includes/`?~~ Resolved — customer-facing chrome is already extracted into `includes/client_navbar.php`, `includes/client_footer.php`, and `includes/auth_modals.php`, used by all six customer pages.

---

*This audit was produced by direct inspection of the source code on 2026-08-06. No code was modified as part of this audit.*

---

## Architectural note — UI Implementation Plan, Phase 12 (Final UI Review), 2026-08-22

**No architectural change was made in Phase 12.** The phase's seven fixes were confined to CSS rules, one `<body>` class, one PHP presentation helper local to `receipt.php`, and two JavaScript statements. No new file, module, include, endpoint, route, table, or dependency was introduced; nothing was deleted; the customer/admin split, the shared-partial structure, and the dual PDO/mysqli layering are all unchanged.

Three factual corrections were applied to this document in the same pass (the `js/` file list, the jQuery version claim, and the "all docs are empty" line) — those correct stale *descriptions* of the architecture, not the architecture itself.

**One structural observation worth recording**, since it is the kind of thing this audit exists to surface: Phase 11 deleted `js/printer.js` as confirmed dead, and Phase 12 then had to repair a `@media print` rule in the *shared* stylesheet that silently depended on it. Neither decision was wrong in isolation, but the coupling was invisible — a shared CSS file referenced an element id that only one JS module ever created, with nothing in either file pointing at the other. This is a concrete instance of the "no build tooling, no module system, no dependency graph" limitation already listed above: cross-file dependencies in this codebase are discoverable only by grep, and dead-code removal in one file can silently break another. Worth keeping in mind for the still-open dead-code cleanup in `js/app.js` ([BUGS.md](BUGS.md) item 40), which is a larger version of the same hazard — and which "Questions for Developers" #6 below still has not answered.
