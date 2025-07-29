/**
 * Naval EGT Plugin - Main JavaScript
 */

(function($) {
    'use strict';

    // Plugin object
    const NavalEGT = {
        
        // Initialize the plugin
        init: function() {
            this.bindEvents();
            this.initTooltips();
            this.initModals();
            this.initFileUpload();
            this.initFormValidation();
            this.autoHideNotices();
        },

        // Bind all events
        bindEvents: function() {
            // Modal events
            $(document).on('click', '.naval-egt-close', this.closeModal);
            $(document).on('click', '.naval-egt-modal', this.closeModalOnBackdrop);
            
            // File upload events
            $(document).on('change', 'input[type="file"]', this.handleFileSelect);
            
            // Form events
            $(document).on('submit', '.naval-egt-form', this.handleFormSubmit);
            
            // Dashboard events
            $(document).on('click', '.naval-egt-refresh-stats', this.refreshStats);
            
            // Keyboard events
            $(document).on('keydown', this.handleKeydown);
        },

        // Initialize tooltips
        initTooltips: function() {
            $('.naval-egt-tooltip').each(function() {
                const $this = $(this);
                const title = $this.attr('title');
                
                if (title) {
                    $this.removeAttr('title').on('mouseenter', function(e) {
                        const tooltip = $('<div class="naval-egt-tooltip-popup">' + title + '</div>');
                        $('body').append(tooltip);
                        
                        const offset = $this.offset();
                        tooltip.css({
                            position: 'absolute',
                            top: offset.top - tooltip.outerHeight() - 10,
                            left: offset.left + ($this.outerWidth() / 2) - (tooltip.outerWidth() / 2),
                            zIndex: 10000
                        });
                        
                    }).on('mouseleave', function() {
                        $('.naval-egt-tooltip-popup').remove();
                    });
                }
            });
        },

        // Initialize modals
        initModals: function() {
            // Prevent modal content clicks from closing modal
            $(document).on('click', '.naval-egt-modal-content', function(e) {
                e.stopPropagation();
            });
        },

        // Initialize file upload functionality
        initFileUpload: function() {
            // Drag and drop support
            $(document).on('dragover dragenter', '.naval-egt-file-upload-area', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });

            $(document).on('dragleave', '.naval-egt-file-upload-area', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
            });

            $(document).on('drop', '.naval-egt-file-upload-area', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
                
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    const fileInput = $(this).find('input[type="file"]')[0];
                    if (fileInput) {
                        fileInput.files = files;
                        $(fileInput).trigger('change');
                    }
                }
            });
        },

        // Initialize form validation
        initFormValidation: function() {
            // Real-time email validation
            $(document).on('input', 'input[type="email"]', function() {
                const email = $(this).val();
                const $formGroup = $(this).closest('.naval-egt-form-group');
                
                if (email && !NavalEGT.isValidEmail(email)) {
                    $formGroup.addClass('has-error');
                    if (!$formGroup.find('.error-message').length) {
                        $formGroup.append('<div class="error-message">Inserisci un indirizzo email valido</div>');
                    }
                } else {
                    $formGroup.removeClass('has-error');
                    $formGroup.find('.error-message').remove();
                }
            });

            // Password strength indicator
            $(document).on('input', 'input[type="password"]', function() {
                const password = $(this).val();
                const $formGroup = $(this).closest('.naval-egt-form-group');
                let strength = NavalEGT.getPasswordStrength(password);
                
                $formGroup.find('.password-strength').remove();
                
                if (password.length > 0) {
                    const strengthIndicator = $(`
                        <div class="password-strength strength-${strength.level}">
                            <div class="strength-bar">
                                <div class="strength-fill" style="width: ${strength.percentage}%"></div>
                            </div>
                            <div class="strength-text">${strength.text}</div>
                        </div>
                    `);
                    $formGroup.append(strengthIndicator);
                }
            });
        },

        // Auto-hide notices after a delay
        autoHideNotices: function() {
            setTimeout(function() {
                $('.naval-egt-notice').fadeOut(500, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        // Handle file selection
        handleFileSelect: function(e) {
            const files = e.target.files;
            const $fileInfo = $(this).siblings('.naval-egt-file-info');
            
            if (files.length > 0) {
                const file = files[0];
                const fileSize = NavalEGT.formatFileSize(file.size);
                const fileName = file.name;
                
                $fileInfo.html(`
                    <div class="selected-file">
                        <span class="file-name">${fileName}</span>
                        <span class="file-size">(${fileSize})</span>
                    </div>
                `);
            } else {
                $fileInfo.empty();
            }
        },

        // Handle form submissions
        handleFormSubmit: function(e) {
            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            
            // Show loading state
            $submitBtn.prop('disabled', true);
            const originalText = $submitBtn.text();
            $submitBtn.html('<span class="naval-egt-loading-spinner"></span> Caricamento...');
            
            // Re-enable button after 10 seconds as fallback
            setTimeout(function() {
                $submitBtn.prop('disabled', false).text(originalText);
            }, 10000);
        },

        // Close modal
        closeModal: function(e) {
            e.preventDefault();
            $(this).closest('.naval-egt-modal').hide();
        },

        // Close modal when clicking backdrop
        closeModalOnBackdrop: function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        },

        // Handle keyboard events
        handleKeydown: function(e) {
            // Close modal on ESC key
            if (e.keyCode === 27) {
                $('.naval-egt-modal:visible').hide();
            }
        },

        // Refresh dashboard stats
        refreshStats: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            $btn.prop('disabled', true);
            
            $.ajax({
                url: naval_egt_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'naval_egt_get_folder_stats',
                    nonce: naval_egt_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        NavalEGT.updateStatsDisplay(response.data);
                        NavalEGT.showNotice('Statistiche aggiornate!', 'success');
                    } else {
                        NavalEGT.showNotice(response.data.message || 'Errore durante l\'aggiornamento', 'error');
                    }
                },
                error: function() {
                    NavalEGT.showNotice('Errore di connessione', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },

        // Update stats display
        updateStatsDisplay: function(stats) {
            $('.naval-egt-stat-card').each(function() {
                const $card = $(this);
                const statType = $card.data('stat-type');
                
                if (stats[statType] !== undefined) {
                    const $number = $card.find('.naval-egt-stat-number');
                    const currentValue = parseInt($number.text());
                    const newValue = statType.includes('size') ? 
                        NavalEGT.formatFileSize(stats[statType]) : 
                        stats[statType];
                    
                    // Animate the change
                    if (statType.includes('size')) {
                        $number.text(newValue);
                    } else {
                        NavalEGT.animateNumber($number, currentValue, newValue);
                    }
                }
            });
        },

        // Animate number changes
        animateNumber: function($element, from, to) {
            $({ counter: from }).animate({ counter: to }, {
                duration: 1000,
                easing: 'swing',
                step: function() {
                    $element.text(Math.ceil(this.counter));
                },
                complete: function() {
                    $element.text(to);
                }
            });
        },

        // Show notice
        showNotice: function(message, type = 'info') {
            const notice = $(`
                <div class="naval-egt-notice naval-egt-notice-${type}">
                    <p>${message}</p>
                </div>
            `);
            
            // Remove existing notices
            $('.naval-egt-notice').remove();
            
            // Add new notice
            if ($('.naval-egt-dashboard').length) {
                $('.naval-egt-dashboard').prepend(notice);
            } else if ($('.naval-egt-area-riservata').length) {
                $('.naval-egt-area-riservata').prepend(notice);
            } else {
                $('body').prepend(notice);
            }
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                notice.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        // Utility functions
        isValidEmail: function(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },

        getPasswordStrength: function(password) {
            let score = 0;
            
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;
            
            const levels = [
                { level: 'very-weak', text: 'Molto debole', percentage: 10 },
                { level: 'weak', text: 'Debole', percentage: 25 },
                { level: 'fair', text: 'Sufficiente', percentage: 50 },
                { level: 'good', text: 'Buona', percentage: 75 },
                { level: 'strong', text: 'Forte', percentage: 90 },
                { level: 'very-strong', text: 'Molto forte', percentage: 100 }
            ];
            
            return levels[Math.min(score, 5)];
        },

        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 B';
            
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        formatDate: function(timestamp) {
            const date = new Date(timestamp * 1000);
            return date.toLocaleDateString('it-IT') + ' ' + 
                   date.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
        },

        // Debounce function for search inputs
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
        },

        // Copy text to clipboard
        copyToClipboard: function(text) {
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(text).then(function() {
                    NavalEGT.showNotice('Copiato negli appunti!', 'success');
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                try {
                    document.execCommand('copy');
                    NavalEGT.showNotice('Copiato negli appunti!', 'success');
                } catch (err) {
                    NavalEGT.showNotice('Impossibile copiare negli appunti', 'error');
                }
                
                document.body.removeChild(textArea);
            }
        },

        // Smooth scroll to element
        scrollTo: function(target, offset = 0) {
            const $target = $(target);
            if ($target.length) {
                $('html, body').animate({
                    scrollTop: $target.offset().top - offset
                }, 500);
            }
        },

        // Check if element is in viewport
        isInViewport: function(element) {
            const rect = element.getBoundingClientRect();
            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
        },

        // Lazy load images
        lazyLoadImages: function() {
            const images = document.querySelectorAll('img[data-src]');
            
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });
            
            images.forEach(img => imageObserver.observe(img));
        },

        // Print functionality
        printPage: function() {
            window.print();
        },

        // Export data to CSV
        exportToCSV: function(data, filename = 'export.csv') {
            const csv = this.arrayToCSV(data);
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            
            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        },

        // Convert array to CSV format
        arrayToCSV: function(data) {
            if (!data.length) return '';
            
            const headers = Object.keys(data[0]);
            const csvContent = [
                headers.join(','),
                ...data.map(row => 
                    headers.map(header => {
                        const value = row[header];
                        return typeof value === 'string' && value.includes(',') 
                            ? `"${value}"` 
                            : value;
                    }).join(',')
                )
            ].join('\n');
            
            return csvContent;
        }
    };

    // FTP Manager specific functionality
    const FTPManager = {
        currentPath: '',
        
        init: function() {
            this.bindEvents();
            this.loadFileList('');
        },

        bindEvents: function() {
            // File upload form
            $(document).on('submit', '#naval-egt-upload-form', this.handleFileUpload);
            
            // Create folder form
            $(document).on('submit', '#naval-egt-folder-form', this.handleCreateFolder);
            
            // File actions
            $(document).on('click', '.naval-egt-file-download', this.handleFileDownload);
            $(document).on('click', '.naval-egt-file-delete', this.handleFileDelete);
            $(document).on('click', '.naval-egt-folder-open', this.handleFolderOpen);
            
            // Refresh button
            $(document).on('click', '.naval-egt-refresh-files', this.refreshFileList);
        },

        loadFileList: function(path) {
            this.currentPath = path;
            
            const $fileList = $('#naval-egt-file-list');
            $fileList.html('<div class="naval-egt-loading">Caricamento file...</div>');
            
            $.ajax({
                url: naval_egt_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'naval_egt_list_files',
                    nonce: naval_egt_ajax.nonce,
                    path: path
                },
                success: function(response) {
                    if (response.success) {
                        FTPManager.displayFileList(response.data);
                        FTPManager.updateCurrentPath(path);
                    } else {
                        NavalEGT.showNotice(response.data.message || 'Errore durante il caricamento', 'error');
                        $fileList.html('<div class="naval-egt-empty-folder">Errore durante il caricamento</div>');
                    }
                },
                error: function() {
                    NavalEGT.showNotice('Errore di connessione', 'error');
                    $fileList.html('<div class="naval-egt-empty-folder">Errore di connessione</div>');
                }
            });
        },

        displayFileList: function(files) {
            const $fileList = $('#naval-egt-file-list');
            
            if (files.length === 0) {
                $fileList.html('<div class="naval-egt-empty-folder">Cartella vuota</div>');
                return;
            }
            
            let html = '<div class="naval-egt-file-grid">';
            
            files.forEach(file => {
                html += this.renderFileItem(file);
            });
            
            html += '</div>';
            $fileList.html(html);
        },

        renderFileItem: function(file) {
            const icon = this.getFileIcon(file.type, file.extension);
            const isFolder = file.type === 'folder';
            const canDownload = file.can_download && !isFolder;
            const canDelete = file.path.startsWith('uploads/') && !isFolder;
            
            return `
                <div class="naval-egt-file-item" data-type="${file.type}">
                    <div class="naval-egt-file-icon">${icon}</div>
                    <div class="naval-egt-file-info">
                        <div class="naval-egt-file-name">${file.name}</div>
                        ${!isFolder ? `<div class="naval-egt-file-size">${NavalEGT.formatFileSize(file.size)}</div>` : ''}
                        <div class="naval-egt-file-date">${NavalEGT.formatDate(file.modified)}</div>
                    </div>
                    <div class="naval-egt-file-actions">
                        ${isFolder ? `<button class="naval-egt-btn-sm naval-egt-folder-open" data-path="${file.path}">Apri</button>` : ''}
                        ${canDownload ? `<button class="naval-egt-btn-sm naval-egt-file-download" data-path="${file.path}">Scarica</button>` : ''}
                        ${canDelete ? `<button class="naval-egt-btn-sm naval-egt-btn-danger naval-egt-file-delete" data-path="${file.path}">Elimina</button>` : ''}
                    </div>
                </div>
            `;
        },

        getFileIcon: function(type, extension) {
            if (type === 'folder') return '📁';
            
            const icons = {
                'pdf': '📄',
                'doc': '📝', 'docx': '📝',
                'xls': '📊', 'xlsx': '📊',
                'ppt': '📊', 'pptx': '📊',
                'zip': '🗜️', 'rar': '🗜️', '7z': '🗜️',
                'jpg': '🖼️', 'jpeg': '🖼️', 'png': '🖼️', 'gif': '🖼️', 'bmp': '🖼️', 'tiff': '🖼️',
                'dwg': '📐', 'dxf': '📐',
                'step': '⚙️', 'stp': '⚙️', 'iges': '⚙️', 'igs': '⚙️',
                'txt': '📝', 'rtf': '📝'
            };
            
            return icons[extension?.toLowerCase()] || '📄';
        },

        updateCurrentPath: function(path) {
            $('#current-path').text('/' + path);
        },

        handleFileUpload: function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            const fileInput = document.getElementById('ftp_file');
            const file = fileInput.files[0];
            
            if (!file) {
                NavalEGT.showNotice('Seleziona un file', 'error');
                return;
            }
            
            formData.append('action', 'naval_egt_upload_file');
            formData.append('nonce', naval_egt_ajax.nonce);
            formData.append('file', file);
            formData.append('subfolder', document.getElementById('subfolder').value);
            
            const $progressBar = $('#upload-progress');
            const $progressFill = $('.naval-egt-progress-fill');
            
            $progressBar.show();
            $progressFill.css('width', '0%');
            
            $.ajax({
                url: naval_egt_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            const percentComplete = (evt.loaded / evt.total) * 100;
                            $progressFill.css('width', percentComplete + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    $progressBar.hide();
                    if (response.success) {
                        NavalEGT.showNotice(response.data.message, 'success');
                        $('#naval-egt-upload-modal').hide();
                        FTPManager.refreshFileList();
                        document.getElementById('naval-egt-upload-form').reset();
                    } else {
                        NavalEGT.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    $progressBar.hide();
                    NavalEGT.showNotice('Errore durante il caricamento', 'error');
                }
            });
        },

        handleCreateFolder: function(e) {
            e.preventDefault();
            
            const folderName = document.getElementById('folder_name').value;
            
            if (!folderName) {
                NavalEGT.showNotice('Inserisci il nome della cartella', 'error');
                return;
            }
            
            $.ajax({
                url: naval_egt_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'naval_egt_create_folder',
                    nonce: naval_egt_ajax.nonce,
                    folder_name: folderName,
                    parent_folder: FTPManager.currentPath
                },
                success: function(response) {
                    if (response.success) {
                        NavalEGT.showNotice(response.data.message, 'success');
                        $('#naval-egt-folder-modal').hide();
                        FTPManager.refreshFileList();
                        document.getElementById('naval-egt-folder-form').reset();
                    } else {
                        NavalEGT.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    NavalEGT.showNotice('Errore durante la creazione della cartella', 'error');
                }
            });
        },

        handleFileDownload: function(e) {
            e.preventDefault();
            const filePath = $(this).data('path');
            const downloadUrl = naval_egt_ajax.ajax_url + 
                '?action=naval_egt_download_file&nonce=' + naval_egt_ajax.nonce + 
                '&file=' + encodeURIComponent(filePath);
            
            window.location.href = downloadUrl;
        },

        handleFileDelete: function(e) {
            e.preventDefault();
            
            if (!confirm('Sei sicuro di voler eliminare questo file?')) {
                return;
            }
            
            const filePath = $(this).data('path');
            
            $.ajax({
                url: naval_egt_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'naval_egt_delete_file',
                    nonce: naval_egt_ajax.nonce,
                    file_path: filePath
                },
                success: function(response) {
                    if (response.success) {
                        NavalEGT.showNotice(response.data.message, 'success');
                        FTPManager.refreshFileList();
                    } else {
                        NavalEGT.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    NavalEGT.showNotice('Errore durante l\'eliminazione', 'error');
                }
            });
        },

        handleFolderOpen: function(e) {
            e.preventDefault();
            const path = $(this).data('path');
            FTPManager.loadFileList(path);
        },

        refreshFileList: function() {
            FTPManager.loadFileList(FTPManager.currentPath);
        }
    };

    // Document ready
    $(document).ready(function() {
        NavalEGT.init();
        
        // Initialize FTP Manager if present
        if ($('#naval-egt-ftp-manager').length) {
            FTPManager.init();
        }
        
        // Initialize lazy loading if supported
        if ('IntersectionObserver' in window) {
            NavalEGT.lazyLoadImages();
        }
    });

    // Make NavalEGT globally available
    window.NavalEGT = NavalEGT;
    window.FTPManager = FTPManager;

})(jQuery);

// Global functions for backward compatibility
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
    if (window.FTPManager) {
        window.FTPManager.refreshFileList();
    }
}