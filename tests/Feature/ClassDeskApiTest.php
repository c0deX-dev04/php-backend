<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Note;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-password',
            'role' => 'teacher',
        ])->assertUnauthorized();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-password',
            'role' => 'cr',
        ])->assertOk()->assertJsonPath('user.role', 'cr');

        $this->withToken($response->json('token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
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
        $student = User::factory()->create(['role' => 'student', 'email' => 'student@example.test']);
        $subject = Subject::query()->create(['name' => 'PHP', 'code' => 'PHP']);
        config([
            'services.google_sheets.api_key' => 'test-key',
            'services.google_sheets.spreadsheet_id' => 'test-sheet',
            'services.google_sheets.range' => 'Attendance!A:D',
        ]);
        Http::fake([
            'sheets.googleapis.com/*' => Http::response([
                'values' => [
                    ['email', 'subject_code', 'date', 'status'],
                    ['student@example.test', 'PHP', '2026-09-01', 'Present'],
                ],
            ]),
        ]);
        Sanctum::actingAs($cr);

        $this->postJson('/api/attendance/sync')
            ->assertOk()
            ->assertJsonPath('records_synced', 1);

        $this->assertDatabaseHas('attendance', [
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'date' => '2026-09-01',
            'status' => 'present',
        ]);
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
}
