<?php

namespace Tests\Feature;

use App\Models\LauncherDiagnostic;
use App\Models\McpKey;
use App\Models\McpSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lecture des diagnostics du launcher par l'API MCP (portée read). */
class DiagnosticMcpTest extends TestCase
{
    use RefreshDatabase;

    private string $plain;

    protected function setUp(): void
    {
        parent::setUp();
        McpSetting::put('enabled', '1');
        $this->plain = McpKey::generate();
        McpKey::create(['name' => 'test', 'key_hash' => McpKey::hash($this->plain), 'key_prefix' => substr($this->plain, 0, 8), 'scope' => 'read']);
    }

    private function tool(string $name, array $args = []): array
    {
        $r = $this->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $name, 'arguments' => (object) $args]],
            ['Authorization' => 'Bearer ' . $this->plain])->assertOk()->json('result');

        return ['error' => $r['isError'] ?? false, 'data' => json_decode($r['content'][0]['text'], true)];
    }

    private function diagnostic(array $over = []): LauncherDiagnostic
    {
        return LauncherDiagnostic::create($over + [
            'launcher_version' => '2.0.14', 'instance' => 'geoventure', 'os' => 'windows', 'summary' => 'Bob · mémoire', 'report' => 'texte',
            'ip_hash' => str_repeat('a', 64), 'handled' => false,
        ]);
    }

    public function test_la_liste_ne_donne_ni_rapport_ni_ip_et_filtre(): void
    {
        $this->diagnostic();
        $this->diagnostic(['summary' => 'Zoé · réseau', 'handled' => true]);
        $all = $this->tool('diagnostics_list')['data'];
        $this->assertSame(2, $all['total']);
        $this->assertArrayNotHasKey('report', $all['items'][0]);
        $this->assertArrayNotHasKey('ip_hash', $all['items'][0]);
        $this->assertSame(1, $this->tool('diagnostics_list', ['pending_only' => true])['data']['total']);
        $this->assertSame('Zoé · réseau', $this->tool('diagnostics_list', ['search' => 'Zoé'])['data']['items'][0]['summary']);
        $this->assertSame(0, $this->tool('diagnostics_list', ['search' => '%'])['data']['total']);
    }

    public function test_le_detail_masque_et_se_lit_par_morceaux(): void
    {
        $d = $this->diagnostic(['report' => 'bob@exemple.fr 192.168.1.20 forge 47.4.23 Windows 10.0.26100.9974 ' . str_repeat('é', 50)]);
        $r = $this->tool('diagnostic_get', ['id' => $d->id])['data'];
        $this->assertStringNotContainsString('bob@', $r['report']);
        $this->assertStringNotContainsString('192.168', $r['report']);
        $this->assertStringContainsString('47.4.23 Windows 10.0.26100.9974', $r['report']);
        $this->assertFalse($r['has_more']);
        $part = $this->tool('diagnostic_get', ['id' => $d->id, 'offset' => $r['total_chars'] - 3, 'length' => 2])['data'];
        $this->assertSame('éé', $part['report']);
        $this->assertTrue($part['has_more']);
    }

    public function test_un_identifiant_inconnu_est_une_erreur_et_rien_n_est_modifie(): void
    {
        $d = $this->diagnostic();
        $this->assertTrue($this->tool('diagnostic_get', ['id' => 9999])['error']);
        $this->tool('diagnostic_get', ['id' => $d->id]);
        $this->assertFalse($d->fresh()->handled);
    }
}
