<?php
/**
 * Naval EGT Shortcodes Class
 * 
 * Handles all shortcodes for the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class NavalEGT_Shortcodes {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Register shortcodes
        add_shortcode('naval_egt_login_form', array($this, 'login_form_shortcode'));
        add_shortcode('naval_egt_register_form', array($this, 'register_form_shortcode'));
        add_shortcode('naval_egt_dashboard', array($this, 'dashboard_shortcode'));
        add_shortcode('naval_egt_ftp_manager', array($this, 'ftp_manager_shortcode'));
        add_shortcode('naval_egt_user_profile', array($this, 'user_profile_shortcode'));
        
        // Start session for notices
        if (!session_id()) {
            session_start();
        }
    }
    
    public function login_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'redirect_to' => home_url('/dashboard-clienti/'),
            'show_register_link' => 'true',
            'show_forgot_password' => 'true'
        ), $atts);
        
        // If user is already logged in and has access, redirect to dashboard
        if (is_user_logged_in() && naval_egt_user_can_access()) {
            wp_redirect($atts['redirect_to']);
            exit;
        }
        
        ob_start();
        
        // Display notices
        $this->display_notices();
        
        // Check for activation success
        if (isset($_GET['activated']) && $_GET['activated'] == '1') {
            echo '<div class="naval-egt-notice naval-egt-notice-success">';
            echo '<p>' . __('Account attivato con successo! Ora puoi effettuare il login.', 'naval-egt') . '</p>';
            echo '</div>';
        }
        ?>
        
        <div class="naval-egt-area-riservata">
            <div class="naval-egt-header">
                <h2><?php _e('AREA RISERVATA', 'naval-egt'); ?></h2>
                <p><?php _e('Accedi o registrati per utilizzare l\'area riservata dedicata ai clienti Naval EGT: potrai consultare documentazione tecnica, visualizzare i progetti condivisi e utilizzare la cartella FTP dedicata allo scambio di file.', 'naval-egt'); ?></p>
            </div>
            
            <div class="naval-egt-login-section">
                <h3><?php _e('Se hai già un account', 'naval-egt'); ?></h3>
                
                <form method="post" action="<?php echo esc_url(add_query_arg('naval_action', 'login')); ?>" class="naval-egt-login-form">
                    <?php wp_nonce_field('naval_egt_login', 'naval_egt_nonce'); ?>
                    
                    <div class="naval-egt-form-group">
                        <label for="username"><?php _e('Email o nome utente', 'naval-egt'); ?></label>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo esc_attr($_POST['username'] ?? ''); ?>" />
                    </div>
                    
                    <div class="naval-egt-form-group">
                        <label for="password"><?php _e('Password', 'naval-egt'); ?></label>
                        <input type="password" id="password" name="password" required />
                        <?php if ($atts['show_forgot_password'] === 'true'): ?>
                        <div class="naval-egt-forgot-password">
                            <a href="#" onclick="showPasswordReset()"><?php _e('Password dimenticata? Clicca qui per reimpostarla', 'naval-egt'); ?></a>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="naval-egt-form-group">
                        <label class="naval-egt-checkbox">
                            <input type="checkbox" name="remember" value="1" />
                            <span><?php _e('Ricordami', 'naval-egt'); ?></span>
                        </label>
                    </div>
                    
                    <div class="naval-egt-form-group">
                        <button type="submit" class="naval-egt-btn naval-egt-btn-primary">
                            <?php _e('Accedi all\'area riservata', 'naval-egt'); ?>
                        </button>
                    </div>
                </form>
            </div>
            
            <?php if ($atts['show_register_link'] === 'true'): ?>
            <div class="naval-egt-register-section">
                <h3><?php _e('Prima volta qui?', 'naval-egt'); ?></h3>
                <p><?php _e('Registrati per creare il tuo account e accedere all\'area riservata.', 'naval-egt'); ?></p>
                <a href="#" onclick="showRegistrationForm()" class="naval-egt-btn naval-egt-btn-secondary">
                    <?php _e('Registrati ora', 'naval-egt'); ?>
                </a>
            </div>
            <?php endif; ?>
            
            <div class="naval-egt-support-info">
                <p><?php _e('Per assistenza tecnica o problemi di accesso, scrivi a:', 'naval-egt'); ?> 
                   <a href="mailto:technical@navalegt.it">technical@navalegt.it</a></p>
            </div>
            
            <!-- Password Reset Form (Hidden by default) -->
            <div id="naval-egt-password-reset" class="naval-egt-modal" style="display: none;">
                <div class="naval-egt-modal-content">
                    <span class="naval-egt-close" onclick="hidePasswordReset()">&times;</span>
                    <h3><?php _e('Reimposta Password', 'naval-egt'); ?></h3>
                    <form method="post" action="<?php echo esc_url(add_query_arg('naval_action', 'reset_password')); ?>">
                        <?php wp_nonce_field('naval_egt_reset', 'naval_egt_nonce'); ?>
                        <div class="naval-egt-form-group">
                            <label for="reset_email"><?php _e('Indirizzo email', 'naval-egt'); ?></label>
                            <input type="email" id="reset_email" name="email" required />
                        </div>
                        <div class="naval-egt-form-group">
                            <button type="submit" class="naval-egt-btn naval-egt-btn-primary">
                                <?php _e('Invia email di reset', 'naval-egt'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Registration Form (Hidden by default) -->
            <?php if ($atts['show_register_link'] === 'true'): ?>
            <div id="naval-egt-registration" class="naval-egt-modal" style="display: none;">
                <div class="naval-egt-modal-content">
                    <span class="naval-egt-close" onclick="hideRegistrationForm()">&times;</span>
                    <h3><?php _e('Registrazione', 'naval-egt'); ?></h3>
                    <form method="post" action="<?php echo esc_url(add_query_arg('naval_action', 'register')); ?>" class="naval-egt-register-form">
                        <?php wp_nonce_field('naval_egt_register', 'naval_egt_nonce'); ?>
                        
                        <div class="naval-egt-form-row">
                            <div class="naval-egt-form-group">
                                <label for="first_name"><?php _e('Nome *', 'naval-egt'); ?></label>
                                <input type="text" id="first_name" name="first_name" required 
                                       value="<?php echo esc_attr($_POST['first_name'] ?? ''); ?>" />
                            </div>
                            <div class="naval-egt-form-group">
                                <label for="last_name"><?php _e('Cognome *', 'naval-egt'); ?></label>
                                <input type="text" id="last_name" name="last_name" required 
                                       value="<?php echo esc_attr($_POST['last_name'] ?? ''); ?>" />
                            </div>
                        </div>
                        
                        <div class="naval-egt-form-group">
                            <label for="email"><?php _e('Email *', 'naval-egt'); ?></label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo esc_attr($_POST['email'] ?? ''); ?>" />
                        </div>
                        
                        <div class="naval-egt-form-group">
                            <label for="company"><?php _e('Azienda *', 'naval-egt'); ?></label>
                            <input type="text" id="company" name="company" required 
                                   value="<?php echo esc_attr($_POST['company'] ?? ''); ?>" />
                        </div>
                        
                        <div class="naval-egt-form-group">
                            <label for="phone"><?php _e('Telefono', 'naval-egt'); ?></label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo esc_attr($_POST['phone'] ?? ''); ?>" />
                        </div>
                        
                        <div class="naval-egt-form-group">
                            <button type="submit" class="naval-egt-btn naval-egt-btn-primary">
                                <?php _e('Registrati', 'naval-egt'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Instructions Section -->
            <div class="naval-egt-instructions">
                <h3><?php _e('Istruzioni operative', 'naval-egt'); ?></h3>
                
                <div class="naval-egt-instruction-block">
                    <h4><?php _e('Registrazione e accesso all\'area riservata', 'naval-egt'); ?></h4>
                    <p><?php _e('Per registrarti, riceverai via email un link di attivazione che ti permetterà di creare il tuo nome utente e impostare una password personale. Una volta registrato, potrai accedere all\'area riservata in qualsiasi momento utilizzando il modulo di login presente sul sito.', 'naval-egt'); ?></p>
                    <p><?php _e('Per i successivi accessi, sarà sufficiente inserire le tue credenziali (nome utente o email e password). In caso di smarrimento della password, utilizza la funzione "Hai dimenticato la password?" per reimpostarla in autonomia.', 'naval-egt'); ?></p>
                </div>
                
                <div class="naval-egt-instruction-block">
                    <h4><?php _e('Scambio di file tramite cartella FTP', 'naval-egt'); ?></h4>
                    <p><?php _e('L\'area riservata contiene una cartella FTP dedicata allo scambio di file e documenti tecnici tra Naval EGT e i clienti. Potrai caricare o scaricare materiali in modo sicuro e tracciato.', 'naval-egt'); ?></p>
                    <p><?php _e('Per assistenza tecnica o problemi di accesso, contatta il nostro supporto all\'indirizzo:', 'naval-egt'); ?> 
                       <a href="mailto:technical@navalegt.it">technical@navalegt.it</a></p>
                </div>
            </div>
        </div>
        
        <script>
        function showPasswordReset() {
            document.getElementById('naval-egt-password-reset').style.display = 'block';
        }
        
        function hidePasswordReset() {
            document.getElementById('naval-egt-password-reset').style.display = 'none';
        }
        
        function showRegistrationForm() {
            document.getElementById('naval-egt-registration').style.display = 'block';
        }
        
        function hideRegistrationForm() {
            document.getElementById('naval-egt-registration').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            var resetModal = document.getElementById('naval-egt-password-reset');
            var registerModal = document.getElementById('naval-egt-registration');
            
            if (event.target == resetModal) {
                resetModal.style.display = 'none';
            }
            if (event.target == registerModal) {
                registerModal.style.display = 'none';
            }
        }
        </script>
        
        <?php
        return ob_get_clean();
    }
    
    public function register_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'redirect_to' => home_url('/area-riservata/')
        ), $atts);
        
        if (is_user_logged_in()) {
            return '<p>' . __('Sei già registrato e loggato.', 'naval-egt') . '</p>';
        }
        
        ob_start();
        
        $this->display_notices();
        ?>
        
        <div class="naval-egt-registration-form">
            <h3><?php _e('Registrazione Area Riservata', 'naval-egt'); ?></h3>
            
            <form method="post" action="<?php echo esc_url(add_query_arg('naval_action', 'register')); ?>">
                <?php wp_nonce_field('naval_egt_register', 'naval_egt_nonce'); ?>
                
                <div class="naval-egt-form-row">
                    <div class="naval-egt-form-group">
                        <label for="first_name"><?php _e('Nome *', 'naval-egt'); ?></label>
                        <input type="text" id="first_name" name="first_name" required />
                    </div>
                    <div class="naval-egt-form-group">
                        <label for="last_name"><?php _e('Cognome *', 'naval-egt'); ?></label>
                        <input type="text" id="last_name" name="last_name" required />
                    </div>
                </div>
                
                <div class="naval-egt-form-group">
                    <label for="email"><?php _e('Email *', 'naval-egt'); ?></label>
                    <input type="email" id="email" name="email" required />
                </div>
                
                <div class="naval-egt-form-group">
                    <label for="company"><?php _e('Azienda *', 'naval-egt'); ?></label>
                    <input type="text" id="company" name="company" required />
                </div>
                
                <div class="naval-egt-form-group">
                    <label for="phone"><?php _e('Telefono', 'naval-egt'); ?></label>
                    <input type="tel" id="phone" name="phone" />
                </div>
                
                <div class="naval-egt-form-group">
                    <button type="submit" class="naval-egt-btn naval-egt-btn-primary">
                        <?php _e('Registrati', 'naval-egt'); ?>
                    </button>
                </div>
            </form>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    public function dashboard_shortcode($atts) {
        if (!is_user_logged_in() || !naval_egt_user_can_access()) {
            return '<p>' . __('Devi effettuare il login per accedere a questa area.', 'naval-egt') . ' <a href="' . home_url('/area-riservata/') . '">' . __('Effettua il login', 'naval-egt') . '</a></p>';
        }
        
        $atts = shortcode_atts(array(
            'show_profile' => 'true',
            'show_ftp' => 'true',
            'show_stats' => 'true'
        ), $atts);
        
        $user_data = naval_egt_get_user_data();
        $ftp_manager = new NavalEGT_FTPManager();
        $folder_stats = $ftp_manager->get_folder_stats(get_current_user_id());
        $recent_activity = $ftp_manager->get_recent_activity(get_current_user_id(), 5);
        
        ob_start();
        ?>
        
        <div class="naval-egt-dashboard">
            <div class="naval-egt-dashboard-header">
                <h2><?php printf(__('Benvenuto, %s', 'naval-egt'), esc_html($user_data['display_name'])); ?></h2>
                <div class="naval-egt-logout">
                    <a href="<?php echo esc_url(add_query_arg('naval_action', 'logout', home_url('/area-riservata/'))); ?>" 
                       class="naval-egt-btn naval-egt-btn-secondary">
                        <?php _e('Logout', 'naval-egt'); ?>
                    </a>
                </div>
            </div>
            
            <?php if ($atts['show_stats'] === 'true' && $folder_stats): ?>
            <div class="naval-egt-stats-grid">
                <div class="naval-egt-stat-card">
                    <h3><?php _e('File Totali', 'naval-egt'); ?></h3>
                    <div class="naval-egt-stat-number"><?php echo intval($folder_stats['total_files']); ?></div>
                </div>
                <div class="naval-egt-stat-card">
                    <h3><?php _e('Spazio Utilizzato', 'naval-egt'); ?></h3>
                    <div class="naval-egt-stat-number"><?php echo size_format($folder_stats['total_size']); ?></div>
                </div>
                <div class="naval-egt-stat-card">
                    <h3><?php _e('File Caricati', 'naval-egt'); ?></h3>
                    <div class="naval-egt-stat-number"><?php echo intval($folder_stats['uploads_count']); ?></div>
                </div>
                <div class="naval-egt-stat-card">
                    <h3><?php _e('File Disponibili', 'naval-egt'); ?></h3>
                    <div class="naval-egt-stat-number"><?php echo intval($folder_stats['downloads_count']); ?></div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="naval-egt-dashboard-content">
                <div class="naval-egt-dashboard-main">
                    <?php if ($atts['show_ftp'] === 'true'): ?>
                    <div class="naval-egt-section">
                        <h3><?php _e('Gestione File FTP', 'naval-egt'); ?></h3>
                        <?php echo do_shortcode('[naval_egt_ftp_manager]'); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($recent_activity): ?>
                    <div class="naval-egt-section">
                        <h3><?php _e('Attività Recente', 'naval-egt'); ?></h3>
                        <div class="naval-egt-activity-list">
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
                                    <div class="naval-egt-activity-action">
                                        <?php 
                                        switch ($activity->action) {
                                            case 'upload':
                                                printf(__('Caricato: %s', 'naval-egt'), esc_html($activity->file_name));
                                                break;
                                            case 'download':
                                                printf(__('Scaricato: %s', 'naval-egt'), esc_html($activity->file_name));
                                                break;
                                            case 'delete':
                                                printf(__('Eliminato: %s', 'naval-egt'), esc_html($activity->file_name));
                                                break;
                                            case 'create_folder':
                                                printf(__('Creata cartella: %s', 'naval-egt'), esc_html($activity->file_name));
                                                break;
                                        }
                                        ?>
                                    </div>
                                    <div class="naval-egt-activity-date">
                                        <?php echo date_i18n('d/m/Y H:i', strtotime($activity->created_at)); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="naval-egt-dashboard-sidebar">
                    <?php if ($atts['show_profile'] === 'true'): ?>
                    <div class="naval-egt-section">
                        <h3><?php _e('Il Tuo Profilo', 'naval-egt'); ?></h3>
                        <div class="naval-egt-profile-info">
                            <p><strong><?php _e('Nome:', 'naval-egt'); ?></strong> <?php echo esc_html($user_data['display_name']); ?></p>
                            <p><strong><?php _e('Email:', 'naval-egt'); ?></strong> <?php echo esc_html($user_data['email']); ?></p>
                            <?php if ($user_data['company']): ?>
                            <p><strong><?php _e('Azienda:', 'naval-egt'); ?></strong> <?php echo esc_html($user_data['company']); ?></p>
                            <?php endif; ?>
                            <?php if ($user_data['phone']): ?>
                            <p><strong><?php _e('Telefono:', 'naval-egt'); ?></strong> <?php echo esc_html($user_data['phone']); ?></p>
                            <?php endif; ?>
                            <p><strong><?php _e('Registrato il:', 'naval-egt'); ?></strong> <?php echo date_i18n('d/m/Y', strtotime($user_data['registered'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="naval-egt-section">
                        <h3><?php _e('Supporto', 'naval-egt'); ?></h3>
                        <p><?php _e('Per assistenza tecnica:', 'naval-egt'); ?></p>
                        <p><a href="mailto:technical@navalegt.it">technical@navalegt.it</a></p>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    public function ftp_manager_shortcode($atts) {
        if (!is_user_logged_in() || !naval_egt_user_can_access()) {
            return '<p>' . __('Accesso negato.', 'naval-egt') . '</p>';
        }
        
        $atts = shortcode_atts(array(
            'folder' => '',
            'allow_upload' => 'true',
            'allow_delete' => 'true',
            'show_stats' => 'true'
        ), $atts);
        
        $user_id = get_current_user_id();
        $ftp_manager = new NavalEGT_FTPManager();
        
        ob_start();
        ?>
        
        <div class="naval-egt-ftp-manager" id="naval-egt-ftp-manager">
            <div class="naval-egt-ftp-toolbar">
                <?php if ($atts['allow_upload'] === 'true'): ?>
                <button type="button" class="naval-egt-btn naval-egt-btn-primary" onclick="showUploadForm()">
                    <?php _e('Carica File', 'naval-egt'); ?>
                </button>
                <button type="button" class="naval-egt-btn naval-egt-btn-secondary" onclick="showCreateFolderForm()">
                    <?php _e('Crea Cartella', 'naval-egt'); ?>
                </button>
                <?php endif; ?>
                <button type="button" class="naval-egt-btn naval-egt-btn-secondary" onclick="refreshFileList()">
                    <?php _e('Aggiorna', 'naval-egt'); ?>
                </button>
            </div>
            
            <!-- Upload Form Modal -->
            <?php if ($atts['allow_upload'] === 'true'): ?>
            <div id="naval-egt-upload-modal" class="naval-egt-modal" style="display: none;">
                <div class="naval-egt-modal-content">
                    <span class="naval-egt-close" onclick="hideUploadForm()">&times;</span>
                    <h3><?php _e('Carica File', 'naval-egt'); ?></h3>
                    <form id="naval-egt-upload-form" enctype="multipart/form-data">
                        <div class="naval-egt-form-group">
                            <label for="ftp_file"><?php _e('Seleziona File', 'naval-egt'); ?></label>
                            <input type="file" id="ftp_file" name="ftp_file" required />
                            <small><?php printf(__('Dimensione massima: %s', 'naval-egt'), size_format(50 * 1024 * 1024)); ?></small>
                        </div>
                        <div class="naval-egt-form-group">
                            <label for="subfolder"><?php _e('Cartella di destinazione', 'naval-egt'); ?></label>
                            <select id="subfolder" name="subfolder">
                                <option value="uploads/"><?php _e('I miei file', 'naval-egt'); ?></option>
                                <option value="shared/"><?php _e('Condivisi', 'naval-egt'); ?></option>
                            </select>
                        </div>
                        <div class="naval-egt-form-group">
                            <button type="submit" class="naval-egt-btn naval-egt-btn-primary">
                                <?php _e('Carica', 'naval-egt'); ?>
                            </button>
                        </div>
                        <div id="upload-progress" style="display: none;">
                            <div class="naval-egt-progress-bar">
                                <div class="naval-egt-progress-fill"></div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Create Folder Modal -->
            <div id="naval-egt-folder-modal" class="naval-egt-modal" style="display: none;">
                <div class="naval-egt-modal-content">
                    <span class="naval-egt-close" onclick="hideCreateFolderForm()">&times;</span>
                    <h3><?php _e('Crea Cartella', 'naval-egt'); ?></h3>
                    <form id="naval-egt-folder-form">
                        <div class="naval-egt-form-group">
                            <label for="folder_name"><?php _e('Nome Cartella', 'naval-egt'); ?></label>
                            <input type="text" id="folder_name" name="folder_name" required />
                        </div>
                        <div class="naval-egt-form-group">
                            <button type="submit" class="naval-egt-btn naval-egt-btn-primary">
                                <?php _e('Crea', 'naval-egt'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- File List -->
            <div class="naval-egt-ftp-navigation">
                <span id="current-path"><?php _e('/', 'naval-egt'); ?></span>
            </div>
            
            <div class="naval-egt-file-list" id="naval-egt-file-list">
                <div class="naval-egt-loading"><?php _e('Caricamento...', 'naval-egt'); ?></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            loadFileList('');
            
            // Upload form submission
            $('#naval-egt-upload-form').on('submit', function(e) {
                e.preventDefault();
                uploadFile();
            });
            
            // Create folder form submission
            $('#naval-egt-folder-form').on('submit', function(e) {
                e.preventDefault();
                createFolder();
            });
        });
        
        function showUploadForm() {
            document.getElementById('naval-egt-upload-modal').style.display = 'block';
        }
        
        function hideUploadForm() {
            document.getElementById('naval-egt-upload-modal').style.display = 'none';
        }
        
        function showCreateFolderForm() {
            document.getElementById('naval-egt-folder-modal').style.display = 'block';
        }
        
        function hideCreateFolderForm() {
            document.getElementById('naval-egt-folder-modal').style.display = 'none';
        }
        
        function refreshFileList() {
            var currentPath = getCurrentPath();
            loadFileList(currentPath);
        }
        
        function getCurrentPath() {
            return jQuery('#current-path').text().replace('/', '');
        }
        
        function loadFileList(path) {
            jQuery.ajax({
                url: naval_egt_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'naval_egt_list_files',
                    nonce: naval_egt_ajax.nonce,
                    path: path
                },
                success: function(response) {
                    if (response.success) {
                        displayFileList(response.data);
                        updateCurrentPath(path);
                    } else {
                        showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    showNotice('Errore di comunicazione', 'error');
                }
            });
        }
        
        function displayFileList(files) {
            var html = '';
            
            if (files.length === 0) {
                html = '<div class="naval-egt-empty-folder"><?php _e("Cartella vuota", "naval-egt"); ?></div>';
            } else {
                html += '<div class="naval-egt-file-grid">';
                files.forEach(function(file) {
                    html += '<div class="naval-egt-file-item" data-type="' + file.type + '">';
                    html += '<div class="naval-egt-file-icon">';
                    html += file.type === 'folder' ? '📁' : getFileIcon(file.extension);
                    html += '</div>';
                    html += '<div class="naval-egt-file-info">';
                    html += '<div class="naval-egt-file-name">' + file.name + '</div>';
                    if (file.type === 'file') {
                        html += '<div class="naval-egt-file-size">' + formatFileSize(file.size) + '</div>';
                    }
                    html += '<div class="naval-egt-file-date">' + formatDate(file.modified) + '</div>';
                    html += '</div>';
                    html += '<div class="naval-egt-file-actions">';
                    if (file.type === 'folder') {
                        html += '<button onclick="loadFileList(\'' + file.path + '\')" class="naval-egt-btn-sm">Apri</button>';
                    } else if (file.can_download) {
                        html += '<button onclick="downloadFile(\'' + file.path + '\')" class="naval-egt-btn-sm">Scarica</button>';
                    }
                    if (file.path.startsWith('uploads/') && <?php echo ($atts['allow_delete'] === 'true' ? 'true' : 'false'); ?>) {
                        html += '<button onclick="deleteFile(\'' + file.path + '\')" class="naval-egt-btn-sm naval-egt-btn-danger">Elimina</button>';
                    }
                    html += '</div>';
                    html += '</div>';
                });
                html += '</div>';
            }
            
            jQuery('#naval-egt-file-list').html(html);
        }
        
        function updateCurrentPath(path) {
            jQuery('#current-path').text('/' + path);
        }
        
        function getFileIcon(extension) {
            var icons = {
                'pdf': '📄',
                'doc': '📝', 'docx': '📝',
                'xls': '📊', 'xlsx': '📊',
                'zip': '🗜️', 'rar': '🗜️',
                'jpg': '🖼️', 'jpeg': '🖼️', 'png': '🖼️', 'gif': '🖼️',
                'dwg': '📐', 'dxf': '📐'
            };
            return icons[extension.toLowerCase()] || '📄';
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            var k = 1024;
            var sizes = ['B', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function formatDate(timestamp) {
            var date = new Date(timestamp * 1000);
            return date.toLocaleDateString('it-IT') + ' ' + date.toLocaleTimeString('it-IT', {hour: '2-digit', minute: '2-digit'});
        }
        
        function uploadFile() {
            var formData = new FormData();
            var fileInput = document.getElementById('ftp_file');
            var file = fileInput.files[0];
            
            if (!file) {
                showNotice('Seleziona un file', 'error');
                return;
            }
            
            formData.append('action', 'naval_egt_upload_file');
            formData.append('nonce', naval_egt_ajax.nonce);
            formData.append('file', file);
            formData.append('subfolder', document.getElementById('subfolder').value);
            
            jQuery('#upload-progress').show();
            
            jQuery.ajax({
                url: naval_egt_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener("progress", function(evt) {
                        if (evt.lengthComputable) {
                            var percentComplete = evt.loaded / evt.total * 100;
                            jQuery('.naval-egt-progress-fill').css('width', percentComplete + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    jQuery('#upload-progress').hide();
                    if (response.success) {
                        showNotice(response.data.message, 'success');
                        hideUploadForm();
                        refreshFileList();
                        document.getElementById('naval-egt-upload-form').reset();
                    } else {
                        showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    jQuery('#upload-progress').hide();
                    showNotice('Errore durante il caricamento', 'error');
                }
            });
        }
        
        function createFolder() {
            var folderName = document.getElementById('folder_name').value;
            
            if (!folderName) {
                showNotice('Inserisci il nome della cartella', 'error');
                return;
            }
            
            jQuery.ajax({
                url: naval_egt_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'naval_egt_create_folder',
                    nonce: naval_egt_ajax.nonce,
                    folder_name: folderName
                },
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message, 'success');
                        hideCreateFolderForm();
                        refreshFileList();
                        document.getElementById('naval-egt-folder-form').reset();
                    } else {
                        showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    showNotice('Errore durante la creazione della cartella', 'error');
                }
            });
        }
        
        function downloadFile(filePath) {
            window.location.href = naval_egt_ajax.ajax_url + '?action=naval_egt_download_file&nonce=' + naval_egt_ajax.nonce + '&file=' + encodeURIComponent(filePath);
        }
        
        function deleteFile(filePath) {
            if (!confirm('Sei sicuro di voler eliminare questo file?')) {
                return;
            }
            
            jQuery.ajax({
                url: naval_egt_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'naval_egt_delete_file',
                    nonce: naval_egt_ajax.nonce,
                    file_path: filePath
                },
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message, 'success');
                        refreshFileList();
                    } else {
                        showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    showNotice('Errore durante l\'eliminazione', 'error');
                }
            });
        }
        
        function showNotice(message, type) {
            var noticeClass = 'naval-egt-notice-' + type;
            var notice = '<div class="naval-egt-notice ' + noticeClass + '">' + message + '</div>';
            
            jQuery('#naval-egt-ftp-manager').prepend(notice);
            
            setTimeout(function() {
                jQuery('.naval-egt-notice').fadeOut();
            }, 5000);
        }
        </script>
        
        <?php
        return ob_get_clean();
    }
    
    public function user_profile_shortcode($atts) {
        if (!is_user_logged_in() || !naval_egt_user_can_access()) {
            return '<p>' . __('Accesso negato.', 'naval-egt') . '</p>';
        }
        
        $user_data = naval_egt_get_user_data();
        
        ob_start();
        ?>
        
        <div class="naval-egt-user-profile">
            <h3><?php _e('Profilo Utente', 'naval-egt'); ?></h3>
            
            <div class="naval-egt-profile-card">
                <div class="naval-egt-profile-info">
                    <p><strong><?php _e('Nome:', 'naval-egt'); ?></strong> <?php echo esc_html($user_data['display_name']); ?></p>
                    <p><strong><?php _e('Email:', 'naval-egt'); ?></strong> <?php echo esc_html($user_data['email']); ?></p>
                    <p><strong><?php _e('Username:', 'naval-egt'); ?></strong> <?php echo esc_html($user_data['username']); ?></p>
                    <?php if ($user_data['company']): ?>
                    <p><strong><?php _e('Azienda:', 'naval-egt'); ?></strong> <?php echo esc_html($user_data['company']); ?></p>
                    <?php endif; ?>
                    <?php if ($user_data['phone']): ?>
                    <p><strong><?php _e('Telefono:', 'naval-egt'); ?></strong> <?php echo esc_html($user_data['phone']); ?></p>
                    <?php endif; ?>
                    <p><strong><?php _e('Registrato il:', 'naval-egt'); ?></strong> <?php echo date_i18n('d/m/Y', strtotime($user_data['registered'])); ?></p>
                </div>
            </div>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    private function display_notices() {
        $notices = NavalEGT_UserManagement::get_notices();
        
        if (!empty($notices)) {
            foreach ($notices as $notice) {
                $class = 'naval-egt-notice naval-egt-notice-' . $notice['type'];
                echo '<div class="' . esc_attr($class) . '">';
                echo '<p>' . esc_html($notice['message']) . '</p>';
                echo '</div>';
            }
        }
    }
}
?>