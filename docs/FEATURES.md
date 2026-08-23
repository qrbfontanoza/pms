# PMS Car Rental — Implemented Features

Every feature below was confirmed by reading its actual implementation (PHP endpoint(s), and the JS/HTML that call it). Nothing here is inferred from naming alone. "Missing functionality" lists only things that were searched for and not found in the files inspected — it does not speculate about intent.

---

## Customer Registration

**Description:** New customers create an account with name, email, password, and an optional driver's license image.

**Files involved:** [register.php](../register.php) (endpoint), `#signupModal` in the shared [includes/auth_modals.php](../includes/auth_modals.php) partial, `registerUser()` and the `#signupForm` submit handler in [js/app.js](../js/app.js).

**Dependencies:** `users` table (via [db.php](../db.php)/PDO), `assets/licenses/` directory (created at runtime if missing), PHP's `exif_imagetype()`/`random_bytes()`.

**Current status:** Implemented and wired end-to-end. Accepts either JSON or `multipart/form-data`. Validates email format and password length (≥6). If a license file is included, validates real image type (JPEG/PNG only) and size (≤3MB) via `exif_imagetype()`, and stores it under `assets/licenses/` with a random filename. Contains a self-healing path: if the `license_path` column doesn't exist yet, the script `ALTER TABLE`s it and retries the insert. **As of 2026-08-13**, the modal has real-time per-field validation (name/email/password/confirm-password, matching the server's actual rules), a password-length helper text, a visibility toggle on both password fields, and an "Already have an account? Log in" link — see the Authentication phase's [CHANGELOG.md](../CHANGELOG.md) entries for detail. The signup modal's own `<input type="file">` for a license upload was not added by that phase (out of its scope) — the only license-upload UI on the site remains inside the booking flow on `vehicles.php`.

**Missing functionality:** No email verification step (account is usable immediately after registration). No duplicate-submission/rate limiting. No CAPTCHA.

---

## Customer Login

**Description:** Registered customers authenticate with email + password.

**Files involved:** [login.php](../login.php) (endpoint), `#loginModal` in the shared [includes/auth_modals.php](../includes/auth_modals.php) partial, `loginUser()` and `#loginForm` handler in [js/app.js](../js/app.js).

**Dependencies:** `users` table, `password_verify()` against `users.password`, PHP session (`$_SESSION['user']`).

**Current status:** Implemented. Accepts JSON body, looks up by email, verifies hash, stores `id`/`name`/`email` in session on success (password hash is not retained in session). **As of 2026-08-13**, the modal has real-time validation on both fields and a "Forgot your password?" link opening the new forgot-password flow (see below) — see the Authentication phase's [CHANGELOG.md](../CHANGELOG.md) entries for detail.

**Missing functionality:** No account lockout or throttling after repeated failed attempts. No "remember me" option.

**Known limitation, unrelated to the Authentication phase:** on `transactions.php` specifically, an unguarded `.datepicker()` call at `js/app.js:51` throws before any of this file's login/registration/forgot-password code is reached, silently breaking all of it (along with `renderNavbarAuth()`) on that one page only — see [BUGS.md](BUGS.md) item 11. Pre-existing since 2026-08-08, confirmed to also affect the Authentication phase's new code during that phase's Step 6 review, not caused by it.

---

## Customer Logout

**Description:** Ends the current session for either a customer or an admin.

**Files involved:** [logout.php](../logout.php), linked from the customer navbar dropdown and the admin sidebar.

**Dependencies:** PHP session.

**Current status:** Implemented. Calls `session_unset()` and `session_destroy()`, then redirects to `index.php`. Because both customer and admin session keys live in the same PHP session, this single script clears both regardless of which one triggered it.

**Missing functionality:** None found relative to its stated purpose; it is a shared endpoint for both roles rather than two separate ones.

---

## Session / Login Status Check

**Description:** Lets client-side JavaScript ask the server whether a customer is currently logged in.

**Files involved:** [me.php](../me.php) and [check_login.php](../check_login.php) — two separate endpoints doing an equivalent check; called from different places (`vehicles.php`'s inline script and `js/app.js`'s `getMe()` use `me.php`; `js/app.js`'s `#openBookingMultiModal` handler uses `check_login.php`).

**Dependencies:** PHP session (`$_SESSION['user']`).

**Current status:** Both implemented and functional independently. `me.php` returns `{logged_in, user: {id, name, email, role}}`; `check_login.php` returns `{loggedIn, user}` (different key casing/shape).

**Missing functionality:** No single shared session-check endpoint — two implementations exist with slightly different response shapes, used inconsistently across the codebase.

---

## Forgot Password (Request Reset Code)

**Description:** A customer who forgot their password requests a 6-digit reset code by email.

**Files involved:** [forgot_password.php](../forgot_password.php) (endpoint), [includes/auth_modals.php](../includes/auth_modals.php) `#forgotPasswordModal` Step 1 (UI, added Authentication Phase Step 4).

**Dependencies:** `users` table (`reset_code`, `reset_code_expires` columns — added by [db_migrations/02_add_password_reset_columns.sql](../db_migrations/02_add_password_reset_columns.sql)), PHP's native `mail()` function.

**Current status:** Implemented, both backend and UI. Generates a random 6-digit code, sets a 15-minute expiry, stores both on the user row, and calls `mail()` to send it. Always returns a generic success message regardless of whether the email exists (does not leak account existence). **As of 2026-08-13, a customer-facing UI exists** — a "Forgot your password?" link in the login modal opens `#forgotPasswordModal`, a 3-step flow (email → code → new password) with real-time field validation, reusing this endpoint and `reset_password.php` below. Previously, this endpoint had no client-side UI anywhere in the project despite being fully functional.

**Missing functionality:** No SMTP/mail transport configuration exists anywhere in the codebase — `mail()` depends entirely on the server's local mail setup and was not verified to actually deliver anything (though a local Mailpit-based test during the Authentication phase confirmed the endpoint itself, and PHP's `sendmail_path` wiring, work correctly end-to-end on this development machine). No resend cooldown/throttling on repeated requests — the UI's "Resend" button can be clicked freely.

---

## Reset Password (Consume Code)

**Description:** A customer submits the emailed code plus a new password to complete the reset.

**Files involved:** [reset_password.php](../reset_password.php) (endpoint), [includes/auth_modals.php](../includes/auth_modals.php) `#forgotPasswordModal` Steps 2-3 (UI, added Authentication Phase Step 4).

**Dependencies:** `users` table (`reset_code`, `reset_code_expires`, `password`).

**Current status:** Implemented, both backend and UI. Validates the code matches and hasn't expired, hashes the new password with `password_hash()`, clears the reset code fields. **As of 2026-08-13**, the forgot-password modal's Step 3 calls this endpoint with the email (from Step 1) and code (from Step 2) carried in JS closure variables; on success the modal closes and the login modal opens. Verified genuinely end-to-end during implementation: a real reset code was requested, consumed, the password changed in the database, and a subsequent real login with the new password succeeded.

**Missing functionality:** No limit on how many times a code can be attempted before it's invalidated (only time-based expiry is enforced). No separate "verify code" step server-side — an incorrect code is only caught when Step 3 submits (by design; see [AUTHENTICATION_IMPLEMENTATION_PLAN.md](AUTHENTICATION_IMPLEMENTATION_PLAN.md) Step 4).

---

## Change Password (Logged In)

**Description:** A logged-in customer changes their password by providing the current one.

**Files involved:** [change_password.php](../change_password.php).

**Dependencies:** `users` table, active `$_SESSION['user']`.

**Current status:** Implemented. Verifies the current password with `password_verify()` before allowing the change; new password re-hashed with `password_hash()`. **As of 2026-08-19 (Customer Dashboard phase, Step 4)**, a customer-facing UI now exists — the "Change Password" form in `transactions.php`'s Profile section (Current/New/Confirm fields, `AuthValidation`-wired) calls this endpoint via `fetch()`. Previously the endpoint was fully functional but had no client-side form anywhere in the project.

**Missing functionality:** None found relative to its stated purpose.

---

## Vehicle Browsing (Public Listing Page)

**Description:** Customers browse active vehicles through a full server-side filter/sort/pagination pipeline: a sidebar filter panel (category, price range, seats, fuel, transmission, availability), 5 sort options, and paginated results. Rewritten across the 7-step Vehicle Listing Modernization ([VEHICLE_LISTING_IMPLEMENTATION_PLAN.md](VEHICLE_LISTING_IMPLEMENTATION_PLAN.md), 2026-08-08 to 2026-08-09); this entry reflects the post-Step-7 state.

**Files involved:** [vehicles.php](../vehicles.php) only — filter panel markup, PHP filter/sort/pagination logic, and results grid all live in this one file. `js/app.js` is loaded for unrelated shared functionality (navbar, booking modal, auth); its own `.category-btn` handler (`js/app.js:371-397`) is confirmed dead on this page — see [BUGS.md](BUGS.md) item 12.

**Dependencies:** `vehicles` table, `bookings` table (via a single `LEFT JOIN` subquery for booked-unit counts, not per-row queries), `db.php`/PDO only — the old `db_connect.php`/mysqli dependency was removed in Step 1.

**Current status:** Implemented. Sidebar filter form (GET-submitted, offcanvas below 992px) supports category (6, checkbox, static counts against the full active set), price range (min/max), seats (4 fixed buckets: 2 / 4-6 / 7-8 / 9+), fuel type, transmission, and an availability-only toggle — all combined server-side into one parameterized `WHERE` clause, applied before `LIMIT`/`OFFSET` so pagination math stays correct under any filter combination. Sort supports title/price ascending-descending plus newest, via a whitelisted `ORDER BY`. Results are paginated at 9 per page with a standard Bootstrap pagination control that preserves all active filters and sort in its links. Availability is computed via a single `LEFT JOIN (SELECT vehicle_id, COUNT(*) ... GROUP BY vehicle_id)` against the same `pending`/`confirmed`/`completed` status whitelist used elsewhere, eliminating the previous per-vehicle query. The homepage's `?category=suv` handoff (a plain string) and the sidebar's own `?category[]=suv` (an array) are normalized into one array before filtering, so both entry points work identically.

**Missing functionality:** No text/keyword search. Category checkbox counts are static (always reflect the full active set, not the currently-filtered result) — a deliberate decision, not a gap; see the Step 5 changelog entry for the reasoning. Live-browser responsive/interactive verification (offcanvas animation, keyboard navigation, mobile filter apply/close cycle) was not obtained during the modernization — the in-session Browser tool was gated behind a per-action approval prompt that could not be granted; recommend a manual pass before treating those aspects as verified.

---

## Vehicle Listing API

**Description:** JSON endpoint exposing vehicle data (list, single vehicle, or filtered by category) for non-browser consumers.

**Files involved:** [api_vehicles.php](../api_vehicles.php); documented in [PMS.postman_collection.json](../PMS.postman_collection.json).

**Dependencies:** `vehicles` and `bookings` tables via `db.php`/PDO.

**Current status:** Implemented. Supports `?id=`, `?category=`, or no params (all active vehicles), and includes a computed `available_units` field per vehicle using the same non-date-filtered count as `vehicles.php`.

**Missing functionality:** No authentication/API key is required to call this endpoint. No pagination.

---

## My Bookings API

**Description:** JSON endpoint returning the logged-in customer's pending/confirmed bookings.

**Files involved:** [api_my_bookings.php](../api_my_bookings.php).

**Dependencies:** `bookings`/`vehicles` tables, `$_SESSION['user']['id']`.

**Current status:** Implemented. Requires login (401 JSON response if not). Joins `bookings` to `vehicles` for the title and filters to `status IN ('pending','confirmed')`.

**Missing functionality:** Does not include `completed` or `cancelled` bookings (unlike the customer-facing `transactions.php` page, which shows a broader set) — no equivalent API endpoint for full booking history was found.

---

## Booking Price Preview

**Description:** Calculates days/subtotal/discount/total for a prospective booking before it's submitted, with no side effects.

**Files involved:** [reserve_preview.php](../reserve_preview.php), called from inline scripts in `vehicles.php` and from `js/app.js`'s `#btnPreview` handler(s).

**Dependencies:** `vehicles` table (rate lookup), `vouchers` table (optional discount lookup).

**Current status:** Implemented and functional as a read-only calculation endpoint.

**Missing functionality:** None found relative to its stated purpose; note that `reserve.php` independently re-implements the same day/subtotal/discount calculation rather than calling or sharing code with this endpoint, so the two must be kept in sync manually.

---

## Booking Creation

**Description:** Creates a rental booking after server-side re-validation of dates, age, vehicle availability, and voucher, with optional license image upload.

**Files involved:** [reserve.php](../reserve.php), the booking modal/form in `vehicles.php`, and multiple overlapping submit handlers in [js/app.js](../js/app.js).

**Dependencies:** `bookings`, `vehicles`, `vouchers` tables via `db.php`/PDO; `uploads/licenses/` directory for the optional base64 license upload; active `$_SESSION['user']`.

**Current status:** Implemented. Requires login. Validates rental date not in the past, return date ≥ rental date, age ≥ 18, sufficient `amount_paid` vs. computed total, and vehicle availability via an overlap query on `rental_date`/`return_date`. Inserts the booking as `status='pending'` inside a PDO transaction. Does not decrement `vehicles.units_total` or create a `transactions` row — that happens only when an admin confirms the booking. If a voucher was applied, updates its `usage_count`/`used_by`/`used_at` immediately after the booking insert (in a separate statement, not part of the same transaction).

**Missing functionality:** No payment gateway — `amount_paid` is a self-reported number compared against the computed total, with no external verification. The optional license upload uses a different target directory (`uploads/licenses/`) than the one used during registration (`assets/licenses/`), with no cross-reference between the two license records for the same user.

---

## Voucher Application at Booking Time

**Description:** A voucher code entered during booking is looked up and its discount (percentage or flat amount) applied to the subtotal.

**Files involved:** Voucher lookup logic embedded in both [reserve_preview.php](../reserve_preview.php) and [reserve.php](../reserve.php) (duplicated, not shared).

**Dependencies:** `vouchers` table (`code`, `is_active`, `discount_pct`, `discount_amount`, `usage_count`, `usage_limit`).

**Current status:** Implemented. `discount_pct` takes priority over `discount_amount` if both are set (per the `if (!empty($voucher['discount_pct']))` check). Rejects codes that don't match an active voucher.

**Missing functionality:** No validation that a `single_use` voucher hasn't already been used by the same customer before this booking is finalized beyond the general `usage_count`/`usage_limit` check.

---

## Voucher List Retrieval

**Description:** Populates the voucher dropdown shown in the booking form with currently usable vouchers.

**Files involved:** [get_vouchers.php](../get_vouchers.php) (endpoint), `loadVouchers()` in both [js/app.js](../js/app.js) and [js/voucher-manager.js](../js/voucher-manager.js) (two separate implementations of the same call).

**Dependencies:** `vouchers` table via `db_connect.php`/mysqli.

**Current status:** Implemented. Returns vouchers where `usage_count < usage_limit`, ordered by `created_at DESC`. No login check on this endpoint.

**Missing functionality:** Does not filter by `is_active`, only by usage count vs. limit — a voucher manually deactivated but not yet at its usage limit would still be returned by this endpoint (note: `reserve.php`/`reserve_preview.php` separately re-check `is_active` at booking time, so an inactive voucher shown in the dropdown would still be rejected at submission).

---

## Standalone Voucher Apply Endpoint

**Description:** A second, separate voucher-application code path (distinct from the one embedded in `reserve.php`/`reserve_preview.php`) that computes a discount and can optionally mark the voucher as used.

**Files involved:** [apply_voucher.php](../apply_voucher.php) (endpoint, mysqli-based), [js/voucher-manager.js](../js/voucher-manager.js) (`VoucherManager.applyVoucher()`), referenced by a `#voucherCode` change handler in `js/app.js` that targets DOM elements (`#voucherCode`, `#totalAmount`, `#discountAmount`, `#finalAmount`) not found in the booking form markup read in `vehicles.php`.

**Dependencies:** `vouchers` table via `db_connect.php`/mysqli; requires `$_SESSION['user']['id']`.

**Current status:** Implemented as a standalone endpoint with its own login check, discount calculation, and — when called with `complete: true` — its own separate usage-count increment logic (`SELECT ... FOR UPDATE` + transaction), independent from the usage-increment logic inside `reserve.php`.

**Missing functionality:** This endpoint's calling code in `js/app.js` references form field IDs (`#voucherCode`, `#totalAmount`) that do not match the IDs actually present in the booking form (`#voucherSelect`) confirmed in `vehicles.php` — whether this code path is reachable from the current UI could not be confirmed from the files inspected.

---

## Booking Date Validation (Client-Side)

**Description:** Restricts the rental/return date pickers to tomorrow onward and auto-triggers a price preview when dates or voucher selection change.

**Files involved:** [js/booking-validation.js](../js/booking-validation.js).

**Dependencies:** jQuery, the `#rental_date`/`#return_date`/`#btnPreview` elements in the booking form.

**Current status:** Implemented. Sets `min` attribute on both date inputs to tomorrow's date, and keeps `#return_date`'s minimum in sync with the selected `#rental_date`.

**Missing functionality:** This client-side minimum (tomorrow onward) is stricter than the server-side check in `reserve.php`, which explicitly allows a rental date equal to today (`if ($rental < $today)` rejects only dates strictly before today) — the two layers do not enforce the same rule.

---

## Booking Cancellation

**Description:** A customer cancels an upcoming pending or confirmed booking, with a refund percentage calculated from time remaining until the rental date.

**Files involved:** [cancel_booking.php](../cancel_booking.php), Cancel button/modal in [transactions.php](../transactions.php).

**Dependencies:** `bookings`/`vehicles` tables via PDO, active `$_SESSION['user']`.

**Current status:** Implemented. Only allows cancelling `pending`/`confirmed` bookings owned by the requesting user, and only if the rental date hasn't arrived yet. Refund is 100% if ≥24 hours until rental date, 50% otherwise. If the booking had been `confirmed` (not `pending`), increments `vehicles.units_total` back by 1. All inside a PDO transaction.

**Missing functionality:** The computed refund amount/percentage is returned in the JSON response and shown to the user, but no record of the refund is written anywhere (no `transactions` row, no refund-status column) — there is no persisted trace that a refund was owed or paid.

---

## Return Vehicle Early

**Description:** A customer with an active (`confirmed`, currently in-progress) booking can mark it returned before the scheduled return date.

**Files involved:** [return_early.php](../return_early.php), "Return Early" button/modal in [transactions.php](../transactions.php).

**Dependencies:** `bookings`/`vehicles`/`transactions` tables via PDO, active `$_SESSION['user']`.

**Current status:** Implemented. Requires the booking to be `status='confirmed'`. Locks the booking row (`FOR UPDATE`), sets `status='completed'`, increments `vehicles.units_total`, and inserts a `transactions` row with `transaction_ref` and `payment_status='returned'`. Conditionally sets `actual_return_date`/`early_return` only if those columns exist (checked via `information_schema.COLUMNS` at request time).

**Missing functionality:** No refund or credit is calculated or issued for the unused remaining rental days — the UI explicitly states "No refunds will be provided for unused rental days," confirming this is a stated design choice rather than an oversight.

---

## Transaction / Booking History (Customer Dashboard)

**Description:** The logged-in customer's full dashboard — enhanced summary cards, active/upcoming and completed booking history with status-correct action buttons, contextual booking alerts, and receipt/confirmation viewing. Rewritten across the Customer Dashboard phase, Steps 1-3 and 3.1 (2026-08-14).

**Files involved:** [transactions.php](../transactions.php), [receipt.php](../receipt.php) (status-aware confirmation/receipt view).

**Dependencies:** `bookings`/`vehicles`/`transactions` tables via PDO, `$_SESSION['user']`.

**Current status:** Implemented. The `js/app.js:51` crash that previously broke all client-side JS on this page (navbar auth, `AuthValidation`, motion) was fixed in Step 1 — see [BUGS.md](BUGS.md) item 11 (RESOLVED). The page now opens with 4 metric cards (Active Rentals, Completed Rentals, Total Spent, Next Rental — all derived from existing query data, no new queries) and a dismissible alerts section (see Notification Alerts, below). Bucketing is now driven by actual `bookings.status`, not just date comparison (fixes [BUGS.md](BUGS.md) item 10, RESOLVED): Active = `pending`/`confirmed` with `return_date >= today`; Completed/History = everything else, including `completed`, `cancelled` (regardless of date), and any `pending`/`confirmed` booking whose return date has passed — so a booking completed via Return Early now correctly appears in History instead of vanishing (the query's prior `AND status != 'completed'` exclusion was removed, with `GROUP BY b.id` added to prevent `LEFT JOIN transactions` duplicate rows). Action buttons are entirely status-driven: `pending`/`confirmed` with a future date get "Cancel Booking"; `pending`/`confirmed` already in-progress get "Return Early"; `confirmed` rows additionally get a "View Booking Confirmation" link to `receipt.php` (Step 3.1 — distinct label/copy from `completed` rows' "View Receipt", since a confirmed booking's total isn't necessarily final); `completed` rows get "View Receipt". Cancel/Return-Early now give Bootstrap alert feedback instead of native `alert()`. The orphaned License Preview modal was removed.

**Missing functionality:** No filtering/search/pagination for customers with many bookings.

---

## Customer Profile

**Description:** Self-service account management — view name/email/member-since/role, edit name and email, change password, and upload a profile picture. Added in the Customer Dashboard phase, Step 4 (2026-08-14) and Step 4.1 (picture upload).

**Files involved:** [update_profile.php](../update_profile.php) (new endpoint), [update_profile_picture.php](../update_profile_picture.php) (new endpoint), Profile section in [transactions.php](../transactions.php), avatar rendering in [js/app.js](../js/app.js) (`getAvatarHtml()`) and [admin_users.php](../admin_users.php) (`render_avatar_html()`, Avatar table column).

**Dependencies:** `users` table (`name`, `email`, `profile_picture_path` columns), active `$_SESSION['user']`, `assets/avatars/` directory (created at runtime if missing).

**Current status:** Implemented. `update_profile.php`: POST-only, requires session auth, validates non-empty name and a valid email format, checks email uniqueness excluding the current user's own row, updates via PDO prepared statement — no `user_id` accepted from the request body, only the session's own ID. `update_profile_picture.php`: validates real image content via `exif_imagetype()` (JPEG/PNG only, ≤3MB, matching `register.php`'s license-upload pattern), stores under a server-generated random filename in `assets/avatars/`, updates `users.profile_picture_path`, and deletes the previous picture file only after the new one is confirmed written and the DB row updated (avoids losing both on a partial failure). The navbar, profile section, and `admin_users.php` all render the same avatar-or-initials-circle pattern — an `<img>` when `profile_picture_path` is set, else a colored initials circle (first + last name initial) — confirmed via live testing that this fallback renders correctly for accounts with no picture, and that uploading/replacing a picture updates all three locations without a page reload (profile section and navbar update via JS DOM patch after a successful upload; `admin_users.php` reflects it on next load since it's server-rendered).

**Missing functionality:** No client-side pre-validation of file type/size before upload (relies entirely on server-side rejection). No way to remove a profile picture and revert to initials without uploading a replacement.

---

## Notification Alerts (Dashboard)

**Description:** Contextual, dismissible alerts on the customer dashboard for recent booking status changes — reminders for rentals starting soon, and notices for recently confirmed/cancelled bookings. Added in the Customer Dashboard phase, Step 5 (2026-08-14), as "Option B" of that step's design-decision analysis.

**Files involved:** [transactions.php](../transactions.php) only.

**Dependencies:** The existing `$active`/`$completed` booking arrays already loaded for the dashboard; no new table, endpoint, or migration.

**Current status:** Implemented as a deliberately lightweight, non-persistent mechanism — a conscious alternative to a full notification system (deferred; see [CUSTOMER_DASHBOARD_IMPLEMENTATION_PLAN.md](CUSTOMER_DASHBOARD_IMPLEMENTATION_PLAN.md) Step 5's Option A/B/C comparison). On every page load, PHP computes: a "reminder" alert for any active booking whose `rental_date` is within 24 hours; a "confirmed" alert for any `confirmed` booking created within the last 48 hours; a "cancelled" alert for any `cancelled` booking created within the last 48 hours. Rendered as dismissible Bootstrap alerts (`alert-warning`/`alert-success`/`alert-danger`). Because the `bookings` table has no `updated_at`/`confirmed_at`/`cancelled_at` column, "recently confirmed/cancelled" is approximated as "created within 48h AND currently in that status" — this also fires for a booking confirmed/cancelled in the same action it was created in (the common case here), not a true status-change timestamp. This approximation is documented inline in `transactions.php` and in `CHANGELOG.md`.

**Missing functionality:** No persistence — dismissing an alert only hides it for that page view; it reappears identically on the next reload since nothing is stored. No true status-change timestamp to key off of (see approximation above). Not a general-purpose notification system — see Step 5's Option A (deferred) for what a full implementation would require (new table, triggers, endpoints, navbar bell icon).

---

## Booking Receipt View

**Description:** Displays a single booking's full details (vehicle, dates, amounts, payment) as a standalone printable page, looked up by booking ID or reference.

**Files involved:** [receipt.php](../receipt.php) and the `@media print` block in [css/styles.css](../css/styles.css).

> **Updated 2026-08-22 (UI Implementation Plan, Phase 12).** This entry previously also listed `js/printer.js`'s `showReceiptModal()`/`printReceipt()` as an alternative in-modal rendering. **That file was deleted in Phase 11 as confirmed dead** (unreferenced by any page), so the modal rendering no longer exists and `receipt.php` is now the single receipt surface.

**Dependencies:** `bookings`/`vehicles`/`transactions` tables via PDO, active `$_SESSION['user']` (redirects to login if absent).

**Current status:** Implemented. `receipt.php` requires the booking to belong to the requesting user. Determines "amount paid" from `bookings.paid` if present, otherwise falls back to the most recent `transactions` row for that booking. The document is titled "Booking Receipt" for a `completed` booking and "Booking Confirmation" otherwise.

**Printing (repaired 2026-08-22, Phase 12 — [BUGS.md](BUGS.md) item 26):** printing works via the browser's own print command (Ctrl+P / print menu). The shared `@media print` block is scoped to `body.receipt-page .receipt-card` and prints the receipt card alone, with the navbar, footer and the card's own navigation links suppressed. Before this fix the block targeted the `#receiptModal` element that `js/printer.js` used to build, so after that file's deletion **every page in the application printed blank**, not just this one.

**Error states (added 2026-08-22, Phase 12 — [BUGS.md](BUGS.md) item 29):** requesting the page with no `?id=`/`?ref=` (HTTP 400) or with an unknown/foreign booking (HTTP 404) now renders a themed, responsive, theme-aware error card with recovery links to `transactions.php` and `vehicles.php`, instead of the bare unstyled `<p>` fragment it previously emitted. Status codes are unchanged.

**Missing functionality:** No in-page print button and no PDF export. Printing relies on the browser's own print command. Adding a dedicated print affordance is a feature decision, deliberately not taken during a review phase.

---

## Contact / Support Message Form

**Description:** Logged-in customers send a message to the business from the About page; admins can view submitted messages.

**Files involved:** [save_message.php](../save_message.php) (endpoint), contact form in [about.php](../about.php), display block in [admin-dashboard.php](../admin-dashboard.php).

**Dependencies:** `messages` table via PDO, `$_SESSION['user']`.

**Current status:** Implemented. Requires login; validates name/email format/message are non-empty. Name/email fields are pre-filled and read-only when the user is logged in (pulled from session). Messages are listed (not paginated) on the admin dashboard, newest first.

**Missing functionality:** No reply mechanism, no read/unread status, no deletion capability for messages was found in the admin dashboard.

---

## FAQ Page

**Description:** Static accordion of frequently asked questions.

**Files involved:** [faq.php](../faq.php).

**Dependencies:** None (no DB query in this file).

**Current status:** Implemented as fully static content; no dynamic data source.

**Missing functionality:** Not applicable — content is hardcoded by design, confirmed by the absence of any DB query in this file.

---

## About Us Page

**Description:** Static company story/team section plus the contact form described above.

**Files involved:** [about.php](../about.php).

**Dependencies:** `$_SESSION['user']` (to pre-fill/lock the contact form fields); no other DB usage.

**Current status:** Implemented. Team member section and company copy are hardcoded.

**Missing functionality:** Not applicable to the static portions; see Contact Form above for the dynamic part of this page.

---

## Admin Login

**Description:** Separate authentication for staff/admin accounts.

**Files involved:** [admin-login.php](../admin-login.php).

**Dependencies:** `admins` table via `db_connect.php`/mysqli.

**Current status:** Implemented. Verifies `password_verify()` against `admins.password`, sets `$_SESSION['admin_id']`/`$_SESSION['admin_name']` on success, redirects to `admin-dashboard.php`.

**Missing functionality:** No lockout/throttling on repeated failed attempts. No "forgot admin password" flow exists (the customer-facing reset flow only applies to the `users` table, not `admins`) — an admin who is currently logged in can now change their own password via Admin Account Settings (below), but a locked-out admin still has no self-service recovery path.

---

## Admin Dashboard

**Description:** Landing page after admin login showing summary metrics, recent bookings (with an inline confirm action), recent contact messages, and a "Business Overview" panel (revenue by month, bookings by status, top vehicles by bookings).

**Files involved:** [admin-dashboard.php](../admin-dashboard.php).

**Dependencies:** `vehicles`/`users`/`bookings`/`messages` tables via mysqli.

**Current status:** Implemented. Shows total vehicle count, total user count, count of `confirmed` bookings, count of `pending` bookings, the 5 most recent bookings (joined to user/vehicle), and the 5 most recent messages. Includes an inline "Confirm" button for `pending` bookings that calls `admin_confirm_booking.php`.

Beneath the metric cards, a **Business Overview panel** (Admin Dashboard phase, Step 7) delivers the Phase 8 "Reports" and "Charts" tasks: a revenue-by-month bar list (last 6 months, `bookings.total_amount` summed for `confirmed`/`completed` bookings), a bookings-by-status breakdown (all four enum values, zero-filled), and a top-5-vehicles-by-bookings ranked list. Built entirely with Bootstrap's `.progress`/`.list-group` — no charting library. **Design decision:** this "Option A" approach (a dashboard panel, no new page, no new dependency) was chosen over a dedicated `admin_reports.php` with Chart.js ("Option B") specifically because all of its date bucketing runs inside MySQL (`GROUP BY YEAR(created_at), MONTH(created_at)`), sidestepping [BUGS.md](BUGS.md) item 15's server-timezone-vs-database-clock mismatch, which a date-range-filterable reports page would have made a hard blocker. See [ADMIN_DASHBOARD_IMPLEMENTATION_PLAN.md](ADMIN_DASHBOARD_IMPLEMENTATION_PLAN.md) Step 7 for the full option comparison. `transactions` is deliberately not used as an aggregation source (§2.9 of [ADMIN_DASHBOARD_ANALYSIS.md](ADMIN_DASHBOARD_ANALYSIS.md) — its `amount` column is `NULL` on return-type rows).

**Missing functionality:** Recent-transactions table is hardcoded to the 5 most recent rows with no "view more" pagination on this page itself (a separate full list exists at `view-all-data.php`, linked from here). The Business Overview panel has no date-range filter and no export — by design, per the Option A trade-off recorded above. `bookings.created_at` has no database index (flagged during Step 7, not fixed — a schema change requiring separate approval).

---

## Admin Vehicle Management

**Description:** List, add, edit, and delete vehicles, including thumbnail image upload.

**Files involved:** [admin_vehicles.php](../admin_vehicles.php) (list + modals), [admin_add_vehicle.php](../admin_add_vehicle.php), [admin_edit_vehicle.php](../admin_edit_vehicle.php), [admin_delete_vehicle.php](../admin_delete_vehicle.php).

**Dependencies:** `vehicles` table via mysqli, `assets/` directory for thumbnails, `exif_imagetype()`/`random_bytes()` for upload validation/naming.

**Current status:** Implemented. Add/Edit validate that title/category are non-empty and price/units/seats are numeric, and validate real image content (JPEG/PNG/GIF/WEBP) before accepting an upload. Edit allows keeping the existing thumbnail if no new file is provided. Delete is a direct `DELETE FROM vehicles WHERE id = ?` reachable via a plain GET link with a `confirm()` JS dialog (no server-side confirmation step).

**Missing functionality:** Old thumbnail files are not deleted from `assets/` when a vehicle's image is replaced during edit. ~~Delete does not check for or warn about existing bookings tied to the vehicle before deleting...~~ — **resolved in the Admin Dashboard phase, Step 5 (2026-08-20):** the delete confirmation now discloses the cascade in its text ("...will also permanently delete every booking made for this vehicle and every transaction on those bookings"); this remains a client-side `confirm()`, not a server-side confirmation step, and the underlying `ON DELETE CASCADE` behavior itself (see [DATABASE.md](DATABASE.md)) was not changed. All three vehicle mutation endpoints now redirect back to `admin_vehicles.php` with a reachable success alert (previously redirected to `admin-dashboard.php`, which read none of the alert parameters — every add/edit/delete produced zero visible feedback).

---

## Admin User Management

**Description:** List, edit (name/email), and delete customer accounts.

**Files involved:** [admin_users.php](../admin_users.php) (list), [admin_update_user.php](../admin_update_user.php), [admin_delete_user.php](../admin_delete_user.php).

**Dependencies:** `users` table via mysqli.

**Current status:** Implemented. Lists users with `role != 'admin'`. Edit checks the new email isn't already used by a different user. Delete explicitly blocks an admin from deleting their own account (`$user_id == $_SESSION['admin_id']`), though this check compares a `users.id` value against an `admins.id` session value, which are different tables/ID spaces.

**Missing functionality:** No password reset/administrative override for a customer's password was found in the admin user management UI. Delete does not warn about the cascading deletion of that user's bookings/transactions (see Database doc).

---

## Admin Voucher Management

**Description:** Create, edit, and delete discount vouchers.

**Files involved:** [admin_vouchers.php](../admin_vouchers.php) (list, modals, and the create/update/delete handlers, all in one file switching on `$_POST['action']`).

**Dependencies:** `vouchers` table via mysqli.

**Current status:** Implemented. Displays usage as `usage_count / usage_limit` with a "Maxed Out" badge when the limit is reached. Create/update/delete all handled in the same file via AJAX.

**Missing functionality:** No way to reactivate/deactivate a voucher (`is_active`) from this UI was found — the create form doesn't expose it and the update handler's SQL does not include `is_active` in its `UPDATE` statement.

---

## Admin Booking Confirmation

**Description:** Admin approves a `pending` booking, which atomically reserves inventory and creates the payment record.

**Files involved:** [admin_confirm_booking.php](../admin_confirm_booking.php), "Confirm" buttons in `admin-dashboard.php` and `view-all-data.php`.

**Dependencies:** `bookings`/`vehicles`/`transactions` tables via mysqli.

**Current status:** Implemented with row-level locking (`SELECT ... FOR UPDATE`) on both the booking and vehicle rows inside a transaction. Rejects confirmation if no units are available or the booking is already confirmed. Decrements `vehicles.units_total` and flips `is_active` to 0 if that reaches zero. Inserts a `transactions` row only if one doesn't already exist for that booking.

**Missing functionality:** None found relative to its stated purpose — this is the most defensively-implemented write path in the codebase.

---

## Admin All-Transactions View

**Description:** Full list of all bookings across all customers, with actions to edit pickup/dropoff time, delete, or confirm.

**Files involved:** [view-all-data.php](../view-all-data.php), [update_booking_time.php](../update_booking_time.php), [delete_booking.php](../delete_booking.php).

**Dependencies:** `bookings`/`users`/`vehicles` tables via mysqli.

**Current status:** Implemented as a DataTables-powered table (`columnDefs` baseline applied in Step 4: numeric ID sort, non-sortable/non-searchable Actions column). Delete is still guarded against `status === 'active'` — no `bookings.status` value in the schema is literally `'active'` (the enum values are `pending`/`confirmed`/`completed`/`cancelled`), so this guard clause still cannot trigger for actual data; this is a business-rule bug explicitly out of scope for the Admin Dashboard phase, requiring separate approval per [BUGS.md](BUGS.md) item 8. ~~The page's inline JavaScript has a nested/malformed handler structure where the `.confirm-transaction` click handler is defined inside the `.delete-transaction` handler's...~~ **Resolved in the Admin Dashboard phase, Step 4 (2026-08-20)** — the registration was moved to a sibling statement inside `$(document).ready()`; Confirm now fires exactly one request on first interaction after a fresh page load, live-verified. Admin JavaScript for this page now lives in `js/admin.js` (Step 6).

**Missing functionality:** No pagination beyond what DataTables provides client-side (all rows are still fetched from the database in one query).

---

## Admin Account Settings

**Description:** The logged-in admin views and edits their own `admins` row: name, email, and password. Added in the Admin Dashboard phase, Step 8 (2026-08-20), as "Option C" of that step's design-decision analysis (Account Settings built now, Application Settings recorded as future work — see below).

**Files involved:** [admin_settings.php](../admin_settings.php) (the page), [admin_update_profile.php](../admin_update_profile.php) (new endpoint, a single file with a `$_POST['action']` switch: `update_profile` and `change_password`, matching [admin_vouchers.php](../admin_vouchers.php)'s existing single-file action-switch convention).

**Dependencies:** `admins` table via mysqli (`db_connect.php`), `$_SESSION['admin_id']`/`$_SESSION['admin_name']`.

**Current status:** Implemented, mirroring [update_profile.php](../update_profile.php)'s pattern for `users`, scoped to `admins`. POST-only; requires `$_SESSION['admin_id']` (403 JSON if absent); **derives the target row from the session only — no `id`/`admin_id` is ever read from the request body**, verified explicitly with a real second admin account (a temporary row inserted and removed for testing) posting a foreign id alongside valid session cookies, which the endpoint ignored entirely. Profile update validates non-empty name and a valid email, checks email uniqueness within `admins` only excluding the caller's own row (deliberately not checked against `users` — the two tables have no cross-table uniqueness constraint, confirmed unchanged by this step), updates via a prepared statement, and refreshes `$_SESSION['admin_name']` on success so the topbar reflects it without re-login. Password change verifies the current password with `password_verify()` before accepting anything, enforces the same 6-character minimum as `register.php`, and hashes with `password_hash()`. Verified with a full logout/login cycle: the old password stops working and the new one logs in successfully. `admins` has no `created_at` column (verified against the live schema via `DESCRIBE admins`, not just documentation), so no "member since" line was added, per the plan's conditional requirement.

**Known limitation, stated explicitly rather than silently addressed:** this endpoint has no CSRF protection, matching every other endpoint in the codebase — the project has no CSRF protection anywhere ([PROJECT_AUDIT.md](PROJECT_AUDIT.md), "Known Limitations"). Adding it here would be a project-wide decision, out of scope for this step.

**Missing functionality:** No "forgot password" recovery for a locked-out admin — this only covers a currently-logged-in admin changing their own password (see Admin Login, above). No email-change confirmation step (a new email takes effect immediately, same as the customer-side `update_profile.php`).

---

## Dark Mode

**Description:** A site-wide dark theme, toggleable independently of the OS setting, spanning all 14 pages (customer and admin). Added in the System Enhancements initiative, Steps 8-10 (2026-08-21), Feature 1.

**Files involved:** [css/styles.css](../css/styles.css) (dark token set under `[data-bs-theme="dark"]`, plus per-component dark-mode corrections), [js/theme.js](../js/theme.js) (new — toggle click handling, persistence, icon/ARIA state), `includes/client_navbar.php`/`includes/admin_topbar.php` (the toggle button markup), and a no-flash inline `<script>` (reads `localStorage`, falls back to `prefers-color-scheme`) as the literal first child of `<head>` on all 14 pages.

**Dependencies:** Bootstrap 5.3's native `data-bs-theme` colour-mode mechanism (Decision D3); `localStorage` for persistence, no database involvement (Decision D2 Option A).

**Current status:** Implemented. Theme is OS-seeded via `prefers-color-scheme` on first visit and pinned to an explicit choice only after the user's first manual toggle (Decision D1 Option C). Every text/background pairing on all 14 pages was measured live against WCAG 2.1 AA via an automated contrast scanner and re-verified per page after fixes — not sampled; see [CHANGELOG.md](../CHANGELOG.md)'s Step 9/10 entries for the full findings list and [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) for the token table. `admin-login.php` has no toggle button and doesn't load `js/theme.js` (it has no other JS either), but still respects a previously-set theme via the shared no-flash script. Reduced-motion preference is honoured automatically by a pre-existing, unrelated global CSS gate (`css/styles.css`) that already covered every animation/transition this feature added.

**Missing functionality:** No per-account persistence — a user's theme choice is tied to the browser via `localStorage`, not their account, so it doesn't follow them across devices (the explicitly deferred half of Decision D2).

---

## Consequential-Action Confirmations

**Description:** A confirmation dialog before every consequential action across the site — logout, password change, email change, booking confirmation, and admin booking-time edits — replacing every native browser `confirm()` call. Added in the System Enhancements initiative, Steps 1, 3, and 4 (2026-08-21), Feature 3.

**Files involved:** [js/confirm.js](../js/confirm.js) (new — the shared `window.PMSConfirm` module, moved out of `js/admin.js` where an earlier phase had built an admin-only version), with call sites added across [js/app.js](../js/app.js) (client: logout, booking confirm, password change, email change) and [js/admin.js](../js/admin.js) (admin: logout, password change, email change, booking-time edits).

**Dependencies:** Bootstrap's modal component (native nested-modal stacking, no custom focus-trap code needed — confirmed empirically, not assumed).

**Current status:** Implemented at 8 call sites (of a reviewed 27 candidate actions — 8 included, 19 explicitly excluded as not consequential enough to warrant a confirmation gate, Decision D5). Two of the eight are nested confirmations (a confirm dialog opening on top of an already-open modal): booking confirmation over `#bookingModal`, and admin booking-time edits over `#editTransactionModal`. Both were tested live in Step 11 via real interaction (not just inspected) — correct backdrop stacking, focus moved into the confirmation dialog on open, and correctly returned to the underlying modal (not lost to `<body>`) on cancel. See [COMPONENT_LIBRARY.md](COMPONENT_LIBRARY.md) §10 for the full call-site list and the dialog's exact markup/API.

**Missing functionality:** None relative to Decision D5's scope. Simple error/validation messages (e.g. "You must be at least 18 years old to rent") still use native `alert()`, unchanged — those were never in scope for this feature, which is specifically about confirming actions before they happen, not about how errors are displayed after the fact (see [BUGS.md](BUGS.md) for the broader `alert()` inventory, not itself a tracked bug).

---

## Privacy Policy Page

**Description:** A dedicated, linkable privacy policy page satisfying RA 10173 (Data Privacy Act of the Philippines) disclosure requirements — what's collected, why, retention, third-party sharing, security measures, and contact information. Added in the System Enhancements initiative, Step 6 (2026-08-21), Feature 4, Decision D7 Option A.

**Files involved:** [privacy.php](../privacy.php) (new), `includes/client_footer.php` (new footer link).

**Dependencies:** None beyond the shared client page shell (`includes/client_navbar.php`/`client_footer.php`/`auth_modals.php`).

**Current status:** Implemented as a standalone page (not a modal), authored with policy text supplied directly by the user rather than drafted speculatively. Eight sections: Introduction, Information We Collect, Purpose of Collection, Data Retention, Data Sharing & Third Parties, Security Measures, Your Rights, Contact. The Contact section names a placeholder project data representative, group, institution, and email, explicitly marked as placeholders pending real values. The Data Retention section deliberately does not state a fixed period, since the project has not finalized one — an explicit correction made during drafting rather than inventing a plausible-sounding number.

**Missing functionality:** None relative to its stated purpose as a disclosure page. It does not itself collect or record anything — see Privacy Consent Checkbox, below, for the actual consent-capture mechanism.

---

## Privacy Consent Checkbox (Signup)

**Description:** A required, unticked-by-default checkbox on the signup form, gating account creation on explicit consent to the data practices described in the Privacy Policy page, per RA 10173/NPC guidance against pre-ticked consent boxes. Added in the System Enhancements initiative, Step 7 (2026-08-21), Feature 4, Decision D7.

**Files involved:** `includes/auth_modals.php` (`#signupPrivacyConsent`, inside `#signupModal`), [js/app.js](../js/app.js) (the `AuthValidation.rules.checked()` rule builder and the `registerUser()` request payload), [register.php](../register.php) (server-side re-validation).

**Dependencies:** `privacy.php` (linked from the checkbox's label).

**Current status:** Implemented with both client- and server-side enforcement. Client-side: a dedicated validation rule (checkboxes can't be validated via `.val()` like other inputs, since it always returns `"on"` regardless of checked state — worked around with a closure-based rule reading `.is(':checked')` directly instead of extending the shared pipeline's assumption). Server-side, and the actual enforcement boundary: `register.php` reads `privacy_consent` from the request body and rejects with `400 {"error": "You must agree to the Privacy Policy to create an account."}` before any database work if it's missing or falsy — verified live via direct `fetch()` calls bypassing the UI entirely (400 without consent, 400 with `false`, 200 with `true`). This is a **breaking change** for any future API consumer (e.g. the planned mobile app) that doesn't yet send this field — flagged in [API.md](API.md).

**Missing functionality (identified future work, Decision D8 Option A "now," B/C deferred):** consent is **not currently persisted as its own record** — there is no timestamp column or versioned consent table capturing *when* a user agreed and to *which version* of the policy text. `PRIVACY_POLICY_VERSION`/`PRIVACY_POLICY_LAST_UPDATED` constants exist on `privacy.php` itself (so the policy's own version is tracked), but nothing links a given user's account to "I agreed to version X on date Y." If this project needs to demonstrate consent provenance later (an audit, a dispute, or simply tightening RA 10173 compliance further), the schema work required is: a new `user_consents` table (or a pair of columns on `users`) recording `user_id`, `policy_version`, and `consented_at`, written by `register.php` at the same point it currently does the pass/fail check. Decision D8 explicitly deferred this rather than building it speculatively — see [SYSTEM_ENHANCEMENTS_IMPLEMENTATION_PLAN.md](SYSTEM_ENHANCEMENTS_IMPLEMENTATION_PLAN.md)'s Decision Gate.

---

## Application Settings (Identified Future Work, Not Built)

**Description:** Business-level configuration — default rental terms, minimum age, cancellation-refund thresholds, contact details — currently hardcoded across `reserve.php`, `cancel_booking.php`, and elsewhere, editable by an admin instead of requiring a code change.

**Status:** **Not built.** Considered and explicitly rejected for the Admin Dashboard phase as "Option B" of Step 8's design decision, in favor of Option C (Admin Account Settings now, this recorded as future work). See [ADMIN_DASHBOARD_IMPLEMENTATION_PLAN.md](ADMIN_DASHBOARD_IMPLEMENTATION_PLAN.md) Step 8 for the full option comparison.

**Scope this would require, if built:** a **new `settings` table** (schema change); a new authenticated admin endpoint to read/write it; and — the larger cost — **refactoring every customer-facing consumer of the currently-hardcoded values** (`reserve.php`, `cancel_booking.php`, and any other file found to hardcode a business rule) to read from that table instead. [PROJECT_AUDIT.md](PROJECT_AUDIT.md) confirms no configuration mechanism of any kind exists today to build on. This is a schema change plus a cross-cutting refactor of customer-facing business logic — substantially outside a UI phase's remit, per [CLAUDE.md](../CLAUDE.md)'s "Backend modifications should only be suggested unless explicitly requested."

---

*This document reflects direct inspection of every PHP endpoint and its calling JavaScript/HTML. No functionality is inferred beyond what the code demonstrably does.*
