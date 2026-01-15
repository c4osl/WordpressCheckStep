<?php
/**
 * Process Flow Diagram Page
 *
 * Displays visual process flow diagrams for the CheckStep integration.
 *
 * @package CheckStep_Integration
 * @since 1.0.17
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap checkstep-process-flow">
    <h1>CheckStep Integration Process Flow</h1>
    <p>Visual diagrams showing how content flows through the CheckStep moderation system.</p>

    <div class="nav-tab-wrapper">
        <a href="#architecture" class="nav-tab nav-tab-active" data-tab="architecture">System Architecture</a>
        <a href="#ingestion" class="nav-tab" data-tab="ingestion">Content Ingestion</a>
        <a href="#reports" class="nav-tab" data-tab="reports">Community Reports</a>
        <a href="#webhook" class="nav-tab" data-tab="webhook">Webhook Processing</a>
        <a href="#queue" class="nav-tab" data-tab="queue">Queue Processing</a>
        <a href="#sequence" class="nav-tab" data-tab="sequence">Full Sequence</a>
    </div>

    <div id="architecture" class="tab-content active">
        <h2>System Architecture Overview</h2>
        <p>This diagram shows the overall system architecture. All content is processed asynchronously via a queue.</p>
        <div class="mermaid">
flowchart TB
    subgraph WordPress["WordPress / BuddyBoss Platform"]
        WP_Hooks["WordPress Hooks<br>(posts, media)"]
        BB_Hooks["BuddyBoss Hooks<br>(forums, activities, messages)"]
        BB_Reports["BuddyBoss Reports"]
        WP_Cron["WP-Cron<br>(Async Processing)"]
        BB_Messaging["User Notifications"]
    end

    subgraph Plugin["CheckStep Integration Plugin"]
        Ingestion["Ingestion Handler"]
        ContentTypes["Content Types"]
        Queue["Async Queue"]
        API["API Client"]
        Webhook["Webhook Handler"]
        Moderation["Moderation Actions"]
    end

    subgraph CheckStep["CheckStep Platform"]
        CS_API["Standard API<br>/content"]
        CS_Headless["Headless API<br>/reports"]
        CS_Scan["AI/Keyword Scanning"]
        CS_Decision["Decisions"]
        CS_Webhook["Webhooks"]
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
        </div>
    </div>

    <div id="ingestion" class="tab-content">
        <h2>Content Ingestion Flow (Asynchronous)</h2>
        <p>Shows the complete flow for all content types. All submissions are processed asynchronously via WP-Cron queue.</p>
        <div class="mermaid">
flowchart TD
    Start([Content Created/Updated])
    
    subgraph Detection["Hook Detection - All Content Types"]
        H1{Content Type?}
        ForumHook["Forum<br>bbp_new_topic, bbp_new_reply"]
        ActivityHook["Activity<br>bp_activity_posted_update"]
        ProfileHook["User Profile<br>bp_core_activated_user"]
        PostHook["Blog Post<br>save_post, publish_post"]
        MediaHook["Image/Video<br>add_attachment, bp_media_add"]
        MessageHook["Messages<br>messages_message_sent"]
    end

    subgraph Formatting["Content Formatting"]
        GetContent["Get Content Data<br>Using BuddyBoss Functions"]
        BuildJSON["Build CheckStep JSON<br>{id, author, type, fields[]}"]
    end

    subgraph AsyncQueue["Queue-First Async Model"]
        AddQueue["add_to_queue()<br>Store for async processing"]
        WPCron["WP-Cron Trigger"]
        ProcessQueue["process_queue()"]
        APICall["API: POST /content"]
        Success{Success?}
        MarkComplete["Mark Processed"]
        RetryLater["Mark for Retry"]
        LogResult["Log API Call"]
    end

    Start --> H1
    H1 -->|Forum| ForumHook
    H1 -->|Activity| ActivityHook
    H1 -->|Profile| ProfileHook
    H1 -->|Blog Post| PostHook
    H1 -->|Media| MediaHook
    H1 -->|Message| MessageHook

    ForumHook --> GetContent
    ActivityHook --> GetContent
    ProfileHook --> GetContent
    PostHook --> GetContent
    MediaHook --> GetContent
    MessageHook --> GetContent

    GetContent --> BuildJSON
    BuildJSON --> AddQueue
    AddQueue --> WPCron
    WPCron --> ProcessQueue
    ProcessQueue --> APICall
    APICall --> Success
    Success -->|Yes| MarkComplete
    Success -->|No| RetryLater
    MarkComplete --> LogResult
    RetryLater --> LogResult
    LogResult --> End([Complete])
        </div>
    </div>

    <div id="reports" class="tab-content">
        <h2>Community Reports Flow (Headless API)</h2>
        <p>Shows how user-generated moderation reports from BuddyBoss are forwarded to CheckStep's Headless API.</p>
        <div class="mermaid">
flowchart TD
    Start([User Reports Content])
    
    subgraph BuddyBoss["BuddyBoss Moderation"]
        BBReport["Report Button Clicked"]
        BBFlag["Content Flagged"]
        CaptureHook["Capture: bp_moderation_add"]
    end

    subgraph ReportData["Report Data"]
        GetReporter["Get Reporter Info"]
        GetContent["Get Reported Content"]
        GetReason["Get Report Reason"]
        BuildReport["Build Report Payload"]
    end

    subgraph Headless["CheckStep Headless API"]
        SubmitReport["POST /reports"]
        ModReview["Moderator Review"]
        Decision["Decision Made"]
    end

    subgraph Outcome["Outcome"]
        Webhook["Webhook Callback"]
        Action["Execute Action"]
        NotifyReporter["Notify Reporter"]
        NotifyAuthor["Notify Author"]
    end

    Start --> BBReport
    BBReport --> BBFlag
    BBFlag --> CaptureHook
    
    CaptureHook --> GetReporter
    CaptureHook --> GetContent
    CaptureHook --> GetReason
    
    GetReporter --> BuildReport
    GetContent --> BuildReport
    GetReason --> BuildReport
    
    BuildReport --> SubmitReport
    SubmitReport --> ModReview
    ModReview --> Decision
    
    Decision --> Webhook
    Webhook --> Action
    Action --> NotifyReporter
    Action --> NotifyAuthor
        </div>
    </div>

    <div id="webhook" class="tab-content">
        <h2>Webhook Decision Flow</h2>
        <p>How moderation decisions from CheckStep are received and processed.</p>
        <div class="mermaid">
flowchart TD
    Incoming([Webhook Received])
    
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
        ActionType{Action?}
        DeleteContent["Delete/Hide Content"]
        AddWarning["Add Content Warning"]
        ApproveContent["Mark Approved"]
        BanUser["Ban/Suspend User"]
        NotifyUser["Send BuddyBoss Message"]
    end

    Incoming --> VerifySig
    VerifySig --> ValidSig
    ValidSig -->|No| Reject
    ValidSig -->|Yes| ParsePayload
    
    ParsePayload --> EventType
    EventType -->|decision_made| DecisionMade
    EventType -->|incident_escalated| Escalated
    EventType -->|incident_closed| Closed

    DecisionMade --> ActionType
    
    ActionType -->|delete| DeleteContent
    ActionType -->|warn| AddWarning
    ActionType -->|approve| ApproveContent
    ActionType -->|ban| BanUser

    DeleteContent --> NotifyUser
    AddWarning --> NotifyUser
    BanUser --> NotifyUser
    ApproveContent --> End([Complete])
    NotifyUser --> End
        </div>
    </div>

    <div id="queue" class="tab-content">
        <h2>Queue Processing Flow</h2>
        <p>Background queue processing via WP-Cron.</p>
        <div class="mermaid">
flowchart TD
    Cron([WP-Cron Trigger])
    
    subgraph QueueProcess["Queue Processing"]
        GetItems["Get Pending Items<br>(Limit: 10 per batch)"]
        HasItems{Items?}
        NoItems["Exit - Nothing to Process"]
        
        Loop["For Each Item"]
        GetContent["Get Content Data"]
        
        ContentValid{Valid?}
        MarkFailed["Mark as Failed"]
        
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
        </div>
    </div>

    <div id="sequence" class="tab-content">
        <h2>Complete System Sequence (with Appeals)</h2>
        <p>Full sequence diagram showing content ingestion, community reports, decisions, and appeals.</p>
        <div class="mermaid">
sequenceDiagram
    participant User as User/Author
    participant Reporter as Reporter
    participant WP as WordPress/BuddyBoss
    participant Plugin as CheckStep Plugin
    participant Queue as Content Queue
    participant API as CheckStep API
    participant Mod as CheckStep Moderation
    participant Webhook as Webhook Handler

    Note over User,Webhook: Content Creation Flow
    User->>WP: Creates Content
    WP->>Plugin: Triggers Hook
    Plugin->>Queue: Add to Queue (async)
    Queue->>Plugin: WP-Cron Process
    Plugin->>API: POST /content
    API-->>Plugin: Response
    
    Note over API,Mod: Content Scanning
    API->>Mod: AI/Keyword Analysis

    Note over Reporter,API: Community Reports
    Reporter->>WP: Report Content
    WP->>Plugin: bp_moderation_add
    Plugin->>API: POST /reports

    Note over Mod,User: Decision & Actions
    Mod->>Webhook: POST Decision
    Webhook->>Plugin: Receive Decision
    Plugin->>WP: Execute Action
    Plugin->>User: Notification + Appeal Link

    Note over User,Mod: Appeal Process
    User->>API: Submit Appeal
    Mod->>Mod: Human Review
    
    alt Appeal Approved
        Mod->>Webhook: Appeal Approved
        Plugin->>WP: Restore Content
        Plugin->>User: Content Restored
    else Appeal Denied
        Mod->>Webhook: Appeal Denied
        Plugin->>User: Appeal Denied
    end
        </div>
    </div>

</div>

<style>
.checkstep-process-flow {
    max-width: 1200px;
}

.checkstep-process-flow .nav-tab-wrapper {
    margin-bottom: 20px;
}

.checkstep-process-flow .tab-content {
    display: none;
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-top: none;
}

.checkstep-process-flow .tab-content.active {
    display: block;
}

.checkstep-process-flow .mermaid {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 4px;
    overflow-x: auto;
}

.checkstep-process-flow h2 {
    margin-top: 0;
    color: #1d2327;
}

.checkstep-process-flow p {
    color: #50575e;
    margin-bottom: 20px;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    mermaid.initialize({
        startOnLoad: true,
        theme: 'default',
        securityLevel: 'loose',
        flowchart: {
            useMaxWidth: true,
            htmlLabels: true,
            curve: 'basis'
        }
    });

    var tabs = document.querySelectorAll('.checkstep-process-flow .nav-tab');
    var contents = document.querySelectorAll('.checkstep-process-flow .tab-content');

    tabs.forEach(function(tab) {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            tabs.forEach(function(t) { t.classList.remove('nav-tab-active'); });
            contents.forEach(function(c) { c.classList.remove('active'); });
            
            this.classList.add('nav-tab-active');
            var tabId = this.getAttribute('data-tab');
            document.getElementById(tabId).classList.add('active');
            
            setTimeout(function() {
                mermaid.init(undefined, document.querySelectorAll('#' + tabId + ' .mermaid'));
            }, 100);
        });
    });
});
</script>
