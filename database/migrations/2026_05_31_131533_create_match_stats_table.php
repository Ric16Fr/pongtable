<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('cups_scored')->default(0);
            $table->unsignedInteger('throws')->default(0);
            $table->unsignedInteger('penalty_cups')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_stats');
    }
};
