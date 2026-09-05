<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Courtly</title>
    <link rel="icon" type="image/png" href="/assets/favicon.png?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/courtly.css?v=3">
    <style>
        .auth-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; background: var(--bg); font-family: "Manrope", "Segoe UI", sans-serif; }
        .auth-card { width: 100%; max-width: 540px; background: var(--surface); border-radius: 8px; padding: 48px 44px; box-shadow: var(--shadow-card); border: 1px solid var(--stroke); }
        .auth-logo { text-align: center; margin-bottom: 32px; }
        .auth-logo img { height: 128px; }
        .auth-card h2 { font-size: 1.8rem; font-weight: 800; margin-bottom: 8px; text-align: center; color: var(--text); }
        .auth-card .sub { color: var(--text-muted); text-align: center; margin-bottom: 32px; font-size: 1.1rem; }
        .auth-field { margin-bottom: 20px; }
        .auth-field label { display: block; font-size: 1rem; font-weight: 700; color: var(--text-muted); margin-bottom: 6px; }
        .auth-field input { width: 100%; padding: 14px 16px; border: 1px solid var(--stroke); border-radius: 6px; font-size: 1.15rem; box-sizing: border-box; font-family: inherit; background: var(--bg); color: var(--text); }
        .auth-field input:focus { outline: none; border-color: var(--court-cyan); }
        .auth-btn { width: 100%; padding: 16px; border: none; border-radius: 6px; font-size: 1.15rem; font-weight: 700; cursor: pointer; margin-bottom: 16px; font-family: inherit; }
        .auth-btn--primary { background: var(--court-blue); color: #fff; }
        .auth-btn--primary:hover { filter: brightness(1.1); }
        .auth-divider { display: flex; align-items: center; gap: 14px; margin: 24px 0; color: #8888a8; font-size: 1rem; }
        .auth-divider::before, .auth-divider::after { content: ''; flex: 1; height: 1px; background: var(--stroke); }
        .social-btn { width: 100%; padding: 14px; border: 1px solid var(--stroke); border-radius: 6px; font-size: 1.1rem; font-weight: 700; cursor: pointer; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; gap: 10px; background: var(--surface-2); color: var(--text); text-decoration: none; font-family: inherit; }
        .social-btn:hover { background: var(--bg-accent); }
        .social-btn svg { width: 22px; height: 22px; }
        .auth-footer { text-align: center; font-size: 1.05rem; color: var(--text-muted); margin-top: 12px; }
        .auth-footer a { color: var(--court-cyan); font-weight: 700; text-decoration: none; }
        .auth-error { background: rgba(255,104,104,.12); color: #ff8d98; padding: 14px 18px; border-radius: 6px; font-size: 1rem; margin-bottom: 20px; border: 1px solid rgba(255,141,152,.35); }
    </style>
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo"><a href="/"><img src="/assets/favicon.png" alt="Courtly"></a></div>
        <h2>Create your account</h2>
        <p class="sub">Play. Connect. Rotate. Improve.<br>Join the badminton community.</p>

        @if ($errors->any())
            <div class="auth-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/register">
            @csrf
            <div class="auth-field">
                <label>Name</label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="Your name" required autofocus>
            </div>
            <div class="auth-field">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required>
            </div>
            <div class="auth-field">
                <label>Password</label>
                <input type="password" name="password" placeholder="At least 8 characters" required minlength="8">
            </div>
            <div class="auth-field">
                <label>Confirm Password</label>
                <input type="password" name="password_confirmation" placeholder="Same as above" required minlength="8">
            </div>
            <button type="submit" class="auth-btn auth-btn--primary">Create account</button>
        </form>

        <div class="auth-divider">or continue with</div>

        <a href="/auth/google/redirect" class="social-btn">
            <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
            Google
        </a>

        <a href="/auth/facebook/redirect" class="social-btn">
            <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            Facebook
        </a>

        <p class="auth-footer">Already have an account? <a href="/login">Sign in</a></p>
    </div>
</div>
</body>
</html>
