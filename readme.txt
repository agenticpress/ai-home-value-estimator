
=== AgenticPress AI Home Values ===
Contributors: agenticpress
Tags: real estate, home values, property, attom, avm, ai
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.6.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Provides a home value form via shortcode that retrieves property valuations from the ATTOM API.

== Description ==

AgenticPress AI Home Values is a WordPress plugin that allows you to easily add a home value lookup form to any page, post, or widget on your website. The plugin integrates with the ATTOM API to provide accurate property valuations and details.

**Key Features:**

* Simple shortcode implementation: `[agenticpress_home_value_form]`
* Google Places API integration for address autocomplete
* Displays comprehensive property information including:
  * Estimated market value
  * Assessed value
  * Property details (bedrooms, bathrooms, year built)
  * Lot size and property type
* Customizable submit button text
* Responsive design with clean styling
* AJAX-powered for seamless user experience

**API Requirements:**

This plugin requires API keys from:
* ATTOM Data (for property valuations and details)
* Google Places API (for address autocomplete functionality)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/agenticpress-home-values` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to 'Home Values' in your WordPress admin menu.
4. Enter your ATTOM API key and Google Places API key in the settings.
5. Use the shortcode `[agenticpress_home_value_form]` on any page or post where you want the form to appear.

== Frequently Asked Questions ==

= How do I get an ATTOM API key? =

Visit the ATTOM Data website to sign up for an API key. You'll need this to access property valuation data.

= How do I get a Google Places API key? =

Go to the Google Cloud Console, enable the Places API, and generate an API key. This is required for the address autocomplete feature.

= Can I customize the button text? =

Yes! Use the shortcode with the button_text parameter: `[agenticpress_home_value_form button_text="See My Home Value"]`

= What property information is displayed? =

The plugin shows estimated value, assessed value, bedrooms, bathrooms, year built, lot size, and property type.

= Does this work with all addresses? =

The plugin works with addresses in the United States that are available in the ATTOM database.

== Shortcode Usage ==

**Basic Usage:**
`[agenticpress_home_value_form]`

**Custom Button Text:**
`[agenticpress_home_value_form button_text="Get My Property Value"]`

== Screenshots ==

1. The home value form with Google Places autocomplete
2. Property details display after successful lookup
3. Admin settings page for API configuration

== Changelog ==

= 1.6.2 =
* Current stable version
* Improved error handling and user feedback
* Enhanced property details display with grid layout
* Better address validation and autocomplete integration

== Upgrade Notice ==

= 1.6.2 =
This version includes improved error handling and enhanced property details display.

== Support ==

For support and documentation, please visit the AgenticPress website or contact our support team.

== Privacy ==

This plugin sends address information to third-party APIs (ATTOM Data and Google Places) to retrieve property information. Please ensure your privacy policy reflects this data sharing.
