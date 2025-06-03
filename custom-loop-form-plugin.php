<?php
/**
 * @package CLLF
 * @wordpress-plugin
 *
 * Plugin Name:          Custom Laundry Loops Form
 * Plugin URI:           https://www.texontowel.com
 * Description:          Display a custom form for ordering custom laundry loops directly on the frontend.
 * Version:              2.2
 * Author:               Texon Towel
 * Author URI:           https://www.texontowel.com
 * Developer:            Ryan Ours
 * Copyright:            © 2025 Texon Towel (email : sales@texontowel.com).
 * License: GNU          General Public License v3.0
 * License URI:          http://www.gnu.org/licenses/gpl-3.0.html
 * Tested up to:         6.8.1
 * WooCommerce:          9.8.3
 * PHP tested up to:     8.2.28
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('CLLF_VERSION', '1.0.0');
define('CLLF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CLLF_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load admin functionality
add_action('init', 'cllf_load_admin_checks');

function cllf_load_admin_checks() {
    if (current_user_can('manage_options')) {
        require_once CLLF_PLUGIN_DIR . 'admin-init.php';
    }
}

// Ensure WooCommerce session for subscribers
add_action('init', 'cllf_current_user_check');

function cllf_current_user_check() {
    $user = wp_get_current_user();
    $allowed_roles = array('subscriber');
    
    if (array_intersect($allowed_roles, $user->roles)) {
        add_action('woocommerce_init', function() {
            if (!WC()->session->has_session()) {
                WC()->session->set_customer_session_cookie(true);
            }
        });
    }
}

// Allow duplicate SKUs
add_filter('wc_product_has_unique_sku', '__return_false', PHP_INT_MAX);

// Enqueue scripts and styles
function cllf_enqueue_scripts() {
    global $post;
    
    // Check if current page contains our shortcode
    $shortcode_found = false;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'custom_laundry_loops_form')) {
        $shortcode_found = true;
    }
    
    if ($shortcode_found) {
        // Enqueue jQuery UI
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-button');
        wp_enqueue_script('jquery-ui-tooltip');
        
        // Enqueue our custom JS and CSS
        wp_enqueue_style('cllf-styles', CLLF_PLUGIN_URL . 'css/cllf-styles.css', array(), CLLF_VERSION);
        wp_enqueue_script('cllf-scripts', CLLF_PLUGIN_URL . 'js/cllf-scripts.js', array('jquery'), CLLF_VERSION, true);
        
        // Pass variables to JS
        wp_localize_script('cllf-scripts', 'cllfVars', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cllf-nonce'),
            'nonceFieldName' => 'nonce',
            'pluginUrl' => CLLF_PLUGIN_URL
        ));
    }
}
add_action('wp_enqueue_scripts', 'cllf_enqueue_scripts');

// Create directory structure on plugin activation
function cllf_activate() {
    // Create CSS directory
    if (!file_exists(CLLF_PLUGIN_DIR . 'css')) {
        mkdir(CLLF_PLUGIN_DIR . 'css', 0755, true);
    }
    
    // Create JS directory
    if (!file_exists(CLLF_PLUGIN_DIR . 'js')) {
        mkdir(CLLF_PLUGIN_DIR . 'js', 0755, true);
    }
    
    // Create images directory
    if (!file_exists(CLLF_PLUGIN_DIR . 'images')) {
        mkdir(CLLF_PLUGIN_DIR . 'images', 0755, true);
    }
    
    // Create uploads directory
    $upload_dir = wp_upload_dir();
    $cllf_upload_dir = $upload_dir['basedir'] . '/cllf-uploads';
    
    if (!file_exists($cllf_upload_dir)) {
        mkdir($cllf_upload_dir, 0755, true);
    }
    
    // Create a placeholder file for loops
    if (!file_exists(CLLF_PLUGIN_DIR . 'images/loop-placeholder.png')) {
        // Copy placeholder from assets if available or create a basic one
        $default_placeholder = CLLF_PLUGIN_DIR . 'assets/loop-placeholder.png';
        if (file_exists($default_placeholder)) {
            copy($default_placeholder, CLLF_PLUGIN_DIR . 'images/loop-placeholder.png');
        }
    }
}
register_activation_hook(__FILE__, 'cllf_activate');

// Include helper functions
require_once CLLF_PLUGIN_DIR . 'includes/helper-functions.php';

// Include the form template
function cllf_form_shortcode() {
    ob_start();
    include_once CLLF_PLUGIN_DIR . 'templates/form-template.php';
    return ob_get_clean();
}
add_shortcode('custom_laundry_loops_form', 'cllf_form_shortcode');

// Load color extraction functionality
require_once CLLF_PLUGIN_DIR . 'color-extractor.php';

// AJAX handler for form submission
add_action('wp_ajax_cllf_submit_form', 'cllf_handle_form_submission');
add_action('wp_ajax_nopriv_cllf_submit_form', 'cllf_handle_form_submission');

// Alternative direct form submission handler for non-AJAX fallback
add_action('template_redirect', 'cllf_handle_direct_form_submission');

function cllf_handle_direct_form_submission() {
    if (isset($_POST['cllf_direct_submit']) && $_POST['cllf_direct_submit'] === '1') {
        // Process the form submission directly
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        
        if (!wp_verify_nonce($nonce, 'cllf-nonce')) {
            wp_die('Security check failed. Please try again.');
        }
        
        // Process the form data
        $loop_color = isset($_POST['loop_color']) ? sanitize_text_field($_POST['loop_color']) : '';
        $sock_clips = isset($_POST['sock_clips']) ? sanitize_text_field($_POST['sock_clips']) : '';
        $tag_info_type = isset($_POST['tag_info_type']) ? sanitize_text_field($_POST['tag_info_type']) : '';
        $tag_numbers = isset($_POST['tag_numbers']) ? array_map('intval', $_POST['tag_numbers']) : array();
        $tag_names = isset($_POST['tag_names']) ? array_map('sanitize_text_field', $_POST['tag_names']) : array();
        $add_blanks = isset($_POST['add_blanks']) ? intval($_POST['add_blanks']) : 0;
        $num_sets = isset($_POST['num_sets']) ? intval($_POST['num_sets']) : 1;
        
        // Calculate total
        $tag_count = ($tag_info_type === 'Numbers') ? count($tag_numbers) : count($tag_names);
        $total_loops = ($tag_count * $num_sets) + $add_blanks;
        
        // Add to cart but don't redirect
        $cart_url = cllf_process_order_and_add_to_cart($loop_color, $sock_clips, $total_loops);
        
        // Set a transient to display a success message
        set_transient('cllf_form_success_' . get_current_user_id(), 'Your custom loops have been added to the cart!', 60);
        
        // Redirect back to the same page
        wp_redirect(remove_query_arg(array('cllf_direct_submit'), wp_get_referer()));
        exit;
    }
}

function cllf_handle_form_submission() {
    // For debugging
    error_log('Form submission received');
    if (isset($_POST['nonce'])) {
        error_log('Nonce value received: ' . $_POST['nonce']);
    } else {
        error_log('No nonce value received in POST');
    }
    
    // Verify nonce with more detailed error reporting
    if (!isset($_POST['nonce'])) {
        wp_send_json_error('Security check failed: Nonce is missing');
        exit;
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'cllf-nonce')) {
        error_log('Nonce verification failed for value: ' . $_POST['nonce']);
        wp_send_json_error('Security check failed: Invalid nonce value');
        exit;
    }
    
    error_log('Nonce verification successful');
    
    // Get and sanitize form data
    $loop_color = isset($_POST['loop_color']) ? sanitize_text_field($_POST['loop_color']) : '';
    $sock_clips = isset($_POST['sock_clips']) ? sanitize_text_field($_POST['sock_clips']) : '';
    $has_logo = isset($_POST['has_logo']) ? sanitize_text_field($_POST['has_logo']) : 'No';
    $sport_word = isset($_POST['sport_word']) ? sanitize_text_field($_POST['sport_word']) : '';
    // Enforce 20 character limit
    if (strlen($sport_word) > 20) {
        $sport_word = substr($sport_word, 0, 20);
    }
    $tag_info_type = isset($_POST['tag_info_type']) ? sanitize_text_field($_POST['tag_info_type']) : '';
    $tag_numbers = isset($_POST['tag_numbers']) ? array_map('intval', $_POST['tag_numbers']) : array();
    $tag_names = isset($_POST['tag_names']) ? array_map('sanitize_text_field', $_POST['tag_names']) : array();
    $add_blanks = isset($_POST['add_blanks']) ? intval($_POST['add_blanks']) : 0;
    $num_sets = isset($_POST['num_sets']) ? intval($_POST['num_sets']) : 1;
    $order_notes = isset($_POST['order_notes']) ? sanitize_textarea_field($_POST['order_notes']) : '';
    $text_color = isset($_POST['text_color']) ? sanitize_text_field($_POST['text_color']) : '#000000';
    if ($text_color === 'custom' && isset($_POST['custom_color'])) {
        $text_color = sanitize_text_field($_POST['custom_color']);
    }
    
    // For names, enforce 20 character limit on each name
    if ($tag_info_type === 'Names' && !empty($tag_names)) {
        foreach ($tag_names as $key => $name) {
            if (strlen($name) > 20) {
                $tag_names[$key] = substr($name, 0, 20);
            }
        }
    }
    
    // Validate required fields
    if (empty($loop_color) || empty($sock_clips) || empty($tag_info_type)) {
        wp_send_json_error('Please fill out all required fields');
    }
    
    // Validate tag information
    if ($tag_info_type === 'Numbers' && empty($tag_numbers)) {
        wp_send_json_error('Please select at least one number');
    } elseif ($tag_info_type === 'Names' && empty($tag_names)) {
        wp_send_json_error('Please enter at least one name');
    }
    
    // Handle logo upload if applicable
    $logo_url = '';
    if ($has_logo === 'Yes') {
        // Check if we're using a stored logo from a previous submission
        if (isset($_POST['use_stored_logo']) && $_POST['use_stored_logo'] === 'yes') {
            // If logo is stored in session storage (for cloned submissions)
            if (isset($_POST['logo_in_session_storage']) && $_POST['logo_in_session_storage'] === 'yes') {
                // The logo data URL would be stored in session storage by JavaScript
                // We would need to retrieve it via AJAX or a separate endpoint
                // For now, we'll just store the flag and handle it later
                $logo_url = 'using_previous_logo';
                if (isset($_POST['stored_logo_name'])) {
                    // Store the original filename for reference
                    WC()->session->set('cllf_previous_logo_name', sanitize_text_field($_POST['stored_logo_name']));
                }
            } else if (WC()->session->get('cllf_logo_url')) {
                // Use the logo URL from the previous submission stored in the session
                $logo_url = WC()->session->get('cllf_logo_url');
            }
        } else if (isset($_FILES['logo_file'])) {
            $logo_file = $_FILES['logo_file'];
            $upload_dir = wp_upload_dir();
            $logo_dir = $upload_dir['basedir'] . '/cllf-uploads';
            
            // Check for upload errors
            if ($logo_file['error'] !== UPLOAD_ERR_OK) {
                wp_send_json_error('Logo upload failed with error code: ' . $logo_file['error']);
            }
            
            // Validate file type
            $allowed_types = array('image/jpeg', 'image/png', 'image/svg+xml', 'application/pdf', 'application/illustrator');
            $file_type = wp_check_filetype(basename($logo_file['name']));
            
            if (!in_array($logo_file['type'], $allowed_types)) {
                wp_send_json_error('Invalid file type. Please upload an image, PDF, SVG, or AI file.');
            }
            
            // Generate unique filename
            $filename = wp_unique_filename($logo_dir, $logo_file['name']);
            $logo_path = $logo_dir . '/' . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($logo_file['tmp_name'], $logo_path)) {
                $logo_url = $upload_dir['baseurl'] . '/cllf-uploads/' . $filename;
            } else {
                wp_send_json_error('Failed to save logo file');
            }
        }
    }
    
    $font_choice = isset($_POST['font_choice']) ? sanitize_text_field($_POST['font_choice']) : 'default';
    
    // Handle custom font upload if applicable
    $custom_font_url = '';
    $custom_font_name = '';
    
    if ($font_choice === 'new' && isset($_FILES['custom_font']) && $_FILES['custom_font']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Process the font upload
        $font_file = $_FILES['custom_font'];
        $upload_dir = wp_upload_dir();
        $font_dir = $upload_dir['basedir'] . '/cllf-uploads/fonts';
        
        // Create fonts directory if needed
        if (!file_exists($font_dir)) {
            mkdir($font_dir, 0755, true);
        }
        
        // Check for upload errors
        if ($font_file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('Font upload failed with error code: ' . $font_file['error']);
            exit;
        }
        
        // Validate file type
        $allowed_extensions = array('.ttf', '.otf', '.woff', '.woff2', '.eot', '.ps');
        $file_extension = strtolower(pathinfo($font_file['name'], PATHINFO_EXTENSION));
        
        if (!in_array('.' . $file_extension, $allowed_extensions)) {
            wp_send_json_error('Invalid font file type. Allowed: TTF, OTF, WOFF, WOFF2, EOT, PS');
            exit;
        }
        
        // Generate unique filename
        $filename = wp_unique_filename($font_dir, $font_file['name']);
        $font_path = $font_dir . '/' . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($font_file['tmp_name'], $font_path)) {
            $custom_font_url = $upload_dir['baseurl'] . '/cllf-uploads/fonts/' . $filename;
            $custom_font_name = $font_file['name'];
        } else {
            wp_send_json_error('Failed to save font file');
            exit;
        }
    }
    
    // Store in session
    if ($custom_font_url) {
        WC()->session->set('cllf_custom_font_url', $custom_font_url);
        WC()->session->set('cllf_custom_font_name', $custom_font_name);
    }
    WC()->session->set('cllf_font_choice', $font_choice);
    
    // Calculate total number of loops
    $tag_count = ($tag_info_type === 'Numbers') ? count($tag_numbers) : count($tag_names);
    $total_loops = ($tag_count * $num_sets) + $add_blanks;
    
    // Store data in session for cart processing
    WC()->session->set('cllf_loop_color', $loop_color);
    WC()->session->set('cllf_sock_clips', $sock_clips);
    WC()->session->set('cllf_has_logo', $has_logo);
    WC()->session->set('cllf_logo_url', $logo_url);
    WC()->session->set('cllf_sport_word', $sport_word);
    WC()->session->set('cllf_tag_info_type', $tag_info_type);
    WC()->session->set('cllf_tag_numbers', $tag_numbers);
    WC()->session->set('cllf_tag_names', $tag_names);
    WC()->session->set('cllf_add_blanks', $add_blanks);
    WC()->session->set('cllf_num_sets', $num_sets);
    WC()->session->set('cllf_total_loops', $total_loops);
    WC()->session->set('cllf_order_notes', $order_notes);
    WC()->session->set('cllf_text_color', $text_color);
    
    // Process the order and add items to cart
    $cart_url = cllf_process_order_and_add_to_cart($loop_color, $sock_clips, $total_loops);
    
    // Return success without redirecting
    wp_send_json_success(array('cart_url' => $cart_url, 'message' => 'Your custom loops have been added to the cart!'));
}

/**
 * Process the order and add items to cart
 */
function cllf_process_order_and_add_to_cart($loop_color, $sock_clips, $total_loops) {
    // Get all form data from the session
    $has_logo = WC()->session->get('cllf_has_logo', 'No');
    $sport_word = WC()->session->get('cllf_sport_word', '');
    $tag_info_type = WC()->session->get('cllf_tag_info_type', '');
    $tag_numbers = WC()->session->get('cllf_tag_numbers', array());
    $tag_names = WC()->session->get('cllf_tag_names', array());
    $add_blanks = WC()->session->get('cllf_add_blanks', 0);
    $num_sets = WC()->session->get('cllf_num_sets', 1);
    
    // Get settings
    $clloi_settings = get_option('clloi_settings');
    
    // Helper function to convert color to camel case
    function to_camel_case($color) {
        return ucfirst(strtolower($color));
    }
    
    // Sanitize loop color to ensure consistent formatting
    $color = to_camel_case($loop_color);
    $clips = $sock_clips;
    
    // Create unique key for this combination
    $color_clip_switch = $color . '_' . $clips;
    
    // Determine product SKU and image ID based on color/clip combination
    $sku = '';
    $image_id = 0;
    $price = ($clips === 'Double') ? 4.95 : 4.45;
    
    switch ($color_clip_switch) {
        case 'Black_Single':
            $sku = 'CL-Black';
            $image_id = isset($clloi_settings['clloi_image_id_black_single']) ? $clloi_settings['clloi_image_id_black_single'] : 41859;
            break;
        case 'Bone_Single':
            $sku = 'CL-Bone';
            $image_id = isset($clloi_settings['clloi_image_id_bone_single']) ? $clloi_settings['clloi_image_id_bone_single'] : 41856;
            break;
        case 'Brown_Single':
            $sku = 'CL-Brown';
            $image_id = isset($clloi_settings['clloi_image_id_brown_single']) ? $clloi_settings['clloi_image_id_brown_single'] : 41857;
            break;
        case 'Grey_Single':
            $sku = 'CL-Grey';
            $image_id = isset($clloi_settings['clloi_image_id_grey_single']) ? $clloi_settings['clloi_image_id_grey_single'] : 41860;
            break;
        case 'Hunter green_Single':
            $sku = 'CL-Hunter';
            $image_id = isset($clloi_settings['clloi_image_id_hunter_single']) ? $clloi_settings['clloi_image_id_hunter_single'] : 41854;
            break;
        case 'Kelly green_Single':
            $sku = 'CL-Kelly';
            $image_id = isset($clloi_settings['clloi_image_id_kelly_single']) ? $clloi_settings['clloi_image_id_kelly_single'] : 41853;
            break;
        case 'Maroon_Single':
            $sku = 'CL-Maroon';
            $image_id = isset($clloi_settings['clloi_image_id_maroon_single']) ? $clloi_settings['clloi_image_id_maroon_single'] : 41850;
            break;
        case 'Minty green_Single':
            $sku = 'CL-MintyGreen';
            $image_id = isset($clloi_settings['clloi_image_id_minty_single']) ? $clloi_settings['clloi_image_id_minty_single'] : 41851;
            break;
        case 'Navy blue_Single':
            $sku = 'CL-Navy';
            $image_id = isset($clloi_settings['clloi_image_id_navy_single']) ? $clloi_settings['clloi_image_id_navy_single'] : 41847;
            break;
        case 'Neon yellow_Single':
            $sku = 'CL-Neon';
            $image_id = isset($clloi_settings['clloi_image_id_neon_single']) ? $clloi_settings['clloi_image_id_neon_single'] : 41848;
            break;
        case 'Olive_Single':
            $sku = 'CL-Olive';
            $image_id = isset($clloi_settings['clloi_image_id_olive_single']) ? $clloi_settings['clloi_image_id_olive_single'] : 41849;
            break;
        case 'Orange_Single':
            $sku = 'CL-Orange';
            $image_id = isset($clloi_settings['clloi_image_id_orange_single']) ? $clloi_settings['clloi_image_id_orange_single'] : 41843;
            break;
        case 'Pacific blue_Single':
            $sku = 'CL-PacificBlue';
            $image_id = isset($clloi_settings['clloi_image_id_pacific_single']) ? $clloi_settings['clloi_image_id_pacific_single'] : 41862;
            break;
        case 'Pink_Single':
            $sku = 'CL-Pink';
            $image_id = isset($clloi_settings['clloi_image_id_pink_single']) ? $clloi_settings['clloi_image_id_pink_single'] : 41844;
            break;
        case 'Purple_Single':
            $sku = 'CL-Purple';
            $image_id = isset($clloi_settings['clloi_image_id_purple_single']) ? $clloi_settings['clloi_image_id_purple_single'] : 41845;
            break;
        case 'Rainbow_Single':
            $sku = 'CL-Rainbow';
            $image_id = isset($clloi_settings['clloi_image_id_rainbow_single']) ? $clloi_settings['clloi_image_id_rainbow_single'] : 41846;
            break;
        case 'Red_Single':
            $sku = 'CL-Red';
            $image_id = isset($clloi_settings['clloi_image_id_red_single']) ? $clloi_settings['clloi_image_id_red_single'] : 41842;
            break;
        case 'Royal blue_Single':
            $sku = 'CL-Royal';
            $image_id = isset($clloi_settings['clloi_image_id_royal_single']) ? $clloi_settings['clloi_image_id_royal_single'] : 41861;
            break;
        case 'Sky (light) blue_Single':
            $sku = 'CL-Sky';
            $image_id = isset($clloi_settings['clloi_image_id_sky_single']) ? $clloi_settings['clloi_image_id_sky_single'] : 41852;
            break;
        case 'Sun gold_Single':
            $sku = 'CL-SunGold';
            $image_id = isset($clloi_settings['clloi_image_id_gold_single']) ? $clloi_settings['clloi_image_id_gold_single'] : 41858;
            break;
        case 'Tan (jute)_Single':
            $sku = 'CL-Tan';
            $image_id = isset($clloi_settings['clloi_image_id_tan_single']) ? $clloi_settings['clloi_image_id_tan_single'] : 41841;
            break;
        case 'Teal_Single':
            $sku = 'CL-Teal';
            $image_id = isset($clloi_settings['clloi_image_id_teal_single']) ? $clloi_settings['clloi_image_id_teal_single'] : 41839;
            break;
        case 'Turquoise_Single':
            $sku = 'CL-Turquoise';
            $image_id = isset($clloi_settings['clloi_image_id_turquoise_single']) ? $clloi_settings['clloi_image_id_turquoise_single'] : 41840;
            break;
        case 'Violet_Single':
            $sku = 'CL-Violet';
            $image_id = isset($clloi_settings['clloi_image_id_violet_single']) ? $clloi_settings['clloi_image_id_violet_single'] : 41855;
            break;
        case 'White_Single':
            $sku = 'CL-White';
            $image_id = isset($clloi_settings['clloi_image_id_white_single']) ? $clloi_settings['clloi_image_id_white_single'] : 41837;
            break;
        case 'Yellow_Single':
            $sku = 'CL-Yellow';
            $image_id = isset($clloi_settings['clloi_image_id_yellow_single']) ? $clloi_settings['clloi_image_id_yellow_single'] : 41836;
            break;
        case 'Black_Double':
            $sku = 'CL-Black-2';
            $image_id = isset($clloi_settings['clloi_image_id_black_double']) ? $clloi_settings['clloi_image_id_black_double'] : 44256;
            break;
        case 'Bone_Double':
            $sku = 'CL-Bone-2';
            $image_id = isset($clloi_settings['clloi_image_id_bone_double']) ? $clloi_settings['clloi_image_id_bone_double'] : 44246;
            break;
        case 'Brown_Double':
            $sku = 'CL-Brown-2';
            $image_id = isset($clloi_settings['clloi_image_id_brown_double']) ? $clloi_settings['clloi_image_id_brown_double'] : 44257;
            break;
        case 'Grey_Double':
            $sku = 'CL-Grey-2';
            $image_id = isset($clloi_settings['clloi_image_id_grey_double']) ? $clloi_settings['clloi_image_id_grey_double'] : 7643;
            break;
        case 'Hunter green_Double':
            $sku = 'CL-Hunter-2';
            $image_id = isset($clloi_settings['clloi_image_id_hunter_double']) ? $clloi_settings['clloi_image_id_hunter_double'] : 44258;
            break;
        case 'Kelly green_Double':
            $sku = 'CL-Kelly-2';
            $image_id = isset($clloi_settings['clloi_image_id_kelly_double']) ? $clloi_settings['clloi_image_id_kelly_double'] : 44255;
            break;
        case 'Maroon_Double':
            $sku = 'CL-Maroon-2';
            $image_id = isset($clloi_settings['clloi_image_id_maroon_double']) ? $clloi_settings['clloi_image_id_maroon_double'] : 44267;
            break;
        case 'Minty green_Double':
            $sku = 'CL-MintyGreen-2';
            $image_id = isset($clloi_settings['clloi_image_id_minty_double']) ? $clloi_settings['clloi_image_id_minty_double'] : 44269;
            break;
        case 'Navy blue_Double':
            $sku = 'CL-Navy-2';
            $image_id = isset($clloi_settings['clloi_image_id_navy_double']) ? $clloi_settings['clloi_image_id_navy_double'] : 44259;
            break;
        case 'Neon yellow_Double':
            $sku = 'CL-Neon-2';
            $image_id = isset($clloi_settings['clloi_image_id_neon_double']) ? $clloi_settings['clloi_image_id_neon_double'] : 44252;
            break;
        case 'Olive_Double':
            $sku = 'CL-Olive-2';
            $image_id = isset($clloi_settings['clloi_image_id_olive_double']) ? $clloi_settings['clloi_image_id_olive_double'] : 44260;
            break;
        case 'Orange_Double':
            $sku = 'CL-Orange-2';
            $image_id = isset($clloi_settings['clloi_image_id_orange_double']) ? $clloi_settings['clloi_image_id_orange_double'] : 44261;
            break;
        case 'Pacific blue_Double':
            $sku = 'CL-PacificBlue-2';
            $image_id = isset($clloi_settings['clloi_image_id_pacific_double']) ? $clloi_settings['clloi_image_id_pacific_double'] : 44262;
            break;
        case 'Pink_Double':
            $sku = 'CL-Pink-2';
            $image_id = isset($clloi_settings['clloi_image_id_pink_double']) ? $clloi_settings['clloi_image_id_pink_double'] : 44238;
            break;
        case 'Purple_Double':
            $sku = 'CL-Purple-2';
            $image_id = isset($clloi_settings['clloi_image_id_purple_double']) ? $clloi_settings['clloi_image_id_purple_double'] : 44239;
            break;
        case 'Rainbow_Double':
            $sku = 'CL-Rainbow-2';
            $image_id = isset($clloi_settings['clloi_image_id_rainbow_double']) ? $clloi_settings['clloi_image_id_rainbow_double'] : 44264;
            break;
        case 'Red_Double':
            $sku = 'CL-Red-2';
            $image_id = isset($clloi_settings['clloi_image_id_red_double']) ? $clloi_settings['clloi_image_id_red_double'] : 44241;
            break;
        case 'Royal blue_Double':
            $sku = 'CL-Royal-2';
            $image_id = isset($clloi_settings['clloi_image_id_royal_double']) ? $clloi_settings['clloi_image_id_royal_double'] : 44242;
            break;
        case 'Sky (light) blue_Double':
            $sku = 'CL-Sky-2';
            $image_id = isset($clloi_settings['clloi_image_id_sky_double']) ? $clloi_settings['clloi_image_id_sky_double'] : 44243;
            break;
        case 'Sun gold_Double':
            $sku = 'CL-SunGold-2';
            $image_id = isset($clloi_settings['clloi_image_id_gold_double']) ? $clloi_settings['clloi_image_id_gold_double'] : 44244;
            break;
        case 'Tan (jute)_Double':
            $sku = 'CL-Tan-2';
            $image_id = isset($clloi_settings['clloi_image_id_tan_double']) ? $clloi_settings['clloi_image_id_tan_double'] : 44245;
            break;
        case 'Teal_Double':
            $sku = 'CL-Teal-2';
            $image_id = isset($clloi_settings['clloi_image_id_teal_double']) ? $clloi_settings['clloi_image_id_teal_double'] : 44272;
            break;
        case 'Turquoise_Double':
            $sku = 'CL-Turquoise-2';
            $image_id = isset($clloi_settings['clloi_image_id_turquoise_double']) ? $clloi_settings['clloi_image_id_turquoise_double'] : 44273;
            break;
        case 'Violet_Double':
            $sku = 'CL-Violet-2';
            $image_id = isset($clloi_settings['clloi_image_id_violet_double']) ? $clloi_settings['clloi_image_id_violet_double'] : 44248;
            break;
        case 'White_Double':
            $sku = 'CL-White-2';
            $image_id = isset($clloi_settings['clloi_image_id_white_double']) ? $clloi_settings['clloi_image_id_white_double'] : 44249;
            break;
        case 'Yellow_Double':
            $sku = 'CL-Yellow-2';
            $image_id = isset($clloi_settings['clloi_image_id_yellow_double']) ? $clloi_settings['clloi_image_id_yellow_double'] : 44250;
            break;
        default:
            error_log('Unmatched case: ' . $color_clip_switch);
            break;
    }
    
    // Create enhanced product title with all custom details
    $product_title = 'Custom Laundry Straps with Sublimated ID Tags - Color: ' . $color . ' - Clips: ' . $clips;
    
    // Your existing title creation code...
    if (!empty($sport_word)) {
        $product_title .= ' - Text: "' . $sport_word . '"';
    }
    
    $product_title .= ' - Logo: ' . ($has_logo === 'Yes' ? 'Yes' : 'No');
    $product_title .= ' - Tag Type: ' . $tag_info_type;
    
    // Add number or name details
    if ($tag_info_type === 'Numbers') {
        // Sort numbers for a cleaner representation
        sort($tag_numbers, SORT_NUMERIC);
        
        // Format numbers as ranges (e.g., 1-5, 7, 9-12)
        $ranges = [];
        $range_start = null;
        $range_end = null;
        
        foreach ($tag_numbers as $num) {
            if ($range_start === null) {
                $range_start = $num;
                $range_end = $num;
            } elseif ($num == $range_end + 1) {
                $range_end = $num;
            } else {
                // End the previous range
                if ($range_start == $range_end) {
                    $ranges[] = $range_start;
                } else {
                    $ranges[] = $range_start . '-' . $range_end;
                }
                
                // Start a new range
                $range_start = $num;
                $range_end = $num;
            }
        }
        
        // Add the last range
        if ($range_start !== null) {
            if ($range_start == $range_end) {
                $ranges[] = $range_start;
            } else {
                $ranges[] = $range_start . '-' . $range_end;
            }
        }
        
        $numbers_summary = implode(', ', $ranges);
        $product_title .= ' - Numbers: ' . $numbers_summary;
    } else if ($tag_info_type === 'Names') {
        // Improved handling for names
        $name_count = count($tag_names);
        
        if ($name_count <= 3) {
            // Show all names if 3 or fewer
            $product_title .= ' - Names: ' . implode(', ', $tag_names);
        } else {
            // Show first 2 names followed by count for longer lists
            $first_names = array_slice($tag_names, 0, 2);
            $product_title .= ' - Names: ' . implode(', ', $first_names) . 
                              '... (' . $name_count . ' total)';
        }
    }
    
    // Add blank information if any
    if ($add_blanks > 0) {
        $product_title .= ' - Additional Blanks: ' . $add_blanks;
    }
    
    // Add sets information if more than 1
    if ($num_sets > 1) {
        $product_title .= ' - Sets: ' . $num_sets;
    }
    
    // Check if product already exists
    $product_id = wc_get_product_id_by_sku($sku);
    
    if ($product_id) {
        // Product exists, update title and add it to the cart
        $product = wc_get_product($product_id);
        if ($product) {
            $product->set_name($product_title);
            $product->save();
            
            // Ensure we use an integer product ID
            WC()->cart->add_to_cart((int)$product_id, $total_loops);
        } else {
            error_log("Failed to load product with ID: $product_id");
            // Handle error - maybe create a new product instead
        }
    } else {
        // Product does not exist, create it with the enhanced title
        $product = new WC_Product_Simple();
        $product->set_name($product_title);
        $product->set_regular_price($price);
        $product->set_sku($sku);
        $product->set_stock_quantity($total_loops);
        $product->set_catalog_visibility('hidden');
        
        if ($clips == 'Double') {
            $product->set_weight(0.025);
        } else {
            $product->set_weight(0.020);
        }
        
        $product->set_length(4);
        $product->set_width(1);
        $product->set_height(0.25);
        $product->set_image_id($image_id);
        
        // Save the product
        $new_product_id = $product->save();
        
        // Verify we have a valid product ID
        if ($new_product_id && is_numeric($new_product_id) && $new_product_id > 0) {
            // Cast to int for safety
            WC()->cart->add_to_cart((int)$new_product_id, $total_loops);
        } else {
            error_log("Failed to create new product with SKU: $sku");
            // Handle the error - perhaps show a message to the user
        }
    }
    
    // Get sublimation product IDs
    $sublimation_product_1 = isset($clloi_settings['clloi_sublimation_product_1']) ? $clloi_settings['clloi_sublimation_product_1'] : 49143;
    $sublimation_product_2 = isset($clloi_settings['clloi_sublimation_product_2']) ? $clloi_settings['clloi_sublimation_product_2'] : 49133;
    $sublimation_product_3 = isset($clloi_settings['clloi_sublimation_product_3']) ? $clloi_settings['clloi_sublimation_product_3'] : 49132;
    
    // RADICAL NEW APPROACH: Temporarily disable all hooks
    
    // 1. Save the current hooks
    global $wp_filter;
    $original_filters = $wp_filter;
    
    // 2. Create a new empty filter array (effectively disabling all hooks)
    $wp_filter = array();
    
    // 3. Count all loops in cart (including what we just added)
    $all_loops = 0;
    $setup_fee_exists = false;
    $setup_fee_cart_key = null;
    $per_loop_fee_cart_items = array();
    
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $product = $cart_item['data'];
        
        // Count all custom loops
        if ($product->get_sku() && strpos($product->get_sku(), 'CL-') === 0) {
            $all_loops += $cart_item['quantity'];
        }
        
        // Track sublimation fees
        if ($product->get_sku() === 'Sublimation') {
            // Check if it's the setup fee
            if ($product->get_name() === 'Sublimation Tags - Digital Set Up Fee') {
                $setup_fee_exists = true;
                $setup_fee_cart_key = $cart_item_key;
            } else {
                // Only track per-loop fees for removal
                $per_loop_fee_cart_items[] = $cart_item_key;
            }
        }
    }
    
    // 4. Only remove per-loop sublimation fee items from cart
    foreach ($per_loop_fee_cart_items as $cart_item_key) {
        // Directly remove from cart contents
        unset(WC()->cart->cart_contents[$cart_item_key]);
    }
    
    // 5. Add the correct per-loop sublimation fee
    $per_loop_product_id = ($all_loops < 24) ? $sublimation_product_1 : $sublimation_product_2;
    
    // Convert to integer
    $per_loop_product_id = (int)$per_loop_product_id;
    
    // Verify the product exists
    $product_data = wc_get_product($per_loop_product_id);
    
    if ($product_data) {
        // Add directly to cart contents
        $cart_item_key = WC()->cart->generate_cart_id($per_loop_product_id);
        
        WC()->cart->cart_contents[$cart_item_key] = array(
            'key'          => $cart_item_key,
            'product_id'   => $per_loop_product_id,
            'variation_id' => 0,
            'variation'    => array(),
            'quantity'     => $all_loops,
            'data'         => $product_data,
            'data_hash'    => wc_get_cart_item_data_hash($product_data),
        );
    } else {
        error_log("Failed to get sublimation product with ID: $per_loop_product_id");
        // Handle error - sublimation product doesn't exist
    }
    
    // 6. Add setup fee if it wasn't already in the cart
    if (!$setup_fee_exists) {
        // Convert to integer
        $setup_product_id = (int)$sublimation_product_3;
        
        // Verify the product exists
        $product_data = wc_get_product($setup_product_id);
        
        if ($product_data) {
            $cart_item_key = WC()->cart->generate_cart_id($setup_product_id);
            
            WC()->cart->cart_contents[$cart_item_key] = array(
                'key'          => $cart_item_key,
                'product_id'   => $setup_product_id,
                'variation_id' => 0,
                'variation'    => array(),
                'quantity'     => 1,
                'data'         => $product_data,
                'data_hash'    => wc_get_cart_item_data_hash($product_data),
            );
        } else {
            error_log("Failed to get setup fee product with ID: $setup_product_id");
            // Handle error - setup fee product doesn't exist
        }
    }
    
    // 7. Save the updated cart
    WC()->cart->set_session();
    
    // 8. Restore the original hooks
    $wp_filter = $original_filters;
    
    // 9. Set a flag to prevent our protection from interfering during page load
    WC()->session->set('cllf_skip_protection_once', true);
    
    // Return cart URL
    return wc_get_cart_url();
}

// Save form data to order meta after checkout
add_action('woocommerce_checkout_update_order_meta', 'cllf_save_form_data_to_order');

function cllf_save_form_data_to_order($order_id) {
    // Get session data
    $loop_color = WC()->session->get('cllf_loop_color');
    $sock_clips = WC()->session->get('cllf_sock_clips');
    $has_logo = WC()->session->get('cllf_has_logo');
    $logo_url = WC()->session->get('cllf_logo_url');
    $custom_font_url = WC()->session->get('cllf_custom_font_url');
    $custom_font_name = WC()->session->get('cllf_custom_font_name');
    $sport_word = WC()->session->get('cllf_sport_word');
    $tag_info_type = WC()->session->get('cllf_tag_info_type');
    $tag_numbers = WC()->session->get('cllf_tag_numbers');
    $tag_names = WC()->session->get('cllf_tag_names');
    $add_blanks = WC()->session->get('cllf_add_blanks');
    $num_sets = WC()->session->get('cllf_num_sets');
    $total_loops = WC()->session->get('cllf_total_loops');
    $text_color = WC()->session->get('cllf_text_color');
    
    // Save to order meta
    if ($loop_color) {
        update_post_meta($order_id, '_cllf_loop_color', sanitize_text_field($loop_color));
    }
    
    if ($sock_clips) {
        update_post_meta($order_id, '_cllf_sock_clips', sanitize_text_field($sock_clips));
    }
    
    if ($has_logo) {
        update_post_meta($order_id, '_cllf_has_logo', sanitize_text_field($has_logo));
    }
    
    if ($logo_url) {
        update_post_meta($order_id, '_cllf_logo_url', sanitize_text_field($logo_url));
    }
    
    if ($font_choice = WC()->session->get('cllf_font_choice')) {
        update_post_meta($order_id, '_cllf_font_choice', sanitize_text_field($font_choice));
    }
    
    if ($custom_font_url) {
        update_post_meta($order_id, '_cllf_custom_font_url', sanitize_text_field($custom_font_url));
        update_post_meta($order_id, '_cllf_custom_font_name', sanitize_text_field($custom_font_name));
    }
    
    if ($sport_word) {
        update_post_meta($order_id, '_cllf_sport_word', sanitize_text_field($sport_word));
    }
    
    if ($tag_info_type) {
        update_post_meta($order_id, '_cllf_tag_info_type', sanitize_text_field($tag_info_type));
    }
    
    if ($tag_numbers) {
        update_post_meta($order_id, '_cllf_tag_numbers', $tag_numbers);
    }
    
    if ($tag_names) {
        update_post_meta($order_id, '_cllf_tag_names', $tag_names);
    }
    
    if ($text_color) {
        update_post_meta($order_id, '_cllf_text_color', sanitize_text_field($text_color));
    }
    
    if ($add_blanks) {
        update_post_meta($order_id, '_cllf_add_blanks', intval($add_blanks));
    }
    
    if ($num_sets) {
        update_post_meta($order_id, '_cllf_num_sets', intval($num_sets));
    }
    
    if ($total_loops) {
        update_post_meta($order_id, '_cllf_total_loops', intval($total_loops));
    }
    
    if ($order_notes = WC()->session->get('cllf_order_notes')) {
        update_post_meta($order_id, '_cllf_order_notes', sanitize_textarea_field($order_notes));
    }
    
    // Clear session data
    WC()->session->__unset('cllf_loop_color');
    WC()->session->__unset('cllf_sock_clips');
    WC()->session->__unset('cllf_has_logo');
    WC()->session->__unset('cllf_logo_url');
    WC()->session->__unset('cllf_custom_font_url');
    WC()->session->__unset('cllf_custom_font_name');
    WC()->session->__unset('cllf_font_choice');
    WC()->session->__unset('cllf_sport_word');
    WC()->session->__unset('cllf_tag_info_type');
    WC()->session->__unset('cllf_tag_numbers');
    WC()->session->__unset('cllf_tag_names');
    WC()->session->__unset('cllf_add_blanks');
    WC()->session->__unset('cllf_num_sets');
    WC()->session->__unset('cllf_total_loops');
    WC()->session->__unset('cllf_text_color');
    WC()->session->__unset('cllf_order_notes');
}

// Display custom form data in admin order page
add_action('woocommerce_admin_order_data_after_order_details', 'cllf_display_form_data_in_admin');

function cllf_display_form_data_in_admin($order) {
    $order_id = $order->get_id();
    
    // Check if this order was placed using our form
    if (get_post_meta($order_id, '_cllf_loop_color', true)) {
        ?>
        <div class="order_data_column">
            <h4><?php _e('Custom Laundry Loops Form Data'); ?></h4>
            <p>
                <strong><?php _e('Loop Color'); ?>:</strong>
                <?php echo get_post_meta($order_id, '_cllf_loop_color', true); ?>
            </p>
            <p>
                <strong><?php _e('Sock Clips'); ?>:</strong>
                <?php echo get_post_meta($order_id, '_cllf_sock_clips', true); ?>
            </p>
            <p>
                <strong><?php _e('Has Logo'); ?>:</strong>
                <?php echo get_post_meta($order_id, '_cllf_has_logo', true); ?>
            </p>
            <?php if (get_post_meta($order_id, '_cllf_has_logo', true) === 'Yes' && get_post_meta($order_id, '_cllf_logo_url', true)) : ?>
                <p>
                    <strong><?php _e('Logo File'); ?>:</strong>
                    <a href="<?php echo esc_url(get_post_meta($order_id, '_cllf_logo_url', true)); ?>" target="_blank">
                        <?php _e('View Logo'); ?>
                    </a>
                </p>
            <?php endif; ?>
            <?php if ($font_choice = get_post_meta($order_id, '_cllf_font_choice', true)) : ?>
                <p>
                    <strong><?php _e('Font Choice'); ?>:</strong>
                    <?php
                    switch ($font_choice) {
                        case 'default':
                            echo 'Use Default Font(s)';
                            break;
                        case 'previous':
                            echo 'Use Font(s) Previously Provided';
                            break;
                        case 'new':
                            echo 'Uploaded New Font';
                            break;
                        default:
                            echo esc_html($font_choice);
                    }
                    ?>
                </p>
            <?php else : ?>
                <p>
                    <strong><?php _e('Font Choice'); ?>:</strong>
                    Use Default Font(s)
                </p>
            <?php endif; ?>
            <?php if ($custom_font_url = get_post_meta($order_id, '_cllf_custom_font_url', true)) : ?>
                <p>
                    <strong><?php _e('Custom Font'); ?>:</strong>
                    <a href="<?php echo esc_url($custom_font_url); ?>" target="_blank">
                        <?php echo esc_html(get_post_meta($order_id, '_cllf_custom_font_name', true)); ?>
                    </a>
                </p>
            <?php else : ?>
                <p>
                    <strong><?php _e('Custom Font'); ?>:</strong>
                    None (using default fonts)
                </p>
            <?php endif; ?>
            <?php if (get_post_meta($order_id, '_cllf_sport_word', true)) : ?>
                <p>
                    <strong><?php _e('Sport/Word on Strap'); ?>:</strong>
                    <?php echo get_post_meta($order_id, '_cllf_sport_word', true); ?>
                </p>
            <?php endif; ?>
            <p>
                <strong><?php _e('Tag Information Type'); ?>:</strong>
                <?php echo get_post_meta($order_id, '_cllf_tag_info_type', true); ?>
            </p>
            <?php if (get_post_meta($order_id, '_cllf_tag_info_type', true) === 'Numbers') : ?>
                <p>
                    <strong><?php _e('Selected Numbers'); ?>:</strong>
                    <?php 
                    $numbers = get_post_meta($order_id, '_cllf_tag_numbers', true);
                    echo implode(', ', $numbers);
                    ?>
                </p>
            <?php else : ?>
                <p>
                    <strong><?php _e('Names'); ?>:</strong>
                    <?php 
                    $names = get_post_meta($order_id, '_cllf_tag_names', true);
                    echo implode(', ', $names);
                    ?>
                </p>
            <?php endif; ?>
            <?php if ($text_color = get_post_meta($order_id, '_cllf_text_color', true)) : ?>
                <p>
                    <strong><?php _e('Text Color'); ?>:</strong>
                    <span style="display: inline-block; width: 20px; height: 20px; background-color: <?php echo esc_attr($text_color); ?>; vertical-align: middle; border: 1px solid #ddd; margin-right: 5px;"></span>
                    <?php echo esc_html($text_color); ?>
                </p>
            <?php endif; ?>
            <p>
                <strong><?php _e('Additional Blanks'); ?>:</strong>
                <?php echo get_post_meta($order_id, '_cllf_add_blanks', true); ?>
            </p>
            <p>
                <strong><?php _e('Number of Sets'); ?>:</strong>
                <?php echo get_post_meta($order_id, '_cllf_num_sets', true); ?>
            </p>
            <p>
                <strong><?php _e('Total Loops'); ?>:</strong>
                <?php echo get_post_meta($order_id, '_cllf_total_loops', true); ?>
            </p>
            <?php if ($order_notes = get_post_meta($order_id, '_cllf_order_notes', true)) : ?>
            <p>
                <strong><?php _e('Order Notes'); ?>:</strong>
                <?php echo nl2br(esc_html($order_notes)); ?>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }
}

// Send admin notification email when order is placed
add_action('woocommerce_thankyou', 'cllf_send_admin_notification', 10, 1);

function cllf_send_admin_notification($order_id) {
    $order = wc_get_order($order_id);
    
    // Check if this order was placed using our form
    if (get_post_meta($order_id, '_cllf_loop_color', true)) {
        $loop_color = get_post_meta($order_id, '_cllf_loop_color', true);
        $sock_clips = get_post_meta($order_id, '_cllf_sock_clips', true);
        $has_logo = get_post_meta($order_id, '_cllf_has_logo', true);
        $logo_url = get_post_meta($order_id, '_cllf_logo_url', true);
        $sport_word = get_post_meta($order_id, '_cllf_sport_word', true);
        $tag_info_type = get_post_meta($order_id, '_cllf_tag_info_type', true);
        $tag_numbers = get_post_meta($order_id, '_cllf_tag_numbers', true);
        $tag_names = get_post_meta($order_id, '_cllf_tag_names', true);
        $add_blanks = get_post_meta($order_id, '_cllf_add_blanks', true);
        $num_sets = get_post_meta($order_id, '_cllf_num_sets', true);
        $total_loops = get_post_meta($order_id, '_cllf_total_loops', true);
        $text_color = get_post_meta($order_id, '_cllf_text_color', true);
        $custom_font_url = get_post_meta($order_id, '_cllf_custom_font_url', true);
        $custom_font_name = get_post_meta($order_id, '_cllf_custom_font_name', true);
        $font_choice = get_post_meta($order_id, '_cllf_font_choice', true);
        $font_choice_text = 'Default Fonts';
        
        $headers[] = "From: Texon Athletic Towel <sales@texontowel.com>" . "\r\n";
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $to = array('ryan@texontowel.com', 'stephanie@texontowel.com', 'jen@texontowel.com', 'wmk@texontowel.com', 'jessica@texontowel.com');
        $subject = 'New Custom Loop Order - #' . $order_id;
        
        $message = "<p>A new Custom Loop Order has been placed on TexonTowel.com!</p>
        <p>Order #" . $order_id . " was placed by " . $order->get_formatted_billing_full_name() . " from " . $order->get_billing_company() . "</p>
        <p>This order will be shipping to:<br>" . $order->get_formatted_shipping_address() . "</p>
        <p>You may view this order here: " . $order->get_edit_order_url() . "</p>
        <p>Custom Order Details:</p>
        <ul>
            <li><strong>Loop Color:</strong> " . $loop_color . "</li>
            <li><strong>Sock Clips:</strong> " . $sock_clips . "</li>
            <li><strong>Has Logo:</strong> " . $has_logo . "</li>";
        
        if ($has_logo === 'Yes' && $logo_url) {
            $message .= "<li><strong>Logo File:</strong> <a href='" . esc_url($logo_url) . "'>View Logo</a></li>";
        }
        
        if ($sport_word) {
            $message .= "<li><strong>Sport/Word on Strap:</strong> " . $sport_word . "</li>";
        }
        
        $message .= "<li><strong>Tag Information Type:</strong> " . $tag_info_type . "</li>";
        
        if ($tag_info_type === 'Numbers') {
            $message .= "<li><strong>Selected Numbers:</strong> " . implode(', ', $tag_numbers) . "</li>";
        } else {
            $message .= "<li><strong>Names:</strong> " . implode(', ', $tag_names) . "</li>";
        }
        
        $message .= "<li><strong>Additional Blanks:</strong> " . $add_blanks . "</li>
            <li><strong>Number of Sets:</strong> " . $num_sets . "</li>
            <li><strong>Total Loops:</strong> " . $total_loops . "</li>";
            
        // Add text color information
        if ($text_color) {
            $message .= "<li><strong>Text Color:</strong> " . $text_color . " <span style='display: inline-block; width: 20px; height: 20px; background-color: " . $text_color . "; vertical-align: middle; border: 1px solid #ddd;'></span></li>";
        }
        
        if ($font_choice) {
            switch ($font_choice) {
                case 'default':
                    $font_choice_text = 'Use Default Font(s)';
                    break;
                case 'previous':
                    $font_choice_text = 'Use Font(s) Previously Provided';
                    break;
                case 'new':
                    $font_choice_text = 'Uploaded New Font';
                    break;
            }
        }
        
        $message .= "<li><strong>Font Choice:</strong> " . $font_choice_text . "</li>";
        
        // If they uploaded a new font and we have the URL
        if ($font_choice === 'new' && $custom_font_url) {
            $message .= "<li><strong>Custom Font:</strong> <a href='" . esc_url($custom_font_url) . "'>" . esc_html($custom_font_name) . "</a></li>";
        }
            
        if ($order_notes = get_post_meta($order_id, '_cllf_order_notes', true)) {
            $message .= "<li><strong>Order Notes:</strong> " . nl2br(esc_html($order_notes)) . "</li>";
        }
        
        $message .= "</ul>";
        
        if (is_page(7320)) {
            wp_mail($to, $subject, $message, $headers);
        }
    }
}

/**
 * Cart Protection for Sublimation Fees
 * Prevents customers from removing sublimation fees while custom loops are in the cart
 */

// Add this at the top of your protection functions
 function cllf_should_skip_protection() {
     // Check if we're in our specific AJAX action
     if (
         defined('DOING_AJAX') && 
         DOING_AJAX && 
         isset($_POST['action']) && 
         $_POST['action'] === 'cllf_update_sublimation_fees'
     ) {
         return true;
     }
     
     // Also check the session flag as before
     if (!function_exists('WC') || WC()->session === null) {
         return false;
     }
     
     if (WC()->session->get('cllf_skip_protection_once', false)) {
         // Clear the flag so it only applies once
         WC()->session->set('cllf_skip_protection_once', false);
         return true;
     }
     
     return false;
 }

// Check for custom loop products in the cart
 function cllf_has_custom_loops_in_cart() {
     if (!function_exists('WC') || WC()->cart === null) {
         return false;
     }
 
     foreach (WC()->cart->get_cart() as $cart_item) {
         $product = $cart_item['data'];
         if (is_a($product, 'WC_Product') && $product->get_sku() && strpos($product->get_sku(), 'CL-') === 0) {
             return true;
         }
     }
     
     return false;
 }

// Check if a product is a sublimation fee product
 function cllf_is_sublimation_fee($product_id) {
     $product = wc_get_product($product_id);
     
     if (!$product) {
         return false;
     }
     
     // Check by product title to distinguish between different sublimation products
     $product_title = $product->get_name();
     return ($product->get_sku() === 'Sublimation' || 
            $product_title === 'Sublimation Tags (ea)' || 
            $product_title === 'Sublimation Tags - Digital Set Up Fee');
 }
 
 // More specific check for per-loop sublimation fee
 function cllf_is_per_loop_sublimation_fee($product_id) {
     $product = wc_get_product($product_id);
     
     if (!$product) {
         return false;
     }
     
     $product_title = $product->get_name();
     return $product_title === 'Sublimation Tags (ea)';
 }
 
 // More specific check for setup fee
 function cllf_is_setup_sublimation_fee($product_id) {
     $product = wc_get_product($product_id);
     
     if (!$product) {
         return false;
     }
     
     $product_title = $product->get_name();
     return $product_title === 'Sublimation Tags - Digital Set Up Fee';
 }
 
 // Check if the current operation is a programmatic update
 function cllf_is_programmatic_update() {
     if (!function_exists('WC') || WC()->session === null) {
         return false;
     }
     
     return WC()->session->get('cllf_programmatic_update', false);
 }

// Prevent removal of sublimation fees when custom loops are in cart
 add_filter('woocommerce_cart_item_remove_link', 'cllf_filter_cart_item_remove_link', 10, 2);
 function cllf_filter_cart_item_remove_link($remove_link, $cart_item_key) {
     if (cllf_should_skip_protection()) {
         return $remove_link;
     }
     
     if (!function_exists('WC') || WC()->cart === null) {
         return $remove_link;
     }
     
     $cart_item = WC()->cart->get_cart_item($cart_item_key);
     if (!$cart_item) {
         return $remove_link;
     }
     
     $product_id = $cart_item['product_id'];
     
     // If this is a sublimation fee and we have custom loops in the cart
     if (cllf_is_sublimation_fee($product_id) && cllf_has_custom_loops_in_cart()) {
         // Replace the removal link with a message
         return '<span class="sublimation-fee-locked" title="This fee cannot be removed while custom loops are in your cart">🔒</span>';
     }
     
     return $remove_link;
 }
 
 // Prevent AJAX removal of sublimation fees when custom loops are in cart
 add_action('wp_ajax_woocommerce_remove_from_cart', 'cllf_restrict_sublimation_fee_removal', 5); 
 add_action('wp_ajax_nopriv_woocommerce_remove_from_cart', 'cllf_restrict_sublimation_fee_removal', 5);
 function cllf_restrict_sublimation_fee_removal() {
     if (cllf_should_skip_protection()) {
         return $remove_link;
     }
     
     if (!isset($_POST['cart_item_key'])) {
         return;
     }
     
     if (!function_exists('WC') || WC()->cart === null) {
         return;
     }
     
     $cart_item_key = sanitize_text_field($_POST['cart_item_key']);
     $cart_item = WC()->cart->get_cart_item($cart_item_key);
     
     if (!$cart_item) {
         return;
     }
     
     $product_id = $cart_item['product_id'];
     
     // If this is a sublimation fee and we have custom loops in the cart
     if (cllf_is_sublimation_fee($product_id) && cllf_has_custom_loops_in_cart()) {
         // Send error response and exit
         wp_send_json_error(array(
             'error' => true,
             'message' => 'This fee cannot be removed while custom loops are in your cart.'
         ));
         exit;
     }
 }

// Prevent quantity changes for sublimation fees when custom loops are in cart
add_filter('woocommerce_cart_item_quantity', 'cllf_filter_cart_item_quantity', 10, 3);
function cllf_filter_cart_item_quantity($product_quantity, $cart_item_key, $cart_item) {
 if (cllf_should_skip_protection()) {
     return $remove_link;
 }
 
 if (!function_exists('WC') || WC()->cart === null) {
     return $product_quantity;
 }
 
 $product_id = $cart_item['product_id'];
 
 // If this is a sublimation fee and we have custom loops in the cart
 if (cllf_is_sublimation_fee($product_id) && cllf_has_custom_loops_in_cart()) {
     // Get the current quantity
     $current_qty = $cart_item['quantity'];
     
     // Replace the quantity input with plain text showing the quantity
     return '<span class="sublimation-fee-qty">' . $current_qty . '</span>' .
            '<input type="hidden" name="cart[' . $cart_item_key . '][qty]" value="' . $current_qty . '" />';
 }
 
 return $product_quantity;
}

// Prevent quantity updates for sublimation fees through form submission
add_action('woocommerce_before_cart_item_quantity_zero', 'cllf_prevent_fee_quantity_zero', 10, 2);
function cllf_prevent_fee_quantity_zero($cart_item_key, $cart) {
 if (cllf_should_skip_protection()) {
     return $remove_link;
 }
 
 $cart_item = $cart->get_cart_item($cart_item_key);
 
 if (!$cart_item) {
     return;
 }
 
 $product_id = $cart_item['product_id'];
 
 // If this is a sublimation fee and we have custom loops in the cart
 if (cllf_is_sublimation_fee($product_id) && cllf_has_custom_loops_in_cart()) {
     // Restore the original quantity and show error message
     $cart->cart_contents[$cart_item_key]['quantity'] = $cart_item['quantity'];
     
     // Add error notice
     wc_add_notice('Sublimation fees cannot be removed while custom loops are in your cart.', 'error');
     
     // Prevent further processing of this item
     add_filter('woocommerce_update_cart_action_cart_updated', '__return_true');
 }
}

// Also protect against direct cart update requests
add_action('woocommerce_before_calculate_totals', 'cllf_protect_sublimation_fee_quantities', 10, 1);
function cllf_protect_sublimation_fee_quantities($cart) {
 if (cllf_should_skip_protection()) {
     return $remove_link;
 }
 
 if (!function_exists('WC') || !cllf_has_custom_loops_in_cart()) {
     return;
 }
 
 // Only run once
 if (did_action('woocommerce_before_calculate_totals') > 1) {
     return;
 }
 
 // Get original quantities from session if available
 $original_quantities = WC()->session->get('sublimation_fee_quantities', []);
 $quantities_updated = false;
 
 foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
     $product_id = $cart_item['product_id'];
     
     if (cllf_is_sublimation_fee($product_id)) {
         // Store the original quantity if not already stored
         if (!isset($original_quantities[$cart_item_key])) {
             $original_quantities[$cart_item_key] = $cart_item['quantity'];
             $quantities_updated = true;
         }
         
         // If quantity was changed to zero or different from original, restore it
         if ($cart_item['quantity'] === 0 || $cart_item['quantity'] !== $original_quantities[$cart_item_key]) {
             $cart->cart_contents[$cart_item_key]['quantity'] = $original_quantities[$cart_item_key];
             
             // Add an error notice if this was an attempt to change quantity
             if (!defined('DOING_AJAX') || !DOING_AJAX) {
                 wc_add_notice('Sublimation fee quantities cannot be modified while custom loops are in your cart.', 'error');
             }
         }
     }
 }
 
 // Save updated quantities
 if ($quantities_updated) {
     WC()->session->set('sublimation_fee_quantities', $original_quantities);
 }
}

// Update styles to include the quantity display
add_action('wp_head', 'cllf_add_locked_fee_styles');
function cllf_add_locked_fee_styles() {
    if (!is_cart() && !is_checkout()) {
        return;
    }
    
    ?>
    <style>
        .sublimation-fee-locked {
            font-size: 18px;
            color: #777;
            cursor: not-allowed;
        }
        
        .sublimation-fee-qty {
            font-weight: bold;
            padding: 0.5em;
            background-color: #f8f8f8;
            border-radius: 3px;
            border: 1px solid #ddd;
            display: inline-block;
            min-width: 3em;
            text-align: center;
        }
        
        /* Style for error message displayed when attempting to remove via AJAX */
        .woocommerce-error-sublimation {
            padding: 1em 2em 1em 3.5em;
            margin: 0 0 2em;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            position: relative;
        }
    </style>
    <?php
}

// Clear stored quantities when all loops are removed
add_action('woocommerce_cart_emptied', 'cllf_clear_sublimation_fee_quantities');
function cllf_clear_sublimation_fee_quantities() {
    if (function_exists('WC') && WC()->session !== null) {
        WC()->session->set('sublimation_fee_quantities', []);
    }
}

// Add JavaScript to handle AJAX removal attempts and show error message
add_action('wp_footer', 'cllf_add_cart_protection_script');
function cllf_add_cart_protection_script() {
    if (!is_cart() && !is_checkout()) {
        return;
    }
    
    ?>
    <script>
    jQuery(document).ready(function($) {
        $(document.body).on('removed_from_cart', function(event, fragments, cart_hash, $button) {
            if (fragments && fragments.hasOwnProperty('error') && fragments.error) {
                // Show error message
                if ($('.woocommerce-error-sublimation').length === 0) {
                    $('<div class="woocommerce-error-sublimation">' + fragments.message + '</div>')
                        .insertBefore('.woocommerce-cart-form')
                        .delay(4000)
                        .fadeOut(400, function() {
                            $(this).remove();
                        });
                }
                
                // Refresh the cart to restore the item
                $('body').trigger('wc_update_cart');
            }
        });
    });
    </script>
    <?php
}

// Remove our problematic hooked function
remove_action('woocommerce_cart_item_removed', 'cllf_maybe_remove_sublimation_fees');
remove_action('woocommerce_remove_cart_item', 'cllf_maybe_remove_sublimation_fees');

// Add an AJAX endpoint to update sublimation fees
add_action('wp_ajax_cllf_update_sublimation_fees', 'cllf_update_sublimation_fees_ajax');
add_action('wp_ajax_nopriv_cllf_update_sublimation_fees', 'cllf_update_sublimation_fees_ajax');

function cllf_update_sublimation_fees_ajax() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cllf-cart-nonce')) {
        wp_send_json_error('Security check failed');
        exit;
    }
    
    // Log that we're starting
    error_log('AJAX: Starting sublimation fee update');
    
    // Count all custom loops in cart
    $remaining_loops = 0;
    $per_loop_fee_key = null;
    $per_loop_fee_product_id = null;
    
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $product = $cart_item['data'];
        
        // Count custom loops
        if (is_a($product, 'WC_Product') && $product->get_sku() && 
            strpos($product->get_sku(), 'CL-') === 0) {
            $remaining_loops += $cart_item['quantity'];
            error_log('AJAX: Found custom loop product: ' . $product->get_sku() . ', Quantity: ' . $cart_item['quantity']);
        }
        
        // Find the per-loop fee item
        if (is_a($product, 'WC_Product') && $product->get_name() === 'Sublimation Tags (ea)') {
            $per_loop_fee_key = $cart_item_key;
            $per_loop_fee_product_id = $cart_item['product_id'];
            error_log('AJAX: Found per-loop fee product: ' . $product->get_name() . ', Current qty: ' . $cart_item['quantity']);
        }
    }
    
    // Get settings
    $clloi_settings = get_option('clloi_settings');
    $sublimation_product_1 = isset($clloi_settings['clloi_sublimation_product_1']) ? 
                             $clloi_settings['clloi_sublimation_product_1'] : 49143; // < 24 loops
    $sublimation_product_2 = isset($clloi_settings['clloi_sublimation_product_2']) ? 
                             $clloi_settings['clloi_sublimation_product_2'] : 49133; // ≥ 24 loops
    $sublimation_product_3 = isset($clloi_settings['clloi_sublimation_product_3']) ? 
                             $clloi_settings['clloi_sublimation_product_3'] : 49132; // Setup fee
    
    // Force protection bypass
    WC()->session->set('cllf_skip_protection_once', true);
    
    // SUPER DIRECT APPROACH - directly manipulate cart contents
    
    if ($remaining_loops === 0) {
        error_log('AJAX: No loops remain - removing all sublimation fees');
        
        // If no loops remain, remove all sublimation fees
        $updated_cart = array();
        
        foreach (WC()->cart->cart_contents as $key => $item) {
            $product = $item['data'];
            
            // Skip sublimation products
            if (is_a($product, 'WC_Product') && $product->get_sku() === 'Sublimation') {
                error_log('AJAX: Removing sublimation product: ' . $product->get_name());
                continue;
            }
            
            // Keep all other products
            $updated_cart[$key] = $item;
        }
        
        // Replace cart contents
        WC()->cart->cart_contents = $updated_cart;
        WC()->cart->set_session();
        WC()->cart->calculate_totals();
        
        error_log('AJAX: Cart updated - all sublimation fees removed');
        wp_send_json_success(array(
            'message' => 'All sublimation fees removed',
            'remaining_loops' => 0
        ));
    } else {
        error_log('AJAX: Updating sublimation fees for ' . $remaining_loops . ' loops');
        
        // Determine which product to use
        $correct_product_id = ($remaining_loops < 24) ? $sublimation_product_1 : $sublimation_product_2;
        error_log('AJAX: Correct product ID should be: ' . $correct_product_id);
        
        if ($per_loop_fee_key) {
            // We found an existing fee
            if ($per_loop_fee_product_id != $correct_product_id) {
                error_log('AJAX: Need to switch from product ' . $per_loop_fee_product_id . ' to ' . $correct_product_id);
                
                // Product switch needed - remove old one
                unset(WC()->cart->cart_contents[$per_loop_fee_key]);
                
                // Add new one directly
                $product_data = wc_get_product($correct_product_id);
                $cart_id = WC()->cart->generate_cart_id($correct_product_id);
                
                WC()->cart->cart_contents[$cart_id] = array(
                    'key'          => $cart_id,
                    'product_id'   => $correct_product_id,
                    'variation_id' => 0,
                    'variation'    => array(),
                    'quantity'     => $remaining_loops,
                    'data'         => $product_data,
                    'data_hash'    => wc_get_cart_item_data_hash($product_data),
                );
                error_log('AJAX: Added new per-loop product with quantity ' . $remaining_loops);
            } else {
                // Just update quantity directly
                error_log('AJAX: Updating existing per-loop fee quantity to ' . $remaining_loops);
                WC()->cart->cart_contents[$per_loop_fee_key]['quantity'] = $remaining_loops;
            }
        } else {
            // No per-loop fee found, add it
            error_log('AJAX: Adding missing per-loop fee product with quantity ' . $remaining_loops);
            $product_data = wc_get_product($correct_product_id);
            $cart_id = WC()->cart->generate_cart_id($correct_product_id);
            
            WC()->cart->cart_contents[$cart_id] = array(
                'key'          => $cart_id,
                'product_id'   => $correct_product_id,
                'variation_id' => 0,
                'variation'    => array(),
                'quantity'     => $remaining_loops,
                'data'         => $product_data,
                'data_hash'    => wc_get_cart_item_data_hash($product_data),
            );
        }
        
        // Make sure we have the setup fee
        $has_setup_fee = false;
        foreach (WC()->cart->cart_contents as $item) {
            $product = $item['data'];
            if (is_a($product, 'WC_Product') && $product->get_name() === 'Sublimation Tags - Digital Set Up Fee') {
                $has_setup_fee = true;
                break;
            }
        }
        
        if (!$has_setup_fee) {
            error_log('AJAX: Adding missing setup fee');
            $product_data = wc_get_product($sublimation_product_3);
            $cart_id = WC()->cart->generate_cart_id($sublimation_product_3);
            
            WC()->cart->cart_contents[$cart_id] = array(
                'key'          => $cart_id,
                'product_id'   => $sublimation_product_3,
                'variation_id' => 0,
                'variation'    => array(),
                'quantity'     => 1,
                'data'         => $product_data,
                'data_hash'    => wc_get_cart_item_data_hash($product_data),
            );
        }
        
        // Save and calculate
        WC()->cart->set_session();
        WC()->cart->calculate_totals();
        
        error_log('AJAX: Cart saved and totals calculated');
        wp_send_json_success(array(
            'message' => 'Sublimation fees updated',
            'remaining_loops' => $remaining_loops
        ));
    }
}

// Add JavaScript to cart page to trigger the AJAX update
add_action('wp_footer', 'cllf_add_cart_update_script');
function cllf_add_cart_update_script() {
    if (!is_cart() && !is_checkout()) {
        return;
    }
    
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Create a nonce for security
        var cllf_cart_nonce = '<?php echo wp_create_nonce('cllf-cart-nonce'); ?>';
        var updateInProgress = false;
        var lastUpdateTime = 0;
        
        // Function to update sublimation fees
        function updateSublimationFees() {
            // Prevent multiple simultaneous calls
            if (updateInProgress) {
                console.log('Update already in progress, skipping');
                return;
            }
            
            // Don't allow updates more frequently than once per second
            var now = Date.now();
            if (now - lastUpdateTime < 1000) {
                console.log('Update rate limited, skipping');
                return;
            }
            
            updateInProgress = true;
            lastUpdateTime = now;
            
            console.log('Updating sublimation fees...');
            
            $.ajax({
                type: 'POST',
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                data: {
                    action: 'cllf_update_sublimation_fees',
                    nonce: cllf_cart_nonce
                },
                success: function(response) {
                    updateInProgress = false;
                    
                    if (response.success) {
                        console.log('Sublimation fees updated successfully:', response.data);
                    } else {
                        console.error('Error updating sublimation fees:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    updateInProgress = false;
                    console.error('AJAX error:', error);
                },
                complete: function() {
                    updateInProgress = false;
                }
            });
        }
        
        // Listen for cart changes - use once per event type
        var cartRemovalHandler = function(event, fragments, cart_hash, $button) {
            console.log('Item removed from cart, scheduling fee update');
            setTimeout(updateSublimationFees, 500);
        };
        
        var cartUpdateHandler = function() {
            console.log('Cart updated, scheduling fee update');
            setTimeout(updateSublimationFees, 500);
        };
        
        $(document.body).on('removed_from_cart', cartRemovalHandler);
        $(document.body).on('updated_cart_totals', cartUpdateHandler);
    });
    </script>
    <?php
}

/**
 * Custom Payment Gateway for Custom Loops Orders
 * Allows orders to be placed without immediate payment
 */

// Register the custom payment gateway
add_filter('woocommerce_payment_gateways', 'cllf_add_custom_loop_gateway');
function cllf_add_custom_loop_gateway($gateways) {
    $gateways[] = 'CLLF_Custom_Loop_Gateway';
    return $gateways;
}

// Initialize the gateway class
add_action('plugins_loaded', 'cllf_init_custom_loop_gateway');
function cllf_init_custom_loop_gateway() {
    class CLLF_Custom_Loop_Gateway extends WC_Payment_Gateway {
        public $instructions;
        
        public function __construct() {
            $this->id = 'cllf_custom_loop_gateway';
            $this->icon = '';
            $this->has_fields = false;
            $this->method_title = 'Custom Loop Orders';
            $this->method_description = 'Special payment method for custom loop orders. Payment will be collected after order details are verified and digital proof is approved.';
            
            // Define settings
            $this->init_form_fields();
            $this->init_settings();
            
            // Get settings
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->instructions = $this->get_option('instructions');
            
            // Save settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }
        
        // Define settings fields
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'type' => 'checkbox',
                    'label' => 'Enable Custom Loop Payment Method',
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'Pay After Proof Approval',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Since your order contains custom loops, we\'ll review your order details first and send you a digital proof for approval. After your approval, we\'ll send you a final invoice for payment.',
                ),
                'instructions' => array(
                    'title' => 'Instructions',
                    'type' => 'textarea',
                    'description' => 'Instructions that will be added to the thank you page and order emails.',
                    'default' => 'Thank you for your custom loops order! We\'ll review your order details and send you a digital proof for approval. After you approve the design, we\'ll send you a final invoice for payment.',
                    'desc_tip' => true,
                ),
            );
        }
        
        // Process payment
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            
            // Mark as on-hold (we're awaiting the payment)
            $order->update_status('on-hold', __('Awaiting proof approval and payment.', 'woocommerce'));
            
            // Reduce stock levels
            wc_reduce_stock_levels($order_id);
            
            // Remove cart
            WC()->cart->empty_cart();
            
            // Add order note
            $order->add_order_note('This is a custom loop order. Payment will be collected after proof approval.');
            
            // Return thankyou redirect
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            );
        }
        
        // Add content to the thank you page
        public function thankyou_page($order_id) {
            if ($this->instructions) {
                echo wp_kses_post(wpautop(wptexturize($this->instructions)));
            }
        }
        
        // Add instructions to order emails
        public function email_instructions($order, $sent_to_admin, $plain_text = false) {
            if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method()) {
                echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
            }
        }
    }
}

// Show only the custom gateway when cart has custom loops
add_filter('woocommerce_available_payment_gateways', 'cllf_custom_loop_available_payment_gateways');
function cllf_custom_loop_available_payment_gateways($available_gateways) {
    if (!is_admin() && cllf_has_custom_loops_in_cart()) {
        // If cart has custom loops, only make our custom gateway available
        if (isset($available_gateways['cllf_custom_loop_gateway'])) {
            return array('cllf_custom_loop_gateway' => $available_gateways['cllf_custom_loop_gateway']);
        }
    }
    
    // If no custom loops or our gateway isn't available, keep standard gateways
    return $available_gateways;
}

// Add checkout notice explaining the payment process for custom loops
add_action('woocommerce_before_checkout_form', 'cllf_add_custom_loop_checkout_notice');
function cllf_add_custom_loop_checkout_notice() {
    if (cllf_has_custom_loops_in_cart()) {
        echo '<div class="woocommerce-info custom-loop-checkout-notice">
            <span class="custom-notice-icon">ℹ️</span> Your order contains custom laundry loops. Due to the custom nature of these products, we\'ll first review your order details and send you a digital proof. After you approve the design, we\'ll send you a final invoice for payment.
        </div>';
        
        // Add comprehensive styling to override WooCommerce defaults
        echo '<style>
            /* Base styling for our custom notice */
            .custom-loop-checkout-notice {
                background-color: #e6f3ff !important;
                border-left: 4px solid #17a2b8 !important;
                padding: 15px 15px 15px 50px !important;
                margin-bottom: 30px !important;
                border-radius: 4px !important;
                color: #333 !important;
                font-weight: normal !important;
                text-shadow: none !important;
                position: relative !important;
                list-style: none !important;
            }
            
            /* Fix the ::before pseudo-element */
            .woocommerce-info.custom-loop-checkout-notice::before {
                background-image: none !important;
                text-indent: 0 !important;
                content: "" !important;
                width: 0 !important;
                height: 0 !important;
                display: none !important;
            }
            
            /* Custom icon styling */
            .custom-notice-icon {
                position: absolute !important;
                left: 20px !important;
                top: 50% !important;
                transform: translateY(-50%) !important;
                font-size: 20px !important;
            }
            
            /* Fix ::after pseudo-element */
            .woocommerce-info.custom-loop-checkout-notice::after {
                content: none !important;
            }
            
            /* Remove any padding and margins that might be applied to default notices */
            .woocommerce-info.custom-loop-checkout-notice {
                padding-left: 50px !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
        </style>';
    }
}

// Add instructions to order confirmation emails
add_action('woocommerce_email_before_order_table', 'cllf_custom_loop_email_instructions', 10, 4);
function cllf_custom_loop_email_instructions($order, $sent_to_admin, $plain_text, $email) {
    // Check if the order contains custom loops
    $has_custom_loops = false;
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if ($product && $product->get_sku() && strpos($product->get_sku(), 'CL-') === 0) {
            $has_custom_loops = true;
            break;
        }
    }
    
    if ($has_custom_loops && !$sent_to_admin) {
        $instructions = 'Thank you for your custom loops order! We\'ll review your order details and send you a digital proof for approval. After you approve the design, we\'ll send you a final invoice for payment.';
        
        if ($plain_text) {
            echo esc_html($instructions) . PHP_EOL . PHP_EOL;
        } else {
            echo '<div style="margin-bottom: 40px; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #17a2b8;">';
            echo wp_kses_post(wpautop($instructions));
            echo '</div>';
        }
    }
}

// Add information to admin order page
add_action('woocommerce_admin_order_data_after_order_details', 'cllf_custom_loop_admin_order_notice');
function cllf_custom_loop_admin_order_notice($order) {
    // Check if the order contains custom loops
    $has_custom_loops = false;
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if ($product && $product->get_sku() && strpos($product->get_sku(), 'CL-') === 0) {
            $has_custom_loops = true;
            break;
        }
    }
    
    if ($has_custom_loops) {
        echo '<div class="notice notice-info inline" style="padding: 10px; margin: 15px 0;">
            <p><strong>Custom Loop Order</strong>: This order contains custom loops. Remember to create and send a digital proof for customer approval before collecting payment.</p>
        </div>';
    }
}

// Utility function to clear the cart for testing
add_action('init', 'cllf_clear_cart_endpoint');
function cllf_clear_cart_endpoint() {
    // Only process on a specific URL parameter for security
    if (isset($_GET['cllf_clear_cart']) && $_GET['cllf_clear_cart'] === 'yes') {
        if (function_exists('WC') && WC()->cart !== null) {
            WC()->cart->empty_cart();
            WC()->session->set('cllf_programmatic_update', false);
            WC()->session->set('sublimation_fee_quantities', []);
            
            // Clear all other session data related to the form
            WC()->session->__unset('cllf_loop_color');
            WC()->session->__unset('cllf_sock_clips');
            WC()->session->__unset('cllf_has_logo');
            WC()->session->__unset('cllf_logo_url');
            WC()->session->__unset('cllf_sport_word');
            WC()->session->__unset('cllf_tag_info_type');
            WC()->session->__unset('cllf_tag_numbers');
            WC()->session->__unset('cllf_tag_names');
            WC()->session->__unset('cllf_add_blanks');
            WC()->session->__unset('cllf_num_sets');
            WC()->session->__unset('cllf_total_loops');
            WC()->session->__unset('cllf_order_notes');
            
            echo 'Cart cleared successfully!';
            exit;
        }
    }
}

/**
 * GitHub Personal Access Token Integration
 * 
 * These functions handle securely storing and retrieving a GitHub token
 * for use with the Custom Laundry Loops Form plugin.
 */

/**
 * Register settings for GitHub token
 */
function cllf_register_github_settings() {
    register_setting(
        'cllf_github_settings_group',
        'cllf_github_token',
        array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        )
    );
}
add_action('admin_init', 'cllf_register_github_settings');

/**
 * Add GitHub settings submenu page
 */
function cllf_add_github_settings_page() {
    add_submenu_page(
        'options-general.php',
        'GitHub Integration Settings',
        'GitHub Settings',
        'manage_options',
        'cllf-github-settings',
        'cllf_github_settings_page'
    );
}
add_action('admin_menu', 'cllf_add_github_settings_page');

/**
 * Render GitHub settings page
 */
function cllf_github_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('cllf_github_settings_group'); ?>
            <?php do_settings_sections('cllf_github_settings_group'); ?>
            
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">GitHub Personal Access Token</th>
                    <td>
                        <input type="password" name="cllf_github_token" value="<?php echo esc_attr(get_option('cllf_github_token')); ?>" class="regular-text" />
                        <p class="description">Enter your GitHub Personal Access Token. This is stored securely in the WordPress database.</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Modify the settings saving process to encrypt the token
 */
add_filter('pre_update_option_cllf_github_token', function($value, $old_value) {
    // Only encrypt if the value has changed and is not empty
    if ($value !== $old_value && !empty($value)) {
        return cllf_encrypt_token($value);
    }
    return $value;
}, 10, 2);

/**
 * Register function to generate readme.html when visiting plugins page
 */
add_action('admin_init', 'cllf_maybe_generate_readme');

function cllf_maybe_generate_readme() {
    // Only run on the plugins page
    if (!is_admin() || !isset($_SERVER['PHP_SELF']) || basename($_SERVER['PHP_SELF']) !== 'plugins.php') {
        return;
    }
    
    // Only regenerate readme once per session to prevent overhead
    if (get_transient('cllf_readme_generated')) {
        return;
    }
    
    // Set a transient so we don't regenerate the readme too frequently
    set_transient('cllf_readme_generated', true, HOUR_IN_SECONDS);
    
    // Path to the generate-readme.php script
    $readme_generator = CLLF_PLUGIN_DIR . 'generate-readme.php';
    
    // Check if the generator script exists
    if (file_exists($readme_generator)) {
        // Define debug constant (false to disable debug output)
        if (!defined('CLLF_DEBUG')) {
            define('CLLF_DEBUG', false);
        }
        
        // Include the script which defines the functions but doesn't run
        require_once($readme_generator);
        
        // Run readme generation process but capture any output
        ob_start();
        try {
            // GitHub repository information
            $github_username = 'rynoceris';
            $github_repo = 'Texon-Custom-Loop-Form';
            $github_token = cllf_get_github_token();
            
            // Get environment versions directly from WordPress
            $env_versions = [
                'wordpress' => get_bloginfo('version'),
                'woocommerce' => defined('WC_VERSION') ? WC_VERSION : 'Unknown',
                'php' => phpversion()
            ];
            
            // Get commits and generate readme
            $commits = get_commits($github_username, $github_repo, $github_token);
            $versions = create_version_entries($commits);
            $html = generate_html($versions, $env_versions);
            
            // Save the HTML to a file
            file_put_contents(CLLF_PLUGIN_DIR . 'readme.html', $html);
           
        } catch (Exception $e) {
            // Log error but continue
            error_log('Error generating readme.html: ' . $e->getMessage());
        }
        // Discard any output
        ob_end_clean();
    }
}

/**
 * Add "View details" link to plugin page
 */
add_filter(
    'plugin_row_meta',
    function( $plugin_meta, $plugin_file, $plugin_data ) {
        // Check if this is our plugin by comparing the plugin basename
        if ( 'custom-laundry-loops-form/custom-loop-form-plugin.php' === $plugin_file ) {
            // Link to the readme.html file
            $url = plugins_url( 'readme.html', __FILE__ );

            // Add the "View details" link
            $plugin_meta[] = sprintf(
                '<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
                add_query_arg( 'TB_iframe', 'true', $url ),
                esc_attr( sprintf( __( 'More information about %s', 'custom-laundry-loops-form' ), $plugin_data['Name'] ) ),
                esc_attr( $plugin_data['Name'] ),
                __( 'View details', 'custom-laundry-loops-form' )
            );
        }
        return $plugin_meta;
    },
    10,
    3
);