Phase 6 - Manual Test Instructions

This file describes quick manual checks for upload and evidence handling.

Prerequisites
- PHP + webserver serving project root (e.g., built-in PHP server: `php -S localhost:8000` run from project root)
- Database configured and `db_connect.php` pointing to a running MySQL instance with schema applied

1) Quick smoke test for `submit_request.php` (single/multi attachments)
- Open `test_upload.html` in a browser (served via PHP server) or POST using curl.
- Fill fields and attach files (images or PDF). Submit.
- Expected: request created, attachments saved in `assets/uploads/`, and DB `attachments` rows inserted with `attachment_stage='Request'`.

Curl example (single file):
```
curl -F "title=Test" -F "description=desc" -F "building=1" -F "room=1" -F "category=1" -F "priority=Low" -F "attachment=@/path/to/photo.jpg" http://localhost:8000/submit_request.php
```

Curl example (multiple files):
```
curl -F "title=Test" -F "description=desc" -F "building=1" -F "room=1" -F "category=1" -F "priority=Low" -F "attachments[]=@/path/to/photo1.jpg" -F "attachments[]=@/path/to/photo2.png" http://localhost:8000/submit_request.php
```

2) Upload evidence as technician via `upload_evidence.php`
- Use `dashboard_technician.html` or a direct curl POST to upload `before_photo` and/or `after_photo` with `request_id` and `comment`.
- Expected: files stored in `assets/uploads/`, `attachments` rows with stages `Before-Work`/`After-Work`, and work comment recorded.

Curl example:
```
curl -F "request_id=123" -F "before_photo=@/path/before.jpg" -F "after_photo=@/path/after.jpg" -F "comment=Fixed it" http://localhost:8000/upload_evidence.php
```

3) View evidence
- Open `view_request.php?request_id=123` in browser. Images should preview; PDFs download. Comments should appear.

4) Edge cases
- Upload a >5MB file -> should be rejected by server (no file stored).
- Upload disallowed extension (.exe) -> rejected.

Location of uploads: `assets/uploads/` (created automatically).
