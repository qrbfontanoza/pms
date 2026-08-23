# PMS Car Rental — API Planning Document

This is a **planning document**, not an implementation. Sections describing the current system state are based on direct source inspection and are marked accordingly. Sections proposing future work are clearly labeled as candidates/proposals, not decisions — nothing here has been built, and CLAUDE.md's workflow (Understand → Analyze → Explain → Plan → Implement → Test → Document) means implementation would follow only after separate review and approval.

---

## Current Internal Communication

*(Verified from source — this describes what exists today, not a proposal.)*

There is no formal API layer today. Communication between the browser and the server happens two ways, both same-origin and both undocumented as a formal contract beyond the code itself:

**1. Full-page requests** — standard browser navigation to a `.php` file, which returns a complete HTML document (e.g. `GET vehicles.php`, `GET transactions.php`). No JSON is involved; PHP renders HTML directly using session state and DB query results.

**2. AJAX/JSON endpoints** — jQuery `fetch()`/`$.ajax()` calls from page-embedded JavaScript to sibling `.php` files that return `Content-Type: application/json`. Confirmed examples: `login.php`, `register.php`, `logout.php`, `me.php`, `check_login.php`, `reserve.php`, `reserve_preview.php`, `cancel_booking.php`, `return_early.php`, `apply_voucher.php`, `get_vouchers.php`, `save_message.php`, `forgot_password.php`, `reset_password.php`, `change_password.php`, and all `admin_*.php` action scripts.

These JSON endpoints are **not a designed API** — each was written independently for its one calling page, with no shared response envelope (some return `{success, ...}`, some `{error, ...}`, some both in the same file — see [BUGS.md](BUGS.md)), no versioning, no consistent status-code discipline, and no API documentation beyond the code itself. Authentication for all of them is the browser's PHP session cookie — there is no token, header, or key-based auth anywhere in the current code.

**Two endpoints are the one exception** — they were written with a different consumer in mind: [api_vehicles.php](../api_vehicles.php) (comment: *"JSON vehicle listing/detail for API consumers (e.g. the mobile app)"*) and [api_my_bookings.php](../api_my_bookings.php). These are structurally identical to the other JSON endpoints (same session-cookie auth, same ad hoc response shape) but are named and commented as forward-looking API surface. [PMS.postman_collection.json](../PMS.postman_collection.json) documents a broader set of these endpoints as "the JSON API surface used by both the web front end and (eventually) the mobile app," confirming intent but not a built mobile client — no mobile app source exists in this repository.

---

## Future API Candidates

*(Proposal — for consideration, not a commitment or an implementation.)*

Given the existing intent signal (the Postman collection description and the `api_vehicles.php` comment), the following areas are natural candidates if a formal API is built out, ranked by how directly they map to functionality that already exists and is stable today:

1. **Vehicle catalog** — already has a start (`api_vehicles.php`). Read-only, no auth-sensitive data, lowest risk to formalize first.
2. **Customer auth** (register/login/logout/session-check/password reset) — currently four-plus separate endpoints (`register.php`, `login.php`, `me.php`, `check_login.php`, `forgot_password.php`, `reset_password.php`, `change_password.php`) that would need consolidating into a coherent auth surface for a non-browser client, since session cookies are impractical for a native mobile app (see Authentication Requirements below).
3. **Booking lifecycle** (preview → create → cancel → return-early → view history) — the most business-critical flow; already has a mobile-oriented endpoint (`api_my_bookings.php`) for the read side, but no equivalent for creating/cancelling/returning a booking. This is the highest-value but highest-risk candidate, since it involves payment amounts, inventory locking, and voucher usage — the transactional logic currently living in `reserve.php`, `cancel_booking.php`, `return_early.php`, and `admin_confirm_booking.php`.
4. **Vouchers** — read (`get_vouchers.php`) and apply (`apply_voucher.php`) already exist as separate, somewhat overlapping endpoints (see [BUGS.md](BUGS.md), Duplicate Code); a formal API would need to pick one canonical voucher-application path rather than the two that exist today.
5. **Receipts/transaction history** — `receipt.php` currently returns HTML only; a JSON equivalent would be needed for a mobile client to render its own receipt view rather than opening a web page.
6. **Admin operations** — vehicle/user/voucher/booking management currently exist only as session-cookie-gated, browser-form-driven endpoints under `admin_*.php`; not a near-term candidate for external API exposure unless an admin mobile/desktop client is planned, given the sensitivity of these operations.

Contact-form submission (`save_message.php`) and static content (FAQ, About) are not flagged as API candidates — they have no evident need for non-browser access.

---

## Endpoints That Should Exist

*(Proposal only — describing a possible shape for a future, versioned API. No routes, files, or code have been created. This is a planning sketch to support a future implementation decision, not a spec ready to build from.)*

If a formal API were introduced, it would need a versioned, consistent path structure distinct from the current flat root-level `*.php` layout — for example, a `/api/v1/` prefix, though the actual routing mechanism (rewrite rules vs. a router library) is an implementation decision outside the scope of this document.

Candidate endpoint groupings, mapped to functionality that already exists in some form today:

**Auth**
- `POST /api/v1/auth/register` — maps to today's `register.php`. **Breaking change, System Enhancements initiative Step 7 (2026-08-21):** `register.php` now requires a truthy `privacy_consent` field in the request body and returns `400` with `{"error": "You must agree to the Privacy Policy to create an account."}` if it is missing or falsy. Any client calling this endpoint directly — including a future mobile client — must send this field. See [SYSTEM_ENHANCEMENTS_ANALYSIS.md](SYSTEM_ENHANCEMENTS_ANALYSIS.md) §4 and [PMS.postman_collection.json](../PMS.postman_collection.json), which describes this collection as used by both the web front end and eventually the mobile app.
- `POST /api/v1/auth/login` — maps to today's `login.php`; would need to return a token instead of setting a cookie for non-browser clients
- `POST /api/v1/auth/logout` — maps to today's `logout.php`
- `GET /api/v1/auth/me` — maps to today's `me.php`/`check_login.php` (would consolidate the current duplication)
- `POST /api/v1/auth/password/forgot` — maps to today's `forgot_password.php`
- `POST /api/v1/auth/password/reset` — maps to today's `reset_password.php`
- `POST /api/v1/auth/password/change` — maps to today's `change_password.php`

**Vehicles**
- `GET /api/v1/vehicles` — maps to today's `api_vehicles.php` (list/category-filter mode)
- `GET /api/v1/vehicles/{id}` — maps to today's `api_vehicles.php` (single-vehicle mode)

**Bookings**
- `GET /api/v1/bookings` — maps to today's `api_my_bookings.php`, though that endpoint currently excludes `completed`/`cancelled` bookings, which a full history endpoint likely should include
- `POST /api/v1/bookings/preview` — maps to today's `reserve_preview.php`
- `POST /api/v1/bookings` — maps to today's `reserve.php`
- `POST /api/v1/bookings/{id}/cancel` — maps to today's `cancel_booking.php`
- `POST /api/v1/bookings/{id}/return-early` — maps to today's `return_early.php`
- `GET /api/v1/bookings/{id}/receipt` — would need to be newly built as a JSON equivalent of today's HTML-only `receipt.php`

**Vouchers**
- `GET /api/v1/vouchers` — maps to today's `get_vouchers.php`
- `POST /api/v1/vouchers/apply` — would consolidate today's two overlapping code paths (voucher logic inside `reserve.php`/`reserve_preview.php`, and the separate `apply_voucher.php`)

**Messages**
- `POST /api/v1/messages` — maps to today's `save_message.php`

This list is a mapping exercise showing which existing endpoints correspond to which future surface — it is explicitly not a recommendation to build all of it at once, and does not address versioning strategy, deprecation of the current endpoints, or how the existing browser-facing pages would migrate to consume a new API rather than querying the database directly.

---

## Data Models

*(The "Current shape" rows are verified directly from response bodies in the code. The "Notes for a formal model" rows are proposal-level observations about what a designed API would need to resolve — not implemented changes.)*

### User
- **Current shape** (from `me.php`/`login.php`): `{ id, name, email, role }` — password never included.
- **Underlying table** (from [DATABASE.md](DATABASE.md)): `users.id, name, email, password, role, created_at` (+ `reset_code`, `reset_code_expires`, and inconsistently-present `license_path`).
- **Notes for a formal model:** `role` is a free-text `varchar`, not a constrained enum in the schema; a formal API would need to decide whether to expose it as-is or normalize it. `license_path` is written by `register.php` but not read back by any endpoint inspected — a formal user model would need to decide whether to surface it.

### Vehicle
- **Current shape** (from `api_vehicles.php`): full row from `vehicles` plus a computed `available_units` field (not a stored column).
- **Underlying table:** `vehicles.id, title, category, seats, fuel, transmission, price_per_day, units_total, is_active, thumbnail, created_at, slug`.
- **Notes for a formal model:** `thumbnail` is currently a bare filename requiring the client to know the `assets/` base path convention; a formal API would need an absolute or fully-qualified URL instead. `slug` exists in the schema but was not found populated or read by any query inspected.

### Booking
- **Current shape** (from `reserve.php`'s success response): `{ success, booking_ref, total, amount_paid, change, days, rate, subtotal, discount, rental_date, return_date, pickup_time, dropoff_time, vehicle_name, voucher_code, booking_id }` — note this response shape differs from `api_my_bookings.php`'s shape: `{ id, booking_ref, vehicle_id, vehicle_title, status, rental_date, return_date, total_amount }`. The two do not share a common structure today.
- **Underlying table:** `bookings.id, user_id, vehicle_id, booking_ref, rental_date, return_date, pickup_time, dropoff_time, days, rate, discount, total_amount, contact_number, status, created_at, voucher_id, age, license_file, paid` (+ conditionally `actual_return_date`, `early_return` if present).
- **Notes for a formal model:** a formal API would need one canonical `Booking` representation instead of the two different shapes above; `status` values (`pending`/`confirmed`/`completed`/`cancelled`) would need to be the documented, stable contract, since [BUGS.md](BUGS.md) records existing code that incorrectly checks for a non-existent `'active'` value.

### Voucher
- **Current shape** (from `get_vouchers.php`): full row from `vouchers`, returned as a bare JSON array (not wrapped in `{success, ...}` like other endpoints).
- **Underlying table:** `vouchers.id, code, discount_amount, discount_pct, is_active, single_use, usage_count, usage_limit, used_at, used_by, created_at`.
- **Notes for a formal model:** `used_by`/`used_at` are single-value columns that get overwritten on each use of a multi-use voucher (documented in [DATABASE.md](DATABASE.md)) — a formal API exposing "who used this voucher" would need a real usage-log table behind it, which does not exist today.

### Transaction
- **Current shape:** not directly returned by any JSON endpoint inspected; only referenced server-side (`transactions.php` joins it in for `transaction_ref`) or rendered into HTML (`receipt.php`).
- **Underlying table:** `transactions.id, booking_id, transaction_ref, amount, paid_at` (+ `payment_status` from migration).
- **Notes for a formal model:** would need to be defined from scratch for an API, since no existing endpoint exposes it as JSON today.

### Message
- **Current shape:** not returned as JSON by any endpoint; only inserted (`save_message.php`) and rendered into admin HTML (`admin-dashboard.php`).
- **Underlying table:** `messages.id, name, email, message, created_at`.

---

## Authentication Requirements

*(Current state is verified. Future requirements are proposal-level, describing what would need to change to support a non-browser client — not a decision to build it.)*

**Current state, verified:**
- All customer-facing endpoints authenticate via PHP's native session (`$_SESSION['user']`), tied to a session cookie set by `session_start()`. There is no token, API key, or header-based credential anywhere in the codebase.
- Admin endpoints use a separate session key (`$_SESSION['admin_id']`) in the same underlying PHP session mechanism.
- `api_vehicles.php` requires no authentication at all (public data). `api_my_bookings.php` requires the same session cookie as the rest of the customer-facing site — i.e., it is not actually independently callable by a non-browser client without first establishing a cookie-based session through `login.php`.
- No CSRF tokens, no rate limiting, and no request-signing of any kind were found anywhere in the codebase (documented in [ARCHITECTURE.md](ARCHITECTURE.md) and [PROJECT_AUDIT.md](PROJECT_AUDIT.md)).

**What a formal API consumed by a non-browser client (e.g. a mobile app) would need, as a planning consideration:**
- A token-based authentication scheme (e.g. a bearer token issued at login) in place of, or alongside, the current session cookie — cookie-based sessions are workable for a WebView-style client but not for a native app making direct HTTP calls.
- A defined token lifetime/refresh strategy, which does not exist in any form today (the current session has no explicit expiry logic beyond PHP's default session garbage collection).
- A decision on whether admin operations would ever be exposed through the same API surface or kept entirely separate, given they currently use a distinct, separate session namespace with no shared guard logic.
- Rate limiting and CSRF/request-forgery protection for any state-changing endpoint (booking creation/cancellation, password reset, voucher application), none of which exist today.

None of the above has been designed in detail or implemented — this section exists to name what would need to be decided before implementation begins, per CLAUDE.md's requirement to plan before implementing.

---

*This document is planning material only. No API routes, authentication mechanisms, or endpoints described as "future" or "candidate" have been implemented. Sections describing current behavior reflect direct source inspection.*
