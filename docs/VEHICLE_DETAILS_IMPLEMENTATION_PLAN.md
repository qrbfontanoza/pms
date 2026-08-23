# Vehicle Details Modal & Reservation Flow — Implementation Plan

Implementation plan for the approved [VEHICLE_DETAILS_ANALYSIS.md](VEHICLE_DETAILS_ANALYSIS.md). Follows the modal + authentication conditional as one connected workflow, per the phase instructions — not treated as an isolated visual component.

**Status:** Plan only. No code has been modified. Each step requires separate approval before implementation begins. Do not proceed to a later step without explicit sign-off on the current one.

**Data attribute approach:** Option A (approved) — additional vehicle data (`data-cat`, `data-seats`, `data-fuel`, `data-transmission`, `data-thumbnail`, `data-available`, `data-available-text`) is added directly to the "View Car Details" button, extending the existing `data-id`/`data-car`/`data-rate` pattern already used by the current Reserve button. No new PHP query required — all values come from the `$car` array already fetched by the existing query.

**Vehicle description:** Out of scope for this implementation (see [VEHICLE_DETAILS_ANALYSIS.md](VEHICLE_DETAILS_ANALYSIS.md) §5, "Flagged Future Task"). No `details`/`description` field will appear in the modal.

---

## Step 1 — Vehicle Details Modal Foundation

### Objective

Introduce the `#vehicleDetailsModal` markup and change the vehicle card's primary action from "Reserve Now" to "View Car Details", wiring the click to open the new modal populated with that vehicle's data. No "Reserve Now" integration yet — the modal's own Reserve button is inert in this step (or absent), and authentication is untouched.

### Files to Inspect

- `vehicles.php` (lines 245-380 for existing modal patterns, 506-558 for the card render loop, 678-707 for the existing `.btn-reserve` handler)
- `css/styles.css` (`.vehicle-card`, `.vehicle-img-wrap`, `.vehicle-specs`, `.vehicle-pricebar` for style patterns to reuse)
- `includes/auth_modals.php` (for existing modal structure conventions — `glassmorph`, header/body layout)

### Files Expected to Change

- `vehicles.php`:
  - Card render loop (lines 525-556): change button text from "Reserve Now" to "View Car Details", change class from `btn-reserve` to `btn-view-details`, add the new `data-*` attributes (Option A)
  - New markup block: `#vehicleDetailsModal`, inserted near `#bookingModal` (e.g., immediately before or after it)
  - New inline `<script>` block (or addition to the existing one): `.btn-view-details` click handler that reads data attributes and populates/opens the modal
- `css/styles.css`: minor additions only if Bootstrap utilities are insufficient (expect none needed — see Bootstrap Requirements below)

### Existing Components to Reuse

- Bootstrap `modal`, `modal-lg`, `modal-dialog-centered` (matches `#bookingModal`'s sizing convention)
- `.badge bg-success` / `.badge bg-danger` availability badge pattern (from the card)
- Spec icon pattern (`fa-users`, `fa-gas-pump`, `fa-briefcase`) from `.vehicle-specs`
- `htmlspecialchars`-escaped PHP output pattern already used throughout the card loop

### New Components Required

- `#vehicleDetailsModal` markup (new)
- `.btn-view-details` class (new, replaces `.btn-reserve` on the card)
- JS function to populate the modal from a clicked button's dataset (new)

### Existing Logic to Preserve

- The existing `.btn-reserve` handler in `vehicles.php` (lines 678-707) and the `app.js` handler (lines 988-1026) are **not modified in this step**. They will simply stop firing once no element on the page carries the `.btn-reserve` class — this is a side effect of the class rename, not a direct edit.
- `#bookingModal` and its entire flow: completely untouched in this step.
- Filter sidebar, sort dropdown, pagination: untouched.

### Conditional Logic Involved

```
User clicks .btn-view-details (on a vehicle card)
    → read data-id, data-car, data-cat, data-rate, data-seats,
      data-fuel, data-transmission, data-thumbnail,
      data-available, data-available-text from the clicked button
    → populate #vehicleDetailsModal fields (image, title, category,
      price, specs, availability badge)
    → open #vehicleDetailsModal (no auth check)
```

No authentication conditional exists yet in this step — that is Step 3/4.

### JavaScript Behavior

- New delegated click handler: `$(document).on('click', '.btn-view-details', function () { ... })`
- Reads `$(this).data(...)` for each attribute
- Populates modal DOM elements by ID (e.g., `$('#detailsModalImage').attr('src', ...)`, `$('#detailsModalTitle').text(...)`)
- Calls `$('#vehicleDetailsModal').modal('show')`
- No `fetch`/AJAX calls in this step — all data comes from attributes already in the DOM

### PHP Behavior

- Card render loop gains additional `htmlspecialchars`-escaped `data-*` output, matching the existing escaping convention exactly (e.g., `data-fuel='" . htmlspecialchars($car['fuel']) . "'"`)
- `data-available` (integer) and `data-available-text` (string) computed from the same `$isAvailable`/`$available` variables already calculated at lines 515-522 — no new computation needed, just additional output
- No new query, no new PHP function

### Database Requirements

None. All fields already exist in the `$car` array from the current query.

### Bootstrap Requirements

- `modal fade`, `modal-dialog modal-lg modal-dialog-centered`, `modal-content`, `modal-header`, `modal-body`, `modal-footer` — standard structure, same as `#bookingModal`
- No custom Bootstrap overrides expected

### Responsive Requirements

- Modal must render correctly at 320px through 1400px+ using Bootstrap's default modal responsiveness (no custom breakpoints in this step — full responsive polish is Step 5)
- Basic check only in this step: no horizontal overflow, image doesn't break layout

### Accessibility Requirements

- `aria-labelledby` on the modal pointing to the title element's ID
- Close button: `aria-label="Close"`
- Vehicle image: `alt="{vehicle title}"` (htmlspecialchars-escaped)
- Full accessibility polish (focus management edge cases, keyboard nav verification) is Step 5 — this step establishes correct baseline markup only

### Testing Requirements

- `php -l vehicles.php` — no syntax errors
- Live browser: click "View Car Details" on multiple different vehicles (including one Available and one Unavailable), confirm modal populates with that vehicle's correct data each time (not stale from a previous click)
- Confirm modal opens without any authentication check or redirect
- Confirm `#bookingModal`'s own trigger is inert (no button on the card opens it directly anymore — this is expected in this step, since Reserve Now integration is Step 3)
- Confirm the two old `.btn-reserve` handlers no longer fire (no `alert()`, no login modal, no console errors) since no element has that class anymore
- Console: no new errors
- Confirm filter sidebar, sort dropdown, and pagination still function correctly (regression check)
- Confirm homepage (`index.php`) featured vehicle cards are unaffected (they use `<a href="vehicles.php">`, not `.btn-reserve`/`.btn-view-details`)

### Acceptance Criteria

- [ ] Vehicle card's primary button reads "View Car Details" and uses class `.btn-view-details`
- [ ] Clicking it opens `#vehicleDetailsModal` populated with that specific vehicle's image, name, category, price, seats, fuel, transmission, and availability
- [ ] No authentication check occurs when opening the details modal
- [ ] The old `.btn-reserve` handlers (inline script and `app.js`) no longer fire on the vehicle card (since the class no longer exists there)
- [ ] `#bookingModal` markup and its JS handlers remain completely unmodified
- [ ] No console errors, no PHP errors
- [ ] No regression in filters, sort, or pagination

### Risks

| Risk | Mitigation |
|---|---|
| Modal shows stale data from a previously-clicked vehicle | Always fully repopulate every field on each click — never assume a field is already correct from a prior open |
| `app.js`'s dead `.btn-reserve` handler was relied upon for something unnoticed | Verified in analysis: it targets `#bookingMultiModal` (nonexistent) and has a confirmed `$(this)` bug — dead code, safe to let it stop firing |
| New data attributes break existing `data-id`/`data-car`/`data-rate` reads elsewhere | No other code reads these attributes from the card button besides the two `.btn-reserve` handlers being retired in this step — confirmed via the investigation in the analysis phase |
| Modal ID collision | `#vehicleDetailsModal` confirmed as a new, unused ID via `grep` before implementation |

---

## Step 2 — Vehicle Details Content

### Objective

Refine the Vehicle Details modal's visual presentation and information hierarchy: proper image display, clear price/spec layout, availability messaging, and a short "rental information" block — turning the functional-but-plain Step 1 modal into a polished, Design-System-compliant component.

### Files to Inspect

- `docs/DESIGN_SYSTEM.md`, `docs/COMPONENT_LIBRARY.md` (current versions, re-read for latest state)
- `css/styles.css` (`.vehicle-pricebar`, `.section-eyebrow`, brand CSS variables)
- The Step 1 modal markup (as implemented)

### Files Expected to Change

- `vehicles.php` (`#vehicleDetailsModal` markup refinement only — no new modal, no new JS logic)
- `css/styles.css` (new modal-specific rules only if Bootstrap utilities + existing classes are insufficient)

### Existing Components to Reuse

- `--primary`/`--secondary`/`--accent` CSS variables for price and spec styling
- `.vehicle-specs`-style icon+label layout (adapted for modal width, not the narrow card width)
- `.badge bg-success`/`bg-danger` availability badge

### New Components Required

- Possibly a modal-specific spec layout class if the card's `.vehicle-specs` 3-column grid doesn't adapt cleanly to `modal-lg` width (to be determined during implementation — default to reusing `.vehicle-specs` as-is if it works, per "avoid unnecessary custom CSS")

### Existing Logic to Preserve

Same as Step 1 — no handler logic changes in this step, presentation only.

### Conditional Logic Involved

None new. Same open/populate flow as Step 1.

### JavaScript Behavior

No new JS — this step is markup/CSS only, assuming Step 1's populate function already targets the right element IDs. If new sub-elements are added (e.g., a separate "units available" line), the Step 1 populate function gains a couple more `.text()`/`.html()` calls to fill them.

### PHP Behavior

No changes beyond what Step 1 already introduced.

### Database Requirements

None.

### Bootstrap Requirements

- Grid layout inside modal body (e.g., `row`/`col-*`) if image and specs are placed side-by-side at larger widths
- `list-group` or simple `row g-2` for the spec list — whichever matches existing conventions more closely (check `.vehicle-specs` first)

### Responsive Requirements

- At `≥768px`: image and key specs may sit side-by-side within the modal body
- At `<768px`: single-column stack (image on top, specs below)
- No horizontal scrolling at any width

### Accessibility Requirements

- Heading hierarchy inside the modal: one clear title (vehicle name), spec labels as visually distinct but not necessarily new heading levels (avoid over-nesting headings inside a modal)
- Rental information block (age/license reminders) should be plain text, not implying interactivity

### Testing Requirements

- Visual check at 320px, 375px, 576px, 768px, 992px, 1200px, 1400px+
- Confirm price, specs, and availability are clearly legible and visually consistent with the vehicle card's own styling (same brand colors, not a divergent look)
- `php -l` clean
- Console: no new errors

### Acceptance Criteria

- [ ] Modal content follows the structure defined in `VEHICLE_DETAILS_ANALYSIS.md` §9 (image, category, price, availability, specs, rental info, Reserve Now)
- [ ] Visual style is consistent with the existing vehicle card and Design System (no new colors, no inline styles)
- [ ] Responsive at all breakpoints with no overflow
- [ ] No console/PHP errors

### Risks

| Risk | Mitigation |
|---|---|
| Over-engineering the modal with new CSS classes when existing ones suffice | Default to reusing `.vehicle-specs`/`.vehicle-pricebar`-style rules; only add new CSS if reuse genuinely doesn't fit the modal's wider layout |
| Visual inconsistency between card and modal | Cross-check colors/fonts against `docs/DESIGN_SYSTEM.md` before finalizing |

---

## Step 3 — Reserve Now Integration

### Objective

Add a functional "Reserve Now" button inside `#vehicleDetailsModal` that connects to the existing booking flow for already-authenticated users. Authentication branching (guest handling) is deliberately deferred to Step 4 — this step assumes the user is logged in, to isolate the "does the handoff to `#bookingModal` work correctly" question from the "does the auth branch work correctly" question.

### Files to Inspect

- `vehicles.php` (existing `.btn-reserve` handler, lines 678-707, for the exact vehicle-id/label-setting pattern to replicate)
- `#bookingModal` markup (to confirm what fields it expects populated before opening)

### Files Expected to Change

- `vehicles.php`:
  - `#vehicleDetailsModal`'s footer: add/activate the "Reserve Now" button (`#btnReserveFromDetails` or similar distinct ID — not a class that could collide with `.btn-reserve`)
  - New JS handler for this button

### Existing Components to Reuse

- The exact vehicle-id-setting logic from the current inline handler: `$("#vehicle_id").val(vehicleId); $("#bookingModalLabel").text("Book: " + carName);` plus the existing form-reset lines (698-703)
- `#bookingModal`'s `modal('show')` call

### New Components Required

- `#btnReserveFromDetails` button inside the details modal footer
- New click handler bridging details-modal data to `#bookingModal`

### Existing Logic to Preserve

- `#bookingModal` and every downstream step (preview, confirm, receipt, redirect) — zero changes
- The vehicle ID must reach `#vehicle_id` exactly as it does today

### Conditional Logic Involved

```
User clicks #btnReserveFromDetails (inside #vehicleDetailsModal)
    → read the currently-displayed vehicle's id/name
      (stored on the modal itself via data attribute when populated in Step 1,
       OR re-read from the button that originally opened the details modal —
       recommend storing on the modal via .data() when populated, so this
       step doesn't need to track "which card was clicked" separately)
    → close #vehicleDetailsModal
    → on #vehicleDetailsModal's 'hidden.bs.modal' event (fires once,
      after the close transition completes):
        → set #vehicle_id, set #bookingModalLabel (reusing existing lines 693-694 logic)
        → reset #bookingForm, clear preview/alert/receipt state (reusing existing lines 698-703)
        → open #bookingModal
```

**No authentication check in this step** — that arrives in Step 4. This step's handler assumes the user is already logged in (manually test as a logged-in user only).

### JavaScript Behavior

- Store the active vehicle's `id`/`car name` as `.data()` on `#vehicleDetailsModal` itself when Step 1's populate function runs, so Step 3's Reserve handler has a single source of truth without re-querying the original card
- Use Bootstrap's `hidden.bs.modal` event (not a fixed `setTimeout`) to sequence "close details modal, then open booking modal" — this avoids the two modals ever being visually stacked or racing each other, and is the technically correct way to sequence Bootstrap modal transitions
- Confirm no naming collision: `#btnReserveFromDetails` is distinct from `#btnPreview`/`#btnConfirm` (booking modal) and from `.btn-reserve`/`.btn-view-details` (card buttons)

### PHP Behavior

None — no PHP changes in this step.

### Database Requirements

None.

### Bootstrap Requirements

- Correct use of the `hidden.bs.modal` event for sequencing (Bootstrap 5's documented pattern for modal-to-modal transitions)

### Responsive Requirements

No new requirements beyond Steps 1-2 — the Reserve Now button in the footer follows standard modal-footer button sizing.

### Accessibility Requirements

- `#btnReserveFromDetails` disabled (with `disabled` attribute) when the currently-displayed vehicle is unavailable, mirroring the card's own disabled-button pattern
- Focus returns sensibly after the modal-to-modal transition (Bootstrap's default focus-trap behavior should handle this; verify manually)

### Testing Requirements

- **As a logged-in user**: click "View Car Details" → click "Reserve Now" inside the modal → confirm `#bookingModal` opens with the correct vehicle name in its title and the correct `#vehicle_id` value
- Confirm the details modal fully closes (not stacked/hidden behind) before the booking modal appears
- Complete a full booking end-to-end (Preview → Confirm → Receipt → redirect to `receipt.php`) to confirm zero regression in the preserved flow
- Confirm the Reserve Now button is disabled/inert for an Unavailable vehicle
- Console: no new errors during the modal transition

### Acceptance Criteria

- [x] "Reserve Now" inside the details modal opens `#bookingModal` for a logged-in user, with correct vehicle id/name
- [x] Details modal closes cleanly before the booking modal opens — no stacking, no visual glitch
- [~] Full existing booking flow (preview/confirm/receipt/redirect) works unchanged — code untouched (zero diff), but live end-to-end re-verification is blocked by the pre-existing `password`/`password_hash` login schema mismatch (see CHANGELOG); not this step's regression
- [x] Reserve Now is disabled for unavailable vehicles
- [x] No console errors

### Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Modal stacking glitch (both visible at once, or backdrop z-index conflict) | Medium | Use `hidden.bs.modal` event, not immediate/synchronous open — this is the standard, tested Bootstrap 5 pattern already used elsewhere in this codebase for modal-to-modal transitions (login → signup in `auth_modals.php`) |
| Vehicle id/name lost between modals | Low | Store on `#vehicleDetailsModal` via `.data()`, single source of truth, read immediately before closing |
| Breaking `#bookingModal`'s existing flow | High (if it happened) | Zero edits to `#bookingModal` markup or its downstream handlers (preview/confirm/receipt) in this step — only the trigger changes |

---

## Step 4 — Authentication Conditional Refinement

### Objective

Add the authentication branch to the Step 3 handler: guests are redirected to the login/signup flow instead of the booking modal, using the existing `me.php`-based check. This is the step that actually delivers the phase's core requirement — "require authentication only when the user attempts to reserve."

### Files to Inspect

- The current inline `checkLoginStatus()` function (vehicles.php:642-651) — reuse as-is, do not reimplement
- `includes/auth_modals.php` (`#loginModal` structure, confirm no changes needed)
- `app.js`'s login-success handler (lines 1141-1162) — confirm it still does `window.location.reload()` and understand that this is the point where vehicle context is currently lost (unchanged, per the analysis's accepted risk)

### Files Expected to Change

- `vehicles.php`: the Step 3 handler for `#btnReserveFromDetails` gains an `await checkLoginStatus()` call and a branch

### Existing Components to Reuse

- `checkLoginStatus()` (exact existing function, called exactly as the current `.btn-reserve` handler calls it)
- `#loginModal` (existing, from `includes/auth_modals.php`)

### New Components Required

None — this step is pure conditional logic added to Step 3's existing handler.

### Existing Logic to Preserve

- `checkLoginStatus()` internals — untouched
- `#loginModal`/`#signupModal` markup and their own submit handlers in `app.js` — untouched
- The current post-login `window.location.reload()` behavior — untouched (see Risks)

### Conditional Logic Involved

**CURRENT (Step 3 state, before this step):**
```
User clicks #btnReserveFromDetails
    → close #vehicleDetailsModal
    → open #bookingModal directly (no auth check)
```

**PROPOSED (this step):**
```
User clicks #btnReserveFromDetails
    → await checkLoginStatus()
    → close #vehicleDetailsModal
    → on 'hidden.bs.modal':
        if (!userLoggedIn):
            → open #loginModal
        else:
            → set #vehicle_id, set #bookingModalLabel, reset form
            → open #bookingModal
```

### JavaScript Behavior

- `await checkLoginStatus()` called before deciding which modal to open next — same pattern as the current (pre-Step-1) `.btn-reserve` handler, just relocated to fire from the details modal's Reserve button instead of directly from the card
- Both branches close `#vehicleDetailsModal` first (consistent single exit point, then branch on `hidden.bs.modal`)

### PHP Behavior

None.

### Database Requirements

None.

### Bootstrap Requirements

Same `hidden.bs.modal` sequencing pattern as Step 3, now branching to one of two target modals instead of always the same one.

### Responsive Requirements

No new requirements — inherits Steps 1-3.

### Accessibility Requirements

No new requirements — `#loginModal` already has its own accessibility properties (unchanged, from `includes/auth_modals.php`).

### Testing Requirements

- **As a guest (no session)**: View Car Details → Reserve Now → confirm `#loginModal` opens (not `#bookingModal`, not a browser `alert()`)
- Confirm the old `alert("Please log in first...")` no longer appears (it was tied to the retired `.btn-reserve` handler, which no longer exists on the card — verify no leftover reference to it fires)
- Confirm no duplicate modal-open attempts (only one modal opens, not both `#loginModal` and something else)
- **As a guest**: from `#loginModal`, complete login → confirm existing `window.location.reload()` behavior — page reloads, user is now logged in, must re-click the vehicle (this is the accepted, documented limitation, not a regression)
- **As a guest**: from `#loginModal`, click "Sign up" → complete registration → log in → confirm the same reload behavior
- **As a logged-in user**: re-confirm Step 3's flow still works unchanged (no regression from adding the conditional)
- Console: no new errors; specifically confirm no "Cannot read property of undefined" errors from the `checkLoginStatus()` call timing

### Acceptance Criteria

- [x] Guest clicking Reserve Now (inside the details modal) sees the login modal, not a booking modal and not a browser alert
- [x] Authenticated user clicking Reserve Now proceeds directly to the booking modal, unchanged from Step 3
- [x] No duplicate/competing modal opens
- [~] Full registration → login → (re-click vehicle) → reserve flow works end-to-end — blocked by the pre-existing `password`/`password_hash` login schema mismatch (see CHANGELOG); not this step's regression, re-verify once fixed
- [x] No console errors

### Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Vehicle context lost after login (user must re-click the vehicle) | Medium (UX, not correctness) | **Explicitly accepted as out-of-scope** per the analysis (§17) — this is the *existing* app-wide behavior (the current `.btn-reserve` handler has the same limitation today). Fixing it would require `sessionStorage`/URL-param plumbing beyond this phase's approved scope. Flagged here for a possible future enhancement, not silently fixed. |
| `checkLoginStatus()` race condition (button clicked before session check resolves) | Low | Handler is already `async`/`await`-based, matching the existing proven pattern from the current `.btn-reserve` handler — no new race introduced |
| Regression: guest can still somehow reach `#bookingModal` | High (if it happened) | Explicit `if/else` branch, tested both ways in this step before sign-off |

---

## Step 5 — Responsive & UX Refinement

### Objective

Full responsive and UX polish pass across the entire new flow (details modal + its integration with booking/login modals) at every breakpoint, plus any small UX rough edges found during Steps 1-4's testing (e.g., loading state while `checkLoginStatus()` resolves, if it proves noticeably slow).

### Files to Inspect

- The as-implemented `#vehicleDetailsModal` and its handlers (Steps 1-4)
- `css/styles.css` for any breakpoint patterns already established (`.filter-sidebar`'s sticky/offcanvas pattern, `.navbar-offset`, etc.)

### Files Expected to Change

- `vehicles.php` (minor markup adjustments if responsive issues are found)
- `css/styles.css` (targeted responsive fixes only — no wholesale rewrite)

### Existing Components to Reuse

Whatever responsive patterns already exist and apply (Bootstrap's grid/utility classes first, per CLAUDE.md's Bootstrap Standards).

### New Components Required

None expected — this step should not introduce new UI elements, only refine existing ones.

### Existing Logic to Preserve

All flow logic from Steps 1-4 — this step is presentation/UX only, not logic changes, unless a genuine bug is found during testing (in which case it should be flagged and fixed with the smallest possible change, then documented).

### Conditional Logic Involved

None new.

### JavaScript Behavior

- Optional: a lightweight loading indicator (e.g., disable the Reserve Now button briefly, or a small spinner) during the `checkLoginStatus()` await, if manual testing in Step 4 revealed a noticeable delay. Only add if actually needed — do not add speculative loading states.

### PHP Behavior

None.

### Database Requirements

None.

### Bootstrap Requirements

- Confirm `modal-dialog-scrollable` is applied if content can exceed viewport height on short/mobile screens
- Confirm touch-target sizing (button height/padding) meets comfortable tap-target size on mobile

### Responsive Requirements

Full sweep at: 320px, 375px, 576px, 768px, 992px, 1200px, 1400px+, covering:
- `#vehicleDetailsModal` layout and legibility
- Modal-to-modal transition smoothness (details → booking, details → login)
- No horizontal overflow at any width
- Touch interaction on mobile (tap targets, no accidental double-triggers)

### Accessibility Requirements

- Full keyboard-only walkthrough: Tab into "View Car Details", Enter to open, Tab through modal content, Enter/activate "Reserve Now", confirm focus lands sensibly in the next modal, Esc closes modals at each stage
- Screen-reader spot check (or accessibility-tree inspection if a screen reader isn't available) confirming the modal announces its title and that interactive elements have accessible names
- Confirm color contrast on any new text (price, availability badge) meets WCAG AA against its background, consistent with the rest of the site

### Testing Requirements

- Full responsive matrix (see above) via live browser, not just static HTML inspection
- Full keyboard-navigation walkthrough
- Regression pass: filters, sort, pagination, homepage, about, faq, transactions pages — confirm nothing outside `vehicles.php` was affected
- Console error check at each breakpoint

### Acceptance Criteria

- [x] No horizontal overflow or layout breakage at any tested breakpoint
- [~] Full flow (View Details → Reserve → Auth branch → Booking/Login) works via touch on mobile viewport — verified via the Browser pane's automatic mouse-to-touch translation at widths <768px (real touch emulation, per the pane's own documented behavior), not a physical device; see CHANGELOG for detail
- [~] Full flow works via keyboard-only navigation — Tab order, focus trap, focus handoff between modals, and Esc-to-close were all verified with real dispatched key input; native Enter/Space *activation* of a focused button could not be verified in this session due to a Browser pane input-pipeline limitation (confirmed not a page-code issue — see CHANGELOG), flagged rather than assumed passing
- [x] No new console errors at any breakpoint
- [x] No regression on any other page

### Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Regressions discovered late (Step 5) requiring changes to Steps 1-4's logic | Medium | Steps 1-4 each include their own testing/acceptance gate specifically to catch logic issues early; Step 5 should mostly find presentation-level issues, not flow-breaking ones |
| Scope creep (adding new UX features not in the original approved flow) | Low | This step is explicitly "refinement," not "new features" — anything beyond polish should be flagged as a separate future task, not folded in silently |

---

## Step 6 — Final Vehicle Details Review

### Objective

Full-phase regression and sign-off pass, mirroring the "Step 6 (Final Review)" pattern already established in the About Us and Vehicle Listing phases (see `CHANGELOG.md`). Verifies the entire feature end-to-end and updates project documentation.

### Files to Inspect

- The complete as-implemented `vehicles.php`
- `docs/COMPONENT_LIBRARY.md`, `docs/DESIGN_SYSTEM.md`, `docs/BUGS.md`, `docs/PROJECT_AUDIT.md`, `docs/VEHICLE_LISTING_ANALYSIS.md` — for any statements that are now stale post-implementation (e.g., "vehicle card CTA is Reserve Now", references to the old `.btn-reserve` dead-handler bug)

### Files Expected to Change

- `CHANGELOG.md` — new entry documenting the phase
- `docs/COMPONENT_LIBRARY.md` — update the vehicle card / modal sections to reflect the new `.btn-view-details` pattern and the new `#vehicleDetailsModal` component
- `docs/BUGS.md` — mark the duplicate `.btn-reserve` handler issue and the dead `#bookingMultiModal` reference as resolved (superseded by this phase, same pattern as how the Vehicle Listing phase's Step 7 documented dead-code resolution)
- `docs/VEHICLE_DETAILS_ANALYSIS.md` / `VEHICLE_DETAILS_IMPLEMENTATION_PLAN.md` — no changes expected (historical record of the phase, left as-is per this project's established convention)

### Existing Components to Reuse

N/A — this is a documentation and verification step, not a code-authoring step.

### New Components Required

None.

### Existing Logic to Preserve

Everything implemented in Steps 1-5 — this step verifies, and fixes only what regression testing reveals, following the same "no code changes required, verification only" pattern the About Us phase's Step 6 used when everything already passed.

### Conditional Logic Involved

N/A.

### JavaScript Behavior

N/A unless a regression is found, in which case it's a targeted fix, documented as a deviation.

### PHP Behavior

`php -l` across every file touched in the phase, plus every other client-facing page, matching the full-file-list pattern used in prior Step 6 entries (`about.php`, `index.php`, `vehicles.php`, `faq.php`, `transactions.php`, `receipt.php`, all shared includes).

### Database Requirements

None.

### Bootstrap Requirements

Full component audit: confirm no leftover Bootstrap class mismatches (e.g., no repeat of the `.offcanvas`/`.offcanvas-lg` bug class from the Vehicle Listing phase).

### Responsive Requirements

Final full-page (not just the new modal) responsive sweep at 320-1400px+, since new markup was added to a page that already has a sidebar/offcanvas filter panel — confirm no interaction/z-index conflicts between the filter offcanvas and the new details modal.

### Accessibility Requirements

Final accessibility sweep across the whole page, not just the new component, to catch any cross-component regression.

### Testing Requirements

- Functional: full flow end-to-end, both guest and authenticated paths, at least twice each (different vehicles, including one Available and one Unavailable)
- Regression: every item in `VEHICLE_DETAILS_ANALYSIS.md` §18's testing checklist
- UI consistency: side-by-side comparison of the new modal against `docs/DESIGN_SYSTEM.md`
- Authentication: full login, registration, and logout flows re-verified working (given the known pre-existing `password`/`password_hash` schema blocker documented in `CHANGELOG.md` — confirm whether it's still present; if so, this phase's login-modal testing may be blocked by that pre-existing, unrelated issue, and should be reported as such rather than silently worked around)
- Reservation: full booking flow completed successfully at least once as part of this final pass

### Acceptance Criteria

- [ ] Every acceptance criterion from Steps 1-5 re-confirmed true simultaneously (not just individually, at the time each step was implemented)
- [ ] `docs/COMPONENT_LIBRARY.md` and `docs/BUGS.md` updated to reflect the new component and resolved dead-code items
- [ ] `CHANGELOG.md` entry added documenting the phase
- [ ] No open regressions in any other page or shared component
- [ ] All 19 acceptance criteria from `VEHICLE_DETAILS_ANALYSIS.md` §19 satisfied

### Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Pre-existing `password`/`password_hash` login blocker (documented in `CHANGELOG.md`, About Us phase) prevents full live-auth testing | Medium | Verify whether it's still present before this step; if so, report it as a pre-existing blocker (not a regression introduced by this phase) and test as much of the flow as possible around it, consistent with how prior phases handled the same limitation |
| Documentation drift (this step's doc updates become stale after a future phase) | Low | Accepted, ongoing project-wide pattern — each future phase's own Step 6 re-verifies and corrects drift, as already demonstrated by the About Us phase's Step 6 |

---

*This document is a plan only. No code has been modified. Each step requires separate approval before implementation begins, per CLAUDE.md working rules and the phase's explicit approval-gate instructions.*
