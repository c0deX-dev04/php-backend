<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index()
    {
        return Subject::query()->with('teacher:id,name')->orderBy('name')->paginate(50);
    }

    public function store(Request $request): Subject
    {
        return Subject::query()->create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:subjects,code'],
        ]));
    }

    public function show(Subject $subject): Subject
    {
        return $subject;
    }
}
