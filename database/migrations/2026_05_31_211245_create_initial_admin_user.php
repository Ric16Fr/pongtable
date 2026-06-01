<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->where('name', 'admin')->exists()) {
            return;
        }

        DB::table('users')->insert([
            'name' => 'admin',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('name', 'admin')
            ->where('role', 'admin')
            ->delete();
    }
};
