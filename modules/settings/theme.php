<?php
// modules/settings/theme.php

require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/settings.php';

require_login();
require_permission('settings.view');

$pageTitle    = 'Tema e aspetto';
$pageSubtitle = 'Personalizzazione grafica del gestionale Turnar';
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
        $errorMessage = 'Non hai i permessi per modificare il tema.';
    } else {
        $themeMode = trim((string)post('theme_mode', 'dark'));
        $themePrimaryColor = trim((string)post('theme_primary_color', '#6ea8ff'));
        $themeSecondaryColor = trim((string)post('theme_secondary_color', '#8b5cf6'));
        $themeTopbarStyle = trim((string)post('theme_topbar_style', 'glass'));
        $themeBadgeStyle = trim((string)post('theme_badge_style', 'rounded'));

        if (!in_array($themeMode, ['dark', 'light', 'auto'], true)) {
            $themeMode = 'dark';
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $themePrimaryColor)) {
            $themePrimaryColor = '#6ea8ff';
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $themeSecondaryColor)) {
            $themeSecondaryColor = '#8b5cf6';
        }

        if (!in_array($themeTopbarStyle, ['glass', 'solid'], true)) {
            $themeTopbarStyle = 'glass';
        }

        if (!in_array($themeBadgeStyle, ['rounded', 'soft'], true)) {
            $themeBadgeStyle = 'rounded';
        }

        $data = [
            'theme_mode'            => $themeMode,
            'theme_primary_color'   => $themePrimaryColor,
            'theme_secondary_color' => $themeSecondaryColor,
            'theme_topbar_style'    => $themeTopbarStyle,
            'theme_badge_style'     => $themeBadgeStyle,
        ];

        if (settings_save_many($data)) {
            $successMessage = 'Tema salvato correttamente.';
        } else {
            $errorMessage = 'Salvataggio tema non riuscito.';
        }
    }
}

$current = load_settings();

$themeMode = (string)($current['theme_mode'] ?? 'dark');
$themePrimaryColor = (string)($current['theme_primary_color'] ?? '#6ea8ff');
$themeSecondaryColor = (string)($current['theme_secondary_color'] ?? '#8b5cf6');
$themeTopbarStyle = (string)($current['theme_topbar_style'] ?? 'glass');
$themeBadgeStyle = (string)($current['theme_badge_style'] ?? 'rounded');

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.theme-shell{
    display:flex;
    flex-direction:column;
    gap:18px;
}

.theme-hero{
    display:grid;
    grid-template-columns:minmax(0, 1.35fr) minmax(300px, .8fr);
    gap:16px;
}

.theme-hero-card,
.theme-side-card,
.theme-form-card,
.theme-preview-card{
    background:linear-gradient(180deg, rgba(255,255,255,.045), rgba(255,255,255,.02));
    border:1px solid var(--line);
    border-radius:24px;
    box-shadow:var(--shadow);
}

.theme-hero-card{
    padding:22px;
    position:relative;
    overflow:hidden;
}

.theme-hero-card::after{
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

.theme-kicker{
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

.theme-title{
    margin:16px 0 0;
    font-size:28px;
    line-height:1.1;
    font-weight:900;
    position:relative;
    z-index:1;
}

.theme-text{
    margin:12px 0 0;
    color:var(--muted);
    font-size:14px;
    line-height:1.65;
    max-width:860px;
    position:relative;
    z-index:1;
}

.theme-tags{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-top:18px;
    position:relative;
    z-index:1;
}

.theme-tag{
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

.theme-side-card{
    padding:18px;
    display:flex;
    flex-direction:column;
    gap:12px;
}

.theme-side-title{
    font-size:13px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.06em;
    color:var(--muted);
}

.theme-side-box{
    padding:14px 16px;
    border-radius:18px;
    border:1px solid var(--line);
    background:rgba(255,255,255,.04);
}

.theme-side-label{
    color:var(--muted);
    font-size:12px;
    margin-bottom:6px;
}

.theme-side-value{
    color:var(--text);
    font-size:15px;
    font-weight:900;
    line-height:1.3;
}

.theme-alert{
    padding:14px 16px;
    border-radius:18px;
    border:1px solid var(--line);
    font-size:14px;
    font-weight:700;
}

.theme-alert.success{
    color:#dcfce7;
    background:rgba(52,211,153,.14);
    border-color:rgba(52,211,153,.28);
}

.theme-alert.error{
    color:#ffe4e6;
    background:rgba(248,113,113,.12);
    border-color:rgba(248,113,113,.28);
}

.theme-main-grid{
    display:grid;
    grid-template-columns:minmax(0, 1.45fr) minmax(300px, .85fr);
    gap:16px;
    align-items:start;
}

.theme-form-card,
.theme-preview-card{
    padding:20px;
}

.theme-form-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:18px;
}

.theme-form-title{
    margin:0;
    font-size:22px;
    line-height:1.1;
}

.theme-form-subtitle{
    margin:8px 0 0;
    color:var(--muted);
    font-size:14px;
}

.theme-status-pill{
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

.theme-block{
    border:1px solid var(--line);
    background:rgba(255,255,255,.03);
    border-radius:22px;
    padding:18px;
}

.theme-block + .theme-block{
    margin-top:16px;
}

.theme-block-top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:16px;
}

.theme-block-title{
    margin:0;
    font-size:18px;
    font-weight:900;
    line-height:1.2;
}

.theme-block-text{
    margin:8px 0 0;
    color:var(--muted);
    font-size:13px;
    line-height:1.6;
}

.theme-mini-pill{
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

.theme-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:16px;
}

.theme-field{
    display:flex;
    flex-direction:column;
    gap:8px;
}

.theme-field.full{
    grid-column:1 / -1;
}

.theme-label{
    font-size:13px;
    font-weight:800;
    color:var(--text);
}

.theme-help{
    color:var(--muted);
    font-size:12px;
    line-height:1.5;
    margin-top:-2px;
}

.theme-select{
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

.theme-select:focus{
    border-color:rgba(110,168,255,.45);
    box-shadow:0 0 0 3px rgba(110,168,255,.12);
}

.theme-options{
    display:grid;
    gap:10px;
}

.theme-radio{
    display:flex;
    align-items:flex-start;
    gap:10px;
    padding:12px 14px;
    border-radius:16px;
    border:1px solid var(--line);
    background:rgba(255,255,255,.03);
}

.theme-radio input{
    margin-top:2px;
}

.theme-radio strong{
    display:block;
    font-size:14px;
    margin-bottom:4px;
}

.theme-radio small{
    display:block;
    color:var(--muted);
    font-size:12px;
    line-height:1.5;
}

.theme-color-row{
    display:flex;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
}

.theme-color{
    width:52px;
    height:46px;
    padding:4px;
    border:none;
    border-radius:14px;
    background:transparent;
    cursor:pointer;
}

.theme-color-value{
    min-height:46px;
    display:inline-flex;
    align-items:center;
    padding:0 14px;
    border-radius:14px;
    border:1px solid var(--line);
    background:rgba(255,255,255,.04);
    font-size:13px;
    font-weight:800;
}

.theme-preview-stack{
    display:flex;
    flex-direction:column;
    gap:16px;
}

.theme-preview-title{
    margin:0 0 12px;
    font-size:18px;
    font-weight:900;
}

.theme-preview-box{
    border:1px solid var(--line);
    border-radius:22px;
    padding:16px;
    background:rgba(255,255,255,.03);
}

.theme-preview-app{
    border-radius:20px;
    overflow:hidden;
    border:1px solid rgba(255,255,255,.10);
    background:#0b1226;
}

.theme-preview-app.light{
    background:#f4f7fb;
}

.theme-preview-topbar{
    padding:14px;
    display:flex;
    align-items:center;
    gap:10px;
    background:rgba(255,255,255,.05);
    border-bottom:1px solid rgba(255,255,255,.08);
}

.theme-preview-app.light .theme-preview-topbar{
    background:rgba(15,23,42,.05);
    border-bottom:1px solid rgba(15,23,42,.08);
}

.theme-preview-badge{
    padding:8px 12px;
    border-radius:999px;
    color:#fff;
    font-size:12px;
    font-weight:800;
}

.theme-preview-body{
    padding:16px;
    display:grid;
    gap:12px;
}

.theme-preview-card-mini{
    padding:14px;
    border-radius:18px;
    background:rgba(255,255,255,.05);
    border:1px solid rgba(255,255,255,.08);
}

.theme-preview-app.light .theme-preview-card-mini{
    background:#ffffff;
    border:1px solid rgba(15,23,42,.08);
}

.theme-preview-line-1,
.theme-preview-line-2{
    border-radius:999px;
}

.theme-preview-line-1{
    height:12px;
    width:62%;
    background:rgba(255,255,255,.90);
}

.theme-preview-line-2{
    height:10px;
    width:86%;
    margin-top:8px;
    background:rgba(170,184,211,.55);
}

.theme-preview-app.light .theme-preview-line-1{
    background:rgba(15,23,42,.85);
}

.theme-preview-app.light .theme-preview-line-2{
    background:rgba(71,85,105,.35);
}

.theme-form-actions{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    margin-top:18px;
}

.theme-actions-left,
.theme-actions-right{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
}

.theme-note{
    color:var(--muted);
    font-size:12px;
    font-weight:700;
}

@media (max-width: 1080px){
    .theme-hero,
    .theme-main-grid{
        grid-template-columns:1fr;
    }
}

@media (max-width: 820px){
    .theme-grid{
        grid-template-columns:1fr;
    }
}

@media (max-width: 720px){
    .theme-title{
        font-size:24px;
    }

    .theme-form-card,
    .theme-preview-card{
        padding:16px;
    }

    .theme-block{
        padding:16px;
    }

    .theme-form-actions{
        align-items:stretch;
    }

    .theme-actions-left,
    .theme-actions-right{
        width:100%;
    }
}
</style>

<div class="content-card">
    <div class="theme-shell">

        <div class="settings-back-row" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <a href="<?php echo h(app_url('modules/settings/index.php')); ?>" class="nav-link">← Torna a Impostazioni</a>

            <div class="page-head-right">
                <span class="soft-pill">Sezione: Tema</span>
                <span class="soft-pill">Layout dinamico pronto</span>
            </div>
        </div>

        <section class="theme-hero">
            <div class="theme-hero-card">
                <span class="theme-kicker">Personalizzazione grafica</span>

                <h2 class="theme-title">Tema e aspetto del gestionale</h2>

                <p class="theme-text">
                    Qui puoi configurare la modalità visiva e i colori principali di Turnar.
                    Le modifiche vengono applicate al layout condiviso del software, così il tema diventa davvero globale.
                </p>

                <div class="theme-tags">
                    <span class="theme-tag">Dark mode</span>
                    <span class="theme-tag">Light mode</span>
                    <span class="theme-tag">Colori dinamici</span>
                    <span class="theme-tag">Topbar condivisa</span>
                </div>
            </div>

            <aside class="theme-side-card">
                <div class="theme-side-title">Riepilogo attuale</div>

                <div class="theme-side-box">
                    <div class="theme-side-label">Modalità</div>
                    <div class="theme-side-value"><?php echo h($themeMode); ?></div>
                </div>

                <div class="theme-side-box">
                    <div class="theme-side-label">Colore primario</div>
                    <div class="theme-side-value"><?php echo h($themePrimaryColor); ?></div>
                </div>

                <div class="theme-side-box">
                    <div class="theme-side-label">Colore secondario</div>
                    <div class="theme-side-value"><?php echo h($themeSecondaryColor); ?></div>
                </div>
            </aside>
        </section>

        <?php if ($successMessage !== ''): ?>
            <div class="theme-alert success"><?php echo h($successMessage); ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="theme-alert error"><?php echo h($errorMessage); ?></div>
        <?php endif; ?>

        <div class="theme-main-grid">
            <section class="theme-form-card">
                <div class="theme-form-head">
                    <div>
                        <h3 class="theme-form-title">Configura il tema</h3>
                        <p class="theme-form-subtitle">Le impostazioni salvate qui andranno ad agire su tutto il layout comune.</p>
                    </div>

                    <span class="theme-status-pill">Sezione attiva</span>
                </div>

                <form method="post" action="">
                    <div class="theme-block">
                        <div class="theme-block-top">
                            <div>
                                <h4 class="theme-block-title">Modalità visiva</h4>
                                <p class="theme-block-text">Scegli il comportamento generale del tema grafico.</p>
                            </div>
                            <span class="theme-mini-pill">Base tema</span>
                        </div>

                        <div class="theme-options">
                            <label class="theme-radio">
                                <input type="radio" name="theme_mode" value="dark" <?php echo $themeMode === 'dark' ? 'checked' : ''; ?>>
                                <span>
                                    <strong>Dark</strong>
                                    <small>Modalità scura moderna, consigliata per Turnar.</small>
                                </span>
                            </label>

                            <label class="theme-radio">
                                <input type="radio" name="theme_mode" value="light" <?php echo $themeMode === 'light' ? 'checked' : ''; ?>>
                                <span>
                                    <strong>Light</strong>
                                    <small>Versione chiara del gestionale, utile per ufficio e stampa visiva.</small>
                                </span>
                            </label>

                            <label class="theme-radio">
                                <input type="radio" name="theme_mode" value="auto" <?php echo $themeMode === 'auto' ? 'checked' : ''; ?>>
                                <span>
                                    <strong>Automatico</strong>
                                    <small>Segue la preferenza del browser o del sistema operativo.</small>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="theme-block">
                        <div class="theme-block-top">
                            <div>
                                <h4 class="theme-block-title">Colori principali</h4>
                                <p class="theme-block-text">Definisci i colori chiave del layout condiviso.</p>
                            </div>
                            <span class="theme-mini-pill">Color system</span>
                        </div>

                        <div class="theme-grid">
                            <div class="theme-field">
                                <label class="theme-label" for="theme_primary_color">Colore primario</label>
                                <div class="theme-color-row">
                                    <input type="color" id="theme_primary_color" name="theme_primary_color" class="theme-color" value="<?php echo h($themePrimaryColor); ?>">
                                    <span class="theme-color-value" id="themePrimaryColorValue"><?php echo h($themePrimaryColor); ?></span>
                                </div>
                                <div class="theme-help">Usato per highlight, pulsanti attivi e dettagli principali.</div>
                            </div>

                            <div class="theme-field">
                                <label class="theme-label" for="theme_secondary_color">Colore secondario</label>
                                <div class="theme-color-row">
                                    <input type="color" id="theme_secondary_color" name="theme_secondary_color" class="theme-color" value="<?php echo h($themeSecondaryColor); ?>">
                                    <span class="theme-color-value" id="themeSecondaryColorValue"><?php echo h($themeSecondaryColor); ?></span>
                                </div>
                                <div class="theme-help">Usato per gradient, accenti visivi e badge secondari.</div>
                            </div>
                        </div>
                    </div>

                    <div class="theme-block">
                        <div class="theme-block-top">
                            <div>
                                <h4 class="theme-block-title">Stile interfaccia</h4>
                                <p class="theme-block-text">Parametri già pronti per l’evoluzione del layout.</p>
                            </div>
                            <span class="theme-mini-pill">Preset</span>
                        </div>

                        <div class="theme-grid">
                            <div class="theme-field">
                                <label class="theme-label" for="theme_topbar_style">Stile topbar</label>
                                <select id="theme_topbar_style" name="theme_topbar_style" class="theme-select">
                                    <option value="glass" <?php echo $themeTopbarStyle === 'glass' ? 'selected' : ''; ?>>Glass / trasparente</option>
                                    <option value="solid" <?php echo $themeTopbarStyle === 'solid' ? 'selected' : ''; ?>>Solid / piena</option>
                                </select>
                            </div>

                            <div class="theme-field">
                                <label class="theme-label" for="theme_badge_style">Stile pill / badge</label>
                                <select id="theme_badge_style" name="theme_badge_style" class="theme-select">
                                    <option value="rounded" <?php echo $themeBadgeStyle === 'rounded' ? 'selected' : ''; ?>>Rounded</option>
                                    <option value="soft" <?php echo $themeBadgeStyle === 'soft' ? 'selected' : ''; ?>>Soft</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="theme-form-actions">
                        <div class="theme-actions-left">
                            <a href="<?php echo h(app_url('modules/settings/index.php')); ?>" class="nav-link">Annulla</a>
                        </div>

                        <div class="theme-actions-right">
                            <?php if ($canEditSettings): ?>
                                <button type="submit" class="nav-link active">Salva impostazioni tema</button>
                            <?php else: ?>
                                <button type="button" class="nav-link" disabled style="opacity:.65; cursor:not-allowed;">Modifica non consentita</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="theme-note">
                        Il tema viene letto dal layout condiviso e influenza l’aspetto globale del software.
                    </div>
                </form>
            </section>

            <aside class="theme-preview-stack">
                <section class="theme-preview-card">
                    <h3 class="theme-preview-title">Anteprima rapida</h3>

                    <div class="theme-preview-box">
                        <div class="theme-preview-app <?php echo $themeMode === 'light' ? 'light' : ''; ?>" id="themePreviewApp">
                            <div class="theme-preview-topbar">
                                <span class="theme-preview-badge" id="themePreviewBadge" style="background:linear-gradient(135deg, <?php echo h($themePrimaryColor); ?>, <?php echo h($themeSecondaryColor); ?>);">
                                    Turnar
                                </span>
                            </div>

                            <div class="theme-preview-body">
                                <div class="theme-preview-card-mini">
                                    <div class="theme-preview-line-1"></div>
                                    <div class="theme-preview-line-2"></div>
                                </div>

                                <div class="theme-preview-card-mini">
                                    <div class="theme-preview-line-1" style="width:48%;"></div>
                                    <div class="theme-preview-line-2" style="width:72%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="theme-preview-card">
                    <h3 class="theme-preview-title">Cosa cambia</h3>

                    <div class="theme-preview-box" style="display:grid;gap:10px;">
                        <div class="theme-side-box">
                            <div class="theme-side-label">Layout globale</div>
                            <div class="theme-side-value">Sfondo, topbar, badge, pulsanti e highlight.</div>
                        </div>

                        <div class="theme-side-box">
                            <div class="theme-side-label">Effetto immediato</div>
                            <div class="theme-side-value">Dopo il salvataggio il layout condiviso leggerà i nuovi valori.</div>
                        </div>

                        <div class="theme-side-box">
                            <div class="theme-side-label">Passo successivo</div>
                            <div class="theme-side-value">Rifiniremo light mode e varianti visive modulo per modulo.</div>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</div>

<script>
(function () {
    const primaryInput = document.getElementById('theme_primary_color');
    const secondaryInput = document.getElementById('theme_secondary_color');
    const primaryValue = document.getElementById('themePrimaryColorValue');
    const secondaryValue = document.getElementById('themeSecondaryColorValue');
    const previewBadge = document.getElementById('themePreviewBadge');

    function updatePreview() {
        const primary = primaryInput ? primaryInput.value : '#6ea8ff';
        const secondary = secondaryInput ? secondaryInput.value : '#8b5cf6';

        if (primaryValue) {
            primaryValue.textContent = primary;
        }

        if (secondaryValue) {
            secondaryValue.textContent = secondary;
        }

        if (previewBadge) {
            previewBadge.style.background = 'linear-gradient(135deg, ' + primary + ', ' + secondary + ')';
        }
    }

    if (primaryInput) {
        primaryInput.addEventListener('input', updatePreview);
    }

    if (secondaryInput) {
        secondaryInput.addEventListener('input', updatePreview);
    }

    updatePreview();
})();
</script>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>