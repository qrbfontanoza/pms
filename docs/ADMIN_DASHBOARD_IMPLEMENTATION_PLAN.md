# Admin Dashboard Implementation Plan

Derived from [ADMIN_DASHBOARD_ANALYSIS.md](ADMIN_DASHBOARD_ANALYSIS.md) (pending approval). Defines the exact implementation order and per-step requirements for the Admin Dashboard phase (Phase 8 of [UI_IMPLEMENTATION_PLAN.md](UI_IMPLEMENTATION_PLAN.md)), per [CLAUDE.md](../CLAUDE.md)'s workflow.

**Status:** Plan only. No code has been modified. Each step below is implemented only after a dedicated Claude Code prompt is generated, reviewed, and separately approved — one step at a time, per this project's established workflow.

**Key constraints, stated up front:**

1. **Three of Phase 8's eight named tasks have zero existing implementation at any layer** — Reports, Charts, and Settings. This is the same position Notifications occupied in Phase 7, and they are handled the same way: as design decisions with options and a recommendation, gated on the user's call, not silently invented.
2. **Phase 8's backend exposure is larger than Phase 7's.** Phase 7 needed one new endpoint (`update_profile.php`). Phase 8 has three categories: (a) UI-serving PHP corrections inside existing files, flagged per step; (b) two genuine business-rule bugs (`delete_booking.php`, `admin_delete_user.php`) requiring separate approval; and (c) Reports/Charts/Settings, none of which can be built without new server-side code. Every one is flagged in its step's **Note**, in the same manner Phase 7's Step 4 flagged `update_profile.php`.
3. **The step count is driven by the real scope, not forced to match Phase 7's seven or the task list's eight.** This plan has ten steps. Reports and Charts are deliberately merged into one step (they share a single aggregation dependency and a single design decision); Sidebar splits into two (a blocking structural prerequisite, then the navigation work proper).
4. **`css/styles.css` and `js/motion.js` are shared with all six customer-facing pages.** Every step that touches either must include a customer-side regression check.

---

## Implementation Sequence Overview

```
Step 1  — Admin Shell Repair & Sidebar Visibility (Prerequisite, blocking)
   ↓
Step 2  — Sidebar Navigation & Shared Topbar
   ↓
Step 3  — Dashboard Cards & Overview
   ↓
Step 4  — Reservation Tables
   ↓
Step 5  — Vehicle Management
   ↓
Step 6  — Admin Forms Standardization  (introduces js/admin.js)
   ↓
Step 7  — Reports & Charts             (DESIGN DECISION REQUIRED — gated)
   ↓
Step 8  — Settings                     (DESIGN DECISION REQUIRED — gated)
   ↓
Step 9  — Responsive & Accessibility Pass
   ↓
Step 10 — Final Review & Documentation
```

### Dependency Graph

```
Step 1  Admin Shell Repair        ── independent; blocks everything else. Fixes BUGS.md item 14
        & Sidebar Visibility         (invisible sidebar at all widths) and resolves the
                                     double-doctype documents emitted by admin_users.php and
                                     admin_vehicles.php. Until this lands, no admin page has
                                     desktop navigation and no layout work on those two pages
                                     can be trusted. Also removes the duplicate jQuery/DataTables
                                     loads and the 404 stylesheet link that come with
                                     includes/header.php. Narrowly scoped: structure only,
                                     zero visual redesign.

Step 2  Sidebar Navigation        ── depends on Step 1 (a persistent desktop sidebar must
        & Shared Topbar              actually render before its navigation semantics can be
                                     evaluated). Adds the <nav> landmark, aria-current, and
                                     extracts the three duplicated topbars into one partial.
                                     Establishes the chrome every later step inherits.

Step 3  Dashboard Cards           ── depends on Steps 1-2 (cards sit inside the shell those
        & Overview                   steps establish). Fixes the mislabeled metric, the
                                     never-matching status badges, the wrong colspan, and the
                                     unbounded Recent Messages query. Defines .metric-card.
                                     First consumer of initReveal()/initCounters() on admin.

Step 4  Reservation Tables        ── depends on Steps 1-2. Independent of Step 3 in code, but
                                     ordered after it because the dashboard's Recent
                                     Transactions table shares badge logic with view-all-data.php
                                     and should be settled once. Fixes BUGS.md item 6 (unbound
                                     Confirm handler) — the highest-impact functional bug on the
                                     admin side. Establishes the DataTables configuration
                                     baseline Step 5 reuses.

Step 5  Vehicle Management        ── depends on Steps 1-2 and on Step 4's DataTables baseline.
                                     Redirect-target repair, cascade warning, dead-field removal.

Step 6  Admin Forms               ── depends on Steps 3-5 being complete, because it removes the
        Standardization              duplicated dead handlers those steps touch and consolidates
                                     the surviving ones into js/admin.js. Doing this earlier
                                     would mean moving code that later steps then rewrite.
                                     Introduces the admin-side field-validation and feedback
                                     patterns. Fixes BUGS.md item 9 (voucher usage_limit reset).

Step 7  Reports & Charts          ── depends on Steps 1-6 (needs the shell, the topbar, the card
        [GATED]                      pattern, and the form/feedback conventions to build on).
                                     REQUIRES A DESIGN DECISION and, under every option, NEW
                                     BACKEND SQL. Also has a hard prerequisite on BUGS.md item 15
                                     (timezone) for any date-scoped output. Cannot be prompted
                                     until the decision is made and backend approval is given.

Step 8  Settings                  ── depends on Steps 1-6, and on Step 7 only for sidebar
        [GATED]                      ordering (both add a nav entry). REQUIRES A DESIGN DECISION
                                     and, under every non-trivial option, a NEW ENDPOINT.
                                     Independent of Step 7 in code.

Step 9  Responsive &              ── depends on all of Steps 1-8 being complete (or on the gated
        Accessibility Pass           steps being formally descoped). Sweeps all five admin pages
                                     plus whatever Steps 7-8 added.

Step 10 Final Review &            ── depends on all prior steps.
        Documentation
```

**A note on ordering.** Steps 1 and 2 are placed first for the same reason Phase 7 placed its crash fix first: they are not improvements, they are the condition under which the rest of the phase's work is observable. The sidebar is Phase 8's first named task, and it currently does not render at any width — evaluating "Sidebar" before fixing that would be evaluating a blank space.

---

## Step 1: Admin Shell Repair & Sidebar Visibility (Prerequisite)

### Objective

Make the admin sidebar actually render, and reduce the three incompatible admin page shells to one consistent structure, so that every subsequent step builds on a valid HTML document with working navigation. This step is **structural only** — no visual redesign, no new components, no styling changes beyond what is required to make the existing sidebar visible.

### Existing Files Involved

- [includes/admin_sidebar.php](../includes/admin_sidebar.php) — line 2, the `offcanvas offcanvas-start offcanvas-lg` class combination
- [includes/header.php](../includes/header.php) — the entire file; emits a second complete HTML document (lines 9-27) and a 404 stylesheet link (line 22)
- [includes/footer.php](../includes/footer.php) — the entire file; emits `</div></div>`, four scripts, and `</body></html>`
- [admin_users.php](../admin_users.php) — line 45 (sidebar include), line 46 (header include), line 144 (footer include), lines 146-149 (duplicate jQuery/DataTables)
- [admin_vehicles.php](../admin_vehicles.php) — line 25, line 26, line 268, lines 270-273 (same pattern)
- [admin-dashboard.php](../admin-dashboard.php) — lines 32-38 (`<body class="bg-light">` + `.d-flex.min-vh-100#adminLayout` + `.flex-grow-1`), lines 257-260 (the logout handler that neuters `logout.php`)
- [admin_vouchers.php](../admin_vouchers.php) — line 88 (`<body class="bg-light d-flex min-vh-100" id="adminLayout">`), line 90, line 91 (`.flex-grow-1`)
- [view-all-data.php](../view-all-data.php) — line 7 (`die(json_encode(...))` on an HTML page), line 31, line 33, line 35
- [css/styles.css](../css/styles.css) — lines 336-352 (`#adminSidebar` rules)
- [vehicles.php](../vehicles.php) — line 457, the **reference implementation** of the offcanvas fix already proven on this codebase
- [logout.php](../logout.php) — read-only reference; confirms `session_unset()` + `session_destroy()` are correct

### Files Expected to Be Modified

- `includes/admin_sidebar.php` (drop the bare `offcanvas` class)
- `admin_users.php` (remove the `header.php`/`footer.php` includes and the duplicate script tags; close its own document properly)
- `admin_vehicles.php` (same)
- `admin-dashboard.php` (remove the logout-intercepting handler)
- `view-all-data.php` (replace the JSON `die()` with a redirect to `admin-login.php`)
- `css/styles.css` (only if the sidebar needs a desktop-sticky rule that Bootstrap's `offcanvas-lg` does not provide — to be determined during implementation, not assumed)

### Files Expected to Be Deleted

- **None in this step.** `includes/header.php` and `includes/footer.php` become unreferenced once `admin_users.php` and `admin_vehicles.php` stop including them, but they are left in place. Deleting them is a separate decision, deferred to Step 10 so that this step remains reversible by re-adding two include lines.

### Components to Reuse

- The existing `includes/admin_sidebar.php` markup in full — it is correct and must not be rebuilt
- The `#adminSidebar` rules at `css/styles.css:336-352`, including `--sidebar-bg` and `--sidebar-hover` from `:root`
- The `vehicles.php:457` class pattern as the exact template for the fix
- The existing `#adminLayout` / `d-flex min-vh-100` / `flex-grow-1` shell already used correctly by `admin-dashboard.php`, `admin_vouchers.php`, and `view-all-data.php` — this becomes the single canonical shell

### Components to Create

- **None.** This step creates no new component. It removes duplication and restores intended behavior.

### Fix Specification

1. **Sidebar visibility.** Change `includes/admin_sidebar.php:2` from `class="offcanvas offcanvas-start offcanvas-lg"` to `class="offcanvas-lg offcanvas-start"`. This is the identical change already applied to `vehicles.php:457`. Rationale is documented in full in [BUGS.md](BUGS.md) item 14, which verified the rule order against the actual fetched Bootstrap 5.3.2 CDN stylesheet rather than inferring it.
2. **Shell unification.** Remove `include 'includes/header.php'` and `include 'includes/footer.php'` from `admin_users.php` and `admin_vehicles.php`. Both pages already emit their own complete, correct `<!doctype>`/`<head>`/`<body>` with the right stylesheet paths and already load jQuery 3.7.1, Bootstrap JS, DataTables, and `motion.js` at the bottom. Removing the includes removes: the second nested HTML document, the duplicate jQuery 3.6.0, the duplicate DataTables pair, and the 404 `../css/styles.css` request. Their `<body>` classes must be aligned to the canonical `bg-light d-flex min-vh-100" id="adminLayout"` (already correct on both) and each must wrap its main content in a `.flex-grow-1` sibling of the sidebar, matching `view-all-data.php:35`.
3. **Logout.** Delete the `$('#adminLogoutBtn').on('click', ...)` handler at `admin-dashboard.php:257-260` entirely, plus the commented-out predecessor at lines 250-256. With no handler, the anchor's `href="logout.php"` navigates normally — the same behavior the other four admin pages already have.
4. **Unauthenticated redirect.** Replace `view-all-data.php:7`'s `die(json_encode([...]))` with `header('Location: admin-login.php'); exit;`, matching the other four page-level guards.

### Dependencies

None. This is the first step and blocks all others.

### Data Requirements

None. No query is added, removed, or altered.

### CSS Requirements

- Prefer Bootstrap's native `offcanvas-lg` desktop behavior with no custom CSS at all.
- If verification shows the sidebar renders but does not stay pinned during page scroll, add a **single** scoped rule using existing tokens — no new colors, no new custom properties. Any such rule must be documented inline with the reason, matching the commenting convention already used at `css/styles.css:809-813`.
- Do not alter the existing `#adminSidebar` rules (336-352) unless a specific defect is observed.

### Bootstrap 5 Requirements

- `offcanvas-lg offcanvas-start` — drawer below 992px, static block at and above it.
- The `d-lg-none` toggle button on each page stays as-is; it is correct once the sidebar is visible at desktop.
- The `btn-close d-lg-none` inside the sidebar header stays as-is.
- Retain `data-bs-toggle="offcanvas"` / `data-bs-target="#adminSidebar"` / `aria-controls` on all five toggle buttons.

### Responsive Requirements

- **<992px:** sidebar hidden by default, opens as a left drawer via the hamburger, closes via the `btn-close` and via backdrop click.
- **≥992px:** sidebar permanently visible at 250px (`--bs-offcanvas-width`, `css/styles.css:338`), main content in `.flex-grow-1` beside it, hamburger hidden.
- No horizontal scroll introduced at 375px, 768px, 992px, or 1400px on any of the five pages.
- The 992px boundary must be checked at 991px and 993px specifically, not just "mobile and desktop".

### Accessibility Requirements

- The existing `aria-labelledby="adminSidebarLabel"` on the sidebar container is preserved.
- The `aria-label="Close sidebar"` on the close button and `aria-label="Toggle sidebar"` on the five hamburgers are preserved.
- Focus must not be trapped in a hidden offcanvas at desktop width — verify Tab traversal reaches the sidebar links and then the main content, in that order.
- No new ARIA is added in this step. The `<nav>` landmark and `aria-current` belong to Step 2.

### Testing Requirements

- Run `php -l` on all five modified PHP files. *(`php` is not on `PATH` in the current shell — invoke the Laragon PHP binary directly.)*
- **View Source** (not DevTools' normalized DOM) on `admin_users.php` and `admin_vehicles.php`: exactly one `<!doctype>`, one `<html>`, one `<head>`, one `<body>`, one `</body></html>`.
- Network tab on both pages: jQuery requested once, each DataTables file requested once, no 404 for `../css/styles.css`.
- DataTables still initializes on `#usersTable` and `#vehiclesTable` after the duplicate scripts are removed (`$.fn.DataTable` must be defined at the point the inline init runs).
- Sidebar visible without interaction at 1400px and 993px on **all five** admin pages.
- Sidebar drawer opens/closes at 991px and 375px on all five pages.
- Active-page highlight still resolves correctly on all five pages (it derives from `basename($_SERVER['PHP_SELF'])`, which the shell change does not affect).
- **Logout from each of the five admin pages**, then navigate directly to `admin-dashboard.php` and confirm the redirect to `admin-login.php`. This must be tested on `admin-dashboard.php` specifically.
- Unauthenticated visit to `view-all-data.php` lands on `admin-login.php`, not on raw JSON.
- All existing admin actions still function: edit/delete user, edit vehicle, add vehicle, confirm from dashboard, edit/delete transaction, voucher CRUD.
- Zero new console errors on all five pages.
- **Customer-side regression check:** if `css/styles.css` was touched, load `index.php`, `vehicles.php`, `transactions.php`, `about.php`, `faq.php`, `receipt.php` and confirm no layout change — in particular `vehicles.php`'s own filter sidebar.

### Acceptance Criteria

- The admin sidebar is visible without interaction at ≥992px on all five admin pages, and opens as a drawer below 992px.
- `admin_users.php` and `admin_vehicles.php` each emit exactly one HTML document.
- jQuery and DataTables are each loaded once per page; no 404 stylesheet request remains.
- Logging out from `admin-dashboard.php` actually destroys the session.
- An unauthenticated visit to any admin page lands on `admin-login.php`.
- Every existing admin action works exactly as before.
- No visual redesign has occurred — the pages look the same except that navigation is now present at desktop width.
- [BUGS.md](BUGS.md) item 14 is marked resolved; item 5 is annotated as no longer reachable from `admin_users.php`/`admin_vehicles.php` (the two pages that included `header.php`), with the file itself left in place.

### Risks

- **High.** This step removes includes from two working pages. The specific regression risk is script-ordering: `admin_users.php:152` and `admin_vehicles.php:276` call `$(document).ready` and `.DataTable()`, which require jQuery and the DataTables plugin to already be defined. Both pages load them at lines 146-149 / 270-273, *before* the inline block — so removing the `footer.php` copies is safe, but this must be verified in the browser, not reasoned about.
- **Medium.** `includes/footer.php` closes two `<div>`s. Removing it from a page that was relying on that closure to balance its own markup would leave an unclosed element. Both pages' own markup must be checked for balance after removal.
- **Medium.** Making the sidebar visible at desktop for the first time will shift every admin page's content 250px to the right. This is the intended, correct outcome, but it is a large visible change and must be presented as such rather than as a silent side effect.
- **Low.** The logout handler removal is a two-line deletion with no dependencies.

---

## Step 2: Sidebar Navigation & Shared Topbar

### Objective

Bring the now-visible sidebar up to the project's navigation and accessibility standards, and eliminate the three hand-duplicated topbars by extracting a single shared partial — establishing the page chrome that every subsequent step inherits.

### Existing Files Involved

- [includes/admin_sidebar.php](../includes/admin_sidebar.php) — line 1 (`$current` computation), line 12 (`<ul class="nav flex-column gap-2">`), lines 13-42 (six links), line 39 (`#adminLogoutBtn`)
- [admin-dashboard.php](../admin-dashboard.php) — lines 41-57 (topbar: hamburger + breadcrumb + `Hello, Admin!`)
- [view-all-data.php](../view-all-data.php) — lines 37-50 (near-identical topbar, no greeting)
- [admin_vouchers.php](../admin_vouchers.php) — lines 92-104 (near-identical topbar, no greeting)
- [admin_users.php](../admin_users.php) — lines 49-58 (bare heading row with hamburger, **no topbar**)
- [admin_vehicles.php](../admin_vehicles.php) — lines 29-41 (bare heading row with hamburger + Add button, **no topbar**)
- [admin-login.php](../admin-login.php) — line 22 reference for `$_SESSION['admin_name']`; **note this page loads no JS and no Font Awesome**
- [css/styles.css](../css/styles.css) — lines 336-352
- `references/inspiration/screenshots/admin-dashboard/fleet-pro-admin-dashboard/fleet-pro-admin-dashboard.png` — grouped sidebar sections, persistent desktop rail
- `references/inspiration/screenshots/admin-dashboard/rent-q-admin-dashboard/rent-q-admin-dashboard.png` — labelled sidebar groups (Main / Management / Support / Others)

### Files Expected to Be Modified

- `includes/admin_sidebar.php`
- `admin-dashboard.php`, `view-all-data.php`, `admin_vouchers.php`, `admin_users.php`, `admin_vehicles.php` (each replaces its topbar/heading row with the shared partial)
- `css/styles.css` (sidebar active/focus states, only if Bootstrap utilities are insufficient)

### Files Expected to Be Created

- **`includes/admin_topbar.php`** — one shared partial accepting a page title and an optional right-hand action slot.

### Components to Reuse

- `includes/admin_sidebar.php`'s entire existing structure and its `basename($_SERVER['PHP_SELF'])` active-state mechanism
- `--sidebar-bg` / `--sidebar-hover` / `--primary` / `--secondary` / `--border` from `:root`
- Bootstrap `.breadcrumb`, `.nav`, `.nav-link`, `.offcanvas` components
- Font Awesome icons already in the sidebar
- `$_SESSION['admin_name']`, already populated at `admin-login.php:22` and currently unused

### Components to Create

- **`includes/admin_topbar.php`** — replaces five divergent implementations with one. Contains:
  - The `d-lg-none` sidebar toggle button (identical across all five pages today)
  - An `<h1>` page title — **this is where the missing `<h1>` on every admin page comes from.** The title is passed in by the including page via a variable set immediately before the include (matching the existing `$current` convention in `admin_sidebar.php`), defaulting to a safe value if unset.
  - A breadcrumb `<nav aria-label="breadcrumb">`, preserving the existing pattern from three pages
  - The admin's name from `$_SESSION['admin_name']`, replacing the hardcoded `Hello, Admin!` at `admin-dashboard.php:56` — escaped with `htmlspecialchars()`
  - An optional right-hand action slot, so `admin_vehicles.php` can keep its "Add Vehicle" button (`:38-40`) and `admin_vouchers.php` its "Add New Voucher" button (`:111-113`) in the topbar rather than in a separate row
- **Sidebar `<nav>` landmark** — wrap the existing `<ul class="nav flex-column">` in `<nav aria-label="Admin navigation">`.
- **`aria-current="page"`** on the active sidebar link, alongside the existing `.active` class. Both are needed: the class drives the visual state, the attribute drives assistive-technology announcement. This mirrors the pattern already used on the customer navbar (`js/app.js:9`).
- **A visible keyboard focus state** on sidebar links using `--accent-focus` (`css/styles.css:12-14`), which exists specifically because `--accent` fails WCAG 1.4.11's 3:1 requirement for UI-component contrast.

### Dependencies

Step 1 must be complete — the sidebar must actually render before its navigation semantics can be evaluated or tested.

### Data Requirements

None beyond `$_SESSION['admin_name']`, which is already set at login. No query added.

### CSS Requirements

- Prefer Bootstrap utilities. The existing `#adminSidebar .nav-link` rules (`css/styles.css:342-352`) already handle padding, radius, and hover/active background.
- The only likely additions: a focus-visible outline using `--accent-focus`, and a left accent bar or font-weight shift on `.active` if the current background-only treatment reads as insufficiently distinct.
- If sidebar groups/labels are added (as both references do), style the group label with existing utilities (`text-uppercase small text-muted fw-semibold`) — no new CSS, no new colors.
- Any new rule must be prefixed/scoped (`#adminSidebar ...` or `.admin-topbar`) so it cannot leak to customer pages, which load the same stylesheet.

### Bootstrap 5 Requirements

- Topbar: `d-flex align-items-center justify-content-between bg-white border-bottom px-4 py-3` — the exact pattern already at `admin-dashboard.php:41`, preserved so this is an extraction rather than a redesign.
- Breadcrumb: existing `<nav aria-label="breadcrumb"> → <ol class="breadcrumb mb-0"> → <li class="breadcrumb-item active" aria-current="page">`.
- Sidebar links: `.nav-link` with `.active`.
- Right-hand action slot: `.ms-auto` with the page's existing button markup unchanged.

### Responsive Requirements

- Topbar readable and non-wrapping at 375px — the page title must truncate (`text-truncate`) rather than push the toggle or the action button off-screen.
- The admin name may be hidden below `sm` (`d-none d-sm-inline`) if space is tight; it is decorative, not functional.
- The action slot's button collapses to icon-only or wraps below the title at 375px, whichever preserves a ≥44px touch target.
- Sidebar behavior per Step 1: drawer <992px, static ≥992px.
- Verify at 375px, 768px, 991px, 993px, 1400px on all five pages.

### Accessibility Requirements

- Exactly one `<h1>` per admin page, supplied by the topbar partial. This resolves analysis item 11 for all five pages at once.
- Sidebar wrapped in `<nav aria-label="Admin navigation">`.
- `aria-current="page"` on the active sidebar link.
- Visible focus indicator on every sidebar link and on the toggle button, meeting WCAG 1.4.11's 3:1 against the sidebar background — use `--accent-focus`, not `--accent`.
- Keyboard order: toggle → sidebar links → topbar actions → main content.
- Sidebar icons remain decorative — verify each carries the text label beside it (they all do today); add `aria-hidden="true"` to the `<i>` elements.
- Breadcrumb keeps `aria-label="breadcrumb"` and `aria-current="page"` on its active item.

### Testing Requirements

- `php -l` on all six modified/created PHP files.
- Every one of the five admin pages renders the shared topbar with the correct page title.
- Exactly one `<h1>` per page, and the heading order runs `h1 → h2 → h3` with no skips (`admin_vouchers.php`'s existing `<h2>` at line 110 and `admin_users.php`/`admin_vehicles.php`'s `<h4>` headings must be reconciled with the new `<h1>`).
- The admin's real name renders in the topbar; verify with an admin whose `admins.name` contains an apostrophe or `<` to confirm `htmlspecialchars()` escaping.
- The active sidebar link is visually highlighted **and** carries `aria-current="page"` — checked on each of the five pages individually, since the active state is filename-derived.
- Screen-reader spot check (NVDA or Windows Narrator): the sidebar is announced as a navigation landmark; the active link is announced as current.
- Keyboard-only traversal of all five pages: focus is always visible, order is logical, no trap.
- `admin_vehicles.php`'s Add Vehicle button and `admin_vouchers.php`'s Add New Voucher button still open their modals from their new position in the topbar.
- No horizontal scroll at 375px on any page.
- Zero new console errors.
- **Customer-side regression check** if `css/styles.css` was touched.

### Acceptance Criteria

- One `includes/admin_topbar.php` is used by all five admin pages; no page retains a hand-rolled topbar or bare heading row.
- Every admin page has exactly one `<h1>` and a valid heading hierarchy.
- The sidebar is a labelled `<nav>` landmark with `aria-current="page"` on the active link and a visible focus state on all links.
- `Hello, Admin!` is replaced by the authenticated admin's actual name.
- Existing page-level actions (Add Vehicle, Add New Voucher) are preserved and functional.
- No new colors introduced; all styling uses existing `:root` tokens.

### Risks

- **Medium.** Five pages are edited simultaneously. A per-page verification pass is required — a partial for five callers is exactly the kind of change that works on four of them.
- **Medium.** `admin_users.php` and `admin_vehicles.php` currently have their heading row *inside* their `.container py-4`, whereas the other three have the topbar *outside* the container as a full-bleed bar. Extracting one partial means choosing one placement, which changes the visual layout of two pages. This is the intended consolidation but is a real visible change on those two pages.
- **Low.** `$_SESSION['admin_name']` could theoretically be unset for a session created before it was written. Guard with `?? 'Admin'`.
- **Low.** Adding an `<h1>` where pages currently start at `<h4>`/`<h2>` changes visual type scale. Use Bootstrap's `.h4`/`.fs-*` utilities on the `<h1>` to keep the current visual weight while fixing the semantics — do not enlarge the type as a side effect of a semantic fix.

---

## Step 3: Dashboard Cards & Overview

### Objective

Turn `admin-dashboard.php` into a correct, legible overview page: fix the metric that contradicts its own label, define the `.metric-card` class that currently does nothing, apply the entrance and counter animations that are already loaded and idle, fix the status badges that never match real data, fix the wrong `colspan`, and bound the unlimited Recent Messages query.

### Existing Files Involved

- [admin-dashboard.php](../admin-dashboard.php) — lines 12-14 (three metric queries), lines 61-89 (three metric cards), line 85 (the mislabeled card), lines 93-163 (Recent Transactions card), lines 114-120 (its query), lines 123-128 (the `match` that never matches), line 152 (`colspan='5'` on 10 columns), lines 165-202 (Recent Messages card), line 181 (the unbounded query), lines 209-236 (the dead `editUserModal`), lines 262-353 (dead handlers)
- [css/styles.css](../css/styles.css) — `:root` tokens (3-19), motion tokens (21-48); `.metric-card` is **absent** and must be added
- [js/motion.js](../js/motion.js) — `initReveal()` (10-46), `initCounters()` (122-162); both already run on this page via line 165/167
- [includes/admin_topbar.php](../includes/admin_topbar.php) — created in Step 2
- [DATABASE.md](DATABASE.md) — `bookings.status` enum values, `bookings.total_amount`
- `references/inspiration/screenshots/admin-dashboard/fleet-pro-admin-dashboard/fleet-pro-admin-dashboard.png` — four-tile KPI row, white card + tinted icon chip
- `references/inspiration/screenshots/admin-dashboard/rent-q-admin-dashboard/rent-q-admin-dashboard.png` — four-tile KPI row, value-over-label

### Files Expected to Be Modified

- `admin-dashboard.php`
- `css/styles.css` (add `.metric-card`)

### Components to Reuse

- The existing three-card structure (`admin-dashboard.php:61-89`) — enhanced, not replaced
- Font Awesome icons already on the cards (`fa-car`, `fa-users`, `fa-key`)
- `initCounters()` / `.js-count` / `data-target` — already loaded, zero admin consumers today, purpose-built for these cards, includes a `prefers-reduced-motion` bail-out and an accessible visually-hidden static sibling
- `initReveal()` / `[data-reveal]` / `[data-reveal-stagger]` — same situation
- `:root` design tokens; `--motion-stagger` for card sequencing
- Bootstrap `.card`, `.row g-3`, `.badge`, `.table-responsive`
- `includes/admin_topbar.php` from Step 2 (this page's `<h1>` now comes from there)

### Components to Create

- **`.metric-card` CSS rule** — currently applied to three elements and defined nowhere. Define it with existing tokens: a subtle border/shadow consistent with the project's `bg-white rounded-3 shadow-sm` convention, and a hover lift guarded by `@media (prefers-reduced-motion: no-preference)`, matching the `.team-member-card` pattern already at `css/styles.css:826-839`.
- **A fourth metric card** so the row divides evenly (three `col-md-3` cards currently leave a visible quarter-width gap). Both references use a four-tile row. Recommended fourth metric: **Pending Bookings** — the number that actually drives admin action, and the one a "Confirm" workflow needs surfaced.
- **A corrected third card.** `$activeRentals` counts `status='confirmed'` but renders as "Total Bookings" (`:85`). Two defensible resolutions; **recommendation: relabel to "Active Rentals"** so the label matches the query and the variable name, since changing the query instead would silently change a number an admin may already be reading. The choice should be stated explicitly in the step's prompt rather than assumed.
- **Corrected status badges** — replace the `match` at `:123-128` with a lowercase-keyed map covering all four real enum values: `pending`, `confirmed`, `completed`, `cancelled`. `cancelled` must be visually distinct from `pending` (they are currently both gray). Use existing Bootstrap contextual classes only; introduce no new colors.
- **`LIMIT` on the Recent Messages query** with a "View all" affordance — or, if no messages page exists to link to, the header is changed to reflect what is actually shown. **Note:** there is no messages management page in this project; `admin-dashboard.php` is the only place messages are ever displayed. Adding a `LIMIT` without a destination hides data an admin currently has access to. Recommended: `LIMIT 5` plus wrapping the messages table in DataTables, which gives search and pagination without a new page or new endpoint.

### Dependencies

Steps 1 and 2. The cards sit inside the shell Step 1 fixed and below the topbar Step 2 created.

### Data Requirements

- **Existing, unchanged:** `$totalCars`, `$totalUsers`, `$activeRentals` (`:12-14`).
- **One new single-value query** for the Pending Bookings card: `SELECT COUNT(*) AS total FROM bookings WHERE status='pending'` — identical in shape to the three that already exist, on the same connection, in the same place.
- **Optionally one more** for a revenue metric: `SELECT COALESCE(SUM(total_amount),0) AS total FROM bookings WHERE status IN ('confirmed','completed')`. Per [ADMIN_DASHBOARD_ANALYSIS.md](ADMIN_DASHBOARD_ANALYSIS.md) §2.9, `bookings` is the correct aggregation source; `transactions` is not (migration-dependent columns, `NULL` amounts on return rows). If added, this makes the row five cards — grid must be adjusted accordingly, or revenue replaces a weaker metric.
- `LIMIT` added to the messages query.
- **No date-scoped metric is proposed in this step.** Anything of the form "this month" or "last 7 days" depends on [BUGS.md](BUGS.md) item 15 (timezone) and belongs to Step 7, where that dependency is handled explicitly.

### CSS Requirements

- `.metric-card` defined in `css/styles.css` using only `:root` tokens.
- Icon color drawn from `--secondary` or `--accent`; the existing `text-primary`/`text-success`/`text-warning` Bootstrap classes on the three cards may be retained for continuity, but must not be extended with any new color.
- Hover/lift transitions guarded by `@media (prefers-reduced-motion: no-preference)`.
- Any new class prefixed and scoped so it cannot affect customer pages sharing this stylesheet.

### Bootstrap 5 Requirements

- Card grid: `row g-3` with `col-12 col-sm-6 col-xl-3` for four cards — one column at <576px, two at sm, four at xl. (The current `col-md-3` jumps straight from one column to four at 768px, which is cramped.)
- Cards keep `card border-0 shadow-sm` + `card-body text-center`.
- Badges use only existing contextual classes.
- Tables keep their `.table-responsive` wrappers.
- Section headings become `<h2>` beneath the topbar's `<h1>`.

### Responsive Requirements

- 1 / 2 / 4 columns at xs / sm / xl.
- Metric values must not overflow their card at 375px — verify with a six-digit revenue figure, not a two-digit test value.
- Recent Transactions is a 10-column table; it must scroll horizontally within `.table-responsive` and must not cause page-level horizontal scroll at 375px.
- Verify at 375px, 768px, 992px, 1400px.

### Accessibility Requirements

- Section headings are `<h2>`, under the topbar's single `<h1>`.
- Card icons get `aria-hidden="true"` — the text label carries the meaning.
- `.js-count` spans must follow `initCounters()`'s established contract: the animating span is `aria-hidden`, with a `.visually-hidden` sibling carrying the correct final value from first paint, so assistive technology never announces an intermediate number.
- `data-reveal` animations respect `prefers-reduced-motion` (already handled inside `initReveal()`).
- Status badges must not convey status by color alone — each already contains its status text, which must be preserved.
- If the messages table becomes a DataTable, its generated search input needs an associated label.

### Testing Requirements

- `php -l` on `admin-dashboard.php`.
- Each card's number verified against a hand-run SQL query on the same database.
- Counter animation reaches the correct final value; the `.visually-hidden` sibling reads correctly from first paint (inspect before the animation completes).
- With `prefers-reduced-motion: reduce` set at the OS level: final values shown immediately, no reveal animation, no hover lift.
- **Seed one booking in each of the four statuses** and verify each renders a distinct, correct badge — this is the direct regression test for [BUGS.md](BUGS.md) item 7.
- Empty-state row spans the full table width — verified by temporarily filtering the query to return zero rows.
- Recent Messages renders bounded; if DataTables was added, search and pagination work.
- The Confirm button on Recent Transactions still fires exactly one request and still confirms the booking.
- Cards render 1 / 2 / 4 across breakpoints with no gap and no overflow.
- Zero new console errors.
- **Customer-side regression check** for the `css/styles.css` change.

### Acceptance Criteria

- Four (or five) metric cards render in an evenly-divided responsive grid.
- No card's label contradicts its query.
- `.metric-card` has a real CSS definition using existing tokens only.
- Status badges render correctly and distinctly for all four `bookings.status` values.
- The empty-state `colspan` matches the table's column count.
- Recent Messages is bounded, with search/pagination if DataTables was applied.
- `data-reveal` and `.js-count` are in use, respecting `prefers-reduced-motion`.
- The existing Confirm action is preserved and functional.
- No new colors.

### Risks

- **Medium.** The status-badge and metric-label corrections are PHP edits inside an existing file. They are presentation logic, but they are PHP — see the **Note** below.
- **Medium.** Adding a `LIMIT` to Recent Messages removes an admin's current (if awkward) access to the full message history. The DataTables mitigation is what makes this acceptable; without it, this is a feature removal and should not proceed.
- **Low.** `.metric-card` currently does nothing, so defining it has no existing appearance to regress.
- **Low.** The dead `editUserModal` and its handlers (`:209-236`, `:262-353`) are present on this page. They are **not** removed in this step — that is Step 6's scope, to keep this step's diff reviewable. They must simply not be modified here.

> **Note — backend modification flag.** This step edits PHP inside `admin-dashboard.php`: it adds one (or two) `COUNT`/`SUM` query, adds a `LIMIT`, changes a `match` expression, and changes a card label. None of these creates a new endpoint, changes a schema, or alters business behavior — they are presentation corrections and a bounded read query. However, [CLAUDE.md](../CLAUDE.md) states *"Backend modifications should only be suggested unless explicitly requested."* These changes are therefore **flagged for explicit confirmation in this step's prompt**, in the same manner Phase 7's Step 4 flagged `update_profile.php`. If the user prefers to keep Phase 8 strictly frontend, the metric-label fix, the badge fix, the `LIMIT`, and the new card must all be deferred, and this step reduces to CSS and motion attributes only.

---

## Step 4: Reservation Tables

### Objective

Repair the admin's core workflow surface. Fix the Confirm button that is unbound on page load, make status visually distinguishable, add the missing empty state, correct the ID column's string sort and the sortable Actions column, and fix the header typo — across both the All Transactions page and the dashboard's Recent Transactions table.

### Existing Files Involved

- [view-all-data.php](../view-all-data.php) — lines 11-18 (query), line 61 (`#transactionsTable`), lines 62-75 (headers; line 67 typo), lines 77-121 (row loop, no empty state), lines 90-95 (badge logic), lines 98-119 (action buttons, unlabeled), lines 132-159 (Edit Times modal), line 169-171 (DataTables init), **lines 216-270 (the nested handler bug)**
- [admin-dashboard.php](../admin-dashboard.php) — lines 93-163 (Recent Transactions), lines 356-379 (the correctly-scoped Confirm handler, the reference for the fix)
- [admin_confirm_booking.php](../admin_confirm_booking.php) — **read-only reference. Must not be modified.** Transactional, row-locked, guarded.
- [update_booking_time.php](../update_booking_time.php) — endpoint for the Edit Times modal
- [delete_booking.php](../delete_booking.php) — line 30, the dead status guard (**backend, gated — see Note**)
- [DATABASE.md](DATABASE.md) — line 65 (`transactions_booking_fk ON DELETE CASCADE`), line 99 (status enum)
- [js/motion.js](../js/motion.js) — `PMSMotion.setButtonLoading()`, already used by all six handlers on this page
- `references/inspiration/screenshots/admin-dashboard/fleet-pro-admin-dashboard/fleet-pro-admin-dashboard-booking-list.png` — status chips, filter pills, explicit pagination text
- `references/inspiration/ui/fleet-pro-template/forms-tables/tables/tables.png` — Bootstrap 5 table conventions

### Files Expected to Be Modified

- `view-all-data.php`
- `admin-dashboard.php` (Recent Transactions badge logic — shared with Step 3; if Step 3 already corrected it, this step only verifies)
- `css/styles.css` (only if a status-chip style is needed beyond Bootstrap badges)

### Components to Reuse

- The existing DataTables integration and its `.table-responsive` wrapper
- `PMSMotion.setButtonLoading()` on all action buttons (already in use)
- The existing Edit Booking Times modal and `update_booking_time.php`
- The correctly-scoped Confirm handler at `admin-dashboard.php:356-379` as the structural template for the fix
- Bootstrap `.badge` with existing contextual classes
- `includes/admin_topbar.php` from Step 2

### Components to Create

- **A correctly-scoped `.confirm-transaction` handler** — moved out of the `.delete-transaction` handler's `if (confirm(...))` block to become a sibling registration inside `$(document).ready()`. This is a **brace-structure correction, not a rewrite**: the handler's body is already correct and must be preserved verbatim.
- **A complete status badge map** covering `pending`, `confirmed`, `completed`, `cancelled` with four distinct treatments (currently `pending` and `cancelled` both render `secondary`).
- **An empty-state row** — `view-all-data.php` currently renders a bare `<tbody>` on zero rows. `colspan` must equal 10.
- **DataTables `columnDefs`** — the configuration baseline for the whole admin side:
  - `orderable: false` and `searchable: false` on the Actions column
  - numeric-aware sort on the ID column (currently rendered as `#12`, sorted as a string, so `#9` sorts after `#10`)
  - `pageLength` and `language` strings consistent with the project's tone
  - This configuration is reused verbatim by Step 5 and Step 6.
- **`aria-label` on every icon-only action button** — Confirm, Delete, Edit. Each must name the booking it acts on (e.g. `aria-label="Confirm booking #142"`), not just the action, since a table of unlabeled "Confirm" buttons is not navigable.
- **A cascade warning in the delete confirmation.** Deleting a booking cascades to its `transactions` row ([DATABASE.md](DATABASE.md) line 65). The current `confirm()` says only "This action cannot be undone." If Step 6's confirmation modal is not yet built, the warning text is added to the existing `confirm()` string here and migrated in Step 6.
- **Optional: status filter pills** above the table, filtering client-side through DataTables' existing API. Purely additive, no backend. Both references use this pattern. Recommended but separable if the step is running long.

### Dependencies

Steps 1 and 2. Step 3 if the shared badge logic is corrected there first.

### Data Requirements

No query change is required for this step's core scope. Two observations recorded for later, **not acted on here**:

- `view-all-data.php:11-18` selects `b.status` and `b.total_amount` redundantly alongside `b.*`.
- The query has no `LIMIT` — every booking is server-rendered and DataTables paginates client-side. This is the standard client-side-DataTables trade-off and is acceptable at current scale. Server-side pagination is out of scope for a UI phase and is noted in Step 10's documentation update as a future scaling concern.

### CSS Requirements

- Prefer Bootstrap `.badge` classes; add no new colors.
- If a status chip needs a softer treatment than a solid badge (as in both references), use Bootstrap 5.3's `text-bg-*` utilities or existing tokens — not new hex values.
- Any filter-pill styling uses `.btn-outline-*` / `.btn-check`, no custom CSS.

### Bootstrap 5 Requirements

- Table keeps `table table-hover` inside `.table-responsive`.
- Badges use existing contextual classes.
- Filter pills, if added: `.btn-group` with `.btn-check` + `.btn-outline-secondary`.
- The Edit Times modal keeps its current structure — restructuring modals is Step 6's scope.

### Responsive Requirements

- A 10-column table cannot fit 375px; it must scroll inside `.table-responsive` without causing page-level horizontal scroll.
- Action buttons must remain ≥44px touch targets at mobile widths.
- DataTables' generated controls (search box, length menu, pagination) must not overflow at 375px — verify, since the default Bootstrap 5 integration lays them out in a two-column row.
- Filter pills wrap rather than overflow.
- Verify at 375px, 768px, 992px, 1400px.

### Accessibility Requirements

- Every icon-only action button gets a descriptive `aria-label` naming its target row.
- Status must not be conveyed by color alone — the badge text stays.
- DataTables' generated search input needs an associated label.
- Table headers are `<th>` within `<thead>` (already correct).
- Sorting state must be announced — DataTables sets `aria-sort` on sortable headers by default; verify it is present and that the now-unsortable Actions column no longer advertises itself as sortable.
- Keyboard: every action button reachable and activatable by keyboard, focus visible.

### Testing Requirements

- `php -l` on both modified PHP files.
- **The primary regression test:** load `view-all-data.php` fresh, click Confirm on a pending booking as the **first** interaction on the page, and verify (a) the booking is confirmed, (b) exactly one request to `admin_confirm_booking.php` fires in the Network tab. Then delete a transaction, then click Confirm on another pending booking, and verify **still exactly one** request fires (the old code stacked duplicate handlers on each delete).
- Verify Confirm still works from `admin-dashboard.php`.
- Verify `admin_confirm_booking.php`'s guarantees are intact after confirming: `bookings.status` becomes `confirmed`, `vehicles.units_total` decrements by exactly one, and exactly one `transactions` row is created.
- Seed bookings in all four statuses; verify four visually distinct badges on both tables.
- Empty state renders with `colspan="10"` when the query returns zero rows.
- Sort the ID column: `#9` sorts before `#10`.
- The Actions column header is not sortable and its markup is not matched by the search box.
- Edit Times still saves and reloads correctly.
- Delete still works and its confirmation now states the transaction cascade.
- Every action button announces a meaningful name in a screen reader.
- Zero new console errors.

### Acceptance Criteria

- Confirming a booking from `view-all-data.php` works on first interaction after a page load and fires exactly one request, regardless of prior actions on the page.
- All four booking statuses render distinct, correct badges on both tables.
- An empty result set renders a correct, full-width empty state.
- The ID column sorts numerically; the Actions column is neither sortable nor searchable.
- Every icon-only action button has a descriptive accessible name.
- The delete confirmation discloses the transaction cascade.
- `admin_confirm_booking.php` is unmodified.
- [BUGS.md](BUGS.md) item 6 is marked resolved; item 7 is marked resolved (here or in Step 3).

### Risks

- **Medium.** The `.confirm-transaction` fix is a brace-structure change inside a nested block. Care is needed to move the registration out without altering its body or accidentally changing the delete handler's own scope. The two handlers must be verified independently after the change.
- **Medium.** Changing the ID column's sort changes the visible default row order on any table crossing a digit boundary. This is a correction, but it is a visible behavioral change and should be stated in the changelog rather than slipped in.
- **Low.** Badge and `aria-label` changes are additive.
- **Low.** Filter pills are purely client-side over DataTables' existing API and can be dropped without affecting the rest of the step.

> **Note — backend modification flag.** [BUGS.md](BUGS.md) item 8 (`delete_booking.php:30` guards on the status value `'active'`, which does not exist in the enum) is **deliberately excluded from this step and requires separate explicit approval.** It is the highest-consequence defect on the admin side — a `confirmed` booking can be hard-deleted, its `transactions` row cascades away, and the inventory unit decremented at confirmation is never returned — but fixing it means deciding a **business rule**, not a UI behavior: should deletion of a `confirmed` booking be blocked outright, or should it return the inventory unit first, or should it become a soft delete? That decision, and the endpoint change implementing it, are backend work under [CLAUDE.md](../CLAUDE.md)'s rule and must be approved on their own terms. Until then, this step must **not** make the delete action more prominent or easier to reach than it is today. The same flag applies to [BUGS.md](BUGS.md)-adjacent item `admin_delete_user.php:28` (the `users.id` vs `admins.id` guard), which is out of scope for every step in this plan.

---

## Step 5: Vehicle Management

### Objective

Repair the vehicle management workflow so admin actions produce visible confirmation on the page the admin is working on, remove the form field whose value is silently discarded, disclose the deletion cascade, and bring the vehicles table in line with Step 4's DataTables baseline.

### Existing Files Involved

- [admin_vehicles.php](../admin_vehicles.php) — lines 43-51 (the three unreachable success/error alerts), line 56 (`#vehiclesTable`), lines 57-68 (headers; `thead.table-dark`, unlike every other admin table), lines 70-105 (row loop), line 85 (thumbnail cell), lines 87-98 (action buttons; line 98 is a GET delete link with a bare `confirm()`), line 103 (empty state, `colspan='8'` — correct), lines 114-190 (Add modal; **line 176 is the discarded `details` textarea**; lines 132-137 options **with** `value`), lines 193-266 (Edit modal; lines 212-217 options **without** `value`), line 277 (DataTables init)
- [admin_add_vehicle.php](../admin_add_vehicle.php) — lines 41-50 (the INSERT that ignores `details`), **line 52 (`Location: admin-dashboard.php?success=1`)**, lines 27-33 (`exif_imagetype()` validation — must be preserved exactly)
- [admin_edit_vehicle.php](../admin_edit_vehicle.php) — **line 79 (`Location: admin-dashboard.php?updated=1`)**
- [admin_delete_vehicle.php](../admin_delete_vehicle.php) — **line 20 (`Location: admin-dashboard.php?deleted=1`)**; line 18 (`execute()` with no return check)
- [DATABASE.md](DATABASE.md) — line 68 (deleting a vehicle cascades to every booking for it, and transitively to every transaction), lines 98-99 (`vehicles` has no `details` column)
- Step 4's DataTables `columnDefs` baseline
- `references/inspiration/screenshots/admin-dashboard/fleet-pro-admin-dashboard/fleet-pro-admin-dashboard-add-vehicle.png` and `-edit-vehicle.png`

### Files Expected to Be Modified

- `admin_vehicles.php`
- `admin_add_vehicle.php` (redirect target only)
- `admin_edit_vehicle.php` (redirect target only)
- `admin_delete_vehicle.php` (redirect target only)

### Components to Reuse

- The entire existing CRUD flow and all three endpoints' validation logic — particularly `exif_imagetype()` content validation and the random-filename generation, which are the most careful code in the vehicle path and must survive this step untouched
- The existing `?error=` / `?updated=1` / `?deleted=1` alert markup at `admin_vehicles.php:43-51` — it is already written and correct; it is simply never reached
- Step 4's DataTables `columnDefs` configuration
- Bootstrap `.alert`, `.modal`, `.form-control`, `.form-select`, `.form-check`
- `includes/admin_topbar.php` (the Add Vehicle button moves into its action slot in Step 2)

### Components to Create

- **Corrected redirect targets.** All three endpoints redirect to `admin_vehicles.php` instead of `admin-dashboard.php`, so the existing success alerts fire and the admin stays on the page they were working on. `admin_add_vehicle.php:52` additionally needs its parameter aligned — it sends `?success=1`, but `admin_vehicles.php` only reads `?updated` and `?deleted`, so a matching `?added=1` branch is added to the alert block.
- **Removal of the `details` textarea** (`admin_vehicles.php:176`). Whatever an admin types there is silently discarded — there is no `details` column and the INSERT does not reference it. Removing it is strictly better than a field that pretends to save. *(The alternative — adding a `details` column — is a schema change and is explicitly out of scope; see the Note.)*
- **A cascade warning on delete.** The current `onclick="return confirm('Delete this vehicle?')"` does not mention that deletion destroys every booking ever made for the vehicle and every transaction on those bookings. The warning text must state this. If Step 6's confirmation modal is not yet built, the text goes in the existing `confirm()` and migrates in Step 6.
- **Aligned Category options.** The Edit modal's `<option>Sedan</option>` (no `value`) becomes `<option value="Sedan">Sedan</option>`, matching the Add modal. Functionally equivalent today, but the inconsistency is a latent bug the moment a display label diverges from its stored value.
- **DataTables `columnDefs`** applied to `#vehiclesTable`: Image and Actions columns non-sortable and non-searchable; numeric sort on ID.
- **`aria-label` on the action buttons**, naming the vehicle each acts on.
- **`alt` text on the thumbnail images** (`admin_vehicles.php:85` currently emits `<img>` with no `alt`). Use the vehicle title.
- **A `thead` treatment consistent with the other admin tables.** `admin_vehicles.php:57` uses `table-dark` while `admin_users.php:64` uses `table-light` and the rest use no class at all. Pick one — recommended `table-light`, matching the more recently-modernized `admin_users.php`.

### Dependencies

Steps 1 and 2. Step 4's DataTables baseline.

### Data Requirements

No query change. No schema change. The `details` field is **removed from the form**, not added to the database.

### CSS Requirements

- No new CSS expected. The thumbnail's inline `style` at line 85 (`width:70px;height:45px;object-fit:cover`) matches the project's existing inline-sizing convention for images (documented at `css/styles.css:806-813`) and may stay.
- No new colors.

### Bootstrap 5 Requirements

- Table: `table table-striped align-middle` inside `.table-responsive` (existing).
- Modals: `.modal-lg` (existing). **Their form structure is not restructured here** — that is Step 6's scope, so that the modal-convention unification happens once, across all seven admin modals, rather than piecemeal.
- Alerts: existing `.alert-danger` / `.alert-success` markup, now actually reachable. Add `alert-dismissible` with a close button so a stale success message can be dismissed without a reload.
- Form fields: existing `.form-control` / `.form-select` / `.form-check`.

### Responsive Requirements

- An 8-column table with an image column scrolls inside `.table-responsive` at 375px without page-level horizontal scroll.
- Both modals use `.modal-lg`; their `row g-3` field grids must stack cleanly at 375px — verify the `col-md-4` triples in particular.
- The file input must remain usable and ≥44px tall at mobile widths.
- Verify at 375px, 768px, 992px, 1400px.

### Accessibility Requirements

- Thumbnails get meaningful `alt` text (the vehicle title).
- Action buttons get descriptive `aria-label`s naming the vehicle.
- Both modals already have `aria-labelledby` pointing at their titles — preserve.
- Every form field already has a `<label>` — verify each is correctly associated (by `for`/`id` or by wrapping), since several admin labels elsewhere are not.
- Success and error alerts should be announced — add `role="alert"`.
- The delete link is an `<a>` performing a destructive action; ensure it is keyboard-activatable and that its accessible name states what will be deleted.

### Testing Requirements

- `php -l` on all four modified PHP files.
- **Add a vehicle:** lands back on `admin_vehicles.php` with a visible success alert; the new row appears; the image is written to `assets/` with a random filename.
- **Edit a vehicle** with and without a new image: lands back on `admin_vehicles.php` with a success alert; changes persist; the old image is not orphaned unexpectedly.
- **Delete a vehicle:** the confirmation states the booking/transaction cascade; lands back on `admin_vehicles.php` with a success alert.
- **Image validation regression:** rename a `.txt` file to `.jpg` and upload it — `exif_imagetype()` must still reject it and redirect with the "Invalid image file." error. This verifies the redirect change did not disturb the validation path.
- **Error path regression:** submit the Add form with a non-numeric price — the `?error=` alert must still display.
- The Edit modal populates every field correctly from the row's `data-*` attributes, including Category now that it has explicit `value`s.
- The `details` textarea is gone from the Add modal, and adding a vehicle still succeeds.
- ID column sorts numerically; Image and Actions columns are not sortable or searchable.
- Empty state renders correctly (`colspan="8"`) when no vehicles exist.
- Zero new console errors.

### Acceptance Criteria

- Adding, editing, and deleting a vehicle each return the admin to `admin_vehicles.php` with a visible, dismissible success alert.
- No form field silently discards its value.
- The delete confirmation discloses the cascade to bookings and transactions.
- Both Category selects use explicit `value` attributes.
- The vehicles table follows Step 4's DataTables baseline and matches the other admin tables' header treatment.
- Thumbnails have `alt` text; action buttons have accessible names.
- All three endpoints' image validation and random-filename logic are unchanged.
- [ADMIN_DASHBOARD_ANALYSIS.md](ADMIN_DASHBOARD_ANALYSIS.md) items 6, 26, 27 are resolved.

### Risks

- **Medium.** Changing the post-action destination is a workflow change: admins are currently deposited on the dashboard after every vehicle action and will now stay on the vehicles list. This is the correct behavior and is what makes the existing alerts reachable, but it is a visible change in habit and must be called out in the changelog, not slipped in.
- **Medium.** The three endpoints contain the most carefully written upload validation in the project. The edits here are confined to `header('Location: ...')` strings; nothing in the validation, upload, or SQL path may be touched. Any diff touching lines outside the redirects should be rejected in review.
- **Low.** Removing the `details` textarea removes a field admins may have believed was saving data. Worth a changelog line noting the data was never persisted.
- **Low.** Adding `value` attributes to the Edit Category options is behavior-preserving as long as the values exactly match the existing option text.

> **Note — backend modification flag.** This step edits three PHP endpoint files. The edits are confined to `header('Location: ...')` targets — no query, no validation, no business logic, no schema. This is a **UI-serving correction** (it makes existing, already-written success feedback reachable), but it is still a change to backend files and is therefore **flagged for explicit confirmation in this step's prompt**. Separately, adding a `details` column to `vehicles` to make that form field functional would be a **schema change** and is explicitly **not** proposed here — the field is removed instead. If the user would prefer the field made functional rather than removed, that is a schema change requiring its own approval and its own migration script in `db_migrations/`.

---

## Step 6: Admin Forms Standardization

### Objective

Give the admin section one form convention, one feedback convention, and one place for its JavaScript. Replace `alert()` and `confirm()` with Bootstrap feedback, add field-level validation, remove the duplicated dead handlers, and fix the voucher modal ID collision that silently corrupts data.

### Existing Files Involved

- [admin_vouchers.php](../admin_vouchers.php) — lines 14-71 (the action switch), lines 172-249 (Add and Edit modals; **the duplicate `id="usage_limit"` and crossed `for` attributes**), line 293 (`$('#edit_usage_limit')` matching zero elements), lines 261-345 (three AJAX handlers, **none with an `error:` callback**), lines 272/302/328 (manual `JSON.parse`)
- [admin_users.php](../admin_users.php) — lines 116-142 (Edit User modal), lines 155-223 (handlers, the more modern of the two copies)
- [admin-dashboard.php](../admin-dashboard.php) — lines 209-236 (**dead duplicate** `editUserModal`), lines 262-274 (**dead** `.editVehicleBtn` handler targeting nine IDs that exist only on `admin_vehicles.php`), lines 277-353 (**dead duplicate** user handlers), lines 356-379 (the **live** Confirm handler)
- [admin_vehicles.php](../admin_vehicles.php) — lines 114-190, 193-266 (modals where `<form>` wraps the whole `.modal-content`)
- [view-all-data.php](../view-all-data.php) — lines 132-159 (modal where `<form>` wraps only `.modal-body`)
- [js/motion.js](../js/motion.js) — `setButtonLoading()` (52-66), `initModalFocus()` (99-109) — **`initModalFocus()` must keep working after any modal restructuring**
- [js/app.js](../js/app.js) — lines 1038-1155 (`AuthValidation`) and 1159-1170 (`showAuthError`) — **read-only reference for the pattern.** Not loaded on admin pages and must not be.
- `references/inspiration/ui/fleet-pro-template/forms-tables/validation/validation.png` and `forms/forms.png`

### Files Expected to Be Modified

- `admin_vouchers.php`, `admin_users.php`, `admin_vehicles.php`, `view-all-data.php`, `admin-dashboard.php` (all five: inline JS extracted, modals unified, feedback replaced)
- `css/styles.css` (only if the confirmation modal or validation feedback needs styling Bootstrap does not provide)

### Files Expected to Be Created

- **`js/admin.js`** — the admin section's shared script, loaded on all five admin pages.

### Components to Reuse

- `PMSMotion.setButtonLoading()` — already the established admin pattern on nine handlers; extend to all, and ensure every one clears it on the error path
- `initModalFocus()` — already auto-focusing the first input of all seven admin modals; the unified modal structure must not break its selector (`input:not([type="hidden"]), select, textarea` filtered to `:visible`)
- Bootstrap `.alert`, `.modal`, `.form-control`, `.is-invalid`, `.invalid-feedback`, `.was-validated`
- The **pattern** of `AuthValidation` and `showAuthError()` — blur/input triggers, `.is-invalid` toggling, inline feedback text, shake-and-focus on submit error. **The pattern, not the code.**
- Existing `:root` and motion tokens

### Components to Create

- **`js/admin.js`**, containing:
  - **`AdminValidation`** — a self-contained module mirroring `AuthValidation`'s shape (`rules.required`, `rules.email`, `rules.minLength`, `rules.numeric`, `rules.min`, `attachRealTime`, `validateAll`, `clearForm`) writing `.is-invalid` + `.invalid-feedback` on blur and clearing on input. This is a **re-implementation of the pattern**, deliberately not a copy of `AuthValidation` and deliberately not achieved by loading `js/app.js` — that file is 62KB, is not IIFE-wrapped, and binds customer-only handlers (booking modal, navbar auth, voucher UI, `#contactForm`) against elements that do not exist on admin pages. Wrapped in an IIFE exposing a single `window.AdminValidation`.
  - **`showAdminError($alert, message)` / `showAdminSuccess($alert, message)`** — render into a Bootstrap `.alert` inside the relevant modal or page region, replacing every `alert()` call.
  - **`confirmAction({title, body, confirmLabel, variant})`** — a promise-returning Bootstrap confirmation modal replacing every `confirm()`. This is where Step 4's and Step 5's cascade warnings finally live, with proper formatting instead of a plain-text browser dialog.
  - **The consolidated user edit/delete handlers**, migrated from `admin_users.php` (the more modern copy, which already uses `PMSMotion`), with the dead dashboard duplicates deleted.
  - **The consolidated voucher, transaction, and vehicle handlers**, each gaining a proper `error:` callback.
- **One unified modal + form DOM convention.** Two shapes coexist today: `<form>` wrapping the entire `.modal-content` (`admin_vehicles.php`) versus `<form>` wrapping only `.modal-body` with the submit button outside it in `.modal-footer` (`admin_users.php`, `admin_vouchers.php`, `view-all-data.php`). **Recommendation: `<form>` wraps the entire `.modal-content`** — it is the only shape where the footer's submit button is a real `type="submit"` inside its form, which gives Enter-to-submit and native HTML5 validation for free. The three pages using the other shape currently work around this with `type="button"` + a click handler (`admin_vouchers.php:260,297`).
- **A dismissible in-modal alert region** in every admin modal, as the target for `showAdminError`/`showAdminSuccess`.
- **The voucher ID collision fix**: the Edit modal's input becomes `id="edit_usage_limit"` (which the existing JS at line 293 already targets), and both labels' `for` attributes are corrected to point at their own modal's input.
- **`dataType: 'json'`** on the three voucher AJAX calls, replacing manual `JSON.parse()`.

### Dependencies

Steps 3, 4, and 5 must be complete. This step moves and consolidates handlers that those steps modify; doing it earlier would mean relocating code that later steps then rewrite.

### Data Requirements

None. No query, endpoint, or schema change. **The one PHP-adjacent change is markup-only** — correcting `id` and `for` attributes in `admin_vouchers.php`'s modals. The endpoint at `admin_vouchers.php:38-52` already reads `$_POST['usage_limit']` correctly; it receives a wrong value today only because the form field is never populated.

### CSS Requirements

- Bootstrap's `.is-invalid` / `.invalid-feedback` are used as-is — no custom validation styling.
- The confirmation modal uses standard Bootstrap modal classes; only the destructive-variant accent may need a rule, and it must use an existing token.
- A shake animation on submit error, if used, must use existing motion tokens and be guarded by `@media (prefers-reduced-motion: no-preference)`.
- No new colors.

### Bootstrap 5 Requirements

- `.modal` / `.modal-dialog` / `.modal-content` / `.modal-header` / `.modal-body` / `.modal-footer` — unchanged classes, unified `<form>` placement.
- `.alert alert-danger` / `.alert-success` with `.alert-dismissible` inside modals.
- `.form-control` + `.is-invalid` + `.invalid-feedback` for field errors.
- `novalidate` on forms where `AdminValidation` supersedes native bubbles — but **only** where every native constraint is replicated, so validation is not weakened.
- Confirmation modal: `.modal-sm`, with the destructive action as `.btn-danger`.

### Responsive Requirements

- All seven modals usable at 375px: fields stack, the footer's buttons do not overflow, `.modal-dialog-scrollable` where content exceeds viewport height (the two `.modal-lg` vehicle modals in particular).
- Inline `.invalid-feedback` text wraps rather than overflowing its field.
- The confirmation modal is legible and its buttons are ≥44px at 375px.
- Verify at 375px, 768px, 992px, 1400px.

### Accessibility Requirements

- Every input's `<label>` correctly associated via `for`/`id` — this step fixes the crossed voucher labels and must audit all seven modals for the same class of error.
- **No duplicate `id` attributes anywhere in any admin document** — verifiable with `document.querySelectorAll('[id]')` and a uniqueness check.
- Invalid fields get `aria-invalid="true"` and `aria-describedby` pointing at their `.invalid-feedback`.
- The in-modal alert region gets `role="alert"` so errors are announced.
- The confirmation modal gets `role="alertdialog"`, `aria-labelledby`, `aria-describedby`, and returns focus to the triggering button on dismissal.
- `initModalFocus()`'s first-input focus must still work after restructuring — verified per modal.
- Enter-to-submit must work in every modal form once the `<form>` wraps the footer.

### Testing Requirements

- `php -l` on all five modified PHP files.
- **Every admin form submits successfully:** edit user, delete user, add vehicle, edit vehicle, delete vehicle, add voucher, edit voucher, delete voucher, edit booking times, delete booking, confirm booking.
- **Every failure path shows an in-modal Bootstrap alert, not a browser `alert()`.**
- **Every destructive action shows the Bootstrap confirmation modal, not a browser `confirm()`**, and cancelling it performs no action.
- **Loading-state clearing on error:** with DevTools throttled to Offline, trigger each of the three voucher actions and confirm the button re-enables and an error message appears. This is the direct regression test for the missing `error:` callbacks.
- **The voucher regression test:** edit a voucher's code **without touching Usage Limit**, save, and verify the stored `usage_limit` is unchanged. Today this silently resets it to 1. ([BUGS.md](BUGS.md) item 9.)
- `document.querySelectorAll('#usage_limit').length === 1` on `admin_vouchers.php`.
- Field-level validation fires on blur and clears on input, on at least the Edit User (name, email), Add Vehicle (title, price, units, seats), and Add Voucher (code, percentage, amount, limit) forms.
- Submitting an invalid form focuses the first invalid field.
- Enter-to-submit works in every modal.
- `initModalFocus()` still focuses the first field of each of the seven modals.
- The dead dashboard handlers are gone and the live Confirm handler on that page still works.
- Zero new console errors on all five pages.
- **Customer-side regression check** if `css/styles.css` was touched — and confirm `js/admin.js` is loaded on admin pages only, never on customer pages.

### Acceptance Criteria

- `js/admin.js` exists, is loaded on all five admin pages, and is the single home for admin JavaScript.
- Zero `alert()` and zero `confirm()` calls remain in admin code.
- All seven admin modals use one form structure and one feedback pattern.
- Field-level validation with `.is-invalid` / `.invalid-feedback` works on every admin form.
- Every admin AJAX handler has an `error:` callback that clears its loading state and surfaces a message.
- Editing a voucher no longer resets its usage limit; no duplicate `id` exists in any admin document.
- The duplicated dead modal and handlers are removed from `admin-dashboard.php`.
- [BUGS.md](BUGS.md) item 9 is marked resolved.

### Risks

- **High (breadth).** This step touches every admin form on all five pages. It is the largest step in the plan, and it is placed after Steps 3-5 specifically so it consolidates settled code rather than code still in flux. If it proves too large in practice, the natural split is: (6a) `js/admin.js` + feedback replacement; (6b) modal unification + validation.
- **Medium.** Changing where `<form>` sits relative to `.modal-footer` changes submit semantics on three pages. A button that was `type="button"` with a click handler becomes `type="submit"` inside a form — if the handler is not updated in step, the form will submit twice or navigate away.
- **Medium.** `initModalFocus()` selects the first `:visible` non-hidden input. Adding an alert region above the fields must not introduce a focusable element that steals that focus.
- **Medium.** Removing the dead dashboard handlers requires certainty that they are dead. The analysis verified that `admin-dashboard.php` renders no `.edit-user`, `.delete-user`, or `.editVehicleBtn` element and contains no `#editVehicleModal` — this must be re-verified with a grep at implementation time before deletion.
- **Low.** The voucher ID fix is a three-attribute markup change; the JS already targets the correct ID.

---

## Step 7: Reports & Charts (Design Decision Required)

### Objective

Deliver Phase 8's "Reports" and "Charts" tasks — **or formally descope them.** Neither exists at any layer today: no page, no route, no sidebar link, no query, no endpoint, no library, no `<canvas>`, no chart markup. This step cannot be scoped until a design decision is made.

**Why Reports and Charts are one step, not two.** Charts have no independent data source — they visualize exactly the aggregations Reports needs. Scoping them separately would mean either building the aggregation layer twice or building charts with no data behind them. They share one design decision and one backend dependency, so they share one step.

### Design Options

**Option A — Dashboard Insights Panel (no new page, no new library, minimum backend).**
Add a "Business Overview" section to `admin-dashboard.php` beneath the metric cards: a revenue-by-month bar list, a bookings-by-status breakdown, and a top-vehicles-by-bookings list. Rendered with Bootstrap's `.progress` component and `.list-group` — **zero new dependencies.**
- *Precedent:* this is exactly how FleetPro's own "Revenue Trend" and "Revenue Breakdown" panels are built (`fleet-pro-admin-dashboard-revenue-report.png`) — horizontal bars, not a charting library.
- *Backend:* three `GROUP BY` queries added to `admin-dashboard.php`. No new file, no new endpoint, no schema change.
- *Pros:* smallest surface; no CDN dependency added to a project that already loads five; no new page to secure; satisfies both "Reports" and "Charts" in a defensible, honest way; fits the existing card layout.
- *Cons:* not a "reports page"; no date-range filtering; no export; less visually impressive than a real chart library.
- *Timezone exposure:* **moderate.** A revenue-by-month bucket uses `GROUP BY YEAR(created_at), MONTH(created_at)` evaluated **in MySQL**, which runs on Manila time — so the buckets are correct without touching PHP's timezone. [BUGS.md](BUGS.md) item 15 only bites when PHP computes a boundary and passes it to SQL. Option A can be built to avoid that entirely.

**Option B — Dedicated Reports Page with a chart library.**
A new `admin_reports.php` with a filter row (report type + date range), a KPI tile row, one or two Chart.js canvases, and a detail table — following the RentQ and FleetPro reference layouts closely.
- *Backend:* several `GROUP BY` queries, parameterized by an admin-supplied date range. Possibly a JSON endpoint if charts fetch asynchronously.
- *New dependency:* Chart.js via CDN (~65KB), the first new frontend dependency added since the project began.
- *Pros:* matches the design references directly; genuinely satisfies both named tasks; extensible.
- *Cons:* largest surface; new page needs its own auth guard, its own sidebar entry, its own shell; a new CDN dependency in a project with no lockfile and no build tooling; **admin-supplied date ranges make [BUGS.md](BUGS.md) item 15 a hard blocker** — PHP computes the boundaries, MySQL stores Manila time, and every booking created 16:00-23:59 local lands in the wrong bucket. Item 15's own recommended fix is application-wide and architectural.
- *Additional dependency:* [BUGS.md](BUGS.md) item 15 must be resolved **first**, as its own approved change.

**Option C — Defer to a later phase.**
Treat Reports and Charts the way Phase 7's analysis proposed treating Notifications: as a cross-cutting feature that deserves its own phase rather than two steps inside a UI phase. Phase 8 delivers Sidebar, Dashboard Cards, Vehicle Management, Reservation Tables, Forms, and Settings, and formally records Reports/Charts as descoped with the reasoning.
- *Pros:* keeps Phase 8 a UI phase, honoring [CLAUDE.md](../CLAUDE.md)'s backend rule cleanly; avoids adding a dependency and an architectural timezone fix under a UI banner; the analysis has already documented exactly what a future Reports phase would need.
- *Cons:* two of Phase 8's eight named tasks go undelivered; the acceptance criterion "Admin workflow preserved" is still met, but the task list is not fully discharged.

### Recommendation

**Option A**, with Option C as the fallback if any backend work is declined.

Reasoning: Option A delivers something real for both named tasks, adds **zero** new dependencies to a project that has none under version control, requires no new page and no new auth surface, can be built to sidestep [BUGS.md](BUGS.md) item 15 entirely by keeping all date arithmetic inside MySQL, and has direct precedent in the project's own design reference. Option B is the better long-term answer but drags in a CDN dependency, a new page, and an architectural timezone fix — three things that each deserve their own decision rather than riding along inside a UI step.

**This is a recommendation, not a decision. The final call is the user's**, and this step cannot be prompted for implementation until it is made.

### Everything Below Assumes Option A

### Existing Files Involved

- [admin-dashboard.php](../admin-dashboard.php) — the metric card row from Step 3; the new panel sits beneath it
- [css/styles.css](../css/styles.css) — `:root` tokens; **`--chart-1` … `--chart-5` at lines 70-74 are declared under `.dark`, not `:root`, and have zero consumers** — if used, they must first be promoted to `:root`
- [DATABASE.md](DATABASE.md) — §2.9 of the analysis establishes `bookings` as the correct aggregation source and `transactions` as unsuitable (migration-dependent columns; `NULL` amounts on return rows)
- [BUGS.md](BUGS.md) item 15 — the timezone constraint that shapes how the queries must be written
- `includes/admin_sidebar.php` — no new entry needed under Option A
- `references/inspiration/screenshots/admin-dashboard/fleet-pro-admin-dashboard/fleet-pro-admin-dashboard-revenue-report.png`

### Files Expected to Be Modified

- `admin-dashboard.php` (three `GROUP BY` queries + the panel markup)
- `css/styles.css` (promote `--chart-*` tokens to `:root` if used; otherwise untouched)

### Files Expected to Be Created

- **None under Option A.** (Option B would create `admin_reports.php` and possibly `api_admin_stats.php`.)

### Components to Reuse

- Bootstrap `.progress` / `.progress-bar` — the chart primitive
- Bootstrap `.list-group` / `.list-group-item` for breakdown rows
- The `.metric-card` treatment defined in Step 3, for panel consistency
- `[data-reveal]` / `[data-reveal-stagger]` for panel entrance
- `:root` tokens; `--chart-1` … `--chart-5` only after promotion to `:root`
- `includes/admin_topbar.php`

### Components to Create

- **Revenue by month** — a horizontal bar list, last 6 months, `GROUP BY YEAR(created_at), MONTH(created_at)` computed entirely in MySQL, bars scaled to the maximum value in the set.
- **Bookings by status** — a four-row breakdown with counts and proportional bars, one per enum value.
- **Top vehicles by bookings** — a ranked `.list-group`, `GROUP BY vehicle_id` joined to `vehicles.title`, limited to five.
- Each panel gets an accessible text representation of its data, not bars alone (see Accessibility).

### Dependencies

- Steps 1-6 complete.
- **The design decision above must be made.**
- **Backend approval must be granted** for the three aggregation queries.
- If Option B is chosen instead: [BUGS.md](BUGS.md) item 15 must be resolved first, as its own separately-approved change.

### Data Requirements

Three read-only `GROUP BY` queries against `bookings` (joined to `vehicles` for the third). **All date arithmetic stays inside MySQL** — no PHP-computed date boundary is passed as a parameter, which is what keeps [BUGS.md](BUGS.md) item 15 out of scope for this option. `transactions` is **not** used as a source, per the analysis. No schema change. No new table. No new index required at current scale, though `bookings.created_at` and `bookings.vehicle_id` should be checked against [DATABASE.md](DATABASE.md)'s index list before the queries are considered final.

### CSS Requirements

- Bootstrap `.progress` styling with bar color from existing tokens.
- If `--chart-1` … `--chart-5` are used for multi-series color, they must first be **moved or duplicated into `:root`** — they are currently unreachable outside `.dark`. This must be an explicit, documented change, not an assumption that they already work.
- No new colors beyond the existing token set.

### Bootstrap 5 Requirements

- `.progress` with `.progress-bar`, `role="progressbar"`, and the `aria-valuenow`/`aria-valuemin`/`aria-valuemax` set.
- `.list-group` / `.list-group-item d-flex justify-content-between align-items-center`.
- Panel grid: `row g-3` with `col-12 col-lg-6` or `col-lg-4`.
- Card structure matching Step 3's.

### Responsive Requirements

- Panels stack to one column below `lg`.
- Bar labels and values must not overflow at 375px — long vehicle titles need `text-truncate` with a `title` attribute.
- Currency values must not wrap mid-number.
- Verify at 375px, 768px, 992px, 1400px.

### Accessibility Requirements

- **Bars must not be the only representation of the data.** Every bar carries its numeric value as visible text beside it, and every `.progress-bar` carries `role="progressbar"` with correct `aria-valuenow`/`aria-valuemin`/`aria-valuemax` and an `aria-label` naming its series.
- Panel headings are `<h2>` or `<h3>`, consistent with the page's hierarchy under the topbar's `<h1>`.
- Color is never the sole carrier of meaning — each series is labelled in text.
- Bar fill transitions respect `prefers-reduced-motion`.
- Empty state: if a period has no data, the panel says so in text rather than rendering an empty bar.

### Testing Requirements

- `php -l` on `admin-dashboard.php`.
- **Every figure verified against a hand-run SQL query** on the same database — this is the core correctness test and cannot be skipped.
- **Timezone verification:** create a booking at a wall-clock time between 16:00 and 23:59 Manila and confirm it lands in the correct month bucket. This is the direct test that Option A's MySQL-side date arithmetic sidesteps [BUGS.md](BUGS.md) item 15.
- Bars scale correctly relative to the maximum, including when all values are equal and when one value is zero.
- Empty-data state renders a text message, not a broken or zero-width bar.
- Screen reader announces each bar's label and value.
- `prefers-reduced-motion: reduce` disables bar fill animation.
- Panels render correctly at all four breakpoints.
- Existing dashboard content (metric cards, Recent Transactions, Recent Messages, Confirm action) is unaffected.
- Zero new console errors.
- **Customer-side regression check** if `css/styles.css` was touched — particularly if `--chart-*` tokens were promoted to `:root`.

### Acceptance Criteria

- The dashboard has a business-overview panel with at least three data views.
- Every figure is verifiably correct against the database.
- No new frontend dependency has been added.
- Every visualization has an accessible text equivalent.
- All date bucketing is correct under the Manila/UTC mismatch.
- No new colors introduced.
- The design decision is recorded in [CHANGELOG.md](../CHANGELOG.md) and [FEATURES.md](FEATURES.md), including the options considered and why the chosen one was chosen.

### Risks

- **High.** This step is gated on a decision that has not been made. It must not be prompted for implementation until the decision is recorded.
- **High.** Under **any** option this step writes new server-side SQL — a backend modification requiring explicit approval.
- **High (Option B only).** Admin-supplied date ranges make [BUGS.md](BUGS.md) item 15 a blocking prerequisite, and item 15's own fix is application-wide and architectural, touching every PHP entry point.
- **Medium.** Aggregate queries that look right and are subtly wrong (double-counting through a join, silently excluding `NULL`s, misbucketing a boundary date) are the characteristic failure mode here. Hand-verification of every figure is mandatory, not optional.
- **Medium.** Adding queries to `admin-dashboard.php` increases its load time on a page that already runs an unbounded messages query (bounded in Step 3).
- **Low (Option A).** No new dependency, no new page, no new auth surface.

> **Note — backend modification flag.** **This step requires explicit, separate approval before its prompt can be generated, on two counts.** First, the **design decision** above — Option A, B, or C — is the user's to make; this plan recommends but does not decide. Second, **every option except C requires new server-side SQL**, which crosses [CLAUDE.md](../CLAUDE.md)'s rule that *"Backend modifications should only be suggested unless explicitly requested."* This is the same gate Phase 7's Step 4 applied to `update_profile.php`, applied here to a larger surface. If Option B is chosen, a **third** approval is required for the [BUGS.md](BUGS.md) item 15 timezone fix, which is an architectural change touching every PHP entry point and is explicitly out of scope for a UI phase. No part of this step may be implemented on the assumption that any of these approvals will be granted.

---

## Step 8: Settings (Design Decision Required)

### Objective

Deliver Phase 8's "Settings" task — **or formally descope it.** Nothing exists: a repo-wide grep for `settings`, `preference`, `site_config`, and `admin_profile` across `*.php` and `*.js` returns no matches at all. There is no settings page, no configuration table, no admin-profile UI, and no endpoint.

### Design Options

**Option A — Admin Account Settings (recommended).**
A new `admin_settings.php` where the logged-in admin views and edits their own `admins` row: name, email, and a change-password form. Mirrors RentQ's "Account Settings" reading of the task (`rent-q-admin-dashboard-settings.png`) and FleetPro's admin-profile page.
- *Backend:* one new endpoint (`admin_update_profile.php`) doing for `admins` what `update_profile.php` already does for `users` — a **directly parallel, already-approved-in-principle pattern** from Phase 7 Step 4.
- *Schema:* none. The `admins` table already has `id`, `name`, `email`, `password`.
- *Pros:* smallest meaningful scope; a real gap (an admin currently cannot change their own password by any means — there is no admin forgot-password flow either, per [FEATURES.md](FEATURES.md) line 369); reuses an established endpoint pattern; makes `$_SESSION['admin_name']` — surfaced in the Step 2 topbar — actually editable.
- *Cons:* not "system settings"; one new endpoint.

**Option B — Application Settings.**
A settings page for business-level configuration: default rental terms, minimum age, cancellation-refund thresholds, contact details — values currently hardcoded across `reserve.php`, `cancel_booking.php`, and elsewhere.
- *Backend:* a **new `settings` table**, a new endpoint, plus refactoring every consumer of the currently-hardcoded values to read from it.
- *Pros:* genuinely valuable; removes real magic numbers.
- *Cons:* by far the largest option; a schema change plus a cross-cutting refactor of customer-facing business logic; **substantially outside a UI phase's remit.** [PROJECT_AUDIT.md](PROJECT_AUDIT.md) confirms no configuration mechanism of any kind exists to build on.

**Option C — Combined (A now, B recorded as future work).**
Build Option A, and document Option B's scope in [FEATURES.md](FEATURES.md) as an identified future enhancement without building it.

**Option D — Defer entirely.**
Record Settings as descoped from Phase 8 with the reasoning, alongside whatever is decided for Reports/Charts.

### Recommendation

**Option C** — build Account Settings, record Application Settings as future work.

Reasoning: Option A closes a real, verified gap (no admin can change their own password by any route today), needs no schema change, and reuses a pattern this project already approved and shipped in Phase 7. Option B is a business-logic refactor wearing a UI label. Option C delivers the task honestly while leaving an accurate record of what a fuller Settings feature would require.

**This is a recommendation, not a decision. The final call is the user's**, and this step cannot be prompted for implementation until it is made.

### Everything Below Assumes Option A/C

### Existing Files Involved

- [admin-login.php](../admin-login.php) — lines 12-22, the `admins` table query and `$_SESSION['admin_id']`/`$_SESSION['admin_name']` assignment
- [update_profile.php](../update_profile.php) — **the reference implementation.** Per [FEATURES.md](FEATURES.md) line 283: POST-only, session-authenticated, validates non-empty name and email format, checks uniqueness excluding the caller's own row, updates via a prepared statement, and **accepts no user ID from the request body — only the session's own.** `admin_update_profile.php` must replicate this exactly.
- [change_password.php](../change_password.php) — the reference for current-password verification before a password change
- [includes/admin_sidebar.php](../includes/admin_sidebar.php) — a Settings entry is added
- [includes/admin_topbar.php](../includes/admin_topbar.php) — from Step 2
- [js/admin.js](../js/admin.js) — from Step 6; `AdminValidation` and the feedback helpers are consumed here
- [DATABASE.md](DATABASE.md) — the `admins` table
- `references/inspiration/screenshots/admin-dashboard/rent-q-admin-dashboard/rent-q-admin-dashboard-settings.png` and `fleet-pro-admin-dashboard-admin-profile.png`

### Files Expected to Be Modified

- `includes/admin_sidebar.php` (new Settings entry)
- `js/admin.js` (settings form handlers)

### Files Expected to Be Created

- **`admin_settings.php`** — the page
- **`admin_update_profile.php`** — the endpoint (**new backend file — see Note**)
- **`admin_change_password.php`** — the password endpoint, **or** an action branch inside `admin_update_profile.php`, mirroring `admin_vouchers.php`'s single-file action-switch pattern

### Components to Reuse

- `update_profile.php`'s validation and session-scoping pattern — replicated for `admins`, not shared, since the two tables and session keys are entirely separate
- `change_password.php`'s current-password verification pattern
- `AdminValidation`, `showAdminError()`, `showAdminSuccess()` from Step 6
- `PMSMotion.setButtonLoading()`
- `includes/admin_sidebar.php`, `includes/admin_topbar.php`
- The canonical page shell established in Step 1
- Bootstrap `.card`, `.form-control`, `.is-invalid`, `.invalid-feedback`, `.alert`
- `:root` tokens

### Components to Create

- **`admin_settings.php`** — a page using the canonical shell, with two cards: **Profile Information** (name, email; pre-filled from the session's `admins` row) and **Change Password** (current, new, confirm).
- **`admin_update_profile.php`** — POST-only; requires `$_SESSION['admin_id']`; **accepts no ID from the request body**; validates non-empty name and valid email; checks email uniqueness within `admins` excluding the caller's own row; updates via a prepared statement; returns JSON; refreshes `$_SESSION['admin_name']` on success so the Step 2 topbar updates on the next load.
- **Password change** — verifies the current password with `password_verify()` against `admins.password`, enforces a minimum length matching the customer-side rule (6 characters, per `register.php`), hashes with `password_hash()`, updates.
- **A Settings entry in the sidebar**, placed above Logout.
- **Read-only account context** — account email and, if a `created_at` column exists on `admins`, member-since. *(This must be verified against the live schema before it is displayed — [DATABASE.md](DATABASE.md) line 97 lists `admins` as `id`, `name`, `email`, `password` with no `created_at`, so this element is conditional, not assumed.)*

### Dependencies

- Steps 1-6 complete (shell, topbar, sidebar, `js/admin.js`).
- **The design decision above must be made.**
- **Explicit backend approval for one to two new endpoint files.**
- Independent of Step 7 in code; coordinate only on sidebar ordering if both add an entry.

### Data Requirements

- Read: `SELECT name, email FROM admins WHERE id = ?` using the session's `admin_id` only.
- Write: `UPDATE admins SET name = ?, email = ? WHERE id = ?`, and separately `UPDATE admins SET password = ? WHERE id = ?`.
- **No schema change.** No new table.
- The email-uniqueness check runs within `admins` only. Note that `admins` and `users` are separate tables with no cross-table uniqueness constraint — an email may exist in both today, and this step must **not** silently change that behavior.

### CSS Requirements

- No new CSS expected — Bootstrap form and card utilities cover the page.
- No new colors.

### Bootstrap 5 Requirements

- Page shell per Step 1; topbar per Step 2.
- Two `.card`s in a `row g-3` with `col-12 col-lg-6`, or stacked full-width — whichever reads better at the reference proportions.
- `.form-control` + `.is-invalid` + `.invalid-feedback`.
- `.alert` with `role="alert"` for save results.
- Submit buttons with `PMSMotion.setButtonLoading()`.

### Responsive Requirements

- Cards stack to one column below `lg`.
- Forms fully usable at 375px; inputs ≥44px tall.
- Buttons full-width below `sm` if that improves the touch target.
- Verify at 375px, 768px, 992px, 1400px.

### Accessibility Requirements

- One `<h1>` from the topbar; card titles as `<h2>`.
- Every input has an associated `<label>`.
- Password fields get `autocomplete="current-password"` / `autocomplete="new-password"`.
- Invalid fields get `aria-invalid="true"` and `aria-describedby` pointing at their feedback.
- Save results announced via `role="alert"`.
- Submitting an invalid form focuses the first invalid field.
- The new sidebar link participates in the Step 2 `aria-current` mechanism.

### Testing Requirements

- `php -l` on all new and modified PHP files.
- **Unauthenticated access to `admin_settings.php`, `admin_update_profile.php`, and the password endpoint must be rejected** — page redirects to `admin-login.php`, endpoints return 403 JSON. This is the first new admin surface added in this phase and its guard must be verified explicitly, not assumed from the pattern.
- **Privilege test: attempt to update a *different* admin's record by posting an `id`/`admin_id` parameter.** The endpoint must ignore it entirely and act only on the session's own row.
- Edit the name: saves; the topbar reflects it after reload.
- Edit the email: saves; a duplicate within `admins` is rejected with a clear message; an email that exists in `users` but not `admins` is **accepted** (documenting the current cross-table behavior rather than silently changing it).
- Change the password: a wrong current password is rejected; a correct one succeeds; **log out and log back in with the new password**; confirm the old password no longer works.
- Field validation fires on blur and clears on input.
- Loading states clear on both success and simulated network error.
- The Settings sidebar link is highlighted with `aria-current="page"` when on the page.
- Zero new console errors.

### Acceptance Criteria

- `admin_settings.php` exists, is auth-guarded, and uses the canonical shell, sidebar, and topbar.
- An admin can view and update their own name and email, and change their own password.
- The endpoint derives identity from the session only and ignores any ID in the request body.
- The topbar reflects a changed name.
- Validation and feedback use the Step 6 patterns.
- The sidebar has a Settings entry with correct active state.
- If Option C: Application Settings is recorded in [FEATURES.md](FEATURES.md) as identified future work, with its schema and refactor scope described.

### Risks

- **High.** Gated on a decision that has not been made. Must not be prompted until it is recorded.
- **High.** Creates one to two new backend endpoint files — the clearest backend modification in this plan.
- **High (security).** A new authenticated endpoint that mutates credentials is the highest-risk surface in Phase 8. It must derive identity from `$_SESSION['admin_id']` alone. The project has **no CSRF protection anywhere** ([PROJECT_AUDIT.md](PROJECT_AUDIT.md), "Known Limitations"), so this endpoint inherits that gap — it should not be presented as more secure than the rest of the codebase, and adding CSRF protection is a separate, project-wide decision.
- **Medium.** A password-change endpoint that silently fails would leave an admin unable to log in. The test must include a full logout/login cycle with the new password, and the old password must be confirmed non-working.
- **Low.** The page itself is a straightforward two-card form using patterns Step 6 established.

> **Note — backend modification flag.** **This step requires explicit, separate approval before its prompt can be generated, on two counts.** First, the **design decision** above is the user's to make. Second, Option A/C **creates one to two entirely new backend endpoint files** (`admin_update_profile.php`, and a password endpoint), which crosses [CLAUDE.md](../CLAUDE.md)'s rule that *"Backend modifications should only be suggested unless explicitly requested."* This is the direct analogue of Phase 7 Step 4's `update_profile.php` flag, and it must be treated the same way. Option B would additionally require a **new database table and a refactor of customer-facing business logic**, which is a substantially larger approval and is not recommended within a UI phase. No part of this step may be implemented on the assumption that approval will be granted.

---

## Step 9: Responsive & Accessibility Pass

### Objective

Sweep every admin page at every target breakpoint, complete the accessibility work that individual steps deferred, and verify keyboard and screen-reader usability across the whole admin section — including whatever Steps 7 and 8 added, or confirming their absence if descoped.

### Existing Files Involved

All admin pages and partials as they stand after Steps 1-8:
- `admin-login.php`, `admin-dashboard.php`, `admin_users.php`, `admin_vehicles.php`, `admin_vouchers.php`, `view-all-data.php`
- `includes/admin_sidebar.php`, `includes/admin_topbar.php`
- `css/styles.css`, `js/admin.js`, `js/motion.js`
- `admin_settings.php` (if Step 8 was built)
- [UI_IMPLEMENTATION_PLAN.md](UI_IMPLEMENTATION_PLAN.md) Phase 9 and Phase 10 checklists, which this step anticipates for the admin surface

### Files Expected to Be Modified

Any of the above, as defects are found. This step is diagnostic first, corrective second.

### Checks to Perform

**Breakpoints — 320px, 375px, 768px, 992px, 1200px, 1400px**, per [UI_IMPLEMENTATION_PLAN.md](UI_IMPLEMENTATION_PLAN.md) Phase 9. Note that 320px is **wider than the Phase 7 sweep's narrowest** and is where six- to ten-column admin tables are most likely to break. 991px and 993px must be checked individually — the sidebar's entire behavior changes across that one-pixel boundary.

Per page, per breakpoint:
- No page-level horizontal scroll. Table overflow must be confined to `.table-responsive`.
- Sidebar: drawer below 992px, static above; toggle visible only below 992px.
- Topbar: title truncates rather than pushing controls off-screen; action buttons remain reachable.
- All modals usable; long-content modals scroll internally.
- DataTables' generated controls (search, length menu, pagination) do not overflow — the Bootstrap 5 integration lays these out in a two-column row that is tight at 375px.
- Touch targets ≥44px on every action button, sidebar link, and pagination control.
- Metric cards and any Step 7 panels lay out at their specified column counts with no gap.

**Accessibility — per [UI_IMPLEMENTATION_PLAN.md](UI_IMPLEMENTATION_PLAN.md) Phase 10:**
- **Keyboard navigation:** every admin page fully operable without a mouse. Sidebar links, topbar actions, table sorting, DataTables search and pagination, every action button, every modal (open, complete, submit, dismiss, and focus return to trigger).
- **ARIA labels:** every icon-only button has a descriptive accessible name naming its target — audited across all pages, not sampled.
- **Alt text:** every `<img>` — vehicle thumbnails (Step 5), sidebar logo, `admin-login.php`'s logo.
- **Heading hierarchy:** exactly one `<h1>` per page from the topbar; `h1 → h2 → h3` with no skips. `admin-login.php` is not part of the topbar system and needs its own `<h1>` (it currently starts at `<h3>`).
- **Focus states:** visible on every interactive element, meeting WCAG 1.4.11's 3:1 against its background. `--accent-focus` exists specifically for this (`css/styles.css:12-14`).
- **Contrast:** every text/background pairing at ≥4.5:1 for normal text. Measure rather than assume — [BUGS.md](BUGS.md)'s closing note records `bg-primary` badges sitting at exactly 4.50:1 with zero margin, so any new badge usage introduced in Steps 3-5 needs measuring.
- **Screen reader:** landmark structure (`<nav>` sidebar, `<main>` content — **note `<main>` is not currently used on any admin page and should be added here**), table headers announced, sort state announced, form errors announced, modal role and labelling correct.
- **`prefers-reduced-motion`:** every animation added in Steps 2, 3, 6, and 7 respects it — reveals, counters, hover lifts, bar fills, shake feedback.

**Cross-cutting:**
- `admin-login.php` — the one admin page with no JS, no Font Awesome, and its own layout. Verify it was not broken by any shared-stylesheet change, and give it its own `<h1>` and focus states.
- Verify `js/admin.js` is not loaded on any customer page and `js/app.js` is not loaded on any admin page.
- Verify every `css/styles.css` change across Steps 1-8 left all six customer pages visually unchanged.

### Dependencies

Steps 1-8 complete, or Steps 7-8 formally descoped.

### Testing Requirements

- Full manual sweep of every admin page at all six breakpoints.
- Keyboard-only traversal of every page and every modal.
- Screen-reader pass (NVDA or Windows Narrator) over the sidebar, one table, and one modal per page type.
- Contrast measurement of every new or changed color pairing.
- OS-level `prefers-reduced-motion: reduce` enabled for a full pass.
- Automated audit (browser Lighthouse accessibility or axe DevTools) on every admin page, with each finding triaged rather than the score alone recorded.
- Full customer-side regression pass across all six customer pages.
- Zero console errors on every page.

### Acceptance Criteria

- No horizontal scroll on any admin page at any of the six breakpoints.
- Every admin page fully operable by keyboard, with a visible focus indicator throughout.
- Every icon-only button has a descriptive accessible name; every image has appropriate alt text.
- Exactly one `<h1>` per admin page, with a valid heading hierarchy.
- `<nav>` and `<main>` landmarks present on every admin page.
- All contrast ratios measured and meeting WCAG AA, with any at-threshold pairing recorded in [BUGS.md](BUGS.md) as the existing convention does.
- All motion respects `prefers-reduced-motion`.
- Touch targets ≥44px throughout.
- No customer-facing regression.

### Risks

- **Medium.** This step is likely to surface defects that require reopening earlier steps — particularly DataTables' generated controls at 320-375px, which no earlier step tests in isolation.
- **Medium.** Adding a `<main>` landmark means wrapping each page's content region, a structural change late in the phase. It is low-risk on the canonical shell established in Step 1, but it does touch every page again.
- **Low.** `admin-login.php` is nearly static and low-risk to adjust.

---

## Step 10: Final Review & Documentation

### Objective

Full regression pass across the admin section and the customer section, resolution of the deferred cleanup decisions, and complete documentation of Phase 8 — including formally recording whatever was descoped.

### Existing Files Involved

- Every file modified across Steps 1-9
- [includes/header.php](../includes/header.php) and [includes/footer.php](../includes/footer.php) — **unreferenced since Step 1; their deletion is decided here**
- [CHANGELOG.md](../CHANGELOG.md), [FEATURES.md](FEATURES.md), [BUGS.md](BUGS.md), [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md), [COMPONENT_LIBRARY.md](COMPONENT_LIBRARY.md), [PROJECT_AUDIT.md](PROJECT_AUDIT.md), [UI_IMPLEMENTATION_PLAN.md](UI_IMPLEMENTATION_PLAN.md)

### Files Expected to Be Modified

- Documentation files listed above
- Possibly small corrections found during regression

### Files Possibly Deleted

- `includes/header.php` and `includes/footer.php` — **only after** confirming with a repo-wide grep that no file references either. Deferred to this step deliberately so Step 1 remained reversible by restoring two include lines.

### Dependencies

All prior steps complete or formally descoped.

### Testing Requirements

**Full admin regression** — every workflow end to end:
- Admin login → dashboard → each sidebar destination → logout, verifying the session is actually destroyed **from each page**.
- Vehicle: add (with image), edit (with and without a new image), delete. Verify success feedback on each, and that `exif_imagetype()` rejection still works.
- User: edit (including the duplicate-email rejection), delete.
- Voucher: create, edit **without touching Usage Limit** (verifying it is not reset), delete.
- Booking: confirm from `admin-dashboard.php`, confirm from `view-all-data.php` **as the first interaction after a fresh page load**, edit times, delete.
- Verify `admin_confirm_booking.php`'s invariants after a confirmation: status flips to `confirmed`, `units_total` decrements by exactly one, exactly one `transactions` row is created.
- Settings (if built): profile update, password change, full logout/login cycle with the new password.
- Reports/Charts (if built): every figure re-verified against hand-run SQL.
- Unauthenticated access to every admin page and every admin endpoint.

**Full customer regression** — `css/styles.css` and `js/motion.js` are shared:
- `index.php`, `vehicles.php` (including its own filter sidebar and pagination), `transactions.php` (dashboard, bookings, history, profile, notification alerts), `receipt.php`, `about.php` (contact form), `faq.php`.
- Login, signup, logout, booking flow, cancel, return early, profile update, profile picture upload.

**Technical:**
- `php -l` on every modified PHP file.
- Zero console errors on all eleven pages.
- No 404s in the Network tab on any page.
- Verify `js/admin.js` loads only on admin pages and `js/app.js` only on customer pages.

### Documentation Updates Required

- **[CHANGELOG.md](../CHANGELOG.md)** — a Phase 8 entry per step, including the design decisions made for Reports/Charts and Settings, the options considered, and the reasoning.
- **[FEATURES.md](FEATURES.md)** — update every admin section; add Settings if built; record Application Settings as identified future work if Option C was taken; record Reports/Charts as built or descoped.
- **[BUGS.md](BUGS.md)** — mark resolved: item 6 (Confirm handler), item 7 (status badges), item 9 (voucher usage limit), item 14 (sidebar visibility), and item 5 as no longer reachable from the two pages that included `header.php`. Add the **new** findings from this analysis that were fixed: the `admin-dashboard.php` logout interception; the unreachable vehicle success alerts; the `view-all-data.php` JSON `die()`. Add as **still-open, requiring separate approval**: item 8 (`delete_booking.php`'s dead guard) and the `admin_delete_user.php` ID-space bug. Update item 15's note to record that Reports/Charts either avoided or resolved it.
- **[DESIGN_SYSTEM.md](DESIGN_SYSTEM.md)** — **correct the six documentation conflicts catalogued in [ADMIN_DASHBOARD_ANALYSIS.md](ADMIN_DASHBOARD_ANALYSIS.md) §6**, specifically §9/§10's misattribution of the sidebar to `includes/header.php`, §11's "Back to Dashboard" claim, and §12's claim that `view-all-data.php` uses `header.php`.
- **[COMPONENT_LIBRARY.md](COMPONENT_LIBRARY.md)** — correct §"Layout Shells" and §"Summary" item 2 (the pre-`admin_sidebar.php` description), and §"Empty States" line 309 (the already-resolved `admin_users.php` colspan). Add `includes/admin_topbar.php`, `js/admin.js`, `AdminValidation`, and the confirmation modal as new shared components. Update the DataTables table with the new configuration baseline.
- **[PROJECT_AUDIT.md](PROJECT_AUDIT.md)** — correct the "Architecture" claim that admin pages share `header.php`/`footer.php`, and the "Folder Structure" entry for `includes/`, which omits four of its six files.
- **[UI_IMPLEMENTATION_PLAN.md](UI_IMPLEMENTATION_PLAN.md)** — mark Phase 8 complete, noting explicitly which of the eight named tasks were delivered and which were descoped and why.
- **[ADMIN_DASHBOARD_ANALYSIS.md](ADMIN_DASHBOARD_ANALYSIS.md)** and **this plan** — final status notes recording what was implemented against what was planned.

### Acceptance Criteria

- Every admin workflow verified working end to end.
- No customer-facing regression.
- Zero console errors, zero PHP errors, zero 404s across all pages.
- All eight Phase 8 tasks are accounted for — each either delivered or formally descoped with recorded reasoning.
- Every documentation conflict identified in the analysis §6 is corrected in its source document.
- All resolved bugs marked resolved; all still-open bugs recorded with their approval status.
- **"Admin workflow preserved"** — Phase 8's stated acceptance criterion — is verified explicitly: every admin capability that worked before Phase 8 still works, and the four that were silently broken (Confirm from `view-all-data.php`, logout from the dashboard, vehicle success feedback, voucher usage limits) now work.
- A recommended commit message per step is provided, following the plan's `UI: ...` format.

### Risks

- **Medium.** Deleting `includes/header.php` and `includes/footer.php` is irreversible in a repository with **no git history** ([PROJECT_AUDIT.md](PROJECT_AUDIT.md): "This directory is not a git repository"). The grep confirming zero references must be exhaustive across `*.php`, and the decision should be confirmed with the user rather than assumed.
- **Medium.** Documentation correction is the step most likely to be rushed. The six conflicts in §6 are corrections to *existing published claims*, not additions — each needs editing in place, in the right document, with the right replacement text.
- **Low.** The regression pass itself is mechanical but long; it must not be sampled.

---

## Done / Partial / Not Started Summary (Planned End State)

| Item | Current | Planned After Phase 8 | Step |
|---|---|---|---|
| Sidebar visible at desktop | **Broken** | Done | 1 |
| Single valid HTML document per admin page | **Broken (2 pages)** | Done | 1 |
| Duplicate jQuery/DataTables removed | **Broken (2 pages)** | Done | 1 |
| Logout actually logs out from every page | **Broken (1 page)** | Done | 1 |
| Unauthenticated redirect on every admin page | **Broken (1 page)** | Done | 1 |
| Sidebar `<nav>` landmark + `aria-current` | Not Started | Done | 2 |
| Shared admin topbar partial | Not Started | Done | 2 |
| One `<h1>` per admin page | Not Started | Done | 2, 9 |
| Admin's real name in the UI | Not Started | Done | 2 |
| `.metric-card` defined | Not Started | Done | 3 |
| Metric labels match their queries | **Broken** | Done | 3 |
| Pending-bookings metric | Not Started | Done | 3 |
| Dashboard entrance/counter motion | Not Started | Done | 3 |
| Status badges match real data | **Broken** | Done | 3, 4 |
| Correct `colspan` on every empty state | **Broken (1)** | Done | 3, 4 |
| Recent Messages bounded | **Broken** | Done | 3 |
| Confirm works from `view-all-data.php` | **Broken** | Done | 4 |
| Empty state on every admin table | **Missing (2)** | Done | 4, 5 |
| DataTables configuration baseline | Not Started | Done | 4, 5 |
| Numeric ID sort | **Broken** | Done | 4, 5 |
| `aria-label` on every icon-only button | Not Started | Done | 4, 5, 9 |
| Vehicle success feedback reachable | **Broken** | Done | 5 |
| Cascade warnings on destructive actions | Not Started | Done | 4, 5, 6 |
| Discarded `details` field removed | **Broken** | Done | 5 |
| `js/admin.js` shared script | Not Started | Done | 6 |
| Field-level validation on admin forms | Not Started | Done | 6 |
| `alert()` / `confirm()` eliminated | Not Started | Done | 6 |
| One modal + form convention | Not Started | Done | 6 |
| Voucher usage limit preserved on edit | **Broken** | Done | 6 |
| `error:` callback on every admin AJAX call | **Missing (3)** | Done | 6 |
| Dead duplicated handlers removed | Not Started | Done | 6 |
| **Reports** | Not Started | **Gated — decision required** | 7 |
| **Charts** | Not Started | **Gated — decision required** | 7 |
| **Settings** | Not Started | **Gated — decision required** | 8 |
| Responsive at 320-1400px | Partial | Done | 9 |
| `<main>` landmark | Not Started | Done | 9 |
| Full keyboard operability | Partial | Done | 9 |
| Contrast measured throughout | Not Started | Done | 9 |
| `delete_booking.php` guard | **Broken** | **Out of scope — separate approval** | — |
| `admin_delete_user.php` ID-space guard | **Broken** | **Out of scope — separate approval** | — |
| [BUGS.md](BUGS.md) item 15 (timezone) | Open | **Out of scope unless Step 7 Option B** | — |
| CSRF protection | Absent project-wide | **Out of scope — project-wide decision** | — |

---

**Status:** Plan only. No code has been modified as part of producing this document. This plan is derived from [ADMIN_DASHBOARD_ANALYSIS.md](ADMIN_DASHBOARD_ANALYSIS.md), which itself awaits approval — if the analysis's scope assessment in §7 is revised, this plan must be revised with it.

Each step is implemented only after a dedicated Claude Code prompt is generated, reviewed, and separately approved — one step at a time, per this project's established workflow. **Steps 7 and 8 additionally require a design decision from the user and separate backend approval before their prompts can be generated at all**, and the two business-rule bugs flagged in Step 4's Note are outside every step in this plan and require their own approval.

*Produced on 2026-08-20.*

---

**Final status (2026-08-21): all ten steps complete.** See [CHANGELOG.md](../CHANGELOG.md)'s ten "Admin Dashboard" entries for full detail. Steps 1-6 and 9 delivered as specified. Step 7 delivered Option A (Business Overview panel, no new page, no new dependency) rather than Option B. Step 8 delivered Option C (Account Settings now; Application Settings recorded in [FEATURES.md](FEATURES.md) as descoped future work). Step 10 deleted `includes/header.php`/`includes/footer.php` after its verification grep confirmed zero remaining references, and reconciled every documentation conflict this plan's derivation analysis identified. `delete_booking.php`'s dead status guard and `admin_delete_user.php`'s ID-space bug remain open, exactly as this plan specified, pending their own separate business-rule approval.
