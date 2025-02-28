=== CheckStep Integration for BuddyBoss ===
Contributors: fanrefuge, checkstep
Tags: moderation, content-moderation, buddyboss, ai-moderation, trust-safety
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrate CheckStep's AI-powered content moderation with your BuddyBoss community for automated trust & safety management.

== Description ==

CheckStep Integration for BuddyBoss provides seamless content moderation for your community platform. By leveraging CheckStep's advanced AI moderation capabilities, you can automatically detect and manage inappropriate content, protect your community, and reduce moderation workload.

= Key Features =

* **Automated Content Scanning**: Automatically scan new posts, comments, and user profiles for policy violations
* **Real-time Moderation**: Get instant feedback on content that may violate your community guidelines
* **Custom Taxonomy Integration**: Utilize content warnings and custom taxonomies for granular content control
* **BuddyBoss Integration**: Seamlessly works with BuddyBoss's existing moderation system
* **Flexible Configuration**: Customize moderation settings to match your community's needs
* **Comprehensive Coverage**: Supports text, images, and videos across your platform

= Use Cases =

* Community forums requiring content moderation
* Membership sites with user-generated content
* Educational platforms needing safe content environments
* Social networks built on BuddyBoss
* Any WordPress site using BuddyBoss Platform

= Premium Support =

While this plugin is free and open source, premium support and trust & safety consulting services are available. [Contact us](https://checkstep.com/contact) for more information.

== Installation ==

1. Upload the `checkstep-integration` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > CheckStep Integration to configure your API credentials
4. Sign up for a CheckStep account at [checkstep.com](https://checkstep.com) if you haven't already
5. Enter your CheckStep API key and webhook secret in the plugin settings
6. Configure your moderation preferences and content warning taxonomies

== Frequently Asked Questions ==

= What is CheckStep? =

CheckStep is a leading content moderation platform that uses artificial intelligence to automatically detect and manage inappropriate content. It helps maintain safe online communities by identifying policy violations and toxic content.

= Do I need a CheckStep account? =

Yes, you need a CheckStep account to use this plugin. You can sign up at [checkstep.com](https://checkstep.com).

= What content types are supported? =

The plugin supports moderation of:
* Blog posts and comments
* Forum posts and replies
* User profiles
* Images and videos
* Custom post types

= How does the content warning system work? =

Content warnings are implemented using WordPress taxonomies. When CheckStep detects potentially sensitive content, it automatically applies appropriate warning tags that can be displayed to users before they view the content.

= Is this plugin GDPR compliant? =

Yes, the plugin is designed with privacy in mind and is GDPR compliant. It only processes the content necessary for moderation and respects user privacy settings.

== Screenshots ==

1. Plugin settings page
2. Moderation dashboard
3. Content warning configuration
4. Real-time moderation in action
5. BuddyBoss integration options

== Changelog ==

= 1.0.0 =
* Initial release
* Basic content moderation features
* BuddyBoss integration
* Content warning system
* API integration
* Webhook support

== Upgrade Notice ==

= 1.0.0 =
Initial release of the CheckStep Integration plugin for BuddyBoss.

== Additional Documentation ==

= Content Types =

The plugin supports various content types:

**User Profiles**
* Display name
* Profile content
* Social media links
* Custom profile fields

**Blog Posts**
* Post title
* Post content
* Comments
* Custom fields
* Embedded media

**Forum Posts**
* Thread content
* Replies
* Attachments
* Custom taxonomies

**Media**
* Images
* Videos
* Attachments
* Custom metadata

= API Integration =

The plugin uses two CheckStep APIs:

1. **Standard Integration API**
   * Asynchronous content scanning
   * Bulk content processing
   * Historical content analysis

2. **Headless Integration API**
   * Real-time moderation decisions
   * Community reports handling
   * Appeals management

= Moderation Actions =

Available moderation actions include:

* Content deletion
* Content hiding
* Warning application
* User suspension
* Custom actions via hooks

= Extending the Plugin =

Developers can extend the plugin using various filters and actions:

`checkstep_before_content_submission`
`checkstep_after_moderation_decision`
`checkstep_content_warning_applied`
`checkstep_user_notification_sent`

= Support =

For technical support, please visit our [support forum](https://wordpress.org/support/plugin/checkstep-integration/).

For premium support and consulting services, visit [checkstep.com/enterprise](https://checkstep.com/enterprise).
