/**
 * PitchPrint Frontend JavaScript
 */

(function($) {
    'use strict';

    var PitchPrintFrontend = {
        
        ppclient: null,
        mode: 'new',
        projectId: null,
        projectData: null,
        uploadedFileId: null,
        uploadedFileUrl: null,
        
        /**
         * Initialize
         */
        init: function() {
            // Check if we have product data
            if (typeof pitchPrintProductData === 'undefined') {
                return;
            }
            
            // Bind events
            this.bindEvents();
            
            // Initialize PitchPrint client
            this.initializePitchPrint();
            
            // Check design button state
            this.checkDesignButtonState();
        },
        
        /**
         * Check and update design button state
         */
        checkDesignButtonState: function() {
            var $designBtn = $('#pitchprint-design-btn');
            if ($designBtn.length) {
                var designId = $designBtn.data('design-id');
                if (!designId || designId === '') {
                    $designBtn.prop('disabled', true)
                        .attr('title', 'No design template selected for this product');
                } else {
                    $designBtn.prop('disabled', false)
                        .removeAttr('title');
                }
            }
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;
            
            // Design Online button
            $(document).on('click', '#pitchprint-design-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.openDesigner();
            });
            
            // Upload Artwork button
            $(document).on('click', '#pitchprint-upload-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.openUploadModal();
            });
            
            // Edit design button
            $(document).on('click', '.edit-design-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.mode = 'edit';
                self.openDesigner();
            });
            
            // Upload modal events
            this.bindUploadEvents();
            
            // Validate before add to cart
            $('form.cart').on('submit', function(e) {
                if (self.isCustomizationRequired() && !self.projectId) {
                    e.preventDefault();
                    alert(pitchprint_vars.messages.customization_required || 'Please customize your product before adding to cart.');
                    return false;
                }
            });
        },
        
        /**
         * Bind upload modal events
         */
        bindUploadEvents: function() {
            var self = this;
            
            // Close modal
            $(document).on('click', '#pitchprint-upload-modal .close, #pitchprint-upload-modal .cancel-upload', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.closeUploadModal();
            });
            
            // Click outside modal to close
            $(document).on('click', '#pitchprint-upload-modal', function(e) {
                if (e.target === this) {
                    self.closeUploadModal();
                }
            });
            
            // File input change
            $(document).on('change', '#pitchprint-file-input', function(e) {
                var file = this.files[0];
                if (file) {
                    $('.upload-area .file-name').text(file.name);
                    $('.upload-area .file-info').show();
                    $('.upload-area .upload-label').hide();
                }
            });
            
            // Remove file
            $(document).on('click', '.upload-area .remove-file', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('#pitchprint-file-input').val('');
                $('.upload-area .file-info').hide();
                $('.upload-area .upload-label').show();
            });
            
            // Drag and drop
            var uploadArea = $('.upload-area');
            
            uploadArea.on('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('drag-over');
            });
            
            uploadArea.on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');
            });
            
            uploadArea.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');
                
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    $('#pitchprint-file-input')[0].files = files;
                    $('#pitchprint-file-input').trigger('change');
                }
            });
            
            // Upload form submit - PREVENT DEFAULT FORM SUBMISSION
            $(document).on('submit', '#pitchprint-upload-form', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.handleFileUpload();
                return false; // Extra safety to prevent form submission
            });
            
            // Prevent button from submitting cart form
            $('#pitchprint-upload-form button[type="submit"]').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('#pitchprint-upload-form').submit();
                return false;
            });
        },
        
        /**
         * Initialize PitchPrint client
         */
        initializePitchPrint: function() {
            var self = this;
            
            if (!pitchPrintProductData.apiKey) {
                console.error('PitchPrint API key not configured');
                return;
            }
            
            var options = {
                apiKey: pitchPrintProductData.apiKey,
                custom: true,
                isvx: true,
                mode: this.mode,
                userId: pitchprint_vars.user_id || 'guest',
                product: {
                    id: pitchPrintProductData.productId,
                    name: $('.product_title').text() || 'Product'
                }
            };
            
            // Add design ID if available and not in upload mode
            if (pitchPrintProductData.designId && this.mode !== 'upload') {
                options.designId = pitchPrintProductData.designId;
            }
            
            // Add project ID if editing
            if (this.mode === 'edit' && this.projectId) {
                options.projectId = this.projectId;
            }
            
            // Create PitchPrint client instance
            this.ppclient = new PitchPrintClient(options);
            
            // Attach event handlers
            this.attachPitchPrintEvents();
        },
        
        /**
         * Attach PitchPrint event handlers
         */
        attachPitchPrintEvents: function() {
            var self = this;
            
            // App validated event
            this.ppclient.on('app-validated', function() {
                $('#pitchprint-loader').hide();
                console.log('PitchPrint app validated');
                
                // If we have an uploaded file, load it after app is ready
                if (self.uploadedFileUrl && self.mode === 'upload') {
                    setTimeout(function() {
                        self.ppclient.fire('load-image', {
                            url: self.uploadedFileUrl,
                            type: 'image'
                        });
                    }, 1000);
                }
            });
            
            // Project saved event
            this.ppclient.on('project-saved', function(event) {
                var data = event.data;
                
                if (data) {
                    self.projectId = data.projectId;
                    self.projectData = data;
                    
                    // Update hidden fields
                    $('#pitchprint_project_id').val(data.projectId);
                    $('#pitchprint_project_data').val(JSON.stringify(data));
                    
                    // Show preview
                    self.showPreview(data);
                    
                    // Save to server via AJAX
                    self.saveProject(data);
                    
                    // Enable add to cart if it was disabled
                    $('.single_add_to_cart_button').prop('disabled', false);
                }
            });
            
            // Design loaded event
            this.ppclient.on('design-loaded', function() {
                console.log('Design loaded');
            });
            
            // After close event
            this.ppclient.on('after-close-app', function() {
                console.log('Designer closed');
            });
        },
        
        /**
         * Open designer
         */
        openDesigner: function() {
            if (!this.ppclient) {
                alert('PitchPrint is not initialized. Please refresh the page.');
                return;
            }
            
            var designId = $('#pitchprint-design-btn').data('design-id');
            
            if (!designId && pitchPrintProductData.buttonType !== 'upload_artwork') {
                alert('No design template selected for this product. Please contact the store administrator.');
                return;
            }
            
            $('#pitchprint-loader').show();
            this.ppclient.showApp();
        },
        
        /**
         * Open upload modal
         */
        openUploadModal: function() {
            $('#pitchprint-upload-modal').fadeIn();
            $('body').addClass('modal-open');
        },
        
        /**
         * Close upload modal
         */
        closeUploadModal: function() {
            $('#pitchprint-upload-modal').fadeOut();
            $('body').removeClass('modal-open');
            
            // Reset form
            $('#pitchprint-upload-form')[0].reset();
            $('.upload-area .file-info').hide();
            $('.upload-area .upload-label').show();
        },
        
        /**
         * Handle file upload
         */
        handleFileUpload: function() {
            var self = this;
            var fileInput = $('#pitchprint-file-input')[0];
            
            if (!fileInput.files || !fileInput.files[0]) {
                alert('Please select a file to upload');
                return false;
            }
            
            var formData = new FormData();
            formData.append('action', 'pitchprint_upload_file');
            formData.append('nonce', pitchPrintProductData.uploadNonce);
            formData.append('artwork_file', fileInput.files[0]);
            
            // Show loading
            $('#pitchprint-loader').show();
            $('#pitchprint-loader .loader-text').text('Uploading file...');
            self.closeUploadModal();
            
            $.ajax({
                url: pitchPrintProductData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.uploadedFileId = response.data.file_id;
                        self.uploadedFileUrl = response.data.file_url;
                        
                        // Store uploaded file info
                        $('#pitchprint_uploaded_file').val(self.uploadedFileId);
                        
                        // Open designer with blank canvas
                        self.mode = 'upload';
                        self.openUploaderDesigner();
                    } else {
                        $('#pitchprint-loader').hide();
                        alert(response.data.message || 'Upload failed');
                    }
                },
                error: function() {
                    $('#pitchprint-loader').hide();
                    alert('Error uploading file. Please try again.');
                }
            });
            
            return false; // Prevent any form submission
        },
        
        /**
         * Open designer for uploaded artwork
         */
        openUploaderDesigner: function() {
            var self = this;
            
            // Create a new client for blank canvas
            var blankOptions = {
                apiKey: pitchPrintProductData.apiKey,
                custom: true,
                isvx: true,
                mode: 'new',
                userId: pitchprint_vars.user_id || 'guest',
                product: {
                    id: pitchPrintProductData.productId,
                    name: $('.product_title').text() || 'Product'
                }
            };
            
            // Reinitialize with blank canvas
            this.ppclient = new PitchPrintClient(blankOptions);
            this.attachPitchPrintEvents();
            
            $('#pitchprint-loader .loader-text').text('Loading designer...');
            
            // Show app first
            this.ppclient.showApp();
            
            // Create blank page after a short delay
            setTimeout(function() {
                self.ppclient.fire('blank-page', {
                    width: 8.5,
                    height: 11,
                    title: "Your Artwork"
                });
            }, 2000);
        },
        
        /**
         * Show preview
         */
        showPreview: function(data) {
            if (!data.previews || !data.previews.length) {
                return;
            }
            
            var previewHtml = '';
            for (var i = 0; i < data.previews.length; i++) {
                previewHtml += '<img src="' + data.previews[i] + '" alt="Design Preview ' + (i + 1) + '" />';
            }
            
            $('#pitchprint-preview .preview-images').html(previewHtml);
            $('#pitchprint-preview').show();
            
            // Hide design buttons if needed
            if (this.shouldHideButtons()) {
                $('.pitchprint-button').not('.edit-design-btn').hide();
            }
        },
        
        /**
         * Save project to server
         */
        saveProject: function(data) {
            var self = this;
            
            $.ajax({
                url: pitchprint_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'pitchprint_save_project',
                    nonce: pitchprint_vars.nonce,
                    project_id: data.projectId,
                    project_data: JSON.stringify(data),
                    product_id: pitchPrintProductData.productId,
                    uploaded_file_id: self.uploadedFileId
                },
                success: function(response) {
                    if (response.success) {
                        console.log('Project saved successfully');
                    } else {
                        console.error('Failed to save project:', response.data.message);
                    }
                },
                error: function() {
                    console.error('Error saving project');
                }
            });
        },
        
        /**
         * Check if customization is required
         */
        isCustomizationRequired: function() {
            // You can implement custom logic here
            // For now, return false to allow normal add to cart
            return false;
        },
        
        /**
         * Should hide buttons after design
         */
        shouldHideButtons: function() {
            // You can implement custom logic here
            return false;
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        PitchPrintFrontend.init();
    });
    
    // Make it accessible globally for debugging
    window.PitchPrintFrontend = PitchPrintFrontend;

})(jQuery);
