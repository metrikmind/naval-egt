/**
 * Naval EGT Admin JavaScript - Versione completa con gestione Dropbox
 */

jQuery(document).ready(function($) {
    
    // Variobili globali
    let currentModal = null;
    
    /**
     * Inizializzazione
     */
    initAdminFunctions();
    
    function initAdminFunctions() {
        // Gestione modali
        initModals();
        
        // Gestione tabelle utenti
        initUserTable();
        
        // Gestione upload file
        initFileUpload();
        
        // Gestione filtri log
        initLogFilters();
        
        // Gestione export
        initExportFunctions();
        
        // Gestione Dropbox
        initDropboxFunctions();
        
        // Auto-refresh statistiche
        setInterval(refreshStats, 300000); // Ogni 5 minuti
    }
    
    /**
     * Gestione Dropbox - NUOVO
     */
    function initDropboxFunctions() {
        // Configura Dropbox
        $(document).on('click', '#configure-dropbox, .configure-dropbox-btn', function(e) {
            e.preventDefault();
            configureDropbox();
        });
        
        // Test connessione Dropbox
        $(document).on('click', '.test-dropbox-btn', function(e) {
            e.preventDefault();
            testDropboxConnection();
        });
        
        // Sincronizza cartelle
        $(document).on('click', '.sync-folders-btn', function(e) {
            e.preventDefault();
            syncAllUserFolders();
        });
        
        // Disconnetti Dropbox
        $(document).on('click', '.disconnect-dropbox-btn', function(e) {
            e.preventDefault();
            disconnectDropbox();
        });
        
        // Validazione campi Dropbox in tempo reale
        $('#dropbox_app_key, #dropbox_app_secret').on('input', function() {
            validateDropboxFields();
        });
    }
    
    function configureDropbox() {
        if (!confirm('Vuoi procedere con la configurazione automatica di Dropbox?\n\nVerrai reindirizzato alla pagina di autorizzazione Dropbox.')) {
            return;
        }
        
        showLoading('Configurazione automatica Dropbox...');
        
        $.ajax({
            url: naval_egt_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'naval_egt_configure_dropbox',
                nonce: naval_egt_ajax.nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    showNotice('Configurazione automatica completata! Reindirizzamento a Dropbox...', 'success');
                    
                    // Breve ritardo per far vedere il messaggio
                    setTimeout(function() {
                        window.location.href = response.data.auth_url;
                    }, 1500);
                } else {
                    showNotice('Errore nella configurazione: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error('Errore AJAX:', xhr.responseText);
                showNotice('Errore di comunicazione con il server. Controlla i log per dettagli.', 'error');
            }
        });
    }
    
    function testDropboxConnection() {
        showLoading('Test connessione Dropbox...');
        
        $.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_ajax',
            naval_action: 'test_dropbox_connection',
            nonce: naval_egt_ajax.nonce
        }, function(response) {
            hideLoading();
            if (response.success) {
                const accountName = response.data.account_name || 'Account sconosciuto';
                const accountEmail = response.data.account_email || '';
                const message = `Connessione Dropbox OK!\nAccount: ${accountName}${accountEmail ? '\nEmail: ' + accountEmail : ''}`;
                showNotice(message, 'success');
                
                // Aggiorna lo stato nell'interfaccia
                updateDropboxStatus(true, accountName);
            } else {
                showNotice('Errore connessione Dropbox: ' + response.data, 'error');
                updateDropboxStatus(false);
            }
        }).fail(function() {
            hideLoading();
            showNotice('Errore di comunicazione durante il test', 'error');
        });
    }
    
    function syncAllUserFolders() {
        if (!confirm('Vuoi sincronizzare tutte le cartelle utenti con Dropbox?\n\nQuesta operazione potrebbe richiedere diversi minuti a seconda del numero di utenti e file.')) {
            return;
        }
        
        showLoading('Sincronizzazione cartelle in corso...');
        
        $.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_ajax',
            naval_action: 'sync_all_user_folders',
            nonce: naval_egt_ajax.nonce
        }, function(response) {
            hideLoading();
            if (response.success) {
                const syncData = response.data;
                let message = 'Sincronizzazione completata!\n';
                
                if (syncData.stats) {
                    message += `\n• Utenti processati: ${syncData.stats.users_processed || 0}`;
                    message += `\n• Cartelle trovate: ${syncData.stats.folders_found || 0}`;
                    message += `\n• File sincronizzati: ${syncData.stats.files_synced || 0}`;
                }
                
                showNotice(message, 'success');
                refreshStats();
                refreshUsersTable();
            } else {
                showNotice('Errore durante la sincronizzazione: ' + response.data, 'error');
            }
        }).fail(function() {
            hideLoading();
            showNotice('Errore di comunicazione durante la sincronizzazione', 'error');
        });
    }
    
    function disconnectDropbox() {
        if (!confirm('Sei sicuro di voler disconnettere Dropbox?\n\nDovrai riconfigurare l\'integrazione per utilizzarla nuovamente.')) {
            return;
        }
        
        showLoading('Disconnessione Dropbox...');
        
        $.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_ajax',
            naval_action: 'disconnect_dropbox',
            nonce: naval_egt_ajax.nonce
        }, function(response) {
            hideLoading();
            if (response.success) {
                showNotice('Dropbox disconnesso con successo!', 'success');
                updateDropboxStatus(false);
                
                // Pulisci i campi
                $('#dropbox_app_key, #dropbox_app_secret').val('');
                
                // Ricarica la pagina dopo 2 secondi
                setTimeout(function() {
                    location.reload();
                }, 2000);
            } else {
                showNotice('Errore nella disconnessione: ' + response.data, 'error');
            }
        }).fail(function() {
            hideLoading();
            showNotice('Errore di comunicazione durante la disconnessione', 'error');
        });
    }
    
    function validateDropboxFields() {
        const appKey = $('#dropbox_app_key').val().trim();
        const appSecret = $('#dropbox_app_secret').val().trim();
        const configBtn = $('#configure-dropbox, .configure-dropbox-btn');
        
        if (appKey && appSecret) {
            configBtn.prop('disabled', false).removeClass('disabled');
            $('#dropbox_app_key, #dropbox_app_secret').removeClass('error');
        } else {
            configBtn.prop('disabled', true).addClass('disabled');
        }
        
        // Validazione formato App Key (generalmente inizia con lettere/numeri)
        if (appKey && appKey.length < 10) {
            $('#dropbox_app_key').addClass('warning');
        } else {
            $('#dropbox_app_key').removeClass('warning');
        }
        
        // Validazione formato App Secret (generalmente più lungo)
        if (appSecret && appSecret.length < 15) {
            $('#dropbox_app_secret').addClass('warning');
        } else {
            $('#dropbox_app_secret').removeClass('warning');
        }
    }
    
    function updateDropboxStatus(isConnected, accountName = '') {
        const statusElement = $('.dropbox-status');
        const actionsElement = $('.dropbox-actions');
        
        if (isConnected) {
            statusElement.removeClass('disconnected').addClass('connected')
                .html(`<span class="dashicons dashicons-yes-alt"></span> Connesso${accountName ? ' (' + accountName + ')' : ''}`);
            
            actionsElement.find('.test-dropbox-btn, .sync-folders-btn, .disconnect-dropbox-btn').show();
            actionsElement.find('.configure-dropbox-btn').hide();
        } else {
            statusElement.removeClass('connected').addClass('disconnected')
                .html('<span class="dashicons dashicons-dismiss"></span> Non configurato');
            
            actionsElement.find('.test-dropbox-btn, .sync-folders-btn, .disconnect-dropbox-btn').hide();
            actionsElement.find('.configure-dropbox-btn').show();
        }
    }
    
    /**
     * Gestione Modali
     */
    function initModals() {
        // Chiudi modal con ESC
        $(document).keyup(function(e) {
            if (e.keyCode === 27) {
                closeModal();
            }
        });
        
        // Chiudi modal cliccando fuori
        $(document).on('click', '.modal-overlay', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Bottoni chiudi modal
        $(document).on('click', '.modal-close, .btn-cancel', function() {
            closeModal();
        });
    }
    
    function openModal(content, title = '') {
        const modalHtml = `
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
        `;
        
        $('body').append(modalHtml);
        currentModal = $('.modal-overlay').last();
        currentModal.fadeIn(200);
    }
    
    function closeModal() {
        if (currentModal) {
            currentModal.fadeOut(200, function() {
                $(this).remove();
            });
            currentModal = null;
        }
    }
    
    /**
     * Gestione Tabella Utenti
     */
    function initUserTable() {
        // Ricerca utenti
        $('#user-search').on('input', debounce(function() {
            filterUsers();
        }, 500));
        
        // Filtro status
        $('#status-filter').on('change', function() {
            filterUsers();
        });
        
        // Azioni utenti
        $(document).on('click', '.btn-edit-user', function() {
            const userId = $(this).data('user-id');
            editUser(userId);
        });
        
        $(document).on('click', '.btn-suspend-user', function() {
            const userId = $(this).data('user-id');
            const currentStatus = $(this).data('current-status');
            toggleUserStatus(userId, currentStatus);
        });
        
        $(document).on('click', '.btn-delete-user', function() {
            const userId = $(this).data('user-id');
            const userName = $(this).data('user-name');
            deleteUser(userId, userName);
        });
        
        $(document).on('click', '.btn-upload-user', function() {
            const userId = $(this).data('user-id');
            openUserUploadModal(userId);
        });
        
        // Aggiungi utente
        $('#add-user-btn').on('click', function() {
            openAddUserModal();
        });
    }
    
    function filterUsers() {
        const search = $('#user-search').val();
        const status = $('#status-filter').val();
        
        showLoading('Filtraggio utenti...');
        
        $.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_ajax',
            naval_action: 'filter_users',
            nonce: naval_egt_ajax.nonce,
            search: search,
            status: status
        }, function(response) {
            hideLoading();
            if (response.success) {
                $('#users-table-body').html(response.data.html);
                updatePagination(response.data.pagination);
            } else {
                showNotice('Errore nel filtraggio: ' + response.data, 'error');
            }
        });
    }
    
    function editUser(userId) {
        showLoading('Caricamento dati utente...');
        
        $.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_ajax',
            naval_action: 'get_user_data',
            nonce: naval_egt_ajax.nonce,
            user_id: userId
        }, function(response) {
            hideLoading();
            if (response.success) {
                openEditUserModal(response.data.user);
            } else {
                showNotice('Errore nel caricamento: ' + response.data, 'error');
            }
        });
    }
    
    function openEditUserModal(user) {
        const formHtml = `
            <form id="edit-user-form" class="naval-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit-nome">Nome *</label>
                        <input type="text" id="edit-nome" name="nome" value="${user.nome}" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-cognome">Cognome *</label>
                        <input type="text" id="edit-cognome" name="cognome" value="${user.cognome}" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit-email">Email *</label>
                        <input type="email" id="edit-email" name="email" value="${user.email}" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-username">Username *</label>
                        <input type="text" id="edit-username" name="username" value="${user.username}" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit-telefono">Telefono</label>
                    <input type="tel" id="edit-telefono" name="telefono" value="${user.telefono || ''}">
                </div>
                <div class="form-group">
                    <label for="edit-ragione-sociale">Ragione Sociale</label>
                    <input type="text" id="edit-ragione-sociale" name="ragione_sociale" value="${user.ragione_sociale || ''}">
                </div>
                <div class="form-group">
                    <label for="edit-partita-iva">Partita IVA</label>
                    <input type="text" id="edit-partita-iva" name="partita_iva" value="${user.partita_iva || ''}">
                </div>
                <div class="form-group">
                    <label for="edit-status">Status</label>
                    <select id="edit-status" name="status">
                        <option value="ATTIVO" ${user.status === 'ATTIVO' ? 'selected' : ''}>ATTIVO</option>
                        <option value="SOSPESO" ${user.status === 'SOSPESO' ? 'selected' : ''}>SOSPESO</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit-password">Nuova Password (lascia vuoto per non modificare)</label>
                    <input type="password" id="edit-password" name="password">
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-primary">Salva Modifiche</button>
                    <button type="button" class="btn-cancel">Annulla</button>
                </div>
                <input type="hidden" name="user_id" value="${user.id}">
            </form>
        `;
        
        openModal(formHtml, `Modifica Utente: ${user.nome} ${user.cognome}`);
        
        // Gestione submit form
        $('#edit-user-form').on('submit', function(e) {
            e.preventDefault();
            saveUserChanges();
        });
    }
    
    function saveUserChanges() {
        const formData = $('#edit-user-form').serialize();
        
        showLoading('Salvataggio modifiche...');
        
        $.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_ajax',
            naval_action: 'update_user',
            nonce: naval_egt_ajax.nonce,
            ...Object.fromEntries(new URLSearchParams(formData))
        }, function(response) {
            hideLoading();
            if (response.success) {
                showNotice('Utente aggiornato con successo!', 'success');
                closeModal();
                refreshUsersTable();
            } else {
                showNotice('Errore nel salvataggio: ' + response.data, 'error');
            }
        });
    }
    
    function toggleUserStatus(userId, currentStatus) {
        const newStatus = currentStatus === 'ATTIVO' ? 'SOSPESO' : 'ATTIVO';
        const action = newStatus === 'ATTIVO' ? 'attivare' : 'sospendere';
        
        if (!confirm(`Sei sicuro di voler ${action} questo utente?`)) {
            return;
        }
        
        showLoading(`${action.charAt(0).toUpperCase() + action.slice(1)} utente...`);
        
        $.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_ajax',
            naval_action: 'toggle_user_status',
            nonce: naval_egt_ajax.nonce,
            user_id: userId,
            status: newStatus
        }, function(response) {
            hideLoading();
            if (response.success) {
                showNotice(`Utente ${action}to con successo!`, 'success');
                refreshUsersTable();
            } else {
                showNotice('Errore: ' + response.data, 'error');
            }
        });
    }
    
    function deleteUser(userId, userName) {
        if (!confirm(`ATTENZIONE: Sei sicuro di voler eliminare l'utente "${userName}"?\n\nQuesta azione eliminerà anche tutti i suoi file e non può essere annullata.`)) {
            return;
        }
        
        showLoading('Eliminazione utente...');
        
        $.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_ajax',
            naval_action: 'delete_user',
            nonce: naval_egt_ajax.nonce,
            user_id: userId
        }, function(response) {
            hideLoading();
            if (response.success) {
                showNotice('Utente eliminato con successo!', 'success');
                refreshUsersTable();
                refreshStats();
            } else {
                showNotice('Errore nell\'eliminazione: ' + response.data, 'error');
            }
        });
    }
    
    /**
     * Gestione Upload File
     */
    function initFileUpload() {
        // Drop zone per file
        $(document).on('dragover', '.file-drop-zone', function(e) {
            e.preventDefault();
            $(this).addClass('drag-over');
        });
        
        $(document).on('dragleave', '.file-drop-zone', function(e) {
            e.preventDefault();
            $(this).removeClass('drag-over');
        });
        
        $(document).on('drop', '.file-drop-zone', function(e) {
            e.preventDefault();
            $(this).removeClass('drag-over');
            
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                uploadFiles(files);
            }
        });
        
        // Click per selezionare file
        $(document).on('click', '.file-drop-zone', function() {
            $('#file-upload-input').click();
        });
        
        $(document).on('change', '#file-upload-input', function() {
            const files = this.files;
            if (files.length > 0) {
                uploadFiles(files);
            }
        });
    }
    
    function uploadFiles(files) {
        const userId = $('#selected-user-id').val();
        const folderPath = $('#selected-folder-path').val();
        
        if (!userId) {
            showNotice('Seleziona prima un utente', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'naval_egt_ajax');
        formData.append('naval_action', 'admin_upload_files');
        formData.append('nonce', naval_egt_ajax.nonce);
        formData.append('user_id', userId);
        formData.append('folder_path', folderPath);
        
        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }
        
        showLoading('Caricamento file in corso...');
        
        $.ajax({
            url: naval_egt_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showNotice('File caricati con successo!', 'success');
                    refreshFilesList();
                } else {
                    showNotice('Errore nel caricamento: ' + response.data, 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotice('Errore di connessione', 'error');
            }
        });
    }
    
    /**
     * Gestione Filtri Log
     */
    function initLogFilters() {
        $('#log-user-filter, #log-action-filter, #log-date-from, #log-date-to').on('change', function() {
            filterLogs();
        });
        
        $('#clear-logs-btn').on('click', function() {
            if (confirm('Sei sicuro di voler eliminare tutti i log? Questa azione non può essere annullata.')) {
                clearLogs();
            }
        });
    }
    
    function filterLogs() {
        const filters = {
            user_id: $('#log-user-filter').val(),
            action: $('#log-action-filter').val(),
            date_from: $('#log-date-from').val(),
            date_to: $('#log-date-to').val()
        };
        
        showLoading('Filtraggio log...');
        
        $.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_ajax',
            naval_action: 'filter_logs',
            nonce: naval_egt_ajax.nonce,
            filters: filters
        }, function(response) {
            hideLoading();
            if (response.success) {
                $('#logs-table-body').html(response.data.html);
                updateLogsPagination(response.data.pagination);
            } else {
                showNotice('Errore nel filtraggio: ' + response.data, 'error');
            }
        });
    }
    
    function clearLogs() {
        showLoading('Eliminazione log...');
        
        $.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_ajax',
            naval_action: 'clear_logs',
            nonce: naval_egt_ajax.nonce
        }, function(response) {
            hideLoading();
            if (response.success) {
                showNotice('Log eliminati con successo!', 'success');
                $('#logs-table-body').empty();
            } else {
                showNotice('Errore nell\'eliminazione: ' + response.data, 'error');
            }
        });
    }
    
    /**
     * Gestione Export
     */
    function initExportFunctions() {
        $('.export-btn').on('click', function() {
            const exportType = $(this).data('export-type');
            const format = $(this).data('format');
            openExportModal(exportType, format);
        });
    }
    
    function openExportModal(exportType, format) {
        let formHtml = '';
        let title = '';
        
        if (exportType === 'users') {
            title = `Esporta Utenti (${format.toUpperCase()})`;
            formHtml = `
                <form id="export-form">
                    <div class="form-group">
                        <label for="export-status">Filtra per Status</label>
                        <select id="export-status" name="status">
                            <option value="">Tutti</option>
                            <option value="ATTIVO">Solo Attivi</option>
                            <option value="SOSPESO">Solo Sospesi</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="export-search">Cerca per nome/email</label>
                        <input type="text" id="export-search" name="search" placeholder="Nome, cognome o email">
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-primary">Esporta ${format.toUpperCase()}</button>
                        <button type="button" class="btn-cancel">Annulla</button>
                    </div>
                    <input type="hidden" name="export_type" value="${exportType}">
                    <input type="hidden" name="format" value="${format}">
                </form>
            `;
        } else if (exportType === 'logs') {
            title = `Esporta Log (${format.toUpperCase()})`;
            formHtml = `
                <form id="export-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="export-log-user">Utente</label>
                            <select id="export-log-user" name="user_id">
                                <option value="">Tutti gli utenti</option>
                                <!-- Popolato via AJAX -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="export-log-action">Azione</label>
                            <select id="export-log-action" name="action">
                                <option value="">Tutte le azioni</option>
                                <option value="LOGIN">Login</option>
                                <option value="UPLOAD">Upload</option>
                                <option value="DOWNLOAD">Download</option>
                                <option value="REGISTRATION">Registrazione</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="export-date-from">Da Data</label>
                            <input type="date" id="export-date-from" name="date_from">
                        </div>
                        <div class="form-group">
                            <label for="export-date-to">A Data</label>
                            <input type="date" id="export-date-to" name="date_to">
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-primary">Esporta ${format.toUpperCase()}</button>
                        <button type="button" class="btn-cancel">Annulla</button>
                    </div>
                    <input type="hidden" name="export_type" value="${exportType}">
                    <input type="hidden" name="format" value="${format}">
                </form>
            `;
        }
        
        openModal(formHtml, title);
        
        // Popola select utenti per log
        if (exportType === 'logs') {
            loadUsersForSelect('#export-log-user');
        }
        
        // Gestione submit
        $('#export-form').on('submit', function(e) {
            e.preventDefault();
            executeExport();
        });
    }
    
    function executeExport() {
        const formData = $('#export-form').serialize();
        
        showLoading('Preparazione export...');
        
        // Crea form per download
        const form = $('<form>', {
            method: 'POST',
            action: naval_egt_ajax.ajax_url
        });
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'naval_egt_export'
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'nonce',
            value: naval_egt_ajax.nonce
        }));
        
        // Aggiungi tutti i campi del form
        const params = new URLSearchParams(formData);
        for (const [key, value] of params) {
            form.append($('<input>', {
                type: 'hidden',
                name: key,
                value: value
            }));
        }
        
        $('body').append(form);
        form.submit();
        form.remove();
        
        hideLoading();
        closeModal();
        showNotice('Export avviato! Il download inizierà a breve.', 'success');
    }
    
    /**
     * Funzioni di utilità
     */
    function refreshStats() {
        $.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_ajax',
            naval_action: 'refresh_stats',
            nonce: naval_egt_ajax.nonce
        }, function(response) {
            if (response.success) {
                updateStatsBoxes(response.data);
            }
        });
    }
    
    function updateStatsBoxes(stats) {
        $('.stat-box').each(function() {
            const statType = $(this).data('stat-type');
            if (stats[statType] !== undefined) {
                $(this).find('.stat-number').text(stats[statType]);
            }
        });
    }
    
    function refreshUsersTable() {
        filterUsers();
    }
    
    function refreshFilesList() {
        // Implementa refresh lista file se necessario
        location.reload();
    }
    
    function loadUsersForSelect(selector) {
        $.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_ajax',
            naval_action: 'get_users_list',
            nonce: naval_egt_ajax.nonce
        }, function(response) {
            if (response.success) {
                const select = $(selector);
                response.data.users.forEach(function(user) {
                    select.append($('<option>', {
                        value: user.id,
                        text: `${user.nome} ${user.cognome} (${user.user_code})`
                    }));
                });
            }
        });
    }
    
    function showLoading(message = 'Caricamento...') {
        if ($('#naval-loading').length === 0) {
            const loadingHtml = `
                <div id="naval-loading" class="loading-overlay">
                    <div class="loading-content">
                        <div class="spinner"></div>
                        <p>${message}</p>
                    </div>
                </div>
            `;
            $('body').append(loadingHtml);
        } else {
            $('#naval-loading p').text(message);
        }
    }
    
    function hideLoading() {
        $('#naval-loading').remove();
    }
    
    function showNotice(message, type = 'info') {
        const noticeHtml = `
            <div class="notice notice-${type} is-dismissible naval-notice">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Chiudi notifica</span>
                </button>
            </div>
        `;
        
        $('.wrap h1').after(noticeHtml);
        
        // Auto-dismiss dopo 5 secondi
        setTimeout(function() {
            $('.naval-notice').fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Gestione click dismiss
        $('.naval-notice .notice-dismiss').on('click', function() {
            $(this).closest('.naval-notice').fadeOut(function() {
                $(this).remove();
            });
        });
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
    
    function updatePagination(pagination) {
        if (pagination && pagination.html) {
            $('.tablenav-pages').html(pagination.html);
        }
    }
    
    function updateLogsPagination(pagination) {
        if (pagination && pagination.html) {
            $('.logs-pagination').html(pagination.html);
        }
    }
    
    /**
     * Gestione selezione utente per file
     */
    $(document).on('change', '#user-select', function() {
        const userId = $(this).val();
        if (userId) {
            loadUserFolders(userId);
        } else {
            $('#folder-select').empty().append('<option value="">Seleziona prima un utente</option>');
        }
    });
    
    function loadUserFolders(userId) {
        showLoading('Caricamento cartelle utente...');
        
        $.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_ajax',
            naval_action: 'get_user_folders',
            nonce: naval_egt_ajax.nonce,
            user_id: userId
        }, function(response) {
            hideLoading();
            if (response.success) {
                const select = $('#folder-select');
                select.empty();
                select.append('<option value="">Cartella principale</option>');
                
                if (response.data.folders && response.data.folders.length > 0) {
                    response.data.folders.forEach(function(folder) {
                        select.append($('<option>', {
                            value: folder.path,
                            text: folder.name
                        }));
                    });
                }
            } else {
                showNotice('Errore nel caricamento cartelle: ' + response.data, 'error');
            }
        });
    }
    
    /**
     * Gestione bulk actions
     */
    $('#bulk-action-selector-top, #bulk-action-selector-bottom').on('change', function() {
        const action = $(this).val();
        const selector = $(this).attr('id');
        
        // Sincronizza i due selettori
        if (selector === 'bulk-action-selector-top') {
            $('#bulk-action-selector-bottom').val(action);
        } else {
            $('#bulk-action-selector-top').val(action);
        }
    });
    
    $('#doaction, #doaction2').on('click', function(e) {
        e.preventDefault();
        
        const action = $('#bulk-action-selector-top').val();
        if (!action || action === '-1') {
            showNotice('Seleziona un\'azione', 'error');
            return;
        }
        
        const selectedUsers = [];
        $('input[name="users[]"]:checked').each(function() {
            selectedUsers.push($(this).val());
        });
        
        if (selectedUsers.length === 0) {
            showNotice('Seleziona almeno un utente', 'error');
            return;
        }
        
        executeBulkAction(action, selectedUsers);
    });
    
    function executeBulkAction(action, userIds) {
        let confirmMessage = '';
        let actionText = '';
        
        switch (action) {
            case 'activate':
                confirmMessage = `Attivare ${userIds.length} utenti selezionati?`;
                actionText = 'Attivazione utenti...';
                break;
            case 'suspend':
                confirmMessage = `Sospendere ${userIds.length} utenti selezionati?`;
                actionText = 'Sospensione utenti...';
                break;
            case 'delete':
                confirmMessage = `ATTENZIONE: Eliminare ${userIds.length} utenti selezionati?\n\nQuesta azione eliminerà anche tutti i loro file e non può essere annullata.`;
                actionText = 'Eliminazione utenti...';
                break;
            default:
                return;
        }
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        showLoading(actionText);
        
        $.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_ajax',
            naval_action: 'bulk_user_action',
            nonce: naval_egt_ajax.nonce,
            bulk_action: action,
            user_ids: userIds
        }, function(response) {
            hideLoading();
            if (response.success) {
                showNotice(response.data.message, 'success');
                refreshUsersTable();
                refreshStats();
                
                // Deseleziona tutti i checkbox
                $('input[name="users[]"]').prop('checked', false);
                $('#cb-select-all-1, #cb-select-all-2').prop('checked', false);
            } else {
                showNotice('Errore: ' + response.data, 'error');
            }
        });
    }
    
    /**
     * Gestione select all checkbox
     */
    $('#cb-select-all-1, #cb-select-all-2').on('change', function() {
        const checked = $(this).prop('checked');
        
        // Sincronizza entrambi i checkbox "select all"
        $('#cb-select-all-1, #cb-select-all-2').prop('checked', checked);
        
        // Seleziona/deseleziona tutti gli utenti
        $('input[name="users[]"]').prop('checked', checked);
    });
    
    // Aggiorna stato "select all" quando si cambia selezione individuale
    $(document).on('change', 'input[name="users[]"]', function() {
        const total = $('input[name="users[]"]').length;
        const checked = $('input[name="users[]"]:checked').length;
        
        $('#cb-select-all-1, #cb-select-all-2').prop('checked', total === checked);
    });
    
    /**
     * Tooltips
     */
    $(document).on('mouseenter', '[title]', function() {
        const title = $(this).attr('title');
        if (title && title.length > 20) {
            $(this).attr('data-tooltip', title).removeAttr('title');
            
            const tooltip = $(`<div class="naval-tooltip">${title}</div>`);
            $('body').append(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.css({
                top: rect.top - tooltip.outerHeight() - 5,
                left: rect.left + (rect.width / 2) - (tooltip.outerWidth() / 2)
            });
        }
    });
    
    $(document).on('mouseleave', '[data-tooltip]', function() {
        $('.naval-tooltip').remove();
        $(this).attr('title', $(this).attr('data-tooltip')).removeAttr('data-tooltip');
    });
});

/**
 * Funzioni globali per compatibilità
 */
function navalEgtRefreshStats() {
    jQuery(document).trigger('refresh-stats');
}

function navalEgtOpenUserModal(userId) {
    // Implementa apertura modal utente
    console.log('Open user modal:', userId);
}

/**
 * Gestione inizializzazione al caricamento pagina
 */
jQuery(window).on('load', function() {
    // Verifica stato Dropbox all'avvio
    if (jQuery('.dropbox-status').length > 0) {
        jQuery.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_ajax',
            naval_action: 'check_dropbox_status',
            nonce: naval_egt_ajax.nonce
        }, function(response) {
            if (response.success && response.data.connected) {
                jQuery('.dropbox-status').removeClass('disconnected').addClass('connected')
                    .html('<span class="dashicons dashicons-yes-alt"></span> Connesso' + 
                          (response.data.account_name ? ' (' + response.data.account_name + ')' : ''));
            }
        });
    }
});

/**
 * CSS dinamico per miglioramenti UI
 */
jQuery(document).ready(function($) {
    // Aggiungi CSS personalizzato se non presente
    if ($('#naval-egt-dynamic-css').length === 0) {
        const dynamicCSS = `
            <style id="naval-egt-dynamic-css">
                .dropbox-status.connected { color: #46b450; font-weight: bold; }
                .dropbox-status.disconnected { color: #dc3232; }
                .loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center; }
                .loading-content { background: white; padding: 20px; border-radius: 5px; text-align: center; }
                .spinner { border: 3px solid #f3f3f3; border-top: 3px solid #0073aa; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: 0 auto 10px; }
                @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                .form-group.error input, .form-group.error select { border-color: #dc3232; }
                .form-group.warning input, .form-group.warning select { border-color: #ffb900; }
                .btn-primary.disabled, .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
                .naval-tooltip { position: absolute; background: #333; color: white; padding: 5px 10px; border-radius: 3px; font-size: 12px; z-index: 10000; white-space: nowrap; }
            </style>
        `;
        $('head').append(dynamicCSS);
    }
});