<?php
require_once __DIR__ . '/../../core/settings.php';
if (auth_check()) redirect();
$errorMessage='';
if (is_post()){
 $r=auth_attempt(trim((string)post('username','')),(string)post('password',''),'web');
 if(!empty($r['ok'])) redirect();
 $errorMessage=(string)($r['message']??'Login error');
}
$theme=app_theme_mode();
$logo=function_exists('app_company_logo')&&app_company_logo()?app_url(app_company_logo()):app_url('assets/img/turnar-logo.svg');
?>
<!DOCTYPE html><html data-theme="<?php echo $theme;?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="<?php echo app_url('assets/css/turnar.css');?>">
<link rel="stylesheet" href="<?php echo app_url('assets/css/turnar-compact.css');?>">
<title>Login</title>
<style>body{display:flex;align-items:center;justify-content:center;min-height:100vh} .c{max-width:420px;width:100%} .card{padding:24px;border-radius:24px} .t{font-size:28px;font-weight:900;text-align:center;background:linear-gradient(135deg,var(--primary),var(--primary-2));-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:12px} .btn{width:100%;margin-top:10px}</style>
</head><body>
<div class="c">
<img src="<?php echo $logo;?>" style="width:70px;display:block;margin:0 auto 10px">
<div class="t">TURNAR</div>
<div class="card">
<?php if($errorMessage):?><div class="alert"><?php echo $errorMessage;?></div><?php endif;?>
<form method="post">
<input name="username" placeholder="Username o email"><br><br>
<input type="password" name="password" placeholder="Password"><br>
<button class="btn btn-primary">ENTRA</button>
</form>
</div>
</div>
</body></html>