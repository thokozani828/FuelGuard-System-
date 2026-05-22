# api.py
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from typing import Dict, List, Optional
from collections import defaultdict
from datetime import datetime
import logging

from .models import TelemetryData, FuelLevelResponse, SensorType
from .fuel_engine import FuelLevelEngine, TankConfig
from .supabase_rest import store_telemetry, get_latest_reading, get_history, store_alert

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="FuelGuard IoT Gateway", version="1.0.0")

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Tank configurations - Add your fleet trucks here
TANK_CONFIGS = {
    "SENSOR_001": TankConfig(  # Map sensor to tank config
        tank_id="TRK001",
        tank_height_cm=120.0,
        tank_cross_section_area_m2=2.5,
        dead_zone_cm=5.0,
        max_capacity_liters=3000.0
    ),
    "SENSOR_002": TankConfig(
        tank_id="TRK002",
        tank_height_cm=130.0,
        tank_cross_section_area_m2=2.8,
        dead_zone_cm=5.0,
        max_capacity_liters=3500.0
    ),
    "SENSOR_003": TankConfig(
        tank_id="TRK003",
        tank_height_cm=115.0,
        tank_cross_section_area_m2=2.4,
        dead_zone_cm=5.0,
        max_capacity_liters=2800.0
    ),
    "SENSOR_004": TankConfig(
        tank_id="TRK004",
        tank_height_cm=140.0,
        tank_cross_section_area_m2=3.0,
        dead_zone_cm=5.0,
        max_capacity_liters=4000.0
    ),
    "SENSOR_005": TankConfig(
        tank_id="TRK005",
        tank_height_cm=125.0,
        tank_cross_section_area_m2=2.6,
        dead_zone_cm=5.0,
        max_capacity_liters=3200.0
    ),
    "tank_001": TankConfig(  # Keep original for compatibility
        tank_id="tank_001",
        tank_height_cm=120.0,
        tank_cross_section_area_m2=2.5,
        dead_zone_cm=5.0,
        max_capacity_liters=3000.0
    ),
    "tank_002": TankConfig(
        tank_id="tank_002",
        tank_height_cm=150.0,
        tank_cross_section_area_m2=3.0,
        dead_zone_cm=8.0,
        max_capacity_liters=4500.0
    )
}

# Helper function to get truck info from sensor ID
def get_truck_info_from_sensor(device_id: str) -> Dict[str, Optional[str]]:
    """Map sensor ID to truck information"""
    sensor_to_truck = {
        "SENSOR_001": {"truck_id": "TRK001", "license_plate": "ABC-1234", "driver_name": "John Smith"},
        "SENSOR_002": {"truck_id": "TRK002", "license_plate": "XYZ-5678", "driver_name": "Sarah Johnson"},
        "SENSOR_003": {"truck_id": "TRK003", "license_plate": "DEF-9012", "driver_name": "Mike Wilson"},
        "SENSOR_004": {"truck_id": "TRK004", "license_plate": "GHI-3456", "driver_name": "Emma Brown"},
        "SENSOR_005": {"truck_id": "TRK005", "license_plate": "JKL-7890", "driver_name": "David Lee"},
        "tank_001": {"truck_id": "TRK001", "license_plate": "ABC-1234", "driver_name": "John Smith"},
        "tank_002": {"truck_id": "TRK002", "license_plate": "XYZ-5678", "driver_name": "Sarah Johnson"},
    }
    return sensor_to_truck.get(device_id, {})

# Initialize engine
fuel_engine = FuelLevelEngine(TANK_CONFIGS)

# In-memory storage for recent readings (for smoothing)
recent_readings: Dict[str, List[float]] = {}

@app.post("/api/logs", response_model=FuelLevelResponse)
async def ingest_telemetry(telemetry: TelemetryData):
    try:
        logger.info(f"Received telemetry from {telemetry.device_id}: {telemetry.raw_value}cm")
        
        # Get truck information for this sensor
        truck_info = get_truck_info_from_sensor(telemetry.device_id)
        truck_id = truck_info.get("truck_id")
        license_plate = truck_info.get("license_plate")
        driver_name = truck_info.get("driver_name")
        
        # Store recent reading for smoothing
        if telemetry.device_id not in recent_readings:
            recent_readings[telemetry.device_id] = []
        recent_readings[telemetry.device_id].append(telemetry.raw_value)
        
        # Keep last 10 readings
        if len(recent_readings[telemetry.device_id]) > 10:
            recent_readings[telemetry.device_id].pop(0)
        
        # Apply smoothing
        smoothed_distance = fuel_engine.smooth_readings(recent_readings[telemetry.device_id])
        
        # Create copy with smoothed value and truck info
        smoothed_telemetry = TelemetryData(
            device_id=telemetry.device_id,
            sensor_type=telemetry.sensor_type,
            raw_value=smoothed_distance,
            temperature=telemetry.temperature,
            timestamp=telemetry.timestamp
        )
        
        # Calculate fuel level
        fuel_level = fuel_engine.calculate_fuel_level(smoothed_telemetry)
        
        if not fuel_level:
            raise HTTPException(status_code=404, detail=f"Device {telemetry.device_id} not configured")
        
        # Add truck info to response
        fuel_level.truck_id = truck_id
        fuel_level.license_plate = license_plate
        fuel_level.driver_name = driver_name
        
        # Prepare data for Supabase
        supabase_data = {
            "device_id": telemetry.device_id,
            "sensor_type": telemetry.sensor_type.value if hasattr(telemetry.sensor_type, 'value') else str(telemetry.sensor_type),
            "raw_value_cm": telemetry.raw_value,
            "temperature_c": telemetry.temperature,
            "fuel_level_cm": fuel_level.fuel_level_cm,
            "fuel_percentage": fuel_level.fuel_level_percentage,
            "fuel_volume_liters": fuel_level.fuel_volume_liters,
            "status": fuel_level.status,
            "truck_id": truck_id,
            "driver_name": driver_name,
            "license_plate": license_plate,
            "timestamp": telemetry.timestamp.isoformat() if hasattr(telemetry.timestamp, 'isoformat') else str(telemetry.timestamp)
        }
        
        # Store in Supabase (cloud database)
        try:
            store_telemetry(supabase_data)
            logger.info(f"Stored telemetry for {telemetry.device_id} in Supabase")
        except Exception as e:
            logger.error(f"Failed to store in Supabase: {e}")
            # Continue even if Supabase fails
        
        # Check for alerts
        if fuel_level.status in ["CRITICAL", "EMPTY"]:
            alert_msg = f"Fuel level at {fuel_level.fuel_level_percentage:.1f}%"
            if truck_id:
                alert_msg = f"Truck {truck_id}: {alert_msg}"
            logger.warning(f"⚠️ ALERT: {alert_msg}")
            # Store alert in Supabase
            try:
                store_alert(telemetry.device_id, fuel_level.status, alert_msg, truck_id)
            except Exception as e:
                logger.error(f"Failed to store alert: {e}")
        
        return fuel_level
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error processing telemetry: {str(e)}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/api/logs/{device_id}/latest")
async def get_latest_reading_endpoint(device_id: str):
    """Get latest reading from Supabase"""
    try:
        result = get_latest_reading(device_id)
        if result:
            return result
        raise HTTPException(status_code=404, detail="No data found")
    except Exception as e:
        logger.error(f"Error getting latest reading: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/api/logs/{device_id}/history")
async def get_history_endpoint(device_id: str, limit: int = 100):
    """Get historical data from Supabase"""
    try:
        result = get_history(device_id, limit)
        if result:
            return {
                "device_id": device_id,
                "count": len(result),
                "data": result
            }
        raise HTTPException(status_code=404, detail="No data found")
    except Exception as e:
        logger.error(f"Error getting history: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/api/fleet/status")
async def get_fleet_status():
    """Get current fuel status for all trucks in fleet"""
    try:
        from .supabase_rest import get_fleet_status as get_supabase_fleet_status
        fleet_data = get_supabase_fleet_status()
        return {
            "timestamp": datetime.now().isoformat(),
            "total_trucks": len(fleet_data),
            "trucks": fleet_data
        }
    except Exception as e:
        logger.error(f"Error getting fleet status: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/api/fleet/truck/{truck_id}")
async def get_truck_status(truck_id: str):
    """Get fuel status for a specific truck"""
    try:
        from .supabase_rest import get_truck_summary
        truck_data = get_truck_summary(truck_id)
        if truck_data:
            return truck_data
        raise HTTPException(status_code=404, detail=f"Truck {truck_id} not found")
    except Exception as e:
        logger.error(f"Error getting truck status: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/api/devices")
async def list_devices():
    return {"devices": list(TANK_CONFIGS.keys())}

@app.get("/api/health")
async def health_check():
    try:
        # Check Supabase connection
        from .supabase_rest import SUPABASE_AVAILABLE
        db_status = "Connected" if SUPABASE_AVAILABLE else "Not connected"
    except:
        db_status = "Unknown"
    
    return {
        "status": "healthy",
        "timestamp": datetime.now().isoformat(),
        "database": f"Supabase: {db_status}",
        "active_sensors": len(TANK_CONFIGS)
    }

@app.get("/")
async def root():
    return {
        "message": "FuelGuard IoT Gateway with Supabase Database",
        "version": "1.0.0",
        "endpoints": {
            "POST /api/logs": "Send telemetry data",
            "GET /api/logs/{device_id}/latest": "Get latest reading",
            "GET /api/logs/{device_id}/history": "Get historical data",
            "GET /api/fleet/status": "Get all truck statuses",
            "GET /api/fleet/truck/{truck_id}": "Get specific truck status",
            "GET /api/devices": "List all devices",
            "GET /api/health": "Health check"
        }
    }