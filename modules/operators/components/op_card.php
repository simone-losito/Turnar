<?php
// card operatore

function op_avatar($op){
    $foto = trim((string)($op['foto'] ?? ''));
    if ($foto !== '') {
        return '<div class="entity-avatar lg"><img src="'.op_h($foto).'" alt=""></div>';
    }
    return '<div class="entity-avatar lg">'.op_h(op_initials($op)).'</div>';
}

function op_card($op, $perm){
    $nome = op_name($op);
    $email = $op['email'] ?? '';
    $telefono = $op['telefono'] ?? '';
    $matricola = $op['matricola'] ?? '';
    $livello = $op['livello'] ?? '';
    $username = $op['user_username'] ?? '';
    $attivo = !empty($op['attivo']);
    $id = (int)($op['id'] ?? 0);
    $userId = (int)($op['user_id'] ?? 0);

    ob_start();
    ?>
    <div class="entity-card operator-card">
        <div class="operator-top">
            <?= op_avatar($op) ?>
            <div class="operator-main">
                <div class="operator-name"><?= op_h($nome) ?></div>
                <div class="operator-role"><?= op_h($op['tipologia'] ?? '—') ?></div>
                <div class="operator-badges"><?= op_badges($op) ?></div>
            </div>
        </div>
        <div class="operator-info">
            <div class="operator-info-row"><div class="operator-info-label">Email</div><div class="operator-info-value"><?= op_h($email ?: '-') ?></div></div>
            <div class="operator-info-row"><div class="operator-info-label">Telefono</div><div class="operator-info-value"><?= op_h($telefono ?: '-') ?></div></div>
            <div class="operator-info-row"><div class="operator-info-label">Matricola</div><div class="operator-info-value"><?= op_h($matricola ?: '-') ?></div></div>
            <div class="operator-info-row"><div class="operator-info-label">Livello</div><div class="operator-info-value"><?= op_h($livello ?: '-') ?></div></div>
            <div class="operator-info-row"><div class="operator-info-label">Username</div><div class="operator-info-value"><?= op_h($username ?: '-') ?></div></div>
        </div>
        <div class="operator-footer">
            <span class="status-pill <?= $attivo ? 'is-active' : 'is-inactive' ?>"><?= $attivo ? 'Attivo' : 'Disattivo' ?></span>
            <div class="operator-actions">
                <?php if (!empty($perm['edit'])): ?>
                    <a href="<?= op_h(app_url('modules/operators/edit.php?id='.$id)) ?>" class="btn btn-secondary btn-sm">Modifica</a>
                <?php endif; ?>
                <?php if ($userId > 0 && (!empty($perm['viewUsers']) || !empty($perm['editUsers']))): ?>
                    <a href="<?= op_h(app_url('modules/users/edit.php?id='.$userId)) ?>" class="btn btn-ghost btn-sm">Utente</a>
                <?php endif; ?>
                <?php if (!empty($perm['delete'])): ?>
                    <a href="<?= op_h(app_url('modules/operators/delete.php?id='.$id)) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Eliminare questa persona?');">Elimina</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
