<?php

namespace App\Http\Controllers;

use App\Models\OptionsGeneral;
use App\Models\OptionsAzuriom;
use App\Models\OptionsServer;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class AdminConfigController extends Controller
{
    public function show()
    {
        $options = OptionsGeneral::first();
        $azuriomSites = OptionsAzuriom::with('server')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
        $servers = OptionsServer::orderBy('server_name')->get();

        return view('admin.config', compact('options', 'azuriomSites', 'servers'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'app_name' => 'required|string|max:255',
        ]);

        // Vérifier si le nom de l'application a changé
        $currentAppName = config('app.name');
        if ($validated['app_name'] !== $currentAppName) {
            // Mettre à jour le nom de l'application dans le fichier .env
            $envPath = base_path('.env');
            $envContent = File::get($envPath);
            $newEnvContent = preg_replace(
                '/^APP_NAME=.*/m',
                'APP_NAME="' . $validated['app_name'] . '"',
                $envContent
            );
            File::put($envPath, $newEnvContent);

            // Vider le cache de configuration pour prendre en compte le changement
            \Artisan::call('config:clear');
        }

        return redirect()->route('admin.config')->with('success', __('messages.flash.config_updated'));
    }

    public function addAzuriom(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'url'        => 'required|url',
            'api_key'    => 'nullable|string',
            'server_id'  => 'nullable|integer',
            'is_primary' => 'boolean',
        ]);

        $isPrimary = (bool) ($validated['is_primary'] ?? false);

        if ($isPrimary) {
            OptionsAzuriom::where('is_primary', true)->update(['is_primary' => false]);
        }

        $site = OptionsAzuriom::create([
            'name'       => $validated['name'],
            'url'        => $validated['url'],
            'api_key'    => $validated['api_key'] ?? null,
            'server_id'  => $validated['server_id'] ?? null,
            'is_primary' => $isPrimary,
        ]);

        AuditLog::record('azuriom_site_created', $site, [
            'name' => $site->name,
            'url'  => $site->url,
        ]);

        return redirect()->route('admin.config')
            ->with('success', __('messages.flash.azuriom_added', ['name' => $site->name]));
    }

    public function editAzuriom(Request $request, int $id)
    {
        $site = OptionsAzuriom::findOrFail($id);

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'url'        => 'required|url',
            'api_key'    => 'nullable|string',
            'server_id'  => 'nullable|integer',
            'is_primary' => 'boolean',
        ]);

        $isPrimary = (bool) ($validated['is_primary'] ?? false);

        if ($isPrimary) {
            OptionsAzuriom::where('is_primary', true)
                ->where('id', '!=', $id)
                ->update(['is_primary' => false]);
        }

        $site->update([
            'name'       => $validated['name'],
            'url'        => $validated['url'],
            'api_key'    => $validated['api_key'] ?? null,
            'server_id'  => $validated['server_id'] ?? null,
            'is_primary' => $isPrimary,
        ]);

        AuditLog::record('azuriom_site_updated', $site, [
            'name' => $site->name,
            'url'  => $site->url,
        ]);

        return redirect()->route('admin.config')
            ->with('success', __('messages.flash.azuriom_edited', ['name' => $site->name]));
    }

    public function deleteAzuriom(int $id)
    {
        $site = OptionsAzuriom::findOrFail($id);
        $name = $site->name;

        AuditLog::record('azuriom_site_deleted', $site, ['name' => $name]);
        $site->delete();

        return redirect()->route('admin.config')
            ->with('success', __('messages.flash.azuriom_deleted', ['name' => $name]));
    }

    public function setPrimaryAzuriom(int $id)
    {
        $site = OptionsAzuriom::findOrFail($id);

        OptionsAzuriom::where('is_primary', true)->update(['is_primary' => false]);
        $site->update(['is_primary' => true]);

        AuditLog::record('azuriom_site_set_primary', $site, ['name' => $site->name]);

        return redirect()->route('admin.config')
            ->with('success', __('messages.flash.azuriom_set_primary', ['name' => $site->name]));
    }
}
