# 🚛⛽ FuelGuard System

### *IoT-Driven Fuel Monitoring, Theft Detection & Fleet Management Platform*

<p align="center">
  <strong>Real-Time Fuel Intelligence for Modern Logistics Fleets</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Python-3.10%2B-3776AB?style=for-the-badge&logo=python&logoColor=white" alt="Python">
  <img src="https://img.shields.io/badge/FastAPI-0.100%2B-009688?style=for-the-badge&logo=fastapi&logoColor=white" alt="FastAPI">
  <img src="https://img.shields.io/badge/PHP-8%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/PostgreSQL-Supabase-4169E1?style=for-the-badge&logo=postgresql&logoColor=white" alt="PostgreSQL">
  <img src="https://img.shields.io/badge/JavaScript-ES6%2B-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black" alt="JavaScript">
</p>

<p align="center">
  <img src="https://img.shields.io/badge/IoT-Enabled-FF6F00?style=flat-square" alt="IoT">
  <img src="https://img.shields.io/badge/Status-Active-success?style=flat-square" alt="Status">
  <img src="https://img.shields.io/badge/Platform-Web-black?style=flat-square" alt="Platform">
  <img src="https://img.shields.io/badge/Database-Supabase-3ECF8E?style=flat-square" alt="Supabase">
</p>

---

## 📑 Table of Contents

* [✨ About FuelGuard](#-about-fuelguard)
* [🎯 Problem Statement](#-problem-statement)
* [💡 Solution](#-solution)
* [🚀 Core Features](#-core-features)
* [⛽ Real-Time Fuel Monitoring](#-real-time-fuel-monitoring)
* [🚨 Fuel Theft Detection](#-fuel-theft-detection)
* [🚛 Fleet Management](#-fleet-management)
* [👥 User Roles](#-user-roles)
* [📊 Dashboards](#-dashboards)
* [📡 IoT Architecture](#-iot-architecture)
* [🏗️ System Architecture](#️-system-architecture)
* [🔄 Data Flow](#-data-flow)
* [🗄️ Database Architecture](#️-database-architecture)
* [🛠️ Technology Stack](#️-technology-stack)
* [📂 Project Structure](#-project-structure)
* [🚀 Getting Started](#-getting-started)
* [🧪 Sensor Simulation](#-sensor-simulation)
* [🔐 Security](#-security)
* [🐳 Docker](#-docker)
* [⚙️ CI/CD](#️-cicd)
* [📈 Future Improvements](#-future-improvements)
* [🎯 Project Vision](#-project-vision)
* [📄 License](#-license)

---

# ✨ About FuelGuard

**FuelGuard** is an IoT-driven fuel monitoring, fuel theft detection and fleet management platform designed to help logistics companies monitor fuel consumption and identify suspicious fuel losses.

The system combines:

* 📡 IoT fuel sensors
* 📊 Real-time telemetry
* 🚨 Automated anomaly detection
* 🚛 Fleet management
* 👨‍✈️ Driver management
* 🖥️ Fleet management dashboards
* 🔐 Secure authentication
* 🗄️ Centralized cloud database

FuelGuard transforms raw fuel sensor data into actionable fleet intelligence.

Instead of relying only on manual fuel checks, fleet managers can monitor vehicle fuel levels and receive alerts when abnormal fuel loss is detected.

---

# 🎯 Problem Statement

Fuel theft and unexplained fuel losses can significantly affect logistics operations.

Traditional monitoring methods often rely on:

* Manual fuel checks
* Driver reports
* Fuel receipts
* Periodic inspections
* Basic fuel gauges

These methods can make it difficult to identify exactly **when**, **where** and **how** fuel was lost.

FuelGuard addresses this problem by continuously monitoring fuel levels through IoT sensors.

---

# 💡 Solution

FuelGuard connects fuel sensors installed on fleet vehicles to a centralized monitoring platform.

```text
Fuel Sensor
     │
     ▼
IoT Gateway
     │
     ▼
Telemetry Processing
     │
     ▼
Anomaly Detection
     │
     ▼
FuelGuard API
     │
     ▼
Supabase Database
     │
     ▼
Fleet Dashboard
     │
     ▼
Alerts & Reports
```

The system continuously evaluates fuel-level changes and identifies abnormal drops that may indicate possible siphoning or unauthorized fuel loss.

---

# 🚀 Core Features

## ⛽ Real-Time Fuel Monitoring

FuelGuard receives telemetry from ultrasonic fuel sensors installed on fleet vehicles.

The platform can monitor:

* Current fuel level
* Fuel percentage
* Fuel consumption
* Fuel level changes
* Vehicle status
* Sensor status
* Telemetry timestamps

---

## 🚨 Fuel Theft Detection

FuelGuard uses automated logic to identify suspicious fuel-level drops.

The detection system considers factors such as:

* Fuel drop magnitude
* Rate of fuel loss
* Time between readings
* Vehicle operating state
* Expected fuel consumption
* Sensor readings
* Historical patterns

Example:

```text
Normal Fuel Change
        │
        ▼
   Small Reduction
        │
        ▼
 Expected Consumption
        │
        ▼
      NORMAL
```

Suspicious event:

```text
Fuel Level
    │
100%│
    │
 80%│
    │
 60%│
    │
 40%│
    │
 20%│
    │
  0%└──────────────────
        Rapid Drop
             │
             ▼
      Anomaly Detection
             │
             ▼
        🚨 ALERT
```

---

# 🚛 Fleet Management

FuelGuard provides fleet managers with a centralized registry for managing fleet assets.

### Vehicles

Managers can manage:

* Vehicle registration
* Registration number
* Vehicle type
* Fuel capacity
* Fuel sensor
* Current fuel level
* Vehicle status
* Assigned driver

### Drivers

Managers can manage:

* Driver profiles
* Driver assignments
* Vehicle assignments
* Driver activity
* Driver status

### IoT Hardware

The platform can maintain information about:

* Fuel sensors
* Sensor identifiers
* Gateway devices
* Device status
* Device assignments
* Last communication time

---

# 👥 User Roles

FuelGuard separates functionality according to user responsibilities.

## 👔 Fleet Manager

Fleet managers have access to the complete fleet management environment.

They can:

* View all vehicles
* Monitor fuel levels
* Monitor fuel alerts
* Manage drivers
* Manage vehicles
* Manage IoT devices
* Investigate fuel anomalies
* View historical data
* Monitor fleet performance

---

## 👨‍✈️ Driver

Authorized drivers receive a restricted interface focused on their assigned vehicle.

Depending on permissions, drivers can:

* View assigned vehicle
* View fuel level
* View vehicle status
* View relevant alerts
* Review fuel activity

Drivers do not have access to fleet-wide administrative information.

---

# 📊 Dashboards

FuelGuard provides specialized dashboards for different user types.

## 👔 Fleet Manager Dashboard

The fleet manager dashboard provides a centralized view of fleet activity.

Example dashboard information:

```text
┌────────────────────────────────────────────┐
│              FUELGUARD                     │
│          FLEET MANAGER                     │
├────────────────────────────────────────────┤
│                                            │
│  🚛 Total Vehicles          42              │
│  ⛽ Vehicles Online         38              │
│  🚨 Active Alerts            3              │
│  📊 Avg Fuel Level          68%             │
│                                            │
├────────────────────────────────────────────┤
│                                            │
│  Recent Fuel Alerts                         │
│                                            │
│  🚨 Truck KZN-245                           │
│     Rapid fuel decrease                     │
│                                            │
│  ⚠️ Truck GP-781                            │
│     Unusual consumption                     │
│                                            │
└────────────────────────────────────────────┘
```

---

## 👨‍✈️ Driver Dashboard

The driver interface focuses on information relevant to the driver's assigned vehicle.

```text
┌─────────────────────────────┐
│       MY VEHICLE            │
├─────────────────────────────┤
│                             │
│ 🚛 Vehicle: KZN-245         │
│                             │
│ ⛽ Fuel Level                │
│ ████████████░░ 78%          │
│                             │
│ 📍 Vehicle Status: Active   │
│                             │
│ 🟢 Sensor: Online           │
│                             │
└─────────────────────────────┘
```

---

# ⛽ Real-Time Fuel Monitoring

FuelGuard receives sensor readings continuously.

A typical telemetry record may contain:

```json
{
  "vehicle_id": "TRUCK-001",
  "sensor_id": "SENSOR-001",
  "fuel_level": 68.5,
  "fuel_percentage": 68,
  "timestamp": "2026-08-18T08:30:00Z"
}
```

The IoT gateway validates incoming data before forwarding it to the backend.

---

# 📡 IoT Architecture

FuelGuard uses an IoT gateway to receive and process fuel sensor telemetry.

```text
┌─────────────────────┐
│  Ultrasonic Sensor  │
│                     │
│  Fuel Level Data    │
└──────────┬──────────┘
           │
           │ Telemetry
           ▼
┌─────────────────────┐
│     IoT Gateway     │
│      FastAPI        │
└──────────┬──────────┘
           │
           │ Validated Data
           ▼
┌─────────────────────┐
│   Detection Engine  │
│                     │
│ Threshold Analysis  │
│ Physics-Based Logic │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│     Backend API     │
│        PHP          │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│      Supabase       │
│     PostgreSQL      │
└─────────────────────┘
```

---

# 🏗️ System Architecture

FuelGuard follows a layered architecture.

```text
┌───────────────────────────────────────────────┐
│                 PRESENTATION                  │
├───────────────────────────────────────────────┤
│ Fleet Manager Dashboard │ Driver Dashboard   │
│              HTML / CSS / JavaScript         │
└───────────────────────┬───────────────────────┘
                        │
                        ▼
┌───────────────────────────────────────────────┐
│                    API                        │
├───────────────────────────────────────────────┤
│              PHP Backend API                  │
│ Authentication │ Fleet │ Alerts │ Telemetry  │
└───────────────────────┬───────────────────────┘
                        │
                        ▼
┌───────────────────────────────────────────────┐
│                IoT GATEWAY                    │
├───────────────────────────────────────────────┤
│             Python + FastAPI                  │
│     Pydantic Validation + Telemetry           │
└───────────────────────┬───────────────────────┘
                        │
                        ▼
┌───────────────────────────────────────────────┐
│                  DATA                         │
├───────────────────────────────────────────────┤
│             Supabase PostgreSQL               │
└───────────────────────────────────────────────┘
```

---

# 🔄 Data Flow

The complete telemetry lifecycle follows this process:

```text
1. Fuel Sensor
       ↓
2. Sensor Measures Fuel Level
       ↓
3. IoT Gateway Receives Reading
       ↓
4. Pydantic Validates Data
       ↓
5. Detection Engine Analyses Reading
       ↓
6. Backend API Processes Data
       ↓
7. Supabase Stores Telemetry
       ↓
8. Dashboard Retrieves Data
       ↓
9. Fleet Manager Monitors Vehicle
       ↓
10. Alert Generated if Anomaly Detected
```

---

# 🗄️ Database Architecture

FuelGuard uses **Supabase PostgreSQL** as its centralized database platform.

The database stores information relating to:

* Users
* Vehicles
* Drivers
* Sensors
* IoT devices
* Telemetry
* Fuel readings
* Fuel alerts
* Fleet activity
* Authentication data

### Conceptual Relationships

```text
User
 │
 ├── Fleet Manager
 │
 └── Driver
        │
        ▼
     Vehicle
        │
        ├── Fuel Sensor
        │
        ├── Telemetry
        │
        ├── Fuel Readings
        │
        └── Fuel Alerts
```

---

# 🛠️ Technology Stack

| Layer             | Technology          | Purpose                  |
| ----------------- | ------------------- | ------------------------ |
| IoT Gateway       | Python              | Sensor communication     |
| API Framework     | FastAPI             | IoT gateway API          |
| Validation        | Pydantic            | Data validation          |
| Backend           | PHP                 | Application API          |
| Database          | Supabase PostgreSQL | Centralized data storage |
| Frontend          | HTML5               | Application structure    |
| Styling           | CSS3                | Interface design         |
| Frontend Logic    | JavaScript ES6+     | Application behaviour    |
| Containers        | Docker              | Deployment environment   |
| CI/CD             | GitHub Actions      | Automated workflows      |
| Authentication    | API Keys + Sessions | Access control           |
| Password Security | Bcrypt              | Password hashing         |

---

# 📂 Project Structure

```text
FuelGuard/
│
├── gateway/
│   ├── api.py
│   ├── models.py
│   ├── services/
│   └── sensors/
│
├── backend/
│   ├── api/
│   ├── controllers/
│   ├── middleware/
│   ├── services/
│   └── config/
│
├── frontend/
│   ├── manager/
│   │   ├── index.html
│   │   ├── css/
│   │   └── js/
│   │
│   └── driver/
│       ├── index.html
│       ├── css/
│       └── js/
│
├── simulator/
│   └── sensor_simulator.py
│
├── database/
│   └── schema.sql
│
├── scripts/
│   └── init_db.py
│
├── tests/
│
├── .github/
│   └── workflows/
│
├── Dockerfile
├── docker-compose.yml
├── .env.example
├── requirements.txt
└── README.md
```

---

# 🚀 Getting Started

## 📋 Prerequisites

Make sure the following are installed:

* Python 3.10+
* PHP 8+
* Git
* Docker
* Docker Compose
* Supabase account

Verify Python:

```bash
python --version
```

Verify PHP:

```bash
php --version
```

Verify Docker:

```bash
docker --version
```

---

# 📥 Installation

Clone the repository:

```bash
git clone https://github.com/your-username/FuelGuard.git
```

Navigate into the project:

```bash
cd FuelGuard
```

Install Python dependencies:

```bash
pip install -r requirements.txt
```

---

# 🗄️ Database Setup

Create a Supabase project and obtain the required credentials.

Initialize the database schema:

```bash
python init_db.py
```

The database should contain the required tables for:

* Users
* Vehicles
* Drivers
* Sensors
* Telemetry
* Alerts
* Fleet management

---

# ⚙️ Environment Configuration

Create a `.env` file in the project root.

Example:

```env
SUPABASE_URL=your_supabase_url
SUPABASE_KEY=your_supabase_anon_key
GATEWAY_API_KEY=your_secure_api_key
```

### ⚠️ Important

Never commit your `.env` file to GitHub.

Add it to `.gitignore`:

```text
.env
```

Use `.env.example` to document required environment variables without exposing credentials.

---

# ▶️ Running the Application

## 🐳 Using Docker

The recommended approach is Docker.

```bash
docker-compose up -d
```

Check running containers:

```bash
docker-compose ps
```

Stop the services:

```bash
docker-compose down
```

---

## 🐍 Manual Start

### IoT Gateway

Run the FastAPI gateway:

```bash
uvicorn gateway.api:app --reload
```

The gateway provides the interface for receiving and validating sensor telemetry.

---

### Frontend

The frontend can be served using:

* Apache
* Nginx
* PHP development server
* VS Code Live Server
* Docker

Example PHP server:

```bash
php -S localhost:8000
```

---

# 🧪 Sensor Simulation

FuelGuard includes a simulator for testing the system without physical IoT hardware.

Run the multi-truck simulator:

```bash
python -m simulator.sensor_simulator --multi
```

The simulator can generate telemetry for multiple vehicles.

Example:

```text
Truck 001
Fuel: 82%

Truck 002
Fuel: 64%

Truck 003
Fuel: 41%

Truck 004
Fuel: 76%
```

This allows developers to test:

* Real-time monitoring
* Fuel level changes
* Anomaly detection
* Multiple vehicles
* Alert generation
* Dashboard updates

---

# 🚨 Testing Fuel Theft Detection

A simulated fuel siphoning event can be used to test the detection engine.

Example:

```text
Normal Reading
      │
      ▼
Fuel: 72%
      │
      ▼
Fuel: 71%
      │
      ▼
Fuel: 70%
      │
      ▼
Rapid Drop
      │
      ▼
Fuel: 52%
      │
      ▼
Detection Engine
      │
      ▼
🚨 SUSPICIOUS FUEL LOSS
```

The event can then be recorded and displayed on the fleet manager dashboard.

---

# 🔐 Security

FuelGuard is designed with security as a core requirement.

## 🔑 API Authentication

Protected API endpoints use API key authentication.

Example:

```http
X-API-Key: your_secure_api_key
```

---

## 👤 Session Validation

Authenticated users are protected using stateful session validation.

This prevents unauthorized access to protected dashboards and APIs.

---

## 🔒 Password Security

User passwords should never be stored as plain text.

FuelGuard uses **Bcrypt** password hashing.

```text
Plain Password
      ↓
   Bcrypt
      ↓
Hashed Password
      ↓
Database
```

---

## 🧪 Input Validation

Incoming data is validated before processing.

Pydantic models are used by the FastAPI IoT gateway to validate telemetry.

Example:

```python
class FuelReading(BaseModel):
    vehicle_id: str
    sensor_id: str
    fuel_level: float
    timestamp: datetime
```

Additional validation can be applied to:

* API requests
* User input
* Vehicle identifiers
* Sensor identifiers
* Fuel readings
* Authentication credentials

---

# 🐳 Docker

Docker provides a consistent environment for running FuelGuard services.

A typical deployment can include:

```text
┌────────────────────────────┐
│       Docker Compose       │
├────────────────────────────┤
│                            │
│  FastAPI IoT Gateway       │
│                            │
│  PHP Backend API           │
│                            │
│  Frontend                  │
│                            │
└──────────────┬─────────────┘
               │
               ▼
       Supabase PostgreSQL
```

---

# ⚙️ CI/CD

FuelGuard can use GitHub Actions to automate:

* Code validation
* Testing
* Docker builds
* Deployment
* Environment checks

Example workflow:

```text
Developer
    │
    ▼
Git Push
    │
    ▼
GitHub
    │
    ▼
GitHub Actions
    │
    ├── Tests
    │
    ├── Validation
    │
    ├── Build
    │
    └── Deploy
```

---

# 📈 Future Improvements

FuelGuard can be expanded with additional capabilities.

## 🗺️ GPS Tracking

* Real-time vehicle locations
* Route history
* Geofencing
* Unauthorized route detection

## 📊 Advanced Analytics

* Fuel consumption trends
* Driver behaviour analysis
* Vehicle efficiency reports
* Fuel cost analysis
* Fleet performance analytics

## 🤖 Machine Learning

Future versions could use machine learning to identify complex fuel consumption patterns.

Potential models could include:

* Anomaly detection
* Time-series forecasting
* Consumption prediction
* Driver behaviour classification

---

## 📱 Mobile Application

A dedicated mobile application could provide:

### Fleet Managers

* Real-time alerts
* Fleet overview
* Vehicle tracking
* Fuel monitoring

### Drivers

* Assigned vehicle
* Fuel level
* Alerts
* Vehicle information

---

## 🔔 Advanced Alerts

Future notification channels could include:

* Email
* SMS
* Push notifications
* WhatsApp
* In-app notifications

Example:

```text
🚨 FUELGUARD ALERT

Vehicle: KZN-245
Fuel Drop: 18%
Time: 14:32
Status: Suspicious

Immediate investigation recommended.
```

---

# 📊 Future Analytics Dashboard

A future FuelGuard analytics platform could provide:

```text
┌────────────────────────────────────────────┐
│              FLEET ANALYTICS               │
├────────────────────────────────────────────┤
│                                            │
│ Fuel Consumption                           │
│ ████████████████████                      │
│                                            │
│ Fuel Loss Events                           │
│ ████████                                  │
│                                            │
│ Vehicle Efficiency                         │
│ █████████████████                         │
│                                            │
│ Driver Performance                         │
│ ███████████████████                       │
│                                            │
└────────────────────────────────────────────┘
```

---

# 🎯 Project Vision

FuelGuard aims to evolve from a basic fuel monitoring solution into a complete **fleet intelligence platform**.

The long-term vision is:

```text
                   FUELGUARD
                       │
        ┌──────────────┼──────────────┐
        │              │              │
        ▼              ▼              ▼
   Fuel Monitoring  Fleet Management  Security
        │              │              │
        └──────────────┼──────────────┘
                       │
                       ▼
                 Data Analytics
                       │
                       ▼
                Machine Learning
                       │
                       ▼
              Intelligent Fleet
                  Management
```

The platform can eventually help logistics organizations move from **reactive fuel loss investigation** to **proactive fuel intelligence**.

---

# 📌 Key Project Principles

FuelGuard is built around five core principles:

| Principle          | Description                            |
| ------------------ | -------------------------------------- |
| 📡 **Real-Time**   | Continuously monitor fuel telemetry    |
| 🚨 **Proactive**   | Detect suspicious fuel behaviour early |
| 🔐 **Secure**      | Protect users, APIs and fleet data     |
| 📊 **Data-Driven** | Turn telemetry into useful insights    |
| 📈 **Scalable**    | Support growing fleets and IoT devices |

---

# 🏁 Project Status

**Development Status:** 🚧 Active Development

Current development areas include:

* IoT telemetry
* Fuel monitoring
* Fuel anomaly detection
* Fleet management
* Driver management
* Dashboard development
* API development
* Database integration
* Security
* Sensor simulation

---

# 📄 License

This project is proprietary software.

Copyright © 2026 FuelGuard.

All rights reserved.

Unauthorized copying, modification, distribution or commercial use is prohibited.

---

<div align="center">

# 🚛⛽ FuelGuard

### *Monitor. Detect. Protect.*

**IoT • Fuel Intelligence • Fleet Security**

<br>

Built for precision.
Deployed for security.

</div>
