<?php
/**
 * Plugin Name:       AI Home Value Estimator
 * Plugin URI:        https://wordpress.org/plugins/ai-home-value-estimator/
 * Description:       Create professional home value estimate forms with AI-powered property summaries. Integrates with ATTOM Data API for accurate valuations, Google Places for address autocomplete, and Gemini AI for intelligent property analysis. Perfect for real estate websites and lead generation.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            AgenticPress
 * Author URI:        https://agenticpress.ai
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-home-value-estimator
 * Domain Path:       /languages
 * Network:           false
 * 
 * AI Home Value Estimator is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 * 
 * AI Home Value Estimator is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with AI Home Value Estimator. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
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
    // Set plugin version for upgrade tracking
    $current_version = '1.0.0';
    $installed_version = get_option('agenticpress_hv_plugin_version', '0.0.0');
    
    // Create the Super Admin role with specific capabilities
    add_role(
        'agenticpress_super_admin',
        'AgenticPress Super Admin',
        [
            'read' => true, // Basic access to the dashboard
            'agenticpress_manage_settings' => true,
        ]
    );

    // Add capabilities to the main site administrator role as well
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->add_cap('agenticpress_manage_settings');
    }

    // Run database creation/upgrade
    agenticpress_hv_create_property_table();
    agenticpress_hv_create_security_log_table();
    
    // Check if upgrade is needed
    if (version_compare($installed_version, $current_version, '<')) {
        agenticpress_hv_upgrade_plugin($installed_version, $current_version);
    }
    
    // Update plugin version
    update_option('agenticpress_hv_plugin_version', $current_version);
    
    // Set activation timestamp
    if (!get_option('agenticpress_hv_activated_time')) {
        update_option('agenticpress_hv_activated_time', current_time('mysql'));
    }
}

function agenticpress_hv_deactivate() {
    // Clean up by removing the custom role and capabilities
    remove_role('agenticpress_super_admin');
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->remove_cap('agenticpress_manage_settings');
    }
}


// -----------------------------------------------------------------------------
// Plugin Upgrade System
// -----------------------------------------------------------------------------

/**
 * Handle plugin upgrades between versions
 */
function agenticpress_hv_upgrade_plugin($from_version, $to_version) {
    // Log the upgrade
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log("AgenticPress HV: Upgrading from version $from_version to $to_version");
    }
    
    // Version-specific upgrade routines
    if (version_compare($from_version, '1.0.0', '<')) {
        agenticpress_hv_upgrade_to_1_0_0();
    }
    
    // Future version upgrades would go here
    // if (version_compare($from_version, '1.1.0', '<')) {
    //     agenticpress_hv_upgrade_to_1_1_0();
    // }
    
    // Clear any relevant caches after upgrade
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
}

/**
 * Upgrade routines for version 1.0.0
 */
function agenticpress_hv_upgrade_to_1_0_0() {
    // Set default security settings for existing installations
    if (!get_option('agenticpress_hv_enable_advanced_protection')) {
        update_option('agenticpress_hv_enable_advanced_protection', true);
    }
    
    if (!get_option('agenticpress_hv_captcha_threshold')) {
        update_option('agenticpress_hv_captcha_threshold', 0.5);
    }
    
    // Ensure security log table exists with new schema
    agenticpress_hv_create_security_log_table();
}

/**
 * Check database version and upgrade if needed
 */
function agenticpress_hv_check_database_version() {
    $current_db_version = '1.2'; // Increment this when database changes are made
    $installed_db_version = get_option('agenticpress_hv_db_version', '1.0');
    
    if (version_compare($installed_db_version, $current_db_version, '<')) {
        agenticpress_hv_upgrade_database($installed_db_version, $current_db_version);
        update_option('agenticpress_hv_db_version', $current_db_version);
    }
}

/**
 * Handle database upgrades
 */
function agenticpress_hv_upgrade_database($from_version, $to_version) {
    if (version_compare($from_version, '1.1', '<')) {
        // Add new columns to existing tables if needed
        agenticpress_hv_upgrade_properties_table_v1_1();
    }
    
    if (version_compare($from_version, '1.2', '<')) {
        // Ensure security log table has latest schema
        agenticpress_hv_create_security_log_table();
    }
}

/**
 * Upgrade properties table to version 1.1 (example)
 */
function agenticpress_hv_upgrade_properties_table_v1_1() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'agenticpress_properties';
    
    // Check if column exists before adding
    $column_exists = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME,
            $table_name,
            'security_score'
        )
    );
    
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN security_score DECIMAL(3,2) DEFAULT NULL AFTER confidence_score");
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
    
    // Check and upgrade database if needed
    agenticpress_hv_check_database_version();

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
    
    // Set initial database version
    if (!get_option('agenticpress_hv_db_version')) {
        update_option('agenticpress_hv_db_version', '1.2');
    }
}

/**
 * Create security log table
 */
function agenticpress_hv_create_security_log_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'agenticpress_security_log';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        timestamp datetime NOT NULL,
        event_type varchar(50) NOT NULL DEFAULT 'rate_limit_violation',
        ip_address varchar(45) NOT NULL,
        request_count int(11) DEFAULT NULL,
        tier varchar(20) DEFAULT NULL,
        user_agent text,
        referer text,
        request_method varchar(10) DEFAULT NULL,
        additional_data JSON,
        PRIMARY KEY (id),
        KEY idx_timestamp (timestamp),
        KEY idx_ip_address (ip_address),
        KEY idx_event_type (event_type),
        KEY idx_tier (tier)
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
    add_submenu_page('agenticpress_home_values', 'Lookups', 'Lookups', 'read', 'agenticpress_lookups', 'agenticpress_hv_lookups_page_html');
    add_submenu_page('agenticpress_home_values', 'Security', 'Security', 'agenticpress_manage_settings', 'agenticpress_security', 'agenticpress_hv_security_page_html');
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


    // Group for AI Settings
    register_setting('agenticpress_hv_ai_settings', 'agenticpress_hv_ai_mode');
    register_setting('agenticpress_hv_ai_settings', 'agenticpress_hv_ai_limit');
    register_setting('agenticpress_hv_ai_settings', 'agenticpress_hv_ai_instructions');
    add_settings_section('agenticpress_hv_ai_section', 'AI Settings', 'agenticpress_hv_ai_section_html', 'agenticpress_ai');
    add_settings_field('agenticpress_hv_ai_mode_field', 'AI Mode', 'agenticpress_hv_ai_mode_field_html', 'agenticpress_ai', 'agenticpress_hv_ai_section');
    add_settings_field('agenticpress_hv_ai_api_key_field', 'Gemini API Key', 'agenticpress_hv_ai_api_key_field_html', 'agenticpress_ai', 'agenticpress_hv_ai_section');
    add_settings_field('agenticpress_hv_ai_limit_field', 'Monthly AI Requests Limit', 'agenticpress_hv_ai_limit_field_html', 'agenticpress_ai', 'agenticpress_hv_ai_section');
    add_settings_field('agenticpress_hv_ai_instructions_field', 'AI Instructions', 'agenticpress_hv_ai_instructions_field_html', 'agenticpress_ai', 'agenticpress_hv_ai_section');

    // Group for Security Settings
    register_setting('agenticpress_hv_security_settings', 'agenticpress_hv_recaptcha_site_key');
    register_setting('agenticpress_hv_security_settings', 'agenticpress_hv_recaptcha_secret_key');
    register_setting('agenticpress_hv_security_settings', 'agenticpress_hv_enable_captcha');
    register_setting('agenticpress_hv_security_settings', 'agenticpress_hv_captcha_threshold');
    register_setting('agenticpress_hv_security_settings', 'agenticpress_hv_enable_advanced_protection');
    add_settings_section('agenticpress_hv_security_section', 'Security & Bot Protection', 'agenticpress_hv_security_section_html', 'agenticpress_security');
    add_settings_field('agenticpress_hv_enable_captcha_field', 'Enable reCAPTCHA', 'agenticpress_hv_enable_captcha_field_html', 'agenticpress_security', 'agenticpress_hv_security_section');
    add_settings_field('agenticpress_hv_recaptcha_site_key_field', 'reCAPTCHA Site Key', 'agenticpress_hv_recaptcha_site_key_field_html', 'agenticpress_security', 'agenticpress_hv_security_section');
    add_settings_field('agenticpress_hv_recaptcha_secret_key_field', 'reCAPTCHA Secret Key', 'agenticpress_hv_recaptcha_secret_key_field_html', 'agenticpress_security', 'agenticpress_hv_security_section');
    add_settings_field('agenticpress_hv_captcha_threshold_field', 'CAPTCHA Score Threshold', 'agenticpress_hv_captcha_threshold_field_html', 'agenticpress_security', 'agenticpress_hv_security_section');
    add_settings_field('agenticpress_hv_enable_advanced_protection_field', 'Enable Advanced Bot Protection', 'agenticpress_hv_enable_advanced_protection_field_html', 'agenticpress_security', 'agenticpress_hv_security_section');

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
    ?>
    <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #e74c3c; margin: 10px 0;">
        <h3 style="margin-top: 0;">🏠 ATTOM Data API - Real Estate Intelligence</h3>
        <p>Get accurate property valuations and comprehensive real estate data from the industry's most trusted source.</p>
        
        <h4>📋 How to Get Your ATTOM API Key:</h4>
        <ol style="line-height: 1.6;">
            <li><strong>Visit ATTOM Developer Platform:</strong> <a href="https://api.developer.attomdata.com/signup" target="_blank" rel="noopener">https://api.developer.attomdata.com/signup</a></li>
            <li><strong>Create Account:</strong> Fill out registration form with business details</li>
            <li><strong>Verify Email:</strong> Check your email and verify your account</li>
            <li><strong>Get Free API Key:</strong> Receive your API key immediately after verification</li>
            <li><strong>Copy Your Key:</strong> Copy your API key from the developer dashboard</li>
            <li><strong>Paste Below:</strong> Enter your API key in the field below</li>
        </ol>
        
        <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0; border-radius: 4px;">
            <strong>🆓 FREE Trial:</strong> Get <strong>30 days FREE</strong> with access to 158+ million properties nationwide!
        </div>
        
        <h4>📊 What's Included:</h4>
        <ul style="line-height: 1.6;">
            <li><strong>Property Valuations (AVM):</strong> Automated valuation models for accurate estimates</li>
            <li><strong>Property Details:</strong> Square footage, bedrooms, bathrooms, lot size</li>
            <li><strong>Sales History:</strong> Recent transactions and price trends</li>
            <li><strong>Tax Information:</strong> Assessment values and tax records</li>
            <li><strong>Market Data:</strong> Neighborhood comparables and analytics</li>
        </ul>
        
        <h4>💰 Pricing Information:</h4>
        <ul style="line-height: 1.6;">
            <li><strong>Free Trial:</strong> 30 days with full access</li>
            <li><strong>Startup Plans:</strong> Flexible pricing for small businesses</li>
            <li><strong>Enterprise:</strong> Custom pricing for high-volume usage</li>
            <li><strong>Contact Sales:</strong> <strong>(800) 659-2877</strong> for pricing details</li>
        </ul>
        
        <h4>🔒 Security Best Practices:</h4>
        <ul style="line-height: 1.6;">
            <li><strong>Environment Variable (Recommended):</strong> Add <code>define('AGENTICPRESS_ATTOM_API_KEY', 'your-key-here');</code> to your <code>wp-config.php</code> file</li>
            <li><strong>Keep your API key private</strong> - never display it publicly</li>
            <li><strong>Monitor usage</strong> through the ATTOM developer dashboard</li>
        </ul>
        
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 4px;">
            <strong>💡 Pro Tip:</strong> ATTOM covers <strong>99% of US properties</strong> with data from 3,100+ counties. Perfect for real estate websites!
        </div>
        
        <div style="background: #dff0d8; border: 1px solid #d6e9c6; padding: 10px; margin: 10px 0; border-radius: 4px;">
            <strong>✅ Ready to Start?</strong> Your ATTOM API key enables instant property valuations and detailed real estate data!
        </div>
    </div>
    <?php
}

function agenticpress_hv_google_section_html() {
    ?>
    <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #4285f4; margin: 10px 0;">
        <h3 style="margin-top: 0;">🗺️ Google Places API - Address Autocomplete</h3>
        <p>Enable intelligent address search with Google's powerful Places API for a smooth user experience.</p>
        
        <h4>📋 How to Get Your Google Places API Key:</h4>
        <ol style="line-height: 1.6;">
            <li><strong>Go to Google Cloud Console:</strong> <a href="https://console.cloud.google.com/google/maps-apis/start" target="_blank" rel="noopener">https://console.cloud.google.com/google/maps-apis/start</a></li>
            <li><strong>Sign in</strong> with your Google account (create one if needed)</li>
            <li><strong>Create Project:</strong> Click "New Project" and give it a name</li>
            <li><strong>Enable Places API:</strong> Search for "Places API" and click "Enable"</li>
            <li><strong>Create Credentials:</strong> Go to "Credentials" → "Create Credentials" → "API Key"</li>
            <li><strong>Secure Your Key:</strong> Set restrictions for HTTP referrers (your website domain)</li>
            <li><strong>Enable Billing:</strong> Add billing account for usage beyond free tier</li>
            <li><strong>Copy Your Key:</strong> Copy your API key and paste below</li>
        </ol>
        
        <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0; border-radius: 4px;">
            <strong>🆓 FREE Tier:</strong> Get <strong>1,000 FREE</strong> address lookups per day (up to 150,000 with billing enabled)!
        </div>
        
        <h4>✨ What You'll Get:</h4>
        <ul style="line-height: 1.6;">
            <li><strong>Smart Autocomplete:</strong> Instant address suggestions as users type</li>
            <li><strong>Global Coverage:</strong> Accurate addresses worldwide</li>
            <li><strong>Validation:</strong> Ensures users select valid, formatted addresses</li>
            <li><strong>Enhanced UX:</strong> Professional, fast address input experience</li>
        </ul>
        
        <h4>💰 Pricing (2025):</h4>
        <ul style="line-height: 1.6;">
            <li><strong>Autocomplete:</strong> $2.83 per 1,000 requests (after free tier)</li>
            <li><strong>Place Details:</strong> $17 per 1,000 requests</li>
            <li><strong>Monthly Credit:</strong> $200 free usage credit every month</li>
            <li><strong>Perfect for Most Sites:</strong> Free tier covers typical website needs</li>
        </ul>
        
        <h4>🔧 Setup Requirements:</h4>
        <ul style="line-height: 1.6;">
            <li><strong>Google Account:</strong> Free Google account required</li>
            <li><strong>Billing Account:</strong> Credit card required (charged only after free limits)</li>
            <li><strong>API Restrictions:</strong> Secure your key with domain restrictions</li>
            <li><strong>Places API Enabled:</strong> Must enable Places API in your project</li>
        </ul>
        
        <h4>🔒 Security Best Practices:</h4>
        <ul style="line-height: 1.6;">
            <li><strong>Environment Variable (Recommended):</strong> Add <code>define('AGENTICPRESS_GOOGLE_API_KEY', 'your-key-here');</code> to your <code>wp-config.php</code> file</li>
            <li><strong>Domain Restrictions:</strong> Limit API key to your website domains only</li>
            <li><strong>Monitor Usage:</strong> Check Google Cloud Console regularly</li>
            <li><strong>Set Usage Limits:</strong> Configure daily quotas to prevent overuse</li>
        </ul>
        
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 4px;">
            <strong>⚠️ Important:</strong> Enable <strong>billing</strong> in Google Cloud Console for the API to work properly (you won't be charged until you exceed free limits).
        </div>
        
        <div style="background: #dff0d8; border: 1px solid #d6e9c6; padding: 10px; margin: 10px 0; border-radius: 4px;">
            <strong>✅ Ready to Go?</strong> Your Google Places API key will enable fast, accurate address autocomplete for your users!
        </div>
    </div>
    <?php
}

function agenticpress_hv_ai_section_html() {
    ?>
    <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #0073aa; margin: 10px 0;">
        <h3 style="margin-top: 0;">🚀 Enable AI-Powered Property Summaries</h3>
        <p>Add intelligent property analysis to your home value estimates using Google's Gemini AI.</p>
        
        <h4>📋 How to Get Your FREE Gemini API Key:</h4>
        <ol style="line-height: 1.6;">
            <li><strong>Visit Google AI Studio:</strong> <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">https://aistudio.google.com/app/apikey</a></li>
            <li><strong>Sign in</strong> with your Google account (create one if needed)</li>
            <li><strong>Accept Terms:</strong> Review and accept Google's AI Terms of Service</li>
            <li><strong>Create API Key:</strong> Click "Create API key" button</li>
            <li><strong>Copy Your Key:</strong> Copy the generated API key (starts with "AIza...")</li>
            <li><strong>Paste Below:</strong> Enter your API key in the field below</li>
        </ol>
        
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 4px;">
            <strong>💡 Pro Tip:</strong> The Gemini API is <strong>FREE</strong> with generous usage limits - perfect for most websites!
        </div>
        
        <h4>🔒 Security Best Practices:</h4>
        <ul style="line-height: 1.6;">
            <li><strong>Environment Variable (Recommended):</strong> Add <code>define('AGENTICPRESS_GEMINI_API_KEY', 'your-key-here');</code> to your <code>wp-config.php</code> file</li>
            <li><strong>Never commit API keys</strong> to version control (Git, etc.)</li>
            <li><strong>Treat your API key like a password</strong> - keep it confidential</li>
        </ul>
        
        <h4>✨ What You'll Get:</h4>
        <ul style="line-height: 1.6;">
            <li>Intelligent property summaries with market insights</li>
            <li>Neighborhood analysis and trends</li>
            <li>Investment potential assessments</li>
            <li>Enhanced user engagement with AI-powered content</li>
        </ul>
        
        <div style="background: #dff0d8; border: 1px solid #d6e9c6; padding: 10px; margin: 10px 0; border-radius: 4px;">
            <strong>✅ Ready to Get Started?</strong> Once you enter your API key below, AI summaries will automatically appear with every property lookup!
        </div>
    </div>
    <?php
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

/**
 * PERMANENT DATA STRUCTURE REFERENCE
 * ==================================
 * This function serves as the canonical reference for all data fields available 
 * from the ATTOM API response. This data structure maps directly to the database
 * schema in the agenticpress_properties table.
 * 
 * DATABASE MAPPING:
 * - Each key corresponds to a column in wp_agenticpress_properties table
 * - Values are extracted from ATTOM API JSON response using agenticpress_hv_get_value_from_json()
 * - All fields are sanitized before database insertion
 * 
 * ATTOM API SOURCE PATHS:
 * - full_address: property.address.oneLine
 * - street: property.address.line1  
 * - city: property.address.locality
 * - state: property.address.countrySubd
 * - zip: property.address.postal1
 * - latitude: property.location.latitude
 * - longitude: property.location.longitude
 * - property_type: property.summary.proptype
 * - year_built: property.summary.yearbuilt
 * - lot_size_acres: property.lot.lotsize1
 * - building_size_sqft: property.building.size.livingsize
 * - bedrooms: property.building.rooms.beds
 * - bathrooms: property.building.rooms.bathstotal
 * - avm_value: property.avm.amount.value
 * - avm_confidence_score: property.avm.amount.scr
 * - avm_value_high: property.avm.amount.high
 * - avm_value_low: property.avm.amount.low
 * - last_sale_date: property.sale.saleTransDate
 * - last_sale_price: property.sale.amount.saleamt
 * - assessed_total_value: property.assessment.assessed.assdttlvalue
 * - market_total_value: property.assessment.market.mktttlvalue
 * - owner_name: property.owner.owner1.fullname
 * - attomId: property.identifier.attomId
 * 
 * Get all available data placeholders for AI instructions
 * This serves as the permanent reference for available data fields from ATTOM API
 */
function agenticpress_hv_get_ai_placeholders() {
    return [
        'Property Address & Location' => [
            'full_address' => 'Complete property address',
            'street' => 'Street address (line 1)',
            'city' => 'City name',
            'state' => 'State abbreviation',
            'zip' => 'ZIP/postal code',
            'latitude' => 'Property latitude coordinates',
            'longitude' => 'Property longitude coordinates'
        ],
        'Property Details' => [
            'property_type' => 'Type of property (Single Family, Condo, etc.)',
            'year_built' => 'Year the property was built',
            'lot_size_acres' => 'Lot size in acres',
            'building_size_sqft' => 'Living area square footage',
            'bedrooms' => 'Number of bedrooms',
            'bathrooms' => 'Number of bathrooms (total)'
        ],
        'Valuation & AVM Data' => [
            'avm_value' => 'Current estimated market value',
            'avm_confidence_score' => 'Confidence score for the estimate (0-100)',
            'avm_value_high' => 'High end of value range',
            'avm_value_low' => 'Low end of value range'
        ],
        'Sales & Transaction History' => [
            'last_sale_date' => 'Date of last sale/transfer',
            'last_sale_price' => 'Last sale price amount'
        ],
        'Tax Assessment Data' => [
            'assessed_total_value' => 'Total assessed value for taxes',
            'market_total_value' => 'Market total value from assessor'
        ],
        'Ownership Information' => [
            'owner_name' => 'Current property owner name'
        ],
        'System Data' => [
            'attomId' => 'ATTOM Data unique property identifier',
            'lookup_time' => 'Timestamp when data was retrieved'
        ]
    ];
}

function agenticpress_hv_ai_instructions_field_html() {
    $default_instructions = "As a real estate analyst, provide a concise, two-paragraph summary of the property's value, speaking directly to the homeowner.

Your property at {{full_address}}, a {{property_type}} with {{bedrooms}} bedrooms and {{bathrooms}} bathrooms, has seen significant appreciation since its last sale at {{last_sale_price}}. Today's automated valuation model (AVM) estimates its current value at **{{avm_value}}**, with a high confidence score of {{avm_confidence_score}}. This suggests a substantial increase in your home equity and highlights the strong opportunity in the current market.

This automated estimate provides a powerful snapshot, but it doesn't account for your home's unique features or recent upgrades. To determine your property's true market value and unlock its full financial potential, a detailed Comparative Market Analysis (CMA) is essential. Contact us to schedule a complimentary, no-obligation CMA and make an informed decision about your investment.";
    $instructions = get_option('agenticpress_hv_ai_instructions', $default_instructions);
    ?>
    <textarea name="agenticpress_hv_ai_instructions" rows="15" class="large-text"><?php echo esc_textarea($instructions); ?></textarea>
    
    <div style="margin-top: 15px;">
        <p><strong>Available Data Placeholders:</strong></p>
        <p class="description">Click any placeholder below to insert it into your instructions. Use double curly braces: <code>{{placeholder_name}}</code></p>
        
        <div id="agenticpress-placeholder-selector" style="border: 1px solid #ddd; padding: 15px; background: #fafafa; max-height: 300px; overflow-y: auto;">
            <?php
            $placeholders = agenticpress_hv_get_ai_placeholders();
            foreach ($placeholders as $category => $fields) {
                echo '<h4 style="margin: 10px 0 5px 0; color: #2271b1;">' . esc_html($category) . '</h4>';
                echo '<div style="margin-left: 15px;">';
                foreach ($fields as $field => $description) {
                    echo '<span class="placeholder-item" data-placeholder="' . esc_attr($field) . '" 
                          style="display: inline-block; background: #fff; border: 1px solid #ccc; padding: 3px 8px; 
                          margin: 2px; cursor: pointer; border-radius: 3px; font-size: 12px;"
                          title="' . esc_attr($description) . '">
                          {{' . esc_html($field) . '}}
                          </span>';
                }
                echo '</div>';
            }
            ?>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('.placeholder-item').on('click', function() {
            var placeholder = '{{' + $(this).data('placeholder') + '}}';
            var textarea = $('textarea[name="agenticpress_hv_ai_instructions"]');
            var cursorPos = textarea.prop('selectionStart');
            var textBefore = textarea.val().substring(0, cursorPos);
            var textAfter = textarea.val().substring(cursorPos);
            textarea.val(textBefore + placeholder + textAfter);
            textarea.focus();
            textarea.prop('selectionStart', cursorPos + placeholder.length);
            textarea.prop('selectionEnd', cursorPos + placeholder.length);
            
            // Visual feedback
            $(this).css('background', '#e7f3ff').delay(200).queue(function() {
                $(this).css('background', '#fff').dequeue();
            });
        });
        
        // Add hover effects
        $('.placeholder-item').hover(
            function() { $(this).css('background', '#f0f8ff'); },
            function() { $(this).css('background', '#fff'); }
        );
    });
    </script>
    
    <p class="description" style="margin-top: 10px;">
        <strong>Tips:</strong>
        • Use placeholders to personalize AI responses with actual property data<br>
        • Combine multiple placeholders for rich, detailed summaries<br>
        • Example: "This {{bedrooms}}-bedroom, {{bathrooms}}-bathroom property built in {{year_built}} is valued at {{avm_value}}."
    </p>
    <?php
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
                    let shortcode = '[ai_home_value_estimator';

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

                $('#sc-button-text, #sc-input-width, #sc-button-position, #sc-gap, #sc-button-color, #sc-button-text-color').on('input change', generateShortcode);

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



function agenticpress_hv_lookups_page_html() {
    if (!current_user_can('read')) return;
    require_once plugin_dir_path(__FILE__) . 'class-ai-home-value-lookups-list-table.php';
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
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'ai_home_value_estimator')) {

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

        // Load reCAPTCHA if enabled
        $captcha_enabled = get_option('agenticpress_hv_enable_captcha', false);
        $recaptcha_site_key = get_option('agenticpress_hv_recaptcha_site_key');
        if ($captcha_enabled && !empty($recaptcha_site_key)) {
            wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr($recaptcha_site_key), [], null, true);
            wp_localize_script('agenticpress-hv-js', 'agenticpress_hv_recaptcha', [
                'site_key' => $recaptcha_site_key,
                'enabled' => true
            ]);
        } else {
            wp_localize_script('agenticpress-hv-js', 'agenticpress_hv_recaptcha', ['enabled' => false]);
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
add_shortcode('ai_home_value_estimator', 'agenticpress_hv_render_form');
function agenticpress_hv_render_form($atts) {
    $a = shortcode_atts([
        'button_text' => 'Get Home Value',
        'input_width' => '100%',
        'button_class' => '',
        'button_style' => '',
        'button_position' => 'below-left',
        'gap' => '10',
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
    
    // Handle background color options
    if ($bg_color_option === 'black') {
        $background_color = '#000000';
    } else if ($bg_color_option === 'white') {
        $background_color = '#ffffff';
    } else {
        // Default to white if not specified or invalid
        $background_color = '#ffffff';
    }

    // Get text color setting, with explicit shortcode parameter taking priority
    if (!empty($a['input_text_color'])) {
        $text_color = esc_attr($a['input_text_color']);
    } else {
        // Default text color based on background
        $text_color = ($bg_color_option === 'black') ? '#ffffff' : '#000000';
    }

    $cma_form_id = get_option('agenticpress_hv_gf_cma_form');
    ob_start();
    ?>
    <style>
        #agenticpress-hv-container gmp-place-autocomplete {
            /* This is the best practice for styling the component's internals */
            --gmp-mat-color-on-surface: <?php echo $text_color; ?> !important;
            --gmp-mat-color-surface: <?php echo $background_color; ?> !important;
            --gmp-mat-color-primary: <?php echo $text_color; ?> !important;
            --gmp-mat-color-outline: #767676 !important;
            --gmp-mat-color-on-surface-variant: <?php echo $text_color; ?> !important;
            --gmp-mat-color-outline-variant: #767676 !important;
            --mdc-theme-primary: <?php echo $text_color; ?> !important;
            --mdc-theme-surface: <?php echo $background_color; ?> !important;
            --mdc-theme-on-surface: <?php echo $text_color; ?> !important;
            --mdc-filled-text-field-container-color: <?php echo $background_color; ?> !important;
            --mdc-filled-text-field-label-text-color: <?php echo $text_color; ?> !important;
            --mdc-filled-text-field-input-text-color: <?php echo $text_color; ?> !important;
            color-scheme: none !important;
            width: 100%;
        }

        /* Multiple ways to target the input for maximum compatibility */
        #agenticpress-hv-container gmp-place-autocomplete::part(input),
        #agenticpress-hv-container gmp-place-autocomplete input,
        #agenticpress-hv-container gmp-place-autocomplete [role="combobox"],
        #agenticpress-hv-container gmp-place-autocomplete .mat-mdc-input-element,
        #agenticpress-hv-container gmp-place-autocomplete .mdc-text-field__input,
        #agenticpress-hv-container gmp-place-autocomplete .mat-mdc-form-field-input-control input,
        #agenticpress-hv-container gmp-place-autocomplete .mdc-filled-text-field input,
        #agenticpress-hv-container gmp-place-autocomplete .mat-input-element {
            color: <?php echo $text_color; ?> !important;
            background-color: <?php echo $background_color; ?> !important;
            border: 1px solid #767676 !important;
            padding: 8px !important;
            box-sizing: border-box !important;
            font-size: 16px !important;
            -webkit-text-fill-color: <?php echo $text_color; ?> !important;
            caret-color: <?php echo $text_color; ?> !important;
        }

        /* Force text color in all states */
        #agenticpress-hv-container gmp-place-autocomplete::part(input):focus,
        #agenticpress-hv-container gmp-place-autocomplete input:focus,
        #agenticpress-hv-container gmp-place-autocomplete [role="combobox"]:focus {
            color: <?php echo $text_color; ?> !important;
            background-color: <?php echo $background_color; ?> !important;
            outline: 2px solid #0073aa !important;
            outline-offset: -2px !important;
        }

        /* Target placeholder text */
        #agenticpress-hv-container gmp-place-autocomplete::part(input)::placeholder,
        #agenticpress-hv-container gmp-place-autocomplete input::placeholder {
            color: <?php echo $text_color === '#ffffff' ? 'rgba(255,255,255,0.7)' : 'rgba(0,0,0,0.5)'; ?> !important;
        }

        /* This targets the browser's aggressive autofill styles */
        #agenticpress-hv-container gmp-place-autocomplete::part(input):-webkit-autofill,
        #agenticpress-hv-container gmp-place-autocomplete input:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 100px <?php echo $background_color; ?> inset !important;
            -webkit-text-fill-color: <?php echo $text_color; ?> !important;
            -webkit-background-clip: text !important;
            transition: background-color 5000s ease-in-out 0s !important;
        }

        /* Additional Material Design overrides */
        #agenticpress-hv-container gmp-place-autocomplete .mat-mdc-form-field-infix,
        #agenticpress-hv-container gmp-place-autocomplete .mdc-text-field__input {
            color: <?php echo $text_color; ?> !important;
            background-color: <?php echo $background_color; ?> !important;
        }

        /* Force override any inline styles that Google might apply */
        #agenticpress-hv-container gmp-place-autocomplete *,
        #agenticpress-hv-container .agenticpress-custom-autocomplete * {
            color: <?php echo $text_color; ?> !important;
        }

        /* Ultra-specific targeting for the custom class */
        .agenticpress-custom-autocomplete,
        .agenticpress-custom-autocomplete input,
        .agenticpress-custom-autocomplete [role="combobox"],
        .agenticpress-custom-autocomplete .mdc-text-field__input,
        .agenticpress-custom-autocomplete .mat-mdc-input-element {
            color: <?php echo $text_color; ?> !important;
            background-color: <?php echo $background_color; ?> !important;
            -webkit-text-fill-color: <?php echo $text_color; ?> !important;
            caret-color: <?php echo $text_color; ?> !important;
        }

        /* Google Places API styling override */
        gmp-place-autocomplete {
            color-scheme: none !important;
        }

        .sidx-streamlined-toggle {
            color: #387a00 !important;
            border: 1px solid #387a00 !important;
        }

        /* Fix dropdown suggestions styling - target all possible containers */
        gmp-place-autocomplete [role="listbox"],
        gmp-place-autocomplete [role="option"],
        gmp-place-autocomplete [role="listbox"] *,
        gmp-place-autocomplete [role="option"] *,
        #agenticpress-hv-container gmp-place-autocomplete [role="listbox"],
        #agenticpress-hv-container gmp-place-autocomplete [role="option"],
        #agenticpress-hv-container gmp-place-autocomplete [role="listbox"] *,
        #agenticpress-hv-container gmp-place-autocomplete [role="option"] *,
        .gm-style .gm-style-iw,
        .gm-style .gm-style-iw *,
        [data-testid*="place"] *,
        [class*="place-autocomplete"] *,
        [class*="dropdown"] [role="option"],
        [class*="suggestion"] *,
        .dropdown[part="prediction-list"],
        .dropdown[part="prediction-list"] *,
        ul[role="listbox"],
        ul[role="listbox"] *,
        li[part="prediction-item"],
        li[part="prediction-item"] *,
        .place-autocomplete-element-row,
        .place-autocomplete-element-row *,
        div[class*="place-autocomplete-element"],
        span[class*="place-autocomplete-element"] {
            color: #333333 !important;
            background-color: #ffffff !important;
            opacity: 1 !important;
            z-index: 9999 !important;
        }

        /* Dropdown container styling */
        #agenticpress-hv-container gmp-place-autocomplete [role="listbox"],
        .dropdown[part="prediction-list"],
        ul[role="listbox"],
        gmp-place-autocomplete::part(listbox),
        gmp-place-autocomplete::part(option),
        gmp-place-autocomplete::shadow > div,
        gmp-place-autocomplete::shadow ul {
            background: #ffffff !important;
            background-color: #ffffff !important;
            border: 1px solid #ccc !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
            opacity: 1 !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            z-index: 999999 !important;
            position: relative !important;
        }

        /* Individual dropdown option styling */
        #agenticpress-hv-container gmp-place-autocomplete [role="option"],
        li[part="prediction-item"],
        .place-autocomplete-element-row,
        gmp-place-autocomplete::part(option-text),
        gmp-place-autocomplete::shadow li {
            background: #ffffff !important;
            background-color: #ffffff !important;
            color: #333333 !important;
            padding: 8px 12px !important;
            opacity: 1 !important;
            border-bottom: 1px solid #f0f0f0 !important;
            opacity: 1 !important;
        }

        /* Hover state for dropdown options */
        #agenticpress-hv-container gmp-place-autocomplete [role="option"]:hover,
        li[part="prediction-item"]:hover,
        .place-autocomplete-element-row:hover {
            background-color: #f5f5f5 !important;
            color: #333333 !important;
            opacity: 1 !important;
        }

        /* Global override for Google Places dropdowns anywhere on the page */
        body [role="listbox"],
        body [role="option"],
        body [role="listbox"] *,
        body [role="option"] * {
            color: #333333 !important;
            background-color: #ffffff !important;
        }

        /* Target Google's common CSS classes for dropdowns */
        body .pac-container,
        body .pac-container *,
        body .pac-item,
        body .pac-item * {
            color: #333333 !important;
            background-color: #ffffff !important;
        }

        /* Nuclear approach - force any element that might be a dropdown */
        [data-value],
        [data-value] *,
        [class*="suggestion"],
        [class*="suggestion"] *,
        [class*="autocomplete"],
        [class*="autocomplete"] *,
        [class*="places"],
        [class*="places"] *,
        div[style*="position: absolute"]:not(#agenticpress-hv-container *),
        div[style*="position: fixed"]:not(#agenticpress-hv-container *) {
            color: #333333 !important;
            background-color: #ffffff !important;
        }

        /* Target any divs that appear after our autocomplete that might be dropdowns */
        #agenticpress-hv-container ~ div,
        #agenticpress-hv-container ~ div * {
            color: #333333 !important;
            background-color: #ffffff !important;
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

        /* Prevent jumping when Gravity Forms loads/submits */
        #agenticpress-hv-container {
            scroll-behavior: smooth;
            position: relative;
        }
        
        /* Ensure smooth transitions for form state changes */
        #agenticpress-combined-result-container {
            transition: all 0.3s ease-in-out;
        }
        
        #agenticpress-cma-form-wrapper {
            transition: all 0.3s ease-in-out;
        }
        
        /* Prevent page jumping on Gravity Forms submission */
        .gform_wrapper {
            scroll-margin-top: 20px;
        }
        
        /* Ensure containers maintain their position */
        #agenticpress-hv-container .gform_confirmation_wrapper {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }

        /* Fix for Google Places dropdown transparency and z-index */
        gmp-place-autocomplete {
            color-scheme: none !important;
        }

        .sidx-streamlined-toggle {
            color: #387a00 !important;
            border: 1px solid #387a00 !important;
        }

        /* Ensure dropdown appears above all other content */
        gmp-place-autocomplete [role="listbox"],
        .dropdown[part="prediction-list"],
        ul[role="listbox"],
        body .pac-container {
            z-index: 99999 !important;
            position: relative !important;
            background: white !important;
            opacity: 1 !important;
        }
    </style>
    <div id="agenticpress-hv-container">
        <div id="agenticpress-hv-form-wrapper">
             <form id="agenticpress-hv-form" <?php echo $form_style; ?> novalidate>
                <!-- Honeypot field for bot detection -->
                <input type="text" name="website" id="agenticpress-website" style="position: absolute; left: -9999px; opacity: 0; pointer-events: none;" tabindex="-1" autocomplete="off">
                <!-- Timestamp for timing verification -->
                <input type="hidden" name="form_timestamp" id="agenticpress-form-timestamp" value="<?php echo time(); ?>">
                <div class="ap-input-container" <?php echo $input_container_style; ?>>
                    <gmp-place-autocomplete 
                        country="us" 
                        placeholder="type address here"
                        class="agenticpress-custom-autocomplete"
                        style="--gmp-mat-color-on-surface: <?php echo $text_color; ?>; --gmp-mat-color-surface: <?php echo $background_color; ?>; --gmp-mat-color-primary: <?php echo $text_color; ?>; --mdc-theme-primary: <?php echo $text_color; ?>; --mdc-theme-surface: <?php echo $background_color; ?>; --mdc-theme-on-surface: <?php echo $text_color; ?>;">
                    </gmp-place-autocomplete>
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

    // Rate limiting protection
    if (!agenticpress_hv_check_rate_limit()) {
        wp_send_json_error(['message' => 'Too many requests. Please wait before trying again.'], 429);
    }

    // Bot detection - honeypot and timing checks
    if (!agenticpress_hv_verify_human_request()) {
        wp_send_json_error(['message' => 'Automated requests are not allowed.'], 403);
    }

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



// -----------------------------------------------------------------------------
// Gravity Forms Integration Functions (defined globally but check for GFAPI)
// -----------------------------------------------------------------------------

function agenticpress_hv_gf_section_html() {
    if (!class_exists('GFAPI')) {
        echo '<p>Gravity Forms is not installed or activated.</p>';
        return;
    }
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
    if (!class_exists('GFAPI')) {
        echo '<p>Gravity Forms is not installed or activated.</p>';
        return;
    }
    
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

// Only add the Gravity Forms hook if Gravity Forms is active
if (class_exists('GFAPI')) {
    add_action('gform_after_submission', 'agenticpress_hv_capture_cma_submission', 10, 2);
}

// -----------------------------------------------------------------------------
// Security Admin Page Functions
// -----------------------------------------------------------------------------

/**
 * Security settings page HTML
 */
function agenticpress_hv_security_page_html() {
    if (!current_user_can('agenticpress_manage_settings')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    if (isset($_GET['settings-updated'])) {
        add_settings_error('agenticpress_hv_messages', 'agenticpress_hv_message', __('Security settings saved.'), 'updated');
    }
    
    settings_errors('agenticpress_hv_messages');
    ?>
    <div class="wrap agenticpress-security-page">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <div class="agenticpress-settings-container">
            <form action="options.php" method="post">
                <?php
                settings_fields('agenticpress_hv_security_settings');
                do_settings_sections('agenticpress_security');
                submit_button('Save Security Settings');
                ?>
            </form>
        </div>
    </div>
    <?php
}

/**
 * Security section description
 */
function agenticpress_hv_security_section_html() {
    echo '<p>Configure security measures to protect your forms from bots and abuse.</p>';
}

/**
 * Enable CAPTCHA field
 */
function agenticpress_hv_enable_captcha_field_html() {
    $enabled = get_option('agenticpress_hv_enable_captcha', false);
    echo '<input type="checkbox" name="agenticpress_hv_enable_captcha" value="1" ' . checked(1, $enabled, false) . '>';
    echo '<label for="agenticpress_hv_enable_captcha"> Enable Google reCAPTCHA v3 for bot protection</label>';
}

/**
 * reCAPTCHA Site Key field
 */
function agenticpress_hv_recaptcha_site_key_field_html() {
    $site_key = get_option('agenticpress_hv_recaptcha_site_key');
    echo '<input type="text" name="agenticpress_hv_recaptcha_site_key" value="' . esc_attr($site_key) . '" class="regular-text" placeholder="Your reCAPTCHA Site Key">';
    echo '<p class="description">Get your site key from <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA Console</a></p>';
}

/**
 * reCAPTCHA Secret Key field
 */
function agenticpress_hv_recaptcha_secret_key_field_html() {
    $secret_key = get_option('agenticpress_hv_recaptcha_secret_key');
    echo '<input type="password" name="agenticpress_hv_recaptcha_secret_key" value="' . esc_attr($secret_key) . '" class="regular-text" placeholder="Your reCAPTCHA Secret Key">';
    echo '<p class="description">Keep this key secure and never share it publicly</p>';
}

/**
 * CAPTCHA Threshold field
 */
function agenticpress_hv_captcha_threshold_field_html() {
    $threshold = get_option('agenticpress_hv_captcha_threshold', 0.5);
    echo '<input type="number" step="0.1" min="0" max="1" name="agenticpress_hv_captcha_threshold" value="' . esc_attr($threshold) . '" class="small-text">';
    echo '<p class="description">Score threshold (0.0-1.0). Lower scores indicate likely bot traffic. Recommended: 0.5</p>';
}

/**
 * Advanced Protection field
 */
function agenticpress_hv_enable_advanced_protection_field_html() {
    $enabled = get_option('agenticpress_hv_enable_advanced_protection', true);
    echo '<input type="checkbox" name="agenticpress_hv_enable_advanced_protection" value="1" ' . checked(1, $enabled, false) . '>';
    echo '<label for="agenticpress_hv_enable_advanced_protection"> Enable advanced bot detection (timing, fingerprinting, behavior analysis)</label>';
}

// -----------------------------------------------------------------------------
// Security & Rate Limiting Functions
// -----------------------------------------------------------------------------

/**
 * Enhanced rate limiting function with multiple tiers and progressive penalties
 */
function agenticpress_hv_check_rate_limit() {
    $client_ip = agenticpress_hv_get_client_ip();
    
    // Check if IP is temporarily blocked
    $block_key = 'agenticpress_blocked_' . md5($client_ip);
    if (get_transient($block_key)) {
        agenticpress_hv_log_security_event('blocked_ip_attempt', ['ip' => $client_ip]);
        return false;
    }
    
    // Multi-tier rate limiting
    $rate_limits = [
        'minute' => ['max' => 3, 'window' => MINUTE_IN_SECONDS],
        'hour' => ['max' => 10, 'window' => HOUR_IN_SECONDS],
        'day' => ['max' => 50, 'window' => DAY_IN_SECONDS]
    ];
    
    foreach ($rate_limits as $tier => $config) {
        $rate_limit_key = 'agenticpress_rate_limit_' . $tier . '_' . md5($client_ip);
        $request_count = get_transient($rate_limit_key);
        $max_requests = apply_filters("agenticpress_hv_rate_limit_max_{$tier}", $config['max']);
        
        if ($request_count === false) {
            set_transient($rate_limit_key, 1, $config['window']);
        } else {
            if ($request_count >= $max_requests) {
                // Progressive blocking: minute violation = 5 min block, hour = 30 min, day = 24 hours
                $block_duration = $tier === 'minute' ? 5 * MINUTE_IN_SECONDS : 
                                ($tier === 'hour' ? 30 * MINUTE_IN_SECONDS : DAY_IN_SECONDS);
                
                set_transient($block_key, true, $block_duration);
                agenticpress_hv_log_rate_limit_violation($client_ip, $request_count, $tier);
                return false;
            }
            set_transient($rate_limit_key, $request_count + 1, $config['window']);
        }
    }
    
    return true;
}

/**
 * Get the real client IP address (handles proxies and load balancers)
 */
function agenticpress_hv_get_client_ip() {
    $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]);
            
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Log rate limit violations for monitoring
 */
function agenticpress_hv_log_rate_limit_violation($ip, $request_count, $tier = 'general') {
    $log_entry = [
        'timestamp' => current_time('mysql'),
        'ip' => $ip,
        'request_count' => $request_count,
        'tier' => $tier,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'referer' => $_SERVER['HTTP_REFERER'] ?? 'Unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown'
    ];
    
    // Log to WordPress debug log if enabled
    if (WP_DEBUG_LOG) {
        error_log('AgenticPress Rate Limit Violation: ' . json_encode($log_entry));
    }
    
    // Store in database for admin review
    global $wpdb;
    $table_name = $wpdb->prefix . 'agenticpress_security_log';
    
    // Ensure security log table exists
    agenticpress_hv_create_security_log_table();
    
    $wpdb->insert($table_name, [
        'timestamp' => $log_entry['timestamp'],
        'event_type' => 'rate_limit_violation',
        'ip_address' => $log_entry['ip'],
        'request_count' => $request_count,
        'tier' => $tier,
        'user_agent' => $log_entry['user_agent'],
        'referer' => $log_entry['referer'],
        'request_method' => $log_entry['request_method']
    ]);
}

/**
 * Verify reCAPTCHA v3 response
 */
function agenticpress_hv_verify_recaptcha($captcha_response) {
    $secret_key = get_option('agenticpress_hv_recaptcha_secret_key');
    if (empty($secret_key) || empty($captcha_response)) {
        return false;
    }
    
    $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
        'body' => [
            'secret' => $secret_key,
            'response' => $captcha_response,
            'remoteip' => agenticpress_hv_get_client_ip()
        ]
    ]);
    
    if (is_wp_error($response)) {
        agenticpress_hv_log_security_event('recaptcha_error', ['error' => $response->get_error_message()]);
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (!$data['success']) {
        agenticpress_hv_log_security_event('recaptcha_failed', ['errors' => $data['error-codes'] ?? []]);
        return false;
    }
    
    $threshold = get_option('agenticpress_hv_captcha_threshold', 0.5);
    $score = $data['score'] ?? 0;
    
    if ($score < $threshold) {
        agenticpress_hv_log_security_event('recaptcha_low_score', ['score' => $score, 'threshold' => $threshold]);
        return false;
    }
    
    return true;
}

/**
 * Advanced request fingerprinting and bot detection
 */
function agenticpress_hv_generate_request_fingerprint() {
    $fingerprint_data = [
        'ip' => agenticpress_hv_get_client_ip(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
        'accept_encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
        'connection' => $_SERVER['HTTP_CONNECTION'] ?? '',
        'referer' => $_SERVER['HTTP_REFERER'] ?? '',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'headers' => array_filter([
            'x-forwarded-for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            'x-real-ip' => $_SERVER['HTTP_X_REAL_IP'] ?? null,
            'cf-connecting-ip' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null
        ])
    ];
    
    return hash('sha256', json_encode($fingerprint_data));
}

/**
 * Enhanced human verification with multiple security layers
 */
function agenticpress_hv_verify_human_request() {
    $advanced_protection = get_option('agenticpress_hv_enable_advanced_protection', true);
    
    // Layer 1: reCAPTCHA verification (if enabled)
    $captcha_enabled = get_option('agenticpress_hv_enable_captcha', false);
    if ($captcha_enabled) {
        $captcha_response = isset($_POST['g-recaptcha-response']) ? sanitize_text_field($_POST['g-recaptcha-response']) : '';
        if (!agenticpress_hv_verify_recaptcha($captcha_response)) {
            return false;
        }
    }
    
    // Layer 2: Honeypot field check - should be empty
    $honeypot = isset($_POST['website']) ? sanitize_text_field($_POST['website']) : '';
    if (!empty($honeypot)) {
        agenticpress_hv_log_security_event('honeypot_triggered', ['honeypot_value' => $honeypot]);
        return false;
    }
    
    // Layer 3: Timing analysis
    $form_timestamp = isset($_POST['form_timestamp']) ? intval($_POST['form_timestamp']) : 0;
    $current_time = time();
    $min_time_threshold = apply_filters('agenticpress_hv_min_form_time', 3);
    $max_time_threshold = apply_filters('agenticpress_hv_max_form_time', 3600);
    
    if ($form_timestamp === 0) {
        agenticpress_hv_log_security_event('missing_timestamp');
        return false;
    }
    
    $time_diff = $current_time - $form_timestamp;
    
    if ($time_diff < $min_time_threshold) {
        agenticpress_hv_log_security_event('form_submitted_too_quickly', ['time_diff' => $time_diff]);
        return false;
    }
    
    if ($time_diff > $max_time_threshold) {
        agenticpress_hv_log_security_event('form_submitted_too_late', ['time_diff' => $time_diff]);
        return false;
    }
    
    if ($advanced_protection) {
        // Layer 4: Enhanced user agent analysis
        if (!agenticpress_hv_validate_user_agent()) {
            return false;
        }
        
        // Layer 5: Request pattern analysis
        if (!agenticpress_hv_analyze_request_pattern()) {
            return false;
        }
        
        // Layer 6: Behavioral analysis
        if (!agenticpress_hv_analyze_user_behavior()) {
            return false;
        }
    }
    
    return true;
}

/**
 * Enhanced user agent validation
 */
function agenticpress_hv_validate_user_agent() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (empty($user_agent)) {
        agenticpress_hv_log_security_event('missing_user_agent');
        return false;
    }
    
    // Expanded bot pattern detection
    $bot_patterns = [
        'curl', 'wget', 'python', 'bot', 'spider', 'crawler', 'scraper',
        'postman', 'insomnia', 'automated', 'phantom', 'selenium', 'headless',
        'puppeteer', 'playwright', 'requests', 'urllib', 'httpie', 'apache-httpclient'
    ];
    
    foreach ($bot_patterns as $pattern) {
        if (stripos($user_agent, $pattern) !== false) {
            agenticpress_hv_log_security_event('bot_user_agent', ['user_agent' => $user_agent, 'pattern' => $pattern]);
            return false;
        }
    }
    
    // Check for suspicious user agent patterns
    if (strlen($user_agent) < 10 || strlen($user_agent) > 500) {
        agenticpress_hv_log_security_event('suspicious_user_agent_length', ['user_agent' => $user_agent, 'length' => strlen($user_agent)]);
        return false;
    }
    
    return true;
}

/**
 * Analyze request patterns for bot behavior
 */
function agenticpress_hv_analyze_request_pattern() {
    // Check for missing headers that legitimate browsers send
    $required_headers = ['HTTP_ACCEPT', 'HTTP_ACCEPT_LANGUAGE'];
    foreach ($required_headers as $header) {
        if (!isset($_SERVER[$header]) || empty($_SERVER[$header])) {
            agenticpress_hv_log_security_event('missing_browser_header', ['header' => $header]);
            return false;
        }
    }
    
    // Check for suspicious header combinations
    $accept_header = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (strpos($accept_header, 'text/html') === false && strpos($accept_header, '*/*') === false) {
        agenticpress_hv_log_security_event('suspicious_accept_header', ['accept' => $accept_header]);
        return false;
    }
    
    return true;
}

/**
 * Analyze user behavior patterns
 */
function agenticpress_hv_analyze_user_behavior() {
    $client_ip = agenticpress_hv_get_client_ip();
    $fingerprint = agenticpress_hv_generate_request_fingerprint();
    
    // Check for rapid repeated requests with same fingerprint
    $fingerprint_key = 'agenticpress_fingerprint_' . $fingerprint;
    $fingerprint_count = get_transient($fingerprint_key);
    
    if ($fingerprint_count === false) {
        set_transient($fingerprint_key, 1, 5 * MINUTE_IN_SECONDS);
    } else {
        if ($fingerprint_count >= 3) { // Max 3 requests per 5 minutes with same fingerprint
            agenticpress_hv_log_security_event('fingerprint_abuse', ['fingerprint' => $fingerprint, 'count' => $fingerprint_count]);
            return false;
        }
        set_transient($fingerprint_key, $fingerprint_count + 1, 5 * MINUTE_IN_SECONDS);
    }
    
    return true;
}

/**
 * Log security events for monitoring
 */
function agenticpress_hv_log_security_event($event_type, $additional_data = []) {
    $client_ip = agenticpress_hv_get_client_ip();
    
    $log_entry = array_merge([
        'timestamp' => current_time('mysql'),
        'event_type' => $event_type,
        'ip' => $client_ip,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ], $additional_data);
    
    // Log to WordPress debug log if enabled
    if (WP_DEBUG_LOG) {
        error_log('AgenticPress Security Event: ' . json_encode($log_entry));
    }
    
    // Store in database
    global $wpdb;
    $table_name = $wpdb->prefix . 'agenticpress_security_log';
    
    $wpdb->insert($table_name, [
        'timestamp' => $log_entry['timestamp'],
        'event_type' => $event_type,
        'ip_address' => $log_entry['ip'],
        'user_agent' => $log_entry['user_agent'],
        'request_count' => $additional_data['request_count'] ?? null
    ]);
}