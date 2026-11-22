<?php

namespace Tests\Feature;

use App\Http\Controllers\api\DiagnosticController;
use App\Models\LauncherDiagnostic;
use App\Models\TelemetryEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LauncherDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    private const REPORT = "=== Diagnostic Geoventure Launcher (Rust) ===\nLauncher : 2.0.10\nJoueur : Bob\n--- Réseau ---\nPanel : OK (80 ms) HTTP 200\nMojang : ÉCHEC (6000 ms) délai\n[12:00] [WARN] [crash] Plantage analysé : Le jeu a manqué de mémoire — Augmentez la RAM.\n";

    public function test_le_launcher_envoie_un_rapport(): void
    {
        $this->postJson('/utils/diagnostic', ['launcherVersion' => '2.0.10', 'instance' => 'geoventure', 'os' => 'windows x86_64', 'report' => self::REPORT])
            ->assertOk()->assertJson(['ok' => true]);
        $d = LauncherDiagnostic::firstOrFail();
        $this->assertSame('Bob · Le jeu a manqué de mémoire · injoignable : Mojang', $d->summary);
        $this->assertSame('geoventure', $d->instance);
        $this->assertNotNull($d->ip_hash);
    }

    public function test_rapport_vide_ou_trop_gros_refuse(): void
    {
        $this->postJson('/utils/diagnostic', ['report' => ''])->assertStatus(422);
        $this->postJson('/utils/diagnostic', ['report' => ['x']])->assertStatus(422);
        $this->postJson('/utils/diagnostic', ['report' => str_repeat('a', DiagnosticController::MAX_REPORT + 1)])->assertStatus(422);
        $this->assertSame(0, LauncherDiagnostic::count());
    }

    public function test_cinq_rapports_par_jour_et_par_pc(): void
    {
        for ($i = 0; $i < 5; $i++) {
            LauncherDiagnostic::create(['report' => 'x', 'ip_hash' => hash_hmac('sha256', '127.0.0.1', (string) config('app.key'))]);
        }
        $this->postJson('/utils/diagnostic', ['report' => self::REPORT])->assertStatus(429);
    }

    public function test_les_vieux_rapports_sont_effaces(): void
    {
        $old = LauncherDiagnostic::create(['report' => 'ancien']);
        $old->created_at = now()->subDays(LauncherDiagnostic::KEEP_DAYS + 1);
        $old->save();
        $this->postJson('/utils/diagnostic', ['report' => self::REPORT])->assertOk();
        $this->assertNull(LauncherDiagnostic::find($old->id));
    }

    public function test_page_admin_reservee_aux_comptes_du_panel(): void
    {
        $this->get('/admin/diagnostics')->assertRedirect();
    }

    private function admin(): User
    {
        $u = User::forceCreate(['name' => 'admin', 'email' => 'a@example.test', 'password' => bcrypt('x'), 'is_admin' => true, 'role' => 'superadmin']);
        return $u;
    }

    public function test_pages_admin_des_diagnostics(): void
    {
        $this->postJson('/utils/diagnostic', ['launcherVersion' => '2.0.10', 'report' => self::REPORT])->assertOk();
        $d = LauncherDiagnostic::firstOrFail();
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/diagnostics')->assertOk()->assertSee('Bob');
        $this->actingAs($admin)->get('/admin/diagnostics/' . $d->id)->assertOk()->assertSee('Mojang : ÉCHEC');
        $this->actingAs($admin)->patch('/admin/diagnostics/' . $d->id . '/toggle')->assertRedirect();
        $this->assertTrue($d->fresh()->handled);
        $this->actingAs($admin)->get('/admin/diagnostics')->assertOk()->assertDontSee('Bob');
        $this->actingAs($admin)->get('/admin/diagnostics?show=all')->assertOk()->assertSee('Bob');
    }

    public function test_statistiques_comptent_les_plantages_du_launcher_rust(): void
    {
        foreach (['launch', 'launch', 'game_crash:out_of_memory', 'game_crash:gpu_driver', 'game_crash:out_of_memory'] as $e) {
            TelemetryEvent::create(['event' => $e, 'server_id' => 'geoventure', 'launcher_version' => 'rust-2.0.10', 'os' => 'windows-x86_64', 'ip_hash' => 'h']);
        }
        $this->actingAs($this->admin())->get('/admin/stats')->assertOk()->assertSee('Out of memory')->assertSee('Graphics driver')->assertSee('Game crashes by cause');
    }

    public function test_reglages_de_l_ancien_launcher_signales(): void
    {
        $this->actingAs($this->admin())->get('/admin/general')->assertOk()->assertSee('Old launcher');
    }

    public function test_route_publique_sans_jeton_csrf(): void
    {
        // Les tests désactivent le contrôle CSRF : on vérifie que la route est bien dans les exceptions.
        $mw = new \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken($this->app, $this->app['encrypter']);
        $this->assertContains('utils/diagnostic', $mw->getExcludedPaths());
        $this->assertContains('utils/telemetry', $mw->getExcludedPaths());
    }
}
