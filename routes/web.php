<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminRpcController;
use App\Http\Controllers\AdminModController;
use App\Http\Controllers\AdminSecurityController;
use App\Http\Controllers\AdminServerController;
use App\Http\Controllers\AdminUIController;
use App\Http\Controllers\AdminWhitelistController;
use App\Http\Controllers\AdminLoaderController;
use App\Http\Controllers\AdminIgnoreController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\AdminConfigController;
use App\Http\Controllers\AdminNotificationController;
use App\Http\Controllers\AdminCommunityModController;
use App\Http\Controllers\users\AdminUserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SettingsExportController;
use App\Http\Controllers\AdminAuditController;
use App\Http\Controllers\AdminBgController;
use App\Http\Controllers\api\ApiController;
use App\Http\Controllers\api\FileController;
use App\Http\Controllers\api\ModController;
use App\Http\Controllers\api\NotificationController;
use App\Http\Controllers\api\ServerStatusController;
use App\Http\Controllers\api\TelemetryController;
use App\Http\Controllers\api\LeaderboardController;
use App\Http\Controllers\api\FactionController;
use App\Http\Controllers\api\CommunityModController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\Admin\StatsController;


Auth::routes(['register' => false]);

// Routes d'installation
Route::get('/install', [InstallController::class, 'showDatabase'])->name('install.database');
Route::post('/install', [InstallController::class, 'install'])->name('install.store');
Route::get('/install/finish', [InstallController::class, 'finish'])->name('install.finish');

// Redirection de la route racine vers la page de connexion ou admin selon l'état de connexion
Route::get('/', function () {
    $isInstalled = File::exists(storage_path('installed'));
    $hasRealKey = config('app.key') !== \App\Http\Controllers\InstallController::TEMP_KEY;

    // L'application est installée seulement si les DEUX conditions sont vraies
    if (!$isInstalled || !$hasRealKey) {
        return redirect()->route('install.database');
    }

    if (Auth::check()) {
        return redirect()->route('admin.index');
    }
    return redirect()->route('login');
});

// Routes avec le préfixe 'admin' (groupées)
Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('admin.index');

    Route::get('/config', [AdminConfigController::class, 'show'])->name('admin.config');
    Route::post('/config', [AdminConfigController::class, 'update'])->name('admin.config.update');
    Route::post('/config/azuriom', [AdminConfigController::class, 'addAzuriom'])->name('admin.config.azuriom.add');
    Route::post('/config/azuriom/{id}/edit', [AdminConfigController::class, 'editAzuriom'])->name('admin.config.azuriom.edit');
    Route::delete('/config/azuriom/{id}', [AdminConfigController::class, 'deleteAzuriom'])->name('admin.config.azuriom.delete');
    Route::post('/config/azuriom/{id}/primary', [AdminConfigController::class, 'setPrimaryAzuriom'])->name('admin.config.azuriom.primary');

    Route::get('/general', [AdminController::class, 'general'])->name('admin.general');
    Route::post('/general/update', [AdminController::class, 'updateGeneral'])->name('admin.general.update');
    Route::get('/security', [AdminSecurityController::class, 'show'])->name('admin.security');
    Route::post('/security/update', [AdminSecurityController::class, 'update'])->name('admin.security.update');
    Route::post('/maintenance/toggle', [AdminSecurityController::class, 'toggleMaintenance'])->name('admin.maintenance.toggle');

    Route::get('/server', [AdminServerController::class, 'show'])->name('admin.server');
    Route::post('/server/set-default', [AdminServerController::class, 'setDefaultServer'])->name('admin.server.set-default');
    Route::post('/server/sync', [AdminServerController::class, 'sync'])->name('admin.server.sync');
    Route::post('/server/add', [AdminServerController::class, 'addServer'])->name('admin.server.add');
    Route::post('/server/{serverId}/edit', [AdminServerController::class, 'editServer'])->name('admin.server.edit');
    Route::delete('/server/{serverId}', [AdminServerController::class, 'deleteServer'])->name('admin.server.delete');
    Route::post('/server/{serverId}/icon', [AdminServerController::class, 'updateIcon'])->name('admin.server.updateIcon');
    Route::delete('/server/{serverId}/icon', [AdminServerController::class, 'deleteIcon'])->name('admin.server.deleteIcon');

    Route::get('/ui', [AdminUIController::class, 'show'])->name('admin.ui');
    Route::post('/ui/update', [AdminUIController::class, 'update'])->name('admin.ui.update');

    Route::get('/whitelist', [AdminWhitelistController::class, 'index'])->name('admin.whitelist');
    Route::post('/whitelist', [AdminWhitelistController::class, 'store'])->name('admin.whitelist.store');
    Route::get('/whitelist/fetch-users', [AdminWhitelistController::class, 'fetchUsers'])->name('admin.whitelist.fetchUsers');
    Route::get('/whitelist/fetch-roles', [AdminWhitelistController::class, 'fetchRoles'])->name('admin.whitelist.fetchRoles');
    Route::delete('/whitelist/user/{id}', [AdminWhitelistController::class, 'destroyUser'])->name('admin.whitelist.destroyUser');
    Route::delete('/whitelist/role/{id}', [AdminWhitelistController::class, 'destroyRole'])->name('admin.whitelist.destroyRole');

    Route::get('/ignore', [AdminIgnoreController::class, 'index'])->name('admin.ignore');
    Route::post('/ignore', [AdminIgnoreController::class, 'store'])->name('admin.ignore.store');
    Route::delete('/ignore/folder/{id}', [AdminIgnoreController::class, 'destroyFolder'])->name('admin.ignore.destroyFolder');

    Route::get('/mods', [AdminModController::class, 'index'])->name('admin.mods');
    Route::post('/mods/add', [AdminModController::class, 'addOptionalMod'])->name('admin.mods.addOptional');
    Route::post('/mods/update', [AdminModController::class, 'updateOptionalMod'])->name('admin.mods.updateOptional');
    Route::post('/mods/delete/{id}', [AdminModController::class, 'deleteOptionalMod'])->name('admin.mods.delete');
    Route::get('/mods/{id}', [AdminModController::class, 'getOptionalModDetails'])->name('admin.mods.getOptionalModDetails');

    Route::get('/loader', [AdminLoaderController::class, 'index'])->name('admin.loader');
    Route::post('/loader/update', [AdminLoaderController::class, 'update'])->name('admin.loader.update');
    Route::get('/loader/builds/', [AdminLoaderController::class, 'getForgeBuilds'])->name('admin.loader.builds');
    Route::get('/loader/fabric-versions', [AdminLoaderController::class, 'getFabricVersions'])->name('admin.loader.fabric-versions');

    Route::get('/rpc', [AdminRpcController::class, 'show'])->name('admin.rpc');
    Route::post('/rpc/update', [AdminRpcController::class, 'update'])->name('admin.rpc.update');

    Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users');
    Route::get('/users/create', [AdminUserController::class, 'create'])->name('admin.users.create');
    Route::post('/users/add', [AdminUserController::class, 'add'])->name('admin.users.add');
    Route::delete('/users/delete/{id}', [AdminUserController::class, 'delete'])->name('admin.users.delete');
    Route::get('/users/edit/{id}', [AdminUserController::class, 'edit'])->name('admin.users.edit');
    Route::put('/users/update/{id}', [AdminUserController::class, 'update'])->name('admin.users.update');

    Route::get('/settings/export', [SettingsExportController::class, 'export'])->name('admin.settings.export');
    Route::post('/settings/import', [SettingsExportController::class, 'import'])->name('admin.settings.import');

    Route::get('/bg', [AdminBgController::class, 'index'])->name('admin.bg');
    Route::post('/bg/update', [AdminBgController::class, 'update'])->name('admin.bg.update');
    Route::delete('/bg/destroy/{role_id}', [AdminBgController::class, 'destroy'])->name('admin.bg.destroy');

    Route::get('/update', [UpdateController::class, 'index'])->name('admin.update');
    Route::post('/update', [UpdateController::class, 'update'])->name('admin.update.run');

    Route::get('/notifications', [AdminNotificationController::class, 'index'])->name('admin.notifications');
    Route::post('/notifications', [AdminNotificationController::class, 'store'])->name('admin.notifications.store');
    Route::patch('/notifications/{notification}/toggle', [AdminNotificationController::class, 'toggle'])->name('admin.notifications.toggle');
    Route::delete('/notifications/{notification}', [AdminNotificationController::class, 'destroy'])->name('admin.notifications.destroy');

    Route::get('/community-mods', [AdminCommunityModController::class, 'index'])->name('admin.community-mods');
    Route::post('/community-mods', [AdminCommunityModController::class, 'store'])->name('admin.community-mods.store');
    Route::put('/community-mods/{mod}', [AdminCommunityModController::class, 'update'])->name('admin.community-mods.update');
    Route::patch('/community-mods/{mod}/toggle', [AdminCommunityModController::class, 'toggle'])->name('admin.community-mods.toggle');
    Route::delete('/community-mods/{mod}', [AdminCommunityModController::class, 'destroy'])->name('admin.community-mods.destroy');

    Route::get('/audit', [AdminAuditController::class, 'index'])->name('admin.audit.index');

    Route::get('/stats', [StatsController::class, 'index'])->name('admin.stats');
});

// Routes sans le préfixe 'admin'

Route::get('/file-manager', function () {
    return view('admin.file-manager');
})->name('admin.file-manager')->middleware('auth');

Route::prefix('utils')->middleware(['throttle:120,1'])->group(function () {
    Route::get('/api', [ApiController::class, 'getOptions']);
    Route::get('/mods', [ModController::class, 'getMods']);
    Route::get('/notifications', [NotificationController::class, 'getNotifications']);
    Route::get('/servers-status', [ServerStatusController::class, 'getServersStatus']);
    Route::post('/telemetry', [TelemetryController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/leaderboards', [LeaderboardController::class, 'getLeaderboards']);
    Route::get('/factions', [FactionController::class, 'getFactions']);
    Route::get('/community-mods', [CommunityModController::class, 'getCommunityMods']);
});
Route::get('/data', [FileController::class, 'getFiles'])->middleware('throttle:120,1');
Route::get('/api/centralcorp/community-mods', [CommunityModController::class, 'getCommunityMods']);
Route::get('/api-schema.json', fn() => response()->json(['schemaVersion' => '1.0.0'], 200, [], JSON_UNESCAPED_SLASHES));

Route::get('lang/{locale}', [App\Http\Controllers\LanguageController::class, 'switch'])->name('lang.switch');
