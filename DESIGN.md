# System Design

## Database Schema (Supabase)
| Table | Key Columns | Relationships / Notes |
|---|---|---|
| `fuelguard_users` | `user_id`, `first_name`, `email`, `password_hash`, `is_active` | Fleet managers and admins. |
| `drivers` | `driver_id`, `full_name`, `email`, `password_hash`, `license_number`, `status` | Driver accounts for driver dashboard. |
| `trucks` | `truck_id`, `license_plate`, `driver_name`, `fuel_capacity`, `tank_height` | Vehicle registry. |
| `sensors` | `sensor_id`, `sensor_type`, `truck_id`, `is_active` | FK to `trucks.truck_id`. |
| `fuel_telemetry` | `device_id`, `truck_id`, `fuel_percentage`, `fuel_volume_liters`, `timestamp`, `latitude`, `longitude`, `speed_kmh` | The core time-series data table. |
| `alerts` | `alert_id`, `device_id`, `truck_id`, `alert_type`, `message`, `status`, `is_resolved` | Triggered anomalies and threshold breaches. |

## API Endpoint Reference

### IoT Gateway (FastAPI)
| Method | Path | Auth | Request Body | Response |
|---|---|---|---|---|
| POST | `/api/logs` | None ⚠️ | `TelemetryData` JSON | `FuelLevelResponse` |
| GET | `/api/fleet/status` | None ⚠️ | - | Fleet summary JSON |

### Backend API (PHP)
| Method | Path | Auth | Request Body | Response |
|---|---|---|---|---|
| POST | `/api/login.php` | None | `{email, password}` | User profile & Success boolean |
| POST | `/api/driver-login.php` | None | `{email, password}` | Driver profile & Session token |
| GET | `/api/alerts.php?action=getAll` | None ⚠️ | - | List of alerts |
| GET | `/api/telemetry.php?action=getLatest` | None ⚠️ | - | Recent telemetry records |
| GET | `/api/ai_predict.php?action=predict`| None ⚠️ | - | 🚧 Stubbed Linear Regression & Z-Score data |

## Sensor Data Format
Expected JSON payload from IoT hardware to Gateway:
```json
{
  "device_id": "SENSOR_001",
  "sensor_type": "ultrasonic",
  "raw_value_cm": 45.5,
  "temperature_c": 22.1,
  "truck_id": "TRK001",
  "latitude": -29.8587,
  "longitude": 31.0218,
  "speed_kmh": 65
}
```

## Detection Algorithm
Currently, detection is primarily **threshold-based**:
1. The gateway receives `raw_value_cm` (distance from top of tank).
2. `fuel_level_cm = tank_height - raw_value_cm`
3. If `fuel_percentage` < 15%, it triggers a LOW alert. If < 10%, it triggers a CRITICAL alert.
4. ⚠️ **Missing Logic**: The documentation mentions a KNN classification model to categorize consumption vs. theft when the engine is off. This is not yet implemented in the gateway.

## Frontend Component Tree
Standard HTML/CSS multi-page application with separate files for views:
- **Admin/Manager View**: `index.html`, `dashboard.html`, `trucks.html`, `sensors.html`, `alerts.html`, `ai-analytics.html`
- **Driver View**: `drivers page/driver-dashboard.html`, `drivers page/my-trucks.html`, `drivers page/alerts.html`