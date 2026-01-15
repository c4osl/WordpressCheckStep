# CheckStep Integration Plugin

WordPress plugin for integrating CheckStep content moderation with BuddyBoss Platform.

## Current Version: 1.0.17

### Version History

**v1.0.17** (Current) - 2025-12-01
- Updated: Forum hooks now use bbp_get_topic_content() and bbp_get_reply_content() per BuddyBoss docs
- Improved: bbp_new_topic and bbp_new_reply hooks now process content immediately (not just queue)
- Improved: bp_core_activated_user hook now processes user profiles immediately
- Added: Fallback to queue if immediate API call fails

**v1.0.16** - 2025-12-01
- Fixed: API endpoint corrected from /content/submit to /content per CheckStep API v2 docs
- Fixed: api_call logs now properly recorded at info level (previously missed in logs viewer)
- Fixed: init and rendered logs changed from debug to info level for better visibility
- Improved: All initialization events now logged at info level for easier troubleshooting

**v1.0.15** - 2025-11-30
- NEW: Log level filter setting - choose which log levels (Error, Warning, Info, Debug) to record
- Fixed: Database table creation - queue table now auto-creates if missing
- Added: Logging Settings section in admin settings page
- Improved: Tables are checked and created on admin_init if they don't exist

**v1.0.14** - 2025-11-30
- Fixed: Fatal error "Class CheckStep_Admin_Tab not found" when BuddyBoss is active
- Admin tab class now properly loaded before instantiation in register_buddyboss_integration_tab()
- Added file existence check and error logging for missing admin tab file

**v1.0.13** - 2025-11-30
- CRITICAL FIX: Core components (API, content_types) now initialized directly in constructor
- Fixed: "Cannot initialize ingestion - API or content_types not ready" warning
- Root cause: Plugin init runs on plugins_loaded, but was trying to add another plugins_loaded hook that had already fired
- Core components now available immediately for ingestion on 'init' hook
- API calls now execute directly when content is created (forum posts, activities, comments, etc.)

**v1.0.12** - 2025-11-30
- CRITICAL FIX: Hook registration timing - ingestion now initialized on 'init' action (priority 20) instead of 'plugins_loaded'
- NEW: Added bbp_new_topic_post_extras and bbp_new_reply_post_extras hooks as recommended by BuddyBoss documentation
- NEW: Added bbp_edit_topic_post_extras and bbp_edit_reply_post_extras for edited forum content
- NEW: Immediate content processing - forum content sent to CheckStep API immediately instead of just queuing
- NEW: Scheduled queue processing via WP-Cron (runs every minute)
- Added: process_content_immediately() method for direct API submission
- Added: Custom cron schedule 'every_minute' for queue processing
- Fixed: BuddyBoss forum hooks now fire correctly (were previously registered too late)
- Improved: Split init_components into init_core_components and init_ingestion for proper timing
- Improved: API calls and responses now properly logged with CheckStep_Logger::api() and api_response()

**v1.0.11** - 2025-11-30
- NEW: Logs viewer tab in plugin settings - view all hook triggers, API calls, and system events
- NEW: Database storage for logs with filtering by level, type, and search
- NEW: BuddyBoss Activity hooks - bp_activity_posted_update, bp_groups_posted_update, bp_activity_after_save
- NEW: Activity comment moderation via bp_activity_comment_posted hook
- NEW: User profile update hooks - xprofile_updated_profile, profile_update, user_register
- NEW: Private message moderation via messages_message_sent hook
- NEW: BuddyBoss media moderation via bp_media_add hook
- Added: Comprehensive hook logging to track all content creation events
- Added: API call and response logging for debugging
- Fixed: Missing hooks for new posts - added publish_post and transition_post_status hooks

**v1.0.10** - 2025-11-30
- Enhanced: User Profile with comprehensive fields (display_name, email, role, profile_picture, bio, social links)
- Enhanced: Blog Post with publish_date, custom_taxonomies (content-warning), fragments for embedded media
- Enhanced: Forum Post with thread_id, timestamp, custom_taxonomies support
- Enhanced: Image with alt_text, caption, parent_content, and image metadata (dimensions, file size)
- Enhanced: Video with title, parent_content, duration, and video metadata
- Improved: All content types now use consistent CheckStep API format (id, author, type, fields[], parent?, metadata?)
- Added: Media fragment extraction from blog post content (embedded images, YouTube, Vimeo videos)
- Added: BuddyBoss extended profile fields integration for user profiles

**v1.0.9** - 2025-11-30
- NEW: Forum topic/discussion moderation support via bbp_new_topic hook
- Added get_forum_topic() method for CheckStep API-formatted topic data
- Added get_forum_reply() method for CheckStep API-formatted reply data  
- Content now formatted per CheckStep API spec: id, author, type, fields, parent, metadata
- Uses CheckStep complex types: 'thread' for topics, 'reply' for replies, 'channel' for forums
- Media attachments (images, videos, documents) properly extracted from forum posts
- Rich metadata included: author info, platform, timestamps, forum/topic context

**v1.0.8** - 2025-11-13
- CRITICAL FIX: Test Connection now works with unsaved API keys
- Removed exception throwing from CheckStep_API constructor when key is missing
- Users can test connection before saving settings
- Added API key validation checks to send_content() and get_decision() methods
- Better UX: test before save workflow now supported

**v1.0.7** - 2025-11-13
- Fixed Test Connection button 404 error
- Added ajax_test_connection AJAX handler in CheckStep_Admin class
- Added test_connection method to CheckStep_API class
- Test Connection button now validates API key with CheckStep API ping endpoint
- Proper error handling and user feedback for connection tests

**v1.0.6** - 2025-11-13
- CRITICAL FIX: Removed JavaScript AJAX interception that prevented form submission
- Settings form now submits normally to options.php (WordPress standard)
- Resolved 400 error from admin-ajax.php when clicking "Save Changes"
- JavaScript no longer prevents default form submission behavior
- Settings save now works correctly with WordPress Settings API

**v1.0.5** - 2025-11-13
- CRITICAL FIX: Settings now save properly when "Save Changes" button is clicked
- Added proper sanitization callbacks to register_setting() calls
- API Key and Webhook Secret use sanitize_text_field()
- Appeal URL uses esc_url_raw() for proper URL validation
- Settings now save reliably with WordPress validation

**v1.0.4** - 2025-11-10
- CRITICAL FIX: Resolved fatal error when BuddyBoss is not installed
- BuddyBoss tab file (class-checkstep-admin-tab.php) now only loads if BP_Admin_Integration_tab class exists
- Plugin now works completely standalone without BuddyBoss
- Admin pages (Settings, Moderation Queue) fully accessible without BuddyBoss

**v1.0.3** - 2025-11-10
- CRITICAL FIX: Admin menu items now appear in WordPress admin
- Fixed "Sorry, you are not allowed to access this page" error
- Fixed admin initialization timing - admin interface now loads earlier
- Admin hooks now register at correct point in WordPress initialization sequence
- Settings → CheckStep and Tools → Moderation Queue now accessible

**v1.0.2** - 2025-11-10
- Added Settings link on plugins list page (next to activate/deactivate)
- Link goes to options-general.php?page=checkstep-settings
- Improves user experience for quick access to plugin configuration

**v1.0.1** - 2025-11-10
- Fixed admin settings page not loading when BuddyBoss is missing
- Settings → CheckStep menu now appears in WordPress admin regardless of BuddyBoss
- Tools → Moderation Queue menu now accessible without BuddyBoss
- Plugin shows warning instead of preventing load when BuddyBoss is not installed
- Admin interface now works independently of BuddyBoss (content moderation still requires it)

**v1.0.0** - Initial release
- Basic content moderation features
- BuddyBoss integration
- Webhook support
- API integration

## Project Structure

```
checkstep-integration/
├── admin/                      # Admin interface
│   ├── class-checkstep-admin.php          # Main settings page
│   ├── class-checkstep-admin-tab.php      # BuddyBoss integration tab
│   └── partials/
│       ├── settings-page.php              # Settings page template
│       └── queue-page.php                 # Moderation queue page
├── assets/                     # CSS and JavaScript
│   ├── css/admin.css
│   └── js/admin.js
├── includes/                   # Core functionality
│   ├── class-checkstep-api.php           # API client
│   ├── class-checkstep-content-types.php # Content type handlers
│   ├── class-checkstep-ingestion.php     # Content ingestion
│   ├── class-checkstep-moderation.php    # Moderation logic
│   ├── class-checkstep-notifications.php # User notifications
│   ├── class-checkstep-webhook-handler.php # Webhook processing
│   └── class-checkstep-logger.php        # Logging utility
├── tests/                      # Test files
├── checkstep-integration.php   # Main plugin file
├── README.md                   # Full documentation
├── readme.txt                  # WordPress.org readme
└── INSTALL.txt                 # Quick install guide
```

## Installation

This is a WordPress plugin. To use it:

1. Install WordPress (5.0+) with PHP 7.4+
2. Upload `checkstep-integration` folder to `/wp-content/plugins/`
3. Activate through WordPress admin: Plugins → Installed Plugins
4. Configure at: Settings → CheckStep

## Configuration

### Settings Location

**Settings → CheckStep** (Primary)
- API Key configuration
- Webhook Secret configuration
- Appeal URL (optional)
- Connection testing
- Webhook endpoint URL display

**Tools → Moderation Queue**
- View pending items
- Process moderation decisions
- Queue statistics

### Required Settings

1. **API Key** - Your CheckStep API key (get from CheckStep dashboard)
2. **Webhook Secret** - Secret for webhook verification (get from CheckStep dashboard)
3. **Appeal URL** - Optional URL where users can appeal decisions

### Webhook Setup

Copy the webhook URL from Settings → CheckStep:
```
https://yoursite.com/wp-json/checkstep/v1/decisions
```

Add this to your CheckStep dashboard webhook configuration.

## Features

### Content Monitoring (Requires BuddyBoss)

Automatically monitors:
- Activity posts
- Forum posts and topics
- Private messages
- User profiles
- Group posts
- Blog posts

### Admin Interface (Works Without BuddyBoss)

- Settings page for API configuration
- Connection testing
- Webhook URL display
- Queue management interface

### Webhook Events

Handles these CheckStep events:
- `decision_made` - Moderation decision received
- `incident_escalated` - Incident requires review
- `incident_closed` - Incident resolved

## Development Notes

### Version Numbering

Version numbers follow semantic versioning (MAJOR.MINOR.PATCH):
- Increment PATCH (0.0.1) for bug fixes and minor updates
- Increment MINOR (0.1.0) for new features (backward compatible)
- Increment MAJOR (1.0.0) for breaking changes

Current version must be updated in:
1. `checkstep-integration.php` - Plugin header comment (line 9)
2. `checkstep-integration.php` - @version docblock (line 18)
3. `checkstep-integration.php` - CHECKSTEP_VERSION constant (line 25)
4. `readme.txt` - Stable tag header (line 6)
5. `readme.txt` - Changelog section (add new entry at top)

### Testing

The plugin can be tested in this Replit environment using the test files:
```bash
cd checkstep-integration
php tests/test-webhook-handler.php
```

However, full WordPress functionality requires:
- WordPress installation
- BuddyBoss Platform (optional for admin interface, required for moderation)
- Valid CheckStep API credentials

## Important Notes

⚠️ **WordPress Required**: This is a WordPress plugin and cannot run standalone. The current workflow in this Replit runs a PHP server for testing only.

⚠️ **BuddyBoss Optional**: As of v1.0.1, the admin settings page works without BuddyBoss. Content moderation features still require BuddyBoss Platform.

⚠️ **API Credentials**: Store credentials securely. The plugin uses WordPress options table for storage and masks values in the UI.

## Support

- Check INSTALL.txt for quick setup guide
- Review README.md for full documentation
- Check WordPress debug logs for errors
- Contact CheckStep support for API issues

## User Preferences

- Increment version by 0.0.1 (PATCH) for all changes
- Update all 5 version locations when making updates
- Document changes in readme.txt changelog
