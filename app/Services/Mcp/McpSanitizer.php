<?php

namespace App\Services\Mcp;

/**
 * Retire les secrets de tout ce qui sort du MCP (résultats d'outils) ou qui est
 * journalisé (arguments, avant/après d'audit). Défense en profondeur : les
 * outils ne manipulent déjà aucun secret.
 */
class McpSanitizer
{
    public const MASK = '••••';

    /** Noms de champs dont la valeur n'est jamais exposée. */
    private const SENSITIVE_KEY = '/pass(word)?|passwd|token|secret|api[_-]?key|apikey|webhook|authorization|credential|private|totp|remember|cookie|e-?mail|client_id|key_hash|ip_hash/i';

    /** Motifs de valeurs : nos clés MCP et les URL de webhook Discord. */
    private const SENSITIVE_VALUE = [
        '/gmcp_[A-Za-z0-9]{10,}/',
        '#https?://(?:[a-z]+\.)?discord(?:app)?\.com/api/webhooks/\S+#i',
    ];

    public static function scrub(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 8) {
            return null;
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = is_string($k) && preg_match(self::SENSITIVE_KEY, $k) && ((is_string($v) && $v !== '') || is_array($v))
                    ? self::MASK
                    : self::scrub($v, $depth + 1);
            }

            return $out;
        }
        if (is_object($value)) {
            return self::scrub(method_exists($value, 'toArray') ? $value->toArray() : (array) $value, $depth + 1);
        }
        if (is_string($value)) {
            foreach (self::SENSITIVE_VALUE as $re) {
                $value = preg_replace($re, self::MASK, $value);
            }
        }

        return $value;
    }

    /** Version compacte pour le journal : secrets masqués, chaînes tronquées, JSON borné. */
    public static function forLog(array $args, int $maxJson = 1000): string
    {
        $clean = self::truncate(self::scrub($args));
        $json = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

        return mb_strlen($json) > $maxJson ? mb_substr($json, 0, $maxJson) . '…' : $json;
    }

    private static function truncate(mixed $v, int $depth = 0): mixed
    {
        if (is_array($v)) {
            return $depth > 4 ? '…' : array_map(fn ($x) => self::truncate($x, $depth + 1), array_slice($v, 0, 30, true));
        }

        return is_string($v) && mb_strlen($v) > 120 ? mb_substr($v, 0, 120) . '…' : $v;
    }
}
