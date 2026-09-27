<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['lab_id', 'subject_id', 'title', 'problem_statement', 'deadline'];

    protected function casts(): array
    {
        return ['deadline' => 'datetime'];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
