<?php
/**
 * Classe per la gestione del database
 */

if (!defined('ABSPATH')) {
    exit;
}

class Naval_EGT_Database {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor
    }
    
    /**
     * Crea le tabelle del database
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabella utenti area riservata
        $table_users = $wpdb->prefix . 'naval_egt_users';
        $sql_users = "CREATE TABLE $table_users (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_code varchar(6) NOT NULL,
            nome varchar(100) NOT NULL,
            cognome varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            username varchar(50) NOT NULL,
            password varchar(255) NOT NULL,
            telefono varchar(20),
            ragione_sociale varchar(200),
            partita_iva varchar(20),
            status enum('ATTIVO','SOSPESO') DEFAULT 'SOSPESO',
            dropbox_folder varchar(255),
            last_login datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_code (user_code),
            UNIQUE KEY email (email),
            UNIQUE KEY username (username)
        ) $charset_collate;";
        
        // Tabella log attività
        $table_logs = $wpdb->prefix . 'naval_egt_activity_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9),
            user_code varchar(6),
            action enum('LOGIN','LOGOUT','UPLOAD','DOWNLOAD','REGISTRATION','ADMIN_UPLOAD','ADMIN_ACTION') NOT NULL,
            file_name varchar(255),
            file_path varchar(500),
            file_size bigint,
            ip_address varchar(45),
            user_agent text,
            details text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY user_code (user_code),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Tabella file (cache info Dropbox)
        $table_files = $wpdb->prefix . 'naval_egt_files';
        $sql_files = "CREATE TABLE $table_files (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            user_code varchar(6) NOT NULL,
            file_name varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            dropbox_path varchar(500) NOT NULL,
            file_size bigint,
            file_type varchar(50),
            dropbox_id varchar(255),
            last_modified datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY user_code (user_code),
            KEY file_name (file_name)
        ) $charset_collate;";
        
        // Tabella impostazioni
        $table_settings = $wpdb->prefix . 'naval_egt_settings';
        $sql_settings = "CREATE TABLE $table_settings (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_users);
        dbDelta($sql_logs);
        dbDelta($sql_files);
        dbDelta($sql_settings);
        
        // Inserisce impostazioni default
        self::insert_default_settings();
    }
    
    /**
     * Elimina le tabelle del database
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'naval_egt_users',
            $wpdb->prefix . 'naval_egt_activity_logs',
            $wpdb->prefix . 'naval_egt_files',
            $wpdb->prefix . 'naval_egt_settings'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
    
    /**
     * Inserisce le impostazioni di default
     */
    private static function insert_default_settings() {
        global $wpdb;
        
        $table_settings = $wpdb->prefix . 'naval_egt_settings';
        
        $default_settings = array(
            'dropbox_app_key' => '',
            'dropbox_app_secret' => '',
            'dropbox_access_token' => '',
            'dropbox_refresh_token' => '',
            'email_notifications' => '1',
            'allowed_file_types' => 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,dwg,dxf,zip,rar',
            'max_file_size' => '10485760', // 10MB
            'user_registration_enabled' => '1',
            'manual_user_activation' => '1',
            'welcome_email_template' => 'Benvenuto nell\'Area Riservata Naval EGT',
            'next_user_code' => '100001'
        );
        
        foreach ($default_settings as $key => $value) {
            $wpdb->replace(
                $table_settings,
                array(
                    'setting_key' => $key,
                    'setting_value' => $value
                ),
                array('%s', '%s')
            );
        }
    }
    
    /**
     * Ottiene un'impostazione
     */
    public static function get_setting($key, $default = '') {
        global $wpdb;
        
        $table_settings = $wpdb->prefix . 'naval_egt_settings';
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $table_settings WHERE setting_key = %s",
            $key
        ));
        
        return $value !== null ? $value : $default;
    }
    
    /**
     * Aggiorna un'impostazione
     */
    public static function update_setting($key, $value) {
        global $wpdb;
        
        $table_settings = $wpdb->prefix . 'naval_egt_settings';
        
        return $wpdb->replace(
            $table_settings,
            array(
                'setting_key' => $key,
                'setting_value' => $value
            ),
            array('%s', '%s')
        );
    }
    
    /**
     * Genera il prossimo codice utente
     */
    public static function get_next_user_code() {
        $next_code = self::get_setting('next_user_code', '100001');
        $new_code = str_pad((int)$next_code + 1, 6, '0', STR_PAD_LEFT);
        self::update_setting('next_user_code', $new_code);
        
        return $next_code;
    }
    
    /**
     * Ottiene statistiche utenti
     */
    public static function get_user_stats() {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        $total_users = $wpdb->get_var("SELECT COUNT(*) FROM $table_users");
        $active_users = $wpdb->get_var("SELECT COUNT(*) FROM $table_users WHERE status = 'ATTIVO' AND last_login IS NOT NULL");
        $pending_users = $wpdb->get_var("SELECT COUNT(*) FROM $table_users WHERE status = 'SOSPESO'");
        
        $table_files = $wpdb->prefix . 'naval_egt_files';
        $total_files = $wpdb->get_var("SELECT COUNT(*) FROM $table_files");
        
        return array(
            'total_users' => (int)$total_users,
            'active_users' => (int)$active_users,
            'pending_users' => (int)$pending_users,
            'total_files' => (int)$total_files
        );
    }
    
    /**
     * Ottiene attività recenti
     */
    public static function get_recent_activities($limit = 10) {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . 'naval_egt_activity_logs';
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT l.*, CONCAT(u.nome, ' ', u.cognome) as user_name
            FROM $table_logs l
            LEFT JOIN $table_users u ON l.user_id = u.id
            ORDER BY l.created_at DESC
            LIMIT %d
        ", $limit), ARRAY_A);
    }
    
    /**
     * Verifica se una tabella esiste
     */
    public static function table_exists($table_name) {
        global $wpdb;
        
        $table = $wpdb->prefix . $table_name;
        return $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
    }
    
    /**
     * Ottiene la versione del database
     */
    public static function get_db_version() {
        return get_option('naval_egt_db_version', '1.0.0');
    }
    
    /**
     * Aggiorna la versione del database
     */
    public static function update_db_version($version) {
        update_option('naval_egt_db_version', $version);
    }
}