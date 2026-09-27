<?php

namespace App\Services;

use App\Models\Subject;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AttendanceSheetSyncService
{
    public function sync(): int
    {
        $spreadsheetId = config('services.google_sheets.spreadsheet_id');
        $range = config('services.google_sheets.range');
        $apiKey = config('services.google_sheets.api_key');

        if (! $spreadsheetId || ! $range || ! $apiKey) {
            throw new RuntimeException('Google Sheets sync is not configured.');
        }

        $url = 'https://sheets.googleapis.com/v4/spreadsheets/'
            .rawurlencode($spreadsheetId).'/values/'.rawurlencode($range);
        $rows = Http::timeout(15)->get($url, ['key' => $apiKey])->throw()->json('values', []);

        if (! is_array($rows) || count($rows) < 2) {
            return 0;
        }

        $headers = array_map(fn ($header) => Str::snake(Str::lower(trim((string) $header))), array_shift($rows));
        $headerPositions = array_flip($headers);
        $requiredHeaders = ['email', 'subject_code', 'date', 'status'];

        if (count($headerPositions) !== count($headers) || array_diff($requiredHeaders, $headers)) {
            throw ValidationException::withMessages([
                'sheet' => ['The sheet must have unique email, subject_code, date, and status columns.'],
            ]);
        }

        $students = User::query()->where('role', 'student')->get()->keyBy(fn (User $user) => strtolower($user->email));
        $subjects = Subject::query()->get()->keyBy(fn (Subject $subject) => strtoupper($subject->code));
        $records = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $value = fn (string $column) => trim((string) ($row[$headerPositions[$column]] ?? ''));
            $email = strtolower($value('email'));
            $subjectCode = strtoupper($value('subject_code'));
            $date = $value('date');
            $status = strtolower($value('status'));
            $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            $dateErrors = DateTimeImmutable::getLastErrors();

            if (! $students->has($email)
                || ! $subjects->has($subjectCode)
                || ! in_array($status, ['present', 'absent'], true)
                || ! $parsedDate
                || $parsedDate->format('Y-m-d') !== $date
                || ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count']))) {
                throw ValidationException::withMessages([
                    'sheet' => ["Row {$line} has an unknown student/subject or invalid date/status."],
                ]);
            }

            $studentId = $students->get($email)->id;
            $subjectId = $subjects->get($subjectCode)->id;
            $key = "{$studentId}:{$subjectId}:{$date}";
            $records[$key] = [
                'user_id' => $studentId,
                'subject_id' => $subjectId,
                'date' => $date,
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($records === []) {
            return 0;
        }

        DB::transaction(fn () => DB::table('attendance')->upsert(
            array_values($records),
            ['user_id', 'subject_id', 'date'],
            ['status', 'updated_at'],
        ));

        return count($records);
    }
}
