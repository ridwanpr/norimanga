<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    protected $table = 'genres';

    protected $fillable = ['name', 'slug'];

    // ✅ Many-to-Many: Genre belongs to many Manga
    public function manga(): BelongsToMany
    {
        return $this->belongsToMany(Manga::class, 'manga_genre', 'genre_id', 'manga_id');
    }
}
