<?php
/**
 * Classe per la gestione degli utenti
 */

if (!defined('ABSPATH')) {
    exit;
}

class Naval_EGT_User_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init_session'));
    }
    
    /**
     * Inizializza la sessione
     */
    public function init_session() {
        if (!session_id() && !headers_sent()) {
            session_start();
        }
    }
    
    /**
     * Crea un nuovo utente
     */
    public static function create_user($data) {
        global $wpdb;
        
        // Validazione dati
        $validation = self::validate_user_data($data);
        if (!$validation['valid']) {
            return array('success' => false, 'message' => $validation['message']);
        }
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        // Genera codice utente
        $user_code = Naval_EGT_Database::get_next_user_code();
        
        // Hash password
        $password_hash = wp_hash_password($data['password']);
        
        $user_data = array(
            'user_code' => $user_code,
            'nome' => sanitize_text_field($data['nome']),
            'cognome' => sanitize_text_field($data['cognome']),
            'email' => sanitize_email($data['email']),
            'username' => sanitize_user($data['username']),
            'password' => $password_hash,
            'telefono' => sanitize_text_field($data['telefono'] ?? ''),
            'ragione_sociale' => sanitize_text_field($data['ragione_sociale'] ?? ''),
            'partita_iva' => sanitize_text_field($data['partita_iva'] ?? ''),
            'status' => 'SOSPESO',
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table_users, $user_data);
        
        if ($result === false) {
            return array('success' => false, 'message' => 'Errore durante la creazione dell\'utente');
        }
        
        $user_id = $wpdb->insert_id;
        
        // Log attività
        Naval_EGT_Activity_Logger::log_activity($user_id, $user_code, 'REGISTRATION', null, null, 0, array(
            'nome' => $data['nome'],
            'cognome' => $data['cognome'],
            'email' => $data['email']
        ));
        
        return array(
            'success' => true, 
            'message' => 'Utente creato con successo',
            'user_id' => $user_id,
            'user_code' => $user_code
        );
    }
    
    /**
     * Aggiorna un utente
     */
    public static function update_user($user_id, $data) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        $update_data = array();
        
        if (isset($data['nome'])) {
            $update_data['nome'] = sanitize_text_field($data['nome']);
        }
        if (isset($data['cognome'])) {
            $update_data['cognome'] = sanitize_text_field($data['cognome']);
        }
        if (isset($data['email'])) {
            $update_data['email'] = sanitize_email($data['email']);
        }
        if (isset($data['telefono'])) {
            $update_data['telefono'] = sanitize_text_field($data['telefono']);
        }
        if (isset($data['ragione_sociale'])) {
            $update_data['ragione_sociale'] = sanitize_text_field($data['ragione_sociale']);
        }
        if (isset($data['partita_iva'])) {
            $update_data['partita_iva'] = sanitize_text_field($data['partita_iva']);
        }
        if (isset($data['status'])) {
            $update_data['status'] = in_array($data['status'], array('ATTIVO', 'SOSPESO')) ? $data['status'] : 'SOSPESO';
        }
        if (isset($data['dropbox_folder'])) {
            $update_data['dropbox_folder'] = sanitize_text_field($data['dropbox_folder']);
        }
        if (isset($data['password']) && !empty($data['password'])) {
            $update_data['password'] = wp_hash_password($data['password']);
        }
        
        $update_data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $table_users,
            $update_data,
            array('id' => $user_id),
            null,
            array('%d')
        );
        
        if ($result === false) {
            return array('success' => false, 'message' => 'Errore durante l\'aggiornamento dell\'utente');
        }
        
        return array('success' => true, 'message' => 'Utente aggiornato con successo');
    }
    
    /**
     * Elimina un utente
     */
    public static function delete_user($user_id) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        $table_files = $wpdb->prefix . 'naval_egt_files';
        $table_logs = $wpdb->prefix . 'naval_egt_activity_logs';
        
        // Ottieni informazioni utente prima di eliminarlo
        $user = self::get_user_by_id($user_id);
        if (!$user) {
            return array('success' => false, 'message' => 'Utente non trovato');
        }
        
        // Elimina file da Dropbox se configurato
        if (!empty($user['dropbox_folder'])) {
            $dropbox = Naval_EGT_Dropbox::get_instance();
            $dropbox->delete_folder($user['dropbox_folder']);
        }
        
        // Elimina record correlati
        $wpdb->delete($table_files, array('user_id' => $user_id), array('%d'));
        $wpdb->delete($table_logs, array('user_id' => $user_id), array('%d'));
        $wpdb->delete($table_users, array('id' => $user_id), array('%d'));
        
        return array('success' => true, 'message' => 'Utente eliminato con successo');
    }
    
    /**
     * Ottiene un utente per ID
     */
    public static function get_user_by_id($user_id) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_users WHERE id = %d",
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Ottiene un utente per codice
     */
    public static function get_user_by_code($user_code) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_users WHERE user_code = %s",
            $user_code
        ), ARRAY_A);
    }
    
    /**
     * Ottiene un utente per email
     */
    public static function get_user_by_email($email) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_users WHERE email = %s",
            $email
        ), ARRAY_A);
    }
    
    /**
     * Ottiene un utente per username
     */
    public static function get_user_by_username($username) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_users WHERE username = %s",
            $username
        ), ARRAY_A);
    }
    
    /**
     * Ottiene tutti gli utenti con filtri
     */
    public static function get_users($filters = array(), $limit = null, $offset = 0) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        $where = array('1=1');
        $values = array();
        
        if (!empty($filters['search'])) {
            $search = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where[] = "(nome LIKE %s OR cognome LIKE %s OR email LIKE %s OR ragione_sociale LIKE %s)";
            $values = array_merge($values, array($search, $search, $search, $search));
        }
        
        if (!empty($filters['status'])) {
            $where[] = "status = %s";
            $values[] = $filters['status'];
        }
        
        $sql = "SELECT * FROM $table_users WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT %d OFFSET %d";
            $values = array_merge($values, array($limit, $offset));
        }
        
        if (!empty($values)) {
            return $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
        } else {
            return $wpdb->get_results($sql, ARRAY_A);
        }
    }
    
    /**
     * Conta gli utenti con filtri
     */
    public static function count_users($filters = array()) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        $where = array('1=1');
        $values = array();
        
        if (!empty($filters['search'])) {
            $search = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where[] = "(nome LIKE %s OR cognome LIKE %s OR email LIKE %s OR ragione_sociale LIKE %s)";
            $values = array_merge($values, array($search, $search, $search, $search));
        }
        
        if (!empty($filters['status'])) {
            $where[] = "status = %s";
            $values[] = $filters['status'];
        }
        
        $sql = "SELECT COUNT(*) FROM $table_users WHERE " . implode(' AND ', $where);
        
        if (!empty($values)) {
            return (int)$wpdb->get_var($wpdb->prepare($sql, $values));
        } else {
            return (int)$wpdb->get_var($sql);
        }
    }
    
    /**
     * Autentica un utente
     */
    public static function authenticate($login, $password) {
        // Il login può essere username o email
        $user = filter_var($login, FILTER_VALIDATE_EMAIL) 
            ? self::get_user_by_email($login)
            : self::get_user_by_username($login);
        
        if (!$user) {
            return array('success' => false, 'message' => 'Utente non trovato');
        }
        
        if ($user['status'] !== 'ATTIVO') {
            return array('success' => false, 'message' => 'Account non attivo. Contatta l\'amministratore.');
        }
        
        if (!wp_check_password($password, $user['password'])) {
            return array('success' => false, 'message' => 'Password non corretta');
        }
        
        // Aggiorna ultimo accesso
        global $wpdb;
        $table_users = $wpdb->prefix . 'naval_egt_users';
        $wpdb->update(
            $table_users,
            array('last_login' => current_time('mysql')),
            array('id' => $user['id']),
            array('%s'),
            array('%d')
        );
        
        // Log accesso
        Naval_EGT_Activity_Logger::log_activity($user['id'], $user['user_code'], 'LOGIN');
        
        // Salva in sessione
        $_SESSION['naval_egt_user'] = $user;
        
        return array(
            'success' => true, 
            'message' => 'Login effettuato con successo',
            'user' => $user
        );
    }
    
    /**
     * Logout utente
     */
    public static function logout() {
        if (isset($_SESSION['naval_egt_user'])) {
            $user = $_SESSION['naval_egt_user'];
            Naval_EGT_Activity_Logger::log_activity($user['id'], $user['user_code'], 'LOGOUT');
            unset($_SESSION['naval_egt_user']);
        }
        
        return array('success' => true, 'message' => 'Logout effettuato con successo');
    }
    
    /**
     * Ottiene l'utente attualmente loggato
     */
    public static function get_current_user() {
        return isset($_SESSION['naval_egt_user']) ? $_SESSION['naval_egt_user'] : null;
    }
    
    /**
     * Verifica se l'utente è loggato
     */
    public static function is_logged_in() {
        return isset($_SESSION['naval_egt_user']) && !empty($_SESSION['naval_egt_user']);
    }
    
    /**
     * Cambia stato utente
     */
    public static function change_user_status($user_id, $status) {
        if (!in_array($status, array('ATTIVO', 'SOSPESO'))) {
            return array('success' => false, 'message' => 'Status non valido');
        }
        
        return self::update_user($user_id, array('status' => $status));
    }
    
    /**
     * Valida i dati utente
     */
    private static function validate_user_data($data, $user_id = null) {
        // Controlli obbligatori
        if (empty($data['nome'])) {
            return array('valid' => false, 'message' => 'Il nome è obbligatorio');
        }
        
        if (empty($data['cognome'])) {
            return array('valid' => false, 'message' => 'Il cognome è obbligatorio');
        }
        
        if (empty($data['email']) || !is_email($data['email'])) {
            return array('valid' => false, 'message' => 'Email non valida');
        }
        
        if (empty($data['username'])) {
            return array('valid' => false, 'message' => 'Lo username è obbligatorio');
        }
        
        if (!$user_id && empty($data['password'])) {
            return array('valid' => false, 'message' => 'La password è obbligatoria');
        }
        
        // Controllo univocità email
        $existing_user = self::get_user_by_email($data['email']);
        if ($existing_user && (!$user_id || $existing_user['id'] != $user_id)) {
            return array('valid' => false, 'message' => 'Email già esistente');
        }
        
        // Controllo univocità username
        $existing_user = self::get_user_by_username($data['username']);
        if ($existing_user && (!$user_id || $existing_user['id'] != $user_id)) {
            return array('valid' => false, 'message' => 'Username già esistente');
        }
        
        // Controllo partita IVA se ragione sociale presente
        if (!empty($data['ragione_sociale']) && empty($data['partita_iva'])) {
            return array('valid' => false, 'message' => 'La partita IVA è obbligatoria se si specifica la ragione sociale');
        }
        
        return array('valid' => true, 'message' => 'Dati validi');
    }
}