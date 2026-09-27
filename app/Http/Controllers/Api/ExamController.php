<?php

namespace App\Http\Controllers\Api;

use App\Enums\ExamType;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExamController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'subject_id' => ['sometimes', 'integer', 'exists:subjects,id'],
        ]);

        return Exam::query()
            ->with(['subject:id,name,code', 'creator:id,name'])
            ->when(isset($filters['subject_id']), fn ($query) => $query->where('subject_id', $filters['subject_id']))
            ->orderBy('exam_date')
            ->paginate(50);
    }

    public function store(Request $request): Exam
    {
        $data = $request->validate($this->rules());
        $data['created_by'] = $request->user()->id;

        return Exam::query()->create($data);
    }

    public function show(Exam $exam): Exam
    {
        return $exam->load(['subject:id,name,code', 'creator:id,name']);
    }

    public function update(Request $request, Exam $exam): Exam
    {
        $exam->update($request->validate($this->rules()));

        return $exam->refresh();
    }

    public function destroy(Exam $exam)
    {
        $exam->delete();

        return response()->noContent();
    }

    private function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'type' => ['required', Rule::enum(ExamType::class)],
            'exam_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room' => ['required', 'string', 'max:255'],
        ];
    }
}
