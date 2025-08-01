<?php
/**
 * Pagina impostazioni admin - Versione semplificata con Dropbox integrato
 */

if (!defined('ABSPATH')) {
    exit;
}

// Gestione salvataggio impostazioni
if (isset($_POST['save_settings']) && wp_verify_nonce($_POST['naval_egt_nonce'], 'save_settings')) {
    $settings = array(
        'user_registration_enabled' => isset($_POST['user_registration_enabled']) ? '1' : '0',
        'manual_user_activation' => isset($_POST['manual_user_activation']) ? '1' : '0',
        'email_notifications' => isset($_POST['email_notifications']) ? '1' : '0',
        'max_file_size' => intval($_POST['max_file_size']),
        'allowed_file_types' => sanitize_text_field($_POST['allowed_file_types']),
        'welcome_email_template' => wp_kses_post($_POST['welcome_email_template'])
    );
    
    foreach ($settings as $key => $value) {
        Naval_EGT_Database::update_setting($key, $value);
    }
    
    echo '<div class="notice notice-success"><p>Impostazioni salvate con successo!</p></div>';
}

// Carica impostazioni correnti
$current_settings = array(
    'user_registration_enabled' => Naval_EGT_Database::get_setting('user_registration_enabled', '1'),
    'manual_user_activation' => Naval_EGT_Database::get_setting('manual_user_activation', '1'),
    'email_notifications' => Naval_EGT_Database::get_setting('email_notifications', '1'),
    'max_file_size' => Naval_EGT_Database::get_setting('max_file_size', '20971520'),
    'allowed_file_types' => Naval_EGT_Database::get_setting('allowed_file_types', 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,dwg,dxf,zip,rar'),
    'welcome_email_template' => Naval_EGT_Database::get_setting('welcome_email_template', 'Benvenuto {nome} nell\'Area Riservata Naval EGT! Il tuo codice utente è: {user_code}')
);

// Stato Dropbox
$dropbox = Naval_EGT_Dropbox::get_instance();
$dropbox_status = $dropbox->get_connection_status();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?> - Impostazioni</h1>
    
    <div class="nav-tab-wrapper">
        <a href="#general" class="nav-tab nav-tab-active" data-tab="general">Generali</a>
        <a href="#dropbox" class="nav-tab" data-tab="dropbox">Dropbox</a>
        <a href="#email" class="nav-tab" data-tab="email">Email</a>
        <a href="#files" class="nav-tab" data-tab="files">File</a>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('save_settings', 'naval_egt_nonce'); ?>
        
        <!-- Tab Generali -->
        <div id="tab-general" class="tab-content active">
            <h2>Impostazioni Generali</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Registrazione Utenti</th>
                    <td>
                        <label>
                            <input type="checkbox" name="user_registration_enabled" value="1" 
                                   <?php checked($current_settings['user_registration_enabled'], '1'); ?>>
                            Consenti nuove registrazioni
                        </label>
                        <p class="description">Se disabilitato, gli utenti non potranno registrarsi autonomamente</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Attivazione Account</th>
                    <td>
                        <label>
                            <input type="checkbox" name="manual_user_activation" value="1" 
                                   <?php checked($current_settings['manual_user_activation'], '1'); ?>>
                            Attivazione manuale degli account
                        </label>
                        <p class="description">I nuovi account richiedono approvazione manuale dell'admin</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Tab Dropbox -->
        <div id="tab-dropbox" class="tab-content">
            <h2>Configurazione Dropbox</h2>
            
            <div class="dropbox-status-card">
                <h3>Stato Connessione</h3>
                <div class="dropbox-status-info">
                    <?php if ($dropbox_status['connected']): ?>
                        <div class="status-connected">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <strong>Connesso con successo</strong>
                            <?php if (!empty($dropbox_status['account_name'])): ?>
                                <br><small>Account: <?php echo esc_html($dropbox_status['account_name']); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($dropbox_status['has_credentials']): ?>
                        <div class="status-pending">
                            <span class="dashicons dashicons-clock"></span>
                            <strong>Configurazione in corso</strong>
                            <p>Le credenziali sono configurate ma serve l'autorizzazione.</p>
                        </div>
                    <?php else: ?>
                        <div class="status-disconnected">
                            <span class="dashicons dashicons-dismiss"></span>
                            <strong>Non configurato</strong>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="dropbox-actions">
                    <?php if ($dropbox_status['connected']): ?>
                        <button type="button" class="button test-dropbox-btn">Test Connessione</button>
                        <button type="button" class="button sync-folders-btn">Sincronizza Cartelle</button>
                        <button type="button" class="button button-secondary disconnect-dropbox-btn">Disconnetti</button>
                    <?php elseif (isset($dropbox_status['auth_url'])): ?>
                        <a href="<?php echo esc_url($dropbox_status['auth_url']); ?>" class="button button-primary">
                            Autorizza Dropbox
                        </a>
                    <?php else: ?>
                        <button type="button" class="button button-primary configure-dropbox-btn">
                            Configura Dropbox
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="dropbox-info">
                <h4>Come funziona:</h4>
                <ol>
                    <li><strong>Credenziali integrate:</strong> Le chiavi Dropbox sono già configurate nel plugin</li>
                    <li><strong>Autorizzazione:</strong> Clicca "Configura Dropbox" per autorizzare l'accesso</li>
                    <li><strong>Cartelle automatiche:</strong> Il plugin cerca cartelle che iniziano con il codice utente (es. 100001_Nome_Cliente)</li>
                    <li><strong>Sincronizzazione:</strong> I file vengono sincronizzati automaticamente con il database</li>
                </ol>
                
                <div class="dropbox-requirements">
                    <h4>Requisiti:</h4>
                    <ul>
                        <li>✅ Certificato SSL attivo (HTTPS)</li>
                        <li>✅ Account Dropbox Business/Developer</li>
                        <li>✅ Cartelle nominate con codice utente: <code>100001_Nome_Cliente</code></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Tab Email -->
        <div id="tab-email" class="tab-content">
            <h2>Configurazione Email</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Notifiche Email</th>
                    <td>
                        <label>
                            <input type="checkbox" name="email_notifications" value="1" 
                                   <?php checked($current_settings['email_notifications'], '1'); ?>>
                            Invia notifiche email agli admin
                        </label>
                        <p class="description">Ricevi notifiche per nuove registrazioni e attività importanti</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Template Email Benvenuto</th>
                    <td>
                        <textarea name="welcome_email_template" rows="5" cols="50" class="large-text"><?php 
                            echo esc_textarea($current_settings['welcome_email_template']); 
                        ?></textarea>
                        <p class="description">
                            Variabili disponibili: <code>{nome}</code>, <code>{cognome}</code>, <code>{user_code}</code>, <code>{email}</code>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Tab File -->
        <div id="tab-files" class="tab-content">
            <h2>Configurazione File</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Dimensione Massima File</th>
                    <td>
                        <input type="number" name="max_file_size" value="<?php echo esc_attr($current_settings['max_file_size']); ?>" 
                               min="1048576" max="104857600" step="1048576">
                        <p class="description">
                            Dimensione in bytes (1048576 = 1MB, 20971520 = 20MB)
                            <br>Attuale: <?php echo size_format($current_settings['max_file_size']); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Tipi File Consentiti</th>
                    <td>
                        <input type="text" name="allowed_file_types" value="<?php echo esc_attr($current_settings['allowed_file_types']); ?>" 
                               class="large-text">
                        <p class="description">
                            Estensioni separate da virgola (es: pdf,doc,docx,jpg,png)
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <input type="submit" name="save_settings" class="button-primary" value="Salva Impostazioni">
        </p>
    </form>
</div>

<style>
.dropbox-status-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.status-connected {
    color: #46b450;
    font-size: 16px;
}

.status-pending {
    color: #ffb900;
    font-size: 16px;
}

.status-disconnected {
    color: #dc3232;
    font-size: 16px;
}

.dropbox-actions {
    margin-top: 15px;
}

.dropbox-actions .button {
    margin-right: 10px;
}

.dropbox-info {
    background: #f7f7f7;
    border-left: 4px solid #0073aa;
    padding: 15px;
    margin: 20px 0;
}

.dropbox-requirements ul {
    list-style: none;
    padding-left: 0;
}

.dropbox-requirements li {
    margin: 5px 0;
}

.tab-content {
    display: none;
    padding: 20px 0;
}

.tab-content.active {
    display: block;
}

.nav-tab.nav-tab-active {
    border-bottom: 1px solid #f1f1f1;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Gestione tab
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var targetTab = $(this).attr('data-tab');
        
        // Rimuovi active da tutti i tab
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').removeClass('active');
        
        // Attiva tab selezionato
        $(this).addClass('nav-tab-active');
        $('#tab-' + targetTab).addClass('active');
    });
});
</script>