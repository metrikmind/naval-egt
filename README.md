# Naval EGT - Plugin Area Riservata

**Plugin WordPress per l'area riservata clienti Naval EGT**

Versione: 1.0.0  
Autore: Scherpmind di Raffaele Borrelli  
Compatibilità: WordPress 5.0+ | PHP 7.4+  
Licenza: GPL v2 or later

## 📋 Descrizione

Naval EGT è un plugin WordPress completo per la gestione di un'area riservata dedicata ai clienti. Il plugin offre funzionalità avanzate di registrazione, autenticazione, gestione file FTP e amministrazione utenti.

### ✨ Caratteristiche Principali

- **Sistema di registrazione e login personalizzato**
- **Gestione FTP integrata** con cartelle dedicate per ogni utente
- **Dashboard amministrativa completa**
- **Sistema di ruoli e permessi dedicato**
- **Upload e download file sicuro**
- **Notifiche email automatiche**
- **Log dettagliato delle attività**
- **Interface responsive e user-friendly**
- **Integrazione con Bricks Builder**

## 🚀 Installazione

### Metodo 1: Upload Manuale

1. Scarica il plugin dal repository
2. Carica la cartella `naval-egt` nella directory `/wp-content/plugins/`
3. Attiva il plugin dal pannello amministrativo WordPress
4. Vai su **Naval EGT** > **Impostazioni** per configurare il plugin

### Metodo 2: Installazione da ZIP

1. Vai su **Plugin** > **Aggiungi nuovo** > **Carica plugin**
2. Seleziona il file ZIP del plugin
3. Clicca **Installa ora** e poi **Attiva**

## ⚙️ Configurazione Iniziale

### 1. Impostazioni Base

Dopo l'attivazione, configura le impostazioni base:

- **Dimensione massima file**: Default 50MB
- **Estensioni consentite**: PDF, DOC, XLS, immagini, file CAD
- **Email amministratore**: Per ricevere notifiche
- **Percorso FTP**: Generalmente `wp-content/uploads/naval-egt-ftp/`

### 2. Pagine Automatiche

Il plugin crea automaticamente:

- **Area Riservata** (`/area-riservata/`) - Pagina di login e registrazione
- **Dashboard Clienti** (`/dashboard-clienti/`) - Area riservata utenti

### 3. Ruoli Utente

Viene creato automaticamente il ruolo **Cliente Naval EGT** con permessi specifici.

## 📖 Utilizzo

### Per gli Amministratori

#### Gestione Utenti

1. Vai su **Naval EGT** > **Utenti** 
2. Visualizza, modifica o elimina utenti
3. Cambia status (attivo/sospeso/in attesa)
4. Invia notifiche personalizzate
5. Carica file per utenti specifici

#### Monitoraggio Attività

- **Log completo** di tutte le azioni utenti
- **Statistiche** in tempo reale
- **Report esportabili** in CSV
- **Controllo accessi** e sessioni

#### Gestione File

- Upload file per utenti specifici
- Controllo dimensioni e formati
- Organizzazione in cartelle dedicate
- Backup e pulizia automatica

### Per gli Utenti Finali

#### Registrazione

1. Visita `/area-riservata/`
2. Clicca **"Registrati ora"**
3. Compila il form con i dati richiesti
4. Conferma tramite email ricevuta

#### Area Personale

Una volta loggati, gli utenti accedono a:

- **Dashboard personale** con statistiche
- **Gestione file FTP** (upload/download)
- **Cronologia attività**
- **Profilo utente modificabile**

## 🏗️ Shortcodes Disponibili

### `[naval_egt_login_form]`
Mostra il form di login completo con opzioni di registrazione.

```php
[naval_egt_login_form redirect_to="/dashboard/" show_register_link="true"]
```

**Parametri:**
- `redirect_to`: URL di reindirizzamento dopo il login
- `show_register_link`: Mostra link registrazione (true/false)
- `show_forgot_password`: Mostra link password dimenticata (true/false)

### `[naval_egt_dashboard]`
Mostra la dashboard completa dell'utente loggato.

```php
[naval_egt_dashboard show_profile="true" show_ftp="true" show_stats="true"]
```

**Parametri:**
- `show_profile`: Mostra sezione profilo utente
- `show_ftp`: Mostra gestione file FTP
- `show_stats`: Mostra statistiche utente

### `[naval_egt_ftp_manager]`
Gestione file FTP standalone.

```php
[naval_egt_ftp_manager folder="uploads" allow_upload="true" allow_delete="true"]
```

### `[naval_egt_user_profile]`
Profilo utente standalone.

```php
[naval_egt_user_profile]
```

## 🛠️ Personalizzazione

### Template Override

Per personalizzare l'aspetto, copia i template nella tua cartella theme:

```
/wp-content/themes/your-theme/naval-egt/
├── login-form.php
├── dashboard.php
├── ftp-manager.php
└── user-profile.php
```

### Hook e Filtri

#### Actions

```php
// Dopo la registrazione utente
do_action('naval_egt_user_registered', $user_id);

// Dopo il login utente
do_action('naval_egt_user_logged_in', $user_id);

// Dopo upload file
do_action('naval_egt_file_uploaded', $user_id, $file_path);

// Creazione cartella FTP
do_action('naval_egt_create_ftp_folder', $user_id, $folder_name);
```

#### Filters

```php
// Personalizza dimensione massima file
add_filter('naval_egt_max_file_size', function($size) {
    return 100 * 1024 * 1024; // 100MB
});

// Personalizza estensioni consentite
add_filter('naval_egt_allowed_extensions', function($extensions) {
    return array_merge($extensions, ['ai', 'psd']);
});

// Personalizza email di benvenuto
add_filter('naval_egt_welcome_email_content', function($content, $user) {
    return "Benvenuto " . $user->display_name . "!\n\n" . $content;
}, 10, 2);
```

### CSS Personalizzato

Aggiungi CSS personalizzato nel tuo tema:

```css
/* Personalizza colori primari */
:root {
    --naval-egt-primary: #2a5298;
    --naval-egt-secondary: #1e3c72;
}

/* Personalizza form di login */
.naval-egt-login-form {
    max-width: 500px;
    margin: 0 auto;
}

/* Personalizza dashboard */
.naval-egt-dashboard {
    background: #f8f9fa;
    border-radius: 10px;
}
```

## 🔧 Funzioni Helpers

### PHP

```php
// Verifica se utente può accedere
if (naval_egt_user_can_access()) {
    // L'utente ha accesso all'area riservata
}

// Ottieni dati utente Naval EGT
$user_data = naval_egt_get_user_data();
echo $user_data['company']; // Azienda utente

// Verifica permessi specifici
if (naval_egt_user_can_access($user_id)) {
    // Utente specifico ha accesso
}
```

### JavaScript

```javascript
// Mostra notifica
NavalEGT.showNotice('Messaggio di successo', 'success');

// Formatta dimensione file
const size = NavalEGT.formatFileSize(1024000); // "1 MB"

// Copia negli appunti
NavalEGT.copyToClipboard('Testo da copiare');

// Controllo validità email
if (NavalEGT.isValidEmail(email)) {
    // Email valida
}
```

## 📁 Struttura File

```
naval-egt/
├── naval-egt.php                 # File principale
├── README.md                     # Documentazione
├── includes/                     # Classi PHP
│   ├── class-user-management.php
│   ├── class-ftp-manager.php
│   ├── class-shortcodes.php
│   └── class-ajax-handlers.php
├── admin/                        # Pannello amministrativo
│   ├── admin-page.php
│   ├── settings-page.php
│   └── users-page.php
├── assets/                       # Risorse frontend
│   ├── css/
│   │   ├── style.css
│   │   └── admin-style.css
│   └── js/
│       ├── script.js
│       └── admin-script.js
└── languages/                    # Traduzioni (futuro)
    └── naval-egt.pot
```

## 🔒 Sicurezza

### Misure Implementate

- **Sanitizzazione input**: Tutti gli input sono sanitizzati
- **Validazione CSRF**: Nonces per tutte le operazioni
- **Controllo permessi**: Verifica ruoli per ogni azione
- **Scansione malware**: File upload controllati
- **Protezione directory**: .htaccess per cartelle FTP
- **Log sicurezza**: Tracciamento completo attività
- **Limit login**: Protezione brute force
- **Sessioni sicure**: Timeout e invalidazione automatica

### Best Practices

1. **Aggiorna regolarmente** WordPress e il plugin
2. **Monitora i log** per attività sospette
3. **Configura backup** delle cartelle FTP
4. **Limita permessi** file a 755/644
5. **Usa SSL** per tutte le comunicazioni

## 🐛 Risoluzione Problemi

### Problemi Comuni

#### Upload File Non Funziona

1. Verifica permessi cartella FTP (755)
2. Controlla dimensione massima PHP (`upload_max_filesize`)
3. Verifica spazio disco disponibile
4. Controlla log errori WordPress

#### Utenti Non Ricevono Email

1. Configura SMTP nel plugin email WordPress
2. Verifica impostazioni email nel plugin
3. Controlla spam/junk delle email
4. Testa con plugin come WP Mail SMTP

#### Login Non Funziona

1. Disabilita altri plugin di login
2. Controlla conflitti con tema attivo
3. Verifica tabelle database create correttamente
4. Controlla permessi file plugin

#### Errori Database

```sql
-- Ricrea tabelle se necessarie
CREATE TABLE wp_naval_egt_sessions (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    session_token varchar(255) NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    expires_at datetime NOT NULL,
    ip_address varchar(45),
    user_agent text,
    PRIMARY KEY (id)
);
```

### Debug Mode

Abilita debug aggiungendo in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('NAVAL_EGT_DEBUG', true);
```

## 📊 Performance

### Ottimizzazioni Incluse

- **Cache query database** per statistiche
- **Lazy loading** immagini e contenuti
- **Minificazione** CSS/JS in produzione
- **Compressione GZIP** file statici
- **Ottimizzazione immagini** automatica
- **Pulizia automatica** log vecchi

### Raccomandazioni Server

- **PHP 8.0+** per performance ottimali
- **MySQL 5.7+** o **MariaDB 10.3+**
- **Almeno 256MB** memoria PHP
- **SSD storage** per directory FTP
- **Backup automatici** giornalieri

## 🔄 Aggiornamenti

### Processo di Aggiornamento

1. **Backup completo** sito e database
2. **Disattiva plugin** temporaneamente
3. **Carica nuova versione**
4. **Riattiva plugin**
5. **Verifica funzionalità** principali

### Compatibilità Database

Il plugin gestisce automaticamente:
- **Migrazioni database** tra versioni
- **Aggiornamento strutture** tabelle
- **Preservazione dati** utente esistenti
- **Rollback automatico** in caso di errori

## 🆘 Supporto

### Canali di Supporto

- **Email tecnica**: technical@navalegt.it
- **Documentazione online**: [Link documentazione]
- **Repository GitHub**: [Link repository]

### Informazioni per il Supporto

Quando richiedi supporto, includi:

1. **Versione WordPress** e **versione plugin**
2. **Tema attivo** e **plugin attivi**
3. **Messaggio errore completo**
4. **Log errori** se disponibili
5. **Screenshots** del problema

### Sistema Ticket

Per problemi urgenti, utilizza il sistema ticket interno con:
- **Priorità** (Bassa/Media/Alta/Critica)
- **Categoria** (Bug/Feature/Supporto)
- **Descrizione dettagliata** del problema
- **Passi per riprodurre** l'errore

## 📜 Changelog

### Versione 1.0.0 (Data Release)
- ✅ Rilascio iniziale
- ✅ Sistema registrazione/login completo  
- ✅ Gestione FTP integrata
- ✅ Dashboard amministrativa
- ✅ Shortcodes e personalizzazione
- ✅ Sistema log e sicurezza
- ✅ Documentazione completa

### Prossime Versioni (Roadmap)

#### v1.1.0 (Pianificata)
- 🔄 Sistema notifiche push
- 🔄 API REST per integrazioni
- 🔄 Multilingua (IT/EN)
- 🔄 App mobile companion

#### v1.2.0 (Futuro)
- 🔄 Sistema chat integrata
- 🔄 Workflow approvazione file
- 🔄 Integration CRM/ERP
- 🔄 Dashboard analytics avanzata

## 📄 Licenza

Questo plugin è rilasciato sotto licenza **GPL v2 or later**.

```
Naval EGT Plugin
Copyright (C) 2024 Scherpmind di Raffaele Borrelli

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## 🙏 Crediti

- **Sviluppatore**: Scherpmind di Raffaele Borrelli
- **Cliente**: Naval EGT
- **Framework CSS**: Custom responsive framework
- **Icone**: Emoji Unicode standard
- **Ispirazioni**: WordPress core, best practices community

---

**Made with ❤️ for Naval EGT**

Per ulteriori informazioni, visita [www.navalegt.it](https://www.navalegt.it/) o contatta [technical@navalegt.it](mailto:technical@navalegt.it).