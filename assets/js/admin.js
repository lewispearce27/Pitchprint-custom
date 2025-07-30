/**
 * PitchPrint Admin JavaScript
 */

(function($) {
    'use strict';

    var PitchPrintAdmin = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;
            
            // Test connection button
            $('#test-connection').on('click', function(e) {
                e.preventDefault();
                self.testConnection();
            });
            
            // View design in order
            $(document).on('click', '.view-pitchprint-design', function(e) {
                e.preventDefault();
                var projectId = $(this).data('project-id');
                self.viewDesign(projectId);
            });
            
            // Download PDF in order
            $(document).on('click', '.download-pitchprint-pdf', function(e) {
                e.preventDefault();
                var projectId = $(this).data('project-id');
                self.downloadPDF(projectId);
            });
            
            // Auto-save API keys when changed
            $('#pitchprint_api_key, #pitchprint_secret_key').on('blur', function() {
                var $form = $(this).closest('form');
                if ($form.length && $(this).val() !== $(this).data('original-value')) {
                    self.showNotice('Remember to save your settings!', 'warning');
                }
            });
        },
        
        /**
         * Test API connection
         */
        testConnection: function() {
            var $button = $('#test-connection');
            var $status = $('#connection-status');
            
            $button.prop('disabled', true).text('Testing...');
            $status.html('');
            
            $.ajax({
                url: pitchprint_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'pitchprint_test_connection',
                    nonce: pitchprint_admin_vars.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html(
                            '<div class="notice notice-success"><p>' + 
                            response.data.message + 
                            '</p></div>'
                        );
                    } else {
                        $status.html(
                            '<div class="notice notice-error"><p>' + 
                            response.data.message + 
                            '</p></div>'
                        );
                    }
                },
                error: function() {
                    $status.html(
                        '<div class="notice notice-error"><p>' + 
                        'Connection test failed. Please check your settings.' + 
                        '</p></div>'
                    );
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        },
        
        /**
         * View design
         */
        viewDesign: function(projectId) {
            if (!projectId) {
                alert('Invalid project ID');
                return;
            }
            
            // Create a modal or popup to show the design
            var modal = $('<div class="pitchprint-modal">' +
                '<div class="modal-content">' +
                '<span class="close">&times;</span>' +
                '<h2>Design Preview</h2>' +
                '<div class="design-preview-container">' +
                '<p>Loading design...</p>' +
                '</div>' +
                '</div>' +
                '</div>');
            
            $('body').append(modal);
            
            // Close modal
            modal.find('.close').on('click', function() {
                modal.remove();
            });
            
            // Load design preview
            this.loadDesignPreview(projectId, modal.find('.design-preview-container'));
        },
        
        /**
         * Load design preview
         */
        loadDesignPreview: function(projectId, container) {
            $.ajax({
                url: pitchprint_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'pitchprint_get_project',
                    nonce: pitchprint_admin_vars.nonce,
                    project_id: projectId
                },
                success: function(response) {
                    if (response.success && response.data.previews) {
                        var html = '';
                        response.data.previews.forEach(function(preview, index) {
                            html += '<img src="' + preview + '" alt="Page ' + (index + 1) + '" />';
                        });
                        container.html(html);
                    } else {
                        container.html('<p>Unable to load design preview.</p>');
                    }
                },
                error: function() {
                    container.html('<p>Error loading design preview.</p>');
                }
            });
        },
        
        /**
         * Download PDF
         */
        downloadPDF: function(projectId) {
            if (!projectId) {
                alert('Invalid project ID');
                return;
            }
            
            // Create a form to submit the download request
            var form = $('<form>', {
                action: pitchprint_admin_vars.ajax_url,
                method: 'POST'
            });
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'pitchprint_download_pdf'
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'nonce',
                value: pitchprint_admin_vars.nonce
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'project_id',
                value: projectId
            }));
            
            form.appendTo('body').submit().remove();
        },
        
        /**
         * Show notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            var notice = $('<div class="notice notice-' + type + ' is-dismissible">' +
                '<p>' + message + '</p>' +
                '<button type="button" class="notice-dismiss">' +
                '<span class="screen-reader-text">Dismiss this notice.</span>' +
                '</button>' +
                '</div>');
            
            $('.wrap h1').after(notice);
            
            // Make dismissible
            notice.on('click', '.notice-dismiss', function() {
                notice.fadeOut(function() {
                    notice.remove();
                });
            });
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut(function() {
                    notice.remove();
                });
            }, 5000);
        },
        
        /**
         * Store original values for change detection
         */
        storeOriginalValues: function() {
            $('#pitchprint_api_key, #pitchprint_secret_key').each(function() {
                $(this).data('original-value', $(this).val());
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        PitchPrintAdmin.init();
        PitchPrintAdmin.storeOriginalValues();
    });
    
    // Make it accessible globally for debugging
    window.PitchPrintAdmin = PitchPrintAdmin;

})(jQuery);
