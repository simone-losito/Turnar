<?php

require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/settings.php';

require_login();
require_permission('settings.view');

$pageTitle = 'Moduli software';
$pageSubtitle = 'Attiva o disattiva moduli Web / App / Menu';
$activeModule = 'settings';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$modules = [
    'dashboard'=>'Dashboard',
    'operators'=>'Personale',
    'destinations'=>'Destinazioni',
    'assignments'=>'Turni',
    'calendar'=>'Calendario',
    'communications'=>'Comunicazioni',
    'reports'=>'Report',
    'gantt'=>'Gantt',
    'users'=>'Utenti',
    'settings'=>'Impostazioni',
    'mobile'=>'Mobile',
    'badges'=>'Badge',
    'push'=>'Notifiche push',
    'email'=>'Email SMTP'
];

$state = json_decode(setting('modules_matrix','{}'), true);
if(!is_array($state)) $state=[];

if($_SERVER['REQUEST_METHOD']==='POST'){
    $new=[];
    foreach($modules as $k=>$v){
        $new[$k]=[
            'web'=>isset($_POST[$k.'_web'])?1:0,
            'app'=>isset($_POST[$k.'_app'])?1:0,
            'menu'=>isset($_POST[$k.'_menu'])?1:0
        ];
    }
    setting_set('modules_matrix', json_encode($new));
    $state=$new;
}

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<div class="content-card">

<form method="post">

<?php foreach($modules as $k=>$label): 
    $s=$state[$k] ?? ['web'=>1,'app'=>0,'menu'=>1];
?>

<div class="form-section">
    <strong><?php echo h($label); ?></strong><br>

```
<label><input type="checkbox" name="<?php echo $k; ?>_web" <?php if($s['web']) echo 'checked'; ?>> Web</label>
<label><input type="checkbox" name="<?php echo $k; ?>_app" <?php if($s['app']) echo 'checked'; ?>> App</label>
<label><input type="checkbox" name="<?php echo $k; ?>_menu" <?php if($s['menu']) echo 'checked'; ?>> Menu</label>
```

</div>

<?php endforeach; ?>

<button class="btn btn-primary">Salva</button>

</form>

</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>
