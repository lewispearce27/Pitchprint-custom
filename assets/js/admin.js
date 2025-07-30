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
            this.initProductTab();
        },
        
        /**
         * Initialize product tab functionality
         */
        initProductTab: function() {
            // Only run on product edit pages
            if ($('#pitchprint_product_data').length > 0) {
                this.loadCategoriesOnProductPage();
                this.bindProductEvents();
            }
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
         * Bind product-specific events
         */
        bindProductEvents: function() {
            var self = this;
            
            // Show/hide fields based on button type
            $('#pitchprint_button_type').on('change', function() {
                self.togglePitchPrintFields();
            });
            
            // Load designs when category changes
            $('#pitchprint_category_id').on('change', function() {
                self.loadDesigns($(this).val());
            });
            
            // Initial field toggle
            this.togglePitchPrintFields();
        },
        
        /**
         * Toggle PitchPrint fields based on button type
         */
        togglePitchPrintFields: function() {
            var buttonType = $('#pitchprint_button_type').val();
            
            if (buttonType === 'none' || buttonType === 'upload_artwork') {
                $('.pitchprint-category-field, .pitchprint-design-field').hide();
            } else {
                $('.pitchprint-category-field, .pitchprint-design-field').show();
            }
        },
        
        /**
         * Load categories on product page
         */
        loadCategoriesOnProductPage: function() {
            var self = this;
            var $categorySelect = $('#pitchprint_category_id');
            var savedCategoryId = $categorySelect.data('saved-value') || $categorySelect.find('option:selected').val();
            var savedDesignId = $('#pitchprint_design_id').data('saved-value') || $('#pitchprint_design_id').find('option:selected').val();
            
            if (!$categorySelect.length) {
                return;
            }
            
            $categorySelect.empty().append('<option value="">Loading categories...</option>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'pitchprint_fetch_categories',
                    nonce: pitchprint_admin_vars.nonce
                },
                success: function(response) {
                    $categorySelect.empty().append('<option value="">Select a category...</option>');
                    
                    if (response.success && response.data) {
                        var categories = response.data;
                        
                        if (Array.isArray(categories)) {
                            $.each(categories, function(index, category) {
                                var selected = (category.id === savedCategoryId) ? ' selected="selected"' : '';
                                $categorySelect.append(
                                    '<option value="' + category.id + '"' + selected + '>' + 
                                    category.title + 
                                    '</option>'
                                );
                            });
                        } else if (typeof categories === 'object') {
                            $.each(categories, function(id, title) {
                                var selected = (id === savedCategoryId) ? ' selected="selected"' : '';
                                $categorySelect.append(
                                    '<option value="' + id + '"' + selected + '>' + 
                                    title + 
                                    '</option>'
                                );
                            });
                        }
                        
                        // If we have a saved category, load its designs
                        if (savedCategoryId) {
                            self.loadDesigns(savedCategoryId, savedDesignId);
                        }
                    } else {
                        $categorySelect.append('<option value="">No categories found</option>');
                    }
                },
                error: function() {
                    $categorySelect.empty().append('<option value="">Error loading categories</option>');
                }
            });
        },
        
        /**
         * Load designs for a category
         */
        loadDesigns: function(categoryId, savedDesignId) {
            var $designSelect = $('#pitchprint_design_id');
            
            if (!categoryId) {
                $designSelect.empty().append('<option value="">Select a design...</option>');
                return;
            }
            
            $designSelect.empty().append('<option value="">Loading designs...</option>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'pitchprint_fetch_designs',
                    category_id: categoryId,
                    nonce: pitchprint_admin_vars.nonce
                },
                success: function(response) {
                    $designSelect.empty().append('<option value="">Select a design...</option>');
                    
                    if (response.success && response.data && response.data.data && response.data.data.items) {
                        var designs = response.data.data.items;
                        
                        if (designs.length > 0) {
                            $.each(designs, function(index, design) {
                                var selected = (design.designId === savedDesignId) ? ' selected="selected"' : '';
                                $designSelect.append(
                                    '<option value="' + design.designId + '"' + selected + '>' + 
                                    (design.title || design.designId) + 
                                    '</option>'
                                );
                            });
                        } else {
                            $designSelect.append('<option value="">No designs found in this category</option>');
                        }
                    } else {
                        $designSelect.append('<option value="">No designs found</option>');
                    }
                },
                error: function() {
                    $designSelect.empty().append('<option value="">Error loading designs</option>');
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
