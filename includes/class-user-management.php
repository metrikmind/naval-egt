<?php
/**
 * Naval EGT User Management Class
 * 
 * Handles user registration, login, and account management
 */

if (!defined('ABSPATH')) {
    exit;
}

class NavalEGT_UserManagement {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_login', array($this, 'on_user_login'), 10, 2);
        add_action('wp_logout', array($this, 'on_user_logout'));
        add_filter('authenticate', array($this, 'custom_authenticate'), 30, 3);
    }
    
    public function init() {
        // Handle custom login/logout URLs
        add_action('template_redirect', array($this, 'handle_custom_actions'));
        
        // Add custom user fields
        add_action('show_user_profile', array($this, 'add_custom_user_fields'));
        add_action('edit_user_profile', array($this, 'add_custom_user_fields'));
        add_action('personal_options_update', array($this, 'save_custom_user_fields'));
        add_action('edit_user_profile_update', array($this, 'save_custom_user_fields'));
        
        // Custom registration process
        add_action('user_register', array($this, 'on_user_register'));
    }
    
    public function handle_custom_actions() {
        if (!isset($_GET['naval_action'])) {
            return;
        }
        
        $action = sanitize_text_field($_GET['naval_action']);
        
        switch ($action) {
            case 'login':
                $this->handle_login();
                break;
            case 'register':
                $this->handle_registration();
                break;
            case 'logout':
                $this->handle_logout();
                break;
            case 'reset_password':
                $this->handle_password_reset();
                break;
            case 'activate':
                $this->handle_account_activation();
                break;
        }
    }
    
    private function handle_login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['naval_egt_nonce'], 'naval_egt_login')) {
            wp_die(__('Errore di sicurezza. Riprova.', 'naval-egt'));
        }
        
        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);
        
        if (empty($username) || empty($password)) {
            $this->add_notice(__('Tutti i campi sono obbligatori.', 'naval-egt'), 'error');
            return;
        }
        
        $credentials = array(
            'user_login' => $username,
            'user_password' => $password,
            'remember' => $remember
        );
        
        $user = wp_signon($credentials);
        
        if (is_wp_error($user)) {
            $this->add_notice(__('Credenziali non valide. Verifica email/username e password.', 'naval-egt'), 'error');
            return;
        }
        
        // Check if user has access to Naval EGT area
        if (!naval_egt_user_can_access($user->ID)) {
            wp_logout();
            $this->add_notice(__('Non hai i permessi per accedere all\'area riservata.', 'naval-egt'), 'error');
            return;
        }
        
        // Log the session
        $this->log_user_session($user->ID);
        
        // Redirect to dashboard
        $dashboard_url = home_url('/dashboard-clienti/');
        wp_redirect($dashboard_url);
        exit;
    }
    
    private function handle_registration() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['naval_egt_nonce'], 'naval_egt_register')) {
            wp_die(__('Errore di sicurezza. Riprova.', 'naval-egt'));
        }
        
        $email = sanitize_email($_POST['email']);
        $company = sanitize_text_field($_POST['company']);
        $phone = sanitize_text_field($_POST['phone']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        
        // Validation
        if (empty($email) || empty($company) || empty($first_name) || empty($last_name)) {
            $this->add_notice(__('Tutti i campi obbligatori devono essere compilati.', 'naval-egt'), 'error');
            return;
        }
        
        if (!is_email($email)) {
            $this->add_notice(__('Inserisci un indirizzo email valido.', 'naval-egt'), 'error');
            return;
        }
        
        if (email_exists($email)) {
            $this->add_notice(__('Un account con questa email esiste già.', 'naval-egt'), 'error');
            return;
        }
        
        // Create user with pending status
        $username = $this->generate_username($email);
        $password = wp_generate_password(12, false);
        
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            $this->add_notice(__('Errore durante la registrazione. Riprova.', 'naval-egt'), 'error');
            return;
        }
        
        // Set user role
        $user = new WP_User($user_id);
        $user->set_role('naval_egt_client');
        
        // Save additional user data
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        update_user_meta($user_id, 'naval_egt_company', $company);
        update_user_meta($user_id, 'naval_egt_phone', $phone);
        update_user_meta($user_id, 'naval_egt_status', 'pending');
        update_user_meta($user_id, 'naval_egt_activation_key', wp_generate_password(20, false));
        
        // Create FTP folder for user
        $this->create_user_ftp_folder($user_id);
        
        // Send activation email
        $this->send_activation_email($user_id);
        
        $this->add_notice(__('Registrazione completata! Controlla la tua email per attivare l\'account.', 'naval-egt'), 'success');
    }
    
    private function handle_logout() {
        if (is_user_logged_in()) {
            $this->log_user_logout(get_current_user_id());
            wp_logout();
        }
        
        wp_redirect(home_url('/area-riservata/'));
        exit;
    }
    
    private function handle_password_reset() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['naval_egt_nonce'], 'naval_egt_reset')) {
            wp_die(__('Errore di sicurezza. Riprova.', 'naval-egt'));
        }
        
        $email = sanitize_email($_POST['email']);
        
        if (empty($email) || !is_email($email)) {
            $this->add_notice(__('Inserisci un indirizzo email valido.', 'naval-egt'), 'error');
            return;
        }
        
        $user = get_user_by('email', $email);
        
        if (!$user || !naval_egt_user_can_access($user->ID)) {
            $this->add_notice(__('Nessun account trovato con questa email nell\'area riservata.', 'naval-egt'), 'error');
            return;
        }
        
        // Generate reset key
        $reset_key = get_password_reset_key($user);
        
        if (is_wp_error($reset_key)) {
            $this->add_notice(__('Errore durante la generazione della chiave di reset.', 'naval-egt'), 'error');
            return;
        }
        
        // Send reset email
        $this->send_password_reset_email($user, $reset_key);
        
        $this->add_notice(__('Email per il reset della password inviata. Controlla la tua casella di posta.', 'naval-egt'), 'success');
    }
    
    private function handle_account_activation() {
        $key = sanitize_text_field($_GET['key']);
        $user_id = intval($_GET['user_id']);
        
        if (empty($key) || empty($user_id)) {
            wp_die(__('Link di attivazione non valido.', 'naval-egt'));
        }
        
        $user = get_user_by('id', $user_id);
        $stored_key = get_user_meta($user_id, 'naval_egt_activation_key', true);
        
        if (!$user || $key !== $stored_key) {
            wp_die(__('Link di attivazione non valido o scaduto.', 'naval-egt'));
        }
        
        // Activate user
        update_user_meta($user_id, 'naval_egt_status', 'active');
        delete_user_meta($user_id, 'naval_egt_activation_key');
        
        // Send welcome email with login credentials
        $this->send_welcome_email($user_id);
        
        // Redirect to login with success message
        wp_redirect(add_query_arg('activated', '1', home_url('/area-riservata/')));
        exit;
    }
    
    public function on_user_login($user_login, $user) {
        if (naval_egt_user_can_access($user->ID)) {
            $this->log_user_session($user->ID);
        }
    }
    
    public function on_user_logout() {
        if (is_user_logged_in()) {
            $this->log_user_logout(get_current_user_id());
        }
    }
    
    public function custom_authenticate($user, $username, $password) {
        if (is_wp_error($user)) {
            return $user;
        }
        
        // Check if this is a Naval EGT user trying to login
        if ($user && naval_egt_user_can_access($user->ID)) {
            $status = get_user_meta($user->ID, 'naval_egt_status', true);
            
            if ($status === 'pending') {
                return new WP_Error('account_pending', __('Il tuo account è in attesa di attivazione. Controlla la tua email.', 'naval-egt'));
            }
            
            if ($status === 'suspended') {
                return new WP_Error('account_suspended', __('Il tuo account è stato sospeso. Contatta l\'assistenza.', 'naval-egt'));
            }
        }
        
        return $user;
    }
    
    public function add_custom_user_fields($user) {
        if (!current_user_can('manage_options') && !naval_egt_user_can_access($user->ID)) {
            return;
        }
        ?>
        <h3><?php _e('Informazioni Naval EGT', 'naval-egt'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="naval_egt_company"><?php _e('Azienda', 'naval-egt'); ?></label></th>
                <td>
                    <input type="text" name="naval_egt_company" id="naval_egt_company" 
                           value="<?php echo esc_attr(get_user_meta($user->ID, 'naval_egt_company', true)); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="naval_egt_phone"><?php _e('Telefono', 'naval-egt'); ?></label></th>
                <td>
                    <input type="text" name="naval_egt_phone" id="naval_egt_phone" 
                           value="<?php echo esc_attr(get_user_meta($user->ID, 'naval_egt_phone', true)); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            <?php if (current_user_can('manage_options')): ?>
            <tr>
                <th><label for="naval_egt_status"><?php _e('Status Account', 'naval-egt'); ?></label></th>
                <td>
                    <select name="naval_egt_status" id="naval_egt_status">
                        <option value="active" <?php selected(get_user_meta($user->ID, 'naval_egt_status', true), 'active'); ?>><?php _e('Attivo', 'naval-egt'); ?></option>
                        <option value="pending" <?php selected(get_user_meta($user->ID, 'naval_egt_status', true), 'pending'); ?>><?php _e('In attesa', 'naval-egt'); ?></option>
                        <option value="suspended" <?php selected(get_user_meta($user->ID, 'naval_egt_status', true), 'suspended'); ?>><?php _e('Sospeso', 'naval-egt'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="naval_egt_ftp_folder"><?php _e('Cartella FTP', 'naval-egt'); ?></label></th>
                <td>
                    <input type="text" name="naval_egt_ftp_folder" id="naval_egt_ftp_folder" 
                           value="<?php echo esc_attr(get_user_meta($user->ID, 'naval_egt_ftp_folder', true)); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('Cartella FTP dedicata per questo utente', 'naval-egt'); ?></p>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }
    
    public function save_custom_user_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        if (isset($_POST['naval_egt_company'])) {
            update_user_meta($user_id, 'naval_egt_company', sanitize_text_field($_POST['naval_egt_company']));
        }
        
        if (isset($_POST['naval_egt_phone'])) {
            update_user_meta($user_id, 'naval_egt_phone', sanitize_text_field($_POST['naval_egt_phone']));
        }
        
        if (current_user_can('manage_options')) {
            if (isset($_POST['naval_egt_status'])) {
                update_user_meta($user_id, 'naval_egt_status', sanitize_text_field($_POST['naval_egt_status']));
            }
            
            if (isset($_POST['naval_egt_ftp_folder'])) {
                update_user_meta($user_id, 'naval_egt_ftp_folder', sanitize_text_field($_POST['naval_egt_ftp_folder']));
            }
        }
    }
    
    public function on_user_register($user_id) {
        // Set default status for new users
        if (!get_user_meta($user_id, 'naval_egt_status', true)) {
            update_user_meta($user_id, 'naval_egt_status', 'pending');
        }
    }
    
    private function generate_username($email) {
        $username = sanitize_user(substr($email, 0, strpos($email, '@')));
        
        // Make sure username is unique
        $counter = 1;
        $original_username = $username;
        
        while (username_exists($username)) {
            $username = $original_username . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    private function create_user_ftp_folder($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        $folder_name = 'client_' . $user_id . '_' . sanitize_title($user->user_login);
        update_user_meta($user_id, 'naval_egt_ftp_folder', $folder_name);
        
        // Here you would create the actual FTP folder
        // This depends on your FTP setup and server configuration
        do_action('naval_egt_create_ftp_folder', $user_id, $folder_name);
        
        return $folder_name;
    }
    
    private function send_activation_email($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        $activation_key = get_user_meta($user_id, 'naval_egt_activation_key', true);
        $activation_url = add_query_arg(array(
            'naval_action' => 'activate',
            'key' => $activation_key,
            'user_id' => $user_id
        ), home_url('/area-riservata/'));
        
        $subject = __('Attiva il tuo account Naval EGT', 'naval-egt');
        $message = sprintf(
            __("Ciao %s,\n\nGrazie per esserti registrato all'area riservata Naval EGT.\n\nPer attivare il tuo account, clicca sul seguente link:\n%s\n\nSe non hai richiesto questa registrazione, ignora questa email.\n\nCordiali saluti,\nIl team Naval EGT", 'naval-egt'),
            $user->display_name,
            $activation_url
        );
        
        return wp_mail($user->user_email, $subject, $message);
    }
    
    private function send_welcome_email($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        $login_url = home_url('/area-riservata/');
        
        $subject = __('Benvenuto nell\'area riservata Naval EGT', 'naval-egt');
        $message = sprintf(
            __("Ciao %s,\n\nIl tuo account è stato attivato con successo!\n\nPuoi ora accedere all'area riservata utilizzando:\nEmail: %s\nUsername: %s\n\nAccedi qui: %s\n\nSe hai dimenticato la password, puoi reimpostarla dalla pagina di login.\n\nCordiali saluti,\nIl team Naval EGT", 'naval-egt'),
            $user->display_name,
            $user->user_email,
            $user->user_login,
            $login_url
        );
        
        return wp_mail($user->user_email, $subject, $message);
    }
    
    private function send_password_reset_email($user, $reset_key) {
        $reset_url = add_query_arg(array(
            'action' => 'rp',
            'key' => $reset_key,
            'login' => rawurlencode($user->user_login)
        ), wp_login_url());
        
        $subject = __('Reset password Naval EGT', 'naval-egt');
        $message = sprintf(
            __("Hai richiesto il reset della password per il tuo account Naval EGT.\n\nClicca sul seguente link per impostare una nuova password:\n%s\n\nSe non hai richiesto questa operazione, ignora questa email.\n\nCordiali saluti,\nIl team Naval EGT", 'naval-egt'),
            $reset_url
        );
        
        return wp_mail($user->user_email, $subject, $message);
    }
    
    private function log_user_session($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'naval_egt_sessions';
        
        // Clean old sessions
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE user_id = %d OR expires_at < NOW()",
            $user_id
        ));
        
        // Log new session
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'session_token' => wp_generate_password(32, false),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'])
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }
    
    private function log_user_logout($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'naval_egt_sessions';
        
        $wpdb->delete(
            $table_name,
            array('user_id' => $user_id),
            array('%d')
        );
    }
    
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    private function add_notice($message, $type = 'info') {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['naval_egt_notices'])) {
            $_SESSION['naval_egt_notices'] = array();
        }
        
        $_SESSION['naval_egt_notices'][] = array(
            'message' => $message,
            'type' => $type
        );
    }
    
    public static function get_notices() {
        if (!session_id()) {
            session_start();
        }
        
        $notices = $_SESSION['naval_egt_notices'] ?? array();
        unset($_SESSION['naval_egt_notices']);
        
        return $notices;
    }
}
?>