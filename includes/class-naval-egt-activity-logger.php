<?php
/**
 * Classe per il logging delle attività
 */

if (!defined('ABSPATH')) {
    exit;
}

class Naval_EGT_Activity_Logger {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor
    }
    
    /**
     * Registra un'attività
     */
    public static function log_activity($user_id, $user_code, $action, $file_name = null, $file_path = null, $file_size = 0, $details = null) {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . 'naval_egt_activity_logs';
        
        $log_data = array(
            'user_id' => $user_id,
            'user_code' => $user_code,
            'action' => $action,
            'file_name' => $file_name,
            'file_path' => $file_path,
            'file_size' => $file_size,
            'ip_address' => self::get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '',
            'details' => $details ? json_encode($details) : null,
            'created_at' => current_time('mysql')
        );
        
        return $wpdb->insert($table_logs, $log_data);
    }
    
    /**
     * Ottiene i log con filtri
     */
    public static function get_logs($filters = array(), $limit = 50, $offset = 0) {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . 'naval_egt_activity_logs';
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        $where = array('1=1');
        $values = array();
        
        if (!empty($filters['user_id'])) {
            $where[] = 'l.user_id = %d';
            $values[] = $filters['user_id'];
        }
        
        if (!empty($filters['user_code'])) {
            $where[] = 'l.user_code = %s';
            $values[] = $filters['user_code'];
        }
        
        if (!empty($filters['action'])) {
            $where[] = 'l.action = %s';
            $values[] = $filters['action'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'l.created_at >= %s';
            $values[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'l.created_at <= %s';
            $values[] = $filters['date_to'] . ' 23:59:59';
        }
        
        if (!empty($filters['file_name'])) {
            $search = '%' . $wpdb->esc_like($filters['file_name']) . '%';
            $where[] = 'l.file_name LIKE %s';
            $values[] = $search;
        }
        
        $sql = "SELECT l.*, CONCAT(COALESCE(u.nome, ''), ' ', COALESCE(u.cognome, '')) as user_name
                FROM $table_logs l
                LEFT JOIN $table_users u ON l.user_id = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY l.created_at DESC
                LIMIT %d OFFSET %d";
        
        $values = array_merge($values, array($limit, $offset));
        
        return $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
    }
    
    /**
     * Conta i log con filtri
     */
    public static function count_logs($filters = array()) {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . 'naval_egt_activity_logs';
        
        $where = array('1=1');
        $values = array();
        
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = %d';
            $values[] = $filters['user_id'];
        }
        
        if (!empty($filters['user_code'])) {
            $where[] = 'user_code = %s';
            $values[] = $filters['user_code'];
        }
        
        if (!empty($filters['action'])) {
            $where[] = 'action = %s';
            $values[] = $filters['action'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= %s';
            $values[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= %s';
            $values[] = $filters['date_to'] . ' 23:59:59';
        }
        
        if (!empty($filters['file_name'])) {
            $search = '%' . $wpdb->esc_like($filters['file_name']) . '%';
            $where[] = 'file_name LIKE %s';
            $values[] = $search;
        }
        
        $sql = "SELECT COUNT(*) FROM $table_logs WHERE " . implode(' AND ', $where);
        
        if (!empty($values)) {
            return (int)$wpdb->get_var($wpdb->prepare($sql, $values));
        } else {
            return (int)$wpdb->get_var($sql);
        }
    }
    
    /**
     * Ottiene i log recenti
     */
    public static function get_recent_logs($limit = 10) {
        return self::get_logs(array(), $limit, 0);
    }
    
    /**
     * Pulisce i log più vecchi di X giorni
     */
    public static function clean_old_logs($days = 90) {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . 'naval_egt_activity_logs';
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_logs WHERE created_at < %s",
            $cutoff_date
        ));
        
        return array(
            'success' => true,
            'message' => "Eliminati $deleted log più vecchi di $days giorni",
            'deleted_count' => $deleted
        );
    }
    
    /**
     * Svuota tutti i log
     */
    public static function clear_all_logs() {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . 'naval_egt_activity_logs';
        
        $deleted = $wpdb->query("DELETE FROM $table_logs");
        
        return array(
            'success' => true,
            'message' => "Eliminati tutti i $deleted log",
            'deleted_count' => $deleted
        );
    }
    
    /**
     * Ottiene statistiche sui log
     */
    public static function get_log_stats($days = 30) {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . 'naval_egt_activity_logs';
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        // Totali per azione negli ultimi X giorni
        $by_action = $wpdb->get_results($wpdb->prepare("
            SELECT action, COUNT(*) as count
            FROM $table_logs
            WHERE created_at >= %s
            GROUP BY action
            ORDER BY count DESC
        ", $cutoff_date), ARRAY_A);
        
        // Attività giornaliera negli ultimi 7 giorni
        $daily_activity = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as count
            FROM $table_logs
            WHERE created_at >= %s
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ", date('Y-m-d H:i:s', strtotime('-7 days'))), ARRAY_A);
        
        // Utenti più attivi
        $active_users = $wpdb->get_results($wpdb->prepare("
            SELECT 
                l.user_code,
                CONCAT(COALESCE(u.nome, ''), ' ', COALESCE(u.cognome, '')) as user_name,
                COUNT(*) as activity_count
            FROM $table_logs l
            LEFT JOIN {$wpdb->prefix}naval_egt_users u ON l.user_id = u.id
            WHERE l.created_at >= %s
            GROUP BY l.user_id, l.user_code
            ORDER BY activity_count DESC
            LIMIT 10
        ", $cutoff_date), ARRAY_A);
        
        return array(
            'by_action' => $by_action,
            'daily_activity' => $daily_activity,
            'active_users' => $active_users
        );
    }
    
    /**
     * Ottiene l'IP del client
     */
    private static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip_list = explode(',', $_SERVER[$key]);
                $ip = trim($ip_list[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown';
    }
    
    /**
     * Esporta i log in formato CSV
     */
    public static function export_logs_csv($filters = array()) {
        $logs = self::get_logs($filters, 10000, 0); // Massimo 10k record
        
        $filename = 'naval_egt_logs_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Cache-Control: no-cache, must-revalidate');
        
        $output = fopen('php://output', 'w');
        
        // Intestazioni CSV
        fputcsv($output, array(
            'Data/Ora',
            'Utente',
            'Codice Utente',
            'Azione',
            'File',
            'Dimensione (KB)',
            'IP',
            'Dettagli'
        ));
        
        // Dati
        foreach ($logs as $log) {
            $file_size_kb = $log['file_size'] > 0 ? round($log['file_size'] / 1024, 2) : '';
            $details = $log['details'] ? json_decode($log['details'], true) : '';
            $details_str = is_array($details) ? implode(', ', array_map(function($k, $v) {
                return "$k: $v";
            }, array_keys($details), $details)) : $details;
            
            fputcsv($output, array(
                $log['created_at'],
                $log['user_name'] ?: 'Utente eliminato',
                $log['user_code'],
                $log['action'],
                $log['file_name'] ?: '',
                $file_size_kb,
                $log['ip_address'],
                $details_str
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Log di sistema (per azioni admin)
     */
    public static function log_system_activity($action, $details = null) {
        $current_user = wp_get_current_user();
        $user_info = $current_user->exists() ? $current_user->display_name . ' (' . $current_user->user_login . ')' : 'Sistema';
        
        return self::log_activity(0, 'SYSTEM', $action, null, null, 0, array_merge(
            array('admin_user' => $user_info),
            $details ?: array()
        ));
    }
}