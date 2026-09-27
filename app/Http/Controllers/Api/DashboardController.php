<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Exam;
use App\Models\LabAssignment;
use App\Models\Notice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $attendance = Attendance::query()
            ->when($request->user()->role === 'student', fn ($query) => $query->where('user_id', $request->user()->id));
        $totalRecords = (clone $attendance)->count();
        $presentRecords = (clone $attendance)->where('status', 'present')->count();

        return response()->json(['data' => [
            'attendance' => [
                'total_records' => $totalRecords,
                'present_records' => $presentRecords,
                'percentage' => $totalRecords > 0 ? round($presentRecords / $totalRecords * 100, 2) : 0,
            ],
            'upcoming_exams' => Exam::query()
                ->with('subject:id,name,code')
                ->whereDate('exam_date', '>=', today())
                ->orderBy('exam_date')
                ->limit(5)
                ->get(),
            'upcoming_assignments' => LabAssignment::query()
                ->with(['lab:id,name', 'subject:id,name,code'])
                ->where('deadline', '>=', now())
                ->orderBy('deadline')
                ->limit(5)
                ->get(),
            'recent_notices' => Notice::query()->latest()->limit(5)->get(),
        ]]);
    }
}
