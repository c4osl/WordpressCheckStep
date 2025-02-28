# Design document for CheckStep Wordpress integration

## Version 2025-02-08 — Commercial in confidence — J Malcolm/M Jimon

***

## 1. Overview

This is a specification for a plugin that would provide seamless integration between a BuddyBoss-powered website and CheckStep’s moderation system. The integration leverages both CheckStep’s Standard (asynchronous ingestion and scanning) and Headless (community reports, appeals, and decisions) APIs, as documented at [docs.checkstep.com](https://docs.checkstep.com/). The plugin will ingest various complex content types (e.g., user profiles, blog posts, forum posts, images, videos) asynchronously, pass along associated metadata (such as custom content warnings via a dedicated taxonomy), and integrate existing BuddyBoss moderation events.

## 2. Environment and Assumptions

- **WordPress Installation Requirements**:
    - WordPress (version 5.0+)
    - BuddyBoss Platform and BuddyBoss Platform Pro
    - BuddyPress User Blog (optional but recommended)
    - Dead Dove (version 2.0, under development by us and could be merged into this project)
- **Integration Mode**:
    - **Standard Integration**: For ingesting content asynchronously into CheckStep for scanning (keyword and AI-based analysis).
    - **Headless Integration**: For passing community moderation reports (sourced from BuddyBoss moderation features) and handling decisions and appeals.
- **Ingestion**: All content submission to CheckStep will be performed asynchronously (e.g., using WP-Cron or background processing).
- **Notification**: Use the BuddyBoss messaging system to notify users about moderation decisions and provide them with an appeal link.

## 3. Complex Types Definitions

Each complex type is to be mapped to a JSON structure for ingestion by CheckStep. The following types should be defined:

### 3.1. User Profile

- **Fields**:
    - **user_id**: Unique identifier from WordPress.
    - **display_name**: User’s public display name.
    - **email**: (If permitted by privacy settings) email address.
    - **role**: User’s role (e.g., subscriber, contributor, moderator).
    - **profile_picture**: URL to the profile image.
    - **metadata**: Any additional BuddyBoss profile fields (e.g., bio, social links).
- **Use Case**: Ingested for AI or keyword scanning when user profiles are moderated, and for associating moderation decisions with user actions.

### 3.2. Blog Post

- **Fields**:
    - **post_id**: Unique ID.
    - **title**: Title of the post.
    - **content**: Main body of text.
    - **author**: Reference to the User Profile.
    - **publish_date**: Timestamp.
    - **custom_taxonomies**: Includes the `content-warning` taxonomy if set.
    - **fragments**: References (URLs or attachment IDs) for moderation images or videos embedded in the post.
    - **metadata**: Custom fields from BuddyPress User Blog if applicable.
- **Use Case**: Processed by CheckStep for content scanning with support for extracting and scanning embedded media.

### 3.3. Forum Post

- **Fields**:
    - **forum_post_id**: Unique identifier.
    - **thread_id**: Identifier of the forum thread.
    - **content**: Forum post body.
    - **author**: Reference to the User Profile.
    - **timestamp**: Date and time of posting.
    - **fragments**: Attached images or video clips (if any).
    - **custom_taxonomies**: Support for `content-warning` tags.
- **Use Case**: Similar to blog posts, with emphasis on community-generated content and associated reporting.

### 3.4. Image

- **Fields**:
    - **image_id**: Unique ID or attachment ID.
    - **url**: Direct URL to the image.
    - **alt_text**: Accessibility text.
    - **caption**: Optional caption.
    - **parent_content**: ID of the content (blog post, forum post, etc.) where the image is used.
- **Use Case**: To allow CheckStep’s AI-based image analysis.

### 3.5. Video

- **Fields**:
    - **video_id**: Unique identifier.
    - **url**: URL or embed link.
    - **title**: Title or description.
    - **parent_content**: ID of the associated blog or forum post.
- **Use Case**: For video moderation via CheckStep’s scanning strategies, including processing of embedded fragments.

## 4. Trust and Safety Actions

The plugin must support a range of trust and safety actions for both content and authors:

### 4.1. Content Actions

- **Delete Content**: Remove the content (blog post, forum post, image, video) from public view.
- **Hide/Flag Content**: Temporarily hide content pending review.
- **Add Content Warning**:
    - **Implementation**: Use a custom WordPress taxonomy named `content-warning`from the Dead Dove plugin.
    - **Process**: If content already includes warnings at ingestion, pass these as tags to CheckStep.
    - **Question**: Would it be necessary to define the content warnings available in Wordpress as policy violations in CheckStep, even though some "warnings" are not violations per se?

### 4.2. Author Actions

- **Ban/Block User**: Prevent a user from further posting or logging in.
- **Suspend Account**: Temporarily disable a user account pending further review.
- **Other Actions**: Optionally add actions like reducing privileges or requiring re-verification.

### 4.3. Notification & Appeal Workflow

- **User Notification**:
    - Use BuddyBoss’s messaging system to send notifications that include:
        - A summary of the moderation decision.
        - A link to an appeal endpoint (hosted via CheckStep).
- **Appeal Submission**:
    - Provide a mechanism for users to submit appeals, which are then forwarded to CheckStep for re-assessment.

## 5. Content Ingestion & Scanning Strategy

### 5.1. Asynchronous Ingestion Workflow

- **Trigger Points**:
    - New or updated content (blog posts, forum posts, user profiles) and new media attachments (images/videos) will trigger asynchronous ingestion tasks.
    - Content creation hooks (e.g., `publish_post`, BuddyBoss-specific hooks) and scheduled checks for modifications.
- **Payload Construction**:
    - Construct JSON payloads representing the relevant complex types.
    - Include all associated metadata, such as custom taxonomy terms (i.e., `content-warning` values) and fragments.
- **Submission**:
    - Use CheckStep’s Standard Integration API endpoints for sending the content asynchronously.
    - Ensure that each submission includes metadata for scanning strategies:
        - **Keyword Scanning**: Textual content fields.
        - **AI-Based Analysis**: For text, images, videos.
        - **Complex Type Scanning**: In the case of the OpenAI strategy, the full JSON structure representing a complex type (e.g., a blog post with embedded fragments).

### 5.2. Integration with BuddyBoss Moderation

- **Community Reports**:
    - When users or moderators flag content using BuddyBoss’s built-in moderation tools, capture these events.
    - Forward these reports to CheckStep as community reports via the Headless Integration API.
    - Include contextual data such as reporter identity, reason codes, and associated content IDs.

## 6. API & Communication

### 6.1. Outgoing Requests to CheckStep

- **Authentication**:
    - API keys must be used in all outgoing requests.
    - Secure storage of API credentials within the plugin configuration.
- **Error Handling & Retries**:
    - Implement robust error logging.
    - Set up retries for failed requests using exponential backoff or scheduled re-tries via WP-Cron.

### 6.2. Incoming Webhooks and Callbacks

- **Moderation Decisions**:
    - Create endpoints to receive CheckStep’s moderation decisions (e.g., content flagged, content approved, appeals outcomes).
- **Action Mapping**:
    - On receiving a decision, map the action to a corresponding WordPress action (e.g., delete post, assign content-warning taxonomy, ban user).
- **User Messaging**:
    - Use the BuddyBoss messaging system to notify affected users, including relevant details and appeal links.

## 7. Monetization

- **Freemium model:**
    - The plugin would be free/GPL, as needed for listing in the Wordpress plugin directory**.**
- **Flagship website:**
    - The plugin will be used on the Fan Refuge platform, under development.
- **Integrated CheckStep signup:**
    - A reseller commission would be payable for users who sign up for CheckStep via the plugin.
- **Marketplace:**
    - Provide access to paid support and trust and safety consulting (from me), and human moderation services (eg. from ModSquad, already a partner of CheckStep).

## 8. Implementation Considerations

- **Hooks and Actions**:
    - Leverage WordPress hooks (e.g., `save_post`, `wp_insert_user`) and BuddyBoss-specific events to trigger ingestion and report generation.
- **Background Processing**:
    - Consider using asynchronous processing libraries (or WP-Cron) to handle delayed submissions without impacting front-end performance.
- **Extensibility**:
    - Expose filters and actions so developers can modify payloads, change behavior for custom content types, or adjust notification workflows.
- **Security**:
    - Ensure all endpoints (both outgoing and incoming) are secured (using nonces, capability checks, etc.).
    - Validate and sanitize all data going in and out.
- **Performance**:
    - Optimize database queries and limit the frequency of background tasks to minimize performance impacts on a high-traffic BuddyBoss site.

***