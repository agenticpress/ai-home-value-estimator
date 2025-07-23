<?php
/**
 * Plugin Name:       AgenticPress AI Home Values
 * Description:       Provides a home value form via a shortcode and retrieves an AVM from the ATTOM API.
 * Version:           2.4.0
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
// Activation Hook to Create/Update Database Table
// -----------------------------------------------------------------------------
register_activation_hook(__FILE__, 'agenticpress_hv_create_property_table');
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
        full_json LONGTEXT,
        PRIMARY KEY (id),
        INDEX attomId (attomId)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// -----------------------------------------------------------------------------
// Admin Menu and Settings
// -----------------------------------------------------------------------------
add_action('admin_menu', 'agenticpress_hv_add_admin_menu');
function agenticpress_hv_add_admin_menu() {
    add_menu_page('Home Values', 'Home Values', 'manage_options', 'agenticpress_home_values', 'agenticpress_hv_welcome_page_html', 'dashicons-admin-home', 25);
    add_submenu_page('agenticpress_home_values', 'Welcome', 'Welcome', 'manage_options', 'agenticpress_home_values', 'agenticpress_hv_welcome_page_html');
    add_submenu_page('agenticpress_home_values', 'Configuration', 'Configuration', 'manage_options', 'agenticpress-configuration', 'agenticpress_hv_configuration_page_html');
    add_submenu_page('agenticpress_home_values', 'API Test Area', 'API Test Area', 'manage_options', 'agenticpress_api_test', 'agenticpress_hv_api_test_page_html');
    add_submenu_page('agenticpress_home_values', 'Lookups', 'Lookups', 'manage_options', 'agenticpress_lookups', 'agenticpress_hv_lookups_page_html');
}

add_action('admin_init', 'agenticpress_hv_settings_init');
function agenticpress_hv_settings_init() {
    // Group for ATTOM Settings
    register_setting('agenticpress_hv_attom_settings', 'agenticpress_hv_api_key');
    add_settings_section('agenticpress_hv_attom_section', 'ATTOM API Settings', null, 'agenticpress_attom');
    add_settings_field('agenticpress_hv_api_key_field', 'ATTOM API Key', 'agenticpress_hv_api_key_field_html', 'agenticpress_attom', 'agenticpress_hv_attom_section');

    // Group for Google Settings
    register_setting('agenticpress_hv_google_settings', 'agenticpress_hv_google_api_key');
    add_settings_section('agenticpress_hv_google_section', 'Google Places API Settings', null, 'agenticpress_google');
    add_settings_field('agenticpress_hv_google_api_key_field', 'Google Places API Key', 'agenticpress_hv_google_api_key_field_html', 'agenticpress_google', 'agenticpress_hv_google_section');

    // Group for AI Settings
    register_setting('agenticpress_hv_ai_settings', 'agenticpress_hv_ai_mode');
    register_setting('agenticpress_hv_ai_settings', 'agenticpress_hv_ai_api_key');
    add_settings_section('agenticpress_hv_ai_section', 'AI Settings', null, 'agenticpress_ai');
    add_settings_field('agenticpress_hv_ai_mode_field', 'AI Mode', 'agenticpress_hv_ai_mode_field_html', 'agenticpress_ai', 'agenticpress_hv_ai_section');
    add_settings_field('agenticpress_hv_ai_api_key_field', 'AI API Key', 'agenticpress_hv_ai_api_key_field_html', 'agenticpress_ai', 'agenticpress_hv_ai_section');
}

// Field render functions
function agenticpress_hv_api_key_field_html() {
    $api_key = get_option('agenticpress_hv_api_key');
    echo '<input type="text" name="agenticpress_hv_api_key" value="' . esc_attr($api_key) . '" class="regular-text" placeholder="Enter your ATTOM API key">';
}

function agenticpress_hv_google_api_key_field_html() {
    $google_api_key = get_option('agenticpress_hv_google_api_key');
    echo '<input type="text" name="agenticpress_hv_google_api_key" value="' . esc_attr($google_api_key) . '" class="regular-text" placeholder="Enter your Google Places API key">';
    echo '<p class="description">Required for the address autocomplete feature.</p>';
}

function agenticpress_hv_ai_mode_field_html() {
    $ai_mode = get_option('agenticpress_hv_ai_mode', 'disabled');
    echo '<select name="agenticpress_hv_ai_mode">';
    echo '<option value="disabled"' . selected($ai_mode, 'disabled', false) . '>Disabled</option>';
    echo '<option value="enabled"' . selected($ai_mode, 'enabled', false) . '>Enabled</option>';
    echo '</select>';
    echo '<p class="description">Enable or disable AI-powered features.</p>';
}

function agenticpress_hv_ai_api_key_field_html() {
    $ai_api_key = get_option('agenticpress_hv_ai_api_key');
    echo '<input type="text" name="agenticpress_hv_ai_api_key" value="' . esc_attr($ai_api_key) . '" class="regular-text" placeholder="Enter your AI API Key">';
}

function agenticpress_hv_welcome_page_html() {
    if (!current_user_can('manage_options')) return;

    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'welcome';
    ?>
    <div class="wrap">
        <h1>Welcome to AgenticPress AI Home Values</h1>

        <h2 class="nav-tab-wrapper">
            <a href="?page=agenticpress_home_values&tab=welcome" class="nav-tab <?php echo $active_tab == 'welcome' ? 'nav-tab-active' : ''; ?>">Welcome</a>
            <a href="?page=agenticpress_home_values&tab=shortcodes" class="nav-tab <?php echo $active_tab == 'shortcodes' ? 'nav-tab-active' : ''; ?>">Shortcodes</a>
        </h2>

        <?php if ($active_tab == 'welcome') : ?>
            <div class="welcome-panel">
                <div class="welcome-panel-content">
                    <h2>Powerful Property Valuations, Simplified</h2>
                    <p class="about-description">This plugin provides an easy-to-use home value lookup form for your website. By integrating with the ATTOM API and Google Places API, you can offer visitors accurate property valuations and detailed information with a seamless, AJAX-powered user experience.</p>
                    <div class="welcome-panel-column-container">
                        <div class="welcome-panel-column">
                            <h3>Key Features</h3>
                            <ul>
                                <li>Simple shortcode implementation.</li>
                                <li>Google Places API for address autocomplete.</li>
                                <li>Displays comprehensive property information.</li>
                                <li>AJAX-powered for a smooth experience without page reloads.</li>
                                <li>All lookups are saved to a searchable database table.</li>
                            </ul>
                        </div>
                        <div class="welcome-panel-column">
                            <h3>Getting Started</h3>
                            <p>To get started, please visit the <a href="<?php echo admin_url('admin.php?page=agenticpress-configuration'); ?>">Configuration</a> page to enter your API keys.</p>
                            <ol>
                                <li>Enter your ATTOM API Key.</li>
                                <li>Enter your Google Places API Key.</li>
                                <li>(Optional) Configure your AI API Key.</li>
                                <li>Place the shortcode on any page or post.</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        <?php else : ?>
            <div id="shortcodes" class="welcome-panel">
                 <div class="welcome-panel-content">
                    <h2>Shortcode Usage</h2>
                    <p>Use the following shortcodes to display the home value form on any page, post, or text widget.</p>
                    <style>
                        .shortcode-display { background: #f6f7f7; border-left: 4px solid #72aee6; padding: 15px; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; }
                        .shortcode-display code { font-size: 14px; padding: 5px 8px; background: #fff; border: 1px solid #ddd; user-select: all; -webkit-user-select: all; -moz-user-select: all; }
                    </style>
                    <h3>Basic Usage</h3>
                    <p>To display the form with the default button text:</p>
                    <div class="shortcode-display">
                        <code id="basic-shortcode">[agenticpress_home_value_form]</code>
                        <button type="button" class="button" data-clipboard-target="#basic-shortcode">Copy</button>
                    </div>

                    <h3>Custom Button Text</h3>
                    <p>To customize the text on the submit button:</p>
                    <div class="shortcode-display">
                        <code id="custom-text-shortcode">[agenticpress_home_value_form button_text="See My Home Value"]</code>
                        <button type="button" class="button" data-clipboard-target="#custom-text-shortcode">Copy</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}


// Tabbed configuration page
function agenticpress_hv_configuration_page_html() {
    if (!current_user_can('manage_options')) return;

    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'attom_api';
    ?>
    <div class="wrap">
        <h1>Configuration</h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=agenticpress-configuration&tab=attom_api" class="nav-tab <?php echo $active_tab == 'attom_api' ? 'nav-tab-active' : ''; ?>">ATTOM API</a>
            <a href="?page=agenticpress-configuration&tab=google_api" class="nav-tab <?php echo $active_tab == 'google_api' ? 'nav-tab-active' : ''; ?>">Google API</a>
            <a href="?page=agenticpress-configuration&tab=ai_api" class="nav-tab <?php echo $active_tab == 'ai_api' ? 'nav-tab-active' : ''; ?>">AI API Key</a>
        </h2>

        <form action="options.php" method="post">
            <?php
            if ($active_tab == 'attom_api') {
                settings_fields('agenticpress_hv_attom_settings');
                do_settings_sections('agenticpress_attom');
            } elseif ($active_tab == 'google_api') {
                settings_fields('agenticpress_hv_google_settings');
                do_settings_sections('agenticpress_google');
            } else {
                settings_fields('agenticpress_hv_ai_settings');
                do_settings_sections('agenticpress_ai');
            }
            submit_button('Save Settings');
            ?>
        </form>
    </div>
    <?php
}


function agenticpress_hv_api_test_page_html() {
    if (!current_user_can('manage_options')) return;
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
    if (!current_user_can('manage_options')) return;
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
    wp_enqueue_script('agenticpress-hv-admin-js', plugin_dir_url(__FILE__) . 'assets/js/ap-admin-script.js', ['jquery'], '2.4.0', true);
    wp_localize_script('agenticpress-hv-admin-js', 'agenticpress_hv_admin_ajax', ['ajax_url' => admin_url('admin-ajax.php')]);
}


add_action('wp_enqueue_scripts', 'agenticpress_hv_enqueue_scripts');
function agenticpress_hv_enqueue_scripts() {
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'agenticpress_home_value_form')) {
        wp_enqueue_script('agenticpress-hv-js', plugin_dir_url(__FILE__) . 'assets/js/ap-form-handler.js', ['jquery'], '2.4.0', true);
        wp_localize_script('agenticpress-hv-js', 'agenticpress_hv_ajax', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('agenticpress_hv_nonce')]);
        $google_api_key = get_option('agenticpress_hv_google_api_key');
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
    $a = shortcode_atts(['button_text' => 'Get Home Value'], $atts);
    ob_start();
    ?>
    <style>
        #agenticpress-hv-form p { margin-bottom: 10px; }
        #agenticpress-hv-form label { display: block; margin-bottom: 5px; font-weight: bold; }
        #agenticpress-hv-form input, #agenticpress_hv_form gmp-place-autocomplete input { width: 100%; padding: 8px; box-sizing: border-box; }
        #agenticpress-hv-result { margin-top: 20px; padding: 15px; border-left: 5px solid #ccc; font-size: 1.1em; }
        #agenticpress-hv-result.success { border-color: #28a745; background-color: #f0fff4; }
        #agenticpress-hv-result.error { border-color: #dc3545; background-color: #fff5f5; }
        .agenticpress-hv-details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 15px; }
        .agenticpress-hv-details-grid div { background: #f9f9f9; padding: 10px; border-radius: 4px; }
        .agenticpress-hv-details-grid strong { display: block; margin-bottom: 5px; color: #333; }
    </style>
    <div id="agenticpress-hv-container">
        <form id="agenticpress-hv-form" novalidate>
            <p> <label>Enter Full Address</label> <gmp-place-autocomplete country="us" placeholder="e.g., 123 Main St, Bellingham, WA 98225"></gmp-place-autocomplete> </p>
            <button type="submit"><?php echo esc_html($a['button_text']); ?></button>
        </form>
        <div id="agenticpress-hv-result"></div>
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
    if (!current_user_can('manage_options')) {
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

    // The full_json is already a JSON string, so we send it directly.
    wp_send_json_success(['full_json' => $lookup->full_json]);
}

function agenticpress_hv_get_value_from_json($property, $path) {
    $keys = explode('->', $path);
    $temp = $property;
    foreach ($keys as $key) {
        if (!isset($temp->$key)) return null;
        $temp = $temp->$key;
    }
    return $temp;
}

add_action('wp_ajax_agenticpress_get_home_value', 'agenticpress_hv_handle_ajax_request');
add_action('wp_ajax_nopriv_agenticpress_get_home_value', 'agenticpress_hv_handle_ajax_request');
function agenticpress_hv_handle_ajax_request() {
    if (!check_ajax_referer('agenticpress_hv_nonce', 'nonce', false)) wp_send_json_error(['message' => 'Security check failed.'], 403);

    $api_key = get_option('agenticpress_hv_api_key');
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

    wp_send_json_success(['details' => $details]);
}


add_action('wp_ajax_agenticpress_api_test', 'agenticpress_hv_handle_api_test_request');
function agenticpress_hv_handle_api_test_request() {
    if (!check_ajax_referer('agenticpress_api_test_nonce', 'api_test_nonce')) {
        wp_send_json_error(['message' => 'Security check failed.'], 403);
    }

    $api_key = get_option('agenticpress_hv_api_key');
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