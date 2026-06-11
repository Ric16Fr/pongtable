<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->boolean('play_placement_matches')->default(false);
            $table->enum('status', ['setup', 'group', 'placement', 'ko', 'finished'])->default('setup')->change();
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->enum('phase', ['group', 'placement', 'ko'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->enum('phase', ['group', 'ko'])->change();
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->enum('status', ['setup', 'group', 'ko', 'finished'])->default('setup')->change();
            $table->dropColumn('play_placement_matches');
        });
    }
};
