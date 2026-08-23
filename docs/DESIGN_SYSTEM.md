# PMS Car Rental — UI / Design System Inventory

This document is a page-by-page inventory of every customer-facing and admin page, based on direct inspection of each file's HTML, the shared [css/styles.css](../css/styles.css), and the JavaScript that runs on each page. Nothing here is a redesign proposal — "Improvement Priority" ranks the *severity of what was found*, it does not prescribe a fix.

Global facts that apply to every page below, verified once here rather than repeated per page:
- Bootstrap 5.3.2, Font Awesome 6.4.2, and Animate.css 4.1.1 are loaded via CDN on every page.
- [css/styles.css](../css/styles.css) defines CSS custom-property design tokens (`--primary: #0F2A4D`, `--secondary: #2F6FED`, `--accent: #63A8FF`, `--background: #F8FAFC`, etc.) and is loaded on every page.
- Responsive breakpoints actually defined in `styles.css`: `991.98px`, `768px`, `575.98px`, `480px`, plus a `@media print` block — beyond these, pages rely entirely on Bootstrap's own grid/utility breakpoints.
- `styles.css` lines 240–336 contain a `@media (max-width: 991.98px) { @media (max-width: 991.98px) { ... } @media (max-width: 991.98px) { ... } @media (max-width: 991.98px) { ... } }` structure — three separate mobile-navbar media blocks are nested inside an outer block using the *same* breakpoint, rather than being three sibling blocks. Modern browsers support nested `@media` (the conditions simply AND together), so this is not broken, but it is redundant/confusing structure, verified directly in the file.

**Dark mode (System Enhancements initiative, Steps 8-10, 2026-08-21).** Every page listed below now supports a dark theme via Bootstrap 5.3's native `data-bs-theme` attribute mechanism (Decision D3), seeded from the OS's `prefers-color-scheme` and pinned to `localStorage` on the first manual toggle (Decision D1/D2). A dark token set is defined under `[data-bs-theme="dark"]` in `css/styles.css`, immediately after `:root`:

| Token | Light | Dark |
|---|---|---|
| `--background` | `#F8FAFC` | `#0F172A` |
| `--primary` | `#0F2A4D` | `#8FB8E8` |
| `--secondary` | `#2F6FED` | `#6FA0F5` |
| `--muted-foreground` | (Bootstrap default) | `#A3AFC2` |
| `--accent` | `#63A8FF` | `#8CC0FF` |
| `--accent-focus` | — | `#4DA6FF` |
| `--border` | (Bootstrap default) | `rgba(255,255,255,0.14)` |
| `--sidebar-bg` | `#ffffff` | `#1A2436` |
| `--sidebar-hover` | (Bootstrap default) | `rgba(143,184,232,0.15)` |

`--primary`/`--secondary` carry a documented dual-role conflict: they're used both as text colour (where the dark values above are correct) and as a solid white-text-bearing fill (`.cta-banner`, the footer, `.step-badge`, the active navbar pill, and JS-injected `.avatar-circle`) where the dark *text* value fails white-text contrast (~2:1). Those five specific selectors re-scope `--primary`/`--secondary` back to their light-mode literal values locally, rather than trying to find one dark value that serves both roles — see `css/styles.css`'s "Dark mode corrections" comment block for the full reasoning. A `.js-theme-toggle` button (44×44px, `#themeToggle` on client pages, `#adminThemeToggle` on admin pages) lives in the navbar/topbar on every page and is owned by `js/theme.js`.

**Contrast verification (Steps 5, 9, 10).** Every text/background pairing on all 13 original pages plus `privacy.php` was measured live in both themes via an automated WCAG 2.1 AA contrast scanner (not sampled) — full page-by-page findings and fixes are in [CHANGELOG.md](../CHANGELOG.md)'s Step 9/10 entries, not duplicated here. The two headline fixes: an unscoped `.modal-header { background-color: #f8f9fa; }` rule that forced every modal header light regardless of theme (Step 9), and an unscoped `.table th` rule with the same problem on admin tables (Step 10). Bootstrap 5.3 does not dark-adjust its own brand-colour utilities (`.text-success`, `.text-danger`, `.btn-outline-*`, `.progress-bar` contextual fills) — every such fix reuses Bootstrap's own `-text-emphasis` custom properties rather than inventing new colours, keeping the token set closed.

**Glassmorphism removed (Step 5).** The `.glassmorph` class and its `body.dark .glassmorph` counterpart have been deleted from `css/styles.css` entirely; every consumer (the Home search widget, three feature cards, three auth modals) now uses solid `bg-body`/`Bootstrap`-standard surfaces instead. `.cta-banner`'s translucent scrim (Additional Finding A4) was flattened to a solid `background: var(--primary)` in the same step (Decision D6). Any "glassmorphism variant" wording elsewhere in this document is stale as of 2026-08-21.

**Touch-target standard — now complete across every control type (UI Implementation Plan, Phases 9 and 12).** The project's minimum interactive-control size is **44×44px**, established by [BUGS.md](BUGS.md) item 25 and extended in three passes: `#adminLayout .btn-sm` and admin `.page-link` (Admin Dashboard Step 9), client-side `.btn:not(.btn-sm):not(.btn-close)` and `.page-link` (Phase 9, item 34), and finally **`.btn-close`** (Phase 12, item 28) — which had been the last documented exception at Bootstrap's default 32×32px. Every modal, offcanvas header and dismissible alert close button in the project, client and admin alike, now measures 44×44px. Two details of that last rule are non-obvious and load-bearing: `box-sizing: border-box` is required because Bootstrap ships `.btn-close` as `content-box` with `.25em` padding (width/height alone yields 60×60px), and `flex-shrink: 0` is required because the admin CRUD modals place their validation-alert close button inside a `d-flex` row, where it was being compressed to 22px wide despite an explicit `width: 44px`. Documented exceptions that remain deliberate: the signup privacy-consent checkbox glyph renders at 24×24px, with the wrapping `<label>` (~230×192px) serving as the real touch target.

**Contrast status (UI Implementation Plan, Phase 12, 2026-08-22) — clean in both themes.** A per-page automated WCAG 2.1 AA sweep — compositing the full ancestor background stack *and* foreground alpha, and applying the large-text 3:1 threshold rather than a flat 4.5:1 — was run on a clean page load in **each theme separately** (in-page `data-bs-theme` flipping produces false readings and must not be used). Result after this phase's fixes: **zero failures in either theme** on `index.php`, `vehicles.php`, `about.php`, `faq.php`, `privacy.php` and `transactions.php`. Four items closed:

- **[BUGS.md](BUGS.md) item 39** — `.vehicle-specs`, a hardcoded light-mode grey measuring 2.04:1 in dark mode that the Steps 9/10 dark sweeps missed. Fixed with `var(--muted-foreground)`, matching its two already-corrected neighbours.
- **[BUGS.md](BUGS.md) item 41** — **two brand-colour changes, approved by the project owner** (see the token table below).
- **[BUGS.md](BUGS.md) item 31** — closed by measurement, no code change: `.text-body-secondary` passes at 15.43:1, and `--muted-foreground`'s light value is applied by nothing (all five consumers are `[data-bs-theme="dark"]`-scoped).
- **[BUGS.md](BUGS.md) item 42** — `privacy.php` had no theme bootstrap at all (see the correction below).

**Palette change (2026-08-22, approved).** Two light-mode values were darkened to clear WCAG AA; **dark mode was not touched** and is confirmed still on Bootstrap's own `#6ea8fe` links and `--secondary: #6FA0F5`:

| Token | Old | New | On `--background` | Note |
|---|---|---|---|---|
| `--secondary` | `#2F6FED` | **`#2159C7`** | 4.35 → **6.05** | Safe in both its text and white-text-fill roles; white-on-it rises 4.55 → 6.33 |
| `--bs-link-color` (+ `-rgb`) | `#0d6efd` | **`#0a58ca`** | 4.30 → **6.15** | Bootstrap's own darker ramp step, not a new colour |
| `--bs-link-hover-color` (+ `-rgb`) | `#0a58ca` | **`#084298`** | — | Bootstrap's own `$primary-text-emphasis` |
| `--sidebar-hover` | `rgba(47,111,237,.1)` | `rgba(33,89,199,.1)` | — | Tracks `--secondary`; hover background only, no text |

Two mechanics are essential if this is ever revisited: **`--bs-link-color-rgb` is what actually colours `<a>`** (Bootstrap uses `rgba(var(--bs-link-color-rgb), …)`; setting only the named variable was verified to do nothing), and the override **must** be scoped `:root:not([data-bs-theme="dark"])` — this stylesheet loads after Bootstrap, so a bare `:root` would win in dark mode too and force a dark navy link onto a near-black background.

**Correction to the "all 14 pages" claim below.** The Confirmation-modals/theme paragraph previously asserted the no-flash `<script>` was "the literal first child of `<head>` on all 14 pages". That was **false for `privacy.php`**, which had neither that script nor `js/theme.js` — so it ignored the stored theme entirely and rendered a `#themeToggle` button (inherited from the shared navbar partial) that did nothing when clicked. Fixed 2026-08-22; all seven client pages now have exact parity, verified by grep. The claim is accurate as of that date, but it was a claim, not a verified fact, when originally written.

**Printing (UI Implementation Plan, Phase 12, 2026-08-22).** There is exactly one `@media print` block in the project, and it is scoped entirely to `body.receipt-page .receipt-card` — `receipt.php` is the only printable surface. Every other page prints its normal content. This replaced an unscoped block that hid `body *` on every page and un-hid an element (`#receiptModal`) that no longer existed after Phase 11 deleted `js/printer.js`, which meant **every page printed blank** ([BUGS.md](BUGS.md) item 26). If a second printable surface is ever added, it must opt in with its own `body` class rather than widening this rule.

**Confirmation modals (Steps 1, 3, 4).** A shared `js/confirm.js` module (`window.PMSConfirm`) provides a single Bootstrap-modal-based confirmation dialog (`#adminConfirmModal`, `role="alertdialog"`) used by both client and admin pages, replacing every native `confirm()` in the codebase. Eight call sites across client and admin pages use it (see [COMPONENT_LIBRARY.md](COMPONENT_LIBRARY.md) for the full pattern).

---

## 1. Home

- **Purpose:** Landing page — hero banner, feature highlights, a featured-cars carousel, and the shared login/signup modals.
- **Route:** `index.php`
- **Files Used:** [index.php](../index.php)
- **CSS Files:** `css/styles.css`, Bootstrap 5.3.2 CDN, Font Awesome 6.4.2 CDN, Animate.css 4.1.1 CDN
- **JavaScript Files:** `js/app.js`; inline `<script>` for jQuery/Bootstrap CDN load only (no page-specific inline logic beyond what's in `app.js`)
- **Bootstrap Components:** navbar (`navbar-expand-md fixed-top`), carousel (`carousel-fade`, `carousel-item`, indicators/controls), cards (`card`, solid `bg-body` surface — the glassmorphism variant was removed in the System Enhancements initiative's Step 5), modals (login, signup, 3 "quick view" vehicle modals), buttons (`btn-warning`, `btn-outline-info`, pill buttons), grid (`row`/`col-md-4`)
- **Forms:** Login form (email, password) and Signup form (name, email, password, confirm password) — both inside modals, both client-validated only via `required` attributes plus JS in `app.js`.
- **Tables:** None.
- **Cards:** Three feature-highlight cards ("Wide Selection," "24/7 Access," "Trusted & Safe"); three featured-vehicle carousel cards (Toyota Fortuner, Kymco Scooter, Mitsubishi Mirage) — all three are **hardcoded**, not pulled from the `vehicles` table (confirmed: no DB query exists in `index.php`).
- **Modals:** Login modal, Signup modal, and three "Quick View" modals (`#quickViewXpander`, `#quickViewAccord`, `#quickViewMirage`) tied to the hardcoded carousel cards.
- **Navigation:** Top navbar (Home/Vehicles/FAQ/About Us/Transactions) plus a dynamic `#navbarAuthArea` populated client-side by `app.js` after checking `me.php`; footer with company blurb and contact list.
- **Current UI Problems:** Featured-cars carousel content is static/hardcoded and can drift from actual `vehicles` table data (a featured car could be sold out, price-changed, or deactivated without this page reflecting it). The hero background video (`assets/car-video-model-compressed.mp4`) autoplays with `preload="none"` and `loading` deferred via a `lazy-video` class, but no `<noscript>`/static-image fallback was found for browsers or users who block video.
- **Responsiveness Issues:** Relies on the shared navbar media queries described above (functional, if redundantly structured). The hero section uses `min-height: min(calc(100vh - 56px), 600px)` which was not tested at very short mobile viewport heights (landscape phones) as part of this inspection — flagged as unverified rather than confirmed broken.
- **Accessibility Issues:** The hero `<video>` has no captions/track and no visible pause control (autoplay + loop + muted, which mitigates but does not eliminate motion-sensitivity concerns since Bootstrap's `prefers-reduced-motion` handling is not itself customized here). The three hardcoded carousel cards' "Quick View" buttons open modals whose content is a single unlabeled `<div>` of bullet text rather than a heading-structured description. Login/Signup buttons rendered by `app.js` (`renderNavbarAuth()`) are appended without any preceding accessible name change relative to the logged-in state beyond visible text, which is minor but confirmed as the only cue.
- **Improvement Priority:** Medium — the static/DB-data mismatch on the carousel is the most concrete functional issue found on this page.

---

## 2. Vehicles

- **Purpose:** Browse all active vehicles, filter by category, and start the booking flow.
- **Route:** `vehicles.php`
- **Files Used:** [vehicles.php](../vehicles.php)
- **CSS Files:** `css/styles.css`, Bootstrap 5.3.2, Font Awesome 6.4.2, Animate.css 4.1.1
- **JavaScript Files:** `js/app.js`, `js/voucher-manager.js`, `js/booking-validation.js`, `js/confirm.js`, `js/theme.js` (**corrected 2026-08-22:** `js/printer.js` was listed here but its `<script>` load was removed from this page during Vehicle Listing Modernization Step 7, and the file itself was deleted as confirmed dead by UI Implementation Plan Phase 11, 2026-08-21), plus a large page-specific inline `<script>` block (category filtering, login-check, booking preview/confirm handlers) that duplicates logic also present in `app.js`.
- **Bootstrap Components:** navbar, booking modal (`modal-lg`), category filter buttons (`btn-outline-primary`/`btn-outline-secondary` toggle group), cards (`vehicle-card`), badges (`bg-success`/`bg-danger` for availability), login/signup modals, grid (`row g-4`, `col-md-4`).
- **Forms:** Booking form inside `#bookingModal` — rental/return date, pickup/dropoff time, contact number, age (min 18), license file upload (JPG/PNG, ≤5MB client-checked), voucher select, amount-paid input (revealed only after a successful preview). Login and Signup forms (shared modal markup, duplicated from `index.php`).
- **Tables:** None.
- **Cards:** One `vehicle-card` per active vehicle, rendered from a PHP loop over the `vehicles` table (title, category, seats, fuel, transmission, price, availability badge, Reserve/Unavailable button).
- **Modals:** Booking modal (multi-section: form → preview → receipt display), Login modal, Signup modal. A second, unused trigger (`#openBookingMultiModal`, "Book Now (Multi-Step)") points at a `#bookingMultiModal` modal that does not exist anywhere in this file's markup (see [BUGS.md](BUGS.md), Broken Links).
- **Navigation:** Same shared navbar/footer pattern as `index.php` (duplicated markup, not a shared include — see [ARCHITECTURE.md](ARCHITECTURE.md)).
- **Current UI Problems:** A large block (lines 231–481) of hardcoded, commented-out static vehicle cards remains in the file, superseded by the live PHP loop directly below it (see [BUGS.md](BUGS.md), Deprecated Code) — dead weight in the source, not visibly rendered, but present. The `#btnPreview` handler references an undeclared `amount_paid` variable, which throws a JS error caught only as a generic "Error generating preview" message (see [BUGS.md](BUGS.md), Verified Bug #3). The "Book Now (Multi-Step)" button is non-functional (targets a modal that doesn't exist on this page).
- **Responsiveness Issues:** Category filter buttons have dedicated mobile-size rules at `768px` and `480px` breakpoints in `styles.css` (`#categoryBar .btn` font-size/padding steps down twice) — confirmed present and structured normally (not nested redundantly like the navbar block). No responsiveness problem confirmed beyond what's already noted for the shared navbar.
- **Accessibility Issues:** The license-file `<input type="file">` has a visible label and a `form-text` hint, but no `aria-describedby` linking the hint to the input explicitly (relies on default DOM proximity only). Availability badges convey status via color plus text ("Available"/"Unavailable"), which is an accessible pattern (not color-only). The disabled "Unavailable" reserve button uses the `disabled` HTML attribute (correctly removes it from the tab order) — confirmed a correct pattern here.
- **Improvement Priority:** High — this is the primary booking entry point and has two confirmed JS-breaking bugs on this exact page (preview failure, non-functional multi-step button).

---

## 3. FAQ

- **Purpose:** Static list of frequently asked questions in an accordion.
- **Route:** `faq.php`
- **Files Used:** [faq.php](../faq.php)
- **CSS Files:** `css/styles.css`, Bootstrap 5.3.2, Font Awesome 6.4.2, Animate.css 4.1.1
- **JavaScript Files:** `js/app.js`; one inline `<script>` defining an unused `toggleDarkMode()` function (no button/control on this page calls it).
- **Bootstrap Components:** navbar, accordion (`accordion`, `accordion-item`, `accordion-button`, `accordion-collapse`), login/signup modals.
- **Forms:** Login and Signup modal forms only (shared markup).
- **Tables:** None.
- **Cards:** None.
- **Modals:** Login modal, Signup modal (shared markup, duplicated from `index.php`/`vehicles.php`).
- **Navigation:** Same shared navbar/footer pattern.
- **Current UI Problems:** The inline `toggleDarkMode()` function is dead code — no toggle control exists on this or any other page inspected that calls it, and no `body.dark`-driven visual system beyond a few CSS rules (`body.dark .navbar .nav-link`, confirmed in `styles.css`) is otherwise wired up anywhere.
- **Responsiveness Issues:** None found specific to this page — accordion is a standard Bootstrap component and reflows normally under the grid/container rules already covered.
- **Accessibility Issues:** Bootstrap's accordion markup provides correct `aria-expanded`/`data-bs-toggle` semantics by default, confirmed present. No issues specific to this page beyond the shared navbar/modal patterns already noted.
- **Improvement Priority:** Low — static content page with no functional bugs found.

---

## 4. About Us

- **Purpose:** Company story, static feature highlights, team member photos, and a login-gated contact form.
- **Route:** `about.php`
- **Files Used:** [about.php](../about.php)
- **CSS Files:** `css/styles.css`, Bootstrap 5.3.2, Font Awesome 6.4.2, Animate.css 4.1.1
- **JavaScript Files:** `js/app.js`; page-specific inline `<script>` handling `#contactForm` submission (login-gate check, then AJAX POST to `save_message.php`).
- **Bootstrap Components:** navbar, cards (feature highlights, team-member circular photo cards), rounded-pill form inputs, login/signup modals, alert (`#contactAlert`).
- **Forms:** Contact form (name, email, message) — name/email are pre-filled and `readonly` when a user session exists (server-rendered from `$_SESSION['user']`), editable otherwise.
- **Tables:** None.
- **Cards:** Four static feature cards (emoji icon + heading + text); five team-member cards, each a circular photo + name + role placeholder ("TBA" for every member, confirmed for all five).
- **Modals:** Login modal, Signup modal (shared markup).
- **Navigation:** Same shared navbar/footer pattern.
- **Current UI Problems:** One team-member photo (`assets/quider.png`, for "Basil Jhudi Quider") is a broken image link — the file does not exist in `assets/` (see [BUGS.md](BUGS.md), Verified Bug #4). All five team members' role/title fields display the literal placeholder text "TBA" rather than actual role information. The contact form's success message (`#contactAlert`, hardcoded to a success-styled alert in the HTML) is shown/hidden via inline JS regardless of actual AJAX result in one code path — the inline script does correctly branch success/failure via `response.success`, but the static HTML alert block itself is pre-styled as `alert-success` only, requiring class-swapping at runtime to show an error state.
- **Responsiveness Issues:** Team-member grid uses `row-cols-1 row-cols-md-2`, a standard responsive Bootstrap pattern — no issue found. The decorative background logo (`position-absolute end-3 top-3 ... opacity:0.12`) is not confirmed to reflow safely at very narrow widths (not tested as part of static inspection).
- **Accessibility Issues:** Team member `<img>` tags have `alt` text set to the person's first name only (e.g. `alt="fontanoza"`, `alt="quider"`) rather than a full descriptive name — present but minimal. The contact form's `readonly` fields when logged in provide no visible styling difference beyond the browser default `readonly` appearance, and no `aria-readonly` or explanatory text accompanies it.
- **Improvement Priority:** Medium — the broken image and placeholder "TBA" text are both real, user-visible content defects on a page meant to represent the company/team.

---

## 5. Transactions (Customer Dashboard)

*Rewritten for the Customer Dashboard phase (Phase 7, 2026-08-14 to 2026-08-19). `transactions.php` is now the customer's full self-service dashboard: bookings, profile, and contextual alerts in one page. Prior findings below are superseded where noted; see [CUSTOMER_DASHBOARD_IMPLEMENTATION_PLAN.md](CUSTOMER_DASHBOARD_IMPLEMENTATION_PLAN.md) for the step-by-step history.*

- **Purpose:** Logged-in customer's dashboard — booking metrics, active/upcoming and completed booking history with status-correct actions, contextual status alerts, and self-service profile management (info edit, password change, picture upload).
- **Route:** `transactions.php`
- **Files Used:** [transactions.php](../transactions.php), [update_profile.php](../update_profile.php), [update_profile_picture.php](../update_profile_picture.php), [receipt.php](../receipt.php) (linked, status-aware)
- **CSS Files:** `css/styles.css`, Bootstrap 5.3.2, Font Awesome 6.4.2, Animate.css 4.1.1
- **JavaScript Files:** `js/app.js` (booking action handlers, `AuthValidation`'s profile-form consumer, avatar rendering, navbar); page-specific inline `<script>` for modal population and the cancel/return-early AJAX calls.
- **Bootstrap Components:** navbar (with avatar/initials-circle), 4-card metric grid, contextual `alert-dismissible` stack, `list-group`/`list-group-item` for bookings, `role="status"` badges (time-bucket + actual status), modals (Return Early, Cancel Booking, Return Receipt — License Preview modal removed in Step 3), two-column profile forms, login/signup modals.
- **Forms:** Cancel and Return-Early modals remain confirmation dialogs with hidden ID fields only. Three new profile forms (Profile Information, Change Password, Profile Picture upload) — see [COMPONENT_LIBRARY.md](COMPONENT_LIBRARY.md) §8 for full field-level detail.
- **Tables:** None (list-group used instead of a table for booking rows — this is the "Responsive Tables" Phase 7 task, reinterpreted and closed via Step 6's responsive pass on these rows).
- **Cards:** Four metric cards — Active Rentals count, Completed Rentals count, Total Spent (sum across all bucketed bookings), Next Rental (nearest upcoming `rental_date` among active bookings). `row g-3 g-lg-4`, `col-sm-6 col-xl-3`.
- **Alerts:** A dismissible alert stack above the booking sections — derived, non-persistent "reminder" (rental within 24h), "confirmed" (within 48h of `created_at`), and "cancelled" (within 48h of `created_at`) notices (Option B notifications, Step 5). Separately, Cancel/Return-Early actions now give Bootstrap alert feedback (`alert-success`/`alert-danger`) in place of the previous native `alert()`.
- **Modals:** Return Early modal (leads into a receipt-confirmation modal, then auto-reload), Cancel Booking modal, Return Receipt modal, Login modal, Signup modal. The orphaned License Preview modal (dead trigger, `<!-- license preview removed -->` comments) was deleted in Step 3.
- **Navigation:** Same shared navbar/footer pattern, plus a "My Profile" link (→ `transactions.php#profile`) in the auth dropdown and the navbar avatar/initials-circle component (shared with `admin_users.php`'s Avatar column).
- **Current UI Problems (resolved):** [BUGS.md](BUGS.md) items 10 and 11 are both marked RESOLVED as of this Final Review — action buttons are driven by actual `bookings.status`, not time-computed buckets, and the `js/app.js:51` crash guard from Step 1 has held with no regressions across the whole phase.
- **Responsiveness:** Booking rows now use `d-flex flex-column flex-md-row` (was a fixed horizontal layout) — confirmed stacking correctly at 375px during Step 6 and re-verified in this Final Review at 375/768/992/1400px, no horizontal scroll. Profile section is single-column below `lg`, two-column (`col-lg-6`/`col-lg-6`) at `lg`+.
- **Accessibility:** Status/time badges use both color and `role="status"` text (WCAG-friendly). `bg-primary` badge/text combination measures 4.50:1 contrast — right at the WCAG AA 4.5:1 threshold with no better fix available in the current token set (noted, not a defect; see [BUGS.md](BUGS.md) Notes). All three profile-section submit buttons were brought up to the page's `py-3`/44px touch-target convention during this Final Review (previously ~37-41px, below the Step 6-claimed minimum for that section specifically).
- **Improvement Priority:** Low — this phase's acceptance criteria are met; see [CHANGELOG.md](../CHANGELOG.md) for the full phase-summary entry.

---

## 6. Receipt

- **Purpose:** Standalone view of a single booking's full receipt, looked up by ID or reference.
- **Route:** `receipt.php?id=...` or `receipt.php?ref=...`
- **Files Used:** [receipt.php](../receipt.php)
- **CSS Files:** `css/styles.css`, Bootstrap 5.3.2, Font Awesome 6.4.2; one small page-specific inline `<style>` block (`.receipt-card { max-width: 900px; margin: 0 auto; }`, `body { padding-top: 60px; }`).
- **JavaScript Files:** None — this page has no `<script>` tags at all beyond what Bootstrap/jQuery would need for its non-interactive markup (and it does not even load jQuery or Bootstrap's JS bundle, confirmed — only the CSS `<link>` tags are present).
- **Bootstrap Components:** Minimal navbar (brand + two buttons, not the full nav-link menu used elsewhere), card (`receipt-card`), grid (`row`/`col-md-6`).
- **Forms:** None.
- **Tables:** None.
- **Cards:** One receipt card containing all booking details.
- **Modals:** None.
- **Navigation:** A reduced navbar (logo/brand + "Back to Vehicles" + "My Transactions" buttons only) — not the same shared navbar markup used on the other customer pages; this is a distinct, third navbar variant.
- **Current UI Problems:** No login/signup modal exists on this page, so a session-expired user landing here via a stale link is redirected server-side to `login.php`, a JSON-only endpoint, not a form — a customer-side issue, unrelated to and unaffected by the Admin Dashboard phase's deletion of `includes/header.php` (which carried the analogous admin-only redirect mismatch, now resolved — see [BUGS.md](BUGS.md) item 5's annotation).
- **Responsiveness Issues:** None found — this is a simple two-column `row`/`col-md-6` layout with no custom breakpoints, relying entirely on Bootstrap's default grid collapse.
- **Accessibility Issues:** No `<script>`/interactive elements to assess. Semantic structure (`<h4>`, `<h5>`, `<p><strong>`) is reasonably used for a document-style page.
- **Improvement Priority:** Low functionally, but the login-redirect target (`login.php`, a JSON endpoint) applies here too and is already flagged at higher priority elsewhere.

---

## 7. Admin Login

- **Purpose:** Separate authentication entry point for admin/staff accounts.
- **Route:** `admin-login.php`
- **Files Used:** [admin-login.php](../admin-login.php)
- **CSS Files:** `css/styles.css`, Bootstrap 5.3.2 (only these two — no Font Awesome/Animate.css loaded on this page, unlike every customer-facing page).
- **JavaScript Files:** None — no `<script>` tag of any kind in this file; it is a plain server-rendered form with no client-side validation beyond native HTML `required` attributes.
- **Bootstrap Components:** Card-style centered box (`bg-white rounded-4 shadow p-4`), form controls, alert (`alert-danger`, shown conditionally on failed login).
- **Forms:** Admin login form (email, password), plain `method="POST"` (no AJAX/JSON — full page reload on submit, unlike the customer login flow).
- **Tables:** None.
- **Cards:** One login card.
- **Modals:** None.
- **Navigation:** No navbar at all — just the logo, the login card, and a "Back to Home" link.
- **Current UI Problems:** None found — this is the simplest page in the codebase and matches its own stated behavior exactly (server-rendered error message on failed login, redirect on success).
- **Responsiveness Issues:** None found — single centered card with inline `min-width`/`max-width` styling (`min-width:320px;max-width:380px;`), no custom breakpoints needed or present.
- **Accessibility Issues:** Form inputs have associated `<label for="...">` elements matching input `id`s (confirmed correct pairing for both email and password fields) — a correct pattern, notably more consistent than some of the admin CRUD forms found elsewhere (see Admin Voucher Management below).
- **Improvement Priority:** Low — no confirmed issues.

---

## 8. Admin Dashboard

- **Purpose:** Post-login landing page for admins — summary metrics, recent bookings with inline confirm action, recent contact messages.
- **Route:** `admin-dashboard.php`
- **Files Used:** [admin-dashboard.php](../admin-dashboard.php) — builds its own full HTML document. `includes/header.php`/`footer.php` never applied here and were deleted project-wide in the Admin Dashboard phase (Step 10); the "unlike several other admin pages" framing is stale — **all** admin pages now share `includes/admin_sidebar.php` + `includes/admin_topbar.php` instead (Steps 1-2).
- **CSS Files:** `css/styles.css`, Bootstrap 5.3.2, Font Awesome 6.4.2, DataTables Bootstrap5 CSS — **now actually initialized on this page** (Step 3 added `$('#messagesTable').DataTable(...)` for Recent Messages; the "loaded but unused" finding below is resolved for that table).
- **JavaScript Files:** jQuery 3.7.1, Bootstrap JS bundle, DataTables JS; admin JavaScript now lives in the shared `js/admin.js` (Step 6) rather than a large page-specific inline `<script>`. The dead `.editVehicleBtn` handler and the duplicate `editUserModal` markup described below were deleted in Step 6, not merely noted.
- **Bootstrap Components:** Sidebar nav (`#adminSidebar`), shared topbar/breadcrumb (`includes/admin_topbar.php`, Step 2), four metric cards (Step 3 added a fourth), table (`table table-hover`), DataTables-enhanced Recent Messages table (Step 3), modal (`#editUserModal`, now the sole live copy — the dashboard's duplicate was deleted, see below).
- **Forms:** Edit User form inside `#editUserModal` (name, email) — **removed from this page in Step 6** (dead duplicate; the one live copy is on `admin_users.php`, see §10 below).
- **Tables:** Recent Transactions table (5 rows max, joined booking/user/vehicle data, inline Confirm button for pending rows, correct `colspan` and correct status badges as of Step 3); Recent Messages table (**`LIMIT 5` + DataTables added in Step 3** — previously uncapped).
- **Cards:** **Four** metric cards (Total Cars, Total Users, Active Rentals, Pending Bookings — Step 3 relabeled "Total Bookings"→"Active Rentals" to match its query and added the fourth card). A Business Overview panel (Revenue by Month, Bookings by Status, Top Vehicles) was also added below the cards in Step 7.
- **Modals:** Edit User modal — **removed** (see Forms, above); the dashboard's `.editVehicleBtn` dead handler was also removed (Step 6).
- **Navigation:** Sidebar (Dashboard, Manage Vehicles, User Accounts, Vouchers, Transactions, Settings, Logout — a Settings link was added in Step 8) + shared topbar (Step 2) — this is the admin-wide navigation pattern, distinct from the customer navbar.
- **Current UI Problems:** ~~The status badge color logic never matches real lowercase enum values...~~ **Resolved in Step 3** — badges now use `match (strtolower(...))` keyed on all four real enum values. ~~The `.editVehicleBtn` click handler...dead code...~~ **Resolved in Step 6** — deleted, along with the duplicate dead `editUserModal`. ~~DataTables CSS/JS is loaded on this page but never initialized/used...~~ **Resolved in Step 3** for Recent Messages.
- **Responsiveness Issues:** ~~The fixed-width sidebar (`width:250px` inline style, toggled via jQuery `.css('width', ...)`)...~~ **Stale/superseded.** The sidebar is now Bootstrap's native `offcanvas-lg` (Step 1's visibility fix), not a JS-driven width toggle — drawer below 992px, static ≥992px, matching the customer-facing pattern's use of Bootstrap-native mechanisms rather than diverging from it.
- **Accessibility Issues:** The sidebar toggle button's `aria-label="Toggle sidebar"` is preserved. The sidebar is now wrapped in `<nav aria-label="Admin navigation">` with `aria-current="page"` on the active link (Step 2). Confirming a booking still triggers a full reload rather than an in-place update; no `aria-live` region was added around the metric cards or tables in this phase.
- **Improvement Priority:** Low — the badge-color bug, the dead `.editVehicleBtn` handler, the DataTables/Recent-Messages gap, and the sidebar's non-native responsive mechanism are all resolved as of this phase.

---

## 9. Manage Vehicles (Admin)

- **Purpose:** List, add, edit, and delete vehicle inventory.
- **Route:** `admin_vehicles.php`
- **Files Used:** [admin_vehicles.php](../admin_vehicles.php); actions handled by [admin_add_vehicle.php](../admin_add_vehicle.php), [admin_edit_vehicle.php](../admin_edit_vehicle.php), [admin_delete_vehicle.php](../admin_delete_vehicle.php). ~~`includes/header.php`/`includes/footer.php`~~ — **removed in Step 1** (this page previously included both, producing two nested HTML documents; it now builds one valid document using `includes/admin_sidebar.php` + `includes/admin_topbar.php`, and the two files it used to include were deleted project-wide in Step 10).
- **CSS Files:** `css/styles.css`, Bootstrap 5.3.2, Font Awesome 6.4.2, DataTables Bootstrap5 CSS (actually initialized here via `$('#vehiclesTable').DataTable(...)`, unlike the Dashboard page).
- **JavaScript Files:** jQuery 3.7.1, Bootstrap JS bundle, DataTables JS; page-specific inline `<script>` for DataTables init and the Edit-modal population handler.
- **Bootstrap Components:** table (`table-striped`, DataTables-enhanced), two modals (Add Vehicle, Edit Vehicle), badges (Available/Unavailable), buttons.
- **Forms:** Add Vehicle form (title, category select, price, units, seats, fuel select, transmission select, thumbnail file, details textarea, active checkbox) — `multipart/form-data`, plain POST to `admin_add_vehicle.php` (full page reload, not AJAX). Edit Vehicle form — same field set plus a hidden `id`, also plain POST (not AJAX) to `admin_edit_vehicle.php`.
- **Tables:** Vehicles table (ID, Name, Category, Price/Day, Units, Status badge, Image thumbnail, Actions).
- **Cards:** None (table-based layout, wrapped in a single `.card`).
- **Modals:** Add Vehicle modal, Edit Vehicle modal.
- **Navigation:** Shared admin sidebar (`includes/admin_sidebar.php`) + shared topbar (`includes/admin_topbar.php`, Step 2) — **not** `includes/header.php`, which contained no sidebar or topbar markup even before its Step 10 deletion.
- **Current UI Problems:** ~~Delete...no server-side confirmation step...no warning about cascade...~~ **Partially resolved in Step 5** — the delete confirmation now discloses the booking/transaction cascade in its text (still a client-side `confirm()`, since the underlying `admin_delete_vehicle.php` guard itself is out of this phase's scope). ~~The Edit modal's Category `<option>`s have no `value` attribute...~~ **Resolved in Step 5** — aligned to explicit `value="Sedan"` etc., matching the Add modal. Every vehicle success alert (Add/Edit/Delete) is now reachable — previously all three endpoints redirected to `admin-dashboard.php`, which read none of the alert parameters (**resolved in Step 5**, redirect targets corrected to `admin_vehicles.php`). The discarded `details` textarea (silently ignored by the INSERT) was **removed in Step 5**.
- **Responsiveness Issues:** Table uses `table-responsive` (confirmed, wraps the table in a horizontally-scrollable container on narrow viewports) — a correct, present pattern.
- **Accessibility Issues:** ~~Thumbnail `<img>` tags...have no `alt` attribute at all...~~ **Resolved in Step 5** — thumbnails now carry the vehicle's title as `alt` text. Action buttons now carry `aria-label`s naming the specific vehicle (Step 5).
- **Improvement Priority:** Low — the cascade-warning, alt-text, and unreachable-alert issues here are all resolved as of this phase.

---

## 10. User Accounts (Admin)

- **Purpose:** List, edit, and delete customer accounts.
- **Route:** `admin_users.php`
- **Files Used:** [admin_users.php](../admin_users.php); actions handled by [admin_update_user.php](../admin_update_user.php), [admin_delete_user.php](../admin_delete_user.php). ~~`includes/header.php`/`includes/footer.php`~~ — **removed in Step 1** (same double-doctype fix as Manage Vehicles, above; both files deleted project-wide in Step 10).
- **CSS Files:** `css/styles.css`, Bootstrap 5.3.2, Font Awesome 6.4.2, DataTables Bootstrap5 CSS.
- **JavaScript Files:** jQuery 3.7.1, Bootstrap JS bundle, DataTables JS; page-specific inline `<script>` for DataTables init, Edit modal population, AJAX edit submit, and AJAX delete with a loading-spinner button state.
- **Bootstrap Components:** table (DataTables-enhanced), modal (Edit User), spinner (`spinner-border spinner-border-sm`) shown during AJAX submit/delete.
- **Forms:** Edit User form (name, email) inside a modal, submitted via AJAX (`$.ajax` to `admin_update_user.php`) — the only form-submission on this page that is AJAX-based rather than a full page POST, unlike the Vehicles page's Add/Edit forms.
- **Tables:** Users table (ID, Name, Email, Role badge, Joined date, Actions) — query explicitly excludes `role != 'admin'`.
- **Cards:** None.
- **Modals:** Edit User modal.
- **Navigation:** Shared admin sidebar (`includes/admin_sidebar.php`) + shared topbar (`includes/admin_topbar.php`, Step 2).
- **Current UI Problems:** Delete still has no cascade warning about the user's bookings/transactions — not addressed in this phase (out of this phase's scope; the underlying `admin_delete_user.php` ID-space bug remains open, requiring separate business-rule approval per the plan). No password-reset/override action for admins to help a locked-out customer — still absent; not addressed in this phase (an admin's own password change was added in Step 8, but that is self-service, not an admin-assisted reset for a customer).
- **Responsiveness Issues:** `table-responsive` wrapper present, consistent with other admin tables.
- **Accessibility Issues:** ~~Edit/Delete action buttons use icon-only content...with no `aria-label`...~~ **Resolved (Step 9)** — `.edit-user`/`.delete-user` now carry descriptive `aria-label`s naming their target (e.g. "Edit peter parking").
- **Improvement Priority:** Low — the icon-only-button accessible-name gap is resolved as of Step 9; the remaining findings are explicitly out of this phase's scope.

---

## 11. Voucher Management (Admin)

- **Purpose:** Create, edit, and delete discount vouchers.
- **Route:** `admin_vouchers.php`
- **Files Used:** [admin_vouchers.php](../admin_vouchers.php) — builds its own document (like the Dashboard page); never included `includes/header.php`/`footer.php` in the first place, and always used `includes/admin_sidebar.php` (see Navigation, below — this section's prior claims about a missing sidebar were a documentation error, not a description of a real prior state).
- **CSS Files:** `css/styles.css`, Bootstrap 5.3.2, Font Awesome 6.4.2 (no DataTables CSS/JS on this page — the voucher table is plain, unpaginated HTML).
- **JavaScript Files:** jQuery 3.7.1, Bootstrap JS bundle; page-specific inline `<script>` for Add/Edit/Delete AJAX calls, using raw `FormData`/`JSON.parse(response)` rather than jQuery's `dataType: 'json'` auto-parsing used elsewhere.
- **Bootstrap Components:** table, two modals (Add Voucher, Edit Voucher), badges (usage status: green if under limit, red "Maxed Out" if at/over limit).
- **Forms:** Add Voucher form (code, discount percentage, discount amount, usage limit). Edit Voucher form (same fields plus hidden id) — neither form includes an `is_active` control (see [BUGS.md](BUGS.md), Incomplete Implementations).
- **Tables:** Vouchers table (ID, Code, Discount %, Discount ₱, Usage badge, Created At, Actions) — plain HTML table, no DataTables search/sort/pagination despite being visually similar to the DataTables-enhanced tables on other admin pages.
- **Cards:** None.
- **Modals:** Add Voucher modal, Edit Voucher modal.
- **Navigation:** **Corrected — this section previously stated there was no sidebar on this page ("only a single 'Back to Dashboard' link").** A repo-wide grep for the literal string "Back to Dashboard" returns zero matches anywhere in the codebase; no such link ever existed. `admin_vouchers.php` always included `includes/admin_sidebar.php` and rendered the same topbar+breadcrumb pattern as the Dashboard page — it was never visually inconsistent with its siblings on navigation, only on its table (see below). It now shares `includes/admin_topbar.php` (Step 2) like every other admin page.
- **Current UI Problems:** ~~Duplicate `id="usage_limit"`...the Usage Limit field is never correctly pre-filled when editing...~~ **Resolved in Step 6** ([BUGS.md](BUGS.md) item 9) — the Edit modal's input is now `id="edit_usage_limit"`, matching the populate script, and both labels' `for` attributes point at their own modal's input. Still no control to toggle `is_active` — not addressed in this phase. The table still has no DataTables (see Responsiveness/Tables, below) — not addressed in this phase.
- **Responsiveness Issues:** Table has a `table-responsive` wrapper (confirmed present), consistent with other admin tables. It remains the only admin list with no DataTables search/sort/pagination — that specific inconsistency (not a navigation one) is still accurate and unaddressed in this phase.
- **Accessibility Issues:** ~~Edit/Delete buttons are icon-only with no `aria-label`...~~ **Resolved (Step 9)** — `.edit-voucher`/`.delete-voucher` now carry descriptive `aria-label`s. The `usage_limit` id/label mismatch above is resolved (Step 6).
- **Improvement Priority:** Low — the functionally-broken edit field (the page's most severe defect) and the icon-button accessible-name gap are both resolved as of this phase. The missing `is_active` toggle and missing DataTables remain, at their original (non-navigation) severity.

---

## 12. All Transactions (Admin)

- **Purpose:** Full list of every booking across all customers, with time-editing, delete, and confirm actions.
- **Route:** `view-all-data.php`
- **Files Used:** [view-all-data.php](../view-all-data.php); actions handled by [update_booking_time.php](../update_booking_time.php), [delete_booking.php](../delete_booking.php), [admin_confirm_booking.php](../admin_confirm_booking.php). ~~`includes/header.php`/`includes/footer.php`~~ — **this section's claim was already stale at analysis time**: `view-all-data.php` never included either file (it built its own document, per the original [ADMIN_DASHBOARD_ANALYSIS.md](ADMIN_DASHBOARD_ANALYSIS.md) §1.3 shell inventory); both files have since been deleted project-wide (Step 10).
- **CSS Files:** `css/styles.css`, Bootstrap 5.3.2, Font Awesome 6.4.2 — this page has always loaded its own `<head>` directly (not via `includes/header.php`, contrary to this section's prior claim), so Font Awesome's presence here was never actually dependent on that file.
- **JavaScript Files:** jQuery, Bootstrap JS bundle, DataTables JS — loaded once (the prior "loaded twice via `includes/footer.php`" claim does not apply; this page never included it), now using Step 4's `columnDefs` baseline; admin JavaScript for this page's handlers now lives in `js/admin.js` (Step 6).
- **Bootstrap Components:** table (DataTables-enhanced), modal (Edit Booking Times), badges (booking status).
- **Forms:** Edit Booking Times form (pickup time, dropoff time) inside a modal, submitted via AJAX.
- **Tables:** All-transactions table (Booking ID, User, Vehicle, Rental Date, Return Date, Pickup Time, Dropoff Time, Status badge, Amount, Actions).
- **Cards:** None.
- **Modals:** Edit Transaction (booking time) modal.
- **Navigation:** Shared admin sidebar (`includes/admin_sidebar.php`) + shared topbar (`includes/admin_topbar.php`, Step 2) — **not** `includes/header.php`, which never applied to this page.
- **Current UI Problems:** ~~The `.confirm-transaction` click handler is nested inside the `.delete-transaction` handler's...not bound on page load...~~ **Resolved in Step 4** ([BUGS.md](BUGS.md) item 6) — the registration was moved to a sibling statement inside `$(document).ready()`; Confirm now works on first interaction after a fresh page load. ~~Table header typo: "Rental Dsate"~~ **Resolved in Step 4** ("Rental Date").
- **Responsiveness Issues:** `table-responsive` wrapper present, consistent with other admin tables.
- **Accessibility Issues:** ~~Action buttons...without individual `aria-label`s...~~ **Resolved in Step 4** — Confirm/Delete/Edit buttons now carry `aria-label`s naming the specific booking (e.g. "Confirm booking #142").
- **Improvement Priority:** Low — the confirm-button binding bug, the header typo, and the unlabeled-button gap are all resolved as of this phase.

---

## 13. Privacy Policy

*New page, added during the System Enhancements initiative's Step 6, 2026-08-21 (Feature 4, Decision D7 Option A — a dedicated page rather than a modal, with user-authored policy content).*

- **Purpose:** RA 10173 (Data Privacy Act)-facing policy page — what's collected, why, how long it's retained, who it's shared with, and how to contact the project's data representative. Linked from the signup consent checkbox (Step 7) and the site footer.
- **Route:** `privacy.php`
- **Files Used:** [privacy.php](../privacy.php)
- **CSS Files:** `css/styles.css`, Bootstrap 5.3.2, Font Awesome 6.4.2, Animate.css 4.1.1 — same stack as every other client page.
- **JavaScript Files:** `js/app.js`, `js/confirm.js`, `js/theme.js` — same shared set as every other client page (no page-specific script).
- **Bootstrap Components:** navbar, footer, login/signup modals (shared markup, `includes/auth_modals.php`) — same shell as the other six client pages, built via `includes/client_navbar.php`/`includes/client_footer.php`.
- **Forms:** None on this page itself (the consent checkbox it's linked from lives in the signup modal, shared across all client pages).
- **Tables:** None.
- **Cards:** None — plain prose sections.
- **Modals:** Login, Signup (with the Step 7 consent checkbox and its link back to this page), Forgot Password — shared markup.
- **Navigation:** Same shared navbar/footer pattern as the other six client pages. The footer's copyright bar carries a new `· Privacy Policy` link (`includes/client_footer.php`) pointing here.
- **Structure:** One `<h1>` ("Privacy Policy") followed by eight sequential `<h2>` sections — Introduction, Information We Collect, Purpose of Collection, Data Retention, Data Sharing & Third Parties, Security Measures, Your Rights, Contact — verified live to have no skipped heading levels (System Enhancements initiative, Step 11).
- **Content notes:** The Data Retention section deliberately does not state a fixed retention period — the project has not finalized one, and the page says so honestly rather than inventing a number (an explicit correction made during Step 6's drafting). The Contact section names a placeholder project data representative, group name, institution, and email, all marked as placeholders pending real values.
- **Responsiveness/Accessibility:** Same shared navbar/footer/modal patterns as every other client page — no page-specific issues found (System Enhancements initiative, Step 11's 320px/992px overflow sweep, and the 4px `.row` gutter artifact shared with `about.php`/`vehicles.php`, see [BUGS.md](BUGS.md) item 27).
- **Improvement Priority:** Low — new page, no functional bugs found.

---

## Cross-Page Summary

Patterns confirmed as consistent (or inconsistently applied) across multiple pages, for reference:

- ~~Three different admin page "shells" exist: pages using `includes/header.php`/`footer.php`...and pages building their own full HTML document independently...producing the sidebar-navigation inconsistency noted specifically on the Voucher Management page.~~ **Resolved (Admin Dashboard phase, Steps 1-2, 2026-08-20).** `admin_users.php` and `admin_vehicles.php` no longer include `includes/header.php`/`footer.php` (Step 1 — the double-doctype defect this caused is also fixed); those two files were deleted project-wide in Step 10. All seven admin pages now build one valid document and share `includes/admin_sidebar.php` + `includes/admin_topbar.php` (Step 2). The "sidebar-navigation inconsistency" attributed to Voucher Management was itself a documentation error, not a real defect — see §11's correction above.
- ~~Icon-only buttons with no accessible name...repeated pattern across Admin Vehicles, Admin Users, and Admin Vouchers.~~ **Resolved (Steps 4, 5, 9).** Every icon-only admin action button now carries a descriptive `aria-label`.
- **Customer-facing pages duplicate the entire navbar/footer/modal markup** — **stale.** This was resolved by the earlier Shared Components phase, before the Admin Dashboard phase began: all six customer pages now include `includes/client_navbar.php`, `includes/client_footer.php`, and `includes/auth_modals.php`. Out of scope for this phase to re-verify further beyond noting the claim is outdated.
- **DataTables is loaded inconsistently**: now initialized on Admin Vehicles, Admin Users, All Transactions, **and Admin Dashboard's Recent Messages table (Step 3)**; still entirely absent on Admin Vouchers, unaddressed in this phase.
- **Dark mode now spans all 14 pages** (System Enhancements initiative, Steps 8-10, 2026-08-21) — every client and admin page, plus the new `privacy.php`, supports `data-bs-theme="dark"` with zero contrast findings from an automated WCAG scan, re-verified per page rather than sampled. See the Global facts section above for the token table.
- **Every consequential action on every page now confirms before proceeding** (Steps 1, 3, 4) — native `confirm()` is gone from the codebase entirely (verified by grep), replaced by the shared `js/confirm.js` modal.

---

*This document is a factual inventory only. No visual, structural, or accessibility changes were made or proposed as fixes — every "Improvement Priority" rating reflects the severity of confirmed findings, not a recommended design direction.*
