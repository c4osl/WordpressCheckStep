# CheckStep Integration for BuddyBoss

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/checkstep-integration.svg)](https://wordpress.org/plugins/checkstep-integration/)
[![WordPress Plugin Rating](https://img.shields.io/wordpress/plugin/r/checkstep-integration.svg)](https://wordpress.org/plugins/checkstep-integration/)
[![WordPress Plugin Downloads](https://img.shields.io/wordpress/plugin/dt/checkstep-integration.svg)](https://wordpress.org/plugins/checkstep-integration/)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Integrate CheckStep's AI-powered content moderation with your BuddyBoss community platform.

## 🚀 Features

- **Automated Content Moderation**: AI-powered scanning of posts, comments, and profiles
- **Real-time Processing**: Instant feedback on potential policy violations
- **Complex Content Support**: Handle text, images, and videos
- **Custom Taxonomies**: Flexible content warning system
- **BuddyBoss Integration**: Seamless integration with existing moderation tools
- **Developer-friendly**: Extensive hooks and filters for customization

## 📋 Requirements

- WordPress 5.0+
- PHP 7.4+
- BuddyBoss Platform
- CheckStep API credentials

## Supported Content Types

The plugin supports moderation of the following content types:

### Activity Stream Posts
- Text content with rich media support
- Author information and roles
- Group context (if applicable)
- Parent post references for replies
- Attached media (images, videos, documents)

### Forum Posts
- Post content with formatting
- Thread and forum context
- Author details
- Media attachments
- Parent post references

### Group Discussions
- Discussion content
- Group context and metadata
- Author information
- Attached media support
- Content hierarchy

### User Blog Posts
- Post title and content
- Author information
- Content warning taxonomies
- Embedded media
- File attachments

### Standalone Media
1. **Images**
   - Image file with metadata
   - Alt text and captions
   - Author information
   - Parent content reference (if embedded)

2. **Videos**
   - Video file with metadata
   - Title and description
   - Author information
   - Parent content reference (if embedded)

Each content type is processed asynchronously and supports:
- Text analysis for policy violations
- Image/video scanning for inappropriate content
- Author context for moderation decisions
- Content warning tags
- Parent-child relationships

## 🔧 Installation

1. Download the latest release
2. Upload to your WordPress plugins directory
3. Activate the plugin
4. Configure your CheckStep API credentials
5. Customize your moderation settings

## 🛠️ Development Setup

### Local Environment

```bash
# Clone the repository
git clone https://github.com/yourusername/checkstep-integration.git

# Install dependencies
composer install

# Run tests
composer test
```

### Configuration

```php
// Define API credentials in wp-config.php
define('CHECKSTEP_API_KEY', 'your-api-key');
define('CHECKSTEP_WEBHOOK_SECRET', 'your-webhook-secret');
```

### Webhook Events

The plugin processes the following webhook events from CheckStep:

1. **Decision Taken**
   - **No Action**: Content meets community guidelines, no moderation needed
   - **Hide**: Content is hidden from public view using BuddyBoss moderation
   - **Upheld**: Appeal was refused, user is notified via BuddyBoss notifications
   - **Overturn**: Appeal was accepted, content is restored and user is notified
   - **Content Warning**: (Not Yet Implemented) Adds content warning tags
   - **Ban User**: (Not Yet Implemented) Restricts user access

2. **Incident Closed**
   - Manages user notifications
   - Updates appeal statuses
   - Provides closure information to users

Future Enhancement:
- **Content Analysed** (Not Yet Implemented)
  - Could enable early intervention
  - Potential for automated takedowns pending review
  - Risk score tracking and preemptive warnings


### Available Hooks

```php
// Hook into moderation decisions
add_action('checkstep_decision_handled', function($decision_data) {
    // Handle moderation decision
});

// Filter content before submission
add_filter('checkstep_before_submission', function($content) {
    // Modify content before sending to CheckStep
    return $content;
});
```

## 📚 Documentation

- [Plugin Documentation](https://docs.checkstep.com/wordpress)
- [API Reference](https://docs.checkstep.com/api)
- [Support Forum](https://wordpress.org/support/plugin/checkstep-integration/)

## 🧪 Testing

The plugin includes a comprehensive test suite:

```bash
# Run unit tests
composer test

# Run specific test suite
composer test -- --testsuite=moderation

# Generate coverage report
composer test -- --coverage-html=coverage
```

## 🤝 Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/improvement`)
3. Commit your changes (`git commit -am 'Add feature'`)
4. Push to the branch (`git push origin feature/improvement`)
5. Open a Pull Request

### Coding Standards

- Follow WordPress Coding Standards
- Include unit tests for new features
- Update documentation as needed
- Maintain backwards compatibility

## 📜 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 🔗 Links

- [CheckStep Website](https://checkstep.com)
- [WordPress Plugin Page](https://wordpress.org/plugins/checkstep-integration/)
- [BuddyBoss Platform](https://buddyboss.com)

## 🙋 Support

- Community Support: [WordPress.org Forum](https://wordpress.org/support/plugin/checkstep-integration/)
- Premium Support: [CheckStep Enterprise](https://checkstep.com/enterprise)

## 🗺️ Roadmap

Future features and improvements:

- Complex content type support (images, videos)
- Advanced moderation actions (user bans, appeals)
- Custom taxonomy integration (content warnings)
- Performance optimizations and caching