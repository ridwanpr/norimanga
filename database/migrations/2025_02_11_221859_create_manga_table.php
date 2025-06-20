<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('manga', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->timestamps();
        });

        Schema::create('manga_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manga_id');
            $table->foreign('manga_id')->references('id')->on('manga')->onDelete('cascade');
            $table->string('status', 15);
            $table->string('cover');
            $table->string('type', 15);
            $table->string('release_year')->nullable()->default(null);
            $table->text('author')->nullable()->default(null);
            $table->string('artist', 250)->nullable()->default(null);
            $table->integer('views')->nullable()->default(0);
            $table->text('synopsis')->nullable()->default(null);
            $table->timestamps();
        });

        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug');
            $table->timestamps();
        });

        Schema::create('manga_genre', function (Blueprint $table) {
            $table->unsignedBigInteger('manga_id');
            $table->unsignedBigInteger('genre_id');
            $table->foreign('manga_id')->references('id')->on('manga')->onDelete('cascade');
            $table->foreign('genre_id')->references('id')->on('genres')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('manga_chapters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manga_id');
            $table->foreign('manga_id')->references('id')->on('manga')->onDelete('cascade');
            $table->string('title');
            $table->integer('chapter_number');
            $table->string('slug');
            $table->json('image');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manga');
        Schema::dropIfExists('manga_detail');
        Schema::dropIfExists('genres');
        Schema::dropIfExists('manga_genre');
        Schema::dropIfExists('manga_chapters');
    }
};
