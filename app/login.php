<?php
require_once __DIR__ . '/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    $res = auth_attempt($login, $password, 'app');

    if (!empty($res['ok'])) {
        header('Location: index.php');
        exit;
    } else {
        $error = $res['message'] ?? 'Errore login';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Turnar App</title>

<link rel="manifest" href="manifest.php">
<meta name="theme-color" content="#6ea8ff">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Turnar">
<link rel="apple-touch-icon" href="icon.php?size=180">

<style>
body{
    margin:0;
    font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
    background:
        radial-gradient(circle at top left, rgba(110,168,255,.18), transparent 28%),
        radial-gradient(circle at top right, rgba(139,92,246,.14), transparent 24%),
        linear-gradient(180deg, #0b1226, #050816);
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
    min-height:100vh;
    padding:18px;
}

.card{
    background:linear-gradient(180deg, rgba(255,255,255,.045), rgba(255,255,255,.02));
    border:1px solid rgba(255,255,255,.10);
    padding:24px;
    border-radius:24px;
    width:100%;
    max-width:360px;
    box-shadow:0 18px 40px rgba(0,0,0,.35);
}

.logo{
    width:68px;
    height:68px;
    border-radius:22px;
    margin-bottom:16px;
    overflow:hidden;
    background:linear-gradient(135deg, #6ea8ff, #8b5cf6);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:28px;
    font-weight:900;
}

h1{
    margin:0 0 8px;
    font-size:28px;
}

.sub{
    color:#aab8d3;
    font-size:14px;
    margin-bottom:18px;
}

.error{
    color:#fecaca;
    background:rgba(248,113,113,.12);
    border:1px solid rgba(248,113,113,.28);
    border-radius:16px;
    padding:12px 14px;
    margin-bottom:14px;
    font-size:14px;
    font-weight:700;
}

input{
    width:100%;
    padding:13px 14px;
    margin-bottom:12px;
    border-radius:14px;
    border:1px solid rgba(255,255,255,.10);
    background:#121a31;
    color:#fff;
    outline:none;
    font-size:14px;
}

input:focus{
    border-color:rgba(110,168,255,.45);
    box-shadow:0 0 0 3px rgba(110,168,255,.12);
}

button{
    width:100%;
    padding:13px 14px;
    border:none;
    border-radius:14px;
    background:linear-gradient(135deg, #6ea8ff, #8b5cf6);
    color:#fff;
    font-size:14px;
    font-weight:900;
    cursor:pointer;
}

.install-tip{
    margin-top:14px;
    color:#aab8d3;
    font-size:12px;
    line-height:1.55;
}
</style>
</head>
<body>

<div class="card">
    <div class="logo">T</div>

    <h1>Turnar</h1>
    <div class="sub">Accedi alla tua area turni mobile</div>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="post">
        <input name="login" placeholder="Username o email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Accedi</button>
    </form>

    <div class="install-tip">
        Su Android puoi installare l’app dal browser.  
        Su iPhone usa “Condividi → Aggiungi alla schermata Home”.
    </div>
</div>

<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('./service-worker.js').catch(function () {});
    });
}
</script>

</body>
</html>