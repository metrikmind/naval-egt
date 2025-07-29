<?php
/**
 * Naval EGT Admin - Users Management Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['users']) && wp_verify_nonce($_POST['naval_egt_users_nonce'], 'naval_egt_users_bulk')) {
    $action = sanitize_text_field($_POST['bulk_action']);
    $user_ids = array_map('intval', $_POST['users']);
    $processed = 0;
    
    foreach ($user_ids as $user_id) {
        $user = get_user_by('id', $user_id);
        if ($user && in_array('naval_egt_client', $user->roles)) {
            switch ($action) {
                case 'activate':
                    update_user_meta($user_id, 'naval_egt_status', 'active');
                    $processed++;
                    break;
                case 'suspend':
                    update_user_meta($user_id, 'naval_egt_status', 'suspended');
                    $processed++;
                    break;
                case 'delete':
                    // Delete user's FTP folder
                    $ftp_folder = get_user_meta($user_id, 'naval_egt_ftp_folder', true);
                    if ($ftp_folder) {
                        $upload_dir = wp_upload_dir();
                        $folder_path = $upload_dir['basedir'] . '/naval-egt-ftp/' . $ftp_folder;
                        if (is_dir($folder_path)) {
                            naval_egt_delete_directory_recursive($folder_path);
                        }
                    }
                    
                    // Delete from custom tables
                    global $wpdb;
                    $wpdb->delete($wpdb->prefix . 'naval_egt_sessions', array('user_id' => $user_id));
                    $wpdb->delete($wpdb->prefix . 'naval_egt_ftp_logs', array('user_id' => $user_id));
                    
                    // Delete WordPress user
                    wp_delete_user($user_id);
                    $processed++;
                    break;
            }
        }
    }
    
    echo '<div class="naval-egt-admin-notice naval-egt-admin-notice-success"><p>' . 
         sprintf(__('Azione applicata a %d utenti.', 'naval-egt'), $processed) . '</p></div>';
}

// Get statistics
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
$suspended_users = count(get_users(array(
    'role' => 'naval_egt_client',
    'meta_query' => array(
        array(
            'key' => 'naval_egt_status',
            'value' => 'suspended',
            'compare' => '='
        )
    )
)));

// Helper function for recursive directory deletion
function naval_egt_delete_directory_recursive($dir) {
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
            naval_egt_delete_directory_recursive($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}
?>

<div class="naval-egt-admin-wrap">
    <div class="naval-egt-admin-header">
        <h1><?php _e('Gestione Utenti Naval EGT', 'naval-egt'); ?></h1>
        <p><?php _e('Gestisci gli utenti dell\'area riservata, modifica i loro permessi e monitora l\'attività.', 'naval-egt'); ?></p>
    </div>

    <!-- User Statistics -->
    <div class="naval-egt-admin-cards">
        <div class="naval-egt-admin-card">
            <h3><?php _e('Totale Utenti', 'naval-egt'); ?></h3>
            <div class="naval-egt-stat-number"><?php echo intval($total_users); ?></div>
            <div class="naval-egt-stat-label"><?php _e('Registrati nell\'area riservata', 'naval-egt'); ?></div>
        </div>

        <div class="naval-egt-admin-card">
            <h3><?php _e('Utenti Attivi', 'naval-egt'); ?></h3>
            <div class="naval-egt-stat-number"><?php echo intval($active_users); ?></div>
            <div class="naval-egt-stat-label"><?php _e('Con accesso confermato', 'naval-egt'); ?></div>
        </div>

        <div class="naval-egt-admin-card">
            <h3><?php _e('In Attesa', 'naval-egt'); ?></h3>
            <div class="naval-egt-stat-number"><?php echo intval($pending_users); ?></div>
            <div class="naval-egt-stat-label"><?php _e('Da attivare manualmente', 'naval-egt'); ?></div>
        </div>

        <div class="naval-egt-admin-card">
            <h3><?php _e('Sospesi', 'naval-egt'); ?></h3>
            <div class="naval-egt-stat-number"><?php echo intval($suspended_users); ?></div>
            <div class="naval-egt-stat-label"><?php _e('Accesso bloccato', 'naval-egt'); ?></div>
        </div>
    </div>

    <!-- Users Management Interface -->
    <div class="naval-egt-admin-tabs">
        <div class="naval-egt-admin-tab-nav">
            <button data-tab="users-list" class="active"><?php _e('Lista Utenti', 'naval-egt'); ?></button>
            <button data-tab="add-user"><?php _e('Aggiungi Utente', 'naval-egt'); ?></button>
            <button data-tab="import-export"><?php _e('Importa/Esporta', 'naval-egt'); ?></button>
            <button data-tab="user-activity"><?php _e('Attività Utenti', 'naval-egt'); ?></button>
        </div>

        <div class="naval-egt-admin-tab-content">
            <!-- Users List Tab -->
            <div id="users-list" class="naval-egt-admin-tab-pane active">
                <div class="naval-egt-admin-table-container">
                    <div class="naval-egt-admin-table-header">
                        <h3><?php _e('Tutti gli Utenti', 'naval-egt'); ?></h3>
                        <div class="naval-egt-admin-table-filters">
                            <input type="text" id="user-search" placeholder="<?php _e('Cerca utenti...', 'naval-egt'); ?>" class="admin-search-input">
                            <select id="status-filter" class="admin-filter-select">
                                <option value=""><?php _e('Tutti gli status', 'naval-egt'); ?></option>
                                <option value="active"><?php _e('Attivi', 'naval-egt'); ?></option>
                                <option value="pending"><?php _e('In attesa', 'naval-egt'); ?></option>
                                <option value="suspended"><?php _e('Sospesi', 'naval-egt'); ?></option>
                            </select>
                            <button class="naval-egt-admin-btn naval-egt-admin-btn-primary btn-refresh-users">
                                <?php _e('Aggiorna', 'naval-egt'); ?>
                            </button>
                        </div>
                    </div>

                    <form method="post" id="users-bulk-form">
                        <?php wp_nonce_field('naval_egt_users_bulk', 'naval_egt_users_nonce'); ?>
                        
                        <div id="users-table-container">
                            <div class="naval-egt-admin-loading">
                                <div class="naval-egt-admin-loading-spinner"></div>
                                <p><?php _e('Caricamento utenti...', 'naval-egt'); ?></p>
                            </div>
                        </div>

                        <!-- Bulk Actions -->
                        <div class="naval-egt-admin-bulk-actions" style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                            <div class="naval-egt-admin-flex naval-egt-admin-flex-gap-10">
                                <select name="bulk_action" id="bulk-action-select">
                                    <option value=""><?php _e('Azioni multiple', 'naval-egt'); ?></option>
                                    <option value="activate"><?php _e('Attiva selezionati', 'naval-egt'); ?></option>
                                    <option value="suspend"><?php _e('Sospendi selezionati', 'naval-egt'); ?></option>
                                    <option value="delete"><?php _e('Elimina selezionati', 'naval-egt'); ?></option>
                                </select>
                                <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-secondary" onclick="applyBulkAction()">
                                    <?php _e('Applica', 'naval-egt'); ?>
                                </button>
                                <span class="selected-count" style="margin-left: 15px; color: #666;">
                                    <?php _e('0 utenti selezionati', 'naval-egt'); ?>
                                </span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add User Tab -->
            <div id="add-user" class="naval-egt-admin-tab-pane">
                <!-- Sostituisci il form di aggiunta utente con questo: -->
<div class="naval-egt-admin-form">
    <h3><?php _e('Aggiungi Nuovo Utente', 'naval-egt'); ?></h3>
    
    <form id="add-user-form">
        <?php wp_nonce_field('naval_egt_add_user', 'naval_egt_add_user_nonce'); ?>
        
        <div class="naval-egt-admin-form-row">
            <div class="naval-egt-form-group">
                <label for="first_name">Nome *</label>
                <input type="text" id="first_name" name="first_name" required>
            </div>
            <div class="naval-egt-form-group">
                <label for="last_name">Cognome *</label>
                <input type="text" id="last_name" name="last_name" required>
            </div>
        </div>
        
        <div class="naval-egt-admin-form-row">
            <div class="naval-egt-form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="naval-egt-form-group">
                <label for="company">Azienda *</label>
                <input type="text" id="company" name="company" required>
            </div>
        </div>
        
        <div class="naval-egt-admin-form-row">
            <div class="naval-egt-form-group">
                <label for="username">Username (opzionale)</label>
                <input type="text" id="username" name="username">
            </div>
            <div class="naval-egt-form-group">
                <label for="phone">Telefono</label>
                <input type="tel" id="phone" name="phone">
            </div>
        </div>
        
        <div class="naval-egt-form-group">
            <label for="password">Password (opzionale)</label>
            <input type="password" id="password" name="password">
            <small>Se vuota, sarà generata automaticamente</small>
        </div>
        
        <div class="naval-egt-form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="active">Attivo</option>
                <option value="pending">In attesa</option>
            </select>
        </div>
        
        <button type="submit" class="naval-egt-admin-btn naval-egt-admin-btn-primary">
            Crea Utente
        </button>
        
        <div id="add-user-result"></div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#add-user-form').on('submit', function(e) {
        e.preventDefault();
        addNewUser();
    });
});
</script>

</div>

<script>
jQuery(document).ready(function($) {
    $('#add-user-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const $result = $('#add-user-result');
        
        // Disable button
        $button.prop('disabled', true).text('Creazione in corso...');
        $result.html('');
        
        // Get form data
        const formData = $form.serialize();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData + '&action=naval_egt_add_user',
            success: function(response) {
                console.log('Server response:', response);
                
                if (response.success) {
                    $result.html('<div style="color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0;">' +
                        '<strong>✅ Utente creato con successo!</strong><br>' +
                        'ID: ' + response.data.user_id + '<br>' +
                        'Username: ' + response.data.username +
                        '</div>');
                    
                    // Reset form
                    $form[0].reset();
                    
                    // Reload users table if exists
                    if (typeof loadUsersTable === 'function') {
                        setTimeout(loadUsersTable, 1000);
                    }
                } else {
                    $result.html('<div style="color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0;">' +
                        '<strong>❌ Errore:</strong> ' + (response.data?.message || 'Errore sconosciuto') +
                        '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Response:', xhr.responseText);
                
                $result.html('<div style="color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0;">' +
                    '<strong>❌ Errore AJAX:</strong> ' + error + '<br>' +
                    '<small>Controlla la console per dettagli (F12)</small>' +
                    '</div>');
            },
            complete: function() {
                // Re-enable button
                $button.prop('disabled', false).text('<?php _e('Crea Utente', 'naval-egt'); ?>');
            }
        });
    });
});
</script>
            
        </div>

            <!-- Import/Export Tab -->
            <div id="import-export" class="naval-egt-admin-tab-pane">
                <div class="naval-egt-admin-settings-grid">
                    <!-- Export Section -->
                    <div class="naval-egt-admin-form">
                        <h3><?php _e('Esporta Utenti', 'naval-egt'); ?></h3>
                        <p><?php _e('Esporta i dati degli utenti in formato CSV per backup o analisi.', 'naval-egt'); ?></p>
                        
                        <div class="naval-egt-form-group">
                            <label for="export_status_filter"><?php _e('Filtra per Status', 'naval-egt'); ?></label>
                            <select id="export_status_filter">
                                <option value=""><?php _e('Tutti gli utenti', 'naval-egt'); ?></option>
                                <option value="active"><?php _e('Solo attivi', 'naval-egt'); ?></option>
                                <option value="pending"><?php _e('Solo in attesa', 'naval-egt'); ?></option>
                                <option value="suspended"><?php _e('Solo sospesi', 'naval-egt'); ?></option>
                            </select>
                        </div>
                        
                        <div class="naval-egt-form-group">
                            <label>
                                <input type="checkbox" id="include_activity_data" value="1">
                                <?php _e('Includi dati attività (ultimo accesso, file caricati)', 'naval-egt'); ?>
                            </label>
                        </div>
                        
                        <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-primary" onclick="exportUsers()">
                            <?php _e('Esporta CSV', 'naval-egt'); ?>
                        </button>
                    </div>

                    <!-- Import Section -->
                    <div class="naval-egt-admin-form">
                        <h3><?php _e('Importa Utenti', 'naval-egt'); ?></h3>
                        <p><?php _e('Importa utenti da un file CSV. Il file deve contenere le colonne: nome, cognome, email, azienda.', 'naval-egt'); ?></p>
                        
                        <div class="naval-egt-form-group">
                            <label for="import_csv_file"><?php _e('File CSV', 'naval-egt'); ?></label>
                            <input type="file" id="import_csv_file" accept=".csv" required>
                            <div class="description">
                                <?php _e('Formato richiesto: Nome, Cognome, Email, Azienda, Telefono (opzionale)', 'naval-egt'); ?>
                            </div>
                        </div>
                        
                        <div class="naval-egt-form-group">
                            <label>
                                <input type="checkbox" id="send_import_welcome_emails" value="1" checked>
                                <?php _e('Invia email di benvenuto agli utenti importati', 'naval-egt'); ?>
                            </label>
                        </div>
                        
                        <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-success" onclick="importUsers()">
                            <?php _e('Importa Utenti', 'naval-egt'); ?>
                        </button>
                        
                        <div id="import-progress" style="display: none; margin-top: 15px;">
                            <div class="naval-egt-progress-bar">
                                <div class="naval-egt-progress-fill"></div>
                            </div>
                            <div class="import-status"></div>
                        </div>
                    </div>
                </div>

                <!-- CSV Template Download -->
                <div class="naval-egt-admin-form">
                    <h3><?php _e('Template CSV', 'naval-egt'); ?></h3>
                    <p><?php _e('Scarica un template CSV da compilare per l\'importazione utenti.', 'naval-egt'); ?></p>
                    
                    <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-secondary" onclick="downloadTemplate()">
                        <?php _e('Scarica Template CSV', 'naval-egt'); ?>
                    </button>
                </div>
            </div>

            <!-- User Activity Tab -->
            <div id="user-activity" class="naval-egt-admin-tab-pane">
                <div class="naval-egt-admin-form">
                    <h3><?php _e('Monitoraggio Attività Utenti', 'naval-egt'); ?></h3>
                    
                    <div class="naval-egt-admin-form-row">
                        <div class="naval-egt-form-group">
                            <label for="activity_user_filter"><?php _e('Filtra per Utente', 'naval-egt'); ?></label>
                            <select id="activity_user_filter">
                                <option value=""><?php _e('Tutti gli utenti', 'naval-egt'); ?></option>
                                <?php
                                $all_users = get_users(array('role' => 'naval_egt_client'));
                                foreach ($all_users as $user) {
                                    echo '<option value="' . $user->ID . '">' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="naval-egt-form-group">
                            <label for="activity_date_from"><?php _e('Da Data', 'naval-egt'); ?></label>
                            <input type="date" id="activity_date_from" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                        </div>
                        <div class="naval-egt-form-group">
                            <label for="activity_date_to"><?php _e('A Data', 'naval-egt'); ?></label>
                            <input type="date" id="activity_date_to" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-primary" onclick="loadUserActivity()">
                        <?php _e('Carica Attività', 'naval-egt'); ?>
                    </button>
                </div>

                <div id="user-activity-results">
                    <div class="naval-egt-admin-empty">
                        <p><?php _e('Seleziona i filtri e clicca "Carica Attività" per visualizzare i dati.', 'naval-egt'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- User Edit Modal -->
<div id="edit-user-modal" class="naval-egt-admin-modal" style="display: none;">
    <div class="naval-egt-admin-modal-content">
        <div class="naval-egt-admin-modal-header">
            <h3><?php _e('Modifica Utente', 'naval-egt'); ?></h3>
            <button class="naval-egt-admin-modal-close">&times;</button>
        </div>
        
        <form id="edit-user-form">
            <input type="hidden" id="edit_user_id" name="user_id">
            
            <div class="naval-egt-admin-form-row">
                <div class="naval-egt-form-group">
                    <label for="edit_first_name"><?php _e('Nome', 'naval-egt'); ?></label>
                    <input type="text" id="edit_first_name" name="first_name">
                </div>
                <div class="naval-egt-form-group">
                    <label for="edit_last_name"><?php _e('Cognome', 'naval-egt'); ?></label>
                    <input type="text" id="edit_last_name" name="last_name">
                </div>
            </div>
            
            <div class="naval-egt-form-group">
                <label for="edit_email"><?php _e('Email', 'naval-egt'); ?></label>
                <input type="email" id="edit_email" name="email">
            </div>
            
            <div class="naval-egt-admin-form-row">
                <div class="naval-egt-form-group">
                    <label for="edit_company"><?php _e('Azienda', 'naval-egt'); ?></label>
                    <input type="text" id="edit_company" name="company">
                </div>
                <div class="naval-egt-form-group">
                    <label for="edit_phone"><?php _e('Telefono', 'naval-egt'); ?></label>
                    <input type="tel" id="edit_phone" name="phone">
                </div>
            </div>
            
            <div class="naval-egt-admin-form-row">
                <div class="naval-egt-form-group">
                    <label for="edit_status"><?php _e('Status', 'naval-egt'); ?></label>
                    <select id="edit_status" name="status">
                        <option value="active"><?php _e('Attivo', 'naval-egt'); ?></option>
                        <option value="pending"><?php _e('In attesa', 'naval-egt'); ?></option>
                        <option value="suspended"><?php _e('Sospeso', 'naval-egt'); ?></option>
                    </select>
                </div>
                <div class="naval-egt-form-group">
                    <label for="edit_ftp_folder"><?php _e('Cartella FTP', 'naval-egt'); ?></label>
                    <input type="text" id="edit_ftp_folder" name="ftp_folder">
                </div>
            </div>
        </form>
        
        <div class="naval-egt-admin-modal-footer">
            <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-secondary naval-egt-admin-modal-close">
                <?php _e('Annulla', 'naval-egt'); ?>
            </button>
            <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-primary" onclick="saveUserChanges()">
                <?php _e('Salva Modifiche', 'naval-egt'); ?>
            </button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Load users on page load
    loadUsersTable();
    
    // Refresh button
    $('.btn-refresh-users').click(function() {
        loadUsersTable();
    });
    
    // Search and filters
    $('#user-search').on('input', debounce(function() {
        loadUsersTable();
    }, 500));
    
    $('#status-filter').change(function() {
        loadUsersTable();
    });
    
    // Checkbox selection
    $(document).on('change', '.select-all-users', function() {
        const isChecked = $(this).prop('checked');
        $('.user-checkbox').prop('checked', isChecked);
        updateSelectedCount();
    });
    
    $(document).on('change', '.user-checkbox', function() {
        updateSelectedCount();
    });
    
    // Add user form
    $('#add-user-form').submit(function(e) {
        e.preventDefault();
        addNewUser();
    });
});

function loadUsersTable() {
    const search = $('#user-search').val();
    const status = $('#status-filter').val();
    
    $('#users-table-container').html('<div class="naval-egt-admin-loading"><div class="naval-egt-admin-loading-spinner"></div><p>Caricamento utenti...</p></div>');
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'naval_egt_admin_list_users',
            nonce: naval_egt_admin.nonce,
            search: search,
            status: status,
            per_page: 20
        },
        success: function(response) {
            if (response.success) {
                renderUsersTable(response.data.users);
            } else {
                $('#users-table-container').html('<div class="naval-egt-admin-empty">Errore durante il caricamento</div>');
            }
        },
        error: function() {
            $('#users-table-container').html('<div class="naval-egt-admin-empty">Errore di connessione</div>');
        }
    });
}

function renderUsersTable(users) {
    let html = '<table class="naval-egt-admin-table"><thead><tr>';
    html += '<th><input type="checkbox" class="select-all-users"></th>';
    html += '<th>Nome</th><th>Email</th><th>Azienda</th><th>Status</th><th>Registrato</th><th>Azioni</th>';
    html += '</tr></thead><tbody>';
    
    if (users.length === 0) {
        html += '<tr><td colspan="7" class="naval-egt-admin-empty">Nessun utente trovato</td></tr>';
    } else {
        users.forEach(user => {
            html += `<tr>
                <td><input type="checkbox" class="user-checkbox" name="users[]" value="${user.id}"></td>
                <td>${user.display_name}</td>
                <td>${user.email}</td>
                <td>${user.company || '-'}</td>
                <td><span class="naval-egt-status-badge naval-egt-status-${user.status}">${getStatusText(user.status)}</span></td>
                <td>${formatDate(user.registered)}</td>
                <td class="naval-egt-actions">
                    <button class="naval-egt-btn-sm naval-egt-btn-edit" onclick="editUser(${user.id})">Modifica</button>
                    <button class="naval-egt-btn-sm ${user.status === 'active' ? 'naval-egt-btn-suspend' : 'naval-egt-btn-activate'}" onclick="toggleUserStatus(${user.id}, '${user.status}')">
                        ${user.status === 'active' ? 'Sospendi' : 'Attiva'}
                    </button>
                    <button class="naval-egt-btn-sm naval-egt-btn-delete" onclick="deleteUser(${user.id})">Elimina</button>
                </td>
            </tr>`;
        });
    }
    
    html += '</tbody></table>';
    $('#users-table-container').html(html);
}

function updateSelectedCount() {
    const selectedCount = $('.user-checkbox:checked').length;
    $('.selected-count').text(selectedCount + ' utenti selezionati');
}

function applyBulkAction() {
    const action = $('#bulk-action-select').val();
    const selectedUsers = $('.user-checkbox:checked');
    
    if (!action) {
        NavalEGTAdmin.showNotice('Seleziona un\'azione', 'warning');
        return;
    }
    
    if (selectedUsers.length === 0) {
        NavalEGTAdmin.showNotice('Seleziona almeno un utente', 'warning');
        return;
    }
    
    if (!confirm(`Sei sicuro di voler applicare l'azione "${action}" a ${selectedUsers.length} utenti?`)) {
        return;
    }
    
    $('#users-bulk-form').submit();
}

function editUser(userId) {
    // Load user data and show modal
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'naval_egt_get_user_data',
            nonce: naval_egt_admin.nonce,
            user_id: userId
        },
        success: function(response) {
            if (response.success) {
                const user = response.data;
                $('#edit_user_id').val(user.id);
                $('#edit_first_name').val(user.first_name);
                $('#edit_last_name').val(user.last_name);
                $('#edit_email').val(user.email);
                $('#edit_company').val(user.company);
                $('#edit_phone').val(user.phone);
                $('#edit_status').val(user.status);
                $('#edit_ftp_folder').val(user.ftp_folder);
                
                $('#edit-user-modal').show();
            } else {
                NavalEGTAdmin.showNotice('Errore durante il caricamento dei dati utente', 'error');
            }
        }
    });
}

function saveUserChanges() {
    const formData = $('#edit-user-form').serialize();
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: formData + '&action=naval_egt_update_user&nonce=' + naval_egt_admin.nonce,
        success: function(response) {
            if (response.success) {
                NavalEGTAdmin.showNotice('Utente aggiornato con successo', 'success');
                $('#edit-user-modal').hide();
                loadUsersTable();
            } else {
                NavalEGTAdmin.showNotice(response.data.message || 'Errore durante l\'aggiornamento', 'error');
            }
        }
    });
}

function toggleUserStatus(userId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'suspended' : 'active';
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'naval_egt_admin_update_user_status',
            nonce: naval_egt_admin.nonce,
            user_id: userId,
            status: newStatus
        },
        success: function(response) {
            if (response.success) {
                NavalEGTAdmin.showNotice('Status utente aggiornato', 'success');
                loadUsersTable();
            } else {
                NavalEGTAdmin.showNotice('Errore durante l\'aggiornamento', 'error');
            }
        }
    });
}

function deleteUser(userId) {
    if (!confirm('Sei sicuro di voler eliminare questo utente? Questa azione eliminerà anche tutti i suoi file e non può essere annullata.')) {
        return;
    }
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'naval_egt_admin_delete_user',
            nonce: naval_egt_admin.nonce,
            user_id: userId
        },
        success: function(response) {
            if (response.success) {
                NavalEGTAdmin.showNotice('Utente eliminato con successo', 'success');
                loadUsersTable();
            } else {
                NavalEGTAdmin.showNotice(response.data.message || 'Errore durante l\'eliminazione', 'error');
            }
        }
    });
}

function addNewUser() {
    const formData = $('#add-user-form').serialize();
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: formData + '&action=naval_egt_add_user&nonce=' + naval_egt_admin.nonce,
        success: function(response) {
            if (response.success) {
                NavalEGTAdmin.showNotice('Utente creato con successo', 'success');
                $('#add-user-form')[0].reset();
                loadUsersTable();
            } else {
                NavalEGTAdmin.showNotice(response.data.message || 'Errore durante la creazione', 'error');
            }
        }
    });
}

function exportUsers() {
    const status = $('#export_status_filter').val();
    const includeActivity = $('#include_activity_data').prop('checked');
    
    const params = new URLSearchParams({
        action: 'naval_egt_export_users',
        nonce: naval_egt_admin.nonce,
        status: status,
        include_activity: includeActivity ? '1' : '0'
    });
    
    window.location.href = ajaxurl + '?' + params.toString();
}

function importUsers() {
    const fileInput = document.getElementById('import_csv_file');
    const file = fileInput.files[0];
    
    if (!file) {
        NavalEGTAdmin.showNotice('Seleziona un file CSV', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'naval_egt_import_users');
    formData.append('nonce', naval_egt_admin.nonce);
    formData.append('csv_file', file);
    formData.append('send_welcome_emails', jQuery('#send_import_welcome_emails').prop('checked') ? '1' : '0');
    
    jQuery('#import-progress').show();
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            jQuery('#import-progress').hide();
            if (response.success) {
                NavalEGTAdmin.showNotice(response.data.message, 'success');
                loadUsersTable();
            } else {
                NavalEGTAdmin.showNotice(response.data.message || 'Errore durante l\'importazione', 'error');
            }
        },
        error: function() {
            jQuery('#import-progress').hide();
            NavalEGTAdmin.showNotice('Errore durante l\'importazione', 'error');
        }
    });
}

function downloadTemplate() {
    window.location.href = ajaxurl + '?action=naval_egt_download_csv_template&nonce=' + naval_egt_admin.nonce;
}

function loadUserActivity() {
    const userId = $('#activity_user_filter').val();
    const dateFrom = $('#activity_date_from').val();
    const dateTo = $('#activity_date_to').val();
    
    jQuery('#user-activity-results').html('<div class="naval-egt-admin-loading"><div class="naval-egt-admin-loading-spinner"></div><p>Caricamento attività...</p></div>');
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'naval_egt_get_user_activity',
            nonce: naval_egt_admin.nonce,
            user_id: userId,
            date_from: dateFrom,
            date_to: dateTo
        },
        success: function(response) {
            if (response.success) {
                renderUserActivity(response.data);
            } else {
                jQuery('#user-activity-results').html('<div class="naval-egt-admin-empty">Errore durante il caricamento</div>');
            }
        }
    });
}

function renderUserActivity(data) {
    // Implementation for rendering user activity data
    jQuery('#user-activity-results').html('<div class="naval-egt-admin-empty">Funzionalità attività utenti in sviluppo</div>');
}

// Utility functions
function getStatusText(status) {
    const statusTexts = {
        'active': 'Attivo',
        'pending': 'In attesa',
        'suspended': 'Sospeso'
    };
    return statusTexts[status] || status;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('it-IT');
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
</script>

<style>
.naval-egt-progress-bar {
    width: 100%;
    height: 8px;
    background-color: #e1e5e9;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 10px;
}

.naval-egt-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #2a5298, #1e3c72);
    border-radius: 4px;
    transition: width 0.3s ease;
    width: 0%;
}

.import-status {
    font-size: 14px;
    color: #666;
    text-align: center;
}

.selected-count {
    font-weight: 500;
}

#edit-user-modal .naval-egt-admin-modal-content {
    max-width: 600px;
}
</style>