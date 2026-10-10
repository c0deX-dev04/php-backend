<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RosterSheetSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        return User::query()
            ->whereIn('role', ['student', 'cr'])
            ->where('is_active', true)
            ->when($request->user()->role === 'student', fn ($query) => $query->whereKey($request->user()->id))
            ->select(['id', 'name', 'roll_number'])
            ->orderByRaw("CASE WHEN roll_number IS NULL OR roll_number = '' THEN 1 ELSE 0 END")
            ->orderByRaw('CAST(SUBSTR(roll_number, -3) AS INTEGER) ASC')
            ->orderBy('roll_number')
            ->orderBy('name')
            ->paginate(100);
    }

    public function sync(RosterSheetSyncService $service): JsonResponse
    {
        try {
            return response()->json([
                'message' => 'Student and staff roster synchronized.',
                ...$service->sync(),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }
}
