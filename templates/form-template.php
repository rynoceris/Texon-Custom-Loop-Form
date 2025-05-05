<?php
/**
 * Custom Laundry Loops Form Template
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!class_exists('WooCommerce')) {
    echo '<p>This form requires WooCommerce to be active.</p>';
    return;
}

// Check for success message in transient
$user_id = get_current_user_id();
$success_message = get_transient('cllf_form_success_' . $user_id);

if ($success_message) {
    echo '<div class="cllf-success-message">
        <div class="cllf-success-content">
            <span>' . esc_html($success_message) . '</span>
            <div class="cllf-button-group">
                <button type="button" id="new-order-btn" class="button button-primary">Start a New Loop Order</button>
                <button type="button" id="clone-form-btn" class="button button-secondary">Clone with New Color</button>
                <a href="' . esc_url(wc_get_cart_url()) . '" class="button button-secondary">View Cart</a>
            </div>
        </div>
    </div>';
    
    // Add script to handle the button clicks
    echo '<script>
        jQuery(document).ready(function($) {
            // Hide form buttons when success message is shown
            $(".cllf-button-container").hide();
            
            // New order button
            $("#new-order-btn").on("click", function() {
                // Clear the form
                $("#cllf-form")[0].reset();
                $("#names-list-container").empty();
                $("#logo_preview").empty();
                $("#logo-upload-container").hide();
                
                // Show form buttons again
                $(".cllf-button-container").show();
                
                // Hide success message
                $(this).closest(".cllf-success-message").slideUp(300);
                
                // Focus on the color dropdown
                $("#loop_color").focus();
                
                // Scroll to the top of the form
                $("html, body").animate({
                    scrollTop: $("#cllf-form").offset().top - 50
                }, 500);
            });
            
            // Clone button
            $("#clone-form-btn").on("click", function() {
                $(this).closest(".cllf-success-message").slideUp(300);
                
                // Show form buttons again
                $(".cllf-button-container").show();
                
                // Focus on the color dropdown
                $("#loop_color").focus();
                
                // Scroll to the top of the form
                $("html, body").animate({
                    scrollTop: $("#cllf-form").offset().top - 50
                }, 500);
            });
        });
    </script>';
    
    delete_transient('cllf_form_success_' . $user_id);
}
?>

<div id="cllf-form-container" class="cllf-form-container">
    <form id="cllf-form" class="cllf-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>">
        <div class="cllf-form-row">
            <div class="cllf-form-group">
                <h2>Custom Strap Order Details</h2>
            </div>
        </div>
        <div class="cllf-form-row">
            <div class="cllf-form-group">
                <label for="loop_color">Loop Color <span class="required">*</span></label>
                <!-- In the loop color section, make the thumbnail clickable -->
                <span class="color-thumbnail">
                    <img src="<?php echo CLLF_PLUGIN_URL; ?>images/color-thumb.jpg" alt="Loop Colors" id="color-thumbnail-img" class="clickable-thumbnail">
                    <div class="thumbnail-click-hint">Click to enlarge</div>
                </span>
                <select id="loop_color" name="loop_color" required>
                    <option value="">Select Strap Color</option>
                    <option value="Black">Black</option>
                    <option value="Bone">Bone</option>
                    <option value="Brown">Brown</option>
                    <option value="Grey">Grey</option>
                    <option value="Hunter Green">Hunter Green</option>
                    <option value="Kelly Green">Kelly Green</option>
                    <option value="Maroon">Maroon</option>
                    <option value="Minty Green">Minty Green</option>
                    <option value="Navy Blue">Navy Blue</option>
                    <option value="Neon Yellow">Neon Yellow</option>
                    <option value="Olive">Olive</option>
                    <option value="Orange">Orange</option>
                    <option value="Pacific Blue">Pacific Blue</option>
                    <option value="Pink">Pink</option>
                    <option value="Purple">Purple</option>
                    <option value="Rainbow">Rainbow</option>
                    <option value="Red">Red</option>
                    <option value="Royal Blue">Royal Blue</option>
                    <option value="Sky (Light) Blue">Sky (Light) Blue</option>
                    <option value="Sun Gold">Sun Gold</option>
                    <option value="Tan (Jute)">Tan (Old Gold)</option>
                    <option value="Teal">Teal</option>
                    <option value="Turquoise">Turquoise</option>
                    <option value="Violet">Violet</option>
                    <option value="White">White</option>
                    <option value="Yellow">Yellow</option>
                </select>
            </div>
        </div>

        <div class="cllf-form-row">
            <div class="cllf-form-group">
                <label>Sock Clips <span class="required">*</span></label>
                <div class="cllf-radio-group clip-options">
                    <label class="clip-option">
                        <input type="radio" name="sock_clips" value="Single" required checked>
                        <span class="option-text">Single</span>
                        <span class="clip-thumbnail single-clip-thumbnail">
                            <img src="<?php echo CLLF_PLUGIN_URL; ?>images/single-clip-thumb.jpg" alt="Single Clip">
                        </span>
                    </label>
                    <label class="clip-option">
                        <input type="radio" name="sock_clips" value="Double">
                        <span class="option-text">Double</span>
                        <span class="clip-thumbnail double-clip-thumbnail">
                            <img src="<?php echo CLLF_PLUGIN_URL; ?>images/double-clip-thumb.jpg" alt="Double Clip">
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <div class="cllf-form-row">
            <div class="cllf-form-group">
                <label>Add a Logo? <span class="required">*</span></label>
                <div class="cllf-radio-group">
                    <label>
                        <input type="radio" name="has_logo" value="Yes" required>
                        Yes
                    </label>
                    <label>
                        <input type="radio" name="has_logo" value="No" checked>
                        No
                    </label>
                </div>
            </div>
        </div>

        <div id="logo-upload-container" class="cllf-form-row" style="display: none;">
            <div class="cllf-form-group">
                <label for="logo_file">Upload Logo File</label>
                <input type="file" id="logo_file" name="logo_file" accept=".ai,.pdf,.svg,.eps,.png,.jpg,.jpeg">
                <p class="description">Accepted formats: .ai, .pdf, .svg, .eps, .png, .jpg, .jpeg</p>
                <div id="logo_preview"></div>
            </div>
        </div>
        
        <div id="custom-font-container" class="cllf-form-row">
            <div class="cllf-form-group">
                <label for="font_choice">Font Selection</label>
                <select id="font_choice" name="font_choice">
                    <option value="default" selected>Use Default Font(s)</option>
                    <option value="previous">Use Font(s) Previously Provided</option>
                    <option value="new">I Need to Upload a New Font</option>
                </select>
                <p class="description">By default, we use Jersey M54 for numbers and Arial Black for text. You may choose to use your previously submitted custom font or upload a new custom font file above.</p>
                
                <div id="font-upload-container" style="display: none; margin-top: 15px;">
                    <input type="file" id="custom_font" name="custom_font" accept=".ttf,.otf,.woff,.woff2,.eot,.ps">
                    <p class="description">Upload a custom font file to be used on your tags.</p>
                    <div class="font-disclaimer">
                        <strong>Note:</strong> Your custom font will not be reflected in the preview below, but will be applied to your final product.
                    </div>
                </div>
            </div>
        </div>

        <div class="cllf-form-row">
            <div class="cllf-form-group">
                <label for="sport_word">Sport or Word on Strap</label>
                <input type="text" id="sport_word" name="sport_word" maxlength="20" placeholder="Example: 'VOLLEYBALL' or 'PRACTICE' or 'TRAVEL'">
                <p class="description">Note: For best results, limit to 20 characters</p>
                <div class="char-count-container">
                    <span id="sport-word-char-count">0</span>/20 characters
                </div>
            </div>
        </div>

        <div class="cllf-form-row">
            <div class="cllf-form-group">
                <label>Tag Information <span class="required">*</span></label>
                <div class="cllf-radio-group">
                    <label>
                        <input type="radio" name="tag_info_type" value="Numbers" required checked>
                        Numbers
                    </label>
                    <label>
                        <input type="radio" name="tag_info_type" value="Names">
                        Names
                    </label>
                </div>
            </div>
        </div>

        <div id="numbers-container" class="cllf-form-row">
            <div class="cllf-form-group">
                <label>Select Numbers</label>
                <div class="cllf-numbers-header">
                    <label>
                        <input type="checkbox" id="select-all-numbers">
                        Select All
                    </label>
                    <span class="shift-select-hint">Pro tip: Hold SHIFT while clicking to select multiple numbers in a row</span>
                </div>
                <div class="cllf-numbers-grid">
                    <?php for ($i = 0; $i <= 150; $i++) : ?>
                        <div class="cllf-number-checkbox">
                            <label>
                                <input type="checkbox" name="tag_numbers[]" value="<?php echo $i; ?>" class="number-checkbox">
                                <?php echo $i; ?>
                            </label>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <div id="names-container" class="cllf-form-row" style="display: none;">
            <div class="cllf-form-group">
                <label for="names_paste">Enter Names</label>
                <textarea id="names_paste" placeholder="Paste names here, one per line or comma separated"></textarea>
                <button type="button" id="process-names-btn" class="button">Process Names</button>
                <p class="description">You can copy/paste from a spreadsheet to enter all names.</p>
            </div>
            <div id="names-list-container" class="cllf-names-list">
                <!-- Dynamic name fields will be added here by JavaScript -->
            </div>
        </div>
        
        <div id="text-color-container" class="cllf-form-row">
            <div class="cllf-form-group">
                <label for="text_color">Text Color</label>
                <select id="text_color" name="text_color">
                    <option value="#000000" data-color="#000000">Black</option>
                    <!-- Logo colors will be added here dynamically via JavaScript -->
                    <option value="custom">Custom Color...</option>
                </select>
                <div id="color-preview" class="color-preview">
                    <span style="background-color: #000000;"></span>
                </div>
                
                <div id="custom-color-container" style="display: none; margin-top: 10px;">
                    <label for="custom_color">Custom Color</label>
                    <input type="text" id="custom_color" name="custom_color" placeholder="#000000">
                    <div id="color-picker-container"></div>
                </div>
            </div>
        </div>

        <div class="cllf-form-row">
            <div class="cllf-form-group">
                <label for="add_blanks">Add Blanks</label>
                <select id="add_blanks" name="add_blanks">
                    <?php for ($i = 0; $i <= 150; $i++) : ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
                <p class="description">Will include Logo & Word, if applicable.</p>
            </div>
        </div>

        <div class="cllf-form-row">
            <div class="cllf-form-group">
                <label for="num_sets">Number of Sets <span class="required">*</span></label>
                <select id="num_sets" name="num_sets" required>
                    <?php for ($i = 1; $i <= 25; $i++) : ?>
                        <option value="<?php echo $i; ?>" <?php echo ($i === 1) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
                <p class="description">Select how many sets of the selected numbers/names you need.</p>
            </div>
        </div>

        <div class="cllf-form-row">
            <div class="cllf-form-group">
                <h3>Custom Loop Preview</h3>
                <div id="cllf-loop-preview">
                    <div class="cllf-preview-container">
                        <div class="cllf-loop-image-container">
                            <img id="cllf-loop-color-image" src="<?php echo CLLF_PLUGIN_URL; ?>images/loop-placeholder.png" alt="Loop Preview">
                            <div id="cllf-preview-overlay">
                                <div id="cllf-preview-logo"></div>
                                <div id="cllf-preview-text"></div>
                                <div id="cllf-preview-tag"></div>
                            </div>
                        </div>
                        <p class="cllf-preview-caption">Preview is for visualization purposes only. Actual product may vary slightly.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="cllf-form-row">
            <div class="cllf-order-summary">
                <h3>Order Summary</h3>
                <div class="cllf-order-calculation">
                    <div class="cllf-calc-row">
                        <span class="cllf-calc-label">QUANTITY:</span>
                        <span id="cllf-quantity" class="cllf-calc-value">0</span>
                    </div>
                    <div class="cllf-calc-row">
                        <span class="cllf-calc-label">x SETS:</span>
                        <span id="cllf-sets" class="cllf-calc-value">1</span>
                    </div>
                    <div class="cllf-calc-row">
                        <span class="cllf-calc-label">+ BLANKS:</span>
                        <span id="cllf-blanks" class="cllf-calc-value">0</span>
                    </div>
                    <div class="cllf-calc-row cllf-calc-total">
                        <span class="cllf-calc-label">= TOTAL:</span>
                        <span id="cllf-total" class="cllf-calc-value">0</span>
                    </div>
                </div>
                <div id="cllf-summary-text" class="cllf-summary-text"></div>
            </div>
        </div>

        <div class="cllf-form-row">
            <div class="cllf-form-group">
                <label for="order_notes">Order Notes</label>
                <textarea id="order_notes" name="order_notes" rows="4" placeholder="Enter any special instructions or preferences (e.g., font color, special handling)"></textarea>
                <p class="description">Use this space to provide any additional information about your order.</p>
            </div>
        </div>

        <div class="cllf-form-row">
            <div class="cllf-form-group">
                <div class="cllf-button-container">
                    <button type="button" id="cllf-clear-btn" class="button button-secondary">Clear Form</button>
                    <button type="submit" id="cllf-submit-btn" class="button button-primary">Add to Cart</button>
                </div>
                <div id="cllf-form-messages"></div>
            </div>
        </div>

        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('cllf-nonce'); ?>">
    </form>
    <!-- Scroll to top button -->
    <button id="scroll-to-top" class="cllf-scroll-top" aria-label="Scroll to top">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="18 15 12 9 6 15"></polyline>
        </svg>
    </button>
    <!-- Add at the bottom of the form, just before the closing </div> tag -->
    <!-- Color Modal -->
    <div id="color-modal" class="cllf-modal">
        <div class="cllf-modal-content">
            <span class="cllf-modal-close">&times;</span>
            <img id="color-modal-img" src="<?php echo CLLF_PLUGIN_URL; ?>images/color-thumb.jpg" alt="Loop Colors">
            <div class="cllf-modal-caption">Available Loop Colors</div>
        </div>
    </div>
</div>