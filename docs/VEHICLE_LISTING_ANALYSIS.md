# Vehicle Listing Analysis — Phase 7

Analysis document for the Vehicle Listing Modernization phase. Based on direct inspection of [vehicles.php](../vehicles.php), all project documentation ([CLAUDE.md](../CLAUDE.md), [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md), [UI_ANALYSIS.md](UI_ANALYSIS.md), [UI_IMPLEMENTATION_PLAN.md](UI_IMPLEMENTATION_PLAN.md), [COMPONENT_LIBRARY.md](COMPONENT_LIBRARY.md), [UI_MASTER_PLAN.md](UI_MASTER_PLAN.md), [SHARED_COMPONENTS_AUDIT.md](SHARED_COMPONENTS_AUDIT.md)), all vehicle listing reference screenshots in `references/inspiration/screenshots/client-side/product-listing/`, and the completed Phase 1 shared components and Phase 4 homepage.

**Status:** Analysis only. No code has been modified. Implementation requires separate approval.

---

## Current Vehicle Listing Assessment

### File Under Review

[vehicles.php](../vehicles.php) — 853 lines. The largest client-facing PHP file in the project. Contains the shared navbar (`includes/client_navbar.php`), shared footer (`includes/client_footer.php`), shared auth modals (`includes/auth_modals.php`), a full booking modal with multi-step preview/confirm/receipt flow, category filter buttons, a DB-driven vehicle card grid, and ~300 lines of inline JavaScript handling the booking workflow.

### Current Page Structure

1. **Navbar** — shared partial (`includes/client_navbar.php`)
2. **Booking Modal** — `#bookingModal`, large modal with booking form (dates, times, contact, age, license upload, voucher), preview summary, amount-paid input, receipt display, and redirect to `receipt.php`
3. **Orphaned Trigger Button** — `#openBookingMultiModal`, a visible `btn-warning` button targeting `#bookingMultiModal` which does not exist anywhere in the DOM (confirmed broken — see [BUGS.md](BUGS.md))
4. **Page Title** — centered `<h2>` "Select your vehicle"
5. **Category Filter Bar** — `#categoryBar`, 7 buttons (All/Sedan/SUV/Van/Minivan/Scooter/Pickup), mutually exclusive, JS-driven show/hide
6. **Commented-Out Static Cards** — ~230 lines (lines 214–464) of hardcoded vehicle cards wrapped in an HTML comment, completely superseded by the PHP loop below
7. **DB-Driven Vehicle Grid** — PHP loop over `SELECT * FROM vehicles WHERE is_active = 1 ORDER BY title ASC`, rendering one `.vehicle-card` per vehicle in a `row g-4` / `col-md-4` grid
8. **No Results Message** — `#noResultsMessage`, hidden by default, shown by `app.js`'s category filter handler when zero cards match
9. **Footer** — shared partial (`includes/client_footer.php`)
10. **Auth Modals** — shared partial (`includes/auth_modals.php`)
11. **Scripts** — jQuery 3.7.1, jQuery UI 1.13.2, Bootstrap 5.3.2, jQuery UI Timepicker addon, `printer.js`, `app.js`, `voucher-manager.js`, `booking-validation.js`, plus ~300 lines of inline `<script>` for category filtering, login-check, booking preview/confirm

### Database Connections

Two separate database connections are opened on every page load:

1. **`db.php` (PDO)** — used for the main vehicle query (`SELECT * FROM vehicles WHERE is_active = 1`) and per-vehicle availability count (`SELECT COUNT(*) FROM bookings WHERE vehicle_id = ? AND status IN ('pending','confirmed','completed')`)
2. **`db_connect.php` (mysqli)** — used only for the category dropdown query (`SELECT DISTINCT category FROM vehicles WHERE is_active = 1 ORDER BY category ASC`), which populates a `$categories` PHP array that is **never actually used in the page output** — the `#categoryBar` buttons are hardcoded HTML, not generated from this query

### PHP Queries

| Query | Connection | Purpose | Notes |
|---|---|---|---|
| `SELECT DISTINCT category FROM vehicles WHERE is_active = 1 ORDER BY category ASC` | mysqli (`$conn`) | Populates `$categories` array | **Result is unused** — category buttons are hardcoded |
| `SELECT * FROM vehicles WHERE is_active = 1 ORDER BY title ASC` | PDO (`$pdo`) | Main vehicle listing | No pagination, returns all active vehicles |
| `SELECT COUNT(*) FROM bookings WHERE vehicle_id = ? AND status IN ('pending','confirmed','completed')` | PDO (`$pdo`) | Per-vehicle availability check | Runs once per vehicle (N+1 query pattern) — no date filtering |

### JavaScript Dependencies

| File | Purpose | Notes |
|---|---|---|
| `jquery-3.7.1.min.js` | DOM manipulation, AJAX | Loaded twice — once in `<head>` (line 39), once before `</body>` (line 541) |
| `jquery-ui-1.13.2.min.js` | Date picker (unused on this page) | Loaded but `.datepicker()` is never called on this page |
| `jquery-ui-timepicker-addon` | Time picker (unused on this page) | Loaded but not used |
| `bootstrap.bundle.min.js` | Bootstrap components | Modal, collapse |
| `js/printer.js` | Receipt modal builder | Builds `#receiptModal` — appears unused by current flow |
| `js/app.js` | Navbar auth, category filter (duplicate), floating alerts | Contains a second category filter handler (lines 371–397) |
| `js/voucher-manager.js` | Voucher dropdown population | Duplicates voucher logic also in `app.js` |
| `js/booking-validation.js` | Booking form validation | Date/time validation rules |
| Inline `<script>` (~300 lines) | Category filter, login check, booking preview/confirm/receipt | Contains a third category filter handler (lines 557–567) |

### Category Pre-Select from Homepage

The homepage search widget passes `?category=...` via GET. `vehicles.php` reads this at line 22 (`$selectedCategory = isset($_GET['category']) ? strtolower(trim($_GET['category'])) : ''`) and injects a jQuery `.trigger('click')` on the matching `.category-btn` to simulate a click on page load (lines 569–572). This piggybacks on the inline script's category handler, which in turn triggers the same filtering as `app.js`'s duplicate handler. Both handlers fire on the same click event.

---

## Existing Strengths

1. **DB-driven vehicle cards** — the PHP loop renders real data from the `vehicles` table, with proper `htmlspecialchars` escaping and dynamic availability badges. This was the right architectural choice.
2. **Shared components already extracted** — navbar, footer, and auth modals are single-source partials (Phase 1 complete). Changes propagate automatically.
3. **Brand palette implemented** — CSS variables (`--primary`, `--secondary`, `--accent`) are in place. Vehicle card styling uses brand colors for borders, hover shadows, and price text.
4. **Vehicle card CSS is well-designed** — `.vehicle-card`, `.vehicle-img-wrap`, `.vehicle-specs`, `.vehicle-pricebar` provide a polished card component with hover lift, image zoom, gradient overlay, and clean spec grid. This component is already reused on the homepage's featured vehicles section.
5. **Availability logic works** — per-vehicle booking count determines Available/Unavailable status with appropriate badge colors and disabled button state.
6. **Category pre-select from homepage** — the search widget → vehicles.php handoff works via query parameter.
7. **Booking modal is functional** — the multi-step preview → amount → confirm → receipt → redirect flow works end-to-end despite its complexity.

---

## Existing Weaknesses

### Layout & Structure

1. **No sidebar filter** — only a single row of mutually exclusive category buttons. No multi-select filtering. No price range filter. No capacity filter. No fuel type filter. No transmission filter. The reference (Vroomo) shows a rich sidebar with 6 filter groups.
2. **No sort control** — vehicles are sorted `ORDER BY title ASC` server-side with no user-facing sort option. Reference shows "Sort by Price".
3. **No pagination** — all active vehicles render in a single unpaginated grid. With 31+ active vehicles, this creates a very long page with no way to navigate results.
4. **No result count** — no "X cars available" indicator. Reference shows "200+ cars available".
5. **No search/date toolbar** — no date-range search above the grid. The homepage search widget passes dates but they are ignored on this page (no date-based availability filtering exists).
6. **Fixed 3-column grid** — `col-md-4` creates 3 columns at `md+` and 1 column below. No 4-column option at `xl+`, no 2-column option at `md`. The reference (Vroomo) uses a 3-column grid with a sidebar, while Doon.ph uses a 4-column full-width grid.
7. **Page title is generic** — "Select your vehicle" with no eyebrow label, no breadcrumb trail.

### Code Quality

8. **~230 lines of commented-out dead code** — lines 214–464 contain 11 hardcoded static vehicle cards wrapped in an HTML comment, completely superseded by the PHP loop. Pure dead weight.
9. **Orphaned `#openBookingMultiModal` button** — line 189, a visible `btn-warning` button that targets a non-existent `#bookingMultiModal` modal. This renders as a stray yellow button on the page above the vehicle grid (confirmed broken).
10. **Duplicate category filter handlers** — three separate implementations bound to the same click event: inline script (lines 557–567), `app.js` (lines 371–397), and the pre-select trigger (lines 569–572). Only `app.js`'s version handles the `#noResultsMessage` toggle.
11. **jQuery loaded twice** — once in `<head>` (line 39) and once before `</body>` (line 541). The second load overwrites the first, potentially breaking any handlers bound between the two loads.
12. **Unused jQuery UI / Timepicker libraries** — `jquery-ui.min.js` and `jquery-ui-timepicker-addon.min.js` are loaded but never called on this page (they're for the booking modal's datepicker, which uses `<input type="date">` instead).
13. **Unused `$categories` PHP array** — the mysqli query populates a categories array that is never referenced in the output. The `db_connect.php` connection is opened for no purpose.
14. **Unnecessary `db_connect.php` (mysqli) connection** — a second database connection is opened alongside the PDO one, solely for the unused categories query. This doubles the connection overhead per page load.
15. **N+1 query pattern** — one availability-count query per vehicle. With 31 active vehicles, this means 32 queries per page load (1 listing + 31 availability checks). Could be a single query with a subquery or JOIN.
16. **Inline styles on images** — `style='height:200px; object-fit:cover;'` is hardcoded in the PHP echo for every vehicle image instead of using a CSS class.
17. **`btn-warning` as Reserve CTA** — Bootstrap's amber/yellow `btn-warning` is used for "Reserve Now", which conflicts with the brand palette (no yellow/amber in the Design System). The homepage's featured vehicles already use `btn-primary` for the same action.

### UX Issues

18. **No visual feedback during booking** — clicking "Reserve Now" checks login via async fetch, then opens the modal. No loading indicator during the auth check.
19. **Category filter has no URL state** — selecting a category doesn't update the URL, so the filter state is lost on page refresh (except when arriving from the homepage search widget).
20. **"All Vehicles" button is always initially active** — even when arriving with `?category=suv`, the "All" button briefly shows as active before the jQuery trigger fires, causing a visual flash.
21. **No way to clear filters** — no "Clear All" or "Reset" affordance beyond clicking "All Vehicles".

### Responsiveness

22. **Category buttons don't scroll horizontally on mobile** — `flex-wrap: wrap` causes the 7 buttons to wrap into 2–3 rows on narrow screens, taking significant vertical space. The Doon.ph reference shows horizontal scrolling category tabs with icons.
23. **Vehicle cards go to single column at `< 768px`** — the jump from 3 columns to 1 column is abrupt. A 2-column layout at `sm` (576–767px) would be more appropriate.
24. **No minimum card image height in CSS** — image height is set via inline style (`200px`), not a responsive CSS class.

### Accessibility

25. **No `aria-label` on category filter buttons** — the filter bar has no `role` or label indicating it's a filter group.
26. **Availability conveyed by color + text** — this is actually an accessible pattern (not color-only), so no issue here.
27. **Booking modal form labels lack `for` attribute pairing** — labels use text content only, not `for`/`id` pairing (though inputs do have `id` attributes).
28. **No skip-to-content or landmark roles** — `<main>` is used (good), but no `aria-label` on the vehicle grid region.

---

## Reference Comparison

### References Reviewed

1. **vroomo-product-listing-with-filter** (primary) — full-page screenshot: search toolbar at top (location, pickup/dropoff date+time, search button), breadcrumb trail, sidebar filter panel (Location Type, Vehicle Categories, Capacity, Car Types, Cancellation Policy, Total Price), result count ("200+ cars available"), sort dropdown ("Sort by Price"), 3-column card grid, "DETAILS" button per card.
2. **doon-ph-product-listing** (secondary) — full-page screenshot: search toolbar (Where?, Trip Start, Trip End), horizontal icon-based category tabs (Any, Hatchback, Sedan, Station Wagon, Coupe, Convertible, Crossover, SUV, Pick Up, MPV, Van), result count ("498 cars available"), 4-column card grid, price/rating/specs per card, "Show Map" toggle for split-view map.
3. **doon-ph-product-listing-show-map** (secondary) — map view variant: 2-column card grid + Google Maps with price pins.

### Gap Analysis

| Dimension | Reference Target | Current State | Gap |
|---|---|---|---|
| **Search/date toolbar** | Floating search bar with location, pickup/dropoff date+time (Vroomo); location + trip start/end (Doon) | Absent — dates only collected inside booking modal after selecting a vehicle | **High** |
| **Sidebar filter** | Multi-group checkbox sidebar: location type, categories, capacity, car types, price range (Vroomo) | Single row of 7 mutually exclusive category buttons | **High** |
| **Sort control** | "Sort by Price" dropdown with sort-direction icon (Vroomo) | No sort control — fixed `ORDER BY title ASC` | **High** |
| **Pagination** | Implied by large result sets (200+, 498 cars) | All vehicles rendered unpaginated | **Medium-High** |
| **Result count** | "200+ cars available" / "498 cars available" prominently displayed | Absent | **Medium** |
| **Breadcrumb** | "HOME / CAR LISTING" trail (Vroomo) | Absent | **Low-Medium** |
| **Vehicle cards** | Photo, title, spec icons (seats, doors, transmission, fuel), price/day, "DETAILS" CTA (Vroomo); photo, price, title, location, seating, transmission, rating (Doon) | Photo, title, category label, 3-icon spec row, price/day, availability badge, "Reserve Now" CTA — functionally similar to Vroomo, missing rating | **Low** (cards are already close to reference) |
| **Card grid density** | 3-column with sidebar (Vroomo); 4-column full-width (Doon) | 3-column full-width, no sidebar | **Medium** (changes with sidebar addition) |
| **Category tabs with icons** | Horizontal scrolling tabs with car silhouette icons (Doon) | Plain text buttons, no icons, wrapping layout | **Medium** |
| **Map view toggle** | Split-view map with price pins (Doon — optional/bonus) | Not present — explicitly optional per master plan | **Low** (out of scope) |

---

## Recommended Page Structure

Based on the reference designs (primarily Vroomo), project requirements, existing implementation, and the Design System:

```
┌─────────────────────────────────────────────────┐
│  Navbar (shared partial — no changes)           │
├─────────────────────────────────────────────────┤
│  Page Header                                    │
│  - Breadcrumb: Home > Vehicles                  │
│  - Page title: "Browse Our Fleet"               │
│  - Section eyebrow: "VEHICLES"                  │
├─────────────────────────────────────────────────┤
│  Search / Date Toolbar (optional — Phase 2)     │
│  - Reuse homepage search widget pattern         │
│  - Pickup Date | Return Date | Category | Search│
├─────────────────────────────────────────────────┤
│  ┌──────────┐  ┌──────────────────────────────┐ │
│  │ Sidebar  │  │ Results Header               │ │
│  │ Filter   │  │ "X vehicles available"       │ │
│  │ Panel    │  │ Sort: Price ↑↓ | Name        │ │
│  │          │  ├──────────────────────────────┤ │
│  │ Category │  │ Vehicle Card Grid            │ │
│  │ □ Sedan  │  │ ┌────┐ ┌────┐ ┌────┐        │ │
│  │ □ SUV    │  │ │Card│ │Card│ │Card│        │ │
│  │ □ Van    │  │ └────┘ └────┘ └────┘        │ │
│  │ □ ...    │  │ ┌────┐ ┌────┐ ┌────┐        │ │
│  │          │  │ │Card│ │Card│ │Card│        │ │
│  │ Price    │  │ └────┘ └────┘ └────┘        │ │
│  │ Range    │  ├──────────────────────────────┤ │
│  │ ₱___-₱___│  │ Pagination                  │ │
│  │          │  │ « 1 2 3 4 ... »             │ │
│  │ Seats    │  └──────────────────────────────┘ │
│  │ □ 2      │                                   │
│  │ □ 4-5    │                                   │
│  │ □ 7+     │                                   │
│  │          │                                   │
│  │ Fuel     │                                   │
│  │ □ Gas    │                                   │
│  │ □ Diesel │                                   │
│  │          │                                   │
│  │ Trans.   │                                   │
│  │ □ Auto   │                                   │
│  │ □ Manual │                                   │
│  │          │                                   │
│  │[Clear]   │                                   │
│  └──────────┘                                   │
├─────────────────────────────────────────────────┤
│  Footer (shared partial — no changes)           │
├─────────────────────────────────────────────────┤
│  Auth Modals (shared partial — no changes)      │
│  Booking Modal (preserve existing)              │
└─────────────────────────────────────────────────┘
```

### Mobile Layout (< 992px)

```
┌─────────────────────────────┐
│  Navbar                     │
├─────────────────────────────┤
│  Page Header                │
│  Breadcrumb + Title         │
├─────────────────────────────┤
│  [🔍 Filters] button       │
│  "X vehicles" | Sort ↕     │
├─────────────────────────────┤
│  Vehicle Card Grid (2-col)  │
│  ┌─────┐ ┌─────┐           │
│  │Card │ │Card │           │
│  └─────┘ └─────┘           │
│  ┌─────┐ ┌─────┐           │
│  │Card │ │Card │           │
│  └─────┘ └─────┘           │
├─────────────────────────────┤
│  Pagination                 │
├─────────────────────────────┤
│  Footer                     │
└─────────────────────────────┘

Filter Panel = Bootstrap offcanvas (slides from left)
```

---

## Component Reuse

| Page Section | Existing Shared Components | New Components Required |
|---|---|---|
| Navbar | `includes/client_navbar.php` | None |
| Page Header | `.section-eyebrow` (from homepage) | Breadcrumb markup (standard Bootstrap) |
| Sidebar Filter | Bootstrap accordion, form-check, form-range | Filter panel CSS, offcanvas for mobile |
| Results Header | Bootstrap utilities | Result count + sort dropdown |
| Vehicle Cards | `.vehicle-card`, `.vehicle-img-wrap`, `.vehicle-specs`, `.vehicle-pricebar` (all existing) | Minor enhancements only |
| Pagination | Bootstrap `.pagination` component | PHP pagination logic |
| Footer | `includes/client_footer.php` | None |
| Auth Modals | `includes/auth_modals.php` | None |
| Booking Modal | Existing `#bookingModal` | None (preserve as-is) |

### Summary

- **Existing components reused:** 10+ (shared partials, CSS classes, Bootstrap components)
- **New components required:** 3 (sidebar filter panel, sort dropdown, pagination)
- **New PHP logic required:** 2 (pagination query with LIMIT/OFFSET, server-side sort parameter)
- **Components to modify:** 1 (vehicle card — minor: replace inline image style with CSS class, replace `btn-warning` with `btn-primary`)

---

## Files Affected

| File | Action | Scope |
|---|---|---|
| `vehicles.php` | **Major modification** | Page structure, layout, PHP queries, filter logic, pagination, cleanup |
| `css/styles.css` | **Moderate addition** | Sidebar filter CSS, pagination styling, responsive grid adjustments |
| `js/app.js` | **Minor modification** | Remove or reconcile duplicate category filter handler |
| `db_connect.php` | **No change** | Will be unused after cleanup (connection can be removed from vehicles.php) |
| `db.php` | **No change** | Existing PDO connection, reused |
| `includes/client_navbar.php` | **No change** | Shared partial |
| `includes/client_footer.php` | **No change** | Shared partial |
| `includes/auth_modals.php` | **No change** | Shared partial |

---

## Implementation Complexity

| Section | Complexity | Rationale |
|---|---|---|
| Page Header (breadcrumb + title) | **Low** | Simple Bootstrap breadcrumb + heading markup |
| Dead code cleanup | **Low** | Remove commented-out cards, orphaned button, unused `db_connect.php` include, unused `$categories` variable |
| Vehicle card fixes | **Low** | Replace inline image styles with CSS class, `btn-warning` → `btn-primary`, remove jQuery double-load |
| Results header (count + sort) | **Low-Medium** | Count from existing query, sort requires adding `ORDER BY` parameter handling |
| Sidebar filter panel | **Medium** | New UI component with checkbox groups, Bootstrap offcanvas for mobile, JS filtering logic |
| Pagination | **Medium** | PHP `LIMIT`/`OFFSET` query modification, page parameter handling, Bootstrap pagination component |
| Filter → query integration | **Medium-High** | Server-side filtering by multiple criteria (category, price range, seats, fuel, transmission), URL state management, coordination with pagination |
| Duplicate JS reconciliation | **Low-Medium** | Consolidate 3 category filter handlers into one; remove unused library loads |

---

## Risks and Mitigations

| Risk | Severity | Mitigation |
|---|---|---|
| Breaking the booking modal flow | **High** | The booking modal and its JS handlers must be preserved exactly. All booking-related code (preview, confirm, receipt, redirect) should be treated as untouchable unless explicitly requested. Test the full booking flow after any change. |
| Filter state lost on pagination | **Medium** | Pass filter parameters as GET query strings (e.g., `?category[]=sedan&category[]=suv&sort=price_asc&page=2`). Ensure all filter values survive page navigation. |
| N+1 query performance with pagination | **Medium** | Optimize the availability query with a JOIN or subquery when implementing pagination, to avoid running a separate count query per vehicle. |
| Category filter handler conflicts | **Medium** | The three duplicate handlers currently all fire on the same click. When refactoring, ensure the homepage `?category=` pre-select still works. |
| Mobile filter UX | **Medium** | The offcanvas filter panel must be tested at 320px, 375px. Filter state should be preserved when the offcanvas is opened/closed. |
| URL query string complexity | **Low** | Keep parameter names simple and consistent: `category[]`, `fuel[]`, `transmission[]`, `seats_min`, `price_min`, `price_max`, `sort`, `page`. |

---

## Testing Checklist

### Functional

- [ ] All active vehicles display in the grid (with no filters applied)
- [ ] Category filter works (single and multi-select)
- [ ] Price range filter works
- [ ] Seats filter works
- [ ] Fuel type filter works
- [ ] Transmission filter works
- [ ] Filters can be combined (e.g., SUV + Diesel + 7+ seats)
- [ ] "Clear All" / reset filters returns to unfiltered state
- [ ] Sort by price ascending works
- [ ] Sort by price descending works
- [ ] Sort by name works
- [ ] Pagination navigates correctly
- [ ] Filter state persists across pagination
- [ ] Result count updates when filters change
- [ ] Homepage search widget `?category=` pre-select still works
- [ ] "Reserve Now" button opens booking modal for available vehicles
- [ ] "Unavailable" button is disabled for unavailable vehicles
- [ ] Full booking flow works: Reserve → Preview → Amount → Confirm → Receipt → Redirect
- [ ] No Results message displays when filters return zero vehicles
- [ ] Breadcrumb "Home" link navigates to `index.php`

### Responsive

- [ ] Desktop (1200px+): sidebar visible, 3-column grid
- [ ] Laptop (992–1199px): sidebar visible, 2-column grid
- [ ] Tablet (768–991px): sidebar hidden, filter button visible, 2-column grid, offcanvas filter
- [ ] Mobile (576–767px): filter button, 2-column grid
- [ ] Small mobile (320–575px): filter button, 1-column grid (or 2-column with smaller cards)
- [ ] No horizontal overflow at any breakpoint
- [ ] Filter offcanvas opens/closes correctly on mobile
- [ ] Pagination controls are touch-friendly

### Accessibility

- [ ] Filter panel has appropriate `role` and `aria-label`
- [ ] Sort dropdown is keyboard-accessible
- [ ] Pagination has `aria-label="Pagination"` and current page has `aria-current="page"`
- [ ] Vehicle card images have `alt` text (existing — verify preserved)
- [ ] Heading hierarchy is correct (`h1` or `h2` page title, `h5` card titles)
- [ ] No new console errors

### Regression

- [ ] Navbar auth state works (login/logout)
- [ ] Footer renders correctly
- [ ] Auth modals open/close correctly
- [ ] Booking modal behavior unchanged
- [ ] Homepage featured vehicles section unaffected
- [ ] Other pages sharing `css/styles.css` unaffected
- [ ] `app.js` category filter handler reconciled (no duplicate handlers)

---

## Acceptance Criteria

- [ ] Vehicle listing page has a sidebar filter panel with category, price range, seats, fuel, and transmission filters
- [ ] Sidebar collapses to a Bootstrap offcanvas on mobile (< 992px)
- [ ] A "Sort by" dropdown allows sorting by price (asc/desc) and name
- [ ] Result count ("X vehicles available") displays above the grid
- [ ] Pagination limits results per page (suggested: 9 or 12 per page)
- [ ] Filter state persists in URL query parameters
- [ ] All dead code removed (commented-out cards, orphaned multi-step button, unused PHP variables/connections)
- [ ] `btn-warning` replaced with `btn-primary` on Reserve button (brand consistency)
- [ ] Inline image styles replaced with CSS class
- [ ] jQuery loaded only once
- [ ] Duplicate category filter handlers consolidated
- [ ] Existing booking flow completely preserved
- [ ] Responsive at all breakpoints (320px through 1400px+)
- [ ] No new console errors
- [ ] No PHP errors
- [ ] Breadcrumb navigation present
- [ ] Page follows the project Design System

---

*This document is analysis only. No code has been modified. Implementation should not proceed without review and approval per CLAUDE.md working rules.*
