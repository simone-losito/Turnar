<?php
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/settings.php';
require_once __DIR__ . '/../../core/mail.php';

require_login();
require_permission('settings.view');

$pageTitle    = 'Email';
$pageSubtitle = 'Configurazione SMTP e invio email';
$activeModule = 'settings';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$success = '';
$error   = '';

// SALVATAGGIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (post('action') === 'save') {

        $data = [
            'smtp_host'       => post('smtp_host'),
            'smtp_port'       => post('smtp_port'),
            'smtp_user'       => post('smtp_user'),
            'smtp_pass'       => post('smtp_pass'),
            'smtp_secure'     => post('smtp_secure'),
            'email_from'      => post('email_from'),
            'email_from_name' => post('email_from_name'),
        ];

        $ok = true;

        foreach ($data as $k => $v) {
            if (!setting_set($k, (string)$v)) {
                $ok = false;
            }
        }

        if ($ok) {
            $success = 'Configurazione salvata correttamente';
        } else {
            $error = 'Errore durante il salvataggio';
        }
    }

    if (post('action') === 'test') {

        $to = trim(post('test_email'));

        if ($to && send_test_email($to)) {
            $success = 'Email inviata correttamente';
        } else {
            $error = 'Invio fallito (controlla configurazione SMTP)';
        }
    }
}

$s = load_settings();

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<div class="content-card">

    <h2>Configurazione Email</h2>

    <?php if($success): ?>
        <div style="color:#34d399; margin-bottom:10px;"><?php echo h($success); ?></div>
    <?php endif; ?>

    <?php if($error): ?>
        <div style="color:#f87171; margin-bottom:10px;"><?php echo h($error); ?></div>
    <?php endif; ?>

    <form method="post">

        <h3>SMTP</h3>

        <input name="smtp_host" placeholder="SMTP Host" value="<?php echo h($s['smtp_host']); ?>"><br><br>

        <input name="smtp_port" placeholder="Porta" value="<?php echo h($s['smtp_port']); ?>"><br><br>

        <input name="smtp_user" placeholder="Username" value="<?php echo h($s['smtp_user']); ?>"><br><br>

        <input name="smtp_pass" placeholder="Password" value="<?php echo h($s['smtp_pass']); ?>"><br><br>

        <select name="smtp_secure">
            <option value="tls" <?php if($s['smtp_secure']==='tls') echo 'selected'; ?>>TLS</option>
            <option value="ssl" <?php if($s['smtp_secure']==='ssl') echo 'selected'; ?>>SSL</option>
            <option value="none" <?php if($s['smtp_secure']==='none') echo 'selected'; ?>>None</option>
        </select>

        <h3>Mittente</h3>

        <input name="email_from" placeholder="Email mittente" value="<?php echo h($s['email_from']); ?>"><br><br>

        <input name="email_from_name" placeholder="Nome mittente" value="<?php echo h($s['email_from_name']); ?>"><br><br>

        <button name="action" value="save" class="nav-link active">Salva configurazione</button>

    </form>

    <hr>

    <h3>Test invio</h3>

    <form method="post">
        <input name="test_email" placeholder="Inserisci email di test">
        <button name="action" value="test" class="nav-link">Invia test</button>
    </form>

</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>