<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\OptionsGeneral;
use App\Models\User;
use App\Services\SsoClient;
use Illuminate\Http\Request;

/**
 * Réglages de la connexion unique : le panel comme client du SSO du site.
 *
 * Réservé aux super-admins (le secret client vaut, en pratique, les clés du
 * panel) — la restriction est posée sur la route.
 */
class AdminSsoController extends Controller
{
    public function __construct(private readonly SsoClient $sso)
    {
    }

    public function show()
    {
        $options = OptionsGeneral::first();

        return view('admin.sso', [
            'options'    => $options,
            'configured' => $this->sso->isConfigured(),
            'callback'   => route('sso.callback'),
            // Le secret n'est jamais réaffiché : on indique seulement s'il
            // y en a un, comme pour un mot de passe.
            'hasSecret'  => !empty(optional($options)->sso_client_secret),
            'linked'     => User::whereNotNull('sso_id')->count(),
            'total'      => User::count(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'sso_url'           => 'nullable|url|max:255|starts_with:https://,http://',
            'sso_client_id'     => 'nullable|string|max:100',
            'sso_client_secret' => 'nullable|string|max:255',
        ]);

        $options = OptionsGeneral::first();
        if ($options === null) {
            return redirect()->route('admin.sso')
                ->withErrors(['sso' => __('messages.common.errors_occurred')]);
        }

        $options->sso_url = rtrim((string) ($data['sso_url'] ?? ''), '/') ?: null;
        $options->sso_client_id = $data['sso_client_id'] ?? null;
        $options->sso_enabled = $request->boolean('sso_enabled');
        $options->sso_auto_provision = $request->boolean('sso_auto_provision');

        // Champ laissé vide = on garde le secret existant, sinon activer une
        // option effacerait la configuration sans prévenir.
        if (!empty($data['sso_client_secret'])) {
            $options->sso_client_secret = SsoClient::encryptSecret($data['sso_client_secret']);
        }

        $options->save();

        AuditLog::record('sso.settings_updated', $options, [
            'enabled' => $options->sso_enabled,
            'url'     => $options->sso_url,
        ]);

        return redirect()->route('admin.sso')->with('success', __('messages.flash.options_updated'));
    }
}
