<?php
/**
 * Classe per l'esportazione di dati
 */

if (!defined('ABSPATH')) {
    exit;
}

class Naval_EGT_Export {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_naval_egt_export_users', array($this, 'export_users'));
        add_action('wp_ajax_naval_egt_export_logs', array($this, 'export_logs'));
    }
    
    /**
     * Esporta utenti in formato Excel/CSV
     */
    public static function export_users() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        $format = sanitize_text_field($_POST['format'] ?? 'xlsx');
        $filters = array();
        
        // Applica filtri se presenti
        if (!empty($_POST['status'])) {
            $filters['status'] = sanitize_text_field($_POST['status']);
        }
        
        if (!empty($_POST['search'])) {
            $filters['search'] = sanitize_text_field($_POST['search']);
        }
        
        $users = Naval_EGT_User_Manager::get_users($filters);
        
        if ($format === 'xlsx') {
            self::export_users_excel($users);
        } elseif ($format === 'pdf') {
            self::export_users_pdf($users);
        } else {
            self::export_users_csv($users);
        }
    }
    
    /**
     * Esporta utenti in formato CSV
     */
    private static function export_users_csv($users) {
        $filename = 'naval_egt_utenti_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Cache-Control: no-cache, must-revalidate');
        
        $output = fopen('php://output', 'w');
        
        // BOM per UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Intestazioni
        fputcsv($output, array(
            'Codice Utente',
            'Nome',
            'Cognome',
            'Email',
            'Username',
            'Telefono',
            'Ragione Sociale',
            'Partita IVA',
            'Status',
            'Cartella Dropbox',
            'Ultimo Accesso',
            'Data Registrazione'
        ));
        
        // Dati utenti
        foreach ($users as $user) {
            fputcsv($output, array(
                $user['user_code'],
                $user['nome'],
                $user['cognome'],
                $user['email'],
                $user['username'],
                $user['telefono'] ?: '',
                $user['ragione_sociale'] ?: '',
                $user['partita_iva'] ?: '',
                $user['status'],
                $user['dropbox_folder'] ?: '',
                $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Mai',
                date('d/m/Y H:i', strtotime($user['created_at']))
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Esporta utenti in formato Excel (simulato con HTML)
     */
    private static function export_users_excel($users) {
        $filename = 'naval_egt_utenti_' . date('Y-m-d_H-i-s') . '.xls';
        
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Cache-Control: no-cache, must-revalidate');
        
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>';
        echo '<body>';
        echo '<table border="1">';
        
        // Intestazioni
        echo '<tr style="background-color: #4285f4; color: white; font-weight: bold;">';
        echo '<th>Codice Utente</th>';
        echo '<th>Nome</th>';
        echo '<th>Cognome</th>';
        echo '<th>Email</th>';
        echo '<th>Username</th>';
        echo '<th>Telefono</th>';
        echo '<th>Ragione Sociale</th>';
        echo '<th>P.IVA</th>';
        echo '<th>Status</th>';
        echo '<th>Cartella Dropbox</th>';
        echo '<th>Ultimo Accesso</th>';
        echo '<th>Data Registrazione</th>';
        echo '</tr>';
        
        // Dati utenti
        foreach ($users as $user) {
            $status_color = $user['status'] === 'ATTIVO' ? '#28a745' : '#dc3545';
            echo '<tr>';
            echo '<td>' . esc_html($user['user_code']) . '</td>';
            echo '<td>' . esc_html($user['nome']) . '</td>';
            echo '<td>' . esc_html($user['cognome']) . '</td>';
            echo '<td>' . esc_html($user['email']) . '</td>';
            echo '<td>' . esc_html($user['username']) . '</td>';
            echo '<td>' . esc_html($user['telefono'] ?: '') . '</td>';
            echo '<td>' . esc_html($user['ragione_sociale'] ?: '') . '</td>';
            echo '<td>' . esc_html($user['partita_iva'] ?: '') . '</td>';
            echo '<td style="color: ' . $status_color . '; font-weight: bold;">' . esc_html($user['status']) . '</td>';
            echo '<td>' . esc_html($user['dropbox_folder'] ?: '') . '</td>';
            echo '<td>' . ($user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Mai') . '</td>';
            echo '<td>' . date('d/m/Y H:i', strtotime($user['created_at'])) . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</body></html>';
        exit;
    }
    
    /**
     * Esporta utenti in formato PDF (HTML)
     */
    private static function export_users_pdf($users) {
        $filename = 'naval_egt_utenti_' . date('Y-m-d_H-i-s') . '.html';
        
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<title>Report Utenti Naval EGT</title>';
        echo '<style>';
        echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
        echo 'table { width: 100%; border-collapse: collapse; margin-top: 20px; }';
        echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
        echo 'th { background-color: #4285f4; color: white; }';
        echo '.status-active { color: #28a745; font-weight: bold; }';
        echo '.status-suspended { color: #dc3545; font-weight: bold; }';
        echo '.header { text-align: center; margin-bottom: 30px; }';
        echo '.stats { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }';
        echo '</style>';
        echo '</head>';
        echo '<body>';
        
        echo '<div class="header">';
        echo '<h1>🚢 Naval EGT - Report Utenti</h1>';
        echo '<p>Generato il ' . date('d/m/Y H:i:s') . '</p>';
        echo '</div>';
        
        // Statistiche
        $total = count($users);
        $active = count(array_filter($users, function($u) { return $u['status'] === 'ATTIVO'; }));
        $suspended = $total - $active;
        
        echo '<div class="stats">';
        echo '<h3>Riepilogo</h3>';
        echo '<p><strong>Totale utenti:</strong> ' . $total . '</p>';
        echo '<p><strong>Utenti attivi:</strong> ' . $active . '</p>';
        echo '<p><strong>Utenti sospesi:</strong> ' . $suspended . '</p>';
        echo '</div>';
        
        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Codice</th>';
        echo '<th>Nome Completo</th>';
        echo '<th>Email</th>';
        echo '<th>Azienda</th>';
        echo '<th>Status</th>';
        echo '<th>Registrazione</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($users as $user) {
            $status_class = $user['status'] === 'ATTIVO' ? 'status-active' : 'status-suspended';
            echo '<tr>';
            echo '<td>' . esc_html($user['user_code']) . '</td>';
            echo '<td>' . esc_html($user['nome'] . ' ' . $user['cognome']) . '</td>';
            echo '<td>' . esc_html($user['email']) . '</td>';
            echo '<td>' . esc_html($user['ragione_sociale'] ?: '-') . '</td>';
            echo '<td class="' . $status_class . '">' . esc_html($user['status']) . '</td>';
            echo '<td>' . date('d/m/Y', strtotime($user['created_at'])) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        echo '<div style="margin-top: 30px; text-align: center; color: #666; font-size: 12px;">';
        echo '<p>Naval Engineering & Green Technologies S.r.l.</p>';
        echo '<p>Report generato automaticamente dal sistema Naval EGT</p>';
        echo '</div>';
        
        echo '</body>';
        echo '</html>';
        exit;
    }
    
    /**
     * Esporta log attività
     */
    public static function export_logs() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $filters = array();
        
        // Applica filtri
        if (!empty($_POST['user_id'])) {
            $filters['user_id'] = intval($_POST['user_id']);
        }
        
        if (!empty($_POST['action'])) {
            $filters['action'] = sanitize_text_field($_POST['action']);
        }
        
        if (!empty($_POST['date_from'])) {
            $filters['date_from'] = sanitize_text_field($_POST['date_from']);
        }
        
        if (!empty($_POST['date_to'])) {
            $filters['date_to'] = sanitize_text_field($_POST['date_to']);
        }
        
        // Usa la funzione esistente per CSV
        if ($format === 'csv') {
            Naval_EGT_Activity_Logger::export_logs_csv($filters);
        } else {
            self::export_logs_html($filters);
        }
    }
    
    /**
     * Esporta log in formato HTML
     */
    private static function export_logs_html($filters) {
        $logs = Naval_EGT_Activity_Logger::get_logs($filters, 5000, 0);
        
        $filename = 'naval_egt_logs_' . date('Y-m-d_H-i-s') . '.html';
        
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<title>Log Attività Naval EGT</title>';
        echo '<style>';
        echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
        echo 'table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }';
        echo 'th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }';
        echo 'th { background-color: #4285f4; color: white; }';
        echo '.action-upload { color: #28a745; }';
        echo '.action-download { color: #17a2b8; }';
        echo '.action-login { color: #6f42c1; }';
        echo '.action-registration { color: #fd7e14; }';
        echo '.header { text-align: center; margin-bottom: 30px; }';
        echo '</style>';
        echo '</head>';
        echo '<body>';
        
        echo '<div class="header">';
        echo '<h1>🚢 Naval EGT - Log Attività</h1>';
        echo '<p>Generato il ' . date('d/m/Y H:i:s') . '</p>';
        echo '<p>Totale record: ' . count($logs) . '</p>';
        echo '</div>';
        
        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Data/Ora</th>';
        echo '<th>Utente</th>';
        echo '<th>Azione</th>';
        echo '<th>File</th>';
        echo '<th>Dimensione</th>';
        echo '<th>IP</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($logs as $log) {
            $action_class = 'action-' . strtolower($log['action']);
            $file_size = $log['file_size'] > 0 ? size_format($log['file_size']) : '';
            
            echo '<tr>';
            echo '<td>' . date('d/m/Y H:i:s', strtotime($log['created_at'])) . '</td>';
            echo '<td>' . esc_html($log['user_name'] ?: 'Sistema') . '</td>';
            echo '<td class="' . $action_class . '">' . esc_html($log['action']) . '</td>';
            echo '<td>' . esc_html($log['file_name'] ?: '-') . '</td>';
            echo '<td>' . $file_size . '</td>';
            echo '<td>' . esc_html($log['ip_address']) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        echo '<div style="margin-top: 30px; text-align: center; color: #666; font-size: 12px;">';
        echo '<p>Naval Engineering & Green Technologies S.r.l.</p>';
        echo '<p>Report generato automaticamente dal sistema Naval EGT</p>';
        echo '</div>';
        
        echo '</body>';
        echo '</html>';
        exit;
    }
    
    /**
     * Esporta statistiche generali
     */
    public static function export_general_stats() {
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        $stats = Naval_EGT_Database::get_user_stats();
        $log_stats = Naval_EGT_Activity_Logger::get_log_stats(30);
        $file_stats = Naval_EGT_File_Manager::get_file_stats();
        
        $filename = 'naval_egt_statistiche_' . date('Y-m-d_H-i-s') . '.html';
        
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<title>Statistiche Naval EGT</title>';
        echo '<style>';
        echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
        echo '.stat-box { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 15px 0; display: inline-block; width: 200px; text-align: center; margin-right: 20px; }';
        echo '.stat-number { font-size: 36px; font-weight: bold; color: #4285f4; }';
        echo '.stat-label { font-size: 14px; color: #666; }';
        echo 'table { width: 100%; border-collapse: collapse; margin: 20px 0; }';
        echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
        echo 'th { background-color: #4285f4; color: white; }';
        echo '</style>';
        echo '</head>';
        echo '<body>';
        
        echo '<h1>📊 Naval EGT - Statistiche Generali</h1>';
        echo '<p>Generato il ' . date('d/m/Y H:i:s') . '</p>';
        
        echo '<h2>Utenti</h2>';
        echo '<div class="stat-box">';
        echo '<div class="stat-number">' . $stats['total_users'] . '</div>';
        echo '<div class="stat-label">Utenti Totali</div>';
        echo '</div>';
        
        echo '<div class="stat-box">';
        echo '<div class="stat-number">' . $stats['active_users'] . '</div>';
        echo '<div class="stat-label">Utenti Attivi</div>';
        echo '</div>';
        
        echo '<div class="stat-box">';
        echo '<div class="stat-number">' . $stats['pending_users'] . '</div>';
        echo '<div class="stat-label">In Attesa</div>';
        echo '</div>';
        
        echo '<div class="stat-box">';
        echo '<div class="stat-number">' . $stats['total_files'] . '</div>';
        echo '<div class="stat-label">File Totali</div>';
        echo '</div>';
        
        echo '<div style="clear: both;"></div>';
        
        // Attività per azione (ultimi 30 giorni)
        if (!empty($log_stats['by_action'])) {
            echo '<h2>Attività per Tipo (ultimi 30 giorni)</h2>';
            echo '<table>';
            echo '<tr><th>Azione</th><th>Conteggio</th></tr>';
            foreach ($log_stats['by_action'] as $action_stat) {
                echo '<tr>';
                echo '<td>' . esc_html($action_stat['action']) . '</td>';
                echo '<td>' . $action_stat['count'] . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        
        // File per tipo
        if (!empty($file_stats['by_type'])) {
            echo '<h2>File per Tipo</h2>';
            echo '<table>';
            echo '<tr><th>Estensione</th><th>Conteggio</th><th>Dimensione Totale</th></tr>';
            foreach ($file_stats['by_type'] as $type_stat) {
                echo '<tr>';
                echo '<td>.' . esc_html($type_stat['extension']) . '</td>';
                echo '<td>' . $type_stat['count'] . '</td>';
                echo '<td>' . size_format($type_stat['total_size']) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        
        echo '<div style="margin-top: 50px; text-align: center; color: #666; font-size: 12px;">';
        echo '<p>Naval Engineering & Green Technologies S.r.l.</p>';
        echo '<p>Report generato automaticamente dal sistema Naval EGT</p>';
        echo '</div>';
        
        echo '</body>';
        echo '</html>';
        exit;
    }
}