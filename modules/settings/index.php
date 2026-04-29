<?php
// modules/settings/index.php

require_once __DIR__ . '/../../core/helpers.php';

require_login();
require_permission('settings.view');

$pageTitle    = 'Impostazioni';
$pageSubtitle = 'Centro di controllo e configurazione generale di Turnar';
$activeModule = 'settings';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$settingsSections = [
    [
        'key' => 'general',
        'title' => 'Generali',
        'description' => 'Nome software, versione, lingua, timezone, formati data e ora.',
        'status' => 'Attiva',
        'status_class' => 'success',
        'icon' => '⚙️',
        'href' => app_url('modules/settings/general.php'),
        'available' => true,
        'group' => 'Sistema',
    ],
    [
        'key' => 'branding',
        'title' => 'Branding e logo',
        'description' => 'Dati aziendali, logo, topbar e identità visiva.',
        'status' => 'Attiva',
        'status_class' => 'success',
        'icon' => '🏢',
        'href' => app_url('modules/settings/branding.php'),
        'available' => true,
        'group' => 'Sistema',
    ],
    [
        'key' => 'theme',
        'title' => 'Tema e aspetto',
        'description' => 'Dark, light, colori principali e stile del gestionale.',
        'status' => 'Attiva',
        'status_class' => 'success',
        'icon' => '🎨',
        'href' => app_url('modules/settings/theme.php'),
        'available' => true,
        'group' => 'Sistema',
    ],
    [
    'key' => 'email',
    'title' => 'Email SMTP',
    'description' => 'Configurazione posta in uscita e test invio email.',
    'status' => 'Attiva',
    'status_class' => 'success',
    'icon' => '✉️',
    'href' => app_url('modules/settings/email.php'),
    'available' => true,
    'group' => 'Comunicazioni',
],
    [
        'key' => 'assignment_notifications',
        'title' => 'Notifiche turni',
        'description' => 'Regole invio notifiche app/email quando viene assegnato un turno.',
        'status' => 'Attiva',
        'status_class' => 'success',
        'icon' => '🔔',
        'href' => app_url('modules/settings/notifications.php'),
        'available' => true,
        'group' => 'Sistema',
    ],
    [
        'key' => 'users_security',
        'title' => 'Utenti e sicurezza',
        'description' => 'Gestione utenti, ruoli, scope e accessi web/app.',
        'status' => 'Attiva',
        'status_class' => 'success',
        'icon' => '🔐',
        'href' => app_url('modules/users/index.php'),
        'available' => true,
        'group' => 'Accessi',
    ],
    [
        'key' => 'mobile',
        'title' => 'Mobile / App',
        'description' => 'Accesso alla parte mobile/PWA collegata a Turnar.',
        'status' => 'Attiva',
        'status_class' => 'success',
        'icon' => '📱',
        'href' => mobile_url('index.php'),
        'available' => true,
        'group' => 'Comunicazioni',
    ],
    [
        'key' => 'badge',
        'title' => 'Badge dipendenti',
        'description' => 'Gestione personale, foto, tesserini e QR badge pubblico.',
        'status' => 'Attiva',
        'status_class' => 'success',
        'icon' => '🪪',
        'href' => app_url('modules/operators/index.php'),
        'available' => true,
        'group' => 'Operatività',
    ],
    [
        'key' => 'audit',
        'title' => 'Audit',
        'description' => 'Registro operazioni e controllo attività del sistema.',
        'status' => 'Attiva',
        'status_class' => 'success',
        'icon' => '🧾',
        'href' => app_url('modules/settings/audit.php'),
        'available' => true,
        'group' => 'Manutenzione',
    ],
    [
        'key' => 'info',
        'title' => 'Info programma',
        'description' => 'Versione, stato moduli e controlli installazione.',
        'status' => 'Attiva',
        'status_class' => 'success',
        'icon' => 'ℹ️',
        'href' => app_url('modules/settings/info.php'),
        'available' => true,
        'group' => 'Sistema',
    ],
];

$totalSections = count($settingsSections);
$availableSections = 0;
$plannedSections = 0;

foreach ($settingsSections as $section) {
    if (!empty($section['available'])) {
        $availableSections++;
    } else {
        $plannedSections++;
    }
}

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.settings-shell{
    display:flex;
    flex-direction:column;
    gap:18px;
}

.settings-hero{
    display:grid;
    grid-template-columns:minmax(0, 1.6fr) minmax(320px, .9fr);
    gap:16px;
}

.settings-hero-card,
.settings-summary-card,
.settings-section-card{
    background:var(--content-card-bg);
    border:1px solid var(--line);
    border-radius:24px;
    box-shadow:0 18px 40px rgba(0,0,0,.10);
}

.settings-hero-card{
    padding:22px;
    position:relative;
    overflow:hidden;
}

.settings-hero-card::before{
    content:"";
    position:absolute;
    inset:auto -60px -60px auto;
    width:220px;
    height:220px;
    border-radius:999px;
    background:
        radial-gradient(
            circle,
            color-mix(in srgb, var(--primary) 22%, transparent),
            transparent 68%
        );
    pointer-events:none;
}

.settings-hero-top{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
    position:relative;
    z-index:1;
}

.settings-hero-badge{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:9px 14px;
    border-radius:999px;
    border:1px solid color-mix(in srgb, var(--primary) 28%, transparent);
    background:color-mix(in srgb, var(--primary) 12%, transparent);
    color:color-mix(in srgb, var(--primary) 68%, var(--text));
    font-size:12px;
    font-weight:800;
    letter-spacing:.02em;
}

.settings-hero-title{
    margin:14px 0 0;
    font-size:28px;
    line-height:1.1;
    font-weight:900;
    position:relative;
    z-index:1;
    color:var(--text);
}

.settings-hero-text{
    margin:12px 0 0;
    color:var(--muted);
    font-size:14px;
    line-height:1.65;
    max-width:900px;
    position:relative;
    z-index:1;
}

.settings-hero-pills{
    margin-top:18px;
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    position:relative;
    z-index:1;
}

.settings-tag{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:10px 14px;
    border-radius:999px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 86%, transparent);
    color:var(--text);
    font-size:12px;
    font-weight:700;
}

.settings-summary-card{
    padding:18px;
    display:flex;
    flex-direction:column;
    gap:14px;
}

.settings-summary-title{
    font-size:13px;
    font-weight:900;
    letter-spacing:.06em;
    text-transform:uppercase;
    color:var(--muted);
}

.settings-summary-grid{
    display:grid;
    grid-template-columns:1fr;
    gap:10px;
}

.settings-kpi{
    padding:14px 16px;
    border-radius:18px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 82%, transparent);
}

.settings-kpi-label{
    color:var(--muted);
    font-size:12px;
    margin-bottom:6px;
}

.settings-kpi-value{
    font-size:24px;
    font-weight:900;
    line-height:1;
    color:var(--text);
}

.settings-kpi-sub{
    margin-top:8px;
    font-size:12px;
    color:var(--muted);
}

.settings-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));
    gap:16px;
}

.settings-section-card{
    padding:18px;
    display:flex;
    flex-direction:column;
    gap:14px;
    min-height:250px;
    transition:.18s ease;
}

.settings-section-card:hover{
    transform:translateY(-2px);
    border-color:color-mix(in srgb, var(--primary) 22%, transparent);
}

.settings-section-top{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
}

.settings-section-icon{
    width:56px;
    height:56px;
    border-radius:18px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:25px;
    background:linear-gradient(
        135deg,
        color-mix(in srgb, var(--primary) 22%, transparent),
        color-mix(in srgb, var(--primary-2) 18%, transparent)
    );
    border:1px solid var(--line);
    flex:0 0 auto;
    box-shadow:0 10px 24px rgba(0,0,0,.10);
}

.settings-status-pill{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:7px 11px;
    border-radius:999px;
    border:1px solid var(--line);
    font-size:11px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
    white-space:nowrap;
}

.settings-status-pill.warning{
    background:rgba(251,191,36,.14);
    color:#b45309;
    border-color:rgba(251,191,36,.26);
}

.settings-status-pill.info{
    background:color-mix(in srgb, var(--primary) 18%, transparent);
    color:color-mix(in srgb, var(--primary) 68%, var(--text));
    border-color:color-mix(in srgb, var(--primary) 26%, transparent);
}

.settings-status-pill.success{
    background:rgba(52,211,153,.14);
    color:#059669;
    border-color:rgba(52,211,153,.26);
}

.settings-status-pill.danger{
    background:rgba(248,113,113,.14);
    color:#dc2626;
    border-color:rgba(248,113,113,.28);
}

.settings-group-pill{
    display:inline-flex;
    align-items:center;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 84%, transparent);
    color:var(--muted);
    font-size:11px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
    width:max-content;
}

.settings-section-title{
    margin:0;
    font-size:19px;
    font-weight:900;
    line-height:1.2;
    color:var(--text);
}

.settings-section-description{
    margin:0;
    color:var(--muted);
    font-size:14px;
    line-height:1.6;
    flex:1;
}

.settings-section-footer{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
    margin-top:auto;
}

.settings-section-hint{
    color:var(--muted);
    font-size:12px;
    font-weight:700;
}

.settings-card-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.hidden-by-filter{
    display:none !important;
}

@media (max-width: 1100px){
    .settings-hero{
        grid-template-columns:1fr;
    }
}

@media (max-width: 720px){
    .settings-grid{
        grid-template-columns:1fr;
    }

    .settings-hero-title{
        font-size:24px;
    }
}
</style>

<div class="content-card">
    <div class="settings-shell">

        <section class="settings-hero">
            <div class="settings-hero-card">
                <div class="settings-hero-top">
                    <span class="settings-hero-badge">Pannello centrale configurazione</span>
                </div>

                <h2 class="settings-hero-title">Impostazioni complete di Turnar</h2>

                <p class="settings-hero-text">
                    Questa area diventerà il punto unico di configurazione del software:
                    branding, logo aziendale, tema grafico, regole turni, notifiche, email, mobile, sicurezza,
                    backup, moduli e impostazioni generali. La struttura è già pronta per crescere
                    in modo ordinato e mantenere coerenza con tutto il gestionale.
                </p>

                <div class="settings-hero-pills">
                    <span class="settings-tag">Dark UI coerente</span>
                    <span class="settings-tag">Struttura modulare</span>
                    <span class="settings-tag">Notifiche centralizzate</span>
                    <span class="settings-tag">Base per PWA e mobile</span>
                </div>
            </div>

            <aside class="settings-summary-card">
                <div class="settings-summary-title">Stato area impostazioni</div>

                <div class="settings-summary-grid">
                    <div class="settings-kpi">
                        <div class="settings-kpi-label">Sezioni previste</div>
                        <div class="settings-kpi-value"><?php echo (int)$totalSections; ?></div>
                        <div class="settings-kpi-sub">Architettura pronta per la crescita del pannello</div>
                    </div>

                    <div class="settings-kpi">
                        <div class="settings-kpi-label">Sezioni già attive</div>
                        <div class="settings-kpi-value"><?php echo (int)$availableSections; ?></div>
                        <div class="settings-kpi-sub">Le impostazioni principali sono già operative</div>
                    </div>

                    <div class="settings-kpi">
                        <div class="settings-kpi-label">Sezioni da completare</div>
                        <div class="settings-kpi-value"><?php echo (int)$plannedSections; ?></div>
                        <div class="settings-kpi-sub">Ordine consigliato: email, mobile, turni, sicurezza</div>
                    </div>
                </div>
            </aside>
        </section>

        <div class="toolbar">
            <div class="toolbar-left">
                <input
                    type="text"
                    id="settingsSearchInput"
                    class="toolbar-search"
                    placeholder="Cerca una sezione: logo, notifiche, email, tema, backup..."
                    autocomplete="off"
                >

                <select id="settingsGroupFilter" class="field-sm">
                    <option value="all">Tutti i gruppi</option>
                    <option value="Sistema">Sistema</option>
                    <option value="Operatività">Operatività</option>
                    <option value="Comunicazioni">Comunicazioni</option>
                    <option value="Accessi">Accessi</option>
                    <option value="Manutenzione">Manutenzione</option>
                </select>

                <button type="button" id="settingsResetBtn" class="btn btn-ghost">Reset</button>
            </div>

            <div class="toolbar-right">
                <span class="soft-pill">Home impostazioni</span>
            </div>
        </div>

        <div class="text-muted mb-1" style="font-size:13px;">
            <span id="settingsVisibleCount"><?php echo (int)$totalSections; ?></span>
            sezion<span id="settingsCounterSuffix"><?php echo $totalSections === 1 ? 'e visibile' : 'i visibili'; ?></span>
            su <?php echo (int)$totalSections; ?>
        </div>

        <div class="settings-grid" id="settingsGrid">
            <?php foreach ($settingsSections as $section): ?>
                <?php
                $searchBlob = implode(' ', [
                    $section['title'] ?? '',
                    $section['description'] ?? '',
                    $section['status'] ?? '',
                    $section['group'] ?? '',
                ]);
                ?>
                <article
                    class="settings-section-card"
                    data-search="<?php echo h(mb_strtolower($searchBlob, 'UTF-8')); ?>"
                    data-group="<?php echo h($section['group'] ?? ''); ?>"
                >
                    <div class="settings-section-top">
                        <div class="settings-section-icon"><?php echo h($section['icon'] ?? '•'); ?></div>
                        <span class="settings-status-pill <?php echo h($section['status_class'] ?? 'info'); ?>">
                            <?php echo h($section['status'] ?? ''); ?>
                        </span>
                    </div>

                    <span class="settings-group-pill"><?php echo h($section['group'] ?? ''); ?></span>

                    <h3 class="settings-section-title"><?php echo h($section['title'] ?? ''); ?></h3>

                    <p class="settings-section-description">
                        <?php echo h($section['description'] ?? ''); ?>
                    </p>

                    <div class="settings-section-footer">
                        <span class="settings-section-hint">
                            <?php echo !empty($section['available']) ? 'Sezione pronta' : 'Sezione da sviluppare'; ?>
                        </span>

                        <div class="settings-card-actions">
                            <?php if (!empty($section['available']) && !empty($section['href'])): ?>
                                <a href="<?php echo h($section['href']); ?>" class="btn btn-primary btn-sm">Entra</a>
                            <?php else: ?>
                                <button type="button" class="btn btn-ghost btn-sm" disabled style="opacity:.65; cursor:not-allowed;">Presto disponibile</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="empty-state hidden-by-filter" id="settingsEmptyState">
            <h3 class="empty-state-title">Nessuna sezione trovata</h3>
            <div class="empty-state-text">Nessuna sezione corrisponde ai filtri attuali. Prova a cambiare ricerca o gruppo.</div>
        </div>
    </div>
</div>

<script>
(function () {
    const searchInput = document.getElementById('settingsSearchInput');
    const groupFilter = document.getElementById('settingsGroupFilter');
    const resetBtn = document.getElementById('settingsResetBtn');
    const visibleCount = document.getElementById('settingsVisibleCount');
    const counterSuffix = document.getElementById('settingsCounterSuffix');
    const emptyState = document.getElementById('settingsEmptyState');
    const cards = Array.from(document.querySelectorAll('.settings-section-card'));

    if (!searchInput || !groupFilter || !visibleCount || !counterSuffix) {
        return;
    }

    function normalizeText(value) {
        return (value || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function applyFilters() {
        const query = normalizeText(searchInput.value);
        const tokens = query === '' ? [] : query.split(' ').filter(Boolean);
        const selectedGroup = groupFilter.value;

        let matched = 0;

        cards.forEach(function (card) {
            const searchText = normalizeText(card.getAttribute('data-search') || '');
            const group = card.getAttribute('data-group') || '';

            const matchesGroup = selectedGroup === 'all' || group === selectedGroup;
            const matchesSearch =
                tokens.length === 0 ||
                tokens.every(function (token) {
                    return searchText.includes(token);
                });

            const visible = matchesGroup && matchesSearch;
            card.classList.toggle('hidden-by-filter', !visible);

            if (visible) {
                matched++;
            }
        });

        visibleCount.textContent = String(matched);
        counterSuffix.textContent = matched === 1 ? 'e visibile' : 'i visibili';

        if (emptyState) {
            emptyState.classList.toggle('hidden-by-filter', matched !== 0);
        }
    }

    searchInput.addEventListener('input', applyFilters);
    groupFilter.addEventListener('change', applyFilters);

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            searchInput.value = '';
            groupFilter.value = 'all';
            applyFilters();
            searchInput.focus();
        });
    }

    applyFilters();
})();
</script>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>
