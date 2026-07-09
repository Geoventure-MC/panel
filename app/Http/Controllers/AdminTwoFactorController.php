<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 2FA (TOTP) du compte admin : activation en deux temps (secret affiché puis
 * confirmé par un code), 8 codes de récupération à usage unique (hashés,
 * affichés UNE SEULE fois), désactivation protégée par mot de passe.
 * Toutes les transitions sont journalisées dans l'audit.
 */
class AdminTwoFactorController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $pendingSecret = session('2fa_pending_secret');

        return view('admin.two-factor', [
            'enabled'       => !empty($user->totp_secret),
            'pendingSecret' => $pendingSecret,
            'otpauthUri'    => $pendingSecret
                ? Totp::otpauthUri($pendingSecret, $user->email ?? $user->name, config('app.name', 'Panel'))
                : null,
            // Codes de récupération en clair : uniquement juste après l'activation.
            'freshRecoveryCodes' => session('2fa_fresh_recovery_codes'),
        ]);
    }

    /** Étape 1 : génère un secret provisoire (en session, rien en base). */
    public function begin()
    {
        $user = Auth::user();
        if (!empty($user->totp_secret)) {
            return redirect()->route('admin.two-factor.index');
        }

        session(['2fa_pending_secret' => Totp::generateSecret()]);

        return redirect()->route('admin.two-factor.index');
    }

    /** Étape 2 : confirme le secret provisoire avec un code de l'application. */
    public function confirm(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:16']]);

        $user = Auth::user();
        $secret = session('2fa_pending_secret');

        if (empty($secret) || !empty($user->totp_secret)) {
            return redirect()->route('admin.two-factor.index');
        }

        if (!Totp::verify($secret, $request->input('code'))) {
            AuditLog::record('security.2fa.confirm_failed', $user);

            return back()->withErrors(['code' => __('messages.two_factor.invalid_code')]);
        }

        // 8 codes de récupération à usage unique, stockés hashés.
        $plainCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $plainCodes[] = strtoupper(Str::random(5) . '-' . Str::random(5));
        }

        $user->forceFill([
            'totp_secret'         => $secret,
            'totp_recovery_codes' => json_encode(array_map(fn ($c) => Hash::make($c), $plainCodes)),
        ])->save();

        session()->forget('2fa_pending_secret');
        AuditLog::record('security.2fa.enabled', $user);

        // Affichés une seule fois (flash), jamais réaffichables.
        return redirect()->route('admin.two-factor.index')
            ->with('2fa_fresh_recovery_codes', $plainCodes)
            ->with('success', __('messages.flash.two_factor_enabled'));
    }

    /** Annule une activation commencée (secret provisoire non confirmé). */
    public function cancel()
    {
        session()->forget('2fa_pending_secret');

        return redirect()->route('admin.two-factor.index');
    }

    /** Désactivation : mot de passe du compte exigé. */
    public function disable(Request $request)
    {
        $request->validate(['password' => ['required', 'string']]);

        $user = Auth::user();

        if (!Hash::check($request->input('password'), $user->password)) {
            AuditLog::record('security.2fa.disable_failed', $user);

            return back()->withErrors(['password' => __('messages.two_factor.invalid_password')]);
        }

        $user->forceFill([
            'totp_secret'         => null,
            'totp_recovery_codes' => null,
        ])->save();

        AuditLog::record('security.2fa.disabled', $user);

        return redirect()->route('admin.two-factor.index')
            ->with('success', __('messages.flash.two_factor_disabled'));
    }
}
