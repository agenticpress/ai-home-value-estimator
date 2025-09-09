<?php
/**
 * Uninstall script for AI Home Value Estimator
 * 
 * This script runs when the plugin is deleted from the WordPress admin.
 * It removes all plugin data including database tables, options, transients, 
 * and user roles to ensure complete cleanup.
 * 
 * @package AI_Home_Value_Estimator
 * @version 1.0.0
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Security check - ensure this is being called properly
if (!current_user_can('delete_plugins')) {
    exit;
}

/**
 * Remove all plugin database tables
 */
function agenticpress_hv_remove_database_tables() {
    global $wpdb;
    
    // List of tables created by the plugin
    $tables_to_remove = [
        $wpdb->prefix . 'agenticpress_properties',
        $wpdb->prefix . 'agenticpress_security_log',
        $wpdb->prefix . 'agenticpress_lookups_old' // Backup table that might exist
    ];
    
    foreach ($tables_to_remove as $table_name) {
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }
}

/**
 * Remove all plugin options from wp_options table
 */
function agenticpress_hv_remove_plugin_options() {
    // API Keys and Settings
    $options_to_remove = [
        // API Keys
        'agenticpress_hv_api_key',
        'agenticpress_hv_google_api_key', 
        'agenticpress_hv_ai_api_key',
        
        // AVM Settings
        'agenticpress_hv_avm_limit',
        
        // AI Settings
        'agenticpress_hv_ai_mode',
        'agenticpress_hv_ai_limit', 
        'agenticpress_hv_ai_instructions',
        
        // Google Settings
        'agenticpress_hv_input_bg_color',
        
        // Security Settings
        'agenticpress_hv_recaptcha_site_key',
        'agenticpress_hv_recaptcha_secret_key',
        'agenticpress_hv_enable_captcha',
        'agenticpress_hv_captcha_threshold',
        'agenticpress_hv_enable_advanced_protection',
        
        // Gravity Forms Integration
        'agenticpress_hv_gf_cma_form',
        
        // Plugin Version and Meta
        'agenticpress_hv_plugin_version',
        'agenticpress_hv_db_version',
    ];
    
    foreach ($options_to_remove as $option) {
        delete_option($option);
    }
}

/**
 * Remove all plugin transients and cache
 */
function agenticpress_hv_remove_transients() {
    global $wpdb;
    
    // Remove transients with our prefix
    $transient_patterns = [
        'agenticpress_rate_limit_%',
        'agenticpress_blocked_%',
        'agenticpress_fingerprint_%',
    ];
    
    foreach ($transient_patterns as $pattern) {
        // Remove regular transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . $pattern,
                '_transient_timeout_' . $pattern
            )
        );
        
        // Remove site transients (for multisite)
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_site_transient_' . $pattern,
                '_site_transient_timeout_' . $pattern
            )
        );
    }
}

/**
 * Remove custom user roles and capabilities
 */
function agenticpress_hv_remove_user_roles_and_capabilities() {
    // Remove the custom super admin role
    remove_role('agenticpress_super_admin');
    
    // Remove custom capabilities from administrator role
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->remove_cap('agenticpress_manage_settings');
        $admin_role->remove_cap('agenticpress_access_api_test');
    }
    
    // Remove capabilities from any other roles that might have them
    $roles = wp_roles();
    $custom_capabilities = [
        'agenticpress_manage_settings'
    ];
    
    foreach ($roles->roles as $role_name => $role_info) {
        $role = get_role($role_name);
        if ($role) {
            foreach ($custom_capabilities as $cap) {
                if ($role->has_cap($cap)) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
}

/**
 * Remove scheduled events (cron jobs)
 */
function agenticpress_hv_remove_scheduled_events() {
    // Remove any scheduled cron events
    $scheduled_hooks = [
        'agenticpress_hv_cleanup_security_log',
        'agenticpress_hv_cleanup_old_transients',
        'agenticpress_hv_monthly_reset'
    ];
    
    foreach ($scheduled_hooks as $hook) {
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }
        // Clear all scheduled instances of this hook
        wp_clear_scheduled_hook($hook);
    }
}

/**
 * Clean up user meta data related to the plugin
 */
function agenticpress_hv_remove_user_meta() {
    global $wpdb;
    
    // Remove any user meta with our prefix
    $meta_patterns = [
        'agenticpress_hv_%',
        'agenticpress_%'
    ];
    
    foreach ($meta_patterns as $pattern) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
                $pattern
            )
        );
    }
}

/**
 * Remove plugin-specific uploads and cache files
 */
function agenticpress_hv_remove_upload_files() {
    $upload_dir = wp_upload_dir();
    $plugin_upload_path = $upload_dir['basedir'] . '/agenticpress-hv/';
    
    // Remove the plugin's upload directory if it exists
    if (is_dir($plugin_upload_path)) {
        agenticpress_hv_remove_directory($plugin_upload_path);
    }
}

/**
 * Helper function to recursively remove a directory
 */
function agenticpress_hv_remove_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        is_dir($path) ? agenticpress_hv_remove_directory($path) : unlink($path);
    }
    
    return rmdir($dir);
}

/**
 * Log the uninstallation for debugging purposes
 */
function agenticpress_hv_log_uninstall() {
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('AI Home Value Estimator: Plugin uninstalled and all data removed at ' . current_time('mysql'));
    }
}

/**
 * Main uninstall function
 * Executes all cleanup procedures
 */
function agenticpress_hv_uninstall() {
    // Log the start of uninstall process
    agenticpress_hv_log_uninstall();
    
    // Remove database tables
    agenticpress_hv_remove_database_tables();
    
    // Remove all plugin options
    agenticpress_hv_remove_plugin_options();
    
    // Remove transients and cache
    agenticpress_hv_remove_transients();
    
    // Remove custom user roles and capabilities  
    agenticpress_hv_remove_user_roles_and_capabilities();
    
    // Remove scheduled events
    agenticpress_hv_remove_scheduled_events();
    
    // Remove user meta data
    agenticpress_hv_remove_user_meta();
    
    // Remove upload files
    agenticpress_hv_remove_upload_files();
    
    // Clear any remaining WordPress caches
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Clear object cache if available
    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('agenticpress_hv');
    }
}

// Execute the uninstall process
agenticpress_hv_uninstall();

// Final log entry
if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
    error_log('AI Home Value Estimator: Uninstall process completed successfully');
}