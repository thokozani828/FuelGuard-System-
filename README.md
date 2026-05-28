# FuelGuard-System: Real-Time Fleet Fuel Monitoring

FuelGuard-System is an IoT-driven solution designed to combat "fuel shrinkage" (unauthorized siphoning) in the logistics industry. By utilizing ultrasonic sensors and real-time telemetry, the system provides fleet managers with immediate visibility into fuel levels, consumption patterns, and potential theft events.

## 🚀 Key Features

*   **Real-Time Monitoring:** Continuous telemetry ingestion from IoT sensors (simulated via Python).
*   **Intelligent Analytics:** Calculates fuel volume, percentage, and status based on tank dimensions and sensor data.
*   **Theft Detection:** (Planned/In Progress) Classification of fuel drops to identify suspicious activity.
*   **Fleet Management Dashboard:** A premium web-based interface (Cream & Orange theme) for viewing fleet health, sensor status, and alerts.
*   **Cloud Integration:** Secure data storage and retrieval using Supabase (PostgreSQL).
*   **Multi-Platform Support:** Includes a FastAPI gateway for IoT devices and a PHP/HTML frontend for managers.

## 🛠️ System Architecture

1.  **IoT Devices (Simulated):** Python scripts simulate ultrasonic sensors measuring fuel levels in truck tanks.
2.  **FastAPI Gateway:** A high-performance Python API that ingests telemetry, applies temperature compensation and smoothing, and stores data in the cloud.
3.  **Supabase Cloud Database:** Stores telemetry logs, truck configurations, sensor assignments, and alerts.
4.  **Web Dashboard:** An interactive HTML/JS dashboard providing real-time visualization of fleet status.
5.  **Authentication System:** PHP-based login and registration for secure access to the management portal.

## 📁 Project Structure

```text
FuelGuard-System/
├── gateway/                # FastAPI IoT Gateway
│   ├── api.py              # Main API endpoints
│   ├── fuel_engine.py      # Fuel calculation logic
│   ├── supabase_rest.py    # Cloud database interface
│   └── models.py           # Pydantic data models
├── simulator/              # IoT Fleet Simulator
│   └── sensor_simulator.py # Database-integrated sensor simulator
├── api/                    # Legacy/Additional API endpoints (PHP)
├── config/                 # Configuration files
├── dashboard.html          # Main Fleet Management Dashboard
├── login.php / register.php # User Authentication
├── DOCUMENT.md             # Project requirements and design document
├── docker-compose.yml      # Container orchestration (FastAPI, Redis, InfluxDB)
└── requirements.txt        # Python dependencies
```

## ⚙️ Setup Instructions

### Prerequisites

*   Python 3.10+
*   PHP (for local web server)
*   Supabase Account (for cloud database)
*   Docker (Optional, for running Redis/InfluxDB)

### 1. Environment Configuration

Create a `.env` file in the `gateway/` directory with your Supabase credentials:

```env
SUPABASE_URL=your_supabase_url
SUPABASE_KEY=your_supabase_anon_key
```

### 2. Python Setup

Install the required dependencies:

```bash
pip install -r requirements.txt
```

### 3. Running the System

**Start the IoT Gateway:**
```bash
python -m uvicorn gateway.api:app --reload
```
The API will be available at `http://localhost:8000`.

**Start the Fleet Simulator:**
```bash
python simulator/sensor_simulator.py
```
This script will fetch assigned trucks from Supabase and start sending simulated telemetry.

**Access the Dashboard:**
Open `dashboard.html` in a web browser or host it via a PHP server.

## 📊 Database Schema (Supabase)

The system utilizes several key tables in PostgreSQL:
*   `trucks`: Vehicle information (ID, License Plate, Tank Capacity).
*   `sensors`: IoT sensor metadata.
*   `sensor_assignments`: Links sensors to specific trucks.
*   `fuel_telemetry`: Historical log of all fuel readings.
*   `alerts`: System-generated alerts for low fuel or critical events.

## 🤝 Stakeholders

*   **Fleet Managers:** Monitor daily operations and investigate theft.
*   **Fleet Owners:** Review ROI and operational efficiency.
*   **IoT Technicians:** Manage hardware installation and maintenance.

---

*This project is part of a strategic initiative to improve profit margins and enhance driver accountability in the Durban metro area logistics industry.*
