<?php
// modules/settings/general.php

require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/settings.php';

require_login();
require_permission('settings.view');

$pageTitle    = 'Impostazioni generali';
$pageSubtitle = 'Configurazione base del software Turnar';
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
        $errorMessage = 'Non hai i permessi per modificare le impostazioni.';
    } else {
        $data = [
            'app_name'           => trim((string)post('app_name', '')),
            'app_tagline'        => trim((string)post('app_tagline', '')),
            'app_version_visual' => trim((string)post('app_version_visual', '')),
            'app_language'       => trim((string)post('app_language', 'it')),
            'app_timezone'       => trim((string)post('app_timezone', 'Europe/Rome')),
            'date_format'        => trim((string)post('date_format', 'd/m/Y')),
            'time_format'        => trim((string)post('time_format', 'H:i')),
            'reference_year'     => trim((string)post('reference_year', date('Y'))),
            'custom_footer_text' => trim((string)post('custom_footer_text', '')),
        ];

        if ($data['app_name'] === '') {
            $errorMessage = 'Il nome software è obbligatorio.';
        } elseif (!preg_match('/^\d{4}$/', $data['reference_year'])) {
            $errorMessage = 'L’anno di riferimento deve essere composto da 4 cifre.';
        } else {
            if (settings_save_many($data)) {
                $successMessage = 'Impostazioni generali salvate correttamente.';
            } else {
                $errorMessage = 'Salvataggio non riuscito. Controlla tabella settings e configurazione database.';
            }
        }
    }
}

$current = load_settings();

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.settings-general-shell{
    display:flex;
    flex-direction:column;
    gap:18px;
}

.settings-back-row{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
}

.settings-page-hero{
    display:grid;
    grid-template-columns:minmax(0, 1.35fr) minmax(280px, .8fr);
    gap:16px;
}

.settings-page-hero-card,
.settings-page-side-card,
.settings-form-card{
    background:linear-gradient(180deg, rgba(255,255,255,.045), rgba(255,255,255,.02));
    border:1px solid var(--line);
    border-radius:24px;
    box-shadow:var(--shadow);
}

.settings-page-hero-card{
    padding:22px;
    position:relative;
    overflow:hidden;
}

.settings-page-hero-card::after{
    content:"";
    position:absolute;
    right:-40px;
    bottom:-40px;
    width:180px;
    height:180px;
    border-radius:999px;
    background:radial-gradient(circle, rgba(139,92,246,.18), transparent 68%);
    pointer-events:none;
}

.settings-page-kicker{
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

.settings-page-title{
    margin:16px 0 0;
    font-size:28px;
    line-height:1.1;
    font-weight:900;
    position:relative;
    z-index:1;
}

.settings-page-text{
    margin:12px 0 0;
    color:var(--muted);
    font-size:14px;
    line-height:1.65;
    max-width:860px;
    position:relative;
    z-index:1;
}

.settings-page-tags{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-top:18px;
    position:relative;
    z-index:1;
}

.settings-page-tag{
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

.settings-page-side-card{
    padding:18px;
    display:flex;
    flex-direction:column;
    gap:12px;
}

.settings-side-title{
    font-size:13px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.06em;
    color:var(--muted);
}

.settings-side-box{
    padding:14px 16px;
    border-radius:18px;
    border:1px solid var(--line);
    background:rgba(255,255,255,.04);
}

.settings-side-label{
    color:var(--muted);
    font-size:12px;
    margin-bottom:6px;
}

.settings-side-value{
    color:var(--text);
    font-size:15px;
    font-weight:900;
    line-height:1.3;
}

.settings-form-card{
    padding:20px;
}

.settings-form-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:18px;
}

.settings-form-title{
    margin:0;
    font-size:22px;
    line-height:1.1;
}

.settings-form-subtitle{
    margin:8px 0 0;
    color:var(--muted);
    font-size:14px;
}

.settings-status-pill{
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
}

.settings-status-pill.success{
    color:#dcfce7;
    background:rgba(52,211,153,.14);
    border-color:rgba(52,211,153,.28);
}

.settings-status-pill.info{
    color:#dbeafe;
    background:rgba(59,130,246,.14);
    border-color:rgba(59,130,246,.28);
}

.settings-alert{
    margin-bottom:16px;
    padding:14px 16px;
    border-radius:18px;
    border:1px solid var(--line);
    font-size:14px;
    font-weight:700;
}

.settings-alert.success{
    color:#dcfce7;
    background:rgba(52,211,153,.14);
    border-color:rgba(52,211,153,.28);
}

.settings-alert.error{
    color:#ffe4e6;
    background:rgba(248,113,113,.12);
    border-color:rgba(248,113,113,.28);
}

.settings-section-block{
    border:1px solid var(--line);
    background:rgba(255,255,255,.03);
    border-radius:22px;
    padding:18px;
}

.settings-section-block + .settings-section-block{
    margin-top:16px;
}

.settings-section-top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:16px;
}

.settings-section-title{
    margin:0;
    font-size:18px;
    font-weight:900;
    line-height:1.2;
}

.settings-section-text{
    margin:8px 0 0;
    color:var(--muted);
    font-size:13px;
    line-height:1.6;
}

.settings-mini-pill{
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

.settings-form-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:16px;
}

.settings-field{
    display:flex;
    flex-direction:column;
    gap:8px;
}

.settings-field.full{
    grid-column:1 / -1;
}

.settings-label{
    font-size:13px;
    font-weight:800;
    color:var(--text);
}

.settings-help{
    color:var(--muted);
    font-size:12px;
    line-height:1.5;
    margin-top:-2px;
}

.settings-input,
.settings-select,
.settings-textarea{
    width:100%;
    min-height:46px;
    padding:12px 14px;
    border-radius:16px;
    border:1px solid var(--line);
    background:var(--bg-3);
    color:var(--text);
    outline:none;
    font-size:14px;
}

.settings-textarea{
    min-height:110px;
    resize:vertical;
    border-radius:18px;
}

.settings-input:focus,
.settings-select:focus,
.settings-textarea:focus{
    border-color:rgba(110,168,255,.45);
    box-shadow:0 0 0 3px rgba(110,168,255,.12);
}

.settings-form-actions{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    margin-top:18px;
}

.settings-actions-left,
.settings-actions-right{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
}

.settings-note{
    color:var(--muted);
    font-size:12px;
    font-weight:700;
}

@media (max-width: 980px){
    .settings-page-hero{
        grid-template-columns:1fr;
    }

    .settings-form-grid{
        grid-template-columns:1fr;
    }
}

@media (max-width: 720px){
    .settings-page-title{
        font-size:24px;
    }

    .settings-form-card{
        padding:16px;
    }

    .settings-section-block{
        padding:16px;
    }

    .settings-form-actions{
        align-items:stretch;
    }

    .settings-actions-left,
    .settings-actions-right{
        width:100%;
    }
}
</style>

<div class="content-card">
    <div class="settings-general-shell">

        <div class="settings-back-row">
            <a href="<?php echo h(app_url('modules/settings/index.php')); ?>" class="nav-link">← Torna a Impostazioni</a>

            <div class="page-head-right">
                <span class="soft-pill">Sezione: Generali</span>
                <span class="soft-pill">Chiave/valore DB attivo</span>
            </div>
        </div>

        <section class="settings-page-hero">
            <div class="settings-page-hero-card">
                <span class="settings-page-kicker">Base applicativa Turnar</span>

                <h2 class="settings-page-title">Impostazioni generali del software</h2>

                <p class="settings-page-text">
                    Qui configuriamo l’identità base del gestionale: nome software, tagline, versione visuale,
                    lingua, timezone, formati data e ora, anno di riferimento e footer personalizzato.
                    Questa è la prima sottosezione reale del pannello Impostazioni.
                </p>

                <div class="settings-page-tags">
                    <span class="settings-page-tag">Nome applicazione</span>
                    <span class="settings-page-tag">Timezone</span>
                    <span class="settings-page-tag">Formati data/ora</span>
                    <span class="settings-page-tag">Base per branding e report</span>
                </div>
            </div>

            <aside class="settings-page-side-card">
                <div class="settings-side-title">Riepilogo attuale</div>

                <div class="settings-side-box">
                    <div class="settings-side-label">Nome software</div>
                    <div class="settings-side-value"><?php echo h((string)setting('app_name', 'Turnar')); ?></div>
                </div>

                <div class="settings-side-box">
                    <div class="settings-side-label">Tagline</div>
                    <div class="settings-side-value"><?php echo h((string)setting('app_tagline', '')); ?></div>
                </div>

                <div class="settings-side-box">
                    <div class="settings-side-label">Timezone / Lingua</div>
                    <div class="settings-side-value">
                        <?php echo h((string)setting('app_timezone', 'Europe/Rome')); ?>
                        ·
                        <?php echo h((string)setting('app_language', 'it')); ?>
                    </div>
                </div>
            </aside>
        </section>

        <section class="settings-form-card">
            <div class="settings-form-head">
                <div>
                    <h3 class="settings-form-title">Configura i parametri principali</h3>
                    <p class="settings-form-subtitle">
                        Salva qui le impostazioni generali che guideranno il comportamento base del software.
                    </p>
                </div>

                <span class="settings-status-pill info">Prima sezione attiva</span>
            </div>

            <?php if ($successMessage !== ''): ?>
                <div class="settings-alert success"><?php echo h($successMessage); ?></div>
            <?php endif; ?>

            <?php if ($errorMessage !== ''): ?>
                <div class="settings-alert error"><?php echo h($errorMessage); ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="settings-section-block">
                    <div class="settings-section-top">
                        <div>
                            <h4 class="settings-section-title">Identità software</h4>
                            <p class="settings-section-text">
                                Informazioni principali mostrate nel gestionale e usate come base comune nelle schermate.
                            </p>
                        </div>
                        <span class="settings-mini-pill">Base applicazione</span>
                    </div>

                    <div class="settings-form-grid">
                        <div class="settings-field">
                            <label class="settings-label" for="app_name">Nome software</label>
                            <input
                                type="text"
                                id="app_name"
                                name="app_name"
                                class="settings-input"
                                value="<?php echo h((string)$current['app_name']); ?>"
                                maxlength="150"
                                required
                            >
                            <div class="settings-help">Esempio: Turnar</div>
                        </div>

                        <div class="settings-field">
                            <label class="settings-label" for="app_version_visual">Versione visuale</label>
                            <input
                                type="text"
                                id="app_version_visual"
                                name="app_version_visual"
                                class="settings-input"
                                value="<?php echo h((string)$current['app_version_visual']); ?>"
                                maxlength="50"
                            >
                            <div class="settings-help">Esempio: 1.0.0 oppure 2026.1</div>
                        </div>

                        <div class="settings-field full">
                            <label class="settings-label" for="app_tagline">Slogan / tagline</label>
                            <input
                                type="text"
                                id="app_tagline"
                                name="app_tagline"
                                class="settings-input"
                                value="<?php echo h((string)$current['app_tagline']); ?>"
                                maxlength="255"
                            >
                            <div class="settings-help">Testo breve mostrabile sotto il nome software nella topbar.</div>
                        </div>
                    </div>
                </div>

                <div class="settings-section-block">
                    <div class="settings-section-top">
                        <div>
                            <h4 class="settings-section-title">Lingua, data e ora</h4>
                            <p class="settings-section-text">
                                Parametri generali usati nel gestionale per visualizzazione e logica temporale.
                            </p>
                        </div>
                        <span class="settings-mini-pill">Localizzazione</span>
                    </div>

                    <div class="settings-form-grid">
                        <div class="settings-field">
                            <label class="settings-label" for="app_language">Lingua</label>
                            <select id="app_language" name="app_language" class="settings-select">
                                <option value="it" <?php echo ((string)$current['app_language'] === 'it') ? 'selected' : ''; ?>>Italiano</option>
                                <option value="en" <?php echo ((string)$current['app_language'] === 'en') ? 'selected' : ''; ?>>English</option>
                            </select>
                            <div class="settings-help">Per ora consigliato: Italiano.</div>
                        </div>

                        <div class="settings-field">
                            <label class="settings-label" for="app_timezone">Timezone</label>
                            <select id="app_timezone" name="app_timezone" class="settings-select">
                                <?php
                                $timezones = [
                                    'Europe/Rome',
                                    'Europe/London',
                                    'Europe/Berlin',
                                    'Europe/Paris',
                                    'UTC',
                                ];
                                $selectedTimezone = (string)$current['app_timezone'];
                                foreach ($timezones as $tz):
                                ?>
                                    <option value="<?php echo h($tz); ?>" <?php echo $selectedTimezone === $tz ? 'selected' : ''; ?>>
                                        <?php echo h($tz); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="settings-help">Default consigliato per Turnar: Europe/Rome.</div>
                        </div>

                        <div class="settings-field">
                            <label class="settings-label" for="date_format">Formato data</label>
                            <select id="date_format" name="date_format" class="settings-select">
                                <?php
                                $dateFormats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'd.m.Y'];
                                $selectedDateFormat = (string)$current['date_format'];
                                foreach ($dateFormats as $fmt):
                                ?>
                                    <option value="<?php echo h($fmt); ?>" <?php echo $selectedDateFormat === $fmt ? 'selected' : ''; ?>>
                                        <?php echo h($fmt); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="settings-help">Formato principale per viste e stampe future.</div>
                        </div>

                        <div class="settings-field">
                            <label class="settings-label" for="time_format">Formato ora</label>
                            <select id="time_format" name="time_format" class="settings-select">
                                <?php
                                $timeFormats = ['H:i', 'H:i:s'];
                                $selectedTimeFormat = (string)$current['time_format'];
                                foreach ($timeFormats as $fmt):
                                ?>
                                    <option value="<?php echo h($fmt); ?>" <?php echo $selectedTimeFormat === $fmt ? 'selected' : ''; ?>>
                                        <?php echo h($fmt); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="settings-help">Per Turnar consigliato: H:i</div>
                        </div>

                        <div class="settings-field">
                            <label class="settings-label" for="reference_year">Anno di riferimento</label>
                            <input
                                type="number"
                                id="reference_year"
                                name="reference_year"
                                class="settings-input"
                                min="2000"
                                max="2100"
                                step="1"
                                value="<?php echo h((string)$current['reference_year']); ?>"
                            >
                            <div class="settings-help">Usato come riferimento iniziale per report e configurazioni.</div>
                        </div>
                    </div>
                </div>

                <div class="settings-section-block">
                    <div class="settings-section-top">
                        <div>
                            <h4 class="settings-section-title">Footer personalizzato</h4>
                            <p class="settings-section-text">
                                Testo opzionale da riutilizzare in fondo a schermate, stampe o aree informative future.
                            </p>
                        </div>
                        <span class="settings-mini-pill">Testi comuni</span>
                    </div>

                    <div class="settings-form-grid">
                        <div class="settings-field full">
                            <label class="settings-label" for="custom_footer_text">Footer personalizzato</label>
                            <textarea
                                id="custom_footer_text"
                                name="custom_footer_text"
                                class="settings-textarea"
                                placeholder="Inserisci un testo footer comune per il software..."
                            ><?php echo h((string)$current['custom_footer_text']); ?></textarea>
                            <div class="settings-help">
                                Esempio: ragione sociale, contatti rapidi, nota gestionale o testo standard aziendale.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-form-actions">
                    <div class="settings-actions-left">
                        <a href="<?php echo h(app_url('modules/settings/index.php')); ?>" class="nav-link">Annulla</a>
                    </div>

                    <div class="settings-actions-right">
                        <?php if ($canEditSettings): ?>
                            <button type="submit" class="nav-link active">Salva impostazioni generali</button>
                        <?php else: ?>
                            <button type="button" class="nav-link" disabled style="opacity:.65; cursor:not-allowed;">Modifica non consentita</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="settings-note">
                    Le impostazioni vengono salvate nella tabella <strong>settings</strong> come coppie chiave/valore.
                </div>
            </form>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>