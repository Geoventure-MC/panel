<?php

namespace App\Http\Controllers;

use App\Models\OptionsServer;
use App\Models\OptionsGeneral;
use App\Models\AuditLog;
use App\Request\AzuriomApi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminServerController extends Controller
{
    private $azuriomApi;

    public function __construct()
    {
        try {
            $this->azuriomApi = new AzuriomApi();
        } catch (\RuntimeException $e) {
        }
    }

    public function show()
    {
        $options = OptionsGeneral::first();
        $error = null;

        $servers = OptionsServer::all();

        $defaultServers = [];
        foreach ($servers as $server) {
            $defaultServers[$server->server_id] = $server->is_default;
        }

        $serversArray = $servers->map(function($server) {
            return [
                'id' => $server->server_id,
                'instance_slug' => $server->instance_slug,
                'name' => $server->server_name,
                'address' => $server->server_ip,
                'port' => $server->server_port,
                'type' => $server->type ?? 'minecraft',
                'icon' => $server->icon,
                'icon_local' => $server->icon_local,
                'icon_url' => $server->icon_url,
                'minecraft_version' => $server->minecraft_version,
                'loader_type' => $server->loader_type,
                'loader_build_version' => $server->loader_build_version,
                'loader_activation' => $server->loader_activation,
                'data_folder' => $server->data_folder,
                'theme_color' => $server->theme_color,

            ];
        })->toArray();

        return view('admin.server', [
            'servers' => $serversArray,
            'options' => $options,
            'error' => $error,
            'defaultServers' => $defaultServers
        ]);
    }

    public function sync()
    {
        $options = OptionsGeneral::first();

        if (!$options || !$options->azuriom_url) {
            return redirect()->route('admin.server')->with('error', __('messages.server.config_error'));
        }

        try {
            if (!$this->azuriomApi) {
                $this->azuriomApi = new AzuriomApi();
            }

            $serversResponse = $this->azuriomApi->getServers();
            if (!$serversResponse->successful()) {
                throw new \RuntimeException('Impossible de contacter l\'API Azuriom');
            }

            $servers = $serversResponse->json();
            $syncedCount = 0;
            $isFirstServer = true;
            $hasDefaultServer = OptionsServer::where('is_default', true)->exists();

            foreach ($servers as $server) {
                $iconPath = $server['icon'] ?? null;
                if ($iconPath) {
                    $iconPath = ltrim($iconPath, '/');
                    if (str_starts_with($iconPath, 'storage/')) {
                        $iconPath = substr($iconPath, 8);
                    }
                }

                $serverModel = OptionsServer::updateOrCreate(
                    ['server_id' => $server['id']],
                    [
                        'server_name' => $server['name'],
                        'server_ip' => $server['address'],
                        'server_port' => (string)$server['port'],
                        'icon' => $iconPath,
                        'type' => $server['type']
                    ]
                );

                if (!$hasDefaultServer && $isFirstServer) {
                    $serverModel->is_default = true;
                    $serverModel->save();
                    $hasDefaultServer = true;
                }

                $syncedCount++;
                $isFirstServer = false;
            }

            return redirect()->route('admin.server')->with('success', __('messages.flash.server_sync_success', ['count' => $syncedCount]));

        } catch (\RuntimeException $e) {
            return redirect()->route('admin.server')->with('error', __('messages.flash.server_sync_error') . ' ' . $e->getMessage());
        }
    }

    public function setDefaultServer(Request $request)
    {
        $request->validate([
            'server_id' => 'required|integer'
        ]);

        // Mettre à jour tous les serveurs pour désélectionner le serveur par défaut
        OptionsServer::where('is_default', true)->update(['is_default' => false]);

        // Mettre à jour le serveur sélectionné comme serveur par défaut
        OptionsServer::where('is_default', true)->update(['is_default' => false]);
        $server = OptionsServer::where('server_id', $request->server_id)->first();
        if ($server) {
            $server->is_default = true;
            $server->save();

            return redirect()->route('admin.server')->with('success', __('messages.flash.server_set_default', ['name' => $server->server_name]));
        }

        \Log::error('Serveur non trouvé', ['server_id' => $request->server_id]);
        return redirect()->route('admin.server')->with('error', __('messages.flash.server_not_found'));
    }

    public function addServer(Request $request)
    {
        $validated = $request->validate([
            'server_name'    => 'required|string|max:255',
            'server_ip'      => 'required|string|max:255',
            'server_port'    => 'required|string|max:255',
            'type'           => 'nullable|string|max:255',
            'icon'           => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'instance_slug'  => 'nullable|string|max:64|regex:/^[a-z0-9_-]+$/',
            'server_port'    => 'required|integer|min:1|max:65535',
            'type'           => 'nullable|string|max:255',
            'icon'           => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'instance_slug'  => 'nullable|string|max:64|regex:/^[a-z0-9_-]+$/|unique:options_server,instance_slug',
            'minecraft_version'    => 'nullable|string|max:32',
            'loader_type'          => 'nullable|string|max:32',
            'loader_build_version' => 'nullable|string|max:64',
            'loader_activation'    => 'nullable|boolean',
            'data_folder'          => 'nullable|string|max:64|regex:/^[a-z0-9_-]+$/',
            'theme_color'          => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',

        ]);

        $nextId = (int) (OptionsServer::max('server_id') ?? 0) + 1;
        if ($nextId < 1000) {
            $nextId = 1000;
        }

        $iconPath = null;
        if ($request->hasFile('icon')) {
            $iconPath = $request->file('icon')->store('server_icons', 'public');
        }

        $isFirst = !OptionsServer::exists();

        $server = OptionsServer::create([
            'server_id'            => $nextId,
            'instance_slug'        => $validated['instance_slug'] ?? null,
            'server_name'          => $validated['server_name'],
            'server_ip'            => $validated['server_ip'],
            'server_port'          => $validated['server_port'],
            'type'                 => $validated['type'] ?? 'minecraft',
            'icon_local'           => $iconPath,
            'minecraft_version'    => $validated['minecraft_version'] ?? null,
            'loader_type'          => $validated['loader_type'] ?? null,
            'loader_build_version' => $validated['loader_build_version'] ?? null,
            'loader_activation'    => $request->has('loader_activation') ? (bool) $request->input('loader_activation') : null,
            'data_folder'          => $validated['data_folder'] ?? null,
            'theme_color'          => $validated['theme_color'] ?? null,
            'is_default'           => $isFirst, // premier serveur ajouté = défaut
            'is_default'           => $isFirst,
        ]);

        AuditLog::record('server.add', $server, [
            'name' => $server->server_name,
            'ip'   => $server->server_ip,
            'port' => $server->server_port,
        ]);

        return redirect()->route('admin.server')->with('success', __('messages.flash.server_added', ['name' => $server->server_name]));
    }

    public function editServer(Request $request, $serverId)
    {
        $validated = $request->validate([
            'server_name'    => 'required|string|max:255',
            'server_ip'      => 'required|string|max:255',
            'server_port'    => 'required|string|max:255',
            'type'           => 'nullable|string|max:255',
            'instance_slug'  => 'nullable|string|max:64|regex:/^[a-z0-9_-]+$/',
            'server_port'    => 'required|integer|min:1|max:65535',
            'type'           => 'nullable|string|max:255',
            'instance_slug'  => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/', Rule::unique('options_server', 'instance_slug')->ignore($serverId, 'server_id')],
            'minecraft_version'    => 'nullable|string|max:32',
            'loader_type'          => 'nullable|string|max:32',
            'loader_build_version' => 'nullable|string|max:64',
            'loader_activation'    => 'nullable|boolean',
            'data_folder'          => 'nullable|string|max:64|regex:/^[a-z0-9_-]+$/',
            'theme_color'          => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',

        ]);

        $server = OptionsServer::where('server_id', $serverId)->first();
        if (!$server) {
            return redirect()->route('admin.server')->with('error', __('messages.flash.server_not_found'));
        }

        $server->update([
            'server_name'          => $validated['server_name'],
            'server_ip'            => $validated['server_ip'],
            'server_port'          => $validated['server_port'],
            'type'                 => $validated['type'] ?? $server->type ?? 'minecraft',
            'instance_slug'        => $validated['instance_slug'] ?? null,
            'minecraft_version'    => $validated['minecraft_version'] ?? null,
            'loader_type'          => $validated['loader_type'] ?? null,
            'loader_build_version' => $validated['loader_build_version'] ?? null,
            'loader_activation'    => $request->has('loader_activation') ? (bool) $request->input('loader_activation') : null,
            'data_folder'          => $validated['data_folder'] ?? null,
            'theme_color'          => $validated['theme_color'] ?? null,

        ]);

        AuditLog::record('server.edit', $server, [
            'name' => $server->server_name,
            'ip'   => $server->server_ip,
            'port' => $server->server_port,
        ]);

        return redirect()->route('admin.server')->with('success', __('messages.flash.server_edited', ['name' => $server->server_name]));
    }

    public function deleteServer($serverId)
    {
        $server = OptionsServer::where('server_id', $serverId)->first();
        if (!$server) {
            return redirect()->route('admin.server')->with('error', __('messages.flash.server_not_found'));
        }

        $wasDefault = (bool) $server->is_default;
        $name = $server->server_name;

        if ($server->icon_local) {
            \Storage::disk('public')->delete($server->icon_local);
        }

        AuditLog::record('server.delete', $server, ['name' => $name]);
        $server->delete();

        if ($wasDefault) {
            $fallback = OptionsServer::first();
            if ($fallback) {
                $fallback->is_default = true;
                $fallback->save();
            }
        }

        return redirect()->route('admin.server')->with('success', __('messages.flash.server_deleted', ['name' => $name]));
    }

    public function updateIcon(Request $request, $serverId)
    {
        $request->validate([
            'icon' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $server = OptionsServer::where('server_id', $serverId)->first();

        if (!$server) {
            return redirect()->route('admin.server')->with('error', __('messages.flash.server_not_found'));
        }

        if ($server->icon_local) {
            \Storage::disk('public')->delete($server->icon_local);
        }

        $path = $request->file('icon')->store('server_icons', 'public');
        $server->icon_local = $path;
        $server->save();

        AuditLog::record('server.icon.update', $server, ['name' => $server->server_name, 'path' => $path]);

        return redirect()->route('admin.server')->with('success', __('messages.flash.server_icon_updated', ['name' => $server->server_name]));
    }

    public function deleteIcon($serverId)
    {
        $server = OptionsServer::where('server_id', $serverId)->first();

        if (!$server) {
            return redirect()->route('admin.server')->with('error', __('messages.flash.server_not_found'));
        }

        if ($server->icon_local) {
            \Storage::disk('public')->delete($server->icon_local);
            $server->icon_local = null;
            $server->save();

            AuditLog::record('server.icon.delete', $server, ['name' => $server->server_name]);
        }

        return redirect()->route('admin.server')->with('success', __('messages.flash.server_icon_deleted', ['name' => $server->server_name]));
    }
}
