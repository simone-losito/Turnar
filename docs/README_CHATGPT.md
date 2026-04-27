# 🚀 TURNAR - DOCUMENTAZIONE TECNICA (PER CHATGPT)

## 📌 SCOPO

Turnar è un gestionale web sviluppato in PHP + MySQL per la gestione di:

* turni di lavoro
* operatori (dipendenti)
* destinazioni (cantieri)
* utenti e permessi
* report operativi

Questo documento serve per permettere a ChatGPT di comprendere rapidamente la struttura del software e lavorarci sopra senza dover rispiegare tutto ogni volta.

---

# 🧱 ARCHITETTURA GENERALE

## 🔹 Tecnologie

* PHP procedurale (no framework)
* MySQL (mysqli)
* Struttura modulare per cartelle
* Sessioni PHP native

---

## 📂 STRUTTURA PRINCIPALE

```
/config
/core
/modules
    /users
    /operators
    /destinations
    /turni
```

---

# ⚙️ CORE DEL SISTEMA

## 📁 /config

### bootstrap.php

* avvia sessione
* gestisce timeout inattività
* definisce helper base (redirect, json, ecc.)

### database.php

* connessione centralizzata MySQL
* funzione principale: `db_connect()`

---

## 📁 /core

### helpers.php

Funzioni generiche:

* date (`today_date`, `format_date_it`)
* request (`get`, `post`)
* stringhe
* utility utenti

Include anche:
👉 gestione destinazioni preferite utente

---

### auth.php (IMPORTANTISSIMO)

Gestisce autenticazione e permessi:

#### Funzioni chiave:

* `auth_check()`
* `auth_user()`
* `auth_id()`
* `can($permission)`
* `require_permission($permission)`
* `require_login()`

---

# 👥 SISTEMA UTENTI E PERMESSI

## 📊 Tabelle DB

### users

* utenti del gestionale
* collegati a dipendenti tramite `dipendente_id`

### permissions

* elenco di tutti i permessi (es: `operators.view`)

### role_permissions

* permessi per ruolo:

  * user
  * manager
  * master

### user_permissions

* override singolo utente

---

## 🔐 LOGICA PERMESSI

Ordine di priorità:

1. **user_permissions (override)**
2. **role_permissions (ruolo)**
3. fallback = false

---

## 👤 RUOLI

### USER

* accesso base
* vede solo turni e calendario

### MANAGER

* gestione operativa
* operatori, destinazioni, turni

### MASTER

* accesso totale
* utenti, permessi, impostazioni

---

# 👷 MODULO OPERATORI (dipendenti)

Cartella:

```
/modules/operators
```

Tabella:

```
dipendenti
```

Funzione:

* anagrafica personale
* collegamento opzionale a utente

Relazione:

```
users.dipendente_id → dipendenti.id
```

---

# 🏗️ MODULO DESTINAZIONI

Cartella:

```
/modules/destinations
```

Tabella:

```
cantieri
```

Contiene:

* nome (commessa)
* foto
* configurazioni

### DESTINAZIONI SPECIALI

Non eliminabili:

* ferie
* permessi
* malattia
* corsi di formazione

---

# 📅 MODULO TURNI

Cartella:

```
/modules/turni
```

Tabella:

```
eventi_turni
```

Campi principali:

* data
* ora_inizio
* ora_fine
* id_dipendente
* id_cantiere

---

## ⚠️ LOGICA TURNI

* gestione conflitti
* possibilità override manuale
* cancellazione tramite:
  `delete_assignment.php`

---

# ⭐ DESTINAZIONI PREFERITE

Tabella:

```
user_favorite_destinations
```

Funzioni:

* `get_user_favorite_destinations()`
* `toggle_user_favorite_destination()`

Uso:
👉 filtrare cantieri per utente

---

# ⚙️ SETTINGS DINAMICI

Tabella:

```
settings
```

Gestione:

* `setting()`
* `setting_set()`

Esempi:

* orario default turni
* attivazione notifiche

---

# 📊 REPORT (STRUTTURA PREVISTA)

Tipologie:

* per operatore
* per destinazione
* per periodo

Output:

* CSV
* PDF (futuro)

---

# 📱 APP MOBILE

* usa sessione dipendente
* calendario dedicato
* notifiche push (in sviluppo)

---

# 🧠 REGOLE IMPORTANTI

1. PHP procedurale → NO MVC
2. sempre usare:

   * `db_connect()`
   * `require_login()`
3. permessi SEMPRE controllati con:

   * `can()`
4. redirect sempre con:

   * `redirect()`

---

# 🚀 OBIETTIVI FUTURI

* dashboard intelligente
* notifiche avanzate
* esportazione PDF
* miglioramento UI
* configurazione dinamica completa

---

# 📌 NOTE PER CHATGPT

Quando lavori su Turnar:

* NON cambiare struttura senza motivo
* usa funzioni esistenti
* mantieni compatibilità con permessi
* restituisci SEMPRE file completi

---

# 🔚 FINE
