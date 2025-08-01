<?php
/**
 * Tab Log Attività - Dashboard Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verifica permessi
if (!current_user_can('manage_options')) {
    wp_die(__('Non hai i permessi per accedere a questa pagina.'));
}

// Parametri filtri
$user_filter = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$action_filter = isset($_GET['action_filter']) ? sanitize_text_field($_GET['action_filter']) : '';
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
$search_file = isset($_GET['search_file']) ? sanitize_text_field($_GET['search_file']) : '';
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 50;

// Costruisci filtri per query
$filters = array();
if ($user_filter) {
    $filters['user_id'] = $user_filter;
}
if ($action_filter) {
    $filters['action'] = $action_filter;
}
if ($date_from) {
    $filters['date_from'] = $date_from;
}
if ($date_to) {
    $filters['date_to'] = $date_to;
}
if ($search_file) {
    $filters['file_name'] = $search_file;
}

// Ottieni log
$offset = ($paged - 1) * $per_page;
$logs = Naval_EGT_Activity_Logger::get_logs($filters, $per_page, $offset);
$total_logs = Naval_EGT_Activity_Logger::count_logs($filters);
$total_pages = ceil($total_logs / $per_page);

// Ottieni utenti per dropdown
$users = Naval_EGT_User_Manager::get_users(array(), 1000, 0);

// Statistiche log
$log_stats = Naval_EGT_Activity_Logger::get_log_stats(30);

// Azioni disponibili
$available_actions = array(
    'LOGIN' => 'Login',
    'LOGOUT' => 'Logout',
    'UPLOAD' => 'Upload File',
    'DOWNLOAD' => 'Download File',
    'REGISTRATION' => 'Registrazione',
    'ADMIN_UPLOAD' => 'Upload Admin',
    'ADMIN_ACTION' => 'Azione Admin'
);
?>

<div class="logs-management">
    
    <!-- Header sezione -->
    <div class="logs-header">
        <h2>Log Attività Sistema</h2>
        <div class="logs-actions">
            <button type="button" id="export-logs" class="btn-secondary">
                <span class="dashicons dashicons-download"></span>
                Esporta Log
            </button>
            <button type="button" id="clear-logs" class="btn-danger">
                <span class="dashicons dashicons-trash"></span>
                Svuota Log
            </button>
            <button type="button" id="refresh-logs" class="btn-secondary">
                <span class="dashicons dashicons-update"></span>
                Aggiorna
            </button>
        </div>
    </div>

    <!-- Statistiche Log -->
    <div class="log-statistics">
        <div class="stat-group">
            <h4>Attività Ultimi 30 Giorni</h4>
            <div class="stats-grid">
                <?php foreach ($log_stats['by_action'] as $action_stat): ?>
                <div class="stat-item">
                    <div class="stat-icon action-<?php echo strtolower($action_stat['action']); ?>">
                        <?php
                        $icons = array(
                            'LOGIN' => '🔑',
                            'LOGOUT' => '🚪',
                            'UPLOAD' => '⬆️',
                            'DOWNLOAD' => '⬇️',
                            'REGISTRATION' => '📝',
                            'ADMIN_UPLOAD' => '📤',
                            'ADMIN_ACTION' => '⚙️'
                        );
                        echo $icons[$action_stat['action']] ?? '•';
                        ?>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($action_stat['count']); ?></div>
                        <div class="stat-label"><?php echo $available_actions[$action_stat['action']] ?? $action_stat['action']; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php if (!empty($log_stats['daily_activity'])): ?>
        <div class="stat-group">
            <h4>Attività Giornaliera (Ultimi 7 Giorni)</h4>
            <div class="daily-chart">
                <?php 
                $max_activity = max(array_column($log_stats['daily_activity'], 'count'));
                foreach ($log_stats['daily_activity'] as $day): 
                    $percentage = $max_activity > 0 ? ($day['count'] / $max_activity) * 100 : 0;
                ?>
                <div class="daily-bar">
                    <div class="bar-fill" style="height: <?php echo $percentage; ?>%" title="<?php echo $day['count']; ?> attività"></div>
                    <div class="bar-label"><?php echo date('d/m', strtotime($day['date'])); ?></div>
                    <div class="bar-count"><?php echo $day['count']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($log_stats['active_users'])): ?>
        <div class="stat-group">
            <h4>Utenti Più Attivi</h4>
            <div class="active-users-list">
                <?php foreach (array_slice($log_stats['active_users'], 0, 5) as $active_user): ?>
                <div class="active-user-item">
                    <div class="user-info">
                        <strong><?php echo esc_html($active_user['user_name'] ?: 'Utente eliminato'); ?></strong>
                        <small><?php echo esc_html($active_user['user_code']); ?></small>
                    </div>
                    <div class="activity-count">
                        <span class="count"><?php echo $active_user['activity_count']; ?></span>
                        <small>attività</small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Filtri Avanzati -->
    <div class="logs-filters">
        <form method="get" class="filters-form">
            <input type="hidden" name="page" value="naval-egt">
            <input type="hidden" name="tab" value="logs">
            
            <div class="filters-row">
                <div class="filter-group">
                    <label for="user-filter">Utente:</label>
                    <select id="user-filter" name="user_id">
                        <option value="">Tutti gli utenti</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php selected($user_filter, $user['id']); ?>>
                                <?php echo esc_html($user['user_code'] . ' - ' . $user['nome'] . ' ' . $user['cognome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="action-filter">Azione:</label>
                    <select id="action-filter" name="action_filter">
                        <option value="">Tutte le azioni</option>
                        <?php foreach ($available_actions as $action_key => $action_label): ?>
                            <option value="<?php echo $action_key; ?>" <?php selected($action_filter, $action_key); ?>>
                                <?php echo $action_label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="search-file">Nome File:</label>
                    <input type="text" id="search-file" name="search_file" value="<?php echo esc_attr($search_file); ?>" placeholder="Cerca per nome file">
                </div>
            </div>
            
            <div class="filters-row">
                <div class="filter-group">
                    <label for="date-from">Da Data:</label>
                    <input type="date" id="date-from" name="date_from" value="<?php echo esc_attr($date_from); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="date-to">A Data:</label>
                    <input type="date" id="date-to" name="date_to" value="<?php echo esc_attr($date_to); ?>">
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn-primary">
                        <span class="dashicons dashicons-search"></span>
                        Filtra
                    </button>
                    <?php if ($user_filter || $action_filter || $date_from || $date_to || $search_file): ?>
                        <a href="?page=naval-egt&tab=logs" class="btn-secondary">
                            <span class="dashicons dashicons-dismiss"></span>
                            Pulisci
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
        
        <!-- Quick Filters -->
        <div class="quick-filters">
            <h5>Filtri Rapidi:</h5>
            <div class="quick-filter-buttons">
                <a href="?page=naval-egt&tab=logs&date_from=<?php echo date('Y-m-d'); ?>" class="quick-filter <?php echo ($date_from === date('Y-m-d') && !$date_to) ? 'active' : ''; ?>">
                    Oggi
                </a>
                <a href="?page=naval-egt&tab=logs&date_from=<?php echo date('Y-m-d', strtotime('-7 days')); ?>" class="quick-filter <?php echo ($date_from === date('Y-m-d', strtotime('-7 days')) && !$date_to) ? 'active' : ''; ?>">
                    Ultimi 7 giorni
                </a>
                <a href="?page=naval-egt&tab=logs&date_from=<?php echo date('Y-m-d', strtotime('-30 days')); ?>" class="quick-filter <?php echo ($date_from === date('Y-m-d', strtotime('-30 days')) && !$date_to) ? 'active' : ''; ?>">
                    Ultimi 30 giorni
                </a>
                <a href="?page=naval-egt&tab=logs&action_filter=LOGIN" class="quick-filter <?php echo ($action_filter === 'LOGIN') ? 'active' : ''; ?>">
                    Solo Login
                </a>
                <a href="?page=naval-egt&tab=logs&action_filter=UPLOAD" class="quick-filter <?php echo ($action_filter === 'UPLOAD') ? 'active' : ''; ?>">
                    Solo Upload
                </a>
                <a href="?page=naval-egt&tab=logs&action_filter=DOWNLOAD" class="quick-filter <?php echo ($action_filter === 'DOWNLOAD') ? 'active' : ''; ?>">
                    Solo Download
                </a>
            </div>
        </div>
    </div>

    <!-- Risultati -->
    <div class="logs-results">
        <div class="results-info">
            <span class="results-count">
                <?php 
                if ($total_logs > 0) {
                    printf('Visualizzazione %d-%d di %d log', 
                        $offset + 1, 
                        min($offset + $per_page, $total_logs), 
                        number_format($total_logs)
                    );
                } else {
                    echo 'Nessun log trovato';
                }
                ?>
            </span>
            
            <?php if ($total_logs > 0): ?>
            <div class="results-actions">
                <button type="button" class="btn-outline export-filtered" data-format="csv">
                    <span class="dashicons dashicons-media-spreadsheet"></span>
                    Esporta Filtrati (CSV)
                </button>
                <button type="button" class="btn-outline export-filtered" data-format="html">
                    <span class="dashicons dashicons-media-document"></span>
                    Esporta Filtrati (HTML)
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabella Log -->
    <div class="logs-table-container">
        <?php if (!empty($logs)): ?>
        <table class="wp-list-table widefat fixed striped logs">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-datetime">
                        <span>Data/Ora</span>
                    </th>
                    <th scope="col" class="manage-column column-user">
                        <span>Utente</span>
                    </th>
                    <th scope="col" class="manage-column column-action">
                        <span>Azione</span>
                    </th>
                    <th scope="col" class="manage-column column-file">
                        <span>File</span>
                    </th>
                    <th scope="col" class="manage-column column-size">
                        <span>Dimensione</span>
                    </th>
                    <th scope="col" class="manage-column column-ip">
                        <span>IP</span>
                    </th>
                    <th scope="col" class="manage-column column-details">
                        <span>Dettagli</span>
                    </th>
                </tr>
            </thead>
            
            <tbody id="logs-table-body">
                <?php foreach ($logs as $log): ?>
                <?php
                $created_at = date('d/m/Y H:i:s', strtotime($log['created_at']));
                $user_name = $log['user_name'] ?: 'Sistema';
                $action_class = 'action-' . strtolower($log['action']);
                $file_size = $log['file_size'] > 0 ? size_format($log['file_size']) : '';
                $details = $log['details'] ? json_decode($log['details'], true) : null;
                ?>
                <tr data-log-id="<?php echo $log['id']; ?>" class="log-row <?php echo $action_class; ?>">
                    <td class="datetime column-datetime">
                        <strong><?php echo $created_at; ?></strong>
                        <div class="row-meta">
                            <small title="Timestamp"><?php echo $log['created_at']; ?></small>
                        </div>
                    </td>
                    
                    <td class="user column-user">
                        <div class="user-info">
                            <strong><?php echo esc_html($user_name); ?></strong>
                            <?php if ($log['user_code']): ?>
                                <div class="user-code">
                                    <small>Codice: <?php echo esc_html($log['user_code']); ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    
                    <td class="action column-action">
                        <span class="action-badge <?php echo $action_class; ?>">
                            <span class="action-icon">
                                <?php
                                $icons = array(
                                    'LOGIN' => '🔑',
                                    'LOGOUT' => '🚪',
                                    'UPLOAD' => '⬆️',
                                    'DOWNLOAD' => '⬇️',
                                    'REGISTRATION' => '📝',
                                    'ADMIN_UPLOAD' => '📤',
                                    'ADMIN_ACTION' => '⚙️'
                                );
                                echo $icons[$log['action']] ?? '•';
                                ?>
                            </span>
                            <?php echo $available_actions[$log['action']] ?? $log['action']; ?>
                        </span>
                    </td>
                    
                    <td class="file column-file">
                        <?php if ($log['file_name']): ?>
                            <div class="file-info">
                                <strong title="<?php echo esc_attr($log['file_path']); ?>">
                                    <?php echo esc_html($log['file_name']); ?>
                                </strong>
                                <?php if ($log['file_path']): ?>
                                    <div class="file-path">
                                        <small><?php echo esc_html(dirname($log['file_path'])); ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <span class="no-file">-</span>
                        <?php endif; ?>
                    </td>
                    
                    <td class="size column-size">
                        <?php if ($file_size): ?>
                            <span class="file-size"><?php echo $file_size; ?></span>
                        <?php else: ?>
                            <span class="no-size">-</span>
                        <?php endif; ?>
                    </td>
                    
                    <td class="ip column-ip">
                        <span class="ip-address" title="<?php echo esc_attr($log['user_agent']); ?>">
                            <?php echo esc_html($log['ip_address']); ?>
                        </span>
                        <?php if ($log['user_agent']): ?>
                            <button type="button" class="btn-icon btn-view-agent" title="Visualizza User Agent">
                                <span class="dashicons dashicons-info"></span>
                            </button>
                        <?php endif; ?>
                    </td>
                    
                    <td class="details column-details">
                        <?php if ($details): ?>
                            <button type="button" class="btn-icon btn-view-details" data-details="<?php echo esc_attr(json_encode($details)); ?>" title="Visualizza dettagli">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        <?php else: ?>
                            <span class="no-details">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            
            <tfoot>
                <tr>
                    <th scope="col">Data/Ora</th>
                    <th scope="col">Utente</th>
                    <th scope="col">Azione</th>
                    <th scope="col">File</th>
                    <th scope="col">Dimensione</th>
                    <th scope="col">IP</th>
                    <th scope="col">Dettagli</th>
                </tr>
            </tfoot>
        </table>
        
        <?php else: ?>
        <div class="no-logs-found">
            <div class="no-logs-icon">📋</div>
            <h3>Nessun log trovato</h3>
            <?php if ($user_filter || $action_filter || $date_from || $date_to || $search_file): ?>
                <p>Nessun log corrisponde ai filtri applicati.</p>
                <a href="?page=naval-egt&tab=logs" class="btn-secondary">Rimuovi Filtri</a>
            <?php else: ?>
                <p>Non ci sono ancora log di attività registrati nel sistema.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Paginazione -->
    <?php if ($total_pages > 1): ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num"><?php printf('%s elementi', number_format($total_logs)); ?></span>
            <span class="pagination-links">
                <?php
                $base_url = add_query_arg(array(
                    'page' => 'naval-egt',
                    'tab' => 'logs',
                    'user_id' => $user_filter,
                    'action_filter' => $action_filter,
                    'date_from' => $date_from,
                    'date_to' => $date_to,
                    'search_file' => $search_file
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
    </div>
    <?php endif; ?>

</div>

<!-- Template Modal Dettagli -->
<script type="text/template" id="details-modal-template">
    <div class="details-modal-content">
        <div class="details-content" id="details-content">
            <!-- Contenuto dinamico -->
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-secondary btn-cancel">
                <span class="dashicons dashicons-no-alt"></span>
                Chiudi
            </button>
        </div>
    </div>
</script>

<!-- Template Modal Export -->
<script type="text/template" id="export-modal-template">
    <div class="export-modal-content">
        <form id="export-logs-form">
            <div class="form-section">
                <h4>Opzioni Export</h4>
                <div class="form-group">
                    <label>Formato:</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="format" value="csv" checked>
                            CSV (Excel)
                        </label>
                        <label>
                            <input type="radio" name="format" value="html">
                            HTML (Stampa)
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="apply_filters" value="1" checked>
                        Applica filtri correnti
                    </label>
                    <small>Se deselezionato, esporterà tutti i log</small>
                </div>
                
                <div class="form-group">
                    <label for="export-limit">Limite righe:</label>
                    <select id="export-limit" name="limit">
                        <option value="1000">1.000 righe</option>
                        <option value="5000">5.000 righe</option>
                        <option value="10000">10.000 righe</option>
                        <option value="0">Tutti (attenzione ai tempi)</option>
                    </select>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <span class="dashicons dashicons-download"></span>
                    Esporta Log
                </button>
                <button type="button" class="btn-secondary btn-cancel">
                    <span class="dashicons dashicons-no-alt"></span>
                    Annulla
                </button>
            </div>
        </form>
    </div>
</script>

<style>
/* Stili specifici per la tab logs */
.logs-management {
    max-width: 100%;
}

.logs-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid #ddd;
}

.logs-header h2 {
    margin: 0;
    color: #333;
}

.logs-actions {
    display: flex;
    gap: 10px;
}

.btn-danger {
    background: #dc3545;
    color: white;
    border: 1px solid #dc3545;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-danger:hover {
    background: #c82333;
    border-color: #bd2130;
}

/* Statistiche */
.log-statistics {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 25px;
    margin-bottom: 30px;
}

.stat-group {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
}

.stat-group h4 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 16px;
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 8px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
}

.stat-icon {
    font-size: 20px;
    width: 30px;
    text-align: center;
}

.stat-number {
    font-size: 18px;
    font-weight: bold;
    color: #4285f4;
}

.stat-label {
    font-size: 11px;
    color: #666;
    line-height: 1.2;
}

/* Chart giornaliero */
.daily-chart {
    display: flex;
    align-items: end;
    gap: 8px;
    height: 120px;
    padding: 10px 0;
}

.daily-bar {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
    position: relative;
}

.bar-fill {
    width: 100%;
    min-height: 2px;
    background: linear-gradient(to top, #4285f4, #34a853);
    border-radius: 2px 2px 0 0;
    transition: height 0.3s ease;
    margin-top: auto;
}

.bar-label {
    font-size: 10px;
    color: #666;
    margin-top: 5px;
}

.bar-count {
    position: absolute;
    top: -20px;
    font-size: 10px;
    font-weight: bold;
    color: #333;
}

/* Utenti attivi */
.active-users-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.active-user-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: #f8f9fa;
    border-radius: 6px;
}

.user-info strong {
    display: block;
    color: #333;
    font-size: 13px;
}

.user-info small {
    color: #666;
    font-size: 11px;
}

.activity-count {
    text-align: right;
}

.activity-count .count {
    display: block;
    font-weight: bold;
    color: #4285f4;
    font-size: 14px;
}

.activity-count small {
    color: #666;
    font-size: 10px;
}

/* Filtri */
.logs-filters {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 25px;
}

.filters-form {
    margin-bottom: 20px;
}

.filters-row {
    display: flex;
    gap: 20px;
    align-items: flex-end;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
    min-width: 150px;
}

.filter-group label {
    font-weight: 600;
    color: #333;
    font-size: 13px;
}

.filter-group input,
.filter-group select {
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 13px;
}

.filter-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

/* Quick filters */
.quick-filters h5 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 14px;
}

.quick-filter-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.quick-filter {
    padding: 6px 12px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 15px;
    text-decoration: none;
    color: #666;
    font-size: 12px;
    transition: all 0.2s ease;
}

.quick-filter:hover,
.quick-filter.active {
    background: #4285f4;
    color: white;
    border-color: #4285f4;
    text-decoration: none;
}

/* Risultati */
.logs-results {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    flex-wrap: wrap;
    gap: 15px;
}

.results-count {
    color: #666;
    font-size: 14px;
}

.results-actions {
    display: flex;
    gap: 10px;
}

/* Tabella log */
.logs-table-container {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 20px;
}

.logs table {
    margin: 0;
}

.logs th,
.logs td {
    padding: 12px 8px;
    border-bottom: 1px solid #f0f0f0;
}

.logs th {
    background: #f8f9fa;
    font-weight: 600;
    color: #333;
    font-size: 13px;
}

.log-row {
    transition: background-color 0.2s ease;
}

.log-row:hover {
    background: #f8f9ff;
}

/* Colonne specifiche */
.column-datetime {
    width: 140px;
}

.column-user {
    width: 150px;
}

.column-action {
    width: 120px;
}

.column-file {
    width: 200px;
}

.column-size {
    width: 80px;
}

.column-ip {
    width: 120px;
}

.column-details {
    width: 60px;
}

.datetime strong {
    display: block;
    color: #333;
    font-size: 13px;
}

.row-meta small {
    color: #999;
    font-size: 11px;
}

.user-info strong {
    display: block;
    color: #333;
    font-size: 13px;
}

.user-code small {
    color: #666;
    font-size: 11px;
}

/* Action badges */
.action-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.action-badge.action-login {
    background: #e1f5fe;
    color: #0277bd;
}

.action-badge.action-logout {
    background: #fce4ec;
    color: #c2185b;
}

.action-badge.action-upload {
    background: #e8f5e8;
    color: #2e7d32;
}

.action-badge.action-download {
    background: #fff3e0;
    color: #ef6c00;
}

.action-badge.action-registration {
    background: #f3e5f5;
    color: #7b1fa2;
}

.action-badge.action-admin_upload {
    background: #e0f2f1;
    color: #00695c;
}

.action-badge.action-admin_action {
    background: #f1f8e9;
    color: #558b2f;
}

.file-info strong {
    display: block;
    color: #333;
    font-size: 13px;
    word-break: break-word;
}

.file-path small {
    color: #666;
    font-size: 11px;
}

.ip-address {
    font-family: monospace;
    font-size: 12px;
    color: #333;
}

.no-file,
.no-size,
.no-details {
    color: #ccc;
    font-style: italic;
}

/* No logs */
.no-logs-found {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.no-logs-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.no-logs-found h3 {
    margin: 0 0 15px 0;
    color: #333;
}

/* Modal styles */
.details-modal-content {
    max-width: 600px;
}

.details-content {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 20px;
    max-height: 400px;
    overflow-y: auto;
}

.details-content pre {
    margin: 0;
    font-family: monospace;
    font-size: 12px;
    white-space: pre-wrap;
    word-break: break-word;
}

.export-modal-content {
    max-width: 500px;
}

.form-section {
    margin-bottom: 20px;
}

.form-section h4 {
    margin: 0 0 15px 0;
    color: #333;
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 8px;
}

.radio-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.radio-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

/* Responsive */
@media (max-width: 1200px) {
    .log-statistics {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .logs-header {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }
    
    .logs-actions {
        justify-content: center;
    }
    
    .filters-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group {
        min-width: 100%;
    }
    
    .quick-filter-buttons {
        justify-content: center;
    }
    
    .logs-results {
        flex-direction: column;
        align-items: stretch;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .daily-chart {
        height: 80px;
    }
    
    .logs table {
        font-size: 12px;
    }
    
    .logs th,
    .logs td {
        padding: 8px 4px;
    }
    
    /* Nasconde alcune colonne su mobile */
    .column-size,
    .column-details {
        display: none;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // Auto-submit filtri con delay
    let filterTimeout;
    
    $('#user-filter, #action-filter, #date-from, #date-to').on('change', function() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(function() {
            $('.filters-form').submit();
        }, 300);
    });
    
    $('#search-file').on('input', function() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(function() {
            $('.filters-form').submit();
        }, 800);
    });
    
    // Visualizza dettagli log
    $(document).on('click', '.btn-view-details', function() {
        const details = $(this).data('details');
        showDetailsModal(details);
    });
    
    // Visualizza user agent
    $(document).on('click', '.btn-view-agent', function() {
        const userAgent = $(this).attr('title').replace('Visualizza User Agent', '');
        const row = $(this).closest('tr');
        const ip = row.find('.ip-address').text();
        
        const agentInfo = {
            'IP Address': ip,
            'User Agent': userAgent || 'Non disponibile'
        };
        
        showDetailsModal(agentInfo);
    });
    
    // Export logs
    $('#export-logs').on('click', function() {
        openExportModal();
    });
    
    $('.export-filtered').on('click', function() {
        const format = $(this).data('format');
        exportFilteredLogs(format);
    });
    
    // Clear logs
    $('#clear-logs').on('click', function() {
        if (confirm('ATTENZIONE: Sei sicuro di voler eliminare tutti i log?\n\nQuesta azione non può essere annullata e rimuoverà permanentemente tutti i record di attività.')) {
            clearAllLogs();
        }
    });
    
    // Refresh
    $('#refresh-logs').on('click', function() {
        location.reload();
    });
    
    // Funzioni helper
    function showDetailsModal(details) {
        const template = $('#details-modal-template').html();
        showModal(template, 'Dettagli Log');
        
        let content = '';
        if (typeof details === 'object') {
            content = '<pre>' + JSON.stringify(details, null, 2) + '</pre>';
        } else {
            content = '<pre>' + details + '</pre>';
        }
        
        $('#details-content').html(content);
    }
    
    function openExportModal() {
        const template = $('#export-modal-template').html();
        showModal(template, 'Esporta Log Attività');
        
        $('#export-logs-form').on('submit', function(e) {
            e.preventDefault();
            executeExport();
        });
    }
    
    function executeExport() {
        const formData = new FormData($('#export-logs-form')[0]);
        
        // Aggiungi filtri correnti se richiesto
        if (formData.get('apply_filters') === '1') {
            formData.append('user_id', '<?php echo $user_filter; ?>');
            formData.append('action_filter', '<?php echo $action_filter; ?>');
            formData.append('date_from', '<?php echo $date_from; ?>');
            formData.append('date_to', '<?php echo $date_to; ?>');
            formData.append('search_file', '<?php echo $search_file; ?>');
        }
        
        // Crea form per download
        const form = $('<form>', {
            method: 'POST',
            action: ajaxurl
        });
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'naval_egt_ajax'
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'naval_action',
            value: 'export_logs'
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'nonce',
            value: naval_egt_ajax.nonce
        }));
        
        // Aggiungi tutti i campi del form
        for (const [key, value] of formData.entries()) {
            form.append($('<input>', {
                type: 'hidden',
                name: key,
                value: value
            }));
        }
        
        $('body').append(form);
        form.submit();
        form.remove();
        
        $('.modal-overlay').remove();
    }
    
    function exportFilteredLogs(format) {
        const form = $('<form>', {
            method: 'POST',
            action: ajaxurl
        });
        
        const params = {
            action: 'naval_egt_ajax',
            naval_action: 'export_logs',
            nonce: naval_egt_ajax.nonce,
            format: format,
            apply_filters: '1',
            user_id: '<?php echo $user_filter; ?>',
            action_filter: '<?php echo $action_filter; ?>',
            date_from: '<?php echo $date_from; ?>',
            date_to: '<?php echo $date_to; ?>',
            search_file: '<?php echo $search_file; ?>',
            limit: '5000'
        };
        
        for (const [key, value] of Object.entries(params)) {
            form.append($('<input>', {
                type: 'hidden',
                name: key,
                value: value
            }));
        }
        
        $('body').append(form);
        form.submit();
        form.remove();
    }
    
    function clearAllLogs() {
        $.post(ajaxurl, {
            action: 'naval_egt_ajax',
            naval_action: 'clear_logs',
            nonce: naval_egt_ajax.nonce
        }, function(response) {
            if (response.success) {
                alert('Tutti i log sono stati eliminati con successo');
                location.reload();
            } else {
                alert('Errore durante l\'eliminazione: ' + response.data);
            }
        });
    }
    
    function showModal(content, title) {
        const modal = $(`
            <div class="modal-overlay">
                <div class="modal-container">
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
        
        $('.modal-close, .btn-cancel').on('click', function() {
            modal.fadeOut(200, function() {
                $(this).remove();
            });
        });
    }
});
</script>