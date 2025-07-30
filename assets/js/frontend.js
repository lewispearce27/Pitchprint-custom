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
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;
            
            // Design Online button
            $(document).on('click', '#pitchprint-design-btn', function(e) {
                e.preventDefault();
                self.openDesigner();
            });
            
            // Upload Artwork button
            $(document).on('click', '#pitchprint-upload-btn', function(e) {
                e.preventDefault();
                self.openUploader();
            });
            
            // Edit design button
            $(document).on('click', '.edit-design-btn', function(e) {
                e.preventDefault();
                self.mode = 'edit';
                self.openDesigner();
            });
            
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
            
            // Add design ID if available
            if (pitchPrintProductData.designId) {
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
            
            if (!pitchPrintProductData.designId && pitchPrintProductData.buttonType !== 'upload_artwork') {
                alert('No design template selected for this product.');
                return;
            }
            
            $('#pitchprint-loader').show();
            this.ppclient.showApp();
        },
        
        /**
         * Open uploader (blank canvas)
         */
        openUploader: function() {
            var self = this;
            
            // Create a blank design for upload
            var blankOptions = {
                apiKey: pitchPrintProductData.apiKey,
                custom: true,
                isvx: true,
                mode: 'new',
                userId: pitchprint_vars.user_id || 'guest',
                product: {
                    id: pitchPrintProductData.productId,
                    name: $('.product_title').text() || 'Product'
                },
                // Create a blank canvas
                designId: null,
                blankCanvas: {
                    width: 8.5,
                    height: 11,
                    unit: 'in'
                }
            };
            
            // Reinitialize with blank canvas
            this.ppclient = new PitchPrintClient(blankOptions);
            this.attachPitchPrintEvents();
            
            $('#pitchprint-loader').show();
            
            // Fire blank page event after initialization
            setTimeout(function() {
                self.ppclient.fire('blank-page', {
                    width: 8.5,
                    height: 11,
                    title: "Upload Your Artwork"
                });
                self.ppclient.showApp();
            }, 500);
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
            $.ajax({
                url: pitchprint_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'pitchprint_save_project',
                    nonce: pitchprint_vars.nonce,
                    project_id: data.projectId,
                    project_data: JSON.stringify(data),
                    product_id: pitchPrintProductData.productId
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
