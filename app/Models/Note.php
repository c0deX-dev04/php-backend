<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    use HasFactory;

    protected $fillable = ['subject_id', 'uploaded_by', 'title', 'file_path', 'file_type'];

    protected $hidden = ['file_path'];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
