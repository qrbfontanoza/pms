# Homepage Implementation Plan — Phase 3

Detailed implementation plan for the Homepage Modernization, derived from [HOME_PAGE_ANALYSIS.md](HOME_PAGE_ANALYSIS.md). Each section includes objective, files affected, dependencies, components used, design requirements, Bootstrap requirements, testing requirements, and acceptance criteria.

**Status:** Plan only. No code has been modified. Implementation requires approval per CLAUDE.md working rules.

**Pre-implementation checklist (applies to every step):**
- Read CLAUDE.md
- Read DESIGN_SYSTEM.md (docs/DESIGN_SYSTEM.md)
- Read HOME_PAGE_ANALYSIS.md
- Inspect current index.php
- Identify any changes to shared components since this plan was written

---

## Step 1: How It Works (Reframe Feature Cards)

### Objective
Reframe the existing 3-column glassmorphism feature cards from quality descriptors ("Wide Selection", "24/7 Access", "Trusted & Safe") to a process-oriented "How It Works" section with step numbering, matching the reference's "Better Way to Rent Your Perfect Cars" pattern.

### Files Affected
| File | Action |
|---|---|
| `index.php` | Modify — lines 42–69 (features section). Change section heading, card icons, card titles, card descriptions. Add step number badges. Add eyebrow label. |
| `css/styles.css` | Modify — add step-number badge CSS (small addition, ~10–15 lines). |

### Dependencies
- None. This section is self-contained.
- Existing CSS: `.glassmorph`, `.feature-card`, Animate.css classes.

### Components Used
| Component | Source | Status |
|---|---|---|
| `.glassmorph` card | `css/styles.css:328–335` | Existing — reuse |
| `.feature-card` hover | `css/styles.css:348–355` | Existing — reuse |
| Font Awesome icons | CDN | Existing — change icon classes only |
| Animate.css entrance | CDN | Existing — keep `animate__fadeInLeft/Up/Right` |
| Bootstrap grid | `col-md-4` | Existing — no change |
| Step number badge | New | **Create** — small circular badge with step number |

### Design Requirements
- Eyebrow label above section heading: "HOW IT WORKS" — uppercase, small font, `--secondary` color, `letter-spacing: 2px`
- Section heading: "Better Way to Rent Your Perfect Car" or similar
- 3 cards, each with:
  - Step number badge (1, 2, 3) — circular, `--secondary` background, white text, positioned top-center or top-left of card
  - Font Awesome icon (suggestion: `fa-car` → Step 1, `fa-calendar-check` → Step 2, `fa-key` → Step 3)
  - Title: "Choose Your Vehicle" / "Select Your Dates" / "Reserve & Go"
  - Short description paragraph
- Maintain glassmorphism card styling
- Maintain hover animation (`translateY(-7px) scale(1.05)`)

### Bootstrap Requirements
- `container`, `row`, `col-md-4` — no change from current layout
- `text-center`, `fw-bold`, `text-muted` — standard utilities
- `rounded-circle` for step badge
- `py-5` section spacing

### Testing Requirements
- [ ] Visual: 3 cards render correctly with step numbers at desktop (1200px+)
- [ ] Visual: cards stack properly on mobile (< 768px)
- [ ] Visual: glassmorphism effect visible on cards
- [ ] Visual: hover animation works on each card
- [ ] Visual: eyebrow label renders in `--secondary` color
- [ ] Responsive: test at 320px, 375px, 768px, 992px, 1200px
- [ ] Accessibility: heading hierarchy is correct (`<h2>` section heading, `<h5>` card titles)
- [ ] Console: no new JS errors
- [ ] Regression: navbar, footer, auth modals unaffected

### Acceptance Criteria
- [ ] Section displays eyebrow label + heading + 3 step cards
- [ ] Each card has a numbered step badge, icon, title, and description
- [ ] Glassmorphism and hover effects preserved
- [ ] Responsive at all breakpoints
- [ ] No console errors
- [ ] No changes to shared partials

---

## Step 2: Featured Vehicles (DB-Driven Grid)

### Objective
Replace the hardcoded 3-item carousel and its 3 Quick View modals with a DB-driven 3-card responsive grid using the existing `.vehicle-card` pattern from `vehicles.php`. This removes ~115 lines of static/mismatched modal markup and introduces the first PHP database query on the homepage.

### Files Affected
| File | Action |
|---|---|
| `index.php` | Modify — replace lines 71–197 (carousel section + 3 Quick View modals) with a PHP-driven featured vehicles grid. Add a `require` for DB connection. Add PHP query. |
| `css/styles.css` | No changes — `.vehicle-card`, `.vehicle-img-wrap`, `.vehicle-specs`, `.vehicle-pricebar` already exist. |

### Dependencies
- Database connection (`includes/db.php` or equivalent — inspect current DB include pattern used by `vehicles.php`).
- `vehicles` table must have active vehicles.
- `.vehicle-card` CSS must be intact (verified in `css/styles.css:393–454`).

### Components Used
| Component | Source | Status |
|---|---|---|
| `.vehicle-card` | `css/styles.css:393–398` | Existing — reuse |
| `.vehicle-img-wrap` + gradient overlay | `css/styles.css:405–426` | Existing — reuse |
| `.vehicle-specs` grid | `css/styles.css:428–439` | Existing — reuse |
| `.vehicle-pricebar` | `css/styles.css:441–454` | Existing — reuse |
| Bootstrap grid | `row`, `col-md-6 col-lg-4` | Existing utility |
| Section eyebrow label | Step 1 CSS (if implemented first) | Reuse from Step 1 |

### Design Requirements
- Eyebrow label: "FEATURED VEHICLES"
- Section heading: "Our Top Picks" or "Featured Vehicles"
- 3 vehicle cards in a responsive grid (`col-md-6 col-lg-4`)
- Each card mirrors the `vehicles.php` card pattern:
  - Vehicle image in `.vehicle-img-wrap`
  - Title, category badge
  - 3-icon spec row (seats, fuel, transmission) in `.vehicle-specs`
  - Price per day in `.vehicle-pricebar`
  - "Reserve Now" button → links to `vehicles.php`
- "View All Vehicles →" text link below the grid, centered
- No Quick View modals (removed)
- PHP query: `SELECT * FROM vehicles WHERE is_active = 1 ORDER BY id DESC LIMIT 3` (or similar — inspect `vehicles.php` query pattern)
- Handle edge case: if < 3 vehicles exist, show whatever is available. If 0, show a "No vehicles available" message.

### Bootstrap Requirements
- `container`, `row`, `g-4` (gutter)
- `col-md-6 col-lg-4` for responsive grid
- `btn-primary rounded-pill w-100` for CTA button (replaces `btn-warning`)
- `text-center` for "View All" link
- `py-5` section spacing

### Testing Requirements
- [ ] Visual: 3 vehicle cards render from DB data at desktop
- [ ] Visual: cards match `vehicles.php` card appearance
- [ ] Visual: responsive grid — 3 columns on lg+, 2 on md, 1 on sm
- [ ] Functional: "Reserve Now" button links to `vehicles.php`
- [ ] Functional: "View All Vehicles" link works
- [ ] Edge case: page renders correctly with 0, 1, 2 active vehicles
- [ ] Regression: Quick View modals fully removed, no orphaned JS references
- [ ] Console: no new JS errors
- [ ] Accessibility: vehicle images have `alt` text from DB
- [ ] Responsive: test at 320px, 375px, 768px, 992px, 1200px

### Acceptance Criteria
- [ ] Featured vehicles section shows up to 3 DB-driven cards
- [ ] Cards use the existing `.vehicle-card` component pattern
- [ ] All 3 Quick View modals removed
- [ ] No hardcoded vehicle data remains in `index.php`
- [ ] Page handles 0-vehicle edge case gracefully
- [ ] Responsive at all breakpoints
- [ ] No console errors

---

## Step 3: Hero Section Improvements

### Objective
Improve the hero section by adding a static image fallback, `prefers-reduced-motion` support, refining the content overlay, and adjusting layout to accommodate the search widget (Step 4). Remove the standalone "Book Now" button (to be replaced by the search widget).

### Files Affected
| File | Action |
|---|---|
| `index.php` | Modify — hero section (lines 22–40). Add fallback `<img>` or CSS background-image. Remove "Book Now" `<a>` button. Adjust spacing/padding for search widget overlap. |
| `css/styles.css` | Modify — add `prefers-reduced-motion` media query for `.hero-immersive`. Add hero fallback image CSS. |

### Dependencies
- A static hero image asset must exist (or be created from a video frame). Check `assets/` for an existing hero image.
- Step 4 (search widget) will depend on the hero's bottom spacing.

### Components Used
| Component | Source | Status |
|---|---|---|
| `.hero-immersive` | Inline in `index.php` | Existing — modify |
| Animate.css | CDN | Existing — keep entrance animations |
| Brand CSS variables | `css/styles.css:3–13` | Existing — use for gradient |

### Design Requirements
- Keep video background with brand gradient overlay
- Add `<img>` fallback behind/instead of video for:
  - `prefers-reduced-motion: reduce` — hide video, show static image
  - Browsers that don't support autoplay video
- Hero content: heading + subheading only (CTA button removed — search widget in Step 4 replaces it)
- Increase bottom padding to create space for the search widget to overlap (negative margin from below)
- Heading: keep `display-4 fw-bold` or adjust to `display-3` for more impact
- Subheading: keep `lead text-white-50`
- Hero `min-height`: keep `min(calc(100vh - 56px), 600px)` but consider increasing to `650px` or `700px` to accommodate search widget overlap
- Move `margin-top: 56px` from inline style to CSS class for maintainability

### Bootstrap Requirements
- `position-relative`, `overflow-hidden`, `d-flex`, `align-items-center` — keep existing
- `container`, `text-center` — keep existing
- Animate.css classes — keep existing

### Testing Requirements
- [ ] Visual: video plays correctly on desktop Chrome, Firefox, Edge
- [ ] Visual: static fallback image visible when video fails to load
- [ ] Visual: `prefers-reduced-motion` — video hidden, static image shown (test with OS accessibility settings or browser devtools emulation)
- [ ] Visual: hero has sufficient bottom padding for search widget overlap
- [ ] Visual: "Book Now" button is removed
- [ ] Responsive: hero scales correctly at all breakpoints
- [ ] Responsive: hero height appropriate on landscape phones
- [ ] Accessibility: video pause consideration (prefers-reduced-motion handling)
- [ ] Console: no new JS errors

### Acceptance Criteria
- [ ] Hero section renders with video on capable devices
- [ ] Static image fallback present for `prefers-reduced-motion` and no-video scenarios
- [ ] Standalone "Book Now" CTA button removed
- [ ] Hero bottom spacing accommodates search widget overlap
- [ ] No inline `margin-top` — use CSS class instead
- [ ] Responsive at all breakpoints
- [ ] No console errors

---

## Step 4: Vehicle Search Widget

### Objective
Add a floating vehicle search form that overlaps the hero/content boundary, providing the primary homepage conversion action. The form navigates to `vehicles.php` with query parameters for pickup date, return date, and optional vehicle category.

### Files Affected
| File | Action |
|---|---|
| `index.php` | Modify — add search widget markup after the hero section. Add PHP query for vehicle categories (for the dropdown). |
| `css/styles.css` | Modify — add search widget positioning CSS (~20–30 lines). Floating overlap on `lg+`, stacked inline on `< lg`. |
| `vehicles.php` | Potentially modify — accept and apply query parameters from the search form (pickup date, return date, category filter). Inspect current query param handling first. |

### Dependencies
- Step 3 (hero improvements) must be complete — the hero's bottom padding must accommodate the widget overlap.
- Database connection for category dropdown.
- `vehicles.php` must be inspected for existing query param support.

### Components Used
| Component | Source | Status |
|---|---|---|
| `.glassmorph` | `css/styles.css:328–335` | Existing — reuse for widget card |
| Bootstrap form controls | `.form-control`, `.form-select` | Existing Bootstrap classes |
| Brand CSS variables | `css/styles.css:3–13` | Existing |
| Bootstrap grid | `row`, `col-md-*` | Existing utility |

### Design Requirements
- Card-style widget using `.glassmorph` or white card with shadow
- Position: centered horizontally, overlapping the hero bottom edge by ~50% of the widget height (achieved via negative margin-top or `transform: translateY(-50%)`)
- On `lg+`: single horizontal row layout — 3 form fields + submit button in a row
- On `< lg`: stacked vertical layout — fields stack, button full-width
- Form fields:
  - Pickup Date (`<input type="date">`) — required
  - Return Date (`<input type="date">`) — required
  - Vehicle Type (`<select>`) — optional, populated from `SELECT DISTINCT category FROM vehicles WHERE is_active = 1`
  - Submit button: "Search Vehicles" with `fa-search` icon, `btn-primary rounded-pill` or brand-colored
- Form `action="vehicles.php"` `method="GET"`
- Field names: `pickup_date`, `return_date`, `category` (coordinate with vehicles.php)
- `z-index` above surrounding sections

### Bootstrap Requirements
- `container`
- `row`, `col-lg-3`, `col-lg-auto` for horizontal layout
- `form-control`, `form-select`, `form-label`
- `btn`, `btn-primary`, `rounded-pill`
- `shadow-lg` for elevated appearance
- `position-relative`, `z-*` utilities

### Testing Requirements
- [ ] Visual: widget overlaps hero/content boundary on desktop
- [ ] Visual: widget stacks vertically on mobile
- [ ] Visual: glassmorphism or shadow effect visible
- [ ] Functional: form submits to `vehicles.php` with correct query params
- [ ] Functional: category dropdown populates from DB
- [ ] Functional: date inputs enforce pickup < return (HTML5 `min` attribute or JS)
- [ ] Responsive: test at 320px, 375px, 768px, 992px, 1200px
- [ ] Responsive: no horizontal overflow from the widget at any breakpoint
- [ ] Accessibility: all form fields have associated `<label>` elements
- [ ] Accessibility: form is keyboard-navigable
- [ ] Console: no new JS errors

### Acceptance Criteria
- [ ] Search widget renders as a floating card overlapping the hero boundary
- [ ] 3 form fields (pickup date, return date, vehicle type) + submit button
- [ ] Form navigates to `vehicles.php` with query parameters
- [ ] Category dropdown populated from database
- [ ] Responsive layout: horizontal on lg+, stacked on smaller
- [ ] Accessible form with labels
- [ ] No console errors
- [ ] No changes to shared partials

---

## Step 5: Statistics / Trust Bar

### Objective
Add a statistics section displaying key trust indicators (total vehicles, customers, completed rentals, years of service) using real database counts where possible. Provides credibility and visual rhythm between the featured vehicles and testimonials sections.

### Files Affected
| File | Action |
|---|---|
| `index.php` | Modify — add statistics section markup. Add PHP count queries. |
| `css/styles.css` | Modify — add counter section CSS (~15–20 lines). Light background tint, large number styling. |

### Dependencies
- Database connection (already established in Step 2).
- `vehicles`, `users`, `bookings` tables must exist.

### Components Used
| Component | Source | Status |
|---|---|---|
| Brand CSS variables | `css/styles.css:3–13` | Existing |
| Bootstrap grid | `row`, `col-6 col-lg-3` | Existing utility |
| Font Awesome icons | CDN | Existing |

### Design Requirements
- Full-width section with a subtle tinted background (e.g., `--primary` at 5% opacity, or `#F0F4FF`)
- 4 counters in a row:
  - Total Vehicles — `SELECT COUNT(*) FROM vehicles WHERE is_active = 1`
  - Happy Customers — `SELECT COUNT(*) FROM users WHERE role != 'admin'`
  - Completed Rentals — `SELECT COUNT(*) FROM bookings WHERE status = 'completed'`
  - Years of Service — hardcoded (e.g., "5+")
- Each counter: large number (`display-5` or `fs-1 fw-bold`), label text below, optional Font Awesome icon above
- Numbers use `--secondary` or `--primary` color for emphasis
- Responsive: 2 per row on mobile (`col-6`), 4 per row on desktop (`col-lg-3`)

### Bootstrap Requirements
- `container`, `row`
- `col-6 col-lg-3`
- `text-center`
- `py-5` section spacing
- `fw-bold`, `display-5` or `fs-1` for numbers

### Testing Requirements
- [ ] Visual: 4 counters display correctly at desktop
- [ ] Visual: 2 per row on mobile
- [ ] Visual: numbers are visually prominent
- [ ] Functional: counts reflect actual database data
- [ ] Responsive: test at 320px, 375px, 768px, 992px, 1200px
- [ ] Console: no new JS errors
- [ ] Database: queries execute without error

### Acceptance Criteria
- [ ] Statistics section displays 4 counters with DB-driven data (3 dynamic + 1 static)
- [ ] Visual styling matches brand palette
- [ ] Responsive at all breakpoints
- [ ] No console errors
- [ ] No PHP errors

---

## Step 6: Testimonials

### Objective
Add a testimonials section with 3 customer review cards for social proof. Static/hardcoded content initially — a database-driven version can be a future enhancement.

### Files Affected
| File | Action |
|---|---|
| `index.php` | Modify — add testimonials section markup. |
| `css/styles.css` | Modify — add testimonial card CSS (~20–25 lines). Circular avatar, quote styling. |

### Dependencies
- Section eyebrow label CSS from Step 1 (reuse).
- No backend dependency (static content).

### Components Used
| Component | Source | Status |
|---|---|---|
| Bootstrap grid | `row`, `col-md-4` | Existing utility |
| Bootstrap carousel (optional, for mobile) | Bootstrap JS | Existing |
| Brand CSS variables | `css/styles.css:3–13` | Existing |
| Section eyebrow label | Step 1 CSS | Reuse |

### Design Requirements
- Eyebrow label: "TESTIMONIALS"
- Section heading: "What Our Customers Say"
- 3 testimonial cards, each with:
  - Circular avatar placeholder (can use Font Awesome `fa-user-circle` or a generic avatar image)
  - Quote text (1–3 sentences)
  - Customer name (bold)
  - Role/context (muted text, e.g., "Business Traveler", "Tourist", "Regular Customer")
- Cards: white background, subtle shadow, `rounded-3` border-radius
- Do NOT use glassmorphism here — creates visual variety vs. the "How It Works" section
- On desktop: 3 cards in a row. On mobile: single column stack (or optional carousel with indicators)
- Quote text can optionally start with a decorative quote mark icon (`fa-quote-left`)

### Bootstrap Requirements
- `container`, `row`, `col-md-4`
- `card`, `card-body`, `shadow-sm`, `rounded-3`
- `rounded-circle` for avatar
- `text-center`
- `py-5` section spacing

### Testing Requirements
- [ ] Visual: 3 testimonial cards render correctly at desktop
- [ ] Visual: cards stack on mobile
- [ ] Visual: circular avatar visible on each card
- [ ] Visual: clear visual distinction from "How It Works" glassmorphism cards
- [ ] Responsive: test at 320px, 375px, 768px, 992px, 1200px
- [ ] Accessibility: quote text is readable, proper heading hierarchy
- [ ] Console: no new JS errors

### Acceptance Criteria
- [ ] Testimonials section displays 3 review cards
- [ ] Each card has avatar, quote, name, and role
- [ ] Visually distinct from other card sections
- [ ] Responsive at all breakpoints
- [ ] No console errors

---

## Step 7: CTA Banner

### Objective
Add a full-width call-to-action banner near the bottom of the page to re-engage users who have scrolled past the featured content. Uses the brand palette for a visually strong section.

### Files Affected
| File | Action |
|---|---|
| `index.php` | Modify — add CTA banner section markup. |
| `css/styles.css` | Modify — add CTA banner CSS (~10–15 lines). |

### Dependencies
- Brand CSS variables (existing).
- No backend dependency.

### Components Used
| Component | Source | Status |
|---|---|---|
| Brand CSS variables | `css/styles.css:3–13` | Existing |
| Bootstrap buttons | `.btn`, `.rounded-pill` | Existing |

### Design Requirements
- Full-width section with `--primary` or `--secondary` gradient background (or solid `--primary`)
- White text
- Heading: "Ready to Hit the Road?" (or similar)
- Subheading: "Browse our collection and find the perfect vehicle for your next trip."
- CTA button: "Browse Vehicles" → `vehicles.php`, white/light button on dark background (`btn-outline-light rounded-pill btn-lg` or `btn-light rounded-pill btn-lg`)
- Centered content
- Generous vertical padding (`py-5 py-lg-6` or custom)
- Optional: subtle pattern or texture overlay for visual interest

### Bootstrap Requirements
- `text-white`, `text-center`
- `container`
- `btn`, `btn-outline-light` or `btn-light`, `rounded-pill`, `btn-lg`
- `py-5`

### Testing Requirements
- [ ] Visual: banner renders with brand-colored background
- [ ] Visual: white text readable against background
- [ ] Visual: CTA button visible and clickable
- [ ] Functional: button links to `vehicles.php`
- [ ] Responsive: test at all breakpoints
- [ ] Accessibility: sufficient color contrast for text on colored background
- [ ] Console: no new JS errors

### Acceptance Criteria
- [ ] CTA banner renders with brand palette
- [ ] Heading, subheading, and button present
- [ ] Button navigates to `vehicles.php`
- [ ] Responsive at all breakpoints
- [ ] WCAG-compliant contrast ratio
- [ ] No console errors

---

## Step 8: FAQ Preview (Optional)

### Objective
Add a compact FAQ preview section with 3–4 commonly asked questions from the existing `faq.php`, plus a link to the full FAQ page. This is an optional section that can be deferred.

### Files Affected
| File | Action |
|---|---|
| `index.php` | Modify — add FAQ preview section markup. |

### Dependencies
- None. Static content mirroring `faq.php` questions. No PHP query needed.
- Section eyebrow label CSS from Step 1 (reuse).

### Components Used
| Component | Source | Status |
|---|---|---|
| Bootstrap accordion | `.accordion`, `.accordion-item`, `.accordion-button` | Existing Bootstrap component |
| Section eyebrow label | Step 1 CSS | Reuse |
| Brand CSS variables | `css/styles.css:3–13` | Existing |

### Design Requirements
- Eyebrow label: "FAQ"
- Section heading: "Frequently Asked Questions"
- 3–4 accordion items with the most common questions from `faq.php`
- Standard Bootstrap accordion styling (no glassmorphism)
- "View All FAQs →" link below the accordion, centered, linking to `faq.php`
- Constrained width (`col-lg-8 mx-auto` or similar) for readability

### Bootstrap Requirements
- `container`
- `accordion`, `accordion-item`, `accordion-header`, `accordion-button`, `accordion-collapse`, `accordion-body`
- `col-lg-8 mx-auto` or `col-lg-10 mx-auto`
- `text-center` for "View All" link
- `py-5` section spacing

### Testing Requirements
- [ ] Visual: accordion renders correctly with 3–4 items
- [ ] Functional: accordion expand/collapse works
- [ ] Functional: "View All FAQs" links to `faq.php`
- [ ] Responsive: accordion readable on mobile
- [ ] Accessibility: Bootstrap accordion ARIA attributes present
- [ ] Console: no new JS errors

### Acceptance Criteria
- [ ] FAQ preview displays 3–4 questions in a Bootstrap accordion
- [ ] Accordion items expand/collapse correctly
- [ ] "View All FAQs" link navigates to `faq.php`
- [ ] Responsive at all breakpoints
- [ ] No console errors

---

## Post-Implementation Checklist

After all homepage steps are complete:

- [ ] Full page visual review at 320px, 375px, 768px, 992px, 1200px, 1400px+
- [ ] Check all links on the page (vehicles.php, faq.php, all CTAs)
- [ ] Check console for errors (existing pre-existing errors are acceptable if documented in BUGS.md)
- [ ] Verify shared partials (navbar, footer, auth modals) are unaffected
- [ ] Verify no regressions on other pages that share `css/styles.css`
- [ ] Check print view (navbar/footer hidden via `d-print-none`)
- [ ] Test with `prefers-reduced-motion: reduce` (hero video)
- [ ] Verify `prefers-color-scheme` doesn't cause issues (dark mode CSS exists but no toggle — ensure homepage sections don't break in dark mode even though it's not actively used)
- [ ] Update CHANGELOG.md with detailed entry for each completed step
- [ ] Update FEATURES.md if applicable
- [ ] Update BUGS.md if any pre-existing bugs were fixed or new ones discovered

---

## Final Homepage Structure (Post-Implementation)

```
┌─────────────────────────────────────────┐
│  Navbar (shared partial)                │
├─────────────────────────────────────────┤
│  Hero Section                           │
│  - Video background + gradient overlay  │
│  - Heading + subheading                 │
│  - prefers-reduced-motion fallback      │
├─────────────────────────────────────────┤
│  Vehicle Search Widget (floating)       │
│  - Pickup Date | Return Date | Type     │
│  - "Search Vehicles" button             │
├─────────────────────────────────────────┤
│  How It Works                           │
│  - Step 1: Choose Vehicle               │
│  - Step 2: Select Dates                 │
│  - Step 3: Reserve & Go                 │
├─────────────────────────────────────────┤
│  Featured Vehicles (DB-driven)          │
│  - 3 vehicle cards in responsive grid   │
│  - "View All Vehicles" link             │
├─────────────────────────────────────────┤
│  Statistics / Trust Bar                 │
│  - Vehicles | Customers | Rentals | Yrs │
├─────────────────────────────────────────┤
│  Testimonials                           │
│  - 3 customer review cards              │
├─────────────────────────────────────────┤
│  CTA Banner                             │
│  - "Ready to Hit the Road?"             │
│  - "Browse Vehicles" button             │
├─────────────────────────────────────────┤
│  FAQ Preview (optional)                 │
│  - 3–4 accordion items                  │
│  - "View All FAQs" link                 │
├─────────────────────────────────────────┤
│  Footer (shared partial)                │
├─────────────────────────────────────────┤
│  Auth Modals (shared partial)           │
└─────────────────────────────────────────┘
```

---

*This document is a plan only. No code has been modified. Each step should be implemented sequentially, tested, and approved before proceeding to the next. Implementation should follow the workflow defined in CLAUDE.md and UI_IMPLEMENTATION_PLAN.md.*
