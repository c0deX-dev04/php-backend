<?php

namespace App\Http\Controllers\Api;

use App\Enums\ExamType;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        if ($exam->seating_attachment) {
            Storage::disk('local')->delete($exam->seating_attachment);
        }

        $exam->delete();

        return response()->noContent();
    }

    public function uploadSeatingPlan(Request $request, Exam $exam): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $oldPath = $exam->seating_attachment;
        $exam->seating_attachment = $data['file']->store('exam-seating');
        $exam->save();

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return response()->json(['message' => 'Seating plan uploaded.', 'has_seating_plan' => true]);
    }

    public function downloadSeatingPlan(Exam $exam)
    {
        abort_unless($exam->seating_attachment && Storage::disk('local')->exists($exam->seating_attachment), 404);

        return Storage::disk('local')->download(
            $exam->seating_attachment,
            Str::slug($exam->subject?->name.'-'.$exam->type.'-seating').'.pdf',
        );
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
