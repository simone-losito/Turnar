<?php
// modules/users/index.php

require_once __DIR__ . '/../../core/helpers.php';

require_login();
require_permission('users.view');

$pageTitle    = 'Gestione Utenti';
$pageSubtitle = 'Gestione accessi, ruoli e permessi';
$activeModule = 'users';

$db = db_connect();

$sql = "
    SELECT
        u.*,
        d.nome AS dip_nome,
        d.cognome AS dip_cognome,
        d.tipologia AS dip_tipologia,
        d.attivo AS dip_attivo
    FROM users u
    LEFT JOIN dipendenti d ON d.id = u.dipendente_id
    ORDER BY
        COALESCE(d.cognome, '') ASC,
        COALESCE(d.nome, '') ASC,
        u.username ASC
";
$res = $db->query($sql);

$users = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
}

$totaleUtenti = count($users);

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.users-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
    gap:16px;
}

.user-card{
    display:flex;
    flex-direction:column;
    gap:14px;
}

.user-top{
    display:flex;
    align-items:center;
    gap:12px;
    min-width:0;
}

.user-main,
.user-row-title{
    min-width:0;
}

.user-name,
.user-row-name{
    color:var(--text);
    line-height:1.2;
    word-break:break-word;
}

.user-name{
    font-size:16px;
    font-weight:800;
}

.user-row-name{
    font-size:15px;
    font-weight:900;
}

.user-role{
    margin-top:5px;
    font-size:12px;
    color:var(--muted);
}

.user-badges{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    margin-top:8px;
}

.user-info{
    display:grid;
    gap:8px;
    font-size:13px;
    color:var(--text);
}

.user-info-row{
    display:flex;
    gap:8px;
    align-items:flex-start;
    min-width:0;
}

.user-info-label{
    color:var(--muted);
    min-width:110px;
    flex:0 0 auto;
}

.user-info-value{
    word-break:break-word;
}

.user-footer{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    margin-top:auto;
}

.user-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.users-grid-wrap{
    display:block;
}

.users-grid-wrap.hidden{
    display:none;
}

.users-list-wrap{
    display:none;
}

.users-list-wrap.active{
    display:block;
}

.users-list{
    display:flex;
    flex-direction:column;
    gap:10px;
}

.user-row{
    display:grid;
    grid-template-columns:minmax(280px, 2.2fr) minmax(180px, 1.2fr) minmax(160px, 1fr) minmax(170px, 1fr) minmax(180px, 1.1fr) auto;
    gap:12px;
    align-items:center;
}

.user-row-main{
    display:flex;
    align-items:center;
    gap:12px;
    min-width:0;
}

.user-row-badges{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    margin-top:5px;
}

.user-row-col{
    min-width:0;
    display:flex;
    flex-direction:column;
    gap:4px;
}

.user-row-label{
    color:var(--muted);
    font-size:11px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.04em;
}

.user-row-value{
    color:var(--text);
    font-size:13px;
    font-weight:700;
    word-break:break-word;
}

.user-row-actions{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:8px;
    flex-wrap:wrap;
}

@media (max-width: 1280px){
    .user-row{
        grid-template-columns:1fr;
        align-items:flex-start;
    }

    .user-row-actions{
        justify-content:flex-start;
    }
}

@media (max-width: 720px){
    .users-grid{
        grid-template-columns:1fr;
    }

    .user-info-label{
        min-width:88px;
    }
}
</style>

<div class="content-card">

    <div class="toolbar" autocomplete="off">
        <div class="toolbar-left">
            <input
                type="text"
                id="usersSearchInput"
                placeholder="Cerca nome, cognome, username, email, ruolo, scope..."
                class="toolbar-search"
                autocomplete="off"
            >

            <select id="usersStatusSelect" class="field-sm">
                <option value="all">Tutti</option>
                <option value="active">Attivi</option>
                <option value="inactive">Disattivi</option>
                <option value="with-personal">Con personale collegato</option>
                <option value="without-personal">Senza personale collegato</option>
                <option value="web-access">Con accesso web</option>
                <option value="app-access">Con accesso app</option>
                <option value="webapp-access">Web + App</option>
                <option value="must-change">Cambio password</option>
                <option value="admin">Amministrativi</option>
            </select>

            <div class="view-toggle">
                <button type="button" class="toggle-item active" id="viewCardsBtn">Vista card</button>
                <button type="button" class="toggle-item" id="viewListBtn">Vista elenco</button>
            </div>

            <button type="button" id="usersResetBtn" class="btn btn-ghost">Reset</button>
        </div>

        <div class="toolbar-right">
            <?php if (can('users.permissions')): ?>
                <a href="<?php echo h(app_url('modules/users/roles.php')); ?>" class="btn btn-ghost">Permessi ruoli</a>
            <?php endif; ?>

            <?php if (can('users.create')): ?>
                <a href="<?php echo h(app_url('modules/users/edit.php')); ?>" class="btn btn-primary">+ Nuovo utente</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="text-muted mb-3" style="font-size:13px;">
        <span id="usersVisibleCount"><?php echo $totaleUtenti; ?></span>
        utent<?php echo $totaleUtenti === 1 ? 'e' : 'i'; ?>
        <span id="usersCounterSuffix"><?php echo $totaleUtenti === 1 ? ' visibile' : ' visibili'; ?></span>
        su <?php echo $totaleUtenti; ?>
    </div>

    <?php if (!empty($users)): ?>

        <div class="users-grid-wrap" id="usersGridWrap">
            <div class="users-grid" id="usersGrid">
                <?php foreach ($users as $user): ?>
                    <?php
                        $id = (int)($user['id'] ?? 0);
                        $username = trim((string)($user['username'] ?? ''));
                        $email = trim((string)($user['email'] ?? ''));
                        $role = trim((string)($user['role'] ?? ROLE_USER));
                        $scope = trim((string)($user['scope'] ?? SCOPE_SELF));
                        $isActive = !empty($user['is_active']);
                        $web = !empty($user['can_login_web']);
                        $app = !empty($user['can_login_app']);
                        $mustChange = !empty($user['must_change_password']);
                        $admin = !empty($user['is_administrative']);
                        $lastLogin = trim((string)($user['last_login_at'] ?? ''));

                        $nome = trim((string)($user['dip_nome'] ?? ''));
                        $cognome = trim((string)($user['dip_cognome'] ?? ''));
                        $tipologia = trim((string)($user['dip_tipologia'] ?? ''));
                        $nomeCompleto = trim($nome . ' ' . $cognome);
                        $nomeInvertito = trim($cognome . ' ' . $nome);

                        $hasLinkedPerson = ($nome !== '' || $cognome !== '');
                        $linkedPerson = $hasLinkedPerson ? trim($nome . ' ' . $cognome) : 'Account standalone';

                        $iniziali = '';
                        if ($nome !== '') {
                            $iniziali .= mb_strtoupper(mb_substr($nome, 0, 1, 'UTF-8'), 'UTF-8');
                        }
                        if ($cognome !== '') {
                            $iniziali .= mb_strtoupper(mb_substr($cognome, 0, 1, 'UTF-8'), 'UTF-8');
                        }
                        if ($iniziali === '') {
                            $baseInit = $username !== '' ? $username : 'US';
                            $iniziali = mb_strtoupper(mb_substr($baseInit, 0, 2, 'UTF-8'), 'UTF-8');
                        }
                        if ($iniziali === '') {
                            $iniziali = 'US';
                        }

                        $displayName = $nomeCompleto !== '' ? $nomeCompleto : ($username !== '' ? $username : 'Utente senza nome');

                        $searchBlob = implode(' ', [
                            $nome,
                            $cognome,
                            $nomeCompleto,
                            $nomeInvertito,
                            $username,
                            $email,
                            $role,
                            $scope,
                            $tipologia,
                            $linkedPerson,
                            $admin ? 'amministrativo' : '',
                            $mustChange ? 'cambio password' : '',
                        ]);

                        $hasWebApp = $web && $app;
                    ?>

                    <div
                        class="entity-card user-card"
                        data-search="<?php echo h(mb_strtolower($searchBlob, 'UTF-8')); ?>"
                        data-status="<?php echo $isActive ? 'active' : 'inactive'; ?>"
                        data-linked-person="<?php echo $hasLinkedPerson ? '1' : '0'; ?>"
                        data-web-access="<?php echo $web ? '1' : '0'; ?>"
                        data-app-access="<?php echo $app ? '1' : '0'; ?>"
                        data-webapp-access="<?php echo $hasWebApp ? '1' : '0'; ?>"
                        data-must-change="<?php echo $mustChange ? '1' : '0'; ?>"
                        data-admin="<?php echo $admin ? '1' : '0'; ?>"
                    >
                        <div class="user-top">
                            <div class="entity-avatar lg"><?php echo h($iniziali); ?></div>

                            <div class="user-main">
                                <div class="user-name"><?php echo h($displayName); ?></div>
                                <div class="user-role">
                                    <?php echo h($username !== '' ? '@' . $username : 'Username non impostato'); ?>
                                </div>

                                <div class="user-badges">
                                    <span class="mini-pill user-role"><?php echo h(role_label($role)); ?></span>
                                    <span class="mini-pill app-flag"><?php echo h(scope_label($scope)); ?></span>

                                    <?php if ($web): ?>
                                        <span class="mini-pill app-flag">Web</span>
                                    <?php endif; ?>

                                    <?php if ($app): ?>
                                        <span class="mini-pill app-flag">App</span>
                                    <?php endif; ?>

                                    <?php if ($admin): ?>
                                        <span class="mini-pill flag-role">Amministrativo</span>
                                    <?php endif; ?>

                                    <?php if ($mustChange): ?>
                                        <span class="mini-pill password-flag">Cambio password</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="user-info">
                            <div class="user-info-row">
                                <div class="user-info-label">Email</div>
                                <div class="user-info-value"><?php echo h($email !== '' ? $email : '-'); ?></div>
                            </div>

                            <div class="user-info-row">
                                <div class="user-info-label">Personale</div>
                                <div class="user-info-value">
                                    <?php echo h($linkedPerson); ?>
                                    <?php if ($hasLinkedPerson && $tipologia !== ''): ?>
                                        <span class="text-muted"> · <?php echo h($tipologia); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="user-info-row">
                                <div class="user-info-label">Ultimo accesso</div>
                                <div class="user-info-value"><?php echo h($lastLogin !== '' ? format_datetime_it($lastLogin) : '-'); ?></div>
                            </div>
                        </div>

                        <div class="user-footer">
                            <span class="status-pill <?php echo $isActive ? 'is-active' : 'is-inactive'; ?>">
                                <?php echo $isActive ? 'Attivo' : 'Disattivo'; ?>
                            </span>

                            <div class="user-actions">
                                <?php if ($hasLinkedPerson && !empty($user['dipendente_id'])): ?>
                                    <a href="<?php echo h(app_url('modules/operators/edit.php?id=' . (int)$user['dipendente_id'])); ?>" class="btn btn-ghost btn-sm">
                                        Personale
                                    </a>
                                <?php endif; ?>

                                <?php if (can('users.edit')): ?>
                                    <a href="<?php echo h(app_url('modules/users/edit.php?id=' . $id)); ?>" class="btn btn-secondary btn-sm">
                                        Modifica
                                    </a>
                                <?php endif; ?>

                                <?php if (can('users.delete')): ?>
                                    <a
                                        href="<?php echo h(app_url('modules/users/delete.php?id=' . $id)); ?>"
                                        class="btn btn-danger btn-sm"
                                        onclick="return confirm('Sei sicuro di voler eliminare questo utente?');"
                                    >
                                        Elimina
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="users-list-wrap" id="usersListWrap">
            <div class="users-list" id="usersList">
                <?php foreach ($users as $user): ?>
                    <?php
                        $id = (int)($user['id'] ?? 0);
                        $username = trim((string)($user['username'] ?? ''));
                        $email = trim((string)($user['email'] ?? ''));
                        $role = trim((string)($user['role'] ?? ROLE_USER));
                        $scope = trim((string)($user['scope'] ?? SCOPE_SELF));
                        $isActive = !empty($user['is_active']);
                        $web = !empty($user['can_login_web']);
                        $app = !empty($user['can_login_app']);
                        $mustChange = !empty($user['must_change_password']);
                        $admin = !empty($user['is_administrative']);
                        $lastLogin = trim((string)($user['last_login_at'] ?? ''));

                        $nome = trim((string)($user['dip_nome'] ?? ''));
                        $cognome = trim((string)($user['dip_cognome'] ?? ''));
                        $tipologia = trim((string)($user['dip_tipologia'] ?? ''));
                        $nomeCompleto = trim($nome . ' ' . $cognome);
                        $nomeInvertito = trim($cognome . ' ' . $nome);

                        $hasLinkedPerson = ($nome !== '' || $cognome !== '');
                        $linkedPerson = $hasLinkedPerson ? trim($nome . ' ' . $cognome) : 'Account standalone';

                        $iniziali = '';
                        if ($nome !== '') {
                            $iniziali .= mb_strtoupper(mb_substr($nome, 0, 1, 'UTF-8'), 'UTF-8');
                        }
                        if ($cognome !== '') {
                            $iniziali .= mb_strtoupper(mb_substr($cognome, 0, 1, 'UTF-8'), 'UTF-8');
                        }
                        if ($iniziali === '') {
                            $baseInit = $username !== '' ? $username : 'US';
                            $iniziali = mb_strtoupper(mb_substr($baseInit, 0, 2, 'UTF-8'), 'UTF-8');
                        }
                        if ($iniziali === '') {
                            $iniziali = 'US';
                        }

                        $displayName = $nomeCompleto !== '' ? $nomeCompleto : ($username !== '' ? $username : 'Utente senza nome');

                        $searchBlob = implode(' ', [
                            $nome,
                            $cognome,
                            $nomeCompleto,
                            $nomeInvertito,
                            $username,
                            $email,
                            $role,
                            $scope,
                            $tipologia,
                            $linkedPerson,
                            $admin ? 'amministrativo' : '',
                            $mustChange ? 'cambio password' : '',
                        ]);

                        $hasWebApp = $web && $app;
                    ?>

                    <div
                        class="entity-row user-row"
                        data-search="<?php echo h(mb_strtolower($searchBlob, 'UTF-8')); ?>"
                        data-status="<?php echo $isActive ? 'active' : 'inactive'; ?>"
                        data-linked-person="<?php echo $hasLinkedPerson ? '1' : '0'; ?>"
                        data-web-access="<?php echo $web ? '1' : '0'; ?>"
                        data-app-access="<?php echo $app ? '1' : '0'; ?>"
                        data-webapp-access="<?php echo $hasWebApp ? '1' : '0'; ?>"
                        data-must-change="<?php echo $mustChange ? '1' : '0'; ?>"
                        data-admin="<?php echo $admin ? '1' : '0'; ?>"
                    >
                        <div class="user-row-main">
                            <div class="entity-avatar md"><?php echo h($iniziali); ?></div>

                            <div class="user-row-title">
                                <div class="user-row-name"><?php echo h($displayName); ?></div>
                                <div class="user-row-badges">
                                    <span class="mini-pill user-role"><?php echo h(role_label($role)); ?></span>
                                    <span class="mini-pill app-flag"><?php echo h(scope_label($scope)); ?></span>

                                    <?php if ($web): ?>
                                        <span class="mini-pill app-flag">Web</span>
                                    <?php endif; ?>

                                    <?php if ($app): ?>
                                        <span class="mini-pill app-flag">App</span>
                                    <?php endif; ?>

                                    <?php if ($admin): ?>
                                        <span class="mini-pill flag-role">Amministrativo</span>
                                    <?php endif; ?>

                                    <?php if ($mustChange): ?>
                                        <span class="mini-pill password-flag">Cambio password</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="user-row-col">
                            <div class="user-row-label">Email / Username</div>
                            <div class="user-row-value">
                                <?php
                                $parts = [];
                                if ($email !== '') {
                                    $parts[] = $email;
                                }
                                if ($username !== '') {
                                    $parts[] = '@' . $username;
                                }
                                echo h(!empty($parts) ? implode(' • ', $parts) : '-');
                                ?>
                            </div>
                        </div>

                        <div class="user-row-col">
                            <div class="user-row-label">Stato</div>
                            <div class="user-row-value">
                                <span class="status-pill <?php echo $isActive ? 'is-active' : 'is-inactive'; ?>">
                                    <?php echo $isActive ? 'Attivo' : 'Disattivo'; ?>
                                </span>
                            </div>
                        </div>

                        <div class="user-row-col">
                            <div class="user-row-label">Accessi</div>
                            <div class="user-row-value">
                                <?php
                                $parts = [];
                                if ($web) {
                                    $parts[] = 'Web';
                                }
                                if ($app) {
                                    $parts[] = 'App';
                                }
                                if ($mustChange) {
                                    $parts[] = 'Cambio password';
                                }
                                echo h(!empty($parts) ? implode(' • ', $parts) : '-');
                                ?>
                            </div>
                        </div>

                        <div class="user-row-col">
                            <div class="user-row-label">Personale collegato</div>
                            <div class="user-row-value">
                                <?php
                                $parts = [];
                                $parts[] = $linkedPerson;
                                if ($hasLinkedPerson && $tipologia !== '') {
                                    $parts[] = $tipologia;
                                }
                                echo h(implode(' • ', array_filter($parts)));
                                ?>
                            </div>
                        </div>

                        <div class="user-row-actions">
                            <?php if ($hasLinkedPerson && !empty($user['dipendente_id'])): ?>
                                <a href="<?php echo h(app_url('modules/operators/edit.php?id=' . (int)$user['dipendente_id'])); ?>" class="btn btn-ghost btn-sm">
                                    Personale
                                </a>
                            <?php endif; ?>

                            <?php if (can('users.edit')): ?>
                                <a href="<?php echo h(app_url('modules/users/edit.php?id=' . $id)); ?>" class="btn btn-secondary btn-sm">
                                    Modifica
                                </a>
                            <?php endif; ?>

                            <?php if (can('users.delete')): ?>
                                <a
                                    href="<?php echo h(app_url('modules/users/delete.php?id=' . $id)); ?>"
                                    class="btn btn-danger btn-sm"
                                    onclick="return confirm('Sei sicuro di voler eliminare questo utente?');"
                                >
                                    Elimina
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="empty-state hidden-by-filter" id="usersEmptyState">
            <h3 class="empty-state-title">Nessun utente trovato</h3>
            <div class="empty-state-text">Nessun utente corrisponde ai filtri attuali. Prova a cambiare ricerca o selezione stato.</div>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <h3 class="empty-state-title">Nessun utente presente</h3>
            <div class="empty-state-text">Non ci sono ancora utenti configurati nel sistema.</div>
        </div>
    <?php endif; ?>

</div>

<script>
(function () {
    const searchInput = document.getElementById('usersSearchInput');
    const statusSelect = document.getElementById('usersStatusSelect');
    const resetBtn = document.getElementById('usersResetBtn');
    const visibleCount = document.getElementById('usersVisibleCount');
    const counterSuffix = document.getElementById('usersCounterSuffix');
    const emptyState = document.getElementById('usersEmptyState');

    const gridWrap = document.getElementById('usersGridWrap');
    const listWrap = document.getElementById('usersListWrap');
    const cardsBtn = document.getElementById('viewCardsBtn');
    const listBtn = document.getElementById('viewListBtn');

    const allItems = Array.from(document.querySelectorAll('.user-card, .user-row'));

    if (!searchInput || !statusSelect || !visibleCount || !counterSuffix) {
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

    function setView(view) {
        const finalView = (view === 'list') ? 'list' : 'cards';

        if (gridWrap && listWrap && cardsBtn && listBtn) {
            if (finalView === 'list') {
                gridWrap.classList.add('hidden');
                listWrap.classList.add('active');
                cardsBtn.classList.remove('active');
                listBtn.classList.add('active');
            } else {
                gridWrap.classList.remove('hidden');
                listWrap.classList.remove('active');
                cardsBtn.classList.add('active');
                listBtn.classList.remove('active');
            }
        }

        try {
            localStorage.setItem('turnar_users_view', finalView);
        } catch (e) {}
    }

    function applyFilters() {
        const query = normalizeText(searchInput.value);
        const tokens = query === '' ? [] : query.split(' ').filter(Boolean);
        const selectedStatus = statusSelect.value;

        let matched = 0;
        const countedIds = new Set();

        allItems.forEach(function (item) {
            const searchText = normalizeText(item.getAttribute('data-search') || '');
            const cardStatus = item.getAttribute('data-status') || 'inactive';
            const linkedPerson = item.getAttribute('data-linked-person') === '1';
            const webAccess = item.getAttribute('data-web-access') === '1';
            const appAccess = item.getAttribute('data-app-access') === '1';
            const webappAccess = item.getAttribute('data-webapp-access') === '1';
            const mustChange = item.getAttribute('data-must-change') === '1';
            const admin = item.getAttribute('data-admin') === '1';

            const matchesStatus =
                selectedStatus === 'all' ||
                (selectedStatus === 'active' && cardStatus === 'active') ||
                (selectedStatus === 'inactive' && cardStatus === 'inactive') ||
                (selectedStatus === 'with-personal' && linkedPerson) ||
                (selectedStatus === 'without-personal' && !linkedPerson) ||
                (selectedStatus === 'web-access' && webAccess) ||
                (selectedStatus === 'app-access' && appAccess) ||
                (selectedStatus === 'webapp-access' && webappAccess) ||
                (selectedStatus === 'must-change' && mustChange) ||
                (selectedStatus === 'admin' && admin);

            const matchesSearch =
                tokens.length === 0 ||
                tokens.every(function (token) {
                    return searchText.includes(token);
                });

            const visible = matchesStatus && matchesSearch;

            item.classList.toggle('hidden-by-filter', !visible);

            if (visible) {
                const editHref = item.querySelector('a[href*="modules/users/edit.php?id="]');
                let uniqueId = '';

                if (editHref) {
                    uniqueId = editHref.getAttribute('href') || '';
                } else {
                    uniqueId = item.getAttribute('data-search') || Math.random().toString();
                }

                if (!countedIds.has(uniqueId)) {
                    countedIds.add(uniqueId);
                    matched++;
                }
            }
        });

        visibleCount.textContent = matched.toString();
        counterSuffix.textContent = matched === 1 ? ' visibile' : ' visibili';

        if (emptyState) {
            emptyState.classList.toggle('hidden-by-filter', matched !== 0);
        }
    }

    searchInput.addEventListener('input', applyFilters);
    statusSelect.addEventListener('change', applyFilters);

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            searchInput.value = '';
            statusSelect.value = 'all';
            applyFilters();
            searchInput.focus();
        });
    }

    if (cardsBtn) {
        cardsBtn.addEventListener('click', function () {
            setView('cards');
        });
    }

    if (listBtn) {
        listBtn.addEventListener('click', function () {
            setView('list');
        });
    }

    let savedView = 'cards';
    try {
        savedView = localStorage.getItem('turnar_users_view') || 'cards';
    } catch (e) {}

    setView(savedView);
    applyFilters();
})();
</script>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>