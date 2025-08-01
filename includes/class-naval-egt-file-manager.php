<?php
/**
 * Classe per la gestione dei file con integrazione Dropbox
 */

if (!defined('ABSPATH')) {
    exit;
}

class Naval_EGT_File_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_naval_egt_upload_file', array($this, 'handle_upload'));
        add_action('wp_ajax_naval_egt_download_file', array($this, 'handle_download'));
        add_action('wp_ajax_naval_egt_delete_file', array($this, 'handle_delete'));
        add_action('wp_ajax_naval_egt_get_file_info', array($this, 'get_file_info'));
    }
    
    /**
     * Gestisce l'upload dei file
     */
    public static function handle_upload() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        // Determina utente (frontend o admin)
        $current_user = null;
        $is_admin_upload = false;
        
        if (isset($_SESSION['naval_egt_admin_upload'])) {
            // Upload da admin
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Permessi insufficienti per upload admin');
            }
            
            $admin_data = $_SESSION['naval_egt_admin_upload'];
            $current_user = Naval_EGT_User_Manager::get_user_by_id($admin_data['user_id']);
            $is_admin_upload = true;
        } else {
            // Upload da frontend
            $current_user = Naval_EGT_User_Manager::get_current_user();
        }
        
        if (!$current_user) {
            wp_send_json_error('Utente non trovato o non autorizzato');
        }
        
        // Verifica stato utente
        if ($current_user['status'] !== 'ATTIVO') {
            wp_send_json_error('Account non attivo. Contatta l\'amministratore.');
        }
        
        // Verifica file
        if (!isset($_FILES['file']) && !isset($_FILES['files'])) {
            wp_send_json_error('Nessun file selezionato per l\'upload');
        }
        
        // Normalizza struttura file per gestire sia singoli che multipli
        $files_to_process = array();
        
        if (isset($_FILES['files'])) {
            // File multipli
            $file_count = count($_FILES['files']['name']);
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                    $files_to_process[] = array(
                        'name' => $_FILES['files']['name'][$i],
                        'type' => $_FILES['files']['type'][$i],
                        'tmp_name' => $_FILES['files']['tmp_name'][$i],
                        'error' => $_FILES['files']['error'][$i],
                        'size' => $_FILES['files']['size'][$i]
                    );
                }
            }
        } elseif (isset($_FILES['file'])) {
            // File singolo
            if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $files_to_process[] = $_FILES['file'];
            }
        }
        
        if (empty($files_to_process)) {
            wp_send_json_error('Nessun file valido per l\'upload');
        }
        
        // Verifica Dropbox
        $dropbox = Naval_EGT_Dropbox::get_instance();
        if (!$dropbox->is_configured()) {
            wp_send_json_error('Servizio di archiviazione non configurato. Contatta l\'amministratore.');
        }
        
        // Processa ogni file
        $uploaded_files = array();
        $errors = array();
        
        foreach ($files_to_process as $file) {
            $result = self::process_single_file_upload($file, $current_user, $is_admin_upload);
            
            if ($result['success']) {
                $uploaded_files[] = $file['name'];
            } else {
                $errors[] = $file['name'] . ': ' . $result['message'];
            }
        }
        
        // Risposta finale
        if (!empty($uploaded_files)) {
            $message = 'File caricati con successo: ' . implode(', ', $uploaded_files);
            if (!empty($errors)) {
                $message .= '. Alcuni errori: ' . implode(', ', $errors);
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'uploaded' => $uploaded_files,
                'errors' => $errors,
                'total_uploaded' => count($uploaded_files)
            ));
        } else {
            wp_send_json_error('Nessun file è stato caricato. Errori: ' . implode(', ', $errors));
        }
    }
    
    /**
     * Processa l'upload di un singolo file
     */
    private static function process_single_file_upload($file, $user, $is_admin_upload = false) {
        // Validazioni file
        $validation_result = self::validate_file($file);
        if (!$validation_result['valid']) {
            return array('success' => false, 'message' => $validation_result['message']);
        }
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        
        // Trova o determina cartella utente
        $folder_path = null;
        
        if ($is_admin_upload && isset($_SESSION['naval_egt_admin_upload']['folder_path'])) {
            // Admin ha specificato una cartella
            $folder_path = $_SESSION['naval_egt_admin_upload']['folder_path'];
        }
        
        if (!$folder_path) {
            // Cerca cartella automaticamente
            $folder_result = $dropbox->find_folder_by_code($user['user_code']);
            
            if (!$folder_result['success'] || empty($folder_result['folders'])) {
                return array(
                    'success' => false,
                    'message' => 'Cartella Dropbox non trovata per il codice ' . $user['user_code'] . '. Contatta l\'amministratore.'
                );
            }
            
            $folder_path = $folder_result['folders'][0]['path_lower'];
        }
        
        // Genera nome file univoco se esiste già
        $original_name = $file['name'];
        $file_name = self::generate_unique_filename($original_name, $folder_path, $dropbox);
        $dropbox_path = $folder_path . '/' . $file_name;
        
        // Upload su Dropbox
        $upload_result = $dropbox->upload_file($file['tmp_name'], $dropbox_path);
        
        if (!$upload_result['success']) {
            return array(
                'success' => false,
                'message' => 'Errore upload Dropbox: ' . $upload_result['message']
            );
        }
        
        // Salva nel database
        $file_id = self::save_file_to_database($upload_result['data'], $user, $file);
        
        if (!$file_id) {
            // Se fallisce il salvataggio, prova a eliminare da Dropbox
            $dropbox->delete($dropbox_path);
            return array(
                'success' => false,
                'message' => 'Errore salvataggio database'
            );
        }
        
        // Log attività
        Naval_EGT_Activity_Logger::log_activity(
            $user['id'],
            $user['user_code'],
            'UPLOAD',
            $file_name,
            $dropbox_path,
            $file['size'],
            array(
                'original_name' => $original_name,
                'admin_upload' => $is_admin_upload,
                'mime_type' => $file['type']
            )
        );
        
        return array(
            'success' => true,
            'file_id' => $file_id,
            'file_name' => $file_name,
            'dropbox_path' => $dropbox_path
        );
    }
    
    /**
     * Valida un file prima dell'upload
     */
    private static function validate_file($file) {
        // Verifica errori PHP
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_messages = array(
                UPLOAD_ERR_INI_SIZE => 'File troppo grande (limite PHP)',
                UPLOAD_ERR_FORM_SIZE => 'File troppo grande (limite form)',
                UPLOAD_ERR_PARTIAL => 'Upload incompleto',
                UPLOAD_ERR_NO_FILE => 'Nessun file selezionato',
                UPLOAD_ERR_NO_TMP_DIR => 'Cartella temporanea mancante',
                UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere su disco',
                UPLOAD_ERR_EXTENSION => 'Upload bloccato da estensione PHP'
            );
            
            return array(
                'valid' => false,
                'message' => $error_messages[$file['error']] ?? 'Errore upload sconosciuto'
            );
        }
        
        // Verifica dimensione file
        $max_size = intval(Naval_EGT_Database::get_setting('max_file_size', '20971520')); // 20MB default
        if ($file['size'] > $max_size) {
            return array(
                'valid' => false,
                'message' => 'File troppo grande. Massimo consentito: ' . size_format($max_size)
            );
        }
        
        // Verifica estensione file
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_types = explode(',', Naval_EGT_Database::get_setting('allowed_file_types', 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,dwg,dxf,zip,rar'));
        $allowed_types = array_map('trim', $allowed_types);
        
        if (!in_array($file_ext, $allowed_types)) {
            return array(
                'valid' => false,
                'message' => 'Tipo file non consentito. Tipi permessi: ' . implode(', ', $allowed_types)
            );
        }
        
        // Verifica nome file
        if (empty($file['name']) || strlen($file['name']) > 255) {
            return array(
                'valid' => false,
                'message' => 'Nome file non valido'
            );
        }
        
        // Verifica che il file esista e sia leggibile
        if (!is_uploaded_file($file['tmp_name']) || !is_readable($file['tmp_name'])) {
            return array(
                'valid' => false,
                'message' => 'File non accessibile'
            );
        }
        
        return array('valid' => true);
    }
    
    /**
     * Genera nome file univoco se necessario
     */
    private static function generate_unique_filename($original_name, $folder_path, $dropbox) {
        $file_name = $original_name;
        $counter = 1;
        
        while (true) {
            $test_path = $folder_path . '/' . $file_name;
            $metadata_result = $dropbox->get_metadata($test_path);
            
            if (!$metadata_result['success']) {
                // File non esiste, possiamo usare questo nome
                break;
            }
            
            // File esiste, genera nuovo nome
            $path_info = pathinfo($original_name);
            $base_name = $path_info['filename'];
            $extension = isset($path_info['extension']) ? '.' . $path_info['extension'] : '';
            
            $file_name = $base_name . '_' . $counter . $extension;
            $counter++;
            
            // Limite di sicurezza
            if ($counter > 100) {
                $file_name = $base_name . '_' . time() . $extension;
                break;
            }
        }
        
        return $file_name;
    }
    
    /**
     * Salva informazioni file nel database
     */
    private static function save_file_to_database($dropbox_data, $user, $original_file) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'naval_egt_files',
            array(
                'user_id' => $user['id'],
                'user_code' => $user['user_code'],
                'file_name' => basename($dropbox_data['name']),
                'original_name' => $original_file['name'],
                'file_path' => $dropbox_data['path_display'],
                'dropbox_path' => $dropbox_data['path_lower'],
                'file_size' => $original_file['size'],
                'mime_type' => $original_file['type'],
                'dropbox_id' => $dropbox_data['id'],
                'last_modified' => isset($dropbox_data['server_modified']) ? $dropbox_data['server_modified'] : current_time('mysql'),
                'created_at' => current_time('mysql'),
                'uploaded_by' => isset($_SESSION['naval_egt_admin_upload']) ? 'admin' : 'user'
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Gestisce il download dei file
     */
    public static function handle_download() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        $file_id = intval($_POST['file_id'] ?? $_GET['file_id'] ?? 0);
        
        if (!$file_id) {
            wp_send_json_error('ID file non valido');
        }
        
        // Verifica permessi
        $current_user = Naval_EGT_User_Manager::get_current_user();
        $is_admin = current_user_can('manage_options');
        
        if (!$current_user && !$is_admin) {
            wp_send_json_error('Accesso richiesto');
        }
        
        // Carica info file
        global $wpdb;
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}naval_egt_files WHERE id = %d",
            $file_id
        ), ARRAY_A);
        
        if (!$file) {
            wp_send_json_error('File non trovato');
        }
        
        // Verifica proprietà (solo admin può scaricare file di altri)
        if (!$is_admin && $file['user_id'] != $current_user['id']) {
            wp_send_json_error('Non autorizzato ad accedere a questo file');
        }
        
        // Download da Dropbox
        $dropbox = Naval_EGT_Dropbox::get_instance();
        $download_result = $dropbox->download_file($file['dropbox_path']);
        
        if (!$download_result['success']) {
            wp_send_json_error('Errore nel download: ' . $download_result['message']);
        }
        
        // Log download
        $downloader = $is_admin ? 'admin' : $current_user['user_code'];
        Naval_EGT_Activity_Logger::log_activity(
            $file['user_id'],
            $file['user_code'],
            'DOWNLOAD',
            $file['file_name'],
            $file['dropbox_path'],
            $file['file_size'],
            array('downloaded_by' => $downloader)
        );
        
        // Invia file al browser
        $file_name = $file['original_name'] ?: $file['file_name'];
        
        header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . $file['file_size']);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        echo $download_result['content'];
        exit;
    }
    
    /**
     * Gestisce l'eliminazione dei file
     */
    public static function handle_delete() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        $file_id = intval($_POST['file_id'] ?? 0);
        
        if (!$file_id) {
            wp_send_json_error('ID file non valido');
        }
        
        // Verifica permessi
        $current_user = Naval_EGT_User_Manager::get_current_user();
        $is_admin = current_user_can('manage_options');
        
        if (!$current_user && !$is_admin) {
            wp_send_json_error('Accesso richiesto');
        }
        
        // Carica info file
        global $wpdb;
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}naval_egt_files WHERE id = %d",
            $file_id
        ), ARRAY_A);
        
        if (!$file) {
            wp_send_json_error('File non trovato');
        }
        
        // Verifica proprietà (solo admin può eliminare file di altri)
        if (!$is_admin && $file['user_id'] != $current_user['id']) {
            wp_send_json_error('Non autorizzato ad eliminare questo file');
        }
        
        // Elimina da Dropbox (opzionale - potrebbe essere configurabile)
        $delete_from_dropbox = Naval_EGT_Database::get_setting('delete_from_dropbox', '0') === '1';
        
        if ($delete_from_dropbox) {
            $dropbox = Naval_EGT_Dropbox::get_instance();
            if ($dropbox->is_configured()) {
                $dropbox->delete($file['dropbox_path']);
            }
        }
        
        // Elimina dal database
        $result = $wpdb->delete(
            $wpdb->prefix . 'naval_egt_files',
            array('id' => $file_id),
            array('%d')
        );
        
        if ($result) {
            // Log eliminazione
            $deleter = $is_admin ? 'admin' : $current_user['user_code'];
            Naval_EGT_Activity_Logger::log_activity(
                $file['user_id'],
                $file['user_code'],
                'DELETE',
                $file['file_name'],
                $file['dropbox_path'],
                $file['file_size'],
                array(
                    'deleted_by' => $deleter,
                    'deleted_from_dropbox' => $delete_from_dropbox
                )
            );
            
            wp_send_json_success('File eliminato con successo');
        } else {
            wp_send_json_error('Errore nell\'eliminazione del file');
        }
    }
    
    /**
     * Ottiene informazioni su un file
     */
    public function get_file_info() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        $file_id = intval($_POST['file_id'] ?? 0);
        
        if (!$file_id) {
            wp_send_json_error('ID file non valido');
        }
        
        global $wpdb;
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT f.*, u.nome, u.cognome FROM {$wpdb->prefix}naval_egt_files f 
             LEFT JOIN {$wpdb->prefix}naval_egt_users u ON f.user_id = u.id 
             WHERE f.id = %d",
            $file_id
        ), ARRAY_A);
        
        if (!$file) {
            wp_send_json_error('File non trovato');
        }
        
        // Verifica permessi
        $current_user = Naval_EGT_User_Manager::get_current_user();
        $is_admin = current_user_can('manage_options');
        
        if (!$is_admin && (!$current_user || $file['user_id'] != $current_user['id'])) {
            wp_send_json_error('Non autorizzato');
        }
        
        wp_send_json_success(array(
            'id' => $file['id'],
            'name' => $file['original_name'] ?: $file['file_name'],
            'size' => size_format($file['file_size']),
            'size_bytes' => $file['file_size'],
            'type' => $file['mime_type'],
            'uploaded' => mysql2date('d/m/Y H:i', $file['created_at']),
            'user' => $file['nome'] . ' ' . $file['cognome'],
            'user_code' => $file['user_code']
        ));
    }
    
    /**
     * Ottiene lista file per un utente
     */
    public static function get_user_files($user_id, $options = array()) {
        global $wpdb;
        
        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'search' => '',
            'order_by' => 'created_at',
            'order' => 'DESC'
        );
        
        $options = array_merge($defaults, $options);
        
        // Query base
        $where = array();
        $where_values = array($user_id);
        
        $where[] = "user_id = %d";
        
        // Filtro ricerca
        if (!empty($options['search'])) {
            $where[] = "(file_name LIKE %s OR original_name LIKE %s)";
            $search = '%' . $wpdb->esc_like($options['search']) . '%';
            $where_values[] = $search;
            $where_values[] = $search;
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Query conteggio
        $count_query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}naval_egt_files WHERE {$where_clause}",
            $where_values
        );
        $total = $wpdb->get_var($count_query);
        
        // Query file
        $offset = ($options['page'] - 1) * $options['per_page'];
        $order_by = sanitize_sql_orderby($options['order_by'] . ' ' . $options['order']);
        
        $files_query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}naval_egt_files 
             WHERE {$where_clause} 
             ORDER BY {$order_by} 
             LIMIT %d OFFSET %d",
            array_merge($where_values, array($options['per_page'], $offset))
        );
        
        $files = $wpdb->get_results($files_query, ARRAY_A);
        
        return array(
            'files' => $files,
            'total' => intval($total),
            'pages' => ceil($total / $options['per_page']),
            'current_page' => $options['page'],
            'per_page' => $options['per_page']
        );
    }
    
    /**
     * Ottiene statistiche file
     */
    public static function get_file_stats($user_id = null) {
        global $wpdb;
        
        $where = $user_id ? $wpdb->prepare("WHERE user_id = %d", $user_id) : "";
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_files,
                SUM(file_size) as total_size,
                AVG(file_size) as avg_size,
                MAX(created_at) as last_upload
             FROM {$wpdb->prefix}naval_egt_files {$where}",
            ARRAY_A
        );
        
        return array(
            'total_files' => intval($stats['total_files'] ?? 0),
            'total_size' => intval($stats['total_size'] ?? 0),
            'avg_size' => floatval($stats['avg_size'] ?? 0),
            'last_upload' => $stats['last_upload']
        );
    }
    
    /**
     * Sincronizza file da Dropbox per un utente
     */
    public static function sync_user_files($user_id) {
        $user = Naval_EGT_User_Manager::get_user_by_id($user_id);
        if (!$user) {
            return array('success' => false, 'message' => 'Utente non trovato');
        }
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        return $dropbox->sync_user_folder($user['user_code'], $user_id);
    }
}