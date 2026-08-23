# Component Library — Current State Inventory

Companion document to [`UI_MASTER_PLAN.md`](UI_MASTER_PLAN.md) and [`UI_ANALYSIS.md`](UI_ANALYSIS.md). This catalogs every reusable UI component/pattern **as it exists today** across the whole PMS codebase — both client-facing and admin pages — with exact file:line citations, so each one can later be extracted into a real reusable partial/component.

**Method:** every client and admin PHP page was read in full, along with `css/styles.css` (905 lines) and every file in `js/`. Findings are verified against source, not assumed.

**Status:** research/documentation only. No code has been modified.

**Note (2026-08-09):** the entries touching `vehicles.php`'s category filter, pagination, and `js/printer.js` were updated to reflect the 7-step Vehicle Listing Modernization ([VEHICLE_LISTING_IMPLEMENTATION_PLAN.md](VEHICLE_LISTING_IMPLEMENTATION_PLAN.md), 2026-08-08 to 2026-08-09) — see those sections for what changed and when. This was a targeted update, not a full re-read of every page; other sections may still reflect the codebase as it stood before that phase.

---

## 0. Headline Finding

There is **no shared partial system anywhere in the project**, on either side:

- **Client side:** navbar and footer markup are copy-pasted across 5–6 pages (`index.php`, `about.php`, `faq.php`, `vehicles.php`, `transactions.php`, `receipt.php`), with silent drift between copies (wrong `active` link, trimmed footer text, a wholly different navbar on `receipt.php`). ~~Login/Signup modals are duplicated verbatim 5 times.~~ **Stale as of 2026-08-13** — the Shared Components phase already consolidated Login/Signup (and, since, a new Forgot Password modal) into one shared `includes/auth_modals.php` partial, included identically by all six pages. See §8/§9/§10 below for the corrected, current state.
- **Admin side:** ~~`includes/header.php` / `includes/footer.php` exist but only emit an empty wrapper... The sidebar/topbar that actually appears is hand-coded standalone inside `admin-dashboard.php` only; every other admin page has zero navigation chrome besides a "Back to Dashboard" button.~~ **Stale as of the Admin Dashboard phase (Steps 1-2, 2026-08-20).** No "Back to Dashboard" button was ever found anywhere in the codebase (repo-wide grep confirmed zero matches at analysis time) — that description predates `includes/admin_sidebar.php`. All seven admin pages now share one real sidebar (`includes/admin_sidebar.php`, a `<nav aria-label="Admin navigation">` landmark with `aria-current="page"`) and one shared topbar partial (`includes/admin_topbar.php`, Step 2), not a per-page hand-rolled header. `includes/header.php`/`includes/footer.php` themselves were deleted in Step 10 after being confirmed unreferenced.

Every category below inherits this problem: "the component" usually means 2–6 near-identical but not-quite-identical copies, not one reusable thing.

---

## 1. Navbar

### Client-side — 6 instances, 2 variants, no shared partial
| Page | Location | Notes |
|---|---|---|
| `index.php:21-43`, `about.php:24-45`, `faq.php:18-39` | Baseline navbar | Byte-identical: fixed-top, logo+brand, collapsible nav-links (Home/Vehicles/FAQ/About Us/Transactions), `#navbarAuthArea` for JS-injected auth state |
| `vehicles.php:42-62` | Same baseline, but with **hardcoded `active` class on the wrong link** (Transactions instead of Vehicles) — copy/paste bug | |
| `transactions.php:67-88` | Same baseline, `active` correctly on Transactions | |
| `receipt.php:72-83` | **Different, simplified variant** — no hamburger/collapse, no nav-links list, no auth area; just brand + two CTA buttons ("Back to Vehicles", "My Transactions") | Not reusable as the same component without reconciling |

Active-link state is set client-side by JS matching `location.pathname` — no server-side `active` logic exists except the hardcoded (and one incorrect) cases above.

### Admin side — one shared topbar partial (Admin Dashboard phase, Step 2, 2026-08-20)
| Page | Location | Notes |
|---|---|---|
| All seven admin pages (`admin-dashboard.php`, `admin_users.php`, `admin_vehicles.php`, `admin_vouchers.php`, `view-all-data.php`, `admin_settings.php`, and `admin-login.php`'s own standalone heading) | `includes/admin_topbar.php` | One shared partial: `d-lg-none` sidebar toggle, page `<h1>`, breadcrumb (`aria-label="breadcrumb"`), and the authenticated admin's real name (`$_SESSION['admin_name']`, `htmlspecialchars()`-escaped) replacing the old hardcoded "Hello, Admin!". Accepts an optional right-hand action slot (used by `admin_vehicles.php`'s Add Vehicle button and `admin_vouchers.php`'s Add New Voucher button). Five previously-divergent implementations (three hand-rolled topbars, two bare heading rows) were consolidated into this one partial; `includes/header.php`, which emitted no topbar markup at all, was deleted in Step 10. |

---

## 2. Footer

### Client-side — 6 copies, 2 variants
| Page | Location | Notes |
|---|---|---|
| `index.php:224-247`, `about.php:233-256`, `faq.php:115-138`, `vehicles.php:553-576` | Baseline: dark footer, Company blurb + Contact list (`fa-phone`/`fa-envelope`/`fa-map-marker-alt`) + copyright bar | Byte-identical across 4 pages |
| `transactions.php:244-262` | Same structure, Company blurb text silently shortened | Content drift |
| `receipt.php` | **No footer at all** | |

### Admin side — not found
No footer markup on any admin page. `includes/footer.php` contains only closing tags and script includes (jQuery, Bootstrap, DataTables) — no visible footer content.

---

## 3. Buttons

### Client-side variants found
| Pattern | Example | Notes |
|---|---|---|
| Primary CTA pill | `index.php:61` `.btn.btn-warning.btn-lg.rounded-pill` | Hero CTA, with `animate__pulse` |
| Card primary action ("View Car Details") | `vehicles.php:597` `.btn.$btnClass.w-100.rounded-pill.mt-2.btn-view-details` | **Superseded 2026-08-10** (Vehicle Details phase, Step 1). `$btnClass` swaps `btn-primary`↔`btn-secondary` server-side (`vehicles.php:573`) — corrected from this table's prior, already-stale `btn-warning`↔`btn-secondary disabled` description, which predated the Vehicle Listing Modernization. Neither state carries a `disabled` attribute/class as of this phase: the button opens `#vehicleDetailsModal` (read-only) regardless of availability, so an Unavailable vehicle's details remain viewable — only `#btnReserveFromDetails` inside the modal is disabled for Unavailable vehicles. The old "Reserve Now" button (`.btn-reserve`, auth-gated, opened `#bookingModal` directly) no longer exists on the card. |
| Outline small pill | `index.php:113,131,149` `.btn.btn-outline-info.btn-sm.rounded-pill` | Quick View triggers |
| Modal submit pills | `index.php:268,310` `.btn.btn-primary.w-100.rounded-pill` / `.btn.btn-success.w-100.rounded-pill` | Repeated identically in every modal copy |
| Sidebar filter checkboxes/inputs | `vehicles.php:405-471` `.form-check-input` (category/fuel/transmission/seats/availability) + `.form-control` (price min/max) inside `#filterSidebar` | **Superseded 2026-08-08/09.** Replaced the old `#categoryBar` `.category-btn` button row and its two competing click handlers (see [BUGS.md](BUGS.md) item 12) as of the Vehicle Listing Modernization Steps 4-5. Server-side GET-submitted form, not client-side JS filtering. |
| Icon-only circular button | CSS `.vehicle-actions .btn-icon` (`css/styles.css:723-732`) | **Defined in CSS, no matching markup anywhere** — dead |
| Square (non-pill) buttons | `receipt.php:79-80,142-143` | Only page whose buttons aren't `rounded-pill` — visual outlier |
| ~~Orphaned trigger button~~ | ~~`vehicles.php:206` `#openBookingMultiModal`~~ | **RESOLVED 2026-08-08** — the dead button (opened a modal ID, `#bookingMultiModal`, that existed in no page's DOM) was removed during Vehicle Listing Modernization Step 1. See [BUGS.md](BUGS.md) "Resolved (Step 1...)". |

Global override: `css/styles.css:876-879` redefines `.btn-sm` padding/font-size site-wide.

### Admin-side variants (as of the Admin Dashboard phase, Step 6, 2026-08-20)
The per-table color/style conventions below are unchanged — Step 6 standardized the *destructive-action confirmation and feedback* path, not each table's button coloring:
| Table | Style |
|---|---|
| `admin_vehicles.php` | Solid `btn-warning` (Edit) / `btn-danger` (Delete), text+icon |
| `admin_users.php` | Outline `btn-outline-primary` / `btn-outline-danger`, icon-only |
| `admin_vouchers.php` | Solid `btn-primary` / `btn-danger`, icon-only |
| `view-all-data.php` | Outline `btn-outline-success` / `btn-outline-danger` / `btn-outline-primary`, icon-only, 3-button group |
| `admin-dashboard.php` | Solid `btn-success` text-label "Confirm" — still a fifth variant for the same confirm action shown elsewhere as icon-only |

Every icon-only action button now carries a descriptive `aria-label` naming its target row (e.g. `aria-label="Edit Toyota"`). Every destructive action (delete user/vehicle/voucher/transaction) now routes through `js/admin.js`'s shared `confirmAction()` — a Promise-based Bootstrap confirmation modal replacing the old per-page `confirm()` calls, with cascade-warning text reused verbatim from Steps 4-5. Modal footers are `btn-secondary` (cancel) + `btn-primary`/`btn-success` (submit), now consistently `type="submit"` inside a `<form>` (see §10).
`admin-login.php:62-63` uses a unique pill-shaped `rounded-pill` button, matching no other admin page — unchanged, out of scope for this phase.

---

## 4. Cards (generic, non-vehicle/non-booking/non-KPI)

### Client-side
| Location | Pattern |
|---|---|
| `index.php:69-89` | Feature cards: `.p-4.text-center.rounded-3.shadow`, 3 instances with FA icons. **Correction (System Enhancements initiative, Step 5, 2026-08-21):** `.glassmorph` was removed project-wide; these now use `bg-body`, a solid theme-aware surface. |
| `about.php:76-105` | Stat cards: `.p-4.bg-white.rounded-3.shadow-sm.text-center` — uses **raw emoji instead of Font Awesome**, inconsistent with rest of site |
| `about.php:117-153` | Team member cards: circular avatar + name + placeholder "TBA" role (content gap), 5 instances |
| `about.php:237-243` | CTA banner: uses the shared `.cta-banner` class (`css/styles.css:715-724`), the same component `index.php`'s homepage CTA banner uses — migrated off its former bespoke inline gradient during the About Us Modernization phase (Step 5). No inline styling, no custom colors. |
| `transactions.php:117-130` | Summary stat cards: near-duplicate of about.php's stat card pattern but different padding, no icon |
| `index.php:102,120,138` | Carousel cards use literal class `.card.3d` — **CSS defines `.card.three-d`, not `.3d`**, so the intended 3D hover effect never applies (bug) |

### Admin side
Four different card "flavors" wrap admin tables with no shared partial: plain `.card` (`admin_vehicles.php`, `admin_users.php`), `.card.shadow-sm` (`admin_vouchers.php`), `.card.shadow-sm.border-0` with header (`admin-dashboard.php`), and `.card.shadow-sm.border-0` with header+footer (`view-all-data.php`).

---

## 5. Vehicle Cards

Two entirely separate, non-shared implementations:

| Location | Pattern |
|---|---|
| `vehicles.php:577-608` (live, DB-driven) | `.vehicle-card.bg-white.rounded-3.shadow-sm.overflow-hidden` → `.vehicle-img-wrap > img` → `.vehicle-specs` (3-icon grid: seats/fuel/transmission) → `.vehicle-pricebar` → availability badge → `.btn.$btnClass.w-100.rounded-pill.btn-view-details` ("View Car Details", **updated 2026-08-10** — Vehicle Details phase Step 1; previously `.btn-reserve` ("Reserve Now"), which opened `#bookingModal` directly with an inline auth check). The button now also carries `data-cat`/`data-seats`/`data-fuel`/`data-transmission`/`data-thumbnail`/`data-available`/`data-available-text` (in addition to the pre-existing `data-id`/`data-car`/`data-rate`), feeding the new `#vehicleDetailsModal` (see §10). |
| ~~`vehicles.php:230-481` commented-out static mockup block~~ | **RESOLVED 2026-08-08** — the ~11 dead example `.car-card` divs were deleted during Vehicle Listing Modernization Step 1. See [BUGS.md](BUGS.md) "Resolved (Step 1...)". |
| `index.php:101-154` | Completely different card in the featured carousel: `.card.3d.p-4`, specs inline in one `<p>` instead of the `.vehicle-specs` grid, dual "Quick View"/"Reserve Now" buttons, hardcoded (not DB-driven), and modal IDs mislabeled vs. displayed vehicle content. **Correction (Step 5, 2026-08-21):** `.glassmorph` removed, see §4's correction. |

CSS backing (`css/styles.css:655-761`) also defines `.vehicle-badges`/`.vehicle-badge` and `.vehicle-actions`/`.btn-icon` — both **fully styled but with no matching markup anywhere** (dead CSS).

Admin side has no equivalent "vehicle card" — vehicle rows are table rows only (see §7).

---

## 6. Booking Cards

At least **four parallel "booking receipt/summary" implementations** exist client-side, of unclear/overlapping ownership:

| Location | Pattern |
|---|---|
| `vehicles.php:153-161` `#bookingPreview` | Live in-modal summary card, filled by JS |
| `vehicles.php:185-194` `#receiptArea` | In-page receipt shown before redirect to `receipt.php` |
| `receipt.php:86-145` `.receipt-card` | The one server-rendered, permanent receipt page — styled by an inline `<style>` block, not `styles.css` |
| `js/printer.js:7-78` `#receiptModal` | A third, JS-built modal receipt (`showReceiptModal`) — **confirmed unused** (was only "appears unused" prior to Step 7's verification): `vehicles.php`'s booking JS redirects to `receipt.php` instead of calling it, and repo-wide grep found no caller of `showReceiptModal`/`printReceipt` anywhere outside `printer.js` itself. Its `<script src="js/printer.js">` load was removed from `vehicles.php` in Vehicle Listing Modernization Step 7, and **the file itself was deleted by UI Implementation Plan Phase 11 (2026-08-21)** — `#receiptModal` no longer exists anywhere in the codebase. The shared `@media print` rule that depended on it was repaired in Phase 12 ([BUGS.md](BUGS.md) item 26). |
| `transactions.php:469-` `#returnReceiptModal` | A fourth ad hoc "return receipt" card, specific to the early-return flow |
| `transactions.php:211-320` | Booking history presented as `.list-group-item` rows, not cards at all — a fifth visual pattern for "one booking's info" |

Admin side: no dedicated booking-card component; booking data is table-only (§7), with a KPI-style summary via metric cards (§8).

**Customer Dashboard phase update (Phase 7, 2026-08-19):** the `.list-group-item` booking rows (`transactions.php:211-320`) were substantially reworked across Steps 2-3 and 6:
- **Status-aware badges:** each row now carries two `<span class="badge rounded-pill" role="status">` elements — a time-bucket badge ("Upcoming"/"Active", Active section only) and a status badge (`bg-warning text-dark` Pending, `bg-primary` Confirmed, `bg-success` Completed, `bg-danger` Cancelled). Cancelled/completed bookings render only the status badge, no time badge.
- **Status-aware actions:** action buttons/links are driven entirely by `bookings.status`, not by date math (fixes [BUGS.md](BUGS.md) item 10) — `pending`/`confirmed` with a future rental date get "Cancel Booking" (`.btn-outline-danger`); `pending`/`confirmed` already in-progress get "Return Early" (`.btn-outline-warning`); `confirmed` rows additionally get a "View Booking Confirmation" link (`.btn-outline-secondary`, → `receipt.php?ref=...`); `completed` rows get "View Receipt" (same class, same target). `cancelled`/`pending`-but-not-yet-actionable rows render no action element at all.
- **Responsive stacking:** each row is a `d-flex flex-column flex-md-row` layout (was a fixed horizontal `justify-content-between` that overflowed at 375px) — title/badges/dates/amount stack vertically below `md`, flow horizontally at `md`+. Action buttons use `.d-grid.d-md-flex` + `py-3` for a 44px+ touch target on every breakpoint (see Step 6, and the 3 profile-section buttons brought in line with this same `py-3` pattern during this phase's Final Review, below).
- **Alert feedback:** Cancel/Return Early no longer use `alert()` — both inject a `.alert.alert-success.alert-dismissible.fade.show` (or `.alert-danger` on failure) at the top of the Active section, `role="alert"`, auto-reload after 3s.

---

## 7. Tables

### Client-side
**Not found.** No `<table>` markup exists on any client-facing page (index, vehicles, about, faq, receipt, transactions) — booking history uses list-group rows instead (§6). The table CSS in `styles.css` is loaded on every client page but unused there.

### Admin side (updated for the Admin Dashboard phase, Steps 3-5, 2026-08-20)
| Table | Location | DataTables? | Notes |
|---|---|---|---|
| Recent Transactions | `admin-dashboard.php` | No | `LIMIT 5`, status badges corrected (Step 3), correct `colspan` |
| Recent Messages | `admin-dashboard.php` | Yes (Step 3) | `LIMIT 5` added; DataTables-enhanced for search/pagination since there is no dedicated messages page |
| `#vehiclesTable` | `admin_vehicles.php` | Yes, `columnDefs` baseline (Step 5) | `thead.table-light` (was `table-dark`, unified in Step 5); numeric ID sort; Image/Actions non-sortable |
| `#usersTable` | `admin_users.php` | Yes | `thead.table-light` |
| Vouchers table | `admin_vouchers.php` | **Still no DataTables** | Only admin list with no search/sort/pagination — not addressed in this phase |
| `#transactionsTable` | `view-all-data.php` | Yes, `columnDefs` baseline (Step 4) | Header typo fixed ("Rental Date"); numeric ID sort; Actions column non-sortable/non-searchable |

All wrapped in `.table-responsive`. The DataTables `columnDefs` baseline established in Step 4 and reused verbatim in Step 5 — `orderable:false`/`searchable:false` on the Actions column, a `data-order` attribute for numeric-aware ID sorting (fixing `#9` sorting after `#10`), `pageLength: 10`, and project-toned `language` strings — is now in use on `#transactionsTable` and `#vehiclesTable`; `#usersTable` and the Recent Messages table still use the older default `{order:[[0,'desc']]}` configuration.

---

## 8. Forms

### Client-side
| Form | Location | Notes |
|---|---|---|
| Booking form `#bookingForm` | `vehicles.php:80-181` | Two-stage Preview → Confirm flow, `.row.g-3` layout |
| Login/Signup/Forgot-Password forms | Single shared copy in `includes/auth_modals.php`, included by all 6 client pages (§0 correction) | `.form-control` inputs with per-field `.invalid-feedback`/`aria-describedby` real-time validation (Authentication phase, 2026-08-13), `.alert-danger.d-none` error slot per modal, `.input-group.has-validation` + password-visibility toggle on all 4 password fields |
| Contact form `#contactForm` | `about.php:170-215` | Only client form using `.rounded-pill` inputs — everything else uses square `.form-control` |
| Return-early / Cancel-booking "forms" | `transactions.php:338-343,380-394` | No real inputs, just confirmation text + hidden id |
| Voucher select | `vehicles.php:125-129` | Populated by **two independent, duplicate implementations** (`voucher-manager.js` and `app.js`) |
| Profile Information form `#profileInfoForm` | `transactions.php:359-373` (Customer Dashboard phase, Step 4) | Name + Email, `.form-control`, `AuthValidation`'s second consumer (blur/input triggers, `.is-invalid`/`.invalid-feedback`, `aria-describedby`) — same validation rules as signup. Submits to `update_profile.php`, updates `#profileNameDisplay`/`#profileEmailDisplay`/`#navbarWelcomeText` in place on success, no page reload. |
| Change Password form `#changePasswordForm` | `transactions.php:380-400` | Current/New/Confirm password, `AuthValidation`-wired (min 6 chars, confirm-match), submits to the pre-existing `change_password.php`. First customer-facing UI for that endpoint (previously backend-only). |
| Profile Picture upload form `#profilePictureForm` | `transactions.php:339-346` | Single `<input type="file" accept="image/jpeg,image/png">` + Upload button, `multipart/form-data` to `update_profile_picture.php`. No client-side type/size pre-check beyond the `accept` attribute — validation (3MB, real-image-content via `exif_imagetype()`) is server-side only. |

**Avatar component (`getAvatarHtml()`, `js/app.js:951-961`; PHP twin `render_avatar_html()`, `admin_users.php:20-27`):** renders `<img class="rounded-circle" style="object-fit:cover">` when `profile_picture_path` is set, else a `.avatar-circle` initials fallback (first letter of first + last name, colored per design tokens). Three call sites share this logic: navbar (`#navbarAvatar`, 28px), profile section display (`#profilePictureDisplay`, 96px), and `admin_users.php`'s Avatar table column (32px) — the JS and PHP versions are independent implementations of the same initials algorithm, not a shared function, since one runs client-side and the other server-side.

All three profile-section submit buttons (`#profilePictureSaveBtn`, `#profileInfoSaveBtn`, `#changePasswordSaveBtn`) were brought up to the page's `py-3` 44px-touch-target convention during Phase 7's Final Review (Step 7) — previously plain `.btn.rounded-pill.px-4` at ~37-41px, below the Step 6-claimed WCAG/mobile touch-target minimum.

No dedicated search/filter form exists client-side.

### Admin side (updated for the Admin Dashboard phase, Step 6, 2026-08-20)
**Vehicles** still use real page POST + redirect-with-`?error=`/`?added=`/`?updated=`/`?deleted=` query strings (`admin_vehicles.php`, `admin_add_vehicle.php`, `admin_edit_vehicle.php`, `admin_delete_vehicle.php`) — now correctly redirecting back to `admin_vehicles.php` so the alerts are reachable (Step 5), still a structurally different submission convention from the rest of the admin side. **Users, Vouchers, Transactions, Booking-confirm** use AJAX + JSON. Zero `alert()`/`confirm()` calls remain anywhere in admin JS (verified by grep) — every AJAX failure now renders into a `.js-modal-alert`/`#pageAlert` region via `js/admin.js`'s `showAdminError()`/`showAdminSuccess()`, and every destructive action goes through the shared `confirmAction()` modal. One modal+form DOM convention now applies across all seven admin modals: `<form>` wraps the entire `.modal-content`, every footer submit button is a real `type="submit"`. Field-level validation is now wired via `js/admin.js`'s `AdminValidation` (a from-scratch admin equivalent of the customer side's `AuthValidation` pattern — not a copy, and not achieved by loading `js/app.js`, which is not IIFE-wrapped and binds customer-only handlers) on: Edit User, Add/Edit Vehicle, Add/Edit Voucher, Edit Booking Times — `.is-invalid`/`.invalid-feedback` on blur, `aria-invalid`/`aria-describedby`, first-invalid-field focus on submit.

---

## 9. Alerts

### Client-side
- `.alert.alert-danger.d-none#loginError` / `#signupError` / `#forgotError` (new, Authentication phase) — one copy of each, in the single shared `includes/auth_modals.php` partial (§0 correction), not duplicated per page. Each is shown/shaken via the shared `showAuthError()` helper in `js/app.js`.
- `about.php:208` `#contactAlert` — markup says `alert-success`, JS swaps the class for both success and error cases.
- `vehicles.php:77` `#bookingAlert` — no color class in markup, set entirely by JS.
- `js/app.js:558-568` `showFloatingAlert()` — appends `.alert-floating`, but **`.alert-floating` has no CSS rule anywhere** — doesn't actually float/fix-position despite the name.
- `voucher-manager.js` defines its own separate `showFloatingAlert()` — a name collision with `app.js`'s version.
- `transactions.php` dashboard alerts (Customer Dashboard phase, Step 5, Option B) — `.alert-warning`/`.alert-success`/`.alert-danger.alert-dismissible.fade.show`, `role="alert"`, derived entirely from `$active`/`$completed` PHP arrays at render time (reminder within 24h of rental start, confirmed within 48h of `created_at`, cancelled within 48h of `created_at`). No persistence — dismissing one only hides it for that page view; it reappears on reload since nothing is stored server-side.
- `transactions.php` Cancel/Return-Early feedback (Step 3) — `.alert-success`/`.alert-danger.alert-dismissible.fade.show` injected into the DOM by JS in place of the previous native `alert()`, `role="alert"`, 3s auto-reload.
- `transactions.php` profile-section feedback (Step 4) — three independent inline alert slots (`#profileInfoAlert`, `#profilePictureAlert`, `#changePasswordAlert`), each `.alert.d-none` swapped to `.alert-success`/`.alert-danger` on submit, no reload (in-place DOM update only).

### Admin side
- `admin_vehicles.php:36-44` — query-string-driven (`?error=`, `?updated=1`, `?deleted=1`).
- `admin-login.php:50-52` — inline PHP var, **not `htmlspecialchars`-escaped** (currently safe only because the string is hardcoded, not user input).
- No other admin page uses `.alert` — errors surface via browser `alert()` instead, an inconsistent second convention.

---

## 10. Modals

### Client-side (12 distinct modal IDs across the codebase)
| Modal | Location | Notes |
|---|---|---|
| `#quickViewXpander/#quickViewAccord/#quickViewMirage` | `index.php:169-217` | IDs are mislabeled vs. the vehicle actually displayed. **Correction (Step 5, 2026-08-21):** `glassmorph` styling removed, see §4. |
| `#loginModal`, `#signupModal`, `#forgotPasswordModal` (new, 2026-08-13) | Single shared copy each, in `includes/auth_modals.php`, included by all 6 client pages — **not** duplicated per page (§0 correction) | `#forgotPasswordModal` is a single modal with a 3-step internal flow (email → code → new password), not three separate modals. **Correction (Step 5, 2026-08-21):** `glassmorph` styling removed from all three, see §4. `#signupModal` also gained a required privacy-consent checkbox in Step 7 (see §20 below). |
| `#bookingModal` | `vehicles.php:66-200` | `.modal-lg`. Unmodified by the Vehicle Details phase — only its trigger changed (see `#vehicleDetailsModal` below). |
| `#vehicleDetailsModal` | `vehicles.php:383-429` | **New, added 2026-08-10** (Vehicle Details phase, Steps 1-3). Plain (no glassmorph), `.modal-lg.modal-dialog-centered.modal-dialog-scrollable`. Opened by the card's `.btn-view-details` button (populated client-side from its `data-*` attributes, no auth check, no fetch/AJAX). Body: image, title, category badge, price, availability badge, `.vehicle-specs`-reused seats/fuel/transmission grid, static rental-information list. Footer: Close + `#btnReserveFromDetails` ("Reserve Now", disabled when the displayed vehicle is unavailable). Clicking `#btnReserveFromDetails` calls `checkLoginStatus()`, closes this modal (`hidden.bs.modal`-sequenced, no stacking), then opens `#loginModal` (guest) or `#bookingModal` (authenticated, with `#vehicle_id`/`#bookingModalLabel` set). |
| `#bookingMultiModal` | **Referenced only in `app.js`** (lines 582, 969, 1016) | **No matching markup found anywhere** — confirmed dead; the entire multi-step flow behind it (`showStep()`, `#bookingStep1/2/3`, payment fields) is unreachable |
| ~~`#receiptModal`~~ | ~~Built in `js/printer.js:9-78`~~ | **GONE** — script tag dropped from `vehicles.php` in Vehicle Listing Modernization Step 7; `js/printer.js` itself deleted by UI Implementation Plan Phase 11 (2026-08-21). This element no longer exists anywhere in the codebase; the shared `@media print` rule that silently depended on it was repaired in Phase 12 ([BUGS.md](BUGS.md) item 26) |
| `#returnEarlyModal`, `#cancelBookingModal`, `#returnReceiptModal` | `transactions.php` | Standard structure |
| `#licensePreviewModal` | `transactions.php:353-369` | Comment `<!-- license preview removed -->` appears 3x — trigger was deliberately removed but the modal markup was left behind |

**Correction (Step 5, 2026-08-21):** the "two stylistic families" (`glassmorph` vs. plain) noted above no longer exist — every modal listed in this section is now a solid, theme-aware surface (`bg-body`/Bootstrap default), so this is no longer a normalization candidate.

### Admin side (7 modal instances) — unified in the Admin Dashboard phase, Step 6, 2026-08-20
~~`addVehicleModal`/`editVehicleModal` (`.modal-lg`, `<form>` wraps the entire modal-content) vs. `addVoucherModal`/`editVoucherModal`/`editUserModal`/`editTransactionModal` (`<form>` wraps only `.modal-body`...) — two structurally different modal+form conventions coexist.~~ **Resolved.** All seven admin modals now share one DOM convention: `<form>` wraps the entire `.modal-content`, footer submit buttons are real `type="submit"`. `editUserModal`'s duplicate, dead copy on `admin-dashboard.php` (the page has no `.edit-user`/`.delete-user` trigger) was deleted in Step 6; the one live copy lives on `admin_users.php` only.

**Correction (System Enhancements initiative, Step 1, 2026-08-21):** the `confirmAction()` confirmation modal described above has moved out of `js/admin.js` into a new shared module, `js/confirm.js` (`window.PMSConfirm`), loaded on both client and admin pages (all 12 pages that have any confirmable action — `admin-login.php` is the sole exclusion, having no JS and no confirmable action at all). `js/admin.js` keeps a one-line alias (`window.confirmAction = window.PMSConfirm;`) for backward compatibility with existing call sites. Its scope also expanded well beyond "destructive actions": Steps 3-4 wired it to 8 total call sites — logout (client G1, admin G2), password change (client G4, admin G5), email change (client G6, admin G7), booking confirmation with a live-interpolated total (client G3, nested over `#bookingModal`), and admin booking-time edits (G8, nested over `#editTransactionModal`). Both nested cases were verified live in Step 11 to stack correctly (2 backdrops) and return focus to the underlying modal, not lose it to `<body>`, when cancelled.

### The shared confirmation dialog (`#adminConfirmModal`, `js/confirm.js`)

Despite the `adminConfirmModal` id (a naming artifact from its original admin-only home, kept rather than churned across every call site), this is the single confirmation dialog used everywhere in the project. Markup (built once, lazily, on first call): `role="alertdialog"` (reasserted on every `shown.bs.modal`, since Bootstrap's own `Modal._showElement()` otherwise clobbers it back to `role="dialog"`), `aria-labelledby`/`aria-describedby` pointing at the title/body, a header `.btn-close` (**44×44px as of 2026-08-22** — was 32×32px, Bootstrap's unmodified default; [BUGS.md](BUGS.md) item 28 was resolved project-wide in UI Implementation Plan Phase 12, so every `.btn-close` in the project now shares this size), and a footer with a `.btn-secondary` "Cancel" and a caller-supplied confirm button (`.btn-danger` by default via `opts.variant`, or a caller-chosen variant/label — e.g. `variant: 'success', confirmLabel: 'Confirm Booking'` for G3). Both footer buttons measure a full 44px tall. Called as `window.PMSConfirm({title, body, variant, confirmLabel})` — returns a `Promise` that always *resolves* (never rejects) with `true` (confirmed) or `false` (Cancel, backdrop click, or Esc), and restores focus to whichever element triggered the dialog once it closes, regardless of outcome.

---

## 11. Pagination

**Client-side: implemented on `vehicles.php` only**, as of Vehicle Listing Modernization Step 6 (2026-08-08). Standard Bootstrap `<nav aria-label="Pagination"> → ul.pagination.justify-content-center → li.page-item > a.page-link` (`vehicles.php:568-603`), server-side (`LIMIT`/`OFFSET`, 9 per page), with a 5-page sliding window + `&hellip;` ellipses, `page-item disabled` at both bounds, and `aria-current="page"` on the active page. Pagination links preserve every active filter and the current sort. The transaction list (`transactions.php`) still has no pagination — everything renders in one page. Admin side: unchanged — relies entirely on default DataTables pagination wherever DataTables is initialized; `admin_vouchers.php`'s table has no DataTables at all, so it has no pagination whatsoever.

---

## 12. Search

**Not found** as a dedicated, working component on either side. Client side has a dead JS handler (`app.js:1043-1046`) targeting a search input inside `.hero-immersive` that doesn't exist in `index.php`'s markup. Admin side search is limited to whatever the default (unconfigured) DataTables search box provides on the three tables that use DataTables.

---

## 13. Filters

### Client-side
**Rebuilt 2026-08-08/09** as part of Vehicle Listing Modernization Steps 4-5, replacing the previous `#categoryBar` button row entirely. `vehicles.php` now has a real sidebar filter panel (`#filterSidebar`, `vehicles.php:405-471`; desktop `col-lg-3` sticky column, offcanvas below 992px) with 6 groups, each headed by a consistent `.filter-group-title`: Category, Price Range (min/max number inputs), Seats (4 fixed buckets: 2 / 4-6 / 7-8 / 9+), Fuel Type, Transmission, and an Availability-only toggle. GET-submitted, filtered entirely server-side against a single parameterized `WHERE` clause — no client-side JS filtering, no duplicate handlers. Category checkboxes are now generated from a `SELECT category, seats FROM vehicles WHERE is_active = 1` query (`vehicles.php:51-58`), not a hardcoded list — this resolves the previous `#categoryBar` gap where any vehicle category outside its hardcoded 6-button set (e.g. `mpv`/`hatchback`) was unreachable via the filter. `app.js`'s original `.category-btn` click handler (`app.js:371-397`) was left in the shared file, unbound to anything — see [BUGS.md](BUGS.md) item 12, confirmed dead on every page as of Step 7.

### Admin side
**Not found.** No status filter, date filter, or category filter exists on any admin table — filtering is whatever DataTables' default search box happens to match.

---

## 14. Badges

### Client-side
| Location | Represents |
|---|---|
| `vehicles.php:529` `bg-success`/`bg-danger` | Vehicle availability |
| `transactions.php:172-173,226` | Rental timing status + booking record status (two badges per row) |

### Admin side
| Location | Represents |
|---|---|
| `admin_vehicles.php:67-69` | Vehicle `is_active` |
| `admin_users.php:60` | User role |
| `admin_vouchers.php:130-135` | Voucher usage vs. limit |
| `admin-dashboard.php` | Booking status — **fixed in Step 3**: previously compared against `'Completed'`/`'Active'`/`'Cancelled'` against a lowercase enum, so no case could ever match and every badge rendered gray; now a `match` keyed on all four real lowercase values |
| `view-all-data.php` | Booking status — **fixed in Step 4**, now reuses the identical mapping as `admin-dashboard.php` (`completed`→`bg-success`, `confirmed`→`bg-primary`, `cancelled`→`bg-danger`, `pending`→`bg-warning text-dark`); the previous color-mapping inconsistency between the two pages no longer exists |

Global override: `css/styles.css:881-885` `.badge{font-weight:500;padding:.35em .65em}` applies site-wide.

---

## 15. Status Indicators (beyond badges)

- `.booking-step-indicator .step-circle`/`.step-circle.active` (`css/styles.css:580-599`) and the `.booking-step` fade-in animation (`:526-545`) — fully styled in CSS but have **no matching markup anywhere** on either side. This is the dead multi-step booking modal's intended UI (§10) — also the exact CSS hook flagged in `UI_ANALYSIS.md` §4 as ready to reuse for a real checkout progress indicator.
- No colored-dot, traffic-light, or icon-based status indicators exist beyond badges and this unused step-circle system.

---

## 16. Dropdowns

- Client side: one real dropdown, the JS-injected "Welcome, {name}!" navbar menu — **defined twice** in `app.js` (lines 436-444 and 1177-1184), one copy dead/no-op.
- Admin side: **not found anywhere.** No `.dropdown` markup exists on any admin page.

---

## 17. Icons

Both sides use **Font Awesome 6.4.2** via CDN, consistently versioned. Two inconsistencies found:
- **Icon prefix mixing** on the admin side: old `fa fa-*` and newer `fas fa-*` prefixes are mixed, sometimes within the same file (`admin-dashboard.php` sidebar list uses both).
- **Emoji instead of icons**: `about.php:79,86,93,100` uses raw emoji (🚗🎧🛡️⚙️) for its 4 stat cards instead of Font Awesome, breaking icon-system consistency with the rest of the client side.

Common patterns: icon+label sidebar links (admin), icon-only row-action buttons (admin tables), icon+text buttons (both sides), icon chips in feature cards (client).

---

## 18. Empty States

### Client-side
| Location | Trigger |
|---|---|
| `vehicles.php:562-564` `#noResultsMessage` | **Rebuilt 2026-08-08/09.** Now server-rendered (`<?php if ($vehicleCount === 0): ?>`), not JS-toggled — the previous inconsistency between `app.js`'s handler showing it and the inline duplicate filter script not showing it no longer applies, since neither JS path controls it anymore. |
| `transactions.php:98-105` | Not-logged-in state |
| `transactions.php:107-113` | Logged-in, zero bookings — shares the same heading text as the not-logged-in state despite being a different condition |

### Admin side
| Location | Notes |
|---|---|
| `admin_vehicles.php` | `No vehicles found`, correct colspan |
| `admin_users.php` | `No users found` — colspan is correct at `7` (the Avatar column was added in Customer Dashboard Step 4.1); the colspan-bug entry this table previously carried here is **stale/resolved** |
| `admin-dashboard.php` | `No transactions found` — **colspan bug fixed in Step 3** (`colspan='5'` → `colspan='10'`, matching the table's actual 10 columns) |
| `admin-dashboard.php` | `No messages yet.` — correct colspan |
| `view-all-data.php` | **Empty-state row added in Step 4** (`colspan="10"`) — previously rendered a silently empty `<tbody>` on a zero-row result |
| `admin_vouchers.php` | **Still no empty-state row at all** — not addressed in this phase |

All are plain muted-text table rows; no icon or illustration used anywhere.

---

## 19. Loading States

**Client side unchanged** — no general pattern; contact form submit and voucher apply give no in-flight visual feedback.

**Admin side — standardized in the Admin Dashboard phase (Step 6, 2026-08-20).** `PMSMotion.setButtonLoading()` (`js/motion.js`) is now used consistently across every admin AJAX handler in `js/admin.js` — Add/Edit/Delete Vehicle, Add/Edit/Delete Voucher, Confirm Booking, Edit Booking Times, Delete Transaction, Edit/Delete User — each with a real `error:` callback that clears the loading state on failure (previously missing on the three `admin_vouchers.php` handlers, which could leave a button permanently spinning on a network error). No skeleton loaders or full-page loading overlays exist anywhere in the project.

---

## 20. Theme Toggle

*New, System Enhancements initiative, Step 8b, 2026-08-21 (Feature 1, Decisions D1-D3).*

One pattern, two instances: `#themeToggle` (`includes/client_navbar.php`, inside `.navbar-collapse` so it stays reachable when the navbar is collapsed on mobile) and `#adminThemeToggle` (`includes/admin_topbar.php`, static markup rather than routed through the per-page `$topbarActions` slot, since a global control shouldn't depend on every caller remembering to set it). Both: `<button class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center js-theme-toggle">` with an inline `width/height` (44px — corrected from an original 38px in Step 11, see [BUGS.md](BUGS.md) item 25's standard), an `<i class="fas fa-moon">` icon, and `aria-pressed`/`aria-label` attributes. `js/theme.js` owns the icon swap (moon ↔ sun) and both ARIA attributes for every `.js-theme-toggle` on the page — confirmed live in Step 11 that a click flips `aria-pressed` (`"true"`↔`"false"`) and `aria-label` (`"Switch to light mode"`↔`"Switch to dark mode"`) together. Persistence is `localStorage` only (`pms-theme` key, Decision D2); the initial theme is OS-seeded via `prefers-color-scheme` and pinned to an explicit choice only after the first manual toggle (Decision D1). A no-flash inline `<script>` (reads `localStorage`, falls back to the media query, sets `data-bs-theme` on `<html>`) is the literal first child of `<head>` on all 14 pages, including `admin-login.php`, which has neither the toggle button nor `js/theme.js` loaded but still respects a previously-set theme.

> **Correction (UI Implementation Plan, Phase 12, 2026-08-22 — [BUGS.md](BUGS.md) item 42).** "All 14 pages" was **not true when written**: `privacy.php` had neither the no-flash script nor `js/theme.js`. The consequence was worse than a flash — that page ignored the stored theme entirely (no `data-bs-theme` attribute at all, always rendered light) *and* still rendered `#themeToggle` from the shared `includes/client_navbar.php`, so a visible toggle button sat there doing nothing when clicked. Both were added in Phase 12, copied verbatim from `faq.php`'s pattern and script order. The statement is accurate **as of 2026-08-22**, now verified by grepping both markers across all seven client pages rather than assumed — a reminder that "all N pages" claims in this document should be re-grepped, not trusted, whenever a page is added late in a phase.

## 21. Privacy Consent Checkbox

*New, System Enhancements initiative, Step 7, 2026-08-21 (Feature 4, Decisions D7-D8).*

One instance: `#signupPrivacyConsent`, inside `#signupModal` (`includes/auth_modals.php`). Standard Bootstrap `.form-check` structure — `<input type="checkbox" class="form-check-input" required aria-describedby="signupPrivacyConsentFeedback">`, wrapped by a `<label class="form-check-label">` carrying the required legal wording and an inline `target="_blank" rel="noopener"` link to `privacy.php`, followed by an empty `.invalid-feedback` div matching the `aria-describedby` id. The checkbox itself renders at 24×24px (a documented trade-off against the literal 44×44px touch-target guideline — chasing 44px on the visible glyph would look oversized next to standard-height text), but the label's own bounding box is the real click target for any tap on the wrapped consent text, measured live in Step 11 at roughly 230×192px — comfortably clearing 44×44px in practice. Validated via a purpose-built rule builder, `AuthValidation.rules.checked($input, message)` (`js/app.js`), because a checkbox's `.val()` always returns `"on"` regardless of checked state, which the shared validation pipeline's other rule builders (built around reading `.val()`) can't distinguish — this rule closes over the element and reads `.is(':checked')` directly instead. Unticked by default (RA 10173/NPC compliance — no pre-ticked consent boxes); `register.php` independently re-validates the `privacy_consent` field server-side and rejects with 400 before any database work if it's missing or falsy, so the client-side check is a UX convenience, not the actual enforcement boundary.

---

## Appendix: Full List of Cross-Cutting Inconsistencies

**Shared chrome**
1. Navbar duplicated 6x (client), footer duplicated 6x (client) — with silent content/behavior drift between copies.
2. ~~Admin sidebar/topbar exists only on `admin-dashboard.php`; every other admin page has no nav chrome.~~ **Resolved (Admin Dashboard phase, Steps 1-2, 2026-08-20)** — `includes/admin_sidebar.php` is a real, shared, correctly-visible component on all seven admin pages; `includes/admin_topbar.php` (new in Step 2) is a shared partial used by all seven.
3. ~~`includes/header.php`/`footer.php` are near-empty wrappers, not a real shared layout.~~ **Resolved** — both files were deleted in Step 10 after Step 1 removed their only two includers (`admin_users.php`, `admin_vehicles.php`) and a repo-wide grep (literal and dynamic includes) confirmed zero remaining references.

**Dead code / dead CSS**
4. ~~`.metric-card` (admin KPI cards) has no corresponding CSS rule.~~ **Resolved (Step 3)** — `.metric-card` is now defined in `css/styles.css` using existing `:root` tokens, with a `prefers-reduced-motion`-guarded hover lift.
5. `--sidebar-bg`/`--sidebar-hover` custom properties are referenced in CSS but never defined.
6. `.vehicle-badges`/`.vehicle-actions`/`.btn-icon` (client) are fully styled with zero matching markup.
7. `.booking-step-indicator`/`.step-circle` (both sides) fully styled, zero matching markup.
8. `#bookingMultiModal` and its entire multi-step flow in `app.js` are unreachable — no markup exists to open it.
9. `js/printer.js`'s `#receiptModal` is confirmed unused by the live booking flow, which redirects to `receipt.php` instead — its `<script>` load was removed from `vehicles.php` in Vehicle Listing Modernization Step 7 (2026-08-09), and the file was deleted by UI Implementation Plan Phase 11 (2026-08-21).
10. `.card.3d` (should be `.card.three-d`) means index.php's carousel cards never get their intended 3D hover effect.
11. `.alert-floating` has no CSS rule despite being used as if it produces a floating/fixed alert.

**Duplicate/competing logic**
12. ~~Category-filter click handler exists both inline in `vehicles.php` and in `app.js` — bound to the same event.~~ **RESOLVED 2026-08-08** — `vehicles.php`'s inline copy and `#categoryBar` were removed and replaced by the server-side sidebar filter panel (Vehicle Listing Modernization Steps 4-5). `app.js`'s copy remains but is now unbound to any element on any page — see [BUGS.md](BUGS.md) item 12.
13. Voucher-loading logic duplicated between `voucher-manager.js` and `app.js`.
14. `showFloatingAlert()` and `renderNavbarAuth()` are each defined twice (once dead) in `app.js`/`voucher-manager.js`.

**Style/convention inconsistencies**
15. Four different admin row-action button color/style conventions across Vehicles/Users/Vouchers/Transactions tables — **still true** (Step 6 unified the confirmation/feedback path, not each table's button coloring, per the plan's scope).
16. ~~Two different admin modal+form DOM structures (form wraps whole modal vs. form wraps only the body).~~ **Resolved (Step 6)** — one convention (`<form>` wraps the entire `.modal-content`) now applies to all seven admin modals.
17. Two different admin CRUD submission conventions (page-POST-redirect vs. AJAX-JSON) — **still true**; Vehicles still uses page-POST-redirect (now correctly reaching its alerts, Step 5), the rest use AJAX. The `alert()`-on-response half of the old description is resolved (Step 6 replaced every `alert()`/`confirm()`).
18. ~~Booking-status badge color mapping differs between `admin-dashboard.php` and `view-all-data.php` for the same field.~~ **Resolved (Steps 3-4)** — both now use the identical status→color mapping.
19. Admin icon prefixes mixed (`fa fa-*` vs `fas fa-*`), sometimes in the same file — not addressed in this phase, still present.
20. `receipt.php` uses square buttons; every other client page uses `rounded-pill` — unchanged, customer-side, out of scope for this phase.
21. `about.php` uses emoji instead of Font Awesome for its 4 stat-card icons — unchanged, customer-side, out of scope for this phase.
22. ~~Two colspan/empty-state bugs in admin tables (`admin_users.php`, `admin-dashboard.php`).~~ **Resolved** — `admin_users.php`'s was already correct at analysis time (stale entry); `admin-dashboard.php`'s `colspan='5'`→`colspan='10'` fixed in Step 3. `view-all-data.php`'s separate missing-empty-state gap was also fixed, in Step 4.
23. `admin_vouchers.php` table has no DataTables init, unlike the other three admin tables (no search/sort/pagination) — **still true**, not addressed in this phase.

---

*This document is a current-state inventory only. Deciding which variant becomes "the" canonical component, and building the actual shared partials, is implementation work requiring separate approval per the working rules in `CLAUDE.md`.*
