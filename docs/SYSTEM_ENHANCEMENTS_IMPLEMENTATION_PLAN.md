# System Enhancements Implementation Plan

Derived from [SYSTEM_ENHANCEMENTS_ANALYSIS.md](SYSTEM_ENHANCEMENTS_ANALYSIS.md) (pending approval). Defines the exact implementation order and per-step requirements for the **System Enhancements initiative** — five user-requested features that fall outside every prior phase's scope — per [CLAUDE.md](../CLAUDE.md)'s workflow.

**Status:** Plan only. **No code has been modified.** Each step below is implemented only after a dedicated Claude Code prompt is generated, reviewed, and separately approved — one step at a time, matching the process every prior phase used.

**This is its own tracked initiative.** It is not a continuation of Phase 8 (Admin Dashboard, complete — ten steps, see [CHANGELOG.md](../CHANGELOG.md)), and it is not part of the upcoming full-site Responsive Review. Its documentation is not merged into either.

---

## Key constraints, stated up front

1. **Two of the five requests rest on a premise the code contradicts.** Feature 2's "the CTA" does not use glassmorphism, and its translucency is a load-bearing legibility scrim whose removal would *break* contrast. Feature 5's "the preview button is useless" is false — Preview is a hard gate that reveals the payment field and the Confirm button, and the "automatic update" the user describes is `booking-validation.js` programmatically clicking that very button. Both are handled as decisions ([D6], [D9]), not silently implemented as stated.
2. **One feature has a blocking non-engineering prerequisite.** No privacy policy exists anywhere in the project. Step 7's checkbox cannot ship without Step 6's page, and Step 6's *content* is a legal/content deliverable the user supplies — not something this plan drafts.
3. **Feature 1 is by far the largest.** 122 fixed-value Bootstrap utility instances, 27 hardcoded hex literals, and 14 `rgba()` literals across 13 pages, plus two abandoned dark-mode scaffolds to remove. It is three steps (8, 9, 10), not one.
4. **`css/styles.css` is shared by all 13 pages.** Phase 8's constraint applies verbatim: every step touching it needs a cross-side (client *and* admin) regression check.
5. **Backend modification flags.** Step 7 modifies `register.php`. Step 8 modifies `admin_update_profile.php`/`update_profile.php` **only if [D2]** selects a DB-backed option. Both cross [CLAUDE.md](../CLAUDE.md)'s rule that *"Backend modifications should only be suggested unless explicitly requested"* and require separate explicit approval, exactly as Phase 7's Step 4 and Phase 8's Steps 7-8 did.
6. **Four booking-flow handlers are duplicated and contained, not fixed.** [BUGS.md](BUGS.md) Code Smells records that `js/app.js` and `vehicles.php` both bind `#btnPreview` and `#bookingForm submit`, with disabled-button guards added in 2026-08-12 to hold network requests to one per click. **Any step touching either handler must re-run that live check.** Steps 2 and 3 both do.

---

## Decision Gate — nine decisions block Step 1

Presented in the same manner Phase 8's plan presented Options A/B/C for its Steps 7 and 8: this plan recommended but did not decide. **All nine are now resolved by the user (2026-08-21) — see the table below.**

> **RESOLVED 2026-08-21 — all nine decided by the user.** Every option below marked **✅ DECIDED** is now locked in; the rejected options remain in place only as the record of what was considered and why they were not chosen. **Step 1 may now be prompted.**

| ID | Decision | Chosen |
|---|---|---|
| D1 | Dark mode default | **Option C** — OS-seeded, then pinned on first manual toggle |
| D2 | Dark mode persistence | **Option A (recommended)** — `localStorage` only |
| D3 | Dark mode mechanism | **Option A (recommended)** — Bootstrap `data-bs-theme` |
| D4 | Confirmation architecture | **Option A (recommended)** — shared `js/confirm.js` |
| D5 | Confirmation scope | **Option A (recommended)** — accept the 8-include / 19-exclude split |
| D6 | `.cta-banner` treatment | **Option B** — flatten to solid `var(--primary)`, delete the scrim |
| D7 | Privacy policy content/delivery | **Option (a)(i) + (b) Option A (recommended)** — user supplies final text; dedicated `privacy.php` |
| D8 | Consent recording scope | **Option A now (recommended)** — non-persisted; Option C recorded in [FEATURES.md](FEATURES.md) as future work, conditional on RA 10173 advice |
| D9 | Preview button disposition | **Option A (recommended)** — refactor, then remove |

---

### D1 — Dark mode default vs. OS `prefers-color-scheme` — ✅ DECIDED: Option C

**Option A — Three-state toggle: Light / Dark / System, defaulting to System.** The control cycles or offers all three; "System" reads `prefers-color-scheme` live and follows OS changes without a reload.
- *Pros:* correct behaviour for an accessibility feature. A user who has set their OS to dark has already stated a preference; ignoring it is the thing accessibility guidance argues against. `css/styles.css:112-139` already respects `prefers-reduced-motion`, so honouring its sibling signal is consistent with what this project already does.
- *Cons:* three states are harder to represent in one icon; needs a `matchMedia` change listener; a first-time visitor may be surprised by a dark site.

**Option B — Two-state toggle, always defaulting to Light.** `prefers-color-scheme` ignored entirely; dark is strictly opt-in and remembered.
- *Pros:* simplest to build and to explain; the site always looks the same on first visit, so screenshots, the design reference material in `references/`, and [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) stay accurate by default.
- *Cons:* ignores a stated OS-level accessibility preference — which is at odds with the user's own framing of this feature as "an accessibility preference."

**Option C — Two-state toggle, defaulting to the OS preference.** No explicit "System" state; the OS value seeds the initial choice, and the first manual toggle pins it permanently.
- *Pros:* the ergonomics of A with the simplicity of B's control; the common pattern on the web.
- *Cons:* once pinned, it never follows the OS again — a user who later switches their OS to dark won't see the site follow.

**Recommendation: Option C.** It honours the OS signal (satisfying the accessibility framing) with a two-state control that fits both shells cleanly, and it is the least surprising behaviour for the widest audience. Option A is defensible if the user wants the fully correct answer and accepts the extra control complexity.

---

### D2 — Dark mode persistence — ✅ DECIDED: Option A

**Option A — `localStorage` only (browser-local, per-device).** A `pms-theme` key plus a `data-bs-theme` attribute set by an inline `<head>` script before first paint.
- *Pros:* zero schema change, zero backend change, works for **logged-out visitors** — who are the majority audience on `index.php`, `vehicles.php`, `faq.php` and `about.php`. Correct semantics: a theme is a per-device preference. No flash-of-wrong-theme. No existing `localStorage` usage to conflict with (verified — the only five matches in `js/app.js` are inside commented-out dead blocks).
- *Cons:* not carried across devices; lost if the user clears site data.

**Option B — Database-backed per account.** A column on `users` and on `admins`; [admin_settings.php](../admin_settings.php) gains a control; `transactions.php`'s profile card gains one for customers.
- *Pros:* follows the account across devices; [admin_settings.php](../admin_settings.php) (Phase 8, Step 8) is a real, shipped home for it on the admin side.
- *Cons:* **cannot work at all for logged-out visitors.** Requires a schema change on two tables plus two endpoint changes (a flagged backend modification). Customers have no settings page — only a profile card. And it is arguably the *wrong* semantics: phone-at-night and desktop-at-noon want different answers.

**Option C — Hybrid.** `localStorage` as the source of truth; if logged in, mirror to the DB and use it to seed a new device.
- *Pros:* best of both.
- *Cons:* two sources of truth, a reconciliation rule to define, and everything Option B costs — for a preference that takes one click to re-set.

**Recommendation: Option A.** Stated plainly, per the analysis: **this is a client-side, browser-local preference, not a database-backed per-account setting.** [admin_settings.php](../admin_settings.php) was evaluated as a home and rejected on the logged-out-visitor point, not overlooked.

---

### D3 — Dark mode mechanism — ✅ DECIDED: Option A

> **This is the earliest gate in the initiative.** Although dark mode ships in Steps 8-10, this decision changes the replacement values Step 5 writes. It must be answered before Step 5 is prompted.

**Option A — Bootstrap 5.3's native `data-bs-theme`.** All 13 pages already load `bootstrap@5.3.2`. Set `data-bs-theme="dark"` on `<html>`; migrate the 122 fixed-value utilities (`bg-white`→`bg-body`, `text-muted`→`text-body-secondary`, `text-dark`→`text-body`, `bg-light`→`bg-body-tertiary`, drop `table-light`); add dark values for the nine `:root` tokens; convert the remaining 27 hex / 14 `rgba()` literals to tokens.
- *Pros:* Bootstrap recolours every component automatically — cards, modals, tables, dropdowns, navbars, form controls, alerts, badges, offcanvas, accordions, and DataTables' BS5 skin — with palettes it has already contrast-tested. **Every utility swap is a no-op in light mode**, so the migration lands and is verified *before* any dark palette exists. `text-muted`→`text-body-secondary` is also the 5.3 non-deprecated replacement, so it is a correctness fix regardless. Zero new dependencies.
- *Cons:* touches 122 markup sites across 13 files (mechanical and greppable, but broad). Bootstrap's dark palette is Bootstrap's, not this project's brand — the nine `:root` tokens still need hand-authored dark values on top.

**Option B — Hand-written `body.dark` override sheet.** Extend the existing Scaffold B (`css/styles.css:319-334, 527-530, 576-596`) into a complete override sheet.
- *Pros:* total control over every value; markup untouched.
- *Cons:* re-implements from scratch what Bootstrap already ships and already contrast-tested. Must hand-override every one of the 122 fixed-value utilities anyway (they don't respond to a token flip either) — so it is *strictly more* work, not less. Grows the stylesheet substantially. Builds on scaffolding the analysis found to be abandoned debris.

**Option C — Resurrect the `oklch` `.dark` block (`css/styles.css:48-83`).**
- *Pros:* it is already written.
- *Cons:* **not viable.** It defines `--card`, `--popover`, `--foreground`, `--ring`, `--sidebar` — names with no `:root` counterpart in this project — and omits `--accent-focus`, `--sidebar-bg`, `--sidebar-hover`, which do exist. It is a dark counterpart to a *different* design system. Using it means first authoring the light half of a token set this project does not have.

**Recommendation: Option A.** Bootstrap 5.3's colour-mode system is already loaded on every page and completely unused; adopting it is the single highest-leverage decision in this initiative. Option B is the honest fallback if the user objects to touching 122 markup sites. **Option C is not recommended under any circumstance** — its token vocabulary does not match this project's.

---

### D4 — Confirmation architecture — ✅ DECIDED: Option A

The request correctly identified this as a real architectural question that must be answered rather than sidestepped.

**Option A — A new shared `js/confirm.js`, loaded by both shells.** A small IIFE exporting `window.PMSConfirm`, containing the current `confirmAction()` implementation lifted verbatim from `js/admin.js:175-241`. `js/admin.js`'s copy is deleted; `window.confirmAction` is kept as a one-line alias so Phase 8's five existing call sites need **no edit at all**.
- *Pros:* `confirmAction()` is uniquely suited to extraction — it is already self-contained (~65 lines), depends only on jQuery + `bootstrap.Modal` (both present on all 13 pages), **already builds its own DOM** via `ensureConfirmModal()` and so has no page-element dependency, and is already exported globally. **Preserves the documented `js/app.js` ↔ `js/admin.js` isolation exactly** — neither file learns about the other; this is a *new shared dependency*, not a cross-shell coupling. Avoids duplicating four subtle correctness fixes (the `settled` double-resolution latch, namespaced handler cleanup, focus return with a `document.body.contains()` liveness check, and the `shown.bs.modal` hook that reasserts `role="alertdialog"` because Bootstrap clobbers it — [BUGS.md](BUGS.md) item 23's fix) and then letting them drift.
- *Cons:* one more `<script>` tag on 13 pages; a third shared JS file in a project with no bundler.

**Option B — Two independent implementations.** Write a separate client-side confirm in `js/app.js`, leaving `js/admin.js` untouched.
- *Pros:* zero risk to shipped admin behaviour; matches the precedent Phase 8 Step 6 set with `AdminValidation` vs. `AuthValidation` ("the **pattern**, not the code").
- *Cons:* the `AdminValidation` precedent was justified by reasons that **do not apply here** — `AuthValidation` was non-portable because it bound to page markup and lived inside a 62KB non-IIFE file; `confirmAction()` has neither problem. Duplicating means duplicating [BUGS.md](BUGS.md) item 23's fix and three other subtleties, in two files that will diverge.

**Option C — Load `js/admin.js` on client pages.**
- *Pros:* no new file.
- *Cons:* **not viable, and explicitly documented as such.** `js/admin.js:4-15` records the reasoning; its `$(function(){…})` block binds admin-only handlers (`#usersTable` DataTables init, voucher/vehicle/transaction CRUD) against elements customer pages do not have, and it would ship DataTables-dependent code to pages that never load DataTables.

**Recommendation: Option A.** Presented as a decision, not a default — but the analysis's evidence points one way, and Option C should be treated as ruled out.

---

### D5 — Confirmation scope — ✅ DECIDED: Option A

The request asked that "every decision" be turned into an enumerated list, and that any action where a modal would be *actively harmful* be named for exclusion rather than mechanically included. 34 actions were swept.

**Already covered — 7 actions, not to be re-implemented:** five admin `confirmAction()` call sites (delete user / vehicle / voucher / transaction, confirm booking) and two bespoke client modals in `transactions.php` (cancel booking, return early). **Recommendation: leave the two client modals exactly as they are** — they carry action-specific refund-policy copy a generic modal could not, and homogenising them would lose it.

**Recommended for confirmation — 8 gaps:** G1 customer logout · G2 admin logout · **G3 booking submission/payment (highest value)** · G4 customer password change · G5 admin password change · G6 customer email change · G7 admin email change · G8 admin edit-booking-times.

**Recommended for exclusion — 19 actions:** login/signup/forgot-password submits; name-only profile change; profile-picture upload; **Preview booking** (read-only, and auto-fired on every date change — a modal here would ambush the user constantly); apply voucher; contact form; home search widget (a `GET` navigation); vehicle filter, pagination, View Details, Reserve Now; receipt print; and all pure-UI-state controls.

**The principle applied:** confirm what is destructive, financial, irreversible, or session-ending; do not confirm what is additive, navigational, or trivially undoable.

**Options: (A)** accept the 8-include / 19-exclude split as recommended; **(B)** accept it with named adjustments; **(C)** apply "every decision" literally to all 34.

**Recommendation: Option A.** Option C is explicitly *not* recommended — it would put a modal in front of logging in and in front of a live-updating price preview, which is friction, not safety.

---

### D6 — `.cta-banner` treatment — ✅ DECIDED: Option B

`.cta-banner` ([index.php:268](../index.php), [about.php:251](../about.php)) does **not** use `.glassmorph`. Its `::before { background: rgba(0,0,0,0.35) }` (`css/styles.css:975-980`) is a legibility scrim, and it is doing necessary work: white text over the gradient's `--accent` end measures **2.45:1 without it** (fail) and **5.31:1 with it** (pass).

**Option A — Leave `.cta-banner` entirely alone.** It is not glassmorphism, and it is not a visibility problem.
- *Pros:* zero risk; the section keeps its gradient identity.
- *Cons:* the translucent `rgba()` layer the user asked about survives.

**Option B — Flatten to a single solid token colour.** `background: var(--primary)`; delete `::before`.
- *Pros:* satisfies "switch to solid colours" literally, removes the translucency, and **improves** contrast (white on `#0F2A4D` = 15.9:1). Also removes an absolutely-positioned pseudo-element and a `z-2` stacking dependency (`index.php:269`).
- *Cons:* loses the gradient, a distinctive visual on the two highest-traffic pages. A visual-identity change, not a bug fix.

**Option C — Keep the gradient, deepen the scrim to 45-50%.**
- *Pros:* keeps the gradient; brings the scrim into `ui-ux-pro-max`'s recommended 40-60% band (see Additional Finding A4) and widens the contrast margin.
- *Cons:* still translucent; does not address the user's stated ask at all.

**Recommendation: Option A**, with a plain statement to the user that the CTA was inspected, is not glassmorphism, and is *already* the safer of the two treatments. **Option B** if the user's intent is genuinely "no translucency anywhere" — it is safe and improves contrast, at the cost of the gradient. **Option C is not recommended** — it is a half-measure that satisfies neither goal.

---

### D7 — Privacy policy content and delivery — ✅ DECIDED: (a)(i) + (b) Option A

**Verified: no privacy policy exists anywhere in the project** — no page, no FAQ section, no footer link, no draft in `docs/`. A checkbox linking to nothing does not satisfy the compliance intent.

**Two sub-decisions:**

**(a) Who authors the text?** Per the request's explicit instruction, **this plan does not draft it.** Options: **(i)** the user supplies final text, and Step 6 builds only the page around it *(recommended)*; **(ii)** the user supplies an outline and Step 6 builds a clearly-marked placeholder structure with `[TO BE SUPPLIED]` markers, to be filled before launch; **(iii)** Step 6 is deferred entirely until the text exists, and Feature 4 is descoped from this initiative.

**(b) Where does it live?**
- **Option A — a dedicated `privacy.php` page** using `includes/client_navbar.php` / `client_footer.php`, linked from the footer *and* from the signup checkbox with `target="_blank" rel="noopener"`.
  - *Pros:* a real URL — shareable, linkable from a future mobile app, and reachable outside the signup flow. Follows the six-page client shell convention exactly. `target="_blank"` is a **hard requirement**: the signup form lives inside a Bootstrap `.modal`, so same-tab navigation destroys everything typed.
  - *Cons:* one new file.
- **Option B — a modal inside `includes/auth_modals.php`.**
  - *Pros:* no new page; no navigation away.
  - *Cons:* no URL, so it cannot be linked from the footer, from a mobile app, or from anywhere else. Requires nesting or swapping Bootstrap modals — a known-awkward pattern. Not discoverable by anyone not signing up. **Not recommended.**

**Recommendation: (a)(i) + (b) Option A.** A privacy policy needs a URL.

> **A note the plan is obliged to make.** This is not legal advice. The system already collects name, email, contact number, **age**, and **uploaded driver's-licence images** stored in two unreconciled web-root directories with no documented retention policy ([SYSTEM_ENHANCEMENTS_ANALYSIS.md](SYSTEM_ENHANCEMENTS_ANALYSIS.md) §4.1, §9 A10). **That fact should be put in front of whoever advises on RA 10173 before the policy text is written**, because it materially changes what the policy has to say. Remediating the storage itself is out of scope for this initiative.

---

### D8 — Consent recording scope — ✅ DECIDED: Option A now, Option C as recorded future work

**Option A — Non-persisted checkbox.** Validate client-side and server-side; do not store.
- *Pros:* zero schema change, zero migration, zero timezone exposure. Fully satisfies "the form cannot submit unchecked."
- *Cons:* no audit trail. Cannot demonstrate *after the fact* that any given user consented.

**Option B — A timestamp column.** `users.privacy_consent_at DATETIME`, written with MySQL's `NOW()`.
- *Pros:* small; proves *when*. One `db_migrations/` script.
- *Cons:* proves *when* but not *to what*. If the policy is ever revised, the record cannot distinguish versions. Existing users have `NULL` with no defined meaning.

**Option C — A versioned consent record.** Either `users.privacy_consent_at` + `users.privacy_policy_version`, or a separate `user_consents` table (`user_id`, `policy_version`, `consented_at`, optionally `ip_address`).
- *Pros:* the only option that survives a policy revision — what an auditable consent record actually looks like.
- *Cons:* largest. New table or two new columns, a versioning discipline on the policy document itself, and a **re-consent flow for existing users** — which is its own feature, not a checkbox.

**Constraints that apply to B and C equally:**
- **Timezone.** [BUGS.md](BUGS.md) item 15 (PHP on UTC, MySQL on Manila) makes any PHP-computed timestamp silently 8 hours wrong. **Write with MySQL's `NOW()` / `CURRENT_TIMESTAMP`, never from PHP** — the same technique Phase 8's Step 7 used. Item 15's own fix stays out of scope.
- **Migration discipline.** `register.php:107-136` already contains a runtime `ALTER TABLE … ADD COLUMN IF NOT EXISTS` retry for `license_path` — evidence of real environment drift. Any new column ships as a `db_migrations/` script, **never** as another runtime `ALTER`.
- **API impact.** `register.php` is in [PMS.postman_collection.json](../PMS.postman_collection.json)'s mobile-facing collection. A server-side consent requirement is a **breaking API change** and must be recorded in [API.md](API.md).

**Recommendation: Option A now, Option C recorded in [FEATURES.md](FEATURES.md) as identified future work** — the same shape Phase 8's Step 8 took (Option C: build the smaller thing honestly, document the larger one accurately). **But this recommendation is explicitly conditional on D7's legal input.** If the RA 10173 advisor states that an auditable consent record is required, Option C is required and this recommendation is void. **This plan cannot make that call.**

---

### D9 — Preview button disposition — ✅ DECIDED: Option A

**The premise as stated is false.** `#btnConfirm` and `#amountPaidSection` both ship `d-none`; the only code that reveals them is the Preview success branch. And the "automatic update" the user describes **is** `js/booking-validation.js:55` calling `$("#btnPreview").click()`. Naïve removal would leave the booking flow with no payment field and no Confirm button.

**Option A — Refactor, then remove.** Extract Handler A's body into `refreshBookingPreview()`; call it directly from `booking-validation.js` instead of `.click()`; make `#amountPaidSection` unconditionally visible; gate `#btnConfirm` on a successful preview *result*. Then delete the button, the dead duplicate Handler B, and the `setButtonLoading` calls tied to the button.
- *Pros:* delivers exactly what the user wants — the button disappears and pricing still auto-updates. Closes [BUGS.md](BUGS.md) item 3 and removes one of the four competing booking implementations as a side effect. Removes the proven-dead `vehicles.php` handler (Additional Finding A5).
- *Cons:* the largest of the three. Touches `vehicles.php`, `js/app.js`, `js/booking-validation.js`, `js/voucher-manager.js` (verification only), and `css/styles.css`. Retires shipped Motion P0/P1 work, requiring four [MOTION_DESIGN_ANALYSIS.md](MOTION_DESIGN_ANALYSIS.md) checklist items to be marked superseded.

**Option B — Keep the button, relabel it.** Rename "Preview" → "Calculate Total" (or similar) and leave the mechanism alone.
- *Pros:* near-zero risk. If the real complaint is *"why do I have to click this when it already updates?"*, the honest answer is that the label misdescribes a required step — a copy fix addresses the actual confusion.
- *Cons:* the button the user asked to remove is still there.

**Option C — No change.** Record the finding; do nothing.
- *Pros:* zero risk.
- *Cons:* the request goes undelivered.

**Recommendation: Option A.** The user's underlying goal — no redundant button — is achievable safely, and the refactor pays for itself by closing a known bug and deleting a dead handler. **Option B is the correct fallback** if the user prefers not to touch the booking flow, and it is a legitimate answer, not a cop-out. Whichever is chosen, this must be stated plainly: *the button is not currently redundant; it is load-bearing, and making it redundant is the work.*

---

## Implementation Sequence Overview

```
Step 1  — Shared Confirmation Module            (D4 ✅ Option A)          [F3 foundation]
   ↓
Step 2  — Booking Preview Refactor              (D9 ✅ Option A)          [F5]
   ↓
Step 3  — Client-Side Confirmations             (D5 ✅ Option A)          [F3]
   ↓
Step 4  — Admin-Side Confirmation Gaps          (D5 ✅ Option A)          [F3]
   ↓
Step 5  — Glassmorphism → Solid Surfaces        (D3 ✅ A, D6 ✅ B)         [F2]
   ↓
Step 6  — Privacy Policy Page                   (D7 ✅ decided — awaiting user's policy text) [F4 prerequisite]
   ↓
Step 7  — Signup Consent Checkbox               (D8 ✅ Option A · BACKEND APPROVAL STILL REQUIRED) [F4]
   ↓
Step 8  — Dark Mode Foundation                  (D1 ✅ C, D2 ✅ A, D3 ✅ A) [F1]
   ↓
Step 9  — Dark Mode: Client Sweep                                     [F1]
   ↓
Step 10 — Dark Mode: Admin Sweep                                      [F1]
   ↓
Step 11 — Responsive & Accessibility Pass
   ↓
Step 12 — Final Review & Documentation
```

### Dependency Graph

```
Step 1  Shared Confirmation      ── independent of all other features; blocks Steps 3 and 4.
        Module                      Answers D4. Introduces js/confirm.js (or not, per D4)
                                    and re-points js/admin.js's five existing call sites at
                                    it via an alias, so Phase 8's shipped behaviour is
                                    byte-identical. Pure infrastructure: zero user-visible
                                    change, which is exactly what makes it safely first.

Step 2  Booking Preview          ── independent of Step 1; must precede Step 3. Step 3's G3
        Refactor                    adds a confirmation to #btnConfirm, and this step changes
                                    what reveals #btnConfirm. Doing them in the other order
                                    means wiring a confirmation to markup about to change.
                                    Touches the [BUGS.md] Code Smells double-handler pair —
                                    the "1 network request per click" live check is mandatory.

Step 3  Client-Side              ── depends on Steps 1 and 2. Wires G1, G3, G4, G6. Leaves
        Confirmations               transactions.php's two existing bespoke modals untouched.

Step 4  Admin-Side               ── depends on Step 1 only; independent of Steps 2-3 in code,
        Confirmation Gaps           ordered after Step 3 so one confirmation convention is
                                    settled on the client before it is extended to admin.
                                    Wires G2, G5, G7, G8. G2 is notable: #adminLogoutBtn has
                                    no JS handler at all today — this step adds the first one.

Step 5  Glassmorphism →          ── depends on D3 being answered (it determines whether
        Solid Surfaces              replacements are bg-white or bg-body), NOT on Step 8
                                    shipping. Must precede Steps 8-10: theming .glassmorph
                                    for dark mode and then deleting it is wasted work plus a
                                    wasted contrast audit. Fixes 4 pieces of failing copy and
                                    5 failing controls in the auth modals, and removes an
                                    indeterminate-contrast surface over video.

Step 6  Privacy Policy Page      ── independent of every other step. HARD PREREQUISITE for
        [GATED — content]           Step 7. Its content is a legal/content deliverable the
                                    user supplies; this plan builds the page, not the words.

Step 7  Signup Consent           ── depends on Step 6 (a checkbox must link to something).
        Checkbox                    REQUIRES BACKEND APPROVAL (register.php). Under D8's
        [GATED — BACKEND]           Options B/C also requires a db_migrations/ script.
                                    Breaking API change for any future mobile client.

Step 8  Dark Mode Foundation     ── depends on Step 5 (fewer surfaces to theme) and on D1,
        [GATED]                     D2, D3. Removes both abandoned scaffolds, adds the dark
                                    token set, adds both toggles, adds the no-flash inline
                                    head script. Under D3 Option A, also performs the 122
                                    utility-class migration — every swap a light-mode no-op,
                                    verifiable before any dark palette exists.

Step 9  Dark Mode:               ── depends on Step 8. Contrast-audits all 6 client pages in
        Client Sweep                dark mode, independently against WCAG 2.1 AA. Includes
                                    Step 5's new solid surfaces and Step 7's consent checkbox.

Step 10 Dark Mode:               ── depends on Step 8; independent of Step 9 in code, ordered
        Admin Sweep                 after it so the client palette is settled first. All 6
                                    admin pages + admin-login.php. DataTables, .metric-card,
                                    and the Business Overview panel (Phase 8 Step 7) are the
                                    highest-risk surfaces.

Step 11 Responsive &             ── depends on all of Steps 1-10 (or on gated steps being
        Accessibility Pass          formally descoped). Verifies ONLY what this initiative
                                    changed. Does not absorb [BUGS.md] item 24 or the
                                    upcoming full-site Responsive Review phase.

Step 12 Final Review &           ── depends on all prior steps.
        Documentation
```

**A note on ordering.** Step 1 is first for the same reason Phase 8 put its shell repair first: it is not an improvement, it is the condition under which Steps 3 and 4 are implementable at all. It is also the only step in this plan with **zero user-visible change** — which makes it the safest possible place to start and the cleanest thing to verify.

---

## Step 1: Shared Confirmation Module (D4 ✅ DECIDED: Option A — shared `js/confirm.js`) — ✅ IMPLEMENTED 2026-08-21

> **Status: complete.** See [CHANGELOG.md](../CHANGELOG.md)'s "System Enhancements — Step 1" entry for the full verification record. One deviation from this section as originally written, found during implementation: **`admin-login.php` was excluded** from the "all 13 pages" script-tag rollout — it loads no JavaScript at all (no jQuery, no Bootstrap bundle, no client-side confirmable action; a native `method="POST"` form), so there is nothing for `js/confirm.js` to support and nothing on the page to confirm. 12 pages received the tag, not 13.

### Objective

Establish one confirmation-dialog implementation usable by both the customer and admin shells, without breaking the deliberate, documented isolation between `js/app.js` and `js/admin.js`. **Zero user-visible change.** Phase 8's five existing confirmations must behave byte-identically after this step.

### Existing Files Involved

- [js/admin.js](../js/admin.js) — `ensureConfirmModal()` `:175-204`, `confirmAction()` `:206-241`, export `:930`; five call sites at `:328`, `:488`, `:613`, `:731`, `:775`; the isolation rationale comment at `:4-15`
- [js/app.js](../js/app.js) — read-only reference. `AuthValidation` `:1038-1146`, `showAuthError()` `:1159`. **Must not be loaded on admin pages.**
- All 13 pages' `<script>` blocks (for the new tag, under D4 Option A)

### Files Expected to Be Created

- **`js/confirm.js`** — under D4 Option A only. A small IIFE (~70 lines) exporting `window.PMSConfirm`.

### Files Expected to Be Modified

- `js/admin.js` (delete the moved code; add `window.confirmAction = window.PMSConfirm;` alias)
- The 6 admin pages + `admin-login.php` and the 6 client pages (one `<script>` tag each, ordered **before** `js/admin.js` / `js/app.js`)

### Components to Reuse

- `confirmAction()`'s implementation **verbatim**, including every correctness detail: the `settled` double-resolution latch, `.adminConfirm`-namespaced handler cleanup, focus return to `document.activeElement` guarded by `document.body.contains()`, and the `shown.bs.modal` hook reasserting `role="alertdialog"` ([BUGS.md](BUGS.md) item 23's fix — **do not re-derive this; move it**)
- Bootstrap `.modal`, `.modal-sm`, `.btn-secondary`, `.btn-danger`
- The `{title, body, confirmLabel, variant}` options contract Phase 8 established

### Components to Create

- `window.PMSConfirm(options) → Promise<boolean>` — identical signature and behaviour
- `window.confirmAction` retained as an alias, so **none of Phase 8's five call sites are edited**

### Dependencies

**D4 resolved: Option A.** Depends on nothing else.

### Data Requirements

**None.** No query, endpoint, or schema change.

### CSS Requirements

**None.** The modal is built entirely from Bootstrap classes. No new colours, no new tokens.

### Bootstrap 5 Requirements

- `.modal` / `.modal-dialog.modal-sm` / `.modal-content` / `-header` / `-body` / `-footer` — unchanged from Phase 8
- `bootstrap.Modal.getOrCreateInstance()` — verify `bootstrap.bundle.min.js` is loaded before `js/confirm.js` on all 13 pages (it is on 12; **verify `receipt.php` explicitly**)

### Responsive Requirements

- `.modal-sm` legible and both buttons ≥44×44px at 320px and 375px (the Phase 8 Step 9 standard, [BUGS.md](BUGS.md) item 25)
- No change from current admin behaviour — this is a regression check, not new work

### Accessibility Requirements

- `role="alertdialog"` survives every show (the `shown.bs.modal` reassertion must move intact)
- `aria-labelledby` / `aria-describedby` wiring preserved
- Focus returns to the trigger on Confirm, Cancel, backdrop click, **and** Esc
- Esc and backdrop click both resolve `false`

### Testing Requirements

- `node --check js/confirm.js` and `node --check js/admin.js`
- **All five Phase 8 confirmations still work identically:** delete user, delete vehicle, delete voucher, delete transaction, confirm booking — each showing the same title, body copy, and button variant as before
- Cancel / Esc / backdrop each perform no action and return focus to the trigger
- `typeof window.confirmAction === 'function'` **and** `typeof window.PMSConfirm === 'function'` on every admin page
- `typeof window.PMSConfirm === 'function'` on every client page
- **`window.AdminValidation` is `undefined` on client pages** — proof `js/admin.js` was not accidentally loaded
- Zero new console errors on all 13 pages
- Two confirmations in sequence reuse the same lazily-built modal without duplicate-ID errors

### Acceptance Criteria

- One confirmation implementation exists, reachable from both shells
- `js/app.js` and `js/admin.js` remain mutually unaware — neither loads or references the other
- Phase 8's five call sites are **unedited**
- No visual or behavioural change is observable anywhere

### Risks

- **Low overall** — this is a move, not a rewrite.
- **Medium (load order).** `js/confirm.js` must be parsed before `js/admin.js`'s `window.confirmAction` alias line runs. Getting the tag order wrong on one of 13 pages produces a `TypeError` only when a confirmation is triggered. **Verify per page, not by assumption.**
- **Low.** `receipt.php` loads a different script set (`js/printer.js`); confirm Bootstrap's bundle is present before adding the tag.

> **Note — no backend modification.** This step touches zero PHP logic; the only PHP-file edits are `<script>` tags.

---

## Step 2: Booking Preview Refactor (D9 ✅ DECIDED: Option A — refactor, then remove) — ✅ IMPLEMENTED 2026-08-21

> **Status: complete.** See [CHANGELOG.md](../CHANGELOG.md)'s "System Enhancements — Step 2" entry for the full verification record, including a real end-to-end booking submitted live to confirm the refactored flow. One finding surfaced during implementation, deliberately not fixed here: `js/app.js` binds its own second, unguarded `#bookingForm` submit handler alongside `#btnConfirm`'s click handler — a Confirm-side duplicate-POST risk structurally similar to the `js/app.js` ↔ `vehicles.php` pair [BUGS.md](../docs/BUGS.md) Code Smells already documents, but internal to `js/app.js` itself and not previously flagged. Live testing showed exactly one `reserve.php` request per submission, so it did not fire in practice — recorded for a future finding, out of scope for this step.

### Objective

Make `#btnPreview` genuinely redundant — then remove it — without altering pricing, payment entry, or confirm-gating. **Or**, under D9 Option B, relabel it and stop. This step exists because the request's premise was verified false: Preview is currently a hard gate, and the "automatic update" is `booking-validation.js` clicking the button.

### Existing Files Involved

- [vehicles.php](../vehicles.php) — `#bookingPreview` `:332-339` (+ commented-out copy `:317-330`), `#amountPaidSection` `:342-350`, button row `:354-358`, reset `:812-817`, Handler B `:828-895` (**dead render path**), submit handler `:899+`
- [js/app.js](../js/app.js) — Handler A `:57-160`, `setButtonLoading` `:87`/`:157`, resets `:48` and `:512`, `#btnConfirm` handler `:213`
- [js/booking-validation.js](../js/booking-validation.js) — `calculateTotalPrice()` `:44-57` (the `.click()` at `:55`), init `:60-72`
- [js/voucher-manager.js](../js/voucher-manager.js) — `#bookingPreview` text parsing at `:68-77` and `:199-211` — **read-only; verification target**
- `css/styles.css:1032-1050` — `.js-booking-reveal` and its 9-line rationale comment
- [MOTION_DESIGN_ANALYSIS.md](MOTION_DESIGN_ANALYSIS.md) — Priority Matrix `:389-390`, checklist `:961, :992, :993, :994`

### Files Expected to Be Modified

`vehicles.php`, `js/app.js`, `js/booking-validation.js`, `css/styles.css` (only if `.js-booking-reveal` becomes unreferenced), plus `docs/MOTION_DESIGN_ANALYSIS.md` and `docs/BUGS.md`

### Components to Reuse

- `reserve_preview.php` — **unchanged**. Same endpoint, same payload shape, same response shape.
- The existing pricing render markup from Handler A, including the vehicle-thumbnail row
- `PMSMotion.setButtonLoading()` — **re-pointed at `#btnConfirm`**, not deleted, so the Motion P0 loading affordance survives on the button that remains

### Components to Create

- **`refreshBookingPreview()`** — a named function containing Handler A's body, callable directly. Returns a promise resolving to success/failure so callers can gate on the result.
- A **preview-state flag** replacing the `d-none`-on-`#btnConfirm` gate: `#btnConfirm` becomes `disabled` until a preview succeeds, rather than hidden. Visible-but-disabled communicates the required order; invisible does not. (`ui-ux-pro-max` §8 `disabled-states`, §4 `primary-action`.)

### Dependencies

**D9 resolved: Option A.** Must precede Step 3 (which adds a confirmation to `#btnConfirm`).

### Data Requirements

**None.** No query, endpoint, or schema change. `reserve_preview.php` is not touched.

### CSS Requirements

- `.js-booking-reveal` (`:1032-1050`) stays if the reveal is retained for `#bookingPreview`; delete **only** if provably unreferenced, and delete its rationale comment with it.
- No new colours, no new tokens.

### Bootstrap 5 Requirements

- `.d-none` removed from `#amountPaidSection`; the section becomes permanently visible
- `#btnConfirm` uses the `disabled` attribute rather than `.d-none`
- The button row `d-flex justify-content-end gap-2` must not wrap awkwardly with one fewer button

### Responsive Requirements

- Button row at **375px and 768px** — verify wrap behaviour with two buttons instead of three
- `#amountPaidSection` permanently visible lengthens the modal; verify `.modal-dialog-scrollable` behaviour at **320px and 375px**

### Accessibility Requirements

- `#amountPaidSection` no longer appearing dynamically **removes** a live-region concern — confirm no `aria-live` is orphaned
- `#btnConfirm` disabled must carry `aria-disabled` semantics and an explanation of *why* (`ui-ux-pro-max` §8 `disabled-states`, `error-clarity`)
- `#amount_paid`'s `required` toggling must keep its `<label>` association intact
- Keyboard: Tab order through the form must remain logical with the button removed

### Testing Requirements

- `php -l vehicles.php`; `node --check` on all three JS files
- **The regression check that matters most:** with DevTools Network open, change `#rental_date`, then `#return_date`, then `#voucherSelect` — each must produce **exactly one** POST to `reserve_preview.php`. This is the direct test of [BUGS.md](BUGS.md) Code Smells' containment surviving the refactor.
- Full booking end-to-end: select dates → total updates with no click → enter amount → Confirm → booking created with the correct `total_amount` and `amount_paid`
- **Voucher regression:** apply a voucher and verify the discount is correct — this exercises `voucher-manager.js`'s `#bookingPreview` text-parsing fallbacks at `:68-77` and `:199-211`
- Confirm cannot be submitted before a successful preview
- Error path: force `reserve_preview.php` to fail (DevTools Offline) — the alert appears, `#btnConfirm` stays disabled, no loading state is stranded
- Modal reopen resets cleanly (all three reset paths)
- Zero new console errors on `vehicles.php`

### Acceptance Criteria

- Under D9 Option A: `#btnPreview` no longer exists; pricing still updates automatically on every date and voucher change; the payment field is always visible; Confirm is gated on a successful preview
- The dead `vehicles.php` Handler B is removed (Additional Finding A5)
- [BUGS.md](BUGS.md) item 3 marked resolved **and its mechanism corrected** (Additional Finding A6 — it is not a thrown `ReferenceError`; the identifier resolves to the DOM element via `window` named-access and serialises as `{}`)
- [MOTION_DESIGN_ANALYSIS.md](MOTION_DESIGN_ANALYSIS.md)'s four `#btnPreview`/`#bookingPreview` checklist items marked **superseded with the reason**, not left silently failing
- Exactly one `reserve_preview.php` request per input change

### Risks

- **High.** This is the most functionally sensitive step in the initiative. It touches the booking flow — the system's revenue path — and four files, one of which (`voucher-manager.js`) couples to it through *parsed rendered text* rather than an API.
- **High.** Removing one of a contained duplicate-handler pair changes the containment equation. The double-submit guard at `vehicles.php:829` exists precisely because this pair misbehaves. **Re-verify live, not by reading.**
- **Medium.** `voucher-manager.js`'s regex fallbacks break *silently* if `#bookingPreview`'s text stops matching `/Total[:\s]*₱?…/`. Handler A's markup renders `<h5>Total: ₱…</h5>` — the refactor must preserve that string shape exactly, or update both parse sites.
- **Medium.** Retiring shipped Motion P0 work requires documentation updates in two files, not just code deletion.
- **Low.** Making `#amountPaidSection` always visible is a UX change (the form is longer on open). Acceptable and arguably better — progressive disclosure was hiding a required field.

---

## Step 3: Client-Side Confirmations (D5 ✅ DECIDED: Option A — 8-include / 19-exclude split accepted) — ✅ IMPLEMENTED 2026-08-21

> **Status: complete.** See [CHANGELOG.md](../CHANGELOG.md)'s "System Enhancements — Step 3" entry for the full verification record. The nested-modal risk this section flagged for G3 (a stacked `#bookingModal` + confirmation dialog) was investigated directly with a diagnostic `submit`-event listener under both a programmatic and a genuinely trusted mouse click, before shipping — the theorized race between the async confirmation and the native form-submit default action did not occur in practice, so no defensive `e.preventDefault()` or hide-and-reshow fallback was needed. G3 was also verified end-to-end with a real booking (`B17872978468880`), confirming the [BUGS.md](../docs/BUGS.md) Code Smells double-submit containment guard survived a second consecutive modification (this step, on top of Step 2's).

### Objective

Wire the four client-side confirmation gaps — **G1** customer logout, **G3** booking submission/payment, **G4** password change, **G6** email change — using Step 1's shared module. Leave `transactions.php`'s two existing bespoke modals untouched.

### Existing Files Involved

- [js/app.js](../js/app.js) — `#logoutBtn` `:1546`, `logoutUser()` `:988`, `#btnConfirm` `:213`, `#changePasswordForm` `:1758`, `#profileInfoForm` `:1693`
- [vehicles.php](../vehicles.php) — `#bookingForm` submit `:899+` (the duplicate half of the contained pair)
- [transactions.php](../transactions.php) — `#cancelBookingModal` `:435-464`, `#returnEarlyModal` `:414-432` — **read-only. Do not modify.**
- `js/confirm.js` (from Step 1)

### Files Expected to Be Modified

`js/app.js` only. **No PHP file is modified in this step.**

### Components to Reuse

- `window.PMSConfirm()` from Step 1 — entirely
- `PMSMotion.setButtonLoading()` — the confirmation must resolve **before** the loading state is set, not after
- The existing `showAuthError()` / alert conventions for the failure paths

### Components to Create

- Four call sites with action-specific copy. Draft copy, for review:
  - **G1 logout** — *"Log out? You'll need to sign in again to view your bookings."* · `variant: 'warning'` · confirm label "Log Out"
  - **G3 booking** — *"Confirm this booking for ₱{total}? Your booking will be submitted for approval and the voucher you selected will be used."* — **must interpolate the live total**, or the modal adds friction without adding information · `variant: 'success'` · confirm label "Confirm Booking"
  - **G4 password** — *"Change your password? You'll use the new password the next time you log in."* · `variant: 'primary'` · confirm label "Change Password"
  - **G6 email** — *"Change your email to {new}? This is the address you'll log in with and the address password resets are sent to."* · `variant: 'primary'` · confirm label "Change Email"
- **A diff check on `#profileInfoForm`** — confirm **only** if the email field actually changed. A name-only edit must submit with no modal (per D5's exclusion list).

### Dependencies

Steps 1 and 2. **D5 resolved: Option A.**

### Data Requirements

**None.** No endpoint, query, or schema change. Every confirmation is a pre-flight gate in front of an existing `fetch`.

### CSS Requirements

**None.**

### Bootstrap 5 Requirements

- G3's confirmation opens **while `#bookingModal` is already open** — a nested-modal situation. Bootstrap 5.3 supports stacked modals, but backdrop z-index and scroll-lock must be verified live. If stacking proves problematic, the fallback is to hide `#bookingModal` first via `hidden.bs.modal` and re-show it on cancel — the same one-at-a-time sequencing `vehicles.php:798-820` already uses for the details→booking hand-off.
- `variant` must be passed explicitly on all four — `PMSConfirm`'s default is `danger`, which is wrong for every one of them.

### Responsive Requirements

- All four confirmations at **320px and 375px**; buttons ≥44×44px
- **G3 specifically at 375px** — a `.modal-sm` stacked over `#bookingModal` (a `.modal-lg`) is the tightest case in this initiative

### Accessibility Requirements

- Focus moves into the confirmation and returns to the trigger on dismissal (inherited from Step 1)
- **Nested-modal focus trapping for G3** — focus must not escape into the still-mounted `#bookingModal` behind it. **This is the specific a11y risk of this step and must be keyboard-tested, not assumed.**
- Esc closes only the confirmation, leaving `#bookingModal` open
- The interpolated total in G3's body is announced (it is inside `aria-describedby`'s target)

### Testing Requirements

- `node --check js/app.js`
- **G1:** logout prompts; Cancel keeps the session (verify `me.php` still reports logged-in); Confirm logs out and reloads
- **G3:** the confirmation appears, shows the correct total, and — critically — **still fires exactly one POST to `reserve.php`** on confirm. This re-tests the contained `js/app.js` ↔ `vehicles.php` duplicate submit pair from [BUGS.md](BUGS.md) Code Smells, which Step 2 already disturbed once.
- **G3 cancel:** no booking is created; `#bookingModal` remains open with all entered data intact
- **G4:** confirm prompts; Cancel leaves the password unchanged (verify by logging in with the old one)
- **G6:** changing **only the name** submits with **no modal**; changing the email prompts and shows the new address
- Keyboard-only walkthrough of all four
- **Excluded actions verified as still un-prompted:** login, signup, forgot-password, profile-picture upload, apply voucher, contact form, home search widget, filter, pagination
- Zero new console errors on all 6 client pages

### Acceptance Criteria

- G1, G3, G4, G6 each show a confirmation with action-specific copy and correct variant
- Name-only profile edits are not gated
- `transactions.php`'s two bespoke modals are byte-unchanged
- No excluded action gained a modal
- Exactly one network request per confirmed action

### Risks

- **Medium-High (G3).** Nested modals plus a contained duplicate-handler pair plus a step that just refactored the same flow. **G3 is the single riskiest call site in this plan.** If nested modals misbehave, take the hide-and-reshow fallback rather than forcing the stack.
- **Medium.** Inserting an `await` before `setButtonLoading(true)` changes async ordering in four handlers. A confirmation resolved after the fetch already started is worse than no confirmation.
- **Low.** The G6 diff check must compare against the *server-rendered original* value, not the field's current value at submit time.

---

## Step 4: Admin-Side Confirmation Gaps (D5 ✅ DECIDED: Option A — 8-include / 19-exclude split accepted) — ✅ IMPLEMENTED 2026-08-21

> **Status: complete.** See [CHANGELOG.md](../CHANGELOG.md)'s "System Enhancements — Step 4" entry for the full verification record, including G2 tested at both desktop and 375px (offcanvas-stacked) breakpoints on two separate pages, and a full byte-identical regression of Phase 8's five existing confirmations. **One tooling limitation to carry forward to Step 11:** this session's Browser tool could not produce a composited screenshot, so G2's offcanvas-stacking test at 375px was verified functionally (z-index order, backdrop count, scroll-lock, successful interaction) rather than visually — a real screenshot/manual look at that specific interaction is still owed before Step 11 closes it out.

### Objective

Close the four admin confirmation gaps — **G2** admin logout, **G5** admin password change, **G7** admin email change, **G8** edit booking times — bringing the admin side to one consistent convention. Phase 8's five existing confirmations are not touched.

### Existing Files Involved

- [includes/admin_sidebar.php](../includes/admin_sidebar.php)`:44-48` — `#adminLogoutBtn`, a bare `<a href="logout.php">` with **no JS handler anywhere** (verified by grep)
- [js/admin.js](../js/admin.js) — `#passwordForm` `:879`, `#profileForm` `:825`, `#editTransactionForm` `:689`; the five existing `confirmAction()` sites `:328, :488, :613, :731, :775` (**read-only reference**)
- [admin_settings.php](../admin_settings.php) — both forms, Phase 8 Step 8's output
- `js/confirm.js` (from Step 1)

### Files Expected to Be Modified

`js/admin.js` only. `includes/admin_sidebar.php` is modified **only** if the logout link needs an `id`/`data-` hook it does not already have — it already has `id="adminLogoutBtn"`, so **no PHP change is expected.**

### Components to Reuse

- `window.PMSConfirm()` / `window.confirmAction()` — and the copy conventions the five existing call sites established
- `PMSMotion.setButtonLoading()`
- `showAdminError()` / `showAdminSuccess()` for the failure paths

### Components to Create

- Four call sites. Draft copy:
  - **G2 logout** — *"Log out of the admin panel?"* · `variant: 'warning'` · "Log Out". **This step adds the first-ever JS handler on `#adminLogoutBtn`**, which means `e.preventDefault()` on a plain link and a manual `window.location = 'logout.php'` on confirm. It must degrade gracefully if JS fails — the plain `href` must remain functional.
  - **G5 password** — *"Change your admin password? You'll use the new password the next time you sign in."* · `variant: 'primary'`
  - **G7 email** — *"Change your admin email to {new}? This is the address you'll sign in with."* · `variant: 'primary'` · **email-changed diff check only**, matching Step 3's G6
  - **G8 booking times** — *"Update this booking's pickup and drop-off times? The customer is not notified of this change."* · `variant: 'warning'` — the second clause is the honest and important part; no notification mechanism exists.

### Dependencies

Step 1. **D5 resolved: Option A.** Ordered after Step 3 so one convention is settled client-side first.

### Data Requirements

**None.** No endpoint, query, or schema change. `admin_update_profile.php` and `update_booking_time.php` are untouched.

### CSS Requirements

**None.**

### Bootstrap 5 Requirements

- **G8 opens over `#editTransactionModal`** — the same nested-modal situation as Step 3's G3. Apply whatever resolution Step 3 arrived at, consistently.
- Explicit non-`danger` variants on all four.

### Responsive Requirements

- All four at **375px**; ≥44×44px buttons (the Phase 8 Step 9 standard)
- **G2 at <992px specifically** — the sidebar is an offcanvas below `lg`. Confirm the offcanvas and the confirmation modal do not fight over backdrop or scroll-lock. **This is the specific responsive risk of this step.**

### Accessibility Requirements

- Focus returns to the trigger (inherited)
- **G2:** the logout link must remain keyboard-operable and must still work with JS disabled (the `href` fallback)
- **G8:** nested-modal focus trapping, same standard as G3
- The interpolated email in G7 is announced

### Testing Requirements

- `node --check js/admin.js`; `php -l includes/admin_sidebar.php` if touched
- **G2:** logout prompts on **every one of the six sidebar-bearing admin pages** (the sidebar is a shared include); Cancel keeps the session; Confirm destroys it — verified by then hitting `admin-dashboard.php` and being redirected to `admin-login.php`
- **G2 at 375px:** works from inside the offcanvas
- **G5:** Cancel leaves the password unchanged (verify by signing in with the old one)
- **G7:** name-only edit submits with no modal; email edit prompts
- **G8:** Cancel leaves booking times unchanged in the DB
- **Phase 8's five existing confirmations still work identically** — full regression pass
- **Customer-side regression check** — `js/admin.js` is unchanged in behaviour on client pages because it is not loaded there; verify `window.AdminValidation === undefined` on all 6 client pages
- Zero new console errors on all 7 admin pages

### Acceptance Criteria

- G2, G5, G7, G8 each show a confirmation with correct copy and variant
- `#adminLogoutBtn` still functions with JavaScript disabled
- Name-only admin profile edits are not gated
- Phase 8's five confirmations are unchanged
- **Every admin write in the system now has a confirmation step** — the state the request asked for on the admin side

### Risks

- **Medium (G2).** Intercepting a plain `<a href>` on a shared include affects six pages at once. If the handler throws, logout still works via the `href` — but the confirmation is silently skipped. The graceful-degradation requirement is what makes this acceptable.
- **Medium (G2 at <992px).** Offcanvas plus modal is an untested combination in this codebase.
- **Medium (G8).** Nested modals again.
- **Low.** `js/admin.js` is loaded on all seven admin pages and every block is element-gated; new blocks must follow that convention or they will throw on pages lacking the elements.

---

## Step 5: Glassmorphism → Solid Surfaces (D6 ✅ DECIDED: Option B — flatten `.cta-banner`; D3 ✅ DECIDED: Option A — `data-bs-theme`, so replacements use `bg-body`) — ✅ IMPLEMENTED 2026-08-21

> **Status: complete.** See [CHANGELOG.md](../CHANGELOG.md)'s "System Enhancements — Step 5" entry for the full verification record, including live-measured contrast (14.40:1 CTA text, 4.69:1 and 6.78:1 in the auth modals — the latter better than predicted once Bootstrap 5.3's actual rgba-based `.text-muted` implementation was correctly composited, not the older flat `#6c757d` the analysis's manual math assumed). **One tooling-artifact finding, investigated and ruled out as a regression:** a 1590px-vs-1280px `scrollWidth` reading at desktop traced to all three feature cards' pre-existing Motion Design entrance animations being stuck in their off-screen pre-reveal state — identical regardless of which card's `.glassmorph` was replaced, and consistent with the same non-compositing session limitation Step 4 already flagged. Recorded as distinct from [BUGS.md](../docs/BUGS.md) item 24, not conflated with it. **Carried forward to Step 11:** both this and Step 4's backdrop-opacity finding need a real, composited browser check.

### Objective

Replace all seven live `.glassmorph` usages with opaque surfaces sourced from existing tokens, fixing four pieces of failing copy and five failing controls in the auth modals and eliminating an indeterminate-contrast surface over video. Resolve the CTA question honestly.

### Existing Files Involved

- `css/styles.css:517-525` (`.glassmorph`), `:527-530` (dead dark override), `:146-161` (`.search-widget-wrap`/`-card`), `:971-980` (`.cta-banner`)
- [includes/auth_modals.php](../includes/auth_modals.php)`:4, :40, :93`
- [index.php](../index.php)`:68` (search widget), `:121, :129, :137` (feature cards), `:268` (CTA)
- [about.php](../about.php)`:251` (CTA)

### Files Expected to Be Modified

`css/styles.css`, `includes/auth_modals.php`, `index.php`, and `about.php` (**only if D6 selects Option B**)

### Components to Reuse

- Bootstrap's own `.modal-content` background (`--bs-modal-bg`) — the auth-modal fix is a **class deletion**, not a new rule
- `bg-white` / `bg-body` and `rounded-3 shadow`, already present on the feature cards
- `--primary` for the CTA under D6 Option B
- `.search-widget-card`'s `max-width` and `.search-widget-wrap`'s negative-margin overlap — **both must survive**
- `data-reveal` + `--reveal-delay` on the search widget (`index.php:67`) — must survive

### Components to Create

- **No new tokens.** Every replacement is a Bootstrap default or an existing `:root` token — the analysis verified this explicitly.
- `border-radius: 18px` and `box-shadow: 0 6px 32px #0002` **relocated** from `.glassmorph` onto `.search-widget-card`; the widget genuinely needs elevation to read against the video.

### Dependencies

**D6 resolved: Option B** (flatten the CTA). **D3 resolved: Option A** — replacements are written as `bg-body`/`bg-body-tertiary`. Must precede Steps 8-10.

### Data Requirements

**None.**

### CSS Requirements

- Delete `.glassmorph` (`:517-525`) and `body.dark .glassmorph` (`:527-530`) once zero consumers remain
- Add `border-radius` + `box-shadow` to `.search-widget-card`
- Under D6 Option B: `.cta-banner { background: var(--primary); }`, delete `.cta-banner::before`, and audit the `z-2` on `index.php:269` / `about.php` which existed to sit above that pseudo-element
- **No new colours. No new tokens.**

### Bootstrap 5 Requirements

- `.modal-content` reverts to Bootstrap's own background — verify no other rule overrides it
- `bg-white` / `bg-body` per D3, plus `rounded-3 shadow` on cards
- `p-2 p-sm-4` responsive padding on the auth modals is retained

### Responsive Requirements

- **Auth modals at 320, 375, 768** — an opaque background changes perceived modal size; verify `p-2 p-sm-4` still reads correctly
- **Search widget at 320, 375, 768, 992, 1200** — `.search-widget-wrap`'s `margin-top` is `-90px` above 992 and `-60px` below (`css/styles.css:157-161`). An opaque card changes how that overlap reads against the video at *every* breakpoint. **Highest-attention item in this step.**
- **Feature cards at 375 and 768** — `col-md-4` stacking, unchanged
- **CTA at 375 and 1200** on both `index.php` and `about.php` if D6 Option B

### Accessibility Requirements

Measure and record, per surface, **after** the change:

| Pairing | Expected after |
|---|---|
| `.text-muted` `#6c757d` in the auth modals | **4.69:1** (from 1.55:1 — **fixes a WCAG AA failure**) |
| `.btn-outline-secondary` in the auth modals | **4.69:1** (from 1.55:1 — **fixes a WCAG 1.4.11 failure**) |
| Body text `#212529` in the auth modals | **15.43:1** (from 5.09:1) |
| Body text on the search widget | **determinate** for the first time (currently 1.91:1–13.06:1 depending on video frame) |
| `.text-muted` on the feature cards | **4.69:1** (from 4.52:1) |
| White on `.cta-banner` under D6 Option B | **15.9:1** (from 5.31:1 at the gradient's weakest point) |

- Verify `:focus-visible` (`css/styles.css:991-998`, `--accent-focus` `#1A7FFF`) still meets 3:1 against every new opaque surface
- Verify `data-reveal` motion on the search widget still respects the global reduced-motion gate

### Testing Requirements

- `php -l` on every modified PHP file
- **Grep for zero remaining `glassmorph` occurrences** across all `.php`, `.css`, `.js`
- All three auth modals open, submit, and error correctly from **all six client pages** (shared include — verify on more than one)
- All four `.btn-toggle-password` buttons still work and are now clearly visible
- The hero search widget submits to `vehicles.php` with correct query parameters
- Feature-card hover-lift (`css/styles.css:551-555`) still works and is still reduced-motion-gated
- CTA links still work on both pages
- **Contrast re-measured and recorded** for every pairing in the table above
- **Customer-side regression check** (`css/styles.css` is shared) **and admin-side** — confirm no admin page rendered anything through `.glassmorph` (it did not, but grep-verify)
- Zero new console errors

### Acceptance Criteria

- Zero `.glassmorph` references remain anywhere
- Every measured pairing meets WCAG 2.1 AA, with the numbers recorded in [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md)
- The search widget's contrast is determinate
- No new tokens and no new colours were introduced
- The search widget keeps its overlap, `max-width`, elevation, and reveal motion
- The CTA's disposition matches D6, with the reasoning recorded

### Risks

- **Medium.** The search widget's negative-margin overlap over a video is the most visually delicate element on the site. An opaque card will look materially different. **This is intended** (it is the fix), but it should be reviewed visually before acceptance, not just measured.
- **Medium.** `.glassmorph` currently wins over `.modal-content` on source order. Deleting it means Bootstrap's background applies — verify no *other* project rule was silently depending on the glass background.
- **Low.** Feature cards are visually near-identical before and after (`#F9FBFD` → `#FFFFFF`); expect no perceptible change beyond slightly crisper text.
- **Low (D6 Option B only).** Losing the gradient is a visual-identity change on the two highest-traffic pages, and should be shown to the user before acceptance.

---

## Step 6: Privacy Policy Page (D7 ✅ DECIDED: user supplies final text; dedicated `privacy.php`) — ✅ IMPLEMENTED 2026-08-21

> **Status: complete.** See [CHANGELOG.md](../CHANGELOG.md)'s "System Enhancements — Step 6" entry for the full content and verification record. Content was supplied directly by the user, not drafted independently. **One correction made mid-step at the user's explicit instruction:** the Data Retention section's originally-briefed "2 years after last rental" example was removed and replaced with an honest statement that no fixed retention schedule has been finalized yet — a real, open gap recorded plainly rather than papered over. **A second content gap surfaced and flagged, not filled:** the checkbox wording finalized here for Step 7 references a `[Terms of Service]` link that does not exist in this project; only the Privacy Policy link will be wired in Step 7 unless the user supplies Terms of Service content separately. **Step 7 remains gated** on backend-modification approval for `register.php`, independent of this step's completion.

### Objective

Create the privacy policy the Feature 4 checkbox will link to. **This step builds the page and the route. It does not write the policy.**

### Existing Files Involved

- [includes/client_navbar.php](../includes/client_navbar.php), [includes/client_footer.php](../includes/client_footer.php), [includes/auth_modals.php](../includes/auth_modals.php) — the six-page client shell convention
- [faq.php](../faq.php) — **the structural template.** The closest existing analogue: a static, no-DB, shell-including content page.
- `css/styles.css:142-144` `.navbar-offset` — required on non-hero content pages

### Files Expected to Be Created

- **`privacy.php`** — a static content page following `faq.php`'s exact structure

### Files Expected to Be Modified

- [includes/client_footer.php](../includes/client_footer.php) — add the link (affects all six client pages at once)

### Components to Reuse

- `faq.php`'s document structure verbatim: doctype, `<head>` CDN block, `client_navbar.php`, `.navbar-offset` main, `client_footer.php`, `auth_modals.php`, the script block
- Bootstrap `.container`, `.row`, `.col-lg-8 mx-auto`, heading hierarchy
- `.section-eyebrow` (`css/styles.css:558-562`) for the section label, matching `index.php` and `faq.php`

### Components to Create

- The page shell, with a **clearly-delimited content region** so the supplied text drops in without touching structure
- A **`data-policy-version` attribute or a PHP constant** naming the policy version — cheap now, and **required** if D8 selects a versioned option. Adding it later means retrofitting.
- A **"Last updated" date**, rendered from a hardcoded constant, **not** from `date()` — [BUGS.md](BUGS.md) item 15 makes PHP's clock 8 hours off from the database's, and a policy date must not drift.

### Dependencies

**D7 resolved** — user supplies the text; dedicated `privacy.php`. **Still blocks Step 7 until that text is delivered.**

### Data Requirements

**None.** Static page, no DB access — the same as `faq.php`.

### CSS Requirements

- **None expected.** Bootstrap typography plus the existing `.section-eyebrow`.
- If long-form prose needs a measure constraint, use Bootstrap's grid (`col-lg-8`) — **not** a custom `max-width` rule (`ui-ux-pro-max` §5 `line-length-control`; `skills/bootstrap-standards.md`).

### Bootstrap 5 Requirements

- `.container` / `.row` / `.col-lg-8 mx-auto` for readable measure
- Sequential heading hierarchy `h1 → h2 → h3`, no skipped levels
- `.navbar-offset` on the main region (the fixed navbar clearance convention)

### Responsive Requirements

- **320, 375, 768, 992, 1200** — long-form prose is the primary content; verify no horizontal overflow and comfortable line length at every width
- Footer link does not break footer wrapping at 320px

### Accessibility Requirements

- One `<h1>`; sequential heading levels throughout
- Body text contrast ≥4.5:1 — **note Additional Finding A3:** avoid bare `.text-muted` on `--background` here, since it measures 4.48:1 and fails. Use default body colour for prose.
- The footer link has a descriptive accessible name
- Keyboard-reachable from every page via the footer
- If the policy has numbered sections, in-page anchors with visible focus

### Testing Requirements

- `php -l privacy.php` and `php -l includes/client_footer.php`
- The page renders correctly and the navbar/footer/auth-modals all work on it
- The footer link appears and works on **all six** client pages
- Login/signup modals open from `privacy.php` (it includes `auth_modals.php`)
- Verified at all five breakpoints
- Verified in a screen reader for heading structure
- Zero console errors

### Acceptance Criteria

- `privacy.php` exists, is linked from the footer of all six client pages, and matches the established client-page shell
- The policy version and "Last updated" date are present and are not PHP-clock-derived
- The content region is structured to receive the supplied text
- [FEATURES.md](FEATURES.md) records the page

### Risks

- **High (non-engineering).** If D7(a)(ii) is chosen — placeholder content — a page containing `[TO BE SUPPLIED]` markers **must not** be linked from a live consent checkbox. The plan's ordering assumes real text exists before Step 7 ships.
- **Low (engineering).** The page itself is the lowest-risk item in the initiative: static, no DB, no JS, following an existing template exactly.

> **Note.** This step introduces no backend logic and touches no endpoint. Its risk is entirely in the content, not the code.

---

## Step 7: Signup Consent Checkbox (D8 ✅ DECIDED: Option A, non-persisted · **BACKEND MODIFICATION — APPROVED, IMPLEMENTED**) — ✅ IMPLEMENTED 2026-08-21

> **Status: complete.** Backend-modification approval for `register.php` was given explicitly. See [CHANGELOG.md](../CHANGELOG.md)'s "System Enhancements — Step 7" entry for the full verification record, including a direct HTTP-level test proving the server rejects an unconsented registration even when the UI is bypassed entirely. **One real implementation constraint found and resolved during the build:** the existing `AuthValidation` rule pipeline only ever passes a field's string `.val()` into rule test functions, but a checkbox's `.val()` carries no checked/unchecked information — worked around with a rule builder (`rules.checked($input, message)`) that closes over the input directly rather than modifying the shared pipeline. **One honest trade-off recorded, not silently shipped:** the checkbox glyph is 24px, not a literal 44×44px — chosen because the adjacent multi-line label is itself fully clickable, giving a large effective tap area without an oversized glyph; documented rather than either quietly under-sizing it or overclaiming compliance. **A second content gap carried from Step 6:** the user's checkbox wording also names a `[Terms of Service]` link that doesn't exist in this project — only Privacy Policy was wired.

### Objective

Add a required, unticked-by-default privacy-consent checkbox to the signup form, enforced **both** client-side and server-side.

### Existing Files Involved

- [includes/auth_modals.php](../includes/auth_modals.php)`:38-88` — `#signupModal` / `#signupForm`; insertion point between `:78` and `:79`
- [js/app.js](../js/app.js) — `AuthValidation` `:1038-1146` (`firstError` `:1069`, `clearForm` `:1062`, `rules` `:1126-1145`), signup submit `:1241-1301`, `registerUser()` `:966`
- [register.php](../register.php) — payload read `:19-21`, validation `:24-28`, inserts `:109/:131/:138`, the runtime `ALTER TABLE` retry `:107-136`
- `privacy.php` (from Step 6)
- [PMS.postman_collection.json](../PMS.postman_collection.json), [API.md](API.md)

### Files Expected to Be Modified

`includes/auth_modals.php`, `js/app.js`, **`register.php`**, [API.md](API.md), and — under D8 Options B/C — a new `db_migrations/` script

### Components to Reuse

- Bootstrap's `.form-check` / `.form-check-input` / `.form-check-label` / `.invalid-feedback` — **no custom CSS**
- `AuthValidation`'s `<fieldId>Feedback` convention and its `.is-invalid` handling, which **work unchanged** on `.form-check-input` because Bootstrap styles it natively
- `register.php`'s existing `http_response_code(400)` + `{'error': …}` response shape

### Components to Create

- **`#signupPrivacyConsent`** — `type="checkbox"`, `required`, **unticked by default**, `aria-describedby="signupPrivacyConsentFeedback"`, placed **between the confirm-password field and the submit button** (consent must be the last thing read before the action it authorises)
- Label text containing a link to `privacy.php` with **`target="_blank" rel="noopener"`** — a **hard requirement**, not a nicety: the form is inside a Bootstrap `.modal`, and same-tab navigation destroys everything typed
- **`AuthValidation.rules.checked(message)`** — `{ test: () => $input.is(':checked'), message }`. Necessary because every existing rule tests `$input.val()`, which returns `"on"` on a checkbox regardless of state, so `rules.required` would pass on an unticked box.
- **`clearForm()` selector extension** — `.form-control, .form-check-input`. A checkbox is not `.form-control` and would never be cleared between submits.
- **Server-side guard in `register.php`** — read the consent field and reject with 400 if absent/falsy, alongside the existing `:24` validation
- Under D8 B/C: a `db_migrations/` SQL script, and a write using **MySQL's `NOW()`**, never a PHP-computed timestamp

### Dependencies

**Step 6 must be complete** — the checkbox must link to a real, populated policy. **D8 resolved: Option A.** **Explicit backend approval still required for the `register.php` change.**

### Data Requirements

- **D8 Option A:** none.
- **D8 Option B/C:** a new column or table via `db_migrations/`. **Never a runtime `ALTER TABLE`** — `register.php:107-136` already shows what that pattern costs. **Never a PHP-computed timestamp** — [BUGS.md](BUGS.md) item 15.

### CSS Requirements

**None.** Bootstrap's form-check pattern covers it entirely.

### Bootstrap 5 Requirements

- `.form-check` + `.mb-3`, matching the surrounding fields' spacing rhythm
- `.form-check-input.is-invalid` + sibling `.invalid-feedback` — native Bootstrap, no custom rule
- The `required` attribute is present **in addition to** the JS gate, so native validation is not weakened

### Responsive Requirements

- **320 and 375** — the label wraps to 2-3 lines. The checkbox must stay **top-aligned to the first line**, not vertically centred against a multi-line label.
- The checkbox's tap target must be **≥44×44px** — Bootstrap's default `.form-check-input` is 1em (~16px), so padding or an expanded hit area is required. **This is a real, easily-missed requirement.**
- The policy link must be tappable without hitting the checkbox, and vice versa (≥8px separation, `ui-ux-pro-max` §2 `touch-spacing`)

### Accessibility Requirements

- `<label for="signupPrivacyConsent">` correctly associated; **the link inside the label must not swallow the label's click target** — clicking the text (not the link) must still toggle the checkbox
- `aria-describedby` → the feedback element; `aria-invalid` toggled by `AuthValidation`
- The error is announced (the existing `#signupError` alert has `role="alert"`)
- Keyboard: Space toggles the checkbox; Tab reaches the link; Enter follows it in a new tab
- Focus moves to the checkbox when it is the first invalid field (`validateAll()` already does this)

### Testing Requirements

- `php -l register.php` and `php -l includes/auth_modals.php`; `node --check js/app.js`
- **The checkbox is unticked on every modal open**, including after a failed submit and after closing and reopening
- Submitting unticked shows an inline error, focuses the checkbox, and **sends no network request**
- Submitting ticked registers successfully
- **The server-side gate is the critical test:** POST directly to `register.php` (curl/Postman) **without** the consent field → must return **400**. This is what makes it a compliance control rather than a UI decoration.
- The policy link opens `privacy.php` in a new tab **and the signup form retains everything typed**
- Clicking the label text (not the link) toggles the checkbox
- **Regression:** all three auth modals still validate correctly — the `clearForm()` selector change touches `#loginForm` and the forgot-password forms too
- Works from **all six client pages** (shared include)
- Under D8 B/C: the migration applies cleanly; the stored timestamp matches Manila wall-clock time, not UTC — **the direct test for [BUGS.md](BUGS.md) item 15 having been routed around**
- Verified at 320 and 375; tap target measured
- Zero new console errors

### Acceptance Criteria

- The signup form cannot be submitted unticked, client-side **or** server-side
- The checkbox is unticked by default, always
- The policy link opens in a new tab and preserves form state
- `AuthValidation` gained `rules.checked` and an extended `clearForm()` selector, with no regression to the other three auth forms
- Under D8 B/C: consent is recorded with a MySQL-side timestamp via a `db_migrations/` script
- [API.md](API.md) records `register.php`'s new required field **as a breaking change** for the mobile client
- [FEATURES.md](FEATURES.md) records the feature; under D8 Option A, [FEATURES.md](FEATURES.md) also records Option C's versioned consent record as identified future work

> **Note — backend modification flag, still live.** D8 is decided (Option A, non-persisted), but this step still **requires explicit, separate approval before its prompt can be generated**, because it **modifies `register.php`** regardless of D8's outcome — crossing [CLAUDE.md](../CLAUDE.md)'s rule that *"Backend modifications should only be suggested unless explicitly requested"*, the same gate Phase 7's Step 4 applied to `update_profile.php` and Phase 8's Step 8 applied to `admin_update_profile.php`. Under D8 Option A there is **no** schema change, so this is a single approval, not the layered one an Option B/C choice would have required.
>
> **And a non-engineering gate that remains regardless.** This step's correctness depends on Step 6's policy being real and populated. **The engineering can be verified; the compliance cannot be verified by this plan.**

---

## Step 8: Dark Mode Foundation (D1 ✅ Option C, D2 ✅ Option A, D3 ✅ Option A — all DECIDED) — ✅ IMPLEMENTED 2026-08-21 (both 8a and 8b)

> **Status: complete.** Split at the user's request into 8a and 8b, both now done. See [CHANGELOG.md](../CHANGELOG.md)'s "System Enhancements — Step 8a" and "Step 8b" entries for the full record. **8a:** both abandoned dark-mode scaffolds deleted, the 122-instance utility-class migration completed and verified across all 13 pages, zero light-mode visual regressions (one named exception — `table-light`'s removal — turned out to be a consistency improvement, not a trade-off, since it was already an inconsistent minority pattern across the admin tables). **8b:** the dark token set (nine tokens, tonal variants not inversions), both toggle buttons, `js/theme.js`, the no-flash inline script on all 13 pages, and `localStorage` persistence — all verified live, including under a real OS-level dark-mode signal from the test environment itself. **One real, unresolved design conflict found and documented rather than hidden:** `--primary` is used both as text colour and as a white-text-bearing fill; a single dark-mode value cannot serve both roles (white-on-dark-`--primary` measures ~2.06:1, failing AA). Recorded directly in the CSS as a comment assigning the fix to Steps 9-10, which must give each fill-role consumer its own override. **This step does not claim any page's dark-mode contrast is verified — that is Steps 9 and 10's job, starting next.**

### Objective

Establish the theming mechanism, the dark token set, both toggles, and persistence — **without yet claiming any page is contrast-correct in dark mode.** Steps 9 and 10 do that. This step's success criterion is that the *machinery* works and that **light mode is byte-identical to before.**

### Existing Files Involved

- `css/styles.css` — `:root` `:3-46`; **Scaffold A** `.dark` `:48-83`; **Scaffold B** `body.dark` `:319-334, 527-530, 576-596`; dead `.btn-darkmode-toggle` `:601-604`; reduced-motion gate `:112-139`; `:focus-visible` `:991-998`
- [faq.php](../faq.php)`:104-106` — the orphaned `toggleDarkMode()`
- [includes/client_navbar.php](../includes/client_navbar.php)`:19` — beside `#navbarAuthArea`
- [includes/admin_topbar.php](../includes/admin_topbar.php)`:27-32` — the right-hand group, beside "Hello, {name}!"
- All 13 pages' `<head>` blocks
- [js/app.js](../js/app.js)`:418, :1568` — `renderNavbarAuth()`, **read-only:** the toggle must **not** live inside its output, or it disappears for logged-out visitors

### Files Expected to Be Modified

`css/styles.css`, `faq.php`, `includes/client_navbar.php`, `includes/admin_topbar.php`, all 13 pages (`<head>` script + `<html>` attribute), `js/app.js` and `js/admin.js` (toggle handlers), and — under D3 Option A — all 13 pages for the 122 utility-class swaps

### Files Expected to Be Created

- **`js/theme.js`** — the toggle handler and persistence, kept out of both shells' main files so it can be loaded on all 13 pages including `receipt.php`

### Components to Reuse

- The nine existing `:root` colour tokens as the light half — **unchanged**
- Bootstrap 5.3's `data-bs-theme` system (D3 Option A) — already loaded on all 13 pages, currently unused
- The existing reduced-motion gate pattern (`:551-555`, `:965-969`) for any theme-transition animation
- `--accent-focus`'s documented rationale as the worked example for re-deriving focus-ring contrast in dark

### Components to Create

- **A dark counterpart for all nine colour tokens** — desaturated tonal variants, **not inversions** (`ui-ux-pro-max` §6 `color-dark-mode`). `--primary: #0F2A4D` becomes a *lighter, desaturated navy*, not a pale cream.
- **A dark `--accent-focus`**, re-derived by the same calculation its `css/styles.css:10-12` comment documents. The light value is not transferable.
- **The no-flash inline `<head>` script** — reads `localStorage`, applies `data-bs-theme` to `<html>` before first paint. **Must be inline and must precede the stylesheet link**; an external file would still flash.
- **Two toggle controls** — static markup in `client_navbar.php` (beside `#navbarAuthArea`) and in `admin_topbar.php`'s right-hand group (**not** via `$topbarActions`, which a caller could forget to set). Same relative position on both sides.
- **`js/theme.js`** — toggle handling, `localStorage` write, and (under D1 Option A) a `matchMedia('(prefers-color-scheme: dark)')` change listener
- Under D3 Option A: **the 122 utility-class swaps** — `bg-white`(33)→`bg-body`, `text-muted`(72)→`text-body-secondary`, `text-dark`(8)→`text-body`, `bg-light`(7)→`bg-body-tertiary`, `table-light`(2)→removed

### Components to Delete

- **Scaffold A** — the entire `.dark { … }` oklch block, `css/styles.css:48-83` (34 declarations, zero consumers, token vocabulary that does not match this project)
- **Scaffold B** — all `body.dark` rules at `:319-334`, `:527-530`, `:576-596`
- **`.btn-darkmode-toggle`** `:601-604` (zero consumers)
- **`faq.php:104-106`** — the orphaned inline script (Additional Finding A2)

### Dependencies

**D1, D2 and D3 must all be answered.** Depends on Step 5 (four fewer surfaces to theme). Blocks Steps 9 and 10.

### Data Requirements

- **D2 Option A (recommended):** **none.**
- **D2 Option B/C:** a schema change on `users` **and** `admins`, plus modifications to `update_profile.php` and `admin_update_profile.php` — **a flagged backend modification requiring separate approval.**

### CSS Requirements

- Delete all four dead artefacts above **before** adding anything, so there is no ambiguity about which rules are live
- Add the dark token block, scoped per D3's mechanism
- Convert the remaining 27 hex and 14 `rgba()` literals to tokens **where they are theme-dependent** — a shadow's `rgba(0,0,0,.1)` may legitimately stay literal; `#334155` on a navbar link may not
- Any theme-transition animation goes inside `@media (prefers-reduced-motion: no-preference)`
- **No new colours beyond the nine dark counterparts**, each justified as a tonal step of its light partner

### Bootstrap 5 Requirements

- `data-bs-theme` on `<html>` (D3 Option A)
- Theme-aware utilities replacing fixed-value ones — **every swap a no-op in light mode**
- The toggle is a `.btn` with an `<i>` icon and an `aria-label`; **not** an emoji (`ui-ux-pro-max` "No Emoji as Structural Icons"). Font Awesome 6.4.2 is loaded on all 13 pages.

### Responsive Requirements

- **Client toggle at 320 and 375** — the navbar collapses at `md`. The toggle must remain reachable **inside** the collapsed `#navbarMenu`, and must not overflow the collapsed panel.
- **Admin toggle at 768 and 992** — the sidebar switches from offcanvas to persistent at `lg`, and the topbar's right-hand group is tightest just below that. The "Hello, {name}!" span is already `d-none d-sm-inline`; the toggle must not push it or the hamburger out of the row.
- Toggle ≥44×44px at every width

### Accessibility Requirements

- The toggle is a real `<button>` with `aria-label` and `aria-pressed` (two-state) or a labelled group (three-state, D1 Option A)
- Its state change is announced, not signalled by icon alone (`ui-ux-pro-max` §1 `color-not-only`)
- **Dark `--accent-focus` must meet 3:1 against the dark background** — re-derived, not assumed
- Reduced-motion respected on any theme transition
- **No page-level contrast claim is made in this step.** Steps 9 and 10 own that.

### Testing Requirements

- `php -l` on every modified PHP file; `node --check js/theme.js`, `js/app.js`, `js/admin.js`
- **The primary test: light mode is pixel-identical to before this step on all 13 pages.** Under D3 Option A every utility swap is a light-mode no-op, so any visible light-mode difference is a defect. Compare before/after screenshots page by page.
- **No flash of light theme** on hard reload with dark selected — on all 13 pages, including `receipt.php`
- The toggle works on **all 13 pages** and the choice survives navigation between them
- Under D1 Option C: a fresh browser profile with OS dark set loads dark; after one manual toggle the choice is pinned
- Under D1 Option A: changing the OS theme with "System" selected updates the page live
- **Grep confirms zero `body.dark` and zero bare `.dark` selectors remain**; zero `toggleDarkMode` references
- `localStorage.getItem('pms-theme')` returns the expected value
- Keyboard-operable toggle on both shells
- Zero new console errors on all 13 pages

### Acceptance Criteria

- Both abandoned scaffolds, the dead toggle class, and the orphaned `faq.php` script are gone
- A complete dark token set exists for all nine colour tokens, each a justified tonal variant
- A toggle exists in both shells, in the same relative position, reachable at every breakpoint
- The preference persists per D2 and defaults per D1
- **Light mode is unchanged**
- Dark mode *renders* — with no claim yet that it is contrast-correct
- [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) records the dark token set

### Risks

- **High (breadth).** Under D3 Option A this touches all 13 pages plus the shared stylesheet. It is the largest step in the initiative. **Mitigation:** every utility swap is a light-mode no-op, making it verifiable page-by-page against screenshots before any dark palette is even applied. If it proves too large in practice, the natural split is **(8a)** delete the dead scaffolds + the utility migration (all light-mode no-ops), **(8b)** dark tokens + toggle + persistence.
- **Medium.** The inline no-flash script must be inline, must precede the stylesheet, and must be on all 13 pages. Missing one produces a flash only on that page, only in dark mode — easy to miss.
- **Medium.** Deleting 40+ `body.dark` rules requires certainty they are dead. Grep-verified during analysis; **re-verify at implementation time before deletion**, per the standard Phase 8 Step 6 applied to its dead-handler removal.
- **Medium.** The admin topbar's right-hand group is genuinely tight at 768-992px.
- **Low.** `receipt.php` loads a different script set and has a `@media print` block (`css/styles.css:827`) — verify printing is unaffected in both themes.

> **Note — no backend modification.** D2 is decided as Option A (`localStorage` only), so this step touches **no** PHP logic and requires no backend approval.

---

## Step 9: Dark Mode — Client Sweep — ✅ IMPLEMENTED 2026-08-21

> **Status: complete.** See [CHANGELOG.md](../CHANGELOG.md)'s "System Enhancements — Step 9" entry for the full record: an automated live contrast scanner (not manual sampling) found and fixed eight distinct issues across all six client pages, re-run to zero findings after each fix. **The highest-impact single fix was global and unscoped** — a pre-existing `.modal-header` rule was forcing every modal's header light regardless of theme, on every page including the admin side, which Step 10 inherits already fixed. **A real gap in Step 8a's own migration was found and closed**: that step's `sed` sweep only covered `.php` files, missing four old utility-class instances sitting in JS template literals (`js/app.js`, `js/printer.js`). **One functional bug was found and correctly left unfixed**: `receipt.php` has no `#receiptModal` element, so the project's shared print CSS would render it blank on Ctrl+P — unrelated to dark mode, logged as [BUGS.md](../docs/BUGS.md) item 26 rather than fixed under this step's contrast-only mandate. Light mode re-verified completely unaffected after every fix, including the `!important`-scoped ones.

### Objective

Audit and correct every colour pairing on the six customer-facing pages in dark mode, against WCAG 2.1 AA **independently** — not by assuming light-mode values transfer.

### Existing Files Involved

`index.php`, `vehicles.php`, `transactions.php`, `about.php`, `faq.php`, `receipt.php`; `includes/client_navbar.php`, `client_footer.php`, `auth_modals.php`; `css/styles.css`; `js/app.js` (any JS-injected markup with colour classes — `renderNavbarAuth()` `:418`/`:1568`, the booking-preview render `:125`, alert rendering)

### Files Expected to Be Modified

Whichever of the above fail the audit. Expected: `css/styles.css` and the pages with the highest fixed-value density — `about.php` (12 `bg-white`, 15 `text-muted`), `index.php` (4/12), `transactions.php` (6/11).

### Components to Reuse

Step 8's dark token set. **This step must not invent new tokens** — if a pairing fails, either the token value is wrong (fix it in Step 8's block, and re-check every consumer) or the markup uses a non-theme-aware class (fix the markup).

### Components to Create

**None expected.** If a genuinely new token proves necessary, it is a Step 8 amendment with its own justification, not a Step 9 addition.

### Dependencies

Step 8. Covers Step 5's new solid surfaces and Step 7's consent checkbox.

### Data Requirements

**None.**

### CSS Requirements

- Corrections only, all within Step 8's token vocabulary
- Any remaining hardcoded hex found during the sweep is converted or justified as theme-independent
- **No new colours**

### Bootstrap 5 Requirements

- Verify Bootstrap's own dark palettes for cards, modals, accordions, carousels, dropdowns, and form controls read correctly against this project's tokens
- **`.badge bg-info` and similar semantic badges are a known hazard** — [BUGS.md](BUGS.md) item 22 was exactly this class of failure in light mode on the admin side. Check every badge on `transactions.php` and `vehicles.php` in dark.

### Responsive Requirements

All six pages at **320, 375, 768, 992, 1200**, in dark mode. Specific attention:
- `index.php` hero — video plus the (now opaque) search widget in dark
- `vehicles.php` — 11 cards, filter row, booking modal
- `transactions.php` — 3 cards, the profile section, both bespoke confirmation modals
- `receipt.php` — **and its print output**, which must remain light regardless of theme

### Accessibility Requirements

**Measure and record every pairing.** Minimum coverage:

| Surface | Pairings to measure |
|---|---|
| Body text on the dark page background | ≥4.5:1 |
| Secondary text (`text-body-secondary`) on page and on card | ≥4.5:1 |
| `.navbar .nav-link` and its `.active` pill state | ≥4.5:1 |
| `--primary`/`--secondary` buttons, text on fill | ≥4.5:1 |
| `.btn-outline-*` borders | ≥3:1 (WCAG 1.4.11) |
| `:focus-visible` ring (`--accent-focus`) | ≥3:1 |
| `--border` hairlines | visible (the light `rgba(15,42,77,.12)` is invisible on dark) |
| Every badge, alert, and `.invalid-feedback` | ≥4.5:1 |
| `.cta-banner` white text | ≥4.5:1 |
| Auth modals, incl. the Step 7 consent checkbox and its link | ≥4.5:1 |
| Vehicle card price and availability text | ≥4.5:1 |

Plus: images and the hero video must not be inverted or filtered; `.js-fade-on-load` and `data-reveal` motion unaffected; disabled states remain distinguishable in **both** themes (`ui-ux-pro-max` "State contrast parity").

### Testing Requirements

- Every measured pairing recorded in a table in [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md), light **and** dark
- **Full functional regression in dark mode**, not just visual: login, signup, forgot-password, booking end-to-end, cancel booking, return early, profile update, password change, contact form, filter, pagination, receipt print
- **Light mode re-verified unchanged** after every correction
- All six pages at all five breakpoints in dark
- **`receipt.php` print output verified** in both themes
- Zero new console errors

### Acceptance Criteria

- Every measured pairing on all six client pages meets WCAG 2.1 AA in dark mode, with numbers recorded
- No new tokens or colours were introduced
- Light mode is unchanged
- Every client feature works in dark mode
- Print output is unaffected by theme

### Risks

- **High (volume).** This is a measurement-heavy step across six pages and five breakpoints. **Mitigation:** it is mechanical and checkable — the risk is time, not correctness.
- **Medium.** JS-injected markup (`renderNavbarAuth()`, booking-preview render, alert rendering) is easy to miss in a static sweep. **Audit it by triggering it, not by reading it.**
- **Medium.** `index.php`'s hero video is uncontrolled content; overlaid text must work in both themes against arbitrary frames.
- **Low.** `receipt.php` print already has its own `@media print` block; verify it wins over dark tokens.

---

## Step 10: Dark Mode — Admin Sweep

> ✅ **IMPLEMENTED 2026-08-21.** All seven admin pages audited live with the same automated contrast scanner used in Step 9, including DataTables' injected controls on the three pages that use them, the shared confirmation modal, and forced `.is-invalid`/`.invalid-feedback` states. Two real findings, both fixed: an unscoped `.table th` rule forcing light headers regardless of theme, and `.progress-bar`'s plain Bootstrap contextual fills not dark-adjusting (reusing the same `-text-emphasis` tokens Step 9 established for `.text-success`/`.text-danger`). A self-inflicted CSS-comment bug (an accidental `*/` sequence that silently dropped the `.progress-bar.bg-primary` rule from the parsed stylesheet) was found and fixed during this step's own verification. A minor token-consistency fix was also made: `.table-responsive`'s mobile border, hardcoded to `#dee2e6`, now resolves to `var(--border)` in dark mode (it already passed contrast at 11.85:1; this was a consistency fix, not an accessibility fix). One pre-existing, non-dark-mode-specific gap was found and deliberately left unfixed: `--sidebar-bg` never applies at ≥992px in *either* theme, due to Bootstrap's `.offcanvas-lg` `background-color: transparent !important` reset (documented as an addendum to [BUGS.md](BUGS.md) item 14, which had already investigated this exact Bootstrap behavior). Zero contrast findings on all seven pages after fixes; light mode re-verified unaffected; client-side pages re-verified unaffected (shared stylesheet). Full details in [CHANGELOG.md](../CHANGELOG.md).

### Objective

The same audit for the seven admin pages, with attention to the surfaces Phase 8 built.

### Existing Files Involved

`admin-dashboard.php`, `admin_users.php`, `admin_vehicles.php`, `admin_vouchers.php`, `view-all-data.php`, `admin_settings.php`, `admin-login.php`; `includes/admin_sidebar.php`, `admin_topbar.php`; `css/styles.css` (`#adminSidebar` `:336-345`, `--sidebar-bg`, `--sidebar-hover`, `.admin-topbar`, `.metric-card`, `.business-overview` `:961-969`); `js/admin.js` (injected markup)

### Files Expected to Be Modified

Whichever fail. Expected: `css/styles.css` (the admin-specific blocks) and `admin-dashboard.php` (6 `bg-white`, 10 `text-muted`, 22 cards, 2 tables — the densest page in the project).

### Components to Reuse

Step 8's dark token set, including `--sidebar-bg` and `--sidebar-hover`, which exist specifically for this shell.

### Dependencies

Step 8. Ordered after Step 9 so the client palette is settled first — the two shells share every token.

### Data Requirements

**None.**

### CSS Requirements

Corrections only, within Step 8's vocabulary. `--sidebar-bg: #ffffff` needs a dark counterpart that is **distinguishable from** the page background — a sidebar that merges into the body destroys the layout's structure.

### Bootstrap 5 Requirements

- **DataTables' Bootstrap 5 skin is the highest-risk surface here** — it is initialized on four admin tables and injects its own controls (search input, length select, pagination, "Showing X of Y" info text). Verify each in dark. **DataTables 1.11.5 predates Bootstrap 5.3's colour modes**, so its injected markup may need explicit treatment.
- `.offcanvas` in dark below `lg`
- Every status badge on `view-all-data.php` and `admin-dashboard.php` — [BUGS.md](BUGS.md) item 22 precedent
- `.progress` / `.list-group` in the Business Overview panel (Phase 8 Step 7's Option A output)

### Responsive Requirements

All seven pages at **320, 375, 768, 992, 1200** in dark. Specific attention:
- **992** — the sidebar offcanvas↔persistent transition, in dark, with the toggle present
- **375** — admin tables horizontally scrolling in dark; the `.table-responsive` border rule at `css/styles.css:983-988` uses a hardcoded `#dee2e6` that will need a dark counterpart
- `admin-login.php` — a standalone page with `bg-light` on `<body>`

### Accessibility Requirements

Same measurement discipline as Step 9, plus admin-specific:

| Surface | Requirement |
|---|---|
| Sidebar link, hover, and `.active` states | ≥4.5:1, and `--sidebar-bg` distinguishable from the page background |
| `.admin-topbar` on `bg-white` + `border-bottom` | ≥4.5:1, border visible |
| `.metric-card` values and labels (Phase 8 Step 3) | ≥4.5:1 |
| Table headers, body text, zebra striping, `table-light` | ≥4.5:1 |
| DataTables injected controls | ≥4.5:1 |
| Every status badge | ≥4.5:1 |
| `.progress` bars in Business Overview | ≥3:1 against their track |
| All seven modals' `.is-invalid` / `.invalid-feedback` | ≥4.5:1 |
| The Step 1/4 confirmation modal | ≥4.5:1 |
| Row-action buttons | ≥3:1 borders, ≥44×44px ([BUGS.md](BUGS.md) item 25 standard) |

### Testing Requirements

- Every pairing recorded in [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md)
- **Full admin functional regression in dark:** admin login, all four DataTables, every CRUD operation on users/vehicles/vouchers/transactions, booking confirm, edit booking times, account settings, password change, logout — **including every confirmation modal from Steps 1 and 4**
- All seven pages at all five breakpoints in dark
- **Client-side regression re-run** — `css/styles.css` is shared, so admin corrections can regress client pages
- Zero new console errors on all 13 pages

### Acceptance Criteria

- Every measured pairing on all seven admin pages meets WCAG 2.1 AA in dark, recorded
- DataTables reads correctly in dark on all four tables
- The sidebar remains structurally distinguishable in dark
- No new tokens or colours
- Light mode unchanged on both sides
- Every admin feature works in dark

### Risks

- **High.** `admin-dashboard.php` is the densest page in the project (22 cards, 2 tables, the metric row, the Business Overview panel).
- **High.** **DataTables 1.11.5 predates Bootstrap 5.3's colour modes.** Its injected controls may need explicit dark rules, and there is no precedent in this codebase. **This is the most likely source of unplanned work in the whole initiative.**
- **Medium.** Admin corrections to a shared stylesheet can regress client pages. Step 9's measurements must be spot-re-verified after this step, not assumed to hold.
- **Medium.** `--sidebar-bg` in dark is a design judgement, not a calculation — too close to the background and the layout loses its structure; too far and it reads as a different surface than intended.

---

## Step 11: Responsive & Accessibility Pass

> ✅ **IMPLEMENTED 2026-08-21.** All 14 pages checked for structural overflow at 320px and 992px in dark mode; both nested-modal cases (G3, G8) tested live via real interaction (filled booking form, real admin edit-times save) and both trap and return focus correctly. One real, in-scope fix: both theme toggles were hardcoded to 38×38px, below the 44×44px standard — corrected to 44×44px and re-verified, including inside the mobile navbar's collapsed drawer. The consent checkbox's touch target, confirmation-modal buttons, `privacy.php`'s heading structure, the toggle's `aria-pressed`/`aria-label` state changes, the consent error's `aria-describedby` wiring, and the pre-existing global reduced-motion gate were all verified passing with no fix needed. Three findings confirmed outside this step's bounded scope (a pre-existing 4px Bootstrap grid overflow, Bootstrap's sitewide-default 32px `.btn-close`, and `receipt.php`'s unstyled missing-parameter fallback) were logged to [BUGS.md](BUGS.md) items 27-29 rather than fixed. Full details in [CHANGELOG.md](../CHANGELOG.md).

### Objective

One consolidated sweep across everything this initiative changed, at every breakpoint, in both themes, with a keyboard and a screen reader.

### Scope — explicitly bounded

**In scope:** only what Steps 1-10 changed. Per the [SYSTEM_ENHANCEMENTS_ANALYSIS.md](SYSTEM_ENHANCEMENTS_ANALYSIS.md) §7 matrix:

| From | Pages | Breakpoints of specific concern |
|---|---|---|
| Steps 1, 3, 4 (confirmations) | `vehicles.php`, `transactions.php`, 7 admin pages | 320, 375 — `.modal-sm`, ≥44×44px buttons, nested-modal focus trapping |
| Step 2 (preview) | `vehicles.php` | 375, 768 — button-row wrap; taller booking modal |
| Step 5 (solid surfaces) | `index.php`, 6 client pages (auth modals) | 320, 375, 992 — the search widget's `-90px`/`-60px` overlap |
| Steps 6, 7 (privacy + consent) | `privacy.php`, 6 client pages | 320, 375 — label wrap, checkbox top-alignment, ≥44px tap target |
| Steps 8-10 (dark mode) | **All 13 pages + `privacy.php`** | 320, 375, 768, 992, 1200 — toggle reachability in the collapsed navbar and the tight admin topbar |

**Explicitly out of scope:** [BUGS.md](BUGS.md) item 24 (`index.php` horizontal overflow at desktop, flagged for a separate task) and the upcoming full-site Responsive Review phase. **This pass does not absorb either.** Anything found outside this initiative's changes is logged in [BUGS.md](BUGS.md), not fixed here.

### Files Expected to Be Modified

Whichever fail. Expected to be small — each prior step carried its own responsive and accessibility requirements.

### Testing Requirements

- **Responsive:** every page in the scope table at 320 / 375 / 768 / 992 / 1200, in **both** themes. No horizontal scroll; no overflow; no overlap.
- **Touch targets:** every control this initiative added or moved measured at ≥44×44px, with ≥8px separation. Specifically: both theme toggles, the consent checkbox, and every confirmation-modal button.
- **Keyboard-only:** a full walkthrough of all eight new confirmations (including the two nested cases), both toggles, the consent checkbox and its link, and `privacy.php`. Focus must be visible at every stop and must return correctly from every modal.
- **Screen reader:** the eight confirmations announce title and body; the toggles announce state; the consent error announces; `privacy.php`'s heading structure reads correctly.
- **Reduced motion:** OS reduced-motion enabled — verify the theme transition, `.js-booking-reveal` (if retained), `data-reveal`, and hover-lifts all honour the global gate at `css/styles.css:112-139`, and that the spinner exception still works.
- **Contrast spot-re-verification** of the highest-risk pairings from Steps 5, 9 and 10, after all corrections have landed.
- **Zero new console errors on all 14 pages, in both themes.**

### Acceptance Criteria

- Everything this initiative changed works at all five breakpoints in both themes
- Every new control meets the touch-target and keyboard standards
- Every new modal traps and returns focus correctly, including the two nested cases
- Reduced-motion is honoured
- Nothing outside this initiative's scope was modified; anything found is logged in [BUGS.md](BUGS.md)

### Risks

- **Medium.** The two nested-modal cases (Step 3's G3, Step 4's G8) are the most likely to fail keyboard testing, and they are the hardest to fix late.
- **Low.** Everything else has been verified once already at its own step; this is confirmation, not discovery.

---

## Step 12: Final Review & Documentation

### Objective

Verify the initiative end-to-end and bring every affected document into an accurate state.

### Documentation Requirements

| Document | Update |
|---|---|
| **[CHANGELOG.md](../CHANGELOG.md)** | One entry per implemented step, matching the format of Phase 8's ten entries |
| **[DESIGN_SYSTEM.md](DESIGN_SYSTEM.md)** | The dark token set; the full light-and-dark contrast measurement tables from Steps 5, 9 and 10; removal of `.glassmorph` from every page's "Bootstrap Components" line (currently listed on Home as *"cards (`card`, glassmorphism variant)"*); `privacy.php` added as a new page section |
| **[COMPONENT_LIBRARY.md](COMPONENT_LIBRARY.md)** | The shared confirmation modal; the theme toggle; the consent checkbox pattern; `.glassmorph` removed |
| **[FEATURES.md](FEATURES.md)** | Dark mode; confirmations; the privacy policy page and consent checkbox; under D8 Option A, the versioned consent record recorded as identified future work — the shape Phase 8's Step 8 used |
| **[BUGS.md](BUGS.md)** | **Item 3** marked resolved **and its mechanism corrected** (Finding A6 — it is not a thrown `ReferenceError`). **New entries** for the abandoned dark-mode scaffolds (A1), the site-wide `.text-muted` 4.48:1 failure (A3), the dead `vehicles.php` preview handler (A5), and `logoutUser()`'s await-ordering (A8) — each logged whether or not it was fixed |
| **[MOTION_DESIGN_ANALYSIS.md](MOTION_DESIGN_ANALYSIS.md)** | The four `#btnPreview`/`#bookingPreview` checklist items (`:961, :992, :993, :994`) marked **superseded with the reason**, and the Priority Matrix rows annotated |
| **[API.md](API.md)** | `register.php`'s new required consent field, flagged as a **breaking change** for the mobile client; cross-reference [PMS.postman_collection.json](../PMS.postman_collection.json) |
| **[PROJECT_AUDIT.md](PROJECT_AUDIT.md)** | The client-page count (a seventh page, `privacy.php`); `js/confirm.js` and `js/theme.js` in the folder structure; the `js/app.js` ↔ `js/admin.js` isolation note updated to reflect the shared modules |
| **[DATABASE.md](DATABASE.md)** | Only if D8 Option B/C or D2 Option B/C changed the schema |
| **This plan** | A final status line recording which option was taken for each of D1-D9 — matching Phase 8's plan's closing line |

### Testing Requirements

- **Full end-to-end regression, both themes, all 14 pages:** every customer flow (browse → book → confirm → cancel/return → receipt) and every admin flow (login → CRUD × 4 → confirm booking → settings → logout)
- `php -l` on every modified PHP file; `node --check` on every modified JS file
- Grep verification: zero `glassmorph`, zero `body.dark`, zero bare `.dark`, zero `toggleDarkMode`, zero `btnPreview` (under D9 Option A), zero `confirm(` and zero `alert(`
- Every document cross-reference resolves
- **Zero console errors on all 14 pages in both themes**

### Acceptance Criteria

- Every implemented step is verified end-to-end
- Every document listed above is accurate
- Every descoped or gated item is recorded with its reason
- **This initiative's documentation is not merged into Phase 8's or the upcoming Responsive Review's**

---

## Traceability

| Requested feature | Steps | Decisions | Backend? |
|---|---|---|---|
| **F1** Dark mode | 8, 9, 10 | D1, D2, D3 | Only under D2 B/C |
| **F2** Remove glassmorphism | 5 | D3, D6 | No |
| **F3** Confirmation modals | 1, 3, 4 | D4, D5 | No |
| **F4** Privacy consent | 6, 7 | D7, D8 | **Yes — `register.php`**, plus schema under D8 B/C |
| **F5** Remove preview button | 2 | D9 | No |
| *(cross-cutting)* | 11, 12 | — | No |

| Open item carried, not fixed | Where |
|---|---|
| [BUGS.md](BUGS.md) item 8 — `delete_booking.php` dead guard | Untouched (§8) |
| `admin_delete_user.php` ID-space guard | Untouched (§8) |
| CSRF | Untouched (§8) |
| [BUGS.md](BUGS.md) item 15 — timezone | **Routed around** in Steps 6 and 7; not fixed (§8) |
| [BUGS.md](BUGS.md) item 24 — `index.php` desktop overflow | Out of scope (Step 11) |
| Licence-file storage split | **Surfaced** for the RA 10173 advisor (D7 note); not scheduled |

---

## Final Status (Step 12, 2026-08-21)

All twelve steps are implemented, verified live, and documented. Decision Gate outcomes, as actually shipped:

| Decision | Option taken | Shipped in |
|---|---|---|
| **D1** — Dark mode default | C: OS-seeded, pinned on first manual toggle | Step 8b |
| **D2** — Dark mode persistence | A: `localStorage` only | Step 8b |
| **D3** — Dark mode mechanism | A: Bootstrap `data-bs-theme` | Steps 5, 8-10 |
| **D4** — Confirmation architecture | A: shared `js/confirm.js` module | Step 1 |
| **D5** — Confirmation scope | A: the 8-include / 19-exclude recommendation, accepted as-is | Steps 3-4 |
| **D6** — `.cta-banner` | B: flattened to a solid token colour | Step 5 |
| **D7** — Privacy policy | A: dedicated `privacy.php` page, user-supplied text | Steps 6-7 |
| **D8** — Consent recording | A now (non-persisted); versioned consent table recorded as future work | Step 7; future work noted in [FEATURES.md](FEATURES.md) |
| **D9** — Preview button | A: refactor-then-remove | Step 2 |

Every descoped or gated item and its reason is recorded in the "Open item carried, not fixed" table above, plus three further out-of-scope findings surfaced during Step 11 and logged to [BUGS.md](BUGS.md) items 27-29, and four Additional Findings (A1, A3, A5, A8) formally logged to [BUGS.md](BUGS.md) items 30-33 per Step 12's documentation requirement.

**Full regression:** all 14 pages exercised in both themes across Steps 9-11 (contrast, overflow, keyboard/focus, console errors); `php -l` and `node --check` clean on every modified file as of each step; grep-verified zero `glassmorph`, zero `body.dark`, zero bare `.dark`, zero `toggleDarkMode`, zero `btnPreview`, zero native `confirm(`. **One grep clause not fully clean, documented rather than silently passed over:** `alert(` still appears 13 times in `js/app.js`, all pre-existing error/validation messaging outside any of the eight confirmation call sites — converting those to a non-blocking UI pattern is a separate feature (a toast/notification system) never among the five originally requested, and was not implemented here to avoid unapproved scope expansion; flagged for a future decision if a blanket alert-free UI is wanted.

**Documentation updated this step:** [CHANGELOG.md](../CHANGELOG.md) (one entry per step, Steps 1-11), [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) (dark token table, contrast-verification summary, glassmorphism-removal note, new `privacy.php` page section), [COMPONENT_LIBRARY.md](COMPONENT_LIBRARY.md) (confirmation dialog, theme toggle, and consent checkbox patterns; `glassmorph` references corrected), [FEATURES.md](FEATURES.md) (four new feature entries: Dark Mode, Consequential-Action Confirmations, Privacy Policy Page, Privacy Consent Checkbox), [BUGS.md](BUGS.md) (item 3's mechanism correction confirmed already accurate; items 27-33 new), [MOTION_DESIGN_ANALYSIS.md](MOTION_DESIGN_ANALYSIS.md) (the four `#btnPreview`/`#bookingPreview` checklist items and the Priority Matrix row were already correctly annotated during Step 2 itself — confirmed, not re-done), [API.md](API.md) (already flagged the `register.php` breaking change during Step 7 — confirmed, not re-done), [PROJECT_AUDIT.md](PROJECT_AUDIT.md) (client page count, `js/confirm.js`/`js/theme.js` in the folder structure, the `js/app.js`↔`js/admin.js` isolation note). [DATABASE.md](DATABASE.md) — **not touched**, correctly: D8 Option A and D2 Option A mean no schema change occurred anywhere in this initiative.

This initiative's documentation was kept in its own step entries throughout and is not merged into Phase 8's or the Responsive Review's documentation, per this plan's own acceptance criteria.

*This plan modified no code by itself. Every step required its own prompt and its own approval, one at a time (with two explicit exceptions: Step 9 and Step 10, which the user authorized to generate-and-implement without a separate approval pause). All nine decisions (D1-D9) were resolved by the user on 2026-08-21. The System Enhancements initiative is complete.*
