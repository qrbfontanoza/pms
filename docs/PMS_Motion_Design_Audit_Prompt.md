# PMS Interactive UX & Motion Design Audit — Claude Project Prompt

# Phase — Interactive UX, Motion Design & Micro-Interactions

We have completed and verified several major UI implementation phases of the PMS Car Rental website.

Current completed areas include:

- Shared Components
- Homepage
- Vehicle Listing
- About Us
- Vehicle Details is currently being implemented/planned

The website is built using the existing PMS architecture, including:

- PHP
- Bootstrap 5
- HTML
- CSS
- JavaScript/jQuery
- Existing PHP/MySQL architecture

The goal of this phase is NOT to immediately implement animations.

The goal is to perform a complete UX and motion-design audit of the existing website and create a professional, practical, and implementation-ready motion design strategy.

You are acting as a:

- Senior UX Designer
- Senior UI Designer
- Senior Front-End Engineer
- Motion Design Specialist
- Interaction Design Specialist
- UX Researcher

Your responsibility is to determine:

> Where should PMS use motion, what type of motion should be used, why it should be used, how it should behave, and where motion should NOT be used.

Do not simply add animations everywhere.

Motion must have a UX purpose.

---

# PRIMARY OBJECTIVE

Analyze the entire current PMS website and create a comprehensive but practical:

# PMS Motion & Interaction Design System

The final recommendations should make the website:

- More interactive
- More modern
- More professional
- More visually engaging
- Easier to understand
- More responsive to user actions
- More polished
- More consistent

WITHOUT making it:

- Distracting
- Slow
- Over-animated
- Difficult to navigate
- Visually chaotic
- Unprofessional
- Inaccessible
- Performance-heavy

The final result should feel appropriate for a professional modern car-rental website.

---

# IMPORTANT

DO NOT IMPLEMENT ANYTHING YET.

This is an analysis and design-planning phase.

Do not modify:

- PHP files
- HTML
- CSS
- JavaScript
- Database
- Components
- Configuration

The only output at this stage should be the analysis/documentation.

---

# STEP 1 — READ THE PROJECT DOCUMENTATION

Before analyzing the website, read the latest versions of all relevant project documentation.

At minimum, read:

- CLAUDE.md
- CHANGELOG.md
- DESIGN_SYSTEM.md
- COMPONENT_LIBRARY.md
- UI_ANALYSIS.md
- UI_IMPLEMENTATION_PLAN.md

Read the documentation for:

- Shared Components
- Homepage
- Vehicle Listing
- About Us
- Vehicle Details

Also read any other documentation relevant to:

- UX
- UI
- Components
- Front-end architecture
- JavaScript
- Responsive behavior

IMPORTANT:

The repository is the ultimate source of truth.

If the documentation says something exists but the implementation does not contain it, report the discrepancy.

If the implementation contains something not documented, report it.

Do not assume that old documentation is still accurate.

---

# STEP 2 — AUDIT THE ENTIRE CURRENT WEBSITE

Inspect the actual implementation.

Do not analyze only the homepage.

Inspect every available customer-facing page and relevant admin-facing interface.

Determine the current structure of:

## Customer-facing pages

Examples may include:

- Homepage
- Vehicle Listing
- Vehicle Details
- About Us
- Contact Us
- Login
- Registration
- Booking/Reservation
- Profile
- Other available customer pages

## Admin pages

Inspect the available:

- Dashboard
- Vehicle management
- User management
- Voucher management
- Other admin interfaces

The goal is to understand the entire interaction ecosystem.

---

# STEP 3 — INVENTORY ALL INTERACTIVE ELEMENTS

Create an inventory of interactive UI elements.

Identify:

### Navigation

- Navbar
- Navigation links
- Dropdowns
- Mobile menu
- Sticky navigation
- Active navigation states

### Buttons

- Primary buttons
- Secondary buttons
- CTA buttons
- Reserve buttons
- View Details buttons
- Submit buttons
- Cancel buttons

### Cards

- Vehicle cards
- Feature cards
- Information cards
- Team cards
- Promotional cards

### Forms

- Login
- Registration
- Contact
- Search
- Filters
- Booking
- Profile

### Modals

- Authentication modals
- Vehicle Details modal
- Booking modal
- Confirmation modal
- Other modals

### Feedback

- Alerts
- Errors
- Success messages
- Validation messages
- Loading states

### Other interactions

- Search
- Filters
- Sorting
- Tabs
- Accordions
- Carousels
- Image galleries
- Counters
- Dropdowns
- Tooltips
- Pagination

For every interactive element, determine whether it currently has:

- Hover state
- Focus state
- Active state
- Loading state
- Transition
- Animation
- Feedback
- Error state
- Success state

---

# STEP 4 — AUDIT CURRENT MOTION

Determine what motion already exists.

Search the entire front-end implementation for:

- CSS transitions
- CSS animations
- Keyframes
- Transformations
- Hover animations
- JavaScript animations
- jQuery animations
- Bootstrap transitions
- Modal animations
- Carousel animations
- Scroll-triggered behavior
- Existing animation libraries

Document:

- Where motion already exists
- What it does
- Whether it is useful
- Whether it is inconsistent
- Whether it should be preserved
- Whether it should be modified
- Whether it should be removed

Do not remove anything.

---

# STEP 5 — ANALYZE THE USER JOURNEY

Analyze PMS from the perspective of an actual customer.

Trace the journey:

```text
Landing on PMS
      ↓
Understanding the service
      ↓
Browsing vehicles
      ↓
Searching/filtering
      ↓
Viewing vehicle details
      ↓
Deciding to reserve
      ↓
Authentication
      ↓
Booking
      ↓
Confirmation
```

Determine where motion can help communicate:

- Hierarchy
- Direction
- Feedback
- State changes
- Progress
- Relationships
- Important information
- User actions

For each opportunity, explain WHY motion helps.

---

# STEP 6 — MOTION DESIGN PRINCIPLES

Do not recommend animation simply because it looks impressive.

Every recommended animation must have at least one purpose:

### 1. Orientation

Helps the user understand where they are.

### 2. Feedback

Confirms that an action occurred.

### 3. Continuity

Shows the relationship between states.

### 4. Hierarchy

Draws attention to important information.

### 5. Guidance

Helps users understand where to look or what to do next.

### 6. Delight

Adds subtle personality where appropriate.

### 7. Perceived performance

Makes loading or state changes feel smoother.

If an animation does not serve one of these purposes, question whether it is necessary.

---

# STEP 7 — RESEARCH OPEN-SOURCE MOTION RESOURCES

Use these resources as references for motion patterns and capabilities.

## AOS — Animate On Scroll

Research:

- Scroll reveal
- Fade
- Slide
- Zoom
- Stagger
- Timing
- Delay

Use it primarily as a reference for scroll-triggered section reveals.

---

## Anime.js

Research:

- ScrollObserver
- Scroll-triggered animation
- Enter/leave behavior
- Scroll progress
- Stagger
- Easing
- Synchronization

Official documentation:

https://animejs.com/

Use it as a reference for richer JavaScript-driven interactions.

---

## Animate.css

Research:

- Fade
- Slide
- Zoom
- Attention effects
- Entrance animations

Use it primarily as a reference library for simple animation patterns.

---

## GSAP + ScrollTrigger

Research:

- Scroll-triggered animation
- Scroll-linked animation
- Stagger
- Timeline
- Pinning
- Scrubbing
- Section transitions
- Parallax

Official documentation:

https://gsap.com/docs/v3/Plugins/ScrollTrigger/

Do NOT recommend GSAP simply because it is powerful.

Only recommend it where the PMS UX genuinely benefits from advanced motion.

---

# STEP 8 — DO NOT DEFAULT TO A SINGLE LIBRARY

Evaluate whether PMS actually needs an animation library.

Compare:

### Option A

Pure CSS + IntersectionObserver

### Option B

AOS

### Option C

Anime.js

### Option D

GSAP + ScrollTrigger

### Option E

Hybrid approach

For each option evaluate:

- Complexity
- Performance
- Maintainability
- Learning curve
- Bundle size/overhead
- Compatibility with current PMS architecture
- Bootstrap 5 compatibility
- jQuery compatibility
- Mobile behavior
- Accessibility
- Future maintainability

Then recommend the most appropriate approach for THIS project.

Do not choose a library simply because it can produce more impressive animations.

---

# STEP 9 — PAGE-BY-PAGE MOTION ANALYSIS

Analyze every major page individually.

For each page identify:

## Page

Example:

Homepage

### Existing elements

List the actual components.

### Recommended motion

For every element provide:

- Element
- Motion type
- Trigger
- Direction
- Duration
- Delay
- Easing
- Repeat behavior
- Mobile behavior
- Purpose
- Priority

Example:

| Element | Motion | Trigger | Duration | Purpose | Priority |
|---|---|---|---|---|---|
| Hero heading | Fade + slight upward movement | Page load | 600ms | Establish hierarchy | High |
| Hero CTA | Fade + upward movement | After heading | 500ms | Guide user | High |
| Vehicle cards | Fade-up + stagger | Viewport entry | 500ms | Reveal content | High |
| Vehicle image | Subtle scale on hover | Hover | 300ms | Interactive feedback | Medium |

Do this for:

- Homepage
- Vehicle Listing
- Vehicle Details
- About Us
- Contact Us
- Booking
- Authentication
- Profile
- Other customer pages
- Admin Dashboard
- Relevant admin pages

Only include pages that actually exist in the project.

Do not invent pages.

---

# STEP 10 — SCROLL MOTION STRATEGY

Create a specific scroll-motion system.

Define:

### Section entrance

Example:

Fade + translateY

### Stagger

Example:

100ms between cards

### Image reveal

Example:

Subtle scale + fade

### Counters

Example:

Count-up when entering viewport

### Parallax

Identify ONLY sections where it genuinely improves the experience.

### Sticky elements

Determine whether any elements should react to scrolling.

### Scroll-linked effects

Identify where they would provide meaningful UX value.

IMPORTANT:

Avoid excessive parallax.

Avoid animations on every section.

Avoid animations that make users wait for content.

---

# STEP 11 — MICRO-INTERACTION SYSTEM

Define interaction behavior for:

### Buttons

- Hover
- Focus
- Active
- Loading
- Disabled

### Vehicle cards

- Hover
- Focus
- Image interaction
- CTA interaction

### Forms

- Focus
- Validation
- Error
- Success
- Loading

### Modals

- Opening
- Closing
- Content appearance
- Loading state

### Navigation

- Sticky behavior
- Mobile menu
- Active state

### Filters/search

- Applying filters
- Clearing filters
- Loading
- Empty state

### Booking

- Step progression
- Validation
- Confirmation

---

# STEP 12 — MOTION HIERARCHY

Create a hierarchy so that not everything animates equally.

Define:

## Level 1 — Essential Motion

Used for:

- Important state changes
- Primary actions
- Navigation
- Modal transitions
- Feedback

## Level 2 — Supporting Motion

Used for:

- Cards
- Section reveals
- Images
- Secondary interactions

## Level 3 — Decorative Motion

Used sparingly for:

- Hero accents
- Background elements
- Subtle visual polish

Make clear that Level 1 should always receive priority over decorative motion.

---

# STEP 13 — MOTION TOKENS

Create recommended PMS motion tokens.

For example:

### Duration

- Fast
- Normal
- Slow

### Delay

- None
- Short
- Medium
- Long

### Easing

- Standard
- Emphasized
- Entrance
- Exit

### Distance

- Small
- Medium
- Large

### Scale

- Subtle
- Standard

Do not choose arbitrary values.

Recommend a consistent system that can be reused throughout the website.

---

# STEP 14 — ACCESSIBILITY

Motion must support accessibility.

Specifically analyze:

`prefers-reduced-motion`

Determine how animations should behave for users who prefer reduced motion.

Recommended behavior may include:

- Disable decorative motion
- Remove large transforms
- Preserve essential state transitions
- Keep content immediately accessible

Also identify:

- Focus visibility
- Keyboard interaction
- Screen-reader considerations
- Motion-induced usability problems

---

# STEP 15 — PERFORMANCE

Evaluate:

- Number of animations
- Scroll listeners
- DOM manipulation
- JavaScript execution
- Layout/reflow risks
- Large images
- Mobile performance
- Animation frequency
- Third-party libraries

Prefer performant properties such as:

- transform
- opacity

Avoid unnecessary animations involving expensive layout changes.

---

# STEP 16 — CREATE A MOTION PRIORITY MATRIX

Create a table:

| Page | Element | Motion | Priority | Complexity | UX Benefit |
|---|---|---|---|---|---|

Use:

### Priority

P0 — Essential

P1 — Recommended

P2 — Optional

P3 — Avoid / unnecessary

### Complexity

Low

Medium

High

This should allow the development team to implement the highest-value motion first.

---

# STEP 17 — IDENTIFY WHAT SHOULD NOT ANIMATE

This is extremely important.

Create a section:

# Elements That Should Remain Static

Identify elements where animation would:

- Distract
- Slow the user
- Reduce usability
- Reduce accessibility
- Create visual noise
- Hurt performance
- Make the interface feel less professional

Examples might include:

- Dense data tables
- Critical form labels
- Important pricing information
- Legal information
- Admin data-heavy interfaces

Base the recommendations on the actual PMS implementation.

---

# STEP 18 — FINAL RECOMMENDED MOTION ARCHITECTURE

Provide a final recommendation for how PMS should technically implement motion.

Compare:

```text
CSS
+
IntersectionObserver
```

vs.

```text
AOS
```

vs.

```text
Anime.js
```

vs.

```text
GSAP + ScrollTrigger
```

vs.

```text
Hybrid
```

Recommend ONE primary approach.

If a hybrid approach is recommended, clearly define:

- What CSS handles
- What JavaScript handles
- What library handles
- Where advanced animation is allowed

Avoid unnecessary dependencies.

---

# STEP 19 — CREATE THE DOCUMENT

Create:

`MOTION_DESIGN_ANALYSIS.md`

The document must contain:

1. Executive Summary
2. Current PMS UX Assessment
3. Current Motion Audit
4. User Journey Analysis
5. Motion Design Principles
6. Open-Source Resource Research
7. Recommended Motion Architecture
8. Page-by-Page Motion Analysis
9. Scroll Motion Strategy
10. Micro-Interaction Strategy
11. Motion Hierarchy
12. PMS Motion Tokens
13. Accessibility Strategy
14. Performance Strategy
15. Motion Priority Matrix
16. Elements That Should Remain Static
17. Recommended Implementation Order
18. Risks
19. Acceptance Criteria
20. Final Recommendations

The document should be detailed enough for another developer to implement without having to make major UX decisions themselves.

However, do not fill the document with unnecessary theory.

Prioritize actionable recommendations.

---

# STEP 20 — IMPLEMENTATION PLAN

After completing the analysis, create:

`MOTION_DESIGN_IMPLEMENTATION_PLAN.md`

The implementation plan should divide the work into manageable phases.

Recommended structure:

```text
Motion Design Foundation
        ↓
Global Micro-Interactions
        ↓
Homepage Motion
        ↓
Vehicle Listing Motion
        ↓
Vehicle Details Motion
        ↓
About Us Motion
        ↓
Contact / Booking Motion
        ↓
Authentication Motion
        ↓
Admin Motion
        ↓
Accessibility / Reduced Motion
        ↓
Performance Optimization
        ↓
Final Motion QA
```

Modify this sequence if your analysis finds a better dependency order.

For each phase specify:

- Objective
- Files involved
- Components involved
- Motion patterns
- Technical approach
- Dependencies
- Risks
- Testing requirements
- Acceptance criteria

---

# IMPORTANT — DO NOT IMPLEMENT

At the end of the analysis and implementation plan:

STOP.

Do not modify the actual website.

Do not install libraries.

Do not add animations.

Do not modify CSS.

Do not modify JavaScript.

Do not modify PHP.

Wait for my approval.

---

# QUALITY STANDARD

Think like a senior UX/motion designer reviewing a production website.

Do not ask:

> "Where can we add an animation?"

Instead ask:

> "Where does motion improve the user's understanding, feedback, navigation, or perception of the interface?"

The final PMS experience should feel:

- Modern
- Smooth
- Professional
- Intentional
- Consistent
- Fast
- Accessible

The goal is NOT:

"Make everything move."

The goal is:

"Use motion deliberately to make the interface easier and more enjoyable to use."
