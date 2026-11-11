<?php

namespace Tests\Unit;

use App\Models\McpKey;
use App\Services\CommunityModRules;
use App\Services\Mcp\McpContext;
use App\Services\Mcp\McpRegistry;
use App\Services\Mcp\McpToolError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CommunityModRulesTest extends TestCase
{
    private function fails(array $data): bool
    {
        return Validator::make($data, CommunityModRules::rules())->fails();
    }

    private function good(): array
    {
        return [
            'filename' => 'ForgematicaPrinter-0.1.0+mc1.20.1.jar',
            'url' => 'https://cdn.modrinth.com/data/oqw1j8E7/versions/Iu8zwuRq/ForgematicaPrinter-0.1.0%2Bmc1.20.1.jar',
        ];
    }

    public function test_modrinth_https_et_nom_de_fichier_valides(): void
    {
        $this->assertFalse($this->fails($this->good()));
        $this->assertFalse($this->fails(['filename' => 'oculus-mc1.20.1-1.8.0.jar'] + $this->good()));
    }

    public function test_url_http_refusee(): void
    {
        $this->assertTrue($this->fails(['url' => 'http://cdn.modrinth.com/a.jar'] + $this->good()));
    }

    public function test_hote_non_autorise_refuse(): void
    {
        $this->assertTrue($this->fails(['url' => 'https://evil.example/a.jar'] + $this->good()));
        // Contournements classiques : identifiants, sous-domaine, suffixe, port.
        $this->assertTrue($this->fails(['url' => 'https://cdn.modrinth.com@evil.example/a.jar'] + $this->good()));
        $this->assertTrue($this->fails(['url' => 'https://cdn.modrinth.com.evil.example/a.jar'] + $this->good()));
        $this->assertTrue($this->fails(['url' => 'https://evilcdn.modrinth.com/a.jar'] + $this->good()));
        $this->assertTrue($this->fails(['url' => 'https://cdn.modrinth.com:8443/a.jar'] + $this->good()));
    }

    public function test_hotes_configures_pris_en_compte(): void
    {
        config(['mcp.community_mod_download_hosts' => ['mods.example.org']]);
        $this->assertFalse($this->fails(['url' => 'https://mods.example.org/a.jar'] + $this->good()));
        $this->assertTrue($this->fails($this->good())); // cdn.modrinth.com n'est plus listé

        config(['mcp.community_mod_download_hosts' => []]);
        $this->assertTrue($this->fails(['url' => 'https://mods.example.org/a.jar'] + $this->good()));
    }

    public function test_nom_de_fichier_contraint(): void
    {
        foreach (['../evil.jar', 'a/b.jar', 'a\\b.jar', 'a..b.jar', 'mod.zip', 'mod.jar.exe', '.jar', 'mod jar.jar', ''] as $name) {
            $this->assertTrue($this->fails(['filename' => $name] + $this->good()), "« {$name} » devrait être refusé");
        }
    }

    public function test_les_cinq_mods_existants_restent_valides(): void
    {
        $mods = [
            ['Forgematica-0.1.13-mc1.20.1.jar', 'https://cdn.modrinth.com/data/dCKRaeBC/versions/YncE2C0Y/Forgematica-0.1.13-mc1.20.1.jar'],
            ['MaFgLib-0.1.14-mc1.20.1.jar', 'https://cdn.modrinth.com/data/SKI34J7B/versions/adXONMb5/MaFgLib-0.1.14-mc1.20.1.jar'],
            ['Tweakerge-0.1.5-mc1.20.1.jar', 'https://cdn.modrinth.com/data/yke6wdGF/versions/CYN4k7x6/Tweakerge-0.1.5-mc1.20.1.jar'],
            ['ForgematicaPrinter-0.1.0+mc1.20.1.jar', 'https://cdn.modrinth.com/data/oqw1j8E7/versions/Iu8zwuRq/ForgematicaPrinter-0.1.0%2Bmc1.20.1.jar'],
            ['oculus-mc1.20.1-1.8.0.jar', 'https://cdn.modrinth.com/data/GchcoXML/versions/iQ1SwGc3/oculus-mc1.20.1-1.8.0.jar'],
        ];
        foreach ($mods as [$filename, $url]) {
            $this->assertFalse($this->fails(['filename' => $filename, 'url' => $url]), $filename);
        }
    }

    public function test_portees_des_outils_mcp(): void
    {
        $tools = McpRegistry::all();
        $this->assertSame('admin', $tools['community_mod_delete']->scope);
        $this->assertTrue($tools['community_mod_delete']->destructive);
        $this->assertSame('write', $tools['community_mod_upsert']->scope);
        // Le schéma de l'outil porte les règles communes (https + hôte autorisé).
        $this->assertSame(CommunityModRules::rules()['url'], $tools['community_mod_upsert']->rules['url']);
    }

    public function test_commandes_d_argent_refusees_a_une_cle_write(): void
    {
        $tool = McpRegistry::all()['game_command_send'];
        $this->assertSame(['give_coins', 'bank_deposit'], config('mcp.game_command_admin_types'));

        $key = new McpKey();
        $key->scope = 'write';
        $ctx = new McpContext($key, Request::create('/api/mcp', 'POST'));

        foreach (['give_coins', 'bank_deposit'] as $type) {
            try {
                ($tool->handler)(['type' => $type, 'target' => 'Steve', 'amount' => 1, 'reason' => 'test unitaire'], $ctx);
                $this->fail("Le type {$type} aurait dû être refusé à une clé write.");
            } catch (McpToolError $e) {
                $this->assertStringContainsString('Portée insuffisante', $e->getMessage());
            }
        }
    }
}
