<?php

namespace App\Services;

use App\Models\Subject;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AttendanceSheetSyncService
{
    public function sync(): int
    {
        $spreadsheetId = config('services.google_sheets.attendance.spreadsheet_id');
        $range = config('services.google_sheets.attendance.range');
        $subjectCode = strtoupper((string) config('services.google_sheets.attendance.subject_code'));

        if (! $spreadsheetId || ! $range || ! $subjectCode) {
            throw new RuntimeException('Google Sheets attendance is not configured.');
        }

        $rows = app(GoogleSheetsReader::class)->values($spreadsheetId, $range);

        if (! is_array($rows) || count($rows) < 2) {
            return 0;
        }

        $headers = array_shift($rows);
        if (strtolower(trim((string) ($headers[0] ?? ''))) !== 'roll number'
            || strtolower(trim((string) ($headers[1] ?? ''))) !== 'student'
            || count($headers) < 7) {
            throw ValidationException::withMessages([
                'sheet' => ['Expected Roll Number in column B, Student in column C, and attendance dates from column H.'],
            ]);
        }

        $students = User::query()->whereIn('role', ['student', 'cr'])->where('is_active', true)
            ->get()->keyBy(fn (User $user) => trim((string) $user->roll_number));
        $subject = Subject::query()->where('code', $subjectCode)->first();
        if (! $subject) {
            throw new RuntimeException("Subject code {$subjectCode} is not in the database.");
        }

        $dateColumns = [];
        foreach (array_slice($headers, 6, null, true) as $index => $header) {
            $date = $this->parseDate(trim((string) $header));
            if (! $date) {
                throw ValidationException::withMessages([
                    'sheet' => ['Column '.($index + 2).' does not contain a valid attendance date.'],
                ]);
            }
            $dateColumns[$index] = $date;
        }

        $records = [];

        foreach ($rows as $index => $row) {
            $line = $index + 7;
            $rollNumber = trim((string) ($row[0] ?? ''));
            if ($rollNumber === '') {
                continue;
            }

            if (! $students->has($rollNumber)) {
                throw ValidationException::withMessages([
                    'sheet' => ["Row {$line} has an unknown student roll number."],
                ]);
            }

            foreach ($dateColumns as $columnIndex => $date) {
                $value = $row[$columnIndex] ?? null;
                $status = $value === true || strtoupper(trim((string) $value)) === 'TRUE'
                    ? 'present'
                    : ($value === false || strtoupper(trim((string) $value)) === 'FALSE' ? 'absent' : null);

                if ($status === null) {
                    continue;
                }

                $studentId = $students->get($rollNumber)->id;
                $records["{$studentId}:{$date}"] = [
                    'user_id' => $studentId,
                    'subject_id' => $subject->id,
                    'date' => $date,
                    'status' => $status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
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

    private function parseDate(string $value): ?string
    {
        $format = (string) config('services.google_sheets.attendance.date_format', 'd/m');
        $year = (int) config('services.google_sheets.attendance.year', now()->year);
        $dateValue = str_contains($format, 'Y') ? $value : "{$value}/{$year}";
        $dateFormat = str_contains($format, 'Y') ? "!{$format}" : "!{$format}/Y";
        $date = DateTimeImmutable::createFromFormat($dateFormat, $dateValue);
        $errors = DateTimeImmutable::getLastErrors();

        if (! $date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
            return null;
        }

        return $date->format('Y-m-d');
    }
}
#test