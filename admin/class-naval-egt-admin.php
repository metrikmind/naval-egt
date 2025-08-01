<?php
/**
 * Classe per la gestione dell'area admin - Versione completa con debug avanzato
 */

if (!defined('ABSPATH')) {
    exit;
}

class Naval_EGT_Admin {
    
    private static $instance = null;
    private $admin_notices = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_naval_egt_ajax', array($this, 'handle_ajax_requests'));
        add_action('wp_ajax_naval_egt_export', array($this, 'handle_export_requests'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }
    
    /**
     * Aggiunge menu admin
     */
    public function add_admin_menu() {
        add_menu_page(
            'Naval EGT',
            'Naval EGT',
            'manage_options',
            'naval-egt',
            array($this, 'render_admin_page'),
            'dashicons-cloud',
            30
        );
    }
    
    /**
     * Carica script admin
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'naval-egt') === false) {
            return;
        }
        
        wp_enqueue_script('naval-egt-admin', plugin_dir_url(__FILE__) . 'js/admin.js', array('jquery'), '1.0.0', true);
        wp_enqueue_style('naval-egt-admin', plugin_dir_url(__FILE__) . 'css/admin.css', array(), '1.0.0');
        
        wp_localize_script('naval-egt-admin', 'naval_egt_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('naval_egt_nonce')
        ));
    }
    
    /**
     * Inizializzazione admin
     */
    public function admin_init() {
        // Gestione callback Dropbox se presente - URL AGGIORNATO
        if (isset($_GET['page']) && $_GET['page'] === 'naval-egt' && 
            isset($_GET['tab']) && $_GET['tab'] === 'dropbox' && 
            isset($_GET['action']) && $_GET['action'] === 'callback') {
            $this->handle_dropbox_callback();
        }
        
        // Mantieni compatibilità con URL legacy
        if (isset($_GET['dropbox_callback']) && $_GET['dropbox_callback'] === '1') {
            $this->handle_dropbox_callback_legacy();
        }

        // NUOVO: Gestione azioni debug Dropbox
        if (isset($_GET['page']) && $_GET['page'] === 'naval-egt' && 
            isset($_GET['tab']) && $_GET['tab'] === 'dropbox') {
            $this->handle_dropbox_debug_actions();
        }
    }

    /**
     * NUOVO: Gestisce le azioni di debug Dropbox
     */
    private function handle_dropbox_debug_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $action = isset($_GET['dropbox_debug_action']) ? sanitize_text_field($_GET['dropbox_debug_action']) : '';
        
        if (empty($action)) {
            return;
        }

        // Verifica nonce per sicurezza
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'naval_egt_dropbox_debug')) {
            $this->add_admin_notice('Errore di sicurezza. Riprova.', 'error');
            return;
        }

        $dropbox = Naval_EGT_Dropbox::get_instance();

        switch ($action) {
            case 'test_diagnosis':
                $diagnosis = $dropbox->full_system_diagnosis();
                $this->set_dropbox_diagnosis_data($diagnosis);
                $this->add_admin_notice('Diagnosi completa eseguita. Controlla i risultati qui sotto.', 'info');
                break;

            case 'regenerate_token':
                $result = $dropbox->force_reauth();
                if ($result['success']) {
                    $this->add_admin_notice($result['message'], 'success');
                    if (!empty($result['auth_url'])) {
                        $this->set_auth_url_for_display($result['auth_url']);
                    }
                } else {
                    $this->add_admin_notice('Errore nella rigenerazione: ' . $result['message'], 'error');
                }
                break;

            case 'test_connection':
                $test = $dropbox->test_connection();
                if ($test['success']) {
                    $this->add_admin_notice('✅ Test connessione riuscito: ' . $test['message'], 'success');
                } else {
                    $this->add_admin_notice('❌ Test connessione fallito: ' . $test['message'], 'error');
                }
                break;

            case 'analyze_token':
                $analysis = $dropbox->analyze_token_detailed();
                $this->set_token_analysis_data($analysis);
                $this->add_admin_notice('Analisi token completata. Controlla i risultati qui sotto.', 'info');
                break;

            case 'test_multiple_methods':
                $tests = $dropbox->test_token_multiple_methods();
                $this->set_multiple_tests_data($tests);
                $this->add_admin_notice('Test multipli completati. Controlla i risultati qui sotto.', 'info');
                break;
        }

        // Redirect per pulire l'URL
        wp_redirect(admin_url('admin.php?page=naval-egt&tab=dropbox&debug_completed=1'));
        exit;
    }

    /**
     * NUOVO: Salva i dati della diagnosi per la visualizzazione
     */
    private function set_dropbox_diagnosis_data($diagnosis) {
        set_transient('naval_egt_dropbox_diagnosis', $diagnosis, 300); // 5 minuti
    }

    /**
     * NUOVO: Salva l'URL di autorizzazione per la visualizzazione
     */
    private function set_auth_url_for_display($auth_url) {
        set_transient('naval_egt_dropbox_auth_url', $auth_url, 600); // 10 minuti
    }

    /**
     * NUOVO: Salva i dati dell'analisi token
     */
    private function set_token_analysis_data($analysis) {
        set_transient('naval_egt_token_analysis', $analysis, 300); // 5 minuti
    }

    /**
     * NUOVO: Salva i dati dei test multipli
     */
    private function set_multiple_tests_data($tests) {
        set_transient('naval_egt_multiple_tests', $tests, 300); // 5 minuti
    }
    
    /**
     * Aggiunge notice admin
     */
    public function add_admin_notice($message, $type = 'success') {
        $this->admin_notices[] = array(
            'message' => $message,
            'type' => $type
        );
    }
    
    /**
     * Mostra notice admin
     */
    public function display_admin_notices() {
        foreach ($this->admin_notices as $notice) {
            $class = 'notice notice-' . $notice['type'];
            echo '<div class="' . esc_attr($class) . '"><p>' . wp_kses_post($notice['message']) . '</p></div>';
        }
    }
    
    /**
     * Gestisce callback Dropbox - VERSIONE AGGIORNATA
     */
    private function handle_dropbox_callback() {
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        // Gestisce errori OAuth
        if (isset($_GET['error'])) {
            $error_message = isset($_GET['error_description']) ? $_GET['error_description'] : $_GET['error'];
            $this->add_admin_notice('Errore Dropbox: ' . $error_message, 'error');
            return;
        }
        
        // Elabora il codice di autorizzazione
        if (!isset($_GET['code'])) {
            $this->add_admin_notice('Codice di autorizzazione mancante', 'error');
            return;
        }
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        $result = $dropbox->handle_authorization_callback();
        
        if ($result['success']) {
            $this->add_admin_notice($result['message'], 'success');
        } else {
            $this->add_admin_notice('Errore configurazione: ' . $result['message'], 'error');
        }
    }
    
    /**
     * Gestisce callback Dropbox legacy - COMPATIBILITÀ
     */
    private function handle_dropbox_callback_legacy() {
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        // Gestisce errori OAuth
        if (isset($_GET['error'])) {
            $error_message = isset($_GET['error_description']) ? $_GET['error_description'] : $_GET['error'];
            $this->add_admin_notice('Errore Dropbox: ' . $error_message, 'error');
            return;
        }
        
        // Elabora il codice di autorizzazione
        if (isset($_GET['code'])) {
            $code = sanitize_text_field($_GET['code']);
            $redirect_uri = admin_url('admin.php?page=naval-egt-settings&dropbox_callback=1');
            
            $dropbox = Naval_EGT_Dropbox::get_instance();
            $result = $dropbox->exchange_code_for_token($code, $redirect_uri);
            
            if ($result['success']) {
                // Test della connessione
                $account_info = $dropbox->get_account_info();
                if ($account_info['success']) {
                    $name = isset($account_info['data']['name']['display_name']) ? $account_info['data']['name']['display_name'] : 'Utente';
                    $this->add_admin_notice('Dropbox configurato con successo! Connesso come: ' . $name, 'success');
                } else {
                    $this->add_admin_notice('Token ottenuto ma test di connessione fallito. Verifica le impostazioni.', 'warning');
                }
            } else {
                $this->add_admin_notice('Errore durante l\'ottenimento del token: ' . $result['message'], 'error');
            }
            
            // Redirect per pulire l'URL
            wp_redirect(admin_url('admin.php?page=naval-egt&tab=dropbox'));
            exit;
        }
    }
    
    /**
     * Renderizza pagina admin principale
     */
    public function render_admin_page() {
        $current_tab = $_GET['tab'] ?? 'overview';
        
        ?>
        <div class="wrap">
            <h1>Naval EGT - Area Riservata Clienti</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=naval-egt&tab=overview" class="nav-tab <?php echo $current_tab === 'overview' ? 'nav-tab-active' : ''; ?>">📊 Panoramica</a>
                <a href="?page=naval-egt&tab=users" class="nav-tab <?php echo $current_tab === 'users' ? 'nav-tab-active' : ''; ?>">👥 Utenti</a>
                <a href="?page=naval-egt&tab=files" class="nav-tab <?php echo $current_tab === 'files' ? 'nav-tab-active' : ''; ?>">📁 File</a>
                <a href="?page=naval-egt&tab=logs" class="nav-tab <?php echo $current_tab === 'logs' ? 'nav-tab-active' : ''; ?>">📋 Log</a>
                <a href="?page=naval-egt&tab=dropbox" class="nav-tab <?php echo $current_tab === 'dropbox' ? 'nav-tab-active' : ''; ?>">☁️ Dropbox</a>
                <a href="?page=naval-egt&tab=dropbox-debug" class="nav-tab <?php echo $current_tab === 'dropbox-debug' ? 'nav-tab-active' : ''; ?>" style="color: #d63638;">🔍 Debug</a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($current_tab) {
                    case 'overview':
                        $this->render_overview_tab();
                        break;
                    case 'users':
                        $this->render_users_tab();
                        break;
                    case 'files':
                        $this->render_files_tab();
                        break;
                    case 'logs':
                        $this->render_logs_tab();
                        break;
                    case 'dropbox':
                        $this->render_dropbox_settings();
                        break;
                    case 'dropbox-debug':
                        $this->render_dropbox_debug();
                        break;
                    default:
                        $this->render_overview_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renderizza tab panoramica
     */
    private function render_overview_tab() {
        $stats = Naval_EGT_Database::get_user_stats();
        $dropbox = Naval_EGT_Dropbox::get_instance();
        $dropbox_status = $dropbox->get_connection_status();
        
        ?>
        <div class="naval-egt-dashboard">
            <h2>Benvenuto in Naval EGT</h2>
            
            <!-- Statistiche Rapide -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3>👥 Utenti Totali</h3>
                    <div class="stat-number"><?php echo $stats['total_users'] ?? 0; ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>✅ Utenti Attivi</h3>
                    <div class="stat-number"><?php echo $stats['active_users'] ?? 0; ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>📁 File Totali</h3>
                    <div class="stat-number"><?php echo $stats['total_files'] ?? 0; ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>💾 Spazio Usato</h3>
                    <div class="stat-number"><?php echo size_format($stats['total_size'] ?? 0); ?></div>
                </div>
            </div>
            
            <!-- Stato Dropbox -->
            <div class="card">
                <h3>☁️ Stato Dropbox</h3>
                <?php if ($dropbox_status['connected']): ?>
                    <p style="color: green;">✅ <strong>Connesso</strong></p>
                    <p><?php echo esc_html($dropbox_status['message']); ?></p>
                    <?php if (isset($dropbox_status['account_email'])): ?>
                        <p><strong>Account:</strong> <?php echo esc_html($dropbox_status['account_email']); ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color: red;">❌ <strong>Non Connesso</strong></p>
                    <p><?php echo esc_html($dropbox_status['message']); ?></p>
                    <a href="?page=naval-egt&tab=dropbox" class="button button-primary">Configura Dropbox</a>
                <?php endif; ?>
            </div>
            
            <!-- Azioni Rapide -->
            <div class="card">
                <h3>🚀 Azioni Rapide</h3>
                <div class="quick-actions">
                    <a href="?page=naval-egt&tab=users" class="button">👥 Gestisci Utenti</a>
                    <a href="?page=naval-egt&tab=files" class="button">📁 Gestisci File</a>
                    <a href="?page=naval-egt&tab=logs" class="button">📋 Visualizza Log</a>
                    <?php if ($dropbox_status['connected']): ?>
                        <button class="button" onclick="syncAllFolders()">🔄 Sincronizza Tutte le Cartelle</button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Attività Recenti -->
            <div class="card">
                <h3>📈 Attività Recenti</h3>
                <?php
                $recent_logs = Naval_EGT_Activity_Logger::get_logs(array(), 10, 0);
                if ($recent_logs):
                ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Utente</th>
                            <th>Azione</th>
                            <th>Dettagli</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $log): ?>
                        <tr>
                            <td><?php echo mysql2date('d/m/Y H:i', $log['created_at']); ?></td>
                            <td><?php echo esc_html($log['user_code']); ?></td>
                            <td><?php echo esc_html($log['action']); ?></td>
                            <td><?php echo esc_html($log['file_name'] ?? '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p><em>Nessuna attività recente</em></p>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .card {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }
        </style>
        
        <script>
        function syncAllFolders() {
            if (!confirm('Vuoi sincronizzare tutte le cartelle utenti con Dropbox?')) return;
            
            jQuery.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'sync_all_user_folders',
                nonce: '<?php echo wp_create_nonce('naval_egt_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('Sincronizzazione completata!\n\nUtenti processati: ' + response.data.stats.users_processed + '\nCartelle trovate: ' + response.data.stats.folders_found + '\nFile sincronizzati: ' + response.data.stats.files_synced);
                    location.reload();
                } else {
                    alert('Errore durante la sincronizzazione: ' + response.data);
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Renderizza tab utenti
     */
    private function render_users_tab() {
        ?>
        <div class="card">
            <h2>👥 Gestione Utenti</h2>
            <p>Gestisci gli utenti del sistema Naval EGT.</p>
            
            <!-- Filtri -->
            <div class="users-filters">
                <input type="text" id="user-search" placeholder="Cerca utenti..." />
                <select id="user-status-filter">
                    <option value="">Tutti gli stati</option>
                    <option value="ATTIVO">Attivo</option>
                    <option value="SOSPESO">Sospeso</option>
                    <option value="ELIMINATO">Eliminato</option>
                </select>
                <button class="button" onclick="filterUsers()">🔍 Filtra</button>
                <button class="button button-primary" onclick="addNewUser()">➕ Nuovo Utente</button>
            </div>
            
            <!-- Tabella Utenti -->
            <div id="users-table-container">
                <p><em>Caricamento utenti...</em></p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function() {
            loadUsers();
        });
        
        function loadUsers() {
            jQuery.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'filter_users',
                nonce: '<?php echo wp_create_nonce('naval_egt_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#users-table-container').html('<table class="widefat"><thead><tr><th>Codice</th><th>Nome</th><th>Email</th><th>Stato</th><th>Azioni</th></tr></thead><tbody>' + response.data.html + '</tbody></table>');
                } else {
                    jQuery('#users-table-container').html('<p style="color: red;">Errore nel caricamento utenti</p>');
                }
            });
        }
        
        function filterUsers() {
            var search = jQuery('#user-search').val();
            var status = jQuery('#user-status-filter').val();
            
            jQuery.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'filter_users',
                search: search,
                status: status,
                nonce: '<?php echo wp_create_nonce('naval_egt_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#users-table-container').html('<table class="widefat"><thead><tr><th>Codice</th><th>Nome</th><th>Email</th><th>Stato</th><th>Azioni</th></tr></thead><tbody>' + response.data.html + '</tbody></table>');
                } else {
                    alert('Errore nel filtro utenti');
                }
            });
        }
        
        function addNewUser() {
            alert('Funzionalità in sviluppo');
        }
        </script>
        <?php
    }
    
    /**
     * Renderizza tab file
     */
    private function render_files_tab() {
        ?>
        <div class="card">
            <h2>📁 Gestione File</h2>
            <p>Gestisci i file caricati dagli utenti.</p>
            
            <div class="file-upload-section">
                <h3>⬆️ Upload File per Utente</h3>
                <form id="admin-file-upload-form" enctype="multipart/form-data">
                    <table class="form-table">
                        <tr>
                            <th><label for="upload-user-select">Seleziona Utente</label></th>
                            <td>
                                <select id="upload-user-select" name="user_id" required>
                                    <option value="">Caricamento utenti...</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="upload-files">File</label></th>
                            <td>
                                <input type="file" id="upload-files" name="files[]" multiple required />
                                <p class="description">Seleziona uno o più file da caricare.</p>
                            </td>
                        </tr>
                    </table>
                    <button type="submit" class="button button-primary">📤 Carica File</button>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function() {
            loadUsersForSelect();
        });
        
        function loadUsersForSelect() {
            jQuery.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'get_users_list',
                nonce: '<?php echo wp_create_nonce('naval_egt_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    var options = '<option value="">Seleziona utente...</option>';
                    response.data.users.forEach(function(user) {
                        options += '<option value="' + user.id + '">' + user.user_code + ' - ' + user.nome + ' ' + user.cognome + '</option>';
                    });
                    jQuery('#upload-user-select').html(options);
                }
            });
        }
        
        jQuery('#admin-file-upload-form').on('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            formData.append('action', 'naval_egt_ajax');
            formData.append('naval_action', 'admin_upload_files');
            formData.append('nonce', '<?php echo wp_create_nonce('naval_egt_nonce'); ?>');
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(response) {
                    if (response.success) {
                        alert('File caricati con successo!');
                        jQuery('#admin-file-upload-form')[0].reset();
                    } else {
                        alert('Errore: ' + response.data);
                    }
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Renderizza tab log
     */
    private function render_logs_tab() {
        ?>
        <div class="card">
            <h2>📋 Log Attività</h2>
            <p>Visualizza e gestisci i log delle attività del sistema.</p>
            
            <!-- Filtri Log -->
            <div class="logs-filters">
                <select id="log-user-filter">
                    <option value="">Tutti gli utenti</option>
                </select>
                <select id="log-action-filter">
                    <option value="">Tutte le azioni</option>
                    <option value="LOGIN">Login</option>
                    <option value="LOGOUT">Logout</option>
                    <option value="UPLOAD">Upload</option>
                    <option value="DOWNLOAD">Download</option>
                    <option value="DELETE">Delete</option>
                </select>
                <input type="date" id="log-date-from" />
                <input type="date" id="log-date-to" />
                <button class="button" onclick="filterLogs()">🔍 Filtra</button>
                <button class="button button-secondary" onclick="clearLogs()" style="color: red;">🗑️ Pulisci Log</button>
            </div>
            
            <!-- Tabella Log -->
            <div id="logs-table-container">
                <p><em>Caricamento log...</em></p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function() {
            loadLogs();
            loadUsersForLogFilter();
        });
        
        function loadLogs() {
            jQuery.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'filter_logs',
                nonce: '<?php echo wp_create_nonce('naval_egt_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#logs-table-container').html('<table class="widefat"><thead><tr><th>Data</th><th>Utente</th><th>Azione</th><th>File</th><th>IP</th></tr></thead><tbody>' + response.data.html + '</tbody></table>');
                }
            });
        }
        
        function loadUsersForLogFilter() {
            jQuery.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'get_users_list',
                nonce: '<?php echo wp_create_nonce('naval_egt_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    var options = '<option value="">Tutti gli utenti</option>';
                    response.data.users.forEach(function(user) {
                        options += '<option value="' + user.user_code + '">' + user.user_code + ' - ' + user.nome + ' ' + user.cognome + '</option>';
                    });
                    jQuery('#log-user-filter').html(options);
                }
            });
        }
        
        function filterLogs() {
            var filters = {
                user_code: jQuery('#log-user-filter').val(),
                action: jQuery('#log-action-filter').val(),
                date_from: jQuery('#log-date-from').val(),
                date_to: jQuery('#log-date-to').val()
            };
            
            jQuery.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'filter_logs',
                filters: filters,
                nonce: '<?php echo wp_create_nonce('naval_egt_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#logs-table-container').html('<table class="widefat"><thead><tr><th>Data</th><th>Utente</th><th>Azione</th><th>File</th><th>IP</th></tr></thead><tbody>' + response.data.html + '</tbody></table>');
                }
            });
        }
        
        function clearLogs() {
            if (!confirm('Sei sicuro di voler eliminare tutti i log? Questa azione non può essere annullata.')) return;
            
            jQuery.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'clear_logs',
                nonce: '<?php echo wp_create_nonce('naval_egt_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('Log eliminati con successo');
                    loadLogs();
                } else {
                    alert('Errore nell\'eliminazione dei log');
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Renderizza pagina impostazioni Dropbox - AGGIORNATA CON DEBUG INTEGRATO
     */
    private function render_dropbox_settings() {
        $dropbox = Naval_EGT_Dropbox::get_instance();
        
        // Gestisci il salvataggio delle credenziali (se necessario per compatibilità)
        if (isset($_POST['save_dropbox_credentials'])) {
            check_admin_referer('naval_egt_dropbox_settings');
            $this->add_admin_notice('Le credenziali sono già preconfigurate nel plugin.', 'info');
        }
        
        // Gestisci test connessione
        if (isset($_POST['test_dropbox_connection'])) {
            check_admin_referer('naval_egt_dropbox_test');
            
            $test_result = $dropbox->test_connection();
            
            if ($test_result['success']) {
                $this->add_admin_notice($test_result['message'], 'success');
            } else {
                $this->add_admin_notice('Test connessione fallito: ' . $test_result['message'], 'error');
            }
        }
        
        // Gestisci reset configurazione
        if (isset($_POST['reset_dropbox_config'])) {
            check_admin_referer('naval_egt_dropbox_reset');
            
            $result = $dropbox->disconnect();
            $this->add_admin_notice($result['message'], 'success');
        }
        
        $is_configured = $dropbox->is_configured();
        $connection_status = $dropbox->get_connection_status();

        // NUOVO: Recupera dati debug se disponibili
        $diagnosis_data = get_transient('naval_egt_dropbox_diagnosis');
        $auth_url_display = get_transient('naval_egt_dropbox_auth_url');
        $token_analysis = get_transient('naval_egt_token_analysis');
        $multiple_tests = get_transient('naval_egt_multiple_tests');
        
        ?>
        <div class="wrap">
            <h2>☁️ Configurazione Dropbox</h2>
            
            <!-- NUOVO: Strumenti di Debug Integrati -->
            <div class="card" style="border-left: 4px solid #dc3232;">
                <h3>🔧 Strumenti di Debug e Risoluzione Problemi</h3>
                <p><strong>Se Dropbox non funziona correttamente, usa questi strumenti per diagnosticare e risolvere i problemi:</strong></p>
                
                <div style="margin: 15px 0;">
                    <?php
                    $debug_base_url = admin_url('admin.php?page=naval-egt&tab=dropbox');
                    $nonce = wp_create_nonce('naval_egt_dropbox_debug');
                    ?>
                    
                    <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=test_diagnosis&_wpnonce=' . $nonce); ?>" 
                       class="button button-primary" style="margin-right: 10px;">
                        🔍 Diagnosi Completa
                    </a>
                    
                    <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=analyze_token&_wpnonce=' . $nonce); ?>" 
                       class="button" style="margin-right: 10px;">
                        🔑 Analizza Token
                    </a>
                    
                    <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=test_multiple_methods&_wpnonce=' . $nonce); ?>" 
                       class="button" style="margin-right: 10px;">
                        🧪 Test Multipli
                    </a>
                    
                    <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=test_connection&_wpnonce=' . $nonce); ?>" 
                       class="button" style="margin-right: 10px;">
                        📡 Test Connessione
                    </a>
                    
                    <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=regenerate_token&_wpnonce=' . $nonce); ?>" 
                       class="button" style="background: #dc3232; border-color: #dc3232; color: white; margin-right: 10px;"
                       onclick="return confirm('⚠️ ATTENZIONE: Questo cancellerà il token corrente e dovrai riautorizzare Dropbox.\n\nUsa questo solo se il token attuale è corrotto.\n\nProcedere?');">
                        🔄 Rigenera Token
                    </a>
                </div>
                
                <p><small><strong>💡 Suggerimento:</strong> Se Dropbox non funziona, prova prima "Diagnosi Completa" per capire il problema, poi "Rigenera Token" se necessario.</small></p>
            </div>

            <!-- NUOVO: Mostra URL di autorizzazione se generato -->
            <?php if ($auth_url_display): ?>
            <div class="card" style="border-left: 4px solid #00a32a; background: #f0fff0;">
                <h3>🔗 Autorizzazione Dropbox Richiesta</h3>
                <p><strong>Il token è stato cancellato. Ora devi autorizzare nuovamente l'applicazione su Dropbox:</strong></p>
                <p>
                    <a href="<?php echo esc_url($auth_url_display); ?>" 
                       target="_blank" 
                       class="button button-primary button-large"
                       style="background: #00a32a; border-color: #00a32a;">
                        🚀 AUTORIZZA SU DROPBOX
                    </a>
                </p>
                <p><small>Dopo aver cliccato il link, autorizza l'app su Dropbox e verrai reindirizzato automaticamente qui.</small></p>
            </div>
            <?php endif; ?>

            <!-- NUOVO: Mostra risultati diagnosi -->
            <?php if ($diagnosis_data): ?>
            <div class="card" style="border-left: 4px solid #0073aa;">
                <h3>📊 Risultati Diagnosi Completa</h3>
                
                <?php if (isset($diagnosis_data['token_analysis']['error'])): ?>
                    <div style="background: #ffeaea; padding: 15px; margin: 10px 0; border: 1px solid #dc3232; border-radius: 4px;">
                        <h4 style="color: #dc3232;">❌ Token Mancante</h4>
                        <p><?php echo esc_html($diagnosis_data['token_analysis']['error']); ?></p>
                    </div>
                <?php else: ?>
                    <div style="background: <?php echo $diagnosis_data['token_analysis']['seems_valid'] ? '#eafaea' : '#ffeaea'; ?>; padding: 15px; margin: 10px 0; border-radius: 4px;">
                        <h4 style="color: <?php echo $diagnosis_data['token_analysis']['seems_valid'] ? '#00a32a' : '#dc3232'; ?>;">
                            <?php echo $diagnosis_data['token_analysis']['seems_valid'] ? '✅ Token Valido' : '❌ Token Non Valido'; ?>
                        </h4>
                        <p><strong>Lunghezza:</strong> <?php echo $diagnosis_data['token_analysis']['length']; ?> caratteri</p>
                        <p><strong>Caratteri unici:</strong> <?php echo $diagnosis_data['token_analysis']['unique_chars']; ?></p>
                        <p><strong>Sembra valido:</strong> <?php echo $diagnosis_data['token_analysis']['seems_valid'] ? 'SÌ' : 'NO'; ?></p>
                    </div>
                <?php endif; ?>

                <?php if (isset($diagnosis_data['token_tests']['summary'])): ?>
                    <div style="background: <?php echo $diagnosis_data['token_tests']['summary']['success_count'] > 0 ? '#eafaea' : '#ffeaea'; ?>; padding: 15px; margin: 10px 0; border-radius: 4px;">
                        <h4 style="color: <?php echo $diagnosis_data['token_tests']['summary']['success_count'] > 0 ? '#00a32a' : '#dc3232'; ?>;">
                            🧪 Test API: <?php echo $diagnosis_data['token_tests']['summary']['success_count']; ?>/<?php echo $diagnosis_data['token_tests']['summary']['total_methods_tested']; ?> funzionanti
                        </h4>
                        <p><strong>Token funziona:</strong> <?php echo $diagnosis_data['token_tests']['summary']['token_seems_valid'] ? 'SÌ' : 'NO'; ?></p>
                    </div>
                <?php endif; ?>

                <?php if (isset($diagnosis_data['recommendations']) && !empty($diagnosis_data['recommendations'])): ?>
                    <h4>💡 Raccomandazioni:</h4>
                    <?php foreach ($diagnosis_data['recommendations'] as $rec): ?>
                        <div style="background: #fff; padding: 10px; margin: 5px 0; border-left: 4px solid <?php echo $rec['priority'] === 'HIGH' ? '#dc3232' : ($rec['priority'] === 'MEDIUM' ? '#ffb900' : '#00a32a'); ?>;">
                            <p><strong><?php echo esc_html($rec['priority']); ?>:</strong> <?php echo esc_html($rec['issue']); ?></p>
                            <p><em><?php echo esc_html($rec['solution']); ?></em></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <p><small><a href="?page=naval-egt&tab=dropbox-debug">🔍 Vai al Debug Avanzato per maggiori dettagli</a></small></p>
            </div>
            <?php 
            // Pulisci il transient dopo la visualizzazione
            delete_transient('naval_egt_dropbox_diagnosis');
            endif; ?>

            <!-- NUOVO: Mostra analisi token -->
            <?php if ($token_analysis): ?>
            <div class="card" style="border-left: 4px solid #ffb900;">
                <h3>🔑 Analisi Token Dettagliata</h3>
                
                <?php if (isset($token_analysis['error'])): ?>
                    <p style="color: #dc3232;"><strong>❌ <?php echo esc_html($token_analysis['error']); ?></strong></p>
                <?php else: ?>
                    <table class="widefat">
                        <tr><th>Lunghezza</th><td><?php echo $token_analysis['length']; ?> caratteri</td></tr>
                        <tr><th>Caratteri unici</th><td><?php echo $token_analysis['unique_chars']; ?></td></tr>
                        <tr><th>Contiene spazi</th><td><?php echo $token_analysis['contains_spaces'] ? '❌ SÌ' : '✅ NO'; ?></td></tr>
                        <tr><th>Sembra valido</th><td><?php echo $token_analysis['seems_valid'] ? '✅ SÌ' : '❌ NO'; ?></td></tr>
                        <tr><th>Inizia con "sl."</th><td><?php echo $token_analysis['dropbox_patterns']['starts_with_sl'] ? '✅ SÌ' : '❌ NO'; ?></td></tr>
                    </table>
                <?php endif; ?>
            </div>
            <?php 
            delete_transient('naval_egt_token_analysis');
            endif; ?>

            <!-- NUOVO: Mostra test multipli -->
            <?php if ($multiple_tests): ?>
            <div class="card" style="border-left: 4px solid #7c3aed;">
                <h3>🧪 Risultati Test Multipli</h3>
                
                <?php if (isset($multiple_tests['error'])): ?>
                    <p style="color: #dc3232;"><strong>❌ <?php echo esc_html($multiple_tests['error']); ?></strong></p>
                <?php else: ?>
                    <table class="widefat">
                        <thead>
                            <tr><th>Metodo</th><th>Stato</th><th>Codice HTTP</th><th>Errore</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($multiple_tests as $method => $result): ?>
                                <?php if ($method === 'summary') continue; ?>
                                <tr>
                                    <td><strong><?php echo esc_html($method); ?></strong></td>
                                    <td><?php echo $result['success'] ? '✅ OK' : '❌ FAIL'; ?></td>
                                    <td><?php echo $result['http_code'] ?? 'N/A'; ?></td>
                                    <td><?php echo esc_html($result['curl_error'] ?? $result['wp_error'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (isset($multiple_tests['summary'])): ?>
                        <p><strong>Riepilogo:</strong> <?php echo $multiple_tests['summary']['success_count']; ?>/<?php echo $multiple_tests['summary']['total_methods_tested']; ?> metodi funzionanti</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php 
            delete_transient('naval_egt_multiple_tests');
            endif; ?>
            
            <!-- Stato configurazione -->
            <div class="card">
                <h3>Stato Configurazione</h3>
                <table class="form-table">
                    <tr>
                        <th>Stato Dropbox</th>
                        <td>
                            <?php if ($is_configured && $connection_status['connected']): ?>
                                <span style="color: green; font-weight: bold;">✅ CONFIGURATO E CONNESSO</span>
                                <br><small><?php echo esc_html($connection_status['message']); ?></small>
                            <?php elseif ($is_configured): ?>
                                <span style="color: orange; font-weight: bold;">⚠️ CONFIGURATO MA NON CONNESSO</span>
                                <br><small><?php echo esc_html($connection_status['message']); ?></small>
                            <?php else: ?>
                                <span style="color: red; font-weight: bold;">❌ NON CONFIGURATO</span>
                                <br><small>È necessario autorizzare l'applicazione su Dropbox</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Credenziali App</th>
                        <td>
                            ✅ <strong>Preconfigurate</strong>
                            <br><small>App Key e App Secret sono integrati nel plugin</small>
                        </td>
                    </tr>
                    <?php if ($connection_status['connected'] && isset($connection_status['account_email'])): ?>
                    <tr>
                        <th>Account Connesso</th>
                        <td>
                            <?php echo esc_html($connection_status['account_email']); ?>
                            <?php if (isset($connection_status['account_name'])): ?>
                                <br><small><?php echo esc_html($connection_status['account_name']); ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <?php if (!$is_configured || !$connection_status['connected']): ?>
            <!-- Autorizzazione -->
            <div class="card">
                <h3>🔐 Autorizzazione Dropbox</h3>
                <p>Per utilizzare le funzionalità di Dropbox, è necessario autorizzare l'applicazione.</p>
                
                <?php
                $auth_url = $dropbox->get_authorization_url();
                if ($auth_url):
                ?>
                <p>
                    <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary button-large">
                        🔐 Autorizza su Dropbox
                    </a>
                </p>
                <p class="description">
                    Verrai reindirizzato su Dropbox per autorizzare l'applicazione. 
                    Dopo l'autorizzazione, tornerai automaticamente qui.
                </p>
                <?php else: ?>
                <p style="color: red;">❌ Errore nella generazione dell'URL di autorizzazione.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($is_configured): ?>
            <!-- Dropbox configurato -->
            <div class="card">
                <h3>⚙️ Gestione Configurazione</h3>
                
                <!-- Test connessione -->
                <form method="post" style="display: inline-block; margin-right: 10px;">
                    <?php wp_nonce_field('naval_egt_dropbox_test'); ?>
                    <input type="submit" name="test_dropbox_connection" class="button" value="🔍 Testa Connessione" />
                </form>
                
                <!-- Sincronizza cartelle -->
                <button class="button" onclick="syncAllUserFolders()" style="margin-right: 10px;">🔄 Sincronizza Tutte le Cartelle</button>
                
                <!-- Reset configurazione -->
                <form method="post" style="display: inline-block;" 
                      onsubmit="return confirm('Sei sicuro di voler disconnettere Dropbox? Dovrai riautorizzare l\'applicazione.');">
                    <?php wp_nonce_field('naval_egt_dropbox_reset'); ?>
                    <input type="submit" name="reset_dropbox_config" class="button button-secondary" value="🔌 Disconnetti Dropbox" />
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Guida configurazione -->
            <div class="card">
                <h3>📋 Informazioni Configurazione</h3>
                <p><strong>URL di Redirect configurato nell'app Dropbox:</strong></p>
                <code><?php echo admin_url('admin.php?page=naval-egt&tab=dropbox&action=callback'); ?></code>
                
                <h4>Permessi richiesti:</h4>
                <ul>
                    <li><code>files.metadata.write</code> - Scrittura metadati file</li>
                    <li><code>files.metadata.read</code> - Lettura metadati file</li>
                    <li><code>files.content.write</code> - Scrittura contenuto file</li>
                    <li><code>files.content.read</code> - Lettura contenuto file</li>
                </ul>
                
                <p><strong>Struttura cartelle:</strong></p>
                <p>Il plugin cerca automaticamente cartelle che iniziano con il codice utente (es. <code>100001_Nome_Cliente</code>)</p>
            </div>
        </div>
        
        <script>
        function syncAllUserFolders() {
            if (!confirm('Vuoi sincronizzare tutte le cartelle utenti con Dropbox? Questa operazione potrebbe richiedere alcuni minuti.')) return;
            
            jQuery.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'sync_all_user_folders',
                nonce: '<?php echo wp_create_nonce('naval_egt_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    var stats = response.data.stats;
                    var message = 'Sincronizzazione completata!\n\n';
                    message += 'Utenti processati: ' + stats.users_processed + '\n';
                    message += 'Cartelle trovate: ' + stats.folders_found + '\n';
                    message += 'File sincronizzati: ' + stats.files_synced;
                    
                    if (stats.errors.length > 0) {
                        message += '\n\nErrori:\n' + stats.errors.join('\n');
                    }
                    
                    alert(message);
                } else {
                    alert('Errore durante la sincronizzazione: ' + response.data);
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Renderizza pagina debug Dropbox - AGGIORNATA
     */
    private function render_dropbox_debug() {
        $dropbox = Naval_EGT_Dropbox::get_instance();
        
        // Gestisci azioni debug
        if (isset($_POST['clear_debug_logs'])) {
            check_admin_referer('naval_egt_debug_clear');
            $dropbox->clear_debug_logs();
            $this->add_admin_notice('Log di debug puliti.', 'success');
        }
        
        if (isset($_POST['test_configuration'])) {
            check_admin_referer('naval_egt_debug_test');
            $test_result = $dropbox->test_connection();
            if ($test_result['success']) {
                $this->add_admin_notice('Test configurazione: ' . $test_result['message'], 'success');
            } else {
                $this->add_admin_notice('Test configurazione fallito: ' . $test_result['message'], 'error');
            }
        }
        
        if (isset($_POST['reload_credentials'])) {
            check_admin_referer('naval_egt_debug_reload');
            $dropbox->reload_credentials();
            $this->add_admin_notice('Credenziali ricaricate dal database.', 'info');
        }

        // NUOVO: Gestione nuove azioni debug
        if (isset($_POST['run_full_diagnosis'])) {
            check_admin_referer('naval_egt_debug_diagnosis');
            $diagnosis = $dropbox->full_system_diagnosis();
            $this->set_dropbox_diagnosis_data($diagnosis);
            $this->add_admin_notice('Diagnosi completa eseguita. Controlla i risultati qui sotto.', 'info');
        }

        if (isset($_POST['force_token_regeneration'])) {
            check_admin_referer('naval_egt_debug_regen');
            $result = $dropbox->force_reauth();
            if ($result['success']) {
                $this->add_admin_notice($result['message'], 'success');
                if (!empty($result['auth_url'])) {
                    $this->set_auth_url_for_display($result['auth_url']);
                }
            } else {
                $this->add_admin_notice('Errore nella rigenerazione: ' . $result['message'], 'error');
            }
        }
        
        // Ottieni informazioni debug
        $debug_info = $dropbox->debug_configuration();
        $debug_logs = $dropbox->get_debug_logs();
        $is_configured = $dropbox->is_configured();

        // NUOVO: Recupera dati diagnosi se disponibili
        $diagnosis_data = get_transient('naval_egt_dropbox_diagnosis');
        $auth_url_display = get_transient('naval_egt_dropbox_auth_url');
        
        ?>
        <div class="wrap">
            <h1>🔍 Debug Dropbox - Naval EGT</h1>

            <!-- NUOVO: URL di autorizzazione se generato -->
            <?php if ($auth_url_display): ?>
            <div class="card" style="border-left: 4px solid #00a32a; background: #f0fff0;">
                <h3>🔗 Autorizzazione Dropbox Richiesta</h3>
                <p><strong>Il token è stato rigenerato. Ora devi autorizzare l'applicazione su Dropbox:</strong></p>
                <p>
                    <a href="<?php echo esc_url($auth_url_display); ?>" 
                       target="_blank" 
                       class="button button-primary button-large"
                       style="background: #00a32a; border-color: #00a32a;">
                        🚀 AUTORIZZA SU DROPBOX
                    </a>
                </p>
                <p><small>Dopo aver cliccato il link, autorizza l'app su Dropbox e verrai reindirizzato automaticamente.</small></p>
            </div>
            <?php 
            delete_transient('naval_egt_dropbox_auth_url');
            endif; ?>

            <!-- NUOVO: Risultati diagnosi dettagliati -->
            <?php if ($diagnosis_data): ?>
            <div class="card" style="border-left: 4px solid #0073aa;">
                <h3>📊 Risultati Diagnosi Completa Dettagliata</h3>
                
                <!-- Credenziali -->
                <h4>🔑 Credenziali</h4>
                <table class="widefat" style="margin-bottom: 20px;">
                    <tr>
                        <th>App Key</th>
                        <td><?php echo $diagnosis_data['credentials']['app_key_set'] ? '✅ SET' : '❌ MISSING'; ?></td>
                        <td><?php echo esc_html($diagnosis_data['credentials']['app_key_preview']); ?></td>
                    </tr>
                    <tr>
                        <th>App Secret</th>
                        <td><?php echo $diagnosis_data['credentials']['app_secret_set'] ? '✅ SET' : '❌ MISSING'; ?></td>
                        <td>Hardcoded: <?php echo $diagnosis_data['credentials']['hardcoded_credentials_match'] ? '✅' : '❌'; ?></td>
                    </tr>
                </table>

                <!-- Token Database -->
                <h4>💾 Token Database</h4>
                <table class="widefat" style="margin-bottom: 20px;">
                    <tr>
                        <th>Naval Database</th>
                        <td><?php echo $diagnosis_data['database_tokens']['naval_db_token_exists'] ? '✅ EXISTS' : '❌ EMPTY'; ?></td>
                        <td><?php echo $diagnosis_data['database_tokens']['naval_db_token_length']; ?> caratteri</td>
                    </tr>
                    <tr>
                        <th>WP Options</th>
                        <td><?php echo $diagnosis_data['database_tokens']['wp_option_token_exists'] ? '✅ EXISTS' : '❌ EMPTY'; ?></td>
                        <td><?php echo $diagnosis_data['database_tokens']['wp_option_token_length']; ?> caratteri</td>
                    </tr>
                    <tr>
                        <th>Token Match</th>
                        <td colspan="2"><?php echo $diagnosis_data['database_tokens']['tokens_match'] ? '✅ MATCH' : '❌ MISMATCH'; ?></td>
                    </tr>
                    <tr>
                        <th>Primary Source</th>
                        <td colspan="2"><strong><?php echo esc_html($diagnosis_data['database_tokens']['primary_token_source']); ?></strong></td>
                    </tr>
                </table>

                <!-- Analisi Token -->
                <?php if (!isset($diagnosis_data['token_analysis']['error'])): ?>
                <h4>🔍 Analisi Token</h4>
                <div style="background: <?php echo $diagnosis_data['token_analysis']['seems_valid'] ? '#eafaea' : '#ffeaea'; ?>; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <table class="widefat">
                        <tr><th>Lunghezza</th><td><?php echo $diagnosis_data['token_analysis']['length']; ?> caratteri</td></tr>
                        <tr><th>Caratteri unici</th><td><?php echo $diagnosis_data['token_analysis']['unique_chars']; ?></td></tr>
                        <tr><th>Contiene spazi</th><td><?php echo $diagnosis_data['token_analysis']['contains_spaces'] ? '❌ SÌ' : '✅ NO'; ?></td></tr>
                        <tr><th>Contiene newline</th><td><?php echo $diagnosis_data['token_analysis']['contains_newlines'] ? '❌ SÌ' : '✅ NO'; ?></td></tr>
                        <tr><th>Inizia con "sl."</th><td><?php echo $diagnosis_data['token_analysis']['dropbox_patterns']['starts_with_sl'] ? '✅ SÌ' : '❌ NO'; ?></td></tr>
                        <tr><th>Lunghezza ragionevole</th><td><?php echo $diagnosis_data['token_analysis']['dropbox_patterns']['reasonable_length'] ? '✅ SÌ' : '❌ NO'; ?></td></tr>
                        <tr><th style="font-weight: bold; color: <?php echo $diagnosis_data['token_analysis']['seems_valid'] ? '#00a32a' : '#dc3232'; ?>;">
                            SEMBRA VALIDO</th>
                            <td style="font-weight: bold; color: <?php echo $diagnosis_data['token_analysis']['seems_valid'] ? '#00a32a' : '#dc3232'; ?>;">
                                <?php echo $diagnosis_data['token_analysis']['seems_valid'] ? '✅ SÌ' : '❌ NO'; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Test Connessione -->
                <?php if (isset($diagnosis_data['token_tests']['summary'])): ?>
                <h4>🧪 Test Connessione API</h4>
                <div style="background: <?php echo $diagnosis_data['token_tests']['summary']['success_count'] > 0 ? '#eafaea' : '#ffeaea'; ?>; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <p><strong>Metodi testati:</strong> <?php echo $diagnosis_data['token_tests']['summary']['total_methods_tested']; ?></p>
                    <p><strong>Metodi funzionanti:</strong> <?php echo $diagnosis_data['token_tests']['summary']['success_count']; ?></p>
                    <p><strong>Token funziona:</strong> <?php echo $diagnosis_data['token_tests']['summary']['token_seems_valid'] ? '✅ SÌ' : '❌ NO'; ?></p>
                    
                    <?php if (!empty($diagnosis_data['token_tests']['summary']['successful_methods'])): ?>
                        <p><strong>Metodi che funzionano:</strong> <?php echo implode(', ', $diagnosis_data['token_tests']['summary']['successful_methods']); ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Raccomandazioni -->
                <?php if (isset($diagnosis_data['recommendations']) && !empty($diagnosis_data['recommendations'])): ?>
                <h4>💡 Raccomandazioni Dettagliate</h4>
                <?php foreach ($diagnosis_data['recommendations'] as $rec): ?>
                    <div style="background: #fff; padding: 15px; margin: 10px 0; border-left: 4px solid <?php echo $rec['priority'] === 'HIGH' ? '#dc3232' : ($rec['priority'] === 'MEDIUM' ? '#ffb900' : '#00a32a'); ?>;">
                        <h5 style="margin: 0 0 10px 0; color: <?php echo $rec['priority'] === 'HIGH' ? '#dc3232' : ($rec['priority'] === 'MEDIUM' ? '#ffb900' : '#00a32a'); ?>;">
                            <?php echo esc_html($rec['priority']); ?>: <?php echo esc_html($rec['issue']); ?>
                        </h5>
                        <p><strong>Soluzione:</strong> <?php echo esc_html($rec['solution']); ?></p>
                        <p><strong>Azione:</strong> <code><?php echo esc_html($rec['action']); ?></code></p>
                    </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <details style="margin-top: 20px;">
                    <summary style="cursor: pointer; font-weight: bold;">📋 Dati Completi (clicca per espandere)</summary>
                    <pre style="background: #f0f0f0; padding: 15px; margin: 10px 0; font-size: 11px; overflow: auto; max-height: 500px;"><?php echo esc_html(json_encode($diagnosis_data, JSON_PRETTY_PRINT)); ?></pre>
                </details>
            </div>
            <?php 
            delete_transient('naval_egt_dropbox_diagnosis');
            endif; ?>
            
            <!-- Stato configurazione -->
            <div class="card">
                <h2>Stato Configurazione Dettagliato</h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Elemento</th>
                            <th>Proprietà Classe</th>
                            <th>Database</th>
                            <th>Stato</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>App Key</strong></td>
                            <td><?php echo $debug_info['property_values']['app_key'] ? '✅ SET' : '❌ EMPTY'; ?></td>
                            <td><?php echo $debug_info['database_values']['app_key'] ? '✅ SET' : '❌ EMPTY'; ?></td>
                            <td><?php echo $debug_info['property_values']['app_key'] && $debug_info['database_values']['app_key'] ? '✅ OK' : '⚠️ PROBLEM'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>App Secret</strong></td>
                            <td><?php echo $debug_info['property_values']['app_secret'] ? '✅ SET' : '❌ EMPTY'; ?></td>
                            <td><?php echo $debug_info['database_values']['app_secret'] ? '✅ SET' : '❌ EMPTY'; ?></td>
                            <td><?php echo $debug_info['property_values']['app_secret'] && $debug_info['database_values']['app_secret'] ? '✅ OK' : '⚠️ PROBLEM'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Access Token</strong></td>
                            <td><?php echo $debug_info['property_values']['access_token'] ? '✅ SET' : '❌ EMPTY'; ?></td>
                            <td><?php echo $debug_info['database_values']['access_token'] ? '✅ SET' : '❌ EMPTY'; ?></td>
                            <td><?php echo $debug_info['database_values']['access_token'] ? '✅ OK' : '❌ MISSING'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Refresh Token</strong></td>
                            <td><?php echo $debug_info['property_values']['refresh_token'] ? '✅ SET' : '❌ EMPTY'; ?></td>
                            <td><?php echo $debug_info['database_values']['refresh_token'] ? '✅ SET' : '❌ EMPTY'; ?></td>
                            <td><?php echo $debug_info['database_values']['refresh_token'] ? '✅ OK' : '⚠️ OPTIONAL'; ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <h3>Riassunto Configurazione</h3>
                <p style="font-size: 18px; font-weight: bold;">
                    Stato Finale: 
                    <?php if ($is_configured): ?>
                        <span style="color: green;">✅ CONFIGURATO</span>
                    <?php else: ?>
                        <span style="color: red;">❌ NON CONFIGURATO</span>
                    <?php endif; ?>
                </p>
                
                <?php if (isset($debug_info['values_preview'])): ?>
                <h3>Anteprima Valori</h3>
                <ul>
                    <li><strong>Access Token (Proprietà):</strong> <code><?php echo esc_html($debug_info['values_preview']['property_access_token']); ?></code></li>
                    <li><strong>Access Token (Database):</strong> <code><?php echo esc_html($debug_info['values_preview']['database_access_token']); ?></code></li>
                    <li><strong>Redirect URI:</strong> <code><?php echo esc_html($debug_info['redirect_uri']); ?></code></li>
                </ul>
                <?php endif; ?>
            </div>
            
            <!-- Azioni Debug -->
            <div class="card">
                <h2>Azioni Debug Avanzate</h2>
                <p>Usa questi pulsanti per diagnosticare e risolvere problemi complessi:</p>
                
                <!-- Riga 1: Test e Analisi -->
                <div style="margin-bottom: 15px;">
                    <form method="post" style="display: inline-block; margin-right: 10px;">
                        <?php wp_nonce_field('naval_egt_debug_diagnosis'); ?>
                        <input type="submit" name="run_full_diagnosis" class="button button-primary" value="🔍 Diagnosi Completa Avanzata" />
                    </form>
                    
                    <form method="post" style="display: inline-block; margin-right: 10px;">
                        <?php wp_nonce_field('naval_egt_debug_test'); ?>
                        <input type="submit" name="test_configuration" class="button" value="🧪 Test Configurazione Veloce" />
                    </form>
                    
                    <form method="post" style="display: inline-block; margin-right: 10px;">
                        <?php wp_nonce_field('naval_egt_debug_reload'); ?>
                        <input type="submit" name="reload_credentials" class="button" value="🔄 Ricarica Credenziali" />
                    </form>
                </div>

                <!-- Riga 2: Azioni Critiche -->
                <div style="margin-bottom: 15px;">
                    <form method="post" style="display: inline-block; margin-right: 10px;">
                        <?php wp_nonce_field('naval_egt_debug_regen'); ?>
                        <input type="submit" name="force_token_regeneration" 
                               class="button" 
                               style="background: #dc3232; border-color: #dc3232; color: white;" 
                               value="🔄 Rigenera Token Completamente" 
                               onclick="return confirm('⚠️ ATTENZIONE: Questo cancellerà completamente il token corrente.\n\nDovrai riautorizzare l\'applicazione su Dropbox.\n\nUsa questo solo se il token è definitivamente corrotto.\n\nProcedere con la rigenerazione?');" />
                    </form>
                    
                    <form method="post" style="display: inline-block;">
                        <?php wp_nonce_field('naval_egt_debug_clear'); ?>
                        <input type="submit" name="clear_debug_logs" class="button button-secondary" value="🗑️ Pulisci Log Debug" 
                               onclick="return confirm('Vuoi pulire tutti i log di debug?');" />
                    </form>
                </div>

                <!-- Riga 3: Link Veloci-->
                <div>
                    <a href="<?php echo admin_url('admin.php?page=naval-egt&tab=dropbox'); ?>" class="button">
                        ↩️ Torna a Configurazione Dropbox
                    </a>
                </div>
                
                <?php if (!$is_configured && $debug_info['database_values']['app_key'] && $debug_info['database_values']['app_secret']): ?>
                <hr style="margin: 20px 0;">
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px;">
                    <h4>🔐 Riprova Autorizzazione Manuale</h4>
                    <p>Se i test automatici falliscono, prova l'autorizzazione manuale:</p>
                    <?php
                    $auth_url = $dropbox->get_authorization_url();
                    if ($auth_url):
                    ?>
                    <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary button-large" target="_blank">
                        ↗️ Autorizza Manualmente su Dropbox
                    </a>
                    <p><small>Questo aprirà una nuova finestra/tab per l'autorizzazione.</small></p>
                    <?php else: ?>
                    <p style="color: red;">❌ Impossibile generare URL di autorizzazione. Verifica le credenziali.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Log Debug -->
            <div class="card">
                <h2>Log Debug (Ultimi <?php echo count($debug_logs); ?> eventi)</h2>
                <?php if (empty($debug_logs)): ?>
                    <p><em>Nessun log di debug disponibile. Prova a eseguire un'azione di debug per generare log.</em></p>
                <?php else: ?>
                    <div style="max-height: 500px; overflow-y: auto; border: 1px solid #ccc; padding: 15px; background: #f9f9f9;">
                        <?php foreach (array_reverse($debug_logs) as $i => $log): ?>
                            <div style="margin-bottom: 15px; padding: 12px; background: white; border-left: 4px solid #0073aa; border-radius: 3px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <strong style="color: #0073aa;"><?php echo esc_html($log['timestamp']); ?></strong>
                                    <span style="font-size: 12px; color: #666;">#<?php echo count($debug_logs) - $i; ?></span>
                                </div>
                                <div style="color: #333; margin-bottom: 5px;">
                                    <strong>📝 <?php echo esc_html($log['message']); ?></strong>
                                </div>
                                <?php if ($log['data']): ?>
                                    <details style="margin-top: 8px;">
                                        <summary style="cursor: pointer; color: #0073aa; font-size: 13px;">
                                            📊 Mostra Dati (<?php echo is_array($log['data']) ? count($log['data']) . ' elementi' : 'dettagli'; ?>)
                                        </summary>
                                        <pre style="background: #f0f0f0; padding: 10px; margin: 8px 0; font-size: 11px; overflow-x: auto; border-radius: 3px; max-height: 200px; overflow-y: auto;"><?php echo esc_html(json_encode($log['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                                    </details>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Informazioni Sistema -->
            <div class="card">
                <h2>Informazioni Sistema</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
                    <div>
                        <h4>🖥️ Sistema</h4>
                        <table class="widefat">
                            <tr><th>WordPress</th><td><?php echo get_bloginfo('version'); ?></td></tr>
                            <tr><th>PHP</th><td><?php echo phpversion(); ?></td></tr>
                            <tr><th>SSL</th><td><?php echo is_ssl() ? '✅ Attivo' : '❌ Inattivo'; ?></td></tr>
                            <tr><th>WP Debug</th><td><?php echo (defined('WP_DEBUG') && WP_DEBUG) ? '✅ Attivo' : '❌ Inattivo'; ?></td></tr>
                        </table>
                    </div>
                    <div>
                        <h4>🌐 URLs</h4>
                        <table class="widefat">
                            <tr><th>Site URL</th><td><?php echo get_site_url(); ?></td></tr>
                            <tr><th>Admin URL</th><td><?php echo admin_url(); ?></td></tr>
                            <tr><th>Redirect URI</th><td><?php echo esc_html($debug_info['redirect_uri']); ?></td></tr>
                        </table>
                    </div>
                    <div>
                        <h4>⏰ Tempi</h4>
                        <table class="widefat">
                            <tr><th>Ora Attuale</th><td><?php echo current_time('Y-m-d H:i:s'); ?></td></tr>
                            <tr><th>Timezone</th><td><?php echo wp_timezone_string(); ?></td></tr>
                            <tr><th>UTC</th><td><?php echo gmdate('Y-m-d H:i:s'); ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Guida Risoluzione Problemi -->
            <div class="card">
                <h2>🚨 Guida Risoluzione Problemi Avanzata</h2>
                
                <?php if (!$is_configured): ?>
                <div style="background: #ffebe8; border: 1px solid #c3291b; padding: 20px; margin: 15px 0; border-radius: 4px;">
                    <h3 style="color: #c3291b; margin-top: 0;">❌ Dropbox NON Configurato</h3>
                    
                    <h4>🔍 Diagnostica il Problema:</h4>
                    <ol>
                        <li><strong>Usa "Diagnosi Completa Avanzata"</strong> - Ti darà un report dettagliato del problema</li>
                        <li><strong>Controlla i Log Debug</strong> - Cerca errori specifici nei log qui sopra</li>
                        <li><strong>Verifica URL Redirect</strong> - Deve essere esatto nell'app Dropbox</li>
                        <li><strong>Test Configurazione Veloce</strong> - Per un controllo rapido</li>
                    </ol>
                    
                    <h4>🔧 Risolvi il Problema:</h4>
                    <ol>
                        <li><strong>Se il token è corrotto:</strong> Usa "Rigenera Token Completamente"</li>
                        <li><strong>Se l'autorizzazione fallisce:</strong> Prova "Autorizza Manualmente"</li>
                        <li><strong>Se persistono problemi:</strong> Controlla le credenziali Dropbox App</li>
                    </ol>
                </div>
                <?php else: ?>
                <div style="background: #d5edda; border: 1px solid #198754; padding: 20px; margin: 15px 0; border-radius: 4px;">
                    <h3 style="color: #198754; margin-top: 0;">✅ Dropbox Configurato Correttamente</h3>
                    <p>La configurazione base è corretta. Se riscontri ancora problemi specifici:</p>
                    <ul>
                        <li>Usa "Test Configurazione Veloce" per verifiche puntuali</li>
                        <li>Controlla i Log Debug per errori durante l'uso</li>
                        <li>Usa "Diagnosi Completa" se ci sono problemi intermittenti</li>
                    </ul>
                </div>
                <?php endif; ?>
                
                <div style="background: #e3f2fd; border: 1px solid #1976d2; padding: 20px; margin: 15px 0; border-radius: 4px;">
                    <h3>📋 Checklist Debug Completa</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                        <div>
                            <h4>🔧 Configurazione Dropbox App</h4>
                            <ul>
                                <li>✅ App Key e Secret corretti</li>
                                <li>✅ URL redirect esatto: <code><?php echo esc_html($debug_info['redirect_uri']); ?></code></li>
                                <li>✅ Permessi files.* abilitati</li>
                                <li>✅ App in modalità "Development" o "Production"</li>
                            </ul>
                        </div>
                        <div>
                            <h4>🖥️ Configurazione Server</h4>
                            <ul>
                                <li>✅ HTTPS attivo (richiesto da Dropbox)</li>
                                <li>✅ cURL abilitato e funzionante</li>
                                <li>✅ Connessioni esterne permesse</li>
                                <li>✅ WP_DEBUG attivato per vedere errori</li>
                            </ul>
                        </div>
                        <div>
                            <h4>💾 Database e Token</h4>
                            <ul>
                                <li>✅ Tabelle plugin create correttamente</li>
                                <li>✅ Token salvato senza troncamenti</li>
                                <li>✅ Encoding database corretto (UTF-8)</li>
                                <li>✅ Permessi scrittura database</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 15px 0; border-radius: 4px;">
                    <h4>💡 Suggerimenti Pro</h4>
                    <ul>
                        <li><strong>Token troppo lungo?</strong> Potrebbe essere corrotto durante il salvataggio</li>
                        <li><strong>Pochi caratteri unici?</strong> Il token potrebbe essere stato alterato</li>
                        <li><strong>Test API falliscono?</strong> Problema di rete o token invalido</li>
                        <li><strong>Autorizzazione non funziona?</strong> Controlla URL redirect nell'app Dropbox</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Gestisce le richieste AJAX - AGGIORNATE
     */
    public function handle_ajax_requests() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        $action = isset($_POST['naval_action']) ? sanitize_text_field($_POST['naval_action']) : '';
        
        switch ($action) {
            case 'get_user_stats':
                $this->get_user_stats();
                break;
                
            case 'filter_users':
                $this->filter_users();
                break;
                
            case 'get_user_data':
                $this->get_user_data();
                break;
                
            case 'update_user':
                $this->update_user();
                break;
                
            case 'toggle_user_status':
                $this->toggle_user_status();
                break;
                
            case 'delete_user':
                $this->delete_user();
                break;
                
            case 'bulk_user_action':
                $this->bulk_user_action();
                break;
                
            case 'test_dropbox_connection':
                $this->test_dropbox_connection();
                break;
                
            case 'sync_all_user_folders':
                $this->sync_all_user_folders();
                break;
                
            case 'disconnect_dropbox':
                $this->disconnect_dropbox();
                break;
                
            case 'check_dropbox_status':
                $this->check_dropbox_status();
                break;
                
            case 'admin_upload_files':
                $this->admin_upload_files();
                break;
                
            case 'get_user_folders':
                $this->get_user_folders();
                break;
                
            case 'filter_logs':
                $this->filter_logs();
                break;
                
            case 'clear_logs':
                $this->clear_logs();
                break;
                
            case 'get_users_list':
                $this->get_users_list();
                break;
                
            case 'refresh_stats':
                $this->refresh_stats();
                break;
                
            default:
                wp_send_json_error('Azione non valida');
                break;
        }
        
        wp_die();
    }
    
    /**
     * Ottiene statistiche utenti
     */
    public function get_user_stats() {
        $stats = Naval_EGT_Database::get_user_stats();
        wp_send_json_success($stats);
    }
    
    /**
     * Filtra utenti
     */
    public function filter_users() {
        $search = sanitize_text_field($_POST['search'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? '');
        $page = intval($_POST['page'] ?? 1);
        $per_page = 20;
        
        $users = Naval_EGT_User_Manager::get_users(array(
            'search' => $search,
            'status' => $status,
            'page' => $page,
            'per_page' => $per_page
        ));
        
        $html = '';
        foreach ($users['users'] as $user) {
            $html .= $this->render_user_row($user);
        }
        
        wp_send_json_success(array(
            'html' => $html,
            'pagination' => $users['pagination']
        ));
    }
    
    /**
     * Test connessione Dropbox
     */
    public function test_dropbox_connection() {
        $dropbox = Naval_EGT_Dropbox::get_instance();
        $result = $dropbox->get_account_info();
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => 'Connessione Dropbox OK!',
                'account_name' => isset($result['data']['name']['display_name']) ? $result['data']['name']['display_name'] : '',
                'account_email' => isset($result['data']['email']) ? $result['data']['email'] : ''
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Sincronizza tutte le cartelle utenti
     */
    public function sync_all_user_folders() {
        $dropbox = Naval_EGT_Dropbox::get_instance();
        
        if (!$dropbox->is_configured()) {
            wp_send_json_error('Dropbox non configurato');
        }
        
        $users = Naval_EGT_User_Manager::get_all_users();
        $stats = array(
            'users_processed' => 0,
            'folders_found' => 0,
            'files_synced' => 0,
            'errors' => array()
        );
        
        foreach ($users as $user) {
            $stats['users_processed']++;
            
            $result = $dropbox->sync_user_folder($user['user_code'], $user['id']);
            
            if ($result['success']) {
                $stats['folders_found']++;
                
                // Conta i file sincronizzati
                global $wpdb;
                $file_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}naval_egt_files WHERE user_id = %d",
                    $user['id']
                ));
                $stats['files_synced'] += intval($file_count);
            } else {
                $stats['errors'][] = "Utente {$user['user_code']}: {$result['message']}";
            }
        }
        
        wp_send_json_success(array(
            'message' => 'Sincronizzazione completata',
            'stats' => $stats
        ));
    }
    
    /**
     * Disconnetti Dropbox
     */
    public function disconnect_dropbox() {
        $dropbox = Naval_EGT_Dropbox::get_instance();
        $result = $dropbox->disconnect();
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Controlla stato Dropbox
     */
    public function check_dropbox_status() {
        $dropbox = Naval_EGT_Dropbox::get_instance();
        $status = $dropbox->get_connection_status();
        
        wp_send_json_success($status);
    }
    
    /**
     * Upload file admin
     */
    public function admin_upload_files() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            wp_send_json_error('ID utente non valido');
        }
        
        if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
            wp_send_json_error('Nessun file selezionato');
        }
        
        $user = Naval_EGT_User_Manager::get_user_by_id($user_id);
        if (!$user) {
            wp_send_json_error('Utente non trovato');
        }
        
        // Simula upload tramite public class
        if (!session_id()) {
            session_start();
        }
        $_SESSION['naval_egt_admin_user'] = $user;
        
        $public = Naval_EGT_Public::get_instance();
        $public->upload_user_file();
        
        // Pulisci sessione
        unset($_SESSION['naval_egt_admin_user']);
    }
    
    /**
     * Ottiene cartelle utente
     */
    public function get_user_folders() {
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            wp_send_json_error('ID utente non valido');
        }
        
        $user = Naval_EGT_User_Manager::get_user_by_id($user_id);
        if (!$user) {
            wp_send_json_error('Utente non trovato');
        }
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        $folders_result = $dropbox->find_folder_by_code($user['user_code']);
        
        if ($folders_result['success'] && !empty($folders_result['folders'])) {
            $folders = array();
            foreach ($folders_result['folders'] as $folder) {
                $folders[] = array(
                    'path' => $folder['path_lower'],
                    'name' => basename($folder['name'])
                );
            }
            
            wp_send_json_success(array('folders' => $folders));
        } else {
            wp_send_json_success(array('folders' => array()));
        }
    }
    
    /**
     * Filtra log
     */
    public function filter_logs() {
        $filters = $_POST['filters'] ?? array();
        
        $logs = Naval_EGT_Activity_Logger::get_logs($filters, 50, 0);
        
        $html = '';
        foreach ($logs as $log) {
            $html .= $this->render_log_row($log);
        }
        
        wp_send_json_success(array(
            'html' => $html,
            'pagination' => array()
        ));
    }
    
    /**
     * Pulisci log
     */
    public function clear_logs() {
        Naval_EGT_Activity_Logger::clear_all_logs();
        wp_send_json_success('Log eliminati con successo');
    }
    
    /**
     * Ottiene lista utenti per select
     */
    public function get_users_list() {
        $users = Naval_EGT_User_Manager::get_all_users();
        
        $user_list = array();
        foreach ($users as $user) {
            $user_list[] = array(
                'id' => $user['id'],
                'nome' => $user['nome'],
                'cognome' => $user['cognome'],
                'user_code' => $user['user_code']
            );
        }
        
        wp_send_json_success(array('users' => $user_list));
    }
    
    /**
     * Refresh statistiche
     */
    public function refresh_stats() {
        $stats = Naval_EGT_Database::get_user_stats();
        wp_send_json_success($stats);
    }
    
    /**
     * Gestisce richieste di export
     */
    public function handle_export_requests() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        $export_type = sanitize_text_field($_POST['export_type'] ?? '');
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        
        if ($export_type === 'users') {
            Naval_EGT_Export::export_users($format, $_POST);
        } elseif ($export_type === 'logs') {
            Naval_EGT_Export::export_logs($format, $_POST);
        } else {
            wp_die('Tipo export non valido');
        }
    }
    
    /**
     * Renderizza riga utente
     */
    private function render_user_row($user) {
        $status_colors = array(
            'ATTIVO' => 'green',
            'SOSPESO' => 'orange',
            'ELIMINATO' => 'red'
        );
        
        $status_color = $status_colors[$user['status']] ?? 'gray';
        
        return sprintf(
            '<tr>
                <td><strong>%s</strong></td>
                <td>%s %s</td>
                <td>%s</td>
                <td><span style="color: %s;">%s</span></td>
                <td>
                    <button class="button button-small" onclick="editUser(%d)">✏️ Modifica</button>
                    <button class="button button-small" onclick="toggleUserStatus(%d, \'%s\')">%s</button>
                    <button class="button button-small button-link-delete" onclick="deleteUser(%d)">🗑️ Elimina</button>
                </td>
            </tr>',
            esc_html($user['user_code']),
            esc_html($user['nome']),
            esc_html($user['cognome']),
            esc_html($user['email']),
            $status_color,
            esc_html($user['status']),
            $user['id'],
            $user['id'],
            esc_attr($user['status']),
            $user['status'] === 'ATTIVO' ? '⏸️ Sospendi' : '▶️ Attiva',
            $user['id']
        );
    }
    
    /**
     * Renderizza riga log
     */
    private function render_log_row($log) {
        $action_icons = array(
            'LOGIN' => '🔑',
            'LOGOUT' => '🚪',
            'UPLOAD' => '⬆️',
            'DOWNLOAD' => '⬇️',
            'DELETE' => '🗑️',
            'REGISTRATION' => '📝'
        );
        
        $icon = $action_icons[$log['action']] ?? '📋';
        
        return sprintf(
            '<tr>
                <td>%s</td>
                <td><strong>%s</strong></td>
                <td>%s %s</td>
                <td>%s</td>
                <td><code>%s</code></td>
            </tr>',
            mysql2date('d/m/Y H:i', $log['created_at']),
            esc_html($log['user_code']),
            $icon,
            esc_html($log['action']),
            esc_html($log['file_name'] ?? '-'),
            esc_html($log['ip_address'] ?? '-')
        );
    }
    
    /**
     * Ottiene dati utente per modifica
     */
    public function get_user_data() {
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            wp_send_json_error('ID utente non valido');
        }
        
        $user = Naval_EGT_User_Manager::get_user_by_id($user_id);
        
        if ($user) {
            wp_send_json_success($user);
        } else {
            wp_send_json_error('Utente non trovato');
        }
    }
    
    /**
     * Aggiorna utente
     */
    public function update_user() {
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            wp_send_json_error('ID utente non valido');
        }
        
        $data = array(
            'nome' => sanitize_text_field($_POST['nome'] ?? ''),
            'cognome' => sanitize_text_field($_POST['cognome'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'telefono' => sanitize_text_field($_POST['telefono'] ?? ''),
            'ragione_sociale' => sanitize_text_field($_POST['ragione_sociale'] ?? ''),
            'partita_iva' => sanitize_text_field($_POST['partita_iva'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? 'SOSPESO')
        );
        
        // Validazioni
        if (empty($data['nome']) || empty($data['cognome']) || empty($data['email'])) {
            wp_send_json_error('Nome, cognome e email sono obbligatori');
        }
        
        if (!is_email($data['email'])) {
            wp_send_json_error('Formato email non valido');
        }
        
        $result = Naval_EGT_User_Manager::update_user($user_id, $data);
        
        if ($result) {
            // Log modifica
            $user = Naval_EGT_User_Manager::get_user_by_id($user_id);
            Naval_EGT_Activity_Logger::log_activity(
                $user_id,
                $user['user_code'],
                'USER_UPDATE',
                null,
                null,
                0,
                array('updated_by' => 'admin', 'fields' => array_keys($data))
            );
            
            wp_send_json_success('Utente aggiornato con successo');
        } else {
            wp_send_json_error('Errore nell\'aggiornamento dell\'utente');
        }
    }
    
    /**
     * Cambia stato utente
     */
    public function toggle_user_status() {
        $user_id = intval($_POST['user_id'] ?? 0);
        $current_status = sanitize_text_field($_POST['current_status'] ?? '');
        
        if (!$user_id) {
            wp_send_json_error('ID utente non valido');
        }
        
        $new_status = ($current_status === 'ATTIVO') ? 'SOSPESO' : 'ATTIVO';
        
        $result = Naval_EGT_User_Manager::update_user($user_id, array('status' => $new_status));
        
        if ($result) {
            // Log cambio stato
            $user = Naval_EGT_User_Manager::get_user_by_id($user_id);
            Naval_EGT_Activity_Logger::log_activity(
                $user_id,
                $user['user_code'],
                'STATUS_CHANGE',
                null,
                null,
                0,
                array('from' => $current_status, 'to' => $new_status, 'changed_by' => 'admin')
            );
            
            wp_send_json_success(array(
                'message' => 'Stato utente cambiato con successo',
                'new_status' => $new_status
            ));
        } else {
            wp_send_json_error('Errore nel cambio stato utente');
        }
    }
    
    /**
     * Elimina utente
     */
    public function delete_user() {
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            wp_send_json_error('ID utente non valido');
        }
        
        $user = Naval_EGT_User_Manager::get_user_by_id($user_id);
        if (!$user) {
            wp_send_json_error('Utente non trovato');
        }
        
        // Log eliminazione prima di eliminare
        Naval_EGT_Activity_Logger::log_activity(
            $user_id,
            $user['user_code'],
            'USER_DELETE',
            null,
            null,
            0,
            array('deleted_by' => 'admin', 'user_data' => $user)
        );
        
        $result = Naval_EGT_User_Manager::delete_user($user_id);
        
        if ($result) {
            wp_send_json_success('Utente eliminato con successo');
        } else {
            wp_send_json_error('Errore nell\'eliminazione dell\'utente');
        }
    }
    
    /**
     * Azioni bulk per utenti
     */
    public function bulk_user_action() {
        $action = sanitize_text_field($_POST['bulk_action'] ?? '');
        $user_ids = array_map('intval', $_POST['user_ids'] ?? array());
        
        if (empty($action) || empty($user_ids)) {
            wp_send_json_error('Azione o utenti non specificati');
        }
        
        $processed = 0;
        $errors = array();
        
        foreach ($user_ids as $user_id) {
            $user = Naval_EGT_User_Manager::get_user_by_id($user_id);
            if (!$user) {
                $errors[] = "Utente ID $user_id non trovato";
                continue;
            }
            
            switch ($action) {
                case 'activate':
                    $result = Naval_EGT_User_Manager::update_user($user_id, array('status' => 'ATTIVO'));
                    if ($result) {
                        $processed++;
                        Naval_EGT_Activity_Logger::log_activity(
                            $user_id, $user['user_code'], 'BULK_ACTIVATE', null, null, 0,
                            array('bulk_action' => true, 'processed_by' => 'admin')
                        );
                    } else {
                        $errors[] = "Errore attivazione utente {$user['user_code']}";
                    }
                    break;
                    
                case 'suspend':
                    $result = Naval_EGT_User_Manager::update_user($user_id, array('status' => 'SOSPESO'));
                    if ($result) {
                        $processed++;
                        Naval_EGT_Activity_Logger::log_activity(
                            $user_id, $user['user_code'], 'BULK_SUSPEND', null, null, 0,
                            array('bulk_action' => true, 'processed_by' => 'admin')
                        );
                    } else {
                        $errors[] = "Errore sospensione utente {$user['user_code']}";
                    }
                    break;
                    
                case 'delete':
                    Naval_EGT_Activity_Logger::log_activity(
                        $user_id, $user['user_code'], 'BULK_DELETE', null, null, 0,
                        array('bulk_action' => true, 'deleted_by' => 'admin', 'user_data' => $user)
                    );
                    
                    $result = Naval_EGT_User_Manager::delete_user($user_id);
                    if ($result) {
                        $processed++;
                    } else {
                        $errors[] = "Errore eliminazione utente {$user['user_code']}";
                    }
                    break;
                    
                default:
                    $errors[] = "Azione non valida: $action";
            }
        }
        
        $message = "Elaborati $processed utenti su " . count($user_ids);
        if (!empty($errors)) {
            $message .= ". Errori: " . implode(', ', $errors);
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'processed' => $processed,
            'errors' => $errors
        ));
    }
}

?>