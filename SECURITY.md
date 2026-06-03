# Security Profile

## Authentication and Authorisation Model
- **Fleet Managers**: Authenticate via `login.php` using SHA256 hashed passwords stored in `fuelguard_users`.
- **Drivers**: Authenticate via `driver-login.php` using SHA256 hashed passwords in `drivers`.
- **Authorisation**: ⚠️ Currently, there is no active token validation or session enforcement on the PHP data endpoints (`alerts.php`, `telemetry.php`). If a user knows the URL, they can access the data.

## Known Attack Surfaces
1. **Sensor Spoofing**: The `/api/logs` gateway endpoint accepts POST requests without any API key or HMAC signature. An attacker could flood the system with fake fuel readings to mask a theft.
2. **Unauthenticated Data Access**: Backend PHP scripts do not verify the session token before returning sensitive fleet telemetry and alerts.
3. **Password Hashing**: Passwords use basic `SHA256` hashing without salt (`hash('sha256', $password)`), which is vulnerable to rainbow table attacks.

## Current Security Controls
- **Data in Transit**: Communication to Supabase is secured over HTTPS. Supabase requires an `apikey` and `Bearer` token (though these are hardcoded in the PHP and Python source files).
- **CORS**: Implemented on API endpoints to prevent cross-origin abuse, but currently set to `Allow-Origin: *` which is overly permissive.

## Missing Security Controls (Gaps)
- ⚠️ **Missing API Authentication**: IoT devices must authenticate to the Gateway (e.g., using JWTs, mutual TLS, or static API keys).
- ⚠️ **Missing Endpoint Authorisation**: PHP endpoints must validate the session token provided at login before returning data.
- ⚠️ **Hardcoded Secrets**: The Supabase URL and Key are hardcoded in multiple PHP files (`db.php`, `api/alerts.php`, `api/telemetry.php`).
- ⚠️ **Weak Hashing**: `password_hash()` (bcrypt) should be used natively instead of raw SHA256.

## Data Sensitivity Classification
- **High Sensitivity**: Driver Identity (PII), Location Data (GPS coordinates), Password Hashes.
- **Medium Sensitivity**: Fuel Volumes, Alerts.
- **Low Sensitivity**: Tank configuration sizes, Sensor IDs.

## Recommended Hardening Steps (Prioritised)
1. **Extract Secrets**: Move Supabase credentials out of PHP files into environment variables (`.env`).
2. **Secure the Gateway**: Implement an API key requirement on `/api/logs` so only authorized sensors can transmit data.
3. **Session Validation**: Update PHP API endpoints to require and validate a JWT or session token.
4. **Upgrade Hashing**: Migrate from raw SHA256 to PHP's built-in `password_hash()` for driver accounts.