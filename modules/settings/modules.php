<?php
// modules/settings/modules.php

require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/settings.php';

require_login();
require_permission('settings.view');

$pageTitle = 'Moduli software';
$pageSubtitle = 'Attiva o disattiva moduli Web / App / Menu';
$activeModule = 'settings';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$modules = [
    'dashboard'      => ['label'=>'Dashboard', 'icon'=>'📊'],
    'operators'      => ['label'=>'Personale', 'icon'=>'👥'],
    'destinations'   => ['label'=>'Destinazioni', 'icon'=>'📍'],
    'assignments'    => ['label'=>'Turni', 'icon'=>'🗓️'],
    'calendar'       => ['label'=>'Calendario', 'icon'=>'📅'],
    'communications' => ['label'=>'Comunicazioni', 'icon'=>'💬'],
    'reports'        => ['label'=>'Report', 'icon'=>'📄'],
    'gantt'          => ['label'=>'Gantt', 'icon'=>'📈'],
    'users'          => ['label'=>'Utenti', 'icon'=>'🔐'],
    'settings'       => ['label'=>'Impostazioni', 'icon'=>'⚙️'],
    'mobile'         => ['label'=>'Mobile', 'icon'=>'📱'],
    'badges'         => ['label'=>'Badge', 'icon'=>'🪪'],
    'push'           => ['label'=>'Notifiche push', 'icon'=>'🔔'],
    'email'          => ['label'=>'Email SMTP', 'icon'=>'✉️'],
];

$lockedModules = ['settings'];

$state = json_decode(setting('modules_matrix','{}'), true);
if(!is_array($state)) $state=[];

$saved = false;

if($_SERVER['REQUEST_METHOD']==='POST'){
    $new=[];

    foreach($modules as $k=>$meta){
        $old = $state[$k] ?? ['web'=>1,'app'=>0,'menu'=>1];

        if(in_array($k, $lockedModules, true)){
            $new[$k] = [
                'web' => 1,
                'app' => !empty($old['app']) ? 1 : 0,
                'menu' => 1,
            ];
            continue;
        }

        $new[$k]=[
            'web'=>isset($_POST[$k.'_web'])?1:0,
            'app'=>isset($_POST[$k.'_app'])?1:0,
            'menu'=>isset($_POST[$k.'_menu'])?1:0
        ];
    }

    setting_set('modules_matrix', json_encode($new, JSON_UNESCAPED_UNICODE));
    $state=$new;
    $saved = true;
}

$webCount = 0; $appCount = 0; $menuCount = 0;
foreach($modules as $k=>$meta){
    $s = $state[$k] ?? ['web'=>1,'app'=>0,'menu'=>1];

    if(in_array($k, $lockedModules, true)){
        $s['web'] = 1;
        $s['menu'] = 1;
    }

    if(!empty($s['web'])) $webCount++;
    if(!empty($s['app'])) $appCount++;
    if(!empty($s['menu'])) $menuCount++;
}

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.modules-shell{display:grid;gap:12px}.modules-hero{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(280px,.7fr);gap:12px}.modules-card,.module-card{border:1px solid var(--line);border-radius:18px;background:linear-gradient(180deg,color-mix(in srgb,var(--bg-4) 78%,transparent),color-mix(in srgb,var(--bg-3) 88%,transparent));box-shadow:var(--shadow);padding:14px}.modules-title{margin:0;font-size:20px;font-weight:950}.modules-text{margin:6px 0 0;color:var(--muted);font-size:12.5px;line-height:1.45}.modules-kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}.modules-kpi{padding:10px;border-radius:14px;border:1px solid var(--line-soft);background:color-mix(in srgb,var(--bg-4) 62%,transparent)}.modules-kpi-label{font-size:10px;font-weight:950;color:var(--muted);text-transform:uppercase}.modules-kpi-value{font-size:22px;font-weight:950;color:var(--text);margin-top:3px}.modules-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(245px,1fr));gap:10px}.module-card{display:grid;gap:10px;padding:12px}.module-card.locked{border-color:color-mix(in srgb,var(--primary) 35%,var(--line));box-shadow:0 0 0 1px color-mix(in srgb,var(--primary) 14%,transparent),var(--shadow)}.module-head{display:flex;align-items:flex-start;gap:10px}.module-icon{width:40px;height:40px;border-radius:14px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--primary),var(--primary-2));font-size:19px;box-shadow:0 10px 20px rgba(0,0,0,.18)}.module-name{font-size:14px;font-weight:950;color:var(--text);line-height:1.1}.module-key{font-size:10.5px;color:var(--muted);font-weight:800;margin-top:4px}.module-toggles{display:flex;gap:6px;flex-wrap:wrap}.pill-toggle{display:inline-flex;align-items:center;gap:6px;padding:7px 10px;border-radius:999px;border:1px solid var(--line);background:color-mix(in srgb,var(--bg-4) 70%,transparent);font-size:11.5px;font-weight:900;color:var(--text);cursor:pointer}.pill-toggle input{width:auto;min-height:0;margin:0;accent-color:var(--primary)}.pill-toggle.locked{opacity:.75;cursor:not-allowed}.pill-toggle.locked input{cursor:not-allowed}.lock-note{font-size:10.5px;color:var(--muted);font-weight:800}.modules-actions{position:sticky;bottom:10px;z-index:20;display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;padding:10px;border:1px solid var(--line);border-radius:18px;background:color-mix(in srgb,var(--bg-2) 92%,transparent);box-shadow:0 14px 32px rgba(0,0,0,.28);backdrop-filter:blur(12px)}.modules-alert{padding:10px 12px;border-radius:16px;border:1px solid rgba(34,197,94,.35);background:rgba(34,197,94,.16);font-size:12px;font-weight:900;color:#dcfce7}html[data-theme="light"] .modules-alert{color:#14532d}@media(max-width:900px){.modules-hero{grid-template-columns:1fr}.modules-kpis{grid-template-columns:1fr}.modules-actions{position:static}}
</style>

<div class="modules-shell">
    <div class="toolbar">
        <div class="toolbar-left">
            <a href="<?php echo h(app_url('modules/settings/index.php')); ?>" class="btn btn-ghost">← Torna a Impostazioni</a>
        </div>
        <div class="toolbar-right"><span class="soft-pill">Web / App / Menu</span></div>
    </div>

    <?php if($saved): ?><div class="modules-alert">Configurazione moduli salvata correttamente.</div><?php endif; ?>

    <section class="modules-hero">
        <div class="modules-card">
            <h2 class="modules-title">Controllo moduli Turnar</h2>
            <p class="modules-text">Gestisci cosa è attivo nel gestionale web, cosa sarà disponibile nell'app mobile e cosa compare nei menu. Impostazioni resta sempre protetto per evitare blocchi di accesso.</p>
        </div>
        <div class="modules-card">
            <div class="modules-kpis">
                <div class="modules-kpi"><div class="modules-kpi-label">Web</div><div class="modules-kpi-value"><?php echo (int)$webCount; ?></div></div>
                <div class="modules-kpi"><div class="modules-kpi-label">App</div><div class="modules-kpi-value"><?php echo (int)$appCount; ?></div></div>
                <div class="modules-kpi"><div class="modules-kpi-label">Menu</div><div class="modules-kpi-value"><?php echo (int)$menuCount; ?></div></div>
            </div>
        </div>
    </section>

    <form method="post">
        <div class="modules-grid">
            <?php foreach($modules as $k=>$meta):
                $s=$state[$k] ?? ['web'=>1,'app'=>0,'menu'=>1];
                $isLocked = in_array($k, $lockedModules, true);

                if($isLocked){
                    $s['web'] = 1;
                    $s['menu'] = 1;
                }
            ?>
                <article class="module-card <?php echo $isLocked ? 'locked' : ''; ?>">
                    <div class="module-head">
                        <div class="module-icon"><?php echo h($meta['icon']); ?></div>
                        <div>
                            <div class="module-name"><?php echo h($meta['label']); ?></div>
                            <div class="module-key"><?php echo h($k); ?></div>
                        </div>
                    </div>

                    <div class="module-toggles">
                        <label class="pill-toggle <?php echo $isLocked ? 'locked' : ''; ?>">
                            <input type="checkbox" name="<?php echo h($k); ?>_web" <?php if(!empty($s['web'])) echo 'checked'; ?> <?php if($isLocked) echo 'disabled'; ?>>
                            Web
                        </label>

                        <label class="pill-toggle">
                            <input type="checkbox" name="<?php echo h($k); ?>_app" <?php if(!empty($s['app'])) echo 'checked'; ?>>
                            App
                        </label>

                        <label class="pill-toggle <?php echo $isLocked ? 'locked' : ''; ?>">
                            <input type="checkbox" name="<?php echo h($k); ?>_menu" <?php if(!empty($s['menu'])) echo 'checked'; ?> <?php if($isLocked) echo 'disabled'; ?>>
                            Menu
                        </label>
                    </div>

                    <?php if($isLocked): ?>
                        <div class="lock-note">Modulo protetto: Web e Menu non possono essere disattivati.</div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="modules-actions">
            <span class="text-muted">Salva per rendere persistente la configurazione.</span>
            <button class="btn btn-primary" type="submit">Salva configurazione</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>
