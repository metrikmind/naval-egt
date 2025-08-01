<?php
/**
 * Classe per la gestione del frontend pubblico - Versione con sessioni corrette
 */

if (!defined('ABSPATH')) {
    exit;
}

class Naval_EGT_Public {
    
    private static $instance = null;
    private static $session_started = false;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Avvia sessione solo se necessario e possibile
        add_action('init', array($this, 'maybe_start_session'), 1);
        
        add_action('wp_ajax_naval_egt_login', array($this, 'handle_login'));
        add_action('wp_ajax_nopriv_naval_egt_login', array($this, 'handle_login'));
        add_action('wp_ajax_naval_egt_register', array($this, 'handle_registration'));
        add_action('wp_ajax_nopriv_naval_egt_register', array($this, 'handle_registration'));
        add_action('wp_ajax_naval_egt_logout', array($this, 'handle_logout'));
        add_action('wp_ajax_nopriv_naval_egt_logout', array($this, 'handle_logout'));
        add_action('wp_ajax_naval_egt_get_user_files', array($this, 'get_user_files'));
        add_action('wp_ajax_naval_egt_get_user_activity', array($this, 'get_user_activity'));
        add_action('wp_ajax_naval_egt_upload_file', array($this, 'upload_user_file'));
        add_action('wp_ajax_naval_egt_download_file', array($this, 'download_user_file'));
        add_action('wp_ajax_naval_egt_delete_file', array($this, 'delete_user_file'));
    }
    
    /**
     * Avvia sessione solo se necessario e sicuro
     */
    public function maybe_start_session() {
        // Non avviare sessione se:
        // - Già avviata
        // - Siamo in admin
        // - Headers già inviati
        // - Non è necessaria (nessun utente naval egt)
        
        if (self::$session_started) {
            return;
        }
        
        if (is_admin()) {
            return;
        }
        
        if (headers_sent()) {
            error_log('Naval EGT: Cannot start session - headers already sent');
            return;
        }
        
        // Avvia sessione solo se abbiamo un ID sessione o se è necessario
        if (!session_id() && $this->should_start_session()) {
            if (@session_start()) {
                self::$session_started = true;
                error_log('Naval EGT: Session started successfully');
            } else {
                error_log('Naval EGT: Failed to start session');
            }
        } elseif (session_id()) {
            self::$session_started = true;
        }
        
        // Check remember cookie dopo l'avvio sessione
        if (self::$session_started) {
            $this->check_remember_cookie();
        }
    }
    
    /**
     * Determina se è necessario avviare una sessione
     */
    private function should_start_session() {
        // Avvia sessione se:
        // - C'è un cookie "ricordami"
        // - Siamo su una pagina con shortcode naval egt
        // - C'è una richiesta AJAX naval egt
        
        global $post;
        
        if (isset($_COOKIE['naval_egt_remember'])) {
            return true;
        }
        
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $action = $_REQUEST['action'] ?? '';
            if (strpos($action, 'naval_egt_') === 0) {
                return true;
            }
        }
        
        if ($post && has_shortcode($post->post_content, 'naval_egt_area_riservata')) {
            return true;
        }
        
        if ($post && has_shortcode($post->post_content, 'naval_egt_login_form')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Garantisce che la sessione sia avviata per operazioni critiche
     */
    private function ensure_session() {
        if (!self::$session_started && !headers_sent()) {
            if (!session_id() && @session_start()) {
                self::$session_started = true;
            }
        }
        return self::$session_started;
    }
    
    /**
     * Gestisce il login utente
     */
    public static function handle_login() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        $instance = self::get_instance();
        $instance->ensure_session();
        
        $login = sanitize_text_field($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) && $_POST['remember'] == '1';
        
        if (empty($login) || empty($password)) {
            wp_send_json_error('Inserisci email/username e password');
        }
        
        $result = Naval_EGT_User_Manager::authenticate($login, $password);
        
        if ($result['success']) {
            // Se "ricordami" è selezionato, imposta cookie di lunga durata
            if ($remember) {
                $cookie_expiry = time() + (30 * DAY_IN_SECONDS); // 30 giorni
                setcookie('naval_egt_remember', base64_encode($login), $cookie_expiry, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            }
            
            // Log dell'accesso
            Naval_EGT_Activity_Logger::log_activity(
                $result['user']['id'],
                $result['user']['user_code'],
                'LOGIN',
                null,
                null,
                0,
                array('remember' => $remember ? 'yes' : 'no')
            );
            
            wp_send_json_success(array(
                'message' => 'Login effettuato con successo',
                'redirect' => $_POST['redirect_to'] ?? '',
                'user' => array(
                    'nome' => $result['user']['nome'],
                    'cognome' => $result['user']['cognome'],
                    'user_code' => $result['user']['user_code']
                )
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Gestisce la registrazione utente
     */
    public static function handle_registration() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        // Verifica se la registrazione è abilitata
        $registration_enabled = Naval_EGT_Database::get_setting('user_registration_enabled', '1');
        if ($registration_enabled !== '1') {
            wp_send_json_error('Le registrazioni sono temporaneamente disabilitate');
        }
        
        $data = array(
            'nome' => sanitize_text_field($_POST['nome'] ?? ''),
            'cognome' => sanitize_text_field($_POST['cognome'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'telefono' => sanitize_text_field($_POST['telefono'] ?? ''),
            'username' => sanitize_user($_POST['username'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
            'ragione_sociale' => sanitize_text_field($_POST['ragione_sociale'] ?? ''),
            'partita_iva' => sanitize_text_field($_POST['partita_iva'] ?? ''),
            'privacy_policy' => isset($_POST['privacy_policy']) ? '1' : '0'
        );
        
        // Validazioni aggiuntive
        if ($data['password'] !== $data['password_confirm']) {
            wp_send_json_error('Le password non corrispondono');
        }
        
        if ($data['privacy_policy'] !== '1') {
            wp_send_json_error('È necessario accettare la Privacy Policy');
        }
        
        if (!empty($data['ragione_sociale']) && empty($data['partita_iva'])) {
            wp_send_json_error('La Partita IVA è obbligatoria se si specifica la Ragione Sociale');
        }
        
        // Validazione email
        if (!is_email($data['email'])) {
            wp_send_json_error('Formato email non valido');
        }
        
        // Validazione password
        if (strlen($data['password']) < 6) {
            wp_send_json_error('La password deve essere di almeno 6 caratteri');
        }
        
        // Crea l'utente
        $result = Naval_EGT_User_Manager::create_user($data);
        
        if ($result['success']) {
            // Log registrazione
            Naval_EGT_Activity_Logger::log_activity(
                $result['user_id'],
                $result['user_code'],
                'REGISTRATION',
                null,
                null,
                0,
                array('email' => $data['email'], 'ragione_sociale' => $data['ragione_sociale'])
            );
            
            // Invio email di conferma all'admin se configurato
            $email_enabled = Naval_EGT_Database::get_setting('email_notifications', '1');
            if ($email_enabled === '1') {
                $admin_email = get_option('admin_email');
                $subject = 'Nuova richiesta di registrazione - Naval EGT';
                $message = sprintf(
                    "Nuova richiesta di registrazione ricevuta:\n\n" .
                    "Nome: %s %s\n" .
                    "Email: %s\n" .
                    "Username: %s\n" .
                    "Codice Utente: %s\n" .
                    "Azienda: %s\n" .
                    "Telefono: %s\n\n" .
                    "Vai su %s per attivare l'utente.",
                    $data['nome'],
                    $data['cognome'],
                    $data['email'],
                    $data['username'],
                    $result['user_code'],
                    $data['ragione_sociale'] ?: 'Non specificata',
                    $data['telefono'] ?: 'Non specificato',
                    admin_url('admin.php?page=naval-egt&tab=users')
                );
                
                wp_mail($admin_email, $subject, $message);
            }
            
            wp_send_json_success(array(
                'message' => 'Richiesta di registrazione inviata con successo! Il tuo account sarà attivato manualmente dal nostro staff. Riceverai una email di conferma.',
                'user_code' => $result['user_code']
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Gestisce il logout utente
     */
    public static function handle_logout() {
        $current_user = Naval_EGT_User_Manager::get_current_user();
        
        if ($current_user) {
            // Log logout
            Naval_EGT_Activity_Logger::log_activity(
                $current_user['id'],
                $current_user['user_code'],
                'LOGOUT',
                null,
                null,
                0
            );
        }
        
        Naval_EGT_User_Manager::logout();
        
        // Rimuovi cookie "ricordami"
        if (isset($_COOKIE['naval_egt_remember'])) {
            setcookie('naval_egt_remember', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        }
        
        wp_send_json_success(array('message' => 'Logout effettuato con successo'));
    }
    
    /**
     * Ottiene i file dell'utente corrente
     */
    public function get_user_files() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        $current_user = Naval_EGT_User_Manager::get_current_user();
        if (!$current_user) {
            wp_send_json_error('Accesso richiesto');
        }
        
        $page = intval($_POST['page'] ?? 1);
        $search = sanitize_text_field($_POST['search'] ?? '');
        
        $files = Naval_EGT_File_Manager::get_user_files($current_user['id'], array(
            'page' => $page,
            'per_page' => 20,
            'search' => $search
        ));
        
        // Formatta i file per il frontend
        $formatted_files = array();
        foreach ($files['files'] as $file) {
            $formatted_files[] = array(
                'id' => $file['id'],
                'name' => $file['file_name'],
                'size' => size_format($file['file_size']),
                'date' => mysql2date('d/m/Y H:i', $file['created_at']),
                'download_url' => add_query_arg(array(
                    'action' => 'naval_egt_download',
                    'file_id' => $file['id'],
                    'nonce' => wp_create_nonce('download_file_' . $file['id'])
                ), admin_url('admin-ajax.php'))
            );
        }
        
        wp_send_json_success(array(
            'files' => $formatted_files,
            'total' => $files['total'],
            'pagination' => $files['pagination']
        ));
    }
    
    /**
     * Ottiene l'attività dell'utente corrente
     */
    public function get_user_activity() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        $current_user = Naval_EGT_User_Manager::get_current_user();
        if (!$current_user) {
            wp_send_json_error('Accesso richiesto');
        }
        
        $page = intval($_POST['page'] ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $activities = Naval_EGT_Activity_Logger::get_logs(
            array('user_id' => $current_user['id']),
            $limit,
            $offset
        );
        
        // Formatta le attività
        $formatted_activities = array();
        foreach ($activities as $activity) {
            $formatted_activities[] = array(
                'id' => $activity['id'],
                'action' => $this->format_action_name($activity['action']),
                'description' => $this->format_activity_description($activity),
                'date' => mysql2date('d/m/Y H:i', $activity['created_at']),
                'ip_address' => $activity['ip_address'] ?? ''
            );
        }
        
        wp_send_json_success(array(
            'activities' => $formatted_activities,
            'total' => count($activities)
        ));
    }
    
    /**
     * Upload file per utente corrente
     */
    public function upload_user_file() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        $current_user = Naval_EGT_User_Manager::get_current_user();
        if (!$current_user) {
            wp_send_json_error('Accesso richiesto');
        }
        
        if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
            wp_send_json_error('Nessun file selezionato');
        }
        
        // Verifica stato utente
        if ($current_user['status'] !== 'ATTIVO') {
            wp_send_json_error('Account non attivo. Contatta l\'amministratore.');
        }
        
        // Processa file multipli
        $uploaded_files = array();
        $errors = array();
        
        $files = $_FILES['files'];
        $file_count = count($files['name']);
        
        // Verifica Dropbox
        $dropbox = Naval_EGT_Dropbox::get_instance();
        if (!$dropbox->is_configured()) {
            wp_send_json_error('Servizio di archiviazione non disponibile. Contatta l\'amministratore.');
        }
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $errors[] = "Errore nel file {$files['name'][$i]}";
                continue;
            }
            
            // Validazioni file
            $file_name = $files['name'][$i];
            $file_size = $files['size'][$i];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Controlla estensione
            $allowed_types = explode(',', Naval_EGT_Database::get_setting('allowed_file_types', 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif'));
            if (!in_array($file_ext, $allowed_types)) {
                $errors[] = "Tipo file non consentito: {$file_name}";
                continue;
            }
            
            // Controlla dimensione
            $max_size = intval(Naval_EGT_Database::get_setting('max_file_size', '20971520'));
            if ($file_size > $max_size) {
                $errors[] = "File troppo grande: {$file_name} (" . size_format($file_size) . ")";
                continue;
            }
            
            $file = array(
                'name' => $file_name,
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $file_size
            );
            
            // Upload tramite File Manager
            $upload_result = $this->process_single_file_upload($file, $current_user);
            
            if ($upload_result['success']) {
                $uploaded_files[] = $file_name;
            } else {
                $errors[] = $file_name . ': ' . $upload_result['message'];
            }
        }
        
        if (!empty($uploaded_files)) {
            $message = 'File caricati con successo: ' . implode(', ', $uploaded_files);
            if (!empty($errors)) {
                $message .= '. Errori: ' . implode(', ', $errors);
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'uploaded' => $uploaded_files,
                'errors' => $errors
            ));
        } else {
            wp_send_json_error('Nessun file è stato caricato. Errori: ' . implode(', ', $errors));
        }
    }
    
    /**
     * Download file utente
     */
    public function download_user_file() {
        $file_id = intval($_GET['file_id'] ?? 0);
        $nonce = $_GET['nonce'] ?? '';
        
        if (!wp_verify_nonce($nonce, 'download_file_' . $file_id)) {
            wp_die('Token di sicurezza non valido');
        }
        
        $current_user = Naval_EGT_User_Manager::get_current_user();
        if (!$current_user) {
            wp_die('Accesso richiesto');
        }
        
        // Verifica proprietà del file
        global $wpdb;
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}naval_egt_files WHERE id = %d AND user_id = %d",
            $file_id, $current_user['id']
        ), ARRAY_A);
        
        if (!$file) {
            wp_die('File non trovato o non autorizzato');
        }
        
        // Download da Dropbox
        $dropbox = Naval_EGT_Dropbox::get_instance();
        $download_result = $dropbox->download_file($file['dropbox_path']);
        
        if (!$download_result['success']) {
            wp_die('Errore nel download: ' . $download_result['message']);
        }
        
        // Log download
        Naval_EGT_Activity_Logger::log_activity(
            $current_user['id'],
            $current_user['user_code'],
            'DOWNLOAD',
            $file['file_name'],
            $file['dropbox_path'],
            $file['file_size']
        );
        
        // Invia file al browser
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file['file_name'] . '"');
        header('Content-Length: ' . $file['file_size']);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        echo $download_result['content'];
        exit;
    }
    
    /**
     * Elimina file utente
     */
    public function delete_user_file() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        $current_user = Naval_EGT_User_Manager::get_current_user();
        if (!$current_user) {
            wp_send_json_error('Accesso richiesto');
        }
        
        $file_id = intval($_POST['file_id'] ?? 0);
        
        if (!$file_id) {
            wp_send_json_error('ID file non valido');
        }
        
        // Verifica proprietà del file
        global $wpdb;
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}naval_egt_files WHERE id = %d AND user_id = %d",
            $file_id, $current_user['id']
        ), ARRAY_A);
        
        if (!$file) {
            wp_send_json_error('File non trovato o non autorizzato');
        }
        
        // Elimina da Dropbox (opzionale - potrebbe essere solo nascosto nel DB)
        $dropbox = Naval_EGT_Dropbox::get_instance();
        if ($dropbox->is_configured()) {
            $dropbox->delete($file['dropbox_path']);
        }
        
        // Elimina dal database
        $wpdb->delete($wpdb->prefix . 'naval_egt_files', array('id' => $file_id), array('%d'));
        
        // Log eliminazione
        Naval_EGT_Activity_Logger::log_activity(
            $current_user['id'],
            $current_user['user_code'],
            'DELETE',
            $file['file_name'],
            $file['dropbox_path'],
            $file['file_size']
        );
        
        wp_send_json_success('File eliminato con successo');
    }
    
    /**
     * Processa upload di un singolo file
     */
    private function process_single_file_upload($file, $user) {
        $dropbox = Naval_EGT_Dropbox::get_instance();
        
        // Cerca cartella utente
        $folder_result = $dropbox->find_folder_by_code($user['user_code']);
        
        if (!$folder_result['success'] || empty($folder_result['folders'])) {
            return array(
                'success' => false,
                'message' => 'Cartella Dropbox non trovata per il codice ' . $user['user_code']
            );
        }
        
        $user_folder = $folder_result['folders'][0]['path_lower'];
        $dropbox_path = $user_folder . '/' . $file['name'];
        
        // Upload su Dropbox
        $upload_result = $dropbox->upload_file($file['tmp_name'], $dropbox_path);
        
        if (!$upload_result['success']) {
            return array(
                'success' => false,
                'message' => 'Errore upload Dropbox: ' . $upload_result['message']
            );
        }
        
        // Salva nel database
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'naval_egt_files',
            array(
                'user_id' => $user['id'],
                'user_code' => $user['user_code'],
                'file_name' => $file['name'],
                'file_path' => $upload_result['data']['path_display'],
                'dropbox_path' => $upload_result['data']['path_lower'],
                'file_size' => $file['size'],
                'dropbox_id' => $upload_result['data']['id'],
                'last_modified' => current_time('mysql'),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        if ($result) {
            // Log upload
            Naval_EGT_Activity_Logger::log_activity(
                $user['id'],
                $user['user_code'],
                'UPLOAD',
                $file['name'],
                $dropbox_path,
                $file['size']
            );
            
            return array('success' => true, 'file_id' => $wpdb->insert_id);
        } else {
            return array('success' => false, 'message' => 'Errore salvataggio database');
        }
    }
    
    /**
     * Verifica se l'utente ha un cookie "ricordami" valido - VERSIONE CORRETTA
     */
    public function check_remember_cookie() {
        if (!isset($_COOKIE['naval_egt_remember']) || Naval_EGT_User_Manager::is_logged_in()) {
            return;
        }
        
        $login_credential = base64_decode($_COOKIE['naval_egt_remember']);
        if (!$login_credential) {
            return;
        }
        
        $user = filter_var($login_credential, FILTER_VALIDATE_EMAIL) 
            ? Naval_EGT_User_Manager::get_user_by_email($login_credential)
            : Naval_EGT_User_Manager::get_user_by_username($login_credential);
        
        if ($user && $user['status'] === 'ATTIVO') {
            // Assicurati che la sessione sia disponibile
            if ($this->ensure_session()) {
                $_SESSION['naval_egt_user'] = $user;
                
                // Log accesso automatico
                Naval_EGT_Activity_Logger::log_activity(
                    $user['id'], 
                    $user['user_code'], 
                    'LOGIN',
                    null,
                    null,
                    0,
                    array('auto_login' => 'remember_me')
                );
            }
        }
    }
    
    /**
     * Ottiene informazioni pubbliche per shortcode
     */
    public static function get_public_info() {
        $stats = Naval_EGT_Database::get_user_stats();
        
        return array(
            'registration_enabled' => Naval_EGT_Database::get_setting('user_registration_enabled', '1') === '1',
            'total_users' => $stats['total_users'] ?? 0,
            'support_email' => 'tecnica@naval.it'
        );
    }
    
    /**
     * Shortcode per form di login personalizzato - VERSIONE CORRETTA
     */
    public static function login_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'redirect' => '',
            'show_register_link' => 'true'
        ), $atts, 'naval_egt_login_form');
        
        if (Naval_EGT_User_Manager::is_logged_in()) {
            return '<p>Sei già connesso. <a href="?logout=1">Logout</a></p>';
        }
        
        ob_start();
        echo '<div class="naval-egt-login-form">';
        echo '<form method="post" class="login-form">';
        wp_nonce_field('naval_egt_nonce', 'nonce');
        echo '<input type="hidden" name="naval_action" value="login">';
        
        if ($atts['redirect']) {
            echo '<input type="hidden" name="redirect_to" value="' . esc_attr($atts['redirect']) . '">';
        }
        
        echo '<div class="form-group">';
        echo '<label for="user_login">Email o Username</label>';
        echo '<input type="text" id="user_login" name="login" required>';
        echo '</div>';
        
        echo '<div class="form-group">';
        echo '<label for="user_password">Password</label>';
        echo '<input type="password" id="user_password" name="password" required>';
        echo '</div>';
        
        echo '<div class="form-group checkbox-group">';
        echo '<label>';
        echo '<input type="checkbox" name="remember" value="1">';
        echo 'Ricordami';
        echo '</label>';
        echo '</div>';
        
        echo '<button type="submit" class="btn-primary">Accedi</button>';
        
        if ($atts['show_register_link'] === 'true') {
            echo '<p><a href="?register=1">Non hai un account? Richiedi registrazione</a></p>';
        }
        
        echo '</form>';
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Formatta nome azione per display
     */
    private function format_action_name($action) {
        $actions = array(
            'LOGIN' => 'Accesso',
            'LOGOUT' => 'Disconnessione',
            'UPLOAD' => 'Caricamento file',
            'DOWNLOAD' => 'Scaricamento file',
            'DELETE' => 'Eliminazione file',
            'REGISTRATION' => 'Registrazione'
        );
        
        return $actions[$action] ?? $action;
    }
    
    /**
     * Formatta descrizione attività
     */
    private function format_activity_description($activity) {
        switch ($activity['action']) {
            case 'UPLOAD':
                return 'Caricato: ' . ($activity['file_name'] ?? 'file sconosciuto');
            case 'DOWNLOAD':
                return 'Scaricato: ' . ($activity['file_name'] ?? 'file sconosciuto');
            case 'DELETE':
                return 'Eliminato: ' . ($activity['file_name'] ?? 'file sconosciuto');
            case 'LOGIN':
                $details = json_decode($activity['details'] ?? '{}', true);
                if (isset($details['auto_login'])) {
                    return 'Accesso automatico (ricordami)';
                }
                return 'Accesso effettuato';
            case 'LOGOUT':
                return 'Disconnessione effettuata';
            case 'REGISTRATION':
                return 'Account registrato';
            default:
                return $activity['action'];
        }
    }
    
    /**
     * Inizializzazione del frontend - VERSIONE CORRETTA
     */
    public static function init() {
        $instance = self::get_instance();
        
        // Registra shortcode aggiuntivi
        add_shortcode('naval_egt_login_form', array(__CLASS__, 'login_form_shortcode'));
        
        // Handle logout via GET parameter
        if (isset($_GET['logout']) && $_GET['logout'] == '1') {
            $current_user = Naval_EGT_User_Manager::get_current_user();
            if ($current_user) {
                Naval_EGT_Activity_Logger::log_activity(
                    $current_user['id'],
                    $current_user['user_code'],
                    'LOGOUT'
                );
            }
            
            Naval_EGT_User_Manager::logout();
            wp_redirect(remove_query_arg('logout'));
            exit;
        }
        
        // Gestione download diretto
        if (isset($_GET['action']) && $_GET['action'] === 'naval_egt_download') {
            $instance->download_user_file();
        }
    }
}