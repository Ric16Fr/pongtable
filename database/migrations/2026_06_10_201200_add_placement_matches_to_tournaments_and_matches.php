<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->boolean('play_placement_matches')->default(false);
        });

        if ($this->isPostgres()) {
            $this->replaceCheck('tournaments', 'status', ['setup', 'group', 'placement', 'ko', 'finished']);
            $this->replaceCheck('matches', 'phase', ['group', 'placement', 'ko']);

            return;
        }

        Schema::table('tournaments', function (Blueprint $table) {
            $table->enum('status', ['setup', 'group', 'placement', 'ko', 'finished'])->default('setup')->change();
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->enum('phase', ['group', 'placement', 'ko'])->change();
        });
    }

    public function down(): void
    {
        if ($this->isPostgres()) {
            $this->replaceCheck('matches', 'phase', ['group', 'ko']);
            $this->replaceCheck('tournaments', 'status', ['setup', 'group', 'ko', 'finished']);
        } else {
            Schema::table('matches', function (Blueprint $table) {
                $table->enum('phase', ['group', 'ko'])->change();
            });

            Schema::table('tournaments', function (Blueprint $table) {
                $table->enum('status', ['setup', 'group', 'ko', 'finished'])->default('setup')->change();
            });
        }

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('play_placement_matches');
        });
    }

    private function isPostgres(): bool
    {
        return Schema::getConnection()->getDriverName() === 'pgsql';
    }

    /**
     * Drop and recreate the CHECK constraint Laravel uses to emulate enums on Postgres.
     *
     * @param  array<int, string>  $values
     */
    private function replaceCheck(string $table, string $column, array $values): void
    {
        $constraint = "{$table}_{$column}_check";
        $allowed = collect($values)->map(fn (string $value): string => "'$value'")->implode(', ');

        DB::statement("ALTER TABLE $table DROP CONSTRAINT IF EXISTS $constraint");
        DB::statement("ALTER TABLE $table ADD CONSTRAINT {$constraint} CHECK ($column::text IN ($allowed))");
    }
};
