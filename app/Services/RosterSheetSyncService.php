<?php

namespace App\Services;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RosterSheetSyncService
{
    public function sync(): array
    {
        $spreadsheetId = config('services.google_sheets.roster.spreadsheet_id');
        $range = config('services.google_sheets.roster.range');

        if (! $spreadsheetId || ! $range) {
            throw new RuntimeException('Google Sheets roster is not configured.');
        }

        $rows = app(GoogleSheetsReader::class)->values($spreadsheetId, $range);
        if (count($rows) < 2) {
            return ['created' => 0, 'updated' => 0, 'teachers_assigned' => 0];
        }

        $headers = array_map(
            fn ($header) => Str::snake(Str::lower(trim((string) $header))),
            array_shift($rows),
        );
        $positions = array_flip($headers);
        $required = ['email', 'name', 'role', 'roll_number', 'subject_codes'];

        if (count($positions) !== count($headers) || array_diff($required, $headers)) {
            throw ValidationException::withMessages([
                'sheet' => ['Roster headers must be unique and include email, name, role, roll_number, and subject_codes.'],
            ]);
        }

        $subjects = Subject::query()->get()->keyBy(fn (Subject $subject) => strtoupper($subject->code));
        $accounts = [];
        $teacherAssignments = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $value = fn (string $column) => trim((string) ($row[$positions[$column]] ?? ''));
            $email = strtolower($value('email'));
            $name = $value('name');
            $role = strtolower($value('role'));
            $rollNumber = $value('roll_number');
            $subjectCodes = array_values(array_filter(array_map(
                fn (string $code) => strtoupper(trim($code)),
                preg_split('/[,;]+/', $value('subject_codes')) ?: [],
            )));

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)
                || $name === ''
                || ! in_array($role, ['student', 'cr', 'teacher'], true)
                || (in_array($role, ['student', 'cr'], true) && $rollNumber === '')
                || ($role === 'teacher' && $subjectCodes === [])) {
                throw ValidationException::withMessages([
                    'sheet' => ["Roster row {$line} has an invalid email, name, role, roll number, or teacher subject mapping."],
                ]);
            }

            if (isset($accounts[$email])) {
                throw ValidationException::withMessages(['sheet' => ["Roster row {$line} duplicates email {$email}."]]);
            }

            foreach ($subjectCodes as $code) {
                if (! $subjects->has($code)) {
                    throw ValidationException::withMessages(['sheet' => ["Roster row {$line} references unknown subject code {$code}."]]);
                }
            }

            $accounts[$email] = compact('email', 'name', 'role', 'rollNumber');
            if ($role === 'teacher') {
                foreach ($subjectCodes as $code) {
                    if (isset($teacherAssignments[$code])) {
                        throw ValidationException::withMessages(['sheet' => ["Subject {$code} has more than one assigned teacher."]]);
                    }
                    $teacherAssignments[$code] = $email;
                }
            }
        }

        if ($accounts === []) {
            throw ValidationException::withMessages([
                'sheet' => ['The roster has no account rows; no accounts were changed.'],
            ]);
        }

        $created = 0;
        $updated = 0;
        DB::transaction(function () use ($accounts, $teacherAssignments, $subjects, &$created, &$updated): void {
            $users = [];
            foreach ($accounts as $email => $account) {
                $user = User::query()->firstOrNew(['email' => $email]);
                if (! $user->exists) {
                    $user->password = Hash::make(Str::random(64));
                    $created++;
                } else {
                    $updated++;
                }

                $user->fill([
                    'name' => $account['name'],
                    'role' => $account['role'],
                    'roll_number' => $account['rollNumber'] ?: null,
                    'is_active' => true,
                ])->save();
                $users[$email] = $user;
            }

            $removedAccounts = User::query()
                ->whereNotIn('email', array_keys($accounts))
                ->where('is_active', true)
                ->get();
            foreach ($removedAccounts as $removedAccount) {
                $removedAccount->tokens()->delete();
                $removedAccount->update(['is_active' => false]);
            }

            Subject::query()->update(['teacher_id' => null]);
            foreach ($teacherAssignments as $code => $email) {
                $subjects->get($code)->update(['teacher_id' => $users[$email]->id]);
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'teachers_assigned' => count($teacherAssignments),
        ];
    }
}
