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

## Test credentials (not displayed on the login page)
- superadmin / password
- admin / password
- dataentry / password

## Local AI
Ollama + qwen2.5:3b. See LOCAL_AI_SETUP_SINHALA.md.

## Important upgrade fix
This version repairs passenger_profiles columns when an older RPTA database already exists, preventing the mysqli `bind_param()` fatal error seen during upgrade.
