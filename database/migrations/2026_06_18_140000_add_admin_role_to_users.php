<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                // Niveau de rôle au-dessus de is_admin (qui reste la porte d'accès /admin).
                $table->string('role')->nullable()->default('admin')->after('is_admin');
            });
        }

        // ANTI-LOCKOUT : les admins existants deviennent superadmin pour garder
        // l'accès complet (gestion users, config, self-update, import/export).
        DB::table('users')->where('is_admin', true)->update(['role' => 'superadmin']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }
};
