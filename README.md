# Sindh Seenghar Portal

The Sindh Seenghar Portal is a PHP/MySQL web platform that helps Sindh-based schools manage enrolment, attendance, exams, and teacher reports in one place. The project provides dedicated dashboards for administrators and teachers, along with utilities to export results, generate attendance sheets, and track school performance.

## Key Features

- **Administration**
  - Manage schools, classes, teachers, and students from the `admin/` panel.
  - Create exams, record attendance, review results, and export reports to Excel.
  - View teacher-submitted class reports (`admin/reports.php`, `admin/view_report.php`).
  - Granular access control so only authenticated admins can reach protected routes.

- **Teacher Tools**
  - Dashboard for class summaries, exam results, and attendance statistics (`teacher/dashboard.php`).
  - Mark attendance and submit class reports (`teacher/attendance.php`, `teacher/reports.php`).
  - Enter and review exam results for assigned classes.

- **Shared Utilities**
  - Session-based authentication with role checks (`includes/functions.php`).
  - Schema self-healing for critical tables (e.g., `admin/create_exam.php`, `admin/enter_results.php`).
  - Common layout components under `includes/` and reusable helpers inside `includes/functions.php`.

## Technology Stack

- **Language:** PHP 8+ (tested with PHP bundled in XAMPP)
- **Database:** MySQL (MariaDB via XAMPP)
- **Frontend:** Bootstrap 4, Font Awesome
- **Composer Packages:**
  - `tecnickcom/tcpdf` for PDF generation (future enhancement hooks already in place)
  - `phpoffice/phpspreadsheet` for Excel exports

## Repository Structure

```
├── admin/                # Admin-facing pages and forms
├── teacher/              # Teacher-facing dashboard and tools
├── includes/             # Shared header, footer, and helper functions
├── config/               # Database connection and table initialisation scripts
├── exports/              # Generated Excel exports
├── uploads/              # File uploads (e.g., report attachments)
├── vendor/               # Composer dependencies
├── index.php             # Landing page / login redirect
├── login.php             # Authentication entry point
├── README.md             # Project documentation (this file)
└── composer.json         # PHP dependencies
```

## Database Schema Highlights

`config/init_tables.php` bootstraps the schema when the project first runs. Tables include:

- **users** – Admins, teachers, and optionally parents; stores role, email, hashed password.
- **schools** – School registry with address, contact info.
- **classes** – Class records linked to schools and teachers.
- **students** – Student profiles assigned to classes.
- **attendance / attendance_details** – Daily attendance metadata plus per-student details.
- **exams** – Exam definitions with total marks and linked classes.
- **results** – Scores per student (`obtained_marks`, `remarks`), auto-migrated from legacy schema if needed.
- **reports** – Teacher-submitted narrative reports (type, content, optional attachment path).

> **Note:** The admin pages include runtime guards to backfill missing columns (e.g., `admin/create_exam.php`, `admin/enter_results.php`). For a fresh installation, running `config/init_tables.php` once is sufficient.

## Prerequisites

- PHP 8.0 or newer
- MySQL 5.7+ (MariaDB 10.x works via XAMPP)
- Composer (for dependency installation)
- Node tooling is **not** required

## Local Development Setup

1. **Clone or copy the project into your web root**
   ```bash
   git clone https://github.com/your-org/Sindh-Singhar-Portal.git
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Configure database access**
   - The default configuration in `config/database.php` expects `root` with no password and creates the database `sindh_seenghar_db` automatically.
   - Update `DB_USERNAME`, `DB_PASSWORD`, and `DB_NAME` as needed for your environment.

4. **Start Apache & MySQL** (e.g., via XAMPP Control Panel or your preferred stack).

5. **Initialize tables**
   - Visiting any page will trigger the table creation logic in `config/init_tables.php` if the schema does not exist.
   - On success, a default admin user is created: `admin@seenghar.org / admin123`.

6. **Log in**
   - Navigate to `http://localhost/Sindh-Singhar-Portal/login.php`.
   - Use the default admin credentials or add additional users via the admin panel.

## Deployment Notes

- Ensure `uploads/` and `exports/` are writable by the web server for file exports and attachments.
- For production, create a dedicated MySQL user with limited privileges and update `config/database.php` accordingly.
- Enforce HTTPS and secure session cookies before hosting publicly.
- Consider moving runtime schema guards into formal migrations once the schema stabilises.

## Available Scripts & Utilities

- **Attendance Export:** `admin/attendance.php` and `teacher/attendance.php` integrate with `PhpSpreadsheet` for Excel downloads.
- **Report Viewing:** `admin/view_report.php` displays detailed teacher submissions with optional attachments.
- **Self-Healing Columns:** Exam and results pages will attempt to add missing columns automatically, reducing downtime after deployments.

## Troubleshooting

- **“Unknown column …” errors:** Visit the relevant admin page once (exam creation or results entry) to trigger column backfill, or run the SQL statements documented in those files.
- **Composer autoload issues:** Make sure to run `composer install` and include `vendor/autoload.php` where necessary (already handled in the project).
- **Permissions:** If PDF or Excel exports fail, verify write permissions on `exports/` and `uploads/`.

## Contributing

1. Fork the repository and create a feature branch.
2. Follow existing coding style (PHP with procedural structure and shared helpers).
3. Submit a pull request outlining the problem solved and testing performed.

## License

Specify your project license here (e.g., MIT). If none is chosen, consider adding one before opening the repository to the public.

---

For questions or suggestions, open an issue or reach out to the maintainers listed in your GitHub organization.
