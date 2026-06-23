<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('options_launcher_content', function (Blueprint $table) {
            $table->id();
            $table->string('section'); // news_banner|shortcut|discover
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // Bootstrap icon class or emoji
            $table->string('image_url')->nullable();
            $table->string('url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('options_launcher_content');
    }
};
