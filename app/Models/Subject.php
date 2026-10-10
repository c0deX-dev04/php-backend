<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'teacher_id'];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function labAssignments(): HasMany
    {
        return $this->hasMany(LabAssignment::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }
}
