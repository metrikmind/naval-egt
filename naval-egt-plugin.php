<?php
/**
 * Plugin Name: Naval EGT - Area Riservata Clienti
 * Plugin URI: https://metrikmind.it
 * Description: Plugin completo per gestione area riservata clienti con integrazione Dropbox
 * Version: 1.0.19
 * Author: Metrikmind
 * Author URI: https://metrikmind.it
 * Requires at least: 6.8.2
 * Tested up to: 6.8.2
 * License: GPL v2 or later
 * Text Domain: naval-egt
 * Domain Path: /languages
 */

// Prevenire accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Definire costanti del plugin
define('NAVAL_EGT_VERSION', '1.0.0');
define('NAVAL_EGT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NAVAL_EGT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NAVAL_EGT_PLUGIN_FILE', __FILE__);

/**
 * Classe principale del plugin Naval EGT
 */
class Naval_EGT_Plugin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Inizializza gli hooks del plugin
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('Naval_EGT_Plugin', 'uninstall'));
        
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'handle_dropbox_callback'));
        
        // RIMOSSO: add_action('admin_menu', array($this, 'add_admin_menu'));
        // Il menu viene gestito dalla classe Naval_EGT_Admin
        
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
        add_action('wp_ajax_naval_egt_ajax', array($this, 'handle_ajax'));
        add_action('wp_ajax_nopriv_naval_egt_ajax', array($this, 'handle_ajax'));
        add_action('wp_ajax_naval_egt_configure_dropbox', array($this, 'handle_dropbox_config'));
        add_shortcode('naval_egt_area_riservata', array($this, 'area_riservata_shortcode'));
    }
    
    /**
     * Carica le dipendenze del plugin
     */
    private function load_dependencies() {
        require_once NAVAL_EGT_PLUGIN_DIR . 'includes/class-naval-egt-database.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'includes/class-naval-egt-user-manager.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'includes/class-naval-egt-dropbox.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'includes/class-naval-egt-file-manager.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'includes/class-naval-egt-activity-logger.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'includes/class-naval-egt-email.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'includes/class-naval-egt-export.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'admin/class-naval-egt-admin.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'public/class-naval-egt-public.php';
    }
    
    /**
     * Inizializzazione del plugin
     */
    public function init() {
        load_plugin_textdomain('naval-egt', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Inizializza le classi principali
        Naval_EGT_Database::get_instance();
        Naval_EGT_User_Manager::get_instance();
        Naval_EGT_Dropbox::get_instance();
        Naval_EGT_File_Manager::get_instance();
        Naval_EGT_Activity_Logger::get_instance();
        Naval_EGT_Email::get_instance();
        Naval_EGT_Admin::get_instance();
        Naval_EGT_Public::get_instance();
        
        // Inizializza frontend pubblico
        Naval_EGT_Public::init();
    }
    
    /**
     * Gestisce la configurazione Dropbox via AJAX (ora automatica)
     */
    public function handle_dropbox_config() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        // Configurazione automatica con credenziali integrate
        $dropbox = Naval_EGT_Dropbox::get_instance();
        $result = $dropbox->auto_configure();
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'auth_url' => $result['auth_url']
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Gestisce il callback OAuth di Dropbox - VERSIONE CORRETTA
     */
    public function handle_dropbox_callback() {
        if (!is_admin() || !isset($_GET['dropbox_callback']) || $_GET['dropbox_callback'] !== '1') {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        error_log('Naval EGT: Callback OAuth ricevuto - GET params: ' . print_r($_GET, true));
        
        // Gestisce errori OAuth
        if (isset($_GET['error'])) {
            $error_message = isset($_GET['error_description']) ? $_GET['error_description'] : $_GET['error'];
            error_log('Naval EGT: Errore OAuth ricevuto: ' . $error_message);
            
            add_action('admin_notices', function() use ($error_message) {
                echo '<div class="notice notice-error"><p><strong>Errore Dropbox:</strong> ' . esc_html($error_message) . '</p></div>';
            });
            return;
        }
        
        // Elabora il codice di autorizzazione
        if (isset($_GET['code'])) {
            $code = sanitize_text_field($_GET['code']);
            
            // Costruisce l'URL di redirect esatto
            $site_url = get_site_url();
            if (strpos($site_url, 'https://') !== 0) {
                $site_url = str_replace('http://', 'https://', $site_url);
            }
            $redirect_uri = $site_url . '/wp-admin/admin.php?page=naval-egt-settings&dropbox_callback=1';
            
            error_log('Naval EGT: Gestione callback OAuth - Code ricevuto: ' . substr($code, 0, 10) . '...');
            error_log('Naval EGT: Redirect URI utilizzato: ' . $redirect_uri);
            
            $dropbox = Naval_EGT_Dropbox::get_instance();
            $result = $dropbox->exchange_code_for_token($code, $redirect_uri);
            
            if ($result['success']) {
                error_log('Naval EGT: Token ottenuto con successo, test connessione...');
                
                // Forza il reload delle credenziali
                $dropbox->reload_credentials();
                
                // Breve pausa per assicurarsi che il token sia salvato
                usleep(500000); // 0.5 secondi
                
                // Test della connessione
                $account_info = $dropbox->get_account_info();
                if ($account_info['success']) {
                    add_action('admin_notices', function() use ($account_info) {
                        $name = isset($account_info['data']['name']['display_name']) ? $account_info['data']['name']['display_name'] : 'Utente';
                        $email = isset($account_info['data']['email']) ? $account_info['data']['email'] : '';
                        echo '<div class="notice notice-success is-dismissible">';
                        echo '<p><strong>🎉 Dropbox configurato con successo!</strong></p>';
                        echo '<p>👤 <strong>Account:</strong> ' . esc_html($name) . '</p>';
                        if ($email) {
                            echo '<p>📧 <strong>Email:</strong> ' . esc_html($email) . '</p>';
                        }
                        echo '<p>✅ La connessione è attiva e funzionante.</p>';
                        echo '</div>';
                    });
                    error_log('Naval EGT: Test connessione riuscito - Account: ' . (isset($account_info['data']['name']['display_name']) ? $account_info['data']['name']['display_name'] : 'N/A'));
                } else {
                    add_action('admin_notices', function() use ($account_info) {
                        echo '<div class="notice notice-warning is-dismissible">';
                        echo '<p><strong>⚠️ Token ottenuto ma test di connessione fallito.</strong></p>';
                        echo '<p><strong>Errore:</strong> ' . esc_html($account_info['message']) . '</p>';
                        echo '<p>Riprova la configurazione se il problema persiste.</p>';
                        echo '</div>';
                    });
                    error_log('Naval EGT: Test connessione fallito: ' . $account_info['message']);
                }
            } else {
                add_action('admin_notices', function() use ($result) {
                    echo '<div class="notice notice-error is-dismissible">';
                    echo '<p><strong>❌ Errore durante l\'ottenimento del token:</strong></p>';
                    echo '<p>' . esc_html($result['message']) . '</p>';
                    echo '<p>Verifica le credenziali Dropbox e riprova.</p>';
                    echo '</div>';
                });
                error_log('Naval EGT: Errore ottenimento token: ' . $result['message']);
            }
            
            // Redirect per pulire l'URL - con parametro di stato
            $redirect_url = admin_url('admin.php?page=naval-egt-settings&tab=dropbox');
            if ($result['success']) {
                $redirect_url .= '&auth_result=success';
            } else {
                $redirect_url .= '&auth_result=error';
            }
            
            error_log('Naval EGT: Redirect a: ' . $redirect_url);
            
            // Usa JavaScript per il redirect se gli header sono già stati inviati
            if (headers_sent()) {
                echo '<script type="text/javascript">window.location.href = "' . esc_url($redirect_url) . '";</script>';
                echo '<noscript><meta http-equiv="refresh" content="0; url=' . esc_url($redirect_url) . '"></noscript>';
                exit;
            } else {
                wp_redirect($redirect_url);
                exit;
            }
        } else {
            error_log('Naval EGT: Callback ricevuto senza codice di autorizzazione');
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>Errore:</strong> Codice di autorizzazione non ricevuto da Dropbox.</p>';
                echo '<p>Riprova la configurazione.</p>';
                echo '</div>';
            });
        }
    }
    
    /**
     * Attivazione del plugin
     */
    public function activate() {
        Naval_EGT_Database::create_tables();
        
        // Crea la pagina dell'area riservata se non esiste
        $page_exists = get_page_by_path('area-riservata-naval-egt');
        if (!$page_exists) {
            wp_insert_post(array(
                'post_title' => 'Area Riservata Naval EGT',
                'post_content' => '[naval_egt_area_riservata]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => 'area-riservata-naval-egt'
            ));
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Disattivazione del plugin
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Disinstallazione del plugin
     */
    public static function uninstall() {
        Naval_EGT_Database::drop_tables();
        
        // Rimuove le opzioni del plugin
        delete_option('naval_egt_settings');
        delete_option('naval_egt_dropbox_access_token');
        delete_option('naval_egt_dropbox_refresh_token');
        
        // Rimuove la pagina dell'area riservata
        $page = get_page_by_path('area-riservata-naval-egt');
        if ($page) {
            wp_delete_post($page->ID, true);
        }
    }
    
    /**
     * RIMOSSO: add_admin_menu - gestito da Naval_EGT_Admin
     * RIMOSSO: admin_page - gestito da Naval_EGT_Admin  
     * RIMOSSO: settings_page - gestito da Naval_EGT_Admin
     */
    
    /**
     * Enqueue scripts admin
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'naval-egt') !== false) {
            wp_enqueue_script('naval-egt-admin-js', NAVAL_EGT_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), NAVAL_EGT_VERSION, true);
            wp_enqueue_style('naval-egt-admin-css', NAVAL_EGT_PLUGIN_URL . 'admin/css/admin.css', array(), NAVAL_EGT_VERSION);
            
            wp_localize_script('naval-egt-admin-js', 'naval_egt_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('naval_egt_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Sei sicuro di voler eliminare questo elemento?', 'naval-egt'),
                    'loading' => __('Caricamento...', 'naval-egt'),
                    'error' => __('Si è verificato un errore', 'naval-egt')
                )
            ));
        }
    }
    
    /**
     * Enqueue scripts frontend
     */
    public function frontend_enqueue_scripts() {
        if (is_page('area-riservata-naval-egt') || has_shortcode(get_post()->post_content ?? '', 'naval_egt_area_riservata')) {
            wp_enqueue_script('naval-egt-public-js', NAVAL_EGT_PLUGIN_URL . 'public/js/public.js', array('jquery'), NAVAL_EGT_VERSION, true);
            wp_enqueue_style('naval-egt-public-css', NAVAL_EGT_PLUGIN_URL . 'public/css/public.css', array(), NAVAL_EGT_VERSION);
            
            wp_localize_script('naval-egt-public-js', 'naval_egt_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('naval_egt_nonce'),
                'strings' => array(
                    'login_error' => __('Credenziali non valide', 'naval-egt'),
                    'loading' => __('Caricamento...', 'naval-egt'),
                    'upload_error' => __('Errore durante il caricamento', 'naval-egt')
                )
            ));
        }
    }
    
    /**
     * Gestisce le richieste AJAX - DELEGATO alla classe Admin
     */
    public function handle_ajax() {
        // Delega alla classe Naval_EGT_Admin
        $admin = Naval_EGT_Admin::get_instance();
        $admin->handle_ajax_requests();
    }
    
    /**
     * Shortcode per l'area riservata
     */
    public function area_riservata_shortcode($atts) {
        $atts = shortcode_atts(array(
            'template' => 'default'
        ), $atts, 'naval_egt_area_riservata');
        
        ob_start();
        require_once NAVAL_EGT_PLUGIN_DIR . 'public/views/area-riservata.php';
        return ob_get_clean();
    }
}

// Inizializza il plugin
Naval_EGT_Plugin::get_instance();