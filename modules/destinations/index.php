<?php
// modules/destinations/index.php

require_once __DIR__ . '/../../core/helpers.php';

require_login();
require_permission('destinations.view');

$pageTitle    = 'Destinazioni';
$pageSubtitle = 'Gestione destinazioni e cantieri';
$activeModule = 'destinations';

$canCreateDestination = can('destinations.create');
$canEditDestination   = can('destinations.edit');
$canDeleteDestination = can('destinations.delete');

$db = db_connect();
$currentUserId = (int)(auth_id() ?? 0);

$favoriteError = '';
$favoritesTable = 'user_favorite_destinations';
$favoriteColumn = null;

// --------------------------------------------------
// HELPER
// --------------------------------------------------
function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// --------------------------------------------------
// RILEVO COLONNA ID DESTINAZIONE NEI PREFERITI
// --------------------------------------------------
$resCols = $db->query("SHOW COLUMNS FROM `{$favoritesTable}`");
if ($resCols) {
    $columns = [];
    while ($col = $resCols->fetch_assoc()) {
        $columns[] = (string)($col['Field'] ?? '');
    }
    $resCols->free();

    if (in_array('cantiere_id', $columns, true)) {
        $favoriteColumn = 'cantiere_id';
    } elseif (in_array('destination_id', $columns, true)) {
        $favoriteColumn = 'destination_id';
    }
}

// --------------------------------------------------
// TOGGLE PREFERITO
// --------------------------------------------------
if ($currentUserId > 0 && $favoriteColumn !== null && isset($_GET['toggle_favorite'])) {
    $destinationId = (int)$_GET['toggle_favorite'];

    if ($destinationId > 0) {
        $stmtCheck = $db->prepare("
            SELECT id
            FROM `{$favoritesTable}`
            WHERE user_id = ? AND `{$favoriteColumn}` = ?
            LIMIT 1
        ");

        if ($stmtCheck) {
            $stmtCheck->bind_param('ii', $currentUserId, $destinationId);
            $stmtCheck->execute();
            $resCheck = $stmtCheck->get_result();
            $existing = $resCheck ? $resCheck->fetch_assoc() : null;
            $stmtCheck->close();

            if ($existing) {
                $stmtDelete = $db->prepare("
                    DELETE FROM `{$favoritesTable}`
                    WHERE user_id = ? AND `{$favoriteColumn}` = ?
                ");

                if ($stmtDelete) {
                    $stmtDelete->bind_param('ii', $currentUserId, $destinationId);
                    $okDelete = $stmtDelete->execute();
                    $deleteError = $stmtDelete->error;
                    $stmtDelete->close();

                    if ($okDelete) {
                        redirect('modules/destinations/index.php?favorite_saved=1&favorite_state=removed');
                    }

                    $favoriteError = $deleteError !== '' ? $deleteError : 'Errore durante la rimozione del preferito.';
                } else {
                    $favoriteError = $db->error !== '' ? $db->error : 'Errore preparazione rimozione preferito.';
                }
            } else {
                $stmtInsert = $db->prepare("
                    INSERT INTO `{$favoritesTable}` (user_id, `{$favoriteColumn}`)
                    VALUES (?, ?)
                ");

                if ($stmtInsert) {
                    $stmtInsert->bind_param('ii', $currentUserId, $destinationId);
                    $okInsert = $stmtInsert->execute();
                    $insertError = $stmtInsert->error;
                    $stmtInsert->close();

                    if ($okInsert) {
                        redirect('modules/destinations/index.php?favorite_saved=1&favorite_state=added');
                    }

                    $favoriteError = $insertError !== '' ? $insertError : 'Errore durante il salvataggio del preferito.';
                } else {
                    $favoriteError = $db->error !== '' ? $db->error : 'Errore preparazione salvataggio preferito.';
                }
            }
        } else {
            $favoriteError = $db->error !== '' ? $db->error : 'Errore controllo preferito.';
        }
    }
} elseif ($currentUserId > 0 && isset($_GET['toggle_favorite']) && $favoriteColumn === null) {
    $favoriteError = 'La tabella dei preferiti non contiene né cantiere_id né destination_id.';
}

// --------------------------------------------------
// CARICO PREFERITI UTENTE
// --------------------------------------------------
$userFavorites = [];

if ($currentUserId > 0 && $favoriteColumn !== null) {
    $stmtFav = $db->prepare("
        SELECT `{$favoriteColumn}` AS destination_ref
        FROM `{$favoritesTable}`
        WHERE user_id = ?
    ");

    if ($stmtFav) {
        $stmtFav->bind_param('i', $currentUserId);
        $stmtFav->execute();
        $resFav = $stmtFav->get_result();

        if ($resFav) {
            while ($favRow = $resFav->fetch_assoc()) {
                $userFavorites[] = (int)$favRow['destination_ref'];
            }
        }

        $stmtFav->close();
    } elseif ($favoriteError === '') {
        $favoriteError = $db->error !== '' ? $db->error : 'Errore lettura preferiti utente.';
    }
}

// --------------------------------------------------
// CARICO TUTTE LE DESTINAZIONI
// --------------------------------------------------
$sql = "SELECT * FROM cantieri ORDER BY commessa ASC";
$res = $db->query($sql);

$destinazioni = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['is_favorite']       = in_array((int)($row['id'] ?? 0), $userFavorites, true);
        $row['is_special']        = !empty($row['is_special']) ? 1 : 0;
        $row['counts_as_work']    = isset($row['counts_as_work']) ? (int)$row['counts_as_work'] : 1;
        $row['counts_as_absence'] = isset($row['counts_as_absence']) ? (int)$row['counts_as_absence'] : 0;
        $destinazioni[] = $row;
    }
    $res->free();
}

// Preferiti in alto, poi speciali, poi nome
usort($destinazioni, static function (array $a, array $b): int {
    $favA = !empty($a['is_favorite']) ? 1 : 0;
    $favB = !empty($b['is_favorite']) ? 1 : 0;

    if ($favA !== $favB) {
        return $favB <=> $favA;
    }

    $specialA = !empty($a['is_special']) ? 1 : 0;
    $specialB = !empty($b['is_special']) ? 1 : 0;

    if ($specialA !== $specialB) {
        return $specialB <=> $specialA;
    }

    $nameA = mb_strtolower(trim((string)($a['commessa'] ?? '')), 'UTF-8');
    $nameB = mb_strtolower(trim((string)($b['commessa'] ?? '')), 'UTF-8');

    return $nameA <=> $nameB;
});

$totaleDestinazioni = count($destinazioni);

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.alert-inline{
    margin-bottom:16px;
    padding:14px 16px;
    border-radius:18px;
    font-weight:700;
    border:1px solid var(--line);
}

.alert-inline.success{
    border-color:rgba(52,211,153,.28);
    background:rgba(52,211,153,.12);
    color:#166534;
}

.alert-inline.warning{
    border-color:rgba(251,191,36,.30);
    background:rgba(251,191,36,.12);
    color:#92400e;
}

.alert-inline.error{
    border-color:rgba(248,113,113,.30);
    background:rgba(248,113,113,.12);
    color:#991b1b;
}

.destinations-grid-wrap{
    display:block;
}

.destinations-grid-wrap.hidden{
    display:none;
}

.destinations-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(320px,1fr));
    gap:16px;
}

.destination-card{
    display:flex;
    flex-direction:column;
    gap:14px;
    position:relative;
    overflow:hidden;
}

.destination-card.special{
    border-color:color-mix(in srgb, var(--primary-2) 38%, var(--line));
    background:
        linear-gradient(180deg, color-mix(in srgb, var(--primary-2) 8%, transparent), rgba(255,255,255,0)),
        var(--content-card-bg);
}

.destination-card.special::before{
    content:'';
    position:absolute;
    inset:0 auto 0 0;
    width:5px;
    background:linear-gradient(180deg, color-mix(in srgb, var(--primary-2) 92%, transparent), color-mix(in srgb, var(--primary) 82%, transparent));
}

.destination-card.favorite{
    border-color:rgba(251,191,36,.34);
    box-shadow:
        0 18px 40px rgba(0,0,0,.10),
        0 0 0 1px rgba(251,191,36,.10) inset;
}

.destination-top{
    display:flex;
    align-items:flex-start;
    gap:12px;
}

.destination-main{
    min-width:0;
    flex:1;
}

.destination-top-right{
    display:flex;
    align-items:flex-start;
    justify-content:flex-end;
    flex:0 0 auto;
}

.favorite-btn,
.destination-row-favorite{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 86%, transparent);
    text-decoration:none;
    transition:.18s ease;
    line-height:1;
}

.favorite-btn{
    width:42px;
    height:42px;
    border-radius:14px;
    font-size:20px;
}

.destination-row-favorite{
    width:38px;
    height:38px;
    border-radius:12px;
    font-size:18px;
}

.favorite-btn:hover,
.destination-row-favorite:hover{
    transform:translateY(-1px);
    border-color:rgba(251,191,36,.35);
    background:rgba(251,191,36,.10);
}

.favorite-btn.is-favorite,
.destination-row-favorite.is-favorite{
    color:#d97706;
    background:rgba(251,191,36,.12);
    border-color:rgba(251,191,36,.30);
}

.favorite-btn.not-favorite,
.destination-row-favorite.not-favorite{
    color:var(--muted);
}

.destination-name{
    font-size:16px;
    font-weight:800;
    line-height:1.2;
    word-break:break-word;
    color:var(--text);
}

.destination-meta,
.destination-row-badges{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
}

.destination-meta{
    margin-top:6px;
}

.destination-row-badges{
    gap:6px;
}

.tag-pill{
    display:inline-flex;
    align-items:center;
    padding:6px 10px;
    border-radius:var(--badge-radius);
    font-size:11px;
    font-weight:700;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 88%, transparent);
    color:var(--text);
    line-height:1;
}

.tag-pill.special{
    background:color-mix(in srgb, var(--primary-2) 16%, transparent);
    color:color-mix(in srgb, var(--primary-2) 72%, var(--text));
    border-color:color-mix(in srgb, var(--primary-2) 28%, transparent);
}

.tag-pill.favorite{
    background:rgba(251,191,36,.15);
    color:#92400e;
    border-color:rgba(251,191,36,.28);
}

.tag-pill.operational{
    background:color-mix(in srgb, var(--primary) 16%, transparent);
    color:color-mix(in srgb, var(--primary) 72%, var(--text));
    border-color:color-mix(in srgb, var(--primary) 26%, transparent);
}

.tag-pill.work{
    background:rgba(34,197,94,.15);
    color:#166534;
    border-color:rgba(34,197,94,.28);
}

.tag-pill.absence{
    background:rgba(239,68,68,.14);
    color:#b91c1c;
    border-color:rgba(239,68,68,.26);
}

.destination-info{
    display:grid;
    gap:8px;
    font-size:13px;
    color:var(--text);
}

.destination-info-row{
    display:flex;
    gap:8px;
    align-items:flex-start;
    min-width:0;
}

.destination-info-label{
    color:var(--muted);
    min-width:100px;
    flex:0 0 auto;
}

.destination-info-value{
    word-break:break-word;
    color:var(--text);
}

.destination-footer{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    margin-top:auto;
}

.destination-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.delete-blocked{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:34px;
    padding:7px 12px;
    border-radius:12px;
    border:1px solid color-mix(in srgb, var(--primary-2) 24%, transparent);
    background:color-mix(in srgb, var(--primary-2) 10%, transparent);
    color:color-mix(in srgb, var(--primary-2) 78%, var(--text));
    font-size:12px;
    font-weight:800;
}

.destinations-list-wrap{
    display:none;
}

.destinations-list-wrap.active{
    display:block;
}

.destinations-list{
    display:flex;
    flex-direction:column;
    gap:10px;
}

.destination-row{
    display:grid;
    grid-template-columns:minmax(280px, 2.2fr) minmax(120px, .9fr) minmax(120px, .9fr) minmax(110px, .8fr) minmax(180px, 1.3fr) auto;
    gap:12px;
    align-items:center;
    position:relative;
    overflow:hidden;
}

.destination-row.special{
    border-color:color-mix(in srgb, var(--primary-2) 38%, var(--line));
    background:
        linear-gradient(180deg, color-mix(in srgb, var(--primary-2) 8%, transparent), rgba(255,255,255,0)),
        var(--content-card-bg);
}

.destination-row.special::before{
    content:'';
    position:absolute;
    inset:0 auto 0 0;
    width:4px;
    background:linear-gradient(180deg, color-mix(in srgb, var(--primary-2) 92%, transparent), color-mix(in srgb, var(--primary) 82%, transparent));
}

.destination-row.favorite{
    border-color:rgba(251,191,36,.34);
    box-shadow:
        0 18px 40px rgba(0,0,0,.10),
        0 0 0 1px rgba(251,191,36,.10) inset;
}

.destination-row-main{
    display:flex;
    align-items:center;
    gap:12px;
    min-width:0;
}

.destination-row-title{
    display:flex;
    flex-direction:column;
    gap:5px;
    min-width:0;
}

.destination-row-name{
    font-size:15px;
    font-weight:900;
    color:var(--text);
    line-height:1.2;
    word-break:break-word;
}

.destination-row-col{
    min-width:0;
    display:flex;
    flex-direction:column;
    gap:4px;
}

.destination-row-label{
    color:var(--muted);
    font-size:11px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.04em;
}

.destination-row-value{
    color:var(--text);
    font-size:13px;
    font-weight:700;
    word-break:break-word;
}

.destination-row-actions{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:8px;
    flex-wrap:wrap;
}

.hidden-by-filter{
    display:none !important;
}

@media (max-width: 1220px){
    .destination-row{
        grid-template-columns:1fr;
        align-items:flex-start;
    }

    .destination-row-actions{
        justify-content:flex-start;
    }
}

@media (max-width: 720px){
    .destinations-grid{
        grid-template-columns:1fr;
    }

    .destination-info-label{
        min-width:88px;
    }
}
</style>

<?php if ((int)get('created', 0) === 1): ?>
    <div class="alert-inline success">Destinazione creata correttamente.</div>
<?php endif; ?>

<?php if ((int)get('updated', 0) === 1): ?>
    <div class="alert-inline success">Destinazione aggiornata correttamente.</div>
<?php endif; ?>

<?php if ((int)get('deleted', 0) === 1): ?>
    <div class="alert-inline success">Destinazione eliminata correttamente.</div>
<?php endif; ?>

<?php if ((int)get('blocked_delete', 0) === 1): ?>
    <div class="alert-inline warning">Questa destinazione speciale non può essere eliminata.</div>
<?php endif; ?>

<?php if ((int)get('blocked_in_use', 0) === 1): ?>
    <div class="alert-inline warning">Questa destinazione non può essere eliminata perché è già presente nei turni.</div>
<?php endif; ?>

<?php if ((int)get('delete_error', 0) === 1): ?>
    <div class="alert-inline error">Errore durante l’eliminazione della destinazione.</div>
<?php endif; ?>

<?php if ((int)get('favorite_saved', 0) === 1 && get('favorite_state', '') === 'added'): ?>
    <div class="alert-inline success">Destinazione aggiunta ai preferiti.</div>
<?php endif; ?>

<?php if ((int)get('favorite_saved', 0) === 1 && get('favorite_state', '') === 'removed'): ?>
    <div class="alert-inline warning">Destinazione rimossa dai preferiti.</div>
<?php endif; ?>

<?php if ($favoriteError !== ''): ?>
    <div class="alert-inline error">
        Errore preferiti: <?php echo h($favoriteError); ?>
    </div>
<?php endif; ?>

<div class="content-card">

    <div class="toolbar">
        <div class="toolbar-left">
            <input
                type="text"
                id="destinationsSearchInput"
                placeholder="Cerca commessa, cliente, comune, tipologia, codice..."
                class="toolbar-search"
                autocomplete="off"
            >

            <select id="destinationsStatusSelect" class="field-sm">
                <option value="all">Tutti</option>
                <option value="active">Attivi</option>
                <option value="inactive">Disattivi</option>
                <option value="special">Solo speciali</option>
                <option value="operational">Solo operative</option>
                <option value="favorite">Solo preferiti</option>
            </select>

            <div class="view-toggle">
                <button type="button" class="toggle-item active" id="viewCardsBtn">Vista card</button>
                <button type="button" class="toggle-item" id="viewListBtn">Vista elenco</button>
            </div>

            <button type="button" id="destinationsResetBtn" class="btn btn-ghost">Reset</button>
        </div>

        <div class="toolbar-right">
            <?php if ($canCreateDestination): ?>
                <a href="<?php echo h(app_url('modules/destinations/edit.php')); ?>" class="btn btn-primary">+ Nuova destinazione</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="text-muted mb-3" style="font-size:13px;">
        <span id="destinationsVisibleCount"><?php echo $totaleDestinazioni; ?></span>
        destinazion<span id="destinationsCounterSuffix"><?php echo $totaleDestinazioni === 1 ? 'e visibile' : 'i visibili'; ?></span>
        su <?php echo $totaleDestinazioni; ?>
    </div>

    <?php if (!empty($destinazioni)): ?>

        <div class="destinations-grid-wrap" id="destinationsGridWrap">
            <div class="destinations-grid" id="destinationsGrid">
                <?php foreach ($destinazioni as $dest): ?>
                    <?php
                        $id = (int)($dest['id'] ?? 0);
                        $commessa = trim((string)($dest['commessa'] ?? ''));
                        $cliente = trim((string)($dest['cliente'] ?? ''));
                        $codiceCommessa = trim((string)($dest['codice_commessa'] ?? ''));
                        $indirizzo = trim((string)($dest['indirizzo'] ?? ''));
                        $comune = trim((string)($dest['comune'] ?? ''));
                        $tipologia = trim((string)($dest['tipologia'] ?? ''));
                        $stato = trim((string)($dest['stato'] ?? ''));
                        $cig = trim((string)($dest['cig'] ?? ''));
                        $cup = trim((string)($dest['cup'] ?? ''));
                        $dataInizio = trim((string)($dest['data_inizio'] ?? ''));
                        $dataFinePrevista = trim((string)($dest['data_fine_prevista'] ?? ''));
                        $attivo = !empty($dest['attivo']);
                        $visibileCalendario = !empty($dest['visibile_calendario']);
                        $pausaPranzo = isset($dest['pausa_pranzo']) ? (string)$dest['pausa_pranzo'] : '';
                        $note = trim((string)($dest['note'] ?? ''));
                        $noteOperativo = trim((string)($dest['note_operativo'] ?? ''));
                        $foto = trim((string)($dest['foto'] ?? ''));
                        $fotoUrl = $foto !== '' ? app_url($foto) : '';
                        $isFavorite = !empty($dest['is_favorite']);
                        $isSpecial = !empty($dest['is_special']);
                        $countsAsWork = isset($dest['counts_as_work']) ? (int)$dest['counts_as_work'] : 1;
                        $countsAsAbsence = isset($dest['counts_as_absence']) ? (int)$dest['counts_as_absence'] : 0;

                        $initial = 'D';
                        if ($commessa !== '') {
                            $initial = mb_strtoupper(mb_substr($commessa, 0, 1, 'UTF-8'), 'UTF-8');
                        }

                        $logicText = [];
                        if ($countsAsWork === 1) {
                            $logicText[] = 'conteggia lavoro ore lavorate';
                        }
                        if ($countsAsAbsence === 1) {
                            $logicText[] = 'conteggia assenza assenze';
                        }

                        $searchBlob = implode(' ', [
                            $commessa,
                            $cliente,
                            $codiceCommessa,
                            $indirizzo,
                            $comune,
                            $tipologia,
                            $stato,
                            $cig,
                            $cup,
                            $dataInizio,
                            $dataFinePrevista,
                            $note,
                            $noteOperativo,
                            $pausaPranzo,
                            $visibileCalendario ? 'visibile calendario' : 'nascosto calendario',
                            $attivo ? 'attivo' : 'disattivo',
                            $isFavorite ? 'preferito favorito' : '',
                            $isSpecial ? 'speciale' : 'operativa',
                            implode(' ', $logicText),
                        ]);
                    ?>

                    <div
                        class="entity-card destination-card <?php echo $isSpecial ? 'special' : ''; ?> <?php echo $isFavorite ? 'favorite' : ''; ?>"
                        data-search="<?php echo h(mb_strtolower($searchBlob, 'UTF-8')); ?>"
                        data-status="<?php echo $attivo ? 'active' : 'inactive'; ?>"
                        data-special="<?php echo $isSpecial ? '1' : '0'; ?>"
                        data-favorite="<?php echo $isFavorite ? '1' : '0'; ?>"
                    >
                        <div class="destination-top">
                            <div class="entity-avatar lg">
                                <?php if ($fotoUrl !== ''): ?>
                                    <img src="<?php echo h($fotoUrl); ?>" alt="Foto destinazione">
                                <?php else: ?>
                                    <?php echo h($initial); ?>
                                <?php endif; ?>
                            </div>

                            <div class="destination-main">
                                <div class="destination-name">
                                    <?php echo h($commessa !== '' ? $commessa : 'Destinazione senza nome'); ?>
                                </div>

                                <div class="destination-meta">
                                    <?php if ($tipologia !== ''): ?>
                                        <span class="tag-pill"><?php echo h($tipologia); ?></span>
                                    <?php endif; ?>

                                    <?php if ($codiceCommessa !== ''): ?>
                                        <span class="tag-pill"><?php echo h($codiceCommessa); ?></span>
                                    <?php endif; ?>

                                    <?php if ($isSpecial): ?>
                                        <span class="tag-pill special">Destinazione speciale</span>
                                    <?php else: ?>
                                        <span class="tag-pill operational">Operativa</span>
                                    <?php endif; ?>

                                    <?php if ($countsAsWork === 1): ?>
                                        <span class="tag-pill work">Conta come lavoro</span>
                                    <?php endif; ?>

                                    <?php if ($countsAsAbsence === 1): ?>
                                        <span class="tag-pill absence">Conta come assenza</span>
                                    <?php endif; ?>

                                    <?php if ($isFavorite): ?>
                                        <span class="tag-pill favorite">Preferita</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="destination-top-right">
                                <a
                                    href="<?php echo h(app_url('modules/destinations/index.php?toggle_favorite=' . $id)); ?>"
                                    class="favorite-btn <?php echo $isFavorite ? 'is-favorite' : 'not-favorite'; ?>"
                                    title="<?php echo $isFavorite ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti'; ?>"
                                >
                                    <?php echo $isFavorite ? '★' : '☆'; ?>
                                </a>
                            </div>
                        </div>

                        <div class="destination-info">
                            <div class="destination-info-row">
                                <div class="destination-info-label">Cliente</div>
                                <div class="destination-info-value"><?php echo h($cliente !== '' ? $cliente : '-'); ?></div>
                            </div>

                            <div class="destination-info-row">
                                <div class="destination-info-label">Comune</div>
                                <div class="destination-info-value"><?php echo h($comune !== '' ? $comune : '-'); ?></div>
                            </div>

                            <div class="destination-info-row">
                                <div class="destination-info-label">Indirizzo</div>
                                <div class="destination-info-value"><?php echo h($indirizzo !== '' ? $indirizzo : '-'); ?></div>
                            </div>

                            <div class="destination-info-row">
                                <div class="destination-info-label">Stato</div>
                                <div class="destination-info-value"><?php echo h($stato !== '' ? $stato : '-'); ?></div>
                            </div>

                            <div class="destination-info-row">
                                <div class="destination-info-label">Inizio</div>
                                <div class="destination-info-value"><?php echo h($dataInizio !== '' ? format_date_it($dataInizio) : '-'); ?></div>
                            </div>

                            <div class="destination-info-row">
                                <div class="destination-info-label">Fine prevista</div>
                                <div class="destination-info-value"><?php echo h($dataFinePrevista !== '' ? format_date_it($dataFinePrevista) : '-'); ?></div>
                            </div>

                            <div class="destination-info-row">
                                <div class="destination-info-label">Pausa pranzo</div>
                                <div class="destination-info-value">
                                    <?php echo h($pausaPranzo !== '' ? $pausaPranzo . ' h' : '-'); ?>
                                </div>
                            </div>

                            <div class="destination-info-row">
                                <div class="destination-info-label">Calendario</div>
                                <div class="destination-info-value">
                                    <?php echo $visibileCalendario ? 'Visibile' : 'Nascosto'; ?>
                                </div>
                            </div>

                            <?php if ($isSpecial): ?>
                                <div class="destination-info-row">
                                    <div class="destination-info-label">Logica report</div>
                                    <div class="destination-info-value">
                                        <?php
                                        $logicParts = [];
                                        if ($countsAsWork === 1) {
                                            $logicParts[] = 'Conta come lavoro';
                                        }
                                        if ($countsAsAbsence === 1) {
                                            $logicParts[] = 'Conta come assenza';
                                        }
                                        echo h(!empty($logicParts) ? implode(' • ', $logicParts) : 'Nessuna logica specifica');
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="destination-footer">
                            <span class="status-pill <?php echo $attivo ? 'is-active' : 'is-inactive'; ?>">
                                <?php echo $attivo ? 'Attivo' : 'Disattivo'; ?>
                            </span>

                            <div class="destination-actions">
                                <?php if ($canEditDestination): ?>
                                    <a href="<?php echo h(app_url('modules/destinations/edit.php?id=' . $id)); ?>" class="btn btn-secondary btn-sm">
                                        Modifica
                                    </a>
                                <?php endif; ?>

                                <?php if ($canDeleteDestination): ?>
                                    <?php if ($isSpecial): ?>
                                        <span class="delete-blocked">Speciale protetta</span>
                                    <?php else: ?>
                                        <a
                                            href="<?php echo h(app_url('modules/destinations/delete.php?id=' . $id)); ?>"
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('Sei sicuro di voler eliminare questa destinazione?');"
                                        >
                                            Elimina
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="destinations-list-wrap" id="destinationsListWrap">
            <div class="destinations-list" id="destinationsList">
                <?php foreach ($destinazioni as $dest): ?>
                    <?php
                        $id = (int)($dest['id'] ?? 0);
                        $commessa = trim((string)($dest['commessa'] ?? ''));
                        $cliente = trim((string)($dest['cliente'] ?? ''));
                        $codiceCommessa = trim((string)($dest['codice_commessa'] ?? ''));
                        $indirizzo = trim((string)($dest['indirizzo'] ?? ''));
                        $comune = trim((string)($dest['comune'] ?? ''));
                        $tipologia = trim((string)($dest['tipologia'] ?? ''));
                        $stato = trim((string)($dest['stato'] ?? ''));
                        $dataInizio = trim((string)($dest['data_inizio'] ?? ''));
                        $attivo = !empty($dest['attivo']);
                        $foto = trim((string)($dest['foto'] ?? ''));
                        $fotoUrl = $foto !== '' ? app_url($foto) : '';
                        $isFavorite = !empty($dest['is_favorite']);
                        $isSpecial = !empty($dest['is_special']);
                        $countsAsWork = isset($dest['counts_as_work']) ? (int)$dest['counts_as_work'] : 1;
                        $countsAsAbsence = isset($dest['counts_as_absence']) ? (int)$dest['counts_as_absence'] : 0;

                        $initial = 'D';
                        if ($commessa !== '') {
                            $initial = mb_strtoupper(mb_substr($commessa, 0, 1, 'UTF-8'), 'UTF-8');
                        }

                        $logicText = [];
                        if ($countsAsWork === 1) {
                            $logicText[] = 'conteggia lavoro ore lavorate';
                        }
                        if ($countsAsAbsence === 1) {
                            $logicText[] = 'conteggia assenza assenze';
                        }

                        $searchBlob = implode(' ', [
                            $commessa,
                            $cliente,
                            $codiceCommessa,
                            $indirizzo,
                            $comune,
                            $tipologia,
                            $stato,
                            $dataInizio,
                            $attivo ? 'attivo' : 'disattivo',
                            $isFavorite ? 'preferito favorito' : '',
                            $isSpecial ? 'speciale' : 'operativa',
                            implode(' ', $logicText),
                        ]);
                    ?>

                    <div
                        class="entity-row destination-row <?php echo $isSpecial ? 'special' : ''; ?> <?php echo $isFavorite ? 'favorite' : ''; ?>"
                        data-search="<?php echo h(mb_strtolower($searchBlob, 'UTF-8')); ?>"
                        data-status="<?php echo $attivo ? 'active' : 'inactive'; ?>"
                        data-special="<?php echo $isSpecial ? '1' : '0'; ?>"
                        data-favorite="<?php echo $isFavorite ? '1' : '0'; ?>"
                    >
                        <div class="destination-row-main">
                            <div class="entity-avatar md">
                                <?php if ($fotoUrl !== ''): ?>
                                    <img src="<?php echo h($fotoUrl); ?>" alt="Foto destinazione">
                                <?php else: ?>
                                    <?php echo h($initial); ?>
                                <?php endif; ?>
                            </div>

                            <div class="destination-row-title">
                                <div class="destination-row-name">
                                    <?php echo h($commessa !== '' ? $commessa : 'Destinazione senza nome'); ?>
                                </div>
                                <div class="destination-row-badges">
                                    <?php if ($isSpecial): ?>
                                        <span class="tag-pill special">Speciale</span>
                                    <?php else: ?>
                                        <span class="tag-pill operational">Operativa</span>
                                    <?php endif; ?>

                                    <?php if ($countsAsWork === 1): ?>
                                        <span class="tag-pill work">Conta come lavoro</span>
                                    <?php endif; ?>

                                    <?php if ($countsAsAbsence === 1): ?>
                                        <span class="tag-pill absence">Conta come assenza</span>
                                    <?php endif; ?>

                                    <?php if ($isFavorite): ?>
                                        <span class="tag-pill favorite">Preferita</span>
                                    <?php endif; ?>

                                    <?php if ($codiceCommessa !== ''): ?>
                                        <span class="tag-pill"><?php echo h($codiceCommessa); ?></span>
                                    <?php endif; ?>

                                    <?php if ($tipologia !== ''): ?>
                                        <span class="tag-pill"><?php echo h($tipologia); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="destination-row-col">
                            <div class="destination-row-label">Cliente</div>
                            <div class="destination-row-value"><?php echo h($cliente !== '' ? $cliente : '-'); ?></div>
                        </div>

                        <div class="destination-row-col">
                            <div class="destination-row-label">Comune</div>
                            <div class="destination-row-value"><?php echo h($comune !== '' ? $comune : '-'); ?></div>
                        </div>

                        <div class="destination-row-col">
                            <div class="destination-row-label">Stato</div>
                            <div class="destination-row-value">
                                <span class="status-pill <?php echo $attivo ? 'is-active' : 'is-inactive'; ?>">
                                    <?php echo $attivo ? 'Attivo' : 'Disattivo'; ?>
                                </span>
                            </div>
                        </div>

                        <div class="destination-row-col">
                            <div class="destination-row-label">Indirizzo / Inizio / Logica</div>
                            <div class="destination-row-value">
                                <?php
                                $partsCompact = [];
                                if ($indirizzo !== '') {
                                    $partsCompact[] = $indirizzo;
                                }
                                if ($dataInizio !== '') {
                                    $partsCompact[] = 'Dal ' . format_date_it($dataInizio);
                                }
                                if ($isSpecial) {
                                    if ($countsAsWork === 1) {
                                        $partsCompact[] = 'Conta come lavoro';
                                    }
                                    if ($countsAsAbsence === 1) {
                                        $partsCompact[] = 'Conta come assenza';
                                    }
                                }
                                echo h(!empty($partsCompact) ? implode(' • ', $partsCompact) : '-');
                                ?>
                            </div>
                        </div>

                        <div class="destination-row-actions">
                            <a
                                href="<?php echo h(app_url('modules/destinations/index.php?toggle_favorite=' . $id)); ?>"
                                class="destination-row-favorite <?php echo $isFavorite ? 'is-favorite' : 'not-favorite'; ?>"
                                title="<?php echo $isFavorite ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti'; ?>"
                            >
                                <?php echo $isFavorite ? '★' : '☆'; ?>
                            </a>

                            <?php if ($canEditDestination): ?>
                                <a href="<?php echo h(app_url('modules/destinations/edit.php?id=' . $id)); ?>" class="btn btn-secondary btn-sm">
                                    Modifica
                                </a>
                            <?php endif; ?>

                            <?php if ($canDeleteDestination): ?>
                                <?php if ($isSpecial): ?>
                                    <span class="delete-blocked">Speciale protetta</span>
                                <?php else: ?>
                                    <a
                                        href="<?php echo h(app_url('modules/destinations/delete.php?id=' . $id)); ?>"
                                        class="btn btn-danger btn-sm"
                                        onclick="return confirm('Sei sicuro di voler eliminare questa destinazione?');"
                                    >
                                        Elimina
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="empty-state hidden-by-filter" id="destinationsEmptyState">
            <h3 class="empty-state-title">Nessuna destinazione trovata</h3>
            <div class="empty-state-text">Nessuna destinazione corrisponde ai filtri attuali. Prova a cambiare ricerca o selezione.</div>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <h3 class="empty-state-title">Nessuna destinazione presente</h3>
            <div class="empty-state-text">Non ci sono ancora destinazioni configurate nel sistema.</div>
        </div>
    <?php endif; ?>

</div>

<script>
(function () {
    const searchInput = document.getElementById('destinationsSearchInput');
    const statusSelect = document.getElementById('destinationsStatusSelect');
    const resetBtn = document.getElementById('destinationsResetBtn');
    const visibleCount = document.getElementById('destinationsVisibleCount');
    const counterSuffix = document.getElementById('destinationsCounterSuffix');
    const emptyState = document.getElementById('destinationsEmptyState');

    const gridWrap = document.getElementById('destinationsGridWrap');
    const listWrap = document.getElementById('destinationsListWrap');
    const cardsBtn = document.getElementById('viewCardsBtn');
    const listBtn = document.getElementById('viewListBtn');

    const allItems = Array.from(document.querySelectorAll('.destination-card, .destination-row'));

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

        if (gridWrap && listWrap) {
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
            localStorage.setItem('turnar_destinations_view', finalView);
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
            const isSpecial = item.getAttribute('data-special') === '1';
            const isFavorite = item.getAttribute('data-favorite') === '1';

            const matchesStatus =
                selectedStatus === 'all' ||
                (selectedStatus === 'active' && cardStatus === 'active') ||
                (selectedStatus === 'inactive' && cardStatus === 'inactive') ||
                (selectedStatus === 'special' && isSpecial) ||
                (selectedStatus === 'operational' && !isSpecial) ||
                (selectedStatus === 'favorite' && isFavorite);

            const matchesSearch =
                tokens.length === 0 ||
                tokens.every(function (token) {
                    return searchText.includes(token);
                });

            const visible = matchesStatus && matchesSearch;

            item.classList.toggle('hidden-by-filter', !visible);

            if (visible) {
                const favHref = item.querySelector('a[href*="toggle_favorite="]');
                let uniqueId = '';

                if (favHref) {
                    uniqueId = favHref.getAttribute('href') || '';
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
        counterSuffix.textContent = matched === 1 ? 'e visibile' : 'i visibili';

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
        savedView = localStorage.getItem('turnar_destinations_view') || 'cards';
    } catch (e) {}

    setView(savedView);
    applyFilters();
})();
</script>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>