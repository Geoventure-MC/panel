<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\OptionsGeneral;
use App\Models\OptionsUI;
use App\Models\OptionsServer;
use App\Models\OptionsRPC;
use App\Models\OptionsSecurity;
use App\Models\OptionsLoader;
use App\Models\OptionsIgnore;
use App\Models\OptionsWhitelist;
use App\Models\OptionsWhitelistRole;
use Illuminate\Support\Facades\Schema;

class SettingsExportController extends Controller
{
    /**
     * Tables autorisées à l'import/export. Toute clé hors de cette liste est
     * rejetée pour empêcher un fichier malveillant de truncate/insérer dans
     * des tables arbitraires (users, sessions, migrations…).
     */
    private const ALLOWED_TABLES = [
        'options_general',
        'options_ui',
        'options_server',
        'options_rpc',
        'options_security',
        'options_loader',
        'ignored_folders',
        'whitelist',
        'whitelist_roles',
        'mods',
        'community_mods',
    ];

    /**
     * Colonnes secrètes masquées à l'export.
     */
    private const SECRET_COLUMNS = [
        'azuriom_api_key',
        'discord_webhook_url',
    ];

    /**
     * Masque les valeurs secrètes (et toute colonne password/token/secret).
     */
    private function redactSecrets($rows)
    {
        return collect($rows)->map(function ($row) {
            $arr = (array) $row;
            foreach ($arr as $key => $value) {
                if ($this->isSecretColumn((string) $key)) {
                    $arr[$key] = '';
                }
            }
            return $arr;
        })->all();
    }

    private function isSecretColumn(string $key): bool
    {
        $lower = strtolower($key);

        return in_array($lower, self::SECRET_COLUMNS, true)
            || str_contains($lower, 'password')
            || str_contains($lower, 'token')
            || str_contains($lower, 'secret');
    }

    public function export()
    {
        $settings = [
            'version' => '1.0',
            'export_date' => now()->format('Y-m-d H:i:s'),
            'data' => [
                'options_general' => $this->redactSecrets(OptionsGeneral::all()),
                'options_ui' => OptionsUI::all(),
                'options_server' => OptionsServer::all(),
                'options_rpc' => $this->redactSecrets(OptionsRPC::all()),
                'options_security' => $this->redactSecrets(OptionsSecurity::all()),
                'options_loader' => OptionsLoader::all(),
                'ignored_folders' => OptionsIgnore::all(),
                'whitelist' => OptionsWhitelist::all(),
                'whitelist_roles' => OptionsWhitelistRole::all(),
            ]
        ];

        $json = json_encode($settings, JSON_PRETTY_PRINT);
        $filename = 'centralcorp_settings_' . date('Y-m-d_H-i-s') . '.centralcorp';

        return response($json)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function import(Request $request)
    {
        $request->validate([
            'settings_file' => 'required|file|mimes:centralcorp,json'
        ]);

        $json = file_get_contents($request->file('settings_file')->path());
        $settings = json_decode($json, true);

        if (!$settings || !isset($settings['data'])) {
            return back()->with('error', 'Le fichier .centralcorp est invalide ou corrompu.');
        }

        // Vérifier la version du fichier
        if (!isset($settings['version']) || $settings['version'] !== '1.0') {
            return back()->with('error', 'Version du fichier .centralcorp non supportée.');
        }

        DB::beginTransaction();
        try {
            foreach ($settings['data'] as $table => $data) {
                // N'autoriser que les tables de configuration connues.
                if (!in_array($table, self::ALLOWED_TABLES, true)) {
                    throw new \Exception("La table {$table} n'est pas autorisée à l'import.");
                }

                // Vérifier si la table existe
                if (!Schema::hasTable($table)) {
                    throw new \Exception("La table {$table} n'existe pas.");
                }

                // Secrets actuels (clé API, webhook…) : l'export les vide, l'import ne doit pas les effacer.
                $current = (array) (DB::table($table)->first() ?? []);

                // Vider la table existante. delete() et non truncate() : sur MySQL, TRUNCATE valide
                // implicitement la transaction et un échec plus loin laisserait un import à moitié fait.
                DB::table($table)->delete();

                // Insérer les nouvelles données
                foreach ($data as $row) {
                    // Supprimer les timestamps si présents
                    unset($row['created_at'], $row['updated_at']);
                    $row = (array) $row;
                    foreach ($row as $col => $val) {
                        if (($val === '' || $val === null) && $this->isSecretColumn((string) $col) && ! empty($current[$col])) {
                            $row[$col] = $current[$col];
                        }
                    }
                    DB::table($table)->insert($row);
                }
            }
            
            DB::commit();
            return back()->with('success', 'Les paramètres ont été importés avec succès depuis le fichier .centralcorp.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Une erreur est survenue lors de l\'importation : ' . $e->getMessage());
        }
    }
} 