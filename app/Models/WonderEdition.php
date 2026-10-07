<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WonderEdition extends Model
{
    /** /utils/wonder est mis en cache 30 s : toute modification admin l'invalide aussitôt. */
    protected static function booted(): void
    {
        $forget = static fn () => \Illuminate\Support\Facades\Cache::forget('geo_wonder');
        static::saved($forget);
        static::deleted($forget);
    }

    protected $table = 'wonder_editions';
    protected $fillable = [
        'name', 'theme', 'world', 'opens_at', 'closes_at', 'team_size', 'builders',
        'weight_technical', 'weight_aesthetic', 'weight_theme', 'rewards', 'status', 'published_at',
    ];
    protected $casts = [
        'opens_at'     => 'datetime',
        'closes_at'    => 'datetime',
        'published_at' => 'datetime',
    ];

    public function teams(): HasMany
    {
        return $this->hasMany(WonderTeam::class);
    }

    /** upcoming | open | closed | published */
    public function phase(): string
    {
        if ($this->status === 'published') {
            return 'published';
        }
        $now = now();
        if ($this->opens_at && $now->lt($this->opens_at)) {
            return 'upcoming';
        }
        if ($this->closes_at && $now->gte($this->closes_at)) {
            return 'closed';
        }

        return $this->opens_at ? 'open' : 'upcoming';
    }

    /** Pondérations normalisées (somme = 100) ; 40/30/30 si tout est à zéro. */
    public function weights(): array
    {
        $w = [(int) $this->weight_technical, (int) $this->weight_aesthetic, (int) $this->weight_theme];
        $sum = array_sum($w);
        if ($sum <= 0) {
            return [40.0, 30.0, 30.0];
        }

        return array_map(fn ($x) => $x * 100 / $sum, $w);
    }

    /**
     * Classement : moyenne des jurés par volet (0-10), total sur 100 pondéré.
     * Les équipes sans note sont exclues. Égalité : volet technique puis nom.
     */
    public function ranking(): array
    {
        [$wt, $wa, $wh] = $this->weights();
        $rows = [];
        foreach ($this->teams()->with('scores')->get() as $team) {
            if ($team->scores->isEmpty()) {
                continue;
            }
            $t = (float) $team->scores->avg('technical');
            $a = (float) $team->scores->avg('aesthetic');
            $h = (float) $team->scores->avg('theme');
            $rows[] = [
                'team'      => $team->name,
                'color'     => $team->color,
                'technical' => round($t, 2),
                'aesthetic' => round($a, 2),
                'theme'     => round($h, 2),
                'total'     => round(($t * $wt + $a * $wa + $h * $wh) / 10, 2),
                'jurors'    => $team->scores->count(),
            ];
        }
        usort($rows, fn ($x, $y) => [$y['total'], $y['technical'], $x['team']] <=> [$x['total'], $x['technical'], $y['team']]);
        foreach ($rows as $i => &$r) {
            $r['rank'] = $i + 1;
        }

        return $rows;
    }
}
