# PMS Car Rental — Shared Components Audit

Phase 1 prerequisite audit. Every finding below is verified by direct source inspection with file:line citations. No code has been modified. Cross-references to existing documentation are noted as [COMPONENT_LIBRARY], [DESIGN_SYSTEM], [BUGS], or [UI_ANALYSIS] where a finding confirms, extends, or conflicts with prior analysis.

---

## Executive Summary

The PMS codebase has **zero shared UI partials**. Every reusable component — navbar, footer, modals, sidebar — is copy-pasted across files. The result is 6 navbar variants, 5 footer copies, 5 login/signup modal duplications, and an admin sidebar that exists in exactly one file. The CSS layer (`css/styles.css`, 905 lines) contains ~220 lines of dead Tailwind v4 scaffolding that will never execute in this CDN-only Bootstrap project. The JavaScript layer (`js/app.js`, 1208 lines) is a monolith with 4+ competing booking-confirm implementations and ~300 lines of commented-out deprecated code.

### Key Metrics

| Metric | Count |
|---|---|
| Total shared partials | 0 |
| Client navbar duplications | 6 (index, vehicles, faq, about, transactions, receipt-variant) |
| Footer duplications | 5 (index, vehicles, faq, about, transactions) |
| Login/Signup modal duplications | 5 (index, vehicles, faq, about, transactions) |
| Admin sidebar instances | 1 (admin-dashboard.php only) |
| Dead CSS lines (Tailwind scaffolding) | ~120 (lines 99–218) |
| Dead/deprecated JS lines | ~300+ |
| Verified bugs affecting shared components | 6 |
| Brand palette compliance | 0% (target: `#0F2A4D`/`#2F6FED`/`#63A8FF`; actual: `#2d4a9e`/`#d97706`) |

### Critical Findings

1. **No shared partials exist** — `includes/header.php` and `includes/footer.php` are admin-only and do not emit nav/footer markup usable by client pages
2. **Brand palette is completely unimplemented** — zero CSS variables match the UI_MASTER_PLAN.md target colors
3. **Dead Tailwind v4 code in `styles.css`** (`@theme inline`, `@custom-variant dark`, `@apply`) requires a build step that doesn't exist
4. **`js/app.js` contains 4+ competing booking-confirm implementations** that are never called through a shared function
5. **jQuery version split**: 3.6.0 via `includes/footer.php` (admin), 3.7.1 on all client pages

---

## Per-Component Audit

### 1. Navbar

**Instances found:** 6 distinct copies

| File | Lines | Variant |
|---|---|---|
| `index.php` | 21–43 | Standard 5-link client navbar |
| `vehicles.php` | 42–62 | Standard 5-link, "Transactions" marked `active` (bug: should be "Vehicles") |
| `faq.php` | 18–39 | Standard 5-link |
| `about.php` | 24–45 | Standard 5-link |
| `transactions.php` | 67–88 | Standard 5-link, "Transactions" marked `active` |
| `receipt.php` | 72–83 | Simplified: brand + 2 buttons only, no hamburger/collapse |

**Structure:** `nav.navbar.navbar-expand-md.fixed-top.bg-white.shadow-sm.border-bottom` > `.container` > logo+brand | toggler | collapse menu + `#navbarAuthArea`

**Confirmed Issues:**
- **DUPLICATE**: All 5 standard navbars are near-identical hand-copied markup. Any nav change requires editing 5 files. [COMPONENT_LIBRARY] confirms.
- **INCONSISTENCY**: `vehicles.php:57` marks "Transactions" as `active` instead of "Vehicles". The JS-based active-state logic in `app.js:1-11` should override this, but the server-rendered `active` class creates a flash of incorrect state before JS runs.
- **DRIFT**: `receipt.php:72-83` uses a completely different navbar structure (no collapse, no hamburger, only 2 link-buttons) with no shared base.
- **INCONSISTENCY**: Logo uses inline styles (`style="width:36px;height:28px;object-fit:cover;border-radius:6px;"`) identically across all 5 standard navbars instead of a CSS class. [COMPONENT_LIBRARY] confirms.
- **DEAD CODE**: `app.js:428-452` contains a `renderNavbarAuth()` function that hardcodes `const user = null;` (line 430), immediately followed by a second, working `renderNavbarAuth()` at `app.js:1169-1195` that actually calls `getMe()`. The first is dead code that runs first, then gets overridden.

**Target (DESIGN_SYSTEM):** Shared partial, brand palette colors, consistent active-state highlighting.

---

### 2. Footer

**Instances found:** 5 copies (receipt.php has NO footer)

| File | Lines |
|---|---|
| `index.php` | 224–247 |
| `vehicles.php` | 553–576 |
| `faq.php` | 115–138 |
| `about.php` | 233–256 |
| `transactions.php` | 244–262 |

**Structure:** `footer.bg-dark.text-white.mt-5` > `.container.py-5` > 2-column row (company blurb + contact info) > copyright bar

**Confirmed Issues:**
- **DUPLICATE**: All 5 are functionally identical markup. [COMPONENT_LIBRARY] confirms.
- **INCONSISTENCY**: `transactions.php:249` has a slightly shorter company description paragraph ("PMS got you covered!" only, no second sentence) vs. the other 4 which include "Discover premium vehicles for every journey."
- **MISSING**: `receipt.php` has no footer at all. [DESIGN_SYSTEM] confirms.
- **INCONSISTENCY**: Footer uses `bg-dark` (Bootstrap's dark gray) not the brand `--primary: #0F2A4D`. [UI_ANALYSIS] confirms palette mismatch.

**Target (DESIGN_SYSTEM):** Shared partial, brand colors, consistent content across all pages.

---

### 3. Login/Signup Modals

**Instances found:** 5 duplications

| File | Lines (Login) | Lines (Signup) |
|---|---|---|
| `index.php` | 250–286 | 289–315 |
| `vehicles.php` | 580–608 | 612–643 |
| `faq.php` | 140–175 | 177–203 |
| `about.php` | 259–287 | 290–321 |
| `transactions.php` | 265–293 | 296–327 |

**Structure:** Login: `#loginModal` > `.glassmorph` content > email/password form + "Login as Admin" button + signup link. Signup: `#signupModal` > `.glassmorph` content > name/email/password/confirm form.

**Confirmed Issues:**
- **DUPLICATE**: Identical markup across all 5 files. Any modal change requires 5 edits. [COMPONENT_LIBRARY] confirms.
- **MISSING**: `receipt.php` has no login/signup modals — if a user's session expires while viewing a receipt, there's no way to re-authenticate without navigating away.
- **CONFLICT**: The modals use `.glassmorph` styling, which is defined **twice** in `css/styles.css` (lines 547–552 and 554–567) with different values. The second definition wins due to CSS cascade. [BUGS] does not document this; **new finding**.
- **INCONSISTENCY**: Login modal button styles: "Log In" uses `btn-primary`, "Login as Admin" uses `btn-outline-secondary`, "Create Account" uses `btn-success` — three different button styles for auth actions. [COMPONENT_LIBRARY] confirms inconsistency.

**Target (DESIGN_SYSTEM):** Single shared modal partial, consistent button styling per brand.

---

### 4. Admin Sidebar

**Instances found:** 1 (admin-dashboard.php only)

| File | Lines |
|---|---|
| `admin-dashboard.php` | 35–51 |

**Structure:** `nav#adminSidebar.bg-white.border-end.p-3` > logo+brand > `ul.nav.flex-column.gap-2` with 5 nav-items + logout link

**Confirmed Issues:**
- **MISSING**: Only `admin-dashboard.php` has a sidebar. Pages using `includes/header.php` (`admin_vehicles.php`, `admin_users.php`, `view-all-data.php`) get **no sidebar**. `admin_vouchers.php` doesn't include `header.php` at all and also has no sidebar — only a "Back to Dashboard" link. [BUGS] confirms; [UI_ANALYSIS §8.1] confirms.
- **DEAD CODE**: `css/styles.css:434` references `var(--sidebar-bg)` and line 456 references `var(--sidebar-hover)` — **neither variable is defined in `:root`**. The sidebar uses `bg-white` from markup instead. [BUGS] confirms; [UI_ANALYSIS §8.1] confirms.
- **INCONSISTENCY**: No active-state highlighting — the sidebar doesn't mark the current page. All nav-links look the same regardless of which page is active.
- **BUG**: The sidebar toggle (`#sidebarToggle`) uses jQuery `.css('width', ...)` to collapse/expand (admin-dashboard.php:256-263) rather than Bootstrap's offcanvas or collapse, making it inconsistent with how the client navbar handles responsive behavior.

**Target (DESIGN_SYSTEM):** Shared admin sidebar partial across all admin pages with active-state highlighting.

---

### 5. Buttons

**Instances found across all pages:**

| Pattern | Usage | Files |
|---|---|---|
| `btn-warning.rounded-pill` | Primary CTA ("Reserve Now") | vehicles.php, index.php |
| `btn-primary.rounded-pill` | Auth ("Log In"), nav, admin actions | All client pages, admin pages |
| `btn-success.rounded-pill` | "Create Account", "Confirm Booking" | All client pages, admin-dashboard.php |
| `btn-outline-primary.rounded-pill` | "Log In" (navbar) | All client pages (via app.js) |
| `btn-outline-secondary` | "Cancel", category filters, admin nav | vehicles.php, modals, admin pages |
| `btn-danger` | Delete actions | admin_vehicles.php, admin_users.php |
| `btn-outline-danger` | Cancel booking, delete | transactions.php, view-all-data.php |
| `btn-outline-success` | Confirm transaction | view-all-data.php |

**Confirmed Issues:**
- **INCONSISTENCY**: Primary CTA is `btn-warning` (Bootstrap's stock amber) on customer pages but `btn-primary` (maps to `--primary: #2d4a9e`) on admin pages. Neither matches the target palette. [COMPONENT_LIBRARY] confirms.
- **INCONSISTENCY**: `rounded-pill` is applied inconsistently — most CTAs use it, but admin table action buttons use default border-radius. No documented standard exists.
- **INCONSISTENCY**: Button sizing varies: `btn-sm` in admin tables, default size in modals, `w-100` in modal forms — no systematic sizing convention.
- **MISSING**: No loading-state button pattern exists anywhere. Admin pages use inline spinner HTML (`<span class="spinner-border spinner-border-sm">`) constructed in JS (e.g., `admin_users.php:144`, `admin-dashboard.php:313`) but this is ad-hoc, not reusable.

**Target (DESIGN_SYSTEM):** Standardized button hierarchy: primary, secondary, outline, destructive, with consistent `rounded-pill`, sizing, and loading states.

---

### 6. Cards

**Instances found:** Multiple distinct card patterns

| Pattern | Files | Lines |
|---|---|---|
| Vehicle card (`.vehicle-card`) | vehicles.php | 510–541 (PHP loop) |
| Feature card (glassmorphism) | index.php | 66–92 |
| Metric card (`.metric-card`) | admin-dashboard.php | 69–96 |
| Summary stat card | transactions.php | 117–130 |
| Team member card | about.php | 116–154 |
| Feature highlight card | about.php | 76–105 |
| Data table card | admin pages | Various |
| Receipt card | receipt.php | 86–145 |

**Confirmed Issues:**
- **DEAD CODE**: `.metric-card` class used at `admin-dashboard.php:70` is **not defined anywhere** in `css/styles.css`. Cards rely solely on Bootstrap utilities. [BUGS] confirms; [UI_ANALYSIS §8.1] confirms.
- **DEAD CODE**: `.card.three-d` CSS exists at `styles.css:570-578` but index.php uses `.card.3d` (line ~167 area) — class name mismatch means the 3D effect never applies. [COMPONENT_LIBRARY] confirms.
- **INCONSISTENCY**: Card border-radius varies — `.vehicle-card` uses `border-radius: 16px` (styles.css:659), feature cards use `rounded-3` (Bootstrap's 0.5rem), admin cards use default Bootstrap card radius. No standard.
- **INCONSISTENCY**: Card shadow treatment varies — `.vehicle-card` has custom hover shadow (`styles.css:665`), other cards use `shadow-sm`, some use no shadow.

**Target (DESIGN_SYSTEM):** Standardized card component with consistent border-radius, shadow, and hover behavior.

---

### 7. Vehicle Cards

**Instances found:** 2 patterns

| Pattern | File | Lines |
|---|---|---|
| Dynamic (PHP loop) | vehicles.php | 483–543 |
| Static (hardcoded carousel) | index.php | 95–220 |

**Confirmed Issues:**
- **DUPLICATE**: `index.php` hardcodes 3 featured vehicle cards that are not sourced from the database. These can become stale if vehicle data changes. [DESIGN_SYSTEM §1] confirms.
- **DEAD CODE**: `vehicles.php:231-481` contains ~250 lines of commented-out static vehicle cards, superseded by the PHP loop below. [BUGS] confirms as deprecated code.
- **DEAD CODE**: `css/styles.css:691-732` defines `.vehicle-badges`, `.vehicle-badge`, `.vehicle-actions`, `.btn-icon` — none of these classes are used in any current markup. [COMPONENT_LIBRARY] confirms.
- **INCONSISTENCY**: Vehicle cards on `vehicles.php` use `btn-warning` for "Reserve Now" but `index.php` carousel cards use `btn-outline-info` for "Quick View" — different CTA patterns for the same entity type.

**Target (DESIGN_SYSTEM):** Single reusable vehicle card component with consistent CTA, badge, and spec layout.

---

### 8. Booking Cards / Booking Modal

**Instances found:** 3+ competing implementations

| Implementation | File | Lines |
|---|---|---|
| Inline booking modal | vehicles.php | 66–200 |
| Multi-step booking JS (no matching DOM) | js/app.js | 572–929 |
| Inline booking handlers | vehicles.php | 660–949 |

**Confirmed Issues:**
- **DUPLICATE**: `vehicles.php` inline script (660-949) and `js/app.js` both implement booking preview, confirm, and receipt — independently, not sharing a function. [BUGS] confirms 4+ competing implementations.
- **BUG**: `vehicles.php:780-781` references undeclared `amount_paid` variable in preview request body, causing a ReferenceError caught only as a generic error message. [BUGS] Verified Bug #3.
- **DEAD CODE**: Multi-step booking JS in `app.js` (steps 1-3, payment method fields, GCash/credit card rendering) has **no matching HTML** in any PHP file. `#bookingMultiModal`, `#bookingStep1`, `#bookingStep2`, `#bookingStep3` do not exist in any markup. [BUGS] confirms as incomplete implementation.
- **BUG**: `app.js:849-853` references `data` instead of `preview` for booking summary rendering — `data` is undeclared in that scope. [BUGS] Verified Bug #2.

**Target (DESIGN_SYSTEM):** Single booking modal implementation with clear step flow and shared handler functions.

---

### 9. Tables

**Instances found:** 6 table implementations

| Table | File | Lines | DataTables? |
|---|---|---|---|
| Recent Transactions | admin-dashboard.php | 104–168 | No (CSS loaded, JS not initialized) |
| Recent Messages | admin-dashboard.php | 176–208 | No |
| Vehicles | admin_vehicles.php | 49–100 | Yes (`#vehiclesTable`) |
| Users | admin_users.php | 38–83 | Yes (`#usersTable`) |
| Vouchers | admin_vouchers.php | 110–160 | No |
| All Transactions | view-all-data.php | 36–98 | Yes (`#transactionsTable`) |

**Confirmed Issues:**
- **INCONSISTENCY**: DataTables is loaded on `admin-dashboard.php` but never initialized — wasted page weight. [BUGS] confirms; [UI_ANALYSIS §8.1] confirms.
- **INCONSISTENCY**: Table header styles vary — `admin_vehicles.php` uses `thead.table-dark`, `admin_users.php` uses `thead.table-light`, `admin-dashboard.php` and `view-all-data.php` use unstyled `<thead>`. No standard. [COMPONENT_LIBRARY] confirms.
- **INCONSISTENCY**: `admin_vouchers.php` has no DataTables, no pagination — unlike other admin tables that do use DataTables for sorting/search/pagination.
- **BUG**: `view-all-data.php:43` has a typo: "Rental Dsate" instead of "Rental Date". **New finding** — not in [BUGS].
- **BUG**: The `.confirm-transaction` handler in `view-all-data.php:207-226` is nested inside the `.delete-transaction` handler's `if (confirm(...))` block, so it only registers after a delete is confirmed. [BUGS] Verified Bug #6.
- **INCONSISTENCY**: Global table `<th>` styling at `styles.css:861-869` overrides font-weight, size, text-transform, and background-color for **all** tables — including customer-facing list views (transactions.php uses list-groups, not tables, so it's unaffected).

**Target (DESIGN_SYSTEM):** Consistent DataTables initialization, standardized thead styling, shared table component pattern.

---

### 10. Forms

**Instances found:** Multiple form patterns

| Form | File | Lines | Style |
|---|---|---|---|
| Login | All 5 client pages | Various | `.form-control`, no `rounded-pill` |
| Signup | All 5 client pages | Various | `.form-control`, no `rounded-pill` |
| Contact | about.php | 170–215 | `.form-control.rounded-pill` inputs, `rounded-3` textarea |
| Booking | vehicles.php | 80–181 | `.form-control`, date/time/file/select |
| Add Vehicle | admin_vehicles.php | 110–183 | `.form-control`, `.form-select` |
| Edit Vehicle | admin_vehicles.php | 189–258 | `.form-control`, `.form-select` |
| Edit User | admin_users.php | 89–115 | `.form-control` |
| Admin Login | admin-login.php | 40–59 | `.form-control.rounded-pill` |
| Add/Edit Voucher | admin_vouchers.php | 173–241 | `.form-control` |

**Confirmed Issues:**
- **INCONSISTENCY**: `rounded-pill` inputs on Contact form and Admin Login, but standard corners on all other forms. No documented standard. [COMPONENT_LIBRARY] confirms.
- **INCONSISTENCY**: Label associations vary — some use `for`/`id` pairing (admin-login.php:42-43), some omit `for` attributes entirely (booking form labels in vehicles.php). [COMPONENT_LIBRARY] confirms.
- **BUG**: `admin_vouchers.php:189` has `for="edit_usage_limit"` but the input's `id` is `"usage_limit"`, and there are duplicate `id="usage_limit"` across both Add and Edit modals. [BUGS] Verified Bug #9.
- **MISSING**: No consistent validation pattern — booking form uses HTML5 `required` + custom JS, admin forms have no client-side validation beyond `required`, admin login relies on server-side only.
- **INCONSISTENCY**: Global form styling at `styles.css:894-897` sets `border-radius: 0.375rem` on all `.form-control`/`.form-select`, conflicting with `rounded-pill` used on some forms.

**Target (DESIGN_SYSTEM):** Standardized form input styling (one border-radius), consistent label associations, shared validation patterns.

---

### 11. Alerts

**Instances found:**

| Pattern | File | Lines |
|---|---|---|
| Bootstrap `.alert.d-none` toggled via JS | vehicles.php | 77 |
| Contact form alert | about.php | 208–209 |
| Admin success/error via query params | admin_vehicles.php | 36–44 |
| JS `alert()` dialogs | admin-dashboard.php, admin_users.php, admin_vouchers.php, app.js | Various |
| Floating alert function | app.js | 558–568 |

**Confirmed Issues:**
- **INCONSISTENCY**: Three alert patterns coexist — Bootstrap `.alert` elements toggled via class manipulation, PHP-rendered `alert-success`/`alert-danger` blocks via query params, and plain `alert()` JS dialogs. No standard. [COMPONENT_LIBRARY] confirms.
- **DEAD CODE**: `showFloatingAlert()` function is defined in both `app.js:558-568` and `voucher-manager.js:2-7` (different implementations with the same name). The `voucher-manager.js` version uses `#bookingAlert`, the `app.js` version creates a DOM element. Name collision. **New finding**.
- **MISSING**: No toast/notification component — confirmations on admin pages use `location.reload()` or `alert()` instead.

**Target (DESIGN_SYSTEM):** Standardized alert/toast component, eliminate JS `alert()` dialogs.

---

### 12. Modals

**Instances found:** 14+ modal instances across the codebase

| Modal | File | Lines |
|---|---|---|
| Login | 5 client pages | Duplicated |
| Signup | 5 client pages | Duplicated |
| Booking | vehicles.php | 66–200 |
| Quick View (3x) | index.php | ~150–200 area |
| Receipt (dynamic) | printer.js | 9–104 |
| Edit User | admin-dashboard.php + admin_users.php | Duplicated |
| Edit Vehicle | admin_vehicles.php | 186–258 |
| Add Vehicle | admin_vehicles.php | 107–183 |
| Add/Edit Voucher | admin_vouchers.php | 165–242 |
| Edit Transaction | view-all-data.php | 107–133 |
| Return Early | transactions.php | 330–350 |
| Cancel Booking | transactions.php | 372–402 |
| Return Receipt | transactions.php | 406–420 |
| License Preview | transactions.php | 353–369 |

**Confirmed Issues:**
- **DUPLICATE**: Login/Signup modals duplicated 5x. Edit User modal duplicated across `admin-dashboard.php:217-243` and `admin_users.php:89-115` — with corresponding JS handlers duplicated too. [COMPONENT_LIBRARY] confirms.
- **DEAD CODE**: License Preview modal exists at `transactions.php:353-369` but all trigger buttons have been removed (3 `<!-- license preview removed -->` comments). Unreachable UI.
- **INCONSISTENCY**: Modal styling varies — auth modals use `.glassmorph`, booking modal uses plain `.modal-content`, admin modals use plain `.modal-content`. No standard.
- **INCONSISTENCY**: Close/cancel buttons vary — some use `btn-secondary`, some `btn-outline-secondary`, some have no cancel button.

**Target (DESIGN_SYSTEM):** Shared modal partials (at minimum: auth modals), consistent styling and button patterns.

---

### 13. Pagination

**Confirmed Issues:**
- **MISSING**: No pagination exists on `vehicles.php` — all active vehicles render in one unpaginated grid. [UI_ANALYSIS §2] confirms.
- DataTables provides built-in pagination on admin pages where initialized, but it's not used on `admin-dashboard.php` or `admin_vouchers.php`.

**Target (DESIGN_SYSTEM):** Client-side pagination on vehicles page; consistent DataTables usage on all admin tables.

---

### 14. Search/Filters

**Instances found:**

| Pattern | File | Lines |
|---|---|---|
| Category filter buttons | vehicles.php | 215–223 |
| Category filter JS | vehicles.php (inline) + app.js | 662–673, 371–397 |

**Confirmed Issues:**
- **DUPLICATE**: Category filter logic is implemented **twice** — once inline in `vehicles.php:662-673` and once in `app.js:371-397`. Both bind to `.category-btn` click. The `app.js` version also handles `#noResultsMessage` visibility; the inline version doesn't. [COMPONENT_LIBRARY] confirms.
- **MISSING**: No search bar, no price range filter, no sort control. [UI_ANALYSIS §2] confirms these as High-gap items vs. reference.
- **INCONSISTENCY**: Hardcoded category buttons (`vehicles.php:216-222`) don't match the dynamic PHP query at `vehicles.php:15-19` that fetches distinct categories — the buttons include "Scooter" and "Pickup" which may not exist in the database, while the PHP query result (`$categories`) is fetched but never used to render the buttons.

**Target (DESIGN_SYSTEM):** Single filter implementation, dynamic category rendering from DB, search bar, sort control.

---

### 15. Badges / Status Indicators

**Instances found:**

| Badge | File | Lines | Style |
|---|---|---|---|
| Vehicle availability | vehicles.php | 529 | `bg-success` / `bg-danger` |
| Booking status (admin) | admin-dashboard.php | 130–135 | `match()` → always `bg-secondary` (BUG) |
| Booking status (customer) | transactions.php | 160–168 | `bg-success`/`bg-danger`/`bg-info`/`bg-secondary` |
| Time-based status | transactions.php | 144–152 | `bg-info`/`bg-success`/`bg-warning` |
| User role | admin_users.php | 60 | `bg-info` |
| Voucher usage | admin_vouchers.php | 130–134 | `bg-success` / `bg-danger` |
| Transaction status (all) | view-all-data.php | 65–69 | `bg-success`/`bg-primary`/`bg-secondary` |

**Confirmed Issues:**
- **BUG**: `admin-dashboard.php:130-135` uses capitalized status values (`'Completed'`, `'Active'`, `'Cancelled'`) in a `match()` expression, but the database stores lowercase values. Every badge falls to `bg-secondary`. [BUGS] Verified Bug #7.
- **INCONSISTENCY**: Status badge colors differ across pages for the same status: `confirmed` is `bg-success` on `transactions.php` and `view-all-data.php` but would be `bg-secondary` on `admin-dashboard.php` (due to the bug). `completed` is `bg-primary` on `view-all-data.php` but `bg-warning` on `transactions.php`.
- **MISSING**: No standardized badge convention — each page independently maps statuses to Bootstrap contextual classes.

**Target (DESIGN_SYSTEM):** Centralized status-to-badge-class mapping (ideally a PHP helper function), consistent colors across all pages.

---

### 16. Dropdowns

**Instances found:**

| Dropdown | File | Mechanism |
|---|---|---|
| User auth dropdown | app.js:1176-1185 | Bootstrap dropdown (dynamic) |
| Voucher select | vehicles.php:125-128 | `<select>` populated by JS |
| Category select (forms) | admin_vehicles.php:122-132 | Static `<select>` |
| Fuel/Transmission select | admin_vehicles.php:147-163 | Static `<select>` |

**Confirmed Issues:**
- **INCONSISTENCY**: Dropdown minimum width set to `120px` at `styles.css:387` as a global override on `.dropdown-menu` — applies to all Bootstrap dropdowns, may cause layout issues for longer content.

---

### 17. Icons

**Confirmed Issues:**
- **INCONSISTENCY**: Client pages use Font Awesome 6.4.2 consistently, but `about.php:79-100` uses raw emoji (🚗🎧🛡️⚙️) for feature icons while every other page uses Font Awesome icons. [UI_ANALYSIS §7] confirms.
- Admin pages use Font Awesome consistently.
- **CONFLICT**: UI_MASTER_PLAN references FleetPro which uses Bootstrap Icons (`bi-*`). Current codebase uses Font Awesome throughout. Decision needed on which icon library to standardize on.

---

### 18. Empty States

**Instances found:**

| Empty State | File | Lines |
|---|---|---|
| No transactions (not logged in) | transactions.php | 99–105 |
| No transactions (logged in) | transactions.php | 107–113 |
| No vehicles in category | vehicles.php | 546–548 |
| No vehicles found (admin) | admin_vehicles.php | 96 |
| No users found | admin_users.php | 78 |
| No transactions found | admin-dashboard.php | 159 |
| No messages yet | admin-dashboard.php | 203 |

**Confirmed Issues:**
- **INCONSISTENCY**: Empty states vary — `transactions.php` has a full display-6 heading with action buttons, admin pages use plain `<td colspan="..." class="text-center text-muted">` text. No shared pattern. [COMPONENT_LIBRARY] confirms.
- **MISSING**: No empty state on `vehicles.php` main grid if the database has zero active vehicles (only the category-filter "no results" message exists).

---

### 19. Loading States

**Confirmed Issues:**
- **MISSING**: No consistent loading state component. Inline spinners are constructed ad-hoc in JS:
  - `admin_users.php:144`: `'<span class="spinner-border spinner-border-sm">'`
  - `admin-dashboard.php:313`: Same pattern
  - No loading skeleton, no page-level spinner, no button loading class. [COMPONENT_LIBRARY] confirms.

---

### 20. Typography

**Confirmed Issues:**
- **INCONSISTENCY**: Body font set to `'Inter', 'Poppins', ...` at `styles.css:226-228`, but `.font-poppins` is applied to most headings across all pages (index.php:29, vehicles.php:46, faq.php:28, about.php:29,55, admin-dashboard.php:38,63). The target (UI_MASTER_PLAN) is Inter only.
- **DEAD CODE**: `styles.css:156-218` contains `@layer base` with `@apply` directives (Tailwind syntax) — these are completely inert without a Tailwind build step. They define `h1-h4`, `p`, `label`, `button`, `input` styles that never apply.
- **INCONSISTENCY**: No enforced type scale — heading sizes vary by page: `display-5` on index.php hero, `h2.fw-bold` on vehicles.php, `display-5` on transactions.php, `h2` on admin-dashboard.php. [COMPONENT_LIBRARY] confirms.

---

### 21. Colors

**Confirmed Issues:**
- **CONFLICT**: Target palette (UI_MASTER_PLAN): `Primary #0F2A4D`, `Secondary #2F6FED`, `Accent #63A8FF`, `Background #F8FAFC`. Actual `styles.css:13-19`: `--primary: #2d4a9e`, `--accent: #d97706`, `--background: #fafbfc`. **Zero overlap**. [UI_ANALYSIS §1] confirms total mismatch.
- **DEAD CODE**: Extended color palette at `styles.css:32-50` defines `--navy-blue`, `--royal-blue`, `--steel-blue`, etc. — none of these variables are referenced in any markup or other CSS rules. Similarly, chart/sidebar oklch variables (lines 46-59) appear to be from a shadcn/Tailwind scaffold — dead code.
- **INCONSISTENCY**: Hero gradient at `index.php:55` uses hardcoded `linear-gradient(135deg,#2d4a9e99,#d9770699)` — not CSS variables.
- **INCONSISTENCY**: About page CTA banner at `about.php:222` uses hardcoded `linear-gradient(90deg,#ffd6a5,#ffb4b4,#c4b5fd)` — pastel gradient unrelated to brand.

---

### 22. Shadows

**Confirmed Issues:**
- **INCONSISTENCY**: At least 4 shadow patterns in use:
  - `shadow-sm` (Bootstrap default) — most cards
  - Custom hover shadow: `0 14px 40px rgba(45,74,158,.18)` on `.vehicle-card:hover` (styles.css:665)
  - Glassmorphism shadow: `0 6px 32px #0002` on `.glassmorph` (styles.css:560)
  - Feature card shadow: `0 7px 48px #d9770644` on `.feature-card:hover` (styles.css:618)
- No shadow scale defined in CSS variables.

---

### 23. Border Radius

**Confirmed Issues:**
- **INCONSISTENCY**: Multiple competing radius values:
  - `--radius: 0.625rem` defined in `:root` (styles.css:51) — used only by dead Tailwind code
  - `border-radius: 16px` on `.vehicle-card` (styles.css:659)
  - `border-radius: 18px` on `.glassmorph` (styles.css:551)
  - `rounded-3` (Bootstrap 0.5rem) on various cards
  - `rounded-pill` (50rem) on buttons
  - `rounded-4` on admin login card (admin-login.php:37)
  - `border-radius: 0.375rem` on all form controls (styles.css:896)
- No documented border-radius scale.

---

### 24. Spacing

**Confirmed Issues:**
- **INCONSISTENCY**: Navbar spacer height varies:
  - `index.php`: no explicit spacer (video starts behind fixed navbar)
  - `about.php:46`: `<div style="height:80px">`
  - `transactions.php:90`: `<div style="height:80px">`
  - `vehicles.php`: no spacer (main has `py-5` only)
  - `faq.php`: no spacer
- All inline styles, not a shared CSS class.
- Section spacing uses Bootstrap utilities (`py-5`, `mb-4`, `mt-5`) inconsistently — no documented spacing scale beyond Bootstrap defaults.

---

## Dependency Map

### CSS Dependencies

```
css/styles.css (905 lines)
├── Lines 1: @import Google Fonts (Inter + Poppins)
├── Lines 3: @custom-variant dark — DEAD (requires Tailwind v4)
├── Lines 5-60: :root variables — ACTIVE but mismatched to brand target
├── Lines 62-97: .dark theme — PARTIAL (some oklch values, no toggle mechanism)
├── Lines 99-154: @theme inline — DEAD (Tailwind v4 scaffold)
├── Lines 156-218: @layer base with @apply — DEAD (Tailwind syntax)
├── Lines 226-232: Font declarations — ACTIVE
├── Lines 240-336: Nested media queries — ACTIVE (redundantly structured)
├── Lines 340-383: Carousel styling — ACTIVE (index.php)
├── Lines 386-430: Navbar dropdown/active — ACTIVE
├── Lines 432-457: Admin sidebar — PARTIALLY BROKEN (undefined vars)
├── Lines 459-497: Category bar — ACTIVE (vehicles.php)
├── Lines 500-524: Mobile logout button — ACTIVE (duplicated from 317-335)
├── Lines 526-545: .booking-step — DEAD (no matching DOM)
├── Lines 547-567: .glassmorph — ACTIVE (duplicate definition)
├── Lines 570-578: .card.three-d — DEAD (markup uses .card.3d)
├── Lines 580-599: .booking-step-indicator — DEAD (no matching DOM)
├── Lines 601-618: Carousel/feature hover — ACTIVE
├── Lines 620-648: Dark mode general — PARTIAL (no toggle)
├── Lines 655-761: Vehicle card styles — ACTIVE (691-732 dead)
├── Lines 762-850: Receipt modal styles — ACTIVE (printer.js)
├── Lines 860-905: Global table/badge/modal/form overrides — ACTIVE
```

### JS Dependencies

```
js/app.js (1208 lines) — loaded on ALL client pages
├── Lines 1-11: Navbar active highlighting — ACTIVE
├── Lines 12-47: Orphaned booking field reads + ReferenceError — BUG
├── Lines 50-65: Datepicker/timepicker init — DEAD (targets non-existent IDs)
├── Lines 69-365: Booking flow (multiple implementations) — PARTIALLY ACTIVE
├── Lines 370-397: Category filter — DUPLICATE of vehicles.php inline
├── Lines 400-405: Accordion fallback — ACTIVE
├── Lines 408-424: Contact form handler — CONFLICTS with about.php inline
├── Lines 428-452: renderNavbarAuth (dead version) — DEAD
├── Lines 458-464: Modal show handlers — ACTIVE
├── Lines 468-534: localStorage auth (commented out) — DEPRECATED
├── Lines 538-543: Booking form submit (demo) — CONFLICTS with line 298+
├── Lines 558-568: showFloatingAlert — NAME COLLISION with voucher-manager.js
├── Lines 572-929: Multi-step booking wizard — DEAD (no DOM)
├── Lines 932-965: getBookingFormData utility — DEAD
├── Lines 968-1022: .btn-reserve handler — CONFLICTS with vehicles.php:717
├── Lines 1025-1029: .booking-step-indicator — DEAD
├── Lines 1033-1047: Scroll animations — ACTIVE
├── Lines 1052-1095: Server auth functions — ACTIVE
├── Lines 1102-1205: Front-end auth handlers — ACTIVE

js/booking-validation.js — loaded on vehicles.php only
├── Date min-date enforcement — ACTIVE
├── Auto-triggers #btnPreview on date change — POTENTIAL ISSUE (clicks preview before user is ready)

js/printer.js — loaded on vehicles.php only
├── showReceiptModal() — ACTIVE (creates dynamic modal)

js/voucher-manager.js — loaded on vehicles.php only
├── VoucherManager.loadVouchers() — DUPLICATE of app.js loadVouchers
├── showFloatingAlert() — NAME COLLISION with app.js
```

### CDN Dependencies

| Library | Version | Client Pages | Admin Pages | Notes |
|---|---|---|---|---|
| Bootstrap CSS | 5.3.2 | All | All | Consistent |
| Bootstrap JS | 5.3.2 | All | All | Consistent |
| Font Awesome | 6.4.2 | All except admin-login | All except admin-login | admin-login.php omits it |
| Animate.css | 4.1.1 | All client | None | Not used on admin |
| jQuery | 3.7.1 | All client | admin-dashboard, admin_vehicles, admin_users, admin_vouchers, view-all-data | **3.6.0 via includes/footer.php** |
| jQuery UI | 1.13.2 | index.php, vehicles.php, about.php | None | |
| jQuery Timepicker | 1.6.3 | index.php, vehicles.php, about.php | None | |
| DataTables | 1.11.5 | None | admin-dashboard (unused), admin_vehicles, admin_users, view-all-data | admin_vouchers lacks it |

**jQuery Version Conflict:** `includes/footer.php:3` loads jQuery **3.6.0**, but every page that uses it also loads 3.7.1 in a `<script>` tag *before* the footer include — the 3.6.0 version from footer.php would overwrite 3.7.1 on admin pages that include `footer.php`. [COMPONENT_LIBRARY] confirms.

---

## Recommended Implementation Order

Based on dependency analysis, blast radius, and risk:

### Tier 1 — Foundation (do first, everything else depends on these)

| # | Task | Rationale | Risk |
|---|---|---|---|
| 1.1 | **Create shared client navbar partial** (`includes/client_navbar.php`) | 6 files depend on it; prerequisite for any page redesign | Low — extract existing markup |
| 1.2 | **Create shared client footer partial** (`includes/client_footer.php`) | 5 files depend on it | Low |
| 1.3 | **Create shared login/signup modal partial** (`includes/auth_modals.php`) | 5 files depend on it | Low |
| 1.4 | **Create shared admin sidebar partial** (`includes/admin_sidebar.php`) | Unblocks admin page consistency | Low |
| 1.5 | **Standardize CSS variables to brand palette** | Every component's color depends on this | Medium — visual change across all pages |
| 1.6 | **Remove dead CSS** (Tailwind scaffold, unused classes) | ~220 lines of confusion removed; zero runtime risk | Very Low |

### Tier 2 — Component Standardization (after Tier 1)

| # | Task | Rationale | Risk |
|---|---|---|---|
| 2.1 | **Standardize button hierarchy** | Defines CTA/secondary/outline/destructive patterns | Low |
| 2.2 | **Standardize card patterns** | Vehicle cards, metric cards, data cards | Low |
| 2.3 | **Standardize form inputs** | One border-radius, consistent labels, validation | Medium |
| 2.4 | **Standardize table patterns** | Consistent DataTables init, thead styles | Low |
| 2.5 | **Standardize badges/status indicators** | Fix admin-dashboard bug, create status-to-class mapping | Low |
| 2.6 | **Standardize alerts/notifications** | Replace JS `alert()` with Bootstrap toasts/alerts | Medium |
| 2.7 | **Standardize typography** | Inter-only, defined type scale, remove `.font-poppins` | Medium — visual change |
| 2.8 | **Standardize spacing** | Navbar spacer class, section spacing convention | Low |

### Tier 3 — Cleanup (after Tier 2)

| # | Task | Rationale | Risk |
|---|---|---|---|
| 3.1 | **Consolidate `js/app.js`** | Remove dead code, deduplicate booking handlers, fix bugs | High — must test thoroughly |
| 3.2 | **Resolve jQuery version split** | Pin one version across all pages | Low-Medium |
| 3.3 | **Remove dead JS** (multi-step modal, localStorage auth) | ~300 lines of dead code | Low |
| 3.4 | **Create loading-state and empty-state components** | Standardize patterns | Low |
| 3.5 | **Fix remaining verified bugs** | Duplicate IDs, undefined vars, status badge logic | Medium |

---

## Risk Assessment

### High Risk
- **`js/app.js` consolidation** (Tier 3.1): This monolith has deeply interleaved, competing handlers. Refactoring must be accompanied by thorough testing of every booking flow path. The `vehicles.php` inline script duplicates app.js logic — both must be reconciled.
- **Brand palette migration** (Tier 1.5): Changing `--primary` from `#2d4a9e` to `#0F2A4D` and `--accent` from `#d97706` to `#63A8FF` affects every page simultaneously. Hardcoded color values (hero gradients, about page CTA) must be found and updated individually.

### Medium Risk
- **Shared navbar extraction** (Tier 1.1): The `#navbarAuthArea` is populated dynamically by `app.js` — the partial must not break this JS hook. The active-state highlighting in `app.js:1-11` also depends on specific `.nav-link` markup structure.
- **Typography standardization** (Tier 2.7): Removing `.font-poppins` and switching to Inter-only will change the visual weight of headings across all pages. Requires visual review.
- **Form standardization** (Tier 2.3): The global `border-radius: 0.375rem` override at `styles.css:894-897` conflicts with `rounded-pill` usage — resolving this requires deciding which is standard.

### Low Risk
- **Dead CSS/JS removal** (Tier 1.6, 3.3): These are inert code blocks. Removing them carries zero runtime risk.
- **Shared footer/modal extraction** (Tier 1.2, 1.3): Mechanical extraction of identical markup.
- **Admin sidebar extraction** (Tier 1.4): Only admin-dashboard.php currently has one; other admin pages get *more* functionality, not less.

### Dependencies Between Tasks
- Tier 2 tasks depend on Tier 1.5 (color variables) being done first
- Tier 3.1 (app.js consolidation) should wait until Tier 2 component patterns are established
- Tier 3.5 (bug fixes) can partially proceed in parallel with Tier 2

---

*This document is audit and analysis only. No code has been modified. Implementation should not proceed without review and approval per CLAUDE.md working rules.*
