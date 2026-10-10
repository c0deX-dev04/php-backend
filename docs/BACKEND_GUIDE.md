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
  php artisan db:seed
   php artisan serve --host=127.0.0.1 --port=8000
   ```

   The API is available at `http://127.0.0.1:8000/api`; `GET /api/health` does not require MySQL credentials or authentication.

## Database Structure

The migrations create the ClassDesk tables: `users`, `subjects`, `attendance`, `attendance_thresholds`, `notes`, `labs`, `lab_assignments`, `exams`, and `notices`. User profiles include a unique optional `roll_number` and roster-controlled `is_active` status; subjects can be assigned a teacher; notices persist `category` and `pinned`; exams can reference a private seating-plan PDF; attendance thresholds are shared per subject. Laravel also creates support tables for sessions, cache, jobs, password resets, and Sanctum tokens. `php artisan db:seed` creates PHP with MySQL, Object Oriented Programming with Java, Financial Management, Entrepreneurship, PHP with MySQL Lab, and Object Oriented Programming with Java Lab, plus PHP and Java lab records.

Foreign keys connect attendance to a student and subject; notes to a subject and uploader; assignments to a lab and subject; exams to a subject and creator; and notices to their creator. A unique key prevents more than one attendance record for the same student, subject, and date. Uploaded files are stored privately under Laravel's local storage disk; their paths are not returned in API JSON.

The roster sync can create accounts from the roster sheet. It assigns random unusable password hashes to new accounts and preserves existing password hashes. An administrator must set passwords for new accounts through trusted tooling. For a single account, use Tinker; student and CR accounts need their unique roll number:

```powershell
php artisan migrate:status
```

Provision an account with Tinker. Replace the sample values and use a strong password:

```powershell
php artisan tinker
```

```php
App\Models\User::create([
  'name' => 'Student Name',
  'email' => 'student@example.edu',
    'password' => 'Replace-With-A-Strong-Unique-Password',
  'role' => 'student',
  'roll_number' => '22601222274',
]);
```

Allowed roles are `student`, `cr`, and `teacher`. Laravel hashes passwords automatically. There is intentionally no public registration endpoint; provision and disable accounts through trusted administrative tooling such as Tinker or a future admin-only workflow.

## API Overview

All module endpoints require a Sanctum bearer token. Login validates email and password, then returns the role assigned to the account; an optional role can be sent for compatibility and must match. Send `Accept: application/json`; pass the token as `Authorization: Bearer <token>`.

| Method and path | Access | Purpose |
| --- | --- | --- |
| `GET /api/health` | Public | Health check |
| `POST /api/login` | Public, rate-limited | Login with email and password; role is returned from the account |
| `GET /api/me` | Authenticated | Current account |
| `POST /api/logout` | Authenticated | Revoke the current token |
| `GET /api/subjects` | Authenticated | List subjects |
| `POST /api/subjects` | CR | Create a subject |
| `GET /api/students` | Authenticated | List students; student accounts receive only their own profile |
| `POST /api/students/sync` | CR | Sync the student/CR/teacher roster and teacher-subject mappings |
| `GET /api/attendance-thresholds` | Authenticated | List subject attendance thresholds |
| `PUT /api/attendance-thresholds/{subject}` | CR | Save a subject's minimum attendance percentage |
| `GET, POST /api/attendance` | Read: all roles; write: CR | View or manually mark attendance |
| `POST /api/attendance/sync` | CR | Sync attendance from the configured Google Sheet |
| `GET, POST /api/notes` | Read: all roles; write: CR | List or upload notes |
| `GET /api/notes/{id}/download` | Authenticated | Download a note |
| `GET, POST /api/labs` | Read: all roles; write: CR | View or manage labs |
| `GET, POST /api/lab-assignments` | Read: all roles; write: CR | View or manage assignments |
| `GET, POST /api/exams` | Read: all roles; write: CR | View or manage exams |
| `POST /api/exams/{id}/seating-plan` | CR | Upload or replace an exam seating PDF |
| `GET /api/exams/{id}/seating-plan` | Authenticated | Download the exam seating PDF |
| `GET, POST /api/notices` | Read: all roles; write: CR | View or manage notices |
| `GET /api/notices/{id}/download` | Authenticated | Download an optional notice PDF |

The module resources also support `GET /{id}`, `PUT/PATCH /{id}`, and `DELETE /{id}` as appropriate. For multipart file updates, submit `POST` with Laravel's `_method=PUT` field. Student attendance responses are restricted to the signed-in student. Teachers can view academic data but every write route is rejected unless the authenticated role is `cr`.

The frontend at `frontend/PHP-project--Frontend` defaults to `/api`. Its Vite development server proxies that path to `http://127.0.0.1:8000`, so run the API and frontend together. To call the API directly from a different origin, set `FRONTEND_URL` in the backend `.env` to the frontend origin and set `VITE_API_URL` for the frontend build.

Example PowerShell login and subject request:

```powershell
$login = Invoke-RestMethod -Method Post `
  -Uri http://127.0.0.1:8000/api/login `
  -ContentType 'application/json' `
  -Body (@{ email = 'cr@example.test'; password = 'your-password' } | ConvertTo-Json)

Invoke-RestMethod -Uri http://127.0.0.1:8000/api/subjects `
  -Headers @{ Authorization = "Bearer $($login.token)"; Accept = 'application/json' }
```

Attendance payloads use `user_id`, `subject_id`, `date` (`YYYY-MM-DD`), and `status` (`present` or `absent`). Exam types are `CA1`, `CA2`, `CA3`, and `semester`. Note uploads use multipart fields `subject_id`, `title`, and `file`; allowed files are PDF, PPT/PPTX, and DOC/DOCX up to 10 MB. Notice attachments are optional PDFs up to 10 MB.

## Optional Google Sheets Sync

Both sync operations use the official Google API PHP client with a service account. Enable the Google Sheets API, create a service account, and save its JSON key outside the repository and web root. Share each private spreadsheet with the service account email as a Viewer. Never commit the JSON key, expose it in frontend code, or place passwords in a sheet.

Set the secure key path and PHP attendance sheet settings in the backend `.env`, then run `php artisan config:clear`:

```dotenv
GOOGLE_APPLICATION_CREDENTIALS=C:\secure\classdesk-sheets-service-account.json
GOOGLE_SHEETS_ATTENDANCE_SPREADSHEET_ID=16pzODiFjSYfMJOJWRug6Ev_QAEd9j9nWbNVlp0yorZM
GOOGLE_SHEETS_ATTENDANCE_RANGE=CLASS Attendance!B6:ZZ
GOOGLE_SHEETS_ATTENDANCE_SUBJECT_CODE=PHP
GOOGLE_SHEETS_ATTENDANCE_DATE_FORMAT=d/m
GOOGLE_SHEETS_ATTENDANCE_YEAR=2026
GOOGLE_SHEETS_ROSTER_SPREADSHEET_ID=your-roster-spreadsheet-id
GOOGLE_SHEETS_ROSTER_RANGE=Roster!A1:E
```

The in-app roster sync requires a CR token, so perform the first import from Tinker after configuring the service-account key and roster sheet:

```php
app(App\Services\RosterSheetSyncService::class)->sync();
$cr = App\Models\User::where('email', 'cr@example.edu')->firstOrFail();
$cr->password = 'Replace-With-A-Strong-Unique-Password';
$cr->save();
```

Replace the CR email with one from the roster. New accounts receive random unusable password hashes; passwords are set through trusted administration and never stored in Sheets. After the CR can sign in, use the Attendance page's **Sync roster** action for later updates.

The provided attendance sheet is the PHP demo; no other subject sheets are configured yet. Its row 6 has `Roll Number` in column B and `Student` in column C; checkbox dates begin in column H. Data starts on row 7. The sync uses the configured subject code (`PHP` initially), resolves each roll number against the roster, maps checked boxes to `present`, unchecked boxes to `absent`, and ignores blank cells. Date headings such as `11/08` are parsed as day/month using the configured year. Change `GOOGLE_SHEETS_ATTENDANCE_DATE_FORMAT` and year if the sheet uses another convention. Authenticate as a CR and call `POST /api/attendance/sync`. Repeated student/subject/date records are updated; records not represented in the sheet are not deleted. The other five subjects appear in the database and frontend but have no Google attendance source until their sheets are configured.

The roster range must start with a header row containing `email`, `name`, `role`, `roll_number`, and `subject_codes`. Use one row per account; roles are `student`, `cr`, or `teacher`. Student and CR rows require roll numbers. Teacher `subject_codes` are comma- or semicolon-separated and must reference existing subject codes. For example, assign the same PHP teacher to `PHP,PHP_LAB` and the Java teacher to `JAVA,JAVA_LAB`. The other teachers map to `FIN_MGMT` and `ENTREPRENEURSHIP`. Run `POST /api/students/sync` as a CR after configuring the roster ID. Accounts missing from the next successful roster sync are deactivated, not deleted. Set passwords for newly imported accounts through trusted administration; passwords and password hashes do not belong in the sheet.

## Tests and Daily Development

Run the feature suite:

```powershell
php artisan test
```

Tests use an in-memory SQLite database configured in `phpunit.xml`; they do not touch the XAMPP `classdesk` database or make real Google API requests. Google Sheets reads are mocked in the feature tests.

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
- Keep `.env`, the service-account JSON key, and uploaded files out of Git. Rotate any exposed credentials and limit service-account access to the required spreadsheets.
- MySQL connection errors: confirm XAMPP MySQL is running, the `classdesk` database exists, and `DB_HOST`, `DB_PORT`, `DB_USERNAME`, and `DB_PASSWORD` in `.env` are correct. Then run `php artisan config:clear`.
- `could not find driver`: enable `extension=pdo_mysql` in the PHP configuration used by the CLI, reopen the terminal, and confirm `php -m` lists `pdo_mysql`.
- Composer archive/ZIP errors: enable `extension=zip` in `C:\xampp\php\php.ini`, restart the terminal, and retry `composer install`.
- A 401 means the token is missing/invalid; a 403 means the signed-in role cannot perform that operation; a 422 includes validation errors. Log in again after logout because the old token is revoked.
- Note and notice files live on Laravel's private local disk and must be downloaded through their authenticated API routes. Back up `storage/app/private` along with MySQL if those files need to be retained.

For production, do not use the XAMPP root account, an empty database password, debug mode, or a publicly readable attendance sheet. This setup is a local college-project foundation, not a production deployment recipe.