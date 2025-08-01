/**
 * Naval EGT Public JavaScript
 */

(function($) {
    'use strict';
    
    // Variabili globali
    let fileUploadQueue = [];
    let isUploading = false;
    
    /**
     * Inizializzazione
     */
    $(document).ready(function() {
        initPublicFunctions();
    });
    
    function initPublicFunctions() {
        // Gestione form login/registrazione
        initAuthForms();
        
        // Gestione upload file
        initFileUpload();
        
        // Gestione download file
        initFileDownload();
        
        // Auto-refresh periodico
        if ($('.naval-egt-dashboard').length > 0) {
            setInterval(refreshUserData, 300000); // Ogni 5 minuti
        }
        
        // Gestione responsive
        initResponsiveHandlers();
        
        // Animazioni e UX
        initAnimations();
    }
    
    /**
     * Gestione Form Autenticazione
     */
    function initAuthForms() {
        // Form login
        $('#naval-egt-login-form').on('submit', function(e) {
            e.preventDefault();
            handleLogin();
        });
        
        // Form registrazione
        $('#naval-egt-registration-form').on('submit', function(e) {
            e.preventDefault();
            handleRegistration();
        });
        
        // Validazione password in tempo reale
        $('#password_confirm').on('input', function() {
            validatePasswordConfirm();
        });
        
        // Validazione Partita IVA quando si inserisce Ragione Sociale
        $('#ragione_sociale').on('input', function() {
            togglePartitaIvaRequired();
        });
        
        // Show/hide password
        $('.password-toggle').on('click', function() {
            togglePasswordVisibility($(this));
        });
        
        // Remember me cookie check
        checkRememberMeCookie();
    }
    
    function handleLogin() {
        const form = $('#naval-egt-login-form');
        const formData = new FormData(form[0]);
        
        // Disabilita form durante invio
        form.find('input, button').prop('disabled', true);
        
        showLoading('Accesso in corso...');
        
        $.ajax({
            url: naval_egt_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    showMessage('Accesso effettuato con successo!', 'success');
                    
                    // Redirect o reload dopo 1 secondo
                    setTimeout(function() {
                        if (response.data && response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            window.location.reload();
                        }
                    }, 1000);
                } else {
                    showMessage(response.data || 'Errore durante l\'accesso', 'error');
                    form.find('input, button').prop('disabled', false);
                }
            },
            error: function() {
                hideLoading();
                showMessage('Errore di connessione. Riprova.', 'error');
                form.find('input, button').prop('disabled', false);
            }
        });
    }
    
    function handleRegistration() {
        const form = $('#naval-egt-registration-form');
        
        // Validazione client-side
        if (!validateRegistrationForm()) {
            return;
        }
        
        const formData = new FormData(form[0]);
        
        // Disabilita form durante invio
        form.find('input, button').prop('disabled', true);
        
        showLoading('Invio richiesta di registrazione...');
        
        $.ajax({
            url: naval_egt_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    showMessage(response.data.message || 'Richiesta inviata con successo!', 'success');
                    form[0].reset();
                    
                    // Mostra messaggio di conferma dettagliato
                    showRegistrationSuccess(response.data);
                } else {
                    showMessage(response.data || 'Errore durante la registrazione', 'error');
                }
                
                form.find('input, button').prop('disabled', false);
            },
            error: function() {
                hideLoading();
                showMessage('Errore di connessione. Riprova.', 'error');
                form.find('input, button').prop('disabled', false);
            }
        });
    }
    
    function validateRegistrationForm() {
        const password = $('#password').val();
        const passwordConfirm = $('#password_confirm').val();
        const ragioneSociale = $('#ragione_sociale').val();
        const partitaIva = $('#partita_iva').val();
        const privacyPolicy = $('#privacy_policy').is(':checked');
        
        // Verifica password
        if (password !== passwordConfirm) {
            showMessage('Le password non corrispondono', 'error');
            $('#password_confirm').focus();
            return false;
        }
        
        // Verifica P.IVA se ragione sociale presente
        if (ragioneSociale && !partitaIva) {
            showMessage('La Partita IVA è obbligatoria se si specifica la Ragione Sociale', 'error');
            $('#partita_iva').focus();
            return false;
        }
        
        // Verifica privacy policy
        if (!privacyPolicy) {
            showMessage('È necessario accettare la Privacy Policy', 'error');
            return false;
        }
        
        return true;
    }
    
    function validatePasswordConfirm() {
        const password = $('#password').val();
        const passwordConfirm = $('#password_confirm').val();
        const confirmField = $('#password_confirm')[0];
        
        if (passwordConfirm && password !== passwordConfirm) {
            confirmField.setCustomValidity('Le password non corrispondono');
            $('#password_confirm').addClass('error');
        } else {
            confirmField.setCustomValidity('');
            $('#password_confirm').removeClass('error');
        }
    }
    
    function togglePartitaIvaRequired() {
        const ragioneSociale = $('#ragione_sociale').val();
        const partitaIvaField = $('#partita_iva');
        const helpText = partitaIvaField.siblings('small');
        
        if (ragioneSociale.trim()) {
            partitaIvaField.prop('required', true);
            helpText.html('<strong>Obbligatoria se specificata la Ragione Sociale</strong>');
            partitaIvaField.addClass('required');
        } else {
            partitaIvaField.prop('required', false);
            helpText.html('Obbligatoria se specificata la Ragione Sociale');
            partitaIvaField.removeClass('required');
        }
    }
    
    function showRegistrationSuccess(data) {
        const successHtml = `
            <div class="registration-success">
                <div class="success-icon">✅</div>
                <h3>Registrazione completata!</h3>
                <p>La tua richiesta è stata inviata con successo.</p>
                <div class="user-code-box">
                    <strong>Il tuo codice utente è: ${data.user_code || 'TBD'}</strong>
                </div>
                <p>Il tuo account sarà attivato manualmente dal nostro staff. Riceverai una email di conferma.</p>
                <p>Per assistenza contatta: <a href="mailto:tecnica@naval.it">tecnica@naval.it</a></p>
            </div>
        `;
        
        $('.registration-box').html(successHtml);
    }
    
    /**
     * Gestione Upload File
     */
    function initFileUpload() {
        // Input file change
        $('#file-upload').on('change', function() {
            const files = this.files;
            if (files.length > 0) {
                addFilesToQueue(files);
            }
        });
        
        // Drag & Drop
        const filesGrid = $('#files-grid');
        
        filesGrid.on('dragover', function(e) {
            e.preventDefault();
            $(this).addClass('drag-over');
        });
        
        filesGrid.on('dragleave', function(e) {
            e.preventDefault();
            $(this).removeClass('drag-over');
        });
        
        filesGrid.on('drop', function(e) {
            e.preventDefault();
            $(this).removeClass('drag-over');
            
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                addFilesToQueue(files);
            }
        });
        
        // Pulsante refresh file
        $('.btn-refresh').on('click', function() {
            refreshUserFiles();
        });
    }
    
    function addFilesToQueue(files) {
        // Validazione file
        const validFiles = [];
        const errors = [];
        
        Array.from(files).forEach(file => {
            const validation = validateFile(file);
            if (validation.valid) {
                validFiles.push(file);
            } else {
                errors.push(`${file.name}: ${validation.error}`);
            }
        });
        
        if (errors.length > 0) {
            showMessage('Alcuni file non sono validi:\n' + errors.join('\n'), 'error');
        }
        
        if (validFiles.length > 0) {
            uploadFiles(validFiles);
        }
    }
    
    function validateFile(file) {
        // Tipi consentiti (dovrebbe essere sincronizzato con PHP)
        const allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'dwg', 'dxf', 'zip', 'rar'];
        const maxSize = 10 * 1024 * 1024; // 10MB
        
        const extension = file.name.split('.').pop().toLowerCase();
        
        if (!allowedTypes.includes(extension)) {
            return { valid: false, error: 'Tipo di file non consentito' };
        }
        
        if (file.size > maxSize) {
            return { valid: false, error: 'File troppo grande (max 10MB)' };
        }
        
        return { valid: true };
    }
    
    function uploadFiles(files) {
        if (isUploading) {
            showMessage('Upload già in corso, attendi...', 'warning');
            return;
        }
        
        isUploading = true;
        
        const formData = new FormData();
        formData.append('action', 'naval_egt_ajax');
        formData.append('naval_action', 'upload_file');
        formData.append('nonce', naval_egt_ajax.nonce);
        
        Array.from(files).forEach(file => {
            formData.append('files[]', file);
        });
        
        showLoading(`Caricamento ${files.length} file...`);
        
        $.ajax({
            url: naval_egt_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentage = Math.round((e.loaded / e.total) * 100);
                        updateUploadProgress(percentage);
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                hideLoading();
                isUploading = false;
                
                if (response.success) {
                    showMessage(response.data.message || 'File caricati con successo!', 'success');
                    refreshUserFiles();
                    refreshUserActivity();
                    
                    // Reset input file
                    $('#file-upload').val('');
                } else {
                    showMessage(response.data || 'Errore durante il caricamento', 'error');
                }
            },
            error: function() {
                hideLoading();
                isUploading = false;
                showMessage('Errore di connessione durante il caricamento', 'error');
            }
        });
    }
    
    function updateUploadProgress(percentage) {
        const loadingDiv = $('#naval-loading');
        if (loadingDiv.length > 0) {
            loadingDiv.find('p').html(`Caricamento... ${percentage}%`);
        }
    }
    
    /**
     * Gestione Download File
     */
    function initFileDownload() {
        $(document).on('click', '.btn-download', function() {
            const fileId = $(this).data('file-id');
            downloadFile(fileId);
        });
        
        $(document).on('click', '.btn-preview', function() {
            const fileId = $(this).data('file-id');
            const fileName = $(this).data('file-name');
            previewFile(fileId, fileName);
        });
    }
    
    function downloadFile(fileId) {
        const url = `${naval_egt_ajax.ajax_url}?action=naval_egt_download_file&file_id=${fileId}&nonce=${naval_egt_ajax.nonce}`;
        
        // Crea un link temporaneo per il download
        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        // Aggiorna attività dopo download
        setTimeout(refreshUserActivity, 2000);
    }
    
    function previewFile(fileId, fileName) {
        // Per ora mostra solo informazioni file
        // In futuro si può implementare anteprima per PDF/immagini
        const modalContent = `
            <div class="file-preview">
                <div class="file-info">
                    <div class="file-icon">${getFileIcon(fileName)}</div>
                    <h4>${fileName}</h4>
                    <p>Anteprima non disponibile per questo tipo di file.</p>
                    <div class="preview-actions">
                        <button type="button" class="btn-primary" onclick="downloadFile(${fileId})">
                            ⬇️ Scarica File
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        openModal(modalContent, 'Anteprima File');
    }
    
    /**
     * Refresh Dati Utente
     */
    function refreshUserFiles() {
        showLoading('Aggiornamento file...');
        
        $.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_ajax',
            naval_action: 'get_user_files',
            nonce: naval_egt_ajax.nonce
        }, function(response) {
            hideLoading();
            
            if (response.success) {
                displayFiles(response.data.files);
            } else {
                showMessage('Errore nell\'aggiornamento file', 'error');
            }
        });
    }
    
    function refreshUserActivity() {
        $.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_ajax',
            naval_action: 'get_user_activity',
            nonce: naval_egt_ajax.nonce
        }, function(response) {
            if (response.success) {
                displayActivity(response.data.activities);
            }
        });
    }
    
    function refreshUserData() {
        refreshUserFiles();
        refreshUserActivity();
    }
    
    /**
     * Display Functions
     */
    function displayFiles(files) {
        const filesGrid = $('#files-grid');
        
        if (!files || files.length === 0) {
            filesGrid.html(`
                <div class="no-files">
                    <div class="no-files-icon">📄</div>
                    <h4>Nessun file presente</h4>
                    <p>Carica i tuoi primi file utilizzando il pulsante "Carica file" o trascinandoli qui</p>
                </div>
            `);
            return;
        }
        
        let html = '';
        files.forEach(file => {
            const fileIcon = getFileIcon(file.file_name);
            const fileSize = formatFileSize(file.file_size);
            const fileDate = formatDate(file.created_at);
            
            html += `
                <div class="file-item" data-file-id="${file.id}">
                    <div class="file-icon">${fileIcon}</div>
                    <div class="file-info">
                        <div class="file-name" title="${file.file_name}">${file.file_name}</div>
                        <div class="file-meta">
                            <span class="file-size">${fileSize}</span>
                            <span class="file-date">${fileDate}</span>
                        </div>
                    </div>
                    <div class="file-actions">
                        <button type="button" class="btn-icon btn-download" data-file-id="${file.id}" title="Scarica">
                            ⬇️
                        </button>
                        <button type="button" class="btn-icon btn-preview" data-file-id="${file.id}" data-file-name="${file.file_name}" title="Anteprima">
                            👁️
                        </button>
                    </div>
                </div>
            `;
        });
        
        filesGrid.html(html);
        
        // Animazione fade in
        filesGrid.find('.file-item').css('opacity', 0).animate({ opacity: 1 }, 300);
    }
    
    function displayActivity(activities) {
        const activityList = $('#activity-list');
        
        if (!activities || activities.length === 0) {
            activityList.html('<p class="no-activity">Nessuna attività recente</p>');
            return;
        }
        
        let html = '';
        activities.forEach(activity => {
            const activityIcon = getActivityIcon(activity.action);
            const activityDate = formatDateTime(activity.created_at);
            const activityText = getActivityText(activity);
            
            html += `
                <div class="activity-item">
                    <div class="activity-icon">${activityIcon}</div>
                    <div class="activity-content">
                        <div class="activity-text">${activityText}</div>
                        <div class="activity-date">${activityDate}</div>
                    </div>
                </div>
            `;
        });
        
        activityList.html(html);
    }
    
    /**
     * Utility Functions
     */
    function getFileIcon(fileName) {
        const extension = fileName.split('.').pop().toLowerCase();
        const icons = {
            pdf: '📄',
            doc: '📝', docx: '📝',
            xls: '📊', xlsx: '📊',
            jpg: '🖼️', jpeg: '🖼️', png: '🖼️', gif: '🖼️',
            dwg: '📐', dxf: '📐',
            zip: '🗜️', rar: '🗜️'
        };
        return icons[extension] || '📎';
    }
    
    function getActivityIcon(action) {
        const icons = {
            UPLOAD: '⬆️',
            DOWNLOAD: '⬇️',
            LOGIN: '🔑',
            LOGOUT: '🚪'
        };
        return icons[action] || '•';
    }
    
    function getActivityText(activity) {
        switch(activity.action) {
            case 'UPLOAD':
                return `Hai caricato <strong>${activity.file_name}</strong>`;
            case 'DOWNLOAD':
                return `Hai scaricato <strong>${activity.file_name}</strong>`;
            case 'LOGIN':
                return 'Accesso effettuato';
            case 'LOGOUT':
                return 'Logout effettuato';
            default:
                return activity.action;
        }
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('it-IT', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }
    
    function formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('it-IT', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    /**
     * UI Functions
     */
    function showMessage(message, type = 'info') {
        const messageDiv = $(`
            <div class="naval-message naval-message-${type}">
                <span>${message}</span>
                <button type="button" class="message-close">&times;</button>
            </div>
        `);
        
        $('body').append(messageDiv);
        
        // Animazione apparizione
        messageDiv.css({ transform: 'translateX(100%)', opacity: 0 })
                   .animate({ transform: 'translateX(0)', opacity: 1 }, 300);
        
        // Auto-remove dopo 5 secondi
        setTimeout(() => {
            messageDiv.animate({ transform: 'translateX(100%)', opacity: 0 }, 300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Click per chiudere
        messageDiv.find('.message-close').on('click', function() {
            messageDiv.animate({ transform: 'translateX(100%)', opacity: 0 }, 300, function() {
                $(this).remove();
            });
        });
    }
    
    function showLoading(message = 'Caricamento...') {
        if ($('#naval-loading').length === 0) {
            const loadingHtml = `
                <div id="naval-loading" class="naval-loading">
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
    
    function openModal(content, title = '') {
        const modalHtml = `
            <div id="file-modal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>${title}</h3>
                        <span class="modal-close">&times;</span>
                    </div>
                    <div class="modal-body">
                        ${content}
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        $('#file-modal').fadeIn(200);
        
        // Click per chiudere
        $('.modal-close').on('click', closeModal);
        $('.modal').on('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    }
    
    function closeModal() {
        $('#file-modal').fadeOut(200, function() {
            $(this).remove();
        });
    }
    
    /**
     * Responsive Handlers
     */
    function initResponsiveHandlers() {
        // Mobile menu toggle se necessario
        $('.mobile-menu-toggle').on('click', function() {
            $('.dashboard-nav').toggleClass('show');
        });
        
        // Gestione orientamento mobile
        $(window).on('orientationchange', function() {
            setTimeout(function() {
                // Ricalcola layout se necessario
                refreshLayout();
            }, 100);
        });
    }
    
    function refreshLayout() {
        // Implementa refresh layout per mobile
        $('.files-grid').trigger('refresh-layout');
    }
    
    /**
     * Animazioni e UX
     */
    function initAnimations() {
        // Hover effects per file items
        $(document).on('mouseenter', '.file-item', function() {
            $(this).addClass('hover');
        });
        
        $(document).on('mouseleave', '.file-item', function() {
            $(this).removeClass('hover');
        });
        
        // Click animations
        $(document).on('click', '.btn-icon', function() {
            $(this).addClass('clicked');
            setTimeout(() => {
                $(this).removeClass('clicked');
            }, 150);
        });
        
        // Smooth scroll per anchor links
        $('a[href^="#"]').on('click', function(e) {
            e.preventDefault();
            const target = $($(this).attr('href'));
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 100
                }, 500);
            }
        });
    }
    
    /**
     * Cookies e Remember Me
     */
    function checkRememberMeCookie() {
        // Se c'è un cookie remember me, pre-compila il campo
        if (document.cookie.includes('naval_egt_remember=')) {
            $('#remember').prop('checked', true);
        }
    }
    
    function togglePasswordVisibility(button) {
        const input = button.siblings('input[type="password"], input[type="text"]');
        const isPassword = input.attr('type') === 'password';
        
        input.attr('type', isPassword ? 'text' : 'password');
        button.text(isPassword ? '🙈' : '👁️');
    }
    
    // Esponi funzioni globali se necessario
    window.navalEgt = {
        refreshFiles: refreshUserFiles,
        refreshActivity: refreshUserActivity,
        downloadFile: downloadFile,
        showMessage: showMessage
    };
    
})(jQuery);

/**
 * Service Worker per cache offline (opzionale)
 */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        // Registra service worker se disponibile
        // navigator.serviceWorker.register('/sw.js');
    });
}