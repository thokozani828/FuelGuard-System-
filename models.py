# models.py
from pydantic import BaseModel
from datetime import datetime
from enum import Enum
from typing import Optional

class SensorType(str, Enum):
    ULTRASONIC = "ultrasonic"
    RADAR = "radar"
    OPTICAL = "optical"
    CAPACITIVE = "capacitive"

class TelemetryData(BaseModel):
    device_id: str
    sensor_type: SensorType
    raw_value: float  # distance in cm from sensor to fuel
    temperature: Optional[float] = None
    timestamp: datetime

class FuelLevelResponse(BaseModel):
    device_id: str
    fuel_level_cm: float
    fuel_level_percentage: float
    fuel_volume_liters: float
    status: str  # NORMAL, LOW, CRITICAL, EMPTY, FULL
    timestamp: datetime

class TankConfig(BaseModel):
    tank_id: str
    tank_height_cm: float
    tank_cross_section_area_m2: float
    dead_zone_cm: float = 5.0
    max_capacity_liters: float