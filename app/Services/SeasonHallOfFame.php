<?php

namespace App\Services;

/**
 * Hall of fame des saisons : historique des champions et records, calculés à partir
 * des saisons terminées. Même forme que SeasonHallOfFame du plugin (additif pour le
 * launcher). Pur : aucune dépendance à la base, testable seul.
 */
class SeasonHallOfFame
{
    /**
     * @param iterable $seasons saisons terminées (objets/tableaux : external_id, winner_name,
     *                          winner_score, podium[] optionnel avec rank 2 pour la marge)
     */
    public static function compute(iterable $seasons): array
    {
        $rows = [];
        foreach ($seasons as $s) {
            $s = (array) (is_object($s) && method_exists($s, 'toArray') ? $s->toArray() : $s);
            $id = (string) ($s['external_id'] ?? '');
            $name = trim((string) ($s['winner_name'] ?? ''));
            if ($id === '' || $name === '') {
                continue;
            }
            $podium = is_array($s['podium'] ?? null) ? $s['podium'] : [];
            $second = null;
            foreach ($podium as $p) {
                if ((int) ($p['rank'] ?? 0) === 2) {
                    $second = ['name' => (string) ($p['name'] ?? ''), 'score' => (int) ($p['score'] ?? 0)];
                }
            }
            $rows[$id] = [
                'season' => $id,
                'name'   => $name,
                'score'  => (int) ($s['winner_score'] ?? 0),
                'second' => $second,
            ];
        }
        ksort($rows, SORT_STRING); // ids ISO : ordre chronologique

        $titles = [];
        $best = [];
        $history = [];
        $topScore = null;
        $margin = null;
        $prev = null;
        $streak = 0;
        $bestStreak = 0;
        $streakName = null;

        foreach ($rows as $r) {
            $titles[$r['name']][] = $r['season'];
            $best[$r['name']] = max($best[$r['name']] ?? 0, $r['score']);
            if ($topScore === null || $r['score'] > $topScore['score']) {
                $topScore = $r;
            }
            $streak = $r['name'] === $prev ? $streak + 1 : 1;
            $prev = $r['name'];
            if ($streak > $bestStreak) {
                $bestStreak = $streak;
                $streakName = $r['name'];
            }
            if ($r['second'] !== null) {
                $m = $r['score'] - $r['second']['score'];
                if ($margin === null || $m > $margin['margin']) {
                    $margin = ['name' => $r['name'], 'margin' => $m, 'season' => $r['season']];
                }
            }
            $h = ['season' => $r['season'], 'champion' => $r['name'], 'score' => $r['score']];
            if ($r['second'] !== null && $r['second']['name'] !== '') {
                $h['runnerUp'] = $r['second']['name'];
            }
            array_unshift($history, $h); // plus récent d'abord
        }

        $champions = [];
        foreach ($titles as $name => $seasonsWon) {
            $champions[] = [
                'name'      => (string) $name,
                'titles'    => count($seasonsWon),
                'seasons'   => $seasonsWon,
                'bestScore' => $best[$name] ?? 0,
            ];
        }
        usort($champions, fn ($a, $b) => [$b['titles'], end($b['seasons'])] <=> [$a['titles'], end($a['seasons'])]);

        $records = [];
        if ($champions) {
            $records['mostTitles'] = ['name' => $champions[0]['name'], 'count' => $champions[0]['titles']];
        }
        if ($bestStreak > 0) {
            $records['longestStreak'] = ['name' => $streakName, 'count' => $bestStreak];
        }
        if ($topScore !== null) {
            $records['highestScore'] = ['name' => $topScore['name'], 'score' => $topScore['score'], 'season' => $topScore['season']];
        }
        if ($margin !== null) {
            $records['biggestMargin'] = $margin;
        }

        return [
            'seasonsPlayed' => count($rows),
            'champions'     => $champions,
            'history'       => $history,
            'records'       => (object) $records,
        ];
    }
}
