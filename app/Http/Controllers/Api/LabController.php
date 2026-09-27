<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lab;
use Illuminate\Http\Request;

class LabController extends Controller
{
    public function index()
    {
        return Lab::query()->withCount('assignments')->orderBy('name')->paginate(50);
    }

    public function store(Request $request): Lab
    {
        return Lab::query()->create($request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:labs,name'],
            'description' => ['nullable', 'string'],
        ]));
    }

    public function show(Lab $lab): Lab
    {
        return $lab->load('assignments');
    }

    public function update(Request $request, Lab $lab): Lab
    {
        $lab->update($request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:labs,name,'.$lab->id],
            'description' => ['nullable', 'string'],
        ]));

        return $lab->refresh();
    }

    public function destroy(Lab $lab)
    {
        $lab->delete();

        return response()->noContent();
    }
}
