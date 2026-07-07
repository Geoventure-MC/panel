<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_mods', function (Blueprint $table) {
            $table->string('category', 100)->nullable()->after('icon');
            $table->string('author', 100)->nullable()->after('category');
            $table->string('version', 50)->nullable()->after('author');
        });
    }

    public function down(): void
    {
        Schema::table('community_mods', function (Blueprint $table) {
            $table->dropColumn(['category', 'author', 'version']);
        });
    }
};
