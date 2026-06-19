<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\OptionsWhitelist;
use App\Models\OptionsWhitelistRole;
use Illuminate\Http\Request;
use App\Models\OptionsSecurity;
use App\Request\AzuriomApi;

class AdminWhitelistController extends Controller
{
    private $azuriomApi;

    public function __construct()
    {
        try {
            $this->azuriomApi = new AzuriomApi();
        } catch (\RuntimeException $e) {
            $this->azuriomApi = null;
        }
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $users = OptionsWhitelist::query()
            ->when($search !== '', fn ($q) => $q->where('users', 'like', '%' . $search . '%'))
            ->orderBy('users')
            ->paginate(30, ['*'], 'users_page')
            ->withQueryString();

        $roles = OptionsWhitelistRole::query()
            ->when($search !== '', fn ($q) => $q->where('role', 'like', '%' . $search . '%'))
            ->orderBy('role')
            ->paginate(30, ['*'], 'roles_page')
            ->withQueryString();

        $securityOptions = OptionsSecurity::first();
        $hasAzuriomApi = $this->azuriomApi !== null;

        // Historique : actions whitelist tirées du journal d'audit.
        $history = AuditLog::with('user')
            ->where('action', 'like', 'whitelist.%')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('admin.whitelist', compact('users', 'roles', 'securityOptions', 'hasAzuriomApi', 'search', 'history'));
    }

    /**
     * Fetch AJAX de tous les utilisateurs Azuriom (pour mise en cache côté client)
     */
    public function fetchUsers(Request $request)
    {
        if (!$this->azuriomApi) {
            return response()->json(['error' => __('messages.flash.azuriom_api_error')], 503);
        }

        $allUsers = $this->azuriomApi->getUsers();
        $whitelistedUsers = OptionsWhitelist::pluck('users')->toArray();

        // Filtrer et formater les résultats
        $results = collect($allUsers)
            ->filter(function ($user) use ($whitelistedUsers) {
                // Exclure les utilisateurs déjà whitelistés, bannis ou supprimés
                if (in_array($user['name'], $whitelistedUsers)) return false;
                if ($user['is_banned'] ?? false) return false;
                if (str_starts_with($user['name'], 'Deleted #')) return false;
                return true;
            })
            ->sortBy(function ($user) {
                // Admins en premier, puis par nom
                return [($user['role']['is_admin'] ?? false) ? 0 : 1, strtolower($user['name'])];
            })
            ->map(function ($user) {
                return [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'role' => $user['role']['name'] ?? 'Unknown',
                    'role_color' => $user['role']['color'] ?? '#666',
                    'is_admin' => $user['role']['is_admin'] ?? false,
                ];
            })
            ->values();

        return response()->json($results);
    }

    /**
     * Fetch AJAX de tous les rôles Azuriom (pour mise en cache côté client)
     */
    public function fetchRoles(Request $request)
    {
        if (!$this->azuriomApi) {
            return response()->json(['error' => __('messages.flash.azuriom_api_error')], 503);
        }

        $allRoles = $this->azuriomApi->getRoles();
        $whitelistedRoles = OptionsWhitelistRole::pluck('role')->toArray();

        // Filtrer et formater les résultats
        $results = collect($allRoles)
            ->filter(function ($role) use ($whitelistedRoles) {
                // Exclure les rôles déjà whitelistés
                return !in_array($role['name'], $whitelistedRoles);
            })
            ->sortBy(function ($role) {
                // Admins en premier, puis par nom
                return [($role['is_admin'] ?? false) ? 0 : 1, strtolower($role['name'])];
            })
            ->map(function ($role) {
                return [
                    'id' => $role['id'],
                    'name' => $role['name'],
                    'color' => $role['color'] ?? '#666',
                    'power' => $role['power'] ?? 0,
                    'is_admin' => $role['is_admin'] ?? false,
                ];
            })
            ->values();

        return response()->json($results);
    }

    public function store(Request $request)
    {
        $whitelistActivation = $request->input('whitelist');

        $securityOptions = OptionsSecurity::first();

        if ($securityOptions) {
            $securityOptions->whitelist = $whitelistActivation;
            $securityOptions->save();
        }

        // Gérer les utilisateurs sélectionnés
        if ($request->input('whitelist_users')) {
            foreach ($request->input('whitelist_users') as $username) {
                if (trim($username) !== '') {
                    $entry = OptionsWhitelist::firstOrCreate(['users' => trim($username)]);
                    if ($entry->wasRecentlyCreated) {
                        AuditLog::record('whitelist.user.add', $entry, ['users' => $entry->users]);
                    }
                }
            }
        }

        // Gérer les rôles d'Azuriom sélectionnés
        if ($request->input('azuriom_roles')) {
            foreach ($request->input('azuriom_roles') as $role) {
                if (trim($role) !== '') {
                    $entry = OptionsWhitelistRole::firstOrCreate(['role' => trim($role)]);
                    if ($entry->wasRecentlyCreated) {
                        AuditLog::record('whitelist.role.add', $entry, ['role' => $entry->role]);
                    }
                }
            }
        }

        AuditLog::record('whitelist.update', $securityOptions);

        return redirect()->route('admin.whitelist')->with('success', __('messages.flash.whitelist_updated'));
    }

    public function destroyUser($id)
    {
        $entry = OptionsWhitelist::findOrFail($id);
        $entry->delete();
        AuditLog::record('whitelist.user.delete', $entry, ['users' => $entry->users]);
        return redirect()->route('admin.whitelist')->with('success', __('messages.flash.whitelist_user_deleted'));
    }

    public function destroyRole($id)
    {
        $entry = OptionsWhitelistRole::findOrFail($id);
        $entry->delete();
        AuditLog::record('whitelist.role.delete', $entry, ['role' => $entry->role]);
        return redirect()->route('admin.whitelist')->with('success', __('messages.flash.whitelist_role_deleted'));
    }
}
