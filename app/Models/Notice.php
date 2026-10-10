<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notice extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'content', 'category', 'pinned', 'attachment', 'created_by'];

    protected $hidden = ['attachment'];

    protected $appends = ['has_attachment'];

    protected function casts(): array
    {
        return ['pinned' => 'boolean'];
    }

    public function getHasAttachmentAttribute(): bool
    {
        return filled($this->attachment);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
