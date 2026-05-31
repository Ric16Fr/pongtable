<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->uuid('public_token')->unique();
            $table->enum('status', ['setup', 'group', 'ko', 'finished'])->default('setup');
            $table->unsignedInteger('group_match_duration_minutes')->default(10);
            $table->unsignedInteger('ko_match_duration_minutes')->default(15);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
