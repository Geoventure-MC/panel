<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('options_azuriom', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->string('api_key')->nullable();
            $table->integer('server_id')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('server_id')
                  ->references('server_id')
                  ->on('options_server')
                  ->nullOnDelete();
        });

        // Seed from existing options_general if azuriom_url exists
        $general = DB::table('options_general')->first();
        if ($general && !empty($general->azuriom_url)) {
            DB::table('options_azuriom')->insert([
                'name'       => 'Azuriom Principal',
                'url'        => $general->azuriom_url,
                'api_key'    => $general->azuriom_api_key ?? null,
                'server_id'  => null,
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('options_azuriom');
    }
};
