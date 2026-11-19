# Modal save URL fix

## Apply this correction

From this ZIP, replace these files inside your existing ClinicCare7-Organized folder:

1. `public/assets/app.js`
2. `public/index.php`

Keep your existing `config.local.php` and database. No SQL import is required for this correction. Reload the website with Ctrl+F5 after replacing the files.

You can also extract the full package over the same project folder and replace its application files. Do not import schema or demo SQL for this correction.

## Cause

Every save form contains a hidden input named `action`. In browsers, that named input takes precedence over the `HTMLFormElement.action` property. The JavaScript read that property as the fetch target and sent the request to an address derived from the input object, instead of to the PHP handler. That returned HTML rather than a JSON save confirmation.

The corrected code reads the form's actual `action` attribute, resolves it to an absolute URL, and sets attributes explicitly. Both modal POSTs and inline notification actions use this method. The existing hidden field is retained for PHP action routing. The PHP entry point also uses a new JavaScript version suffix to avoid loading the previous cached script.

Source reference for browser behavior: https://developer.mozilla.org/en-US/docs/Web/API/HTMLFormElement

## Regression check

The test recreates browser named-property precedence, which the earlier DOM tests did not model. The old code reproduced the HTML 404 and confirmation error without inserting a student. The patched code sent the correct URL through the actual PHP handler, inserted one student, and returned a JSON success confirmation. The notification action destination also passed the named-field check.

The PHP check used an isolated PHP 8.2 runtime and SQLite fixtures. It did not connect to your local XAMPP database.
