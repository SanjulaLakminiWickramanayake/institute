# Nexora Institute Management System

Nexora Institute is an existing public institute website integrated with a PHP/MySQL application and administrator portal. Public pages and branding remain in place; the management system supports student registration, applications, approval, and account management.

## Technology

- HTML5, CSS3, and vanilla JavaScript
- PHP 8.0 or later, PHP sessions, PDO
- MySQL or MariaDB
- Composer and PHPMailer for authenticated Gmail SMTP

## Main features

- Preserved public pages: home, About, Courses, Admissions, and Contact
- Database-driven course and intake availability
- Student signup with six-digit email verification
- Online applications, email confirmation, and administrator review
- Separate student and administrator sign-in, role checks, and dashboards
- Password reset using one-time, expiring links
- Administrator approval/rejection notifications and account setup emails
- Contact message storage and institute email notification
- Admin-only SMTP test page and delivery log
- CSRF-protected forms, hashed passwords and tokens, environment-based credentials

## Project layout

```text
admin/                 Administrator pages and application review
css/, js/, images/     Existing site styles, scripts, and media
database/              schema.sql and automatic, idempotent initializer
includes/               Shared bootstrap, password service, and email service
student/                Student dashboard
*.html                  Public website pages
*.php                   Authentication, application, contact, and reset endpoints
.env                    Local secrets and runtime configuration (not committed)
.env.example            Safe environment template
composer.json           PHP dependencies
```

## Local installation (XAMPP)

1. Install XAMPP with Apache, MySQL/MariaDB, and PHP 8.0+ (including PDO MySQL and OpenSSL).
2. Put this project folder in `C:\xampp\htdocs\institute`.
3. Start **Apache** and **MySQL** from the XAMPP Control Panel.
4. The project creates `.env` from `.env.example` automatically if it is missing. The local XAMPP defaults are:

   ```dotenv
   APP_NAME=Nexora Institute
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://localhost/institute

   DB_HOST=localhost
   DB_DATABASE=nexora_institute
   DB_USERNAME=root
   DB_PASSWORD=

   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=nexorainstitute026@gmail.com
   MAIL_PASSWORD=YOUR_GMAIL_APP_PASSWORD
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=nexorainstitute026@gmail.com
   MAIL_FROM_NAME="Nexora Institute"

   ADMIN_EMAIL=admin@gmail.com
   ADMIN_INITIAL_PASSWORD=Abc@1234
   ```

   The initial administrator password shown here is the required local academic-project credential; change it immediately after first login. Do not put SMTP App Passwords or other email credentials in README/source control. `.env` and Composer's `vendor/` are excluded by `.gitignore`; Apache rules also deny web access to environment/Composer manifests, installed dependencies, and internal PHP directories. Check `APP_URL` if the installation is not at `http://localhost/institute`.
5. Install Composer from [getcomposer.org](https://getcomposer.org/) if it is not already available. From PowerShell in the project directory, run:

   ```powershell
   composer install
   ```

   This installs PHPMailer in `vendor/`; do not manually copy credentials into the dependency.
6. Open `http://localhost/institute/`. On the first PHP request, the application creates the configured database if needed, creates any missing tables and seed data from `database/schema.sql`, applies safe `users.username`/password-hash migrations, and provisions the initial administrator. No manual SQL import or account insert is required. The MySQL account in `.env` must have permission to create the database and tables. Do not open the HTML pages with `file://`; server-side forms require Apache/PHP.

## Gmail SMTP

System mail is sent through the institute account **nexorainstitute026@gmail.com**, using `smtp.gmail.com`, port `587`, and TLS. The application sends signup and application OTPs, verification and submission notices, approval/rejection and account setup messages, password resets, and contact form notifications.

To configure Gmail:

1. Enable 2-Step Verification for the institute Google account.
2. Generate an **App Password** in the account's Google security settings. App Passwords may be unavailable for some account/security configurations.
3. Put the generated value in `MAIL_PASSWORD` in the local `.env` file. Do not share it or place it in README/source files.
4. Sign in as an administrator and use **Test Email Configuration** from the Admin Dashboard. Check the recipient mailbox and **Email Log** if delivery fails.

Google may display App Passwords with spaces for readability; the application removes whitespace when loading `MAIL_PASSWORD`. PHPMailer SMTP is the only delivery path; PHP `mail()` is not used as a success-shaped fallback. In local development (`APP_DEBUG=true`), SMTP delivery failures display a sanitized technical diagnostic in the form message. SMTP credentials are redacted from diagnostics and are never written to logs.

## Automatic database and administrator setup

The `users` table stores administrator and student accounts. Its `password_hash` column contains PHP password hashes only. On the first PHP request the initializer creates the configured database, creates missing tables, safely updates an older `users` table, and seeds this administrator:

- **Username/email:** `admin@gmail.com`
- **Initial password:** `Abc@1234`
- **Role/status:** `admin` / `active`

The password is hashed in PHP before insertion. Initialization is idempotent: existing accounts are reused and their passwords are never reset on later requests. A newly created administrator must change the initial password on first login at `http://localhost/institute/admin-login.php`. If desired, `C:\xampp\php\php.exe database\initialize.php` runs the same initializer explicitly; normally opening the site is sufficient.

## Student application flow

1. A student opens the **Apply Now** link or the online application page.
2. They provide contact and education details and select an available course/intake.
3. The application is stored and the system emails a six-digit OTP.
4. The student verifies the email address; the verified application is queued for admin review.
5. An administrator reviews and approves or rejects the request.
6. The student receives the result by email. For a newly approved account, the email includes a one-time link to set its password.
7. Approved students sign in through **My Account** and access their Student Dashboard.

## Local testing checklist

- **Signup and OTP:** open `sign-up.php`, register using an email mailbox you can access, retrieve the OTP, and verify it.
- **Application and verification:** submit through `apply.php`, verify its email OTP, then check **Registration Requests** in the admin portal.
- **Admin authentication:** opening the project automatically initializes the database and administrator account. Sign in with username or email at `admin-login.php`, change the initial password, and verify a student login cannot access admin pages. Admin accounts are stored in the `users` table; the `username` column is unique, and passwords are stored as PHP password hashes.
- **Approval/rejection:** review a verified request and test each decision; check student email and the Email Log. Newly approved accounts should set a password from the email link.
- **Student sign-in:** sign in at `sign-in.php` and confirm the student dashboard loads.
- **Forgot/reset password:** request a link through `forgot-password.php` and complete it at the emailed `reset-password.php` URL.
- **Contact notification:** submit the public Contact form and verify that the message appears in Admin > Messages and a notification is sent to the institute address.
- **SMTP:** send a test email from Admin > Test Email Configuration.

Real email delivery requires a valid Gmail App Password configured locally in `.env` and successful Composer installation. SMTP credentials are not included in this README.

## Security notes

- Keep `.env` local and private. Use `.env.example` only as a placeholder template.
- Use a unique Gmail App Password. Change the published local-project initial administrator password immediately, before using the system with real student data.
- Prepared PDO statements are used for database operations. All account password writes go through the centralized `hashPassword()` service and store hashes; login uses `verifyPassword()`/`password_verify()`. OTP values and password reset tokens are also stored as hashes.
- Sessions use HTTP-only, SameSite cookies and strict mode; administrative endpoints enforce role authorization and CSRF checks.
- The admin seed utility is CLI-only, and the database/internal include folders are denied over HTTP by Apache rules.
- For any public deployment, set `APP_DEBUG=false`, configure HTTPS and secure cookies, use a least-privilege database account, and keep dependencies updated.
