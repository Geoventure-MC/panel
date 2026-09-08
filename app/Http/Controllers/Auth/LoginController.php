<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers {
        logout as protected traitLogout;
    }

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/admin';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /**
     * Déconnexion : on coupe aussi la session côté site.
     *
     * Sans ça, se déconnecter du panel laissait le ticket SSO valide sur
     * geoventure.fr — le clic suivant sur « se connecter avec le site »
     * reconnectait sans rien demander, ce qui n'est pas ce qu'on attend
     * d'une déconnexion sur un poste partagé.
     */
    public function logout(Request $request)
    {
        $ticket = $request->session()->get('sso_ticket');
        if (is_string($ticket) && $ticket !== '') {
            app(\App\Services\SsoClient::class)->logout($ticket);
        }

        return $this->traitLogout($request);
    }

    /**
     * 2FA : si le compte a un secret TOTP, le mot de passe seul ne suffit
     * pas — on referme la session et on bascule vers l'étape du code
     * (session courte portant l'id + le « se souvenir de moi »).
     */
    protected function authenticated(Request $request, $user)
    {
        if (empty($user->totp_secret)) {
            return null; // comportement standard
        }

        $remember = $request->boolean('remember');

        Auth::logout();
        $request->session()->regenerate();
        $request->session()->put('2fa_challenge_user_id', $user->id);
        $request->session()->put('2fa_challenge_remember', $remember);

        return redirect()->route('two-factor.challenge');
    }
}
