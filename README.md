WPU Maintenance Request System

Quick setup notes for deployment and configuration

1) Environment
- Copy `.env.example` to `.env` or set equivalent environment variables in your hosting environment.
- Important vars: `EMAIL_ENABLED`, `SMTP_ENABLED`, `SMTP_HOST`, `SMTP_USER`, `SMTP_PASS`, `MAIL_FROM_ADDRESS`.

2) Dependencies
- PHP 7.4+ recommended.
- Composer dependencies installed (`vendor/`) — see `composer.json` for `phpmailer/phpmailer`.

3) Enabling email (PHPMailer+SMTP)
- Set `EMAIL_ENABLED=true` and `SMTP_ENABLED=true` and populate SMTP credentials.
- Ensure outgoing SMTP access from the host and correct TLS/SSL port.

4) CSRF Protection
- Static HTML forms include `csrf.js` which fetches a CSRF token from `/csrf_token.php` and injects it into POST forms.
- All major POST handlers verify the token server-side.

5) Reports
- Reports can be generated via `generate_report.php` and exported as CSV by submitting `export=csv` with the POST.

6) Security notes
- Use HTTPS in production.
- Use environment variables for secrets; do not commit credentials.
- Consider integrating additional hardening (CSP header, rate-limiting, input validation libraries).

7) Testing email
- Use the admin test snippet in `docs/test-email.php` (create locally) or call `sendNotification()` with type `Email`.

If you want, I can now:
- Add an admin test page for sending emails,
- Add CSP header and HSTS,
- Or implement server-side rate limiting for auth endpoints.
