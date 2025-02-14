<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MangaDetail extends Model
{
    protected $table = 'manga_detail';

    protected $fillable = [
        'manga_id',
        'status',
        'type',
        'release_year',
        'author',
        'artist',
        'views',
        'synopsis',
        'cover',
        'bucket',
    ];

    // ✅ One-to-One: MangaDetail belongs to Manga
    public function manga(): BelongsTo
    {
        return $this->belongsTo(Manga::class, 'manga_id');
    }
}
