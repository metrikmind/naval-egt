<?php
/**
 * Tab Gestione File - Dashboard Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verifica permessi
if (!current_user_can('manage_options')) {
    wp_die(__('Non hai i permessi per accedere a questa pagina.'));
}

// Ottieni lista utenti per il dropdown
$users = Naval_EGT_User_Manager::get_users(array(), 1000, 0);
$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Se è selezionato un utente, ottieni i suoi file
$user_files = array();
$user_folders = array();
$selected_user = null;

if ($selected_user_id) {
    $selected_user = Naval_EGT_User_Manager::get_user_by_id($selected_user_id);
    if ($selected_user) {
        $user_files = Naval_EGT_File_Manager::get_user_files($selected_user_id);
        $folder_structure = Naval_EGT_File_Manager::get_user_folder_structure($selected_user_id);
        if ($folder_structure['success']) {
            $user_folders = $folder_structure['structure']['folders'] ?? array();
        }
    }
}

// Statistiche file
$file_stats = Naval_EGT_File_Manager::get_file_stats();
$dropbox = Naval_EGT_Dropbox::get_instance();
$dropbox_configured = $dropbox->is_configured();
?>

<div class="files-management">
    
    <!-- Header sezione -->
    <div class="files-header">
        <h2>Gestione File Globale</h2>
        <div class="files-actions">
            <button type="button" id="sync-all-folders" class="btn-secondary" <?php echo !$dropbox_configured ? 'disabled' : ''; ?>>
                <span class="dashicons dashicons-update"></span>
                Sincronizza Tutto
            </button>
            <button type="button" id="refresh-files" class="btn-secondary">
                <span class="dashicons dashicons-update"></span>
                Aggiorna
            </button>
        </div>
    </div>

    <!-- Statistiche File -->
    <div class="file-statistics">
        <div class="stat-card">
            <div class="stat-icon">📁</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($file_stats['total_files']); ?></div>
                <div class="stat-label">File Totali</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">💾</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo size_format($file_stats['total_size']); ?></div>
                <div class="stat-label">Spazio Utilizzato</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo count($users); ?></div>
                <div class="stat-label">Utenti con File</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">☁️</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $dropbox_configured ? 'OK' : 'NO'; ?></div>
                <div class="stat-label">Dropbox</div>
            </div>
        </div>
    </div>

    <!-- Selezione Utente -->
    <div class="user-selection-section">
        <div class="section-header">
            <h3>Seleziona Utente per Gestione File</h3>
            <p>Scegli un utente per visualizzare e gestire i suoi file su Dropbox</p>
        </div>
        
        <form method="get" class="user-selection-form">
            <input type="hidden" name="page" value="naval-egt">
            <input type="hidden" name="tab" value="files">
            
            <div class="form-group">
                <label for="user-select">Seleziona Utente:</label>
                <select id="user-select" name="user_id" onchange="this.form.submit()">
                    <option value="">-- Seleziona un utente --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php selected($selected_user_id, $user['id']); ?>>
                            <?php echo esc_html($user['user_code'] . ' - ' . $user['nome'] . ' ' . $user['cognome']); ?>
                            <?php if (!empty($user['ragione_sociale'])): ?>
                                (<?php echo esc_html($user['ragione_sociale']); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if ($selected_user_id): ?>
                <a href="?page=naval-egt&tab=files" class="btn-secondary">Deseleziona</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($selected_user): ?>
    <!-- Sezione Utente Selezionato -->
    <div class="selected-user-section">
        <div class="user-info-card">
            <div class="user-avatar">
                <span class="dashicons dashicons-admin-users"></span>
            </div>
            <div class="user-details">
                <h4><?php echo esc_html($selected_user['nome'] . ' ' . $selected_user['cognome']); ?></h4>
                <p><strong>Codice:</strong> <?php echo esc_html($selected_user['user_code']); ?></p>
                <p><strong>Email:</strong> <?php echo esc_html($selected_user['email']); ?></p>
                <?php if (!empty($selected_user['ragione_sociale'])): ?>
                    <p><strong>Azienda:</strong> <?php echo esc_html($selected_user['ragione_sociale']); ?></p>
                <?php endif; ?>
                <p><strong>Status:</strong> 
                    <span class="status-badge <?php echo $selected_user['status'] === 'ATTIVO' ? 'status-active' : 'status-suspended'; ?>">
                        <?php echo $selected_user['status']; ?>
                    </span>
                </p>
            </div>
            <div class="user-actions">
                <button type="button" class="btn-secondary btn-sync-user" data-user-id="<?php echo $selected_user['id']; ?>">
                    <span class="dashicons dashicons-update"></span>
                    Sincronizza
                </button>
                <button type="button" class="btn-primary btn-create-folder" data-user-id="<?php echo $selected_user['id']; ?>">
                    <span class="dashicons dashicons-plus"></span>
                    Crea Cartella
                </button>
            </div>
        </div>

        <!-- Dropbox Status -->
        <div class="dropbox-status-section">
            <h4>
                <span class="dashicons dashicons-cloud"></span>
                Status Connessione Dropbox
            </h4>
            
            <?php if ($dropbox_configured): ?>
                <div class="dropbox-status connected">
                    <span class="status-icon">✅</span>
                    <div class="status-info">
                        <strong>Connesso</strong>
                        <?php if (!empty($selected_user['dropbox_folder'])): ?>
                            <p>Cartella: <code><?php echo esc_html($selected_user['dropbox_folder']); ?></code></p>
                        <?php else: ?>
                            <p class="text-warning">⚠️ Cartella non collegata automaticamente</p>
                            <button type="button" class="btn-link btn-link-dropbox" data-user-id="<?php echo $selected_user['id']; ?>">
                                Collega Cartella Dropbox
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="dropbox-status disconnected">
                    <span class="status-icon">❌</span>
                    <div class="status-info">
                        <strong>Non Configurato</strong>
                        <p>Configura Dropbox nella sezione Panoramica per gestire i file</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Caricamento File -->
        <div class="file-upload-section">
            <h4>
                <span class="dashicons dashicons-upload"></span>
                Carica File per <?php echo esc_html($selected_user['nome']); ?>
            </h4>
            
            <div class="upload-area">
                <div class="folder-selection">
                    <label for="target-folder">Cartella di Destinazione:</label>
                    <select id="target-folder" name="target_folder">
                        <option value="">Cartella Principale</option>
                        <?php foreach ($user_folders as $folder): ?>
                            <option value="<?php echo esc_attr($folder['path']); ?>">
                                <?php echo esc_html($folder['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="file-drop-zone" id="file-drop-zone">
                    <div class="drop-zone-content">
                        <span class="drop-icon">📁</span>
                        <h5>Trascina i file qui o clicca per selezionare</h5>
                        <p>Supporta: PDF, DOC, XLS, immagini, file CAD</p>
                        <p><small>Dimensione massima: 10MB per file</small></p>
                    </div>
                    <input type="file" id="file-upload-input" multiple style="display: none;" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.dwg,.dxf,.zip,.rar">
                </div>
                
                <div class="upload-progress" id="upload-progress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress-fill"></div>
                    </div>
                    <div class="progress-text" id="progress-text">Caricamento in corso...</div>
                </div>
                
                <button type="button" id="upload-files-btn" class="btn-primary" style="display: none;">
                    <span class="dashicons dashicons-upload"></span>
                    Carica File Selezionati
                </button>
            </div>
        </div>

        <!-- Lista File Utente -->
        <div class="user-files-section">
            <div class="files-header">
                <h4>
                    <span class="dashicons dashicons-media-default"></span>
                    File di <?php echo esc_html($selected_user['nome']); ?> (<?php echo count($user_files); ?>)
                </h4>
                <div class="files-actions">
                    <button type="button" class="btn-secondary btn-refresh-files">
                        <span class="dashicons dashicons-update"></span>
                        Aggiorna Lista
                    </button>
                </div>
            </div>
            
            <?php if (!empty($user_files)): ?>
            <div class="files-grid">
                <?php foreach ($user_files as $file): ?>
                <div class="file-card" data-file-id="<?php echo $file['id']; ?>">
                    <div class="file-icon">
                        <?php echo Naval_EGT_File_Manager::get_file_icon($file['file_name']); ?>
                    </div>
                    <div class="file-info">
                        <div class="file-name" title="<?php echo esc_attr($file['file_name']); ?>">
                            <?php echo esc_html($file['file_name']); ?>
                        </div>
                        <div class="file-meta">
                            <span class="file-size"><?php echo size_format($file['file_size']); ?></span>
                            <span class="file-date"><?php echo date('d/m/Y H:i', strtotime($file['created_at'])); ?></span>
                        </div>
                        <div class="file-path">
                            <small title="<?php echo esc_attr($file['dropbox_path']); ?>">
                                📁 <?php echo esc_html(dirname($file['file_path'])); ?>
                            </small>
                        </div>
                    </div>
                    <div class="file-actions">
                        <button type="button" class="btn-icon btn-download" data-file-id="<?php echo $file['id']; ?>" title="Scarica">
                            <span class="dashicons dashicons-download"></span>
                        </button>
                        <button type="button" class="btn-icon btn-copy-link" data-file-path="<?php echo esc_attr($file['dropbox_path']); ?>" title="Copia Link">
                            <span class="dashicons dashicons-admin-links"></span>
                        </button>
                        <button type="button" class="btn-icon btn-move-file" data-file-id="<?php echo $file['id']; ?>" title="Sposta">
                            <span class="dashicons dashicons-move"></span>
                        </button>
                        <button type="button" class="btn-icon btn-delete-file text-danger" data-file-id="<?php echo $file['id']; ?>" data-file-name="<?php echo esc_attr($file['file_name']); ?>" title="Elimina">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php else: ?>
            <div class="no-files">
                <div class="no-files-icon">📄</div>
                <h5>Nessun file presente</h5>
                <p>Questo utente non ha ancora caricato file o la sincronizzazione Dropbox non è ancora stata effettuata.</p>
                <?php if ($dropbox_configured): ?>
                    <button type="button" class="btn-secondary btn-sync-user" data-user-id="<?php echo $selected_user['id']; ?>">
                        Sincronizza con Dropbox
                    </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Struttura Cartelle -->
        <?php if (!empty($user_folders)): ?>
        <div class="folder-structure-section">
            <h4>
                <span class="dashicons dashicons-category"></span>
                Struttura Cartelle Dropbox
            </h4>
            
            <div class="folders-tree">
                <div class="folder-item main-folder">
                    <span class="folder-icon">📁</span>
                    <span class="folder-name">
                        <?php echo esc_html($selected_user['user_code']); ?>
                        <small>(Cartella Principale)</small>
                    </span>
                </div>
                
                <?php foreach ($user_folders as $folder): ?>
                <div class="folder-item sub-folder">
                    <span class="folder-icon">📂</span>
                    <span class="folder-name"><?php echo esc_html($folder['name']); ?></span>
                    <div class="folder-actions">
                        <button type="button" class="btn-icon btn-browse-folder" data-folder-path="<?php echo esc_attr($folder['path']); ?>" title="Sfoglia">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    <?php else: ?>
    <!-- Nessun utente selezionato -->
    <div class="no-user-selected">
        <div class="no-user-icon">👤</div>
        <h3>Seleziona un utente per iniziare</h3>
        <p>Scegli un utente dal menu dropdown sopra per visualizzare e gestire i suoi file su Dropbox.</p>
    </div>
    
    <!-- Tipi di file supportati -->
    <div class="supported-files-info">
        <h4>Tipi di File Supportati</h4>
        <div class="file-types-grid">
            <?php 
            $supported_types = array(
                'pdf' => 'Documenti PDF',
                'doc,docx' => 'Microsoft Word',
                'xls,xlsx' => 'Microsoft Excel',
                'jpg,jpeg,png,gif' => 'Immagini',
                'dwg,dxf' => 'File CAD',
                'zip,rar' => 'Archivi Compressi'
            );
            
            foreach ($supported_types as $extensions => $description): ?>
            <div class="file-type-card">
                <div class="file-type-icon">📎</div>
                <div class="file-type-info">
                    <strong><?php echo $description; ?></strong>
                    <small>.<?php echo str_replace(',', ', .', $extensions); ?></small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistiche per tipo di file -->
    <?php if (!empty($file_stats['by_type'])): ?>
    <div class="file-stats-section">
        <h4>Distribuzione File per Tipo</h4>
        <div class="stats-chart">
            <?php foreach ($file_stats['by_type'] as $stat): ?>
            <div class="stat-item">
                <div class="stat-bar">
                    <div class="stat-fill" style="width: <?php echo ($stat['count'] / $file_stats['total_files']) * 100; ?>%"></div>
                </div>
                <div class="stat-details">
                    <strong>.<?php echo strtoupper($stat['extension']); ?></strong>
                    <span><?php echo $stat['count']; ?> file</span>
                    <small><?php echo size_format($stat['total_size']); ?></small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Template Modal Crea Cartella -->
<script type="text/template" id="create-folder-template">
    <div class="create-folder-content">
        <form id="create-folder-form">
            <div class="form-group">
                <label for="folder-name">Nome Cartella *</label>
                <input type="text" id="folder-name" name="folder_name" required placeholder="Inserisci nome cartella">
                <small>Sarà creata nella cartella principale dell'utente</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <span class="dashicons dashicons-yes"></span>
                    Crea Cartella
                </button>
                <button type="button" class="btn-secondary btn-cancel">
                    <span class="dashicons dashicons-no-alt"></span>
                    Annulla
                </button>
            </div>
            
            <input type="hidden" id="folder-user-id" name="user_id" value="">
        </form>
    </div>
</script>

<style>
/* Stili specifici per la tab file */
.files-management {
    max-width: 100%;
}

.files-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid #ddd;
}

.files-header h2 {
    margin: 0;
    color: #333;
}

.files-actions {
    display: flex;
    gap: 10px;
}

/* Statistiche file */
.file-statistics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: transform 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.stat-icon {
    font-size: 32px;
    opacity: 0.8;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #4285f4;
    margin-bottom: 4px;
}

.stat-label {
    color: #666;
    font-size: 14px;
}

/* Selezione utente */
.user-selection-section {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 25px;
}

.section-header h3 {
    margin: 0 0 8px 0;
    color: #333;
}

.section-header p {
    margin: 0 0 20px 0;
    color: #666;
}

.user-selection-form {
    display: flex;
    align-items: center;
    gap: 15px;
}

.user-selection-form select {
    min-width: 300px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

/* Utente selezionato */
.selected-user-section {
    margin-bottom: 30px;
}

.user-info-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
}

.user-avatar {
    width: 60px;
    height: 60px;
    background: #4285f4;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.user-details {
    flex: 1;
}

.user-details h4 {
    margin: 0 0 10px 0;
    color: #333;
}

.user-details p {
    margin: 5px 0;
    font-size: 14px;
    color: #666;
}

.user-actions {
    display: flex;
    gap: 10px;
}

/* Status Dropbox */
.dropbox-status-section {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 25px;
}

.dropbox-status-section h4 {
    margin: 0 0 15px 0;
    color: #333;
}

.dropbox-status {
    display: flex;
    align-items: flex-start;
    gap: 15px;
}

.status-icon {
    font-size: 20px;
}

.dropbox-status.connected {
    color: #28a745;
}

.dropbox-status.disconnected {
    color: #dc3545;
}

.btn-link {
    background: none;
    border: none;
    color: #4285f4;
    text-decoration: underline;
    cursor: pointer;
    font-size: 14px;
}

.btn-link:hover {
    color: #3367d6;
}

/* Upload area */
.file-upload-section {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 25px;
}

.file-upload-section h4 {
    margin: 0 0 20px 0;
    color: #333;
}

.upload-area {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.folder-selection select {
    width: 300px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.file-drop-zone {
    border: 2px dashed #ccc;
    border-radius: 8px;
    padding: 40px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.file-drop-zone:hover,
.file-drop-zone.drag-over {
    border-color: #4285f4;
    background: #f8f9ff;
}

.drop-zone-content {
    pointer-events: none;
}

.drop-icon {
    font-size: 48px;
    margin-bottom: 15px;
    display: block;
}

.drop-zone-content h5 {
    margin: 0 0 10px 0;
    color: #333;
}

.drop-zone-content p {
    margin: 5px 0;
    color: #666;
}

.upload-progress {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
}

.progress-bar {
    width: 100%;
    height: 20px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 10px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #4285f4, #34a853);
    width: 0%;
    transition: width 0.3s ease;
}

.progress-text {
    text-align: center;
    color: #666;
    font-weight: 600;
}

/* Lista file */
.user-files-section {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 25px;
}

.files-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.files-header h4 {
    margin: 0;
    color: #333;
}

.files-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.file-card {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 15px;
    transition: all 0.2s ease;
    background: #fafafa;
}

.file-card:hover {
    border-color: #4285f4;
    background: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.file-card .file-icon {
    font-size: 32px;
    margin-bottom: 10px;
}

.file-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
    word-break: break-word;
}

.file-meta {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
    font-size: 12px;
    color: #666;
}

.file-path {
    margin-bottom: 15px;
}

.file-path small {
    color: #999;
    font-size: 11px;
}

.file-actions {
    display: flex;
    gap: 5px;
    justify-content: flex-end;
}

.no-files {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.no-files-icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.no-files h5 {
    margin: 0 0 10px 0;
    color: #333;
}

/* Struttura cartelle */
.folder-structure-section {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 25px;
}

.folder-structure-section h4 {
    margin: 0 0 20px 0;
    color: #333;
}

.folders-tree {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.folder-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    background: #f8f9fa;
}

.folder-item.main-folder {
    background: #e3f2fd;
    border-color: #4285f4;
    font-weight: 600;
}

.folder-item.sub-folder {
    margin-left: 30px;
}

.folder-icon {
    font-size: 18px;
}

.folder-name {
    flex: 1;
}

.folder-name small {
    color: #666;
    font-weight: normal;
}

.folder-actions {
    display: flex;
    gap: 5px;
}

/* Nessun utente selezionato */
.no-user-selected {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.no-user-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.no-user-selected h3 {
    margin: 0 0 15px 0;
    color: #333;
}

/* Tipi file supportati */
.supported-files-info {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 25px;
}

.supported-files-info h4 {
    margin: 0 0 20px 0;
    color: #333;
}

.file-types-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.file-type-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    background: #f8f9fa;
}

.file-type-icon {
    font-size: 24px;
}

.file-type-info strong {
    display: block;
    color: #333;
    margin-bottom: 2px;
}

.file-type-info small {
    color: #666;
}

/* Statistiche per tipo */
.file-stats-section {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 25px;
}

.file-stats-section h4 {
    margin: 0 0 20px 0;
    color: #333;
}

.stats-chart {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-bar {
    flex: 1;
    height: 20px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}

.stat-fill {
    height: 100%;
    background: linear-gradient(90deg, #4285f4, #34a853);
    border-radius: 10px;
}

.stat-details {
    min-width: 120px;
    text-align: right;
    font-size: 14px;
}

.stat-details strong {
    display: block;
    color: #333;
}

.stat-details span {
    display: block;
    color: #666;
    font-size: 12px;
}

.stat-details small {
    color: #999;
    font-size: 11px;
}

/* Responsive */
@media (max-width: 768px) {
    .files-header {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }
    
    .user-info-card {
        flex-direction: column;
        text-align: center;
    }
    
    .user-selection-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .user-selection-form select {
        min-width: 100%;
    }
    
    .files-grid {
        grid-template-columns: 1fr;
    }
    
    .file-types-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-item {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    let selectedFiles = [];
    let currentUserId = <?php echo $selected_user_id; ?>;
    
    // Gestione drag & drop
    const dropZone = $('#file-drop-zone');
    const fileInput = $('#file-upload-input');
    
    dropZone.on('click', function() {
        fileInput.click();
    });
    
    dropZone.on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('drag-over');
    });
    
    dropZone.on('dragleave', function(e) {
        e.preventDefault();
        $(this).removeClass('drag-over');
    });
    
    dropZone.on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('drag-over');
        
        const files = e.originalEvent.dataTransfer.files;
        handleFileSelection(files);
    });
    
    fileInput.on('change', function() {
        const files = this.files;
        handleFileSelection(files);
    });
    
    function handleFileSelection(files) {
        selectedFiles = Array.from(files);
        
        if (selectedFiles.length > 0) {
            updateDropZone();
            $('#upload-files-btn').show();
        }
    }
    
    function updateDropZone() {
        const fileList = selectedFiles.map(file => 
            `<div class="selected-file">
                <span class="file-icon">📎</span>
                <span class="file-name">${file.name}</span>
                <span class="file-size">(${formatFileSize(file.size)})</span>
            </div>`
        ).join('');
        
        dropZone.html(`
            <div class="selected-files-list">
                <h5>${selectedFiles.length} file selezionati</h5>
                ${fileList}
                <button type="button" class="btn-link" onclick="clearSelectedFiles()">Cancella selezione</button>
            </div>
        `);
    }
    
    window.clearSelectedFiles = function() {
        selectedFiles = [];
        fileInput.val('');
        $('#upload-files-btn').hide();
        
        dropZone.html(`
            <div class="drop-zone-content">
                <span class="drop-icon">📁</span>
                <h5>Trascina i file qui o clicca per selezionare</h5>
                <p>Supporta: PDF, DOC, XLS, immagini, file CAD</p>
                <p><small>Dimensione massima: 10MB per file</small></p>
            </div>
        `);
    };
    
    // Upload file
    $('#upload-files-btn').on('click', function() {
        if (selectedFiles.length === 0 || !currentUserId) {
            alert('Seleziona dei file e un utente');
            return;
        }
        
        uploadFiles();
    });
    
    function uploadFiles() {
        const formData = new FormData();
        formData.append('action', 'naval_egt_ajax');
        formData.append('naval_action', 'admin_upload_files');
        formData.append('nonce', naval_egt_ajax.nonce);
        formData.append('user_id', currentUserId);
        formData.append('folder_path', $('#target-folder').val());
        
        selectedFiles.forEach(file => {
            formData.append('files[]', file);
        });
        
        // Mostra progress
        $('#upload-progress').show();
        $('#upload-files-btn').hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentage = Math.round((e.loaded / e.total) * 100);
                        updateProgress(percentage);
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                $('#upload-progress').hide();
                
                if (response.success) {
                    alert('File caricati con successo!');
                    clearSelectedFiles();
                    refreshUserFiles();
                } else {
                    alert('Errore: ' + response.data);
                    $('#upload-files-btn').show();
                }
            },
            error: function() {
                $('#upload-progress').hide();
                $('#upload-files-btn').show();
                alert('Errore di connessione');
            }
        });
    }
    
    function updateProgress(percentage) {
        $('#progress-fill').css('width', percentage + '%');
        $('#progress-text').text(`Caricamento... ${percentage}%`);
    }
    
    // Gestione azioni file
    $(document).on('click', '.btn-download', function() {
        const fileId = $(this).data('file-id');
        downloadFile(fileId);
    });
    
    $(document).on('click', '.btn-delete-file', function() {
        const fileId = $(this).data('file-id');
        const fileName = $(this).data('file-name');
        deleteFile(fileId, fileName);
    });
    
    $(document).on('click', '.btn-copy-link', function() {
        const filePath = $(this).data('file-path');
        copyToClipboard(filePath);
    });
    
    // Gestione azioni utente
    $(document).on('click', '.btn-sync-user', function() {
        const userId = $(this).data('user-id');
        syncUserFolder(userId);
    });
    
    $(document).on('click', '.btn-create-folder', function() {
        const userId = $(this).data('user-id');
        openCreateFolderModal(userId);
    });
    
    $(document).on('click', '.btn-link-dropbox', function() {
        const userId = $(this).data('user-id');
        linkDropboxFolder(userId);
    });
    
    // Refresh
    $('#refresh-files, .btn-refresh-files').on('click', function() {
        location.reload();
    });
    
    $('#sync-all-folders').on('click', function() {
        if (confirm('Vuoi sincronizzare tutte le cartelle utenti? Questa operazione potrebbe richiedere del tempo.')) {
            syncAllFolders();
        }
    });
    
    // Funzioni helper
    function downloadFile(fileId) {
        const url = `${ajaxurl}?action=naval_egt_download_file&file_id=${fileId}&nonce=${naval_egt_ajax.nonce}`;
        window.open(url, '_blank');
    }
    
    function deleteFile(fileId, fileName) {
        if (confirm(`Sei sicuro di voler eliminare il file "${fileName}"?`)) {
            $.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'delete_file',
                nonce: naval_egt_ajax.nonce,
                file_id: fileId
            }, function(response) {
                if (response.success) {
                    alert('File eliminato con successo');
                    refreshUserFiles();
                } else {
                    alert('Errore: ' + response.data);
                }
            });
        }
    }
    
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Percorso file copiato negli appunti');
        });
    }
    
    function syncUserFolder(userId) {
        $.post(ajaxurl, {
            action: 'naval_egt_ajax',
            naval_action: 'sync_user_folder',
            nonce: naval_egt_ajax.nonce,
            user_id: userId
        }, function(response) {
            if (response.success) {
                alert('Sincronizzazione completata');
                location.reload();
            } else {
                alert('Errore: ' + response.data);
            }
        });
    }
    
    function syncAllFolders() {
        $.post(ajaxurl, {
            action: 'naval_egt_ajax',
            naval_action: 'sync_all_user_folders',
            nonce: naval_egt_ajax.nonce
        }, function(response) {
            if (response.success) {
                alert('Sincronizzazione di tutte le cartelle completata');
                location.reload();
            } else {
                alert('Errore: ' + response.data);
            }
        });
    }
    
    function refreshUserFiles() {
        if (currentUserId) {
            location.reload();
        }
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    function openCreateFolderModal(userId) {
        const template = $('#create-folder-template').html();
        showModal(template, 'Crea Nuova Cartella');
        
        $('#folder-user-id').val(userId);
        
        $('#create-folder-form').on('submit', function(e) {
            e.preventDefault();
            createFolder();
        });
    }
    
    function createFolder() {
        const formData = $('#create-folder-form').serialize();
        
        $.post(ajaxurl, {
            action: 'naval_egt_ajax',
            naval_action: 'create_user_folder',
            nonce: naval_egt_ajax.nonce,
            ...Object.fromEntries(new URLSearchParams(formData))
        }, function(response) {
            $('.modal-overlay').remove();
            
            if (response.success) {
                alert('Cartella creata con successo');
                location.reload();
            } else {
                alert('Errore: ' + response.data);
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