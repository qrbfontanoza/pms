# Vehicle Details Modal & Reservation Flow — Analysis

Analysis document for the Vehicle Details phase. Based on direct inspection of all source files, all project documentation, and the completed Shared Components, Homepage, Vehicle Listing, and About Us phases.

**Status:** Analysis only. No code has been modified. Implementation requires separate approval.

---

## 1. Current Vehicle Listing Implementation

### File Under Review

[vehicles.php](../vehicles.php) — 914 lines. The largest client-facing PHP file. Contains:
- Shared navbar (`includes/client_navbar.php`)
- Shared footer (`includes/client_footer.php`)
- Shared auth modals (`includes/auth_modals.php`)
- Booking modal (`#bookingModal`, lines 245–379)
- Sidebar filter panel (`#filterSidebar`, desktop `col-lg-3`, offcanvas below 992px)
- Server-side filtered/sorted/paginated vehicle grid (`#carsGrid`, lines 506–558)
- Inline JavaScript for booking flow (lines 636–911)

### Current Vehicle Card Structure (lines 525–556)

Each vehicle card renders inside a `col-md-4 car-card` wrapper:

```
.vehicle-card.bg-white.rounded-3.shadow-sm.overflow-hidden
├── .vehicle-img-wrap > img (thumbnail)
└── .p-3
    ├── h5 (title)
    ├── small.text-muted (category)
    ├── .vehicle-specs (3-icon row: seats, fuel, transmission)
    ├── .vehicle-pricebar (₱X,XXX / day)
    ├── .badge (Available/Unavailable)
    └── button.btn-reserve (Reserve Now / Unavailable)
```

**Data attributes on the Reserve button:**
- `data-id` — vehicle database ID
- `data-car` — vehicle title (htmlspecialchars-escaped)
- `data-rate` — price_per_day (numeric)
- `disabled` attribute when unavailable

**CSS classes involved:**
- `.vehicle-card` — `css/styles.css` hover lift, border, cursor
- `.vehicle-img-wrap` — fixed-height container with overflow hidden, `::after` gradient overlay
- `.vehicle-img` — `object-fit: cover`, hover zoom via `transform: scale(1.05)`
- `.vehicle-specs` — 3-column grid for spec icons
- `.vehicle-pricebar` — price display with `color: var(--secondary)`

### Current Primary CTA

The vehicle card's primary action is **"Reserve Now"** (`btn-primary rounded-pill w-100 btn-reserve`). There is no "View Car Details" action. Clicking "Reserve Now" immediately triggers an authentication check, then opens the booking form modal.

---

## 2. Current Vehicle Interaction Flow

### What happens on each user action:

**User clicks vehicle image:** Nothing. No click handler is bound to the vehicle image or the card wrapper. The image has no `<a>` link.

**User clicks vehicle card body:** Nothing. No click handler is bound to the `.vehicle-card` div.

**User clicks "Reserve Now" button:** Two competing click handlers fire simultaneously:

#### Handler A — Inline script (`vehicles.php:678-707`)
1. Calls `checkLoginStatus()` → fetches `me.php` → sets local `userLoggedIn`
2. If NOT logged in: shows `alert("Please log in first to book a vehicle.")` and returns
3. If logged in: sets `#vehicle_id` hidden input, updates `#bookingModalLabel` to `"Book: {carName}"`, resets form, opens `#bookingModal`

#### Handler B — `js/app.js:988-1026`
1. Calls `$.getJSON('me.php')`
2. If NOT logged in: opens `#loginModal`
3. If logged in: attempts to open `#bookingMultiModal` (which **does not exist** in the DOM — dead code)
4. Bug: `$(this)` inside the `.done()` callback refers to jqXHR, not the button — `data-car`/`data-rate`/`data-id` reads return `undefined`

**Net effect for unauthenticated users:** User sees a browser `alert()` (Handler A) AND the login modal opens (Handler B). Both fire because both are delegated `$(document).on('click', '.btn-reserve', ...)` handlers.

**Net effect for authenticated users:** Handler A opens `#bookingModal` correctly. Handler B tries to open `#bookingMultiModal` (non-existent), which silently fails. The booking modal opens and works.

### Flow diagram (current):

```
Vehicle Card
    ↓
"Reserve Now" clicked
    ↓
Two handlers fire simultaneously
    ↓
┌─────────────────────┴─────────────────────┐
│ Handler A (inline)                        │ Handler B (app.js)
│ fetch me.php                              │ $.getJSON me.php
│                                           │
├── Not logged in:                          ├── Not logged in:
│   alert("Please log in...")               │   open #loginModal
│   return (no modal)                       │
├── Logged in:                              ├── Logged in:
│   set vehicle_id, open #bookingModal      │   try #bookingMultiModal (dead)
└───────────────────────────────────────────┘
```

---

## 3. Current Authentication Flow

### How logged-in users are detected

**Server-side:** `$_SESSION['user']` is set by `login.php` on successful login. Contains `id`, `name`, `email`.

**Client-side (page load):** `vehicles.php:623` sets `window.userLoggedIn = true/false` from `$_SESSION`, but this global is **not used** by either `.btn-reserve` handler. The inline script declares its own local `userLoggedIn` variable (line 638).

**Client-side (on click):** Both handlers independently call `me.php` to check current session state.

### How guest users are detected

Both handlers call `me.php`. If `$_SESSION['user']` is not set, `me.php` returns `{"logged_in": false}`.

### Where login is triggered

- Handler B (`app.js:1018`) opens `#loginModal` programmatically via `$('#loginModal').modal('show')`
- Handler A (`vehicles.php:687`) shows a plain `alert()` — does **not** open the login modal

### Where registration is triggered

- The login modal (`includes/auth_modals.php:25`) has a "No account? Sign up" link that dismisses `#loginModal` and opens `#signupModal` via Bootstrap's `data-bs-dismiss` + `data-bs-toggle` chain

### Login form handler (`app.js:1141-1162`)

1. Prevents default form submit
2. POSTs `{email, password}` to `login.php`
3. On success: `alert('Login successful!')`, hides `#loginModal`, calls `window.location.reload()`
4. On failure: shows error in `#loginError`

### Registration form handler (`app.js:1108-1137`)

1. POSTs `{name, email, password}` to `register.php`
2. On success: `alert('Registration successful! Please log in.')`, hides `#signupModal`, opens `#loginModal`
3. Does NOT auto-login after registration

### Critical: No "return to previous action" logic

After successful login, `window.location.reload()` fires. The page reloads completely. There is **no mechanism** to:
- Remember which vehicle the user was trying to reserve
- Automatically reopen the booking modal
- Resume the interrupted booking flow

The user must find the vehicle again and click "Reserve Now" a second time.

---

## 4. Current Reservation Flow

### Booking modal content (`#bookingModal`, vehicles.php:245-379)

The modal contains:

| Field | ID | Type | Required |
|---|---|---|---|
| Vehicle ID (hidden) | `vehicle_id` | hidden input | — |
| Rental Date | `rental_date` | date | yes |
| Return Date | `return_date` | date | yes |
| Pick-up Time | `pickup_time` | time | yes |
| Drop-off Time | `dropoff_time` | time | yes |
| Contact Number | `contact_number` | tel | yes |
| Age | `age` | number (min 18) | yes |
| Driver's License | `license_file` | file (jpeg/png) | yes |
| Voucher | `voucherSelect` | select | no |

**Vehicle info displayed in modal:** Only the modal title — `"Book: {carName}"`. No vehicle image, no price, no specs, no availability info.

### Booking flow steps:

1. **Form fill** → user enters dates, times, contact, age, license, optional voucher
2. **Preview** (`#btnPreview`) → POSTs to `reserve_preview.php` → returns days, rate, subtotal, discount, total → rendered in `#previewContent`
3. **Amount input** → `#amountPaidSection` revealed, user enters payment amount
4. **Confirm** (`#btnConfirm` / form submit) → POSTs to `reserve.php` → returns booking_ref, receipt data → rendered in `#receiptContent`
5. **Redirect** → `window.location.href = 'receipt.php?id=...'`

### Vehicle ID preservation

The vehicle ID is stored in the hidden `#vehicle_id` input field, set by the inline `.btn-reserve` handler at line 693. This value persists inside the modal's form until the modal is dismissed or the form is reset.

---

## 5. Vehicle Data Available

### Already Available (from the `vehicles` table)

| Column | Currently Displayed on Card | Notes |
|---|---|---|
| `id` | No (data attribute only) | Used as `data-id` on Reserve button |
| `title` | Yes | Card heading, modal title, image alt text |
| `category` | Yes | Subtitle below title (e.g., "Sedan", "SUV") |
| `seats` | Yes | Spec row: "{N} Seats" with `fa-users` icon |
| `fuel` | Yes | Spec row: "Gasoline"/"Diesel" with `fa-gas-pump` icon |
| `transmission` | Yes | Spec row: "Automatic"/"Manual" with `fa-briefcase` icon |
| `price_per_day` | Yes | Price bar: "₱X,XXX / day" |
| `thumbnail` | Yes | Card image |
| `units_total` | No (computation only) | Used to compute availability |
| `is_active` | No (filter only) | `WHERE is_active = 1` |
| `created_at` | No | Used only for "Newest First" sort |
| `slug` | No | Completely unused — no PHP code reads or writes it |

**Computed from JOIN:**
| Value | Currently Displayed | Notes |
|---|---|---|
| `booked_count` | No (computation only) | `COALESCE(bc.booked_count, 0)` from LEFT JOIN |
| Availability | Yes (badge) | `units_total - booked_count > 0` → "Available"/"Unavailable" |

### Available but Not Currently Displayed

| Data | Status | Notes |
|---|---|---|
| Available units count | Computable | `units_total - booked_count` — could show "3 units available" |
| `created_at` | In DB | Could show "Listed since {date}" but low value |

### Missing (Not in the database)

| Data | Status | Notes |
|---|---|---|
| `details` / `description` | **Does not exist** | Admin form has a `<textarea name="details">` (`admin_vehicles.php:176`) but the INSERT query in `admin_add_vehicle.php` **silently drops this field** — it is never saved |
| Air conditioning | Does not exist | No amenity fields exist |
| Doors count | Does not exist | |
| Engine specs | Does not exist | |
| Color | Does not exist | |
| Year/model | Does not exist | |
| Additional photos | Does not exist | Only one `thumbnail` column |
| Rating/reviews | Does not exist | |

### Important: Do NOT invent data

The Vehicle Details modal must work with what actually exists. The available information for the modal is:
- Vehicle image (thumbnail)
- Vehicle name (title)
- Category
- Rental price per day
- Seats
- Fuel type
- Transmission
- Availability status (computed)
- Available units count (computable)

### Flagged Future Task: Vehicle Description Field

**Decision (2026-08-10): out of scope for this phase, deliberately deferred.**

A description/`details` field is a natural addition to the Vehicle Details modal, but it requires backend changes beyond this phase's front-end-only scope (per `CLAUDE.md`: "Backend modifications should only be suggested unless explicitly requested"). If pursued later, it requires:

1. **Database migration** — `vehicles` has no `details`/`description` column today. Add one (e.g. `ALTER TABLE vehicles ADD COLUMN details TEXT DEFAULT NULL`) via a new file in `db_migrations/` (matching the project's existing migration-tracking convention — see `docs/DATABASE.md`), not just a live `phpMyAdmin` edit, so the schema-drift documentation stays accurate.
2. **`admin_add_vehicle.php`** — the "Add Vehicle" modal (`admin_vehicles.php:176`) already has a `<textarea name="details">`, but the INSERT statement silently drops it. Needs the column added to the INSERT.
3. **`admin_edit_vehicle.php` / `admin_vehicles.php`** — the Edit Vehicle modal has no `details` textarea at all. Needs the field added to both the modal markup, the JS populate handler (`admin_vehicles.php` `.editVehicleBtn` click handler), and the UPDATE query.
4. **Data population** — all existing vehicles would have `NULL`/empty descriptions until populated via the admin panel.
5. **Vehicle Details modal** — display the description only when non-empty; omit the section entirely when `NULL`, rather than showing a placeholder like "No description available."

This is being tracked here as a follow-up task, not implemented in this phase.

---

## 6. Existing Vehicle Modal / Booking Modal Analysis

### Current modals on `vehicles.php`

| Modal | ID | Purpose | Status |
|---|---|---|---|
| Booking Modal | `#bookingModal` | Booking form + preview + receipt | Active, functional |
| Login Modal | `#loginModal` | User authentication | Active (from `includes/auth_modals.php`) |
| Signup Modal | `#signupModal` | User registration | Active (from `includes/auth_modals.php`) |

### No Vehicle Details modal exists

There is currently **no modal for viewing vehicle details**. The only modal that displays vehicle information is `#bookingModal`, which shows only the vehicle name in its title.

### Bootstrap modal stacking consideration

Bootstrap 5 does **not** natively support stacked modals (opening a second modal while one is already open). The approved flow requires:

1. Vehicle Details modal opens (user views vehicle)
2. User clicks "Reserve Now" inside the details modal
3. If not authenticated: Login/Signup modal opens

This means the Vehicle Details modal should **close before** the Login or Booking modal opens to avoid Bootstrap stacking issues. Bootstrap's `data-bs-dismiss` + `data-bs-toggle` chain (already used by the auth modals' "No account? Sign up" link) handles this pattern correctly.

---

## 7. Problems With Current Flow

### User Experience Problems

1. **No way to view vehicle details before committing to reserve.** The only action available on a vehicle card is "Reserve Now" — users cannot learn more about a vehicle without starting the booking process.

2. **Authentication required too early.** Users must be logged in just to see the booking form. They should be able to browse vehicle details freely and only need to authenticate when they actually want to reserve.

3. **Inconsistent auth-failure behavior.** Two handlers fire simultaneously — one shows an `alert()`, the other opens the login modal. Users see both.

4. **No post-login context preservation.** After logging in via the modal, the page reloads and the user loses all context — which vehicle they were viewing, their scroll position, etc.

5. **Booking modal shows minimal vehicle info.** Only the vehicle name appears in the modal title. No image, no price, no specs — the user has to remember these from the card.

### Technical Problems

1. **Duplicate `.btn-reserve` handlers** (`vehicles.php:678` and `app.js:988`) both fire on every click.
2. **Dead code in `app.js:988-1026`** targets `#bookingMultiModal` which does not exist.
3. **Broken `$(this)` in `app.js:999`** — inside the `.done()` callback, `$(this)` is the jqXHR object, not the button.

---

## 8. Approved New User Flow

```
Vehicle Listing
        ↓
User clicks vehicle card or "View Car Details"
        ↓
Vehicle Details Modal opens (NO auth required)
        ↓
User reviews: image, name, category, price, specs, availability
        ↓
"Reserve Now" button
        ↓
Authentication Check (via me.php)
        ↓
 ┌───────────────────────┴───────────────────────┐
 │                                               │
User authenticated                         User not authenticated
 │                                               │
 ↓                                               ↓
Vehicle Details modal closes              Vehicle Details modal closes
Booking modal opens                       Login modal opens
(#bookingModal with vehicle data)         (#loginModal)
                                                 │
                                          User logs in or registers
                                                 │
                                                 ↓
                                          Page reloads (existing behavior)
                                          User clicks vehicle again
                                          Vehicle Details modal opens
                                          "Reserve Now" → Booking modal
```

### Key behavioral changes:

1. **Vehicle card CTA changes** from "Reserve Now" to "View Car Details"
2. **New Vehicle Details modal** displays all available vehicle information
3. **"Reserve Now" moves** from the vehicle card into the Vehicle Details modal
4. **Authentication check moves** from the card click to the modal's "Reserve Now" click
5. **No registration required** to view vehicle details — only to reserve

---

## 9. Vehicle Details Modal Structure

Based on actual available vehicle data (no invented fields):

```
Vehicle Details Modal (#vehicleDetailsModal)
├── Modal Header
│   ├── Vehicle Name (title)
│   └── Close button
├── Modal Body
│   ├── Vehicle Image (thumbnail, larger than card)
│   ├── Category Badge (e.g., "SUV")
│   ├── Price Display (₱X,XXX / day — prominent)
│   ├── Availability Badge (Available/Unavailable + unit count if available)
│   ├── Key Specifications
│   │   ├── Seats (fa-users icon)
│   │   ├── Fuel Type (fa-gas-pump icon)
│   │   └── Transmission (fa-briefcase or fa-cog icon)
│   └── Rental Information (static text — policy reminders)
│       ├── Minimum age: 18 years old
│       ├── Valid driver's license required
│       └── Pick-up/drop-off times apply
└── Modal Footer
    ├── Close button (secondary)
    └── Reserve Now button (primary, disabled if unavailable)
```

### Design decisions:

- **No description section** — the `details` column does not exist in the database. Adding a placeholder "No description available" would look worse than omitting it. If/when the column is added and populated, the modal can be extended.
- **No air conditioning / amenity section** — no data exists.
- **Rental information section** — static, informational text derived from the booking form's own requirements (age ≥ 18, license required). Helps the user understand requirements before starting the reservation.
- **Availability shows unit count** when available — e.g., "Available (3 units)" vs just "Available" — gives users more useful information.
- **Modal size** — `modal-lg` to give the vehicle image adequate display space, matching `#bookingModal`'s size.

---

## 10. Component Reuse

| Component | Source | Reuse Strategy |
|---|---|---|
| Vehicle card CSS | `.vehicle-card`, `.vehicle-img-wrap`, `.vehicle-specs`, `.vehicle-pricebar` | Reuse spec/price styling patterns inside the modal |
| Auth modals | `includes/auth_modals.php` (`#loginModal`, `#signupModal`) | Already included — no changes needed |
| Booking modal | `#bookingModal` (vehicles.php:245-379) | Preserve as-is; only change what triggers it |
| Modal patterns | Bootstrap 5 `modal`, `modal-lg`, `modal-dialog-centered` | Standard Bootstrap, no custom component needed |
| Badge styles | `bg-success`/`bg-danger` availability badges | Reuse same badge pattern in details modal |
| Section eyebrow | `.section-eyebrow` (css/styles.css) | Could use for spec section labels, but may be overdesign for a modal |
| Design system colors | `--primary`, `--secondary`, `--accent` | Price display, spec icons |
| Auth check pattern | `me.php` fetch + response handling | Reuse exact pattern from existing inline handler |

### New components required:

1. **Vehicle Details modal markup** — new `#vehicleDetailsModal` in `vehicles.php`
2. **Vehicle Details modal CSS** — minimal: image display, spec layout within modal context
3. **JavaScript to populate and open the details modal** — reads `data-*` attributes from clicked card, populates modal fields, opens it
4. **Reserve Now handler inside details modal** — performs auth check, closes details modal, opens appropriate next modal

---

## 11. Files Involved

| File | Current Role | Expected Changes |
|---|---|---|
| `vehicles.php` | Vehicle listing page | Add Vehicle Details modal markup; change card CTA from "Reserve Now" to "View Car Details"; add new click handler; modify reserve handler |
| `css/styles.css` | Global styles | Add Vehicle Details modal styles (image display, spec layout) |
| `js/app.js` | Shared JavaScript | The dead `.btn-reserve` handler (lines 988-1026) should ideally be removed or disabled, but this file is shared — changes must be minimal and safe |
| `includes/auth_modals.php` | Login/Signup modals | No changes expected |
| `includes/client_navbar.php` | Shared navbar | No changes |
| `includes/client_footer.php` | Shared footer | No changes |
| `me.php` | Auth state API | No changes |
| `login.php` | Login endpoint | No changes |
| `register.php` | Registration endpoint | No changes |
| `reserve_preview.php` | Booking preview API | No changes |
| `reserve.php` | Booking confirmation API | No changes |
| `index.php` | Homepage | The featured vehicle cards use `<a href="vehicles.php">Reserve Now</a>` — these are simple links to the listing page, not `.btn-reserve` buttons, so they are **unaffected** by this change |

---

## 12. Conditional Logic Changes Required

### CURRENT flow (vehicles.php inline script, line 678):

```
User clicks .btn-reserve
    → await checkLoginStatus()
    → if (!userLoggedIn) → alert("Please log in") → return
    → if (userLoggedIn) → set #vehicle_id, set modal title → open #bookingModal
```

### PROPOSED flow:

```
User clicks .btn-view-details (NEW class on vehicle card button)
    → read data-id, data-car, data-rate, data-cat, data-seats,
      data-fuel, data-transmission, data-thumbnail, data-available
      from the clicked card/button
    → populate #vehicleDetailsModal with vehicle info
    → open #vehicleDetailsModal
    → NO auth check at this point

User clicks #btnReserveFromDetails (inside Vehicle Details modal)
    → await checkLoginStatus()
    → if (!userLoggedIn):
        → close #vehicleDetailsModal
        → open #loginModal
        → (after login, page reloads — user clicks vehicle again)
    → if (userLoggedIn):
        → close #vehicleDetailsModal
        → set #vehicle_id, set #bookingModalLabel
        → open #bookingModal
```

### Data attribute additions to vehicle card:

The current button has `data-id`, `data-car`, `data-rate`. The Vehicle Details modal needs additional vehicle info. Two approaches:

**Option A: Add data attributes to the button/card**
Add `data-cat`, `data-seats`, `data-fuel`, `data-transmission`, `data-thumbnail`, `data-available` to the card wrapper or the button. Simple, no extra queries.

**Option B: Add data attributes to a hidden element per card**
Cleaner separation but more markup.

**Recommended: Option A** — add all needed data attributes directly to the "View Car Details" button. This is the same pattern the existing "Reserve Now" button already uses (`data-id`, `data-car`, `data-rate`), just extended with additional fields.

---

## 13. JavaScript Changes Required

### New code needed:

1. **`.btn-view-details` click handler** — reads data attributes, populates `#vehicleDetailsModal`, opens it
2. **`#btnReserveFromDetails` click handler** — performs auth check, closes details modal, opens booking modal or login modal
3. **Data-flow bridge** — when "Reserve Now" is clicked inside the details modal, the vehicle ID and name must be passed to `#bookingModal` (same pattern as the current inline handler at line 693-694)

### Existing code to modify:

1. **Inline `.btn-reserve` handler (vehicles.php:678-707)** — rename to `.btn-view-details` handler OR repurpose: the current handler checks auth and opens booking modal; the new handler should skip auth and open the details modal instead
2. **The `app.js` `.btn-reserve` handler (lines 988-1026)** — this targets `.btn-reserve` which will no longer exist on vehicle cards. If the class is changed to `.btn-view-details`, this handler will stop firing automatically (it binds to `.btn-reserve`). No modification to `app.js` required if we simply change the button class.

### Key insight: Changing the button class from `.btn-reserve` to `.btn-view-details` automatically disables the competing `app.js` handler

The `app.js` handler at line 988 binds to `.btn-reserve`. If the vehicle card buttons are changed to use `.btn-view-details` instead, the `app.js` handler will never fire on these buttons. This eliminates the duplicate-handler problem **without modifying `app.js`** — the safest possible approach since `app.js` is a shared file.

The new "Reserve Now" button inside `#vehicleDetailsModal` can use a distinct ID (`#btnReserveFromDetails`) or class (`.btn-reserve-from-details`), also avoiding collision with the `app.js` handler.

---

## 14. PHP/Database Changes Required

### PHP changes:

**Additional data attributes in the card render loop** — the `echo` block (vehicles.php:525-556) needs to output additional `data-*` attributes on the button or card wrapper:
- `data-cat` (already exists on the card wrapper)
- `data-seats`
- `data-fuel`
- `data-transmission`
- `data-thumbnail`
- `data-available` (available unit count)
- `data-available-text` (availability status text)

These are all already available in the `$car` array from the existing query — no new database query is needed.

### Database changes:

**None.** The Vehicle Details modal works with existing columns. The `details`/`description` column does not exist and should not be added as part of this phase — it would require:
1. Schema migration
2. Admin form fix (the INSERT query currently drops the `details` field)
3. Data population for all 31+ vehicles

This is a separate, future task.

---

## 15. Responsive Requirements

### Desktop (≥992px)
- Vehicle Details modal: `modal-lg` centered, image takes ~40-50% width, specs beside it
- Adequate spacing for all vehicle information

### Tablet (768–991px)
- Modal: full-width dialog
- Image stacks above specs (single column layout inside modal)

### Mobile (<768px)
- Modal: full-width, near-full-height
- Image at top, specs below, Reserve Now button at bottom
- Touch-friendly button sizes
- Scrollable modal body if content exceeds viewport

### Bootstrap modal behavior
- `modal-dialog-centered` for vertical centering on desktop
- `modal-dialog-scrollable` for scrollable body on small screens
- Standard Bootstrap responsive behavior — no custom breakpoints needed

---

## 16. Accessibility Requirements

- Modal has proper `aria-labelledby` linking to the title
- Close button has `aria-label="Close"`
- Vehicle image has descriptive `alt` text (vehicle title)
- Availability badge conveys status via text, not color only (existing pattern)
- "Reserve Now" button is disabled (with `disabled` attribute) when vehicle is unavailable
- Focus management: Bootstrap handles focus trapping inside modals automatically
- Keyboard: ESC closes modal, Tab cycles through interactive elements
- Spec icons have `aria-hidden="true"` (text labels carry the meaning)

---

## 17. Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Breaking the existing booking flow | **High** | The booking modal (`#bookingModal`) and all its handlers (preview, confirm, receipt, redirect) are preserved exactly as-is. Only the trigger mechanism changes — what opens it. |
| Bootstrap modal stacking conflicts | **Medium** | Never open two modals simultaneously. Close the details modal before opening login/booking modal. Use Bootstrap's built-in `hidden.bs.modal` event to sequence transitions. |
| `app.js` `.btn-reserve` handler conflict | **Medium** | Eliminated by changing the card button class from `.btn-reserve` to `.btn-view-details`. The `app.js` handler binds to `.btn-reserve` and will simply never match. No `app.js` edit needed. |
| Vehicle data lost during auth flow | **Medium** | After login, `window.location.reload()` fires. The user must re-click the vehicle. This is the **existing** behavior and is preserved — the current system already loses vehicle context on login. Improving this (e.g., storing selected vehicle in `sessionStorage`) is possible but would add complexity beyond the approved scope. |
| Duplicate event handlers | **Medium** | Use specific IDs (`#btnReserveFromDetails`) rather than classes for the new Reserve button, avoiding accidental binding collisions. |
| Mobile modal usability | **Low** | Use `modal-dialog-scrollable` so long content scrolls inside the modal rather than requiring the user to scroll the page behind it. Test at 320px and 375px. |
| Homepage featured vehicle cards | **None** | Homepage cards use `<a href="vehicles.php">Reserve Now</a>` — plain anchor tags, not `.btn-reserve` buttons. Completely unaffected by this change. |
| Regression on filter/sort/pagination | **None** | The filter sidebar, sort dropdown, and pagination are all above the card grid in the DOM and use separate elements/handlers. None are affected by changing the card button's class or adding a modal. |

---

## 18. Testing Requirements

### Functional Testing

- [ ] Vehicle card shows "View Car Details" instead of "Reserve Now"
- [ ] Clicking "View Car Details" opens the Vehicle Details modal (no auth required)
- [ ] Vehicle Details modal displays correct: image, title, category, price, seats, fuel, transmission, availability
- [ ] Vehicle Details modal shows correct data for each different vehicle (not stale from a previous click)
- [ ] "Reserve Now" inside the modal opens the booking modal for authenticated users
- [ ] "Reserve Now" inside the modal opens the login modal for unauthenticated users
- [ ] Vehicle Details modal closes before the next modal opens (no stacking)
- [ ] After login + page reload, clicking the same vehicle again works correctly
- [ ] The booking modal receives the correct vehicle ID and name
- [ ] Full booking flow works end-to-end: View Details → Reserve Now → Booking Form → Preview → Confirm → Receipt → Redirect
- [ ] Unavailable vehicles show disabled "Reserve Now" in the details modal
- [ ] Clicking an unavailable vehicle's "View Car Details" still opens the details modal (read-only viewing allowed)

### Authentication Testing

- [ ] Guest user can view vehicle details without logging in
- [ ] Guest user clicking "Reserve Now" in details modal is prompted to log in
- [ ] After login, page reloads and user can re-access the vehicle
- [ ] Registration flow (Signup → Login → Reserve) works end-to-end
- [ ] Already-logged-in user can go directly from details modal to booking modal

### Responsive Testing

- [ ] Vehicle Details modal at 320px — no horizontal overflow, scrollable, button visible
- [ ] Vehicle Details modal at 375px — same checks
- [ ] Vehicle Details modal at 576px — image and specs layout correctly
- [ ] Vehicle Details modal at 768px — layout transitions as expected
- [ ] Vehicle Details modal at 992px — side-by-side layout if applicable
- [ ] Vehicle Details modal at 1200px+ — centered, well-proportioned

### Regression Testing

- [ ] Sidebar filter panel still works (all 6 filter groups)
- [ ] Sort dropdown still works (all 5 options)
- [ ] Pagination still works (navigation, filter state preservation)
- [ ] Homepage featured vehicle cards still link to vehicles.php correctly
- [ ] Homepage search widget `?category=` handoff still works
- [ ] No new console errors
- [ ] No PHP errors (`php -l`)
- [ ] Other pages unaffected (index.php, about.php, faq.php, transactions.php, receipt.php)

---

## 19. Acceptance Criteria

1. Vehicle cards display "View Car Details" as their primary action
2. A Vehicle Details modal exists and displays all available vehicle information
3. The Vehicle Details modal does NOT require authentication to view
4. "Reserve Now" inside the Vehicle Details modal performs an authentication check
5. Authenticated users proceed to the existing booking modal
6. Unauthenticated users are directed to the login modal
7. The existing booking flow (form → preview → confirm → receipt → redirect) is completely preserved
8. No duplicate click handlers fire on card interaction
9. The modal follows the project Design System (colors, typography, spacing)
10. The modal is responsive across all breakpoints (320px to 1400px+)
11. The modal is accessible (ARIA attributes, keyboard navigation, focus management)
12. No regression in filter, sort, pagination, or any other existing functionality
13. No new console errors, no PHP errors

---

*This document is analysis only. No code has been modified. Implementation should not proceed without review and approval per CLAUDE.md working rules.*
