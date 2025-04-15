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

// Add settings link to plugins page
add_filter('plugin_action_links_' . plugin_basename(CLLF_PLUGIN_DIR . 'custom-laundry-loops-form.php'), 'cllf_add_settings_link');

function cllf_add_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=cllf-settings">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
