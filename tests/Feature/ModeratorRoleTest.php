<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ModeratorRoleTest extends TestCase
{
    /** Routes qui touchent à l'argent, aux commandes vers le jeu, aux fichiers, aux réglages ou aux rôles. */
    private const SUPERADMIN_ONLY = [
        'admin.game-commands', 'admin.game-commands.store',
        'admin.game-events', 'admin.game-events.update',
        'admin.scheduled-events.store', 'admin.scheduled-events.cancel', 'admin.scheduled-events.destroy',
        'admin.wonder.store', 'admin.wonder.update', 'admin.wonder.destroy', 'admin.wonder.sync', 'admin.wonder.publish',
        'admin.wonder.teams.store', 'admin.wonder.teams.update', 'admin.wonder.teams.destroy',
        'admin.wonder.scores.store', 'admin.wonder.scores.destroy',
        'admin.file-manager',
        'admin.general', 'admin.general.update', 'admin.security', 'admin.security.update', 'admin.maintenance.toggle',
        'admin.server', 'admin.server.add', 'admin.server.edit', 'admin.server.delete', 'admin.server.set-default', 'admin.server.sync',
        'admin.ui', 'admin.ui.update', 'admin.rpc', 'admin.rpc.update', 'admin.bg', 'admin.bg.update',
        'admin.whitelist', 'admin.whitelist.store', 'admin.ignore', 'admin.ignore.store',
        'admin.mods', 'admin.mods.addOptional', 'admin.mods.updateOptional', 'admin.mods.delete',
        'admin.loader', 'admin.loader.update',
        'admin.monitor.update', 'admin.monitor.test',
        'admin.users', 'admin.users.role', 'admin.settings.import', 'admin.update.run', 'admin.mcp', 'admin.sso.update',
    ];

    /** Ce que le modérateur garde : la modération et l'information. */
    private const MODERATOR_ALLOWED = [
        'admin.moderation.index', 'admin.moderation.sanctions', 'admin.moderation.reports', 'admin.moderation.player',
        'admin.notifications', 'admin.changelog', 'admin.stats', 'admin.monitor', 'admin.audit.index', 'admin.dashboard.live',
    ];

    private function user(string $role): User
    {
        $u = new User();
        $u->forceFill(['id' => 1, 'name' => $role, 'is_admin' => true, 'role' => $role]);

        return $u;
    }

    public function test_les_routes_sensibles_exigent_le_super_admin(): void
    {
        foreach (self::SUPERADMIN_ONLY as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "route inconnue : {$name}");
            $this->assertContains('superadmin', $route->gatherMiddleware(), "{$name} doit être réservée au super-admin");
        }
    }

    public function test_la_moderation_reste_ouverte_au_moderateur(): void
    {
        foreach (self::MODERATOR_ALLOWED as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "route inconnue : {$name}");
            $this->assertNotContains('superadmin', $route->gatherMiddleware(), "{$name} doit rester accessible au modérateur");
        }
    }

    public function test_un_moderateur_recoit_403_sur_les_commandes_de_jeu_et_les_reglages(): void
    {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32)), 'app.cipher' => 'AES-256-CBC']);
        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');

        $moderator = $this->user('moderator');

        foreach (['/admin/game-commands', '/admin/general', '/admin/server', '/admin/whitelist', '/file-manager'] as $url) {
            $this->actingAs($moderator)->getJson($url)->assertForbidden();
        }
    }

    public function test_le_role_moderateur_n_est_pas_super_admin(): void
    {
        $this->assertFalse($this->user('moderator')->isSuperAdmin());
        $this->assertTrue($this->user('superadmin')->isSuperAdmin());
    }
}
