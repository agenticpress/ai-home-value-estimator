<?php
/**
 * Plugin Name:       AgenticPress AI Home Values
 * Description:       Provides a home value form via a shortcode and retrieves an AVM from the ATTOM API.
 * Version:           1.6.5
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
// Admin Settings Page and Other Functions
// -----------------------------------------------------------------------------
add_action('admin_menu', 'agenticpress_hv_add_admin_menu');
function agenticpress_hv_add_admin_menu() { add_menu_page('AgenticPress AI Home Values', 'Home Values', 'manage_options', 'agenticpress_home_values', 'agenticpress_hv_settings_page_html', 'dashicons-admin-home', 25); }
add_action('admin_init', 'agenticpress_hv_settings_init');
function agenticpress_hv_settings_init() {
    register_setting('agenticpress_hv_settings', 'agenticpress_hv_api_key');
    register_setting('agenticpress_hv_settings', 'agenticpress_hv_google_api_key');
    add_settings_section('agenticpress_hv_api_section', 'API Configuration', 'agenticpress_hv_section_callback', 'agenticpress_home_values');
    add_settings_field('agenticpress_hv_api_key_field', 'ATTOM API Key', 'agenticpress_hv_api_key_field_html', 'agenticpress_home_values', 'agenticpress_hv_api_section');
    add_settings_field('agenticpress_hv_google_api_key_field', 'Google Places API Key', 'agenticpress_hv_google_api_key_field_html', 'agenticpress_home_values', 'agenticpress_hv_api_section');
}
function agenticpress_hv_section_callback() { echo '<p>Enter API keys for the required services.</p>'; }
function agenticpress_hv_api_key_field_html() { $api_key = get_option('agenticpress_hv_api_key'); echo '<input type="text" name="agenticpress_hv_api_key" value="' . esc_attr($api_key) . '" size="50" placeholder="Enter your ATTOM API key">'; }
function agenticpress_hv_google_api_key_field_html() { $google_api_key = get_option('agenticpress_hv_google_api_key'); echo '<input type="text" name="agenticpress_hv_google_api_key" value="' . esc_attr($google_api_key) . '" size="50" placeholder="Enter your Google Places API key"><p class="description">Required for the address autocomplete feature.</p>'; }

function agenticpress_hv_settings_page_html() {
    if (!current_user_can('manage_options')) return;
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?> Settings</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('agenticpress_hv_settings');
            do_settings_sections('agenticpress_home_values');
            submit_button('Save API Keys');
            ?>
        </form>
        <hr>
        <h2>Shortcode Usage</h2>
        <p>Use the following shortcodes to display the home value form on any page, post, or text widget.</p>
        <style>
            .shortcode-display { background: #f6f7f7; border-left: 4px solid #72aee6; padding: 15px; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; }
            .shortcode-display code { font-size: 14px; padding: 5px 8px; background: #fff; border: 1px solid #ddd; user-select: all; -webkit-user-select: all; -moz-user-select: all; }
        </style>
        <h3>Basic Usage</h3>
        <div class="shortcode-display">
            <p>To display the form with the default button text:</p>
            <div>
                <code id="basic-shortcode">[agenticpress_home_value_form]</code>
                <button type="button" class="button" data-clipboard-target="#basic-shortcode">Copy</button>
            </div>
        </div>
        <h3>Advanced Usage (Custom Button)</h3>
        <div class="shortcode-display">
            <p>To customize the text on the submit button:</p>
            <div>
                <code id="advanced-shortcode">[agenticpress_home_value_form button_text="See My Home Value"]</code>
                <button type="button" class="button" data-clipboard-target="#advanced-shortcode">Copy</button>
            </div>
        </div>
    </div>
    <?php
}

add_action('admin_enqueue_scripts', 'agenticpress_hv_enqueue_admin_scripts');
function agenticpress_hv_enqueue_admin_scripts($hook) { if ('toplevel_page_agenticpress_home_values' != $hook) return; wp_enqueue_script('agenticpress-hv-admin-js', plugin_dir_url(__FILE__) . 'assets/js/ap-admin-script.js', [], '1.6.5', true); }
add_action('wp_enqueue_scripts', 'agenticpress_hv_enqueue_scripts');
function agenticpress_hv_enqueue_scripts() {
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'agenticpress_home_value_form')) {
        wp_enqueue_script('agenticpress-hv-js', plugin_dir_url(__FILE__) . 'assets/js/ap-form-handler.js', ['jquery'], '1.6.5', true);
        wp_localize_script('agenticpress-hv-js', 'agenticpress_hv_ajax', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('agenticpress_hv_nonce')]);
        $google_api_key = get_option('agenticpress_hv_google_api_key');
        if (!empty($google_api_key)) {
            $google_script_url = 'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($google_api_key) . '&libraries=places&v=beta';
            wp_enqueue_script('google-places-api', $google_script_url, [], null, true);
        }
    }
}
add_filter('script_loader_tag', 'agenticpress_hv_add_async_attribute', 10, 3);
function agenticpress_hv_add_async_attribute($tag, $handle, $src) { if ('google-places-api' === $handle) { $tag = str_replace(' src', ' async defer src', $tag); } return $tag; }
add_shortcode('agenticpress_home_value_form', 'agenticpress_hv_render_form');
function agenticpress_hv_render_form($atts) {
    $a = shortcode_atts(['button_text' => 'Get Home Value'], $atts);
    ob_start();
    ?>
    <style> #agenticpress-hv-form p { margin-bottom: 10px; } #agenticpress-hv-form label { display: block; margin-bottom: 5px; font-weight: bold; } #agenticpress-hv-form input, #agenticpress_hv_form gmp-place-autocomplete input { width: 100%; padding: 8px; box-sizing: border-box; } #agenticpress-hv-result { margin-top: 20px; padding: 15px; border-left: 5px solid #ccc; font-size: 1.1em; } #agenticpress-hv-result.success { border-color: #28a745; background-color: #f0fff4; } #agenticpress-hv-result.error { border-color: #dc3545; background-color: #fff5f5; } .agenticpress-hv-details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 15px; } .agenticpress-hv-details-grid div { background: #f9f9f9; padding: 10px; border-radius: 4px; } .agenticpress-hv-details-grid strong { display: block; margin-bottom: 5px; color: #333; } </style>
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
// AJAX Handler (Definitive version with robust parsing)
// -----------------------------------------------------------------------------
add_action('wp_ajax_agenticpress_get_home_value', 'agenticpress_hv_handle_ajax_request');
add_action('wp_ajax_nopriv_agenticpress_get_home_value', 'agenticpress_hv_handle_ajax_request');
function agenticpress_hv_handle_ajax_request() {
    if (!check_ajax_referer('agenticpress_hv_nonce', 'nonce', false)) wp_send_json_error(['message' => 'Security check failed.'], 403);

    $api_key = get_option('agenticpress_hv_api_key');
    if (empty($api_key)) wp_send_json_error(['message' => 'ATTOM API Key is not configured.']);

    $address1 = isset($_POST['address1']) ? sanitize_text_field($_POST['address1']) : '';
    $address2 = isset($_POST['address2']) ? sanitize_text_field($_POST['address2']) : '';
    if (empty($address1) || empty($address2)) wp_send_json_error(['message' => 'Please select a valid address.']);

    $api_args = ['headers' => ['apikey' => $api_key, 'Accept' => 'application/json'], 'timeout' => 15];
    $address_query = http_build_query(['address1' => $address1, 'address2' => $address2]);

    // --- STEP 1: Get Property Details ---
    $detail_url = 'https://api.gateway.attomdata.com/propertyapi/v1.0.0/property/detail?' . $address_query;
    $detail_response = wp_remote_get($detail_url, $api_args);

    if (is_wp_error($detail_response) || wp_remote_retrieve_response_code($detail_response) !== 200) {
        wp_send_json_error(['message' => 'Could not retrieve property details for this address.']);
    }
    $detail_data = json_decode(wp_remote_retrieve_body($detail_response));
    $property = $detail_data->property[0] ?? null;

    if (!$property) {
        wp_send_json_error(['message' => 'No property record found for this address.']);
    }

    // --- STEP 2: Get AVM Details ---
    $avm_url = 'https://api.gateway.attomdata.com/propertyapi/v1.0.0/attomavm/detail?' . $address_query;
    $avm_response = wp_remote_get($avm_url, $api_args);
    $avm_data = null;
    if (!is_wp_error($avm_response) && wp_remote_retrieve_response_code($avm_response) === 200) {
        $avm_data = json_decode(wp_remote_retrieve_body($avm_response));
    }

    // --- Intelligent Data Extraction ---
    function find_attom_value($obj, $paths) {
        if (!$obj) return null;
        foreach ((array)$paths as $path) {
            $keys = explode('->', $path);
            $temp = $obj;
            $found = true;
            foreach ($keys as $key) {
                if (!isset($temp->$key)) {
                    $found = false;
                    break;
                }
                $temp = $temp->$key;
            }
            if ($found) return $temp;
        }
        return null;
    }

    // --- Combine results using all known possible paths for each data point ---
    $estimated_value = find_attom_value($avm_data, 'property->0->avm->amount->value');
    $assessed_value  = find_attom_value($property, ['assessment->assessed->assdttlvalue', 'assessment->assessment->assdttlvalue']);
    $year_built      = find_attom_value($property, ['building->summary->yearbuilt', 'summary->yearbuilt']);
    $lot_size_acres  = find_attom_value($property, ['lot->lotSize1', 'summary->lotSize1']);
    $bedrooms        = find_attom_value($property, ['building->summary->beds', 'summary->beds']);
    $bathrooms       = find_attom_value($property, ['building->summary->bathsfull', 'summary->bathsfull']);
    $property_type   = find_attom_value($property, 'summary->proptype');

    $details = [
        'estimated_value' => $estimated_value ? '$' . number_format($estimated_value) : 'N/A',
        'assessed_value'  => $assessed_value ? '$' . number_format($assessed_value) : 'N/A',
        'year_built'      => $year_built ?? 'N/A',
        'lot_size_acres'  => $lot_size_acres ?? 'N/A',
        'bedrooms'        => $bedrooms ?? 'N/A',
        'bathrooms'       => $bathrooms ?? 'N/A',
        'property_type'   => $property_type ?? 'N/A',
    ];

    wp_send_json_success(['details' => $details]);

    wp_die();
}