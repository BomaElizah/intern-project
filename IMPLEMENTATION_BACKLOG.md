# Maintenance Request System - Implementation Backlog

This backlog turns the gap analysis into a practical development plan for completing the project in a phased, buildable sequence.

## Recommended Delivery Order

The work should be implemented in this order so each phase builds on the previous one:

1. Foundation: role-based access and protected pages
2. Core user experience: dynamic dashboards and request views
3. Request lifecycle: status history and reassignment workflow
4. Communication: email notifications
5. Reporting and analytics
6. Reliability and security hardening
7. Database optimization and UI cleanup

---

## Phase 1 - Core Access Control (Highest Priority)

### Task 1.1: Enforce role-based login redirection
- Update login logic so each user is redirected to the correct dashboard based on role.
- Ensure roles such as student, supervisor, technician, and admin are handled properly.
- Effort: 1 day

### Task 1.2: Protect pages with role-based access checks
- Add server-side authorization checks before loading dashboard and management pages.
- Prevent users from reaching pages they are not allowed to view.
- Effort: 1-2 days

### Task 1.3: Add role-aware navigation and menus
- Show only the menu options relevant to the logged-in role.
- Hide restricted actions for regular users.
- Effort: 0.5 day

---

## Phase 2 - Dynamic Dashboards and Request Views (Highest Priority)

### Task 2.1: Replace static dashboard content with database-driven data
- Fetch requests, assignments, and user-related records from the database.
- Remove placeholder/example rows from the dashboards.
- Effort: 2-3 days

### Task 2.2: Build “My Requests” and “Assigned Jobs” views
- Show each student’s own requests.
- Show technician assignments and current workload.
- Effort: 1-2 days

### Task 2.3: Build supervisor/admin management views
- Add request queues, assignment lists, and user management panels.
- Effort: 2 days

### Task 2.4: Add dashboard summary metrics
- Display counts such as open requests, pending requests, assigned jobs, and completed jobs.
- Effort: 1 day

---

## Phase 3 - Request Lifecycle Completeness

### Task 3.1: Record every status transition in the status history table
- Ensure each request status change is logged consistently.
- Include actor, previous status, new status, and timestamp.
- Effort: 1-2 days

### Task 3.2: Build a visible request history timeline
- Show a chronological history view for each request.
- Make status changes easy to review for users and staff.
- Effort: 1 day

### Task 3.3: Implement complete reassignment workflow
- Allow supervisors to reassign a request to another technician.
- Store reassignment history and maintain audit details.
- Effort: 2 days

---

## Phase 4 - Notifications and Email Delivery

### Task 4.1: Add real email sending support
- Configure SMTP or a mail service provider.
- Send notifications for request submission, assignment, status updates, and reassignment.
- Effort: 2 days

### Task 4.2: Create email templates
- Add readable email messages for different events.
- Include request details, status, and action links where applicable.
- Effort: 1 day

### Task 4.3: Link notifications to both database records and email delivery
- Ensure the same event triggers both an internal notification and an external email.
- Effort: 0.5 day

---

## Phase 5 - Reporting and Analytics

### Task 5.1: Build report generation screens
- Add reports by month, building, category, technician, and status.
- Effort: 2-3 days

### Task 5.2: Add basic charts and summary analytics
- Show charts for request volume, response times, completion rates, and category distribution.
- Effort: 1-2 days

### Task 5.3: Add export support
- Allow exporting reports as CSV or PDF.
- Effort: 1 day

---

## Phase 6 - File Upload and Evidence Handling

### Task 6.1: Improve upload validation
- Validate allowed file types and reasonable file size limits.
- Reject unsafe or overly large uploads.
- Effort: 1 day

### Task 6.2: Make upload storage reliable
- Create the target upload directory if it is missing.
- Use safe file naming and avoid hardcoded paths.
- Effort: 1 day

### Task 6.3: Display uploaded evidence clearly
- Show evidence files and comments in the request detail view.
- Effort: 1 day

---

## Phase 7 - Security Hardening

### Task 7.1: Strengthen input validation and output escaping
- Sanitize incoming form values and escape data displayed in the browser.
- Effort: 1 day

### Task 7.2: Add CSRF protection for state-changing actions
- Protect forms such as login, registration, request submission, status update, and assignment actions.
- Effort: 1 day

### Task 7.3: Prepare for secure deployment
- Document HTTPS requirements and secure hosting practices.
- Effort: 0.5 day

---

## Phase 8 - Database and UI Refinement

### Task 8.1: Add indexes and reporting-friendly database structures
- Improve performance for common queries such as request lookup and dashboard summaries.
- Effort: 1 day

### Task 8.2: Add reporting views or summary tables if needed
- Support faster analytics without overloading application queries.
- Effort: 1 day

### Task 8.3: Fix layout and path consistency issues
- Remove broken links and align asset paths across pages.
- Effort: 1 day

---

## Suggested MVP Scope

If the goal is to deliver a working MVP quickly, focus first on these items:

1. Role-based login and access control
2. Database-driven dashboards
3. Status history tracking
4. Reassignment workflow
5. Email notifications

These items provide the greatest improvement in real usability for the system.

---

## Rough Total Effort Estimate

- Minimum realistic implementation time: 14-18 working days
- Recommended comfortable delivery window: 3-4 weeks

This estimate assumes incremental implementation with testing and small fixes after each phase.
