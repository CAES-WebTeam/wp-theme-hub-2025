# Event Approval System

A WordPress plugin system that provides controlled event submission and approval workflows for multi-calendar event management.

## 📋 For Site Administrators

### Overview
This system allows you to manage events across multiple calendars with different approval workflows. Think of it like a content management system where different people can submit events, but they need approval from designated reviewers before going live.

### How It Works

#### 1. **User Roles**
The system creates two new user roles alongside WordPress's default roles:

- **Event Submitter** - Can create and edit events, but cannot publish them directly
- **Event Approver** - Can review and approve events for specific calendars
- **Administrator/Editor** - Have full access to all events and calendars

#### 2. **The Approval Workflow**

```
📝 User creates event → 📋 Selects calendar(s) → 🔄 Submits for approval → 
👀 Approver reviews → ✅ Approves → 🌟 Event goes live
```

**Step-by-step process:**
1. A user creates a new event
2. They select which calendar(s) the event should appear on
3. They submit the event for approval (it goes to "Pending" status)
4. Email notifications are sent to the appropriate approvers
5. Approvers can review and approve the event for their specific calendars
6. Once all selected calendars are approved, the event automatically goes live
7. The original submitter gets notified that their event was approved

#### 3. **Setting Up Permissions**

**User Permission System:**
- Go to Users → All Users → Edit User
- Scroll down to "Event Calendar Permissions" section
- Check boxes for which calendars the user can:
  - Submit events to
  - Approve events for

### 4. **Managing Calendar Permissions**

Visit **Events → Calendar Management** to see:
- All your calendars
- Who can submit to each calendar
- Who can approve each calendar  
- How many events are in each calendar

### 5. **Daily Workflow**

**For Event Submitters:**
1. Create a new event (Events → Add New)
2. Fill out event details
3. **Important:** Select at least one calendar - you'll see a warning if you forget!
4. Click "Submit for Review" 
5. Wait for email notification that it's approved

**For Event Approvers:**
1. Check your email for new event notifications
2. Click the link to review the event
3. Look for the "Event Approval Status" box on the right side
4. Click "Approve for [Calendar Name]" for calendars you manage
5. The submitter will be automatically notified when approved

**For Administrators:**
1. You can approve any event for any calendar
2. You can publish events immediately if needed
3. Use the Calendar Management page to oversee permissions

### 6. **Common Scenarios**

**Multi-Calendar Events:**
If an event is submitted to 3 different calendars, it needs approval from all 3 calendar approvers before going live.

**No Calendar Approvers:**
If a calendar doesn't have any users with approval permissions assigned, only Administrators and Editors can approve events for that calendar.

**Emergency Publishing:**
Administrators and Editors can always publish events immediately, bypassing the approval process when needed.

---

## 🔧 For Developers

### System Architecture

This system consists of 7 main components that work together to provide a comprehensive event approval workflow:

#### File Structure
```
/inc/events/
├── events-main.php                 # Main handler (include this in functions.php)
├── events-roles.php               # User roles and capabilities
├── events-approver-assignment.php # Helper functions for getting approvers
├── events-approval-workflow.php   # Core submission/approval logic
├── events-approval-metabox.php    # Admin meta box and AJAX handler
├── events-approval-admin.php      # Calendar management admin page
└── event-approval.js             # Frontend JavaScript for approval buttons
```

### Core Components

#### 1. **Custom User Roles** (`events-roles.php`)

**Event Submitter Role:**
```php
'event_submitter' => [
    'edit_events'       => true,
    'publish_events'    => false,  // Key restriction
    'delete_events'     => true,
    'upload_files'      => true
]
```

**Event Approver Role:**
```php
'event_approver' => [
    'edit_events'           => true,
    'edit_others_events'    => true,  // Can edit pending events
    'publish_events'        => true,  // Can approve/publish
    'delete_events'         => true
]
```

**Permission System:**
The system uses a user-based permission system where permissions are stored in user meta fields:
- `calendar_submit_permissions` (array of term IDs user can submit to)
- `calendar_approve_permissions` (array of term IDs user can approve for)

#### 2. **Approval Workflow Logic** (`events-approval-workflow.php`)

**Hook Integration:**
```php
add_action('save_post', 'handle_event_submission_and_approval', 10, 3);
```

**Key Workflow Scenarios:**

**Scenario 1: Non-privileged user tries to publish**
```php
if (!$user_can_publish && $post->post_status === 'publish') {
    // Force to pending status
    // Send notifications to approvers
    // Update submission meta
}
```

**Scenario 2: Status change to pending**
```php
if ($post->post_status === 'pending' && $previous_status !== 'pending') {
    update_post_meta($post_id, '_submitted_for_approval', true);
    send_approval_notification_email($post_id, $approvers);
}
```

**Meta Fields Used:**
- `_calendar_approval_status` - Array of calendar_id => 'approved' status
- `_submitted_for_approval` - Boolean flag
- `_previous_status` - Tracks status changes

#### 3. **AJAX Approval System** (`events-approval-metabox.php` + `event-approval.js`)

**AJAX Handler:**
```php
add_action('wp_ajax_approve_event_calendar', 'handle_ajax_event_approval');
```

**Security Measures:**
- Nonce verification: `wp_verify_nonce($_POST['nonce'], 'event_approval_nonce')`
- Permission checks: User must be assigned approver or admin/editor
- Data validation: Validates post_id, term_id, and user permissions

**Frontend JavaScript:**
- Handles approval button clicks
- Updates UI in real-time
- Shows success/error messages
- Removes approved buttons
- Handles timeout and error scenarios

#### 4. **User Permission System**

**Modern Permission Management:**
Users are granted permissions via their profile:
```php
// Submit permissions
$submit_permissions = get_user_meta($user_id, 'calendar_submit_permissions', true);

// Approve permissions  
$approve_permissions = get_user_meta($user_id, 'calendar_approve_permissions', true);
```

**Permission Checking Functions:**
```php
function user_can_approve_calendar($user_id, $calendar_term_id)
function user_can_submit_to_calendar($user_id, $calendar_term_id)
function get_event_approvers_for_post($post_id)
```

#### 5. **Email Notification System**

**Approval Request Notifications:**
```php
function send_approval_notification_email($post_id, $approver_ids) {
    // Sends to all assigned approvers
    // Includes edit link for quick access
    // Currently logs to error_log (enable wp_mail for production)
}
```

**Approval Confirmation:**
```php
function send_submitter_notification_email($post_id, $submitter_id) {
    // Notifies original author when approved
    // Includes live event permalink
}
```

### Database Schema

**Post Meta:**
- `_calendar_approval_status`: Serialized array `{calendar_id: 'approved'}`
- `_submitted_for_approval`: Boolean flag
- `_previous_status`: String tracking status changes

**User Meta:**
- `calendar_submit_permissions`: Array of calendar term IDs user can submit to
- `calendar_approve_permissions`: Array of calendar term IDs user can approve for

### Key Functions

#### Permission Checking
```php
function user_can_approve_calendar($user_id, $calendar_term_id)
function user_can_submit_to_calendar($user_id, $calendar_term_id)
function get_event_approvers_for_post($post_id)
```

#### Workflow Helpers
```php
function user_can_approve_all_event_calendars($user_id, $post_id)
function handle_event_submission_and_approval($post_id, $post, $update)
```

### Integration Points

**Required Taxonomy:** `event_caes_departments`
**Required Post Type:** `events`
**Required Capabilities:** Custom event capabilities are automatically added to roles

**Dependencies:** None - the system works with core WordPress functionality only

### Installation

1. Include `events-main.php` in your theme's `functions.php`:
```php
require_once get_template_directory() . '/inc/events/events-main.php';
```

2. Ensure you have:
   - `events` custom post type
   - `event_caes_departments` taxonomy
   - jQuery loaded in admin (for approval buttons)

3. Configure email notifications:
   - Currently logs to `error_log` for development
   - Uncomment `wp_mail()` calls for production use

### Customization Hooks

**Email Templates:**
Override email functions to customize notification content:
```php
// Override in your theme
function send_approval_notification_email($post_id, $approver_ids) {
    // Your custom email template
}
```

**Permission Logic:**
Filter the permission checking functions:
```php
add_filter('user_can_approve_calendar', 'your_custom_approval_logic', 10, 2);
```

**UI Customization:**
The meta box and admin pages use standard WordPress styling and can be customized with CSS or by overriding the render functions.

### Security Considerations

1. **Nonce Protection:** All AJAX requests use WordPress nonces
2. **Capability Checks:** Multiple permission layers prevent unauthorized access
3. **Data Sanitization:** All inputs are sanitized and validated
4. **Role Isolation:** Event Submitters cannot publish without approval

### Performance Notes

1. **Database Queries:** Uses efficient meta queries for permission checking
2. **Caching:** Leverages WordPress object cache for user and term lookups  
3. **AJAX Optimization:** Single request per approval with minimal data transfer
4. **Script Loading:** JavaScript only loads on event edit screens

### Troubleshooting

**JavaScript Not Loading:**
- Check file path in `events-approval-metabox.php`
- Verify jQuery is loaded
- Look for console errors

**Permissions Not Working:**
- Verify user has correct role
- Check calendar assignments in user profile
- Confirm taxonomy terms exist

**Email Notifications Not Sending:**
- Uncomment `wp_mail()` calls in notification functions
- Test WordPress email functionality
- Check error logs for SMTP issues