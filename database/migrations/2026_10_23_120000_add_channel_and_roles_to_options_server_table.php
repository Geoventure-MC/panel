<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('options_server', function (Blueprint $table) {
            if (!Schema::hasColumn('options_server', 'channel')) {
                $table->string('channel', 16)->default('stable')->after('instance_slug');
            }
            if (!Schema::hasColumn('options_server', 'allowed_roles')) {
                $table->text('allowed_roles')->nullable()->after('theme_color');
            }
            if (!Schema::hasColumn('options_server', 'test_roles')) {
                $table->text('test_roles')->nullable()->after('allowed_roles');
            }
            if (!Schema::hasColumn('options_server', 'test_ip')) {
                $table->string('test_ip')->nullable()->after('test_roles');
            }
            if (!Schema::hasColumn('options_server', 'test_port')) {
                $table->string('test_port', 16)->nullable()->after('test_ip');
            }
            if (!Schema::hasColumn('options_server', 'test_data_folder')) {
                $table->string('test_data_folder', 64)->nullable()->after('test_port');
            }
        });
    }

    public function down(): void
    {
        Schema::table('options_server', function (Blueprint $table) {
            $cols = array_filter(
                ['channel', 'allowed_roles', 'test_roles', 'test_ip', 'test_port', 'test_data_folder'],
                fn ($c) => Schema::hasColumn('options_server', $c)
            );
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }
};
