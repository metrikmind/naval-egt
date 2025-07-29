<?php
/**
 * Naval EGT FTP Manager Class
 * 
 * Handles FTP file operations for client area
 */

if (!defined('ABSPATH')) {
    exit;
}

class NavalEGT_FTPManager {
    
    private $ftp_base_path;
    private $max_file_size;
    private $allowed_extensions;
    
    public function __construct() {
        $this->ftp_base_path = wp_upload_dir()['basedir'] . '/naval-egt-ftp/';
        $this->max_file_size = 50 * 1024 * 1024; // 50MB default
        $this->allowed_extensions = array(
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'rtf', 'zip', 'rar', '7z',
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff',
            'dwg', 'dxf', 'step', 'stp', 'iges', 'igs'
        );
        
        add_action('init', array($this, 'init'));
        add_action('naval_egt_create_ftp_folder', array($this, 'create_physical_folder'), 10, 2);
    }
    
    public function init() {
        // Create base FTP directory if it doesn't exist
        if (!file_exists($this->ftp_base_path)) {
            wp_mkdir_p($this->ftp_base_path);
            
            // Add .htaccess for security
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "deny from all\n";
            file_put_contents($this->ftp_base_path . '.htaccess', $htaccess_content);
        }
        
        // Load settings
        $this->load_settings();
    }
    
    private function load_settings() {
        $this->max_file_size = get_option('naval_egt_max_file_size', 50) * 1024 * 1024;
        $extensions = get_option('naval_egt_allowed_extensions', '');
        
        if (!empty($extensions)) {
            $this->allowed_extensions = array_map('trim', explode(',', $extensions));
        }
    }
    
    public function create_physical_folder($user_id, $folder_name) {
        $folder_path = $this->ftp_base_path . $folder_name . '/';
        
        if (!file_exists($folder_path)) {
            wp_mkdir_p($folder_path);
            
            // Create subfolders
            wp_mkdir_p($folder_path . 'uploads/');
            wp_mkdir_p($folder_path . 'downloads/');
            wp_mkdir_p($folder_path . 'shared/');
            
            // Add .htaccess for security
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "Order Allow,Deny\n";
            $htaccess_content .= "Deny from all\n";
            file_put_contents($folder_path . '.htaccess', $htaccess_content);
            
            // Add readme file
            $readme_content = "Cartella FTP dedicata per " . get_user_by('id', $user_id)->display_name . "\n";
            $readme_content .= "Creata il: " . date('d/m/Y H:i:s') . "\n\n";
            $readme_content .= "Istruzioni:\n";
            $readme_content .= "- uploads/: Carica qui i tuoi file\n";
            $readme_content .= "- downloads/: Scarica i file condivisi da Naval EGT\n";
            $readme_content .= "- shared/: File condivisi tra te e Naval EGT\n";
            file_put_contents($folder_path . 'README.txt', $readme_content);
        }
        
        return $folder_path;
    }
    
    public function get_user_folder_path($user_id) {
        $folder_name = get_user_meta($user_id, 'naval_egt_ftp_folder', true);
        
        if (empty($folder_name)) {
            return false;
        }
        
        return $this->ftp_base_path . $folder_name . '/';
    }
    
    public function get_user_folder_url($user_id) {
        $folder_name = get_user_meta($user_id, 'naval_egt_ftp_folder', true);
        
        if (empty($folder_name)) {
            return false;
        }
        
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/naval-egt-ftp/' . $folder_name . '/';
    }
    
    public function list_files($user_id, $subfolder = '') {
        if (!naval_egt_user_can_access($user_id)) {
            return false;
        }
        
        $folder_path = $this->get_user_folder_path($user_id);
        if (!$folder_path) {
            return false;
        }
        
        $full_path = $folder_path . $subfolder;
        
        if (!file_exists($full_path) || !is_dir($full_path)) {
            return array();
        }
        
        $files = array();
        $items = scandir($full_path);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.htaccess') {
                continue;
            }
            
            $item_path = $full_path . '/' . $item;
            $item_info = array(
                'name' => $item,
                'path' => $subfolder . $item,
                'type' => is_dir($item_path) ? 'folder' : 'file',
                'size' => is_file($item_path) ? filesize($item_path) : 0,
                'modified' => filemtime($item_path),
                'extension' => is_file($item_path) ? pathinfo($item, PATHINFO_EXTENSION) : '',
                'can_download' => $this->can_user_download($user_id, $subfolder . $item)
            );
            
            $files[] = $item_info;
        }
        
        // Sort: folders first, then files alphabetically
        usort($files, function($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'folder' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });
        
        return $files;
    }
    
    public function upload_file($user_id, $file, $subfolder = 'uploads/') {
        if (!naval_egt_user_can_access($user_id)) {
            return array('success' => false, 'message' => __('Accesso negato.', 'naval-egt'));
        }
        
        // Validate file
        $validation = $this->validate_file($file);
        if (!$validation['valid']) {
            return array('success' => false, 'message' => $validation['message']);
        }
        
        $folder_path = $this->get_user_folder_path($user_id);
        if (!$folder_path) {
            return array('success' => false, 'message' => __('Cartella utente non trovata.', 'naval-egt'));
        }
        
        $upload_path = $folder_path . $subfolder;
        if (!file_exists($upload_path)) {
            wp_mkdir_p($upload_path);
        }
        
        // Generate unique filename if file exists
        $filename = $this->generate_unique_filename($upload_path, $file['name']);
        $destination = $upload_path . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Log the upload
            $this->log_file_action($user_id, 'upload', $filename, $subfolder . $filename, $file['size']);
            
            // Send notification email to admin
            $this->notify_admin_file_upload($user_id, $filename);
            
            return array(
                'success' => true, 
                'message' => __('File caricato con successo.', 'naval-egt'),
                'filename' => $filename
            );
        }
        
        return array('success' => false, 'message' => __('Errore durante il caricamento del file.', 'naval-egt'));
    }
    
    public function download_file($user_id, $file_path) {
        if (!naval_egt_user_can_access($user_id)) {
            return false;
        }
        
        if (!$this->can_user_download($user_id, $file_path)) {
            return false;
        }
        
        $folder_path = $this->get_user_folder_path($user_id);
        $full_path = $folder_path . $file_path;
        
        if (!file_exists($full_path) || !is_file($full_path)) {
            return false;
        }
        
        // Log the download
        $this->log_file_action($user_id, 'download', basename($file_path), $file_path, filesize($full_path));
        
        // Force download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($full_path) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($full_path));
        
        readfile($full_path);
        exit;
    }
    
    public function delete_file($user_id, $file_path) {
        if (!naval_egt_user_can_access($user_id)) {
            return array('success' => false, 'message' => __('Accesso negato.', 'naval-egt'));
        }
        
        // Users can only delete files from their uploads folder
        if (strpos($file_path, 'uploads/') !== 0) {
            return array('success' => false, 'message' => __('Puoi eliminare solo i file che hai caricato.', 'naval-egt'));
        }
        
        $folder_path = $this->get_user_folder_path($user_id);
        $full_path = $folder_path . $file_path;
        
        if (!file_exists($full_path) || !is_file($full_path)) {
            return array('success' => false, 'message' => __('File non trovato.', 'naval-egt'));
        }
        
        if (unlink($full_path)) {
            $this->log_file_action($user_id, 'delete', basename($file_path), $file_path, 0);
            return array('success' => true, 'message' => __('File eliminato con successo.', 'naval-egt'));
        }
        
        return array('success' => false, 'message' => __('Errore durante l\'eliminazione del file.', 'naval-egt'));
    }
    
    public function create_folder($user_id, $folder_name, $parent_folder = 'uploads/') {
        if (!naval_egt_user_can_access($user_id)) {
            return array('success' => false, 'message' => __('Accesso negato.', 'naval-egt'));
        }
        
        // Sanitize folder name
        $folder_name = sanitize_file_name($folder_name);
        
        if (empty($folder_name)) {
            return array('success' => false, 'message' => __('Nome cartella non valido.', 'naval-egt'));
        }
        
        $folder_path = $this->get_user_folder_path($user_id);
        $new_folder_path = $folder_path . $parent_folder . $folder_name;
        
        if (file_exists($new_folder_path)) {
            return array('success' => false, 'message' => __('Una cartella con questo nome esiste già.', 'naval-egt'));
        }
        
        if (wp_mkdir_p($new_folder_path)) {
            $this->log_file_action($user_id, 'create_folder', $folder_name, $parent_folder . $folder_name, 0);
            return array('success' => true, 'message' => __('Cartella creata con successo.', 'naval-egt'));
        }
        
        return array('success' => false, 'message' => __('Errore durante la creazione della cartella.', 'naval-egt'));
    }
    
    private function validate_file($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return array(
                'valid' => false,
                'message' => $this->get_upload_error_message($file['error'])
            );
        }
        
        // Check file size
        if ($file['size'] > $this->max_file_size) {
            return array(
                'valid' => false,
                'message' => sprintf(__('Il file è troppo grande. Dimensione massima: %s', 'naval-egt'), 
                                   size_format($this->max_file_size))
            );
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowed_extensions)) {
            return array(
                'valid' => false,
                'message' => sprintf(__('Tipo di file non consentito. Estensioni permesse: %s', 'naval-egt'), 
                                   implode(', ', $this->allowed_extensions))
            );
        }
        
        // Check for malicious files
        if ($this->is_malicious_file($file['tmp_name'], $extension)) {
            return array(
                'valid' => false,
                'message' => __('File non sicuro rilevato.', 'naval-egt')
            );
        }
        
        return array('valid' => true);
    }
    
    private function is_malicious_file($file_path, $extension) {
        // Basic checks for malicious files
        $dangerous_extensions = array('php', 'js', 'html', 'htm', 'exe', 'bat', 'sh', 'cmd');
        
        if (in_array($extension, $dangerous_extensions)) {
            return true;
        }
        
        // Check file content for PHP tags
        $content = file_get_contents($file_path, false, null, 0, 1024);
        if (strpos($content, '<?php') !== false || strpos($content, '<?=') !== false) {
            return true;
        }
        
        return false;
    }
    
    private function can_user_download($user_id, $file_path) {
        // Users can download from any subfolder except system files
        $restricted_files = array('.htaccess', 'index.php', 'README.txt');
        $filename = basename($file_path);
        
        return !in_array($filename, $restricted_files);
    }
    
    private function generate_unique_filename($path, $filename) {
        $info = pathinfo($filename);
        $name = $info['filename'];
        $extension = isset($info['extension']) ? '.' . $info['extension'] : '';
        
        $counter = 1;
        $new_filename = $filename;
        
        while (file_exists($path . $new_filename)) {
            $new_filename = $name . '_' . $counter . $extension;
            $counter++;
        }
        
        return $new_filename;
    }
    
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return __('Il file è troppo grande.', 'naval-egt');
            case UPLOAD_ERR_PARTIAL:
                return __('Il file è stato caricato solo parzialmente.', 'naval-egt');
            case UPLOAD_ERR_NO_FILE:
                return __('Nessun file selezionato.', 'naval-egt');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Cartella temporanea mancante.', 'naval-egt');
            case UPLOAD_ERR_CANT_WRITE:
                return __('Impossibile scrivere il file su disco.', 'naval-egt');
            case UPLOAD_ERR_EXTENSION:
                return __('Caricamento del file bloccato da un\'estensione.', 'naval-egt');
            default:
                return __('Errore sconosciuto durante il caricamento.', 'naval-egt');
        }
    }
    
    private function log_file_action($user_id, $action, $file_name, $file_path, $file_size) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'naval_egt_ftp_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'action' => $action,
                'file_name' => $file_name,
                'file_path' => $file_path,
                'file_size' => $file_size,
                'ip_address' => $this->get_client_ip()
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s')
        );
    }
    
    private function notify_admin_file_upload($user_id, $filename) {
        $user = get_user_by('id', $user_id);
        $admin_email = get_option('admin_email');
        
        $subject = __('Nuovo file caricato - Naval EGT', 'naval-egt');
        $message = sprintf(
            __("Un nuovo file è stato caricato nell'area riservata.\n\nUtente: %s (%s)\nFile: %s\nData: %s\n\nPuoi gestire i file dall'area di amministrazione.", 'naval-egt'),
            $user->display_name,
            $user->user_email,
            $filename,
            date('d/m/Y H:i:s')
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    public function get_folder_stats($user_id) {
        if (!naval_egt_user_can_access($user_id)) {
            return false;
        }
        
        $folder_path = $this->get_user_folder_path($user_id);
        if (!$folder_path) {
            return false;
        }
        
        $stats = array(
            'total_files' => 0,
            'total_size' => 0,
            'uploads_count' => 0,
            'uploads_size' => 0,
            'downloads_count' => 0,
            'downloads_size' => 0
        );
        
        // Count files in each subfolder
        $subfolders = array('uploads', 'downloads', 'shared');
        
        foreach ($subfolders as $subfolder) {
            $subfolder_path = $folder_path . $subfolder . '/';
            if (is_dir($subfolder_path)) {
                $folder_stats = $this->count_files_recursive($subfolder_path);
                $stats['total_files'] += $folder_stats['count'];
                $stats['total_size'] += $folder_stats['size'];
                $stats[$subfolder . '_count'] = $folder_stats['count'];
                $stats[$subfolder . '_size'] = $folder_stats['size'];
            }
        }
        
        return $stats;
    }
    
    private function count_files_recursive($path) {
        $count = 0;
        $size = 0;
        
        if (is_dir($path)) {
            $items = scandir($path);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || $item === '.htaccess') {
                    continue;
                }
                
                $item_path = $path . '/' . $item;
                if (is_file($item_path)) {
                    $count++;
                    $size += filesize($item_path);
                } elseif (is_dir($item_path)) {
                    $sub_stats = $this->count_files_recursive($item_path);
                    $count += $sub_stats['count'];
                    $size += $sub_stats['size'];
                }
            }
        }
        
        return array('count' => $count, 'size' => $size);
    }
    
    public function get_recent_activity($user_id, $limit = 10) {
        global $wpdb;
        
        if (!naval_egt_user_can_access($user_id)) {
            return false;
        }
        
        $table_name = $wpdb->prefix . 'naval_egt_ftp_logs';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id,
            $limit
        ));
        
        return $results;
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
    
    public function cleanup_old_logs($days = 90) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'naval_egt_ftp_logs';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
}
?>