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
        Schema::table('manga_detail', function (Blueprint $table) {
            $table->year('release_year')->nullable()->change();
            $table->string('artist', 75)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manga_detail', function (Blueprint $table) {
            $table->year('release_year')->change();
            $table->string('artist', 75)->change();
        });
    }
};
