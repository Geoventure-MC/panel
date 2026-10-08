<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\McpCall;
use App\Models\McpKey;
use App\Models\McpSetting;
use App\Services\Mcp\McpRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Page « API MCP » (super-admin) : activation globale, clés d'accès, journal des appels.
 * La clé en clair n'est affichée qu'UNE fois, dans la réponse à sa création : jamais stockée ni relisible.
 */
class AdminMcpController extends Controller
{
    public function index(Request $request)
    {
        return $this->render($request);
    }

    private function render(Request $request, array $extra = [])
    {
        $keyFilter = $request->query('key');
        $calls = McpCall::query()
            ->when($keyFilter, fn ($q) => $q->where('mcp_key_id', (int) $keyFilter))
            ->orderByDesc('id')->paginate(25, ['*'], 'calls_page')->withQueryString();

        return view('admin.mcp', array_merge([
            'enabled'   => McpSetting::enabled(),
            'keys'      => McpKey::orderByDesc('id')->get(),
            'calls'     => $calls,
            'keyFilter' => $keyFilter,
            'tools'     => McpRegistry::all(),
            'secure'    => $request->isSecure(),
            'endpoint'  => url('/api/mcp'),
            'newKey'    => null,
            'newKeyName' => null,
        ], $extra));
    }

    public function toggle(Request $request)
    {
        $enabled = $request->boolean('enabled');
        McpSetting::put('enabled', $enabled ? '1' : '0');
        AuditLog::record('mcp.toggle', null, ['enabled' => $enabled]);

        return redirect()->route('admin.mcp')->with('success', __($enabled ? 'mcp.flash.enabled' : 'mcp.flash.disabled'));
    }

    public function store(Request $request)
    {
        $toolNames = array_keys(McpRegistry::all());
        $data = $request->validate([
            'name'            => 'required|string|min:2|max:100',
            'scope'           => ['required', Rule::in(McpKey::SCOPES)],
            'allowed_tools'   => 'nullable|array|max:100',
            'allowed_tools.*' => ['string', Rule::in($toolNames)],
            'allowed_ips'     => 'nullable|string|max:1000',
            'expires_at'      => 'nullable|date|after:now',
        ]);

        $ips = [];
        foreach (preg_split('/[\s,;]+/', (string) ($data['allowed_ips'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $ip) {
            [$addr, $mask] = array_pad(explode('/', $ip, 2), 2, null);
            $valid = filter_var($addr, FILTER_VALIDATE_IP) !== false
                && ($mask === null || (ctype_digit($mask) && (int) $mask <= (str_contains($addr, ':') ? 128 : 32)));
            if (! $valid) {
                return back()->withErrors(['allowed_ips' => __('mcp.errors.bad_ip', ['ip' => mb_substr($ip, 0, 50)])])->withInput();
            }
            $ips[] = $ip;
        }

        $plain = McpKey::generate();
        $key = McpKey::create([
            'name'          => trim($data['name']),
            'key_hash'      => McpKey::hash($plain),
            'key_prefix'    => substr($plain, 0, 8),
            'scope'         => $data['scope'],
            'allowed_tools' => ! empty($data['allowed_tools']) ? array_values($data['allowed_tools']) : null,
            'allowed_ips'   => $ips ?: null,
            'created_by'    => $request->user()->id,
            'expires_at'    => $data['expires_at'] ?? null,
        ]);
        // Jamais la clé dans l'audit.
        AuditLog::record('mcp.key.create', $key, [
            'name' => $key->name, 'scope' => $key->scope, 'tools' => $key->allowed_tools ? count($key->allowed_tools) : 'all',
            'ips' => count($ips), 'expires_at' => $key->expires_at?->toIso8601String(),
        ]);

        // Réponse directe (pas de redirection ni de flash) : la clé n'est écrite nulle part.
        return $this->render($request, ['newKey' => $plain, 'newKeyName' => $key->name]);
    }

    public function revoke(McpKey $key)
    {
        if (! $key->revoked_at) {
            $key->forceFill(['revoked_at' => now()])->save();
            AuditLog::record('mcp.key.revoke', $key, ['name' => $key->name]);
        }

        return redirect()->route('admin.mcp')->with('success', __('mcp.flash.revoked'));
    }
}
