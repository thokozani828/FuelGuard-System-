# Feature Completion Plan

## Unfinished & Missing Features
These features are stubbed, partially implemented, or missing entirely based on the initial system specification and codebase audit.

### Critical Priority
1. **API Authentication (IoT Gateway)**
   - **Why it's needed**: Currently, anyone can POST to `/api/logs` and spoof fuel data.
   - **Affected files**: `gateway/api.py`, `simulator/sensor_simulator.py`.
   - **Approach**: Implement an API key header (`X-API-Key`) in FastAPI and require it for all ingestion requests.
   - **Complexity**: S

2. **Session Validation on PHP Endpoints**
   - **Why it's needed**: Telemetry and alerts are exposed publicly to anyone who hits the `.php` files.
   - **Affected files**: `api/telemetry.php`, `api/alerts.php`, `api/dashboard.php`.
   - **Approach**: Require a Bearer token or PHP session cookie to validate that the user is authenticated before returning Supabase data.
   - **Complexity**: M

3. **Secrets Management in PHP**
   - **Why it's needed**: Supabase URL and Keys are hardcoded in multiple PHP files.
   - **Affected files**: `db.php`, `api/alerts.php`, `api/telemetry.php`, `api/driver-login.php`, `login.php`.
   - **Approach**: Use a `.env` loader library for PHP (like `vlucas/phpdotenv`) and read credentials dynamically.
   - **Complexity**: S

### High Priority
4. **Engine-Off Siphoning Detection (KNN Model)**
   - **Why it's needed**: The spec mentions using a KNN model to detect theft when the engine is off, but currently it's just a simple `< 15%` threshold in the gateway.
   - **Affected files**: `gateway/api.py`, `gateway/fuel_engine.py`.
   - **Approach**: Implement a scikit-learn KNN model in the Python gateway that triggers alerts if fuel drops significantly while the `speed_kmh` is 0.
   - **Complexity**: L

5. **Secure Password Hashing**
   - **Why it's needed**: Raw SHA-256 is currently used for drivers, which is insecure against rainbow tables.
   - **Affected files**: `api/driver-login.php`, `api/change-password.php`.
   - **Approach**: Migrate to `password_hash()` and `password_verify()` with bcrypt.
   - **Complexity**: S

### Nice-to-Have Priority
6. **Real AI Predictive Analytics**
   - **Why it's needed**: `api/ai_predict.php` currently uses hardcoded mock arrays and basic math.
   - **Affected files**: `api/ai_predict.php`, `api/ai_train.php`.
   - **Approach**: Move the prediction logic to the Python gateway using actual historical data from Supabase, and have the PHP endpoint proxy the request to Python.
   - **Complexity**: L

7. **Alert Export to CSV**
   - **Why it's needed**: Required by US8, partially implemented but needs polish to match the telemetry export.
   - **Affected files**: `api/alerts.php`.
   - **Approach**: Standardize the CSV export headers and ensure filters apply to the export query.
   - **Complexity**: S

## Recommended New Features

1. **Geofencing & Safe Zones**
   - Allow fleet managers to define safe refueling zones. If fuel increases outside these zones, or drops inside them, flag it for investigation.
2. **Push Notifications / Webhooks**
   - Implement push notifications (e.g., Firebase Cloud Messaging) or SMS integrations for Critical alerts so managers don't have to watch the dashboard actively.
3. **Maintenance Tracking Module**
   - A dedicated UI and schema to log truck maintenance, linking predictive maintenance alerts to actual work orders.
4. **Driver Performance Scoring**
   - Aggregate harsh braking, speeding (from `speed_kmh`), and fuel efficiency into a driver leaderboard to gamify fuel savings.