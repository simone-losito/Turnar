<?php
// modules/settings/branding.php

require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/settings.php';

require_login();
require_permission('settings.view');

$pageTitle    = 'Branding e logo';
$pageSubtitle = 'Identità aziendale e immagine del software';
$activeModule = 'settings';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$canEditSettings = function_exists('can') ? can('settings.edit') : true;

$successMessage = '';
$errorMessage   = '';

$uploadDirFs   = __DIR__ . '/../../uploads/branding/';
$uploadBaseRel = 'uploads/branding/';

if (!is_dir($uploadDirFs)) {
    @mkdir($uploadDirFs, 0775, true);
}

function branding_uploaded_logo_url(array $settings): string
{
    $path = trim((string)($settings['company_logo_path'] ?? ''));
    if ($path === '') {
        return '';
    }

    return app_url($path);
}

function branding_upload_logo(string $fieldName, string $uploadDirFs, string $uploadBaseRel, ?string &$errorMessage): ?string
{
    if (empty($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return null;
    }

    $file = $_FILES[$fieldName];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errorMessage = 'Errore durante il caricamento del logo.';
        return null;
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    $originalName = (string)($file['name'] ?? '');

    if ($tmpName === '' || $originalName === '') {
        $errorMessage = 'File logo non valido.';
        return null;
    }

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['png', 'jpg', 'jpeg', 'webp'];

    if (!in_array($ext, $allowed, true)) {
        $errorMessage = 'Formato logo non valido. Usa PNG, JPG, JPEG o WEBP.';
        return null;
    }

    $maxBytes = 5 * 1024 * 1024;
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        $errorMessage = 'Il logo deve essere più piccolo di 5 MB.';
        return null;
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string)finfo_file($finfo, $tmpName);
            finfo_close($finfo);
        }
    }

    $allowedMime = [
        'image/png',
        'image/jpeg',
        'image/webp',
    ];

    if ($mime !== '' && !in_array($mime, $allowedMime, true)) {
        $errorMessage = 'Il file caricato non sembra essere un’immagine valida.';
        return null;
    }

    $safeName = 'company_logo_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $destFs = $uploadDirFs . $safeName;

    if (!move_uploaded_file($tmpName, $destFs)) {
        $errorMessage = 'Impossibile salvare il logo caricato.';
        return null;
    }

    return $uploadBaseRel . $safeName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEditSettings) {
        $errorMessage = 'Non hai i permessi per modificare le impostazioni.';
    } else {
        $data = [
            'company_name'             => trim((string)post('company_name', '')),
            'company_legal_name'       => trim((string)post('company_legal_name', '')),
            'company_address'          => trim((string)post('company_address', '')),
            'company_city'             => trim((string)post('company_city', '')),
            'company_province'         => trim((string)post('company_province', '')),
            'company_zip'              => trim((string)post('company_zip', '')),
            'company_phone'            => trim((string)post('company_phone', '')),
            'company_email'            => trim((string)post('company_email', '')),
            'company_pec'              => trim((string)post('company_pec', '')),
            'company_vat'              => trim((string)post('company_vat', '')),
            'company_tax_code'         => trim((string)post('company_tax_code', '')),
            'topbar_logo_mode'         => trim((string)post('topbar_logo_mode', 'logo_and_name')),
            'company_favicon_path'     => trim((string)post('company_favicon_path', '')),
            'company_login_image_path' => trim((string)post('company_login_image_path', '')),
        ];

        if ($data['company_name'] === '') {
            $errorMessage = 'Il nome azienda è obbligatorio.';
        }

        if ($errorMessage === '') {
            $logoPath = branding_upload_logo('company_logo', $uploadDirFs, $uploadBaseRel, $errorMessage);
            if ($logoPath !== null) {
                $data['company_logo_path'] = $logoPath;
            }

            if ($errorMessage === '') {
                if (settings_save_many($data)) {
                    $successMessage = 'Branding salvato correttamente.';
                } else {
                    $errorMessage = 'Salvataggio non riuscito. Controlla tabella settings e permessi cartelle.';
                }
            }
        }
    }
}

$current = load_settings();
$currentLogoUrl = branding_uploaded_logo_url($current);

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.settings-branding-shell{
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
    grid-template-columns:minmax(0, 1.35fr) minmax(300px, .8fr);
    gap:16px;
}

.settings-page-hero-card,
.settings-page-side-card,
.settings-form-card,
.settings-preview-card{
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
    word-break:break-word;
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

.settings-main-grid{
    display:grid;
    grid-template-columns:minmax(0, 1.5fr) minmax(300px, .8fr);
    gap:16px;
    align-items:start;
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

.settings-form-grid.triple{
    grid-template-columns:repeat(3, minmax(0, 1fr));
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
.settings-file{
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

.settings-input:focus,
.settings-select:focus,
.settings-file:focus{
    border-color:rgba(110,168,255,.45);
    box-shadow:0 0 0 3px rgba(110,168,255,.12);
}

.settings-preview-stack{
    display:flex;
    flex-direction:column;
    gap:16px;
}

.settings-preview-card{
    padding:18px;
}

.settings-preview-title{
    margin:0 0 12px;
    font-size:18px;
    font-weight:900;
}

.brand-preview-box{
    border:1px solid var(--line);
    border-radius:20px;
    padding:18px;
    background:
        radial-gradient(circle at top left, rgba(110,168,255,.12), transparent 28%),
        linear-gradient(180deg, rgba(255,255,255,.04), rgba(255,255,255,.02));
}

.brand-preview-topbar{
    display:flex;
    align-items:center;
    gap:14px;
    min-width:0;
}

.brand-preview-logo{
    width:60px;
    height:60px;
    border-radius:18px;
    flex:0 0 auto;
    overflow:hidden;
    display:flex;
    align-items:center;
    justify-content:center;
    background:linear-gradient(135deg, var(--primary), var(--primary-2));
    color:#fff;
    font-size:22px;
    font-weight:900;
    border:1px solid rgba(255,255,255,.12);
}

.brand-preview-logo img{
    width:100%;
    height:100%;
    object-fit:contain;
    display:block;
    background:#fff;
}

.brand-preview-text{
    min-width:0;
}

.brand-preview-name{
    font-size:20px;
    font-weight:900;
    line-height:1.1;
    color:var(--text);
    word-break:break-word;
}

.brand-preview-sub{
    margin-top:4px;
    color:var(--muted);
    font-size:13px;
    line-height:1.4;
    word-break:break-word;
}

.brand-info-list{
    display:grid;
    gap:10px;
}

.brand-info-item{
    padding:12px 14px;
    border-radius:16px;
    border:1px solid var(--line);
    background:rgba(255,255,255,.04);
}

.brand-info-label{
    color:var(--muted);
    font-size:11px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.05em;
    margin-bottom:5px;
}

.brand-info-value{
    color:var(--text);
    font-size:14px;
    font-weight:700;
    line-height:1.45;
    word-break:break-word;
}

.logo-upload-box{
    display:grid;
    grid-template-columns:minmax(0, 1fr);
    gap:14px;
    padding:16px;
    border-radius:18px;
    border:1px dashed rgba(255,255,255,.16);
    background:rgba(255,255,255,.03);
}

.logo-preview-frame{
    min-height:130px;
    border-radius:18px;
    border:1px solid var(--line);
    background:rgba(255,255,255,.03);
    display:flex;
    align-items:center;
    justify-content:center;
    padding:14px;
    overflow:hidden;
}

.logo-preview-frame img{
    max-width:100%;
    max-height:100px;
    display:block;
    object-fit:contain;
    background:#fff;
    border-radius:10px;
    padding:8px;
}

.logo-preview-placeholder{
    color:var(--muted);
    font-size:13px;
    font-weight:700;
    text-align:center;
    line-height:1.5;
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

@media (max-width: 1080px){
    .settings-page-hero,
    .settings-main-grid{
        grid-template-columns:1fr;
    }
}

@media (max-width: 820px){
    .settings-form-grid,
    .settings-form-grid.triple{
        grid-template-columns:1fr;
    }
}

@media (max-width: 720px){
    .settings-page-title{
        font-size:24px;
    }

    .settings-form-card,
    .settings-preview-card{
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
    <div class="settings-branding-shell">

        <div class="settings-back-row">
            <a href="<?php echo h(app_url('modules/settings/index.php')); ?>" class="nav-link">← Torna a Impostazioni</a>

            <div class="page-head-right">
                <span class="soft-pill">Sezione: Branding</span>
                <span class="soft-pill">Logo topbar pronto</span>
            </div>
        </div>

        <section class="settings-page-hero">
            <div class="settings-page-hero-card">
                <span class="settings-page-kicker">Identità visiva aziendale</span>

                <h2 class="settings-page-title">Branding professionale per Turnar</h2>

                <p class="settings-page-text">
                    Qui configuri nome azienda, dati societari, logo, elementi grafici e modalità di visualizzazione
                    del marchio nella topbar. Questa sezione sarà la base anche per report PDF, schermata login e app mobile.
                </p>

                <div class="settings-page-tags">
                    <span class="settings-page-tag">Logo aziendale</span>
                    <span class="settings-page-tag">Topbar dinamica</span>
                    <span class="settings-page-tag">Base per PDF e login</span>
                    <span class="settings-page-tag">Immagine coordinata</span>
                </div>
            </div>

            <aside class="settings-page-side-card">
                <div class="settings-side-title">Riepilogo attuale</div>

                <div class="settings-side-box">
                    <div class="settings-side-label">Nome azienda</div>
                    <div class="settings-side-value"><?php echo h((string)setting('company_name', 'La tua azienda')); ?></div>
                </div>

                <div class="settings-side-box">
                    <div class="settings-side-label">Email aziendale</div>
                    <div class="settings-side-value"><?php echo h((string)setting('company_email', '')); ?></div>
                </div>

                <div class="settings-side-box">
                    <div class="settings-side-label">Modalità logo topbar</div>
                    <div class="settings-side-value"><?php echo h((string)setting('topbar_logo_mode', 'logo_and_name')); ?></div>
                </div>
            </aside>
        </section>

        <?php if ($successMessage !== ''): ?>
            <div class="settings-alert success"><?php echo h($successMessage); ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="settings-alert error"><?php echo h($errorMessage); ?></div>
        <?php endif; ?>

        <div class="settings-main-grid">
            <section class="settings-form-card">
                <div class="settings-form-head">
                    <div>
                        <h3 class="settings-form-title">Configura il branding</h3>
                        <p class="settings-form-subtitle">
                            Salva qui l’identità aziendale che verrà riutilizzata nel software.
                        </p>
                    </div>

                    <span class="settings-status-pill success">Sezione attiva</span>
                </div>

                <form method="post" action="" enctype="multipart/form-data">
                    <div class="settings-section-block">
                        <div class="settings-section-top">
                            <div>
                                <h4 class="settings-section-title">Dati azienda</h4>
                                <p class="settings-section-text">
                                    Informazioni principali dell’azienda da usare nel gestionale e nelle stampe future.
                                </p>
                            </div>
                            <span class="settings-mini-pill">Anagrafica</span>
                        </div>

                        <div class="settings-form-grid">
                            <div class="settings-field">
                                <label class="settings-label" for="company_name">Nome azienda</label>
                                <input
                                    type="text"
                                    id="company_name"
                                    name="company_name"
                                    class="settings-input"
                                    value="<?php echo h((string)$current['company_name']); ?>"
                                    maxlength="150"
                                    required
                                >
                                <div class="settings-help">Nome breve e principale dell’azienda.</div>
                            </div>

                            <div class="settings-field">
                                <label class="settings-label" for="company_legal_name">Ragione sociale</label>
                                <input
                                    type="text"
                                    id="company_legal_name"
                                    name="company_legal_name"
                                    class="settings-input"
                                    value="<?php echo h((string)$current['company_legal_name']); ?>"
                                    maxlength="255"
                                >
                                <div class="settings-help">Facoltativa, utile per documenti e intestazioni.</div>
                            </div>

                            <div class="settings-field full">
                                <label class="settings-label" for="company_address">Indirizzo</label>
                                <input
                                    type="text"
                                    id="company_address"
                                    name="company_address"
                                    class="settings-input"
                                    value="<?php echo h((string)$current['company_address']); ?>"
                                    maxlength="255"
                                >
                                <div class="settings-help">Via, numero civico e altri dettagli utili.</div>
                            </div>
                        </div>

                        <div class="settings-form-grid triple" style="margin-top:16px;">
                            <div class="settings-field">
                                <label class="settings-label" for="company_city">Città</label>
                                <input
                                    type="text"
                                    id="company_city"
                                    name="company_city"
                                    class="settings-input"
                                    value="<?php echo h((string)$current['company_city']); ?>"
                                    maxlength="100"
                                >
                            </div>

                            <div class="settings-field">
                                <label class="settings-label" for="company_province">Provincia</label>
                                <input
                                    type="text"
                                    id="company_province"
                                    name="company_province"
                                    class="settings-input"
                                    value="<?php echo h((string)$current['company_province']); ?>"
                                    maxlength="10"
                                >
                            </div>

                            <div class="settings-field">
                                <label class="settings-label" for="company_zip">CAP</label>
                                <input
                                    type="text"
                                    id="company_zip"
                                    name="company_zip"
                                    class="settings-input"
                                    value="<?php echo h((string)$current['company_zip']); ?>"
                                    maxlength="10"
                                >
                            </div>
                        </div>

                        <div class="settings-form-grid triple" style="margin-top:16px;">
                            <div class="settings-field">
                                <label class="settings-label" for="company_phone">Telefono</label>
                                <input
                                    type="text"
                                    id="company_phone"
                                    name="company_phone"
                                    class="settings-input"
                                    value="<?php echo h((string)$current['company_phone']); ?>"
                                    maxlength="50"
                                >
                            </div>

                            <div class="settings-field">
                                <label class="settings-label" for="company_email">Email aziendale</label>
                                <input
                                    type="email"
                                    id="company_email"
                                    name="company_email"
                                    class="settings-input"
                                    value="<?php echo h((string)$current['company_email']); ?>"
                                    maxlength="190"
                                >
                            </div>

                            <div class="settings-field">
                                <label class="settings-label" for="company_pec">PEC</label>
                                <input
                                    type="text"
                                    id="company_pec"
                                    name="company_pec"
                                    class="settings-input"
                                    value="<?php echo h((string)$current['company_pec']); ?>"
                                    maxlength="190"
                                >
                            </div>
                        </div>

                        <div class="settings-form-grid" style="margin-top:16px;">
                            <div class="settings-field">
                                <label class="settings-label" for="company_vat">Partita IVA</label>
                                <input
                                    type="text"
                                    id="company_vat"
                                    name="company_vat"
                                    class="settings-input"
                                    value="<?php echo h((string)$current['company_vat']); ?>"
                                    maxlength="50"
                                >
                            </div>

                            <div class="settings-field">
                                <label class="settings-label" for="company_tax_code">Codice fiscale</label>
                                <input
                                    type="text"
                                    id="company_tax_code"
                                    name="company_tax_code"
                                    class="settings-input"
                                    value="<?php echo h((string)$current['company_tax_code']); ?>"
                                    maxlength="50"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="settings-section-block">
                        <div class="settings-section-top">
                            <div>
                                <h4 class="settings-section-title">Logo e comportamento topbar</h4>
                                <p class="settings-section-text">
                                    Carica il logo aziendale e scegli come mostrarlo nella barra superiore del software.
                                </p>
                            </div>
                            <span class="settings-mini-pill">Immagine coordinata</span>
                        </div>

                        <div class="settings-form-grid">
                            <div class="settings-field full">
                                <label class="settings-label" for="company_logo">Carica logo aziendale</label>
                                <div class="logo-upload-box">
                                    <input
                                        type="file"
                                        id="company_logo"
                                        name="company_logo"
                                        class="settings-file"
                                        accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                                    >
                                    <div class="settings-help">
                                        Formati ammessi: PNG, JPG, JPEG, WEBP. Dimensione massima consigliata: 5 MB.
                                    </div>

                                    <div class="logo-preview-frame">
                                        <?php if ($currentLogoUrl !== ''): ?>
                                            <img src="<?php echo h($currentLogoUrl); ?>" alt="Logo aziendale attuale">
                                        <?php else: ?>
                                            <div class="logo-preview-placeholder">
                                                Nessun logo caricato.<br>
                                                Verrà usato il logo standard di Turnar.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="settings-field">
                                <label class="settings-label" for="topbar_logo_mode">Modalità logo topbar</label>
                                <select id="topbar_logo_mode" name="topbar_logo_mode" class="settings-select">
                                    <option value="icon" <?php echo ((string)$current['topbar_logo_mode'] === 'icon') ? 'selected' : ''; ?>>Solo logo / icona</option>
                                    <option value="logo" <?php echo ((string)$current['topbar_logo_mode'] === 'logo') ? 'selected' : ''; ?>>Logo senza nome</option>
                                    <option value="logo_and_name" <?php echo ((string)$current['topbar_logo_mode'] === 'logo_and_name') ? 'selected' : ''; ?>>Logo + nome software</option>
                                </select>
                                <div class="settings-help">Prepariamo già la topbar per i comportamenti futuri.</div>
                            </div>

                            <div class="settings-field">
                                <label class="settings-label" for="company_favicon_path">Path favicon (futuro)</label>
                                <input
                                    type="text"
                                    id="company_favicon_path"
                                    name="company_favicon_path"
                                    class="settings-input"
                                    value="<?php echo h((string)$current['company_favicon_path']); ?>"
                                    maxlength="255"
                                    placeholder="uploads/branding/favicon.png"
                                >
                                <div class="settings-help">Per ora opzionale. Lo useremo più avanti.</div>
                            </div>

                            <div class="settings-field full">
                                <label class="settings-label" for="company_login_image_path">Path immagine login / splash (futuro)</label>
                                <input
                                    type="text"
                                    id="company_login_image_path"
                                    name="company_login_image_path"
                                    class="settings-input"
                                    value="<?php echo h((string)$current['company_login_image_path']); ?>"
                                    maxlength="255"
                                    placeholder="uploads/branding/login-cover.jpg"
                                >
                                <div class="settings-help">Campo preparato per schermata login e app mobile future.</div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-form-actions">
                        <div class="settings-actions-left">
                            <a href="<?php echo h(app_url('modules/settings/index.php')); ?>" class="nav-link">Annulla</a>
                        </div>

                        <div class="settings-actions-right">
                            <?php if ($canEditSettings): ?>
                                <button type="submit" class="nav-link active">Salva branding</button>
                            <?php else: ?>
                                <button type="button" class="nav-link" disabled style="opacity:.65; cursor:not-allowed;">Modifica non consentita</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="settings-note">
                        Il logo verrà usato nella topbar del software e sarà riutilizzabile anche in report, login e app.
                    </div>
                </form>
            </section>

            <aside class="settings-preview-stack">
                <section class="settings-preview-card">
                    <h3 class="settings-preview-title">Anteprima branding</h3>

                    <div class="brand-preview-box">
                        <div class="brand-preview-topbar">
                            <div class="brand-preview-logo">
                                <?php if ($currentLogoUrl !== ''): ?>
                                    <img src="<?php echo h($currentLogoUrl); ?>" alt="Logo aziendale">
                                <?php else: ?>
                                    T
                                <?php endif; ?>
                            </div>

                            <div class="brand-preview-text">
                                <div class="brand-preview-name">
                                    <?php
                                    $brandName = trim((string)$current['company_name']);
                                    echo h($brandName !== '' ? $brandName : 'La tua azienda');
                                    ?>
                                </div>
                                <div class="brand-preview-sub">
                                    <?php
                                    $tagline = trim((string)setting('app_tagline', 'Organizzazione turni e operatività'));
                                    echo h($tagline !== '' ? $tagline : 'Organizzazione turni e operatività');
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="settings-preview-card">
                    <h3 class="settings-preview-title">Dati rapidi</h3>

                    <div class="brand-info-list">
                        <div class="brand-info-item">
                            <div class="brand-info-label">Ragione sociale</div>
                            <div class="brand-info-value"><?php echo h((string)$current['company_legal_name'] !== '' ? (string)$current['company_legal_name'] : '-'); ?></div>
                        </div>

                        <div class="brand-info-item">
                            <div class="brand-info-label">Contatti</div>
                            <div class="brand-info-value">
                                <?php
                                $contactParts = [];
                                if (trim((string)$current['company_phone']) !== '') {
                                    $contactParts[] = (string)$current['company_phone'];
                                }
                                if (trim((string)$current['company_email']) !== '') {
                                    $contactParts[] = (string)$current['company_email'];
                                }
                                echo h(!empty($contactParts) ? implode(' • ', $contactParts) : '-');
                                ?>
                            </div>
                        </div>

                        <div class="brand-info-item">
                            <div class="brand-info-label">Dati fiscali</div>
                            <div class="brand-info-value">
                                <?php
                                $fiscalParts = [];
                                if (trim((string)$current['company_vat']) !== '') {
                                    $fiscalParts[] = 'P.IVA ' . (string)$current['company_vat'];
                                }
                                if (trim((string)$current['company_tax_code']) !== '') {
                                    $fiscalParts[] = 'CF ' . (string)$current['company_tax_code'];
                                }
                                echo h(!empty($fiscalParts) ? implode(' • ', $fiscalParts) : '-');
                                ?>
                            </div>
                        </div>

                        <div class="brand-info-item">
                            <div class="brand-info-label">Uso futuro</div>
                            <div class="brand-info-value">
                                Topbar, report PDF, login, schermata mobile e branding generale del software.
                            </div>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>