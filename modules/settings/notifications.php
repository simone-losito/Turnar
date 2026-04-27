<?php
// modules/settings/notifications.php

require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/settings.php';

require_login();
require_permission('settings.view');

$pageTitle    = 'Notifiche turni';
$pageSubtitle = 'Configurazione invio automatico notifiche per assegnazioni turno';
$activeModule = 'settings';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$canEditSettings = function_exists('can') ? can('settings.edit') : true;

$successMessage = '';
$errorMessage   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEditSettings) {
        $errorMessage = 'Non hai i permessi per modificare queste impostazioni.';
    } else {
        $assignmentNotifyMode = trim((string)post('assignment_notify_mode', 'app'));

        if (!in_array($assignmentNotifyMode, ['app', 'email', 'both', 'none'], true)) {
            $assignmentNotifyMode = 'app';
        }

        $data = [
            'assignment_notify_mode' => $assignmentNotifyMode,
        ];

        if (settings_save_many($data)) {
            $successMessage = 'Impostazioni notifiche turni salvate correttamente.';
        } else {
            $errorMessage = 'Salvataggio non riuscito.';
        }
    }
}

$current = load_settings();
$notifyMode = trim((string)($current['assignment_notify_mode'] ?? 'app'));
if (!in_array($notifyMode, ['app', 'email', 'both', 'none'], true)) {
    $notifyMode = 'app';
}

$modeLabels = [
    'app'   => 'Solo notifiche app',
    'email' => 'Solo email',
    'both'  => 'App + email',
    'none'  => 'Nessuna notifica automatica',
];

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.notify-shell{
    display:flex;
    flex-direction:column;
    gap:18px;
}

.notify-back-row{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
}

.notify-hero{
    display:grid;
    grid-template-columns:minmax(0, 1.35fr) minmax(300px, .8fr);
    gap:16px;
}

.notify-hero-card,
.notify-side-card,
.notify-form-card,
.notify-preview-card{
    background:linear-gradient(180deg, rgba(255,255,255,.045), rgba(255,255,255,.02));
    border:1px solid var(--line);
    border-radius:24px;
    box-shadow:var(--shadow);
}

.notify-hero-card{
    padding:22px;
    position:relative;
    overflow:hidden;
}

.notify-hero-card::after{
    content:"";
    position:absolute;
    right:-40px;
    bottom:-40px;
    width:180px;
    height:180px;
    border-radius:999px;
    background:radial-gradient(circle, rgba(110,168,255,.18), transparent 68%);
    pointer-events:none;
}

.notify-kicker{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:9px 14px;
    border-radius:999px;
    border:1px solid rgba(110,168,255,.28);
    background:rgba(110,168,255,.10);
    color:#dbeafe;
    font-size:12px;
    font-weight:800;
    letter-spacing:.02em;
}

.notify-title{
    margin:16px 0 0;
    font-size:28px;
    line-height:1.1;
    font-weight:900;
    position:relative;
    z-index:1;
}

.notify-text{
    margin:12px 0 0;
    color:var(--muted);
    font-size:14px;
    line-height:1.65;
    max-width:860px;
    position:relative;
    z-index:1;
}

.notify-tags{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-top:18px;
    position:relative;
    z-index:1;
}

.notify-tag{
    display:inline-flex;
    align-items:center;
    padding:10px 14px;
    border-radius:999px;
    border:1px solid var(--line);
    background:rgba(255,255,255,.05);
    color:var(--text);
    font-size:12px;
    font-weight:700;
}

.notify-side-card{
    padding:18px;
    display:flex;
    flex-direction:column;
    gap:12px;
}

.notify-side-title{
    font-size:13px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.06em;
    color:var(--muted);
}

.notify-side-box{
    padding:14px 16px;
    border-radius:18px;
    border:1px solid var(--line);
    background:rgba(255,255,255,.04);
}

.notify-side-label{
    color:var(--muted);
    font-size:12px;
    margin-bottom:6px;
}

.notify-side-value{
    color:var(--text);
    font-size:15px;
    font-weight:900;
    line-height:1.3;
}

.notify-alert{
    padding:14px 16px;
    border-radius:18px;
    border:1px solid var(--line);
    font-size:14px;
    font-weight:700;
}

.notify-alert.success{
    color:#dcfce7;
    background:rgba(52,211,153,.14);
    border-color:rgba(52,211,153,.28);
}

.notify-alert.error{
    color:#ffe4e6;
    background:rgba(248,113,113,.12);
    border-color:rgba(248,113,113,.28);
}

.notify-main-grid{
    display:grid;
    grid-template-columns:minmax(0, 1.45fr) minmax(300px, .85fr);
    gap:16px;
    align-items:start;
}

.notify-form-card,
.notify-preview-card{
    padding:20px;
}

.notify-form-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:18px;
}

.notify-form-title{
    margin:0;
    font-size:22px;
    line-height:1.1;
}

.notify-form-subtitle{
    margin:8px 0 0;
    color:var(--muted);
    font-size:14px;
}

.notify-status-pill{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:8px 12px;
    border-radius:999px;
    border:1px solid var(--line);
    font-size:11px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
    white-space:nowrap;
    color:#dcfce7;
    background:rgba(52,211,153,.14);
    border-color:rgba(52,211,153,.28);
}

.notify-block{
    border:1px solid var(--line);
    background:rgba(255,255,255,.03);
    border-radius:22px;
    padding:18px;
}

.notify-block + .notify-block{
    margin-top:16px;
}

.notify-block-top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:16px;
}

.notify-block-title{
    margin:0;
    font-size:18px;
    font-weight:900;
    line-height:1.2;
}

.notify-block-text{
    margin:8px 0 0;
    color:var(--muted);
    font-size:13px;
    line-height:1.6;
}

.notify-mini-pill{
    display:inline-flex;
    align-items:center;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid var(--line);
    background:rgba(255,255,255,.04);
    color:var(--muted);
    font-size:11px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}

.notify-options{
    display:grid;
    gap:12px;
}

.notify-radio{
    display:flex;
    align-items:flex-start;
    gap:12px;
    padding:14px 16px;
    border-radius:18px;
    border:1px solid var(--line);
    background:rgba(255,255,255,.03);
}

.notify-radio input{
    margin-top:3px;
}

.notify-radio strong{
    display:block;
    font-size:15px;
    margin-bottom:4px;
}

.notify-radio small{
    display:block;
    color:var(--muted);
    font-size:12px;
    line-height:1.6;
}

.notify-preview-title{
    margin:0 0 12px;
    font-size:18px;
    font-weight:900;
}

.notify-preview-box{
    border:1px solid var(--line);
    border-radius:22px;
    padding:16px;
    background:rgba(255,255,255,.03);
}

.notify-mode-card{
    padding:14px 16px;
    border-radius:18px;
    border:1px solid var(--line);
    background:rgba(255,255,255,.04);
    display:grid;
    gap:6px;
}

.notify-mode-label{
    color:var(--muted);
    font-size:11px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.05em;
}

.notify-mode-value{
    color:var(--text);
    font-size:15px;
    font-weight:900;
    line-height:1.4;
}

.notify-form-actions{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    margin-top:18px;
}

.notify-actions-left,
.notify-actions-right{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
}

.notify-note{
    color:var(--muted);
    font-size:12px;
    font-weight:700;
}

@media (max-width: 1080px){
    .notify-hero,
    .notify-main-grid{
        grid-template-columns:1fr;
    }
}

@media (max-width: 720px){
    .notify-title{
        font-size:24px;
    }

    .notify-form-card,
    .notify-preview-card{
        padding:16px;
    }

    .notify-block{
        padding:16px;
    }

    .notify-form-actions{
        align-items:stretch;
    }

    .notify-actions-left,
    .notify-actions-right{
        width:100%;
    }
}
</style>

<div class="content-card">
    <div class="notify-shell">

        <div class="notify-back-row">
            <a href="<?php echo h(app_url('modules/settings/index.php')); ?>" class="nav-link">← Torna a Impostazioni</a>

            <div class="page-head-right">
                <span class="soft-pill">Sezione: Notifiche turni</span>
                <span class="soft-pill">Default: app</span>
            </div>
        </div>

        <section class="notify-hero">
            <div class="notify-hero-card">
                <span class="notify-kicker">Comunicazioni automatiche</span>

                <h2 class="notify-title">Notifiche automatiche per i turni</h2>

                <p class="notify-text">
                    Qui decidi come Turnar deve avvisare i dipendenti quando viene assegnato un nuovo turno.
                    La modalità può essere app, email, entrambe oppure nessuna.
                    La scelta predefinita del sistema resta centrata sulle notifiche app.
                </p>

                <div class="notify-tags">
                    <span class="notify-tag">Default su app</span>
                    <span class="notify-tag">Email opzionale</span>
                    <span class="notify-tag">Controllo da pannello</span>
                    <span class="notify-tag">Pronto per evoluzione PWA</span>
                </div>
            </div>

            <aside class="notify-side-card">
                <div class="notify-side-title">Riepilogo attuale</div>

                <div class="notify-side-box">
                    <div class="notify-side-label">Modalità attiva</div>
                    <div class="notify-side-value"><?php echo h($modeLabels[$notifyMode] ?? 'Solo notifiche app'); ?></div>
                </div>

                <div class="notify-side-box">
                    <div class="notify-side-label">Uso consigliato</div>
                    <div class="notify-side-value">Solo notifiche app</div>
                </div>

                <div class="notify-side-box">
                    <div class="notify-side-label">Effetto</div>
                    <div class="notify-side-value">Nuovi turni notificati automaticamente ai dipendenti</div>
                </div>
            </aside>
        </section>

        <?php if ($successMessage !== ''): ?>
            <div class="notify-alert success"><?php echo h($successMessage); ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="notify-alert error"><?php echo h($errorMessage); ?></div>
        <?php endif; ?>

        <div class="notify-main-grid">
            <section class="notify-form-card">
                <div class="notify-form-head">
                    <div>
                        <h3 class="notify-form-title">Configura la modalità di invio</h3>
                        <p class="notify-form-subtitle">
                            Scegli una sola regola generale per i nuovi turni assegnati dal gestionale.
                        </p>
                    </div>

                    <span class="notify-status-pill">Sezione attiva</span>
                </div>

                <form method="post" action="">
                    <div class="notify-block">
                        <div class="notify-block-top">
                            <div>
                                <h4 class="notify-block-title">Modalità notifica turni</h4>
                                <p class="notify-block-text">
                                    Questa impostazione viene letta automaticamente dal salvataggio turni.
                                </p>
                            </div>
                            <span class="notify-mini-pill">Controllo centrale</span>
                        </div>

                        <div class="notify-options">
                            <label class="notify-radio">
                                <input type="radio" name="assignment_notify_mode" value="app" <?php echo $notifyMode === 'app' ? 'checked' : ''; ?>>
                                <span>
                                    <strong>Solo notifiche app</strong>
                                    <small>Modalità consigliata. Il dipendente riceve la notifica dentro l’app Turnar.</small>
                                </span>
                            </label>

                            <label class="notify-radio">
                                <input type="radio" name="assignment_notify_mode" value="email" <?php echo $notifyMode === 'email' ? 'checked' : ''; ?>>
                                <span>
                                    <strong>Solo email</strong>
                                    <small>Invia solo una mail all’indirizzo associato al dipendente.</small>
                                </span>
                            </label>

                            <label class="notify-radio">
                                <input type="radio" name="assignment_notify_mode" value="both" <?php echo $notifyMode === 'both' ? 'checked' : ''; ?>>
                                <span>
                                    <strong>App + email</strong>
                                    <small>Invia sia la notifica interna app sia l’email.</small>
                                </span>
                            </label>

                            <label class="notify-radio">
                                <input type="radio" name="assignment_notify_mode" value="none" <?php echo $notifyMode === 'none' ? 'checked' : ''; ?>>
                                <span>
                                    <strong>Nessuna notifica automatica</strong>
                                    <small>Il turno viene salvato senza inviare alcun avviso al dipendente.</small>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="notify-form-actions">
                        <div class="notify-actions-left">
                            <a href="<?php echo h(app_url('modules/settings/index.php')); ?>" class="nav-link">Annulla</a>
                        </div>

                        <div class="notify-actions-right">
                            <?php if ($canEditSettings): ?>
                                <button type="submit" class="nav-link active">Salva impostazioni notifiche</button>
                            <?php else: ?>
                                <button type="button" class="nav-link" disabled style="opacity:.65; cursor:not-allowed;">Modifica non consentita</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="notify-note">
                        Queste impostazioni controllano l’invio automatico quando un turno viene salvato dal gestionale.
                    </div>
                </form>
            </section>

            <aside class="notify-preview-card">
                <h3 class="notify-preview-title">Anteprima comportamento</h3>

                <div class="notify-preview-box" style="display:grid;gap:12px;">
                    <div class="notify-mode-card">
                        <div class="notify-mode-label">Modalità selezionata</div>
                        <div class="notify-mode-value"><?php echo h($modeLabels[$notifyMode] ?? 'Solo notifiche app'); ?></div>
                    </div>

                    <div class="notify-mode-card">
                        <div class="notify-mode-label">Quando viene usata</div>
                        <div class="notify-mode-value">Subito dopo il salvataggio di un nuovo turno assegnato.</div>
                    </div>

                    <div class="notify-mode-card">
                        <div class="notify-mode-label">Obiettivo</div>
                        <div class="notify-mode-value">Far vedere subito al dipendente che c’è un nuovo turno o una modifica.</div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>