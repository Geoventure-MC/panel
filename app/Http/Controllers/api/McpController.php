<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\McpKey;
use App\Models\McpSetting;
use App\Services\Mcp\McpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * POST /api/mcp — serveur MCP (JSON-RPC 2.0, transport HTTP « streamable », réponses JSON).
 *
 * Sans session ni CSRF (route de routes/api.php) ; authentification par en-tête
 * `Authorization: Bearer gmcp_…`. DÉSACTIVÉ par défaut (réglage de la page « API MCP »).
 *
 * Défenses : taille de corps bornée, limite de débit par IP puis par clé, blocage après
 * échecs répétés, comparaison de hash en temps constant, restriction d'IP optionnelle.
 * Codes d'erreur HTTP-niveau (corps JSON-RPC) : -32000 désactivé, -32001 non authentifié,
 * -32002 interdit, -32003 débit dépassé, -32004 corps trop gros.
 */
class McpController extends Controller
{
    public function __construct(private McpServer $server)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        app()->setLocale('fr');

        if (! McpSetting::enabled()) {
            return $this->fail(404, -32000, 'API MCP désactivée.');
        }

        $ipKey = hash('sha256', (string) $request->ip());

        if (Cache::has('mcp:lock:' . $ipKey)) {
            return $this->fail(429, -32003, 'Trop d\'échecs d\'authentification : accès temporairement bloqué.', ['Retry-After' => (string) config('mcp.lockout')]);
        }
        if (RateLimiter::tooManyAttempts('mcp:ip:' . $ipKey, (int) config('mcp.ip_rate_per_minute'))) {
            return $this->fail(429, -32003, 'Limite de débit dépassée.', ['Retry-After' => (string) RateLimiter::availableIn('mcp:ip:' . $ipKey)]);
        }
        RateLimiter::hit('mcp:ip:' . $ipKey, 60);

        $max = (int) config('mcp.max_body_bytes');
        if ((int) $request->header('Content-Length', 0) > $max || strlen($request->getContent()) > $max) {
            return $this->fail(413, -32004, 'Corps de requête trop volumineux.');
        }

        $key = $this->authenticate($request);
        if (! $key) {
            $this->registerFailure($ipKey);
            // Petit délai aléatoire : ralentit l'énumération sans bloquer les clients légitimes.
            usleep(random_int(150, 350) * 1000);

            return $this->fail(401, -32001, 'Clé MCP invalide, expirée ou révoquée.', ['WWW-Authenticate' => 'Bearer realm="geoventure-mcp"']);
        }

        if (! $this->ipAllowed($key, (string) $request->ip())) {
            $this->registerFailure($ipKey);

            return $this->fail(403, -32002, 'Adresse IP non autorisée pour cette clé.');
        }

        $keyBucket = 'mcp:key:' . $key->id;
        if (RateLimiter::tooManyAttempts($keyBucket, (int) config('mcp.rate_per_minute'))) {
            return $this->fail(429, -32003, 'Limite de débit dépassée pour cette clé.', ['Retry-After' => (string) RateLimiter::availableIn($keyBucket)]);
        }
        RateLimiter::hit($keyBucket, 60);

        $key->forceFill(['last_used_at' => now(), 'last_used_ip_hash' => $ipKey])->save();

        $decoded = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return response()->json(McpServer::error(null, McpServer::E_PARSE, 'JSON invalide.'), 400);
        }

        try {
            $response = $this->server->handleBody($decoded, $key, $request);
        } catch (\Throwable $e) {
            Log::error('[MCP] ' . $e->getMessage());
            $response = McpServer::error(null, McpServer::E_INTERNAL, 'Erreur interne.');
        }

        if ($response === null) {
            return response()->json(null, 202); // notifications : acquittement sans corps
        }

        return response()->json($response, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** GET/DELETE : pas de flux serveur ni de session. */
    public function notAllowed(): JsonResponse
    {
        return $this->fail(405, -32000, 'Méthode non autorisée : utilisez POST.', ['Allow' => 'POST']);
    }

    private function authenticate(Request $request): ?McpKey
    {
        $header = (string) $request->header('Authorization', '');
        if (! preg_match('/^Bearer\s+(gmcp_[A-Za-z0-9]{40})$/', trim($header), $m)) {
            return null;
        }
        $hash = McpKey::hash($m[1]);
        $found = null;
        // Candidats par préfixe, puis comparaison des hash en temps constant.
        foreach (McpKey::where('key_prefix', substr($m[1], 0, 8))->get() as $cand) {
            if (hash_equals($cand->getRawOriginal('key_hash'), $hash)) {
                $found = $cand;
            }
        }
        if (! $found) {
            hash_equals(str_repeat('0', 64), $hash); // coût constant pour une clé inconnue

            return null;
        }

        return $found->isActive() ? $found : null;
    }

    private function ipAllowed(McpKey $key, string $ip): bool
    {
        $list = $key->allowed_ips;
        if (! is_array($list) || $list === []) {
            return true;
        }

        return IpUtils::checkIp($ip, $list);
    }

    private function registerFailure(string $ipKey): void
    {
        $k = 'mcp:fail:' . $ipKey;
        Cache::add($k, 0, (int) config('mcp.failure_window'));
        $n = Cache::increment($k);
        if ($n >= (int) config('mcp.max_failures')) {
            Cache::put('mcp:lock:' . $ipKey, 1, (int) config('mcp.lockout'));
            Cache::forget($k);
        }
    }

    private function fail(int $http, int $code, string $message, array $headers = []): JsonResponse
    {
        return response()->json(McpServer::error(null, $code, $message), $http, $headers, JSON_UNESCAPED_UNICODE);
    }
}
