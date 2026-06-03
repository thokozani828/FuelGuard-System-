# System Specification

## Purpose and Problem Statement
The South African logistics industry faces significant financial pressure due to "fuel shrinkage" (unauthorized removal of diesel from trucks). Existing fuel management relies on periodic manual reconciliations, lacking real-time visibility. FuelGuard implements an automated, IoT-driven system to detect anomalies proactively, improving profit margins and accountability.

## Functional Requirements
- **US1**: Authenticate users via secure login for both fleet managers and drivers.
- **US2**: Register vehicles with tank dimensions for accurate volume calculations.
- **US3**: Ingest real-time telemetry from IoT sensors (fuel level, engine status, location).
- **US4**: Flag "Suspected Theft" or anomalies if fuel drops rapidly while the engine is off. (⚠️ Partially Implemented: Basic threshold alerts exist, but engine-off logic/AI anomaly detection is stubbed).
- **US5**: Assign drivers to vehicles to maintain accountability.
- **US6**: Generate and manage alerts for critical fuel levels or drops.
- **US7**: View fuel loss trends over time via analytics dashboards.
- **US8**: 🚧 Export fuel logs and alerts to CSV formats (Telemetry export is implemented; Alert export is partially implemented).
- **US9**: 🚧 Predictive Analytics for fuel consumption and maintenance (Currently stubbed in `api/ai_predict.php`).

## Non-Functional Requirements
- **Availability**: The IoT Gateway must handle continuous telemetry ingestion. ⚠️ Currently running as a single Uvicorn instance; high availability not explicitly configured.
- **Security**: Data transmission must be encrypted via TLS (handled by Supabase HTTPS endpoints).
- **Performance**: AI classification/alerting results must be delivered within 2 seconds of log reception.

## Supported User Roles
1. **Fleet Managers**: Monitor operations, investigate theft, review driver performance.
2. **Fleet Owners**: Overview reporting and analytics for business sustainability.
3. **Drivers**: Monitor their own vehicle's fuel integrity and receive notifications.
4. **IoT Technicians**: Install and calibrate hardware.

## System Boundaries
**In Scope**:
- Ingestion of fuel sensor data.
- Basic alerting based on volume thresholds.
- Dashboards for managers and drivers.
- Mock/Stub ML predictive analytics.

**Out of Scope**:
- Physical hardware design and firmware implementation (simulated via Python).
- Direct GPS hardware integration (mocked in telemetry payload).

## Integration Points
- **IoT Protocol**: HTTP POST (REST API) to `/api/logs`.
- **Database**: Supabase REST API (`/rest/v1/*`).
- **External APIs**: None currently actively used (Supabase is the BaaS).