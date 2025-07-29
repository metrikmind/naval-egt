<?php
/**
 * Naval EGT Ajax Handlers Class
 * 
 * Handles all AJAX requests for the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class NavalEGT_AjaxHandlers {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // AJAX handlers for logged-in users
        add_action('wp_ajax_naval_egt_list_files', array($this, 'handle_list_files'));
        add_action('wp_ajax_naval_egt_upload_file', array($this, 'handle_upload_file'));
        add_action('wp_ajax_naval_egt_download_file', array($this, 'handle_download_file'));
        add_action('wp_ajax_naval_egt_delete_file', array($this, 'handle_delete_file'));
        add_action('wp_ajax_naval_egt_create_folder', array($this, 'handle_create_folder'));
        add_action('wp_ajax_naval_egt_get_folder_stats', array($this, 'handle_get_folder_stats'));
        add_action('wp_ajax_naval_egt_get_recent_activity', array($this, 'handle_get_recent_activity'));
        
        // Admin AJAX handlers
        add_action('wp_ajax_naval_egt_admin_list_users', array($this, 'handle_admin_list_users'));
        add_action('wp_ajax_naval_egt_admin_update_user_status', array($this, 'handle_admin_update_user_status'));
        add_action('wp_ajax_naval_egt_admin_delete_user', array($this, 'handle_admin_delete_user'));
        add_action('wp_ajax_naval_egt_admin_send_notification', array($this, 'handle_admin_send_notification'));
        add_action('wp_ajax_naval_egt_admin_upload_file_for_user', array($this, 'handle_admin_upload_file_for_user'));
        add_action('wp_ajax_naval_egt_admin_get_logs', array($this, 'handle_admin_get_logs'));
        
        // Public AJAX handlers (for non-logged users)
        add_action('wp_ajax_nopriv_naval_egt_check_email', array($this, 'handle_check_email'));
    }
    
    public function handle_list_files() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_nonce')) {
            wp_send_json_error(array('message' => __('Errore di sicurezza.', 'naval-egt')));
        }
        
        // Check user permissions
        if (!is_user_logged_in() || !naval_egt_user_can_access()) {
            wp_send_json_error(array('message' => __('Accesso negato.', 'naval-egt')));
        }
        
        $path = sanitize_text_field($_POST['path'] ?? '');
        $user_id = get_current_user_id();
        
        $ftp_manager = new NavalEGT_FTPManager();
        $files = $ftp_manager->list_files($user_id, $path);
        
        if ($files === false) {
            wp_send_json_error(array('message' => __('Errore durante il caricamento dei file.', 'naval-egt')));
        }
        
        wp_send_json_success($files);
    }
    
    public function handle_upload_file() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_nonce')) {
            wp_send_json_error(array('message' => __('Errore di sicurezza.', 'naval-egt')));
        }
        
        // Check user permissions
        if (!is_user_logged_in() || !naval_egt_user_can_access()) {
            wp_send_json_error(array('message' => __('Accesso negato.', 'naval-egt')));
        }
        
        // Check if file was uploaded
        if (empty($_FILES['file'])) {
            wp_send_json_error(array('message' => __('Nessun file selezionato.', 'naval-egt')));
        }
        
        $user_id = get_current_user_id();
        $subfolder = sanitize_text_field($_POST['subfolder'] ?? 'uploads/');
        
        $ftp_manager = new NavalEGT_FTPManager();
        $result = $ftp_manager->upload_file($user_id, $_FILES['file'], $subfolder);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function handle_download_file() {
        // Verify nonce
        if (!wp_verify_nonce($_GET['nonce'], 'naval_egt_nonce')) {
            wp_die(__('Errore di sicurezza.', 'naval-egt'));
        }
        
        // Check user permissions
        if (!is_user_logged_in() || !naval_egt_user_can_access()) {
            wp_die(__('Accesso negato.', 'naval-egt'));
        }
        
        $file_path = sanitize_text_field($_GET['file'] ?? '');
        $user_id = get_current_user_id();
        
        if (empty($file_path)) {
            wp_die(__('File non specificato.', 'naval-egt'));
        }
        
        $ftp_manager = new NavalEGT_FTPManager();
        $ftp_manager->download_file($user_id, $file_path);
    }
    
    public function handle_delete_file() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_nonce')) {
            wp_send_json_error(array('message' => __('Errore di sicurezza.', 'naval-egt')));
        }
        
        // Check user permissions
        if (!is_user_logged_in() || !naval_egt_user_can_access()) {
            wp_send_json_error(array('message' => __('Accesso negato.', 'naval-egt')));
        }
        
        $file_path = sanitize_text_field($_POST['file_path'] ?? '');
        $user_id = get_current_user_id();
        
        if (empty($file_path)) {
            wp_send_json_error(array('message' => __('File non specificato.', 'naval-egt')));
        }
        
        $ftp_manager = new NavalEGT_FTPManager();
        $result = $ftp_manager->delete_file($user_id, $file_path);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function handle_create_folder() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_nonce')) {
            wp_send_json_error(array('message' => __('Errore di sicurezza.', 'naval-egt')));
        }
        
        // Check user permissions
        if (!is_user_logged_in() || !naval_egt_user_can_access()) {
            wp_send_json_error(array('message' => __('Accesso negato.', 'naval-egt')));
        }
        
        $folder_name = sanitize_text_field($_POST['folder_name'] ?? '');
        $parent_folder = sanitize_text_field($_POST['parent_folder'] ?? 'uploads/');
        $user_id = get_current_user_id();
        
        if (empty($folder_name)) {
            wp_send_json_error(array('message' => __('Nome cartella non specificato.', 'naval-egt')));
        }
        
        $ftp_manager = new NavalEGT_FTPManager();
        $result = $ftp_manager->create_folder($user_id, $folder_name, $parent_folder);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function handle_get_folder_stats() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_nonce')) {
            wp_send_json_error(array('message' => __('Errore di sicurezza.', 'naval-egt')));
        }
        
        // Check user permissions
        if (!is_user_logged_in() || !naval_egt_user_can_access()) {
            wp_send_json_error(array('message' => __('Accesso negato.', 'naval-egt')));
        }
        
        $user_id = get_current_user_id();
        $ftp_manager = new NavalEGT_FTPManager();
        $stats = $ftp_manager->get_folder_stats($user_id);
        
        if ($stats === false) {
            wp_send_json_error(array('message' => __('Errore durante il caricamento delle statistiche.', 'naval-egt')));
        }
        
        wp_send_json_success($stats);
    }
    
    public function handle_get_recent_activity() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_nonce')) {
            wp_send_json_error(array('message' => __('Errore di sicurezza.', 'naval-egt')));
        }
        
        // Check user permissions
        if (!is_user_logged_in() || !naval_egt_user_can_access()) {
            wp_send_json_error(array('message' => __('Accesso negato.', 'naval-egt')));
        }
        
        $limit = intval($_POST['limit'] ?? 10);
        $user_id = get_current_user_id();
        
        $ftp_manager = new NavalEGT_FTPManager();
        $activity = $ftp_manager->get_recent_activity($user_id, $limit);
        
        if ($activity === false) {
            wp_send_json_error(array('message' => __('Errore durante il caricamento dell\'attività.', 'naval-egt')));
        }
        
        wp_send_json_success($activity);
    }
    
    // Admin AJAX Handlers
    
    public function handle_admin_list_users() {
        // Check admin permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Accesso negato.', 'naval-egt')));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_admin_nonce')) {
            wp_send_json_error(array('message' => __('Errore di sicurezza.', 'naval-egt')));
        }
        
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 20);
        $search = sanitize_text_field($_POST['search'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? '');
        
        $args = array(
            'role' => 'naval_egt_client',
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'orderby' => 'registered',
            'order' => 'DESC'
        );
        
        if (!empty($search)) {
            $args['search'] = '*' . $search . '*';
        }
        
        $users = get_users($args);
        $total_users = count(get_users(array('role' => 'naval_egt_client')));
        
        $users_data = array();
        foreach ($users as $user) {
            $user_status = get_user_meta($user->ID, 'naval_egt_status', true);
            
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
        
        wp_send_json_success(array(
            'users' => $users_data,
            'total' => $total_users,
            'page' => $page,
            'per_page' => $per_page
        ));
    }
    
    public function handle_admin_update_user_status() {
        // Check admin permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Accesso negato.', 'naval-egt')));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_admin_nonce')) {
            wp_send_json_error(array('message' => __('Errore di sicurezza.', 'naval-egt')));
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        
        if (empty($user_id) || empty($status)) {
            wp_send_json_error(array('message' => __('Parametri mancanti.', 'naval-egt')));
        }
        
        $allowed_statuses = array('active', 'pending', 'suspended');
        if (!in_array($status, $allowed_statuses)) {
            wp_send_json_error(array('message' => __('Status non valido.', 'naval-egt')));
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user || !in_array('naval_egt_client', $user->roles)) {
            wp_send_json_error(array('message' => __('Utente non trovato.', 'naval-egt')));
        }
        
        update_user_meta($user_id, 'naval_egt_status', $status);
        
        // Send notification email if status changed to active
        if ($status === 'active') {
            $this->send_status_notification($user, 'activated');
        } elseif ($status === 'suspended') {
            $this->send_status_notification($user, 'suspended');
        }
        
        wp_send_json_success(array('message' => __('Status utente aggiornato.', 'naval-egt')));
    }
    
    public function handle_admin_delete_user() {
        // Check admin permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Accesso negato.', 'naval-egt')));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_admin_nonce')) {
            wp_send_json_error(array('message' => __('Errore di sicurezza.', 'naval-egt')));
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (empty($user_id)) {
            wp_send_json_error(array('message' => __('ID utente mancante.', 'naval-egt')));
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user || !in_array('naval_egt_client', $user->roles)) {
            wp_send_json_error(array('message' => __('Utente non trovato.', 'naval-egt')));
        }
        
        // Delete user's FTP folder
        $ftp_folder = get_user_meta($user_id, 'naval_egt_ftp_folder', true);
        if ($ftp_folder) {
            $ftp_manager = new NavalEGT_FTPManager();
            $folder_path = $ftp_manager->get_user_folder_path($user_id);
            if ($folder_path && is_dir($folder_path)) {
                $this->delete_directory_recursive($folder_path);
            }
        }
        
        // Delete user records from custom tables
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'naval_egt_sessions', array('user_id' => $user_id));
        $wpdb->delete($wpdb->prefix . 'naval_egt_ftp_logs', array('user_id' => $user_id));
        
        // Delete WordPress user
        if (wp_delete_user($user_id)) {
            wp_send_json_success(array('message' => __('Utente eliminato con successo.', 'naval-egt')));
        } else {
            wp_send_json_error(array('message' => __('Errore durante l\'eliminazione dell\'utente.', 'naval-egt')));
        }
    }
    
    public function handle_admin_send_notification() {
        // Check admin permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Accesso negato.', 'naval-egt')));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_admin_nonce')) {
            wp_send_json_error(array('message' => __('Errore di sicurezza.', 'naval-egt')));
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        if (empty($user_id) || empty($subject) || empty($message)) {
            wp_send_json_error(array('message' => __('Tutti i campi sono obbligatori.', 'naval-egt')));
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user || !in_array('naval_egt_client', $user->roles)) {
            wp_send_json_error(array('message' => __('Utente non trovato.', 'naval-egt')));
        }
        
        $full_message = sprintf(
            __("Ciao %s,\n\n%s\n\nCordiali saluti,\nIl team Naval EGT", 'naval-egt'),
            $user->display_name,
            $message
        );
        
        if (wp_mail($user->user_email, $subject, $full_message)) {
            wp_send_json_success(array('message' => __('Notifica inviata con successo.', 'naval-egt')));
        } else {
            wp_send_json_error(array('message' => __('Errore durante l\'invio della notifica.', 'naval-egt')));
        }
    }
    
    public function handle_admin_upload_file_for_user() {
        // Check admin permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Accesso negato.', 'naval-egt')));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_admin_nonce')) {
            wp_send_json_error(array('message' => __('Errore di sicurezza.', 'naval-egt')));
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $subfolder = sanitize_text_field($_POST['subfolder'] ?? 'downloads/');
        
        if (empty($user_id) || empty($_FILES['file'])) {
            wp_send_json_error(array('message' => __('Parametri mancanti.', 'naval-egt')));
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user || !in_array('naval_egt_client', $user->roles)) {
            wp_send_json_error(array('message' => __('Utente non trovato.', 'naval-egt')));
        }
        
        $ftp_manager = new NavalEGT_FTPManager();
        $result = $ftp_manager->upload_file($user_id, $_FILES['file'], $subfolder);
        
        if ($result['success']) {
            // Notify user about new file
            $this->notify_user_new_file($user, $result['filename']);
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function handle_admin_get_logs() {
        // Check admin permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Accesso negato.', 'naval-egt')));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_admin_nonce')) {
            wp_send_json_error(array('message' => __('Errore di sicurezza.', 'naval-egt')));
        }
        
        global $wpdb;
        
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 50);
        $user_id = intval($_POST['user_id'] ?? 0);
        $action = sanitize_text_field($_POST['action_filter'] ?? '');
        
        $offset = ($page - 1) * $per_page;
        
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
                LIMIT %d OFFSET %d";
        
        $query_values = array_merge($where_values, array($per_page, $offset));
        $logs = $wpdb->get_results($wpdb->prepare($sql, $query_values));
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM $table_name $where_sql";
        $total = $wpdb->get_var($wpdb->prepare($count_sql, $where_values));
        
        wp_send_json_success(array(
            'logs' => $logs,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page
        ));
    }
    
    // Public AJAX Handlers
    
    public function handle_check_email() {
        $email = sanitize_email($_POST['email'] ?? '');
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array('message' => __('Email non valida.', 'naval-egt')));
        }
        
        $exists = email_exists($email);
        wp_send_json_success(array('exists' => (bool)$exists));
    }
    
    // Helper Methods
    
    private function send_status_notification($user, $status) {
        if ($status === 'activated') {
            $subject = __('Account Naval EGT attivato', 'naval-egt');
            $message = sprintf(
                __("Ciao %s,\n\nIl tuo account nell'area riservata Naval EGT è stato attivato.\n\nPuoi ora accedere all'area riservata e utilizzare tutti i servizi disponibili.\n\nAccedi qui: %s\n\nCordiali saluti,\nIl team Naval EGT", 'naval-egt'),
                $user->display_name,
                home_url('/area-riservata/')
            );
        } elseif ($status === 'suspended') {
            $subject = __('Account Naval EGT sospeso', 'naval-egt');
            $message = sprintf(
                __("Ciao %s,\n\nIl tuo account nell'area riservata Naval EGT è stato temporaneamente sospeso.\n\nPer maggiori informazioni, contatta il nostro supporto all'indirizzo: technical@navalegt.it\n\nCordiali saluti,\nIl team Naval EGT", 'naval-egt'),
                $user->display_name
            );
        }
        
        if (isset($subject) && isset($message)) {
            wp_mail($user->user_email, $subject, $message);
        }
    }
    
    private function notify_user_new_file($user, $filename) {
        $subject = __('Nuovo file disponibile - Naval EGT', 'naval-egt');
        $message = sprintf(
            __("Ciao %s,\n\nÈ stato caricato un nuovo file nella tua area riservata Naval EGT.\n\nFile: %s\n\nPuoi accedere alla tua area riservata per scaricarlo:\n%s\n\nCordiali saluti,\nIl team Naval EGT", 'naval-egt'),
            $user->display_name,
            $filename,
            home_url('/dashboard-clienti/')
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    private function delete_directory_recursive($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->delete_directory_recursive($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
}
?>