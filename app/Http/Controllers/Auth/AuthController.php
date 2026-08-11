<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /** Get the Socialite driver with SSL workaround for local dev. */
    private function googleDriver()
    {
        $driver = Socialite::driver('google')
            ->redirectUrl(config('services.google.redirect'))
            ->stateless();

        // Windows fix: provide CA cert bundle
        $certPath = base_path('cacert.pem');
        $verify = file_exists($certPath) ? $certPath : true;
        $driver->setHttpClient(new \GuzzleHttp\Client(['verify' => $verify]));

        return $driver;
    }
    /** Show login form. */
    public function showLogin(): \Illuminate\Http\Response
    {
        $error = session('errors') ? session('errors')->first() : '';
        $oldEmail = old('email', '');
        $csrf = csrf_token();
        $base = request()->getBasePath() ?: '/courtly';

        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login — Courtly</title><link rel="icon" href="'.$base.'/assets/favicon.png?v=2"><link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="'.$base.'/css/courtly.css?v=3"><style>.auth-page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:#12121f;font-family:"JetBrains Mono","SF Mono","Fira Code",monospace}.auth-card{width:100%;max-width:540px;background:#1e1e32;border-radius:8px;padding:48px 44px;box-shadow:0 4px 20px rgba(0,0,0,.3);border:1px solid #2e2e4a}.auth-logo{text-align:center;margin-bottom:32px}.auth-logo img{height:128px}.auth-card h2{font-size:1.8rem;font-weight:800;margin-bottom:8px;text-align:center;color:#e4e4f0}.auth-card .sub{color:#8888a8;text-align:center;margin-bottom:32px;font-size:1.1rem}.auth-field{margin-bottom:20px}.auth-field label{display:block;font-size:1rem;font-weight:700;color:#8888a8;margin-bottom:6px}.auth-field input{width:100%;padding:14px 16px;border:1px solid #2e2e4a;border-radius:6px;font-size:1.15rem;box-sizing:border-box;font-family:inherit;background:#12121f;color:#e4e4f0}.auth-field input:focus{outline:none;border-color:#ff2d55}.auth-btn{width:100%;padding:16px;border:none;border-radius:6px;font-size:1.15rem;font-weight:700;cursor:pointer;margin-bottom:16px;font-family:inherit}.auth-btn--primary{background:#ff2d55;color:#fff}.auth-divider{display:flex;align-items:center;gap:14px;margin:24px 0;color:#8888a8;font-size:1rem}.auth-divider::before,.auth-divider::after{content:\'\';flex:1;height:1px;background:#2e2e4a}.social-btn{width:100%;padding:14px;border:1px solid #2e2e4a;border-radius:6px;font-size:1.1rem;font-weight:700;cursor:pointer;margin-bottom:12px;display:flex;align-items:center;justify-content:center;gap:10px;background:#252540;color:#e4e4f0;text-decoration:none;font-family:inherit}.auth-footer{text-align:center;font-size:1.05rem;color:#8888a8;margin-top:12px}.auth-footer a{color:#ff2d55;font-weight:700;text-decoration:none}.auth-error{background:#3a1020;color:#ff2d55;padding:14px 18px;border-radius:6px;font-size:1rem;margin-bottom:20px;border:1px solid #4a1525}</style></head><body><div class="auth-page"><div class="auth-card"><div class="auth-logo"><a href="'.$base.'/"><img src="'.$base.'/assets/courtly_light.png" alt="Courtly"></a></div><h2>Welcome back</h2><p class="sub">Sign in to manage your badminton sessions</p>';

        if ($error) {
            $html .= '<div class="auth-error">' . e($error) . '</div>';
        }

        $html .= '<form method="POST" action="'.$base.'/login"><input type="hidden" name="_token" value="' . $csrf . '"><div class="auth-field"><label>Email</label><input type="email" name="email" value="' . e($oldEmail) . '" placeholder="you@example.com" required autofocus></div><div class="auth-field"><label>Password</label><input type="password" name="password" placeholder="········" required></div><button type="submit" class="auth-btn auth-btn--primary">Sign in</button></form><div class="auth-divider">or continue with</div><a href="'.$base.'/auth/google/redirect" class="social-btn"><svg width="22" height="22" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg> Google</a><a href="'.$base.'/auth/facebook/redirect" class="social-btn"><svg width="22" height="22" viewBox="0 0 24 24"><path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg> Facebook</a><p class="auth-footer">Don\'t have an account? <a href="'.$base.'/register">Sign up</a></p></div></div></body></html>';

        return response($html);
    }

    /** Show registration form. */
    public function showRegister(): \Illuminate\Http\Response
    {
        $error = session('errors') ? session('errors')->first() : '';
        $oldName = old('name', '');
        $oldEmail = old('email', '');
        $csrf = csrf_token();
        $base = request()->getBasePath() ?: '/courtly';

        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Register — Courtly</title><link rel="icon" href="'.$base.'/assets/favicon.png?v=2"><link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="'.$base.'/css/courtly.css?v=3"><style>.auth-page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:#12121f;font-family:"JetBrains Mono","SF Mono","Fira Code",monospace}.auth-card{width:100%;max-width:540px;background:#1e1e32;border-radius:8px;padding:48px 44px;box-shadow:0 4px 20px rgba(0,0,0,.3);border:1px solid #2e2e4a}.auth-logo{text-align:center;margin-bottom:32px}.auth-logo img{height:128px}.auth-card h2{font-size:1.8rem;font-weight:800;margin-bottom:8px;text-align:center;color:#e4e4f0}.auth-card .sub{color:#8888a8;text-align:center;margin-bottom:32px;font-size:1.1rem}.auth-field{margin-bottom:20px}.auth-field label{display:block;font-size:1rem;font-weight:700;color:#8888a8;margin-bottom:6px}.auth-field input{width:100%;padding:14px 16px;border:1px solid #2e2e4a;border-radius:6px;font-size:1.15rem;box-sizing:border-box;font-family:inherit;background:#12121f;color:#e4e4f0}.auth-field input:focus{outline:none;border-color:#ff2d55}.auth-btn{width:100%;padding:16px;border:none;border-radius:6px;font-size:1.15rem;font-weight:700;cursor:pointer;margin-bottom:16px;font-family:inherit}.auth-btn--primary{background:#ff2d55;color:#fff}.auth-divider{display:flex;align-items:center;gap:14px;margin:24px 0;color:#8888a8;font-size:1rem}.auth-divider::before,.auth-divider::after{content:\'\';flex:1;height:1px;background:#2e2e4a}.social-btn{width:100%;padding:14px;border:1px solid #2e2e4a;border-radius:6px;font-size:1.1rem;font-weight:700;cursor:pointer;margin-bottom:12px;display:flex;align-items:center;justify-content:center;gap:10px;background:#252540;color:#e4e4f0;text-decoration:none;font-family:inherit}.auth-footer{text-align:center;font-size:1.05rem;color:#8888a8;margin-top:12px}.auth-footer a{color:#ff2d55;font-weight:700;text-decoration:none}.auth-error{background:#3a1020;color:#ff2d55;padding:14px 18px;border-radius:6px;font-size:1rem;margin-bottom:20px;border:1px solid #4a1525}</style></head><body><div class="auth-page"><div class="auth-card"><div class="auth-logo"><a href="'.$base.'/"><img src="'.$base.'/assets/courtly_light.png" alt="Courtly"></a></div><h2>Create your account</h2><p class="sub">Join the badminton community</p>';

        if ($error) {
            $html .= '<div class="auth-error">' . e($error) . '</div>';
        }

        $html .= '<form method="POST" action="'.$base.'/register"><input type="hidden" name="_token" value="' . $csrf . '"><div class="auth-field"><label>Name</label><input type="text" name="name" value="' . e($oldName) . '" placeholder="Your name" required autofocus></div><div class="auth-field"><label>Email</label><input type="email" name="email" value="' . e($oldEmail) . '" placeholder="you@example.com" required></div><div class="auth-field"><label>Password</label><input type="password" name="password" placeholder="At least 8 characters" required minlength="8"></div><div class="auth-field"><label>Confirm Password</label><input type="password" name="password_confirmation" placeholder="Same as above" required minlength="8"></div><button type="submit" class="auth-btn auth-btn--primary">Create account</button></form><div class="auth-divider">or continue with</div><a href="'.$base.'/auth/google/redirect" class="social-btn"><svg width="22" height="22" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg> Google</a><a href="'.$base.'/auth/facebook/redirect" class="social-btn"><svg width="22" height="22" viewBox="0 0 24 24"><path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg> Facebook</a><p class="auth-footer">Already have an account? <a href="'.$base.'/login">Sign in</a></p></div></div></body></html>';

        return response($html);
    }

    /** Handle email/password login. */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended('/');
        }

        return back()->withErrors(['email' => 'Invalid email or password.'])->onlyInput('email');
    }

    /** Handle registration. */
    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);

        return redirect('/');
    }

    /** Logout. */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    // ── Social login redirects ────────────────────────────────────────

    /** Redirect to Google. */
    public function redirectToGoogle(): RedirectResponse
    {
        return $this->googleDriver()->redirect();
    }

    /** Handle Google callback. */
    public function handleGoogleCallback(): RedirectResponse
    {
        $googleUser = $this->googleDriver()->user();

        $user = User::updateOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'name' => $googleUser->getName(),
                'google_id' => $googleUser->getId(),
                'password' => Hash::make(bin2hex(random_bytes(16))),
            ]
        );

        Auth::login($user);

        return redirect('/');
    }

    /** Redirect to Facebook. */
    public function redirectToFacebook(): RedirectResponse
    {
        return Socialite::driver('facebook')
            ->redirectUrl(url('/auth/facebook/callback'))
            ->redirect();
    }

    /** Handle Facebook callback. */
    public function handleFacebookCallback(): RedirectResponse
    {
        $facebookUser = Socialite::driver('facebook')
            ->redirectUrl(url('/auth/facebook/callback'))
            ->user();

        $user = User::updateOrCreate(
            ['email' => $facebookUser->getEmail()],
            [
                'name' => $facebookUser->getName(),
                'facebook_id' => $facebookUser->getId(),
                'password' => Hash::make(bin2hex(random_bytes(16))),
            ]
        );

        Auth::login($user);

        return redirect('/');
    }
}
