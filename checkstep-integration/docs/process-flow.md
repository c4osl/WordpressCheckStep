# CheckStep Integration Process Flow

This document provides visual process flow diagrams for the CheckStep WordPress plugin integration with BuddyBoss Platform.

## System Architecture Overview

```mermaid
flowchart TB
    subgraph WordPress["WordPress / BuddyBoss Platform"]
        WP_Hooks["WordPress Hooks<br>(save_post, publish_post, add_attachment)"]
        BB_Hooks["BuddyBoss Hooks<br>(bbp_new_topic, bp_activity_posted_update,<br>bp_media_add, messages_message_sent)"]
        WP_Cron["WP-Cron<br>(Async Queue Processing)"]
        BB_Messaging["BuddyBoss Messaging<br>(User Notifications)"]
        BB_Reports["BuddyBoss Reports<br>(bp_moderation_add)"]
    end

    subgraph Plugin["CheckStep Integration Plugin"]
        Ingestion["Ingestion Handler<br>(class-checkstep-ingestion.php)"]
        ContentTypes["Content Types<br>(class-checkstep-content-types.php)"]
        API["API Client<br>(class-checkstep-api.php)"]
        Queue["Content Queue<br>(Database Table)"]
        Webhook["Webhook Handler<br>(class-checkstep-webhook-handler.php)"]
        Moderation["Moderation Actions<br>(class-checkstep-moderation.php)"]
        Logger["Logger<br>(class-checkstep-logger.php)"]
    end

    subgraph CheckStep["CheckStep Platform"]
        CS_API["CheckStep Standard API<br>/content endpoint"]
        CS_Headless["CheckStep Headless API<br>/reports endpoint"]
        CS_Scan["AI/Keyword Scanning"]
        CS_Decision["Moderation Decisions"]
        CS_Webhook["Webhook Callbacks"]
    end

    WP_Hooks --> Ingestion
    BB_Hooks --> Ingestion
    BB_Reports --> Ingestion
    Ingestion --> ContentTypes
    ContentTypes --> Queue
    WP_Cron --> Queue
    Queue --> API
    API -->|Content| CS_API
    API -->|Reports| CS_Headless
    
    CS_API --> CS_Scan
    CS_Headless --> CS_Scan
    CS_Scan --> CS_Decision
    CS_Decision --> CS_Webhook
    CS_Webhook --> Webhook
    Webhook --> Moderation
    Moderation --> BB_Messaging
    
    Ingestion --> Logger
    API --> Logger
    Webhook --> Logger
```

## Content Ingestion Flow (Asynchronous)

This diagram shows the complete flow from content creation to CheckStep submission.
Per the design specification, all content submission to CheckStep is performed asynchronously
via WP-Cron or background processing to avoid impacting front-end performance.

```mermaid
flowchart TD
    Start([Content Created/Updated])
    
    subgraph Detection["Hook Detection"]
        H1{Content Type?}
        ForumHook["bbp_new_topic<br>bbp_new_reply"]
        ActivityHook["bp_activity_posted_update<br>bp_groups_posted_update"]
        ProfileHook["bp_core_activated_user<br>xprofile_updated_profile"]
        PostHook["save_post<br>publish_post"]
        MediaHook["add_attachment<br>bp_media_add"]
        MessageHook["messages_message_sent"]
    end

    subgraph Formatting["Content Formatting"]
        GetTopic["get_forum_topic()<br>Uses bbp_get_topic_content()"]
        GetReply["get_forum_reply()<br>Uses bbp_get_reply_content()"]
        GetActivity["get_activity_post()"]
        GetProfile["get_user_profile()"]
        GetPost["get_blog_post()"]
        GetMedia["get_image() / get_video()"]
        GetMessage["get_private_message()"]
        
        BuildJSON["Build CheckStep JSON<br>{id, author, type, fields[], parent?, metadata?}"]
    end

    subgraph QueueFirst["Queue-First Async Model"]
        AddToQueue["add_to_queue()<br>Store in DB for async processing"]
        WPCron["WP-Cron Trigger<br>(every minute)"]
        ProcessQueue["process_queue()<br>Background processing"]
        APICall["API: POST /content<br>(Async submission)"]
        Success{Success?}
        RetryLater["Mark for retry<br>(exponential backoff)"]
        MarkComplete["Mark as processed"]
        LogResult["Log API Call & Response"]
    end

    Start --> H1
    H1 -->|Forum Topic| ForumHook
    H1 -->|Forum Reply| ForumHook
    H1 -->|Activity| ActivityHook
    H1 -->|User Profile| ProfileHook
    H1 -->|Blog Post| PostHook
    H1 -->|Media| MediaHook
    H1 -->|Message| MessageHook

    ForumHook --> GetTopic
    ForumHook --> GetReply
    ActivityHook --> GetActivity
    ProfileHook --> GetProfile
    PostHook --> GetPost
    MediaHook --> GetMedia
    MessageHook --> GetMessage

    GetTopic --> BuildJSON
    GetReply --> BuildJSON
    GetActivity --> BuildJSON
    GetProfile --> BuildJSON
    GetPost --> BuildJSON
    GetMedia --> BuildJSON
    GetMessage --> BuildJSON

    BuildJSON --> AddToQueue
    AddToQueue --> WPCron
    WPCron --> ProcessQueue
    ProcessQueue --> APICall
    APICall --> Success
    Success -->|Yes| MarkComplete
    Success -->|No| RetryLater
    MarkComplete --> LogResult
    RetryLater --> LogResult
    LogResult --> End([Complete])
```

Note: The current implementation uses a hybrid approach where some hooks (forum posts, user profiles) 
attempt immediate API calls for faster moderation, with queue fallback on failure. The design 
specification recommends full asynchronous queue-first processing for high-traffic sites.

## Webhook Decision Flow

This diagram shows how moderation decisions from CheckStep are processed.

```mermaid
flowchart TD
    Incoming([Webhook Received<br>POST /wp-json/checkstep/v1/decisions])
    
    subgraph Validation["Request Validation"]
        VerifySig["Verify Webhook Signature<br>(HMAC-SHA256)"]
        ValidSig{Valid?}
        Reject["Return 401 Unauthorized"]
        ParsePayload["Parse JSON Payload"]
    end

    subgraph EventRouting["Event Routing"]
        EventType{Event Type?}
        DecisionMade["decision_made"]
        Escalated["incident_escalated"]
        Closed["incident_closed"]
    end

    subgraph Actions["Moderation Actions"]
        GetDecision["Get Decision Details"]
        ActionType{Action?}
        
        DeleteContent["Delete/Hide Content"]
        AddWarning["Add Content Warning<br>(content-warning taxonomy)"]
        ApproveContent["Mark Approved"]
        BanUser["Ban/Suspend User"]
        
        NotifyUser["Send BuddyBoss Message<br>with Appeal Link"]
    end

    Incoming --> VerifySig
    VerifySig --> ValidSig
    ValidSig -->|No| Reject
    ValidSig -->|Yes| ParsePayload
    
    ParsePayload --> EventType
    EventType -->|decision_made| DecisionMade
    EventType -->|incident_escalated| Escalated
    EventType -->|incident_closed| Closed

    DecisionMade --> GetDecision
    GetDecision --> ActionType
    
    ActionType -->|delete| DeleteContent
    ActionType -->|warn| AddWarning
    ActionType -->|approve| ApproveContent
    ActionType -->|ban| BanUser

    DeleteContent --> NotifyUser
    AddWarning --> NotifyUser
    BanUser --> NotifyUser
    ApproveContent --> End([Complete])
    NotifyUser --> End

    Escalated --> LogEscalation["Log for Admin Review"]
    Closed --> LogClosed["Update Incident Status"]
    LogEscalation --> End
    LogClosed --> End
```

## Community Reports Flow (Headless API)

This diagram shows how community-generated moderation reports from BuddyBoss are forwarded to CheckStep's Headless API.

```mermaid
flowchart TD
    Start([User Reports Content])
    
    subgraph BuddyBoss["BuddyBoss Moderation"]
        BB_Report["BuddyBoss Report Button<br>(bp_moderation_report)"]
        BB_Flag["Content Flagged by User"]
        CaptureHook["Capture Report Event<br>bp_moderation_add hook"]
    end

    subgraph ReportData["Report Data Collection"]
        GetReporter["Get Reporter Info<br>(user_id, username)"]
        GetContent["Get Reported Content<br>(content_id, type)"]
        GetReason["Get Report Reason<br>(reason_code, description)"]
        BuildReport["Build Report Payload"]
    end

    subgraph Headless["CheckStep Headless API"]
        SubmitReport["POST /reports<br>(Community Report)"]
        ReportQueued["Report Queued for Review"]
        ModeratorReview["Human/AI Review"]
        Decision["Decision Made"]
    end

    subgraph Outcome["Report Outcome"]
        Webhook["Webhook Callback"]
        Action["Execute Action"]
        NotifyReporter["Notify Reporter of Outcome"]
        NotifyAuthor["Notify Content Author"]
    end

    Start --> BB_Report
    BB_Report --> BB_Flag
    BB_Flag --> CaptureHook
    
    CaptureHook --> GetReporter
    CaptureHook --> GetContent
    CaptureHook --> GetReason
    
    GetReporter --> BuildReport
    GetContent --> BuildReport
    GetReason --> BuildReport
    
    BuildReport --> SubmitReport
    SubmitReport --> ReportQueued
    ReportQueued --> ModeratorReview
    ModeratorReview --> Decision
    
    Decision --> Webhook
    Webhook --> Action
    Action --> NotifyReporter
    Action --> NotifyAuthor
```

## Queue Processing Flow

This diagram shows the background queue processing mechanism.

```mermaid
flowchart TD
    Cron([WP-Cron Trigger<br>checkstep_process_queue])
    
    subgraph QueueProcess["Queue Processing"]
        GetItems["Get Pending Items<br>(Limit: 10 per batch)"]
        HasItems{Items?}
        NoItems["Exit - Nothing to Process"]
        
        Loop["For Each Item"]
        GetContent["Get Content Data<br>by Type"]
        
        ContentValid{Valid?}
        MarkFailed["Mark as Failed<br>Log Error"]
        
        SendAPI["Send to CheckStep API"]
        APISuccess{Success?}
        
        MarkComplete["Mark as Processed"]
        IncrementRetry["Increment Retry Count"]
        MaxRetries{Max Retries?}
        MarkPermanentFail["Mark as Permanent Failure"]
    end

    Cron --> GetItems
    GetItems --> HasItems
    HasItems -->|No| NoItems
    HasItems -->|Yes| Loop
    
    Loop --> GetContent
    GetContent --> ContentValid
    ContentValid -->|No| MarkFailed
    ContentValid -->|Yes| SendAPI
    
    SendAPI --> APISuccess
    APISuccess -->|Yes| MarkComplete
    APISuccess -->|No| IncrementRetry
    
    IncrementRetry --> MaxRetries
    MaxRetries -->|Yes| MarkPermanentFail
    MaxRetries -->|No| Loop
    
    MarkComplete --> Loop
    MarkFailed --> Loop
    MarkPermanentFail --> Loop
    
    NoItems --> End([Complete])
```

## Content Type JSON Structure

This diagram shows the standard JSON payload structure for CheckStep API.

```mermaid
flowchart LR
    subgraph Payload["CheckStep Content Payload"]
        direction TB
        ID["id: string<br>(Content ID)"]
        Author["author: string<br>(User ID)"]
        Type["type: string<br>(thread, reply, post, profile, image, video)"]
        
        subgraph Fields["fields: array"]
            direction TB
            TextField["Text Field<br>{id, type: 'text', src}"]
            ImageField["Image Field<br>{id, type: 'image', src: URL}"]
            VideoField["Video Field<br>{id, type: 'video', src: URL}"]
        end
        
        subgraph Parent["parent: object (optional)"]
            ParentType["type: string<br>(channel, thread)"]
            ParentID["id: string"]
        end
        
        subgraph Metadata["metadata: object"]
            Platform["platform: 'buddyboss'"]
            Timestamp["timestamp: ISO 8601"]
            AuthorInfo["author_name, author_role"]
            Context["Additional context data"]
        end
    end
```

## Logging System Flow

```mermaid
flowchart TD
    Event([Event Occurs])
    
    subgraph LogLevels["Log Level Check"]
        CheckLevel{Log Level Enabled?}
        Error["ERROR - Always logged"]
        Warning["WARNING - Configurable"]
        Info["INFO - Configurable"]
        Debug["DEBUG - Configurable"]
    end

    subgraph LogTypes["Log Types"]
        HookLog["hook - Hook triggered"]
        APILog["api_call - API request sent"]
        APIResponse["api_response - API response received"]
        SystemLog["system - System events"]
    end

    subgraph Storage["Database Storage"]
        InsertLog["INSERT into checkstep_logs<br>(timestamp, level, type, message, context)"]
        Cleanup["Auto-cleanup old logs<br>(configurable retention)"]
    end

    subgraph Admin["Admin Interface"]
        LogsViewer["Logs Viewer Tab"]
        Filter["Filter by Level/Type"]
        Search["Search Messages"]
        Export["Export to CSV"]
    end

    Event --> CheckLevel
    CheckLevel -->|Disabled| Skip([Skip Logging])
    CheckLevel -->|Enabled| LogTypes
    
    HookLog --> InsertLog
    APILog --> InsertLog
    APIResponse --> InsertLog
    SystemLog --> InsertLog
    
    InsertLog --> Cleanup
    Cleanup --> Admin
    
    LogsViewer --> Filter
    Filter --> Search
    Search --> Export
```

## Complete System Sequence (with Appeals)

```mermaid
sequenceDiagram
    participant User as User/Author
    participant Reporter as Reporter (optional)
    participant WP as WordPress/BuddyBoss
    participant Plugin as CheckStep Plugin
    participant Queue as Content Queue
    participant API as CheckStep API
    participant Mod as CheckStep Moderation
    participant Webhook as Webhook Handler

    Note over User,Webhook: Content Creation & Ingestion Flow
    User->>WP: Creates Content (Post/Topic/Reply)
    WP->>Plugin: Triggers Hook (bbp_new_topic, etc.)
    Plugin->>Plugin: Format Content (get_forum_topic)
    Plugin->>Queue: Add to Queue (async)
    
    Note over Queue,API: Background Processing (WP-Cron)
    Queue->>Plugin: Process Queue Items
    Plugin->>API: POST /content (Batch)
    
    alt API Success
        API-->>Plugin: 200 OK + Content ID
        Plugin->>Plugin: Mark Processed & Log
    else API Failure
        API-->>Plugin: Error Response
        Plugin->>Queue: Mark for Retry
    end

    Note over API,Mod: AI/Keyword Scanning
    API->>Mod: Content Analysis
    Mod->>Mod: AI + Keyword Scanning

    Note over Reporter,API: Community Reports (Headless API)
    Reporter->>WP: Report Content (Flag)
    WP->>Plugin: bp_moderation_add hook
    Plugin->>API: POST /reports (Headless)
    API->>Mod: Add Community Report

    Note over Mod,User: Decision & Actions
    Mod->>Mod: Generate Decision
    Mod->>Webhook: POST Decision Callback
    Webhook->>Plugin: Receive Decision
    Plugin->>Plugin: Verify HMAC Signature
    Plugin->>WP: Execute Action (Delete/Warn/Ban)
    Plugin->>User: BuddyBoss Message + Appeal Link
    Plugin->>Reporter: Notification (Report Outcome)

    Note over User,API: Appeal Process
    User->>API: Submit Appeal (via Appeal URL)
    API->>Mod: Re-review Content
    Mod->>Mod: Human Review
    
    alt Appeal Approved
        Mod->>Webhook: Appeal Approved
        Webhook->>Plugin: Reverse Action
        Plugin->>WP: Restore Content
        Plugin->>User: Content Restored Notification
    else Appeal Denied
        Mod->>Webhook: Appeal Denied
        Webhook->>Plugin: Maintain Decision
        Plugin->>User: Appeal Denied Notification
    end
```

---

## File References

| Component | File |
|-----------|------|
| Main Plugin | `checkstep-integration.php` |
| Ingestion Handler | `includes/class-checkstep-ingestion.php` |
| Content Types | `includes/class-checkstep-content-types.php` |
| API Client | `includes/class-checkstep-api.php` |
| Webhook Handler | `includes/class-checkstep-webhook-handler.php` |
| Moderation Actions | `includes/class-checkstep-moderation.php` |
| Logger | `includes/class-checkstep-logger.php` |
| Admin Settings | `admin/class-checkstep-admin.php` |
| Admin Tab (BuddyBoss) | `admin/class-checkstep-admin-tab.php` |

---

*Generated from Design Document v2025-02-08*
