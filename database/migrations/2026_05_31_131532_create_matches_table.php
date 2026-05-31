<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->enum('phase', ['group', 'ko']);
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('ko_round')->nullable();
            $table->unsignedInteger('ko_position')->nullable();
            $table->foreignId('table_id')->constrained()->cascadeOnDelete();
            $table->foreignId('home_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('away_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('winner_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->enum('status', ['pending', 'pre_entry', 'active', 'scoring', 'finished'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'status']);
            $table->index(['tournament_id', 'phase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
