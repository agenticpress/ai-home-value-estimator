# AI Home Value Estimator

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/ai-home-value-estimator)](https://wordpress.org/plugins/ai-home-value-estimator/)
[![WordPress Plugin Downloads](https://img.shields.io/wordpress/plugin/dt/ai-home-value-estimator)](https://wordpress.org/plugins/ai-home-value-estimator/)
[![License](https://img.shields.io/badge/license-GPL%20v2%2B-blue.svg)](LICENSE)

A professional WordPress plugin that creates intelligent home value estimate forms with AI-powered property summaries. Perfect for real estate websites, mortgage brokers, and property investment sites.

## Features

### 🏠 Accurate Property Valuations
- **ATTOM Data Integration**: Access comprehensive property data including assessed values, market trends, and neighborhood statistics
- **Real-time Market Data**: Get current property valuations based on recent comparable sales
- **Historical Analysis**: Track property value changes over time

### 🤖 AI-Powered Property Insights
- **Gemini AI Integration**: Generate intelligent property summaries and market analysis
- **Automated Descriptions**: Create compelling property descriptions automatically
- **Market Trend Analysis**: AI-driven insights into local market conditions

### 📍 Smart Address Autocomplete
- **Google Places API**: Professional address autocomplete with validation
- **Geolocation Support**: Automatic address detection based on user location
- **Address Standardization**: Ensures consistent, properly formatted addresses

### 🔒 Enterprise Security
- **Rate Limiting**: Prevents API abuse with configurable request limits (10 requests/hour per IP)
- **Bot Protection**: Advanced bot detection including honeypot fields and timing analysis
- **User Agent Filtering**: Blocks known malicious bots and scrapers
- **Nonce Verification**: WordPress security tokens for all form submissions

### 📊 Lead Management
- **Gravity Forms Integration**: Seamless integration with WordPress's leading form plugin
- **Contact Database**: Automatically populate forms with property and contact data
- **Email Notifications**: Instant alerts for new property inquiries
- **Export Capabilities**: Download lead data in various formats

## Installation

### From WordPress Admin (Recommended)
1. Navigate to **Plugins > Add New** in your WordPress admin
2. Search for "AI Home Value Estimator"
3. Click **Install Now** and then **Activate**

### Manual Installation
1. Download the plugin zip file from [WordPress.org](https://wordpress.org/plugins/ai-home-value-estimator/)
2. Upload to `/wp-content/plugins/` directory
3. Activate through the **Plugins** menu in WordPress

### GitHub Installation
```bash
cd /wp-content/plugins/
git clone https://github.com/agenticpress/ai-home-value-estimator.git
```

## Quick Setup

### 1. API Configuration
After activation, navigate to **AI Home Values** in your WordPress admin and configure your API keys:

#### ATTOM Data API
1. Visit [ATTOM Data](https://api.developer.attomdata.com/)
2. Sign up for a free developer account
3. Copy your API key to the plugin settings

#### Google Places API
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing
3. Enable the Places API
4. Generate an API key and add it to plugin settings

#### Gemini AI API
1. Visit [Google AI Studio](https://aistudio.google.com/)
2. Sign in with your Google account
3. Generate an API key
4. Add the key to your plugin configuration

### 2. Add the Shortcode
Add the home value estimator to any page or post using:
```
[ai_home_value_estimator]
```

### 3. Customize Styling
The plugin includes default styles that work with most themes. For custom styling, target these CSS classes:
- `.agenticpress-form-container`
- `.agenticpress-address-input`
- `.agenticpress-submit-btn`
- `.agenticpress-results`

## Usage

### Basic Implementation
```php
// Shortcode in posts/pages
[ai_home_value_estimator]

// PHP template usage
<?php echo do_shortcode('[ai_home_value_estimator]'); ?>
```

### Advanced Customization
```php
// Custom form styling
[ai_home_value_estimator 
    background_color="white" 
    text_color="black" 
    button_color="#0073aa"
    gravity_form_id="1"
]
```

### Gravity Forms Integration
1. Create a new Gravity Form
2. Add fields for name, email, phone, and property address
3. Note the form ID
4. Configure the form ID in plugin settings or shortcode

## API Requirements

### ATTOM Data API
- **Free Tier**: 10,000 requests/month
- **Paid Plans**: Starting at $49/month for 50,000 requests
- **Features**: Property details, valuations, market trends

### Google Places API
- **Free Tier**: $200 monthly credit (≈28,500 requests)
- **Paid Rates**: $17 per 1,000 requests after free tier
- **Features**: Address autocomplete, geocoding, place details

### Gemini AI API
- **Free Tier**: 15 requests per minute, 1,500 per day
- **Paid Plans**: $0.000125 per 1K characters
- **Features**: Property analysis, market insights, automated descriptions

## Configuration Options

### Security Settings
```php
// Rate limiting (requests per hour per IP)
define('AGENTICPRESS_RATE_LIMIT', 10);

// Bot protection sensitivity (1-10, higher = more strict)
define('AGENTICPRESS_BOT_PROTECTION', 7);

// Minimum time between requests (seconds)
define('AGENTICPRESS_MIN_REQUEST_TIME', 5);
```

### Display Options
```php
// Default background color
define('AGENTICPRESS_DEFAULT_BG', '#ffffff');

// Default text color
define('AGENTICPRESS_DEFAULT_TEXT', '#333333');

// Enable/disable AI descriptions
define('AGENTICPRESS_AI_DESCRIPTIONS', true);
```

## Development & Testing

### Uninstall Testing
For developers, a test script is provided to validate the uninstall process:

```bash
# Run from plugin directory in development environment
php test-uninstall.php
```

This script will:
- Inventory all plugin data before uninstall
- Validate that uninstall.php contains all required cleanup functions
- Perform security checks on the uninstall process
- Provide a dry-run test without actually removing data

⚠️ **Only run this in development environments with `WP_DEBUG` enabled.**

## Troubleshooting

### Common Issues

#### "API Key Invalid" Error
- Verify all API keys are correctly entered
- Check API quotas haven't been exceeded
- Ensure APIs are enabled in respective consoles

#### Address Autocomplete Not Working
- Confirm Google Places API is enabled
- Check browser console for JavaScript errors
- Verify domain is authorized for the API key

#### Form Submissions Failing
- Check rate limiting isn't blocking legitimate users
- Verify Gravity Forms is installed and activated
- Ensure form ID is correctly configured

#### Styling Issues
- Check for theme conflicts by temporarily switching themes
- Clear any caching plugins
- Inspect CSS for conflicting styles

### Debug Mode
Enable WordPress debug mode for detailed error logging:
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

## Development

### Requirements
- PHP 7.4 or higher
- WordPress 5.0 or higher
- Gravity Forms plugin (recommended)

### Local Development Setup
```bash
# Clone repository
git clone https://github.com/agenticpress/ai-home-value-estimator.git

# Navigate to plugin directory
cd ai-home-value-estimator

# Install WordPress (if needed)
wp core download
wp config create --dbname=your_db --dbuser=your_user --dbpass=your_pass
wp core install --url=your-site.local --title="Test Site" --admin_user=admin --admin_password=password --admin_email=admin@test.com
```

### File Structure
```
ai-home-value-estimator/
├── README.md
├── LICENSE
├── ai-home-value-estimator.php        # Main plugin file
├── readme.txt                        # WordPress.org readme
├── class-ai-home-value-lookups-list-table.php # Admin list table
├── assets/
│   └── js/
│       ├── ap-admin-script.js        # Admin JavaScript
│       └── ap-form-handler.js        # Frontend form handling
└── index.php                         # Security index file
```

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

### Development Workflow
1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make your changes and add tests
4. Commit your changes: `git commit -m 'Add amazing feature'`
5. Push to the branch: `git push origin feature/amazing-feature`
6. Submit a pull request

### Reporting Issues
- Use the [GitHub Issues](https://github.com/agenticpress/ai-home-value-estimator/issues) page
- Include WordPress version, plugin version, and PHP version
- Provide detailed steps to reproduce the issue
- Include error messages and logs when possible

## Changelog

### Version 1.0.0
- Initial release
- ATTOM Data API integration
- Google Places autocomplete
- Gemini AI property analysis
- Gravity Forms integration
- Advanced security features
- WordPress.org submission ready

## Support

### Documentation
- [Plugin Documentation](https://agenticpress.ai/docs/ai-home-value-estimator/)
- [API Setup Guides](https://agenticpress.ai/docs/api-setup/)
- [Troubleshooting Guide](https://agenticpress.ai/docs/troubleshooting/)

### Community Support
- [WordPress.org Support Forum](https://wordpress.org/support/plugin/ai-home-value-estimator/)
- [GitHub Discussions](https://github.com/agenticpress/ai-home-value-estimator/discussions)

### Premium Support
For priority support and custom development:
- Email: support@agenticpress.ai
- Website: [AgenticPress.ai](https://agenticpress.ai/contact/)

## License

This plugin is licensed under the GPL v2 or later.

```
AI Home Value Estimator
Copyright (C) 2024 AgenticPress

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## Credits

- **Development**: [AgenticPress](https://agenticpress.ai/)
- **APIs**: ATTOM Data, Google Places, Gemini AI
- **Integration**: Gravity Forms
- **Testing**: WordPress community

---

**Made with ❤️ by [AgenticPress](https://agenticpress.ai/) - Empowering Real Estate with AI**