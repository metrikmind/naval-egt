<?php
/**
 * Template per l'area riservata utenti - Interfaccia completa
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verifica se l'utente è loggato
$current_user = Naval_EGT_User_Manager::get_current_user();
$is_logged_in = !empty($current_user);

// Carica informazioni pubbliche
$public_info = Naval_EGT_Public::get_public_info();
?>

<div id="naval-egt-area-riservata" class="naval-egt-container">
    
    <?php if (!$is_logged_in): ?>
        <!-- SEZIONE LOGIN/REGISTRAZIONE -->
        <div class="naval-egt-auth-section">
            <div class="auth-tabs">
                <button type="button" class="auth-tab active" data-tab="login">
                    <span class="dashicons dashicons-unlock"></span> Accedi
                </button>
                <?php if ($public_info['registration_enabled']): ?>
                <button type="button" class="auth-tab" data-tab="register">
                    <span class="dashicons dashicons-plus"></span> Registrati
                </button>
                <?php endif; ?>
            </div>

            <!-- TAB LOGIN -->
            <div id="tab-login" class="auth-tab-content active">
                <div class="auth-card">
                    <div class="auth-header">
                        <h2>Accedi alla tua Area Riservata</h2>
                        <p>Inserisci le tue credenziali per accedere ai tuoi file</p>
                    </div>

                    <form id="naval-login-form" class="auth-form">
                        <?php wp_nonce_field('naval_egt_nonce', 'nonce'); ?>
                        <input type="hidden" name="naval_action" value="login">

                        <div class="form-group">
                            <label for="login-email">Email o Username</label>
                            <div class="input-wrapper">
                                <span class="input-icon dashicons dashicons-admin-users"></span>
                                <input type="text" id="login-email" name="login" required 
                                       placeholder="Inserisci email o username">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="login-password">Password</label>
                            <div class="input-wrapper">
                                <span class="input-icon dashicons dashicons-lock"></span>
                                <input type="password" id="login-password" name="password" required 
                                       placeholder="Inserisci password">
                                <button type="button" class="toggle-password" title="Mostra/Nascondi password">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>
                            </div>
                        </div>

                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="remember" value="1">
                                <span class="checkmark"></span>
                                Ricordami per 30 giorni
                            </label>
                        </div>

                        <button type="submit" class="btn-primary btn-full-width">
                            <span class="btn-text">Accedi</span>
                            <span class="btn-loading" style="display: none;">
                                <span class="spinner"></span> Accesso in corso...
                            </span>
                        </button>

                        <div class="auth-footer">
                            <p>Non ricordi la password? <a href="mailto:tecnica@naval.it">Contatta il supporto</a></p>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TAB REGISTRAZIONE -->
            <?php if ($public_info['registration_enabled']): ?>
            <div id="tab-register" class="auth-tab-content">
                <div class="auth-card">
                    <div class="auth-header">
                        <h2>Richiedi Accesso</h2>
                        <p>Compila il form per richiedere l'attivazione del tuo account</p>
                    </div>

                    <form id="naval-register-form" class="auth-form">
                        <?php wp_nonce_field('naval_egt_nonce', 'nonce'); ?>
                        <input type="hidden" name="naval_action" value="register">

                        <div class="form-row">
                            <div class="form-group">
                                <label for="reg-nome">Nome *</label>
                                <input type="text" id="reg-nome" name="nome" required>
                            </div>
                            <div class="form-group">
                                <label for="reg-cognome">Cognome *</label>
                                <input type="text" id="reg-cognome" name="cognome" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="reg-email">Email *</label>
                            <input type="email" id="reg-email" name="email" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="reg-username">Username *</label>
                                <input type="text" id="reg-username" name="username" required>
                            </div>
                            <div class="form-group">
                                <label for="reg-telefono">Telefono</label>
                                <input type="tel" id="reg-telefono" name="telefono">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="reg-password">Password *</label>
                                <input type="password" id="reg-password" name="password" required>
                                <small>Minimo 6 caratteri</small>
                            </div>
                            <div class="form-group">
                                <label for="reg-password-confirm">Conferma Password *</label>
                                <input type="password" id="reg-password-confirm" name="password_confirm" required>
                            </div>
                        </div>

                        <div class="form-section-title">
                            <h4>Informazioni Azienda (Opzionale)</h4>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="reg-ragione-sociale">Ragione Sociale</label>
                                <input type="text" id="reg-ragione-sociale" name="ragione_sociale">
                            </div>
                            <div class="form-group">
                                <label for="reg-partita-iva">Partita IVA</label>
                                <input type="text" id="reg-partita-iva" name="partita_iva">
                                <small>Obbligatoria se specifichi la Ragione Sociale</small>
                            </div>
                        </div>

                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="privacy_policy" value="1" required>
                                <span class="checkmark"></span>
                                Accetto la <a href="#" target="_blank">Privacy Policy</a> *
                            </label>
                        </div>

                        <button type="submit" class="btn-primary btn-full-width">
                            <span class="btn-text">Richiedi Registrazione</span>
                            <span class="btn-loading" style="display: none;">
                                <span class="spinner"></span> Invio in corso...
                            </span>
                        </button>

                        <div class="auth-footer">
                            <p><strong>Nota:</strong> La registrazione richiede approvazione manuale. Riceverai una email di conferma.</p>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- SEZIONE DASHBOARD UTENTE -->
        <div class="naval-egt-dashboard">
            <!-- Header Dashboard -->
            <div class="dashboard-header">
                <div class="user-welcome">
                    <h1>Benvenuto, <?php echo esc_html($current_user['nome'] . ' ' . $current_user['cognome']); ?></h1>
                    <p>Codice Utente: <strong><?php echo esc_html($current_user['user_code']); ?></strong></p>
                </div>
                <div class="user-actions">
                    <button type="button" class="btn-secondary" id="refresh-data">
                        <span class="dashicons dashicons-update"></span> Aggiorna
                    </button>
                    <button type="button" class="btn-logout" id="logout-btn">
                        <span class="dashicons dashicons-exit"></span> Logout
                    </button>
                </div>
            </div>

            <!-- Statistiche Utente -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-media-default"></span>
                    </div>
                    <div class="stat-content">
                        <h3 id="total-files-count">0</h3>
                        <p>File Totali</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-chart-pie"></span>
                    </div>
                    <div class="stat-content">
                        <h3 id="total-storage-size">0 MB</h3>
                        <p>Spazio Utilizzato</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-upload"></span>
                    </div>
                    <div class="stat-content">
                        <h3 id="last-upload-date">Mai</h3>
                        <p>Ultimo Upload</p>
                    </div>
                </div>
            </div>

            <!-- Tabs Dashboard -->
            <div class="dashboard-tabs">
                <button type="button" class="dashboard-tab active" data-tab="files">
                    <span class="dashicons dashicons-media-default"></span> I Miei File
                </button>
                <button type="button" class="dashboard-tab" data-tab="upload">
                    <span class="dashicons dashicons-upload"></span> Carica File
                </button>
                <button type="button" class="dashboard-tab" data-tab="activity">
                    <span class="dashicons dashicons-clock"></span> Attività
                </button>
                <button type="button" class="dashboard-tab" data-tab="profile">
                    <span class="dashicons dashicons-admin-users"></span> Profilo
                </button>
            </div>

            <!-- TAB FILES -->
            <div id="tab-files" class="dashboard-tab-content active">
                <div class="tab-header">
                    <h3>I Miei File</h3>
                    <div class="tab-actions">
                        <div class="search-box">
                            <input type="text" id="files-search" placeholder="Cerca file...">
                            <span class="dashicons dashicons-search"></span>
                        </div>
                    </div>
                </div>

                <div class="files-container">
                    <div id="files-list" class="files-grid">
                        <!-- File popolati via AJAX -->
                    </div>
                    
                    <div id="files-pagination" class="pagination-container">
                        <!-- Paginazione popolata via AJAX -->
                    </div>
                </div>

                <div id="no-files-message" class="empty-state" style="display: none;">
                    <span class="dashicons dashicons-media-default"></span>
                    <h4>Nessun file trovato</h4>
                    <p>Non hai ancora caricato nessun file. Usa la sezione "Carica File" per iniziare.</p>
                </div>
            </div>

            <!-- TAB UPLOAD -->
            <div id="tab-upload" class="dashboard-tab-content">
                <div class="tab-header">
                    <h3>Carica File</h3>
                </div>

                <div class="upload-section">
                    <form id="files-upload-form" enctype="multipart/form-data">
                        <?php wp_nonce_field('naval_egt_nonce', 'nonce'); ?>
                        <input type="hidden" name="naval_action" value="upload_file">

                        <div class="upload-area" id="upload-drop-zone">
                            <div class="upload-icon">
                                <span class="dashicons dashicons-cloud-upload"></span>
                            </div>
                            <div class="upload-text">
                                <h4>Trascina i file qui o clicca per selezionare</h4>
                                <p>Formati supportati: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, DWG, DXF, ZIP, RAR</p>
                                <small>Dimensione massima: 20MB per file</small>
                            </div>
                            <input type="file" id="files-input" name="files[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.dwg,.dxf,.zip,.rar" style="display: none;">
                        </div>

                        <div id="upload-progress" class="upload-progress" style="display: none;">
                            <div class="progress-bar">
                                <div class="progress-fill"></div>
                            </div>
                            <div class="progress-text">
                                <span id="progress-message">Caricamento in corso...</span>
                                <span id="progress-percentage">0%</span>
                            </div>
                        </div>

                        <div id="selected-files" class="selected-files"></div>

                        <button type="submit" id="upload-submit" class="btn-primary" style="display: none;">
                            <span class="dashicons dashicons-upload"></span> Carica File Selezionati
                        </button>
                    </form>
                </div>

                <div class="upload-info">
                    <h4>📋 Informazioni Upload</h4>
                    <ul>
                        <li><strong>File multipli:</strong> Puoi selezionare e caricare più file contemporaneamente</li>
                        <li><strong>Dimensione massima:</strong> 20MB per singolo file</li>
                        <li><strong>Formati supportati:</strong> Documenti, immagini, file CAD e archivi</li>
                        <li><strong>Sicurezza:</strong> Tutti i file sono archiviati in modo sicuro e privato</li>
                    </ul>
                </div>
            </div>

            <!-- TAB ACTIVITY -->
            <div id="tab-activity" class="dashboard-tab-content">
                <div class="tab-header">
                    <h3>Storico Attività</h3>
                    <div class="tab-actions">
                        <button type="button" class="btn-secondary" id="refresh-activity">
                            <span class="dashicons dashicons-update"></span> Aggiorna
                        </button>
                    </div>
                </div>

                <div class="activity-container">
                    <div id="activity-list" class="activity-timeline">
                        <!-- Attività popolate via AJAX -->
                    </div>
                    
                    <div id="activity-pagination" class="pagination-container">
                        <!-- Paginazione attività -->
                    </div>
                </div>

                <div id="no-activity-message" class="empty-state" style="display: none;">
                    <span class="dashicons dashicons-clock"></span>
                    <h4>Nessuna attività registrata</h4>
                    <p>Le tue attività verranno mostrate qui quando inizierai ad utilizzare l'area riservata.</p>
                </div>
            </div>

            <!-- TAB PROFILE -->
            <div id="tab-profile" class="dashboard-tab-content">
                <div class="tab-header">
                    <h3>Il Mio Profilo</h3>
                </div>

                <div class="profile-section">
                    <div class="profile-card">
                        <div class="profile-avatar">
                            <span class="dashicons dashicons-admin-users"></span>
                        </div>
                        <div class="profile-info">
                            <h4><?php echo esc_html($current_user['nome'] . ' ' . $current_user['cognome']); ?></h4>
                            <p class="profile-email"><?php echo esc_html($current_user['email']); ?></p>
                            <p class="profile-code">Codice: <strong><?php echo esc_html($current_user['user_code']); ?></strong></p>
                        </div>
                    </div>

                    <div class="profile-details">
                        <div class="detail-row">
                            <label>Username:</label>
                            <span><?php echo esc_html($current_user['username']); ?></span>
                        </div>
                        <?php if (!empty($current_user['telefono'])): ?>
                        <div class="detail-row">
                            <label>Telefono:</label>
                            <span><?php echo esc_html($current_user['telefono']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($current_user['ragione_sociale'])): ?>
                        <div class="detail-row">
                            <label>Azienda:</label>
                            <span><?php echo esc_html($current_user['ragione_sociale']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($current_user['partita_iva'])): ?>
                        <div class="detail-row">
                            <label>Partita IVA:</label>
                            <span><?php echo esc_html($current_user['partita_iva']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="detail-row">
                            <label>Status Account:</label>
                            <span class="status-badge status-<?php echo strtolower($current_user['status']); ?>">
                                <?php echo esc_html($current_user['status']); ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <label>Registrato il:</label>
                            <span><?php echo mysql2date('d/m/Y', $current_user['created_at']); ?></span>
                        </div>
                    </div>

                    <div class="profile-actions">
                        <p><strong>Serve assistenza?</strong></p>
                        <p>Per modifiche ai dati del profilo o supporto tecnico, contatta:</p>
                        <p><a href="mailto:tecnica@naval.it" class="btn-secondary">
                            <span class="dashicons dashicons-email"></span> tecnica@naval.it
                        </a></p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Modal per visualizzazione file -->
    <div id="file-modal" class="naval-modal" style="display: none;">
        <div class="modal-overlay"></div>
        <div class="modal-container">
            <div class="modal-header">
                <h3 id="modal-file-title">Dettagli File</h3>
                <button type="button" class="modal-close">×</button>
            </div>
            <div class="modal-body">
                <div id="file-details" class="file-details">
                    <!-- Dettagli file popolati via JS -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-primary" id="modal-download-btn">
                    <span class="dashicons dashicons-download"></span> Scarica
                </button>
                <button type="button" class="btn-danger" id="modal-delete-btn">
                    <span class="dashicons dashicons-trash"></span> Elimina
                </button>
                <button type="button" class="btn-secondary modal-close">Chiudi</button>
            </div>
        </div>
    </div>

    <!-- Toast notifications -->
    <div id="toast-container" class="toast-container"></div>
</div>

<style>
/* Stili CSS per l'Area Riservata */
.naval-egt-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

/* === SEZIONE AUTENTICAZIONE === */
.naval-egt-auth-section {
    max-width: 500px;
    margin: 0 auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    overflow: hidden;
}

.auth-tabs {
    display: flex;
    background: #f8f9fa;
}

.auth-tab {
    flex: 1;
    padding: 15px 20px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-weight: 600;
    color: #666;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s;
}

.auth-tab.active {
    background: #fff;
    color: #0073aa;
    border-bottom: 3px solid #0073aa;
}

.auth-tab:hover:not(.active) {
    background: #e9ecef;
}

.auth-tab-content {
    display: none;
    padding: 0;
}

.auth-tab-content.active {
    display: block;
}

.auth-card {
    padding: 40px;
}

.auth-header {
    text-align: center;
    margin-bottom: 30px;
}

.auth-header h2 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 24px;
}

.auth-header p {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.auth-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-weight: 600;
    color: #333;
    font-size: 14px;
}

.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon {
    position: absolute;
    left: 12px;
    color: #666;
    z-index: 2;
}

.form-group input {
    padding: 12px 12px 12px 40px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s;
    width: 100%;
    box-sizing: border-box;
}

.form-group input:focus {
    outline: none;
    border-color: #0073aa;
    box-shadow: 0 0 0 3px rgba(0,115,170,0.1);
}

.toggle-password {
    position: absolute;
    right: 12px;
    background: none;
    border: none;
    color: #666;
    cursor: pointer;
    padding: 0;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 12px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: #333;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    cursor: pointer;
}

.checkmark {
    position: relative;
    height: 18px;
    width: 18px;
    background-color: #eee;
    border-radius: 4px;
    border: 2px solid #ddd;
    transition: all 0.3s;
}

.checkbox-label input:checked ~ .checkmark {
    background-color: #0073aa;
    border-color: #0073aa;
}

.checkmark:after {
    content: "";
    position: absolute;
    display: none;
    left: 5px;
    top: 2px;
    width: 4px;
    height: 8px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

.checkbox-label input:checked ~ .checkmark:after {
    display: block;
}

.btn-primary {
    background: linear-gradient(135deg, #0073aa, #005a87);
    color: white;
    border: none;
    padding: 15px 30px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 16px;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,115,170,0.3);
}

.btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.btn-full-width {
    width: 100%;
}

.btn-loading {
    display: flex;
    align-items: center;
    gap: 8px;
}

.spinner {
    width: 16px;
    height: 16px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.auth-footer {
    text-align: center;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.auth-footer p {
    margin: 0;
    font-size: 14px;
    color: #666;
}

.auth-footer a {
    color: #0073aa;
    text-decoration: none;
}

.auth-footer a:hover {
    text-decoration: underline;
}

.form-section-title {
    margin: 20px 0 10px 0;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.form-section-title h4 {
    margin: 0;
    color: #333;
    font-size: 16px;
}

.form-group small {
    color: #666;
    font-size: 13px;
    margin-top: -5px;
}

/* === DASHBOARD UTENTE === */
.naval-egt-dashboard {
    background: #f8f9fa;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

.dashboard-header {
    background: linear-gradient(135deg, #0073aa, #005a87);
    color: white;
    padding: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.user-welcome h1 {
    margin: 0 0 8px 0;
    font-size: 28px;
    font-weight: 700;
}

.user-welcome p {
    margin: 0;
    opacity: 0.9;
    font-size: 16px;
}

.user-actions {
    display: flex;
    gap: 12px;
}

.btn-secondary {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.3);
    padding: 10px 16px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
}

.btn-secondary:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-1px);
}

.btn-logout {
    background: rgba(220,50,50,0.8);
    color: white;
    border: 1px solid rgba(220,50,50,0.5);
    padding: 10px 16px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-logout:hover {
    background: rgba(220,50,50,1);
    transform: translateY(-1px);
}

.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    padding: 30px;
    background: white;
}

.stat-card {
    background: #fff;
    border: 1px solid #e1e5e9;
    border-radius: 12px;
    padding: 25px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.stat-icon {
    font-size: 32px;
    color: #0073aa;
    opacity: 0.8;
}

.stat-content h3 {
    margin: 0 0 5px 0;
    font-size: 24px;
    font-weight: 700;
    color: #333;
}

.stat-content p {
    margin: 0;
    color: #666;
    font-size: 14px;
    font-weight: 500;
}

.dashboard-tabs {
    display: flex;
    background: #e9ecef;
    padding: 0;
    margin: 0;
    border-top: 1px solid #dee2e6;
}

.dashboard-tab {
    flex: 1;
    padding: 18px 20px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-weight: 600;
    color: #666;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s;
    font-size: 14px;
}

.dashboard-tab.active {
    background: #fff;
    color: #0073aa;
    border-bottom: 3px solid #0073aa;
}

.dashboard-tab:hover:not(.active) {
    background: #dee2e6;
    color: #333;
}

.dashboard-tab-content {
    display: none;
    background: white;
    padding: 30px;
    min-height: 500px;
}

.dashboard-tab-content.active {
    display: block;
}

.tab-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f1f3f4;
}

.tab-header h3 {
    margin: 0;
    color: #333;
    font-size: 20px;
}

.tab-actions {
    display: flex;
    gap: 15px;
    align-items: center;
}

.search-box {
    position: relative;
    display: flex;
    align-items: center;
}

.search-box input {
    padding: 10px 40px 10px 12px;
    border: 2px solid #e1e5e9;
    border-radius: 6px;
    font-size: 14px;
    width: 250px;
}

.search-box .dashicons {
    position: absolute;
    right: 12px;
    color: #666;
}

/* === FILES SECTION === */
.files-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.file-card {
    background: #fff;
    border: 2px solid #f1f3f4;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s;
    cursor: pointer;
}

.file-card:hover {
    border-color: #0073aa;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.file-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
}

.file-icon {
    font-size: 24px;
    color: #0073aa;
}

.file-name {
    font-weight: 600;
    color: #333;
    font-size: 16px;
    word-break: break-word;
}

.file-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: #666;
}

.file-actions {
    display: flex;
    gap: 8px;
    margin-top: 15px;
}

.btn-small {
    padding: 6px 12px;
    font-size: 12px;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s;
}

.btn-download {
    background: #28a745;
    color: white;
}

.btn-download:hover {
    background: #218838;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover {
    background: #c82333;
}

/* === UPLOAD SECTION === */
.upload-area {
    border: 3px dashed #dee2e6;
    border-radius: 12px;
    padding: 60px 30px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    background: #f8f9fa;
}

.upload-area:hover,
.upload-area.drag-over {
    border-color: #0073aa;
    background: rgba(0,115,170,0.05);
}

.upload-icon {
    font-size: 48px;
    color: #0073aa;
    margin-bottom: 20px;
}

.upload-text h4 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 18px;
}

.upload-text p {
    margin: 0 0 5px 0;
    color: #666;
}

.upload-text small {
    color: #999;
    font-size: 13px;
}

.upload-progress {
    margin: 20px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 10px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #0073aa, #005a87);
    width: 0%;
    transition: width 0.3s;
}

.progress-text {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    color: #666;
}

.selected-files {
    margin: 20px 0;
}

.selected-file {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
    margin-bottom: 8px;
}

.file-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.remove-file {
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 4px 8px;
    cursor: pointer;
    font-size: 12px;
}

.upload-info {
    margin-top: 30px;
    padding: 20px;
    background: #e7f3ff;
    border-left: 4px solid #0073aa;
    border-radius: 6px;
}

.upload-info h4 {
    margin: 0 0 15px 0;
    color: #333;
}

.upload-info ul {
    margin: 0;
    padding-left: 20px;
}

.upload-info li {
    margin-bottom: 8px;
    color: #666;
}

/* === ACTIVITY SECTION === */
.activity-timeline {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.activity-item {
    background: #fff;
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.activity-icon {
    font-size: 20px;
    color: #0073aa;
    background: rgba(0,115,170,0.1);
    padding: 12px;
    border-radius: 50%;
}

.activity-content {
    flex: 1;
}

.activity-title {
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
}

.activity-description {
    color: #666;
    font-size: 14px;
    margin-bottom: 5px;
}

.activity-meta {
    font-size: 13px;
    color: #999;
}

/* === PROFILE SECTION === */
.profile-section {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 30px;
    align-items: start;
}

.profile-card {
    background: #fff;
    border: 1px solid #e1e5e9;
    border-radius: 12px;
    padding: 30px;
    text-align: center;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #0073aa, #005a87);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px auto;
    font-size: 32px;
    color: white;
}

.profile-card h4 {
    margin: 0 0 8px 0;
    color: #333;
    font-size: 18px;
}

.profile-email {
    color: #666;
    margin: 0 0 8px 0;
    font-size: 14px;
}

.profile-code {
    color: #0073aa;
    margin: 0;
    font-size: 14px;
    font-weight: 600;
}

.profile-details {
    background: #fff;
    border: 1px solid #e1e5e9;
    border-radius: 12px;
    padding: 30px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f1f3f4;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row label {
    font-weight: 600;
    color: #333;
}

.detail-row span {
    color: #666;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-attivo {
    background: #d4edda;
    color: #155724;
}

.status-sospeso {
    background: #f8d7da;
    color: #721c24;
}

.profile-actions {
    margin-top: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.profile-actions p {
    margin: 0 0 10px 0;
    color: #666;
}

/* === MODAL === */
.naval-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}

.modal-container {
    background: white;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow: hidden;
    position: relative;
    z-index: 10000;
}

.modal-header {
    padding: 20px 30px;
    border-bottom: 1px solid #e1e5e9;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: #333;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-body {
    padding: 30px;
    max-height: 60vh;
    overflow-y: auto;
}

.modal-footer {
    padding: 20px 30px;
    border-top: 1px solid #e1e5e9;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.file-details {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.file-detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f1f3f4;
}

.file-detail-row:last-child {
    border-bottom: none;
}

/* === EMPTY STATES === */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.empty-state .dashicons {
    font-size: 64px;
    opacity: 0.3;
    margin-bottom: 20px;
}

.empty-state h4 {
    margin: 0 0 10px 0;
    color: #333;
}

.empty-state p {
    margin: 0;
    max-width: 400px;
    margin: 0 auto;
}

/* === PAGINATION === */
.pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-top: 30px;
}

.pagination-btn {
    padding: 8px 12px;
    border: 1px solid #dee2e6;
    background: white;
    color: #333;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
}

.pagination-btn:hover:not(:disabled) {
    background: #0073aa;
    color: white;
    border-color: #0073aa;
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-btn.active {
    background: #0073aa;
    color: white;
    border-color: #0073aa;
}

/* === TOAST NOTIFICATIONS === */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.toast {
    min-width: 300px;
    padding: 15px 20px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    animation: slideIn 0.3s ease-out;
    position: relative;
    display: flex;
    align-items: center;
    gap: 10px;
}

.toast-success {
    background: #28a745;
}

.toast-error {
    background: #dc3545;
}

.toast-info {
    background: #17a2b8;
}

.toast-warning {
    background: #ffc107;
    color: #333;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* === RESPONSIVE === */
@media (max-width: 768px) {
    .naval-egt-container {
        padding: 10px;
    }
    
    .dashboard-header {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .dashboard-stats {
        grid-template-columns: 1fr;
        padding: 20px;
    }
    
    .dashboard-tabs {
        flex-wrap: wrap;
    }
    
    .dashboard-tab {
        flex: 1 1 50%;
        min-width: 120px;
    }
    
    .tab-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .search-box input {
        width: 100%;
    }
    
    .files-grid {
        grid-template-columns: 1fr;
    }
    
    .profile-section {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .auth-card {
        padding: 20px;
    }
    
    .modal-container {
        width: 95%;
        margin: 20px;
    }
    
    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 15px 20px;
    }
}

@media (max-width: 480px) {
    .user-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .dashboard-tab {
        flex: 1 1 100%;
        font-size: 12px;
        padding: 12px 8px;
    }
    
    .toast-container {
        left: 10px;
        right: 10px;
        top: 10px;
    }
    
    .toast {
        min-width: auto;
    }
}
</style>

<script>
// JavaScript per l'Area Riservata
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('naval-egt-area-riservata');
    if (!container) return;
    
    // Variabili globali
    let currentPage = 1;
    let currentActivityPage = 1;
    let isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
    
    // Inizializzazione
    if (isLoggedIn) {
        initDashboard();
    } else {
        initAuth();
    }
    
    // === FUNZIONI AUTENTICAZIONE ===
    function initAuth() {
        // Gestione tab autenticazione
        const authTabs = document.querySelectorAll('.auth-tab');
        const authContents = document.querySelectorAll('.auth-tab-content');
        
        authTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetTab = tab.dataset.tab;
                
                authTabs.forEach(t => t.classList.remove('active'));
                authContents.forEach(c => c.classList.remove('active'));
                
                tab.classList.add('active');
                document.getElementById(`tab-${targetTab}`).classList.add('active');
            });
        });
        
        // Gestione toggle password
        const toggleButtons = document.querySelectorAll('.toggle-password');
        toggleButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const input = btn.previousElementSibling;
                const icon = btn.querySelector('.dashicons');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('dashicons-visibility');
                    icon.classList.add('dashicons-hidden');
                } else {
                    input.type = 'password';
                    icon.classList.remove('dashicons-hidden');
                    icon.classList.add('dashicons-visibility');
                }
            });
        });
        
        // Gestione form login
        const loginForm = document.getElementById('naval-login-form');
        if (loginForm) {
            loginForm.addEventListener('submit', handleLogin);
        }
        
        // Gestione form registrazione
        const registerForm = document.getElementById('naval-register-form');
        if (registerForm) {
            registerForm.addEventListener('submit', handleRegister);
        }
    }
    
    async function handleLogin(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        
        try {
            // Mostra loading
            btnText.style.display = 'none';
            btnLoading.style.display = 'flex';
            submitBtn.disabled = true;
            
            const formData = new FormData(form);
            formData.append('action', 'naval_egt_login');
            
            const response = await fetch(navalEgtAjax.ajax_url, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast('Login effettuato con successo!', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showToast(result.data || 'Errore durante il login', 'error');
            }
        } catch (error) {
            showToast('Errore di connessione', 'error');
        } finally {
            // Nascondi loading
            btnText.style.display = 'block';
            btnLoading.style.display = 'none';
            submitBtn.disabled = false;
        }
    }
    
    async function handleRegister(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        
        try {
            // Validazioni client-side
            const password = form.querySelector('[name="password"]').value;
            const passwordConfirm = form.querySelector('[name="password_confirm"]').value;
            
            if (password !== passwordConfirm) {
                showToast('Le password non corrispondono', 'error');
                return;
            }
            
            if (password.length < 6) {
                showToast('La password deve essere di almeno 6 caratteri', 'error');
                return;
            }
            
            const ragioneSociale = form.querySelector('[name="ragione_sociale"]').value;
            const partitaIva = form.querySelector('[name="partita_iva"]').value;
            
            if (ragioneSociale && !partitaIva) {
                showToast('La Partita IVA è obbligatoria se specifichi la Ragione Sociale', 'error');
                return;
            }
            
            // Mostra loading
            btnText.style.display = 'none';
            btnLoading.style.display = 'flex';
            submitBtn.disabled = true;
            
            const formData = new FormData(form);
            formData.append('action', 'naval_egt_register');
            
            const response = await fetch(navalEgtAjax.ajax_url, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast(result.data.message, 'success');
                form.reset();
                
                // Torna al tab login dopo 2 secondi
                setTimeout(() => {
                    document.querySelector('.auth-tab[data-tab="login"]').click();
                }, 2000);
            } else {
                showToast(result.data || 'Errore durante la registrazione', 'error');
            }
        } catch (error) {
            showToast('Errore di connessione', 'error');
        } finally {
            // Nascondi loading
            btnText.style.display = 'block';
            btnLoading.style.display = 'none';
            submitBtn.disabled = false;
        }
    }
    
    // === FUNZIONI DASHBOARD ===
    function initDashboard() {
        // Gestione tab dashboard
        const dashboardTabs = document.querySelectorAll('.dashboard-tab');
        const dashboardContents = document.querySelectorAll('.dashboard-tab-content');
        
        dashboardTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetTab = tab.dataset.tab;
                
                dashboardTabs.forEach(t => t.classList.remove('active'));
                dashboardContents.forEach(c => c.classList.remove('active'));
                
                tab.classList.add('active');
                document.getElementById(`tab-${targetTab}`).classList.add('active');
                
                // Carica contenuto specifico del tab
                switch(targetTab) {
                    case 'files':
                        loadUserFiles();
                        break;
                    case 'activity':
                        loadUserActivity();
                        break;
                }
            });
        });
        
        // Gestione logout
        const logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', handleLogout);
        }
        
        // Gestione refresh
        const refreshBtn = document.getElementById('refresh-data');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                loadUserStats();
                loadUserFiles();
            });
        }
        
        // Gestione upload
        initFileUpload();
        
        // Gestione ricerca file
        const filesSearch = document.getElementById('files-search');
        if (filesSearch) {
            let searchTimeout;
            filesSearch.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    currentPage = 1;
                    loadUserFiles();
                }, 500);
            });
        }
        
        // Carica dati iniziali
        loadUserStats();
        loadUserFiles();
    }
    
    async function handleLogout() {
        if (!confirm('Sei sicuro di voler uscire?')) return;
        
        try {
            const formData = new FormData();
            formData.append('action', 'naval_egt_logout');
            formData.append('nonce', navalEgtAjax.nonce);
            
            const response = await fetch(navalEgtAjax.ajax_url, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast('Logout effettuato con successo', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        } catch (error) {
            showToast('Errore durante il logout', 'error');
        }
    }
    
    async function loadUserStats() {
        try {
            const formData = new FormData();
            formData.append('action', 'naval_egt_get_user_stats');
            formData.append('nonce', navalEgtAjax.nonce);
            
            const response = await fetch(navalEgtAjax.ajax_url, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                const stats = result.data;
                updateStatsDisplay(stats);
            }
        } catch (error) {
            console.error('Errore caricamento statistiche:', error);
        }
    }
    
    function updateStatsDisplay(stats) {
        const totalFiles = document.getElementById('total-files-count');
        const totalStorage = document.getElementById('total-storage-size');
        const lastUpload = document.getElementById('last-upload-date');
        
        if (totalFiles) totalFiles.textContent = stats.total_files || 0;
        if (totalStorage) totalStorage.textContent = formatFileSize(stats.total_size || 0);
        if (lastUpload) {
            const date = stats.last_upload ? new Date(stats.last_upload).toLocaleDateString('it-IT') : 'Mai';
            lastUpload.textContent = date;
        }
    }
    
    async function loadUserFiles() {
        const filesContainer = document.getElementById('files-list');
        const noFilesMessage = document.getElementById('no-files-message');
        const searchQuery = document.getElementById('files-search')?.value || '';
        
        try {
            const formData = new FormData();
            formData.append('action', 'naval_egt_get_user_files');
            formData.append('nonce', navalEgtAjax.nonce);
            formData.append('page', currentPage);
            formData.append('search', searchQuery);
            
            const response = await fetch(navalEgtAjax.ajax_url, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                const files = result.data.files;
                
                if (files.length === 0) {
                    filesContainer.style.display = 'none';
                    noFilesMessage.style.display = 'block';
                } else {
                    filesContainer.style.display = 'grid';
                    noFilesMessage.style.display = 'none';
                    renderFiles(files);
                }
                
                renderPagination(result.data.pagination, 'files');
            }
        } catch (error) {
            console.error('Errore caricamento file:', error);
            showToast('Errore nel caricamento dei file', 'error');
        }
    }
    
    function renderFiles(files) {
        const container = document.getElementById('files-list');
        
        container.innerHTML = files.map(file => `
            <div class="file-card" data-file-id="${file.id}">
                <div class="file-header">
                    <span class="file-icon dashicons ${getFileIcon(file.name)}"></span>
                    <div class="file-name">${escapeHtml(file.name)}</div>
                </div>
                <div class="file-meta">
                    <span>${file.size}</span>
                    <span>${file.date}</span>
                </div>
                <div class="file-actions">
                    <a href="${file.download_url}" class="btn-small btn-download" target="_blank">
                        <span class="dashicons dashicons-download"></span> Scarica
                    </a>
                    <button type="button" class="btn-small btn-danger delete-file-btn" data-file-id="${file.id}">
                        <span class="dashicons dashicons-trash"></span> Elimina
                    </button>
                </div>
            </div>
        `).join('');
        
        // Aggiungi event listener per eliminazione
        container.querySelectorAll('.delete-file-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const fileId = btn.dataset.fileId;
                deleteFile(fileId);
            });
        });
        
        // Aggiungi event listener per visualizzazione dettagli
        container.querySelectorAll('.file-card').forEach(card => {
            card.addEventListener('click', () => {
                const fileId = card.dataset.fileId;
                showFileModal(fileId);
            });
        });
    }
    
    async function deleteFile(fileId) {
        if (!confirm('Sei sicuro di voler eliminare questo file?')) return;
        
        try {
            const formData = new FormData();
            formData.append('action', 'naval_egt_delete_file');
            formData.append('nonce', navalEgtAjax.nonce);
            formData.append('file_id', fileId);
            
            const response = await fetch(navalEgtAjax.ajax_url, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast('File eliminato con successo', 'success');
                loadUserFiles();
                loadUserStats();
            } else {
                showToast(result.data || 'Errore durante l\'eliminazione', 'error');
            }
        } catch (error) {
            showToast('Errore di connessione', 'error');
        }
    }
    
    async function loadUserActivity() {
        const activityContainer = document.getElementById('activity-list');
        const noActivityMessage = document.getElementById('no-activity-message');
        
        try {
            const formData = new FormData();
            formData.append('action', 'naval_egt_get_user_activity');
            formData.append('nonce', navalEgtAjax.nonce);
            formData.append('page', currentActivityPage);
            
            const response = await fetch(navalEgtAjax.ajax_url, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                const activities = result.data.activities;
                
                if (activities.length === 0) {
                    activityContainer.style.display = 'none';
                    noActivityMessage.style.display = 'block';
                } else {
                    activityContainer.style.display = 'flex';
                    noActivityMessage.style.display = 'none';
                    renderActivity(activities);
                }
            }
        } catch (error) {
            console.error('Errore caricamento attività:', error);
        }
    }
    
    function renderActivity(activities) {
        const container = document.getElementById('activity-list');
        
        container.innerHTML = activities.map(activity => `
            <div class="activity-item">
                <div class="activity-icon">
                    <span class="dashicons ${getActivityIcon(activity.action)}"></span>
                </div>
                <div class="activity-content">
                    <div class="activity-title">${activity.action}</div>
                    <div class="activity-description">${escapeHtml(activity.description)}</div>
                    <div class="activity-meta">${activity.date} - IP: ${activity.ip_address}</div>
                </div>
            </div>
        `).join('');
    }
    
    // === GESTIONE UPLOAD ===
    function initFileUpload() {
        const dropZone = document.getElementById('upload-drop-zone');
        const fileInput = document.getElementById('files-input');
        const uploadForm = document.getElementById('files-upload-form');
        const selectedFilesContainer = document.getElementById('selected-files');
        const uploadSubmit = document.getElementById('upload-submit');
        
        if (!dropZone || !fileInput || !uploadForm) return;
        
        // Click per aprire file selector
        dropZone.addEventListener('click', () => {
            fileInput.click();
        });
        
        // Drag & Drop
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });
        
        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            
            const files = Array.from(e.dataTransfer.files);
            handleFileSelection(files);
        });
        
        // File input change
        fileInput.addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            handleFileSelection(files);
        });
        
        // Form submit
        uploadForm.addEventListener('submit', handleFileUpload);
    }
    
    let selectedFiles = [];
    
    function handleFileSelection(files) {
        selectedFiles = [...selectedFiles, ...files];
        renderSelectedFiles();
        
        const uploadSubmit = document.getElementById('upload-submit');
        if (selectedFiles.length > 0) {
            uploadSubmit.style.display = 'block';
        }
    }
    
    function renderSelectedFiles() {
        const container = document.getElementById('selected-files');
        
        container.innerHTML = selectedFiles.map((file, index) => `
            <div class="selected-file">
                <div class="file-info">
                    <span class="dashicons ${getFileIcon(file.name)}"></span>
                    <span>${escapeHtml(file.name)} (${formatFileSize(file.size)})</span>
                </div>
                <button type="button" class="remove-file" data-index="${index}">×</button>
            </div>
        `).join('');
        
        // Event listener per rimozione file
        container.querySelectorAll('.remove-file').forEach(btn => {
            btn.addEventListener('click', () => {
                const index = parseInt(btn.dataset.index);
                selectedFiles.splice(index, 1);
                renderSelectedFiles();
                
                if (selectedFiles.length === 0) {
                    document.getElementById('upload-submit').style.display = 'none';
                }
            });
        });
    }
    
    async function handleFileUpload(e) {
        e.preventDefault();
        
        if (selectedFiles.length === 0) {
            showToast('Seleziona almeno un file', 'warning');
            return;
        }
        
        const uploadProgress = document.getElementById('upload-progress');
        const progressFill = uploadProgress.querySelector('.progress-fill');
        const progressMessage = document.getElementById('progress-message');
        const progressPercentage = document.getElementById('progress-percentage');
        const uploadSubmit = document.getElementById('upload-submit');
        
        try {
            uploadProgress.style.display = 'block';
            uploadSubmit.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'naval_egt_upload_file');
            formData.append('nonce', navalEgtAjax.nonce);
            
            selectedFiles.forEach(file => {
                formData.append('files[]', file);
            });
            
            // Simulazione progresso (WordPress non supporta upload progress nativo)
            let progress = 0;
            const progressInterval = setInterval(() => {
                if (progress < 90) {
                    progress += Math.random() * 20;
                    updateProgress(Math.min(progress, 90));
                }
            }, 200);
            
            const response = await fetch(navalEgtAjax.ajax_url, {
                method: 'POST',
                body: formData
            });
            
            clearInterval(progressInterval);
            updateProgress(100);
            
            const result = await response.json();
            
            if (result.success) {
                showToast(result.data.message, 'success');
                
                // Reset form
                selectedFiles = [];
                renderSelectedFiles();
                document.getElementById('files-input').value = '';
                uploadSubmit.style.display = 'none';
                
                // Refresh dati
                loadUserFiles();
                loadUserStats();
                
                // Vai al tab file
                document.querySelector('.dashboard-tab[data-tab="files"]').click();
            } else {
                showToast(result.data || 'Errore durante l\'upload', 'error');
            }
        } catch (error) {
            showToast('Errore di connessione durante l\'upload', 'error');
        } finally {
            setTimeout(() => {
                uploadProgress.style.display = 'none';
                uploadSubmit.disabled = false;
                updateProgress(0);
            }, 1000);
        }
        
        function updateProgress(percent) {
            progressFill.style.width = percent + '%';
            progressPercentage.textContent = Math.round(percent) + '%';
            
            if (percent < 100) {
                progressMessage.textContent = 'Caricamento in corso...';
            } else {
                progressMessage.textContent = 'Upload completato!';
            }
        }
    }
    
    // === UTILITY FUNCTIONS ===
    function getFileIcon(filename) {
        const ext = filename.toLowerCase().split('.').pop();
        const iconMap = {
            'pdf': 'dashicons-pdf',
            'doc': 'dashicons-media-document',
            'docx': 'dashicons-media-document',
            'xls': 'dashicons-media-spreadsheet',
            'xlsx': 'dashicons-media-spreadsheet',
            'jpg': 'dashicons-format-image',
            'jpeg': 'dashicons-format-image',
            'png': 'dashicons-format-image',
            'gif': 'dashicons-format-image',
            'zip': 'dashicons-media-archive',
            'rar': 'dashicons-media-archive',
            'dwg': 'dashicons-admin-tools',
            'dxf': 'dashicons-admin-tools'
        };
        return iconMap[ext] || 'dashicons-media-default';
    }
    
    function getActivityIcon(action) {
        const iconMap = {
            'Accesso': 'dashicons-unlock',
            'Disconnessione': 'dashicons-lock',
            'Caricamento': 'dashicons-upload',
            'Scaricamento': 'dashicons-download',
            'Eliminazione': 'dashicons-trash',
            'Registrazione': 'dashicons-plus'
        };
        return iconMap[action] || 'dashicons-admin-generic';
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }
    
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
    
    function showToast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icon = {
            'success': 'dashicons-yes-alt',
            'error': 'dashicons-dismiss',
            'warning': 'dashicons-warning',
            'info': 'dashicons-info'
        }[type] || 'dashicons-info';
        
        toast.innerHTML = `
            <span class="dashicons ${icon}"></span>
            <span>${escapeHtml(message)}</span>
        `;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 5000);
    }
    
    function renderPagination(pagination, type) {
        // Implementa paginazione se necessario
        // Per ora placeholder
    }
    
    async function showFileModal(fileId) {
        // Implementa modal dettagli file
        // Per ora placeholder
        console.log('Show file modal for:', fileId);
    }
});

// Variabili globali per AJAX (devono essere definite dal PHP)
if (typeof navalEgtAjax === 'undefined') {
    window.navalEgtAjax = {
        ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>',
        nonce: '<?php echo wp_create_nonce("naval_egt_nonce"); ?>'
    };
}
</script>