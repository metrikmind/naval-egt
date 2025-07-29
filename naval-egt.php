<?php
/**
 * Plugin Name: Naval EGT - Area Riservata
 * Plugin URI: https://www.navalegt.it/
 * Description: Plugin per la gestione dell'area riservata clienti Naval EGT con accesso, registrazione e cartella FTP integrata.
 * Version: 1.0.1
 * Author: Scherpmind di Raffaele Borrelli
 * Author URI: https://scherpmind.com
 * Text Domain: naval-egt
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NAVAL_EGT_VERSION', '1.0.1');
define('NAVAL_EGT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NAVAL_EGT_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Main Naval EGT Plugin Class
 */
class NavalEGTPlugin {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('template_redirect', array($this, 'handle_custom_actions'));
        
        // AJAX Actions - Admin
        add_action('wp_ajax_naval_egt_add_user', array($this, 'ajax_add_user'));
        add_action('wp_ajax_naval_egt_admin_list_users', array($this, 'ajax_list_users'));
        add_action('wp_ajax_naval_egt_admin_update_user_status', array($this, 'ajax_update_user_status'));
        add_action('wp_ajax_naval_egt_admin_delete_user', array($this, 'ajax_delete_user'));
        add_action('wp_ajax_naval_egt_admin_upload_file_for_user', array($this, 'ajax_upload_file_for_user'));
        add_action('wp_ajax_naval_egt_admin_get_logs', array($this, 'ajax_get_logs'));
        add_action('wp_ajax_naval_egt_get_user_data', array($this, 'ajax_get_user_data'));
        add_action('wp_ajax_naval_egt_update_user', array($this, 'ajax_update_user'));
        add_action('wp_ajax_naval_egt_admin_send_notification', array($this, 'ajax_send_notification'));
        
        // AJAX Actions - Frontend
        add_action('wp_ajax_naval_egt_download_file', array($this, 'ajax_download_file'));
        add_action('wp_ajax_naval_egt_refresh_user_files', array($this, 'ajax_refresh_user_files'));
        
        // Export actions
        add_action('wp_ajax_naval_egt_export_users', array($this, 'export_users'));
        add_action('wp_ajax_naval_egt_export_logs', array($this, 'export_logs'));
        
        // Shortcodes
        add_shortcode('naval_egt_login_form', array($this, 'login_form_shortcode'));
        add_shortcode('naval_egt_dashboard', array($this, 'dashboard_shortcode'));
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        load_plugin_textdomain('naval-egt', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        if (!session_id()) {
            session_start();
        }
        
        // Ensure FTP base directory exists
        $this->ensure_ftp_directory();
    }
    
    private function ensure_ftp_directory() {
        $upload_dir = wp_upload_dir();
        $ftp_base = $upload_dir['basedir'] . '/naval-egt-ftp/';
        
        if (!file_exists($ftp_base)) {
            wp_mkdir_p($ftp_base);
            
            // Add security .htaccess
            $htaccess_content = "Options -Indexes\nDeny from all\n";
            file_put_contents($ftp_base . '.htaccess', $htaccess_content);
        }
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style('naval-egt-style', NAVAL_EGT_PLUGIN_URL . 'assets/css/style.css', array(), NAVAL_EGT_VERSION);
        wp_enqueue_script('naval-egt-script', NAVAL_EGT_PLUGIN_URL . 'assets/js/script.js', array('jquery'), NAVAL_EGT_VERSION, true);
        
        wp_localize_script('naval-egt-script', 'naval_egt_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('naval_egt_nonce'),
        ));
    }
    
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'naval-egt') !== false) {
            wp_enqueue_style('naval-egt-admin-style', NAVAL_EGT_PLUGIN_URL . 'assets/css/admin-style.css', array(), NAVAL_EGT_VERSION);
            wp_enqueue_script('naval-egt-admin-script', NAVAL_EGT_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), NAVAL_EGT_VERSION, true);
            
            wp_localize_script('naval-egt-admin-script', 'naval_egt_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('naval_egt_admin'),
            ));
        }
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Naval EGT',
            'Naval EGT',
            'manage_options',
            'naval-egt',
            array($this, 'admin_page'),
            'dashicons-lock',
            30
        );
        
        add_submenu_page(
            'naval-egt',
            'Impostazioni',
            'Impostazioni',
            'manage_options',
            'naval-egt-settings',
            array($this, 'settings_page')
        );
    }
    
    public function admin_page() {
        // Get statistics
        global $wpdb;
        
        $total_users = count(get_users(array('role' => 'naval_egt_client')));
        $active_users = count(get_users(array(
            'role' => 'naval_egt_client',
            'meta_query' => array(
                array(
                    'key' => 'naval_egt_status',
                    'value' => 'active',
                    'compare' => '='
                )
            )
        )));
        $pending_users = count(get_users(array(
            'role' => 'naval_egt_client',
            'meta_query' => array(
                array(
                    'key' => 'naval_egt_status',
                    'value' => 'pending',
                    'compare' => '='
                )
            )
        )));
        
        $logs_table = $wpdb->prefix . 'naval_egt_ftp_logs';
        $total_files = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE action = 'upload'");
        $recent_uploads = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE action = 'upload' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        
        // Get recent activity
        $recent_activity = $wpdb->get_results("
            SELECT l.*, u.display_name, u.user_email 
            FROM $logs_table l 
            LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
            ORDER BY l.created_at DESC 
            LIMIT 10
        ");
        ?>
        
        <div class="naval-egt-admin-wrap">
            <div class="naval-egt-admin-header">
                <h1>Naval EGT - Area di Amministrazione</h1>
                <p>Gestisci gli utenti dell'area riservata, monitora l'attività e configura le impostazioni del plugin.</p>
            </div>

            <!-- Dashboard Statistics -->
            <div class="naval-egt-admin-cards">
                <div class="naval-egt-admin-card stat-total-users" data-stat-type="total_users">
                    <h3>Utenti Totali</h3>
                    <div class="naval-egt-stat-number"><?php echo intval($total_users); ?></div>
                    <div class="naval-egt-stat-label">Registrati nell'area riservata</div>
                </div>

                <div class="naval-egt-admin-card stat-active-users" data-stat-type="active_users">
                    <h3>Utenti Attivi</h3>
                    <div class="naval-egt-stat-number"><?php echo intval($active_users); ?></div>
                    <div class="naval-egt-stat-label">Con accesso confermato</div>
                </div>

                <div class="naval-egt-admin-card stat-pending-users" data-stat-type="pending_users">
                    <h3>In Attesa</h3>
                    <div class="naval-egt-stat-number"><?php echo intval($pending_users); ?></div>
                    <div class="naval-egt-stat-label">Registrazioni da attivare</div>
                </div>

                <div class="naval-egt-admin-card stat-total-files" data-stat-type="total_files">
                    <h3>File Caricati</h3>
                    <div class="naval-egt-stat-number"><?php echo intval($total_files); ?></div>
                    <div class="naval-egt-stat-label">Totale su tutta la piattaforma</div>
                </div>
            </div>

            <!-- Main Content Tabs -->
            <div class="naval-egt-admin-tabs">
                <div class="naval-egt-admin-tab-nav">
                    <button data-tab="overview" class="active">Panoramica</button>
                    <button data-tab="users">Gestione Utenti</button>
                    <button data-tab="files">Gestione File</button>
                    <button data-tab="logs">Log Attività</button>
                </div>

                <div class="naval-egt-admin-tab-content">
                    <!-- Overview Tab -->
                    <div id="overview" class="naval-egt-admin-tab-pane active">
                        <div class="naval-egt-admin-settings-grid">
                            <!-- Recent Activity -->
                            <div class="naval-egt-admin-settings-section">
                                <div class="naval-egt-admin-settings-header">
                                    <h3>Attività Recente</h3>
                                </div>
                                <div class="naval-egt-admin-settings-content">
                                    <?php if (!empty($recent_activity)): ?>
                                    <div class="naval-egt-recent-activity">
                                        <?php foreach ($recent_activity as $activity): ?>
                                        <div class="naval-egt-activity-item">
                                            <div class="naval-egt-activity-icon">
                                                <?php 
                                                switch ($activity->action) {
                                                    case 'upload':
                                                        echo '⬆️';
                                                        break;
                                                    case 'download':
                                                        echo '⬇️';
                                                        break;
                                                    case 'delete':
                                                        echo '🗑️';
                                                        break;
                                                    default:
                                                        echo '📄';
                                                }
                                                ?>
                                            </div>
                                            <div class="naval-egt-activity-details">
                                                <div class="naval-egt-activity-user">
                                                    <strong><?php echo esc_html($activity->display_name ?: 'Utente eliminato'); ?></strong>
                                                    <?php if ($activity->user_email): ?>
                                                    <small>(<?php echo esc_html($activity->user_email); ?>)</small>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="naval-egt-activity-action">
                                                    <?php 
                                                    switch ($activity->action) {
                                                        case 'upload':
                                                            printf('ha caricato %s', '<code>' . esc_html($activity->file_name) . '</code>');
                                                            break;
                                                        case 'download':
                                                            printf('ha scaricato %s', '<code>' . esc_html($activity->file_name) . '</code>');
                                                            break;
                                                        case 'delete':
                                                            printf('ha eliminato %s', '<code>' . esc_html($activity->file_name) . '</code>');
                                                            break;
                                                        case 'admin_upload':
                                                            printf('Admin ha caricato %s', '<code>' . esc_html($activity->file_name) . '</code>');
                                                            break;
                                                    }
                                                    ?>
                                                </div>
                                                <div class="naval-egt-activity-time">
                                                    <?php echo date_i18n('d/m/Y H:i', strtotime($activity->created_at)); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php else: ?>
                                    <div class="naval-egt-admin-empty">
                                        <p>Nessuna attività recente</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Quick Actions -->
                            <div class="naval-egt-admin-settings-section">
                                <div class="naval-egt-admin-settings-header">
                                    <h3>Azioni Rapide</h3>
                                </div>
                                <div class="naval-egt-admin-settings-content">
                                    <div class="naval-egt-admin-flex naval-egt-admin-flex-gap-10" style="flex-direction: column;">
                                        <a href="#" onclick="switchToUsersTab()" class="naval-egt-admin-btn naval-egt-admin-btn-primary">
                                            👥 Gestisci Utenti
                                        </a>
                                        <a href="<?php echo admin_url('admin.php?page=naval-egt-settings'); ?>" class="naval-egt-admin-btn naval-egt-admin-btn-secondary">
                                            ⚙️ Impostazioni
                                        </a>
                                        <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-success" onclick="location.reload()">
                                            🔄 Aggiorna Statistiche
                                        </button>
                                        <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-warning btn-export-users">
                                            📊 Esporta Utenti
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Users Tab -->
                    <div id="users" class="naval-egt-admin-tab-pane">
                        <div class="naval-egt-admin-table-container">
                            <div class="naval-egt-admin-table-header">
                                <h3>Gestione Utenti</h3>
                                <div class="naval-egt-admin-table-filters">
                                    <input type="text" id="user-search" placeholder="Cerca utenti..." class="admin-search-input">
                                    <select id="status-filter" class="admin-filter-select">
                                        <option value="">Tutti gli status</option>
                                        <option value="active">Attivi</option>
                                        <option value="pending">In attesa</option>
                                        <option value="suspended">Sospesi</option>
                                    </select>
                                    <button class="naval-egt-admin-btn naval-egt-admin-btn-primary btn-add-user">
                                        ➕ Aggiungi Utente
                                    </button>
                                    <button class="naval-egt-admin-btn naval-egt-admin-btn-secondary btn-refresh-users">
                                        🔄 Aggiorna
                                    </button>
                                </div>
                            </div>

                            <div id="users-table-container">
                                <div class="naval-egt-admin-loading">
                                    <div class="naval-egt-admin-loading-spinner"></div>
                                    <p>Caricamento utenti...</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Files Tab -->
                    <div id="files" class="naval-egt-admin-tab-pane">
                        <div class="naval-egt-admin-form">
                            <h3>Gestione File Globale</h3>
                            <p>Carica file per specifici utenti o gestisci i file esistenti.</p>
                            
                            <div class="naval-egt-admin-form-row">
                                <div class="naval-egt-form-group">
                                    <label for="select-user-for-upload">Seleziona Utente</label>
                                    <select id="select-user-for-upload" class="widefat">
                                        <option value="">Seleziona un utente...</option>
                                        <?php
                                        $all_users = get_users(array('role' => 'naval_egt_client'));
                                        foreach ($all_users as $user) {
                                            $company = get_user_meta($user->ID, 'naval_egt_company', true);
                                            echo '<option value="' . $user->ID . '">' . esc_html($user->display_name) . 
                                                 ($company ? ' (' . esc_html($company) . ')' : '') . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="naval-egt-form-group">
                                    <label for="select-folder-for-upload">Cartella di Destinazione</label>
                                    <select id="select-folder-for-upload" class="widefat">
                                        <option value="downloads/">Downloads (visibile all'utente)</option>
                                        <option value="shared/">Shared (cartella condivisa)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="naval-egt-form-group">
                                <label for="admin-bulk-file-upload">Carica Files</label>
                                <div class="naval-egt-admin-file-upload" onclick="document.getElementById('admin-bulk-file-upload').click()">
                                    <div class="naval-egt-admin-file-upload-icon">📁</div>
                                    <div class="naval-egt-admin-file-upload-text">Clicca per selezionare i file o trascinali qui</div>
                                    <div class="naval-egt-admin-file-upload-subtext">Supporta PDF, DOC, XLS, immagini, file CAD</div>
                                    <input type="file" id="admin-bulk-file-upload" multiple style="display: none;">
                                </div>
                            </div>
                            
                            <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-primary" id="btn-admin-upload">
                                📤 Carica File per Utente
                            </button>
                        </div>
                    </div>

                    <!-- Logs Tab -->
                    <div id="logs" class="naval-egt-admin-tab-pane">
                        <div id="logs-table-container">
                            <div class="naval-egt-admin-loading">
                                <div class="naval-egt-admin-loading-spinner"></div>
                                <p>Caricamento log...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Edit Modal -->
        <div id="edit-user-modal" class="naval-egt-admin-modal" style="display: none;">
            <div class="naval-egt-admin-modal-content">
                <div class="naval-egt-admin-modal-header">
                    <h3>Modifica Utente</h3>
                    <button class="naval-egt-admin-modal-close">&times;</button>
                </div>
                
                <form id="edit-user-form">
                    <input type="hidden" id="edit_user_id" name="user_id">
                    
                    <div class="naval-egt-admin-form-row">
                        <div class="naval-egt-form-group">
                            <label for="edit_first_name">Nome</label>
                            <input type="text" id="edit_first_name" name="first_name">
                        </div>
                        <div class="naval-egt-form-group">
                            <label for="edit_last_name">Cognome</label>
                            <input type="text" id="edit_last_name" name="last_name">
                        </div>
                    </div>
                    
                    <div class="naval-egt-form-group">
                        <label for="edit_email">Email</label>
                        <input type="email" id="edit_email" name="email">
                    </div>
                    
                    <div class="naval-egt-admin-form-row">
                        <div class="naval-egt-form-group">
                            <label for="edit_company">Azienda</label>
                            <input type="text" id="edit_company" name="company">
                        </div>
                        <div class="naval-egt-form-group">
                            <label for="edit_phone">Telefono</label>
                            <input type="tel" id="edit_phone" name="phone">
                        </div>
                    </div>
                    
                    <div class="naval-egt-admin-form-row">
                        <div class="naval-egt-form-group">
                            <label for="edit_status">Status</label>
                            <select id="edit_status" name="status">
                                <option value="active">Attivo</option>
                                <option value="pending">In attesa</option>
                                <option value="suspended">Sospeso</option>
                            </select>
                        </div>
                        <div class="naval-egt-form-group">
                            <label for="edit_ftp_folder">Cartella FTP</label>
                            <input type="text" id="edit_ftp_folder" name="ftp_folder" readonly>
                        </div>
                    </div>
                </form>
                
                <div class="naval-egt-admin-modal-footer">
                    <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-secondary naval-egt-admin-modal-close">
                        Annulla
                    </button>
                    <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-primary" onclick="saveUserChanges()">
                        Salva Modifiche
                    </button>
                </div>
            </div>
        </div>

        <!-- Add User Modal -->
        <div id="add-user-modal" class="naval-egt-admin-modal" style="display: none;">
            <div class="naval-egt-admin-modal-content">
                <div class="naval-egt-admin-modal-header">
                    <h3>Aggiungi Nuovo Utente</h3>
                    <button class="naval-egt-admin-modal-close">&times;</button>
                </div>
                
                <form id="add-user-form">
                    <div class="naval-egt-admin-form-row">
                        <div class="naval-egt-form-group">
                            <label for="first_name">Nome *</label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>
                        <div class="naval-egt-form-group">
                            <label for="last_name">Cognome *</label>
                            <input type="text" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="naval-egt-admin-form-row">
                        <div class="naval-egt-form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="naval-egt-form-group">
                            <label for="company">Azienda *</label>
                            <input type="text" id="company" name="company" required>
                        </div>
                    </div>
                    
                    <div class="naval-egt-admin-form-row">
                        <div class="naval-egt-form-group">
                            <label for="username">Username (opzionale)</label>
                            <input type="text" id="username" name="username">
                        </div>
                        <div class="naval-egt-form-group">
                            <label for="phone">Telefono</label>
                            <input type="tel" id="phone" name="phone">
                        </div>
                    </div>
                    
                    <div class="naval-egt-form-group">
                        <label for="password">Password (opzionale)</label>
                        <input type="password" id="password" name="password">
                        <small>Se vuota, sarà generata automaticamente</small>
                    </div>
                    
                    <div class="naval-egt-form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active">Attivo</option>
                            <option value="pending">In attesa</option>
                        </select>
                    </div>
                </form>
                
                <div class="naval-egt-admin-modal-footer">
                    <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-secondary naval-egt-admin-modal-close">
                        Annulla
                    </button>
                    <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-primary" onclick="addNewUser()">
                        Crea Utente
                    </button>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Initialize tabs
            initTabs();
            
            // Load users on page load
            loadUsersTable();
            
            // Bind events
            $('.btn-add-user').click(function() {
                $('#add-user-modal').show();
            });
            
            $('.btn-refresh-users').click(function() {
                loadUsersTable();
            });
            
            // Search and filters
            $('#user-search').on('input', debounce(function() {
                loadUsersTable();
            }, 500));
            
            $('#status-filter').change(function() {
                loadUsersTable();
            });
            
            // Modal close events
            $('.naval-egt-admin-modal-close').click(function() {
                $(this).closest('.naval-egt-admin-modal').hide();
            });
            
            // File upload
            setupFileUpload();
            
            // Export buttons
            $('.btn-export-users').click(function() {
                window.location.href = naval_egt_admin.ajax_url + '?action=naval_egt_export_users&nonce=' + naval_egt_admin.nonce;
            });
        });

        function initTabs() {
            $('.naval-egt-admin-tab-nav button').click(function() {
                const tab = $(this).data('tab');
                
                // Update nav
                $('.naval-egt-admin-tab-nav button').removeClass('active');
                $(this).addClass('active');
                
                // Update content
                $('.naval-egt-admin-tab-pane').removeClass('active');
                $('#' + tab).addClass('active');
                
                // Load tab-specific data
                if (tab === 'users') {
                    loadUsersTable();
                } else if (tab === 'logs') {
                    loadLogsTable();
                }
            });
        }

        function switchToUsersTab() {
            $('.naval-egt-admin-tab-nav button[data-tab="users"]').click();
        }

        function loadUsersTable() {
            const search = $('#user-search').val();
            const status = $('#status-filter').val();
            
            $('#users-table-container').html('<div class="naval-egt-admin-loading"><div class="naval-egt-admin-loading-spinner"></div><p>Caricamento utenti...</p></div>');
            
            jQuery.ajax({
                url: naval_egt_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'naval_egt_admin_list_users',
                    nonce: naval_egt_admin.nonce,
                    search: search,
                    status: status,
                    per_page: 20
                },
                success: function(response) {
                    if (response.success) {
                        renderUsersTable(response.data.users);
                    } else {
                        $('#users-table-container').html('<div class="naval-egt-admin-empty">Errore durante il caricamento</div>');
                    }
                },
                error: function() {
                    $('#users-table-container').html('<div class="naval-egt-admin-empty">Errore di connessione</div>');
                }
            });
        }

        function renderUsersTable(users) {
            let html = '<table class="naval-egt-admin-table"><thead><tr>';
            html += '<th>Nome</th><th>Email</th><th>Azienda</th><th>Status</th><th>Registrato</th><th>Azioni</th>';
            html += '</tr></thead><tbody>';
            
            if (users.length === 0) {
                html += '<tr><td colspan="6" class="naval-egt-admin-empty">Nessun utente trovato</td></tr>';
            } else {
                users.forEach(user => {
                    const statusClass = user.status === 'active' ? 'naval-egt-status-active' : 
                                       (user.status === 'suspended' ? 'naval-egt-status-suspended' : 'naval-egt-status-pending');
                    const statusText = user.status === 'active' ? 'Attivo' : 
                                      (user.status === 'suspended' ? 'Sospeso' : 'In attesa');
                    
                    html += `<tr>
                        <td>${user.display_name}</td>
                        <td>${user.email}</td>
                        <td>${user.company || '-'}</td>
                        <td><span class="naval-egt-status-badge ${statusClass}">${statusText}</span></td>
                        <td>${formatDate(user.registered)}</td>
                        <td class="naval-egt-actions">
                            <button class="naval-egt-btn-sm naval-egt-btn-edit" onclick="editUser(${user.id})">Modifica</button>
                            <button class="naval-egt-btn-sm ${user.status === 'active' ? 'naval-egt-btn-suspend' : 'naval-egt-btn-activate'}" onclick="toggleUserStatus(${user.id}, '${user.status}')">
                                ${user.status === 'active' ? 'Sospendi' : 'Attiva'}
                            </button>
                            <button class="naval-egt-btn-sm naval-egt-btn-delete" onclick="deleteUser(${user.id})">Elimina</button>
                        </td>
                    </tr>`;
                });
            }
            
            html += '</tbody></table>';
            $('#users-table-container').html(html);
        }

        function addNewUser() {
            const formData = $('#add-user-form').serialize();
            
            jQuery.ajax({
                url: naval_egt_admin.ajax_url,
                type: 'POST',
                data: formData + '&action=naval_egt_add_user&nonce=' + naval_egt_admin.nonce,
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message, 'success');
                        $('#add-user-modal').hide();
                        $('#add-user-form')[0].reset();
                        loadUsersTable();
                    } else {
                        showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    showNotice('Errore durante la creazione', 'error');
                }
            });
        }

        function editUser(userId) {
            jQuery.ajax({
                url: naval_egt_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'naval_egt_get_user_data',
                    nonce: naval_egt_admin.nonce,
                    user_id: userId
                },
                success: function(response) {
                    if (response.success) {
                        const user = response.data;
                        $('#edit_user_id').val(user.id);
                        $('#edit_first_name').val(user.first_name);
                        $('#edit_last_name').val(user.last_name);
                        $('#edit_email').val(user.email);
                        $('#edit_company').val(user.company);
                        $('#edit_phone').val(user.phone);
                        $('#edit_status').val(user.status);
                        $('#edit_ftp_folder').val(user.ftp_folder);
                        
                        $('#edit-user-modal').show();
                    } else {
                        showNotice('Errore durante il caricamento dei dati utente', 'error');
                    }
                }
            });
        }

        function saveUserChanges() {
            const formData = $('#edit-user-form').serialize();
            
            jQuery.ajax({
                url: naval_egt_admin.ajax_url,
                type: 'POST',
                data: formData + '&action=naval_egt_update_user&nonce=' + naval_egt_admin.nonce,
                success: function(response) {
                    if (response.success) {
                        showNotice('Utente aggiornato con successo', 'success');
                        $('#edit-user-modal').hide();
                        loadUsersTable();
                    } else {
                        showNotice(response.data.message || 'Errore durante l\'aggiornamento', 'error');
                    }
                }
            });
        }

        function toggleUserStatus(userId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'suspended' : 'active';
            const action = newStatus === 'suspended' ? 'sospendere' : 'attivare';
            
            if (!confirm(`Sei sicuro di voler ${action} questo utente?`)) {
                return;
            }
            
            jQuery.ajax({
                url: naval_egt_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'naval_egt_admin_update_user_status',
                    nonce: naval_egt_admin.nonce,
                    user_id: userId,
                    status: newStatus
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('Status utente aggiornato', 'success');
                        loadUsersTable();
                    } else {
                        showNotice('Errore durante l\'aggiornamento', 'error');
                    }
                }
            });
        }

        function deleteUser(userId) {
            if (!confirm('Sei sicuro di voler eliminare questo utente? Questa azione eliminerà anche tutti i suoi file e non può essere annullata.')) {
                return;
            }
            
            jQuery.ajax({
                url: naval_egt_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'naval_egt_admin_delete_user',
                    nonce: naval_egt_admin.nonce,
                    user_id: userId
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('Utente eliminato con successo', 'success');
                        loadUsersTable();
                    } else {
                        showNotice(response.data.message || 'Errore durante l\'eliminazione', 'error');
                    }
                }
            });
        }

        function loadLogsTable() {
            $('#logs-table-container').html('<div class="naval-egt-admin-loading"><div class="naval-egt-admin-loading-spinner"></div><p>Caricamento log...</p></div>');
            
            jQuery.ajax({
                url: naval_egt_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'naval_egt_admin_get_logs',
                    nonce: naval_egt_admin.nonce,
                    per_page: 50
                },
                success: function(response) {
                    if (response.success) {
                        renderLogsTable(response.data.logs);
                    }
                }
            });
        }

        function renderLogsTable(logs) {
            let html = '<table class="naval-egt-admin-table"><thead><tr>';
            html += '<th>Data/Ora</th><th>Utente</th><th>Azione</th><th>File</th><th>Dimensione</th>';
            html += '</tr></thead><tbody>';
            
            if (logs.length === 0) {
                html += '<tr><td colspan="5" class="naval-egt-admin-empty">Nessun log trovato</td></tr>';
            } else {
                logs.forEach(log => {
                    html += `<tr>
                        <td>${formatDateTime(log.created_at)}</td>
                        <td>${log.display_name || 'Utente eliminato'}<br><small>${log.user_email || ''}</small></td>
                        <td><span class="action-badge action-${log.action}">${getActionText(log.action)}</span></td>
                        <td>${log.file_name || '-'}</td>
                        <td>${log.file_size ? formatFileSize(log.file_size) : '-'}</td>
                    </tr>`;
                });
            }
            
            html += '</tbody></table>';
            $('#logs-table-container').html(html);
        }

        function setupFileUpload() {
            $('#admin-bulk-file-upload').change(function() {
                const files = this.files;
                if (files.length > 0) {
                    let fileNames = [];
                    for (let i = 0; i < files.length; i++) {
                        fileNames.push(files[i].name);
                    }
                    $('.naval-egt-admin-file-upload-text').text(fileNames.join(', '));
                }
            });
            
            $('#btn-admin-upload').click(function() {
                const userId = $('#select-user-for-upload').val();
                const folder = $('#select-folder-for-upload').val();
                const files = $('#admin-bulk-file-upload')[0].files;
                
                if (!userId) {
                    showNotice('Seleziona un utente', 'warning');
                    return;
                }
                
                if (files.length === 0) {
                    showNotice('Seleziona almeno un file', 'warning');
                    return;
                }
                
                const formData = new FormData();
                formData.append('action', 'naval_egt_admin_upload_file_for_user');
                formData.append('nonce', naval_egt_admin.nonce);
                formData.append('user_id', userId);
                formData.append('subfolder', folder);
                
                for (let i = 0; i < files.length; i++) {
                    formData.append('files[]', files[i]);
                }
                
                $.ajax({
                    url: naval_egt_admin.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            showNotice(response.data.message, 'success');
                            $('#admin-bulk-file-upload').val('');
                            $('.naval-egt-admin-file-upload-text').text('Clicca per selezionare i file o trascinali qui');
                        } else {
                            showNotice(response.data.message, 'error');
                        }
                    },
                    error: function() {
                        showNotice('Errore durante il caricamento', 'error');
                    }
                });
            });
        }

        // Utility functions
        function showNotice(message, type) {
            const notice = $(`<div class="naval-egt-admin-notice naval-egt-admin-notice-${type}"><p>${message}</p></div>`);
            $('.naval-egt-admin-wrap').prepend(notice);
            
            setTimeout(function() {
                notice.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 5000);
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('it-IT');
        }

        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('it-IT') + ' ' + 
                   date.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function getActionText(action) {
            const actions = {
                'upload': 'Caricamento',
                'download': 'Scaricamento',
                'delete': 'Eliminazione',
                'admin_upload': 'Caricamento Admin'
            };
            return actions[action] || action;
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        </script>

        <style>
        .naval-egt-admin-wrap {
            margin: 20px 20px 0 2px;
        }

        .naval-egt-admin-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .naval-egt-admin-header h1 {
            margin: 0;
            font-size: 2em;
            font-weight: 600;
        }

        .naval-egt-admin-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 1.1em;
        }

        .naval-egt-admin-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .naval-egt-admin-card {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: box-shadow 0.3s ease;
        }

        .naval-egt-admin-card:hover {
            box-shadow: 0 3px 10px rgba(0,0,0,0.15);
        }

        .naval-egt-admin-card h3 {
            margin: 0 0 15px 0;
            color: #2a5298;
            font-size: 1.3em;
            font-weight: 600;
            border-bottom: 2px solid #f0f0f1;
            padding-bottom: 10px;
        }

        .naval-egt-admin-card .naval-egt-stat-number {
            font-size: 2.5em;
            font-weight: 700;
            color: #1e3c72;
            text-align: center;
            margin: 15px 0;
        }

        .naval-egt-admin-card .naval-egt-stat-label {
            text-align: center;
            color: #666;
            font-size: 0.95em;
            margin-bottom: 15px;
        }

        .naval-egt-admin-tabs {
            margin-bottom: 20px;
        }

        .naval-egt-admin-tab-nav {
            display: flex;
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 8px 8px 0 0;
            overflow: hidden;
        }

        .naval-egt-admin-tab-nav button {
            background: none;
            border: none;
            padding: 15px 20px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            border-right: 1px solid #ccd0d4;
            transition: all 0.3s ease;
        }

        .naval-egt-admin-tab-nav button:last-child {
            border-right: none;
        }

        .naval-egt-admin-tab-nav button:hover {
            background: #f9f9f9;
            color: #2a5298;
        }

        .naval-egt-admin-tab-nav button.active {
            background: #2a5298;
            color: white;
        }

        .naval-egt-admin-tab-content {
            background: white;
            border: 1px solid #ccd0d4;
            border-top: none;
            border-radius: 0 0 8px 8px;
            padding: 20px;
        }

        .naval-egt-admin-tab-pane {
            display: none;
        }

        .naval-egt-admin-tab-pane.active {
            display: block;
        }

        .naval-egt-admin-table-container {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .naval-egt-admin-table-header {
            background: #f9f9f9;
            padding: 15px 20px;
            border-bottom: 1px solid #ccd0d4;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .naval-egt-admin-table-header h3 {
            margin: 0;
            color: #2a5298;
            font-size: 1.2em;
            font-weight: 600;
        }

        .naval-egt-admin-table-filters {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .naval-egt-admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .naval-egt-admin-table th,
        .naval-egt-admin-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f1;
        }

        .naval-egt-admin-table th {
            background: #f9f9f9;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #ccd0d4;
        }

        .naval-egt-admin-table tr:hover {
            background: #f9f9f9;
        }

        .naval-egt-status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .naval-egt-status-active {
            background: #d4edda;
            color: #155724;
        }

        .naval-egt-status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .naval-egt-status-suspended {
            background: #f8d7da;
            color: #721c24;
        }

        .naval-egt-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .naval-egt-btn-sm {
            padding: 4px 8px;
            font-size: 12px;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            font-weight: 500;
        }

        .naval-egt-btn-edit {
            background: #17a2b8;
            color: white;
        }

        .naval-egt-btn-edit:hover {
            background: #138496;
        }

        .naval-egt-btn-delete {
            background: #dc3545;
            color: white;
        }

        .naval-egt-btn-delete:hover {
            background: #c82333;
        }

        .naval-egt-btn-activate {
            background: #28a745;
            color: white;
        }

        .naval-egt-btn-activate:hover {
            background: #218838;
        }

        .naval-egt-btn-suspend {
            background: #ffc107;
            color: #212529;
        }

        .naval-egt-btn-suspend:hover {
            background: #e0a800;
        }

        .naval-egt-admin-btn {
            display: inline-block;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            line-height: 1.4;
        }

        .naval-egt-admin-btn-primary {
            background: #2a5298;
            color: white;
        }

        .naval-egt-admin-btn-primary:hover {
            background: #1e3c72;
        }

        .naval-egt-admin-btn-secondary {
            background: #6c757d;
            color: white;
        }

        .naval-egt-admin-btn-secondary:hover {
            background: #5a6268;
        }

        .naval-egt-admin-btn-success {
            background: #28a745;
            color: white;
        }

        .naval-egt-admin-btn-success:hover {
            background: #218838;
        }

        .naval-egt-admin-btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .naval-egt-admin-btn-warning:hover {
            background: #e0a800;
        }

        .naval-egt-admin-modal {
            display: none;
            position: fixed;
            z-index: 100000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .naval-egt-admin-modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 25px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            position: relative;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        }

        .naval-egt-admin-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f1;
        }

        .naval-egt-admin-modal-header h3 {
            margin: 0;
            color: #2a5298;
            font-size: 1.4em;
            font-weight: 600;
        }

        .naval-egt-admin-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .naval-egt-admin-modal-close:hover {
            background: #f0f0f1;
            color: #333;
        }

        .naval-egt-admin-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f1;
        }

        .naval-egt-admin-form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .naval-egt-admin-form-row .naval-egt-form-group {
            flex: 1;
        }

        .naval-egt-form-group {
            margin-bottom: 20px;
        }

        .naval-egt-form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #333;
        }

        .naval-egt-form-group input,
        .naval-egt-form-group select,
        .naval-egt-form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .naval-egt-form-group input:focus,
        .naval-egt-form-group select:focus,
        .naval-egt-form-group textarea:focus {
            outline: none;
            border-color: #2a5298;
            box-shadow: 0 0 0 2px rgba(42, 82, 152, 0.1);
        }

        .naval-egt-admin-form {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .naval-egt-admin-form h3 {
            margin: 0 0 20px 0;
            color: #2a5298;
            font-size: 1.3em;
            font-weight: 600;
            border-bottom: 2px solid #f0f0f1;
            padding-bottom: 10px;
        }

        .naval-egt-admin-file-upload {
            border: 2px dashed #ccd0d4;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            background: #f9f9f9;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .naval-egt-admin-file-upload:hover {
            border-color: #2a5298;
            background: #f0f4f8;
        }

        .naval-egt-admin-file-upload-icon {
            font-size: 3em;
            color: #ccd0d4;
            margin-bottom: 15px;
        }

        .naval-egt-admin-file-upload-text {
            color: #666;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .naval-egt-admin-file-upload-subtext {
            color: #999;
            font-size: 14px;
        }

        .naval-egt-admin-settings-section {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .naval-egt-admin-settings-header {
            background: #f9f9f9;
            padding: 15px 20px;
            border-bottom: 1px solid #ccd0d4;
        }

        .naval-egt-admin-settings-header h3 {
            margin: 0;
            color: #2a5298;
            font-size: 1.2em;
            font-weight: 600;
        }

        .naval-egt-admin-settings-content {
            padding: 20px;
        }

        .naval-egt-admin-settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .naval-egt-admin-loading {
            text-align: center;
            padding: 40px;
            color: #2a5298;
        }

        .naval-egt-admin-loading-spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid rgba(42, 82, 152, 0.3);
            border-top: 4px solid #2a5298;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .naval-egt-admin-empty {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }

        .naval-egt-admin-notice {
            padding: 12px 15px;
            margin: 15px 0;
            border-left: 4px solid;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 0 4px 4px 0;
        }

        .naval-egt-admin-notice-success {
            border-color: #28a745;
            background: #d4edda;
            color: #155724;
        }

        .naval-egt-admin-notice-error {
            border-color: #dc3545;
            background: #f8d7da;
            color: #721c24;
        }

        .naval-egt-admin-notice-warning {
            border-color: #ffc107;
            background: #fff3cd;
            color: #856404;
        }

        .naval-egt-admin-notice p {
            margin: 0;
            font-weight: 500;
        }

        .naval-egt-recent-activity {
            max-height: 400px;
            overflow-y: auto;
        }

        .naval-egt-activity-item {
            display: flex;
            align-items: flex-start;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f1;
        }

        .naval-egt-activity-item:last-child {
            border-bottom: none;
        }

        .naval-egt-activity-icon {
            font-size: 1.2em;
            margin-right: 12px;
            margin-top: 2px;
        }

        .naval-egt-activity-details {
            flex: 1;
        }

        .naval-egt-activity-user {
            margin-bottom: 4px;
        }

        .naval-egt-activity-action {
            margin-bottom: 4px;
            color: #666;
        }

        .naval-egt-activity-time {
            font-size: 0.9em;
            color: #999;
        }

        .naval-egt-admin-flex {
            display: flex;
        }

        .naval-egt-admin-flex-gap-10 {
            gap: 10px;
        }

        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.9em;
        }

        .action-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .action-upload {
            background: #d4edda;
            color: #155724;
        }

        .action-download {
            background: #d1ecf1;
            color: #0c5460;
        }

        .action-delete {
            background: #f8d7da;
            color: #721c24;
        }

        .action-admin_upload {
            background: #e2e3e5;
            color: #383d41;
        }

        @media (max-width: 782px) {
            .naval-egt-admin-wrap {
                margin: 10px 10px 0 0;
            }
            
            .naval-egt-admin-header {
                padding: 20px 15px;
            }
            
            .naval-egt-admin-header h1 {
                font-size: 1.6em;
            }
            
            .naval-egt-admin-cards {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .naval-egt-admin-table-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .naval-egt-admin-table-filters {
                justify-content: stretch;
            }
            
            .naval-egt-admin-table-filters select,
            .naval-egt-admin-table-filters input {
                flex: 1;
            }
            
            .naval-egt-admin-table {
                font-size: 12px;
            }
            
            .naval-egt-admin-table th,
            .naval-egt-admin-table td {
                padding: 8px 10px;
            }
            
            .naval-egt-admin-tab-nav {
                flex-direction: column;
            }
            
            .naval-egt-admin-tab-nav button {
                border-right: none;
                border-bottom: 1px solid #ccd0d4;
            }
            
            .naval-egt-admin-form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .naval-egt-admin-modal-content {
                width: 95%;
                margin: 10% auto;
                padding: 20px;
            }
            
            .naval-egt-admin-settings-grid {
                grid-template-columns: 1fr;
            }
        }
        </style>
        
        <?php
    }
    
    public function settings_page() {
        echo '<div class="wrap"><h1>Impostazioni Naval EGT</h1><p>Pagina delle impostazioni in sviluppo.</p></div>';
    }
    
    // AJAX METHODS
    
    public function ajax_list_users() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_admin')) {
            wp_send_json_error(array('message' => 'Errore di sicurezza'));
        }
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? '');
        $per_page = intval($_POST['per_page'] ?? 20);
        
        $args = array(
            'role' => 'naval_egt_client',
            'number' => $per_page,
            'orderby' => 'registered',
            'order' => 'DESC'
        );
        
        if (!empty($search)) {
            $args['search'] = '*' . $search . '*';
        }
        
        $users = get_users($args);
        $users_data = array();
        
        foreach ($users as $user) {
            $user_status = get_user_meta($user->ID, 'naval_egt_status', true) ?: 'active';
            
            // Filter by status if specified
            if (!empty($status) && $user_status !== $status) {
                continue;
            }
            
            $users_data[] = array(
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'company' => get_user_meta($user->ID, 'naval_egt_company', true),
                'phone' => get_user_meta($user->ID, 'naval_egt_phone', true),
                'status' => $user_status,
                'registered' => $user->user_registered,
                'ftp_folder' => get_user_meta($user->ID, 'naval_egt_ftp_folder', true)
            );
        }
        
        wp_send_json_success(array('users' => $users_data));
    }
    
    public function ajax_add_user() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_admin')) {
            wp_send_json_error(array('message' => 'Errore di sicurezza'));
        }
        
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $email = sanitize_email($_POST['email']);
        $company = sanitize_text_field($_POST['company']);
        $phone = sanitize_text_field($_POST['phone']);
        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password'];
        $status = sanitize_text_field($_POST['status']) ?: 'active';
        
        // Validation
        if (empty($first_name) || empty($last_name) || empty($email) || empty($company)) {
            wp_send_json_error(array('message' => 'Tutti i campi obbligatori devono essere compilati'));
        }
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Inserisci un indirizzo email valido'));
        }
        
        if (email_exists($email)) {
            wp_send_json_error(array('message' => 'Un account con questa email esiste già'));
        }
        
        // Generate username if not provided
        if (empty($username)) {
            $username = $this->generate_username($email);
        } else {
            if (username_exists($username)) {
                wp_send_json_error(array('message' => 'Username già in uso'));
            }
        }
        
        // Generate password if not provided
        if (empty($password)) {
            $password = wp_generate_password(12, false);
        }
        
        if (strlen($password) < 6) {
            wp_send_json_error(array('message' => 'La password deve essere di almeno 6 caratteri'));
        }
        
        // Create user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => 'Errore durante la creazione utente'));
        }
        
        // Set role
        $user = new WP_User($user_id);
        $user->set_role('naval_egt_client');
        
        // Save additional data
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        update_user_meta($user_id, 'naval_egt_company', $company);
        update_user_meta($user_id, 'naval_egt_phone', $phone);
        update_user_meta($user_id, 'naval_egt_status', $status);
        
        // Update display name
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $first_name . ' ' . $last_name
        ));
        
        // Create FTP folder
        $folder_name = $this->create_user_ftp_folder($user_id);
        
        // Send welcome email
        $this->send_welcome_email($user_id, $password);
        
        // Log activity
        $this->log_file_action($user_id, 'user_created', 'Utente creato', '', 0);
        
        wp_send_json_success(array(
            'message' => 'Utente creato con successo',
            'user_id' => $user_id,
            'username' => $username
        ));
    }
    
    public function ajax_get_user_data() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_admin')) {
            wp_send_json_error(array('message' => 'Errore di sicurezza'));
        }
        
        $user_id = intval($_POST['user_id']);
        $user = get_user_by('id', $user_id);
        
        if (!$user || !in_array('naval_egt_client', $user->roles)) {
            wp_send_json_error(array('message' => 'Utente non trovato'));
        }
        
        $user_data = array(
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'first_name' => get_user_meta($user->ID, 'first_name', true),
            'last_name' => get_user_meta($user->ID, 'last_name', true),
            'company' => get_user_meta($user->ID, 'naval_egt_company', true),
            'phone' => get_user_meta($user->ID, 'naval_egt_phone', true),
            'status' => get_user_meta($user->ID, 'naval_egt_status', true) ?: 'active',
            'ftp_folder' => get_user_meta($user->ID, 'naval_egt_ftp_folder', true)
        );
        
        wp_send_json_success($user_data);
    }
    
    public function ajax_update_user() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_admin')) {
            wp_send_json_error(array('message' => 'Errore di sicurezza'));
        }
        
        $user_id = intval($_POST['user_id']);
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            wp_send_json_error(array('message' => 'Utente non trovato'));
        }
        
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $email = sanitize_email($_POST['email']);
        $company = sanitize_text_field($_POST['company']);
        $phone = sanitize_text_field($_POST['phone']);
        $status = sanitize_text_field($_POST['status']);
        
        // Check if email is already in use by another user
        if ($email !== $user->user_email) {
            $existing_user = get_user_by('email', $email);
            if ($existing_user && $existing_user->ID !== $user_id) {
                wp_send_json_error(array('message' => 'Email già in uso da un altro utente'));
            }
        }
        
        // Update user
        wp_update_user(array(
            'ID' => $user_id,
            'user_email' => $email,
            'display_name' => $first_name . ' ' . $last_name
        ));
        
        // Update meta
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        update_user_meta($user_id, 'naval_egt_company', $company);
        update_user_meta($user_id, 'naval_egt_phone', $phone);
        update_user_meta($user_id, 'naval_egt_status', $status);
        
        wp_send_json_success(array('message' => 'Utente aggiornato con successo'));
    }
    
    public function ajax_update_user_status() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_admin')) {
            wp_send_json_error(array('message' => 'Errore di sicurezza'));
        }
        
        $user_id = intval($_POST['user_id']);
        $status = sanitize_text_field($_POST['status']);
        
        $allowed_statuses = array('active', 'pending', 'suspended');
        if (!in_array($status, $allowed_statuses)) {
            wp_send_json_error(array('message' => 'Status non valido'));
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user || !in_array('naval_egt_client', $user->roles)) {
            wp_send_json_error(array('message' => 'Utente non trovato'));
        }
        
        update_user_meta($user_id, 'naval_egt_status', $status);
        
        wp_send_json_success(array('message' => 'Status utente aggiornato'));
    }
    
    public function ajax_delete_user() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_admin')) {
            wp_send_json_error(array('message' => 'Errore di sicurezza'));
        }
        
        $user_id = intval($_POST['user_id']);
        $user = get_user_by('id', $user_id);
        
        if (!$user || !in_array('naval_egt_client', $user->roles)) {
            wp_send_json_error(array('message' => 'Utente non trovato'));
        }
        
        // Delete user's FTP folder
        $ftp_folder = get_user_meta($user_id, 'naval_egt_ftp_folder', true);
        if ($ftp_folder) {
            $upload_dir = wp_upload_dir();
            $folder_path = $upload_dir['basedir'] . '/naval-egt-ftp/' . $ftp_folder;
            if (is_dir($folder_path)) {
                $this->delete_directory_recursive($folder_path);
            }
        }
        
        // Delete user records from custom tables
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'naval_egt_ftp_logs', array('user_id' => $user_id));
        
        // Delete WordPress user
        if (wp_delete_user($user_id)) {
            wp_send_json_success(array('message' => 'Utente eliminato con successo'));
        } else {
            wp_send_json_error(array('message' => 'Errore durante l\'eliminazione dell\'utente'));
        }
    }
    
    public function ajax_upload_file_for_user() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_admin')) {
            wp_send_json_error(array('message' => 'Errore di sicurezza'));
        }
        
        $user_id = intval($_POST['user_id']);
        $subfolder = sanitize_text_field($_POST['subfolder']) ?: 'downloads/';
        
        if (empty($user_id) || empty($_FILES['files'])) {
            wp_send_json_error(array('message' => 'Parametri mancanti'));
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user || !in_array('naval_egt_client', $user->roles)) {
            wp_send_json_error(array('message' => 'Utente non trovato'));
        }
        
        // Get or create user FTP folder
        $folder_name = get_user_meta($user_id, 'naval_egt_ftp_folder', true);
        if (!$folder_name) {
            $folder_name = $this->create_user_ftp_folder($user_id);
        }
        
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/naval-egt-ftp/' . $folder_name . '/' . $subfolder;
        
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        
        $uploaded_files = array();
        $files = $_FILES['files'];
        
        if (!is_array($files['name'])) {
            $files = array(
                'name' => array($files['name']),
                'tmp_name' => array($files['tmp_name']),
                'error' => array($files['error']),
                'size' => array($files['size'])
            );
        }
        
        $file_count = count($files['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $file_name = sanitize_file_name($files['name'][$i]);
                $file_tmp = $files['tmp_name'][$i];
                $file_size = $files['size'][$i];
                
                // Validate file type
                $allowed_types = array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'dwg', 'dxf');
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                if (!in_array($file_ext, $allowed_types)) {
                    continue; // Skip invalid files
                }
                
                // Make filename unique
                $target_file = $target_dir . $file_name;
                $counter = 1;
                $original_name = pathinfo($file_name, PATHINFO_FILENAME);
                $extension = pathinfo($file_name, PATHINFO_EXTENSION);
                
                while (file_exists($target_file)) {
                    $file_name = $original_name . '_' . $counter . '.' . $extension;
                    $target_file = $target_dir . $file_name;
                    $counter++;
                }
                
                if (move_uploaded_file($file_tmp, $target_file)) {
                    $uploaded_files[] = $file_name;
                    
                    // Log the upload
                    $this->log_file_action($user_id, 'admin_upload', $file_name, $subfolder . $file_name, $file_size);
                }
            }
        }
        
        if (!empty($uploaded_files)) {
            // Send notification email to user
            $this->notify_user_new_files($user, $uploaded_files);
            
            wp_send_json_success(array(
                'message' => 'File caricati con successo: ' . implode(', ', $uploaded_files)
            ));
        } else {
            wp_send_json_error(array('message' => 'Nessun file valido caricato'));
        }
    }
    
    public function ajax_get_logs() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_admin')) {
            wp_send_json_error(array('message' => 'Errore di sicurezza'));
        }
        
        global $wpdb;
        
        $per_page = intval($_POST['per_page'] ?? 50);
        $user_id = intval($_POST['user_id'] ?? 0);
        $action = sanitize_text_field($_POST['action_filter'] ?? '');
        
        $where_clauses = array();
        $where_values = array();
        
        if ($user_id > 0) {
            $where_clauses[] = "user_id = %d";
            $where_values[] = $user_id;
        }
        
        if (!empty($action)) {
            $where_clauses[] = "action = %s";
            $where_values[] = $action;
        }
        
        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        $table_name = $wpdb->prefix . 'naval_egt_ftp_logs';
        
        // Get logs
        $sql = "SELECT l.*, u.display_name, u.user_email 
                FROM $table_name l 
                LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
                $where_sql 
                ORDER BY l.created_at DESC 
                LIMIT %d";
        
        $query_values = array_merge($where_values, array($per_page));
        $logs = $wpdb->get_results($wpdb->prepare($sql, $query_values));
        
        wp_send_json_success(array('logs' => $logs));
    }
    
    public function ajax_send_notification() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        // Implementation for sending notifications
        wp_send_json_success(array('message' => 'Funzionalità in sviluppo'));
    }
    
    // Frontend AJAX Methods
    
    public function ajax_download_file() {
        if (!wp_verify_nonce($_GET['nonce'], 'naval_egt_nonce')) {
            wp_die('Errore di sicurezza');
        }
        
        if (!is_user_logged_in() || !naval_egt_user_can_access()) {
            wp_die('Accesso negato');
        }
        
        $file_path = sanitize_text_field($_GET['file']);
        $user_id = get_current_user_id();
        
        $folder_name = get_user_meta($user_id, 'naval_egt_ftp_folder', true);
        if (!$folder_name) {
            wp_die('Cartella non trovata');
        }
        
        $upload_dir = wp_upload_dir();
        $full_path = $upload_dir['basedir'] . '/naval-egt-ftp/' . $folder_name . '/' . $file_path;
        
        if (!file_exists($full_path) || !is_file($full_path)) {
            wp_die('File non trovato');
        }
        
        // Log download
        $this->log_file_action($user_id, 'download', basename($file_path), $file_path, filesize($full_path));
        
        // Force download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($full_path) . '"');
        header('Content-Length: ' . filesize($full_path));
        readfile($full_path);
        exit;
    }
    
    public function ajax_refresh_user_files() {
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_nonce')) {
            wp_send_json_error(array('message' => 'Errore di sicurezza'));
        }
        
        if (!is_user_logged_in() || !naval_egt_user_can_access()) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        $user_id = get_current_user_id();
        $files = $this->get_user_files($user_id);
        
        wp_send_json_success(array('files' => $files));
    }
    
    // Export Methods
    
    public function export_users() {
        if (!current_user_can('manage_options')) {
            wp_die('Accesso negato');
        }
        
        if (!wp_verify_nonce($_GET['nonce'], 'naval_egt_admin')) {
            wp_die('Errore di sicurezza');
        }
        
        $users = get_users(array('role' => 'naval_egt_client'));
        
        $filename = 'naval-egt-users-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, array('ID', 'Nome', 'Email', 'Azienda', 'Telefono', 'Status', 'Registrato'));
        
        foreach ($users as $user) {
            $status = get_user_meta($user->ID, 'naval_egt_status', true) ?: 'active';
            $company = get_user_meta($user->ID, 'naval_egt_company', true);
            $phone = get_user_meta($user->ID, 'naval_egt_phone', true);
            
            fputcsv($output, array(
                $user->ID,
                $user->display_name,
                $user->user_email,
                $company,
                $phone,
                $status,
                $user->user_registered
            ));
        }
        
        fclose($output);
        exit;
    }
    
    public function export_logs() {
        if (!current_user_can('manage_options')) {
            wp_die('Accesso negato');
        }
        
        if (!wp_verify_nonce($_GET['nonce'], 'naval_egt_admin')) {
            wp_die('Errore di sicurezza');
        }
        
        global $wpdb;
        
        $logs = $wpdb->get_results("
            SELECT l.*, u.display_name, u.user_email 
            FROM {$wpdb->prefix}naval_egt_ftp_logs l 
            LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
            ORDER BY l.created_at DESC 
            LIMIT 1000
        ");
        
        $filename = 'naval-egt-logs-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, array('Data', 'Utente', 'Email', 'Azione', 'File', 'Dimensione', 'IP'));
        
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log->created_at,
                $log->display_name ?: 'Utente eliminato',
                $log->user_email ?: '',
                $log->action,
                $log->file_name ?: '',
                $log->file_size ?: '',
                $log->ip_address ?: ''
            ));
        }
        
        fclose($output);
        exit;
    }
    
    // SHORTCODES
    
    public function login_form_shortcode($atts) {
        if (is_user_logged_in() && naval_egt_user_can_access()) {
            wp_redirect(home_url('/dashboard-clienti/'));
            exit;
        }
        
        ob_start();
        $this->display_notices();
        ?>
        
        <div class="naval-egt-area-riservata">
            <div class="naval-egt-header">
                <h2>🔐 AREA RISERVATA NAVAL EGT</h2>
                <p>Accedi con le tue credenziali per utilizzare l'area riservata dedicata ai clienti Naval EGT</p>
            </div>
            
            <div style="max-width: 500px; margin: 0 auto;">
                <div style="background: white; padding: 40px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
                    <h3 style="text-align: center; color: #2a5298; margin-bottom: 30px;">🚪 Accedi al tuo account</h3>
                    
                    <form method="post" action="<?php echo esc_url(add_query_arg('naval_action', 'login')); ?>">
                        <?php wp_nonce_field('naval_egt_login', 'naval_egt_nonce'); ?>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px;">Email o Username</label>
                            <input type="text" name="username" required 
                                   style="width: 100%; padding: 15px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px;" />
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px;">Password</label>
                            <input type="password" name="password" required 
                                   style="width: 100%; padding: 15px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px;" />
                        </div>
                        
                        <div style="margin-bottom: 25px;">
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" name="remember" style="margin-right: 10px;" />
                                Ricordami per i prossimi accessi
                            </label>
                        </div>
                        
                        <button type="submit" style="width: 100%; padding: 18px; background: linear-gradient(135deg, #2a5298, #1e3c72); color: white; border: none; border-radius: 10px; font-size: 18px; font-weight: 600; cursor: pointer; transition: transform 0.2s;">
                            🔑 Accedi all'Area Riservata
                        </button>
                    </form>
                    
                    <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                        <p style="margin: 0; color: #666; font-size: 14px;">
                            <strong>🔒 Account sicuro:</strong> Non hai un account? Contatta il nostro staff per la registrazione
                        </p>
                    </div>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 40px; padding: 25px; background: #f8f9fa; border-radius: 10px; border-left: 4px solid #2a5298;">
                <h4 style="margin: 0 0 10px 0; color: #2a5298;">🆘 Assistenza Tecnica</h4>
                <p style="margin: 5px 0;">
                    <strong>Email:</strong> <a href="mailto:technical@navalegt.it" style="color: #2a5298;">technical@navalegt.it</a>
                </p>
                <p style="margin: 5px 0; font-size: 14px; color: #666;">
                    Per problemi di accesso, richieste di registrazione o supporto tecnico
                </p>
            </div>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    public function dashboard_shortcode($atts) {
        if (!is_user_logged_in() || !naval_egt_user_can_access()) {
            return '<p>Devi effettuare il login. <a href="' . home_url('/area-riservata/') . '">Clicca qui</a></p>';
        }
        
        $user_data = naval_egt_get_user_data();
        $user_files = $this->get_user_files(get_current_user_id());
        
        ob_start();
        ?>
        
        <div class="naval-egt-dashboard">
            <div style="background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; padding: 30px; border-radius: 15px; margin-bottom: 30px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2 style="margin: 0; font-size: 2em;">👋 Benvenuto, <?php echo esc_html($user_data['display_name']); ?></h2>
                        <p style="margin: 10px 0 0 0; opacity: 0.9;">La tua area riservata Naval EGT</p>
                    </div>
                    <div>
                        <button onclick="refreshUserFiles()" style="background: rgba(255,255,255,0.2); color: white; padding: 8px 16px; border: none; border-radius: 6px; margin-right: 10px; cursor: pointer;">
                            🔄 Aggiorna
                        </button>
                        <a href="<?php echo esc_url(add_query_arg('naval_action', 'logout', home_url('/area-riservata/'))); ?>" 
                           style="background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none;">
                            🚪 Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                <!-- File Area -->
                <div>
                    <div style="background: white; padding: 25px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin-bottom: 20px;">
                        <h3 style="margin: 0 0 20px 0; color: #2a5298;">📁 I Tuoi File</h3>
                        
                        <div id="user-files-list">
                            <?php if (!empty($user_files)): ?>
                                <?php foreach ($user_files as $file): ?>
                                    <div style="display: flex; align-items: center; padding: 15px; border: 1px solid #e1e5e9; border-radius: 10px; margin-bottom: 10px;">
                                        <div style="font-size: 2em; margin-right: 15px;">📄</div>
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600;"><?php echo esc_html($file['name']); ?></div>
                                            <div style="color: #666; font-size: 0.9em;">
                                                <?php echo size_format($file['size']); ?> - 
                                                <?php echo date('d/m/Y H:i', $file['date']); ?>
                                            </div>
                                        </div>
                                        <a href="<?php echo esc_url($this->get_download_url($file['path'])); ?>" 
                                           style="background: #28a745; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none;">
                                            ⬇️ Scarica
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 40px; color: #666;">
                                    📭 Nessun file disponibile
                                    <p><small>I file condivisi da Naval EGT appariranno qui</small></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Sidebar -->
                <div>
                    <div style="background: white; padding: 25px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
                        <h3 style="margin: 0 0 20px 0; color: #2a5298;">👤 Il Tuo Profilo</h3>
                        
                        <div style="space-y: 10px;">
                            <p><strong>Nome:</strong> <?php echo esc_html($user_data['display_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo esc_html($user_data['email']); ?></p>
                            <?php if ($user_data['company']): ?>
                                <p><strong>Azienda:</strong> <?php echo esc_html($user_data['company']); ?></p>
                            <?php endif; ?>
                            <?php if ($user_data['phone']): ?>
                                <p><strong>Telefono:</strong> <?php echo esc_html($user_data['phone']); ?></p>
                            <?php endif; ?>
                            <p><strong>Registrato:</strong> <?php echo date_i18n('d/m/Y', strtotime($user_data['registered'])); ?></p>
                        </div>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 15px; margin-top: 20px; text-align: center;">
                        <h4 style="margin: 0 0 10px 0;">🆘 Assistenza</h4>
                        <p style="margin: 0;"><a href="mailto:technical@navalegt.it">technical@navalegt.it</a></p>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        function refreshUserFiles() {
            jQuery.ajax({
                url: naval_egt_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'naval_egt_refresh_user_files',
                    nonce: naval_egt_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                }
            });
        }
        </script>
        
        <?php
        return ob_get_clean();
    }
    
    // CUSTOM ACTIONS HANDLING
    
    public function handle_custom_actions() {
        if (!isset($_GET['naval_action'])) {
            return;
        }
        
        $action = sanitize_text_field($_GET['naval_action']);
        
        switch ($action) {
            case 'login':
                $this->handle_login();
                break;
            case 'logout':
                $this->handle_logout();
                break;
        }
    }
    
    private function handle_login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        if (!wp_verify_nonce($_POST['naval_egt_nonce'], 'naval_egt_login')) {
            wp_die('Errore di sicurezza');
        }
        
        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);
        
        $credentials = array(
            'user_login' => $username,
            'user_password' => $password,
            'remember' => $remember
        );
        
        $user = wp_signon($credentials);
        
        if (is_wp_error($user)) {
            $this->add_notice('Credenziali non valide.', 'error');
            return;
        }
        
        if (!naval_egt_user_can_access($user->ID)) {
            wp_logout();
            $this->add_notice('Non hai i permessi per accedere.', 'error');
            return;
        }
        
        wp_redirect(home_url('/dashboard-clienti/'));
        exit;
    }
    
    private function handle_logout() {
        wp_logout();
        wp_redirect(home_url('/area-riservata/'));
        exit;
    }
    
    // HELPER METHODS
    
    private function generate_username($email) {
        $username = sanitize_user(substr($email, 0, strpos($email, '@')));
        $counter = 1;
        $original = $username;
        
        while (username_exists($username)) {
            $username = $original . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    private function create_user_ftp_folder($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) return false;
        
        $upload_dir = wp_upload_dir();
        $base_path = $upload_dir['basedir'] . '/naval-egt-ftp/';
        
        if (!file_exists($base_path)) {
            wp_mkdir_p($base_path);
        }
        
        $folder_name = 'client_' . $user_id . '_' . sanitize_title($user->user_login);
        $folder_path = $base_path . $folder_name . '/';
        
        $downloads_path = $folder_path . 'downloads/';
        $uploads_path = $folder_path . 'uploads/';
        $shared_path = $folder_path . 'shared/';
        
        wp_mkdir_p($downloads_path);
        wp_mkdir_p($uploads_path);
        wp_mkdir_p($shared_path);
        
        // Security files
        file_put_contents($folder_path . '.htaccess', "Options -Indexes\nDeny from all\n");
        file_put_contents($downloads_path . '.htaccess', "Options -Indexes\n");
        file_put_contents($uploads_path . '.htaccess', "Options -Indexes\n");
        file_put_contents($shared_path . '.htaccess', "Options -Indexes\n");
        
        // README file
        $readme = "Cartella FTP per " . $user->display_name . "\n";
        $readme .= "Creata il: " . date('d/m/Y H:i:s') . "\n\n";
        $readme .= "downloads/ = File condivisi da Naval EGT\n";
        $readme .= "uploads/ = I tuoi file caricati\n";
        $readme .= "shared/ = File condivisi bidirezionali\n";
        file_put_contents($folder_path . 'README.txt', $readme);
        
        update_user_meta($user_id, 'naval_egt_ftp_folder', $folder_name);
        
        return $folder_name;
    }
    
    private function send_welcome_email($user_id, $password) {
        $user = get_user_by('id', $user_id);
        if (!$user) return false;
        
        $to = $user->user_email;
        $subject = 'Benvenuto nell\'Area Riservata Naval EGT - Credenziali di Accesso';
        
        $message = "Ciao " . $user->display_name . ",\n\n";
        $message .= "Benvenuto nell'Area Riservata Naval EGT!\n\n";
        $message .= "Il tuo account è stato creato con successo. Ecco i tuoi dati di accesso:\n\n";
        $message .= "🔐 DATI DI ACCESSO:\n";
        $message .= "- Email: " . $user->user_email . "\n";
        $message .= "- Username: " . $user->user_login . "\n";
        $message .= "- Password: " . $password . "\n\n";
        $message .= "🌐 LINK DI ACCESSO:\n";
        $message .= home_url('/area-riservata/') . "\n\n";
        $message .= "📁 COSA PUOI FARE:\n";
        $message .= "- Accedere alla tua area riservata personale\n";
        $message .= "- Visualizzare e scaricare i file che Naval EGT condivide con te\n";
        $message .= "- Gestire il tuo profilo\n\n";
        $message .= "Per qualsiasi assistenza, contatta:\n";
        $message .= "📧 technical@navalegt.it\n\n";
        $message .= "Cordiali saluti,\n";
        $message .= "Il Team Naval EGT";
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: Naval EGT <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>'
        );
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    private function notify_user_new_files($user, $files) {
        $subject = 'Nuovi file disponibili - Naval EGT';
        $files_list = implode("\n- ", $files);
        
        $message = "Ciao " . $user->display_name . ",\n\n";
        $message .= "Sono stati caricati nuovi file nella tua area riservata Naval EGT:\n\n";
        $message .= "- " . $files_list . "\n\n";
        $message .= "Puoi accedere alla tua area riservata per visualizzarli e scaricarli:\n";
        $message .= home_url('/dashboard-clienti/') . "\n\n";
        $message .= "Cordiali saluti,\n";
        $message .= "Il Team Naval EGT";
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: Naval EGT <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>'
        );
        
        return wp_mail($user->user_email, $subject, $message, $headers);
    }
    
    private function get_user_files($user_id) {
        $folder_name = get_user_meta($user_id, 'naval_egt_ftp_folder', true);
        if (!$folder_name) return array();
        
        $upload_dir = wp_upload_dir();
        $downloads_path = $upload_dir['basedir'] . '/naval-egt-ftp/' . $folder_name . '/downloads/';
        
        if (!is_dir($downloads_path)) return array();
        
        $files = array();
        $items = scandir($downloads_path);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.htaccess') continue;
            
            $file_path = $downloads_path . $item;
            if (is_file($file_path)) {
                $files[] = array(
                    'name' => $item,
                    'path' => 'downloads/' . $item,
                    'size' => filesize($file_path),
                    'date' => filemtime($file_path)
                );
            }
        }
        
        return $files;
    }
    
    private function get_download_url($file_path) {
        return add_query_arg(array(
            'action' => 'naval_egt_download_file',
            'nonce' => wp_create_nonce('naval_egt_nonce'),
            'file' => urlencode($file_path)
        ), admin_url('admin-ajax.php'));
    }
    
    private function log_file_action($user_id, $action, $file_name, $file_path, $file_size) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'naval_egt_ftp_logs',
            array(
                'user_id' => $user_id,
                'action' => $action,
                'file_name' => $file_name,
                'file_path' => $file_path,
                'file_size' => $file_size,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s')
        );
    }
    
    private function delete_directory_recursive($dir) {
        if (!is_dir($dir)) return false;
        
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->delete_directory_recursive($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
    
    private function add_notice($message, $type = 'info') {
        if (!session_id()) session_start();
        
        if (!isset($_SESSION['naval_egt_notices'])) {
            $_SESSION['naval_egt_notices'] = array();
        }
        
        $_SESSION['naval_egt_notices'][] = array(
            'message' => $message,
            'type' => $type
        );
    }
    
    private function display_notices() {
        if (!session_id()) session_start();
        
        $notices = $_SESSION['naval_egt_notices'] ?? array();
        unset($_SESSION['naval_egt_notices']);
        
        foreach ($notices as $notice) {
            echo '<div class="naval-egt-notice naval-egt-notice-' . esc_attr($notice['type']) . '">';
            echo '<p>' . esc_html($notice['message']) . '</p>';
            echo '</div>';
        }
    }
    
    // ACTIVATION/DEACTIVATION
    
    public function activate() {
        $this->create_tables();
        $this->create_roles();
        $this->create_pages();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // FTP Logs table
        $sql1 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}naval_egt_ftp_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action varchar(50) NOT NULL,
            file_name varchar(255),
            file_path text,
            file_size bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45),
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
    }
    
    private function create_roles() {
        add_role('naval_egt_client', 'Cliente Naval EGT', array(
            'read' => true,
            'naval_egt_access' => true,
        ));
    }
    
    private function create_pages() {
        // Area Riservata page
        if (!get_page_by_path('area-riservata')) {
            wp_insert_post(array(
                'post_title' => 'Area Riservata',
                'post_content' => '[naval_egt_login_form]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => 'area-riservata',
                'comment_status' => 'closed'
            ));
        }
        
        // Dashboard page
        if (!get_page_by_path('dashboard-clienti')) {
            wp_insert_post(array(
                'post_title' => 'Dashboard Clienti',
                'post_content' => '[naval_egt_dashboard]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => 'dashboard-clienti',
                'comment_status' => 'closed'
            ));
        }
    }
}

// Initialize plugin
new NavalEGTPlugin();

// Helper functions
function naval_egt_user_can_access($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) return false;
    
    $user = get_user_by('id', $user_id);
    if (!$user) return false;
    
    // Check if user has Naval EGT role and is active
    if (in_array('naval_egt_client', $user->roles)) {
        $status = get_user_meta($user_id, 'naval_egt_status', true) ?: 'active';
        return $status === 'active';
    }
    
    return in_array('administrator', $user->roles);
}

function naval_egt_get_user_data($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!naval_egt_user_can_access($user_id)) return false;
    
    $user = get_user_by('id', $user_id);
    if (!$user) return false;
    
    return array(
        'id' => $user->ID,
        'username' => $user->user_login,
        'email' => $user->user_email,
        'display_name' => $user->display_name,
        'registered' => $user->user_registered,
        'company' => get_user_meta($user->ID, 'naval_egt_company', true),
        'phone' => get_user_meta($user->ID, 'naval_egt_phone', true),
        'ftp_folder' => get_user_meta($user->ID, 'naval_egt_ftp_folder', true)
    );
}

// Add CSS styles to make notices look good
add_action('wp_head', function() {
    ?>
    <style>
    .naval-egt-notice {
        padding: 15px 20px;
        margin-bottom: 20px;
        border-radius: 8px;
        border-left: 4px solid;
        font-weight: 500;
    }
    
    .naval-egt-notice-success {
        background: #d4edda;
        border-color: #28a745;
        color: #155724;
    }
    
    .naval-egt-notice-error {
        background: #f8d7da;
        border-color: #dc3545;
        color: #721c24;
    }
    
    .naval-egt-notice-warning {
        background: #fff3cd;
        border-color: #ffc107;
        color: #856404;
    }
    
    .naval-egt-area-riservata {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .naval-egt-header {
        text-align: center;
        margin-bottom: 40px;
        padding: 40px;
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        color: white;
        border-radius: 15px;
    }
    
    .naval-egt-header h2 {
        margin: 0 0 15px 0;
        font-size: 2.2em;
        font-weight: 700;
    }
    
    .naval-egt-dashboard {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    @media (max-width: 768px) {
        .naval-egt-area-riservata > div,
        .naval-egt-dashboard > div > div {
            grid-template-columns: 1fr !important;
        }
    }
    </style>
    <?php
});

public function ajax_add_user() {
    // Verifica nonce - IMPORTANTE: usa il nonce corretto
    if (!wp_verify_nonce($_POST['naval_egt_add_user_nonce'], 'naval_egt_add_user')) {
        wp_send_json_error(array('message' => 'Errore di sicurezza'));
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Accesso negato'));
        return;
    }
    
    $first_name = sanitize_text_field($_POST['first_name'] ?? '');
    $last_name = sanitize_text_field($_POST['last_name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $company = sanitize_text_field($_POST['company'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $username = sanitize_text_field($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $status = sanitize_text_field($_POST['status'] ?? 'active');
    
    // Validazione
    if (empty($first_name) || empty($last_name) || empty($email) || empty($company)) {
        wp_send_json_error(array('message' => 'Tutti i campi obbligatori devono essere compilati'));
        return;
    }
    
    if (!is_email($email)) {
        wp_send_json_error(array('message' => 'Inserisci un indirizzo email valido'));
        return;
    }
    
    if (email_exists($email)) {
        wp_send_json_error(array('message' => 'Un account con questa email esiste già'));
        return;
    }
    
    // Genera username se non fornito
    if (empty($username)) {
        $username = $this->generate_username($email);
    } else {
        if (username_exists($username)) {
            wp_send_json_error(array('message' => 'Username già in uso'));
            return;
        }
    }
    
    // Genera password se non fornita
    if (empty($password)) {
        $password = wp_generate_password(12, false);
    }
    
    // Crea utente
    $user_id = wp_create_user($username, $password, $email);
    
    if (is_wp_error($user_id)) {
        wp_send_json_error(array('message' => 'Errore durante la creazione utente: ' . $user_id->get_error_message()));
        return;
    }
    
    // Imposta ruolo
    $user = new WP_User($user_id);
    $user->set_role('naval_egt_client');
    
    // Salva metadati aggiuntivi
    update_user_meta($user_id, 'first_name', $first_name);
    update_user_meta($user_id, 'last_name', $last_name);
    update_user_meta($user_id, 'naval_egt_company', $company);
    update_user_meta($user_id, 'naval_egt_phone', $phone);
    update_user_meta($user_id, 'naval_egt_status', $status);
    
    // Aggiorna display name
    wp_update_user(array(
        'ID' => $user_id,
        'display_name' => $first_name . ' ' . $last_name
    ));
    
    // Crea cartella FTP
    $folder_name = $this->create_user_ftp_folder($user_id);
    
    // Invia email di benvenuto se richiesto
    if (isset($_POST['send_welcome_email']) && $_POST['send_welcome_email'] == '1') {
        $this->send_welcome_email($user_id, $password);
    }
    
    wp_send_json_success(array(
        'message' => 'Utente creato con successo',
        'user_id' => $user_id,
        'username' => $username
    ));
}

// IMPORTANTE: Registra correttamente l'AJAX action nel costruttore della classe:
// Aggiungi questa riga nel metodo __construct():
add_action('wp_ajax_naval_egt_add_user', array($this, 'ajax_add_user'));

?>