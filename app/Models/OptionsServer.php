<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OptionsServer extends Model
{
    use HasFactory;

    public const DEFAULT_TEST_ROLES = [
        'Admin',
        'Administrateur',
        'Fondateur',
        'Développeur',
        'Dev',
        'Modérateur',
        'Modo',
        'Staff',
        'Testeur',
        'Beta',
        'Bêta-Testeur',
    ];

    protected $table = 'options_server';
    protected $fillable = [
        'server_id',
        'instance_slug',
        'channel',
        'server_name',
        'server_ip',
        'server_port',
        'icon',
        'icon_local',
        'type',
        'minecraft_version',
        'loader_type',
        'loader_build_version',
        'loader_activation',
        'data_folder',
        'theme_color',
        'allowed_roles',
        'test_roles',
        'test_ip',
        'test_port',
        'test_data_folder',
        'is_default'
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'loader_activation' => 'boolean',
    ];

    /**
     * Résout une instance à partir du slug envoyé par le launcher (?instance=).
     * Tolérant : matche d'abord instance_slug, puis server_id, puis le nom
     * slugifié — pour fonctionner même si l'admin n'a pas encore renseigné de slug.
     */
    public static function resolveInstance(?string $slug): ?self
    {
        if (!$slug) {
            return null;
        }

        $bySlug = static::where('instance_slug', $slug)->first();
        if ($bySlug) {
            return $bySlug;
        }

        $byId = static::where('server_id', $slug)->first();
        if ($byId) {
            return $byId;
        }

        return static::all()->first(function ($server) use ($slug) {
            return \Illuminate\Support\Str::slug($server->server_name) === \Illuminate\Support\Str::slug($slug);
        });
    }

    /**
     * Le sous-dossier modpack effectif de cette instance (data_folder ou slug).
     */
    public function getModpackFolderAttribute(): ?string
    {
        return $this->data_folder ?: $this->instance_slug;
    }

    /**
     * Le sous-dossier modpack pour le canal test (test_data_folder ou <slug>-test).
     */
    public function getTestModpackFolderAttribute(): string
    {
        if (!empty($this->test_data_folder)) {
            return $this->test_data_folder;
        }
        $base = $this->data_folder ?: $this->instance_slug;
        return $base ? ($base . '-test') : 'test';
    }

    /**
     * Liste des grades Azuriom autorisés sur le canal Stable (vide = tous les grades).
     */
    public function getAllowedRolesListAttribute(): array
    {
        return self::parseRolesList($this->allowed_roles, []);
    }

    /**
     * Liste des grades Azuriom autorisés sur le canal Test.
     */
    public function getTestRolesListAttribute(): array
    {
        return self::parseRolesList($this->test_roles, self::DEFAULT_TEST_ROLES);
    }

    public static function parseRolesList(?string $raw, array $default = []): array
    {
        if ($raw === null || trim($raw) === '') {
            return $default;
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('trim', $decoded), fn ($r) => $r !== ''));
        }
        return array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($r) => $r !== ''));
    }

    /**
     * Retourne l'URL de l'icône (locale prioritaire sur distante)
     */
    public function getIconUrlAttribute(): ?string
    {
        if ($this->icon_local) {
            return asset('storage/' . $this->icon_local);
        }

        if ($this->icon) {
            $options = OptionsGeneral::first();
            if ($options && $options->azuriom_url) {
                return rtrim($options->azuriom_url, '/') . '/storage/' . ltrim(str_replace('storage/', '', $this->icon), '/');
            }
        }

        return null;
    }
}
