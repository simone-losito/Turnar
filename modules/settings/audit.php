<?php
// modules/settings/audit.php

require_once __DIR__ . '/../../core/helpers.php';

require_login();
require_permission('settings.view');

if (!isMaster()) {
    die('Accesso negato');
}

$db = db_connect();

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$res = $db->query("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 200");

$pageTitle = 'Audit sistema';
$pageSubtitle = 'Storico azioni utenti e invii notifiche';
$activeModule = 'settings';

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<div class="content-card">
    <h3>Ultime attività</h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Utente</th>
                    <th>Azione</th>
                    <th>Dettaglio</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($res && $res->num_rows > 0): ?>
                    <?php while($row = $res->fetch_assoc()): ?>
                        <tr>
                            <td><?= h(format_datetime_it($row['created_at'])) ?></td>
                            <td><?= h($row['user_label']) ?></td>
                            <td><?= h($row['action']) ?></td>
                            <td><?= h($row['description']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4">Nessun dato</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>
