<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\SsoClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * « Se connecter avec geoventure.fr » — le panel délègue son identification.
 *
 * Aucun compte n'est créé à l'aveugle : par défaut, seul un compte panel
 * existant (rapproché par e-mail) peut être lié. L'auto-création reste une
 * option explicite, parce qu'un panel d'administration n'a pas vocation à
 * s'ouvrir à tous les joueurs du site.
 */
class SsoLoginController extends Controller
{
    public function __construct(private readonly SsoClient $sso)
    {
    }

    public function redirect(Request $request)
    {
        if (!$this->sso->isConfigured()) {
            return redirect()->route('login')
                ->withErrors(['sso' => __('messages.sso.not_configured')]);
        }

        // `state` lie la réponse à CETTE session : sans lui, un tiers pourrait
        // faire aboutir chez nous un code obtenu ailleurs.
        $state = Str::random(40);
        $request->session()->put('sso_state', $state);

        return redirect()->away($this->sso->authorizeUrl(route('sso.callback'), $state));
    }

    public function callback(Request $request)
    {
        if (!$this->sso->isConfigured()) {
            return redirect()->route('login')->withErrors(['sso' => __('messages.sso.not_configured')]);
        }

        $expected = $request->session()->pull('sso_state');
        $state = (string) $request->query('state', '');

        if ($expected === null || !hash_equals((string) $expected, $state)) {
            return redirect()->route('login')->withErrors(['sso' => __('messages.sso.bad_state')]);
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route('login')->withErrors(['sso' => __('messages.sso.no_code')]);
        }

        $result = $this->sso->exchange($code, route('sso.callback'));
        if ($result === null) {
            return redirect()->route('login')->withErrors(['sso' => __('messages.sso.exchange_failed')]);
        }

        $profile = $result['user'];
        $user = $this->resolveUser($profile);

        if ($user === null) {
            return redirect()->route('login')->withErrors(['sso' => __('messages.sso.no_local_account')]);
        }

        // Le ticket sert à couper la session côté site à la déconnexion.
        $request->session()->put('sso_ticket', $result['ticket']);

        Auth::login($user, true);
        $request->session()->regenerate();

        $this->audit($user, 'sso.login');

        return redirect()->intended(route('admin.index'));
    }

    /**
     * Retrouve — ou crée — le compte panel correspondant au profil du site.
     *
     * Ordre de rapprochement : lien déjà établi, puis e-mail. L'e-mail est le
     * seul point commun fiable entre les deux bases ; le pseudo, lui, peut
     * changer côté site.
     */
    private function resolveUser(array $profile): ?User
    {
        $ssoId = (int) ($profile['id'] ?? 0);
        if ($ssoId <= 0) {
            return null;
        }

        $user = User::where('sso_id', $ssoId)->first();

        if ($user === null && !empty($profile['email'])) {
            $user = User::where('email', $profile['email'])->first();
        }

        if ($user === null) {
            if (!(bool) optional($this->sso->options())->sso_auto_provision) {
                return null;
            }

            $user = new User();
            $user->name = $this->uniqueName((string) ($profile['name'] ?? 'joueur'));
            $user->email = (string) ($profile['email'] ?? $ssoId.'@sso.local');
            // Mot de passe inutilisable : ce compte ne se connecte que par SSO.
            $user->password = Str::random(64);
            $user->email_verified_at = now();
            $user->is_admin = false;
            $user->role = 'moderator';
        }

        $user->sso_id = $ssoId;
        $user->sso_uuid = $profile['uuid'] ?? null;
        $user->sso_linked_at = now();

        // Les droits admin ne montent que si le site le dit ET que le client
        // y est habilité (le site refuse `is_admin` sinon). Ils ne sont
        // jamais retirés ici : un admin panel local doit le rester.
        if (!empty($profile['is_admin'])) {
            $user->is_admin = true;
        }

        $user->save();

        return $user;
    }

    /** Évite une collision de pseudo avec un compte panel déjà existant. */
    private function uniqueName(string $base): string
    {
        $name = mb_substr($base, 0, 40);
        $candidate = $name;
        $i = 1;

        while (User::where('name', $candidate)->exists()) {
            $candidate = $name.'-'.(++$i);
        }

        return $candidate;
    }

    private function audit(User $user, string $action): void
    {
        try {
            AuditLog::create([
                'user_id' => $user->id,
                'action'  => $action,
                'target'  => User::class.'#'.$user->id,
                'changes' => ['sso_id' => $user->sso_id],
                'ip'      => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            // Le journal d'audit ne doit jamais faire échouer une connexion.
            report($e);
        }
    }
}
