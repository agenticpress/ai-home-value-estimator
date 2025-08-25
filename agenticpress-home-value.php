<?php
/**
 * Plugin Name:       AgenticPress AI Home Values
 * Description:       Provides a home value form via a shortcode and retrieves an AVM from the ATTOM API.
 * Version:           1.6.4 (Definitive Autofill & Component Style Fix)
 * Author:            AgenticPress
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       agenticpress-home-values
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// -----------------------------------------------------------------------------
// Activation/Deactivation Hooks for Super Admin Role
// -----------------------------------------------------------------------------
register_activation_hook(__FILE__, 'agenticpress_hv_activate');
register_deactivation_hook(__FILE__, 'agenticpress_hv_deactivate');

function agenticpress_hv_activate() {
    // Create the Super Admin role with specific capabilities
    add_role(
        'agenticpress_super_admin',
        'AgenticPress Super Admin',
        [
            'read' => true, // Basic access to the dashboard
            'agenticpress_manage_settings' => true,
            'agenticpress_access_api_test' => true,
        ]
    );

    // Add capabilities to the main site administrator role as well
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->add_cap('agenticpress_manage_settings');
        $admin_role->add_cap('agenticpress_access_api_test');
    }

    // Also run the table creation
    agenticpress_hv_create_property_table();
}

function agenticpress_hv_deactivate() {
    // Clean up by removing the custom role and capabilities
    remove_role('agenticpress_super_admin');
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->remove_cap('agenticpress_manage_settings');
        $admin_role->remove_cap('agenticpress_access_api_test');
    }
}


// -----------------------------------------------------------------------------
// Database Table Creation
// -----------------------------------------------------------------------------
function agenticpress_hv_create_property_table() {
    global $wpdb;
    $new_table_name = $wpdb->prefix . 'agenticpress_properties';
    $old_table_name = $wpdb->prefix . 'agenticpress_lookups';
    $charset_collate = $wpdb->get_charset_collate();

    // Rename old table if it exists to preserve data
    if ($wpdb->get_var("SHOW TABLES LIKE '$old_table_name'") === $old_table_name) {
        $backup_table_name = $wpdb->prefix . 'agenticpress_lookups_old';
        $wpdb->query("RENAME TABLE $old_table_name TO $backup_table_name");
    }

    $sql = "CREATE TABLE $new_table_name (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        attomId BIGINT(20) UNSIGNED,
        lookup_time DATETIME NOT NULL,
        full_address VARCHAR(255) NOT NULL,
        street VARCHAR(200),
        city VARCHAR(100),
        state VARCHAR(50),
        zip VARCHAR(20),
        latitude DECIMAL(10, 7),
        longitude DECIMAL(11, 7),
        property_type VARCHAR(100),
        year_built INT,
        lot_size_acres DECIMAL(10, 4),
        building_size_sqft INT,
        bedrooms INT,
        bathrooms DECIMAL(3, 1),
        avm_value INT,
        avm_confidence_score INT,
        avm_value_high INT,
        avm_value_low INT,
        last_sale_date DATE,
        last_sale_price INT,
        assessed_total_value INT,
        market_total_value INT,
        owner_name VARCHAR(255),
        owner_email VARCHAR(255),
        owner_phone VARCHAR(50),
        gform_entry_id BIGINT(20),
        full_json LONGTEXT,
        PRIMARY KEY (id),
        INDEX attomId (attomId)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// -----------------------------------------------------------------------------
// Helper function to securely get API Keys
// -----------------------------------------------------------------------------
function agenticpress_hv_get_api_key($option_name, $constant_name) {
    if (defined($constant_name) && !empty(constant($constant_name))) {
        return constant($constant_name);
    }
    return get_option($option_name);
}

// -----------------------------------------------------------------------------
// Admin Menu and Settings
// -----------------------------------------------------------------------------
add_action('admin_menu', 'agenticpress_hv_add_admin_menu');
function agenticpress_hv_add_admin_menu() {
    add_menu_page('Home Values', 'Home Values', 'read', 'agenticpress_home_values', 'agenticpress_hv_welcome_page_html', 'dashicons-admin-home', 25);
    add_submenu_page('agenticpress_home_values', 'Welcome', 'Welcome', 'read', 'agenticpress_home_values', 'agenticpress_hv_welcome_page_html');
    add_submenu_page('agenticpress_home_values', 'Configuration', 'Configuration', 'read', 'agenticpress-configuration', 'agenticpress_hv_configuration_page_html');
    if (current_user_can('agenticpress_access_api_test')) {
        add_submenu_page('agenticpress_home_values', 'API Test Area', 'API Test Area', 'agenticpress_access_api_test', 'agenticpress_api_test', 'agenticpress_hv_api_test_page_html');
    }
    add_submenu_page('agenticpress_home_values', 'Lookups', 'Lookups', 'read', 'agenticpress_lookups', 'agenticpress_hv_lookups_page_html');
}

// Sanitize callback to prevent saved keys from being deleted on empty submit
function agenticpress_hv_sanitize_api_key($new_value) {
    $option_name = str_replace('sanitize_option_', '', current_filter());
    $old_value = get_option($option_name);
    if (empty($new_value)) {
        return $old_value;
    }
    return sanitize_text_field($new_value);
}


add_action('admin_init', 'agenticpress_hv_settings_init');
function agenticpress_hv_settings_init() {

    $api_key_options = [
        'agenticpress_hv_api_key',
        'agenticpress_hv_google_api_key',
        'agenticpress_hv_ai_api_key'
    ];

    foreach ($api_key_options as $option) {
        register_setting('agenticpress_hv_avm_settings', $option, ['sanitize_callback' => 'agenticpress_hv_sanitize_api_key']);
        register_setting('agenticpress_hv_google_settings', $option, ['sanitize_callback' => 'agenticpress_hv_sanitize_api_key']);
        register_setting('agenticpress_hv_ai_settings', $option, ['sanitize_callback' => 'agenticpress_hv_sanitize_api_key']);
    }

    // Group for AVM Settings
    register_setting('agenticpress_hv_avm_settings', 'agenticpress_hv_avm_limit');
    add_settings_section('agenticpress_hv_avm_section', 'AVM API Settings', 'agenticpress_hv_avm_section_html', 'agenticpress_avm');
    add_settings_field('agenticpress_hv_api_key_field', 'ATTOM AVM API Key', 'agenticpress_hv_api_key_field_html', 'agenticpress_avm', 'agenticpress_hv_avm_section');
    add_settings_field('agenticpress_hv_avm_limit_field', 'Monthly AVM Limit', 'agenticpress_hv_avm_limit_field_html', 'agenticpress_avm', 'agenticpress_hv_avm_section');

    // Group for Google Settings
    add_settings_section('agenticpress_hv_google_section', 'Google Places API Settings', 'agenticpress_hv_google_section_html', 'agenticpress_google');
    add_settings_field('agenticpress_hv_google_api_key_field', 'Google Places API Key', 'agenticpress_hv_google_api_key_field_html', 'agenticpress_google', 'agenticpress_hv_google_section');
    register_setting('agenticpress_hv_google_settings', 'agenticpress_hv_input_bg_color');
    add_settings_field('agenticpress_hv_input_bg_color_field', 'Input Background Color', 'agenticpress_hv_input_bg_color_field_html', 'agenticpress_google', 'agenticpress_hv_google_section');


    // Group for AI Settings
    register_setting('agenticpress_hv_ai_settings', 'agenticpress_hv_ai_mode');
    register_setting('agenticpress_hv_ai_settings', 'agenticpress_hv_ai_limit');
    register_setting('agenticpress_hv_ai_settings', 'agenticpress_hv_ai_instructions');
    add_settings_section('agenticpress_hv_ai_section', 'AI Settings', 'agenticpress_hv_ai_section_html', 'agenticpress_ai');
    add_settings_field('agenticpress_hv_ai_mode_field', 'AI Mode', 'agenticpress_hv_ai_mode_field_html', 'agenticpress_ai', 'agenticpress_hv_ai_section');
    add_settings_field('agenticpress_hv_ai_api_key_field', 'Gemini API Key', 'agenticpress_hv_ai_api_key_field_html', 'agenticpress_ai', 'agenticpress_hv_ai_section');
    add_settings_field('agenticpress_hv_ai_limit_field', 'Monthly AI Requests Limit', 'agenticpress_hv_ai_limit_field_html', 'agenticpress_ai', 'agenticpress_hv_ai_section');
    add_settings_field('agenticpress_hv_ai_instructions_field', 'AI Instructions', 'agenticpress_hv_ai_instructions_field_html', 'agenticpress_ai', 'agenticpress_hv_ai_section');


    // Group for Gravity Forms Settings
    if (class_exists('GFAPI')) {
        register_setting('agenticpress_hv_gf_settings', 'agenticpress_hv_gf_cma_form');
        add_settings_section('agenticpress_hv_gf_section', 'Gravity Forms Integration', 'agenticpress_hv_gf_section_html', 'agenticpress_gf');
        add_settings_field('agenticpress_hv_gf_cma_form_field', 'CMA Request Form', 'agenticpress_hv_gf_cma_form_field_html', 'agenticpress_gf', 'agenticpress_hv_gf_section');
    }
}

// Helper for rendering the secure key fields
function agenticpress_hv_render_secure_key_field($option_name, $constant_name) {
    if (defined($constant_name) && !empty(constant($constant_name))) {
        echo '<input type="text" value="Defined in wp-config.php" class="regular-text" disabled>';
        echo '<p class="description">Key is securely set in your <code>wp-config.php</code> file.</p>';
    } else {
        $api_key = get_option($option_name);
        $placeholder = !empty($api_key) ? '••••••••••••••••••••••' : 'Enter your API key';
        $type = !empty($api_key) ? 'password' : 'text';
        echo '<input type="'.$type.'" name="'.$option_name.'" value="" class="regular-text" placeholder="'.$placeholder.'">';
        echo '<p class="description">Leave blank to keep the current saved key. Enter a new key to update.</p>';
    }
}


// Section HTML
function agenticpress_hv_avm_section_html() {
    echo '<p>Configure the connection to the ATTOM AVM API. For maximum security, you can add <code>define(\'AGENTICPRESS_ATTOM_API_KEY\', \'your-key-here\');</code> to your `wp-config.php` file.</p>';
}

function agenticpress_hv_google_section_html() {
    echo '<p>Configure the Google Places API and related form styles. For maximum security, you can add <code>define(\'AGENTICPRESS_GOOGLE_API_KEY\', \'your-key-here\');</code> to your `wp-config.php` file.</p>';
}

function agenticpress_hv_ai_section_html() {
    echo '<p>Enable AI-powered features. For maximum security, you can add <code>define(\'AGENTICPRESS_GEMINI_API_KEY\', \'your-key-here\');</code> to your `wp-config.php` file.</p>';
}

// Field render functions
function agenticpress_hv_api_key_field_html() {
    agenticpress_hv_render_secure_key_field('agenticpress_hv_api_key', 'AGENTICPRESS_ATTOM_API_KEY');
}

function agenticpress_hv_google_api_key_field_html() {
    agenticpress_hv_render_secure_key_field('agenticpress_hv_google_api_key', 'AGENTICPRESS_GOOGLE_API_KEY');
}

function agenticpress_hv_ai_api_key_field_html() {
    agenticpress_hv_render_secure_key_field('agenticpress_hv_ai_api_key', 'AGENTICPRESS_GEMINI_API_KEY');
}

function agenticpress_hv_input_bg_color_field_html() {
    $bg_color = get_option('agenticpress_hv_input_bg_color', 'white');
    ?>
    <select name="agenticpress_hv_input_bg_color">
        <option value="white" <?php selected($bg_color, 'white'); ?>>White</option>
        <option value="black" <?php selected($bg_color, 'black'); ?>>Black</option>
    </select>
    <p class="description">Select the background color for the address input field to resolve theme conflicts.</p>
    <?php
}

function agenticpress_hv_avm_limit_field_html() {
    $limit = get_option('agenticpress_hv_avm_limit', 100);
    if (current_user_can('agenticpress_manage_settings')) {
        echo '<input type="number" name="agenticpress_hv_avm_limit" value="' . esc_attr($limit) . '" class="regular-text">';
        echo '<p class="description">This limit is set by AgenticPress support.</p>';
    } else {
        echo "<strong>" . esc_html($limit) . "</strong>";
        echo '<p class="description">Only a Super Admin can change this value.</p>';
    }
}

function agenticpress_hv_ai_mode_field_html() {
    $ai_mode = get_option('agenticpress_hv_ai_mode', 'disabled');
    if (current_user_can('agenticpress_manage_settings')) {
        echo '<select name="agenticpress_hv_ai_mode">';
        echo '<option value="disabled"' . selected($ai_mode, 'disabled', false) . '>Disabled</option>';
        echo '<option value="enabled"' . selected($ai_mode, 'enabled', false) . '>Enabled</option>';
        echo '</select>';
        echo '<p class="description">Enable or disable AI-powered features (must be activated by support).</p>';
    } else {
        echo "<strong>" . esc_html(ucfirst($ai_mode)) . "</strong>";
        echo '<p class="description">Only a Super Admin can change this value.</p>';
    }
}

function agenticpress_hv_ai_limit_field_html() {
    $limit = get_option('agenticpress_hv_ai_limit', 0);
    echo "<strong>" . esc_html($limit) . "</strong>";
    echo '<p class="description">This limit is set by AgenticPress support after feature activation.</p>';
}

function agenticpress_hv_ai_instructions_field_html() {
    $default_instructions = "You are a real estate analyst providing a summary of an automated valuation. Write a brief, engaging, and professional property summary using the data provided.

Start by directly addressing the property at `{{full_address}}`.

Craft a narrative that includes these key details:
- The property is a `{{property_type}}` built in `{{year_built}}`.
- It has `{{bedrooms}}` bedrooms and `{{bathrooms}}` bathrooms.
- The living area is approximately `{{building_size_sqft}}` square feet.
- The last recorded sale price was `{{last_sale_price}}`.

The current Automated Valuation Model (AVM) estimates the property's value at **`{{avm_value}}`**. The estimated value range is between `{{avm_range}}`, with a confidence score of `{{confidence_score}}`.

Conclude with a strong call to action. Emphasize that this is an automated estimate and that a full Comparative Market Analysis (CMA) is necessary for a more accurate valuation. Encourage the user to contact us to schedule their free, comprehensive CMA.";
    $instructions = get_option('agenticpress_hv_ai_instructions', $default_instructions);
    echo '<textarea name="agenticpress_hv_ai_instructions" rows="15" class="large-text">' . esc_textarea($instructions) . '</textarea>';
    echo '<p class="description">Provide instructions for the AI. You can use placeholders like <code>{{bedrooms}}</code>, <code>{{bathrooms}}</code>, <code>{{year_built}}</code>, <code>{{lot_size_acres}}</code>, etc., to include data from the ATTOM API response. Refer to the ATTOM API documentation for available fields.</p>';
}


function agenticpress_hv_welcome_page_html() {
    if (!current_user_can('read')) return;

    global $wpdb;
    $table_name = $wpdb->prefix . 'agenticpress_properties';
    $first_day_of_month = date('Y-m-01 00:00:00');
    $avm_usage = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_name WHERE lookup_time >= %s", $first_day_of_month));

    // AI usage is not yet tracked, so we'll set it to 0 for now. This will be implemented later.
    $ai_usage = 0;

    $avm_limit = get_option('agenticpress_hv_avm_limit', 100);
    $ai_limit = get_option('agenticpress_hv_ai_limit', 0);
    $reset_date = date('F 1, Y', strtotime('first day of next month'));

    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'welcome';
    ?>
    <style>
        .ap-welcome-wrap { background-color: #f6f7f8; padding: 2rem; }
        .ap-welcome-header { text-align: center; margin-bottom: 2.5rem; }
        .ap-welcome-header h1 { font-size: 2.5rem; line-height: 1.2; margin-bottom: 0.5rem; }
        .ap-welcome-header .ap-tagline { font-size: 1.2rem; color: #50575e; margin-top: 0; }
        .ap-cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; margin-bottom: 2.5rem; }
        .ap-card { background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 1.5rem; display: flex; flex-direction: column; }
        .ap-card h3 { font-size: 1.2rem; margin-top: 1rem; margin-bottom: 0.5rem; }
        .ap-card p { margin-top: 0; color: #50575e; flex-grow: 1; }
        .ap-card .dashicons { font-size: 2.5rem; color: var(--wp-admin-theme-color, #2271b1); height: 40px; width: 40px; }
        .ap-usage-card .usage-display { font-size: 2.5rem; font-weight: bold; line-height: 1; margin-bottom: 0.5rem; }
        .ap-usage-card .usage-display span { font-size: 1.5rem; font-weight: normal; color: #50575e; }
        .ap-usage-card .resets-on { font-style: italic; color: #50575e; font-size: 0.9rem; }
        .ap-support-footer { text-align: center; border-top: 1px solid #c3c4c7; padding-top: 1.5rem; margin-top: 1.5rem; }
    </style>
    <div class="wrap">
        <h2 class="nav-tab-wrapper">
            <a href="?page=agenticpress_home_values&tab=welcome" class="nav-tab <?php echo $active_tab == 'welcome' ? 'nav-tab-active' : ''; ?>">Welcome</a>
            <a href="?page=agenticpress_home_values&tab=shortcodes" class="nav-tab <?php echo $active_tab == 'shortcodes' ? 'nav-tab-active' : ''; ?>">Shortcodes</a>
        </h2>

        <?php if ($active_tab == 'welcome') : ?>
        <div class="ap-welcome-wrap">
            <div class="ap-welcome-header">
                <h1>Welcome to AgenticPress AI Home Values</h1>
                <p class="ap-tagline">The complete solution for integrating real-time property valuations on your WordPress site.</p>
            </div>

            <div class="ap-cards-grid">
                <div class="ap-card ap-usage-card">
                    <span class="dashicons dashicons-performance"></span>
                    <h3>AVM API Usage</h3>
                    <div class="usage-display"><?php echo esc_html($avm_usage); ?> <span>/ <?php echo esc_html($avm_limit); ?></span></div>
                    <p>This is your total AVM lookup usage for the current month.</p>
                    <p class="resets-on">Resets on: <?php echo esc_html($reset_date); ?></p>
                </div>
                <div class="ap-card ap-usage-card">
                    <span class="dashicons dashicons-cloud"></span>
                    <h3>AI Requests Usage</h3>
                    <div class="usage-display"><?php echo esc_html($ai_usage); ?> <span>/ <?php echo esc_html($ai_limit); ?></span></div>
                    <p>This is your total AI request usage for the current month.</p>
                    <p class="resets-on">Resets on: <?php echo esc_html($reset_date); ?></p>
                </div>
                <div class="ap-card">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <h3>Configure APIs</h3>
                    <p>Manage API keys and review your account limits and upgrade options.</p>
                    <a href="<?php echo admin_url('admin.php?page=agenticpress-configuration'); ?>" class="button button-primary">Go to Configuration</a>
                </div>
                <div class="ap-card">
                    <span class="dashicons dashicons-visibility"></span>
                    <h3>View Lookups</h3>
                    <p>See a complete history of all the property valuation lookups performed on your website.</p>
                    <a href="<?php echo admin_url('admin.php?page=agenticpress_lookups'); ?>" class="button button-secondary">View Lookups</a>
                </div>
            </div>

            <div class="ap-support-footer">
                <p>Thank you for using AgenticPress! For help, please <a href="https://agenticpress.com/support" target="_blank">visit our support site</a>.</p>
            </div>

        </div>

        <?php else : // Shortcodes Tab ?>
            <div class="wrap">
                <h2>Shortcode Generator</h2>
                <p>Use the options below to build a custom shortcode, then copy and paste it into any page, post, or text widget.</p>

                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="sc-button-text">Button Text</label></th>
                            <td><input type="text" id="sc-button-text" value="Get Home Value" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sc-input-width">Form Width</label></th>
                            <td><input type="text" id="sc-input-width" value="100%" class="regular-text">
                                <p class="description">Enter any valid CSS width (e.g., "500px", "100%", "80vw"). The form will auto-center if width is less than 100%.</p></td>
                        </tr>
                         <tr>
                            <th scope="row"><label for="sc-button-position">Button Position</label></th>
                            <td>
                                <select id="sc-button-position">
                                    <option value="below-left">Below, Aligned Left</option>
                                    <option value="below-center">Below, Aligned Center</option>
                                    <option value="below-right">Below, Aligned Right</option>
                                    <option value="inline">Same Line as Input</option>
                                </select>
                            </td>
                        </tr>
                         <tr>
                            <th scope="row"><label for="sc-gap">Gap (px) for Inline Mode</label></th>
                            <td><input type="number" id="sc-gap" value="10" class="small-text">
                                <p class="description">The space between the input field and button in inline mode.</p></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sc-button-color">Button Color</label></th>
                            <td><input type="color" id="sc-button-color" value="#0073aa"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sc-button-text-color">Button Text Color</label></th>
                            <td><input type="color" id="sc-button-text-color" value="#ffffff"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sc-input-bg-color">Input Background Color</label></th>
                            <td>
                                <select id="sc-input-bg-color">
                                    <option value="default">Default</option>
                                    <option value="white">White</option>
                                    <option value="black">Black</option>
                                </select>
                            </td>
                        </tr>
                         <tr>
                            <th scope="row"><label for="sc-input-text-color">Input Text Color</label></th>
                            <td><input type="color" id="sc-input-text-color" value="#000000"></td>
                        </tr>
                    </tbody>
                </table>

                <h3>Your Generated Shortcode</h3>
                <div style="background: #f6f7f7; border-left: 4px solid #72aee6; padding: 15px; margin-bottom: 15px;">
                    <textarea id="generated-shortcode" rows="4" class="large-text" readonly style="white-space: pre; word-wrap: normal; overflow-x: scroll;"></textarea>
                    <p><button id="copy-shortcode" class="button button-primary">Copy Shortcode</button> <span id="copy-feedback" style="display: none; color: green; margin-left: 10px;">Copied!</span></p>
                </div>
            </div>

            <script>
            jQuery(document).ready(function($) {
                function generateShortcode() {
                    let shortcode = '[agenticpress_home_value_form';

                    const buttonText = $('#sc-button-text').val();
                    if (buttonText && buttonText !== 'Get Home Value') {
                        shortcode += ` button_text="${buttonText}"`;
                    }

                    const inputWidth = $('#sc-input-width').val();
                    if (inputWidth && inputWidth !== '100%') {
                        shortcode += ` input_width="${inputWidth}"`;
                    }

                    const buttonPosition = $('#sc-button-position').val();
                    if (buttonPosition && buttonPosition !== 'below-left') {
                         shortcode += ` button_position="${buttonPosition}"`;
                    }

                    const inputBgColor = $('#sc-input-bg-color').val();
                    if (inputBgColor && inputBgColor !== 'default') {
                        shortcode += ` input_bg_color="${inputBgColor}"`;
                    }

                    const inputTextColor = $('#sc-input-text-color').val();
                    if (inputTextColor && inputTextColor !== '#000000') {
                        shortcode += ` input_text_color="${inputTextColor}"`;
                    }

                    const gap = $('#sc-gap').val();
                    if (gap && gap !== '10') {
                        shortcode += ` gap="${gap}"`;
                    }

                    const buttonColor = $('#sc-button-color').val();
                    const buttonTextColor = $('#sc-button-text-color').val();
                    let buttonStyle = '';

                    if (buttonColor && buttonColor !== '#0073aa') {
                        buttonStyle += `background-color: ${buttonColor};`;
                    }
                     if (buttonTextColor && buttonTextColor !== '#ffffff') {
                        buttonStyle += ` color: ${buttonTextColor};`;
                    }

                    if (buttonStyle) {
                        shortcode += ` button_style="${buttonStyle.trim()}"`;
                    }

                    shortcode += ']';
                    $('#generated-shortcode').val(shortcode);
                }

                $('#sc-button-text, #sc-input-width, #sc-button-position, #sc-gap, #sc-button-color, #sc-button-text-color, #sc-input-bg-color, #sc-input-text-color').on('input change', generateShortcode);

                $('#copy-shortcode').on('click', function(e) {
                    e.preventDefault();
                    const textarea = $('#generated-shortcode');
                    textarea.select();
                    document.execCommand('copy');

                    $('#copy-feedback').show();
                    setTimeout(function() {
                        $('#copy-feedback').hide();
                    }, 2000);
                });

                // Initial generation
                generateShortcode();
            });
            </script>

        <?php endif; ?>
    </div>
    <?php
}


// Tabbed configuration page
function agenticpress_hv_configuration_page_html() {
    if (!current_user_can('read')) return;

    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'avm_api';
    ?>
    <div class="wrap">
        <h1>Configuration</h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=agenticpress-configuration&tab=avm_api" class="nav-tab <?php echo $active_tab == 'avm_api' ? 'nav-tab-active' : ''; ?>">AVM API</a>
            <a href="?page=agenticpress-configuration&tab=google_api" class="nav-tab <?php echo $active_tab == 'google_api' ? 'nav-tab-active' : ''; ?>">Google API</a>
            <a href="?page=agenticpress-configuration&tab=ai_api" class="nav-tab <?php echo $active_tab == 'ai_api' ? 'nav-tab-active' : ''; ?>">AI API</a>
            <?php if (class_exists('GFAPI')) : ?>
                <a href="?page=agenticpress-configuration&tab=gf_api" class="nav-tab <?php echo $active_tab == 'gf_api' ? 'nav-tab-active' : ''; ?>">Gravity Forms</a>
            <?php endif; ?>
        </h2>

        <?php if (!current_user_can('agenticpress_manage_settings')) : ?>
            <div class="notice notice-info">
                <p>Some settings are hidden. To manage all settings, you need to have the "AgenticPress Super Admin" or "Administrator" role.</p>
            </div>
        <?php endif; ?>

        <form action="options.php" method="post">
            <?php
            if ($active_tab == 'avm_api') {
                settings_fields('agenticpress_hv_avm_settings');
                do_settings_sections('agenticpress_avm');
            } elseif ($active_tab == 'google_api') {
                settings_fields('agenticpress_hv_google_settings');
                do_settings_sections('agenticpress_google');
            } elseif ($active_tab == 'ai_api') {
                settings_fields('agenticpress_hv_ai_settings');
                do_settings_sections('agenticpress_ai');
            } elseif ($active_tab == 'gf_api' && class_exists('GFAPI')) {
                settings_fields('agenticpress_hv_gf_settings');
                do_settings_sections('agenticpress_gf');
            }
            submit_button('Save Settings');
            ?>
        </form>
    </div>
    <?php
}


function agenticpress_hv_api_test_page_html() {
    if (!current_user_can('agenticpress_access_api_test')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    ?>
    <div class="wrap">
        <h1>API Test Area</h1>
        <p>Use this form to make a direct call to an ATTOM API endpoint and see the raw JSON response.</p>
        <form id="api-test-form">
            <?php wp_nonce_field('agenticpress_api_test_nonce', 'api_test_nonce'); ?>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="api-test-address">Full Address</label></th>
                        <td><input type="text" id="api-test-address" name="api_test_address" class="regular-text" placeholder="e.g., 400 Broad St, Seattle, WA 98109"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="api-test-endpoint">API Endpoint</label></th>
                        <td>
                            <select id="api-test-endpoint" name="api_test_endpoint">
                                <option value="attomavm/detail">AVM Detail (recommended)</option>
                                <option value="property/detail">Property Detail</option>
                                <option value="assessment/detail">Assessment Detail</option>
                                <option value="property/expandedprofile">Expanded Profile</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <input type="submit" name="submit" id="api-test-submit" class="button button-primary" value="Run API Test">
                <span class="spinner"></span>
            </p>
        </form>
        <h3>Raw JSON Response</h3>
        <pre id="api-test-result"><code>Waiting for test...</code></pre>
    </div>
    <?php
}

function agenticpress_hv_lookups_page_html() {
    if (!current_user_can('read')) return;
    require_once plugin_dir_path(__FILE__) . 'class-agenticpress-lookups-list-table.php';
    $list_table = new AgenticPress_Lookups_List_Table();
    $list_table->prepare_items();
    ?>
     <style>
        #details-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1050; overflow-y: auto; }
        #details-modal-content { background: #fff; width: 90%; max-width: 800px; margin: 40px auto; padding: 20px 30px; border-radius: 4px; position: relative; }
        #details-modal .close-modal { position: absolute; top: 15px; right: 15px; text-decoration: none; font-size: 24px; line-height: 1; color: #666; }
        .details-section h3 { font-size: 1.2em; border-bottom: 2px solid #eee; padding-bottom: 5px; margin-top: 20px; margin-bottom: 10px; }
        .details-section ul { list-style-type: none; margin-left: 0; padding-left: 0; }
        .details-section ul ul { margin-left: 20px; padding-left: 10px; border-left: 1px solid #ddd; }
        .details-section li { padding: 5px 0; border-bottom: 1px solid #f5f5f5; }
        .details-section li strong { display: inline-block; width: 180px; font-weight: 600; color: #333; }
        #modal-body .spinner { visibility: visible; }
    </style>
    <div class="wrap">
        <h1>Property Lookups</h1>
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
            <?php $list_table->search_box('Search Properties', 'search_id'); ?>
        </form>
        <?php $list_table->display(); ?>
    </div>
    <div id="details-modal">
        <div id="details-modal-content">
            <a href="#" class="close-modal">&times;</a>
            <div id="modal-body"><span class="spinner"></span></div>
        </div>
    </div>
     <script>
    jQuery(document).ready(function($) {

        function formatValue(key, value) {
            if (value === null || value === undefined || value === "") return 'N/A';
            const k = key.toLowerCase();
            if ((k.includes('value') || k.includes('amt') || k.includes('price')) && isFinite(value)) {
                return '$' + parseInt(value, 10).toLocaleString();
            }
            if(k.includes('date') && typeof value === 'string' && value.match(/^\d{4}-\d{2}-\d{2}/)) {
                return new Date(value).toLocaleDateString();
            }
            return value;
        }

        function buildHtmlFromObject(obj) {
            if (obj === null || typeof obj !== 'object') return '';
            let html = '<ul>';
            for (const key in obj) {
                if (Object.prototype.hasOwnProperty.call(obj, key)) {
                    const value = obj[key];
                    if (typeof value === 'object' && value !== null && !Array.isArray(value) && Object.keys(value).length > 0) {
                        html += `<li><strong>${key}:</strong>${buildHtmlFromObject(value)}</li>`;
                    } else if (Array.isArray(value)) {
                        html += `<li><strong>${key}:</strong> ${value.join(', ')}</li>`;
                    } else {
                        html += `<li><strong>${key}:</strong> ${formatValue(key, value)}</li>`;
                    }
                }
            }
            html += '</ul>';
            return html;
        }

        $('table.wp-list-table').on('click', '.view-details', function(e) {
            e.preventDefault();
            const lookupId = $(this).data('id');
            const modalBody = $('#modal-body');

            modalBody.html('<span class="spinner is-active" style="float:left; margin-right: 10px;"></span> Loading details...');
            $('#details-modal').show();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'agenticpress_get_lookup_details',
                    nonce: '<?php echo wp_create_nonce('agenticpress_lookup_nonce'); ?>',
                    id: lookupId
                },
                success: function(response) {
                    if (response.success) {
                        const jsonData = JSON.parse(response.data.full_json);
                        const propertyData = jsonData.property && jsonData.property[0] ? jsonData.property[0] : null;
                        modalBody.empty();

                        if(propertyData) {
                            for (const sectionKey in propertyData) {
                                if (Object.prototype.hasOwnProperty.call(propertyData, sectionKey)) {
                                    const sectionDiv = $('<div class="details-section"></div>');
                                    sectionDiv.append(`<h3>${sectionKey.charAt(0).toUpperCase() + sectionKey.slice(1)}</h3>`);
                                    sectionDiv.append(buildHtmlFromObject(propertyData[sectionKey]));
                                    modalBody.append(sectionDiv);
                                }
                            }
                        } else {
                            modalBody.html('<p>No property data found in the response.</p>');
                        }
                    } else {
                        modalBody.html('<p>Error: ' + response.data.message + '</p>');
                    }
                },
                error: function() {
                    modalBody.html('<p>An unknown error occurred while fetching details.</p>');
                }
            });
        });

        $('.close-modal').on('click', function(e) {
            e.preventDefault();
            $('#details-modal').hide();
            $('#modal-body').empty(); // Clear content when closing
        });

        $(document).on('click', function(e) {
            if ($(e.target).is('#details-modal')) {
                $('#details-modal').hide();
                $('#modal-body').empty(); // Clear content when closing
            }
        });
    });
    </script>
    <?php
}

// -----------------------------------------------------------------------------
// Enqueue Scripts and Styles
// -----------------------------------------------------------------------------
add_action('admin_enqueue_scripts', 'agenticpress_hv_enqueue_admin_scripts');
function agenticpress_hv_enqueue_admin_scripts($hook) {
    if (strpos($hook, 'agenticpress') === false) return;

    // Use filemtime for cache-busting during development.
    $script_path = plugin_dir_path(__FILE__) . 'assets/js/ap-admin-script.js';
    $script_version = file_exists($script_path) ? filemtime($script_path) : '1.5.1';

    wp_enqueue_script('agenticpress-hv-admin-js', plugin_dir_url(__FILE__) . 'assets/js/ap-admin-script.js', ['jquery'], $script_version, true);
    wp_localize_script('agenticpress-hv-admin-js', 'agenticpress_hv_admin_ajax', ['ajax_url' => admin_url('admin-ajax.php')]);
}


add_action('wp_enqueue_scripts', 'agenticpress_hv_enqueue_scripts');
function agenticpress_hv_enqueue_scripts() {
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'agenticpress_home_value_form')) {

        // Use filemtime for cache-busting during development.
        $script_path = plugin_dir_path(__FILE__) . 'assets/js/ap-form-handler.js';
        $script_version = file_exists($script_path) ? filemtime($script_path) : '1.5.1';

        wp_enqueue_script('agenticpress-hv-js', plugin_dir_url(__FILE__) . 'assets/js/ap-form-handler.js', ['jquery'], $script_version, true);
        wp_localize_script('agenticpress-hv-js', 'agenticpress_hv_ajax', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('agenticpress_hv_nonce')]);

        $google_api_key = agenticpress_hv_get_api_key('agenticpress_hv_google_api_key', 'AGENTICPRESS_GOOGLE_API_KEY');
        if (!empty($google_api_key)) {
            $google_script_url = 'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($google_api_key) . '&libraries=places&v=beta';
            wp_enqueue_script('google-places-api', $google_script_url, [], null, true);
        }
    }
}

add_filter('script_loader_tag', 'agenticpress_hv_add_async_attribute', 10, 3);
function agenticpress_hv_add_async_attribute($tag, $handle, $src) {
    if ('google-places-api' === $handle) {
        $tag = str_replace(' src', ' async defer src', $tag);
    }
    return $tag;
}

// -----------------------------------------------------------------------------
// Shortcode
// -----------------------------------------------------------------------------
add_shortcode('agenticpress_home_value_form', 'agenticpress_hv_render_form');
function agenticpress_hv_render_form($atts) {
    $a = shortcode_atts([
        'button_text' => 'Get Home Value',
        'input_width' => '100%',
        'button_class' => '',
        'button_style' => '',
        'button_position' => 'below-left',
        'gap' => '10',
        'input_bg_color' => '',
        'input_text_color' => '',
    ], $atts);

    // --- Style & Class Generation ---
    $form_style_array = [];
    $input_container_style_array = [];

    // The <form> element is the main container for layout and sizing
    if ($a['input_width'] !== '100%') {
        $form_style_array['margin-left'] = 'auto';
        $form_style_array['margin-right'] = 'auto';
        $form_style_array['max-width'] = esc_attr($a['input_width']);
    }

    if ($a['button_position'] === 'inline') {
        $form_style_array['display'] = 'flex';
        $form_style_array['align-items'] = 'center';
        $form_style_array['gap'] = esc_attr($a['gap']) . 'px';
        $input_container_style_array['flex-grow'] = '1';
    } else {
        $align_map = [
            'below-left' => 'left',
            'below-center' => 'center',
            'below-right' => 'right'
        ];
        $form_style_array['text-align'] = $align_map[$a['button_position']] ?? 'left';
        $input_container_style_array['margin-bottom'] = '10px';
    }

    // Helper to convert style arrays to strings
    $style_array_to_string = function($style_array) {
        if (empty($style_array)) return '';
        $style_str = '';
        foreach ($style_array as $key => $value) {
            $style_str .= esc_attr($key) . ': ' . esc_attr($value) . '; ';
        }
        return 'style="' . rtrim($style_str) . '"';
    };

    $form_style = $style_array_to_string($form_style_array);
    $input_container_style = $style_array_to_string($input_container_style_array);

    $button_style_attr = !empty($a['button_style']) ? 'style="' . esc_attr($a['button_style']) . '"' : '';
    $button_class_attr = esc_attr($a['button_class']);

    // Get background color setting
    $bg_color_option = !empty($a['input_bg_color']) ? $a['input_bg_color'] : get_option('agenticpress_hv_input_bg_color', 'white');
    $background_color = ($bg_color_option === 'black') ? '#000000' : '#ffffff';

    // Get text color setting, with a default based on the background color
    if (!empty($a['input_text_color'])) {
        $text_color = esc_attr($a['input_text_color']);
    } else {
        $text_color = ($bg_color_option === 'black') ? '#ffffff' : '#000000';
    }

    $cma_form_id = get_option('agenticpress_hv_gf_cma_form');
    ob_start();
    ?>
    <style>
        #agenticpress-hv-container gmp-place-autocomplete {
            /* This is the best practice for styling the component's internals */
            --gmp-mat-color-on-surface: <?php echo $text_color; ?>;
            --gmp-mat-color-surface: <?php echo $background_color; ?>;
            width: 100%;
        }

        /* This provides a powerful, direct way to style the input "part" */
        #agenticpress-hv-container gmp-place-autocomplete::part(input) {
            color: <?php echo $text_color; ?> !important;
            background-color: <?php echo $background_color; ?> !important;
            border: 1px solid #767676 !important;
            padding: 8px;
            box-sizing: border-box;
        }

        /* This targets the browser's aggressive autofill styles */
        #agenticpress-hv-container gmp-place-autocomplete::part(input):-webkit-autofill {
            -webkit-box-shadow: 0 0 0 100px <?php echo $background_color; ?> inset !important;
            -webkit-text-fill-color: <?php echo $text_color; ?> !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        /* General styles for the results container */
        #agenticpress-combined-result-container {
            display: none;
            background: #f7f7f7;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 2rem;
            margin-top: 20px;
        }
        #agenticpress-result-address {
            font-size: 1.5rem;
            font-weight: bold;
            margin-top: 0;
            margin-bottom: 2rem;
            text-align: center;
        }
        .ap-estimated-value-wrapper {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .ap-estimated-value-wrapper strong {
            display: block;
            font-size: 1rem;
            color: #555;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }
        .ap-estimated-value-wrapper span {
            font-size: 2.5rem;
            font-weight: bold;
            color: #111;
            line-height: 1;
        }
        .ap-secondary-details-wrapper {
            display: flex;
            justify-content: space-around;
            text-align: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .ap-secondary-details-wrapper strong {
            display: block;
            font-size: 0.9rem;
            color: #555;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }
        .ap-secondary-details-wrapper span {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }
        #agenticpress-ai-summary-wrapper {
            display: none;
            background: #fff;
            border: 1px solid #e0e0e0;
            padding: 1.5rem;
            margin: 0 auto 2rem auto;
            max-width: 90%;
            border-radius: 4px;
        }
        #agenticpress-ai-summary-wrapper h4 {
            margin-top: 0;
            text-align: center;
            font-size: 1.1rem;
            color: #333;
        }
        #agenticpress-cma-form-wrapper {
             border-top: 1px solid #ddd;
             padding-top: 1.5rem;
             margin-top: 1.5rem;
        }
         #agenticpress-cma-form-wrapper h3, #agenticpress-cma-form-wrapper p {
            text-align: center;
         }
    </style>
    <div id="agenticpress-hv-container">
        <div id="agenticpress-hv-form-wrapper">
             <form id="agenticpress-hv-form" <?php echo $form_style; ?> novalidate>
                <div class="ap-input-container" <?php echo $input_container_style; ?>>
                    <gmp-place-autocomplete country="us" placeholder="type address here"></gmp-place-autocomplete>
                </div>
                <button type="submit" class="<?php echo $button_class_attr; ?>" <?php echo $button_style_attr; ?>><?php echo esc_html($a['button_text']); ?></button>
            </form>
            <div id="agenticpress-hv-error-container" style="display:none; margin-top: 10px; padding: 15px; border-left: 5px solid #dc3545; background-color: #fff5f5;"></div>
        </div>

        <div id="agenticpress-combined-result-container">
            <h3 id="agenticpress-result-address"></h3>
            <div class="agenticpress-simplified-details">
                <div class="ap-estimated-value-wrapper">
                    <strong>Estimated Value (AVM)</strong>
                    <span id="ap-result-value"></span>
                </div>
                <div id="agenticpress-ai-summary-wrapper">
                    <h4>Property Summary</h4>
                    <div id="ap-result-ai-summary"></div>
                </div>
                <div class="ap-secondary-details-wrapper">
                    <div>
                        <strong>Value Range (High-Low)</strong>
                        <span id="ap-result-range"></span>
                    </div>
                    <div>
                        <strong>Value Confidence Score</strong>
                        <span id="ap-result-confidence"></span>
                    </div>
                </div>
            </div>

            <?php if (!empty($cma_form_id) && class_exists('GFAPI')) : ?>
            <div id="agenticpress-cma-form-wrapper">
                <h3>Get The Full CMA Report</h3>
                <p>For a detailed Comparative Market Analysis, please provide your contact information below.</p>
                <?php echo gravity_form($cma_form_id, false, false, false, '', true); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}


// -----------------------------------------------------------------------------
// AJAX Handlers & Data Processing
// -----------------------------------------------------------------------------
add_action('wp_ajax_agenticpress_get_lookup_details', 'agenticpress_hv_get_lookup_details_ajax_handler');
function agenticpress_hv_get_lookup_details_ajax_handler() {
    if (!check_ajax_referer('agenticpress_lookup_nonce', 'nonce')) {
        wp_send_json_error(['message' => 'Security check failed.'], 403);
    }
    if (!current_user_can('read')) {
        wp_send_json_error(['message' => 'You do not have permission to view this data.'], 403);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'agenticpress_properties';
    $lookup_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($lookup_id === 0) {
        wp_send_json_error(['message' => 'Invalid Lookup ID.'], 400);
    }

    $lookup = $wpdb->get_row($wpdb->prepare("SELECT full_json FROM $table_name WHERE id = %d", $lookup_id));

    if (!$lookup) {
        wp_send_json_error(['message' => 'Lookup not found.'], 404);
    }

    wp_send_json_success(['full_json' => $lookup->full_json]);
}

function agenticpress_hv_get_value_from_json($property, $path) {
    $keys = explode('->', $path);
    $temp = $property;
    foreach ($keys as $key) {
        if (is_object($temp) && isset($temp->$key)) {
            $temp = $temp->$key;
        } else {
            return null;
        }
    }
    return $temp;
}


add_action('wp_ajax_agenticpress_get_home_value', 'agenticpress_hv_handle_ajax_request');
add_action('wp_ajax_nopriv_agenticpress_get_home_value', 'agenticpress_hv_handle_ajax_request');
function agenticpress_hv_handle_ajax_request() {
    if (!check_ajax_referer('agenticpress_hv_nonce', 'nonce', false)) wp_send_json_error(['message' => 'Security check failed.'], 403);

    $api_key = agenticpress_hv_get_api_key('agenticpress_hv_api_key', 'AGENTICPRESS_ATTOM_API_KEY');
    if (empty($api_key)) wp_send_json_error(['message' => 'ATTOM API Key is not configured.']);

    $address1 = isset($_POST['address1']) ? sanitize_text_field($_POST['address1']) : '';
    $address2 = isset($_POST['address2']) ? sanitize_text_field($_POST['address2']) : '';
    if (empty($address1) || empty($address2)) wp_send_json_error(['message' => 'Please select a valid address.']);

    $api_args = ['headers' => ['apikey' => $api_key, 'Accept' => 'application/json'], 'timeout' => 20];
    $address_query = http_build_query(['address1' => $address1, 'address2' => $address2]);

    $api_url = 'https://api.gateway.attomdata.com/propertyapi/v1.0.0/attomavm/detail?' . $address_query;
    $response = wp_remote_get($api_url, $api_args);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        wp_send_json_error(['message' => 'Could not retrieve property data for this address.']);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);
    $property = $data->property[0] ?? null;

    if (!$property) {
        wp_send_json_error(['message' => 'No property record found in the API response.']);
    }

    // --- Save to Database ---
    global $wpdb;
    $table_name = $wpdb->prefix . 'agenticpress_properties';

    $db_data = [
        'attomId' => agenticpress_hv_get_value_from_json($property, 'identifier->attomId'),
        'lookup_time' => current_time('mysql'),
        'full_address' => agenticpress_hv_get_value_from_json($property, 'address->oneLine'),
        'street' => agenticpress_hv_get_value_from_json($property, 'address->line1'),
        'city' => agenticpress_hv_get_value_from_json($property, 'address->locality'),
        'state' => agenticpress_hv_get_value_from_json($property, 'address->countrySubd'),
        'zip' => agenticpress_hv_get_value_from_json($property, 'address->postal1'),
        'latitude' => agenticpress_hv_get_value_from_json($property, 'location->latitude'),
        'longitude' => agenticpress_hv_get_value_from_json($property, 'location->longitude'),
        'property_type' => agenticpress_hv_get_value_from_json($property, 'summary->proptype'),
        'year_built' => agenticpress_hv_get_value_from_json($property, 'summary->yearbuilt'),
        'lot_size_acres' => agenticpress_hv_get_value_from_json($property, 'lot->lotsize1'),
        'building_size_sqft' => agenticpress_hv_get_value_from_json($property, 'building->size->livingsize'),
        'bedrooms' => agenticpress_hv_get_value_from_json($property, 'building->rooms->beds'),
        'bathrooms' => agenticpress_hv_get_value_from_json($property, 'building->rooms->bathstotal'),
        'avm_value' => agenticpress_hv_get_value_from_json($property, 'avm->amount->value'),
        'avm_confidence_score' => agenticpress_hv_get_value_from_json($property, 'avm->amount->scr'),
        'avm_value_high' => agenticpress_hv_get_value_from_json($property, 'avm->amount->high'),
        'avm_value_low' => agenticpress_hv_get_value_from_json($property, 'avm->amount->low'),
        'last_sale_date' => agenticpress_hv_get_value_from_json($property, 'sale->saleTransDate'),
        'last_sale_price' => agenticpress_hv_get_value_from_json($property, 'sale->amount->saleamt'),
        'assessed_total_value' => agenticpress_hv_get_value_from_json($property, 'assessment->assessed->assdttlvalue'),
        'market_total_value' => agenticpress_hv_get_value_from_json($property, 'assessment->market->mktttlvalue'),
        'owner_name' => agenticpress_hv_get_value_from_json($property, 'owner->owner1->fullname'),
        'full_json' => $body,
    ];

    $wpdb->insert($table_name, $db_data);
    $lookup_id = $wpdb->insert_id;

    // --- Prepare response for the front-end form ---
    $avm_low = agenticpress_hv_get_value_from_json($property, 'avm->amount->low');
    $avm_high = agenticpress_hv_get_value_from_json($property, 'avm->amount->high');

    $details = [
        'estimated_value'    => $db_data['avm_value'] ? '$' . number_format($db_data['avm_value']) : 'N/A',
        'confidence_score'   => $db_data['avm_confidence_score'] ? $db_data['avm_confidence_score'] . '%' : 'N/A',
        'avm_range'          => ($avm_low && $avm_high) ? '$' . number_format($avm_low) . ' - $' . number_format($avm_high) : 'N/A',
        'year_built'         => $db_data['year_built'] ?? 'N/A',
        'bedrooms'           => $db_data['bedrooms'] ?? 'N/A',
        'bathrooms'          => $db_data['bathrooms'] ?? 'N/A',
        'lot_size_acres'     => $db_data['lot_size_acres'] ?? 'N/A',
        'property_type'      => $db_data['property_type'] ?? 'N/A',
    ];

    // --- AI Summary Generation ---
    if (get_option('agenticpress_hv_ai_mode') === 'enabled') {
        $ai_summary = agenticpress_hv_generate_ai_summary($property, $details);
        if ($ai_summary) {
            $details['ai_summary'] = $ai_summary;
        }
    }


    wp_send_json_success(['details' => $details, 'lookup_id' => $lookup_id]);
}

/**
 * Generates a property summary using the Gemini API.
 *
 * @param object $property The property data object from the ATTOM API.
 * @param array $details The formatted details array.
 * @return string|null The generated summary, or null on failure.
 */
function agenticpress_hv_generate_ai_summary($property, $details) {
    $ai_api_key = agenticpress_hv_get_api_key('agenticpress_hv_ai_api_key', 'AGENTICPRESS_GEMINI_API_KEY');
    $instructions = get_option('agenticpress_hv_ai_instructions');

    if (empty($ai_api_key) || empty($instructions)) {
        return null;
    }

    // Build the prompt by replacing placeholders in the user's instructions
    $prompt = preg_replace_callback('/\{\{([a-zA-Z0-9_>]+)\}\}/', function($matches) use ($property, $details) {
        $key = $matches[1];

        $value_map = [
            'full_address' => agenticpress_hv_get_value_from_json($property, 'address->oneLine'),
            'locality' => agenticpress_hv_get_value_from_json($property, 'address->locality'),
            'property_type' => $details['property_type'],
            'year_built' => $details['year_built'],
            'bedrooms' => $details['bedrooms'],
            'bathrooms' => $details['bathrooms'],
            'building_size_sqft' => number_format((int)agenticpress_hv_get_value_from_json($property, 'building->size->livingsize')),
            'prkgType' => agenticpress_hv_get_value_from_json($property, 'parking->prkgType'),
            'fplccount' => agenticpress_hv_get_value_from_json($property, 'interior->fplccount'),
            'last_sale_price' => agenticpress_hv_get_value_from_json($property, 'sale->amount->saleamt') ? '$' . number_format((int)agenticpress_hv_get_value_from_json($property, 'sale->amount->saleamt')) : 'not available',
            'avm_value' => $details['estimated_value'],
            'avm_range' => $details['avm_range'],
            'confidence_score' => $details['confidence_score']
        ];

        if (isset($value_map[$key]) && !empty($value_map[$key])) {
            return $value_map[$key];
        }

        return 'details not available';

    }, $instructions);

    // -------------------------------------------------------------------------
    // --- Actual API Call to Google Gemini ---
    // -------------------------------------------------------------------------
    $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $ai_api_key;

    $request_body = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]
    ];

    $response = wp_remote_post($api_url, [
        'headers' => [
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode($request_body),
        'timeout' => 45,
    ]);

    if (is_wp_error($response)) {
        // Return the actual WordPress error message
        return "WordPress HTTP API Error: " . $response->get_error_message();
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    if ($response_code !== 200) {
        // Try to get a specific error message from the Gemini API response
        $api_error_message = $response_body['error']['message'] ?? 'An unknown API error occurred.';
        return "AI Summary Error (Code: {$response_code}): {$api_error_message}";
    }


    // Safely access the generated text from the Gemini API response structure
    $generated_text = $response_body['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if ($generated_text) {
        $paragraphs = explode("\n\n", trim($generated_text));
        $html_output = '';
        foreach ($paragraphs as $p) {
            $html_output .= '<p>' . nl2br(esc_html($p)) . '</p>';
        }
        return $html_output;
    }

    return "We're sorry, the AI summary returned an invalid format.";
}


add_action('wp_ajax_agenticpress_api_test', 'agenticpress_hv_handle_api_test_request');
function agenticpress_hv_handle_api_test_request() {
    if (!check_ajax_referer('agenticpress_api_test_nonce', 'api_test_nonce')) {
        wp_send_json_error(['message' => 'Security check failed.'], 403);
    }

    $api_key = agenticpress_hv_get_api_key('agenticpress_hv_api_key', 'AGENTICPRESS_ATTOM_API_KEY');
    if (empty($api_key)) {
        wp_send_json_error(['message' => 'ATTOM API Key is not configured.']);
    }

    $full_address = isset($_POST['address']) ? sanitize_text_field($_POST['address']) : '';
    $endpoint = isset($_POST['endpoint']) ? sanitize_text_field($_POST['endpoint']) : 'property/detail';
    if (empty($full_address)) {
        wp_send_json_error(['message' => 'Please enter a full address.']);
    }

    $parts = explode(',', $full_address, 2);
    $address1 = trim($parts[0]);
    $address2 = isset($parts[1]) ? trim($parts[1]) : '';

    if (empty($address2)) {
        wp_send_json_error(['message' => 'Address format is invalid. Please use format: Street Address, City, ST ZIP']);
    }

    $base_url = 'https://api.gateway.attomdata.com/propertyapi/v1.0.0/' . $endpoint;
    $address_query = http_build_query(['address1' => $address1, 'address2' => $address2]);
    $api_url = $base_url . '?' . $address_query;

    $api_args = ['headers' => ['apikey' => $api_key, 'Accept' => 'application/json'], 'timeout' => 20];
    $response = wp_remote_get($api_url, $api_args);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'WP_Error: ' . $response->get_error_message()]);
    }

    $body = wp_remote_retrieve_body($response);
    $response_code = wp_remote_retrieve_response_code($response);

    $json_data = json_decode($body);
    $pretty_json = json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    wp_send_json_success(['raw_json' => $pretty_json, 'status_code' => $response_code]);
}

// -----------------------------------------------------------------------------
// Gravity Forms Integration
// -----------------------------------------------------------------------------

// Only declare these functions and hooks if Gravity Forms is active
if (class_exists('GFAPI')) {

    function agenticpress_hv_gf_section_html() {
        ?>
        <style>
            .agenticpress-gf-instructions {
                background-color: #f9f9f9;
                border: 1px solid #ddd;
                padding: 15px 25px;
                margin-top: 15px;
                border-radius: 4px;
            }
            .agenticpress-gf-instructions h3 {
                margin-top: 0;
                border-bottom: 1px solid #ddd;
                padding-bottom: 10px;
            }
            .agenticpress-gf-instructions code {
                background: #e4e4e4;
                padding: 1px 5px;
                border-radius: 3px;
                font-size: 13px;
            }
            .agenticpress-gf-instructions .field-config {
                margin-left: 20px;
                border-left: 3px solid #eee;
                padding-left: 20px;
                margin-bottom: 15px;
            }
        </style>
        <p>Select the Gravity Form to use for capturing full CMA report requests. This form will appear after a user gets their initial valuation.</p>

        <div class="agenticpress-gf-instructions">
            <h3>Form Setup Instructions</h3>
            <p>To ensure this plugin can correctly pass data to your form, you must add and configure specific fields. Please check these settings carefully.</p>
            <ol>
                <li><strong>Create Your Form:</strong> If you haven't already, create a new form. Add standard fields like Name, Email, and Phone.</li>
                <li><strong>Add Hidden Fields:</strong> From the "Standard Fields" section of the form editor, add three "Hidden" fields to your form.</li>
                <li><strong>Configure Each Hidden Field:</strong> Click on each hidden field and configure it exactly as follows:
                    <div class="field-config">
                        <h4>1. Lookup ID Field (Critical)</h4>
                        <ul>
                            <li><strong>Field Label:</strong> Name this "Lookup ID".</li>
                            <li>Open the <strong>Appearance</strong> tab. In the "Custom CSS Class" box, enter exactly: <code>ap-lookup-id-field</code></li>
                        </ul>
                    </div>
                     <div class="field-config">
                        <h4>2. Address Field</h4>
                        <ul>
                             <li><strong>Field Label:</strong> Name this "Subject Address".</li>
                             <li>Open the <strong>Appearance</strong> tab. In the "Custom CSS Class" box, enter exactly: <code>ap-address-field</code></li>
                        </ul>
                    </div>
                     <div class="field-config">
                        <h4>3. Estimated Value Field</h4>
                        <ul>
                            <li><strong>Field Label:</strong> Name this "Estimated Value".</li>
                            <li>Open the <strong>Appearance</strong> tab. In the "Custom CSS Class" box, enter exactly: <code>ap-estimated-value-field</code></li>
                        </ul>
                    </div>
                </li>
                 <li><strong>(Optional) Allow Dynamic Population:</strong> For each of the three hidden fields, you can go to the "Advanced" tab and check "Allow field to be populated dynamically". This is not strictly necessary for the plugin to work but is good practice.</li>
                <li><strong>Save Your Form:</strong> Save your form and make sure it is selected in the dropdown below.</li>
            </ol>
            <p>These settings allow the plugin to find the correct fields and populate them with the data from the home value lookup before the user submits the CMA request.</p>
        </div>
        <?php
    }

    function agenticpress_hv_gf_cma_form_field_html() {
        $forms = GFAPI::get_forms();
        $selected_form = get_option('agenticpress_hv_gf_cma_form');

        echo '<select name="agenticpress_hv_gf_cma_form">';
        echo '<option value="">-- Select a Form --</option>';
        foreach ($forms as $form) {
            echo '<option value="' . esc_attr($form['id']) . '"' . selected($selected_form, $form['id'], false) . '>' . esc_html($form['title']) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">This form will be shown after a user gets their initial home value estimate.</p>';
    }

    add_action('gform_after_submission', 'agenticpress_hv_capture_cma_submission', 10, 2);
    function agenticpress_hv_capture_cma_submission($entry, $form) {
        // Ensure this only runs for the form we've selected in our settings
        $selected_form_id = get_option('agenticpress_hv_gf_cma_form');
        if (empty($selected_form_id) || $form['id'] != $selected_form_id) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'agenticpress_properties';

        $lookup_id = null;
        // Find the lookup ID by checking the CSS class of each field
        foreach ($form['fields'] as $field) {
            if (strpos($field->cssClass, 'ap-lookup-id-field') !== false) {
                $lookup_id = rgar($entry, (string) $field->id);
                break;
            }
        }

        if (empty($lookup_id)) {
            // Optional: Add logging here for debugging if the lookup ID is not found
            return;
        }

        $entry_id = $entry['id'];

        // Update the database record with the new Gravity Form entry ID
        $wpdb->update(
            $table_name,
            ['gform_entry_id' => intval($entry_id)],
            ['id' => intval($lookup_id)],
            ['%d'],
            ['%d']
        );
    }
}