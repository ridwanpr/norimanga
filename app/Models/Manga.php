<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Manga extends Model
{
    protected $table = 'manga';

    protected $fillable = ['title', 'slug'];

    // ✅ One-to-One: Manga has one MangaDetail
    public function detail(): HasOne
    {
        return $this->hasOne(MangaDetail::class, 'manga_id');
    }

    // ✅ One-to-Many: Manga has many Chapters
    public function chapters(): HasMany
    {
        return $this->hasMany(MangaChapter::class, 'manga_id');
    }

    // ✅ Many-to-Many: Manga belongs to many Genres
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'manga_genre', 'manga_id', 'genre_id');
    }
}
