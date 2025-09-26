# Event Approval System

A WordPress system for managing event submissions across multiple calendars with approval workflows.

## Table of Contents

### Site Administrator Guide
- [Quick Setup](#quick-setup)
- [User Management](#user-management)
- [The Workflow](#the-workflow)
- [Troubleshooting](#troubleshooting)
- [Best Practices](#best-practices)

### Technical Documentation
- [Technical Setup](#technical-setup)

---

## Site Administrator Guide

### Quick Setup

1. **Create calendars** in Events → Event Departments taxonomy
2. **Assign user roles**: Event Submitter or Event Approver
3. **Set calendar permissions** in user profiles under "Event Calendar Permissions"

### User Management

#### User Roles
- **Event Submitter**: Can create events, restricted admin interface
- **Event Approver**: Can create and approve events, restricted admin interface
- **Administrator/Editor**: Full access to all events and calendars

#### Setting Permissions
Edit any user → scroll to "Event Calendar Permissions":
- **Can Submit**: User can create events for this calendar
- **Can Approve**: User can approve events for this calendar

**Example Permissions:**
- **Department Admin Assistant**: Can Submit + Can Approve for "Horticulture Department", Can Submit only for "CAES Main Calendar"
- **Faculty Member**: Can Submit for "Animal Science Department", needs approval
- **CAES Communications**: Can Approve for "CAES Main Calendar", Can Submit for department calendars

**Monitor permissions** at Events → Calendar Management to see who has access to what.

### The Workflow

```
Create Event → Select Calendars → Submit for Review → Email to Approvers → Approve → Published
```

**Key Rules:**
- Events need approval for at least one selected calendar to go live
- Users with approval permissions can publish immediately
- Admins can override everything

**For Event Submitters:**
1. Events → Add New → Fill details → **Must select calendars** → Submit for Review
2. Receive approval notification via email

**For Event Approvers:**
1. Receive email notification → Click link to review
2. Use "Approve for [Calendar Name]" buttons in sidebar
3. Event auto-publishes when approved

**Calendar Selection:**
- Users only see calendars they have submit permissions for
- Non-authors cannot modify calendar selections (approval-only view)
- System validates permissions on save

### Troubleshooting

**Events stuck pending**: Check if calendar has assigned approvers, verify email notifications work
**Users can't submit**: Confirm user role and calendar permissions match their needs
**Calendar field issues**: Ensure ACF `caes_department` field exists and user has submit permissions
**Permission confusion**: Remember roles control interface, permissions control actual access

### Best Practices

**Organizational:**
- Assign clear calendar ownership (department vs college-wide)
- Set backup approvers for critical calendars
- Review permissions each semester
- Document calendar hierarchy

**Technical:**
- Keep calendar names simple
- Don't delete calendars with historical events
- Test workflow after WordPress updates
- Use Events → Expiration Tool to manage old events

---

## Technical Setup

### Installation

1. **Include main file** in `functions.php`:
```php
require_once get_template_directory() . '/inc/events/events-main.php';
require_once get_template_directory() . '/inc/events-support.php'; // If using ACF integration
```

2. **Required Dependencies:**
   - `events` custom post type
   - `event_caes_departments` taxonomy
   - ACF with `caes_department` field (for calendar selection)
   - jQuery in admin

3. **Email Notifications:**
   Email notifications are enabled by default. If emails aren't working:
   - Test WordPress email functionality with a plugin like WP Mail SMTP
   - Check server email configuration
   - Verify emails aren't going to spam folders

### File Structure
```
/inc/events/
├── events-main.php          # Main system loader
├── events-roles.php         # User roles and capabilities  
├── events-approval-workflow.php  # Core approval logic
├── events-approval-metabox.php   # Admin interface and AJAX
├── events-approval-admin.php     # Calendar management page
├── events-frontend-queries.php   # Public query helpers
├── events-approver-assignment.php # Helper functions
└── event-approval.js       # Frontend JavaScript

/inc/
└── events-support.php       # ACF integration and permissions
```

### Key Features

**Permission System:**
- User meta: `calendar_submit_permissions`, `calendar_approve_permissions`
- Automatic ACF field filtering based on user permissions
- Prevents calendar modification by non-authors

**Event Statuses:**
- `draft` → `pending` → `publish` → `expired` (automated daily)
- Custom "expired" status for past events

**Security:**
- Nonce-protected AJAX requests
- Multi-layer permission checks
- Input validation and sanitization

**Database Schema:**
- Post meta: `_calendar_approval_status`, `_submitted_for_approval`
- User meta: Permission arrays by calendar term ID

### Admin Tools

**Calendar Management**: Events → Calendar Management (permission overview)
**Expiration Tool**: Events → Expiration Tool (manually expire old events)
**Debug**: Add `?debug_user_permissions=USER_ID` to Users page (admin only)

### Frontend Integration

```php
// Get approved events for specific calendar
$events = get_approved_events_for_calendar($calendar_term_id);

// Check if event approved for calendar  
if (is_event_approved_for_calendar($post_id, $calendar_term_id)) {
    // Display event
}
```

### Cron Jobs
- **Daily**: Automatically moves past events to "expired" status
- **Manual**: Use Expiration Tool for immediate processing

### Customization

**Override emails:**
```php
function custom_approval_notification($post_id, $approver_ids) {
    // Custom email template
}
```

**Filter permissions:**
```php
add_filter('acf/load_field/name=caes_department', 'custom_calendar_filter');
```