<?php
/**
 * @package CLLF
 * @wordpress-plugin
 *
 * Plugin Name:          Custom Laundry Loops Form
 * Plugin URI:           https://www.texontowel.com
 * Description:          Display a custom form for ordering custom laundry loops directly on the frontend.
 * Version:              2.3.2
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

/**
 * Updated form submission handler to store multiple custom loop products
 * Replace the existing cllf_handle_form_submission function (around line 718)
 */
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
    
    // Calculate total number of loops
    $tag_count = ($tag_info_type === 'Numbers') ? count($tag_numbers) : count($tag_names);
    $total_loops = ($tag_count * $num_sets) + $add_blanks;
    
    // Create a unique identifier for this submission
    $submission_id = uniqid('cllf_', true);
    
    // Get existing submissions array or create new one
    $all_submissions = WC()->session->get('cllf_all_submissions', array());
    
    // Store this submission's data
    $submission_data = array(
        'submission_id' => $submission_id,
        'timestamp' => current_time('mysql'),
        'loop_color' => $loop_color,
        'sock_clips' => $sock_clips,
        'has_logo' => $has_logo,
        'logo_url' => $logo_url,
        'sport_word' => $sport_word,
        'tag_info_type' => $tag_info_type,
        'tag_numbers' => $tag_numbers,
        'tag_names' => $tag_names,
        'add_blanks' => $add_blanks,
        'num_sets' => $num_sets,
        'total_loops' => $total_loops,
        'order_notes' => $order_notes,
        'text_color' => $text_color,
        'font_choice' => $font_choice,
        'custom_font_url' => $custom_font_url,
        'custom_font_name' => $custom_font_name
    );
    
    // Add to submissions array
    $all_submissions[$submission_id] = $submission_data;
    
    // Save all submissions
    WC()->session->set('cllf_all_submissions', $all_submissions);
    
    // Also store in individual session keys for backward compatibility
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
    WC()->session->set('cllf_font_choice', $font_choice);
    
    if ($custom_font_url) {
        WC()->session->set('cllf_custom_font_url', $custom_font_url);
        WC()->session->set('cllf_custom_font_name', $custom_font_name);
    }
    
    // Store the submission ID in the current session for processing
    WC()->session->set('cllf_current_submission_id', $submission_id);
    
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

/**
 * Updated function to add submission ID to cart item data
 * Add this new function after cllf_process_order_and_add_to_cart
 */
add_filter('woocommerce_add_cart_item_data', 'cllf_add_submission_id_to_cart_item', 10, 3);
function cllf_add_submission_id_to_cart_item($cart_item_data, $product_id, $variation_id) {
    // Get the current submission ID
    $submission_id = WC()->session->get('cllf_current_submission_id');
    
    if ($submission_id) {
        // Check if this is a custom loop product
        $product = wc_get_product($product_id);
        if ($product && $product->get_sku() && strpos($product->get_sku(), 'CL-') === 0) {
            $cart_item_data['cllf_submission_id'] = $submission_id;
            
            // Clear the current submission ID so it doesn't get added to other products
            WC()->session->set('cllf_current_submission_id', null);
        }
    }
    
    return $cart_item_data;
}

// Save form data to order meta after checkout
add_action('woocommerce_checkout_update_order_meta', 'cllf_save_form_data_to_order');

/**
 * Updated function to save ALL submission data to order meta
 * Replace the existing cllf_save_form_data_to_order function (around line 1862)
 */
function cllf_save_form_data_to_order($order_id) {
    // Get all submissions
    $all_submissions = WC()->session->get('cllf_all_submissions', array());
    
    if (!empty($all_submissions)) {
        // Save all submissions as a single meta entry
        update_post_meta($order_id, '_cllf_all_submissions', $all_submissions);
        
        // Also save individual submission data for each cart item
        $order = wc_get_order($order_id);
        $submission_index = 1;
        
        foreach ($order->get_items() as $item_id => $item) {
            // Check if this item has a submission ID
            $submission_id = $item->get_meta('cllf_submission_id');
            
            if ($submission_id && isset($all_submissions[$submission_id])) {
                // Save the submission data to the order item
                $submission_data = $all_submissions[$submission_id];
                
                // Add submission data as item meta
                wc_add_order_item_meta($item_id, '_cllf_submission_data', $submission_data);
                wc_add_order_item_meta($item_id, '_cllf_submission_index', $submission_index);
                
                $submission_index++;
            }
        }
        
        // Clear session data
        WC()->session->set('cllf_all_submissions', array());
    }
    
    // Keep backward compatibility - save the last submission data to order meta
    $loop_color = WC()->session->get('cllf_loop_color');
    if ($loop_color) {
        update_post_meta($order_id, '_cllf_loop_color', sanitize_text_field($loop_color));
        update_post_meta($order_id, '_cllf_sock_clips', sanitize_text_field(WC()->session->get('cllf_sock_clips')));
        update_post_meta($order_id, '_cllf_has_logo', sanitize_text_field(WC()->session->get('cllf_has_logo')));
        update_post_meta($order_id, '_cllf_logo_url', sanitize_text_field(WC()->session->get('cllf_logo_url')));
        update_post_meta($order_id, '_cllf_sport_word', sanitize_text_field(WC()->session->get('cllf_sport_word')));
        update_post_meta($order_id, '_cllf_tag_info_type', sanitize_text_field(WC()->session->get('cllf_tag_info_type')));
        update_post_meta($order_id, '_cllf_tag_numbers', WC()->session->get('cllf_tag_numbers'));
        update_post_meta($order_id, '_cllf_tag_names', WC()->session->get('cllf_tag_names'));
        update_post_meta($order_id, '_cllf_text_color', sanitize_text_field(WC()->session->get('cllf_text_color')));
        update_post_meta($order_id, '_cllf_add_blanks', intval(WC()->session->get('cllf_add_blanks')));
        update_post_meta($order_id, '_cllf_num_sets', intval(WC()->session->get('cllf_num_sets')));
        update_post_meta($order_id, '_cllf_total_loops', intval(WC()->session->get('cllf_total_loops')));
        
        if ($font_choice = WC()->session->get('cllf_font_choice')) {
            update_post_meta($order_id, '_cllf_font_choice', sanitize_text_field($font_choice));
        }
        
        if ($custom_font_url = WC()->session->get('cllf_custom_font_url')) {
            update_post_meta($order_id, '_cllf_custom_font_url', sanitize_text_field($custom_font_url));
            update_post_meta($order_id, '_cllf_custom_font_name', sanitize_text_field(WC()->session->get('cllf_custom_font_name')));
        }
        
        if ($order_notes = WC()->session->get('cllf_order_notes')) {
            update_post_meta($order_id, '_cllf_order_notes', sanitize_textarea_field($order_notes));
        }
    }
    
    // Clear all individual session data
    $session_keys = array(
        'cllf_loop_color', 'cllf_sock_clips', 'cllf_has_logo', 'cllf_logo_url',
        'cllf_sport_word', 'cllf_tag_info_type', 'cllf_tag_numbers', 'cllf_tag_names',
        'cllf_add_blanks', 'cllf_num_sets', 'cllf_total_loops', 'cllf_text_color',
        'cllf_order_notes', 'cllf_custom_font_url', 'cllf_custom_font_name', 'cllf_font_choice'
    );
    
    foreach ($session_keys as $key) {
        WC()->session->__unset($key);
    }
}

/**
 * Store submission ID in order item meta
 */
add_action('woocommerce_checkout_create_order_line_item', 'cllf_save_submission_id_to_order_item', 10, 4);
function cllf_save_submission_id_to_order_item($item, $cart_item_key, $values, $order) {
    if (isset($values['cllf_submission_id'])) {
        $item->add_meta_data('cllf_submission_id', $values['cllf_submission_id'], true);
    }
}

// Display custom form data in admin order page
add_action('woocommerce_admin_order_data_after_order_details', 'cllf_display_form_data_in_admin');

/**
 * Updated admin display to show ALL custom loop products in an order
 * Replace the existing cllf_display_form_data_in_admin function
 */
function cllf_display_form_data_in_admin($order) {
    $order_id = $order->get_id();
    
    // Get all submissions data
    $all_submissions = get_post_meta($order_id, '_cllf_all_submissions', true);
    
    // If we have multiple submissions, display them all
    if (!empty($all_submissions) && is_array($all_submissions)) {
        ?>
        <div class="order_data_column" style="width: 100%;">
            <h3><?php _e('Custom Laundry Loops - All Products'); ?> (<?php echo count($all_submissions); ?> total)</h3>
            
            <?php
            $product_number = 1;
            foreach ($all_submissions as $submission_id => $submission) {
                ?>
                <div style="background-color: #f9f9f9; border: 1px solid #e0e0e0; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                    <h4 style="margin-top: 0; color: #23282d; border-bottom: 1px solid #ddd; padding-bottom: 10px;">
                        Custom Loop Product #<?php echo $product_number; ?>
                        <span style="font-size: 12px; color: #666; margin-left: 10px;">
                            (Added: <?php echo esc_html($submission['timestamp']); ?>)
                        </span>
                    </h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <p><strong>Loop Color:</strong> <?php echo esc_html($submission['loop_color']); ?></p>
                            <p><strong>Sock Clips:</strong> <?php echo esc_html($submission['sock_clips']); ?></p>
                            <p><strong>Has Logo:</strong> <?php echo esc_html($submission['has_logo']); ?></p>
                            
                            <?php if ($submission['has_logo'] === 'Yes' && !empty($submission['logo_url'])) : ?>
                                <p>
                                    <strong>Logo File:</strong>
                                    <a href="<?php echo esc_url($submission['logo_url']); ?>" target="_blank">View Logo</a>
                                </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($submission['sport_word'])) : ?>
                                <p><strong>Sport/Word on Strap:</strong> <?php echo esc_html($submission['sport_word']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($submission['text_color'])) : ?>
                                <p>
                                    <strong>Text Color:</strong>
                                    <span style="display: inline-block; width: 20px; height: 20px; background-color: <?php echo esc_attr($submission['text_color']); ?>; vertical-align: middle; border: 1px solid #ddd; margin-right: 5px;"></span>
                                    <?php echo esc_html($submission['text_color']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <p><strong>Tag Type:</strong> <?php echo esc_html($submission['tag_info_type']); ?></p>
                            
                            <?php if ($submission['tag_info_type'] === 'Numbers') : ?>
                                <p>
                                    <strong>Selected Numbers:</strong>
                                    <?php 
                                    if (is_array($submission['tag_numbers'])) {
                                        echo implode(', ', $submission['tag_numbers']);
                                    }
                                    ?>
                                </p>
                            <?php else : ?>
                                <div>
                                    <strong>Names:</strong>
                                    <?php 
                                    if (is_array($submission['tag_names']) && !empty($submission['tag_names'])) {
                                        $total_names = count($submission['tag_names']);
                                        echo ' (' . $total_names . ' total)';
                                        ?>
                                        <div style="margin-top: 10px; padding: 10px; background-color: white; border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto;">
                                            <ol style="margin: 0; padding-left: 20px;">
                                                <?php foreach ($submission['tag_names'] as $name) : ?>
                                                    <li style="padding: 2px 0;"><?php echo esc_html($name); ?></li>
                                                <?php endforeach; ?>
                                            </ol>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                            
                            <p><strong>Additional Blanks:</strong> <?php echo esc_html($submission['add_blanks']); ?></p>
                            <p><strong>Number of Sets:</strong> <?php echo esc_html($submission['num_sets']); ?></p>
                            <p><strong>Total Loops:</strong> <?php echo esc_html($submission['total_loops']); ?></p>
                        </div>
                    </div>
                    
                    <?php if (!empty($submission['font_choice'])) : ?>
                        <p>
                            <strong>Font Choice:</strong>
                            <?php
                            switch ($submission['font_choice']) {
                                case 'default':
                                    echo 'Use Default Font(s)';
                                    break;
                                case 'previous':
                                    echo 'Use Font(s) Previously Provided';
                                    break;
                                case 'new':
                                    echo 'Uploaded New Font';
                                    if (!empty($submission['custom_font_url'])) {
                                        echo ' - <a href="' . esc_url($submission['custom_font_url']) . '" target="_blank">' . 
                                             esc_html($submission['custom_font_name']) . '</a>';
                                    }
                                    break;
                            }
                            ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($submission['order_notes'])) : ?>
                        <div style="margin-top: 10px;">
                            <strong>Order Notes:</strong>
                            <div style="padding: 10px; background-color: #fff8dc; border: 1px solid #ffd700; border-radius: 4px;">
                                <?php echo nl2br(esc_html($submission['order_notes'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
                $product_number++;
            }
            ?>
        </div>
        <?php
    } else {
        // Fallback to single product display for backward compatibility
        if (get_post_meta($order_id, '_cllf_loop_color', true)) {
            // [Original single product display code here - same as before]
            ?>
            <div class="order_data_column">
                <h4><?php _e('Custom Laundry Loops Form Data'); ?></h4>
                <!-- [Rest of original display code] -->
            </div>
            <?php
        }
    }
}

// Send admin notification email when order is placed
add_action('woocommerce_thankyou', 'cllf_send_admin_notification', 10, 1);

/**
 * Updated admin email notification to include ALL custom loop products
 * Replace the existing cllf_send_admin_notification function
 */
function cllf_send_admin_notification($order_id) {
    $order = wc_get_order($order_id);
    
    // Get all submissions
    $all_submissions = get_post_meta($order_id, '_cllf_all_submissions', true);
    
    // Check if we have multiple submissions
    if (!empty($all_submissions) && is_array($all_submissions)) {
        $headers[] = "From: Texon Athletic Towel <sales@texontowel.com>" . "\r\n";
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $to = array('ryan@texontowel.com', 'stephanie@texontowel.com', 'jen@texontowel.com', 'wmk@texontowel.com', 'jessica@texontowel.com');
        $subject = 'New Custom Loop Order - #' . $order_id . ' (' . count($all_submissions) . ' custom products)';
        
        $message = "<p>A new Custom Loop Order has been placed on TexonTowel.com!</p>
        <p>Order #" . $order_id . " was placed by " . $order->get_formatted_billing_full_name() . " from " . $order->get_billing_company() . "</p>
        <p>This order will be shipping to:<br>" . $order->get_formatted_shipping_address() . "</p>
        <p>You may view this order here: " . $order->get_edit_order_url() . "</p>
        <h2>Custom Loop Products (" . count($all_submissions) . " total):</h2>";
        
        $product_number = 1;
        foreach ($all_submissions as $submission_id => $submission) {
            $message .= "<div style='margin: 20px 0; padding: 20px; background-color: #f9f9f9; border-left: 4px solid #17a2b8;'>";
            $message .= "<h3>Product #" . $product_number . "</h3>";
            $message .= "<ul>";
            $message .= "<li><strong>Loop Color:</strong> " . $submission['loop_color'] . "</li>";
            $message .= "<li><strong>Sock Clips:</strong> " . $submission['sock_clips'] . "</li>";
            $message .= "<li><strong>Has Logo:</strong> " . $submission['has_logo'] . "</li>";
            
            if ($submission['has_logo'] === 'Yes' && !empty($submission['logo_url'])) {
                $message .= "<li><strong>Logo File:</strong> <a href='" . esc_url($submission['logo_url']) . "'>View Logo</a></li>";
            }
            
            if (!empty($submission['sport_word'])) {
                $message .= "<li><strong>Sport/Word on Strap:</strong> " . $submission['sport_word'] . "</li>";
            }
            
            $message .= "<li><strong>Tag Information Type:</strong> " . $submission['tag_info_type'] . "</li>";
            
            if ($submission['tag_info_type'] === 'Numbers') {
                $message .= "<li><strong>Selected Numbers:</strong> ";
                if (is_array($submission['tag_numbers'])) {
                    $message .= implode(', ', $submission['tag_numbers']);
                }
                $message .= "</li>";
            } else {
                $message .= "<li><strong>Names:</strong> ";
                if (is_array($submission['tag_names']) && !empty($submission['tag_names'])) {
                    $total_names = count($submission['tag_names']);
                    $message .= $total_names . " total";
                    
                    $message .= "<div style='margin-top: 10px; margin-left: 20px; padding: 10px; background-color: white; border-left: 3px solid #ddd;'>";
                    $message .= "<ol style='margin: 0; padding-left: 20px;'>";
                    foreach ($submission['tag_names'] as $name) {
                        $message .= "<li>" . esc_html($name) . "</li>";
                    }
                    $message .= "</ol>";
                    $message .= "</div>";
                }
                $message .= "</li>";
            }
            
            $message .= "<li><strong>Additional Blanks:</strong> " . $submission['add_blanks'] . "</li>";
            $message .= "<li><strong>Number of Sets:</strong> " . $submission['num_sets'] . "</li>";
            $message .= "<li><strong>Total Loops:</strong> " . $submission['total_loops'] . "</li>";
            
            if (!empty($submission['text_color'])) {
                $message .= "<li><strong>Text Color:</strong> " . $submission['text_color'] . 
                           " <span style='display: inline-block; width: 20px; height: 20px; background-color: " . 
                           $submission['text_color'] . "; vertical-align: middle; border: 1px solid #ddd;'></span></li>";
            }
            
            if (!empty($submission['font_choice'])) {
                $font_text = '';
                switch ($submission['font_choice']) {
                    case 'default':
                        $font_text = 'Use Default Font(s)';
                        break;
                    case 'previous':
                        $font_text = 'Use Font(s) Previously Provided';
                        break;
                    case 'new':
                        $font_text = 'Uploaded New Font';
                        if (!empty($submission['custom_font_url'])) {
                            $font_text .= " - <a href='" . esc_url($submission['custom_font_url']) . "'>" . 
                                         esc_html($submission['custom_font_name']) . "</a>";
                        }
                        break;
                }
                $message .= "<li><strong>Font Choice:</strong> " . $font_text . "</li>";
            }
            
            if (!empty($submission['order_notes'])) {
                $message .= "<li><strong>Order Notes:</strong> " . nl2br(esc_html($submission['order_notes'])) . "</li>";
            }
            
            $message .= "</ul>";
            $message .= "</div>";
            
            $product_number++;
        }
        
        $message .= "<hr style='margin: 20px 0; border: none; border-top: 1px solid #ddd;'>";
        $message .= "<p style='font-size: 12px; color: #666;'>This is an automated notification from the Custom Laundry Loops Form plugin.</p>";
        
        if (is_page(7320)) {
            wp_mail($to, $subject, $message, $headers);
        }
    } else {
        // Fallback to original single product email for backward compatibility
        if (get_post_meta($order_id, '_cllf_loop_color', true)) {
            // [Original email code here]
        }
    }
}

/**
 * Clear all submissions when cart is emptied
 * Add this to the existing cllf_clear_protected_item_quantities function
 */
add_action('woocommerce_cart_emptied', 'cllf_clear_all_submissions');
function cllf_clear_all_submissions() {
    if (function_exists('WC') && WC()->session !== null) {
        WC()->session->set('cllf_all_submissions', array());
        WC()->session->set('cllf_current_submission_id', null);
    }
}

/**
 * Enhanced Cart Protection for Custom Laundry Loops
 * This code replaces the existing cart protection functions starting around line 2500
 */

// Check if we should skip protection (updated to handle WooCommerce empty cart action)
function cllf_should_skip_protection() {
    // Check if this is the WooCommerce empty cart action
    if (isset($_GET['empty-cart']) && $_GET['empty-cart'] === 'yes' && 
        isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'woocommerce-cart')) {
        return true;
    }
    
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

// Check if a product is a custom loop product
function cllf_is_custom_loop_product($product_id) {
    $product = wc_get_product($product_id);
    
    if (!$product) {
        return false;
    }
    
    // Check if SKU starts with 'CL-'
    $sku = $product->get_sku();
    return ($sku && strpos($sku, 'CL-') === 0);
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

// Prevent removal of sublimation fees AND custom loops when they're in cart together
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
    
    // NEW: If this is a custom loop product, lock it as well
    if (cllf_is_custom_loop_product($product_id)) {
        return '<span class="custom-loop-locked" title="Custom loops cannot be removed individually. Use the \'Empty Cart\' button to clear all items.">🔒</span>';
    }
    
    return $remove_link;
}

// Prevent AJAX removal of sublimation fees AND custom loops when they're in cart together
add_action('wp_ajax_woocommerce_remove_from_cart', 'cllf_restrict_cart_item_removal', 5); 
add_action('wp_ajax_nopriv_woocommerce_remove_from_cart', 'cllf_restrict_cart_item_removal', 5);
function cllf_restrict_cart_item_removal() {
    if (cllf_should_skip_protection()) {
        return;
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
    
    // NEW: If this is a custom loop product, prevent removal
    if (cllf_is_custom_loop_product($product_id)) {
        wp_send_json_error(array(
            'error' => true,
            'message' => 'Custom loops cannot be removed individually. Please use the "Empty Cart" button to clear all items from your cart.'
        ));
        exit;
    }
}

// Prevent quantity changes for sublimation fees AND custom loops
add_filter('woocommerce_cart_item_quantity', 'cllf_filter_cart_item_quantity', 10, 3);
function cllf_filter_cart_item_quantity($product_quantity, $cart_item_key, $cart_item) {
    if (cllf_should_skip_protection()) {
        return $product_quantity;
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
    
    // NEW: If this is a custom loop product, lock quantity changes as well
    if (cllf_is_custom_loop_product($product_id)) {
        $current_qty = $cart_item['quantity'];
        
        return '<span class="custom-loop-qty">' . $current_qty . '</span>' .
               '<input type="hidden" name="cart[' . $cart_item_key . '][qty]" value="' . $current_qty . '" />';
    }
    
    return $product_quantity;
}

// Prevent quantity updates for sublimation fees AND custom loops through form submission
add_action('woocommerce_before_cart_item_quantity_zero', 'cllf_prevent_protected_item_quantity_zero', 10, 2);
function cllf_prevent_protected_item_quantity_zero($cart_item_key, $cart) {
    if (cllf_should_skip_protection()) {
        return;
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
    
    // NEW: Prevent custom loop removal
    if (cllf_is_custom_loop_product($product_id)) {
        // Restore the original quantity
        $cart->cart_contents[$cart_item_key]['quantity'] = $cart_item['quantity'];
        
        // Add error notice
        wc_add_notice('Custom loops cannot be removed individually. Please use the "Empty Cart" button to clear all items.', 'error');
        
        // Prevent further processing
        add_filter('woocommerce_update_cart_action_cart_updated', '__return_true');
    }
}

// Protect against direct cart update requests
add_action('woocommerce_before_calculate_totals', 'cllf_protect_cart_item_quantities', 10, 1);
function cllf_protect_cart_item_quantities($cart) {
    if (cllf_should_skip_protection()) {
        return;
    }
    
    if (!function_exists('WC')) {
        return;
    }
    
    // Only run once
    if (did_action('woocommerce_before_calculate_totals') > 1) {
        return;
    }
    
    // Get original quantities from session if available
    $original_quantities = WC()->session->get('protected_item_quantities', []);
    $quantities_updated = false;
    
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $is_protected = false;
        $error_message = '';
        
        // Check if this is a protected item
        if (cllf_is_sublimation_fee($product_id) && cllf_has_custom_loops_in_cart()) {
            $is_protected = true;
            $error_message = 'Sublimation fee quantities cannot be modified while custom loops are in your cart.';
        } elseif (cllf_is_custom_loop_product($product_id)) {
            $is_protected = true;
            $error_message = 'Custom loop quantities cannot be modified. Please remove all items and re-add with the correct quantity.';
        }
        
        if ($is_protected) {
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
                    wc_add_notice($error_message, 'error');
                }
            }
        }
    }
    
    // Save updated quantities
    if ($quantities_updated) {
        WC()->session->set('protected_item_quantities', $original_quantities);
    }
}

// Update styles to include the custom loop styles
add_action('wp_head', 'cllf_add_locked_item_styles');
function cllf_add_locked_item_styles() {
    if (!is_cart() && !is_checkout()) {
        return;
    }
    
    ?>
    <style>
        .sublimation-fee-locked,
        .custom-loop-locked {
            font-size: 18px;
            color: #777;
            cursor: not-allowed;
        }
        
        .sublimation-fee-qty,
        .custom-loop-qty {
            font-weight: bold;
            padding: 0.5em;
            background-color: #f8f8f8;
            border-radius: 3px;
            border: 1px solid #ddd;
            display: inline-block;
            min-width: 3em;
            text-align: center;
        }
        
        /* Style for error messages */
        .woocommerce-error-sublimation,
        .woocommerce-error-custom-loop {
            padding: 1em 2em 1em 3.5em;
            margin: 0 0 2em;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            position: relative;
        }
        
        /* Highlight the empty cart button when items are locked */
        .custom-loops-in-cart .woocommerce-cart-form__contents + .cart-collaterals .button[name="empty_cart"] {
            background-color: #dc3545;
            color: white;
            font-weight: bold;
        }
        
        .custom-loops-in-cart .woocommerce-cart-form__contents + .cart-collaterals .button[name="empty_cart"]:hover {
            background-color: #c82333;
        }
    </style>
    <?php
}

// Clear stored quantities when all loops are removed
add_action('woocommerce_cart_emptied', 'cllf_clear_protected_item_quantities');
function cllf_clear_protected_item_quantities() {
    if (function_exists('WC') && WC()->session !== null) {
        WC()->session->set('protected_item_quantities', []);
        WC()->session->set('sublimation_fee_quantities', []); // Keep existing one too
    }
}

// Add JavaScript to handle AJAX removal attempts and show error messages
add_action('wp_footer', 'cllf_add_cart_protection_script');
function cllf_add_cart_protection_script() {
    if (!is_cart() && !is_checkout()) {
        return;
    }
    
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Add class to body when custom loops are in cart
        <?php if (cllf_has_custom_loops_in_cart()) : ?>
        $('body').addClass('custom-loops-in-cart');
        <?php endif; ?>
        
        $(document.body).on('removed_from_cart', function(event, fragments, cart_hash, $button) {
            if (fragments && fragments.hasOwnProperty('error') && fragments.error) {
                // Show error message
                var errorClass = fragments.message.indexOf('Custom loops') !== -1 ? 
                    'woocommerce-error-custom-loop' : 'woocommerce-error-sublimation';
                
                if ($('.' + errorClass).length === 0) {
                    $('<div class="' + errorClass + '">' + fragments.message + '</div>')
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
        
        // Add tooltip to locked items
        $('.sublimation-fee-locked, .custom-loop-locked').tooltip({
            position: { my: "left center", at: "right+10 center" }
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

/**
 * Updated debug functions to display ALL custom loop products data
 * Replace your existing debug functions with these
 */

// Add debug display to cart page
add_action('woocommerce_before_cart', 'cllf_debug_display_all_submissions');
function cllf_debug_display_all_submissions() {
    // Check if debug mode is enabled
    if (!cllf_is_debug_mode_enabled()) {
        return;
    }
    
    // Only show to administrators
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Get all submissions
    $all_submissions = WC()->session->get('cllf_all_submissions', array());
    
    if (empty($all_submissions)) {
        return;
    }
    
    ?>
    <div style="background-color: #fff3cd; border: 2px solid #ffc107; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <h3 style="margin-top: 0; color: #856404;">🔍 DEBUG: All Custom Loop Submissions (Admin Only)</h3>
        <p><strong>Total Submissions:</strong> <?php echo count($all_submissions); ?></p>
        
        <?php 
        $submission_num = 1;
        foreach ($all_submissions as $submission_id => $submission) : 
        ?>
            <div style="background-color: white; padding: 15px; margin: 10px 0; border: 1px solid #ddd; border-radius: 3px;">
                <h4 style="margin-top: 0; color: #17a2b8;">
                    Submission #<?php echo $submission_num; ?> 
                    <span style="font-size: 12px; color: #666;">(ID: <?php echo esc_html($submission_id); ?>)</span>
                </h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <p><strong>Color:</strong> <?php echo esc_html($submission['loop_color']); ?></p>
                        <p><strong>Clips:</strong> <?php echo esc_html($submission['sock_clips']); ?></p>
                        <p><strong>Logo:</strong> <?php echo esc_html($submission['has_logo']); ?></p>
                        <p><strong>Sport/Word:</strong> <?php echo esc_html($submission['sport_word'] ?: 'None'); ?></p>
                        <p><strong>Text Color:</strong> 
                            <span style="display: inline-block; width: 20px; height: 20px; background-color: <?php echo esc_attr($submission['text_color']); ?>; border: 1px solid #ddd; vertical-align: middle;"></span>
                            <?php echo esc_html($submission['text_color']); ?>
                        </p>
                    </div>
                    <div>
                        <p><strong>Tag Type:</strong> <?php echo esc_html($submission['tag_info_type']); ?></p>
                        <p><strong>Sets:</strong> <?php echo esc_html($submission['num_sets']); ?></p>
                        <p><strong>Blanks:</strong> <?php echo esc_html($submission['add_blanks']); ?></p>
                        <p><strong>Total Loops:</strong> <?php echo esc_html($submission['total_loops']); ?></p>
                    </div>
                </div>
                
                <?php if ($submission['tag_info_type'] === 'Names' && !empty($submission['tag_names'])) : ?>
                    <div style="margin-top: 10px;">
                        <strong>Names (<?php echo count($submission['tag_names']); ?> total):</strong>
                        <a href="#" onclick="jQuery('#debug-names-<?php echo $submission_num; ?>').toggle(); return false;" style="font-size: 12px; margin-left: 10px;">
                            [Show/Hide]
                        </a>
                        <div id="debug-names-<?php echo $submission_num; ?>" style="display: none; max-height: 200px; overflow-y: auto; background: #f9f9f9; padding: 10px; margin-top: 5px; font-size: 12px;">
                            <?php 
                            foreach ($submission['tag_names'] as $i => $name) {
                                echo ($i + 1) . ". " . esc_html($name) . " (" . strlen($name) . " chars)<br>";
                            }
                            ?>
                        </div>
                    </div>
                <?php elseif ($submission['tag_info_type'] === 'Numbers' && !empty($submission['tag_numbers'])) : ?>
                    <div style="margin-top: 10px;">
                        <strong>Numbers:</strong> <?php echo implode(', ', $submission['tag_numbers']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($submission['order_notes'])) : ?>
                    <div style="margin-top: 10px;">
                        <strong>Notes:</strong> <?php echo nl2br(esc_html($submission['order_notes'])); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php 
        $submission_num++;
        endforeach; 
        ?>
        
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ffc107;">
            <p style="margin: 0; font-size: 12px; color: #856404;">
                <strong>Note:</strong> This debug information is only visible to administrators. 
                Each submission represents a separate custom loop product added to the cart.
            </p>
        </div>
    </div>
    <?php
}

// Alternative: Add debug info to the cart totals area
add_action('woocommerce_cart_totals_after_order_total', 'cllf_debug_names_in_totals');
function cllf_debug_names_in_totals() {
    // Check if debug mode is enabled
    if (!cllf_is_debug_mode_enabled()) {
        return;
    }
    
    // Only show to administrators
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Get all submissions
    $all_submissions = WC()->session->get('cllf_all_submissions', array());
    
    // Count total names across all submissions
    $total_names = 0;
    $total_numbers = 0;
    $names_products = 0;
    $numbers_products = 0;
    
    foreach ($all_submissions as $submission) {
        if ($submission['tag_info_type'] === 'Names' && !empty($submission['tag_names'])) {
            $total_names += count($submission['tag_names']);
            $names_products++;
        } elseif ($submission['tag_info_type'] === 'Numbers' && !empty($submission['tag_numbers'])) {
            $total_numbers += count($submission['tag_numbers']);
            $numbers_products++;
        }
    }
    
    if ($total_names > 0 || $total_numbers > 0) {
        ?>
        <tr>
            <th>Debug: Loop Products (Admin Only)</th>
            <td data-title="Loop Products">
                <?php 
                $summary = array();
                if ($names_products > 0) {
                    $summary[] = $names_products . " with names (" . $total_names . " names total)";
                }
                if ($numbers_products > 0) {
                    $summary[] = $numbers_products . " with numbers";
                }
                echo implode(', ', $summary);
                ?>
                <a href="#" onclick="jQuery('#cllf-debug-all-products').toggle(); return false;" style="font-size: 12px; margin-left: 10px;">
                    [Show Details]
                </a>
            </td>
        </tr>
        <tr id="cllf-debug-all-products" style="display: none;">
            <td colspan="2">
                <div style="max-height: 300px; overflow-y: auto; background: #f9f9f9; padding: 10px; font-size: 12px;">
                    <?php 
                    $prod_num = 1;
                    foreach ($all_submissions as $submission_id => $submission) {
                        echo "<strong>Product #{$prod_num}:</strong> ";
                        echo $submission['loop_color'] . " " . $submission['sock_clips'] . " Clip - ";
                        
                        if ($submission['tag_info_type'] === 'Names') {
                            echo count($submission['tag_names']) . " names<br>";
                            echo "<div style='margin-left: 20px;'>";
                            foreach ($submission['tag_names'] as $i => $name) {
                                echo ($i + 1) . ". " . esc_html($name) . "<br>";
                            }
                            echo "</div>";
                        } else {
                            echo "Numbers: " . implode(', ', $submission['tag_numbers']) . "<br>";
                        }
                        
                        echo "<br>";
                        $prod_num++;
                    }
                    ?>
                </div>
            </td>
        </tr>
        <?php
    }
}

// Add a button to view all session data
add_action('woocommerce_proceed_to_checkout', 'cllf_debug_session_button', 999);
function cllf_debug_session_button() {
    // Check if debug mode is enabled
    if (!cllf_is_debug_mode_enabled()) {
        return;
    }
    
    // Only show to administrators
    if (!current_user_can('manage_options')) {
        return;
    }
    
    ?>
    <div style="margin-top: 20px; text-align: center;">
        <button type="button" onclick="jQuery('#cllf-all-session-data').toggle();" style="background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer;">
            🔍 Show/Hide All CLLF Session Data (Admin Only)
        </button>
    </div>
    <div id="cllf-all-session-data" style="display: none; margin-top: 20px; background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-radius: 5px;">
        <h4>All Custom Loop Form Session Data:</h4>
        
        <?php
        // Get all submissions
        $all_submissions = WC()->session->get('cllf_all_submissions', array());
        
        if (!empty($all_submissions)) {
            ?>
            <h5 style="color: #17a2b8;">All Submissions (<?php echo count($all_submissions); ?> total):</h5>
            <pre style="background: white; padding: 15px; overflow-x: auto; font-size: 11px; max-height: 400px;"><?php
                echo htmlspecialchars(print_r($all_submissions, true));
            ?></pre>
            <?php
        }
        ?>
        
        <h5 style="color: #17a2b8; margin-top: 20px;">Current/Last Submission Data (Legacy):</h5>
        <pre style="background: white; padding: 15px; overflow-x: auto; font-size: 11px;"><?php
            $session_data = array(
                'loop_color' => WC()->session->get('cllf_loop_color'),
                'sock_clips' => WC()->session->get('cllf_sock_clips'),
                'has_logo' => WC()->session->get('cllf_has_logo'),
                'logo_url' => WC()->session->get('cllf_logo_url'),
                'sport_word' => WC()->session->get('cllf_sport_word'),
                'tag_info_type' => WC()->session->get('cllf_tag_info_type'),
                'tag_numbers' => WC()->session->get('cllf_tag_numbers'),
                'tag_names' => WC()->session->get('cllf_tag_names'),
                'add_blanks' => WC()->session->get('cllf_add_blanks'),
                'num_sets' => WC()->session->get('cllf_num_sets'),
                'total_loops' => WC()->session->get('cllf_total_loops'),
                'order_notes' => WC()->session->get('cllf_order_notes'),
                'text_color' => WC()->session->get('cllf_text_color'),
                'font_choice' => WC()->session->get('cllf_font_choice'),
                'custom_font_url' => WC()->session->get('cllf_custom_font_url'),
                'custom_font_name' => WC()->session->get('cllf_custom_font_name'),
            );
            echo htmlspecialchars(print_r($session_data, true));
        ?></pre>
        
        <h5 style="color: #17a2b8; margin-top: 20px;">Cart Items with Submission IDs:</h5>
        <pre style="background: white; padding: 15px; overflow-x: auto; font-size: 11px;"><?php
            $cart_items_debug = array();
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $product = $cart_item['data'];
                if ($product->get_sku() && strpos($product->get_sku(), 'CL-') === 0) {
                    $cart_items_debug[$cart_item_key] = array(
                        'product_name' => $product->get_name(),
                        'sku' => $product->get_sku(),
                        'quantity' => $cart_item['quantity'],
                        'submission_id' => isset($cart_item['cllf_submission_id']) ? $cart_item['cllf_submission_id'] : 'Not set'
                    );
                }
            }
            echo htmlspecialchars(print_r($cart_items_debug, true));
        ?></pre>
    </div>
    <?php
}

// Add a quick summary in the cart header
add_action('woocommerce_before_cart_table', 'cllf_debug_cart_summary');
function cllf_debug_cart_summary() {
    // Check if debug mode is enabled
    if (!cllf_is_debug_mode_enabled()) {
        return;
    }
    
    // Only show to administrators
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $all_submissions = WC()->session->get('cllf_all_submissions', array());
    
    if (!empty($all_submissions)) {
        $total_loops = 0;
        foreach ($all_submissions as $submission) {
            $total_loops += $submission['total_loops'];
        }
        
        ?>
        <div style="background-color: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 20px; border-radius: 4px; color: #155724;">
            <strong>🔍 Debug Summary (Admin Only):</strong> 
            <?php echo count($all_submissions); ?> custom loop products in session, 
            totaling <?php echo $total_loops; ?> loops
        </div>
        <?php
    }
}

/**
 * Add "Start a new Loop Order" button to cart page
 * Add this to your custom-loop-form-plugin.php file
 */

// Add button above the cart table
add_action('woocommerce_before_cart_table', 'cllf_add_new_loop_order_button', 15);
function cllf_add_new_loop_order_button() {
    // Only show if there are custom loops in the cart
    if (!cllf_has_custom_loops_in_cart()) {
        return;
    }
    
    // Get the page URL that has the form shortcode
    // You can hardcode this or make it a setting
    $form_page_url = cllf_get_form_page_url();
    
    ?>
    <div class="cllf-cart-actions" style="margin-bottom: 20px;">
        <a href="<?php echo esc_url($form_page_url); ?>" class="button alt" style="background-color: #17a2b8; color: white; padding: 12px 24px; font-size: 16px;">
            ➕ Start a New Loop Order
        </a>
        <span style="margin-left: 15px; color: #666; font-style: italic;">
            Add another custom loop product to your order
        </span>
    </div>
    <?php
}

// Alternative location: Add button after cart totals
add_action('woocommerce_after_cart_totals', 'cllf_add_new_loop_order_button_after_totals');
function cllf_add_new_loop_order_button_after_totals() {
    // Only show if there are custom loops in the cart
    if (!cllf_has_custom_loops_in_cart()) {
        return;
    }
    
    // Get the form page URL
    $form_page_url = cllf_get_form_page_url();
    
    ?>
    <div class="cllf-add-more-loops" style="text-align: center; margin-top: 20px; padding: 20px; background-color: #f8f9fa; border-radius: 5px;">
        <p style="margin-bottom: 10px; font-weight: bold;">Need more custom loops?</p>
        <a href="<?php echo esc_url($form_page_url); ?>" class="button" style="background-color: #28a745; color: white;">
            ➕ Start a New Loop Order
        </a>
    </div>
    <?php
}

/**
 * Add shipping disclaimer to cart page
 */
add_action('woocommerce_cart_totals_after_shipping', 'cllf_add_shipping_disclaimer_cart');
function cllf_add_shipping_disclaimer_cart() {
    // Only show if there are custom loops in the cart
    if (!cllf_has_custom_loops_in_cart()) {
        return;
    }
    
    ?>
    <tr class="shipping-disclaimer">
        <td colspan="2" style="padding: 10px;">
            <div style="background-color: #e6f3ff; border-left: 4px solid #17a2b8; padding: 12px; margin: 10px 0; border-radius: 4px;">
                <strong style="color: #17a2b8;">📦 Shipping Note:</strong>
                <p style="margin: 5px 0 0 0; font-size: 13px; color: #333; line-height: 1.5;">
                    The shipping quote shown is an estimate based on standard product weights. Due to the custom nature of your loops order, 
                    actual shipping charges will be calculated after production and added to your final invoice. 
                    You will be notified of any shipping adjustments before payment is processed.
                </p>
            </div>
        </td>
    </tr>
    <?php
}

/**
 * Add shipping disclaimer to checkout page
 */
add_action('woocommerce_review_order_after_shipping', 'cllf_add_shipping_disclaimer_checkout');
function cllf_add_shipping_disclaimer_checkout() {
    // Only show if there are custom loops in the cart
    if (!cllf_has_custom_loops_in_cart()) {
        return;
    }
    
    ?>
    <tr class="shipping-disclaimer">
        <td colspan="2" style="padding: 10px 0;">
            <div style="background-color: #fff8dc; border-left: 4px solid #ffc107; padding: 12px; margin: 10px 0; border-radius: 4px; font-size: 13px;">
                <strong style="color: #856404;">⚠️ Important Shipping Information:</strong>
                <p style="margin: 5px 0 0 0; color: #856404; line-height: 1.5;">
                    This shipping quote is an estimate. Final shipping costs for your custom loops will be calculated based on actual 
                    weight and dimensions after production. Any adjustments will be reflected in your final invoice, which will be 
                    sent for approval before payment processing.
                </p>
            </div>
        </td>
    </tr>
    <?php
}

/**
 * Alternative: Add shipping disclaimer as a notice on cart page
 */
add_action('woocommerce_before_cart', 'cllf_shipping_disclaimer_notice_cart');
function cllf_shipping_disclaimer_notice_cart() {
    // Only show if there are custom loops in the cart
    if (!cllf_has_custom_loops_in_cart()) {
        return;
    }
    
    wc_print_notice(
        '<strong>Shipping Notice:</strong> Shipping costs shown are estimates. Final shipping charges for custom loop orders will be confirmed after production and included in your final invoice.',
        'notice'
    );
}

/**
 * Add more prominent shipping disclaimer on checkout
 */
add_action('woocommerce_checkout_before_order_review', 'cllf_shipping_disclaimer_notice_checkout');
function cllf_shipping_disclaimer_notice_checkout() {
    // Only show if there are custom loops in the cart
    if (!cllf_has_custom_loops_in_cart()) {
        return;
    }
    
    ?>
    <div class="cllf-checkout-shipping-notice" style="background-color: #fef9e7; border: 2px solid #f9e79f; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
        <h4 style="margin-top: 0; color: #7d6608;">📦 Shipping Information for Custom Loops</h4>
        <p style="margin-bottom: 0; color: #7d6608;">
            The shipping cost displayed is an estimate based on standard dimensions and weights. 
            Your custom loops order will have its final shipping cost calculated after production is complete. 
            This ensures you receive the most accurate shipping rate based on your specific order. 
            Any shipping adjustments will be included in your final invoice for approval.
        </p>
    </div>
    <?php
}

/**
 * Add CSS for better styling
 */
add_action('wp_head', 'cllf_add_cart_button_styles');
function cllf_add_cart_button_styles() {
    if (!is_cart() && !is_checkout()) {
        return;
    }
    
    ?>
    <style>
        /* Style the new loop order button */
        .cllf-cart-actions .button {
            transition: all 0.3s ease;
        }
        
        .cllf-cart-actions .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        /* Ensure shipping disclaimer is visible */
        .shipping-disclaimer td {
            padding: 0 !important;
        }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .cllf-cart-actions {
                text-align: center;
            }
            
            .cllf-cart-actions span {
                display: block;
                margin-top: 10px;
                margin-left: 0 !important;
            }
            
            .cllf-checkout-shipping-notice {
                margin-left: -15px;
                margin-right: -15px;
                border-radius: 0;
            }
        }
        
        /* Make checkout shipping notice stand out */
        .woocommerce-checkout .cllf-checkout-shipping-notice {
            animation: subtle-pulse 2s ease-in-out;
        }
        
        @keyframes subtle-pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.9;
            }
        }
    </style>
    <?php
}

/**
 * Then update your button functions to use this saved setting
 * Replace the form page URL detection in your button functions with this:
 */
function cllf_get_form_page_url() {
    // First try to get from settings
    $settings = get_option('clloi_settings');
    if (!empty($settings['cllf_form_page_id'])) {
        $page_id = $settings['cllf_form_page_id'];
        $page_url = get_permalink($page_id);
        if ($page_url) {
            return $page_url;
        }
    }
    
    // Fallback to auto-detection
    $pages = get_pages();
    foreach ($pages as $page) {
        if (has_shortcode($page->post_content, 'custom_laundry_loops_form')) {
            return get_permalink($page->ID);
        }
    }
    
    // Last resort - return home URL with a hash
    return home_url('/#custom-loops-form');
}