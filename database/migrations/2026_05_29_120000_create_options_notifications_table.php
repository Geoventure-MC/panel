<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('options_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('info'); // info|warning|maintenance|event
            $table->text('message');
            $table->string('url')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('options_notifications');
    }
};
