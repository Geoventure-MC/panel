<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Deuxième étape du login pour les comptes avec 2FA : après un mot de passe
 * valide, {@see LoginController} déconnecte l'utilisateur, stocke son id en
 * session et redirige ici. Accepte un code TOTP (fenêtre ±1 période) ou un
 * code de récupération à usage unique.
 */
class TwoFactorChallengeController extends Controller
{
    public function show(Request $request)
    {
        if (!$request->session()->has('2fa_challenge_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:32']]);

        $userId = $request->session()->get('2fa_challenge_user_id');
        $user = $userId ? User::find($userId) : null;

        if ($user === null || empty($user->totp_secret)) {
            $request->session()->forget(['2fa_challenge_user_id', '2fa_challenge_remember']);

            return redirect()->route('login');
        }

        $code = trim($request->input('code'));
        $valid = Totp::verify($user->totp_secret, $code) || $this->useRecoveryCode($user, $code);

        if (!$valid) {
            AuditLog::record('security.2fa.challenge_failed', $user);

            return back()->withErrors(['code' => __('messages.two_factor.invalid_code')]);
        }

        $remember = (bool) $request->session()->pull('2fa_challenge_remember', false);
        $request->session()->forget('2fa_challenge_user_id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended('/admin');
    }

    /** Consomme un code de récupération valide (usage unique). */
    private function useRecoveryCode(User $user, string $code): bool
    {
        $hashes = json_decode($user->totp_recovery_codes ?? '[]', true);
        if (!is_array($hashes)) {
            return false;
        }

        foreach ($hashes as $i => $hash) {
            if (is_string($hash) && Hash::check(strtoupper($code), $hash)) {
                unset($hashes[$i]);
                $user->forceFill([
                    'totp_recovery_codes' => json_encode(array_values($hashes)),
                ])->save();

                AuditLog::record('security.2fa.recovery_code_used', $user);

                return true;
            }
        }

        return false;
    }
}
