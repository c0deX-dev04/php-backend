<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceThreshold;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceThresholdController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => AttendanceThreshold::query()->get(['subject_id', 'minimum_percentage']),
        ]);
    }

    public function update(Request $request, Subject $subject): JsonResponse
    {
        $data = $request->validate([
            'minimum_percentage' => ['required', 'integer', 'between:0,100'],
        ]);

        $threshold = AttendanceThreshold::query()->updateOrCreate(
            ['subject_id' => $subject->id],
            ['minimum_percentage' => $data['minimum_percentage'], 'updated_by' => $request->user()->id],
        );

        return response()->json(['data' => $threshold]);
    }
}
