<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NoteController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'subject_id' => ['sometimes', 'integer', 'exists:subjects,id'],
        ]);

        return Note::query()
            ->with(['subject:id,name,code', 'uploader:id,name'])
            ->when(isset($filters['subject_id']), fn ($query) => $query->where('subject_id', $filters['subject_id']))
            ->latest()
            ->paginate(50);
    }

    public function store(Request $request): Note
    {
        $data = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf,ppt,pptx,doc,docx', 'max:10240'],
        ]);

        $file = $data['file'];

        return Note::query()->create([
            'subject_id' => $data['subject_id'],
            'uploaded_by' => $request->user()->id,
            'title' => $data['title'],
            'file_path' => $file->store('notes'),
            'file_type' => strtolower($file->extension()),
        ]);
    }

    public function show(Note $note): Note
    {
        return $note->load(['subject:id,name,code', 'uploader:id,name']);
    }

    public function update(Request $request, Note $note): Note
    {
        $data = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'file' => ['sometimes', 'file', 'mimes:pdf,ppt,pptx,doc,docx', 'max:10240'],
        ]);

        $oldPath = $note->file_path;
        $note->fill(['subject_id' => $data['subject_id'], 'title' => $data['title']]);

        if (isset($data['file'])) {
            $note->file_path = $data['file']->store('notes');
            $note->file_type = strtolower($data['file']->extension());
        }

        $note->save();

        if (isset($data['file'])) {
            Storage::disk('local')->delete($oldPath);
        }

        return $note->refresh();
    }

    public function destroy(Note $note)
    {
        Storage::disk('local')->delete($note->file_path);
        $note->delete();

        return response()->noContent();
    }

    public function download(Note $note)
    {
        abort_unless(Storage::disk('local')->exists($note->file_path), 404);

        $downloadName = Str::slug($note->title).'.'.$note->file_type;

        return Storage::disk('local')->download($note->file_path, $downloadName);
    }
}
