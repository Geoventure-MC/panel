<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Anti-lockout safeguard: now that the admin route group is protected by
     * the `admin` middleware, make sure at least one admin exists so the panel
     * owner keeps access.
     */
    public function up(): void
    {
        // Ensure the is_admin column exists before backfilling.
        if (! Schema::hasColumn('users', 'is_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_admin')->default(false);
            });
        }

        if (DB::table('users')->where('is_admin', true)->doesntExist()) {
            DB::table('users')->orderBy('id')->limit(1)->update(['is_admin' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: we don't want to revoke admin access on rollback.
    }
};
