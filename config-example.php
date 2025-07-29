<?php
/**
 * Naval EGT Plugin - Advanced Configuration Example
 * 
 * Copia questo file come config-local.php nella root del plugin
 * per personalizzare le configurazioni avanzate.
 * 
 * IMPORTANTE: Non modificare questo file direttamente.
 * Usa config-local.php per le tue personalizzazioni.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// CONFIGURAZIONE BASE
// =============================================================================

// Dimensione massima file di default (in MB)
if (!defined('NAVAL_EGT_DEFAULT_MAX_FILE_SIZE')) {
    define('NAVAL_EGT_DEFAULT_MAX_FILE_SIZE', 50);
}

// Numero massimo di file per utente
if (!defined('NAVAL_EGT_MAX_FILES_PER_USER')) {
    define('NAVAL_EGT_MAX_FILES_PER_USER', 100);
}

// Durata sessione di default (in ore)
if (!defined('NAVAL_EGT_DEFAULT_SESSION_TIMEOUT')) {
    define('NAVAL_EGT_DEFAULT_SESSION_TIMEOUT', 24);
}

// Durata conservazione log (in giorni)
if (!defined('NAVAL_EGT_LOG_RETENTION_DAYS')) {
    define('NAVAL_EGT_LOG_RETENTION_DAYS', 90);
}

// =============================================================================
// CONFIGURAZIONE FTP
// =============================================================================

// Percorso base FTP (relativo a wp-content/uploads/)
if (!defined('NAVAL_EGT_FTP_BASE_DIR')) {
    define('NAVAL_EGT_FTP_BASE_DIR', 'naval-egt-ftp');
}

// Permessi cartelle FTP
if (!defined('NAVAL_EGT_FOLDER_PERMISSIONS')) {
    define('NAVAL_EGT_FOLDER_PERMISSIONS', 0755);
}

// Permessi file FTP
if (!defined('NAVAL_EGT_FILE_PERMISSIONS')) {
    define('NAVAL_EGT_FILE_PERMISSIONS', 0644);
}

// Crea sottocartelle automaticamente
if (!defined('NAVAL_EGT_AUTO_CREATE_SUBFOLDERS')) {
    define('NAVAL_EGT_AUTO_CREATE_SUBFOLDERS', true);
}

// Sottocartelle di default
if (!defined('NAVAL_EGT_DEFAULT_SUBFOLDERS')) {
    define('NAVAL_EGT_DEFAULT_SUBFOLDERS', 'uploads,downloads,shared');
}

// =============================================================================
// CONFIGURAZIONE SICUREZZA
// =============================================================================

// Numero massimo tentativi login
if (!defined('NAVAL_EGT_MAX_LOGIN_ATTEMPTS')) {
    define('NAVAL_EGT_MAX_LOGIN_ATTEMPTS', 5);
}

// Durata blocco dopo tentativi falliti (in minuti)
if (!defined('NAVAL_EGT_LOCKOUT_DURATION')) {
    define('NAVAL_EGT_LOCKOUT_DURATION', 30);
}

// Abilita scansione antimalware
if (!defined('NAVAL_EGT_ENABLE_MALWARE_SCAN')) {
    define('NAVAL_EGT_ENABLE_MALWARE_SCAN', true);
}

// Forza HTTPS per area riservata
if (!defined('NAVAL_EGT_FORCE_HTTPS')) {
    define('NAVAL_EGT_FORCE_HTTPS', false);
}

// Salt personalizzato per hash (cambia questo valore!)
if (!defined('NAVAL_EGT_SALT')) {
    define('NAVAL_EGT_SALT', 'your-unique-salt-here-change-this');
}

// =============================================================================
// CONFIGURAZIONE EMAIL
// =============================================================================

// Mittente email di default
if (!defined('NAVAL_EGT_DEFAULT_FROM_EMAIL')) {
    define('NAVAL_EGT_DEFAULT_FROM_EMAIL', get_option('admin_email'));
}

// Nome mittente di default
if (!defined('NAVAL_EGT_DEFAULT_FROM_NAME')) {
    define('NAVAL_EGT_DEFAULT_FROM_NAME', get_bloginfo('name'));
}

// Abilita notifiche email admin
if (!defined('NAVAL_EGT_ADMIN_NOTIFICATIONS')) {
    define('NAVAL_EGT_ADMIN_NOTIFICATIONS', true);
}

// Template email personalizzato
if (!defined('NAVAL_EGT_CUSTOM_EMAIL_TEMPLATE')) {
    define('NAVAL_EGT_CUSTOM_EMAIL_TEMPLATE', false);
}

// =============================================================================
// CONFIGURAZIONE PERFORMANCE
// =============================================================================

// Abilita cache query
if (!defined('NAVAL_EGT_ENABLE_QUERY_CACHE')) {
    define('NAVAL_EGT_ENABLE_QUERY_CACHE', true);
}

// Durata cache (in secondi)
if (!defined('NAVAL_EGT_CACHE_DURATION')) {
    define('NAVAL_EGT_CACHE_DURATION', 300); // 5 minuti
}

// Numero massimo risultati per pagina
if (!defined('NAVAL_EGT_MAX_RESULTS_PER_PAGE')) {
    define('NAVAL_EGT_MAX_RESULTS_PER_PAGE', 50);
}

// Abilita lazy loading
if (!defined('NAVAL_EGT_ENABLE_LAZY_LOADING')) {
    define('NAVAL_EGT_ENABLE_LAZY_LOADING', true);
}

// =============================================================================
// CONFIGURAZIONE DEBUG
// =============================================================================

// Abilita modalità debug
if (!defined('NAVAL_EGT_DEBUG')) {
    define('NAVAL_EGT_DEBUG', false);
}

// Log debug dettagliato
if (!defined('NAVAL_EGT_DEBUG_VERBOSE')) {
    define('NAVAL_EGT_DEBUG_VERBOSE', false);
}

// Log query SQL
if (!defined('NAVAL_EGT_LOG_QUERIES')) {
    define('NAVAL_EGT_LOG_QUERIES', false);
}

// =============================================================================
// CONFIGURAZIONE PERSONALIZZAZIONI
// =============================================================================

// Prefisso CSS personalizzato
if (!defined('NAVAL_EGT_CSS_PREFIX')) {
    define('NAVAL_EGT_CSS_PREFIX', 'naval-egt');
}

// Tema colori personalizzato
if (!defined('NAVAL_EGT_CUSTOM_COLORS')) {
    define('NAVAL_EGT_CUSTOM_COLORS', json_encode([
        'primary' => '#2a5298',
        'secondary' => '#1e3c72',
        'success' => '#28a745',
        'warning' => '#ffc107',
        'danger' => '#dc3545',
        'info' => '#17a2b8'
    ]));
}

// Logo personalizzato URL
if (!defined('NAVAL_EGT_CUSTOM_LOGO_URL')) {
    define('NAVAL_EGT_CUSTOM_LOGO_URL', '');
}

// =============================================================================
// CONFIGURAZIONE INTEGRAZIONI
// =============================================================================

// API Key per servizi esterni (se necessaria)
if (!defined('NAVAL_EGT_API_KEY')) {
    define('NAVAL_EGT_API_KEY', '');
}

// Webhook URL per notifiche
if (!defined('NAVAL_EGT_WEBHOOK_URL')) {
    define('NAVAL_EGT_WEBHOOK_URL', '');
}

// Integrazione con CRM
if (!defined('NAVAL_EGT_CRM_INTEGRATION')) {
    define('NAVAL_EGT_CRM_INTEGRATION', false);
}

// =============================================================================
// CONFIGURAZIONE ESTENSIONI FILE
// =============================================================================

// Estensioni file consentite (override default)
if (!defined('NAVAL_EGT_ALLOWED_EXTENSIONS')) {
    define('NAVAL_EGT_ALLOWED_EXTENSIONS', implode(',', [
        // Documenti
        'pdf', 'doc', 'docx', 'odt', 'rtf', 'txt',
        // Fogli di calcolo  
        'xls', 'xlsx', 'ods', 'csv',
        // Presentazioni
        'ppt', 'pptx', 'odp',
        // Archivi
        'zip', 'rar', '7z', 'tar', 'gz',
        // Immagini
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'svg', 'webp',
        // CAD/Tecnici
        'dwg', 'dxf', 'step', 'stp', 'iges', 'igs', 'stl',
        // Audio/Video (se necessari)
        // 'mp4', 'avi', 'mov', 'mp3', 'wav'
    ]));
}

// Estensioni vietate (sicurezza)
if (!defined('NAVAL_EGT_FORBIDDEN_EXTENSIONS')) {
    define('NAVAL_EGT_FORBIDDEN_EXTENSIONS', implode(',', [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phps',
        'asp', 'aspx', 'jsp', 'pl', 'py', 'rb',
        'exe', 'bat', 'cmd', 'com', 'scr', 'msi',
        'sh', 'bash', 'ps1', 'vbs', 'js', 'jar'
    ]));
}

// =============================================================================
// CONFIGURAZIONE BACKUP E MANUTENZIONE
// =============================================================================

// Abilita backup automatico
if (!defined('NAVAL_EGT_AUTO_BACKUP')) {
    define('NAVAL_EGT_AUTO_BACKUP', false);
}

// Frequenza backup automatico
if (!defined('NAVAL_EGT_BACKUP_FREQUENCY')) {
    define('NAVAL_EGT_BACKUP_FREQUENCY', 'weekly'); // daily, weekly, monthly
}

// Cartella backup
if (!defined('NAVAL_EGT_BACKUP_DIR')) {
    define('NAVAL_EGT_BACKUP_DIR', wp_upload_dir()['basedir'] . '/naval-egt-backups/');
}

// Numero massimo backup da conservare
if (!defined('NAVAL_EGT_MAX_BACKUPS')) {
    define('NAVAL_EGT_MAX_BACKUPS', 10);
}

// Pulizia automatica file temporanei
if (!defined('NAVAL_EGT_AUTO_CLEANUP')) {
    define('NAVAL_EGT_AUTO_CLEANUP', true);
}

// =============================================================================
// CONFIGURAZIONE MULTISITE
// =============================================================================

// Abilita supporto multisite
if (!defined('NAVAL_EGT_MULTISITE_SUPPORT')) {
    define('NAVAL_EGT_MULTISITE_SUPPORT', is_multisite());
}

// Condividi utenti tra siti
if (!defined('NAVAL_EGT_SHARED_USERS')) {
    define('NAVAL_EGT_SHARED_USERS', false);
}

// =============================================================================
// FUNZIONI HELPER PER CONFIGURAZIONE
// =============================================================================

/**
 * Ottieni configurazione con fallback
 */
function naval_egt_get_config($key, $default = null) {
    $config_map = [
        'max_file_size' => NAVAL_EGT_DEFAULT_MAX_FILE_SIZE,
        'session_timeout' => NAVAL_EGT_DEFAULT_SESSION_TIMEOUT,
        'log_retention' => NAVAL_EGT_LOG_RETENTION_DAYS,
        'ftp_base_dir' => NAVAL_EGT_FTP_BASE_DIR,
        'max_login_attempts' => NAVAL_EGT_MAX_LOGIN_ATTEMPTS,
        'lockout_duration' => NAVAL_EGT_LOCKOUT_DURATION,
        'debug' => NAVAL_EGT_DEBUG,
        'cache_duration' => NAVAL_EGT_CACHE_DURATION,
    ];
    
    return isset($config_map[$key]) ? $config_map[$key] : $default;
}

/**
 * Verifica se una funzionalità è abilitata
 */
function naval_egt_is_feature_enabled($feature) {
    $features = [
        'malware_scan' => NAVAL_EGT_ENABLE_MALWARE_SCAN,
        'query_cache' => NAVAL_EGT_ENABLE_QUERY_CACHE,
        'lazy_loading' => NAVAL_EGT_ENABLE_LAZY_LOADING,
        'admin_notifications' => NAVAL_EGT_ADMIN_NOTIFICATIONS,
        'auto_backup' => NAVAL_EGT_AUTO_BACKUP,
        'auto_cleanup' => NAVAL_EGT_AUTO_CLEANUP,
        'multisite' => NAVAL_EGT_MULTISITE_SUPPORT,
        'crm_integration' => NAVAL_EGT_CRM_INTEGRATION,
    ];
    
    return isset($features[$feature]) ? $features[$feature] : false;
}

/**
 * Ottieni colori tema personalizzati
 */
function naval_egt_get_theme_colors() {
    return json_decode(NAVAL_EGT_CUSTOM_COLORS, true);
}

/**
 * Ottieni estensioni file consentite come array
 */
function naval_egt_get_allowed_extensions() {
    return array_map('trim', explode(',', NAVAL_EGT_ALLOWED_EXTENSIONS));
}

/**
 * Ottieni estensioni file vietate come array
 */
function naval_egt_get_forbidden_extensions() {
    return array_map('trim', explode(',', NAVAL_EGT_FORBIDDEN_EXTENSIONS));
}

/**
 * Ottieni sottocartelle di default come array
 */
function naval_egt_get_default_subfolders() {
    return array_map('trim', explode(',', NAVAL_EGT_DEFAULT_SUBFOLDERS));
}

/**
 * Verifica se debug è attivo
 */
function naval_egt_is_debug() {
    return NAVAL_EGT_DEBUG || (defined('WP_DEBUG') && WP_DEBUG);
}

/**
 * Log debug personalizzato
 */
function naval_egt_debug_log($message, $context = []) {
    if (!naval_egt_is_debug()) {
        return;
    }
    
    $log_message = '[Naval EGT Debug] ' . $message;
    
    if (!empty($context)) {
        $log_message .= ' | Context: ' . json_encode($context);
    }
    
    error_log($log_message);
}

// =============================================================================
// CONFIGURAZIONE AMBIENTE SPECIFICA
// =============================================================================

// Configurazioni per ambiente di sviluppo
if (defined('WP_DEBUG') && WP_DEBUG) {
    // Abilita tutte le funzionalità di debug
    if (!defined('NAVAL_EGT_DEBUG')) {
        define('NAVAL_EGT_DEBUG', true);
    }
    
    if (!defined('NAVAL_EGT_DEBUG_VERBOSE')) {
        define('NAVAL_EGT_DEBUG_VERBOSE', true);
    }
    
    // Disabilita cache in sviluppo
    if (!defined('NAVAL_EGT_ENABLE_QUERY_CACHE')) {
        define('NAVAL_EGT_ENABLE_QUERY_CACHE', false);
    }
}

// Configurazioni per ambiente di produzione
if (defined('WP_ENV') && WP_ENV === 'production') {
    // Abilita tutte le ottimizzazioni
    if (!defined('NAVAL_EGT_ENABLE_QUERY_CACHE')) {
        define('NAVAL_EGT_ENABLE_QUERY_CACHE', true);
    }
    
    if (!defined('NAVAL_EGT_AUTO_BACKUP')) {
        define('NAVAL_EGT_AUTO_BACKUP', true);
    }
    
    if (!defined('NAVAL_EGT_AUTO_CLEANUP')) {
        define('NAVAL_EGT_AUTO_CLEANUP', true);
    }
    
    // Disabilita debug in produzione
    if (!defined('NAVAL_EGT_DEBUG')) {
        define('NAVAL_EGT_DEBUG', false);
    }
}

// =============================================================================
// HOOK DI CONFIGURAZIONE
// =============================================================================

// Permetti override via WordPress hooks
add_action('naval_egt_loaded', function() {
    // Hook per personalizzazioni post-caricamento
    do_action('naval_egt_config_loaded');
});

// Filtro per configurazioni dinamiche
add_filter('naval_egt_runtime_config', function($config) {
    // Permetti modifiche runtime alle configurazioni
    return apply_filters('naval_egt_custom_config', $config);
});

/**
 * ESEMPIO DI USO NEL TEMA O IN ALTRI PLUGIN:
 * 
 * // In functions.php del tema
 * add_filter('naval_egt_custom_config', function($config) {
 *     $config['max_file_size'] = 100; // 100MB
 *     $config['theme_colors']['primary'] = '#ff0000'; // Rosso
 *     return $config;
 * });
 * 
 * // Per disabilitare una funzionalità
 * add_filter('naval_egt_feature_enabled', function($enabled, $feature) {
 *     if ($feature === 'malware_scan') {
 *         return false; // Disabilita scansione malware
 *     }
 *     return $enabled;
 * }, 10, 2);
 */