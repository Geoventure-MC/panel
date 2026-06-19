<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('options_rpc', function (Blueprint $table) {
            if (!Schema::hasColumn('options_rpc', 'rpc_large_image')) {
                $table->text('rpc_large_image')->nullable()->after('rpc_state');
            }
            if (!Schema::hasColumn('options_rpc', 'rpc_small_image')) {
                $table->text('rpc_small_image')->nullable()->after('rpc_large_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('options_rpc', function (Blueprint $table) {
            $table->dropColumn(['rpc_large_image', 'rpc_small_image']);
        });
    }
};
