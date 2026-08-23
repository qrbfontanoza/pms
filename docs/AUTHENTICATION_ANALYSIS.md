# Authentication Analysis

Analysis document for the Authentication UI Modernization phase (Phase 6 of [UI_IMPLEMENTATION_PLAN.md](UI_IMPLEMENTATION_PLAN.md)), prepared per [CLAUDE.md](../CLAUDE.md)'s workflow (Understand → Analyze → Explain → Plan → Implement → Test → Document). Based on direct inspection of [includes/auth_modals.php](../includes/auth_modals.php), [login.php](../login.php), [register.php](../register.php), [forgot_password.php](../forgot_password.php), [reset_password.php](../reset_password.php), [change_password.php](../change_password.php), [js/app.js](../js/app.js), [css/styles.css](../css/styles.css), and the Motion Design Phase 8 CHANGELOG entry.

**Status:** Analysis only. No code has been modified. Implementation requires separate approval, one section at a time, per this project's established workflow.

---

## 1. Current Implementation Overview

### 1.1 Auth Modals (the customer-facing UI)

- **File:** `includes/auth_modals.php` (64 lines)
- **Contains:** two Bootstrap 5 modals — `#loginModal` and `#signupModal`
- **Included by:** every customer-facing page via `<?php include 'includes/auth_modals.php'; ?>` — this is a shared partial, not duplicated per-page (confirmed by the Shared Components phase)
- **No Forgot Password modal exists.** The file contains only login and signup modals. Grepped the entire codebase for any `forgotModal`, `resetModal`, or forgot-password-related modal markup — **zero matches**. The backend endpoints (`forgot_password.php`, `reset_password.php`) exist and are fully functional (see §2), but no UI anywhere in the project exposes them to the customer.

### 1.2 Login Modal (`#loginModal`)

- **Markup:** `includes/auth_modals.php:1-30`
- **Form ID:** `#loginForm`
- **Fields:**
  - Email (`#loginEmail`) — `type="email"`, `required`, `autocomplete="email"`
  - Password (`#loginPassword`) — `type="password"`, `required`, `autocomplete="current-password"`
- **Error display:** `#loginError` — `<div class="alert alert-danger d-none" role="alert" tabindex="-1">` (the `tabindex="-1"` was added by Motion Design Phase 8 for programmatic focus)
- **Buttons:** "Log In" (primary submit), "Login as Admin" (outline-secondary, redirects to `admin-login.php` via `js/app.js:509`)
- **Footer link:** "No account? Sign up" — dismisses login modal, opens signup modal
- **Styling:** `.glassmorph` class on `.modal-content`, `p-2 p-sm-4` responsive padding

### 1.3 Signup Modal (`#signupModal`)

- **Markup:** `includes/auth_modals.php:32-64`
- **Form ID:** `#signupForm`
- **Fields:**
  - Name (`#signupName`) — `type="text"`, `required`, `autocomplete="name"`
  - Email (`#signupEmail`) — `type="email"`, `required`, `autocomplete="email"`
  - Password (`#signupPassword`) — `type="password"`, `required`, `autocomplete="new-password"`
  - Confirm Password (`#signupConfirmPassword`) — `type="password"`, `required`, `autocomplete="new-password"`
- **Error display:** `#signupError` — identical `<div>` pattern to `#loginError`, with `tabindex="-1"` (Phase 8)
- **Buttons:** "Create Account" (success submit)
- **No footer link back to login** — the signup modal has no "Already have an account? Log in" link (unlike the login modal which has a "Sign up" link)

### 1.4 License Upload (Registration)

`register.php` accepts an optional `license` file upload (JPG/PNG, max 3MB), but **no `<input type="file">` for license exists in `#signupForm`** (`includes/auth_modals.php:41-63`). The upload capability is server-ready but has no client-side UI in the registration modal. The only license upload UI is in the booking flow (`vehicles.php`'s `#bookingModal`).

---

## 2. Server-Side Validation Rules (verbatim from source)

### 2.1 Login (`login.php`)

| Check | Code (verbatim) | Error message |
|---|---|---|
| Empty email or password | `if (!$email \|\| !$password)` (line 13) | "Missing email or password." (400) |
| Invalid credentials | `if (!$user \|\| !password_verify($password, $user['password']))` (line 24) | "Invalid email or password." (401) |

No email format validation — the server accepts any non-empty string as the email field for login (relies on the client `type="email"` for format enforcement).

### 2.2 Registration (`register.php`)

| Check | Code (verbatim) | Error message |
|---|---|---|
| Name empty, invalid email, or password < 6 chars | `if (!$name \|\| !filter_var($email, FILTER_VALIDATE_EMAIL) \|\| strlen($password) < 6)` (line 24) | "Please provide a valid name, email, and a password (min 6 chars)." (400) |
| Duplicate email | `if ($stmt->fetch())` (line 96) | "Email already registered." (409) |
| License file upload error | `if ($file['error'] !== UPLOAD_ERR_OK)` (line 36) | "Error uploading file." (400) |
| License file > 3MB | `if ($file['size'] > $maxSize)` (line 44) | "License image must be 3MB or smaller." (400) |
| License file not JPG/PNG | `if (!$detected \|\| !isset($allowed[$detected]))` (line 55) | "Only JPG and PNG images are accepted for license." (400) |

**The actual password rule is: minimum 6 characters, no complexity requirement.** No uppercase, lowercase, digit, or special-character requirements exist anywhere in the codebase. Confirmed by reading `register.php:24`, `reset_password.php:18`, and `change_password.php:23` — all three use the identical `strlen($password) < 6` check.

### 2.3 Forgot Password (`forgot_password.php`)

| Check | Code (verbatim) | Error message |
|---|---|---|
| Invalid email format | `if (!filter_var($email, FILTER_VALIDATE_EMAIL))` (line 10) | "Please provide a valid email address." (400) |

Always returns a generic success message regardless of whether the email exists (line 34) — does not leak account existence. Generates a 6-digit code with 15-minute expiry.

### 2.4 Reset Password (`reset_password.php`)

| Check | Code (verbatim) | Error message |
|---|---|---|
| Any of email/code/new_password empty | `if (!$email \|\| !$code \|\| !$newPassword)` (line 12) | "Email, code, and new password are required." (400) |
| New password < 6 chars | `if (strlen($newPassword) < 6)` (line 18) | "New password must be at least 6 characters." (400) |
| Invalid or wrong code | `if (!$user \|\| !$user['reset_code'] \|\| $user['reset_code'] !== $code)` (line 28) | "Invalid or expired reset code." (400) |
| Expired code | `if (... new DateTime($user['reset_code_expires']) < new DateTime())` (line 34) | "This reset code has expired. Please request a new one." (400) |

### 2.5 Change Password (`change_password.php`)

| Check | Code (verbatim) | Error message |
|---|---|---|
| Not logged in | `if (!isset($_SESSION['user']['id']))` (line 7) | "Please log in first" (401) |
| Current or new password empty | `if (!$currentPassword \|\| !$newPassword)` (line 17) | "Current password and new password are required." (400) |
| New password < 6 chars | `if (strlen($newPassword) < 6)` (line 23) | "New password must be at least 6 characters." (400) |
| Wrong current password | `if (!$user \|\| !password_verify($currentPassword, $user['password']))` (line 35) | "Current password is incorrect." (401) |

---

## 3. Client-Side Validation (current state)

### 3.1 Login Form Handler (`js/app.js:1125-1154`)

| Check | Trigger | Error message | Method |
|---|---|---|---|
| Empty email or password | On submit | "Please enter both email and password." | `showAuthError()` (Phase 8 shake + focus) |
| Server rejection | On submit (after fetch) | Server error message (e.g. "Invalid email or password.") | `showAuthError()` |

No email format validation client-side beyond the HTML `type="email"` attribute (browser-native). No real-time (on-input/blur) validation of any kind.

### 3.2 Signup Form Handler (`js/app.js:1086-1122`)

| Check | Trigger | Error message | Method |
|---|---|---|---|
| Any field empty | On submit | "All fields are required." | `showAuthError()` |
| Passwords don't match | On submit | "Passwords do not match." | `showAuthError()` |
| Server rejection | On submit (after fetch) | Server error message (e.g. "Email already registered.") | `showAuthError()` |

**Missing client-side checks (compared to server rules):**
- No minimum password length check (server enforces 6 chars, but client doesn't check — the user sees a generic server error instead)
- No email format validation beyond HTML `type="email"` (server uses `filter_var(FILTER_VALIDATE_EMAIL)`)
- No name-length or content validation

### 3.3 The `.is-invalid` gap

Phase 8's CHANGELOG entry (`CHANGELOG.md:298`) explicitly documents: **"`.is-invalid` is never set anywhere in this codebase."** All validation feedback is exclusively through the `#loginError`/`#signupError` alert divs. Bootstrap's built-in per-field `.is-invalid` class and `.invalid-feedback` elements are not used on any auth form field. This means:

- No per-field visual indication of which specific field has the problem
- No Bootstrap red border on the problematic input
- No field-level help text appearing below the wrong field
- The user must read the alert message at the top of the form and mentally map it to the relevant field

---

## 4. What Phase 8 (Motion Design) Already Shipped

Confirmed by reading `CHANGELOG.md:291-344` and verified against `js/app.js` and `includes/auth_modals.php`:

### 4.1 `showAuthError()` helper (`js/app.js:1074-1081`)

```js
function showAuthError($alert, message) {
  $alert.text(message).removeClass('d-none');
  $alert.addClass('animate__animated animate__shakeX');
  setTimeout(function () {
    $alert.removeClass('animate__animated animate__shakeX');
  }, 500);
  $alert.trigger('focus');
}
```

- Sets alert text and shows it
- Applies `animate__shakeX` (Animate.css) for 500ms error-shake
- Focuses the alert div programmatically (accessibility — screen reader announces the error)
- Used by both login and signup submit handlers

### 4.2 `tabindex="-1"` on error alerts (`includes/auth_modals.php:11, 42`)

Added to `#loginError` and `#signupError` so they're programmatically focusable (`<div>` elements are not natively focusable).

### 4.3 Submit spinner (`PMSMotion.setButtonLoading`)

From Motion Design Phase 2 (verified still in use): both login and signup handlers call `PMSMotion.setButtonLoading($btn, true)` before the fetch and `PMSMotion.setButtonLoading($btn, false)` in the `finally` block. This disables the button, sets `aria-busy="true"`, and shows a `.js-motion-spinner` element.

### 4.4 Modal-open focus (`js/motion.js` `initModalFocus()`)

From Motion Design Phase 1 (verified via Phase 8 regression check): when `#loginModal` opens, focus lands on `#loginEmail`; when `#signupModal` opens, focus lands on `#signupName`. Generic handler covering all modals.

### 4.5 Reduced-motion coverage

The global `@media (prefers-reduced-motion: reduce)` rule in `css/styles.css` already covers `animate__shakeX` — collapses its duration to `0.01ms`. No Phase-8-specific addition was needed.

---

## 5. Existing Strengths

- **Already on a shared partial.** `includes/auth_modals.php` is a single-source include — changes propagate to every page automatically. No per-page duplication to reconcile.
- **`showAuthError()` is well-designed.** Uses a single local `$alert` reference captured per-handler (not re-queried inside the timeout), preventing cross-modal class leaks if the user switches modals quickly. The shake + focus pattern is accessible (screen reader announces the error) and visually clear.
- **Server-side validation is security-aware.** Login doesn't leak which field is wrong ("Invalid email or password"), forgot-password doesn't leak account existence, passwords are hashed with `password_hash(PASSWORD_DEFAULT)`, session stores no password hash.
- **Autocomplete attributes are correctly set.** Login uses `autocomplete="email"` / `autocomplete="current-password"`, signup uses `autocomplete="new-password"` — correct per the HTML spec for password manager compatibility.
- **ARIA foundations are in place.** `aria-labelledby` on modals, `role="alert"` on error divs, `tabindex="-1"` for programmatic focus — the critical accessibility hooks already exist.
- **Backend forgot/reset/change password flows are fully implemented.** The three PHP endpoints are complete, tested (per FEATURES.md), and handle edge cases (code expiry, account-existence leaking, etc.). Only the client-side UI to access them is missing.

---

## 6. Existing Problems

### Critical

- **No Forgot Password UI exists anywhere in the codebase.** The three backend endpoints (`forgot_password.php`, `reset_password.php`, `change_password.php`) are fully implemented and documented in [FEATURES.md](FEATURES.md), but no modal, page, link, or form in the entire customer-facing frontend exposes any of them. A customer who forgets their password has no way to recover their account. This is not a polish issue — it's a missing feature at the UI level.

### High Priority

- **No real-time field validation.** Every validation check fires only on submit. The user fills out the entire form, submits, reads an alert message at the top, mentally maps it to the offending field, and corrects — no immediate feedback as they type or leave a field. This is the gap Phase 8 documented as "`.is-invalid` is never set."
- **No per-field error indication.** Bootstrap's `.is-invalid` class (red border on the input) and `.invalid-feedback` elements (error text below the field) are never used. All errors go to a single alert div at the top of the form, regardless of which field caused the problem.
- **Password rule is not communicated to the user.** The minimum-6-character rule exists on the server (`register.php:24`, `reset_password.php:18`, `change_password.php:23`) but is nowhere visible in the signup form's UI — no helper text, no placeholder, no aria-describedby. A user who enters a 4-character password sees a generic server error ("Please provide a valid name, email, and a password (min 6 chars).") only after submitting.
- **No "Already have an account?" link in the signup modal.** The login modal has a "No account? Sign up" link that switches modals, but the signup modal has no reciprocal link back to login. A user who opens signup by mistake must close the modal and find the login trigger again.

### Medium Priority

- **Client-side validation doesn't match server rules.** The signup handler checks for empty fields and mismatched passwords, but does not check password length (min 6). The login handler checks for empty fields only. Any rule the client doesn't check results in a round-trip to the server for an error the client could have caught instantly.
- **Login success uses `alert()`.** `js/app.js:1145` calls `alert('Login successful!')` — a native browser alert dialog — before reloading the page. This is a jarring UX that blocks the page reload until dismissed, and it bypasses the project's own alert/notification patterns.
- **Registration success uses `alert()`.** Same pattern at `js/app.js:1113` — `alert('Account created successfully! You can now log in.')`.
- **Dead commented-out auth handlers.** `js/app.js:487-504` (login) and `js/app.js:516-541` (signup) contain fully commented-out duplicate handlers from a pre-Phase-8 era. These are confirmed dead code (wrapped in `/* ... */`) that was already present before Phase 8 and left untouched — not a regression, but dead weight.
- **No password visibility toggle.** Password fields have no show/hide button — a common accessibility and usability feature, especially on mobile where typos are frequent and the user cannot verify what they typed.

### Low Priority

- **No "remember me" functionality.** Session-only authentication — closing the browser logs the user out. Not necessarily wrong for a university project, but worth noting as a UX gap.
- **`loginAsAdminBtn` is a redirect, not a role switch.** The "Login as Admin" button in the login modal navigates to `admin-login.php` (a separate page) rather than adding an admin login option within the modal — this is intentional separation of admin/customer auth, not a bug, but it means the button leaves the modal context unexpectedly.

---

## 7. Documentation Conflicts (flagged, not silently resolved)

- [COMPONENT_LIBRARY.md](COMPONENT_LIBRARY.md) §0 states login/signup modals are "duplicated verbatim 5 times." **This is stale.** Direct inspection confirms `includes/auth_modals.php` is a shared partial included by all customer-facing pages — the Shared Components phase already consolidated this. The five-copy duplication no longer exists.
- [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) still lists the old palette (`--primary: #2d4a9e`, `--accent: #d97706`). The current palette is `--primary: #0F2A4D`, `--secondary: #2F6FED`, `--accent: #63A8FF`, `--background: #F8FAFC` (confirmed from `css/styles.css:6-9`). This conflict was already flagged in [ABOUT_US_ANALYSIS.md](ABOUT_US_ANALYSIS.md) §5 and remains unresolved.
- [UI_IMPLEMENTATION_PLAN.md](UI_IMPLEMENTATION_PLAN.md) Phase 6 lists "Forgot Password" as a task alongside Login, Register, Validation, and Accessibility. This analysis confirms that Forgot Password requires **new UI construction** (modal + multi-step form), not just modernization of existing UI — the scope is significantly larger than the other Phase 6 items, which are improvements to existing modals.
- [MOTION_DESIGN_ANALYSIS.md](MOTION_DESIGN_ANALYSIS.md) §8.7 lists `login.php`, `register.php`, `forgot_password.php`, `reset_password.php`, `change_password.php` as "server-rendered form pages, not modals." This is **partially inaccurate** — login and register are modals (in `includes/auth_modals.php`), not standalone pages. The `.php` files at the root (`login.php`, `register.php`) are JSON API endpoints, not rendered pages. `forgot_password.php`, `reset_password.php`, and `change_password.php` are also API-only endpoints with no rendered HTML.

---

## 8. Recommended Scope Structure

Per [UI_IMPLEMENTATION_PLAN.md](UI_IMPLEMENTATION_PLAN.md) Phase 6's task list (Login, Register, Forgot Password, Validation, Accessibility), evaluated against the current implementation and Phase 8's shipped work:

1. **Login Modal Improvements** — real-time field validation, per-field error states, replace `alert()` success with a proper UX pattern
2. **Signup Modal Improvements** — real-time field validation (including password-length feedback matching the actual 6-char rule), per-field error states, add "Already have an account?" link, replace `alert()` success, password visibility toggle
3. **Forgot Password UI** — **new build**: multi-step modal (email entry → code entry → new password), since no UI exists today despite the backend being complete
4. **Shared Validation Infrastructure** — the real-time validation logic, `.is-invalid`/`.invalid-feedback` wiring, and coexistence rules with the existing `showAuthError()` alert+shake path
5. **Accessibility Pass** — confirm heading hierarchy, focus management, ARIA attributes, keyboard navigation, and reduced-motion coverage across all auth modals after the above changes

---

## 9. Section-by-Section Analysis

### 9.1 Login Modal

- **Purpose:** Customer authentication — email + password.
- **Content:** Two input fields, two buttons, one error alert, one "No account?" link.
- **Existing content reusable:** All current markup is reusable as the foundation — this is an enhancement pass, not a rebuild.
- **Components to reuse:** `.glassmorph` modal styling (already applied), `showAuthError()` (Phase 8), `PMSMotion.setButtonLoading` (Phase 2), `.form-control` Bootstrap inputs.
- **New components needed:**
  - `.invalid-feedback` elements below each field for per-field error messages
  - Real-time validation listeners (`blur`/`input`) that add `.is-invalid` / `.is-valid` to fields
  - A "Forgot your password?" link — currently entirely absent, needed to launch the new forgot-password flow
- **Data requirements:** Static (no new server endpoints needed — `login.php` is unchanged).
- **Responsive behavior:** Current `p-2 p-sm-4` padding and modal sizing are already responsive. No change anticipated.
- **Accessibility:** `role="alert"` and `tabindex="-1"` already present on `#loginError`. New `.invalid-feedback` elements should use `aria-describedby` linking each feedback to its input. Focus should move to the first `.is-invalid` field after an on-submit validation failure (complementing, not replacing, the existing alert-focus behavior).

### 9.2 Signup Modal

- **Purpose:** Customer registration — name, email, password, confirm password.
- **Content:** Four input fields, one button, one error alert.
- **Existing content reusable:** All current markup.
- **Components to reuse:** Same as login (`.glassmorph`, `showAuthError()`, `PMSMotion.setButtonLoading`, `.form-control`).
- **New components needed:**
  - `.invalid-feedback` elements for each field with field-specific messages
  - Password helper text (e.g. "Minimum 6 characters") — currently nowhere visible in the UI, despite being the actual server rule
  - "Already have an account? Log in" reciprocal link (missing — login modal has the reverse link, but signup does not)
  - Password visibility toggle (optional, recommended for usability)
  - Real-time password-match indicator for the confirm-password field
- **Data requirements:** Static (no new server endpoints — `register.php` is unchanged).
- **Responsive behavior:** Same as login — already handled.
- **Accessibility:** Same `.invalid-feedback` + `aria-describedby` pattern as login. Password helper text should be linked to `#signupPassword` via `aria-describedby` so screen readers announce the rule when the field is focused.

### 9.3 Forgot Password (New Build)

- **Purpose:** Allow a customer who forgot their password to recover their account via a 6-digit emailed code.
- **Content:** **Entirely new UI.** No modal, form, link, or page exists anywhere in the current frontend for this flow. The backend is fully implemented across three endpoints.
- **Existing content reusable:** None (UI-wise). Backend endpoints (`forgot_password.php`, `reset_password.php`) are complete and unchanged.
- **Components to reuse:** `.glassmorph` modal styling (matching login/signup), `showAuthError()` pattern (for error display), `PMSMotion.setButtonLoading` (for submit states), brand CSS variables.
- **New components needed:**
  - A new modal (`#forgotPasswordModal` or similar) with a multi-step internal flow:
    - **Step 1 — Email entry:** single email field + "Send Reset Code" button → calls `forgot_password.php`
    - **Step 2 — Code entry:** 6-digit code field + email (hidden/readonly, carried from Step 1) + "Verify Code" button
    - **Step 3 — New password:** new password + confirm password fields → calls `reset_password.php`
  - A "Forgot your password?" link in the login modal that dismisses login and opens the forgot-password modal
  - A "Back to login" link in the forgot-password modal
  - Per-step validation and error display
- **Data requirements:** Calls `forgot_password.php` (POST email → sends code) and `reset_password.php` (POST email + code + new_password → resets). No new server-side work needed.
- **Responsive behavior:** Must match login/signup modal patterns exactly — same `.modal-dialog` sizing, same `p-2 p-sm-4` padding.
- **Accessibility:** Same ARIA patterns as login/signup. Step transitions should manage focus appropriately (focus the first input of each new step). The 6-digit code input should have clear labeling ("Enter the 6-digit code sent to your email").

### 9.4 Validation Infrastructure

- **Purpose:** Fill the `.is-invalid`-never-set gap documented by Phase 8, adding real-time per-field validation to all auth forms.
- **Scope:** Shared validation logic used by login, signup, and forgot-password modals.
- **Key design decisions:**
  - **Trigger:** `blur` for initial validation (don't interrupt typing), then `input` for real-time correction once a field has been marked invalid (so the error clears as soon as the user fixes it)
  - **Coexistence with `showAuthError()`:** Real-time validation prevents most errors from reaching the submit handler. For errors that do reach submit (server-side rejections like "Email already registered" or "Invalid email or password"), the existing `showAuthError()` alert+shake+focus path fires exactly as it does today — no change needed to that code path. The two systems are complementary, not competing.
  - **Field-level error clearing on submit:** Before a submit-triggered `showAuthError()` fires, clear any stale per-field `.is-invalid` states from the real-time validation pass, so the user doesn't see conflicting signals.
  - **Interaction with Phase 8's shake/focus:** The alert-level shake and focus remain the response for on-submit errors (especially server errors where no single field is at fault, like "Invalid email or password"). Real-time validation eliminates most on-submit errors by catching them earlier — it doesn't replace the shake/focus, it reduces how often it's needed.

**Per-field validation rules (derived from server endpoints):**

| Form | Field | Real-time rule | Message |
|---|---|---|---|
| Login | Email | Non-empty + basic email format (contains `@`) | "Please enter your email address." / "Please enter a valid email address." |
| Login | Password | Non-empty | "Please enter your password." |
| Signup | Name | Non-empty | "Please enter your name." |
| Signup | Email | Non-empty + email format | Same as login |
| Signup | Password | Non-empty + min 6 chars | "Please enter a password." / "Password must be at least 6 characters." |
| Signup | Confirm Password | Non-empty + matches password | "Please confirm your password." / "Passwords do not match." |
| Forgot (Step 1) | Email | Non-empty + email format | Same as login |
| Forgot (Step 3) | New Password | Non-empty + min 6 chars | Same as signup password |
| Forgot (Step 3) | Confirm Password | Non-empty + matches new password | Same as signup confirm |

### 9.5 Accessibility

- **Purpose:** Ensure all auth modals meet WCAG AA and the project's established accessibility patterns.
- **Already in place (Phase 8 + Phase 1):** `role="alert"` on error divs, `tabindex="-1"` for programmatic focus, modal-open focus management, `aria-labelledby` on modals, `aria-hidden="true"` on modals, reduced-motion coverage for shake animation.
- **New requirements from this phase:**
  - `aria-describedby` on each input, linking to its `.invalid-feedback` element (so screen readers announce the error when the field is focused)
  - Password helper text linked via `aria-describedby` to the password field
  - Focus management on step transitions in the forgot-password modal
  - Keyboard navigation verification: Tab order through all fields, Enter to submit, Escape to close modal
  - Focus return to the triggering element when a modal is dismissed (Bootstrap handles this by default, but verify it still works with the modal-switching patterns: login → signup, login → forgot-password)

---

## 10. Component Reuse

| Component | Source | Reuse in Authentication |
|---|---|---|
| `includes/auth_modals.php` | Shared Components phase | Foundation — all login/signup markup lives here, extended with new elements |
| `.glassmorph` modal styling | Homepage phase (`css/styles.css`) | Already applied to both modals; apply to new forgot-password modal |
| `showAuthError()` | Motion Design Phase 8 (`js/app.js:1074-1081`) | Keep for on-submit and server-error display; real-time validation is additive, not a replacement |
| `PMSMotion.setButtonLoading()` | Motion Design Phase 2 (`js/motion.js`) | Keep for submit states on all auth forms including the new forgot-password form |
| `initModalFocus()` | Motion Design Phase 1 (`js/motion.js`) | Already handles focus-on-open for all modals; will automatically cover the new forgot-password modal |
| `.form-control` | Bootstrap 5 | Already used on all auth inputs |
| `.is-invalid` / `.invalid-feedback` | Bootstrap 5 (built-in, currently unused) | New — the core of the real-time validation layer |
| `.is-valid` / `.valid-feedback` | Bootstrap 5 (built-in, currently unused) | Optional — green checkmark on valid fields; use sparingly (password-match confirmation is the strongest candidate) |
| `animate__shakeX` | Animate.css (already loaded) | Already used by `showAuthError()`; unchanged |
| Reduced-motion global rule | `css/styles.css` (Foundation Debt Cleanup) | Already covers all animations including shake; no additions needed |

**No new shared partials are required.** All new UI elements are additions to the existing `includes/auth_modals.php` (modals) and `js/app.js` (handlers/validation logic). No new CSS file or JS file is anticipated — additions go into `css/styles.css` and `js/app.js` respectively, following the project's established pattern.

---

## 11. Content/Data Requirements

**Confirmed existing, reusable as-is:**
- Login modal markup and handler
- Signup modal markup and handler
- `showAuthError()` helper and all Phase 8 accessibility work
- All five backend auth endpoints (`login.php`, `register.php`, `forgot_password.php`, `reset_password.php`, `change_password.php`)
- Password rule: minimum 6 characters, no complexity requirement

**No new server-side work required.** Every backend endpoint this phase needs already exists and is fully functional. This is purely a frontend/UI phase.

**No missing content requiring user input.** Unlike the About Us phase (which was blocked on Mission/Vision copy and team roles), the Authentication phase has everything it needs — the password rule is known (6 chars), the server endpoints are defined, and the error messages are established.

---

## 12. Responsive Requirements

- All auth modals must render correctly at the project's standard test breakpoints: 375px, 768px, 992px, 1400px.
- The existing `p-2 p-sm-4` responsive padding on `.modal-content` must be preserved.
- `.invalid-feedback` text must not overflow or clip at narrow widths (375px).
- The forgot-password modal's multi-step flow must be usable at 375px — step indicators (if any) must not overflow, and the 6-digit code input must be comfortably tappable on mobile.
- Password visibility toggle (if implemented) must have a touch target of at least 44×44px on mobile.

---

## 13. Accessibility Requirements

- Maintain all Phase 8 accessibility work: `role="alert"`, `tabindex="-1"`, programmatic focus on error, `aria-busy` on submit buttons during loading.
- Add `aria-describedby` linking each input to its `.invalid-feedback` element and any helper text.
- Add `aria-live="polite"` or `role="status"` for real-time validation feedback that appears without a page reload or form submission.
- Verify focus management across modal transitions (login ↔ signup, login → forgot-password → login).
- Verify keyboard navigation: Tab through all fields in order, Enter submits the form, Escape closes the modal.
- Verify reduced-motion: all new animations (if any) must be covered by the existing global reduced-motion rule or gain their own `prefers-reduced-motion` guard.
- WCAG AA color contrast on all new text elements (`.invalid-feedback` red text, helper text, any new links).

---

## 14. Risks and Dependencies

| Risk | Severity | Notes |
|---|---|---|
| Real-time validation conflicting with `showAuthError()` on-submit | Medium | Must be designed to coexist, not compete — spec'd in §9.4. Real-time catches pre-submit errors; `showAuthError()` handles post-submit server errors. Clear stale `.is-invalid` states before showing the alert. |
| Forgot-password modal size (3-step flow in a single modal) | Medium | Multi-step modals can feel cramped at 375px. Consider using Bootstrap's `.modal-dialog-centered` and keeping each step minimal (one or two fields). Test at 375px explicitly. |
| `mail()` not working on the development/deployment server | Medium | The forgot-password flow depends on the user receiving an email with the 6-digit code. `forgot_password.php` uses PHP's native `mail()` with no SMTP configuration — already flagged in [FEATURES.md](FEATURES.md) and [PROJECT_AUDIT.md](PROJECT_AUDIT.md) as potentially non-functional depending on the server. This is a pre-existing backend limitation, not introduced by this phase, but it directly impacts whether the forgot-password UI can be meaningfully tested end-to-end. |
| Dead commented-out auth handlers in `js/app.js` | Low | `js/app.js:487-504` and `:516-541` contain old, commented-out login/signup handlers. These are inert (confirmed `/* ... */` wrapped) and don't execute, but they're confusing to read alongside the real handlers. Recommend removing during this phase's implementation as a low-risk cleanup. |
| Bootstrap version compatibility for `.is-invalid` behavior | Low | Bootstrap 5.3.2 (the version loaded by this project) fully supports `.is-invalid`, `.invalid-feedback`, and `.was-validated` — no version risk. |

---

## 15. Testing Requirements

For each implementation step:

- `php -l` on any modified PHP file (no syntax errors).
- Visual check in-browser at 375px, 768px, 992px, 1400px.
- Confirm no console JS errors.
- Real-time validation: type in each field, blur away, confirm `.is-invalid`/`.invalid-feedback` appear correctly; correct the field, confirm they clear.
- On-submit validation: submit with invalid data, confirm `showAuthError()` fires with shake + focus (Phase 8 behavior preserved).
- Server-error path: submit with valid-looking but server-rejected data (e.g. wrong password for login, duplicate email for signup), confirm server error appears via `showAuthError()`.
- Login success: confirm the page reloads and the user is logged in (navbar auth area updates).
- Registration success: confirm the signup modal closes and the login modal opens.
- Forgot password (once built): test the full 3-step flow — email entry → code display (verify server response) → new password → success → redirect to login.
- Modal transitions: login → signup (existing link), signup → login (new link), login → forgot-password (new link), forgot-password → login (new link). Confirm focus management works on each transition.
- Keyboard navigation: Tab through every field, Enter to submit, Escape to close.
- Reduced motion: confirm no new unguarded animations.
- Cross-page regression: confirm auth modals work identically on `index.php`, `vehicles.php`, `about.php`, `faq.php`, `transactions.php` (since they all include `auth_modals.php`).

---

## 16. Done / Partial / Not Started Summary

| Item | Status | Details |
|---|---|---|
| Login modal markup | **Done** | Shared partial, correct ARIA, Phase 8 tabindex |
| Signup modal markup | **Done** | Same shared partial, Phase 8 tabindex |
| Login submit handler | **Done** | Phase 8 `showAuthError()` + shake + focus |
| Signup submit handler | **Done** | Phase 8 `showAuthError()` + shake + focus |
| Submit spinner (loading state) | **Done** | Phase 2 `PMSMotion.setButtonLoading` on both forms |
| Modal-open focus | **Done** | Phase 1 `initModalFocus()` — auto-focuses first input |
| Reduced-motion coverage | **Done** | Global rule covers all current animations |
| Error alert shake animation | **Done** | Phase 8 `animate__shakeX` on `#loginError`/`#signupError` |
| Autocomplete attributes | **Done** | Correctly set on all fields |
| Login → Signup modal link | **Done** | "No account? Sign up" link in login modal |
| Signup → Login modal link | **Not started** | Missing entirely — no reciprocal link |
| Real-time field validation | **Not started** | `.is-invalid` never set anywhere; all validation is on-submit only |
| Per-field error messages | **Not started** | No `.invalid-feedback` elements exist on any auth field |
| Password rule communication | **Not started** | Min 6 chars rule exists on server but is invisible in the UI |
| Password visibility toggle | **Not started** | No show/hide button on any password field |
| Forgot Password UI | **Not started** | Backend fully implemented; zero UI exists — entire modal + multi-step flow must be built |
| `alert()` replacement (login/signup success) | **Not started** | Both handlers use native `alert()` for success messages |
| Client-side password-length check | **Not started** | Server enforces 6-char min, client doesn't check before submit |

**Summary:** The foundation is solid — Phase 8's shake/focus/spinner work provides a complete on-submit error-feedback system, and the shared partial architecture means changes propagate everywhere automatically. The genuine new build work is: (1) the real-time validation layer (`.is-invalid` + per-field messages, coexisting with `showAuthError()`), (2) the forgot-password modal (entirely new, 3-step flow), and (3) several targeted UX improvements (reciprocal modal links, password helper text, `alert()` replacement). The backend is 100% complete and untouched by this phase.

---

*This document is analysis only. No code has been modified. Awaiting review and approval before proceeding to `docs/AUTHENTICATION_IMPLEMENTATION_PLAN.md`, per the project's established workflow.*
