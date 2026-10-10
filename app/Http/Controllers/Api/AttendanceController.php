<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Services\AttendanceSheetSyncService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AttendanceController extends Controller
{
    public function sync(AttendanceSheetSyncService $service): JsonResponse
    {
        try {
            return response()->json([
                'message' => 'Attendance synchronized.',
                'records_synced' => $service->sync(),
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'The Google Sheet contains invalid attendance data.',
                'errors' => $exception->errors(),
            ], 422);
        } catch (RequestException) {
            return response()->json(['message' => 'The Google Sheets API request failed.'], 502);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'subject_id' => ['sometimes', 'integer', 'exists:subjects,id'],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
        ]);

        return Attendance::query()
            ->with(['student:id,name,roll_number', 'subject:id,name,code'])
            ->leftJoin('users as students', 'students.id', '=', 'attendance.user_id')
            ->select('attendance.*')
            ->when(isset($filters['subject_id']), fn ($query) => $query->where('subject_id', $filters['subject_id']))
            ->when(isset($filters['date']), fn ($query) => $query->whereDate('date', $filters['date']))
            ->when($request->user()->role === 'student', fn ($query) => $query->where('user_id', $request->user()->id))
            ->when(isset($filters['user_id']) && $request->user()->role !== 'student', fn ($query) => $query->where('user_id', $filters['user_id']))
            ->orderByDesc('attendance.date')
            ->orderByRaw("CASE WHEN students.roll_number IS NULL OR students.roll_number = '' THEN 1 ELSE 0 END")
            ->orderByRaw('CAST(SUBSTR(students.roll_number, -3) AS INTEGER) ASC')
            ->orderBy('students.roll_number')
            ->orderBy('students.name')
            ->paginate(50);
    }

    public function store(Request $request): Attendance
    {
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where(fn (Builder $query) => $query->whereIn('role', ['student', 'cr'])->where('is_active', true))],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', Rule::in(['present', 'absent'])],
        ]);

        Validator::make($data, [
            'user_id' => [Rule::unique('attendance', 'user_id')->where(fn (Builder $query) => $query
                ->where('subject_id', $data['subject_id'])
                ->whereDate('date', $data['date']))],
        ])->validate();

        return Attendance::query()->create($data);
    }

    public function show(Attendance $attendance, Request $request): Attendance
    {
        abort_if($request->user()->role === 'student' && $attendance->user_id !== $request->user()->id, 404);

        return $attendance->load(['student:id,name,roll_number', 'subject:id,name,code']);
    }

    public function update(Request $request, Attendance $attendance): Attendance
    {
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where(fn (Builder $query) => $query->whereIn('role', ['student', 'cr'])->where('is_active', true))],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', Rule::in(['present', 'absent'])],
        ]);

        Validator::make($data, [
            'user_id' => [Rule::unique('attendance', 'user_id')->ignore($attendance->id)->where(fn (Builder $query) => $query
                ->where('subject_id', $data['subject_id'])
                ->whereDate('date', $data['date']))],
        ])->validate();

        $attendance->update($data);

        return $attendance->refresh();
    }

    public function destroy(Attendance $attendance)
    {
        $attendance->delete();

        return response()->noContent();
    }
}
