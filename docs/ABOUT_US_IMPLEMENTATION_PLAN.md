# About Us Implementation Plan

Derived from [ABOUT_US_ANALYSIS.md](ABOUT_US_ANALYSIS.md) (approved). Defines the exact implementation order and per-section requirements for the About Us Page Modernization phase, per [CLAUDE.md](../CLAUDE.md)'s workflow.

**Status:** Plan only. No code has been modified. Each section below is implemented only after a dedicated Claude Code prompt is generated, reviewed, and separately approved — one section at a time, per the About Us phase instructions.

**Decision confirmed by user (this plan):** the existing 4 stat cards currently living inside "Our Story" (Variety Brands / Awesome Support / Maximum Freedom / Flexibility On The Go) are **moved out** into their own new "Why Choose PMS" section, converted from emoji to Font Awesome icons. "Our Story" becomes a single-column (or full-width two-paragraph) section once its right-column cards are removed. **"Our Story" and "Why Choose PMS" are implemented together as one merged step** (confirmed by user) so the cards are relocated in a single pass with no in-between state where they exist in neither location.

**Also confirmed by user:** the broken `assets/quider.png` → `assets/basil.png` team-photo fix (flagged in the analysis as needing confirmation) has already been applied manually and is logged in [CHANGELOG.md](../CHANGELOG.md). The Team step below no longer needs to gate on this — only the `alt="quider"` text fix remains outstanding for that step.

---

## Implementation Sequence Overview

```
Hero
  ↓
Our Story & Why Choose PMS
  ↓
Mission / Vision
  ↓
Team
  ↓
CTA
  ↓
Final Review
```

### Dependency Graph

```
Hero                         ── independent (touches the top of the page + navbar-offset spacer)
Our Story & Why Choose PMS   ── depends on Hero landing above it; merged into one step so the
                                 4 stat cards are removed from Our Story's right column and
                                 re-added in a new, dedicated Why Choose PMS section within the
                                 same implementation pass — no intermediate state where the cards
                                 exist in neither location
Mission/Vision                ── independent new section, placed between the merged Our Story /
                                 Why Choose PMS step and Team
Team                          ── independent; fixes (alt text, TBA roles) gated on user-supplied
                                 role data — the broken-image fix itself is already done (see above)
CTA                          ── independent; migrates to the existing `.cta-banner` class
Final Review                 ── depends on all prior steps
```

---

## Step 1: Hero

### Objective

Replace the current plain centered `<h1>` block and inline `<div style="height:80px">` navbar spacer with a proper Hero section, establishing stronger visual identity for the page while fixing the non-standard navbar-clearance technique.

### Existing Files Involved

- [about.php](../about.php) (lines 25, 33-36)
- [css/styles.css](../css/styles.css) (`.navbar-offset`, lines 90-92 — existing class to adopt, not create)

### Files Expected to Be Modified

- `about.php` only

### Components to Reuse

- `.navbar-offset` (replaces the inline height spacer)
- Existing heading/typography utilities (`font-poppins`, `display-*`, `fw-bold`) already used site-wide
- Brand CSS variables (`--primary`/`--secondary`/`--accent`) if any background treatment is added

### Components to Create

- None expected. If a background band/treatment beyond plain white is wanted, it should be a small, scoped CSS addition (e.g. a `.about-hero` class) — not a new video/media asset, since none exists for this page.

### Dependencies

None — this is the first step and only touches the top of the page.

### Data Requirements

Static. Existing copy ("About Us" heading, "Get to know our story and meet our team" subtitle) is reusable verbatim or lightly edited — confirm final wording with the user if a change is wanted, otherwise carry over as-is.

### CSS Requirements

- Replace `<div style="height:80px"></div>` with the `.navbar-offset` class applied to the appropriate wrapping element.
- No new hardcoded hex colors — any background must use existing CSS custom properties.

### Bootstrap 5 Requirements

- Keep using standard container/text-utility classes; no custom grid needed unless the hero design calls for a two-part layout (heading + decorative element), in which case standard `row`/`col` is sufficient.

### Responsive Requirements

- Verify fixed-navbar clearance holds correctly at all breakpoints after the `.navbar-offset` swap (this class is already proven on other pages, but must be re-verified in place on `about.php` specifically).
- Confirm the existing decorative background logo (about.php:29-31) still renders safely at narrow widths, or adjust/hide it at small breakpoints if it doesn't — this was flagged as unverified in the analysis.

### Accessibility Requirements

- Exactly one `<h1>` on the page, unchanged in content meaning.
- If a background color/gradient is introduced, verify text contrast meets WCAG AA.

### Testing Requirements

- `php -l about.php`.
- Visual check at 375px, 768px, 992px, 1400px.
- Confirm navbar overlap/clearance is correct (no content hidden behind the fixed navbar, no excess gap).
- Confirm no console errors.

### Acceptance Criteria

- Inline height-spacer div is gone, replaced by `.navbar-offset`.
- Hero renders with correct navbar clearance at all tested breakpoints.
- No new hardcoded colors introduced.
- Rest of the page (Our Story downward) is visually unaffected by this step.

### Risks

- Low. This is a scoped, low-blast-radius change (top of page only). Main risk is a navbar-clearance regression if `.navbar-offset`'s fixed margin doesn't match this page's actual navbar height in edge cases — mitigated by direct visual testing, not assumption.

---

## Step 2: Our Story & Why Choose PMS (merged)

### Objective

Keep the existing two-paragraph company story content, fix its icon-system inconsistency, and — in the same pass — relocate the 4 existing stat cards (Variety Brands / Awesome Support / Maximum Freedom / Flexibility On The Go) out of Our Story's right column into their own new, dedicated "Why Choose PMS" section, converting their icons from raw emoji to Font Awesome. Merged into a single step (per user decision) so the cards are moved atomically, with no intermediate state where they exist in neither location.

### Existing Files Involved

- [about.php](../about.php) (lines 38-86 — Our Story + the 4 cards' current location)

### Files Expected to Be Modified

- `about.php` only

### Components to Reuse

- Existing `row`/`col-lg-6` two-column layout pattern for Our Story — since the right column's cards are being removed, this step also resolves Our Story's layout (see below)
- `.section-eyebrow` (Homepage phase, `css/styles.css:400-406`) for the new Why Choose PMS heading label (e.g. "WHY CHOOSE PMS")
- Either `.feature-card`/`.glassmorph` (homepage's "How It Works" pattern) or the existing plain white `shadow-sm` card style already used for these 4 cards today — **decision to confirm at prompt-generation time**; recommend keeping the current plain white card style since it already matches other card conventions on the site (e.g. transactions summary cards) and requires no new visual pattern
- Font Awesome 6.4.2 (already loaded site-wide) for the 4 icons, replacing 🚗🎧🛡️⚙️ with appropriate equivalents (e.g. `fa-car`, `fa-headset`, `fa-shield-halved`, `fa-gears` — exact icon choice confirmed during implementation, not fixed here)

### Components to Create

- None. This step is a content move + icon-system cleanup, not new construction.

### Layout Decision for Our Story (post-removal)

Once the 4 cards are removed from the right column, "Our Story" needs a resolved layout — options to confirm with the user at prompt-generation time:
1. Collapse to a single full-width column (simplest, matches "Our Story" being pure narrative text), or
2. Keep a two-column layout with an image/decorative element in the right column instead of the cards (requires an asset that doesn't currently exist).

**Recommendation carried into the prompt:** default to option 1 (full-width single column) since no suitable image asset exists yet and inventing one is out of scope. Confirm with the user before implementation if a different treatment is preferred.

### Dependencies

Depends on Hero (Step 1) landing above it. No longer depends on a separate later step, since Why Choose PMS is implemented together with Our Story in this single step.

### Data Requirements

Static. Existing Our Story paragraph text and all 4 card headings/descriptions are reused verbatim (no content gap here, confirmed in the analysis).

### CSS Requirements

- No new hardcoded hex colors. If a new Why Choose PMS card class is introduced, it should follow the existing custom-property-driven pattern.
- Standard Bootstrap spacing utilities for Our Story's resolved single-column layout.

### Bootstrap 5 Requirements

- Our Story: standard `row`/`col` — likely simplified from the current `col-lg-6`/`col-lg-6` split to a single centered or full-width column.
- Why Choose PMS: a 4-card grid — likely `row-cols-1 row-cols-sm-2 row-cols-lg-4` for a full single row at large widths, an improvement over the current 2×2-only layout that was constrained by sharing a row with Our Story's text column.

### Responsive Requirements

- Confirm Our Story's simplified single-column layout reflows correctly at all breakpoints.
- Verify the new full-width 4-card Why Choose PMS grid reflows correctly at all breakpoints (this is a new layout shape versus the current 2×2, tested fresh, not assumed).

### Accessibility Requirements

- Both section headings (`<h2>Our Story</h2>`, `<h2>` for Why Choose PMS) at a consistent level with sibling sections, no skipped levels.
- Why Choose PMS icons must be `aria-hidden="true"` (decorative) — text (`<h6>` + description) carries the meaning, consistent with how Font Awesome icons are already handled elsewhere on the site.

### Testing Requirements

- `php -l about.php`.
- Visual check that Our Story's simplified layout has no awkward leftover whitespace, and that all 4 cards render correctly in Why Choose PMS's new grid at all breakpoints.
- Confirm emoji are fully gone from the page (`grep` for the 4 emoji characters in `about.php` should return zero matches after this step).
- Confirm the cards render exactly once (in Why Choose PMS only, not still in Our Story) — since both changes land in the same step, there's no separate "verify no duplication across steps" check needed, but still confirm no duplication within this step's own diff.
- Confirm this step's edits don't worsen the known unclosed-`<div class="row">` structural defect further down the file (about.php:89, addressed in the Team step) — this step doesn't need to touch it, just not make it worse.
- Confirm no console errors.

### Acceptance Criteria

- Story paragraphs unchanged in content; layout resolves cleanly with no leftover empty column/whitespace artifacts.
- 4 cards render in their own dedicated Why Choose PMS section, not inside Our Story, and not duplicated.
- All 4 icons are Font Awesome, not emoji.
- Card and story copy unchanged from the original.

### Risks

- Medium (per the analysis): Our Story's visual weight/balance changes since it loses its entire right-column content — mitigated by merging the two steps, which removes the earlier cross-step sequencing risk entirely.

---

## Step 3: Mission / Vision

### Objective

Add a new Mission/Vision section — currently entirely absent from the page.

### Existing Files Involved

None — this is net-new content with no current equivalent in `about.php`.

### Files Expected to Be Modified

- `about.php` only

### Components to Reuse

- `.section-eyebrow` (Homepage phase, `css/styles.css:400-406`) for a heading label (e.g. "OUR PURPOSE" / "OUR MISSION")
- Same two-column text layout convention as Our Story, for visual consistency between adjacent narrative sections
- Brand CSS variables for any background tint (e.g. matching the Coche reference's alternating tinted-band pattern between Mission and Story, adapted, not copied)

### Components to Create

- None expected beyond applying `.section-eyebrow` to a new `<h2>`.

### Dependencies

**Blocked on user-supplied content.** Per the analysis and the task's explicit instruction ("do not invent company information"), Mission and Vision statement copy must be provided by the user before this step's Claude Code prompt can be implemented. The prompt for this step must explicitly gate on this and stop to ask if the copy hasn't been supplied yet.

### Data Requirements

Static, pending user-supplied Mission/Vision copy.

### CSS Requirements

No new hardcoded colors; reuse existing brand variables if a tinted background band is used.

### Bootstrap 5 Requirements

Standard `row`/`col` two-column (or single-column, if no accompanying image/visual is used — no new image asset should be invented for this step unless the user supplies one).

### Responsive Requirements

Match Our Story's proven collapse pattern (stacks to single column below `lg`).

### Accessibility Requirements

- `<h2>` heading level, consistent with sibling sections — no skipped levels.
- If a tinted background is used, verify text contrast (WCAG AA).

### Testing Requirements

- `php -l about.php`.
- Visual check at all standard breakpoints.
- Confirm heading hierarchy via accessibility tree.

### Acceptance Criteria

- Section only implemented once real Mission/Vision copy is supplied — no placeholder/invented text ships.
- Visually consistent with Our Story's established two-column or tinted-band pattern.

### Risks

- High if implemented without real content (explicitly prohibited). Otherwise low — this is a self-contained new section with no dependency on other steps.

---

## Step 4: Team

### Objective

Fix the remaining confirmed defect in the existing Team section (placeholder role text) and improve accessibility (`alt` text), without restructuring the section's fundamental layout. The broken-image defect (`assets/quider.png` → `assets/basil.png`) is **already fixed** (user-applied, logged in [CHANGELOG.md](../CHANGELOG.md)) and is out of scope for this step except for its leftover `alt` text.

### Existing Files Involved

- [about.php](../about.php) (lines 88-197 — Team + Contact row; also touches the unclosed `<div class="row">` structural defect at line 89)

### Files Expected to Be Modified

- `about.php` only

### Components to Reuse

- Existing circular-photo-card markup pattern (kept as-is structurally)

### Components to Create

- None.

### Dependencies

**Blocked on one user confirmation**, per the analysis:
1. Actual role/title for each of the 5 team members (currently "TBA" for all five) — must be supplied, not invented.

The Claude Code prompt for this step must explicitly stop and ask if this hasn't been supplied yet.

**Already resolved, not a blocker:** the `assets/quider.png` → `assets/basil.png` broken-image fix (about.php:128) was applied by the user directly and is confirmed in place. This step only needs to update that image's `alt="quider"` to a full descriptive name, same as the other 4 team photos.

### Data Requirements

Static — 5 names (existing), 5 roles (missing, pending user input), 5 photo references (all 5 already correct as of the user's `basil.png` fix).

### CSS Requirements

None expected — existing card styling is reused unchanged.

### Bootstrap 5 Requirements

- Existing `row-cols-1 row-cols-md-2` grid is reused. If the Contact form's position changes as a side effect of this step (it currently shares a row with Team via `col-lg-7`/`col-lg-5`), that is explicitly **out of scope for this step** unless separately approved — the analysis flagged the Contact form's placement as a decision needing its own confirmation, not something to change incidentally while fixing Team defects.
- This step should also **fix the unclosed `<div class="row">` structural defect** (about.php:89) while editing this section, since it sits directly in the markup being touched — low-risk, high-value cleanup done in the same pass rather than left for later.

### Responsive Requirements

No change expected to the existing, already-correct `row-cols-1 row-cols-md-2` responsive behavior.

### Accessibility Requirements

- Change all 5 `alt` attributes from bare first names (e.g. `alt="fontanoza"`) to full descriptive names (e.g. `alt="Radi Fontanoza"`).
- Once real roles are supplied, ensure they render as visible text (not just decorative), consistent with the current `<div class="text-muted small">` treatment.

### Testing Requirements

- `php -l about.php`.
- Visual check that all 5 team photos render (no broken image icon — the `basil.png` fix is already in place, this step should confirm it still holds).
- Confirm the unclosed-`<div>` fix doesn't shift layout unexpectedly elsewhere on the page (spot-check the CTA section and footer render correctly after the fix).
- Confirm `alt` text updated for all 5 images via the accessibility tree.

### Acceptance Criteria

- No broken image anywhere on the page.
- All 5 team members show real role text, not "TBA" (only after user supplies this data — otherwise this step should not ship the change and should flag it as still blocked).
- `alt` text is descriptive for all 5 photos.
- The unclosed `row` div is properly closed, verified via HTML validation or careful manual trace, not just visual inspection (since the current rendering "looks fine" despite being invalid).

### Risks

- Medium — fixing the unclosed-div defect touches markup shared with the CTA section (they're adjacent in the same broken row), so this step's testing must include a check that the CTA section (implemented/left alone until Step 5) still renders correctly afterward.

---

## Step 5: CTA

### Objective

Migrate the existing About Us CTA banner from its bespoke inline-gradient implementation to the shared `.cta-banner` class already established and proven during the Homepage phase.

### Existing Files Involved

- [about.php](../about.php) (lines 199-208)
- [css/styles.css](../css/styles.css) (`.cta-banner`, lines 715-724 — existing class, not created here)
- [index.php](../index.php) (line 256 — reference implementation to match)

### Files Expected to Be Modified

- `about.php` only

### Components to Reuse

- `.cta-banner` class (exact same class used on the homepage) — replaces the inline `style="background: linear-gradient(...)"` block entirely
- Existing button styling pattern from the homepage's CTA banner (`btn-light rounded-pill`, or whatever the homepage's implementation uses at `index.php:256` — match it exactly for consistency)

### Components to Create

- None. This is a straight migration to an existing, already-tested component.

### Dependencies

Should be implemented after Step 4 (Team), since both steps touch the same unclosed-`<div class="row">` region — Step 4 is expected to have already fixed that structural issue, so this step lands on clean markup.

### Data Requirements

Static — existing heading/subtext/button copy and link (`vehicles.php`) reused verbatim unless the user wants copy changes.

### CSS Requirements

- Remove the inline `style="background: linear-gradient(90deg,#0F2A4D,#2F6FED,#63A8FF)"` attribute entirely — this is the exact case the project's "never introduce new colors" / prefer-CSS-classes-over-inline-styles rule is meant to prevent, and `.cta-banner` already encodes the same gradient via CSS custom properties.
- No new CSS needed; `.cta-banner` already exists.

### Bootstrap 5 Requirements

- Match the homepage's exact CTA banner markup structure (`section`/`container` wrapping, button classes) so the two banners are visually and structurally identical components, not just similar-looking.

### Responsive Requirements

Inherits `.cta-banner`'s already-proven responsive behavior from the homepage — verify it holds identically here (should, since it's the same class), not assumed without a check.

### Accessibility Requirements

- Confirm text contrast against `.cta-banner::before`'s dark overlay remains sufficient (already proven acceptable on the homepage — re-verify here since the surrounding content differs).

### Testing Requirements

- `php -l about.php`.
- Visual side-by-side comparison against the homepage's CTA banner to confirm consistency.
- Confirm the "View Cars" button still links correctly to `vehicles.php`.
- Confirm no leftover inline gradient style remains in the markup (`grep` for `linear-gradient` in `about.php` should return zero matches after this step).

### Acceptance Criteria

- CTA banner uses `.cta-banner` class, matching the homepage's implementation.
- No inline gradient styling remains.
- Button/link behavior unchanged.

### Risks

- Low — this is a well-understood migration to an already-tested component with a direct reference implementation to match.

---

## Step 6: Final Review

### Objective

Full-page regression pass across every section implemented in Steps 1-5, confirming the page holds together as a coherent whole and that no shared component (navbar/footer/auth modals) or other page was affected.

### Existing Files Involved

All changes from Steps 1-5, plus the shared includes (`includes/client_navbar.php`, `includes/client_footer.php`, `includes/auth_modals.php`) to confirm they render unchanged.

### Files Expected to Be Modified

Potentially none (a pure verification pass) — or minor final polish fixes surfaced only once every section coexists, scoped to `about.php`/`css/styles.css` only, and only with separate approval if anything more than trivial is found.

### Dependencies

All of Steps 1-5 must be complete and individually approved.

### Testing Requirements

- `php -l about.php` and every other client-facing page (`index.php`, `vehicles.php`, `faq.php`, `transactions.php`, `receipt.php`) to confirm nothing else regressed.
- Full visual pass at 320/375/576/768/992/1200/1400px+.
- Confirm heading hierarchy across the entire page (`<h1>` once, `<h2>` per section, no skipped levels) end-to-end.
- Confirm all images have correct `alt` text, no broken images anywhere on the page.
- Confirm no hardcoded hex colors remain anywhere in `about.php`'s markup.
- Confirm no leftover HTML structural defects (unclosed tags) — validate the full page structure, not just the sections touched individually.
- Confirm contact form still works end-to-end (logged-out block, logged-in submit, `messages` table insert, admin dashboard visibility) — unchanged throughout this phase, but must be re-verified as still working after all the surrounding markup changes.
- Confirm console has no new JS errors.
- Confirm no other page (index.php, vehicles.php, etc.) was inadvertently modified.

### Acceptance Criteria

- Every section from Steps 1-5 present, correctly ordered per §7 of the analysis (Hero → Our Story & Why Choose PMS → Mission/Vision → Team → CTA), on top of the shared Navbar/Footer/Auth Modal partials.
- No regressions on any other page.
- `docs/CHANGELOG.md` updated summarizing the full phase (in addition to each step's own changelog entry made at implementation time).

### Risks

- Low, assuming each prior step was individually tested and approved — this step is a safety net, not expected to surface major new issues.

---

## Documentation Updates Required (per step, and again at Final Review)

Per `CLAUDE.md`'s Documentation section, each implementation step's Claude Code prompt must include updating `CHANGELOG.md` with what changed, consistent with how the Homepage and Vehicle Listing phases documented their own steps. `docs/FEATURES.md`, `docs/COMPONENT_LIBRARY.md`, and `docs/UI_ANALYSIS.md` should be revisited at Final Review to correct the now-stale claims about About Us identified in the analysis (§5 of `ABOUT_US_ANALYSIS.md`), not during individual section steps.

---

*This document is a plan only. No code has been modified. Implementation proceeds one step at a time, beginning with Hero, only after this plan is approved and a dedicated Claude Code prompt is generated and separately reviewed for that step.*
