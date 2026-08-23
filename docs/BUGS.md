# PMS Car Rental — Bugs, Debt, and Code Health

Every item below was located by reading the actual source and, for JavaScript issues, tracing variable scope statically. Nothing here is speculative UI/UX opinion — each entry cites the file/line and the specific mechanism of the defect. Items whose runtime impact could not be fully confirmed without executing the code are placed under **Potential Bugs**, not **Verified Bugs**.

---

## Verified Bugs

### 1. ~~Undefined variables thrown on every page load — `js/app.js:1-47`~~ **RESOLVED (Customer Dashboard phase, Step 1, 2026-08-14)**
The top-level `$(function () { ... })` block (which runs immediately on document-ready for every page that loads `app.js` — confirmed loaded on `index.php`, `vehicles.php`, `faq.php`, `about.php`, `transactions.php`) references `carType`, `placeRental`, and `from` at [js/app.js:38](../js/app.js):
```js
if (!carType || !placeRental || !from || !to || !pickup || !dropoff) {
```
None of these three identifiers are declared with `var`/`let`/`const` anywhere in an enclosing scope in this file. This throws an uncaught `ReferenceError` as soon as this line executes, on every page load.

**RESOLVED:** Investigation confirmed this block (originally lines 13-46) was orphaned dead code — a leftover fragment of booking-form-submit logic, never attached to any submit event, duplicating pieces of the real, working `$('#bookingForm').on('submit', ...)` handlers found later in the file (`js/app.js:325` and `:486`). No functioning feature depended on it (it always threw before doing anything useful). The entire dead block was removed, leaving the legitimate navbar-active-highlighting code around it intact. Logged in [CHANGELOG.md](../CHANGELOG.md).

### 2. ~~Undefined variable `data` in booking-preview handler~~ **RESOLVED (UI Implementation Plan, Phase 12 — Final UI Review, 2026-08-22)** — was `js/app.js:849-853` (original), `js/app.js:735-741` at time of fix
Inside `$('#bookingStep2Confirm').on('click', async function () {...})`, the preview response is captured as `const preview = await prevRes.json();`, but the code that renders the summary reads from `data` instead:
```js
<p><strong>Days:</strong> ${data.days}</p>
<p><strong>Rate per day:</strong> ₱${data.rate.toLocaleString()}</p>
```
`data` is never declared in this handler's scope. This throws a `ReferenceError` whenever this specific handler runs (bound to `#bookingStep2Confirm`, part of the multi-step booking modal — see Incomplete Implementations).

**RESOLVED, 2026-08-22, UI Implementation Plan Phase 12 (Final UI Review):** all five `${data.*}` interpolations renamed to `${preview.*}` — `preview` being the variable two lines above that actually holds the parsed `reserve_preview.php` response. Nothing else in the handler changed. **Reachability re-confirmed before fixing, and it has not changed:** a repo-wide grep for `id="bookingStep2Confirm"` (and for every other id this wizard binds to — `bookingMultiModal`, `bookingStep1`, `bookingStep2`, `bookingStep3`, `paymentMethod`, `paymentFields`, `receiptSummary`, `driverName`) returns **zero matches in any `.php` file**, so this handler cannot fire in the current UI. This is therefore a correctness fix to unreachable code, not a user-visible behaviour change — it removes a latent `ReferenceError` that would fire the instant the wizard's markup were ever added. The wizard's wholesale removal is a separate, larger question; see the new **item 40** below.

### 3. ~~Undefined variable `amount_paid` in preview request~~ **RESOLVED (System Enhancements initiative, Step 2, 2026-08-21)** — was `vehicles.php:402-414` (original)
*(Line numbers updated 2026-08-08 after Step 1 of the Vehicle Listing Modernization removed ~269 unrelated lines earlier in the file — see [VEHICLE_LISTING_IMPLEMENTATION_PLAN.md](VEHICLE_LISTING_IMPLEMENTATION_PLAN.md) Step 1. The bug itself was unchanged until Step 2 of [SYSTEM_ENHANCEMENTS_IMPLEMENTATION_PLAN.md](SYSTEM_ENHANCEMENTS_IMPLEMENTATION_PLAN.md).)*

**Mechanism corrected 2026-08-21, before the fix (see [SYSTEM_ENHANCEMENTS_ANALYSIS.md](SYSTEM_ENHANCEMENTS_ANALYSIS.md) §5.2, Additional Finding A6):** this was never a thrown `ReferenceError`. The `#btnPreview` click handler built its request body as:
```js
body: JSON.stringify({
  vehicle_id, rental_date, return_date, pickup_time, dropoff_time,
  voucher_code,
  amount_paid
 })
```
`amount_paid` was not declared as a local variable anywhere in this handler — but the page also has an `<input id="amount_paid">` element, and in a non-strict-mode browser script, an undeclared bare identifier that matches an element's `id` resolves via the DOM's implicit global named-access (`window.amount_paid` aliasing the element). So this line did **not** throw — it silently serialized the `<input>` **element object** into the JSON payload as `"amount_paid":{}`, which `reserve_preview.php` simply ignored (it never read that key). The practical symptom was real: the request body carried a nonsensical value instead of a number. But the originally-documented cause (a `ReferenceError` swallowed by the surrounding `try`/`catch`) was incorrect — verified by direct testing before this fix landed.

**Fix:** this entire handler (`vehicles.php`'s standalone `#btnPreview` click handler) was deleted in Step 2 as dead code — it was a duplicate of `js/app.js`'s own preview handler that never actually populated the UI (see item 12-adjacent finding, [SYSTEM_ENHANCEMENTS_ANALYSIS.md](SYSTEM_ENHANCEMENTS_ANALYSIS.md) §5.2 Additional Finding A5). Pricing now comes solely from `js/app.js`'s `window.refreshBookingPreview()`, which never referenced `amount_paid` in its request payload.

### 4. ~~Broken image link — `about.php:149`~~ **RESOLVED (About Us Modernization phase)**
```html
<img src="assets/quider.png" class="w-100 h-100 object-fit-cover" alt="quider">
```
No file named `quider.png` (or any case/extension variant) exists in `assets/`, confirmed by directory listing. This `<img>` will always render broken on the About page. Note: `assets/basil.png` exists but is not referenced by any file read during this inspection — the team member this image was presumably meant for ("Basil Jhudi Quider") is captioned with a photo that 404s instead.

**RESOLVED:** `src` swapped to `assets/basil.png` (manual fix, logged in [CHANGELOG.md](../CHANGELOG.md)), and the leftover `alt="quider"` was corrected to `alt="Basil Jhudi Quider"` during the About Us Modernization phase's Team step (Step 4). The same step also replaced all five team members' placeholder "TBA" role text with real role titles — that content gap is resolved as well, current markup confirmed at `about.php:130-171`.

### 5. ~~Admin session guard redirects to a non-HTML JSON endpoint~~ **RESOLVED (Admin Dashboard phase, Steps 1 and 10, 2026-08-20/21)** — `includes/header.php:5` (original, file since deleted)
```php
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
```
`login.php` is a JSON-only API endpoint (`header('Content-Type: application/json')`, no HTML output) — it is not the admin login page. Any unauthenticated visit to an admin page that includes this header (`admin_users.php`, `admin_vehicles.php`, `view-all-data.php`) redirects the browser to a page that renders raw JSON text (`{"error":"Missing email or password."}`) instead of a usable login form. The actual admin login page is `admin-login.php`.

**Annotated, 2026-08-20, Admin Dashboard phase, Step 1:** as of this step, `admin_users.php` and `admin_vehicles.php` no longer include `includes/header.php` at all (their double-nested-document shell was unified — see [CHANGELOG.md](../CHANGELOG.md)), so this defect was no longer reachable from either page. `includes/header.php` itself was initially left unmodified and in place, per the Admin Dashboard implementation plan's Step 1, which deferred deletion to Step 10 pending a repo-wide reference check.

**RESOLVED, 2026-08-21, Admin Dashboard phase, Step 10 (Final Review & Documentation):** a repo-wide grep (every `include`/`require`/`include_once`/`require_once` across every `.php` file, plus a separate check for dynamic `include($var)`/`require($var)` patterns) confirmed zero remaining references, literal or dynamic, to `includes/header.php` or `includes/footer.php`. Both files were deleted. This bug can no longer exist in the codebase.

### 6. ~~`.confirm-transaction` click handler only registers after a delete is confirmed at least once~~ **RESOLVED (Admin Dashboard phase, Step 4, 2026-08-20)** — `view-all-data.php:182-229` (original)
```js
$(document).on('click', '.delete-transaction', function() {
    if (confirm('...')) {
        ...
        $.ajax({ ... });

    // Handle confirm transaction (admin)
    $(document).on('click', '.confirm-transaction', function() {
        ...
    });
        }
    });
});
```
The `$(document).on('click', '.confirm-transaction', ...)` registration is nested inside the `if (confirm(...))` block of the `.delete-transaction` handler, rather than being a sibling statement. As written, the Confirm button's click handler is never bound on page load — it only gets registered the first time a user clicks Delete and accepts the confirm dialog. Before that happens, clicking "Confirm" on a pending transaction in this page does nothing.

### 7. ~~Status badge color logic never matches actual data~~ **RESOLVED (Admin Dashboard phase, Step 3, 2026-08-20)** — `admin-dashboard.php:130-135` (original)
```php
$badgeClass = match ($row['status']) {
    'Completed' => 'bg-success',
    'Active' => 'bg-warning text-dark',
    'Cancelled' => 'bg-danger',
    default => 'bg-secondary',
};
```
`bookings.status` is a lowercase enum (`completed`, `pending`, `confirmed`, `cancelled` — confirmed from the schema) and has no `'Active'` value at all. Because PHP's `match` uses strict (`===`) comparison, none of the three named cases can ever match real data — every row's badge falls through to `default => 'bg-secondary'` regardless of its actual status.

**RESOLVED:** replaced with `match (strtolower($row['status']))` keyed on all four real enum values (`completed` → `bg-success`, `confirmed` → `bg-primary`, `cancelled` → `bg-danger`, `pending` → `bg-warning text-dark`), matching the mapping already proven at `transactions.php:282-296`. Verified live against real data: `cancelled` and `confirmed` rows render distinct badges (`bg-danger` vs `bg-primary`); `pending`/`completed` were verified by code inspection only, since no row of either status existed in the Recent Transactions' 5-row slice at verification time — seeding synthetic bookings of every status into the shared local database was not performed, since that would alter real data rather than exercise a read-only code path.

### 8. Dead status check in delete guard — `delete_booking.php:30`
```php
if ($result['status'] === 'active') {
    throw new Exception('Cannot delete active bookings');
}
```
`bookings.status` never contains the literal value `'active'` (confirmed enum values above). This guard can never trigger, so this endpoint has no effective protection against deleting bookings in any particular state — it will delete a `pending`, `confirmed`, or `completed` booking exactly the same as a `cancelled` one.

### 9. ~~Duplicate `id="usage_limit"` across two modals breaks Edit-form population~~ **RESOLVED (Admin Dashboard phase, Step 6, 2026-08-20)** — `admin_vouchers.php:189-191, 230-232, 279` (original)
The Add Voucher modal contains:
```html
<label for="edit_usage_limit" class="form-label">Usage Limit</label>
<input type="number" class="form-control" id="usage_limit" name="usage_limit" ...>
```
and the Edit Voucher modal separately contains:
```html
<label for="usage_limit" class="form-label">Usage Limit</label>
<input type="number" class="form-control" id="usage_limit" name="usage_limit" ...>
```
Both inputs share the literal `id="usage_limit"` (an invalid, duplicate ID within one HTML document), and neither has `id="edit_usage_limit"`. But the JS that opens the edit modal does:
```js
$('#edit_usage_limit').val($button.data('usage-limit'));
```
`$('#edit_usage_limit')` matches zero elements, so this line is a no-op — the Usage Limit field in the Edit Voucher modal is never pre-filled with the voucher's current value when an admin clicks Edit. It's left at whatever value it last held (its HTML `value="1"` default, or a leftover value from the Add form due to the duplicate ID), so submitting an edit without manually re-entering the usage limit will silently overwrite it.

**RESOLVED, 2026-08-20, Admin Dashboard phase, Step 6 (Admin Forms Standardization):** the Edit Voucher modal's Usage Limit input is now `id="edit_usage_limit"` (matching the JS that already targeted that id), and each modal's label `for` attribute now points at its own modal's input instead of the other modal's. No duplicate `id` remains in the document. Verified live against the running local instance: opening Edit on a real voucher (`BOOK50`, usage limit `3`) correctly populated the Usage Limit field with `3` (previously would have shown the stale HTML default); editing only the voucher's code and saving reloaded the page with the code changed and the usage limit still `3`, confirmed via the same PHP-rendered `data-usage-limit` value the edit button reads from — the direct regression test for this bug, run against the live database, not a mock. `document.querySelectorAll('[id]')` duplicate check confirmed clean on `admin_vouchers.php` and all four other admin pages.

### 10. ~~Cancel/Return-Early buttons rendered for bookings regardless of their actual status~~ **RESOLVED (Customer Dashboard phase, Step 3 code fix / Step 7 Final Review, 2026-08-19)** — `transactions.php:186-200` (original)
```php
<?php if ($status === 'Active'): ?>
  <button ... onclick="showReturnEarlyModal(...)">Return Early</button>
<?php elseif ($status === 'Upcoming'): ?>
  <button ... onclick="showCancelModal(...)">Cancel Booking</button>
<?php endif; ?>
```
`$status` here is the *time-based* bucket (Upcoming/Active/Completed, computed purely from `rental_date`/`return_date` vs. today), not the booking's actual `status` column. A booking whose real `status` is already `cancelled` but whose `rental_date` is still in the future is placed in the `$active` array (the query only excludes `status = 'completed'`) and will still display an active-looking "Upcoming" time badge alongside its red "Cancelled" status badge, with a working-looking "Cancel Booking" button underneath. Clicking it calls `cancel_booking.php`, which correctly rejects it server-side ("Booking not found or already cancelled") — so no data corruption results, but the UI presents an action button for a booking that cannot actually be acted on.

**Code fix applied, 2026-08-14, Customer Dashboard phase, Step 3:** action buttons are now driven entirely by `$booking['status']` (`pending`/`confirmed` render Cancel Booking or Return Early depending on the rental date window; `cancelled` and `completed` bookings no longer reach the Active-section loop at all — they're bucketed into the Completed/History section, which renders no action buttons for either status, only a View Receipt link for `completed` rows). The scenario described above — a cancelled booking with a future rental date sitting in `$active` with a live-looking button — is now structurally impossible, not just visually suppressed, since `$active` can only ever contain `pending`/`confirmed` bookings (see Step 3's bucketing fix, [CHANGELOG.md](../CHANGELOG.md)).

**RESOLVED, 2026-08-19, Customer Dashboard phase, Step 7 (Final Review):** re-verified live with test bookings in all four statuses — `pending`/`confirmed` rows render exactly one correct action button (Cancel Booking or Return Early depending on date window), `confirmed` rows additionally get "View Booking Confirmation" (Step 3.1), `cancelled`/`completed` rows render no action buttons, only the appropriate badge and (for `completed`) a View Receipt link. No regressions found. Formally marked resolved together with item 11 per the implementation plan's Final Review convention.

### 14. ~~Admin sidebar offcanvas hidden at desktop width~~ **RESOLVED (Admin Dashboard phase, Step 1, 2026-08-20)** — `includes/admin_sidebar.php:2` (original)
```html
<div class="offcanvas offcanvas-start offcanvas-lg" tabindex="-1" id="adminSidebar" ...>
```
This carries the identical class combination that was found and fixed in `vehicles.php`'s filter sidebar during Step 4 of the Vehicle Listing Modernization (that fix dropped the bare `offcanvas` class, leaving only `offcanvas-lg offcanvas-start filter-sidebar` — confirmed current at `vehicles.php:457`). `admin_sidebar.php` was never given the same fix.

**Verified directly against the actual compiled stylesheet** (Motion Design Phase 9, 2026-08-13): fetched `https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css` — the exact CDN URL every admin page loads — and inspected the real rule order and media-query scoping (not inferred from Bootstrap's documented behavior):
- A bare, **unscoped** `.offcanvas{position:fixed;...;visibility:hidden;...}` rule exists exactly once, outside any media query, positioned in the file *after* all five `.offcanvas-{sm,md,lg,xl,xxl}` responsive blocks. This applies at every viewport width unconditionally.
- `.offcanvas-lg`'s own rules are entirely split across two media queries: `@media (max-width:991.98px){.offcanvas-lg{position:fixed;...;visibility:hidden;...}}` (redundant with the bare rule below 992px) and `@media (min-width:992px){.offcanvas-lg{--bs-offcanvas-height:auto;--bs-offcanvas-border-width:0;background-color:transparent!important} .offcanvas-lg .offcanvas-header{display:none} .offcanvas-lg .offcanvas-body{display:flex;flex-grow:0;padding:0;overflow-y:visible;background-color:transparent!important}}`.
- Critically, the `min-width:992px` block **does not reset `visibility`, `position`, or `transform`** — it only touches custom properties, background, and header/body layout. The `visibility:visible` override that makes `.offcanvas-lg` behave like a real navbar-embedded collapse only exists on the unrelated `.navbar-expand-{bp} .offcanvas` selector (for Bootstrap's navbar component), which does not apply here since `#adminSidebar` is not nested inside a `.navbar-expand-*` element.

Net effect: at every viewport width, including desktop (≥992px), `#adminSidebar` keeps `visibility:hidden` from the unconditional bare `.offcanvas` rule unless Bootstrap's JS has added a `.show`/`.showing` class to it (which only happens when the `d-lg-none` toggle button — itself hidden at desktop — is clicked). The sidebar is invisible on every admin page at desktop width by default. **Not fixed in this phase** (Motion Design Phase 9 is scoped to spinners only) — the fix, when scheduled, is the same one-line change already applied to `vehicles.php`: drop the bare `offcanvas` class, keep `offcanvas-lg offcanvas-start`.

**RESOLVED, 2026-08-20, Admin Dashboard phase, Step 1 (Admin Shell Repair & Sidebar Visibility):** `includes/admin_sidebar.php:2` changed from `class="offcanvas offcanvas-start offcanvas-lg"` to `class="offcanvas-lg offcanvas-start"`, the identical class combination already proven at `vehicles.php:457`. Verified live on all five admin pages (`admin-dashboard.php`, `admin_users.php`, `admin_vehicles.php`, `admin_vouchers.php`, `view-all-data.php`): sidebar renders with no interaction required at 993px and 1400px, and opens/closes as a drawer via the hamburger and the close button at 991px and 375px, on every page. Active-link highlighting (filename-derived) re-verified unaffected on all five.

**Addendum, 2026-08-21, System Enhancements initiative, Step 10 (Dark Mode — Admin Sweep):** the `background-color: transparent !important` behavior this entry already documented at line 141 (the `min-width:992px` Bootstrap block) means `#adminSidebar { background: var(--sidebar-bg); }` (`css/styles.css:452-455`, added in Step 8b) **never actually applies at ≥992px, in either theme** — confirmed live: `getComputedStyle(#adminSidebar).backgroundColor` reads `transparent` at 992px width in both light and dark mode, while correctly resolving to the theme's `--sidebar-bg` value below that width (the offcanvas drawer state, where Bootstrap's reset doesn't apply). This is not a dark-mode regression — light mode has had the identical gap since Step 8b, just less visible against a white page background. **Not fixed here** — overriding Bootstrap's `!important` reset to force a colored sidebar-as-static-block at desktop width is a structural/layout change beyond Step 10's dark-mode-correction mandate, and risks fighting the exact behavior item 14 relies on for the sidebar to render as a normal, borderless, static block instead of a floating offcanvas panel at desktop widths. Flagged for a future dedicated pass if a persistent, visually distinct desktop sidebar panel is wanted.

---

### 13. ~~Duplicate `#contactForm` submit handler silently wiped typed input and hid the real alert~~ **RESOLVED (Duplicate-Handler Containment pass, 2026-08-12)**
`js/app.js` (formerly lines ~422-445) bound a *second*, unconditional `submit` handler onto `#contactForm` — the same element `about.php`'s own inline script (`about.php:272`) already handles, correctly gating on server-rendered login state. Since `about.php` is the only page with `id="contactForm"` (confirmed via a repo-wide grep before removal), this second handler was pure dead-weight duplication, not a legitimate second use site. Live reproduction (Phase 7 motion verification) showed both handlers fired on every submit: `about.php`'s handler correctly displayed "Please log in to send a message." and left it visible, but `js/app.js`'s handler ran alongside it, unconditionally (no login check) called `$(this)[0].reset()` — wiping the user's typed name/email within the same tick — and queued a `setTimeout(...,2500)` that forced `#contactAlert` back to `d-none` ~2.5s later regardless of what message was showing, plus flashed a spinner on the Send button for that same 2.5s even though nothing had actually been sent. Timed trace (t=0/500/3000/3500ms) confirmed the alert vanishing and fields clearing at ~2.7s pre-fix; both symptoms gone post-fix. **Fix:** the duplicate handler was deleted outright from `js/app.js` (not merged, not guarded — verified no other page references `#contactForm`, so there was nothing to preserve).

---

## Potential Bugs

These are suspicious patterns confirmed in the code but whose full runtime impact could not be verified without executing the application.

- **`register.php:11`** — `$_SERVER['CONTENT_TYPE']` is read without an `isset()`/`??` guard: `if (!empty($_FILES) || $_SERVER['CONTENT_TYPE'] && stripos(...))`. Some HTTP clients omit the `Content-Type` header on GET-like requests; if absent, this triggers a PHP "Undefined array key" warning. `ini_set('display_errors', 0)` is set immediately after, which should suppress it from output, but this depends on `error_reporting`/logging configuration outside this file, which was not inspected.
- **`reserve.php`** — supports an `is_preview` branch that re-implements the same days/subtotal/discount calculation already performed by the separate `reserve_preview.php` endpoint. Both must independently stay in sync; a change to discount logic in one without the other would silently produce inconsistent preview vs. final totals. Not confirmed as currently inconsistent, but the duplication itself is a latent risk.
- **`get_vouchers.php`** — has no session/login check and does not filter by `is_active`, only by `usage_count < usage_limit`. A voucher manually deactivated by an admin (`is_active = 0`) but still under its usage limit would still appear in the customer-facing voucher dropdown, even though `reserve.php`/`reserve_preview.php` would reject it at submission time. Confirmed from the query; full user-facing impact (confusing rejection after selection) not runtime-tested.
- **`js/apply_voucher.php` calling code in `js/app.js`** (`#voucherCode` change handler, referencing `#totalAmount`, `#discountAmount`, `#finalAmount`) — these element IDs do not appear in the booking form markup confirmed in `vehicles.php` (which uses `#voucherSelect` instead). Whether this handler ever fires in the current UI could not be confirmed statically. — **RESOLVED as a question, 2026-08-22 (UI Implementation Plan, Phase 12):** it does **not** fire. A repo-wide grep found **zero** occurrences of `id="voucherCode"`, `id="totalAmount"`, `id="discountAmount"` or `id="finalAmount"` in any `.php` file, so the `change` handler has nothing to bind to and the three elements it writes to do not exist. Confirmed dead, not merely suspicious — this entry graduates out of "Potential Bugs". The code itself is left in place and folded into **item 40** (deferred dead-code removal in `js/app.js`), not deleted piecemeal here.
- ~~**`includes/admin_sidebar.php:2` — offcanvas sidebar plausibly invisible at desktop width.**~~ **CONFIRMED (Motion Design Phase 9, 2026-08-13)** — moved to Verified Bugs #14 below. See that entry for the full mechanism, verified directly against the compiled `bootstrap@5.3.2` CSS (not just inferred from the `vehicles.php` precedent).

---

## Technical Debt

- **Two parallel database connection layers** (`db.php` using PDO, `db_connect.php` using mysqli) used inconsistently across the codebase. ~~`vehicles.php`, `apply_voucher.php`, and `get_vouchers.php` opening both in the same request.~~ **Partially resolved 2026-08-08** — `vehicles.php`'s unused `db_connect.php` include was removed (it only ever fed a `$categories` array that nothing rendered). `apply_voucher.php` and `get_vouchers.php` still open both connections; this item remains open for those two files. Documented in full in [ARCHITECTURE.md](ARCHITECTURE.md).
- **No shared session/auth-guard function** — every one of the ~30 endpoint files re-implements its own `if (!isset($_SESSION[...]))` check inline.
- **No shared response-envelope convention** — JSON endpoints mix `{success, ...}`, `{error, ...}`, and both patterns within the same file (e.g. `reserve.php`).
- **Schema drift managed through defensive runtime probing** rather than a migration system — `return_early.php` queries `information_schema.COLUMNS` at request time, and `register.php` runs `ALTER TABLE` from within request handling if a column is missing. Both are functioning workarounds, but they mean schema correctness is partly enforced by application code at runtime instead of guaranteed by deployment.
- **No `.htaccess`/routing layer** — every page is a literal, directly-addressable file path with no abstraction over it.
- **Two separate license-upload directories** (`assets/licenses/` from `register.php`, `uploads/licenses/` from `reserve.php`) for what is conceptually the same kind of document.

---

## Code Smells

- **`js/app.js` is 1208 lines with no module structure** — all logic (navbar rendering, auth, booking flow, voucher handling, category filtering) lives in one file with multiple independent `$(function(){...})`/`$(document).ready(...)` blocks rather than organized, named functions.
- **Multiple competing implementations of the same booking-confirm flow** exist side by side in `js/app.js` (at minimum: the handler at `#btnConfirm`, the handler at `#bookingForm` submit near line 298, and the handler at `#bookingStep2Confirm` near line 796) plus `vehicles.php`'s own inline duplicate of the same flow. They are not calls to a shared function — each re-implements field collection, the `fetch('reserve.php', ...)` call, and response handling independently. **Live-reproduction evidence (Phase 7 motion verification, 2026-08-12):** a single Preview click fired **5** POSTs to `reserve_preview.php` (2 duplicate `#btnPreview` handlers — `js/app.js` + `vehicles.php` — compounded by `js/booking-validation.js`'s auto-click-on-date-change firing both again); a single Confirm click fired the `js/app.js` `#btnConfirm` handler and, conditionally, a second POST from `vehicles.php`'s own `#bookingForm` submit handler whenever the required `#license_file` field is filled (native HTML5 validation was silently blocking the second handler in the untested case, masking the double-submit risk). **Containment applied, 2026-08-12:** a `if ($(this).prop('disabled')) return;` / `if ($btn.prop('disabled')) { e.preventDefault(); return; }` guard was added as the first line of whichever handler in each pair does *not* already disable the button first (`vehicles.php`'s `#btnPreview` and `#bookingForm submit` handlers), relying on the other handler's existing `PMSMotion.setButtonLoading(..., true)` call to have already disabled the button by the time the second handler runs in the same event dispatch. Verified live: Preview and Confirm both now produce exactly 1 network request per click. **This is containment only — the four-implementation duplication itself remains open, unchanged, and is not addressed here; see this same bullet's original text above.**
- ~~**Inconsistent jQuery version pinning** — 3.6.0 on admin pages, 3.7.1 on customer pages (verified from `<script>` tags), with no stated reason for the split.~~ **STALE, re-verified RESOLVED (UI Implementation Plan, Phase 11 — Performance Review, 2026-08-21):** a repo-wide grep for every `<script src="...jquery...">` tag across all 20 PHP entry points found `jquery-3.7.1.min.js` used everywhere, admin and customer pages alike — zero references to `3.6.0` remain anywhere in the codebase. This item's underlying split was fixed by an earlier, undocumented change; only this doc entry was left stale. Bootstrap (`5.3.2`), Font Awesome (`6.4.2`), Animate.css (`4.1.1`), and DataTables (`1.11.5`) were also re-verified consistent site-wide in the same pass — no other CDN version mismatches found.
- **String-concatenated HTML building in PHP** (`vehicles.php`, `admin_vehicles.php`, `admin-dashboard.php`) mixed with heredoc-style embedded `<?php ?>` blocks in other files (`transactions.php`, `admin_users.php`) — two different HTML-generation styles used interchangeably across otherwise-similar admin list pages.
- ~~**Mismatched label `for`/input `id` pairs** beyond the duplicate-ID bug above — e.g. `admin_vouchers.php`'s Add modal label `for="edit_usage_limit"` pointing at an input whose real `id` is `usage_limit`.~~ **STALE, re-verified RESOLVED (UI Implementation Plan, Phase 12 — Final UI Review, 2026-08-22).** An exhaustive audit was run this phase rather than spot-checking the one cited example: for **every** `.php` file in the repository plus every `includes/` partial, the full set of `for="…"` values was diffed against the full set of `id="…"` values in the same file. **Zero labels point at a non-existent id, in any file.** The specific `admin_vouchers.php` case named above is confirmed fixed — the Add modal reads `for="usage_limit"` → `id="usage_limit"` (lines 213-214) and the Edit modal reads `for="edit_usage_limit"` → `id="edit_usage_limit"` (lines 264-265), correctly paired in both. This was collateral of Step 6's duplicate-id fix (item 9); only this doc entry was left stale.

  The same sweep also re-checked **duplicate ids across every page**: the only hits were three ids in `vehicles.php` (`bookingPreview`, `previewContent`, `amount_paid`) that appeared twice **only because a commented-out copy of that markup was still present** — HTML comments are not parsed as elements, so these were never real DOM duplicates, but they made every automated audit of the file report false positives. That dead comment block has now been deleted (see **Deprecated Code** below), and the sweep is clean: **zero duplicate ids in any file**, re-confirmed in-browser via `document.querySelectorAll('[id]')` on `vehicles.php`.

---

## Duplicate Code

- ~~**Navbar, footer, and login/signup modal markup** is duplicated verbatim across five files: `index.php`, `vehicles.php`, `transactions.php`, `faq.php`, `about.php`.~~ **Stale for `about.php`:** since the Shared Components phase, `about.php` includes `includes/client_navbar.php`, `includes/client_footer.php`, and `includes/auth_modals.php` rather than duplicating this markup — confirmed by direct inspection during the About Us Modernization phase. See [ARCHITECTURE.md](ARCHITECTURE.md) (Shared Includes).
- **Voucher-dropdown population (`loadVouchers`)** is implemented twice independently: once in `js/app.js` and once as `VoucherManager.loadVouchers()` in `js/voucher-manager.js` — both call the same `get_vouchers.php` endpoint and build the same `<select>` options.
- **Days/subtotal/discount calculation** is implemented independently in three places: `reserve_preview.php`, `reserve.php` (its own inline copy, not a call to `reserve_preview.php`), and again client-side in `getBookingFormData()` inside `js/app.js` (a simplified version using a hardcoded car-type-to-rate map, unrelated to the actual `vehicles.price_per_day` column).
- **Booking-confirm submission logic**, as noted under Code Smells, is duplicated at least four times across `js/app.js` and `vehicles.php`'s inline script. A disabled-button guard now prevents the double-network-request symptom of the `#btnPreview`/`#bookingForm submit` pair specifically (see Code Smells entry above for live before/after evidence) — the underlying duplication is otherwise untouched.
- ~~**`#contactForm` submit handler** was duplicated between `about.php` (real, login-gated) and `js/app.js` (dead weight, unconditional).~~ **Resolved 2026-08-12** — see Verified Bugs #13. The `js/app.js` copy is deleted; `about.php` is confirmed the only page with `id="contactForm"`.

---

## Broken Links

- ~~`assets/quider.png` referenced by `about.php:149` — file does not exist~~ **RESOLVED** — see Verified Bugs #4.
- ~~`includes/header.php:5` redirects unauthenticated admin users to `login.php`, which is a JSON API endpoint, not a login page (see Verified Bugs #5).~~ **STALE, RESOLVED** — see Verified Bugs #5: `includes/header.php` was deleted outright during the Admin Dashboard phase (Step 10) once every admin page redirected to `admin-login.php` directly. Re-verified 2026-08-22 (Phase 12): the file does not exist, and a repo-wide grep finds zero references to it. Only this Broken Links entry was left stale.
- ~~The "Book Now (Multi-Step)" button in `vehicles.php` (`id="openBookingMultiModal"`) triggers `$('#bookingMultiModal').modal('show')` in `js/app.js`, but no element with `id="bookingMultiModal"` exists anywhere in `vehicles.php`'s markup.~~ **RESOLVED 2026-08-08** — the dead button was removed from `vehicles.php`. See Resolved section below. The underlying unreachable `#bookingMultiModal` JS (see Incomplete Implementations) is still present and still dead, just no longer triggerable from this button.

---

## Deprecated Code

- ~~**`js/app.js:468-497` and `:507-534`** — large commented-out blocks implementing an earlier `localStorage`-based login/signup flow (`localStorage.getItem('pmsUser')`, `localStorage.setItem('pmsUsers', ...)`), superseded by the current server-session-based flow (`me.php`/`login.php`/`register.php`) that appears later in the same file. Left in place as comments rather than removed.~~ **RESOLVED** — deleted (Authentication Step 5), along with the adjacent commented-out `#logoutBtn` handler in the same block. Confirmed via grep that nothing live referenced `pmsUser`/`pmsUsers` before removal.
- **`js/app.js:740-792`** — a second, fully commented-out implementation of the `#bookingStep2Confirm` handler that builds a receipt from `localStorage`-stored bookings, directly superseded by the active (but buggy — see Verified Bug #2) implementation immediately following it at line 796.
- ~~**`vehicles.php:138-150`** — a commented-out earlier version of the `#bookingPreview` markup (with the amount-paid input inline and `required`), superseded by the active version below it that moved the amount input into a separate `#amountPaidSection` block.~~ **RESOLVED (UI Implementation Plan, Phase 12 — Final UI Review, 2026-08-22)** — deleted (it had drifted to `vehicles.php:331-344` by the time of removal). Two concrete reasons beyond general tidiness, both verified: (1) it duplicated three element ids verbatim (`bookingPreview`, `previewContent`, `amount_paid`), which made every automated duplicate-id audit of this file report three false positives — the file is now clean; (2) its `<label class="form-label">Amount to Pay</label>` carried **no `for` attribute**, so uncommenting it would have re-introduced precisely the unlabelled-input defect Phase 10 fixed on the live copy. The live version immediately below it is unchanged and supersedes it in full. `php -l vehicles.php` clean; page re-verified live with zero console errors and no visual change.
- ~~**`vehicles.php:231-481`** — a large commented-out block of static, hardcoded vehicle `<div class="col-md-4 car-card">` cards, superseded by the PHP loop immediately below it.~~ **RESOLVED 2026-08-08** — deleted. See Resolved section below.

---

## Incomplete Implementations

> **User-reachability audit (UI Implementation Plan, Phase 12 — Final UI Review, 2026-08-22).** Every item in this section was re-checked against one specific question: *can a user stumble into this through the UI and see something that looks like a broken feature?* Summary of that audit — detail inline against each item below:
>
> | Incomplete implementation | Reachable through the UI? | Looks broken to a user? |
> |---|---|---|
> | Multi-step booking modal (`#bookingMultiModal` + wizard) | **No** — zero markup; its only trigger button was deleted in 2026-08-08 | No — nothing renders, nothing to click |
> | Payment-method fields (`#paymentMethod`, `#paymentFields`) | **No** — zero markup | No — nothing renders |
> | Voucher reactivation | **N/A** — the *absence* of a control, not a broken one | No — no disabled/dead button is shown |
> | `bookings.actual_return_date` / `early_return` | **No UI surface** — never displayed on any page | No |
>
> **Conclusion: none of the four is a user-visible broken feature.** They are dead code and absent capabilities, not half-rendered UI. No hiding work was needed in this phase — nothing was found that renders a control leading nowhere. This is recorded as a verified finding, not an assumption; the id-by-id grep evidence is in **item 40**.

- **Multi-step booking modal (`#bookingMultiModal`, `#bookingStep1`/`#bookingStep2`/`#bookingStep3`, `showStep()`, `resetBookingModal()`, `$('#bookingStep1Next')`, `$('#bookingStep2Back')`, `$('#bookingStep2Confirm')`, `$('#bookingStep3Close')`)** — extensive supporting JavaScript for a multi-step booking wizard exists in `js/app.js`, including payment-method-specific field rendering (credit card / GCash) and a step-progress indicator, but no corresponding HTML (`#bookingMultiModal` and its step containers) was found in any PHP page inspected. The only page reference to it is the non-functional trigger button described under Broken Links. This entire feature is unreachable in the current markup. **Re-confirmed 2026-08-22 (Phase 12):** zero `.php` occurrences of any of these ids, and the trigger button itself was removed from `vehicles.php` on 2026-08-08 — so there is no longer any control a user could click to reach it. **Not user-visible as a broken feature**; it is invisible. Tracked for removal as **item 40** (deferred — shared-file blast radius).
- **Voucher reactivation** — `admin_vouchers.php`'s `update` action does not include `is_active` in its `UPDATE vouchers SET ...` statement, and the create form has no `is_active` input either (it's hardcoded to `1` in the `INSERT`). There is no UI path to deactivate or reactivate a voucher.
- **Payment method fields (`#paymentMethod`, `#paymentFields`, credit-card/GCash inputs)** — `js/app.js` contains a `change` handler that renders card-number/expiry/CVV or GCash-number fields based on a `#paymentMethod` select, but no such select or its container was found in any PHP page's markup, and no server-side endpoint processes card or GCash fields — payment is handled entirely as a self-reported `amount_paid` number, confirmed in `reserve.php`. **Re-confirmed 2026-08-22 (Phase 12):** zero `.php` occurrences of `paymentMethod` or `paymentFields`. **Not user-visible as a broken feature** — no payment-method selector renders anywhere, so a user is never offered a card/GCash option that then fails; the booking form asks only for an amount, which works. Worth stating explicitly because "the app collects card numbers but does nothing with them" would be a serious defect — it does **not**: the card/GCash inputs exist only as unreferenced JS that never runs, and no card data is ever collected, transmitted, or stored.
- **`bookings.actual_return_date` / `bookings.early_return`** — `return_early.php` only sets these columns if they're detected as present via an `information_schema` probe at request time; neither column is created by any of the four migration scripts inspected, so on a database that hasn't had them added manually, early-return records proceed without this data being recorded.

---

### 11. ~~Unguarded `.datepicker()`/`.timepicker()` calls at top level of `js/app.js:51-57`~~ **RESOLVED (Customer Dashboard phase, Step 1 code fix / Step 7 Final Review, 2026-08-19)** — was CURRENTLY ACTIVE on `transactions.php`, latent elsewhere
```js
$("#rentalDate, #returnDate").datepicker({ ... });
$("#pickupTime, #dropoffTime").timepicker({ ... });
```
These two calls sit at the top level of `app.js` — after the `carType` ReferenceError block closes at line 47 (see Verified Bug #1) but *not* inside any `$(document).ready(...)` wrapper. They execute synchronously the moment `app.js` is parsed.

The target IDs (`#rentalDate`, `#returnDate`, `#pickupTime`, `#dropoffTime` — note the missing underscores, these don't match the real form field IDs `#rental_date` etc.) match zero elements on every page, and jQuery UI plugin methods no-op on an empty selection *as long as the plugin is actually loaded*. Whether it's loaded depends entirely on which `<script>` tags each page happens to include — and that is inconsistent across the five pages that load `app.js`:

| Page | Loads `jquery-ui` + `jquery-ui-timepicker-addon`? | Result |
|---|---|---|
| `index.php` (`:315-317`) | Yes | Latent only |
| `vehicles.php` | Yes | Latent only |
| `faq.php` (`:98-101`) | Yes | Latent only |
| `about.php` (`:216-218`) | Yes | Latent only |
| `transactions.php` | **No** — only jQuery core + Bootstrap bundle are loaded before `app.js` (`:62, :319-320`) | **Crashes on every load, today** |

**Confirmed by direct execution, per page**, not static analysis: `app.js` was run in a jsdom sandbox loading the exact CDN builds each page references.
- **`index.php`/`vehicles.php`/`faq.php`/`about.php` (jquery-ui + timepicker-addon present):** no throw at lines 51/57; the rest of `app.js` executes normally (`renderNavbarAuth()`, login/signup handlers, the `#openBookingMultiModal` click binding at line 572, etc. all run).
- **`transactions.php` (neither plugin loaded) — tested live against the running site:**
  ```
  jsdomError: Uncaught [TypeError: $(...).datepicker is not a function]
  jsdomError: Uncaught [ReferenceError: carType is not defined]
  #navbarAuthArea populated (proxy for "did renderNavbarAuth() run"): false
  ```
  The `TypeError` at line 51 is an uncaught synchronous exception at the top level of the script (not inside a `try`/`catch` or an async callback), so it halts all remaining top-level code in `app.js` — confirmed `renderNavbarAuth()` never populates `#navbarAuthArea`. **On `transactions.php` right now, in production, the navbar's login/signup/logout display is broken** — independent of, and unrelated to, anything touched in the Vehicle Listing Modernization. (The `carType` ReferenceError also still fires separately — it's inside a previously-registered `$(document).ready` callback that runs asynchronously later, unaffected by the synchronous throw earlier in the same file. See Verified Bug #1.)

**Practical implication:** `js/app.js:51-57` should either be guarded (`typeof $.fn.datepicker === 'function'`) or moved into a ready handler with proper feature-detection, or `transactions.php` should load the two missing scripts to match the other four pages as a stopgap. This was discovered 2026-08-08 while scoping [VEHICLE_LISTING_IMPLEMENTATION_PLAN.md](VEHICLE_LISTING_IMPLEMENTATION_PLAN.md) Step 1, which originally planned to remove `jquery-ui`/`jquery-ui-timepicker-addon` from `vehicles.php` as dead weight; that specific sub-change was skipped once the latent-dependency risk was found (before the `transactions.php` finding above, which shows the risk isn't purely hypothetical). Not fixed here — out of scope for the vehicle-listing phase, since it requires editing `js/app.js` and/or `transactions.php`, neither of which this step touches.

**Impact re-confirmed and expanded, 2026-08-13, during the Authentication phase's Step 6 Final Review:** all of Steps 1-5's new code (`AuthValidation`, `showAuthError()`, `requestPasswordReset()`/`resetPassword()`, and every login/signup/forgot-password submit handler) was added to `js/app.js` further down the file, after this crash point — confirmed live on `transactions.php`: `ReferenceError: AuthValidation is not defined` when attempting to use it, same root cause as the pre-existing navbar breakage above. This means real-time validation, the forgot-password flow, and all Authentication-phase client-side behavior are also silently non-functional on `transactions.php` today, not just navbar rendering. Not a regression introduced by the Authentication phase — every new symbol simply inherits the same pre-existing crash every other post-line-51 symbol in this file already had. Still not fixed here, for the same reason as before: the fix (guarding line 51, or adding the two missing script tags to `transactions.php`) touches neither `includes/auth_modals.php` nor the Authentication-phase-owned sections of `js/app.js`, and was never in scope for any of Steps 1-6.

**Code fix applied, 2026-08-14, Customer Dashboard phase, Step 1:** the two calls at `js/app.js:51-57` are now guarded with `typeof $.fn.datepicker === 'function'` / `typeof $.fn.timepicker === 'function'` checks, so they no longer throw on `transactions.php`. Live-tested: `renderNavbarAuth()` and `AuthValidation` both now run correctly on `transactions.php`.

**RESOLVED, 2026-08-19, Customer Dashboard phase, Step 7 (Final Review):** re-confirmed via `node --check js/app.js` (no syntax errors) and live browser testing across every subsequent Phase 7 step's functionality on `transactions.php` (navbar auth, profile forms, booking actions, dashboard alerts) — all ran without the previously-fatal crash. No regressions found across the full phase. Formally marked resolved together with item 10 per the implementation plan's Final Review convention.

---

### 12. ~~Confirmed dead code — `.category-btn` click handler, `js/app.js:371-397`~~ **RESOLVED (UI Implementation Plan, Phase 11 — Performance Review, 2026-08-21)**
```js
$(document).on('click', '.category-btn', function () {
```
Previously flagged only as "duplicated" (see Deprecated Code, and the now-superseded [COMPONENT_LIBRARY.md](COMPONENT_LIBRARY.md) note about `vehicles.php`/`app.js` both binding `.category-btn`). As of Step 7 of the Vehicle Listing Modernization ([VEHICLE_LISTING_IMPLEMENTATION_PLAN.md](VEHICLE_LISTING_IMPLEMENTATION_PLAN.md)), `vehicles.php`'s own copy of this handler and its `#categoryBar` markup were already removed in Step 5. A repo-wide grep for `category-btn` across every `.php` file in the codebase, done in Step 7, returned **zero matches** — no page has a `.category-btn` element anymore. This delegated `$(document).on(...)` handler therefore has no matching element anywhere and never fires; it is confirmed dead on every page that loads `app.js`, not just `vehicles.php`. Left in place (not deleted) at the time since `app.js` is shared and out of scope for the vehicle-listing phase; a guard comment was added directly above it in Step 7 documenting this finding.

**RESOLVED, 2026-08-21, UI Implementation Plan Phase 11 (Performance Review):** re-verified dead with a fresh repo-wide grep for `category-btn` (zero matches in any `.php` file, confirmed again). Since this phase's explicit mandate is unused-JavaScript cleanup — the "out of scope" reason the prior phase gave no longer applies — the 27-line handler (and its guard comment) was deleted outright from `js/app.js`. `node --check js/app.js` confirmed no syntax errors after removal, and `vehicles.php`/`index.php` were re-tested live with no console errors and no functional regressions (category filtering on `vehicles.php` is unaffected, since it already runs entirely through the server-side sidebar form, not this handler).

---

### 15. Server timezone (UTC) doesn't match the database's Manila clock, except on one page — found during Customer Dashboard phase, Step 5, 2026-08-19

`transactions.php`'s Step 5 (Notification Alerts) added `date_default_timezone_set('Asia/Manila')` near the top of that one file, needed so its `new DateTime()`/`time()` comparisons against `rental_date`/`created_at` line up with the actual wall-clock time the business operates in. No other PHP file in the codebase sets a timezone — every other page runs on whatever the server's default `date.timezone` is (confirmed UTC in this environment via `php -r "echo date_default_timezone_get();"`, unset in `php.ini`), while the MySQL server's own clock (and therefore every `TIMESTAMP`/`DATE` column written by `NOW()`/`CURRENT_TIMESTAMP`) is on Manila time (UTC+8).

**Severity: Medium.** Nothing observed to be currently broken by this — most of the codebase either does pure date-string comparison against `DATE` columns (which is timezone-agnostic, e.g. `transactions.php`'s own `$today = date('Y-m-d')` bucketing logic predates the Step 5 fix and works correctly regardless of PHP's timezone setting, since both sides of the comparison are calendar dates, not instants) or doesn't do time-of-day arithmetic at all. But any future code that computes an hours/minutes-precision delta against a `TIMESTAMP` column (the way Step 5's own reminder/confirmed/cancelled alert logic does) will silently be off by 8 hours if written on a page other than `transactions.php`, exactly the class of bug Step 5 had to route around locally. `register.php`'s `created_at` display, any future "time since" feature, and any future cron/scheduled-task timing would all inherit this latent mismatch.

**Not fixed here** — out of scope for a documentation/verification step; the fix (setting the timezone once, application-wide — e.g. in a shared bootstrap/config file that every entry point includes, rather than per-file) touches every PHP entry point and is a genuine architectural change, not a one-line guard. Flagged for a future phase.

**Update (Admin Dashboard phase, Step 7, 2026-08-20):** the new Business Overview panel on `admin-dashboard.php` (revenue-by-month, bookings-by-status, top-vehicles) was deliberately built with all date bucketing (`GROUP BY YEAR(created_at), MONTH(created_at)`, `DATE_SUB(CURDATE(), INTERVAL 6 MONTH)`) evaluated entirely inside MySQL, with no PHP-computed date boundary passed in — this was the specific design reason "Option A" was chosen over a date-range-filterable reports page ("Option B") for that step. Confirmed no `date()`/`strtotime()`/`DateTime` call constrains any of the three new queries. This item itself remains **unresolved** — any future admin-supplied date range (which Option B would have required) would still hit this mismatch and needs the application-wide fix described above.

---

### 16. `$_SESSION['user']['role']` is never populated at login — found during Customer Dashboard phase, Step 4 inspection, 2026-08-14; logged 2026-08-19

`login.php` builds `$_SESSION['user']` from the authenticated row but does not include the `role` column — confirmed by reading `login.php`'s session-assignment block, which sets `id`/`name`/`email` only. `me.php` returns `$_SESSION['user']['role'] ?? 'user'`, so every logged-in customer's role has always silently defaulted to `'user'` in any code that reads it from the session or from `me.php`'s response, regardless of the account's actual `users.role` value in the database.

**Severity: Low-Medium.** The customer-facing Profile section (Customer Dashboard phase, Step 4) displays this defaulted value in its "Role" field — confirmed live during this Final Review: a fresh test account (`role = 'user'` in the database, the only role value any self-registered account can have) correctly showed "user", so the defaulting happens to be unobservable for every account created through normal signup. The gap would only become visible if a `users.role` value other than `'user'` existed and that account's owner viewed their own Profile section — no such account was found in the current database, and no code path was found elsewhere in the codebase that branches on `$_SESSION['user']['role']` for an authorization decision (all actual admin/customer separation runs through the entirely separate `$_SESSION['admin_id']` session key, set by `admin-login.php`, not this one) — so this is a display-only gap today, not an authorization bypass.

**Not fixed here** — out of scope for a documentation/verification step; the fix is a one-line addition to `login.php`'s session-assignment block (`'role' => $user['role']`), but touching `login.php` was not part of any approved Phase 7 step's file list. Flagged for a future phase.

---

### 17. ~~Logout intercepted on `admin-dashboard.php` — session never destroyed~~ **RESOLVED (Admin Dashboard phase, Step 1, 2026-08-20)** — found during Step 1 inspection, `admin-dashboard.php:257-260` (original)

The sidebar's Logout link (`includes/admin_sidebar.php:39`, `<a href="logout.php" id="adminLogoutBtn">`) navigates to `logout.php`, which correctly calls `session_unset()` + `session_destroy()`. But `admin-dashboard.php` only, carried an inline handler:
```js
$('#adminLogoutBtn').on('click', function(e) {
  e.preventDefault();
  window.location.href = 'index.php';
});
```
This intercepted the click, called `e.preventDefault()`, and navigated to `index.php` instead — `logout.php` was never requested, so `$_SESSION['admin_id']` survived. The admin landed on the public homepage and appeared logged out, but a subsequent direct navigation to any admin page still granted full access. A commented-out predecessor of the same handler (lines 250-256, an older `localStorage`-based version) sat immediately above it. This handler existed on `admin-dashboard.php` only — the other four admin pages had no such handler and logged out correctly.

**RESOLVED:** both the live handler and its commented-out predecessor were deleted outright. With no JS intercepting the click, the anchor's `href="logout.php"` now navigates normally on all five admin pages. Verified live: logging out from `admin-dashboard.php`, then navigating directly back to `admin-dashboard.php`, now correctly redirects to `admin-login.php`.

### 18. ~~Unauthenticated visit to `view-all-data.php` dumps raw JSON instead of redirecting~~ **RESOLVED (Admin Dashboard phase, Step 1, 2026-08-20)** — found during Step 1 inspection, `view-all-data.php:7` (original)

Every other admin-gated page redirects an unauthenticated visitor to `admin-login.php`. `view-all-data.php` — an HTML page — instead did:
```php
if (!isset($_SESSION['admin_id'])) {
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}
```
An unauthenticated visitor was shown raw JSON text (`{"success":false,"error":"Unauthorized"}`) rather than a usable login form — the only admin page in the codebase with this specific failure mode (distinct from item 5, which is about `includes/header.php`'s guard pointing at the wrong file).

**RESOLVED:** replaced with `header('Location: admin-login.php'); exit;`, matching the guard pattern already used by the other four admin page files. Verified live: an unauthenticated visit to `view-all-data.php` now lands on `admin-login.php`.

### 19. ~~Recent Messages query loaded every row with no bound and no pagination~~ **RESOLVED (Admin Dashboard phase, Step 3, 2026-08-20)** — found during Step 3 inspection, flagged in [ADMIN_DASHBOARD_ANALYSIS.md](ADMIN_DASHBOARD_ANALYSIS.md) but not previously numbered here — `admin-dashboard.php` (original: `SELECT * FROM messages ORDER BY created_at DESC`, no `LIMIT`)

`admin-dashboard.php` is the only place messages are ever displayed in this codebase — there is no dedicated messages management page. The unbounded query rendered every row from the `messages` table into one flat HTML table with no pagination, which would grow unboundedly with the table.

**RESOLVED:** added `LIMIT 5`, and wrapped the table in DataTables (`$('#messagesTable').DataTable({ order: [[0, 'desc']] })`, the same configuration pattern already used on `#usersTable`/`#vehiclesTable`/`#transactionsTable`) so search and pagination replace the full-history access an admin loses by no longer seeing every message on one page — a `LIMIT` without that would have been a silent feature removal. Verified live: page renders `Show [10/25/50/100] entries`, `Search:`, and pagination controls; DataTables' default markup wraps the generated search input in a `<label>`, so it already carries an accessible name.

### 20. ~~Vehicle add/edit/delete success redirected to a page that never reads its query parameters~~ **RESOLVED (Admin Dashboard phase, Step 5, 2026-08-20)** — found during Step 5 inspection, flagged in [ADMIN_DASHBOARD_ANALYSIS.md](ADMIN_DASHBOARD_ANALYSIS.md) items 6, 26, 27 but not previously numbered here — `admin_add_vehicle.php:52`, `admin_edit_vehicle.php:79`, `admin_delete_vehicle.php:20` (original)

All three vehicle CRUD endpoints redirected to `admin-dashboard.php?success=1` / `?updated=1` / `?deleted=1` on success. `admin-dashboard.php` reads none of those parameters, so every successful add/edit/delete produced zero visible feedback anywhere and deposited the admin on a different page than the one they were working on — the already-written success/error alert markup on `admin_vehicles.php` was unreachable except on the error path (which already redirected back correctly). Additionally: the Add Vehicle modal's `details` textarea silently discarded its value (no `details` column exists on `vehicles`, and the `INSERT` never reads it); the delete confirmation didn't disclose that deleting a vehicle cascades to every booking made for it and every transaction on those bookings (`bookings_vehicle_fk`/`transactions_booking_fk`, both `ON DELETE CASCADE`); and the Edit modal's Category `<option>`s had no `value` attribute (relied on text content), while the Add modal's did — a latent desync risk if a display label ever changed.

**RESOLVED:** all three endpoints' redirect targets changed to `admin_vehicles.php`, with `admin_add_vehicle.php` sending `?added=1` to match a new alert branch added for it. The `details` textarea was removed from the Add modal rather than given a fake destination. The delete `confirm()` text now states the booking/transaction cascade plainly. The Edit modal's Category options now carry explicit `value` attributes matching the Add modal's exactly. No query, validation, or upload logic in any of the three endpoints was touched — see the Step 5 changelog entry for the full verification, including the `exif_imagetype()` regression check.

---

### 21. No automatic or admin-driven path to `status = 'completed'` — found during Admin Dashboard phase, Step 7, 2026-08-20

A `bookings` row only ever reaches `status = 'completed'` through one code path in the entire project: [return_early.php:65](../return_early.php), which runs when a **customer** clicks "Return Early" on their own `transactions.php` dashboard for a currently-active rental. Confirmed by grepping every `UPDATE bookings SET status = ...` in the codebase — the only three writers are `admin_confirm_booking.php` (`pending` → `confirmed`), `cancel_booking.php` (→ `cancelled`), and `return_early.php` (→ `completed`). No cron job, scheduled task, or admin-side "mark complete" action exists anywhere.

**Consequence:** a `confirmed` booking whose `return_date` has already passed stays `confirmed` indefinitely unless the customer happens to click Return Early — there is no way for it to become `completed` on its own, and no admin control to force it. `transactions.php`'s own "Active" vs. "Completed" bucketing (lines 40-56) is driven by actual `status`, not by whether `return_date` has passed, so an overdue-but-never-returned booking still displays as "Active" to the customer and as `confirmed` (not `completed`) everywhere on the admin side, including the new Business Overview panel's Revenue by Month and Bookings by Status figures on `admin-dashboard.php` (Step 7) — both correctly reflect this real data, not a display bug in that panel. Verified live: the current database has 10 `confirmed` and 4 `cancelled` bookings and **zero** `completed` ones, consistent with no customer in this dataset ever having used Return Early.

**Severity: Low-Medium.** Nothing is silently lost — the booking, its transaction, and its inventory decrement all remain correct and intact. But the admin has no way to close out a rental once it is actually returned in person (as opposed to via the self-service Return Early flow), and "Completed Rentals" as a metric (both on the customer dashboard and in this Step's new Bookings-by-Status panel) will under-report indefinitely on any installation where customers don't self-service their returns.

**Not fixed here** — this is a business-rule decision (should an admin be able to manually mark a booking completed? should overdue `confirmed` bookings auto-complete on a schedule, and if so is that a cron job or a lazy check-on-read?), not a UI defect, and squarely the kind of change [CLAUDE.md](../CLAUDE.md) requires separate explicit approval for. Flagged for a future phase.

---

### 22. ~~`badge bg-info` white-on-cyan contrast failure — `admin_users.php:83`~~ **RESOLVED (Admin Dashboard phase, Step 9, 2026-08-20)** — found and fixed during the Step 9 accessibility sweep

The Role column's `<span class="badge bg-info">` renders Bootstrap's default white badge text on `#0dcaf0` (light cyan). Measured live against the real rendered element: **1.96:1** — well below WCAG AA's 4.5:1 minimum for normal text, and unlike the `bg-primary`/`bg-success`/`bg-danger`/`bg-secondary` badges elsewhere in the admin section (all 4.5:1+, see the corrected note above), this one was not merely at-threshold, it was a clear failure that had gone unmeasured until this step.

**RESOLVED:** added `text-dark`, re-measured live at **7.88:1**.

### 23. ~~Four "Edit" CRUD modals never returned keyboard focus to their trigger button~~ **RESOLVED (Admin Dashboard phase, Step 9, 2026-08-20)** — found during Step 9's live keyboard-navigation test, `js/admin.js` (original)

`editUserModal`, `editVehicleModal`, `editVoucherModal`, and `editTransactionModal` are all opened via `bootstrap.Modal.getOrCreateInstance(el).show()` from a row button's jQuery click handler, not via a native `data-bs-toggle="modal"` attribute. Bootstrap 5 only auto-restores focus to the element that opened a modal when it tracked that element itself (via `data-bs-toggle`/`relatedTarget`) — a modal opened purely from JS has no such record, so on close, focus simply stayed wherever it last was: the modal's own now-hidden first input. Confirmed live: open `admin_users.php`'s Edit modal by clicking a row's Edit button, press <kbd>Escape</kbd>, inspect `document.activeElement` — it remained `#editUserName` (invisible, inside the closed modal), not the Edit button that opened it. A keyboard-only user closing any of these four modals lost their place entirely and had to re-locate the page with Tab from scratch. `confirmAction()`'s own shared confirmation modal (used for every Delete/Confirm action) did **not** have this bug — it already explicitly restores focus to `$trigger` in its `hidden.bs.modal.adminConfirm` handler, added when it was built (Step 6).

**RESOLVED:** added a shared `restoreFocusOnHide($modal, $trigger)` helper to `js/admin.js`, mirroring `confirmAction()`'s existing pattern, called at each of the four modals' open sites with the button that triggered them. Verified live on two of the four (`admin_users.php` Edit modal, `admin_vehicles.php` Edit modal): focus now correctly lands back on the originating row button (confirmed via `document.activeElement.getAttribute('aria-label')`) after closing via Escape. `admin_vouchers.php` and `view-all-data.php`'s Edit modals use the identical helper call and were not independently re-tested live, only confirmed by code inspection.

### 25. ~~Admin table row-action buttons and DataTables pagination links below the 44×44px touch-target minimum~~ **RESOLVED (Admin Dashboard phase, Step 9, 2026-08-20)** — flagged but deliberately deferred across Steps 4-6, not previously numbered here

Every admin table's row-action button (`.edit-user`/`.delete-user`, `.editVehicleBtn`/`.delete-vehicle`, `.edit-voucher`/`.delete-voucher`, and the Confirm/Delete/Edit buttons on `view-all-data.php`) used Bootstrap's `.btn-sm` sizing, which live measurement in Step 9 found rendered as small as 27-29px tall — some icon-only ones as narrow as 28px wide — below the 44×44px WCAG/mobile touch-target guidance. DataTables' generated pagination `.page-link` measured 37px tall, also under the minimum. Steps 4, 5, and 6 each individually confirmed this gap on the table they were touching and explicitly deferred it rather than fixing it piecemeal per-table (see each step's changelog entry), reasoning that `.btn-sm` is the established convention across every admin action button on every admin page and resizing it required a single, deliberate, project-wide decision rather than a scattered one.

**RESOLVED:** a scoped `min-height`/`min-width: 44px` + flex-centering rule was added on `#adminLayout .btn-sm` and `#adminLayout .page-link` in `css/styles.css` — scoped to the admin shell (confirmed `.btn-sm` unused on all six customer pages by grep before this change) rather than editing the pre-existing unscoped `.btn-sm` rule, so the scoping documents the intent explicitly instead of relying on an accidental non-collision holding forever. Re-measured live after the fix: every button and pagination link above now reports 44×44px or larger.

### 24. ~~`index.php` horizontal overflow at desktop width~~ **RESOLVED — confirmed a false positive (UI Implementation Plan, Phase 9, 2026-08-21)** — found incidentally during Admin Dashboard phase, Step 9's customer-regression check, 2026-08-20

While confirming Step 9's admin-scoped CSS changes left the six customer pages unaffected, `document.body.scrollWidth` (1590px) was found to exceed the viewport (1280px) on `index.php`. The offending elements, confirmed live via `getBoundingClientRect()`: `.feature-card`, `.glassmorph`, `.step-badge`, and a nearby `<h5>` inside the "How It Works" section all extend past the right edge of the viewport. **Confirmed unrelated to this step** — none of these classes were touched by any Step 9 change (all of Step 9's CSS additions are scoped to `#adminLayout`, which does not exist on `index.php`), and the same overflow would reproduce on an unmodified copy of the file. **Not fixed here** — customer-facing, unrelated to the admin accessibility pass this step is scoped to. Flagged for a separate task.

**Update (System Enhancements initiative, Step 5, 2026-08-21):** `.glassmorph` no longer exists on this page (removed — see that step's CHANGELOG entry), so this item's element list is now stale by one class. **Update (System Enhancements initiative, Step 9, 2026-08-21):** a *separate* investigation (below, item 26's sibling finding, not a fix for this item) found that `.feature-card`'s three children — which carry `animate__fadeInLeft`/`fadeInUp`/`fadeInRight` — sit at `transform: translateX(±380px)` / `opacity: 0` (their pre-reveal state) whenever the page is inspected via an automated tool session that never triggers `js/motion.js`'s `data-reveal` IntersectionObserver (no real user scroll occurs). This reproduces the same class of horizontal-overflow symptom this item describes and may be this item's actual root cause rather than a genuine layout bug — **not confirmed either way**, since this item was originally found via a different method (Step 9 Admin Dashboard phase) than the one that surfaced the animation-state explanation (a non-compositing browser-tool session, per this document's item 26 investigation notes and the System Enhancements CHANGELOG's Step 5/Step 9 entries). Still not fixed here; still flagged for a separate task, but with this connection recorded so a future investigation doesn't have to rediscover it.

**RESOLVED, 2026-08-21, UI Implementation Plan Phase 9 (Responsive Review) — confirmed a false positive, not a real bug:** re-measured live at 320/375/768/992/1200px with a throwaway `animation:none!important; transition:none!important` style injected first (the same neutralization technique the Step 11 changelog entry used) and every `[data-reveal]` element force-given its `.is-visible` class. With the non-compositing artifact removed, `index.php` measures `document.documentElement.scrollWidth === clientWidth` (zero overflow) at all five breakpoints, confirming the Step 9 finding's root cause genuinely was CSS keyframe animations stalled at their pre-reveal frame in a background/non-compositing browser-tool tab — never a real layout defect. Also directly confirmed live: `.feature-card`'s `top` position (~1198px) sits well inside the initial viewport at common device heights, and Animate.css's entrance animation (1s duration, unrelated to scroll position) completes long before a real user scrolls that far, so this was never reachable by an actual visitor even before considering the compositing artifact.

---

### 26. ~~`receipt.php` prints a blank page — no `#receiptModal` element on the page the print CSS assumes exists~~ **RESOLVED (UI Implementation Plan, Phase 12 — Final UI Review, 2026-08-22)** — found during System Enhancements initiative, Step 9, 2026-08-21, while verifying "receipt.php print output in both themes" per that step's plan; **severity had since escalated site-wide — see the resolution note below**

`css/styles.css`'s `@media print` block (`.modal-header`'s dark-mode-corrections section neighbours it) is written for exactly one printable surface across the whole project:
```css
@media print {
  body * { visibility: hidden; }
  #receiptModal, #receiptModal * { visibility: visible; }
  ...
}
```
This is unscoped to any particular page — it's in the shared stylesheet and applies to print on every page. `#receiptModal` is a modal built dynamically by `js/printer.js`'s `showReceiptModal()`, used elsewhere in the booking flow. **`receipt.php` — the standalone receipt page reached via `receipt.php?id=`/`?ref=`, and the only page whose entire purpose is to be printed — does not include `js/printer.js` and never creates a `#receiptModal` element**, confirmed live (`document.getElementById('receiptModal')` returns `null` on this page). Since the print rule's first line unconditionally hides everything (`body * { visibility: hidden; }`) and nothing on this page ever satisfies the second line's un-hide condition, printing `receipt.php` directly via the browser (Ctrl+P, or any print affordance a user might use) produces a **completely blank printed page**.

**Confirmed unrelated to dark mode or any System Enhancements initiative change** — `receipt.php` has never included `js/printer.js`, and this print rule predates the initiative; it was found only because Step 9's plan explicitly named "receipt.php print output verified in both themes" as a testing requirement, which prompted checking print behaviour directly rather than assuming it worked. **Not fixed here** — this is a functional print-scope gap, not a contrast issue, and deciding the right fix (give `receipt.php` its own print-visibility rule scoped to `.receipt-card`, versus unifying it to also use `#receiptModal`, versus adding a dedicated print button) is a design decision outside a contrast-audit step's mandate. Flagged for a separate task.

**Severity escalated between logging and fixing — this entry understated the blast radius.** When this item was written, `#receiptModal` still existed as a runtime construct: `js/printer.js`'s `showReceiptModal()` built it, so on any page that loaded `printer.js` the un-hide condition *could* be satisfied and printing worked; `receipt.php` was the one page where it could not. **Phase 11 then deleted `js/printer.js` as confirmed dead** (item 37) and deliberately left this print block untouched precisely because it was tracked here — a reasonable call in isolation, but the combination meant that from that point **no element named `#receiptModal` could exist on any page at all**, while `body * { visibility: hidden }` still applied unconditionally to every page in the shared stylesheet. Net effect at the start of Phase 12: **pressing Ctrl+P on any page of the application — home, vehicles, transactions, receipt, every admin page — produced a completely blank sheet.** Re-verified before fixing: repo-wide grep found zero `#receiptModal` and zero `.btn-print` in any `.php`, `.js`, or `.css` file (the only surviving `receiptModal` token is a local JS *variable* name in `transactions.php:604` holding a `bootstrap.Modal` instance of the unrelated `#returnReceiptModal` — not an element id).

**RESOLVED, 2026-08-22, UI Implementation Plan Phase 12 (Final UI Review):**
- The five dead `#receiptModal .modal-*` rulesets (~30 lines) were deleted outright.
- The `@media print` block was re-scoped to the one surface whose actual purpose is to be printed: `receipt.php`'s existing `.receipt-card`, gated behind a new `body.receipt-page` class so the page-wide hide **cannot leak onto a page that has no printable card**. Every other page now prints its normal content instead of nothing.
- `receipt.php:156`'s `<body>` gained `class="receipt-page"` (the only markup change; no ids, routes, or PHP logic touched).
- `.btn-print` was dropped from the hidden-controls list (no element anywhere carries that class — it belonged to `js/printer.js`). The receipt's own `.card-footer` navigation links, which sit *inside* `.receipt-card` and are therefore un-hidden by the new rule, get an explicit `display: none` opt-out instead — they are meaningless on paper.

Verified live on `receipt.php?id=71` (a real booking, logged-in session): the print selectors now match **226 elements hidden / 54 un-hidden (the receipt card and its descendants) / 1 card-footer excluded** — where the pre-fix rule matched 0 un-hidden elements. Confirmed on `vehicles.php` that `document.styleSheets` now exposes exactly one `@media print` rule and every selector in it is `body.receipt-page`-scoped, so that page (which has no such class) is no longer affected by any print rule at all. Brace balance and comment balance re-verified across the whole stylesheet after the edit (163/163 braces, 92/92 comment delimiters).

**Deliberately not done:** no print *button* was added to `receipt.php`. That is a feature decision, not a defect fix — the page prints correctly via the browser's own Ctrl+P / print menu now, which is what the original rule was written to support.

---

### 27. ~~`.row`/`.container` gutter produces a 4px horizontal overflow at 320px width~~ **RESOLVED (UI Implementation Plan, Phase 9 — Responsive Review, 2026-08-21)** — found during System Enhancements initiative, Step 11, 2026-08-21, while sweeping all 14 pages for overflow at every breakpoint

`about.php`, `vehicles.php`, and `privacy.php` (all three share the same simple `.container > .row > .col-12` page-header shell) measure `scrollWidth: 324` against `clientWidth: 320` at exactly 320px viewport width — a 4px horizontal overflow. **Confirmed pre-existing and unrelated to any System Enhancements change**: reproduced identically with `data-bs-theme="light"` (theme has no effect, as expected — this is a layout artifact, not a colour one), and the pattern is Bootstrap's own well-documented `.row`'s negative gutter margins (`-0.75rem` each side) not being fully absorbed by `.container`'s padding at the narrowest supported viewport. **Not fixed here** — out of this initiative's explicitly bounded scope (`SYSTEM_ENHANCEMENTS_IMPLEMENTATION_PLAN.md` Step 11: "anything found outside this initiative's changes is logged in BUGS.md, not fixed here"), and it's the same category of pre-existing Bootstrap-grid issue as item 24. 4px is below the threshold that produces a visible scrollbar on most mobile browsers (which hide the scrollbar or rubber-band instead), so this is low severity.

**Root cause found and RESOLVED, 2026-08-21, UI Implementation Plan Phase 9 (Responsive Review):** live inspection (`getComputedStyle` on `main.container` and its direct `.row` child at 320px) found the actual mechanism was more specific than "Bootstrap's grid math not being absorbed" — a pre-existing custom rule at [css/styles.css](../css/styles.css) (inside the `@media (max-width: 991.98px)` "Custom mobile adjustments" block) reduced `main.container`/`.container-lg`'s own padding to `0.5rem` (8px) `!important` on mobile, while the *un-touched* `.row` immediately inside it still carries Bootstrap's default `-0.75rem` (-12px) negative margin — an 8px-vs-12px mismatch that overflows by exactly the difference (4-5px, matching the reported measurement) on every page using this container/row combination, not just the three originally sampled. **Fix:** changed the override's padding values from `0.5rem` to `0.75rem` (Bootstrap's own default container gutter, `var(--bs-gutter-x) * 0.5`), which exactly cancels `.row`'s default `-0.75rem` margin regardless of what gutter-utility class (`g-2`/`g-3`/`g-4`, or none) any nested `.row` uses — a one-line-per-property change reusing an existing Bootstrap token instead of inventing a new custom property or touching the grid/row markup on any page. Verified live at 320/375/768px on all three originally-affected pages (`about.php`, `vehicles.php`, `privacy.php`) plus `index.php`/`faq.php`/`transactions.php` as a regression check: `scrollWidth === clientWidth` on every one, zero offending elements. 992px/1200px were already unaffected (outside this media query) and re-confirmed unchanged.

### 28. ~~Bootstrap's default `.btn-close` (32×32px) is below the project's 44×44px touch-target standard~~ **RESOLVED (UI Implementation Plan, Phase 12 — Final UI Review, 2026-08-22)** — found during System Enhancements initiative, Step 11, 2026-08-21, while measuring every confirmation-modal button per that step's testing requirement

The shared `js/confirm.js` confirmation modal's header dismiss button (`<button class="btn-close">`, added in Step 1) measures 32×32px live, below the 44×44px minimum item 25 already established as this project's standard. **Confirmed sitewide and pre-existing**, not specific to the new confirmation modal: the identical unmodified `class="btn-close"` markup, with no size override anywhere in `css/styles.css`, is used in all three pre-existing auth modals (`includes/auth_modals.php:7,43,107`) and every other modal across the project. **Not fixed here** — enlarging it only on the new confirmation modal would create a visibly inconsistent close-button size across the site's modals (worse than the current uniform-but-undersized state); fixing it project-wide is a larger design-system change than a single confirmation-modal step should absorb, especially since every confirmation modal also has a full 44px-tall, clearly labelled "Cancel" button immediately available as the primary, unambiguous dismiss action — the X is a redundant convenience, not the only path. Flagged for a future dedicated pass if a uniform, larger close button is wanted across all modals.

**RESOLVED, 2026-08-22, UI Implementation Plan Phase 12 (Final UI Review)** — this *is* that dedicated pass, so the "one modal only would be inconsistent" objection no longer applies: the fix is applied once, globally, to every `.btn-close` on every modal, offcanvas header and dismissible alert, client and admin alike. One rule added to [css/styles.css](../css/styles.css), directly below Phase 9's `.btn:not(.btn-sm):not(.btn-close)` rule (whose exclusion comment was updated to point here rather than to a deferral):

```css
.btn-close {
  box-sizing: border-box;
  width: 44px;
  height: 44px;
  padding: 0;
  flex-shrink: 0;
  background-position: center;
}
```

**Two of those five declarations are load-bearing in non-obvious ways, both found by measuring rather than assuming:**
1. **`box-sizing: border-box`** — Bootstrap ships `.btn-close` as `box-sizing: content-box` with `padding: .25em`. Setting `width/height: 44px` alone produced a **60×60px** box (44 + 8 + 8) and inflated every modal header from ~56px to ~77px. Measured live before the override was added. `padding: 0` accompanies it so the declared 44px is the true outer size.
2. **`flex-shrink: 0`** — `admin_vehicles.php`, `admin_vouchers.php` and `admin_users.php` place their in-modal validation alert's `.btn-close` inside a `d-flex justify-content-between align-items-start` row beside the message text. As a flex item with the default `flex-shrink: 1`, the button was compressed to **22px wide** at 320px *despite* the explicit `width: 44px` — i.e. the width declaration alone did not deliver 44px in the one context where the button is a flex child. Pinning `flex-shrink: 0` makes the text span absorb the squeeze instead (measured 236.7px → 214.7px, still wrapping cleanly), which is the correct priority.

Only the box changes — `background-position: center` keeps Bootstrap's own `.75em` icon at its original size, visually centred, so the glyph, colour, hover/focus states and dismiss behaviour are all untouched.

**Verified live at 320px and 1280px**, measuring the computed box of every `.btn-close` in each distinct context the codebase actually uses:

| Context | Before | After | Checks |
|---|---|---|---|
| `#loginModal` / `#signupModal` header (client) | 32×32 | **44×44** | inside header, no title overlap, within modal-content |
| `#bookingModal` / `#vehicleDetailsModal` header (client) | 32×32 | **44×44** | same |
| `#returnEarlyModal` / `#cancelBookingModal` / `#returnReceiptModal` (`transactions.php`) | 32×32 | **44×44** | same, at 320px |
| `#filterSidebar` offcanvas header (`vehicles.php`) | 32×32 | **44×44** | same |
| `.alert-dismissible` (synthetic, matching `admin_vehicles.php`/`transactions.php` markup) | 32×32 | **44×44** | inside alert bounds, no text overlap |
| `.js-modal-alert-close` in a flex row (admin CRUD modals) | 32×32, **22px wide** under flex | **44×44** | inside alert, no text overlap |
| `includes/admin_sidebar.php` offcanvas header | 32×32 | **44×44** | inside header |

Modal header height settles at 62.7px, identical across all four client modals (consistent, not per-modal drift). Zero horizontal overflow introduced at 320px on `vehicles.php`, `transactions.php` or `receipt.php`; zero console errors on any client page after the change.

**Admin-surface verification method, stated plainly:** the admin pages could not be driven live in this session (they require `admin-login.php` credentials that were not available, and guessing them was not appropriate). The two admin-only `.btn-close` contexts — the `js-modal-alert` flex row and the sidebar offcanvas header — were instead verified by injecting their **verbatim markup, copied from `admin_vehicles.php` and `includes/admin_sidebar.php`**, into a page that loads the same shared `css/styles.css` and the same Bootstrap build, then measuring. That is what surfaced the `flex-shrink` gap, which a modal-header-only check would have missed entirely. The measurement is of the real CSS against the real markup; only the surrounding page differs.

### 29. ~~`receipt.php`'s "missing booking identifier" guard clause renders a bare, unstyled HTML fragment~~ **RESOLVED (UI Implementation Plan, Phase 12 — Final UI Review, 2026-08-22)** — found during System Enhancements initiative, Step 11, 2026-08-21, while sweeping page overflow at 320px

`receipt.php:15-19` returns `http_response_code(400); echo "<p>Missing booking identifier.</p>"; exit;` with no `<head>`, no viewport meta tag, no stylesheet, and no dark-theme attribute — confirmed live (`document.documentElement.outerHTML` shows exactly `<html><head></head><body><p>Missing booking identifier.</p></body></html>`). Because there's no viewport meta tag, mobile browsers render it at their default desktop-width fallback (measured `clientWidth: 980` in this session) rather than the actual device width. **Not a real user-facing regression**: this guard clause is only reached when `receipt.php` is loaded without its required `?id=`/`?ref=` query parameter, which normal application flow never does (every real caller supplies one) — it was only triggered here by testing the URL directly with no parameters, the same way a raw API error response isn't expected to be a themed page. **Not fixed here** — it's a backend response-shape decision (whether this guard should return a themed error page or stay a minimal text response, like `register.php`'s JSON error responses do) outside a front-end/UI initiative's scope per this project's CLAUDE.md. Flagged for consideration if `receipt.php` is revisited.

**RESOLVED, 2026-08-22, UI Implementation Plan Phase 12 (Final UI Review).** Re-classified as in scope on inspection: the deciding question is not "what response shape should this endpoint have" but "what does `receipt.php` render". `receipt.php` is a *page*, not a JSON endpoint — it has no JSON path at all, and both guards already returned HTML (`<p>…</p>`), just unstyled HTML. So this was always a markup defect on a user-facing page, which is squarely a front-end concern; the original deferral drew the scope line in the wrong place. The fix is markup-only.

Both guard clauses (the `!$id && !$ref` 400 and the `!$booking` 404) now call a small local `receipt_error_page($title, $message, $status)` helper that emits a complete document: `<!doctype html>`, `lang="en"`, `<meta charset>`, `<meta name="viewport">`, Bootstrap 5.3.2 + Font Awesome + `css/styles.css`, and the same inline no-flash `data-bs-theme` bootstrap IIFE every other page in the project carries. The body is a centred `.card` reusing the project's existing card/heading/`.text-body-secondary`/`.btn-primary`/`.btn-outline-secondary` conventions — no new components, no new colours — plus two recovery links ("View My Bookings" → `transactions.php`, "Browse Vehicles" → `vehicles.php`) so the page is a navigable dead end rather than an absolute one.

**Behaviour explicitly preserved, verified by fetching each case with `redirect: 'manual'` from an authenticated browser session:** `receipt.php` (no params) → **400**, `receipt.php?ref=NOPE` → **404**, `receipt.php?id=71` (valid) → **200** and renders the real receipt unchanged. The login guard still runs first and still `header('Location: login.php')`-redirects (confirmed 302 via `curl` with no session), so the guard ordering is untouched. No query, lookup, status-code or redirect behaviour changed — only what is echoed.

Verified live at 320px in dark mode: themed (`background: rgb(15,23,42)`, honouring the stored theme), `clientWidth` now tracks the real device width instead of the ~980px desktop fallback (the missing viewport meta tag was the cause), `scrollWidth === clientWidth` (zero overflow), and `<h1>` present. Also confirmed the new `@media print` scope (item 26) correctly does **not** apply here — the error page carries no `.receipt-page` class, which is right: there is no receipt to print.

---

### 30. ~~A second, larger abandoned dark-mode attempt existed beyond the `--chart-*` tokens~~ **RESOLVED (System Enhancements initiative, Step 8a, 2026-08-21)** — was `css/styles.css:319-334, 527-530, 576-604`, `faq.php:105` (original) — first identified as Additional Finding A1 in [SYSTEM_ENHANCEMENTS_ANALYSIS.md](SYSTEM_ENHANCEMENTS_ANALYSIS.md) §9

A 34-line `oklch`-based `.dark { ... }` block, ~40+ `body.dark` rules across four separate blocks, a fully-styled `.btn-darkmode-toggle` with no corresponding markup anywhere in the project, and an orphaned `toggleDarkMode()` function on `faq.php` that nothing called — all leftovers from an earlier, abandoned dark-mode attempt that predated this initiative's actual dark-mode implementation (Steps 8-10). **Resolved:** all of it deleted in Step 8a before the real dark-mode token system and toggle were built, so the new implementation started from a clean baseline rather than layering on top of dead code. Verified via grep (see Step 12's verification sweep): zero `body.dark`, zero bare `.dark`, zero `toggleDarkMode` remain anywhere in the codebase outside historical comments referencing the removal.

### 31. ~~`.text-muted` measured 4.48:1 against `--background` — a WCAG AA failure, site-wide (72 instances)~~ **RESOLVED (UI Implementation Plan, Phase 12 — Final UI Review, 2026-08-22) — verified passing, no code change required** — found during System Enhancements initiative's analysis phase, 2026-08-21, logged per Additional Finding A3 in [SYSTEM_ENHANCEMENTS_ANALYSIS.md](SYSTEM_ENHANCEMENTS_ANALYSIS.md) §9

Bare `.text-muted` sitting directly on the page's `--background` token (not on an intervening white/`.glassmorph` card) measured 4.48:1 in light mode — just under WCAG AA's 4.5:1 normal-text minimum. This is a **pre-existing accessibility defect, independent of any of this initiative's five requested features** — it was true before dark mode, glassmorphism removal, or any other Step touched the codebase. **Not fixed here**: Step 8a's bulk migration renamed all 72 `.text-muted` instances to `.text-body-secondary` for dark-mode-token reasons (Bootstrap's theme-relative secondary-text utility), but that was a class-name/mechanism change for dark-mode compatibility, not a colour audit — it was never verified whether `.text-body-secondary`'s light-mode computed colour actually clears 4.5:1 against `--background` in the specific contexts where the original failure was measured. Flagged for a dedicated light-mode contrast pass; do not assume the Step 8a rename silently fixed it.

**RESOLVED, 2026-08-22, UI Implementation Plan Phase 12 (Final UI Review) — this is that dedicated light-mode contrast pass, and it closes the item by measurement, not by assumption.** Both halves of the concern were checked directly and both clear AA:

1. **`.text-body-secondary` (the 72 renamed instances).** It resolves to Bootstrap's own `--bs-secondary-color`, `rgba(33, 37, 41, 0.75)` — a *translucent* colour, which is why earlier by-eye estimates were unreliable. Composited properly against each element's real effective background: **15.43:1** on the white card surfaces where 16 of the 20 instances on `index.php` sit, and comfortably above 4.5:1 on `--background` (`#F8FAFC`) for the rest. Passes. (An initial scan reported 1.07:1 for four of them — that was an alpha-compositing bug in the scanner, treating `rgba(…, .75)` as opaque; corrected before drawing any conclusion.)
2. **`--muted-foreground: #718096`, the token the item warned about specifically.** `#718096` on `#F8FAFC` genuinely does measure only **3.83:1** — the item's suspicion was arithmetically correct. But it is moot in practice: a grep of every `var(--muted-foreground)` consumer in [css/styles.css](../css/styles.css) found **all five are scoped to `[data-bs-theme="dark"]`** (`.navbar .nav-link`, `.vehicle-pricebar small`, `.btn-outline-secondary`'s `--bs-btn-color`/`--bs-btn-border-color`, `.table th`). The light-mode value of this token is **applied by nothing** — it is a dark-mode-only token whose `:root` declaration is effectively a default that never wins. In dark mode, where it *is* used, it measures 8.05:1. So there is no light-mode surface bearing this colour, and nothing to fix.

**Method (so this can be re-run rather than re-argued):** an automated scanner walked every element with a non-empty text node on each page, skipped `display:none`/`visibility:hidden`/`opacity:0`/zero-box elements, composited the full ancestor background stack *and* the foreground alpha before computing the ratio, and applied WCAG 2.1's large-text threshold (≥24px, or ≥18.66px bold → 3:1) rather than a flat 4.5:1. Run per page on a clean load in each theme — **not** by flipping `data-bs-theme` in-page, which was tried first and produced false results (computed styles lag the attribute flip mid-transition, so dark token values leak into a nominally "light" reading).

**Result across the client surface: `index.php`, `vehicles.php`, `about.php`, `faq.php`, `transactions.php`, `receipt.php` in both themes — zero `.text-body-secondary` or `--muted-foreground` failures.** Two *unrelated* light-mode failures were found by the same sweep and are logged separately as **item 41** below (they are brand-colour decisions, not this item's muted-text concern), and one dark-mode failure logged as **item 39**.

### 32. ~~Two handlers were bound to `#btnPreview`, and `vehicles.php`'s render path was a silent no-op~~ **RESOLVED (System Enhancements initiative, Step 2, 2026-08-21)** — was `js/app.js:125` vs `vehicles.php:872` (original) — first identified as Additional Finding A5 in [SYSTEM_ENHANCEMENTS_ANALYSIS.md](SYSTEM_ENHANCEMENTS_ANALYSIS.md) §9

Handler A (`js/app.js`) replaced `#bookingPreview`'s entire inner HTML, destroying `#previewContent`; Handler B (`vehicles.php`'s standalone `#btnPreview` click handler) then wrote to `#previewContent`, matching zero elements — a proven dead code path, distinct from (though related to) item 3/item 6's already-documented duplicate-binding issue. **Resolved:** Handler B and the `#btnPreview` button itself were deleted in Step 2 as part of Feature 5 (see [CHANGELOG.md](../CHANGELOG.md) Step 2 entry); pricing now comes solely from `js/app.js`'s `window.refreshBookingPreview()`.

### 33. ~~`logoutUser()` does not await the response before reloading~~ **RESOLVED (UI Implementation Plan, Phase 12 — Final UI Review, 2026-08-22)** — found during System Enhancements initiative's analysis phase, 2026-08-21, logged per Additional Finding A8 in [SYSTEM_ENHANCEMENTS_ANALYSIS.md](SYSTEM_ENHANCEMENTS_ANALYSIS.md) §9

`js/app.js:988-991`'s `logoutUser()` calls `await fetch('logout.php'); window.location.reload();` — `fetch`'s promise resolves once response *headers* arrive, not once the server has necessarily finished tearing down the session. A sufficiently slow `logout.php` could see the page reload race the session teardown, potentially reloading into a still-partially-authenticated state. **Not fixed here**: Step 3 added a confirmation dialog (G1) in front of this same call, which is a different, already-completed piece of work (confirming the user *wants* to log out) — the await-ordering issue is a separate correctness question about the fetch itself, not a confirmation gap, and was explicitly out of this initiative's five requested features. Flagged for a dedicated fix (the straightforward correction is awaiting `response.ok`/a parsed body before reloading, rather than just the fetch promise).

**RESOLVED, 2026-08-22, UI Implementation Plan Phase 12 (Final UI Review)** — the flagged correction, applied as described:

```js
async function logoutUser() {
  try {
    const res = await fetch('logout.php');
    await res.text(); // drain the body: guarantees the request completed
  } catch (err) {
    console.error('Logout request failed', err);
  }
  window.location.reload();
}
```

Draining the body via `.text()` is what makes the `await` mean what the original code assumed — that promise settles only once the response is fully received, whereas `await fetch(...)` alone settles at headers. The `try/catch` is deliberate and is *not* a swallowed error: the reload must still run even if the request fails, because leaving the user on a page that still renders logged-in chrome after they clicked Log Out is a worse outcome than a failed teardown; the failure is surfaced to the console rather than silently dropped.

**Verified live** on `faq.php` with a real authenticated session, exercising the new call shape without reloading: `me.php` reported `logged_in: true` before, `logout.php` returned **200**, and `me.php` reported `logged_in: false` after — session teardown confirmed. Notably the response body measured **32,185 bytes** (`logout.php` redirects, and `fetch` follows it, so the body is a full HTML page): a 32KB body is exactly the case where headers arrive materially before the response completes, which confirms the race this item described was real and not theoretical. Zero console errors on any client page after the change; `node --check js/app.js` clean.

### 34. ~~Client-side default-sized `.btn`/`.page-link` controls below the 44×44px touch-target minimum~~ **RESOLVED (UI Implementation Plan, Phase 9 — Responsive Review, 2026-08-21)** — found while sweeping touch targets across all customer-facing pages

Item 25 fixed this exact class of gap for the admin side (`#adminLayout .btn-sm`/`.page-link`, both ~37px live) but was deliberately scoped to `.btn-sm` only, "confirmed `.btn-sm` is not used anywhere on the six customer-facing pages." That scoping was correct as far as it went, but it left the client side's own default-sized button convention — plain `.btn` (customer pages don't use `.btn-sm` at all, they use unmodified default-size Bootstrap buttons) — unaudited. Measured live on `vehicles.php`/`index.php`: `.btn-view-details` ("View Car Details"), `#btnReserveFromDetails` ("Reserve Now" inside `#vehicleDetailsModal`), the filter-sidebar's `d-lg-none` "Filters" toggle button, `#loginModal`'s submit button, and pagination `.page-link` all measured **37px tall** live — the same Bootstrap default-padding root cause as item 25, just never checked outside `#adminLayout`.

**RESOLVED:** added a client-side-scoped rule to [css/styles.css](../css/styles.css), directly below `#adminLayout .page-link`, mirroring item 25's exact technique (`min-height: 44px; display: inline-flex; align-items: center; justify-content: center;`) on `.btn:not(.btn-sm):not(.btn-close)` (excludes `.btn-sm`, already covered by item 25's admin-scoped rule, and `.btn-close`, item 28's documented, deliberately-unchanged exception) plus a general `.page-link` rule (client pages' own pagination, `vehicles.php`, was not covered by item 25's `#adminLayout`-scoped version). Verified live: `.btn-view-details`, the Filters toggle, `.page-link`, and `#loginModal`'s submit button all now measure 44px; `.btn-close` (32px, item 28) and admin `.btn-sm` row-action buttons (44px via item 25's pre-existing rule) are unaffected, confirmed by re-reading their computed height after the change. No overflow regression at 320/375/768/992/1200px on `index.php`/`vehicles.php` (re-swept after the change). Not scoped to a breakpoint (matches item 25's own unconditional approach — touch input isn't exclusive to narrow viewports).

---

### 35. ~~`about.php`, `faq.php`, `privacy.php` each loaded jQuery 3.7.1 twice~~ **RESOLVED (UI Implementation Plan, Phase 11 — Performance Review, 2026-08-21)**

The same "duplicate jQuery load" bug already fixed for `vehicles.php` (see Resolved — Step 1, Vehicle Listing Modernization) was still present, undocumented, on three other pages: each of `about.php`, `faq.php`, and `privacy.php` loaded `<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>` once, render-blocking, in `<head>` — with nothing in `<head>` (only the vanilla-JS no-flash theme bootstrap IIFE) ever calling `$` — and loaded it again, correctly, immediately before `</body>` alongside jQuery UI/Bootstrap bundle/the app's own scripts in real dependency order. The `<head>` copy was pure dead weight: same URL, so the browser's HTTP cache mostly absorbed the network cost on repeat loads, but the script still had to be **re-parsed and re-executed** (redefining the entire `$`/`jQuery` global a second time) on every page load, and — more importantly — it blocked HTML parsing in `<head>` for no functional reason, since the real, load-bearing copy already existed at the body end.

**RESOLVED:** the redundant `<head>` `<script>` tag was deleted from all three files; the body-end copy (unchanged) remains the sole jQuery load. Verified live on all three pages: `typeof $ === 'function'` and `$.fn.jquery === '3.7.1'` both confirmed post-fix, zero console errors, and `about.php`'s jQuery-dependent contact-form handler still functions. `index.php`, `transactions.php`, `vehicles.php`, and `receipt.php` were also checked and confirmed to already load jQuery exactly once each (no duplication there) — `index.php`/`transactions.php` load their single copy in `<head>` (still render-blocking, but not duplicated); `vehicles.php`/`receipt.php` load it at the body end already. The `<head>`-placement render-blocking pattern on `index.php`/`transactions.php` was not changed in this pass — moving a single, non-duplicated script requires re-verifying every downstream non-deferred script's execution-order dependency on it, a larger and riskier change than deleting a confirmed no-op duplicate. Flagged for a future dedicated pass if `defer`-based restructuring is wanted.

### 36. Confirmed-dead CSS removed — orphaned carousel, mobile-logout, and receipt-modal styling rules — found and fixed during UI Implementation Plan, Phase 11 (Performance Review), 2026-08-21

A repo-wide cross-check (every class selector in `css/styles.css` grepped against every `.php` and `.js` file) found several rule blocks with zero matching elements anywhere in the live codebase:

- `#featuredCarsCarousel`/`#featuredCarsCarousel3D` and their `.carousel-inner`/`.carousel-control-prev`/`.carousel-control-next`/`.carousel-control-*-icon` rules (~45 lines) — no element with either ID exists in any `.php` file; this carousel feature was apparently never shipped or was removed elsewhere, leaving its styling behind.
- `.navbar-nav .btn-logout` and its `:hover` rule (mobile-menu logout button) — zero matches; the actual logout link/button in `includes/client_navbar.php` uses different markup.
- A bare `.carousel-inner` box-shadow-transition rule — same dead-carousel-feature leftover as above.
- `.receipt-header`, `.receipt-header h4`, `.receipt-section`, `.receipt-section .section-title`, `.receipt-footer` (~30 lines) — styling for `#receiptModal`, which is only ever built by `js/printer.js`'s `showReceiptModal()` (see item 37 below); confirmed these exact class names appear in neither `js/printer.js` itself nor any live page.

**Not removed:** the `@media print { #receiptModal ... }` block immediately following the receipt styling rules was deliberately left untouched, even though it is equally dead — it is the specific subject of item 26 (open, tracked, explicitly flagged as a business/design decision for a future phase: "give `receipt.php` its own print-visibility rule... versus unifying it to also use `#receiptModal`..."). Deleting it now would foreclose one of that item's still-open options without the separate approval item 26 already calls for, so it was left exactly as-is.

**Fix:** all four confirmed-dead blocks above were deleted from `css/styles.css` (`37,434` → `35,367` bytes, ~5.5% smaller). Verified live: brace count balanced (166 open / 166 close), `index.php` and `vehicles.php` reloaded with zero console errors and no visible layout change (none of the removed selectors matched any rendered element before or after).

### 37. Confirmed-dead file removed — `js/printer.js` — found and fixed during UI Implementation Plan, Phase 11 (Performance Review), 2026-08-21

`js/printer.js` (4,690 bytes) has been fully unreferenced since the Vehicle Listing Modernization's Step 7 (2026-08-09), which removed its only `<script>` load from `vehicles.php` and left the file itself in place "out of scope to remove... this step" (see Resolved — Step 7 note, and [COMPONENT_LIBRARY.md](COMPONENT_LIBRARY.md)/[FEATURES.md](FEATURES.md), both of which already documented it as confirmed orphaned). Re-verified fresh for this phase: a repo-wide grep for `printer.js` across every `.php` file returned zero `<script src>` references on any of the 20 entry points — the file is loaded nowhere.

Per this project's convention of not deleting code left in place for a documented reason without re-verifying and explaining why: the original reason was scope ("out of scope... this step"), not safety, and this phase's explicit mandate is unused-JavaScript cleanup — so the reason no longer applies. **Fix:** the file was deleted (a full backup was kept outside the web root before deletion, since this repository has no git history to recover it from). `js/app.js`, the only other file with any historical connection to it, does not reference it. No page's `<script>` tags changed as a result (none loaded it). This item can be considered fully resolved rather than re-flagged.

*Stale references remaining, not updated in this pass (out of this phase's scope): [ARCHITECTURE.md](ARCHITECTURE.md), [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md), [PROJECT_AUDIT.md](PROJECT_AUDIT.md), [COMPONENT_LIBRARY.md](COMPONENT_LIBRARY.md), [FEATURES.md](FEATURES.md), and several `docs/*_ANALYSIS.md`/`*_AUDIT.md` files still list `js/printer.js` as an existing project file. These are historical snapshots from prior phases; flagged here so a future documentation pass doesn't miss them.*

### 38. Seven unoptimized source images (43.4MB combined) served at 5-15MB each — found and fixed during UI Implementation Plan, Phase 11 (Performance Review), 2026-08-21

`assets/` contained seven images far larger than anything their display context requires — six vehicle-card thumbnails (rendered at `height: 200px` in `.vehicle-img-wrap img`, or inside a 260px-tall details-modal image) sourced straight from camera/stock-photo originals, plus the homepage's hero fallback image (`.hero-fallback-img`, full-bleed `object-fit: cover`):

| File | Before | Dimensions | After | Dimensions |
|---|---|---|---|---|
| `Isuzu D-Max.jpg` | 13,491 KB | 5404×3099 | 402 KB | 1600×918 |
| `Civic.jpg` | 11,836 KB | 5690×2897 | 254 KB | 1600×815 |
| `Mitsubushi.jpg` | 7,043 KB | 4105×2396 | 322 KB | 1600×934 |
| `Mazda6.jpg` | 3,966 KB | 4182×1909 | 206 KB | 1600×730 |
| `Yamaha Aerox.jpeg` | 3,539 KB | 3329×3000 | 606 KB | 1600×1442 |
| `2024-Ford-Ranger-Raptor-14.jpg` | 3,255 KB | 6240×4160 | 336 KB | 1600×1067 |
| `car-hero-img.jpg` | 3,316 KB | 3558×4447 | 683 KB | 1920×2400 |
| **Total** | **~43.4 MB** | | **~2.6 MB** | |

These are served from the `vehicles.thumbnail` DB column via `vehicles.php:606`/`:785` — real, requested images, not dead assets (the `<img loading="lazy">` attribute was already in place from an earlier phase, so this fix compounds with the existing lazy-loading rather than duplicating it).

**Fix:** each was resized (longest edge capped at 1600px for card/modal images — comfortably covers a ~2× pixel density at their largest real display width — and 2400px for the hero image) and re-encoded as JPEG quality 82, using .NET's `System.Drawing` (no ImageMagick/cwebp/Pillow/ffmpeg were available in this environment). Originals backed up outside the web root before any file was modified. Verified live: all seven load with `naturalWidth`/`naturalHeight` matching their new dimensions exactly (no corruption), `vehicles.php` and `index.php` re-tested with zero console errors, no visible quality loss at their actual display sizes.

**Not touched:** `assets/car-video-model.mp4` (36.4MB) and `assets/car-video-model-compressed.mp4` (19.9MB) — both confirmed **unreferenced by any page** (`index.php`'s `<video>` tag that would load the compressed one is fully HTML-commented-out, and the uncompressed original has zero references anywhere in the codebase). Since neither is ever requested by a browser, they don't affect page-load performance and were left as-is — deleting a 36MB+56MB pair of binaries with no version control to recover them from is a disk-hygiene decision, not a performance fix, and outside this phase's scope. Flagged for a separate cleanup task if disk usage is a concern. The other ~50 images in `assets/` were left unchanged — all already under 1MB and reasonably sized for their use.


---

### 39. ~~`.vehicle-specs` and its icons are hardcoded light-mode greys with no dark-mode counterpart — 2.04:1 in dark mode~~ **RESOLVED (UI Implementation Plan, Phase 12 — Final UI Review, 2026-08-22)** — found by this phase's automated per-page contrast sweep

`css/styles.css`'s `.vehicle-specs { color: #4b5563 }` and `.vehicle-specs i { color: #6b7280 }` — the seats / fuel / transmission row rendered on **every vehicle card**, on both `index.php` (Featured Vehicles) and `vehicles.php` (the full listing) — are hardcoded light-mode hex values with no `[data-bs-theme="dark"]` override. Measured live in dark mode: `#4b5563` against the card's `#212529` surface is **2.04:1**, well under WCAG AA's 4.5:1 minimum. Light mode is fine (**7.57:1**), which is precisely why it survived: a light-only contrast check passes it, and it only fails once dark mode is active.

**This is the same defect class the System Enhancements initiative's Steps 9 and 10 dark-mode sweeps found and fixed for `.navbar .nav-link` (`#334155`) and `.vehicle-pricebar small` (`#6b7280`)** — three hardcoded-hex rules that never had a dark counterpart, of which those sweeps caught two and missed this one. It sits eight lines away from `.vehicle-pricebar`'s own correction in the same stylesheet, on the same component (the vehicle card), so this is a genuine gap in an earlier pass rather than a new regression — and it is the one substantive defect Phases 9-11 left behind on the client surface.

**Fix:** one rule, added immediately below its two already-corrected neighbours so the three read as a set:

```css
[data-bs-theme="dark"] .vehicle-specs,
[data-bs-theme="dark"] .vehicle-specs i {
  color: var(--muted-foreground);
}
```

Same token, same technique, same location as the existing corrections — **no new colour introduced**, per CLAUDE.md's design-system rule. Verified live: `.vehicle-specs` computes to `rgb(163, 175, 194)` (`--muted-foreground`'s dark value) on `index.php` and `vehicles.php` in dark mode, and the automated sweep on both pages now reports **zero contrast failures in dark mode**. Re-checked in light mode after the change: still `rgb(75, 85, 99)` (`#4b5563`, 7.57:1) — the dark-scoped rule does not leak.

---

### 40. Dead multi-step booking wizard remains in `js/app.js` (~400 lines, zero markup) — **NOT FIXED, deferred: shared-file blast radius, requires approval** — re-confirmed during UI Implementation Plan, Phase 12 (Final UI Review), 2026-08-22

Formalises, as a numbered and actionable item, what **Incomplete Implementations** already describes prose-only. Re-verified fresh this phase by grepping every element id the wizard binds to against every `.php` file in the repository:

| Element id referenced by `js/app.js` | Occurrences in any `.php` file |
|---|---|
| `bookingMultiModal`, `bookingStep1`, `bookingStep2`, `bookingStep3`, `bookingStep2Confirm` | **0** |
| `paymentMethod`, `paymentFields` | **0** |
| `receiptSummary`, `driverName` | **0** |
| `voucherCode`, `totalAmount`, `discountAmount`, `finalAmount` | **0** |
| `bookingPreview` | 1 (`vehicles.php` — **live**, used by `window.refreshBookingPreview()`) |

So the wizard, the payment-method field renderer, and the `#voucherCode` change handler are **all unreachable**. This also closes the open question in the **Potential Bugs** entry that could not previously determine whether the `#voucherCode` handler ever fires in the current UI: it cannot.

**Why it was not deleted in this phase, despite Phase 11 having set the precedent of removing confirmed-dead JS** (the `.category-btn` handler, item 12): the dead wizard is **not a contiguous block**. It is interleaved, inside the same `$(function () { ... })` scope, with helpers that live code depends on — `resetBookingModal()` manipulates `#bookingAlert`, `#bookingPreview`, `#btnConfirm` and `#receiptArea`, all of which are **real, live elements on `vehicles.php`**, and `showAlert()` / `showValidation()` / `showFloatingAlert()` sit in the same block. Removing ~400 lines from a 1,860-line shared file that both `index.php` and `vehicles.php` load, where the boundary between dead and live is not a clean cut, is a refactor with a real regression surface on the booking flow — the single most business-critical path in the application. Phase 11's `.category-btn` deletion was a self-contained 27-line handler with no shared helpers; this is not the same kind of change.

Per CLAUDE.md's decision priority (**preserve existing functionality** ranks above maintainability and simplicity), it is deferred with the reason stated rather than attempted. **Recommended as its own task**, with a dependency map of the shared helpers produced first. Note that [PROJECT_AUDIT.md](PROJECT_AUDIT.md)'s "Questions for Developers" #6 asks exactly this question ("is `js/app.js`'s dead code safe to remove, or does it reflect an in-progress refactor?") and has never been answered — that answer is the real prerequisite.

Item 2 (the `ReferenceError` inside this wizard) **was** fixed in this phase, since renaming a variable is a two-line change with no structural risk.

---

### 41. ~~`.section-eyebrow` (4.35:1) and Bootstrap's default link colour `#0d6efd` (4.30:1) fail WCAG AA in light mode, site-wide~~ **RESOLVED (UI Implementation Plan, Phase 12 — Final UI Review, 2026-08-22) — approved by the project owner, then implemented** — found by Phase 12's automated per-page contrast sweep

Two marginal but genuine light-mode WCAG AA failures, both systemic rather than page-specific, both reproduced on a clean page load on every page that renders the affected element:

| Selector | Colour | Background | Measured | Required | Where seen |
|---|---|---|---|---|---|
| `.section-eyebrow` | `--secondary` `#2F6FED` | `--background` `#F8FAFC` | **4.35:1** | 4.5:1 (13.6px, not large text) | "HOW IT WORKS" (`index.php`), "VEHICLES" (`vehicles.php`), "OUR PURPOSE" (`about.php`) — every section eyebrow site-wide |
| default link / `.btn-link` | Bootstrap `--bs-link-color` `#0d6efd` | white card | **4.30:1** | 4.5:1 (16px) | "View All Vehicles →" (`index.php`), "Clear All" filter reset and breadcrumb "Home" (`vehicles.php`) |

Both are *pre-existing* and unrelated to any Phase 12 change — `#2F6FED` is the project's own `--secondary` brand token, unchanged since the original palette, and `#0d6efd` is Bootstrap 5.3.2's untouched default link colour. Neither is a dark-mode issue (dark mode measures clean on every page).

**Why this is not fixed here.** Every available remedy changes a colour that is either brand identity or a framework-wide default:

- Darkening `--secondary` from `#2F6FED` to roughly `#2159C7` (**6.33:1**) or `#1F5FD6` (**5.73:1**) would clear the threshold — but `--secondary` is the project's brand blue, used far beyond `.section-eyebrow` (including as a *fill* behind white text in the active navbar pill and `.cta-banner`, per the dual-role conflict [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) already documents), so changing it is a design-system decision with site-wide visual consequences, not a local contrast patch.
- Overriding `--bs-link-color` to Bootstrap's own darker `#0a58ca` (**6.44:1**) or to this project's `--primary` `#0F2A4D` (**14.4:1**) would clear it — but it restyles every link on every page, and choosing between "a darker blue" and "navy links" is a visual-identity call.
- Scoping the fix to only `.section-eyebrow` and `.btn-link` would leave the same colours failing elsewhere and introduce exactly the kind of inconsistency item 28 was originally deferred to avoid.

CLAUDE.md is explicit — **"Never introduce new colors unless approved"** — and its Bootstrap Standards section repeats it. All three options above either introduce a colour value not currently in the token set or repurpose an existing one across new surfaces. Both items are therefore **escalated for approval with measured options attached, rather than guessed at**, consistent with the precedent already set by the `bg-primary` 4.50:1 note below, which was likewise recorded rather than silently "fixed".

**Severity: Low-Medium.** Both shortfalls are marginal (4.30-4.35 against a 4.50 bar — roughly a 4% deficit, not a legibility failure a user would notice), and neither affects body copy: one is a decorative section label, the other is link text that also carries non-colour affordances (underline on `.btn-link`, the `→` glyph, `fw-semibold`). But both are real AA failures and both are site-wide, so they are logged as open rather than dismissed.

---

**RESOLVED, 2026-08-22 — approved by the project owner: links → `#0a58ca`, `--secondary` → `#2159C7`.** Both were escalated above with measured options; these two were selected and implemented.

| Token | Before | After | On `--background` | On white | White text on it (fill role) |
|---|---|---|---|---|---|
| link (`--bs-link-color*`) | `#0d6efd` | **`#0a58ca`** | 4.30 → **6.15** | 4.50 → **6.44** | n/a |
| link `:hover` | `#0a58ca` | **`#084298`** | — | 6.44 → **9.36** | n/a |
| `--secondary` | `#2F6FED` | **`#2159C7`** | 4.35 → **6.05** | 4.55 → **6.33** | 4.55 → **6.33** |

Neither value is invented: `#0a58ca` is Bootstrap 5.3.2's *own* darker step in the same ramp (it already ships as the default `:hover`), and `#084298` is Bootstrap's own `$primary-text-emphasis`, so hover still moves visibly darker than rest instead of collapsing onto it.

**`--secondary` was checked against every consumer before changing it, because it carries the dual text/fill role [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) documents.** All six consumers verified safe:

| Consumer | Role | Effect of darkening |
|---|---|---|
| `.section-eyebrow` | text on `--background` | improves (the target fix) |
| `.stats-bar .stat-number` | text on a 5% navy tint | improves |
| `.testimonial-avatar` | 3rem icon on a light card | improves |
| `.dashboard-stat-icon` | icon on a light card | improves |
| `.step-badge` | **fill**, white text on it | improves, 4.55 → **6.33** |
| `.avatar-circle` | **fill**, white text on it | improves, 4.55 → **6.33** |

There is **no consumer where this token is a light element on a dark surface** — the only case where darkening could have hurt. Verified live: `.step-badge` now renders `rgb(33,89,199)` with white text at 6.33:1.

**Two implementation details were load-bearing, both established by testing rather than assumption:**

1. **`--bs-link-color-rgb` is the property that actually works.** Bootstrap colours anchors with `rgba(var(--bs-link-color-rgb), var(--bs-link-opacity, 1))`, **not** `var(--bs-link-color)`. Setting only the named variable was verified live to change nothing whatsoever — the anchor stayed `rgb(13, 110, 253)`. Adding the `-rgb` variant moved it. All four properties (`--bs-link-color`, `--bs-link-color-rgb`, `--bs-link-hover-color`, `--bs-link-hover-color-rgb`) are declared together, because `.btn-link` maps `--bs-btn-color`/`--bs-btn-hover-color` to the *named* pair while `<a>` uses the *rgb* pair — declaring only one pair would make the two link flavours diverge.
2. **The override is scoped `:root:not([data-bs-theme="dark"])`, and that scope is required, not decorative.** Bootstrap declares its dark link colours in a plain `[data-bs-theme="dark"]` block (specificity 0,1,0). `css/styles.css` loads *after* Bootstrap, so a bare `:root` here (also 0,1,0) would have won on source order in **both** themes and forced this dark navy blue onto dark mode's near-black background. `:root:not(...)` raises specificity to 0,2,0 and confines the override to light mode.

**Dark mode confirmed completely untouched** — the specific regression risk this change carried. On a clean dark-mode load of `vehicles.php`: `--bs-link-color` still resolves to Bootstrap's `#6ea8fe`, `--bs-link-color-rgb` to `110,168,254`, `--secondary` to `#6FA0F5`, and six live anchors render at `rgb(110, 168, 254)`. Zero contrast failures.

**Also updated in step, for consistency rather than contrast:**
- `--sidebar-hover`, which is this brand blue at 10% alpha: `rgba(47,111,237,.1)` → `rgba(33,89,199,.1)`. Visually negligible at 10%; the point is that the token stays a tint of the *current* brand colour rather than silently becoming a tint of the retired one. Hover background only, carries no text.
- `[data-bs-theme="dark"]`'s fill re-scope block, which deliberately restates the *light* brand value as a literal for `.cta-banner`/`.step-badge`/`.avatar-circle`/the active navbar pill: `#2F6FED` → `#2159C7`, so the brand fill is one colour in both themes rather than forking into two.
- `index.php`'s hero scrim, an inline `linear-gradient(135deg,#0F2A4D99,#2F6FED99)` → `#2159C799`. Same brand blue as a hardcoded literal; leaving it would have forked the colour between the stylesheet and this one inline style. Darkening a scrim that carries white text on top only improves legibility. Left as a hex rather than `var(--secondary)` because the `99` suffix is 8-digit-hex alpha, which cannot be applied to a `var()` reference without restructuring the gradient.

**Verification:** the same automated per-page contrast scanner, re-run on a clean load in **each theme separately**, across `index.php`, `vehicles.php`, `about.php`, `faq.php`, `privacy.php`, and `transactions.php` (logged-out view). **Zero failures in either theme on every page** — `index.php` went 2 → 0 and `vehicles.php` 3 → 0. All 13 Bootstrap-blue links on `vehicles.php` migrated (`rgb(13,110,253)`: 13 → 0; `rgb(10,88,202)`: 0 → 13). Zero console errors on any page from a fresh tab.

**Not re-verified live after this change, stated plainly:** `receipt.php`'s authenticated card view and the seven admin pages. Both require a session this pass did not have (the customer session ended when item 33's logout fix was exercised, and admin credentials were unavailable). The change is entirely in `:root`-level custom properties consumed by components already verified on other pages, so the risk is low — but it is unverified, not verified, and is recorded as such.

---

### 42. ~~`privacy.php` ignored the user's theme and rendered a dead theme-toggle button~~ **RESOLVED (UI Implementation Plan, Phase 12 — Final UI Review, 2026-08-22)** — found incidentally while re-running the contrast sweep after item 41

`privacy.php` was **the only one of the seven client pages carrying neither the no-flash theme bootstrap `<script>` nor `js/theme.js`** — confirmed by grepping both markers across all seven pages (every other page has exactly one of each; `receipt.php` has two of the former because item 29's error-page helper carries its own copy). Two user-visible consequences:

1. **The page ignored the stored theme entirely.** With `localStorage['pms-theme'] === 'dark'`, `document.documentElement` had **no `data-bs-theme` attribute at all** and the page rendered light (`body` background `rgb(248,250,252)`). A dark-mode user following the Privacy Policy link — from the footer, or from the signup consent checkbox, which opens it in a new tab — got a jarring full-white page.
2. **The theme toggle rendered but did nothing.** `#themeToggle` comes from the shared `includes/client_navbar.php`, so the button was present on the page; without `js/theme.js` loaded, nothing was wired to it. This is the worst category of the two — a visible control that silently does nothing is a broken feature, not merely a missing one.

This contradicted [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md)'s explicit claim that the no-flash script "is the literal first child of `<head>` on all 14 pages"; that claim was wrong for this page. `privacy.php` was created in System Enhancements Step 6 and dark mode was built in Steps 8-10, so it appears to have been missed by that sweep — the same "created just before the sweep that would have covered it" gap pattern as item 39.

**Fix:** added the two missing pieces, copied verbatim from the established pattern rather than newly written — the no-flash IIFE as the first child of `<head>` (before the stylesheet, so `data-bs-theme` is set before first paint and the page cannot flash light before flipping dark), and `js/theme.js` immediately after `js/confirm.js` at the body end, matching `faq.php`'s script order exactly. No other change to the page.

**Verified live:** with `pms-theme = dark`, a clean load now yields `data-bs-theme="dark"` and `body` background `rgb(15,23,42)`, with **zero contrast failures**. Clicking the toggle flips `data-bs-theme`, `aria-pressed` (`"true"`→`"false"`), `aria-label` (`"Switch to dark mode"`), the rendered background, **and** persists to `localStorage` — all four confirmed in one interaction. A clean light-mode load also measures **zero contrast failures**. `php -l privacy.php` clean; zero console errors on a fresh tab. All seven client pages now have exact theme-bootstrap parity.
---

**Note (not a bug): `bg-primary` badge/text contrast measured at the WCAG AA threshold — found during Customer Dashboard phase, Step 6, re-confirmed Step 7, 2026-08-19.** The `Confirmed` status badge (`badge bg-primary`, white text on `--primary: #0F2A4D`) measures **4.50:1** contrast — passes WCAG AA's 4.5:1 normal-text minimum, but with zero margin, and there is no better-contrasting color available in the current design-token set without introducing a new color (which [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md)/this project's CSS conventions disallow without approval). Logged here on record, not as an actionable bug, so that if `--primary` is ever adjusted for any other reason, this badge's contrast is re-checked rather than silently regressing below 4.5:1.

**Correction (Admin Dashboard phase, Step 9, 2026-08-20):** live measurement (`getComputedStyle` against a real rendered `.badge.bg-primary` element, admin and customer pages both) shows the badge's actual background is `rgb(13, 110, 253)` — Bootstrap 5.3.2's own default `--bs-primary` (`#0d6efd`), **not** this project's custom `--primary: #0F2A4D` token. No CSS rule in `css/styles.css` overrides `.bg-primary`/`.badge` to use `var(--primary)`; the two colors are unrelated and only coincidentally land at a similar contrast ratio (**4.50:1**, matching the value above almost exactly by chance — white text on `#0F2A4D` would actually measure far higher, well above 10:1). The **4.50:1 figure and "zero margin" conclusion above are still correct and still apply** — only the stated background color was wrong. Also re-measured live this step: `bg-success` (white on `#198754`) 4.53:1, `bg-danger` (white on `#dc3545`) 4.53:1, `bg-secondary` (white on `#6c757d`) 4.69:1, `bg-warning text-dark` (`#212529` on `#ffc107`) 9.46:1 — all comfortably or narrowly pass; `bg-primary` remains the tightest.

---

## Resolved (Step 1 — Vehicle Listing Modernization, 2026-08-08)

The following previously-documented items were removed/fixed as part of `vehicles.php` cleanup and no longer apply. Left here for history rather than deleted outright, per this doc's edit convention.

- **Broken Link — `#openBookingMultiModal` button**: the orphaned "Book Now (Multi-Step)" button (`btn-warning`, `id="openBookingMultiModal"`) has been removed from `vehicles.php`. Its dead click binding still exists in `js/app.js` (line 572) and the unreachable `#bookingMultiModal` modal machinery described under Incomplete Implementations is untouched — only the dead trigger button in the markup is gone.
- **Deprecated Code — `vehicles.php:231-481` static vehicle cards**: this commented-out block of ~11 hardcoded `<div class="col-md-4 car-card">` cards has been deleted. The live `foreach ($vehicles as $car)` loop (now starting at `vehicles.php:198`) was already the sole source of rendered vehicle cards.
- **Technical Debt — dual DB connections in `vehicles.php`**: the unused `include 'db_connect.php'` (mysqli) plus its `$categoryQuery`/`$categories` loop have been removed from `vehicles.php`. `vehicles.php` now uses only the `db.php` PDO connection. `apply_voucher.php` and `get_vouchers.php` still open both connection layers — this item remains open for those two files.
- **Code Smell — inline `style="height:200px; object-fit:cover;"` on vehicle-card `<img>`**: replaced with `class="vehicle-img"`, with the sizing rule folded into the existing `.vehicle-img-wrap img` selector in `css/styles.css`.
- **Cosmetic — Reserve button used `btn-warning` (amber) instead of the site's primary action color**: changed to `btn-primary`. The disabled/unavailable state was `btn-secondary disabled` at the time this item was written; as of the Vehicle Details phase (Step 1, 2026-08-10) the button (now "View Car Details", `.btn-view-details`) no longer carries the `disabled` attribute/class in either state — see the new Resolved section below for why.
- **Duplicate jQuery load**: `vehicles.php` loaded `jquery-3.7.1.min.js` twice (once in `<head>`, once before `</body>`). The `<head>` copy has been removed; confirmed no inline script or include executes before the remaining body-end load that depends on jQuery being available earlier.

---

## Resolved (Step 7 — Vehicle Listing Modernization, final step, 2026-08-09)

- **Unused `js/printer.js` load on `vehicles.php`**: the `<script src="js/printer.js"></script>` tag has been removed. Confirmed unused before removal: `vehicles.php` was the only file loading it; `printReceipt`/`showReceiptModal`/`#receiptModal` are referenced only inside `printer.js` itself with no external caller; the actual booking flow builds its receipt display inline and redirects to `receipt.php` instead. `js/printer.js` itself was left in place (unreferenced file, not deleted — out of scope to remove the file itself this step).

---

## Resolved (Vehicle Details phase, Step 1, 2026-08-10)

- **Duplicate `.btn-reserve` click handlers firing simultaneously** (previously documented in `docs/VEHICLE_DETAILS_ANALYSIS.md` §2/§7): `vehicles.php`'s inline handler (old lines 678-707) and `js/app.js`'s delegated handler (old lines 988-1026) both bound to `.btn-reserve` and fired together on every click — guests saw a browser `alert()` *and* `#loginModal` open at once. Resolved by the Vehicle Details phase's card redesign: the vehicle card's button was renamed from `.btn-reserve` to `.btn-view-details` (now opens the new `#vehicleDetailsModal` instead, no auth check), and the old inline handler was replaced by the new populate/open handler for that modal. No element in the current markup carries the `.btn-reserve` class.
- **Dead `.btn-reserve` handler in `js/app.js` (old lines 987-1026), targeting the non-existent `#bookingMultiModal`, with a broken `$(this)` reference inside its `.done()` callback**: deleted outright (not just orphaned) as part of Step 1, with scope explicitly extended and approved for this deletion. Grep-verified before deletion that `.btn-reserve` had no other markup match anywhere in the codebase. The rest of the `#bookingMultiModal` dead-code cluster (`openBookingMultiModal` binding, `showStep()`, `resetBookingModal()`, etc. — see Incomplete Implementations, item 11-adjacent) is untouched and remains a separate, still-open tracked item.
- **Reserve button `disabled` state removed from the card's primary button** (deviation from the written plan, disclosed in `CHANGELOG.md`'s Step 1 entry): the old Reserve button's `disabled` attribute/`.disabled` class (applied when a vehicle was unavailable) were dropped from the card button so that an Unavailable vehicle's "View Car Details" can still open the (read-only) details modal — disabled browsers refuse to dispatch `click` to a `disabled` button, which would have blocked exactly the read-only-viewing behavior the phase requires. Availability is still communicated via the `bg-success`/`bg-danger` badge on both the card and the modal; only the modal's own `#btnReserveFromDetails` button is disabled for Unavailable vehicles.

## New tracked item (Vehicle Details phase, Step 5, 2026-08-10 — flagged, not fixed) — **RESOLVED (UI Implementation Plan, Phase 10 — Accessibility Review, 2026-08-21)**

- ~~**`#loginModal`'s header close button has no `aria-label`** (`includes/auth_modals.php`), unlike `#vehicleDetailsModal`'s own close buttons, which do.~~ Found during Step 5's accessibility-tree spot check (`closeBtn.getAttribute('aria-label')` returned `null`) and re-confirmed still present during this phase's Step 6 final review. Out of scope to fix at the time — `includes/auth_modals.php` was not touched by that phase per its implementation plan's file list.

**RESOLVED, 2026-08-21, UI Implementation Plan Phase 10 (Accessibility Review):** added `aria-label="Close"` to `#loginModal`'s header `.btn-close`. While in the file for this exact defect, the identical gap was also found (previously undocumented) on `#signupModal` and `#forgotPasswordModal`'s close buttons and fixed the same way, matching the convention already used by `vehicles.php`'s own modals. Verified live via the accessibility tree: all three now expose `"Close"` as their accessible name. See [CHANGELOG.md](../CHANGELOG.md)'s Phase 10 entry for the full pass this fix was part of.

---

*Every item in this document was located by direct inspection of the source files cited (and, for item 11, direct execution in a sandboxed environment loading the exact production script versions). No other code was modified as part of documenting these findings.*
