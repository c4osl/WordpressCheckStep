=== CheckStep Integration for BuddyBoss ===
Contributors: fanrefuge, checkstep
Tags: moderation, content-moderation, buddyboss, ai-moderation, trust-safety
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.17
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

= 1.0.17 =
* Updated: Forum hooks now use bbp_get_topic_content() and bbp_get_reply_content() per BuddyBoss docs
* Improved: bbp_new_topic and bbp_new_reply hooks now process content immediately (not just queue)
* Improved: bp_core_activated_user hook now processes user profiles immediately
* Added: Fallback to queue if immediate API call fails

= 1.0.16 =
* Fixed: API endpoint corrected from /content/submit to /content per CheckStep API v2 docs
* Fixed: api_call logs now properly recorded at info level (previously missed in logs viewer)
* Fixed: init and rendered logs changed from debug to info level for better visibility
* Improved: All initialization events now logged at info level for easier troubleshooting

= 1.0.15 =
* NEW: Log level filter setting - choose which log levels (Error, Warning, Info, Debug) to record
* Fixed: Database table creation - queue table now auto-creates if missing
* Added: Logging Settings section in admin settings page
* Improved: Tables are checked and created on admin_init if they don't exist

= 1.0.14 =
* Fixed: Fatal error "Class CheckStep_Admin_Tab not found" when BuddyBoss is active
* Admin tab class now properly loaded before instantiation in register_buddyboss_integration_tab()
* Added file existence check and error logging for missing admin tab file

= 1.0.13 =
* CRITICAL FIX: Core components (API, content_types) now initialized directly in constructor
* Fixed: "Cannot initialize ingestion - API or content_types not ready" warning
* Root cause: Plugin init runs on plugins_loaded, but was trying to add another plugins_loaded hook that had already fired
* Core components now available immediately for ingestion on 'init' hook
* API calls now execute directly when content is created (forum posts, activities, comments, etc.)

= 1.0.12 =
* CRITICAL FIX: Hook registration timing - ingestion now initialized on 'init' action (priority 20) instead of 'plugins_loaded'
* NEW: Added bbp_new_topic_post_extras and bbp_new_reply_post_extras hooks as recommended by BuddyBoss documentation
* NEW: Added bbp_edit_topic_post_extras and bbp_edit_reply_post_extras for edited forum content
* NEW: Immediate content processing - forum content sent to CheckStep API immediately instead of just queuing
* NEW: Scheduled queue processing via WP-Cron (runs every minute)
* Added: process_content_immediately() method for direct API submission
* Added: Custom cron schedule 'every_minute' for queue processing
* Fixed: BuddyBoss forum hooks now fire correctly (were previously registered too late)
* Improved: Split init_components into init_core_components and init_ingestion for proper timing
* Improved: API calls and responses now properly logged with CheckStep_Logger::api() and api_response()

= 1.0.11 =
* NEW: Logs viewer tab in plugin settings - view all hook triggers, API calls, and system events
* NEW: Database storage for logs with filtering by level, type, and search
* NEW: BuddyBoss Activity hooks - bp_activity_posted_update, bp_groups_posted_update, bp_activity_after_save
* NEW: Activity comment moderation via bp_activity_comment_posted hook
* NEW: User profile update hooks - xprofile_updated_profile, profile_update, user_register
* NEW: Private message moderation via messages_message_sent hook
* NEW: BuddyBoss media moderation via bp_media_add hook
* Added: Comprehensive hook logging to track all content creation events
* Added: API call and response logging for debugging
* Added: Log statistics showing hook activity and API usage
* Fixed: Missing hooks for new posts - added publish_post and transition_post_status hooks
* Improved: Duplicate queue prevention - items already pending won't be re-queued

= 1.0.10 =
* Enhanced: User Profile content type with comprehensive fields (display_name, email, role, profile_picture, bio, social links)
* Enhanced: Blog Post content type with publish_date, custom_taxonomies (content-warning), fragments for embedded media
* Enhanced: Forum Post content type with thread_id, timestamp, custom_taxonomies support
* Enhanced: Image content type with alt_text, caption, parent_content, and image metadata
* Enhanced: Video content type with title, parent_content, duration, and video metadata
* Improved: All content types now use consistent CheckStep API format (id, author, type, fields[], parent?, metadata?)
* Added: Media fragment extraction from blog post content (embedded images, YouTube, Vimeo videos)
* Added: BuddyBoss extended profile fields integration for user profiles

= 1.0.9 =
* Added: Forum topic/discussion moderation via bbp_new_topic hook
* Added: get_forum_topic() method for CheckStep API-formatted topic data
* Added: get_forum_reply() method for CheckStep API-formatted reply data
* Improved: Content formatted per CheckStep API spec (id, author, type, fields, parent, metadata)
* Added: Support for CheckStep complex types: 'thread' for topics, 'reply' for replies, 'channel' for forums
* Added: Media attachments (images, videos, documents) extraction from forum posts
* Added: Rich metadata: author info, platform, timestamps, forum/topic context

= 1.0.8 =
* Fixed: Test Connection now works with unsaved API keys
* Fixed: CheckStep_API constructor no longer requires saved API key
* Improved: Users can test connection before clicking "Save Changes"
* Enhanced: Better error handling for missing API key in API methods

= 1.0.7 =
* Fixed: Test Connection button now works correctly
* Added: AJAX handler for test_checkstep_connection action
* Added: test_connection method to CheckStep_API class
* Fixed: Resolved 404 error when clicking Test Connection button
* Improved: Connection test now validates API key with CheckStep API

= 1.0.6 =
* Fixed: Removed AJAX interception preventing settings form submission
* Fixed: Settings form now submits properly to options.php
* Fixed: Resolved 400 error from admin-ajax.php when saving settings
* Improved: Using standard WordPress Settings API form submission

= 1.0.5 =
* Fixed: Settings not saving when "Save Changes" button is clicked
* Fixed: Added proper sanitization callbacks to all settings fields
* Improved: Settings now save reliably with proper WordPress validation
* Enhanced: Appeal URL now uses esc_url_raw for proper URL sanitization

= 1.0.4 =
* Fixed: Fatal error when BuddyBoss Platform is not installed
* Fixed: BuddyBoss integration tab file now only loads when BuddyBoss is available
* Improved: Plugin works completely without BuddyBoss (admin pages accessible)

= 1.0.3 =
* Fixed: Admin menu items now appear correctly in WordPress admin
* Fixed: "Sorry, you are not allowed to access this page" error
* Fixed: Admin initialization timing to ensure menu hooks register properly
* Improved: Admin interface loads earlier in WordPress initialization sequence

= 1.0.2 =
* Added: Settings link on plugins page next to activate/deactivate
* Improved: Quick access to settings from Plugins → Installed Plugins page
* Enhanced: User experience for plugin configuration

= 1.0.1 =
* Fixed: Admin settings page now loads even when BuddyBoss is not installed
* Fixed: Settings → CheckStep menu now appears in WordPress admin
* Fixed: Tools → Moderation Queue menu now appears in WordPress admin
* Improved: Plugin shows warning instead of blocking when BuddyBoss is missing
* Changed: Admin interface is now available without BuddyBoss (content moderation still requires BuddyBoss)

= 1.0.0 =
* Initial release
* Basic content moderation features
* BuddyBoss integration
* Content warning system
* API integration
* Webhook support

== Upgrade Notice ==

= 1.0.9 =
New: Complete forum topic and reply moderation support. Content now properly formatted for CheckStep API.

= 1.0.8 =
Improved: Test Connection now works before saving settings. Better user experience.

= 1.0.7 =
Fixed: Test Connection button now functional. Adds API connection testing capability.

= 1.0.6 =
Critical fix: Resolves settings save failure and 400 error. Form now submits properly. Update immediately.

= 1.0.5 =
Critical fix: Settings now save properly when clicking "Save Changes" button. All users should update.

= 1.0.4 =
Critical fix: Resolves fatal error when BuddyBoss Platform is not installed. Plugin now works standalone.

= 1.0.3 =
Critical fix: Admin menu items now appear correctly. Resolves permission errors when accessing settings page.

= 1.0.2 =
Added convenient Settings link on the plugins page for quick access to configuration.

= 1.0.1 =
Settings page is now accessible even without BuddyBoss installed. Content moderation features still require BuddyBoss Platform.

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
