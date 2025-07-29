/**
 * Naval EGT Plugin - Admin JavaScript
 */

(function($) {
    'use strict';

    // Admin object
    const NavalEGTAdmin = {
        
        // Current data
        currentPage: 1,
        currentFilter: '',
        currentSearch: '',
        
        // Initialize admin functionality
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initDataTables();
            this.initModals();
            this.initCharts();
            this.loadDashboardData();
        },

        // Bind all admin events
        bindEvents: function() {
            // Tab navigation
            $(document).on('click', '.naval-egt-admin-tab-nav button', this.switchTab);
            
            // Modal events
            $(document).on('click', '.naval-egt-admin-modal-close', this.closeModal);
            $(document).on('click', '.naval-egt-admin-modal', this.closeModalOnBackdrop);
            
            // User management
            $(document).on('click', '.btn-edit-user', this.editUser);
            $(document).on('click', '.btn-delete-user', this.deleteUser);
            $(document).on('click', '.btn-change-status', this.changeUserStatus);
            $(document).on('click', '.btn-send-notification', this.sendNotification);
            
            // File management
            $(document).on('click', '.btn-upload-for-user', this.uploadFileForUser);
            $(document).on('change', '#admin-file-upload', this.handleAdminFileUpload);
            
            // Settings
            $(document).on('submit', '#naval-egt-settings-form', this.saveSettings);
            
            // Search and filters
            $(document).on('input', '.admin-search-input', this.debounce(this.handleSearch, 500));
            $(document).on('change', '.admin-filter-select', this.handleFilter);
            
            // Pagination
            $(document).on('click', '.admin-pagination button', this.handlePagination);
            
            // Export functions
            $(document).on('click', '.btn-export-users', this.exportUsers);
            $(document).on('click', '.btn-export-logs', this.exportLogs);
            
            // Bulk actions
            $(document).on('change', '.select-all-users', this.toggleSelectAll);
            $(document).on('click', '.btn-bulk-action', this.handleBulkAction);
            
            // Log viewer
            $(document).on('click', '.btn-view-logs', this.viewLogs);
            $(document).on('change', '#log-filter-user', this.filterLogs);
            $(document).on('change', '#log-filter-action', this.filterLogs);
        },

        // Initialize tabs
        initTabs: function() {
            const $firstTab = $('.naval-egt-admin-tab-nav button:first');
            if ($firstTab.length) {
                $firstTab.addClass('active');
                const targetPane = $firstTab.data('tab');
                $('.naval-egt-admin-tab-pane').removeClass('active');
                $(`#${targetPane}`).addClass('active');
            }
        },

        // Initialize data tables
        initDataTables: function() {
            this.loadUsers();
        },

        // Initialize modals
        initModals: function() {
            // Prevent modal content clicks from closing modal
            $(document).on('click', '.naval-egt-admin-modal-content', function(e) {
                e.stopPropagation();
            });
        },

        // Initialize charts (if needed)
        initCharts: function() {
            // Placeholder for future chart implementation
            this.createStatsChart();
        },

        // Load dashboard data
        loadDashboardData: function() {
            this.updateDashboardStats();
            this.loadRecentActivity();
        },

        // Switch tabs
        switchTab: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const targetTab = $btn.data('tab');
            
            // Update nav
            $('.naval-egt-admin-tab-nav button').removeClass('active');
            $btn.addClass('active');
            
            // Update content
            $('.naval-egt-admin-tab-pane').removeClass('active');
            $(`#${targetTab}`).addClass('active');
            
            // Load tab-specific data
            if (targetTab === 'users') {
                NavalEGTAdmin.loadUsers();
            } else if (targetTab === 'logs') {
                NavalEGTAdmin.loadLogs();
            }
        },

        // Load users table
        loadUsers: function(page = 1, search = '', status = '') {
            this.currentPage = page;
            this.currentSearch = search;
            this.currentFilter = status;
            
            const $container = $('#users-table-container');
            $container.html('<div class="naval-egt-admin-loading"><div class="naval-egt-admin-loading-spinner"></div><p>Caricamento utenti...</p></div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'naval_egt_admin_list_users',
                    nonce: naval_egt_admin.nonce,
                    page: page,
                    per_page: 20,
                    search: search,
                    status: status
                },
                success: function(response) {
                    if (response.success) {
                        NavalEGTAdmin.renderUsersTable(response.data);
                    } else {
                        $container.html('<div class="naval-egt-admin-empty">Errore durante il caricamento degli utenti</div>');
                    }
                },
                error: function() {
                    $container.html('<div class="naval-egt-admin-empty">Errore di connessione</div>');
                }
            });
        },

        // Render users table
        renderUsersTable: function(data) {
            const users = data.users;
            const total = data.total;
            const page = data.page;
            const perPage = data.per_page;
            
            let html = `
                <div class="naval-egt-admin-table-container">
                    <div class="naval-egt-admin-table-header">
                        <h3>Utenti Area Riservata (${total})</h3>
                        <div class="naval-egt-admin-table-filters">
                            <input type="text" class="admin-search-input" placeholder="Cerca utenti..." value="${this.currentSearch}">
                            <select class="admin-filter-select" id="status-filter">
                                <option value="">Tutti gli status</option>
                                <option value="active" ${this.currentFilter === 'active' ? 'selected' : ''}>Attivi</option>
                                <option value="pending" ${this.currentFilter === 'pending' ? 'selected' : ''}>In attesa</option>
                                <option value="suspended" ${this.currentFilter === 'suspended' ? 'selected' : ''}>Sospesi</option>
                            </select>
                            <button class="naval-egt-admin-btn naval-egt-admin-btn-primary btn-export-users">Esporta</button>
                        </div>
                    </div>
                    <table class="naval-egt-admin-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" class="select-all-users"></th>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Azienda</th>
                                <th>Status</th>
                                <th>Registrato</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            if (users.length === 0) {
                html += '<tr><td colspan="7" class="naval-egt-admin-empty">Nessun utente trovato</td></tr>';
            } else {
                users.forEach(user => {
                    html += `
                        <tr>
                            <td><input type="checkbox" class="user-checkbox" value="${user.id}"></td>
                            <td>${user.display_name}</td>
                            <td>${user.email}</td>
                            <td>${user.company || '-'}</td>
                            <td><span class="naval-egt-status-badge naval-egt-status-${user.status}">${this.getStatusText(user.status)}</span></td>
                            <td>${this.formatDate(user.registered)}</td>
                            <td class="naval-egt-actions">
                                <button class="naval-egt-btn-sm naval-egt-btn-edit btn-edit-user" data-user-id="${user.id}">Modifica</button>
                                <button class="naval-egt-btn-sm naval-egt-btn-activate btn-change-status" data-user-id="${user.id}" data-status="${user.status === 'active' ? 'suspended' : 'active'}">
                                    ${user.status === 'active' ? 'Sospendi' : 'Attiva'}
                                </button>
                                <button class="naval-egt-btn-sm btn-send-notification" data-user-id="${user.id}">Notifica</button>
                                <button class="naval-egt-btn-sm btn-upload-for-user" data-user-id="${user.id}">Upload</button>
                                <button class="naval-egt-btn-sm naval-egt-btn-delete btn-delete-user" data-user-id="${user.id}">Elimina</button>
                            </td>
                        </tr>
                    `;
                });
            }
            
            html += `
                        </tbody>
                    </table>
                    ${this.renderPagination(page, perPage, total)}
                </div>
                
                <!-- Bulk Actions -->
                <div class="naval-egt-admin-bulk-actions" style="margin-top: 15px;">
                    <select id="bulk-action-select">
                        <option value="">Azioni multiple</option>
                        <option value="activate">Attiva selezionati</option>
                        <option value="suspend">Sospendi selezionati</option>
                        <option value="delete">Elimina selezionati</option>
                    </select>
                    <button class="naval-egt-admin-btn naval-egt-admin-btn-secondary btn-bulk-action">Applica</button>
                </div>
            `;
            
            $('#users-table-container').html(html);
        },

        // Render pagination
        renderPagination: function(currentPage, perPage, total) {
            const totalPages = Math.ceil(total / perPage);
            
            if (totalPages <= 1) return '';
            
            let html = '<div class="naval-egt-admin-pagination">';
            
            // Previous button
            html += `<button ${currentPage <= 1 ? 'disabled' : ''} data-page="${currentPage - 1}">« Precedente</button>`;
            
            // Page numbers
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);
            
            if (startPage > 1) {
                html += '<button data-page="1">1</button>';
                if (startPage > 2) html += '<span>...</span>';
            }
            
            for (let i = startPage; i <= endPage; i++) {
                html += `<button ${i === currentPage ? 'class="active"' : ''} data-page="${i}">${i}</button>`;
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) html += '<span>...</span>';
                html += `<button data-page="${totalPages}">${totalPages}</button>`;
            }
            
            // Next button
            html += `<button ${currentPage >= totalPages ? 'disabled' : ''} data-page="${currentPage + 1}">Successiva »</button>`;
            
            html += `<div class="page-info">Pagina ${currentPage} di ${totalPages} (${total} utenti totali)</div>`;
            html += '</div>';
            
            return html;
        },

        // Load logs
        loadLogs: function(page = 1, userId = '', action = '') {
            const $container = $('#logs-table-container');
            $container.html('<div class="naval-egt-admin-loading"><div class="naval-egt-admin-loading-spinner"></div><p>Caricamento log...</p></div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'naval_egt_admin_get_logs',
                    nonce: naval_egt_admin.nonce,
                    page: page,
                    per_page: 50,
                    user_id: userId,
                    action_filter: action
                },
                success: function(response) {
                    if (response.success) {
                        NavalEGTAdmin.renderLogsTable(response.data);
                    } else {
                        $container.html('<div class="naval-egt-admin-empty">Errore durante il caricamento dei log</div>');
                    }
                },
                error: function() {
                    $container.html('<div class="naval-egt-admin-empty">Errore di connessione</div>');
                }
            });
        },

        // Render logs table
        renderLogsTable: function(data) {
            const logs = data.logs;
            const total = data.total;
            const page = data.page;
            const perPage = data.per_page;
            
            let html = `
                <div class="naval-egt-admin-table-container">
                    <div class="naval-egt-admin-table-header">
                        <h3>Log Attività (${total})</h3>
                        <div class="naval-egt-admin-table-filters">
                            <select id="log-filter-user">
                                <option value="">Tutti gli utenti</option>
                            </select>
                            <select id="log-filter-action">
                                <option value="">Tutte le azioni</option>
                                <option value="upload">Upload</option>
                                <option value="download">Download</option>
                                <option value="delete">Eliminazione</option>
                                <option value="create_folder">Creazione cartella</option>
                            </select>
                            <button class="naval-egt-admin-btn naval-egt-admin-btn-primary btn-export-logs">Esporta Log</button>
                        </div>
                    </div>
                    <table class="naval-egt-admin-table">
                        <thead>
                            <tr>
                                <th>Data/Ora</th>
                                <th>Utente</th>
                                <th>Azione</th>
                                <th>File</th>
                                <th>Dimensione</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            if (logs.length === 0) {
                html += '<tr><td colspan="6" class="naval-egt-admin-empty">Nessun log trovato</td></tr>';
            } else {
                logs.forEach(log => {
                    html += `
                        <tr>
                            <td>${this.formatDateTime(log.created_at)}</td>
                            <td>${log.display_name || 'Utente eliminato'}<br><small>${log.user_email || ''}</small></td>
                            <td><span class="action-badge action-${log.action}">${this.getActionText(log.action)}</span></td>
                            <td>${log.file_name || '-'}<br><small>${log.file_path || ''}</small></td>
                            <td>${log.file_size ? this.formatFileSize(log.file_size) : '-'}</td>
                            <td>${log.ip_address}</td>
                        </tr>
                    `;
                });
            }
            
            html += `
                        </tbody>
                    </table>
                    ${this.renderPagination(page, perPage, total)}
                </div>
            `;
            
            $('#logs-table-container').html(html);
        },

        // Edit user modal
        editUser: function(e) {
            e.preventDefault();
            const userId = $(this).data('user-id');
            
            // Show modal with user data (implement as needed)
            NavalEGTAdmin.showNotice('Funzionalità di modifica utente in sviluppo', 'info');
        },

        // Delete user
        deleteUser: function(e) {
            e.preventDefault();
            
            if (!confirm('Sei sicuro di voler eliminare questo utente? Questa azione non può essere annullata.')) {
                return;
            }
            
            const userId = $(this).data('user-id');
            const $btn = $(this);
            
            $btn.prop('disabled', true).text('Eliminazione...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'naval_egt_admin_delete_user',
                    nonce: naval_egt_admin.nonce,
                    user_id: userId
                },
                success: function(response) {
                    if (response.success) {
                        NavalEGTAdmin.showNotice(response.data.message, 'success');
                        NavalEGTAdmin.loadUsers(NavalEGTAdmin.currentPage, NavalEGTAdmin.currentSearch, NavalEGTAdmin.currentFilter);
                    } else {
                        NavalEGTAdmin.showNotice(response.data.message, 'error');
                        $btn.prop('disabled', false).text('Elimina');
                    }
                },
                error: function() {
                    NavalEGTAdmin.showNotice('Errore durante l\'eliminazione', 'error');
                    $btn.prop('disabled', false).text('Elimina');
                }
            });
        },

        // Change user status
        changeUserStatus: function(e) {
            e.preventDefault();
            
            const userId = $(this).data('user-id');
            const newStatus = $(this).data('status');
            const $btn = $(this);
            
            $btn.prop('disabled', true);
            
            $.ajax({
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
                        NavalEGTAdmin.showNotice(response.data.message, 'success');
                        NavalEGTAdmin.loadUsers(NavalEGTAdmin.currentPage, NavalEGTAdmin.currentSearch, NavalEGTAdmin.currentFilter);
                    } else {
                        NavalEGTAdmin.showNotice(response.data.message, 'error');
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    NavalEGTAdmin.showNotice('Errore durante l\'aggiornamento', 'error');
                    $btn.prop('disabled', false);
                }
            });
        },

        // Send notification to user
        sendNotification: function(e) {
            e.preventDefault();
            const userId = $(this).data('user-id');
            
            const subject = prompt('Oggetto della notifica:');
            if (!subject) return;
            
            const message = prompt('Messaggio:');
            if (!message) return;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'naval_egt_admin_send_notification',
                    nonce: naval_egt_admin.nonce,
                    user_id: userId,
                    subject: subject,
                    message: message
                },
                success: function(response) {
                    if (response.success) {
                        NavalEGTAdmin.showNotice(response.data.message, 'success');
                    } else {
                        NavalEGTAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    NavalEGTAdmin.showNotice('Errore durante l\'invio della notifica', 'error');
                }
            });
        },

        // Upload file for user
        uploadFileForUser: function(e) {
            e.preventDefault();
            const userId = $(this).data('user-id');
            
            // Create a temporary file input
            const fileInput = $('<input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.zip,.jpg,.jpeg,.png,.dwg,.dxf">');
            fileInput.click();
            
            fileInput.change(function() {
                const files = this.files;
                if (files.length === 0) return;
                
                const formData = new FormData();
                formData.append('action', 'naval_egt_admin_upload_file_for_user');
                formData.append('nonce', naval_egt_admin.nonce);
                formData.append('user_id', userId);
                formData.append('subfolder', 'downloads/');
                formData.append('file', files[0]);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            NavalEGTAdmin.showNotice(response.data.message, 'success');
                        } else {
                            NavalEGTAdmin.showNotice(response.data.message, 'error');
                        }
                    },
                    error: function() {
                        NavalEGTAdmin.showNotice('Errore durante il caricamento', 'error');
                    }
                });
            });
        },

        // Handle search
        handleSearch: function(e) {
            const search = $(this).val();
            NavalEGTAdmin.loadUsers(1, search, NavalEGTAdmin.currentFilter);
        },

        // Handle filter
        handleFilter: function(e) {
            const filter = $(this).val();
            NavalEGTAdmin.loadUsers(1, NavalEGTAdmin.currentSearch, filter);
        },

        // Handle pagination
        handlePagination: function(e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'));
            if (page && !$(this).prop('disabled')) {
                NavalEGTAdmin.loadUsers(page, NavalEGTAdmin.currentSearch, NavalEGTAdmin.currentFilter);
            }
        },

        // Toggle select all
        toggleSelectAll: function() {
            const isChecked = $(this).prop('checked');
            $('.user-checkbox').prop('checked', isChecked);
        },

        // Handle bulk actions
        handleBulkAction: function(e) {
            e.preventDefault();
            
            const action = $('#bulk-action-select').val();
            if (!action) {
                NavalEGTAdmin.showNotice('Seleziona un\'azione', 'warning');
                return;
            }
            
            const selectedUsers = $('.user-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedUsers.length === 0) {
                NavalEGTAdmin.showNotice('Seleziona almeno un utente', 'warning');
                return;
            }
            
            if (!confirm(`Sei sicuro di voler applicare l'azione "${action}" a ${selectedUsers.length} utenti?`)) {
                return;
            }
            
            // Process bulk action
            NavalEGTAdmin.showNotice('Funzionalità di azioni multiple in sviluppo', 'info');
        },

        // Update dashboard stats
        updateDashboardStats: function() {
            // Simulate stats update
            const stats = {
                totalUsers: Math.floor(Math.random() * 100) + 50,
                activeUsers: Math.floor(Math.random() * 80) + 30,
                totalFiles: Math.floor(Math.random() * 500) + 200,
                storageUsed: Math.floor(Math.random() * 10) + 5 // GB
            };
            
            $('.stat-total-users .naval-egt-stat-number').text(stats.totalUsers);
            $('.stat-active-users .naval-egt-stat-number').text(stats.activeUsers);
            $('.stat-total-files .naval-egt-stat-number').text(stats.totalFiles);
            $('.stat-storage-used .naval-egt-stat-number').text(stats.storageUsed + ' GB');
        },

        // Load recent activity
        loadRecentActivity: function() {
            // This would load recent activity data
        },

        // Create stats chart
        createStatsChart: function() {
            // Placeholder for chart implementation
            const canvas = document.getElementById('stats-chart');
            if (canvas) {
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#2a5298';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.fillStyle = 'white';
                ctx.font = '16px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('Grafico statistiche', canvas.width/2, canvas.height/2);
            }
        },

        // Export users
        exportUsers: function(e) {
            e.preventDefault();
            
            const exportUrl = ajaxurl + '?action=naval_egt_export_users&nonce=' + naval_egt_admin.nonce;
            window.location.href = exportUrl;
        },

        // Export logs
        exportLogs: function(e) {
            e.preventDefault();
            
            const exportUrl = ajaxurl + '?action=naval_egt_export_logs&nonce=' + naval_egt_admin.nonce;
            window.location.href = exportUrl;
        },

        // Filter logs
        filterLogs: function() {
            const userId = $('#log-filter-user').val();
            const action = $('#log-filter-action').val();
            NavalEGTAdmin.loadLogs(1, userId, action);
        },

        // Save settings
        saveSettings: function(e) {
            e.preventDefault();
            
            const formData = $(this).serialize();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData + '&action=naval_egt_save_settings&nonce=' + naval_egt_admin.nonce,
                success: function(response) {
                    if (response.success) {
                        NavalEGTAdmin.showNotice('Impostazioni salvate!', 'success');
                    } else {
                        NavalEGTAdmin.showNotice('Errore durante il salvataggio', 'error');
                    }
                },
                error: function() {
                    NavalEGTAdmin.showNotice('Errore di connessione', 'error');
                }
            });
        },

        // Modal functions
        closeModal: function(e) {
            e.preventDefault();
            $(this).closest('.naval-egt-admin-modal').hide();
        },

        closeModalOnBackdrop: function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        },

        // Show admin notice
        showNotice: function(message, type = 'info') {
            const notice = $(`
                <div class="naval-egt-admin-notice naval-egt-admin-notice-${type}">
                    <p>${message}</p>
                </div>
            `);
            
            // Remove existing notices
            $('.naval-egt-admin-notice').remove();
            
            // Add new notice
            $('.naval-egt-admin-wrap').prepend(notice);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                notice.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        // Utility functions
        getStatusText: function(status) {
            const statusTexts = {
                'active': 'Attivo',
                'pending': 'In attesa',
                'suspended': 'Sospeso'
            };
            return statusTexts[status] || status;
        },

        getActionText: function(action) {
            const actionTexts = {
                'upload': 'Caricamento',
                'download': 'Scaricamento',
                'delete': 'Eliminazione',
                'create_folder': 'Creazione cartella'
            };
            return actionTexts[action] || action;
        },

        formatDate: function(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('it-IT');
        },

        formatDateTime: function(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('it-IT') + ' ' + 
                   date.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
        },

        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 B';
            
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        // Debounce function
        debounce: function(func, wait, immediate) {
            let timeout;
            return function executedFunction() {
                const context = this;
                const args = arguments;
                const later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        }
    };

    // Document ready
    $(document).ready(function() {
        NavalEGTAdmin.init();
    });

    // Make NavalEGTAdmin globally available
    window.NavalEGTAdmin = NavalEGTAdmin;

})(jQuery);