<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceThreshold extends Model
{
    protected $fillable = ['subject_id', 'minimum_percentage', 'updated_by'];

    protected function casts(): array
    {
        return ['minimum_percentage' => 'integer'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
