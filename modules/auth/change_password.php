<?php
// modules/auth/change_password.php

require_once __DIR__ . '/../../core/settings.php';

if (!auth_check()) {
    redirect('modules/auth/login.php');
}

$errorMessage = '';
$successMessage = '';

if (is_post()) {
    $password1 = trim((string)post('password_new', ''));
    $password2 = trim((string)post('password_confirm', ''));

    if ($password1 === '') {
        $errorMessage = 'Inserisci la nuova password.';
    } elseif ($password2 === '') {
        $errorMessage = 'Conferma la nuova password.';
    } elseif ($password1 !== $password2) {
        $errorMessage = 'Le due password non coincidono.';
    } else {
        $result = auth_change_current_user_password($password1);

        if (!empty($result['ok'])) {
            redirect();
        }

        $errorMessage = (string)($result['message'] ?? 'Impossibile cambiare la password.');
    }
}

$pageTitle    = 'Cambio password';
$pageSubtitle = 'Aggiorna la password del tuo account';
$activeModule = '';

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
    .change-wrap{
        display:grid;
        grid-template-columns:minmax(320px, 560px);
        gap:18px;
        justify-content:center;
    }

    .change-card{
        background:linear-gradient(180deg, rgba(255,255,255,.045), rgba(255,255,255,.02));
        border:1px solid rgba(255,255,255,.10);
        border-radius:24px;
        box-shadow:0 18px 40px rgba(0,0,0,.18);
        padding:22px;
    }

    .change-card h2{
        margin:0 0 10px;
        font-size:24px;
    }

    .change-sub{
        margin:0 0 18px;
        color:#aab8d3;
        line-height:1.6;
        font-size:14px;
    }

    .change-form{
        display:grid;
        gap:14px;
    }

    .field{
        display:flex;
        flex-direction:column;
        gap:7px;
    }

    .field label{
        font-size:12px;
        color:#aab8d3;
        font-weight:600;
        letter-spacing:.03em;
    }

    .field input{
        width:100%;
        padding:12px 13px;
        border-radius:14px;
        border:1px solid rgba(255,255,255,.10);
        background:rgba(255,255,255,.04);
        color:#eef4ff;
        outline:none;
        font-size:14px;
    }

    .field input:focus{
        border-color:rgba(110,168,255,.45);
        box-shadow:0 0 0 3px rgba(110,168,255,.12);
    }

    .btn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:8px;
        padding:12px 16px;
        border:none;
        border-radius:14px;
        font-weight:700;
        cursor:pointer;
        text-decoration:none;
        transition:.16s ease;
    }

    .btn-primary{
        background:linear-gradient(135deg, #6ea8ff, #8b5cf6);
        color:#fff;
    }

    .btn:hover{
        transform:translateY(-1px);
        filter:brightness(1.05);
    }

    .alert{
        margin-bottom:16px;
        padding:14px 16px;
        border-radius:16px;
        line-height:1.6;
        font-size:14px;
    }

    .alert-error{
        background:rgba(248,113,113,.10);
        border:1px solid rgba(248,113,113,.24);
        color:#fecaca;
    }

    .alert-info{
        background:rgba(96,165,250,.10);
        border:1px solid rgba(96,165,250,.24);
        color:#dbeafe;
    }
</style>

<div class="change-wrap">
    <section class="change-card">
        <h2>Cambio password obbligatorio</h2>
        <p class="change-sub">
            Ciao <strong><?php echo h(auth_display_name()); ?></strong>, per proseguire devi impostare la password del tuo account.<br>
            Non ci sono vincoli particolari: puoi usare anche la stessa password di prima.
        </p>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-error">
                <?php echo h($errorMessage); ?>
            </div>
        <?php endif; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-info">
                <?php echo h($successMessage); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="change-form" autocomplete="off">
            <div class="field">
                <label for="password_new">Nuova password</label>
                <input
                    type="password"
                    id="password_new"
                    name="password_new"
                    value=""
                    placeholder="Inserisci la nuova password"
                    autofocus
                >
            </div>

            <div class="field">
                <label for="password_confirm">Conferma password</label>
                <input
                    type="password"
                    id="password_confirm"
                    name="password_confirm"
                    value=""
                    placeholder="Ripeti la password"
                >
            </div>

            <button type="submit" class="btn btn-primary">Salva password e continua</button>
        </form>
    </section>
</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>