# WPU Maintenance Request System

A complete implementation of the Western Pacific University Maintenance
Request System, built to the accompanying SRS: HTML/CSS/JavaScript on the
front end, PHP on the back end, and MySQL for the database — the stack
specified in Section 8 for local (XAMPP) hosting.

## What was changed in the SRS

The source document used two different lists of roles in different
sections (five roles under FR-03 vs. six roles — including "Academic" —
everywhere else: Section 3.1 scope, the Section 6 dashboards, and the
7.3.1 data dictionary). That's the kind of inconsistency a system can't be
built against, so it's been resolved by standardizing on the six roles
actually used throughout the dashboards and data dictionary — **Student,
Staff, Academic, Technician, Supervisor, Administrator** — and updating
FR-03 and the `roles` table description to match. ("Technician" is the
same role 7.3.1 called "Maintenance Officer"; the FR-07–FR-11 wording and
the Section 6.5 dashboard name both already say "technician", so that's
the name used consistently throughout.) No other requirements were added,
removed, or reinterpreted. The corrected document is
`WPU_Maintenance_Request_System_SRS_updated.docx`.

## Current implementation

- **Functional requirements:** registration, login/password reset, six
  roles, maintenance requests with photos, status history, assignment and
  reassignment, technician work queues, before/after evidence, comments,
  dashboard notifications, and reporting by month/building/category/
  technician/status.
- **Security and quality:** role-based access control, bcrypt passwords,
  CSRF protection on state-changing forms, session regeneration and active
  account checks, upload validation, audit logging, server-side workflow
  validation, and responsive phone/tablet/desktop layouts.
- **Dashboards:** all Student/Staff/Academic, Technician, Supervisor, and
  Administrator pages use role-specific orange sidebar navigation with a
  wide main content panel and active navigation states.
- **Reports:** visual status, month, and category graphs are shown on the
  Supervisor Reports page. Supervisors and administrators can download
  report data as PDF, Word-compatible `.doc`, or Excel-compatible `.xls`.
- **User experience:** phone and tablet layouts use hamburger menus for
  the global header and dashboard sidebars. The Home page includes the
  supplied footer sections and links.
- **Section 7 (Database):** the schema in `database/schema.sql` matches
  the data dictionary table-for-table (SERIAL → `INT AUTO_INCREMENT`,
  CHECK-lists → `ENUM`, for MySQL/XAMPP per Section 3.5), plus the two
  reporting views from Section 7.6.
- **Out of scope:** email notifications or email delivery, native mobile
  applications, and third-party/GIS/QR/AI integrations. Notifications are
  dashboard-only. Email addresses are retained for account identity,
  contact links, and password-reset verification.

## Setup (Docker Compose)

Docker Compose is the recommended local setup. It provides PHP with the
`pdo_mysql` extension, starts MySQL, imports the schema and seed data on the
first run, and keeps database data in a named Docker volume.

Requirements: Docker Desktop, Docker Engine, or Docker Compose.

From the project root:

```bash
docker compose up --build
```

Open `http://localhost:8000/` in a browser. The default administrator account
is `admin@wpu.ac.pg` with password `Admin@12345`; change this password after
signing in.

Stop the services with `docker compose down`. To remove the database and
re-import the starter data on the next run, use `docker compose down -v`.
See [`docs/DOCKER_SETUP.md`](docs/DOCKER_SETUP.md) for troubleshooting and
port-change instructions.

## Setup (XAMPP)

1. Copy this whole `wpu_maintenance_system` folder into your XAMPP
   `htdocs` directory (e.g. `htdocs/wpu_maintenance_system`).
2. Start Apache and MySQL from the XAMPP control panel.
3. Open phpMyAdmin (or the `mysql` CLI) and run, in order:
   - `database/schema.sql`
   - `database/seed.sql`
4. Visit `http://localhost/wpu_maintenance_system/` in your browser.
5. Sign in as the seeded administrator to start creating real accounts:
  - **Email:** `admin@wpu.ac.pg`
  - **Password:** `Admin@12345`
   - Change this password immediately (there's no Admin self-registration
     by design — Section 6.7 System Settings doesn't include one, and
     new staff/student/academic/technician/supervisor accounts are
     created via Create Account or by the administrator).

`config/config.php` assumes the default XAMPP MySQL credentials
(`root` / no password) against a database named `wpu_maintenance`. Update
the constants there if your setup differs.

## Notes on judgment calls

A few places in the SRS described *what* a screen should do without
specifying exact mechanics; these were filled in conservatively:

- **Password reset (FR-02):** email delivery is out of scope, so identity
  is verified with a WPU email and ID number and a one-time reset link is
  shown on screen. Reset attempts are throttled per session.
- **Staff location fields (Section 6.3):** "Area/Location" and "Building
  Number" reuse the same `buildings`/`rooms` tables as the other roles
  (with relabeled fields), rather than a separate schema, so all
  requests stay queryable together for reporting (FR-14).
- **System Settings (Section 6.7)** wasn't itemized in the source
  document, so it was built out with the settings a real deployment
  would need: site name/support email/upload limit/backup frequency,
  plus building & room management (since something has to populate the
  location dropdowns used in Sections 6.2–6.4).

## Folder structure

```
config/           Database connection & app constants
includes/         Shared header/footer/sidebar/helpers
requests/         Student/Staff/Academic: submit, view, track
technician/       Technician dashboard & job actions
supervisor/       All-requests view, assign/reassign, reports
admin/            Users, categories, settings, audit logs
notifications/    Dashboard alerts
assets/           CSS, JS, and the WPU logo
database/         schema.sql, seed.sql
uploads/          Photo/attachment storage (write-protected against script execution)
  footer.png        Supplied footer reference image
  Dashboard mockup.docx
                    Supplied dashboard reference
  Submit Request UI.docx
                    Supplied submit-request reference
docs/              Current-state use-case diagram and system flowchart
```

## Operational notes

- Change the seeded administrator password immediately after first login.
- Keep the `uploads/` directory write-enabled for PHP but non-executable;
  production deployments should preferably store uploads outside the public
  web root.
- PDF, Word, and Excel report downloads are protected by Supervisor/Admin
  role checks. Word and Excel exports use compatible document formats and do
  not require Composer packages.
- The local database schema restricts notification records to the
  `Dashboard` type; no email notification transport is configured.
