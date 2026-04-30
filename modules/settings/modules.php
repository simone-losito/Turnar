<?php
// modules/settings/modules.php
// Matrice moduli software per ruolo

require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/settings.php';

require_login();
require_permission('settings.view');

// Solo Master può cambiare la matrice moduli
if (!function_exists('is_master') || !is_master()) {
    http_response_code(403);
    exit('Accesso negato: solo un utente Master può configurare i moduli software.');
}

$pageTitle = 'Moduli software';
$pageSubtitle = 'Gestione accesso moduli per Manager e User';
$activeModule = 'settings';

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$modules = [
    'dashboard'      => ['label'=>'Dashboard', 'icon'=>'📊', 'note'=>'Cruscotto operativo'],
    'operators'      => ['label'=>'Personale', 'icon'=>'👥', 'note'=>'Anagrafiche operatori'],
    'destinations'   => ['label'=>'Destinazioni', 'icon'=>'📍', 'note'=>'Cantieri e destinazioni'],
    'assignments'    => ['label'=>'Turni', 'icon'=>'🗓️', 'note'=>'Planning e gestione turni'],
    'calendar'       => ['label'=>'Calendario', 'icon'=>'📅', 'note'=>'Vista calendario turni'],
    'communications' => ['label'=>'Comunicazioni', 'icon'=>'💬', 'note'=>'Comunicazioni interne'],
    'reports'        => ['label'=>'Report', 'icon'=>'📄', 'note'=>'Analisi, export e stampe'],
    'gantt'          => ['label'=>'Gantt', 'icon'=>'📈', 'note'=>'Viste Gantt report'],
    'users'          => ['label'=>'Utenti', 'icon'=>'🔐', 'note'=>'Account e permessi'],
    'settings'       => ['label'=>'Impostazioni', 'icon'=>'⚙️', 'note'=>'Configurazione sistema'],
    'mobile'         => ['label'=>'Mobile / App', 'icon'=>'📱', 'note'=>'Area mobile operatori'],
    'badges'         => ['label'=>'Badge dipendenti', 'icon'=>'🪪', 'note'=>'Badge e identificativi'],
    'push'           => ['label'=>'Notifiche push', 'icon'=>'🔔', 'note'=>'Notifiche app/mobile'],
    'email'          => ['label'=>'Email SMTP', 'icon'=>'✉️', 'note'=>'Invio email sistema'],
];

$lockedModules = ['settings'];
$lockedFields = [
    'settings' => ['web', 'menu'],
];

$defaults = function_exists('turnar_default_modules_matrix') ? turnar_default_modules_matrix() : [];
$state = function_exists('turnar_modules_matrix') ? turnar_modules_matrix() : [];

foreach ($modules as $key => $meta) {
    if (!isset($state[$key]) || !is_array($state[$key])) {
        $state[$key] = $defaults[$key] ?? [
            'web'=>1,'app'=>0,'menu'=>1,
            'manager_web'=>1,'manager_app'=>0,'manager_menu'=>1,
            'user_web'=>0,'user_app'=>0,'user_menu'=>0,
        ];
    }
}

$saved = false;

if (is_post()) {
    $new = [];

    foreach ($modules as $key => $meta) {
        $old = $state[$key] ?? [];

        $row = [
            'web'          => isset($_POST[$key . '_web']) ? 1 : 0,
            'app'          => isset($_POST[$key . '_app']) ? 1 : 0,
            'menu'         => isset($_POST[$key . '_menu']) ? 1 : 0,
            'manager_web'  => isset($_POST[$key . '_manager_web']) ? 1 : 0,
            'manager_app'  => isset($_POST[$key . '_manager_app']) ? 1 : 0,
            'manager_menu' => isset($_POST[$key . '_manager_menu']) ? 1 : 0,
            'user_web'     => isset($_POST[$key . '_user_web']) ? 1 : 0,
            'user_app'     => isset($_POST[$key . '_user_app']) ? 1 : 0,
            'user_menu'    => isset($_POST[$key . '_user_menu']) ? 1 : 0,
        ];

        // Impostazioni sempre protette: non si può chiudere fuori il Master.
        if ($key === 'settings') {
            $row = [
                'web'          => 1,
                'app'          => 0,
                'menu'         => 1,
                'manager_web'  => 0,
                'manager_app'  => 0,
                'manager_menu' => 0,
                'user_web'     => 0,
                'user_app'     => 0,
                'user_menu'    => 0,
            ];
        }

        // Coerenza: se globale spento, anche ruoli spenti per quel canale.
        if (empty($row['web'])) {
            $row['menu'] = 0;
            $row['manager_web'] = 0;
            $row['manager_menu'] = 0;
            $row['user_web'] = 0;
            $row['user_menu'] = 0;
        }
        if (empty($row['menu'])) {
            $row['manager_menu'] = 0;
            $row['user_menu'] = 0;
        }
        if (empty($row['app'])) {
            $row['manager_app'] = 0;
            $row['user_app'] = 0;
        }

        $new[$key] = $row;
    }

    setting_set('modules_matrix', json_encode($new, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $state = $new;
    $saved = true;
}

$counts = [
    'global_web' => 0,
    'manager_web' => 0,
    'user_web' => 0,
    'app' => 0,
];

foreach ($modules as $key => $meta) {
    $s = $state[$key] ?? [];
    if (!empty($s['web'])) $counts['global_web']++;
    if (!empty($s['manager_web'])) $counts['manager_web']++;
    if (!empty($s['user_web'])) $counts['user_web']++;
    if (!empty($s['app'])) $counts['app']++;
}

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.modules-role-shell{display:grid;gap:12px}.modules-role-hero{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(280px,.75fr);gap:12px}.modules-role-card{border:1px solid var(--line);border-radius:18px;background:linear-gradient(180deg,color-mix(in srgb,var(--bg-4) 78%,transparent),color-mix(in srgb,var(--bg-3) 88%,transparent));box-shadow:var(--shadow);padding:14px}.modules-role-title{margin:0;font-size:20px;font-weight:950}.modules-role-text{margin:6px 0 0;color:var(--muted);font-size:12.5px;line-height:1.45}.modules-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}.modules-kpi{padding:10px;border-radius:14px;border:1px solid var(--line-soft);background:color-mix(in srgb,var(--bg-4) 62%,transparent)}.modules-kpi-label{font-size:10px;font-weight:950;color:var(--muted);text-transform:uppercase}.modules-kpi-value{font-size:20px;font-weight:950;color:var(--text);margin-top:3px}.modules-table-wrap{overflow:auto;border:1px solid var(--line);border-radius:18px;background:color-mix(in srgb,var(--bg-3) 88%,transparent);box-shadow:var(--shadow)}.modules-table{width:100%;border-collapse:separate;border-spacing:0;min-width:1040px}.modules-table th,.modules-table td{padding:9px 10px;border-bottom:1px solid var(--line-soft);vertical-align:middle}.modules-table th{position:sticky;top:0;z-index:2;background:color-mix(in srgb,var(--bg-2) 94%,transparent);font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);font-weight:950;text-align:center}.modules-table th:first-child{text-align:left}.modules-table tr:last-child td{border-bottom:0}.module-cell{display:flex;align-items:center;gap:10px;min-width:250px}.module-icon{width:34px;height:34px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--primary),var(--primary-2));font-size:17px;box-shadow:0 10px 20px rgba(0,0,0,.16);flex:0 0 auto}.module-name{font-size:13px;font-weight:950;color:var(--text);line-height:1.1}.module-key{font-size:10.5px;color:var(--muted);font-weight:800;margin-top:3px}.module-note{font-size:10.5px;color:var(--muted);margin-top:2px}.check-cell{text-align:center}.switch-pill{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:6px 9px;border-radius:999px;border:1px solid var(--line);background:color-mix(in srgb,var(--bg-4) 70%,transparent);font-size:11px;font-weight:900;color:var(--text);cursor:pointer;white-space:nowrap}.switch-pill input{width:auto;min-height:0;margin:0;accent-color:var(--primary)}.switch-pill.locked{opacity:.7;cursor:not-allowed}.switch-pill.locked input{cursor:not-allowed}.locked-row{box-shadow:inset 4px 0 0 color-mix(in srgb,var(--primary) 70%,transparent)}.role-separator{border-left:1px solid var(--line)!important}.modules-alert{padding:10px 12px;border-radius:16px;border:1px solid rgba(34,197,94,.35);background:rgba(34,197,94,.16);font-size:12px;font-weight:900;color:#dcfce7}html[data-theme="light"] .modules-alert{color:#14532d}.modules-warning{padding:10px 12px;border-radius:16px;border:1px solid rgba(251,191,36,.32);background:rgba(251,191,36,.12);font-size:12px;font-weight:850;color:#fef3c7}html[data-theme="light"] .modules-warning{color:#78350f}.modules-actions{position:sticky;bottom:10px;z-index:20;display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;padding:10px;border:1px solid var(--line);border-radius:18px;background:color-mix(in srgb,var(--bg-2) 92%,transparent);box-shadow:0 14px 32px rgba(0,0,0,.28);backdrop-filter:blur(12px)}@media(max-width:900px){.modules-role-hero{grid-template-columns:1fr}.modules-kpis{grid-template-columns:repeat(2,1fr)}.modules-actions{position:static}}
</style>

<div class="modules-role-shell">
    <div class="toolbar">
        <div class="toolbar-left">
            <a href="<?php echo h(app_url('modules/settings/index.php')); ?>" class="btn btn-ghost">← Torna a Impostazioni</a>
        </div>
        <div class="toolbar-right"><span class="soft-pill">Master sempre tutto</span></div>
    </div>

    <?php if ($saved): ?>
        <div class="modules-alert">Configurazione moduli salvata correttamente.</div>
    <?php endif; ?>

    <div class="modules-warning">
        Master vede sempre tutto. <strong>Impostazioni</strong> e <strong>Moduli software</strong> sono protetti: non possono essere disattivati, per evitare di chiudersi fuori dal sistema.
    </div>

    <section class="modules-role-hero">
        <div class="modules-role-card">
            <h2 class="modules-role-title">Controllo moduli per ruolo</h2>
            <p class="modules-role-text">
                Attiva il modulo a livello globale e poi scegli cosa può usare il Manager e cosa può usare lo User.
                Le colonne Web/Menu controllano il gestionale desktop; App controlla la parte mobile.
            </p>
        </div>
        <div class="modules-role-card">
            <div class="modules-kpis">
                <div class="modules-kpi"><div class="modules-kpi-label">Global Web</div><div class="modules-kpi-value"><?php echo (int)$counts['global_web']; ?></div></div>
                <div class="modules-kpi"><div class="modules-kpi-label">Manager</div><div class="modules-kpi-value"><?php echo (int)$counts['manager_web']; ?></div></div>
                <div class="modules-kpi"><div class="modules-kpi-label">User</div><div class="modules-kpi-value"><?php echo (int)$counts['user_web']; ?></div></div>
                <div class="modules-kpi"><div class="modules-kpi-label">App</div><div class="modules-kpi-value"><?php echo (int)$counts['app']; ?></div></div>
            </div>
        </div>
    </section>

    <form method="post">
        <div class="modules-table-wrap">
            <table class="modules-table">
                <thead>
                    <tr>
                        <th>Modulo</th>
                        <th>Globale Web</th>
                        <th>Globale Menu</th>
                        <th>Globale App</th>
                        <th class="role-separator">Manager Web</th>
                        <th>Manager Menu</th>
                        <th>Manager App</th>
                        <th class="role-separator">User Web</th>
                        <th>User Menu</th>
                        <th>User App</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modules as $key => $meta): ?>
                        <?php
                            $s = $state[$key] ?? [];
                            $isLocked = in_array($key, $lockedModules, true);
                            if ($key === 'settings') {
                                $s = [
                                    'web'=>1,'menu'=>1,'app'=>0,
                                    'manager_web'=>0,'manager_menu'=>0,'manager_app'=>0,
                                    'user_web'=>0,'user_menu'=>0,'user_app'=>0,
                                ];
                            }

                            $fields = [
                                'web' => 'Web',
                                'menu' => 'Menu',
                                'app' => 'App',
                                'manager_web' => 'M Web',
                                'manager_menu' => 'M Menu',
                                'manager_app' => 'M App',
                                'user_web' => 'U Web',
                                'user_menu' => 'U Menu',
                                'user_app' => 'U App',
                            ];
                        ?>
                        <tr class="<?php echo $isLocked ? 'locked-row' : ''; ?>">
                            <td>
                                <div class="module-cell">
                                    <div class="module-icon"><?php echo h($meta['icon']); ?></div>
                                    <div>
                                        <div class="module-name"><?php echo h($meta['label']); ?></div>
                                        <div class="module-key"><?php echo h($key); ?></div>
                                        <div class="module-note"><?php echo h($meta['note']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <?php foreach ($fields as $field => $label): ?>
                                <?php
                                    $locked = false;
                                    if ($key === 'settings') {
                                        $locked = true;
                                    }
                                    $checked = !empty($s[$field]);
                                ?>
                                <td class="check-cell <?php echo in_array($field, ['manager_web','user_web'], true) ? 'role-separator' : ''; ?>">
                                    <label class="switch-pill <?php echo $locked ? 'locked' : ''; ?>">
                                        <input
                                            type="checkbox"
                                            name="<?php echo h($key . '_' . $field); ?>"
                                            <?php echo $checked ? 'checked' : ''; ?>
                                            <?php echo $locked ? 'disabled' : ''; ?>
                                        >
                                        <?php echo h($label); ?>
                                    </label>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="modules-actions">
            <span class="text-muted">Salva la matrice per applicarla a menu, accesso web e app mobile.</span>
            <button class="btn btn-primary" type="submit">Salva configurazione</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>
