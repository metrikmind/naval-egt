# 🚀 Naval EGT - Guida Installazione Rapida

## ⚡ Installazione in 5 Minuti

### 1. **Prerequisiti**
```
✅ WordPress 5.0 o superiore
✅ PHP 7.4 o superiore  
✅ MySQL 5.7 o superiore
✅ Almeno 256MB memoria PHP
✅ Permessi scrittura wp-content/uploads/
```

### 2. **Upload Plugin**
```bash
# Via FTP/cPanel
1. Carica cartella 'naval-egt' in /wp-content/plugins/
2. Vai su Plugin > Plugin installati  
3. Attiva "Naval EGT - Area Riservata"

# Via WordPress Admin
1. Plugin > Aggiungi nuovo > Carica plugin
2. Seleziona file ZIP > Installa > Attiva
```

### 3. **Configurazione Base** ⭐
```
👉 Vai su: Naval EGT > Impostazioni

GENERALE:
- Dimensione max file: 50MB
- Estensioni: pdf,doc,docx,xls,xlsx,jpg,png,dwg,dxf
- Email admin: la-tua-email@dominio.it

EMAIL:
- Nome mittente: Naval EGT
- Email mittente: noreply@tuodominio.it

FTP:
- Lascia percorso default: wp-content/uploads/naval-egt-ftp/
- ✅ Crea cartelle automaticamente

SICUREZZA:
- Max tentativi login: 5
- Durata blocco: 30 minuti
- Forza password: Media

👉 Clicca SALVA IMPOSTAZIONI
```

### 4. **Verifica Installazione** ✅
```
✅ Vai su: tuosito.it/area-riservata/
✅ Vedi form login? → OK!
✅ Prova registrazione test
✅ Controlla email attivazione
✅ Login con credenziali
✅ Vai su: tuosito.it/dashboard-clienti/
✅ Vedi dashboard? → PERFETTO! 🎉
```

---

## 🛠️ Risoluzione Problemi Veloci

### ❌ **Errore 500 dopo attivazione**
```php
// Aggiungi in wp-config.php per debug:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Controlla log: wp-content/debug.log
// Soluzione comune: aumenta memory_limit PHP
```

### ❌ **Area riservata non si apre**
```
1. Vai su: Pagine > Tutte le pagine
2. Cerca "Area Riservata" 
3. Non esiste? Disattiva e riattiva plugin
4. Ancora problemi? Permalink > Salva modifiche
```

### ❌ **Upload file non funziona**
```bash
# Via FTP, controlla permessi:
chmod 755 wp-content/uploads/
chmod 755 wp-content/uploads/naval-egt-ftp/

# In wp-config.php aggiungi:
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '50M');
```

### ❌ **Email non arrivano**
```
1. Installa plugin: WP Mail SMTP
2. Configura SMTP del tuo provider
3. Testa invio da: Naval EGT > Impostazioni > Email
```

---

## ⚙️ Configurazione Avanzata (Opzionale)

### **Integrazione con Bricks Builder**
```php
// In functions.php del tema:
add_action('wp_head', function() {
    if (is_page('area-riservata')) {
        echo '<style>
        .brxe-container { max-width: 800px; }
        .naval-egt-header { margin-bottom: 2rem; }
        </style>';
    }
});
```

### **Personalizzazione Colori**
```php
// In functions.php:
add_filter('naval_egt_custom_config', function($config) {
    $config['theme_colors'] = [
        'primary' => '#your-color',
        'secondary' => '#your-secondary'
    ];
    return $config;
});
```

### **Estensioni File Custom**
```php
// Aggiungi nuove estensioni:
add_filter('naval_egt_allowed_extensions', function($ext) {
    return array_merge($ext, ['ai', 'psd', 'sketch']);
});
```

---

## 📋 Checklist Post-Installazione

### **Configurazione Utenti**
- [ ] Crea utente test
- [ ] Verifica email attivazione  
- [ ] Test upload file
- [ ] Test download file
- [ ] Controlla log attività

### **Sicurezza**
- [ ] Cambia email admin default
- [ ] Testa reset password
- [ ] Verifica blocco brute force
- [ ] Controlla permessi cartelle FTP
- [ ] Backup database

### **Performance**
- [ ] Test velocità pagine
- [ ] Verifica cache attiva
- [ ] Ottimizza immagini
- [ ] Configura HTTPS
- [ ] Test su mobile

### **Funzionalità**
- [ ] Dashboard admin funzionante
- [ ] Statistiche aggiornate
- [ ] Export CSV utenti
- [ ] Notifiche email
- [ ] Log sistem

---

## 🎯 Setup Produzione

### **Configurazioni Raccomandate**
```php
// wp-config.php
define('WP_DEBUG', false);
define('NAVAL_EGT_DEBUG', false);
define('NAVAL_EGT_ENABLE_QUERY_CACHE', true);
define('NAVAL_EGT_AUTO_BACKUP', true);
define('NAVAL_EGT_AUTO_CLEANUP', true);
```

### **Sicurezza Produzione**
```apache
# .htaccess nella root
# Blocca accesso diretto ai file PHP del plugin
<Files "naval-egt.php">
    Order Allow,Deny
    Deny from all
</Files>

# Proteggi cartella FTP
<Directory "wp-content/uploads/naval-egt-ftp">
    Options -Indexes
    AllowOverride None
</Directory>
```

### **Backup Automatico**
```php
// functions.php - Backup giornaliero DB
if (!wp_next_scheduled('naval_egt_daily_backup')) {
    wp_schedule_event(time(), 'daily', 'naval_egt_daily_backup');
}

add_action('naval_egt_daily_backup', function() {
    // Il tuo script backup
});
```

---

## 📞 Contatti Supporto

### **Supporto Tecnico**
- 📧 **Email**: technical@navalegt.it
- 🕒 **Orari**: Lun-Ven 9:00-18:00
- ⚡ **Risposta**: Entro 24h

### **Emergenze**
- 🚨 **Sito down**: Chiamata immediata
- 🔒 **Problemi sicurezza**: Email prioritaria
- 📊 **Perdita dati**: Backup immediato

---

## ✅ Installazione Completata!

Il tuo sistema Naval EGT è ora attivo e funzionante.

### **Prossimi Passi:**
1. **Aggiungi i primi utenti** dalla dashboard admin
2. **Personalizza email** di benvenuto  
3. **Configura backup** automatici
4. **Monitora log** attività
5. **Testa tutte** le funzionalità

### **URLs Importanti:**
- 🏠 **Area Riservata**: `tuosito.it/area-riservata/`
- 📊 **Dashboard Admin**: `tuosito.it/wp-admin/admin.php?page=naval-egt`
- ⚙️ **Impostazioni**: `tuosito.it/wp-admin/admin.php?page=naval-egt-settings`

---

**🎉 Congratulazioni! Il sistema è pronto per essere utilizzato dai tuoi clienti.**

*Per domande o assistenza, non esitare a contattare il supporto tecnico.*

---

**Made with ❤️ by Scherpmind di Raffaele Borrelli**