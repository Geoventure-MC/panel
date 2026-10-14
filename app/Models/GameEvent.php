<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Événement de jeu piloté par le panel (ex. Halloween). Dates stockées en UTC. */
class GameEvent extends Model
{
    protected $table = 'game_events';
    protected $fillable = ['slug', 'name', 'enabled', 'opens_at', 'ends_at', 'settings', 'announce', 'notification_id'];
    protected $casts = [
        'enabled'  => 'boolean',
        'announce' => 'boolean',
        'opens_at' => 'datetime',
        'ends_at'  => 'datetime',
        'settings' => 'array',
    ];

    /** Réglages autorisés par événement : clé => [min, max]. `rewards` = 4 entiers (podium + participation). */
    public const SETTINGS = [
        'halloween' => [
            'mob_cap' => [0, 500], 'mob_local' => [0, 200],
            'boss_respawn_minutes' => [1, 1440], 'flight_minutes' => [0, 120],
        ],
    ];

    /** disabled | upcoming | open | ended */
    public function phase(): string
    {
        if (! $this->enabled) {
            return 'disabled';
        }
        $now = now();
        if ($this->opens_at && $now->lt($this->opens_at)) {
            return 'upcoming';
        }
        if ($this->ends_at && $now->gte($this->ends_at)) {
            return 'ended';
        }

        return 'open';
    }

    /** Réglages nettoyés (bornés, clés connues seulement). */
    public static function cleanSettings(string $slug, array $in): array
    {
        $out = [];
        foreach (self::SETTINGS[$slug] ?? [] as $k => [$min, $max]) {
            if (isset($in[$k]) && is_numeric($in[$k])) {
                $out[$k] = (int) max($min, min($max, (int) $in[$k]));
            }
        }
        if (isset($in['rewards']) && is_array($in['rewards']) && isset(self::SETTINGS[$slug])) {
            $r = array_values(array_map(fn ($v) => (int) max(0, min(1000000, (int) $v)), array_slice($in['rewards'], 0, 4)));
            if (count($r) === 4) {
                $out['rewards'] = $r;
            }
        }

        return $out;
    }

    /** Garde l'annonce du launcher alignée sur l'événement (une seule annonce liée). */
    public function syncNotification(): void
    {
        $n = $this->notification_id ? OptionsNotification::find($this->notification_id) : null;
        $show = $this->announce && in_array($this->phase(), ['upcoming', 'open'], true);
        if (! $show) {
            $n?->update(['active' => false]);

            return;
        }
        $tz = 'Europe/Paris';
        $fmt = fn ($d) => $d ? $d->copy()->setTimezone($tz)->format('d/m H\hi') : null;
        $msg = $this->phase() === 'open'
            ? "🎃 {$this->name} : l'événement est en cours" . ($this->ends_at ? " jusqu'au " . $fmt($this->ends_at) : '') . ' !'
            : "🎃 {$this->name} : ouverture le " . $fmt($this->opens_at) . ' !';
        $data = ['type' => 'event', 'message' => mb_substr($msg, 0, 500), 'active' => true, 'expires_at' => $this->ends_at];
        if ($n) {
            $n->update($data);
        } else {
            $n = OptionsNotification::create($data);
            $this->forceFill(['notification_id' => $n->id])->saveQuietly();
        }
    }

    public function toPublic(): array
    {
        return [
            'slug' => $this->slug, 'name' => $this->name, 'enabled' => (bool) $this->enabled, 'phase' => $this->phase(),
            'opensAt' => $this->opens_at ? $this->opens_at->getTimestamp() * 1000 : null,
            'endsAt' => $this->ends_at ? $this->ends_at->getTimestamp() * 1000 : null,
            'settings' => (object) ($this->settings ?? []),
        ];
    }
}
