<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'totp_secret')) {
                $table->string('totp_secret', 64)->nullable()->after('password');
            }
            if (!Schema::hasColumn('users', 'totp_recovery_codes')) {
                $table->json('totp_recovery_codes')->nullable()->after('totp_secret');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'totp_secret')) {
                $table->dropColumn('totp_secret');
            }
            if (Schema::hasColumn('users', 'totp_recovery_codes')) {
                $table->dropColumn('totp_recovery_codes');
            }
        });
    }
};
