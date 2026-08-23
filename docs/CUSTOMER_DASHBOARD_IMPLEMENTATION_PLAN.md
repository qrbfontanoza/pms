# Customer Dashboard Implementation Plan

Derived from [CUSTOMER_DASHBOARD_ANALYSIS.md](CUSTOMER_DASHBOARD_ANALYSIS.md) (approved). Defines the exact implementation order and per-step requirements for the Customer Dashboard phase (Phase 7 of [UI_IMPLEMENTATION_PLAN.md](UI_IMPLEMENTATION_PLAN.md)), per [CLAUDE.md](../CLAUDE.md)'s workflow.

**Status:** Plan only. No code has been modified. Each step below is implemented only after a dedicated Claude Code prompt is generated, reviewed, and separately approved — one step at a time, per this project's established workflow.

**Key constraint:** Unlike Phase 6 (Authentication), which was entirely frontend-only, Phase 7 requires **one new backend endpoint** (`update_profile.php`) for the Profile task. All other backend endpoints (`cancel_booking.php`, `return_early.php`, `change_password.php`, `me.php`, `api_my_bookings.php`) are fully implemented and unchanged by this phase. The Notifications task requires a **design decision** before it can be scoped — see Step 5.

---

## Implementation Sequence Overview

```
Fix js/app.js Crash on transactions.php (Prerequisite)
  ↓
Dashboard Layout & Enhanced Cards
  ↓
Bookings & History Improvements
  ↓
Confirmed Booking Invoice/Confirmation View (Step 3.1 addendum) — implemented
  ↓
Customer Profile
  ↓
Notifications (Design Decision Required)
  ↓
Responsive & Accessibility Pass
  ↓
Final Review & Documentation
```

### Dependency Graph

```
Fix js/app.js Crash              ── independent; guards the unloaded .datepicker()/.timepicker()
                                    calls at js/app.js:51-57. Must land first so all subsequent
                                    client-side work on transactions.php actually runs. Fixes
                                    BUGS.md item 11.

Dashboard Layout & Enhanced Cards── depends on Step 1 (crash fix). Transforms the flat
                                    transactions.php into a proper dashboard layout. Establishes
                                    the visual framework that Steps 3-5 build on.

Bookings & History Improvements  ── depends on Step 2 (dashboard layout established). Fixes
                                    BUGS.md item 10 (action buttons), the status!='completed'
                                    query exclusion, and alert() feedback. Independent of Step 4
                                    (profile) in code, ordered before for testing continuity.

Customer Profile                 ── depends on Step 1 (crash fix) and Step 2 (dashboard layout).
                                    Independent of Step 3 in code. Creates a new endpoint and
                                    new UI. Adds profile/dashboard links to navbar dropdown.

Notifications                    ── depends on Steps 1-4 (full dashboard context needed to
                                    decide appropriate scope). Requires a design decision before
                                    implementation begins.

Responsive & Accessibility Pass  ── depends on all of Steps 1-5 being complete.

Final Review & Documentation     ── depends on all prior steps.
```

---

## Step 1: Fix `js/app.js` Crash on `transactions.php` (Prerequisite)

### Objective

Guard the unloaded `.datepicker()` and `.timepicker()` plugin calls at `js/app.js:51-57` so they do not throw a TypeError on pages that do not load jQuery UI or the timepicker plugin. This is [BUGS.md](BUGS.md) item 11 — a pre-existing bug that breaks **all** subsequent JavaScript on `transactions.php`, including `renderNavbarAuth()`, `AuthValidation`, `showAuthError()`, and all Authentication-phase functionality. **Every subsequent Phase 7 step depends on this fix.**

### Existing Files Involved

- [js/app.js](../js/app.js) — lines 51-57 contain unguarded `.datepicker()` and `.timepicker()` calls that crash on pages where those plugins aren't loaded
- [transactions.php](../transactions.php) — includes `js/app.js` (line 57) but does not include jQuery UI or the timepicker plugin in its `<head>` (lines 51-63)

### Files Expected to Be Modified

- `js/app.js` (guard the two plugin calls with `typeof` checks)

### Components to Reuse

None — this is a bug fix, not a feature.

### Components to Create

None — the fix is a conditional guard on existing code.

### Fix Specification

At `js/app.js:51-57`, the code calls `$(selector).datepicker(...)` and `$(selector).timepicker(...)` without checking if those jQuery plugins are loaded. The fix is:

```javascript
if (typeof $.fn.datepicker === 'function') {
    $(selector).datepicker(...);
}
if (typeof $.fn.timepicker === 'function') {
    $(selector).timepicker(...);
}
```

This preserves the datepicker/timepicker functionality on pages where the plugins ARE loaded (e.g., the admin booking page) while preventing the crash on pages where they're not (e.g., `transactions.php`).

### Dependencies

None — this is the first step.

### Data Requirements

None.

### CSS Requirements

None.

### Bootstrap 5 Requirements

None.

### Responsive Requirements

None — this fix is JS-only and invisible to the user.

### Accessibility Requirements

None — fixing this crash actually **restores** accessibility features (keyboard navigation in the navbar, ARIA-wired auth modals) that were previously broken on `transactions.php`.

### Testing Requirements

- Load `transactions.php` in the browser.
- Open the browser console — confirm **no** TypeError related to `.datepicker()` or `.timepicker()`.
- Confirm `renderNavbarAuth()` runs: the navbar shows "Login | Sign Up" buttons (logged out) or the "Welcome, {name}!" dropdown (logged in).
- Confirm `AuthValidation` is available (open a login modal, blur the email field empty → validation error should appear if Phase 6 is complete).
- Load any page that DOES use datepicker/timepicker (e.g., the admin booking page if accessible) and confirm those plugins still work correctly.
- Console: no new JS errors on any page.

### Acceptance Criteria

- `js/app.js` no longer throws on `transactions.php` or any other page missing datepicker/timepicker plugins.
- All `js/app.js` features that were previously broken on `transactions.php` now function (navbar auth, auth modals, motion).
- Datepicker/timepicker functionality is preserved on pages where the plugins are loaded.

### Risks

- Very Low. The fix is two `typeof` guards — no behavioral change to pages that load the plugins, and crash prevention on pages that don't.

---

## Step 2: Dashboard Layout & Enhanced Cards

### Objective

Transform `transactions.php` from a flat page into a structured customer dashboard layout. Enhance the two existing summary cards (active count, completed count) with icons, better visual design using design tokens, and additional metrics derived from already-available query data. Establish the visual framework (page title, card grid, section structure) that Steps 3-5 build on.

### Existing Files Involved

- [transactions.php](../transactions.php) — the entire page; specifically:
  - Lines 10-33: booking query (data source for all metrics)
  - Lines 40-47: PHP bucketing into `$active` and `$completed` arrays
  - Lines 95-108: the two existing summary cards
  - Lines 66-68: `client_navbar.php` include and spacer div
- [css/styles.css](../css/styles.css) — design tokens (lines 2-30)
- [js/motion.js](../js/motion.js) — `initReveal()` for entrance animations
- [includes/client_navbar.php](../includes/client_navbar.php) — may need "Dashboard" link added or "Transactions" renamed

### Files Expected to Be Modified

- `transactions.php` (restructure layout, enhance cards)
- `css/styles.css` (dashboard-specific classes if needed — prefer Bootstrap utilities)
- `includes/client_navbar.php` (potentially rename "Transactions" to "Dashboard" or "My Bookings" in the nav link)

### Components to Reuse

- Design token variables from `css/styles.css` (`--primary`, `--secondary`, `--accent`, `--background`, `--border`)
- `initReveal()` / `data-reveal` / `data-reveal-stagger` (entrance animations for cards)
- Bootstrap grid (`row`, `col-*`), Bootstrap cards (`bg-white rounded-3 shadow-sm`), utility classes
- Font Awesome icons for card decoration (`fa-car`, `fa-check-circle`, `fa-calendar`, `fa-money-bill`)
- Existing PHP data: `$active` array, `$completed` array — all metrics are derivable without new queries

### Components to Create

- **Page header section** — a dashboard title ("My Dashboard" or "My Bookings") with a welcoming subtitle. Uses `<h1>` with appropriate typography from the design system.
- **Enhanced summary card grid** — expand from 2 cards to 3-4 cards in a responsive grid:
  - **Active Rentals** (existing, enhanced with icon) — count of `$active` array
  - **Completed Rentals** (existing, enhanced with icon) — count of `$completed` array
  - **Total Spent** (new) — `array_sum(array_column($completed, 'total_amount'))` + sum of active amounts
  - **Next Rental** (new, conditional) — the nearest future `rental_date` from `$active`, or "None scheduled" if empty
- **Section dividers** — clear visual separation between the cards section, active bookings section, and completed bookings section. Use Bootstrap spacing and optionally light horizontal rules or section headings with icons.
- **`data-reveal` attributes** on cards and sections for entrance animations.

### Dependencies

Step 1 (crash fix) must be complete — without it, `initReveal()` may not run on this page.

### Data Requirements

All data is already available from the existing query at `transactions.php:10-33`. Additional metrics are computed from the `$active` and `$completed` PHP arrays:
- Total spent: `$totalSpent = array_sum(array_column(array_merge($active, $completed), 'total_amount'));`
- Next rental: `$nextRental = !empty($active) ? $active[count($active)-1] : null;` (query is `ORDER BY rental_date DESC`, so last element is nearest future date)

No new database queries required.

### CSS Requirements

- Use design tokens exclusively — no hardcoded colors.
- Card styling should enhance the existing `bg-white rounded-3 shadow-sm` pattern — do not introduce a fundamentally different card style.
- If custom dashboard classes are needed, prefix them (e.g., `.dashboard-stat-card`, `.dashboard-section`) and define them in `css/styles.css`.
- Icons inside cards should use `color: var(--secondary)` or `color: var(--accent)` for visual interest without introducing new colors.

### Bootstrap 5 Requirements

- Card grid: `row g-3 g-lg-4` with `col-sm-6 col-xl-3` for 4 cards (2 per row on mobile, 4 on desktop).
- Section headings: use `<h2>` or `<h3>` with `fw-semibold mb-3` for "Active Rentals" and "Completed Rentals" sections.
- Page container: maintain the existing `container py-4` pattern.
- Spacer div after fixed navbar: verify 80px spacer is still correct after layout changes.

### Responsive Requirements

- Cards: 1 column at <576px, 2 columns at sm-xl, 4 columns at xl+.
- Page title: readable at 375px, appropriate size reduction via responsive font classes.
- Card content (metric + label): must not overflow the card at 375px.
- Section structure must not cause horizontal scroll at any breakpoint.
- Test at 375px, 768px, 992px, 1400px.

### Accessibility Requirements

- Each card should use a semantic heading (`<h6>` or `<p>` with appropriate role) for the metric label.
- Icons should be decorative (`aria-hidden="true"`) — the text label conveys the meaning.
- Page heading uses `<h1>` — maintain heading hierarchy (`h1` → `h2` for section titles → `h3` if needed for sub-sections).
- `data-reveal` animations must respect `prefers-reduced-motion` (already handled by `initReveal()`'s implementation).

### Testing Requirements

- Visual check: page loads with dashboard layout — title, 3-4 summary cards, active bookings section, completed bookings section.
- Cards display correct data: counts match actual bookings, total spent is accurate, next rental shows correct date.
- `data-reveal` animations fire on page load (cards animate in).
- Page renders correctly at 375px, 768px, 992px, 1400px.
- No horizontal scroll at any breakpoint.
- Console: no new JS errors.
- Existing Cancel/Return Early functionality is preserved (buttons still render, modals still open — functional testing deferred to Step 3).

### Acceptance Criteria

- `transactions.php` has a clear dashboard layout with page title, enhanced stat cards, and structured sections.
- Summary cards show: Active Rentals count, Completed Rentals count, Total Spent, Next Rental date.
- All design tokens used — no hardcoded colors.
- `data-reveal` entrance animations applied.
- Existing booking display and action buttons are preserved (restructured visually but functionally identical).
- Responsive at all breakpoints.

### Risks

- Medium — restructuring the page layout must preserve the existing PHP `$active`/`$completed` bucketing, the three action modals (Return Early, Cancel, Return Receipt), and their inline JS handlers. The modals depend on `onclick` attributes that reference booking IDs — ensure the restructured markup doesn't break these bindings.

---

## Step 3: Bookings & History Improvements

### Objective

Fix the two documented bugs affecting booking display ([BUGS.md](BUGS.md) items 10 and 11 — item 11 fixed in Step 1, item 10 fixed here), fix the `status != 'completed'` query exclusion so properly-completed bookings appear in History, replace `alert()` feedback with Bootstrap alerts/toasts, add receipt viewing from completed bookings, and clean up the orphaned License Preview modal.

### Existing Files Involved

- [transactions.php](../transactions.php):
  - Line 23: `WHERE b.user_id = ? AND b.status != 'completed'` — the query exclusion
  - Lines 40-41: PHP bucketing by `return_date` comparison
  - Lines 110-181: active/upcoming bookings section with action buttons
  - Lines 184-217: completed bookings section
  - Lines 226-316: four modals (Return Early, License Preview orphaned, Cancel Booking, Return Receipt)
  - Lines 322-435: inline JS handlers (`confirmCancellation()`, `confirmReturnEarly()`)
- [cancel_booking.php](../cancel_booking.php) — endpoint for cancellation (unchanged)
- [return_early.php](../return_early.php) — endpoint for early return (unchanged)
- [receipt.php](../receipt.php) — standalone receipt page (can be linked from completed bookings)

### Files Expected to Be Modified

- `transactions.php` (query fix, button logic fix, `alert()` replacement, receipt links, orphaned modal cleanup)

### Components to Reuse

- `PMSMotion.setButtonLoading()` — already used on Cancel/Return Early buttons (preserved)
- `showAuthError()` pattern — adapt for in-page error feedback (Bootstrap alert within a section, not inside a modal)
- `.badge` styling — already used for booking status; enhance with status-appropriate colors
- `.list-group-item` — existing booking row pattern (preserved and enhanced)

### Components to Create

- **Status-aware action button rendering** — replace the current time-based button logic with a check on the actual `$booking['status']` field:
  - `pending` or `confirmed` with future rental date → show "Cancel Booking" button
  - `pending` or `confirmed` with active date range → show "Return Early" button
  - `cancelled` → show no action buttons, display "Cancelled" badge
  - `completed` → show no action buttons (already in completed section)
- **Bootstrap alert feedback** — replace `alert('Booking cancelled successfully!')` and `alert('Error: ...')` in `confirmCancellation()` and `confirmReturnEarly()` with a Bootstrap alert inserted at the top of the relevant section:
  ```html
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    Booking cancelled successfully. <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  ```
  After a 3-second delay, reload the page to reflect the updated state (preserving the current reload behavior but with better feedback).
- **"View Receipt" link on completed bookings** — a link or button on each completed booking row that opens `receipt.php?booking_ref=X` in a new tab, or opens the existing Return Receipt modal populated with the booking data.
- **Query fix** — remove `AND b.status != 'completed'` from the query. Adjust the PHP bucketing logic to handle all statuses:
  - Active section: bookings with `status` in (`pending`, `confirmed`) AND `return_date >= today`
  - Completed section: bookings with `status = 'completed'` OR (`return_date < today` AND `status` in (`pending`, `confirmed`, `cancelled`))
  - This ensures completed-via-return-early bookings appear in History instead of vanishing.
- **Duplicate row prevention** — add `GROUP BY b.id` to the query to prevent the `LEFT JOIN transactions` from producing duplicate rows when a booking has multiple transaction records.

### Dependencies

Step 1 (crash fix) and Step 2 (dashboard layout) must be complete.

### Data Requirements

- Modified query: remove `AND b.status != 'completed'`, add `GROUP BY b.id`.
- Revised PHP bucketing: use both `status` AND `return_date` for accurate categorization.
- No new endpoints — `cancel_booking.php` and `return_early.php` are unchanged.

### CSS Requirements

- Status badge colors should use semantic Bootstrap classes: `bg-success` for completed, `bg-warning text-dark` for pending, `bg-primary` for confirmed, `bg-danger` for cancelled.
- Alert styling uses Bootstrap's built-in classes — no custom CSS needed.
- No hardcoded colors.

### Bootstrap 5 Requirements

- `alert alert-success alert-dismissible fade show` for success feedback.
- `alert alert-danger alert-dismissible fade show` for error feedback.
- `btn-close` for alert dismissal.
- Badges: `badge rounded-pill` with status-appropriate `bg-*` classes.

### Responsive Requirements

- Booking row layout must stack cleanly at 375px — vehicle title, badges, dates, and amount should flow vertically on narrow screens instead of overflowing the `d-flex justify-content-between` container.
- Action buttons must maintain 44×44px touch targets.
- "View Receipt" link must be reachable on mobile.
- Test at 375px, 768px, 992px, 1400px.

### Accessibility Requirements

- Action buttons must have `aria-label` describing the action and target: `aria-label="Cancel booking for [vehicle title]"`.
- Status badges should be accessible: `<span class="badge bg-success" role="status">Completed</span>`.
- Alert feedback must use `role="alert"` for screen reader announcement.
- "View Receipt" link needs descriptive accessible name: `aria-label="View receipt for booking [booking_ref]"`.

### Testing Requirements

- **Bug #10 fix:** Cancel a booking. Reload the page. Confirm the cancelled booking does NOT show a "Cancel Booking" button — it should display a "Cancelled" badge instead.
- **Query fix:** Complete a booking via Return Early. Reload. Confirm the booking appears in the Completed Rentals section (previously it vanished entirely).
- **Cancel flow:** Click "Cancel Booking" → confirm in modal → success alert appears (not native `alert()`) → page reloads after delay → booking shows "Cancelled" badge.
- **Return Early flow:** Click "Return Early" → confirm in modal → receipt modal shows → success alert appears (not native `alert()`) → page reloads after delay → booking moves to Completed section.
- **View Receipt:** Click "View Receipt" on a completed booking → `receipt.php` opens with correct booking data.
- **Duplicate rows:** Confirm no booking appears twice in the listing (the `GROUP BY` fix).
- **Orphaned modal:** Confirm the License Preview modal markup is removed and no JS error results.
- Visual check at 375px, 768px, 992px, 1400px — booking rows stack correctly on mobile.
- Console: no new JS errors.

### Acceptance Criteria

- Action buttons render based on actual `status` field, not time-computed status. [BUGS.md](BUGS.md) item 10 resolved.
- `status = 'completed'` bookings appear in the History/Completed section. Query exclusion fixed.
- `alert()` replaced with Bootstrap alert feedback for both Cancel and Return Early.
- "View Receipt" link available on completed bookings.
- Orphaned License Preview modal removed.
- No duplicate booking rows.
- All existing Cancel/Return Early functionality preserved.

### Risks

- Medium — modifying the booking query changes what data is available to the entire page. The PHP bucketing logic must be updated in sync to prevent bookings from appearing in the wrong section or disappearing. Test thoroughly with bookings in all four statuses (`pending`, `confirmed`, `cancelled`, `completed`).
- Low — `GROUP BY b.id` may change which `transaction_ref` is returned for bookings with multiple transactions (it'll return an arbitrary one). If `transaction_ref` display matters, use `GROUP_CONCAT` or a subquery. Currently `transaction_ref` is displayed on booking rows — verify the displayed value is acceptable.

---

## Step 3.1: Confirmed Booking Invoice/Confirmation View (Post-Step-3 Addendum)

**Status:** Implemented, 2026-08-14. Proposed after Step 3 shipped, following a UI-heuristics discussion; open questions below were resolved per the recommendations already stated in each (label = "View Booking Confirmation", scope = `confirmed` only, `receipt.php` variation = heading + one note only, rest of the page untouched). See [CHANGELOG.md](../CHANGELOG.md) for the implementation entry.

### Objective

Step 3 added a single "View Receipt" link that appears only on `completed` bookings, pointing at `receipt.php`. This addendum extends that pattern to `confirmed` bookings with a *differently labeled* link — "View Booking Confirmation" rather than "View Receipt" — so users get proof of reservation / prepayment breakdown before fulfillment, without the system implying a finished transaction that hasn't happened yet.

**Reasoning (UI heuristics, not just cosmetics):**
- **Match between system and the real world** — "Receipt" conventionally means proof a transaction *completed*; reusing it for a booking that hasn't been fulfilled contradicts what users already expect from every rental/hotel/airline booking flow they've used.
- **Visibility of system status** — a differentiated label reinforces the status badge instead of flattening two distinct states (`confirmed` vs `completed`) into identical link text.
- **Error prevention** — a `confirmed` booking's final total isn't necessarily settled (early return, damage adjustments, or a later cancellation refund can still change it). Calling it a "Receipt" risks a user expensing/filing a number that later changes; "Booking Confirmation" correctly frames it as a snapshot, not a final statement.
- **Consistency** — avoids one label doing double duty for two different documents.

### Existing Files Involved

- [transactions.php](../transactions.php) — Active & Upcoming Rentals section, `$active` loop (currently ~lines 144-193 as of Step 3). Only `confirmed` bookings (not `pending`) would get the new link — see Open Question 2.
- [receipt.php](../receipt.php) — entire file. Currently renders a single fixed "Booking Receipt" heading and status/amount-paid framing regardless of the underlying booking's actual `status`. If linked to from a `confirmed` booking unmodified, the page itself would still say "Booking Receipt" under a "Booking Confirmation" link — the exact mismatch this addendum exists to fix would just move one page deeper. This file was **not** touched by Step 3 and needs its own scoped review before any change.

### Files Expected to Be Modified

- `transactions.php` (add a "View Booking Confirmation" link on `confirmed` rows in the Active section, alongside the existing Cancel Booking / Return Early buttons)
- `receipt.php` (status-aware heading/copy — e.g. "Booking Confirmation" for `confirmed`, "Booking Receipt" for `completed` — see Open Question 3 for how much of the page should vary)

### Components to Reuse

- `receipt.php`'s existing booking lookup (`id`/`ref` param handling) and `$amount_paid`/`$total_due`/`$change` calculation — this logic is status-agnostic and doesn't need to change, only the surrounding copy/heading.
- The `.btn-outline-secondary py-3`, `d-grid d-md-flex`, and `aria-label="..."` link pattern Step 3 established for View Receipt.
- `target="_blank" rel="noopener"` (same as View Receipt).

### Components to Create

- A status-driven heading/intro block in `receipt.php` (e.g. `$booking['status'] === 'completed' ? 'Booking Receipt' : 'Booking Confirmation'`), scope TBD per Open Question 3.
- A "View Booking Confirmation" link in `transactions.php`'s Active section, rendered only for `confirmed` bookings, with `aria-label="View booking confirmation for booking {booking_ref}"`.

### Open Questions — Resolved

1. **Label wording:** "View Booking Confirmation" (not "Invoice") — matches how the booking is actually paid for (prepaid via `reserve.php`, not owed).
2. **Scope:** `confirmed` bookings only. `pending` bookings still show only their existing action button (Return Early/Cancel), no confirmation link — a `pending` booking hasn't actually been confirmed yet, so a "Confirmation" link there would be its own mismatch.
3. **`receipt.php` variation:** minimal — only the `<title>`, the card heading, and the footer note ("please present this receipt/confirmation...") vary by status. The amount/status field labels ("Amount Paid", "Change", "Status") are unchanged for both states, keeping the tested `completed` logic path untouched.

### Dependencies

Step 3 (done) — this directly extends the View Receipt link and `receipt.php` linkage Step 3 introduced. Independent of Steps 4-6 in code; can land before or after Step 4 (Profile) without conflict, since neither touches the same markup.

### CSS / Bootstrap / Responsive / Accessibility Requirements

Same shape as Step 3's View Receipt link: existing design tokens only, `py-3`/`d-grid d-md-flex` for the 44×44px touch target and mobile stacking already established, `aria-label` per the pattern above. No new custom CSS expected.

### Testing Performed

- **Link scoping:** live-tested with one `confirmed`, one `pending`, and one `cancelled` booking for the same user — "View Booking Confirmation" rendered only on the `confirmed` row (alongside its existing Cancel Booking button, both inside a shared `d-grid d-md-flex gap-2` wrapper); `pending` showed only Return Early, no confirmation link.
- **`receipt.php` status-awareness:** `?ref=` for a `confirmed` booking rendered title/heading/footer note as "Booking Confirmation" / "Please present this confirmation..."; a `completed` booking rendered "Booking Receipt" / "Please present this receipt..." — confirmed both in the same session.
- **Regression check on Step 3's View Receipt flow:** re-verified a `completed` booking's `receipt.php` output field-for-field (Amount Paid, Change, Status, all unchanged) — identical to Step 3's tested output, confirming the `completed` path wasn't disturbed by the new conditional.
- `php -l` on `transactions.php` and `receipt.php`: no syntax errors. Console: no new JS errors on either page (one stale `500` network log from an earlier Step 3 test session, unrelated to this change).
- Hex-color grep on both files: zero matches.
- Test bookings created for this verification were deleted from the database afterward; no test data left behind.

### Acceptance Criteria

- [x] `confirmed` bookings show a "View Booking Confirmation" link distinct from `completed` bookings' "View Receipt" link.
- [x] `receipt.php`'s content matches whichever label the user actually clicked (no page says "Receipt" for a booking that isn't `completed`, and vice versa).
- [x] Step 3's `completed`-booking View Receipt behavior is unchanged (regression-safe).

### Risks (as realized)

- Low-medium risk materialized as expected: `receipt.php` was touched for the first time this phase. Mitigated by keeping the diff to three conditional strings (title, heading, footer note) and leaving the amount/status calculation logic completely untouched — confirmed via the regression check above.

---

## Step 4: Customer Profile

### Objective

Build a customer profile section that allows users to view their account information and edit their name, email, and password. This requires: (1) a new backend endpoint for customer self-service profile updates (`update_profile.php`), (2) a profile UI (either a dedicated page section within the dashboard or a modal), and (3) adding "My Profile" and "Dashboard" links to the navbar dropdown.

**Note:** This step requires creating a new backend endpoint. Per [CLAUDE.md](../CLAUDE.md), "Backend modifications should only be suggested unless explicitly requested." Explicit approval is needed before implementing `update_profile.php`.

### Existing Files Involved

- [me.php](../me.php) — returns `{id, name, email, role}` for the logged-in user (read-only, unchanged)
- [change_password.php](../change_password.php) — accepts current password + new password, fully implemented (unchanged)
- [js/app.js](../js/app.js):
  - `AuthValidation` pattern (reusable for profile form validation)
  - `showAuthError()` (reusable for server error display)
  - `renderNavbarAuth()` (line 1558) — the navbar dropdown that needs "My Profile" link added
  - `getMe()` function — already fetches user data from `me.php`
- [includes/client_navbar.php](../includes/client_navbar.php) — may need a "Dashboard" link or adjusted naming
- [css/styles.css](../css/styles.css) — design tokens

### Files Expected to Be Modified

- `js/app.js` (profile form handlers, navbar dropdown links, validation wiring)
- `transactions.php` (profile section markup added to the dashboard layout)
- `css/styles.css` (profile section styling if needed — prefer Bootstrap utilities)
- `includes/client_navbar.php` (add "Dashboard" link if not done in Step 2)

### Files Expected to Be Created

- `update_profile.php` — new endpoint for customer self-service name/email updates

### Components to Reuse

- **`AuthValidation` pattern** — this is `AuthValidation`'s second consumer (the first being auth modals). The same `blur`/`input` trigger system, `.is-invalid`/`.invalid-feedback` wiring, and `aria-describedby`/`aria-invalid` ARIA attributes apply directly to the profile form. Name validation (non-empty), email validation (non-empty, email format), and password validation (min 6 chars, match confirmation) are the exact same rules as the signup form.
- **`showAuthError()`** — for server error display on profile save failures (duplicate email, wrong current password).
- **`PMSMotion.setButtonLoading()`** — for save button loading states.
- **`initModalFocus()`** — if profile editing uses a modal.
- **`getMe()`** — already exists in `js/app.js`, fetches user data. Reuse to populate profile display.
- **Bootstrap form patterns** — `.form-control`, `.input-group.has-validation`, `.invalid-feedback`, `.form-text`.

### Components to Create

#### Backend: `update_profile.php`

New endpoint, modeled on `admin_update_user.php` but scoped to customer self-service:

- **Auth:** Requires `$_SESSION['user']` (returns 401 if not logged in)
- **Method:** POST only
- **Input:** `name` (required, non-empty), `email` (required, valid email format)
- **Validation:**
  - Name: non-empty after trim
  - Email: valid format (`filter_var($email, FILTER_VALIDATE_EMAIL)`)
  - Email uniqueness: `SELECT id FROM users WHERE email = ? AND id != ?` — if a row exists, return error "Email already in use."
- **Action:** `UPDATE users SET name = ?, email = ? WHERE id = ?` using `$_SESSION['user']` as the authenticated user ID
- **Response:** JSON `{ success: true }` or `{ success: false, error: "message" }`
- **Security:** Uses PDO prepared statements (consistent with `change_password.php`). Only updates the currently logged-in user's own record — no `user_id` parameter accepted from the request.

#### Frontend: Profile Section

A collapsible or tabbed section within the dashboard (recommended over a separate page, to keep all customer features in one place). Two sub-sections:

**Profile Information:**
- Display: Name, Email, Member Since (`created_at`), Role
- Edit form: Name field, Email field, "Save Changes" button
- Populated via `getMe()` AJAX call (already exists) — may need to extend `me.php` to return `created_at`
- On save: POST to `update_profile.php`, show success/error feedback, update navbar Welcome message

**Change Password:**
- Fields: Current Password, New Password, Confirm New Password
- Helper text: "Minimum 6 characters" on New Password field (same as signup)
- Password visibility toggle (if implemented in Phase 6, apply here for consistency)
- On save: POST to `change_password.php`, show success/error feedback
- `AuthValidation` rules: current password non-empty, new password min 6 chars, confirm matches new

#### Navbar Dropdown Links

Add to the `renderNavbarAuth()` dropdown (the live copy at `js/app.js:1558`):
- "My Profile" — scrolls to the profile section on `transactions.php`, or opens the profile modal
- Optionally "My Dashboard" — links to `transactions.php` from other pages

### Dependencies

Step 1 (crash fix) must be complete. Step 2 (dashboard layout) should be complete so the profile section integrates into the established layout.

### Data Requirements

- `me.php` may need to return `created_at` for the "Member Since" display. This is a minimal backend change (one additional field in the JSON response).
- `update_profile.php` is a new file (see specification above).
- `change_password.php` is unchanged — the UI simply calls it via `fetch()`.

### CSS Requirements

- Profile section uses the same card/section styling established in Step 2 (`.bg-white.rounded-3.shadow-sm.p-4`).
- Form styling uses Bootstrap's built-in classes — minimal or no custom CSS needed.
- No hardcoded colors — use design tokens.

### Bootstrap 5 Requirements

- Form: `.form-control`, `.form-label`, `.input-group.has-validation`, `.invalid-feedback`, `.form-text`.
- Two-column layout on desktop for the profile section: `.row` with `.col-lg-6` for profile info and `.col-lg-6` for change password.
- Success feedback: `alert alert-success` within the profile section.
- Error feedback: `showAuthError()` pattern adapted for inline (non-modal) use, or a section-level `alert alert-danger`.

### Responsive Requirements

- Profile section: single column on mobile (<992px), two columns on desktop (≥992px).
- Form inputs maintain readable size at 375px (Bootstrap's `.form-control` handles this by default).
- "Save Changes" buttons are full-width on mobile, auto-width on desktop.
- Test at 375px, 768px, 992px, 1400px.

### Accessibility Requirements

- All form fields have visible `<label>` elements with `for` attributes.
- `aria-describedby` on password fields pointing to helper text and `.invalid-feedback` elements (space-separated IDs for multiple descriptions).
- `aria-invalid` toggled on validation state changes.
- Password visibility toggle (if used) has `aria-label` that updates on state change.
- Profile section has a heading (`<h2>`) for the heading hierarchy.
- Success/error alerts use `role="alert"` for screen reader announcement.
- "My Profile" link in navbar dropdown is keyboard-focusable.

### Testing Requirements

- **View profile:** Navigate to dashboard → profile section shows current name, email, member since.
- **Edit name:** Change name, click "Save Changes" → success alert appears. Reload → name persisted. Navbar Welcome message updates.
- **Edit email:** Change email to a new valid email → saves successfully. Change to an already-used email → "Email already in use." error.
- **Email validation:** Enter invalid email format → client-side validation catches it before submit.
- **Change password:** Enter current password + new password (6+ chars) + confirm → success. Try to log in with old password → fails. Log in with new password → succeeds.
- **Wrong current password:** Enter incorrect current password → `change_password.php` returns error → displayed in profile section.
- **Short new password:** Enter 4-char new password → client-side validation: "Password must be at least 6 characters."
- **Password mismatch:** New password ≠ confirm → client-side validation: "Passwords do not match."
- **Navbar links:** "My Profile" in dropdown navigates/scrolls to profile section. Works from `transactions.php` and other pages.
- **Not logged in:** Accessing `update_profile.php` directly without session → 401 response.
- **`AuthValidation` wiring:** All profile form fields have blur/input validation with `.is-invalid`/`.invalid-feedback` (same pattern as auth modals).
- Visual check at 375px, 768px, 992px, 1400px.
- Console: no new JS errors.

### Acceptance Criteria

- A profile section exists on the customer dashboard showing name, email, member since, and role.
- Customers can update their name and email via `update_profile.php`.
- Customers can change their password via the existing `change_password.php` endpoint, now exposed through a UI form.
- `AuthValidation` is wired into all profile form fields (its second consumer after auth modals).
- Navbar dropdown includes "My Profile" link.
- `update_profile.php` uses PDO prepared statements and session-based auth — no `user_id` from request body.
- All error/success feedback uses Bootstrap alerts, not `alert()`.

### Risks

- Medium — this step creates a new backend endpoint (`update_profile.php`). Security must be verified: PDO prepared statements, session-based auth only, no mass assignment (only `name` and `email` are updatable). The email uniqueness check must exclude the current user's own ID to avoid a false duplicate error.
- Low — extending `me.php` to return `created_at` is a trivial change, but it is a backend modification requiring approval per CLAUDE.md.
- Low — the dead `renderNavbarAuth()` at `js/app.js:436` must not be accidentally modified instead of the live one at `js/app.js:1558`.

---

## Step 5: Notifications (Design Decision Required)

### Objective

Implement a notification mechanism for the customer dashboard. **This step cannot be scoped until a design decision is made** — there is zero existing infrastructure at any layer (no database table, no backend endpoints, no frontend UI, no notification triggers).

### Design Options

Three options are presented. The recommendation is **Option B** for Phase 7, with Option A deferred to a future phase.

#### Option A: Full Notification System (Recommended to DEFER)

- **New `notifications` table:** `id`, `user_id`, `type` (enum: booking_confirmed, booking_cancelled, booking_reminder, return_reminder, system), `title`, `message`, `is_read` (boolean), `related_booking_id` (nullable FK), `created_at`
- **Notification triggers:** insert notification rows from `cancel_booking.php`, `return_early.php`, `admin_update_booking.php` (if it exists), and potentially a cron job for upcoming-rental reminders
- **Backend endpoints:** `notifications.php` (GET — list for current user, with pagination), `mark_notification_read.php` (POST — mark one or all as read)
- **Frontend:** notification bell icon in navbar with unread count badge, dropdown or slide-out panel showing recent notifications, "Mark all as read" action
- **Scope:** This is a significant full-stack feature touching database schema, multiple existing endpoints, navbar, and a new UI component. It is more appropriate as its own phase than one step within Phase 7.

#### Option B: Booking Status Alerts (Recommended for Phase 7)

- **No new database table** — derive notification-like content from booking data already available
- **Dashboard alerts:** at the top of the dashboard, show contextual alert cards for recent booking status changes:
  - Bookings confirmed in the last 48 hours → "Your booking for [vehicle] has been confirmed!"
  - Bookings with rental date within 24 hours → "Reminder: Your rental of [vehicle] starts tomorrow!"
  - Recently cancelled bookings → "Booking for [vehicle] was cancelled."
- **Implementation:** PHP logic on `transactions.php` comparing booking `created_at`/`rental_date` timestamps to `now()`. No new table, no new endpoints. Alerts are dismissible (Bootstrap `alert-dismissible`).
- **Scope:** Frontend-only (or minimal PHP additions to the existing page). Fits naturally within Phase 7's dashboard focus.

#### Option C: Defer Entirely

- Skip notifications for Phase 7. The acceptance criteria in [UI_IMPLEMENTATION_PLAN.md](UI_IMPLEMENTATION_PLAN.md) for Phase 7 is simply "Customer functionality preserved." — no specific notification requirement is defined beyond the task name.
- Revisit as a dedicated phase when the project scope expands to include email notifications, push notifications, or a real-time notification system.

### Existing Files Involved (Option B)

- [transactions.php](../transactions.php) — dashboard page where alerts would render
- [docs/DATABASE.md](DATABASE.md) — schema reference (no notifications table exists)

### Files Expected to Be Modified (Option B)

- `transactions.php` (add PHP logic for status-based alerts, add alert markup)

### Components to Reuse (Option B)

- Bootstrap `alert alert-info alert-dismissible fade show` for informational alerts
- Bootstrap `alert alert-warning alert-dismissible fade show` for reminders
- Font Awesome icons for alert decoration (`fa-bell`, `fa-calendar-check`, `fa-exclamation-triangle`)
- Existing `$active` and `$completed` arrays — all data is already loaded

### Components to Create (Option B)

- PHP logic block computing alerts from existing booking data:
  ```php
  $alerts = [];
  foreach ($active as $booking) {
      $rentalDate = new DateTime($booking['rental_date']);
      $now = new DateTime();
      $hoursUntil = ($rentalDate->getTimestamp() - $now->getTimestamp()) / 3600;
      if ($hoursUntil > 0 && $hoursUntil <= 24) {
          $alerts[] = ['type' => 'warning', 'icon' => 'fa-clock',
              'message' => "Reminder: Your rental of {$booking['vehicle_title']} starts tomorrow!"];
      }
  }
  ```
- Alert rendering section at the top of the dashboard content (below cards, above booking lists).

### Dependencies

Steps 1-4 should be complete (full dashboard context needed to place alerts correctly).

### Data Requirements (Option B)

No new queries — derived from existing `$active` and `$completed` arrays. If the query fix from Step 3 is in place, cancelled bookings are also available for "recently cancelled" alerts.

### CSS Requirements (Option B)

- Use Bootstrap alert classes — no custom CSS needed.
- No hardcoded colors.

### Bootstrap 5 Requirements (Option B)

- `alert alert-info|alert-warning|alert-success alert-dismissible fade show` with `btn-close`.
- `d-flex align-items-center gap-2` for icon + message layout within alerts.

### Responsive Requirements (Option B)

- Alerts render full-width within the dashboard container — naturally responsive.
- Test at 375px to ensure alert text doesn't overflow with long vehicle titles.

### Accessibility Requirements (Option B)

- Alerts use `role="alert"` for screen reader announcement.
- Dismiss button has `aria-label="Close"` (Bootstrap default).
- Icons are decorative (`aria-hidden="true"`).

### Testing Requirements (Option B)

- Create a booking with `rental_date` = tomorrow → "Reminder" alert appears on dashboard.
- Confirm a booking → check for "confirmed" alert within 48 hours of confirmation.
- Cancel a booking → check for "cancelled" alert on next dashboard load.
- Dismiss an alert → alert disappears. Reload → alert reappears (no persistence — derived from data, not stored state).
- Visual check at 375px, 768px, 992px, 1400px.
- Console: no new JS errors.

### Acceptance Criteria (Option B)

- Contextual alerts appear on the dashboard for relevant booking status conditions.
- Alerts are dismissible.
- No new database table, endpoint, or migration required.
- Alerts derive from existing booking data — no notification infrastructure.

### Risks

- Low (Option B) — pure PHP/HTML additions, no schema or endpoint changes.
- High (Option A) — significant scope expansion. If chosen, should be its own implementation plan.
- Decision risk — the "Notifications" task name in [UI_IMPLEMENTATION_PLAN.md](UI_IMPLEMENTATION_PLAN.md) is ambiguous. Option B fulfills the spirit of the task (inform the customer of relevant status changes) without the infrastructure cost of a full notification system.

---

## Step 6: Responsive & Accessibility Pass

### Objective

Verify and fix any remaining responsive layout issues and accessibility gaps across the entire customer dashboard after Steps 1-5 are complete. This step is where the Phase 7 "Responsive Tables" task is addressed — since no customer-facing `<table>` elements exist, the task is reinterpreted as ensuring all booking data displays (`.list-group` rows) are fully responsive at all breakpoints.

### Existing Files Involved

- All files modified in Steps 1-5
- [transactions.php](../transactions.php) — the complete dashboard page
- [js/app.js](../js/app.js) — any new handlers added in Steps 3-4
- [css/styles.css](../css/styles.css) — any new classes added

### Files Expected to Be Modified

- `transactions.php` (responsive class adjustments, ARIA fixes)
- `css/styles.css` (responsive overrides if needed)
- `js/app.js` (ARIA attribute fixes if needed)

### Checks to Perform

1. **Booking row responsive behavior:**
   - At 375px: booking rows stack vertically — vehicle title on one line, badges on next, dates below, amount below, action buttons full-width at bottom.
   - At 768px: partial horizontal layout — title + badges on one line, dates + amount on another.
   - At 992px+: full horizontal layout — all content on one or two lines.
   - No horizontal scroll at any breakpoint.
   - Touch targets ≥44px on all buttons and links.

2. **Dashboard cards responsive behavior:**
   - 375px: cards stack into 1 column (if 4 cards can't fit 2-per-row readably at this width).
   - 576px-1199px: 2 columns.
   - 1200px+: 4 columns (or 3+1, depending on design).

3. **Profile section responsive behavior:**
   - 375px: single column, forms full-width.
   - 992px+: two-column layout (profile info | change password).

4. **Text truncation:**
   - Long vehicle titles don't overflow — use `text-truncate` or natural wrapping.
   - Long email addresses in profile don't overflow.
   - Booking refs display correctly at all widths.

5. **Keyboard navigation:**
   - Tab through the entire dashboard — correct order, no focus traps.
   - All action buttons (Cancel, Return Early, View Receipt, Save Profile, Change Password) reachable via Tab.
   - Enter/Space activates buttons.
   - Escape closes modals.

6. **ARIA verification:**
   - All form fields have `aria-describedby` pointing to `.invalid-feedback` and helper text.
   - `aria-invalid` toggles correctly with `.is-invalid`.
   - Action buttons have descriptive `aria-label` attributes.
   - Status badges have `role="status"`.
   - Alert feedback uses `role="alert"`.
   - Section headings maintain proper hierarchy (`h1` → `h2` → `h3`).
   - All decorative icons have `aria-hidden="true"`.

7. **Reduced-motion:**
   - Confirm `data-reveal` animations collapse under `prefers-reduced-motion`.
   - No new animations introduced outside the existing motion system.

8. **Color contrast (WCAG AA):**
   - All text elements meet 4.5:1 contrast ratio.
   - Badge text against badge backgrounds.
   - Form helper text against the page background.
   - Alert text against alert backgrounds (Bootstrap defaults pass, but verify with custom styling).

### Dependencies

All of Steps 1-5 must be complete.

### Testing Requirements

- Full viewport sweep at 375px, 768px, 992px, 1400px — screenshot or verify each section.
- Keyboard-only navigation through the entire dashboard.
- Reduced-motion verification: enable `prefers-reduced-motion: reduce` → confirm no unguarded animations.
- Contrast check on all new text elements.
- Console: no new JS errors at any breakpoint.

### Acceptance Criteria

- All dashboard content renders correctly at 375px, 768px, 992px, 1400px.
- No horizontal scroll at any breakpoint.
- All touch targets ≥44px.
- Keyboard navigation works end-to-end.
- ARIA attributes correct on all interactive and status elements.
- WCAG AA contrast met on all text.
- `prefers-reduced-motion` respected.
- The "Responsive Tables" Phase 7 task is fulfilled by ensuring `.list-group` booking displays are fully responsive.

### Risks

- Low — this is a verification and fix pass on already-implemented work.

---

## Step 7: Final Review & Documentation

### Objective

Full regression pass across the entire Customer Dashboard phase and all customer-facing pages, confirming all dashboard features work correctly, no shared component or other page was affected, and documentation is updated.

### Existing Files Involved

All changes from Steps 1-6, plus every customer-facing page that may be affected: `index.php`, `vehicles.php`, `about.php`, `faq.php`, `transactions.php`.

### Files Expected to Be Modified

- `CHANGELOG.md` (phase summary entry)
- `docs/FEATURES.md` (update Transaction/Booking History entry; add Customer Profile entry; update Change Password to note UI now exists)
- `docs/BUGS.md` (mark item 10 and item 11 as resolved)
- `docs/COMPONENT_LIBRARY.md` (add profile form component, update booking card documentation)
- `docs/DESIGN_SYSTEM.md` (update Transactions section to reflect new dashboard layout)

### Dependencies

All of Steps 1-6 must be complete and individually approved.

### Testing Requirements

- `php -l` on all modified PHP files (no syntax errors).
- Full visual pass at 375px, 768px, 992px, 1400px.
- **Dashboard cards:** correct counts and metrics displayed.
- **Active bookings:** correct status badges, action buttons only on actionable bookings.
- **Cancel booking:** full flow — confirm → success alert → reload → booking shows cancelled.
- **Return Early:** full flow — confirm → receipt → success alert → reload → booking in completed section.
- **Completed bookings:** all completed bookings visible (including those completed via Return Early). "View Receipt" works.
- **Profile - view:** name, email, member since displayed correctly.
- **Profile - edit name/email:** save → success → persisted on reload → navbar updated.
- **Profile - change password:** save → success → can login with new password.
- **Notifications/alerts:** relevant contextual alerts appear.
- **Cross-page check:** confirm auth modals work identically on `index.php`, `vehicles.php`, `about.php`, `faq.php`, `transactions.php`.
- **Regression check:** booking flow on `vehicles.php` still works. Contact form on `about.php` still works. FAQ page loads. All navbar links work on all pages.
- Console: no new JS errors on any page.
- No hardcoded hex colors introduced in any file.

### Acceptance Criteria

- All Phase 7 tasks from [UI_IMPLEMENTATION_PLAN.md](UI_IMPLEMENTATION_PLAN.md) are fulfilled:
  - Dashboard Cards — enhanced summary cards on the customer dashboard
  - Bookings — improved active bookings with correct status logic and proper feedback
  - Profile — new customer profile section with name/email/password editing
  - Notifications — contextual booking alerts (Option B) or deferred (Option C)
  - History — completed bookings section with query fix and receipt viewing
  - Responsive Tables — reinterpreted as responsive booking display, verified at all breakpoints
- No regressions on any page or any non-dashboard feature.
- `CHANGELOG.md` updated with full phase summary.
- `docs/FEATURES.md`, `docs/BUGS.md`, `docs/COMPONENT_LIBRARY.md`, `docs/DESIGN_SYSTEM.md` updated.
- [BUGS.md](BUGS.md) items 10 and 11 marked as resolved.

### Risks

- Low — assuming each prior step was individually tested and approved.

---

## Documentation Updates Required

Per [CLAUDE.md](../CLAUDE.md)'s Documentation section, each implementation step's Claude Code prompt must include updating `CHANGELOG.md` with what changed, consistent with how prior phases documented their own steps. At Final Review:

- `docs/FEATURES.md` — update "Transaction / Booking History" entry to reflect new dashboard layout, query fix, and receipt viewing. Add new "Customer Profile" entry documenting the profile section and `update_profile.php`. Update "Change Password" to note customer-facing UI now exists.
- `docs/BUGS.md` — mark item 10 (action buttons based on time, not status) and item 11 (`js/app.js:51` crash) as resolved with the step number and date.
- `docs/COMPONENT_LIBRARY.md` — add profile form component documentation. Update booking card/list-group documentation to reflect the responsive improvements.
- `docs/DESIGN_SYSTEM.md` — update the Transactions/Dashboard section to reflect the new layout, card enhancements, and component patterns.

---

## Done / Partial / Not Started Summary

| Item | Status | What This Phase Does |
|---|---|---|
| `js/app.js:51` crash fix | **Not started → Step 1** | Guard unloaded plugin calls. Prerequisite for everything. |
| Dashboard page layout | **Not started → Step 2** | Transform `transactions.php` into a structured dashboard |
| Summary stat cards | **Partial → Step 2** | Enhance 2 existing cards, add 2 new metrics (total spent, next rental) |
| Active bookings display | **Done → Step 3 enhances** | Fix action-button status logic, improve responsive layout |
| Status-based action buttons | **Partial → Step 3** | Fix [BUGS.md](BUGS.md) item 10: use actual `status`, not time-computed |
| Cancel booking functionality | **Done — preserved** | Unchanged backend. Step 3 replaces `alert()` feedback |
| Return Early functionality | **Done — preserved** | Unchanged backend. Step 3 replaces `alert()` feedback |
| `alert()` → Bootstrap alerts | **Not started → Step 3** | Replace native `alert()` with Bootstrap alert feedback |
| Completed bookings display | **Partial → Step 3** | Fix query to include `status = 'completed'` bookings |
| View Receipt from history | **Not started → Step 3** | Link completed bookings to `receipt.php` |
| Confirmed-booking invoice/confirmation view | **Done → Step 3.1** | Distinct "View Booking Confirmation" link + status-aware `receipt.php` copy for `confirmed` bookings |
| Orphaned License Preview modal | **Not started → Step 3** | Remove dead markup |
| `GROUP BY` duplicate fix | **Not started → Step 3** | Prevent `LEFT JOIN transactions` from duplicating rows |
| Customer profile section | **Not started → Step 4** | New profile UI with name/email display and edit |
| `update_profile.php` endpoint | **Not started → Step 4** | New backend endpoint for customer self-service updates |
| Change password UI | **Not started → Step 4** | Wire `change_password.php` to a form (endpoint is done) |
| Navbar dropdown links | **Not started → Step 4** | Add "My Profile" (and optionally "Dashboard") links |
| `AuthValidation` second consumer | **Not started → Step 4** | Reuse validation infrastructure on profile forms |
| Booking status alerts | **Not started → Step 5** | Contextual alerts derived from booking data (Option B) |
| Responsive booking display | **Partial → Step 6** | Ensure `.list-group` booking rows stack at narrow widths |
| Accessibility pass | **Not started → Step 6** | Full ARIA, keyboard, contrast verification |
| `CHANGELOG.md` update | **Not started → Step 7** | Phase summary entry |
| Documentation updates | **Not started → Step 7** | Update FEATURES.md, BUGS.md, COMPONENT_LIBRARY.md, DESIGN_SYSTEM.md |

**Summary:** Phase 7 builds on the Phase 6 (Authentication) foundation — `AuthValidation`, `showAuthError()`, and `PMSMotion` are reused, not replaced. The genuinely new work breaks down as: (1) a prerequisite bug fix that unblocks all client-side functionality on `transactions.php` (Step 1), (2) a visual restructuring into a proper dashboard layout (Step 2), (3) a data-correctness and UX improvement pass on bookings/history (Step 3), (4) an entirely new profile feature with one new backend endpoint — the only backend work in Phase 7 (Step 4), (5) lightweight contextual alerts as a pragmatic notification solution (Step 5), and (6-7) verification and documentation passes. The backend is ~95% untouched — only `update_profile.php` is new, and `me.php` may gain one additional field.

---

*This document is a plan only. No code has been modified. Implementation proceeds one step at a time, beginning with the `js/app.js` crash fix, only after this plan is approved and a dedicated Claude Code prompt is generated and separately reviewed for that step.*
