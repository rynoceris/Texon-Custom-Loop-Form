<?php
/**
 * @package CLLF
 * @wordpress-plugin
 *
 * Plugin Name:          Custom Laundry Loops Form
 * Plugin URI:           https://www.texontowel.com
 * Description:          Display a custom form for ordering custom laundry loops directly on the frontend.
 * Version:              2.3.4
 * Author:               Texon Towel
 * Author URI:           https://www.texontowel.com
 * Developer:            Ryan Ours
 * Copyright:            © 2025 Texon Towel (email : sales@texontowel.com).
 * License: GNU          General Public License v3.0
 * License URI:          http://www.gnu.org/licenses/gpl-3.0.html
 * Tested up to:         6.8.2
 * WooCommerce:          10.1.2
 * PHP tested up to:     8.2.28
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('CLLF_VERSION', '2.3.4');
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
        
        // Pass variables to JS - with empty nonce that will be refreshed via AJAX
        wp_localize_script('cllf-scripts', 'cllfVars', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => '', // Will be populated via AJAX to avoid cache issues
            'nonceFieldName' => 'nonce',
            'pluginUrl' => CLLF_PLUGIN_URL,
            'nonceRefreshAction' => 'cllf_refresh_nonce'
        ));
    }
}
add_action('wp_enqueue_scripts', 'cllf_enqueue_scripts');

/**
 * AJAX handler to refresh nonce - bypasses caching
 * This ensures users always get a fresh, valid nonce
 */
function cllf_refresh_nonce_ajax() {
    // No nonce verification needed for getting a nonce
    // This is a public endpoint that just generates a new nonce
    $fresh_nonce = wp_create_nonce('cllf-nonce');
    
    wp_send_json_success(array(
        'nonce' => $fresh_nonce,
        'timestamp' => current_time('timestamp')
    ));
}
add_action('wp_ajax_cllf_refresh_nonce', 'cllf_refresh_nonce_ajax');
add_action('wp_ajax_nopriv_cllf_refresh_nonce', 'cllf_refresh_nonce_ajax');

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
/*function cllf_handle_form_submission() {
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
            $allowed_types = array('image/jpeg', 'image/png', 'image/svg+xml', 'application/pdf', 'application/illustrator', 'application/postscript', 'application/eps', 'image/eps', 'application/vnd.adobe.illustrator', '.ai');
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
    
    // IMPORTANT: Check cart items for existing submissions from previous sessions
    // This handles the case where user adds loops in one session, then adds more in another session
    if (WC()->cart) {
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['cllf_submission_id'])) {
                $existing_submission_id = $cart_item['cllf_submission_id'];
                
                // Only add if not already in our current session data
                if (!isset($all_submissions[$existing_submission_id]) && isset($cart_item['cllf_submission_data'])) {
                    $all_submissions[$existing_submission_id] = $cart_item['cllf_submission_data'];
                    error_log('MULTI-SESSION: Recovered submission data for ID: ' . $existing_submission_id);
                }
            }
        }
    }
    
    error_log('MULTI-SESSION: Starting with ' . count($all_submissions) . ' existing submissions before adding new one');
    
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
}*/

/**
 * DIAGNOSTIC VERSION of the form submission handler
 * Replace your existing cllf_handle_form_submission function with this temporarily
 * to debug why form data isn't being saved
 */
 function cllf_handle_form_submission() {
     // Enhanced logging
     error_log('=== CLLF FORM SUBMISSION DEBUG START ===');
     error_log('POST data received: ' . print_r($_POST, true));
     error_log('FILES data received: ' . print_r($_FILES, true));
     
     // Check if WooCommerce session is available
     if (!function_exists('WC') || !WC()->session) {
         error_log('ERROR: WooCommerce session not available');
         wp_send_json_error('WooCommerce session not available');
         exit;
     }
     
     // Enhanced nonce verification with detailed logging
     if (!isset($_POST['nonce'])) {
         error_log('ERROR: No nonce value received in POST');
         wp_send_json_error('Security check failed: Nonce is missing');
         exit;
     }
     
     $received_nonce = $_POST['nonce'];
     error_log('Received nonce: ' . $received_nonce);
     
     if (!wp_verify_nonce($received_nonce, 'cllf-nonce')) {
          error_log('ERROR: Nonce verification failed for value: ' . $received_nonce);
          wp_send_json_error('Security check failed: Your session has expired. Please refresh the page and try again.');
          exit;
      }
     
     error_log('SUCCESS: Nonce verification passed');
     
     // Get and validate form data with detailed logging
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
     $text_color = isset($_POST['text_color']) ? sanitize_text_field($_POST['text_color']) : '#000000';
     
     // Handle custom color
     if ($text_color === 'custom' && isset($_POST['custom_color'])) {
         $text_color = sanitize_text_field($_POST['custom_color']);
     }
     
     // Enforce character limits
     if (strlen($sport_word) > 20) {
         $sport_word = substr($sport_word, 0, 20);
     }
     
     // For names, enforce 20 character limit on each name
     if ($tag_info_type === 'Names' && !empty($tag_names)) {
         foreach ($tag_names as $key => $name) {
             if (strlen($name) > 20) {
                 $tag_names[$key] = substr($name, 0, 20);
             }
         }
     }
     
     // Log extracted form data
     error_log('Extracted form data:');
     error_log('- Loop Color: ' . $loop_color);
     error_log('- Sock Clips: ' . $sock_clips);
     error_log('- Has Logo: ' . $has_logo);
     error_log('- Sport Word: ' . $sport_word);
     error_log('- Tag Info Type: ' . $tag_info_type);
     error_log('- Tag Numbers: ' . print_r($tag_numbers, true));
     error_log('- Tag Names: ' . print_r($tag_names, true));
     error_log('- Num Sets: ' . $num_sets);
     error_log('- Add Blanks: ' . $add_blanks);
     
     // Validate required fields with detailed logging
     if (empty($loop_color)) {
         error_log('ERROR: Loop color is empty');
         wp_send_json_error('Please select a loop color');
         exit;
     }
     
     if (empty($sock_clips)) {
         error_log('ERROR: Sock clips is empty');
         wp_send_json_error('Please select sock clips type');
         exit;
     }
     
     if (empty($tag_info_type)) {
         error_log('ERROR: Tag info type is empty');
         wp_send_json_error('Please select tag information type');
         exit;
     }
     
     // Validate tag information
     if ($tag_info_type === 'Numbers' && empty($tag_numbers)) {
         error_log('ERROR: Numbers selected but no numbers provided');
         wp_send_json_error('Please select at least one number');
         exit;
     } elseif ($tag_info_type === 'Names' && empty($tag_names)) {
         error_log('ERROR: Names selected but no names provided');
         wp_send_json_error('Please enter at least one name');
         exit;
     }
     
     error_log('SUCCESS: All required field validation passed');
     
     // Handle logo upload if applicable - FIXED VERSION
     $logo_url = '';
     if ($has_logo === 'Yes') {
         error_log('Processing logo upload...');
         
         // Check if we're using a stored logo from a previous submission
         if (isset($_POST['use_stored_logo']) && $_POST['use_stored_logo'] === 'yes') {
             // If logo is stored in session storage (for cloned submissions)
             if (isset($_POST['logo_in_session_storage']) && $_POST['logo_in_session_storage'] === 'yes') {
                 // The logo data URL would be stored in session storage by JavaScript
                 // We would need to retrieve it via AJAX or a separate endpoint
                 // For now, we'll use the session stored URL if available
                 $logo_url = WC()->session->get('cllf_logo_url');
                 if ($logo_url) {
                     error_log('Using stored logo URL from session: ' . $logo_url);
                 } else {
                     error_log('No stored logo URL found in session');
                 }
                 
                 if (isset($_POST['stored_logo_name'])) {
                     // Store the original filename for reference
                     WC()->session->set('cllf_previous_logo_name', sanitize_text_field($_POST['stored_logo_name']));
                 }
             } else if (WC()->session->get('cllf_logo_url')) {
                 // Use the logo URL from the previous submission stored in the session
                 $logo_url = WC()->session->get('cllf_logo_url');
                 error_log('Using logo URL from previous session: ' . $logo_url);
             }
         } else if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
             // Process new logo upload
             error_log('Processing new logo file upload');
             $logo_file = $_FILES['logo_file'];
             $upload_dir = wp_upload_dir();
             $logo_dir = $upload_dir['basedir'] . '/cllf-uploads';
             
             // Create upload directory if needed
             if (!file_exists($logo_dir)) {
                 if (!mkdir($logo_dir, 0755, true)) {
                     error_log('Failed to create upload directory: ' . $logo_dir);
                     wp_send_json_error('Failed to create upload directory');
                     exit;
                 }
             }
             
             // Check for upload errors
             if ($logo_file['error'] !== UPLOAD_ERR_OK) {
                 error_log('Logo upload failed with error code: ' . $logo_file['error']);
                 wp_send_json_error('Logo upload failed with error code: ' . $logo_file['error']);
                 exit;
             }
             
             // Enhanced file type validation
             $file_info = wp_check_filetype($logo_file['name']);
             $file_ext = strtolower($file_info['ext']);
             
             // Define allowed file types
             $allowed_mime_types = array(
                 'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/svg+xml',
                 'application/pdf', 'application/postscript', 'application/illustrator'
             );
             
             $allowed_extensions = array(
                 'jpg', 'jpeg', 'png', 'gif', 'svg', 'pdf', 'ai', 'eps'
             );
             
             error_log('File type check: Provided=' . $logo_file['type'] . ', Detected=' . $file_info['type'] .
                       ', Extension=' . $file_ext);
             
             // Check both MIME type and extension
             if (!in_array($logo_file['type'], $allowed_mime_types) && 
                 !in_array($file_info['type'], $allowed_mime_types) && 
                 !in_array($file_ext, $allowed_extensions)) {
                 
                 error_log('Invalid file type: ' . $logo_file['type'] . ' with extension: ' . $file_ext);
                 wp_send_json_error('Invalid file type. Allowed formats: JPEG, PNG, GIF, SVG, PDF, AI, EPS');
                 exit;
             }
             
             // Generate unique filename
             $filename = wp_unique_filename($logo_dir, $logo_file['name']);
             $logo_path = $logo_dir . '/' . $filename;
             
             error_log('Saving logo to: ' . $logo_path);
             
             // Move uploaded file
             if (move_uploaded_file($logo_file['tmp_name'], $logo_path)) {
                 $logo_url = $upload_dir['baseurl'] . '/cllf-uploads/' . $filename;
                 error_log('Logo saved successfully at: ' . $logo_url);
             } else {
                 error_log('Failed to move uploaded file from ' . $logo_file['tmp_name'] . ' to ' . $logo_path);
                 wp_send_json_error('Failed to save logo file');
                 exit;
             }
         } else {
             error_log('Logo required but no file uploaded and no stored logo available');
             wp_send_json_error('Please upload a logo file');
             exit;
         }
     }
     
     // Handle font choice
     $font_choice = isset($_POST['font_choice']) ? sanitize_text_field($_POST['font_choice']) : 'default';
     
     // Handle custom font upload if applicable
     $custom_font_url = '';
     $custom_font_name = '';
     
     if ($font_choice === 'new' && isset($_FILES['custom_font']) && $_FILES['custom_font']['error'] !== UPLOAD_ERR_NO_FILE) {
         error_log('Processing custom font upload');
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
             error_log('Font upload failed with error code: ' . $font_file['error']);
             wp_send_json_error('Font upload failed with error code: ' . $font_file['error']);
             exit;
         }
         
         // Validate file type
         $allowed_extensions = array('.ttf', '.otf', '.woff', '.woff2', '.eot', '.ps');
         $file_extension = strtolower(pathinfo($font_file['name'], PATHINFO_EXTENSION));
         
         if (!in_array('.' . $file_extension, $allowed_extensions)) {
             error_log('Invalid font file type: ' . $file_extension);
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
             error_log('Font saved successfully at: ' . $custom_font_url);
         } else {
             error_log('Failed to save font file');
             wp_send_json_error('Failed to save font file');
             exit;
         }
     }
     
     // Calculate total number of loops
     $tag_count = ($tag_info_type === 'Numbers') ? count($tag_numbers) : count($tag_names);
     $total_loops = ($tag_count * $num_sets) + $add_blanks;
     
     error_log('Calculated totals:');
     error_log('- Tag Count: ' . $tag_count);
     error_log('- Total Loops: ' . $total_loops);
     
     // Create a unique identifier for this submission
     $submission_id = uniqid('cllf_', true);
     error_log('Generated submission ID: ' . $submission_id);
     
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
     
     error_log('Created submission data: ' . print_r($submission_data, true));
     
     // Try to save to WooCommerce session
     try {
         // Get existing submissions or create new array
         $all_submissions = WC()->session->get('cllf_all_submissions', array());
         error_log('Retrieved existing submissions from session: ' . count($all_submissions) . ' items');
         
         // Add new submission
         $all_submissions[$submission_id] = $submission_data;
         
         // Save all submissions
         WC()->session->set('cllf_all_submissions', $all_submissions);
         error_log('Saved submissions to session: ' . count($all_submissions) . ' items');
         
         // Also save individual session keys for backward compatibility
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
         
         error_log('Saved individual session keys for backward compatibility');
         
         // Store the submission ID in the current session for processing
         WC()->session->set('cllf_current_submission_id', $submission_id);
         
         // Verify the data was actually saved
         $verification = WC()->session->get('cllf_all_submissions', array());
         if (isset($verification[$submission_id])) {
             error_log('SUCCESS: Data verification passed - submission was saved to session');
         } else {
             error_log('ERROR: Data verification failed - submission was NOT saved to session');
             wp_send_json_error('Failed to save form data to session');
             exit;
         }
         
     } catch (Exception $e) {
         error_log('ERROR: Exception while saving to session: ' . $e->getMessage());
         wp_send_json_error('Failed to save form data: ' . $e->getMessage());
         exit;
     }
     
     // Process the order and add to cart
     try {
         error_log('Processing order and adding to cart...');
         $cart_url = cllf_process_order_and_add_to_cart($loop_color, $sock_clips, $total_loops);
         error_log('SUCCESS: Order processed and added to cart. Cart URL: ' . $cart_url);
     } catch (Exception $e) {
         error_log('ERROR: Exception while processing order: ' . $e->getMessage());
         wp_send_json_error('Failed to add to cart: ' . $e->getMessage());
         exit;
     }
     
     error_log('=== CLLF FORM SUBMISSION DEBUG END - SUCCESS ===');
     
     // Return success
     wp_send_json_success(array(
         'cart_url' => $cart_url, 
         'message' => 'Your custom loops have been added to the cart!',
         'debug_submission_id' => $submission_id
     ));
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
            // Add submission ID and data to cart item
            $cart_item_data = array(
                'cllf_submission_id' => $submission_id,
                'cllf_submission_data' => $submission_data
            );
            WC()->cart->add_to_cart((int)$product_id, $total_loops, 0, array(), $cart_item_data);
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
            // Add submission ID and data to cart item
            $cart_item_data = array(
                'cllf_submission_id' => $submission_id,
                'cllf_submission_data' => $submission_data
            );
            WC()->cart->add_to_cart((int)$new_product_id, $total_loops, 0, array(), $cart_item_data);
        } else {
            error_log("Failed to create new product with SKU: $sku");
            // Handle the error - perhaps show a message to the user
        }
    }
    
    // Get sublimation product IDs
    $sublimation_product_1 = isset($clloi_settings['clloi_sublimation_product_1']) ? $clloi_settings['clloi_sublimation_product_1'] : 49360;
    $sublimation_product_2 = isset($clloi_settings['clloi_sublimation_product_2']) ? $clloi_settings['clloi_sublimation_product_2'] : 49361;
    $sublimation_product_3 = isset($clloi_settings['clloi_sublimation_product_3']) ? $clloi_settings['clloi_sublimation_product_3'] : 49359;
    
    // RADICAL NEW APPROACH: Temporarily disable all hooks
    
    // 1. Save the current hooks
    global $wp_filter;
    $original_filters = $wp_filter;
    
    // 2. Create a new empty filter array (effectively disabling all hooks)
    $wp_filter = array();
    
    // 2.5. Ensure cart is properly refreshed after adding the new product
    WC()->cart->calculate_totals();
    
    // 3. Count all loops in cart (including what we just added)
    $all_loops = 0;
    $setup_fee_exists = false;
    $setup_fee_cart_key = null;
    $per_loop_fee_cart_items = array();
    $custom_loop_items_found = array();
    
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $product = $cart_item['data'];
        
        // Count all custom loops
        if ($product->get_sku() && strpos($product->get_sku(), 'CL-') === 0) {
            $all_loops += $cart_item['quantity'];
            
            // Debug: track what we found
            $submission_id = isset($cart_item['cllf_submission_id']) ? $cart_item['cllf_submission_id'] : 'No ID';
            $custom_loop_items_found[] = array(
                'sku' => $product->get_sku(),
                'name' => $product->get_name(),
                'quantity' => $cart_item['quantity'],
                'submission_id' => $submission_id
            );
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
    
    // Debug: Log what we found
    error_log('=== CART LOOP CALCULATION DEBUG ===');
    error_log('Total loops calculated: ' . $all_loops);
    error_log('Custom loop items found: ' . print_r($custom_loop_items_found, true));
    error_log('Setup fee exists: ' . ($setup_fee_exists ? 'YES' : 'NO'));
    error_log('Per-loop fee items to remove: ' . count($per_loop_fee_cart_items));
    
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
/*function cllf_save_form_data_to_order($order_id) {
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
}*/

/**
 * DIAGNOSTIC VERSION of the order save function
 * Replace your existing cllf_save_form_data_to_order function with this temporarily
 */
function cllf_save_form_data_to_order($order_id) {
     error_log('=== CLLF SAVE TO ORDER DEBUG START ===');
     error_log('Order ID: ' . $order_id);
     
     if (!function_exists('WC') || !WC()->session) {
         error_log('ERROR: WooCommerce session not available in save function');
         return;
     }
     
     // Get all submissions from session
     $all_submissions = WC()->session->get('cllf_all_submissions', array());
     error_log('Retrieved submissions from session: ' . count($all_submissions) . ' items');
     
     if (!empty($all_submissions)) {
         error_log('Saving all submissions to order meta');
         
         // Validate and clean logo URLs in submissions before saving
         foreach ($all_submissions as $submission_id => &$submission) {
             if (isset($submission['logo_url']) && !empty($submission['logo_url'])) {
                 // Check if this is a placeholder value and try to get the real URL
                 if ($submission['logo_url'] === 'logo_processed' || $submission['logo_url'] === 'using_previous_logo') {
                     // Try to get the actual URL from session
                     $actual_logo_url = WC()->session->get('cllf_logo_url');
                     if ($actual_logo_url && $actual_logo_url !== 'logo_processed' && $actual_logo_url !== 'using_previous_logo') {
                         $submission['logo_url'] = $actual_logo_url;
                         error_log('Fixed logo URL from session for submission ' . $submission_id . ': ' . $actual_logo_url);
                     } else {
                         error_log('WARNING: Could not resolve logo URL for submission ' . $submission_id);
                         // Leave the placeholder value for now, but log it
                     }
                 }
                 
                 error_log('Logo URL for submission ' . $submission_id . ': ' . $submission['logo_url']);
             }
         }
         
         // Save all submissions as order meta
         $save_result = update_post_meta($order_id, '_cllf_all_submissions', $all_submissions);
         error_log('Save result: ' . ($save_result ? 'SUCCESS' : 'FAILED'));
         
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
                 
                 error_log('Saved submission data to order item ' . $item_id . ' with submission ID: ' . $submission_id);
                 
                 $submission_index++;
             }
         }
         
         // Verify the save worked
         $verification = get_post_meta($order_id, '_cllf_all_submissions', true);
         if ($verification && count($verification) === count($all_submissions)) {
             error_log('SUCCESS: Verification passed - data was saved to order');
             error_log('Saved submission IDs: ' . implode(', ', array_keys($verification)));
         } else {
             error_log('ERROR: Verification failed - data was NOT saved properly to order');
             error_log('Expected: ' . count($all_submissions) . ' submissions, Got: ' . (is_array($verification) ? count($verification) : 'not an array'));
             if (is_array($verification)) {
                 error_log('Actually saved submission IDs: ' . implode(', ', array_keys($verification)));
             }
         }
         
         // Clear session data
         WC()->session->set('cllf_all_submissions', array());
         error_log('Cleared session data');
         
     } else {
         error_log('WARNING: No submissions found in session to save');
         
         // Check for legacy data as backup
         $loop_color = WC()->session->get('cllf_loop_color');
         if ($loop_color) {
             error_log('Found legacy session data, saving as backup');
             
             // Get the logo URL properly from session
             $logo_url = WC()->session->get('cllf_logo_url');
             if ($logo_url && ($logo_url === 'logo_processed' || $logo_url === 'using_previous_logo')) {
                 error_log('WARNING: Legacy logo URL is placeholder: ' . $logo_url);
                 // You might want to try to resolve this or set it to empty
                 $logo_url = ''; // Clear invalid placeholder
             }
             
             update_post_meta($order_id, '_cllf_loop_color', sanitize_text_field($loop_color));
             update_post_meta($order_id, '_cllf_sock_clips', sanitize_text_field(WC()->session->get('cllf_sock_clips')));
             update_post_meta($order_id, '_cllf_has_logo', sanitize_text_field(WC()->session->get('cllf_has_logo')));
             update_post_meta($order_id, '_cllf_logo_url', sanitize_text_field($logo_url));
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
         } else {
             error_log('ERROR: No form data found in session at all!');
         }
     }
     
     // Clear all individual session data
     $session_keys = array(
         'cllf_loop_color', 'cllf_sock_clips', 'cllf_has_logo', 'cllf_logo_url',
         'cllf_sport_word', 'cllf_tag_info_type', 'cllf_tag_numbers', 'cllf_tag_names',
         'cllf_add_blanks', 'cllf_num_sets', 'cllf_total_loops', 'cllf_text_color',
         'cllf_order_notes', 'cllf_custom_font_url', 'cllf_custom_font_name', 'cllf_font_choice',
         'cllf_current_submission_id'
     );
     
     foreach ($session_keys as $key) {
         WC()->session->__unset($key);
     }
     
     error_log('=== CLLF SAVE TO ORDER DEBUG END ===');
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
 * Updated admin display to show ALL custom loop products in an order with better formatting
 * This function replaces the existing cllf_display_form_data_in_admin function
 */
function cllf_display_form_data_in_admin($order) {
    $order_id = $order->get_id();
    
    // Check if the order contains any custom loop products first
    $has_custom_loops = false;
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if ($product && $product->get_sku() && strpos($product->get_sku(), 'CL-') === 0) {
            $has_custom_loops = true;
            break;
        }
    }
    
    // If no custom loops, don't display anything
    if (!$has_custom_loops) {
        return;
    }
    
    // Get all submissions data
    $all_submissions = get_post_meta($order_id, '_cllf_all_submissions', true);
    
    // Add CSS for better styling
    echo '<style>
        .cllf-admin-container {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 20px 0;
            overflow: hidden;
        }
        .cllf-admin-header {
            background: #0073aa;
            color: #fff;
            padding: 15px 20px;
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }
        .cllf-product-card {
            border-bottom: 1px solid #eee;
            margin: 0;
        }
        .cllf-product-card:last-child {
            border-bottom: none;
        }
        .cllf-product-header {
            background: #f8f9fa;
            padding: 12px 20px;
            border-bottom: 1px solid #eee;
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            color: #23282d;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .cllf-product-timestamp {
            font-size: 12px;
            color: #666;
            font-weight: normal;
        }
        .cllf-product-content {
            padding: 20px;
        }
        .cllf-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }
        .cllf-detail-item {
            margin-bottom: 12px;
        }
        .cllf-detail-label {
            font-weight: 600;
            color: #23282d;
            margin-bottom: 4px;
        }
        .cllf-detail-value {
            color: #555;
            font-size: 14px;
        }
        .cllf-color-swatch {
            display: inline-block;
            width: 18px;
            height: 18px;
            border-radius: 3px;
            border: 1px solid #ddd;
            margin-left: 8px;
            vertical-align: middle;
        }
        .cllf-names-list {
            background: #fafafa;
            border: 1px solid #e1e1e1;
            border-radius: 4px;
            padding: 12px;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 8px;
        }
        .cllf-names-list ol {
            margin: 0;
            padding-left: 20px;
        }
        .cllf-names-list li {
            padding: 2px 0;
            font-family: monospace;
            font-size: 13px;
        }
        .cllf-font-info, .cllf-notes-section {
            grid-column: 1 / -1;
            margin-top: 10px;
            padding: 12px;
            background: #f0f6fc;
            border-left: 4px solid #0969da;
            border-radius: 0 4px 4px 0;
        }
        .cllf-notes-section {
            background: #fffbdd;
            border-left-color: #d1a441;
        }
        .cllf-badge {
            display: inline-block;
            padding: 2px 8px;
            background: #e7f3ff;
            color: #0969da;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            margin-left: 10px;
        }
        .cllf-admin-container .notice {
            margin: 0;
            border-radius: 0;
        }
        @media (max-width: 768px) {
            .cllf-details-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            .cllf-product-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .cllf-product-timestamp {
                margin-top: 5px;
            }
        }
    </style>';
    
    // If we have multiple submissions, display them all
    if (!empty($all_submissions) && is_array($all_submissions)) {
        ?>
        <div class="cllf-admin-container">
            <h3 class="cllf-admin-header">
                🎯 Custom Laundry Loops Order Details
                <span class="cllf-badge"><?php echo count($all_submissions); ?> Products</span>
            </h3>
            
            <?php
            $product_number = 1;
            foreach ($all_submissions as $submission_id => $submission) {
                ?>
                <div class="cllf-product-card">
                    <div class="cllf-product-header">
                        <span>Custom Loop Product #<?php echo $product_number; ?></span>
                        <span class="cllf-product-timestamp">
                            Added: <?php echo date('M j, Y g:i A', strtotime($submission['timestamp'])); ?>
                        </span>
                    </div>
                    
                    <div class="cllf-product-content">
                        <div class="cllf-details-grid">
                            <div>
                                <div class="cllf-detail-item">
                                    <div class="cllf-detail-label">Loop Color</div>
                                    <div class="cllf-detail-value"><?php echo esc_html($submission['loop_color']); ?></div>
                                </div>
                                
                                <div class="cllf-detail-item">
                                    <div class="cllf-detail-label">Sock Clips</div>
                                    <div class="cllf-detail-value"><?php echo esc_html($submission['sock_clips']); ?></div>
                                </div>
                                
                                <div class="cllf-detail-item">
                                    <div class="cllf-detail-label">Logo</div>
                                    <div class="cllf-detail-value">
                                        <?php echo esc_html($submission['has_logo']); ?>
                                        <?php if ($submission['has_logo'] === 'Yes' && !empty($submission['logo_url'])) : ?>
                                            <br><a href="<?php echo esc_url($submission['logo_url']); ?>" target="_blank" class="button button-small" style="margin-top: 5px;">📎 View Logo File</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($submission['sport_word'])) : ?>
                                <div class="cllf-detail-item">
                                    <div class="cllf-detail-label">Sport/Word on Strap</div>
                                    <div class="cllf-detail-value"><strong>"<?php echo esc_html($submission['sport_word']); ?>"</strong></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($submission['text_color'])) : ?>
                                <div class="cllf-detail-item">
                                    <div class="cllf-detail-label">Text Color</div>
                                    <div class="cllf-detail-value">
                                        <?php echo esc_html($submission['text_color']); ?>
                                        <span class="cllf-color-swatch" style="background-color: <?php echo esc_attr($submission['text_color']); ?>;"></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <div class="cllf-detail-item">
                                    <div class="cllf-detail-label">Tag Information Type</div>
                                    <div class="cllf-detail-value"><?php echo esc_html($submission['tag_info_type']); ?></div>
                                </div>
                                
                                <?php if ($submission['tag_info_type'] === 'Numbers') : ?>
                                    <div class="cllf-detail-item">
                                        <div class="cllf-detail-label">Selected Numbers</div>
                                        <div class="cllf-detail-value">
                                            <?php 
                                            if (is_array($submission['tag_numbers'])) {
                                                echo implode(', ', $submission['tag_numbers']);
                                            }
                                            ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="cllf-detail-item">
                                    <div class="cllf-detail-label">Additional Blanks</div>
                                    <div class="cllf-detail-value"><?php echo esc_html($submission['add_blanks']); ?></div>
                                </div>
                                
                                <div class="cllf-detail-item">
                                    <div class="cllf-detail-label">Number of Sets</div>
                                    <div class="cllf-detail-value"><?php echo esc_html($submission['num_sets']); ?></div>
                                </div>
                                
                                <div class="cllf-detail-item">
                                    <div class="cllf-detail-label">Total Loops</div>
                                    <div class="cllf-detail-value"><strong><?php echo esc_html($submission['total_loops']); ?></strong></div>
                                </div>
                            </div>
                            
                            <?php if ($submission['tag_info_type'] === 'Names' && !empty($submission['tag_names'])) : ?>
                                <div class="cllf-detail-item" style="grid-column: 1 / -1;">
                                    <div class="cllf-detail-label">
                                        Names List (<?php echo count($submission['tag_names']); ?> total)
                                    </div>
                                    <div class="cllf-names-list">
                                        <ol>
                                            <?php foreach ($submission['tag_names'] as $name) : ?>
                                                <li><?php echo esc_html($name); ?></li>
                                            <?php endforeach; ?>
                                        </ol>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($submission['font_choice'])) : ?>
                                <div class="cllf-font-info">
                                    <div class="cllf-detail-label">🔤 Font Information</div>
                                    <div class="cllf-detail-value">
                                        <?php
                                        switch ($submission['font_choice']) {
                                            case 'default':
                                                echo 'Using default fonts (Jersey M54 for numbers, Arial Black for text)';
                                                break;
                                            case 'previous':
                                                echo 'Using previously provided custom font';
                                                break;
                                            case 'new':
                                                echo 'New custom font uploaded';
                                                if (!empty($submission['custom_font_url'])) {
                                                    echo '<br><a href="' . esc_url($submission['custom_font_url']) . '" target="_blank" class="button button-small" style="margin-top: 5px;">📁 Download Font: ' . esc_html($submission['custom_font_name']) . '</a>';
                                                }
                                                break;
                                        }
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($submission['order_notes'])) : ?>
                                <div class="cllf-notes-section">
                                    <div class="cllf-detail-label">📝 Customer Notes</div>
                                    <div class="cllf-detail-value">
                                        <?php echo nl2br(esc_html($submission['order_notes'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php
                $product_number++;
            }
            ?>
        </div>
        <?php
    } else {
        // Fallback to single product display for backward compatibility
        $loop_color = get_post_meta($order_id, '_cllf_loop_color', true);
        
        if ($loop_color) {
            // Get all the legacy data
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
            $order_notes = get_post_meta($order_id, '_cllf_order_notes', true);
            $text_color = get_post_meta($order_id, '_cllf_text_color', true);
            $font_choice = get_post_meta($order_id, '_cllf_font_choice', true);
            $custom_font_url = get_post_meta($order_id, '_cllf_custom_font_url', true);
            $custom_font_name = get_post_meta($order_id, '_cllf_custom_font_name', true);
            ?>
            <div class="cllf-admin-container">
                <h3 class="cllf-admin-header">
                    🎯 Custom Laundry Loops Order Details
                    <span class="cllf-badge">Legacy Format</span>
                </h3>
                
                <div class="cllf-product-card">
                    <div class="cllf-product-content">
                        <div class="cllf-details-grid">
                            <div>
                                <div class="cllf-detail-item">
                                    <div class="cllf-detail-label">Loop Color</div>
                                    <div class="cllf-detail-value"><?php echo esc_html($loop_color); ?></div>
                                </div>
                                
                                <div class="cllf-detail-item">
                                    <div class="cllf-detail-label">Sock Clips</div>
                                    <div class="cllf-detail-value"><?php echo esc_html($sock_clips); ?></div>
                                </div>
                                
                                <div class="cllf-detail-item">
                                    <div class="cllf-detail-label">Logo</div>
                                    <div class="cllf-detail-value">
                                        <?php echo esc_html($has_logo); ?>
                                        <?php if ($has_logo === 'Yes' && !empty($logo_url)) : ?>
                                            <br><a href="<?php echo esc_url($logo_url); ?>" target="_blank" class="button button-small" style="margin-top: 5px;">📎 View Logo File</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($sport_word)) : ?>
                                <div class="cllf-detail-item">
                                    <div class="cllf-detail-label">Sport/Word on Strap</div>
                                    <div class="cllf-detail-value"><strong>"<?php echo esc_html($sport_word); ?>"</strong></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($text_color)) : ?>
                                <div class="cllf-detail-item">
                                    <div class="cllf-detail-label">Text Color</div>
                                    <div class="cllf-detail-value">
                                        <?php echo esc_html($text_color); ?>
                                        <span class="cllf-color-swatch" style="background-color: <?php echo esc_attr($text_color); ?>;"></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <div class="cllf-detail-item">
                                    <div class="cllf-detail-label">Tag Information Type</div>
                                    <div class="cllf-detail-value"><?php echo esc_html($tag_info_type); ?></div>
                                </div>
                                
                                <?php if ($tag_info_type === 'Numbers' && !empty($tag_numbers)) : ?>
                                    <div class="cllf-detail-item">
                                        <div class="cllf-detail-label">Selected Numbers</div>
                                        <div class="cllf-detail-value">
                                            <?php echo implode(', ', array_map('esc_html', $tag_numbers)); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="cllf-detail-item">
                                    <div class="cllf-detail-label">Additional Blanks</div>
                                    <div class="cllf-detail-value"><?php echo esc_html($add_blanks); ?></div>
                                </div>
                                
                                <div class="cllf-detail-item">
                                    <div class="cllf-detail-label">Number of Sets</div>
                                    <div class="cllf-detail-value"><?php echo esc_html($num_sets); ?></div>
                                </div>
                                
                                <div class="cllf-detail-item">
                                    <div class="cllf-detail-label">Total Loops</div>
                                    <div class="cllf-detail-value"><strong><?php echo esc_html($total_loops); ?></strong></div>
                                </div>
                            </div>
                            
                            <?php if ($tag_info_type === 'Names' && !empty($tag_names)) : ?>
                                <div class="cllf-detail-item" style="grid-column: 1 / -1;">
                                    <div class="cllf-detail-label">
                                        Names List (<?php echo count($tag_names); ?> total)
                                    </div>
                                    <div class="cllf-names-list">
                                        <ol>
                                            <?php foreach ($tag_names as $name) : ?>
                                                <li><?php echo esc_html($name); ?></li>
                                            <?php endforeach; ?>
                                        </ol>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($font_choice)) : ?>
                                <div class="cllf-font-info">
                                    <div class="cllf-detail-label">🔤 Font Information</div>
                                    <div class="cllf-detail-value">
                                        <?php
                                        switch ($font_choice) {
                                            case 'default':
                                                echo 'Using default fonts (Jersey M54 for numbers, Arial Black for text)';
                                                break;
                                            case 'previous':
                                                echo 'Using previously provided custom font';
                                                break;
                                            case 'new':
                                                echo 'New custom font uploaded';
                                                if (!empty($custom_font_url)) {
                                                    echo '<br><a href="' . esc_url($custom_font_url) . '" target="_blank" class="button button-small" style="margin-top: 5px;">📁 Download Font: ' . esc_html($custom_font_name) . '</a>';
                                                }
                                                break;
                                        }
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($order_notes)) : ?>
                                <div class="cllf-notes-section">
                                    <div class="cllf-detail-label">📝 Customer Notes</div>
                                    <div class="cllf-detail-value">
                                        <?php echo nl2br(esc_html($order_notes)); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
    }
}

// Send admin notification email when order is placed
add_action('woocommerce_thankyou', 'cllf_send_admin_notification', 20, 1);

/**
 * Updated admin email notification to include ALL custom loop products
 * This function replaces the existing cllf_send_admin_notification function
 */
function cllf_send_admin_notification($order_id) {
    error_log('=== EMAIL NOTIFICATION DEBUG START ===');
    error_log('Order ID: ' . $order_id);
    
    // Prevent duplicate emails by checking if we've already sent for this order
    if (get_post_meta($order_id, '_cllf_admin_email_sent', true)) {
        error_log('Email already sent for order ' . $order_id . ', skipping duplicate');
        return;
    }
    
    $order = wc_get_order($order_id);
    
    // Check if the order contains any custom loop products
    $has_custom_loops = false;
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if ($product && $product->get_sku() && strpos($product->get_sku(), 'CL-') === 0) {
            $has_custom_loops = true;
            break;
        }
    }
    
    error_log('Has custom loops: ' . ($has_custom_loops ? 'YES' : 'NO'));
    
    // Only proceed if there are custom loop products in the order
    if (!$has_custom_loops) {
        error_log('No custom loops found, exiting email function');
        return;
    }
    
    // Get all submissions
    $all_submissions = get_post_meta($order_id, '_cllf_all_submissions', true);
    error_log('Retrieved all_submissions from order meta: ' . (is_array($all_submissions) ? count($all_submissions) . ' submissions' : 'NOT AN ARRAY or EMPTY'));
    
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
        
        // Add information about other products in the order (non-custom loops)
        $other_products = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && (!$product->get_sku() || strpos($product->get_sku(), 'CL-') !== 0)) {
                // This is not a custom loop product
                $other_products[] = array(
                    'name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'sku' => $product->get_sku() ?: 'No SKU'
                );
            }
        }
        
        if (!empty($other_products)) {
            $message .= "<hr style='margin: 20px 0; border: none; border-top: 1px solid #ddd;'>";
            $message .= "<h2>Other Products in This Order:</h2>";
            $message .= "<div style='margin: 20px 0; padding: 20px; background-color: #f0f8ff; border-left: 4px solid #007cba;'>";
            $message .= "<ul>";
            foreach ($other_products as $product) {
                $message .= "<li><strong>" . esc_html($product['name']) . "</strong> - Qty: " . $product['quantity'] . " (SKU: " . esc_html($product['sku']) . ")</li>";
            }
            $message .= "</ul>";
            $message .= "</div>";
        }
        
        $message .= "<hr style='margin: 20px 0; border: none; border-top: 1px solid #ddd;'>";
        $message .= "<p style='font-size: 12px; color: #666;'>This is an automated notification from the Custom Laundry Loops Form plugin.</p>";
        
        // Send the email - removed the page condition that was causing the issue
        wp_mail($to, $subject, $message, $headers);
        error_log('Sent multi-submission format email with ' . count($all_submissions) . ' products');
        
        // Mark email as sent to prevent duplicates
        update_post_meta($order_id, '_cllf_admin_email_sent', true);
        
    } else {
        error_log('No all_submissions data found, checking for legacy data');
        // Fallback to original single product email for backward compatibility
        $loop_color = get_post_meta($order_id, '_cllf_loop_color', true);
        error_log('Legacy loop_color: ' . ($loop_color ? $loop_color : 'EMPTY'));
        if ($loop_color) {
            // Build the fallback email for legacy single-product orders
            $headers[] = "From: Texon Athletic Towel <sales@texontowel.com>" . "\r\n";
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $to = array('ryan@texontowel.com', 'stephanie@texontowel.com', 'jen@texontowel.com', 'wmk@texontowel.com', 'jessica@texontowel.com');
            $subject = 'New Custom Loop Order - #' . $order_id;
            
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
            $order_notes = get_post_meta($order_id, '_cllf_order_notes', true);
            $text_color = get_post_meta($order_id, '_cllf_text_color', true);
            
            $message = "<p>A new Custom Loop Order has been placed on TexonTowel.com!</p>
            <p>Order #" . $order_id . " was placed by " . $order->get_formatted_billing_full_name() . " from " . $order->get_billing_company() . "</p>
            <p>This order will be shipping to:<br>" . $order->get_formatted_shipping_address() . "</p>
            <p>You may view this order here: " . $order->get_edit_order_url() . "</p>
            <h2>Custom Loop Details:</h2>
            <ul>
                <li><strong>Loop Color:</strong> " . $loop_color . "</li>
                <li><strong>Sock Clips:</strong> " . $sock_clips . "</li>
                <li><strong>Has Logo:</strong> " . $has_logo . "</li>";
            
            if ($has_logo === 'Yes' && !empty($logo_url)) {
                $message .= "<li><strong>Logo File:</strong> <a href='" . esc_url($logo_url) . "'>View Logo</a></li>";
            }
            
            if (!empty($sport_word)) {
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
            
            if (!empty($text_color)) {
                $message .= "<li><strong>Text Color:</strong> " . $text_color . "</li>";
            }
            
            if (!empty($order_notes)) {
                $message .= "<li><strong>Order Notes:</strong> " . nl2br(esc_html($order_notes)) . "</li>";
            }
            
            $message .= "</ul>";
            
            // Add information about other products in the order (non-custom loops)
            $other_products = array();
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if ($product && (!$product->get_sku() || strpos($product->get_sku(), 'CL-') !== 0)) {
                    // This is not a custom loop product
                    $other_products[] = array(
                        'name' => $item->get_name(),
                        'quantity' => $item->get_quantity(),
                        'sku' => $product->get_sku() ?: 'No SKU'
                    );
                }
            }
            
            if (!empty($other_products)) {
                $message .= "<hr style='margin: 20px 0; border: none; border-top: 1px solid #ddd;'>";
                $message .= "<h2>Other Products in This Order:</h2>";
                $message .= "<ul>";
                foreach ($other_products as $product) {
                    $message .= "<li><strong>" . esc_html($product['name']) . "</strong> - Qty: " . $product['quantity'] . " (SKU: " . esc_html($product['sku']) . ")</li>";
                }
                $message .= "</ul>";
            }
            
            $message .= "<hr style='margin: 20px 0; border: none; border-top: 1px solid #ddd;'>";
            $message .= "<p style='font-size: 12px; color: #666;'>This is an automated notification from the Custom Laundry Loops Form plugin.</p>";
            
            // Send the email - removed the page condition that was causing the issue
            wp_mail($to, $subject, $message, $headers);
            error_log('Sent legacy format email');
            
            // Mark email as sent to prevent duplicates
            update_post_meta($order_id, '_cllf_admin_email_sent', true);
        } else {
            error_log('ERROR: No legacy data found either!');
        }
    }
    
    error_log('=== EMAIL NOTIFICATION DEBUG END ===');
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

// Restrict custom loop payment gateway based on cart contents
// - If cart contains custom loops: only show custom loop gateway
// - If cart contains NO custom loops: hide custom loop gateway
add_filter('woocommerce_available_payment_gateways', 'cllf_custom_loop_available_payment_gateways');
function cllf_custom_loop_available_payment_gateways($available_gateways) {
    if (!is_admin()) {
        if (cllf_has_custom_loops_in_cart()) {
            // If cart has custom loops, only make our custom gateway available
            if (isset($available_gateways['cllf_custom_loop_gateway'])) {
                return array('cllf_custom_loop_gateway' => $available_gateways['cllf_custom_loop_gateway']);
            }
        } else {
            // If cart has NO custom loops, remove our custom gateway from available options
            if (isset($available_gateways['cllf_custom_loop_gateway'])) {
                unset($available_gateways['cllf_custom_loop_gateway']);
            }
        }
    }
    
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

// Note: Removed automatic readme generation functionality as it was not working correctly

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
 * Helper function to check if debug mode is enabled
 */
function cllf_is_debug_mode_enabled() {
    $settings = get_option('clloi_settings', array());
    return isset($settings['cllf_debug_mode']) && $settings['cllf_debug_mode'] == 1;
}

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
    
    // Get current session submissions
    $current_session_submissions = WC()->session->get('cllf_all_submissions', array());
    
    // Count all custom loops actually in the cart (including from previous sessions)
    $total_loops_in_cart = 0;
    $custom_loop_products_in_cart = 0;
    $cart_submissions = array();
    
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $product = $cart_item['data'];
        if ($product->get_sku() && strpos($product->get_sku(), 'CL-') === 0) {
            $total_loops_in_cart += $cart_item['quantity'];
            $custom_loop_products_in_cart++;
            
            // Track submission IDs from cart items
            $submission_id = isset($cart_item['cllf_submission_id']) ? $cart_item['cllf_submission_id'] : 'No ID';
            $cart_submissions[] = array(
                'product_name' => $product->get_name(),
                'quantity' => $cart_item['quantity'],
                'submission_id' => $submission_id,
                'source' => $submission_id && isset($current_session_submissions[$submission_id]) ? 'Current Session' : 'Previous Session'
            );
        }
    }
    
    // Show debug info if there are custom loops in cart OR current session
    if ($total_loops_in_cart > 0 || !empty($current_session_submissions)) {
        ?>
        <div style="background-color: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 20px; border-radius: 4px; color: #155724;">
            <strong>🔍 Debug Summary (Admin Only):</strong><br>
            <strong>Cart:</strong> <?php echo $custom_loop_products_in_cart; ?> custom loop products, totaling <?php echo $total_loops_in_cart; ?> loops<br>
            <strong>Current Session:</strong> <?php echo count($current_session_submissions); ?> submissions
            
            <?php if (!empty($cart_submissions)): ?>
            <br><strong>Cart Breakdown:</strong>
            <ul style="margin: 5px 0 0 20px; font-size: 12px;">
                <?php foreach ($cart_submissions as $item): ?>
                    <li><?php echo esc_html($item['product_name']); ?> (Qty: <?php echo $item['quantity']; ?>, ID: <?php echo esc_html($item['submission_id']); ?>, <?php echo esc_html($item['source']); ?>)</li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
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

/**
 * Complete Migration System for Custom Loop Orders
 * Add this entire section to your custom-loop-form-plugin.php file
 */

/**
 * CORRECTED Migration function to convert legacy custom loop orders to new format
 */
function cllf_migrate_legacy_orders() {
    // Get orders from the past 30 days only for better performance
    $date_30_days_ago = date('Y-m-d', strtotime('-30 days'));
    $all_orders = wc_get_orders(array(
        'limit' => -1,
        'status' => array('completed', 'processing', 'on-hold', 'pending'),
        'date_created' => '>=' . $date_30_days_ago,
        'return' => 'objects'
    ));
    
    $eligible_orders = array();
    
    // Filter orders to only include those with actual custom loop products
    foreach ($all_orders as $order) {
        $order_id = $order->get_id();
        
        // Check if this order has custom loop products by examining the order items
        $has_custom_loops = false;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->get_sku() && strpos($product->get_sku(), 'CL-') === 0) {
                $has_custom_loops = true;
                break;
            }
        }
        
        // Only include orders that:
        // 1. Have custom loop products (SKU starts with CL-)
        // 2. Have some legacy custom loop meta data
        // 3. Don't already have the new format
        if ($has_custom_loops) {
            $has_legacy_data = get_post_meta($order_id, '_cllf_loop_color', true) || 
                              get_post_meta($order_id, '_cllf_sock_clips', true) ||
                              get_post_meta($order_id, '_cllf_tag_info_type', true);
            
            $has_new_format = get_post_meta($order_id, '_cllf_all_submissions', true);
            
            if ($has_legacy_data && !$has_new_format) {
                $eligible_orders[] = $order;
            }
        }
    }
    
    $migrated_count = 0;
    $error_count = 0;
    
    foreach ($eligible_orders as $order) {
        try {
            $order_id = $order->get_id();
            
            // Get legacy data
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
            $order_notes = get_post_meta($order_id, '_cllf_order_notes', true);
            $text_color = get_post_meta($order_id, '_cllf_text_color', true);
            $font_choice = get_post_meta($order_id, '_cllf_font_choice', true);
            $custom_font_url = get_post_meta($order_id, '_cllf_custom_font_url', true);
            $custom_font_name = get_post_meta($order_id, '_cllf_custom_font_name', true);
            
            // If we don't have essential data, try to derive it from the order
            if (empty($loop_color) || empty($sock_clips)) {
                // Try to extract info from custom loop products in the order
                foreach ($order->get_items() as $item) {
                    $product = $item->get_product();
                    if ($product && $product->get_sku() && strpos($product->get_sku(), 'CL-') === 0) {
                        $product_name = $product->get_name();
                        $sku = $product->get_sku();
                        
                        // Try to extract color from product name or SKU
                        if (empty($loop_color)) {
                            if (preg_match('/Color:\s*([^-]+)/i', $product_name, $matches)) {
                                $loop_color = trim($matches[1]);
                            }
                        }
                        
                        // Try to extract clip type from SKU or product name
                        if (empty($sock_clips)) {
                            if (strpos($sku, '-2') !== false || stripos($product_name, 'double') !== false) {
                                $sock_clips = 'Double';
                            } else {
                                $sock_clips = 'Single';
                            }
                        }
                        
                        break; // Just use the first custom loop product we find
                    }
                }
            }
            
            // Skip if we still don't have essential data
            if (empty($loop_color)) {
                error_log("Skipping order #{$order_id} - no loop color found");
                continue;
            }
            
            // Create new submission format
            $submission_id = 'legacy_' . $order_id . '_' . time();
            $submission_data = array(
                'submission_id' => $submission_id,
                'timestamp' => $order->get_date_created()->date('Y-m-d H:i:s'),
                'loop_color' => $loop_color ?: 'Black',
                'sock_clips' => $sock_clips ?: 'Single',
                'has_logo' => $has_logo ?: 'No',
                'logo_url' => $logo_url ?: '',
                'sport_word' => $sport_word ?: '',
                'tag_info_type' => $tag_info_type ?: 'Numbers',
                'tag_numbers' => is_array($tag_numbers) ? $tag_numbers : array(),
                'tag_names' => is_array($tag_names) ? $tag_names : array(),
                'add_blanks' => intval($add_blanks),
                'num_sets' => intval($num_sets) ?: 1,
                'total_loops' => intval($total_loops) ?: 0,
                'order_notes' => $order_notes ?: '',
                'text_color' => $text_color ?: '#000000',
                'font_choice' => $font_choice ?: 'default',
                'custom_font_url' => $custom_font_url ?: '',
                'custom_font_name' => $custom_font_name ?: ''
            );
            
            // If total_loops is 0, try to calculate it from order items
            if ($submission_data['total_loops'] == 0) {
                $total_loops_calculated = 0;
                foreach ($order->get_items() as $item) {
                    $product = $item->get_product();
                    if ($product && $product->get_sku() && strpos($product->get_sku(), 'CL-') === 0) {
                        $total_loops_calculated += $item->get_quantity();
                    }
                }
                $submission_data['total_loops'] = $total_loops_calculated;
            }
            
            // Create the all_submissions array
            $all_submissions = array(
                $submission_id => $submission_data
            );
            
            // Save the new format
            update_post_meta($order_id, '_cllf_all_submissions', $all_submissions);
            
            // Add a note to the order
            $order->add_order_note('Custom loop order data migrated to new display format.');
            
            $migrated_count++;
            
            error_log("Successfully migrated order #{$order_id}");
            
        } catch (Exception $e) {
            error_log('Error migrating order #' . $order_id . ': ' . $e->getMessage());
            $error_count++;
        }
    }
    
    return array(
        'migrated' => $migrated_count,
        'errors' => $error_count,
        'total_checked' => count($all_orders),
        'eligible_orders' => count($eligible_orders)
    );
}

/**
 * Register the migration admin menu page
 */
function cllf_add_migration_admin_page() {
    add_submenu_page(
        'tools.php',
        'Custom Loops Order Migration',
        'Migrate Custom Loops',
        'manage_options',
        'cllf-migrate-orders',
        'cllf_migration_admin_page'
    );
}
add_action('admin_menu', 'cllf_add_migration_admin_page');

/**
 * Migration admin page content
 */
function cllf_migration_admin_page() {
    // Handle migration request
    if (isset($_POST['run_migration']) && wp_verify_nonce($_POST['migration_nonce'], 'cllf_migration')) {
        set_time_limit(300); // Increase time limit for large migrations
        $results = cllf_migrate_legacy_orders();
        
        echo '<div class="notice notice-success"><p>';
        echo '<strong>Migration Complete!</strong><br>';
        echo 'Orders migrated: ' . $results['migrated'] . '<br>';
        echo 'Errors: ' . $results['errors'] . '<br>';
        echo 'Total orders checked: ' . $results['total_checked'] . '<br>';
        echo 'Eligible orders found: ' . $results['eligible_orders'];
        echo '</p></div>';
    }
    
    // Check how many orders need migration using the same logic (30 days only)
    $date_30_days_ago = date('Y-m-d', strtotime('-30 days'));
    $all_orders = wc_get_orders(array(
        'limit' => -1,
        'status' => array('completed', 'processing', 'on-hold', 'pending'),
        'date_created' => '>=' . $date_30_days_ago,
        'return' => 'objects'
    ));
    
    $orders_needing_migration = array();
    
    // Filter orders properly
    foreach ($all_orders as $order) {
        $order_id = $order->get_id();
        
        // Check if this order has custom loop products
        $has_custom_loops = false;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->get_sku() && strpos($product->get_sku(), 'CL-') === 0) {
                $has_custom_loops = true;
                break;
            }
        }
        
        if ($has_custom_loops) {
            $has_legacy_data = get_post_meta($order_id, '_cllf_loop_color', true) || 
                              get_post_meta($order_id, '_cllf_sock_clips', true) ||
                              get_post_meta($order_id, '_cllf_tag_info_type', true);
            
            $has_new_format = get_post_meta($order_id, '_cllf_all_submissions', true);
            
            if ($has_legacy_data && !$has_new_format) {
                $orders_needing_migration[] = $order;
            }
        }
    }
    
    $count = count($orders_needing_migration);
    
    ?>
    <div class="wrap">
        <h1>Custom Loops Order Migration</h1>
        
        <div class="card">
            <h2>Migration Status</h2>
            <p>This tool will migrate legacy custom loop orders to the new display format.</p>
            <p><em>Only orders with custom loop products (SKU starting with "CL-") from the past 30 days will be processed.</em></p>
            
            <?php if ($count > 0): ?>
                <div class="notice notice-warning">
                    <p><strong><?php echo $count; ?> custom loop orders</strong> need to be migrated to the new format.</p>
                </div>
                
                <h3>What this migration does:</h3>
                <ul>
                    <li>✅ Only processes orders containing custom loop products</li>
                    <li>✅ Converts legacy order data to the new structured format</li>
                    <li>✅ Improves the admin order display formatting</li>
                    <li>✅ Maintains all existing order information</li>
                    <li>✅ Adds order notes indicating migration was completed</li>
                    <li>✅ Does not modify any customer-facing information</li>
                </ul>
                
                <form method="post" style="margin-top: 20px;">
                    <?php wp_nonce_field('cllf_migration', 'migration_nonce'); ?>
                    <p>
                        <button type="submit" name="run_migration" class="button button-primary button-large" 
                                onclick="return confirm('Are you sure you want to migrate <?php echo $count; ?> custom loop orders? This action cannot be undone.');">
                            🔄 Migrate <?php echo $count; ?> Custom Loop Orders
                        </button>
                    </p>
                </form>
                
                <h3>Custom Loop Orders that will be migrated:</h3>
                <div style="max-height: 400px; overflow-y: auto; background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                    <?php foreach ($orders_needing_migration as $order): ?>
                        <?php 
                        $order_id = $order->get_id();
                        
                        // Count custom loop products in this order
                        $custom_loop_count = 0;
                        foreach ($order->get_items() as $item) {
                            $product = $item->get_product();
                            if ($product && $product->get_sku() && strpos($product->get_sku(), 'CL-') === 0) {
                                $custom_loop_count += $item->get_quantity();
                            }
                        }
                        ?>
                        <div style="margin: 10px 0; padding: 10px; background: white; border-left: 4px solid #0073aa;">
                            <strong>
                                <a href="<?php echo admin_url('post.php?post=' . $order_id . '&action=edit'); ?>" target="_blank">
                                    Order #<?php echo $order_id; ?>
                                </a>
                            </strong><br>
                            Customer: <?php echo $order->get_formatted_billing_full_name(); ?><br>
                            Date: <?php echo $order->get_date_created()->date('M j, Y'); ?><br>
                            Custom Loop Products: <?php echo $custom_loop_count; ?> units
                        </div>
                    <?php endforeach; ?>
                </div>
                
            <?php else: ?>
                <div class="notice notice-success">
                    <p><strong>All custom loop orders are already using the new format!</strong> No migration needed.</p>
                </div>
                
                <h3>Detection Summary:</h3>
                <p>Checked <?php echo count($all_orders); ?> orders from the past 30 days and found 0 that need migration.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            padding: 20px;
            margin-top: 20px;
        }
        .card h2 {
            margin-top: 0;
        }
    </style>
    <?php
}

/**
 * Add migration indicator to admin bar if needed
 */
function cllf_add_migration_admin_bar($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Quick check - just look at recent orders (past 30 days) to see if any need migration
    $date_30_days_ago = date('Y-m-d', strtotime('-30 days'));
    $recent_orders = wc_get_orders(array(
        'limit' => 100, // Limit for performance
        'status' => array('completed', 'processing', 'on-hold', 'pending'),
        'date_created' => '>=' . $date_30_days_ago,
        'return' => 'objects'
    ));
    
    $needs_migration = false;
    foreach ($recent_orders as $order) {
        $order_id = $order->get_id();
        
        // Check if this order has custom loop products
        $has_custom_loops = false;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->get_sku() && strpos($product->get_sku(), 'CL-') === 0) {
                $has_custom_loops = true;
                break;
            }
        }
        
        if ($has_custom_loops) {
            $has_legacy_data = get_post_meta($order_id, '_cllf_loop_color', true);
            $has_new_format = get_post_meta($order_id, '_cllf_all_submissions', true);
            
            if ($has_legacy_data && !$has_new_format) {
                $needs_migration = true;
                break;
            }
        }
    }
    
    if ($needs_migration) {
        $wp_admin_bar->add_node(array(
            'id' => 'cllf-migration',
            'title' => '🔄 Migrate Custom Loop Orders',
            'href' => admin_url('tools.php?page=cllf-migrate-orders'),
            'meta' => array(
                'title' => 'Some custom loop orders need to be migrated to the new format'
            )
        ));
    }
}
add_action('admin_bar_menu', 'cllf_add_migration_admin_bar', 999);

/**
 * Show migration notice on individual orders that need it
 */
function cllf_show_migration_notice($order) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $order_id = $order->get_id();
    
    // First check if this order has custom loop products
    $has_custom_loops = false;
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if ($product && $product->get_sku() && strpos($product->get_sku(), 'CL-') === 0) {
            $has_custom_loops = true;
            break;
        }
    }
    
    if (!$has_custom_loops) {
        return; // Not a custom loop order
    }
    
    // Check if this order has legacy data but no new format
    $has_legacy = get_post_meta($order_id, '_cllf_loop_color', true) || 
                  get_post_meta($order_id, '_cllf_sock_clips', true) ||
                  get_post_meta($order_id, '_cllf_tag_info_type', true);
    $has_new = get_post_meta($order_id, '_cllf_all_submissions', true);
    
    if ($has_legacy && !$has_new) {
        ?>
        <div class="notice notice-warning inline" style="margin: 15px 0; padding: 10px;">
            <p>
                <strong>⚠️ Legacy Custom Loop Order</strong><br>
                This custom loop order uses the old data format. 
                <a href="<?php echo admin_url('tools.php?page=cllf-migrate-orders'); ?>" class="button button-small">
                    Go to Migration Tool
                </a>
            </p>
        </div>
        <?php
    }
}
add_action('woocommerce_admin_order_data_after_order_details', 'cllf_show_migration_notice', 5);

/**
 * FIXED Manual migration for orders that have custom loop products but no form data
 * This version handles MULTIPLE custom loop products properly
 */

/**
 * AJAX handler to create missing form data - FIXED VERSION
 * Replace the existing cllf_create_missing_data_ajax function with this one
 */
function cllf_create_missing_data_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'cllf_create_missing_data')) {
        wp_send_json_error('Security check failed');
    }
    
    $order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);
    
    if (!$order) {
        wp_send_json_error('Order not found');
    }
    
    try {
        $all_submissions = array();
        $submission_counter = 1;
        
        // Process EACH custom loop product as a separate submission
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->get_sku() && strpos($product->get_sku(), 'CL-') === 0) {
                $product_name = $product->get_name();
                $sku = $product->get_sku();
                $quantity = $item->get_quantity();
                
                // Extract information for THIS specific product
                $loop_color = 'Black'; // Default
                $sock_clips = 'Single'; // Default
                $has_logo = 'No'; // Default
                $sport_word = '';
                
                // Extract color from product name - try multiple patterns
                if (preg_match('/Color:\s*([^-\n]+)/i', $product_name, $matches)) {
                    $loop_color = trim($matches[1]);
                } elseif (preg_match('/Clips\s*-\s*([^-\n]+)/i', $product_name, $matches)) {
                    // Pattern: "Double Sock Clips - Minty Green"
                    $loop_color = trim($matches[1]);
                } elseif (preg_match('/Straps[^-]*-\s*([^-\n]+)/i', $product_name, $matches)) {
                    // Pattern: "Laundry Straps - Color Name"
                    $loop_color = trim($matches[1]);
                }
                
                // Extract clip type from SKU or product name
                if (strpos($sku, '-2') !== false || stripos($product_name, 'double') !== false) {
                    $sock_clips = 'Double';
                } else {
                    $sock_clips = 'Single';
                }
                
                // Check for logo
                if (stripos($product_name, 'logo') !== false) {
                    $has_logo = 'Yes';
                }
                
                // Try to extract sport word from product name
                if (preg_match('/Text:\s*["\']([^"\']+)["\']|Text:\s*([^-\n]+)/i', $product_name, $matches)) {
                    $sport_word = trim($matches[1] ?: $matches[2]);
                } elseif (preg_match('/with\s+"([^"]+)"/i', $product_name, $matches)) {
                    $sport_word = trim($matches[1]);
                }
                
                // Create a unique submission ID for this product
                $submission_id = 'manual_' . $order_id . '_' . $submission_counter . '_' . time();
                
                // Create submission data for THIS product
                $submission_data = array(
                    'submission_id' => $submission_id,
                    'timestamp' => $order->get_date_created()->date('Y-m-d H:i:s'),
                    'loop_color' => $loop_color,
                    'sock_clips' => $sock_clips,
                    'has_logo' => $has_logo,
                    'logo_url' => '',
                    'sport_word' => $sport_word,
                    'tag_info_type' => 'Numbers', // Default to numbers since we can't determine this
                    'tag_numbers' => array(), // We can't extract the specific numbers
                    'tag_names' => array(),
                    'add_blanks' => 0,
                    'num_sets' => 1,
                    'total_loops' => $quantity, // This product's quantity only
                    'order_notes' => 'Form data was missing and reconstructed from product: ' . $product_name,
                    'text_color' => '#000000',
                    'font_choice' => 'default',
                    'custom_font_url' => '',
                    'custom_font_name' => ''
                );
                
                // Add this submission to the array
                $all_submissions[$submission_id] = $submission_data;
                
                $submission_counter++;
            }
        }
        
        if (empty($all_submissions)) {
            wp_send_json_error('No custom loop products found in this order');
        }
        
        // Save ALL the submissions
        update_post_meta($order_id, '_cllf_all_submissions', $all_submissions);
        
        // Add a note to the order
        $order->add_order_note('Missing custom loop form data was reconstructed for ' . count($all_submissions) . ' products.');
        
        wp_send_json_success(array(
            'message' => 'Form data created for ' . count($all_submissions) . ' custom loop products',
            'submissions_created' => count($all_submissions),
            'submission_data' => $all_submissions
        ));
        
    } catch (Exception $e) {
        error_log('Error creating missing form data for order #' . $order_id . ': ' . $e->getMessage());
        wp_send_json_error('Error: ' . $e->getMessage());
    }
}

/**
 * Helper function to clean up existing partial data if needed
 */
function cllf_reset_order_submissions($order_id) {
    delete_post_meta($order_id, '_cllf_all_submissions');
    
    // Also clean up any partial legacy data
    delete_post_meta($order_id, '_cllf_loop_color');
    delete_post_meta($order_id, '_cllf_sock_clips');
    delete_post_meta($order_id, '_cllf_has_logo');
    delete_post_meta($order_id, '_cllf_tag_info_type');
    // ... etc.
    
    return true;
}

/**
 * Add a "Reset and Recreate" button for orders that have partial data
 */
add_action('woocommerce_admin_order_data_after_order_details', 'cllf_reset_option', 15);

function cllf_reset_option($order) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $order_id = $order->get_id();
    
    // Check if this order has custom loop products
    $has_custom_loops = false;
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if ($product && $product->get_sku() && strpos($product->get_sku(), 'CL-') === 0) {
            $has_custom_loops = true;
            break;
        }
    }
    
    // Only show for custom loop orders that have existing submission data
    if ($has_custom_loops) {
        $all_submissions = get_post_meta($order_id, '_cllf_all_submissions', true);
        
        if ($all_submissions) {
            ?>
            <div class="notice notice-info inline" style="margin: 15px 0; padding: 10px;">
                <p>
                    <strong>🔄 Reset Custom Loop Data</strong><br>
                    If the custom loop data looks incorrect, you can reset and recreate it.
                    <button type="button" class="button button-secondary" onclick="cllfResetAndRecreate(<?php echo $order_id; ?>)">
                        Reset & Recreate Data
                    </button>
                </p>
            </div>
            
            <script>
            function cllfResetAndRecreate(orderId) {
                if (!confirm('This will delete the existing custom loop data and recreate it from the product information. Continue?')) {
                    return;
                }
                
                jQuery.post(ajaxurl, {
                    action: 'cllf_reset_and_recreate',
                    nonce: '<?php echo wp_create_nonce('cllf_reset_and_recreate'); ?>',
                    order_id: orderId
                }, function(response) {
                    if (response.success) {
                        alert('Data reset and recreated successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                    }
                }).fail(function() {
                    alert('AJAX request failed. Please try again.');
                });
            }
            </script>
            <?php
        }
    }
}

/**
 * AJAX handler for reset and recreate
 */
add_action('wp_ajax_cllf_reset_and_recreate', 'cllf_reset_and_recreate_ajax');

function cllf_reset_and_recreate_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'cllf_reset_and_recreate')) {
        wp_send_json_error('Security check failed');
    }
    
    $order_id = intval($_POST['order_id']);
    
    // Reset existing data
    cllf_reset_order_submissions($order_id);
    
    // Set up the POST data as if it came from the create missing data function
    $_POST['nonce'] = wp_create_nonce('cllf_create_missing_data');
    
    // Call the create function
    cllf_create_missing_data_ajax();
}
// SIMPLIFIED: Only essential hooks to avoid conflicts
add_action('woocommerce_before_cart', 'cllf_ensure_correct_sublimation_tags');

// DEBUGGING: Log error messages to identify what's still causing issues
add_filter('woocommerce_add_error', 'cllf_debug_error_messages', 10, 1);

// CART VALIDATION: Fix cart during WooCommerce's cart validation process  
add_action('woocommerce_check_cart_items', 'cllf_fix_cart_during_validation', 1);

// ALSO: Keep the early template redirect as backup
add_action('template_redirect', 'cllf_fix_checkout_early', 1);

// Note: Removed nuclear notice clear - no longer needed with proper skip protection

// FINAL: One last cart fix right before checkout template renders
add_action('woocommerce_checkout_before_customer_details', 'cllf_final_cart_fix', 1);

// AJAX: Intercept WooCommerce AJAX cart validations
add_action('wp_ajax_woocommerce_checkout', 'cllf_fix_ajax_checkout', 1);
add_action('wp_ajax_nopriv_woocommerce_checkout', 'cllf_fix_ajax_checkout', 1);
add_action('wp_ajax_woocommerce_update_order_review', 'cllf_fix_ajax_checkout', 1);
add_action('wp_ajax_nopriv_woocommerce_update_order_review', 'cllf_fix_ajax_checkout', 1);

// Debug: Log when hooks are being registered
error_log('CART REFRESH: Hooks registered - cart and targeted checkout fix');

function cllf_ensure_correct_sublimation_tags() {
    // Log that function was called for debugging
    error_log('CART REFRESH: Function entry - checking conditions');
    
    // REMOVED: Checkout processing skip logic - we WANT fixes to run during order placement
    // to prevent WooCommerce from reverting cart quantities
    
    // Only run on cart page or checkout page (but not during checkout processing)
    $is_cart_page = is_cart();
    $is_checkout_page = is_checkout();
    error_log('CART REFRESH: Page detection - is_cart: ' . ($is_cart_page ? 'YES' : 'NO') . ', is_checkout: ' . ($is_checkout_page ? 'YES' : 'NO'));
    
    if (!$is_cart_page && !$is_checkout_page) {
        error_log('CART REFRESH: Skipping - not cart or checkout page');
        return;
    }
    
    // Only run this if we have custom loops in the cart
    if (!cllf_has_custom_loops_in_cart()) {
        return;
    }
    
    // ALWAYS set skip protection flag when our cart fix runs to prevent protection system conflicts
    if (WC()->session) {
        WC()->session->set('cllf_skip_protection_once', true);
        error_log('CART REFRESH: Set skip protection flag during cart fix');
    }
    
    // Add some basic logging to see if function is running
    $page_type = is_cart() ? 'CART' : (is_checkout() ? 'CHECKOUT' : 'OTHER');
    error_log('CART REFRESH: Function called on ' . $page_type . ' page - checking sublimation tags');
    
    // Get settings for sublimation product IDs
    $clloi_settings = get_option('clloi_settings');
    $sublimation_product_1 = isset($clloi_settings['clloi_sublimation_product_1']) ? $clloi_settings['clloi_sublimation_product_1'] : 49360;
    $sublimation_product_2 = isset($clloi_settings['clloi_sublimation_product_2']) ? $clloi_settings['clloi_sublimation_product_2'] : 49361;
    $sublimation_product_3 = isset($clloi_settings['clloi_sublimation_product_3']) ? $clloi_settings['clloi_sublimation_product_3'] : 49359;
    
    // Count all custom loops in cart
    $all_loops = 0;
    $setup_fee_items = array(); // Track ALL setup fee items (not just existence)
    $per_loop_fee_items = array();
    
    $cart_contents_hash = md5(serialize(WC()->cart->get_cart()));
    error_log('CART REFRESH: Cart contents hash: ' . $cart_contents_hash);
    error_log('CART REFRESH: Cart has ' . count(WC()->cart->get_cart()) . ' items');
    
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $product = $cart_item['data'];
        
        // Count all custom loops
        if ($product->get_sku() && strpos($product->get_sku(), 'CL-') === 0) {
            $all_loops += $cart_item['quantity'];
            error_log('CART REFRESH: Found custom loop: ' . $product->get_sku() . ' x' . $cart_item['quantity']);
        }
        
        // Track sublimation fees
        if ($product->get_sku() === 'Sublimation') {
            if ($product->get_name() === 'Sublimation Tags - Digital Set Up Fee') {
                $setup_fee_items[$cart_item_key] = $cart_item; // Track ALL setup fee items
                error_log('CART REFRESH: Found setup fee (quantity: ' . $cart_item['quantity'] . ')');
            } elseif ($product->get_name() === 'Sublimation Tags (ea)') {
                $per_loop_fee_items[$cart_item_key] = $cart_item;
                error_log('CART REFRESH: Found sublimation tags: x' . $cart_item['quantity'] . ' (Product ID: ' . $cart_item['product_id'] . ')');
            }
        }
    }
    
    error_log('CART REFRESH: Total loops counted: ' . $all_loops);
    
    // Only proceed if we have custom loops
    if ($all_loops === 0) {
        return;
    }
    
    // Check what needs to be fixed
    $needs_update = false;
    $correct_per_loop_product_id = ($all_loops < 24) ? $sublimation_product_1 : $sublimation_product_2;
    
    // Check setup fee - must be exactly 1
    $setup_fee_count = 0;
    $total_setup_fee_quantity = 0;
    foreach ($setup_fee_items as $setup_item) {
        $setup_fee_count++;
        $total_setup_fee_quantity += $setup_item['quantity'];
    }
    
    if ($setup_fee_count !== 1 || $total_setup_fee_quantity !== 1) {
        $needs_update = true;
        error_log('CART REFRESH: Setup fee issue - found ' . $setup_fee_count . ' items with total quantity ' . $total_setup_fee_quantity . ', need exactly 1 item with quantity 1');
    }
    
    // Check per-loop tags - must match total loops exactly
    if (empty($per_loop_fee_items)) {
        $needs_update = true;
        error_log('CART REFRESH: Missing sublimation tags, need to add ' . $all_loops . ' loops');
    } else {
        foreach ($per_loop_fee_items as $cart_item) {
            if ($cart_item['quantity'] !== $all_loops || $cart_item['product_id'] !== (int)$correct_per_loop_product_id) {
                $needs_update = true;
                error_log('CART REFRESH: Incorrect sublimation tags quantity: ' . $cart_item['quantity'] . ' should be ' . $all_loops);
                break;
            }
        }
    }
    
    // Update if needed
    if ($needs_update) {
        error_log('CART REFRESH: Updating sublimation fees for ' . $all_loops . ' total loops');
        
        // Set flag to skip protection during our programmatic updates
        if (WC()->session) {
            WC()->session->set('cllf_skip_protection_once', true);
            error_log('CART REFRESH: Set skip protection flag');
        }
        
        // Remove ALL existing sublimation items (both setup fees and per-loop fees)
        foreach ($setup_fee_items as $cart_item_key => $cart_item) {
            error_log('CART REFRESH: Removing setup fee item');
            WC()->cart->remove_cart_item($cart_item_key);
        }
        foreach ($per_loop_fee_items as $cart_item_key => $cart_item) {
            error_log('CART REFRESH: Removing per-loop fee item');
            WC()->cart->remove_cart_item($cart_item_key);
        }
        
        // Add exactly 1 setup fee
        WC()->cart->add_to_cart((int)$sublimation_product_3, 1);
        error_log('CART REFRESH: Added 1 setup fee');
        
        // Add correct number of per-loop tags
        WC()->cart->add_to_cart((int)$correct_per_loop_product_id, $all_loops);
        error_log('CART REFRESH: Added ' . $all_loops . ' sublimation tags');
        
        // Recalculate cart totals
        WC()->cart->calculate_totals();
        
        // CRITICAL: Force save cart to session to prevent reversion
        if (WC()->session) {
            WC()->session->set('cart', WC()->cart->get_cart_for_session());
            WC()->session->save_data();
            error_log('CART REFRESH: Forced cart save to session');
        }
        
        error_log('CART REFRESH: Successfully updated sublimation tags and saved to session');
    } else {
        error_log('CART REFRESH: Sublimation tags are already correct');
    }
}

// DEBUG: Log all error messages to identify what's causing checkout issues
function cllf_debug_error_messages($message) {
    error_log('CART REFRESH: WooCommerce error message: ' . $message);
    return $message; // Don't suppress, just log
}

// CART VALIDATION: Fix cart during WooCommerce's validation process
function cllf_fix_cart_during_validation() {
    // Only for checkout/cart with custom loops
    if (!cllf_has_custom_loops_in_cart()) {
        return;
    }
    
    // Prevent multiple executions per request
    static $validation_run = false;
    if ($validation_run) {
        return;
    }
    $validation_run = true;
    
    error_log('CART REFRESH: Fix during WooCommerce cart validation');
    
    // Fix cart during validation
    cllf_ensure_correct_sublimation_tags();
    
    // Force WooCommerce to recalculate everything
    WC()->cart->calculate_totals();
}

// VERY EARLY: Fix cart before any WooCommerce checkout validation occurs
function cllf_fix_checkout_early() {
    // Only for checkout with custom loops
    if (!is_checkout() || !cllf_has_custom_loops_in_cart()) {
        return;
    }
    
    // Prevent multiple executions
    static $already_run = false;
    if ($already_run) {
        return;
    }
    $already_run = true;
    
    error_log('CART REFRESH: Very early fix before checkout validation');
    
    // ALWAYS set skip protection flag on checkout pages with custom loops
    if (WC()->session) {
        WC()->session->set('cllf_skip_protection_once', true);
        error_log('CART REFRESH: Set skip protection flag for checkout');
    }
    
    // Fix cart before any validation
    cllf_ensure_correct_sublimation_tags();
    
    // Clear any existing WooCommerce notices that might be cached
    if (function_exists('wc_clear_notices')) {
        wc_clear_notices();
        error_log('CART REFRESH: Cleared WooCommerce notices after fix');
    }
}

// AJAX: Fix cart before any AJAX checkout operations
function cllf_fix_ajax_checkout() {
    error_log('CART REFRESH: AJAX checkout operation detected');
    
    if (!cllf_has_custom_loops_in_cart()) {
        error_log('CART REFRESH: No custom loops in cart for AJAX operation');
        return;
    }
    
    error_log('CART REFRESH: Fixing cart for AJAX checkout operation');
    cllf_ensure_correct_sublimation_tags();
    
    // Clear any notices
    if (function_exists('wc_clear_notices')) {
        wc_clear_notices();
        error_log('CART REFRESH: Cleared notices in AJAX handler');
    }
}

// Note: Removed nuclear notice clear function - no longer needed

// FINAL: One last attempt to fix cart before checkout details render
function cllf_final_cart_fix() {
    if (!cllf_has_custom_loops_in_cart()) {
        return;
    }
    
    // Prevent multiple executions
    static $final_run = false;
    if ($final_run) {
        return;
    }
    $final_run = true;
    
    error_log('CART REFRESH: FINAL cart fix before customer details');
    
    // Set skip protection flag
    if (WC()->session) {
        WC()->session->set('cllf_skip_protection_once', true);
        error_log('CART REFRESH: Set skip protection for final fix');
    }
    
    // Final cart fix
    cllf_ensure_correct_sublimation_tags();
    
    error_log('CART REFRESH: Final fix completed');
}

// Simple debug to test if plugin is loading
error_log('CART REFRESH DEBUG: Plugin loaded at ' . date('Y-m-d H:i:s'));