<?php
/**
 * Classe per la gestione dell'area admin - Versione completa con debug avanzato
 * Integrata con i nuovi metodi di diagnosi Dropbox
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

        // AGGIORNATO: Gestione azioni debug Dropbox
        if (isset($_GET['page']) && $_GET['page'] === 'naval-egt' && 
            isset($_GET['tab']) && $_GET['tab'] === 'dropbox') {
            $this->handle_dropbox_debug_actions();
        }
    }

    /**
     * AGGIORNATO: Gestisce le azioni di debug Dropbox con i nuovi metodi
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

            case 'test_app_credentials':
                $cred_test = $dropbox->test_app_credentials();
                if ($cred_test['success']) {
                    $this->add_admin_notice('✅ Test credenziali app: ' . $cred_test['message'], 'success');
                } else {
                    $this->add_admin_notice('❌ Test credenziali app fallito: ' . $cred_test['message'], 'error');
                }
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

            case 'debug_400_error':
                // Se c'è un codice nella sessione o nei parametri
                $code = isset($_GET['test_code']) ? sanitize_text_field($_GET['test_code']) : '';
                if (!empty($code)) {
                    $debug_result = $dropbox->debug_400_error($code);
                    $this->set_debug_400_data($debug_result);
                    $this->add_admin_notice('Debug errore HTTP 400 completato. Controlla i risultati.', 'info');
                } else {
                    $this->add_admin_notice('Codice di test non fornito per debug 400.', 'warning');
                }
                break;

            case 'reload_credentials':
                $dropbox->reload_credentials();
                $this->add_admin_notice('Credenziali ricaricate dal database.', 'info');
                break;

            case 'clear_debug_logs':
                $dropbox->clear_debug_logs();
                $this->add_admin_notice('Log di debug puliti.', 'success');
                break;

            case 'export_debug_info':
                $debug_info = $dropbox->export_debug_info();
                $this->set_debug_export_data($debug_info);
                $this->add_admin_notice('Informazioni debug esportate. Controlla qui sotto.', 'info');
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
     * NUOVO: Salva i dati del debug 400
     */
    private function set_debug_400_data($debug_result) {
        set_transient('naval_egt_debug_400', $debug_result, 300); // 5 minuti
    }

    /**
     * NUOVO: Salva i dati di export debug
     */
    private function set_debug_export_data($debug_info) {
        set_transient('naval_egt_debug_export', $debug_info, 300); // 5 minuti
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
            
            // NUOVO: Se fallisce, salva i dati per il debug
            if (isset($result['debug_info'])) {
                $this->set_debug_callback_data($result);
            }
        }
    }

    /**
     * NUOVO: Salva i dati del callback per debug
     */
    private function set_debug_callback_data($callback_result) {
        set_transient('naval_egt_callback_debug', $callback_result, 300); // 5 minuti
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
                    <div style="margin-top: 15px;">
                        <a href="?page=naval-egt&tab=dropbox" class="button button-primary">Configura Dropbox</a>
                        <a href="?page=naval-egt&tab=dropbox-debug" class="button button-secondary" style="margin-left: 10px;">🔍 Debug Problemi</a>
                    </div>
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
                        <button class="button" onclick="testDropboxQuick()">🧪 Test Dropbox Veloce</button>
                    <?php else: ?>
                        <button class="button" onclick="diagnoseDropbox()" style="background: #dc3232; border-color: #dc3232; color: white;">🔍 Diagnosi Dropbox</button>
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
            max-width: 100% !important; 
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

        function testDropboxQuick() {
            jQuery.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'test_dropbox_connection',
                nonce: '<?php echo wp_create_nonce('naval_egt_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('✅ Test Dropbox OK!\n\nConnesso come: ' + (response.data.account_email || 'Account Dropbox'));
                } else {
                    alert('❌ Test Dropbox fallito!\n\nErrore: ' + response.data);
                }
            });
        }

        function diagnoseDropbox() {
            if (confirm('Vuoi eseguire una diagnosi completa di Dropbox?\n\nQuesta operazione analizzerà la configurazione e identificherà eventuali problemi.')) {
                window.location.href = '<?php echo admin_url('admin.php?page=naval-egt&tab=dropbox-debug&auto_diagnose=1'); ?>';
            }
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
     * Renderizza pagina impostazioni Dropbox - AGGIORNATA CON TUTTE LE NUOVE FUNZIONALITÀ
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

        // AGGIORNATO: Recupera tutti i dati debug se disponibili
        $diagnosis_data = get_transient('naval_egt_dropbox_diagnosis');
        $auth_url_display = get_transient('naval_egt_dropbox_auth_url');
        $token_analysis = get_transient('naval_egt_token_analysis');
        $multiple_tests = get_transient('naval_egt_multiple_tests');
        $debug_400 = get_transient('naval_egt_debug_400');
        $callback_debug = get_transient('naval_egt_callback_debug');
        
        ?>
        <div class="wrap">
            <h2>☁️ Configurazione Dropbox</h2>
            
            <!-- AGGIORNATO: Strumenti di Debug Integrati con tutti i nuovi metodi -->
            <div class="card" style="border-left: 4px solid #dc3232;">
                <h3>🔧 Strumenti di Debug e Risoluzione Problemi Avanzati</h3>
                <p><strong>Se Dropbox non funziona correttamente, usa questi strumenti per diagnosticare e risolvere i problemi:</strong></p>
                
                <div style="margin: 15px 0;">
                    <?php
                    $debug_base_url = admin_url('admin.php?page=naval-egt&tab=dropbox');
                    $nonce = wp_create_nonce('naval_egt_dropbox_debug');
                    ?>
                    
                    <!-- Riga 1: Diagnosi Principale -->
                    <div style="margin-bottom: 10px;">
                        <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=test_diagnosis&_wpnonce=' . $nonce); ?>" 
                           class="button button-primary" style="margin-right: 10px;">
                            🔍 Diagnosi Completa
                        </a>
                        
                        <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=test_app_credentials&_wpnonce=' . $nonce); ?>" 
                           class="button" style="margin-right: 10px;">
                            🔑 Test Credenziali App
                        </a>
                        
                        <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=test_connection&_wpnonce=' . $nonce); ?>" 
                           class="button" style="margin-right: 10px;">
                            📡 Test Connessione
                        </a>
                    </div>
                    
                    <!-- Riga 2: Analisi Token -->
                    <div style="margin-bottom: 10px;">
                        <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=analyze_token&_wpnonce=' . $nonce); ?>" 
                           class="button" style="margin-right: 10px;">
                            🔑 Analizza Token
                        </a>
                        
                        <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=test_multiple_methods&_wpnonce=' . $nonce); ?>" 
                           class="button" style="margin-right: 10px;">
                            🧪 Test Metodi Multipli
                        </a>
                        
                        <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=export_debug_info&_wpnonce=' . $nonce); ?>" 
                           class="button" style="margin-right: 10px;">
                            📊 Esporta Info Debug
                        </a>
                    </div>
                    
                    <!-- Riga 3: Azioni Avanzate -->
                    <div style="margin-bottom: 10px;">
                        <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=reload_credentials&_wpnonce=' . $nonce); ?>" 
                           class="button" style="margin-right: 10px;">
                            🔄 Ricarica Credenziali
                        </a>
                        
                        <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=clear_debug_logs&_wpnonce=' . $nonce); ?>" 
                           class="button" style="margin-right: 10px;">
                            🗑️ Pulisci Log Debug
                        </a>
                        
                        <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=regenerate_token&_wpnonce=' . $nonce); ?>" 
                           class="button" style="background: #dc3232; border-color: #dc3232; color: white; margin-right: 10px;"
                           onclick="return confirm('⚠️ ATTENZIONE: Questo cancellerà il token corrente e dovrai riautorizzare Dropbox.\n\nUsa questo solo se il token attuale è corrotto.\n\nProcedere?');">
                            🔄 Rigenera Token
                        </a>
                    </div>
                </div>
                
                <p><small><strong>💡 Suggerimento:</strong> Se Dropbox non funziona, prova prima "Diagnosi Completa" per capire il problema, poi "Rigenera Token" se necessario.</small></p>
            </div>

            <!-- NUOVO: Mostra debug callback se fallito -->
            <?php if ($callback_debug && !$callback_debug['success']): ?>
            <div class="card" style="border-left: 4px solid #dc3232; background: #ffeaea;">
                <h3>❌ Errore nel Callback di Autorizzazione</h3>
                <p><strong>Il callback di autorizzazione Dropbox è fallito con il seguente errore:</strong></p>
                <div style="background: #fff; padding: 15px; margin: 10px 0; border: 1px solid #dc3232; border-radius: 4px;">
                    <p><strong>Messaggio:</strong> <?php echo esc_html($callback_debug['message']); ?></p>
                    
                    <?php if (isset($callback_debug['debug_info'])): ?>
                        <details style="margin-top: 10px;">
                            <summary style="cursor: pointer; font-weight: bold;">📋 Dettagli Debug</summary>
                            <pre style="background: #f0f0f0; padding: 10px; margin: 10px 0; font-size: 11px; overflow: auto; max-height: 300px;"><?php echo esc_html(json_encode($callback_debug['debug_info'], JSON_PRETTY_PRINT)); ?></pre>
                        </details>
                    <?php endif; ?>
                </div>
                <p><strong>Cosa fare:</strong></p>
                <ol>
                    <li>Usa "Diagnosi Completa" per identificare il problema specifico</li>
                    <li>Se il token è corrotto, usa "Rigenera Token"</li>
                    <li>Verifica che l'URL di redirect sia corretto nell'app Dropbox</li>
                </ol>
            </div>
            <?php 
            delete_transient('naval_egt_callback_debug');
            endif; ?>

            <!-- NUOVO: Mostra debug 400 se disponibile -->
            <?php if ($debug_400): ?>
            <div class="card" style="border-left: 4px solid #ffb900;">
                <h3>🔍 Debug Errore HTTP 400</h3>
                
                <?php if ($debug_400['success']): ?>
                    <div style="background: #eafaea; padding: 15px; border-radius: 4px;">
                        <p><strong>✅ Il debug del token exchange è riuscito!</strong></p>
                        <p>HTTP Code: <?php echo $debug_400['http_code']; ?></p>
                    </div>
                <?php else: ?>
                    <div style="background: #ffeaea; padding: 15px; border-radius: 4px;">
                        <p><strong>❌ Debug HTTP 400:</strong> <?php echo esc_html($debug_400['message']); ?></p>
                        <p>HTTP Code: <?php echo $debug_400['http_code'] ?? 'N/A'; ?></p>
                        
                        <?php if (isset($debug_400['error_details'])): ?>
                            <details style="margin-top: 10px;">
                                <summary style="cursor: pointer;">📋 Dettagli Errore Dropbox</summary>
                                <pre style="background: #f0f0f0; padding: 10px; margin: 10px 0; font-size: 11px;"><?php echo esc_html(json_encode($debug_400['error_details'], JSON_PRETTY_PRINT)); ?></pre>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php 
            delete_transient('naval_egt_debug_400');
            endif; ?>

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
            <?php 
            delete_transient('naval_egt_dropbox_auth_url');
            endif; ?>

            <!-- AGGIORNATO: Mostra risultati diagnosi -->
            <?php if ($diagnosis_data): ?>
            <div class="card" style="border-left: 4px solid #0073aa;">
                <h3>📊 Risultati Diagnosi Completa</h3>
                
                <!-- Test Credenziali App -->
                <?php if (isset($diagnosis_data['app_credentials_test'])): ?>
                    <div style="background: <?php echo $diagnosis_data['app_credentials_test']['success'] ? '#eafaea' : '#ffeaea'; ?>; padding: 15px; margin: 10px 0; border-radius: 4px;">
                        <h4 style="color: <?php echo $diagnosis_data['app_credentials_test']['success'] ? '#00a32a' : '#dc3232'; ?>;">
                            <?php echo $diagnosis_data['app_credentials_test']['success'] ? '✅ Credenziali App Valide' : '❌ Credenziali App Non Valide'; ?>
                        </h4>
                        <p><?php echo esc_html($diagnosis_data['app_credentials_test']['message']); ?></p>
                    </div>
                <?php endif; ?>
                
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
                        
                        <?php if (!empty($diagnosis_data['token_tests']['summary']['successful_methods'])): ?>
                            <p><strong>Metodi funzionanti:</strong> <?php echo implode(', ', $diagnosis_data['token_tests']['summary']['successful_methods']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($diagnosis_data['recommendations']) && !empty($diagnosis_data['recommendations'])): ?>
                    <h4>💡 Raccomandazioni:</h4>
                    <?php foreach ($diagnosis_data['recommendations'] as $rec): ?>
                        <div style="background: #fff; padding: 10px; margin: 5px 0; border-left: 4px solid <?php echo $rec['priority'] === 'CRITICAL' ? '#dc3232' : ($rec['priority'] === 'HIGH' ? '#dc3232' : ($rec['priority'] === 'MEDIUM' ? '#ffb900' : '#00a32a')); ?>;">
                            <p><strong><?php echo esc_html($rec['priority']); ?>:</strong> <?php echo esc_html($rec['issue']); ?></p>
                            <p><em><?php echo esc_html($rec['solution']); ?></em></p>
                            <p><small><strong>Azione:</strong> <code><?php echo esc_html($rec['action']); ?></code></small></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <p><small><a href="?page=naval-egt&tab=dropbox-debug">🔍 Vai al Debug Avanzato per maggiori dettagli</a></small></p>
            </div>
            <?php 
            // Pulisci il transient dopo la visualizzazione
            delete_transient('naval_egt_dropbox_diagnosis');
            endif; ?>

            <!-- AGGIORNATO: Mostra analisi token -->
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
                        <tr><th>Contiene newline</th><td><?php echo $token_analysis['contains_newlines'] ? '❌ SÌ' : '✅ NO'; ?></td></tr>
                        <tr><th>Sembra valido</th><td><?php echo $token_analysis['seems_valid'] ? '✅ SÌ' : '❌ NO'; ?></td></tr>
                        <tr><th>Inizia con "sl."</th><td><?php echo $token_analysis['dropbox_patterns']['starts_with_sl'] ? '✅ SÌ' : '❌ NO'; ?></td></tr>
                        <tr><th>Ha underscores</th><td><?php echo $token_analysis['dropbox_patterns']['has_underscores'] ? '✅ SÌ' : '❌ NO'; ?></td></tr>
                        <tr><th>Lunghezza ragionevole</th><td><?php echo $token_analysis['dropbox_patterns']['reasonable_length'] ? '✅ SÌ' : '❌ NO'; ?></td></tr>
                    </table>
                <?php endif; ?>
            </div>
            <?php 
            delete_transient('naval_egt_token_analysis');
            endif; ?>

            <!-- AGGIORNATO: Mostra test multipli -->
            <?php if ($multiple_tests): ?>
            <div class="card" style="border-left: 4px solid #7c3aed;">
                <h3>🧪 Risultati Test Multipli</h3>
                
                <?php if (isset($multiple_tests['error'])): ?>
                    <p style="color: #dc3232;"><strong>❌ <?php echo esc_html($multiple_tests['error']); ?></strong></p>
                <?php else: ?>
                    <table class="widefat">
                        <thead>
                            <tr><th>Metodo</th><th>Stato</th><th>Codice HTTP</th><th>Errore</th><th>Risposta</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($multiple_tests as $method => $result): ?>
                                <?php if ($method === 'summary') continue; ?>
                                <tr>
                                    <td><strong><?php echo esc_html($method); ?></strong></td>
                                    <td><?php echo $result['success'] ? '✅ OK' : '❌ FAIL'; ?></td>
                                    <td><?php echo $result['http_code'] ?? 'N/A'; ?></td>
                                    <td><?php echo esc_html($result['curl_error'] ?? $result['wp_error'] ?? '-'); ?></td>
                                    <td><?php echo esc_html(substr($result['response_preview'] ?? '', 0, 50)); ?><?php echo strlen($result['response_preview'] ?? '') > 50 ? '...' : ''; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (isset($multiple_tests['summary'])): ?>
                        <div style="background: <?php echo $multiple_tests['summary']['success_count'] > 0 ? '#eafaea' : '#ffeaea'; ?>; padding: 15px; margin: 10px 0; border-radius: 4px;">
                            <p><strong>Riepilogo:</strong> <?php echo $multiple_tests['summary']['success_count']; ?>/<?php echo $multiple_tests['summary']['total_methods_tested']; ?> metodi funzionanti</p>
                            <p><strong>Token sembra valido:</strong> <?php echo $multiple_tests['summary']['token_seems_valid'] ? '✅ SÌ' : '❌ NO'; ?></p>
                            
                            <?php if (!empty($multiple_tests['summary']['successful_methods'])): ?>
                                <p><strong>Metodi che funzionano:</strong> <?php echo implode(', ', $multiple_tests['summary']['successful_methods']); ?></p>
                            <?php endif; ?>
                        </div>
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
     * Renderizza pagina debug Dropbox - COMPLETAMENTE AGGIORNATA
     */
    private function render_dropbox_debug() {
        $dropbox = Naval_EGT_Dropbox::get_instance();
        
        // AGGIORNATO: Auto-diagnosi se richiesta
        if (isset($_GET['auto_diagnose']) && $_GET['auto_diagnose'] === '1') {
            $diagnosis = $dropbox->full_system_diagnosis();
            $this->set_dropbox_diagnosis_data($diagnosis);
            $this->add_admin_notice('Diagnosi automatica eseguita. Controlla i risultati qui sotto.', 'info');
        }
        
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

        // AGGIORNATO: Gestione nuove azioni debug POST
        if (isset($_POST['run_full_diagnosis'])) {
            check_admin_referer('naval_egt_debug_diagnosis');
            $diagnosis = $dropbox->full_system_diagnosis();
            $this->set_dropbox_diagnosis_data($diagnosis);
            $this->add_admin_notice('Diagnosi completa eseguita. Controlla i risultati qui sotto.', 'info');
        }

        if (isset($_POST['test_app_credentials'])) {
            check_admin_referer('naval_egt_debug_cred');
            $cred_test = $dropbox->test_app_credentials();
            if ($cred_test['success']) {
                $this->add_admin_notice('✅ Test credenziali app: ' . $cred_test['message'], 'success');
            } else {
                $this->add_admin_notice('❌ Test credenziali app fallito: ' . $cred_test['message'], 'error');
            }
        }

        if (isset($_POST['analyze_token_detailed'])) {
            check_admin_referer('naval_egt_debug_token');
            $analysis = $dropbox->analyze_token_detailed();
            $this->set_token_analysis_data($analysis);
            $this->add_admin_notice('Analisi token dettagliata completata.', 'info');
        }

        if (isset($_POST['test_multiple_methods'])) {
            check_admin_referer('naval_egt_debug_multi');
            $tests = $dropbox->test_token_multiple_methods();
            $this->set_multiple_tests_data($tests);
            $this->add_admin_notice('Test multipli completati.', 'info');
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

        if (isset($_POST['export_debug_complete'])) {
            check_admin_referer('naval_egt_debug_export');
            $debug_info = $dropbox->export_debug_info();
            $this->set_debug_export_data($debug_info);
            $this->add_admin_notice('Informazioni debug esportate.', 'info');
        }
        
        // Ottieni informazioni debug
        $debug_info = $dropbox->debug_configuration();
        $debug_logs = $dropbox->get_debug_logs();
        $is_configured = $dropbox->is_configured();

        // AGGIORNATO: Recupera tutti i dati diagnosi se disponibili
        $diagnosis_data = get_transient('naval_egt_dropbox_diagnosis');
        $auth_url_display = get_transient('naval_egt_dropbox_auth_url');
        $token_analysis = get_transient('naval_egt_token_analysis');
        $multiple_tests = get_transient('naval_egt_multiple_tests');
        $debug_export = get_transient('naval_egt_debug_export');
        
        ?>
        <div class="wrap">
            <h1>🔍 Debug Dropbox Avanzato - Naval EGT</h1>

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

            <!-- AGGIORNATO: Export debug se disponibile -->
            <?php if ($debug_export): ?>
            <div class="card" style="border-left: 4px solid #9c27b0;">
                <h3>📊 Esportazione Debug Completa</h3>
                <p><strong>Informazioni complete del sistema esportate per l'analisi:</strong></p>
                
                <details style="margin: 15px 0;">
                    <summary style="cursor: pointer; font-weight: bold; font-size: 16px;">📋 Dati Completi Sistema (clicca per espandere)</summary>
                    <div style="background: #f0f0f0; padding: 20px; margin: 10px 0; border-radius: 4px; max-height: 600px; overflow: auto;">
                        <pre style="white-space: pre-wrap; font-size: 11px; line-height: 1.4;"><?php echo esc_html(json_encode($debug_export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                    </div>
                </details>
                
                <p><strong>Come usare questi dati:</strong></p>
                <ul>
                    <li>Copia il contenuto e invialo al supporto tecnico</li>
                    <li>Cerca sezioni specifiche come "token_analysis" o "recommendations"</li>
                    <li>Controlla "debug_logs" per errori dettagliati</li>
                </ul>
            </div>
            <?php 
            delete_transient('naval_egt_debug_export');
            endif; ?>

            <!-- AGGIORNATO: Risultati diagnosi dettagliati -->
            <?php if ($diagnosis_data): ?>
            <div class="card" style="border-left: 4px solid #0073aa;">
                <h3>📊 Risultati Diagnosi Completa Dettagliata</h3>
                
                <!-- Test Credenziali App -->
                <?php if (isset($diagnosis_data['app_credentials_test'])): ?>
                <div style="background: <?php echo $diagnosis_data['app_credentials_test']['success'] ? '#eafaea' : '#ffeaea'; ?>; padding: 15px; margin: 10px 0; border-radius: 4px;">
                    <h4 style="color: <?php echo $diagnosis_data['app_credentials_test']['success'] ? '#00a32a' : '#dc3232'; ?>; margin: 0 0 10px 0;">
                        🔑 <?php echo $diagnosis_data['app_credentials_test']['success'] ? 'Credenziali App Valide' : 'Credenziali App Non Valide'; ?>
                    </h4>
                    <p style="margin: 0;"><strong>Risultato:</strong> <?php echo esc_html($diagnosis_data['app_credentials_test']['message']); ?></p>
                    
                    <?php if (isset($diagnosis_data['app_credentials_test']['details'])): ?>
                        <details style="margin-top: 10px;">
                            <summary style="cursor: pointer;">📋 Dettagli Test Credenziali</summary>
                            <pre style="background: #f9f9f9; padding: 10px; margin: 5px 0; font-size: 11px; border-radius: 3px;"><?php echo esc_html(json_encode($diagnosis_data['app_credentials_test']['details'], JSON_PRETTY_PRINT)); ?></pre>
                        </details>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Credenziali -->
                <h4>🔑 Credenziali</h4>
                <table class="widefat" style="margin-bottom: 20px;">
                    <tr>
                        <th style="width: 150px;">App Key</th>
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
                        <th style="width: 150px;">Naval Database</th>
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
                    <table class="widefat" style="background: transparent;">
                        <tr><th style="width: 200px;">Lunghezza</th><td><?php echo $diagnosis_data['token_analysis']['length']; ?> caratteri</td></tr>
                        <tr><th>Caratteri unici</th><td><?php echo $diagnosis_data['token_analysis']['unique_chars']; ?></td></tr>
                        <tr><th>Contiene spazi</th><td><?php echo $diagnosis_data['token_analysis']['contains_spaces'] ? '❌ SÌ' : '✅ NO'; ?></td></tr>
                        <tr><th>Contiene newline</th><td><?php echo $diagnosis_data['token_analysis']['contains_newlines'] ? '❌ SÌ' : '✅ NO'; ?></td></tr>
                        <tr><th>Whitespace iniziale</th><td><?php echo $diagnosis_data['token_analysis']['has_leading_whitespace'] ? '❌ SÌ' : '✅ NO'; ?></td></tr>
                        <tr><th>Whitespace finale</th><td><?php echo $diagnosis_data['token_analysis']['has_trailing_whitespace'] ? '❌ SÌ' : '✅ NO'; ?></td></tr>
                        <tr><th>Inizia con "sl."</th><td><?php echo $diagnosis_data['token_analysis']['dropbox_patterns']['starts_with_sl'] ? '✅ SÌ' : '❌ NO'; ?></td></tr>
                        <tr><th>Ha underscores</th><td><?php echo $diagnosis_data['token_analysis']['dropbox_patterns']['has_underscores'] ? '✅ SÌ' : '❌ NO'; ?></td></tr>
                        <tr><th>Lunghezza ragionevole</th><td><?php echo $diagnosis_data['token_analysis']['dropbox_patterns']['reasonable_length'] ? '✅ SÌ' : '❌ NO'; ?></td></tr>
                        <tr style="background: <?php echo $diagnosis_data['token_analysis']['seems_valid'] ? '#d4edda' : '#f8d7da'; ?>;">
                            <th style="font-weight: bold; color: <?php echo $diagnosis_data['token_analysis']['seems_valid'] ? '#155724' : '#721c24'; ?>;">
                                SEMBRA VALIDO
                            </th>
                            <td style="font-weight: bold; color: <?php echo $diagnosis_data['token_analysis']['seems_valid'] ? '#155724' : '#721c24'; ?>;">
                                <?php echo $diagnosis_data['token_analysis']['seems_valid'] ? '✅ SÌ' : '❌ NO'; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php else: ?>
                <div style="background: #ffeaea; padding: 15px; margin: 10px 0; border: 1px solid #dc3232; border-radius: 4px;">
                    <h4 style="color: #dc3232; margin: 0 0 10px 0;">❌ Token Mancante</h4>
                    <p style="margin: 0;"><?php echo esc_html($diagnosis_data['token_analysis']['error']); ?></p>
                </div>
                <?php endif; ?>

                <!-- Test Connessione -->
                <?php if (isset($diagnosis_data['token_tests']['summary'])): ?>
                <h4>🧪 Test Connessione API</h4>
                <div style="background: <?php echo $diagnosis_data['token_tests']['summary']['success_count'] > 0 ? '#eafaea' : '#ffeaea'; ?>; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                        <div>
                            <p><strong>Metodi testati:</strong> <?php echo $diagnosis_data['token_tests']['summary']['total_methods_tested']; ?></p>
                            <p><strong>Metodi funzionanti:</strong> <?php echo $diagnosis_data['token_tests']['summary']['success_count']; ?></p>
                            <p><strong>Token funziona:</strong> <?php echo $diagnosis_data['token_tests']['summary']['token_seems_valid'] ? '✅ SÌ' : '❌ NO'; ?></p>
                        </div>
                        <div>
                            <?php if (!empty($diagnosis_data['token_tests']['summary']['successful_methods'])): ?>
                                <p><strong>Metodi che funzionano:</strong></p>
                                <ul style="margin: 5px 0 0 20px;">
                                    <?php foreach ($diagnosis_data['token_tests']['summary']['successful_methods'] as $method): ?>
                                        <li><?php echo esc_html($method); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Dettagli test singoli -->
                    <details style="margin-top: 15px;">
                        <summary style="cursor: pointer; font-weight: bold;">📋 Dettagli Test Singoli</summary>
                        <table class="widefat" style="margin: 10px 0;">
                            <thead>
                                <tr><th>Metodo</th><th>Stato</th><th>HTTP Code</th><th>Errore</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($diagnosis_data['token_tests'] as $method => $result): ?>
                                    <?php if ($method === 'summary') continue; ?>
                                    <tr>
                                        <td><?php echo esc_html($method); ?></td>
                                        <td><?php echo $result['success'] ? '✅' : '❌'; ?></td>
                                        <td><?php echo $result['http_code'] ?? 'N/A'; ?></td>
                                        <td><?php echo esc_html($result['curl_error'] ?? $result['wp_error'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </details>
                </div>
                <?php endif; ?>

                <!-- Test Connessione Finale -->
                <?php if (isset($diagnosis_data['connection_test'])): ?>
                <h4>📡 Test Connessione Finale</h4>
                <div style="background: <?php echo $diagnosis_data['connection_test']['success'] ? '#eafaea' : '#ffeaea'; ?>; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <p><strong>Risultato:</strong> <?php echo $diagnosis_data['connection_test']['success'] ? '✅ SUCCESSO' : '❌ FALLITO'; ?></p>
                    <p><strong>Messaggio:</strong> <?php echo esc_html($diagnosis_data['connection_test']['message']); ?></p>
                    
                    <?php if ($diagnosis_data['connection_test']['success'] && isset($diagnosis_data['connection_test']['account'])): ?>
                        <p><strong>Account:</strong> <?php echo esc_html($diagnosis_data['connection_test']['account']['email'] ?? 'N/A'); ?></p>
                        <?php if (isset($diagnosis_data['connection_test']['account']['name']['display_name'])): ?>
                            <p><strong>Nome:</strong> <?php echo esc_html($diagnosis_data['connection_test']['account']['name']['display_name']); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Raccomandazioni -->
                <?php if (isset($diagnosis_data['recommendations']) && !empty($diagnosis_data['recommendations'])): ?>
                <h4>💡 Raccomandazioni Dettagliate</h4>
                <?php foreach ($diagnosis_data['recommendations'] as $i => $rec): ?>
                    <div style="background: #fff; padding: 15px; margin: 10px 0; border-left: 4px solid <?php echo $rec['priority'] === 'CRITICAL' ? '#dc3232' : ($rec['priority'] === 'HIGH' ? '#dc3232' : ($rec['priority'] === 'MEDIUM' ? '#ffb900' : '#00a32a')); ?>; border-radius: 0 4px 4px 0;">
                        <h5 style="margin: 0 0 10px 0; color: <?php echo $rec['priority'] === 'CRITICAL' ? '#dc3232' : ($rec['priority'] === 'HIGH' ? '#dc3232' : ($rec['priority'] === 'MEDIUM' ? '#b8860b' : '#00a32a')); ?>;">
                            #<?php echo $i + 1; ?> - <?php echo esc_html($rec['priority']); ?>: <?php echo esc_html($rec['issue']); ?>
                        </h5>
                        <p><strong>💡 Soluzione:</strong> <?php echo esc_html($rec['solution']); ?></p>
                        <p><strong>🔧 Azione:</strong> <code><?php echo esc_html($rec['action']); ?></code></p>
                    </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <details style="margin-top: 20px;">
                    <summary style="cursor: pointer; font-weight: bold; font-size: 14px;">📋 Dati Completi Diagnosi (clicca per espandere)</summary>
                    <pre style="background: #f0f0f0; padding: 15px; margin: 10px 0; font-size: 10px; overflow: auto; max-height: 500px; border-radius: 4px;"><?php echo esc_html(json_encode($diagnosis_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                </details>
            </div>
            <?php 
            delete_transient('naval_egt_dropbox_diagnosis');
            endif; ?>

            <!-- AGGIORNATO: Token analysis se disponibile -->
            <?php if ($token_analysis): ?>
            <div class="card" style="border-left: 4px solid #ffb900;">
                <h3>🔑 Analisi Token Dettagliata Standalone</h3>
                
                <?php if (isset($token_analysis['error'])): ?>
                    <div style="background: #ffeaea; padding: 15px; border-radius: 4px;">
                        <p style="color: #dc3232; margin: 0;"><strong>❌ <?php echo esc_html($token_analysis['error']); ?></strong></p>
                    </div>
                <?php else: ?>
                    <div style="background: <?php echo $token_analysis['seems_valid'] ? '#eafaea' : '#ffeaea'; ?>; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                        <h4 style="margin: 0 0 15px 0; color: <?php echo $token_analysis['seems_valid'] ? '#00a32a' : '#dc3232'; ?>;">
                            <?php echo $token_analysis['seems_valid'] ? '✅ Token Sembra Valido' : '❌ Token Non Valido'; ?>
                        </h4>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div>
                                <h5>📊 Statistiche Base</h5>
                                <table class="widefat" style="background: transparent;">
                                    <tr><th>Lunghezza</th><td><?php echo $token_analysis['length']; ?> caratteri</td></tr>
                                    <tr><th>Caratteri unici</th><td><?php echo $token_analysis['unique_chars']; ?></td></tr>
                                    <tr><th>Primo carattere</th><td><code><?php echo esc_html($token_analysis['first_char']); ?></code></td></tr>
                                    <tr><th>Ultimo carattere</th><td><code><?php echo esc_html($token_analysis['last_char']); ?></code></td></tr>
                                </table>
                            </div>
                            <div>
                                <h5>🔍 Validazione</h5>
                                <table class="widefat" style="background: transparent;">
                                    <tr><th>Contiene spazi</th><td><?php echo $token_analysis['contains_spaces'] ? '❌ SÌ' : '✅ NO'; ?></td></tr>
                                    <tr><th>Contiene newline</th><td><?php echo $token_analysis['contains_newlines'] ? '❌ SÌ' : '✅ NO'; ?></td></tr>
                                    <tr><th>Whitespace iniziale</th><td><?php echo $token_analysis['has_leading_whitespace'] ? '❌ SÌ' : '✅ NO'; ?></td></tr>
                                    <tr><th>Whitespace finale</th><td><?php echo $token_analysis['has_trailing_whitespace'] ? '❌ SÌ' : '✅ NO'; ?></td></tr>
                                </table>
                            </div>
                        </div>
                        
                        <h5>🎯 Pattern Dropbox</h5>
                        <table class="widefat" style="background: transparent; margin-top: 10px;">
                            <tr><th style="width: 200px;">Inizia con "sl."</th><td><?php echo $token_analysis['dropbox_patterns']['starts_with_sl'] ? '✅ SÌ' : '❌ NO'; ?></td></tr>
                            <tr><th>Ha underscores</th><td><?php echo $token_analysis['dropbox_patterns']['has_underscores'] ? '✅ SÌ' : '❌ NO'; ?></td></tr>
                            <tr><th>Lunghezza ragionevole</th><td><?php echo $token_analysis['dropbox_patterns']['reasonable_length'] ? '✅ SÌ' : '❌ NO'; ?></td></tr>
                        </table>
                        
                        <div style="margin-top: 15px; padding: 10px; background: rgba(255,255,255,0.5); border-radius: 4px;">
                            <p><strong>📝 Anteprima Token:</strong></p>
                            <p><strong>Inizio:</strong> <code><?php echo esc_html($token_analysis['starts_with']); ?></code></p>
                            <p><strong>Fine:</strong> <code><?php echo esc_html($token_analysis['ends_with']); ?></code></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php 
            delete_transient('naval_egt_token_analysis');
            endif; ?>

            <!-- AGGIORNATO: Test multipli se disponibili -->
            <?php if ($multiple_tests): ?>
            <div class="card" style="border-left: 4px solid #7c3aed;">
                <h3>🧪 Risultati Test Multipli Dettagliati</h3>
                
                <?php if (isset($multiple_tests['error'])): ?>
                    <div style="background: #ffeaea; padding: 15px; border-radius: 4px;">
                        <p style="color: #dc3232; margin: 0;"><strong>❌ <?php echo esc_html($multiple_tests['error']); ?></strong></p>
                    </div>
                <?php else: ?>
                    <!-- Riepilogo -->
                    <?php if (isset($multiple_tests['summary'])): ?>
                        <div style="background: <?php echo $multiple_tests['summary']['success_count'] > 0 ? '#eafaea' : '#ffeaea'; ?>; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                            <h4 style="margin: 0 0 15px 0; color: <?php echo $multiple_tests['summary']['success_count'] > 0 ? '#00a32a' : '#dc3232'; ?>;">
                                📊 Riepilogo: <?php echo $multiple_tests['summary']['success_count']; ?>/<?php echo $multiple_tests['summary']['total_methods_tested']; ?> metodi funzionanti
                            </h4>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div>
                                    <p><strong>Token sembra valido:</strong> <?php echo $multiple_tests['summary']['token_seems_valid'] ? '✅ SÌ' : '❌ NO'; ?></p>
                                    <p><strong>Metodi testati:</strong> <?php echo $multiple_tests['summary']['total_methods_tested']; ?></p>
                                    <p><strong>Successi:</strong> <?php echo $multiple_tests['summary']['success_count']; ?></p>
                                </div>
                                <div>
                                    <?php if (!empty($multiple_tests['summary']['successful_methods'])): ?>
                                        <p><strong>✅ Metodi funzionanti:</strong></p>
                                        <ul style="margin: 5px 0 0 20px;">
                                            <?php foreach ($multiple_tests['summary']['successful_methods'] as $method): ?>
                                                <li><code><?php echo esc_html($method); ?></code></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p style="color: #dc3232;"><strong>❌ Nessun metodo funzionante</strong></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Dettagli test singoli -->
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th style="width: 200px;">Metodo</th>
                                <th style="width: 80px;">Stato</th>
                                <th style="width: 100px;">HTTP Code</th>
                                <th style="width: 150px;">Errore</th>
                                <th>Risposta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($multiple_tests as $method => $result): ?>
                                <?php if ($method === 'summary') continue; ?>
                                <tr style="background: <?php echo $result['success'] ? '#f0fff0' : '#fff0f0'; ?>;">
                                    <td><strong><?php echo esc_html($method); ?></strong></td>
                                    <td style="text-align: center;"><?php echo $result['success'] ? '✅ OK' : '❌ FAIL'; ?></td>
                                    <td style="text-align: center;"><?php echo $result['http_code'] ?? 'N/A'; ?></td>
                                    <td><?php echo esc_html($result['curl_error'] ?? $result['wp_error'] ?? '-'); ?></td>
                                    <td>
                                        <?php if (!empty($result['response_preview'])): ?>
                                            <details>
                                                <summary style="cursor: pointer;">📄 Mostra risposta (<?php echo $result['response_length'] ?? 0; ?> caratteri)</summary>
                                                <pre style="background: #f9f9f9; padding: 8px; margin: 5px 0; font-size: 10px; max-height: 150px; overflow-y: auto;"><?php echo esc_html($result['response_preview']); ?></pre>
                                            </details>
                                        <?php else: ?>
                                            <em>Nessuna risposta</em>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <?php 
            delete_transient('naval_egt_multiple_tests');
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
                <table class="widefat">
                    <tr><th style="width: 200px;">Access Token (Proprietà)</th><td><code><?php echo esc_html($debug_info['values_preview']['property_access_token']); ?></code></td></tr>
                    <tr><th>Access Token (Database)</th><td><code><?php echo esc_html($debug_info['values_preview']['database_access_token']); ?></code></td></tr>
                    <tr><th>Redirect URI</th><td><code><?php echo esc_html($debug_info['redirect_uri']); ?></code></td></tr>
                </table>
                <?php endif; ?>
            </div>
            
            <!-- AGGIORNATO: Azioni Debug Avanzate con tutti i nuovi metodi -->
            <div class="card">
                <h2>Azioni Debug Avanzate</h2>
                <p>Usa questi pulsanti per diagnosticare e risolvere problemi complessi:</p>
                
                <!-- Riga 1: Test e Analisi Principali -->
                <div style="margin-bottom: 15px;">
                    <form method="post" style="display: inline-block; margin-right: 10px;">
                        <?php wp_nonce_field('naval_egt_debug_diagnosis'); ?>
                        <input type="submit" name="run_full_diagnosis" class="button button-primary" value="🔍 Diagnosi Completa Avanzata" />
                    </form>
                    
                    <form method="post" style="display: inline-block; margin-right: 10px;">
                        <?php wp_nonce_field('naval_egt_debug_cred'); ?>
                        <input type="submit" name="test_app_credentials" class="button" value="🔑 Test Credenziali App" />
                    </form>
                    
                    <form method="post" style="display: inline-block; margin-right: 10px;">
                        <?php wp_nonce_field('naval_egt_debug_test'); ?>
                        <input type="submit" name="test_configuration" class="button" value="🧪 Test Configurazione Veloce" />
                    </form>
                </div>

                <!-- Riga 2: Analisi Token -->
                <div style="margin-bottom: 15px;">
                    <form method="post" style="display: inline-block; margin-right: 10px;">
                        <?php wp_nonce_field('naval_egt_debug_token'); ?>
                        <input type="submit" name="analyze_token_detailed" class="button" value="🔍 Analisi Token Dettagliata" />
                    </form>
                    
                    <form method="post" style="display: inline-block; margin-right: 10px;">
                        <?php wp_nonce_field('naval_egt_debug_multi'); ?>
                        <input type="submit" name="test_multiple_methods" class="button" value="🧪 Test Metodi Multipli" />
                    </form>
                    
                    <form method="post" style="display: inline-block; margin-right: 10px;">
                        <?php wp_nonce_field('naval_egt_debug_export'); ?>
                        <input type="submit" name="export_debug_complete" class="button" value="📊 Esporta Debug Completo" />
                    </form>
                </div>

                <!-- Riga 3: Gestione Sistema -->
                <div style="margin-bottom: 15px;">
                    <form method="post" style="display: inline-block; margin-right: 10px;">
                        <?php wp_nonce_field('naval_egt_debug_reload'); ?>
                        <input type="submit" name="reload_credentials" class="button" value="🔄 Ricarica Credenziali" />
                    </form>
                    
                    <form method="post" style="display: inline-block; margin-right: 10px;">
                        <?php wp_nonce_field('naval_egt_debug_clear'); ?>
                        <input type="submit" name="clear_debug_logs" class="button button-secondary" value="🗑️ Pulisci Log Debug" />
                    </form>
                </div>

                <!-- Riga 4: Azioni Critiche -->
                <div style="margin-bottom: 15px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
                    <h4 style="margin: 0 0 10px 0; color: #856404;">⚠️ Azioni Critiche</h4>
                    <p style="margin: 0 0 15px 0; color: #856404;"><strong>Usa con cautela - queste azioni cancelleranno i token esistenti:</strong></p>
                    
                    <form method="post" style="display: inline-block;">
                        <?php wp_nonce_field('naval_egt_debug_regen'); ?>
                        <input type="submit" name="force_token_regeneration" 
                               class="button" 
                               style="background: #dc3232; border-color: #dc3232; color: white;" 
                               value="🔄 Rigenera Token Completamente" 
                               onclick="return confirm('⚠️ ATTENZIONE: Questo cancellerà completamente il token corrente.\n\nDovrai riautorizzare l\'applicazione su Dropbox.\n\nUsa questo solo se il token è definitivamente corrotto.\n\nProcedere con la rigenerazione?');" />
                    </form>
                </div>

                <!-- Riga 5: Link Veloci-->
                <div>
                    <a href="<?php echo admin_url('admin.php?page=naval-egt&tab=dropbox'); ?>" class="button">
                        ↩️ Torna a Configurazione Dropbox
                    </a>
                    
                    <a href="<?php echo admin_url('admin.php?page=naval-egt&tab=overview'); ?>" class="button" style="margin-left: 10px;">
                        🏠 Torna alla Panoramica
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
                            <tr><th>cURL</th><td><?php echo function_exists('curl_init') ? '✅ Disponibile' : '❌ Non disponibile'; ?></td></tr>
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
                        <li><strong>Usa "Test Credenziali App"</strong> - Verifica che le credenziali hardcoded siano valide</li>
                        <li><strong>Controlla i Log Debug</strong> - Cerca errori specifici nei log qui sopra</li>
                        <li><strong>Usa "Analisi Token Dettagliata"</strong> - Per capire se il token è corrotto</li>
                        <li><strong>Usa "Test Metodi Multipli"</strong> - Per vedere quale metodo API funziona</li>
                    </ol>
                    
                    <h4>🔧 Risolvi il Problema:</h4>
                    <ol>
                        <li><strong>Se le credenziali app falliscono:</strong> C'è un problema con App Key/Secret hardcoded</li>
                        <li><strong>Se il token è corrotto:</strong> Usa "Rigenera Token Completamente"</li>
                        <li><strong>Se l'autorizzazione fallisce:</strong> Prova "Autorizza Manualmente"</li>
                        <li><strong>Se persistono problemi:</strong> Usa "Esporta Debug Completo" e contatta il supporto</li>
                    </ol>
                </div>
                <?php else: ?>
                <div style="background: #d5edda; border: 1px solid #198754; padding: 20px; margin: 15px 0; border-radius: 4px;">
                    <h3 style="color: #198754; margin-top: 0;">✅ Dropbox Configurato Correttamente</h3>
                    <p>La configurazione base è corretta. Se riscontri ancora problemi specifici:</p>
                    <ul>
                        <li>Usa "Test Configurazione Veloce" per verifiche puntuali</li>
                        <li>Usa "Test Metodi Multipli" se alcuni file/operazioni falliscono</li>
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
                        <li><strong>Token troppo lungo/corto?</strong> Potrebbe essere corrotto durante il salvataggio</li>
                        <li><strong>Pochi caratteri unici?</strong> Il token potrebbe essere stato alterato</li>
                        <li><strong>Test API falliscono tutti?</strong> Problema di rete o token completamente invalido</li>
                        <li><strong>Solo alcuni test falliscono?</strong> Problema specifico di configurazione server</li>
                        <li><strong>Autorizzazione non funziona?</strong> Controlla URL redirect nell'app Dropbox</li>
                        <li><strong>Token sembra valido ma non funziona?</strong> Potrebbe essere scaduto o revocato</li>
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

            // NUOVI: Azioni AJAX per debug Dropbox
            case 'run_dropbox_diagnosis':
                $this->ajax_run_dropbox_diagnosis();
                break;
                
            case 'test_app_credentials':
                $this->ajax_test_app_credentials();
                break;
                
            case 'analyze_token_detailed':
                $this->ajax_analyze_token_detailed();
                break;
                
            case 'test_multiple_methods':
                $this->ajax_test_multiple_methods();
                break;
                
            case 'force_reauth':
                $this->ajax_force_reauth();
                break;
                
            case 'export_debug_info':
                $this->ajax_export_debug_info();
                break;
                
            default:
                wp_send_json_error('Azione non valida');
                break;
        }
        
        wp_die();
    }

    /**
     * NUOVO: AJAX - Esegui diagnosi Dropbox
     */
    private function ajax_run_dropbox_diagnosis() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        $diagnosis = $dropbox->full_system_diagnosis();
        
        wp_send_json_success(array(
            'message' => 'Diagnosi completata',
            'diagnosis' => $diagnosis,
            'summary' => $this->generate_diagnosis_summary($diagnosis)
        ));
    }

    /**
     * NUOVO: AJAX - Test credenziali app
     */
    private function ajax_test_app_credentials() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        $result = $dropbox->test_app_credentials();
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * NUOVO: AJAX - Analizza token dettagliato
     */
    private function ajax_analyze_token_detailed() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        $analysis = $dropbox->analyze_token_detailed();
        
        wp_send_json_success(array(
            'message' => 'Analisi token completata',
            'analysis' => $analysis,
            'summary' => $this->generate_token_summary($analysis)
        ));
    }

    /**
     * NUOVO: AJAX - Test metodi multipli
     */
    private function ajax_test_multiple_methods() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        $tests = $dropbox->test_token_multiple_methods();
        
        wp_send_json_success(array(
            'message' => 'Test multipli completati',
            'tests' => $tests,
            'summary' => isset($tests['summary']) ? $tests['summary'] : array()
        ));
    }

    /**
     * NUOVO: AJAX - Forza riautenticazione
     */
    private function ajax_force_reauth() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        $result = $dropbox->force_reauth();
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'auth_url' => $result['auth_url'] ?? null
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * NUOVO: AJAX - Esporta informazioni debug
     */
    private function ajax_export_debug_info() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        $debug_info = $dropbox->export_debug_info();
        
        wp_send_json_success(array(
            'message' => 'Informazioni debug esportate',
            'debug_info' => $debug_info,
            'export_size' => strlen(json_encode($debug_info))
        ));
    }

    /**
     * NUOVO: Genera riassunto diagnosi
     */
    private function generate_diagnosis_summary($diagnosis) {
        $summary = array(
            'overall_status' => 'unknown',
            'critical_issues' => 0,
            'warnings' => 0,
            'recommendations_count' => 0,
            'key_findings' => array()
        );
        
        // Analizza credenziali app
        if (isset($diagnosis['app_credentials_test'])) {
            if (!$diagnosis['app_credentials_test']['success']) {
                $summary['critical_issues']++;
                $summary['key_findings'][] = 'Credenziali app non valide';
            }
        }
        
        // Analizza token
        if (isset($diagnosis['token_analysis']['error'])) {
            $summary['critical_issues']++;
            $summary['key_findings'][] = 'Token mancante';
        } elseif (isset($diagnosis['token_analysis']['seems_valid']) && !$diagnosis['token_analysis']['seems_valid']) {
            $summary['warnings']++;
            $summary['key_findings'][] = 'Token potenzialmente corrotto';
        }
        
        // Analizza test API
        if (isset($diagnosis['token_tests']['summary']['success_count'])) {
            $success_count = $diagnosis['token_tests']['summary']['success_count'];
            $total_count = $diagnosis['token_tests']['summary']['total_methods_tested'];
            
            if ($success_count === 0) {
                $summary['critical_issues']++;
                $summary['key_findings'][] = 'Tutti i test API falliscono';
            } elseif ($success_count < $total_count / 2) {
                $summary['warnings']++;
                $summary['key_findings'][] = 'Alcuni test API falliscono';
            }
        }
        
        // Conta raccomandazioni
        if (isset($diagnosis['recommendations'])) {
            $summary['recommendations_count'] = count($diagnosis['recommendations']);
        }
        
        // Determina stato generale
        if ($summary['critical_issues'] > 0) {
            $summary['overall_status'] = 'critical';
        } elseif ($summary['warnings'] > 0) {
            $summary['overall_status'] = 'warning';
        } else {
            $summary['overall_status'] = 'good';
        }
        
        return $summary;
    }

    /**
     * NUOVO: Genera riassunto token
     */
    private function generate_token_summary($analysis) {
        if (isset($analysis['error'])) {
            return array(
                'status' => 'error',
                'message' => $analysis['error']
            );
        }
        
        $issues = array();
        
        if ($analysis['contains_spaces']) {
            $issues[] = 'Contiene spazi';
        }
        
        if ($analysis['contains_newlines']) {
            $issues[] = 'Contiene newline';
        }
        
        if ($analysis['has_leading_whitespace'] || $analysis['has_trailing_whitespace']) {
            $issues[] = 'Ha whitespace non necessario';
        }
        
        if (!$analysis['dropbox_patterns']['starts_with_sl']) {
            $issues[] = 'Non inizia con "sl."';
        }
        
        if (!$analysis['dropbox_patterns']['reasonable_length']) {
            $issues[] = 'Lunghezza non ragionevole';
        }
        
        return array(
            'status' => $analysis['seems_valid'] ? 'valid' : 'invalid',
            'length' => $analysis['length'],
            'unique_chars' => $analysis['unique_chars'],
            'issues' => $issues,
            'issues_count' => count($issues)
        );
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