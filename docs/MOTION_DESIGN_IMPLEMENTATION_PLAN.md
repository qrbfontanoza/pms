# PMS Motion Design — Implementation Plan

Implementation plan for the approved [MOTION_DESIGN_ANALYSIS.md](MOTION_DESIGN_ANALYSIS.md). Divides the work into 12 phases, sequenced by dependency and priority. Each phase is independently approvable and independently ship-able.

**Status:** Plan only. **No code has been modified.** Each step below requires separate approval before implementation begins, per the working rules in [CLAUDE.md](../CLAUDE.md). Do not proceed to a later step without explicit sign-off on the current one.

**Guiding decisions from the analysis (see [MOTION_DESIGN_ANALYSIS.md](MOTION_DESIGN_ANALYSIS.md) for rationale):**
- **Architecture:** Pure CSS + `IntersectionObserver` + Bootstrap defaults. No new animation library. Animate.css stays where it's already used (hero), no expansion.
- **Nothing existing is removed** in this phase, including the broken motion code documented in [MOTION_DESIGN_ANALYSIS.md](MOTION_DESIGN_ANALYSIS.md) §3.8.
- **Admin side gets the minimum**: spinners only. No decorative motion.
- **Every animation is tokenised** — no hardcoded durations/easings in new CSS.
- **Motion is priority-tiered** (P0 essential feedback / P1 supporting continuity / P2 decorative) — the phases below deliver P0 first, then P1, then P2.

---

## Phase 1 — Motion Design Foundation

### Objective

Establish the technical foundation every subsequent phase depends on: motion tokens, the global `prefers-reduced-motion` rule, the `js/motion.js` skeleton with `IntersectionObserver` and `setButtonLoading()` helpers, and `:focus-visible` outlines. No visible motion changes yet — this phase enables all the others.

### Files to Inspect

- [css/styles.css](../css/styles.css) — verify existing `:root` token block ([:3-13](../css/styles.css#L3)), confirm the single existing `prefers-reduced-motion` block ([:79-87](../css/styles.css#L79)) is preserved as-is.
- [js/app.js](../js/app.js) — confirm broken scroll handler at [:995-1001](../js/app.js#L995) and dead pulse handler at [:1005-1008](../js/app.js#L1005). Both stay untouched.
- [includes/client_navbar.php](../includes/client_navbar.php) — this is the currently-shared navbar (customer side). Verify it is included by every customer page (per [COMPONENT_LIBRARY.md](COMPONENT_LIBRARY.md), navbar duplication has since been consolidated into this include on at least the pages that use it — verify per-page during implementation).
- Every customer PHP page — confirm the `<script src="js/app.js">` load point (so we know where to add `js/motion.js`).

### Files Expected to Change

- [css/styles.css](../css/styles.css) — append motion tokens to `:root`; append a new `@media (prefers-reduced-motion: reduce)` block **after** the existing hero-video block (do not merge — keep the existing narrow rule intact for git-blame clarity); append `:focus-visible` global rule; append spinner-preserve override inside the reduced-motion block; append base keyframes and `[data-reveal]` / `.is-visible` selectors used by later phases.
- **New file:** `js/motion.js` — see structure below.
- Every customer-facing PHP page (`index.php`, `vehicles.php`, `faq.php`, `about.php`, `transactions.php`, `receipt.php`) — add one `<script src="js/motion.js"></script>` line **after** the `<script src="js/app.js">` load. Admin pages will get this in Phase 9 only if needed there.

### Existing Components to Reuse

- CSS custom properties pattern already used in `:root`.
- Bootstrap's own spinner classes (`.spinner-border spinner-border-sm`) — no custom spinner CSS required.
- Bootstrap's own `.fade.show` transition classes — no custom fade CSS required for alerts.
- Existing hero video reduced-motion pattern — preserved verbatim.

### New Components Required

- Motion tokens (12 CSS variables — see [MOTION_DESIGN_ANALYSIS.md](MOTION_DESIGN_ANALYSIS.md) §12 for exact values).
- `[data-reveal]` initial state + `.is-visible` triggered state.
- `[data-reveal-stagger]` container behavior.
- Global reduced-motion neutralisation rule.
- `:focus-visible` global outline rule.
- `.is-loading` state on buttons.
- `js/motion.js` module with:
  - `initReveal()` — sets up IntersectionObserver, handles stagger delay injection.
  - `setButtonLoading($btn, isLoading)` — inserts/removes spinner span, toggles `disabled`, toggles `.is-loading` class, toggles `aria-busy`.
  - `initModalFocus()` — attaches `shown.bs.modal` handlers to standard modals to focus the first input.
  - `prefersReducedMotion()` — cached `matchMedia` check for JS-side decisions (e.g. counter animation skip).
- Optional exported namespace: `window.PMSMotion` object.

### Existing Logic to Preserve

- The existing `.feature-card` scroll listener in [app.js:995-1001](../js/app.js#L995) — untouched.
- The existing `.animate__pulse` handler in [app.js:1005-1008](../js/app.js#L1005) — untouched.
- All existing Bootstrap default transitions — untouched.
- Existing hero `prefers-reduced-motion` block ([css/styles.css:79-87](../css/styles.css#L79)) — untouched (do not merge into the new global block).
- All existing dead motion code (see [MOTION_DESIGN_ANALYSIS.md](MOTION_DESIGN_ANALYSIS.md) §3.8) — untouched.

### JavaScript Behavior

`js/motion.js` skeleton:
```
(function () {
  'use strict';
  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function initReveal() {
    if (!('IntersectionObserver' in window)) return;
    // Observe [data-reveal]. On intersection add .is-visible + unobserve.
    // For containers with [data-reveal-stagger], compute --reveal-delay per child.
  }

  function setButtonLoading($btn, isLoading) {
    // Toggle spinner span + disabled + .is-loading + aria-busy.
  }

  function initModalFocus() {
    // On shown.bs.modal, focus first input/select/textarea, or Close button.
  }

  $(function () {
    initReveal();
    initModalFocus();
  });

  window.PMSMotion = { setButtonLoading: setButtonLoading, prefersReducedMotion: function () { return reducedMotion; } };
})();
```

### PHP Behavior

None. This phase adds no PHP.

### Database Requirements

None.

### Motion Patterns

- Global reduced-motion (Level 1 — always essential — per [MOTION_DESIGN_ANALYSIS.md](MOTION_DESIGN_ANALYSIS.md) §11.1).
- Focus rings (Level 1).
- Token vocabulary for every later phase.

### Technical Approach

CSS-first. `js/motion.js` is small (~150 lines) and self-contained — no dependency on `app.js`, but loads after it to avoid any hoisting issues if a future phase moves shared helpers.

### Dependencies

None (this phase is the base of the dependency tree).

### Risks

- Focus-visible override may conflict with Bootstrap's own `.form-control:focus` box-shadow — mitigate by scoping our `:focus-visible` outline via `outline` (Bootstrap uses `box-shadow`), which stacks non-destructively, and by adding `:focus:not(:focus-visible) { outline: none }` to hide the outline for mouse-users.
- Aggressive reduced-motion `!important` rule catches spinner keyframe — mitigated by a follow-up rule inside the same media block re-enabling `.spinner-border` / `.spinner-grow` at 0.75 s.
- No functional risk beyond CSS specificity — this phase adds zero markup.

### Testing Requirements

- With reduced-motion OFF: existing hero animation still works; card hover lifts still work.
- With reduced-motion ON (OS setting): hero video is hidden (existing behavior); no other animation is currently visible to test yet (this phase adds no visible motion).
- Keyboard Tab through the site: every interactive element now shows a 3 px accent outline.
- Mouse click on a button: no outline visible (`.focus:not(:focus-visible)` rule works).
- Console clean: no JS errors on any page.
- `window.PMSMotion` accessible in DevTools console on every customer page.

### Acceptance Criteria

- [ ] Motion tokens present in `:root`.
- [ ] Global `prefers-reduced-motion` block present (in addition to the existing hero-video block).
- [ ] Spinner-preserve override inside reduced-motion block.
- [ ] Base `[data-reveal]` / `.is-visible` / `[data-reveal-stagger]` selectors present.
- [ ] `:focus-visible` global rule present.
- [ ] `.is-loading` selector present.
- [ ] `js/motion.js` created and loaded on every customer-facing page.
- [ ] `window.PMSMotion.setButtonLoading` and `window.PMSMotion.prefersReducedMotion` callable from DevTools.
- [ ] IntersectionObserver initialised but no `[data-reveal]` markup exists yet (proven by nothing animating on scroll — that's expected).

---

## Phase 2 — Global Micro-Interactions

### Objective

Deploy `setButtonLoading` across every AJAX-submitting button on the customer side, and add `shown.bs.modal` focus handlers to every standard modal. This is the highest-UX-benefit phase in the plan — it removes silent-AJAX feedback failures across the whole booking flow.

### Files to Inspect

- [js/app.js](../js/app.js) — `#btnPreview` handler at [:88-175](../js/app.js#L88), `#btnConfirm` handler at [:227-292](../js/app.js#L227), `#loginForm` submit at [:1099-1120](../js/app.js#L1099), `#signupForm` submit at [:1066-1095](../js/app.js#L1066), `#contactForm` submit at [:412-428](../js/app.js#L412).
- [js/voucher-manager.js](../js/voucher-manager.js) — `#voucherSelect` change handler.
- [about.php](../about.php) — inline contact form handler script (if it exists; otherwise handler is in `app.js`).
- [transactions.php](../transactions.php) — inline cancel/return-early AJAX handlers.
- [includes/auth_modals.php](../includes/auth_modals.php) — verify modal IDs and first-input IDs.
- [vehicles.php](../vehicles.php) — `#bookingModal`, `#vehicleDetailsModal`, `#loginModal`, `#signupModal` first-input targets.

### Files Expected to Change

- [js/app.js](../js/app.js) — wrap each identified AJAX call with `PMSMotion.setButtonLoading($btn, true)` before the fetch/AJAX and `setButtonLoading($btn, false)` in both the success and error branches (`try/finally` where possible).
- [js/voucher-manager.js](../js/voucher-manager.js) — wrap voucher apply AJAX with `setButtonLoading` on `#voucherSelect`'s associated apply UI (or on the select itself — decide during implementation based on visible affordance).
- Per-page inline scripts on `transactions.php` — same wrapping pattern.
- [js/motion.js](../js/motion.js) — `initModalFocus()` implementation completes here (already stubbed in Phase 1).

### Existing Components to Reuse

- `PMSMotion.setButtonLoading` from Phase 1.
- Bootstrap's `shown.bs.modal` event.
- Bootstrap `.spinner-border spinner-border-sm` classes.

### New Components Required

- None (all foundations are in Phase 1).

### Existing Logic to Preserve

- All existing AJAX success/failure branches unchanged in outcome — only the button state changes.
- Existing focus-on-modal behavior for `#bookingMultiModal` at [app.js:975](../js/app.js#L975) (unchanged; that modal is dead but the code stays).
- Existing showAlert calls unchanged.

### JavaScript Behavior

Pattern applied everywhere:
```
$('#btnPreview').on('click', async function () {
  var $btn = $(this);
  PMSMotion.setButtonLoading($btn, true);
  try {
    // ... existing fetch logic ...
  } catch (err) {
    // ... existing error logic ...
  } finally {
    PMSMotion.setButtonLoading($btn, false);
  }
});
```

Modal focus pattern (added once in `initModalFocus`):
```
$(document).on('shown.bs.modal', '.modal', function () {
  var $modal = $(this);
  var $first = $modal.find('input:not([type="hidden"]), select, textarea').filter(':visible').first();
  if ($first.length) $first.trigger('focus');
  else $modal.find('.btn-close').trigger('focus');
});
```

### PHP Behavior

None.

### Database Requirements

None.

### Motion Patterns

- Loading state (Level 1 essential feedback, per [MOTION_DESIGN_ANALYSIS.md](MOTION_DESIGN_ANALYSIS.md) §10.1).
- Focus-on-modal (Level 1 guidance, §10.4).

### Technical Approach

Bulk find-and-wrap. Every AJAX site the analysis identified gets the same treatment. No visible design changes beyond a spinner appearing during the request.

### Dependencies

Phase 1 (motion tokens, `setButtonLoading`, focus rules).

### Risks

- If a fetch throws before the `finally`, the spinner state must still be reset — enforced by using `try/finally` or `.finally()`.
- If the user closes the modal mid-request, the button is destroyed; `setButtonLoading(false)` becomes a no-op safely.
- On success paths that redirect (`window.location.href = 'receipt.php?...'`), the spinner intentionally *stays* until the browser navigates away — this is correct behavior (user sees "loading" until the new page starts loading).
- Existing partial focus behavior on some modals may conflict with the new global handler — the global handler uses event delegation, so it fires after per-modal handlers; test for double-focus (harmless but noisy).

### Testing Requirements

- Click Preview in booking modal: button shows spinner, is disabled, becomes clickable again when preview returns or errors.
- Click Preview twice quickly: second click is blocked because button is disabled.
- Submit login/signup with invalid credentials: spinner shows, then resets on error alert.
- Submit contact form: spinner shows, alert appears.
- Open login modal: focus lands on email field automatically.
- Open booking modal: focus lands on `#rental_date`.
- Open cancel modal: focus lands on the primary action button (no inputs to focus).

### Acceptance Criteria

- [ ] `#btnPreview`, `#btnConfirm`, `#loginForm` submit, `#signupForm` submit, `#contactForm` submit all show a spinner during their AJAX call.
- [ ] Voucher apply (via `#voucherSelect` change) shows in-flight feedback.
- [ ] Every AJAX handler on `transactions.php` (cancel booking, return early) shows a spinner.
- [ ] Every standard `.modal` focuses its first input on `shown.bs.modal`.
- [ ] No double-submit possible on any of the above buttons.
- [ ] No JS errors on any customer page after this phase.

---

## Phase 3 — Homepage Motion

### Objective

Deliver the marketing-page motion story: hero entrance (keep existing), search widget entrance (new), and scroll-reveal on Features / Stats / Testimonials / CTA sections. Replaces the broken jQuery scroll handler's effect without deleting it.

### Files to Inspect

- [index.php](../index.php) — hero section [:32-48](../index.php#L32), search widget [:66-97](../index.php#L66), features section [:112-149](../index.php#L112), plus stats bar, testimonials, CTA banner (verify current line numbers during implementation).
- [css/styles.css](../css/styles.css) — `.feature-card` [:390-398](../css/styles.css#L390), `.testimonial-card` [:700-707](../css/styles.css#L700), `.cta-banner` [:721-730](../css/styles.css#L721), `.stats-bar` [:685-697](../css/styles.css#L685).
- [js/app.js](../js/app.js) — broken scroll handler at [:995-1001](../js/app.js#L995) (still preserved; its `animate__fadeInUp` class add is a no-op because the elements already have Animate.css classes).

### Files Expected to Change

- [index.php](../index.php):
  - Add `data-reveal` to the search-widget outer wrapper, with an inline `style="--reveal-delay: var(--motion-delay-long)"` or `data-reveal-delay="long"` attribute (decide during implementation — inline style is simpler).
  - Add `data-reveal-stagger` to the features section row container; add `data-reveal` to each of the 3 feature cards.
  - Add `data-reveal-stagger` to the stats bar container; add `data-reveal` to each stat item.
  - Add `data-reveal-stagger` to the testimonials row; add `data-reveal` to each testimonial card.
  - Add `data-reveal` to the CTA banner.
  - **Do not remove** the existing `animate__animated animate__fadeInLeft/Up/Right` classes on the three feature cards. They fire on page load (which is above-the-fold for some viewports); the new `data-reveal` runs when they enter the viewport. These will not double-animate visibly because Animate.css keyframes complete in 0.6-1 s and our reveal is scoped to `[data-reveal].is-visible` only; the visible result is dominated by whichever fires first depending on scroll position. If double-animation is visible in QA, adjust in Phase 12 (Final QA).
- [css/styles.css](../css/styles.css):
  - Add a `.feature-card:hover` override to standardise the hover-lift to `translateY(-6px)` (removing the `scale(1.05)`, matching `.vehicle-card`). Existing rule at [:390-398](../css/styles.css#L390) stays; new rule appended.
  - Optionally add `.stats-bar[data-reveal]` styling if the reveal needs custom easing.
- [js/motion.js](../js/motion.js) — optional counter helper for stats bar (P2, may defer to Phase 3.5).

### Existing Components to Reuse

- Existing hero Animate.css classes (unchanged).
- Existing `.feature-card`, `.testimonial-card`, `.cta-banner` classes.
- `[data-reveal]` from Phase 1.

### New Components Required

- None (all patterns are from Phase 1).
- Optional: `initCounters()` in `js/motion.js` if implementing count-up numbers (P2).

### Existing Logic to Preserve

- Hero `<h1>` / `<p>` Animate.css classes.
- Hero video / image fallback behavior.
- Search widget JS (pickup-date → return-date min sync).
- Featured cars carousel (`carousel-fade`).
- Broken jQuery scroll handler at [app.js:995](../js/app.js#L995).

### Motion Patterns

- Scroll-reveal fade + translateY (16 px), 400 ms, 60-100 ms stagger.
- Hero entrance via existing Animate.css.
- Card hover lift (updated `.feature-card` to match vehicle/testimonial).

### Technical Approach

Declarative — most work is adding `data-reveal` attributes to `index.php`. No JS beyond the IO already in `js/motion.js`.

### Dependencies

Phase 1 (foundation).

### Risks

- Feature cards may animate twice (Animate.css on load + our reveal on viewport) if the visitor's viewport happens to include them at load time. Verified logically: our reveal keyframe animates `opacity: 0 → 1` and `translateY(16 → 0)`; if the element already has `opacity: 1` from Animate.css, the reveal is invisible (the new keyframe would first set `opacity: 0` via the `[data-reveal]:not(.is-visible)` base state, potentially causing a flash). Mitigation: gate the `[data-reveal]` base state by `.no-js` absence or by adding `.is-visible` immediately if the element already has an `.animate__animated` class. Determine at implementation time whether this is a visible problem or a theoretical one.
- Search-widget delay of 300 ms after hero may feel like a stall on fast connections — verify visually.

### Testing Requirements

- Load homepage on desktop: hero animates, search widget follows, feature cards reveal on scroll to their section.
- Refresh at various scroll positions: elements below the viewport reveal on scroll; elements above are shown final-state.
- With reduced-motion ON: hero snaps in, no scroll reveal fires, elements are shown final-state.
- Mobile: features/testimonials reveal on scroll; no jank; stagger doesn't feel excessive.
- Feature-card hover: consistent lift with vehicle/testimonial cards, no scale flicker.

### Acceptance Criteria

- [ ] Search widget entrance animates 300 ms after hero.
- [ ] Feature cards reveal with 80 ms stagger on viewport entry.
- [ ] Stats bar reveals with stagger.
- [ ] Testimonial cards reveal with 100 ms stagger.
- [ ] CTA banner reveals on viewport entry.
- [ ] Feature-card hover matches vehicle/testimonial-card hover (no scale).
- [ ] Hero Animate.css classes still present and firing.
- [ ] Reduced-motion mode: all reveal animations neutralised; elements shown final-state.

---

## Phase 3.5 — (Optional) Homepage Stats Counter

### Objective

Count-up animation on stats-bar numbers (0 → target over 1000 ms) when the bar enters the viewport. P2 (decorative-adjacent — falls under Feedback: "numbers feel earned"). May be skipped or deferred.

### Files to Inspect

- [index.php](../index.php) — stats bar markup; identify the DOM elements holding the numeric text.

### Files Expected to Change

- [index.php](../index.php) — wrap each stat number in a `<span class="js-count" data-target="123">123</span>` (fallback text visible without JS).
- [js/motion.js](../js/motion.js) — add `initCounters()` that:
  - Observes `.js-count` elements.
  - On first intersection, animates the `textContent` from 0 to `data-target` over 1000 ms via `requestAnimationFrame`.
  - Skips entirely if `prefersReducedMotion()` returns true (final value shown immediately).
  - Adds `aria-hidden="true"` to the counter span and pairs it with a visually-hidden sibling containing the final value for screen readers.

### Motion Patterns

- Count-up (Level 3 decorative, per [MOTION_DESIGN_ANALYSIS.md](MOTION_DESIGN_ANALYSIS.md) §11.3).

### Acceptance Criteria

- [ ] Counters animate 0 → target once per page load.
- [ ] Numbers are read correctly by screen readers.
- [ ] Reduced-motion: numbers shown final immediately.

---

## Phase 4 — Vehicle Listing Motion

### Objective

Deliver motion for the primary browse-and-filter page: vehicle-grid stagger reveal, filter Apply spinner, pagination click spinner. Card hover behavior stays untouched (already good).

**Pre-Phase-4 remediation note:** the per-container stagger override mechanism this phase originally would have needed to build now already exists — `js/motion.js`'s `initReveal()` reads an optional `data-reveal-stagger="N"` value per container (falling back to the global `--motion-stagger` token when the attribute is bare), landed ahead of this phase along with the homepage's three containers being updated to their §9.2 documented values (Features `="80"`, Stats `="80"`, Testimonials `="100"`). This phase only needs to *use* `data-reveal-stagger="60"` on the vehicle grid, not implement the override logic itself.

### Files to Inspect

- [vehicles.php](../vehicles.php) — vehicle-card render loop, filter sidebar (`#filterSidebar`), pagination component.
- [css/styles.css](../css/styles.css) — `.vehicle-card` [:452-488](../css/styles.css#L452) — verify hover works with new stagger reveal (verified: hover uses `:hover`; reveal uses `.is-visible` — different states, no conflict).
- [js/motion.js](../js/motion.js) — confirm `data-reveal-stagger="N"` parsing is present (Pre-Phase-4 remediation) before assuming it needs to be built here.

### Files Expected to Change

- [vehicles.php](../vehicles.php):
  - Wrap the vehicle-card grid in a container with `data-reveal-stagger="60"` (60 ms stagger, per §8.2) — the attribute value is now read directly by the existing `initReveal()`, no JS change needed for this alone.
  - Add `data-reveal` to each `.vehicle-card` in the render loop.
  - Filter form: add `id="filterForm"` if not present, and register a submit handler (inline or in `motion.js`) that calls `PMSMotion.setButtonLoading(filterApplyBtn, true)` on submit — the spinner stays until the browser navigates away.
  - Pagination: register a delegated click handler on `.page-link` that spins that specific link until navigation.
- [js/motion.js](../js/motion.js) — add a small `initFormSubmitSpinner(selector)` helper (optional; the inline pattern works too).

### Existing Components to Reuse

- Vehicle-card hover behavior (existing).
- Filter form (existing GET submit).
- Pagination component (existing).
- `PMSMotion.setButtonLoading` from Phase 1.

### New Components Required

- Optional `initFormSubmitSpinner` helper.

### Existing Logic to Preserve

- Filter form GET submission with URL preservation of active filters and sort.
- Pagination sliding window with ellipses.
- Vehicle-card hover lift and image scale.
- `#vehicleDetailsModal` open flow (Vehicle Details phase Step 3, already sequenced).

### Motion Patterns

- Scroll-reveal grid stagger (Level 2 continuity).
- Form-submit loading state (Level 1 feedback).
- Pagination loading state (Level 1 feedback).

### Dependencies

Phase 1, Phase 2.

### Risks

- Stagger + reveal duration total (6 × 60 + 400 = 760 ms) is at the edge of "feels slow". Test on real devices; consider dropping to 40 ms stagger if perceptible drag reported.
- If a user has 100+ vehicles per page (not currently the case — page size is 9), stagger would need capping. Already capped at 6 in [MOTION_DESIGN_ANALYSIS.md](MOTION_DESIGN_ANALYSIS.md) §9.1.
- Filter offcanvas on mobile: `[data-reveal]` inside the offcanvas may fire on first offcanvas open. Verify no unintended flashing.

### Testing Requirements

- Land on vehicles page: cards reveal with stagger, no jank.
- Scroll down: cards below fold reveal as they enter viewport.
- Apply a filter: Apply button spins, page reloads with filter applied.
- Click page 2: pagination link spins, page loads.
- Reduced-motion: cards appear final-state, no stagger.

### Acceptance Criteria

- [ ] Vehicle-card grid reveals with 60 ms stagger (capped at 6).
- [ ] Filter Apply button shows spinner on submit.
- [ ] Pagination links show spinner on click.
- [ ] Hover behavior unchanged.
- [ ] `#vehicleDetailsModal` open flow unchanged.

---

## Phase 5 — Vehicle Details & Booking Motion

### Objective

Add motion to the two most important modals: `#vehicleDetailsModal` (image fade on load) and `#bookingModal` (preview reveal, confirm button state). The Preview/Confirm spinners themselves ship in Phase 2; this phase adds the content-reveal choreography.

### Files to Inspect

- [vehicles.php](../vehicles.php) — `#vehicleDetailsModal` markup (added in Vehicle Details phase Step 1), `#bookingModal` markup, inline booking JS.
- [js/app.js](../js/app.js) — `#btnPreview` handler [:88-175](../js/app.js#L88).

### Files Expected to Change

- [vehicles.php](../vehicles.php):
  - `#vehicleDetailsModal` image element: add `class="js-fade-on-load"` and a `load` event listener that fades opacity 0 → 1 over 300 ms (or CSS `img.js-fade-on-load { transition: opacity 300ms var(--motion-easing-entrance); }` + JS setting `opacity: 0` on modal show and `opacity: 1` on `load`).
  - `#bookingPreview` container: add `data-reveal` (variant: fade + max-height expand). New CSS keyframe or transition for this specific case.
  - `#amountPaidSection`: same treatment as `#bookingPreview`.
- [js/app.js](../js/app.js) — in `#btnPreview` success branch: after setting `#bookingPreview` HTML, add `.is-visible` class to trigger reveal; enable `#btnConfirm` (already existing behavior, keep).
- [css/styles.css](../css/styles.css) — add `.booking-preview-reveal` (or reuse `[data-reveal]`) styles.

### Existing Components to Reuse

- `#bookingModal` markup and flow (untouched otherwise).
- `#vehicleDetailsModal` markup (from Vehicle Details phase).
- Modal-focus behavior from Phase 2.

### New Components Required

- `js-fade-on-load` image class.
- Booking-preview reveal variant of `[data-reveal]` (may need `max-height` handling — see Risks).

### Existing Logic to Preserve

- Complete Preview → Confirm flow.
- Modal-in-modal sequencing (Details → Auth or Details → Booking) from Vehicle Details phase Step 3.
- Voucher application flow.
- Existing amount-paid-section reveal (currently `removeClass('d-none')` at [app.js:156](../js/app.js#L156)).

### Motion Patterns

- Content reveal within modal (Level 2 continuity).
- Image fade-in (Level 3 delight).

### Dependencies

Phase 1, Phase 2.

### Risks

- `max-height` animation is a layout property, not a compositable one (per [MOTION_DESIGN_ANALYSIS.md](MOTION_DESIGN_ANALYSIS.md) §14.4). For `#bookingPreview` expand, either:
  a) Set a fixed `max-height: 500px` (safe upper bound for the preview markup) and animate `opacity` + subtle `translateY` only.
  b) Use `transform: scaleY(0 → 1)` with `transform-origin: top` and adjust `overflow: hidden` on the container.
  c) Skip expand entirely; just fade in (simplest).
  **Recommendation:** option (c). The container adds itself to the modal flow naturally when populated; a pure fade avoids all layout thrash concerns.
- Image fade may cause a brief empty modal on slow connections. Mitigation: set a `min-height` on the image container matching typical card image height (200 px).

### Testing Requirements

- Open vehicle-details modal: image fades in when loaded, not before.
- Book a vehicle: click Preview → button spins → preview content fades in → Confirm becomes enabled → click Confirm → spinner → redirect.
- Reduced-motion: image appears immediately; preview appears immediately; no reveal transition.

### Acceptance Criteria

- [ ] `#vehicleDetailsModal` image fades in on load.
- [ ] `#bookingPreview` reveals with fade after Preview succeeds.
- [ ] `#amountPaidSection` reveals with same motion.
- [ ] `#btnConfirm` remains disabled until preview succeeds (existing behavior preserved).
- [ ] Modal-in-modal flow unchanged.

---

## Phase 6 — About Us Motion

### Objective

Scroll-reveal for the About page's section stack (story, feature cards, team cards, CTA), plus contact form field focus rings and team-card hover consistency.

### Files to Inspect

- [about.php](../about.php) — feature cards, team cards, contact form, CTA banner.
- [css/styles.css](../css/styles.css) — existing `.cta-banner` (shared with homepage — no changes here).

### Files Expected to Change

- [about.php](../about.php):
  - Add `data-reveal` to the company-story section.
  - Add `data-reveal-stagger` to the feature-cards row; `data-reveal` on each of 4 cards.
  - Add `data-reveal-stagger` to the team-cards row; `data-reveal` on each of 5 cards.
  - Add `data-reveal` to the CTA banner.
- [css/styles.css](../css/styles.css):
  - Add a `.team-member-card:hover` rule matching the vehicle/testimonial-card pattern (translateY(-4px) + shadow). If the class is different in the current markup, use the actual selector present in `about.php`.
  - Confirm contact form fields already inherit the `:focus-visible` outline from Phase 1 — no additional rule expected, but verify visually.
- Contact form submit spinner ships in Phase 2 already; verify.

### Existing Components to Reuse

- `[data-reveal]` from Phase 1.
- Vehicle/testimonial hover-lift pattern.
- Contact form submit handler.

### New Components Required

- Team-member-card hover rule.

### Existing Logic to Preserve

- Contact form login-gate check and AJAX POST.
- Server-side pre-fill of name/email when logged in.
- Existing `#contactAlert` show/hide.

### Motion Patterns

- Scroll-reveal (Level 2).
- Card hover (Level 2 delight).
- Focus ring (Level 1 feedback).

### Dependencies

Phase 1, Phase 2.

### Risks

- Team-member card layout drift: the existing card markup uses circular avatars; adding a `translateY(-4px)` hover may cause the card to visually detach from its column. Verify.
- Contact alert re-styling: existing alert is `alert-success` in markup, class-swapped in JS. The `.fade.show` Bootstrap transition is already available — no change to markup needed, but confirm the JS `.removeClass('d-none')` is compatible with adding `.fade.show` classes.

### Testing Requirements

- Scroll About page: sections reveal in order.
- Hover team-member card: subtle lift.
- Tab through contact form: focus rings visible.
- Submit contact form logged out: alert prompts login.
- Submit logged in: spinner + success alert.

### Acceptance Criteria

- [ ] All About sections reveal on scroll.
- [ ] Team-member cards lift on hover.
- [ ] Contact form fields show focus rings.
- [ ] Contact form submit spinner works (Phase 2 verification).

---

## Phase 7 — Contact / Booking Motion (Consolidation)

### Objective

Verification-only phase (may be merged with Phase 5/6 during execution if convenient). Confirms that all Contact-form and Booking motion delivered across Phases 2 / 5 / 6 works end-to-end in the full user journey.

### Files to Inspect

- Full user journey per [MOTION_DESIGN_ANALYSIS.md](MOTION_DESIGN_ANALYSIS.md) §4.

### Files Expected to Change

- None (verification phase). Any bugs found here get patched in the phase that introduced them.

### Acceptance Criteria

- [ ] Full booking journey (homepage → vehicles → details → login → booking → receipt) plays with correct motion at every step.
- [ ] Full contact journey (about → login-gate → submit → alert) plays with correct motion.
- [ ] No dead spinners, no missing focus rings, no double-animations.

---

## Phase 8 — Authentication Motion

### Objective

Authentication modal polish: focus on first input (ships in Phase 2 — verify), submit spinner (ships in Phase 2 — verify), optional error-shake on invalid credentials, **and focus-management-on-error (added in the Pre-Phase-4 remediation — was a gap in this plan, not just code, per the skill-alignment audit's finding #9)**.

### Files to Inspect

- [includes/auth_modals.php](../includes/auth_modals.php) — `#loginModal`, `#signupModal` markup.
- [js/app.js](../js/app.js) — `#loginForm`, `#signupForm` handlers.

### Files Expected to Change

- [js/app.js](../js/app.js) — on error alert display in `#loginError` / `#signupError`, add `animate__animated animate__shakeX` to the alert element (or to the modal-content wrapper — decide during implementation). Remove the classes after 500 ms so the next error can re-trigger.
- [js/app.js](../js/app.js) — **new scope:** on validation/submit error, move keyboard focus to the first `.is-invalid` field if one exists, otherwise to the error alert itself (`tabindex="-1"` + `.trigger('focus')`, since the alert isn't natively focusable). Applies to `#loginForm`/`#signupForm` here, and should be checked for `#contactForm` (About Us) and `#bookingModal`'s Preview/Confirm error paths too when those phases are revisited — this phase is where the pattern is first established. Skill basis: `focus-management` (WCAG/MD) — after a submit error, auto-focus the first invalid field; visual `role="alert"` announcement alone doesn't move keyboard focus.

### Existing Components to Reuse

- Animate.css `animate__shakeX` (already loaded).
- Existing error alert markup.

### New Components Required

- None.

### Existing Logic to Preserve

- Full auth flow.
- "Login as Admin" button behavior.

### Motion Patterns

- Attention/feedback for validation errors (Level 2 feedback).

### Dependencies

Phase 1, Phase 2.

### Risks

- Shake on the whole modal is jarring; shake on the alert alone is subtle and effective. Prefer the latter.
- Reduced-motion neutralises the shake (via the global rule from Phase 1) — verify.

### Testing Requirements

- Submit login with wrong password: alert appears + subtle shake.
- Submit signup with mismatched passwords: same.
- Reduced-motion: alert appears without shake.
- Submit login/signup with a field-level validation error: keyboard focus lands on the first `.is-invalid` field.
- Submit login/signup with a request-level error (no specific invalid field, e.g. wrong password): keyboard focus lands on the error alert.

### Acceptance Criteria

- [ ] Login/signup error alerts shake once on error.
- [ ] Focus lands on email field on modal open.
- [ ] Submit buttons spin during fetch.
- [ ] On submit error, focus moves to the first `.is-invalid` field, or to the alert if no field-level error exists.

---

## Phase 9 — Admin Motion (Minimal)

### Objective

Extend spinner pattern to every admin AJAX button that doesn't already have one (Vouchers, All Transactions, Vehicles-modal cases that use AJAX). No decorative motion added — admin stays fast and dense.

### Files to Inspect

- [admin-dashboard.php](../admin-dashboard.php) — confirm-transaction handler [inline].
- [admin_vehicles.php](../admin_vehicles.php) — Add/Edit use page-POST (skip); Delete uses `<a href>` + `confirm()` (skip — spinner is not applicable to a nav click).
- [admin_users.php](../admin_users.php) — edit + delete already have spinners; verify + consolidate to use `PMSMotion.setButtonLoading` for consistency.
- [admin_vouchers.php](../admin_vouchers.php) — Add/Edit/Delete AJAX (no spinner currently).
- [view-all-data.php](../view-all-data.php) — Edit-time modal, delete, confirm-transaction (no spinners currently).

### Files Expected to Change

- Every admin page identified above — replace ad-hoc spinner code (where present) with `PMSMotion.setButtonLoading` calls; add spinner calls where they don't exist.
- Load `js/motion.js` on every admin page (add `<script src="js/motion.js"></script>` after the `<script src="js/app.js">` load).

### Existing Components to Reuse

- `PMSMotion.setButtonLoading`.
- Existing AJAX handlers (unchanged in outcome).

### New Components Required

- None.

### Existing Logic to Preserve

- All admin CRUD flows.
- All admin redirects (page POST/redirect pattern on Vehicles CRUD unchanged).
- DataTables init and behavior.
- Admin sidebar (existing hover transition).

### Motion Patterns

- Loading state (Level 1 feedback).

### Dependencies

Phase 1, Phase 2 (for `setButtonLoading`).

### Risks

- Admin power-users may find the disabled-during-request state slower than "click and it just works". Response times are typically < 300 ms; the tradeoff (preventing double-submits, giving visible confirmation) is worth it. Confirm with real admin usage.
- Consolidating existing spinner code in `admin_users.php` may regress an established working flow — treat as a low-risk refactor and diff carefully.

### Testing Requirements

- Add voucher: spinner during save.
- Edit voucher: spinner during save.
- Delete voucher: spinner during delete.
- Confirm transaction (Dashboard): spinner during confirm.
- Confirm transaction (All Transactions page): spinner during confirm (fixes UX around the existing bug where the button binding is broken — spinner won't fix the binding bug itself, still bound to the delete-confirmation flow per [BUGS.md](BUGS.md); document as a separate item).
- Edit booking time: spinner during save.
- Delete booking: spinner during delete.

### Acceptance Criteria

- [ ] Every admin AJAX submit button spins during its request.
- [ ] `admin_users.php` existing spinners consolidated to `PMSMotion.setButtonLoading`.
- [ ] No admin page gets scroll-reveal, hover lifts, or entrance animation beyond existing sidebar hover.

---

## Phase 10 — Accessibility / Reduced Motion QA

### Objective

Cross-verify that `prefers-reduced-motion: reduce` neutralises every decorative animation while keeping essential feedback (spinners, focus rings, badge colours, alerts) functional. Verify keyboard navigation.

### Files to Inspect

- All customer and admin pages.
- [css/styles.css](../css/styles.css) — reduced-motion block.

### Files Expected to Change

- Any file where reduced-motion QA finds a leak — most likely candidates: Animate.css classes that don't respect the blanket `!important` rule, custom keyframes that use non-standard property names, or JS-triggered animations that check `prefersReducedMotion()` incorrectly.

### Testing Requirements

- Enable OS-level "Reduce motion":
  - Windows: Settings → Ease of Access → Display → "Show animations in Windows".
  - macOS: System Preferences → Accessibility → Display → Reduce motion.
- Reload each page and verify:
  - Hero: video hidden, fallback image shown (existing behavior).
  - Homepage: no scroll reveal, no stats count-up, no card entrance.
  - Vehicles: cards appear final-state, no stagger.
  - Modals: instant open/close (Bootstrap fade collapsed to 0.01 ms — visible-but-imperceptible).
  - Spinners: still rotate (essential feedback exception).
  - Focus rings: still visible.
  - Alerts: still appear (but without shake, without slide).
  - Card hover: **decision recorded (Pre-Phase-4 remediation, see [MOTION_DESIGN_ANALYSIS.md](MOTION_DESIGN_ANALYSIS.md) §13.1) — lift (`transform`) no longer applies under reduced-motion; the shadow/color feedback on the same hover state still does.** `.feature-card:hover`, `.vehicle-card:hover`, and `.testimonial-card:hover`'s `transform` declarations are now wrapped in `@media (prefers-reduced-motion: no-preference)` in `css/styles.css`; verify this live during this phase rather than re-deciding it.

- Keyboard-only nav:
  - Tab through every page: focus rings visible on every stop.
  - Modal Tab-trap: focus stays inside modal.
  - Enter on Apply-filter button submits form.
  - Escape closes modals.

### Acceptance Criteria

- [ ] Reduced-motion mode neutralises all decorative motion.
- [ ] Reduced-motion mode preserves spinners, focus rings, colour transitions on badges/alerts.
- [ ] Keyboard nav works on every page.
- [ ] Screen-reader spot check: modal open announces label; button loading state announces via `aria-busy`.

---

## Phase 11 — Performance Optimisation

### Objective

Verify motion phase stayed within budget: no new library, bundle < +5 KB, no persistent scroll listeners, only `transform`/`opacity`/etc. animated.

### Files to Inspect

- Every file touched in Phases 1-9.
- Chrome DevTools Performance panel: record page load + scroll on homepage and vehicles page.

### Testing Requirements

- **Bundle size:** verify `js/motion.js` + CSS additions total < 5 KB min+gz.
- **Scroll perf:** record homepage scroll; verify no layout thrash spikes (long green bars in the Performance panel).
- **Interaction perf:** click Preview button; verify spinner appears within 1 frame (< 16 ms).
- **Frame drops:** verify no dropped frames during reveal stagger on vehicles page (test with `throttling: 4x slowdown` in DevTools).
- **Coverage:** run Chrome Coverage tab; verify motion CSS/JS is loaded and used.

### Acceptance Criteria

- [ ] No new external animation library.
- [ ] Bundle size delta < 5 KB min+gz.
- [ ] No persistent scroll listener added beyond IntersectionObserver.
- [ ] Animated properties limited to `transform` / `opacity` / `box-shadow` / `background-color` / `color` / `border-color` / `outline`.
- [ ] Scroll and interaction perf show no regression vs. pre-phase baseline.

---

## Phase 12 — Final Motion QA

### Objective

Cross-browser, cross-device sweep. Anything found here is patched in the phase that introduced it.

### Files to Inspect

- All customer and admin pages, in every browser/device combination.

### Testing Requirements

- **Browsers:** Latest Chrome, Safari, Firefox, Edge.
- **Devices:**
  - Desktop 1920 × 1080.
  - Laptop 1440 × 900.
  - Tablet 768 × 1024 (iPad-class).
  - Mobile 390 × 844 (iPhone-class).
  - Mobile 360 × 800 (Android-class).
- **Scenarios per page:**
  - First load (cold cache).
  - Reload (warm cache).
  - Scroll top-to-bottom.
  - Interact with every button / form / modal.
  - Complete a full booking journey.
- **Motion checks:**
  - No layout shifts caused by reveal animations.
  - No double-animation on any element.
  - Stagger sequences feel natural (not slow, not stuttered).
  - Modals open/close smoothly.
  - Spinners appear on every AJAX action.
  - Focus rings visible in every keyboard-tab context.
- **Reduced-motion checks:** repeat the sweep with OS-level reduced-motion enabled.

### Acceptance Criteria

- [ ] Every acceptance criterion from Phases 1-11 passes on every browser/device.
- [ ] No visual regressions found.
- [ ] Dead motion code from [MOTION_DESIGN_ANALYSIS.md](MOTION_DESIGN_ANALYSIS.md) §3.8 confirmed unchanged (still present, still non-functional — cleanup is a separate future phase).

---

## Phase Dependency Diagram

```
Phase 1 (Foundation)
        │
        ├──▶ Phase 2 (Global Micro-Interactions)
        │           │
        │           ├──▶ Phase 3 (Homepage Motion)
        │           │           │
        │           │           └──▶ Phase 3.5 (Stats Counter, optional)
        │           │
        │           ├──▶ Phase 4 (Vehicle Listing Motion)
        │           │
        │           ├──▶ Phase 5 (Vehicle Details & Booking Motion)
        │           │           │
        │           │           └──▶ Phase 7 (Contact/Booking Consolidation)
        │           │
        │           ├──▶ Phase 6 (About Us Motion) ──▶ Phase 7
        │           │
        │           ├──▶ Phase 8 (Authentication Motion)
        │           │
        │           └──▶ Phase 9 (Admin Motion)
        │
        └──▶ Phase 10 (Reduced-Motion QA) — after all P0/P1 delivery
                        │
                        └──▶ Phase 11 (Performance Optimisation)
                                    │
                                    └──▶ Phase 12 (Final QA)
```

**Parallelism opportunity:** Phases 3, 4, 5, 6, 8 can run in parallel once Phases 1 + 2 are landed. Phase 9 is fully independent from Phases 3-8 and can run any time after Phase 2. Phases 10-12 are strictly sequential and land last.

---

## Recommended Delivery Order (single-track)

For a single implementer landing one phase at a time:

1. **Phase 1** — Foundation. Ship it.
2. **Phase 2** — Global spinners + modal focus. Highest UX ROI on the whole plan.
3. **Phase 4** — Vehicles page (spinner on filter/pagination is essential; grid reveal is polish).
4. **Phase 5** — Booking motion. Together with Phase 2, closes the "silent AJAX" UX gap.
5. **Phase 3** — Homepage marketing motion.
6. **Phase 6** — About motion.
7. **Phase 8** — Auth error shake polish.
8. **Phase 9** — Admin spinners.
9. **Phase 3.5** — Stats counter (optional).
10. **Phase 10** — Reduced-motion QA.
11. **Phase 11** — Performance profiling.
12. **Phase 12** — Cross-browser QA.

---

## Out of Scope for This Motion Phase

Per audit prompt STEP 4 and STEP 17, and to keep this phase focused:

- **Removing dead motion code** (broken `.card.3d`, `.animate__pulse` selector, unused scroll handler, `#featuredCarsCarousel3D` selectors, etc. — full list in [MOTION_DESIGN_ANALYSIS.md](MOTION_DESIGN_ANALYSIS.md) §3.8). Separate cleanup phase.
- **Removing Animate.css from pages that don't use it** (loaded on every customer page, used only on `index.php`). Separate perf phase.
- **Refactoring existing hardcoded durations** in `styles.css` (§3.1) to use motion tokens. Follow-up refactor.
- **Page transitions / route transitions**. Requires SPA architecture change.
- **Row-level in-place transitions on Transactions page** (cancel/return currently full-page-reload). Requires refactoring `transactions.php` to AJAX-driven.
- **Skeleton loaders** for tables and grids. Optional future addition; not P0/P1.
- **Micro-illustrations, mascots, animated SVG icons**. Not in current design language.
- **Any admin motion beyond spinners** (§16.5).

---

## Sign-off Gate

Per [CLAUDE.md](../CLAUDE.md) working rules: **do not proceed to any Phase's implementation without explicit approval on that Phase.** Approvals should be per-Phase, not per-plan.

An approval on Phase 1 does not carry through to Phase 2. Each phase's Files to Inspect / Files Expected to Change / Acceptance Criteria must be reviewed before implementation begins for that phase.

Approvals may be batched (e.g., "approve Phases 1-2 together" or "approve Phases 1-4") when the reviewer is comfortable with the scope.

---

*This document is an implementation plan only. No code has been modified. See [MOTION_DESIGN_ANALYSIS.md](MOTION_DESIGN_ANALYSIS.md) for the underlying design rationale and audit findings.*
