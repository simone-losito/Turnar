<?php
require_once __DIR__ . '/../../core/settings.php';

if (auth_check()) {
    redirect();
}

$errorMessage = '';

if (is_post()) {
    if (post('theme_action', '') === 'toggle') {
        $current = app_theme_mode();
        $next = $current === 'dark' ? 'light' : 'dark';
        setting_set('theme_mode', $next);
        header('Location: ' . app_url('modules/auth/login.php'));
        exit;
    }

    $result = auth_attempt(trim((string)post('username', '')), (string)post('password', ''), 'web');
    if (!empty($result['ok'])) {
        redirect();
    }
    $errorMessage = (string)($result['message'] ?? 'Impossibile effettuare il login.');
}

$theme = app_theme_mode();
if (!in_array($theme, ['dark', 'light'], true)) {
    $theme = 'dark';
}

$companyLogo = function_exists('app_company_logo') ? trim((string)app_company_logo()) : '';
$logo = $companyLogo !== '' ? app_url($companyLogo) : app_url('assets/img/turnar-logo.svg');
$version = function_exists('app_version') ? app_version() : '1.0.0';
?>
<!DOCTYPE html>
<html lang="it" data-theme="<?php echo htmlspecialchars($theme, ENT_QUOTES, 'UTF-8'); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login · Turnar</title>
<link rel="stylesheet" href="<?php echo app_url('assets/css/turnar.css'); ?>">
<link rel="stylesheet" href="<?php echo app_url('assets/css/turnar-dark-tune.css'); ?>">
<style>
*{box-sizing:border-box}
body{min-height:100vh;margin:0;display:flex;align-items:center;justify-content:center;overflow:hidden;background:radial-gradient(circle at 18% 30%, rgba(34,211,238,.22), transparent 32%),radial-gradient(circle at 82% 70%, rgba(249,115,22,.20), transparent 30%),radial-gradient(circle at 50% 50%, rgba(139,92,246,.20), transparent 34%),linear-gradient(135deg, var(--bg-2), var(--bg-1) 55%, #070817);color:var(--text);font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
html[data-theme="light"] body{background:radial-gradient(circle at 18% 30%, rgba(59,130,246,.28), transparent 32%),radial-gradient(circle at 82% 70%, rgba(249,115,22,.18), transparent 30%),linear-gradient(135deg,#eaf1ff,#ffffff 52%,#e8efff);}
.login-stage{width:min(94vw,560px);position:relative;z-index:2}
.login-card{position:relative;padding:30px 30px 24px;border-radius:30px;border:1px solid rgba(110,168,255,.48);background:linear-gradient(180deg, rgba(15,27,62,.82), rgba(7,11,27,.88));box-shadow:0 26px 80px rgba(0,0,0,.48),0 0 45px rgba(110,168,255,.18);backdrop-filter:blur(18px);overflow:hidden;}
html[data-theme="light"] .login-card{background:rgba(255,255,255,.84);box-shadow:0 26px 70px rgba(38,64,120,.24),0 0 45px rgba(110,168,255,.18)}
.login-card:before{content:"";position:absolute;inset:-2px;background:linear-gradient(120deg,transparent,rgba(110,168,255,.35),rgba(139,92,246,.34),rgba(249,115,22,.32),transparent);opacity:.65;filter:blur(22px);z-index:-1;animation:glow 3.2s linear infinite}
.logo-wrap{display:flex;justify-content:center;margin-bottom:12px}.logo-wrap img{width:86px;height:86px;object-fit:contain;filter:drop-shadow(0 0 22px rgba(110,168,255,.65))}.logo-fallback{display:none;width:86px;height:86px;border-radius:24px;align-items:center;justify-content:center;background:linear-gradient(135deg,#22d3ee,#6ea8ff,#8b5cf6);color:#fff;font-size:42px;font-weight:1000;box-shadow:0 0 28px rgba(110,168,255,.48)}
.login-title{text-align:center;font-size:42px;line-height:1;letter-spacing:.12em;font-weight:1000;margin:0;background:linear-gradient(90deg,#22d3ee,#6ea8ff,#8b5cf6,#f97316,#22d3ee);background-size:320% 100%;-webkit-background-clip:text;-webkit-text-fill-color:transparent;text-shadow:0 0 28px rgba(110,168,255,.45);animation:shine 2.4s linear infinite, pulse 1.4s ease-in-out infinite;}
.login-sub{text-align:center;margin:8px 0 22px;color:var(--muted);font-size:13px;font-weight:750}.login-form{display:grid;gap:13px}.login-field{position:relative}.login-field span{position:absolute;left:14px;top:50%;transform:translateY(-50%);opacity:.82}.login-field input{width:100%;min-height:48px;padding:12px 48px 12px 42px;border-radius:16px;border:1px solid rgba(110,168,255,.45);background:rgba(3,8,26,.66);color:var(--text);outline:none;font-size:14px;font-weight:750;}html[data-theme="light"] .login-field input{background:#f7faff;color:#10203e}.login-field input:focus{border-color:#6ea8ff;box-shadow:0 0 0 4px rgba(110,168,255,.16),0 0 22px rgba(110,168,255,.22)}
.password-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);width:34px;height:34px;border-radius:999px;border:1px solid rgba(110,168,255,.35);background:rgba(110,168,255,.10);color:var(--text);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px}.password-toggle:hover{filter:brightness(1.15)}
.login-btn{min-height:52px;border:0;border-radius:999px;cursor:pointer;color:#071126;font-size:15px;letter-spacing:.18em;font-weight:1000;background:linear-gradient(90deg,#22d3ee,#6ea8ff,#8b5cf6,#f97316);box-shadow:0 15px 34px rgba(110,168,255,.28),0 0 30px rgba(249,115,22,.16);transition:.16s ease;}.login-btn:hover{transform:translateY(-2px) scale(1.01);filter:saturate(1.15) brightness(1.08)}
.login-links{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:14px;color:var(--muted);font-size:12px}.forgot-link{color:#9ec5ff;text-decoration:none;font-weight:850}.forgot-link:hover{text-decoration:underline}.version-pill{border:1px solid rgba(110,168,255,.28);border-radius:999px;padding:5px 9px;background:rgba(110,168,255,.08);font-weight:850}.login-note{text-align:center;margin:16px 0 0;color:var(--muted);font-size:12px;line-height:1.45}.login-note strong{color:var(--text)}
.alert{margin-bottom:14px;padding:12px 14px;border-radius:16px;background:rgba(248,113,113,.18);border:1px solid rgba(248,113,113,.42);color:#fecdd3;font-weight:900}html[data-theme="light"] .alert{background:#fee2e2;color:#7f1d1d}.theme-form{position:fixed;right:18px;top:18px;z-index:4;margin:0}.theme-btn{width:46px;height:46px;border-radius:999px;border:1px solid rgba(110,168,255,.45);background:rgba(10,19,41,.72);color:var(--text);cursor:pointer;box-shadow:0 12px 28px rgba(0,0,0,.24);font-size:18px}html[data-theme="light"] .theme-btn{background:rgba(255,255,255,.82);color:#10203e}
.orb{position:fixed;border-radius:999px;filter:blur(2px);opacity:.75;animation:float 6s ease-in-out infinite;z-index:1}.orb.one{width:120px;height:120px;background:rgba(34,211,238,.18);left:18%;top:24%}.orb.two{width:150px;height:150px;background:rgba(139,92,246,.16);right:18%;bottom:20%;animation-delay:-2s}.orb.three{width:90px;height:90px;background:rgba(249,115,22,.14);right:28%;top:22%;animation-delay:-4s}
@keyframes shine{0%{background-position:0 0}100%{background-position:320% 0}}@keyframes pulse{0%,100%{filter:drop-shadow(0 0 8px rgba(110,168,255,.35))}50%{filter:drop-shadow(0 0 22px rgba(249,115,22,.35))}}@keyframes glow{0%,100%{opacity:.55}50%{opacity:.9}}@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-18px)}}@media(max-width:640px){.login-card{padding:24px 18px}.login-title{font-size:32px}.logo-wrap img,.logo-fallback{width:72px;height:72px}.login-links{flex-direction:column}}
</style>
</head>
<body>
<form class="theme-form" method="post"><input type="hidden" name="theme_action" value="toggle"><button class="theme-btn" type="submit"><?php echo $theme === 'dark' ? '☀️' : '🌙'; ?></button></form>
<div class="orb one"></div><div class="orb two"></div><div class="orb three"></div>
<main class="login-stage"><section class="login-card">
<div class="logo-wrap"><img src="<?php echo htmlspecialchars($logo, ENT_QUOTES, 'UTF-8'); ?>" alt="Turnar" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"><div class="logo-fallback">T</div></div>
<h1 class="login-title">TURNAR</h1><p class="login-sub">Accesso gestionale · turni, personale e operatività</p>
<?php if ($errorMessage !== ''): ?><div class="alert"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<form method="post" class="login-form">
<label class="login-field"><span>👤</span><input name="username" placeholder="Username o email" autocomplete="username" required></label>
<label class="login-field"><span>🔐</span><input id="passwordInput" type="password" name="password" placeholder="Password" autocomplete="current-password" required><button class="password-toggle" type="button" onclick="togglePassword()" id="passwordToggle">👁️</button></label>
<button class="login-btn" type="submit">ENTRA</button>
</form>
<div class="login-links"><a class="forgot-link" href="#" onclick="alert('Funzione password dimenticata in preparazione.');return false;">Password dimenticata?</a><span class="version-pill">Ver. <?php echo htmlspecialchars($version, ENT_QUOTES, 'UTF-8'); ?></span></div>
<p class="login-note"><strong>Turnar</strong> · powered by <strong>Simoncino Projects</strong></p>
</section></main>
<script>
function togglePassword(){var i=document.getElementById('passwordInput');var b=document.getElementById('passwordToggle');if(!i||!b)return;if(i.type==='password'){i.type='text';b.textContent='🙈';}else{i.type='password';b.textContent='👁️';}}
</script>
</body></html>
