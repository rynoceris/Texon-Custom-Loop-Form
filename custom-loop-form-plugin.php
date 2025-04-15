<?php
/**
 * @package CLLF
 * @wordpress-plugin
 *
 * Plugin Name:          Custom Laundry Loops Form
 * Plugin URI:           https://www.texontowel.com
 * Description:          Display a custom form for ordering custom laundry loops directly on the frontend.
 * Version:              1.0.17
 * Author:               Texon Towel
 * Author URI:           https://www.texontowel.com
 * Developer:            Texon Towel
 * Copyright:            © 2025 Texon Towel (email : sales@texontowel.com).
 * License: GNU          General Public License v3.0
 * License URI:          http://www.gnu.org/licenses/gpl-3.0.html
 * Tested up to:         6.6.2
 * WooCommerce:          9.4.1
 * PHP tested up to:     8.2.24
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

// Include the form template
function cllf_form_shortcode() {
    ob_start();
    include_once CLLF_PLUGIN_DIR . 'templates/form-template.php';
    return ob_get_clean();
}
add_shortcode('custom_laundry_loops_form', 'cllf_form_shortcode');

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
    $tag_info_type = isset($_POST['tag_info_type']) ? sanitize_text_field($_POST['tag_info_type']) : '';
    $tag_numbers = isset($_POST['tag_numbers']) ? array_map('intval', $_POST['tag_numbers']) : array();
    $tag_names = isset($_POST['tag_names']) ? array_map('sanitize_text_field', $_POST['tag_names']) : array();
    $add_blanks = isset($_POST['add_blanks']) ? intval($_POST['add_blanks']) : 0;
    $num_sets = isset($_POST['num_sets']) ? intval($_POST['num_sets']) : 1;
    $order_notes = isset($_POST['order_notes']) ? sanitize_textarea_field($_POST['order_notes']) : '';
    
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
    
    // Process the order and add items to cart
    $cart_url = cllf_process_order_and_add_to_cart($loop_color, $sock_clips, $total_loops);
    
    // Return success without redirecting
    wp_send_json_success(array('cart_url' => $cart_url, 'message' => 'Your custom loops have been added to the cart!'));
}

/**
 * Process the order and add items to cart
 */
function cllf_process_order_and_add_to_cart($loop_color, $sock_clips, $total_loops) {
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
    
    // Check if product already exists
    $product_id = wc_get_product_id_by_sku($sku);
    
    if ($product_id) {
        // Product exists, add it to the cart
        WC()->cart->add_to_cart($product_id, $total_loops);
    } else {
        // Product does not exist, create it
        $product = new WC_Product_Simple();
        $product->set_name('Custom Laundry Straps with Sublimated ID Tags - Color: ' . $color . ' - Clips: ' . $clips);
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
        
        // Add the new product to the cart
        WC()->cart->add_to_cart($new_product_id, $total_loops);
    }
    
    // Add sublimation charges
    $sublimation_product_1 = isset($clloi_settings['clloi_sublimation_product_1']) ? $clloi_settings['clloi_sublimation_product_1'] : 49143;
    $sublimation_product_2 = isset($clloi_settings['clloi_sublimation_product_2']) ? $clloi_settings['clloi_sublimation_product_2'] : 49133;
    $sublimation_product_3 = isset($clloi_settings['clloi_sublimation_product_3']) ? $clloi_settings['clloi_sublimation_product_3'] : 49132;
    
    // If less than 24 total loops are ordered, change sublimation charge to $3.95/ea, otherwise charge $1.95/ea
    if ($total_loops < 24) {
        WC()->cart->add_to_cart($sublimation_product_1, $total_loops);
    } else {
        WC()->cart->add_to_cart($sublimation_product_2, $total_loops);
    }
    
    // Add 1-time $35 sublimation setup fee
    WC()->cart->add_to_cart($sublimation_product_3, 1);
    
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
    $sport_word = WC()->session->get('cllf_sport_word');
    $tag_info_type = WC()->session->get('cllf_tag_info_type');
    $tag_numbers = WC()->session->get('cllf_tag_numbers');
    $tag_names = WC()->session->get('cllf_tag_names');
    $add_blanks = WC()->session->get('cllf_add_blanks');
    $num_sets = WC()->session->get('cllf_num_sets');
    $total_loops = WC()->session->get('cllf_total_loops');
    
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
        WC()->session->__unset('cllf_order_notes');
    }
    
    // Clear session data
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
        
        $headers[] = "From: Texon Athletic Towel <sales@texontowel.com>" . "\r\n";
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $to = array('ryan@texontowel.com', 'stephanie@texontowel.com', 'jen@texontowel.com', 'wmk@texontowel.com');
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
            
        if ($order_notes = get_post_meta($order_id, '_cllf_order_notes', true)) {
            $message .= "<li><strong>Order Notes:</strong> " . nl2br(esc_html($order_notes)) . "</li>";
        }
        
        $message .= "</ul>";
        
        if (is_page(7320)) {
            wp_mail($to, $subject, $message, $headers);
        }
    }
}