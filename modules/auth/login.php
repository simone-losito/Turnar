<?php
// modules/auth/login.php

require_once __DIR__ . '/../../core/settings.php';

if (auth_check()) {
    redirect();
}

$errorMessage = '';

if (is_post()) {
    $login    = trim((string)post('username', ''));
    $password = (string)post('password', '');

    $result = auth_attempt($login, $password, 'web');

    if (!empty($result['ok'])) {
        redirect();
    }

    $errorMessage = (string)($result['message'] ?? 'Impossibile effettuare il login.');
}

$pageTitle    = 'Login';
$pageSubtitle = 'Accesso a Turnar';
$activeModule = '';

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
    .login-wrap{display:grid;grid-template-columns:minmax(320px,520px);gap:18px;justify-content:center;}
    .login-card{background:linear-gradient(180deg,rgba(255,255,255,.045),rgba(255,255,255,.02));border:1px solid var(--line);border-radius:24px;box-shadow:0 18px 40px rgba(0,0,0,.18);padding:22px;}
    html[data-theme="light"] .login-card{background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(255,255,255,.92));}
    .login-card h2{margin:0 0 10px;font-size:24px;color:var(--text);}
    .login-sub{margin:0 0 18px;color:var(--muted);line-height:1.6;font-size:14px;}
    .login-form{display:grid;gap:14px;}
    .field{display:flex;flex-direction:column;gap:7px;}
    .field label{font-size:12px;color:var(--muted);font-weight:700;letter-spacing:.03em;}
    .field input{width:100%;padding:12px 13px;border-radius:14px;border:1px solid var(--line);background:var(--bg-3);color:var(--text);outline:none;font-size:14px;-webkit-text-fill-color:var(--text);caret-color:var(--text);box-shadow:none;}
    .field input::placeholder{color:var(--muted);opacity:1;}
    .field input:focus{border-color:rgba(110,168,255,.45);box-shadow:0 0 0 3px rgba(110,168,255,.12);}
    .field input:-webkit-autofill,.field input:-webkit-autofill:hover,.field input:-webkit-autofill:focus,.field input:-webkit-autofill:active{-webkit-text-fill-color:var(--text);transition:background-color 9999s ease-in-out 0s;box-shadow:0 0 0 1000px var(--bg-3) inset;caret-color:var(--text);}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 16px;border:none;border-radius:14px;font-weight:700;cursor:pointer;text-decoration:none;transition:.16s ease;}
    .btn-primary{background:linear-gradient(135deg,var(--primary),var(--primary-2));color:#fff;}
    .btn:hover{transform:translateY(-1px);filter:brightness(1.05);}
    .alert{margin-bottom:16px;padding:14px 16px;border-radius:16px;line-height:1.6;font-size:14px;font-weight:900;}
    .alert-error{background:rgba(239,68,68,.18);border:1px solid rgba(239,68,68,.46);color:#fecaca;box-shadow:0 0 0 1px rgba(239,68,68,.12) inset;}
    html[data-theme="light"] .alert-error{background:#fee2e2;border-color:#fca5a5;color:#7f1d1d;}
    .login-help{margin-top:18px;padding-top:16px;border-top:1px solid var(--line);color:var(--muted);font-size:13px;line-height:1.6;}
    .login-help strong{color:var(--text);}
    @media(max-width:640px){.login-wrap{grid-template-columns:1fr;}.login-card{padding:18px;border-radius:20px;}.login-card h2{font-size:22px;}}
</style>

<div class="login-wrap">
    <section class="login-card">
        <h2>Login Turnar</h2>
        <p class="login-sub">
            Accedi con username oppure email e la tua password.<br>
            Se arrivi dal vecchio sistema, Turnar prova anche la password legacy del dipendente e la aggiorna automaticamente.
        </p>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-error"><?php echo h($errorMessage); ?></div>
        <?php endif; ?>

        <form method="post" class="login-form" autocomplete="off">
            <div class="field">
                <label for="username">Username o email</label>
                <input type="text" id="username" name="username" value="<?php echo h((string)post('username', '')); ?>" placeholder="es. simone.losito" autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" value="" placeholder="Inserisci la password">
            </div>
            <button type="submit" class="btn btn-primary">Entra</button>
        </form>

        <div class="login-help">
            <strong>Nota:</strong> il login usa la tabella <strong>users</strong>.<br>
            Se l’utente è collegato a un record in <strong>dipendenti</strong>, Turnar può accettare anche la vecchia password legacy e convertirla nel nuovo hash.
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>
