# ClinicCare Version 7 — Organized PHP and CSS

Requires PHP 8.2+ and MariaDB supplied with XAMPP. The database remains **cliniccarefive**, so existing accounts and records are reused.

## Save correction

A browser form-property collision in modal/notification submissions is corrected. See **SAVE-FIX.md** for the two replacement files and cache-refresh instructions. No additional SQL import is needed for this correction.

## This organized edition

This edition uses the latest Version 7 revisions. Each page has its own editable PHP file under `pages/` and matching CSS under `public/assets/css/`. Public entry files are under `public/`. See **EDITING-GUIDE.md** for the complete mapping.

If Version 7 is already installed and `upgrade-v7.sql` has already been imported, this file organization requires **no additional database changes**. Extract this edition into `C:\xampp\htdocs`, copy your existing `config.local.php` if used, and open `http://localhost/ClinicCare7-Organized/`.

## Upgrade from Version 6

1. Export a backup of `cliniccarefive` in phpMyAdmin.
2. Extract this ZIP into `C:\xampp\htdocs`. It creates `ClinicCare7-Organized`.
3. Copy your `config.local.php` from the old installation into `ClinicCare7-Organized`, if you have one.
4. Start Apache and MySQL. In phpMyAdmin, select `cliniccarefive`, then import **upgrade-v7.sql**. This is required for reliable saves. It adds a submission-receipt table and safely ensures the Version 6 columns exist. It does not replace student, visit, incident, medicine, or staff records.
5. Open **http://localhost/ClinicCare7-Organized/** and sign in with your existing account. Hard-refresh once (Ctrl+F5).

Do not import `schema.sql`, `demo-data.sql`, or `install-demo.sql` into your existing database. Use Version 7 after upgrading; avoid switching back to Version 6.

For a Version 5 database, import `upgrade.sql` first, then `upgrade-v7.sql`.

## Version 7 changes

- Notification Center shows four alerts per page with Previous/Next controls. The Overview notification card also shows at most four.
- Every modal uses an aligned title and × close button. Cancel buttons are removed. Escape/× retain unsaved-change confirmation.
- Saves return explicit JSON success or validation errors. Form inputs remain in place on failure. Each successful submission is recorded in the same database transaction as the change. Retrying the same token returns its original result, preventing duplicate records and repeated medicine deductions, even after a response is lost.
- Growth & BMI cards, clinic visit sections, and buttons have consistent gaps. Bottom padding keeps content clear of the navigation dock.
- Dispense Medicine is a text button. Add Medicine uses a + icon with a tooltip and accessible label. Stock Ledger stays a regular page and has Back to Inventory.
- Reorder Requests and Physical Stock Count are removed from active workflows, including notification reorder forms. Existing order and movement history is preserved.
- Receive Stock has no Unit Cost field. Existing lots keep their stored cost; new lots use zero internally. Existing-lot receipts must match the expiration date.
- Bed card footers share a consistent height; View Visit, discharge selection, and Release Bed align at the bottom and wrap on narrow screens.

## Emergency and visit workflow

- Overview provides New Visit and Emergency Incident side by side.
- New Visit records a routine consultation. Log Emergency Incident from the visit links the incident to that same visit and carries over student/treatment details.
- Emergency Incident from Overview creates the incident and a linked visit together, in one transaction. The new visit starts as Observation with clinical assessment pending; no bed is occupied automatically. Open Edit Visit to complete vital signs, assessment, treatment, and disposition.
- Each active visit has at most one active incident through this workflow. Logging again opens the existing record instead of duplicating it.
- Clinic Visit Directory and visit details show View Emergency Incident when linked. Incident details open in a modal with View Linked Visit and Edit Incident.
- Previously saved incidents and visits are preserved. Previously unlinked incidents are not guessed or automatically matched. Saving an edit to an unlinked incident creates its linked visit.

## Fresh installation only

Import `schema.sql` for an empty installation. Create your administrator using the included setup script:

```powershell
cd C:\xampp\htdocs\ClinicCare7-Organized
$env:CLINIC_ADMIN_PASSWORD = "your-own-long-password"
& C:\xampp\php\php.exe setup.php admin@example.test
Remove-Item Env:CLINIC_ADMIN_PASSWORD
```

For a disposable local demo only, import `install-demo.sql`. Demo accounts: `admin` / `AdminFive!2026`, `nurse` / `NurseFive!2026`. Do not use these credentials for real clinic records.

## Stock and notifications

Dispensing uses earliest-expiring, unexpired, non-quarantined batches first (FEFO). It requires an active visit and rejects insufficient stock. Receive Stock and disposal continue to record stock movements. Removal preserves linked history.

For periodic alerts while the app is idle, schedule `C:\xampp\php\php.exe C:\xampp\htdocs\ClinicCare7-Organized\alerts_tick.php`.

## Validation

See VALIDATION.md for the checks performed and their limitations.
