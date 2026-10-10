<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = ['subject_id', 'created_by', 'type', 'exam_date', 'start_time', 'end_time', 'room'];

    protected $hidden = ['seating_attachment'];

    protected $appends = ['has_seating_plan'];

    protected function casts(): array
    {
        return ['exam_date' => 'date'];
    }

    public function getHasSeatingPlanAttribute(): bool
    {
        return filled($this->seating_attachment);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
