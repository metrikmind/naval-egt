<?php
/**
 * Naval EGT Admin - Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['submit']) && wp_verify_nonce($_POST['naval_egt_settings_nonce'], 'naval_egt_settings')) {
    // General Settings
    update_option('naval_egt_max_file_size', intval($_POST['max_file_size']));
    update_option('naval_egt_allowed_extensions', sanitize_text_field($_POST['allowed_extensions']));
    update_option('naval_egt_auto_approve_users', isset($_POST['auto_approve_users']) ? 1 : 0);
    update_option('naval_egt_email_notifications', isset($_POST['email_notifications']) ? 1 : 0);
    
    // Email Settings
    update_option('naval_egt_admin_email', sanitize_email($_POST['admin_email']));
    update_option('naval_egt_from_name', sanitize_text_field($_POST['from_name']));
    update_option('naval_egt_from_email', sanitize_email($_POST['from_email']));
    
    // FTP Settings
    update_option('naval_egt_ftp_base_path', sanitize_text_field($_POST['ftp_base_path']));
    update_option('naval_egt_create_user_folders', isset($_POST['create_user_folders']) ? 1 : 0);
    update_option('naval_egt_folder_permissions', sanitize_text_field($_POST['folder_permissions']));
    
    // Security Settings
    update_option('naval_egt_login_attempts_limit', intval($_POST['login_attempts_limit']));
    update_option('naval_egt_lockout_duration', intval($_POST['lockout_duration']));
    update_option('naval_egt_password_strength', sanitize_text_field($_POST['password_strength']));
    update_option('naval_egt_session_timeout', intval($_POST['session_timeout']));
    
    // Maintenance Settings
    update_option('naval_egt_log_retention_days', intval($_POST['log_retention_days']));
    update_option('naval_egt_cleanup_frequency', sanitize_text_field($_POST['cleanup_frequency']));
    
    echo '<div class="naval-egt-admin-notice naval-egt-admin-notice-success"><p>' . __('Impostazioni salvate con successo!', 'naval-egt') . '</p></div>';
}

// Get current settings
$max_file_size = get_option('naval_egt_max_file_size', 50);
$allowed_extensions = get_option('naval_egt_allowed_extensions', 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,rtf,zip,rar,7z,jpg,jpeg,png,gif,bmp,tiff,dwg,dxf,step,stp,iges,igs');
$auto_approve_users = get_option('naval_egt_auto_approve_users', 0);
$email_notifications = get_option('naval_egt_email_notifications', 1);

$admin_email = get_option('naval_egt_admin_email', get_option('admin_email'));
$from_name = get_option('naval_egt_from_name', get_bloginfo('name'));
$from_email = get_option('naval_egt_from_email', get_option('admin_email'));

$ftp_base_path = get_option('naval_egt_ftp_base_path', wp_upload_dir()['basedir'] . '/naval-egt-ftp/');
$create_user_folders = get_option('naval_egt_create_user_folders', 1);
$folder_permissions = get_option('naval_egt_folder_permissions', '755');

$login_attempts_limit = get_option('naval_egt_login_attempts_limit', 5);
$lockout_duration = get_option('naval_egt_lockout_duration', 30);
$password_strength = get_option('naval_egt_password_strength', 'medium');
$session_timeout = get_option('naval_egt_session_timeout', 24);

$log_retention_days = get_option('naval_egt_log_retention_days', 90);
$cleanup_frequency = get_option('naval_egt_cleanup_frequency', 'weekly');
?>

<div class="naval-egt-admin-wrap">
    <div class="naval-egt-admin-header">
        <h1><?php _e('Impostazioni Naval EGT', 'naval-egt'); ?></h1>
        <p><?php _e('Configura le impostazioni del plugin per l\'area riservata clienti.', 'naval-egt'); ?></p>
    </div>

    <form method="post" action="" id="naval-egt-settings-form">
        <?php wp_nonce_field('naval_egt_settings', 'naval_egt_settings_nonce'); ?>
        
        <div class="naval-egt-admin-tabs">
            <div class="naval-egt-admin-tab-nav">
                <button type="button" data-tab="general" class="active"><?php _e('Generale', 'naval-egt'); ?></button>
                <button type="button" data-tab="email"><?php _e('Email', 'naval-egt'); ?></button>
                <button type="button" data-tab="ftp"><?php _e('FTP', 'naval-egt'); ?></button>
                <button type="button" data-tab="security"><?php _e('Sicurezza', 'naval-egt'); ?></button>
                <button type="button" data-tab="maintenance"><?php _e('Manutenzione', 'naval-egt'); ?></button>
            </div>

            <div class="naval-egt-admin-tab-content">
                <!-- General Settings -->
                <div id="general" class="naval-egt-admin-tab-pane active">
                    <div class="naval-egt-admin-settings-section">
                        <div class="naval-egt-admin-settings-header">
                            <h3><?php _e('Impostazioni Generali', 'naval-egt'); ?></h3>
                        </div>
                        <div class="naval-egt-admin-settings-content">
                            <div class="naval-egt-admin-form-row">
                                <div class="naval-egt-form-group">
                                    <label for="max_file_size"><?php _e('Dimensione Massima File (MB)', 'naval-egt'); ?></label>
                                    <input type="number" id="max_file_size" name="max_file_size" value="<?php echo esc_attr($max_file_size); ?>" min="1" max="500">
                                    <div class="description"><?php _e('Dimensione massima per i file caricati dagli utenti.', 'naval-egt'); ?></div>
                                </div>
                                <div class="naval-egt-form-group">
                                    <label for="allowed_extensions"><?php _e('Estensioni File Consentite', 'naval-egt'); ?></label>
                                    <textarea id="allowed_extensions" name="allowed_extensions" rows="3"><?php echo esc_textarea($allowed_extensions); ?></textarea>
                                    <div class="description"><?php _e('Estensioni file permesse, separate da virgole (es: pdf,doc,jpg).', 'naval-egt'); ?></div>
                                </div>
                            </div>
                            
                            <div class="naval-egt-admin-form-row">
                                <div class="naval-egt-form-group">
                                    <label>
                                        <input type="checkbox" name="auto_approve_users" value="1" <?php checked($auto_approve_users, 1); ?>>
                                        <?php _e('Approva Automaticamente Nuovi Utenti', 'naval-egt'); ?>
                                    </label>
                                    <div class="description"><?php _e('Se abilitato, i nuovi utenti saranno attivati automaticamente senza revisione manuale.', 'naval-egt'); ?></div>
                                </div>
                                <div class="naval-egt-form-group">
                                    <label>
                                        <input type="checkbox" name="email_notifications" value="1" <?php checked($email_notifications, 1); ?>>
                                        <?php _e('Notifiche Email Admin', 'naval-egt'); ?>
                                    </label>
                                    <div class="description"><?php _e('Ricevi notifiche email per nuove registrazioni e upload di file.', 'naval-egt'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="naval-egt-admin-settings-section">
                        <div class="naval-egt-admin-settings-header">
                            <h3><?php _e('Pagine del Plugin', 'naval-egt'); ?></h3>
                        </div>
                        <div class="naval-egt-admin-settings-content">
                            <div class="naval-egt-page-links">
                                <p>
                                    <strong><?php _e('Pagina Area Riservata:', 'naval-egt'); ?></strong>
                                    <a href="<?php echo home_url('/area-riservata/'); ?>" target="_blank"><?php echo home_url('/area-riservata/'); ?></a>
                                    <span class="description"><?php _e('(Contiene il form di login e registrazione)', 'naval-egt'); ?></span>
                                </p>
                                <p>
                                    <strong><?php _e('Dashboard Clienti:', 'naval-egt'); ?></strong>
                                    <a href="<?php echo home_url('/dashboard-clienti/'); ?>" target="_blank"><?php echo home_url('/dashboard-clienti/'); ?></a>
                                    <span class="description"><?php _e('(Area riservata per utenti loggati)', 'naval-egt'); ?></span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email Settings -->
                <div id="email" class="naval-egt-admin-tab-pane">
                    <div class="naval-egt-admin-settings-section">
                        <div class="naval-egt-admin-settings-header">
                            <h3><?php _e('Configurazione Email', 'naval-egt'); ?></h3>
                        </div>
                        <div class="naval-egt-admin-settings-content">
                            <div class="naval-egt-admin-form-row">
                                <div class="naval-egt-form-group">
                                    <label for="admin_email"><?php _e('Email Amministratore', 'naval-egt'); ?></label>
                                    <input type="email" id="admin_email" name="admin_email" value="<?php echo esc_attr($admin_email); ?>">
                                    <div class="description"><?php _e('Indirizzo email dove ricevere le notifiche amministrative.', 'naval-egt'); ?></div>
                                </div>
                                <div class="naval-egt-form-group">
                                    <label for="from_name"><?php _e('Nome Mittente', 'naval-egt'); ?></label>
                                    <input type="text" id="from_name" name="from_name" value="<?php echo esc_attr($from_name); ?>">
                                    <div class="description"><?php _e('Nome che apparirà come mittente nelle email inviate.', 'naval-egt'); ?></div>
                                </div>
                            </div>
                            
                            <div class="naval-egt-form-group">
                                <label for="from_email"><?php _e('Email Mittente', 'naval-egt'); ?></label>
                                <input type="email" id="from_email" name="from_email" value="<?php echo esc_attr($from_email); ?>">
                                <div class="description"><?php _e('Indirizzo email utilizzato come mittente per tutte le comunicazioni.', 'naval-egt'); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="naval-egt-admin-settings-section">
                        <div class="naval-egt-admin-settings-header">
                            <h3><?php _e('Template Email', 'naval-egt'); ?></h3>
                        </div>
                        <div class="naval-egt-admin-settings-content">
                            <div class="naval-egt-email-templates">
                                <p><strong><?php _e('Email automatiche inviate dal sistema:', 'naval-egt'); ?></strong></p>
                                <ul>
                                    <li><?php _e('Email di attivazione account (ai nuovi utenti)', 'naval-egt'); ?></li>
                                    <li><?php _e('Email di benvenuto (dopo l\'attivazione)', 'naval-egt'); ?></li>
                                    <li><?php _e('Email di reset password', 'naval-egt'); ?></li>
                                    <li><?php _e('Notifiche di nuovo file disponibile', 'naval-egt'); ?></li>
                                    <li><?php _e('Notifiche admin per nuove registrazioni', 'naval-egt'); ?></li>
                                </ul>
                                <p class="description"><?php _e('I template email utilizzano i placeholder standard di WordPress e sono personalizzabili tramite filtri.', 'naval-egt'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FTP Settings -->
                <div id="ftp" class="naval-egt-admin-tab-pane">
                    <div class="naval-egt-admin-settings-section">
                        <div class="naval-egt-admin-settings-header">
                            <h3><?php _e('Configurazione FTP', 'naval-egt'); ?></h3>
                        </div>
                        <div class="naval-egt-admin-settings-content">
                            <div class="naval-egt-form-group">
                                <label for="ftp_base_path"><?php _e('Percorso Base FTP', 'naval-egt'); ?></label>
                                <input type="text" id="ftp_base_path" name="ftp_base_path" value="<?php echo esc_attr($ftp_base_path); ?>" class="widefat">
                                <div class="description"><?php _e('Percorso assoluto dove verranno create le cartelle FTP degli utenti.', 'naval-egt'); ?></div>
                            </div>
                            
                            <div class="naval-egt-admin-form-row">
                                <div class="naval-egt-form-group">
                                    <label>
                                        <input type="checkbox" name="create_user_folders" value="1" <?php checked($create_user_folders, 1); ?>>
                                        <?php _e('Crea Cartelle Utente Automaticamente', 'naval-egt'); ?>
                                    </label>
                                    <div class="description"><?php _e('Crea automaticamente le cartelle FTP per i nuovi utenti.', 'naval-egt'); ?></div>
                                </div>
                                <div class="naval-egt-form-group">
                                    <label for="folder_permissions"><?php _e('Permessi Cartelle', 'naval-egt'); ?></label>
                                    <select id="folder_permissions" name="folder_permissions">
                                        <option value="755" <?php selected($folder_permissions, '755'); ?>>755 (rwxr-xr-x)</option>
                                        <option value="750" <?php selected($folder_permissions, '750'); ?>>750 (rwxr-x---)</option>
                                        <option value="700" <?php selected($folder_permissions, '700'); ?>>700 (rwx------)</option>
                                    </select>
                                    <div class="description"><?php _e('Permessi UNIX per le cartelle create.', 'naval-egt'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="naval-egt-admin-settings-section">
                        <div class="naval-egt-admin-settings-header">
                            <h3><?php _e('Struttura Cartelle', 'naval-egt'); ?></h3>
                        </div>
                        <div class="naval-egt-admin-settings-content">
                            <div class="naval-egt-folder-structure">
                                <p><strong><?php _e('Ogni utente avrà la seguente struttura di cartelle:', 'naval-egt'); ?></strong></p>
                                <div class="folder-tree">
                                    <div class="folder-item">📁 client_[ID]_[username]/</div>
                                    <div class="folder-item folder-sub">📁 uploads/ <span class="description"><?php _e('- File caricati dall\'utente', 'naval-egt'); ?></span></div>
                                    <div class="folder-item folder-sub">📁 downloads/ <span class="description"><?php _e('- File condivisi da Naval EGT', 'naval-egt'); ?></span></div>
                                    <div class="folder-item folder-sub">📁 shared/ <span class="description"><?php _e('- File condivisi bidirezionali', 'naval-egt'); ?></span></div>
                                    <div class="folder-item folder-sub">📄 README.txt <span class="description"><?php _e('- Istruzioni per l\'utente', 'naval-egt'); ?></span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div id="security" class="naval-egt-admin-tab-pane">
                    <div class="naval-egt-admin-settings-section">
                        <div class="naval-egt-admin-settings-header">
                            <h3><?php _e('Sicurezza Login', 'naval-egt'); ?></h3>
                        </div>
                        <div class="naval-egt-admin-settings-content">
                            <div class="naval-egt-admin-form-row">
                                <div class="naval-egt-form-group">
                                    <label for="login_attempts_limit"><?php _e('Limite Tentativi Login', 'naval-egt'); ?></label>
                                    <input type="number" id="login_attempts_limit" name="login_attempts_limit" value="<?php echo esc_attr($login_attempts_limit); ?>" min="1" max="20">
                                    <div class="description"><?php _e('Numero massimo di tentativi di login falliti prima del blocco.', 'naval-egt'); ?></div>
                                </div>
                                <div class="naval-egt-form-group">
                                    <label for="lockout_duration"><?php _e('Durata Blocco (minuti)', 'naval-egt'); ?></label>
                                    <input type="number" id="lockout_duration" name="lockout_duration" value="<?php echo esc_attr($lockout_duration); ?>" min="1" max="1440">
                                    <div class="description"><?php _e('Durata del blocco dopo il superamento del limite tentativi.', 'naval-egt'); ?></div>
                                </div>
                            </div>
                            
                            <div class="naval-egt-admin-form-row">
                                <div class="naval-egt-form-group">
                                    <label for="password_strength"><?php _e('Forza Password Richiesta', 'naval-egt'); ?></label>
                                    <select id="password_strength" name="password_strength">
                                        <option value="weak" <?php selected($password_strength, 'weak'); ?>><?php _e('Debole (min 6 caratteri)', 'naval-egt'); ?></option>
                                        <option value="medium" <?php selected($password_strength, 'medium'); ?>><?php _e('Media (8+ caratteri, lettere e numeri)', 'naval-egt'); ?></option>
                                        <option value="strong" <?php selected($password_strength, 'strong'); ?>><?php _e('Forte (8+ caratteri, lettere, numeri e simboli)', 'naval-egt'); ?></option>
                                    </select>
                                    <div class="description"><?php _e('Livello minimo di sicurezza richiesto per le password.', 'naval-egt'); ?></div>
                                </div>
                                <div class="naval-egt-form-group">
                                    <label for="session_timeout"><?php _e('Timeout Sessione (ore)', 'naval-egt'); ?></label>
                                    <input type="number" id="session_timeout" name="session_timeout" value="<?php echo esc_attr($session_timeout); ?>" min="1" max="168">
                                    <div class="description"><?php _e('Durata massima delle sessioni utente prima del logout automatico.', 'naval-egt'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="naval-egt-admin-settings-section">
                        <div class="naval-egt-admin-settings-header">
                            <h3><?php _e('Sicurezza File', 'naval-egt'); ?></h3>
                        </div>
                        <div class="naval-egt-admin-settings-content">
                            <div class="naval-egt-security-info">
                                <p><strong><?php _e('Misure di sicurezza attive:', 'naval-egt'); ?></strong></p>
                                <ul>
                                    <li><?php _e('Scansione antimalware dei file caricati', 'naval-egt'); ?></li>
                                    <li><?php _e('Blocco di estensioni file pericolose', 'naval-egt'); ?></li>
                                    <li><?php _e('Protezione .htaccess per cartelle FTP', 'naval-egt'); ?></li>
                                    <li><?php _e('Validazione MIME type dei file', 'naval-egt'); ?></li>
                                    <li><?php _e('Log completo di tutte le attività', 'naval-egt'); ?></li>
                                    <li><?php _e('Accesso limitato tramite ruoli WordPress', 'naval-egt'); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Settings -->
                <div id="maintenance" class="naval-egt-admin-tab-pane">
                    <div class="naval-egt-admin-settings-section">
                        <div class="naval-egt-admin-settings-header">
                            <h3><?php _e('Pulizia Automatica', 'naval-egt'); ?></h3>
                        </div>
                        <div class="naval-egt-admin-settings-content">
                            <div class="naval-egt-admin-form-row">
                                <div class="naval-egt-form-group">
                                    <label for="log_retention_days"><?php _e('Conservazione Log (giorni)', 'naval-egt'); ?></label>
                                    <input type="number" id="log_retention_days" name="log_retention_days" value="<?php echo esc_attr($log_retention_days); ?>" min="7" max="365">
                                    <div class="description"><?php _e('Numero di giorni di conservazione dei log prima della cancellazione automatica.', 'naval-egt'); ?></div>
                                </div>
                                <div class="naval-egt-form-group">
                                    <label for="cleanup_frequency"><?php _e('Frequenza Pulizia', 'naval-egt'); ?></label>
                                    <select id="cleanup_frequency" name="cleanup_frequency">
                                        <option value="daily" <?php selected($cleanup_frequency, 'daily'); ?>><?php _e('Giornaliera', 'naval-egt'); ?></option>
                                        <option value="weekly" <?php selected($cleanup_frequency, 'weekly'); ?>><?php _e('Settimanale', 'naval-egt'); ?></option>
                                        <option value="monthly" <?php selected($cleanup_frequency, 'monthly'); ?>><?php _e('Mensile', 'naval-egt'); ?></option>
                                    </select>
                                    <div class="description"><?php _e('Frequenza di esecuzione della pulizia automatica del database.', 'naval-egt'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="naval-egt-admin-settings-section">
                        <div class="naval-egt-admin-settings-header">
                            <h3><?php _e('Informazioni Database', 'naval-egt'); ?></h3>
                        </div>
                        <div class="naval-egt-admin-settings-content">
                            <div class="naval-egt-db-info">
                                <?php
                                global $wpdb;
                                
                                // Get table sizes
                                $sessions_table = $wpdb->prefix . 'naval_egt_sessions';
                                $logs_table = $wpdb->prefix . 'naval_egt_ftp_logs';
                                
                                $sessions_count = $wpdb->get_var("SELECT COUNT(*) FROM $sessions_table");
                                $logs_count = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table");
                                $old_logs_count = $wpdb->get_var($wpdb->prepare(
                                    "SELECT COUNT(*) FROM $logs_table WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                                    $log_retention_days
                                ));
                                ?>
                                <div class="naval-egt-db-stats">
                                    <div class="naval-egt-db-stat">
                                        <span class="stat-label"><?php _e('Sessioni attive:', 'naval-egt'); ?></span>
                                        <span class="stat-value"><?php echo intval($sessions_count); ?></span>
                                    </div>
                                    <div class="naval-egt-db-stat">
                                        <span class="stat-label"><?php _e('Record log totali:', 'naval-egt'); ?></span>
                                        <span class="stat-value"><?php echo intval($logs_count); ?></span>
                                    </div>
                                    <div class="naval-egt-db-stat">
                                        <span class="stat-label"><?php _e('Log da eliminare:', 'naval-egt'); ?></span>
                                        <span class="stat-value"><?php echo intval($old_logs_count); ?></span>
                                    </div>
                                </div>
                                
                                <div class="naval-egt-maintenance-actions">
                                    <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-warning" onclick="manualCleanup()">
                                        <?php _e('Esegui Pulizia Manuale', 'naval-egt'); ?>
                                    </button>
                                    <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-secondary" onclick="optimizeDatabase()">
                                        <?php _e('Ottimizza Database', 'naval-egt'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="naval-egt-admin-form">
            <div class="naval-egt-admin-flex naval-egt-admin-flex-between">
                <div>
                    <button type="submit" name="submit" class="naval-egt-admin-btn naval-egt-admin-btn-primary">
                        <?php _e('Salva Impostazioni', 'naval-egt'); ?>
                    </button>
                    <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-secondary" onclick="resetToDefaults()">
                        <?php _e('Ripristina Default', 'naval-egt'); ?>
                    </button>
                </div>
                <div>
                    <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-warning" onclick="testConfiguration()">
                        <?php _e('Testa Configurazione', 'naval-egt'); ?>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function manualCleanup() {
    if (confirm('<?php _e('Eseguire la pulizia manuale del database?', 'naval-egt'); ?>')) {
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'naval_egt_manual_cleanup',
                nonce: '<?php echo wp_create_nonce('naval_egt_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    NavalEGTAdmin.showNotice(response.data.message, 'success');
                    location.reload();
                } else {
                    NavalEGTAdmin.showNotice('Errore durante la pulizia', 'error');
                }
            }
        });
    }
}

function optimizeDatabase() {
    NavalEGTAdmin.showNotice('Ottimizzazione database in corso...', 'info');
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'naval_egt_optimize_database',
            nonce: '<?php echo wp_create_nonce('naval_egt_admin_nonce'); ?>'
        },
        success: function(response) {
            if (response.success) {
                NavalEGTAdmin.showNotice('Database ottimizzato con successo', 'success');
            } else {
                NavalEGTAdmin.showNotice('Errore durante l\'ottimizzazione', 'error');
            }
        }
    });
}

function resetToDefaults() {
    if (confirm('<?php _e('Ripristinare tutte le impostazioni ai valori predefiniti?', 'naval-egt'); ?>')) {
        // Implement reset to defaults
        NavalEGTAdmin.showNotice('Funzionalità di reset in sviluppo', 'info');
    }
}

function testConfiguration() {
    NavalEGTAdmin.showNotice('Test configurazione in corso...', 'info');
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'naval_egt_test_configuration',
            nonce: '<?php echo wp_create_nonce('naval_egt_admin_nonce'); ?>'
        },
        success: function(response) {
            if (response.success) {
                NavalEGTAdmin.showNotice('Configurazione testata con successo', 'success');
            } else {
                NavalEGTAdmin.showNotice(response.data.message || 'Errore nella configurazione', 'error');
            }
        }
    });
}
</script>

<style>
.naval-egt-page-links p {
    margin: 10px 0;
    padding: 10px;
    background: #f9f9f9;
    border-left: 4px solid #2a5298;
    border-radius: 0 4px 4px 0;
}

.naval-egt-email-templates ul {
    background: #f9f9f9;
    padding: 15px 15px 15px 35px;
    border-radius: 4px;
    margin: 10px 0;
}

.folder-tree {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
    font-family: monospace;
    margin: 10px 0;
}

.folder-item {
    padding: 3px 0;
    color: #333;
}

.folder-sub {
    margin-left: 20px;
}

.naval-egt-security-info ul {
    background: #f0f8ff;
    padding: 15px 15px 15px 35px;
    border-radius: 4px;
    border-left: 4px solid #2a5298;
    margin: 10px 0;
}

.naval-egt-db-stats {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 20px;
}

.naval-egt-db-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: #f9f9f9;
    border-radius: 4px;
}

.naval-egt-maintenance-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .naval-egt-maintenance-actions {
        flex-direction: column;
    }
    
    .naval-egt-maintenance-actions button {
        width: 100%;
    }
}
</style>