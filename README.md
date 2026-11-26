# RPTA Professional Portal PRO v4 (Fixed)

Southern Provincial Road Passenger Transport Authority passenger transport portal.

## Main flow
1. The home page links to passenger registration and sign-in.
2. Passengers create an account with their own username and a 6-digit password, then sign in to request tokens.
4. Registered passenger details auto-fill in the token form.
5. The passenger token page generates a QR code whose scanned text shows the token reference, passenger name, address, district, service, date and time; it no longer auto-downloads a PDF.
6. Staff roles cannot open the passenger token-creation form; they use their role-specific dashboards and records.

## Token QR setup
Run `composer install` in the project directory after a fresh checkout. QR codes contain the listed token details and omit NIC, phone and email, so they can be read without opening a website.

## Roles
- Passenger: own registration, own tokens, documents, chatbot and feedback.
- Admin: token records, passenger records, feedback and documents.
- Data Entry: operational data entry/edit/delete, passenger records, vehicles, bus stands, services and FAQ knowledge.
- Super Admin: full access.

## Admin and Super Admin account recovery
- Only `admin` and `super_admin` accounts are subject to lockout and email recovery. Five consecutive failed logins cause a two-minute lockout.
- After lockout, the recovery flow collects the registered email first, then opens a separate OTP verification screen before password reset.
- Admin recovery codes are restricted to `amadinethsarani611@gmail.com`; Super Admin recovery codes are restricted to `aktarushika@gmail.com`. Recovery addresses are stored in `admin_account_recovery`, checked against the account role, and cannot be changed through the recovery page.
- Before upgrading, back up the database and apply `sql/admin_account_recovery.sql`. Run `php configure_admin_recovery_emails.php` to apply the fixed addresses to the corresponding Admin and Super Admin accounts in an existing database.
- Configure Gmail SMTP in the Apache/PHP process environment using `RPTA_SMTP_USERNAME` and `RPTA_SMTP_PASSWORD`, then restart Apache. The username must be the Gmail sender address; the password must be a Gmail App Password (spaces are ignored). SMTP uses `smtp.gmail.com`, port `587`, and TLS. `RPTA_SMTP_FROM_NAME` is optional. Do not store or commit the App Password in the source tree.
- The generic recovery notice intentionally does not confirm delivery. For SMTP configuration or delivery failures, inspect the Apache error log; failed sends invalidate the stored OTP and clear its sent timestamp.
- Recovery codes expire after five minutes, allow five verification attempts, and are limited to one send per minute and three email requests per 15-minute window.
- Admin and Super Admin passwords are never documented here. Use the local CLI reset tool only when intentionally rotating them. Passenger and other staff login/recovery behavior is unchanged.

## Test credentials (not displayed on the login page)
- Admin and Super Admin credentials are provisioned locally and are not listed in source documentation.
- dataentry / password

## Local AI
Ollama + qwen2.5:3b. See LOCAL_AI_SETUP_SINHALA.md.

## Important upgrade fix
This version repairs passenger_profiles columns when an older RPTA database already exists, preventing the mysqli `bind_param()` fatal error seen during upgrade.
