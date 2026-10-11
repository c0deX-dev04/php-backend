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
    public function sync(): array
    {
        $spreadsheetId = config('services.google_sheets.attendance.spreadsheet_id');
        $range = config('services.google_sheets.attendance.range');
        $activationRange = config('services.google_sheets.attendance.activation_range');
        $subjectCode = strtoupper((string) config('services.google_sheets.attendance.subject_code'));

        if (! $spreadsheetId || ! $range || ! $activationRange || ! $subjectCode) {
            throw new RuntimeException('Google Sheets attendance is not configured.');
        }

        $rows = app(GoogleSheetsReader::class)->values($spreadsheetId, $range);

        if (! is_array($rows) || count($rows) < 2) {
            return [
                'records_synced' => 0,
                'students_processed' => 0,
                'active_dates' => 0,
            ];
        }

        $headers = array_shift($rows);
        if (strtolower(trim((string) ($headers[0] ?? ''))) !== 'roll number'
            || strtolower(trim((string) ($headers[1] ?? ''))) !== 'student'
            || count($headers) < 6) {
            throw ValidationException::withMessages([
                'sheet' => ['Expected Roll Number in column B, Student in column C, summary columns D-F, and attendance dates from column G.'],
            ]);
        }

        $activationRows = app(GoogleSheetsReader::class)->values($spreadsheetId, $activationRange);
        $activationRow = $activationRows[0] ?? [];

        $dateColumns = [];
        $columnCount = max(count($headers), count($activationRow));
        for ($index = 5; $index < $columnCount; $index++) {
            $activationValue = $activationRow[$index] ?? null;
            if ($activationValue === null || (is_string($activationValue) && trim($activationValue) === '')) {
                continue;
            }

            $activationValue = is_bool($activationValue)
                ? ($activationValue ? 'TRUE' : 'FALSE')
                : strtoupper(trim((string) $activationValue));

            if (! in_array($activationValue, ['TRUE', 'FALSE'], true)) {
                throw ValidationException::withMessages([
                    'sheet' => ['Row 5, column '.$this->columnLabel($index + 2).' must contain an activation checkbox.'],
                ]);
            }

            if ($activationValue === 'FALSE') {
                continue;
            }

            $date = $this->parseDate(trim((string) ($headers[$index] ?? '')));
            if (! $date) {
                throw ValidationException::withMessages([
                    'sheet' => ['Active column '.$this->columnLabel($index + 2).' does not contain a valid attendance date in row 6.'],
                ]);
            }

            $dateColumns[$index] = $date;
        }

        $subject = Subject::query()->where('code', $subjectCode)->first();
        if (! $subject) {
            throw new RuntimeException("Subject code {$subjectCode} is not in the database.");
        }

        if ($dateColumns === []) {
            return [
                'records_synced' => 0,
                'students_processed' => 0,
                'active_dates' => 0,
            ];
        }

        $students = User::query()->whereIn('role', ['student', 'cr'])->where('is_active', true)
            ->get()->keyBy(fn (User $user) => trim((string) $user->roll_number));

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
                if ($value === null || (is_string($value) && trim($value) === '')) {
                    continue;
                }

                $checkboxValue = is_bool($value) ? ($value ? 'TRUE' : 'FALSE') : strtoupper(trim((string) $value));
                if (! in_array($checkboxValue, ['TRUE', 'FALSE'], true)) {
                    throw ValidationException::withMessages([
                        'sheet' => ["Row {$line}, column ".$this->columnLabel($columnIndex + 2).' must contain a checked or unchecked checkbox.'],
                    ]);
                }

                $status = $checkboxValue === 'TRUE' ? 'present' : 'absent';
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
            return [
                'records_synced' => 0,
                'students_processed' => 0,
                'active_dates' => count(array_unique($dateColumns)),
            ];
        }

        DB::transaction(fn () => DB::table('attendance')->upsert(
            array_values($records),
            ['user_id', 'subject_id', 'date'],
            ['status', 'updated_at'],
        ));

        return [
            'records_synced' => count($records),
            'students_processed' => count(array_unique(array_column($records, 'user_id'))),
            'active_dates' => count(array_unique(array_column($records, 'date'))),
        ];
    }

    private function columnLabel(int $column): string
    {
        $label = '';
        while ($column > 0) {
            $column--;
            $label = chr(65 + ($column % 26)).$label;
            $column = intdiv($column, 26);
        }

        return $label;
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
// test
