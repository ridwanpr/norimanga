<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MangaChapter extends Model
{
    protected $table = 'manga_chapters';

    protected $fillable = [
        'manga_id',
        'title',
        'chapter_number',
        'slug',
        'image',
    ];

    // ✅ One-to-Many: MangaChapter belongs to Manga
    public function manga(): BelongsTo
    {
        return $this->belongsTo(Manga::class, 'manga_id');
    }
}
