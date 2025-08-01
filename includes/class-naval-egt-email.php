<?php
/**
 * Classe per la gestione delle email
 */

if (!defined('ABSPATH')) {
    exit;
}

class Naval_EGT_Email {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));
    }
    
    /**
     * Imposta il content type per email HTML
     */
    public function set_html_content_type() {
        return 'text/html';
    }
    
    /**
     * Invia email di benvenuto a nuovo utente
     */
    public static function send_welcome_email($user_data) {
        $email_enabled = Naval_EGT_Database::get_setting('email_notifications', '1');
        if ($email_enabled !== '1') {
            return false;
        }
        
        $template = Naval_EGT_Database::get_setting('welcome_email_template', self::get_default_welcome_template());
        
        // Sostituzioni placeholder
        $placeholders = array(
            '{nome}' => $user_data['nome'],
            '{cognome}' => $user_data['cognome'],
            '{user_code}' => $user_data['user_code'],
            '{username}' => $user_data['username'],
            '{email}' => $user_data['email'],
            '{ragione_sociale}' => $user_data['ragione_sociale'] ?? '',
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => home_url(),
            '{login_url}' => self::get_login_url()
        );
        
        $subject = apply_filters('naval_egt_welcome_email_subject', 'Benvenuto nell\'Area Riservata Naval EGT', $user_data);
        $message = str_replace(array_keys($placeholders), array_values($placeholders), $template);
        $message = apply_filters('naval_egt_welcome_email_message', $message, $user_data);
        
        // HTML email
        $html_message = self::wrap_email_template($message, $subject);
        
        $headers = array(
            'From: Naval EGT <' . get_option('admin_email') . '>',
            'Reply-To: tecnica@naval.it'
        );
        
        return wp_mail($user_data['email'], $subject, $html_message, $headers);
    }
    
    /**
     * Invia notifica admin per nuova registrazione
     */
    public static function send_admin_notification($user_data) {
        $admin_email = get_option('admin_email');
        
        $subject = 'Nuova registrazione utente - Naval EGT';
        
        $message = sprintf(
            '<h3>Nuova registrazione utente</h3>
            <p>Un nuovo utente si è registrato nell\'area riservata:</p>
            <ul>
                <li><strong>Nome:</strong> %s %s</li>
                <li><strong>Email:</strong> %s</li>
                <li><strong>Username:</strong> %s</li>
                <li><strong>Codice Utente:</strong> %s</li>
                <li><strong>Telefono:</strong> %s</li>
                <li><strong>Azienda:</strong> %s</li>
                <li><strong>P.IVA:</strong> %s</li>
                <li><strong>Data registrazione:</strong> %s</li>
            </ul>
            <p><a href="%s" style="background: #4285f4; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Gestisci Utente</a></p>',
            $user_data['nome'],
            $user_data['cognome'],
            $user_data['email'],
            $user_data['username'],
            $user_data['user_code'],
            $user_data['telefono'] ?? 'Non specificato',
            $user_data['ragione_sociale'] ?? 'Non specificata',
            $user_data['partita_iva'] ?? 'Non specificata',
            date('d/m/Y H:i'),
            admin_url('admin.php?page=naval-egt&tab=users')
        );
        
        $html_message = self::wrap_email_template($message, $subject);
        
        $headers = array(
            'From: Naval EGT <' . $admin_email . '>',
        );
        
        return wp_mail($admin_email, $subject, $html_message, $headers);
    }
    
    /**
     * Invia email di attivazione account
     */
    public static function send_activation_email($user_data) {
        $subject = 'Account attivato - Naval EGT Area Riservata';
        
        $message = sprintf(
            '<h3>Il tuo account è stato attivato!</h3>
            <p>Ciao <strong>%s</strong>,</p>
            <p>Il tuo account nell\'Area Riservata Naval EGT è stato attivato con successo.</p>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h4>I tuoi dati di accesso:</h4>
                <ul>
                    <li><strong>Codice Utente:</strong> %s</li>
                    <li><strong>Username:</strong> %s</li>
                    <li><strong>Email:</strong> %s</li>
                </ul>
            </div>
            <p>Ora puoi accedere alla tua area riservata per:</p>
            <ul>
                <li>Scaricare documenti e file</li>
                <li>Caricare i tuoi file</li>
                <li>Visualizzare lo storico delle attività</li>
            </ul>
            <p style="text-align: center; margin: 30px 0;">
                <a href="%s" style="background: #4285f4; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">Accedi all\'Area Riservata</a>
            </p>
            <p>Per assistenza tecnica contatta: <a href="mailto:tecnica@naval.it">tecnica@naval.it</a></p>',
            $user_data['nome'],
            $user_data['user_code'],
            $user_data['username'],
            $user_data['email'],
            self::get_login_url()
        );
        
        $html_message = self::wrap_email_template($message, $subject);
        
        $headers = array(
            'From: Naval EGT <' . get_option('admin_email') . '>',
            'Reply-To: tecnica@naval.it'
        );
        
        return wp_mail($user_data['email'], $subject, $html_message, $headers);
    }
    
    /**
     * Invia email di reset password (per future implementazioni)
     */
    public static function send_password_reset_email($user_data, $reset_token) {
        $subject = 'Reset Password - Naval EGT Area Riservata';
        
        $reset_url = add_query_arg(array(
            'action' => 'reset_password',
            'token' => $reset_token,
            'user' => $user_data['user_code']
        ), self::get_login_url());
        
        $message = sprintf(
            '<h3>Reset della Password</h3>
            <p>Ciao <strong>%s</strong>,</p>
            <p>Hai richiesto il reset della password per il tuo account Naval EGT.</p>
            <p>Clicca sul link sottostante per impostare una nuova password:</p>
            <p style="text-align: center; margin: 30px 0;">
                <a href="%s" style="background: #4285f4; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">Reset Password</a>
            </p>
            <p><small>Questo link è valido per 24 ore. Se non hai richiesto il reset, ignora questa email.</small></p>
            <p>Il tuo codice utente è: <strong>%s</strong></p>',
            $user_data['nome'],
            $reset_url,
            $user_data['user_code']
        );
        
        $html_message = self::wrap_email_template($message, $subject);
        
        $headers = array(
            'From: Naval EGT <' . get_option('admin_email') . '>',
            'Reply-To: tecnica@naval.it'
        );
        
        return wp_mail($user_data['email'], $subject, $html_message, $headers);
    }
    
    /**
     * Template wrapper per email HTML
     */
    private static function wrap_email_template($content, $subject) {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . esc_html($subject) . '</title>
            <style>
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    max-width: 600px; 
                    margin: 0 auto; 
                    padding: 20px; 
                    background: #f8f9fa;
                }
                .email-container {
                    background: white;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
                }
                .email-header {
                    background: linear-gradient(135deg, #4285f4 0%, #3367d6 100%);
                    color: white;
                    padding: 30px;
                    text-align: center;
                }
                .email-header h1 {
                    margin: 0;
                    font-size: 24px;
                    font-weight: 700;
                }
                .email-body {
                    padding: 30px;
                }
                .email-footer {
                    background: #f8f9fa;
                    padding: 20px 30px;
                    text-align: center;
                    font-size: 12px;
                    color: #666;
                    border-top: 1px solid #e0e0e0;
                }
                a { color: #4285f4; }
                .btn {
                    display: inline-block;
                    background: #4285f4;
                    color: white !important;
                    padding: 12px 24px;
                    text-decoration: none;
                    border-radius: 6px;
                    font-weight: 600;
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="email-header">
                    <h1>🚢 Naval EGT</h1>
                </div>
                <div class="email-body">
                    ' . $content . '
                </div>
                <div class="email-footer">
                    <p><strong>Naval Engineering & Green Technologies</strong></p>
                    <p>Sede legale: via Pietro Castellino, 45 - 80128 Napoli (NA)</p>
                    <p>Email: <a href="mailto:tecnica@naval.it">tecnica@naval.it</a> | 
                       Web: <a href="' . $site_url . '">' . $site_name . '</a></p>
                    <p><small>Questa è una email automatica, non rispondere a questo messaggio.</small></p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Template di benvenuto di default
     */
    private static function get_default_welcome_template() {
        return '<h3>Benvenuto nell\'Area Riservata Naval EGT!</h3>
        <p>Ciao <strong>{nome} {cognome}</strong>,</p>
        <p>La tua richiesta di registrazione è stata inviata con successo.</p>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h4>I tuoi dati di registrazione:</h4>
            <ul>
                <li><strong>Codice Utente:</strong> {user_code}</li>
                <li><strong>Username:</strong> {username}</li>
                <li><strong>Email:</strong> {email}</li>
            </ul>
        </div>
        <p><strong>Il tuo account è in attesa di attivazione.</strong> Riceverai una email di conferma quando il nostro staff avrà verificato e attivato il tuo account.</p>
        <p>Una volta attivato, potrai accedere alla tua area riservata per gestire documenti e file.</p>
        <p>Per qualsiasi domanda o supporto, contatta: <a href="mailto:tecnica@naval.it">tecnica@naval.it</a></p>';
    }
    
    /**
     * Ottiene l'URL della pagina di login
     */
    private static function get_login_url() {
        $page = get_page_by_path('area-riservata-naval-egt');
        if ($page) {
            return get_permalink($page->ID);
        }
        return home_url('/area-riservata-naval-egt/');
    }
    
    /**
     * Test invio email
     */
    public static function test_email_configuration() {
        $admin_email = get_option('admin_email');
        
        $subject = 'Test Email Naval EGT Plugin';
        $message = '<h3>Test Email</h3><p>Se ricevi questa email, la configurazione email del plugin Naval EGT funziona correttamente.</p><p>Data invio: ' . date('d/m/Y H:i:s') . '</p>';
        
        $html_message = self::wrap_email_template($message, $subject);
        
        $headers = array(
            'From: Naval EGT <' . $admin_email . '>',
        );
        
        return wp_mail($admin_email, $subject, $html_message, $headers);
    }
    
    /**
     * Invia digest settimanale admin (per future implementazioni)  
     */
    public static function send_weekly_digest() {
        $stats = Naval_EGT_Database::get_user_stats();
        $recent_activities = Naval_EGT_Database::get_recent_activities(20);
        
        $subject = 'Digest Settimanale Naval EGT - ' . date('d/m/Y');
        
        $activities_html = '';
        foreach ($recent_activities as $activity) {
            $activities_html .= sprintf(
                '<li>%s - %s: %s %s</li>',
                date('d/m H:i', strtotime($activity['created_at'])),
                $activity['user_name'] ?: 'Sistema',
                $activity['action'],
                $activity['file_name'] ? '(' . $activity['file_name'] . ')' : ''
            );
        }
        
        $message = sprintf(
            '<h3>Digest Settimanale</h3>
            <h4>Statistiche Correnti:</h4>
            <ul>
                <li>Utenti Totali: <strong>%d</strong></li>
                <li>Utenti Attivi: <strong>%d</strong></li>
                <li>In Attesa: <strong>%d</strong></li>
                <li>File Totali: <strong>%d</strong></li>
            </ul>
            <h4>Attività Recenti:</h4>
            <ul>%s</ul>
            <p><a href="%s">Vai alla Dashboard</a></p>',
            $stats['total_users'],
            $stats['active_users'],
            $stats['pending_users'],
            $stats['total_files'],
            $activities_html,
            admin_url('admin.php?page=naval-egt')
        );
        
        $html_message = self::wrap_email_template($message, $subject);
        
        return wp_mail(get_option('admin_email'), $subject, $html_message);
    }
}