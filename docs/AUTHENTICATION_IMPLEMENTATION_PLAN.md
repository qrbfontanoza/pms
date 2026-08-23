# Authentication Implementation Plan

Derived from [AUTHENTICATION_ANALYSIS.md](AUTHENTICATION_ANALYSIS.md) (approved). Defines the exact implementation order and per-step requirements for the Authentication UI Modernization phase (Phase 6 of [UI_IMPLEMENTATION_PLAN.md](UI_IMPLEMENTATION_PLAN.md)), per [CLAUDE.md](../CLAUDE.md)'s workflow.

**Status:** Plan only. No code has been modified. Each step below is implemented only after a dedicated Claude Code prompt is generated, reviewed, and separately approved — one step at a time, per this project's established workflow.

**Key constraint:** All backend endpoints (`login.php`, `register.php`, `forgot_password.php`, `reset_password.php`, `change_password.php`) are fully implemented and unchanged by this phase. This is a frontend-only phase — no server-side modifications.

---

## Implementation Sequence Overview

```
Validation Infrastructure
  ↓
Login Modal Improvements
  ↓
Signup Modal Improvements
  ↓
Forgot Password Modal (New Build)
  ↓
Accessibility & Polish Pass
  ↓
Final Review
```

### Dependency Graph

```
Validation Infrastructure    ── independent; builds the shared real-time validation
                                helpers that Steps 2-4 all consume. Must land first
                                so the per-form steps wire into it, not reinvent it.
Login Modal Improvements     ── depends on Step 1 (validation helpers). Adds real-time
                                validation, per-field errors, Forgot Password link
                                (the link target modal is built in Step 4 — the link
                                itself can exist before the modal, pointing at the
                                not-yet-created #forgotPasswordModal).
Signup Modal Improvements    ── depends on Step 1 (validation helpers). Independent of
                                Step 2 (login) in code, but ordered after for testing
                                continuity.
Forgot Password Modal        ── depends on Step 1 (validation helpers) and Step 2
                                (login modal must have the "Forgot your password?" link
                                already in place, targeting this modal's ID). New build.
Accessibility & Polish       ── depends on Steps 1-4 all being complete.
Final Review                 ── depends on all prior steps.
```

---

## Step 1: Validation Infrastructure

### Objective

Build the shared real-time validation logic that all auth forms will use: a reusable pattern for adding `.is-invalid`/`.is-valid` to fields on `blur`/`input`, showing `.invalid-feedback` messages, and coexisting cleanly with the existing `showAuthError()` alert+shake path from Phase 8.

### Existing Files Involved

- [js/app.js](../js/app.js) — the `showAuthError()` helper (line 1074) and both auth submit handlers
- [includes/auth_modals.php](../includes/auth_modals.php) — modal markup where `.invalid-feedback` elements will be added
- [css/styles.css](../css/styles.css) — may need minor additions for validation-related styling

### Files Expected to Be Modified

- `js/app.js` (new validation helper functions)
- `includes/auth_modals.php` (`.invalid-feedback` elements added to each field)
- `css/styles.css` (only if Bootstrap's built-in `.is-invalid`/`.invalid-feedback` styling needs project-specific overrides — likely minimal or none)

### Components to Reuse

- Bootstrap 5's built-in `.is-invalid`, `.is-valid`, `.invalid-feedback`, `.valid-feedback` classes — these already have styling in Bootstrap's CSS, which is loaded on every page
- `showAuthError()` (Phase 8) — unchanged, continues to handle on-submit and server errors
- `PMSMotion.setButtonLoading()` (Phase 2) — unchanged

### Components to Create

A shared validation helper pattern in `js/app.js`. The exact API shape is an implementation-time decision, but it must support:

1. **`blur` trigger for initial validation** — validate when the user leaves a field for the first time, not while they're still typing
2. **`input` trigger for correction** — once a field has been marked `.is-invalid`, switch to `input`-event-driven validation so the error clears as soon as the user fixes it (not on the next blur)
3. **Field-specific validation rules** — each field gets its own rule (non-empty, email format, min length, match another field) and its own error message
4. **`.is-invalid` / `.invalid-feedback` application** — add the class to the input, show the corresponding `.invalid-feedback` element
5. **`.is-valid` application (optional, selective)** — use sparingly; the strongest candidate is the confirm-password field (green checkmark when passwords match). Do not apply `.is-valid` to every field on every keystroke — it's visually noisy.
6. **Clearing stale states before `showAuthError()`** — when a submit-triggered `showAuthError()` fires, clear any per-field `.is-invalid`/`.is-valid` states first, so the user sees only the server error alert, not conflicting field-level signals from a stale validation pass
7. **Full-form validation on submit** — a function that validates all fields in a form at once, marks all invalid ones, and returns whether the form is valid. Used as the first check in each submit handler, before the `fetch()` call. If any field fails, focus the first `.is-invalid` field (complementing, not replacing, the existing `showAuthError()` alert-focus for server errors).

### Coexistence with Phase 8's `showAuthError()`

The two systems handle different error types and must not conflict:

| Error source | Handler | Visual feedback |
|---|---|---|
| Empty/invalid field (caught before submit) | Real-time validation (this step) | `.is-invalid` border + `.invalid-feedback` text on the specific field |
| Empty/invalid field (caught on submit, pre-fetch) | Full-form validation (this step) | Same as above, plus focus on first invalid field |
| Server rejection (caught after fetch) | `showAuthError()` (Phase 8, unchanged) | Alert div shake + focus + error text. Per-field states cleared beforehand. |

This means `showAuthError()` is called **less often** after this step lands (because most errors are caught earlier by real-time validation), but it is **never removed or modified** — it remains the correct handler for server-side errors that no client-side check can predict (wrong password, duplicate email, expired reset code, etc.).

### Dependencies

None — this is the first step and only adds new helper code + `.invalid-feedback` markup.

### Data Requirements

None — static validation rules only. The password rule (min 6 characters) is hardcoded to match the server rule.

### CSS Requirements

- Verify that Bootstrap 5.3.2's built-in `.is-invalid`/`.invalid-feedback` styling (red border, red text below the field) renders correctly inside the `.glassmorph` modal context. If the glassmorphism backdrop/blur causes contrast issues with the red border or text, add a small targeted override in `css/styles.css`.
- No new hardcoded colors — Bootstrap's validation colors are already defined.

### Bootstrap 5 Requirements

- Use `.invalid-feedback` (not `.invalid-tooltip`) — tooltip positioning inside modals is unreliable.
- Do **not** use Bootstrap's `.was-validated` class on the `<form>` element — that triggers browser-native validation styling on all fields at once, which conflicts with the progressive blur-then-input validation pattern described above. Instead, apply `.is-invalid`/`.is-valid` on individual fields programmatically.

### Responsive Requirements

- `.invalid-feedback` text must be readable at 375px without clipping or overflow.
- Test that the additional vertical space taken by error messages doesn't cause the modal to overflow or require scrolling at narrow viewports with multiple errors visible simultaneously.

### Accessibility Requirements

- Each `.invalid-feedback` element must have a unique `id` and be linked to its input via `aria-describedby`. When the field is valid or untouched, the `aria-describedby` can remain (pointing at a hidden element is harmless); when invalid, the feedback becomes visible and announced.
- `aria-invalid="true"` should be set alongside `.is-invalid` on the input element, and removed when the error clears.
- Real-time validation feedback should be announced by screen readers. Bootstrap's `.invalid-feedback` becomes visible via CSS when the sibling input has `.is-invalid`, which is a visual-only change — ensure the feedback element has `role="alert"` or `aria-live="polite"` so it's announced, or rely on the `aria-describedby` link + `aria-invalid` change to trigger re-announcement on focus.

### Testing Requirements

- Add `.invalid-feedback` elements to at least one field (e.g. `#loginEmail`) as a proof-of-concept.
- Verify: type nothing, blur away → `.is-invalid` appears, error text visible.
- Verify: type a valid value → `.is-invalid` clears on `input` event.
- Verify: submit the form with the field empty → full-form validation catches it, focus lands on the field.
- Verify: submit with valid data but wrong password → `showAuthError()` fires with shake+focus on `#loginError`, no stale `.is-invalid` on any field.
- Verify at 375px: error text doesn't clip.
- Verify console: no new JS errors.
- Verify reduced-motion: no new animations introduced in this step.

### Acceptance Criteria

- A reusable validation pattern exists in `js/app.js` that can be applied to any auth form field.
- `.invalid-feedback` elements exist in `includes/auth_modals.php` for every login and signup field.
- `blur`-triggered validation works on at least the login email field (full wiring for all fields happens in Steps 2-3).
- `showAuthError()` is unchanged and continues to work for server errors.
- `aria-describedby` and `aria-invalid` are correctly set on validated fields.

### Risks

- Low. This step adds new code alongside existing code — no existing behavior is modified. The main risk is the coexistence design between real-time validation and `showAuthError()`, which is specified explicitly above.

---

## Step 2: Login Modal Improvements

### Objective

Wire real-time validation into the login form, add per-field error states, add a "Forgot your password?" link, and replace the native `alert()` success pattern.

### Existing Files Involved

- [includes/auth_modals.php](../includes/auth_modals.php) (login modal, lines 1-30)
- [js/app.js](../js/app.js) (login submit handler, lines 1125-1154; `showAuthError()`, line 1074)

### Files Expected to Be Modified

- `includes/auth_modals.php` (add "Forgot your password?" link, wire `.invalid-feedback` into live validation)
- `js/app.js` (wire login fields into the Step 1 validation infrastructure, replace `alert('Login successful!')`)

### Components to Reuse

- Step 1's validation infrastructure (newly built)
- `showAuthError()` (Phase 8, unchanged)
- `PMSMotion.setButtonLoading()` (Phase 2, unchanged)
- `.glassmorph`, `.form-control`, modal structure (unchanged)

### Components to Create

- "Forgot your password?" link — a `<button class="btn btn-link">` or `<a>` placed below the password field or below the submit button, styled consistently with the existing "No account? Sign up" link pattern. This link dismisses `#loginModal` and opens `#forgotPasswordModal` (built in Step 4). The link can exist before the target modal is built — it will simply do nothing until Step 4 lands, which is acceptable since steps are implemented sequentially.
- Success feedback replacement — instead of `alert('Login successful!')`, either: (a) simply reload without any intermediate message (the page reload is itself confirmation), or (b) briefly show a success alert in the modal before reloading. Recommend option (a) — the native `alert()` blocks the reload, and there's no reason to delay it. The user sees the navbar update to their logged-in state as confirmation.

### Validation Rules to Wire

| Field | Trigger | Rule | Message |
|---|---|---|---|
| `#loginEmail` | `blur` (initial), `input` (correction) | Non-empty; basic email format (contains `@` and a `.` after it) | "Please enter your email address." / "Please enter a valid email address." |
| `#loginPassword` | `blur` (initial), `input` (correction) | Non-empty | "Please enter your password." |

No password-length check on login — the server returns a generic "Invalid email or password" for wrong credentials, and checking length on login would leak whether the password rule was met.

### Dependencies

Step 1 (Validation Infrastructure) must be complete.

### Data Requirements

None — `login.php` is unchanged.

### CSS Requirements

None expected — Step 1's CSS work (if any) covers this.

### Bootstrap 5 Requirements

The "Forgot your password?" link should use the same `btn btn-link` pattern as the existing "Sign up" link, for visual consistency.

### Responsive Requirements

- Verify the added "Forgot your password?" link doesn't cause layout overflow at 375px.
- Verify `.invalid-feedback` messages display correctly at all breakpoints.

### Accessibility Requirements

- "Forgot your password?" link must be keyboard-focusable and have a descriptive accessible name.
- Modal transition (login → forgot-password) must manage focus: when `#loginModal` is dismissed and `#forgotPasswordModal` opens, focus should land on the first input of the forgot-password modal (handled automatically by `initModalFocus()` if the modal is wired with the standard Bootstrap pattern).
- `aria-describedby` on `#loginEmail` and `#loginPassword` pointing to their `.invalid-feedback` elements.
- `aria-invalid` toggled on validation state changes.

### Testing Requirements

- **Real-time validation:** focus `#loginEmail`, blur without typing → "Please enter your email address." appears with red border. Type "abc", blur → "Please enter a valid email address." Type "abc@test.com" → error clears on `input` event.
- **On-submit validation:** click "Log In" with both fields empty → both fields get `.is-invalid`, focus lands on `#loginEmail`.
- **Server error:** submit with valid-looking but wrong credentials → `showAuthError()` fires with shake+focus on `#loginError`, per-field `.is-invalid` states are cleared.
- **Success:** submit with correct credentials → page reloads without native `alert()` dialog, navbar shows logged-in state.
- **"Forgot your password?" link:** click it → `#loginModal` dismisses. (The target modal doesn't exist yet — confirm no JS error is thrown.)
- Visual check at 375px, 768px, 992px, 1400px.
- Console: no new errors.

### Acceptance Criteria

- Both login fields have real-time validation with per-field error messages.
- `alert('Login successful!')` is removed — login success goes straight to page reload.
- "Forgot your password?" link is visible and correctly dismisses the login modal.
- `showAuthError()` still fires correctly for server errors (shake + focus unchanged).
- No regression to the existing "No account? Sign up" modal-switch behavior.

### Risks

- Low. All changes are additive to existing markup/handlers. The only modification to the existing submit handler is: (1) adding a full-form validation check before the fetch, and (2) removing the `alert()` call.

---

## Step 3: Signup Modal Improvements

### Objective

Wire real-time validation into the signup form, add per-field error states with specific messages (including the password-length rule), add the missing "Already have an account? Log in" reciprocal link, replace the native `alert()` success pattern, and optionally add a password visibility toggle.

### Existing Files Involved

- [includes/auth_modals.php](../includes/auth_modals.php) (signup modal, lines 32-64)
- [js/app.js](../js/app.js) (signup submit handler, lines 1086-1122; `showAuthError()`, line 1074)

### Files Expected to Be Modified

- `includes/auth_modals.php` (add reciprocal link, password helper text, optionally password toggle button)
- `js/app.js` (wire signup fields into Step 1 validation infrastructure, add client-side password-length check, replace `alert()`)

### Components to Reuse

- Step 1's validation infrastructure
- `showAuthError()` (Phase 8, unchanged)
- `PMSMotion.setButtonLoading()` (Phase 2, unchanged)
- The login modal's "No account? Sign up" link pattern — mirror it in reverse for "Already have an account? Log in"

### Components to Create

- **"Already have an account? Log in" link** — placed below the "Create Account" button, matching the login modal's existing link pattern (dismiss signup modal, open login modal)
- **Password helper text** — a `<div class="form-text">` below `#signupPassword` reading "Minimum 6 characters" (the actual server rule, confirmed from `register.php:24`). Linked to the input via `aria-describedby`.
- **Password visibility toggle** (optional, recommended) — a button/icon inside or adjacent to password fields that toggles `type="password"` ↔ `type="text"`. If implemented, must use `aria-label` ("Show password" / "Hide password") and update the icon (e.g. `fa-eye` ↔ `fa-eye-slash`). Apply to both `#signupPassword` and `#signupConfirmPassword`.
- **Client-side password-length check** — the signup handler's on-submit validation currently checks only for empty fields and password mismatch. Add a `password.length < 6` check with the message "Password must be at least 6 characters." — this matches the server's rule exactly and prevents a round-trip for a predictable error.

### Validation Rules to Wire

| Field | Trigger | Rule | Message |
|---|---|---|---|
| `#signupName` | `blur` (initial), `input` (correction) | Non-empty | "Please enter your name." |
| `#signupEmail` | `blur` (initial), `input` (correction) | Non-empty; email format | "Please enter your email address." / "Please enter a valid email address." |
| `#signupPassword` | `blur` (initial), `input` (correction) | Non-empty; min 6 chars | "Please enter a password." / "Password must be at least 6 characters." |
| `#signupConfirmPassword` | `blur` (initial), `input` (correction) | Non-empty; matches `#signupPassword` | "Please confirm your password." / "Passwords do not match." |

The confirm-password field should also re-validate when `#signupPassword` changes (if the user changes the original password after already confirming, the match state may have changed).

### Dependencies

Step 1 (Validation Infrastructure) must be complete. Independent of Step 2 (Login) in code, but ordered after for testing continuity.

### Data Requirements

None — `register.php` is unchanged.

### CSS Requirements

- If a password visibility toggle is added, it needs positioning CSS (e.g. absolutely positioned inside the `.form-control` via an `.input-group` wrapper, or placed as a sibling button). Keep it minimal and Bootstrap-native (`.input-group` + `.input-group-text` is the standard pattern).
- No new hardcoded colors.

### Bootstrap 5 Requirements

- Use `.input-group` for the password toggle, if implemented — wrapping the password `<input>` and a `<button class="input-group-text">` containing the eye icon.
- The reciprocal link ("Already have an account? Log in") should use the same `text-center mt-3` + `btn-link` pattern as the login modal's "Sign up" link.

### Responsive Requirements

- Verify the password toggle button (if added) doesn't shrink the input field uncomfortably at 375px.
- Verify password helper text doesn't overflow at narrow widths.
- Verify all 4 fields + their potential error messages fit within the modal body at 375px without making the modal scroll excessively.

### Accessibility Requirements

- Password helper text ("Minimum 6 characters") must be linked to `#signupPassword` via `aria-describedby` — if the field also has an `.invalid-feedback` element linked via `aria-describedby`, both IDs should be space-separated in the attribute value (the spec supports this).
- Password visibility toggle must have `aria-label` that updates based on state.
- The reciprocal "Log in" link must be keyboard-focusable and manage the modal transition's focus correctly (same pattern as the existing "Sign up" link).
- Confirm-password re-validation when `#signupPassword` changes must update `aria-invalid` accordingly.

### Testing Requirements

- **Real-time validation per field:** focus each field, blur without typing → correct error appears. Type invalid content (short password, mismatched confirm) → correct field-specific error. Correct it → error clears.
- **Password length:** type 3 characters in `#signupPassword`, blur → "Password must be at least 6 characters." Type 3 more → error clears.
- **Password match:** type different passwords in password and confirm fields → "Passwords do not match." Make them match → error clears (confirm `.is-valid` appears on confirm field, if `.is-valid` is used).
- **On-submit validation:** click "Create Account" with all fields empty → all 4 get `.is-invalid`, focus lands on `#signupName` (the first invalid field).
- **Server error:** submit with a duplicate email → `showAuthError()` fires with "Email already registered.", per-field states cleared.
- **Success:** submit with valid data → signup modal closes, login modal opens, no native `alert()`.
- **Reciprocal link:** click "Already have an account? Log in" → signup modal dismisses, login modal opens.
- **Password toggle (if implemented):** click eye icon → password visible, icon changes, `aria-label` updates. Click again → reversed.
- Visual check at 375px, 768px, 992px, 1400px.
- Console: no new errors.

### Acceptance Criteria

- All 4 signup fields have real-time validation with specific, accurate messages.
- Password helper text ("Minimum 6 characters") is visible below the password field.
- Client-side password-length check prevents submission of passwords under 6 characters.
- "Already have an account? Log in" link works and mirrors the login modal's reciprocal link pattern.
- `alert('Account created successfully!')` is removed — registration success dismisses signup and opens login.
- `showAuthError()` still fires correctly for server errors.

### Risks

- Medium — this step has more moving parts than Step 2 (4 fields, password match cross-validation, optional toggle). The password toggle's `.input-group` wrapper changes the DOM structure around password fields, which could affect the `.is-invalid` CSS targeting if not tested carefully (Bootstrap's `.is-invalid` on an input inside `.input-group` applies the red border correctly, but the `.invalid-feedback` element must be a sibling of the `.input-group`, not inside it, to display correctly — verify during implementation).

---

## Step 4: Forgot Password Modal (New Build)

### Objective

Build a new multi-step Forgot Password modal that exposes the existing `forgot_password.php` and `reset_password.php` backend endpoints to the customer, completing the password-recovery flow that currently has no UI.

### Existing Files Involved

- [forgot_password.php](../forgot_password.php) — POST email → sends 6-digit code (already complete)
- [reset_password.php](../reset_password.php) — POST email + code + new_password → resets password (already complete)
- [includes/auth_modals.php](../includes/auth_modals.php) — where the new modal markup goes
- [js/app.js](../js/app.js) — where the new handlers go

### Files Expected to Be Modified

- `includes/auth_modals.php` (new `#forgotPasswordModal` added)
- `js/app.js` (new multi-step handler added)

### Components to Reuse

- `.glassmorph` modal styling (match login/signup exactly)
- Step 1's validation infrastructure (real-time validation on all fields)
- `showAuthError()` pattern (for server errors within each step)
- `PMSMotion.setButtonLoading()` (for all submit buttons)
- `initModalFocus()` (will automatically handle focus-on-open for the new modal)
- `p-2 p-sm-4` responsive padding (match existing modals)

### Components to Create

**New modal: `#forgotPasswordModal`** with three internal steps, shown/hidden via JS (not three separate modals — a single modal with step visibility toggling):

**Step 1 — Request Reset Code:**
- Heading: "Forgot Password" or "Reset Your Password"
- Error alert: `#forgotError` (same pattern as `#loginError` — `alert alert-danger d-none`, `role="alert"`, `tabindex="-1"`)
- Field: Email (`#forgotEmail`, `type="email"`, `required`, `autocomplete="email"`)
- `.invalid-feedback` for email field
- Button: "Send Reset Code" (primary, full-width, rounded-pill)
- Footer: "Remember your password? Log in" link (dismisses forgot modal, opens login modal)
- On submit: validate email (real-time), POST to `forgot_password.php`, show Step 2 on success

**Step 2 — Enter Code:**
- Heading: "Enter Reset Code"
- Instruction text: "We've sent a 6-digit code to [email]. Check your inbox." (display the email from Step 1)
- Error alert: same `#forgotError` (reused across steps, text updated)
- Field: Code (`#forgotCode`, `type="text"`, `inputmode="numeric"`, `maxlength="6"`, `pattern="[0-9]{6}"`, `autocomplete="one-time-code"`)
- `.invalid-feedback` for code field
- Button: "Verify Code" (primary)
- Footer: "Didn't receive a code? Resend" link (re-calls `forgot_password.php` with the same email) + "Back to login" link
- On submit: validate code (non-empty, 6 digits), proceed to Step 3 (code validation happens server-side in the final reset call — no separate "verify code" endpoint exists, so this step stores the code client-side and Step 3 sends it along with the new password)

**Implementation note on Step 2:** Since no separate "verify code" server endpoint exists (verification is bundled into `reset_password.php`), Step 2 is a client-side transition — it collects the code and moves to Step 3. The code is not validated against the server until the final reset call in Step 3. This means a wrong code won't be caught until Step 3's submission, which is acceptable — the server returns "Invalid or expired reset code." and `showAuthError()` displays it. Alternatively, Steps 2 and 3 could be combined into a single step with code + new password + confirm password all on one screen — **decision to confirm at implementation time**.

**Step 3 — Set New Password:**
- Heading: "Set New Password"
- Error alert: same `#forgotError`
- Fields: New Password (`#forgotNewPassword`, `type="password"`, `autocomplete="new-password"`) + Confirm Password (`#forgotConfirmPassword`, `type="password"`, `autocomplete="new-password"`)
- `.invalid-feedback` for both fields
- Password helper text: "Minimum 6 characters" (same as signup)
- Password visibility toggle (if implemented in Step 3 for signup, apply here too for consistency)
- Button: "Reset Password" (primary)
- On submit: validate both fields (real-time), POST to `reset_password.php` with email (from Step 1) + code (from Step 2) + new password. On success: show a success message, then dismiss modal and open login modal.

**Step transition behavior:**
- Each step shows/hides via JS (e.g. `.d-none` toggle on step container divs), not via page navigation
- Focus lands on the first input of each new step after transition
- The "Back" navigation between steps should be possible (Step 2 → Step 1, Step 3 → Step 2) without losing entered data
- Email entered in Step 1 carries through to Steps 2 and 3 (held in a JS variable, not re-entered)
- If the modal is closed and reopened, it resets to Step 1

### Dependencies

Step 1 (Validation Infrastructure) must be complete. Step 2 (Login Modal) must be complete so that the "Forgot your password?" link in the login modal already targets `#forgotPasswordModal`.

### Data Requirements

Calls two existing endpoints:
- `POST forgot_password.php` — body: `{ email }` — response: `{ success, message }`
- `POST reset_password.php` — body: `{ email, code, new_password }` — response: `{ success, message }` or `{ error }`

No new server-side work.

### CSS Requirements

- No new hardcoded colors.
- Step indicator (if any — e.g. a simple "Step 1 of 3" text or three dots) should use brand CSS variables.
- The modal's step transitions should not use any animation that isn't already covered by the global reduced-motion rule. A simple show/hide (`.d-none` toggle) with no transition is the safest approach.

### Bootstrap 5 Requirements

- Same `.modal-dialog` sizing as login/signup (default, not `.modal-lg`).
- Same `.glassmorph p-2 p-sm-4` content styling.
- The 6-digit code input should use `inputmode="numeric"` for mobile keyboard optimization and `autocomplete="one-time-code"` for browser autofill from SMS/authenticator.

### Responsive Requirements

- Must render correctly at 375px — the multi-step flow's tallest step (Step 3, with two password fields + helper text + error messages) must not overflow the viewport.
- Test the 6-digit code input at 375px — ensure it's wide enough to display all 6 digits comfortably.
- If a step indicator is used, it must not overflow at narrow widths.

### Accessibility Requirements

- `aria-labelledby` on `#forgotPasswordModal` pointing to its heading
- Error alert: `role="alert"`, `tabindex="-1"` (same pattern as login/signup)
- Focus management on step transitions: first input of each step receives focus
- Code input: clear label ("6-digit code"), `inputmode="numeric"`, `autocomplete="one-time-code"`
- Step transitions should announce the new step to screen readers — either via `aria-live` on a step-title region, or by moving focus to the step heading
- "Resend code" action should provide feedback ("Code resent!") via an accessible mechanism (not just visual — use `role="status"` or similar)
- `aria-describedby` and `aria-invalid` on all fields (same pattern as Steps 2-3)

### Testing Requirements

- **Step 1:** enter email, submit → `forgot_password.php` called, Step 2 appears, email displayed in instruction text.
- **Step 1 validation:** submit with empty email → `.is-invalid` on email field. Submit with invalid email → "Please enter a valid email address."
- **Step 2:** enter 6-digit code, submit → Step 3 appears.
- **Step 2 validation:** submit with empty/non-6-digit code → `.is-invalid` on code field.
- **Step 3:** enter new password + confirm, submit → `reset_password.php` called with email + code + new_password.
- **Step 3 success:** modal dismisses, login modal opens.
- **Step 3 server error:** wrong code → `showAuthError()` with "Invalid or expired reset code." and shake.
- **Step 3 validation:** password < 6 chars → "Password must be at least 6 characters." Passwords don't match → "Passwords do not match."
- **Modal reset:** close modal, reopen → starts at Step 1, all fields cleared.
- **Back navigation:** from Step 2, go back to Step 1 → email still populated. From Step 3, go back to Step 2 → code still populated.
- **"Remember your password? Log in" link:** dismisses forgot modal, opens login modal.
- **"Resend code" link:** calls `forgot_password.php` again, shows feedback.
- Visual check at 375px, 768px, 992px, 1400px for each step.
- Console: no new errors.
- **Note on mail delivery:** the `forgot_password.php` endpoint uses PHP's `mail()` with no SMTP configuration. If `mail()` doesn't deliver on the test environment, the forgot-password flow cannot be tested end-to-end. This is a pre-existing backend limitation (documented in [FEATURES.md](FEATURES.md) and [PROJECT_AUDIT.md](PROJECT_AUDIT.md)), not introduced by this phase. For UI testing purposes, the endpoint's JSON response can be verified (it always returns `{ success: true }` regardless of mail delivery), and the code can be read directly from the database's `users.reset_code` column.

### Acceptance Criteria

- A new `#forgotPasswordModal` exists with a working 3-step flow.
- "Forgot your password?" link in the login modal opens the forgot-password modal.
- Real-time validation works on all fields across all three steps.
- `showAuthError()` handles server errors with shake+focus.
- Submit spinners appear on all buttons during server calls.
- Modal resets to Step 1 when closed and reopened.
- Back navigation between steps preserves entered data.
- The full flow works end-to-end (email → code → new password → success → login modal) when mail delivery is functional.

### Risks

- Medium — this is the largest single step, building an entirely new modal with multi-step flow. The main risk is the step-transition logic (show/hide, data persistence across steps, focus management). Keep the implementation straightforward — `.d-none` toggles, JS variables for cross-step data — rather than introducing a state machine or step framework.
- Medium — `mail()` may not deliver on the test server, making end-to-end testing impossible without database inspection. This is a known, pre-existing limitation.

---

## Step 5: Accessibility & Polish Pass

### Objective

Verify and fix any remaining accessibility gaps across all auth modals after Steps 1-4 are complete: focus management, ARIA attributes, keyboard navigation, reduced-motion, color contrast, and minor UX polish.

### Existing Files Involved

- All files modified in Steps 1-4

### Files Expected to Be Modified

- `includes/auth_modals.php` (ARIA fixes if needed)
- `js/app.js` (focus-management fixes if needed)
- `css/styles.css` (contrast fixes if needed)

### Dependencies

All of Steps 1-4 must be complete.

### Checks to Perform

1. **Focus management across all modal transitions:**
   - Login → Signup (existing): focus lands on `#signupName`
   - Signup → Login (new): focus lands on `#loginEmail`
   - Login → Forgot Password (new): focus lands on `#forgotEmail`
   - Forgot Password → Login (new): focus lands on `#loginEmail`
   - Forgot Password step transitions: focus lands on first input of each step
   - All modal dismissals: focus returns to the triggering element

2. **Keyboard navigation:**
   - Tab through every field in each modal — correct order, no focus traps beyond the modal itself
   - Enter submits the form from any field
   - Escape closes the modal
   - Password visibility toggle (if implemented) is reachable via Tab and togglable via Enter/Space

3. **ARIA verification:**
   - Every input with validation has `aria-describedby` pointing to its `.invalid-feedback` (and helper text, if any)
   - `aria-invalid` toggles correctly with `.is-invalid`
   - `aria-busy` on submit buttons during loading (already handled by `PMSMotion.setButtonLoading`)
   - `role="alert"` on all error alert divs
   - `aria-labelledby` on all modals

4. **Reduced-motion:**
   - No new animations that aren't covered by the existing global reduced-motion rule
   - Confirm `animate__shakeX` still collapses to 0.01ms under reduced-motion

5. **Color contrast (WCAG AA):**
   - `.invalid-feedback` text against the `.glassmorph` modal background
   - Password helper text (`form-text`) against the modal background
   - Any new link text against the modal background

6. **Dead code cleanup:**
   - Remove the commented-out login handler (`js/app.js:487-504`) and signup handler (`js/app.js:516-541`) — confirmed dead code from the pre-Phase-8 era, wrapped in `/* ... */`, never executed. This is a low-risk cleanup done in the same pass as the accessibility verification.

### Testing Requirements

- Full keyboard-only walkthrough of every modal and transition.
- Screen reader spot-check: focus an invalid field → screen reader announces the error message.
- Reduced-motion verification: confirm no unguarded animations (method per Phase 10's approach).
- Visual check at 375px: confirm all error messages, helper text, and links are readable.

### Acceptance Criteria

- Every modal transition correctly manages focus.
- Every validated field has correct `aria-describedby` and `aria-invalid`.
- Keyboard navigation works end-to-end with no focus traps.
- No new unguarded animations.
- WCAG AA contrast met on all new text elements.
- Dead auth handlers removed from `js/app.js`.

### Risks

- Low — this is a verification and fix pass on already-implemented work.

---

## Step 6: Final Review

### Objective

Full regression pass across the entire Authentication phase, confirming all auth modals work correctly, no shared component or other page was affected, and documentation is updated.

### Existing Files Involved

All changes from Steps 1-5, plus every customer-facing page that includes `auth_modals.php` (`index.php`, `vehicles.php`, `about.php`, `faq.php`, `transactions.php`).

### Files Expected to Be Modified

- `CHANGELOG.md` (phase summary entry)
- Potentially `docs/FEATURES.md` (update Forgot Password entry to note UI now exists)
- Potentially minor fixes surfaced during full regression, scoped to auth-related files only

### Dependencies

All of Steps 1-5 must be complete and individually approved.

### Testing Requirements

- `php -l` on `includes/auth_modals.php`, `js/app.js`, and every customer-facing page.
- Full visual pass at 375px, 768px, 992px, 1400px.
- **Login flow end-to-end:** open modal, real-time validation fires on blur, submit with correct credentials, page reloads, navbar shows logged-in state.
- **Registration flow end-to-end:** open modal, real-time validation fires on blur, password-length check works, passwords-match check works, submit, signup modal closes, login modal opens.
- **Forgot password flow end-to-end:** login modal → "Forgot your password?" → email step → code step → new password step → success → login modal (or as far as `mail()` allows on the test server).
- **Modal transitions:** login ↔ signup, login → forgot, forgot → login — all manage focus correctly.
- **Server error paths:** wrong login credentials, duplicate registration email, expired reset code — all show `showAuthError()` with shake+focus.
- **Cross-page check:** confirm auth modals work identically on `index.php`, `vehicles.php`, `about.php`, `faq.php`, `transactions.php`.
- **Regression check:** confirm booking flow on `vehicles.php` still works (it uses `PMSMotion.setButtonLoading` which this phase doesn't modify, but verify anyway). Confirm contact form on `about.php` still works.
- Console: no new JS errors on any page.
- No hardcoded hex colors introduced in any file.

### Acceptance Criteria

- All auth modals (login, signup, forgot password) are fully functional with real-time validation, per-field errors, and correct accessibility.
- No regressions on any page or any non-auth feature.
- `CHANGELOG.md` updated with a full phase summary.
- `docs/FEATURES.md` updated to note that Forgot Password now has a customer-facing UI.

### Risks

- Low — assuming each prior step was individually tested and approved.

---

## Documentation Updates Required

Per `CLAUDE.md`'s Documentation section, each implementation step's Claude Code prompt must include updating `CHANGELOG.md` with what changed, consistent with how prior phases documented their own steps. At Final Review:

- `docs/FEATURES.md` — update the "Forgot Password" and "Reset Password" entries to note that customer-facing UI now exists (currently they document only the backend endpoints).
- `docs/COMPONENT_LIBRARY.md` — correct the stale claim about login/signup modals being duplicated 5 times (already a shared partial).
- `docs/BUGS.md` — if the dead commented-out auth handlers are removed in Step 5, log their removal.

---

## Done / Partial / Not Started Summary

| Item | Status | What This Phase Does |
|---|---|---|
| Login modal markup | **Done** | Step 2 adds `.invalid-feedback` elements, "Forgot password?" link |
| Signup modal markup | **Done** | Step 3 adds `.invalid-feedback` elements, reciprocal link, password helper text |
| `showAuthError()` (Phase 8) | **Done — preserved** | Unchanged; real-time validation is additive, reducing how often it fires |
| Submit spinner (Phase 2) | **Done — preserved** | Unchanged; extended to new forgot-password modal |
| Modal-open focus (Phase 1) | **Done — preserved** | Unchanged; automatically covers new forgot-password modal |
| Shake animation (Phase 8) | **Done — preserved** | Unchanged |
| Reduced-motion coverage | **Done — preserved** | Verified in Step 5; no additions needed |
| Real-time field validation | **Not started → Step 1** | New validation infrastructure, `.is-invalid`/`.invalid-feedback` wiring |
| Per-field error messages | **Not started → Steps 2-4** | `.invalid-feedback` elements on every auth field |
| Password rule communication | **Not started → Step 3** | "Minimum 6 characters" helper text on password fields |
| Password visibility toggle | **Not started → Step 3** | Optional; eye icon toggle on password fields |
| Client-side password-length check | **Not started → Step 3** | Prevents submission of < 6 char passwords |
| Signup → Login link | **Not started → Step 3** | "Already have an account? Log in" reciprocal link |
| Forgot Password UI | **Not started → Step 4** | Entirely new 3-step modal (email → code → new password) |
| `alert()` replacement (login) | **Not started → Step 2** | Remove native `alert()`, go straight to page reload |
| `alert()` replacement (signup) | **Not started → Step 3** | Remove native `alert()`, go straight to modal switch |
| Dead handler cleanup | **Not started → Step 5** | Remove commented-out pre-Phase-8 handlers |
| Login "Forgot password?" link | **Not started → Step 2** | New link targeting `#forgotPasswordModal` |
| Forgot → Login link | **Not started → Step 4** | "Remember your password? Log in" link in forgot modal |

**Summary:** The Phase 8 foundation (shake, focus, spinner, ARIA) is fully preserved and extended — nothing is replaced or rearchitected. The genuinely new work breaks down as: (1) a reusable validation infrastructure filling the `.is-invalid`-never-set gap (Step 1), (2) two enhancement passes on existing modals wiring that infrastructure in (Steps 2-3), (3) one entirely new modal for the forgot-password flow whose backend already exists (Step 4), and (4) a verification/polish pass (Step 5). The backend is 100% untouched.

---

*This document is a plan only. No code has been modified. Implementation proceeds one step at a time, beginning with Validation Infrastructure, only after this plan is approved and a dedicated Claude Code prompt is generated and separately reviewed for that step.*
