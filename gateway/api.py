# gateway/api.py
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from typing import Dict, List, Optional
from collections import defaultdict
from datetime import datetime
import logging
from pydantic import BaseModel, Field

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

# Pydantic models for request validation
class TelemetryData(BaseModel):
    device_id: str
    sensor_type: str = "ultrasonic"
    raw_value_cm: float = Field(..., alias="raw_value_cm")  # Accept raw_value_cm
    temperature_c: float = Field(..., alias="temperature_c")
    timestamp: Optional[str] = None
    truck_id: Optional[str] = None
    license_plate: Optional[str] = None
    driver_name: Optional[str] = None
    fuel_level_cm: Optional[float] = None
    fuel_percentage: Optional[float] = None
    fuel_volume_liters: Optional[float] = None
    tank_capacity_liters: Optional[float] = None
    status: Optional[str] = None
    # Location fields
    latitude: Optional[float] = None
    longitude: Optional[float] = None
    location_name: Optional[str] = None
    odometer_km: Optional[float] = None
    speed_kmh: Optional[float] = None
    trip_distance_km: Optional[float] = None
    
    class Config:
        populate_by_name = True  # Allow both field name and alias

class FuelLevelResponse(BaseModel):
    device_id: str
    fuel_level_cm: float
    fuel_level_percentage: float
    fuel_volume_liters: float
    status: str
    truck_id: Optional[str] = None
    license_plate: Optional[str] = None
    driver_name: Optional[str] = None
    timestamp: str

# Import Supabase functions
from gateway.supabase_rest import store_telemetry, get_latest_reading, get_history, store_alert, SUPABASE_AVAILABLE

@app.post("/api/logs")
async def ingest_telemetry(telemetry: TelemetryData):
    """Receive telemetry data from sensors"""
    try:
        logger.info(f"📡 Received telemetry from {telemetry.device_id}")
        
        # Calculate fuel metrics if not provided
        fuel_percentage = telemetry.fuel_percentage
        fuel_volume = telemetry.fuel_volume_liters
        fuel_level = telemetry.fuel_level_cm
        status = telemetry.status
        
        # If fuel metrics not provided, calculate them
        if fuel_percentage is None and telemetry.tank_capacity_liters:
            # Calculate from raw value (distance from top)
            tank_height = 120.0  # Default tank height
            fuel_level = tank_height - telemetry.raw_value_cm
            fuel_percentage = (fuel_level / tank_height) * 100
            fuel_volume = (fuel_percentage / 100) * telemetry.tank_capacity_liters
            
            # Determine status
            if fuel_percentage >= 75:
                status = "NORMAL"
            elif fuel_percentage >= 50:
                status = "NORMAL"
            elif fuel_percentage >= 25:
                status = "WARNING"
            elif fuel_percentage >= 10:
                status = "LOW"
            else:
                status = "CRITICAL"
        
        # Prepare data for storage
        storage_data = {
            "device_id": telemetry.device_id,
            "sensor_type": telemetry.sensor_type,
            "raw_value_cm": telemetry.raw_value_cm,
            "temperature_c": telemetry.temperature_c,
            "fuel_level_cm": fuel_level or telemetry.fuel_level_cm,
            "fuel_percentage": fuel_percentage,
            "fuel_volume_liters": fuel_volume,
            "status": status,
            "truck_id": telemetry.truck_id,
            "driver_name": telemetry.driver_name,
            "license_plate": telemetry.license_plate,
            "tank_capacity_liters": telemetry.tank_capacity_liters,
            "timestamp": telemetry.timestamp or datetime.utcnow().isoformat(),
            # Location data
            "latitude": telemetry.latitude,
            "longitude": telemetry.longitude,
            "location_name": telemetry.location_name,
            "odometer_km": telemetry.odometer_km,
            "speed_kmh": telemetry.speed_kmh,
            "trip_distance_km": telemetry.trip_distance_km
        }
        
        # Remove None values
        storage_data = {k: v for k, v in storage_data.items() if v is not None}
        
        # Store in Supabase
        supabase_success = False
        if SUPABASE_AVAILABLE:
            supabase_success = store_telemetry(storage_data)
        
        # Check for alerts
        if fuel_percentage and fuel_percentage < 15:
            alert_type = "CRITICAL" if fuel_percentage < 10 else "LOW"
            alert_msg = f"Fuel level at {fuel_percentage:.1f}%"
            if telemetry.truck_id:
                alert_msg = f"Truck {telemetry.truck_id}: {alert_msg}"
            
            logger.warning(f"⚠️ ALERT: {alert_msg}")
            
            # Store alert
            if SUPABASE_AVAILABLE:
                store_alert(
                    telemetry.device_id,
                    alert_type,
                    alert_msg,
                    telemetry.truck_id
                )
        
        # Prepare response
        response = FuelLevelResponse(
            device_id=telemetry.device_id,
            fuel_level_cm=fuel_level or 0,
            fuel_level_percentage=fuel_percentage or 0,
            fuel_volume_liters=fuel_volume or 0,
            status=status or "UNKNOWN",
            truck_id=telemetry.truck_id,
            license_plate=telemetry.license_plate,
            driver_name=telemetry.driver_name,
            timestamp=datetime.utcnow().isoformat()
        )
        
        logger.info(f"✅ Processed telemetry for {telemetry.device_id} - Fuel: {fuel_percentage:.1f}%")
        
        return {
            "status": "success",
            "message": "Telemetry processed",
            "data": response.dict(),
            "supabase_stored": supabase_success
        }
        
    except Exception as e:
        logger.error(f"❌ Error processing telemetry: {str(e)}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/api/logs/{device_id}/latest")
async def get_latest_reading_endpoint(device_id: str):
    """Get latest reading from Supabase"""
    try:
        result = get_latest_reading(device_id)
        if result:
            return {
                "status": "success",
                "data": result
            }
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
                "status": "success",
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
        from gateway.supabase_rest import get_fleet_status as get_supabase_fleet_status
        fleet_data = get_supabase_fleet_status(display=False)
        
        # Add summary
        total_trucks = len(fleet_data)
        avg_fuel = sum(t.get('fuel_percentage', 0) for t in fleet_data) / total_trucks if total_trucks > 0 else 0
        critical = sum(1 for t in fleet_data if t.get('fuel_percentage', 0) < 15)
        low = sum(1 for t in fleet_data if 15 <= t.get('fuel_percentage', 0) < 30)
        
        return {
            "status": "success",
            "timestamp": datetime.now().isoformat(),
            "summary": {
                "total_trucks": total_trucks,
                "average_fuel_percentage": round(avg_fuel, 1),
                "critical_fuel": critical,
                "low_fuel": low
            },
            "trucks": fleet_data
        }
    except Exception as e:
        logger.error(f"Error getting fleet status: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/api/fleet/truck/{truck_id}")
async def get_truck_status(truck_id: str):
    """Get fuel status for a specific truck"""
    try:
        from gateway.supabase_rest import get_truck_summary
        truck_data = get_truck_summary(truck_id)
        if truck_data:
            return {
                "status": "success",
                "data": truck_data
            }
        raise HTTPException(status_code=404, detail=f"Truck {truck_id} not found")
    except Exception as e:
        logger.error(f"Error getting truck status: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/api/alerts/active")
async def get_active_alerts():
    """Get all active alerts"""
    try:
        from gateway.supabase_rest import get_active_alerts
        alerts = get_active_alerts(display=False)
        return {
            "status": "success",
            "count": len(alerts),
            "alerts": alerts
        }
    except Exception as e:
        logger.error(f"Error getting alerts: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/api/health")
async def health_check():
    """Health check endpoint"""
    try:
        # Check Supabase connection
        from gateway.supabase_rest import SUPABASE_AVAILABLE, test_connection
        db_status = "Connected" if SUPABASE_AVAILABLE and test_connection() else "Not connected"
    except:
        db_status = "Unknown"
    
    return {
        "status": "healthy",
        "timestamp": datetime.now().isoformat(),
        "database": f"Supabase: {db_status}",
        "service": "FuelGuard IoT Gateway"
    }

@app.get("/")
async def root():
    return {
        "message": "FuelGuard IoT Gateway with Location Tracking",
        "version": "1.1.0",
        "endpoints": {
            "POST /api/logs": "Send telemetry data (with location)",
            "GET /api/logs/{device_id}/latest": "Get latest reading",
            "GET /api/logs/{device_id}/history": "Get historical data",
            "GET /api/fleet/status": "Get all truck statuses",
            "GET /api/fleet/truck/{truck_id}": "Get specific truck status",
            "GET /api/alerts/active": "Get active alerts",
            "GET /api/health": "Health check"
        }
    }

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000, reload=True)