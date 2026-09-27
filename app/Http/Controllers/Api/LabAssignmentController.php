<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LabAssignment;
use Illuminate\Http\Request;

class LabAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'lab_id' => ['sometimes', 'integer', 'exists:labs,id'],
            'subject_id' => ['sometimes', 'integer', 'exists:subjects,id'],
        ]);

        return LabAssignment::query()
            ->with(['lab:id,name', 'subject:id,name,code'])
            ->when(isset($filters['lab_id']), fn ($query) => $query->where('lab_id', $filters['lab_id']))
            ->when(isset($filters['subject_id']), fn ($query) => $query->where('subject_id', $filters['subject_id']))
            ->orderBy('deadline')
            ->paginate(50);
    }

    public function store(Request $request): LabAssignment
    {
        return LabAssignment::query()->create($request->validate([
            'lab_id' => ['required', 'integer', 'exists:labs,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'problem_statement' => ['required', 'string'],
            'deadline' => ['required', 'date'],
        ]));
    }

    public function show(LabAssignment $labAssignment): LabAssignment
    {
        return $labAssignment->load(['lab:id,name', 'subject:id,name,code']);
    }

    public function update(Request $request, LabAssignment $labAssignment): LabAssignment
    {
        $labAssignment->update($request->validate([
            'lab_id' => ['required', 'integer', 'exists:labs,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'problem_statement' => ['required', 'string'],
            'deadline' => ['required', 'date'],
        ]));

        return $labAssignment->refresh();
    }

    public function destroy(LabAssignment $labAssignment)
    {
        $labAssignment->delete();

        return response()->noContent();
    }
}
