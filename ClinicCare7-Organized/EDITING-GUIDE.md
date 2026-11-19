# Editing guide

Start by opening the extracted **ClinicCare7-Organized** folder in VS Code.

## Where to edit each page

The PHP files below contain the actual page content, queries, and markup. Their matching CSS files contain the page styles. Shared controls inherit the common styles described below.

| Page | Editable PHP | Editable CSS |
| --- | --- | --- |
| Overview | `pages/overview.php` | `public/assets/css/overview.css` |
| Student records | `pages/students.php` | `public/assets/css/students.css` |
| Student profile, growth, documents, immunizations | `pages/student-profile.php` | `public/assets/css/student-profile.css` |
| Clinic Visit Directory | `pages/visits.php` | `public/assets/css/visits.css` |
| Clinic visit details | `pages/visit-details.php` | `public/assets/css/visit-details.css` |
| Bed Monitoring | `pages/beds.php` | `public/assets/css/beds.css` |
| Medicine Inventory | `pages/inventory.php` | `public/assets/css/inventory.css` |
| Stock Ledger | `pages/stock-ledger.php` | `public/assets/css/stock-ledger.css` |
| Medicine batches | `pages/medicine-batches.php` | `public/assets/css/medicine-batches.css` |
| Notification Center | `pages/notifications.php` | `public/assets/css/notifications.css` |
| Calendar | `pages/calendar.php` | `public/assets/css/calendar.css` |
| Reports & Analytics | `pages/reports.php` | `public/assets/css/reports.css` |
| Account Management | `pages/management.php` | `public/assets/css/management.css` |
| Emergency incidents and incident details | `pages/incidents.php` | `public/assets/css/incidents.css` |
| Medical certificate options | `pages/document.php` | `public/assets/css/document.css` |
| Modal form content | `pages/form.php` | `public/assets/css/form.css` |
| Archive / void / deactivate confirmation | `pages/remove.php` | `public/assets/css/remove.css` |
| Login | `pages/login.php` | `public/assets/css/login.css` |
| Missing page | `pages/not-found.php` | `public/assets/css/not-found.css` |

## Shared files

| File | Purpose |
| --- | --- |
| `public/assets/css/common.css` | Colors, typography, panels, buttons, fields, tables, navigation dock, and shared responsive rules |
| `public/assets/css/modals.css` | Dialog container, backdrop, title/close alignment, and modal spacing |
| `public/assets/css/form.css` | Shared form grid used by modal forms and standalone form pages |
| `includes/navigation.php` | Bottom navigation dock markup |
| `public/assets/app.js` | Modal loading/saving, search helpers, calendar interaction, and other browser behavior |
| `modules.php` | Form field definitions, validation, and saving data |
| `workflows.php` | Shared notification and clinic workflow logic |
| `workflow_views.php` | Reusable rendering helpers, including alert actions and calendar cards |
| `version7.php` | Action icons, incident links, dispensing history, removal helpers |
| `public/index.php` | Shared session/authentication checks, POST processing, layout, and stylesheet selection |
| `views.php` | Small whitelist router that includes the correct page file |
| `bootstrap.php` | Database connection and query helpers |
| `config.local.php` | Your local database settings, when you create it from `config.example.php` |

## Public page URLs

You can open the main pages directly, for example:

- `http://localhost/ClinicCare7-Organized/public/overview.php`
- `http://localhost/ClinicCare7-Organized/public/students.php`
- `http://localhost/ClinicCare7-Organized/public/students.php?id=1`
- `http://localhost/ClinicCare7-Organized/public/visits.php?id=1`
- `http://localhost/ClinicCare7-Organized/public/inventory.php?ledger=1`
- `http://localhost/ClinicCare7-Organized/public/inventory.php?medicine=1`

Replace example IDs with IDs from your own database. The existing `?p=students`, `?p=visits`, and other query URLs also continue to work.

Files under `public/`, such as `public/students.php`, are entry points that run the shared authentication and controller. Edit **`pages/students.php`** for the actual page content. Files under `pages/` are protected templates and cannot be opened directly in the browser.

## Example: edit Bed Monitoring

1. Change bed card markup/text in `pages/beds.php`.
2. Change card spacing, layout, and colors in `public/assets/css/beds.css`.
3. Refresh the browser. Use Ctrl+F5 if it still displays cached styles.

Each page CSS file also includes a `--page-title-color` variable you can change. Pages that use shared UI inherit it from `common.css`; their own file is the place for page-only overrides. Detail stylesheets import the related directory stylesheet, so student profiles retain student table styling, for example.

The application loads common styles, modal styles, form styles, and the current page's stylesheet. When a modal opens a different page, its stylesheet is loaded too. `public/assets/style.css` is only an optional import manifest; it no longer holds the page rules.

## Database and installation

The database stays **cliniccarefive**. This organization changes no schema or stored records. If you already imported Version 7's `upgrade-v7.sql`, do not repeat setup or import demo data. If upgrading from Version 6, follow README.md and import `upgrade-v7.sql` first.
