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
                <button type="button" id="clone-form-btn" class="button button-secondary">Clone with New Color</button>
                <a href="' . esc_url(wc_get_cart_url()) . '" class="button">View Cart</a>
            </div>
        </div>
    </div>';
    
    // Add script to handle the clone button click
    echo '<script>
        jQuery(document).ready(function($) {
            $("#clone-form-btn").on("click", function() {
                $(this).closest(".cllf-success-message").slideUp(300);
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
                <label for="loop_color">Loop Color <span class="required">*</span></label>
                <select id="loop_color" name="loop_color" required>
                    <option value="">Select Loop Color</option>
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
                    <option value="Tan (Jute)">Tan (Jute)</option>
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
                <div class="cllf-radio-group">
                    <label>
                        <input type="radio" name="sock_clips" value="Single" required checked>
                        Single
                    </label>
                    <label>
                        <input type="radio" name="sock_clips" value="Double">
                        Double
                    </label>
                </div>
            </div>
        </div>

        <div class="cllf-form-row">
            <div class="cllf-form-group">
                <label>Logo <span class="required">*</span></label>
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

        <div class="cllf-form-row">
            <div class="cllf-form-group">
                <label for="sport_word">Sport or Word on Strap</label>
                <input type="text" id="sport_word" name="sport_word" placeholder="e.g., VOLLEYBALL, PRACTICE, TRAVEL">
                <p class="description">Example: "VOLLEYBALL" or "PRACTICE" or "TRAVEL"</p>
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
                <label>Number of Sets <span class="required">*</span></label>
                <div class="cllf-radio-group">
                    <label>
                        <input type="radio" name="num_sets" value="1" required checked>
                        One
                    </label>
                    <label>
                        <input type="radio" name="num_sets" value="2">
                        Two
                    </label>
                    <label>
                        <input type="radio" name="num_sets" value="3">
                        Three
                    </label>
                    <label>
                        <input type="radio" name="num_sets" value="4">
                        Four
                    </label>
                    <label>
                        <input type="radio" name="num_sets" value="5">
                        Five
                    </label>
                </div>
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
</div>