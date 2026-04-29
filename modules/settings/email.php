<?php
// modules/settings/email.php

require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/settings.php';
require_once __DIR__ . '/../../core/mail.php';

require_login();
require_permission('settings.view');

$pageTitle    = 'Email SMTP';
$pageSubtitle = 'Configurazione posta in uscita e test invio email';
$activeModule = 'settings';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$success = '';
$error   = '';
$canEditSettings = function_exists('can') ? can('settings.edit') : true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEditSettings) {
        $error = 'Non hai i permessi per modificare le impostazioni email.';
    } elseif (post('action') === 'save') {
        $data = [
            'smtp_host'       => trim((string)post('smtp_host', '')),
            'smtp_port'       => trim((string)post('smtp_port', '587')),
            'smtp_user'       => trim((string)post('smtp_user', '')),
            'smtp_pass'       => (string)post('smtp_pass', ''),
            'smtp_secure'     => trim((string)post('smtp_secure', 'tls')),
            'email_from'      => trim((string)post('email_from', '')),
            'email_from_name' => trim((string)post('email_from_name', 'Turnar')),
            'email_reply_to'  => trim((string)post('email_reply_to', '')),
            'email_inbox'     => trim((string)post('email_inbox', '')),
        ];

        if (!in_array($data['smtp_secure'], ['tls','ssl','none'], true)) {
            $data['smtp_secure'] = 'tls';
        }

        if ($data['smtp_host'] === '') {
            $error = 'Inserisci host SMTP.';
        } elseif ($data['email_from'] !== '' && !filter_var($data['email_from'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Email mittente non valida.';
        } elseif ($data['email_reply_to'] !== '' && !filter_var($data['email_reply_to'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Reply-to non valido.';
        } elseif ($data['email_inbox'] !== '' && !filter_var($data['email_inbox'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Email ingresso non valida.';
        } else {
            $ok = true;
            foreach ($data as $k => $v) {
                if (!setting_set($k, (string)$v)) $ok = false;
            }
            $success = $ok ? 'Configurazione email salvata correttamente.' : '';
            $error = $ok ? $error : 'Errore durante il salvataggio.';
        }
    } elseif (post('action') === 'test') {
        $to = trim((string)post('test_email', ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $error = 'Inserisci una email di test valida.';
        } elseif (function_exists('send_test_email') && send_test_email($to)) {
            $success = 'Email di test inviata correttamente.';
        } else {
            $error = 'Invio fallito. Controlla configurazione SMTP e log server.';
        }
    }
}

$s = load_settings();

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.email-settings-shell{display:grid;gap:12px}.email-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(280px,.75fr);gap:12px;align-items:start}.email-card{border:1px solid var(--line);border-radius:18px;background:linear-gradient(180deg,color-mix(in srgb,var(--bg-4) 78%,transparent),color-mix(in srgb,var(--bg-3) 88%,transparent));box-shadow:var(--shadow);padding:14px}.email-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;margin-bottom:12px}.email-title{margin:0;font-size:18px;font-weight:950}.email-text{margin:5px 0 0;color:var(--muted);font-size:12px;line-height:1.45}.email-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.email-field{display:flex;flex-direction:column;gap:5px}.email-field.full{grid-column:1/-1}.email-label{font-size:11px;font-weight:900;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}.email-help{font-size:10.5px;color:var(--muted);line-height:1.35}.email-actions{display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-top:12px;padding-top:12px;border-top:1px solid var(--line-soft)}.email-alert{padding:10px 12px;border-radius:16px;margin-bottom:10px;font-size:12px;font-weight:900}.email-alert.success{background:rgba(34,197,94,.16);border:1px solid rgba(34,197,94,.35);color:#dcfce7}.email-alert.error{background:rgba(248,113,113,.16);border:1px solid rgba(248,113,113,.35);color:#fecdd3}html[data-theme="light"] .email-alert.success{color:#14532d}html[data-theme="light"] .email-alert.error{color:#7f1d1d}.email-kpi{display:grid;gap:8px}.email-kpi-item{padding:10px;border-radius:14px;border:1px solid var(--line-soft);background:color-mix(in srgb,var(--bg-4) 62%,transparent)}.email-kpi-label{font-size:10.5px;color:var(--muted);font-weight:900;text-transform:uppercase}.email-kpi-value{margin-top:4px;font-size:13px;color:var(--text);font-weight:900;word-break:break-word}.password-wrap{position:relative}.password-wrap input{padding-right:42px!important}.password-eye{position:absolute;right:7px;top:50%;transform:translateY(-50%);width:30px;height:30px;border-radius:999px;border:1px solid var(--line);background:color-mix(in srgb,var(--bg-4) 70%,transparent);cursor:pointer}@media(max-width:980px){.email-grid,.email-form-grid{grid-template-columns:1fr}.email-field.full{grid-column:auto}}
</style>

<div class="email-settings-shell">
    <div class="toolbar">
        <div class="toolbar-left">
            <a href="<?php echo h(app_url('modules/settings/index.php')); ?>" class="btn btn-ghost">← Torna a Impostazioni</a>
        </div>
        <div class="toolbar-right"><span class="soft-pill">SMTP / posta</span></div>
    </div>

    <?php if($success): ?><div class="email-alert success"><?php echo h($success); ?></div><?php endif; ?>
    <?php if($error): ?><div class="email-alert error"><?php echo h($error); ?></div><?php endif; ?>

    <div class="email-grid">
        <section class="email-card">
            <div class="email-head">
                <div>
                    <h2 class="email-title">Configurazione SMTP</h2>
                    <p class="email-text">Imposta la posta in uscita usata da notifiche, comunicazioni e reset password futuri.</p>
                </div>
                <span class="settings-status-pill success">Pagina attiva</span>
            </div>

            <form method="post">
                <input type="hidden" name="action" value="save">
                <div class="email-form-grid">
                    <div class="email-field full">
                        <label class="email-label" for="smtp_host">Host SMTP</label>
                        <input id="smtp_host" name="smtp_host" placeholder="smtp.example.com" value="<?php echo h($s['smtp_host'] ?? ''); ?>" required>
                    </div>
                    <div class="email-field">
                        <label class="email-label" for="smtp_port">Porta</label>
                        <input id="smtp_port" name="smtp_port" inputmode="numeric" placeholder="587" value="<?php echo h($s['smtp_port'] ?? '587'); ?>">
                    </div>
                    <div class="email-field">
                        <label class="email-label" for="smtp_secure">Sicurezza</label>
                        <select id="smtp_secure" name="smtp_secure">
                            <?php $secure = (string)($s['smtp_secure'] ?? 'tls'); ?>
                            <option value="tls" <?php echo $secure==='tls'?'selected':''; ?>>TLS</option>
                            <option value="ssl" <?php echo $secure==='ssl'?'selected':''; ?>>SSL</option>
                            <option value="none" <?php echo $secure==='none'?'selected':''; ?>>Nessuna</option>
                        </select>
                    </div>
                    <div class="email-field">
                        <label class="email-label" for="smtp_user">Username SMTP</label>
                        <input id="smtp_user" name="smtp_user" autocomplete="username" value="<?php echo h($s['smtp_user'] ?? ''); ?>">
                    </div>
                    <div class="email-field">
                        <label class="email-label" for="smtp_pass">Password SMTP</label>
                        <div class="password-wrap">
                            <input id="smtp_pass" type="password" name="smtp_pass" autocomplete="current-password" value="<?php echo h($s['smtp_pass'] ?? ''); ?>">
                            <button type="button" class="password-eye" onclick="toggleSmtpPassword()">👁️</button>
                        </div>
                    </div>
                    <div class="email-field">
                        <label class="email-label" for="email_from">Email mittente</label>
                        <input id="email_from" type="email" name="email_from" placeholder="noreply@azienda.it" value="<?php echo h($s['email_from'] ?? ''); ?>">
                    </div>
                    <div class="email-field">
                        <label class="email-label" for="email_from_name">Nome mittente</label>
                        <input id="email_from_name" name="email_from_name" placeholder="Turnar" value="<?php echo h($s['email_from_name'] ?? 'Turnar'); ?>">
                    </div>
                    <div class="email-field">
                        <label class="email-label" for="email_reply_to">Reply-to</label>
                        <input id="email_reply_to" type="email" name="email_reply_to" placeholder="ufficio@azienda.it" value="<?php echo h($s['email_reply_to'] ?? ''); ?>">
                    </div>
                    <div class="email-field">
                        <label class="email-label" for="email_inbox">Email ingresso</label>
                        <input id="email_inbox" type="email" name="email_inbox" placeholder="inbox@azienda.it" value="<?php echo h($s['email_inbox'] ?? ''); ?>">
                    </div>
                </div>
                <div class="email-actions">
                    <span class="email-help">La password resta salvata nelle impostazioni locali del software.</span>
                    <?php if ($canEditSettings): ?><button class="btn btn-primary" type="submit">Salva configurazione</button><?php endif; ?>
                </div>
            </form>
        </section>

        <aside class="email-card">
            <div class="email-head"><div><h2 class="email-title">Test e riepilogo</h2><p class="email-text">Verifica rapidamente la configurazione corrente.</p></div></div>
            <div class="email-kpi">
                <div class="email-kpi-item"><div class="email-kpi-label">Host</div><div class="email-kpi-value"><?php echo h($s['smtp_host'] ?? '-'); ?></div></div>
                <div class="email-kpi-item"><div class="email-kpi-label">Mittente</div><div class="email-kpi-value"><?php echo h($s['email_from'] ?? '-'); ?></div></div>
                <div class="email-kpi-item"><div class="email-kpi-label">Sicurezza</div><div class="email-kpi-value"><?php echo h($s['smtp_secure'] ?? 'tls'); ?></div></div>
            </div>
            <form method="post" style="margin-top:12px;display:grid;gap:8px;">
                <input type="hidden" name="action" value="test">
                <label class="email-label" for="test_email">Email di test</label>
                <input id="test_email" type="email" name="test_email" placeholder="tu@email.it">
                <button class="btn btn-secondary" type="submit">Invia test</button>
            </form>
        </aside>
    </div>
</div>

<script>
function toggleSmtpPassword(){
    const i=document.getElementById('smtp_pass');
    if(!i)return;
    i.type = i.type === 'password' ? 'text' : 'password';
}
</script>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>
