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
            $table->string('bucket', 10)->nullable();
        });

        Schema::table('manga_chapters', function (Blueprint $table) {
            $table->string('bucket', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manga_chapters', function (Blueprint $table) {
            $table->dropColumn('bucket');
        });

        Schema::table('manga_detail', function (Blueprint $table) {
            $table->dropColumn('bucket');
        });
    }
};
