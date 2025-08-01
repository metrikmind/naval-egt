<?php
/**
 * Dashboard Admin - Versione corretta con gestione errori
 */

if (!defined('ABSPATH')) {
    exit;
}

// Carica statistiche
$stats = Naval_EGT_Database::get_user_stats();
$dropbox = Naval_EGT_Dropbox::get_instance();

// Gestione sicura dello stato Dropbox con fallback
try {
    if (method_exists($dropbox, 'get_connection_status')) {
        $dropbox_status = $dropbox->get_connection_status();
    } else {
        // Fallback se il metodo non esiste
        $is_configured = $dropbox->is_configured();
        if ($is_configured) {
            // Test connessione
            $test_result = method_exists($dropbox, 'test_connection') ? $dropbox->test_connection() : array('success' => false);
            
            $dropbox_status = array(
                'connected' => $test_result['success'],
                'message' => $test_result['success'] ? 'Connesso' : 'Errore di connessione',
                'has_credentials' => true,
                'account_name' => isset($test_result['account']['name']['display_name']) ? $test_result['account']['name']['display_name'] : '',
                'account_email' => isset($test_result['account']['email']) ? $test_result['account']['email'] : ''
            );
        } else {
            $dropbox_status = array(
                'connected' => false,
                'message' => 'Dropbox non configurato - Autorizza l\'applicazione',
                'has_credentials' => $dropbox->has_credentials(),
                'auth_url' => method_exists($dropbox, 'get_authorization_url') ? $dropbox->get_authorization_url() : null
            );
        }
    }
} catch (Exception $e) {
    // Fallback completo in caso di errore
    $dropbox_status = array(
        'connected' => false,
        'message' => 'Errore nel controllo stato Dropbox',
        'has_credentials' => false
    );
}

$recent_activities = Naval_EGT_Activity_Logger::get_logs(array(), 10, 0);

// Funzione helper per formattare nomi azioni
function format_action_name($action) {
    $actions = array(
        'LOGIN' => 'Accesso',
        'LOGOUT' => 'Disconnessione', 
        'UPLOAD' => 'Caricamento',
        'DOWNLOAD' => 'Scaricamento',
        'DELETE' => 'Eliminazione',
        'REGISTRATION' => 'Registrazione',
        'USER_UPDATE' => 'Modifica Utente',
        'STATUS_CHANGE' => 'Cambio Stato',
        'USER_DELETE' => 'Eliminazione Utente'
    );
    return $actions[$action] ?? $action;
}

// Definisce versione se non presente
if (!defined('NAVAL_EGT_VERSION')) {
    define('NAVAL_EGT_VERSION', '1.0.0');
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Stato Sistema -->
    <div class="naval-egt-system-status">
        <div class="system-status-card">
            <h3><span class="dashicons dashicons-admin-tools"></span> Stato Sistema</h3>
            <div class="status-grid">
                <div class="status-item">
                    <strong>WordPress:</strong>
                    <span class="status-ok">✅ <?php echo get_bloginfo('version'); ?></span>
                </div>
                <div class="status-item">
                    <strong>Plugin Naval EGT:</strong>
                    <span class="status-ok">✅ v<?php echo NAVAL_EGT_VERSION; ?></span>
                </div>
                <div class="status-item">
                    <strong>SSL:</strong>
                    <?php if (is_ssl()): ?>
                        <span class="status-ok">✅ Attivo</span>
                    <?php else: ?>
                        <span class="status-warning">⚠️ Non attivo</span>
                    <?php endif; ?>
                </div>
                <div class="status-item">
                    <strong>Dropbox:</strong>
                    <?php if ($dropbox_status['connected']): ?>
                        <span class="status-ok">✅ Connesso</span>
                        <?php if (!empty($dropbox_status['account_name'])): ?>
                            <small>(<?php echo esc_html($dropbox_status['account_name']); ?>)</small>
                        <?php endif; ?>
                    <?php elseif (isset($dropbox_status['has_credentials']) && $dropbox_status['has_credentials']): ?>
                        <span class="status-warning">⚠️ Da autorizzare</span>
                    <?php else: ?>
                        <span class="status-error">❌ Non configurato</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiche Principali -->
    <div class="naval-egt-stats-grid">
        <div class="stat-card users-stat">
            <div class="stat-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($stats['total_users'] ?? 0); ?></h3>
                <p>Utenti Totali</p>
                <small>
                    <?php echo number_format($stats['active_users'] ?? 0); ?> attivi, 
                    <?php echo number_format($stats['suspended_users'] ?? 0); ?> sospesi
                </small>
            </div>
            <div class="stat-actions">
                <a href="<?php echo admin_url('admin.php?page=naval-egt&tab=users'); ?>" class="button button-small">
                    Gestisci
                </a>
            </div>
        </div>

        <div class="stat-card files-stat">
            <div class="stat-icon">
                <span class="dashicons dashicons-media-default"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($stats['total_files'] ?? 0); ?></h3>
                <p>File Archiviati</p>
                <small>
                    <?php echo size_format($stats['total_storage'] ?? 0); ?> utilizzati
                </small>
            </div>
            <div class="stat-actions">
                <a href="<?php echo admin_url('admin.php?page=naval-egt&tab=files'); ?>" class="button button-small">
                    Gestisci
                </a>
            </div>
        </div>

        <div class="stat-card activity-stat">
            <div class="stat-icon">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($stats['today_activities'] ?? 0); ?></h3>
                <p>Attività Oggi</p>
                <small>
                    <?php echo number_format($stats['total_activities'] ?? 0); ?> totali
                </small>
            </div>
            <div class="stat-actions">
                <a href="<?php echo admin_url('admin.php?page=naval-egt&tab=logs'); ?>" class="button button-small">
                    Visualizza
                </a>
            </div>
        </div>

        <div class="stat-card registrations-stat">
            <div class="stat-icon">
                <span class="dashicons dashicons-plus"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($stats['pending_users'] ?? 0); ?></h3>
                <p>In Attesa di Attivazione</p>
                <small>
                    Nuove richieste da approvare
                </small>
            </div>
            <div class="stat-actions">
                <a href="<?php echo admin_url('admin.php?page=naval-egt&tab=users&status=SOSPESO'); ?>" 
                   class="button button-primary button-small">
                    Approva
                </a>
            </div>
        </div>
    </div>

    <!-- Dropbox e Azioni Rapide -->
    <div class="naval-egt-dashboard-sections">
        <!-- Sezione Dropbox -->
        <div class="dashboard-section dropbox-section">
            <h2><span class="dashicons dashicons-cloud"></span> Dropbox</h2>
            
            <div class="dropbox-status-dashboard">
                <?php if ($dropbox_status['connected']): ?>
                    <div class="dropbox-connected">
                        <div class="connection-info">
                            <span class="status-indicator connected"></span>
                            <div>
                                <strong>Connesso e funzionante</strong>
                                <?php if (!empty($dropbox_status['account_name'])): ?>
                                    <br><small>Account: <?php echo esc_html($dropbox_status['account_name']); ?></small>
                                <?php elseif (!empty($dropbox_status['account_email'])): ?>
                                    <br><small>Account: <?php echo esc_html($dropbox_status['account_email']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="dropbox-actions">
                            <button type="button" class="button test-dropbox-btn" onclick="testDropboxConnection()">
                                <span class="dashicons dashicons-yes"></span> Test Connessione
                            </button>
                            <button type="button" class="button sync-folders-btn" onclick="syncAllFolders()">
                                <span class="dashicons dashicons-update"></span> Sincronizza Cartelle
                            </button>
                        </div>
                    </div>
                <?php elseif (isset($dropbox_status['auth_url']) && $dropbox_status['auth_url']): ?>
                    <div class="dropbox-pending">
                        <div class="connection-info">
                            <span class="status-indicator pending"></span>
                            <div>
                                <strong>Configurazione in corso</strong>
                                <br><small>Le credenziali sono pronte, serve l'autorizzazione</small>
                            </div>
                        </div>
                        
                        <div class="dropbox-actions">
                            <a href="<?php echo esc_url($dropbox_status['auth_url']); ?>" 
                               class="button button-primary">
                                <span class="dashicons dashicons-lock"></span> Autorizza Dropbox
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="dropbox-disconnected">
                        <div class="connection-info">
                            <span class="status-indicator disconnected"></span>
                            <div>
                                <strong>Non configurato</strong>
                                <br><small><?php echo esc_html($dropbox_status['message']); ?></small>
                            </div>
                        </div>
                        
                        <div class="dropbox-actions">
                            <a href="<?php echo admin_url('admin.php?page=naval-egt&tab=dropbox'); ?>" 
                               class="button button-primary">
                                <span class="dashicons dashicons-admin-settings"></span> Configura Dropbox
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=naval-egt&tab=dropbox-debug'); ?>" 
                               class="button" style="background: #d63638; color: white;">
                                <span class="dashicons dashicons-search"></span> Debug
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="dropbox-info-box">
                <h4>💡 Come funziona:</h4>
                <ul>
                    <li><strong>Cartelle automatiche:</strong> Il sistema cerca cartelle nominate <code>100001_Nome_Cliente</code></li>
                    <li><strong>Sincronizzazione:</strong> I file vengono sincronizzati automaticamente con il database</li>
                    <li><strong>Accesso utenti:</strong> Ogni utente vede solo i suoi file della cartella associata</li>
                </ul>
            </div>
        </div>

        <!-- Azioni Rapide -->
        <div class="dashboard-section quick-actions-section">
            <h2><span class="dashicons dashicons-admin-generic"></span> Azioni Rapide</h2>
            
            <div class="quick-actions-grid">
                <div class="quick-action">
                    <a href="<?php echo admin_url('admin.php?page=naval-egt&tab=users'); ?>" 
                       class="quick-action-btn">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <span>Gestisci Utenti</span>
                    </a>
                </div>
                
                <div class="quick-action">
                    <a href="<?php echo admin_url('admin.php?page=naval-egt&tab=files'); ?>" 
                       class="quick-action-btn">
                        <span class="dashicons dashicons-upload"></span>
                        <span>Carica File</span>
                    </a>
                </div>
                
                <div class="quick-action">
                    <a href="<?php echo admin_url('admin.php?page=naval-egt&tab=dropbox'); ?>" 
                       class="quick-action-btn">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <span>Impostazioni</span>
                    </a>
                </div>
                
                <div class="quick-action">
                    <button type="button" class="quick-action-btn" onclick="exportUsers()">
                        <span class="dashicons dashicons-download"></span>
                        <span>Esporta Utenti</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Attività Recenti -->
    <div class="naval-egt-recent-activity">
        <h2><span class="dashicons dashicons-clock"></span> Attività Recenti</h2>
        
        <?php if (!empty($recent_activities)): ?>
            <div class="activity-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Data/Ora</th>
                            <th>Utente</th>
                            <th>Azione</th>
                            <th>File</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_activities as $activity): ?>
                            <tr>
                                <td><?php echo mysql2date('d/m/Y H:i', $activity['created_at']); ?></td>
                                <td>
                                    <strong><?php echo esc_html($activity['user_code']); ?></strong>
                                    <?php if (!empty($activity['user_name'])): ?>
                                        <br><small><?php echo esc_html($activity['user_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="activity-badge activity-<?php echo strtolower($activity['action']); ?>">
                                        <?php echo esc_html(format_action_name($activity['action'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($activity['file_name'])): ?>
                                        <code><?php echo esc_html($activity['file_name']); ?></code>
                                        <?php if (!empty($activity['file_size'])): ?>
                                            <br><small><?php echo size_format($activity['file_size']); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo esc_html($activity['ip_address'] ?? '—'); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="activity-footer">
                    <a href="<?php echo admin_url('admin.php?page=naval-egt&tab=logs'); ?>" class="button">
                        Visualizza tutti i log →
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="no-activity">
                <p>Nessuna attività recente registrata.</p>
                <a href="<?php echo admin_url('admin.php?page=naval-egt&tab=logs'); ?>" class="button">
                    Visualizza tutti i log
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Stili per la dashboard */
.naval-egt-system-status {
    margin: 20px 0;
}

.system-status-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}

.system-status-card h3 {
    margin-top: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 4px;
}

.status-ok { color: #46b450; font-weight: bold; }
.status-warning { color: #ffb900; font-weight: bold; }
.status-error { color: #dc3232; font-weight: bold; }

.naval-egt-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: box-shadow 0.2s;
}

.stat-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.stat-icon {
    font-size: 32px;
    opacity: 0.7;
}

.users-stat .stat-icon { color: #0073aa; }
.files-stat .stat-icon { color: #00a32a; }
.activity-stat .stat-icon { color: #ff6900; }
.registrations-stat .stat-icon { color: #9b51e0; }

.stat-content h3 {
    font-size: 28px;
    margin: 0;
    line-height: 1;
}

.stat-content p {
    margin: 5px 0;
    font-weight: 600;
    color: #333;
}

.stat-content small {
    color: #666;
}

.stat-actions {
    margin-left: auto;
}

.naval-egt-dashboard-sections {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 20px;
    margin: 20px 0;
}

@media (max-width: 1024px) {
    .naval-egt-dashboard-sections {
        grid-template-columns: 1fr;
    }
}

.dashboard-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
}

.dashboard-section h2 {
    margin-top: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.dropbox-status-dashboard {
    margin: 15px 0;
    padding: 15px;
    border-radius: 6px;
    border: 1px solid #ddd;
}

.connection-info {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
}

.status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
}

.status-indicator.connected { background: #46b450; }
.status-indicator.pending { background: #ffb900; }
.status-indicator.disconnected { background: #dc3232; }

.dropbox-connected {
    background: #f0f8f0;
    border-color: #46b450;
}

.dropbox-pending {
    background: #fff8e1;
    border-color: #ffb900;
}

.dropbox-disconnected {
    background: #fdf2f2;
    border-color: #dc3232;
}

.dropbox-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.dropbox-info-box {
    background: #f7f7f7;
    border-left: 4px solid #0073aa;
    padding: 15px;
    margin-top: 15px;
}

.dropbox-info-box h4 {
    margin-top: 0;
}

.dropbox-info-box ul {
    margin: 10px 0;
    padding-left: 20px;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 20px;
    background: #f7f7f7;
    border: 1px solid #ddd;
    border-radius: 6px;
    text-decoration: none;
    color: #333;
    transition: all 0.2s;
    cursor: pointer;
}

.quick-action-btn:hover {
    background: #e7e7e7;
    border-color: #999;
    color: #000;
    text-decoration: none;
}

.quick-action-btn .dashicons {
    font-size: 24px;
}

.naval-egt-recent-activity {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}

.naval-egt-recent-activity h2 {
    margin-top: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.activity-table-container {
    margin-top: 15px;
}

.activity-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
    white-space: nowrap;
}

.activity-login { background: #e7f3ff; color: #0073aa; }
.activity-logout { background: #f0f0f0; color: #666; }
.activity-upload { background: #e8f5e8; color: #00a32a; }
.activity-download { background: #fff3cd; color: #856404; }
.activity-delete { background: #f8d7da; color: #721c24; }
.activity-registration { background: #f4e7ff; color: #9b51e0; }
.activity-user_update { background: #cce5ff; color: #0066cc; }
.activity-status_change { background: #fff2e6; color: #cc6600; }
.activity-user_delete { background: #ffe6e6; color: #cc0000; }

.activity-footer {
    text-align: center;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.no-activity {
    text-align: center;
    padding: 40px;
    color: #666;
}

.text-muted {
    color: #999;
}
</style>

<script>
// Funzioni JavaScript per azioni rapide
function testDropboxConnection() {
    if (!window.jQuery) return;
    
    jQuery.post(ajaxurl, {
        action: 'naval_egt_ajax',
        naval_action: 'test_dropbox_connection',
        nonce: '<?php echo wp_create_nonce('naval_egt_nonce'); ?>'
    }, function(response) {
        if (response.success) {
            alert('✅ Test connessione riuscito!\n\n' + response.data.message);
        } else {
            alert('❌ Test connessione fallito!\n\n' + response.data);
        }
    }).fail(function() {
        alert('Errore nella richiesta di test');
    });
}

function syncAllFolders() {
    if (!window.jQuery) return;
    
    if (!confirm('Vuoi sincronizzare tutte le cartelle utenti con Dropbox?\n\nQuesta operazione potrebbe richiedere alcuni minuti.')) {
        return;
    }
    
    // Disabilita pulsante durante sincronizzazione
    var btn = document.querySelector('.sync-folders-btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Sincronizzazione...';
    }
    
    jQuery.post(ajaxurl, {
        action: 'naval_egt_ajax',
        naval_action: 'sync_all_user_folders',
        nonce: '<?php echo wp_create_nonce('naval_egt_nonce'); ?>'
    }, function(response) {
        if (response.success && response.data.stats) {
            var stats = response.data.stats;
            var message = '✅ Sincronizzazione completata!\n\n';
            message += 'Utenti processati: ' + stats.users_processed + '\n';
            message += 'Cartelle trovate: ' + stats.folders_found + '\n';
            message += 'File sincronizzati: ' + stats.files_synced;
            
            if (stats.errors && stats.errors.length > 0) {
                message += '\n\n⚠️ Alcuni errori:\n' + stats.errors.slice(0, 3).join('\n');
                if (stats.errors.length > 3) {
                    message += '\n... e altri ' + (stats.errors.length - 3) + ' errori';
                }
            }
            
            alert(message);
            location.reload();
        } else {
            alert('❌ Errore durante la sincronizzazione:\n\n' + (response.data || 'Errore sconosciuto'));
        }
    }).fail(function() {
        alert('Errore nella richiesta di sincronizzazione');
    }).always(function() {
        // Riabilita pulsante
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span class="dashicons dashicons-update"></span> Sincronizza Cartelle';
        }
    });
}

function exportUsers() {
    if (!window.jQuery) return;
    
    if (confirm('Vuoi esportare la lista utenti in formato Excel?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = ajaxurl;
        form.style.display = 'none';
        
        var fields = {
            'action': 'naval_egt_export',
            'export_type': 'users',
            'format': 'xlsx',
            'nonce': '<?php echo wp_create_nonce('naval_egt_nonce'); ?>'
        };
        
        for (var key in fields) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = fields[key];
            form.appendChild(input);
        }
        
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
}

// CSS per animazione spinning
var style = document.createElement('style');
style.textContent = `
    .spin {
        animation: naval-spin 1s linear infinite;
    }
    @keyframes naval-spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);
</script>