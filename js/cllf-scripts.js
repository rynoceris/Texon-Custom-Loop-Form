/**
 * Custom Laundry Loops Form Scripts
 * Version: 2.3.4 - With Nonce Refresh Fix
 */
(function($) {
    'use strict';

    // Variable to store the last form submission data
    let lastFormData = null;
    
    // Variable to store the last uploaded logo
    let lastLogoFile = null;
    
    // Variable to store custom font file
    let customFontFile = null;
    
    // Variables for color handling
    let extractedColors = [];
    let selectedTextColor = '#000000';
    
    // CRITICAL: Store current nonce globally
    let currentNonce = '';

    // Initialize the form when document is ready
    $(document).ready(function() {
        console.log('CLLF: Starting initialization...');
        
        // CRITICAL FIX: Refresh nonce FIRST before any form interactions
        refreshNonce().then(function() {
            console.log('CLLF: Nonce refreshed successfully, initializing form');
            initializeForm();
        }).catch(function(error) {
            console.error('CLLF: Failed to refresh nonce:', error);
            // Still initialize form with fallback
            currentNonce = cllfVars.nonce || '';
            console.warn('CLLF: Using fallback nonce, may cause issues');
            initializeForm();
        });
    });
    
    /**
     * Refresh the nonce via AJAX to bypass caching
     * This ensures we always have a valid, fresh nonce
     */
    function refreshNonce() {
        return new Promise(function(resolve, reject) {
            console.log('CLLF: Requesting fresh nonce from server...');
            
            $.ajax({
                url: cllfVars.ajaxurl,
                type: 'POST',
                data: {
                    action: cllfVars.nonceRefreshAction || 'cllf_refresh_nonce'
                },
                success: function(response) {
                    if (response.success && response.data.nonce) {
                        currentNonce = response.data.nonce;
                        cllfVars.nonce = response.data.nonce; // Update global variable too
                        console.log('CLLF: Fresh nonce received:', currentNonce.substring(0, 10) + '...');
                        resolve(currentNonce);
                    } else {
                        console.error('CLLF: Invalid nonce response:', response);
                        reject('Invalid response from server');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('CLLF: Nonce refresh AJAX error:', status, error);
                    reject(error);
                }
            });
        });
    }
    
    /**
     * Periodically refresh nonce (every 10 minutes) to prevent expiration
     */
    setInterval(function() {
        console.log('CLLF: Periodic nonce refresh');
        refreshNonce().catch(function(error) {
            console.error('CLLF: Periodic nonce refresh failed:', error);
        });
    }, 600000); // 10 minutes
    
    /**
     * Initialize all form functionality
     * This runs AFTER nonce is refreshed
     */
    function initializeForm() {
        console.log('CLLF: Initializing form controls...');
        
        // Show/hide scroll to top button based on scroll position
        const scrollToTopBtn = $('#scroll-to-top');
        
        $(window).on('scroll', function() {
            if ($(this).scrollTop() > 300) {
                scrollToTopBtn.addClass('visible');
            } else {
                scrollToTopBtn.removeClass('visible');
            }
        });
        
        // Smooth scroll to top when button is clicked
        scrollToTopBtn.on('click', function(e) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: 0
            }, 500);
        });
        
        // Modal functionality for color thumbnail
        const colorThumbnail = $('#color-thumbnail-img');
        const colorModal = $('#color-modal');
        const modalClose = $('.cllf-modal-close');
        
        // Open modal when clicking on the thumbnail
        colorThumbnail.on('click', function() {
            // Ensure the modal has the highest z-index on the page
            colorModal.css('z-index', 9999);
            $('.cllf-modal-content').css('z-index', 10000);
            
            // Force the page header below our modal
            $('#page-header').css('z-index', 99);
            
            colorModal.addClass('show');
            $('body').addClass('modal-open');
        });
        
        // Close modal when clicking on the close button or outside the modal content
        modalClose.on('click', function() {
            closeModal();
        });
        
        colorModal.on('click', function(e) {
            if ($(e.target).is(colorModal)) {
                closeModal();
            }
        });
        
        // Close modal when pressing ESC key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && colorModal.hasClass('show')) {
                closeModal();
            }
        });
        
        // Function to close the modal
        function closeModal() {
            colorModal.removeClass('show');
            setTimeout(() => {
                // Restore the original z-index for the header
                $('#page-header').css('z-index', '');
                $('body').removeClass('modal-open');
            }, 300); // Wait for the fade-out animation to complete
        }
        
        // Add shift-click functionality for number checkboxes
        let lastChecked = null; // Keep track of the last checkbox that was clicked
        
        $('.number-checkbox').on('click', function(e) {
            const $this = $(this);
            
            // If shift key is pressed and there's a last checked checkbox
            if (e.shiftKey && lastChecked !== null) {
                // Get the current checkbox index and the last checked index
                const $checkboxes = $('.number-checkbox');
                const startIndex = $checkboxes.index(lastChecked);
                const endIndex = $checkboxes.index(this);
                
                // Determine the range (either ascending or descending)
                const start = Math.min(startIndex, endIndex);
                const end = Math.max(startIndex, endIndex);
                
                // Get the checked state from the current checkbox
                const isChecked = $this.prop('checked');
                
                // Apply the same checked state to all checkboxes in the range
                $checkboxes.slice(start, end + 1).prop('checked', isChecked);
                
                // Update the "Select All" checkbox
                updateSelectAllCheckbox();
                
                // Update the order summary and preview
                updateOrderSummary();
                updateLoopPreview();
            }
            
            // Save reference to the checkbox that was just clicked
            lastChecked = this;
        });
        
        // Add visual indicator when shift key is pressed
        $(document).on('keydown', function(e) {
            if (e.shiftKey && $('.cllf-numbers-grid').is(':visible')) {
                $('.cllf-numbers-grid').addClass('shift-key-active');
            }
        });
        
        $(document).on('keyup', function(e) {
            if (e.key === 'Shift') {
                $('.cllf-numbers-grid').removeClass('shift-key-active');
            }
        });
        
        // Handle font choice dropdown
        $('#font_choice').on('change', function() {
            const selectedValue = $(this).val();
            
            // Show file upload only when "new" is selected
            if (selectedValue === 'new') {
                $('#font-upload-container').slideDown(300);
            } else {
                $('#font-upload-container').slideUp(300);
                // Clear the file input when not using new font
                $('#custom_font').val('');
                customFontFile = null;
            }
        });
        
        // Handle logo upload option toggle
        $('input[name="has_logo"]').on('change', function() {
            if ($(this).val() === 'Yes') {
                $('#logo-upload-container').slideDown();
            } else {
                $('#logo-upload-container').slideUp();
                $('#logo_file').val('');
                $('#logo_preview').empty();
            }
            updateLoopPreview();
        });

        // Preview logo file when selected
        $('#logo_file').on('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Store the logo data for later use
                    lastLogoFile = {
                        dataUrl: e.target.result,
                        name: file.name,
                        type: file.type
                    };
                    
                    const img = $('<img>').attr('src', e.target.result);
                    $('#logo_preview').empty().append(img);
                    updateLoopPreview();
                };
                reader.readAsDataURL(file);
            } else {
                $('#logo_preview').empty();
                lastLogoFile = null;
                updateLoopPreview();
            }
        });
        
        // Handle logo file upload and color extraction
        $('#logo_file').on('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Store the logo data for later use
                    lastLogoFile = {
                        dataUrl: e.target.result,
                        name: file.name,
                        type: file.type
                    };
                    
                    const img = $('<img>').attr('src', e.target.result);
                    $('#logo_preview').empty().append(img);
                    
                    // For non-image formats, add a note
                    const fileExt = file.name.split('.').pop().toLowerCase();
                    const specialFormats = ['pdf', 'ai', 'eps'];
                    
                    if (specialFormats.includes(fileExt)) {
                        $('#logo_preview').append(
                            $('<p class="file-type-note">').text(
                                `${fileExt.toUpperCase()} file uploaded. Preview may be limited.`
                            )
                        );
                    }
                    
                    updateLoopPreview();
                    
                    console.log('Logo file loaded, now uploading for color extraction...');
                    console.log('File type:', file.type);
                    
                    // Check if it's an SVG
                    const isSvg = file.type === 'image/svg+xml' || file.name.toLowerCase().endsWith('.svg');
                    
                    // Create a separate form submission for the logo
                    const logoFormData = new FormData();
                    logoFormData.append('action', 'cllf_temp_logo_upload');
                    logoFormData.append('nonce', currentNonce); // USE FRESH NONCE
                    logoFormData.append('logo_file', file);
                    
                    // Show loading state
                    $('#text_color').prop('disabled', true);
                    $('#color-preview').addClass('loading');
                    $('#text-color-container').addClass('color-loading');
                    
                    $.ajax({
                        url: cllfVars.ajaxurl,
                        type: 'POST',
                        data: logoFormData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                        console.log('Logo upload response:', response);
                        if (response.success) {
                            // Different extraction based on file type
                            if (fileExt === 'svg') {
                                // For SVG, try client-side as well
                                extractColorsFromLogo(response.data.url);
                                extractSvgColorsClientSide(response.data.url);
                            } else if (specialFormats.includes(fileExt)) {
                                // For PDF/AI/EPS, server-side only (with timeout)
                                const extractionTimeout = setTimeout(() => {
                                    console.log('Color extraction taking too long, using fallbacks');
                                    $('#text_color').prop('disabled', false);
                                    $('#color-preview').removeClass('loading');
                                    $('#text-color-container').removeClass('color-loading');
                                    useFallbackColors();
                                }, 10000); // 10 second timeout
                                
                                extractColorsFromLogo(response.data.url, () => {
                                    clearTimeout(extractionTimeout);
                                });
                            } else {
                                // Standard images
                                extractColorsFromLogo(response.data.url);
                            }
                        } else {
                            console.error('Error uploading logo:', response.data);
                            $('#text_color').prop('disabled', false);
                            $('#color-preview').removeClass('loading');
                            $('#text-color-container').removeClass('color-loading');
                            useFallbackColors();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', status, error);
                        $('#text_color').prop('disabled', false);
                        $('#color-preview').removeClass('loading');
                        $('#text-color-container').removeClass('color-loading');
                        useFallbackColors();
                    }
                });
            };
                
                // Use appropriate read method based on file type
                const fileExt = file.name.split('.').pop().toLowerCase();
                if (['jpg', 'jpeg', 'png', 'gif', 'svg'].includes(fileExt)) {
                    reader.readAsDataURL(file);
                } else {
                    // For non-image formats like PDF/AI/EPS
                    // Show a placeholder and just upload the file
                    const placeholderImg = $('<img>').attr('src', `${cllfVars.pluginUrl}images/file-icon-${fileExt}.png`);
                    if (!placeholderImg[0].complete) {
                        // If specific icon doesn't exist, use generic
                        placeholderImg.attr('src', `${cllfVars.pluginUrl}images/file-icon-generic.png`);
                    }
                    
                    $('#logo_preview').empty().append(placeholderImg);
                    $('#logo_preview').append(
                        $('<p class="file-type-note">').text(
                            `${fileExt.toUpperCase()} file uploaded. Preview not available.`
                        )
                    );
                    
                    // Use a different reader for non-image files
                    reader.readAsArrayBuffer(file);
                }
            } else {
                $('#logo_preview').empty();
                lastLogoFile = null;
                updateLoopPreview();
                resetColorDropdown();
            }
        });
        
        // Handle text color selection
        $('#text_color').on('change', function() {
            const selectedValue = $(this).val();
            
            if (selectedValue === 'custom') {
                $('#custom-color-container').show();
                if ($('#custom_color').val()) {
                    selectedTextColor = $('#custom_color').val();
                    updateColorPreview(selectedTextColor);
                }
            } else {
                $('#custom-color-container').hide();
                selectedTextColor = selectedValue;
                updateColorPreview(selectedTextColor);
            }
            
            updateLoopPreview();
        });
        
        // Handle custom color input
        $('#custom_color').on('input', function() {
            const color = $(this).val();
            if (isValidColor(color)) {
                selectedTextColor = color;
                updateColorPreview(color);
                updateLoopPreview();
            }
        });
        
        // Initialize color picker (you can use a library like Spectrum or create a simple one)
        initColorPicker();
        
        // Add handler for font upload
        $('#custom_font').on('change', function() {
            const file = this.files[0];
            if (file) {
                // Store reference to the uploaded file
                customFontFile = file;
                console.log('Custom font file selected:', file.name);
            } else {
                customFontFile = null;
            }
        });
        
        $('.clip-option').on('click', function() {
            // Find and check the radio button within this label
            $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
        });

        // Toggle between numbers and names options
        $('input[name="tag_info_type"]').on('change', function() {
            if ($(this).val() === 'Numbers') {
                $('#numbers-container').show();
                $('#names-container').hide();
                $('#names_paste').val('');
                $('#names-list-container').empty();
            } else {
                $('#numbers-container').hide();
                $('#names-container').show();
            }
            updateOrderSummary();
            updateLoopPreview();
        });

        // Select all numbers checkbox
        $('#select-all-numbers').on('change', function() {
            $('.number-checkbox').prop('checked', $(this).prop('checked'));
            updateOrderSummary();
            updateLoopPreview();
        });

        // Individual number checkbox
        $('.number-checkbox').on('change', function() {
            updateSelectAllCheckbox();
            updateOrderSummary();
            updateLoopPreview();
        });

        // Process names from textarea
        $('#process-names-btn').on('click', function() {
            processNames();
        });

        // Update order summary when form values change
        $('#num_sets, #add_blanks').on('change keyup', function() {
            updateOrderSummary();
            updateLoopPreview();
        });

        // Specifically listen for color dropdown changes
        $('#loop_color').on('change', function() {
            updateOrderSummary();
            updateLoopPreview();
        });

        // Specifically listen for sock clips radio changes
        $('input[name="sock_clips"]').on('change', function() {
            updateOrderSummary();
            updateLoopPreview();
        });

        // Listen for sport word changes
        $('#sport_word').on('change keyup', function() {
            updateOrderSummary();
            updateLoopPreview();
        });

        // Update preview when tag information changes
        $('.number-checkbox').on('change', function() {
            updateLoopPreview();
        });

        // Update preview when a name is added or removed
        $(document).on('change', 'input[name="tag_names[]"]', function() {
            updateLoopPreview();
        });

        // Form submission
        $('#cllf-form').on('submit', function(e) {
            e.preventDefault();
            submitForm();
        });

        // Clear form button
        $('#cllf-clear-btn').on('click', function() {
            clearForm();
        });

        // Process names from textarea
        $('#names_paste').on('input', function() {
            validateNamesPaste($(this).val());
        });
        
        // Character count for sport word
        $('#sport_word').on('input', function() {
            const currentLength = $(this).val().length;
            $('#sport-word-char-count').text(currentLength);
            
            if (currentLength >= 15) {
                $('#sport-word-char-count').parent().addClass('near-limit');
            } else {
                $('#sport-word-char-count').parent().removeClass('near-limit');
            }
        });
        
        // Initialize character count for sport word
        $('#sport-word-char-count').text($('#sport_word').val().length);

        // Initialize order summary
        updateOrderSummary();
        
        // Initialize loop preview
        updateLoopPreview();
        
        console.log('CLLF: Form initialization complete');
    }

    /**
     * Function to save the current form data before submission
     */
    function saveFormData() {
        const data = {
            sockClips: $('input[name="sock_clips"]:checked').val(),
            hasLogo: $('input[name="has_logo"]:checked').val(),
            logoFile: lastLogoFile, // Store the logo file data
            customFontName: customFontFile ? customFontFile.name : null,
            sportWord: $('#sport_word').val(),
            tagInfoType: $('input[name="tag_info_type"]:checked').val(),
            addBlanks: $('#add_blanks').val(),
            numSets: $('#num_sets').val(),
            orderNotes: $('#order_notes').val()
        };

        // Save tag information based on the selected type
        if (data.tagInfoType === 'Numbers') {
            data.tagNumbers = [];
            $('.number-checkbox:checked').each(function() {
                data.tagNumbers.push($(this).val());
            });
        } else {
            data.tagNames = [];
            $('input[name="tag_names[]"]').each(function() {
                data.tagNames.push($(this).val());
            });
        }

        return data;
    }
    
    /**
     * Validate names in the paste textarea
     */
    function validateNamesPaste(text) {
        const names = text.split(/[,\n]+/).map(name => name.trim()).filter(name => name);
        let hasLongNames = false;
        
        // Check for names that exceed the limit
        names.forEach(name => {
            if (name.length > 20) {
                hasLongNames = true;
            }
        });
        
        // Show warning if any names are too long
        if (hasLongNames) {
            if (!$('#names-length-warning').length) {
                $('#names_paste').after('<div id="names-length-warning" class="name-length-warning">Warning: Some names exceed the 20 character limit and will be truncated.</div>');
            }
        } else {
            $('#names-length-warning').remove();
        }
    }

    /**
     * Process names from textarea into individual input fields
     */
    function processNames() {
        const namesTextarea = $('#names_paste');
        const namesText = namesTextarea.val().trim();
        
        if (!namesText) {
            return;
        }

        // Split names by newline or comma
        let names = namesText.split(/[,\n]+/).map(name => name.trim()).filter(name => name);
        
        // Add each name as a separate input field (without clearing existing ones)
        names.forEach((name, index) => {
            addNameField(name, index);
        });
        
        // Clear the textarea
        namesTextarea.val('');
        
        // Update the order summary
        updateOrderSummary();
        
        // Update the preview
        updateLoopPreview();
    }

    /**
     * Add a name input field to the names list
     */
    function addNameField(name, index) {
        const nameField = $('<div>').addClass('cllf-name-field');
        
        const input = $('<input>')
            .attr('type', 'text')
            .attr('name', 'tag_names[]')
            .attr('value', name)
            .attr('maxlength', '20')
            .attr('required', true)
            .on('input', function() {
                updateNameCharCount($(this));
            });
        
        const charCount = $('<div>')
            .addClass('char-count-container')
            .html('<span class="name-char-count">0</span>/20 characters');
        
        const removeBtn = $('<button>')
            .attr('type', 'button')
            .addClass('cllf-remove-name')
            .html('&times;')
            .on('click', function() {
                $(this).parent().remove();
                updateOrderSummary();
                updateLoopPreview();
            });
        
        nameField.append(input).append(charCount).append(removeBtn);
        $('#names-list-container').append(nameField);
        
        // Initialize the character count
        updateNameCharCount(input);
    }
    
    /**
     * Update character count for name fields
     */
    function updateNameCharCount(inputField) {
        const currentLength = inputField.val().length;
        inputField.siblings('.char-count-container').find('.name-char-count').text(currentLength);
        
        // Add visual indicator when approaching limit
        if (currentLength >= 15) {
            inputField.siblings('.char-count-container').addClass('near-limit');
        } else {
            inputField.siblings('.char-count-container').removeClass('near-limit');
        }
    }

    /**
     * Update the "Select All" checkbox based on individual checkbox states
     */
    function updateSelectAllCheckbox() {
        const allChecked = $('.number-checkbox:checked').length === $('.number-checkbox').length;
        $('#select-all-numbers').prop('checked', allChecked);
    }

    /**
     * Update the order summary based on form selections
     */
    function updateOrderSummary() {
        let quantity = 0;
        const tagInfoType = $('input[name="tag_info_type"]:checked').val();
        
        if (tagInfoType === 'Numbers') {
            quantity = $('.number-checkbox:checked').length;
        } else {
            quantity = $('input[name="tag_names[]"]').length;
        }
        
        const sets = parseInt($('#num_sets').val()) || 1;
        const blanks = parseInt($('#add_blanks').val()) || 0;
        const loopColor = $('#loop_color').val() || 'Selected color';
        const sockClips = $('input[name="sock_clips"]:checked').val() || 'Single';
        const hasLogo = $('input[name="has_logo"]:checked').val() === 'Yes' ? 'with logo' : 'without logo';
        const sportWord = $('#sport_word').val() ? `, with "${$('#sport_word').val()}" text` : '';
        
        const total = (quantity * sets) + blanks;
        
        // Update the displayed values
        $('#cllf-quantity').text(quantity);
        $('#cllf-sets').text(sets);
        $('#cllf-blanks').text(blanks);
        $('#cllf-total').text(total);
        
        // Update the summary text
        if (loopColor && sockClips) {
            $('#cllf-summary-text').text(`${total} ${loopColor}, ${sockClips} Clip loops ${hasLogo}${sportWord}`);
        } else {
            $('#cllf-summary-text').text('');
        }
    }

    /**
     * Update the loop preview based on form selections
     */
    function updateLoopPreview() {
        // Get form values
        const loopColor = $('#loop_color').val();
        const hasLogo = $('input[name="has_logo"]:checked').val() === 'Yes';
        const sportWord = $('#sport_word').val();
        const tagInfoType = $('input[name="tag_info_type"]:checked').val();
        const sockClips = $('input[name="sock_clips"]:checked').val() || 'Single';
        
        console.log(`Updating preview: Color=${loopColor}, Clips=${sockClips}, HasLogo=${hasLogo}, TagType=${tagInfoType}`);
        
        // Update loop color image if available
        if (loopColor) {
            // Define colorImage variable outside the if/else blocks so it's accessible to both
            let colorImage;
            let formattedColor;
            
            if (loopColor == "Tan (Jute)") {
                // Create formatted color string for filename
                formattedColor = "tan";
            } else if (loopColor == "Sky (Light) Blue") {
                formattedColor = "sky-blue";
            } else {
                // Create formatted color string for filename
                formattedColor = loopColor.toLowerCase().replace(/\s+/g, '-');
            }
            
            // Now formattedColor is accessible here
            console.log(`Formatted color: ${formattedColor}`);
            
            // Determine if we need the large (-lg) or small (-sm) image
            // Names always use large image, Numbers use small unless Double clips
            let clipSizeSuffix;
            if (tagInfoType === 'Names' || sockClips === 'Double') {
                clipSizeSuffix = 'lg';
            } else {
                clipSizeSuffix = 'sm';
            }
            
            // Try to load a color-specific image
            colorImage = `${cllfVars.pluginUrl}images/loop-${formattedColor}-${clipSizeSuffix}.png`;
            
            // Log for debugging
            console.log(`Attempting to load image: ${colorImage} (TagType: ${tagInfoType}, Clips: ${sockClips})`);
            
            // Try primary image format
            $.ajax({
                url: colorImage,
                type: 'HEAD',
                success: function() {
                    console.log(`Successfully loaded: ${colorImage}`);
                    $('#cllf-loop-color-image').attr('src', colorImage);
                },
                error: function() {
                    console.log(`Failed to load: ${colorImage}`);
                    
                    // Try alternative format (without size suffix)
                    const altImage = `${cllfVars.pluginUrl}images/loop-${formattedColor}.png`;
                    console.log(`Trying alternative: ${altImage}`);
                    
                    $.ajax({
                        url: altImage,
                        type: 'HEAD',
                        success: function() {
                            console.log(`Successfully loaded alternative: ${altImage}`);
                            $('#cllf-loop-color-image').attr('src', altImage);
                        },
                        error: function() {
                            console.log(`Failed to load alternative: ${altImage}`);
                            
                            // Try with different extension
                            const jpgImage = `${cllfVars.pluginUrl}images/loop-${formattedColor}-${clipSizeSuffix}.jpg`;
                            console.log(`Trying JPG format: ${jpgImage}`);
                            
                            $.ajax({
                                url: jpgImage,
                                type: 'HEAD',
                                success: function() {
                                    console.log(`Successfully loaded JPG: ${jpgImage}`);
                                    $('#cllf-loop-color-image').attr('src', jpgImage);
                                },
                                error: function() {
                                    console.log(`All image formats failed, using placeholder`);
                                    $('#cllf-loop-color-image').attr('src', `${cllfVars.pluginUrl}images/loop-placeholder.png`);
                                }
                            });
                        }
                    });
                }
            });
        } else {
            // No color selected, use placeholder
            $('#cllf-loop-color-image').attr('src', `${cllfVars.pluginUrl}images/loop-placeholder.png`);
        }
        
        // Adjust positions based on logo presence
        if (hasLogo) {
            // Default positions when logo is present
            $('#cllf-preview-text').css('left', '630px');
            $('#cllf-preview-tag').css('left', '630px');
            
            // Update logo preview
            if ($('#logo_preview img').length > 0) {
                // Use the visible preview image
                const logoSrc = $('#logo_preview img').attr('src');
                $('#cllf-preview-logo').html(`<img src="${logoSrc}" alt="Logo">`).show();
            } else if (lastLogoFile) {
                // Use the stored logo if available
                $('#cllf-preview-logo').html(`<img src="${lastLogoFile.dataUrl}" alt="Logo">`).show();
            } else {
                $('#cllf-preview-logo').empty().hide();
                // No logo file, adjust positions as if no logo was selected
                $('#cllf-preview-text').css('left', '515px');
                $('#cllf-preview-tag').css('left', '515px');
            }
        } else {
            // No logo selected, adjust positions
            $('#cllf-preview-text').css('left', '515px');
            $('#cllf-preview-tag').css('left', '515px');
            $('#cllf-preview-logo').empty().hide();
        }
        
        // Update sport/word text
        if (sportWord) {
            $('#cllf-preview-text').text(sportWord).show();
        } else {
            $('#cllf-preview-text').hide();
        }
        
        // Update tag preview (first number or name)
        let tagText = '';
        let isNumber = false;
        
        if (tagInfoType === 'Numbers') {
            // Get the first checked number
            const firstCheckedNumber = $('.number-checkbox:checked').first().val();
            if (firstCheckedNumber !== undefined) {
                tagText = firstCheckedNumber;
                isNumber = true;
            }
        } else {
            // Get the first name
            const firstName = $('input[name="tag_names[]"]').first().val();
            if (firstName) {
                tagText = firstName;
                isNumber = false;
            }
        }
        
        if (tagText) {
            // Calculate font size based on text length
            const tagLength = tagText.length;
            let fontSize;
            
            // Determine appropriate font size based on text length
            if (tagLength <= 2) {
                fontSize = 42; // Short text (1-2 characters)
            } else if (tagLength <= 4) {
                fontSize = 36; // Medium text (3-4 characters)
            } else if (tagLength <= 8) {
                fontSize = 30; // Longer text (5-8 characters)
            } else if (tagLength <= 12) {
                fontSize = 24; // Even longer (9-12 characters)
            } else {
                fontSize = 18; // Very long text (13+ characters)
            }
            
            // Double the font size if "Double" clip type is selected
            if (sockClips === 'Double' || isNumber || !hasLogo) {
                fontSize = fontSize * 2;
                console.log(`Doubling font size for "Double" clip type`);
            }
            
            // Set the text and apply the font size and font family
            $('#cllf-preview-tag')
                .text(tagText)
                .css({
                    'font-size': `${fontSize}px`,
                    'display': 'flex',
                    'color': selectedTextColor
                })
                .toggleClass('number-tag', isNumber) // Add number-tag class for numbers
                .show();
                
            // Also apply the color to the preview text
            $('#cllf-preview-text').css('color', selectedTextColor);
            
            console.log(`Tag text: "${tagText}" (${tagLength} chars) - Font size: ${fontSize}px - Clip type: ${sockClips} - IsNumber: ${isNumber} - Color: ${selectedTextColor}`);
        } else {
            $('#cllf-preview-tag').hide();
        }
    }

    /**
     * Clear the form and reset to default values
     */
    function clearForm() {
        // Reset the form
        $('#cllf-form')[0].reset();
        
        // Clear names list
        $('#names-list-container').empty();
        
        // Clear logo preview and data
        $('#logo_preview').empty();
        lastLogoFile = null;
        
        // Hide logo upload container
        $('#logo-upload-container').hide();
        
        // Set default values for radio buttons
        $('input[name="sock_clips"][value="Single"]').prop('checked', true);
        $('input[name="has_logo"][value="No"]').prop('checked', true);
        $('input[name="tag_info_type"][value="Numbers"]').prop('checked', true);
        
        // Show numbers container, hide names container
        $('#numbers-container').show();
        $('#names-container').hide();
        
        // Uncheck all number checkboxes
        $('.number-checkbox').prop('checked', false);
        $('#select-all-numbers').prop('checked', false);
        
        // Reset add blanks dropdown and num sets
        $('#add_blanks').val('0');
        $('#num_sets').val('1');
        
        // Reset loop color dropdown
        $('#loop_color').val('');
        
        // Reset font choice
        $('#font_choice').val('default');
        $('#font-upload-container').hide();
        
        // Clear order notes
        $('#order_notes').val('');
        
        // Clear any success/error messages
        $('#cllf-form-messages').removeClass('cllf-error cllf-success').empty();
        
        // Update the order summary
        updateOrderSummary();
        
        // Update the loop preview
        updateLoopPreview();
        
        // Focus on the loop color dropdown
        $('#loop_color').focus();
        
        // Show the form buttons
        $('.cllf-button-container').show();
    }

    /**
     * Submit the form via AJAX
     */
    function submitForm() {
        const form = $('#cllf-form')[0];
        const formData = new FormData(form);
        formData.append('font_choice', $('#font_choice').val());
        formData.append('action', 'cllf_submit_form');
        
        // CRITICAL FIX: Use the fresh nonce instead of cached one
        formData.append('nonce', currentNonce);
        
        console.log('CLLF: Submitting form with fresh nonce:', currentNonce.substring(0, 10) + '...');
        
        // Save the current form data before submission
        lastFormData = saveFormData();
        
        // Check if we need to add the stored logo to the form data
        const hasLogo = $('input[name="has_logo"]:checked').val() === 'Yes';
        const newLogoFile = $('#logo_file')[0].files[0];
        
        // Validate form before submission
        if (!validateForm()) {
            return;
        }
        
        // Show loading state
        $('#cllf-form').addClass('cllf-loading');
        $('#cllf-submit-btn').prop('disabled', true);
        $('#cllf-form-messages').removeClass('cllf-error cllf-success').empty();
        
        // If the user selected "Yes" for logo but didn't upload a new one,
        // we need to include the previously stored logo
        if (hasLogo && !newLogoFile && lastLogoFile) {
            // Add a flag to indicate we're using the stored logo
            formData.append('use_stored_logo', 'yes');
            formData.append('stored_logo_name', lastLogoFile.name);
            
            // If we need to convert back from data URL to a file
            if (lastLogoFile.dataUrl) {
                try {
                    // Store the logo data URL in session storage to be used by the server
                    // This is a workaround since we can't directly add a File to formData programmatically
                    sessionStorage.setItem('last_logo_data_url', lastLogoFile.dataUrl);
                    formData.append('logo_in_session_storage', 'yes');
                } catch (e) {
                    console.error('Failed to store logo in session storage:', e);
                }
            }
        }
        
        $.ajax({
            url: cllfVars.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('CLLF: Form submission response:', response);
                
                if (response.success) {
                    // Hide the form buttons
                    $('.cllf-button-container').hide();
                    
                    // Show success message with improved options
                    $('#cllf-form-messages')
                        .hide()
                        .addClass('cllf-success')
                        .html(`
                            <div class="cllf-success-content">
                                <span>Your custom loops have been added to the cart!</span>
                                <div class="cllf-button-group">
                                    <button type="button" id="new-order-btn" class="button button-primary">Start a New Loop Order</button>
                                    <button type="button" id="clone-form-btn" class="button button-secondary">Clone with New Color</button>
                                    <a href="${response.data.cart_url}" class="button button-secondary">View Cart</a>
                                </div>
                            </div>
                        `)
                        .fadeIn(500);
                    
                    // Add event listener to the new order button
                    $('#new-order-btn').on('click', function() {
                        clearForm();
                        // Scroll to the top of the form
                        $('html, body').animate({
                            scrollTop: $('#cllf-form').offset().top - 50
                        }, 500);
                        // Show the form buttons again
                        $('.cllf-button-container').show();
                        // Hide the success message
                        $('#cllf-form-messages').slideUp(300);
                    });
                    
                    // Add event listener to the clone button
                    $('#clone-form-btn').on('click', function() {
                        cloneFormWithNewColor();
                        // Show the form buttons again
                        $('.cllf-button-container').show();
                    });
                    
                    // Reset the form for a fresh start next time
                    $('#cllf-form')[0].reset();
                    $('#names-list-container').empty();
                    $('#logo_preview').empty();
                    $('#logo-upload-container').hide();
                    
                    // Keep the logo data for potential reuse in cloning
                    const savedLogoFile = lastLogoFile;
                    
                    updateOrderSummary();
                    updateLoopPreview();
                    
                    // Restore the logo file data after clearing the form
                    lastLogoFile = savedLogoFile;
                    
                    // Scroll to the success message
                    $('html, body').animate({
                        scrollTop: $('#cllf-form-messages').offset().top - 100
                    }, 500);
                } else {
                    const errorMsg = response.data || 'An error occurred. Please try again.';
                    console.error('CLLF: Form submission error:', errorMsg);
                    
                    // If nonce error, try to refresh nonce and inform user
                    if (errorMsg.toLowerCase().includes('nonce') || errorMsg.toLowerCase().includes('security') || errorMsg.toLowerCase().includes('session')) {
                        $('#cllf-form-messages')
                            .addClass('cllf-error')
                            .text('Your session has expired. Refreshing the page...');
                        
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $('#cllf-form-messages')
                            .addClass('cllf-error')
                            .text(errorMsg);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('CLLF: AJAX error:', status, error);
                console.error('CLLF: Response:', xhr.responseText);
                $('#cllf-form-messages')
                    .addClass('cllf-error')
                    .text('A connection error occurred. Please check your internet connection and try again.');
            },
            complete: function() {
                // Remove loading state
                $('#cllf-form').removeClass('cllf-loading');
                $('#cllf-submit-btn').prop('disabled', false);
            }
        });
    }

    /**
     * Validate the form before submission
     */
    function validateForm() {
        const loopColor = $('#loop_color').val();
        const tagInfoType = $('input[name="tag_info_type"]:checked').val();
        const hasLogo = $('input[name="has_logo"]:checked').val();
        let valid = true;
        let errorMessage = '';
        
        // Clear previous error messages
        $('#cllf-form-messages').removeClass('cllf-error').empty();
        
        // Check required fields
        if (!loopColor) {
            errorMessage = 'Please select a loop color.';
            valid = false;
        }
        
        // Validate tag information
        if (tagInfoType === 'Numbers') {
            const selectedNumbers = $('.number-checkbox:checked').length;
            if (selectedNumbers === 0) {
                errorMessage = 'Please select at least one number.';
                valid = false;
            }
        } else if (tagInfoType === 'Names') {
            const nameInputs = $('input[name="tag_names[]"]').length;
            if (nameInputs === 0) {
                errorMessage = 'Please enter at least one name.';
                valid = false;
            }
        }
        
        // Validate logo file if required
        if (hasLogo === 'Yes') {
            const logoFile = $('#logo_file')[0].files[0];
            // Check for either a new file or a previously stored file
            if (!logoFile && !lastLogoFile) {
                errorMessage = 'Please upload a logo file.';
                valid = false;
            }
        }
        
        // Display error message if validation fails
        if (!valid) {
            $('#cllf-form-messages')
                .addClass('cllf-error')
                .text(errorMessage);
        }
        
        return valid;
    }

    /**
     * Clone the last form submission with a new color
     */
    function cloneFormWithNewColor() {
        if (!lastFormData) {
            alert('No previous form data available to clone.');
            return;
        }
        
        // Reset the form first
        $('#cllf-form')[0].reset();
        $('#names-list-container').empty();
        $('#logo_preview').empty();
        
        // Set sock clips
        $(`input[name="sock_clips"][value="${lastFormData.sockClips}"]`).prop('checked', true);
        
        // Set has logo
        $(`input[name="has_logo"][value="${lastFormData.hasLogo}"]`).prop('checked', true);
        if (lastFormData.hasLogo === 'Yes') {
            $('#logo-upload-container').show();
            
            // Restore the logo preview if available
            if (lastFormData.logoFile) {
                lastLogoFile = lastFormData.logoFile; // Update the current logo file reference
                const img = $('<img>').attr('src', lastFormData.logoFile.dataUrl);
                $('#logo_preview').empty().append(img);
                
                // Create a note about the logo file
                const noteText = $('<p>').addClass('logo-note').text(
                    `Previous logo "${lastFormData.logoFile.name}" will be used. You can select a different file if needed.`
                );
                $('#logo_preview').append(noteText);
            }
        } else {
            $('#logo-upload-container').hide();
        }
        
        // Add note about custom font if one was previously uploaded
        if (lastFormData.customFontName) {
            const fontNote = $('<p class="font-info">').text(
                `You previously uploaded "${lastFormData.customFontName}". Upload a new file if you wish to change it.`
            );
            $('.font-disclaimer').append(fontNote);
        }
        
        // Set sport word
        $('#sport_word').val(lastFormData.sportWord);
        
        // Set tag info type
        $(`input[name="tag_info_type"][value="${lastFormData.tagInfoType}"]`).prop('checked', true);
        
        // Set tag info based on type
        if (lastFormData.tagInfoType === 'Numbers') {
            $('#numbers-container').show();
            $('#names-container').hide();
            
            // Uncheck all numbers first
            $('.number-checkbox').prop('checked', false);
            
            // Check the selected numbers
            lastFormData.tagNumbers.forEach(number => {
                $(`.number-checkbox[value="${number}"]`).prop('checked', true);
            });
            
            // Update the select all checkbox
            updateSelectAllCheckbox();
        } else {
            $('#numbers-container').hide();
            $('#names-container').show();
            
            // Add name fields
            lastFormData.tagNames.forEach((name, index) => {
                addNameField(name, index);
            });
        }
        
        // Set add blanks
        $('#add_blanks').val(lastFormData.addBlanks);
        
        // Set num sets
        $('#num_sets').val(lastFormData.numSets);
        
        // Set order notes
        if (lastFormData.orderNotes) {
            $('#order_notes').val(lastFormData.orderNotes);
        }
        
        // Clear the loop color selection to prompt the user to select a new color
        $('#loop_color').val('');
        
        // Focus on the loop color dropdown
        $('#loop_color').focus();
        
        // Scroll to the top of the form
        $('html, body').animate({
            scrollTop: $('#cllf-form').offset().top - 50
        }, 500);
        
        // Update the form summary
        updateOrderSummary();
        
        // Update the loop preview
        updateLoopPreview();
        
        // Show the form buttons again
        $('.cllf-button-container').show();
        
        // Hide the success message
        $('#cllf-form-messages').slideUp(300, function() {
            $(this).removeClass('cllf-success').empty();
        });
    }
    
    /**
     * Extract colors from the uploaded logo
     */
    function extractColorsFromLogo(logoUrl, callback) {
        console.log('Starting color extraction from logo URL:', logoUrl);
        
        $.ajax({
            url: cllfVars.ajaxurl,
            type: 'POST',
            data: {
                action: 'cllf_extract_logo_colors',
                nonce: currentNonce, // USE FRESH NONCE
                logo_url: logoUrl
            },
            success: function(response) {
                console.log('Color extraction response received:', response);
                
                // Remove loading states
                $('#text_color').prop('disabled', false);
                $('#color-preview').removeClass('loading');
                $('#text-color-container').removeClass('color-loading');
                
                if (response.success && response.data && response.data.length > 0) {
                    console.log('Colors extracted successfully:', response.data);
                    extractedColors = response.data;
                    updateColorDropdown(extractedColors);
                } else {
                    console.error('Error extracting colors or empty result:', response.data);
                    // Use fallback colors if extraction fails
                    useFallbackColors();
                }
                
                if (typeof callback === 'function') {
                    callback();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error during color extraction:', status, error);
                console.error('Response:', xhr.responseText);
                
                // Remove loading states
                $('#text_color').prop('disabled', false);
                $('#color-preview').removeClass('loading');
                $('#text-color-container').removeClass('color-loading');
                
                // Use fallback colors
                useFallbackColors();
                
                if (typeof callback === 'function') {
                    callback();
                }
            },
            timeout: 20000 // 20 second timeout for complex formats
        });
    }
    
    /**
     * Extract colors from SVG via client-side parsing
     */
    function extractSvgColorsClientSide(svgUrl) {
        console.log('Attempting client-side SVG color extraction:', svgUrl);
        
        // Fetch the SVG content
        fetch(svgUrl)
            .then(response => response.text())
            .then(svgContent => {
                // Extract colors using regex
                const colors = extractColorsFromSvgContent(svgContent);
                console.log('Client-side extracted SVG colors:', colors);
                
                if (colors.length > 0) {
                    // Format colors for dropdown
                    const colorData = colors.map((color, index) => {
                        if (index === 0 && color === '#000000') {
                            return { hex: color, name: 'Black' };
                        }
                        return { hex: color, name: `Logo Color ${index}` };
                    });
                    
                    // Update dropdown
                    updateColorDropdown(colorData);
                } else {
                    useFallbackColors();
                }
            })
            .catch(error => {
                console.error('Error fetching SVG content:', error);
                useFallbackColors();
            });
    }
    
    /**
     * Extract colors from SVG content string
     */
    function extractColorsFromSvgContent(svgContent) {
        const colors = new Set();
        
        // Add black as default
        colors.add('#000000');
        
        // Match hex colors in various attributes
        const hexPattern = /#[0-9a-fA-F]{3,6}/g;
        const hexMatches = svgContent.match(hexPattern) || [];
        
        hexMatches.forEach(color => {
            // Normalize color
            let normalizedColor = color.toLowerCase();
            
            // Convert 3-digit hex to 6-digit
            if (normalizedColor.length === 4) {
                const r = normalizedColor.charAt(1);
                const g = normalizedColor.charAt(2);
                const b = normalizedColor.charAt(3);
                normalizedColor = `#${r}${r}${g}${g}${b}${b}`;
            }
            
            colors.add(normalizedColor);
        });
        
        // Also check for RGB values
        const rgbPattern = /rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/g;
        let rgbMatch;
        while ((rgbMatch = rgbPattern.exec(svgContent)) !== null) {
            const r = parseInt(rgbMatch[1]);
            const g = parseInt(rgbMatch[2]);
            const b = parseInt(rgbMatch[3]);
            
            if (!isNaN(r) && !isNaN(g) && !isNaN(b)) {
                const hexColor = rgbToHex(r, g, b);
                colors.add(hexColor);
            }
        }
        
        // Convert to array, limit to 5 colors
        return Array.from(colors).slice(0, 5);
    }
    
    /**
     * Use fallback colors if extraction fails
     */
    function useFallbackColors() {
        console.log('Using fallback colors');
        
        // Generate colors based on the image
        const img = $('#logo_preview img')[0];
        let fallbackColors = [
            { hex: '#000000', name: 'Black' }
        ];
        
        // Try to get some colors from the image using canvas if available
        if (img && typeof HTMLCanvasElement !== 'undefined') {
            try {
                const canvas = document.createElement('canvas');
                const width = canvas.width = Math.min(img.naturalWidth, 100);
                const height = canvas.height = Math.min(img.naturalHeight, 100);
                
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                
                // Sample some points
                const samplePoints = [
                    {x: Math.floor(width * 0.25), y: Math.floor(height * 0.25)},
                    {x: Math.floor(width * 0.75), y: Math.floor(height * 0.25)},
                    {x: Math.floor(width * 0.25), y: Math.floor(height * 0.75)},
                    {x: Math.floor(width * 0.75), y: Math.floor(height * 0.75)}
                ];
                
                samplePoints.forEach((point, index) => {
                    const pixel = ctx.getImageData(point.x, point.y, 1, 1).data;
                    const hex = rgbToHex(pixel[0], pixel[1], pixel[2]);
                    fallbackColors.push({
                        hex: hex,
                        name: `Logo Color ${index + 1}`
                    });
                });
            } catch (e) {
                console.error('Canvas color sampling failed:', e);
                // Fall back to default colors
                fallbackColors = [
                    { hex: '#000000', name: 'Black' },
                    { hex: '#3366cc', name: 'Logo Color 1' },
                    { hex: '#dc3545', name: 'Logo Color 2' },
                    { hex: '#28a745', name: 'Logo Color 3' },
                    { hex: '#ffc107', name: 'Logo Color 4' }
                ];
            }
        } else {
            // If canvas is not available or no image, use default colors
            fallbackColors = [
                { hex: '#000000', name: 'Black' },
                { hex: '#3366cc', name: 'Logo Color 1' },
                { hex: '#dc3545', name: 'Logo Color 2' },
                { hex: '#28a745', name: 'Logo Color 3' },
                { hex: '#ffc107', name: 'Logo Color 4' }
            ];
        }
        
        updateColorDropdown(fallbackColors);
    }
    
    /**
     * Update the color dropdown with extracted colors
     */
    function updateColorDropdown(colors) {
        const $dropdown = $('#text_color');
        
        // Clear existing color options except Black and Custom
        $dropdown.find('option').not(':first').not(':last').remove();
        
        // Add extracted colors
        colors.forEach(function(color, index) {
            if (index === 0 && color.hex === '#000000') {
                return; // Skip black as it's already the first option
            }
            
            const $option = $('<option></option>')
                .val(color.hex)
                .attr('data-color', color.hex)
                .text(color.name);
            
            $dropdown.find('option:last').before($option);
        });
        
        // Reset to black
        $dropdown.val('#000000');
        selectedTextColor = '#000000';
        updateColorPreview('#000000');
        updateLoopPreview();
    }
    
    /**
     * Reset the color dropdown to default state
     */
    function resetColorDropdown() {
        const $dropdown = $('#text_color');
        
        // Clear extracted colors
        $dropdown.find('option').not(':first').not(':last').remove();
        
        // Reset to black
        $dropdown.val('#000000');
        selectedTextColor = '#000000';
        updateColorPreview('#000000');
        $('#custom-color-container').hide();
        updateLoopPreview();
    }
    
    /**
     * Update the color preview
     */
    function updateColorPreview(color) {
        $('#color-preview span').css('background-color', color);
    }
    
    /**
     * Check if a string is a valid color value
     */
    function isValidColor(color) {
        const style = new Option().style;
        style.color = color;
        return !!style.color;
    }
    
    /**
     * Initialize a simple color picker
     */
    function initColorPicker() {
        // Create tabs for different color formats
        const $container = $('#color-picker-container');
        
        $container.html(`
            <div class="color-picker-tabs">
                <div class="color-picker-tab active" data-tab="hex">HEX</div>
                <div class="color-picker-tab" data-tab="rgb">RGB</div>
                <div class="color-picker-tab" data-tab="cmyk">CMYK</div>
            </div>
            <div class="color-picker-content">
                <div class="color-picker-panel active" id="hex-panel">
                    <input type="text" id="hex-input" placeholder="#000000" value="#000000">
                    <div class="color-picker-swatches">
                        <div class="color-swatch" style="background-color: #ff0000" data-color="#ff0000"></div>
                        <div class="color-swatch" style="background-color: #00ff00" data-color="#00ff00"></div>
                        <div class="color-swatch" style="background-color: #0000ff" data-color="#0000ff"></div>
                        <div class="color-swatch" style="background-color: #ffff00" data-color="#ffff00"></div>
                        <div class="color-swatch" style="background-color: #ff00ff" data-color="#ff00ff"></div>
                        <div class="color-swatch" style="background-color: #00ffff" data-color="#00ffff"></div>
                    </div>
                </div>
                <div class="color-picker-panel" id="rgb-panel">
                    <div>
                        <label>R: <input type="number" id="rgb-r" min="0" max="255" value="0"></label>
                    </div>
                    <div>
                        <label>G: <input type="number" id="rgb-g" min="0" max="255" value="0"></label>
                    </div>
                    <div>
                        <label>B: <input type="number" id="rgb-b" min="0" max="255" value="0"></label>
                    </div>
                </div>
                <div class="color-picker-panel" id="cmyk-panel">
                    <div>
                        <label>C: <input type="number" id="cmyk-c" min="0" max="100" value="0">%</label>
                    </div>
                    <div>
                        <label>M: <input type="number" id="cmyk-m" min="0" max="100" value="0">%</label>
                    </div>
                    <div>
                        <label>Y: <input type="number" id="cmyk-y" min="0" max="100" value="0">%</label>
                    </div>
                    <div>
                        <label>K: <input type="number" id="cmyk-k" min="0" max="100" value="100">%</label>
                    </div>
                </div>
            </div>
        `);
        
        // Tab switching
        $('.color-picker-tab').on('click', function() {
            $('.color-picker-tab').removeClass('active');
            $(this).addClass('active');
            
            const tab = $(this).data('tab');
            $('.color-picker-panel').removeClass('active');
            $(`#${tab}-panel`).addClass('active');
        });
        
        // Color swatch clicking
        $('.color-swatch').on('click', function() {
            const color = $(this).data('color');
            $('#hex-input').val(color);
            updateColorFromHex(color);
        });
        
        // HEX input handling
        $('#hex-input').on('input', function() {
            const color = $(this).val();
            if (isValidColor(color)) {
                updateColorFromHex(color);
            }
        });
        
        // RGB inputs handling
        $('#rgb-r, #rgb-g, #rgb-b').on('input', function() {
            const r = parseInt($('#rgb-r').val()) || 0;
            const g = parseInt($('#rgb-g').val()) || 0;
            const b = parseInt($('#rgb-b').val()) || 0;
            
            const hex = rgbToHex(r, g, b);
            updateColorFromHex(hex, false);
            
            // Update CMYK values
            const cmyk = rgbToCmyk(r, g, b);
            $('#cmyk-c').val(Math.round(cmyk.c));
            $('#cmyk-m').val(Math.round(cmyk.m));
            $('#cmyk-y').val(Math.round(cmyk.y));
            $('#cmyk-k').val(Math.round(cmyk.k));
        });
        
        // CMYK inputs handling
        $('#cmyk-c, #cmyk-m, #cmyk-y, #cmyk-k').on('input', function() {
            const c = parseInt($('#cmyk-c').val()) || 0;
            const m = parseInt($('#cmyk-m').val()) || 0;
            const y = parseInt($('#cmyk-y').val()) || 0;
            const k = parseInt($('#cmyk-k').val()) || 0;
            
            const rgb = cmykToRgb(c, m, y, k);
            $('#rgb-r').val(Math.round(rgb.r));
            $('#rgb-g').val(Math.round(rgb.g));
            $('#rgb-b').val(Math.round(rgb.b));
            
            const hex = rgbToHex(rgb.r, rgb.g, rgb.b);
            updateColorFromHex(hex, false);
        });
    }
    
    /**
     * Update the custom color based on HEX input
     */
    function updateColorFromHex(hex, updateInputs = true) {
        // Update custom color input
        $('#custom_color').val(hex);
        selectedTextColor = hex;
        
        // Update preview
        updateColorPreview(hex);
        
        if (updateInputs) {
            // Update RGB inputs
            const rgb = hexToRgb(hex);
            if (rgb) {
                $('#rgb-r').val(rgb.r);
                $('#rgb-g').val(rgb.g);
                $('#rgb-b').val(rgb.b);
                
                // Update CMYK inputs
                const cmyk = rgbToCmyk(rgb.r, rgb.g, rgb.b);
                $('#cmyk-c').val(Math.round(cmyk.c));
                $('#cmyk-m').val(Math.round(cmyk.m));
                $('#cmyk-y').val(Math.round(cmyk.y));
                $('#cmyk-k').val(Math.round(cmyk.k));
            }
        }
        
        // Update loop preview
        updateLoopPreview();
    }
    
    /**
     * Convert HEX to RGB
     */
    function hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    }
    
    /**
     * Convert RGB to HEX
     */
    function rgbToHex(r, g, b) {
        return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
    }
    
    /**
     * Convert RGB to CMYK
     */
    function rgbToCmyk(r, g, b) {
        r = r / 255;
        g = g / 255;
        b = b / 255;
        
        const k = 1 - Math.max(r, g, b);
        const c = (1 - r - k) / (1 - k) || 0;
        const m = (1 - g - k) / (1 - k) || 0;
        const y = (1 - b - k) / (1 - k) || 0;
        
        return {
            c: c * 100,
            m: m * 100,
            y: y * 100,
            k: k * 100
        };
    }
    
    /**
     * Convert CMYK to RGB
     */
    function cmykToRgb(c, m, y, k) {
        c = c / 100;
        m = m / 100;
        y = y / 100;
        k = k / 100;
        
        const r = 255 * (1 - c) * (1 - k);
        const g = 255 * (1 - m) * (1 - k);
        const b = 255 * (1 - y) * (1 - k);
        
        return {
            r: r,
            g: g,
            b: b
        };
    }
})(jQuery);