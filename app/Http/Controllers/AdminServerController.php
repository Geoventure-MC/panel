<?php

namespace App\Http\Controllers;

use App\Models\OptionsServer;
use App\Models\OptionsGeneral;
use App\Models\AuditLog;
use App\Request\AzuriomApi;
use Illuminate\Http\Request;

class AdminServerController extends Controller
{
    private $azuriomApi;

    public function __construct()
    {
        try {
            $this->azuriomApi = new AzuriomApi();
        } catch (\RuntimeException $e) {
            // On ne fait rien ici, on gérera l'erreur dans les méthodes
        }
    }

    /**
     * Affiche la page serveur depuis la base de données (rapide)
     */
    public function show()
    {
        $options = OptionsGeneral::first();
        $error = null;

        // Charger les serveurs depuis la BDD uniquement (pas d'appel API)
        $servers = OptionsServer::all();

        // Construire le tableau des serveurs par défaut
        $defaultServers = [];
        foreach ($servers as $server) {
            $defaultServers[$server->server_id] = $server->is_default;
        }

        // Transformer en format compatible avec la vue
        $serversArray = $servers->map(function($server) {
            return [
                'id' => $server->server_id,
                'name' => $server->server_name,
                'address' => $server->server_ip,
                'port' => $server->server_port,
                'type' => $server->type ?? 'minecraft',
                'icon' => $server->icon,
                'icon_local' => $server->icon_local,
                'icon_url' => $server->icon_url,
            ];
        })->toArray();

        return view('admin.server', [
            'servers' => $serversArray,
            'options' => $options,
            'error' => $error,
            'defaultServers' => $defaultServers
        ]);
    }

    /**
     * Synchronise les serveurs depuis l'API Azuriom (appelé manuellement)
     */
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
                // Nettoyer le chemin de l'icône (enlever /storage/ au début si présent)
                $iconPath = $server['icon'] ?? null;
                if ($iconPath) {
                    $iconPath = ltrim($iconPath, '/');
                    if (str_starts_with($iconPath, 'storage/')) {
                        $iconPath = substr($iconPath, 8); // Enlever "storage/"
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

    public function update(Request $request)
    {
        $request->validate([
            'server_name' => 'required|string|max:255',
            'server_ip' => 'required|string|max:255',
            'server_port' => 'required|string|max:255',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Validation de l'icône
        ]);

        $serverOptions = OptionsServer::first();

        // Gestion de l'upload de l'icône
        if ($request->hasFile('icon')) {
            // Supprimer l'ancienne icône si elle existe
            if ($serverOptions->icon) {
                \Storage::disk('uploads')->delete($serverOptions->icon);
            }

            // Enregistrer la nouvelle icône
            $path = $request->file('icon')->store('server_icon', 'uploads');
            // Stocker seulement le chemin relatif dans la base de données
            $serverOptions->icon = $path;
        }

        // Mettre à jour les autres options du serveur
        $serverOptions->update($request->except('icon')); // Ignorer l'icône ici, car elle a déjà été mise à jour

        return redirect()->route('admin.server')->with('success', __('messages.flash.server_updated'));
    }

    public function setDefaultServer(Request $request)
    {
        $request->validate([
            'server_id' => 'required|integer'
        ]);

        \Log::info('Tentative de définition du serveur par défaut', ['server_id' => $request->server_id]);

        // Mettre à jour tous les serveurs pour désélectionner le serveur par défaut
        $updatedCount = OptionsServer::where('is_default', true)->update(['is_default' => false]);
        \Log::info('Serveurs désélectionnés', ['count' => $updatedCount]);

        // Mettre à jour le serveur sélectionné comme serveur par défaut
        $server = OptionsServer::where('server_id', $request->server_id)->first();
        if ($server) {
            $server->is_default = true;
            $saved = $server->save();

            \Log::info('Serveur par défaut mis à jour', [
                'server_id' => $request->server_id,
                'server_name' => $server->server_name,
                'saved' => $saved,
                'is_default' => $server->is_default
            ]);

            // Vérification supplémentaire
            $verification = OptionsServer::where('server_id', $request->server_id)->first();
            \Log::info('Vérification après sauvegarde', [
                'server_id' => $verification->server_id,
                'is_default' => $verification->is_default
            ]);

            return redirect()->route('admin.server')->with('success', __('messages.flash.server_set_default', ['name' => $server->server_name]));
        }

        \Log::error('Serveur non trouvé', ['server_id' => $request->server_id]);
        return redirect()->route('admin.server')->with('error', __('messages.flash.server_not_found'));
    }

    /**
     * Ajoute manuellement un serveur (sans passer par la synchro Azuriom).
     * Permet d'avoir plusieurs serveurs dans un seul panel pour le launcher.
     */
    public function addServer(Request $request)
    {
        $validated = $request->validate([
            'server_name' => 'required|string|max:255',
            'server_ip'   => 'required|string|max:255',
            'server_port' => 'required|string|max:255',
            'type'        => 'nullable|string|max:255',
            'icon'        => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        // Génère un server_id unique (au-dessus des id Azuriom pour éviter les collisions)
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
            'server_id'   => $nextId,
            'server_name' => $validated['server_name'],
            'server_ip'   => $validated['server_ip'],
            'server_port' => $validated['server_port'],
            'type'        => $validated['type'] ?? 'minecraft',
            'icon_local'  => $iconPath,
            'is_default'  => $isFirst, // premier serveur ajouté = défaut
        ]);

        AuditLog::record('server.add', $server, [
            'name' => $server->server_name,
            'ip'   => $server->server_ip,
            'port' => $server->server_port,
        ]);

        return redirect()->route('admin.server')->with('success', __('messages.flash.server_added', ['name' => $server->server_name]));
    }

    /**
     * Modifie un serveur existant.
     */
    public function editServer(Request $request, $serverId)
    {
        $validated = $request->validate([
            'server_name' => 'required|string|max:255',
            'server_ip'   => 'required|string|max:255',
            'server_port' => 'required|string|max:255',
            'type'        => 'nullable|string|max:255',
        ]);

        $server = OptionsServer::where('server_id', $serverId)->first();
        if (!$server) {
            return redirect()->route('admin.server')->with('error', __('messages.flash.server_not_found'));
        }

        $server->update([
            'server_name' => $validated['server_name'],
            'server_ip'   => $validated['server_ip'],
            'server_port' => $validated['server_port'],
            'type'        => $validated['type'] ?? $server->type ?? 'minecraft',
        ]);

        AuditLog::record('server.edit', $server, [
            'name' => $server->server_name,
            'ip'   => $server->server_ip,
            'port' => $server->server_port,
        ]);

        return redirect()->route('admin.server')->with('success', __('messages.flash.server_edited', ['name' => $server->server_name]));
    }

    /**
     * Supprime un serveur.
     */
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

        // Si on a supprimé le serveur par défaut, en désigner un autre automatiquement
        if ($wasDefault) {
            $fallback = OptionsServer::first();
            if ($fallback) {
                $fallback->is_default = true;
                $fallback->save();
            }
        }

        return redirect()->route('admin.server')->with('success', __('messages.flash.server_deleted', ['name' => $name]));
    }

    /**
     * Met à jour l'icône d'un serveur spécifique
     */
    public function updateIcon(Request $request, $serverId)
    {
        $request->validate([
            'icon' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $server = OptionsServer::where('server_id', $serverId)->first();

        if (!$server) {
            return redirect()->route('admin.server')->with('error', __('messages.flash.server_not_found'));
        }

        // Supprimer l'ancienne icône locale si elle existe
        if ($server->icon_local) {
            \Storage::disk('public')->delete($server->icon_local);
        }

        // Enregistrer la nouvelle icône
        $path = $request->file('icon')->store('server_icons', 'public');
        $server->icon_local = $path;
        $server->save();

        return redirect()->route('admin.server')->with('success', __('messages.flash.server_icon_updated', ['name' => $server->server_name]));
    }

    /**
     * Supprime l'icône locale d'un serveur
     */
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
        }

        return redirect()->route('admin.server')->with('success', __('messages.flash.server_icon_deleted', ['name' => $server->server_name]));
    }
}
