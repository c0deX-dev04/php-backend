<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;

class SubjectController extends Controller
{
    public function index()
    {
        return Subject::query()->orderBy('name')->paginate(50);
    }

    public function show(Subject $subject): Subject
    {
        return $subject;
    }
}
