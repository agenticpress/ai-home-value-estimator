# Contributing to AI Home Value Estimator

Thank you for your interest in contributing to the AI Home Value Estimator plugin! We welcome contributions from developers of all skill levels.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [How to Contribute](#how-to-contribute)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Pull Request Process](#pull-request-process)
- [Reporting Issues](#reporting-issues)
- [Feature Requests](#feature-requests)

## Code of Conduct

This project follows the [WordPress Community Code of Conduct](https://make.wordpress.org/handbook/community-code-of-conduct/). By participating, you are expected to uphold this code.

### Our Pledge

- **Be welcoming**: We welcome and support people of all backgrounds and identities
- **Be respectful**: Disagreement is no excuse for poor behavior or personal attacks
- **Be collaborative**: What we produce is a complex whole made of many parts
- **Be inquisitive**: Nobody knows everything! Asking questions early avoids many problems later

## Getting Started

### Prerequisites

- PHP 7.4 or higher
- WordPress 5.0 or higher
- Git installed on your local machine
- Text editor or IDE (VS Code, PhpStorm, etc.)
- Local WordPress development environment (Local by Flywheel, XAMPP, etc.)

### Fork and Clone

1. Fork the repository on GitHub
2. Clone your fork locally:
   ```bash
   git clone https://github.com/YOUR-USERNAME/ai-home-value-estimator.git
   cd ai-home-value-estimator
   ```

3. Add the upstream repository:
   ```bash
   git remote add upstream https://github.com/agenticpress/ai-home-value-estimator.git
   ```

## Development Setup

### 1. WordPress Environment

Set up a local WordPress development environment:

```bash
# Using WP-CLI (recommended)
wp core download
wp config create --dbname=ai_home_value_dev --dbuser=root --dbpass=password
wp core install --url=http://ai-home-value.local --title="Dev Site" --admin_user=admin --admin_password=password --admin_email=dev@example.com

# Symlink the plugin
ln -s /path/to/ai-home-value-estimator /path/to/wordpress/wp-content/plugins/
```

### 2. Install Dependencies

```bash
# If using Composer for dependencies
composer install

# If using npm for build tools
npm install
```

### 3. Plugin Activation

1. Navigate to your local WordPress admin
2. Go to **Plugins > Installed Plugins**
3. Activate the "AI Home Value Estimator" plugin

### 4. API Keys (Optional for Development)

For full functionality testing, obtain API keys:
- [ATTOM Data API](https://api.developer.attomdata.com/)
- [Google Places API](https://console.cloud.google.com/)
- [Gemini AI API](https://aistudio.google.com/)

## How to Contribute

### Types of Contributions

- **Bug fixes**: Help us squash bugs
- **Feature enhancements**: Improve existing functionality
- **New features**: Add new capabilities
- **Documentation**: Improve docs, code comments, examples
- **Testing**: Write or improve tests
- **Performance**: Optimize code for better performance
- **Accessibility**: Make the plugin more accessible
- **Security**: Identify and fix security issues

### Contribution Workflow

1. **Check existing issues**: Look for related issues or discussions
2. **Create an issue**: For bugs or feature requests (if none exists)
3. **Fork and branch**: Create a feature branch for your work
4. **Make changes**: Implement your contribution
5. **Test thoroughly**: Ensure everything works as expected
6. **Submit pull request**: Follow our PR guidelines

## Coding Standards

### WordPress Coding Standards

We follow the [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/):

```php
// Good
function agenticpress_get_property_data( $address ) {
    if ( empty( $address ) ) {
        return false;
    }
    
    $api_key = get_option( 'agenticpress_attom_api_key' );
    
    // Rest of function...
}

// Bad
function getPropertyData($address) {
    if(empty($address)) return false;
    $apiKey = get_option('agenticpress_attom_api_key');
    // Rest of function...
}
```

### JavaScript Standards

Follow [WordPress JavaScript Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/):

```javascript
// Good
jQuery( document ).ready( function( $ ) {
    $( '.agenticpress-form' ).on( 'submit', function( event ) {
        event.preventDefault();
        // Handle form submission
    });
});

// Bad
$(document).ready(function($) {
    $('.agenticpress-form').submit(function(e) {
        e.preventDefault();
        // Handle form submission
    });
});
```

### CSS Standards

Follow [WordPress CSS Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/):

```css
/* Good */
.agenticpress-form-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.agenticpress-form-container .form-field {
    margin-bottom: 1rem;
}

/* Bad */
.agenticpress-form-container{
    display:flex;
    flex-direction:column;
    gap:1rem;
}
.agenticpress-form-container .form-field{margin-bottom:1rem;}
```

### File Organization

```
ai-home-value-estimator/
├── ai-home-value-estimator.php           # Main plugin file
├── class-*.php                          # Class files
├── includes/                            # Core functionality
│   ├── class-admin.php
│   ├── class-frontend.php
│   └── class-api-handler.php
├── assets/
│   ├── js/
│   │   ├── admin.js
│   │   └── frontend.js
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   └── images/
├── templates/                           # Template files
├── languages/                           # Translation files
└── tests/                              # Test files
```

### Naming Conventions

- **Functions**: `agenticpress_function_name()`
- **Classes**: `Agenticpress_Class_Name`
- **Variables**: `$variable_name`
- **Constants**: `AGENTICPRESS_CONSTANT_NAME`
- **Hooks**: `agenticpress_hook_name`
- **CSS Classes**: `.agenticpress-class-name`
- **JavaScript**: `camelCase` for variables, `PascalCase` for constructors

## Testing

### Manual Testing

1. Test all form functionality
2. Verify API integrations work correctly
3. Check responsive design
4. Test with various WordPress themes
5. Verify accessibility compliance

### Automated Testing

```bash
# Run PHP unit tests (when available)
composer test

# Run JavaScript tests
npm test

# Check coding standards
composer run-script phpcs
```

### Testing Checklist

- [ ] Plugin activates without errors
- [ ] Forms submit successfully
- [ ] API calls return expected data
- [ ] Error handling works properly
- [ ] Security measures function correctly
- [ ] No JavaScript console errors
- [ ] Responsive on mobile devices
- [ ] Compatible with major themes
- [ ] Accessibility standards met

## Pull Request Process

### Before Submitting

1. **Sync with upstream**:
   ```bash
   git fetch upstream
   git checkout main
   git merge upstream/main
   ```

2. **Create feature branch**:
   ```bash
   git checkout -b feature/descriptive-name
   ```

3. **Make your changes** and commit them:
   ```bash
   git add .
   git commit -m "Add descriptive commit message"
   ```

4. **Push to your fork**:
   ```bash
   git push origin feature/descriptive-name
   ```

### Pull Request Guidelines

#### Title Format
- Use clear, descriptive titles
- Prefix with type: `Fix:`, `Add:`, `Update:`, `Remove:`
- Examples:
  - `Fix: Address autocomplete not working on mobile`
  - `Add: Support for custom CSS classes in shortcode`
  - `Update: Improve error handling for API failures`

#### Description Template
```markdown
## Description
Brief description of the changes.

## Type of Change
- [ ] Bug fix (non-breaking change that fixes an issue)
- [ ] New feature (non-breaking change that adds functionality)
- [ ] Breaking change (fix or feature that causes existing functionality to not work as expected)
- [ ] Documentation update

## Testing
- [ ] I have tested these changes locally
- [ ] I have added tests that prove my fix is effective or that my feature works
- [ ] All existing tests pass

## Checklist
- [ ] My code follows the WordPress coding standards
- [ ] I have commented my code, particularly in hard-to-understand areas
- [ ] I have made corresponding changes to the documentation
- [ ] My changes generate no new warnings
```

#### Review Process

1. **Automated checks**: All CI checks must pass
2. **Code review**: At least one maintainer review required
3. **Testing**: Changes tested in multiple environments
4. **Documentation**: Updated if necessary

## Reporting Issues

### Bug Reports

Use the [GitHub Issues](https://github.com/agenticpress/ai-home-value-estimator/issues) page with this template:

```markdown
**Describe the Bug**
A clear description of what the bug is.

**To Reproduce**
Steps to reproduce the behavior:
1. Go to '...'
2. Click on '...'
3. Scroll down to '...'
4. See error

**Expected Behavior**
A clear description of what you expected to happen.

**Screenshots**
If applicable, add screenshots to help explain your problem.

**Environment:**
- WordPress Version: [e.g. 6.4]
- Plugin Version: [e.g. 1.0.0]
- PHP Version: [e.g. 8.1]
- Browser: [e.g. Chrome, Safari]
- Theme: [e.g. Twenty Twenty-Four]

**Additional Context**
Add any other context about the problem here.
```

### Security Issues

**Do not open public issues for security vulnerabilities.**

Instead, email security issues to: security@agenticpress.ai

Include:
- Detailed description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

## Feature Requests

We welcome feature suggestions! Please:

1. Check existing feature requests first
2. Use the feature request template
3. Provide detailed use cases
4. Consider implementation complexity
5. Be open to discussion and alternatives

### Feature Request Template

```markdown
**Is your feature request related to a problem?**
A clear description of what the problem is.

**Describe the solution you'd like**
A clear description of what you want to happen.

**Describe alternatives you've considered**
Alternative solutions or features you've considered.

**Use Cases**
Specific scenarios where this feature would be useful.

**Additional Context**
Add any other context, screenshots, or examples about the feature request.
```

## Recognition

Contributors will be recognized in:
- Plugin credits
- CONTRIBUTORS.md file
- Release notes
- WordPress.org plugin page (when applicable)

## Questions?

- **General Questions**: [GitHub Discussions](https://github.com/agenticpress/ai-home-value-estimator/discussions)
- **Development Help**: [WordPress Slack](https://chat.wordpress.org/) #plugindev channel
- **Direct Contact**: contribute@agenticpress.ai

## Resources

### WordPress Development
- [WordPress Developer Handbook](https://developer.wordpress.org/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)

### Git and GitHub
- [Git Documentation](https://git-scm.com/doc)
- [GitHub Flow](https://guides.github.com/introduction/flow/)
- [Writing Good Commit Messages](https://chris.beams.io/posts/git-commit/)

Thank you for contributing to AI Home Value Estimator! 🏠✨