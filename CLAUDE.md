# PMS Car Rental Management System

## Project Overview

This repository contains an existing PHP-based Car Rental Management System developed as a university project.

The purpose of this repository is to incrementally enhance the existing system for the Systems Integration and Architecture course.

This project should be treated as an actively maintained production codebase rather than a prototype.

The primary objective is to improve and extend the current implementation while preserving existing functionality.

## Project Objectives

The final system should include:

- Fully deployed web application
- Cloud-hosted MySQL database
- REST API for mobile integration
- Mobile application consuming the same backend
- Modern responsive UI
- Improved maintainability
- Free third-party API integrations where appropriate
- Stable and documented architecture

Unless explicitly requested, enhancements should extend the existing implementation rather than replace it.

## Development Philosophy

Always follow this workflow:

Understand

↓

Analyze

↓

Explain

↓

Plan

↓

Implement

↓

Test

↓

Document

Never skip directly to implementation.

When uncertain, inspect more code rather than making assumptions.

## Architecture

The application follows a traditional PHP architecture centered around index.php.

Expect shared includes such as:

- includes/
- assets/
- uploads/

Do not assume MVC unless verified from the code.

Always inspect the current implementation before recommending architectural changes.

## Current Assignment

Current priority:

Front-End/UI improvements.

Primary focus:

- HTML/PHP structure
- CSS
- Bootstrap 5
- JavaScript
- jQuery
- Responsive Design
- Accessibility

Backend modifications should only be suggested unless explicitly requested.

Future tasks may include:

- REST APIs
- Database improvements
- Deployment
- Mobile integration

## Working Rules

Before making any code changes:

Inspect relevant files.

Identify dependencies.

Explain current implementation.

Identify possible side effects.

Recommend the safest solution.

Wait for approval before major architectural modifications.

Never assume project behavior.

Always verify using the source code.

## Communication

Always:

Explain reasoning.

Reference inspected files.

Clearly distinguish verified facts from assumptions.

Ask questions when requirements are ambiguous.

Avoid unnecessary complexity.

Prefer practical engineering explanations.

## Bootstrap Standards

Prefer Bootstrap utilities.

Avoid unnecessary custom CSS.

Keep layouts responsive.

Use consistent spacing.

Avoid inline styling.

Maintain accessibility.

Never introduce new colors unless approved.

All UI should follow the project Design System.

## Testing

Before considering any task complete:

Verify affected functionality.

Check console errors.

Check responsive layouts.

Verify Bootstrap components.

Identify possible regressions.

Summarize testing performed.

## Git Workflow

Prefer small logical commits.

Never rewrite git history.

Never delete branches.

Summarize modified files.

Recommend commit messages.

## Documentation

Whenever a major feature is completed:

Update relevant documentation.

Possible files:

PROJECT_AUDIT.md

FEATURES.md

BUGS.md

README.md

CHANGELOG.md

## Decision Priority

When multiple solutions exist, prioritize in this order:

1. Preserve existing functionality
2. Correctness
3. Maintainability
4. Simplicity
5. Readability
6. Performance
7. Scalability

Never sacrifice correctness for shorter code.