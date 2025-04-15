/**
 * Custom Laundry Loops Form Scripts
 */
(function($) {
    'use strict';

    // Variable to store the last form submission data
    let lastFormData = null;
    
    // Variable to store the last uploaded logo
    let lastLogoFile = null;

    // Initialize the form when document is ready
    $(document).ready(function() {
        // Handle logo upload option toggle
        $('input[name="has_logo"]').on('change', function() {
            if ($(this).val() === 'Yes') {
                $('#logo-upload-container').slideDown();
            } else {
                $('#logo-upload-container').slideUp();
                $('#logo_file').val('');
                $('#logo_preview').empty();
            }
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
        $('input[name="num_sets"], #add_blanks, input[name="loop_color"], input[name="sock_clips"], input[name="has_logo"], #sport_word').on('change keyup', function() {
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

        // Initialize order summary
        updateOrderSummary();
        
        // Initialize loop preview
        updateLoopPreview();
    });

    /**
     * Function to save the current form data before submission
     */
    function saveFormData() {
        const data = {
            sockClips: $('input[name="sock_clips"]:checked').val(),
            hasLogo: $('input[name="has_logo"]:checked').val(),
            logoFile: lastLogoFile, // Store the logo file data
            sportWord: $('#sport_word').val(),
            tagInfoType: $('input[name="tag_info_type"]:checked').val(),
            addBlanks: $('#add_blanks').val(),
            numSets: $('input[name="num_sets"]:checked').val(),
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
            .attr('required', true);
        
        const removeBtn = $('<button>')
            .attr('type', 'button')
            .addClass('cllf-remove-name')
            .html('&times;')
            .on('click', function() {
                $(this).parent().remove();
                updateOrderSummary();
                updateLoopPreview();
            });
        
        nameField.append(input).append(removeBtn);
        $('#names-list-container').append(nameField);
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
        
        const sets = parseInt($('input[name="num_sets"]:checked').val()) || 1;
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
        
        // Update loop color image if available
        if (loopColor) {
            // Try to load a color-specific image
            const colorImage = `${cllfVars.pluginUrl}images/loop-${loopColor.toLowerCase().replace(/\s+/g, '-')}.png`;
            
            // Check if the image exists
            $.get(colorImage)
                .done(function() {
                    // Image exists, use it
                    $('#cllf-loop-color-image').attr('src', colorImage);
                })
                .fail(function() {
                    // Image doesn't exist, use placeholder
                    $('#cllf-loop-color-image').attr('src', `${cllfVars.pluginUrl}images/loop-placeholder.png`);
                });
        } else {
            // No color selected, use placeholder
            $('#cllf-loop-color-image').attr('src', `${cllfVars.pluginUrl}images/loop-placeholder.png`);
        }
        
        // Update logo preview
        if (hasLogo) {
            if ($('#logo_preview img').length > 0) {
                // Use the visible preview image
                const logoSrc = $('#logo_preview img').attr('src');
                $('#cllf-preview-logo').html(`<img src="${logoSrc}" alt="Logo">`);
            } else if (lastLogoFile) {
                // Use the stored logo if available
                $('#cllf-preview-logo').html(`<img src="${lastLogoFile.dataUrl}" alt="Logo">`);
            } else {
                $('#cllf-preview-logo').empty();
            }
        } else {
            $('#cllf-preview-logo').empty();
        }
        
        // Update sport/word text
        if (sportWord) {
            $('#cllf-preview-text').text(sportWord).show();
        } else {
            $('#cllf-preview-text').hide();
        }
        
        // Update tag preview (first number or name)
        let tagText = '';
        
        if (tagInfoType === 'Numbers') {
            // Get the first checked number
            const firstCheckedNumber = $('.number-checkbox:checked').first().val();
            if (firstCheckedNumber !== undefined) {
                tagText = firstCheckedNumber;
            }
        } else {
            // Get the first name
            const firstName = $('input[name="tag_names[]"]').first().val();
            if (firstName) {
                tagText = firstName;
            }
        }
        
        if (tagText) {
            $('#cllf-preview-tag').text(tagText).show();
        } else {
            $('#cllf-preview-tag').hide();
        }
    }

    /**
     * Clear the form and reset to default values
     */
    function clearForm() {
        // Confirm before clearing
        if (confirm('Are you sure you want to clear the form? All entered data will be lost.')) {
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
            $('input[name="num_sets"][value="1"]').prop('checked', true);
            
            // Show numbers container, hide names container
            $('#numbers-container').show();
            $('#names-container').hide();
            
            // Uncheck all number checkboxes
            $('.number-checkbox').prop('checked', false);
            $('#select-all-numbers').prop('checked', false);
            
            // Reset add blanks dropdown
            $('#add_blanks').val('0');
            
            // Reset loop color dropdown
            $('#loop_color').val('');
            
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
        }
    }

    /**
     * Submit the form via AJAX
     */
    function submitForm() {
        const form = $('#cllf-form')[0];
        const formData = new FormData(form);
        formData.append('action', 'cllf_submit_form');
        
        // Try to get nonce from the form first
        let nonceValue = $('input[name="nonce"]').val();
        
        // If not found, use the one from the global variable
        if (!nonceValue && typeof cllfVars !== 'undefined' && cllfVars.nonce) {
            nonceValue = cllfVars.nonce;
            console.log('Using nonce from global variable:', nonceValue);
        }
        
        formData.append('nonce', nonceValue);
        
        // Save the current form data before submission
        lastFormData = saveFormData();
        
        // Check if we need to add the stored logo to the form data
        const hasLogo = $('input[name="has_logo"]:checked').val() === 'Yes';
        const newLogoFile = $('#logo_file')[0].files[0];
        
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
        
        // Validate form before submission
        if (!validateForm()) {
            return;
        }
        
        // Show loading state
        $('#cllf-form').addClass('cllf-loading');
        $('#cllf-submit-btn').prop('disabled', true);
        $('#cllf-form-messages').removeClass('cllf-error cllf-success').empty();
        
        $.ajax({
            url: cllfVars.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#cllf-form-messages')
                        .hide()
                        .addClass('cllf-success')
                        .html(`
                            <div class="cllf-success-content">
                                <span>Your custom loops have been added to the cart!</span>
                                <div class="cllf-button-group">
                                    <button type="button" id="clone-form-btn" class="button button-secondary">Clone with New Color</button>
                                    <a href="${response.data.cart_url}" class="button">View Cart</a>
                                </div>
                            </div>
                        `)
                        .fadeIn(500);
                    
                    // Add event listener to the clone button
                    $('#clone-form-btn').on('click', function() {
                        cloneFormWithNewColor();
                    });
                    
                    // Reset the form
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
                    $('#cllf-form-messages')
                        .addClass('cllf-error')
                        .text(response.data || 'An error occurred. Please try again.');
                }
            },
            error: function() {
                $('#cllf-form-messages')
                    .addClass('cllf-error')
                    .text('A server error occurred. Please try again.');
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
        $(`input[name="num_sets"][value="${lastFormData.numSets}"]`).prop('checked', true);
        
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
        
        // Hide the success message
        $('#cllf-form-messages').slideUp(300, function() {
            $(this).removeClass('cllf-success').empty();
        });
    }
})(jQuery);