<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wonder : concours de construction par équipes. Idempotente (hasTable).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wonder_editions')) {
            Schema::create('wonder_editions', function (Blueprint $table) {
                $table->id();
                $table->string('name', 40);
                $table->string('theme', 80);
                $table->string('world', 32)->default('wonder');
                $table->timestamp('opens_at')->nullable();
                $table->timestamp('closes_at')->nullable();
                $table->unsignedSmallInteger('team_size')->default(18);
                $table->unsignedSmallInteger('builders')->default(3);
                // Pondérations du barème (points sur 100 au total une fois normalisées).
                $table->unsignedSmallInteger('weight_technical')->default(40);
                $table->unsignedSmallInteger('weight_aesthetic')->default(30);
                $table->unsignedSmallInteger('weight_theme')->default(30);
                $table->text('rewards')->nullable();
                $table->string('status', 16)->default('draft'); // draft | published
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wonder_teams')) {
            Schema::create('wonder_teams', function (Blueprint $table) {
                $table->id();
                $table->foreignId('wonder_edition_id')->constrained('wonder_editions')->cascadeOnDelete();
                $table->string('name', 32);
                $table->string('color', 7)->default('#a78bfa');
                $table->json('builders')->nullable(); // pseudos autorisés à construire
                $table->json('reserves')->nullable(); // remplaçants (spectateurs)
                $table->json('images')->nullable();   // galerie (URLs)
                $table->timestamps();
                $table->unique(['wonder_edition_id', 'name']);
            });
        }

        if (! Schema::hasTable('wonder_scores')) {
            Schema::create('wonder_scores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('wonder_team_id')->constrained('wonder_teams')->cascadeOnDelete();
                $table->string('juror', 32);
                $table->decimal('technical', 4, 1)->default(0);
                $table->decimal('aesthetic', 4, 1)->default(0);
                $table->decimal('theme', 4, 1)->default(0);
                $table->timestamps();
                $table->unique(['wonder_team_id', 'juror']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wonder_scores');
        Schema::dropIfExists('wonder_teams');
        Schema::dropIfExists('wonder_editions');
    }
};
