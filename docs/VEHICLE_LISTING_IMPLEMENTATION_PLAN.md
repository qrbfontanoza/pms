# Vehicle Listing Implementation Plan — Phase 7

Detailed implementation plan for the Vehicle Listing Modernization, derived from [VEHICLE_LISTING_ANALYSIS.md](VEHICLE_LISTING_ANALYSIS.md). Each step includes objective, files affected, dependencies, components used, design requirements, Bootstrap requirements, testing requirements, and acceptance criteria.

**Status:** Plan only. No code has been modified. Implementation requires approval per CLAUDE.md working rules.

**Pre-implementation checklist (applies to every step):**
- Read CLAUDE.md
- Read DESIGN_SYSTEM.md (docs/DESIGN_SYSTEM.md)
- Read VEHICLE_LISTING_ANALYSIS.md
- Inspect current vehicles.php
- Identify any changes to shared components since this plan was written

---

## Implementation Sequence Overview

The plan is organized into 7 steps, sequenced from lowest risk to highest complexity:

```
Step 1: Cleanup & Foundation (dead code, bugs, CSS fixes)
  ↓
Step 2: Page Header & Breadcrumb
  ↓
Step 3: Results Header (count + sort)
  ↓
Step 4: Sidebar Filter Panel
  ↓
Step 5: Server-Side Filtering Integration
  ↓
Step 6: Pagination
  ↓
Step 7: Final Polish & JS Reconciliation
```

### Dependency Graph

```
Step 1 (Cleanup) ──── independent, prerequisite for all others
Step 2 (Header) ──── independent
Step 3 (Results Header) ──── depends on Step 1 (clean grid structure)
Step 4 (Sidebar Filter) ──── depends on Step 1 (clean layout)
Step 5 (Filter Integration) ──── depends on Step 4 (sidebar exists)
Step 6 (Pagination) ──── depends on Step 5 (filters in URL params)
Step 7 (Polish) ──── depends on all prior steps
```

Steps 2 and 3 are independent of each other and could be implemented in either order. Steps 4–6 are sequential. Step 1 must come first.

---

## Step 1: Cleanup & Foundation

### Objective

Remove dead code, fix known bugs, and establish a clean foundation for the remaining steps. This step produces no visible UI change beyond removing the orphaned button — it is purely a code-quality improvement that reduces risk for all subsequent steps.

### Files Affected

| File | Action |
|---|---|
| `vehicles.php` | Modify — remove dead code, fix double jQuery load, remove unused PHP, replace inline styles |
| `css/styles.css` | Modify — add `.vehicle-img` class for image sizing (replaces inline `style`) |

### Changes

1. **Remove commented-out static vehicle cards** — delete lines 214–464 (the `<!-- ... -->` block containing 11 hardcoded cards). This is ~230 lines of pure dead code that has been fully superseded by the PHP loop.

2. **Remove orphaned `#openBookingMultiModal` button** — delete line 189 (`<button class="btn btn-warning rounded-pill" id="openBookingMultiModal">Book Now (Multi-Step)</button>`). This button targets a modal that does not exist anywhere in the DOM. It renders as a stray visible yellow button on the page.

3. **Remove duplicate jQuery load** — delete line 39 (`<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>` in `<head>`). Keep only the load before `</body>` (line 541). jQuery in `<head>` is not needed — no inline script between `<head>` and the pre-`</body>` scripts depends on it.

4. **Remove unused jQuery UI and Timepicker** — delete lines 542 and 544–545 (`jquery-ui.min.js` and `jquery-ui-timepicker-addon.min.js`). Neither `.datepicker()` nor `.timepicker()` is called on this page — the booking modal uses native `<input type="date">` and `<input type="time">`.

5. **Remove unused `db_connect.php` include and `$categories` query** — delete lines 11–19 (the `include 'db_connect.php'`, the `$categoryQuery`, and the `while` loop populating `$categories`). The `$categories` array is never referenced in the page output. The `#categoryBar` buttons are hardcoded HTML. This eliminates the unnecessary second database connection.

6. **Replace inline image styles with CSS class** — in the PHP vehicle loop, replace `style='height:200px; object-fit:cover;'` with `class='vehicle-img'`. Add the corresponding CSS rule to `css/styles.css`:
   ```css
   .vehicle-img-wrap img {
     height: 200px;
     object-fit: cover;
   }
   ```
   Note: `.vehicle-img-wrap img` already has `display: block; width: 100%;` in `styles.css` (line 466–468). Adding `height` and `object-fit` to the same rule avoids creating a new class entirely — just extend the existing rule.

7. **Replace `btn-warning` with `btn-primary`** — in the PHP vehicle loop, change `$btnClass = $isAvailable ? 'btn-warning' : 'btn-secondary disabled';` to `$btnClass = $isAvailable ? 'btn-primary' : 'btn-secondary disabled';`. This aligns the Reserve button with the brand palette (the homepage featured vehicles already use `btn-primary`).

### Dependencies

- None. This step is self-contained.

### Testing Requirements

- [ ] All active vehicles still display correctly
- [ ] Vehicle images display at correct size (200px height, cover fit)
- [ ] "Reserve Now" button is now `btn-primary` (brand blue, not amber)
- [ ] Orphaned yellow "Book Now (Multi-Step)" button is gone
- [ ] Booking modal still opens and works (full flow: preview → amount → confirm → receipt)
- [ ] Category filter still works (both inline handler and `app.js` handler)
- [ ] Homepage `?category=` pre-select still works
- [ ] No new console errors
- [ ] No PHP errors
- [ ] No visual regression on other pages sharing `css/styles.css`

### Acceptance Criteria

- [ ] ~230 lines of commented-out dead code removed
- [ ] Orphaned `#openBookingMultiModal` button removed
- [ ] jQuery loaded exactly once (before `</body>`)
- [ ] Unused jQuery UI / Timepicker libraries removed
- [ ] Unused `db_connect.php` include and `$categories` query removed
- [ ] Inline image styles replaced with CSS
- [ ] `btn-warning` → `btn-primary` on Reserve button
- [ ] All existing functionality preserved

---

## Step 2: Page Header & Breadcrumb

### Objective

Add a proper page header with breadcrumb navigation and section eyebrow label, matching the design language established on the homepage and following the Vroomo reference's "HOME / CAR LISTING" pattern.

### Files Affected

| File | Action |
|---|---|
| `vehicles.php` | Modify — replace current `<h2>` with breadcrumb + eyebrow + heading block |

### Dependencies

- Step 1 (cleanup) should be complete for a clean working base.
- `.section-eyebrow` CSS class already exists (from homepage Step 1).

### Components Used

| Component | Source | Status |
|---|---|---|
| `.section-eyebrow` | `css/styles.css:395–399` | Existing — reuse |
| Bootstrap breadcrumb | `.breadcrumb`, `.breadcrumb-item` | Existing Bootstrap component |
| Brand CSS variables | `css/styles.css:3–13` | Existing |

### Design Requirements

- Breadcrumb trail: `Home > Vehicles` — "Home" links to `index.php`, "Vehicles" is the current page (no link, `aria-current="page"`)
- Section eyebrow: "VEHICLES" — reuses `.section-eyebrow` class from homepage
- Page heading: "Browse Our Fleet" or similar — `h2.fw-bold`
- Optional subheading: "Find the perfect vehicle for your next trip" — `text-muted`
- Centered alignment, consistent with homepage section headings
- Add `margin-top: 56px` to account for fixed navbar (or wrap in a section with `pt-5 mt-5`)

### Bootstrap Requirements

- `container`, `py-4`
- `breadcrumb`, `breadcrumb-item`
- `text-center`, `fw-bold`
- `aria-label="breadcrumb"`, `aria-current="page"`

### Testing Requirements

- [ ] Breadcrumb displays "Home > Vehicles"
- [ ] "Home" link navigates to `index.php`
- [ ] Eyebrow label renders in `--secondary` color
- [ ] Page heading is visible and properly styled
- [ ] Responsive at all breakpoints
- [ ] No console errors
- [ ] Heading hierarchy is correct (no skipped levels)

### Acceptance Criteria

- [ ] Breadcrumb navigation present with correct links
- [ ] Section eyebrow reuses existing `.section-eyebrow` class
- [ ] Page heading replaces the generic "Select your vehicle"
- [ ] Responsive at all breakpoints
- [ ] ARIA attributes present on breadcrumb

---

## Step 3: Results Header (Count + Sort)

### Objective

Add a results header bar above the vehicle grid showing the total number of available vehicles and a sort dropdown, matching the Vroomo reference's "200+ cars available" + "Sort by Price" pattern.

### Files Affected

| File | Action |
|---|---|
| `vehicles.php` | Modify — add results header markup between category bar and grid. Modify PHP query to support `ORDER BY` parameter. |

### Dependencies

- Step 1 (cleanup) complete.

### Components Used

| Component | Source | Status |
|---|---|---|
| Bootstrap form-select | `.form-select`, `.form-select-sm` | Existing Bootstrap component |
| Bootstrap grid | `d-flex`, `justify-content-between`, `align-items-center` | Existing Bootstrap utilities |

### Design Requirements

- Left side: "**X** vehicles available" — bold count, regular text
- Right side: Sort dropdown (`<select>`) with options:
  - "Name (A–Z)" — default, current behavior
  - "Name (Z–A)"
  - "Price: Low to High"
  - "Price: High to Low"
  - "Newest First"
- Sort selection modifies the PHP `ORDER BY` clause via GET parameter (`?sort=price_asc`)
- Sort state preserved in URL for pagination compatibility
- Compact visual style — not a full-width section, just a utility bar

### PHP Changes

- Read `$_GET['sort']` with whitelist validation:
  ```php
  $allowedSorts = [
    'title_asc'  => 'title ASC',
    'title_desc' => 'title DESC',
    'price_asc'  => 'price_per_day ASC',
    'price_desc' => 'price_per_day DESC',
    'newest'     => 'id DESC',
  ];
  $sortParam = $_GET['sort'] ?? 'title_asc';
  $orderBy = $allowedSorts[$sortParam] ?? 'title ASC';
  ```
- Modify the main query: `SELECT * FROM vehicles WHERE is_active = 1 ORDER BY $orderBy`
- The `$orderBy` value comes from the whitelist, not from user input — no SQL injection risk.

### Bootstrap Requirements

- `d-flex justify-content-between align-items-center mb-3`
- `form-select form-select-sm` on the sort dropdown
- `fw-bold` on the count number

### Testing Requirements

- [ ] Vehicle count displays correctly and matches the actual number of rendered cards
- [ ] Sort by Name A–Z works (default)
- [ ] Sort by Name Z–A works
- [ ] Sort by Price Low→High works
- [ ] Sort by Price High→Low works
- [ ] Sort by Newest First works
- [ ] Sort state persists in URL (`?sort=price_asc`)
- [ ] Sort dropdown shows the currently active sort option as selected
- [ ] Responsive: count and sort fit on one line at all breakpoints (stack vertically on very narrow screens if needed)
- [ ] No console errors

### Acceptance Criteria

- [ ] Result count displays above the grid
- [ ] Sort dropdown with 5 options is functional
- [ ] Sort modifies the actual query order (server-side, not JS)
- [ ] Sort state preserved in URL
- [ ] No SQL injection possible (whitelist approach)

---

## Step 4: Sidebar Filter Panel

### Objective

Add a sidebar filter panel to the left of the vehicle grid, matching the Vroomo reference's multi-group checkbox layout. The sidebar is visible on desktop (≥992px) and collapses to a Bootstrap offcanvas on mobile (<992px), triggered by a filter button.

### Files Affected

| File | Action |
|---|---|
| `vehicles.php` | Modify — restructure page layout to sidebar + main content. Add filter panel markup. Add mobile filter toggle button. |
| `css/styles.css` | Modify — add sidebar filter panel CSS (~30–40 lines). |

### Dependencies

- Step 1 (cleanup) and Step 3 (results header) should be complete.
- Bootstrap offcanvas component (already loaded).

### Components Used

| Component | Source | Status |
|---|---|---|
| Bootstrap offcanvas | `offcanvas`, `offcanvas-lg` | Existing Bootstrap component (same pattern as admin sidebar) |
| Bootstrap accordion | `accordion`, `accordion-item` | Existing Bootstrap component (for collapsible filter groups) |
| Bootstrap form-check | `form-check`, `form-check-input`, `form-check-label` | Existing Bootstrap component |
| Bootstrap form-range | `form-range` | Existing Bootstrap component (for price slider) |
| Brand CSS variables | `css/styles.css:3–13` | Existing |

### Design Requirements

**Layout:**
- Desktop (≥992px): 3-column Bootstrap grid — `col-lg-3` sidebar + `col-lg-9` main content
- Mobile (<992px): sidebar hidden, "Filters" button visible above grid, sidebar opens as offcanvas from left
- Use `offcanvas-lg` class (same pattern as the admin sidebar, proven in this codebase)

**Filter Groups** (each as an accordion section or a simple heading + checkboxes):

1. **Category** — checkbox list populated from database categories:
   - Sedan, SUV, Van, Minivan, Scooter, Pickup (current DB values)
   - Multi-select (checkboxes, not radio buttons — unlike the current mutually exclusive button bar)
   - Shows count of vehicles per category in parentheses: "Sedan (8)"

2. **Price Range** — min/max number inputs or a range slider:
   - Two `<input type="number">` fields: Min ₱ and Max ₱
   - Pre-populated with the actual min and max prices from the database
   - Or a simpler approach: preset range checkboxes (₱500–₱1,000 / ₱1,000–₱2,000 / ₱2,000–₱3,000 / ₱3,000+)

3. **Seats** — checkbox groups:
   - 2 seats, 4–5 seats, 7+ seats, 8+ seats
   - Derived from the `seats` column in the vehicles table

4. **Fuel Type** — checkboxes:
   - Gasoline, Diesel
   - Derived from `SELECT DISTINCT fuel FROM vehicles WHERE is_active = 1`

5. **Transmission** — checkboxes:
   - Automatic, Manual
   - Derived from `SELECT DISTINCT transmission FROM vehicles WHERE is_active = 1`

6. **Availability** — optional toggle:
   - "Show available only" (default: checked)
   - "Show all" (includes unavailable vehicles)

**Filter Actions:**
- "Apply Filters" button (primary) — submits form as GET request to reload page with filter params
- "Clear All" link — resets all filters, navigates to `vehicles.php` with no query params
- Active filter count badge on the mobile "Filters" button

**Visual Style:**
- Clean white background, subtle border or shadow
- Filter group headings in `fw-semibold`, slightly smaller than body text
- Checkbox labels in normal text
- Consistent spacing between filter groups
- "Clear All" link in `text-muted` or `--secondary` color

### Bootstrap Requirements

- `offcanvas offcanvas-start offcanvas-lg` for sidebar
- `offcanvas-header`, `offcanvas-body`
- `form-check`, `form-check-input`, `form-check-label`
- `btn btn-primary w-100 rounded-pill` for "Apply Filters"
- `btn btn-link text-decoration-none` for "Clear All"
- `badge bg-primary rounded-pill` for active filter count on mobile button
- `d-lg-none` on mobile filter toggle button
- `col-lg-3`, `col-lg-9` for sidebar/content split

### CSS Additions

```css
.filter-sidebar {
  /* Sticky on desktop so it scrolls with the page */
}

@media (min-width: 992px) {
  .filter-sidebar {
    position: sticky;
    top: 80px; /* below fixed navbar */
    max-height: calc(100vh - 100px);
    overflow-y: auto;
  }
}

.filter-group {
  /* Spacing between filter sections */
  padding-bottom: 1rem;
  margin-bottom: 1rem;
  border-bottom: 1px solid var(--border);
}

.filter-group:last-child {
  border-bottom: none;
}

.filter-group-title {
  font-weight: 600;
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--primary);
  margin-bottom: 0.75rem;
}
```

### Testing Requirements

- [ ] Sidebar visible on desktop (≥992px), positioned left of the grid
- [ ] Sidebar hidden on mobile, "Filters" button visible
- [ ] Offcanvas opens from left on mobile when "Filters" button is clicked
- [ ] Offcanvas closes via close button, backdrop click, or ESC key
- [ ] All 5 filter groups render with correct options from database
- [ ] Checkboxes are selectable (multi-select within each group)
- [ ] "Apply Filters" button is clickable
- [ ] "Clear All" link clears all selections
- [ ] Sidebar is sticky on desktop (doesn't scroll away)
- [ ] Sidebar doesn't overflow vertically (scrollable if content exceeds viewport)
- [ ] No horizontal overflow at any breakpoint
- [ ] No console errors

### Acceptance Criteria

- [ ] Sidebar filter panel with 5+ filter groups
- [ ] Desktop: sidebar visible in left column
- [ ] Mobile: offcanvas filter triggered by button
- [ ] Filter UI matches project Design System
- [ ] No changes to booking modal
- [ ] Responsive at all breakpoints

---

## Step 5: Server-Side Filtering Integration

### Objective

Wire the sidebar filter panel to actual PHP query filtering, so that selecting filter options and clicking "Apply" reloads the page with filtered results. All filter state is preserved in URL query parameters.

### Files Affected

| File | Action |
|---|---|
| `vehicles.php` | Modify — read filter GET params, build dynamic WHERE clause, re-populate checkbox states from params |

### Dependencies

- Step 4 (sidebar filter panel) must be complete (filter UI exists).
- Step 3 (sort) should be complete (sort param preserved alongside filter params).

### PHP Changes

**Read filter parameters:**
```php
$filterCategories    = isset($_GET['category']) ? (array)$_GET['category'] : [];
$filterFuels         = isset($_GET['fuel']) ? (array)$_GET['fuel'] : [];
$filterTransmissions = isset($_GET['transmission']) ? (array)$_GET['transmission'] : [];
$filterSeatsMin      = isset($_GET['seats_min']) ? (int)$_GET['seats_min'] : 0;
$filterPriceMin      = isset($_GET['price_min']) ? (float)$_GET['price_min'] : 0;
$filterPriceMax      = isset($_GET['price_max']) ? (float)$_GET['price_max'] : 0;
$sortParam           = $_GET['sort'] ?? 'title_asc';
```

**Build dynamic query with prepared statement:**
```php
$where = ['is_active = 1'];
$params = [];

if (!empty($filterCategories)) {
    $placeholders = implode(',', array_fill(0, count($filterCategories), '?'));
    $where[] = "category IN ($placeholders)";
    $params = array_merge($params, $filterCategories);
}

if (!empty($filterFuels)) {
    $placeholders = implode(',', array_fill(0, count($filterFuels), '?'));
    $where[] = "fuel IN ($placeholders)";
    $params = array_merge($params, $filterFuels);
}

if (!empty($filterTransmissions)) {
    $placeholders = implode(',', array_fill(0, count($filterTransmissions), '?'));
    $where[] = "transmission IN ($placeholders)";
    $params = array_merge($params, $filterTransmissions);
}

if ($filterSeatsMin > 0) {
    $where[] = "seats >= ?";
    $params[] = $filterSeatsMin;
}

if ($filterPriceMin > 0) {
    $where[] = "price_per_day >= ?";
    $params[] = $filterPriceMin;
}

if ($filterPriceMax > 0) {
    $where[] = "price_per_day <= ?";
    $params[] = $filterPriceMax;
}

$sql = "SELECT * FROM vehicles WHERE " . implode(' AND ', $where) . " ORDER BY $orderBy";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vehicles = $stmt->fetchAll();
```

**Re-populate filter checkboxes:**
Each checkbox in the sidebar form should check whether its value is in the corresponding `$filter*` array, and if so, output `checked` attribute. This preserves the filter state after page reload.

**Preserve sort + filter params:**
The sort dropdown's form action should include current filter params as hidden fields (or the sort dropdown should use JavaScript to append `sort=` to the current URL).

**Replace existing category button bar:**
The old `#categoryBar` buttons should be removed entirely. Category filtering is now handled by the sidebar checkboxes. The homepage `?category=` pre-select should be adapted to work with the new `?category[]=` array format (single value maps to a one-element array, which works with the `IN (?)` query).

### Security

- All filter parameters use prepared statements with bound parameters — no SQL injection.
- `$orderBy` comes from a whitelist (Step 3), not user input.
- Array parameters (`category[]`, `fuel[]`, etc.) are validated against database values or cast to appropriate types.

### Testing Requirements

- [ ] Filtering by single category works
- [ ] Filtering by multiple categories works (checkboxes)
- [ ] Filtering by price range works
- [ ] Filtering by seats works
- [ ] Filtering by fuel type works
- [ ] Filtering by transmission works
- [ ] Combined filters work (e.g., SUV + Diesel + ₱2,000–₱3,000)
- [ ] Filter state preserved in URL query string
- [ ] Filter checkboxes remain checked after page reload
- [ ] Sort state preserved alongside filter state
- [ ] "Clear All" removes all filter params from URL
- [ ] Homepage `?category=suv` pre-select still works
- [ ] Result count updates to reflect filtered results
- [ ] Empty results show "No vehicles match your filters" message
- [ ] No SQL injection possible (all params are bound)
- [ ] No PHP errors with malformed query params

### Acceptance Criteria

- [ ] All 5 filter groups are wired to server-side query filtering
- [ ] Filter state is preserved in URL query parameters
- [ ] All filter combinations produce correct results
- [ ] Prepared statements used for all user input
- [ ] Old `#categoryBar` removed
- [ ] Homepage category pre-select works with new filter system
- [ ] "No results" state handled gracefully

---

## Step 6: Pagination

### Objective

Add server-side pagination to limit the number of vehicles displayed per page, with Bootstrap pagination controls at the bottom of the grid. Filter and sort state must be preserved across pages.

### Files Affected

| File | Action |
|---|---|
| `vehicles.php` | Modify — add `LIMIT`/`OFFSET` to query, add pagination controls markup, add page parameter handling |
| `css/styles.css` | Possibly minor — Bootstrap pagination is mostly self-styled |

### Dependencies

- Step 5 (filter integration) must be complete — pagination must preserve filter params.
- Step 3 (sort) should be complete — pagination must preserve sort params.

### PHP Changes

**Pagination parameters:**
```php
$perPage = 9; // 3 columns × 3 rows, or 12 for 3×4
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;
```

**Two-query approach:**
1. Count query: `SELECT COUNT(*) FROM vehicles WHERE ...` (same WHERE clause as the filtered query, without `ORDER BY` or `LIMIT`)
2. Data query: `SELECT * FROM vehicles WHERE ... ORDER BY ... LIMIT ? OFFSET ?`

```php
// Count total matching vehicles
$countSql = "SELECT COUNT(*) FROM vehicles WHERE " . implode(' AND ', $where);
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalVehicles = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalVehicles / $perPage));

// Ensure current page is within bounds
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $perPage;

// Fetch paginated results
$dataSql = "SELECT * FROM vehicles WHERE " . implode(' AND ', $where) . " ORDER BY $orderBy LIMIT $perPage OFFSET $offset";
$dataStmt = $pdo->prepare($dataSql);
$dataStmt->execute($params);
$vehicles = $dataStmt->fetchAll();
```

**Optimize N+1 availability query:**
Replace the per-vehicle availability count loop with a single query using a LEFT JOIN or subquery:
```php
$dataSql = "
  SELECT v.*, 
    COALESCE(bc.booked_count, 0) AS booked_count
  FROM vehicles v
  LEFT JOIN (
    SELECT vehicle_id, COUNT(*) AS booked_count
    FROM bookings
    WHERE status IN ('pending','confirmed','completed')
    GROUP BY vehicle_id
  ) bc ON v.id = bc.vehicle_id
  WHERE " . implode(' AND ', $where) . "
  ORDER BY $orderBy
  LIMIT $perPage OFFSET $offset
";
```
This eliminates the N+1 pattern entirely — one query instead of N+1 queries.

**Build pagination URL helper:**
```php
function paginationUrl($page, $params) {
    $params['page'] = $page;
    return 'vehicles.php?' . http_build_query($params);
}
// $params includes sort, category[], fuel[], etc.
```

**Pagination controls markup:**
Standard Bootstrap pagination component:
- Previous button (disabled on page 1)
- Page number links (with ellipsis for large page counts)
- Next button (disabled on last page)
- Current page marked with `active` class and `aria-current="page"`
- All pagination links include the current filter + sort params

### Design Requirements

- Centered pagination below the grid
- Bootstrap `.pagination` component with `.page-item` / `.page-link`
- Show max ~5 page numbers with ellipsis for larger sets
- "Showing X–Y of Z vehicles" text above or near pagination
- Pagination at bottom of grid, above footer

### Bootstrap Requirements

- `pagination justify-content-center`
- `page-item`, `page-link`
- `page-item active` for current page
- `page-item disabled` for prev/next when at bounds
- `aria-label="Pagination"` on `<nav>`
- `aria-current="page"` on active page

### Testing Requirements

- [ ] Default page shows first 9 (or 12) vehicles
- [ ] Clicking page 2 shows the next batch
- [ ] Previous button disabled on page 1
- [ ] Next button disabled on last page
- [ ] Filter params preserved when navigating pages
- [ ] Sort params preserved when navigating pages
- [ ] Changing sort resets to page 1
- [ ] Applying filters resets to page 1
- [ ] "Showing X–Y of Z vehicles" count is accurate
- [ ] Page numbers with ellipsis render correctly for large result sets
- [ ] Invalid `?page=0` or `?page=999` handled gracefully (clamped to valid range)
- [ ] N+1 query eliminated — single query with JOIN
- [ ] Pagination is keyboard-accessible
- [ ] Responsive at all breakpoints
- [ ] No console errors

### Acceptance Criteria

- [ ] Server-side pagination with configurable items per page
- [ ] Bootstrap pagination controls below grid
- [ ] Filter + sort state preserved across pages
- [ ] N+1 availability query eliminated
- [ ] Result count shows "Showing X–Y of Z vehicles"
- [ ] Pagination is accessible (ARIA attributes)
- [ ] No performance regression

---

## Step 7: Final Polish & JS Reconciliation

### Objective

Consolidate duplicate JavaScript handlers, remove unused script loads, ensure consistent behavior between the new filter system and all remaining JS, and perform a final visual/functional review.

### Files Affected

| File | Action |
|---|---|
| `vehicles.php` | Modify — remove old category filter inline script, clean up JS |
| `js/app.js` | Modify — remove or guard the duplicate `.category-btn` handler (lines 371–397) so it doesn't conflict |

### Changes

1. **Remove inline category filter script** — the inline `$(function() { ... })` block (vehicles.php lines 555–573) that handles `.category-btn` clicks is no longer needed. Category filtering is now server-side via the sidebar. Remove this entire block.

2. **Guard `app.js` category handler** — `app.js` lines 371–397 contain a `.category-btn` handler with `#noResultsMessage` toggle. Since `#categoryBar` no longer exists on the vehicles page, this handler has no target elements and is harmless. However, confirm it doesn't interfere with any other page. If `.category-btn` elements exist on no other page, the handler is dead code — but removing it from `app.js` is out of scope for this phase (it may affect other pages or future work). Leave it in place with a code comment.

3. **Update homepage `?category=` pre-select** — the homepage search widget sends `?category=suv`. After Step 5, the filter system expects `?category[]=suv`. Add a compatibility shim in the PHP filter-reading code:
   ```php
   // Support both ?category=suv (from homepage) and ?category[]=suv (from filter form)
   if (isset($_GET['category']) && is_string($_GET['category'])) {
       $filterCategories = [strtolower(trim($_GET['category']))];
   }
   ```
   This ensures backward compatibility without modifying the homepage.

4. **Remove `js/printer.js` load** — `printer.js` builds a `#receiptModal` that is not used by the current booking flow (which redirects to `receipt.php` instead). Confirm by grep that nothing in the inline script or other loaded JS calls `showReceiptModal()`, then remove the `<script src="js/printer.js">` tag from vehicles.php.

5. **Final visual consistency pass:**
   - Verify all card borders, shadows, and hover effects are consistent
   - Verify filter panel typography matches Design System
   - Verify pagination styling is clean
   - Verify mobile offcanvas animation is smooth
   - Verify "No results" state looks appropriate with sidebar visible

### Testing Requirements (Full Regression)

- [ ] **Filter:** all 5 filter groups work individually and combined
- [ ] **Sort:** all 5 sort options work and persist across filter/page changes
- [ ] **Pagination:** navigates correctly, preserves filter + sort state
- [ ] **Homepage integration:** `?category=suv` from homepage search widget works
- [ ] **Booking flow:** complete end-to-end — Reserve → Preview → Amount → Confirm → Receipt → Redirect
- [ ] **Auth:** login/logout via navbar works, auth-gated booking check works
- [ ] **Responsive:** 320px, 375px, 576px, 768px, 992px, 1200px, 1400px+
- [ ] **Mobile filter:** offcanvas opens/closes, filter state preserved
- [ ] **Accessibility:** keyboard navigation, ARIA labels, heading hierarchy, `alt` text on images
- [ ] **Console:** no new JS errors (pre-existing `carType is not defined` is acceptable per BUGS.md)
- [ ] **PHP:** no errors or warnings
- [ ] **Other pages:** `index.php` (homepage), `about.php`, `faq.php`, `transactions.php`, `receipt.php` unaffected
- [ ] **Print:** `d-print-none` on navbar/footer still works

### Acceptance Criteria

- [ ] All duplicate JS handlers removed or reconciled
- [ ] No unused script loads remain on vehicles.php
- [ ] Homepage category pre-select backward-compatible
- [ ] Full booking flow preserved
- [ ] Clean console (no new errors)

---

## Post-Implementation Checklist

After all vehicle listing steps are complete:

- [ ] Full page visual review at 320px, 375px, 576px, 768px, 992px, 1200px, 1400px+
- [ ] Check all links (breadcrumb, pagination, filter, sort, Reserve, booking modal)
- [ ] Check console for errors (pre-existing errors acceptable if documented in BUGS.md)
- [ ] Verify shared partials (navbar, footer, auth modals) are unaffected
- [ ] Verify no regressions on other pages that share `css/styles.css`
- [ ] Verify homepage featured vehicles section unaffected
- [ ] Verify homepage search widget → vehicles.php handoff works
- [ ] Test booking flow end-to-end on the paginated/filtered page
- [ ] Check print view (navbar/footer hidden via `d-print-none`)
- [ ] Performance: verify page loads in reasonable time with pagination (fewer queries)
- [ ] Update CHANGELOG.md with detailed entry for each completed step
- [ ] Update FEATURES.md if applicable
- [ ] Update BUGS.md if any pre-existing bugs were fixed or new ones discovered

---

## Final Vehicle Listing Structure (Post-Implementation)

```
┌──────────────────────────────────────────────────┐
│  Navbar (shared partial)                         │
├──────────────────────────────────────────────────┤
│  Page Header                                     │
│  - Breadcrumb: Home > Vehicles                   │
│  - Eyebrow: "VEHICLES"                           │
│  - Heading: "Browse Our Fleet"                   │
├──────────────────────────────────────────────────┤
│  ┌───────────┐  ┌─────────────────────────────┐  │
│  │  Sidebar  │  │  Results Header             │  │
│  │  Filter   │  │  "X vehicles available"     │  │
│  │  Panel    │  │  Sort: [dropdown]           │  │
│  │           │  ├─────────────────────────────┤  │
│  │  Category │  │  Vehicle Grid (3-col)       │  │
│  │  Price    │  │  ┌────┐ ┌────┐ ┌────┐      │  │
│  │  Seats    │  │  │    │ │    │ │    │      │  │
│  │  Fuel     │  │  └────┘ └────┘ └────┘      │  │
│  │  Trans.   │  │  ┌────┐ ┌────┐ ┌────┐      │  │
│  │           │  │  │    │ │    │ │    │      │  │
│  │  [Apply]  │  │  └────┘ └────┘ └────┘      │  │
│  │  Clear    │  │  ┌────┐ ┌────┐ ┌────┐      │  │
│  │           │  │  │    │ │    │ │    │      │  │
│  └───────────┘  │  └────┘ └────┘ └────┘      │  │
│                 ├─────────────────────────────┤  │
│                 │  Showing 1–9 of 31          │  │
│                 │  « 1 [2] 3 4 »              │  │
│                 └─────────────────────────────┘  │
├──────────────────────────────────────────────────┤
│  Footer (shared partial)                         │
├──────────────────────────────────────────────────┤
│  Auth Modals (shared partial)                    │
│  Booking Modal (preserved as-is)                 │
└──────────────────────────────────────────────────┘
```

---

## Estimated Lines of Change

| Step | Lines Added | Lines Removed | Net |
|---|---|---|---|
| Step 1: Cleanup | ~5 | ~250 | −245 |
| Step 2: Page Header | ~15 | ~5 | +10 |
| Step 3: Results Header | ~30 | ~5 | +25 |
| Step 4: Sidebar Filter | ~120 | ~10 | +110 |
| Step 5: Filter Integration | ~60 | ~30 | +30 |
| Step 6: Pagination | ~80 | ~15 | +65 |
| Step 7: Polish | ~10 | ~30 | −20 |
| **Total** | **~320** | **~345** | **−25** |

The net result is roughly the same file size (or slightly smaller), with significantly more functionality. The large removal in Step 1 (~230 lines of dead code) offsets the additions.

---

*This document is a plan only. No code has been modified. Each step should be implemented sequentially, tested, and approved before proceeding to the next. Implementation should follow the workflow defined in CLAUDE.md and UI_IMPLEMENTATION_PLAN.md.*
