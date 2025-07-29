<?php
/**
 * Naval EGT Admin - Main Dashboard Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get statistics
global $wpdb;

$total_users = count(get_users(array('role' => 'naval_egt_client')));
$active_users = count(get_users(array(
    'role' => 'naval_egt_client',
    'meta_query' => array(
        array(
            'key' => 'naval_egt_status',
            'value' => 'active',
            'compare' => '='
        )
    )
)));
$pending_users = count(get_users(array(
    'role' => 'naval_egt_client',
    'meta_query' => array(
        array(
            'key' => 'naval_egt_status',
            'value' => 'pending',
            'compare' => '='
        )
    )
)));

$logs_table = $wpdb->prefix . 'naval_egt_ftp_logs';
$total_files = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE action = 'upload'");
$recent_uploads = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE action = 'upload' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");

// Get recent activity
$recent_activity = $wpdb->get_results("
    SELECT l.*, u.display_name, u.user_email 
    FROM $logs_table l 
    LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
    ORDER BY l.created_at DESC 
    LIMIT 10
");
?>

<div class="naval-egt-admin-wrap">
    <div class="naval-egt-admin-header">
        <h1><?php _e('Naval EGT - Area di Amministrazione', 'naval-egt'); ?></h1>
        <p><?php _e('Gestisci gli utenti dell\'area riservata, monitora l\'attività e configura le impostazioni del plugin.', 'naval-egt'); ?></p>
    </div>

    <!-- Dashboard Statistics -->
    <div class="naval-egt-admin-cards">
        <div class="naval-egt-admin-card stat-total-users" data-stat-type="total_users">
            <h3><?php _e('Utenti Totali', 'naval-egt'); ?></h3>
            <div class="naval-egt-stat-number"><?php echo intval($total_users); ?></div>
            <div class="naval-egt-stat-label"><?php _e('Registrati nell\'area riservata', 'naval-egt'); ?></div>
        </div>

        <div class="naval-egt-admin-card stat-active-users" data-stat-type="active_users">
            <h3><?php _e('Utenti Attivi', 'naval-egt'); ?></h3>
            <div class="naval-egt-stat-number"><?php echo intval($active_users); ?></div>
            <div class="naval-egt-stat-label"><?php _e('Con accesso confermato', 'naval-egt'); ?></div>
        </div>

        <div class="naval-egt-admin-card stat-pending-users" data-stat-type="pending_users">
            <h3><?php _e('In Attesa', 'naval-egt'); ?></h3>
            <div class="naval-egt-stat-number"><?php echo intval($pending_users); ?></div>
            <div class="naval-egt-stat-label"><?php _e('Registrazioni da attivare', 'naval-egt'); ?></div>
        </div>

        <div class="naval-egt-admin-card stat-total-files" data-stat-type="total_files">
            <h3><?php _e('File Caricati', 'naval-egt'); ?></h3>
            <div class="naval-egt-stat-number"><?php echo intval($total_files); ?></div>
            <div class="naval-egt-stat-label"><?php _e('Totale su tutta la piattaforma', 'naval-egt'); ?></div>
        </div>
    </div>

    <!-- Main Content Tabs -->
    <div class="naval-egt-admin-tabs">
        <div class="naval-egt-admin-tab-nav">
            <button data-tab="overview" class="active"><?php _e('Panoramica', 'naval-egt'); ?></button>
            <button data-tab="users"><?php _e('Gestione Utenti', 'naval-egt'); ?></button>
            <button data-tab="files"><?php _e('Gestione File', 'naval-egt'); ?></button>
            <button data-tab="logs"><?php _e('Log Attività', 'naval-egt'); ?></button>
            <button data-tab="tools"><?php _e('Strumenti', 'naval-egt'); ?></button>
        </div>

        <div class="naval-egt-admin-tab-content">
            <!-- Overview Tab -->
            <div id="overview" class="naval-egt-admin-tab-pane active">
                <div class="naval-egt-admin-settings-grid">
                    <!-- Recent Activity -->
                    <div class="naval-egt-admin-settings-section">
                        <div class="naval-egt-admin-settings-header">
                            <h3><?php _e('Attività Recente', 'naval-egt'); ?></h3>
                        </div>
                        <div class="naval-egt-admin-settings-content">
                            <?php if (!empty($recent_activity)): ?>
                            <div class="naval-egt-recent-activity">
                                <?php foreach ($recent_activity as $activity): ?>
                                <div class="naval-egt-activity-item">
                                    <div class="naval-egt-activity-icon">
                                        <?php 
                                        switch ($activity->action) {
                                            case 'upload':
                                                echo '⬆️';
                                                break;
                                            case 'download':
                                                echo '⬇️';
                                                break;
                                            case 'delete':
                                                echo '🗑️';
                                                break;
                                            default:
                                                echo '📄';
                                        }
                                        ?>
                                    </div>
                                    <div class="naval-egt-activity-details">
                                        <div class="naval-egt-activity-user">
                                            <strong><?php echo esc_html($activity->display_name ?: 'Utente eliminato'); ?></strong>
                                            <?php if ($activity->user_email): ?>
                                            <small>(<?php echo esc_html($activity->user_email); ?>)</small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="naval-egt-activity-action">
                                            <?php 
                                            switch ($activity->action) {
                                                case 'upload':
                                                    printf(__('ha caricato %s', 'naval-egt'), '<code>' . esc_html($activity->file_name) . '</code>');
                                                    break;
                                                case 'download':
                                                    printf(__('ha scaricato %s', 'naval-egt'), '<code>' . esc_html($activity->file_name) . '</code>');
                                                    break;
                                                case 'delete':
                                                    printf(__('ha eliminato %s', 'naval-egt'), '<code>' . esc_html($activity->file_name) . '</code>');
                                                    break;
                                                case 'create_folder':
                                                    printf(__('ha creato la cartella %s', 'naval-egt'), '<code>' . esc_html($activity->file_name) . '</code>');
                                                    break;
                                            }
                                            ?>
                                        </div>
                                        <div class="naval-egt-activity-time">
                                            <?php echo date_i18n('d/m/Y H:i', strtotime($activity->created_at)); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="naval-egt-admin-empty">
                                <p><?php _e('Nessuna attività recente', 'naval-egt'); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="naval-egt-admin-settings-section">
                        <div class="naval-egt-admin-settings-header">
                            <h3><?php _e('Statistiche Veloci', 'naval-egt'); ?></h3>
                        </div>
                        <div class="naval-egt-admin-settings-content">
                            <div class="naval-egt-quick-stats">
                                <div class="naval-egt-quick-stat">
                                    <span class="stat-label"><?php _e('Upload questa settimana:', 'naval-egt'); ?></span>
                                    <span class="stat-value"><?php echo intval($recent_uploads); ?></span>
                                </div>
                                <div class="naval-egt-quick-stat">
                                    <span class="stat-label"><?php _e('Nuovi utenti questo mese:', 'naval-egt'); ?></span>
                                    <span class="stat-value">
                                        <?php 
                                        $new_users_this_month = count(get_users(array(
                                            'role' => 'naval_egt_client',
                                            'date_query' => array(
                                                array(
                                                    'after' => '1 month ago'
                                                )
                                            )
                                        )));
                                        echo intval($new_users_this_month);
                                        ?>
                                    </span>
                                </div>
                                <div class="naval-egt-quick-stat">
                                    <span class="stat-label"><?php _e('Sistema operativo:', 'naval-egt'); ?></span>
                                    <span class="stat-value"><?php echo PHP_OS; ?></span>
                                </div>
                                <div class="naval-egt-quick-stat">
                                    <span class="stat-label"><?php _e('Versione WordPress:', 'naval-egt'); ?></span>
                                    <span class="stat-value"><?php echo get_bloginfo('version'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="naval-egt-admin-form">
                    <h3><?php _e('Azioni Rapide', 'naval-egt'); ?></h3>
                    <div class="naval-egt-admin-flex naval-egt-admin-flex-gap-20">
                        <a href="<?php echo admin_url('admin.php?page=naval-egt-users'); ?>" class="naval-egt-admin-btn naval-egt-admin-btn-primary">
                            <?php _e('Gestisci Utenti', 'naval-egt'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=naval-egt-settings'); ?>" class="naval-egt-admin-btn naval-egt-admin-btn-secondary">
                            <?php _e('Impostazioni', 'naval-egt'); ?>
                        </a>
                        <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-success naval-egt-refresh-stats">
                            <?php _e('Aggiorna Statistiche', 'naval-egt'); ?>
                        </button>
                        <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-warning btn-export-users">
                            <?php _e('Esporta Utenti', 'naval-egt'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Users Tab -->
            <div id="users" class="naval-egt-admin-tab-pane">
                <div id="users-table-container">
                    <div class="naval-egt-admin-loading">
                        <div class="naval-egt-admin-loading-spinner"></div>
                        <p><?php _e('Caricamento utenti...', 'naval-egt'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Files Tab -->
            <div id="files" class="naval-egt-admin-tab-pane">
                <div class="naval-egt-admin-form">
                    <h3><?php _e('Gestione File Globale', 'naval-egt'); ?></h3>
                    <p><?php _e('Carica file per specifici utenti o gestisci i file esistenti.', 'naval-egt'); ?></p>
                    
                    <div class="naval-egt-admin-form-row">
                        <div class="naval-egt-form-group">
                            <label for="select-user-for-upload"><?php _e('Seleziona Utente', 'naval-egt'); ?></label>
                            <select id="select-user-for-upload" class="widefat">
                                <option value=""><?php _e('Seleziona un utente...', 'naval-egt'); ?></option>
                                <?php
                                $all_users = get_users(array('role' => 'naval_egt_client'));
                                foreach ($all_users as $user) {
                                    $company = get_user_meta($user->ID, 'naval_egt_company', true);
                                    echo '<option value="' . $user->ID . '">' . esc_html($user->display_name) . 
                                         ($company ? ' (' . esc_html($company) . ')' : '') . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="naval-egt-form-group">
                            <label for="select-folder-for-upload"><?php _e('Cartella di Destinazione', 'naval-egt'); ?></label>
                            <select id="select-folder-for-upload" class="widefat">
                                <option value="downloads/"><?php _e('Downloads (visibile all\'utente)', 'naval-egt'); ?></option>
                                <option value="shared/"><?php _e('Shared (cartella condivisa)', 'naval-egt'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="naval-egt-form-group">
                        <label for="admin-bulk-file-upload"><?php _e('Carica File', 'naval-egt'); ?></label>
                        <div class="naval-egt-admin-file-upload">
                            <div class="naval-egt-admin-file-upload-icon">📁</div>
                            <div class="naval-egt-admin-file-upload-text"><?php _e('Clicca per selezionare i file o trascinali qui', 'naval-egt'); ?></div>
                            <div class="naval-egt-admin-file-upload-subtext"><?php _e('Supporta PDF, DOC, XLS, immagini, file CAD', 'naval-egt'); ?></div>
                            <input type="file" id="admin-bulk-file-upload" multiple style="display: none;">
                        </div>
                    </div>
                    
                    <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-primary" id="btn-admin-upload">
                        <?php _e('Carica File per Utente', 'naval-egt'); ?>
                    </button>
                </div>

                <!-- File Statistics -->
                <div class="naval-egt-admin-form">
                    <h3><?php _e('Statistiche File', 'naval-egt'); ?></h3>
                    <div class="naval-egt-admin-settings-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                        <?php
                        $file_stats = $wpdb->get_results("
                            SELECT 
                                action,
                                COUNT(*) as count,
                                SUM(file_size) as total_size
                            FROM $logs_table 
                            WHERE action IN ('upload', 'download') 
                            GROUP BY action
                        ");
                        
                        foreach ($file_stats as $stat):
                        ?>
                        <div class="naval-egt-admin-card">
                            <h3><?php echo $stat->action === 'upload' ? 'Upload' : 'Download'; ?></h3>
                            <div class="naval-egt-stat-number"><?php echo intval($stat->count); ?></div>
                            <div class="naval-egt-stat-label"><?php echo size_format($stat->total_size ?: 0); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Logs Tab -->
            <div id="logs" class="naval-egt-admin-tab-pane">
                <div id="logs-table-container">
                    <div class="naval-egt-admin-loading">
                        <div class="naval-egt-admin-loading-spinner"></div>
                        <p><?php _e('Caricamento log...', 'naval-egt'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Tools Tab -->
            <div id="tools" class="naval-egt-admin-tab-pane">
                <div class="naval-egt-admin-settings-grid">
                    <!-- Database Tools -->
                    <div class="naval-egt-admin-form">
                        <h3><?php _e('Strumenti Database', 'naval-egt'); ?></h3>
                        <p><?php _e('Strumenti per la manutenzione del database e pulizia dei dati.', 'naval-egt'); ?></p>
                        
                        <div class="naval-egt-admin-flex naval-egt-admin-flex-gap-10" style="flex-direction: column; align-items: flex-start;">
                            <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-warning" onclick="cleanupOldLogs()">
                                <?php _e('Pulisci Log Vecchi (>90 giorni)', 'naval-egt'); ?>
                            </button>
                            <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-warning" onclick="cleanupExpiredSessions()">
                                <?php _e('Pulisci Sessioni Scadute', 'naval-egt'); ?>
                            </button>
                            <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-secondary" onclick="validateFtpFolders()">
                                <?php _e('Valida Cartelle FTP', 'naval-egt'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Export Tools -->
                    <div class="naval-egt-admin-form">
                        <h3><?php _e('Strumenti di Esportazione', 'naval-egt'); ?></h3>
                        <p><?php _e('Esporta dati per backup o analisi esterne.', 'naval-egt'); ?></p>
                        
                        <div class="naval-egt-admin-flex naval-egt-admin-flex-gap-10" style="flex-direction: column; align-items: flex-start;">
                            <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-primary btn-export-users">
                                <?php _e('Esporta Tutti gli Utenti (CSV)', 'naval-egt'); ?>
                            </button>
                            <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-primary btn-export-logs">
                                <?php _e('Esporta Log Attività (CSV)', 'naval-egt'); ?>
                            </button>
                            <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-secondary" onclick="exportSystemInfo()">
                                <?php _e('Esporta Info Sistema', 'naval-egt'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- System Info -->
                    <div class="naval-egt-admin-form">
                        <h3><?php _e('Informazioni Sistema', 'naval-egt'); ?></h3>
                        <div class="naval-egt-system-info">
                            <p><strong><?php _e('Versione Plugin:', 'naval-egt'); ?></strong> <?php echo NAVAL_EGT_VERSION; ?></p>
                            <p><strong><?php _e('WordPress:', 'naval-egt'); ?></strong> <?php echo get_bloginfo('version'); ?></p>
                            <p><strong><?php _e('PHP:', 'naval-egt'); ?></strong> <?php echo PHP_VERSION; ?></p>
                            <p><strong><?php _e('Server:', 'naval-egt'); ?></strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></p>
                            <p><strong><?php _e('Max Upload Size:', 'naval-egt'); ?></strong> <?php echo size_format(wp_max_upload_size()); ?></p>
                            <p><strong><?php _e('Memory Limit:', 'naval-egt'); ?></strong> <?php echo ini_get('memory_limit'); ?></p>
                            <p><strong><?php _e('Directory FTP:', 'naval-egt'); ?></strong> 
                               <?php 
                               $upload_dir = wp_upload_dir();
                               echo $upload_dir['basedir'] . '/naval-egt-ftp/';
                               ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
// DEBUG Naval EGT
jQuery(document).ready(function($) {
    // Test AJAX connection
    const testButton = $('<button type="button" style="position: fixed; top: 100px; right: 20px; z-index: 9999; background: #ff6b6b; color: white; padding: 10px; border: none; border-radius: 4px; cursor: pointer;">🧪 TEST AJAX</button>');
    
    $('body').append(testButton);
    
    testButton.click(function() {
        $.ajax({
            url: ajaxurl,
            type: 'POST', 
            data: {
                action: 'naval_egt_admin_list_users',
                nonce: '<?php echo wp_create_nonce('naval_egt_admin'); ?>'
            },
            success: function(response) {
                console.log('✅ AJAX Test Success:', response);
                alert('✅ AJAX funziona!\nUtenti trovati: ' + (response.data?.users?.length || 0));
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX Test Error:', error);
                alert('❌ AJAX Error: ' + error);
            }
        });
    });
    
    // Show current ajaxurl
    console.log('🔧 Naval EGT Debug Info:');
    console.log('AJAX URL:', ajaxurl);
    console.log('Admin User:', <?php echo current_user_can('manage_options') ? 'true' : 'false'; ?>);
});
</script>
</div>

<script>

function addNewUser() {
    const $form = $('#add-user-form');
    const $btn = $('.btn-add-user, button[onclick="addNewUser()"]');
    
    // Mostra loading
    $btn.prop('disabled', true).text('⏳ Creazione in corso...');
    
    // Prepara i dati del form
    const formData = new FormData();
    formData.append('action', 'naval_egt_add_user');
    formData.append('naval_egt_add_user_nonce', '<?php echo wp_create_nonce("naval_egt_add_user"); ?>');
    formData.append('first_name', $('#first_name').val());
    formData.append('last_name', $('#last_name').val());
    formData.append('email', $('#email').val());
    formData.append('company', $('#company').val());
    formData.append('phone', $('#phone').val());
    formData.append('username', $('#username').val());
    formData.append('password', $('#password').val());
    formData.append('status', $('#status').val());
    formData.append('send_welcome_email', $('#send_welcome_email').is(':checked') ? '1' : '0');
    
    jQuery.ajax({
        url: naval_egt_admin.ajax_url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            console.log('Add user response:', response);
            
            if (response.success) {
                showNotice('✅ Utente creato con successo!', 'success');
                $('#add-user-modal').hide();
                $('#add-user-form')[0].reset();
                loadUsersTable();
            } else {
                showNotice('❌ Errore: ' + (response.data?.message || 'Errore sconosciuto'), 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showNotice('❌ Errore di connessione: ' + error, 'error');
        },
        complete: function() {
            $btn.prop('disabled', false).text('➕ Aggiungi Utente');
        }
    });
}

// Assicurati che il pulsante sia collegato correttamente:
jQuery(document).ready(function($) {
    // Collega il pulsante aggiungi utente
    $(document).on('click', '.btn-add-user', function() {
        $('#add-user-modal').show();
    });
    
    // Collega il form submit
    $(document).on('submit', '#add-user-form', function(e) {
        e.preventDefault();
        addNewUser();
    });
});

// Tools functions
function cleanupOldLogs() {
    if (confirm('Sei sicuro di voler eliminare i log più vecchi di 90 giorni?')) {
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'naval_egt_cleanup_old_logs',
                nonce: '<?php echo wp_create_nonce('naval_egt_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    NavalEGTAdmin.showNotice('Log vecchi eliminati con successo', 'success');
                } else {
                    NavalEGTAdmin.showNotice('Errore durante la pulizia dei log', 'error');
                }
            }
        });
    }
}

function cleanupExpiredSessions() {
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'naval_egt_cleanup_expired_sessions',
            nonce: '<?php echo wp_create_nonce('naval_egt_admin_nonce'); ?>'
        },
        success: function(response) {
            if (response.success) {
                NavalEGTAdmin.showNotice('Sessioni scadute eliminate', 'success');
            } else {
                NavalEGTAdmin.showNotice('Errore durante la pulizia delle sessioni', 'error');
            }
        }
    });
}

function validateFtpFolders() {
    NavalEGTAdmin.showNotice('Validazione cartelle FTP in corso...', 'info');
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'naval_egt_validate_ftp_folders',
            nonce: '<?php echo wp_create_nonce('naval_egt_admin_nonce'); ?>'
        },
        success: function(response) {
            if (response.success) {
                NavalEGTAdmin.showNotice(response.data.message, 'success');
            } else {
                NavalEGTAdmin.showNotice('Errore durante la validazione', 'error');
            }
        }
    });
}

function exportSystemInfo() {
    window.location.href = ajaxurl + '?action=naval_egt_export_system_info&nonce=<?php echo wp_create_nonce('naval_egt_admin_nonce'); ?>';
}

// Admin file upload
jQuery(document).ready(function($) {
    $('#admin-bulk-file-upload').change(function() {
        const files = this.files;
        if (files.length > 0) {
            let fileNames = [];
            for (let i = 0; i < files.length; i++) {
                fileNames.push(files[i].name);
            }
            $('.naval-egt-admin-file-upload-text').text(fileNames.join(', '));
        }
    });
    
    $('.naval-egt-admin-file-upload').click(function() {
        $('#admin-bulk-file-upload').click();
    });
    
    $('#btn-admin-upload').click(function() {
        const userId = $('#select-user-for-upload').val();
        const folder = $('#select-folder-for-upload').val();
        const files = $('#admin-bulk-file-upload')[0].files;
        
        if (!userId) {
            NavalEGTAdmin.showNotice('Seleziona un utente', 'warning');
            return;
        }
        
        if (files.length === 0) {
            NavalEGTAdmin.showNotice('Seleziona almeno un file', 'warning');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'naval_egt_admin_upload_file_for_user');
        formData.append('nonce', '<?php echo wp_create_nonce('naval_egt_admin_nonce'); ?>');
        formData.append('user_id', userId);
        formData.append('subfolder', folder);
        formData.append('file', files[0]);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    NavalEGTAdmin.showNotice(response.data.message, 'success');
                    $('#admin-bulk-file-upload').val('');
                    $('.naval-egt-admin-file-upload-text').text('Clicca per selezionare i file o trascinali qui');
                } else {
                    NavalEGTAdmin.showNotice(response.data.message, 'error');
                }
            },
            error: function() {
                NavalEGTAdmin.showNotice('Errore durante il caricamento', 'error');
            }
        });
    });
});
</script>

<style>
.naval-egt-recent-activity {
    max-height: 400px;
    overflow-y: auto;
}

.naval-egt-activity-item {
    display: flex;
    align-items: flex-start;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f1;
}

.naval-egt-activity-item:last-child {
    border-bottom: none;
}

.naval-egt-activity-icon {
    font-size: 1.2em;
    margin-right: 12px;
    margin-top: 2px;
}

.naval-egt-activity-details {
    flex: 1;
}

.naval-egt-activity-user {
    margin-bottom: 4px;
}

.naval-egt-activity-action {
    margin-bottom: 4px;
    color: #666;
}

.naval-egt-activity-time {
    font-size: 0.9em;
    color: #999;
}

.naval-egt-quick-stats {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.naval-egt-quick-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f1;
}

.naval-egt-quick-stat:last-child {
    border-bottom: none;
}

.stat-label {
    color: #666;
}

.stat-value {
    font-weight: 600;
    color: #2a5298;
}

.naval-egt-system-info p {
    margin: 8px 0;
    padding: 6px 0;
    border-bottom: 1px solid #f0f0f1;
}

.naval-egt-system-info p:last-child {
    border-bottom: none;
}

code {
    background: #f8f9fa;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.9em;
}

.action-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.action-upload {
    background: #d4edda;
    color: #155724;
}

.action-download {
    background: #d1ecf1;
    color: #0c5460;
}

.action-delete {
    background: #f8d7da;
    color: #721c24;
}

.action-create_folder {
    background: #fff3cd;
    color: #856404;
}
</style>