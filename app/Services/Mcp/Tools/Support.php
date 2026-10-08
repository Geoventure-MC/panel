<?php

namespace App\Services\Mcp\Tools;

use App\Services\Mcp\McpSanitizer;

/** Briques communes aux outils : pagination, schémas de propriétés fréquents. */
class Support
{
    public const PAGE_PROPS = [
        'page'  => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10000, 'description' => 'Numéro de page (défaut 1).'],
        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'description' => 'Éléments par page (défaut 20, max 50).'],
    ];

    public const PAGE_RULES = [
        'page'  => 'sometimes|integer|min:1|max:10000',
        'limit' => 'sometimes|integer|min:1|max:50',
    ];

    /** Pagine un constructeur Eloquent/Query et applique $map à chaque ligne. */
    public static function paginate($query, array $args, callable $map): array
    {
        $limit = (int) ($args['limit'] ?? 20);
        $page = (int) ($args['page'] ?? 1);
        $total = (clone $query)->count();
        $items = $query->forPage($page, $limit)->get()->map($map)->values()->all();

        return [
            'items'    => $items,
            'page'     => $page,
            'limit'    => $limit,
            'total'    => $total,
            'has_more' => $page * $limit < $total,
        ];
    }

    public static function iso($date): ?string
    {
        return $date ? \Illuminate\Support\Carbon::parse($date)->toIso8601String() : null;
    }

    /** Masque une valeur secrète (renvoie null si vide, « •••• » sinon). */
    public static function mask(mixed $v): mixed
    {
        return ($v === null || $v === '') ? null : McpSanitizer::MASK;
    }
}
