<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API MCP (Model Context Protocol) : clés, journal des appels, réglages.
 * Idempotente : chaque table n'est créée que si elle n'existe pas déjà.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mcp_keys')) {
            Schema::create('mcp_keys', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('key_hash', 64)->unique();      // SHA-256 de la clé : la clé elle-même n'est JAMAIS stockée
                $table->string('key_prefix', 12);               // 8 premiers caractères, affichables
                $table->string('scope', 10)->default('read');   // read | write | admin
                $table->json('allowed_tools')->nullable();      // restriction optionnelle (liste de noms d'outils)
                $table->json('allowed_ips')->nullable();        // IP / CIDR autorisés (optionnel)
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->string('last_used_ip_hash', 64)->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index('key_prefix');
            });
        }

        if (! Schema::hasTable('mcp_calls')) {
            Schema::create('mcp_calls', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mcp_key_id')->nullable()->index();
                $table->string('key_name', 100)->nullable();
                $table->string('tool', 64);
                $table->text('arguments')->nullable();          // tronqués, SANS secrets
                $table->string('status', 12);                   // ok | error | denied
                $table->string('error', 255)->nullable();
                $table->unsignedInteger('duration_ms')->default(0);
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }

        if (! Schema::hasTable('mcp_settings')) {
            Schema::create('mcp_settings', function (Blueprint $table) {
                $table->string('key', 64)->primary();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_settings');
        Schema::dropIfExists('mcp_calls');
        Schema::dropIfExists('mcp_keys');
    }
};
