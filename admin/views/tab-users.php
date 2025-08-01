<?php
/**
 * Tab Gestione Utenti - Dashboard Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verifica permessi
if (!current_user_can('manage_options')) {
    wp_die(__('Non hai i permessi per accedere a questa pagina.'));
}

// Parametri per filtri e paginazione
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;

// Costruisci filtri
$filters = array();
if (!empty($search)) {
    $filters['search'] = $search;
}
if (!empty($status_filter)) {
    $filters['status'] = $status_filter;
}

// Ottieni utenti
$offset = ($paged - 1) * $per_page;
$users = Naval_EGT_User_Manager::get_users($filters, $per_page, $offset);
$total_users = Naval_EGT_User_Manager::count_users($filters);
$total_pages = ceil($total_users / $per_page);
?>

<div class="users-management">
    
    <!-- Header sezione -->
    <div class="users-header">
        <h2>Gestione Utenti</h2>
        <div class="users-actions">
            <button type="button" id="add-user-btn" class="btn-primary">
                <span class="dashicons dashicons-plus"></span>
                Aggiungi Utente
            </button>
            <button type="button" id="refresh-users" class="btn-secondary">
                <span class="dashicons dashicons-update"></span>
                Aggiorna
            </button>
        </div>
    </div>

    <!-- Filtri e ricerca -->
    <div class="users-filters">
        <form method="get" class="filters-form">
            <input type="hidden" name="page" value="naval-egt">
            <input type="hidden" name="tab" value="users">
            
            <div class="filter-group">
                <label for="user-search">Cerca Utenti:</label>
                <input type="text" id="user-search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Nome, cognome, email o azienda">
            </div>
            
            <div class="filter-group">
                <label for="status-filter">Status:</label>
                <select id="status-filter" name="status">
                    <option value="">Tutti gli status</option>
                    <option value="ATTIVO" <?php selected($status_filter, 'ATTIVO'); ?>>Solo Attivi</option>
                    <option value="SOSPESO" <?php selected($status_filter, 'SOSPESO'); ?>>Solo Sospesi</option>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn-primary">Filtra</button>
                <?php if (!empty($search) || !empty($status_filter)): ?>
                    <a href="?page=naval-egt&tab=users" class="btn-secondary">Pulisci Filtri</a>
                <?php endif; ?>
            </div>
        </form>
        
        <!-- Export Actions -->
        <div class="export-actions">
            <button type="button" class="export-btn btn-outline" data-export-type="users" data-format="xlsx">
                <span class="dashicons dashicons-media-spreadsheet"></span>
                Esporta Excel
            </button>
            <button type="button" class="export-btn btn-outline" data-export-type="users" data-format="csv">
                <span class="dashicons dashicons-media-text"></span>
                Esporta CSV
            </button>
            <button type="button" class="export-btn btn-outline" data-export-type="users" data-format="pdf">
                <span class="dashicons dashicons-media-document"></span>
                Esporta PDF
            </button>
        </div>
    </div>

    <!-- Risultati e bulk actions -->
    <div class="users-results">
        <div class="results-info">
            <span class="results-count">
                <?php 
                if ($total_users > 0) {
                    printf('Visualizzazione %d-%d di %d utenti', 
                        $offset + 1, 
                        min($offset + $per_page, $total_users), 
                        $total_users
                    );
                } else {
                    echo 'Nessun utente trovato';
                }
                ?>
            </span>
        </div>
        
        <?php if (!empty($users)): ?>
        <div class="bulk-actions-top">
            <select id="bulk-action-selector-top" name="action">
                <option value="-1">Azioni di gruppo</option>
                <option value="activate">Attiva</option>
                <option value="suspend">Sospendi</option>
                <option value="delete">Elimina</option>
            </select>
            <button type="button" id="doaction" class="btn-secondary">Applica</button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tabella utenti -->
    <div class="users-table-container">
        <?php if (!empty($users)): ?>
        <table class="wp-list-table widefat fixed striped users">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <input id="cb-select-all-1" type="checkbox">
                    </td>
                    <th scope="col" class="manage-column column-user-code">
                        <span>Codice</span>
                    </th>
                    <th scope="col" class="manage-column column-name">
                        <span>Nome Completo</span>
                    </th>
                    <th scope="col" class="manage-column column-email">
                        <span>Email</span>
                    </th>
                    <th scope="col" class="manage-column column-company">
                        <span>Azienda</span>
                    </th>
                    <th scope="col" class="manage-column column-status">
                        <span>Status</span>
                    </th>
                    <th scope="col" class="manage-column column-date">
                        <span>Registrato</span>
                    </th>
                    <th scope="col" class="manage-column column-actions">
                        <span>Azioni</span>
                    </th>
                </tr>
            </thead>
            
            <tbody id="users-table-body">
                <?php foreach ($users as $user): ?>
                <?php
                $status_class = $user['status'] === 'ATTIVO' ? 'status-active' : 'status-suspended';
                $last_login = $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Mai';
                $created_at = date('d/m/Y H:i', strtotime($user['created_at']));
                $full_name = $user['nome'] . ' ' . $user['cognome'];
                ?>
                <tr data-user-id="<?php echo $user['id']; ?>">
                    <th scope="row" class="check-column">
                        <input type="checkbox" name="users[]" value="<?php echo $user['id']; ?>">
                    </th>
                    
                    <td class="user-code column-user-code">
                        <strong><?php echo esc_html($user['user_code']); ?></strong>
                    </td>
                    
                    <td class="name column-name">
                        <strong>
                            <a href="#" class="user-name-link" data-user-id="<?php echo $user['id']; ?>">
                                <?php echo esc_html($full_name); ?>
                            </a>
                        </strong>
                        <div class="row-actions">
                            <span class="edit">
                                <a href="#" class="btn-edit-user" data-user-id="<?php echo $user['id']; ?>">Modifica</a> |
                            </span>
                            <span class="status">
                                <a href="#" class="btn-toggle-status" data-user-id="<?php echo $user['id']; ?>" data-current-status="<?php echo $user['status']; ?>">
                                    <?php echo $user['status'] === 'ATTIVO' ? 'Sospendi' : 'Attiva'; ?>
                                </a> |
                            </span>
                            <span class="files">
                                <a href="#" class="btn-manage-files" data-user-id="<?php echo $user['id']; ?>">File</a> |
                            </span>
                            <span class="delete">
                                <a href="#" class="btn-delete-user text-danger" data-user-id="<?php echo $user['id']; ?>" data-user-name="<?php echo esc_attr($full_name); ?>">Elimina</a>
                            </span>
                        </div>
                    </td>
                    
                    <td class="email column-email">
                        <a href="mailto:<?php echo esc_attr($user['email']); ?>">
                            <?php echo esc_html($user['email']); ?>
                        </a>
                        <?php if (!empty($user['telefono'])): ?>
                            <br><small>📞 <?php echo esc_html($user['telefono']); ?></small>
                        <?php endif; ?>
                    </td>
                    
                    <td class="company column-company">
                        <?php if (!empty($user['ragione_sociale'])): ?>
                            <strong><?php echo esc_html($user['ragione_sociale']); ?></strong>
                            <?php if (!empty($user['partita_iva'])): ?>
                                <br><small>P.IVA: <?php echo esc_html($user['partita_iva']); ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">Privato</span>
                        <?php endif; ?>
                    </td>
                    
                    <td class="status column-status">
                        <span class="status-badge <?php echo $status_class; ?>">
                            <?php echo $user['status']; ?>
                        </span>
                        <?php if ($user['last_login']): ?>
                            <br><small title="Ultimo accesso">🕐 <?php echo $last_login; ?></small>
                        <?php endif; ?>
                    </td>
                    
                    <td class="date column-date">
                        <?php echo $created_at; ?>
                        <?php if (!empty($user['dropbox_folder'])): ?>
                            <br><small title="Cartella Dropbox collegata">📁 Collegata</small>
                        <?php else: ?>
                            <br><small class="text-warning" title="Cartella Dropbox non collegata">⚠️ Non collegata</small>
                        <?php endif; ?>
                    </td>
                    
                    <td class="actions column-actions">
                        <div class="action-buttons">
                            <button type="button" class="btn-icon btn-edit-user" data-user-id="<?php echo $user['id']; ?>" title="Modifica utente">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            
                            <button type="button" class="btn-icon btn-toggle-status" data-user-id="<?php echo $user['id']; ?>" data-current-status="<?php echo $user['status']; ?>" title="<?php echo $user['status'] === 'ATTIVO' ? 'Sospendi utente' : 'Attiva utente'; ?>">
                                <span class="dashicons dashicons-<?php echo $user['status'] === 'ATTIVO' ? 'pause' : 'controls-play'; ?>"></span>
                            </button>
                            
                            <button type="button" class="btn-icon btn-upload-file" data-user-id="<?php echo $user['id']; ?>" title="Carica file per questo utente">
                                <span class="dashicons dashicons-upload"></span>
                            </button>
                            
                            <button type="button" class="btn-icon btn-link-dropbox" data-user-id="<?php echo $user['id']; ?>" title="Collega cartella Dropbox">
                                <span class="dashicons dashicons-cloud"></span>
                            </button>
                            
                            <button type="button" class="btn-icon btn-view-logs" data-user-id="<?php echo $user['id']; ?>" title="Visualizza log attività">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                            
                            <button type="button" class="btn-icon btn-delete-user text-danger" data-user-id="<?php echo $user['id']; ?>" data-user-name="<?php echo esc_attr($full_name); ?>" title="Elimina utente">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            
            <tfoot>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input id="cb-select-all-2" type="checkbox">
                    </td>
                    <th scope="col" class="manage-column column-user-code">Codice</th>
                    <th scope="col" class="manage-column column-name">Nome Completo</th>
                    <th scope="col" class="manage-column column-email">Email</th>
                    <th scope="col" class="manage-column column-company">Azienda</th>
                    <th scope="col" class="manage-column column-status">Status</th>
                    <th scope="col" class="manage-column column-date">Registrato</th>
                    <th scope="col" class="manage-column column-actions">Azioni</th>
                </tr>
            </tfoot>
        </table>
        
        <?php else: ?>
        <div class="no-users-found">
            <div class="no-users-icon">👥</div>
            <h3>Nessun utente trovato</h3>
            <?php if (!empty($search) || !empty($status_filter)): ?>
                <p>Nessun utente corrisponde ai filtri applicati.</p>
                <a href="?page=naval-egt&tab=users" class="btn-secondary">Rimuovi Filtri</a>
            <?php else: ?>
                <p>Non ci sono ancora utenti registrati nel sistema.</p>
                <button type="button" id="add-first-user" class="btn-primary">Aggiungi Primo Utente</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Paginazione -->
    <?php if ($total_pages > 1): ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num"><?php printf('%d elementi', $total_users); ?></span>
            <span class="pagination-links">
                <?php
                $base_url = add_query_arg(array(
                    'page' => 'naval-egt',
                    'tab' => 'users',
                    's' => $search,
                    'status' => $status_filter
                ), admin_url('admin.php'));
                
                // Prima pagina
                if ($paged > 1): ?>
                    <a class="first-page button" href="<?php echo esc_url(add_query_arg('paged', 1, $base_url)); ?>">
                        <span aria-hidden="true">«</span>
                    </a>
                    <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', max(1, $paged - 1), $base_url)); ?>">
                        <span aria-hidden="true">‹</span>
                    </a>
                <?php else: ?>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
                <?php endif; ?>
                
                <span class="paging-input">
                    <span class="current-page"><?php echo $paged; ?></span>
                    <span class="tablenav-paging-text"> di </span>
                    <span class="total-pages"><?php echo $total_pages; ?></span>
                </span>
                
                <?php if ($paged < $total_pages): ?>
                    <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', min($total_pages, $paged + 1), $base_url)); ?>">
                        <span aria-hidden="true">›</span>
                    </a>
                    <a class="last-page button" href="<?php echo esc_url(add_query_arg('paged', $total_pages, $base_url)); ?>">
                        <span aria-hidden="true">»</span>
                    </a>
                <?php else: ?>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
                <?php endif; ?>
            </span>
        </div>
        
        <!-- Bulk actions bottom -->
        <?php if (!empty($users)): ?>
        <div class="alignleft actions bulkactions">
            <select id="bulk-action-selector-bottom" name="action2">
                <option value="-1">Azioni di gruppo</option>
                <option value="activate">Attiva</option>
                <option value="suspend">Sospendi</option>
                <option value="delete">Elimina</option>
            </select>
            <button type="button" id="doaction2" class="btn-secondary">Applica</button>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<!-- Template Modal Aggiungi/Modifica Utente -->
<script type="text/template" id="user-modal-template">
    <div class="user-modal-content">
        <form id="user-form" class="naval-form">
            <div class="form-section">
                <h4>Informazioni Personali</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="user-nome">Nome *</label>
                        <input type="text" id="user-nome" name="nome" required>
                    </div>
                    <div class="form-group">
                        <label for="user-cognome">Cognome *</label>
                        <input type="text" id="user-cognome" name="cognome" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="user-email">Email *</label>
                        <input type="email" id="user-email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="user-telefono">Telefono</label>
                        <input type="tel" id="user-telefono" name="telefono">
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h4>Credenziali Accesso</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="user-username">Username *</label>
                        <input type="text" id="user-username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="user-password">Password</label>
                        <input type="password" id="user-password" name="password">
                        <small class="form-help">Lascia vuoto per non modificare (solo in modifica)</small>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h4>Informazioni Aziendali</h4>
                <div class="form-group">
                    <label for="user-ragione-sociale">Ragione Sociale</label>
                    <input type="text" id="user-ragione-sociale" name="ragione_sociale">
                </div>
                <div class="form-group">
                    <label for="user-partita-iva">Partita IVA</label>
                    <input type="text" id="user-partita-iva" name="partita_iva">
                    <small class="form-help">Obbligatoria se specificata la Ragione Sociale</small>
                </div>
            </div>
            
            <div class="form-section">
                <h4>Impostazioni Account</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="user-status">Status</label>
                        <select id="user-status" name="status">
                            <option value="SOSPESO">Sospeso</option>
                            <option value="ATTIVO">Attivo</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="user-dropbox-folder">Cartella Dropbox</label>
                        <input type="text" id="user-dropbox-folder" name="dropbox_folder" placeholder="Lascia vuoto per auto-rilevamento">
                        <small class="form-help">Percorso completo cartella Dropbox</small>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <span class="dashicons dashicons-yes"></span>
                    <span class="button-text">Salva</span>
                </button>
                <button type="button" class="btn-secondary btn-cancel">
                    <span class="dashicons dashicons-no-alt"></span>
                    Annulla
                </button>
            </div>
            
            <input type="hidden" id="user-id" name="user_id" value="">
            <input type="hidden" id="user-code" name="user_code" value="">
        </form>
    </div>
</script>

<style>
/* Stili specifici per la tab utenti */
.users-management {
    max-width: 100%;
}

.users-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #ddd;
}

.users-header h2 {
    margin: 0;
    color: #333;
}

.users-actions {
    display: flex;
    gap: 10px;
}

.users-filters {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 20px;
}

.filters-form {
    display: flex;
    gap: 20px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-weight: 600;
    color: #333;
}

.filter-group input,
.filter-group select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    min-width: 150px;
}

.filter-actions {
    display: flex;
    gap: 10px;
}

.export-actions {
    display: flex;
    gap: 10px;
}

.users-results {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.results-count {
    color: #666;
    font-size: 14px;
}

.bulk-actions-top {
    display: flex;
    gap: 10px;
    align-items: center;
}

.users-table-container {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-suspended {
    background: #f8d7da;
    color: #721c24;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

.btn-icon {
    padding: 6px;
    border: 1px solid #ddd;
    background: #f9f9f9;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-icon:hover {
    background: #e9ecef;
    border-color: #adb5bd;
}

.btn-icon.text-danger:hover {
    background: #f5c6cb;
    border-color: #f1b0b7;
    color: #721c24;
}

.user-name-link {
    text-decoration: none;
    color: #0073aa;
    font-weight: 600;
}

.user-name-link:hover {
    color: #005177;
}

.row-actions {
    color: #666;
    font-size: 13px;
}

.row-actions a {
    color: #0073aa;
    text-decoration: none;
}

.row-actions a:hover {
    color: #005177;
}

.row-actions .text-danger {
    color: #dc3545;
}

.row-actions .text-danger:hover {
    color: #c82333;
}

.no-users-found {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.no-users-icon {
    font-size: 48px;
    margin-bottom: 20px;
}

.no-users-found h3 {
    margin: 0 0 15px 0;
    color: #333;
}

.text-muted {
    color: #6c757d;
}

.text-warning {
    color: #856404;
}

.form-section {
    margin-bottom: 25px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 6px;
}

.form-section h4 {
    margin: 0 0 15px 0;
    color: #4285f4;
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 8px;
}

.form-help {
    color: #6c757d;
    font-size: 12px;
    margin-top: 4px;
}

.form-actions {
    text-align: right;
    padding: 20px 0;
    border-top: 1px solid #e9ecef;
}

.form-actions .btn-primary,
.form-actions .btn-secondary {
    margin-left: 10px;
}

/* Responsive */
@media (max-width: 768px) {
    .users-header {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }
    
    .users-filters {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filters-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group input,
    .filter-group select {
        min-width: 100%;
    }
    
    .users-results {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }
    
    .action-buttons {
        flex-wrap: wrap;
    }
    
    .form-row {
        flex-direction: column;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // Gestione click aggiungi utente
    $('#add-user-btn, #add-first-user').on('click', function() {
        openAddUserModal();
    });
    
    // Gestione click modifica utente
    $(document).on('click', '.btn-edit-user, .user-name-link', function(e) {
        e.preventDefault();
        const userId = $(this).data('user-id');
        openEditUserModal(userId);
    });
    
    // Gestione toggle status
    $(document).on('click', '.btn-toggle-status', function(e) {
        e.preventDefault();
        const userId = $(this).data('user-id');
        const currentStatus = $(this).data('current-status');
        toggleUserStatus(userId, currentStatus);
    });
    
    // Gestione eliminazione utente
    $(document).on('click', '.btn-delete-user', function(e) {
        e.preventDefault();
        const userId = $(this).data('user-id');
        const userName = $(this).data('user-name');
        deleteUser(userId, userName);
    });
    
    // Gestione upload file
    $(document).on('click', '.btn-upload-file', function(e) {
        e.preventDefault();
        const userId = $(this).data('user-id');
        openFileUploadModal(userId);
    });
    
    // Gestione link Dropbox
    $(document).on('click', '.btn-link-dropbox', function(e) {
        e.preventDefault();
        const userId = $(this).data('user-id');
        linkDropboxFolder(userId);
    });
    
    // Gestione visualizza log
    $(document).on('click', '.btn-view-logs', function(e) {
        e.preventDefault();
        const userId = $(this).data('user-id');
        viewUserLogs(userId);
    });
    
    // Gestione refresh
    $('#refresh-users').on('click', function() {
        location.reload();
    });
    
    // Auto-submit filtri con delay
    let searchTimeout;
    $('#user-search').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            $('#user-search').closest('form').submit();
        }, 500);
    });
    
    $('#status-filter').on('change', function() {
        $(this).closest('form').submit();
    });
    
    // Funzioni modali
    function openAddUserModal() {
        const template = $('#user-modal-template').html();
        showModal(template, 'Aggiungi Nuovo Utente');
        
        // Reset form per nuovo utente
        $('#user-form')[0].reset();
        $('#user-id').val('');
        $('#user-code').val('');
        $('#user-password').prop('required', true);
        $('#user-password').siblings('small').hide();
    }
    
    function openEditUserModal(userId) {
        // Carica dati utente via AJAX
        $.post(ajaxurl, {
            action: 'naval_egt_ajax',
            naval_action: 'get_user_data',
            nonce: naval_egt_ajax.nonce,
            user_id: userId
        }, function(response) {
            if (response.success) {
                const template = $('#user-modal-template').html();
                showModal(template, 'Modifica Utente: ' + response.data.user.nome + ' ' + response.data.user.cognome);
                
                // Popola form
                const user = response.data.user;
                $('#user-id').val(user.id);
                $('#user-code').val(user.user_code);
                $('#user-nome').val(user.nome);
                $('#user-cognome').val(user.cognome);
                $('#user-email').val(user.email);
                $('#user-telefono').val(user.telefono || '');
                $('#user-username').val(user.username);
                $('#user-ragione-sociale').val(user.ragione_sociale || '');
                $('#user-partita-iva').val(user.partita_iva || '');
                $('#user-status').val(user.status);
                $('#user-dropbox-folder').val(user.dropbox_folder || '');
                
                // Password non obbligatoria in modifica
                $('#user-password').prop('required', false);
                $('#user-password').siblings('small').show();
            }
        });
    }
    
    function showModal(content, title) {
        const modal = $(`
            <div class="modal-overlay">
                <div class="modal-container large">
                    <div class="modal-header">
                        <h3>${title}</h3>
                        <button type="button" class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        ${content}
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        modal.fadeIn(200);
        
        // Gestione submit form
        $('#user-form').on('submit', function(e) {
            e.preventDefault();
            saveUser();
        });
        
        // Gestione chiusura
        $('.modal-close, .btn-cancel').on('click', function() {
            modal.fadeOut(200, function() {
                $(this).remove();
            });
        });
        
        // Validazione Partita IVA
        $('#user-ragione-sociale').on('input', function() {
            const pivaField = $('#user-partita-iva');
            if ($(this).val().trim()) {
                pivaField.prop('required', true);
                pivaField.siblings('small').html('<strong>Obbligatoria se specificata la Ragione Sociale</strong>');
            } else {
                pivaField.prop('required', false);
                pivaField.siblings('small').html('Obbligatoria se specificata la Ragione Sociale');
            }
        });
    }
    
    function saveUser() {
        const formData = new FormData($('#user-form')[0]);
        formData.append('action', 'naval_egt_ajax');
        formData.append('nonce', naval_egt_ajax.nonce);
        
        const userId = $('#user-id').val();
        if (userId) {
            formData.append('naval_action', 'update_user');
        } else {
            formData.append('naval_action', 'create_user');
        }
        
        // Disabilita form
        $('#user-form input, #user-form select, #user-form button').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('.modal-overlay').fadeOut(200, function() {
                        $(this).remove();
                    });
                    
                    // Ricarica tabella
                    location.reload();
                } else {
                    alert('Errore: ' + response.data);
                    $('#user-form input, #user-form select, #user-form button').prop('disabled', false);
                }
            }
        });
    }
    
    function toggleUserStatus(userId, currentStatus) {
        const newStatus = currentStatus === 'ATTIVO' ? 'SOSPESO' : 'ATTIVO';
        const action = newStatus === 'ATTIVO' ? 'attivare' : 'sospendere';
        
        if (confirm(`Sei sicuro di voler ${action} questo utente?`)) {
            $.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'toggle_user_status',
                nonce: naval_egt_ajax.nonce,
                user_id: userId,
                status: newStatus
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Errore: ' + response.data);
                }
            });
        }
    }
    
    function deleteUser(userId, userName) {
        if (confirm(`ATTENZIONE: Sei sicuro di voler eliminare l'utente "${userName}"?\n\nQuesta azione eliminerà anche tutti i suoi file e non può essere annullata.`)) {
            $.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'delete_user',
                nonce: naval_egt_ajax.nonce,
                user_id: userId
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Errore: ' + response.data);
                }
            });
        }
    }
    
    function openFileUploadModal(userId) {
        // Implementa modal upload file
        alert('Funzionalità upload file - Da implementare');
    }
    
    function linkDropboxFolder(userId) {
        // Implementa collegamento cartella Dropbox
        alert('Funzionalità collegamento Dropbox - Da implementare');
    }
    
    function viewUserLogs(userId) {
        // Redirect alla tab log con filtro utente
        window.location.href = `?page=naval-egt&tab=logs&user_id=${userId}`;
    }
});
</script>