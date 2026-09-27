# ClassDesk Backend, Database, and Testing Guide

This backend follows the supplied ClassDesk design: one class/semester, pre-created Student/CR/Teacher accounts, CR-managed data, view-only teachers, private study files, and optional Google Sheets attendance sync.

## Requirements

- XAMPP with MySQL and PHP 8.2 or newer
- Composer 2
- PHP extensions `openssl`, `mbstring`, `pdo_mysql`, and `zip`
- Git (optional, useful for version control)

In XAMPP, `php.exe` is normally `C:\xampp\php\php.exe`. If `php` is not recognized, add `C:\xampp\php` to your Windows `PATH`, reopen VS Code, and check `php --version`. Install Composer for Windows and select the XAMPP `php.exe` when prompted. If Composer reports that ZIP is unavailable, enable `extension=zip` in `C:\xampp\php\php.ini`, restart the terminal, and confirm `php -m` lists `zip`.

## First-Time Setup

1. Open the XAMPP Control Panel and start **MySQL**. Apache is only needed if you want to use phpMyAdmin through XAMPP.
2. Open phpMyAdmin at `http://localhost/phpmyadmin`, create a database named `classdesk`, and choose the `utf8mb4_unicode_ci` collation. Do not create the tables manually; migrations own their structure.
3. In the repository root, install dependencies and create the application key if this is a fresh checkout:

   ```powershell
   composer install
   php artisan key:generate
   ```

4. Check the local `.env` file. The defaults assume XAMPP MySQL at `127.0.0.1:3306`, database `classdesk`, user `root`, and an empty password. Change `DB_USERNAME` and `DB_PASSWORD` if your MySQL account differs. Never commit `.env` or put production credentials in source control.
5. Create the tables and start the API:

   ```powershell
   php artisan config:clear
   php artisan migrate
   php artisan serve --host=127.0.0.1 --port=8000
   ```

   The API is available at `http://127.0.0.1:8000/api`; `GET /api/health` does not require MySQL credentials or authentication.

## Database Structure

The migrations create the eight ClassDesk tables: `users`, `subjects`, `attendance`, `notes`, `labs`, `lab_assignments`, `exams`, and `notices`. Laravel also creates support tables for sessions, cache, jobs, password resets, and Sanctum tokens.

Foreign keys connect attendance to a student and subject; notes to a subject and uploader; assignments to a lab and subject; exams to a subject and creator; and notices to their creator. A unique key prevents more than one attendance record for the same student, subject, and date. Uploaded files are stored privately under Laravel's local storage disk; their paths are not returned in API JSON.

To check migration status:

```powershell
php artisan migrate:status
```

Create a subject and a pre-approved account with Tinker. Replace the sample values and use a strong password:

```powershell
php artisan tinker
```

```php
App\Models\Subject::create(['name' => 'PHP Programming', 'code' => 'PHP']);
App\Models\User::create([
    'name' => 'Class Representative',
    'email' => 'cr@example.test',
    'password' => 'Replace-With-A-Strong-Unique-Password',
    'role' => 'cr',
]);
```

Allowed roles are `student`, `cr`, and `teacher`. Laravel hashes passwords automatically. There is intentionally no public registration endpoint; provision and disable accounts through trusted administrative tooling such as Tinker or a future admin-only workflow.

## API Overview

All module endpoints require a Sanctum bearer token. Login requires the selected role to match the stored role. Send `Accept: application/json`; pass the token as `Authorization: Bearer <token>`.

| Method and path | Access | Purpose |
| --- | --- | --- |
| `GET /api/health` | Public | Health check |
| `POST /api/login` | Public, rate-limited | Login with email, password, and role |
| `GET /api/me` | Authenticated | Current account |
| `POST /api/logout` | Authenticated | Revoke the current token |
| `GET /api/subjects` | Authenticated | List subjects |
| `GET, POST /api/attendance` | Read: all roles; write: CR | View or manually mark attendance |
| `POST /api/attendance/sync` | CR | Sync attendance from the configured Google Sheet |
| `GET, POST /api/notes` | Read: all roles; write: CR | List or upload notes |
| `GET /api/notes/{id}/download` | Authenticated | Download a note |
| `GET, POST /api/labs` | Read: all roles; write: CR | View or manage labs |
| `GET, POST /api/lab-assignments` | Read: all roles; write: CR | View or manage assignments |
| `GET, POST /api/exams` | Read: all roles; write: CR | View or manage exams |
| `GET, POST /api/notices` | Read: all roles; write: CR | View or manage notices |
| `GET /api/notices/{id}/download` | Authenticated | Download an optional notice PDF |

The module resources also support `GET /{id}`, `PUT/PATCH /{id}`, and `DELETE /{id}` as appropriate. For multipart file updates, submit `POST` with Laravel's `_method=PUT` field. Student attendance responses are restricted to the signed-in student. Teachers can view academic data but every write route is rejected unless the authenticated role is `cr`.

Example PowerShell login and subject request:

```powershell
$login = Invoke-RestMethod -Method Post `
  -Uri http://127.0.0.1:8000/api/login `
  -ContentType 'application/json' `
  -Body (@{ email = 'cr@example.test'; password = 'your-password'; role = 'cr' } | ConvertTo-Json)

Invoke-RestMethod -Uri http://127.0.0.1:8000/api/subjects `
  -Headers @{ Authorization = "Bearer $($login.token)"; Accept = 'application/json' }
```

Attendance payloads use `user_id`, `subject_id`, `date` (`YYYY-MM-DD`), and `status` (`present` or `absent`). Exam types are `CA1`, `CA2`, `CA3`, and `semester`. Note uploads use multipart fields `subject_id`, `title`, and `file`; allowed files are PDF, PPT/PPTX, and DOC/DOCX up to 10 MB. Notice attachments are optional PDFs up to 10 MB.

## Optional Google Sheets Sync

The sync endpoint is ready, but Google credentials and spreadsheet details must be configured by the project team. Enable the Google Sheets API in Google Cloud, create an API key restricted to that API, and use a sheet that the key can read. This initial design intentionally does not use OAuth; do not publish sensitive student information in a publicly readable spreadsheet.

Set these values in `.env`, then run `php artisan config:clear`:

```dotenv
GOOGLE_SHEETS_API_KEY=your-restricted-api-key
GOOGLE_SHEETS_SPREADSHEET_ID=your-spreadsheet-id
GOOGLE_SHEETS_RANGE=Attendance!A:D
```

The range's first row must contain `email`, `subject_code`, `date`, and `status`. Each subsequent row must use an existing student email, an existing subject code, an ISO date such as `2026-09-27`, and `present` or `absent`. Authenticate as a CR and call `POST /api/attendance/sync`. Rows are validated before a transaction upserts them; a repeated student/subject/date updates the status, while attendance not present in the sheet is not deleted. CRs can always enter or correct attendance with the manual API.

## Tests and Daily Development

Run the feature suite:

```powershell
php artisan test
```

Tests use an in-memory SQLite database configured in `phpunit.xml`; they do not touch the XAMPP `classdesk` database and do not make real Google API requests. The Sheets test uses Laravel's HTTP fake.

Useful checks while working:

```powershell
php artisan route:list --path=api
php artisan migrate:status
php artisan test --filter=ClassDeskApiTest
vendor\bin\pint --test
```

When adding a database change, create a new migration rather than editing a migration that has already run on another teammate's database:

```powershell
php artisan make:migration add_example_column_to_subjects_table
```

Update the matching model/controller validation and add a feature test. Review `php artisan migrate:status`, apply with `php artisan migrate`, then run `php artisan test` before sharing the change. Avoid `migrate:fresh` on any database with data: it drops every table. For a disposable local database only, it can rebuild everything with `php artisan migrate:fresh`.

## Maintenance and Troubleshooting

- Back up `classdesk` regularly using phpMyAdmin's **Export** tab before schema changes or demos. Keep backups outside the public web root and verify that a backup can be imported.
- Keep `.env` and uploaded files out of Git. Rotate any exposed API key/password, and restrict Google API keys to the Sheets API and the intended environment.
- MySQL connection errors: confirm XAMPP MySQL is running, the `classdesk` database exists, and `DB_HOST`, `DB_PORT`, `DB_USERNAME`, and `DB_PASSWORD` in `.env` are correct. Then run `php artisan config:clear`.
- `could not find driver`: enable `extension=pdo_mysql` in the PHP configuration used by the CLI, reopen the terminal, and confirm `php -m` lists `pdo_mysql`.
- Composer archive/ZIP errors: enable `extension=zip` in `C:\xampp\php\php.ini`, restart the terminal, and retry `composer install`.
- A 401 means the token is missing/invalid; a 403 means the signed-in role cannot perform that operation; a 422 includes validation errors. Log in again after logout because the old token is revoked.
- Note and notice files live on Laravel's private local disk and must be downloaded through their authenticated API routes. Back up `storage/app/private` along with MySQL if those files need to be retained.

For production, do not use the XAMPP root account, an empty database password, debug mode, or a publicly readable attendance sheet. This setup is a local college-project foundation, not a production deployment recipe.