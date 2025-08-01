<?php
/**
 * Tab Panoramica - Dashboard Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$dropbox = Naval_EGT_Dropbox::get_instance();
$dropbox_configured = $dropbox->is_configured();
?>

<div class="overview-content">
    <div class="overview-grid">
        <!-- Attività Recenti -->
        <div class="overview-section">
            <h3>Attività Recenti</h3>
            <div class="activity-list">
                <?php if (!empty($recent_activities)): ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php echo strtolower($activity['action']); ?>">
                                <?php
                                switch ($activity['action']) {
                                    case 'UPLOAD':
                                    case 'ADMIN_UPLOAD':
                                        echo '↑';
                                        break;
                                    case 'DOWNLOAD':
                                        echo '↓';
                                        break;
                                    case 'REGISTRATION':
                                        echo '+';
                                        break;
                                    case 'LOGIN':
                                        echo '→';
                                        break;
                                    default:
                                        echo '•';
                                        break;
                                }
                                ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <?php
                                    switch ($activity['action']) {
                                        case 'UPLOAD':
                                            echo $activity['user_name'] ? $activity['user_name'] : 'Utente eliminato';
                                            echo ' ha caricato';
                                            break;
                                        case 'ADMIN_UPLOAD':
                                            echo 'Admin ha caricato';
                                            break;
                                        case 'DOWNLOAD':
                                            echo $activity['user_name'] ? $activity['user_name'] : 'Utente eliminato';
                                            echo ' ha scaricato';
                                            break;
                                        case 'REGISTRATION':
                                            echo $activity['user_name'] ? $activity['user_name'] : 'Utente eliminato';
                                            echo ' si è registrato';
                                            break;
                                        case 'LOGIN':
                                            echo $activity['user_name'] ? $activity['user_name'] : 'Utente eliminato';
                                            echo ' ha effettuato l\'accesso';
                                            break;
                                        default:
                                            echo $activity['action'];
                                            break;
                                    }
                                    ?>
                                    <?php if ($activity['file_name']): ?>
                                        <strong><?php echo esc_html($activity['file_name']); ?></strong>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-meta">
                                    <?php 
                                    echo date('d/m/Y H:i', strtotime($activity['created_at']));
                                    if ($activity['file_size'] > 0) {
                                        echo ' • ' . size_format($activity['file_size']);
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Nessuna attività recente.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Azioni Rapide -->
        <div class="overview-section">
            <h3>Azioni Rapide</h3>
            
            <div class="quick-actions">
                <a href="?page=naval-egt&tab=users" class="quick-action-btn primary">
                    <span class="dashicons dashicons-groups" style="margin-right: 8px;"></span>
                    Gestisci Utenti
                </a>
                
                <button type="button" class="quick-action-btn success" onclick="refreshStats()">
                    <span class="dashicons dashicons-update" style="margin-right: 8px;"></span>
                    Aggiorna Statistiche
                </button>
                
                <a href="?page=naval-egt&tab=users&action=export" class="quick-action-btn warning">
                    <span class="dashicons dashicons-download" style="margin-right: 8px;"></span>
                    Esporta Utenti
                </a>
            </div>

            <!-- Configurazione Dropbox -->
            <div class="dropbox-config">
                <h3>
                    <span class="dashicons dashicons-cloud" style="margin-right: 8px;"></span>
                    Impostazioni Dropbox
                </h3>
                
                <?php if ($dropbox_configured): ?>
                    <div class="notice notice-success inline">
                        <p><strong>Dropbox configurato correttamente!</strong></p>
                        <p>Il plugin è connesso al tuo account Dropbox e può gestire i file degli utenti.</p>
                    </div>
                    
                    <button type="button" class="button" onclick="testDropboxConnection()">
                        Test Connessione
                    </button>
                    
                    <button type="button" class="button" onclick="syncAllFolders()">
                        Sincronizza Tutte le Cartelle
                    </button>
                    
                <?php else: ?>
                    <div class="notice notice-warning inline">
                        <p><strong>Dropbox non configurato</strong></p>
                        <p>Per utilizzare tutte le funzionalità del plugin, è necessario configurare l'integrazione con Dropbox.</p>
                    </div>
                    
                    <form method="post" id="dropbox-config-form">
                        <?php wp_nonce_field('naval_egt_admin_nonce', 'nonce'); ?>
                        <input type="hidden" name="action" value="dropbox_auth">
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="app_key">App Key</label></th>
                                <td>
                                    <input type="text" id="app_key" name="app_key" class="regular-text" required>
                                    <p class="description">Inserisci l'App Key dell'applicazione Dropbox</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="app_secret">App Secret</label></th>
                                <td>
                                    <input type="password" id="app_secret" name="app_secret" class="regular-text" required>
                                    <p class="description">Inserisci l'App Secret dell'applicazione Dropbox</p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" class="button-primary" value="Configura Dropbox">
                        </p>
                    </form>
                    
                    <div class="dropbox-help">
                        <h4>Come configurare Dropbox:</h4>
                        <ol>
                            <li>Vai su <a href="https://www.dropbox.com/developers/apps" target="_blank">Dropbox App Console</a></li>
                            <li>Clicca su "Create app"</li>
                            <li>Seleziona "Scoped access"</li>
                            <li>Scegli "Full Dropbox"</li>
                            <li>Assegna un nome alla tua app</li>
                            <li>Una volta creata, copia App Key e App Secret qui sopra</li>
                            <li>Aggiungi questo URL di redirect nelle impostazioni della tua app Dropbox:<br>
                                <code><?php echo admin_url('admin.php?page=naval-egt&dropbox_callback=1'); ?></code>
                            </li>
                        </ol>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function refreshStats() {
    if (confirm('Vuoi aggiornare le statistiche?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="refresh_stats">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('naval_egt_admin_nonce'); ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function testDropboxConnection() {
    jQuery.post(ajaxurl, {
        action: 'naval_egt_ajax',
        naval_action: 'test_dropbox',
        nonce: '<?php echo wp_create_nonce("naval_egt_nonce"); ?>'
    }, function(response) {
        if (response.success) {
            alert('Connessione Dropbox OK!');
        } else {
            alert('Errore connessione: ' + response.data);
        }
    });
}

function syncAllFolders() {
    if (confirm('Vuoi sincronizzare tutte le cartelle utenti con Dropbox? Questa operazione potrebbe richiedere del tempo.')) {
        jQuery.post(ajaxurl, {
            action: 'naval_egt_ajax',
            naval_action: 'sync_all_folders',
            nonce: '<?php echo wp_create_nonce("naval_egt_nonce"); ?>'
        }, function(response) {
            if (response.success) {
                alert('Sincronizzazione completata!');
                location.reload();
            } else {
                alert('Errore durante la sincronizzazione: ' + response.data);
            }
        });
    }
}
</script>

<style>
.overview-content {
    max-width: 1200px;
}

.overview-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

@media (max-width: 968px) {
    .overview-grid {
        grid-template-columns: 1fr;
    }
}

.overview-section {
    background: #f8f9fa;
    border: 1px solid #dadce0;
    border-radius: 8px;
    padding: 20px;
}

.overview-section h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
    border-bottom: 2px solid #4285f4;
    padding-bottom: 10px;
}

.activity-list {
    max-height: 400px;
    overflow-y: auto;
}

.quick-actions {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
    margin-bottom: 20px;
}

.dropbox-help {
    background: #fff;
    border: 1px solid #dadce0;
    border-radius: 4px;
    padding: 15px;
    margin-top: 20px;
}

.dropbox-help h4 {
    margin-top: 0;
    color: #4285f4;
}

.dropbox-help ol {
    padding-left: 20px;
}

.dropbox-help code {
    background: #f1f3f4;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
    word-break: break-all;
}

.notice.inline {
    display: block;
    margin: 15px 0;
    padding: 12px;
}
</style>