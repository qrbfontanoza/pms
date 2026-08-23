# PMS Motion & Interaction Design Analysis

Companion document to [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md), [COMPONENT_LIBRARY.md](COMPONENT_LIBRARY.md), and [UI_ANALYSIS.md](UI_ANALYSIS.md). This is a UX / motion-design audit of the PMS Car Rental website in its current state and a practical, implementation-ready motion strategy for the next phase.

**Status:** Analysis and design planning only. No code has been modified. Implementation is defined separately in [MOTION_DESIGN_IMPLEMENTATION_PLAN.md](MOTION_DESIGN_IMPLEMENTATION_PLAN.md) and requires per-phase approval before any work begins, per the working rules in [CLAUDE.md](../CLAUDE.md).

**Method:** every customer-facing and admin PHP page was read (cross-referenced with [UI_ANALYSIS.md](UI_ANALYSIS.md) and [COMPONENT_LIBRARY.md](COMPONENT_LIBRARY.md)), plus [css/styles.css](../css/styles.css) (737 lines) and every file in [js/](../js/). Motion recommendations are anchored to the actual DOM that exists today, not to hypothetical redesigns.

---

## 1. Executive Summary

### Where PMS stands today
- **Animate.css 4.1.1 is loaded on every customer page** but is used in exactly **5 places** — the hero `<h1>` (`animate__fadeInDown`), hero `<p>` (`animate__fadeInUp`), and three homepage feature cards (`animate__fadeInLeft/Up/Right`). All five fire once on page load, unconditionally, regardless of viewport.
- **jQuery scroll listener in [app.js:995-1001](../js/app.js#L995)** attempts a scroll-triggered `animate__fadeInUp` on the same `.feature-card` elements that already have Animate.css classes from page load — competing / no-op behavior, not a working scroll-reveal system.
- **`.animate__pulse` handler at [app.js:1005-1008](../js/app.js#L1005)** targets a hero text input that does not exist in `index.php`'s markup — dead code.
- **CSS-level motion is limited and consistent-in-spirit**: card hover lifts (`.feature-card`, `.vehicle-card`, `.testimonial-card`), image scale-on-hover inside vehicle cards, navbar link colour transitions, and Bootstrap's own modal/carousel/collapse transitions. All use short durations (0.2–0.35s) and only animate `transform`/`opacity`/`box-shadow` — a good baseline.
- **`prefers-reduced-motion` is honoured in exactly one place** ([css/styles.css:79-87](../css/styles.css#L79)): the hero background video is swapped for a still image. Every other animation ignores the media query.
- **No scroll-triggered reveal system, no stagger, no counters, no page-transitions, no skeleton loaders, no loading spinners on submit buttons** (except two admin AJAX handlers).
- **Perceived-performance is weak**: booking Preview, Confirm, Contact-Form submit, Voucher apply, and every customer-side AJAX call give zero in-flight feedback — the button stays clickable and unchanged while the network request runs.

### What the site actually needs
PMS is a service booking site for a Filipino car-rental audience. The primary jobs-to-be-done are: **find a vehicle → understand what you're renting → confirm you're actually being charged the right amount → complete the booking**. Motion should serve these four jobs.

The gap between "site with almost no motion" and "site that feels modern and trustworthy" is bridged by:
1. **Feedback motion on interactive elements** (buttons, form fields, filters) so every user action produces a visible reaction within ~200 ms.
2. **Scroll-reveal on homepage marketing sections** (features, stats, testimonials, CTA) so the page feels alive as the visitor scrolls, but nothing important is hidden behind an animation.
3. **Real loading/in-flight states** on booking Preview, Confirm, and Contact submissions — the highest-leverage motion investment on the whole site.
4. **Consistent modal, filter, and pagination transitions** (Bootstrap already provides these; the work is turning them on where they were bypassed).
5. **Nothing on the admin side beyond what already exists** — data density and speed matter more than aesthetics there.

### Recommended architecture in one line
**Pure CSS transitions + `IntersectionObserver` for scroll-reveal + Bootstrap's own component transitions.** No new animation library. Rationale in §7.

### Highest-impact motion investments (P0)
1. Loading spinner + disable-on-click on `#btnPreview`, `#btnConfirm`, `#contactForm` submit.
2. Real scroll-reveal on homepage sections (Features, Stats, Testimonials, CTA) via `IntersectionObserver` — replaces the broken jQuery scroll handler.
3. Skeleton / spinner state on vehicles-page filter submit (currently the page reloads with no visual cue).
4. Focus-visible outlines on all buttons and inputs (accessibility + interaction feedback in one).
5. Global `prefers-reduced-motion` gate that neutralises all decorative motion.

Everything else is P1/P2 polish.

---

## 2. Current PMS UX Assessment

### 2.1. Frontend tech baseline (verified per [UI_ANALYSIS.md](UI_ANALYSIS.md) global facts)

| Layer | Version | Loaded where |
|---|---|---|
| Bootstrap CSS + JS bundle | 5.3.2 (CDN) | Every customer page, every admin page |
| Font Awesome | 6.4.2 (CDN) | Every customer page, most admin pages |
| Animate.css | 4.1.1 (CDN) | Every customer page (used on: `index.php` only) |
| jQuery | 3.7.1 (CDN) | Every page |
| DataTables (Bootstrap 5) | via CDN | 3 admin pages (Vehicles, Users, All Transactions) |
| Custom CSS | [css/styles.css](../css/styles.css) (737 lines) | Every page |
| Custom JS | [app.js](../js/app.js) (1170 lines), plus [voucher-manager.js](../js/voucher-manager.js), [booking-validation.js](../js/booking-validation.js), [printer.js](../js/printer.js) | Loaded per-page (see [COMPONENT_LIBRARY.md](COMPONENT_LIBRARY.md)) |
| Fonts | Inter + Poppins (Google Fonts, imported in `styles.css`) | Every page |

### 2.2. Interaction ecosystem (from [UI_ANALYSIS.md](UI_ANALYSIS.md) and [COMPONENT_LIBRARY.md](COMPONENT_LIBRARY.md))

**Customer pages:** `index.php`, `vehicles.php`, `faq.php`, `about.php`, `transactions.php`, `receipt.php`, plus auth touchpoints (`login.php`, `register.php`, `forgot_password.php`, `reset_password.php`, `change_password.php` — the last four are server-rendered form pages, not modals).

**Admin pages:** `admin-login.php`, `admin-dashboard.php`, `admin_vehicles.php`, `admin_users.php`, `admin_vouchers.php`, `view-all-data.php`.

**Interactive elements in use:** navbar (fixed-top, collapse-on-mobile), 3 hero CTAs, 4-input search widget, category+price+seats+fuel+transmission+availability sidebar filter (`vehicles.php`), 9-per-page vehicle-card grid with pagination, `#vehicleDetailsModal`, `#bookingModal` (Preview→Confirm→Redirect), login/signup modals (`#loginModal`, `#signupModal`), FAQ accordion, contact form, `#returnEarlyModal`, `#cancelBookingModal`, `#returnReceiptModal`, featured-cars carousel (`carousel-fade`), homepage stats bar, homepage testimonials, homepage CTA banner, admin sidebar toggle, DataTables tables, admin AJAX edit/delete buttons (with spinner on user edit/delete only).

### 2.3. What's already handled well

- **Design tokens are centralised** in `:root` custom properties ([css/styles.css:3-13](../css/styles.css#L3)) — motion tokens should follow the same pattern.
- **All existing CSS transitions animate cheap properties** (`transform`, `opacity`, `box-shadow`, `color`, `background-color`) — no `width`/`height`/`margin`/`top`/`left` animations found. This is already a strong performance baseline.
- **Card hover lifts are consistent** across `.feature-card`, `.vehicle-card`, `.testimonial-card` — same idea (`translateY(-4/6/7px)`), similar durations. The pattern is ready to be formalised as a motion token.
- **Bootstrap's own component motion works out-of-the-box** — modal fade/slide, carousel-fade, accordion collapse, offcanvas slide-in for the mobile filter panel and admin sidebar. None of these were disabled.
- **`prefers-reduced-motion` handling for the hero video** is a correct pattern — it just needs to be applied to the rest of the site.

### 2.4. Confirmed motion / UX gaps

| Gap | Where | Impact |
|---|---|---|
| No in-flight feedback on booking Preview/Confirm | `#btnPreview`, `#btnConfirm` in `#bookingModal` ([vehicles.php](../vehicles.php)) | High — user can double-submit, isn't sure the click registered |
| No in-flight feedback on Contact form submit | `#contactForm` ([about.php](../about.php)) | Medium |
| Scroll-triggered fade-in doesn't actually work | [app.js:995-1001](../js/app.js#L995) fires against elements that already fade in at page load | Homepage feels static once you scroll past the hero |
| Modal opens with visible content-flash | Every `.modal` — Bootstrap's own fade is 150 ms opacity + translateY, but modal content inside populates from JS *after* the fade begins, causing brief empty-modal + then content pop | Feels rough on `#vehicleDetailsModal`, `#bookingModal`, `#returnReceiptModal` |
| Filter submit has no loading state | `#filterSidebar` in `vehicles.php` submits GET, full page reloads | User can't tell if filter took effect until reload completes |
| Pagination click has no loading state | `.page-link` in `vehicles.php` | Same as filter |
| Voucher apply has no visible feedback | `#voucherSelect` change in `voucher-manager.js` | Discount just appears/doesn't appear silently |
| Category badges / availability badges never animate on state change | Everywhere they render | Not a bug, but a real missed opportunity (see §10) |
| Focus rings are Bootstrap default (visible but generic) | Site-wide | Accessible but not designed |
| No skeleton loaders anywhere | Site-wide | Long DataTables loads and vehicles-page grid load feel like a stall |
| No page-transition or route-transition | Full page reloads on every navigation | Acceptable for a server-rendered PHP site (see §7 recommendation) |

### 2.5. Dead / broken motion (source: [COMPONENT_LIBRARY.md](COMPONENT_LIBRARY.md) §Dead code and direct verification)

| Item | Location | Why it's broken |
|---|---|---|
| `.card.3d` hover effect | `index.php:102,120,138` | CSS defines `.card.three-d`, markup uses `.card.3d` — never applies |
| `.alert-floating` | `js/app.js:562-568` `showFloatingAlert()` | No CSS rule for `.alert-floating` — doesn't actually float |
| `#featuredCarsCarousel3D` control arrows | [css/styles.css:211-234](../css/styles.css#L211) | Selector uses `#featuredCarsCarousel3D` but the page uses `#featuredCarsCarousel` — never matches |
| Scroll fade-in on `.feature-card` | [app.js:995-1001](../js/app.js#L995) | Elements already have `animate__fadeIn*` classes from page load; the scroll handler adds a duplicate class that never re-triggers Animate.css |
| `.animate__pulse` on hero search input | [app.js:1005-1008](../js/app.js#L1005) | Selector `.hero-immersive input[type="text"]` matches nothing — hero has no text input |
| `.booking-step-indicator .step-circle` transition | [css/styles.css](../css/styles.css) | Fully styled, zero matching markup (multi-step booking modal is dead — see [COMPONENT_LIBRARY.md](COMPONENT_LIBRARY.md) §Dead code #7) |
| `showStep()` `.fadeIn(200)` | [app.js:591](../js/app.js#L591) | Targets `#bookingStep1/2/3` inside `#bookingMultiModal` — modal doesn't exist |

**Per §STEP 4 of the audit prompt: none of these will be removed as part of the motion phase.** They're documented here so the implementation plan doesn't accidentally build on them.

---

## 3. Current Motion Audit

Full inventory of every motion-related declaration currently in the codebase.

### 3.1. CSS transitions (from [css/styles.css](../css/styles.css))

| Selector | Property | Duration | Line | Verdict |
|---|---|---|---|---|
| `.navbar .nav-link` | `color`, `background-color` | 0.2s | :250 | Keep — good baseline |
| `#adminSidebar .nav-link` | `background` | 0.2s | :294 | Keep |
| `.glassmorph` | `box-shadow` | 0.28s | :376 | Keep |
| `.carousel-inner` | `box-shadow` | 0.35s | :386 | Keep |
| `.feature-card` | `transform`, `box-shadow` | 0.2s / 0.26s | :391 | Keep; formalise as `--motion-duration-normal` |
| `.vehicle-card` | `transform`, `box-shadow` | 0.2s / 0.25s | :454 | Keep |
| `.vehicle-img-wrap img` | `transform` | 0.35s | :471 | Keep — the best-crafted animation in the file |
| `.testimonial-card` | `transform`, `box-shadow` | 0.2s | :701 | Keep |

### 3.2. CSS `:hover` transforms

| Selector | Transform | Line |
|---|---|---|
| `.feature-card:hover` | `translateY(-7px) scale(1.05)` + coloured glow | :394 |
| `.vehicle-card:hover` | `translateY(-6px)` + shadow | :460 |
| `.vehicle-card:hover .vehicle-img-wrap img` | `scale(1.05)` | :478 |
| `.testimonial-card:hover` | `translateY(-4px)` + shadow | :704 |

**Consistency issue:** `.feature-card` uses both `translateY(-7px)` *and* `scale(1.05)` — the only element in the whole codebase that scales the container on hover. Others only translate. Motion tokens (§12) will consolidate.

### 3.3. CSS `@keyframes` and `animation:` declarations

**None found in [css/styles.css](../css/styles.css).** All animation currently comes from Animate.css (external) or Bootstrap's own component transitions.

### 3.4. Animate.css classes used in HTML

| Element | Class | Trigger | Location |
|---|---|---|---|
| Hero H1 | `animate__animated animate__fadeInDown` | Page load | `index.php:44` |
| Hero P | `animate__animated animate__fadeInUp` | Page load | `index.php:45` |
| Feature card 1 | `animate__animated animate__fadeInLeft` | Page load | `index.php:120` |
| Feature card 2 | `animate__animated animate__fadeInUp` | Page load | `index.php:128` |
| Feature card 3 | `animate__animated animate__fadeInRight` | Page load | `index.php:136` |

**All fire simultaneously on page load** — no stagger, no viewport gating. Feature cards animate even when the visitor's viewport is nowhere near them.

**Only used on `index.php`.** Every other customer page loads Animate.css from the CDN but uses no `animate__*` classes anywhere.

### 3.5. Bootstrap-native motion actually in use

| Component | Motion | Where |
|---|---|---|
| `.modal.fade` | 150 ms opacity, 300 ms transform (Bootstrap default) | `#loginModal`, `#signupModal`, `#bookingModal`, `#vehicleDetailsModal`, `#cancelBookingModal`, `#returnEarlyModal`, `#returnReceiptModal`, all admin modals |
| `.carousel-fade` | Bootstrap fade transition | `#featuredCarsCarousel` in `index.php` |
| `.collapse` | 350 ms height (Bootstrap default) | Navbar mobile collapse, FAQ accordion |
| `.offcanvas` | Bootstrap slide-in | Mobile filter panel in `vehicles.php`, admin sidebar |
| `.dropdown-menu` | Bootstrap fade+scale | Auth dropdown in navbar |

### 3.6. jQuery-based motion in `app.js`

| Location | Behavior | Status |
|---|---|---|
| `.fadeIn(200)` inside `showStep()` [:591](../js/app.js#L591) | Multi-step booking modal step transition | Dead — modal doesn't exist |
| `.hero-immersive input[type="text"]` `animate__pulse` [:1005](../js/app.js#L1005) | Pulse on input | Dead — no such input |
| `.feature-card` scroll fade-in [:995-1001](../js/app.js#L995) | Adds `animate__fadeInUp` when in viewport | Broken — element already animated |
| `#contactAlert` show/hide [:412-428](../js/app.js#L412) | `.removeClass('d-none')` + `setTimeout` hide after 2500ms | Functional but no animation |
| Modal `.modal('show')`/`.modal('hide')` calls | Bootstrap's own transition | Functional |

### 3.7. `prefers-reduced-motion` coverage

**One occurrence, [css/styles.css:79-87](../css/styles.css#L79):**
```css
@media (prefers-reduced-motion: reduce) {
  .hero-immersive video { display: none; }
  .hero-immersive .hero-fallback-img { display: block; }
}
```

Every other transition, hover-transform, `animate__*` class, and Bootstrap fade fires unconditionally regardless of user preference. This is the single largest accessibility gap in the current motion state (see §13).

### 3.8. Summary — what to keep, modify, remove

| Category | Recommendation |
|---|---|
| Existing CSS transitions on cards / navbar / glassmorph | **Keep**, normalise durations to tokens (§12) |
| Card hover lifts | **Keep**, drop the `scale(1.05)` on `.feature-card` for consistency with vehicle/testimonial cards |
| Hero Animate.css classes | **Modify** — keep the entrance direction, replace the classes with CSS-token-based keyframes so timing is consistent site-wide |
| Feature-card page-load Animate.css classes | **Replace** with `IntersectionObserver` scroll-reveal + stagger |
| Broken `.feature-card` scroll handler in `app.js` | **Do not remove in this phase** (per audit prompt STEP 4), but replace its behavior with a working `IntersectionObserver` in a new file |
| Broken `.animate__pulse` selector | **Do not remove** |
| Dead `.card.3d`, `.alert-floating`, `#featuredCarsCarousel3D` selectors | **Do not remove** |
| Bootstrap modal / carousel / collapse / offcanvas transitions | **Keep as-is** — they're already correct |
| Missing spinners on submit buttons | **Add** (P0) |
| Missing scroll-reveal on non-`index.php` pages | **Add** (P1) |
| Missing `prefers-reduced-motion` coverage | **Add** globally (P0) |

---

## 4. User Journey Analysis

Trace of the primary customer journey with motion opportunities called out per step.

```
Landing on PMS (index.php)
        ↓
Understanding the service (index.php scroll: Features → Stats → Testimonials → CTA)
        ↓
Browsing vehicles (vehicles.php)
        ↓
Filtering / searching (vehicles.php sidebar + widget from index.php)
        ↓
Viewing vehicle details (#vehicleDetailsModal on vehicles.php)
        ↓
Deciding to reserve (Reserve Now button inside #vehicleDetailsModal)
        ↓
Authentication (#loginModal / #signupModal — modal on top of vehicles.php)
        ↓
Booking (#bookingModal: Preview → Confirm)
        ↓
Confirmation (redirect to receipt.php)
        ↓
Managing bookings (transactions.php: cancel, return-early, view receipt)
```

### 4.1. Journey-level motion opportunities

| Step | Motion opportunity | Purpose it serves (see §5) |
|---|---|---|
| Landing | Hero heading + subhead + search widget entrance | Orientation, Hierarchy |
| Understanding | Scroll-reveal on Features / Stats / Testimonials / CTA with stagger | Guidance, Continuity |
| Browsing | Vehicle-card grid fade-in-up with stagger as cards enter viewport | Continuity |
| Filtering | Loading indicator on form submit + smooth grid re-render feedback | Feedback, Perceived performance |
| Details | Modal slide-fade in (Bootstrap default) + image micro-motion | Continuity, Delight |
| Reserve | Modal close → auth modal open sequenced transition (already sequenced in JS as of Step 3 of Vehicle Details phase) | Continuity |
| Auth | Focus visible on first input on modal-shown + subtle shake on invalid submit | Feedback |
| Booking | **Loading spinner on Preview + Confirm buttons; smooth reveal of the preview summary; disabled state on Confirm until preview succeeds** | Feedback, Perceived performance |
| Confirmation | Full-page redirect to `receipt.php` (no in-app transition possible without a build system) | — |
| Managing | Row highlight-then-fade on cancel/return-early success; badge status transition | Feedback, Hierarchy |

### 4.2. Journey-level anti-patterns to avoid

- **Do not animate content the user is trying to read** (booking summary, receipt, transaction history rows, admin tables). Reveal it, don't dance with it.
- **Do not gate primary content behind a scroll-reveal delay.** If the vehicle grid takes 700 ms to stagger in, the visitor waits 700 ms before they can act. Cards should be visible within ≤400 ms of viewport entry, staggered by ≤80 ms.
- **Do not animate the navbar / header on scroll** (no auto-hide, no shrink-on-scroll). It's a fixed-top persistent element — motion here creates layout thrash for zero UX benefit.
- **Do not animate anything on the admin side beyond loading spinners and Bootstrap defaults.** Admin users are power users; density and speed beat polish.

---

## 5. Motion Design Principles

Every recommended animation in this document is justified by at least one of these seven purposes. Anything that can't be justified is dropped.

| # | Principle | Definition | Example use in PMS |
|---|---|---|---|
| 1 | **Orientation** | Helps the user understand where they are on the page or in a flow. | Modal slide-fade tells the user "you're in a dialog now, the page context is paused." |
| 2 | **Feedback** | Confirms an action was received and is being processed. | Button spinner + disabled state on `#btnConfirm` while the reserve request is in flight. |
| 3 | **Continuity** | Shows the relationship between two states so change doesn't feel like a jump-cut. | Vehicle-card image scaling up on hover previews what the details modal will show. |
| 4 | **Hierarchy** | Draws attention to what matters most on the page. | Hero H1 entrance runs before the H2 subhead runs before the search widget appears. |
| 5 | **Guidance** | Helps the user know what to look at or do next. | Focus ring moves to first form field when a modal opens. Empty-state fade tells the user "nothing here, do this." |
| 6 | **Delight** | Adds subtle personality where it costs nothing. | Testimonial card hover lift. Vehicle-image scale-on-hover. |
| 7 | **Perceived performance** | Makes loading/state changes feel smoother than they are. | Skeleton on filter reload; button-spinner during AJAX. |

If a proposed animation only serves #6 (Delight), it's P2 or P3.

---

## 6. Open-Source Resource Research

Reference research on the four candidate libraries called out in the audit prompt. Each is evaluated against **the actual PMS constraints** (Bootstrap 5 + jQuery + no build system + shared CDN-loaded assets + small team + performance on mid-tier Filipino mobile devices).

### 6.1. AOS — Animate on Scroll (`aos: ^2.3.4`)

- **What it is:** ~14 KB min+gzip. Declarative scroll-triggered animation via `data-aos="fade-up"` attributes.
- **Motion library it provides:** ~30 preset entrances (fade, slide, zoom, flip), configurable duration/delay/easing/offset via `data-aos-*` attributes, one global `AOS.init()` call, one global `AOS.refresh()` when DOM changes.
- **Fit for PMS:** **Strong.** Declarative markup fits PHP-rendered templates naturally, no build required, works with jQuery/Bootstrap without conflict, tiny bundle.
- **Concerns:** Adds a third animation library on top of Animate.css + Bootstrap. Doesn't help with stateful micro-interactions (button spinners, form feedback) — only scroll reveal. Uses `IntersectionObserver` under the hood, so we can get the same benefit without the dependency (see §7).

### 6.2. Anime.js v4

- **What it is:** ~15 KB min+gzip. Powerful JavaScript animation engine — timeline, stagger, spring physics, SVG morphing, scroll observer.
- **Fit for PMS:** **Poor for this phase.** Its strengths (timelines, complex sequencing, SVG) don't map to any concrete PMS need. Reserving it for a future phase (e.g. an animated booking-progress SVG) is fine, but adopting it now would be over-engineering.

### 6.3. Animate.css 4.1.1 (already loaded)

- **What it is:** ~70 KB min+gzip of pre-baked CSS keyframes. Currently loaded on every customer page but used 5 times (§3.4).
- **Fit for PMS:** **Keep, but rein in usage.** It's already loaded, so incremental use is free. But the keyframes are opinionated (`fadeInDown` translates 100 px, which is too much for content that starts near the top of the viewport). We should:
  - Continue using it for the hero entrance where the drop-in reads intentionally.
  - Replace scroll-reveal usage with our own CSS keyframes (16-24 px translate, not 100 px) using motion tokens.
  - Reserve `animate__shakeX` for form-error feedback (P1) and `animate__heartBeat`/`animate__bounce` for nothing (too playful for a booking site).

### 6.4. GSAP + ScrollTrigger

- **What it is:** ~30 KB min+gzip for core + ScrollTrigger. The industry-standard animation engine.
- **Fit for PMS:** **Not recommended.** GSAP shines for scroll-linked, timeline-heavy, or pinning-based marketing sites. PMS is a transactional booking site with a hero, a card grid, and modals — none of which need GSAP-level power. Adopting GSAP for scroll fade-ins is famously overkill; the maintenance cost (learning curve for anyone joining the project, license considerations for the Club plugins) is not justified by the payoff.

### 6.5. Comparison table

| Option | Bundle (min+gz) | Learning curve | Fits jQuery/Bootstrap | Handles scroll reveal | Handles micro-interactions | `prefers-reduced-motion` support | Verdict for PMS |
|---|---|---|---|---|---|---|---|
| Pure CSS + IntersectionObserver | 0 KB | Low | Yes | Yes | Yes | Manual (easy) | **Recommended** |
| AOS | ~14 KB | Very low | Yes | Yes | No | Built-in (opt-in) | Acceptable alternative |
| Animate.css | ~70 KB (already loaded) | Very low | Yes | No (needs scroll listener) | Attention effects only | Manual | Keep for hero, don't expand |
| Anime.js | ~15 KB | Medium | Yes | Yes (v4 ScrollObserver) | Yes | Manual | Over-engineered for now |
| GSAP + ScrollTrigger | ~30 KB | High | Yes | Yes | Yes | Manual | Over-engineered; not recommended |

---

## 7. Recommended Motion Architecture

### 7.1. Recommendation

**Pure CSS + `IntersectionObserver` + Bootstrap's own component transitions, with Animate.css kept only for the hero entrance and (optionally) for `animate__shakeX` on form validation errors.**

### 7.2. Why

1. **PMS has no build system.** Every dependency is a CDN `<link>`/`<script>` in the page `<head>`. Each new library is a network request on a first-time visit — on a mid-range Android at 3G, a 30 KB library adds ~200 ms to first paint. Zero libraries is the fastest option.
2. **The scope of motion needed is small.** Scroll fade-in, hover lifts, button spinners, focus rings, and modal transitions cover 95 % of the visible motion recommended below. Every one of those is a CSS transition or a 20-line `IntersectionObserver` script.
3. **CSS transitions have the best `prefers-reduced-motion` story.** One `@media` query can neutralise every declared transition site-wide. AOS also supports this but the CSS approach doesn't require trusting a third-party's implementation.
4. **`IntersectionObserver` is universally supported** in the browsers PMS's Bootstrap 5 baseline already targets (Safari 12.1+, Chrome 51+, Firefox 55+, Edge 15+ — all shipping since 2017-2019). No polyfill needed.
5. **Animate.css is already loaded** and used in one place — removing it isn't in scope, and its `fadeInDown`/`fadeInUp` on the hero read well as-is. Reusing it there means one less new file.

### 7.3. Division of responsibility

| Layer | What it handles |
|---|---|
| **CSS transitions** (in `styles.css`) | All hover states, focus states, active states, button state transitions, colour/background transitions, card lift, image scale, badge pulse, modal / carousel / accordion / offcanvas (via Bootstrap defaults) |
| **CSS keyframes** (new, in `styles.css`) | `fadeInUp` (16 px translate), `fadeIn`, `slideInLeft/Right` (subtle 24 px), `shakeX` fallback if we don't use Animate.css for form errors, spinner rotation for custom button spinners (Bootstrap already provides) |
| **`IntersectionObserver`** (new, in `js/motion.js`) | Toggle a `.is-visible` class on any element with `[data-reveal]` when it enters the viewport — that class triggers the CSS keyframe. Also handles `[data-reveal-stagger]` for staggered grids (vehicle cards, feature cards, testimonials). |
| **Bootstrap component transitions** | Modal, carousel, dropdown, offcanvas, collapse — unchanged |
| **Animate.css** | Hero H1 + P entrance only (already in place). Optionally `animate__shakeX` for `#loginError` / `#signupError` / `#bookingStep1Alert`. |
| **New micro-interaction JS** (in `js/motion.js`) | `setButtonLoading($btn, true/false)` helper that toggles a spinner + disabled state on any submit button. Called from `#btnPreview`, `#btnConfirm`, `#contactForm`, filter-form submit, pagination click. |

### 7.4. Files that will change (implementation-plan preview)

- New: `js/motion.js` (~150 lines: IntersectionObserver, `setButtonLoading`, reduced-motion detection)
- Modified: `css/styles.css` (motion tokens in `:root`, new keyframes, `.is-visible` state, `prefers-reduced-motion` global block, spinner styles)
- Modified: relevant PHP pages (`index.php`, `vehicles.php`, `about.php`, `transactions.php`) to add `data-reveal` / `data-reveal-stagger` attributes and to load `js/motion.js`
- Modified: `includes/client_navbar.php` (add focus-visible + minor transition polish — no navbar auto-hide)

No file will be deleted. No existing markup will be removed.

---

## 8. Page-by-Page Motion Analysis

Only pages that exist in the current codebase. Priority column uses P0 (essential) / P1 (recommended) / P2 (optional) / P3 (avoid). "Motion" values reference tokens defined in §12.

### 8.1. Homepage (`index.php`)

| Element | Motion | Trigger | Duration | Easing | Purpose | Priority |
|---|---|---|---|---|---|---|
| Hero H1 "Find Your Perfect Ride" | Fade + translateY(-16px→0) | Page load | `--motion-duration-slow` (500 ms) | `--motion-easing-entrance` | Hierarchy | P0 (keep existing Animate.css `fadeInDown`) |
| Hero P subhead | Fade + translateY(16px→0) | Page load, delay 150 ms | 500 ms | `--motion-easing-entrance` | Hierarchy | P0 (keep) |
| Search widget card | Fade + translateY(24px→0) | Page load, delay 300 ms | 500 ms | `--motion-easing-entrance` | Guidance (draws eye to primary CTA) | **P1 new** |
| Features section title + eyebrow | Fade + translateY(16px→0) | Viewport entry (IO) | 500 ms | `--motion-easing-entrance` | Continuity | P1 |
| Feature cards (3) | Fade + translateY(24px→0), stagger 80 ms | Viewport entry (IO) | 500 ms | `--motion-easing-entrance` | Continuity | **P1 new** — replaces broken page-load Animate.css |
| Feature-card hover | translateY(-6px) + shadow | Hover | 200 ms | `--motion-easing-standard` | Delight | P1 (adjust from `-7px scale(1.05)` to `-6px` for consistency) |
| Stats bar counters | Count-up (0 → target) | Viewport entry (IO), single run | 1000 ms | `linear` | Feedback (numbers feel earned) | **P2 new** |
| Testimonial cards | Fade + translateY(16px→0), stagger 100 ms | Viewport entry (IO) | 500 ms | `--motion-easing-entrance` | Continuity | P1 |
| Testimonial-card hover | translateY(-4px) + shadow | Hover | 200 ms | `--motion-easing-standard` | Delight | P0 (keep existing) |
| CTA banner | Fade + translateY(24px→0) | Viewport entry (IO) | 500 ms | `--motion-easing-entrance` | Hierarchy | P1 |
| Featured cars carousel | Bootstrap `carousel-fade` | Auto-cycle | Bootstrap default | Bootstrap default | Continuity | P0 (keep) |
| Carousel "Quick View" buttons | Bootstrap modal fade+slide | Click | Bootstrap default | Bootstrap default | Continuity | P0 (keep) |
| Navbar hamburger | Bootstrap collapse | Click (mobile) | Bootstrap default | Bootstrap default | Continuity | P0 (keep) |

### 8.2. Vehicle Listing (`vehicles.php`)

| Element | Motion | Trigger | Duration | Easing | Purpose | Priority |
|---|---|---|---|---|---|---|
| Page header + filter sidebar column | Fade in | Page load | 300 ms | `--motion-easing-entrance` | Orientation | P1 |
| Vehicle cards grid | Fade + translateY(16px→0), stagger 60 ms, cap at 9 cards (matches paginate size) | Viewport entry (IO) | 400 ms | `--motion-easing-entrance` | Continuity | **P0 new** |
| Vehicle card hover (image scale + card lift) | Existing (see §3.1) | Hover | 200/350 ms | `--motion-easing-standard` | Delight | P0 (keep existing) |
| Availability badge | (No animation — already accessible via colour+text) | — | — | — | — | Static |
| "View Car Details" button hover | Colour transition | Hover | 200 ms | `--motion-easing-standard` | Feedback | P1 (formalise) |
| Filter checkbox toggle | Bootstrap default (colour only) | Click | Bootstrap default | Bootstrap default | Feedback | P0 (keep) |
| Filter form Apply button click | Spinner + disabled state | Click, until page reload | 0 → indefinite | — | Perceived performance | **P0 new** |
| Pagination `.page-link` click | Spinner on the clicked link | Click, until page reload | 0 → indefinite | — | Perceived performance | **P1 new** |
| Mobile filter offcanvas | Bootstrap slide-in | Click | Bootstrap default | Bootstrap default | Continuity | P0 (keep) |
| `#vehicleDetailsModal` opening | Bootstrap fade+slide | Click card button | Bootstrap default | Bootstrap default | Continuity | P0 (keep) |
| `#vehicleDetailsModal` image | Fade in (300 ms) once loaded | On modal shown | 300 ms | `--motion-easing-entrance` | Continuity | **P2 new** |
| `#vehicleDetailsModal` Reserve → auth → booking modal sequence | Already sequenced via `hidden.bs.modal` (Vehicle Details phase Step 3) | Click | Bootstrap default | Bootstrap default | Continuity | P0 (keep) |
| `#bookingModal` opening | Bootstrap fade+slide | Click Reserve | Bootstrap default | Bootstrap default | Continuity | P0 (keep) |
| `#bookingModal` `#btnPreview` click | **Spinner in button + disable button + disable form inputs** | Click, until fetch resolves | 0 → response | — | **Feedback + perceived performance — highest-leverage motion on the whole site** | **P0 new** |
| `#bookingModal` `#bookingPreview` reveal | Fade + max-height expand | Preview success | 400 ms | `--motion-easing-entrance` | Feedback | **P1 new** |
| `#bookingModal` `#btnConfirm` click | **Spinner in button + disable** | Click, until fetch resolves | 0 → response | — | Feedback | **P0 new** |
| `#bookingAlert` show | Bootstrap `.fade.show` transition | Alert appears | Bootstrap default | Bootstrap default | Feedback | P1 (turn on) |
| Empty-state `#noResultsMessage` | Fade in | Server-rendered visible | 300 ms | `--motion-easing-entrance` | Guidance | P2 |

### 8.3. FAQ (`faq.php`)

| Element | Motion | Trigger | Duration | Easing | Purpose | Priority |
|---|---|---|---|---|---|---|
| Accordion item open/close | Bootstrap `.collapse` | Click | 350 ms | Bootstrap default | Continuity | P0 (keep) |
| Accordion chevron rotate | CSS `transform: rotate(180deg)` on `.accordion-button:not(.collapsed)::after` | Bootstrap default | Bootstrap default | Bootstrap default | Feedback | P0 (Bootstrap default, already active) |
| Page title fade in | Fade + translateY | Page load | 500 ms | `--motion-easing-entrance` | Orientation | P2 |

### 8.4. About Us (`about.php`)

| Element | Motion | Trigger | Duration | Easing | Purpose | Priority |
|---|---|---|---|---|---|---|
| Company story section | Fade in | Viewport entry (IO) | 500 ms | `--motion-easing-entrance` | Continuity | P1 |
| Feature/stat cards (4) | Fade + translateY(24px), stagger 80 ms | Viewport entry (IO) | 500 ms | `--motion-easing-entrance` | Continuity | P1 |
| Team-member cards (5) | Fade + translateY(16px), stagger 80 ms | Viewport entry (IO) | 400 ms | `--motion-easing-entrance` | Continuity | P1 |
| Team-member card hover | translateY(-4px) + shadow | Hover | 200 ms | `--motion-easing-standard` | Delight | P2 (new — consistency with other cards) |
| Contact form field focus | Border colour + subtle box-shadow "ring" | Focus | 200 ms | `--motion-easing-standard` | Feedback | P1 |
| Contact form submit button click | **Spinner + disabled** | Click, until fetch resolves | 0 → response | — | Feedback | **P0 new** |
| Contact alert show/hide | Bootstrap `.fade.show` | On submit success/failure | Bootstrap default | Bootstrap default | Feedback | P1 |
| CTA banner reveal | Fade + translateY(24px) | Viewport entry (IO) | 500 ms | `--motion-easing-entrance` | Hierarchy | P1 |

### 8.5. Transactions (`transactions.php`)

| Element | Motion | Trigger | Duration | Easing | Purpose | Priority |
|---|---|---|---|---|---|---|
| Summary stat cards | Fade + translateY(16px) | Page load | 400 ms | `--motion-easing-entrance` | Orientation | P2 |
| Booking list rows | Fade + translateY(8px), stagger 40 ms | Page load, cap first 10 rows | 300 ms | `--motion-easing-entrance` | Continuity | P2 |
| Cancel booking / Return early modal | Bootstrap fade+slide | Click | Bootstrap default | Bootstrap default | Continuity | P0 (keep) |
| Cancel/Return confirm button | **Spinner + disabled during AJAX** | Click, until response | 0 → response | — | Feedback | **P0 new** |
| Row disappears on successful cancel (currently full page reload) | (Full reload — no in-page transition possible without larger refactor) | — | — | — | — | P3 avoid — out of scope |
| Return receipt modal | Bootstrap fade+slide | Post-return | Bootstrap default | Bootstrap default | Continuity | P0 (keep) |

### 8.6. Receipt (`receipt.php`)

| Element | Motion | Trigger | Duration | Easing | Purpose | Priority |
|---|---|---|---|---|---|---|
| Whole receipt | Static (post-booking confirmation, user needs to read/print) | — | — | — | — | P3 avoid |
| Nav buttons hover | Colour transition | Hover | 200 ms | `--motion-easing-standard` | Feedback | P1 |

Reasoning: this is a document view, potentially printed. Any animation would interfere with print or with a user trying to screenshot the reference.

### 8.7. Auth pages (`login.php`, `register.php`, `forgot_password.php`, `reset_password.php`, `change_password.php`)

These are server-rendered form pages, not modals. Recommendations:

| Element | Motion | Priority |
|---|---|---|
| Card entrance | Fade + translateY(16px) on page load | P2 |
| Field focus ring | Border + box-shadow transition, 200 ms | P1 |
| Submit button | Spinner + disabled during POST (server-rendered — spinner shows until browser navigates) | P1 |
| Error alert | Bootstrap `.fade.show` + optional `animate__shakeX` on the card | P1 |

### 8.8. Auth modals (`#loginModal`, `#signupModal` — shared, in [includes/auth_modals.php](../includes/auth_modals.php))

| Element | Motion | Trigger | Duration | Easing | Purpose | Priority |
|---|---|---|---|---|---|---|
| Modal open/close | Bootstrap fade+slide | Click | Bootstrap default | Bootstrap default | Continuity | P0 (keep) |
| First input focus on shown | JS focus on `shown.bs.modal` | Modal shown | — | — | Guidance | **P0 new** (already partially present for one modal — extend to all) |
| Submit button click | Spinner + disabled during fetch | Click | 0 → response | — | Feedback | **P0 new** |
| Error alert show | Bootstrap `.fade.show` + optional `animate__shakeX` on the alert | On error | Bootstrap default + 500 ms shake | — | Feedback | P1 |

### 8.9. Admin pages (dashboard, vehicles, users, vouchers, all transactions)

**Recommendation: no scroll reveal, no card stagger, no decorative motion. Add exactly three things:**

| Element | Motion | Where | Priority |
|---|---|---|---|
| Every AJAX submit button | Spinner + disabled during fetch (extend existing pattern from `admin_users.php`/`admin-dashboard.php` to Vouchers, Vehicles CRUD, and All Transactions) | All admin pages | **P0** |
| DataTables row highlight on hover | `background-color` transition, 100 ms | Existing tables | P1 (Bootstrap default) |
| Sidebar link hover / active | Already handled by existing `#adminSidebar` CSS (§3.1) | — | P0 (keep) |

**Admin dashboard metric cards do not need entrance animation.** They are essential info the admin loads the page to see immediately.

### 8.10. Admin login (`admin-login.php`)

| Element | Motion | Priority |
|---|---|---|
| Login card entrance | Fade + translateY(16px) | P2 |
| Submit button | Spinner + disabled during POST | P1 |

---

## 9. Scroll Motion Strategy

### 9.1. What scroll-triggers, and how

- **Trigger mechanism:** `IntersectionObserver` with `{ threshold: 0.15, rootMargin: "0px 0px -60px 0px" }`. Element gets the `.is-visible` class the first time 15 % of it enters the viewport; the observer then unobserves it (single-run — no re-play on scroll-up).
- **Markup contract:**
  - `data-reveal` on any element that should fade+translate up. CSS applies the entrance keyframe when `.is-visible` is added.
  - `data-reveal-stagger` on a container. Its direct children with `data-reveal` receive `--reveal-delay: calc(var(--motion-stagger) * $index)` inline via the JS by default. **Updated, Pre-Phase-4 remediation:** a container may instead write `data-reveal-stagger="N"` to use `N` ms in place of `--motion-stagger` for its own children only — see §12's addendum. A bare `data-reveal-stagger` still uses the global token exactly as described above.
- **Stagger:** Default 80 ms between siblings, cap at 6 items (so a 9-card grid staggers the first 6 and reveals the last 3 simultaneously with #6). Prevents the "waterfall too long" feel.
- **Base entrance:** `opacity: 0 → 1` + `translateY(16px → 0)` (subtle — content should feel like it's settling, not sliding in from off-screen).
- **Duration:** `--motion-duration-normal` (400 ms).
- **Easing:** `--motion-easing-entrance` (`cubic-bezier(0.16, 1, 0.3, 1)` — quick out, gentle settle).

### 9.2. Where scroll reveal applies

| Section | Element(s) receiving `data-reveal` | Stagger |
|---|---|---|
| Homepage — Features section | Section title + 3 feature cards | 80 ms |
| Homepage — Stats bar | Bar container + 4 stat groups | 80 ms |
| Homepage — Testimonials | Section title + 3 testimonial cards | 100 ms |
| Homepage — CTA banner | Banner card | none |
| Vehicles page — vehicle grid | First 9 cards (paginate size) | 60 ms |
| About page — feature cards (4) | Each card | 80 ms |
| About page — team-member cards (5) | Each card | 80 ms |
| About page — CTA banner | Banner card | none |

### 9.3. Where scroll reveal does NOT apply

- Any content the user came to the page specifically to read (receipt content, transaction history rows once past the first visible batch, admin data tables, FAQ answers).
- Anything above the initial viewport fold (visible on page load) — those use immediate-entrance keyframes, not scroll-triggered.
- The navbar. Ever.

### 9.4. Counters (stats bar)

- Numbers count from 0 → target over 1000 ms `linear`, single-run, when the stats bar enters the viewport.
- `IntersectionObserver` triggers a small `requestAnimationFrame` counter that writes the current integer to the element's text.
- Skip entirely if `prefers-reduced-motion: reduce` (numbers appear final immediately).

### 9.5. Parallax / scroll-linked effects

- **None.** Parallax is a real capability but PMS's design doesn't have a hero photograph or full-bleed image section that would benefit. Adding parallax to the current hero (video/image background at `opacity: 0.35` with a gradient overlay) would create jitter on mobile and confuse the reduced-motion story. Deliberate no-op.

### 9.6. Sticky elements

- **Existing (keep):** navbar (`.navbar.fixed-top`), filter sidebar on desktop (`.filter-sidebar { position: sticky; top: 80px }` — [css/styles.css:494-501](../css/styles.css#L494)). No new sticky elements recommended.

---

## 10. Micro-Interaction Strategy

Behavior spec for each element class. Anything not listed keeps its Bootstrap default.

### 10.1. Buttons

| State | Behavior |
|---|---|
| Default | Existing look |
| Hover | Existing CSS: `.btn-*` gets Bootstrap's built-in colour shift; add `transform: translateY(-1px)` for `.btn-primary`/`.btn-warning`/`.btn-outline-primary` on hover, 150 ms `--motion-easing-standard` |
| Focus-visible | 3 px outline in `--accent`, 2 px offset — replaces Bootstrap's default focus ring for consistency |
| Active (mousedown) | `transform: translateY(0)` + subtle inner shadow, 80 ms |
| Loading | Insert an inline `<span class="spinner-border spinner-border-sm me-2"></span>` before the button label, add `disabled` attribute, add `.is-loading` class. Text stays visible so screen readers can still announce it. |
| Disabled | Existing Bootstrap `disabled` state |

Helper JS (single function, in `js/motion.js`):
```
setButtonLoading($btn, isLoading)
  → adds/removes spinner span, toggles disabled, toggles .is-loading
```

Called from: `#btnPreview`, `#btnConfirm`, `#loginForm` submit, `#signupForm` submit, `#contactForm` submit, filter Apply, admin Add/Edit/Delete submit buttons that don't already have spinners.

### 10.2. Vehicle cards

| State | Behavior |
|---|---|
| Default | Existing (border, radius, shadow) |
| Hover | Existing lift (`translateY(-6px)` + shadow) + image `scale(1.05)` (all already in [css/styles.css:454-480](../css/styles.css#L454)). Keep as-is. |
| Focus-within (keyboard nav into the card's button) | Same visual as hover — apply via `:focus-within` selector |
| Loading (waiting for details modal to populate — instant today, but future-proof) | No-op today |

### 10.3. Forms

| State | Behavior |
|---|---|
| Field default | Existing `.form-control` |
| Field focus | Border-colour transition to `--secondary`, box-shadow `0 0 0 3px rgba(47,111,237,0.15)`, 200 ms |
| Field invalid (`.is-invalid` set by JS after failed submit) | Border-colour transition to Bootstrap danger red, `animate__shakeX` on the field itself once (0.5 s) |
| Field valid (`.is-valid`) | Border-colour to green, no motion |
| Submit success alert | Bootstrap `.fade.show` reveal, auto-hide after 5 s with `.fade` out |
| Submit error alert | Bootstrap `.fade.show` reveal, no auto-hide, `animate__shakeX` once |

### 10.4. Modals

| State | Behavior |
|---|---|
| Opening | Bootstrap default fade+slide-down (keep) |
| Shown | Focus lands on first form input (or on the Close button if no input) — extend existing pattern |
| Closing | Bootstrap default (keep) |
| Content loading (`#vehicleDetailsModal` image swap, `#bookingModal` preview) | Skeleton state on the section that's loading, replace with real content when ready |
| Stacked modal transitions (Details → Auth or Details → Booking) | Already sequenced via `hidden.bs.modal` in current Vehicle Details phase Step 3 — keep as-is |

### 10.5. Navigation

| State | Behavior |
|---|---|
| Nav link default → hover | Existing colour+background transition (`.nav-link` 0.2 s, keep) |
| Nav link active | Existing pill background (keep) |
| Mobile hamburger toggle | Bootstrap `.collapse` (keep) |
| Auth dropdown open | Bootstrap default (keep) |
| Navbar scroll behavior | **Do not add auto-hide or shrink-on-scroll.** Fixed-top with a subtle `box-shadow` on scroll past 40 px is acceptable P2 polish; skip if it costs a scroll listener. |

### 10.6. Filters / search

| State | Behavior |
|---|---|
| Checkbox toggle | Bootstrap default |
| Price input focus | Same field-focus behavior as §10.3 |
| Apply button click | `setButtonLoading` spinner, form submits, page reloads |
| Clear filters | Same as Apply |
| Empty state on zero results (`#noResultsMessage`, server-rendered) | Fade + translateY(16px→0) on page load, 400 ms |

### 10.7. Booking (`#bookingModal`)

| State | Behavior |
|---|---|
| Modal shown | Focus lands on `#rental_date` |
| `#btnPreview` click | `setButtonLoading(#btnPreview, true)`, disable form inputs. On success: `setButtonLoading(false)`, reveal `#bookingPreview` with fade+max-height expand, reveal `#amountPaidSection`, enable `#btnConfirm`. On error: `setButtonLoading(false)`, show alert with `.fade.show`, no shake (not a validation error). |
| `#btnConfirm` click | `setButtonLoading(#btnConfirm, true)`, disable Preview. On success: redirect to `receipt.php` (no in-modal transition; browser navigation). On error: `setButtonLoading(false)`, alert with `.fade.show`. |

### 10.8. Focus rings (site-wide)

Single new rule, applied via `:focus-visible` so mouse-users don't see the ring but keyboard-users do:

```
:focus-visible {
  outline: 3px solid var(--accent);
  outline-offset: 2px;
  transition: outline-color 150ms var(--motion-easing-standard);
}
```

Applies to all buttons, links, form controls, tabs, accordion buttons, etc.

---

## 11. Motion Hierarchy

Not all motion is equal. Three tiers so priority is unambiguous when time is short.

### 11.1. Level 1 — Essential Motion (never skipped, always renders)

Purpose: **Feedback** and **Orientation**. If these fail, the site feels broken.

- Loading spinners on Preview / Confirm / Contact submit / all AJAX admin buttons.
- Modal open/close (Bootstrap default).
- Focus-visible outline on all interactive elements.
- Focus-lands-on-first-input when a modal is shown.
- Form validation error alert reveal (with optional shake).
- Availability badge / status badge colour + text (no motion, but essential feedback).
- Navbar mobile collapse.
- Accordion open/close.

### 11.2. Level 2 — Supporting Motion (respects reduced-motion, otherwise runs)

Purpose: **Continuity** and **Hierarchy**. Improves the experience but the site is fully functional without it.

- Scroll-reveal on homepage sections (Features, Stats, Testimonials, CTA).
- Scroll-reveal on About page sections.
- Vehicle grid stagger on `vehicles.php`.
- Card hover lifts (feature / vehicle / testimonial / team-member).
- Vehicle image scale-on-hover.
- Button hover lift (1 px).
- Field focus ring transition.
- Modal image fade-in.
- Alert `.fade.show` for success/error messages.

### 11.3. Level 3 — Decorative Motion (skip freely)

Purpose: **Delight** only. First to be cut for reduced-motion, first to be cut for performance concerns.

- Hero Animate.css entrance (keep — it's already there and it works).
- Stats bar count-up numbers.
- Testimonial hover lift (already exists, keep).
- Any future SVG or illustration animation.

**Rule:** Level 1 always wins. If a Level 3 animation delays or blocks a Level 1 interaction, cut the Level 3.

---

## 12. PMS Motion Tokens

Add to `:root` in `css/styles.css`, alongside existing design tokens.

```css
:root {
  /* ... existing colour tokens ... */

  /* Motion — Duration */
  --motion-duration-fast:    150ms;   /* micro: hover colour, focus ring */
  --motion-duration-normal:  300ms;   /* standard: card lift, modal fade */
  --motion-duration-slow:    500ms;   /* entrance / reveal */

  /* Motion — Delay */
  --motion-delay-none:    0ms;
  --motion-delay-short:   80ms;       /* stagger between siblings */
  --motion-delay-medium:  160ms;
  --motion-delay-long:    300ms;      /* between hero heading and subhead */

  /* Motion — Easing */
  --motion-easing-standard:   cubic-bezier(0.2, 0, 0.2, 1);      /* default */
  --motion-easing-entrance:   cubic-bezier(0.16, 1, 0.3, 1);     /* content entering */
  --motion-easing-exit:       cubic-bezier(0.4, 0, 1, 1);        /* content leaving */
  --motion-easing-emphasized: cubic-bezier(0.2, 0, 0, 1);        /* attention-getting */

  /* Motion — Distance (translate for entrance) */
  --motion-distance-sm:   8px;
  --motion-distance-md:   16px;
  --motion-distance-lg:   24px;

  /* Motion — Scale (subtle only — no scale > 1.05) */
  --motion-scale-subtle:   1.02;
  --motion-scale-standard: 1.05;

  /* Motion — Stagger */
  --motion-stagger:        80ms;
}
```

**Pre-Phase-4 remediation addendum — `--accent-focus` (color token, not a motion-timing token):**

```css
:root {
  /* ... existing colour tokens, alongside --primary/--secondary/--accent ... */
  --accent-focus: #1A7FFF;
}
```

Added to `css/styles.css`'s `:root` in the brand-token block (not the motion-token block below it) — it's a color, not a timing value. Documented here in §12 anyway because its only consumer is the `:focus-visible` rule in §10.8, which is squarely motion/interaction-state territory. **Rationale:** `--accent` (`#63A8FF`) measures ≈2.3:1 against `--background` (`#F8FAFC`) — below WCAG 1.4.11's 3:1 minimum for non-text UI-component contrast — yet it had been in production as the site-wide focus-ring color since Phase 1. `--accent-focus` is a darker step of the same blue, used nowhere else in the site (so it doesn't touch any existing color decision beyond the focus ring itself). Verified live, computed contrast against three real rendered contexts on `index.php`: default page background 3.63:1, white form-control background 3.80:1, navy (`--primary`, shared by the active-navlink pill and the site footer) 3.79:1, and the CTA banner's `.btn-light` button background 3.61:1 — all clear the 3:1 minimum.

**Per-container stagger override — `js/motion.js` `initReveal()`:**

`data-reveal-stagger="N"` now overrides `--motion-stagger` for that container's children only (e.g. `data-reveal-stagger="60"` → 60ms between siblings). A bare `data-reveal-stagger` (no value) still falls back to the global `--motion-stagger` token (80ms) exactly as before — existing bare usage is unaffected. `index.php`'s three containers now carry their originally-documented §9.2 values explicitly: Features `="80"`, Stats bar `="80"`, Testimonials `="100"` (previously all three silently received the same 80ms default with no way to differ — see the Pre-Phase-4 audit's finding #1). This is what Phase 4 will use for the vehicle grid's `data-reveal-stagger="60"`.

**Rules:**
- Never use hardcoded duration/easing/distance values in new CSS. Reference the tokens.
- Existing hardcoded values in [css/styles.css](../css/styles.css) (§3.1) stay in place for this phase — they happen to fall within the token ranges, so the visual won't change. A follow-up refactor can token-ify them safely.
- No new scale value above `1.05` anywhere. Bigger scales create layout thrash on hover.

---

## 13. Accessibility Strategy

### 13.1. `prefers-reduced-motion: reduce`

Global rule to add in `css/styles.css`:

```css
@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
    scroll-behavior: auto !important;
  }
}
```

**Nuance:** the blanket rule above is deliberately aggressive. It preserves the *final state* of every animation (opacity: 1, transform: 0) but removes the transition. Level 1 essential feedback (spinners, focus rings, badges) is not affected because it's either a) not an animation (colour changes are fine even with 0.01 ms transition), or b) an animation that must run — the spinner keeps rotating because the `@media` block does not `animation-iteration-count: 0` on the spinner class specifically.

If we want the spinner to still animate under reduced-motion, override:

```css
@media (prefers-reduced-motion: reduce) {
  .spinner-border,
  .spinner-grow {
    animation-duration: 0.75s !important;  /* Bootstrap default */
  }
}
```

**Decision recorded (Pre-Phase-4 remediation) — card hover-lift transforms are gated under reduced-motion; hover shadow/color feedback is not.** This was previously left as "arguable" in [MOTION_DESIGN_IMPLEMENTATION_PLAN.md](MOTION_DESIGN_IMPLEMENTATION_PLAN.md) Phase 10's testing checklist rather than decided. Resolved as: the `translateY()` lift on `.feature-card:hover`, `.vehicle-card:hover`, and `.testimonial-card:hover` is a positional shift with the same vestibular-motion profile as any other transform-based animation in this document (§13.4), so it's gated inside `@media (prefers-reduced-motion: no-preference)` like everything else here that isn't essential feedback. The accompanying `box-shadow` change on the same hover states is *not* gated — a shadow has no vestibular-motion component and is the mechanism that keeps hover interaction feedback present (skill guidance: hover states should remain perceivable even when decorative motion is stripped) for reduced-motion users. `.vehicle-card:hover .vehicle-img-wrap img { transform: scale(1.05) }` (the image zoom, a different selector from the three card-level hover-lifts above) was intentionally left out of this pass — flagged, not resolved, in [css/styles.css](../css/styles.css) directly.

### 13.2. Keyboard-only support

- `:focus-visible` outlines on all interactive elements (§10.8).
- Modal focus trap (Bootstrap default, currently in place).
- Focus lands on first input when modal shows (§10.4).
- No `tabindex="-1"` should be added to any element that a keyboard user needs to reach. Scroll-reveal must **never** move focus.

### 13.3. Screen reader considerations

- Alerts use `role="alert"` (Bootstrap `.alert` provides this via ARIA). Motion doesn't affect screen-reader announcement.
- Loading state on buttons: keep the button text visible (`Preview` doesn't disappear), just insert a spinner in front. Optional `aria-busy="true"` when loading.
- Counters (stats bar) update `textContent` — screen readers may announce the intermediate values. To avoid this, wrap the animated number in `aria-hidden="true"` and put the final target value in visually-hidden sibling text.

### 13.4. Vestibular / motion-sickness considerations

- No parallax (§9.5).
- No large translate distances (max 24 px).
- No scale > 1.05.
- No infinite animation loops (except the spinner, which is expected).
- Autoplay video already gates on `prefers-reduced-motion` (existing, keep).

### 13.5. Colour contrast unaffected

Motion doesn't change any colour-contrast decision. Focus ring uses `--accent-focus` (`#1A7FFF`), not `--accent` — swapped and live-verified in the Pre-Phase-4 remediation (see §12's addendum and CHANGELOG). Measured contrast: 3.63:1 against the default `--background`, 3.80:1 against white form-control backgrounds, 3.79:1 against `--primary`, 3.61:1 against the CTA banner's `.btn-light` background — all clear WCAG 1.4.11's 3:1 minimum for non-text UI-component contrast. `--accent` itself (`#63A8FF`, ≈2.3:1 against `--background`) is unchanged everywhere else it's used and was never contrast-adequate for a focus ring, which is why the swap happened instead of re-verifying the old value.

---

## 14. Performance Strategy

### 14.1. Principles

1. **Only animate `transform` and `opacity`.** Never `width`, `height`, `top`, `left`, `margin`. Verified: current codebase already follows this (§3.1).
2. **Composite the animated layer.** Use `will-change: transform, opacity;` sparingly on elements about to animate (add via JS just before, remove on completion — a permanent `will-change` on every card is a memory-cost trap).
3. **`IntersectionObserver` instead of scroll listeners.** Existing `.feature-card` scroll listener in [app.js:995-1001](../js/app.js#L995) is a jQuery scroll handler that fires on every scroll event — expensive. The replacement uses `IntersectionObserver` (native, batched, off the main thread for observation).
4. **Debounce nothing that doesn't need it.** Scroll-reveal via IO is already batched — no debouncing required.
5. **Single-run animations.** Scroll-reveal unobserves after first fire. Counters run once. No infinite decorative loops.

### 14.2. Bundle-cost budget

Motion phase adds:
- CSS: ~1.5 KB min+gz (motion tokens + keyframes + focus-visible + reduced-motion + spinner styles + reveal classes).
- JS: `js/motion.js` ~2 KB min+gz (IntersectionObserver + `setButtonLoading` + reduced-motion detection).

**Total added:** ~3.5 KB min+gz. No new external dependency.

### 14.3. Mobile performance

- Card hover effects don't fire on touch (there's no true hover state) — no penalty.
- Scroll-reveal on mobile stops observing after fire — no ongoing cost.
- Filter offcanvas on mobile uses Bootstrap's own transition — already optimised.
- Video autoplay on the hero is *already disabled* on `prefers-reduced-motion` and can additionally be gated on connection type in a future phase (`navigator.connection.effectiveType`).

### 14.4. Layout thrash risks

- Card hover with `transform: translateY(-6px)` + `box-shadow` change — verified GPU-compositable, no layout thrash.
- Modal open animates opacity + transform — no thrash.
- Reveal keyframe animates opacity + transform — no thrash.

**Risk area:** if a future phase adds `max-height` animation to expand `#bookingPreview`, that *is* a layout property. Prefer `transform: scaleY()` + fixed `transform-origin: top` or pre-set `max-height: 500px` + animate `opacity` only, if this becomes measurable.

---

## 15. Motion Priority Matrix

Consolidated table for the implementation team. Sorted by priority (P0 → P3), then by page.

| Page | Element | Motion | Priority | Complexity | UX Benefit |
|---|---|---|---|---|---|
| Global | All buttons on AJAX submit | Spinner + disabled state via `setButtonLoading` | P0 | Low | High — fixes double-submit, missing feedback |
| Global | `:focus-visible` outlines | 3 px outline, `--accent` | P0 | Low | High — keyboard accessibility |
| Global | `prefers-reduced-motion` global rule | Neutralise all motion | P0 | Low | High — accessibility |
| Global | Modal `shown.bs.modal` → focus first input | JS one-liner per modal | P0 | Low | High — accessibility + UX |
| Vehicles | ~~`#btnPreview` spinner~~ **SUPERSEDED (System Enhancements initiative, Step 2, 2026-08-21)** — `#btnPreview` removed; the spinner now shows on `#btnConfirm` instead (row below), triggered by `window.refreshBookingPreview()` | `setButtonLoading` | P0 | Low | **Highest — core booking flow** |
| Vehicles | `#btnConfirm` spinner | `setButtonLoading` | P0 | Low | **Highest** |
| Vehicles | Vehicle grid stagger reveal | IO + CSS keyframe | P0 | Medium | Medium |
| About | Contact form submit spinner | `setButtonLoading` | P0 | Low | High |
| Transactions | Cancel/Return confirm spinner | `setButtonLoading` | P0 | Low | High |
| Auth modals | Login/Signup submit spinner | `setButtonLoading` | P0 | Low | High |
| Admin | Add/Edit/Delete AJAX button spinner (extend existing pattern) | `setButtonLoading` | P0 | Low | Medium |
| Homepage | Feature-card scroll reveal (replace broken scroll handler) | IO + CSS keyframe | P1 | Medium | Medium |
| Homepage | Testimonial-card scroll reveal | IO + CSS keyframe | P1 | Low | Medium |
| Homepage | CTA-banner scroll reveal | IO + CSS keyframe | P1 | Low | Medium |
| Homepage | Search widget entrance | CSS keyframe, 300 ms delay after hero | P1 | Low | Medium |
| Homepage | Stats counter count-up | JS + IO | P2 | Medium | Low-Medium |
| Vehicles | `#bookingPreview` reveal fade+expand | CSS keyframe | P1 | Low | Medium |
| Vehicles | Filter Apply spinner | `setButtonLoading` | P0 | Low | Medium |
| Vehicles | Pagination click spinner | `setButtonLoading` | P1 | Low | Low |
| Vehicles | `#vehicleDetailsModal` image fade | CSS transition on img load | P2 | Low | Low |
| About | Feature/team-card scroll reveal | IO + CSS keyframe | P1 | Low | Medium |
| About | Contact field focus ring | CSS transition | P1 | Low | Medium |
| About | Team-card hover lift (new — consistency) | CSS transition | P2 | Low | Low |
| Transactions | List rows fade-in on page load | CSS keyframe | P2 | Low | Low |
| FAQ | Page-title fade in | CSS keyframe | P2 | Low | Low |
| Homepage | Feature-card scale on hover | Remove (drop from `translateY(-7px) scale(1.05)` to `translateY(-6px)`) | P2 | Low | Consistency |
| Global | Navbar shrink/hide on scroll | (do not add) | P3 | — | Negative |
| Global | Parallax hero background | (do not add) | P3 | — | Negative |
| Admin | Any decorative scroll reveal | (do not add) | P3 | — | Negative |
| Any | Page-transition / route-transition | (do not add — requires SPA refactor) | P3 | — | Out of scope |

---

## 16. Elements That Should Remain Static

Explicit anti-list. Nothing below should animate beyond a) Bootstrap's built-in transitions where already applied, or b) essential feedback (spinner, focus ring).

### 16.1. Content the user is trying to read

- **`receipt.php`** — printable, screenshot-able document. Static. Only nav button hovers may animate.
- **Transaction list rows past the first 10** on `transactions.php` — the first batch may fade in on page load; anything requiring scroll to see should already be settled by the time it's visible (no scroll-reveal).
- **FAQ answer bodies** once expanded — Bootstrap collapse animates the open/close, not the text.
- **Booking preview summary** inside `#bookingModal` after reveal — the reveal animates, the numbers/text don't move.

### 16.2. Critical form elements

- Age/date/contact-number/license upload inputs during booking — no shake, no pulse, no bounce. Only the focus ring transitions.
- Voucher select — no motion.
- Payment amount input — no motion.

### 16.3. Financial / legal information

- Prices on vehicle cards.
- Total, subtotal, discount, change on receipt.
- Voucher discount amount.
- Any admin numeric column in DataTables.

### 16.4. Dense data tables

- Every admin DataTables table.
- Recent Transactions on `admin-dashboard.php`.
- Recent Messages on `admin-dashboard.php`.
- Vehicles / Users / Vouchers / All-Transactions tables.

Row hover uses Bootstrap's own subtle background transition — that stays. Anything else (row entrance, sort animation, filter animation) is P3 avoid.

### 16.5. Admin interfaces overall

- Admin sidebar (existing colour transition stays; no other motion).
- Admin metric cards (no entrance animation — admin loads the page to see these immediately).
- Admin CRUD modals (Bootstrap defaults only).

### 16.6. The navbar

- No auto-hide.
- No shrink-on-scroll.
- No colour shift on scroll (an optional 1-px shadow on scroll >40 px is P2 polish; skip if it adds a scroll listener).

---

## 17. Recommended Implementation Order

Full phase-by-phase sequencing lives in [MOTION_DESIGN_IMPLEMENTATION_PLAN.md](MOTION_DESIGN_IMPLEMENTATION_PLAN.md). One-paragraph summary here:

1. **Foundation** — motion tokens, `prefers-reduced-motion` global rule, `js/motion.js` skeleton (IntersectionObserver + `setButtonLoading`), focus-visible.
2. **Global micro-interactions** — spinners on all AJAX submits (booking, contact, auth, admin), focus-on-first-input on modal shown.
3. **Homepage motion** — search widget entrance, feature/testimonial/CTA scroll reveal (replaces broken scroll handler), optional stats count-up.
4. **Vehicle listing motion** — vehicle-grid stagger reveal, filter Apply spinner, pagination spinner.
5. **Vehicle details / booking motion** — `#bookingPreview` reveal, image fade in details modal.
6. **About motion** — section scroll reveal, contact field focus, team-card hover.
7. **Contact / booking finalisation** — already covered in phase 2 + 5.
8. **Authentication motion** — modal focus-on-input, submit spinners (covered in phase 2), optional error shake.
9. **Admin motion** — extend spinner pattern to Vouchers, Vehicles CRUD, All Transactions (kept minimal per §16.5).
10. **Accessibility / reduced-motion QA** — verify `prefers-reduced-motion` neutralises all decorative motion without breaking essential feedback.
11. **Performance optimisation** — profile IntersectionObserver load, verify no layout thrash, verify bundle stays under +5 KB.
12. **Final motion QA** — cross-browser + cross-device sweep.

Foundation blocks every other phase. Global micro-interactions unblocks Booking phase (which reuses the spinner helper). Everything else can proceed in parallel.

---

## 18. Risks

| # | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| 1 | Existing `.feature-card` scroll listener in `app.js` conflicts with new IntersectionObserver | Medium | Low | Do not remove the old handler this phase (per audit prompt STEP 4). The new IO adds a *different* class (`.is-visible`); the old handler adds `animate__fadeInUp`. They can co-exist; the visible result is dominated by whichever renders first, and both produce a fade-up. Verify no double-animation by scoping the new keyframe to `[data-reveal].is-visible` only. |
| 2 | Animate.css keyframes on hero conflict with new global reduced-motion rule | Low | Low | The blanket `@media (prefers-reduced-motion: reduce)` `animation-duration: 0.01ms` rule catches Animate.css too — hero snaps to final state, which is the correct behavior. Verified against Animate.css documentation. |
| 3 | Modal-inside-modal transition timing (`#vehicleDetailsModal` → `#loginModal`) breaks under reduced-motion | Low | Medium | Existing sequencing uses `hidden.bs.modal` event, not a hardcoded delay. Reduced-motion makes the transitions instant but the event still fires. Verified logically; test in reduced-motion mode. |
| 4 | Spinner insertion into buttons breaks their text alignment | Low | Low | `spinner-border spinner-border-sm` + `me-2` on the spinner span is Bootstrap-native and tested. Focus is on preserving `.btn` `display: inline-flex` behavior. |
| 5 | Focus ring outline conflicts with Bootstrap's default `.form-control:focus` box-shadow | Low | Low | Scope `:focus-visible` outline to override, and disable Bootstrap's default focus-ring only on `[type="text"]`, `select`, etc. via `:focus-visible { outline: … } :focus:not(:focus-visible) { outline: none }`. |
| 6 | IntersectionObserver misfires on elements inside `display: none` filter offcanvas on mobile | Low | Low | Offcanvas contents aren't `display: none` before shown — Bootstrap uses `visibility` + `transform`. Elements inside still get observed; when they enter viewport post-open they animate. Acceptable. Alternative: skip `data-reveal` on offcanvas contents entirely. |
| 7 | Stagger delay + reveal duration exceeds 800 ms total on 9-card grid, feels slow | Medium | Medium | Cap stagger at 6 cards (§9.1). 6 × 80 ms + 400 ms = 880 ms worst case, which is at the edge — reduce to 60 ms stagger for vehicles grid specifically (matches spec in §8.2). |
| 8 | Users on slow connections see the pre-CSS flash of unstyled content on hero | Existing | Low | Pre-motion problem; motion phase doesn't introduce it. Address in a separate performance phase (inline critical CSS). |
| 9 | Existing dead motion code (§3.8) confuses future maintainers | Low | Low | Not removed this phase per audit prompt STEP 4. Document them in this file (§3.8) as reference. |
| 10 | Admin power-users find spinner+disabled-state on bulk actions slower than the current "click and it just works" pattern | Low | Medium | Spinner appears only during the actual AJAX request (typically < 300 ms). Any perceived slowdown is under 100 ms; the tradeoff is preventing double-submits, which is worth it. Verify with admin user. |

---

## 19. Acceptance Criteria

The motion phase is complete when all of the following hold. Each is verifiable in the browser DevTools or manually.

### 19.1. Foundation

- [ ] `--motion-duration-*`, `--motion-delay-*`, `--motion-easing-*`, `--motion-distance-*`, `--motion-scale-*`, `--motion-stagger` tokens defined in `:root` in `css/styles.css`.
- [ ] `@media (prefers-reduced-motion: reduce)` global rule present.
- [ ] `js/motion.js` exists, exports (via `window.PMSMotion`) at minimum: `initReveal()`, `setButtonLoading($btn, isLoading)`, `initModalFocus()`.
- [ ] `js/motion.js` loaded on every customer-facing page (via the shared navbar include or per-page).
- [ ] `:focus-visible` global rule applies 3 px accent outline to all interactive elements.

### 19.2. Global micro-interactions

- [ ] ~~`#btnPreview` on `vehicles.php` shows spinner + is disabled during the `reserve_preview.php` fetch.~~ **SUPERSEDED (System Enhancements initiative, Step 2, 2026-08-21):** `#btnPreview` was removed entirely — the preview fetch is now triggered automatically by date/voucher changes via `window.refreshBookingPreview()` (`js/app.js`), with no button of its own. Loading spinner + disable now shows on `#btnConfirm` instead (see item below), which is the only button left in this flow. See [SYSTEM_ENHANCEMENTS_IMPLEMENTATION_PLAN.md](SYSTEM_ENHANCEMENTS_IMPLEMENTATION_PLAN.md) Step 2.
- [ ] `#btnConfirm` on `vehicles.php` shows spinner + is disabled during the `reserve.php` fetch.
- [ ] `#contactForm` submit on `about.php` shows spinner + is disabled during save.
- [ ] `#loginForm` and `#signupForm` submit buttons show spinner + are disabled during their fetch.
- [ ] `#loginModal`, `#signupModal`, `#bookingModal`, `#vehicleDetailsModal` all focus their first form input (or Close button if none) on `shown.bs.modal`.
- [ ] Every admin AJAX submit button (Vouchers Add/Edit/Delete, Vehicles Add/Edit is page-POST-only so skipped, All-Transactions Edit/Delete/Confirm) shows spinner + is disabled during request.

### 19.3. Scroll reveal

- [ ] IntersectionObserver initialised in `js/motion.js` observes all `[data-reveal]` elements.
- [ ] `.is-visible` class added exactly once per element, then element is unobserved.
- [ ] Homepage Features section (title + 3 cards), Stats bar, Testimonials (title + 3 cards), CTA banner all reveal on scroll.
- [ ] Vehicles page vehicle grid reveals with 60 ms stagger, cap 6.
- [ ] About page feature cards (4), team cards (5), CTA banner all reveal on scroll.
- [ ] Reveal keyframe animates `opacity: 0 → 1` and `translateY(16px → 0)`, duration `--motion-duration-normal`, easing `--motion-easing-entrance`.

### 19.4. Homepage

- [ ] Hero H1 and P still use Animate.css `fadeInDown` / `fadeInUp` on page load (unchanged).
- [ ] Search widget card fades + translates up at 300 ms delay.
- [ ] Feature cards no longer rely on the broken jQuery scroll handler (handler still exists but is superseded by IO; no double-animation visible).

### 19.5. Vehicle listing

- [ ] Vehicle card hover behavior unchanged (existing lift + image scale).
- [ ] Filter Apply button spins + disables on submit.
- [ ] Pagination link spins on click.
- [ ] `#vehicleDetailsModal` image fades in on load.

### 19.6. Booking (highest priority)

- [ ] `#bookingPreview` block reveals with fade + expand once a preview succeeds. **UPDATED (System Enhancements initiative, Step 2, 2026-08-21):** the trigger is now `window.refreshBookingPreview()` succeeding, not a `#btnPreview` click — `#btnPreview` no longer exists. The reveal mechanism itself (`.js-booking-reveal` + `.addClass('is-visible')`) is unchanged.
- [ ] ~~`#amountPaidSection` reveals in the same motion as `#bookingPreview`.~~ **SUPERSEDED (Step 2, 2026-08-21):** `#amountPaidSection` is now permanently present in the DOM rather than conditionally revealed (it no longer carries `.js-booking-reveal` or `d-none`) — there is nothing left to reveal. `#btnConfirm`'s disabled state is what now communicates "not ready yet," per the item below.
- [ ] `#btnConfirm` is disabled until `#bookingPreview` is populated. **UPDATED (Step 2, 2026-08-21):** this is now literal — `#btnConfirm` ships with the `disabled` HTML attribute and is enabled by `window.refreshBookingPreview()`'s success path (previously it was hidden via `d-none` rather than disabled).
- [ ] Booking alert (`#bookingAlert`) uses Bootstrap `.fade.show` to appear/disappear.

### 19.7. About

- [ ] Contact form fields have transitioning focus ring.
- [ ] Contact alert uses Bootstrap `.fade.show`.
- [ ] Team cards lift on hover (200 ms).

### 19.8. Transactions

- [ ] Cancel confirm / Return-early confirm buttons spin + disable during AJAX.
- [ ] Return receipt modal opens smoothly (Bootstrap default, no change).

### 19.9. Accessibility

- [ ] With OS-level "Reduce motion" enabled: no scroll-reveal transition visible; final states are correct; spinners still rotate (essential feedback exception); focus rings still visible.
- [ ] Keyboard-only navigation reaches every interactive element with a visible focus ring.
- [ ] Modal focus lands on first input on open; focus trap keeps Tab inside modal (Bootstrap default).
- [ ] Screen reader announces button label + "busy" (via `aria-busy`) during loading state.

### 19.10. Performance

- [ ] No new external animation library added.
- [ ] Bundle size increase < 5 KB min+gz.
- [ ] No new persistent scroll listener added (IntersectionObserver only).
- [ ] All keyframes and transitions animate only `transform` / `opacity` / `box-shadow` / `background-color` / `color` / `border-color`.

### 19.11. Do-not-break criteria

- [ ] All existing Bootstrap modal / carousel / accordion / offcanvas transitions still fire and are unchanged.
- [ ] Existing card hover lifts (§3.1) unchanged.
- [ ] Existing hero video → image fallback under reduced-motion still works.
- [ ] Existing `.feature-card` scroll listener in [app.js](../js/app.js#L995) still exists (may be a no-op post-phase, but is not deleted).
- [ ] Existing dead motion code (§3.8) still exists in the same location; no cleanup performed in this phase.

---

## 20. Final Recommendations

### 20.1. What to do

1. **Add motion tokens and a global reduced-motion rule** before writing any animation. This makes every subsequent step trivially tokenisable and accessible.
2. **Ship button loading states first.** They deliver the biggest UX improvement per line of code on the whole site — booking Preview/Confirm alone eliminate the site's worst existing UX problem (silent AJAX). Everything else is polish on top.
3. **Replace the broken scroll handler with `IntersectionObserver`** in the same file (`js/motion.js`). Don't delete the old handler, but supersede its effect with a working one.
4. **Use CSS + IO. No new library.** The scope of motion the site needs is entirely covered by pure CSS transitions, a small keyframes vocabulary, and one JS file.
5. **Formalise the hover-lift pattern.** Card lifts already exist and work — just consolidate `.feature-card` to match `.vehicle-card`/`.testimonial-card` (translateY only, no scale) so the design language is consistent.
6. **Honour `prefers-reduced-motion` globally.** One `@media` rule flips the whole site to a static presentation while keeping essential feedback.
7. **Do nothing on the admin side beyond spinners.** Admin motion is not the ROI.

### 20.2. What NOT to do

1. Do not add GSAP or Anime.js. The site does not need them.
2. Do not add auto-hide navbar, shrink-on-scroll navbar, or any scroll-linked navbar effect.
3. Do not add parallax to the hero.
4. Do not animate financial numbers, receipt content, or admin table cells.
5. Do not remove any existing motion code as part of this phase (per audit prompt STEP 4 and STEP 7 spirit).
6. Do not add page-transitions or route-transitions — that requires an SPA refactor out of scope for the current UI improvement track.
7. Do not use `!important` outside the `prefers-reduced-motion` block.
8. Do not exceed `translateY(24px)` on any reveal, or `scale(1.05)` on any hover.
9. Do not stagger more than 6 siblings.
10. Do not add motion to convey information that isn't already conveyed by text + colour (badges, alerts, statuses — motion complements, never replaces).

### 20.3. The single-sentence recommendation

**Give every interactive element real feedback (spinners on AJAX, focus rings on keyboard, hover on cards), reveal marketing content on scroll with a subtle 16-24 px fade-up, and animate nothing that a user is trying to read.**

---

*This document is analysis and design planning only. Implementation is defined separately in [MOTION_DESIGN_IMPLEMENTATION_PLAN.md](MOTION_DESIGN_IMPLEMENTATION_PLAN.md) and requires per-phase approval before any code changes, per [CLAUDE.md](../CLAUDE.md) working rules.*
