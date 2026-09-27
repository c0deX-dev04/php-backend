<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NoticeController extends Controller
{
    public function index()
    {
        return Notice::query()->with('creator:id,name')->latest()->paginate(50);
    }

    public function store(Request $request): Notice
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'attachment' => ['sometimes', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        if (isset($data['attachment'])) {
            $data['attachment'] = $data['attachment']->store('notices');
        }

        $data['created_by'] = $request->user()->id;

        return Notice::query()->create($data);
    }

    public function show(Notice $notice): Notice
    {
        return $notice->load('creator:id,name');
    }

    public function update(Request $request, Notice $notice): Notice
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'attachment' => ['sometimes', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $oldPath = $notice->attachment;

        if (isset($data['attachment'])) {
            $data['attachment'] = $data['attachment']->store('notices');
        }

        $notice->update($data);

        if (isset($data['attachment']) && $oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return $notice->refresh();
    }

    public function destroy(Notice $notice)
    {
        if ($notice->attachment) {
            Storage::disk('local')->delete($notice->attachment);
        }

        $notice->delete();

        return response()->noContent();
    }

    public function download(Notice $notice)
    {
        abort_unless($notice->attachment && Storage::disk('local')->exists($notice->attachment), 404);

        return Storage::disk('local')->download(
            $notice->attachment,
            Str::slug($notice->title).'.pdf',
        );
    }
}
