# FuelGuard System 🚛⛽

FuelGuard is a high-performance, IoT-driven fuel siphoning detection and fleet management platform. It combines real-time sensor data, automated anomaly detection, and premium dashboards to protect logistics fleets from fuel shrinkage.

## 🚀 Core Features
- **Real-Time Monitoring**: Ingests live telemetry from ultrasonic fuel sensors.
- **Theft Detection**: Intelligent threshold and physics-based logic to flag rapid fuel drops.
- **Fleet Management**: Comprehensive registry for trucks, drivers, and IoT hardware.
- **Dual Dashboards**: Specialized interfaces for Fleet Managers and authorized Drivers.
- **Secure by Design**: Implements API key authentication, stateful sessions, and industry-standard password hashing.

## 🛠️ Tech Stack
- **IoT Gateway**: Python (FastAPI, Pydantic)
- **Backend API**: PHP (Serverless-ready architecture)
- **Database**: Supabase (PostgreSQL)
- **Frontend**: HTML5, CSS3 (Vanilla), JavaScript (ES6+)
- **Infrastructure**: Docker, GitHub Actions CI/CD

## 🏁 Quick Start

### 1. Database Setup
Ensure you have a Supabase project. Initialize your database schema:
```bash
python init_db.py
```

### 2. Environment Configuration
Create a `.env` file in the root directory (refer to `.env.example` if available):
```env
SUPABASE_URL=your_supabase_url
SUPABASE_KEY=your_supabase_anon_key
GATEWAY_API_KEY=your_secure_api_key
```

### 3. Run the Services
**Using Docker (Recommended):**
```bash
docker-compose up -d
```

**Manual Start:**
- **IoT Gateway**: `uvicorn gateway.api:app --reload`
- **Frontend**: Serve via any web server (Apache/Nginx/Live Server).

## 🧪 Simulation
To test the siphoning detection without physical hardware, run the multi-truck simulator:
```bash
python -m simulator.sensor_simulator --multi
```

## 🔒 Security
FuelGuard adheres to modern security standards:
- All API endpoints are protected via `X-API-Key` or Session Validation.
- Passwords are encrypted using `Bcrypt`.
- Inputs are strictly validated via Regex and Pydantic models.

---
*Built for precision. Deployed for security.*
