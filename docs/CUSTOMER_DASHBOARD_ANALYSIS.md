# Customer Dashboard Analysis

Analysis document for the Customer Dashboard phase (Phase 7 of [UI_IMPLEMENTATION_PLAN.md](UI_IMPLEMENTATION_PLAN.md)), prepared per [CLAUDE.md](../CLAUDE.md)'s workflow (Understand → Analyze → Explain → Plan → Implement → Test → Document). Based on direct inspection of [transactions.php](../transactions.php), [me.php](../me.php), [check_login.php](../check_login.php), [change_password.php](../change_password.php), [api_my_bookings.php](../api_my_bookings.php), [receipt.php](../receipt.php), [cancel_booking.php](../cancel_booking.php), [return_early.php](../return_early.php), [includes/client_navbar.php](../includes/client_navbar.php), [includes/client_footer.php](../includes/client_footer.php), [includes/auth_modals.php](../includes/auth_modals.php), [js/app.js](../js/app.js), [js/motion.js](../js/motion.js), [css/styles.css](../css/styles.css), and all documentation in `docs/`.

**Status:** Analysis only. No code has been modified. Implementation requires separate approval, one section at a time, per this project's established workflow.

---

## 1. Current Implementation Overview

### 1.1 Phase 7 Task Mapping — What Exists Today

[UI_IMPLEMENTATION_PLAN.md](UI_IMPLEMENTATION_PLAN.md) Phase 7 defines six tasks with no detail:

> Dashboard Cards, Bookings, Profile, Notifications, History, Responsive Tables

The following is what direct inspection found for each task. No assumptions were made based on filenames alone.

| Task | Real, Rendered Customer UI Exists Today? | Backend Support Exists? | Primary File(s) |
|---|---|---|---|
| **Dashboard Cards** | **Partial** — `transactions.php:95-108` renders two summary cards ("Active Rentals" count and "Completed Rentals" count). No dedicated dashboard page exists. | Yes — the booking query at `transactions.php:10-33` provides the data. | `transactions.php` |
| **Bookings** | **Yes** — `transactions.php:110-217` renders active/upcoming bookings with Cancel and Return Early actions. | Yes — `cancel_booking.php`, `return_early.php`, query at `transactions.php:10-33`. Also `api_my_bookings.php` (JSON, pending/confirmed only). | `transactions.php`, `cancel_booking.php`, `return_early.php` |
| **Profile** | **Not Started** — zero customer-facing UI for viewing or editing profile information exists anywhere in the project. | **Partial** — `me.php` returns `{id, name, email, role}` as JSON (read-only). `change_password.php` accepts current + new password (fully implemented). No endpoint exists for updating name or email from the customer side (`admin_update_user.php` exists but is admin-only). The `users` table stores `id`, `name`, `email`, `password`, `role`, `created_at`, and optionally `license_path` and `reset_code`/`reset_code_expires`. | `me.php`, `change_password.php` |
| **Notifications** | **Not Started** — zero notification UI, table, endpoint, or mechanism exists anywhere in the codebase. | **Not Started** — no `notifications` table, no notification-related query, no notification endpoint found. The `messages` table stores contact-form submissions (customer → admin), not system notifications to the customer. | None |
| **History** | **Yes** — `transactions.php:184-217` renders "Completed Rentals" (bookings whose `return_date` < today, excluding `status = 'completed'`). | Yes — same query as Bookings above. | `transactions.php` |
| **Responsive Tables** | **Not applicable on customer side** — no `<table>` element exists on any customer-facing page. `transactions.php` uses `.list-group`/`.list-group-item` for booking rows. Admin pages use DataTables (`admin_users.php`, `admin_vehicles.php`, `view-all-data.php`) — these are Phase 8 scope, not Phase 7. | N/A | N/A |

### 1.2 The `transactions.php` Investigation

`transactions.php` is the only customer-facing page that shows booking data. Direct inspection confirms:

- **It IS the "Bookings" task** — lines 110-181 render active/upcoming bookings with status badges, amounts, booking refs, and action buttons (Cancel, Return Early).
- **It IS the "History" task** — lines 184-217 render past bookings (return date < today) in a separate "Completed Rentals" section.
- **It partially IS the "Dashboard Cards" task** — lines 95-108 render two summary stat cards (active count, completed count).
- **"Bookings" and "History" are NOT separate pages** — they are two sections of the same `transactions.php` page, split by a PHP date comparison at line 41.

There is no separate `dashboard.php`, `profile.php`, `bookings.php`, `history.php`, or `notifications.php` file anywhere in the project root.

### 1.3 Customer Navbar — No Dashboard/Profile Links

The customer navbar ([includes/client_navbar.php](../includes/client_navbar.php)) contains five links: Home, Vehicles, FAQ, About Us, Transactions. There is no link to a dashboard, profile, account settings, or notifications page. The logged-in state dropdown (`js/app.js:1565-1573`) contains only a "Logout" item — no "My Profile", "My Dashboard", or "Settings" link exists.

---

## 2. Server-Side Data and Rules

### 2.1 Booking History Query (`transactions.php:10-33`)

```php
$stmt = $pdo->prepare("
  SELECT 
    b.id, b.status, b.booking_ref, b.rental_date, b.return_date,
    b.pickup_time, b.dropoff_time, b.days, b.rate, b.discount,
    b.total_amount, b.contact_number,
    v.title AS vehicle_title, t.transaction_ref
  FROM bookings b
  LEFT JOIN vehicles v ON b.vehicle_id = v.id
  LEFT JOIN transactions t ON t.booking_id = b.id
  WHERE b.user_id = ? AND b.status != 'completed'
  ORDER BY b.rental_date DESC
");
```

Key observations:
- Excludes `status = 'completed'` — bookings marked completed by `return_early.php` never appear on this page.
- The "Completed Rentals" section (lines 40-41) splits on `return_date < today`, not on `status`. A booking whose return date has passed but whose status is still `pending`/`confirmed`/`cancelled` appears under "Completed Rentals".
- No pagination — all matching bookings rendered in one page.
- `LEFT JOIN transactions t` can match multiple rows per booking (confirmation + return_early), potentially duplicating booking rows in the result set.

### 2.2 Session Check (`me.php:1-21`)

```php
echo json_encode([
  'logged_in' => true,
  'user' => [
    'id' => $user['id'],
    'name' => $user['name'],
    'email' => $user['email'],
    'role' => $user['role'] ?? 'user'
  ]
]);
```

Returns `id`, `name`, `email`, `role` — the only profile fields currently exposed via any endpoint. No `created_at`, `license_path`, or other user fields are returned.

### 2.3 Change Password (`change_password.php:1-45`)

Fully implemented: requires login (401 if not), validates current password via `password_verify()`, enforces 6-char minimum on new password, hashes with `password_hash()`. Returns JSON success/error. No customer-facing UI exists to call this endpoint.

### 2.4 Cancel Booking (`cancel_booking.php`)

Fully implemented with tiered refund calculation (100% if ≥24h before rental, 50% otherwise). Only allows cancelling `pending`/`confirmed` bookings owned by the requesting user. Increments `vehicles.units_total` back if booking was `confirmed`. All inside a PDO transaction.

### 2.5 Return Early (`return_early.php`)

Fully implemented: locks booking row, sets `status='completed'`, increments `units_total`, inserts `transactions` row with `payment_status='returned'`. Conditionally sets `actual_return_date`/`early_return` if columns exist.

### 2.6 API — My Bookings (`api_my_bookings.php`)

Returns JSON array of `pending`/`confirmed` bookings only. Does not include `completed` or `cancelled`. This is the mobile/API counterpart to the "active" section of `transactions.php` but returns a narrower result set.

### 2.7 Users Table — Profile Fields Available

From [DATABASE.md](DATABASE.md) and the migration scripts, the `users` table has:
- `id`, `name`, `email`, `password` — core fields
- `role` — `varchar(50)`, default `'user'`
- `created_at` — timestamp
- `license_path` — optional, added by `register.php`'s runtime `ALTER TABLE` if missing
- `reset_code`, `reset_code_expires` — password reset support (migration 02)

For a customer profile page, the editable fields would be: `name`, `email` (with uniqueness check), and password (via `change_password.php`). `license_path` is read-only display. `role` and `created_at` are informational.

**No customer-side endpoint exists for updating `name` or `email`.** `admin_update_user.php` handles this for admins but requires `$_SESSION['admin_id']`, not `$_SESSION['user']`.

---

## 3. Client-Side Current State

### 3.1 `transactions.php` — JavaScript (`transactions.php:322-435`)

Three inline functions handle the page's interactive features:

- **`showReturnEarlyModal(bookingId)`** (line 324) — sets hidden input, opens `#returnEarlyModal`
- **`showCancelModal(bookingId, vehicleTitle, rentalDate)`** (line 329) — sets hidden input, populates details, opens `#cancelBookingModal`
- **`confirmCancellation(btn)`** (line 338) — async, uses `PMSMotion.setButtonLoading()`, POSTs to `cancel_booking.php`, `alert()` on success/error, reloads page
- **`confirmReturnEarly(btn)`** (line 370) — async, uses `PMSMotion.setButtonLoading()`, POSTs to `return_early.php`, closes modal, shows receipt modal, reloads after 3s

Both confirm functions use `PMSMotion.setButtonLoading()` correctly. Both use `alert()` for error feedback and `window.location.reload()` for success.

### 3.2 Navbar Auth Rendering (`js/app.js:1558-1584`)

The logged-in dropdown renders only "Logout" — no profile, dashboard, or settings links:

```javascript
$area.append(`
  <div class="dropdown">
    <button class="btn btn-outline-primary rounded-pill dropdown-toggle" ...>
      Welcome, ${me.user.name.split(' ')[0]}!
    </button>
    <ul class="dropdown-menu dropdown-menu-end" ...>
      <li><a class="dropdown-item text-danger" href="#" id="logoutBtn">Logout</a></li>
    </ul>
  </div>
`);
```

### 3.3 Known JS Crash on `transactions.php`

Per [BUGS.md](BUGS.md) item 11: `js/app.js:51-57` has an unguarded `.datepicker()` call that crashes on `transactions.php` because that page does not load jQuery UI. This crash halts all subsequent `app.js` code, including `renderNavbarAuth()`, `AuthValidation`, and all Authentication-phase client-side functionality. **This means the navbar auth display, login/signup modals, and forgot-password flow are all broken on `transactions.php` specifically.** This is a pre-existing bug documented since 2026-08-08, not introduced by any phase.

---

## 4. Existing Strengths — Reusable Patterns

### 4.1 Shared Partials Architecture

All customer-facing pages use:
- `includes/client_navbar.php` — single navbar source
- `includes/client_footer.php` — single footer source
- `includes/auth_modals.php` — login/signup/forgot-password modals

Any new customer page (e.g., a profile page) can include these three partials and immediately have consistent navigation, footer, and auth modals.

### 4.2 `AuthValidation` Module (Phase 6)

Built during the Authentication phase in `js/app.js`. Provides:
- `blur`-triggered initial validation, `input`-triggered correction
- `.is-invalid`/`.invalid-feedback` per-field error states
- `aria-describedby` and `aria-invalid` ARIA wiring
- Full-form validation on submit
- Coexists with `showAuthError()` for server errors

**Reusability assessment for Profile:** A profile-edit form (name, email, current password for verification, new password) would need validation on similar field types (name non-empty, email format, password length). `AuthValidation`'s pattern is directly reusable — the same `blur`/`input` trigger system and `.is-invalid` wiring apply. The password-change portion shares the exact same rules as `change_password.php` already enforces (min 6 chars). **`AuthValidation` IS the second consumer of this module** — profile editing is a natural fit.

### 4.3 `PMSMotion` Module

- `PMSMotion.setButtonLoading($btn, true/false)` — already used on `transactions.php` for Cancel and Return Early actions. Will apply to any new AJAX actions (profile save, etc.).
- `initReveal()` / `data-reveal` / `data-reveal-stagger` — entrance animations, applicable to new dashboard content.
- `initModalFocus()` — auto-focuses first input when a modal opens, applicable to any new modals.

### 4.4 CSS Design Tokens

`css/styles.css:2-30` defines the complete design token set (`--primary`, `--secondary`, `--accent`, `--background`, `--border`, motion tokens). Any new UI must use these, not hardcoded colors.

### 4.5 Bootstrap 5 Patterns Already Established

- Summary stat cards: `transactions.php:95-108` — `.p-3.bg-white.rounded-3.shadow-sm.text-center`
- List-group booking rows: `transactions.php:132-179` — `.list-group-item` with `d-flex justify-content-between`
- Confirmation modals: Return Early, Cancel Booking — standard Bootstrap 5 modal structure
- Form patterns: `.form-control`, `.input-group.has-validation`, `.invalid-feedback` (from Authentication phase)
- Responsive padding: `p-2 p-sm-4` on modal content
- Glassmorphism: `.glassmorph` on auth modals

---

## 5. Existing Problems

### Critical

1. **No Profile UI exists anywhere.** The `change_password.php` endpoint is fully implemented but has zero customer-facing UI. A customer cannot view their account information, change their name, change their email, or change their password from any page. This is the same pattern as Forgot Password was in Phase 6 — complete backend, zero frontend. Additionally, no endpoint exists for customers to update their own name or email (only `admin_update_user.php` does this, and it requires admin auth). **A new endpoint must be created for customer self-service profile updates**, making this the first Phase 7 task requiring backend work.

2. **No Notifications system exists at any layer.** No database table, no backend endpoint, no frontend UI, no notification trigger mechanism. Phase 7's plan lists "Notifications" as a task, but there is literally nothing to modernize — the entire feature must be designed and built from scratch (database, backend, frontend). This is a scope question: is Phase 7 the right place to build a notification system, or should this task be deferred/descoped?

### High

3. **`transactions.php` is a single page doing everything.** Dashboard cards, active bookings, and booking history are all in one page with no separation. The Phase 7 tasks imply these are distinct features ("Dashboard Cards" vs. "Bookings" vs. "History"), but they currently share a single query and a single PHP file. Restructuring into a proper dashboard layout requires careful preservation of the existing booking query, Cancel/Return Early functionality, and the page's modals.

4. **The `js/app.js:51` crash makes `transactions.php` partially non-functional.** The `.datepicker()` TypeError halts all subsequent `app.js` code on this page. This means: navbar auth display is broken (no Welcome dropdown, no login/signup buttons), `AuthValidation` is unavailable, `showAuthError()` is unavailable, and all Authentication-phase functionality is dead. **This pre-existing bug MUST be fixed before any Phase 7 UI work on this page can function correctly.** Fixing it is a prerequisite, not an optional improvement.

5. **Cancel/Return Early buttons shown for already-cancelled bookings.** Per [BUGS.md](BUGS.md) item 10: action buttons are rendered based on time-computed status (Upcoming/Active), not the booking's actual database `status`. A cancelled booking with a future rental date still shows a "Cancel Booking" button. Server-side rejection prevents data corruption, but the UI is misleading.

6. **Booking query excludes `status = 'completed'` bookings entirely.** Bookings that were properly completed via `return_early.php` (which sets `status = 'completed'`) never appear on `transactions.php` — they vanish from the customer's view. The "Completed Rentals" section only shows bookings whose return date has passed while their status is still `pending`/`confirmed`/`cancelled`.

### Medium

7. **No pagination on booking history.** All bookings render in one page. A customer with many bookings has no way to search, filter, or paginate.

8. **`alert()` used for error/success feedback** in `confirmCancellation()` and `confirmReturnEarly()`. This is inconsistent with the project's established pattern (Phase 8's `showAuthError()`, Bootstrap toasts/alerts).

9. **Two duplicate `renderNavbarAuth()` functions** exist in `js/app.js` — one at line 436 (dead, uses `const user = null`) and the live one at line 1558 (async, calls `getMe()`). The dead copy is called first (line 463) and renders login/signup buttons (since `user` is always `null`); then the live copy runs later (line 1584) and overwrites with the correct state. On pages where the line-51 crash occurs (`transactions.php`), neither runs.

10. **No user dropdown menu links.** The logged-in navbar dropdown contains only "Logout". No "My Profile", "My Bookings", "Dashboard", or "Account Settings" link exists. A customer who wants to view their transactions must know to click the "Transactions" nav link.

### Low

11. **Summary cards are minimal.** Two plain cards showing only counts. No icons, no trends, no additional metrics (total spent, next upcoming rental, etc.).

12. **License Preview modal markup is orphaned.** `transactions.php:249-265` contains a License Preview modal with `<!-- license preview removed -->` comments at three former trigger locations. The modal markup remains but is unreachable.

---

## 6. Documentation Conflicts (flagged, not silently resolved)

1. **[DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) §5 (Transactions)** describes `transactions.php` accurately, including the time-based vs. status-based action-button mismatch (confirmed still present, [BUGS.md](BUGS.md) item 10 unchanged). However, it states **"No `<table>` markup exists on any client-facing page"** (§7) — this is still accurate and conflicts with Phase 7's "Responsive Tables" task name. There are no customer-facing tables to make responsive.

2. **[COMPONENT_LIBRARY.md](COMPONENT_LIBRARY.md) §6 (Booking Cards)** lists four separate booking-receipt implementations. This is still accurate for `transactions.php`'s list-group pattern and the return-receipt modal. Relevant because Phase 7 will likely restructure how bookings are displayed.

3. **[COMPONENT_LIBRARY.md](COMPONENT_LIBRARY.md) §7 (Tables)** confirms: "Not found. No `<table>` markup exists on any client-facing page." The Phase 7 task "Responsive Tables" either refers to (a) making the existing `.list-group` booking display responsive, (b) converting booking display to a table and making it responsive, or (c) is a misnomer. This must be resolved before implementation.

4. **[FEATURES.md](FEATURES.md) "Transaction / Booking History"** documents the query-exclusion issue (`status != 'completed'`) and the time-based bucketing. It notes "No filtering/search/pagination for customers with many bookings" as missing functionality — matching this analysis's finding #7.

5. **[FEATURES.md](FEATURES.md) "Change Password"** confirms the endpoint is fully implemented with "Missing functionality: None found relative to its stated purpose" — but does not mention the absence of any UI to call it.

6. **[PROJECT_AUDIT.md](PROJECT_AUDIT.md) "Customer Flow"** lists the flow as Home → Vehicles → Booking → Transactions, with no mention of a dashboard, profile, or notifications page — confirming these do not exist in any form.

7. **[BUGS.md](BUGS.md) item 11** documents the `js/app.js:51` crash affecting `transactions.php`. This analysis's finding #4 (High severity) is the same bug, not a new discovery. The fix proposed in BUGS.md (guard `typeof $.fn.datepicker === 'function'` or add the missing scripts to `transactions.php`) has been documented since 2026-08-08 and remains unimplemented across all subsequent phases.

---

## 7. Recommended Scope Structure

Based on direct inspection, the six Phase 7 tasks map to the following real scope:

### Scope Assessment

| Phase 7 Task | Real Scope | Recommended Approach |
|---|---|---|
| **Dashboard Cards** | Enhance the existing 2 summary cards on `transactions.php` with icons, additional metrics, and better visual design | Enhancement of existing UI |
| **Bookings** | Improve the active/upcoming bookings section — fix action-button status mismatch, replace `alert()` with proper feedback, improve responsive layout | Enhancement of existing UI |
| **Profile** | **New build** — no UI exists, and a new backend endpoint is needed for customer self-service name/email updates. UI must expose `change_password.php` and the new profile-update endpoint. | New page or modal + new endpoint |
| **Notifications** | **Entirely new system** — no database table, no backend, no frontend. Requires full-stack design: schema, triggers, endpoints, UI. | Design decision needed: build or defer? |
| **History** | Improve the completed-rentals section — fix the `status != 'completed'` query exclusion, add receipt viewing, improve layout | Enhancement of existing UI |
| **Responsive Tables** | **Misnomer** — no customer-facing tables exist. Reinterpret as: ensure booking displays (list-group) are fully responsive at all breakpoints, or introduce a table-based layout if appropriate | Responsive improvements to existing list-group display |

### Proposed Implementation Steps

The step count is driven by the real scope, not forced to match Authentication's six.

**Step 1: Fix `js/app.js` Crash on `transactions.php` (Prerequisite)**
Fix [BUGS.md](BUGS.md) item 11 — the `.datepicker()` crash at `js/app.js:51`. Without this fix, no Phase 7 client-side work on `transactions.php` functions correctly (navbar auth, `AuthValidation`, `showAuthError()`, all dead). This is a prerequisite for every subsequent step. Narrowly scoped: guard the two plugin calls, no other changes.

**Step 2: Dashboard Layout & Enhanced Cards**
Transform `transactions.php` from a flat page into a proper customer dashboard layout. Enhance the two summary cards with icons and better design. Add additional metrics where data is already available (e.g., total spent, next upcoming rental date). Establish the visual framework that Steps 3-5 build on.

**Step 3: Bookings & History Improvements**
Fix the action-button status mismatch ([BUGS.md](BUGS.md) item 10). Fix the `status != 'completed'` query exclusion so properly-completed bookings appear in History. Replace `alert()` success/error with proper Bootstrap feedback. Improve responsive layout of booking rows. Add receipt viewing from completed bookings. Clean up orphaned License Preview modal.

**Step 4: Customer Profile**
Build a new profile section (either a dedicated page or an in-page section/modal accessible from the dashboard and the navbar dropdown). Create a new customer-side profile-update endpoint (name, email with uniqueness check). Wire `change_password.php` to a UI. Display account information (name, email, member since, license if uploaded). Add profile link to navbar dropdown.

**Step 5: Notifications (Design Decision Required)**
This step requires a design decision before it can be scoped:
- **Option A — Full notification system:** Design a `notifications` table, build backend triggers (booking confirmed, booking cancelled, booking reminder), build a notification bell UI in the navbar, build a notifications panel/page. This is a significant full-stack feature.
- **Option B — Booking status alerts:** Lighter alternative — show contextual alerts on the dashboard for recent booking status changes (e.g., "Your booking for Toyota Fortuner was confirmed"). No persistent notification table; derived from booking status + timestamps. Frontend-only.
- **Option C — Defer to a later phase.** Notifications are a cross-cutting concern that affects navbar, database schema, and potentially email/push. It may be better scoped as its own phase rather than one step within Phase 7.

**Step 6: Responsive & Accessibility Pass**
Ensure all customer dashboard content renders correctly at 375px, 768px, 992px, 1400px. Verify keyboard navigation. Verify ARIA attributes on all new components. Address the "Responsive Tables" task by ensuring booking list-group displays work at all breakpoints (stacking, text truncation, touch targets). This is where the "Responsive Tables" task actually lives — applied to whatever display format Steps 2-3 established.

**Step 7: Final Review & Documentation**
Full regression pass, documentation updates, changelog entry.

---

## 8. Section-by-Section Analysis

### 8.1 Dashboard Cards

- **Purpose:** Summary metrics giving the customer an at-a-glance view of their rental activity.
- **Existing content reusable:** The two stat cards at `transactions.php:95-108` are functional but visually plain — `<div class="p-3 bg-white rounded-3 shadow-sm text-center">` with an `<h6>` label and an `.h2` count.
- **Components to reuse:** Design token variables, `.shadow-sm.rounded-3` card pattern, Bootstrap grid.
- **New components needed:** Enhanced card design with icons (Font Awesome), additional metrics derived from existing query data (total amount spent, next upcoming rental date/vehicle, booking status breakdown).
- **Data requirements:** All data is already available from the existing query at `transactions.php:10-33`. Additional metrics can be computed in PHP from the `$active` and `$completed` arrays without new database queries.
- **Responsive behavior:** Currently two `.col-md-6` cards. Should expand to 3-4 cards in a responsive grid: `col-sm-6 col-lg-3` or similar.
- **Accessibility:** Cards should have semantic headings and `aria-label` or descriptive text so screen readers understand the metric.

### 8.2 Bookings (Active/Upcoming)

- **Purpose:** Show the customer their current and future rentals with contextual actions.
- **Existing content reusable:** The full active-bookings section at `transactions.php:110-181` is functional. `.list-group-item` rows with `d-flex justify-content-between`, dual badges (time-based + status), amounts, booking refs, and action buttons.
- **Components to reuse:** `PMSMotion.setButtonLoading()` (already used), `confirmCancellation()` / `confirmReturnEarly()` async handlers, the three action modals (Return Early, Cancel, Return Receipt).
- **New components needed:** Fix for action-button rendering to check actual `status` (not just time-based bucket). Proper Bootstrap alert/toast feedback to replace `alert()`. Potentially a "View Receipt" link per booking.
- **Data requirements:** Existing query, but needs the `status != 'completed'` exclusion reviewed — bookings completed via `return_early.php` should appear somewhere (either here as "Completed" or in the History section).
- **Responsive behavior:** Current `d-flex justify-content-between` does not wrap cleanly at narrow widths when a booking has a long vehicle title + price + two badges. Needs responsive stacking.
- **Accessibility:** Action buttons use inline `onclick` handlers — functional but should have `aria-label` describing the action and target (e.g., "Cancel booking for Toyota Fortuner").

### 8.3 Profile

- **Purpose:** Allow customers to view and edit their account information.
- **Existing content reusable:** None — no UI exists. The only profile-adjacent UI is the Welcome dropdown in the navbar, which shows the first name.
- **Components to reuse:** `AuthValidation` (for form field validation on name/email/password), `PMSMotion.setButtonLoading()` (for save actions), `showAuthError()` (for server error feedback), `.glassmorph` modal styling (if profile uses a modal), shared partials (`client_navbar.php`, `client_footer.php`, `auth_modals.php`), Bootstrap `.form-control` / `.input-group.has-validation` patterns.
- **New components needed:**
  - **Profile display section:** Name, email, member since, license (if uploaded), account role.
  - **Profile edit form:** Name (text), email (email, with uniqueness validation), optionally license upload.
  - **Change password form:** Current password, new password, confirm new password (wired to `change_password.php`).
  - **New backend endpoint:** `update_profile.php` — accepts `name` and `email`, validates email uniqueness (excluding current user), requires `$_SESSION['user']`, updates `users` table. Mirrors `admin_update_user.php`'s logic but for self-service.
- **Data requirements:** `me.php` provides current profile data. A new query in `update_profile.php` must check email uniqueness: `SELECT id FROM users WHERE email = ? AND id != ?`.
- **Responsive behavior:** Standard form layout — single-column on mobile, potentially two-column on desktop.
- **Accessibility:** `AuthValidation`'s `aria-describedby` / `aria-invalid` pattern applies directly. Password change form needs the same "Minimum 6 characters" helper text used in signup.

### 8.4 Notifications

- **Purpose:** Inform customers of booking status changes and other relevant updates.
- **Existing content reusable:** None.
- **Components to reuse:** Navbar dropdown pattern (for a notification bell/badge), Bootstrap badge (for unread count), `.list-group` (for notification list).
- **New components needed:** Depends entirely on which scope option is chosen (see §7, Step 5). At minimum for Option B (booking status alerts): contextual alert banners on the dashboard derived from recent booking status changes.
- **Data requirements:** Option A requires a new `notifications` table with columns like `id`, `user_id`, `type`, `message`, `is_read`, `created_at`, and corresponding CRUD endpoints. Option B requires no new table — just PHP logic comparing booking timestamps to "last viewed" time.
- **Responsive behavior:** Notification list/panel must work at mobile widths.
- **Accessibility:** Notification badge needs `aria-label` announcing count. List items need clear text alternatives.

### 8.5 History (Completed Rentals)

- **Purpose:** Show the customer their past rental history.
- **Existing content reusable:** The completed-rentals section at `transactions.php:184-217` is functional but basic. `.list-group-item` rows with vehicle title, status badge, dates, and amount.
- **Components to reuse:** `.list-group-item` pattern, badge styling.
- **New components needed:** Receipt viewing from history items (currently only active bookings have action buttons). Receipt link should open `receipt.php?id=X` or the in-page receipt modal. Potentially filtering/search for long histories.
- **Data requirements:** The query must be fixed to include `status = 'completed'` bookings. Currently `WHERE b.status != 'completed'` excludes them entirely. The fix is to either remove this exclusion and handle all statuses in the PHP bucketing logic, or run a second query for completed bookings.
- **Responsive behavior:** Same as Bookings — current `d-flex` doesn't wrap cleanly at narrow widths.
- **Accessibility:** Completed bookings have no action buttons (which is correct), but should have a "View Receipt" link with proper accessible label.

### 8.6 Responsive Tables (Reinterpreted)

- **Purpose:** Ensure all customer-facing data displays are fully responsive.
- **Existing content reusable:** The `.list-group` pattern on `transactions.php` is Bootstrap's responsive-by-default list component. The current responsive problem is the inner `d-flex justify-content-between` layout within each list item, which doesn't stack at narrow widths.
- **Components to reuse:** Bootstrap responsive utilities (`d-flex flex-column flex-md-row`, `text-truncate`, `order-*`).
- **New components needed:** Responsive breakpoint classes on booking list items. Possibly a card-based layout on mobile instead of list-group. If dashboard cards or profile sections use any tabular data, ensure it uses `table-responsive` or a responsive alternative.
- **Data requirements:** None — pure CSS/markup concern.
- **Responsive behavior:** This IS the task. Test at 375px, 768px, 992px, 1400px. Booking items must stack label/value pairs vertically on mobile. Badges must not overflow. Action buttons must maintain 44×44px touch targets.
- **Accessibility:** Touch targets ≥44px, no horizontal scroll, text remains readable at mobile widths.

---

## 9. Component Reuse Table

| Component | Source | Reuse in Phase 7 | Notes |
|---|---|---|---|
| `AuthValidation` | Phase 6 (`js/app.js`) | **Yes — Profile edit form** | Name/email validation, password change form. Same `blur`/`input` trigger pattern. This is `AuthValidation`'s second consumer. |
| `showAuthError()` | Phase 8 (`js/app.js:1074`) | **Yes — Profile save errors** | Server errors from profile update/password change endpoint. Same shake+focus pattern. |
| `PMSMotion.setButtonLoading()` | Phase 2 (`js/motion.js`) | **Yes — all new AJAX actions** | Already used on Cancel/Return Early. Apply to profile save, password change, notification mark-read. |
| `initModalFocus()` | Phase 1 (`js/motion.js`) | **Yes — any new modals** | Auto-focuses first input. Applies to profile edit modal or change-password modal if modal-based. |
| `initReveal()` / `data-reveal` | Motion Design (`js/motion.js`) | **Yes — dashboard entrance** | Card reveal animations on page load. Already set up for `[data-reveal]` elements. |
| `.glassmorph` | Homepage phase (`css/styles.css`) | **Possibly — profile modal** | If profile uses a modal, match auth modal styling. If inline section, skip. |
| DataTables | Admin pages only | **No — not appropriate for customer side** | DataTables is loaded on admin pages (`admin_users.php`, `admin_vehicles.php`, `view-all-data.php`) but adds significant weight (JS + CSS). Customer-facing booking lists are small enough to not need client-side search/sort/pagination from DataTables. Bootstrap's own responsive utilities + server-side pagination (if needed) are lighter and more appropriate. **This is a deliberate design decision, not an oversight.** |
| `.list-group` / `.list-group-item` | Bootstrap 5 (used on `transactions.php`) | **Yes — booking displays** | Already the pattern for booking rows. Enhance, don't replace with tables. |
| `.form-control` / `.input-group` / `.invalid-feedback` | Bootstrap 5 (used across auth) | **Yes — profile forms** | Same form patterns established in Authentication phase. |
| Shared partials | `includes/client_navbar.php`, `client_footer.php`, `auth_modals.php` | **Yes — any new page** | Include on any new customer page for consistent chrome. |

---

## 10. Risks and Dependencies

| Risk | Severity | Notes |
|---|---|---|
| **`js/app.js:51` crash is a hard blocker for `transactions.php`** | **Critical** | Must be fixed before any Phase 7 client-side work. Without it, navbar auth, `AuthValidation`, `showAuthError()`, and all Authentication-phase features are dead on this page. Documented since 2026-08-08 ([BUGS.md](BUGS.md) item 11), never fixed. |
| **Profile requires a new backend endpoint** | **High** | Unlike the Authentication phase (frontend-only), Phase 7's Profile task requires creating `update_profile.php`. This crosses the "Backend modifications should only be suggested unless explicitly requested" rule in [CLAUDE.md](../CLAUDE.md). Explicit approval needed before building it. |
| **Notifications scope is undefined** | **High** | No existing infrastructure at any layer. Building a full notification system is a significant effort (new table, triggers, endpoints, UI). Must be scoped/deferred before implementation begins. |
| **`transactions.php` restructuring could break Cancel/Return Early** | **Medium** | These are functional, tested features. Restructuring the page layout must preserve the three action modals and their handlers exactly. |
| **Duplicate `renderNavbarAuth()` — modifying navbar dropdown** | **Medium** | Adding "My Profile" or "Dashboard" links to the dropdown requires editing `renderNavbarAuth()`. Two copies exist (lines 436 and 1558). The dead copy (436) must be identified and not accidentally modified instead of the live one (1558). |
| **`LEFT JOIN transactions` may duplicate booking rows** | **Low** | A booking with multiple `transactions` rows (confirmation + return) will appear multiple times in the result set. Currently masked because `status = 'completed'` bookings (which get a second transaction row from `return_early.php`) are excluded. If the query exclusion is fixed (§8.5), this duplication becomes visible. Fix: use `GROUP BY b.id` or a subquery. |
| **Change-password endpoint has no UI but is fully tested** | **Low** | `change_password.php` is production-ready. Wiring a form to it is low-risk — the endpoint handles all validation and error responses correctly. |

---

## 11. Testing Requirements

For each implementation step:

- `php -l` on any modified PHP file (no syntax errors).
- Visual check in-browser at 375px, 768px, 992px, 1400px.
- Confirm no console JS errors.
- **Step 1 (crash fix):** verify `renderNavbarAuth()` runs on `transactions.php` — navbar shows login/signup buttons (logged out) or Welcome dropdown (logged in).
- **Step 2 (dashboard cards):** verify summary cards display correct counts, additional metrics match booking data.
- **Step 3 (bookings/history):**
  - Cancel booking: confirm cancellation works, proper feedback (no `alert()`), page updates.
  - Return Early: confirm return works, receipt modal shows, proper feedback.
  - Verify cancelled bookings do NOT show Cancel button.
  - Verify `status = 'completed'` bookings appear in History.
  - View Receipt from completed bookings.
- **Step 4 (profile):**
  - View profile: name, email, member since displayed correctly.
  - Edit name: saves, reflected on navbar Welcome message after reload.
  - Edit email: saves, uniqueness enforced (try duplicate email → error).
  - Change password: current password verified, new password saved, can log in with new password.
  - `AuthValidation` per-field feedback works on profile form.
- **Step 5 (notifications):** testing requirements depend on scoping decision.
- **Step 6 (responsive):** full viewport sweep on all customer dashboard content. Touch targets ≥44px. No horizontal scroll. Booking items stack correctly on mobile.
- **Step 7 (final review):** full regression across all customer-facing pages. Existing booking flow on `vehicles.php` still works. Contact form on `about.php` still works. Auth modals work on all pages.

---

## 12. Done / Partial / Not Started Summary

| Item | Status | Details |
|---|---|---|
| Dashboard page/layout | **Not Started** | No dedicated customer dashboard exists. `transactions.php` is a flat page. |
| Summary stat cards | **Partial** | Two basic cards exist (active count, completed count). No icons, no additional metrics. |
| Active bookings display | **Done** | Functional list-group rendering with status badges, dates, amounts, booking refs. |
| Cancel booking | **Done** | Fully functional with server-side validation. UI feedback uses `alert()` (improvable). |
| Return Early | **Done** | Fully functional with receipt modal. |
| Booking status-based action buttons | **Partial** | Action buttons render based on time-computed status, not actual booking `status`. [BUGS.md](BUGS.md) item 10. |
| Completed bookings display | **Partial** | Renders bookings with past return dates, but excludes `status = 'completed'` from query. |
| Receipt viewing from history | **Not Started** | No "View Receipt" link on completed bookings. |
| Customer profile page/section | **Not Started** | No UI exists anywhere for viewing or editing profile. |
| Customer profile update endpoint | **Not Started** | No endpoint for customer self-service name/email update. |
| Change password UI | **Not Started** | Backend fully implemented (`change_password.php`). Zero UI. |
| Notifications (any layer) | **Not Started** | No table, no endpoint, no UI, no triggers. |
| Navbar dropdown — profile/dashboard links | **Not Started** | Dropdown contains only "Logout". |
| Responsive booking display | **Partial** | `d-flex justify-content-between` doesn't stack cleanly at narrow widths. |
| Pagination on booking history | **Not Started** | All bookings rendered on one page. |
| `js/app.js:51` crash fix for `transactions.php` | **Not Started** | Pre-existing since 2026-08-08. Blocks all client-side functionality on this page. |

---

*This document is analysis only. No code has been modified. Implementation awaits review and approval of both this document and the implementation plan ([CUSTOMER_DASHBOARD_IMPLEMENTATION_PLAN.md](CUSTOMER_DASHBOARD_IMPLEMENTATION_PLAN.md)) that follows it, per this project's established workflow.*
