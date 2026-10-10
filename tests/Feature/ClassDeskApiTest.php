<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Exam;
use App\Models\Note;
use App\Models\Subject;
use App\Models\User;
use App\Services\GoogleSheetsReader;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClassDeskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_is_available(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    public function test_login_checks_password_and_selected_role_and_issues_a_token(): void
    {
        $user = User::factory()->create([
            'email' => 'cr@example.test',
            'password' => Hash::make('correct-password'),
            'role' => 'cr',
            'roll_number' => '23CS040',
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-password',
            'role' => 'teacher',
        ])->assertUnauthorized();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertOk()
            ->assertJsonPath('user.role', 'cr')
            ->assertJsonPath('user.roll_number', '23CS040');

        $this->withToken($response->json('token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_inactive_roster_accounts_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'former-student@example.test',
            'password' => Hash::make('correct-password'),
            'role' => 'student',
            'is_active' => false,
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertUnauthorized();
    }

    public function test_database_seeder_creates_the_six_requested_subjects(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('subjects', 6);
        $this->assertDatabaseHas('subjects', ['code' => 'PHP']);
        $this->assertDatabaseHas('subjects', ['code' => 'JAVA']);
        $this->assertDatabaseHas('subjects', ['code' => 'FIN_MGMT']);
        $this->assertDatabaseHas('subjects', ['code' => 'ENTREPRENEURSHIP']);
        $this->assertDatabaseHas('subjects', ['code' => 'PHP_LAB']);
        $this->assertDatabaseHas('subjects', ['code' => 'JAVA_LAB']);
    }

    public function test_students_cannot_manage_data_and_only_see_their_own_attendance(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $anotherStudent = User::factory()->create(['role' => 'student']);
        $subject = Subject::query()->create(['name' => 'PHP', 'code' => 'PHP']);

        Attendance::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'date' => '2026-09-01',
            'status' => 'present',
        ]);
        Attendance::query()->create([
            'user_id' => $anotherStudent->id,
            'subject_id' => $subject->id,
            'date' => '2026-09-01',
            'status' => 'absent',
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/attendance')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $student->id);

        $this->getJson('/api/students')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $student->id);

        $this->postJson('/api/notices', ['title' => 'Blocked', 'content' => 'Student write'])
            ->assertForbidden();
    }

    public function test_dashboard_returns_attendance_summary_for_the_signed_in_student(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $subject = Subject::query()->create(['name' => 'PHP', 'code' => 'PHP']);
        Attendance::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'date' => '2026-09-01',
            'status' => 'present',
        ]);
        Sanctum::actingAs($student);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.attendance.total_records', 1)
            ->assertJsonPath('data.attendance.percentage', 100);
    }

    public function test_only_crs_can_persist_subject_attendance_thresholds(): void
    {
        $subject = Subject::query()->create(['name' => 'PHP', 'code' => 'PHP']);
        $cr = User::factory()->create(['role' => 'cr']);
        Sanctum::actingAs($cr);

        $this->putJson('/api/attendance-thresholds/'.$subject->id, ['minimum_percentage' => 82])
            ->assertOk()
            ->assertJsonPath('data.minimum_percentage', 82);

        $this->assertDatabaseHas('attendance_thresholds', [
            'subject_id' => $subject->id,
            'minimum_percentage' => 82,
            'updated_by' => $cr->id,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));
        $this->putJson('/api/attendance-thresholds/'.$subject->id, ['minimum_percentage' => 60])
            ->assertForbidden();
    }

    public function test_cr_can_create_attendance_but_duplicate_daily_records_are_rejected(): void
    {
        $cr = User::factory()->create(['role' => 'cr']);
        $student = User::factory()->create(['role' => 'student']);
        $subject = Subject::query()->create(['name' => 'PHP', 'code' => 'PHP']);
        Sanctum::actingAs($cr);

        $attendance = [
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'date' => '2026-09-01',
            'status' => 'present',
        ];

        $this->postJson('/api/attendance', $attendance)->assertCreated();
        $this->postJson('/api/attendance', $attendance)->assertUnprocessable();
    }

    public function test_cr_can_sync_valid_attendance_rows_from_google_sheets(): void
    {
        $cr = User::factory()->create(['role' => 'cr']);
        $student = User::factory()->create([
            'role' => 'student',
            'email' => 'student@example.test',
            'roll_number' => '22601222274',
        ]);
        $subject = Subject::query()->create(['name' => 'PHP', 'code' => 'PHP']);
        config([
            'services.google_sheets.attendance.spreadsheet_id' => 'attendance-sheet',
            'services.google_sheets.attendance.range' => 'CLASS Attendance!B6:ZZ',
            'services.google_sheets.attendance.subject_code' => 'PHP',
            'services.google_sheets.attendance.date_format' => 'd/m',
            'services.google_sheets.attendance.year' => 2026,
        ]);
        $this->mock(GoogleSheetsReader::class)
            ->shouldReceive('values')
            ->once()
            ->with('attendance-sheet', 'CLASS Attendance!B6:ZZ')
            ->andReturn([
                ['Roll Number', 'Student', 'YES', 'NO', '%', '', '11/08', '14/08'],
                ['22601222274', 'Demo Student', '1', '1', '50%', '', 'TRUE', 'FALSE'],
            ]);
        Sanctum::actingAs($cr);

        $this->postJson('/api/attendance/sync')
            ->assertOk()
            ->assertJsonPath('records_synced', 2);

        $this->assertDatabaseHas('attendance', [
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'date' => '2026-08-11',
            'status' => 'present',
        ]);
        $this->assertDatabaseHas('attendance', [
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'date' => '2026-08-14',
            'status' => 'absent',
        ]);
    }

    public function test_cr_can_sync_roster_and_assign_one_teacher_to_theory_and_lab_subjects(): void
    {
        $cr = User::factory()->create(['role' => 'cr']);
        $removedStudent = User::factory()->create(['role' => 'student', 'roll_number' => '22601222299']);
        $removedTokenId = $removedStudent->createToken('old-session')->accessToken->id;
        $php = Subject::query()->create(['name' => 'PHP with MySQL', 'code' => 'PHP']);
        $phpLab = Subject::query()->create(['name' => 'PHP with MySQL Lab', 'code' => 'PHP_LAB']);
        config([
            'services.google_sheets.roster.spreadsheet_id' => 'roster-sheet',
            'services.google_sheets.roster.range' => 'Roster!A1:E',
        ]);
        $this->mock(GoogleSheetsReader::class)
            ->shouldReceive('values')
            ->once()
            ->with('roster-sheet', 'Roster!A1:E')
            ->andReturn([
                ['email', 'name', 'role', 'roll_number', 'subject_codes'],
                ['student@example.edu', 'Demo Student', 'student', '22601222274', ''],
                ['cr@example.edu', 'Class Representative', 'cr', '22601222275', ''],
                ['teacher@example.edu', 'PHP Teacher', 'teacher', '', 'PHP,PHP_LAB'],
            ]);
        Sanctum::actingAs($cr);

        $this->postJson('/api/students/sync')
            ->assertOk()
            ->assertJsonPath('created', 3)
            ->assertJsonPath('teachers_assigned', 2);

        $teacher = User::query()->where('email', 'teacher@example.edu')->firstOrFail();
        $this->assertSame($teacher->id, $php->fresh()->teacher_id);
        $this->assertSame($teacher->id, $phpLab->fresh()->teacher_id);
        $this->assertDatabaseHas('users', ['email' => 'student@example.edu', 'role' => 'student']);
        $this->assertDatabaseHas('users', ['email' => 'cr@example.edu', 'role' => 'cr']);
        $this->assertDatabaseHas('users', ['id' => $removedStudent->id, 'is_active' => false]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $removedTokenId]);
    }

    public function test_note_upload_uses_private_storage_and_downloads_through_the_api(): void
    {
        Storage::fake('local');
        $cr = User::factory()->create(['role' => 'cr']);
        $subject = Subject::query()->create(['name' => 'PHP', 'code' => 'PHP']);
        Sanctum::actingAs($cr);

        $response = $this->post('/api/notes', [
            'subject_id' => $subject->id,
            'title' => 'Week one notes',
            'file' => UploadedFile::fake()->create('lesson.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $noteId = $response->json('id');
        $note = Note::query()->findOrFail($noteId);
        Storage::disk('local')->assertExists($note->file_path);
        $this->assertArrayNotHasKey('file_path', $response->json());

        $this->get('/api/notes/'.$noteId.'/download')->assertOk();
    }

    public function test_notice_persists_frontend_category_pinned_state_and_attachment_availability(): void
    {
        $cr = User::factory()->create(['role' => 'cr']);
        Sanctum::actingAs($cr);

        $this->postJson('/api/notices', [
            'title' => 'Exam timetable',
            'content' => 'Check the Exams page for the schedule.',
            'category' => 'Examination',
            'pinned' => true,
        ])->assertCreated()
            ->assertJsonPath('category', 'Examination')
            ->assertJsonPath('pinned', true)
            ->assertJsonPath('has_attachment', false);
    }

    public function test_cr_can_upload_and_download_an_exam_seating_plan(): void
    {
        Storage::fake('local');
        $cr = User::factory()->create(['role' => 'cr']);
        $subject = Subject::query()->create(['name' => 'PHP', 'code' => 'PHP']);
        $exam = Exam::query()->create([
            'subject_id' => $subject->id,
            'type' => 'CA1',
            'exam_date' => '2026-10-12',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'room' => 'Room 101',
        ]);
        Sanctum::actingAs($cr);

        $this->post('/api/exams/'.$exam->id.'/seating-plan', [
            'file' => UploadedFile::fake()->create('seating.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('has_seating_plan', true);

        $this->get('/api/exams/'.$exam->id.'/seating-plan')->assertOk();
    }
}
