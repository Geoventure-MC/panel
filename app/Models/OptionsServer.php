<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OptionsServer extends Model
{
    use HasFactory;

    protected $table = 'options_server';
    protected $fillable = [
        'server_id',
        'instance_slug',
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
