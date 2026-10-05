<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\WonderEdition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * GET /utils/wonder — concours de construction Wonder (public, sans secret).
 *
 * { current: {...}|null, past: [...] }. `current` = l'édition en cours (ou la
 * prochaine, ou la dernière si rien d'autre) ; le classement n'apparaît
 * qu'une fois les résultats publiés. Dates en epoch millisecondes.
 * ETag + Cache-Control 30 s + 304, cache serveur 30 s, jamais de 500.
 */
class WonderController extends Controller
{
    public function index(Request $request)
    {
        try {
            $payload = Cache::remember('geo_wonder', 30, fn () => $this->build());
        } catch (\Throwable $e) {
            Log::warning('WonderController@index: '.$e->getMessage());
            $payload = ['current' => null, 'past' => []];
        }

        $body = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $etag = '"'.md5($body).'"';
        $headers = ['Content-Type' => 'application/json', 'ETag' => $etag, 'Cache-Control' => 'public, max-age=30'];
        if (trim($request->header('If-None-Match', '')) === $etag) {
            return response('', 304, $headers);
        }

        return response($body, 200, $headers);
    }

    private function build(): array
    {
        $editions = WonderEdition::orderByDesc('opens_at')->orderByDesc('id')->limit(12)->get();
        $current = $editions->first(fn ($e) => in_array($e->phase(), ['open', 'closed'], true))
            ?? $editions->first(fn ($e) => $e->phase() === 'upcoming')
            ?? $editions->first();

        $past = $editions->filter(fn ($e) => $e->status === 'published' && (! $current || $e->id !== $current->id))
            ->take(8)->map(fn ($e) => $this->edition($e))->values()->all();

        return ['current' => $current ? $this->edition($current) : null, 'past' => $past];
    }

    private function edition(WonderEdition $e): array
    {
        $published = $e->status === 'published';
        [$wt, $wa, $wh] = $e->weights();
        $ranking = $published ? $e->ranking() : [];
        $rank = collect($ranking)->keyBy('team');

        return [
            'id'          => $e->id,
            'name'        => $e->name,
            'theme'       => $e->theme,
            'world'       => $e->world,
            'phase'       => $e->phase(),
            'opensAt'     => $e->opens_at ? $e->opens_at->getTimestamp() * 1000 : null,
            'closesAt'    => $e->closes_at ? $e->closes_at->getTimestamp() * 1000 : null,
            'publishedAt' => $e->published_at ? $e->published_at->getTimestamp() * 1000 : null,
            'teamSize'    => $e->team_size,
            'builders'    => $e->builders,
            'weights'     => ['technical' => round($wt, 1), 'aesthetic' => round($wa, 1), 'theme' => round($wh, 1)],
            'rewards'     => $e->rewards,
            'ranking'     => $ranking,
            'gallery'     => $e->teams()->orderBy('name')->get()->map(fn ($t) => [
                'team'    => $t->name,
                'color'   => $t->color,
                'rank'    => $rank[$t->name]['rank'] ?? null,
                'members' => array_values(array_merge((array) $t->builders, (array) $t->reserves)),
                'images'  => array_values((array) $t->images),
            ])->values()->all(),
        ];
    }
}
