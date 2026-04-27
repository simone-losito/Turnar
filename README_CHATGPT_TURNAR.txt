Sto lavorando a un software PHP/MySQL chiamato Turnar, con struttura modulare, UI dark moderna e logica procedurale/pratica. Voglio continuare il lavoro senza perdere il contesto. Ti riassumo struttura, file, logiche e punto esatto in cui siamo arrivati.

==================================================
CONTESTO GENERALE
==================================================

Turnar è un gestionale per:
- personale / dipendenti
- destinazioni / cantieri
- turni / assegnazioni
- calendario
- utenti / ruoli / permessi
- dashboard
- report

Stack attuale:
- PHP
- MySQL
- layout condiviso con templates/layout_top.php e layout_bottom.php
- config bootstrap centralizzato
- auth custom con ruoli, scope e permessi
- UI dark moderna con card, pill, badge, gradient
- approccio pratico: voglio file completi pronti da incollare, non patch spezzate

Timezone:
- Europe/Rome

Database:
- tabella personale: dipendenti
- tabella destinazioni: cantieri
- tabella turni: eventi_turni
- tabella utenti: users
- tabella permessi utente: user_permissions
- tabella permessi ruolo: role_permissions
- tabella permessi disponibili: permissions
- tabella impostazioni: settings
- tabella preferiti destinazioni utente: user_favorite_destinations

==================================================
STRUTTURA ATTUALE PRINCIPALE
==================================================

Config / core:
- config/app.php
- config/database.php
- config/bootstrap.php
- core/auth.php
- core/helpers.php
- core/settings.php

Template:
- templates/layout_top.php
- templates/layout_bottom.php

Moduli principali:
- modules/operators/
- modules/destinations/
- modules/turni/
- modules/users/
- modules/reports/
- dashboard in root index.php

==================================================
AUTENTICAZIONE / RUOLI / PERMESSI
==================================================

Sistema auth centralizzato in:
- core/auth.php

Ruoli ufficiali:
- user
- manager
- master

Scope ufficiali:
- self
- team
- global

Helper importanti:
- auth_user()
- auth_id()
- auth_role()
- auth_scope()
- can(...)
- require_login()
- require_permission(...)
- can_view_all_assignments()
- can_view_team_assignments()
- can_view_own_assignments()
- can_manage_assignments()

Login:
- utenti su tabella users
- collegamento opzionale con dipendenti tramite users.dipendente_id
- supporto upgrade password legacy da dipendenti.password
- refresh sessione con auth_refresh_session_user()

==================================================
PERSONALE / OPERATORI
==================================================

Modulo:
- modules/operators/index.php
- modules/operators/edit.php
- modules/operators/delete.php

Tabella:
- dipendenti

Campi già usati o gestiti:
- id
- nome
- cognome
- matricola
- foto
- telefono
- email
- codice_fiscale
- iban
- indirizzo_residenza
- data_assunzione
- tipo_contratto
- data_scadenza_contratto
- tipologia
- livello
- preposto
- capo_cantiere
- attivo

Logica importante:
- quando creo o modifico una persona, il sistema sincronizza automaticamente anche l’account utente collegato nella tabella users
- se non esiste account lo crea
- username iniziale generato da nome.cognome
- password iniziale = nome
- must_change_password = 1
- account app ON, web OFF di default per auto-creazione da personale
- dalla scheda persona si può poi aprire Gestione Utenti

Upload foto personale:
- cartella uploads/operators
- solo path nel DB, non blob

==================================================
UTENTI
==================================================

Modulo:
- modules/users/index.php
- modules/users/edit.php
- modules/users/delete.php
- modules/users/roles.php

Tabella:
- users

Campi importanti:
- id
- dipendente_id
- role
- scope
- username
- password_hash
- email
- is_active
- can_login_web
- can_login_app
- must_change_password
- is_administrative
- last_login_at

Logica:
- utente può essere standalone oppure collegato a dipendente
- permessi base da role_permissions
- override utente da user_permissions
- pagina roles.php gestisce permessi base per ruolo
- pagina edit utente gestisce override specifici per singolo utente

==================================================
DESTINAZIONI / CANTIERI
==================================================

Modulo:
- modules/destinations/index.php
- modules/destinations/edit.php
- modules/destinations/delete.php

Tabella:
- cantieri

Campi già usati:
- id
- commessa
- cliente
- codice_commessa
- indirizzo
- comune
- tipologia
- stato
- cig
- cup
- data_inizio
- data_fine_prevista
- data_fine_effettiva
- importo_previsto
- note_operativo
- note
- foto
- attivo
- visibile_calendario
- pausa_pranzo

UPLOAD FOTO:
- uploads/destinations

PREFERITI UTENTE:
- tabella user_favorite_destinations
- il sistema gestisce compatibilità se il campo si chiama destination_id oppure cantiere_id
- le destinazioni preferite vengono mostrate in alto nella lista e nel planning/dashboard

DESTINAZIONI SPECIALI:
abbiamo appena introdotto una logica nuova IMPORTANTISSIMA

Prima:
- il software riconosceva le destinazioni speciali in modo hardcoded dal nome commessa:
  - ferie
  - permessi
  - corsi di formazione
  - malattia

Adesso:
- è stato introdotto un flag vero in tabella cantieri:
  - is_special TINYINT(1) NOT NULL DEFAULT 0

SQL già eseguito:
ALTER TABLE cantieri
ADD COLUMN is_special TINYINT(1) NOT NULL DEFAULT 0 AFTER attivo;

UPDATE cantieri
SET is_special = 1
WHERE LOWER(TRIM(commessa)) IN ('ferie','permessi','corsi di formazione','malattia');

Obiettivo:
- da ora in poi il software deve usare is_special e NON più riconoscere gli speciali dal nome

Quindi:
- ferie, permessi, malattia, corsi di formazione devono essere trattati come “destinazioni speciali”
- il nome non deve più essere la logica principale
- in edit destinazione va aggiunta la possibilità di marcare una destinazione come speciale
- in index destinazioni le speciali devono avere evidenza grafica diversa
- delete di speciali eventualmente può essere bloccato o gestito con regola precisa

Nota:
- i nomi storici speciali restano utili solo come dati legacy o preset iniziale, non come logica futura

==================================================
TURNI / ASSEGNAZIONI
==================================================

Modulo:
- modules/turni/TurniRepository.php
- modules/turni/TurniService.php
- modules/turni/planning.php
- modules/turni/calendar.php
- modules/turni/new_assignment.php
- modules/turni/edit_assignment.php
- modules/turni/delete_assignment.php
- modules/turni/quick_assign.php
- modules/turni/move_assignment_date.php
- modules/turni/check_assignment_move_conflicts.php
- modules/turni/index.php

Tabella:
- eventi_turni

Campi usati:
- id
- data
- id_cantiere
- id_dipendente
- ora_inizio
- ora_fine
- is_capocantiere
- created_at
- updated_at

Logiche già implementate:
- planning giornaliero con operatori, destinazioni e riepilogo laterale
- assegnazione rapida
- selezione singola operatore
- selezione multipla con CTRL + click
- drag & drop operatori -> destinazione
- drag & drop turno assegnato -> altra destinazione
- controllo conflitti orari
- possibilità di forzare salvataggio
- possibilità di salvare solo righe valide in multi assegnazione
- calendario mese/settimana
- spostamento turno di data via drag & drop sul calendario
- check conflitti live sul calendario
- card cantiere compatte e apribili
- badge Responsabile invece di “capo”

TurniRepository:
- lettura operatori da dipendenti
- lettura destinazioni da cantieri
- lettura turni da eventi_turni
- dashboard stats base per una data
- grouping per data

TurniService:
- normalizeDate
- normalizeTime
- explodeTurno (gestione turni che superano mezzanotte)
- checkConflitti
- salvaTurno
- afterSave placeholder per notifiche/mail

==================================================
PAUSA PRANZO
==================================================

Su destinazioni esiste:
- cantieri.pausa_pranzo

Valori tipici:
- 0.00 = nessuna
- 0.50 = 30 minuti
- 1.00 = 60 minuti

Regola desiderata già fissata come logica di progetto:
- la pausa pranzo si scala solo se il turno lordo supera o raggiunge la soglia corretta di giornata piena
- NON va scalata per turni brevi
- questa regola va mantenuta coerente nei report

==================================================
DASHBOARD ATTUALE
==================================================

Esiste una dashboard in root:
- index.php nella root del progetto

Non esiste modules/dashboard/index.php come file attivo principale in questo momento.

La dashboard root attuale ha già:
- KPI base
- turni di oggi
- cantieri operativi oggi
- speciali in finestra separata
- preferiti utente in evidenza
- ultimi accessi utenti ridotti
- navigazione giorno precedente / oggi / giorno successivo
- giorno mostrato in modo evidente con nome del giorno
- vista dark moderna
- tendine chiuse all’apertura
- cantieri attivi senza personale a tendina
- operatori liberi oggi a tendina
- cantieri speciali in blocco separato
- speciali con colori diversi

Nota progettuale:
in futuro voglio anche light mode / dark mode, ma NON adesso.
La struttura attuale permette di farlo alla fine, centralizzando i colori nel layout/CSS variabili.

==================================================
REGOLE DI STILE DI LAVORO
==================================================

Come voglio lavorare:
- non so programmare bene, quindi voglio sempre file completi pronti da incollare
- niente patch spezzate
- niente “sostituisci queste 4 righe”
- guidami un passo alla volta
- prima spiegazione breve, poi file completo
- attenzione massima a compatibilità con file già esistenti
- mantenere UI dark moderna coerente con il resto del progetto
- badge, pill, card, gradient, look pulito e professionale

==================================================
PUNTO ESATTO IN CUI SIAMO
==================================================

Adesso voglio procedere in questo ordine:

A) FIX DASHBOARD
B) REPORT
C) DASHBOARD INTELLIGENTE

E voglio farli in quest’ordine preciso.

--------------------------------------------------
A) FIX DASHBOARD
--------------------------------------------------

Obiettivo immediato:
- aggiornare la dashboard root index.php per usare il flag cantieri.is_special
- eliminare ogni logica basata solo sul nome commessa per distinguere speciali/operativi
- tenere separati:
  - cantieri operativi
  - destinazioni speciali
- mantenere preferiti utente in evidenza
- mantenere accorpamento turni per cantiere
- mantenere UI compatta e pulita

--------------------------------------------------
B) REPORT
--------------------------------------------------

Dopo la dashboard voglio progettare bene i report.

Report desiderati:
- report dipendente
- report destinazione/cantiere
- report per periodo generico
- filtro per singolo operatore
- filtro per singola destinazione
- filtro multi-destinazione
- export PDF
- export CSV

Logica speciale da rispettare:
- destinazioni speciali devono comparire nei report
- ma ferie / permessi / malattia non devono essere trattati come ore lavorative normali
- corsi di formazione può avere logica separata
- il sistema deve essere pronto a distinguere ore lavorate, assenze, speciali

--------------------------------------------------
C) DASHBOARD INTELLIGENTE
--------------------------------------------------

Dopo report voglio evolvere la dashboard in versione intelligente con alert tipo:
- operatori in ferie oggi
- destinazioni senza personale
- troppi operatori liberi
- anomalie o conflitti
- riepiloghi più utili per l’operatività

==================================================
RICHIESTA OPERATIVA
==================================================

Adesso ripartiamo dallo STEP A:
voglio che mi aiuti a sistemare la dashboard root index.php usando is_special in modo corretto e pulito.

Quando rispondi:
- spiegami in breve cosa fai
- poi dammi il file COMPLETO pronto da incollare
- mantieni stile dark coerente con Turnar
- non farmi perdere pezzi già funzionanti
- se serve, chiedimi solo i file strettamente necessari
- se puoi evitare di chiedere e ricostruire direttamente dai dati sopra, ancora meglio