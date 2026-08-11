# WPU Maintenance Request System - SRS Implementation Gap Analysis

## Overview
This report compares the current project implementation against the requirements in the SRS document, WPU_Maintenance_Request_System_SRS.docx.

The project already has a solid foundation for a PHP + MySQL/XAMPP web application, but it is still best described as a functional prototype rather than a complete production-ready system.

## Current Tech Stack Observed
- Frontend: HTML, CSS, JavaScript
- Backend: PHP
- Database: MySQL-compatible schema in SQL
- Hosting approach: local XAMPP-style deployment

## Status Summary
- Completed: Core structure, authentication flow, request lifecycle basics, database schema, and notification/audit plumbing
- Still pending: full role-based dashboards, real data-driven UI, reporting polish, email delivery, and several non-functional/security requirements

## Completed Features

### 1. User Management
- Registration page and registration backend exist
- Login flow exists with password verification
- Password reset flow exists
- Basic password hashing is implemented
- User account creation writes to the database and audit logs

### 2. Maintenance Request Submission
- Student-facing request submission form exists
- Backend inserts a maintenance request into the database
- Request fields include title, description, building, room, category, and priority
- Request submission triggers a notification entry

### 3. Request Tracking / Lifecycle
- Request status is stored in the database
- Status update backend exists for technicians/supervisors
- Basic request status change feedback is implemented

### 4. Assignment Management
- Assignment backend exists for assigning a request to a technician
- Supervisor assignment action updates the request status to Assigned
- Assignment notifications are written to the notification table

### 5. Technician Features
- Technician upload form exists for evidence photos and comments
- Backend stores attachments and work comments
- Technician status update flow exists

### 6. Notifications and Audit Trails
- Notification insertion logic exists through a shared PHP helper
- Audit logging helper exists and is called by main workflows
- Database tables for notifications and audit logs are defined

### 7. Database Foundation
- Core schema for users, roles, buildings, rooms, categories, maintenance requests, assignments, status history, attachments, work comments, notifications, and audit logs is present
- Initial SQL structure is in place

## Still Pending / Not Fully Implemented

### 1. Role-Based Access Control is Incomplete
- The system stores a role ID, but login currently redirects to a fixed student dashboard
- There is no robust enforcement of role-specific access per user role
- Staff, academic, supervisor, technician, and admin dashboards are not fully wired to real user permissions

### 2. Dashboards Are Still Mostly Static Templates
- Dashboard pages exist, but they appear to be mostly UI mockups with example rows
- The pages do not yet show real records pulled dynamically from the database
- There is no true “My Requests”, “Assigned Jobs”, or “Manage Users” data integration yet

### 3. Request History / Status History is Not Fully Implemented
- The schema includes request_status_history, but the current workflows do not clearly record every status transition into that table
- The SRS expects a full audit trail of status changes, which is not fully enforced in the current code

### 4. Reassignment Workflow is Missing
- The SRS requires supervisors to reassign requests to another technician
- The current code supports assignment creation, but not a complete reassignment history flow

### 5. Reporting is Only a Basic Prototype
- A report generation script exists, but it is minimal
- The SRS expects reporting by month, building, category, technician, and status, plus dashboard analytics
- There is no polished reporting UI, charting, or aggregated analytics view yet

### 6. Real Email Notifications Are Not Implemented
- The project stores notification records, but it does not appear to send actual emails through SMTP
- The requirement calls for email notifications, not just database notifications

### 7. Security Requirements Are Only Partially Covered
- Password hashing exists
- Audit logging exists
- Role-based access control is incomplete
- HTTPS, secure deployment, and hardening are not implemented in the current local prototype

### 8. File Upload Handling Needs Improvement
- The upload workflow exists, but the implementation uses a hardcoded path and does not fully validate file type/size or ensure the target directory is available
- Initial request attachment support from the submission form is not fully wired

### 9. Database Refinement Still Needed
- The schema exists, but the SRS also expects indexes, reporting views, and stronger integrity rules
- The current implementation does not yet include the recommended reporting views and performance indexes

### 10. UI Routing and Path Consistency Need Cleanup
- Some pages reference paths such as ../backend and ../assets/css/style.css that do not match the current project layout
- This creates broken links and makes the app inconsistent across pages

## Recommended Next Implementation Priorities
1. Implement real role-based login redirect and access checks
2. Replace static dashboard content with database-driven listings
3. Add full request status history persistence for every transition
4. Add a complete supervisor assignment/reassignment workflow
5. Add real email sending for notifications
6. Build reporting screens and analytics summaries
7. Harden security and upload handling
8. Add indexes and reporting views to align with the SRS

## Bottom Line
The project has already implemented the core backend skeleton for a maintenance request system, but it still needs significant work to become a complete, user-ready, and SRS-compliant solution.

If the goal is to reach a working MVP, the highest-value next steps are:
- dynamic dashboards,
- proper role-based access,
- real reporting,
- and email delivery.
