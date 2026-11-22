<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('launcher_diagnostics')) {
            return;
        }
        // Rapports envoyés par le bouton « Diagnostic › Envoyer à l'équipe » du launcher.
        // Le launcher masque déjà jetons, mots de passe, e-mails et chemins personnels.
        Schema::create('launcher_diagnostics', function (Blueprint $table) {
            $table->id();
            $table->string('launcher_version', 64)->nullable();
            $table->string('instance', 64)->nullable();
            $table->string('os', 64)->nullable();
            $table->string('summary', 255)->nullable();
            $table->mediumText('report');
            $table->string('ip_hash', 64)->nullable()->index();
            $table->boolean('handled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('launcher_diagnostics');
    }
};
