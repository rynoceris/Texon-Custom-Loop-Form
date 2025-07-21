<?php
/**
 * Admin initialization for the Custom Laundry Loops Form plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Add settings page to admin menu
add_action('admin_menu', 'cllf_add_admin_menu');

function cllf_add_admin_menu() {
    add_submenu_page(
        'options-general.php',
        'Custom Laundry Loops Form Settings',
        'Custom Loops Form',
        'manage_options',
        'cllf-settings',
        'cllf_settings_page'
    );
}

// Register settings
add_action('admin_init', 'cllf_register_settings');

function cllf_register_settings() {
    register_setting('clloi_settings_group', 'clloi_settings');
    
    // We're using the same settings group as the original plugin to maintain compatibility
    // This way, the image IDs and sublimation product IDs are stored in the same option
}

// Settings page HTML
function cllf_settings_page() {
    // Get current settings
    $clloi_settings = get_option('clloi_settings', array());
    ?>
    <div class="wrap">
        <h1>Custom Laundry Loops Form Settings</h1>
        
        <div class="notice notice-info">
            <p>This plugin shares settings with the Custom Laundry Loops Order Importer plugin. Changes made here will affect both plugins.</p>
        </div>
        
        <form method="post" action="options.php">
            <?php settings_fields('clloi_settings_group'); ?>
            <?php do_settings_sections('clloi_settings_group'); ?>
            
            <h2>Debug Settings</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="cllf_debug_mode">Debug Mode</label>
                    </th>
                    <td>
                        <input type="checkbox" id="cllf_debug_mode" name="clloi_settings[cllf_debug_mode]" value="1" 
                               <?php checked(isset($clloi_settings['cllf_debug_mode']) ? $clloi_settings['cllf_debug_mode'] : 0, 1); ?> />
                        <label for="cllf_debug_mode">Enable administrator debug mode</label>
                        <p class="description">When enabled, administrators will see detailed debug information on the cart page including all custom loop submissions, session data, and product details.</p>
                    </td>
                </tr>
            </table>
            
            <h2>Form Page Settings</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="cllf_form_page_id">Custom Loops Form Page</label>
                    </th>
                    <td>
                        <?php
                        $selected_page = isset($clloi_settings['cllf_form_page_id']) ? $clloi_settings['cllf_form_page_id'] : '';
                        
                        // Get all pages that contain the shortcode
                        $pages_with_shortcode = array();
                        $pages = get_pages();
                        foreach ($pages as $page) {
                            if (has_shortcode($page->post_content, 'custom_laundry_loops_form')) {
                                $pages_with_shortcode[] = $page;
                            }
                        }
                        ?>
                        <select id="cllf_form_page_id" name="clloi_settings[cllf_form_page_id]">
                            <option value="">-- Auto-detect --</option>
                            <?php foreach ($pages_with_shortcode as $page) : ?>
                                <option value="<?php echo $page->ID; ?>" <?php selected($selected_page, $page->ID); ?>>
                                    <?php echo esc_html($page->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Select the page containing the Custom Loops form, or leave as auto-detect. This will be used for the "Start a New Loop Order" button.</p>
                        
                        <?php if (!empty($pages_with_shortcode)) : ?>
                            <p class="description" style="margin-top: 10px;">
                                <strong>Pages with form shortcode found:</strong><br>
                                <?php foreach ($pages_with_shortcode as $page) : ?>
                                    • <?php echo esc_html($page->post_title); ?> 
                                    (<a href="<?php echo get_permalink($page->ID); ?>" target="_blank">View</a> | 
                                    <a href="<?php echo get_edit_post_link($page->ID); ?>" target="_blank">Edit</a>)<br>
                                <?php endforeach; ?>
                            </p>
                        <?php else : ?>
                            <p class="description" style="margin-top: 10px; color: #d63638;">
                                <strong>Warning:</strong> No pages found containing the [custom_laundry_loops_form] shortcode.
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <h2>Sublimation Product IDs</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="clloi_sublimation_product_1">Sublimation Product ID (< 24 loops)</label>
                    </th>
                    <td>
                        <input type="text" id="clloi_sublimation_product_1" name="clloi_settings[clloi_sublimation_product_1]" 
                               value="<?php echo isset($clloi_settings['clloi_sublimation_product_1']) ? esc_attr($clloi_settings['clloi_sublimation_product_1']) : '49143'; ?>" />
                        <p class="description">Product ID for the sublimation charge per loop when less than 24 loops are ordered ($3.95/ea).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="clloi_sublimation_product_2">Sublimation Product ID (≥ 24 loops)</label>
                    </th>
                    <td>
                        <input type="text" id="clloi_sublimation_product_2" name="clloi_settings[clloi_sublimation_product_2]" 
                               value="<?php echo isset($clloi_settings['clloi_sublimation_product_2']) ? esc_attr($clloi_settings['clloi_sublimation_product_2']) : '49133'; ?>" />
                        <p class="description">Product ID for the sublimation charge per loop when 24 or more loops are ordered ($1.95/ea).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="clloi_sublimation_product_3">Setup Fee Product ID</label>
                    </th>
                    <td>
                        <input type="text" id="clloi_sublimation_product_3" name="clloi_settings[clloi_sublimation_product_3]" 
                               value="<?php echo isset($clloi_settings['clloi_sublimation_product_3']) ? esc_attr($clloi_settings['clloi_sublimation_product_3']) : '49132'; ?>" />
                        <p class="description">Product ID for the one-time $35 sublimation setup fee.</p>
                    </td>
                </tr>
            </table>
            
            <h2>Product Image IDs</h2>
            <p>These settings control the product images associated with each loop color and clip type. If you leave these fields empty, the default image IDs will be used.</p>
            
            <h3>Single Clips</h3>
            <table class="form-table">
                <?php
                $single_clip_colors = array(
                    'black' => 'Black',
                    'bone' => 'Bone',
                    'brown' => 'Brown',
                    'grey' => 'Grey',
                    'hunter' => 'Hunter Green',
                    'kelly' => 'Kelly Green',
                    'maroon' => 'Maroon',
                    'minty' => 'Minty Green',
                    'navy' => 'Navy Blue',
                    'neon' => 'Neon Yellow',
                    'olive' => 'Olive',
                    'orange' => 'Orange',
                    'pacific' => 'Pacific Blue',
                    'pink' => 'Pink',
                    'purple' => 'Purple',
                    'rainbow' => 'Rainbow',
                    'red' => 'Red',
                    'royal' => 'Royal Blue',
                    'sky' => 'Sky (Light) Blue',
                    'gold' => 'Sun Gold',
                    'tan' => 'Tan (Jute)',
                    'teal' => 'Teal',
                    'turquoise' => 'Turquoise',
                    'violet' => 'Violet',
                    'white' => 'White',
                    'yellow' => 'Yellow'
                );
                
                foreach ($single_clip_colors as $color_key => $color_name) {
                    $setting_key = 'clloi_image_id_' . $color_key . '_single';
                    $current_value = isset($clloi_settings[$setting_key]) ? $clloi_settings[$setting_key] : '';
                    ?>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr($setting_key); ?>"><?php echo esc_html($color_name); ?></label>
                        </th>
                        <td>
                            <input type="text" id="<?php echo esc_attr($setting_key); ?>" name="clloi_settings[<?php echo esc_attr($setting_key); ?>]" 
                                   value="<?php echo esc_attr($current_value); ?>" placeholder="Default ID will be used" />
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </table>
            
            <h3>Double Clips</h3>
            <table class="form-table">
                <?php
                foreach ($single_clip_colors as $color_key => $color_name) {
                    $setting_key = 'clloi_image_id_' . $color_key . '_double';
                    $current_value = isset($clloi_settings[$setting_key]) ? $clloi_settings[$setting_key] : '';
                    ?>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr($setting_key); ?>"><?php echo esc_html($color_name); ?></label>
                        </th>
                        <td>
                            <input type="text" id="<?php echo esc_attr($setting_key); ?>" name="clloi_settings[<?php echo esc_attr($setting_key); ?>]" 
                                   value="<?php echo esc_attr($current_value); ?>" placeholder="Default ID will be used" />
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </table>
            
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Add settings link to plugins page - more flexible approach
add_filter('plugin_action_links', 'cllf_add_settings_link', 10, 2);

function cllf_add_settings_link($links, $file) {
    // Get the base plugin file by checking if our constant is defined
    if (defined('CLLF_PLUGIN_DIR')) {
        $plugin_basename = plugin_basename(CLLF_PLUGIN_DIR . basename($file));
        $current_plugin = plugin_basename($file);
        
        // Check if this is our plugin by comparing the directory
        if (dirname($current_plugin) === dirname($plugin_basename) || 
            strpos($file, 'custom-loop-form-plugin.php') !== false) {
            $settings_link = '<a href="' . admin_url('options-general.php?page=cllf-settings') . '">Settings</a>';
            array_unshift($links, $settings_link);
        }
    }
    return $links;
}