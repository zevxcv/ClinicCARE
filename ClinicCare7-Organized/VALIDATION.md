# Organized Version 7 validation

Passed for this edition:

- PHP 8.2 syntax/parser checks on all 46 PHP files; JavaScript syntax check.
- Existing save, validation, stock-deduction, linked-emergency, and duplicate-submission checks rerun through the shared POST handler.
- Rendering checks for 19 routes, including list/detail pages, stock ledger, batches, growth, notifications, calendar, reports, accounts, certificates, forms, and removal confirmation.
- Main content compared with original Version 7 for all 19 routes: matched after normalizing generated submission tokens and timestamps.
- Representative computed styles compared with original Version 7 for the same 19 routes using a DOM environment: matched for layout, spacing, typography, and colors.
- Direct public PHP entry points tested for all ten main clinic routes. Direct access to an internal page template returns HTTP 403.
- Modal DOM checks for loading, JSON submission destination, inline errors, retained input, unsaved-change confirmation, and close controls.
- Four-alert pagination and retry checks continue to pass.

Limitations: checks used PHP 8.2 WebAssembly, SQLite fixtures with adapted row-lock syntax, and a DOM test environment. MariaDB-specific alert generation was isolated in save-handler tests. Full XAMPP/MariaDB integration and a live-browser visual pass were not performed here. No live user database was accessed.

## Save URL regression correction

Reproduced the earlier failure by modeling browser named-control precedence: an `action` input shadows the form URL property. The old JavaScript sent an input object as its POST target and received an HTML 404. The corrected code reads the HTML attribute, submits to the real PHP handler, inserts the student once, and receives JSON success. A notification action with the same named input also sends to the correct URL. JavaScript syntax and existing modal DOM checks passed.
